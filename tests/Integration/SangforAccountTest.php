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
use GLPIKey;
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\Install\CostTables;
use GlpiPlugin\Finops\Install\SangforTables;
use GlpiPlugin\Finops\SangforAccount;
use GlpiPlugin\Finops\Tests\Fake\FakeScp;
use PHPUnit\Framework\TestCase;

final class SangforAccountTest extends TestCase
{
    private const NAME = 'finops-test-sangfor';

    /** @var list<int> */
    private array $computers = [];

    protected function tearDown(): void
    {
        global $DB;

        foreach ($DB->request(['FROM' => CostTables::IMPORTS, 'WHERE' => ['filename' => self::NAME]]) as $row) {
            (new CostImport())->delete(['id' => (int) $row['id']], true);
        }
        $DB->delete(CostTables::RESOURCES, ['provider' => 'sangfor', 'account' => self::NAME]);
        foreach ($DB->request(['FROM' => SangforTables::ACCOUNTS, 'WHERE' => ['name' => self::NAME]]) as $row) {
            (new SangforAccount())->delete(['id' => (int) $row['id']], true);
        }
        foreach ($this->computers as $id) {
            (new Computer())->delete(['id' => $id], true);
        }
        $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function account(array $extra = []): SangforAccount
    {
        $account = new SangforAccount();
        $id = (int) $account->add($extra + [
            'name'          => self::NAME,
            'entities_id'   => 0,
            'url'           => FakeScp::BASE . '/',
            'username'      => FakeScp::USER,
            'password'      => FakeScp::PASSWORD,
            'price_vcpu'    => '10',
            'price_ram_gb'  => '2,5',
            'price_disk_gb' => '0.1',
            'currency'      => 'eur',
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($account->getFromDB($id));

        return $account;
    }

    /**
     * @param array<string, mixed> $where
     */
    private function rows(string $table, array $where): int
    {
        global $DB;

        return count($DB->request(['FROM' => $table, 'WHERE' => $where]));
    }

    private function vms(SangforAccount $account): int
    {
        return $this->rows(SangforTables::VMS, ['plugin_finops_sangforaccounts_id' => $account->getID()]);
    }

    public function testLoginIsEncryptedAndSafeDefaultsApply(): void
    {
        $account = $this->account();
        $this->assertSame(FakeScp::BASE, $account->fields['url']);
        $this->assertSame(0, (int) $account->fields['allow_self_signed']);
        $this->assertSame('EUR', $account->fields['currency']);
        $this->assertSame('2.500000', $account->fields['price_ram_gb']);
        $this->assertNotSame(FakeScp::PASSWORD, $account->fields['password']);
        $this->assertSame(FakeScp::PASSWORD, (new GLPIKey())->decrypt($account->fields['password']));
        $listing = json_encode(SangforAccount::listing());
        $this->assertStringNotContainsString(FakeScp::PASSWORD, (string) $listing);
        $this->assertStringNotContainsString(FakeScp::USER, (string) $listing);
    }

    public function testFormSendsTheNameAndThePortInsteadOfAnUrl(): void
    {
        // Issue #48 : saisie séparée, l'URL est reconstruite côté serveur en https.
        $account = $this->account(['url' => null, 'scp_host' => '192.0.2.10', 'scp_port' => '4430']);
        $this->assertSame('https://192.0.2.10:4430', $account->fields['url']);
        $row = array_values(array_filter(
            SangforAccount::listing(),
            fn (array $row) => $row['id'] === $account->getID()
        ))[0];
        $this->assertSame(['192.0.2.10', '4430'], [$row['scp_host'], $row['scp_port']]);
        $this->assertTrue($account->update([
            'id' => $account->getID(), 'scp_host' => 'scp.example.test', 'scp_port' => '',
        ]));
        $this->assertTrue($account->getFromDB($account->getID()));
        $this->assertSame('https://scp.example.test', $account->fields['url']);
        $this->assertFalse((new SangforAccount())->add([
            'name' => self::NAME, 'scp_host' => 'scp.example.test/janus', 'scp_port' => '', 'username' => 'u',
            'password' => 'p',
        ]));
    }

    public function testInvalidAddressOrPriceIsRefused(): void
    {
        $this->assertFalse((new SangforAccount())->add([
            'name' => self::NAME, 'url' => 'http://scp.example.test', 'username' => 'u', 'password' => 'p',
        ]));
        $this->assertFalse((new SangforAccount())->add([
            'name' => self::NAME, 'url' => FakeScp::BASE, 'username' => 'u', 'password' => 'p', 'price_vcpu' => '-1',
        ]));
    }

    public function testReadStoresTheInventoryAndTheCostOfTheMonthOnce(): void
    {
        $account = $this->account();
        $result = $account->readInventory(new FakeScp());
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(3, $result['vms']);
        $this->assertSame(3, $result['created']);
        $this->assertSame(3, $this->vms($account));
        $this->assertSame(3, $this->rows(CostTables::RESOURCES, ['provider' => 'sangfor', 'account' => self::NAME]));

        $inventory = $account->inventory();
        $this->assertCount(2, $inventory['nodes']);
        $web = array_values(array_filter($inventory['vms'], fn ($vm) => $vm['vm_id'] === FakeScp::VM_WEB))[0];
        // 4 vCPU x 10 + 8 GB x 2.5 + 100 GB x 0.1.
        $this->assertEqualsWithDelta(70.0, $web['monthly_cost'], 0.0001);
        $off = array_values(array_filter($inventory['vms'], fn ($vm) => $vm['vm_id'] === FakeScp::VM_OFF))[0];
        $this->assertEqualsWithDelta(1.0, $off['monthly_cost'], 0.0001);

        $again = $account->readInventory(new FakeScp());
        $this->assertSame([0, 3], [$again['created'], $again['known']]);
    }

    public function testFailedReadKeepsTheInventory(): void
    {
        $account = $this->account();
        $account->readInventory(new FakeScp());
        $fake = new FakeScp();
        $fake->failures['/janus/20180725/servers'] = 503;
        $result = $account->readInventory($fake);
        $this->assertFalse($result['ok']);
        $this->assertSame(3, $this->vms($account));
        $this->assertSame('error', $account->fields['last_status']);
        $this->assertNotEmpty($account->fields['last_success']);
        $this->assertStringNotContainsString('fake failure', $result['message']);
    }

    public function testComputersAreProposedByUuidThenNameAndLinkedOnlyOnRequest(): void
    {
        $byUuid = (int) (new Computer())->add([
            'name' => 'finops-test-other-name', 'entities_id' => 0, 'uuid' => strtoupper(FakeScp::VM_WEB),
        ]);
        $byName = (int) (new Computer())->add(['name' => 'db-fictive-01', 'entities_id' => 0]);
        $this->computers = [$byUuid, $byName];
        $account = $this->account();
        $account->readInventory(new FakeScp());

        $vms = [];
        foreach ($account->inventory()['vms'] as $vm) {
            $vms[$vm['vm_id']] = $vm;
        }
        $this->assertSame(['id' => $byUuid, 'by' => 'uuid'], array_intersect_key(
            (array) $vms[FakeScp::VM_WEB]['suggestion'],
            ['id' => 0, 'by' => 0]
        ));
        $this->assertSame('name', $vms[FakeScp::VM_DB]['suggestion']['by'] ?? '');
        $this->assertSame('', $vms[FakeScp::VM_WEB]['linked'], 'nothing is linked by itself');

        $this->assertTrue(SangforAccount::linkVm($vms[FakeScp::VM_WEB]['id'], $byUuid));
        $after = array_column($account->inventory()['vms'], null, 'vm_id');
        $this->assertSame('finops-test-other-name', $after[FakeScp::VM_WEB]['linked']);
        $this->assertNull($after[FakeScp::VM_WEB]['suggestion']);
    }

    public function testPurgeRemovesTheInventory(): void
    {
        $account = $this->account();
        $account->readInventory(new FakeScp());
        $id = (int) $account->getID();
        $account->delete(['id' => $id], true);
        $this->assertSame(0, $this->rows(SangforTables::VMS, ['plugin_finops_sangforaccounts_id' => $id]));
    }
}
