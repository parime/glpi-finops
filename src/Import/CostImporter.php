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

namespace GlpiPlugin\Finops\Import;

use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\Install\CostTables;
use Session;

/**
 * Aperçu puis écriture d'un fichier de coûts lu par CsvCostParser, dans une entité de la session.
 * Import idempotent : une ligne dont la clé de la source existe déjà dans l'entité n'est pas recréée.
 * Les ressources vues (fournisseur + identifiant) sont créées ou mises à jour, jamais rattachées
 * automatiquement à un actif.
 */
final class CostImporter
{
    private const CHUNK = 500;

    /**
     * Ce que l'import créerait, sans rien écrire.
     *
     * @param array{rows: list<array<string, string>>, rejected: int} $parsed
     * @return array{new: int, known: int, rejected: int, providers: array<string, int>,
     *     months: list<string>, totals: array<string, float>, sample: list<array<string, string>>}
     */
    public function preview(array $parsed, int $entity): array
    {
        $known = $this->knownKeys($parsed['rows'], $entity);
        $providers = $totals = $months = [];
        $new = 0;
        $sample = [];
        foreach ($parsed['rows'] as $row) {
            if (isset($known[$row['source_key']])) {
                continue;
            }
            $new++;
            $providers[$row['provider']] = ($providers[$row['provider']] ?? 0) + 1;
            $totals[$row['currency']] = ($totals[$row['currency']] ?? 0.0) + (float) $row['cost'];
            $months[$row['usage_month']] = true;
            if (count($sample) < 10) {
                $sample[] = $row;
            }
        }
        ksort($providers);
        ksort($totals);
        $months = array_keys($months);
        sort($months);

        return [
            'new'       => $new,
            'known'     => count($parsed['rows']) - $new,
            'rejected'  => $parsed['rejected'],
            'providers' => $providers,
            'months'    => $months,
            'totals'    => $totals,
            'sample'    => $sample,
        ];
    }

    /**
     * Écrit les lignes nouvelles et le journal. L'appelant a vérifié le droit CREATE et l'entité.
     * $source : clé de la source (csv, ovhcloud...) ; $filename : fichier ou nom du compte lu.
     *
     * @param array{rows: list<array<string, string>>, rejected: int} $parsed
     * @return array{import: int, created: int, known: int, rejected: int}
     */
    public function import(array $parsed, int $entity, string $filename, string $source = 'csv'): array
    {
        global $DB;

        $known = $this->knownKeys($parsed['rows'], $entity);
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $journal = new CostImport();
        $importId = (int) $journal->add([
            'entities_id' => $entity,
            'users_id'    => (int) Session::getLoginUserID(),
            'source'      => $source,
            'filename'    => mb_substr(CsvCostParser::clean($filename), 0, 255),
        ]);
        $created = 0;
        $resources = [];
        $DB->beginTransaction();
        try {
            foreach ($parsed['rows'] as $row) {
                if (isset($known[$row['source_key']])) {
                    continue;
                }
                $known[$row['source_key']] = true;
                $DB->insert(CostTables::LINES, [
                    'entities_id'                  => $entity,
                    'plugin_finops_costimports_id' => $importId,
                    'usage_date'                   => $row['usage_date'],
                    'usage_month'                  => $row['usage_month'],
                    'provider'                     => $row['provider'],
                    'account'                      => $row['account'],
                    'service'                      => $row['service'],
                    'resource'                     => $row['resource'],
                    'cost'                         => $row['cost'],
                    'currency'                     => $row['currency'],
                    'tags'                         => $row['tags'],
                    'source_key'                   => $row['source_key'],
                    'date_creation'                => $now,
                ]);
                $created++;
                if ($row['resource'] !== '') {
                    $key = $row['provider'] . "\x1F" . $row['resource'];
                    $seen = $resources[$key]
                        ?? ['row' => $row, 'first' => $row['usage_date'], 'last' => $row['usage_date']];
                    $seen['first'] = min($seen['first'], $row['usage_date']);
                    if ($row['usage_date'] >= $seen['last']) {
                        [$seen['last'], $seen['row']] = [$row['usage_date'], $row];
                    }
                    $resources[$key] = $seen;
                }
            }
            foreach ($resources as $seen) {
                $this->upsertResource($entity, $seen['row'], $seen['first'], $seen['last'], $now);
            }
            $DB->update(CostTables::IMPORTS, [
                'lines_created'  => $created,
                'lines_known'    => count($parsed['rows']) - $created,
                'lines_rejected' => $parsed['rejected'],
            ], ['id' => $importId]);
            $DB->commit();
        } catch (\Throwable $e) {
            $DB->rollBack();
            $DB->delete(CostTables::IMPORTS, ['id' => $importId]);
            throw $e;
        }

        return [
            'import'   => $importId,
            'created'  => $created,
            'known'    => count($parsed['rows']) - $created,
            'rejected' => $parsed['rejected'],
        ];
    }

