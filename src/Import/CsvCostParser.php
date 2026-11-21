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

namespace GlpiPlugin\Finops\Import;

/**
 * Lecture sûre d'un fichier de coûts au format CSV commun (source « csv ») : date, fournisseur,
 * compte ou abonnement, service, ressource, coût, devise, étiquettes.
 *
 * Défenses : taille du fichier bornée avant toute lecture, fichier binaire (octet nul) ou contenant
 * du code PHP refusé, encodage converti en UTF-8, nombre de lignes et de colonnes borné, cellules
 * sans caractère de contrôle et tronquées, nombres et dates validés. Une ligne invalide est rejetée
 * avec son numéro et sa raison ; le reste du fichier est lu. Rien n'est écrit ici.
 */
final class CsvCostParser
{
    public const MAX_LINES = 200000;
    public const MAX_COLUMNS = 64;
    public const MAX_FIELD = 255;
    public const MAX_TAGS = 50;
    public const MAX_TAG_LENGTH = 100;
    public const MAX_ERRORS_KEPT = 100;
    public const MAX_AMOUNT = 1000000000000;

    public const ALLOWED_EXTENSIONS = ['csv', 'txt'];

    /** Colonnes reconnues et leurs synonymes (en-tête normalisé : minuscules, « _ » pour les séparateurs). */
    public const COLUMNS = [
        'date'     => ['date', 'usage_date', 'day', 'billing_date', 'usage_start_date'],
        'provider' => ['provider', 'cloud', 'vendor', 'supplier'],
        'account'  => [
            'account', 'account_id', 'account_name', 'subscription', 'subscription_id', 'project', 'project_id',
        ],
        'service'  => ['service', 'service_name', 'product', 'product_name', 'category', 'meter_category'],
        'resource' => ['resource', 'resource_id', 'resource_name', 'instance', 'instance_id'],
        'cost'     => ['cost', 'amount', 'total', 'cost_amount', 'unblended_cost'],
        'currency' => ['currency', 'currency_code', 'billing_currency'],
        'tags'     => ['tags', 'labels'],
    ];
    public const REQUIRED = ['date', 'provider', 'cost'];

    // Erreurs de fichier (le fichier entier est refusé).
    public const E_UNREADABLE = 'unreadable';
    public const E_TOO_LARGE = 'too_large';
    public const E_EMPTY = 'empty';
    public const E_BINARY = 'binary';
    public const E_CODE = 'code';
    public const E_COLUMNS = 'missing_columns';
    public const E_TOO_MANY_COLUMNS = 'too_many_columns';
    public const E_TOO_MANY_LINES = 'too_many_lines';
    // Erreurs de ligne (la ligne seule est rejetée).
    public const E_DATE = 'date';
    public const E_PROVIDER = 'provider';
    public const E_COST = 'cost';
    public const E_CURRENCY = 'currency';
    public const E_SHAPE = 'shape';

    public function __construct(
        private readonly string $defaultCurrency = 'EUR',
        private readonly int $maxBytes = 5242880
    )
    {
    }

    public static function isAllowedName(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * @return array{rows: list<array<string, string>>, errors: list<array{line: int, code: string}>,
     *     rejected: int, fatal: ?string, missing: list<string>}
     */
    public function parseFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::fatal(self::E_UNREADABLE);
        }
        $size = filesize($path);
        if ($size === false || $size > $this->maxBytes) {
            return self::fatal(self::E_TOO_LARGE);
        }
        $content = file_get_contents($path, false, null, 0, $this->maxBytes + 1);

