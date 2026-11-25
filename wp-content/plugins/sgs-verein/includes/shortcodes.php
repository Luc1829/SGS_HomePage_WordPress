<?php
// Shortcodes für Seiteninhalte. Sie erzeugen dasselbe Markup wie der statische Entwurf (Klassen aus site.css).

defined('ABSPATH') || exit;

/** [sgs_wert k=angebote.schnupperflug.preis f=chf]  f: chf | tel */
add_shortcode('sgs_wert', function ($a) {
    $a = shortcode_atts(['k' => '', 'f' => ''], $a);
    $v = sgs_wert((string) $a['k']);
    return match ($a['f']) {
        'chf'   => esc_html(sgs_chf($v)),
        'tel'   => esc_attr(sgs_tel($v)),
        default => esc_html($v),
    };
});

/** [sgs_fest] -> 2027, [sgs_fest f=datum] -> 4./5. September 2027 */
add_shortcode('sgs_fest', function ($a) {
    $f = sgs_fest();
    return esc_html(($a['f'] ?? '') === 'datum' ? $f['label'] : (string) $f['year']);
});

/** Streifenmotiv wie im Logo: oben kräftig, nach unten feiner. Nur Dekor. */
function sgs_stripes(): string {
    $y = 0.0;
    $rects = '';
    for ($i = 0; $i < 22; $i++) {
        $h = max(1.2, 13 - $i * 0.58);
        $rects .= sprintf('<rect x="0" y="%.1f" width="100" height="%.1f"/>', $y, $h);
        $y += $h + 7;
    }
    return '<svg class="stripes" viewBox="0 0 100 ' . ceil($y) . '" preserveAspectRatio="none" aria-hidden="true" focusable="false"><g fill="currentColor">' . $rects . '</g></svg>';
}

/** Platzhalter für Werte im Titel: «Schmerlat {airfield.icao}», «Flüüger-Fäscht {fest}». */
function sgs_platzhalter(string $text): string {
    return preg_replace_callback('/\{([a-z0-9_.]+)\}/i', fn($m) => $m[1] === 'fest' ? (string) sgs_fest()['year'] : sgs_wert($m[1]), $text);
}

/** [sgs_seitenkopf rubrik="Verein" link="/verein/" titel="Vorstand"]Einleitung[/sgs_seitenkopf] */
add_shortcode('sgs_seitenkopf', function ($a, $lead = '') {
    $a = shortcode_atts(['rubrik' => '', 'link' => '', 'titel' => ''], $a);
    $lead = trim(do_shortcode((string) $lead));
    return '<section class="page-head">' . sgs_stripes() . '<div class="wrap page-head-grid"><div>'
        . ($a['rubrik'] ? '<a class="crumb" href="' . esc_url($a['link']) . '">' . esc_html($a['rubrik']) . '</a>' : '')
        . '<h1>' . esc_html(sgs_platzhalter($a['titel'] ?: get_the_title())) . '</h1>'
        . ($lead !== '' ? '<p class="lead">' . wp_kses_post($lead) . '</p>' : '')
        . '</div></div></section>';
});

/** [sgs_kontakt person=praesident rolle="…" telefon=nein]Text davor[/sgs_kontakt] */
add_shortcode('sgs_kontakt', function ($a, $vor = '') {
    $a = shortcode_atts(['person' => '', 'rolle' => '', 'telefon' => 'ja'], $a);
    $p = sgs_person((string) $a['person']);
    if (!$p) {
        return '';
    }
    $html = '<p class="contact-block">' . wp_kses_post((string) $vor) . '<b>' . esc_html($p['name']) . '</b>, ' . esc_html($a['rolle'] ?: $p['title'])
        . '<br><a href="mailto:' . esc_attr($p['email']) . '">' . esc_html($p['email']) . '</a>';
    if ($a['telefon'] !== 'nein') {
        foreach ($p['phones'] as $t) {
            $html .= '<br>' . esc_html($t['label']) . ' <a href="' . esc_attr($t['href']) . '">' . esc_html($t['number']) . '</a>';
        }
    }
    return $html . '</p>';
});

/** [sgs_mail person=vorstand]Vorstand der SGS[/sgs_mail] oder ohne Text: die Adresse. */
add_shortcode('sgs_mail', function ($a, $text = '') {
    $p = sgs_person((string) ($a['person'] ?? ''));
    if (!$p) {
        return esc_html((string) $text);
    }
    return '<a href="mailto:' . esc_attr($p['email']) . '">' . esc_html($text !== '' && $text !== null ? $text : $p['email']) . '</a>';
});

