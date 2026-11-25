<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-section="<?php echo esc_attr(is_front_page() ? 'home' : (sgs_rubrik() ?: 'page')); ?>">
<?php wp_body_open(); ?>
<a class="skip" href="#inhalt">Zum Inhalt springen</a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Startseite <?php echo esc_attr(sgs_wert('site.name', get_bloginfo('name'))); ?>">
      <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/logo-sgs.png'); ?>" alt="" width="44" height="43">
      <span><b>Segelfluggruppe Schaffhausen</b><small>Flugplatz Schmerlat</small></span>
    </a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="hauptnavigation"><?php echo sgs_icon('menu'); ?><span>Menü</span></button>
    <nav id="hauptnavigation" aria-label="Hauptnavigation">
      <ul class="nav-list"><?php sgs_nav_ausgeben(); ?></ul>
    </nav>
    <a class="status-chip" href="<?php echo esc_url(home_url('/#heute')); ?>" data-status-chip>
      <span class="dot" aria-hidden="true"></span><span data-status-short>Flugbetrieb</span>
    </a>
  </div>
</header>
<main id="inhalt">
