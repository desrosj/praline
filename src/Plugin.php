<?php
/**
 * Starts the bundled parts of Markdown Alternate and this plugin's own; or,
 * when the standalone Markdown Alternate is active, only this plugin's own,
 * on top of it.
 *
 * @package Praline
 */

namespace Praline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Praline\Vendor\Composer\InstalledVersions;
use Praline\Vendor\MarkdownAlternate\Discovery\AlternateLinkHandler;

/**
 * This plugin ships the parts of Markdown Alternate it needs, renamed into
 * its own namespace, so the classes never clash. The filter names, query
 * variables and cache keys aren't renamed, though, and both copies would
 * answer the same .md addresses. So when the standalone plugin is active,
 * the bundled copy stays off and the standalone one serves the Markdown,
 * with this plugin's front matter, outline, search and Content-Location
 * added through the same filters. That needs Markdown Alternate's filters
 * as they are in MINIMUM_STANDALONE_VERSION; with an older standalone
 * plugin, this one starts nothing and tells administrators why.
 */
class Plugin {

	/**
	 * The oldest standalone Markdown Alternate whose filters this plugin uses.
	 */
	const MINIMUM_STANDALONE_VERSION = '1.2.0';

	/**
	 * The started parts.
	 *
	 * @var object[]
	 */
	private array $components = array();

	/**
	 * Sets up.
	 *
	 * @param string|null $standalone_version The standalone Markdown Alternate's version, '' when
	 *                                        it is active but doesn't say, or null when it isn't active.
	 */
	public function __construct( private readonly ?string $standalone_version ) {
	}

	/**
	 * Starts the parts, or shows the notice.
	 */
	public function boot(): void {
		add_filter( 'debug_information', array( $this, 'site_health_info' ) );

		if ( $this->standalone_is_too_old() ) {
			add_action( 'admin_notices', array( $this, 'standalone_too_old_notice' ) );
			return;
		}

		$this->components = array(
			new SiteVersion(),
			new NoindexPolicy(),
			new FrontMatter(),
			new Router(),
			new ContentLocation(),
		);
		if ( null === $this->standalone_version ) {
			$this->components[] = new MarkdownRouter();
			$this->components[] = new AlternateLinkHandler();
		}

		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * The started parts.
	 *
	 * @return object[]
	 */
	public function get_components(): array {
		return $this->components;
	}

	/**
	 * Whether the standalone Markdown Alternate is active but older than the
	 * filters this plugin uses, or doesn't say which version it is.
	 */
	private function standalone_is_too_old(): bool {
		return null !== $this->standalone_version
			&& ( '' === $this->standalone_version || version_compare( $this->standalone_version, self::MINIMUM_STANDALONE_VERSION, '<' ) );
	}

	/**
	 * Tells administrators to update or deactivate an older standalone
	 * Markdown Alternate.
	 */
	public function standalone_too_old_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: the active Markdown Alternate's version, 2: the oldest version Praline works with. */
					__( 'Markdown Alternate %1$s is older than %2$s, the version Praline works with. Praline has stopped until you update or deactivate Markdown Alternate.', 'praline' ),
					'' === $this->standalone_version ? __( '(unknown version)', 'praline' ) : $this->standalone_version,
					self::MINIMUM_STANDALONE_VERSION
				)
			)
		);
	}

	/**
	 * Adds a Praline section to Tools > Site Health > Info, saying which copy
	 * of Markdown Alternate serves the Markdown.
	 *
	 * @param mixed $info The sections so far.
	 * @return mixed
	 */
	public function site_health_info( mixed $info ): mixed {
		if ( ! is_array( $info ) ) {
			return $info;
		}

		if ( $this->standalone_is_too_old() ) {
			$markdown = __( 'Nothing: Praline has stopped, because the standalone Markdown Alternate is older than the version it works with.', 'praline' );
		} elseif ( null !== $this->standalone_version ) {
			/* translators: %s: the standalone Markdown Alternate's version. */
			$markdown = sprintf( __( 'The standalone Markdown Alternate plugin, version %s. Its own rules decide which addresses send BonBon on to Markdown.', 'praline' ), $this->standalone_version );
		} else {
			/* translators: %s: the bundled Markdown Alternate's version. */
			$markdown = sprintf( __( 'The bundled Markdown Alternate, version %s.', 'praline' ), ltrim( (string) InstalledVersions::getPrettyVersion( 'joost/markdown-alternate' ), 'v' ) );
		}

		$info['praline'] = array(
			'label'  => __( 'Praline', 'praline' ),
			'fields' => array(
				'markdown' => array(
					'label' => __( 'Markdown served by', 'praline' ),
					'value' => $markdown,
				),
			),
		);
		return $info;
	}

	/**
	 * On activation: adds the .md rewrite rules and saves them.
	 */
	public static function activate(): void {
		( new MarkdownRouter() )->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * On deactivation: forgets the saved rewrite rules, so WordPress builds
	 * them again on the next request, without the .md ones. Flushing here
	 * would save them again, since init has already added them.
	 */
	public static function deactivate(): void {
		delete_option( 'rewrite_rules' );
	}
}
