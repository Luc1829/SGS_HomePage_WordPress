<?php
// Einmaliger Import aus dem statischen Entwurf (Ordner import/ in diesem Plugin).
// Admin: Werkzeuge › SGS-Import. Kommandozeile: wp sgs import
// Bereits importierte Einträge werden übersprungen (Kennung im Feld «_sgs_import»), der Import kann also gefahrlos wiederholt werden.

defined('ABSPATH') || exit;

const SGS_IMPORT_DIR = SGS_VEREIN_DIR . '/import';

add_action('admin_menu', function () {
    add_management_page('SGS-Import', 'SGS-Import', 'manage_options', 'sgs-import', 'sgs_import_seite');
});

function sgs_import_seite(): void {
    echo '<div class="wrap"><h1>Inhalte aus dem Entwurf übernehmen</h1>';
    if (isset($_POST['sgs_import']) && check_admin_referer('sgs_import')) {
        $log = sgs_import_alles();
        echo '<div class="notice notice-success"><p>Import abgeschlossen.</p></div><pre style="background:#fff;padding:1em;max-height:30em;overflow:auto">' . esc_html(implode("\n", $log)) . '</pre>';
    }
    echo '<p>Legt Werte & Preise, Personen, Termine, Flugzeugpark, Kiosk, alle Berichte mit Bildern, die Seiten und das Menü aus dem statischen Entwurf an. Schon vorhandene Einträge bleiben unverändert. Dauert wegen der Bilder etwa eine Minute.</p>';
    echo '<form method="post">';
    wp_nonce_field('sgs_import');
    submit_button('Import starten', 'primary', 'sgs_import');
    echo '</form></div>';
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sgs import', function () {
        foreach (sgs_import_alles() as $zeile) {
            WP_CLI::log($zeile);
        }
        WP_CLI::success('Import abgeschlossen.');
    });
}

function sgs_import_json(string $name) {
    return json_decode((string) file_get_contents(SGS_IMPORT_DIR . "/data/$name.json"), true);
}

/** wp_insert_post erwartet maskierte Daten (Backslashes in Inhalten bleiben so erhalten). */
function sgs_import_post(array $daten): int {
    return (int) wp_insert_post(wp_slash($daten));
}

