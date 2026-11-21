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

use GLPIKey;
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\Install\CostTables;
use GlpiPlugin\Finops\Install\OvhTables;
use GlpiPlugin\Finops\OvhAccount;
use GlpiPlugin\Finops\Tests\Fake\FakeOvh;
use PHPUnit\Framework\TestCase;

final class OvhAccountTest extends TestCase
{
    private const NAME = 'finops-test-ovh';

    protected function tearDown(): void
    {
        global $DB;

        foreach ($DB->request(['FROM' => CostTables::IMPORTS, 'WHERE' => ['filename' => self::NAME]]) as $row) {
            (new CostImport())->delete(['id' => (int) $row['id']], true);
        }
        $DB->delete(CostTables::RESOURCES, ['provider' => 'ovhcloud', 'account' => 'xx1111-ovh']);
        $DB->delete(OvhTables::ACCOUNTS, ['name' => self::NAME]);
        $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];
    }

    private function account(): OvhAccount
    {
        $account = new OvhAccount();
        $id = (int) $account->add([
            'name'               => self::NAME,
            'entities_id'        => 0,
            'region'             => 'eu',
            'application_key'    => FakeOvh::APP_KEY,
            'application_secret' => FakeOvh::APP_SECRET,
            'consumer_key'       => FakeOvh::CONSUMER,
            'projects'           => strtoupper(FakeOvh::PROJECT),
            'months'             => 99,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($account->getFromDB($id));

        return $account;
    }

    private function lines(): int
    {
        global $DB;

        return count($DB->request([
            'FROM'  => CostTables::LINES,
            'WHERE' => ['provider' => 'ovhcloud', 'account' => 'xx1111-ovh'],
        ]));
    }

    public function testKeysAreEncryptedAndNeverListed(): void
    {
        $account = $this->account();
        $this->assertNotSame(FakeOvh::APP_SECRET, $account->fields['application_secret']);
        $this->assertSame(FakeOvh::APP_SECRET, (new GLPIKey())->decrypt($account->fields['application_secret']));
        $this->assertSame(FakeOvh::PROJECT, $account->fields['projects']);
        $this->assertSame(OvhAccount::MAX_MONTHS, (int) $account->fields['months']);
        $listed = array_values(array_filter(
            OvhAccount::listing(),
            static fn (array $row): bool => $row['id'] === $account->getID()
        ));
        $this->assertCount(1, $listed);
        $this->assertTrue($listed[0]['keys_set']);
        $dump = (string) json_encode($listed);
        $secrets = [FakeOvh::APP_KEY, FakeOvh::APP_SECRET, FakeOvh::CONSUMER, $account->fields['consumer_key']];
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    public function testEmptyKeyOnUpdateKeepsTheSavedKey(): void
    {
        $account = $this->account();
        $this->assertTrue($account->update([
            'id'                 => $account->getID(),
            'application_secret' => '',
            'region'             => 'ca',
        ]));
        $account->getFromDB($account->getID());
        $this->assertSame(FakeOvh::APP_SECRET, (new GLPIKey())->decrypt($account->fields['application_secret']));
        $this->assertSame('ca', $account->fields['region']);
    }

    public function testInvalidInputIsRefused(): void
    {
        $base = [
            'name'               => self::NAME,
            'entities_id'        => 0,
            'application_key'    => FakeOvh::APP_KEY,
            'application_secret' => FakeOvh::APP_SECRET,
            'consumer_key'       => FakeOvh::CONSUMER,
        ];
        $this->assertFalse((new OvhAccount())->add(['projects' => 'not-a-project'] + $base));
        $this->assertFalse((new OvhAccount())->add(['consumer_key' => ''] + $base));
        $this->assertFalse((new OvhAccount())->add(['application_key' => 'bad key; drop'] + $base));
    }

    public function testReadImportsOnceAndFailedReadKeepsThePreviousOne(): void
    {
        global $DB;

        $account = $this->account();
        $result = $account->readCosts(new FakeOvh());
        $this->assertTrue($result['ok']);
        $this->assertSame(4, $result['created']);
        $this->assertSame(4, $this->lines());
        $import = $DB->request([
            'FROM'  => CostTables::IMPORTS,
            'WHERE' => ['id' => $account->fields['plugin_finops_costimports_id']],
        ])->current();
        $this->assertSame('ovhcloud', $import['source']);
        $this->assertSame(self::NAME, $import['filename']);
        $success = $account->fields['last_success'];

        $again = $account->readCosts(new FakeOvh());
        $this->assertSame(0, $again['created']);
        $this->assertSame(4, $again['known']);

        $broken = new FakeOvh();
        $broken->failures['/me/bill/FR0001/details/D1'] = 500;
        $failed = $account->readCosts($broken);
        $this->assertFalse($failed['ok']);
        $this->assertSame(4, $this->lines());
        $account->getFromDB($account->getID());
        $this->assertSame(OvhAccount::STATUS_ERROR, $account->fields['last_status']);
        $this->assertSame($success, $account->fields['last_success']);
        $this->assertNotSame('', OvhAccount::failureLabel($account->fields['last_message']));
    }
}
