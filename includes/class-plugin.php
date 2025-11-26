<?php
/**
 * Main plugin class for AI Content Strategist.
 *
 * Handles plugin initialization and coordinates the registration
 * of all abilities via the WordPress Abilities API.
 *
 * @package AI_Content_Strategist
 */

declare( strict_types = 1 );

namespace AI_Content_Strategist;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * This class follows the singleton pattern to ensure only one instance
 * exists throughout the WordPress request lifecycle.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Stats abilities handler instance.
	 *
	 * @var Stats_Abilities|null
	 */
	private $stats_abilities = null;

	/**
	 * Content abilities handler instance.
	 *
	 * @var Content_Abilities|null
	 */
	private $content_abilities = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin The plugin instance.
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Private to enforce singleton pattern.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * Sets up the hooks needed for the plugin to function.
	 */
	private function init_hooks(): void {
		// Register ability category on the categories init hook.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );

		// Register abilities on the Abilities API init hook.
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// Add admin notice if Jetpack is not connected.
		add_action( 'admin_notices', array( $this, 'maybe_show_jetpack_notice' ) );
	}

	/**
	 * Register all plugin abilities.
	 *
	 * This is the main entry point for registering abilities with the
	 * WordPress Abilities API. Called on the 'wp_abilities_api_init' hook.
	 */
	public function register_abilities(): void {
		// Initialize ability handlers.
		$this->stats_abilities   = new Stats_Abilities();
		$this->content_abilities = new Content_Abilities();

		// Register stats-related abilities (requires Jetpack).
		$this->stats_abilities->register();

		// Register content audit abilities (WordPress-native).
		$this->content_abilities->register();
	}

	/**
	 * Register the content ability category.
	 *
	 * The WordPress Abilities API only provides 'site' and 'user' categories
	 * by default. This plugin needs a 'content' category for its abilities.
	 * Called on the 'wp_abilities_api_categories_init' hook.
	 */
	public function register_ability_category(): void {
		wp_register_ability_category(
			'content',
			array(
				'label'       => __( 'Content', 'ai-content-strategist' ),
				'description' => __( 'Abilities for content analysis, auditing, and strategy.', 'ai-content-strategist' ),
			)
		);
	}

	/**
	 * Check if Jetpack is active and connected.
	 *
	 * Verifies that the Jetpack plugin is installed, active, and has
	 * an active connection to WordPress.com (required for Stats API).
	 *
	 * @return bool True if Jetpack is connected, false otherwise.
	 */
	public static function is_jetpack_connected(): bool {
		// Check if Jetpack class exists.
		if ( ! class_exists( 'Automattic\Jetpack\Connection\Manager' ) ) {
			return false;
		}

		// Check connection status.
		$connection_manager = new \Automattic\Jetpack\Connection\Manager();
		return $connection_manager->is_connected();
	}

	/**
	 * Check if the WPCOM_Stats class is available.
	 *
	 * The Stats class is provided by Jetpack and is required for
	 * fetching analytics data from WordPress.com.
	 *
	 * @return bool True if the Stats class exists, false otherwise.
	 */
	public static function is_stats_available(): bool {
		return class_exists( 'Automattic\Jetpack\Stats\WPCOM_Stats' );
	}

	/**
	 * Display admin notice if Jetpack is not properly configured.
	 *
	 * Shows a warning to administrators if Jetpack is missing or
	 * not connected, as this will prevent stats abilities from working.
	 */
	public function maybe_show_jetpack_notice(): void {
		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show on plugin pages or dashboard.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'dashboard' ), true ) ) {
			return;
		}

		if ( ! self::is_jetpack_connected() ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'AI Content Strategist:', 'ai-content-strategist' ); ?></strong>
					<?php
					esc_html_e(
						'Jetpack is not connected. Stats-related abilities (top posts, search terms, underperforming posts) will not be available until Jetpack is connected to WordPress.com.',
						'ai-content-strategist'
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get a WP_Error for when Jetpack is not connected.
	 *
	 * Provides a consistent error response when stats abilities are
	 * called but Jetpack is not available.
	 *
	 * @return \WP_Error Error object with helpful message.
	 */
	public static function get_jetpack_not_connected_error(): \WP_Error {
		return new \WP_Error(
			'jetpack_not_connected',
			__( 'Jetpack is not connected to WordPress.com. Please install and connect Jetpack to use stats-related abilities.', 'ai-content-strategist' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Prevent cloning of the singleton.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @throws \Exception Always throws to prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
