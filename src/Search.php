<?php
/**
 * Site search for BonBon. A site's bonbon.json names a urlSearch; BonBon
 * adds ?q= and the word and shows the Markdown page that comes back.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Praline\Vendor\MarkdownAlternate\PostTypeSupport;
use WP_Query;

/**
 * Searches the site and answers with a Markdown page of results.
 */
class Search {

	const MAX_RESULTS = 20;

	/**
	 * The results page for a search.
	 *
	 * @param string $search What the reader typed.
	 */
	public function get_markdown( string $search ): string {
		$term  = $this->clean( $search );
		$title = sprintf(
			/* translators: %s: what the reader searched for. */
			__( 'Search: %s', 'praline' ),
			$term
		);

		$lines   = array( '---', 'title: "' . $title . '"', '---', '', '# ' . $title, '' );
		$results = '' === $term ? array() : $this->find( $term );

		if ( ! $results ) {
			$lines[] = __( 'Nothing was found.', 'praline' );
		}
		foreach ( $results as $i => $post ) {
			$lines[] = sprintf(
				'%d. [%s](%s), %s',
				$i + 1,
				$this->link_text( get_the_title( $post ) ),
				Servable::markdown_url( $post ),
				get_the_date( '', $post )
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * The published, servable posts and pages that match.
	 *
	 * @param string $term The cleaned search.
	 * @return \WP_Post[]
	 */
	private function find( string $term ): array {
		$query = new WP_Query(
			array(
				's'                   => $term,
				'post_type'           => PostTypeSupport::get_supported_types(),
				'post_status'         => 'publish',
				'has_password'        => false,
				'posts_per_page'      => self::MAX_RESULTS,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		return array_values( array_filter( $query->posts, Servable::is_servable( ... ) ) );
	}

	/**
	 * The search as words: letters, numbers, spaces and a little
	 * punctuation, so it can't break the front matter or make Markdown.
	 *
	 * @param string $search What the reader typed.
	 */
	private function clean( string $search ): string {
		$search = preg_replace( '/[^\p{L}\p{N}\s\'.,&:-]+/u', ' ', $search );
		$search = preg_replace( '/\s+/u', ' ', (string) $search );
		return trim( mb_substr( (string) $search, 0, 100 ) );
	}

	/**
	 * A post title as plain Markdown link text: its HTML tags removed,
	 * its entities decoded, then backslashes before the characters that
	 * could end the link early (\, [, ]), start HTML (<, >) or start an
	 * entity (&), so a reader shows them as written and never as markup.
	 *
	 * @param string $title The title, as get_the_title() gives it.
	 */
	private function link_text( string $title ): string {
		$text = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
		return addcslashes( $text, '\\[]<>&' );
	}
}
