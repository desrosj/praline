<?php
/**
 * The order of the site's pages, shared by the outline and the arrows.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * The pages BonBon can read, the static front page first, then in the
 * order of the page hierarchy.
 */
class PageOrder {

	/**
	 * The pages, in order.
	 *
	 * @return WP_Post[]
	 */
	public static function pages(): array {
		$pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish',
			)
		);
		$pages = array_values( array_filter( is_array( $pages ) ? $pages : array(), Servable::is_servable( ... ) ) );

		$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		usort(
			$pages,
			static function ( $a, $b ) use ( $front_id ) {
				return (int) ( $b->ID === $front_id ) - (int) ( $a->ID === $front_id );
			}
		);
		return $pages;
	}
}
