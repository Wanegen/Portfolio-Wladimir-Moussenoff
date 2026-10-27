<?php
/**
 * Section « Haut de page (pages intérieures) » : environ 70 % de la hauteur d'écran,
 * vidéo de fond très atténuée, surtitre + titre ferrés à gauche dans le conteneur, bouton.
 */

defined( 'ABSPATH' ) || exit;

$surtitre = get_sub_field( 'surtitre' );
$titre    = get_sub_field( 'titre' );
$type     = get_sub_field( 'video_type' ) ?: 'youtube';
$yt       = 'youtube' === $type ? maxou_youtube_id( get_sub_field( 'video_youtube' ) ) : '';
$file     = 'fichier' === $type ? get_sub_field( 'video_fichier' ) : '';

$bouton = '';
if ( get_sub_field( 'bouton_on' ) ) {
	$link = get_sub_field( 'bouton' );
	if ( empty( $link['url'] ) ) {
		$contact = get_page_by_path( 'contact' );
		$link    = array( 'url' => $contact ? get_permalink( $contact ) : home_url( '/#contact' ), 'title' => 'Contactez-moi', 'target' => '' );
	}
	$bouton = maxou_button( $link );
}
?>
<section class="ph-hero">
<?php if ( $yt ) : ?>
<div class="vid" aria-hidden="true" data-yt="<?php echo esc_attr( $yt ); ?>"><div></div></div>
<?php elseif ( $file ) : ?>
<div class="vid" aria-hidden="true"><video src="<?php echo esc_url( $file ); ?>" autoplay muted loop playsinline preload="auto"></video></div>
<?php endif; ?>
<div class="ph-in">
<?php if ( $surtitre ) : ?><p class="sur"><?php echo esc_html( $surtitre ); ?></p><?php endif; ?>
<h1 class="ph-t" data-wave><?php echo esc_html( $titre ); ?></h1>
<?php echo $bouton; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans maxou_button(). ?>
</div>
</section>
