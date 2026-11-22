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
}
