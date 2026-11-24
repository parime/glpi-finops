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
 * Issue #19 : étiquetage obligatoire. Étiquettes obligatoires et étiquette du propriétaire réglées
 * sur la charge piégée (page de configuration, badges « manquante »), ressource de l'entité A dont
 * les étiquettes (clé et valeur) sont piégées : propriétaire inconnu affiché avec sa valeur.
 * Fragment de tests/security/setup.php xss ($insert, $canary, $ids, $out).
 */

\Config::setConfigurationValues('plugin:finops', ['mandatory_tags' => $canary, 'owner_tag' => $canary]);
$out['tagged_resource'] = $insert('resources', [
    'entities_id' => $ids['a'], 'provider' => 'finops-xss-tags', 'resource' => 'xss-tags-vm',
    'account' => 'compte-xss', 'service' => 'Compute', 'items_id' => 0,
    'first_seen' => '2026-09-15', 'last_seen' => '2026-09-15',
    'tags' => json_encode([$canary => $canary, 'env' => $canary]),
]);