/** Schon importiert? Liefert die ID des Beitrags mit dieser Kennung. */
function sgs_import_finden(string $kennung, string $type = 'any'): int {
    $ids = get_posts(['post_type' => $type, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_sgs_import', 'meta_value' => $kennung]);
    return $ids ? (int) $ids[0] : 0;
}

/** Datei aus import/assets in die Mediathek übernehmen (einmal pro Datei). Liefert die Anhang-ID. */
function sgs_import_medium(string $pfad, int $parent = 0): int {
    $pfad = ltrim($pfad, '/');
    if ($id = sgs_import_finden('medium:' . $pfad, 'attachment')) {
        return $id;
    }
    $datei = SGS_IMPORT_DIR . '/' . $pfad;
    if (!is_readable($datei)) {
        return 0;
    }
    $upload = wp_upload_bits(basename($datei), null, (string) file_get_contents($datei));
    if (!empty($upload['error'])) {
        return 0;
    }
    $typ = wp_check_filetype($upload['file']);
    $id  = wp_insert_attachment([
        'post_mime_type' => $typ['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($datei)),
        'post_status'    => 'inherit',
    ], $upload['file'], $parent);
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
    update_post_meta($id, '_sgs_import', 'medium:' . $pfad);
    return $id;
}

/** @root@-Pfade aus dem Entwurf: Bilder/PDFs in die Mediathek, Seitenlinks auf diese Installation. */
function sgs_import_links(string $html, int $parent = 0): string {
    $html = preg_replace_callback('#@root@(assets/[^"\'\s),]+)#', function ($m) use ($parent) {
        $id = sgs_import_medium($m[1], $parent);
        return $id ? wp_get_attachment_url($id) : $m[0];
    }, $html);
    return preg_replace_callback('#@root@([^"\'\s]*)#', fn($m) => home_url('/' . $m[1]), $html);
}

function sgs_import_alles(): array {
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }
    wp_raise_memory_limit('admin');
    // Der Inhalt stammt aus diesem Plugin (Formulare, SVG, data-Attribute): nicht durch den HTML-Filter für Kommentare schicken.
    kses_remove_filters();
    $log = [];
    $log[] = sgs_import_werte();
    $log[] = sgs_import_personen();
    $log[] = sgs_import_termine();
    $log[] = sgs_import_flotte();
    $log[] = sgs_import_kiosk();
    $log[] = sgs_import_berichte();
    $log[] = sgs_import_seiten();
    $log[] = sgs_import_menue();
    $log[] = sgs_import_einstellungen();
    return $log;
}

function sgs_import_werte(): string {
    $flach = function ($daten, $prefix) use (&$flach) {
        $out = [];
        foreach ($daten as $k => $v) {
            if (str_starts_with((string) $k, '_')) {
                continue;
            }
            if (is_array($v) && !array_is_list($v)) {
                $out += $flach($v, "$prefix.$k");
            } elseif (is_scalar($v)) {
                $out["$prefix.$k"] = is_bool($v) ? ($v ? 'ja' : 'nein') : (string) $v;
            }
        }
        return $out;
    };
    $kiosk = sgs_import_json('kiosk');
    $werte = $flach(sgs_import_json('site'), 'site')
        + ['site.allgemein' => sgs_import_json('contacts')['allgemein']]
        + $flach(sgs_import_json('angebote'), 'angebote')
        + $flach(['intro' => $kiosk['intro'], 'season' => $kiosk['season'], 'teaser' => $kiosk['teaser']], 'kiosk')
        + ['kiosk.zeiten' => implode("\n", array_map(fn($h) => $h['days'] . ' | ' . $h['time'], $kiosk['hours']))];
    // Flugplatz: nur einfache Angaben (Listen wie Gebühren und Pisten bleiben im Seitentext).
    foreach (sgs_import_json('airfield') as $k => $v) {
        if (is_scalar($v) && !str_starts_with($k, '_')) {
            $werte["airfield.$k"] = (string) $v;
        }
    }
    $alt = sgs_werte();
    update_option('sgs_werte', $alt + $werte); // vorhandene Werte nicht überschreiben
    return 'Werte & Preise: ' . count($werte) . ' Angaben (' . count(array_diff_key($werte, $alt)) . ' neu)';
}

function sgs_import_personen(): string {
    $c = sgs_import_json('contacts');
    $vorstand = [];
    foreach ($c['vorstand'] as $i => $v) {
        $vorstand[$v['person']] = $v + ['reihe' => $i + 1];
    }
    $kontakt = [];
    foreach ($c['kontaktseite'] as $i => $v) {
        $kontakt[$v['person']] = $v + ['reihe' => $i + 1];
    }
    $neu = 0;
    $i = 0;
    foreach ($c['people'] as $key => $p) {
        $i++;
        if (sgs_import_finden("person:$key", 'sgs_person')) {
            continue;
        }
        $id = sgs_import_post([
            'post_type' => 'sgs_person', 'post_status' => 'publish', 'post_title' => $p['name'], 'post_name' => $key,
            'menu_order' => $vorstand[$key]['reihe'] ?? 50 + $i,
        ]);
        update_post_meta($id, '_sgs_import', "person:$key");
        update_post_meta($id, 'funktion', $p['title']);
        update_post_meta($id, 'email', $p['email']);
        update_post_meta($id, 'telefon', implode("\n", array_map(fn($t) => $t['label'] . ' | ' . $t['number'], $p['phones'] ?? [])));
        update_post_meta($id, 'vorstand', $vorstand[$key]['role'] ?? '');
        update_post_meta($id, 'kontaktseite', $kontakt[$key]['role'] ?? '');
        update_post_meta($id, 'kontakt_reihe', (string) ($kontakt[$key]['reihe'] ?? ''));
        if (!empty($vorstand[$key]['photo']) && ($foto = sgs_import_medium($vorstand[$key]['photo'], $id))) {
            set_post_thumbnail($id, $foto);
        }
        $neu++;
    }
    return "Personen: $neu neu";
}

function sgs_import_termine(): string {
    $neu = 0;
    foreach (sgs_import_json('events') as $e) {
        $kennung = 'termin:' . $e['date'] . ':' . sanitize_title($e['title']);
        if (sgs_import_finden($kennung, 'sgs_termin')) {
            continue;
        }
        $id = sgs_import_post(['post_type' => 'sgs_termin', 'post_status' => 'publish', 'post_title' => $e['title']]);
        update_post_meta($id, '_sgs_import', $kennung);
        update_post_meta($id, 'datum', $e['date']);
        update_post_meta($id, 'ort', $e['place']);
        update_post_meta($id, 'fuer', $e['who']);
        $neu++;
    }
    return "Termine: $neu neu";
}

function sgs_import_term(string $name, string $taxonomy, int $reihe, string $beschreibung = '', string $icon = ''): int {
    $t = term_exists($name, $taxonomy);
    if (!$t) {
        $t = wp_insert_term($name, $taxonomy, ['description' => $beschreibung]);
        update_term_meta((int) $t['term_id'], 'reihenfolge', $reihe);
        if ($icon) {
            update_term_meta((int) $t['term_id'], 'icon', $icon);
        }
    }
    return (int) $t['term_id'];
}

function sgs_import_flotte(): string {
    $neu = 0;
    foreach (sgs_import_json('fleet')['groups'] as $gi => $g) {
        $term = sgs_import_term($g['title'], 'sgs_flugzeuggruppe', $gi + 1, $g['text']);
        foreach ($g['planes'] as $pi => $p) {
            if (sgs_import_finden('flugzeug:' . $p['reg'], 'sgs_flugzeug')) {
                continue;
            }
            $id = sgs_import_post(['post_type' => 'sgs_flugzeug', 'post_status' => 'publish', 'post_title' => $p['name'], 'menu_order' => $pi + 1]);
            update_post_meta($id, '_sgs_import', 'flugzeug:' . $p['reg']);
            update_post_meta($id, 'immatrikulation', $p['reg']);
            update_post_meta($id, 'kennzeichen', $p['cn'] ?? '');
            update_post_meta($id, 'beschreibung', $p['text']);
            update_post_meta($id, 'link', empty($p['link']) ? '' : '/' . $p['link']);
            update_post_meta($id, 'kennzahlen', implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($p['specs'] ?? []), $p['specs'] ?? [])));
            wp_set_object_terms($id, [$term], 'sgs_flugzeuggruppe');
            if (!empty($p['photo']) && ($foto = sgs_import_medium($p['photo'], $id))) {
                set_post_thumbnail($id, $foto);
            }
            $neu++;
        }
    }
    return "Flugzeugpark: $neu neu";
}

