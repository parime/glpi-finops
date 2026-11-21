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

declare(strict_types=1);

namespace GlpiPlugin\Finops;

use GlpiPlugin\Finops\Compat\Base\MenuBase;
use Session;

/**
 * Seule entrée de menu d'usage du plugin : Gestion > FinOps (charte commune, section 9). Ses écrans
 * sont des sous-pages reliées par une barre d'onglets commune (templates/_section_nav.html.twig) :
 * coûts par fournisseur et par mois (les ressources et l'import arrivent avec le premier lot). Les réglages sont dans la page de
 * configuration du plugin (ConfigPage).
 *
 * Droits (un seul droit, plugin_finops) : READ consulte, CREATE importe, UPDATE rattache une
 * ressource à un actif, PURGE supprime un import et ses lignes.
 */
class Menu extends MenuBase
{
    public const RIGHTNAME = 'plugin_finops';

    public const TAB_OVERVIEW = 'overview';
    public const TAB_RESOURCES = 'resource';
    public const TAB_IMPORT = 'import';

    public static function getTypeName($nb = 0)
    {
        return __('FinOps', 'finops');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-report-money';
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, READ);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Adresse d'une page du plugin (front/<page>.php), relative à la racine de GLPI ou complète.
     *
     * @param array<string, int|string> $params
     */
    public static function url(string $page, array $params = [], bool $full = true): string
    {
        global $CFG_GLPI;

        $url = ($full ? (string) $CFG_GLPI['root_doc'] : '') . '/plugins/finops/front/' . $page . '.php';

        return $params === [] ? $url : $url . '?' . http_build_query($params);
    }

    /**
     * Entrée Gestion > FinOps, qui ouvre les coûts. Les options donnent le fil d'Ariane des sous-pages.
     *
     * @return array<string, mixed>|false
     */
    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        $options = [];
        foreach (self::screens() as $key => $screen) {
            $options[$key] = ['title' => $screen['label'], 'page' => $screen['url'], 'icon' => $screen['icon']];
        }

        return [
            'title'   => self::getTypeName(2),
            'page'    => self::url(self::TAB_OVERVIEW, [], false),
            'icon'    => self::getIcon(),
            'options' => $options,
        ];
    }

    /**
     * Écrans de l'entrée, dans l'ordre des onglets, selon les droits de la session.
     *
     * @return array<string, array{label: string, icon: string, url: string}>
     */
    private static function screens(): array
    {
        $screens = [
            self::TAB_OVERVIEW => [
                'label' => __('Costs', 'finops'),
                'icon'  => 'ti ti-chart-bar',
                'url'   => self::url(self::TAB_OVERVIEW, [], false),
            ],
        ];

        return $screens;
    }

    /**
     * Onglets des sous-pages, pour templates/_section_nav.html.twig (adresses complètes).
     *
     * @return list<array{label: string, icon: string, url: string, active: bool}>
     */
    public static function tabs(string $active): array
    {
        global $CFG_GLPI;

        $tabs = [];
        foreach (self::screens() as $key => $screen) {
            $tabs[] = [
                'label'  => $screen['label'],
                'icon'   => $screen['icon'],
                'url'    => (string) $CFG_GLPI['root_doc'] . $screen['url'],
                'active' => $key === $active,
            ];
        }

        return $tabs;
    }
}
