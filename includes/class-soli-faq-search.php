<?php

namespace Soli\Faq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the FAQ search block and supplies its data. Matching mirrors
 * src/search/view.js, so a search without JavaScript returns the same list.
 */
class Search {

	const QUERY_VAR = 'faq_search';
	const TOPIC_VAR = 'faq_topic';

	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		$block = register_block_type( SOLI_FAQ__PLUGIN_DIR_PATH . 'build/search' );

		// block.json registration only looks in WP_LANG_DIR for the editor
		// strings; this plugin ships its JSON translations in /languages.
		foreach ( $block ? $block->editor_script_handles : array() as $handle ) {
			wp_set_script_translations( $handle, 'soli-faq', SOLI_FAQ__PLUGIN_DIR_PATH . 'languages' );
		}
	}

	/**
	 * Published FAQs the current visitor may read, most recently updated first.
	 * Logged-out visitors get the public ones only.
	 *
	 * @return array[]
	 */
	public static function items() {
		$args = array(
			'post_type'      => Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( ! is_user_logged_in() ) {
			$args['meta_query'] = array( self::public_clause() );
		}
		$posts = get_posts( $args );

		$items = array();
		foreach ( $posts as $post ) {
			$text   = self::plain_text( $post->post_content );
			$topics = get_the_terms( $post, Post_Type::TAXONOMY );

			$items[] = array(
				'id'      => $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'text'    => $text,
				'excerpt' => has_excerpt( $post )
					? wp_strip_all_tags( get_the_excerpt( $post ) )
					: wp_trim_words( $text, 36, '…' ),
				'topics'  => is_array( $topics ) ? wp_list_pluck( $topics, 'slug' ) : array(),
				'updated' => get_the_modified_date( '', $post ),
			);
		}

		return $items;
	}

	/**
	 * Whether any published FAQ is members-only. Queried directly: a WP_Query
	 * from a logged-out request would pass through Visibility and only ever
	 * see public FAQs.
	 */
	public static function has_members_only() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = 'publish'
				AND ( m.meta_value IS NULL OR m.meta_value <> '1' )
				LIMIT 1",
				Post_Type::PUBLIC_META,
				Post_Type::POST_TYPE
			)
		);
	}

	private static function public_clause() {
		return array(
			'key'   => Post_Type::PUBLIC_META,
			'value' => '1',
		);
	}

	/**
	 * Topics of the given items, in term order. Counting from the items keeps
	 * a topic with only members-only FAQs out of a logged-out visitor's filter.
	 *
	 * @param array[] $items As returned by items().
	 * @return array[] Each with slug and name.
	 */
	public static function topics( array $items ) {
		$used = array_unique( array_merge( array(), ...array_column( $items, 'topics' ) ) );
		if ( ! $used ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Post_Type::TAXONOMY,
				'slug'       => $used,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			static fn( $term ) => array(
				'slug' => $term->slug,
				// Term names are stored HTML-encoded ("&amp;"); output escapes again.
				'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			),
			$terms
		);
	}

	/**
	 * Items that contain every search term, best match first. A title hit
	 * outweighs any number of body hits. An empty query keeps the input order.
	 *
	 * @param array[] $items As returned by items().
	 * @param string  $query Raw search input.
	 * @return array[]
	 */
	public static function filter( array $items, string $query ) {
		$terms = self::terms( $query );
		if ( ! $terms ) {
			return $items;
		}

		$scored = array();
		foreach ( $items as $index => $item ) {
			$title = self::normalize( $item['title'] );
			$body  = self::normalize( $item['text'] );
			$score = 0;

			foreach ( $terms as $term ) {
				$in_title = str_contains( $title, $term );
				$in_body  = substr_count( $body, $term );
				if ( ! $in_title && ! $in_body ) {
					continue 2;
				}
				$score += ( $in_title ? 10 : 0 ) + min( $in_body, 5 );
			}

			$scored[] = array( $score, $index, $item );
		}

		usort( $scored, static fn( $a, $b ) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1] );

		return array_column( $scored, 2 );
	}

	/**
	 * Search terms of two characters or more, lowercased and without accents.
	 *
	 * @return string[]
	 */
	public static function terms( string $query ) {
		$parts = preg_split( '/\s+/u', self::normalize( $query ), -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_filter( $parts, static fn( $t ) => mb_strlen( $t ) > 1 ) );
	}

	public static function normalize( string $text ) {
		return mb_strtolower( remove_accents( $text ) );
	}

	/**
	 * Block comments and markup removed. Content is not run through the_content:
	 * an FAQ that contains this block would render itself.
	 */
	private static function plain_text( string $content ) {
		$text = wp_strip_all_tags( strip_shortcodes( $content ) );
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	}
}
