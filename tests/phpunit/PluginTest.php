<?php
/**
 * Starting up: the bundled Markdown Alternate and this plugin's parts; or,
 * with the standalone Markdown Alternate 1.2.0 or later active, only this
 * plugin's parts on top of it; or, with an older one, nothing but a notice.
 *
 * @package Praline
 */

use Praline\Plugin;
use Praline\Vendor\Composer\InstalledVersions;
use Praline\Vendor\MarkdownAlternate\Discovery\AlternateLinkHandler;
use Praline\Vendor\MarkdownAlternate\Router\RewriteHandler;

class PluginTest extends WP_UnitTestCase {

	/**
	 * The classes of the started parts.
	 */
	private function component_classes( Plugin $plugin ): array {
		return array_map( 'get_class', $plugin->get_components() );
	}

	/**
	 * How many callbacks from the bundled Markdown Alternate are hooked in.
	 * The test bootstrap has already started the plugin once, so this is
	 * compared before and after, not with zero.
	 */
	private function bundled_markdown_alternate_callbacks(): int {
		global $wp_filter;
		$count = 0;
		foreach ( array( 'init', 'query_vars', 'parse_request', 'redirect_canonical', 'template_redirect', 'wp_head' ) as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) && ( $function[0] instanceof RewriteHandler || $function[0] instanceof AlternateLinkHandler ) ) {
						++$count;
					}
				}
			}
		}
		return $count;
	}

	/**
	 * What Site Health's Info screen shows for this plugin.
	 */
	private function site_health_markdown(): string {
		$info = apply_filters( 'debug_information', array() );
		$this->assertArrayHasKey( 'praline', $info );
		return (string) $info['praline']['fields']['markdown']['value'];
	}

	/**
	 * The notice, as an administrator sees it.
	 */
	private function notice( Plugin $plugin ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		$plugin->standalone_too_old_notice();
		return (string) ob_get_clean();
	}

	public function test_it_starts_its_parts() {
		$before = $this->bundled_markdown_alternate_callbacks();
		$plugin = new Plugin( null );
		$plugin->boot();

		$this->assertGreaterThan( $before, $this->bundled_markdown_alternate_callbacks(), 'The bundled Markdown Alternate is hooked in.' );

		$classes = $this->component_classes( $plugin );
		$this->assertContains( Praline\MarkdownRouter::class, $classes );
		$this->assertNotContains( RewriteHandler::class, $classes );
		$this->assertContains( AlternateLinkHandler::class, $classes );
		$this->assertContains( Praline\SiteVersion::class, $classes );
		$this->assertContains( Praline\NoindexPolicy::class, $classes );
		$this->assertContains( Praline\CachePolicy::class, $classes );
		$this->assertContains( Praline\FrontMatter::class, $classes );
		$this->assertContains( Praline\Router::class, $classes );
		$this->assertContains( Praline\ContentLocation::class, $classes );
	}

	public function test_site_health_says_the_bundled_copy_serves_markdown() {
		( new Plugin( null ) )->boot();

		$markdown = $this->site_health_markdown();
		$this->assertStringContainsString( 'bundled', $markdown );
		$this->assertStringContainsString( ltrim( (string) InstalledVersions::getPrettyVersion( 'joost/markdown-alternate' ), 'v' ), $markdown );
	}

	public function test_with_standalone_markdown_alternate_it_starts_only_its_own_parts() {
		$before = $this->bundled_markdown_alternate_callbacks();
		$plugin = new Plugin( '1.2.0' );
		$plugin->boot();

		$classes = $this->component_classes( $plugin );
		sort( $classes );
		$expected = array(
			Praline\CachePolicy::class,
			Praline\ContentLocation::class,
			Praline\FrontMatter::class,
			Praline\NoindexPolicy::class,
			Praline\Router::class,
			Praline\SiteVersion::class,
		);
		sort( $expected );
		$this->assertSame( $expected, $classes );
		$this->assertSame( $before, $this->bundled_markdown_alternate_callbacks(), 'Nothing from the bundled Markdown Alternate is hooked in.' );
	}

	public function test_with_standalone_markdown_alternate_it_shows_no_notice() {
		remove_all_actions( 'admin_notices' );

		( new Plugin( '1.3.1' ) )->boot();

		$this->assertFalse( has_action( 'admin_notices' ) );
	}

	public function test_with_standalone_markdown_alternate_site_health_says_it_serves_markdown() {
		( new Plugin( '1.3.1' ) )->boot();

		$markdown = $this->site_health_markdown();
		$this->assertStringContainsString( 'standalone', $markdown );
		$this->assertStringContainsString( '1.3.1', $markdown );
	}

	public function test_with_an_older_standalone_markdown_alternate_it_stands_down_with_a_notice() {
		$plugin = new Plugin( '1.1.0' );
		$plugin->boot();

		$this->assertSame( array(), $plugin->get_components() );
		$this->assertNotFalse( has_action( 'admin_notices', array( $plugin, 'standalone_too_old_notice' ) ) );
	}

	public function test_with_an_older_standalone_markdown_alternate_nothing_routes() {
		remove_all_actions( 'template_redirect' );
		remove_all_actions( 'parse_request' );

		( new Plugin( '1.1.0' ) )->boot();

		$this->assertFalse( has_action( 'template_redirect' ) );
		$this->assertFalse( has_action( 'parse_request' ) );
	}

	public function test_a_standalone_markdown_alternate_of_unknown_version_counts_as_older() {
		$plugin = new Plugin( '' );
		$plugin->boot();

		$this->assertSame( array(), $plugin->get_components() );
		$this->assertNotFalse( has_action( 'admin_notices', array( $plugin, 'standalone_too_old_notice' ) ) );
	}

	public function test_the_notice_says_to_update_or_deactivate_markdown_alternate() {
		$notice = $this->notice( new Plugin( '1.1.0' ) );

		$this->assertStringContainsString( 'Markdown Alternate 1.1.0 is older than 1.2.0', $notice );
		$this->assertStringContainsString( 'update or deactivate', $notice );
		$this->assertStringContainsString( 'notice-warning', $notice );
	}

	public function test_the_notice_is_only_for_people_who_manage_plugins() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$plugin = new Plugin( '1.1.0' );

		ob_start();
		$plugin->standalone_too_old_notice();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_with_an_older_standalone_markdown_alternate_site_health_says_it_has_stopped() {
		( new Plugin( '1.1.0' ) )->boot();

		$this->assertStringContainsString( 'stopped', $this->site_health_markdown() );
	}

	public function test_activation_adds_the_md_rewrite_rules() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		delete_option( 'rewrite_rules' );

		Plugin::activate();

		$this->assertArrayHasKey( '(.+?)\.md$', get_option( 'rewrite_rules' ) );
	}

	public function test_deactivation_removes_the_md_rewrite_rules() {
		// While the plugin runs, init has already added its rules.
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		( new Praline\MarkdownRouter() )->add_rewrite_rules();
		flush_rewrite_rules();
		$this->assertArrayHasKey( '(.+?)\.md$', get_option( 'rewrite_rules' ) );

		Plugin::deactivate();

		$this->assertArrayNotHasKey( '(.+?)\.md$', (array) get_option( 'rewrite_rules' ) );
	}

	public function test_starts_after_all_plugins_have_loaded() {
		$this->assertNotFalse( has_action( 'plugins_loaded', 'praline_boot' ) );
	}
}
