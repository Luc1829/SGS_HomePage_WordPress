<?php
/**
 * Plugin Name:       SGS Verein
 * Description:       Inhalte der Segelfluggruppe Schaffhausen: Termine, Flugzeugpark, Personen, Kiosk, Werte & Preise, «Flugbetrieb heute» und der Import aus dem statischen Entwurf.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Segelfluggruppe Schaffhausen
 * License:           GPL-2.0-or-later
 * Text Domain:       sgs
 */

defined('ABSPATH') || exit;

define('SGS_VEREIN_DIR', __DIR__);
define('SGS_VEREIN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/includes/werte.php';
require_once __DIR__ . '/includes/typen.php';
require_once __DIR__ . '/includes/felder.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/status.php';
require_once __DIR__ . '/includes/shortcodes.php';
require_once __DIR__ . '/includes/import.php';

register_activation_hook(__FILE__, function () {
    sgs_register_types();
    flush_rewrite_rules();
});
