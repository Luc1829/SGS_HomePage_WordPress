<?php
// Menü «SGS» im Admin: Werte & Preise, Termine, Flugzeugpark, Personen, Kiosk.

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_menu_page('SGS', 'SGS', 'edit_pages', 'sgs', 'sgs_werte_seite', 'dashicons-cloud', 3);
    add_submenu_page('sgs', 'Werte & Preise', 'Werte & Preise', 'edit_pages', 'sgs', 'sgs_werte_seite');
    add_submenu_page('sgs', 'Flugzeug-Gruppen', 'Flugzeug-Gruppen', 'edit_pages', 'edit-tags.php?taxonomy=sgs_flugzeuggruppe&post_type=sgs_flugzeug');
    add_submenu_page('sgs', 'Kiosk-Kategorien', 'Kiosk-Kategorien', 'edit_pages', 'edit-tags.php?taxonomy=sgs_kioskkategorie&post_type=sgs_kiosk');
});

// Untermenü «SGS» offen halten, wenn eine Gruppe oder Kategorie bearbeitet wird.
add_filter('parent_file', function ($parent) {
    $screen = get_current_screen();
    return in_array($screen->taxonomy ?? '', ['sgs_flugzeuggruppe', 'sgs_kioskkategorie'], true) ? 'sgs' : $parent;
});

const SGS_WERTE_GRUPPEN = [
    'site'     => ['Verein', 'Adresse, Telefon am Flugplatz, Bank, Gründung. «allgemein» ist das Kürzel der Person, deren E-Mail im Fuss jeder Seite steht.'],
    'angebote' => ['Angebote & Preise', 'Beträge als Zahl in CHF (z. B. 150 oder 6.2). Die Seiten zeigen sie als «CHF 150.–».'],
    'kiosk'    => ['Kiosk', 'Öffnungszeiten: eine Zeile pro Eintrag, «Tage | Zeit». Artikel und Preise unter SGS › Kiosk.'],
    'airfield' => ['Flugplatz', 'Frequenzen, Pistenangaben, Webcam.'],
];

function sgs_werte_seite(): void {
    if (!current_user_can('edit_pages')) {
        return;
    }
    $werte = sgs_werte();
    if (isset($_POST['sgs_werte_nonce']) && wp_verify_nonce(sanitize_key($_POST['sgs_werte_nonce']), 'sgs_werte')) {
        $neu = wp_unslash($_POST['wert'] ?? []);
        foreach ($werte as $key => $alt) {
            if (array_key_exists($key, $neu)) {
                $werte[$key] = str_contains((string) $neu[$key], "\n") ? sanitize_textarea_field($neu[$key]) : sanitize_text_field($neu[$key]);
            }
        }
        update_option('sgs_werte', $werte);
        echo '<div class="notice notice-success"><p>Gespeichert. Die Werte sind auf allen Seiten aktualisiert.</p></div>';
    }
    echo '<div class="wrap"><h1>Werte & Preise</h1>';
    echo '<p>Jede Angabe steht nur hier und erscheint automatisch auf allen Seiten, die sie verwenden. In einem Seitentext einsetzen mit <code>[sgs_wert k=schlüssel]</code>, Beträge mit <code>[sgs_wert k=schlüssel f=chf]</code>.</p>';
    if (!$werte) {
        echo '<p><b>Noch keine Werte vorhanden.</b> Unter Werkzeuge › SGS-Import den Inhalt aus dem Entwurf übernehmen.</p></div>';
        return;
    }
    echo '<form method="post">';
    wp_nonce_field('sgs_werte', 'sgs_werte_nonce');
    foreach (SGS_WERTE_GRUPPEN as $gruppe => [$titel, $hilfe]) {
        echo '<h2>' . esc_html($titel) . '</h2><p class="description">' . esc_html($hilfe) . '</p><table class="form-table" role="presentation">';
        foreach ($werte as $key => $wert) {
            if (!str_starts_with($key, $gruppe . '.')) {
                continue;
            }
            $id = 'w_' . md5($key);
            echo '<tr><th scope="row"><label for="' . $id . '">' . esc_html(substr($key, strlen($gruppe) + 1)) . '</label><br><code style="font-weight:normal;font-size:11px">' . esc_html($key) . '</code></th><td>';
            if (strlen((string) $wert) > 70 || str_contains((string) $wert, "\n")) {
                echo '<textarea class="large-text" rows="3" id="' . $id . '" name="wert[' . esc_attr($key) . ']">' . esc_textarea($wert) . '</textarea>';
            } else {
                echo '<input class="regular-text" id="' . $id . '" name="wert[' . esc_attr($key) . ']" value="' . esc_attr($wert) . '">';
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }
    submit_button('Werte speichern');
    echo '</form></div>';
}
