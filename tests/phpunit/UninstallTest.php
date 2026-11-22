<?php
/**
 * What deleting the plugin leaves behind: nothing it stored.
 *
 * @package Praline
 */

use Praline\SiteVersion;
use Praline\TableOfContents;

class UninstallTest extends WP_UnitTestCase {

	/**
	 * Runs uninstall.php, as WordPress does when the plugin is deleted.
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'praline/praline.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	public function test_removes_the_site_version() {
		update_option( SiteVersion::OPTION, 'abc' );

		$this->uninstall();

		$this->assertFalse( get_option( SiteVersion::OPTION ) );
	}

	public function test_removes_the_cached_outline() {
		set_transient( TableOfContents::CACHE_KEY, array( 'opml' => '<opml/>' ) );

		$this->uninstall();

		$this->assertFalse( get_transient( TableOfContents::CACHE_KEY ) );
	}

	public function test_removes_every_posts_cached_markdown() {
		$first  = self::factory()->post->create();
		$second = self::factory()->post->create();
		set_transient( 'md_alt_cache_' . $first, array( 'markdown' => 'one' ), DAY_IN_SECONDS );
		set_transient( 'md_alt_cache_' . $second, array( 'markdown' => 'two' ), DAY_IN_SECONDS );

		$this->uninstall();

		$this->assertFalse( get_transient( 'md_alt_cache_' . $first ) );
		$this->assertFalse( get_transient( 'md_alt_cache_' . $second ) );
	}

	public function test_leaves_everything_else() {
		update_option( 'blogname', 'Still here' );
		set_transient( 'someone_elses_cache', 'kept' );

		$this->uninstall();

		$this->assertSame( 'Still here', get_option( 'blogname' ) );
		$this->assertSame( 'kept', get_transient( 'someone_elses_cache' ) );
	}
}
