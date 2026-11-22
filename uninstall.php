<?php
/**
 * Removes what the plugin stored when it is deleted: the site version, the
 * cached outline and each post's cached Markdown. The rewrite rules went
 * when it was deactivated.
 *
 * WordPress runs this file without loading the plugin, so the names are
 * written out here: SiteVersion::OPTION, TableOfContents::CACHE_KEY, and
 * the bundled ContentRenderer's md_alt_cache_{post ID}.
 *
 * @package Praline
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$praline_uninstall = static function () {
	global $wpdb;

	delete_option( 'praline_site_version' );
	delete_transient( 'praline_toc' );

	if ( wp_using_ext_object_cache() ) {
		// The transients are in the object cache, one per post.
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Once, on uninstall; every post, whatever its type or status.
	} else {
		// There is no API to find transients by prefix.
		$prefix   = '_transient_md_alt_cache_';
		$names    = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Once, on uninstall.
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' )
		);
		$post_ids = array_map(
			static function ( $name ) use ( $prefix ) {
				return substr( $name, strlen( $prefix ) );
			},
			$names
		);
	}

	foreach ( $post_ids as $post_id ) {
		delete_transient( 'md_alt_cache_' . $post_id );
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $praline_site_id ) {
		switch_to_blog( $praline_site_id );
		$praline_uninstall();
		restore_current_blog();
	}
} else {
	$praline_uninstall();
}
