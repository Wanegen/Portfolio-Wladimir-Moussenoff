<?php
/**
 * Section « Défilement images » : photos sur 2 colonnes en quinconce, chacune affichée une fois.
 * Les images sont listées dans un <template> (jamais
 * chargées telles quelles) : assets/js/defilement.js ne crée que celles proches de l'écran et retire
 * celles qui sont loin au-dessus.
 */

defined( 'ABSPATH' ) || exit;

$titre  = get_sub_field( 'titre' );
$images = array_values( array_filter( (array) get_sub_field( 'images' ), function ( $r ) { return ! empty( $r['image'] ); } ) );
if ( ! $images ) {
	return;
}
?>
<section class="sec ig" id="<?php echo esc_attr( maxou_anchor( 'galerie' ) ); ?>">
<?php if ( $titre ) : ?><h2 class="h2" data-wave><?php echo esc_html( $titre ); ?></h2><?php endif; ?>
<div class="ig-feed" role="region" aria-label="<?php echo esc_attr( $titre ? $titre : 'Galerie d\'images' ); ?>">
<template class="ig-src">
<?php
foreach ( $images as $row ) {
	$cats = array();
	foreach ( (array) ( $row['categories'] ?? array() ) as $term_id ) {
		$t = get_term( (int) $term_id, 'filtre_photo' );
		if ( $t && ! is_wp_error( $t ) ) {
			$cats[] = $t->slug;
		}
	}
	echo wp_get_attachment_image( (int) $row['image'], 'medium_large', false, array( 'loading' => 'lazy', 'data-cat' => implode( ' ', $cats ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
}
?>
</template>
</div>
</section>
