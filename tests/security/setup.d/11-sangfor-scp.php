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

// Issue #11 : une plateforme Sangfor SCP par entité, avec une VM d'inventaire. Aucune lecture réelle.

use GlpiPlugin\Finops\Install\SangforTables;

foreach (['A' => $a, 'B' => $b] as $suffix => $entity) {
    $name = 'finops-scp-securite-' . strtolower($suffix);
    $row = $DB->request(['FROM' => SangforTables::ACCOUNTS, 'WHERE' => ['name' => $name], 'LIMIT' => 1])->current();
    if ($row === null) {
        $key = new GLPIKey();
        $DB->insert(SangforTables::ACCOUNTS, [
            'entities_id' => $entity,
            'name'        => $name,
            'url'         => 'https://scp-' . strtolower($suffix) . '.example.test',
            'username'    => $key->encrypt('securiteScpUser' . $suffix),
            'password'    => $key->encrypt('securiteScpPassword' . $suffix),
            'currency'    => 'EUR',
            'vm_count'    => 1,
            'last_read'   => date('Y-m-d H:i:s'),
            'last_success' => date('Y-m-d H:i:s'),
            'last_status' => 'ok',
        ]);
        $ids['sangfor_' . $suffix] = (int) $DB->insertId();
        $DB->insert(SangforTables::VMS, [
            'plugin_finops_sangforaccounts_id' => $ids['sangfor_' . $suffix],
            'entities_id'                      => $entity,
            'vm_id'                            => 'vm-securite-' . strtolower($suffix),
            'name'                             => 'vm-scp-securite-' . strtolower($suffix),
            'power'                            => 'on',
        ]);
        $ids['sangforvm_' . $suffix] = (int) $DB->insertId();
    } else {
        $ids['sangfor_' . $suffix] = (int) $row['id'];
        $vm = $DB->request([
            'FROM'  => SangforTables::VMS,
            'WHERE' => ['plugin_finops_sangforaccounts_id' => (int) $row['id']],
            'LIMIT' => 1,
        ])->current();
        $ids['sangforvm_' . $suffix] = (int) ($vm['id'] ?? 0);
    }
}
