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
 * Jeu de données des tests de sécurité de bout en bout (tests/security/access-matrix.sh et
 * xss-check.sh), lancé par la CI dans une instance GLPI jetable, et avant la désinstallation par
 * les contrôles communs (seed-command de security.yml).
 *
 * Deux entités A et B ; un gestionnaire (tous les droits du plugin) et un lecteur (lecture seule),
 * limités à l'entité A ; un profil sans droit sur le plugin, qui lit les ordinateurs.
 *
 * Usage (en www-data, depuis la racine GLPI) :
 *   php plugins/finops/tests/security/setup.php               crée les données, JSON des ids
 *   php plugins/finops/tests/security/setup.php xss '<json>'  données piégées, JSON des ids
 *   php plugins/finops/tests/security/setup.php counts        nombre de lignes des tables du plugin
 *
 * Une fonctionnalité ajoute ses données dans tests/security/setup.d/<issue>-<sujet>.php et
 * setup-xss.d/<issue>-<sujet>.php (voir tests/security/README.md), sans allonger ce script.
 */

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();
Session::start();
if (!(new Auth())->login('glpi', 'glpi', false)) {
    fwrite(STDERR, "Connexion glpi/glpi impossible\n");
    exit(1);
}

global $DB;

const SECURITY_TEST_PASSWORD = 'Secfin#2026';

$mode = $argv[1] ?? 'setup';

if ($mode === 'counts') {
    // Nombre de lignes de chaque table du plugin : un lecteur ne doit rien créer ni supprimer.
    $counts = [];
    $tables = $DB->request([
        'SELECT' => ['TABLE_NAME'],
        'FROM'   => 'information_schema.TABLES',
        'WHERE'  => ['TABLE_SCHEMA' => $DB->dbdefault, 'TABLE_NAME' => ['LIKE', 'glpi\_plugin\_finops\_%']],
    ]);
    foreach ($tables as $row) {
        $counts[$row['TABLE_NAME']] = countElementsInTable($row['TABLE_NAME']);
    }
    ksort($counts);
    echo json_encode($counts), "\n";
    exit(0);
}

if ($mode === 'xss') {
    // Charge piégée dans chaque champ texte libre ; xss-check.sh vérifie qu'aucune page ne la
    // restitue telle quelle.
    $ids = json_decode((string) ($argv[2] ?? ''), true);
    if (!is_array($ids)) {
        fwrite(STDERR, "Usage : setup.php xss '<json du mode normal>'\n");
        exit(1);
    }
    $canary = "<svg/onload=alert('xss-canary')>";
    $insert = static function (string $table, array $row) use ($DB): int {
        $DB->insert('glpi_plugin_finops_' . $table, $row);
        return (int) $DB->insertId();
    };
    $update = static function (string $table, array $row, int $id) use ($DB): void {
        $DB->update('glpi_plugin_finops_' . $table, $row, ['id' => $id]);
    };
    $out = [];
    foreach (glob(__DIR__ . '/setup-xss.d/*.php') ?: [] as $fragment) {
        require $fragment;
    }
    echo json_encode($out), "\n";
    exit(0);
}

$entityId = static function (string $name): int {
    $entity = new Entity();
    if ($entity->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
        return (int) $entity->getID();
    }
    return (int) $entity->add(['name' => $name, 'entities_id' => 0]);
};
$profileId = static function (string $name, array $rights): int {
    $profile = new Profile();
    if (!$profile->getFromDBByCrit(['name' => $name])) {
        $profile->add(['name' => $name, 'interface' => 'central']);
    }
    ProfileRight::updateProfileRights((int) $profile->getID(), $rights);
    return (int) $profile->getID();
};
$userId = static function (string $login, int $entity, int $profile) use ($DB): int {
    $user = new User();
    if (!$user->getFromDBbyName($login)) {
        $user->add([
            'name' => $login, 'password' => SECURITY_TEST_PASSWORD, 'password2' => SECURITY_TEST_PASSWORD,
            'entities_id' => $entity, '_profiles_id' => 0,
        ]);
    }
    $id = (int) $user->getID();
    $DB->delete('glpi_profiles_users', ['users_id' => $id]);
    $DB->insert('glpi_profiles_users', [
        'users_id' => $id, 'profiles_id' => $profile, 'entities_id' => $entity,
        'is_recursive' => 0, 'is_dynamic' => 0, 'is_default_profile' => 1,
    ]);
    $DB->update('glpi_users', ['profiles_id' => $profile, 'entities_id' => $entity], ['id' => $id]);
    return $id;
};
$add = static function (CommonDBTM $item, array $input): int {
    $id = (int) $item->add($input);
    if ($id <= 0) {
        fwrite(STDERR, 'Creation impossible : ' . $item::class . ' ' . json_encode($input) . "\n");
        exit(1);
    }
    return $id;
};

$a = $entityId('FinOps securite A');
$b = $entityId('FinOps securite B');
Session::changeActiveEntities(0, true);

$all = READ | CREATE | UPDATE | PURGE;
$ids = ['a' => $a, 'b' => $b];
$ids['manager'] = $userId('finops-gestionnaire', $a, $profileId('FinOps securite - gestionnaire A', [
    'plugin_finops' => $all, 'computer' => READ, 'appliance' => READ, 'networking' => READ,
]));
$ids['reader'] = $userId('finops-lecteur', $a, $profileId('FinOps securite - lecteur A', [
    'plugin_finops' => READ, 'computer' => READ, 'appliance' => READ, 'networking' => READ,
]));
// Sans droit sur le plugin, mais lit les ordinateurs : l'onglet FinOps d'un ordinateur ne lui
// montre rien.
$ids['none'] = $userId('finops-sans-droit', $a, $profileId('FinOps securite - sans droit', [
    'plugin_finops' => 0, 'computer' => READ,
]));

foreach (['A' => $a, 'B' => $b] as $suffix => $entity) {
    $computer = new Computer();
    if (!$computer->getFromDBByCrit(['name' => 'VM FinOps securite ' . $suffix])) {
        $add($computer, ['name' => 'VM FinOps securite ' . $suffix, 'entities_id' => $entity]);
    }
    $ids['computer_' . $suffix] = (int) $computer->getID();
}

// Fragments : une fonctionnalité ajoute son jeu de données dans un NOUVEAU fichier
// tests/security/setup.d/<issue>-<sujet>.php. Le script est relancé plusieurs fois : un fragment
// ne recrée pas ce qui existe déjà.
foreach (glob(__DIR__ . '/setup.d/*.php') ?: [] as $fragment) {
    require $fragment;
}
echo json_encode($ids), "\n";
