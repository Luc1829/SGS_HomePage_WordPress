<?php
// Eingabefelder für die eigenen Inhaltstypen, ohne Zusatz-Plugin (kein ACF nötig).

defined('ABSPATH') || exit;

/** Felder je Inhaltstyp: Schlüssel => [Beschriftung, Typ, Hilfetext]. */
function sgs_felder(): array {
    return [
        'sgs_termin' => [
            'datum' => ['Datum', 'date', 'Vergangene Termine verschwinden automatisch von der Webseite.'],
            'ort'   => ['Ort', 'text', 'z. B. «Flugplatz Schmerlat»'],
            'fuer'  => ['Für wen', 'text', 'z. B. «alle, Eintritt frei». Erscheint als «für …».'],
        ],
        'sgs_flugzeug' => [
            'immatrikulation' => ['Immatrikulation', 'text', 'z. B. HB-3038'],
            'kennzeichen'     => ['Wettbewerbskennzeichen', 'text', 'leer lassen, wenn keines'],
            'beschreibung'    => ['Kurzbeschreibung', 'textarea', ''],
            'kennzahlen'      => ['Kennzahlen', 'textarea', 'Eine pro Zeile, «Name: Wert», z. B. «Spannweite: 17 m»'],
            'link'            => ['Link (optional)', 'text', 'Pfad einer Seite, z. B. /lernen/dimona/'],
        ],
        'sgs_person' => [
            'funktion'      => ['Funktion', 'text', 'z. B. «Präsident». Erscheint bei Kontaktangaben.'],
            'email'         => ['E-Mail', 'email', 'Wird auch als Empfänger von Formularen verwendet.'],
            'telefon'       => ['Telefon', 'textarea', 'Eine Nummer pro Zeile, «Bezeichnung | Nummer», z. B. «Natel | 076 400 65 30»'],
            'vorstand'      => ['Im Vorstand als', 'text', 'Leer = nicht auf der Seite «Vorstand». Reihenfolge: Feld «Reihenfolge» rechts.'],
            'kontaktseite'  => ['Auf «Kontakt & Anfahrt» als', 'text', 'Leer = nicht auf der Kontaktseite.'],
            'kontakt_reihe' => ['Reihenfolge auf «Kontakt & Anfahrt»', 'number', ''],
        ],
        'sgs_kiosk' => [
            'preis'  => ['Preis', 'text', 'ohne «CHF», z. B. «5.–» oder «ab 2.50»'],
            'zusatz' => ['Zusatz', 'text', 'z. B. «5 dl» oder «nach Vorrat»'],
        ],
    ];
}

add_action('add_meta_boxes', function () {
    foreach (sgs_felder() as $type => $felder) {
        add_meta_box('sgs_felder', 'Angaben', 'sgs_felder_box', $type, 'normal', 'high');
    }
});

function sgs_felder_box(WP_Post $post): void {
    wp_nonce_field('sgs_felder', 'sgs_felder_nonce');
    if ($post->post_type === 'sgs_person') {
        echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="sgs_kuerzel">Kürzel</label></th><td>'
            . '<input class="regular-text" id="sgs_kuerzel" name="sgs_kuerzel" value="' . esc_attr($post->post_name) . '" pattern="[a-z0-9\\-]+">'
            . '<p class="description">Verbindet die Person mit Seiten und Formularen, z. B. <code>praesident</code>. Bei einem Wechsel im Amt das Kürzel lassen und nur Name, E-Mail und Foto ändern.</p></td></tr></table>';
    }
    echo '<table class="form-table" role="presentation">';
    foreach (sgs_felder()[$post->post_type] as $key => [$label, $typ, $hilfe]) {
        $wert = (string) get_post_meta($post->ID, $key, true);
        $id   = 'sgs_' . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        if ($typ === 'textarea') {
            echo '<textarea class="large-text" rows="3" id="' . esc_attr($id) . '" name="sgs[' . esc_attr($key) . ']">' . esc_textarea($wert) . '</textarea>';
        } else {
            echo '<input class="regular-text" type="' . esc_attr($typ) . '" id="' . esc_attr($id) . '" name="sgs[' . esc_attr($key) . ']" value="' . esc_attr($wert) . '">';
        }
        if ($hilfe) {
            echo '<p class="description">' . esc_html($hilfe) . '</p>';
        }
        echo '</td></tr>';
    }
    echo '</table>';
}

