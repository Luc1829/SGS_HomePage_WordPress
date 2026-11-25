<?php
// Anfragen › Einstellungen: Empfänger je Formular, Absender, Aufbewahrung, SMTP, Testmail.

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=sgs_anfrage', 'Formular-Einstellungen', 'Einstellungen', 'manage_options', 'sgs-formulare', 'sgs_formulare_seite');
});

function sgs_formulare_seite(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    $e = sgs_formulare_einstellungen();
    $empf = (array) get_option('sgs_formulare_empfaenger', []);
    $namen = array_merge(array_values(SGS_FORMULARE), ['Mitgliedschaft Passiv']);

    if (isset($_POST['sgs_formulare_nonce']) && wp_verify_nonce(sanitize_key($_POST['sgs_formulare_nonce']), 'sgs_formulare')) {
        $in = wp_unslash($_POST);
        foreach ($namen as $n) {
            $v = trim((string) ($in['empf'][$n] ?? ''));
            $empf[$n] = is_email($v) ? sanitize_email($v) : sanitize_title($v);
        }
        update_option('sgs_formulare_empfaenger', $empf);
        $e['absender']        = sanitize_email($in['absender'] ?? '');
        $e['absender_name']   = sanitize_text_field($in['absender_name'] ?? '');
        $e['aufbewahren']     = max(0, (int) ($in['aufbewahren'] ?? 365));
        $e['smtp_host']       = sanitize_text_field($in['smtp_host'] ?? '');
        $e['smtp_port']       = (int) ($in['smtp_port'] ?? 587);
        $e['smtp_sicherheit'] = in_array($in['smtp_sicherheit'] ?? '', ['tls', 'ssl'], true) ? $in['smtp_sicherheit'] : 'tls';
        $e['smtp_benutzer']   = sanitize_text_field($in['smtp_benutzer'] ?? '');
        if (($in['smtp_passwort'] ?? '') !== '') {
            $e['smtp_passwort'] = (string) $in['smtp_passwort'];
        }
        update_option('sgs_formulare_einstellungen', $e, false);
        echo '<div class="notice notice-success"><p>Gespeichert.</p></div>';

        if (!empty($in['testmail'])) {
            $an = wp_get_current_user()->user_email;
            $ok = wp_mail($an, 'Testmail der Webseite SGS', "Der E-Mail-Versand der Webseite funktioniert.\n\nAbsender: {$e['absender']}");
            echo $ok ? '<div class="notice notice-success"><p>Testmail an ' . esc_html($an) . ' verschickt. Kommt sie nicht an, auch im Spam-Ordner nachsehen.</p></div>'
                     : '<div class="notice notice-error"><p>Testmail konnte nicht verschickt werden. SMTP-Angaben prüfen.</p></div>';
        }
    }

    $personen = function_exists('sgs_beitraege') ? sgs_beitraege('sgs_person') : [];
    echo '<div class="wrap"><h1>Formular-Einstellungen</h1><form method="post">';
    wp_nonce_field('sgs_formulare', 'sgs_formulare_nonce');

    echo '<h2>Wer erhält welches Formular?</h2><p>Person aus SGS › Personen (die E-Mail-Adresse kommt von dort) oder direkt eine E-Mail-Adresse.</p><table class="form-table" role="presentation">';
    foreach ($namen as $n) {
        $wert = (string) ($empf[$n] ?? '');
        echo '<tr><th scope="row">' . esc_html($n) . '</th><td><input list="sgs-personen" class="regular-text" name="empf[' . esc_attr($n) . ']" value="' . esc_attr($wert) . '">';
        echo ' <span class="description">→ ' . esc_html(sgs_formular_empfaenger($n)) . '</span></td></tr>';
    }
    echo '</table><datalist id="sgs-personen">';
    foreach ($personen as $p) {
        echo '<option value="' . esc_attr($p->post_name) . '">' . esc_html($p->post_title) . '</option>';
    }
    echo '</datalist>';

    $feld = fn($name, $label, $wert, $typ = 'text', $hilfe = '') => '<tr><th scope="row"><label for="' . $name . '">' . $label . '</label></th><td><input class="regular-text" type="' . $typ . '" id="' . $name . '" name="' . $name . '" value="' . esc_attr((string) $wert) . '">' . ($hilfe ? '<p class="description">' . $hilfe . '</p>' : '') . '</td></tr>';

    echo '<h2>Absender und Aufbewahrung</h2><table class="form-table" role="presentation">';
    echo $feld('absender', 'Absender-Adresse', $e['absender'], 'email', 'Eine Adresse auf der eigenen Domain (z. B. webseite@schmerlat.ch), sonst landen die E-Mails im Spam.');
    echo $feld('absender_name', 'Absender-Name', $e['absender_name']);
    echo $feld('aufbewahren', 'Anfragen aufbewahren (Tage)', $e['aufbewahren'], 'number', 'Danach werden sie automatisch gelöscht. 0 = nie löschen.');
    echo '</table>';

    echo '<h2>E-Mail-Versand über SMTP (empfohlen)</h2><p>Die Zugangsdaten eines Postfachs beim Hoster, z. B. bei Infomaniak <code>mail.infomaniak.com</code>, Port 587, TLS. Leer lassen = Versand über den Webserver.</p><table class="form-table" role="presentation">';
    echo $feld('smtp_host', 'Server', $e['smtp_host']);
    echo $feld('smtp_port', 'Port', $e['smtp_port'], 'number');
    echo '<tr><th scope="row"><label for="smtp_sicherheit">Verschlüsselung</label></th><td><select id="smtp_sicherheit" name="smtp_sicherheit">';
    foreach (['tls' => 'TLS (Port 587)', 'ssl' => 'SSL (Port 465)'] as $k => $l) {
        echo '<option value="' . $k . '"' . selected($e['smtp_sicherheit'], $k, false) . '>' . $l . '</option>';
    }
    echo '</select></td></tr>';
    echo $feld('smtp_benutzer', 'Benutzer', $e['smtp_benutzer']);
    if (defined('SGS_SMTP_PASSWORT')) {
        echo '<tr><th scope="row">Passwort</th><td>in wp-config.php hinterlegt</td></tr>';
    } else {
        echo $feld('smtp_passwort', 'Passwort', '', 'password', $e['smtp_passwort'] !== '' ? 'Hinterlegt. Leer lassen, um es zu behalten. Sicherer: in wp-config.php <code>define(\'SGS_SMTP_PASSWORT\', \'…\');</code>' : 'Sicherer: in wp-config.php <code>define(\'SGS_SMTP_PASSWORT\', \'…\');</code>');
    }
    echo '</table><p class="submit">';
    submit_button('Speichern', 'primary', 'speichern', false);
    echo ' ';
    submit_button('Speichern und Testmail an mich senden', 'secondary', 'testmail', false);
    echo '</p></form></div>';
}
