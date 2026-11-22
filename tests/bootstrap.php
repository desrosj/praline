<?php
/**
 * PHPUnit bootstrap: the WordPress test suite from the wordpress-develop
 * checkout, with this plugin loaded. It bundles the parts of Markdown
 * Alternate it needs, so the standalone plugin isn't loaded.
 *
 * @package Praline
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	// The plugin lives in <wordpress-develop>/src/wp-content/plugins/<slug>/.
	$_tests_dir = dirname( __DIR__, 5 ) . '/tests/phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Can't find the WordPress test suite in {$_tests_dir}. Set WP_TESTS_DIR.\n" );
	exit( 1 );
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