        return $content === false ? self::fatal(self::E_UNREADABLE) : $this->parseString($content);
    }

    /**
     * @return array{rows: list<array<string, string>>, errors: list<array{line: int, code: string}>,
     *     rejected: int, fatal: ?string, missing: list<string>}
     */
    public function parseString(string $content): array
    {
        if (strlen($content) > $this->maxBytes) {
            return self::fatal(self::E_TOO_LARGE);
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (trim($content) === '') {
            return self::fatal(self::E_EMPTY);
        }
        if (str_contains($content, "\0")) {
            return self::fatal(self::E_BINARY);
        }
        if (preg_match('/<\?(php|=)/i', $content) === 1) {
            return self::fatal(self::E_CODE);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n");
        $delimiter = self::guessDelimiter($firstLine === false ? '' : $firstLine);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return self::fatal(self::E_UNREADABLE);
        }
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '');
        if (!is_array($header) || count($header) > self::MAX_COLUMNS) {
            fclose($stream);
            return self::fatal(is_array($header) ? self::E_TOO_MANY_COLUMNS : self::E_EMPTY);
        }
        $map = self::mapHeader($header);
        $missing = array_values(array_diff(self::REQUIRED, array_keys($map)));
        if ($missing !== []) {
            fclose($stream);
            return self::fatal(self::E_COLUMNS, $missing);
        }

        $rows = $errors = [];
        $rejected = 0;
        $occurrences = [];
        $line = 1;
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($line > self::MAX_LINES + 1) {
                fclose($stream);
                return self::fatal(self::E_TOO_MANY_LINES);
            }
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            $result = $this->parseRow($cells, $map, count($header));
            if (is_string($result)) {
                $rejected++;
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['line' => $line, 'code' => $result];
                }
                continue;
            }
            // Clé de la source : la même ligne réimportée garde sa clé (import idempotent) ; deux
            // lignes identiques d'un même fichier restent deux lignes (numéro d'occurrence).
            $base = hash('sha256', implode("\x1F", $result));
            $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;
            $result['source_key'] = hash('sha256', $base . '#' . $occurrences[$base]);
            $result['line'] = (string) $line;
            $rows[] = $result;
        }
        fclose($stream);

        return ['rows' => $rows, 'errors' => $errors, 'rejected' => $rejected, 'fatal' => null, 'missing' => []];
    }

    /**
     * @param list<string|null> $cells
     * @param array<string, int> $map
     * @return array<string, string>|string Ligne au format commun, ou code d'erreur.
     */
    private function parseRow(array $cells, array $map, int $width): array|string
    {
        if (count($cells) > max($width, self::MAX_COLUMNS)) {
            return self::E_SHAPE;
        }
        $get = static fn (string $column): string => isset($map[$column])
            ? self::clean((string) ($cells[$map[$column]] ?? ''))
            : '';

        $date = self::parseDate($get('date'));
        if ($date === null) {
            return self::E_DATE;
        }
        $provider = strtolower($get('provider'));
        if (preg_match('/^[a-z0-9][a-z0-9 ._-]{0,63}$/', $provider) !== 1) {
            return self::E_PROVIDER;
        }
        $cost = self::parseAmount($get('cost'));
        if ($cost === null) {
            return self::E_COST;
        }
        $currency = strtoupper($get('currency'));
        if ($currency === '') {
            $currency = $this->defaultCurrency;
        } elseif (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return self::E_CURRENCY;
        }

        return [
            'usage_date'  => $date,
            'usage_month' => substr($date, 0, 7),
            'provider'    => $provider,
            'account'     => $get('account'),
            'service'     => $get('service'),
            'resource'    => $get('resource'),
            'cost'        => $cost,
            'currency'    => $currency,
            'tags'        => self::parseTags(isset($map['tags']) ? (string) ($cells[$map['tags']] ?? '') : ''),
        ];
    }

    /**
     * @param list<string|null> $header
     * @return array<string, int> Colonne commune => position.
     */
    private static function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $position => $name) {
            $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $name))), '_');
            foreach (self::COLUMNS as $column => $aliases) {
                if (!isset($map[$column]) && in_array($normalized, $aliases, true)) {
                    $map[$column] = $position;
                }
            }
        }

        return $map;
    }

    private static function guessDelimiter(string $line): string
    {
        $best = ',';
        $count = 0;
        foreach ([',', ';', "\t", '|'] as $candidate) {
            $n = substr_count($line, $candidate);
            if ($n > $count) {
                [$best, $count] = [$candidate, $n];
            }
        }

        return $best;
    }

    /** Cellule nettoyée : sans caractère de contrôle, sans espace autour, tronquée. */
    public static function clean(string $value, int $max = self::MAX_FIELD): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

        return mb_substr(trim($value), 0, $max);
    }

    /** Date AAAA-MM-JJ (heure ignorée) ou JJ/MM/AAAA, entre 2000 et 2100. */
    public static function parseDate(string $value): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ][0-9:.]+(?:Z|[+-]\d{2}:?\d{2})?)?$/', $value, $m) === 1) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m) === 1) {
            [$y, $mo, $d] = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } else {
            return null;
        }
        if ($y < 2000 || $y > 2100 || !checkdate($mo, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /**
     * Montant en texte décimal (6 décimales au plus), accepte « 1234.5 », « 1 234,50 », « 1,234.50 ».
     */
    public static function parseAmount(string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}", "'"], '', $value);
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && $dot !== false) {
            $value = $comma > $dot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif ($comma !== false) {
            $value = str_replace(',', '.', $value);
        }
        if (preg_match('/^-?\d{1,13}(\.\d{1,12})?$/', $value) !== 1 || abs((float) $value) > self::MAX_AMOUNT) {
            return null;
        }

        return number_format((float) $value, 6, '.', '');
    }

    /**
     * Étiquettes « clé=valeur;clé2=valeur2 » (aussi « : » et « | ») ou objet JSON, en JSON trié.
     */
    public static function parseTags(string $value): string
    {
        $value = trim($value);
        $tags = [];
        if (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true, 2);
            foreach (is_array($decoded) ? $decoded : [] as $key => $tag) {
                if (is_scalar($tag)) {
                    $tags[(string) $key] = (string) $tag;
                }
            }
        } elseif ($value !== '') {
            foreach (preg_split('/[;|]/', $value) ?: [] as $pair) {
                $parts = preg_split('/[=:]/', $pair, 2) ?: [];
                if (trim($parts[0] ?? '') !== '') {
                    $tags[$parts[0]] = $parts[1] ?? '';
                }
            }
        }
        $clean = [];
        foreach ($tags as $key => $tag) {
            $key = self::clean((string) $key, self::MAX_TAG_LENGTH);
            if ($key !== '' && count($clean) < self::MAX_TAGS) {
                $clean[$key] = self::clean($tag, self::MAX_TAG_LENGTH);
            }
        }
        ksort($clean, SORT_STRING);

        return $clean === [] ? '' : (string) json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param list<string> $missing
     * @return array{rows: list<array<string, string>>, errors: list<array{line: int, code: string}>,
     *     rejected: int, fatal: ?string, missing: list<string>}
     */
    private static function fatal(string $code, array $missing = []): array
    {
        return ['rows' => [], 'errors' => [], 'rejected' => 0, 'fatal' => $code, 'missing' => $missing];
    }
}
