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

/**
 * Client en lecture seule de l'API OVHcloud v1 : requêtes GET signées (application key,
 * application secret, consumer key), horloge recalée sur celle d'OVHcloud (/auth/time), nombre
 * d'appels borné par lecture.
 */
final class OvhClient
{
    /** Régions de l'API : Europe et Canada. */
    public const ENDPOINTS = [
        'eu' => 'https://eu.api.ovh.com/1.0',
        'ca' => 'https://ca.api.ovh.com/1.0',
    ];

    public const MAX_CALLS = 2000;

    private ?int $delta = null;
    private int $calls = 0;

    public function __construct(
        private readonly OvhTransport $transport,
        private readonly string $region,
        private readonly string $applicationKey,
        private readonly string $applicationSecret,
        private readonly string $consumerKey,
    ) {
        if (!isset(self::ENDPOINTS[$region]) || $applicationKey === '' || $applicationSecret === '' || $consumerKey === '') {
            throw new OvhException(OvhException::R_KEYS);
        }
    }

    /**
     * Signature OVHcloud : "$1$" suivi du SHA1 de secret+consumer+méthode+URL+corps+horodatage.
     */
    public static function sign(string $secret, string $consumer, string $method, string $url, string $body, int $time): string
    {
        return '$1$' . sha1(implode('+', [$secret, $consumer, $method, $url, $body, (string) $time]));
    }

    /**
     * GET signé ; renvoie le JSON décodé.
     *
     * @param array<string, string> $query
     */
    public function get(string $path, array $query = []): mixed
    {
        $url = self::ENDPOINTS[$this->region] . $path . ($query === [] ? '' : '?' . http_build_query($query));
        $time = time() + $this->delta();
        [$status, $body] = $this->send($url, [
            'Accept'            => 'application/json',
            'X-Ovh-Application' => $this->applicationKey,
            'X-Ovh-Consumer'    => $this->consumerKey,
            'X-Ovh-Timestamp'   => (string) $time,
            'X-Ovh-Signature'   => self::sign($this->applicationSecret, $this->consumerKey, 'GET', $url, '', $time),
        ]);
        if ($status >= 400 || $status < 200) {
            throw new OvhException(match (true) {
                $status === 401, $status === 403 => OvhException::R_AUTH,
                $status === 404                  => OvhException::R_NOT_FOUND,
                $status === 429                  => OvhException::R_RATE,
                $status >= 500                   => OvhException::R_SERVER,
                default                          => OvhException::R_RESPONSE,
            }, 'HTTP ' . $status . ' ' . $path);
        }

        return self::decode($body, $path);
    }

    /** Écart entre l'horloge d'OVHcloud et la nôtre, lu une fois (appel sans authentification). */
    private function delta(): int
    {
        if ($this->delta === null) {
            [$status, $body] = $this->send(self::ENDPOINTS[$this->region] . '/auth/time', ['Accept' => 'application/json']);
            $server = $status === 200 ? self::decode($body, '/auth/time') : null;
            if (!is_int($server)) {
                throw new OvhException($status >= 500 ? OvhException::R_SERVER : OvhException::R_RESPONSE, '/auth/time');
            }
            $this->delta = $server - time();
        }

        return $this->delta;
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: string}
     */
    private function send(string $url, array $headers): array
    {
        if (++$this->calls > self::MAX_CALLS) {
            throw new OvhException(OvhException::R_TOO_MUCH);
        }

        return $this->transport->get($url, $headers);
    }

    private static function decode(string $body, string $path): mixed
    {
        try {
            return json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new OvhException(OvhException::R_RESPONSE, $path);
        }
    }
}
