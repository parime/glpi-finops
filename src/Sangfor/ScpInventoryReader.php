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

namespace GlpiPlugin\Finops\Sangfor;

use GlpiPlugin\Finops\Import\CsvCostParser;

/**
 * Inventaire d'une plateforme Sangfor SCP : nœuds (GET /janus/20180725/hosts) et machines
 * virtuelles avec leurs allocations (GET /janus/20180725/servers), ramenés à un format court.
 *
 * Format tolérant : la documentation publique ne garantit pas tous les champs (« 是否一定返回 : 否 »)
 * et ils varient d'une version à l'autre. Champs lus, du préféré au suivant :
 * - VM : id | uuid | vm_id ; name | vm_name ; power_state (on, off) | status ;
 *   host_name | host_id ; az_name | az_id ; os_name | os_type ;
 *   vCPU : cores | sockets × cores_per_socket ;
 *   mémoire (Mo) : memory_mb | memory_status.total_mb ;
 *   disque (Mo) : somme de disks[].size_mb | storage_mb | storage_status.total_mb.
 * - Nœud : id ; name | ip ; ip ; status ; cpu.core_count | cpu.socket_count × cpu.cores_per_socket ;
 *   memory.total_mb ; storage.total_mb.
 * Une VM sans identifiant est écartée (comptée dans « rejected »).
 */
final class ScpInventoryReader
{
    public const PROVIDER = 'sangfor';
    public const MAX_VMS = 20000;

    public function __construct(private readonly ScpClient $client)
    {
    }

    /**
     * @return array{nodes: list<array<string, int|string>>, vms: list<array<string, int|string>>, rejected: int}
     */
    public function read(): array
    {
        $nodes = array_map(self::node(...), $this->client->all('/janus/20180725/hosts'));
        $vms = [];
        $rejected = 0;
        foreach ($this->client->all('/janus/20180725/servers') as $raw) {
            $vm = self::vm($raw);
            if ($vm === null) {
                $rejected++;
                continue;
            }
            $vms[$vm['vm_id']] = $vm;
            if (count($vms) > self::MAX_VMS) {
                throw new ScpException(ScpException::R_TOO_MUCH);
            }
        }

        return ['nodes' => $nodes, 'vms' => array_values($vms), 'rejected' => $rejected];
    }

    /**
     * @param array<mixed> $raw
     * @return array{vm_id: string, name: string, power: string, node: string, pool: string, os: string,
     *     vcpu: int, memory_mb: int, disk_mb: int}|null
     */
    public static function vm(array $raw): ?array
    {
        $id = self::text($raw, ['id', 'uuid', 'vm_id']);
        if ($id === '') {
            return null;
        }
        $vcpu = self::number($raw, ['cores']);
        if ($vcpu <= 0) {
            $vcpu = self::number($raw, ['sockets']) * max(1, self::number($raw, ['cores_per_socket']));
        }
        $disk = 0.0;
        foreach (is_array($raw['disks'] ?? null) ? $raw['disks'] : [] as $d) {
            $disk += is_array($d) ? self::number($d, ['size_mb']) : 0;
        }
        if ($disk <= 0) {
            $disk = self::number($raw, ['storage_mb', 'storage_status.total_mb']);
        }

        return [
            'vm_id'     => $id,
            'name'      => self::text($raw, ['name', 'vm_name']) ?: $id,
            'power'     => self::power($raw),
            'node'      => self::text($raw, ['host_name', 'host_id']),
            'pool'      => self::text($raw, ['az_name', 'az_id']),
            'os'        => self::text($raw, ['os_name', 'os_type']),
            'vcpu'      => (int) round($vcpu),
            'memory_mb' => (int) round(self::number($raw, ['memory_mb', 'memory_status.total_mb'])),
            'disk_mb'   => (int) round($disk),
        ];
    }

    /**
     * @param array<mixed> $raw
     * @return array{node_id: string, name: string, ip: string, status: string, cores: int,
     *     memory_mb: int, storage_mb: int}
     */
    public static function node(array $raw): array
    {
        $cores = self::number($raw, ['cpu.core_count']);
        if ($cores <= 0) {
            $cores = self::number($raw, ['cpu.socket_count']) * max(1, self::number($raw, ['cpu.cores_per_socket']));
        }

        return [
            'node_id'    => self::text($raw, ['id']),
            'name'       => self::text($raw, ['name', 'ip']),
            'ip'         => self::text($raw, ['ip']),
            'status'     => self::text($raw, ['status']),
            'cores'      => (int) round($cores),
            'memory_mb'  => (int) round(self::number($raw, ['memory.total_mb'])),
            'storage_mb' => (int) round(self::number($raw, ['storage.total_mb'])),
        ];
    }

