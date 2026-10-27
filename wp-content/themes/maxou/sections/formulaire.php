<?php
/**
 * Section « Formulaire » : titre, coordonnées (menu « Coordonnées ») et formulaire WPForms.
 */

defined( 'ABSPATH' ) || exit;

$titre   = get_sub_field( 'titre' );
$accent  = get_sub_field( 'titre_accent' );
$form_id = (int) get_sub_field( 'formulaire' );
$email   = maxou_opt( 'email' );
$phone   = maxou_phone_button();
?>
<section class="sec sec-form<?php echo esc_attr( maxou_dots() ); ?>" id="<?php echo esc_attr( maxou_anchor( 'contact' ) ); ?>">
<div class="ct glass" data-fade><i class="c tl" aria-hidden="true"></i><i class="c tr" aria-hidden="true"></i><i class="c bl" aria-hidden="true"></i><i class="c br" aria-hidden="true"></i>
<div>
<?php if ( $titre || $accent ) : ?><h2 class="h2"><?php if ( $titre ) : ?><span><?php echo esc_html( $titre ); ?></span><?php endif; ?><?php if ( $accent ) : ?><em><?php echo esc_html( $accent ); ?></em><?php endif; ?></h2><?php endif; ?>
<?php if ( $email || $phone ) : ?>
<div class="li"><?php if ( $email ) : ?><a class="ml" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a><?php endif; ?>
<?php echo $phone; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
<?php endif; ?>
<?php echo maxou_socials( array( 'instagram', 'linkedin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<div class="ct-f">
<?php
if ( $form_id && function_exists( 'wpforms_display' ) ) {
	wpforms_display( $form_id, false, false );
} elseif ( current_user_can( 'edit_pages' ) ) {
	echo '<p class="note">Choisissez un formulaire WPForms dans la section « Formulaire de contact ».</p>';
}
?>
</div>
</div>
</section>
