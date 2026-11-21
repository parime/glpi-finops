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
use GlpiPlugin\Finops\Config;
use GlpiPlugin\Finops\ConfigPage;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\OvhAccount;
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
Html::header(Menu::getTypeName(), '', 'config', 'plugin');
TemplateRenderer::getInstance()->display('@finops/config.html.twig', [
    'tab'       => $tab,
    'tabs'      => ConfigPage::tabs($tab),
    'values'    => Config::load(),
    'min_mb'    => Config::MIN_UPLOAD_MB,
    'max_mb'    => Config::MAX_UPLOAD_MB,
    'sources'   => SourceRegistry::describe(),
    'version'   => PLUGIN_FINOPS_VERSION,
    'usage_url' => Menu::url(Menu::TAB_OVERVIEW),
    // Comptes OVHcloud (issue #26), sans leurs clés.
    'ovh_accounts' => $tab === ConfigPage::TAB_SOURCES ? OvhAccount::listing() : [],
    'ovh_url'      => Menu::url('ovh'),
    'regions'      => OvhAccount::regions(),
    'max_projects' => OvhAccount::MAX_PROJECTS,
    'entity'       => (int) Session::getActiveEntity(),
]);
Html::footer();