function sgs_import_kiosk(): string {
    $neu = 0;
    foreach (sgs_import_json('kiosk')['menu'] as $ci => $cat) {
        $term = sgs_import_term($cat['title'], 'sgs_kioskkategorie', $ci + 1, '', $cat['icon'] ?? '');
        foreach ($cat['items'] as $ii => $d) {
            $kennung = 'kiosk:' . sanitize_title($cat['title'] . '-' . $d['name']);
            if (sgs_import_finden($kennung, 'sgs_kiosk')) {
                continue;
            }
            $id = sgs_import_post(['post_type' => 'sgs_kiosk', 'post_status' => 'publish', 'post_title' => $d['name'], 'menu_order' => $ii + 1]);
            update_post_meta($id, '_sgs_import', $kennung);
            update_post_meta($id, 'preis', $d['price']);
            update_post_meta($id, 'zusatz', $d['text'] ?? '');
            wp_set_object_terms($id, [$term], 'sgs_kioskkategorie');
            if (!empty($d['photo']) && ($foto = sgs_import_medium($d['photo'], $id))) {
                set_post_thumbnail($id, $foto);
            }
            $neu++;
        }
    }
    return "Kiosk: $neu Artikel neu";
}

function sgs_import_berichte(): string {
    $neu = 0;
    foreach (array_reverse(sgs_import_json('berichte')) as $b) {
        if (sgs_import_finden('bericht:' . $b['slug'], 'post')) {
            continue;
        }
        $cats = array_map(fn($t) => sgs_import_term($t, 'category', 0), $b['tags']);
        $id = sgs_import_post([
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $b['title'], 'post_name' => $b['slug'],
            'post_date' => $b['date'] . ' 12:00:00', 'post_excerpt' => $b['teaser'] ?? '', 'post_category' => $cats,
        ]);
        update_post_meta($id, '_sgs_import', 'bericht:' . $b['slug']);
        update_post_meta($id, 'quelle', $b['source'] ?? '');
        wp_update_post(wp_slash(['ID' => $id, 'post_content' => sgs_import_links($b['html'], $id)]));
        if (!empty($b['image']) && ($bild = sgs_import_medium($b['image'], $id))) {
            set_post_thumbnail($id, $bild);
        }
        $neu++;
    }
    // Beispielbeitrag von WordPress und die Kategorie «Allgemein» entfernen, falls unverändert bzw. leer.
    $hallo = get_page_by_path('hello-world', OBJECT, 'post');
    if ($hallo && $hallo->post_date === $hallo->post_modified) {
        wp_delete_post($hallo->ID, true);
    }
    if (($verein = get_term_by('name', 'Verein', 'category'))) {
        update_option('default_category', $verein->term_id);
    }
    $u = get_term_by('slug', 'uncategorized', 'category');
    if ($u && !$u->count) {
        wp_delete_term($u->term_id, 'category');
    }
    return "Berichte: $neu neu";
}

