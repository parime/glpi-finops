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

/**
 * Réglages du plugin, enregistrés dans la configuration de GLPI (contexte plugin:finops) et
 * modifiés seulement depuis la page de configuration du plugin (front/config.php).
 */
final class Config
{
    private const CONTEXT = 'plugin:finops';

    public const MIN_UPLOAD_MB = 1;
    public const MAX_UPLOAD_MB = 50;

    /** @var array<string, string> */
    public const DEFAULTS = [
        'default_currency' => 'EUR',
        'max_upload_mb'    => '5',
        // Composant « Version du plugin » (charte section 9 bis) : désactivable pour une instance
        // sans accès à Internet.
        'version_check'    => '1',
        // Étiquettes obligatoires des ressources et étiquette du propriétaire (issue #19).
        'mandatory_tags'   => 'owner, cost-center, env',
        'owner_tag'        => 'owner',
    ];

    /**
     * @return array<string, string>
     */
    public static function load(): array
    {
        $values = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));

        return array_intersect_key($values, self::DEFAULTS) + self::DEFAULTS;
    }

    /**
     * Valeurs validées : devise ISO 4217 (trois lettres), taille maximale bornée, vérification des
     * nouvelles versions activée ou non, étiquettes obligatoires et étiquette du propriétaire
     * nettoyées (TagPolicy::parseList ; vide : aucune).
     *
     * @param array<string, mixed> $input
     * @return array<string, string> Valeurs enregistrées.
     */
    public static function save(array $input): array
    {
        $currency = strtoupper(trim((string) ($input['default_currency'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $currency = self::DEFAULTS['default_currency'];
        }
        $size = max(self::MIN_UPLOAD_MB, min(self::MAX_UPLOAD_MB, (int) ($input['max_upload_mb'] ?? 0)));
        $values = [
            'default_currency' => $currency,
            'max_upload_mb'    => (string) $size,
            'version_check'    => empty($input['version_check']) ? '0' : '1',
            'mandatory_tags'   => implode(', ', TagPolicy::parseList((string) ($input['mandatory_tags'] ?? ''))),
            'owner_tag'        => TagPolicy::parseList((string) ($input['owner_tag'] ?? ''))[0] ?? '',
        ];
        \Config::setConfigurationValues(self::CONTEXT, $values);

        return $values;
    }

    public static function defaultCurrency(): string
    {
        return self::load()['default_currency'];
    }

    public static function maxUploadBytes(): int
    {
        $size = max(self::MIN_UPLOAD_MB, min(self::MAX_UPLOAD_MB, (int) self::load()['max_upload_mb']));

        return $size * 1024 * 1024;
    }

    public static function uninstall(): void
    {
        \Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
    }
}