/** [sgs_termine anzahl=4 darstellung=reihe]: kommende Termine (vergangene fallen automatisch weg). */
add_shortcode('sgs_termine', function ($a) {
    $a = shortcode_atts(['anzahl' => -1, 'darstellung' => ''], $a);
    $termine = get_posts([
        'post_type' => 'sgs_termin', 'post_status' => 'publish', 'numberposts' => (int) $a['anzahl'],
        'meta_key' => 'datum', 'orderby' => 'meta_value', 'order' => 'ASC',
        'meta_query' => [['key' => 'datum', 'value' => wp_date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE']],
    ]);
    if (!$termine) {
        return '<p class="note">Zurzeit sind keine öffentlichen Termine eingetragen.</p>';
    }
    $reihe = $a['darstellung'] === 'reihe';
    $html = '<ul class="events ' . ($reihe ? 'events-row' : 'measure') . '">';
    foreach ($termine as $t) {
        $d    = sgs_datum_teile((string) get_post_meta($t->ID, 'datum', true));
        $ort  = (string) get_post_meta($t->ID, 'ort', true);
        $fuer = (string) get_post_meta($t->ID, 'fuer', true);
        $wann = $reihe ? $d['weekday'] : "{$d['weekday']}, {$d['day']}. {$d['monthLong']} {$d['year']}";
        $info = implode(', ', array_filter([$wann, $ort, $fuer ? "für $fuer" : '']));
        $html .= '<li><div class="date-tile"><b>' . $d['day'] . '</b><span>' . esc_html($d['month']) . '</span></div>'
            . '<div><h3>' . esc_html(get_the_title($t)) . '</h3><p>' . esc_html($info) . '</p></div></li>';
    }
    return $html . '</ul>';
});

/** Foto oder ehrlich gekennzeichneter Platzhalter. */
function sgs_foto(?string $src, string $alt, string $ratio = '4 / 3'): string {
    if ($src) {
        return '<img src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" loading="lazy" style="aspect-ratio:' . esc_attr($ratio) . '">';
    }
    return '<div class="ph" style="aspect-ratio:' . esc_attr($ratio) . '" role="img" aria-label="Foto folgt: ' . esc_attr($alt) . '"><span>Foto folgt<br><b>' . esc_html($alt) . '</b></span></div>';
}

/** [sgs_flotte]: Flugzeuge nach Gruppen. */
add_shortcode('sgs_flotte', function () {
    $html = '';
    foreach (sgs_terms_sortiert('sgs_flugzeuggruppe') as $g) {
        $planes = sgs_beitraege('sgs_flugzeug', ['tax_query' => [['taxonomy' => 'sgs_flugzeuggruppe', 'terms' => $g->term_id]]]);
        $html .= '<div class="fleet-group"><header><h2>' . esc_html($g->name) . '</h2><p>' . esc_html($g->description) . '</p></header><div class="tiles">';
        foreach ($planes as $p) {
            $name = get_the_title($p);
            $link = (string) get_post_meta($p->ID, 'link', true);
            $cn   = (string) get_post_meta($p->ID, 'kennzeichen', true);
            $html .= '<article class="tile">' . sgs_foto(get_the_post_thumbnail_url($p, 'large') ?: null, $name, '16 / 9')
                . '<h3>' . ($link ? '<a href="' . esc_url(home_url($link)) . '">' . esc_html($name) . '</a>' : esc_html($name)) . '</h3>'
                . '<span class="reg">' . esc_html((string) get_post_meta($p->ID, 'immatrikulation', true)) . ($cn ? ' · Wettbewerbskennzeichen ' . esc_html($cn) : '') . '</span>'
                . '<p>' . esc_html((string) get_post_meta($p->ID, 'beschreibung', true)) . '</p><dl class="specs">';
            foreach (preg_split('/\R/', (string) get_post_meta($p->ID, 'kennzahlen', true)) as $zeile) {
                if (str_contains($zeile, ':')) {
                    [$k, $v] = array_map('trim', explode(':', $zeile, 2));
                    $html .= '<div><dt>' . esc_html($k) . '</dt><dd>' . esc_html($v) . '</dd></div>';
                }
            }
            $html .= '</dl></article>';
        }
        $html .= '</div></div>';
    }
    return $html;
});

/** [sgs_personen liste=vorstand|kontakt] */
add_shortcode('sgs_personen', function ($a) {
    $liste = ($a['liste'] ?? 'vorstand') === 'kontakt' ? 'kontaktseite' : 'vorstand';
    $leute = array_filter(sgs_beitraege('sgs_person'), fn($p) => get_post_meta($p->ID, $liste, true) !== '');
    if ($liste === 'kontaktseite') {
        usort($leute, fn($a, $b) => (int) get_post_meta($a->ID, 'kontakt_reihe', true) <=> (int) get_post_meta($b->ID, 'kontakt_reihe', true));
    }
    $html = '<div class="people">';
    foreach ($leute as $post) {
        $p     = sgs_person_daten($post);
        $rolle = (string) get_post_meta($post->ID, $liste, true);
        $mail  = '<a href="mailto:' . esc_attr($p['email']) . '">' . esc_html($p['email']) . '</a>';
        if ($liste === 'vorstand') {
            $kurz = implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', trim(preg_replace('/«.*?»\s*/u', '', $p['name']))), 0, 2)));
            $html .= '<article class="person"><div class="avatar">'
                . ($p['photo'] ? '<img src="' . esc_url($p['photo']) . '" alt="' . esc_attr($p['name']) . '" loading="lazy">' : '<span aria-hidden="true">' . esc_html($kurz) . '</span>')
                . '</div><h3>' . esc_html($p['name']) . '</h3><p class="role">' . esc_html($rolle) . '</p>' . $mail . '</article>';
        } else {
            $tel = implode('', array_map(fn($t) => '<br><a href="' . esc_attr($t['href']) . '">' . esc_html($t['number']) . '</a>', $p['phones']));
            $html .= '<article class="person"><p class="role">' . esc_html($rolle) . '</p><h3 style="margin-top:0">' . esc_html($p['name']) . '</h3>' . $mail . $tel . '</article>';
        }
    }
    return $html . '</div>';
});

/** [sgs_kiosk]: ganze Karte; [sgs_kiosk auswahl=3]: die ersten Artikel der ersten Kategorie mit Preis (Startseite). */
add_shortcode('sgs_kiosk', function ($a) {
    $auswahl = (int) ($a['auswahl'] ?? 0);
    $kategorien = sgs_terms_sortiert('sgs_kioskkategorie');
    $artikel = fn($term) => sgs_beitraege('sgs_kiosk', ['tax_query' => [['taxonomy' => 'sgs_kioskkategorie', 'terms' => $term->term_id]]]);
    if ($auswahl) {
        $html = '<ul class="price-list">';
        foreach (array_slice($kategorien ? $artikel($kategorien[0]) : [], 0, $auswahl) as $d) {
            $html .= '<li><span>' . esc_html(get_the_title($d)) . '</span><b>CHF ' . esc_html((string) get_post_meta($d->ID, 'preis', true)) . '</b></li>';
        }
        return $html . '</ul>';
    }
    $html = '<div class="menu-card">';
    foreach ($kategorien as $k) {
        $icon = (string) get_term_meta($k->term_id, 'icon', true);
        $html .= '<section class="menu-cat"><h2>' . ($icon ? '<span class="menu-ico">' . sgs_icon($icon) . '</span>' : '') . esc_html($k->name) . '</h2><ul class="dishes">';
        foreach ($artikel($k) as $d) {
            $foto   = get_the_post_thumbnail_url($d, 'thumbnail');
            $zusatz = (string) get_post_meta($d->ID, 'zusatz', true);
            $html .= '<li' . ($foto ? '' : ' class="no-photo"') . '>' . ($foto ? sgs_foto($foto, get_the_title($d), '1 / 1') : '')
                . '<div><b>' . esc_html(get_the_title($d)) . '</b>' . ($zusatz ? '<small>' . esc_html($zusatz) . '</small>' : '') . '</div>'
                . '<span class="price">' . esc_html((string) get_post_meta($d->ID, 'preis', true)) . '</span></li>';
        }
        $html .= '</ul></section>';
    }
    return $html . '<p class="note">Preise in CHF.</p></div>';
});

/** [sgs_kiosk_zeiten]: Öffnungszeiten aus Werte «kiosk.zeiten» (Zeilen «Tage | Zeit»). */
add_shortcode('sgs_kiosk_zeiten', function () {
    $html = '<dl class="hours">';
    foreach (preg_split('/\R/', sgs_wert('kiosk.zeiten')) as $zeile) {
        if (trim($zeile) !== '') {
            [$tage, $zeit] = array_pad(array_map('trim', explode('|', $zeile, 2)), 2, '');
            $html .= '<dt>' . esc_html($tage) . '</dt><dd>' . esc_html($zeit) . '</dd>';
        }
    }
    return $html . '</dl>';
});

// ---------- Berichte (normale WordPress-Beiträge) ----------

/** Erste Kategorie eines Berichts (wie der erste Tag im statischen Entwurf). */
function sgs_bericht_kategorie(WP_Post $post): string {
    $cats = get_the_category($post->ID);
    return $cats ? $cats[0]->name : '';
}

/** Karte mit Bild, Datum, Kategorie, Titel und Anriss. */
function sgs_bericht_karte(WP_Post $post, bool $anriss = true): string {
    $bild = get_the_post_thumbnail_url($post, 'medium_large');
    $text = $anriss ? get_the_excerpt($post) : '';
    return '<article class="report-card"><a class="report-card-link" href="' . esc_url(get_permalink($post)) . '">'
        . ($bild ? '<img src="' . esc_url($bild) . '" alt="" loading="lazy">' : '<div class="report-card-ph" aria-hidden="true">' . sgs_icon('newspaper') . '</div>')
        . '<div class="report-card-body"><p class="meta"><time datetime="' . esc_attr(get_the_date('Y-m-d', $post)) . '">' . esc_html(get_the_date('d.m.Y', $post)) . '</time>'
        . '<span class="tag">' . esc_html(sgs_bericht_kategorie($post)) . '</span></p>'
        . '<h3>' . esc_html(get_the_title($post)) . '</h3>' . ($text ? '<p>' . esc_html($text) . '</p>' : '') . '</div></a></article>';
}

/** [sgs_berichte anzahl=3 ab=1]: Karten der neuesten Berichte (ohne umgebenden Container). */
add_shortcode('sgs_berichte', function ($a) {
    $a = shortcode_atts(['anzahl' => 3, 'ab' => 1, 'kategorie' => ''], $a);
    $posts = get_posts(['numberposts' => (int) $a['anzahl'], 'offset' => max(0, (int) $a['ab'] - 1), 'category_name' => $a['kategorie']]);
    return implode('', array_map(fn($p) => sgs_bericht_karte($p), $posts));
});

/** [sgs_berichte_archiv ab=4]: alle weiteren Berichte als Liste nach Jahr. */
add_shortcode('sgs_berichte_archiv', function ($a) {
    // Mit «offset» braucht es eine feste Höchstzahl (bei -1 ignoriert WordPress den Versatz).
    $posts = get_posts(['numberposts' => 9999, 'offset' => max(0, (int) ($a['ab'] ?? 1) - 1)]);
    $jahre = [];
    foreach ($posts as $p) {
        $jahre[get_the_date('Y', $p)][] = $p;
    }
    $html = '';
    foreach ($jahre as $jahr => $liste) {
        $html .= '<section class="report-year"><h3>' . esc_html((string) $jahr) . '</h3><ul class="report-list">';
        foreach ($liste as $p) {
            $html .= '<li><time datetime="' . esc_attr(get_the_date('Y-m-d', $p)) . '">' . esc_html(get_the_date('d.m.Y', $p)) . '</time>'
                . '<a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a><span class="tag">' . esc_html(sgs_bericht_kategorie($p)) . '</span></li>';
        }
        $html .= '</ul></section>';
    }
    return $html;
});

/** [sgs_berichte_anzahl] -> «58 Berichte seit 2020» */
add_shortcode('sgs_berichte_anzahl', function () {
    $anzahl = (int) wp_count_posts()->publish;
    $erster = get_posts(['numberposts' => 1, 'order' => 'ASC']);
    return $anzahl ? esc_html($anzahl . ' Berichte seit ' . get_the_date('Y', $erster[0])) : '';
});

/** [sgs_presse]: Berichte der Kategorie «Medien». Titel «Zeitung – Titel», PDF aus dem Feld «pdf» oder dem ersten PDF-Link im Bericht. */
add_shortcode('sgs_presse', function () {
    $html = '<ul class="press">';
    foreach (get_posts(['numberposts' => -1, 'category_name' => 'medien']) as $p) {
        $teile  = explode(' – ', get_the_title($p), 2);
        $zeitung = count($teile) === 2 ? $teile[0] : '';
        $titel  = count($teile) === 2 ? $teile[1] : $teile[0];
        $pdf    = (string) get_post_meta($p->ID, 'pdf', true);
        if (!$pdf && preg_match('/href="([^"]+\.pdf)"/i', $p->post_content, $m)) {
            $pdf = $m[1];
        }
        $html .= '<li><time datetime="' . esc_attr(get_the_date('Y-m-d', $p)) . '">' . esc_html(get_the_date('d.m.Y', $p)) . '</time>'
            . '<div><b><a href="' . esc_url(get_permalink($p)) . '">' . esc_html($titel) . '</a></b><small>' . esc_html($zeitung) . '</small></div>'
            . ($pdf ? '<a class="textlink" href="' . esc_url($pdf) . '">' . sgs_icon('file-text') . 'PDF</a>' : '<span></span>') . '</li>';
    }
    return $html . '</ul>';
});
