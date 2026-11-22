<?php
/**
 * Which way through time a post's arrow points.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Older posts are to the left, newer to the right.
 */
enum Direction {

	case Older;
	case Newer;

	/**
	 * The date_query key that finds posts this way.
	 */
	public function date_query_key(): string {
		return match ( $this ) {
			self::Older => 'before',
			self::Newer => 'after',
		};
	}

	/**
	 * The order that puts the nearest post this way first.
	 */
	public function order(): string {
		return match ( $this ) {
			self::Older => 'DESC',
			self::Newer => 'ASC',
		};
	}
}
