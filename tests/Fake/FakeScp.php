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

namespace GlpiPlugin\Finops\Tests\Fake;

use GlpiPlugin\Finops\Sangfor\ScpTransport;

/**
 * Faux Sangfor SCP : vraie paire de clés RSA (le mot de passe chiffré par le plugin est déchiffré
 * comme le ferait SCP), jeton exigé sur chaque GET, listes paginées, données fictives au format de la
 * documentation OpenAPI 6.10.0. Note chaque appel (méthode et adresse).
 */
final class FakeScp implements ScpTransport
{
    public const BASE = 'https://scp.example.test';
    public const USER = 'finops-reader';
    public const PASSWORD = 'Fictive-Pass-1';
    public const TOKEN = 'f6a5bfca121a401f877af196dde17ed5';
    public const VM_WEB = '661ce7a4-5abe-4191-832c-7d1dc6c5fd9a';
    public const VM_DB = 'bb22a4b2-41e7-4404-89c1-745182f790d6';
    public const VM_OFF = '81d8ad67-0a81-4bd6-aba4-6b96b5acbdea';

    /** @var list<array{0: string, 1: string}> */
    public array $calls = [];

    /** @var array<string, int> Chemin => code HTTP imposé. */
    public array $failures = [];

    /** Vrai : seule l'ancienne adresse de la clé publique existe (SCP avant 6.10). */
    public bool $legacyKey = false;

    /** @var list<array<mixed>>|null Liste des VM imposée (une seule page). */
    public ?array $servers = null;

    private static ?\OpenSSLAsymmetricKey $key = null;

    private static function key(): \OpenSSLAsymmetricKey
    {
        if (self::$key === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($key === false) {
                throw new \RuntimeException('OpenSSL cannot create a test key.');
            }
            self::$key = $key;
        }

        return self::$key;
    }

    public static function modulus(): string
    {
        $details = openssl_pkey_get_details(self::key());

        return strtoupper(bin2hex((string) ($details['rsa']['n'] ?? '')));
    }

    public function request(string $method, string $url, array $headers, string $body = ''): array
    {
        $this->calls[] = [$method, $url];
        if (!str_starts_with($url, self::BASE . '/')) {
            return [404, '{"message":"unknown host"}'];
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($this->failures[$path])) {
            return [$this->failures[$path], '{"code":1,"message":"<b>fake failure</b>","data":{}}'];
        }
        if ($method === 'GET' && $path === '/janus/v2/public-key') {
            return $this->legacyKey
                ? [404, '{"code":404,"message":"not found","data":{}}']
                : [200, (string) json_encode(['public_key' => self::modulus() . '\n'])];
        }
        if ($method === 'GET' && $path === '/janus/public-key' && $this->legacyKey) {
            return [200, $this->envelope(['public_key' => self::modulus()])];
        }
        if ($method === 'POST' && $path === '/janus/authenticate') {
            return $this->authenticate($body);
        }
        if ($method !== 'GET' || ($headers['Authorization'] ?? '') !== 'Token ' . self::TOKEN) {
            return [401, '{"code":401,"message":"Unauthorized","data":{}}'];
        }

        return match ($path) {
            '/janus/20180725/servers' => [200, $this->envelope($this->serversPage((int) ($query['page_num'] ?? 0)))],
            '/janus/20180725/hosts'   => [200, $this->envelope([
                [
                    'id' => 'host-6c92bfde3a4c', 'name' => 'node-a', 'ip' => '192.0.2.11', 'status' => 'running',
                    'cpu' => ['core_count' => 32, 'socket_count' => 2, 'cores_per_socket' => 16],
                    'memory' => ['total_mb' => 262144.0], 'storage' => ['total_mb' => 4105435.99],
                ],
                [
                    'id' => 'host-7d03c0ef4b5d', 'ip' => '192.0.2.12', 'status' => 'offline',
                    'cpu' => ['socket_count' => 2, 'cores_per_socket' => 8], 'memory' => ['total_mb' => 131072],
                ],
            ])],
            default => [404, '{"code":404,"message":"not found","data":{}}'],
        };
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function authenticate(string $body): array
    {
        $json = json_decode($body, true);
        $credentials = is_array($json) ? ($json['auth']['passwordCredentials'] ?? []) : [];
        $clear = '';
        $cipher = hex2bin((string) ($credentials['password'] ?? '')) ?: '';
        if ($cipher === '' || !openssl_private_decrypt($cipher, $clear, self::key(), OPENSSL_PKCS1_PADDING)) {
            $clear = '';
        }
        if (($credentials['username'] ?? '') !== self::USER || $clear !== self::PASSWORD) {
            return [401, '{"code":1,"message":"Invalid user or password","data":{}}'];
        }

        return [200, $this->envelope(['access' => [
            'token' => ['id' => self::TOKEN, 'expires' => '2026-01-02T00:00:00.000000Z'],
            'user'  => ['username' => self::USER],
        ]])];
    }

    /**
     * @return array<string, mixed>
     */
    private function serversPage(int $page): array
    {
        if ($this->servers !== null) {
            return ['total_size' => count($this->servers), 'page_num' => 0, 'page_size' => 100,
                'next_page_num' => '', 'data' => $this->servers];
        }
        $pages = [
            [
                [
                    'id' => self::VM_WEB, 'name' => 'web-fictive-01', 'status' => 'running', 'power_state' => 'on',
                    'cores' => 4, 'sockets' => 2, 'cores_per_socket' => 2, 'memory_mb' => 8192.0,
                    'disks' => [['id' => 'ide0', 'size_mb' => 51200.0], ['id' => 'ide1', 'size_mb' => 51200.0]],
                    'host_id' => 'host-6c92bfde3a4c', 'host_name' => 'node-a', 'az_name' => 'pool-fictive',
                    'os_name' => 'Debian 12',
                ],
                [
                    'id' => self::VM_DB, 'name' => 'db-fictive-01', 'power_state' => 'on',
                    'sockets' => 1, 'cores_per_socket' => 8, 'memory_status' => ['total_mb' => 16384],
                    'storage_mb' => 204800, 'host_id' => 'host-7d03c0ef4b5d',
                ],
            ],
            [
                [
                    'id' => self::VM_OFF, 'name' => 'old-fictive-01', 'status' => 'stopped', 'cores' => 2,
                    'memory_mb' => 4096, 'disks' => [['size_mb' => 10240]], 'host_name' => 'node-a',
                ],
                ['name' => 'no-identifier'],
            ],
        ];

        return [
            'total_size'    => 4,
            'page_num'      => $page,
            'page_size'     => 100,
            'next_page_num' => $page === 0 ? '1' : '',
            'data'          => $pages[$page] ?? [],
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function envelope(array $data): string
    {
        return (string) json_encode(['code' => 0, 'message' => '', 'data' => $data]);
    }
}
