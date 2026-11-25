<?php
/**
 * Keeps Markdown rendered for one visitor out of the cache every visitor shares.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Markdown Alternate caches each post's Markdown, once for everyone, but
 * renders it through the_content as whoever is visiting. Content that is
 * different for different visitors (a Login/out block, a members-only
 * section, a WooCommerce cart or account page) would then be shown to
 * everyone as the first visitor saw it.
 *
 * So only a visitor with nothing of their own uses the cache. Anyone logged
 * in, or carrying a session cookie, gets a fresh render each time, which
 * Markdown Alternate also marks so HTTP and page caches don't store it.
 */
class CachePolicy {

	/**
	 * Start of the names of cookies that make a visitor's pages their own:
	 * a password-protected post's password, WooCommerce's guest session and
	 * cart, and Easy Digital Downloads' cart.
	 *
	 * @var string[]
	 */
	const SESSION_COOKIE_PREFIXES = array(
		'wp-postpass_',
		'wp_woocommerce_session_',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'edd_items_in_cart',
	);

	/**
	 * Hooks into Markdown Alternate.
	 */
	public function register(): void {
		add_filter( 'markdown_alternate_cache_enabled', array( $this, 'cache_enabled' ) );
	}

	/**
	 * Whether this request may read and write the shared cache.
	 *
	 * @param mixed $enabled Whether caching is on so far.
	 */
	public function cache_enabled( mixed $enabled ): bool {
		return (bool) $enabled && ! self::visitor_has_state();
	}

	/**
	 * Whether the visitor may see pages that are theirs alone: logged in,
	 * or carrying a session cookie.
	 */
	public static function visitor_has_state(): bool {
		$has_state = is_user_logged_in();

		if ( ! $has_state ) {
			foreach ( array_keys( $_COOKIE ) as $name ) {
				foreach ( self::SESSION_COOKIE_PREFIXES as $prefix ) {
					if ( str_starts_with( (string) $name, $prefix ) ) {
						$has_state = true;
						break 2;
					}
				}
			}
		}

		/**
		 * Filters whether the visitor may see Markdown that is theirs alone,
		 * so it's rendered fresh for them and kept out of the shared cache.
		 * For a plugin with a session cookie of its own, or content that
		 * changes by something other than a cookie.
		 *
		 * @param bool $has_state Whether the visitor is logged in or has a session cookie.
		 */
		return (bool) apply_filters( 'praline_visitor_has_state', $has_state );
	}
}
