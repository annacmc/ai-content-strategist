<?php
/**
 * Content abilities for AI Content Strategist.
 *
 * Handles registration and execution of content audit abilities:
 * - get-stale-drafts: Finds draft posts that have been sitting unfinished
 * - get-underperforming-posts: Finds published posts with low traffic
 *
 * @package AI_Content_Strategist
 */

namespace AI_Content_Strategist;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Abilities class.
 *
 * Registers and handles abilities for content auditing using
 * WordPress native queries combined with Jetpack Stats data.
 */
class Content_Abilities {

	/**
	 * Register content-related abilities with the Abilities API.
	 */
	public function register(): void {
		$this->register_get_stale_drafts();
		$this->register_get_underperforming_posts();
	}

	/**
	 * Register the get-stale-drafts ability.
	 *
	 * Finds draft posts that have been sitting unfinished for a specified period.
	 */
	private function register_get_stale_drafts(): void {
		wp_register_ability(
			'content-strategist/get-stale-drafts',
			array(
				'label'       => __( 'Get Stale Drafts', 'ai-content-strategist' ),
				'description' => __( 'Finds draft posts that have been sitting unfinished for a specified period. Useful for identifying content to complete or delete.', 'ai-content-strategist' ),
				'category'    => 'content',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'days_old' => array(
							'type'        => 'integer',
							'description' => __( 'Find drafts not modified in this many days', 'ai-content-strategist' ),
							'default'     => 180,
							'minimum'     => 7,
							'maximum'     => 730,
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of drafts to return (1-50)', 'ai-content-strategist' ),
							'default'     => 20,
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
							'post_id'             => array(
								'type'        => 'integer',
								'description' => __( 'The WordPress post ID', 'ai-content-strategist' ),
							),
							'title'               => array(
								'type'        => 'string',
								'description' => __( 'The draft title (or "Untitled" if empty)', 'ai-content-strategist' ),
							),
							'excerpt'             => array(
								'type'        => 'string',
								'description' => __( 'First 150 characters of content', 'ai-content-strategist' ),
							),
							'date_created'        => array(
								'type'        => 'string',
								'description' => __( 'Creation date in ISO 8601 format', 'ai-content-strategist' ),
							),
							'date_modified'       => array(
								'type'        => 'string',
								'description' => __( 'Last modified date in ISO 8601 format', 'ai-content-strategist' ),
							),
							'days_since_modified' => array(
								'type'        => 'integer',
								'description' => __( 'Number of days since last modification', 'ai-content-strategist' ),
							),
							'categories'          => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'List of category names', 'ai-content-strategist' ),
							),
							'word_count'          => array(
								'type'        => 'integer',
								'description' => __( 'Approximate word count of the content', 'ai-content-strategist' ),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_stale_drafts' ),
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
	 * Register the get-underperforming-posts ability.
	 *
	 * Finds published posts with low or no traffic that might need
	 * attention (refresh, promotion, or removal).
	 */
	private function register_get_underperforming_posts(): void {
		wp_register_ability(
			'content-strategist/get-underperforming-posts',
			array(
				'label'       => __( 'Get Underperforming Posts', 'ai-content-strategist' ),
				'description' => __( 'Finds published posts with low traffic. Useful for identifying content to refresh, promote, or remove. Requires Jetpack for view data.', 'ai-content-strategist' ),
				'category'    => 'content',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'days_published' => array(
							'type'        => 'integer',
							'description' => __( 'Only include posts published at least this many days ago (to give them time to get traffic)', 'ai-content-strategist' ),
							'default'     => 90,
							'minimum'     => 30,
							'maximum'     => 730,
						),
						'max_views'      => array(
							'type'        => 'integer',
							'description' => __( 'Consider "underperforming" if fewer than this many views', 'ai-content-strategist' ),
							'default'     => 100,
							'minimum'     => 0,
							'maximum'     => 10000,
						),
						'limit'          => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of posts to return (1-50)', 'ai-content-strategist' ),
							'default'     => 20,
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
							'date_published' => array(
								'type'        => 'string',
								'description' => __( 'Publication date in ISO 8601 format', 'ai-content-strategist' ),
							),
							'views'          => array(
								'type'        => 'integer',
								'description' => __( 'Total views (requires Jetpack, 0 if unavailable)', 'ai-content-strategist' ),
							),
							'categories'     => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'List of category names', 'ai-content-strategist' ),
							),
							'word_count'     => array(
								'type'        => 'integer',
								'description' => __( 'Approximate word count of the content', 'ai-content-strategist' ),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_underperforming_posts' ),
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
	 * Execute the get-stale-drafts ability.
	 *
	 * Queries for draft posts that haven't been modified in the specified
	 * number of days and enriches them with useful metadata.
	 *
	 * @param array $input The input parameters.
	 * @return array Array of stale draft posts.
	 */
	public function execute_get_stale_drafts( array $input ): array {
		$days_old = $input['days_old'] ?? 180;
		$limit    = $input['limit'] ?? 20;

		// Calculate the cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// Query for stale drafts.
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'draft',
				'posts_per_page' => $limit,
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => $cutoff_date,
					),
				),
				'orderby'        => 'modified',
				'order'          => 'ASC', // Oldest first.
			)
		);

		$result = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				// Get categories.
				$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );

				// Get timestamps, falling back to local time if GMT is zeroed (common for drafts).
				$created_timestamp  = $this->get_post_timestamp( $post, 'date' );
				$modified_timestamp = $this->get_post_timestamp( $post, 'modified' );

				// Calculate days since modified.
				$days_since = (int) floor( ( time() - $modified_timestamp ) / DAY_IN_SECONDS );

				// Get word count.
				$word_count = $this->get_word_count( $post->post_content );

				// Generate excerpt from content.
				$excerpt = $this->generate_excerpt( $post->post_content );

				// Use title or placeholder.
				$title = ! empty( $post->post_title ) ? $post->post_title : __( 'Untitled', 'ai-content-strategist' );

				$result[] = array(
					'post_id'             => $post->ID,
					'title'               => $title,
					'excerpt'             => $excerpt,
					'date_created'        => gmdate( 'c', $created_timestamp ),
					'date_modified'       => gmdate( 'c', $modified_timestamp ),
					'days_since_modified' => $days_since,
					'categories'          => is_array( $categories ) ? $categories : array(),
					'word_count'          => $word_count,
				);
			}
			wp_reset_postdata();
		}

		return $result;
	}

	/**
	 * Execute the get-underperforming-posts ability.
	 *
	 * Finds published posts older than the specified period that have
	 * fewer views than the threshold. Combines WordPress queries with
	 * Jetpack Stats view data.
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error Array of underperforming posts or error.
	 */
	public function execute_get_underperforming_posts( array $input ): array|\WP_Error {
		$days_published = $input['days_published'] ?? 90;
		$max_views      = $input['max_views'] ?? 100;
		$limit          = $input['limit'] ?? 20;

		// Check if Jetpack is available for view data.
		$has_jetpack = Plugin::is_jetpack_connected() && Plugin::is_stats_available();

		if ( ! $has_jetpack ) {
			return new \WP_Error(
				'jetpack_required',
				__( 'Jetpack Stats is required to identify underperforming posts. Please connect Jetpack to WordPress.com.', 'ai-content-strategist' ),
				array( 'status' => 503 )
			);
		}

		// Calculate the cutoff date for publication.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_published} days" ) );

		// Query for old published posts.
		// We fetch more than needed because we'll filter by views.
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit * 3, // Fetch extra for filtering.
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => $cutoff_date,
					),
				),
				'orderby'        => 'date',
				'order'          => 'ASC', // Oldest first.
			)
		);

		$result = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() && count( $result ) < $limit ) {
				$query->the_post();
				$post = get_post();

				// Get view count from Jetpack Stats.
				$views = Stats_Abilities::get_post_views( $post->ID, $days_published );

				// Skip if views exceed threshold.
				if ( $views > $max_views ) {
					continue;
				}

				// Get categories.
				$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );

				// Get word count.
				$word_count = $this->get_word_count( $post->post_content );

				$result[] = array(
					'post_id'        => $post->ID,
					'title'          => $post->post_title,
					'url'            => get_permalink( $post->ID ),
					'date_published' => gmdate( 'c', strtotime( $post->post_date_gmt ) ),
					'views'          => $views,
					'categories'     => is_array( $categories ) ? $categories : array(),
					'word_count'     => $word_count,
				);
			}
			wp_reset_postdata();
		}

		// Sort by views ascending (lowest views first).
		usort(
			$result,
			function ( $a, $b ) {
				return $a['views'] - $b['views'];
			}
		);

		return $result;
	}

	/**
	 * Get a post timestamp, falling back to local time if GMT is zeroed.
	 *
	 * Draft posts often have zeroed GMT dates (0000-00-00 00:00:00) because
	 * they haven't been published. This method falls back to the local
	 * timezone date in that case.
	 *
	 * @param \WP_Post $post The post object.
	 * @param string   $type Either 'date' for creation or 'modified' for last modified.
	 * @return int Unix timestamp.
	 */
	private function get_post_timestamp( \WP_Post $post, string $type = 'date' ): int {
		$gmt_field   = 'modified' === $type ? 'post_modified_gmt' : 'post_date_gmt';
		$local_field = 'modified' === $type ? 'post_modified' : 'post_date';

		// Check if GMT date is valid (not zeroed).
		$gmt_date = $post->$gmt_field;
		if ( ! empty( $gmt_date ) && '0000-00-00 00:00:00' !== $gmt_date ) {
			return strtotime( $gmt_date );
		}

		// Fall back to local date and convert to UTC timestamp.
		$local_date = $post->$local_field;
		if ( ! empty( $local_date ) && '0000-00-00 00:00:00' !== $local_date ) {
			// Use WordPress function to convert local time to UTC timestamp.
			return get_gmt_from_date( $local_date, 'U' );
		}

		// Last resort: return current time.
		return time();
	}

	/**
	 * Get the word count of content.
	 *
	 * Strips HTML and shortcodes before counting words.
	 *
	 * @param string $content The post content.
	 * @return int The word count.
	 */
	private function get_word_count( string $content ): int {
		// Strip shortcodes and HTML.
		$content = wp_strip_all_tags( strip_shortcodes( $content ) );

		// Count words.
		$word_count = str_word_count( $content );

		return (int) $word_count;
	}

	/**
	 * Generate an excerpt from content.
	 *
	 * Creates a clean excerpt by stripping HTML and shortcodes,
	 * then truncating to a specified length.
	 *
	 * @param string $content The post content.
	 * @param int    $length  Maximum characters (default 150).
	 * @return string The generated excerpt.
	 */
	private function generate_excerpt( string $content, int $length = 150 ): string {
		// Strip shortcodes and HTML.
		$content = wp_strip_all_tags( strip_shortcodes( $content ) );

		// Trim whitespace.
		$content = trim( $content );

		// Return empty string if no content.
		if ( empty( $content ) ) {
			return '';
		}

		// Truncate if longer than length.
		if ( strlen( $content ) > $length ) {
			$content = substr( $content, 0, $length );

			// Try to break at a word boundary.
			$last_space = strrpos( $content, ' ' );
			if ( false !== $last_space && $last_space > $length * 0.8 ) {
				$content = substr( $content, 0, $last_space );
			}

			$content .= '...';
		}

		return $content;
	}
}
