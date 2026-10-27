<?php
/**
 * Thème Maxou : réglages, scripts, taxonomie des filtres vidéo et page d'options.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/champs.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'script', 'style', 'gallery', 'caption' ) );
	register_nav_menus( array( 'principal' => 'Menu principal' ) );
	// Barre d'administration en bas de l'écran : on retire la marge que WordPress ajoute en haut.
	add_theme_support( 'admin-bar', array( 'callback' => '__return_false' ) );
} );

/* Écran de chargement (première page vue dans l'onglet) ou fondu d'arrivée (pages suivantes) :
   la classe est posée avant l'affichage pour éviter tout flash. Sans JavaScript, rien ne s'affiche ;
   par sécurité, tout est retiré au bout de 10 s. Le reste est géré par assets/js/transitions.js. */
add_action( 'wp_head', function () {
	echo "<script>(function(d){try{d.classList.add(sessionStorage.getItem('mxSeen')?'mx-in':'mx-ld')}catch(e){d.classList.add('mx-in')}setTimeout(function(){d.classList.remove('mx-ld','mx-in')},10000)})(document.documentElement)</script>\n";
}, 0 );

/* Barre d'administration (connecté) placée en bas de l'écran, menus ouverts vers le haut. */
add_action( 'wp_head', function () {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	echo '<style id="maxou-admin-bar">'
		. 'html{margin-top:0!important}'
		. 'body.admin-bar{padding-bottom:32px}'
		. '#wpadminbar{position:fixed!important;top:auto!important;bottom:0}'
		. '#wpadminbar .menupop .ab-sub-wrapper{top:auto;bottom:32px}'
		. '#wpadminbar .menupop .ab-sub-wrapper .ab-sub-wrapper{bottom:0}'
		. '@media screen and (max-width:782px){body.admin-bar{padding-bottom:46px}#wpadminbar .menupop .ab-sub-wrapper{bottom:46px}}'
		. '</style>' . "
";
}, 100 );

/* Styles et scripts : version = date de modification du fichier, pour ne jamais servir un cache périmé. */
add_action( 'wp_enqueue_scripts', function () {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'maxou', $uri . '/assets/css/main.css', array(), filemtime( $dir . '/assets/css/main.css' ) );

	wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', array(), null, true );
	wp_enqueue_script( 'gsap-st', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js', array( 'gsap' ), null, true );
	wp_enqueue_script( 'lenis', 'https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js', array(), null, true );
	wp_enqueue_script( 'maxou', $uri . '/assets/js/main.js', array( 'gsap', 'gsap-st', 'lenis' ), filemtime( $dir . '/assets/js/main.js' ), true );
	wp_enqueue_script( 'maxou-defilement', $uri . '/assets/js/defilement.js', array(), filemtime( $dir . '/assets/js/defilement.js' ), true );
	wp_enqueue_script( 'maxou-transitions', $uri . '/assets/js/transitions.js', array(), filemtime( $dir . '/assets/js/transitions.js' ), true );

	// Pas d'éditeur de blocs sur le front : on retire leurs styles.
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
} );

add_action( 'wp_head', function () {
	$uri = get_template_directory_uri();
	echo '<link rel="preload" href="' . esc_url( $uri . '/assets/fonts/amoretta.ttf' ) . '" as="font" type="font/ttf" crossorigin>' . "\n";
	echo '<link rel="preload" href="' . esc_url( $uri . '/assets/fonts/Poppins-Regular.ttf' ) . '" as="font" type="font/ttf" crossorigin>' . "\n";
}, 1 );

/* Les pages se construisent par sections : l'éditeur de contenu classique est masqué. */
add_action( 'init', function () {
	remove_post_type_support( 'page', 'editor' );

	// Filtres de la section « Liste » (ex. Corporate, Artistic). Gérés dans Pages > Filtres vidéo
	// ou créés directement depuis la case à cocher d'un élément.
	register_taxonomy( 'filtre_video', 'page', array(
		'labels'            => array(
			'name'          => 'Filtres vidéo',
			'singular_name' => 'Filtre vidéo',
			'add_new_item'  => 'Ajouter un filtre',
			'edit_item'     => 'Modifier le filtre',
			'search_items'  => 'Rechercher un filtre',
			'not_found'     => 'Aucun filtre',
		),
		'public'            => false,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_admin_column' => false,
		'meta_box_cb'       => false,
		'hierarchical'      => false,
		'rewrite'           => false,
	) );

	// Catégories des photos de la section « Défilement images ». Gérées dans Pages > Filtres photos
	// ou créées directement depuis la case à cocher d'une photo ; affichées dans la barre flottante.
	register_taxonomy( 'filtre_photo', 'page', array(
		'labels'            => array(
			'name'          => 'Filtres photos',
			'singular_name' => 'Filtre photo',
			'add_new_item'  => 'Ajouter un filtre',
			'edit_item'     => 'Modifier le filtre',
			'search_items'  => 'Rechercher un filtre',
			'not_found'     => 'Aucun filtre',
		),
		'public'            => false,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_admin_column' => false,
		'meta_box_cb'       => false,
		'hierarchical'      => false,
		'rewrite'           => false,
	) );
} );

/* Page d'options : coordonnées réutilisées dans le menu, la section formulaire et le pied de page. */
add_action( 'acf/init', function () {
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page( array(
			'page_title' => 'Coordonnées et réseaux',
			'menu_title' => 'Coordonnées',
			'menu_slug'  => 'maxou-coordonnees',
			'capability' => 'edit_pages',
			'icon_url'   => 'dashicons-id',
			'position'   => 59,
		) );
	}
} );

/* Les styles WPForms par défaut restent chargés ; le thème les recolore via leurs variables CSS (main.css). */
