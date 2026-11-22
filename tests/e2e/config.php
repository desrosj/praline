<?php
/**
 * wp-config for the end-to-end site, kept out of the WordPress checkout:
 * the router and the installer define these before loading WordPress.
 *
 * @package Praline
 */

// Test site settings: for the command line and PHP's built-in server only.
if ( ! in_array( PHP_SAPI, array( 'cli', 'cli-server' ), true ) ) {
	exit( 1 );
}

define( 'DB_NAME', getenv( 'E2E_DB_NAME' ) ?: 'praline_e2e' );
define( 'DB_USER', getenv( 'E2E_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'E2E_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'E2E_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$e2e_url = 'http://localhost:' . ( getenv( 'E2E_PORT' ) ?: '8765' );
define( 'WP_HOME', $e2e_url );
define( 'WP_SITEURL', $e2e_url );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', getenv( 'E2E_LOG' ) ?: false );

// The WordPress checkout's src/ folder, five levels up from here.
define( 'ABSPATH', dirname( __DIR__, 5 ) . '/' );

$table_prefix = 'wp_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
