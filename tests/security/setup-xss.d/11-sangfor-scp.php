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

// Issue #11 : plateforme Sangfor SCP et VM piégées (nom, adresse, nœuds, motif d'échec).
$out['sangfor'] = $insert('sangforaccounts', [
    'entities_id' => $ids['a'], 'name' => $canary, 'url' => 'https://scp.example.test', 'currency' => 'EUR',
    'username' => '', 'password' => '', 'vm_count' => 1,
    'nodes' => (string) json_encode([['node_id' => $canary, 'name' => $canary, 'ip' => $canary, 'status' => $canary,
        'cores' => 1, 'memory_mb' => 1, 'storage_mb' => 1]]),
    'last_read' => date('Y-m-d H:i:s'), 'last_success' => date('Y-m-d H:i:s'), 'last_status' => 'error',
    'last_message' => $canary,
]);
$insert('sangforvms', [
    'plugin_finops_sangforaccounts_id' => $out['sangfor'], 'entities_id' => $ids['a'], 'vm_id' => $canary,
    'name' => $canary, 'power' => $canary, 'node' => $canary, 'pool' => $canary, 'os' => $canary,
]);
