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
use User;
use UserEmail;

/**
 * Conformité de l'étiquetage des ressources (issue #19), calculée à la lecture sur les ressources
 * des entités de la session : étiquettes obligatoires manquantes, propriétaire rapproché d'un
 * utilisateur de GLPI par son identifiant de connexion ou son adresse de courriel. Rien n'est
 * modifié : le plugin signale, une personne corrige les étiquettes chez le fournisseur.
 *
 * Une ressource est conforme quand toutes les étiquettes obligatoires sont renseignées ; le
 * propriétaire absent ou inconnu (valeur sans utilisateur correspondant) est compté à part.
 */
final class TagCompliance
{
    public const OWNER_OK = 'ok';
    public const OWNER_UNKNOWN = 'unknown';
    public const OWNER_MISSING = 'missing';
    /** Aucune étiquette de propriétaire réglée : pas de rapprochement. */
    public const OWNER_OFF = 'off';

    private const CHUNK = 500;

    /**
     * @return array{required: list<string>, owner_tag: string}
     */
    public static function settings(): array
    {
        $config = Config::load();

        return [
            'required'  => TagPolicy::parseList($config['mandatory_tags']),
            'owner_tag' => TagPolicy::parseList($config['owner_tag'])[0] ?? '',
        ];
    }

    /**
     * Verdict d'une ressource. $owners : valeurs connues (en minuscules) vers l'utilisateur.
     *
     * @param array<string, string>                            $tags
     * @param array{required: list<string>, owner_tag: string} $settings
     * @param array<string, int>                               $owners
     * @return array{missing: list<string>, owner_status: string, owner_value: string, owner_id: int}
     */
    public static function evaluate(array $tags, array $settings, array $owners): array
    {
        $value = $settings['owner_tag'] === '' ? '' : TagPolicy::value($tags, $settings['owner_tag']);
        $id = $value === '' ? 0 : ($owners[mb_strtolower($value)] ?? 0);
        $status = match (true) {
            $settings['owner_tag'] === '' => self::OWNER_OFF,
            $value === ''                 => self::OWNER_MISSING,
            $id > 0                       => self::OWNER_OK,
            default                       => self::OWNER_UNKNOWN,
        };

        return [
            'missing'      => TagPolicy::missing($tags, $settings['required']),
            'owner_status' => $status,
            'owner_value'  => $value,
            'owner_id'     => $id,
        ];
    }

