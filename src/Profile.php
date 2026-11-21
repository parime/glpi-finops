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

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Onglet « FinOps » des profils : le droit du plugin s'attribue depuis Administration > Profils,
 * avec la matrice du cœur (enregistrée par front/profile.form.php du cœur, droit « profile »).
 */
class Profile extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return Menu::getTypeName($nb);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::canManage($item)
            ? self::createTabEntry(self::getTypeName(), 0, static::class, Menu::getIcon())
            : '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        // ajax/common.tabs.php sert le contenu sans passer par getTabNameForItem().
        if (!self::canManage($item)) {
            return false;
        }
        TemplateRenderer::getInstance()->display('@finops/profile_rights.html.twig', [
            'item'     => $item,
            'title'    => self::getTypeName(),
            'can_edit' => (bool) Session::haveRight('profile', UPDATE),
            'rights'   => [
                [
                    'itemtype' => Menu::class,
                    'label'    => __('Cloud and virtualisation costs', 'finops'),
                    'field'    => Menu::RIGHTNAME,
                    'rights'   => [
                        READ   => __('Read'),
                        CREATE => __('Import costs', 'finops'),
                        UPDATE => __('Link resources to assets', 'finops'),
                        PURGE  => __('Delete an import', 'finops'),
                    ],
                ],
            ],
        ]);

        return true;
    }

    // Droit « profile » du cœur et accès au plugin : on ne délègue pas un module qu'on ne voit pas.
    private static function canManage(CommonGLPI $item): bool
    {
        return $item instanceof \Profile && !$item->isNewItem()
            && \Profile::canView() && (bool) Session::haveRight(Menu::RIGHTNAME, READ);
    }
}
