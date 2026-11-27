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

/**
 * Client en lecture seule de l'API OpenAPI de Sangfor SCP (Sangfor Cloud Platform, 6.3.0 ou plus).
 *
 * Sources publiques (consultées en novembre 2026) :
 * - documentation officielle « OPEN-API v6.10.0, SCP平台开放API接口说明文档 », Sangfor, 2024-05-15 :
 *   https://zhuge-puboss.sangfor.com/qiyu/c712a4ec7eb199fbf6dbf0e78a6cc93f.pdf/Open-API-zh_CN-2024-05-15.pdf
 *   (§ 2.3 en-têtes, § 2.5 enveloppe { code, message, data } et pagination, § 2.6.2 authentification
 *   par jeton, § 4.2.3 clé publique, § 4.3.17 /janus/20180725/hosts, § 4.11.2.2 /janus/20180725/servers) ;
 * - client Python communautaire sangfor-scp (licence MIT), qui suit le même déroulé :
 *   https://github.com/erelbi/sangfor-scp (sangfor_scp/auth/token.py, resources/servers.py).
 *
 * Authentification par jeton : clé publique RSA (module hexadécimal, exposant 65537) lue sur
 * /janus/v2/public-key (ou /janus/public-key sur les versions plus anciennes), mot de passe chiffré
 * en RSA PKCS#1 v1.5 puis en hexadécimal, POST /janus/authenticate, jeton dans data.access.token.id,
 * puis « Authorization: Token <id> » sur chaque GET. Le seul POST est l'authentification ; aucune
 * adresse n'est jamais prise dans une réponse : tout part de l'adresse configurée.
 */
final class ScpClient
{
    public const MAX_CALLS = 500;
    public const PAGE_SIZE = 100;

    private ?string $token = null;
    private int $calls = 0;
    private string $cookie;

    public function __construct(
        private readonly ScpTransport $transport,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
        if (self::normalizeUrl($baseUrl) !== $baseUrl || $username === '' || $password === '') {
            throw new ScpException(ScpException::R_CONFIG);
        }
        // Cookie demandé avant SCP 6.3.70 (valeur libre, un UUID recommandé), ignoré ensuite.
        $this->cookie = 'aCMPAuthToken=' . bin2hex(random_bytes(16));
    }

