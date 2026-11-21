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
use GlpiPlugin\Finops\Menu;

// Page d'accueil du plugin, seule entrée de menu (Gestion > FinOps) : droit de lecture du plugin.
Session::checkRight(Menu::RIGHTNAME, READ);

Html::header(Menu::getTypeName(), '', 'management', Menu::class, Menu::TAB_OVERVIEW);
TemplateRenderer::getInstance()->display('@finops/overview.html.twig', [
    'tabs'       => Menu::tabs(Menu::TAB_OVERVIEW),
    'config_url' => Session::haveRight('config', UPDATE) ? Menu::url('config') : '',
]);
Html::footer();
