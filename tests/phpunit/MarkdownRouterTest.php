<?php
/**
 * Which HTML addresses BonBon's Accept: text/markdown is sent on from:
 * posts and pages that have Markdown for anyone to read, and nothing else.
 *
 * @package Praline
 */

use Praline\MarkdownRouter;
use Praline\Servable;

class MarkdownRouterTest extends WP_UnitTestCase {

	use SimulatesPageLinksTo;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown, text/x-opml';
	}

	public function tear_down() {
		unset( $_SERVER['HTTP_ACCEPT'] );
		parent::tear_down();
	}

	private function redirect_from( string $url ): ?string {
		$this->go_to( $url );
		return ( new MarkdownRouter() )->get_redirect_url();
	}

	public function test_a_post_is_sent_to_its_md() {
		$post = self::factory()->post->create_and_get();

		$this->assertSame( Servable::markdown_url( $post ), $this->redirect_from( get_permalink( $post ) ) );
	}

	public function test_a_page_is_sent_to_its_md() {
		$page = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );

		$this->assertSame( Servable::markdown_url( $page ), $this->redirect_from( get_permalink( $page ) ) );
	}

	public function test_the_static_front_page_is_sent_to_index_md() {
		$front = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->assertSame( home_url( '/index.md' ), $this->redirect_from( home_url( '/' ) ) );
	}

	public function test_author_date_and_category_archives_are_not() {
		$author   = self::factory()->user->create();
		$category = self::factory()->category->create();
		self::factory()->post->create(
			array(
				'post_author'   => $author,
				'post_category' => array( $category ),
				'post_date'     => '2026-11-02 10:00:00',
			)
		);

		$this->assertNull( $this->redirect_from( get_author_posts_url( $author ) ) );
		$this->assertTrue( is_author() );
		$this->assertNull( $this->redirect_from( get_month_link( 2026, 11 ) ) );
		$this->assertTrue( is_month() );
		$this->assertNull( $this->redirect_from( get_day_link( 2026, 11, 2 ) ) );
		$this->assertTrue( is_day() );
		$this->assertNull( $this->redirect_from( get_category_link( $category ) ) );
		$this->assertTrue( is_category() );
	}

	public function test_a_password_protected_post_is_not() {
		$post = self::factory()->post->create( array( 'post_password' => 'secret' ) );

		$this->assertNull( $this->redirect_from( get_permalink( $post ) ) );
	}

	public function test_not_without_text_markdown_in_accept() {
		$post                   = self::factory()->post->create();
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

		$this->assertNull( $this->redirect_from( get_permalink( $post ) ) );
	}

	public function test_a_page_links_to_post_is_sent_to_its_md_on_this_site() {
		$post = self::factory()->post->create();
		$this->go_to( get_permalink( $post ) );
		$this->link_post_elsewhere( $post, 'https://make.wordpress.org/core/2019/10/15/json/', '#new_tab' );

		$url = ( new MarkdownRouter() )->get_redirect_url();

		$this->assertStringStartsWith( home_url( '/' ), $url );
		$this->assertStringEndsWith( '.md', $url );
	}

	public function test_upstream_registers_our_redirect() {
		$router = new MarkdownRouter();
		$router->register();

		$this->assertNotFalse( has_action( 'template_redirect', array( $router, 'handle_accept_negotiation' ) ) );
	}

	public function test_markdown_is_answered_after_template_redirect_hooks_at_the_default_priority() {
		// Plugins that restrict content (a login requirement, a maintenance
		// mode) and WordPress's redirect_canonical() run at 10. Upstream
		// answers at 15 since 1.3.0.
		$router = new MarkdownRouter();
		$router->register();

		foreach ( array( 'handle_format_parameter', 'handle_accept_negotiation', 'handle_markdown_request' ) as $handler ) {
			$this->assertGreaterThan( 10, has_action( 'template_redirect', array( $router, $handler ) ), $handler );
		}
	}

	public function test_no_taxonomy_archives_are_served() {
		( new MarkdownRouter() )->register();

		$this->assertSame( array(), Praline\Vendor\MarkdownAlternate\TaxonomySupport::get_supported_taxonomies() );
	}

	public function test_bonbon_gets_a_category_archives_html() {
		( new MarkdownRouter() )->register();
		$category = self::factory()->category->create( array( 'slug' => 'baseball' ) );
		self::factory()->post->create( array( 'post_category' => array( $category ) ) );

		$this->assertNull( $this->redirect_from( get_category_link( $category ) ) );
	}
}
