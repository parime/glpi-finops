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
 * Issue #6 : rattachement assisté. Ordinateur de l'entité A au nom piégé, proposé (par son numéro
 * de série) pour une ressource non rattachée de A : nom de l'actif proposé affiché dans la liste,
 * sur la fiche de la ressource et dans le libellé de la case à cocher.
 * Fragment de tests/security/setup.php xss ($insert, $canary, $ids, $out).
 */

$out['proposed_computer'] = (int) (new Computer())->add([
    'name' => $canary, 'entities_id' => $ids['a'], 'serial' => 'xss-prop-serial',
]);
$out['proposal_resource'] = $insert('resources', [
    'entities_id' => $ids['a'], 'provider' => 'finops-xss-prop', 'resource' => 'xss-prop-serial',
    'account' => 'compte-xss', 'service' => 'Compute', 'items_id' => 0,
    'first_seen' => '2026-09-15', 'last_seen' => '2026-09-15',
]);
