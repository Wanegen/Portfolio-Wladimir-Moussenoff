<?php
/**
 * Champs SCF déclarés dans le code : la structure est versionnée avec le thème
 * et ne peut pas être cassée depuis l'administration.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$ancre = function ( $layout, $default ) {
		return array(
			'key'          => "field_mx_{$layout}_ancre",
			'label'        => 'Ancre',
			'name'         => 'ancre',
			'type'         => 'text',
			'instructions' => "Identifiant utilisé dans les liens du menu (ex. #{$default}). Laisser vide pour « {$default} ».",
			'placeholder'  => $default,
			'wrapper'      => array( 'width' => 30 ),
		);
	};

	$layouts = array(
		/* HAUT DE PAGE */
		'layout_mx_hero'        => array(
			'key'        => 'layout_mx_hero',
			'name'       => 'hero',
			'label'      => 'Haut de page (vidéo)',
			'display'    => 'block',
			'sub_fields' => array(
				array( 'key' => 'field_mx_hero_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'required' => 1 ),
				array(
					'key'          => 'field_mx_hero_sous_titre',
					'label'        => 'Sous-titre',
					'name'         => 'sous_titre',
					'type'         => 'text',
					'instructions' => 'Séparez les mots par une barre verticale « | » pour les afficher séparés (ex. Vidéaste | Monteur | Photographe).',
				),
				array(
					'key'           => 'field_mx_hero_plein',
					'label'         => 'Hauteur',
					'name'          => 'plein_ecran',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'message'       => 'Toute la hauteur de l\'écran (sinon environ 70 %)',
					'wrapper'       => array( 'width' => 40 ),
				),
				array(
					'key'           => 'field_mx_hero_bouton',
					'label'         => 'Bouton',
					'name'          => 'bouton',
					'type'          => 'link',
					'return_format' => 'array',
					'instructions'  => 'Facultatif.',
					'wrapper'       => array( 'width' => 60 ),
				),
				array(
					'key'           => 'field_mx_hero_video_type',
					'label'         => 'Vidéo de fond',
					'name'          => 'video_type',
					'type'          => 'button_group',
					'choices'       => array( 'youtube' => 'YouTube', 'fichier' => 'Fichier vidéo', 'aucune' => 'Aucune' ),
					'default_value' => 'youtube',
				),
				array(
					'key'               => 'field_mx_hero_youtube',
					'label'             => 'Lien YouTube',
					'name'              => 'video_youtube',
					'type'              => 'url',
					'instructions'      => 'La vidéo est lue en boucle, sans le son.',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_hero_video_type', 'operator' => '==', 'value' => 'youtube' ) ) ),
				),
				array(
					'key'               => 'field_mx_hero_fichier',
					'label'             => 'Fichier vidéo',
					'name'              => 'video_fichier',
					'type'              => 'file',
					'return_format'     => 'url',
					'mime_types'        => 'mp4,webm',
					'instructions'      => 'MP4 ou WebM, idéalement moins de 15 Mo. Lue en boucle, sans le son.',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_hero_video_type', 'operator' => '==', 'value' => 'fichier' ) ) ),
				),
				array(
					'key'           => 'field_mx_hero_image',
					'label'         => 'Image de fond',
					'name'          => 'image',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => 'Affichée pendant le chargement de la vidéo, ou seule si aucune vidéo.',
				),
			),
		),

		/* HAUT DE PAGE INTÉRIEUR */
		'layout_mx_entete'      => array(
			'key'        => 'layout_mx_entete',
			'name'       => 'entete',
			'label'      => 'Haut de page (pages intérieures)',
			'display'    => 'block',
			'sub_fields' => array(
				array( 'key' => 'field_mx_ent_surtitre', 'label' => 'Surtitre', 'name' => 'surtitre', 'type' => 'text', 'wrapper' => array( 'width' => 40 ) ),
				array( 'key' => 'field_mx_ent_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'required' => 1, 'wrapper' => array( 'width' => 60 ) ),
				array(
					'key'           => 'field_mx_ent_video_type',
					'label'         => 'Vidéo de fond (très atténuée)',
					'name'          => 'video_type',
					'type'          => 'button_group',
					'choices'       => array( 'youtube' => 'YouTube', 'fichier' => 'Fichier vidéo', 'aucune' => 'Aucune' ),
					'default_value' => 'youtube',
				),
				array(
					'key'               => 'field_mx_ent_youtube',
					'label'             => 'Lien YouTube',
					'name'              => 'video_youtube',
					'type'              => 'url',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_ent_video_type', 'operator' => '==', 'value' => 'youtube' ) ) ),
				),
				array(
					'key'               => 'field_mx_ent_fichier',
					'label'             => 'Fichier vidéo',
					'name'              => 'video_fichier',
					'type'              => 'file',
					'return_format'     => 'url',
					'mime_types'        => 'mp4,webm',
					'instructions'      => 'MP4 ou WebM, idéalement moins de 15 Mo.',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_ent_video_type', 'operator' => '==', 'value' => 'fichier' ) ) ),
				),
				array(
					'key'           => 'field_mx_ent_bouton_on',
					'label'         => 'Bouton',
					'name'          => 'bouton_on',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'message'       => 'Afficher le bouton',
					'wrapper'       => array( 'width' => 30 ),
				),
				array(
					'key'               => 'field_mx_ent_bouton',
					'label'             => 'Lien du bouton',
					'name'              => 'bouton',
					'type'              => 'link',
					'return_format'     => 'array',
					'instructions'      => 'Laisser vide pour « Contactez-moi » vers la page Contact.',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_ent_bouton_on', 'operator' => '==', 'value' => '1' ) ) ),
					'wrapper'           => array( 'width' => 70 ),
				),
			),
		),

		/* MÉDIA + TEXTE */
		'layout_mx_media_texte' => array(
			'key'        => 'layout_mx_media_texte',
			'name'       => 'media_texte',
			'label'      => '2 colonnes : média et texte',
			'display'    => 'block',
			'sub_fields' => array(
				array( 'key' => 'field_mx_mt_ancre', 'label' => 'Ancre', 'name' => 'ancre', 'type' => 'text', 'instructions' => 'Facultatif : identifiant pour un lien du menu (ex. #approche).', 'wrapper' => array( 'width' => 30 ) ),
				array(
					'key'           => 'field_mx_mt_disposition',
					'label'         => 'Position du média',
					'name'          => 'disposition',
					'type'          => 'button_group',
					'choices'       => array( 'gauche' => 'Média à gauche', 'droite' => 'Média à droite' ),
					'default_value' => 'gauche',
					'wrapper'       => array( 'width' => 70 ),
				),
				array( 'key' => 'field_mx_mt_surtitre', 'label' => 'Surtitre', 'name' => 'surtitre', 'type' => 'text', 'wrapper' => array( 'width' => 40 ) ),
				array( 'key' => 'field_mx_mt_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'wrapper' => array( 'width' => 60 ) ),
				array( 'key' => 'field_mx_mt_texte', 'label' => 'Paragraphe', 'name' => 'texte', 'type' => 'wysiwyg', 'toolbar' => 'basic', 'media_upload' => 0, 'tabs' => 'visual' ),
				array(
					'key'           => 'field_mx_mt_media',
					'label'         => 'Média',
					'name'          => 'media',
					'type'          => 'button_group',
					'choices'       => array( 'image' => 'Image', 'youtube' => 'Vidéo YouTube', 'fichier' => 'Fichier vidéo' ),
					'default_value' => 'image',
				),
				array(
					'key'               => 'field_mx_mt_image',
					'label'             => 'Image',
					'name'              => 'image',
					'type'              => 'image',
					'return_format'     => 'id',
					'preview_size'      => 'medium',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_mt_media', 'operator' => '==', 'value' => 'image' ) ) ),
				),
				array(
					'key'               => 'field_mx_mt_youtube',
					'label'             => 'Lien YouTube',
					'name'              => 'video_youtube',
					'type'              => 'url',
					'instructions'      => 'La miniature s\'affiche, la vidéo se lance au clic.',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_mt_media', 'operator' => '==', 'value' => 'youtube' ) ) ),
				),
				array(
					'key'               => 'field_mx_mt_fichier',
					'label'             => 'Fichier vidéo',
					'name'              => 'video_fichier',
					'type'              => 'file',
					'return_format'     => 'url',
					'mime_types'        => 'mp4,webm',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_mt_media', 'operator' => '==', 'value' => 'fichier' ) ) ),
				),
				array(
					'key'               => 'field_mx_mt_miniature',
					'label'             => 'Miniature',
					'name'              => 'miniature',
					'type'              => 'image',
					'return_format'     => 'id',
					'preview_size'      => 'medium',
					'instructions'      => 'Facultatif : image affichée avant le lancement de la vidéo (format 16:9 conseillé). Sans miniature : celle de YouTube, ou la première image du fichier.',
					'conditional_logic' => array( array( array( 'field' => 'field_mx_mt_media', 'operator' => '!=', 'value' => 'image' ) ) ),
				),
				array(
					'key'           => 'field_mx_mt_cats',
					'label'         => 'Catégories vidéo',
					'name'          => 'categories',
					'type'          => 'taxonomy',
					'taxonomy'      => 'filtre_video',
					'field_type'    => 'checkbox',
					'add_term'      => 1,
					'save_terms'    => 0,
					'load_terms'    => 0,
					'return_format' => 'id',
					'instructions'  => 'Filtres proposés dans la barre en bas de l\'écran (page de vidéos). Le « + » crée une catégorie.',
				),
			),
		),

		/* ACCORDÉONS */
		'layout_mx_accordeons'  => array(
			'key'        => 'layout_mx_accordeons',
			'name'       => 'accordeons',
			'label'      => 'Accordéons (services)',
			'display'    => 'block',
			'sub_fields' => array(
				$ancre( 'accordeons', 'services' ),
				array( 'key' => 'field_mx_acc_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'wrapper' => array( 'width' => 70 ) ),
				array(
					'key'          => 'field_mx_acc_elements',
					'label'        => 'Accordéons',
					'name'         => 'elements',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => 'Ajouter un accordéon',
					'collapsed'    => 'field_mx_acc_el_titre',
					'sub_fields'   => array(
						array( 'key' => 'field_mx_acc_el_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'required' => 1 ),
						array( 'key' => 'field_mx_acc_el_texte', 'label' => 'Description', 'name' => 'texte', 'type' => 'textarea', 'rows' => 3, 'new_lines' => 'br' ),
						array(
							'key'          => 'field_mx_acc_el_etiquettes',
							'label'        => 'Étiquettes',
							'name'         => 'etiquettes',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => 'Ajouter une étiquette',
							'sub_fields'   => array(
								array( 'key' => 'field_mx_acc_el_etiq_texte', 'label' => 'Texte', 'name' => 'texte', 'type' => 'text' ),
							),
						),
						array( 'key' => 'field_mx_acc_el_bouton', 'label' => 'Bouton', 'name' => 'bouton', 'type' => 'link', 'return_format' => 'array', 'instructions' => 'Facultatif (ex. « En savoir plus » vers l\'ancre du service sur la page Mes services).' ),
					),
				),
			),
		),

		/* LISTE DE VIDÉOS */
		'layout_mx_liste'       => array(
			'key'        => 'layout_mx_liste',
			'name'       => 'liste',
			'label'      => 'Liste de vidéos (avec filtres)',
			'display'    => 'block',
			'sub_fields' => array(
				$ancre( 'liste', 'projets' ),
				array( 'key' => 'field_mx_liste_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'wrapper' => array( 'width' => 70 ) ),
				array(
					'key'          => 'field_mx_liste_elements',
					'label'        => 'Vidéos',
					'name'         => 'elements',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => 'Ajouter une vidéo',
					'collapsed'    => 'field_mx_liste_el_titre',
					'sub_fields'   => array(
						array( 'key' => 'field_mx_liste_el_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'required' => 1, 'wrapper' => array( 'width' => 50 ) ),
						array( 'key' => 'field_mx_liste_el_video', 'label' => 'Lien YouTube', 'name' => 'video', 'type' => 'url', 'required' => 1, 'wrapper' => array( 'width' => 50 ) ),
						array(
							'key'           => 'field_mx_liste_el_filtres',
							'label'         => 'Filtres',
							'name'          => 'filtres',
							'type'          => 'taxonomy',
							'taxonomy'      => 'filtre_video',
							'field_type'    => 'checkbox',
							'add_term'      => 1,
							'save_terms'    => 0,
							'load_terms'    => 0,
							'return_format' => 'object',
							'instructions'  => 'Cochez les filtres de cette vidéo. Le bouton « + » crée un nouveau filtre.',
							'wrapper'       => array( 'width' => 50 ),
						),
						array(
							'key'           => 'field_mx_liste_el_miniature',
							'label'         => 'Miniature personnalisée',
							'name'          => 'miniature',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'instructions'  => 'Facultatif : sinon la miniature YouTube est utilisée.',
							'wrapper'       => array( 'width' => 50 ),
						),
					),
				),
				array( 'key' => 'field_mx_liste_bouton', 'label' => 'Bouton', 'name' => 'bouton', 'type' => 'link', 'return_format' => 'array', 'instructions' => 'Facultatif.' ),
			),
		),

		/* LOGOS */
		'layout_mx_logos'       => array(
			'key'        => 'layout_mx_logos',
			'name'       => 'logos',
			'label'      => 'Galerie de logos (défilement)',
			'display'    => 'block',
			'sub_fields' => array(
				$ancre( 'logos', 'clients' ),
				array( 'key' => 'field_mx_logos_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'wrapper' => array( 'width' => 70 ) ),
				array(
					'key'           => 'field_mx_logos_galerie',
					'label'         => 'Logos',
					'name'          => 'logos',
					'type'          => 'gallery',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'mime_types'    => 'png,webp,svg,jpg,jpeg',
					'instructions'  => 'Logos blancs sur fond transparent (PNG, WebP ou SVG). Ils sont tous mis à la même taille automatiquement. Le texte alternatif de chaque image sert de nom du client.',
				),
			),
		),

		/* PHOTOGRAPHIE */
		'layout_mx_photos'      => array(
			'key'        => 'layout_mx_photos',
			'name'       => 'photos',
			'label'      => 'Photographie (photos flottantes)',
			'display'    => 'block',
			'sub_fields' => array(
				$ancre( 'photos', 'photos' ),
				array( 'key' => 'field_mx_photos_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'wrapper' => array( 'width' => 70 ) ),
				array(
					'key'          => 'field_mx_photos_elements',
					'label'        => 'Photos',
					'name'         => 'photos',
					'type'         => 'repeater',
					'layout'       => 'table',
					'max'          => 10,
					'button_label' => 'Ajouter une photo',
					'instructions' => '10 photos au maximum (idéalement 7 portraits et 3 paysages), placées automatiquement autour du titre selon leur format. Le texte alternatif de l\'image est repris.',
					'sub_fields'   => array(
						array( 'key' => 'field_mx_photos_el_image', 'label' => 'Photo', 'name' => 'image', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'thumbnail', 'required' => 1 ),
					),
				),
				array( 'key' => 'field_mx_photos_bouton', 'label' => 'Bouton', 'name' => 'bouton', 'type' => 'link', 'return_format' => 'array', 'instructions' => 'Facultatif.' ),
			),
		),

		/* DÉFILEMENT IMAGES */
		'layout_mx_defilement'  => array(
			'key'        => 'layout_mx_defilement',
			'name'       => 'defilement',
			'label'      => 'Défilement images',
			'display'    => 'block',
			'sub_fields' => array(
				$ancre( 'defilement', 'galerie' ),
				array( 'key' => 'field_mx_defil_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'instructions' => 'Facultatif.', 'wrapper' => array( 'width' => 70 ) ),
				array(
					'key'          => 'field_mx_defil_images',
					'label'        => 'Images',
					'name'         => 'images',
					'type'         => 'repeater',
					'layout'       => 'table',
					'min'          => 1,
					'button_label' => 'Ajouter une image',
					'instructions' => 'Les photos s\'affichent sur 2 colonnes en quinconce, dans l\'ordre de la liste, et Une photo en paysage prend la largeur des 2 colonnes. Au moins 6 images conseillées.',
					'sub_fields'   => array(
						array( 'key' => 'field_mx_defil_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'thumbnail', 'required' => 1 ),
						array(
							'key'           => 'field_mx_defil_cats',
							'label'         => 'Catégories',
							'name'          => 'categories',
							'type'          => 'taxonomy',
							'taxonomy'      => 'filtre_photo',
							'field_type'    => 'checkbox',
							'add_term'      => 1,
							'save_terms'    => 0,
							'load_terms'    => 0,
							'return_format' => 'id',
							'instructions'  => 'Filtres proposés dans la barre en bas de l\'écran. Le « + » crée une catégorie.',
						),
					),
				),
			),
		),

		/* FORMULAIRE */
		'layout_mx_formulaire'  => array(
			'key'        => 'layout_mx_formulaire',
			'name'       => 'formulaire',
			'label'      => 'Formulaire de contact',
			'display'    => 'block',
			'sub_fields' => array(
				$ancre( 'formulaire', 'contact' ),
				array( 'key' => 'field_mx_form_titre', 'label' => 'Titre', 'name' => 'titre', 'type' => 'text', 'default_value' => 'Parlons de', 'wrapper' => array( 'width' => 35 ) ),
				array( 'key' => 'field_mx_form_titre_accent', 'label' => 'Suite du titre (en couleur)', 'name' => 'titre_accent', 'type' => 'text', 'default_value' => 'votre projet', 'wrapper' => array( 'width' => 35 ) ),
				array(
					'key'          => 'field_mx_form_id',
					'label'        => 'Formulaire WPForms',
					'name'         => 'formulaire',
					'type'         => 'select',
					'choices'      => array(),
					'allow_null'   => 1,
					'instructions' => 'L\'e-mail, le téléphone et les réseaux affichés à côté viennent du menu « Coordonnées ».',
				),
			),
		),
	);

	acf_add_local_field_group( array(
		'key'                   => 'group_mx_sections',
		'title'                 => 'Sections de la page',
		'fields'                => array(
			array(
				'key'          => 'field_mx_sections',
				'label'        => 'Sections',
				'name'         => 'sections',
				'type'         => 'flexible_content',
				'button_label' => 'Ajouter une section',
				'layouts'      => $layouts,
			),
		),
		'location'              => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ),
		'position'              => 'acf_after_title',
		'style'                 => 'seamless',
		'hide_on_screen'        => array( 'the_content', 'excerpt', 'discussion', 'comments', 'author', 'format', 'categories', 'tags', 'send-trackbacks' ),
	) );

	acf_add_local_field_group( array(
		'key'      => 'group_mx_coordonnees',
		'title'    => 'Coordonnées et réseaux',
		'fields'   => array(
			array( 'key' => 'field_mx_opt_email', 'label' => 'E-mail', 'name' => 'email', 'type' => 'email', 'wrapper' => array( 'width' => 50 ) ),
			array( 'key' => 'field_mx_opt_tel', 'label' => 'Téléphone', 'name' => 'telephone', 'type' => 'text', 'placeholder' => '06 00 00 00 00', 'instructions' => 'Masqué derrière « Afficher le téléphone » sur le site.', 'wrapper' => array( 'width' => 50 ) ),
			array( 'key' => 'field_mx_opt_linkedin', 'label' => 'LinkedIn', 'name' => 'linkedin', 'type' => 'url', 'wrapper' => array( 'width' => 50 ) ),
			array( 'key' => 'field_mx_opt_instagram', 'label' => 'Instagram', 'name' => 'instagram', 'type' => 'url', 'wrapper' => array( 'width' => 50 ) ),
			array( 'key' => 'field_mx_opt_metiers', 'label' => 'Métiers (pied de page)', 'name' => 'metiers', 'type' => 'text', 'default_value' => 'Vidéaste | Photographe | Monteur', 'wrapper' => array( 'width' => 50 ) ),
			array( 'key' => 'field_mx_opt_dispo', 'label' => 'Disponibilité (pied de page)', 'name' => 'disponibilite', 'type' => 'text', 'default_value' => 'Disponible partout en France !', 'wrapper' => array( 'width' => 50 ) ),
		),
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'maxou-coordonnees' ) ) ),
	) );
} );

/* Liste des formulaires WPForms dans le sélecteur de la section « Formulaire ». */
add_filter( 'acf/load_field/key=field_mx_form_id', function ( $field ) {
	$field['choices'] = array();
	$forms            = get_posts( array( 'post_type' => 'wpforms', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );
	foreach ( $forms as $form ) {
		$field['choices'][ $form->ID ] = $form->post_title . ' (#' . $form->ID . ')';
	}
	return $field;
} );
