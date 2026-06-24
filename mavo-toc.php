<?php
/**
 * Plugin Name: Mavo TOC
 * Description: Adds a [mavo_toc] shortcode that builds a customizable table of contents from the headings in a post.
 * Version: 1.3.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mavo-toc
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAVO_TOC_VERSION', '1.3.1' );
define( 'MAVO_TOC_FILE', __FILE__ );
define( 'MAVO_TOC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAVO_TOC_URL', plugin_dir_url( __FILE__ ) );

require_once MAVO_TOC_PATH . 'includes/class-mavo-toc.php';
require_once MAVO_TOC_PATH . 'includes/class-mavo-toc-settings.php';

/**
 * @return Mavo_TOC
 */
function mavo_toc() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Mavo_TOC();
	}

	return $instance;
}

add_action(
	'plugins_loaded',
	function () {
		mavo_toc();

		if ( is_admin() ) {
			new Mavo_TOC_Settings();
		}
	}
);
