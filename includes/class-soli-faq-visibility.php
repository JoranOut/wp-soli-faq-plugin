<?php

namespace Soli\Faq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides members-only FAQs from not-logged-in visitors on every surface.
 * FAQs marked public (Post_Type::PUBLIC_META) stay visible on the single page,
 * in Query Loops and in the search block:
 *
 * - Query Loop blocks and other secondary WP_Query instances return only
 *   public FAQs; queries that mix post types drop FAQs altogether
 * - A members-only single page responds with HTTP 403; the archive is
 *   disabled (has_archive => false), and a ?post_type= query gets the same 403
 * - The REST endpoints (collection and single, plus the topic terms)
 *   respond with HTTP 403, public or not
 * - The search block lists public FAQs only, or a login prompt when there
 *   are none
 *
 * Site search is handled in Post_Type via a per-request exclude_from_search.
 * Logged-in users (any role) see everything; draft/private post statuses keep
 * their normal WordPress rules.
 */
class Visibility {

	public function init() {
		add_action( 'pre_get_posts', array( $this, 'hide_from_public_queries' ) );
		add_action( 'template_redirect', array( $this, 'guard_front_end' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'guard_rest' ), 10, 3 );
	}

	/**
	 * Limit every logged-out query that targets only FAQs to public ones, or
	 * strip the post type when the query mixes types (e.g. REST search):
	 * the public flag is post meta, and a meta query would filter the other
	 * types too.
	 *
	 * The main query is left alone entirely: single and archive get a 403
	 * from guard_front_end() (emptying them here would turn that into a
	 * silent 404), and site search is covered by the per-request
	 * exclude_from_search flag in Post_Type.
	 */
	public function hide_from_public_queries( $query ) {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		if ( $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) ) {
			return;
		}

		if ( is_array( $post_type ) ) {
			if ( ! in_array( Post_Type::POST_TYPE, $post_type, true ) ) {
				return;
			}
			$remaining = array_values( array_diff( $post_type, array( Post_Type::POST_TYPE ) ) );
			if ( empty( $remaining ) ) {
				self::only_public( $query );
			} else {
				$query->set( 'post_type', $remaining );
			}
		} elseif ( Post_Type::POST_TYPE === $post_type ) {
			self::only_public( $query );
		}
	}

	private static function only_public( $query ) {
		$clause = array(
			'key'   => Post_Type::PUBLIC_META,
			'value' => '1',
		);
		$existing = $query->get( 'meta_query' );
		$query->set(
			'meta_query',
			$existing ? array( 'relation' => 'AND', $existing, $clause ) : array( $clause )
		);
	}

	/**
	 * 403 for logged-out visits to a members-only single faq or any other
	 * main query that targets the post type (e.g. ?post_type=soli_faq),
	 * following the guard pattern in wp-soli-event-plugin.
	 *
	 * With has_archive => false, WordPress does not flag a ?post_type=
	 * request as a post type archive, so the query var is checked
	 * directly — otherwise that URL would render as a 200 listing.
	 */
	public function guard_front_end() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		if ( is_singular( Post_Type::POST_TYPE ) ) {
			if ( Post_Type::is_public( get_queried_object() ) ) {
				return;
			}
		} elseif ( ! in_array( Post_Type::POST_TYPE, (array) get_query_var( 'post_type' ), true ) ) {
			return;
		}

		wp_die(
			esc_html__( 'FAQs are only visible to members. Please log in to view them.', 'soli-faq' ),
			esc_html__( 'Forbidden', 'soli-faq' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * 403 for logged-out REST requests to the FAQ routes. Logged-in
	 * requests (cookie or application password) pass through untouched so the
	 * editor and the Query Loop preview keep working.
	 *
	 * @param mixed            $response Current response, null when nothing intervened yet.
	 * @param array|callable   $handler  Route handler (unused).
	 * @param \WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public function guard_rest( $response, $handler, $request ) {
		if ( null !== $response || is_user_logged_in() ) {
			return $response;
		}

		$route = $request->get_route();
		if ( 0 !== strpos( $route, '/wp/v2/' . Post_Type::REST_BASE )
			&& 0 !== strpos( $route, '/wp/v2/' . Post_Type::TAXONOMY_REST_BASE ) ) {
			return $response;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'FAQs are only visible to members.', 'soli-faq' ),
			array( 'status' => 403 )
		);
	}
}
