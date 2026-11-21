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
use GlpiPlugin\Finops\Menu;
use GlpiPlugin\Finops\Resource;

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
$page = Resource::page($unlinked, $search, $start);

Html::header(Menu::getTypeName(), '', 'management', Menu::class, Menu::TAB_RESOURCES);
TemplateRenderer::getInstance()->display('@finops/resources.html.twig', [
    'tabs'        => Menu::tabs(Menu::TAB_RESOURCES),
    'page_url'    => $pageUrl,
    'rows'        => $page['rows'],
    'total'       => $page['total'],
    'start'       => $start,
    'page_size'   => Resource::PAGE_SIZE,
    'unlinked'    => $unlinked,
    'search'      => $search,
    'selected'    => $selected,
    'asset_types' => Resource::ASSET_TYPES,
    'can_link'    => Resource::canUpdate(),
    'import_url'  => Session::haveRight(Menu::RIGHTNAME, CREATE) ? Menu::url(Menu::TAB_IMPORT) : '',
]);
Html::footer();
