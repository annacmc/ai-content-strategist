<?php
/**
 * Stats abilities for AI Content Strategist.
 *
 * Handles registration and execution of Jetpack Stats-related abilities:
 * - get-top-posts: Returns top performing posts by views
 * - get-search-terms: Returns what people are searching for
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
 * Stats Abilities class.
 *
 * Registers and handles abilities that require Jetpack Stats data
 * from WordPress.com.
 */
class Stats_Abilities {

	/**
	 * Cache expiration time in seconds (15 minutes).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 900;

	/**
	 * Register stats-related abilities with the Abilities API.
	 */
	public function register(): void {
		$this->register_get_top_posts();
		$this->register_get_search_terms();
	}

	/**
	 * Register the get-top-posts ability.
	 *
	 * Returns the site's top performing posts by views over a specified period.
	 */
	private function register_get_top_posts(): void {
		wp_register_ability(
			'content-strategist/get-top-posts',
			array(
				'label'       => __( 'Get Top Posts', 'ai-content-strategist' ),
				'description' => __( 'Returns the site\'s top performing posts by views. Useful for understanding what content resonates with your audience.', 'ai-content-strategist' ),
				'category'    => 'content',
				'input_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'days'  => array(
							'type'        => 'integer',
							'description' => __( 'Number of days to analyze (7, 30, or 90)', 'ai-content-strategist' ),
							'default'     => 30,
							'enum'        => array( 7, 30, 90 ),
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of posts to return (1-50)', 'ai-content-strategist' ),
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
				),
				'output_schema' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'        => array(
								'type'        => 'integer',
								'description' => __( 'The WordPress post ID', 'ai-content-strategist' ),
							),
							'title'          => array(
								'type'        => 'string',
								'description' => __( 'The post title', 'ai-content-strategist' ),
							),
							'url'            => array(
								'type'        => 'string',
								'description' => __( 'The post permalink', 'ai-content-strategist' ),
							),
							'views'          => array(
								'type'        => 'integer',
								'description' => __( 'Total views in the period', 'ai-content-strategist' ),
							),
							'date_published' => array(
								'type'        => 'string',
								'description' => __( 'Publication date in ISO 8601 format', 'ai-content-strategist' ),
							),
							'categories'     => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'List of category names', 'ai-content-strategist' ),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_top_posts' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta' => array(
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the get-search-terms ability.
	 *
	 * Returns what people are searching for on or to find the site.
	 */
	private function register_get_search_terms(): void {
		wp_register_ability(
			'content-strategist/get-search-terms',
			array(
				'label'       => __( 'Get Search Terms', 'ai-content-strategist' ),
				'description' => __( 'Returns search terms people used to find your site. Useful for identifying content opportunities and SEO gaps.', 'ai-content-strategist' ),
				'category'    => 'content',
				'input_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'days'  => array(
							'type'        => 'integer',
							'description' => __( 'Number of days to analyze', 'ai-content-strategist' ),
							'default'     => 30,
							'minimum'     => 1,
							'maximum'     => 365,
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of terms to return (1-100)', 'ai-content-strategist' ),
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
				),
				'output_schema' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'term'  => array(
								'type'        => 'string',
								'description' => __( 'The search term', 'ai-content-strategist' ),
							),
							'count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of times this term was searched', 'ai-content-strategist' ),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_search_terms' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta' => array(
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Execute the get-top-posts ability.
	 *
	 * Fetches top posts from Jetpack Stats and enriches them with
	 * WordPress post data (categories, publication date).
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error Array of top posts or error.
	 */
	public function execute_get_top_posts( array $input ): array|\WP_Error {
		// Verify Jetpack is connected.
		if ( ! Plugin::is_jetpack_connected() || ! Plugin::is_stats_available() ) {
			return Plugin::get_jetpack_not_connected_error();
		}

		$days  = $input['days'] ?? 30;
		$limit = $input['limit'] ?? 10;

		// Try to get cached data first.
		$cache_key = 'ai_cs_top_posts_' . $days . '_' . $limit;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch stats from Jetpack.
		$stats = new \Automattic\Jetpack\Stats\WPCOM_Stats();

		$args = array(
			'num'     => $limit,
			'period'  => 'day',
			'date'    => gmdate( 'Y-m-d' ),
			'max'     => $limit,
			'summarize' => true,
		);

		// Get top posts for the period.
		$top_posts_data = $stats->get_top_posts( $args );

		if ( is_wp_error( $top_posts_data ) ) {
			return $top_posts_data;
		}

		// Process and enrich the data.
		$result = $this->process_top_posts( $top_posts_data, $limit );

		// Cache the result.
		set_transient( $cache_key, $result, self::CACHE_EXPIRATION );

		return $result;
	}

	/**
	 * Process top posts data from Jetpack Stats.
	 *
	 * Enriches the raw stats data with WordPress post information
	 * like categories and formatted dates.
	 *
	 * @param array $top_posts_data Raw data from Jetpack Stats.
	 * @param int   $limit          Maximum posts to return.
	 * @return array Processed posts array.
	 */
	private function process_top_posts( array $top_posts_data, int $limit ): array {
		$result = array();

		// The data structure varies based on the Jetpack Stats response.
		// We need to extract the posts array from the response.
		$posts = $top_posts_data['summary']['postviews'] ?? $top_posts_data['posts'] ?? array();

		$count = 0;
		foreach ( $posts as $post_stat ) {
			if ( $count >= $limit ) {
				break;
			}

			$post_id = $post_stat['id'] ?? 0;

			// Skip if not a valid post.
			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );

			// Skip if post doesn't exist or isn't published.
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			// Get categories.
			$categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );

			$result[] = array(
				'post_id'        => $post_id,
				'title'          => $post->post_title,
				'url'            => get_permalink( $post_id ),
				'views'          => (int) ( $post_stat['views'] ?? 0 ),
				'date_published' => gmdate( 'c', strtotime( $post->post_date_gmt ) ),
				'categories'     => is_array( $categories ) ? $categories : array(),
			);

			++$count;
		}

		return $result;
	}

	/**
	 * Execute the get-search-terms ability.
	 *
	 * Fetches search terms from Jetpack Stats that people used
	 * to find the site.
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error Array of search terms or error.
	 */
	public function execute_get_search_terms( array $input ): array|\WP_Error {
		// Verify Jetpack is connected.
		if ( ! Plugin::is_jetpack_connected() || ! Plugin::is_stats_available() ) {
			return Plugin::get_jetpack_not_connected_error();
		}

		$days  = $input['days'] ?? 30;
		$limit = $input['limit'] ?? 20;

		// Try to get cached data first.
		$cache_key = 'ai_cs_search_terms_' . $days . '_' . $limit;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch stats from Jetpack.
		$stats = new \Automattic\Jetpack\Stats\WPCOM_Stats();

		$args = array(
			'num'  => $days,
			'max'  => $limit,
			'summarize' => true,
		);

		// Get search terms.
		$search_data = $stats->get_search_terms( $args );

		if ( is_wp_error( $search_data ) ) {
			return $search_data;
		}

		// Process the search terms.
		$result = $this->process_search_terms( $search_data, $limit );

		// Cache the result.
		set_transient( $cache_key, $result, self::CACHE_EXPIRATION );

		return $result;
	}

	/**
	 * Process search terms data from Jetpack Stats.
	 *
	 * Formats the raw search terms data into a consistent structure.
	 *
	 * @param array $search_data Raw data from Jetpack Stats.
	 * @param int   $limit       Maximum terms to return.
	 * @return array Processed search terms array.
	 */
	private function process_search_terms( array $search_data, int $limit ): array {
		$result = array();

		// Extract search terms from the response.
		$terms = $search_data['summary']['search_terms'] ?? $search_data['search_terms'] ?? array();

		$count = 0;
		foreach ( $terms as $term_data ) {
			if ( $count >= $limit ) {
				break;
			}

			// Skip encrypted/hidden search terms.
			$term = $term_data['term'] ?? $term_data[0] ?? '';
			if ( empty( $term ) || 'Unknown Search Terms' === $term ) {
				continue;
			}

			$result[] = array(
				'term'  => $term,
				'count' => (int) ( $term_data['views'] ?? $term_data[1] ?? 0 ),
			);

			++$count;
		}

		return $result;
	}

	/**
	 * Get post views for a specific post from Jetpack Stats.
	 *
	 * Helper method used by other abilities to get view counts.
	 *
	 * @param int $post_id The post ID.
	 * @param int $days    Number of days to analyze.
	 * @return int The view count, or 0 if unavailable.
	 */
	public static function get_post_views( int $post_id, int $days = 90 ): int {
		if ( ! Plugin::is_jetpack_connected() || ! Plugin::is_stats_available() ) {
			return 0;
		}

		$cache_key = 'ai_cs_post_views_' . $post_id . '_' . $days;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$stats = new \Automattic\Jetpack\Stats\WPCOM_Stats();

		$args = array(
			'num'       => $days,
			'post_id'   => $post_id,
			'summarize' => true,
		);

		$post_stats = $stats->get_post_views( $post_id, $args );

		if ( is_wp_error( $post_stats ) ) {
			return 0;
		}

		$views = $post_stats['views'] ?? 0;

		// Cache for 15 minutes.
		set_transient( $cache_key, $views, self::CACHE_EXPIRATION );

		return (int) $views;
	}
}