    /**
     * on, off ou unknown.
     *
     * @param array<mixed> $raw
     */
    private static function power(array $raw): string
    {
        $state = strtolower(self::text($raw, ['power_state', 'status']));

        return match (true) {
            in_array($state, ['on', 'running', 'poweron', 'power_on'], true)         => 'on',
            in_array($state, ['off', 'stopped', 'shutoff', 'poweroff', 'power_off'], true) => 'off',
            default                                                                  => 'unknown',
        };
    }

    /**
     * Coût mensuel d'une VM selon la grille (prix par mois) : vCPU et mémoire comptés seulement si
     * la VM n'est pas éteinte au moment de la lecture, disque toujours (il reste réservé).
     *
     * @param array{vm_id: string, power: string, vcpu: int, memory_mb: int, disk_mb: int} $vm
     * @param array{vcpu: float, ram_gb: float, disk_gb: float}                            $grid
     */
    public static function monthlyCost(array $vm, array $grid): float
    {
        $running = $vm['power'] !== 'off';
        $cost = $vm['disk_mb'] / 1024 * $grid['disk_gb'];
        if ($running) {
            $cost += $vm['vcpu'] * $grid['vcpu'] + $vm['memory_mb'] / 1024 * $grid['ram_gb'];
        }

        return round($cost, 6);
    }

    /**
     * Une ligne de coût par VM pour le mois de la lecture, au format commun de l'import CSV. La clé
     * (plateforme, VM, mois) rend la lecture rejouable : la première lecture du mois fixe le coût.
     *
     * @param list<array{vm_id: string, name: string, power: string, node: string, pool: string, os: string,
     *     vcpu: int, memory_mb: int, disk_mb: int}> $vms
     * @param array{vcpu: float, ram_gb: float, disk_gb: float} $grid
     * @return array{rows: list<array<string, string>>, rejected: int}
     */
    public static function costRows(
        array $vms,
        array $grid,
        string $currency,
        string $account,
        int $accountId,
        string $month
    ): array {
        $rows = [];
        foreach ($vms as $vm) {
            $tags = [
                'vm'        => $vm['name'],
                'node'      => $vm['node'],
                'pool'      => $vm['pool'],
                'power'     => $vm['power'],
                'vcpu'      => (string) $vm['vcpu'],
                'memory_gb' => (string) round($vm['memory_mb'] / 1024, 2),
                'disk_gb'   => (string) round($vm['disk_mb'] / 1024, 2),
            ];
            $tags = array_filter($tags, static fn (string $value): bool => $value !== '');
            $rows[] = [
                'usage_date'  => $month . '-01',
                'usage_month' => $month,
                'provider'    => self::PROVIDER,
                'account'     => CsvCostParser::clean($account),
                'service'     => 'Sangfor HCI virtual machine',
                'resource'    => CsvCostParser::clean($vm['vm_id']),
                'cost'        => number_format(min(self::monthlyCost($vm, $grid), 1.0e12), 6, '.', ''),
                'currency'    => $currency,
                'tags'        => CsvCostParser::parseTags((string) json_encode($tags)),
                'source_key'  => hash('sha256', self::PROVIDER . '|' . $accountId . '|' . $vm['vm_id'] . '|' . $month),
            ];
        }

        return ['rows' => $rows, 'rejected' => 0];
    }

    /**
     * Premier texte non vide parmi les champs (chemins pointés pour les sous-objets).
     *
     * @param array<mixed> $raw
     * @param list<string> $fields
     */
    private static function text(array $raw, array $fields): string
    {
        foreach ($fields as $field) {
            $value = self::at($raw, $field);
            if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
                return CsvCostParser::clean((string) $value);
            }
        }

        return '';
    }

    /**
     * Premier nombre positif parmi les champs.
     *
     * @param array<mixed> $raw
     * @param list<string> $fields
     */
    private static function number(array $raw, array $fields): float
    {
        foreach ($fields as $field) {
            $value = self::at($raw, $field);
            if ((is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) && $value > 0) {
                return min((float) $value, 1.0e12);
            }
        }

        return 0.0;
    }

    /**
     * @param array<mixed> $raw
     */
    private static function at(array $raw, string $path): mixed
    {
        $value = $raw;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
