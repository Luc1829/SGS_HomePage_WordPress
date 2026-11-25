<?php
// Eingegangene Anfragen im Admin (Menü «Anfragen»). Nur lesen und löschen, nicht bearbeiten.
// Werden nach der eingestellten Frist automatisch gelöscht (Datenschutz).

defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('sgs_anfrage', [
        'labels' => [
            'name' => 'Anfragen', 'singular_name' => 'Anfrage', 'all_items' => 'Alle Anfragen', 'menu_name' => 'Anfragen',
            'edit_item' => 'Anfrage', 'search_items' => 'Anfragen durchsuchen', 'not_found' => 'Noch keine Anfragen',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 4, 'menu_icon' => 'dashicons-email-alt',
        'supports' => ['title'], 'show_in_rest' => false,
        // Eigene Rechte: nur Administratoren (und wem sie einzeln vergeben werden), nicht die ganze Redaktion.
        'capability_type' => ['sgs_anfrage', 'sgs_anfragen'], 'map_meta_cap' => true,
        'capabilities' => ['create_posts' => 'do_not_allow'],
    ]);
});

add_filter('manage_sgs_anfrage_posts_columns', fn() => [
    'cb' => '<input type="checkbox">', 'title' => 'Anfrage', 'formular' => 'Formular', 'empfaenger' => 'Ging an', 'mail' => 'E-Mail versendet', 'date' => 'Eingang',
]);
add_action('manage_sgs_anfrage_posts_custom_column', function ($col, $id) {
    $name = (string) get_post_meta($id, 'formular', true);
    echo match ($col) {
        'formular'   => esc_html(SGS_FORMULARE[$name] ?? $name),
        'empfaenger' => esc_html((string) get_post_meta($id, 'empfaenger', true)),
        'mail'       => get_post_meta($id, 'mail_ok', true) === '1' ? 'ja' : '<b style="color:#c2352b">nein, bitte weiterleiten</b>',
        default      => '',
    };
}, 10, 2);

// Ansicht einer Anfrage: Inhalt als Text, Antworten per E-Mail.
add_action('add_meta_boxes_sgs_anfrage', function () {
    remove_meta_box('submitdiv', 'sgs_anfrage', 'side');
    add_meta_box('sgs_anfrage_inhalt', 'Angaben', function (WP_Post $p) {
        $email = (string) get_post_meta($p->ID, 'email', true);
        echo '<pre style="white-space:pre-wrap;font:inherit;font-size:14px;line-height:1.6">' . esc_html($p->post_content) . '</pre>';
        if ($email) {
            echo '<p><a class="button button-primary" href="mailto:' . esc_attr($email) . '?subject=' . rawurlencode('Re: ' . $p->post_title) . '">Antworten an ' . esc_html($email) . '</a></p>';
        }
        echo '<p class="description">Eingang ' . esc_html(get_the_date('d.m.Y H:i', $p)) . ' Uhr, ging an ' . esc_html((string) get_post_meta($p->ID, 'empfaenger', true))
            . '. <a href="' . esc_url(get_delete_post_link($p->ID)) . '">In den Papierkorb</a></p>';
    }, 'sgs_anfrage', 'normal', 'high');
});
add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'sgs_anfrage') {
        unset($actions['inline hide-if-no-js']);
        $actions['edit'] = '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">Ansehen</a>';
    }
    return $actions;
}, 10, 2);

/** Rechte für Anfragen. Bei der Aktivierung und einmalig nach dem Update an Administratoren vergeben. */
const SGS_ANFRAGEN_RECHTE = [
    'edit_sgs_anfragen', 'edit_others_sgs_anfragen', 'edit_private_sgs_anfragen', 'read_private_sgs_anfragen',
    'delete_sgs_anfragen', 'delete_others_sgs_anfragen', 'delete_private_sgs_anfragen',
];
function sgs_anfragen_rechte_vergeben(): void {
    if ($admin = get_role('administrator')) {
        foreach (SGS_ANFRAGEN_RECHTE as $cap) {
            $admin->add_cap($cap);
        }
    }
    update_option('sgs_anfragen_rechte', 1);
}
add_action('admin_init', function () {
    if (!get_option('sgs_anfragen_rechte')) {
        sgs_anfragen_rechte_vergeben();
    }
});

// Inhalt einer Anfrage kann im Admin nicht verändert werden (Nachweis, z. B. bei Safety-Meldungen).
add_filter('wp_insert_post_data', function ($data, $postarr) {
    if ($data['post_type'] === 'sgs_anfrage' && !empty($postarr['ID']) && ($alt = get_post((int) $postarr['ID']))) {
        $data['post_title']   = wp_slash($alt->post_title);
        $data['post_content'] = wp_slash($alt->post_content);
        $data['post_status']  = $data['post_status'] === 'trash' ? 'trash' : 'private';
    }
    return $data;
}, 10, 2);

// Hinweis im Dashboard, wenn E-Mails nicht zugestellt werden konnten.
add_action('admin_notices', function () {
    if (!current_user_can('read_private_sgs_anfragen') || get_current_screen()->id !== 'dashboard') {
        return;
    }
    $n = count(get_posts(['post_type' => 'sgs_anfrage', 'post_status' => 'private', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => 'mail_ok', 'meta_value' => '0']));
    if ($n) {
        printf('<div class="notice notice-warning"><p>%d Anfrage(n) konnten nicht per E-Mail zugestellt werden. <a href="%s">Anfragen ansehen</a> und den E-Mail-Versand unter Anfragen › Einstellungen prüfen.</p></div>',
            $n, esc_url(admin_url('edit.php?post_type=sgs_anfrage')));
    }
});

// Alte Anfragen löschen.
add_action('sgs_anfragen_aufraeumen', function () {
    $tage = (int) sgs_formulare_einstellungen()['aufbewahren'];
    if ($tage < 1) {
        return;
    }
    $alt = get_posts([
        'post_type' => 'sgs_anfrage', 'post_status' => 'any', 'numberposts' => 200, 'fields' => 'ids',
        'date_query' => [['before' => "$tage days ago"]],
    ]);
    foreach ($alt as $id) {
        wp_delete_post($id, true);
    }
});
