<?php
/**
 * Section « Accordéons » : titre et accordéons (titre, description, étiquettes, bouton facultatif).
 */

defined( 'ABSPATH' ) || exit;

$titre    = get_sub_field( 'titre' );
$elements = (array) get_sub_field( 'elements' );
?>
<section class="sec sec-acc<?php echo esc_attr( maxou_dots() ); ?>" id="<?php echo esc_attr( maxou_anchor( 'services' ) ); ?>">
<?php if ( $titre ) : ?><h2 class="h2" data-wave><?php echo esc_html( $titre ); ?></h2><?php endif; ?>
<?php if ( $elements ) : ?>
<div class="sv" data-fade>
<?php foreach ( $elements as $el ) : ?>
<div class="sv-i glass"><button class="sv-h" type="button" aria-expanded="false"><span><?php echo esc_html( $el['titre'] ); ?></span><?php echo maxou_icon( 'cv', 'chev' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button><div class="sv-b"><div>
<?php if ( ! empty( $el['texte'] ) ) : ?><p><?php echo wp_kses( $el['texte'], array( 'br' => array() ) ); ?></p><?php endif; ?>
<?php $tags = array_filter( wp_list_pluck( (array) ( $el['etiquettes'] ?? array() ), 'texte' ) ); if ( $tags ) : ?>
<div class="tg"><?php foreach ( $tags as $t ) : ?><b><?php echo esc_html( $t ); ?></b><?php endforeach; ?></div>
<?php endif; ?>
<?php if ( ! empty( $el['bouton']['url'] ) ) : ?><div class="sv-bt"><?php echo maxou_button( $el['bouton'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans maxou_button(). ?></div><?php endif; ?>
</div></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
