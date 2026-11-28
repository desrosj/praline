<?php
/**
 * The front matter fields BonBon reads, added to Markdown Alternate's.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Adds BonBon's fields to the typed front matter Markdown Alternate renders.
 */
class FrontMatter {

	/**
	 * The shape of the Markdown this plugin renders. Change it whenever the
	 * output changes for every post, so Markdown cached before is rebuilt.
	 * 2: no category or tag links. 3: arrows on pages, and from the newest
	 * post on to the pages. 4: nothing cached for a logged-in visitor or one
	 * with a session cookie (see Markdown Alternate's CachePolicy), so older caches, which may
	 * hold one, are thrown away. 5: every value on one line.
	 */
	const FORMAT = '5';

	/**
	 * Hooks into Markdown Alternate.
	 */
	public function register(): void {
		add_filter( 'markdown_alternate_frontmatter_content_lines', array( $this, 'add_fields' ), 10, 2 );
		// Last, so it sees every field, whoever added it.
		add_filter( 'markdown_alternate_frontmatter_content_lines', array( $this, 'keep_on_one_line' ), PHP_INT_MAX );
		add_filter( 'markdown_alternate_cache_version', array( $this, 'cache_version' ), 10, 2 );
		// Markdown Alternate links terms to /category/x.md, which 404s.
		add_filter( 'markdown_alternate_frontmatter_taxonomies', '__return_empty_array' );
	}

	/**
	 * Adds the output format and the site version to Markdown Alternate's
	 * cache token, so a post's cached Markdown is rebuilt when this plugin's
	 * output changes or its neighbors do.
	 *
	 * @param mixed $version The token so far.
	 */
	public function cache_version( mixed $version ): string {
		return (string) $version . '|praline:' . self::FORMAT . ':' . SiteVersion::get();
	}

	/**
	 * Adds BonBon's fields.
	 *
	 * @param mixed $lines Typed fields keyed by their YAML name.
	 * @param mixed $post  The post being rendered.
	 * @return mixed The fields, or what was passed when it isn't fields and a post.
	 */
	public function add_fields( mixed $lines, mixed $post ): mixed {
		if ( ! is_array( $lines ) || ! $post instanceof WP_Post ) {
			return $lines;
		}

		// BonBon wants the RSS 2.0 date format (RFC 822), in GMT.
		$lines['pubDate'] = array(
			'type'    => 'string',
			'content' => get_post_time( 'D, d M Y H:i:s \G\M\T', true, $post ),
		);

		// Only an excerpt someone wrote; a generated one is the first words of the text.
		if ( '' !== trim( $post->post_excerpt ) ) {
			$lines['description'] = array(
				'type'    => 'string',
				'content' => wp_strip_all_tags( $post->post_excerpt, true ),
			);
		}

		// Where BonBon shows the title, it links here: the page on the HTML web.
		$lines['link'] = array(
			'type'    => 'url',
			'content' => get_permalink( $post ),
		);

		$image = $this->get_right_margin_image( $post );
		if ( '' !== $image ) {
			$lines['rightMarginImage'] = array(
				'type'    => 'url',
				'content' => $image,
			);
		}

		foreach ( $this->get_arrows( $post ) as $key => $neighbor ) {
			if ( $neighbor instanceof WP_Post ) {
				$lines[ $key ] = array(
					'type'    => 'url',
					'content' => Servable::markdown_url( $neighbor ),
				);
			}
		}

		return $lines;
	}

	/**
	 * Puts every value on its own one line of the front matter. Markdown
	 * Alternate writes a value in double quotes, decoding its HTML entities
	 * and escaping its backslashes and quotes, but leaves line breaks as
	 * they are, so a value with one would carry on over the lines after it.
	 *
	 * @param mixed $lines Typed fields keyed by their YAML name.
	 * @return mixed The fields, or what was passed when it isn't fields.
	 */
	public function keep_on_one_line( mixed $lines ): mixed {
		if ( ! is_array( $lines ) ) {
			return $lines;
		}

		foreach ( $lines as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( isset( $field['content'] ) && is_string( $field['content'] ) ) {
				$lines[ $key ]['content'] = self::one_line( $field['content'], $field['type'] ?? '' );
			}
			if ( isset( $field['items'] ) && is_array( $field['items'] ) ) {
				foreach ( $field['items'] as $i => $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					foreach ( $item as $item_key => $value ) {
						if ( is_array( $value ) && isset( $value['content'] ) && is_string( $value['content'] ) ) {
							$lines[ $key ]['items'][ $i ][ $item_key ]['content'] = self::one_line( $value['content'], $value['type'] ?? '' );
						}
					}
				}
			}
		}

		return $lines;
	}

