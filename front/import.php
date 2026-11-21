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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Finops\Config;
use GlpiPlugin\Finops\ConfigPage;
use GlpiPlugin\Finops\CostImport;
use GlpiPlugin\Finops\Import\CostImporter;
use GlpiPlugin\Finops\Import\CsvCostParser;
use GlpiPlugin\Finops\Import\PendingUpload;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\OvhAccount;
use GlpiPlugin\Finops\SangforAccount;

// Import d'un fichier de coûts (Gestion > FinOps > Import) : dépôt, aperçu, confirmation, journal.
// Droit d'import du plugin (CREATE) ; supprimer un import exige PURGE. Jeton CSRF vérifié par GLPI
// sur chaque POST (csrf_compliant).
Session::checkRight(Menu::RIGHTNAME, CREATE);

$pageUrl = Menu::url(Menu::TAB_IMPORT);
$parser = new CsvCostParser(Config::defaultCurrency(), Config::maxUploadBytes());
$preview = null;

if (isset($_POST['upload'])) {
    $file = $_FILES['cost_file'] ?? null;
    $entity = (int) ($_POST['entities_id'] ?? -1);
    $error = '';
    if (!Session::haveAccessToEntity($entity)) {
        $error = __('Choose an entity you have access to.', 'finops');
    } elseif (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = in_array((int) ($file['error'] ?? 0), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? CostImporter::errorLabel(CsvCostParser::E_TOO_LARGE)
            : __('Choose a CSV file.', 'finops');
    } elseif (!CsvCostParser::isAllowedName((string) $file['name'])) {
        $error = __('Only .csv and .txt files are accepted.', 'finops');
    } elseif ((int) $file['size'] > Config::maxUploadBytes()) {
        $error = CostImporter::errorLabel(CsvCostParser::E_TOO_LARGE);
    } else {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $textMime = str_starts_with($mime, 'text/')
            || in_array($mime, ['application/csv', 'application/vnd.ms-excel'], true);
        if (!$textMime) {
            $error = CostImporter::errorLabel(CsvCostParser::E_BINARY);
        } else {
            $parsed = $parser->parseFile((string) $file['tmp_name']);
            if ($parsed['fatal'] !== null) {
                $error = CostImporter::errorLabel($parsed['fatal'], $parsed['missing']);
            } elseif ($parsed['rows'] === []) {
                $error = __('No valid cost line in the file.', 'finops');
            } else {
                $pending = PendingUpload::store((string) $file['tmp_name'], basename((string) $file['name']), $entity);
                if ($pending === null) {
                    $error = __('The file cannot be read.', 'finops');
                } else {
                    $preview = (new CostImporter())->preview($parsed, $entity) + $pending + [
                        'errors'      => array_map(
                            static fn (array $e): array => [
                                'line'    => $e['line'],
                                'message' => CostImporter::errorLabel($e['code']),
                            ],
                            $parsed['errors']
                        ),
                        'entity_name' => Dropdown::getDropdownName('glpi_entities', $entity),
                    ];
                }
            }
        }
    }
    if ($error !== '') {
        Session::addMessageAfterRedirect(htmlescape($error), false, ERROR);
        Html::redirect($pageUrl);
    }
} elseif (isset($_POST['confirm'])) {
    $pending = PendingUpload::get((string) ($_POST['token'] ?? ''));
    if ($pending === null || !Session::haveAccessToEntity($pending['entity'])) {
        $message = __('The preview has expired: upload the file again.', 'finops');
        Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);
        Html::redirect($pageUrl);
    }
    $parsed = $parser->parseFile($pending['path']);
    PendingUpload::clear();
    if ($parsed['fatal'] !== null) {
        $message = CostImporter::errorLabel($parsed['fatal'], $parsed['missing']);
        Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);
        Html::redirect($pageUrl);
    }
    $result = (new CostImporter())->import($parsed, $pending['entity'], $pending['filename']);
    Session::addMessageAfterRedirect(htmlescape(sprintf(
        __('Import done: %1$d new lines, %2$d already known, %3$d rejected.', 'finops'),
        $result['created'],
        $result['known'],
        $result['rejected']
    )), false, INFO);
    Html::redirect(Menu::url(Menu::TAB_OVERVIEW));
} elseif (isset($_POST['cancel'])) {
    PendingUpload::clear();
    Html::redirect($pageUrl);
} elseif (isset($_POST['purge'])) {
    $import = new CostImport();
    $id = (int) ($_POST['id'] ?? 0);
    // Droit PURGE et entité de l'import vérifiés par can().
    $import->check($id, PURGE);
    $import->delete(['id' => $id], true);
    Session::addMessageAfterRedirect(htmlescape(__('Import deleted with its cost lines.', 'finops')), false, INFO);
    Html::redirect($pageUrl);
}

Html::header(Menu::getTypeName(), '', 'management', Menu::class, Menu::TAB_IMPORT);
TemplateRenderer::getInstance()->display('@finops/import.html.twig', [
    'tabs'       => Menu::tabs(Menu::TAB_IMPORT),
    'page_url'   => $pageUrl,
    'preview'    => $preview,
    'entity'     => (int) Session::getActiveEntity(),
    'max_mb'     => (int) (Config::maxUploadBytes() / 1048576),
    'currency'   => Config::defaultCurrency(),
    'columns'    => CsvCostParser::COLUMNS,
    'required'   => CsvCostParser::REQUIRED,
    'imports'    => CostImport::recent(),
    'can_purge'  => CostImport::canPurge(),
    // Lecture des comptes OVHcloud (issue #26).
    'ovh_accounts'  => OvhAccount::listing(),
    'ovh_url'       => Menu::url('ovh'),
    'ovh_config'    => OvhAccount::canCreate() ? ConfigPage::url(ConfigPage::TAB_SOURCES) : '',
    // Lecture des plateformes Sangfor SCP (issue #11).
    'sangfor_accounts' => SangforAccount::listing(),
    'sangfor_url'      => Menu::url('sangfor'),
    'sangfor_config'   => SangforAccount::canCreate() ? ConfigPage::url(ConfigPage::TAB_SOURCES) : '',
]);
Html::footer();
