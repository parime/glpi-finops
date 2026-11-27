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
use DbUtils;
use ItemVirtualMachine;

/**
 * Rattachement assisté (issue #6) : des règles de correspondance, dans l'ordre réglé dans la
 * configuration, PROPOSENT un actif de GLPI pour chaque ressource non rattachée. Rien ne se
 * rattache tout seul : un utilisateur qui a le droit de modifier les ressources valide les
 * propositions (une à une ou en masse) depuis la page Ressources, et chaque validation repasse par
 * Resource::link() (entité de la ressource, lecture de l'actif).
 *
 * Une règle propose un actif seulement si elle en trouve exactement un, visible, ni supprimé ni
 * gabarit, dans l'entité de la ressource ; plusieurs actifs : la règle est ambiguë et la suivante
 * est essayée.
 */
final class AssetMatcher
{
    public const RULE_INSTANCE_ID = 'instance_id';
    public const RULE_VM_UUID = 'vm_uuid';
    public const RULE_SERIAL = 'serial';
    public const RULE_NAME = 'name';
    public const RULE_GLPI_ID_TAG = 'glpi_id_tag';

    /** Règles connues, dans l'ordre par défaut. */
    public const RULES = [
        self::RULE_INSTANCE_ID,
        self::RULE_VM_UUID,
        self::RULE_SERIAL,
        self::RULE_NAME,
        self::RULE_GLPI_ID_TAG,
    ];

    /** Étiquette qui donne l'actif de GLPI (« 12 » pour l'ordinateur 12, ou « Computer:12 »). */
    public const GLPI_ID_TAG = 'glpi_id';

    /** Étiquettes qui portent le nom de la machine chez le fournisseur. */
    private const NAME_TAGS = ['name', 'hostname'];

