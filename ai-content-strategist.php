<?php
/**
 * Plugin Name: AI Content Strategist
 * Plugin URI: https://github.com/annacmc/ai-content-strategist
 * Description: Exposes Jetpack Stats data and content audit capabilities via the WordPress Abilities API for AI assistants through MCP.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Anna McPhee
 * Author URI: https://anna.kiwi
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-strategist
 * Domain Path: /languages
 *
 * @package AI_Content_Strategist
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AI_CONTENT_STRATEGIST_VERSION', '1.0.0' );
define( 'AI_CONTENT_STRATEGIST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CONTENT_STRATEGIST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_CONTENT_STRATEGIST_PLUGIN_FILE', __FILE__ );

/**
 * Load autoloader.
 *
 * The Jetpack Autoloader handles version conflicts between plugins using
 * the same packages by always loading the latest version available.
 */
$jetpack_autoloader = AI_CONTENT_STRATEGIST_PLUGIN_DIR . 'vendor/autoload_packages.php';
$composer_autoloader = AI_CONTENT_STRATEGIST_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $jetpack_autoloader ) ) {
	require_once $jetpack_autoloader;
} elseif ( file_exists( $composer_autoloader ) ) {
	require_once $composer_autoloader;
}

// Load plugin class files.
require_once AI_CONTENT_STRATEGIST_PLUGIN_DIR . 'includes/class-plugin.php';
require_once AI_CONTENT_STRATEGIST_PLUGIN_DIR . 'includes/class-stats-abilities.php';
require_once AI_CONTENT_STRATEGIST_PLUGIN_DIR . 'includes/class-content-abilities.php';

/**
 * Initialize the plugin.
 *
 * We use a function to initialize to avoid polluting the global namespace
 * and to ensure WordPress is fully loaded.
 *
 * @return AI_Content_Strategist\Plugin The plugin instance.
 */
function ai_content_strategist_init() {
	return AI_Content_Strategist\Plugin::get_instance();
}

/**
 * Load plugin text domain for translations.
 */
function ai_content_strategist_load_textdomain() {
	load_plugin_textdomain(
		'ai-content-strategist',
		false,
		dirname( plugin_basename( AI_CONTENT_STRATEGIST_PLUGIN_FILE ) ) . '/languages'
	);
}

// Initialize the plugin after WordPress is fully loaded.
add_action( 'plugins_loaded', 'ai_content_strategist_init' );

// Load translations.
add_action( 'init', 'ai_content_strategist_load_textdomain' );

/**
 * Clean up plugin transients on deactivation.
 */
function ai_content_strategist_deactivate() {
	global $wpdb;

	// Delete all plugin transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_ai_cs_%',
			'_transient_timeout_ai_cs_%'
		)
	);
}

register_deactivation_hook( AI_CONTENT_STRATEGIST_PLUGIN_FILE, 'ai_content_strategist_deactivate' );
