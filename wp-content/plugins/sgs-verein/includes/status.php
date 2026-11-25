<?php
// «Flugbetrieb heute»: im Dashboard setzen, auf jeder Seite sichtbar (Statusanzeige im Kopf und «Schmerlat jetzt»).
// Grün erscheint nur, wenn heute gemeldet wurde: ein vergessenes Update vom Vortag zeigt nie fälschlich «Flugbetrieb».

defined('ABSPATH') || exit;

function sgs_status(): array {
    $s = get_option('sgs_status', []);
    return wp_parse_args(is_array($s) ? $s : [], [
        'active' => false, 'short' => 'Heute Flugbetrieb', 'text' => '', 'updated' => '1970-01-01T00:00:00+00:00',
    ]);
}

// Öffentlich lesbar für site.js: /wp-json/sgs/v1/status (gleiches Format wie data/status.json im statischen Entwurf).
add_action('rest_api_init', function () {
    register_rest_route('sgs/v1', '/status', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function () {
            $r = rest_ensure_response(sgs_status());
            $r->header('Cache-Control', 'no-store');
            return $r;
        },
    ]);
});

add_action('wp_dashboard_setup', function () {
    if (current_user_can('edit_pages')) {
        wp_add_dashboard_widget('sgs_status', 'Flugbetrieb heute', 'sgs_status_widget');
        // Ganz nach oben.
        global $wp_meta_boxes;
        $w = $wp_meta_boxes['dashboard']['normal']['core']['sgs_status'] ?? null;
        if ($w) {
            unset($wp_meta_boxes['dashboard']['normal']['core']['sgs_status']);
            $wp_meta_boxes['dashboard']['normal']['high']['sgs_status'] = $w;
        }
    }
});

function sgs_status_widget(): void {
    $s     = sgs_status();
    $heute = wp_date('Y-m-d') === wp_date('Y-m-d', strtotime($s['updated']));
    $an    = $heute && $s['active'];
    echo '<p>Angezeigt wird jetzt: <b style="color:' . ($an ? '#1d7348' : '#c2352b') . '">' . esc_html($an ? $s['short'] : 'Heute kein Flugbetrieb') . '</b>';
    if ($heute) {
        echo ' (gemeldet ' . esc_html(wp_date('H:i', strtotime($s['updated']))) . ' Uhr)';
    }
    echo '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('sgs_status');
    echo '<input type="hidden" name="action" value="sgs_status">';
    echo '<p><label for="sgs_status_text">Hinweis für heute</label><br><input class="widefat" id="sgs_status_text" name="text" value="' . esc_attr($heute ? $s['text'] : 'Flugbetrieb ab 10 Uhr. Kiosk geöffnet.') . '"></p>';
    echo '<p><button class="button button-primary" name="active" value="1">Heute Flugbetrieb melden</button> ';
    echo '<button class="button" name="active" value="0">Heute kein Flugbetrieb</button></p>';
    echo '<p class="description">Gilt nur für heute. Morgen steht die Anzeige automatisch wieder auf «kein Flugbetrieb», bis jemand neu meldet.</p></form>';
}

add_action('admin_post_sgs_status', function () {
    if (!current_user_can('edit_pages') || !check_admin_referer('sgs_status')) {
        wp_die('Keine Berechtigung.');
    }
    $active = ($_POST['active'] ?? '') === '1';
    update_option('sgs_status', [
        'active'  => $active,
        'short'   => 'Heute Flugbetrieb',
        'text'    => sanitize_text_field(wp_unslash($_POST['text'] ?? '')),
        'updated' => wp_date('c'),
    ]);
    wp_safe_redirect(admin_url('index.php?sgs_status=1'));
    exit;
});
