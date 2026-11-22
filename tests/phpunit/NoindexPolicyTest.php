<?php
/**
 * Pages kept out of search engines are kept off the Markdown web too.
 *
 * @package Praline
 */

use Praline\NoindexPolicy;

class NoindexPolicyTest extends WP_UnitTestCase {

	public function test_tells_markdown_alternate_to_skip_noindexed_content() {
		remove_all_filters( 'markdown_alternate_skip_noindexed' );
		( new NoindexPolicy() )->register();

		$post = self::factory()->post->create_and_get();

		$this->assertTrue( apply_filters( 'markdown_alternate_skip_noindexed', false, $post ) );
	}

	public function test_a_site_can_still_turn_it_off() {
		remove_all_filters( 'markdown_alternate_skip_noindexed' );
		( new NoindexPolicy() )->register();
		add_filter( 'markdown_alternate_skip_noindexed', '__return_false', 20 );

		$post = self::factory()->post->create_and_get();

		$this->assertFalse( apply_filters( 'markdown_alternate_skip_noindexed', false, $post ) );
	}
}
