<?php
/**
 * Gabarit de repli (articles, archives, 404) : titre et contenu simples.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="sec">
<?php if ( have_posts() ) : ?>
	<?php while ( have_posts() ) : the_post(); ?>
	<h1 class="h2"><?php the_title(); ?></h1>
	<div class="txt"><?php the_content(); ?></div>
	<?php endwhile; ?>
<?php else : ?>
	<h1 class="h2">Page introuvable</h1>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a></p>
<?php endif; ?>
</section>
<?php
get_footer();
