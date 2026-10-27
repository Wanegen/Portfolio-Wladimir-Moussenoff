<?php
/**
 * Section « Liste de vidéos » : cartes YouTube filtrables (filtres cochés sur chaque vidéo) et bouton jaune/vert dessous.
 * Les boutons de filtre affichés sont ceux utilisés par au moins une vidéo de la section.
 */

defined( 'ABSPATH' ) || exit;

$titre    = get_sub_field( 'titre' );
$elements = (array) get_sub_field( 'elements' );
$bouton   = get_sub_field( 'bouton' );

$filtres = array();
foreach ( $elements as $el ) {
	foreach ( (array) ( $el['filtres'] ?? array() ) as $term ) {
		if ( $term instanceof WP_Term ) {
			$filtres[ $term->slug ] = $term->name;
		}
	}
}
?>
<?php
// Une seule vidéo (ex. showreel) : titre et vidéo centrés, vidéo plus grande, sans son petit titre.
$solo = 1 === count( $elements );
?>
<section class="sec sec-liste<?php echo esc_attr( ( $solo ? ' sec-solo' : '' ) . maxou_dots() ); ?>" id="<?php echo esc_attr( maxou_anchor( 'projets' ) ); ?>">
<?php if ( $titre ) : ?><h2 class="h2" data-wave><?php echo esc_html( $titre ); ?></h2><?php endif; ?>
<?php if ( count( $filtres ) > 1 ) : ?>
<div class="fl" role="group" aria-label="Filtrer les vidéos" data-fade>
<button class="chip" type="button" aria-pressed="true" data-f="all">Tous</button><?php foreach ( $filtres as $slug => $name ) : ?><button class="chip" type="button" aria-pressed="false" data-f="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></button><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ( $elements ) : ?>
<div class="grid">
<?php
foreach ( $elements as $el ) :
	$id = maxou_youtube_id( $el['video'] ?? '' );
	if ( ! $id ) {
		continue;
	}
	$terms = array_filter( (array) ( $el['filtres'] ?? array() ), function ( $t ) { return $t instanceof WP_Term; } );
	$slugs = implode( ' ', wp_list_pluck( $terms, 'slug' ) );
	$thumb = ! empty( $el['miniature'] )
		? wp_get_attachment_image( (int) $el['miniature'], 'large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) )
		: '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg' ) . '" width="1280" height="720" alt="" loading="lazy" decoding="async">';
	?>
<div class="card" data-t="<?php echo esc_attr( $slugs ); ?>" data-yt="<?php echo esc_attr( $id ); ?>"><div class="th" tabindex="0" role="button" aria-label="<?php echo esc_attr( 'Lire la vidéo ' . $el['titre'] ); ?>"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="play glass"><?php echo maxou_icon( 'pl' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></div><div class="cb"><h3><?php echo esc_html( $el['titre'] ); ?></h3><?php if ( $terms ) : ?><div class="tg"><?php foreach ( $terms as $t ) : ?><b><?php echo esc_html( $t->name ); ?></b><?php endforeach; ?></div><?php endif; ?></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ( $bouton && ! empty( $bouton['url'] ) ) : ?><div class="more" data-fade><?php echo maxou_button( $bouton, 'btn btn-acc' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
</section>
