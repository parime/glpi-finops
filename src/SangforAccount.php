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

use Computer;
use DbUtils;
use GLPIKey;
use GlpiPlugin\Finops\Compat\Base\RecordBase;
use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\CostTables;
use GlpiPlugin\Finops\Install\SangforTables;
use GlpiPlugin\Finops\Sangfor\GuzzleScpTransport;
use GlpiPlugin\Finops\Sangfor\ScpClient;
use GlpiPlugin\Finops\Sangfor\ScpException;
use GlpiPlugin\Finops\Sangfor\ScpInventoryReader;
use GlpiPlugin\Finops\Sangfor\ScpTransport;
use Session;

/**
 * Plateforme Sangfor SCP lue en API (issue #11). L'identifiant et le mot de passe sont chiffrés par
 * GLPI et jamais réaffichés ; un champ laissé vide à la modification garde la valeur enregistrée.
 * Gérer les plateformes exige le droit de configuration de GLPI ; lancer une lecture, le droit
 * d'import du plugin ; voir l'inventaire, le droit de lecture du plugin.
 */
class SangforAccount extends RecordBase
{
    public const RIGHTNAME = Menu::RIGHTNAME;

    public const SECRETS = ['username', 'password'];
    public const PRICES = ['price_vcpu', 'price_ram_gb', 'price_disk_gb'];
    public const MAX_PRICE = 1000000;
    public const MAX_SUGGESTIONS = 2000;

    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    public static function getTypeName($nb = 0)
    {
        return _n('Sangfor SCP platform', 'Sangfor SCP platforms', $nb, 'finops');
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
        return (bool) Session::haveRight('config', UPDATE);
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    public static function canPurge(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    public function prepareInputForAdd($input)
    {
        return $this->validate($input, true);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->validate($input, false);
    }

    public function cleanDBonPurge()
    {
        global $DB;

        $DB->delete(SangforTables::VMS, ['plugin_finops_sangforaccounts_id' => (int) $this->getID()]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function validate(array $input, bool $creation): array|false
    {
        $clean = [];
        if (isset($input['id'])) {
            $clean['id'] = (int) $input['id'];
        }
        if ($creation || isset($input['entities_id'])) {
            $clean['entities_id'] = (int) ($input['entities_id'] ?? 0);
        }
        if ($creation || isset($input['name'])) {
            $clean['name'] = CsvCostParser::clean((string) ($input['name'] ?? ''));
            if ($clean['name'] === '') {
                return $this->refuse(__('Give the platform a name.', 'finops'));
            }
        }
        if ($creation || isset($input['url'])) {
            $url = ScpClient::normalizeUrl((string) ($input['url'] ?? ''));
            if ($url === null) {
                return $this->refuse(__(
                    'The address of Sangfor SCP is https:// followed by its name or IP address, and an '
                    . 'optional port, without path.',
                    'finops'
                ));
            }
            $clean['url'] = $url;
        }
        // Case à cocher : absente du formulaire quand elle n'est pas cochée.
        if ($creation || isset($input['_form'])) {
            $clean['allow_self_signed'] = !empty($input['allow_self_signed']) ? 1 : 0;
        }
        foreach (self::PRICES as $field) {
            if ($creation || isset($input[$field])) {
                $raw = trim((string) ($input[$field] ?? ''));
                $price = $raw === '' ? '0' : CsvCostParser::parseAmount($raw);
                if ($price === null || (float) $price < 0 || (float) $price > self::MAX_PRICE) {
                    return $this->refuse(__('Each price is a positive number, or 0.', 'finops'));
                }
                $clean[$field] = number_format((float) $price, 6, '.', '');
            }
        }
        if ($creation || isset($input['currency'])) {
            $currency = strtoupper(trim((string) ($input['currency'] ?? '')));
            $clean['currency'] = preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : Config::defaultCurrency();
        }
        foreach (self::SECRETS as $field) {
            $value = (string) ($input[$field] ?? '');
            if (trim($value) === '') {
                if ($creation) {
                    return $this->refuse(__('The user name and the password are required.', 'finops'));
                }
                continue;
            }
            if (strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return $this->refuse(__('The user name or the password is too long or not printable.', 'finops'));
            }
            $clean[$field] = (new GLPIKey())->encrypt($field === 'username' ? trim($value) : $value);
        }

        return $clean;
    }

    private function refuse(string $message): false
    {
        Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);

        return false;
    }

    /**
     * Lit SCP : inventaire remplacé et lignes de coût du mois importées. Une lecture en échec
     * n'écrit rien (ni inventaire ni coût) et garde la date de la dernière lecture réussie.
     *
     * @return array{ok: bool, message: string, vms: int, created: int, known: int}
     */
    public function readInventory(?ScpTransport $transport = null): array
    {
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $id = (int) $this->getID();
        try {
            $key = new GLPIKey();
            $client = new ScpClient(
                $transport ?? new GuzzleScpTransport((bool) $this->fields['allow_self_signed']),
                (string) $this->fields['url'],
                (string) $key->decrypt((string) $this->fields['username']),
                (string) $key->decrypt((string) $this->fields['password'])
            );
            $inventory = (new ScpInventoryReader($client))->read();
        } catch (ScpException $e) {
            $this->writeStatus($id, [
                'last_read'    => $now,
                'last_status'  => self::STATUS_ERROR,
                'last_message' => $e->reason,
            ]);

            return ['ok' => false, 'message' => $e->label(), 'vms' => 0, 'created' => 0, 'known' => 0];
        }
        $grid = $this->grid();
        $currency = (string) $this->fields['currency'];
        $parsed = ScpInventoryReader::costRows(
            $inventory['vms'],
            $grid,
            $currency,
            (string) $this->fields['name'],
            $id,
            substr($now, 0, 7)
        );
        $parsed['rejected'] = $inventory['rejected'];
        $result = (new CostImporter())->import(
            $parsed,
            (int) $this->fields['entities_id'],
            (string) $this->fields['name'],
            ScpInventoryReader::PROVIDER
        );
        $this->storeVms($id, $inventory['vms'], $grid, $currency, $now);
        $this->writeStatus($id, [
            'nodes'                        => (string) json_encode($inventory['nodes']),
            'vm_count'                     => count($inventory['vms']),
            'last_read'                    => $now,
            'last_success'                 => $now,
            'last_status'                  => self::STATUS_OK,
            'last_message'                 => '',
            'plugin_finops_costimports_id' => $result['import'],
        ]);

        return [
            'ok'      => true,
            'message' => '',
            'vms'     => count($inventory['vms']),
            'created' => $result['created'],
            'known'   => $result['known'],
        ];
    }

    /**
     * @return array{vcpu: float, ram_gb: float, disk_gb: float}
     */
    private function grid(): array
    {
        return [
            'vcpu'    => (float) $this->fields['price_vcpu'],
            'ram_gb'  => (float) $this->fields['price_ram_gb'],
            'disk_gb' => (float) $this->fields['price_disk_gb'],
        ];
    }

    /**
     * Remplace l'inventaire de la plateforme par celui qui vient d'être lu.
     *
     * @param list<array{vm_id: string, name: string, power: string, node: string, pool: string, os: string,
     *     vcpu: int, memory_mb: int, disk_mb: int}> $vms
     * @param array{vcpu: float, ram_gb: float, disk_gb: float} $grid
     */
    private function storeVms(int $id, array $vms, array $grid, string $currency, string $now): void
    {
        global $DB;

        $DB->beginTransaction();
        try {
            $DB->delete(SangforTables::VMS, ['plugin_finops_sangforaccounts_id' => $id]);
            foreach ($vms as $vm) {
                $DB->insert(SangforTables::VMS, [
                    'plugin_finops_sangforaccounts_id' => $id,
                    'entities_id'                      => (int) $this->fields['entities_id'],
                    'vm_id'                            => mb_substr($vm['vm_id'], 0, 255),
                    'name'                             => mb_substr($vm['name'], 0, 255),
                    'power'                            => $vm['power'],
                    'node'                             => mb_substr($vm['node'], 0, 255),
                    'pool'                             => mb_substr($vm['pool'], 0, 255),
                    'os'                               => mb_substr($vm['os'], 0, 255),
                    'vcpu'                             => $vm['vcpu'],
                    'memory_mb'                        => $vm['memory_mb'],
                    'disk_mb'                          => $vm['disk_mb'],
                    'monthly_cost'                     => number_format(
                        min(ScpInventoryReader::monthlyCost($vm, $grid), 1.0e12),
                        6,
                        '.',
                        ''
                    ),
                    'currency'                         => $currency,
                    'date_mod'                         => $now,
                ]);
            }
            $DB->commit();
        } catch (\Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function writeStatus(int $id, array $values): void
    {
        global $DB;

        $DB->update(SangforTables::ACCOUNTS, $values, ['id' => $id]);
        $this->fields = $values + $this->fields;
    }

    /** Message traduit du dernier échec enregistré. */
    public static function failureLabel(string $reason): string
    {
        return (new ScpException($reason))->label();
    }

    /**
     * Plateformes des entités de la session, sans identifiant ni mot de passe (seulement leur présence).
     *
     * @return list<array<string, mixed>>
     */
    public static function listing(): array
    {
        global $DB;

        $rows = [];
        $iterator = $DB->request([
            'FROM'  => SangforTables::ACCOUNTS,
            'WHERE' => (new DbUtils())->getEntitiesRestrictCriteria(SangforTables::ACCOUNTS, '', '', false),
            'ORDER' => 'name',
        ]);
        foreach ($iterator as $row) {
            $rows[] = [
                'id'                => (int) $row['id'],
                'name'              => (string) $row['name'],
                'entities_id'       => (int) $row['entities_id'],
                'entity'            => \Dropdown::getDropdownName('glpi_entities', (int) $row['entities_id']),
                'url'               => (string) $row['url'],
                'allow_self_signed' => (bool) $row['allow_self_signed'],
                'price_vcpu'        => (float) $row['price_vcpu'],
                'price_ram_gb'      => (float) $row['price_ram_gb'],
                'price_disk_gb'     => (float) $row['price_disk_gb'],
                'currency'          => (string) $row['currency'],
                'vm_count'          => (int) $row['vm_count'],
                'keys_set'          => (string) $row['username'] !== '' && (string) $row['password'] !== '',
                'last_read'         => (string) $row['last_read'],
                'last_success'      => (string) $row['last_success'],
                'status'            => (string) $row['last_status'],
                'failure'           => (string) $row['last_status'] === self::STATUS_ERROR
                    ? self::failureLabel((string) $row['last_message'])
                    : '',
            ];
        }

        return $rows;
    }

    /**
     * Dernier inventaire de la plateforme (déjà vérifiée par l'appelant) : nœuds, VM, rattachement
     * de chaque VM à un actif de GLPI, ou proposition d'ordinateur par UUID puis par nom.
     *
     * @return array{nodes: list<array<string, mixed>>, vms: list<array<string, mixed>>,
     *     totals: array<string, float|int>}
     */
    public function inventory(): array
    {
        global $DB;

        $id = (int) $this->getID();
        $entity = (int) $this->fields['entities_id'];
        $nodes = json_decode((string) ($this->fields['nodes'] ?? ''), true);
        $vms = iterator_to_array($DB->request([
            'FROM'  => SangforTables::VMS,
            'WHERE' => ['plugin_finops_sangforaccounts_id' => $id],
            'ORDER' => 'name',
        ]), false);
        $ids = array_column($vms, 'vm_id');
        $resources = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $iterator = $DB->request([
                'FROM'  => CostTables::RESOURCES,
                'WHERE' => ['entities_id' => $entity, 'provider' => ScpInventoryReader::PROVIDER, 'resource' => $chunk],
            ]);
            foreach ($iterator as $row) {
                $resources[(string) $row['resource']] = $row;
            }
        }
        $suggestions = count($vms) <= self::MAX_SUGGESTIONS ? self::suggestions($vms) : ['uuid' => [], 'name' => []];
        $rows = [];
        $totals = ['vcpu' => 0, 'memory_mb' => 0, 'disk_mb' => 0, 'cost' => 0.0, 'linked' => 0];
        foreach ($vms as $vm) {
            $resource = $resources[(string) $vm['vm_id']] ?? null;
            $linked = $resource !== null && (string) ($resource['itemtype'] ?? '') !== ''
                && (int) $resource['items_id'] > 0;
            $suggestion = null;
            if (!$linked) {
                $uuid = $suggestions['uuid'][strtolower((string) $vm['vm_id'])] ?? null;
                $byName = $suggestions['name'][mb_strtolower((string) $vm['name'])] ?? [];
                if ($uuid !== null) {
                    $suggestion = $uuid + ['by' => 'uuid'];
                } elseif (count($byName) === 1) {
                    $suggestion = $byName[0] + ['by' => 'name'];
                }
            }
            $rows[] = [
                'id'           => (int) $vm['id'],
                'vm_id'        => (string) $vm['vm_id'],
                'name'         => (string) $vm['name'],
                'power'        => (string) $vm['power'],
                'node'         => (string) $vm['node'],
                'pool'         => (string) $vm['pool'],
                'os'           => (string) $vm['os'],
                'vcpu'         => (int) $vm['vcpu'],
                'memory_gb'    => round((int) $vm['memory_mb'] / 1024, 1),
                'disk_gb'      => round((int) $vm['disk_mb'] / 1024, 1),
                'monthly_cost' => (float) $vm['monthly_cost'],
                'currency'     => (string) $vm['currency'],
                'resource_id'  => $resource !== null ? (int) $resource['id'] : 0,
                'linked'       => $linked
                    ? \Dropdown::getDropdownName(
                        getTableForItemType((string) $resource['itemtype']),
                        (int) $resource['items_id']
                    )
                    : '',
                'suggestion'   => $suggestion,
            ];
            $totals['vcpu'] += (int) $vm['vcpu'];
            $totals['memory_mb'] += (int) $vm['memory_mb'];
            $totals['disk_mb'] += (int) $vm['disk_mb'];
            $totals['cost'] += (float) $vm['monthly_cost'];
            $totals['linked'] += $linked ? 1 : 0;
        }

        return ['nodes' => is_array($nodes) ? array_values($nodes) : [], 'vms' => $rows, 'totals' => $totals];
    }

    /**
     * Ordinateurs de GLPI visibles par la session (ni supprimés ni modèles) dont l'UUID est
     * l'identifiant d'une VM, ou dont le nom est celui d'une VM.
     *
     * @param list<array<string, mixed>> $vms
     * @return array{uuid: array<string, array{id: int, name: string}>,
     *     name: array<string, list<array{id: int, name: string}>>}
     */
    private static function suggestions(array $vms): array
    {
        global $DB;

        $found = ['uuid' => [], 'name' => []];
        $restrict = (new DbUtils())->getEntitiesRestrictCriteria('glpi_computers', '', '', true);
        $base = ['is_deleted' => 0, 'is_template' => 0] + $restrict;
        foreach (array_chunk($vms, 500) as $chunk) {
            $uuids = array_values(array_unique(array_merge(
                array_map(static fn (array $vm): string => strtolower((string) $vm['vm_id']), $chunk),
                array_map(static fn (array $vm): string => strtoupper((string) $vm['vm_id']), $chunk)
            )));
            $names = array_values(array_unique(array_map(
                static fn (array $vm): string => (string) $vm['name'],
                $chunk
            )));
            $iterator = $DB->request([
                'SELECT' => ['id', 'name', 'uuid'],
                'FROM'   => 'glpi_computers',
                'WHERE'  => $base + ['OR' => ['uuid' => $uuids, 'name' => $names]],
            ]);
            foreach ($iterator as $row) {
                $computer = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
                $uuid = strtolower((string) $row['uuid']);
                if ($uuid !== '' && in_array($uuid, $uuids, true)) {
                    $found['uuid'][$uuid] = $computer;
                }
                $name = mb_strtolower((string) $row['name']);
                $found['name'][$name][$computer['id']] = $computer;
            }
        }
        $found['name'] = array_map('array_values', $found['name']);

        return $found;
    }

    /**
     * Rattache une VM de l'inventaire à un ordinateur, par la ressource de la VM (créée à la lecture) :
     * mêmes contrôles que la page des ressources (Resource::link). Faux si la VM ou la ressource
     * n'existe pas, ou si un droit manque.
     */
    public static function linkVm(int $vmRowId, int $computerId): bool
    {
        global $DB;

        $vm = $DB->request(['FROM' => SangforTables::VMS, 'WHERE' => ['id' => $vmRowId], 'LIMIT' => 1])->current();
        $account = new self();
        if ($vm === null || !$account->can((int) $vm['plugin_finops_sangforaccounts_id'], READ)) {
            return false;
        }
        $resource = $DB->request([
            'FROM'  => CostTables::RESOURCES,
            'WHERE' => [
                'entities_id' => (int) $account->fields['entities_id'],
                'provider'    => ScpInventoryReader::PROVIDER,
                'resource'    => (string) $vm['vm_id'],
            ],
            'LIMIT' => 1,
        ])->current();

        return $resource !== null && Resource::link((int) $resource['id'], Computer::class, $computerId);
    }
}
