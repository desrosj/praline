![Praline: your voice on the web of Markdown. Support the BonBon Markdown browser in WordPress.](assets/banner-1544x500.png)

# Praline

Serves a WordPress site's posts and pages as Markdown for [BonBon](https://github.com/scripting/bonBon), the browser for the Markdown web, with everything BonBon reads beyond the Markdown itself.

When BonBon asks for a post or page with `Accept: text/markdown`, it gets a 303 to that page's `.md` address. Browsers get the same HTML as always. The Markdown itself comes from the parts of [Markdown Alternate](https://github.com/ProgressPlanner/markdown-alternate) this plugin bundles (see [Credits and license](#credits-and-license)), so it needs no other plugin.

**With the standalone Markdown Alternate plugin active** (1.2.0 or later), Praline leaves its bundled copy off and lets the standalone plugin serve the Markdown, adding its front matter, outline, search and `Content-Location` on top through the same filters. Deactivate the standalone plugin and Praline's bundled copy takes over on the next request. **Tools → Site Health → Info → Praline** says which copy is serving. With an older standalone plugin, Praline stops and shows administrators a notice asking to update or deactivate it.

While the standalone plugin serves the Markdown, its own rules decide which addresses send BonBon on to a `.md`, so the redirects in the first two differences below are Markdown Alternate's: BonBon is sent from an archive to a `.md` that doesn't exist (404), from a password-protected post to a 403, and from a Page Links To post to `/wp-admin/`. The outline, arrows and search still link only content that has Markdown, at its address on this site.

## What it adds

**Front matter**, on every page it renders:

| Field | From | What BonBon does with it |
|---|---|---|
| `pubDate` | publish time, RFC 822, GMT | `{{page.pubDate}}` |
| `description` | the handwritten excerpt, when there is one | `{{page.description}}` |
| `link` | the HTML permalink (for a Page Links To post, the page it links to) | makes the title a link |
| `rightMarginImage` | the featured image (PNG, JPEG, GIF, WebP; never SVG) | floats it at the top right |
| `urlPrev`, `urlNext` | the `.md` before and after it in one line through the site (below) | the ◀ ▶ arrows |

**`/.well-known/toc.opml`**: the table of contents.
- The posts come first, as `calendarYear` → `calendarMonth` → `calendarDay` nodes, newest first, the shape of Scripting News's outline. A day's line is its newest post, and that day's other posts sit under it.
- Then come the pages, flat, with the static front page first.

**The arrows** run one way through the whole site, and every post and page names its own in front matter:

```
oldest post ⇄ … ⇄ newest post ⇄ front page ⇄ next page ⇄ … ⇄ last page
```

So ◀ on a post is the older one, ▶ on the newest post goes to the front page, and ◀ on the front page goes back to the newest post.

**`/.well-known/bonbon.json`** names `urlSearch`, at **`/.well-known/bonbon-search?q=word`**, which answers a Markdown page of results.
- Some hosts answer every `.json` request themselves, before WordPress runs. If `bonbon.json` is a 404, upload a static `/.well-known/bonbon.json` containing `{"urlSearch": "https://example.com/.well-known/bonbon-search"}`, with your own domain.

**`Content-Location`** names the `.md` file when Markdown is served at an address that isn't one (`?format=markdown`).

## How it differs from Markdown Alternate

- **Only posts and pages are redirected.** Markdown Alternate also sends category, tag, author and date archives to `.md` addresses it has no Markdown for (404). `MarkdownRouter`, a subclass of its router, sends on only a post or page that has Markdown for anyone to read. Everything else answers with its HTML, including password-protected posts.
- **Page Links To posts stay on this site.** [Page Links To](https://wordpress.org/plugins/page-links-to/) swaps a post's permalink for an external URL. Markdown Alternate then redirected BonBon to `/wp-admin/`, and the outline linked other sites' `.md` addresses. Here they link to the post's own `.md` on this site.
- **No category or tag links in front matter.** Markdown Alternate linked them to `/category/x.md`, a 404.
- **Noindexed content is skipped.** `markdown_alternate_skip_noindexed` is on (off by default in Markdown Alternate), so anything hidden from search engines with Yoast SEO gets no Markdown and no place in the outline or search. To turn it back off: `add_filter( 'markdown_alternate_skip_noindexed', '__return_false', 20 );`
- **Only anonymous visitors share cached Markdown.** Markdown Alternate caches each post's Markdown once for everyone, but renders it as whoever is visiting, so content that differs by visitor (a Login/out block, a members-only section, a WooCommerce cart or account page) reached everyone as the first visitor saw it. `CachePolicy` turns `markdown_alternate_cache_enabled` off for a visitor who is logged in or carries a session cookie (a post password, or WooCommerce's or Easy Digital Downloads' session or cart). They get a fresh render each time, marked so HTTP and page caches don't store it. A plugin with a session cookie of its own can say so with `add_filter( 'praline_visitor_has_state', … )`.
- **Left out:** Markdown Alternate's GitHub updater (`composer.json` replaces its `yahnis-elsts/plugin-update-checker` package, so Composer doesn't install it), and its Yoast llms.txt and WooCommerce integrations. With the standalone plugin active, its own copies of those run.

Markdown Alternate's filters (`markdown_alternate_*`) all still work.

## What is linked