function sgs_import_seiten(): string {
    $seiten = json_decode((string) file_get_contents(SGS_IMPORT_DIR . '/pages.json'), true);
    $ids = [];
    $neu = 0;
    foreach ($seiten as $s) {
        $pfad = trim($s['path'], '/');
        $kennung = 'seite:' . ($pfad ?: 'start');
        if ($id = sgs_import_finden($kennung, 'page')) {
            $ids[$pfad] = $id;
            continue;
        }
        $teile = explode('/', $pfad);
        $slug  = $pfad === '' ? 'startseite' : array_pop($teile);
        $eltern = $teile ? ($ids[implode('/', $teile)] ?? 0) : 0;
        $id = sgs_import_post([
            'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $s['title'], 'post_name' => $slug,
            'post_parent' => $eltern, 'post_content' => '',
        ]);
        update_post_meta($id, '_sgs_import', $kennung);
        update_post_meta($id, '_sgs_section', $s['section']);
        update_post_meta($id, '_sgs_description', $s['description']);
        // wp_slash: WordPress entfernt beim Speichern Backslashes (z. B. in JSON-Attributen).
        wp_update_post(wp_slash(['ID' => $id, 'post_content' => sgs_import_links($s['content'], $id)]));
        $ids[$pfad] = $id;
        $neu++;
    }
    // Beispielseite von WordPress entfernen, falls unverändert.
    $beispiel = get_page_by_path('sample-page');
    if ($beispiel && $beispiel->post_date === $beispiel->post_modified) {
        wp_delete_post($beispiel->ID, true);
    }
    if (!empty($ids[''])) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $ids['']);
    }
    return "Seiten: $neu neu";
}

