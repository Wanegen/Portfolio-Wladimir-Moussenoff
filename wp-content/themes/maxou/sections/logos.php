<?php
/**
 * Section « Galerie de logos » : défilement continu, tous les logos à la même taille.
 * Chaque moitié de la bande répète les logos jusqu'à 15 éléments au moins, pour couvrir
 * les écrans larges sans jamais laisser de vide pendant la boucle.
 */

defined( 'ABSPATH' ) || exit;

$titre = get_sub_field( 'titre' );
$logos = array_values( array_filter( (array) get_sub_field( 'logos' ) ) );
if ( ! $logos ) {
	return;
}

$reps = max( 1, (int) ceil( 15 / count( $logos ) ) );
$half = function ( $with_alt ) use ( $logos, $reps ) {
	$out = '';
	for ( $r = 0; $r < $reps; $r++ ) {
		foreach ( $logos as $l ) {
			$alt  = ( $with_alt && 0 === $r ) ? ( $l['alt'] ?: $l['title'] ) : '';
			$src  = $l['sizes']['medium_large'] ?? $l['url'];
			$w    = $l['sizes']['medium_large-width'] ?? $l['width'];
			$h    = $l['sizes']['medium_large-height'] ?? $l['height'];
			$out .= '<span><img src="' . esc_url( $src ) . '"' . ( $w ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : '' ) . ' alt="' . esc_attr( $alt ) . '" decoding="async"></span>';
		}
	}
	return $out;
};
$aid = 'logos-' . get_row_index();
?>
<section class="sec cl<?php echo esc_attr( maxou_dots() ); ?>" id="<?php echo esc_attr( maxou_anchor( 'clients' ) ); ?>"<?php echo $titre ? ' aria-labelledby="' . esc_attr( $aid ) . '"' : ''; ?>>
<?php if ( $titre ) : ?><h2 class="h2" id="<?php echo esc_attr( $aid ); ?>" data-wave><?php echo esc_html( $titre ); ?></h2><?php endif; ?>
<div class="mq" aria-label="Logos clients"><div><?php echo $half( true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><div aria-hidden="true"><?php echo $half( false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div>
</section>
