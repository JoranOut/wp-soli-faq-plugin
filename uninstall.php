<?php
/**
 * Uninstall script for Soli FAQ Plugin
 *
 * This file is executed when the plugin is deleted through the WordPress admin.
 *
 * @package Soli\Faq
 */

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// A second copy of this plugin may still be active - for instance when another
// version is installed alongside this one and this folder is the one being
// deleted. Removing shared data would break that active copy, so bail out.
$soli_faq_active = (array) get_option( 'active_plugins', array() );
if ( is_multisite() ) {
	$soli_faq_active = array_merge( $soli_faq_active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
}
foreach ( $soli_faq_active as $soli_faq_active_file ) {
	if ( basename( $soli_faq_active_file ) === 'wp-soli-faq-plugin.php' && dirname( $soli_faq_active_file ) !== basename( __DIR__ ) ) {
		return;
	}
}
unset( $soli_faq_active, $soli_faq_active_file );

// The plugin stores no options and creates no tables. Published FAQs
// are intentionally left in the database so nothing is lost on uninstall.
