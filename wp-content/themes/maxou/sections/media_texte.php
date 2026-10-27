<?php
/**
 * Section « 2 colonnes : média et texte » : surtitre, titre, paragraphe et image ou vidéo,
 * média à gauche ou à droite (toujours au-dessus du texte sur mobile). Les catégories vidéo cochées
 * servent aux filtres de la barre flottante (data-cat).
 */

defined( 'ABSPATH' ) || exit;

$surtitre = get_sub_field( 'surtitre' );
$titre    = get_sub_field( 'titre' );
$texte    = get_sub_field( 'texte' );
$media    = get_sub_field( 'media' ) ?: 'image';
$droite   = 'droite' === get_sub_field( 'disposition' );
$ancre    = sanitize_title( (string) get_sub_field( 'ancre' ) );
$mini     = (int) get_sub_field( 'miniature' ); // image avant le lancement de la vidéo (facultative)
$cats     = array();
foreach ( (array) get_sub_field( 'categories' ) as $term_id ) {
	$t = get_term( (int) $term_id, 'filtre_video' );
	if ( $t && ! is_wp_error( $t ) ) {
		$cats[] = $t->slug;
	}
}

$html = '';
if ( 'image' === $media && get_sub_field( 'image' ) ) {
	// Image droite, avec des coins jaune/vert (comme autour du formulaire).
	$img_id = (int) get_sub_field( 'image' );
	$attrs  = array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width:900px) 100vw, 50vw' );
	$html   = '<div class="mt-stack">'
		. '<div class="mt-front">' . wp_get_attachment_image( $img_id, 'large', false, $attrs )
		. '<i class="c tl" aria-hidden="true"></i><i class="c tr" aria-hidden="true"></i><i class="c bl" aria-hidden="true"></i><i class="c br" aria-hidden="true"></i></div>'
		. '</div>';
} elseif ( 'youtube' === $media && ( $yt = maxou_youtube_id( get_sub_field( 'video_youtube' ) ) ) ) {
	$label = 'Lire la vidéo' . ( $titre ? ' ' . $titre : '' );
	$thumb = $mini
		? wp_get_attachment_image( $mini, 'large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) )
		: '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $yt . '/maxresdefault.jpg' ) . '" width="1280" height="720" alt="" loading="lazy" decoding="async">';
	$html  = '<div class="card" data-yt="' . esc_attr( $yt ) . '"><div class="th" tabindex="0" role="button" aria-label="' . esc_attr( $label ) . '">'
		. $thumb
		. '<span class="play glass">' . maxou_icon( 'pl' ) . '</span></div></div>';
} elseif ( 'fichier' === $media && get_sub_field( 'video_fichier' ) ) {
	$poster = $mini ? wp_get_attachment_image_url( $mini, 'large' ) : '';
	$html   = '<video src="' . esc_url( get_sub_field( 'video_fichier' ) ) . '"' . ( $poster ? ' poster="' . esc_url( $poster ) . '"' : '' ) . ' controls playsinline preload="metadata"></video>';
}
?>
<section class="sec mt<?php echo esc_attr( ( $droite ? ' mt-r' : '' ) . maxou_dots() ); ?>"<?php echo $ancre ? ' id="' . esc_attr( $ancre ) . '"' : ''; ?> data-cat="<?php echo esc_attr( implode( ' ', $cats ) ); ?>">
<div class="mt-g<?php echo $html ? '' : ' mt-solo'; ?>">
<?php if ( $html ) : ?><div class="mt-m" data-fade><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
<div class="mt-t">
<?php if ( $surtitre ) : ?><p class="sur"><?php echo esc_html( $surtitre ); ?></p><?php endif; ?>
<?php if ( $titre ) : ?><h2 class="h2" data-wave><?php echo esc_html( $titre ); ?></h2><?php endif; ?>
<?php if ( $texte ) : ?><div class="txt" data-fade><?php echo wp_kses_post( $texte ); ?></div><?php endif; ?>
</div>
</div>
</section>
