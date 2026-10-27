<?php
/**
 * Captures (image / vidéo) : enregistrées sur ce site, dans wp-content/uploads/la-recette/.
 *
 * Le navigateur envoie la capture à WordPress (admin-ajax) ; le fichier est vérifié (type réel),
 * renommé au hasard et rangé par mois. Rien n'est envoyé ailleurs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LR_Media {

	const META      = '_lr_media';
	const MAX_ITEMS = 6;
	const DIR       = 'la-recette';

	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ), 10, 2 );
	}

	/** Le stockage est toujours disponible (dossier des médias du site). */
	public static function is_configured() {
		return true;
	}

	/** Liste des médias d'un point. */
	public static function get( $post_id ) {
		$m = get_post_meta( $post_id, self::META, true );
		return is_array( $m ) ? array_values( $m ) : array();
	}

	/** Version publique d'un élément. */
	public static function public_item( $m, $i ) {
		return array(
			'i'      => $i,
			'type'   => 'video' === $m['type'] ? 'video' : 'image',
			'url'    => esc_url_raw( $m['url'] ),
			'poster' => isset( $m['poster'] ) ? esc_url_raw( $m['poster'] ) : '',
			'w'      => isset( $m['w'] ) ? (int) $m['w'] : 0,
			'h'      => isset( $m['h'] ) ? (int) $m['h'] : 0,
		);
	}

	/** Version publique (sans chemin interne) pour le front. */
	public static function public_list( $post_id ) {
		$out = array();
		foreach ( self::get( $post_id ) as $i => $m ) {
			$out[] = self::public_item( $m, $i );
		}
		return $out;
	}

	/** Extension du fichier enregistré, d'après son type réel. */
	private static function ext( $mime ) {
		$map = array(
			'image/webp'      => 'webp',
			'image/png'       => 'png',
			'image/jpeg'      => 'jpg',
			'image/gif'       => 'gif',
			'image/avif'      => 'avif',
			'video/webm'      => 'webm',
			'video/mp4'       => 'mp4',
			'video/quicktime' => 'mov',
		);
		return isset( $map[ $mime ] ) ? $map[ $mime ] : '';
	}

	/**
	 * Enregistre un fichier envoyé dans le dossier des médias du site. Retourne le tableau média ou WP_Error.
	 */
	public static function send( $file, $kind, $mime, $dur = 5 ) {
		$ext = self::ext( $mime );
		if ( '' === $ext ) {
			return new WP_Error( 'lr_media', 'Format de capture non pris en charge.' );
		}
		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			return new WP_Error( 'lr_media', 'Dossier des médias inaccessible.' );
		}
		$rel = self::DIR . '/' . gmdate( 'Y/m' );
		$dir = trailingslashit( $up['basedir'] ) . $rel;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'lr_media', 'Impossible de créer le dossier des captures.' );
		}
		self::protect( trailingslashit( $up['basedir'] ) . self::DIR );

		$name = strtolower( wp_generate_password( 24, false, false ) ) . '.' . $ext;
		$dest = $dir . '/' . $name;
		$ok   = is_uploaded_file( $file ) ? move_uploaded_file( $file, $dest ) : copy( $file, $dest );
		if ( ! $ok ) {
			return new WP_Error( 'lr_media', 'Enregistrement de la capture impossible.' );
		}
		@chmod( $dest, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$w = 0;
		$h = 0;
		if ( 'image' === $kind ) {
			$size = @getimagesize( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $size ) {
				$w = (int) $size[0];
				$h = (int) $size[1];
			}
		}
		return array(
			'type'   => 'video' === $kind ? 'video' : 'image',
			'url'    => esc_url_raw( trailingslashit( $up['baseurl'] ) . $rel . '/' . $name ),
			'poster' => '',
			'path'   => $rel . '/' . $name,
			'w'      => $w,
			'h'      => $h,
			'bytes'  => (int) filesize( $dest ),
			'at'     => time(),
		);
	}

	/** Empêche le listage du dossier des captures. */
	private static function protect( $base ) {
		if ( ! file_exists( $base . '/index.php' ) ) {
			@file_put_contents( $base . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore
		}
	}

	/** Supprime des fichiers de captures (silencieux en cas d'échec). */
	public static function remove( $items ) {
		$up   = wp_upload_dir();
		$base = trailingslashit( $up['basedir'] );
		foreach ( (array) $items as $m ) {
			$p = isset( $m['path'] ) ? (string) $m['path'] : '';
			if ( 0 === strpos( $p, self::DIR . '/' ) && false === strpos( $p, '..' ) && is_file( $base . $p ) ) {
				@unlink( $base . $p ); // phpcs:ignore
			}
		}
	}

	public static function on_delete_post( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( $post && LR_Plugin::CPT === $post->post_type ) {
			self::remove( self::get( $post_id ) );
			// Pièces jointes des commentaires
			$comments = get_post_meta( $post_id, '_lr_comments', true );
			foreach ( is_array( $comments ) ? $comments : array() as $c ) {
				if ( ! empty( $c['media'] ) ) {
					self::remove( $c['media'] );
				}
			}
		}
	}

	/** Détecte le type réel du fichier envoyé. */
	public static function sniff( $file ) {
		$mime = '';
		if ( function_exists( 'finfo_open' ) ) {
			$fi   = finfo_open( FILEINFO_MIME_TYPE );
			$mime = (string) finfo_file( $fi, $file );
			finfo_close( $fi );
		}
		$map = array(
			'image/webp'      => 'image',
			'image/png'       => 'image',
			'image/jpeg'      => 'image',
			'image/gif'       => 'image',
			'image/avif'      => 'image',
			'video/webm'      => 'video',
			'audio/webm'      => 'video',
			'video/mp4'       => 'video',
			'video/quicktime' => 'video',
		);
		return isset( $map[ $mime ] ) ? array( $map[ $mime ], 'audio/webm' === $mime ? 'video/webm' : $mime ) : array( '', $mime );
	}
}
