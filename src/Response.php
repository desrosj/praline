<?php
/**
 * An answer to a request: status, headers and body.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built by the router, sent by send(), so the deciding can be tested
 * without sending headers or exiting.
 */
final readonly class Response {

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	public int $status;

	/**
	 * Headers, name => value.
	 *
	 * @var array<string, string>
	 */
	public array $headers;

	/**
	 * A 200 with a body of the given type.
	 *
	 * @param string $content_type The Content-Type, without the charset.
	 * @param string $body         The body.
	 */
	public function __construct( string $content_type, public string $body ) {
		$this->status  = 200;
		$this->headers = array(
			'Content-Type'           => $content_type . '; charset=UTF-8',
			'X-Content-Type-Options' => 'nosniff',
		);
	}

	/**
	 * Sends the answer and ends the request.
	 */
	public function send(): never {
		status_header( $this->status );
		foreach ( $this->headers as $name => $value ) {
			header( $name . ': ' . $value );
		}
		if ( 'HEAD' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET' ) ) {
			echo $this->body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- OPML, JSON or Markdown, escaped where it's built.
		}
		exit;
	}
}
