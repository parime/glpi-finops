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

use GlpiPlugin\Finops\Sangfor\ScpClient;
use GlpiPlugin\Finops\Sangfor\ScpException;
use GlpiPlugin\Finops\Sangfor\ScpInventoryReader;
use GlpiPlugin\Finops\Tests\Fake\FakeScp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScpClientTest extends TestCase
{
    private function client(FakeScp $fake, string $password = FakeScp::PASSWORD): ScpClient
    {
        return new ScpClient($fake, FakeScp::BASE, FakeScp::USER, $password);
    }

    private function reason(callable $call): string
    {
        try {
            $call();
        } catch (ScpException $e) {
            return $e->reason;
        }
        $this->fail('ScpException expected');
    }

    public function testOnlyHttpsAddressesWithoutPathAreAccepted(): void
    {
        $this->assertSame('https://scp.example.test', ScpClient::normalizeUrl(' https://SCP.example.test/ '));
        $this->assertSame('https://192.0.2.10:4430', ScpClient::normalizeUrl('https://192.0.2.10:4430'));
        foreach (
            [
                'http://scp.example.test', 'https://scp.example.test/janus', 'https://user:pw@scp.example.test',
                'https://scp.example.test?x=1', 'ftp://scp.example.test', 'scp.example.test', 'https://bad_host',
            ] as $bad
        ) {
            $this->assertNull(ScpClient::normalizeUrl($bad), $bad);
        }
        $this->assertSame(ScpException::R_CONFIG, $this->reason(
            fn () => new ScpClient(new FakeScp(), 'https://scp.example.test/x', FakeScp::USER, FakeScp::PASSWORD)
        ));
    }

    public function testAddressIsBuiltFromTheNameAndThePortTypedSeparately(): void
    {
        // Issue #48 : le formulaire envoie le nom et le port, jamais une URL complète.
        $this->assertSame('https://192.0.2.10:4430', ScpClient::urlFromParts(' 192.0.2.10 ', '4430'));
        $this->assertSame('https://scp.example.test', ScpClient::urlFromParts('SCP.example.test', ''));
        $pasted = ScpClient::urlFromParts('https://scp.example.test/', '4430');
        $this->assertSame('https://scp.example.test:4430', $pasted);
        $this->assertSame('https://scp.example.test:4430', ScpClient::urlFromParts('scp.example.test:4430', ''));
        $this->assertSame('https://[2001:db8::1]:4430', ScpClient::urlFromParts('2001:db8::1', '4430'));
        foreach (
            [
                ['', '443'], ['http://scp.example.test', ''], ['scp.example.test/janus', ''],
                ['user@scp.example.test', ''], ['scp.example.test', '0'], ['scp.example.test', '65536'],
                ['scp.example.test', '44a'], ['scp.example.test:4430', '4430'], ['bad_host', ''],
                ['scp.example.test?x=1', ''],
            ] as [$host, $port]
        ) {
            $this->assertNull(ScpClient::urlFromParts($host, $port), $host . ' / ' . $port);
        }
        $parts = ScpClient::urlParts('https://192.0.2.10:4430');
        $this->assertSame(['host' => '192.0.2.10', 'port' => '4430'], $parts);
        $parts = ScpClient::urlParts('https://scp.example.test');
        $this->assertSame(['host' => 'scp.example.test', 'port' => ''], $parts);
    }

    public function testPasswordIsEncryptedWithTheModulusAndTheTokenIsUsed(): void
    {
        $fake = new FakeScp();
        $client = $this->client($fake);
        $hosts = $client->all('/janus/20180725/hosts');
        $this->assertCount(2, $hosts);
        $this->assertSame(['GET', FakeScp::BASE . '/janus/v2/public-key'], $fake->calls[0]);
        $this->assertSame(['POST', FakeScp::BASE . '/janus/authenticate'], $fake->calls[1]);
        $this->assertStringStartsWith(FakeScp::BASE . '/janus/20180725/hosts?page_num=0', $fake->calls[2][1]);
        $this->assertNotSame(
            ScpClient::encryptPassword(FakeScp::PASSWORD, FakeScp::modulus()),
            ScpClient::encryptPassword(FakeScp::PASSWORD, FakeScp::modulus()),
            'PKCS#1 v1.5 padding is random'
        );
    }

    public function testLegacyPublicKeyAddressIsTried(): void
    {
        $fake = new FakeScp();
        $fake->legacyKey = true;
        $this->client($fake)->login();
        $this->assertSame(FakeScp::BASE . '/janus/public-key', $fake->calls[1][1]);
    }

    /**
     * Les deux formes de réponse de clé publique (sans enveloppe comme les exemples des § 4.2.3.4 et
     * 4.2.4.4, ou dans l'enveloppe commune du § 2.5), sur les deux adresses.
     *
     * @return array<string, array{0: bool, 1: string}>
     */
    public static function keyForms(): array
    {
        return [
            'v2 bare'     => [false, 'bare'],
            'v2 data'     => [false, 'data'],
            'legacy bare' => [true, 'bare'],
            'legacy data' => [true, 'data'],
        ];
    }

    #[DataProvider('keyForms')]
    public function testBothPublicKeyFormsAreAccepted(bool $legacy, string $form): void
    {
        $fake = new FakeScp();
        $fake->legacyKey = $legacy;
        $fake->keyForm = $form;
        $this->assertCount(2, $this->client($fake)->all('/janus/20180725/hosts'));
    }

    /** @return array<string, array{0: int}> */
    public static function v2Failures(): array
    {
        return ['HTTP 400' => [400], 'HTTP 500' => [500], 'HTTP 200 without key' => [200]];
    }

    #[DataProvider('v2Failures')]
    public function testLegacyPublicKeyAddressIsTriedWhenV2GivesNoKey(int $status): void
    {
        $fake = new FakeScp();
        $fake->legacyKey = true;
        $fake->v2KeyStatus = $status;
        $this->client($fake)->login();
        $this->assertSame(FakeScp::BASE . '/janus/public-key', $fake->calls[1][1]);
    }

    public function testRateLimitOnV2IsNotRetriedOnTheLegacyAddress(): void
    {
        $fake = new FakeScp();
        $fake->legacyKey = true;
        $fake->v2KeyStatus = 403;
        $this->assertSame(ScpException::R_AUTH, $this->reason(fn () => $this->client($fake)->login()));
        $this->assertCount(1, $fake->calls);
    }

    public function testWrongPasswordIsAnAuthenticationFailure(): void
    {
        $this->assertSame(ScpException::R_AUTH, $this->reason(fn () => $this->client(new FakeScp(), 'wrong')->login()));
    }

    public function testInvalidModulusIsRefused(): void
    {
        $this->assertSame(ScpException::R_CRYPTO, $this->reason(fn () => ScpClient::encryptPassword('x', 'zz')));
    }

    public function testHttpFailuresAndRedirectsAreMapped(): void
    {
        foreach (
            [
                403 => ScpException::R_AUTH, 404 => ScpException::R_NOT_FOUND, 429 => ScpException::R_RATE,
                503 => ScpException::R_SERVER, 302 => ScpException::R_RESPONSE,
            ] as $status => $reason
        ) {
            $fake = new FakeScp();
            $fake->failures['/janus/20180725/servers'] = $status;
            $this->assertSame($reason, $this->reason(fn () => $this->client($fake)->all('/janus/20180725/servers')));
        }
    }

    public function testErrorCodeInTheEnvelopeIsAFailure(): void
    {
        $fake = new FakeScp();
        $fake->failures['/janus/20180725/hosts'] = 200;
        $this->assertSame(
            ScpException::R_RESPONSE,
            $this->reason(fn () => $this->client($fake)->get('/janus/20180725/hosts'))
        );
    }

    public function testPagesAreFollowedAndOnlyTheConfiguredAddressIsCalled(): void
    {
        $fake = new FakeScp();
        $inventory = (new ScpInventoryReader($this->client($fake)))->read();
        $this->assertCount(3, $inventory['vms']);
        $this->assertSame(1, $inventory['rejected']);
        $this->assertCount(2, $inventory['nodes']);
        foreach ($fake->calls as [$method, $url]) {
            $this->assertStringStartsWith(FakeScp::BASE . '/janus/', $url);
            if ($method !== 'GET') {
                $this->assertSame(FakeScp::BASE . '/janus/authenticate', $url, 'the only POST is the login');
            }
        }
    }
}
