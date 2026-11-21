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
 * Issue #26 : compte OVHcloud piégé dans l'entité A (nom, projets, motif d'échec). Fragment de
 * tests/security/setup.php xss ($insert, $canary, $ids, $out).
 */

$out['ovh'] = $insert('ovhaccounts', [
    'entities_id' => $ids['a'], 'name' => $canary, 'region' => 'eu', 'projects' => $canary, 'months' => 3,
    'application_key' => '', 'application_secret' => '', 'consumer_key' => '',
    'last_read' => date('Y-m-d H:i:s'), 'last_status' => 'error', 'last_message' => $canary,
]);