Only content that is actually served as Markdown is linked anywhere: published, supported types, not password protected, not skipped as noindexed, and not the posts page (its Markdown would be empty, since WordPress shows the list of posts in its place).

Cached Markdown and the outline are rebuilt when content is published, unpublished or edited, or when the front page, site name or permalinks change. `FrontMatter::FORMAT` is part of the cache token; change it whenever the output changes for every post.

## Not included

- `urlUpdateSocket`: it needs a long-running WebSocket server, which PHP on shared hosting can't keep.
- `urlMirrorOf`: same-domain content negotiation makes it unnecessary.

## Credits and license

GPL-2.0-or-later.

`vendor-prefixed/` holds parts of [Markdown Alternate](https://github.com/ProgressPlanner/markdown-alternate) by Joost de Valk / Progress Planner (GPL-2.0-or-later), version 1.2.0:
- `RewriteHandler`, `UrlConverter`, `ContentRenderer`, `MarkdownConverter`, `PostTypeSupport` and `AlternateLinkHandler`
- [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) by Colin O'Dell and Nick Cernis (MIT), which it uses

[Strauss](https://github.com/BrianHenryIE/strauss) renames their namespaces to `Praline\Vendor\`, and each bundled Markdown Alternate file says so in a notice at its top. Both license files are kept beside the code. Nothing in `vendor-prefixed/` is edited by hand.

## Updating Markdown Alternate

1. Change the `joost/markdown-alternate` version in `composer.json`.
2. Run `composer update joost/markdown-alternate`. Composer reruns Strauss and `bin/mark-modified.php`.
3. Read what changed: `gh api repos/ProgressPlanner/markdown-alternate/compare/v<old>...v<new>`. Look closest at `src/Router/RewriteHandler.php`, which `MarkdownRouter` extends, and at the filters this plugin uses.
4. Run the tests below. The Bundled Code workflow confirms `vendor-prefixed/` matches `composer.lock`.
5. Commit `composer.json`, `composer.lock` and `vendor-prefixed/`.

## Tests

`composer install` installs the test tools and rebuilds `vendor-prefixed/` (add `--no-scripts` to leave it as committed). Then, from this folder:

```
composer test                       # 90 PHPUnit integration tests
composer test:e2e                   # 38 HTTP checks, as BonBon asks; drops and recreates the praline_e2e database
E2E_WITH_MARKDOWN_ALTERNATE=1 composer test:e2e   # 36 of them again with the standalone Markdown Alternate active (needs it in ../markdown-alternate)
composer phpcs                      # WordPress coding standards
composer phpcompat                  # PHP 8.2+ compatibility, including the bundled code
```

PHPUnit needs the WordPress test suite. It uses `WP_TESTS_DIR` when that's set; otherwise the surrounding [wordpress-develop](https://github.com/WordPress/wordpress-develop) checkout when the plugin sits in its `src/wp-content/plugins/`; otherwise the one `bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]` installs in the temp folder. The end-to-end checks need the plugin inside a WordPress install (`wp-content/plugins/praline`), a MySQL server and `curl`.

GitHub Actions runs all of this on pull requests and on `main`:

- **PHPUnit Tests:** PHP 8.2 to 8.6, each with the latest WordPress release and with nightly (trunk), plus PHP 8.2 with WordPress 6.5.
- **End-to-end Tests:** the end-to-end checks on PHP 8.2 and 8.6 with the latest WordPress, with the bundled and with the standalone Markdown Alternate (its 1.2.0 release, checked against its SHA-256).
- **Bundled Code:** a rebuild of `vendor-prefixed/` that must match what's committed.
- **Coding Standards:** PHPCS and PHP compatibility, with results shown on the pull request.
- **Lint Workflows:** actionlint and zizmor on the workflow files.

Dependabot proposes GitHub Actions and Composer updates weekly. A Composer update that changes the bundled code fails the Bundled Code workflow until `vendor-prefixed/` is rebuilt (`composer prefix`) and committed.

## Packaging a release

What a site gets is everything not listed in `.distignore`: `praline.php`, `uninstall.php`, `readme.txt`, `LICENSE`, `src/` and the committed `vendor-prefixed/`. `.gitattributes` leaves out the same files, so `git archive` and GitHub's zip downloads match. Keep the two lists in step.

1. Set the new version in three places: the `Version:` header in `praline.php`, `Stable tag:` in `readme.txt`, and a `== Changelog ==` entry there.
2. Check `Tested up to:` in `readme.txt` against the current WordPress release.
3. Run the tests above.
4. Build the zip with `wp dist-archive .` (WP-CLI's `dist-archive` command), or `git archive --format=zip --prefix=praline/ -o praline.zip HEAD`.

There's no need for `composer install --no-dev`: the bundled code is already in `vendor-prefixed/`, and Composer is needed only to rebuild it.

`assets/` is for the WordPress.org listing, deployed to the SVN `assets/` folder, never to sites. It holds the icon and banner so far. WordPress.org reads these names:

- **Icon:** `icon.svg`, or `icon-128x128.png` and `icon-256x256.png`.
- **Banner:** `banner-772x250.png` and `banner-1544x500.png`.
- **Screenshots:** `screenshot-1.png`, `screenshot-2.png` and so on, each captioned in a `== Screenshots ==` section of `readme.txt`.
