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
 * Issue #6 : rattachement assisté. Dans chaque entité, un ordinateur au numéro de série connu ; dans
 * A, deux ressources non rattachées : l'une a le numéro de série de l'ordinateur de A (proposition
 * attendue), l'autre celui de l'ordinateur de B (jamais proposé : autre entité).
 * Fragment de tests/security/setup.php (variables $DB, $ids, $a, $b, $add).
 */

use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\CostTables;

foreach (['A' => $a, 'B' => $b] as $suffix => $entity) {
    $computer = new Computer();
    if (!$computer->getFromDBByCrit(['name' => 'PC propose FinOps ' . $suffix])) {
        $add($computer, ['name' => 'PC propose FinOps ' . $suffix, 'entities_id' => $entity,
            'serial' => 'sec-prop-' . strtolower($suffix)]);
    }
    $ids['proposed_computer_' . $suffix] = (int) $computer->getID();
}
$filename = 'couts-securite-propositions.csv';
if (countElementsInTable(CostTables::IMPORTS, ['filename' => $filename]) === 0) {
    $parsed = (new CsvCostParser('EUR', 1048576))->parseString(
        "date,provider,account,service,resource,cost,currency
"
        . "2026-10-01,finops-securite-prop,compte-a,Compute,sec-prop-a,5,EUR
"
        . "2026-10-01,finops-securite-prop,compte-a,Compute,sec-prop-b,5,EUR
"
    );
    (new CostImporter())->import($parsed, $a, $filename);
}
foreach (['a', 'b'] as $key) {
    $resource = $DB->request([
        'FROM'  => CostTables::RESOURCES,
        'WHERE' => ['entities_id' => $a, 'resource' => 'sec-prop-' . $key],
    ])->current();
    $ids['prop_resource_' . $key] = (int) ($resource['id'] ?? 0);
    // Relancé : la ressource repart non rattachée.
    $DB->update(CostTables::RESOURCES, ['itemtype' => null, 'items_id' => 0], ['id' => $ids['prop_resource_' . $key]]);
}
