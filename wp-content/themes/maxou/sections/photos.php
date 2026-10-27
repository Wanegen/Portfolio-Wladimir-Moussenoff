<?php
/**
 * Section « Photographie » : photos flottantes placées en anneau autour du titre (10 emplacements), bouton.
 */

defined( 'ABSPATH' ) || exit;

$titre  = get_sub_field( 'titre' );
$photos = array_values( array_filter( wp_list_pluck( (array) get_sub_field( 'photos' ), 'image' ) ) );
$bouton = get_sub_field( 'bouton' );

/*
 * 10 emplacements répartis en cercle autour du titre (zone de 13 × 11), tous les 36° en partant du haut,
 * à distance alternée (100 % / 92 % du rayon) pour un anneau un peu vivant. Chaque photo est centrée sur
 * son point (translate -50 % dans le CSS) : les voisines peuvent se frôler légèrement, le centre reste libre.
 * Par emplacement : angle, format (p = portrait 4:5, l = paysage 3:2), profondeur de parallaxe,
 * inclinaison et durée du flottement. Les 3 paysages forment un triangle (haut, bas droite, gauche).
 * Chaque photo va dans le prochain emplacement libre de son orientation (sinon le premier libre).
 */
$ring = array(
	array( -90, 'l', 22, -2, 6 ),
	array( -54, 'p', -28, 3, 8 ),
	array( -18, 'p', 18, -2, 7 ),
	array( 18, 'p', -24, 2, 9 ),
	array( 54, 'l', 20, -3, 7.5 ),
	array( 90, 'p', -16, 2, 6.5 ),
	array( 126, 'p', 26, -3, 8.5 ),
	array( 162, 'p', -20, 2, 9.5 ),
	array( 198, 'l', 16, -2, 7 ),
	array( 234, 'p', -26, 3, 8 ),
);
$slots = array();
foreach ( $ring as $i => $r ) {
	list( $deg, $fmt, $depth, $rot, $dur ) = $r;
	$k       = 0 === $i % 2 ? 1 : .92;
	$a       = deg2rad( $deg );
	$slots[] = array(
		sprintf( 'left:%.2f%%;top:%.2f%%', 50 + 37 * $k * cos( $a ), 50 + 38 * $k * sin( $a ) ),
		'l' === $fmt ? 21 : 16,
		$fmt,
		$depth,
		$rot,
		$dur,
	);
}

/* Répartition des photos dans les emplacements selon leur orientation. */
$pool = array();
foreach ( $photos as $img_id ) {
	$m      = wp_get_attachment_metadata( (int) $img_id );
	$pool[] = array( (int) $img_id, ( ! empty( $m['width'] ) && $m['width'] > $m['height'] ) ? 'l' : 'p' );
}
$placed = array();
foreach ( $slots as $i => $slot ) {
	foreach ( array( true, false ) as $strict ) {
		foreach ( $pool as $k => $ph ) {
			if ( ! $strict || $ph[1] === $slot[2] ) {
				$placed[ $i ] = $ph[0];
				unset( $pool[ $k ] );
				continue 3;
			}
		}
	}
}
?>
<section class="sec sec-photos" id="<?php echo esc_attr( maxou_anchor( 'photos' ) ); ?>">
<div class="lens" aria-hidden="true"></div>
<div class="fo" id="fo">
<div class="fo-t"><?php if ( $titre ) : ?><h2 class="h2" data-wave><?php echo esc_html( $titre ); ?></h2><?php endif; ?><?php echo $bouton ? maxou_button( $bouton ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
<?php
foreach ( $placed as $i => $img_id ) :
	list( $pos, $w, $fmt, $depth, $rot, $dur ) = $slots[ $i ];
	$img = wp_get_attachment_image( $img_id, 'large', false, array(
		'class'    => 'fi',
		'loading'  => 'lazy',
		'decoding' => 'async',
		'style'    => '--rot:' . $rot . 'deg;--d:' . $dur . 's',
		'sizes'    => '(max-width:700px) 40vw, 25vw',
	) );
	?>
<div class="fp fp-<?php echo esc_attr( $fmt ); ?>" data-depth="<?php echo (int) $depth; ?>" style="<?php echo esc_attr( $pos . ';--w:' . $w . '%' ); ?>"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
<?php endforeach; ?>
</div>
</section>
