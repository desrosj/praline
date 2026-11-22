<?php
/**
 * Keeps pages that are hidden from search engines off the Markdown web too.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns on Markdown Alternate's skip for noindexed content (off by
 * default there), so a page hidden from search engines with Yoast SEO has
 * no .md address, no Accept redirect, and no line in the outline or search.
 * A site that wants them back adds
 * add_filter( 'markdown_alternate_skip_noindexed', '__return_false', 20 ).
 */
class NoindexPolicy {

	/**
	 * Hooks in.
	 */
	public function register(): void {
		add_filter( 'markdown_alternate_skip_noindexed', '__return_true' );
	}
}
