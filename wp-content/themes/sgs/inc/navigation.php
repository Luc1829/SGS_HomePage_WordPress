<?php
// Hauptnavigation aus dem WordPress-Menü (Design › Menüs, Ort «Hauptnavigation»).
// Ebene 1: Gruppen mit Aufklappmenü (Link «#») oder direkte Links (z. B. Flüüger-Fäscht).
// Ebene 2: Seiten mit Beschreibung (Feld «Beschreibung» im Menü, unter «Ansicht anpassen» einblenden).

defined('ABSPATH') || exit;

function sgs_nav(): array {
    $orte = get_nav_menu_locations();
    $items = empty($orte['haupt']) ? [] : (wp_get_nav_menu_items($orte['haupt']) ?: []);
    _wp_menu_item_classes_by_context($items); // markiert die aktuelle Seite
    $gruppen = [];
    foreach ($items as $item) {
        if (!$item->menu_item_parent) {
            $gruppen[$item->ID] = ['item' => $item, 'items' => []];
        }
    }
    foreach ($items as $item) {
        if ($item->menu_item_parent && isset($gruppen[$item->menu_item_parent])) {
            $gruppen[$item->menu_item_parent]['items'][] = $item;
        }
    }
    return array_values($gruppen);
}

/** Gruppe aktiv, wenn die Rubrik der Seite passt oder eine Unterseite aktuell ist. */
function sgs_nav_aktiv(array $g): bool {
    $schluessel = ['Besuchen' => 'besuchen', 'Für Piloten' => 'piloten', 'Fliegen lernen' => 'lernen', 'Verein' => 'verein'];
    if (($schluessel[$g['item']->title] ?? '') === sgs_rubrik()) {
        return true;
    }
    foreach ($g['items'] as $i) {
        if (in_array('current-menu-item', (array) $i->classes, true)) {
            return true;
        }
    }
    return in_array('current-menu-item', (array) $g['item']->classes, true);
}

/** Titel eines Eintrags; beim Flüüger-Fäscht (Klasse nav-fest) mit Jahr. */
function sgs_nav_titel($item): string {
    return in_array('nav-fest', (array) $item->classes, true) ? $item->title . ' ' . sgs_fest()['year'] : $item->title;
}

function sgs_nav_ausgeben(): void {
    foreach (sgs_nav() as $i => $g) {
        $item = $g['item'];
        $aktiv = sgs_nav_aktiv($g) ? ' is-current' : '';
        if (!$g['items']) {
            $fest = in_array('nav-fest', (array) $item->classes, true);
            printf('<li><a class="nav-top%s%s" href="%s"%s>%s</a></li>', $fest ? ' nav-fest' : '', $aktiv, esc_url($item->url), $fest ? ' data-fest-label' : '', esc_html(sgs_nav_titel($item)));
            continue;
        }
        $id = 'menu-' . sanitize_title($item->title);
        echo '<li class="has-menu"><button class="nav-top' . $aktiv . '" aria-expanded="false" aria-controls="' . esc_attr($id) . '">' . esc_html($item->title) . sgs_icon('chevron-down', 'icon chev') . '</button>';
        echo '<ul class="menu" id="' . esc_attr($id) . '">';
        foreach ($g['items'] as $sub) {
            $current = in_array('current-menu-item', (array) $sub->classes, true) ? ' aria-current="page"' : '';
            printf('<li><a href="%s"%s><span>%s</span><small>%s</small></a></li>', esc_url($sub->url), $current, esc_html($sub->title), esc_html($sub->description));
        }
        echo '</ul></li>';
    }
}

function sgs_fuss_spalten(): void {
    foreach (sgs_nav() as $g) {
        if (!$g['items']) {
            continue;
        }
        echo '<div><h3>' . esc_html($g['item']->title) . '</h3><ul>';
        foreach ($g['items'] as $sub) {
            echo '<li><a href="' . esc_url($sub->url) . '">' . esc_html($sub->title) . '</a></li>';
        }
        echo '</ul></div>';
    }
}
