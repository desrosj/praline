<?php
/**
 * The addresses BonBon asks a site for: /.well-known/toc.opml,
 * /.well-known/bonbon.json, and the search bonbon.json names.
 *
 * @package Praline
 */

use Praline\Router;
use Praline\TableOfContents;

class RouterTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function test_serves_the_table_of_contents_as_opml() {
		self::factory()->post->create();

		$response = ( new Router() )->route( '/.well-known/toc.opml' );

		$this->assertSame( 200, $response->status );
		$this->assertSame( 'text/x-opml; charset=UTF-8', $response->headers['Content-Type'] );
		$this->assertSame( ( new TableOfContents() )->get_opml(), $response->body );
	}

	public function test_a_query_string_doesnt_change_the_address() {
		$response = ( new Router() )->route( '/.well-known/toc.opml?ver=2' );

		$this->assertSame( 200, $response->status );
	}

	public function test_bonbon_json_names_the_search() {
		$response = ( new Router() )->route( '/.well-known/bonbon.json' );

		$this->assertSame( 200, $response->status );
		$this->assertSame( 'application/json; charset=UTF-8', $response->headers['Content-Type'] );
		$this->assertSame(
			array( 'urlSearch' => home_url( '/.well-known/bonbon-search' ) ),
			json_decode( $response->body, true )
		);
	}

	public function test_search_answers_markdown() {
		$post = self::factory()->post->create( array( 'post_title' => 'Rhubarb' ) );

		$response = ( new Router() )->route( '/.well-known/bonbon-search?q=rhubarb' );

		$this->assertSame( 200, $response->status );
		$this->assertSame( 'text/markdown; charset=UTF-8', $response->headers['Content-Type'] );
		$this->assertStringContainsString( Praline\Servable::markdown_url( get_post( $post ) ), $response->body );
	}

	public function test_search_without_a_query_answers_an_empty_page() {
		$response = ( new Router() )->route( '/.well-known/bonbon-search' );

		$this->assertSame( 200, $response->status );
		$this->assertStringContainsString( 'Nothing was found', $response->body );
	}

	public function test_answers_are_not_sniffed() {
		$response = ( new Router() )->route( '/.well-known/bonbon.json' );

		$this->assertSame( 'nosniff', $response->headers['X-Content-Type-Options'] );
	}

	public function test_other_addresses_are_left_to_wordpress() {
		$router = new Router();

		$this->assertNull( $router->route( '/' ) );
		$this->assertNull( $router->route( '/hello-world.md' ) );
		$this->assertNull( $router->route( '/.well-known/acme-challenge/abc' ) );
		$this->assertNull( $router->route( '/.well-known/toc.opml/extra' ) );
	}

	public function test_addresses_are_under_the_sites_home_path() {
		update_option( 'home', 'http://example.org/blog' );
		$router = new Router();

		$this->assertSame( 200, $router->route( '/blog/.well-known/bonbon.json' )->status );
		$this->assertNull( $router->route( '/.well-known/bonbon.json' ) );
	}

	public function test_answers_after_template_redirect_hooks_at_the_default_priority() {
		// Plugins that restrict content (a login requirement, a maintenance
		// mode) run at 10; Markdown Alternate's handlers at 15.
		$router = new Router();
		$router->register();

		$priority = has_action( 'template_redirect', array( $router, 'handle' ) );
		$this->assertGreaterThan( 10, $priority );
		$this->assertLessThan( 15, $priority );
		$this->assertFalse( has_action( 'parse_request', array( $router, 'handle' ) ), 'Nothing is sent from parse_request.' );
	}

	public function test_a_matched_request_queries_nothing_and_is_not_a_404() {
		self::factory()->post->create();
		$router = new Router();
		$router->register();

		$this->go_to( home_url( '/.well-known/toc.opml' ) );

		$this->assertSame( array(), $GLOBALS['wp_query']->posts );
		$this->assertFalse( is_404() );
		$this->assertFalse( apply_filters( 'redirect_canonical', home_url( '/somewhere/' ), home_url( '/.well-known/toc.opml' ) ) );
	}

	public function test_other_requests_are_queried_as_usual() {
		$post   = self::factory()->post->create();
		$router = new Router();
		$router->register();

		$this->go_to( get_permalink( $post ) );

		$this->assertSame( $post, $GLOBALS['wp_query']->posts[0]->ID );
		$this->assertFalse( has_filter( 'posts_pre_query', array( $router, 'skip_main_query' ) ) );
	}
}
