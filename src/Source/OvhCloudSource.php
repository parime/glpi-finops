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
 * API OVHcloud en lecture seule (issue #26) : factures et consommation des projets Public Cloud,
 * lues sur clic et ramenées aux lignes communes. Comptes et clés : onglet « Sources de coûts » de
 * la configuration ; lecture : page d'import.
 */
final class OvhCloudSource implements CostSource
{
    public function key(): string
    {
        return 'ovhcloud';
    }

    public function label(): string
    {
        return __('OVHcloud', 'finops');
    }

    public function description(): string
    {
        return __(
            'Bills and Public Cloud consumption per resource, read on request from the OVHcloud API '
            . '(Europe or Canada).',
            'finops'
        );
    }

    public function icon(): string
    {
        return 'ti ti-cloud';
    }

    public function kind(): string
    {
        return self::KIND_API;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
