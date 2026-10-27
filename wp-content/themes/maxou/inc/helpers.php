<?php
/**
 * Fonctions utilitaires du thème.
 */

defined( 'ABSPATH' ) || exit;

/** Valeur de la page d'options « Coordonnées », avec repli. */
function maxou_opt( $name, $default = '' ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, 'option' ) : '';
	return ( '' === $v || null === $v || false === $v ) ? $default : $v;
}

/** Identifiant d'une vidéo YouTube à partir de n'importe quel lien (youtu.be, watch?v=, embed, shorts). */
function maxou_youtube_id( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '~^[\w-]{11}$~', $url ) ) {
		return $url;
	}
	if ( preg_match( '~(?:youtu\.be/|[?&]v=|/embed/|/shorts/|/live/)([\w-]{11})~', $url, $m ) ) {
		return $m[1];
	}
	return '';
}

/** Icône du sprite SVG (in, ig, pl, cv). */
function maxou_icon( $id, $class = 'ico' ) {
	return '<svg class="' . esc_attr( $class ) . '" aria-hidden="true"><use href="#' . esc_attr( $id ) . '"/></svg>';
}

/** Lien ACF (url, title, target) en bouton. */
function maxou_button( $link, $class = 'btn' ) {
	if ( empty( $link['url'] ) ) {
		return '';
	}
	$target = ! empty( $link['target'] ) ? ' target="' . esc_attr( $link['target'] ) . '" rel="noopener"' : '';
	$title  = ! empty( $link['title'] ) ? $link['title'] : $link['url'];
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $link['url'] ) . '"' . $target . '>' . esc_html( $title ) . '</a>';
}

/** Liens LinkedIn / Instagram de la page d'options. */
function maxou_socials( $order = array( 'linkedin', 'instagram' ) ) {
	$labels = array( 'linkedin' => array( 'LinkedIn', 'in' ), 'instagram' => array( 'Instagram', 'ig' ) );
	$out    = '';
	foreach ( $order as $key ) {
		$url = maxou_opt( $key );
		if ( $url ) {
			$out .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $labels[ $key ][0] ) . '">' . maxou_icon( $labels[ $key ][1] ) . '</a>';
		}
	}
	return $out ? '<div class="soc">' . $out . '</div>' : '';
}

/** Bouton « Afficher le téléphone » : le numéro n'apparaît en clair qu'au clic (limite la collecte par les robots). */
function maxou_phone_button() {
	$tel = maxou_opt( 'telephone' );
	if ( ! $tel ) {
		return '';
	}
	return '<button class="ph" type="button" data-p="' . esc_attr( base64_encode( $tel ) ) . '">Afficher le téléphone</button>';
}

/** Ancre d'une section (champ « ancre » du layout), nettoyée. */
function maxou_anchor( $default ) {
	$a = sanitize_title( (string) get_sub_field( 'ancre' ) );
	return $a ? $a : $default;
}

/** Menu de l'en-tête : liens simples, et menu déroulant pour un élément qui a des sous-éléments. */
function maxou_menu( $location ) {
	$locs = get_nav_menu_locations();
	if ( empty( $locs[ $location ] ) ) {
		return '';
	}
	$items = (array) wp_get_nav_menu_items( $locs[ $location ] );
	$kids  = array();
	foreach ( $items as $it ) {
		$kids[ (int) $it->menu_item_parent ][] = $it;
	}
	$link = function ( $it ) {
		$target = $it->target ? ' target="' . esc_attr( $it->target ) . '" rel="noopener"' : '';
		return '<a href="' . esc_url( $it->url ) . '"' . $target . '><span class="u">' . esc_html( $it->title ) . '</span></a>';
	};
	$out = '';
	foreach ( $kids[0] ?? array() as $i => $it ) {
		if ( empty( $kids[ $it->ID ] ) ) {
			$out .= $link( $it );
			continue;
		}
		// L'élément parent reste un lien (vers sa page) ; le sous-menu s'ouvre au survol sur ordinateur,
		// ou avec le bouton flèche sur mobile.
		$out .= '<div class="dd">' . str_replace( '<a ', '<a class="dd-b" ', $link( $it ) )
			. '<button class="dd-t" type="button" aria-expanded="false" aria-label="' . esc_attr( 'Afficher le sous-menu ' . $it->title ) . '">' . maxou_icon( 'cv', 'chev' ) . '</button>'
			. '<div class="dd-m glass">';
		foreach ( $kids[ $it->ID ] as $child ) {
			$out .= $link( $child );
		}
		$out .= '</div></div>';
	}
	return $out;
}

