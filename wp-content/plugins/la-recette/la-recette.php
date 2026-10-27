<?php
/**
 * Plugin Name:       La Recette
 * Plugin URI:        https://www.linkedin.com/in/maxandre-dufour/
 * Description:       Outil de recettage : posez des pins commentés sur les pages du site, assignez-les et suivez-les. Tout reste sur ce site.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Maxandre Dufour
 * Author URI:        https://www.linkedin.com/in/maxandre-dufour/
 * Text Domain:       la-recette
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LR_VERSION', '1.0.0' );
define( 'LR_FILE', __FILE__ );
define( 'LR_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-lr-plugin.php';
require_once __DIR__ . '/includes/class-lr-ajax.php';
require_once __DIR__ . '/includes/class-lr-media.php';
require_once __DIR__ . '/includes/class-lr-admin.php';

register_activation_hook( __FILE__, array( 'LR_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'LR_Plugin', 'init' ) );
