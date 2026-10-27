<?php
/**
 * Section « Haut de page » : titre, sous-titre et vidéo de fond (YouTube ou fichier).
 * La vidéo YouTube est pilotée par l'API IFrame (main.js) pour forcer la lecture en muet,
 * y compris sur Safari ; elle n'apparaît qu'une fois la lecture lancée.
 */

defined( 'ABSPATH' ) || exit;

$titre = get_sub_field( 'titre' );
$sous  = array_filter( array_map( 'trim', explode( '|', (string) get_sub_field( 'sous_titre' ) ) ) );
$type  = get_sub_field( 'video_type' ) ?: 'youtube';
$yt    = 'youtube' === $type ? maxou_youtube_id( get_sub_field( 'video_youtube' ) ) : '';
$file  = 'fichier' === $type ? get_sub_field( 'video_fichier' ) : '';
$img   = (int) get_sub_field( 'image' );
$plein = get_sub_field( 'plein_ecran' );
$plein = ( null === $plein ) ? true : (bool) $plein; // rangées créées avant ce réglage : plein écran.
$btn   = get_sub_field( 'bouton' );
$bg    = $img ? wp_get_attachment_image_url( $img, 'full' ) : '';
if ( ! $bg && $yt ) {
	// Sans image choisie : la miniature de la vidéo s'affiche le temps que la lecture démarre.
	$bg = 'https://i.ytimg.com/vi/' . $yt . '/maxresdefault.jpg';
}
?>
<section class="hero<?php echo $plein ? '' : ' hero-70'; ?>"<?php echo $bg ? ' style="background-image:url(' . esc_url( $bg ) . ')"' : ''; ?>>
<?php if ( $yt ) : ?>
<div class="vid" aria-hidden="true" data-yt="<?php echo esc_attr( $yt ); ?>"><div></div></div>
<?php elseif ( $file ) : ?>
<div class="vid" aria-hidden="true"><video src="<?php echo esc_url( $file ); ?>"<?php echo $bg ? ' poster="' . esc_url( $bg ) . '"' : ''; ?> autoplay muted loop playsinline preload="auto"></video></div>
<?php endif; ?>
<div class="hero-in">
<h1><span class="nm" data-wave><?php echo esc_html( $titre ); ?></span><?php if ( $sous ) : ?><span class="sub"><span class="sr"> - </span><?php echo implode( '<i>|</i>', array_map( function ( $w ) { return '<span>' . esc_html( $w ) . '</span>'; }, $sous ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php endif; ?></h1>
<?php echo $btn ? maxou_button( $btn ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans maxou_button(). ?>
</div>
</section>