    /**
     * Utilisateurs de GLPI (non supprimés) dont l'identifiant de connexion ou une adresse de
     * courriel vaut l'une des valeurs, casse ignorée.
     *
     * @param list<string> $values
     * @return array<string, int> valeur en minuscules => identifiant de l'utilisateur
     */
    public static function owners(array $values): array
    {
        global $DB;

        $values = array_values(array_unique(array_filter($values, static fn (string $v): bool => $v !== '')));
        $users = User::getTable();
        $emails = UserEmail::getTable();
        $found = [];
        foreach (array_chunk($values, self::CHUNK) as $chunk) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => $users,
                'WHERE'  => ['name' => $chunk, 'is_deleted' => 0],
            ]);
            foreach ($iterator as $user) {
                $found[mb_strtolower((string) $user['name'])] = (int) $user['id'];
            }
            $iterator = $DB->request([
                'SELECT'     => ["$emails.users_id", "$emails.email"],
                'FROM'       => $emails,
                'INNER JOIN' => [$users => ['ON' => [$emails => 'users_id', $users => 'id']]],
                'WHERE'      => ["$emails.email" => $chunk, "$users.is_deleted" => 0],
            ]);
            foreach ($iterator as $email) {
                $found[mb_strtolower((string) $email['email'])] ??= (int) $email['users_id'];
            }
        }

        return $found;
    }

    /**
     * Ressources choisies par $where (restriction aux entités de la session comprise), avec leurs
     * étiquettes.
     *
     * @param array<mixed> $where
     * @return list<array{id: int, provider: string, account: string, tags: array<string, string>}>
     */
    private static function scan(array $where): array
    {
        global $DB;

        $table = Resource::getTable();
        $rows = [];
        $iterator = $DB->request([
            'SELECT' => ['id', 'provider', 'account', 'tags'],
            'FROM'   => $table,
            'WHERE'  => $where,
            'ORDER'  => ['last_seen DESC', 'id DESC'],
        ]);
        foreach ($iterator as $row) {
            $rows[] = [
                'id'       => (int) $row['id'],
                'provider' => (string) $row['provider'],
                'account'  => (string) $row['account'],
                'tags'     => TagPolicy::decode($row['tags'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>>                       $rows lignes avec une clé tags
     * @param array{required: list<string>, owner_tag: string} $settings
     * @return array<string, int>
     */
    private static function ownersOf(array $rows, array $settings): array
    {
        if ($settings['owner_tag'] === '') {
            return [];
        }
        $values = [];
        foreach ($rows as $row) {
            $values[] = TagPolicy::value(is_array($row['tags'] ?? null) ? $row['tags'] : [], $settings['owner_tag']);
        }

        return self::owners($values);
    }

    /**
     * Taux de conformité global, par fournisseur et par compte.
     *
     * @return array{required: list<string>, owner_tag: string, total: int, compliant: int, rate: ?int,
     *     owner_unknown: int, owner_missing: int, groups: list<array{provider: string, account: string,
     *     total: int, compliant: int, rate: ?int, owner_unknown: int, owner_missing: int}>}
     */
    public static function summary(): array
    {
        $settings = self::settings();
        $rows = self::scan((new DbUtils())->getEntitiesRestrictCriteria(Resource::getTable(), '', '', false));
        $owners = self::ownersOf($rows, $settings);
        $all = ['total' => 0, 'compliant' => 0, 'owner_unknown' => 0, 'owner_missing' => 0];
        $groups = [];
        foreach ($rows as $row) {
            $verdict = self::evaluate($row['tags'], $settings, $owners);
            $key = $row['provider'] . "\x1F" . $row['account'];
            $groups[$key] ??= ['provider' => $row['provider'], 'account' => $row['account'], 'total' => 0,
                'compliant' => 0, 'owner_unknown' => 0, 'owner_missing' => 0];
            $add = [
                'total'         => 1,
                'compliant'     => $verdict['missing'] === [] ? 1 : 0,
                'owner_unknown' => $verdict['owner_status'] === self::OWNER_UNKNOWN ? 1 : 0,
                'owner_missing' => $verdict['owner_status'] === self::OWNER_MISSING ? 1 : 0,
            ];
            foreach ($add as $counter => $increment) {
                $all[$counter] += $increment;
                $groups[$key][$counter] += $increment;
            }
        }
        ksort($groups, SORT_STRING);
        $list = [];
        foreach ($groups as $group) {
            $list[] = $group + ['rate' => self::rate($group['total'], $group['compliant'])];
        }

        return $settings + $all + ['rate' => self::rate($all['total'], $all['compliant']), 'groups' => $list];
    }

    /** Pourcentage arrondi vers le bas (100 seulement quand tout est conforme) ; null sans ressource. */
    public static function rate(int $total, int $compliant): ?int
    {
        return $total === 0 ? null : intdiv(100 * $compliant, $total);
    }

    /**
     * Identifiants des ressources à corriger (étiquette obligatoire manquante, propriétaire absent
     * ou inconnu), des plus récentes aux plus anciennes.
     *
     * @param array<mixed> $where critères de Resource::page(), restriction aux entités comprise
     * @return list<int>
     */
    public static function toFix(array $where): array
    {
        $settings = self::settings();
        $rows = self::scan($where);
        $owners = self::ownersOf($rows, $settings);
        $ids = [];
        foreach ($rows as $row) {
            $verdict = self::evaluate($row['tags'], $settings, $owners);
            if (
                $verdict['missing'] !== []
                || in_array($verdict['owner_status'], [self::OWNER_MISSING, self::OWNER_UNKNOWN], true)
            ) {
                $ids[] = $row['id'];
            }
        }

        return $ids;
    }

    /**
     * Ajoute à chaque ressource présentée ses étiquettes manquantes et son propriétaire : nom de
     * l'utilisateur, lien seulement si l'utilisateur courant peut voir sa fiche.
     *
     * @param list<array<string, mixed>> $rows lignes de Resource::present()
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $rows): array
    {
        $settings = self::settings();
        $owners = self::ownersOf($rows, $settings);
        $users = [];
        foreach ($rows as &$row) {
            $tags = is_array($row['tags'] ?? null) ? $row['tags'] : [];
            $verdict = self::evaluate($tags, $settings, $owners);
            $owner = ['status' => $verdict['owner_status'], 'value' => $verdict['owner_value'], 'name' => '', 'url' => ''];
            $id = $verdict['owner_id'];
            if ($id > 0) {
                if (!isset($users[$id])) {
                    $user = new User();
                    $users[$id] = $user->getFromDB($id)
                        ? ['name' => $user->getFriendlyName(), 'url' => $user->canViewItem() ? $user->getLinkURL() : '']
                        : ['name' => '', 'url' => ''];
                }
                $owner = array_merge($owner, $users[$id]);
            }
            $row['missing_tags'] = $verdict['missing'];
            $row['owner'] = $owner;
        }
        unset($row);

        return $rows;
    }
}
