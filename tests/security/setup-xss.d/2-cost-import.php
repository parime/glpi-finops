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

/**
 * Issue #2 : import, ligne de coût et ressource piégés dans l'entité A (champs écrits sans passer
 * par la validation de l'import), ressource rattachée à l'ordinateur de A. Fragment de
 * tests/security/setup.php xss ($insert, $canary, $ids, $out).
 */

$out['import'] = $insert('costimports', [
    'entities_id' => $ids['a'], 'users_id' => 2, 'filename' => $canary, 'date_creation' => date('Y-m-d H:i:s'),
]);
$insert('costlines', [
    'entities_id' => $ids['a'], 'plugin_finops_costimports_id' => $out['import'], 'usage_date' => '2026-09-15',
    'usage_month' => '2026-09', 'provider' => $canary, 'account' => $canary, 'service' => $canary,
    'resource' => $canary, 'cost' => '1.000000', 'currency' => 'EUR', 'tags' => json_encode(['k' => $canary]),
    'source_key' => hash('sha256', 'xss-' . microtime()), 'date_creation' => date('Y-m-d H:i:s'),
]);
$out['resource'] = $insert('resources', [
    'entities_id' => $ids['a'], 'provider' => $canary, 'resource' => $canary, 'account' => $canary,
    'service' => $canary, 'itemtype' => 'Computer', 'items_id' => $ids['computer_A'],
    'first_seen' => '2026-09-15', 'last_seen' => '2026-09-15',
]);
