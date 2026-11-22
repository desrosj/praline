<?php
/**
 * Stands in for the Page Links To plugin (3.4.1): it swaps a post's
 * permalink for an external URL on post_link at priority 20, through its
 * public page_links_to_link filter, which gets the original link as its
 * third argument, and adds #new_tab when the link opens in a new tab.
 *
 * @package Praline
 */

trait SimulatesPageLinksTo {

	/**
	 * Makes the post link to an external URL, the way Page Links To does.
	 *
	 * @param int    $post_id  The post.
	 * @param string $external The external URL.
	 * @param string $suffix   What Page Links To appends, like '#new_tab'.
	 */
	protected function link_post_elsewhere( int $post_id, string $external, string $suffix = '' ): void {
		add_filter(
			'post_link',
			static function ( $link, $post ) use ( $post_id, $external, $suffix ) {
				if ( (int) $post->ID !== $post_id ) {
					return $link;
				}
				return esc_url( apply_filters( 'page_links_to_link', $external, $post, $link ) ) . $suffix;
			},
			20,
			2
		);
	}
}
