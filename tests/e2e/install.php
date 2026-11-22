<?php
/**
 * Installs the end-to-end site: a static front page, an about page, three
 * posts (one password protected), pretty permalinks, and this plugin
 * active, with the standalone Markdown Alternate active too when
 * E2E_WITH_MARKDOWN_ALTERNATE is set. Prints the posts' IDs as JSON.
 *
 * @package Praline
 */

// Installs a test site: from the command line only, never over the web.
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/config.php';
require ABSPATH . 'wp-settings.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';

wp_install( 'BonBon E2E', 'admin', 'admin@example.org', true, '', 'password' );

update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
update_option(
	'active_plugins',
	getenv( 'E2E_WITH_MARKDOWN_ALTERNATE' )
		? array( 'markdown-alternate/markdown-alternate.php', 'praline/praline.php' )
		: array( 'praline/praline.php' )
);

$home  = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Hi, I am the home page',
		'post_content' => '<p>Welcome.</p>',
	)
);
$about = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'About',
		'post_content' => '<p>About me.</p>',
	)
);
$blog  = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Blog',
	)
);
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );
update_option( 'page_for_posts', $blog );

$ids = array(
	'home'  => $home,
	'about' => $about,
	'blog'  => $blog,
);
foreach ( array( 'older' => '2026-11-01 09:00:00', 'newer' => '2026-11-02 09:00:00' ) as $key => $date ) {
	$ids[ $key ] = wp_insert_post(
		array(
			'post_status'   => 'publish',
			'post_title'    => ucfirst( $key ) . ' rhubarb post',
			'post_name'     => $key . '-rhubarb-post',
			'post_content'  => '<p>Some <strong>rhubarb</strong> text.</p>',
			'post_excerpt'  => 'The ' . $key . ' one.',
			'post_date'     => $date,
			'post_date_gmt' => $date,
		)
	);
}
$ids['protected'] = wp_insert_post(
	array(
		'post_status'   => 'publish',
		'post_title'    => 'Protected rhubarb post',
		'post_name'     => 'protected-rhubarb-post',
		'post_content'  => '<p>Secret.</p>',
		'post_password' => 'secret',
		'post_date'     => '2026-11-03 09:00:00',
	)
);

// Delete the sample post and page wp_install made, so the outline is known.
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1, 'post__not_in' => array_values( $ids ) ) ) as $sample ) {
	wp_delete_post( $sample->ID, true );
}

echo wp_json_encode( $ids );
