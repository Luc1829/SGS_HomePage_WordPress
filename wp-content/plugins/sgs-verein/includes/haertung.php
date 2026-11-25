<?php
// Härtung: Benutzernamen nicht öffentlich preisgeben, keine Anmeldung über XML-RPC, Sicherheits-Header.

defined('ABSPATH') || exit;

// Keine Benutzerliste für Besucher (/wp-json/wp/v2/users verrät sonst die Anmeldenamen der Autoren).
add_filter('rest_endpoints', function ($endpunkte) {
    if (!is_user_logged_in()) {
        unset($endpunkte['/wp/v2/users'], $endpunkte['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpunkte;
});

// Keine Autorenseiten (/?author=1 leitet sonst auf /author/<anmeldename>/ weiter) und keine Autoren in der Sitemap.
add_filter('wp_sitemaps_add_provider', fn($provider, $name) => $name === 'users' ? false : $provider, 10, 2);
add_action('template_redirect', function () {
    if (is_author() || isset($_GET['author'])) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 1); // vor redirect_canonical, das sonst auf /author/<anmeldename>/ weiterleitet

// Anmeldung über XML-RPC abschalten (Passwort-Durchprobieren mit system.multicall). Die Seite braucht XML-RPC nicht.
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', fn($methoden) => array_diff_key($methoden, ['system.multicall' => true, 'pingback.ping' => true]));

// Sicherheits-Header auf der öffentlichen Seite. Die Content-Security-Policy zuerst nur melden lassen
// (Browser-Konsole), nach Prüfung im Betrieb «-Report-Only» entfernen.
add_action('send_headers', function () {
    if (is_admin()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), payment=()');
    header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: https:; font-src 'self'; connect-src 'self' https://api.open-meteo.com; frame-src 'self' https:; "
        . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
});
