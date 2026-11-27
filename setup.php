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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\CostLine;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\Profile;
use GlpiPlugin\Finops\Resource;

define('PLUGIN_FINOPS_VERSION', '0.3.0');
define('PLUGIN_FINOPS_MIN_GLPI', '11.0.0');
// GLPI 11 et 12 depuis un seul code source (voir src/Compat/).
define('PLUGIN_FINOPS_MAX_GLPI', '12.99.99');
define('PLUGIN_FINOPS_MIN_PHP', '8.2.0');

function plugin_init_finops(): void
{
    global $PLUGIN_HOOKS;

    // Clé littérale : GLPI 12 a supprimé la constante Hooks::CSRF_COMPLIANT (la clé reste la même).
    $PLUGIN_HOOKS['csrf_compliant']['finops'] = true;

    // Version majeure de GLPI non vérifiée : rien n'est déclaré (les classes pourraient ne pas
    // se charger). Comparaison de version et non isPluginActive() : cette fonction sert aussi
    // pendant l'installation, avant l'activation.
    if (version_compare(GLPI_VERSION, PLUGIN_FINOPS_MAX_GLPI, '>')) {
        return;
    }

    // Onglet « FinOps » des profils (droits du plugin) et des actifs rattachés à une ressource.
    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);
    Plugin::registerClass(Resource::class, ['addtabon' => Resource::ASSET_TYPES]);
    Plugin::registerClass(CostLine::class);
    Plugin::registerClass(CostImport::class);

    // Charte commune, section 9 : une seule entrée de menu d'usage (Gestion > FinOps, écrans en
    // onglets) et tous les réglages dans la page de configuration du plugin (Configuration > Plugins).
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['finops'] = ['management' => [Menu::class]];
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['finops'] = 'front/config.php';
}

/**
 * @return array<string, mixed>
 */
function plugin_version_finops(): array
{
    return [
        'name'         => 'FinOps',
        'version'      => PLUGIN_FINOPS_VERSION,
        'author'       => 'Vincent GUILLOTTE',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/parime/glpi-finops',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_FINOPS_MIN_GLPI,
                'max' => PLUGIN_FINOPS_MAX_GLPI,
            ],
            'php'  => [
                'min' => PLUGIN_FINOPS_MIN_PHP,
            ],
        ],
    ];
}

function plugin_finops_check_prerequisites(): bool
{
    if (version_compare(PHP_VERSION, PLUGIN_FINOPS_MIN_PHP, '<')) {
        echo sprintf('PHP %s or later is required.', PLUGIN_FINOPS_MIN_PHP);
        return false;
    }

    return true;
}

function plugin_finops_check_config(bool $verbose = false): bool
{
    return true;
}
