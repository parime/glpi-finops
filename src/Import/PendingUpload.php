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
 * Fichier déposé en attente de confirmation, entre l'aperçu et l'import : copié dans le dossier
 * temporaire de GLPI sous un nom aléatoire (jamais le nom fourni), rattaché à la session qui l'a
 * déposé, supprimé après l'import, l'annulation ou une heure.
 */
final class PendingUpload
{
    private const SESSION_KEY = 'plugin_finops_pending';
    private const TTL = 3600;

    /**
     * @return array{token: string, filename: string, entity: int}|null Fichier conservé, ou null.
     */
    public static function store(string $uploadedPath, string $filename, int $entity): ?array
    {
        self::clear();
        $token = bin2hex(random_bytes(16));
        $target = self::path($token);
        if (!is_uploaded_file($uploadedPath)) {
            return null;
        }
        if (!@copy($uploadedPath, $target)) {
            return null;
        }
        $pending = ['token' => $token, 'filename' => $filename, 'entity' => $entity, 'time' => time()];
        $_SESSION[self::SESSION_KEY] = $pending;

        return ['token' => $token, 'filename' => $filename, 'entity' => $entity];
    }

    /**
     * Fichier en attente de cette session, si le jeton correspond et n'a pas expiré.
     *
     * @return array{path: string, filename: string, entity: int}|null
     */
    public static function get(string $token): ?array
    {
        $pending = $_SESSION[self::SESSION_KEY] ?? null;
        if (
            !is_array($pending) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1
            || !hash_equals((string) $pending['token'], $token)
            || time() - (int) $pending['time'] > self::TTL || !is_file(self::path($token))
        ) {
            return null;
        }

        return [
            'path'     => self::path($token),
            'filename' => (string) $pending['filename'],
            'entity'   => (int) $pending['entity'],
        ];
    }

    public static function clear(): void
    {
        $pending = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($pending) && preg_match('/^[a-f0-9]{32}$/', (string) $pending['token']) === 1) {
            @unlink(self::path((string) $pending['token']));
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function path(string $token): string
    {
        return GLPI_TMP_DIR . '/finops-' . $token . '.csv';
    }
}
