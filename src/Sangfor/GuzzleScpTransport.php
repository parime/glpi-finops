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

namespace GlpiPlugin\Finops\Sangfor;

use GLPIKey;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Transport réel : client HTTP livré avec GLPI, derrière le proxy de la configuration de GLPI, sans
 * suivre de redirection. Le certificat de SCP est vérifié, sauf si l'administrateur a coché
 * explicitement « certificat auto-signé accepté » pour cette plateforme.
 */
final class GuzzleScpTransport implements ScpTransport
{
    /** Erreurs cURL d'une négociation TLS refusée (certificat inconnu, expiré, nom différent...). */
    private const TLS_ERRNO = [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91];

    private Client $client;

    public function __construct(bool $allowSelfSigned = false)
    {
        global $CFG_GLPI;

        $options = [
            'timeout'         => 60,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'allow_redirects' => false,
            'verify'          => !$allowSelfSigned,
        ];
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

    public function request(string $method, string $url, array $headers, string $body = ''): array
    {
        $options = ['headers' => $headers];
        if ($body !== '') {
            $options['body'] = $body;
        }
        try {
            $response = $this->client->request($method, $url, $options);
        } catch (ConnectException $e) {
            $errno = (int) ($e->getHandlerContext()['errno'] ?? 0);
            throw new ScpException(
                in_array($errno, self::TLS_ERRNO, true) ? ScpException::R_TLS : ScpException::R_NETWORK,
                'cURL ' . $errno
            );
        } catch (GuzzleException $e) {
            throw new ScpException(ScpException::R_NETWORK, $e::class);
        }

        return [$response->getStatusCode(), (string) $response->getBody()];
    }
}
