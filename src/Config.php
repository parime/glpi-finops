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
     * Valeurs validées : devise ISO 4217 (trois lettres), taille maximale bornée.
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
        $values = ['default_currency' => $currency, 'max_upload_mb' => (string) $size];
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
