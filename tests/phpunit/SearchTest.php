<?php
/**
 * Site search for BonBon: urlSearch?q=word answers a Markdown page.
 *
 * @package Praline
 */

use Praline\Search;

class SearchTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	private function md_url( int $post_id ): string {
		return Praline\Vendor\MarkdownAlternate\Router\UrlConverter::convert_to_markdown_url( get_permalink( $post_id ) );
	}

	public function test_results_link_to_markdown_pages() {
		$post = self::factory()->post->create(
			array(
				'post_title'   => 'Writing about rhubarb',
				'post_content' => 'Rhubarb pie.',
			)
		);

		$markdown = ( new Search() )->get_markdown( 'rhubarb' );

		$this->assertStringContainsString( '[Writing about rhubarb](' . $this->md_url( $post ) . ')', $markdown );
	}

	public function test_pages_are_found_too() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Rhubarb page',
			)
		);

		$this->assertStringContainsString( $this->md_url( $page ), ( new Search() )->get_markdown( 'rhubarb' ) );
	}

	public function test_the_posts_page_is_not_found() {
		$blog = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Rhubarb blog',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $blog );

		$this->assertStringNotContainsString( $this->md_url( $blog ), ( new Search() )->get_markdown( 'rhubarb' ) );
	}

	public function test_the_page_is_titled_with_the_search_in_front_matter() {
		$markdown = ( new Search() )->get_markdown( 'rhubarb' );

		$this->assertSame( 0, strpos( $markdown, "---\ntitle: \"Search: rhubarb\"\n---\n" ) );
	}

	public function test_says_so_when_nothing_is_found() {
		$this->assertStringContainsString( 'Nothing was found', ( new Search() )->get_markdown( 'zzzunfindable' ) );
	}

	public function test_password_protected_posts_are_not_listed_even_to_a_logged_in_user() {
		// WordPress leaves them out of searches by logged-out visitors itself.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$protected = self::factory()->post->create(
			array(
				'post_title'    => 'Secret rhubarb',
				'post_password' => 'secret',
			)
		);

		$this->assertStringNotContainsString( $this->md_url( $protected ), ( new Search() )->get_markdown( 'rhubarb' ) );
	}

	public function test_the_search_cannot_break_the_front_matter_or_the_markdown() {
		$markdown = ( new Search() )->get_markdown( "x\"\n---\n# [evil](http://example.com)" );

		// The front matter is three lines, the title quoted on one of them.
		$lines = explode( "\n", $markdown );
		$this->assertSame( '---', $lines[0] );
		$this->assertStringStartsWith( 'title: "Search: ', $lines[1] );
		$this->assertStringEndsWith( '"', $lines[1] );
		$this->assertSame( '---', $lines[2] );
		// No link made of the search.
		$this->assertStringNotContainsString( '](http://example.com)', $markdown );
	}

	public function test_an_empty_search_finds_nothing() {
		self::factory()->post->create();

		$this->assertStringContainsString( 'Nothing was found', ( new Search() )->get_markdown( '  ' ) );
	}

	public function test_html_in_a_title_is_removed_and_markup_characters_escaped() {
		$post = self::factory()->post->create(
			array(
				'post_title' => 'Rhubarb <img src=x onerror=alert(1)> &lt;script&gt; a <b>bold</b> [tip] Q&amp;A',
			)
		);

		$markdown = ( new Search() )->get_markdown( 'rhubarb' );

		$this->assertStringContainsString(
			'[Rhubarb  \<script\> a bold \[tip\] Q\&A](' . $this->md_url( $post ) . ')',
			$markdown
		);
		$this->assertDoesNotMatchRegularExpression( '/(?<!\\\\)</', $markdown, 'Every < in the page is backslash-escaped.' );
	}
}
