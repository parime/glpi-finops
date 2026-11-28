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
use GlpiPlugin\Finops\AssetMatcher;
use GlpiPlugin\Finops\Config;
use GlpiPlugin\Finops\ConfigPage;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\OvhAccount;
use GlpiPlugin\Finops\PluginVersion;
use GlpiPlugin\Finops\SangforAccount;
use GlpiPlugin\Finops\Source\SourceRegistry;

// Page de configuration du plugin (Configuration > Plugins > FinOps) : le seul endroit où le plugin
// se règle (charte commune, section 9), en onglets. Droit de configuration de GLPI.
Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    // Jeton CSRF vérifié par GLPI (csrf_compliant) ; valeurs validées et bornées par Config::save().
    Config::save($_POST);
    Session::addMessageAfterRedirect(htmlescape(__('Settings saved.', 'finops')), false, INFO);
    Html::redirect(ConfigPage::url());
}

$tab = ConfigPage::tab((string) ($_GET['tab'] ?? ''));
$values = Config::load();
// Encadré « Version du plugin » (charte section 9 bis), onglet Général seulement : pas d'appel à
// GitHub depuis les autres onglets.
$pluginVersion = $tab === ConfigPage::TAB_GENERAL
    ? (new PluginVersion('finops', 'parime/glpi-finops', PLUGIN_FINOPS_VERSION))
        ->status($values['version_check'] === '1')
    : null;
Html::header(Menu::getTypeName(), '', 'config', 'plugin');
TemplateRenderer::getInstance()->display('@finops/config.html.twig', [
    'tab'       => $tab,
    'tabs'      => ConfigPage::tabs($tab),
    'values'    => $values,
    'match_rules' => AssetMatcher::labels(),
    'min_mb'    => Config::MIN_UPLOAD_MB,
    'max_mb'    => Config::MAX_UPLOAD_MB,
    'sources'   => SourceRegistry::describe(),
    'version'   => PLUGIN_FINOPS_VERSION,
    // Encadré « Version du plugin » (issue #38), null hors de l'onglet Général.
    'plugin_version' => $pluginVersion,
    // Comptes OVHcloud (issue #26), sans leurs clés.
    'ovh_accounts' => $tab === ConfigPage::TAB_SOURCES ? OvhAccount::listing() : [],
    'ovh_action'   => Menu::url('ovh'),
    'regions'      => OvhAccount::regions(),
    'max_projects' => OvhAccount::MAX_PROJECTS,
    'entity'       => (int) Session::getActiveEntity(),
    // Plateformes Sangfor SCP (issue #11), sans identifiant ni mot de passe.
    'sangfor_accounts' => $tab === ConfigPage::TAB_SOURCES ? SangforAccount::listing() : [],
    'sangfor_action'   => Menu::url('sangfor'),
    'currency'         => Config::defaultCurrency(),
]);
Html::footer();
