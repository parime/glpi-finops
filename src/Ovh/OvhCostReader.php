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

namespace GlpiPlugin\Finops\Ovh;

use GlpiPlugin\Finops\Import\CsvCostParser;

/**
 * Lit les coûts d'un compte OVHcloud et les ramène aux lignes communes de l'import CSV :
 * - factures (/me/bill et leurs détails) ;
 * - consommation par ressource des projets Public Cloud listés
 *   (/cloud/project/{id}/usage/history, périodes closes seulement : leurs montants ne bougent plus).
 * Les lignes de facture d'un projet listé sont écartées : elles feraient double emploi avec le
 * détail par ressource. Tout est lu avant d'écrire : la moindre erreur lève OvhException.
 */
final class OvhCostReader
{
    public const PROVIDER = 'ovhcloud';

    /** Champs qui identifient une ressource dans la consommation, par ordre de préférence. */
    private const RESOURCE_FIELDS = ['instanceId', 'volumeId', 'snapshotId', 'id', 'name'];

    /** @var list<array<string, string>> */
    private array $rows = [];
    private int $rejected = 0;
    private string $account = '';
    private string $currency = 'EUR';

    public function __construct(private readonly OvhClient $client)
    {
    }

    /**
     * @param string       $since    Premier jour lu (AAAA-MM-JJ).
     * @param list<string> $projects Identifiants des projets Public Cloud.
     * @return array{rows: list<array<string, string>>, rejected: int}
     */
    public function read(string $since, array $projects): array
    {
        $this->rows = [];
        $this->rejected = 0;
        $me = self::object($this->client->get('/me'), '/me');
        $this->account = CsvCostParser::clean((string) ($me['nichandle'] ?? ''));
        $currency = strtoupper((string) ($me['currency']['code'] ?? 'EUR'));
        $this->currency = preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'EUR';

        foreach (self::strings($this->client->get('/me/bill', ['date.from' => $since]), '/me/bill') as $billId) {
            $this->readBill($billId, $projects);
        }
        foreach ($projects as $project) {
            $this->readProject($project, $since);
        }

        return ['rows' => $this->rows, 'rejected' => $this->rejected];
    }

    /**
     * @param list<string> $projects
     */
    private function readBill(string $billId, array $projects): void
    {
        $path = '/me/bill/' . rawurlencode($billId);
        $bill = self::object($this->client->get($path), $path);
        $billDate = substr((string) ($bill['date'] ?? ''), 0, 10);
        foreach (self::strings($this->client->get($path . '/details'), $path) as $detailId) {
            $detailPath = $path . '/details/' . rawurlencode($detailId);
            $detail = self::object($this->client->get($detailPath), $detailPath);
            $domain = (string) ($detail['domain'] ?? '');
            if (in_array($domain, $projects, true)) {
                continue;
            }
            $date = substr((string) ($detail['periodStart'] ?? ''), 0, 10) ?: $billDate;
            $price = $detail['totalPrice'] ?? null;
            $this->add(
                $date,
                (string) ($detail['description'] ?? ''),
                $domain,
                is_array($price) ? ($price['value'] ?? null) : null,
                is_array($price) ? (string) ($price['currencyCode'] ?? '') : '',
                ['bill' => $billId],
                'bill|' . $billId . '|' . $detailId
            );
        }
    }

    private function readProject(string $project, string $since): void
    {
        $path = '/cloud/project/' . rawurlencode($project) . '/usage/history';
        $periods = $this->client->get($path, ['from' => $since]);
        if (!is_array($periods) || !array_is_list($periods)) {
            throw new OvhException(OvhException::R_RESPONSE, $path);
        }
        foreach ($periods as $period) {
            $id = is_array($period) ? (string) ($period['id'] ?? '') : '';
            $from = is_array($period) ? substr((string) ($period['period']['from'] ?? ''), 0, 10) : '';
            if ($id === '' || $from === '' || $from < $since) {
                continue;
            }
            $usagePath = $path . '/' . rawurlencode($id);
            $usage = self::object($this->client->get($usagePath), $usagePath);
            $base = ['project' => $project, 'date' => $from, 'key' => 'usage|' . $project . '|' . $id];
            foreach (['hourlyUsage', 'monthlyUsage'] as $kind) {
                foreach (is_array($usage[$kind] ?? null) ? $usage[$kind] : [] as $category => $entries) {
                    foreach (is_array($entries) && array_is_list($entries) ? $entries : [] as $i => $entry) {
                        if (is_array($entry)) {
                            $label = trim($category . ' ' . (string) ($entry['reference'] ?? $entry['type'] ?? ''));
                            $this->walk($entry, $label, $base, $kind . '|' . $category . '|' . $i);
                        }
                    }
                }
            }
            foreach (is_array($usage['resourcesUsage'] ?? null) ? $usage['resourcesUsage'] : [] as $i => $entry) {
                if (is_array($entry)) {
                    $this->walk($entry, (string) ($entry['type'] ?? ''), $base, 'resources|' . $i);
                }
            }
        }
    }

    /**
     * Une entrée qui a un détail (details, resources) est parcourue ; sinon c'est une ligne de coût.
     *
     * @param array<mixed>                                     $node
     * @param array{project: string, date: string, key: string} $base
     */
    private function walk(array $node, string $label, array $base, string $key, string $region = ''): void
    {
        $region = (string) ($node['region'] ?? $region);
        foreach (['details', 'resources'] as $children) {
            if (is_array($node[$children] ?? null) && $node[$children] !== []) {
                foreach ($node[$children] as $i => $child) {
                    if (is_array($child)) {
                        $this->walk($child, $label, $base, $key . '|' . $children . '|' . $i, $region);
                    }
                }
                return;
            }
        }
        $resource = '';
        foreach (self::RESOURCE_FIELDS as $field) {
            if (is_string($node[$field] ?? null) && $node[$field] !== '') {
                $resource = $node[$field];
                break;
            }
        }
        $price = $node['totalPrice'] ?? null;
        $tags = ['project' => $base['project']] + ($region === '' ? [] : ['region' => $region]);
        $this->add(
            $base['date'],
            'Public Cloud ' . $label,
            $resource,
            is_array($price) ? ($price['value'] ?? null) : $price,
            '',
            $tags,
            $base['key'] . '|' . $key
        );
    }

    /**
     * @param array<string, string> $tags
     */
    private function add(
        string $date,
        string $service,
        string $resource,
        mixed $amount,
        string $currency,
        array $tags,
        string $key
    ): void {
        $cost = is_int($amount) || is_float($amount) || (is_string($amount) && is_numeric($amount))
            ? CsvCostParser::parseAmount(sprintf('%.6F', (float) $amount))
            : null;
        if ($cost === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $this->rejected++;
            return;
        }
        if ((float) $cost === 0.0) {
            return;
        }
        $currency = strtoupper($currency);
        $this->rows[] = [
            'usage_date'  => $date,
            'usage_month' => substr($date, 0, 7),
            'provider'    => self::PROVIDER,
            'account'     => $this->account,
            'service'     => CsvCostParser::clean($service),
            'resource'    => CsvCostParser::clean($resource),
            'cost'        => $cost,
            'currency'    => preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : $this->currency,
            'tags'        => CsvCostParser::parseTags((string) json_encode($tags)),
            'source_key'  => hash('sha256', self::PROVIDER . '|' . $key),
        ];
    }

    /**
     * @return array<mixed>
     */
    private static function object(mixed $value, string $path): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new OvhException(OvhException::R_RESPONSE, $path);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new OvhException(OvhException::R_RESPONSE, $path);
        }

        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
    }
}
