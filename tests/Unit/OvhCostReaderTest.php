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

namespace GlpiPlugin\Finops\Tests\Unit;

use GlpiPlugin\Finops\Ovh\OvhClient;
use GlpiPlugin\Finops\Ovh\OvhCostReader;
use GlpiPlugin\Finops\Ovh\OvhException;
use GlpiPlugin\Finops\Tests\Fake\FakeOvh;
use PHPUnit\Framework\TestCase;

final class OvhCostReaderTest extends TestCase
{
    private function client(FakeOvh $fake, string $secret = FakeOvh::APP_SECRET, string $region = 'eu'): OvhClient
    {
        return new OvhClient($fake, $region, FakeOvh::APP_KEY, $secret, FakeOvh::CONSUMER);
    }

    public function testSignatureFollowsTheOvhRule(): void
    {
        $this->assertSame(
            '$1$339c490c60fda4ea0be481ea35727374852af496',
            OvhClient::sign('secretAAAA1111', 'consumerBBBB2222', 'GET', OvhClient::ENDPOINTS['eu'] . '/me', '', 1700000000)
        );
    }

    public function testSignedRequestIsAcceptedWithTheServerClock(): void
    {
        $fake = new FakeOvh();
        $me = $this->client($fake)->get('/me');
        $this->assertSame('xx1111-ovh', $me['nichandle']);
        $this->assertSame(OvhClient::ENDPOINTS['eu'] . '/auth/time', $fake->calls[0]);
    }

    public function testWrongSecretIsRefused(): void
    {
        try {
            $this->client(new FakeOvh(), 'wrongSecret0001')->get('/me');
            $this->fail('Request accepted with a wrong secret.');
        } catch (OvhException $e) {
            $this->assertSame(OvhException::R_AUTH, $e->reason);
            $this->assertStringNotContainsString('wrongSecret0001', $e->getMessage());
        }
    }

    public function testCanadaRegionCallsTheCanadianEndpoint(): void
    {
        $fake = new FakeOvh(OvhClient::ENDPOINTS['ca']);
        $this->client($fake, FakeOvh::APP_SECRET, 'ca')->get('/me');
        $this->assertStringStartsWith('https://ca.api.ovh.com/1.0/', $fake->calls[1]);
    }

    public function testUnknownRegionOrMissingKeyIsRefused(): void
    {
        foreach ([['us', FakeOvh::APP_SECRET], ['eu', '']] as [$region, $secret]) {
            try {
                $this->client(new FakeOvh(), $secret, $region);
                $this->fail('Client created without a valid region or key.');
            } catch (OvhException $e) {
                $this->assertSame(OvhException::R_KEYS, $e->reason);
            }
        }
    }

    public function testReadsBillsAndProjectUsageWithoutDoubleCounting(): void
    {
        $parsed = (new OvhCostReader($this->client(new FakeOvh())))->read('2026-08-01', [FakeOvh::PROJECT]);
        $this->assertSame(1, $parsed['rejected']);
        $byResource = [];
        foreach ($parsed['rows'] as $row) {
            $this->assertSame('ovhcloud', $row['provider']);
            $this->assertSame('xx1111-ovh', $row['account']);
            $this->assertSame('EUR', $row['currency']);
            $byResource[$row['resource']] = $row;
        }
        ksort($byResource);
        $this->assertSame(['', 'ns0000.ip-192-0-2.example', 'vm-fictive-1', 'vm-fictive-2'], array_keys($byResource));
        $this->assertSame('59.990000', $byResource['ns0000.ip-192-0-2.example']['cost']);
        $this->assertSame('2026-09-01', $byResource['ns0000.ip-192-0-2.example']['usage_date']);
        $this->assertSame('50.250000', $byResource['vm-fictive-1']['cost']);
        $this->assertSame('Public Cloud instance b3-8', $byResource['vm-fictive-1']['service']);
        $this->assertStringContainsString('GRA11', $byResource['vm-fictive-1']['tags']);
        $this->assertSame('3.500000', $byResource['']['cost']);
    }

    public function testWithoutProjectTheBillLineIsKept(): void
    {
        $parsed = (new OvhCostReader($this->client(new FakeOvh())))->read('2026-08-01', []);
        $this->assertSame(['ns0000.ip-192-0-2.example', FakeOvh::PROJECT], array_column($parsed['rows'], 'resource'));
    }

    public function testSourceKeysAreStableBetweenReads(): void
    {
        $first = (new OvhCostReader($this->client(new FakeOvh())))->read('2026-08-01', [FakeOvh::PROJECT]);
        $second = (new OvhCostReader($this->client(new FakeOvh())))->read('2026-08-01', [FakeOvh::PROJECT]);
        $keys = array_column($first['rows'], 'source_key');
        $this->assertSame($keys, array_column($second['rows'], 'source_key'));
        $this->assertCount(count($keys), array_unique($keys));
    }

    public function testAnyFailureStopsTheWholeRead(): void
    {
        $fake = new FakeOvh();
        $fake->failures['/cloud/project/' . FakeOvh::PROJECT . '/usage/history/U1'] = 503;
        try {
            (new OvhCostReader($this->client($fake)))->read('2026-08-01', [FakeOvh::PROJECT]);
            $this->fail('Read succeeded despite a failure.');
        } catch (OvhException $e) {
            $this->assertSame(OvhException::R_SERVER, $e->reason);
            $this->assertStringNotContainsString('fake failure', $e->getMessage());
        }
    }
}
