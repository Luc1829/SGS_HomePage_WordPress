<?php
// Zusatzfelder für Seiten: Rubrik (markiert die Navigation) und Beschreibung für Suchmaschinen.

defined('ABSPATH') || exit;

const SGS_RUBRIKEN = ['' => '– keine –', 'besuchen' => 'Besuchen', 'piloten' => 'Für Piloten', 'lernen' => 'Fliegen lernen', 'verein' => 'Verein', 'fest' => 'Flüüger-Fäscht'];

add_action('init', function () {
    foreach (['_sgs_section', '_sgs_description'] as $key) {
        register_post_meta('page', $key, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'auth_callback' => fn() => current_user_can('edit_pages'),
        ]);
    }
});

add_action('add_meta_boxes_page', function () {
    add_meta_box('sgs_seite', 'Seite (SGS)', function (WP_Post $p) {
        wp_nonce_field('sgs_seite', 'sgs_seite_nonce');
        $rubrik = (string) get_post_meta($p->ID, '_sgs_section', true);
        echo '<p><label for="sgs_rubrik"><b>Rubrik</b></label><br><select id="sgs_rubrik" name="sgs_rubrik" style="width:100%">';
        foreach (SGS_RUBRIKEN as $k => $l) {
            echo '<option value="' . esc_attr($k) . '"' . selected($rubrik, $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></p><p><label for="sgs_beschreibung"><b>Beschreibung für Suchmaschinen</b></label><br>';
        echo '<textarea id="sgs_beschreibung" name="sgs_beschreibung" rows="4" style="width:100%">' . esc_textarea((string) get_post_meta($p->ID, '_sgs_description', true)) . '</textarea></p>';
    }, 'page', 'side');
});

add_action('save_post_page', function ($id) {
    if (!isset($_POST['sgs_seite_nonce']) || !wp_verify_nonce(sanitize_key($_POST['sgs_seite_nonce']), 'sgs_seite') || !current_user_can('edit_page', $id)) {
        return;
    }
    $rubrik = sanitize_key(wp_unslash($_POST['sgs_rubrik'] ?? ''));
    update_post_meta($id, '_sgs_section', array_key_exists($rubrik, SGS_RUBRIKEN) ? $rubrik : '');
    update_post_meta($id, '_sgs_description', sanitize_textarea_field(wp_unslash($_POST['sgs_beschreibung'] ?? '')));
});
