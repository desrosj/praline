<?php
/**
 * Only a visitor with nothing of their own shares the cached Markdown.
 *
 * @package Praline
 */

use Praline\CachePolicy;
use Praline\Vendor\MarkdownAlternate\Output\ContentRenderer;

class CachePolicyTest extends WP_UnitTestCase {

	/**
	 * The cookies before the test.
	 *
	 * @var array
	 */
	private array $cookies = array();

	public function set_up() {
		parent::set_up();
		$this->cookies = $_COOKIE;

		remove_all_filters( 'markdown_alternate_cache_enabled' );
		( new CachePolicy() )->register();

		// Content that is different for each visitor.
		add_shortcode(
			'praline_whoami',
			static function () {
				$user = wp_get_current_user();
				return $user->exists() ? 'Hello, ' . $user->user_login . '.' : 'Hello, stranger.';
			}
		);
	}

	public function tear_down() {
		$_COOKIE = $this->cookies;
		remove_shortcode( 'praline_whoami' );
		parent::tear_down();
	}

	/**
	 * Whether Markdown Alternate may use its shared cache for this request.
	 */
	private function cache_enabled(): bool {
		return (bool) apply_filters( 'markdown_alternate_cache_enabled', true, self::factory()->post->create_and_get() );
	}

	private function log_in(): WP_User {
		$user = self::factory()->user->create_and_get(
			array(
				'role'       => 'administrator',
				'user_login' => 'pralineadmin',
			)
		);
		wp_set_current_user( $user->ID );
		return $user;
	}

	public function test_an_anonymous_visitor_uses_the_cache() {
		wp_set_current_user( 0 );

		$this->assertTrue( $this->cache_enabled() );
	}

	public function test_a_logged_in_visitor_does_not() {
		$this->log_in();

		$this->assertFalse( $this->cache_enabled() );
	}

	public function test_a_visitor_with_a_session_cookie_does_not() {
		wp_set_current_user( 0 );

		foreach ( array( 'wp_woocommerce_session_abc123', 'woocommerce_items_in_cart', 'edd_items_in_cart', 'wp-postpass_abc123' ) as $cookie ) {
			$_COOKIE = array( $cookie => '1' );
			$this->assertFalse( $this->cache_enabled(), $cookie );
		}
	}

	public function test_other_cookies_do_not_count() {
		wp_set_current_user( 0 );
		$_COOKIE = array(
			'wordpress_test_cookie' => 'WP Cookie check',
			'comment_author_abc123' => 'Someone',
		);

		$this->assertTrue( $this->cache_enabled() );
	}

	public function test_a_site_can_say_a_visitor_has_state() {
		wp_set_current_user( 0 );
		add_filter( 'praline_visitor_has_state', '__return_true' );

		$this->assertFalse( $this->cache_enabled() );
	}

	public function test_it_never_turns_caching_back_on() {
		wp_set_current_user( 0 );
		add_filter( 'markdown_alternate_cache_enabled', '__return_false', 5 );

		$this->assertFalse( $this->cache_enabled() );
	}

	public function test_markdown_rendered_for_a_logged_in_visitor_is_not_cached() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => '[praline_whoami]' ) );
		$this->log_in();

		$markdown = ( new ContentRenderer() )->render( $post );

		$this->assertStringContainsString( 'Hello, pralineadmin.', $markdown );
		$this->assertFalse( get_transient( 'md_alt_cache_' . $post->ID ) );
	}

	public function test_an_anonymous_visitor_never_sees_a_logged_in_visitors_markdown() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => '[praline_whoami]' ) );

		$this->log_in();
		( new ContentRenderer() )->render( $post );

		wp_set_current_user( 0 );
		$markdown = ( new ContentRenderer() )->render( $post );

		$this->assertStringContainsString( 'Hello, stranger.', $markdown );
		$this->assertStringNotContainsString( 'pralineadmin', $markdown );
	}

	public function test_a_logged_in_visitor_never_gets_the_anonymous_markdown() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => '[praline_whoami]' ) );

		wp_set_current_user( 0 );
		( new ContentRenderer() )->render( $post );
		$this->assertIsArray( get_transient( 'md_alt_cache_' . $post->ID ), 'The anonymous render is cached.' );

		$this->log_in();
		$markdown = ( new ContentRenderer() )->render( $post );

		$this->assertStringContainsString( 'Hello, pralineadmin.', $markdown );
	}
}
