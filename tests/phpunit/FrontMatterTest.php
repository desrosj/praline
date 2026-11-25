<?php
/**
 * The front matter BonBon reads, added to what Markdown Alternate renders.
 *
 * @package Praline
 */

use Praline\Vendor\MarkdownAlternate\Output\ContentRenderer;

class FrontMatterTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Markdown Alternate needs pretty permalinks.
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	/**
	 * The front matter block of a post, as Markdown Alternate renders it.
	 */
	private function front_matter( WP_Post $post ): string {
		$markdown = ( new ContentRenderer() )->render( $post );
		$this->assertSame( 0, strpos( $markdown, "---\n" ), 'Rendered Markdown starts with front matter.' );
		return substr( $markdown, 0, strpos( $markdown, "\n---", 4 ) + 4 );
	}

	public function test_no_category_or_tag_links() {
		// Markdown Alternate links them to /category/x.md, which 404s.
		$category = self::factory()->category->create( array( 'name' => 'Baseball' ) );
		$post     = self::factory()->post->create_and_get( array( 'post_category' => array( $category ) ) );
		wp_set_post_tags( $post->ID, array( 'ballparks' ) );

		$markdown = ( new ContentRenderer() )->render( $post );

		$this->assertStringNotContainsString( 'categories:', $markdown );
		$this->assertStringNotContainsString( 'tags:', $markdown );
		$this->assertStringNotContainsString( '/category/', $markdown );
	}

	public function test_markdown_cached_before_this_version_is_rebuilt() {
		$post = self::factory()->post->create_and_get();
		// What an earlier version of this plugin cached, under its token.
		set_transient(
			'md_alt_cache_' . $post->ID,
			array(
				'markdown' => 'stale',
				'modified' => $post->post_modified,
				'version'  => '|praline:' . Praline\SiteVersion::get(),
			)
		);

		$this->assertNotSame( 'stale', ( new ContentRenderer() )->render( $post ) );
	}

	public function test_pubdate_is_the_gmt_publish_time_in_rss_format() {
		$post = self::factory()->post->create_and_get(
			array( 'post_date_gmt' => '2026-10-23 15:19:45' )
		);

		$this->assertStringContainsString(
			'pubDate: "Fri, 23 Oct 2026 15:19:45 GMT"',
			$this->front_matter( $post )
		);
	}

	public function test_description_is_the_handwritten_excerpt() {
		$post = self::factory()->post->create_and_get(
			array( 'post_excerpt' => 'A short "summary" of the post.' )
		);

		$this->assertStringContainsString(
			'description: "A short \"summary\" of the post."',
			$this->front_matter( $post )
		);
	}

	public function test_no_description_without_a_handwritten_excerpt() {
		$post = self::factory()->post->create_and_get( array( 'post_excerpt' => '' ) );

		$this->assertStringNotContainsString( 'description:', $this->front_matter( $post ) );
	}

	public function test_link_is_the_html_permalink() {
		$post = self::factory()->post->create_and_get();

		$this->assertStringContainsString(
			'link: "' . get_permalink( $post ) . '"',
			$this->front_matter( $post )
		);
	}

	public function test_right_margin_image_is_the_featured_image() {
		$post          = self::factory()->post->create_and_get();
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $post->ID );
		set_post_thumbnail( $post, $attachment_id );

		$this->assertStringContainsString(
			'rightMarginImage: "' . wp_get_attachment_url( $attachment_id ) . '"',
			$this->front_matter( $post )
		);
	}

	public function test_no_right_margin_image_when_the_featured_image_is_svg() {
		// BonBon draws PNG, JPEG, GIF and WebP only.
		$post          = self::factory()->post->create_and_get();
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'logo.svg',
				'post_parent'    => $post->ID,
				'post_mime_type' => 'image/svg+xml',
			)
		);
		update_post_meta( $post->ID, '_thumbnail_id', $attachment_id );

		$this->assertStringNotContainsString( 'rightMarginImage:', $this->front_matter( $post ) );
	}

	public function test_url_prev_is_the_older_posts_markdown_url() {
		$older = self::factory()->post->create( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$post  = self::factory()->post->create_and_get( array( 'post_date' => '2026-01-02 10:00:00' ) );

		$this->assertStringContainsString(
			'urlPrev: "' . home_url( '/2026/01/01/' . get_post_field( 'post_name', $older ) . '.md' ) . '"',
			$this->front_matter( $post )
		);
	}

	public function test_url_next_is_the_newer_posts_markdown_url() {
		$post  = self::factory()->post->create_and_get( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$newer = self::factory()->post->create( array( 'post_date' => '2026-01-02 10:00:00' ) );

		$this->assertStringContainsString(
			'urlNext: "' . home_url( '/2026/01/02/' . get_post_field( 'post_name', $newer ) . '.md' ) . '"',
			$this->front_matter( $post )
		);
	}

	public function test_the_only_post_has_no_neighbors_without_pages() {
		$post = self::factory()->post->create_and_get();

		$front_matter = $this->front_matter( $post );
		$this->assertStringNotContainsString( 'urlPrev:', $front_matter );
		$this->assertStringNotContainsString( 'urlNext:', $front_matter );
	}

	public function test_drafts_are_not_neighbors() {
		$post = self::factory()->post->create_and_get( array( 'post_date' => '2026-01-01 10:00:00' ) );
		self::factory()->post->create(
			array(
				'post_date'   => '2026-01-02 10:00:00',
				'post_status' => 'draft',
			)
		);

		$this->assertStringNotContainsString( 'urlNext:', $this->front_matter( $post ) );
	}

	/**
	 * A static front page and two more pages, in the outline's order.
	 *
	 * @return WP_Post[] Home, About, Contact.
	 */
	private function pages(): array {
		$home = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_title' => 'Home', 'menu_order' => 3 ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home->ID );
		return array(
			$home,
			self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_title' => 'About', 'menu_order' => 1 ) ),
			self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_title' => 'Contact', 'menu_order' => 2 ) ),
		);
	}

	public function test_the_newest_posts_right_arrow_goes_to_the_front_page() {
		// The posts run into the pages: oldest ... newest, Home, About ...
		$this->pages();
		$post = self::factory()->post->create_and_get( array( 'post_date' => '2026-01-02 10:00:00' ) );

		$this->assertStringContainsString( 'urlNext: "' . home_url( '/index.md' ) . '"', $this->front_matter( $post ) );
	}

	public function test_the_front_pages_left_arrow_goes_back_to_the_newest_post() {
		list( $home ) = $this->pages();
		self::factory()->post->create( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$newest = self::factory()->post->create( array( 'post_date' => '2026-01-02 10:00:00' ) );

		$this->assertStringContainsString(
			'urlPrev: "' . home_url( '/2026/01/02/' . get_post_field( 'post_name', $newest ) . '.md' ) . '"',
			$this->front_matter( $home )
		);
	}

	public function test_a_pages_arrows_go_to_the_pages_beside_it() {
		list( $home, $about, $contact ) = $this->pages();

		$front_matter = $this->front_matter( $about );

		$this->assertStringContainsString( 'urlPrev: "' . home_url( '/index.md' ) . '"', $front_matter );
		$this->assertStringContainsString( 'urlNext: "' . home_url( '/' . $contact->post_name . '.md' ) . '"', $front_matter );
	}

	public function test_the_last_page_has_no_right_arrow() {
		list( , , $contact ) = $this->pages();

		$this->assertStringNotContainsString( 'urlNext:', $this->front_matter( $contact ) );
	}

	public function test_the_front_page_has_no_left_arrow_without_posts() {
		list( $home ) = $this->pages();

		$this->assertStringNotContainsString( 'urlPrev:', $this->front_matter( $home ) );
	}

	public function test_publishing_a_post_refreshes_the_front_pages_cached_markdown() {
		list( $home ) = $this->pages();
		$this->front_matter( $home ); // Markdown Alternate caches this rendering.

		self::factory()->post->create();

		$this->assertStringContainsString( 'urlPrev:', $this->front_matter( $home ) );
	}

	public function test_publishing_a_newer_post_refreshes_the_older_posts_cached_markdown() {
		$post = self::factory()->post->create_and_get( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$this->front_matter( $post ); // Markdown Alternate caches this rendering.

		self::factory()->post->create( array( 'post_date' => '2026-01-02 10:00:00' ) );

		$this->assertStringContainsString( 'urlNext:', $this->front_matter( $post ) );
	}

	public function test_password_protected_posts_are_not_neighbors() {
		// Markdown Alternate answers their Markdown with 403.
		$post = self::factory()->post->create_and_get( array( 'post_date' => '2026-01-01 10:00:00' ) );
		self::factory()->post->create(
			array(
				'post_date'     => '2026-01-02 10:00:00',
				'post_password' => 'secret',
			)
		);

		$this->assertStringNotContainsString( 'urlNext:', $this->front_matter( $post ) );
	}

	/**
	 * The front matter's lines between its delimiters, with the post's excerpt.
	 *
	 * @param string $excerpt The excerpt.
	 * @return string[]
	 */
	private function lines_with_excerpt( string $excerpt ): array {
		// Saved as given, as for a user with unfiltered_html: kses changes some
		// entities on save, and how differs between WordPress versions.
		kses_remove_filters();
		// wp_insert_post() unslashes what it's given.
		$post = self::factory()->post->create_and_get( array( 'post_excerpt' => wp_slash( $excerpt ) ) );
		return $this->field_lines( $post );
	}

	/**
	 * The front matter's lines between its delimiters.
	 *
	 * @return string[]
	 */
	private function field_lines( WP_Post $post ): array {
		$markdown = ( new ContentRenderer() )->render( $post );
		$lines    = explode( "\n", $markdown );
		$this->assertSame( '---', $lines[0] );
		$end = array_search( '---', array_slice( $lines, 1 ), true );
		$this->assertIsInt( $end, 'The front matter ends.' );
		return array_slice( $lines, 1, $end );
	}

	/**
	 * Asserts every front matter line is one field, in name: value form.
	 *
	 * @param string[] $lines The lines.
	 */
	private function assert_one_field_per_line( array $lines ): void {
		foreach ( $lines as $line ) {
			$this->assertMatchesRegularExpression( '/^(title|date|author|featured_image|pubDate|description|link|rightMarginImage|urlPrev|urlNext|extra): \S/', $line );
		}
	}

	public function test_a_multi_line_excerpt_is_one_description_line() {
		$lines = $this->lines_with_excerpt( "First line.\n---\nextra: value" );

		$this->assert_one_field_per_line( $lines );
		$this->assertContains( 'description: "First line. --- extra: value"', $lines );
	}

	public function test_windows_line_breaks_and_tabs_are_one_space() {
		$lines = $this->lines_with_excerpt( "First line.\r\n\r\n\tSecond line." );

		$this->assertContains( 'description: "First line. Second line."', $lines );
	}

	public function test_encoded_line_breaks_are_one_space() {
		$lines = $this->lines_with_excerpt( 'One&#10;two&#x0A;three&NewLine;four&#9;five' );

		$this->assert_one_field_per_line( $lines );
		$this->assertContains( 'description: "One two three four five"', $lines );
	}

	public function test_unicode_line_and_paragraph_separators_are_one_space() {
		$lines = $this->lines_with_excerpt( "One\u{2028}two\u{2029}three\u{0085}four" );

		$this->assertContains( 'description: "One two three four"', $lines );
	}

	public function test_entities_and_escapes_are_kept() {
		$lines = $this->lines_with_excerpt( 'Salt &amp; pepper, &amp;#10; as text, a \\ and a "quote".' );

		$this->assertContains( 'description: "Salt & pepper, &#10; as text, a \\\\ and a \\"quote\\"."', $lines );
	}

	public function test_every_field_is_kept_on_one_line() {
		$post = self::factory()->post->create_and_get();
		add_filter(
			'markdown_alternate_frontmatter_content_lines',
			static function ( $lines ) {
				$lines['title']['content'] = "A\ntitle";
				$lines['extra']            = array(
					'type'    => 'url',
					'content' => "https://example.org/\n---",
				);
				$lines['items']            = array(
					'type'  => 'array',
					'items' => array(
						array(
							'name' => array(
								'type'    => 'string',
								'content' => "A\nname",
							),
						),
					),
				);
				return $lines;
			},
			20
		);

		$lines = $this->field_lines( $post );

		$this->assertContains( 'title: "A title"', $lines );
		$this->assertContains( 'extra: "https://example.org/ ---"', $lines );
		$this->assertContains( '  - name: "A name"', $lines );
	}
}
