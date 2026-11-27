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

use GlpiPlugin\Finops\Compat\Base\RecordBase;

/**
 * Ligne de coût importée (format commun) : une ligne par jour, fournisseur, compte, service et
 * ressource. Écrite seulement par l'import (Import\CostImporter), jamais modifiée ensuite ; supprimée
 * avec son import.
 */
class CostLine extends RecordBase
{
    public const RIGHTNAME = Menu::RIGHTNAME;

    public static function getTypeName($nb = 0)
    {
        return _n('Cost line', 'Cost lines', $nb, 'finops');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-receipt';
    }

    /**
     * Les liens de GLPI vers cet objet (getLinkURL, historique, recherche) ouvrent la page Coûts (une
     * ligne de coût n'a pas d'écran propre), et non le formulaire générique de GLPI, sans contenu
     * utile pour le plugin (issue #37).
     *
     * @param bool $full
     *
     * @return string
     */
    public static function getFormURL($full = true)
    {
        return Menu::url(Menu::TAB_OVERVIEW, [], (bool) $full);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }
}
