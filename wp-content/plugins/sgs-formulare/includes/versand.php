<?php
// Empfang der Formulare: POST /wp-json/sgs/v1/formular
// 1. Spam-Schutz (verstecktes Feld, Mindestzeit, Signatur, Begrenzung pro Absender)
// 2. Ablage als «Anfrage» im Admin (geht nichts verloren, auch wenn eine E-Mail nicht ankommt)
// 3. E-Mail an die zuständige Person, auf Wunsch Bestätigung an den Absender

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('sgs/v1', '/formular', [
        'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'sgs_formular_empfangen',
    ]);
});

function sgs_formular_antwort(WP_REST_Request $req, bool $ok, string $name, int $status = 200) {
    // Ohne JavaScript: zurück zur Seite mit Meldung. Mit JavaScript (site.js): JSON.
    if (!str_contains((string) $req->get_header('accept'), 'application/json')) {
        $zurueck = wp_validate_redirect((string) $req->get_header('referer'), home_url('/'));
        $ziel = add_query_arg(['gesendet' => $ok ? '1' : '0', 'formular' => $name], remove_query_arg(['gesendet', 'formular'], $zurueck));
        $r = new WP_REST_Response(null, 303);
        $r->header('Location', $ziel . '#formular-' . $name);
        return $r;
    }
    return new WP_REST_Response(['ok' => $ok], $ok ? 200 : $status);
}

function sgs_formular_empfangen(WP_REST_Request $req) {
    $daten = $req->get_body_params();
    $name  = sanitize_key((string) ($daten['_formular'] ?? ''));
    if (!isset(SGS_FORMULARE[$name])) {
        return sgs_formular_antwort($req, false, $name, 400);
    }
    // Spam-Falle: Menschen sehen das Feld nicht. Bots erhalten eine scheinbar erfolgreiche Antwort.
    if (!empty($daten['website'])) {
        return sgs_formular_antwort($req, true, $name);
    }
    if (sgs_formular_token_ok($name, (string) ($daten['_t'] ?? '')) !== true) {
        return sgs_formular_antwort($req, false, $name, 400);
    }
    // Höchstens 10 Anfragen pro Stunde von derselben Adresse.
    $ip = wp_hash((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'nonce');
    $zaehler = (int) get_transient("sgs_f_$ip");
    if ($zaehler >= 10) {
        return sgs_formular_antwort($req, false, $name, 429);
    }
    set_transient("sgs_f_$ip", $zaehler + 1, HOUR_IN_SECONDS);

    // Nur Felder, die im Formular vorkommen (Beschriftungen aus dem Markup).
    $felder = ((array) get_option('sgs_formular_felder', []))[$name] ?? [];
    $zeilen = [];
    foreach ($felder as $feld => $label) {
        $wert = $daten[$feld] ?? '';
        $wert = is_array($wert) ? implode(', ', array_map('strval', $wert)) : (string) $wert;
        $wert = trim(sanitize_textarea_field(mb_substr($wert, 0, 5000)));
        if ($wert !== '') {
            $zeilen[$label] = $wert;
        }
    }
    if (!$zeilen) {
        return sgs_formular_antwort($req, false, $name, 400);
    }

    $titel  = SGS_FORMULARE[$name];
    $email  = is_email((string) ($daten['email'] ?? '')) ? sanitize_email($daten['email']) : '';
    $absender = trim((string) ($daten['name'] ?? '')) ?: ($email ?: 'anonym');
    $empf   = sgs_formular_empfaenger($titel);
    // Passivmitglieder an die zuständige Person, falls eingetragen.
    if ($name === 'mitglied' && str_contains((string) ($daten['art'] ?? ''), 'Passiv')
        && !empty(((array) get_option('sgs_formulare_empfaenger', []))['Mitgliedschaft Passiv'])) {
        $empf = sgs_formular_empfaenger('Mitgliedschaft Passiv');
    }
    $text = implode("\n", array_map(fn($k, $v) => str_contains($v, "\n") ? "$k:\n$v\n" : "$k: $v", array_keys($zeilen), $zeilen));

    $id = wp_insert_post(wp_slash([
        'post_type' => 'sgs_anfrage', 'post_status' => 'private',
        'post_title' => sanitize_text_field("$titel – $absender"), 'post_content' => $text,
    ]));
    if ($id) {
        update_post_meta($id, 'formular', $name);
        update_post_meta($id, 'email', $email);
        update_post_meta($id, 'empfaenger', $empf);
    }

    $e = sgs_formulare_einstellungen();
    $kopf = ['Content-Type: text/plain; charset=UTF-8'];
    if ($email) {
        $kopf[] = 'Reply-To: ' . $email;
    }
    $fuss = "\n\n--\nGesendet über die Webseite am " . wp_date('d.m.Y \u\m H:i') . ' Uhr.'
        . ($id ? "\nAlle Anfragen im Admin: " . admin_url('edit.php?post_type=sgs_anfrage') : '')
        . ($email ? "\nAntworte direkt auf diese E-Mail, um {$absender} zu schreiben." : '');
    $ok = wp_mail($empf, "Webseite: $titel", $text . $fuss, $kopf);
    if ($id) {
        update_post_meta($id, 'mail_ok', $ok ? '1' : '0');
    }

    // Bestätigung an den Absender. Bewusst ohne die eingegebenen Texte, damit das Formular nicht
    // zum Versand fremder Inhalte an beliebige Adressen missbraucht werden kann.
    if ($email && ($daten['kopie'] ?? '') === 'ja') {
        $bestaetigung = "Guten Tag\n\nVielen Dank für deine Anfrage ($titel) an die Segelfluggruppe Schaffhausen. "
            . "Wir haben sie erhalten und melden uns so rasch wie möglich bei dir."
            . "\n\nFreundliche Grüsse\nSegelfluggruppe Schaffhausen, Flugplatz Schmerlat\n\n"
            . "Diese E-Mail wurde automatisch versendet. Bei Fragen antworte einfach auf diese Nachricht.";
        wp_mail($email, "Bestätigung: $titel", $bestaetigung, ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $empf]);
    }

    // Gespeichert ist gespeichert: Die Anfrage ist im Admin sichtbar, auch wenn die E-Mail nicht rausging.
    return sgs_formular_antwort($req, (bool) ($id || $ok), $name, 500);
}

