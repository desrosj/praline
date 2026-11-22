<?php
/**
 * Whether Markdown Alternate will answer a post's .md URL with Markdown.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Praline\Vendor\MarkdownAlternate\PostTypeSupport;
use WP_Post;

/**
 * The same rules Markdown Alternate applies before serving, so nothing here
 * points BonBon at an address that answers 403 or 404.
 */
class Servable {

	/**
	 * Whether the post's Markdown is there for anyone to read. The posts
	 * page is left out too: WordPress shows the list of posts in its place,
	 * so its own Markdown is an empty page.
	 *
	 * @param WP_Post $post The post.
	 */
	public static function is_servable( WP_Post $post ): bool {
		return PostTypeSupport::is_supported( $post->post_type )
			&& 'publish' === get_post_status( $post )
			&& '' === $post->post_password
			&& ! PostTypeSupport::should_skip_noindexed( $post )
			&& ! self::is_posts_page( $post );
	}

	/**
	 * Whether the post is the page that shows the list of posts.
	 *
	 * @param WP_Post $post The post.
	 */
	private static function is_posts_page( WP_Post $post ): bool {
		return 'page' === get_option( 'show_on_front' )
			&& (int) get_option( 'page_for_posts' ) === $post->ID;
	}

	/**
	 * The post's Markdown address, as Markdown Alternate builds it.
	 *
	 * @param WP_Post $post The post.
	 */
	public static function markdown_url( WP_Post $post ): string {
		return \Praline\Vendor\MarkdownAlternate\Router\UrlConverter::convert_to_markdown_url( self::local_permalink( $post ) );
	}

	/**
	 * The post's address on this site. The Page Links To plugin swaps a
	 * post's permalink for an external URL, but its .md is still here: its
	 * page_links_to_link filter hands over the original link, which this
	 * keeps for this one call, and drops the #new_tab it may add.
	 *
	 * @param WP_Post $post The post.
	 */
	public static function local_permalink( WP_Post $post ): string {
		$keep_original = static function ( $link, $post, $original ) {
			return $original;
		};
		add_filter( 'page_links_to_link', $keep_original, PHP_INT_MAX, 3 );
		$permalink = (string) get_permalink( $post );
		remove_filter( 'page_links_to_link', $keep_original, PHP_INT_MAX );

		return explode( '#', $permalink, 2 )[0];
	}
}