/** Hauptnavigation wie im Entwurf (Reihenfolge: Besuchen · Für Piloten · Fliegen lernen · Verein · Flüüger-Fäscht). */
function sgs_import_menue(): string {
    if (wp_get_nav_menu_object('Hauptnavigation')) {
        return 'Menü: vorhanden';
    }
    $nav = [
        ['Besuchen', [
            ['besuchen', 'Kiosk & Gartenbeiz', 'Angebot, Preise und Infotafeln'],
            ['besuchen/rundfluege', 'Rundflüge & Gutscheine', 'Mitfliegen oder verschenken'],
            ['besuchen/ferienpass', 'Ferienpass', 'Ein Nachmittag auf dem Flugplatz für Kinder'],
        ]],
        ['Für Piloten', [
            ['piloten', 'Flugplatz LSPF', 'Wind, QNH, Webcam, AIP und Anflugkarten'],
            ['piloten/ppr', 'PPR anfragen', 'Landeerlaubnis und Gebühren'],
            ['piloten/live', 'Live & Streckenflüge', 'SPOT-Tracking, Live-Karte und WeGlide'],
            ['piloten/safety', 'Safety-Meldung', 'Vorfälle melden, auch anonym'],
            ['piloten/modellflug', 'Modellflug & Drohnen', 'Bewilligung im 5-km-Umkreis'],
            ['piloten/kosten', 'Vereinskosten', 'Gebühren und Tarife als PDF (Platzhalter)'],
        ]],
        ['Fliegen lernen', [
            ['lernen', 'Ausbildung & Schnupperflug', 'Vom Schnupperflug zur Lizenz'],
            ['lernen/motorflug', 'Motorflug', 'Umschulungen und Schleppausbildung (Platzhalter)'],
            ['lernen/dimona', 'Super Dimona (TMG)', 'Einweisung Reisemotorsegler'],
            ['lernen/mitglied', 'Mitglied werden', 'Kategorie A, M und Passiv'],
        ]],
        ['Verein', [
            ['verein', 'Über uns', 'Wer wir sind, seit wann es uns gibt'],
            ['verein/flotte', 'Flugzeugpark', 'Segelflugzeuge, Motorsegler, Schleppflugzeuge'],
            ['verein/vorstand', 'Vorstand', 'Ansprechpersonen im Verein'],
            ['verein/medien', 'Berichte & Medien', 'Berichte, Presse, Bilder und Schmerlat-Post'],
        ]],
    ];
    $menu = wp_create_nav_menu('Hauptnavigation');
    $seite = function (string $pfad, string $titel, string $hinweis, int $parent, int $pos) use ($menu) {
        $page = get_page_by_path($pfad);
        return wp_update_nav_menu_item($menu, 0, [
            'menu-item-title' => $titel, 'menu-item-description' => $hinweis, 'menu-item-status' => 'publish',
            'menu-item-object' => 'page', 'menu-item-object-id' => $page ? $page->ID : 0, 'menu-item-type' => 'post_type',
            'menu-item-parent-id' => $parent, 'menu-item-position' => $pos,
        ]);
    };
    $pos = 0;
    foreach ($nav as [$titel, $items]) {
        $gruppe = wp_update_nav_menu_item($menu, 0, [
            'menu-item-title' => $titel, 'menu-item-url' => '#', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-position' => ++$pos,
        ]);
        foreach ($items as [$pfad, $t, $h]) {
            $seite($pfad, $t, $h, $gruppe, ++$pos);
        }
    }
    $fest = $seite('fest', 'Flüüger-Fäscht', '', 0, ++$pos);
    update_post_meta($fest, '_menu_item_classes', ['nav-fest']);
    set_theme_mod('nav_menu_locations', array_merge((array) get_theme_mod('nav_menu_locations', []), ['haupt' => $menu]));
    return 'Menü: angelegt';
}

function sgs_import_einstellungen(): string {
    update_option('blogname', 'Segelfluggruppe Schaffhausen');
    update_option('blogdescription', 'Flugplatz Schmerlat');
    update_option('timezone_string', 'Europe/Zurich');
    update_option('date_format', 'd.m.Y');
    update_option('time_format', 'H:i');
    update_option('WPLANG', 'de_CH');
    // Berichte unter /verein/berichte/<name>/ wie im Entwurf.
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure('/verein/berichte/%postname%/');
    update_option('default_comment_status', 'closed');
    update_option('default_ping_status', 'closed');
    // Empfänger der Formulare (Plugin «SGS Formulare»), falls noch nicht festgelegt.
    if (!get_option('sgs_formulare_empfaenger')) {
        update_option('sgs_formulare_empfaenger', sgs_import_json('contacts')['formulare']);
    }
    $wp_rewrite->flush_rules(true);
    return 'Einstellungen: Zeitzone, Datumsformat, Permalinks, Formular-Empfänger';
}
