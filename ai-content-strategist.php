<?php
/**
 * Plugin Name: AI Content Strategist
 * Plugin URI: https://github.com/your-vendor/ai-content-strategist
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
 * Load Composer autoloader if it exists.
 *
 * The Jetpack Autoloader is preferred if available, as it handles
 * version conflicts between plugins using the same packages.
 */
$autoloader_file = AI_CONTENT_STRATEGIST_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $autoloader_file ) ) {
	require_once $autoloader_file;
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

// Initialize the plugin after WordPress is fully loaded.
add_action( 'plugins_loaded', 'ai_content_strategist_init' );