    /**
     * @param array<string, string> $row
     */
    private function upsertResource(int $entity, array $row, string $first, string $last, string $now): void
    {
        global $DB;

        $criteria = ['entities_id' => $entity, 'provider' => $row['provider'], 'resource' => $row['resource']];
        $existing = $DB->request(['FROM' => CostTables::RESOURCES, 'WHERE' => $criteria, 'LIMIT' => 1])->current();
        if ($existing === null) {
            $DB->insert(CostTables::RESOURCES, $criteria + [
                'account'       => $row['account'],
                'service'       => $row['service'],
                'first_seen'    => $first,
                'last_seen'     => $last,
                'date_creation' => $now,
                'date_mod'      => $now,
            ]);
            return;
        }
        $update = ['date_mod' => $now];
        if ($existing['first_seen'] === null || $first < (string) $existing['first_seen']) {
            $update['first_seen'] = $first;
        }
        if ($existing['last_seen'] === null || $last >= (string) $existing['last_seen']) {
            $update += ['last_seen' => $last, 'account' => $row['account'], 'service' => $row['service']];
        }
        $DB->update(CostTables::RESOURCES, $update, ['id' => (int) $existing['id']]);
    }

    /**
     * Clés de la source déjà présentes dans l'entité.
     *
     * @param list<array<string, string>> $rows
     * @return array<string, true>
     */
    private function knownKeys(array $rows, int $entity): array
    {
        global $DB;

        $known = [];
        foreach (array_chunk(array_column($rows, 'source_key'), self::CHUNK) as $keys) {
            $iterator = $DB->request([
                'SELECT' => 'source_key',
                'FROM'   => CostTables::LINES,
                'WHERE'  => ['entities_id' => $entity, 'source_key' => $keys],
            ]);
            foreach ($iterator as $row) {
                $known[(string) $row['source_key']] = true;
            }
        }

        return $known;
    }

    /**
     * Message traduit d'une erreur de fichier ou de ligne (codes de CsvCostParser).
     *
     * @param list<string> $missing
     */
    public static function errorLabel(string $code, array $missing = []): string
    {
        return match ($code) {
            CsvCostParser::E_TOO_LARGE        => __(
                'The file is larger than the size allowed in the settings.',
                'finops'
            ),
            CsvCostParser::E_EMPTY            => __('The file is empty.', 'finops'),
            CsvCostParser::E_BINARY           => __('The file is not a text file.', 'finops'),
            CsvCostParser::E_CODE             => __('The file contains program code and was refused.', 'finops'),
            CsvCostParser::E_COLUMNS          => sprintf(
                __('Required columns missing: %s.', 'finops'),
                implode(', ', $missing)
            ),
            CsvCostParser::E_TOO_MANY_COLUMNS => __('The file has too many columns.', 'finops'),
            CsvCostParser::E_TOO_MANY_LINES   => __('The file has too many lines.', 'finops'),
            CsvCostParser::E_DATE             => __('Invalid date (expected YYYY-MM-DD or DD/MM/YYYY).', 'finops'),
            CsvCostParser::E_PROVIDER         => __('Missing or invalid provider.', 'finops'),
            CsvCostParser::E_COST             => __('Invalid cost (expected a number).', 'finops'),
            CsvCostParser::E_CURRENCY         => __('Invalid currency (expected a three-letter code).', 'finops'),
            CsvCostParser::E_SHAPE            => __('Too many cells on the line.', 'finops'),
            default                           => __('The file cannot be read.', 'finops'),
        };
    }
}
