=== Praline ===
Contributors: desrosj
Tags: markdown, bonbon, opml, content negotiation, table of contents
Requires at least: 6.5
Tested up to: 7.2
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serves your posts and pages as Markdown for BonBon, the browser for the Markdown web, with a table of contents, arrows and site search.

== Description ==

[BonBon](https://github.com/scripting/bonBon) is a browser for the Markdown web. When it asks for a post or page with `Accept: text/markdown`, Praline sends it on to that page's `.md` address. Browsers get the same HTML as always.

Beyond the Markdown itself, Praline gives BonBon everything it reads:

* **Front matter** on every page: `pubDate`, `description` (a handwritten excerpt), `link` (the HTML page), `rightMarginImage` (the featured image), and `urlPrev` and `urlNext` for the arrows.
* **A table of contents** at `/.well-known/toc.opml`: the posts by year, month and day, newest first, then the pages.
* **Arrows that run one way through the whole site**: from the oldest post to the newest, on to the front page, then through the pages.
* **Site search**, named in `/.well-known/bonbon.json`.
* **`Content-Location`**, naming the `.md` file when Markdown is served at an address that isn't one.

Only content anyone can read is served as Markdown or linked anywhere: published, not password protected, not hidden from search engines with Yoast SEO, and not the posts page. Category, tag, author and date archives keep answering with their HTML.

Praline needs no other plugin. It includes the parts of [Markdown Alternate](https://github.com/ProgressPlanner/markdown-alternate) that turn posts into Markdown, and Markdown Alternate's `markdown_alternate_*` filters all still work.

Praline sends nothing to any outside service.

== Installation ==

1. Upload the `praline` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it.
3. Use pretty permalinks (**Settings → Permalinks**, anything but Plain).

Praline works with or without the standalone Markdown Alternate plugin. See the FAQ.

== Frequently Asked Questions ==

= Does it change my site for anyone else? =

No. Only a request that asks for `text/markdown` is sent on to Markdown. The redirect is marked so page caches don't store it.

= Can I keep the Markdown Alternate plugin active? =

Yes, version 1.2.0 or later. Praline then leaves its bundled copy off, lets Markdown Alternate serve the Markdown, and adds BonBon's front matter, table of contents and search on top. Markdown Alternate's own rules then decide which addresses send BonBon on to Markdown, so category and date archives, password-protected posts and posts that link elsewhere with Page Links To behave as they do in Markdown Alternate. Tools → Site Health → Info → Praline says which copy is serving.

With an older Markdown Alternate, Praline stops and asks administrators to update or deactivate it.

= `/.well-known/bonbon.json` answers 404. =

Some hosts answer every `.json` request themselves, before WordPress runs. Upload a static `/.well-known/bonbon.json` to your site's root folder containing `{"urlSearch": "https://example.com/.well-known/bonbon-search"}`, with your own domain.

= How do I serve noindexed content as Markdown anyway? =

Praline skips anything hidden from search engines. To turn that off:

`add_filter( 'markdown_alternate_skip_noindexed', '__return_false', 20 );`

= What happens when I delete the plugin? =

It removes what it stored: its site version, the cached table of contents and each post's cached Markdown. Deactivating it removes its `.md` rewrite rules.

== Changelog ==

= 0.1.0 =
* First release.

== Credits ==

Praline includes parts of these, each with its license kept beside the code in `vendor-prefixed/`:

* [Markdown Alternate](https://github.com/ProgressPlanner/markdown-alternate) 1.2.0, by Joost de Valk and Progress Planner, GPL-2.0-or-later. Its namespaces are renamed with Strauss, and each bundled file says so at its top.
* [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown), by Colin O'Dell and Nick Cernis, MIT.
