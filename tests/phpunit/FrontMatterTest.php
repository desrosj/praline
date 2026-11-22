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
}
