<?php
/**
 * Endpoints AJAX de l'outil front.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LR_Ajax {

	public static function init() {
		$map = array(
			'lr_list'   => 'get_pins',
			'lr_create' => 'create_pin',
			'lr_update' => 'update_pin',
			'lr_delete' => 'delete_pin',
			'lr_media'  => 'add_media',
			'lr_media_delete' => 'delete_media',
			'lr_identify' => 'identify',
			'lr_comment'  => 'add_comment',
			'lr_comment_delete' => 'delete_comment',
			'lr_comment_media'  => 'add_comment_media',
			'lr_comment_edit'   => 'edit_comment',
		);
		foreach ( $map as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}
	}

	private static function guard( $need_identity = true ) {
		if ( ! check_ajax_referer( 'lr', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Session expirée, rechargez la page.' ), 403 );
		}
		if ( ! LR_Plugin::can_use() ) {
			wp_send_json_error( array( 'message' => 'La recette est désactivée.' ), 403 );
		}
		if ( $need_identity && ! LR_Plugin::is_identified() ) {
			wp_send_json_error( array( 'message' => 'Inscrivez-vous d\'abord (nom et e-mail).', 'identify' => 1 ), 403 );
		}
	}

	/** Limite simple par IP (anti-abus). */
	private static function throttle( $what, $max ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'lr_' . $what . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			wp_send_json_error( array( 'message' => 'Trop de tentatives, réessayez plus tard.' ), 429 );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	}

	private static function post( $key ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security
	}

	private static function num( $key, $min = null, $max = null ) {
		$v = (float) self::post( $key );
		if ( null !== $min ) {
			$v = max( $min, $v );
		}
		if ( null !== $max ) {
			$v = min( $max, $v );
		}
		return $v;
	}

	private static function get_owned_pin() {
		$id   = absint( self::post( 'id' ) );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || LR_Plugin::CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => 'Point introuvable.' ), 404 );
		}
		return $post;
	}

	/* ------------------------------------------------------------------ */

	public static function format_pin( $post ) {
		$assignee_id = (string) get_post_meta( $post->ID, '_lr_assignee', true );
		$assignee    = LR_Plugin::get_assignee( $assignee_id );
		$author      = (string) get_post_meta( $post->ID, '_lr_author_name', true );
		$edited      = (int) get_post_meta( $post->ID, '_lr_edited', true );

		return array(
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'comment'      => $post->post_content,
			'status'       => get_post_meta( $post->ID, '_lr_status', true ) === 'done' ? 'done' : 'open',
			'assignee'     => $assignee ? $assignee['id'] : '',
			'assigneeName' => $assignee ? $assignee['name'] : '',
			'author'       => '' !== $author ? $author : 'Invité',
			'date'         => get_the_date( 'd/m/Y H:i', $post ),
			'selector'     => (string) get_post_meta( $post->ID, '_lr_selector', true ),
			'rx'           => (float) get_post_meta( $post->ID, '_lr_rx', true ),
			'ry'           => (float) get_post_meta( $post->ID, '_lr_ry', true ),
			'px'           => (float) get_post_meta( $post->ID, '_lr_px', true ),
			'py'           => (float) get_post_meta( $post->ID, '_lr_py', true ),
			'vw'           => (int) get_post_meta( $post->ID, '_lr_vw', true ),
			'device'       => self::device( (string) get_post_meta( $post->ID, '_lr_ua', true ), (int) get_post_meta( $post->ID, '_lr_vw', true ) ),
			'canDelete'    => LR_Plugin::can_moderate() || LR_Plugin::owns_pin( $post ),
			'canEdit'      => self::can_edit( $post ),
			'edited'       => $edited ? wp_date( 'd/m/Y H:i', $edited ) : '',
			'canAttach'    => self::can_attach( $post ),
			'media'        => LR_Media::public_list( $post->ID ),
			'comments'     => self::public_comments( $post->ID ),
			'page'         => (string) get_post_meta( $post->ID, '_lr_page', true ),
			'pageTitle'    => (string) get_post_meta( $post->ID, '_lr_page_title', true ),
			'url'          => home_url( (string) get_post_meta( $post->ID, '_lr_page', true ) ),
		);
	}

	/** Type d'appareil déduit du navigateur et de la largeur d'écran. */
	private static function device( $ua, $vw ) {
		if ( preg_match( '/iPad|Tablet/i', $ua ) || ( preg_match( '/Android/i', $ua ) && ! preg_match( '/Mobile/i', $ua ) ) ) {
			return 'tablet';
		}
		if ( preg_match( '/Mobi|iPhone|Android/i', $ua ) || ( $vw && $vw < 768 ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	public static function get_pins() {
		self::guard();
		$page = LR_Plugin::normalize_page( self::post( 'page' ) );

		// Tous les points du site : le front les range en « Sur cette page » / « Sur les autres pages ».
		$posts = get_posts(
			array(
				'post_type'        => LR_Plugin::CPT,
				'post_status'      => 'publish',
				'posts_per_page'   => 1000,
				'orderby'          => 'date',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		$pins = array();
		foreach ( $posts as $post ) {
			$p         = self::format_pin( $post );
			$p['here'] = $p['page'] === $page;
			$pins[]    = $p;
		}
		wp_send_json_success( array( 'pins' => $pins ) );
	}

	public static function create_pin() {
		self::guard();
		self::throttle( 'create', 120 );

		$title = trim( sanitize_text_field( self::post( 'title' ) ) );
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => 'Le titre est obligatoire.' ), 400 );
		}
		$title   = mb_substr( $title, 0, 200 );
		$comment = sanitize_textarea_field( self::post( 'comment' ) );

		$assignee_id = sanitize_key( self::post( 'assignee' ) );
		$assignee    = LR_Plugin::get_assignee( $assignee_id );

		$user   = wp_get_current_user();
		$person = LR_Plugin::current_person();
		if ( ! $user->ID && ! $person ) {
			wp_send_json_error( array( 'message' => 'Indiquez d\'abord votre nom et votre e-mail.', 'identify' => 1 ), 403 );
		}
		$author_name = $person ? $person['name'] : $user->display_name;

		$page = LR_Plugin::normalize_page( self::post( 'page' ) );

		$id = wp_insert_post(
			array(
				'post_type'    => LR_Plugin::CPT,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $comment,
				'post_author'  => (int) $user->ID,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ), 500 );
		}

		$meta = array(
			'_lr_page'        => $page,
			'_lr_page_title'  => mb_substr( sanitize_text_field( self::post( 'page_title' ) ), 0, 200 ),
			'_lr_status'      => 'open',
			'_lr_assignee'    => $assignee ? $assignee['id'] : '',
			'_lr_author_name' => $author_name,
			'_lr_person'      => $person ? $person['id'] : '',
			'_lr_author_email' => $person ? $person['email'] : $user->user_email,
			'_lr_selector'    => mb_substr( wp_strip_all_tags( self::post( 'selector' ) ), 0, 1000 ),
			'_lr_rx'          => self::num( 'rx', 0, 1 ),
			'_lr_ry'          => self::num( 'ry', 0, 1 ),
			'_lr_px'          => self::num( 'px', 0 ),
			'_lr_py'          => self::num( 'py', 0 ),
			'_lr_vw'          => (int) self::num( 'vw', 0, 10000 ),
			'_lr_vh'          => (int) self::num( 'vh', 0, 10000 ),
			'_lr_ua'          => mb_substr( sanitize_text_field( self::post( 'ua' ) ), 0, 400 ),
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}

		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $id ) ) ) );
	}

	public static function update_pin() {
		self::guard();
		$post = self::get_owned_pin();

		// Modification du titre / de la description (auteur du ticket)
		if ( isset( $_POST['title'] ) || isset( $_POST['comment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! self::can_edit( $post ) ) {
				wp_send_json_error( array( 'message' => 'Vous ne pouvez modifier que vos propres tickets.' ), 403 );
			}
			$change = array( 'ID' => $post->ID );
			if ( isset( $_POST['title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$title = mb_substr( trim( sanitize_text_field( self::post( 'title' ) ) ), 0, 200 );
				if ( '' === $title ) {
					wp_send_json_error( array( 'message' => 'Le titre est obligatoire.' ), 400 );
				}
				if ( $title !== $post->post_title ) {
					$change['post_title'] = $title;
				}
			}
			if ( isset( $_POST['comment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$comment = sanitize_textarea_field( self::post( 'comment' ) );
				if ( $comment !== $post->post_content ) {
					$change['post_content'] = $comment;
				}
			}
			if ( count( $change ) > 1 ) {
				wp_update_post( $change );
				update_post_meta( $post->ID, '_lr_edited', time() );
				update_post_meta( $post->ID, '_lr_edited_by', self::me()['name'] );
			}
		}

		// Déplacement du marqueur
		// (tout participant identifié peut replacer un marqueur)
		if ( self::post( 'move' ) ) {
			update_post_meta( $post->ID, '_lr_selector', mb_substr( wp_strip_all_tags( self::post( 'selector' ) ), 0, 1000 ) );
			update_post_meta( $post->ID, '_lr_rx', self::num( 'rx', 0, 1 ) );
			update_post_meta( $post->ID, '_lr_ry', self::num( 'ry', 0, 1 ) );
			update_post_meta( $post->ID, '_lr_px', self::num( 'px', 0 ) );
			update_post_meta( $post->ID, '_lr_py', self::num( 'py', 0 ) );
		}

		if ( isset( $_POST['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$status = 'done' === self::post( 'status' ) ? 'done' : 'open';
			update_post_meta( $post->ID, '_lr_status', $status );
		}

		if ( isset( $_POST['assignee'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$assignee = LR_Plugin::get_assignee( sanitize_key( self::post( 'assignee' ) ) );
			update_post_meta( $post->ID, '_lr_assignee', $assignee ? $assignee['id'] : '' );
		}

		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ) ) );
	}

	public static function delete_pin() {
		self::guard();
		$post = self::get_owned_pin();

		if ( ! LR_Plugin::can_moderate() && ! LR_Plugin::owns_pin( $post ) ) {
			wp_send_json_error( array( 'message' => 'Vous ne pouvez pas supprimer ce point.' ), 403 );
		}

		wp_delete_post( $post->ID, true );
		wp_send_json_success( array( 'id' => $post->ID ) );
	}

	/* ------------------------------------------------------------------ */
	/* Captures                                                            */
	/* ------------------------------------------------------------------ */

	/** Peut modifier le titre / la description : uniquement l'auteur du ticket. */
	public static function can_edit( $post ) {
		return LR_Plugin::owns_pin( $post );
	}

	/** Peut joindre une capture à ce point : administrateur ou auteur du point. */
	public static function can_attach( $post ) {
		return LR_Plugin::can_moderate() || LR_Plugin::owns_pin( $post );
	}

	/** Identification d'un invité (nom + e-mail) : il devient assignable. */
	public static function identify() {
		self::guard( false );
		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Vous êtes déjà identifié par votre compte.' ), 400 );
		}
		$name  = self::post( 'name' );
		$email = self::post( 'email' );
		if ( '' === trim( sanitize_text_field( $name ) ) ) {
			wp_send_json_error( array( 'message' => 'Indiquez votre nom et prénom.' ), 400 );
		}
		if ( ! is_email( trim( $email ) ) ) {
			wp_send_json_error( array( 'message' => 'Adresse e-mail invalide.' ), 400 );
		}
		// Limite anti-abus : 30 inscriptions par heure et par IP.
		self::throttle( 'id', 30 );

		$person = LR_Plugin::upsert_person( $name, $email );
		if ( ! $person ) {
			wp_send_json_error( array( 'message' => 'Identification impossible.' ), 400 );
		}
		LR_Plugin::set_person_cookie( $person['id'] );
		wp_send_json_success(
			array(
				'user'      => array(
					'name'  => $person['name'],
					'email' => $person['email'],
					'me'    => LR_Plugin::assignee_id_for_person( $person ),
				),
				'assignees' => LR_Plugin::public_assignees(),
			)
		);
	}

	public static function add_media() {
		self::guard();
		$post = self::get_owned_pin();
		if ( ! self::can_attach( $post ) ) {
			wp_send_json_error( array( 'message' => 'Vous ne pouvez pas ajouter de capture à ce point.' ), 403 );
		}
		$list = LR_Media::get( $post->ID );
		if ( count( $list ) >= LR_Media::MAX_ITEMS ) {
			wp_send_json_error( array( 'message' => 'Nombre maximum de captures atteint.' ), 400 );
		}
		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => 'Fichier manquant.' ), 400 );
		}
		$tmp  = $_FILES['file']['tmp_name']; // phpcs:ignore
		$size = (int) $_FILES['file']['size']; // phpcs:ignore
		list( $kind, $mime ) = LR_Media::sniff( $tmp );
		if ( '' === $kind ) {
			wp_send_json_error( array( 'message' => 'Format de capture non reconnu (' . $mime . ').' ), 400 );
		}
		if ( $size > ( 'video' === $kind ? 60 : 20 ) * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => 'Capture trop lourde.' ), 413 );
		}

		$m = LR_Media::send( $tmp, $kind, $mime );
		if ( is_wp_error( $m ) ) {
			wp_send_json_error( array( 'message' => $m->get_error_message() ), 502 );
		}
		$list[] = $m;
		update_post_meta( $post->ID, LR_Media::META, $list );

		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ) ) );
	}

	public static function delete_media() {
		self::guard();
		$post = self::get_owned_pin();
		if ( ! LR_Plugin::can_moderate() && ! LR_Plugin::owns_pin( $post ) ) {
			wp_send_json_error( array( 'message' => 'Vous ne pouvez pas supprimer cette capture.' ), 403 );
		}
		$list = LR_Media::get( $post->ID );
		$i    = (int) self::post( 'index' );
		if ( ! isset( $list[ $i ] ) ) {
			wp_send_json_error( array( 'message' => 'Capture introuvable.' ), 404 );
		}
		LR_Media::remove( array( $list[ $i ] ) );
		array_splice( $list, $i, 1 );
		update_post_meta( $post->ID, LR_Media::META, $list );
		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ) ) );
	}


	/* ------------------------------------------------------------------ */
	/* Commentaires (fil de discussion d'un ticket)                        */
	/* ------------------------------------------------------------------ */

	private static function comments( $post_id ) {
		$c = get_post_meta( $post_id, '_lr_comments', true );
		return is_array( $c ) ? array_values( $c ) : array();
	}

	/** Identifiant d'un commentaire (calculé pour les commentaires antérieurs à la v0.8). */
	private static function comment_id( $c ) {
		if ( ! empty( $c['id'] ) ) {
			return (string) $c['id'];
		}
		return 'c' . substr( md5( ( isset( $c['at'] ) ? $c['at'] : '' ) . '|' . ( isset( $c['email'] ) ? $c['email'] : '' ) . '|' . ( isset( $c['name'] ) ? $c['name'] : '' ) ), 0, 11 );
	}

	/** Le commentaire a-t-il été écrit par la personne courante ? */
	private static function is_my_comment( $c ) {
		$me = self::me();
		if ( '' !== $me['id'] ) {
			return $c['person'] === $me['id'];
		}
		return '' === $c['person'] && '' !== (string) $me['email'] && strtolower( (string) $c['email'] ) === strtolower( (string) $me['email'] );
	}

	private static function me() {
		$p = LR_Plugin::current_person();
		if ( $p ) {
			return $p;
		}
		$u = wp_get_current_user();
		return array( 'id' => '', 'name' => $u->display_name, 'email' => $u->user_email );
	}

	public static function public_comments( $post_id ) {
		$me  = LR_Plugin::current_person();
		$out = array();
		foreach ( self::comments( $post_id ) as $i => $c ) {
			$media = array();
			foreach ( ( isset( $c['media'] ) && is_array( $c['media'] ) ) ? array_values( $c['media'] ) : array() as $k => $m ) {
				$media[] = LR_Media::public_item( $m, $k );
			}
			$out[] = array(
				'i'         => $i,
				'id'        => self::comment_id( $c ),
				'canEdit'   => self::is_my_comment( $c ),
				'edited'    => ! empty( $c['edited'] ) ? wp_date( 'd/m/Y H:i', (int) $c['edited'] ) : '',
				'media'     => $media,
				'name'      => $c['name'],
				'text'      => $c['text'],
				'date'      => wp_date( 'd/m/Y H:i', (int) $c['at'] ),
				'canDelete' => LR_Plugin::can_moderate() || ( $me && $c['person'] === $me['id'] ),
			);
		}
		return $out;
	}

	public static function add_comment() {
		self::guard();
		self::throttle( 'comment', 200 );
		$post = self::get_owned_pin();
		$text  = trim( sanitize_textarea_field( self::post( 'text' ) ) );
		$defer = (bool) self::post( 'with_media' );
		if ( '' === $text && ! $defer ) {
			wp_send_json_error( array( 'message' => 'Le commentaire est vide.' ), 400 );
		}
		$me   = self::me();
		$list = self::comments( $post->ID );
		if ( count( $list ) >= 200 ) {
			wp_send_json_error( array( 'message' => 'Trop de commentaires sur ce ticket.' ), 400 );
		}
		$cid    = strtolower( wp_generate_password( 12, false, false ) );
		$list[] = array(
			'id'      => $cid,
			'person'  => $me['id'],
			'name'    => $me['name'],
			'email'   => $me['email'],
			'text'    => mb_substr( $text, 0, 3000 ),
			'at'      => time(),
			'media'   => array(),
			'pending' => $defer ? 1 : 0,
		);
		update_post_meta( $post->ID, '_lr_comments', $list );
		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ), 'cid' => $cid ) );
	}

	public static function delete_comment() {
		self::guard();
		$post = self::get_owned_pin();
		$list = self::comments( $post->ID );
		$i    = (int) self::post( 'index' );
		$me   = LR_Plugin::current_person();
		if ( ! isset( $list[ $i ] ) ) {
			wp_send_json_error( array( 'message' => 'Commentaire introuvable.' ), 404 );
		}
		if ( ! LR_Plugin::can_moderate() && ! ( $me && $list[ $i ]['person'] === $me['id'] ) ) {
			wp_send_json_error( array( 'message' => 'Vous ne pouvez pas supprimer ce commentaire.' ), 403 );
		}
		if ( ! empty( $list[ $i ]['media'] ) ) {
			LR_Media::remove( $list[ $i ]['media'] );
		}
		array_splice( $list, $i, 1 );
		update_post_meta( $post->ID, '_lr_comments', $list );
		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ) ) );
	}

	/** Retrouve un commentaire par son identifiant ; vérifie qu'il appartient à la personne courante. */
	private static function find_own_comment( $post_id, &$list, $strict = false ) {
		$cid  = sanitize_key( self::post( 'cid' ) );
		$list = self::comments( $post_id );
		foreach ( $list as $i => $c ) {
			if ( '' !== $cid && self::comment_id( $c ) === $cid ) {
				$mine = self::is_my_comment( $c );
				if ( ! $mine && ( $strict || ! LR_Plugin::can_moderate() ) ) {
					wp_send_json_error( array( 'message' => 'Ce commentaire n\'est pas le vôtre.' ), 403 );
				}
				return $i;
			}
		}
		wp_send_json_error( array( 'message' => 'Commentaire introuvable.' ), 404 );
	}

	/** Pièce jointe (image ou vidéo) d'un commentaire, enregistrée sur le site. */
	public static function add_comment_media() {
		self::guard();
		self::throttle( 'cmedia', 120 );
		$post = self::get_owned_pin();
		$list = array();
		$i    = self::find_own_comment( $post->ID, $list );
		$media = isset( $list[ $i ]['media'] ) && is_array( $list[ $i ]['media'] ) ? $list[ $i ]['media'] : array();
		if ( count( $media ) >= 4 ) {
			wp_send_json_error( array( 'message' => '4 pièces jointes maximum par commentaire.' ), 400 );
		}
		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => 'Fichier manquant ou trop lourd.' ), 400 );
		}
		$tmp  = $_FILES['file']['tmp_name']; // phpcs:ignore
		$size = (int) $_FILES['file']['size']; // phpcs:ignore
		list( $kind, $mime ) = LR_Media::sniff( $tmp );
		if ( '' === $kind ) {
			wp_send_json_error( array( 'message' => 'Format non pris en charge (images JPG, PNG, WebP, GIF, AVIF ; vidéos MP4, MOV, WebM).' ), 400 );
		}
		if ( $size > ( 'video' === $kind ? 100 : 20 ) * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => 'Fichier trop lourd (' . ( 'video' === $kind ? '100' : '20' ) . ' Mo maximum).' ), 413 );
		}
		$m = LR_Media::send( $tmp, $kind, $mime, 30 );
		if ( is_wp_error( $m ) ) {
			wp_send_json_error( array( 'message' => $m->get_error_message() ), 502 );
		}
		// Relecture juste avant l'écriture (plusieurs envois successifs)
		$i2 = self::find_own_comment( $post->ID, $list );
		if ( ! isset( $list[ $i2 ]['media'] ) || ! is_array( $list[ $i2 ]['media'] ) ) {
			$list[ $i2 ]['media'] = array();
		}
		$list[ $i2 ]['media'][] = $m;
		update_post_meta( $post->ID, '_lr_comments', $list );
		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ) ) );
	}

	/** Modification d'un commentaire : uniquement par son auteur. */
	public static function edit_comment() {
		self::guard();
		self::throttle( 'cedit', 200 );
		$post = self::get_owned_pin();
		$list = array();
		$i    = self::find_own_comment( $post->ID, $list, true );
		$text = mb_substr( trim( sanitize_textarea_field( self::post( 'text' ) ) ), 0, 3000 );
		if ( '' === $text && empty( $list[ $i ]['media'] ) ) {
			wp_send_json_error( array( 'message' => 'Le commentaire est vide.' ), 400 );
		}
		if ( $text !== (string) $list[ $i ]['text'] ) {
			$list[ $i ]['id']     = self::comment_id( $list[ $i ] );
			$list[ $i ]['text']   = $text;
			$list[ $i ]['edited'] = time();
			update_post_meta( $post->ID, '_lr_comments', $list );
		}
		wp_send_json_success( array( 'pin' => self::format_pin( get_post( $post->ID ) ) ) );
	}

}
