<?php
/**
 * En-tête : sprite d'icônes et menu.
 *
 * Menu : celui attribué à l'emplacement « Menu principal » (Apparence > Menus) ; un élément
 * avec des sous-éléments devient un menu déroulant.
 * Sinon le menu reprend les sections de la page d'accueil (projets, services, contact).
 */

defined( 'ABSPATH' ) || exit;

$front_id = (int) get_option( 'page_on_front' );
$anchors  = array();
$services = array();
if ( $front_id && function_exists( 'get_field' ) ) {
	foreach ( (array) get_field( 'sections', $front_id ) as $s ) {
		if ( empty( $s['acf_fc_layout'] ) ) {
			continue;
		}
		$defaults = array( 'accordeons' => 'services', 'liste' => 'projets', 'formulaire' => 'contact' );
		$layout   = $s['acf_fc_layout'];
		if ( isset( $defaults[ $layout ] ) && ! isset( $anchors[ $layout ] ) ) {
			$anchors[ $layout ] = sanitize_title( $s['ancre'] ?? '' ) ?: $defaults[ $layout ];
			if ( 'accordeons' === $layout ) {
				foreach ( (array) ( $s['elements'] ?? array() ) as $el ) {
					if ( ! empty( $el['titre'] ) ) {
						$services[] = $el['titre'];
					}
				}
			}
		}
	}
}
$home = is_front_page() ? '' : home_url( '/' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> id="top">
<?php wp_body_open(); ?>
<div class="ld" aria-hidden="true"><div class="lens"></div><span class="ld-wm">WM</span><span class="ld-bar"><i></i></span></div>
<div class="pt" aria-hidden="true"><span>WM</span></div>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="in" viewBox="0 0 176 176"><path fill="currentColor" stroke="none" d="m152 0h-128a24 24 0 0 0 -24 24v128a24 24 0 0 0 24 24h128a24 24 0 0 0 24-24v-128a24 24 0 0 0 -24-24zm-92 139.28a3.710 3.710 0 0 1 -3.710 3.720h-15.810a3.710 3.710 0 0 1 -3.720-3.720v-66.280a3.720 3.720 0 0 1 3.720-3.720h15.810a3.720 3.720 0 0 1 3.710 3.720zm-11.620-76.280a15 15 0 1 1 15-15 15 15 0 0 1 -15 15zm94.260 76.540a3.410 3.410 0 0 1 -3.420 3.420h-17a3.410 3.410 0 0 1 -3.420-3.420v-31.050c0-4.640 1.360-20.320-12.130-20.320-10.450 0-12.580 10.730-13 15.550v35.860a3.420 3.420 0 0 1 -3.370 3.420h-16.420a3.410 3.410 0 0 1 -3.410-3.420v-66.870a3.410 3.410 0 0 1 3.410-3.420h16.420a3.420 3.420 0 0 1 3.420 3.420v5.780c3.880-5.830 9.630-10.310 21.900-10.310 27.180 0 27 25.380 27 39.320z"/></symbol>
<symbol id="ig" viewBox="0 0 512 512"><path fill="currentColor" stroke="none" d="m301 256c0 24.851562-20.148438 45-45 45s-45-20.148438-45-45 20.148438-45 45-45 45 20.148438 45 45zm0 0"/><path fill="currentColor" stroke="none" d="m332 120h-152c-33.085938 0-60 26.914062-60 60v152c0 33.085938 26.914062 60 60 60h152c33.085938 0 60-26.914062 60-60v-152c0-33.085938-26.914062-60-60-60zm-76 211c-41.355469 0-75-33.644531-75-75s33.644531-75 75-75 75 33.644531 75 75-33.644531 75-75 75zm86-146c-8.285156 0-15-6.714844-15-15s6.714844-15 15-15 15 6.714844 15 15-6.714844 15-15 15zm0 0"/><path fill="currentColor" stroke="none" d="m377 0h-242c-74.4375 0-135 60.5625-135 135v242c0 74.4375 60.5625 135 135 135h242c74.4375 0 135-60.5625 135-135v-242c0-74.4375-60.5625-135-135-135zm45 332c0 49.625-40.375 90-90 90h-152c-49.625 0-90-40.375-90-90v-152c0-49.625 40.375-90 90-90h152c49.625 0 90 40.375 90 90zm0 0"/></symbol>
<symbol id="pl" viewBox="0 0 24 24"><path d="M8 5.5v13l10.5-6.5z"/></symbol>
<symbol id="cv" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
</defs></svg>

<header class="nav"><nav class="pill glass" id="pill" aria-label="Navigation principale">
<a class="logo" href="<?php echo esc_url( is_front_page() ? '#top' : home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">WM</a>
<div class="menu" id="menu">
<?php if ( has_nav_menu( 'principal' ) ) : ?>
	<?php echo maxou_menu( 'principal' ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans la fonction. ?>
<?php else : ?>
	<?php if ( isset( $anchors['liste'] ) ) : ?>
	<a href="<?php echo esc_url( $home . '#' . $anchors['liste'] ); ?>"><span class="u">Mes projets</span></a>
	<?php endif; ?>
	<?php if ( isset( $anchors['accordeons'] ) ) : ?>
	<div class="dd" id="dd"><button class="dd-b" type="button" aria-expanded="false" aria-haspopup="true"><span class="u">Mes services</span></button>
	<?php if ( $services ) : ?>
	<div class="dd-m glass"><?php foreach ( $services as $t ) : ?><a href="<?php echo esc_url( $home . '#' . $anchors['accordeons'] ); ?>"><span class="u"><?php echo esc_html( $t ); ?></span></a><?php endforeach; ?></div>
	<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php if ( isset( $anchors['formulaire'] ) ) : ?>
	<a href="<?php echo esc_url( $home . '#' . $anchors['formulaire'] ); ?>"><span class="u">Contact</span></a>
	<?php endif; ?>
<?php endif; ?>
<?php echo maxou_socials(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans la fonction. ?>
</div>
<button class="burger" id="burger" type="button" aria-label="Ouvrir le menu" aria-expanded="false"><span style="display:block"><i></i><i></i><i></i></span></button>
</nav></header>

<main>
