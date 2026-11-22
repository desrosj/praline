<?php
/**
 * The addresses BonBon asks a site for.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers /.well-known/toc.opml, /.well-known/bonbon.json and the search
 * bonbon.json names. Matched on the request's path in parse_request, the
 * way Markdown Alternate matches .md addresses, so it works without
 * rewrite rules on servers that don't pass them through.
 */
class Router {

	const SEARCH_PATH = '/.well-known/bonbon-search';

	/**
	 * Answers matching requests before WordPress queries for a page.
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'handle' ), 1 );
	}

	/**
	 * Sends the answer when the request is one of ours.
	 */
	public function handle(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$response    = $this->route( $request_uri );
		if ( null !== $response ) {
			$response->send();
		}
	}

	/**
	 * The answer for a request, or null when WordPress should handle it.
	 *
	 * @param string $request_uri The request's path and query.
	 */
	public function route( string $request_uri ): ?Response {
		$path      = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$home_path = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( '' !== $home_path ) {
			if ( ! str_starts_with( $path, $home_path . '/' ) ) {
				return null;
			}
			$path = substr( $path, strlen( $home_path ) );
		}

		return match ( $path ) {
			'/.well-known/toc.opml'    => new Response( 'text/x-opml', ( new TableOfContents() )->get_opml() ),
			'/.well-known/bonbon.json' => new Response(
				'application/json',
				(string) wp_json_encode( array( 'urlSearch' => home_url( self::SEARCH_PATH ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
			),
			self::SEARCH_PATH          => $this->search( $request_uri ),
			default                    => null,
		};
	}

	/**
	 * The search results for the request's ?q=.
	 *
	 * @param string $request_uri The request's path and query.
	 */
	private function search( string $request_uri ): Response {
		wp_parse_str( (string) wp_parse_url( $request_uri, PHP_URL_QUERY ), $query );
		$search = isset( $query['q'] ) && is_string( $query['q'] ) ? $query['q'] : '';
		return new Response( 'text/markdown', ( new Search() )->get_markdown( $search ) );
	}
}
