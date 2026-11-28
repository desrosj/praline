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
 *
 * Answered later, on template_redirect at SERVE_PRIORITY, so plugins that
 * restrict content there at the default 10 (a login requirement, a
 * maintenance mode) run first, as they do for the Markdown.
 */
class Router {

	const SEARCH_PATH = '/.well-known/bonbon-search';

	/**
	 * The template_redirect priority the answer is sent at: after the
	 * default 10, and before Markdown Alternate's handlers at 15, which
	 * have nothing to answer here.
	 */
	const SERVE_PRIORITY = 14;

	/**
	 * The matched request's path and query, or null when it isn't ours.
	 *
	 * @var string|null
	 */
	private ?string $matched = null;

	/**
	 * Hooks in.
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'match' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle' ), self::SERVE_PRIORITY );
	}

	/**
	 * Notes whether the request is one of ours. For one that is, WordPress
	 * has no page to find: its main query is skipped, and it isn't treated
	 * as a 404 (which would mark the answer uncacheable) or redirected to
	 * a page it guesses.
	 */
	public function match(): void {
		$request_uri   = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$this->matched = null !== $this->route_path( $request_uri ) ? $request_uri : null;
		if ( null === $this->matched ) {
			return;
		}

		add_filter( 'posts_pre_query', array( $this, 'skip_main_query' ), 10, 2 );
		add_filter( 'pre_handle_404', '__return_true' );
		add_filter( 'redirect_canonical', '__return_false' );
	}

	/**
	 * No posts for the main query, without asking the database.
	 *
	 * @param mixed  $posts What to answer with so far, or null to query.
	 * @param object $query The query.
	 * @return mixed
	 */
	public function skip_main_query( mixed $posts, object $query ): mixed {
		return $query instanceof \WP_Query && $query->is_main_query() ? array() : $posts;
	}

	/**
	 * Sends the answer when the request is one of ours.
	 */
	public function handle(): void {
		if ( null === $this->matched ) {
			return;
		}
		$response = $this->route( $this->matched );
		if ( null !== $response ) {
			$response->send();
		}
	}

	/**
	 * Which of ours a request is for: its path from the site's home, or
	 * null when it isn't one of ours.
	 *
	 * @param string $request_uri The request's path and query.
	 */
	private function route_path( string $request_uri ): ?string {
		$path      = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$home_path = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( '' !== $home_path ) {
			if ( ! str_starts_with( $path, $home_path . '/' ) ) {
				return null;
			}
			$path = substr( $path, strlen( $home_path ) );
		}

		return in_array( $path, array( '/.well-known/toc.opml', '/.well-known/bonbon.json', self::SEARCH_PATH ), true ) ? $path : null;
	}

	/**
	 * The answer for a request, or null when WordPress should handle it.
	 *
	 * @param string $request_uri The request's path and query.
	 */
	public function route( string $request_uri ): ?Response {
		return match ( $this->route_path( $request_uri ) ) {
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
