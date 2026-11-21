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
 * API OpenAPI de Sangfor SCP en lecture seule (issue #11) : nœuds, machines virtuelles et leurs
 * allocations (vCPU, mémoire, disque), lus sur clic. Sangfor ne facture pas à l'usage : le coût
 * vient d'une grille de prix réglable par plateforme. aSV (un nœud seul, sans SCP) n'est pas lu.
 */
final class SangforScpSource implements CostSource
{
    public function key(): string
    {
        return 'sangfor-scp';
    }

    public function label(): string
    {
        return __('Sangfor HCI (SCP)', 'finops');
    }

    public function description(): string
    {
        return __(
            'Virtual machines, nodes and allocations read on request from the Sangfor Cloud Platform '
            . 'OpenAPI, with a monthly cost from your own price grid.',
            'finops'
        );
    }

    public function icon(): string
    {
        return 'ti ti-server-2';
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
