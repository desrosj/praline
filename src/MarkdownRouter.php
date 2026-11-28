<?php
/**
 * The bundled Markdown Alternate router, with BonBon's rule for which HTML
 * addresses send Accept: text/markdown on to Markdown.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Praline\Vendor\MarkdownAlternate\Router\RewriteHandler;
use WP_Post;

/**
 * Upstream serves .md addresses and ?format=markdown, and this keeps all
 * of that. Only its Accept redirect is replaced, because upstream sends on
 * any singular address: a password-protected post to a .md that answers
 * 403, and an attachment or another post type without Markdown to one
 * that 404s. Here only a post or page with Markdown for anyone to read is
 * sent on, to its .md on this site; everything else answers with its HTML.
 *
 * Upstream also serves category and tag archives as Markdown. They're left
 * out here: the outline, the arrows and search cover posts and pages only.
 */
class MarkdownRouter extends RewriteHandler {

	/**
	 * Registers upstream's hooks, with no taxonomy archives served.
	 */
	public function register(): void {
		parent::register();
		add_filter( 'markdown_alternate_supported_taxonomies', '__return_empty_array' );
	}

	/**
	 * Where the current request should be sent for its Markdown, or null
	 * to answer it as usual.
	 */
	public function get_redirect_url(): ?string {
		if ( get_query_var( 'markdown_request' ) ) {
			return null;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		if ( ! str_contains( $accept, 'text/markdown' ) ) {
			return null;
		}

		if ( ! is_singular() ) {
			return null;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! Servable::is_servable( $post ) ) {
			return null;
		}

		return Servable::markdown_url( $post );
	}

	/**
	 * Sends the request on to its Markdown with a 303, keeping upstream's
	 * cache handling (Markdown Alternate 1.3.0, RewriteHandler.php:597-611).
	 */
	public function handle_accept_negotiation(): void {
		$url = $this->get_redirect_url();
		if ( null === $url ) {
			return;
		}

		// Keep shared and WordPress page caches from storing the redirect.
		if ( apply_filters( 'markdown_alternate_disable_page_cache_on_redirect', true ) ) {
			header( 'Cache-Control: private' );

			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			do_action( 'litespeed_control_set_nocache', 'Content negotiation redirect' );
		}

		header( 'Vary: Accept' );
		wp_safe_redirect( $url, 303 );
		exit;
	}
}
