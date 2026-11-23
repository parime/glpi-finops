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

namespace GlpiPlugin\Finops\Tests\Integration;

use GlpiPlugin\Finops\Config;
use GlpiPlugin\Finops\Install\FeatureTables;
use GlpiPlugin\Finops\Install\FeatureTablesRegistry;
use GlpiPlugin\Finops\Menu;
use PHPUnit\Framework\TestCase;

/**
 * Plugin installé et activé dans une vraie instance GLPI (CI officielle GLPI, job GLPI 12).
 */
final class InstallerTest extends TestCase
{
    public function testRightIsGrantedToTheSuperAdministrator(): void
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => ['profiles_id' => 4, 'name' => Menu::RIGHTNAME],
        ])->current();
        $this->assertNotNull($row);
        $this->assertSame(READ | CREATE | UPDATE | PURGE, (int) $row['rights'] & (READ | CREATE | UPDATE | PURGE));
    }

    public function testEveryDiscoveredTablesClassImplementsTheInterface(): void
    {
        global $DB;

        $this->assertContainsOnly('string', FeatureTablesRegistry::classes());
        foreach (FeatureTablesRegistry::classes() as $class) {
            $this->assertTrue(is_subclass_of($class, FeatureTables::class));
            foreach ($class::pluginClasses() as $itemtype) {
                $this->assertTrue($DB->tableExists($itemtype::getTable()), $itemtype);
            }
        }
    }

    public function testSettingsAreValidatedAndBounded(): void
    {
        $before = Config::load();
        try {
            $saved = Config::save(['default_currency' => 'usd', 'max_upload_mb' => '999', 'version_check' => '1']);
            $this->assertSame([
                'default_currency' => 'USD',
                'max_upload_mb'    => (string) Config::MAX_UPLOAD_MB,
                'version_check'    => '1',
            ], $saved);
            $this->assertSame(Config::MAX_UPLOAD_MB * 1024 * 1024, Config::maxUploadBytes());

            $saved = Config::save(['default_currency' => '<b>', 'max_upload_mb' => '-3']);
            $this->assertSame('EUR', $saved['default_currency']);
            $this->assertSame((string) Config::MIN_UPLOAD_MB, $saved['max_upload_mb']);
            // Case décochée : le formulaire n'envoie rien, la vérification des versions est coupée.
            $this->assertSame('0', $saved['version_check']);
        } finally {
            Config::save($before);
        }
    }
}
