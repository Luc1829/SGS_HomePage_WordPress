<?php
// Werte & Preise, Personen und Datumsfunktionen. Jede Angabe steht nur an einer Stelle:
// Werte in der Option «sgs_werte» (SGS › Werte & Preise), Personen als Beiträge vom Typ «sgs_person».

defined('ABSPATH') || exit;

/** Alle Werte als flache Liste: 'angebote.schnupperflug.preis' => '150'. */
function sgs_werte(): array {
    $werte = get_option('sgs_werte', []);
    return is_array($werte) ? $werte : [];
}

/** Ein Wert. Schlüssel «person.<kürzel>.<feld>» liest aus den Personen (name, title, email). */
function sgs_wert(string $key, string $default = ''): string {
    if (str_starts_with($key, 'person.')) {
        [, $kuerzel, $feld] = array_pad(explode('.', $key, 3), 3, '');
        $p = sgs_person($kuerzel);
        return $p ? (string) ($p[$feld] ?? $default) : $default;
    }
    $werte = sgs_werte();
    return isset($werte[$key]) ? (string) $werte[$key] : $default;
}

/** Betrag nach Design-System: 150 -> «CHF 150.–», 6.2 -> «CHF 6.20». */
function sgs_chf($n): string {
    $n = (float) str_replace([',', "'"], ['.', ''], (string) $n);
    return floor($n) == $n ? 'CHF ' . number_format($n, 0, '.', "'") . '.–' : 'CHF ' . number_format($n, 2, '.', "'");
}

/** Telefonnummer für tel:-Links: «052 681 20 90» -> «+41526812090». */
function sgs_tel(string $nummer): string {
    $n = preg_replace('/[^\d+]/', '', $nummer);
    return str_starts_with($n, '0') ? '+41' . substr($n, 1) : $n;
}

/** Person nach Kürzel (Titelform der Adresse, z. B. «praesident»). */
function sgs_person(string $kuerzel): ?array {
    static $cache = [];
    if (array_key_exists($kuerzel, $cache)) {
        return $cache[$kuerzel];
    }
    $post = get_page_by_path($kuerzel, OBJECT, 'sgs_person');
    return $cache[$kuerzel] = $post ? sgs_person_daten($post) : null;
}

function sgs_person_daten(WP_Post $post): array {
    $phones = [];
    foreach (preg_split('/\R/', (string) get_post_meta($post->ID, 'telefon', true)) as $zeile) {
        if (trim($zeile) === '') {
            continue;
        }
        [$label, $nummer] = array_pad(array_map('trim', explode('|', $zeile, 2)), 2, '');
        if ($nummer === '') {
            [$label, $nummer] = ['Tel.', $label];
        }
        $phones[] = ['label' => $label, 'number' => $nummer, 'href' => 'tel:' . sgs_tel($nummer)];
    }
    return [
        'key'    => $post->post_name,
        'name'   => $post->post_title,
        'title'  => (string) get_post_meta($post->ID, 'funktion', true),
        'email'  => (string) get_post_meta($post->ID, 'email', true),
        'phones' => $phones,
        'photo'  => get_the_post_thumbnail_url($post, 'medium'),
        'id'     => $post->ID,
    ];
}

/** Hauptkontakt (Vereinsadresse im Fuss): Person aus Werte «site.allgemein», sonst «praesident». */
function sgs_hauptkontakt(): ?array {
    return sgs_person(sgs_wert('site.allgemein', 'praesident'));
}

/** Flüüger-Fäscht: erstes Septemberwochenende; nach dem Fest automatisch das nächste Jahr. */
function sgs_fest(): array {
    $tz  = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $wochenende = function (int $y) use ($tz) {
        $d = new DateTimeImmutable("$y-09-01", $tz);
        while ((int) $d->format('w') !== 6) {
            $d = $d->modify('+1 day');
        }
        return $d;
    };
    $y   = (int) $now->format('Y');
    $sat = $wochenende($y);
    if ($now > $sat->modify('+1 day')->setTime(23, 59)) {
        $sat = $wochenende(++$y);
    }
    $sun = $sat->modify('+1 day');
    return ['year' => $y, 'label' => $sat->format('j') . './' . $sun->format('j') . '. September ' . $y];
}

const SGS_MONATE      = ['Jan.', 'Feb.', 'März', 'April', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'];
const SGS_MONATE_LANG = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const SGS_TAGE        = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

/** Datumsteile aus «2026-09-05». */
function sgs_datum_teile(string $iso): array {
    $t = strtotime($iso . ' 12:00:00');
    $m = (int) date('n', $t) - 1;
    return [
        'day' => (int) date('j', $t), 'month' => SGS_MONATE[$m], 'monthLong' => SGS_MONATE_LANG[$m],
        'weekday' => SGS_TAGE[(int) date('w', $t)], 'year' => (int) date('Y', $t),
    ];
}

/** Datum nach Design-System: 09.11.2026 */
function sgs_datum(string $iso): string {
    return date('d.m.Y', strtotime($iso . ' 12:00:00'));
}

/** Lucide-Icon aus dem Theme (assets/icons/<name>.svg), inline. */
function sgs_icon(string $name, string $class = 'icon'): string {
    static $cache = [];
    if (!isset($cache[$name])) {
        $file = get_template_directory() . '/assets/icons/' . sanitize_file_name($name) . '.svg';
        $svg  = is_readable($file) ? (string) file_get_contents($file) : '';
        $svg  = preg_replace(['/<!--.*?-->\s*/s', '/\s*\n\s*/', '/class="[^"]*"/', '/width="24" height="24"/'], ['', ' ', '', ''], $svg);
        $cache[$name] = trim($svg);
    }
    if ($cache[$name] === '') {
        return '';
    }
    return preg_replace('/<svg/', '<svg class="' . esc_attr($class) . '" aria-hidden="true" focusable="false"', $cache[$name], 1);
}
