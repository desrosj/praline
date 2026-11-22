<?php
/**
 * The Content-Location header for Markdown served at an address that isn't
 * the .md file.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Markdown Alternate answers ?format=markdown on a post's own address with
 * the post's Markdown. BonBon finds a page in the table of contents by its
 * address, or by the file the server says it sent; this says which file.
 */
class ContentLocation {

	/**
	 * Sends the header before Markdown Alternate answers the request.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'send' ), 0 );
	}

	/**
	 * Sends the header when there's a location.
	 */
	public function send(): void {
		$location = $this->get_location();
		if ( null !== $location && ! headers_sent() ) {
			header( 'Content-Location: ' . esc_url_raw( $location ) );
		}
	}

	/**
	 * The .md file this request will be answered with, or null when it
	 * won't be answered with Markdown or is already the .md file's address.
	 */
	public function get_location(): ?string {
		if ( 'markdown' !== get_query_var( 'format' ) || get_query_var( 'markdown_request' ) ) {
			return null;
		}

		$post = $this->get_requested_post();
		if ( ! $post instanceof WP_Post || ! Servable::is_servable( $post ) ) {
			return null;
		}
		return Servable::markdown_url( $post );
	}

	/**
	 * The post Markdown Alternate will answer with, by its own rules: the
	 * queried post, or the static front page when the root was asked for.
	 */
	private function get_requested_post(): ?WP_Post {
		if ( is_singular() ) {
			$post = get_queried_object();
			return $post instanceof WP_Post ? $post : null;
		}

		if ( 'page' !== get_option( 'show_on_front' ) || ! $this->is_root_request() ) {
			return null;
		}
		$post = get_post( (int) get_option( 'page_on_front' ) );
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Whether the request is for the site's home, which the extra query
	 * variable can make look like the posts index.
	 */
	private function is_root_request(): bool {
		if ( is_front_page() ) {
			return true;
		}
		if ( ! is_home() || is_paged() ) {
			return false;
		}
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$home_path    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return rtrim( $request_path, '/' ) === rtrim( $home_path, '/' );
	}
}
