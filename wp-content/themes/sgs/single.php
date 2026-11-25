<?php
// Ein Bericht (WordPress-Beitrag) unter /verein/berichte/<name>/, wie im statischen Entwurf.
get_header();
while (have_posts()) {
    the_post();
    $post = get_post();
    $kategorien = implode(', ', wp_list_pluck(get_the_category(), 'name'));
    echo do_shortcode('[sgs_seitenkopf rubrik="Berichte" link="' . esc_url(home_url('/verein/medien/#berichte')) . '" titel="' . esc_attr(str_replace(['[', ']'], ['&#91;', '&#93;'], get_the_title())) . '"]<time datetime="' . get_the_date('Y-m-d') . '">' . get_the_date('d.m.Y') . '</time> · ' . esc_html($kategorien) . '[/sgs_seitenkopf]');
    $aelter = get_previous_post();
    $neuer  = get_next_post();
    ?>
<section class="section">
  <div class="wrap">
    <article class="report">
      <?php the_content(); ?>
    </article>
    <nav class="report-nav" aria-label="Weitere Berichte">
      <?php echo $aelter ? '<a href="' . esc_url(get_permalink($aelter)) . '"><small>Älterer Bericht</small>' . esc_html(get_the_title($aelter)) . '</a>' : '<span></span>'; ?>
      <?php echo $neuer ? '<a class="next" href="' . esc_url(get_permalink($neuer)) . '"><small>Neuerer Bericht</small>' . esc_html(get_the_title($neuer)) . '</a>' : '<span></span>'; ?>
    </nav>
  </div>
</section>
    <?php
    $cats = get_the_category();
    $mehr = $cats ? get_posts(['numberposts' => 3, 'category' => $cats[0]->term_id, 'exclude' => [$post->ID]]) : [];
    if ($mehr && function_exists('sgs_bericht_karte')) : ?>
<section class="section tint">
  <div class="wrap">
    <div class="section-head"><h2>Mehr aus «<?php echo esc_html($cats[0]->name); ?>»</h2><a class="textlink" href="<?php echo esc_url(home_url('/verein/medien/#berichte')); ?>"><?php echo sgs_icon('newspaper'); ?>Alle Berichte</a></div>
    <div class="report-cards"><?php foreach ($mehr as $m) { echo sgs_bericht_karte($m, false); } ?></div>
  </div>
</section>
    <?php endif;
}
get_footer();
