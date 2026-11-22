<?php
/**
 * The site's table of contents, the OPML outline BonBon reads at
 * /.well-known/toc.opml.
 *
 * @package Praline
 */

use Praline\TableOfContents;

class TableOfContentsTest extends WP_UnitTestCase {

	use SimulatesPageLinksTo;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	/**
	 * The outline, parsed.
	 */
	private function outline(): SimpleXMLElement {
		$opml = ( new TableOfContents() )->get_opml();
		$xml  = simplexml_load_string( $opml );
		$this->assertInstanceOf( SimpleXMLElement::class, $xml, "The outline is XML:\n" . $opml );
		return $xml;
	}

	/**
	 * Every outline line with an address, in the order BonBon walks them.
	 */
	private function stops(): array {
		$urls = array();
		foreach ( $this->outline()->xpath( '//outline[@url]' ) as $node ) {
			$urls[] = (string) $node['url'];
		}
		return $urls;
	}

	private function md_url( int $post_id ): string {
		return Praline\Vendor\MarkdownAlternate\Router\UrlConverter::convert_to_markdown_url( get_permalink( $post_id ) );
	}

	public function test_is_an_opml_2_outline_titled_with_the_site_name() {
		update_option( 'blogname', 'Jonathan & Friends' );

		$xml = $this->outline();

		$this->assertSame( '2.0', (string) $xml['version'] );
		$this->assertSame( 'Jonathan & Friends', (string) $xml->head->title );
	}

	public function test_pages_come_after_the_posts_as_top_level_lines_without_children() {
		$parent = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'About' ) );
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Speaking',
				'post_parent' => $parent,
			)
		);
		self::factory()->post->create( array( 'post_date' => '2026-01-01 10:00:00' ) );

		$top = $this->outline()->body->outline;

		// The calendar reads every top-level line with children as a year.
		$this->assertSame( 'calendarYear', (string) $top[0]['type'] );
		$this->assertSame( 'About', (string) $top[1]['text'] );
		$this->assertSame( $this->md_url( $parent ), (string) $top[1]['url'] );
		$this->assertCount( 0, $top[1]->outline );
		$this->assertSame( 'Speaking', (string) $top[2]['text'] );
		$this->assertSame( $this->md_url( $child ), (string) $top[2]['url'] );
	}

	public function test_a_page_links_to_post_is_listed_at_its_md_on_this_site() {
		$post = self::factory()->post->create( array( 'post_date' => '2019-10-15 10:00:00' ) );
		$this->link_post_elsewhere( $post, 'https://make.wordpress.org/core/2019/10/15/json/' );

		foreach ( $this->stops() as $url ) {
			$this->assertStringStartsWith( home_url( '/' ), $url );
		}
	}

	public function test_the_posts_page_is_not_listed() {
		// WordPress shows the list of posts in its place, so its own
		// Markdown is an empty page.
		$blog = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Blog' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		update_option( 'page_for_posts', $blog );

		$this->assertNotContains( $this->md_url( $blog ), $this->stops() );
	}

	public function test_the_static_front_page_is_the_first_page_at_index_md() {
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Another page' ) );
		$front = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Home' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		self::factory()->post->create( array( 'post_date' => '2026-01-01 10:00:00' ) );

		$top = $this->outline()->body->outline;

		$this->assertSame( home_url( '/index.md' ), (string) $top[1]['url'] );
	}

	public function test_posts_are_in_years_months_and_days_newest_first() {
		$older = self::factory()->post->create( array( 'post_date' => '2025-12-31 10:00:00' ) );
		$newer = self::factory()->post->create( array( 'post_date' => '2026-02-05 10:00:00' ) );

		$years = $this->outline()->body->outline;

		$this->assertSame( 'calendarYear', (string) $years[0]['type'] );
		$this->assertSame( '2026', (string) $years[0]['name'] );
		$this->assertSame( '2025', (string) $years[1]['name'] );

		$month = $years[0]->outline[0];
		$this->assertSame( 'calendarMonth', (string) $month['type'] );
		$this->assertSame( '02', (string) $month['name'] );
		$this->assertSame( 'February 2026', (string) $month['text'] );

		$day = $month->outline[0];
		$this->assertSame( 'calendarDay', (string) $day['type'] );
		$this->assertSame( '05', (string) $day['name'] );
		$this->assertSame( 'February 5', (string) $day['text'] );
		$this->assertSame( $this->md_url( $newer ), (string) $day['url'] );

		$this->assertSame( $this->md_url( $older ), (string) $years[1]->outline[0]->outline[0]['url'] );
	}

	public function test_a_days_other_posts_are_under_its_newest() {
		$morning = self::factory()->post->create(
			array(
				'post_date'  => '2026-03-01 08:00:00',
				'post_title' => 'Morning',
			)
		);
		$evening = self::factory()->post->create(
			array(
				'post_date'  => '2026-03-01 20:00:00',
				'post_title' => 'Evening',
			)
		);

		$day = $this->outline()->body->outline[0]->outline[0]->outline[0];

		$this->assertSame( $this->md_url( $evening ), (string) $day['url'] );
		$this->assertCount( 1, $day->outline );
		$this->assertSame( 'Morning', (string) $day->outline[0]['text'] );
		$this->assertSame( $this->md_url( $morning ), (string) $day->outline[0]['url'] );
	}

	public function test_only_content_markdown_alternate_serves_is_listed() {
		$published = self::factory()->post->create();
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$private   = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$protected = self::factory()->post->create( array( 'post_password' => 'secret' ) );

		$stops = $this->stops();

		$this->assertContains( $this->md_url( $published ), $stops );
		$this->assertNotContains( $this->md_url( $draft ), $stops );
		$this->assertNotContains( $this->md_url( $private ), $stops );
		$this->assertNotContains( $this->md_url( $protected ), $stops );
	}

	public function test_titles_are_escaped() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Tom & Jerry say "hi" & <wave>',
			)
		);

		// The title as WordPress shows it (it strips tags, curls quotes), as text, not entities.
		$this->assertSame(
			html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ),
			(string) $this->outline()->body->outline[0]['text']
		);
		$this->assertStringContainsString( '&', (string) $this->outline()->body->outline[0]['text'] );
	}

	public function test_a_newly_published_post_appears() {
		self::factory()->post->create();
		$this->stops(); // Builds, and caches, the outline.

		$new = self::factory()->post->create();

		$this->assertContains( $this->md_url( $new ), $this->stops() );
	}

	public function test_a_retitled_page_shows_its_new_title() {
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Old title' ) );
		$this->stops();

		wp_update_post( array( 'ID' => $page, 'post_title' => 'New title' ) );

		$this->assertSame( 'New title', (string) $this->outline()->body->outline[0]['text'] );
	}

	public function test_choosing_a_new_front_page_moves_it_first() {
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'A' ) );
		$home = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Z' ) );
		$this->stops();

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );

		$this->assertSame( 'Z', (string) $this->outline()->body->outline[0]['text'] );
	}
}
