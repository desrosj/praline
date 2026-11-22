<?php
/**
 * The site's table of contents: the OPML outline BonBon reads at
 * /.well-known/toc.opml.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DOMDocument;
use DOMElement;

/**
 * Builds the outline. BonBon walks every line with an address, top to
 * bottom, children after their parent, to find a page's neighbors for its
 * arrows, and reads the top-level lines with children as the years of its
 * calendar. So: the posts first, as years, months and days, newest first,
 * the shape of Scripting News's outline, then the pages, flat. A day's line
 * is its newest post; the day's other posts are under it. Every page and
 * post names its own arrows too (see FrontMatter), so they run one way
 * through the whole site.
 */
class TableOfContents {

	const CACHE_KEY = 'praline_toc';

	/**
	 * The outline as OPML text, from the cache when the site hasn't changed.
	 */
	public function get_opml(): string {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['version'], $cached['opml'] ) && SiteVersion::get() === $cached['version'] ) {
			return $cached['opml'];
		}

		$opml = $this->build();
		set_transient(
			self::CACHE_KEY,
			array(
				'version' => SiteVersion::get(),
				'opml'    => $opml,
			),
			WEEK_IN_SECONDS
		);
		return $opml;
	}

	/**
	 * Builds the outline.
	 */
	private function build(): string {
		$doc               = new DOMDocument( '1.0', 'UTF-8' );
		$doc->formatOutput = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument's own property.

		$opml = $doc->createElement( 'opml' );
		$opml->setAttribute( 'version', '2.0' );
		$doc->appendChild( $opml );

		$head = $doc->createElement( 'head' );
		$head->appendChild( $this->text_element( $doc, 'title', get_bloginfo( 'name' ) ) );
		$opml->appendChild( $head );

		$body = $doc->createElement( 'body' );
		$opml->appendChild( $body );

		$this->add_calendar( $doc, $body );

		foreach ( PageOrder::pages() as $page ) {
			$body->appendChild( $this->line( $doc, get_the_title( $page ), Servable::markdown_url( $page ) ) );
		}

		return $doc->saveXML();
	}

	/**
	 * Adds the posts as years, months and days.
	 *
	 * @param DOMDocument $doc  The document.
	 * @param DOMElement  $body The outline's body.
	 */
	private function add_calendar( DOMDocument $doc, DOMElement $body ): void {
		$posts = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$years  = array();
		$months = array();
		$days   = array();
		foreach ( $posts as $post ) {
			if ( ! Servable::is_servable( $post ) ) {
				continue;
			}
			$time  = strtotime( $post->post_date );
			$year  = gmdate( 'Y', $time );
			$month = gmdate( 'Y/m', $time );
			$day   = gmdate( 'Y/m/d', $time );

			if ( ! isset( $years[ $year ] ) ) {
				$years[ $year ] = $doc->createElement( 'outline' );
				$years[ $year ]->setAttribute( 'text', $year );
				$years[ $year ]->setAttribute( 'type', 'calendarYear' );
				$years[ $year ]->setAttribute( 'name', $year );
				$body->appendChild( $years[ $year ] );
			}

			if ( ! isset( $months[ $month ] ) ) {
				$months[ $month ] = $doc->createElement( 'outline' );
				$months[ $month ]->setAttribute( 'text', date_i18n( 'F Y', $time ) );
				$months[ $month ]->setAttribute( 'type', 'calendarMonth' );
				$months[ $month ]->setAttribute( 'name', gmdate( 'm', $time ) );
				$years[ $year ]->appendChild( $months[ $month ] );
			}

			if ( ! isset( $days[ $day ] ) ) {
				// The day's newest post, since posts come newest first.
				$days[ $day ] = $this->line( $doc, date_i18n( 'F j', $time ), Servable::markdown_url( $post ) );
				$days[ $day ]->setAttribute( 'type', 'calendarDay' );
				$days[ $day ]->setAttribute( 'name', gmdate( 'd', $time ) );
				$months[ $month ]->appendChild( $days[ $day ] );
			} else {
				$days[ $day ]->appendChild( $this->line( $doc, get_the_title( $post ), Servable::markdown_url( $post ) ) );
			}
		}
	}

	/**
	 * An outline line with an address.
	 *
	 * @param DOMDocument $doc  The document.
	 * @param string      $text The line's text.
	 * @param string      $url  The line's address.
	 */
	private function line( DOMDocument $doc, string $text, string $url ): DOMElement {
		$line = $doc->createElement( 'outline' );
		$line->setAttribute( 'text', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
		$line->setAttribute( 'url', $url );
		return $line;
	}

	/**
	 * An element holding text.
	 *
	 * @param DOMDocument $doc  The document.
	 * @param string      $name The element's name.
	 * @param string      $text The text.
	 */
	private function text_element( DOMDocument $doc, string $name, string $text ): DOMElement {
		$element = $doc->createElement( $name );
		$element->appendChild( $doc->createTextNode( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		return $element;
	}
}
