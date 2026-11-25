<?php
// [sgs_formular name=ppr]<form class="form" …>…</form>[/sgs_formular]
// Das Formular-Markup steht in der Seite und kann dort bearbeitet werden. Der Shortcode ergänzt
// Ziel-Adresse, Spam-Schutz und Fallback-Adresse und merkt sich die Feldbeschriftungen für die E-Mail.

defined('ABSPATH') || exit;

/** Zeitstempel mit Signatur: zu schnelles Absenden (Bots) und gefälschte Formulare erkennen. */
function sgs_formular_token(string $name): string {
    $t = (string) time();
    return $t . '.' . substr(wp_hash("sgs_formular|$name|$t", 'nonce'), 0, 20);
}

function sgs_formular_token_ok(string $name, string $token): bool|string {
    [$t, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if (!ctype_digit($t) || !hash_equals(substr(wp_hash("sgs_formular|$name|$t", 'nonce'), 0, 20), $sig)) {
        return 'token';
    }
    // Kein Ablaufdatum: Seiten können beim Hoster zwischengespeichert sein.
    return time() - (int) $t < 3 ? 'zu_schnell' : true;
}

/** Feldnamen und Beschriftungen aus dem Markup lesen (für lesbare E-Mails und als Liste erlaubter Felder). */
function sgs_formular_felder_lesen(string $html): array {
    $felder = [];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);
    $text = fn($n) => trim(preg_replace('/\s+/u', ' ', str_replace('*', '', $n->textContent)));
    foreach ($xp->query('//input|//select|//textarea') as $el) {
        $name = $el->getAttribute('name');
        if ($name === '' || str_starts_with($name, '_') || in_array($name, ['website', 'kopie'], true) || isset($felder[$name])) {
            continue;
        }
        $label = '';
        if ($id = $el->getAttribute('id')) {
            $l = $xp->query('//label[@for="' . $id . '"]');
            $label = $l->length ? $text($l->item(0)) : '';
        }
        if ($label === '' && in_array($el->getAttribute('type'), ['radio', 'checkbox'], true)) {
            // Auswahl: Beschriftung der Gruppe (.label) oder bei einzelnen Häkchen der Text daneben.
            $gruppe = $xp->query('ancestor::div[contains(@class,"field")][1]//*[contains(@class,"label")]', $el);
            $label = $gruppe->length ? $text($gruppe->item(0)) : mb_strimwidth($text($el->parentNode), 0, 60, '…');
        }
        $felder[$name] = $label ?: ucfirst($name);
    }
    return $felder;
}

add_shortcode('sgs_formular', function ($atts, $inhalt = '') {
    $name = sanitize_key($atts['name'] ?? '');
    $inhalt = do_shortcode((string) $inhalt); // Werte im Formular, z. B. Preise in der Auswahl
    if (!isset(SGS_FORMULARE[$name]) || !str_contains((string) $inhalt, '<form')) {
        return current_user_can('edit_pages') ? '<p class="form-msg err">Formular «' . esc_html($name) . '» unbekannt.</p>' : '';
    }
    $felder = sgs_formular_felder_lesen((string) $inhalt);
    $alle = (array) get_option('sgs_formular_felder', []);
    if (($alle[$name] ?? null) !== $felder) {
        $alle[$name] = $felder;
        update_option('sgs_formular_felder', $alle, false);
    }
    $mail = sgs_formular_empfaenger(SGS_FORMULARE[$name]);
    $zusatz = '<input type="hidden" name="_formular" value="' . esc_attr($name) . '">'
        . '<input type="hidden" name="_t" value="' . esc_attr(sgs_formular_token($name)) . '">'
        . '<div class="hp" aria-hidden="true"><label>Nicht ausfüllen <input name="website" tabindex="-1" autocomplete="off"></label></div>';
    $form = preg_replace(
        '/<form\b([^>]*)>/',
        '<form$1 action="' . esc_url(rest_url('sgs/v1/formular')) . '" data-mail="' . esc_attr($mail) . '" id="formular-' . $name . '">' . $zusatz,
        (string) $inhalt,
        1
    );
    // Rückmeldung ohne JavaScript (nach Weiterleitung).
    if (isset($_GET['gesendet'], $_GET['formular']) && $_GET['formular'] === $name) {
        $ok = $_GET['gesendet'] === '1';
        $msg = $ok ? 'Gesendet. Wir melden uns bei dir.' : 'Senden fehlgeschlagen. Bitte per E-Mail an <a href="mailto:' . esc_attr($mail) . '">' . esc_html($mail) . '</a> schicken.';
        $form = str_replace('</form>', '<p class="form-msg ' . ($ok ? 'ok' : 'err') . '" role="status">' . $msg . '</p></form>', $form);
    }
    return $form;
});