    /**
     * Adresse de la plateforme : https://hôte ou https://hôte:port, sans chemin, paramètre ni
     * identifiant ; null si elle ne convient pas.
     */
    public static function normalizeUrl(string $value): ?string
    {
        $value = rtrim(trim($value), '/');
        $parts = parse_url($value);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['path'])
            || isset($parts['query']) || isset($parts['fragment'])
            || strlen($value) > 255
        ) {
            return null;
        }
        $host = strtolower($parts['host']);
        $ipv6 = str_starts_with($host, '[')
            && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $name = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/';
        if (!$ipv6 && preg_match($name, $host) !== 1) {
            return null;
        }

        return 'https://' . $host . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }

    /**
     * Adresse construite à partir du nom (ou de l'adresse IP) et du port saisis séparément (issue #48) :
     * le formulaire n'envoie pas d'URL complète, qu'un pare-feu applicatif peut prendre pour une
     * inclusion de fichier distant. Le schéma reste https, imposé par le serveur ; un « https:// »
     * collé par l'utilisateur est toléré. Null si le nom ou le port ne conviennent pas.
     */
    public static function urlFromParts(string $host, string $port): ?string
    {
        $host = rtrim(trim($host), '/');
        if (stripos($host, 'https://') === 0) {
            $host = substr($host, 8);
        }
        $port = trim($port);
        if (
            $host === '' || str_contains($host, '://')
            || ($port !== '' && (preg_match('/^[1-9][0-9]{0,4}$/', $port) !== 1 || (int) $port > 65535))
        ) {
            return null;
        }
        // Une adresse IPv6 sans crochets (deux « : » ou plus) est mise entre crochets.
        if (substr_count($host, ':') > 1 && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }
        // Un port écrit dans le nom (« hôte:4430 ») n'est accepté que sans port séparé.
        if ($port !== '' && preg_match('/:[0-9]+$/', $host) === 1) {
            return null;
        }

        return self::normalizeUrl('https://' . $host . ($port !== '' ? ':' . $port : ''));
    }

    /**
     * Nom (ou IP) et port d'une adresse enregistrée, pour préremplir le formulaire.
     *
     * @return array{host: string, port: string}
     */
    public static function urlParts(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return ['host' => '', 'port' => ''];
        }

        return ['host' => (string) $parts['host'], 'port' => isset($parts['port']) ? (string) $parts['port'] : ''];
    }

    /**
     * Chiffre le mot de passe avec le module RSA de SCP (exposant 65537), comme l'exemple de la
     * documentation : PKCS#1 v1.5, résultat en hexadécimal.
     */
    public static function encryptPassword(string $password, string $modulus): string
    {
        // La documentation montre un module qui peut finir par « \n » écrit en toutes lettres.
        $modulus = str_replace('\n', '', preg_replace('/\s+/', '', $modulus) ?? '');
        if (
            !function_exists('openssl_public_encrypt')
            || preg_match('/^[0-9a-fA-F]{256,1024}$/', $modulus) !== 1
            || strlen($modulus) % 2 !== 0
        ) {
            throw new ScpException(ScpException::R_CRYPTO);
        }
        $n = (string) hex2bin($modulus);
        if ((ord($n[0]) & 0x80) !== 0) {
            $n = "\0" . $n;
        }
        $rsa = self::der(0x30, self::der(0x02, $n) . self::der(0x02, "\x01\x00\x01"));
        $algorithm = self::der(0x30, self::der(0x06, (string) hex2bin('2a864886f70d010101')) . self::der(0x05, ''));
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(self::der(0x30, $algorithm . self::der(0x03, "\0" . $rsa))), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        $encrypted = '';
        if (!@openssl_public_encrypt($password, $encrypted, $pem, OPENSSL_PKCS1_PADDING)) {
            throw new ScpException(ScpException::R_CRYPTO);
        }

        return bin2hex((string) $encrypted);
    }

    /** Élément DER : étiquette, longueur, contenu. */
    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $content;
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr($tag) . chr(0x80 | strlen($bytes)) . $bytes . $content;
    }

    /** Obtient un jeton (valable 24 heures chez SCP, une lecture n'en demande qu'un). */
    public function login(): void
    {
        [$status, $body] = $this->send('GET', '/janus/v2/public-key', ['Accept' => 'application/json']);
        if ($status === 404) {
            [$status, $body] = $this->send('GET', '/janus/public-key', ['Accept' => 'application/json']);
        }
        $key = self::envelope($status, $body, '/janus/public-key');
        $modulus = is_array($key) ? ($key['public_key'] ?? null) : null;
        if (!is_string($modulus)) {
            throw new ScpException(ScpException::R_RESPONSE, '/janus/public-key');
        }
        $payload = json_encode(['auth' => ['passwordCredentials' => [
            'username' => $this->username,
            'password' => self::encryptPassword($this->password, $modulus),
        ]]]);
        [$status, $body] = $this->send('POST', '/janus/authenticate', [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ], (string) $payload);
        if ($status === 400) {
            // SCP répond 400 à un identifiant ou un mot de passe faux.
            throw new ScpException(ScpException::R_AUTH, 'HTTP 400 /janus/authenticate');
        }
        $data = self::envelope($status, $body, '/janus/authenticate');
        $token = is_array($data) ? ($data['access']['token']['id'] ?? null) : null;
        if (!is_string($token) || preg_match('/^[A-Za-z0-9._-]{8,512}$/', $token) !== 1) {
            throw new ScpException(ScpException::R_AUTH, '/janus/authenticate');
        }
        $this->token = $token;
    }

    /**
     * GET authentifié ; renvoie le contenu de data (ou la réponse entière sans enveloppe).
     *
     * @param array<string, string|int> $query
     */
    public function get(string $path, array $query = []): mixed
    {
        if ($this->token === null) {
            $this->login();
        }
        $url = $path . ($query === [] ? '' : '?' . http_build_query($query));
        [$status, $body] = $this->send('GET', $url, [
            'Accept'        => 'application/json',
            'Authorization' => 'Token ' . $this->token,
        ]);

        return self::envelope($status, $body, $path);
    }

    /**
     * Tous les éléments d'une liste paginée (page_num à partir de 0, next_page_num vide à la
     * dernière page). Une liste simple, sans pagination, est acceptée telle quelle.
     *
     * @param array<string, string|int> $query
     * @return list<array<mixed>>
     */
    public function all(string $path, array $query = []): array
    {
        $items = [];
        $page = 0;
        while (true) {
            $data = $this->get($path, $query + ['page_num' => $page, 'page_size' => self::PAGE_SIZE]);
            $list = is_array($data) && isset($data['data']) ? $data['data'] : $data;
            if (!is_array($list) || !array_is_list($list)) {
                throw new ScpException(ScpException::R_RESPONSE, $path);
            }
            foreach ($list as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $next = is_array($data) ? ($data['next_page_num'] ?? '') : '';
            if (!is_numeric($next) || $list === [] || (int) $next <= $page) {
                return $items;
            }
            $page = (int) $next;
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: string}
     */
    private function send(string $method, string $pathAndQuery, array $headers, string $body = ''): array
    {
        if (++$this->calls > self::MAX_CALLS) {
            throw new ScpException(ScpException::R_TOO_MUCH);
        }

        return $this->transport->request(
            $method,
            $this->baseUrl . $pathAndQuery,
            $headers + ['Cookie' => $this->cookie],
            $body
        );
    }

    /**
     * Contrôle le code HTTP et l'enveloppe { code, message, data } ; renvoie data.
     */
    private static function envelope(int $status, string $body, string $path): mixed
    {
        if ($status < 200 || $status >= 300) {
            throw new ScpException(match (true) {
                $status === 401, $status === 403 => ScpException::R_AUTH,
                $status === 404                  => ScpException::R_NOT_FOUND,
                $status === 429                  => ScpException::R_RATE,
                $status >= 500                   => ScpException::R_SERVER,
                default                          => ScpException::R_RESPONSE,
            }, 'HTTP ' . $status . ' ' . $path);
        }
        try {
            $json = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ScpException(ScpException::R_RESPONSE, $path);
        }
        if (!is_array($json) || array_is_list($json) || !array_key_exists('data', $json)) {
            return $json;
        }
        $code = $json['code'] ?? ($json['errcode'] ?? 0);
        if (!in_array($code, [0, '0', '', null], true)) {
            throw new ScpException(ScpException::R_RESPONSE, 'code ' . (is_scalar($code) ? (string) $code : '?'));
        }

        return $json['data'];
    }
}