// Absender aller E-Mails der Webseite: Adresse auf der eigenen Domain, sonst landen sie im Spam.
add_filter('wp_mail_from', fn($alt) => is_email(sgs_formulare_einstellungen()['absender']) ? sgs_formulare_einstellungen()['absender'] : $alt);
add_filter('wp_mail_from_name', fn() => sgs_formulare_einstellungen()['absender_name']);

// SMTP: zuverlässiger als die PHP-Funktion mail(). Passwort am besten in wp-config.php: define('SGS_SMTP_PASSWORT', '…');
add_action('phpmailer_init', function ($mailer) {
    $e = sgs_formulare_einstellungen();
    if ($e['smtp_host'] === '') {
        return;
    }
    $mailer->isSMTP();
    $mailer->Host       = $e['smtp_host'];
    $mailer->Port       = (int) $e['smtp_port'];
    $mailer->SMTPSecure = in_array($e['smtp_sicherheit'], ['tls', 'ssl'], true) ? $e['smtp_sicherheit'] : '';
    $mailer->SMTPAutoTLS = $e['smtp_sicherheit'] !== 'keine';
    $passwort = defined('SGS_SMTP_PASSWORT') ? (string) SGS_SMTP_PASSWORT : (string) $e['smtp_passwort'];
    if ($e['smtp_benutzer'] !== '') {
        $mailer->SMTPAuth = true;
        $mailer->Username = $e['smtp_benutzer'];
        $mailer->Password = $passwort;
    }
});
