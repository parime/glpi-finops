<?php

/**
 * -------------------------------------------------------------------------
 * FinOps plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-finops
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Finops;

use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Finops\Compat\Base\RecordBase;
use GlpiPlugin\Finops\Install\CostTables;
use Session;

/**
 * Ressource facturée (instance, VM, base, disque...) vue dans les coûts importés : une par entité,
 * fournisseur et identifiant de ressource, créée par l'import. Elle peut être rattachée à la main à
 * un actif de GLPI (droit UPDATE du plugin et lecture de l'actif) ; l'actif reçoit alors un onglet
 * « FinOps » avec ses coûts. Rien n'est rattaché automatiquement.
 */
class Resource extends RecordBase
{
    public const RIGHTNAME = Menu::RIGHTNAME;

    /** Actifs auxquels une ressource peut être rattachée (onglet FinOps sur chacun). */
    public const ASSET_TYPES = ['Computer', 'Appliance', 'NetworkEquipment'];

    public const PAGE_SIZE = 50;

    public static function getTypeName($nb = 0)
    {
        return _n('Resource', 'Resources', $nb, 'finops');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-server-2';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, UPDATE);
    }

    public static function canPurge(): bool
    {
        return false;
    }

    /**
     * Rattache la ressource à un actif, ou retire le rattachement ($itemtype vide). Vérifie le droit
     * de modifier la ressource (son entité comprise) et le droit de lire l'actif.
     */
    public static function link(int $id, string $itemtype, int $itemsId): bool
    {
        $resource = new self();
        if (!$resource->can($id, UPDATE)) {
            return false;
        }
        if ($itemtype === '' || $itemsId <= 0) {
            return $resource->update(['id' => $id, 'itemtype' => null, 'items_id' => 0, 'users_id_link' => 0,
                'date_link' => null]);
        }
        if (!in_array($itemtype, self::ASSET_TYPES, true)) {
            return false;
        }
        $asset = getItemForItemtype($itemtype);
        if (!$asset instanceof CommonDBTM || !$asset->can($itemsId, READ)) {
            return false;
        }

        return $resource->update([
            'id'            => $id,
            'itemtype'      => $itemtype,
            'items_id'      => $itemsId,
            'users_id_link' => Session::getLoginUserID(),
            'date_link'     => $_SESSION['glpi_currenttime'],
        ]);
    }

    /**
     * Ressources des entités de la session, les plus récentes d'abord, avec leur coût.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function page(bool $unlinkedOnly, string $search, int $start): array
    {
        global $DB;

        $table = self::getTable();
        $where = (new DbUtils())->getEntitiesRestrictCriteria($table, '', '', false);
        if ($unlinkedOnly) {
            $where['items_id'] = 0;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = ['LIKE', '%' . $search . '%'];
            $where[] = ['OR' => ['resource' => $like, 'provider' => $like, 'account' => $like, 'service' => $like]];
        }
        $total = countElementsInTable($table, $where);
        $rows = [];
        $iterator = $DB->request([
            'FROM'  => $table,
            'WHERE' => $where,
            'ORDER' => ['last_seen DESC', 'id DESC'],
            'START' => max(0, $start),
            'LIMIT' => self::PAGE_SIZE,
        ]);
        foreach ($iterator as $row) {
            $rows[] = self::present($row);
        }

        return ['rows' => self::withCosts($rows), 'total' => $total];
    }

    /**
     * Une ressource présentée pour les gabarits, avec son coût.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function presentOne(array $row): array
    {
        return self::withCosts([self::present($row)])[0];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function present(array $row): array
    {
        $asset = null;
        if (!empty($row['itemtype']) && (int) $row['items_id'] > 0) {
            $item = getItemForItemtype((string) $row['itemtype']);
            if ($item instanceof CommonDBTM && $item->getFromDB((int) $row['items_id'])) {
                $asset = [
                    'type'     => $item::getTypeName(1),
                    'icon'     => $item::getIcon(),
                    'name'     => $item->getName(),
                    // Lien seulement vers un actif que l'utilisateur peut lire.
                    'url'      => $item->canViewItem() ? $item->getLinkURL() : '',
                ];
            }
        }

        return [
            'id'         => (int) $row['id'],
            'entities_id' => (int) $row['entities_id'],
            'entity'     => \Dropdown::getDropdownName('glpi_entities', (int) $row['entities_id']),
            'provider'   => (string) $row['provider'],
            'resource'   => (string) $row['resource'],
            'account'    => (string) $row['account'],
            'service'    => (string) $row['service'],
            'first_seen' => (string) $row['first_seen'],
            'last_seen'  => (string) $row['last_seen'],
            'itemtype'   => (string) ($row['itemtype'] ?? ''),
            'items_id'   => (int) $row['items_id'],
            'asset'      => $asset,
            'costs'      => [],
        ];
    }

    /**
     * Coût total de chaque ressource, par devise.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function withCosts(array $rows): array
    {
        global $DB;

        if ($rows === []) {
            return [];
        }
        $or = [];
        foreach ($rows as $row) {
            $or[] = ['entities_id' => $row['entities_id'], 'provider' => $row['provider'], 'resource' => $row['resource']];
        }
        $costs = [];
        $iterator = $DB->request([
            'SELECT'  => ['entities_id', 'provider', 'resource', 'currency', 'SUM' => 'cost AS total'],
            'FROM'    => CostTables::LINES,
            'WHERE'   => ['OR' => $or],
            'GROUPBY' => ['entities_id', 'provider', 'resource', 'currency'],
        ]);
        foreach ($iterator as $cost) {
            $key = $cost['entities_id'] . "\x1F" . $cost['provider'] . "\x1F" . $cost['resource'];
            $costs[$key][(string) $cost['currency']] = (float) $cost['total'];
        }
        foreach ($rows as &$row) {
            $row['costs'] = $costs[$row['entities_id'] . "\x1F" . $row['provider'] . "\x1F" . $row['resource']] ?? [];
        }

        return $rows;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof CommonDBTM || $item->isNewItem() || !Menu::canView()) {
            return '';
        }
        $count = $_SESSION['glpishow_count_on_tabs']
            ? countElementsInTable(self::getTable(), ['itemtype' => $item::class, 'items_id' => $item->getID()]
                + (new DbUtils())->getEntitiesRestrictCriteria(self::getTable(), '', '', false))
            : 0;

        return self::createTabEntry(Menu::getTypeName(), $count, static::class, Menu::getIcon());
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        // ajax/common.tabs.php sert le contenu sans passer par getTabNameForItem() : droit du
        // plugin et droit de lire l'actif vérifiés ici.
        if (!$item instanceof CommonDBTM || !Menu::canView() || !$item->can($item->getID(), READ)) {
            return false;
        }
        global $DB;

        $table = self::getTable();
        $rows = [];
        $iterator = $DB->request([
            'FROM'  => $table,
            'WHERE' => ['itemtype' => $item::class, 'items_id' => $item->getID()]
                + (new DbUtils())->getEntitiesRestrictCriteria($table, '', '', false),
            'ORDER' => 'provider',
        ]);
        foreach ($iterator as $row) {
            $rows[] = self::present($row);
        }
        TemplateRenderer::getInstance()->display('@finops/resource_tab.html.twig', [
            'rows'          => self::withCosts($rows),
            'months'        => CostReport::byMonthForResources($rows),
            'resources_url' => Menu::url(Menu::TAB_RESOURCES),
            'can_link'      => self::canUpdate(),
        ]);

        return true;
    }
}
