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
use GlpiPlugin\Finops\AssetMatcher;
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\Resource;
use GlpiPlugin\Finops\TagCompliance;

// Ressources vues dans les coûts (Gestion > FinOps > Ressources) et rattachement manuel à un actif.
// Lecture : droit READ du plugin ; rattacher : droit UPDATE et lecture de l'actif (Resource::link).
Session::checkRight(Menu::RIGHTNAME, READ);

$pageUrl = Menu::url(Menu::TAB_RESOURCES);

if (isset($_POST['link']) || isset($_POST['unlink'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $resource = new Resource();
    // Droit UPDATE du plugin et entité de la ressource.
    $resource->check($id, UPDATE);
    $itemtype = isset($_POST['unlink']) ? '' : (string) ($_POST['itemtype'] ?? '');
    $itemsId = isset($_POST['unlink']) ? 0 : (int) ($_POST['items_id'] ?? 0);
    if (Resource::link($id, $itemtype, $itemsId)) {
        Session::addMessageAfterRedirect(htmlescape(
            $itemtype === '' ? __('Link removed.', 'finops') : __('Resource linked to the asset.', 'finops')
        ), false, INFO);
    } else {
        Session::addMessageAfterRedirect(htmlescape(__('Choose an asset you can see.', 'finops')), false, ERROR);
    }
    Html::redirect($pageUrl . '?' . http_build_query(['id' => $id]));
}

// Rattachement assisté (issue #6) : propositions cochées, validées par l'utilisateur. Droit UPDATE ;
// chaque proposition est recalculée et rattachée par Resource::link() (entité, lecture de l'actif).
if (isset($_POST['validate'])) {
    Session::checkRight(Menu::RIGHTNAME, UPDATE);
    $proposals = is_array($_POST['proposals'] ?? null) ? $_POST['proposals'] : [];
    $result = AssetMatcher::validate(array_slice($proposals, 0, Resource::PAGE_SIZE, true));
    if ($result['linked'] > 0) {
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            _n('%s resource linked to the proposed asset.', '%s resources linked to the proposed assets.', $result['linked'], 'finops'),
            $result['linked']
        )), false, INFO);
    }
    if ($result['skipped'] > 0 || $result['linked'] === 0) {
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('%s proposals not applied: the resource is already linked, or the proposal changed.', 'finops'),
            $result['skipped']
        )), false, WARNING);
    }
    $back = (string) ($_POST['back'] ?? '');
    Html::redirect($pageUrl . (preg_match('/^[a-z0-9=&%_.+-]*$/i', $back) === 1 && $back !== '' ? '?' . $back : ''));
}

$selected = null;
if (isset($_GET['id'])) {
    $resource = new Resource();
    // Ressource d'une autre entité : refusée.
    $resource->check((int) $_GET['id'], READ);
    $selected = Resource::presentOne($resource->fields);
}

$unlinked = !empty($_GET['unlinked']);
$search = mb_substr((string) ($_GET['search'] ?? ''), 0, 100);
$start = max(0, (int) ($_GET['start'] ?? 0));
// Étiquetage obligatoire (issue #19) : ressources à corriger seulement.
$toFix = !empty($_GET['tofix']);
$page = Resource::page($unlinked, $search, $start, $toFix);

Html::header(Menu::getTypeName(), '', 'management', Menu::class, Menu::TAB_RESOURCES);
TemplateRenderer::getInstance()->display('@finops/resources.html.twig', [
    'tabs'        => Menu::tabs(Menu::TAB_RESOURCES),
    'page_url'    => $pageUrl,
    'rows'        => $page['rows'],
    'total'       => $page['total'],
    'start'       => $start,
    'page_size'   => Resource::PAGE_SIZE,
    'unlinked'    => $unlinked,
    'tofix'       => $toFix,
    'compliance'  => TagCompliance::summary(),
    'search'      => $search,
    'selected'    => $selected,
    'asset_types' => Resource::ASSET_TYPES,
    'can_link'    => Resource::canUpdate(),
    'back'        => http_build_query(array_filter(['search' => $search, 'unlinked' => $unlinked ? 1 : 0,
        'tofix' => $toFix ? 1 : 0, 'start' => $start])),
    'match_rules' => AssetMatcher::rules(),
    'import_url'  => Session::haveRight(Menu::RIGHTNAME, CREATE) ? Menu::url(Menu::TAB_IMPORT) : '',
]);
Html::footer();
