<?php

namespace Soli\Faq;

/*
  Plugin Name: Soli FAQ Plugin
  Version: 0.1.0
  Author: Joran Out
  Description: Members-only frequently asked questions (FAQs) post type for Soli sites, usable in the Query Loop block
  Requires PHP: 8.2
  Text Domain: soli-faq
  Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOLI_FAQ__PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOLI_FAQ__PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SOLI_FAQ__PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'SOLI_FAQ__PLUGIN_VERSION', '0.1.0' );

require_once SOLI_FAQ__PLUGIN_DIR_PATH . 'includes/class-soli-faq-post-type.php';
require_once SOLI_FAQ__PLUGIN_DIR_PATH . 'includes/class-soli-faq-visibility.php';

add_action( 'init', function () {
	load_plugin_textdomain( 'soli-faq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	include_once 'updater.php';

	if ( ! defined( 'WP_GITHUB_FORCE_UPDATE' ) ) {
		define( 'WP_GITHUB_FORCE_UPDATE', true );
	}

	if ( is_admin() ) {
		$config = array(
			'slug'               => plugin_basename( __FILE__ ),
			'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
			'api_url'            => 'https://api.github.com/repos/JoranOut/wp-soli-faq-plugin',
			'raw_url'            => 'https://raw.github.com/JoranOut/wp-soli-faq-plugin/main',
			'github_url'         => 'https://github.com/JoranOut/wp-soli-faq-plugin',
			'zip_url'            => 'https://github.com/JoranOut/wp-soli-faq-plugin/archive/refs/heads/main.zip',
			'sslverify'          => true,
			'requires'           => '6.0.0',
			'tested'             => '6.7.0',
			'readme'             => 'readme.md',
		);

		new WP_GitHub_Updater( $config );
	}
} );

// Register the FAQ post type and its Query Loop variation script
$soli_faq_post_type = new Post_Type();
$soli_faq_post_type->init();

// Keep FAQs invisible to not-logged-in visitors
$soli_faq_visibility = new Visibility();
$soli_faq_visibility->init();
