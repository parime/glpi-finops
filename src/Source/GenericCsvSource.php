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
 * Fichier CSV au format commun, quel que soit le fournisseur : tout export de facturation peut y
 * être ramené (colonnes date, fournisseur, compte, service, ressource, coût, devise, étiquettes).
 */
final class GenericCsvSource implements CostSource
{
    public function key(): string
    {
        return 'csv';
    }

    public function label(): string
    {
        return __('Generic CSV file', 'finops');
    }

    public function description(): string
    {
        return __(
            'Any billing export brought back to the common columns: date, provider, account, service, '
            . 'resource, cost, currency, tags.',
            'finops'
        );
    }

    public function icon(): string
    {
        return 'ti ti-file-spreadsheet';
    }

    public function kind(): string
    {
        return self::KIND_FILE;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