/**
 * Classe « dots » (fond en pointillés) une section sur deux à partir de la deuxième de la page :
 * sections 2, 4, 6… La section Photographie garde son propre fond (objectif) et n'en reçoit pas.
 */
function maxou_dots() {
	$i = (int) ( $GLOBALS['maxou_row'] ?? 0 );
	return ( $i >= 2 && 0 === $i % 2 ) ? ' dots' : '';
}

/** Filtres photos (slug => nom) utilisés par les sections « Défilement images » de la page affichée. */
function maxou_photo_filters() {
	$page = get_queried_object_id();
	if ( ! $page || ! function_exists( 'get_field' ) ) {
		return array();
	}
	$ids = array();
	foreach ( (array) get_field( 'sections', $page, false ) as $s ) {
		if ( ( $s['acf_fc_layout'] ?? '' ) !== 'defilement' ) {
			continue;
		}
		foreach ( (array) ( $s['field_mx_defil_images'] ?? array() ) as $row ) {
			foreach ( (array) ( $row['field_mx_defil_cats'] ?? array() ) as $id ) {
				$ids[ (int) $id ] = true;
			}
		}
	}
	if ( ! $ids ) {
		return array();
	}
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => 'filtre_photo', 'include' => array_keys( $ids ), 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		$out[ $t->slug ] = $t->name;
	}
	return $out;
}

/** Filtres vidéo (slug => nom) cochés sur les sections « 2 colonnes » de la page affichée. */
function maxou_video_filters() {
	$page = get_queried_object_id();
	if ( ! $page || ! function_exists( 'get_field' ) ) {
		return array();
	}
	$ids = array();
	foreach ( (array) get_field( 'sections', $page, false ) as $s ) {
		if ( ( $s['acf_fc_layout'] ?? '' ) === 'media_texte' ) {
			foreach ( (array) ( $s['field_mx_mt_cats'] ?? array() ) as $id ) {
				$ids[ (int) $id ] = true;
			}
		}
	}
	if ( ! $ids ) {
		return array();
	}
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => 'filtre_video', 'include' => array_keys( $ids ), 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		$out[ $t->slug ] = $t->name;
	}
	return $out;
}

/**
 * Menu de filtres des pages photos (« Défilement images ») et vidéos (sections 2 colonnes catégorisées) :
 * placé entre la 1re et la 2e section, centré, puis collé en haut de l'écran au défilement.
 * Flèches haut / bas de part et d'autre des filtres.
 */
function maxou_filters_bar() {
	$photos = maxou_page_has_layout( 'defilement' );
	$f      = $photos ? maxou_photo_filters() : maxou_video_filters();
	if ( ! $f ) {
		return '';
	}
	$out = '<div class="fsel" data-cible="' . ( $photos ? 'photos' : 'videos' ) . '"><div class="fsel-in glass">'
		. '<button class="fb-ar fb-up" type="button" aria-label="Haut de la page"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button>'
		. '<button class="fs-mob" type="button" aria-expanded="false" aria-controls="fs-f">Filtrer</button>'
		. '<div class="fs-f" id="fs-f" role="group" aria-label="' . esc_attr( 'Filtrer les ' . ( $photos ? 'photos' : 'vidéos' ) ) . '">'
		. '<button type="button" data-f="all" aria-pressed="true">Toutes</button>';
	foreach ( $f as $slug => $name ) {
		$out .= '<button type="button" data-f="' . esc_attr( $slug ) . '" aria-pressed="false">' . esc_html( $name ) . '</button>';
	}
	return $out . '</div><button class="fb-ar fb-down" type="button" aria-label="Bas de la page"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12l7 7 7-7"/></svg></button></div></div>';
}

/** La page affichée contient-elle une section de ce type (ex. « defilement ») ? */
function maxou_page_has_layout( $layout ) {
	$page = get_queried_object_id();
	if ( ! $page || ! function_exists( 'get_field' ) ) {
		return false;
	}
	foreach ( (array) get_field( 'sections', $page, false ) as $s ) {
		if ( ( $s['acf_fc_layout'] ?? '' ) === $layout ) {
			return true;
		}
	}
	return false;
}
