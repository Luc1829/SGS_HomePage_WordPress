<?php
// Startseite: wie jede Seite (Einstellungen › Lesen › Startseite).
get_header();
while (have_posts()) {
    the_post();
    if (!has_shortcode(get_the_content(), 'sgs_seitenkopf') && !is_front_page()) {
        echo do_shortcode('[sgs_seitenkopf]');
        echo '<section class="section"><div class="wrap prose">';
        the_content();
        echo '</div></section>';
    } else {
        the_content();
    }
}
get_footer();
