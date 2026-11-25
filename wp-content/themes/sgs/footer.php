<?php
$kontakt = sgs_hauptkontakt();
$telefon = sgs_wert('site.phone');
$kontaktseite = get_page_by_path('kontakt');
$impressum = get_page_by_path('impressum');
?>
</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-contact" id="kontakt">
      <h2>Kontakt & Anfahrt</h2>
      <address>
        <?php echo esc_html(sgs_wert('site.location')); ?><br>
        Post: <?php echo esc_html(sgs_wert('site.name') . ', ' . sgs_wert('site.address.zip') . ' ' . sgs_wert('site.address.city')); ?>
      </address>
      <ul class="contact-list">
        <?php if ($telefon) : ?>
          <li><?php echo sgs_icon('phone'); ?><a href="tel:<?php echo esc_attr(preg_replace('/\s/', '', $telefon)); ?>"><?php echo esc_html($telefon); ?></a> <small><?php echo esc_html(sgs_wert('site.phoneNote')); ?></small></li>
        <?php endif; ?>
        <?php if ($kontakt) : ?>
          <li><?php echo sgs_icon('mail'); ?><a href="mailto:<?php echo esc_attr($kontakt['email']); ?>"><?php echo esc_html($kontakt['email']); ?></a></li>
        <?php endif; ?>
        <li><?php echo sgs_icon('map-pin'); ?><a href="<?php echo esc_url(sgs_wert('site.mapUrl')); ?>" rel="noopener">Auf der Karte öffnen</a></li>
      </ul>
    </div>
    <div class="footer-sitemap"><?php sgs_fuss_spalten(); ?></div>
  </div>
  <div class="wrap footer-bottom">
    <span>© <?php echo esc_html(wp_date('Y') . ' ' . sgs_wert('site.name', get_bloginfo('name'))); ?></span>
    <?php if ($kontaktseite) : ?><a href="<?php echo esc_url(get_permalink($kontaktseite)); ?>">Kontakt & Anfahrt</a><?php endif; ?>
    <?php if ($impressum) : ?><a href="<?php echo esc_url(get_permalink($impressum)); ?>">Impressum & Datenschutz</a><?php endif; ?>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
