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

namespace GlpiPlugin\Finops\Ovh;

/**
 * Envoi d'une requête GET à l'API OVHcloud. Remplacé dans les tests par un faux OVH.
 */
interface OvhTransport
{
    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: string} Code HTTP et corps de la réponse.
     * @throws OvhException Réseau injoignable.
     */
    public function get(string $url, array $headers): array;
}
