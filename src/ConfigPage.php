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

/**
 * Page de configuration du plugin (Configuration > Plugins > FinOps, front/config.php) : le seul
 * endroit où le plugin se règle (charte commune, section 9), en onglets. Droit de configuration de
 * GLPI.
 */
final class ConfigPage
{
    public const TAB_GENERAL = 'general';
    public const TAB_SOURCES = 'sources';
    public const TAB_ABOUT = 'about';

    public const TABS = [self::TAB_GENERAL, self::TAB_SOURCES, self::TAB_ABOUT];

    public static function url(string $tab = self::TAB_GENERAL): string
    {
        return Menu::url('config', $tab === self::TAB_GENERAL ? [] : ['tab' => $tab]);
    }

    public static function tab(string $requested): string
    {
        return in_array($requested, self::TABS, true) ? $requested : self::TAB_GENERAL;
    }

    /**
     * @return list<array{label: string, icon: string, url: string, active: bool}>
     */
    public static function tabs(string $active): array
    {
        $labels = [
            self::TAB_GENERAL => [__('General', 'finops'), 'ti ti-settings'],
            self::TAB_SOURCES => [__('Cost sources', 'finops'), 'ti ti-plug-connected'],
            self::TAB_ABOUT   => [__('About', 'finops'), 'ti ti-info-circle'],
        ];
        $tabs = [];
        foreach ($labels as $tab => [$label, $icon]) {
            $tabs[] = ['label' => $label, 'icon' => $icon, 'url' => self::url($tab), 'active' => $tab === $active];
        }

        return $tabs;
    }
}
