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

namespace GlpiPlugin\Finops\Source;

/**
 * Découverte des sources : chaque fichier src/Source/*Source.php dont la classe implémente
 * CostSource, dans l'ordre alphabétique des noms de fichier. Ajouter une source = ajouter un
 * fichier, sans toucher à cette classe ni à une liste centrale.
 */
final class SourceRegistry
{
    /**
     * @return list<CostSource>
     */
    public static function all(): array
    {
        $files = glob(__DIR__ . '/*Source.php') ?: [];
        sort($files, SORT_STRING);
        $sources = [];
        foreach ($files as $file) {
            $class = __NAMESPACE__ . '\\' . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, CostSource::class)) {
                $sources[] = new $class();
            }
        }

        return $sources;
    }

    public static function get(string $key): ?CostSource
    {
        foreach (self::all() as $source) {
            if ($source->key() === $key) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Sources pour l'onglet « Sources de coûts » de la configuration.
     *
     * @return list<array{key: string, label: string, description: string, icon: string, kind: string,
     *     available: bool}>
     */
    public static function describe(): array
    {
        $rows = [];
        foreach (self::all() as $source) {
            $rows[] = [
                'key'         => $source->key(),
                'label'       => $source->label(),
                'description' => $source->description(),
                'icon'        => $source->icon(),
                'kind'        => $source->kind(),
                'available'   => $source->isAvailable(),
            ];
        }

        return $rows;
    }
}
