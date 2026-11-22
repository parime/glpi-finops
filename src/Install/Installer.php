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

use DBConnection;
use GlpiPlugin\Finops\Config;
use GlpiPlugin\Finops\Menu;
use Migration;
use ProfileRight;

/**
 * Installation, mise à jour et désinstallation. Les tables ne sont jamais citées ici : chaque
 * fonctionnalité déclare les siennes dans src/Install/<Fonctionnalité>Tables.php (interface
 * FeatureTables), découvertes par FeatureTablesRegistry. La désinstallation retire aussi tout ce que
 * le plugin a écrit dans les tables du cœur (droit, configuration, lignes rattachées à ses classes).
 */
final class Installer
{
    public function install(Migration $migration): bool
    {
        FeatureTablesRegistry::install(
            DBConnection::getDefaultPrimaryKeySignOption(),
            DBConnection::getDefaultCharset(),
            DBConnection::getDefaultCollation(),
            $migration
        );
        $migration->executeMigration();

        // Une seule fois : addProfileRights() insère sans vérifier, et chaque mise à jour repasse ici.
        if (countElementsInTable('glpi_profilerights', ['name' => Menu::RIGHTNAME]) === 0) {
            ProfileRight::addProfileRights([Menu::RIGHTNAME]);
            $this->grantToAdministrators(READ | CREATE | UPDATE | PURGE);
        }

        return true;
    }

    public function uninstall(Migration $migration): bool
    {
        ProfileRight::deleteProfileRights([Menu::RIGHTNAME]);
        Config::uninstall();
        PluginVersion::uninstall('finops');
        $this->deleteCoreRowsOfPluginItemtypes();
        FeatureTablesRegistry::uninstall($migration);
        $migration->executeMigration();

        return true;
    }

    /**
     * Premier accès : tous les droits du plugin pour les profils qui modifient déjà la
     * configuration de GLPI (super-administrateur). Les autres profils se règlent dans l'onglet du
     * plugin sur les profils.
     */
    private function grantToAdministrators(int $rights): void
    {
        global $DB;

        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => 'config']]) as $row) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                ProfileRight::updateProfileRights((int) $row['profiles_id'], [Menu::RIGHTNAME => $rights]);
            }
        }
    }

    /**
     * Lignes du cœur (historique, préférences d'affichage...) qui citent une classe du plugin.
     */
    private function deleteCoreRowsOfPluginItemtypes(): void
    {
        global $DB;

        $like = addcslashes('GlpiPlugin\\Finops\\', '\\') . '%';
        $columns = $DB->request([
            'SELECT' => ['TABLE_NAME', 'COLUMN_NAME'],
            'FROM'   => 'information_schema.COLUMNS',
            'WHERE'  => [
                'TABLE_SCHEMA' => $DB->dbdefault,
                'COLUMN_NAME'  => ['itemtype', 'itemtype_link'],
                ['NOT' => ['TABLE_NAME' => ['LIKE', 'glpi\_plugin\_%']]],
            ],
        ]);
        foreach ($columns as $column) {
            $DB->delete($column['TABLE_NAME'], [$column['COLUMN_NAME'] => ['LIKE', $like]]);
        }
    }
}
