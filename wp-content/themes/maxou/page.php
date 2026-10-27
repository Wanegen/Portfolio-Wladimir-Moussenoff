<?php
/**
 * Page construite par sections (champ flexible « sections »).
 * Utilisé aussi pour la page d'accueil (front-page.php l'inclut).
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( function_exists( 'have_rows' ) && have_rows( 'sections' ) ) {
	while ( have_rows( 'sections' ) ) {
		the_row();
		$GLOBALS['maxou_row'] = get_row_index();
		get_template_part( 'sections/' . get_row_layout() );
		if ( 1 === get_row_index() ) {
			echo maxou_filters_bar(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}

get_footer();
