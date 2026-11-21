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

use Computer;
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\CostReport;
use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\CostTables;
use GlpiPlugin\Finops\Resource;
use PHPUnit\Framework\TestCase;

/**
 * Import réel dans la base de test (session du super-administrateur, entité racine) : aperçu,
 * idempotence, ressources, rattachement manuel, agrégats par devise, suppression d'un import.
 * Données fictives, supprimées à la fin.
 */
final class CostImporterTest extends TestCase
{
    private const PROVIDER = 'finops-test-cloud';

    /** @var list<int> */
    private array $imports = [];
    /** @var list<int> */
    private array $computers = [];

    protected function tearDown(): void
    {
        global $DB;

        foreach ($this->imports as $id) {
            (new CostImport())->delete(['id' => $id], true);
        }
        $DB->delete(CostTables::LINES, ['provider' => self::PROVIDER]);
        $DB->delete(CostTables::RESOURCES, ['provider' => self::PROVIDER]);
        foreach ($this->computers as $id) {
            (new Computer())->delete(['id' => $id], true);
        }
    }

    private function parsed(): array
    {
        $p = self::PROVIDER;

        return (new CsvCostParser('EUR', 1048576))->parseString(
            "date,provider,account,service,resource,cost,currency\n"
            . "2026-09-30,$p,acct-1,Compute,vm-test-1,10,EUR\n"
            . "2026-10-01,$p,acct-1,Compute,vm-test-1,11.5,EUR\n"
            . "2026-10-01,$p,acct-1,Storage,disk-test-1,2,USD\n"
            . "2026-10-01,$p,acct-1,Support,,1,EUR\n"
            . "bad-date,$p,acct-1,Compute,vm-test-1,1,EUR\n"
        );
    }

    public function testPreviewWritesNothingAndImportIsIdempotent(): void
    {
        $parsed = $this->parsed();
        $importer = new CostImporter();
        $before = countElementsInTable(CostTables::LINES);

        $preview = $importer->preview($parsed, 0);
        $this->assertSame(4, $preview['new']);
        $this->assertSame(0, $preview['known']);
        $this->assertSame(1, $preview['rejected']);
        $this->assertSame(['2026-09', '2026-10'], $preview['months']);
        $this->assertEqualsWithDelta(22.5, $preview['totals']['EUR'], 0.001);
        $this->assertSame($before, countElementsInTable(CostTables::LINES));

        $first = $importer->import($parsed, 0, 'costs-test.csv');
        $this->imports[] = $first['import'];
        $this->assertSame(4, $first['created']);
        $this->assertSame(1, $first['rejected']);

        $again = $importer->import($parsed, 0, 'costs-test.csv');
        $this->imports[] = $again['import'];
        $this->assertSame(0, $again['created']);
        $this->assertSame(4, $again['known']);
        $this->assertSame(4, countElementsInTable(CostTables::LINES, ['provider' => self::PROVIDER]));
        $this->assertSame(0, $importer->preview($parsed, 0)['new']);

        // Deux ressources nommées (la ligne sans ressource n'en crée pas), première et dernière date.
        $resources = getAllDataFromTable(CostTables::RESOURCES, ['provider' => self::PROVIDER], false, 'resource');
        $this->assertCount(2, $resources);
        $vm = array_values(array_filter($resources, static fn (array $r): bool => $r['resource'] === 'vm-test-1'))[0];
        $this->assertSame('2026-09-30', $vm['first_seen']);
        $this->assertSame('2026-10-01', $vm['last_seen']);
        $this->assertSame(0, (int) $vm['items_id']);
    }

    public function testReportKeepsOneTablePerCurrency(): void
    {
        $result = (new CostImporter())->import($this->parsed(), 0, 'costs-test.csv');
        $this->imports[] = $result['import'];

        $tables = [];
        foreach (CostReport::byProviderAndMonth() as $table) {
            $tables[$table['currency']] = $table;
        }
        $this->assertArrayHasKey('USD', $tables);
        $this->assertArrayHasKey('EUR', $tables);
        $this->assertEqualsWithDelta(22.5, $tables['EUR']['totals'][self::PROVIDER], 0.001);
        $this->assertEqualsWithDelta(2.0, $tables['USD']['totals'][self::PROVIDER], 0.001);
    }

    public function testManualLinkToAComputerAndTabContent(): void
    {
        global $DB;

        $result = (new CostImporter())->import($this->parsed(), 0, 'costs-test.csv');
        $this->imports[] = $result['import'];
        $computerId = (int) (new Computer())->add(['name' => 'VM finops test', 'entities_id' => 0]);
        $this->computers[] = $computerId;
        $resource = $DB->request([
            'FROM'  => CostTables::RESOURCES,
            'WHERE' => ['provider' => self::PROVIDER, 'resource' => 'vm-test-1'],
        ])->current();
        $this->assertNotNull($resource);
        $id = (int) $resource['id'];

        $this->assertFalse(Resource::link($id, 'User', 2), 'type d\'actif non autorisé');
        $this->assertTrue(Resource::link($id, 'Computer', $computerId));
        $linked = Resource::presentOne((new Resource())->find(['id' => $id])[$id]);
        $this->assertSame('VM finops test', $linked['asset']['name']);
        $this->assertEqualsWithDelta(21.5, $linked['costs']['EUR'], 0.001);

        $computer = new Computer();
        $computer->getFromDB($computerId);
        ob_start();
        Resource::displayTabContentForItem($computer);
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('vm-test-1', $html);
        $this->assertStringContainsString('2026-10', $html);

        $this->assertTrue(Resource::link($id, '', 0));
        $this->assertSame(0, (int) (new Resource())->find(['id' => $id])[$id]['items_id']);
    }

    public function testDeletingAnImportDeletesItsLines(): void
    {
        $result = (new CostImporter())->import($this->parsed(), 0, 'costs-test.csv');
        $this->assertSame(4, countElementsInTable(CostTables::LINES, ['plugin_finops_costimports_id' => $result['import']]));

        $this->assertTrue((new CostImport())->delete(['id' => $result['import']], true));
        $this->assertSame(0, countElementsInTable(CostTables::LINES, ['plugin_finops_costimports_id' => $result['import']]));
    }
}
