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
use GlpiPlugin\Finops\Install\CostTables;

/**
 * Agrégats des coûts importés, toujours limités aux entités de la session, et jamais additionnés
 * entre devises (une table par devise).
 */
final class CostReport
{
    public const MONTHS = 12;

    /**
     * Coûts par mois et par fournisseur, sur les derniers mois présents dans les données.
     *
     * @return list<array{currency: string, providers: list<string>, rows: list<array{month: string,
     *     values: array<string, float>, total: float}>, totals: array<string, float>, total: float}>
     */
    public static function byProviderAndMonth(): array
    {
        global $DB;

        $where = self::entityWhere();
        $months = [];
        foreach (
            $DB->request([
                'SELECT'   => 'usage_month',
                'DISTINCT' => true,
                'FROM'     => CostTables::LINES,
                'WHERE'    => $where,
                'ORDER'    => 'usage_month DESC',
                'LIMIT'    => self::MONTHS,
            ]) as $row
        ) {
            $months[] = (string) $row['usage_month'];
        }
        if ($months === []) {
            return [];
        }
        $sums = [];
        $iterator = $DB->request([
            'SELECT'  => ['currency', 'usage_month', 'provider', 'SUM' => 'cost AS total'],
            'FROM'    => CostTables::LINES,
            'WHERE'   => $where + ['usage_month' => $months],
            'GROUPBY' => ['currency', 'usage_month', 'provider'],
        ]);
        foreach ($iterator as $row) {
            $sums[(string) $row['currency']][(string) $row['usage_month']][(string) $row['provider']] = (float) $row['total'];
        }
        ksort($sums);
        $tables = [];
        foreach ($sums as $currency => $byMonth) {
            $providers = [];
            foreach ($byMonth as $values) {
                $providers += array_fill_keys(array_keys($values), true);
            }
            $providers = array_keys($providers);
            sort($providers);
            $totals = array_fill_keys($providers, 0.0);
            $rows = [];
            foreach ($months as $month) {
                $values = $byMonth[$month] ?? [];
                foreach ($values as $provider => $value) {
                    $totals[$provider] += $value;
                }
                $rows[] = ['month' => $month, 'values' => $values, 'total' => array_sum($values)];
            }
            $tables[] = [
                'currency'  => (string) $currency,
                'providers' => array_map('strval', $providers),
                'rows'      => $rows,
                'totals'    => $totals,
                'total'     => array_sum($totals),
            ];
        }

        return $tables;
    }

    /**
     * Chiffres clés de la page d'accueil : lignes, ressources, ressources non rattachées, dernier import.
     *
     * @return array{lines: int, resources: int, unlinked: int, last_import: string}
     */
    public static function summary(): array
    {
        global $DB;

        $last = $DB->request([
            'SELECT' => ['MAX' => 'date_creation AS last'],
            'FROM'   => CostTables::IMPORTS,
            'WHERE'  => self::entityWhere(CostTables::IMPORTS),
        ])->current();

        return [
            'lines'       => countElementsInTable(CostTables::LINES, self::entityWhere()),
            'resources'   => countElementsInTable(CostTables::RESOURCES, self::entityWhere(CostTables::RESOURCES)),
            'unlinked'    => countElementsInTable(
                CostTables::RESOURCES,
                self::entityWhere(CostTables::RESOURCES) + ['items_id' => 0]
            ),
            'last_import' => (string) ($last['last'] ?? ''),
        ];
    }

    /**
     * Coût par mois et par devise des ressources données (onglet FinOps d'un actif).
     *
     * @param list<array<string, mixed>> $resources Lignes de Resource (entities_id, provider, resource).
     * @return list<array{month: string, costs: array<string, float>}>
     */
    public static function byMonthForResources(array $resources): array
    {
        global $DB;

        if ($resources === []) {
            return [];
        }
        $or = [];
        foreach ($resources as $row) {
            $or[] = ['entities_id' => $row['entities_id'], 'provider' => $row['provider'], 'resource' => $row['resource']];
        }
        $months = [];
        $iterator = $DB->request([
            'SELECT'  => ['usage_month', 'currency', 'SUM' => 'cost AS total'],
            'FROM'    => CostTables::LINES,
            'WHERE'   => ['OR' => $or],
            'GROUPBY' => ['usage_month', 'currency'],
            'ORDER'   => 'usage_month DESC',
        ]);
        foreach ($iterator as $row) {
            $months[(string) $row['usage_month']][(string) $row['currency']] = (float) $row['total'];
        }
        $out = [];
        foreach (array_slice($months, 0, self::MONTHS, true) as $month => $costs) {
            $out[] = ['month' => (string) $month, 'costs' => $costs];
        }

        return $out;
    }

    /**
     * @return array<mixed>
     */
    private static function entityWhere(string $table = CostTables::LINES): array
    {
        return (new DbUtils())->getEntitiesRestrictCriteria($table, '', '', false);
    }
}
