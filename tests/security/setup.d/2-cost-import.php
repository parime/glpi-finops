<?php

/**
 * Issue #2 : coûts importés dans les entités A et B, une ressource par entité, chacune rattachée à
 * l'ordinateur de son entité. Fragment de tests/security/setup.php (variables $DB, $ids, $a, $b).
 */

use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Install\CostTables;

foreach (['A' => $a, 'B' => $b] as $suffix => $entity) {
    $lower = strtolower($suffix);
    $filename = 'couts-securite-' . $lower . '.csv';
    $import = $DB->request(['FROM' => CostTables::IMPORTS, 'WHERE' => ['filename' => $filename], 'LIMIT' => 1])->current();
    if ($import === null) {
        $parsed = (new CsvCostParser('EUR', 1048576))->parseString(
            "date,provider,account,service,resource,cost,currency\n"
            . "2026-10-01,finops-securite-$lower,compte-$lower,Compute,securite-vm-$lower,42.5,EUR\n"
            . "2026-11-01,finops-securite-$lower,compte-$lower,Compute,securite-vm-$lower,40,EUR\n"
        );
        $ids['import_' . $suffix] = (new CostImporter())->import($parsed, $entity, $filename)['import'];
    } else {
        $ids['import_' . $suffix] = (int) $import['id'];
    }
    $resource = $DB->request([
        'FROM'  => CostTables::RESOURCES,
        'WHERE' => ['entities_id' => $entity, 'resource' => 'securite-vm-' . $lower],
    ])->current();
    $ids['resource_' . $suffix] = (int) ($resource['id'] ?? 0);
}
foreach (['A', 'B'] as $suffix) {
    $DB->update(
        CostTables::RESOURCES,
        ['itemtype' => 'Computer', 'items_id' => $ids['computer_' . $suffix]],
        ['id' => $ids['resource_' . $suffix]]
    );
}
