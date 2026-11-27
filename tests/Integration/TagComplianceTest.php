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
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\CostTables;
use GlpiPlugin\Finops\Resource;
use GlpiPlugin\Finops\TagCompliance;
use PHPUnit\Framework\TestCase;
use User;
use UserEmail;

/**
 * Étiquetage obligatoire (issue #19) sur des coûts importés dans la base de test : étiquettes de la
 * ressource reprises de la ligne la plus récente, taux de conformité par fournisseur et par compte,
 * propriétaire rapproché d'un utilisateur par identifiant ou courriel, filtre « à corriger ».
 * Données fictives, supprimées à la fin.
 */
final class TagComplianceTest extends TestCase
{
    private const PROVIDER = 'finops-test-tags';

    /** @var array<string, string> */
    private array $config = [];
    private int $import = 0;
    private int $user = 0;

    protected function setUp(): void
    {
        $this->config = Config::load();
        Config::save(['mandatory_tags' => 'owner, cost-center, env', 'owner_tag' => 'owner'] + $this->config);

        $suffix = bin2hex(random_bytes(4));
        $this->user = (int) (new User())->add(['name' => 'finops-owner-' . $suffix]);
        (new UserEmail())->add(['users_id' => $this->user, 'email' => 'owner-' . $suffix . '@example.org']);

        $p = self::PROVIDER;
        $parsed = (new CsvCostParser('EUR', 1048576))->parseString(
            "date,provider,account,service,resource,cost,currency,tags\n"
            // Ligne ancienne sans étiquette, puis la plus récente complète : la ressource est conforme.
            . "2026-09-01,$p,acct-1,Compute,vm-full,1,EUR,\n"
            . "2026-10-01,$p,acct-1,Compute,vm-full,1,EUR,Owner=finops-owner-$suffix;cost-center=CC1;env=prod\n"
            // Courriel en majuscules, env manquant.
            . "2026-10-01,$p,acct-1,Compute,vm-mail,1,EUR,owner=OWNER-$suffix@EXAMPLE.ORG;cost-center=CC1\n"
            // Toutes les étiquettes, propriétaire inconnu de GLPI.
            . "2026-10-01,$p,acct-2,Storage,disk-unknown,1,EUR,owner=nobody-$suffix;cost-center=CC2;env=dev\n"
            // Aucune étiquette.
            . "2026-10-01,$p,acct-2,Storage,disk-bare,1,EUR,\n"
        );
        $this->import = (new CostImporter())->import($parsed, 0, 'tags-test.csv')['import'];
    }

    protected function tearDown(): void
    {
        global $DB;

        (new CostImport())->delete(['id' => $this->import], true);
        $DB->delete(CostTables::LINES, ['provider' => self::PROVIDER]);
        $DB->delete(CostTables::RESOURCES, ['provider' => self::PROVIDER]);
        (new User())->delete(['id' => $this->user], true);
        Config::save($this->config);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(bool $toFixOnly): array
    {
        $rows = [];
        foreach (Resource::page(false, self::PROVIDER, 0, $toFixOnly)['rows'] as $row) {
            $rows[(string) $row['resource']] = $row;
        }

        return $rows;
    }

    public function testResourcesCarryTheTagsOfTheirLatestLine(): void
    {
        $rows = $this->rows(false);
        $this->assertCount(4, $rows);
        $this->assertSame('prod', $rows['vm-full']['tags']['env']);
        $this->assertSame([], $rows['vm-full']['missing_tags']);
        $this->assertSame(['env'], $rows['vm-mail']['missing_tags']);
        $this->assertSame(['owner', 'cost-center', 'env'], $rows['disk-bare']['missing_tags']);
    }

    public function testOwnerIsMatchedByLoginOrEmail(): void
    {
        $rows = $this->rows(false);
        $this->assertSame(TagCompliance::OWNER_OK, $rows['vm-full']['owner']['status']);
        $this->assertSame(TagCompliance::OWNER_OK, $rows['vm-mail']['owner']['status']);
        $this->assertNotSame('', $rows['vm-mail']['owner']['name']);
        $this->assertSame(TagCompliance::OWNER_UNKNOWN, $rows['disk-unknown']['owner']['status']);
        $this->assertSame(TagCompliance::OWNER_MISSING, $rows['disk-bare']['owner']['status']);
    }

    public function testToFixFilterAndRates(): void
    {
        $this->assertSame(['disk-bare', 'disk-unknown', 'vm-mail'], $this->sortedKeys($this->rows(true)));
        $this->assertSame(3, Resource::page(false, self::PROVIDER, 0, true)['total']);

        $groups = [];
        foreach (TagCompliance::summary()['groups'] as $group) {
            if ($group['provider'] === self::PROVIDER) {
                $groups[$group['account']] = $group;
            }
        }
        $this->assertSame(2, $groups['acct-1']['total']);
        $this->assertSame(1, $groups['acct-1']['compliant']);
        $this->assertSame(50, $groups['acct-1']['rate']);
        $this->assertSame(1, $groups['acct-2']['owner_unknown']);
        $this->assertSame(1, $groups['acct-2']['owner_missing']);
        $this->assertSame(50, $groups['acct-2']['rate']);
    }

    public function testEmptySettingsTurnTheChecksOff(): void
    {
        Config::save(['mandatory_tags' => ' , ', 'owner_tag' => ''] + $this->config);
        $this->assertSame([], TagCompliance::settings()['required']);
        $this->assertSame([], Resource::page(false, self::PROVIDER, 0, true)['rows']);
        $this->assertSame(TagCompliance::OWNER_OFF, $this->rows(false)['disk-bare']['owner']['status']);
    }

    /**
     * @param array<string, mixed> $rows
     * @return list<string>
     */
    private function sortedKeys(array $rows): array
    {
        $keys = array_map('strval', array_keys($rows));
        sort($keys);

        return $keys;
    }
}
