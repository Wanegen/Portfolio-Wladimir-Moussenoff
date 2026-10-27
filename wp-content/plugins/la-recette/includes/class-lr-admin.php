<?php
/**
 * Back-office : page de réglages, colonnes de la liste, metabox, bouton de la barre d'admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LR_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'option_page_capability_lr', array( __CLASS__, 'settings_cap' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );

		add_filter( 'manage_' . LR_Plugin::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LR_Plugin::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );

		add_action( 'add_meta_boxes_' . LR_Plugin::CPT, array( __CLASS__, 'metabox' ) );
		add_action( 'save_post_' . LR_Plugin::CPT, array( __CLASS__, 'save_metabox' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Réglages                                                            */
	/* ------------------------------------------------------------------ */

	/** Écrans du back-office de La Recette (liste, fiche, réglages). */
	public static function is_lr_screen() {
		$sc = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $sc && ( LR_Plugin::CPT === $sc->post_type || false !== strpos( (string) $sc->id, 'lr' ) );
	}

	/** Même style que le front : DM Sans, #516ef8, cartes arrondies. */
	public static function assets() {
		if ( ! self::is_lr_screen() ) {
			return;
		}
		wp_enqueue_style( 'lr-font', 'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_enqueue_style( 'lr-admin', LR_URL . 'assets/admin.css', array( 'lr-font' ), LR_VERSION );
	}

	public static function body_class( $classes ) {
		return self::is_lr_screen() ? $classes . ' lr-admin' : $classes;
	}

	/** Les administrateurs enregistrent les réglages. */
	public static function settings_cap() {
		return 'manage_options';
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LR_Plugin::CPT,
			'Réglages de La Recette',
			'Réglages',
			'manage_options',
			'lr-settings',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'lr',
			LR_Plugin::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$old = LR_Plugin::settings();
		if ( ! LR_Plugin::is_owner() ) {
			return $old;
		}

		// Retrait de participants.
		if ( ! empty( $in['people_remove'] ) && is_array( $in['people_remove'] ) ) {
			$people = LR_Plugin::people();
			foreach ( $in['people_remove'] as $pid ) {
				unset( $people[ sanitize_key( $pid ) ] );
			}
			update_option( LR_Plugin::PEOPLE, $people, false );
		}

		return array(
			'enabled'      => empty( $in['enabled'] ) ? 0 : 1,
			'public'       => empty( $in['public'] ) ? 0 : 1,
			// Nouveau lien : l'ancien ne donne plus accès.
			'access_token' => ! empty( $in['new_token'] ) ? LR_Plugin::new_token() : $old['access_token'],
		);
	}

	public static function settings_page() {
		if ( ! LR_Plugin::is_owner() ) {
			wp_die( 'Accès réservé aux administrateurs.' );
		}
		$s      = LR_Plugin::settings();
		$opt    = LR_Plugin::OPT;
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$people = LR_Plugin::people();
		?>
		<div class="wrap lr-a-wrap">
			<div class="lr-a-head">
				<span class="lr-a-logo" aria-hidden="true"><svg viewBox="0 0 20 20" width="22" height="22" fill="currentColor"><circle cx="5.6" cy="7.6" r="3.3"/><circle cx="14.4" cy="7.6" r="3.3"/><circle cx="10" cy="5.6" r="3.9"/><rect x="5" y="8" width="10" height="6.2"/><rect x="5" y="15" width="10" height="2.6" rx=".6"/></svg></span>
				<div>
					<h1>La Recette</h1>
					<p><?php echo esc_html( $host ); ?> · v<?php echo esc_html( LR_VERSION ); ?></p>
				</div>
				<a class="lr-a-btn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . LR_Plugin::CPT ) ); ?>">Tous les tickets</a>
			</div>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" class="lr-a-form">
				<?php settings_fields( 'lr' ); ?>

				<section class="lr-a-card">
					<h2>Recette</h2>
					<label class="lr-a-switch"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>><span>Activer l'outil de recette sur le site</span></label>
					<p class="lr-a-desc">Une fois activée, La Recette apparaît pour <strong>les personnes connectées au back-office</strong> et pour celles qui ont ouvert <strong>le lien d'accès</strong> ci-dessous. Les autres visiteurs ne voient rien.</p>
					<label class="lr-a-switch"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[public]" value="1" <?php checked( $s['public'] ); ?>><span>Ouvrir à tous les visiteurs</span></label>
					<p class="lr-a-desc">À réserver à une préproduction : chaque visiteur peut alors s'inscrire (nom et e-mail) et créer des tickets.</p>
				</section>

				<section class="lr-a-card">
					<h2>Lien d'accès</h2>
					<p class="lr-a-desc">Envoyez ce lien aux personnes qui doivent recetter le site sans compte WordPress. En l'ouvrant, elles peuvent s'inscrire avec leur nom et leur e-mail, poser des tickets et les commenter (accès valable 6 mois sur leur navigateur).</p>
					<label class="lr-a-field"><span>Lien</span><input type="text" readonly value="<?php echo esc_attr( LR_Plugin::access_link() ); ?>" onclick="this.select()"></label>
					<label class="lr-a-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[new_token]" value="1"> Générer un nouveau lien (l'ancien ne fonctionnera plus)</label>
				</section>

				<section class="lr-a-card">
					<h2>Participants <span class="lr-a-count"><?php echo (int) count( $people ); ?></span></h2>
					<?php if ( ! $people ) : ?>
						<p class="lr-a-desc">Aucun participant pour l'instant. Toute personne qui s'inscrit (nom et e-mail) ou qui utilise l'outil avec son compte devient assignable.</p>
					<?php else : ?>
						<table class="lr-a-table">
							<thead><tr><th>Nom</th><th>E-mail</th><th>Depuis le</th><th>Retirer</th></tr></thead>
							<tbody>
							<?php foreach ( $people as $p ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
									<td><?php echo esc_html( $p['email'] ); ?></td>
									<td><?php echo esc_html( wp_date( 'd/m/Y', (int) $p['at'] ) ); ?></td>
									<td><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[people_remove][]" value="<?php echo esc_attr( $p['id'] ); ?>"></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p class="lr-a-desc">Un participant retiré peut se réinscrire tant qu'il a accès à la recette.</p>
					<?php endif; ?>
				</section>

				<section class="lr-a-card">
					<h2>Captures (image / vidéo)</h2>
					<p class="lr-a-state is-ok">Les captures et pièces jointes sont enregistrées sur ce site (dossier des médias, sous-dossier « la-recette ») et supprimées avec leur ticket.</p>
				</section>

				<div class="lr-a-actions"><?php submit_button( 'Enregistrer les réglages', 'primary', 'submit', false ); ?></div>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Liste des points                                                    */
	/* ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'author' === $k ) {
				continue;
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['lr_status']   = 'Statut';
				$new['lr_assignee'] = 'Assigné à';
				$new['lr_page']     = 'Page';
				$new['lr_author']   = 'Auteur';
				$new['lr_media']    = 'Captures';
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		switch ( $col ) {
			case 'lr_status':
				$st = get_post_meta( $post_id, '_lr_status', true );
				printf( '<span class="lr-a-chip %s">%s</span>', 'done' === $st ? 'is-done' : 'is-open', esc_html( LR_Plugin::status_label( $st ) ) );
				break;
			case 'lr_assignee':
				$a = LR_Plugin::get_assignee( (string) get_post_meta( $post_id, '_lr_assignee', true ) );
				echo $a ? '<span class="lr-a-chip is-who">' . esc_html( $a['name'] ) . '</span>' : '<span class="lr-a-none">Non assigné</span>';
				break;
			case 'lr_page':
				$page  = (string) get_post_meta( $post_id, '_lr_page', true );
				$title = (string) get_post_meta( $post_id, '_lr_page_title', true );
				printf(
					'<a class="lr-a-page" href="%s" target="_blank">%s</a><span class="lr-a-path">%s</span>',
					esc_url( home_url( $page ) . '#lr-pin-' . $post_id ),
					esc_html( $title ? $title : $page ),
					esc_html( $page )
				);
				break;
			case 'lr_media':
				$img = 0;
				$vid = 0;
				foreach ( LR_Media::get( $post_id ) as $m ) {
					'video' === $m['type'] ? $vid++ : $img++;
				}
				$bits = array();
				if ( $img ) {
					$bits[] = $img . ' image' . ( $img > 1 ? 's' : '' );
				}
				if ( $vid ) {
					$bits[] = $vid . ' vidéo' . ( $vid > 1 ? 's' : '' );
				}
				echo $bits ? '<span class="lr-a-chip is-media">' . esc_html( implode( ', ', $bits ) ) . '</span>' : '<span class="lr-a-none">—</span>';
				break;
			case 'lr_author':
				echo esc_html( (string) get_post_meta( $post_id, '_lr_author_name', true ) );
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( LR_Plugin::CPT === $post->post_type ) {
			$page            = (string) get_post_meta( $post->ID, '_lr_page', true );
			$actions['view'] = sprintf( '<a href="%s" target="_blank">Voir sur la page</a>', esc_url( home_url( $page ) . '#lr-pin-' . $post->ID ) );
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	public static function filters( $post_type ) {
		if ( LR_Plugin::CPT !== $post_type ) {
			return;
		}
		$st = isset( $_GET['lr_status'] ) ? sanitize_key( $_GET['lr_status'] ) : ''; // phpcs:ignore
		$as = isset( $_GET['lr_assignee'] ) ? sanitize_key( $_GET['lr_assignee'] ) : ''; // phpcs:ignore
		echo '<select name="lr_status"><option value="">Tous les statuts</option>';
		foreach ( array( 'open' => 'À traiter', 'done' => 'Résolu' ) as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $st, $k, false ), esc_html( $l ) );
		}
		echo '</select><select name="lr_assignee"><option value="">Toutes les personnes</option>';
		foreach ( LR_Plugin::assignees() as $a ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $a['id'] ), selected( $as, $a['id'], false ), esc_html( $a['name'] ) );
		}
		echo '</select>';
	}

	public static function apply_filters( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || LR_Plugin::CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		$mq = array();
		if ( ! empty( $_GET['lr_status'] ) ) { // phpcs:ignore
			$mq[] = array(
				'key'   => '_lr_status',
				'value' => sanitize_key( $_GET['lr_status'] ), // phpcs:ignore
			);
		}
		if ( ! empty( $_GET['lr_assignee'] ) ) { // phpcs:ignore
			$mq[] = array(
				'key'   => '_lr_assignee',
				'value' => sanitize_key( $_GET['lr_assignee'] ), // phpcs:ignore
			);
		}
		if ( $mq ) {
			$q->set( 'meta_query', $mq );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Metabox                                                             */
	/* ------------------------------------------------------------------ */

	public static function metabox() {
		add_meta_box( 'lr-details', 'Détails du ticket', array( __CLASS__, 'render_metabox' ), LR_Plugin::CPT, 'side', 'high' );
		add_meta_box( 'lr-media', 'Captures', array( __CLASS__, 'render_media_box' ), LR_Plugin::CPT, 'normal', 'high' );
		add_meta_box( 'lr-comments', 'Commentaires', array( __CLASS__, 'render_comments_box' ), LR_Plugin::CPT, 'normal', 'default' );
	}

	public static function render_metabox( $post ) {
		wp_nonce_field( 'lr_meta', 'lr_meta_nonce' );
		$st   = get_post_meta( $post->ID, '_lr_status', true );
		$as   = (string) get_post_meta( $post->ID, '_lr_assignee', true );
		$page = (string) get_post_meta( $post->ID, '_lr_page', true );
		$vw   = (int) get_post_meta( $post->ID, '_lr_vw', true );
		$vh   = (int) get_post_meta( $post->ID, '_lr_vh', true );
		$ua   = (string) get_post_meta( $post->ID, '_lr_ua', true );
		?>
		<p><label><strong>Statut</strong><br>
			<select name="lr_status" style="width:100%">
				<option value="open" <?php selected( 'done' !== $st ); ?>>À traiter</option>
				<option value="done" <?php selected( 'done', $st ); ?>>Résolu</option>
			</select></label></p>
		<p><label><strong>Assigné à</strong><br>
			<select name="lr_assignee" style="width:100%">
				<option value="">Non assigné</option>
				<?php foreach ( LR_Plugin::assignees() as $a ) : ?>
					<option value="<?php echo esc_attr( $a['id'] ); ?>" <?php selected( $as, $a['id'] ); ?>><?php echo esc_html( $a['name'] ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
		<p><strong>Page</strong><br><a href="<?php echo esc_url( home_url( $page ) . '#lr-pin-' . $post->ID ); ?>" target="_blank"><?php echo esc_html( $page ); ?></a></p>
		<p><strong>Auteur</strong><br><?php echo esc_html( (string) get_post_meta( $post->ID, '_lr_author_name', true ) ); ?></p>
		<?php $ed = (int) get_post_meta( $post->ID, '_lr_edited', true ); ?>
		<?php if ( $ed ) : ?>
			<p><strong>Modifié</strong><br><?php echo esc_html( wp_date( 'd/m/Y H:i', $ed ) . ' par ' . (string) get_post_meta( $post->ID, '_lr_edited_by', true ) ); ?></p>
		<?php endif; ?>
		<?php if ( $vw ) : ?>
			<p><strong>Écran</strong><br><?php echo (int) $vw . ' × ' . (int) $vh; ?> px</p>
		<?php endif; ?>
		<?php if ( $ua ) : ?>
			<p><strong>Navigateur</strong><br><small><?php echo esc_html( $ua ); ?></small></p>
		<?php endif; ?>
		<?php
	}

	public static function render_comments_box( $post ) {
		$list = get_post_meta( $post->ID, '_lr_comments', true );
		if ( ! is_array( $list ) || ! $list ) {
			echo '<p style="color:#777">Aucun commentaire.</p>';
			return;
		}
		foreach ( $list as $c ) {
			$files = '';
			foreach ( ( ! empty( $c['media'] ) && is_array( $c['media'] ) ) ? $c['media'] : array() as $m ) {
				$thumb  = 'video' === $m['type'] ? ( isset( $m['poster'] ) ? $m['poster'] : '' ) : $m['url'];
				$files .= sprintf(
					'<a href="%s" target="_blank" rel="noopener" class="lr-a-file%s"><img src="%s" alt="" loading="lazy"></a>',
					esc_url( $m['url'] ),
					'video' === $m['type'] ? ' is-video' : '',
					esc_url( $thumb )
				);
			}
			printf(
				'<div class="lr-a-cmt"><strong>%s</strong> <span>%s · %s</span><div class="lr-a-cmt-text">%s</div>%s</div>',
				esc_html( $c['name'] ),
				esc_html( $c['email'] ),
				esc_html( wp_date( 'd/m/Y H:i', (int) $c['at'] ) . ( ! empty( $c['edited'] ) ? ' · modifié le ' . wp_date( 'd/m/Y H:i', (int) $c['edited'] ) : '' ) ),
				esc_html( $c['text'] ),
				$files ? '<div class="lr-a-files">' . $files . '</div>' : '' // phpcs:ignore WordPress.Security.EscapeOutput
			);
		}
	}

	public static function render_media_box( $post ) {
		$list = LR_Media::get( $post->ID );
		if ( ! $list ) {
			echo '<p style="color:#777">Aucune capture pour ce point.</p>';
			return;
		}
		echo '<div style="display:flex;flex-wrap:wrap;gap:16px">';
		foreach ( $list as $m ) {
			echo '<figure style="margin:0;max-width:100%">';
			if ( 'video' === $m['type'] ) {
				printf(
					'<video src="%s" poster="%s" controls muted playsinline preload="metadata" style="display:block;max-width:100%%;width:720px;border:1px solid #ddd;border-radius:6px;background:#000"></video>',
					esc_url( $m['url'] ),
					esc_url( isset( $m['poster'] ) ? $m['poster'] : '' )
				);
			} else {
				printf(
					'<a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="" loading="lazy" style="display:block;max-width:100%%;width:720px;height:auto;border:1px solid #ddd;border-radius:6px"></a>',
					esc_url( $m['url'] )
				);
			}
			printf(
				'<figcaption style="font-size:12px;color:#666;margin-top:4px">%s · %d × %d · %s · %s</figcaption>',
				'video' === $m['type'] ? 'Vidéo' : 'Image',
				(int) $m['w'],
				(int) $m['h'],
				esc_html( size_format( (int) $m['bytes'], 1 ) ),
				esc_html( wp_date( 'd/m/Y H:i', (int) $m['at'] ) )
			);
			echo '</figure>';
		}
		echo '</div>';
	}

	public static function save_metabox( $post_id ) {
		if ( ! isset( $_POST['lr_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lr_meta_nonce'] ) ), 'lr_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$st = isset( $_POST['lr_status'] ) && 'done' === $_POST['lr_status'] ? 'done' : 'open';
		update_post_meta( $post_id, '_lr_status', $st );

		$a = LR_Plugin::get_assignee( isset( $_POST['lr_assignee'] ) ? sanitize_key( $_POST['lr_assignee'] ) : '' );
		update_post_meta( $post_id, '_lr_assignee', $a ? $a['id'] : '' );
	}
}
