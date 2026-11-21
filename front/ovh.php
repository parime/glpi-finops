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

use GlpiPlugin\Finops\ConfigPage;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\OvhAccount;

// Comptes OVHcloud (issue #26), formulaires seulement : ajout, modification et suppression depuis
// l'onglet « Sources de coûts » de la configuration (droit de configuration de GLPI et entité du
// compte) ; lecture des coûts depuis la page d'import (droit d'import du plugin et entité du compte).
// Jeton CSRF vérifié par GLPI sur chaque POST (csrf_compliant).
Session::checkLoginUser();

$account = new OvhAccount();
$id = (int) ($_POST['id'] ?? 0);
$configUrl = ConfigPage::url(ConfigPage::TAB_SOURCES);

if (isset($_POST['add'])) {
    Session::checkRight('config', UPDATE);
    $account->check(-1, CREATE, $_POST);
    if ($account->add($_POST) !== false) {
        Session::addMessageAfterRedirect(htmlescape(__('OVHcloud account saved.', 'finops')), false, INFO);
    }
    Html::redirect($configUrl);
} elseif (isset($_POST['update'])) {
    Session::checkRight('config', UPDATE);
    $account->check($id, UPDATE);
    $input = ['id' => $id] + $_POST;
    if (isset($input['entities_id']) && !Session::haveAccessToEntity((int) $input['entities_id'])) {
        unset($input['entities_id']);
    }
    if ($account->update($input)) {
        Session::addMessageAfterRedirect(htmlescape(__('OVHcloud account saved.', 'finops')), false, INFO);
    }
    Html::redirect($configUrl);
} elseif (isset($_POST['purge'])) {
    Session::checkRight('config', UPDATE);
    $account->check($id, PURGE);
    $account->delete(['id' => $id], true);
    $message = __('OVHcloud account deleted; the costs already read are kept.', 'finops');
    Session::addMessageAfterRedirect(htmlescape($message), false, INFO);
    Html::redirect($configUrl);
} elseif (isset($_POST['read'])) {
    Session::checkRight(Menu::RIGHTNAME, CREATE);
    $account->check($id, READ);
    $result = $account->readCosts();
    if ($result['ok']) {
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('OVHcloud read: %1$d new lines, %2$d already known.', 'finops'),
            $result['created'],
            $result['known']
        )), false, INFO);
        Html::redirect(Menu::url(Menu::TAB_OVERVIEW));
    }
    $message = sprintf(
        __('OVHcloud read failed, the costs already read are kept: %s', 'finops'),
        $result['message']
    );
    Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);
}

Html::redirect(Menu::url(Menu::TAB_IMPORT));
