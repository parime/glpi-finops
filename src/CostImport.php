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

use DbUtils;
use GlpiPlugin\Finops\Compat\Base\RecordBase;
use GlpiPlugin\Finops\Install\CostTables;
use Session;

/**
 * Journal des imports de coûts : qui, quand, quel fichier, dans quelle entité, combien de lignes
 * créées, déjà connues ou rejetées. Supprimer un import (droit PURGE) supprime ses lignes ; les
 * ressources et leurs rattachements restent.
 */
class CostImport extends RecordBase
{
    public const RIGHTNAME = Menu::RIGHTNAME;

    public static function getTypeName($nb = 0)
    {
        return _n('Cost import', 'Cost imports', $nb, 'finops');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-file-import';
    }

    public static function canCreate(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, CREATE);
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, PURGE);
    }

    public function cleanDBonPurge()
    {
        global $DB;

        $DB->delete(CostTables::LINES, ['plugin_finops_costimports_id' => (int) $this->getID()]);
    }

    /**
     * Derniers imports des entités de la session, pour le journal de la page d'import.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 20): array
    {
        global $DB;

        $rows = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => (new DbUtils())->getEntitiesRestrictCriteria(self::getTable(), '', '', false),
            'ORDER' => 'id DESC',
            'LIMIT' => $limit,
        ]);
        foreach ($iterator as $row) {
            $rows[] = [
                'id'       => (int) $row['id'],
                'date'     => (string) $row['date_creation'],
                'user'     => getUserName((int) $row['users_id']),
                'entity'   => \Dropdown::getDropdownName('glpi_entities', (int) $row['entities_id']),
                'filename' => (string) $row['filename'],
                'created'  => (int) $row['lines_created'],
                'known'    => (int) $row['lines_known'],
                'rejected' => (int) $row['lines_rejected'],
            ];
        }

        return $rows;
    }
}
