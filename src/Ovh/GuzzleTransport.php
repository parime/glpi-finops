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

namespace GlpiPlugin\Finops\Ovh;

use GLPIKey;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Transport réel : client HTTP livré avec GLPI, derrière le proxy de la configuration de GLPI.
 * Seulement des GET, sans suivre de redirection.
 */
final class GuzzleTransport implements OvhTransport
{
    private Client $client;

    public function __construct()
    {
        global $CFG_GLPI;

        $options = ['timeout' => 30, 'connect_timeout' => 5, 'http_errors' => false, 'allow_redirects' => false];
        if (!empty($CFG_GLPI['proxy_name'])) {
            $credentials = '';
            if (!empty($CFG_GLPI['proxy_user'])) {
                $password = (string) (new GLPIKey())->decrypt((string) ($CFG_GLPI['proxy_passwd'] ?? ''));
                $credentials = rawurlencode((string) $CFG_GLPI['proxy_user']) . ':' . rawurlencode($password) . '@';
            }
            $options['proxy'] = sprintf(
                'http://%s%s:%d',
                $credentials,
                (string) $CFG_GLPI['proxy_name'],
                (int) ($CFG_GLPI['proxy_port'] ?? 0)
            );
        }
        $this->client = new Client($options);
    }

    public function get(string $url, array $headers): array
    {
        try {
            $response = $this->client->request('GET', $url, ['headers' => $headers]);
        } catch (GuzzleException $e) {
            throw new OvhException(OvhException::R_NETWORK, $e::class);
        }

        return [$response->getStatusCode(), (string) $response->getBody()];
    }
}
