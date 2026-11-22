<?php
/**
 * Router for PHP's built-in server: real files as they are, everything
 * else through WordPress, the way a .htaccess with pretty permalinks does.
 *
 * Usage: php -S localhost:8765 -t <checkout>/src tests/e2e/router.php
 *
 * @package Praline
 */

// For PHP's built-in server only, never a real web server.
if ( 'cli-server' !== PHP_SAPI ) {
	exit( 1 );
}

$e2e_path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); // phpcs:ignore
if ( '/' !== $e2e_path && is_file( $_SERVER['DOCUMENT_ROOT'] . $e2e_path ) && ! str_ends_with( $e2e_path, '.php' ) ) {
	return false;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require __DIR__ . '/config.php';
define( 'WP_USE_THEMES', true );
require ABSPATH . 'wp-settings.php';
wp();
require ABSPATH . WPINC . '/template-loader.php';
