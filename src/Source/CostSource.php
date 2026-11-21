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

namespace GlpiPlugin\Finops\Source;

/**
 * Source de coûts ou d'inventaire (fichier CSV, API d'un fournisseur cloud, hyperviseur). Une
 * nouvelle source est une classe src/Source/<Nom>Source.php qui implémente cette interface :
 * SourceRegistry la découvre sans liste centrale à modifier (AWS, Azure, GCP, Proxmox, Hyper-V,
 * VMware, puis Sangfor, H3C... chacune dans son fichier).
 *
 * Une source ne fait que lire chez le fournisseur (charte commune, section 5) et produit des lignes
 * au format commun : date, fournisseur, compte, service, ressource, coût, devise, étiquettes.
 */
interface CostSource
{
    public const KIND_FILE = 'file';
    public const KIND_API = 'api';

    /** Identifiant stable, en minuscules (csv, aws-cur, proxmox...). */
    public function key(): string;

    /** Nom affiché, traduit. */
    public function label(): string;

    /** Phrase courte qui dit ce que la source apporte, traduite. */
    public function description(): string;

    /** Icône Tabler (ti ti-*). */
    public function icon(): string;

    /** KIND_FILE (fichier déposé) ou KIND_API (appel en lecture seule). */
    public function kind(): string;

    /** Vrai si la source est utilisable dans cette version (fausse : prévue, pas encore livrée). */
    public function isAvailable(): bool;
}
