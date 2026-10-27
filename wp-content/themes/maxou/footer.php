<?php
/**
 * Pied de page : textes et coordonnées de la page d'options « Coordonnées ».
 */

defined( 'ABSPATH' ) || exit;

$email = maxou_opt( 'email' );
$phone = maxou_phone_button();
?>
</main>

<footer>
<div class="fg">
<div><p class="fb"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p><p><?php echo esc_html( maxou_opt( 'metiers', 'Vidéaste | Photographe | Monteur' ) ); ?></p></div>
<p><span class="av"><?php echo esc_html( maxou_opt( 'disponibilite', 'Disponible partout en France !' ) ); ?></span>
<?php if ( $phone || $email ) : ?><br><?php endif; ?>
<?php echo $phone; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans la fonction. ?>
<?php if ( $phone && $email ) : ?> · <?php endif; ?>
<?php if ( $email ) : ?><a class="ml" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a><?php endif; ?></p>
<?php $soc = maxou_socials(); if ( $soc ) : ?>
<div class="fs"><p>Suivez-moi</p><?php echo $soc; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
<?php endif; ?>
</div>
<p class="cp">© <?php echo esc_html( get_bloginfo( 'name' ) . ' ' . wp_date( 'Y' ) ); ?> - Tous droits réservés. - Site web conçu et réalisé par <a class="ml" href="https://www.linkedin.com/in/maxandre-dufour/" target="_blank" rel="noopener">Maxandre Dufour</a></p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