	/**
	 * A value with its line breaks, other control characters, and Unicode's
	 * next line, line separator and paragraph separator each run made one
	 * space.
	 *
	 * Markdown Alternate decodes a string or url value's HTML entities as it
	 * writes it, which would turn &#10; into a line break after this. So the
	 * entities are decoded here first, the same way, and every & left is
	 * encoded again, for its decoding to give back exactly this.
	 *
	 * @param string $value The value.
	 * @param mixed  $type  Its type: 'string', 'url', 'date', 'number' or 'boolean'.
	 */
	public static function one_line( string $value, mixed $type ): string {
		// Written as it is, unquoted and undecoded.
		$as_is = in_array( $type, array( 'date', 'number', 'boolean' ), true );
		if ( ! $as_is ) {
			$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		$flat = preg_replace( '/[\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]+/u', ' ', $value );
		if ( null === $flat ) {
			// Not valid UTF-8: the control characters, byte by byte.
			$flat = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );
		}
		$flat = trim( (string) $flat );

		return $as_is ? $flat : str_replace( '&', '&amp;', $flat );
	}

	/**
	 * Where the post's arrows go. The arrows run one way through the whole
	 * site: the posts through time, older to the left, newer to the right,
	 * then on from the newest post to the pages, in the outline's order.
	 *
	 * @param WP_Post $post The post or page.
	 * @return array{urlPrev: WP_Post|null, urlNext: WP_Post|null}
	 */
	private function get_arrows( WP_Post $post ): array {
		if ( 'post' === $post->post_type ) {
			$newer = $this->get_neighbor( $post, Direction::Newer );
			return array(
				'urlPrev' => $this->get_neighbor( $post, Direction::Older ),
				'urlNext' => $newer ?? ( PageOrder::pages()[0] ?? null ),
			);
		}

		if ( 'page' === $post->post_type ) {
			$pages = PageOrder::pages();
			$index = array_search( $post->ID, wp_list_pluck( $pages, 'ID' ), true );
			if ( false !== $index ) {
				return array(
					'urlPrev' => 0 === $index ? $this->get_newest_post() : $pages[ $index - 1 ],
					'urlNext' => $pages[ $index + 1 ] ?? null,
				);
			}
		}

		return array(
			'urlPrev' => null,
			'urlNext' => null,
		);
	}

	/**
	 * The newest post whose Markdown is served.
	 *
	 * @return WP_Post|null
	 */
	private function get_newest_post(): ?WP_Post {
		return $this->first_servable( array( 'order' => 'DESC' ) );
	}

	/**
	 * The nearest post before or after this one whose Markdown is served.
	 *
	 * @param WP_Post   $post      The post.
	 * @param Direction $direction Older or newer.
	 * @return WP_Post|null
	 */
	private function get_neighbor( WP_Post $post, Direction $direction ): ?WP_Post {
		return $this->first_servable(
			array(
				'order'      => $direction->order(),
				// Strictly before or after, so never the post itself.
				'date_query' => array(
					array(
						$direction->date_query_key() => $post->post_date,
						'inclusive'                  => false,
					),
				),
			)
		);
	}

	/**
	 * The first post, by date, whose Markdown is served.
	 *
	 * @param array $args The query's order, and anything else that narrows it.
	 * @return WP_Post|null
	 */
	private function first_servable( array $args ): ?WP_Post {
		$posts = get_posts(
			array_merge(
				array(
					'post_type'        => 'post',
					'post_status'      => 'publish',
					'has_password'     => false,
					'numberposts'      => 10, // A few, in case the nearest is skipped as noindexed.
					'orderby'          => 'date',
					'suppress_filters' => false,
				),
				$args
			)
		);
		foreach ( $posts as $candidate ) {
			if ( Servable::is_servable( $candidate ) ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * The featured image's address, when it's a format BonBon draws.
	 *
	 * @param WP_Post $post The post.
	 * @return string The address, or '' when there's none BonBon can show.
	 */
	private function get_right_margin_image( WP_Post $post ): string {
		$attachment_id = (int) get_post_thumbnail_id( $post );
		if ( ! $attachment_id ) {
			return '';
		}

		$drawable = array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' );
		if ( ! in_array( get_post_mime_type( $attachment_id ), $drawable, true ) ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : '';
	}
}
