<?php
/**
 * PHPUnit bootstrap: the WordPress test suite, with this plugin loaded. It
 * bundles the parts of Markdown Alternate it needs, so the standalone plugin
 * isn't loaded.
 *
 * The test suite is found in WP_TESTS_DIR when that's set; otherwise in the
 * wordpress-develop checkout around this plugin, when there is one; otherwise
 * where bin/install-wp-tests.sh puts it.
 *
 * @package Praline
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	// In <wordpress-develop>/src/wp-content/plugins/<slug>/.
	$_tests_dir = dirname( __DIR__, 5 ) . '/tests/phpunit';
	if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
		$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
	}
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Can't find the WordPress test suite in {$_tests_dir}. Set WP_TESTS_DIR, or run bin/install-wp-tests.sh.\n" );
	exit( 1 );
}

// This plugin's own PHPUnit Polyfills, when its dev dependencies are installed.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && is_dir( dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';
require_once __DIR__ . '/helpers/SimulatesPageLinksTo.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/praline.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
