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

use GlpiPlugin\Finops\Ovh\OvhClient;
use GlpiPlugin\Finops\Ovh\OvhTransport;

/**
 * Faux OVHcloud : vérifie la signature de chaque requête comme le ferait l'API (403 sinon),
 * répond avec des données fictives et note les adresses appelées. Seul GET existe. La consommation
 * suit le modèle cloud.usage.UsageHistoryDetail de la référence officielle
 * (https://eu.api.ovh.com/1.0/cloud.json) : hourlyUsage.quantum est un objet de listes et
 * resourcesUsage porte ses prix dans resources[].components[].
 */
final class FakeOvh implements OvhTransport
{
    public const APP_KEY = 'fakeAppKey0001';
    public const APP_SECRET = 'fakeAppSecret0001';
    public const CONSUMER = 'fakeConsumerKey0001';
    public const PROJECT = '0123456789abcdef0123456789abcdef';
    public const SERVER_TIME = 1790000000;

    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, int> Chemin => code HTTP imposé. */
    public array $failures = [];

    /** @var array<string, mixed> */
    private array $routes;

    public function __construct(private readonly string $base = OvhClient::ENDPOINTS['eu'])
    {
        $this->routes = [
            '/me'                       => ['nichandle' => 'xx1111-ovh', 'currency' => ['code' => 'EUR']],
            '/me/bill'                  => ['FR0001'],
            '/me/bill/FR0001'           => ['billId' => 'FR0001', 'date' => '2026-10-01T00:00:00+02:00'],
            '/me/bill/FR0001/details'   => ['D1', 'D2', 'D3', 'D4'],
            '/me/bill/FR0001/details/D1' => [
                'description' => 'Serveur dedie fictif', 'domain' => 'ns0000.ip-192-0-2.example',
                'periodStart' => '2026-09-01', 'totalPrice' => ['currencyCode' => 'EUR', 'value' => 59.99],
            ],
            '/me/bill/FR0001/details/D2' => [
                'description' => 'Nom de domaine fictif', 'domain' => 'finops-test.example',
                'periodStart' => '2026-09-15', 'totalPrice' => ['currencyCode' => 'EUR', 'value' => 0],
            ],
            '/me/bill/FR0001/details/D3' => [
                'description' => 'Public Cloud', 'domain' => self::PROJECT,
                'periodStart' => '2026-09-01', 'totalPrice' => ['currencyCode' => 'EUR', 'value' => 120.5],
            ],
            '/me/bill/FR0001/details/D4' => [
                'description' => 'Ligne illisible', 'domain' => 'x', 'periodStart' => '2026-09-01',
                'totalPrice' => 'n/a',
            ],
            '/cloud/project/' . self::PROJECT . '/usage/history' => [
                ['id' => 'U1', 'period' => ['from' => '2026-09-01T00:00:00Z', 'to' => '2026-10-01T00:00:00Z']],
                ['id' => 'U0', 'period' => ['from' => '2020-01-01T00:00:00Z', 'to' => '2020-02-01T00:00:00Z']],
            ],
            '/cloud/project/' . self::PROJECT . '/usage/history/U1' => [
                'hourlyUsage'  => [
                    'instance' => [[
                        'region' => 'GRA11', 'reference' => 'b3-8', 'totalPrice' => 70.0,
                        'details' => [
                            ['instanceId' => 'vm-fictive-1', 'totalPrice' => 50.25],
                            ['instanceId' => 'vm-fictive-2', 'totalPrice' => 19.75],
                        ],
                    ]],
                    'storage'  => [['region' => 'SBG5', 'type' => 'pcs', 'totalPrice' => 3.5]],
                    // cloud.billingView.HourlyQuantum : un objet de listes, prix en order.Price.
                    'quantum'  => [
                        'notebook'       => [[
                            'region' => 'GRA', 'reference' => 'qpu-notebook',
                            'quantity' => ['unit' => 'Hour', 'value' => 4],
                            'totalPrice' => ['currencyCode' => 'EUR', 'value' => 6.0, 'text' => '6.00 EUR'],
                            'details' => [[
                                'id' => 'nb-detail-1', 'resourceId' => 'notebook-fictive-1',
                                'quantity' => ['unit' => 'Hour', 'value' => 4],
                                'totalPrice' => ['currencyCode' => 'EUR', 'value' => 6.0, 'text' => '6.00 EUR'],
                            ]],
                        ]],
                        'processingUnit' => [],
                    ],
                ],
                'monthlyUsage' => ['instance' => []],
                // cloud.billingView.TypedResources : le prix est dans resources[].components[].
                'resourcesUsage' => [[
                    'type' => 'ai-training', 'totalPrice' => 12.4,
                    'resources' => [[
                        'region' => 'GRA', 'deploymentMode' => 'REGION-1-AZ',
                        'components' => [
                            [
                                'id' => 'component-gpu', 'name' => 'ai1-1-gpu', 'resourceId' => 'job-fictive-1',
                                'quantity' => ['unit' => 'Minute', 'value' => 120], 'totalPrice' => 10.4,
                            ],
                            [
                                'id' => 'component-cpu', 'name' => 'ai1-1-cpu', 'resourceId' => 'job-fictive-1',
                                'quantity' => ['unit' => 'Minute', 'value' => 120], 'totalPrice' => 2.0,
                            ],
                        ],
                    ]],
                ]],
            ],
        ];
    }

    public function get(string $url, array $headers): array
    {
        $this->calls[] = $url;
        $path = (string) parse_url(substr($url, strlen($this->base)), PHP_URL_PATH);
        if (!str_starts_with($url, $this->base)) {
            return [404, '{"message":"unknown region"}'];
        }
        if ($path === '/auth/time') {
            return [200, (string) self::SERVER_TIME];
        }
        if (isset($this->failures[$path])) {
            return [$this->failures[$path], '{"message":"<b>fake failure</b>"}'];
        }
        $time = (int) ($headers['X-Ovh-Timestamp'] ?? 0);
        $expected = OvhClient::sign(self::APP_SECRET, self::CONSUMER, 'GET', $url, '', $time);
        if (
            ($headers['X-Ovh-Application'] ?? '') !== self::APP_KEY
            || ($headers['X-Ovh-Consumer'] ?? '') !== self::CONSUMER
            || ($headers['X-Ovh-Signature'] ?? '') !== $expected
            || abs($time - self::SERVER_TIME) > 30
        ) {
            return [403, '{"message":"Invalid signature"}'];
        }
        if (!array_key_exists($path, $this->routes)) {
            return [404, '{"message":"not found"}'];
        }

        return [200, (string) json_encode($this->routes[$path])];
    }
}
