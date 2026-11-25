<?php
// Theme «SGS»: Kopf, Navigation und Fuss wie im statischen Entwurf. Inhalte und Werte liefert das Plugin «SGS Verein».

defined('ABSPATH') || exit;

const SGS_THEME_VERSION = '0.1.0';

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_editor_style(['assets/css/tokens.css', 'assets/css/editor.css']);
    register_nav_menus(['haupt' => 'Hauptnavigation (zwei Ebenen, Beschreibung erscheint im Aufklappmenü)']);
});

// Ohne Plugin «SGS Verein» fehlen Werte, Icons und Listen.
if (!function_exists('sgs_wert')) {
    add_action('admin_notices', fn() => print '<div class="notice notice-error"><p>Das Theme «SGS» benötigt das Plugin «SGS Verein». Bitte unter Plugins aktivieren.</p></div>');
    function sgs_wert(string $k, string $d = ''): string { return $d; }
    function sgs_icon(string $n, string $c = 'icon'): string { return ''; }
    function sgs_hauptkontakt(): ?array { return null; }
    function sgs_fest(): array { return ['year' => (int) date('Y'), 'label' => '']; }
    function sgs_stripes(): string { return ''; }
}

add_action('wp_enqueue_scripts', function () {
    $uri = get_template_directory_uri() . '/assets';
    wp_enqueue_style('sgs-tokens', "$uri/css/tokens.css", [], SGS_THEME_VERSION);
    wp_enqueue_style('sgs-site', "$uri/css/site.css", ['sgs-tokens'], SGS_THEME_VERSION);
    wp_enqueue_style('sgs-wp', "$uri/css/wordpress.css", ['sgs-site'], SGS_THEME_VERSION);
    wp_enqueue_script('sgs-site', "$uri/js/site.js", [], SGS_THEME_VERSION, ['strategy' => 'defer', 'in_footer' => false]);
    // Unnötiges aus dem Kopf (Emojis, globale Block-Stile der Startseite).
    if (!is_singular('post')) {
        wp_dequeue_style('global-styles');
    }
});

// site.js: Stammverzeichnis und Adresse des Flugbetrieb-Status.
add_filter('script_loader_tag', function ($tag, $handle) {
    if ($handle !== 'sgs-site') {
        return $tag;
    }
    return str_replace(' src=', ' data-root="' . esc_url(home_url('/')) . '" data-status="' . esc_url(rest_url('sgs/v1/status')) . '" src=', $tag);
}, 10, 2);

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_generator');

add_action('wp_head', function () {
    $uri = get_template_directory_uri() . '/assets';
    echo '<link rel="icon" href="' . esc_url("$uri/img/logo-sgs.png") . '">' . "\n";
    echo '<link rel="preload" href="' . esc_url("$uri/fonts/source-sans-3-latin-wght-normal.woff2") . '" as="font" type="font/woff2" crossorigin>' . "\n";
    $beschreibung = is_singular() ? (string) get_post_meta(get_the_ID(), '_sgs_description', true) : '';
    if ($beschreibung === '' && is_singular('post')) {
        $beschreibung = get_the_excerpt();
    }
    if ($beschreibung === '') {
        $beschreibung = sgs_wert('site.description', get_bloginfo('description'));
    }
    echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($beschreibung)) . '">' . "\n";
}, 1);

// Seitentitel «Titel – SG Schaffhausen», Startseite «Segelfluggruppe Schaffhausen – Flugplatz Schmerlat».
add_filter('document_title_separator', fn() => '–');
add_filter('document_title_parts', function ($teile) {
    if (is_front_page()) {
        return ['title' => sgs_wert('site.name', get_bloginfo('name')), 'tagline' => 'Flugplatz Schmerlat'];
    }
    $teile['site'] = sgs_wert('site.shortName', get_bloginfo('name'));
    unset($teile['tagline']);
    return $teile;
});

// Feld «Rubrik» der Seite (Navigation markieren): besuchen, piloten, lernen, verein, fest.
function sgs_rubrik(): string {
    if (is_singular('post') || is_category() || is_home()) {
        return 'verein';
    }
    return is_page() ? (string) get_post_meta(get_the_ID(), '_sgs_section', true) : '';
}

require_once __DIR__ . '/inc/navigation.php';
require_once __DIR__ . '/inc/seiten.php';
