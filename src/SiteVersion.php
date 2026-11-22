<?php
/**
 * A token that changes whenever the set of published content changes.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Markdown Alternate caches each post's Markdown until that post changes. A
 * post's urlPrev and urlNext, and the table of contents, change when another
 * post is published or unpublished, so caches built from them key on this.
 */
class SiteVersion {

	const OPTION = 'praline_site_version';

	/**
	 * Watches for content entering or leaving the published set.
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'maybe_bump' ), 10, 3 );

		// Settings the outline and the Markdown addresses are built from.
		foreach ( array( 'show_on_front', 'page_on_front', 'blogname', 'permalink_structure' ) as $option ) {
			add_action( "update_option_{$option}", array( self::class, 'bump' ) );
		}
	}

	/**
	 * Changes the token.
	 */
	public static function bump(): void {
		update_option( self::OPTION, uniqid( '', true ), false );
	}

	/**
	 * The current token.
	 */
	public static function get(): string {
		return (string) get_option( self::OPTION, '' );
	}

	/**
	 * Changes the token when a post or page is published or unpublished.
	 *
	 * @param string  $new_status The post's new status.
	 * @param string  $old_status The post's old status.
	 * @param WP_Post $post       The post.
	 */
	public function maybe_bump( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		self::bump();
	}
}