add_action('save_post', function (int $post_id, WP_Post $post) {
    $felder = sgs_felder()[$post->post_type] ?? null;
    if (!$felder || !isset($_POST['sgs_felder_nonce']) || !wp_verify_nonce(sanitize_key($_POST['sgs_felder_nonce']), 'sgs_felder')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $eingabe = wp_unslash($_POST['sgs'] ?? []);
    foreach ($felder as $key => [, $typ]) {
        $v = (string) ($eingabe[$key] ?? '');
        $v = match ($typ) {
            'textarea' => sanitize_textarea_field($v),
            'email'    => sanitize_email($v),
            'number'   => $v === '' ? '' : (string) (int) $v,
            'date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '',
            default    => sanitize_text_field($v),
        };
        update_post_meta($post_id, $key, $v);
    }
    $kuerzel = sanitize_title(wp_unslash($_POST['sgs_kuerzel'] ?? ''));
    if ($post->post_type === 'sgs_person' && $kuerzel !== '' && $kuerzel !== $post->post_name) {
        remove_all_actions('save_post'); // keine Endlosschleife
        wp_update_post(['ID' => $post_id, 'post_name' => $kuerzel]);
    }
}, 10, 2);

// Spalten in den Übersichtslisten.
add_filter('manage_sgs_termin_posts_columns', fn($c) => array_slice($c, 0, 2) + ['datum' => 'Datum', 'ort' => 'Ort']);
add_filter('manage_sgs_person_posts_columns', fn($c) => array_slice($c, 0, 2) + ['kuerzel' => 'Kürzel', 'funktion' => 'Funktion', 'email' => 'E-Mail']);
add_filter('manage_sgs_kiosk_posts_columns', fn($c) => array_slice($c, 0, 2) + ['preis' => 'Preis']);
add_filter('manage_sgs_flugzeug_posts_columns', fn($c) => array_slice($c, 0, 2) + ['immatrikulation' => 'Immatrikulation']);
foreach (['sgs_termin', 'sgs_person', 'sgs_kiosk', 'sgs_flugzeug'] as $type) {
    add_action("manage_{$type}_posts_custom_column", function ($col, $id) {
        if ($col === 'kuerzel') {
            echo '<code>' . esc_html(get_post_field('post_name', $id)) . '</code>';
        } elseif ($col === 'datum') {
            $d = (string) get_post_meta($id, 'datum', true);
            echo $d ? esc_html(sgs_datum($d)) : '–';
        } else {
            echo esc_html((string) get_post_meta($id, $col, true));
        }
    }, 10, 2);
}

// Termine in der Liste nach Datum sortieren.
add_action('pre_get_posts', function (WP_Query $q) {
    if (is_admin() && $q->is_main_query() && $q->get('post_type') === 'sgs_termin' && !$q->get('orderby')) {
        $q->set('meta_key', 'datum');
        $q->set('orderby', 'meta_value');
        $q->set('order', 'DESC');
    }
});

// Reihenfolge und Symbol für Gruppen und Kategorien.
foreach (['sgs_flugzeuggruppe', 'sgs_kioskkategorie'] as $tax) {
    $felder = function ($term = null) use ($tax) {
        $reihe = $term ? (string) get_term_meta($term->term_id, 'reihenfolge', true) : '';
        $icon  = $term ? (string) get_term_meta($term->term_id, 'icon', true) : '';
        wp_nonce_field('sgs_term', 'sgs_term_nonce');
        $zeile = fn($label, $feld) => $term ? "<tr class=\"form-field\"><th><label>$label</label></th><td>$feld</td></tr>" : "<div class=\"form-field\"><label>$label</label>$feld</div>";
        echo $zeile('Reihenfolge', '<input type="number" name="sgs_reihenfolge" value="' . esc_attr($reihe) . '">');
        if ($tax === 'sgs_kioskkategorie') {
            echo $zeile('Symbol', '<input name="sgs_icon" value="' . esc_attr($icon) . '"><p class="description">Name eines Lucide-Icons aus dem Theme, z. B. coffee, beer, wine, flame, cookie, cup-soda</p>');
        }
    };
    add_action("{$tax}_add_form_fields", fn() => $felder());
    add_action("{$tax}_edit_form_fields", fn($term) => $felder($term));
    $speichern = function ($term_id) {
        if (!isset($_POST['sgs_term_nonce']) || !wp_verify_nonce(sanitize_key($_POST['sgs_term_nonce']), 'sgs_term') || !current_user_can('edit_pages')) {
            return;
        }
        update_term_meta($term_id, 'reihenfolge', (int) ($_POST['sgs_reihenfolge'] ?? 0));
        if (isset($_POST['sgs_icon'])) {
            update_term_meta($term_id, 'icon', sanitize_key(wp_unslash($_POST['sgs_icon'])));
        }
    };
    add_action("created_{$tax}", $speichern);
    add_action("edited_{$tax}", $speichern);
}
