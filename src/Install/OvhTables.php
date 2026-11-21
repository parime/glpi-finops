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

use GlpiPlugin\Finops\OvhAccount;
use Migration;

/**
 * Comptes OVHcloud lus en API (issue #26). Les trois clés sont chiffrées par GLPI (GLPIKey).
 */
final class OvhTables implements FeatureTables
{
    public const ACCOUNTS = 'glpi_plugin_finops_ovhaccounts';

    public static function install(string $keySign, string $charset, string $collation): void
    {
        global $DB;

        if ($DB->tableExists(self::ACCOUNTS)) {
            return;
        }
        $DB->doQuery("CREATE TABLE `" . self::ACCOUNTS . "` (
            `id` int {$keySign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$keySign} NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `region` char(2) NOT NULL DEFAULT 'eu',
            `application_key` text,
            `application_secret` text,
            `consumer_key` text,
            `projects` text,
            `months` int NOT NULL DEFAULT '3',
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
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    public static function migrate(Migration $migration): void
    {
    }

    public static function uninstall(Migration $migration): void
    {
        $migration->dropTable(self::ACCOUNTS);
    }

    public static function pluginClasses(): array
    {
        return [OvhAccount::class];
    }
}
