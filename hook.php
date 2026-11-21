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

use GlpiPlugin\Finops\Install\Installer;

function plugin_finops_install(): bool
{
    return (new Installer())->install(new Migration(PLUGIN_FINOPS_VERSION));
}

function plugin_finops_uninstall(): bool
{
    return (new Installer())->uninstall(new Migration(PLUGIN_FINOPS_VERSION));
}
