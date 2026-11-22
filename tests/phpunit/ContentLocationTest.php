<?php
/**
 * Content-Location: when Markdown Alternate answers an address that isn't
 * the .md file with Markdown, which file it sent, so BonBon finds the page
 * in the table of contents.
 *
 * @package Praline
 */

use Praline\ContentLocation;

class ContentLocationTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	private function md_url( int $post_id ): string {
		return Praline\Vendor\MarkdownAlternate\Router\UrlConverter::convert_to_markdown_url( get_permalink( $post_id ) );
	}

	public function test_a_post_asked_for_as_markdown_is_its_md_file() {
		$post = self::factory()->post->create();
		$this->go_to( add_query_arg( 'format', 'markdown', get_permalink( $post ) ) );

		$this->assertSame( $this->md_url( $post ), ( new ContentLocation() )->get_location() );
	}

	public function test_the_static_front_page_asked_for_as_markdown_is_index_md() {
		$front = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		$this->go_to( home_url( '/?format=markdown' ) );

		$this->assertSame( home_url( '/index.md' ), ( new ContentLocation() )->get_location() );
	}

	public function test_none_for_the_html_page() {
		$post = self::factory()->post->create();
		$this->go_to( get_permalink( $post ) );

		$this->assertNull( ( new ContentLocation() )->get_location() );
	}

	public function test_none_for_a_post_markdown_alternate_wont_serve() {
		$post = self::factory()->post->create( array( 'post_password' => 'secret' ) );
		$this->go_to( add_query_arg( 'format', 'markdown', get_permalink( $post ) ) );

		$this->assertNull( ( new ContentLocation() )->get_location() );
	}

	public function test_none_for_a_posts_index_front_page() {
		// Markdown Alternate has no Markdown for a list of posts.
		self::factory()->post->create();
		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/?format=markdown' ) );

		$this->assertNull( ( new ContentLocation() )->get_location() );
	}
}
