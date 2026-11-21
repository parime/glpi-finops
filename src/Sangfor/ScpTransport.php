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

namespace GlpiPlugin\Finops\Sangfor;

/**
 * Envoi d'une requête à l'API OpenAPI de Sangfor SCP. Remplacé dans les tests par un faux SCP.
 */
interface ScpTransport
{
    /**
     * @param 'GET'|'POST'          $method  POST ne sert qu'à l'authentification (/janus/authenticate).
     * @param array<string, string> $headers
     * @return array{0: int, 1: string} Code HTTP et corps de la réponse.
     * @throws ScpException Réseau injoignable ou certificat refusé.
     */
    public function request(string $method, string $url, array $headers, string $body = ''): array;
}
