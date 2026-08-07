<?php

namespace Soli\Faq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the soli_faq post type and the editor script that adds
 * the "FAQs" Query Loop variation.
 */
class Post_Type {

	const POST_TYPE = 'soli_faq';
	const REST_BASE = 'faqs';

	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'               => __( 'FAQs', 'soli-faq' ),
			'singular_name'      => __( 'FAQ', 'soli-faq' ),
			'add_new'            => __( 'Add FAQ', 'soli-faq' ),
			'add_new_item'       => __( 'Add New FAQ', 'soli-faq' ),
			'view_item'          => __( 'View FAQ', 'soli-faq' ),
			'edit_item'          => __( 'Edit FAQ', 'soli-faq' ),
			'insert_into_item'   => __( 'Insert into FAQ', 'soli-faq' ),
			'search_items'       => __( 'Search FAQs', 'soli-faq' ),
			'not_found'          => __( 'No FAQs Found', 'soli-faq' ),
		);

		$supports = array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' );

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Members-only frequently asked questions', 'soli-faq' ),
			'supports'            => $supports,
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 7,
			'menu_icon'           => 'dashicons-editor-help',
			'can_export'          => true,
			'has_archive'         => true,
			// Not-logged-in visitors may never find FAQs through site
			// search; the flag is evaluated per request so members still can.
			'exclude_from_search' => ! is_user_logged_in(),
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'rewrite'             => array( 'slug' => 'faq' ),
			'show_in_rest'        => true,
			'rest_base'           => self::REST_BASE,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	public function enqueue_editor_assets() {
		$asset_file = SOLI_FAQ__PLUGIN_DIR_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'soli-faq-editor',
			SOLI_FAQ__PLUGIN_DIR_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'soli-faq-editor',
			'soli-faq',
			SOLI_FAQ__PLUGIN_DIR_PATH . 'languages'
		);
	}
}
