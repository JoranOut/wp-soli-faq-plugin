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

// The plugin stores no options and creates no tables. Published FAQs
// are intentionally left in the database so nothing is lost on uninstall.
