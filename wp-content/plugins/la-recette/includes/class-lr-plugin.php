<?php
/**
 * Coeur du plugin : réglages, droits d'accès, participants, CPT, chargement front.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LR_Plugin {

	const OPT           = 'lr_settings';
	const PEOPLE        = 'lr_people';
	const CPT           = 'lr_pin';
	const COOKIE_WHO    = 'lr_person';
	const COOKIE_ACCESS = 'lr_access';

	/** Capacités du type de contenu, accordées aux administrateurs du site. */
	const CAPS = array( 'edit_lr_pins', 'edit_others_lr_pins', 'edit_published_lr_pins', 'publish_lr_pins', 'read_private_lr_pins', 'delete_lr_pins', 'delete_others_lr_pins', 'delete_published_lr_pins', 'delete_private_lr_pins', 'edit_private_lr_pins' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_owner_caps' ), 10, 4 );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		LR_Ajax::init();
		LR_Media::init();
		LR_Admin::init();
	}

	public static function activate() {
		self::settings(); // crée le lien d'accès dès l'activation
	}

	/* ------------------------------------------------------------------ */
	/* Réglages                                                            */
	/* ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'enabled'      => 0,
			// Ouvert à tous les visiteurs (sinon : back-office et personnes ayant le lien d'accès).
			'public'       => 0,
			'access_token' => '',
		);
	}

	public static function settings() {
		$s = get_option( self::OPT, array() );
		$s = wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
		if ( '' === (string) $s['access_token'] ) {
			$s['access_token'] = self::new_token();
			update_option( self::OPT, $s, false );
		}
		return $s;
	}

	public static function new_token() {
		return strtolower( wp_generate_password( 20, false, false ) );
	}

	/** Lien à transmettre aux personnes qui doivent recetter le site sans compte WordPress. */
	public static function access_link() {
		return add_query_arg( 'recette', self::settings()['access_token'], home_url( '/' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Administrateurs                                                     */
	/* ------------------------------------------------------------------ */

	/** Gestion complète de La Recette (réglages, modération) : administrateurs du site. */
	public static function is_owner() {
		return current_user_can( 'manage_options' );
	}

	public static function grant_owner_caps( $allcaps, $caps, $args, $user ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( self::CAPS as $c ) {
				$allcaps[ $c ] = true;
			}
		}
		return $allcaps;
	}

	/* ------------------------------------------------------------------ */
	/* Participants (personnes assignables)                                */
	/* ------------------------------------------------------------------ */

	/** Participants enregistrés : id => { id, name, email, at }. */
	public static function people() {
		$p = get_option( self::PEOPLE, array() );
		return is_array( $p ) ? $p : array();
	}

	/** Crée ou met à jour un participant (clé : e-mail). */
	public static function upsert_person( $name, $email ) {
		$name  = mb_substr( trim( sanitize_text_field( $name ) ), 0, 80 );
		$email = strtolower( trim( sanitize_email( $email ) ) );
		if ( '' === $name || ! is_email( $email ) ) {
			return null;
		}
		$people = self::people();
		foreach ( $people as $id => $p ) {
			if ( strtolower( $p['email'] ) === $email ) {
				if ( $p['name'] !== $name ) {
					$people[ $id ]['name'] = $name;
					update_option( self::PEOPLE, $people, false );
				}
				return $people[ $id ];
			}
		}
		$id            = 'p_' . strtolower( wp_generate_password( 10, false, false ) );
		$people[ $id ] = array(
			'id'    => $id,
			'name'  => $name,
			'email' => $email,
			'at'    => time(),
		);
		update_option( self::PEOPLE, $people, false );
		return $people[ $id ];
	}

	private static function sign( $what ) {
		return hash_hmac( 'sha256', 'lr|' . $what, wp_salt( 'auth' ) );
	}

	private static function set_cookie( $name, $value ) {
		setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + 180 * DAY_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ $name ] = $value;
	}

	public static function set_person_cookie( $id ) {
		self::set_cookie( self::COOKIE_WHO, $id . '.' . self::sign( $id ) );
	}

	/**
	 * Participant courant : utilisateur WordPress connecté (inscrit automatiquement)
	 * ou invité identifié par son cookie signé.
	 */
	public static function current_person() {
		static $cache = false;
		if ( false !== $cache ) {
			return $cache;
		}
		$user = wp_get_current_user();
		if ( $user->exists() ) {
			$cache = self::upsert_person( $user->display_name, $user->user_email );
			return $cache;
		}
		$cache = null;
		$c     = isset( $_COOKIE[ self::COOKIE_WHO ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_WHO ] ) ) : '';
		if ( preg_match( '/^(p_[a-z0-9]{10})\.([a-f0-9]{64})$/', $c, $m ) && hash_equals( self::sign( $m[1] ), $m[2] ) ) {
			$people = self::people();
			if ( isset( $people[ $m[1] ] ) ) {
				$cache = $people[ $m[1] ];
			}
		}
		return $cache;
	}

	/** Liste « Assigné à » : les participants inscrits, par ordre alphabétique. */
	public static function assignees() {
		$people = array_values( self::people() );
		usort( $people, function ( $x, $y ) { return strcasecmp( $x['name'], $y['name'] ); } );
		return $people;
	}

	public static function get_assignee( $id ) {
		if ( '' === (string) $id ) {
			return null;
		}
		$people = self::people();
		return isset( $people[ $id ] ) ? $people[ $id ] : null;
	}

	/** Id « Assigné à » correspondant au participant courant (utile pour « Moi »). */
	public static function assignee_id_for_person( $person ) {
		return $person ? $person['id'] : '';
	}

	/* ------------------------------------------------------------------ */
	/* Droits                                                              */
	/* ------------------------------------------------------------------ */

	public static function is_enabled() {
		return ! empty( self::settings()['enabled'] );
	}

	/** Accès au back-office (utilisateur connecté pouvant rédiger : éditeur, auteur, admin...). */
	public static function is_backoffice_user() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/** Le visiteur a-t-il ouvert le lien d'accès (cookie signé) ? */
	public static function has_access() {
		$c = isset( $_COOKIE[ self::COOKIE_ACCESS ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_ACCESS ] ) ) : '';
		return '' !== $c && hash_equals( self::sign( 'access|' . self::settings()['access_token'] ), $c );
	}

	/**
	 * Outil visible sur le site quand la recette est activée :
	 * - par les personnes connectées au back-office ;
	 * - par les visiteurs qui ont ouvert le lien d'accès (ils s'inscrivent ensuite avec nom + e-mail) ;
	 * - par tous les visiteurs si l'option « ouvert à tous » est cochée.
	 */
	public static function can_use() {
		if ( ! self::is_enabled() ) {
			return false;
		}
		return self::is_backoffice_user() || ! empty( self::settings()['public'] ) || self::has_access();
	}

	/** Visiteur inscrit (compte WordPress ou participant identifié). */
	public static function is_identified() {
		return is_user_logged_in() || null !== self::current_person();
	}

	/** Peut supprimer / modérer n'importe quel point : administrateurs. */
	public static function can_moderate() {
		return self::is_owner();
	}

	/** Le point a-t-il été créé par la personne courante ? */
	public static function owns_pin( $post ) {
		$uid = get_current_user_id();
		if ( $uid && (int) $post->post_author === $uid ) {
			return true;
		}
		$me = self::current_person();
		return $me && get_post_meta( $post->ID, '_lr_person', true ) === $me['id'];
	}

	/* ------------------------------------------------------------------ */
	/* CPT                                                                 */
	/* ------------------------------------------------------------------ */

	public static function chef_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><g fill="black"><circle cx="5.6" cy="7.6" r="3.3"/><circle cx="14.4" cy="7.6" r="3.3"/><circle cx="10" cy="5.6" r="3.9"/><rect x="5" y="8" width="10" height="6.2"/><rect x="5" y="15" width="10" height="2.6" rx=".6"/></g></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'               => 'La Recette',
					'singular_name'      => 'Ticket',
					'menu_name'          => 'La Recette',
					'all_items'          => 'Tous les tickets',
					'edit_item'          => 'Modifier le ticket',
					'search_items'       => 'Rechercher',
					'not_found'          => 'Aucun ticket',
					'not_found_in_trash' => 'Aucun ticket dans la corbeille',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'menu_icon'           => self::chef_icon(),
				'menu_position'       => 58,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => array( 'lr_pin', 'lr_pins' ),
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Front                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Lien d'accès (?recette=…) : mémorise l'accès dans un cookie signé.
	 * Quand la recette est activée, les pages ne doivent pas être mises en cache
	 * (l'outil et son jeton de sécurité sont injectés dans chaque page).
	 */
	public static function template_redirect() {
		if ( isset( $_GET['recette'] ) && self::is_enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification
			$t = sanitize_text_field( wp_unslash( $_GET['recette'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( hash_equals( (string) self::settings()['access_token'], $t ) ) {
				self::set_cookie( self::COOKIE_ACCESS, self::sign( 'access|' . $t ) );
			}
		}
		if ( self::can_use() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	public static function public_assignees() {
		$out = array();
		foreach ( self::assignees() as $a ) {
			$out[] = array(
				'id'    => $a['id'],
				'name'  => $a['name'],
				'label' => $a['name'],
			);
		}
		return $out;
	}

	public static function enqueue() {
		if ( is_admin() || is_customize_preview() || ! self::can_use() ) {
			return;
		}

		$user   = wp_get_current_user();
		$person = self::current_person();

		$data = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'lr' ),
			'assignees'   => self::public_assignees(),
			'canModerate' => self::can_moderate(),
			'media'       => LR_Media::is_configured(),
			'videoMax'    => 5,
			'user'        => array(
				'id'      => (int) $user->ID,
				'name'    => $person ? $person['name'] : '',
				'email'   => $person ? $person['email'] : '',
				'me'      => self::assignee_id_for_person( $person ),
				'isGuest' => ! $user->ID,
			),
			'adminUrl'    => self::is_owner() ? admin_url( 'edit.php?post_type=' . self::CPT ) : '',
		);

		wp_enqueue_style( 'lr-font', 'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_enqueue_style( 'lr', LR_URL . 'assets/recette.css', array( 'lr-font' ), LR_VERSION );
		wp_enqueue_script( 'lr', LR_URL . 'assets/recette.js', array(), LR_VERSION, true );
		wp_add_inline_script( 'lr', 'window.LR = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/* ------------------------------------------------------------------ */
	/* Utilitaires                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Normalise une URL de page en clé : "/chemin/?a=1&b=2" (params de tracking retirés).
	 */
	public static function normalize_page( $url ) {
		$parts = wp_parse_url( (string) $url );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
		$path  = '/' . ltrim( $path, '/' );
		if ( false === strpos( basename( $path ), '.' ) ) {
			$path = trailingslashit( $path );
		}

		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $args );
			foreach ( array_keys( $args ) as $k ) {
				if ( 'recette' === $k || 0 === strpos( $k, 'utm_' ) || in_array( $k, array( 'fbclid', 'gclid', 'msclkid', '_ga' ), true ) ) {
					unset( $args[ $k ] );
				}
			}
			ksort( $args );
			$query = http_build_query( $args );
		}

		return $path . ( '' !== $query ? '?' . $query : '' );
	}

	public static function status_label( $status ) {
		return 'done' === $status ? 'Résolu' : 'À traiter';
	}
}
