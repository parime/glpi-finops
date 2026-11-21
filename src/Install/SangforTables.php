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

use GlpiPlugin\Finops\SangforAccount;
use Migration;

/**
 * Plateformes Sangfor SCP lues en API (issue #11) et dernier inventaire de leurs machines
 * virtuelles. L'identifiant et le mot de passe sont chiffrés par GLPI (GLPIKey).
 */
final class SangforTables implements FeatureTables
{
    public const ACCOUNTS = 'glpi_plugin_finops_sangforaccounts';
    public const VMS = 'glpi_plugin_finops_sangforvms';

    public static function install(string $keySign, string $charset, string $collation): void
    {
        global $DB;

        $options = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";
        if (!$DB->tableExists(self::ACCOUNTS)) {
            $DB->doQuery("CREATE TABLE `" . self::ACCOUNTS . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `entities_id` int {$keySign} NOT NULL DEFAULT '0',
                `name` varchar(255) NOT NULL DEFAULT '',
                `url` varchar(255) NOT NULL DEFAULT '',
                `username` text,
                `password` text,
                `allow_self_signed` tinyint NOT NULL DEFAULT '0',
                `price_vcpu` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `price_ram_gb` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `price_disk_gb` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `currency` char(3) NOT NULL DEFAULT 'EUR',
                `nodes` mediumtext,
                `vm_count` int NOT NULL DEFAULT '0',
                `last_read` timestamp NULL DEFAULT NULL,
                `last_success` timestamp NULL DEFAULT NULL,
                `last_status` varchar(16) NOT NULL DEFAULT '',
                `last_message` varchar(255) NOT NULL DEFAULT '',
                `plugin_finops_costimports_id` int {$keySign} NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `entities_id` (`entities_id`),
                KEY `plugin_finops_costimports_id` (`plugin_finops_costimports_id`)
            ) {$options}");
        }
        if (!$DB->tableExists(self::VMS)) {
            $DB->doQuery("CREATE TABLE `" . self::VMS . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `plugin_finops_sangforaccounts_id` int {$keySign} NOT NULL DEFAULT '0',
                `entities_id` int {$keySign} NOT NULL DEFAULT '0',
                `vm_id` varchar(255) NOT NULL DEFAULT '',
                `name` varchar(255) NOT NULL DEFAULT '',
                `power` varchar(16) NOT NULL DEFAULT '',
                `node` varchar(255) NOT NULL DEFAULT '',
                `pool` varchar(255) NOT NULL DEFAULT '',
                `os` varchar(255) NOT NULL DEFAULT '',
                `vcpu` int NOT NULL DEFAULT '0',
                `memory_mb` int NOT NULL DEFAULT '0',
                `disk_mb` bigint NOT NULL DEFAULT '0',
                `monthly_cost` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `currency` char(3) NOT NULL DEFAULT 'EUR',
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`plugin_finops_sangforaccounts_id`, `vm_id`),
                KEY `entities_id` (`entities_id`),
                KEY `name` (`name`)
            ) {$options}");
        }
    }

    public static function migrate(Migration $migration): void
    {
    }

    public static function uninstall(Migration $migration): void
    {
        $migration->dropTable(self::VMS);
        $migration->dropTable(self::ACCOUNTS);
    }

    public static function pluginClasses(): array
    {
        return [SangforAccount::class];
    }
}
