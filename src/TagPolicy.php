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

namespace GlpiPlugin\Finops;

use GlpiPlugin\Finops\Import\CsvCostParser;

/**
 * Règles d'étiquetage des ressources (issue #19), sans base de données : liste des étiquettes
 * obligatoires réglée dans la page de configuration, étiquettes manquantes d'une ressource, valeur
 * du propriétaire. Les clés se comparent sans tenir compte de la casse (« Owner » vaut « owner ») ;
 * une étiquette présente mais vide compte comme manquante.
 */
final class TagPolicy
{
    public const MAX_TAGS = 20;

    /**
     * Liste saisie dans la configuration (séparée par des virgules, points-virgules ou retours à la
     * ligne), nettoyée comme les clés importées, sans doublon (casse ignorée), 20 clés au plus.
     *
     * @return list<string>
     */
    public static function parseList(string $value): array
    {
        $keys = [];
        foreach (preg_split('/[,;\r\n]+/', $value) ?: [] as $key) {
            $key = CsvCostParser::clean($key, CsvCostParser::MAX_TAG_LENGTH);
            $lower = mb_strtolower($key);
            if ($key !== '' && !isset($keys[$lower]) && count($keys) < self::MAX_TAGS) {
                $keys[$lower] = $key;
            }
        }

        return array_values($keys);
    }

    /**
     * Étiquettes enregistrées en JSON (CsvCostParser::parseTags) ; vide ou invalide : aucune.
     *
     * @return array<string, string>
     */
    public static function decode(?string $json): array
    {
        $decoded = $json === null || $json === '' ? null : json_decode($json, true, 2);
        $tags = [];
        foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
            if (is_scalar($value)) {
                $tags[(string) $key] = (string) $value;
            }
        }

        return $tags;
    }

    /**
     * Valeur d'une étiquette, clé comparée sans la casse ; '' si absente.
     *
     * @param array<string, string> $tags
     */
    public static function value(array $tags, string $key): string
    {
        $key = mb_strtolower($key);
        foreach ($tags as $name => $value) {
            if (mb_strtolower((string) $name) === $key) {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * Étiquettes obligatoires absentes ou vides, dans l'ordre de la configuration.
     *
     * @param array<string, string> $tags
     * @param list<string>          $required
     * @return list<string>
     */
    public static function missing(array $tags, array $required): array
    {
        return array_values(array_filter(
            $required,
            static fn (string $key): bool => self::value($tags, $key) === ''
        ));
    }
}
