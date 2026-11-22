<?php
/**
 * A post's address on this site, which Page Links To can swap for an
 * external one.
 *
 * @package Praline
 */

use Praline\Servable;

class ServableTest extends WP_UnitTestCase {

	use SimulatesPageLinksTo;

	const EXTERNAL = 'https://make.wordpress.org/core/2019/10/15/php-native-json-extension-now-required/';

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
	}

	public function test_without_page_links_to_it_is_the_permalink() {
		$post = self::factory()->post->create_and_get();

		$this->assertSame( get_permalink( $post ), Servable::local_permalink( $post ) );
	}

	public function test_a_page_links_to_post_is_its_address_on_this_site() {
		$post = self::factory()->post->create_and_get( array( 'post_name' => 'json-note' ) );
		$this->link_post_elsewhere( $post->ID, self::EXTERNAL );

		$local = Servable::local_permalink( $post );

		$this->assertStringStartsWith( home_url( '/' ), $local );
		$this->assertStringContainsString( '/json-note/', $local );
		// The swap is only undone for this call.
		$this->assertSame( self::EXTERNAL, get_permalink( $post ) );
	}

	public function test_a_new_tab_fragment_is_dropped() {
		$post = self::factory()->post->create_and_get();
		$this->link_post_elsewhere( $post->ID, self::EXTERNAL, '#new_tab' );

		$this->assertStringNotContainsString( '#', Servable::local_permalink( $post ) );
	}

	public function test_its_md_is_on_this_site() {
		$post = self::factory()->post->create_and_get( array( 'post_name' => 'json-note' ) );
		$this->link_post_elsewhere( $post->ID, self::EXTERNAL, '#new_tab' );

		$this->assertMatchesRegularExpression( '~^' . preg_quote( home_url( '/' ), '~' ) . '\d{4}/\d{2}/json-note\.md$~', Servable::markdown_url( $post ) );
	}
}
