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
use GlpiPlugin\Finops\AssetMatcher;
use GlpiPlugin\Finops\Config;
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\CostTables;
use GlpiPlugin\Finops\Resource;
use ItemVirtualMachine;
use NetworkEquipment;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement assisté (issue #6) sur des coûts importés par l'import CSV et des actifs créés par
 * le test : chaque règle propose le bon actif, une règle ambiguë laisse la main à la suivante, rien
 * n'est rattaché avant la validation, et la validation refuse une proposition qui a changé.
 * Données fictives, supprimées à la fin. Aucun texte traduit n'est comparé.
 */
final class AssetMatcherTest extends TestCase
{
    private const PROVIDER = 'finops-test-match';

    /** @var array<string, string> */
    private array $config = [];
    private int $import = 0;
    private string $suffix = '';
    /** @var array<string, int> */
    private array $computers = [];
    private int $switch = 0;
    private int $vm = 0;

    protected function setUp(): void
    {
        $this->config = Config::load();
        Config::save(['match_rules' => 'instance_id, vm_uuid, serial, name, glpi_id_tag'] + $this->config);

        $s = $this->suffix = bin2hex(random_bytes(4));
        $fields = [
            'uuid'   => ['name' => "m-uuid-$s", 'uuid' => "uuid-$s"],
            'host'   => ['name' => "m-host-$s"],
            'serial' => ['name' => "m-serial-$s", 'serial' => "SER-$s"],
            'name'   => ['name' => "web-$s"],
            'tag'    => ['name' => "m-tag-$s"],
            'dup1'   => ['name' => "dup-$s"],
            'dup2'   => ['name' => "dup-$s", 'serial' => "dup-$s"],
        ];
        foreach ($fields as $key => $values) {
            $this->computers[$key] = (int) (new Computer())->add($values + ['entities_id' => 0]);
        }
        $this->switch = (int) (new NetworkEquipment())->add(['name' => "sw-$s", 'entities_id' => 0]);
        $this->vm = (int) (new ItemVirtualMachine())->add([
            'itemtype' => 'Computer', 'items_id' => $this->computers['host'], 'entities_id' => 0,
            'name' => "vm-$s", 'uuid' => "vmuuid-$s",
        ]);

        $p = self::PROVIDER;
        $tag = $this->computers['tag'];
        $switch = $this->switch;
        $parsed = (new CsvCostParser('EUR', 1048576))->parseString(
            "date,provider,account,service,resource,cost,currency,tags\n"
            // Identifiant d'instance (dernier segment d'un chemin) = UUID de l'ordinateur.
            . "2026-10-01,$p,acct,Compute,projects/p1/instances/UUID-$s,1,EUR,\n"
            . "2026-10-01,$p,acct,Compute,vmuuid-$s,1,EUR,\n"
            . "2026-10-01,$p,acct,Compute,ser-$s,1,EUR,\n"
            . "2026-10-01,$p,acct,Compute,i-name-$s,1,EUR,Name=web-$s\n"
            . "2026-10-01,$p,acct,Compute,i-tag-$s,1,EUR,glpi_id=$tag\n"
            . "2026-10-01,$p,acct,Network,i-sw-$s,1,EUR,glpi_id=NetworkEquipment:$switch\n"
            // Deux ordinateurs du même nom : nom ambigu, le numéro de série tranche.
            . "2026-10-01,$p,acct,Compute,dup-$s,1,EUR,\n"
            . "2026-10-01,$p,acct,Compute,nothing-$s,1,EUR,glpi_id=999999999\n"
        );
        $this->import = (new CostImporter())->import($parsed, 0, 'match-test.csv')['import'];
    }

    protected function tearDown(): void
    {
        global $DB;

        (new CostImport())->delete(['id' => $this->import], true);
        $DB->delete(CostTables::LINES, ['provider' => self::PROVIDER]);
        $DB->delete(CostTables::RESOURCES, ['provider' => self::PROVIDER]);
        (new ItemVirtualMachine())->delete(['id' => $this->vm], true);
        (new NetworkEquipment())->delete(['id' => $this->switch], true);
        foreach ($this->computers as $id) {
            (new Computer())->delete(['id' => $id], true);
        }
        Config::save($this->config);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];
        foreach (Resource::page(false, $this->suffix, 0)['rows'] as $row) {
            $rows[(string) $row['resource']] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function proposed(array $row): string
    {
        $proposal = $row['proposal'];

        return $proposal === null ? '' : $proposal['itemtype'] . ':' . $proposal['items_id'] . '/' . $proposal['rule'];
    }

    public function testEachRuleProposesTheRightAsset(): void
    {
        $s = $this->suffix;
        $c = $this->computers;
        $rows = $this->rows();
        $this->assertCount(8, $rows);
        $this->assertSame("Computer:{$c['uuid']}/instance_id", $this->proposed($rows["projects/p1/instances/UUID-$s"]));
        $this->assertSame("Computer:{$c['host']}/vm_uuid", $this->proposed($rows["vmuuid-$s"]));
        $this->assertSame("Computer:{$c['serial']}/serial", $this->proposed($rows["ser-$s"]));
        $this->assertSame("Computer:{$c['name']}/name", $this->proposed($rows["i-name-$s"]));
        $this->assertSame("Computer:{$c['tag']}/glpi_id_tag", $this->proposed($rows["i-tag-$s"]));
        $this->assertSame("NetworkEquipment:{$this->switch}/glpi_id_tag", $this->proposed($rows["i-sw-$s"]));
        $this->assertSame('', $this->proposed($rows["nothing-$s"]));
        $this->assertFalse($rows["nothing-$s"]['ambiguous']);
        // Nom ambigu (deux ordinateurs) : la règle suivante dans l'ordre réglé décide.
        $this->assertSame("Computer:{$c['dup2']}/serial", $this->proposed($rows["dup-$s"]));

        // Rien n'est rattaché par la proposition.
        foreach ($rows as $row) {
            $this->assertSame(0, $row['items_id']);
        }
    }

    public function testRuleOrderAndAmbiguity(): void
    {
        $s = $this->suffix;
        Config::save(['match_rules' => 'name, bogus, NAME'] + $this->config);
        $this->assertSame(['name'], AssetMatcher::rules());
        $rows = $this->rows();
        $this->assertSame('', $this->proposed($rows["dup-$s"]));
        $this->assertTrue($rows["dup-$s"]['ambiguous']);
        $this->assertSame('', $this->proposed($rows["ser-$s"]));

        Config::save(['match_rules' => ''] + $this->config);
        $this->assertSame([], AssetMatcher::rules());
        $this->assertSame('', $this->proposed($this->rows()["i-tag-$s"]));
    }

    public function testValidationLinksOnlyConfirmedUnchangedProposals(): void
    {
        $s = $this->suffix;
        $rows = $this->rows();
        $serial = $rows["ser-$s"];
        $tag = $rows["i-tag-$s"];
        $name = $rows["i-name-$s"];

        $result = AssetMatcher::validate([
            $serial['id'] => $serial['proposal']['value'],
            $tag['id']    => $tag['proposal']['value'],
            // Valeur qui n'est pas la proposition : refusée.
            $name['id']   => 'Computer:' . $this->computers['uuid'],
        ]);
        $this->assertSame(['linked' => 2, 'skipped' => 1], $result);

        $rows = $this->rows();
        $this->assertSame($this->computers['serial'], $rows["ser-$s"]['items_id']);
        $this->assertSame($this->computers['tag'], $rows["i-tag-$s"]['items_id']);
        $this->assertSame(0, $rows["i-name-$s"]['items_id']);
        $this->assertNull($rows["ser-$s"]['proposal'], 'plus de proposition une fois rattachée');
        $this->assertSame(0, $rows["vmuuid-$s"]['items_id'], 'non cochée : non rattachée');

        // Déjà rattachée : ignorée.
        $again = AssetMatcher::validate([$serial['id'] => $serial['proposal']['value']]);
        $this->assertSame(['linked' => 0, 'skipped' => 1], $again);
    }
}
