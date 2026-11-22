<?php
/**
 * The parts of Markdown Alternate this plugin ships, renamed into its own
 * namespace by Strauss, and only those parts.
 *
 * @package Praline
 */

class BundleTest extends WP_UnitTestCase {

	private function path( string $relative ): string {
		return dirname( __DIR__, 2 ) . '/' . $relative;
	}

	public function test_the_parts_it_needs_are_bundled_under_its_own_namespace() {
		$classes = array(
			'Praline\Vendor\MarkdownAlternate\Router\RewriteHandler',
			'Praline\Vendor\MarkdownAlternate\Router\UrlConverter',
			'Praline\Vendor\MarkdownAlternate\Output\ContentRenderer',
			'Praline\Vendor\MarkdownAlternate\Converter\MarkdownConverter',
			'Praline\Vendor\MarkdownAlternate\PostTypeSupport',
			'Praline\Vendor\MarkdownAlternate\Discovery\AlternateLinkHandler',
			'Praline\Vendor\League\HTMLToMarkdown\HtmlConverter',
		);
		foreach ( $classes as $class ) {
			$this->assertTrue( class_exists( $class ), "$class is bundled" );
		}
	}

	public function test_the_parts_it_does_not_need_are_not() {
		$this->assertFileDoesNotExist( $this->path( 'vendor-prefixed/joost/markdown-alternate/markdown-alternate.php' ) );
		$this->assertFileDoesNotExist( $this->path( 'vendor-prefixed/joost/markdown-alternate/src/Plugin.php' ) );
		$this->assertFileDoesNotExist( $this->path( 'vendor-prefixed/joost/markdown-alternate/src/Integration' ) );
		$this->assertDirectoryDoesNotExist( $this->path( 'vendor-prefixed/yahnis-elsts' ) );
	}

	public function test_licenses_are_kept() {
		$this->assertFileExists( $this->path( 'vendor-prefixed/joost/markdown-alternate/LICENSE' ) );
		$this->assertFileExists( $this->path( 'vendor-prefixed/league/html-to-markdown/LICENSE' ) );
	}

	public function test_changed_files_say_so() {
		$files = glob( $this->path( 'vendor-prefixed/joost/markdown-alternate/src/{,*/}*.php' ), GLOB_BRACE );
		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$source = file_get_contents( $file );
			$this->assertStringContainsString( 'Modified by Praline', $source, $file );
			$this->assertStringContainsString( 'https://github.com/ProgressPlanner/markdown-alternate', $source, $file );
		}
	}
}
