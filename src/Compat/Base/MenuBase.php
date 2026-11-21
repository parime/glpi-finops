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

namespace GlpiPlugin\Finops\Compat\Base;

use CommonGLPI;
use GlpiPlugin\Finops\Compat\GlpiVersion;
use GlpiPlugin\Finops\Menu;

/*
 * Parent de \GlpiPlugin\Finops\Menu : redéclare $rightname sans type en GLPI 11, typé en GLPI 12
 * (voir GlpiVersion). La valeur vient de la constante de la classe enfant.
 */
if (GlpiVersion::isAtLeast12()) {
    abstract class MenuBase extends CommonGLPI
    {
        public static string $rightname = Menu::RIGHTNAME;
    }
} else {
    abstract class MenuBase extends CommonGLPI
    {
        public static $rightname = Menu::RIGHTNAME;
    }
}
