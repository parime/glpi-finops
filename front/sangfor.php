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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Finops\ConfigPage;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\Resource;
use GlpiPlugin\Finops\SangforAccount;

// Plateformes Sangfor SCP (issue #11) :
// - ajout, modification, suppression depuis l'onglet « Sources de coûts » de la configuration
//   (droit de configuration de GLPI et entité de la plateforme) ;
// - lecture sur clic depuis la page d'import (droit d'import du plugin et entité) ;
// - inventaire (GET ?id=) : droit de lecture du plugin et entité ; rattacher une VM à un ordinateur :
//   droit de modifier les ressources et de lire l'ordinateur (Resource::link).
// Jeton CSRF vérifié par GLPI sur chaque POST (csrf_compliant).
Session::checkLoginUser();

$account = new SangforAccount();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
// Saisie sans les clés « add » et « update » : GLPI garderait sinon le formulaire, mot de passe en
// clair compris, dans la session (saveInput).
$input = array_diff_key($_POST, ['add' => true, 'update' => true]);
$configUrl = ConfigPage::url(ConfigPage::TAB_SOURCES);
$saved = __('Sangfor SCP platform saved.', 'finops');

if (isset($_POST['add'])) {
    Session::checkRight('config', UPDATE);
    $account->check(-1, CREATE, $input);
    if ($account->add($input) !== false) {
        Session::addMessageAfterRedirect(htmlescape($saved), false, INFO);
    }
    Html::redirect($configUrl);
} elseif (isset($_POST['update'])) {
    Session::checkRight('config', UPDATE);
    $account->check($id, UPDATE);
    $input = ['id' => $id] + $input;
    if (isset($input['entities_id']) && !Session::haveAccessToEntity((int) $input['entities_id'])) {
        unset($input['entities_id']);
    }
    if ($account->update($input)) {
        Session::addMessageAfterRedirect(htmlescape($saved), false, INFO);
    }
    Html::redirect($configUrl);
} elseif (isset($_POST['purge'])) {
    Session::checkRight('config', UPDATE);
    $account->check($id, PURGE);
    $account->delete(['id' => $id], true);
    $message = __('Sangfor SCP platform deleted; the costs already read are kept.', 'finops');
    Session::addMessageAfterRedirect(htmlescape($message), false, INFO);
    Html::redirect($configUrl);
} elseif (isset($_POST['read'])) {
    Session::checkRight(Menu::RIGHTNAME, CREATE);
    $account->check($id, READ);
    $result = $account->readInventory();
    if ($result['ok']) {
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('Sangfor SCP read: %1$d virtual machines, %2$d new cost lines, %3$d already known.', 'finops'),
            $result['vms'],
            $result['created'],
            $result['known']
        )), false, INFO);
        Html::redirect(Menu::url('sangfor', ['id' => $id]));
    }
    $message = sprintf(
        __('Sangfor SCP read failed, the inventory and the costs already read are kept: %s', 'finops'),
        $result['message']
    );
    Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);
    Html::redirect(Menu::url(Menu::TAB_IMPORT));
} elseif (isset($_POST['link'])) {
    Session::checkRight(Menu::RIGHTNAME, UPDATE);
    $account->check($id, READ);
    if (SangforAccount::linkVm((int) ($_POST['vm'] ?? 0), (int) ($_POST['items_id'] ?? 0))) {
        Session::addMessageAfterRedirect(htmlescape(__('Resource linked to the asset.', 'finops')), false, INFO);
    } else {
        Session::addMessageAfterRedirect(htmlescape(__('Choose an asset you can see.', 'finops')), false, ERROR);
    }
    Html::redirect(Menu::url('sangfor', ['id' => $id]));
}

if ($id <= 0) {
    Html::redirect(Menu::url(Menu::TAB_IMPORT));
}
Session::checkRight(Menu::RIGHTNAME, READ);
// Droit de lecture du plugin et entité de la plateforme.
$account->check($id, READ);
$summary = null;
foreach (SangforAccount::listing() as $row) {
    if ($row['id'] === $id) {
        $summary = $row;
    }
}

Html::header(Menu::getTypeName(), '', 'management', Menu::class, Menu::TAB_IMPORT);
TemplateRenderer::getInstance()->display('@finops/sangfor_inventory.html.twig', [
    'tabs'        => Menu::tabs(Menu::TAB_IMPORT),
    'account'     => $summary,
    'inventory'   => $account->inventory(),
    'page_url'    => Menu::url('sangfor'),
    'import_url'  => Session::haveRight(Menu::RIGHTNAME, CREATE) ? Menu::url(Menu::TAB_IMPORT) : '',
    'resource_url' => Menu::url(Menu::TAB_RESOURCES),
    'can_link'    => Resource::canUpdate(),
    'can_read'    => (bool) Session::haveRight(Menu::RIGHTNAME, CREATE),
]);
Html::footer();
