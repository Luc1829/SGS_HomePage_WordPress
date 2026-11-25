<?php
get_header();
echo do_shortcode('[sgs_seitenkopf titel="Seite nicht gefunden"]Diese Seite gibt es nicht (mehr). Vielleicht hilft die Navigation oben weiter.[/sgs_seitenkopf]');
?>
<section class="section">
  <div class="wrap btn-row">
    <a class="btn" href="<?php echo esc_url(home_url('/')); ?>">Zur Startseite</a>
    <a class="btn secondary" href="<?php echo esc_url(home_url('/verein/medien/')); ?>">Berichte ansehen</a>
  </div>
</section>
<?php
get_footer();
