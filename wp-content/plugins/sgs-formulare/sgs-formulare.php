<?php
/**
 * Plugin Name:       SGS Formulare
 * Description:       Formulare der Segelfluggruppe Schaffhausen (Kontakt, Schnupperflug, Mitgliedschaft, PPR, Safety-Meldung, Gutschein): Versand an die zuständige Person, Ablage im Admin, Spam-Schutz ohne Google, optional SMTP.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Segelfluggruppe Schaffhausen
 * License:           GPL-2.0-or-later
 * Text Domain:       sgs
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/formular.php';
require_once __DIR__ . '/includes/versand.php';
require_once __DIR__ . '/includes/anfragen.php';
require_once __DIR__ . '/includes/einstellungen.php';

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('sgs_anfragen_aufraeumen')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sgs_anfragen_aufraeumen');
    }
});
register_deactivation_hook(__FILE__, fn() => wp_clear_scheduled_hook('sgs_anfragen_aufraeumen'));

/** Formulare: Kürzel im Shortcode => Name (Betreff der E-Mail, Schlüssel der Empfänger). */
const SGS_FORMULARE = [
    'kontakt'   => 'Kontakt',
    'schnupper' => 'Schnupperflug',
    'mitglied'  => 'Mitgliedschaft',
    'ppr'       => 'PPR-Anfrage',
    'safety'    => 'Safety-Meldung',
    'gutschein' => 'Gutschein',
];

/** Einstellungen mit Standardwerten. */
function sgs_formulare_einstellungen(): array {
    $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'schmerlat.ch';
    $e = get_option('sgs_formulare_einstellungen', []);
    return wp_parse_args(is_array($e) ? $e : [], [
        'absender'      => 'webseite@' . preg_replace('/^www\./', '', $host),
        'absender_name' => 'Webseite SGS',
        'aufbewahren'   => 365,
        'smtp_host'     => '', 'smtp_port' => 587, 'smtp_sicherheit' => 'tls', 'smtp_benutzer' => '', 'smtp_passwort' => '',
    ]);
}

/** Empfänger eines Formulars: Kürzel einer Person (SGS › Personen) oder eine E-Mail-Adresse. */
function sgs_formular_empfaenger(string $name): string {
    $zuordnung = (array) get_option('sgs_formulare_empfaenger', []);
    $wer = (string) ($zuordnung[$name] ?? ($zuordnung['Kontakt'] ?? ''));
    if (is_email($wer)) {
        return $wer;
    }
    if ($wer !== '' && function_exists('sgs_person') && ($p = sgs_person($wer)) && is_email($p['email'])) {
        return $p['email'];
    }
    return (string) get_option('admin_email');
}