    /**
     * Libellés des règles, pour la configuration et les badges.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::RULE_INSTANCE_ID => __('Instance ID = UUID of the asset', 'finops'),
            self::RULE_VM_UUID     => __('UUID of a virtual machine = host of the virtual machine', 'finops'),
            self::RULE_SERIAL      => __('Serial number', 'finops'),
            self::RULE_NAME        => __('Name', 'finops'),
            self::RULE_GLPI_ID_TAG => __('glpi_id tag', 'finops'),
        ];
    }

    /**
     * Liste de règles saisie dans la configuration : clés connues seulement, sans doublon, dans
     * l'ordre saisi. Vide : aucune proposition.
     *
     * @return list<string>
     */
    public static function parseRules(string $value): array
    {
        $rules = [];
        foreach (preg_split('/[\s,;]+/', mb_strtolower($value)) ?: [] as $rule) {
            if (in_array($rule, self::RULES, true) && !in_array($rule, $rules, true)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return self::parseRules(Config::load()['match_rules']);
    }

    /**
     * Valeurs à chercher côté GLPI pour une ressource : son identifiant complet et son dernier
     * segment (« projects/p1/instances/i-0abc » ou un ARN donnent aussi « i-0abc »).
     *
     * @return list<string>
     */
    public static function keys(string $resource): array
    {
        $resource = trim($resource);
        if ($resource === '') {
            return [];
        }
        $keys = [$resource];
        $parts = preg_split('#[/:]#', $resource) ?: [];
        $last = trim((string) end($parts));
        if ($last !== '' && $last !== $resource) {
            $keys[] = $last;
        }

        return $keys;
    }

    /**
     * Ajoute à chaque ressource non rattachée sa proposition (clé « proposal », ou null) et
     * l'indication « ambiguous » quand une règle a trouvé plusieurs actifs.
     *
     * @param list<array<string, mixed>> $rows Ressources présentées par Resource.
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $rows): array
    {
        $rules = self::rules();
        foreach ($rows as &$row) {
            $row['proposal'] = null;
            $row['ambiguous'] = false;
            if ((int) $row['items_id'] > 0 || $rules === []) {
                continue;
            }
            $found = self::propose($row, $rules);
            $row['proposal'] = $found['proposal'];
            $row['ambiguous'] = $found['ambiguous'];
        }

        return $rows;
    }

    /**
     * Proposition pour une ressource (entities_id, resource, tags décodées).
     *
     * @param array<string, mixed> $resource
     * @param list<string>|null    $rules    Règles dans l'ordre ; null : celles de la configuration.
     * @return array{proposal: array<string, mixed>|null, ambiguous: bool}
     */
    public static function propose(array $resource, ?array $rules = null): array
    {
        $rules ??= self::rules();
        $entity = (int) $resource['entities_id'];
        /** @var array<string, string> $tags */
        $tags = is_array($resource['tags'] ?? null) ? $resource['tags'] : [];
        $keys = self::keys((string) $resource['resource']);
        $ambiguous = false;
        $labels = self::labels();
        foreach ($rules as $rule) {
            $matches = self::matches($rule, $keys, $tags, $entity);
            if (count($matches) > 1) {
                $ambiguous = true;
                continue;
            }
            if (count($matches) === 1) {
                [$itemtype, $id] = explode(':', $matches[0]);
                $item = getItemForItemtype($itemtype);
                if (!$item instanceof CommonDBTM || !$item->getFromDB((int) $id)) {
                    continue;
                }

                return ['ambiguous' => false, 'proposal' => [
                    'itemtype'   => $itemtype,
                    'items_id'   => (int) $id,
                    'value'      => $matches[0],
                    'type'       => $item::getTypeName(1),
                    'icon'       => $item::getIcon(),
                    'name'       => $item->getName(),
                    'url'        => $item->getLinkURL(),
                    'rule'       => $rule,
                    'rule_label' => $labels[$rule],
                ]];
            }
        }

        return ['proposal' => null, 'ambiguous' => $ambiguous];
    }

    /**
     * Valide les propositions choisies : chaque ressource est rattachée seulement si elle n'est pas
     * déjà rattachée et si la proposition recalculée est toujours celle affichée ($proposals :
     * identifiant de ressource => « Itemtype:id »).
     *
     * @param array<int|string, mixed> $proposals
     * @return array{linked: int, skipped: int}
     */
    public static function validate(array $proposals): array
    {
        $linked = 0;
        $skipped = 0;
        foreach ($proposals as $id => $value) {
            $resource = new Resource();
            if (!$resource->can((int) $id, UPDATE) || (int) $resource->fields['items_id'] > 0) {
                $skipped++;
                continue;
            }
            $row = $resource->fields;
            $row['tags'] = TagPolicy::decode(isset($row['tags']) ? (string) $row['tags'] : null);
            $proposal = self::propose($row)['proposal'];
            if ($proposal === null || $proposal['value'] !== (string) $value
                || !Resource::link((int) $id, $proposal['itemtype'], $proposal['items_id'])) {
                $skipped++;
                continue;
            }
            $linked++;
        }

        return ['linked' => $linked, 'skipped' => $skipped];
    }

    /**
     * Actifs trouvés par une règle, en « Itemtype:id » distincts.
     *
     * @param list<string>          $keys
     * @param array<string, string> $tags
     * @return list<string>
     */
    private static function matches(string $rule, array $keys, array $tags, int $entity): array
    {
        switch ($rule) {
            case self::RULE_INSTANCE_ID:
                return self::byField(['Computer', 'NetworkEquipment'], 'uuid', $keys, $entity);
            case self::RULE_VM_UUID:
                return self::byVirtualMachine($keys, $entity);
            case self::RULE_SERIAL:
                return self::byField(Resource::ASSET_TYPES, 'serial', $keys, $entity);
            case self::RULE_NAME:
                $names = $keys;
                foreach (self::NAME_TAGS as $tag) {
                    $value = TagPolicy::value($tags, $tag);
                    if ($value !== '') {
                        $names[] = $value;
                    }
                }

                return self::byField(Resource::ASSET_TYPES, 'name', array_values(array_unique($names)), $entity);
            case self::RULE_GLPI_ID_TAG:
                return self::byGlpiIdTag(TagPolicy::value($tags, self::GLPI_ID_TAG), $entity);
        }

        return [];
    }

    /**
     * @param list<string> $itemtypes
     * @param list<string> $values
     * @return list<string>
     */
    private static function byField(array $itemtypes, string $field, array $values, int $entity): array
    {
        global $DB;

        if ($values === []) {
            return [];
        }
        $found = [];
        foreach ($itemtypes as $itemtype) {
            $item = getItemForItemtype($itemtype);
            if (!$item instanceof CommonDBTM || !$itemtype::canView()) {
                continue;
            }
            $table = $item::getTable();
            if (!$DB->fieldExists($table, $field)) {
                continue;
            }
            // Comparaison sans casse : interclassement de GLPI (utf8mb4_unicode_ci).
            $where = [$field => $values] + self::activeCriteria($item, $table, $entity);
            foreach ($DB->request(['SELECT' => ['id'], 'FROM' => $table, 'WHERE' => $where, 'LIMIT' => 5]) as $row) {
                $found[] = $itemtype . ':' . (int) $row['id'];
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Machine virtuelle déclarée sur un hôte (inventaire de l'agent GLPI, onglet « Virtualisation »
     * d'un ordinateur) dont l'UUID est celui de la ressource : l'hôte est proposé.
     *
     * @param list<string> $keys
     * @return list<string>
     */
    private static function byVirtualMachine(array $keys, int $entity): array
    {
        global $DB;

        if ($keys === [] || !class_exists(ItemVirtualMachine::class)) {
            return [];
        }
        $found = [];
        $iterator = $DB->request([
            'SELECT' => ['itemtype', 'items_id'],
            'FROM'   => ItemVirtualMachine::getTable(),
            'WHERE'  => ['uuid' => $keys, 'is_deleted' => 0, 'itemtype' => Resource::ASSET_TYPES],
            'LIMIT'  => 5,
        ]);
        foreach ($iterator as $vm) {
            $itemtype = (string) $vm['itemtype'];
            $found = array_merge($found, self::visible($itemtype, (int) $vm['items_id'], $entity));
        }

        return array_values(array_unique($found));
    }

    /**
     * Étiquette glpi_id : « 12 » (ordinateur 12) ou « Computer:12 », « NetworkEquipment-3 »...
     *
     * @return list<string>
     */
    private static function byGlpiIdTag(string $value, int $entity): array
    {
        $value = trim($value);
        if (preg_match('/^(?:([A-Za-z]+)\s*[:#\/-]\s*)?(\d+)$/', $value, $m) !== 1) {
            return [];
        }
        $itemtype = 'Computer';
        if ($m[1] !== '') {
            $itemtype = '';
            foreach (Resource::ASSET_TYPES as $type) {
                if (strcasecmp($type, $m[1]) === 0) {
                    $itemtype = $type;
                }
            }
            if ($itemtype === '') {
                return [];
            }
        }

        return self::visible($itemtype, (int) $m[2], $entity);
    }

    /**
     * L'actif s'il est visible, actif et dans l'entité de la ressource.
     *
     * @return list<string>
     */
    private static function visible(string $itemtype, int $id, int $entity): array
    {
        global $DB;

        if ($id <= 0 || !in_array($itemtype, Resource::ASSET_TYPES, true) || !$itemtype::canView()) {
            return [];
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonDBTM) {
            return [];
        }
        $table = $item::getTable();
        $row = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => ['id' => $id] + self::activeCriteria($item, $table, $entity),
        ])->current();

        return $row === null ? [] : [$itemtype . ':' . $id];
    }

    /**
     * Actif ni supprimé ni gabarit, de l'entité de la ressource (ou partagé depuis une entité
     * parente) et des entités de la session.
     *
     * @return array<int|string, mixed>
     */
    private static function activeCriteria(CommonDBTM $item, string $table, int $entity): array
    {
        $where = [];
        if ($item->maybeDeleted()) {
            $where['is_deleted'] = 0;
        }
        if ($item->maybeTemplate()) {
            $where['is_template'] = 0;
        }
        $dbu = new DbUtils();
        $where[] = $dbu->getEntitiesRestrictCriteria($table, '', $entity, $item->maybeRecursive());
        $where[] = $dbu->getEntitiesRestrictCriteria($table, '', '', $item->maybeRecursive());

        return $where;
    }
}
