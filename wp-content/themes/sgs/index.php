<?php
// Übersichten (Kategorie eines Berichts, Suche) und Ausweichvorlage.
get_header();
$titel = is_search() ? 'Suche: ' . get_search_query() : (is_category() ? single_cat_title('', false) : 'Berichte');
echo do_shortcode('[sgs_seitenkopf rubrik="Berichte & Medien" link="' . esc_url(home_url('/verein/medien/')) . '" titel="' . esc_attr($titel) . '"][/sgs_seitenkopf]');
?>
<section class="section">
  <div class="wrap">
    <?php if (have_posts()) : ?>
      <div class="report-cards"><?php while (have_posts()) { the_post(); echo function_exists('sgs_bericht_karte') ? sgs_bericht_karte(get_post()) : '<h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>'; } ?></div>
      <div class="report-nav"><?php echo get_previous_posts_link('Neuere') ?: '<span></span>'; ?><?php echo get_next_posts_link('Ältere') ?: '<span></span>'; ?></div>
    <?php else : ?>
      <p>Nichts gefunden.</p>
    <?php endif; ?>
  </div>
</section>
<?php
get_footer();
