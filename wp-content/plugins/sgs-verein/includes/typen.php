<?php
// Eigene Inhaltstypen: Termine, Flugzeuge, Personen, Kiosk-Artikel (alle im Menü «SGS»).

defined('ABSPATH') || exit;

add_action('init', 'sgs_register_types');

function sgs_register_types(): void {
    $basis = [
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'sgs',
        'show_in_rest'        => false, // einfache Eingabemaske statt Block-Editor
        'capability_type'     => 'page', // Redaktion (Rolle «Redakteur») darf pflegen
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ];

    register_post_type('sgs_termin', $basis + [
        'labels'   => sgs_labels('Termin', 'Termine', 'Neuer Termin'),
        'supports' => ['title'],
        'menu_icon' => 'dashicons-calendar-alt',
    ]);
    register_post_type('sgs_flugzeug', $basis + [
        'labels'   => sgs_labels('Flugzeug', 'Flugzeugpark', 'Neues Flugzeug'),
        'supports' => ['title', 'thumbnail', 'page-attributes'],
    ]);
    register_post_type('sgs_person', $basis + [
        'labels'   => sgs_labels('Person', 'Personen', 'Neue Person'),
        'supports' => ['title', 'thumbnail', 'page-attributes'],
    ]);
    register_post_type('sgs_kiosk', $basis + [
        'labels'   => sgs_labels('Kiosk-Artikel', 'Kiosk', 'Neuer Artikel'),
        'supports' => ['title', 'thumbnail', 'page-attributes'],
    ]);

    $tax = [
        'public'            => false,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'capabilities'      => ['manage_terms' => 'edit_pages', 'edit_terms' => 'edit_pages', 'delete_terms' => 'edit_pages', 'assign_terms' => 'edit_pages'],
    ];
    register_taxonomy('sgs_flugzeuggruppe', 'sgs_flugzeug', $tax + [
        'labels' => ['name' => 'Gruppen', 'singular_name' => 'Gruppe', 'add_new_item' => 'Neue Gruppe', 'menu_name' => 'Flugzeug-Gruppen'],
        'description' => 'Die Beschreibung erscheint unter dem Gruppentitel im Flugzeugpark.',
    ]);
    register_taxonomy('sgs_kioskkategorie', 'sgs_kiosk', $tax + [
        'labels' => ['name' => 'Kategorien', 'singular_name' => 'Kategorie', 'add_new_item' => 'Neue Kategorie', 'menu_name' => 'Kiosk-Kategorien'],
    ]);
}

function sgs_labels(string $eins, string $viele, string $neu): array {
    return [
        'name' => $viele, 'singular_name' => $eins, 'add_new' => $neu, 'add_new_item' => $neu,
        'edit_item' => "$eins bearbeiten", 'new_item' => $neu, 'view_item' => "$eins ansehen",
        'search_items' => "$viele durchsuchen", 'not_found' => 'Nichts gefunden', 'all_items' => $viele,
        'menu_name' => $viele,
    ];
}

/** Gruppen/Kategorien in der festgelegten Reihenfolge (Feld «Reihenfolge»). */
function sgs_terms_sortiert(string $taxonomy): array {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
    if (is_wp_error($terms)) {
        return [];
    }
    usort($terms, fn($a, $b) => (int) get_term_meta($a->term_id, 'reihenfolge', true) <=> (int) get_term_meta($b->term_id, 'reihenfolge', true));
    return $terms;
}

/** Beiträge eines Typs, sortiert nach «Reihenfolge» (menu_order). */
function sgs_beitraege(string $type, array $args = []): array {
    return get_posts($args + [
        'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
    ]);
}
