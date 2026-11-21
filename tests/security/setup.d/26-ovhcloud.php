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
 * Issue #26 : un compte OVHcloud par entité, aux clés fictives (jamais lues : aucune requête vers
 * OVHcloud pendant les tests). Fragment de tests/security/setup.php ($DB, $ids, $a, $b).
 */

use GlpiPlugin\Finops\Install\OvhTables;

foreach (['A' => $a, 'B' => $b] as $suffix => $entity) {
    $name = 'finops-ovh-securite-' . strtolower($suffix);
    $row = $DB->request(['FROM' => OvhTables::ACCOUNTS, 'WHERE' => ['name' => $name], 'LIMIT' => 1])->current();
    if ($row === null) {
        $key = new GLPIKey();
        $DB->insert(OvhTables::ACCOUNTS, [
            'entities_id'        => $entity,
            'name'               => $name,
            'region'             => 'eu',
            'application_key'    => $key->encrypt('securiteAppKey' . $suffix),
            'application_secret' => $key->encrypt('securiteAppSecret' . $suffix),
            'consumer_key'       => $key->encrypt('securiteConsumer' . $suffix),
            'projects'           => '',
            'months'             => 3,
        ]);
        $ids['ovh_' . $suffix] = (int) $DB->insertId();
    } else {
        $ids['ovh_' . $suffix] = (int) $row['id'];
    }
}
