<?php
/**
 * Plugin Name:       Praline
 * Plugin URI:        https://github.com/desrosj/praline
 * Description:       Serves your posts and pages as Markdown for BonBon, the browser for the Markdown web, with BonBon front matter, a table of contents, site search and Content-Location. Includes parts of Markdown Alternate.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Jonathan Desrosiers
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       praline
 *
 * @package Praline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The parts of Markdown Alternate this plugin ships, renamed by Strauss.
require __DIR__ . '/vendor-prefixed/autoload.php';

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Praline\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

/**
 * Starts the plugin once every plugin has loaded, so a standalone Markdown
 * Alternate, which loads after this one, is already there to be noticed.
 */
function praline_boot() {
	$standalone_version = null;
	if ( class_exists( 'MarkdownAlternate\Plugin', false ) ) {
		$standalone_version = defined( 'MARKDOWN_ALTERNATE_VERSION' ) ? (string) MARKDOWN_ALTERNATE_VERSION : '';
	}
	( new Praline\Plugin( $standalone_version ) )->boot();
}
add_action( 'plugins_loaded', 'praline_boot' );

register_activation_hook( __FILE__, array( 'Praline\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Praline\Plugin', 'deactivate' ) );
