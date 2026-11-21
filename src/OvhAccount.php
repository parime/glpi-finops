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

use DbUtils;
use GLPIKey;
use GlpiPlugin\Finops\Compat\Base\RecordBase;
use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\OvhTables;
use GlpiPlugin\Finops\Ovh\GuzzleTransport;
use GlpiPlugin\Finops\Ovh\OvhClient;
use GlpiPlugin\Finops\Ovh\OvhCostReader;
use GlpiPlugin\Finops\Ovh\OvhException;
use GlpiPlugin\Finops\Ovh\OvhTransport;
use Session;

/**
 * Compte OVHcloud lu en API. Les clés sont chiffrées par GLPI et ne sont jamais réaffichées ; un
 * champ de clé laissé vide à la modification garde la clé enregistrée. Gérer les comptes exige le
 * droit de configuration de GLPI ; lancer une lecture, le droit d'import du plugin.
 */
class OvhAccount extends RecordBase
{
    public const RIGHTNAME = Menu::RIGHTNAME;

    public const SECRETS = ['application_key', 'application_secret', 'consumer_key'];
    public const MAX_PROJECTS = 20;
    public const MIN_MONTHS = 1;
    public const MAX_MONTHS = 12;

    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    public static function getTypeName($nb = 0)
    {
        return _n('OVHcloud account', 'OVHcloud accounts', $nb, 'finops');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-cloud-dollar';
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

    /**
     * Régions de l'API proposées.
     *
     * @return array<string, string>
     */
    public static function regions(): array
    {
        return ['eu' => __('Europe', 'finops'), 'ca' => __('Canada', 'finops')];
    }

    /** Lancer une lecture : droit d'import du plugin. */
    public static function canLaunchRead(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, CREATE);
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->validate($input, true);

        return $input === false ? false : $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->validate($input, false);

        return $input === false ? false : $input;
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
                return $this->refuse(__('Give the account a name.', 'finops'));
            }
        }
        if ($creation || isset($input['region'])) {
            $region = (string) ($input['region'] ?? '');
            $clean['region'] = isset(OvhClient::ENDPOINTS[$region]) ? $region : 'eu';
        }
        if ($creation || isset($input['months'])) {
            $clean['months'] = max(self::MIN_MONTHS, min(self::MAX_MONTHS, (int) ($input['months'] ?? 3)));
        }
        if ($creation || isset($input['projects'])) {
            $projects = self::parseProjects((string) ($input['projects'] ?? ''));
            if ($projects === null) {
                return $this->refuse(
                    __('Each Public Cloud project identifier has 32 hexadecimal characters.', 'finops')
                );
            }
            $clean['projects'] = implode(',', $projects);
        }
        foreach (self::SECRETS as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value === '') {
                if ($creation) {
                    return $this->refuse(__('The three OVHcloud keys are required.', 'finops'));
                }
                continue;
            }
            if (preg_match('/^[A-Za-z0-9]{8,128}$/', $value) !== 1) {
                return $this->refuse(__('An OVHcloud key only contains letters and digits.', 'finops'));
            }
            $clean[$field] = (new GLPIKey())->encrypt($value);
        }

        return $clean;
    }

    private function refuse(string $message): false
    {
        Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);

        return false;
    }

    /**
     * Identifiants de projets séparés par des virgules, espaces ou retours à la ligne ; null si l'un
     * d'eux est invalide.
     *
     * @return list<string>|null
     */
    public static function parseProjects(string $value): ?array
    {
        $projects = [];
        foreach (preg_split('/[\s,;]+/', strtolower(trim($value))) ?: [] as $project) {
            if ($project === '') {
                continue;
            }
            if (preg_match('/^[a-f0-9]{32}$/', $project) !== 1) {
                return null;
            }
            $projects[$project] = true;
        }

        return count($projects) > self::MAX_PROJECTS ? null : array_keys($projects);
    }

    /**
     * Lit OVHcloud et importe les lignes nouvelles. Une lecture en échec n'écrit aucune ligne et
     * garde la date de la dernière lecture réussie ; seul son motif est enregistré.
     *
     * @return array{ok: bool, message: string, created: int, known: int}
     */
    public function readCosts(?OvhTransport $transport = null): array
    {
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $id = (int) $this->getID();
        try {
            $key = new GLPIKey();
            $client = new OvhClient(
                $transport ?? new GuzzleTransport(),
                (string) $this->fields['region'],
                (string) $key->decrypt((string) $this->fields['application_key']),
                (string) $key->decrypt((string) $this->fields['application_secret']),
                (string) $key->decrypt((string) $this->fields['consumer_key'])
            );
            $since = date('Y-m-01', strtotime(sprintf('-%d months', max(1, (int) $this->fields['months']) - 1)));
            $projects = self::parseProjects((string) $this->fields['projects']) ?? [];
            $parsed = (new OvhCostReader($client))->read($since, $projects);
        } catch (OvhException $e) {
            $this->writeStatus($id, [
                'last_read'    => $now,
                'last_status'  => self::STATUS_ERROR,
                'last_message' => $e->reason,
            ]);

            return ['ok' => false, 'message' => $e->label(), 'created' => 0, 'known' => 0];
        }
        $result = (new CostImporter())->import(
            $parsed,
            (int) $this->fields['entities_id'],
            (string) $this->fields['name'],
            OvhCostReader::PROVIDER
        );
        $this->writeStatus($id, [
            'last_read'                    => $now,
            'last_success'                 => $now,
            'last_status'                  => self::STATUS_OK,
            'last_message'                 => '',
            'plugin_finops_costimports_id' => $result['import'],
        ]);

        return ['ok' => true, 'message' => '', 'created' => $result['created'], 'known' => $result['known']];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function writeStatus(int $id, array $values): void
    {
        global $DB;

        $DB->update(OvhTables::ACCOUNTS, $values, ['id' => $id]);
        $this->fields = $values + $this->fields;
    }

    /** Message traduit du dernier échec enregistré. */
    public static function failureLabel(string $reason): string
    {
        return (new OvhException($reason))->label();
    }

    /**
     * Comptes des entités de la session, sans aucune clé (seulement leur présence).
     *
     * @return list<array<string, mixed>>
     */
    public static function listing(): array
    {
        global $DB;

        $rows = [];
        $iterator = $DB->request([
            'FROM'  => OvhTables::ACCOUNTS,
            'WHERE' => (new DbUtils())->getEntitiesRestrictCriteria(OvhTables::ACCOUNTS, '', '', false),
            'ORDER' => 'name',
        ]);
        foreach ($iterator as $row) {
            $keys = 0;
            foreach (self::SECRETS as $field) {
                $keys += (string) $row[$field] !== '' ? 1 : 0;
            }
            $rows[] = [
                'id'           => (int) $row['id'],
                'name'         => (string) $row['name'],
                'entities_id'  => (int) $row['entities_id'],
                'entity'       => \Dropdown::getDropdownName('glpi_entities', (int) $row['entities_id']),
                'region'       => (string) $row['region'],
                'projects'     => str_replace(',', ', ', (string) $row['projects']),
                'months'       => (int) $row['months'],
                'keys_set'     => $keys === count(self::SECRETS),
                'last_read'    => (string) $row['last_read'],
                'last_success' => (string) $row['last_success'],
                'status'       => (string) $row['last_status'],
                'failure'      => (string) $row['last_status'] === self::STATUS_ERROR
                    ? self::failureLabel((string) $row['last_message'])
                    : '',
            ];
        }

        return $rows;
    }
}
