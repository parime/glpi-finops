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

namespace GlpiPlugin\Finops\Install;

use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\CostLine;
use GlpiPlugin\Finops\Resource;
use Migration;

/**
 * Tables de l'import des coûts (issue #2) : journal des imports, lignes de coût, ressources et leur
 * rattachement à un actif de GLPI. Découvertes par FeatureTablesRegistry.
 */
final class CostTables implements FeatureTables
{
    public const IMPORTS = 'glpi_plugin_finops_costimports';
    public const LINES = 'glpi_plugin_finops_costlines';
    public const RESOURCES = 'glpi_plugin_finops_resources';

    public static function install(string $keySign, string $charset, string $collation): void
    {
        global $DB;

        $options = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";
        $tables = [
            self::IMPORTS => "
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `entities_id` int {$keySign} NOT NULL DEFAULT '0',
                `users_id` int {$keySign} NOT NULL DEFAULT '0',
                `source` varchar(64) NOT NULL DEFAULT 'csv',
                `filename` varchar(255) NOT NULL DEFAULT '',
                `lines_created` int NOT NULL DEFAULT '0',
                `lines_known` int NOT NULL DEFAULT '0',
                `lines_rejected` int NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `entities_id` (`entities_id`),
                KEY `users_id` (`users_id`),
                KEY `date_creation` (`date_creation`)",
            self::LINES => "
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `entities_id` int {$keySign} NOT NULL DEFAULT '0',
                `plugin_finops_costimports_id` int {$keySign} NOT NULL DEFAULT '0',
                `usage_date` date NOT NULL,
                `usage_month` char(7) NOT NULL DEFAULT '',
                `provider` varchar(64) NOT NULL DEFAULT '',
                `account` varchar(255) NOT NULL DEFAULT '',
                `service` varchar(255) NOT NULL DEFAULT '',
                `resource` varchar(255) NOT NULL DEFAULT '',
                `cost` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `currency` char(3) NOT NULL DEFAULT 'EUR',
                `tags` text,
                `source_key` char(64) NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`entities_id`, `source_key`),
                KEY `plugin_finops_costimports_id` (`plugin_finops_costimports_id`),
                KEY `usage_month` (`usage_month`),
                KEY `provider` (`provider`),
                KEY `resource` (`entities_id`, `provider`, `resource`)",
            self::RESOURCES => "
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `entities_id` int {$keySign} NOT NULL DEFAULT '0',
                `provider` varchar(64) NOT NULL DEFAULT '',
                `resource` varchar(255) NOT NULL DEFAULT '',
                `account` varchar(255) NOT NULL DEFAULT '',
                `service` varchar(255) NOT NULL DEFAULT '',
                `itemtype` varchar(100) DEFAULT NULL,
                `items_id` int {$keySign} NOT NULL DEFAULT '0',
                `users_id_link` int {$keySign} NOT NULL DEFAULT '0',
                `date_link` timestamp NULL DEFAULT NULL,
                `first_seen` date DEFAULT NULL,
                `last_seen` date DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`entities_id`, `provider`, `resource`),
                KEY `item` (`itemtype`, `items_id`),
                KEY `last_seen` (`last_seen`)",
        ];
        foreach ($tables as $table => $columns) {
            if (!$DB->tableExists($table)) {
                $DB->doQuery("CREATE TABLE `{$table}` ({$columns}) {$options}");
            }
        }
    }

    public static function migrate(Migration $migration): void
    {
    }

    public static function uninstall(Migration $migration): void
    {
        foreach ([self::LINES, self::RESOURCES, self::IMPORTS] as $table) {
            $migration->dropTable($table);
        }
    }

    public static function pluginClasses(): array
    {
        return [CostImport::class, CostLine::class, Resource::class];
    }
}
