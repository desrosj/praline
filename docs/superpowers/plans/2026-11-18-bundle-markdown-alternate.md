# Bundle Markdown Alternate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make BonBon for Markdown Alternate a standalone plugin that ships the parts of Markdown Alternate it needs. The bundled copy is updated with a version bump, never edited.

**Architecture:** Composer pins Markdown Alternate from GitHub. Strauss copies only the needed classes, plus league/html-to-markdown, into `vendor-prefixed/` under the `BonBonMarkdownAlternate\Vendor\` namespace, and that folder is committed. Our plugin starts those classes itself and never runs Markdown Alternate's `Plugin`. Our own behavior goes in through Markdown Alternate's filters and one subclass of its `RewriteHandler`. If the standalone Markdown Alternate plugin is active, ours starts nothing and tells administrators why.

**Tech Stack:** PHP 7.4+, WordPress 6.5+, Composer 2, Strauss 0.22.6, the WordPress PHPUnit suite from the surrounding wordpress-develop checkout, and the `tests/e2e/run.sh` curl harness.

**Spec:** no separate document. The decisions were made in conversation on 2026-11-17/18 and are copied below as Global Constraints. The existing `README.md` describes current behavior.

## Global Constraints

- Upstream: `https://github.com/ProgressPlanner/markdown-alternate`, Composer name `joost/markdown-alternate`, pinned to exactly `1.2.0` through a `vcs` repository (it isn't on Packagist).
- Bundled from upstream, unmodified except for the namespace rewrite: `src/Router/RewriteHandler.php`, `src/Router/UrlConverter.php`, `src/Output/ContentRenderer.php`, `src/Converter/MarkdownConverter.php`, `src/PostTypeSupport.php`, `src/Discovery/AlternateLinkHandler.php`, `LICENSE`, and league/html-to-markdown with its `LICENSE`.
- Never bundled: `markdown-alternate.php`, `src/Plugin.php`, `src/Integration/`, `tests/`, and `yahnis-elsts/plugin-update-checker`.
- Namespace prefix: `BonBonMarkdownAlternate\Vendor\`. Classmap prefix: `BonBon_MA_`. Target directory: `vendor-prefixed/`.
- GPL-2.0-or-later §2a: every bundled Markdown Alternate PHP file carries the notice `Modified by BonBon for Markdown Alternate` with the build date and the upstream URL.
- `vendor-prefixed/` is committed; `vendor/` is not. The site never runs Composer.
- Code that runs on the site never edits a file in `vendor-prefixed/` by hand. Behavior changes go through upstream filters or the subclass in Task 4.
- Plugin header keeps `Requires at least: 6.5` and `Requires PHP: 7.4`, and drops `Requires Plugins`.
- Coding standard: `phpcs.xml.dist` (WordPress). The test command is `../../../../vendor/bin/phpunit`, run from the plugin folder; e2e is `tests/e2e/run.sh`.
- Never link BonBon to an address that answers 403 or 404. `Servable::is_servable()` is the single rule for that.

## Review Focus

- **Page Links To post with "open in new tab":** PLT appends `#new_tab` to the link. The local `.md` address must not carry a fragment. Pinned in Task 3 (`test_a_new_tab_fragment_is_dropped`).
- **Page Links To not installed:** the local permalink is simply `get_permalink()`. Pinned in Task 3 (`test_without_page_links_to_it_is_the_permalink`).
- **BonBon at the HTML address of a Page Links To post:** our 303 must run before PLT's own `template_redirect` redirect (priority 10), or BonBon is sent off-site. Pinned in Task 4 (`test_redirects_before_page_links_to`).
- **Standalone Markdown Alternate active too:** none of our code may hook `template_redirect` or `parse_request`, or both copies answer `.md` addresses. Pinned in Task 2 (`test_with_standalone_markdown_alternate_nothing_routes`).
- **A Composer update renames the method our subclass overrides:** `register()` must still hook our `handle_accept_negotiation` at priority 1. Pinned in Task 4 (`test_upstream_registers_our_redirect`).

---

### Task 1: Composer + Strauss bundle, in a new git repo for the plugin

The plugin folder is untracked inside the wordpress-develop checkout, so it gets its own repository.

**Files:**
- Create: `.gitignore`, `composer.json`, `composer.lock` (generated), `bin/mark-modified.php`, `vendor-prefixed/` (generated, committed)
- Modify: `bonbon-markdown-alternate.php` (require `vendor-prefixed/autoload.php` before the `spl_autoload_register` call)
- Test: `tests/phpunit/BundleTest.php`

**Interfaces:**
- Produces: the classes `BonBonMarkdownAlternate\Vendor\MarkdownAlternate\Router\RewriteHandler`, `…\Router\UrlConverter`, `…\Output\ContentRenderer`, `…\Converter\MarkdownConverter`, `…\PostTypeSupport`, `…\Discovery\AlternateLinkHandler` and `BonBonMarkdownAlternate\Vendor\League\HTMLToMarkdown\HtmlConverter`, loaded by the main plugin file. Composer script `composer prefix`.

- [ ] **Step 1: Start the repository.** From the plugin folder run `git init`. Write `.gitignore` containing `vendor/` and `.phpunit.result.cache`. Commit the current plugin as it stands: `git add -A && git commit -m "Initial import: BonBon for Markdown Alternate 0.1.0"`.

- [ ] **Step 2: Write the failing test** `tests/phpunit/BundleTest.php`:
  - `test_the_parts_it_needs_are_bundled_under_its_own_namespace`: `assertTrue( class_exists( … ) )` for each class in Produces.
  - `test_the_parts_it_does_not_need_are_not`: `assertFileDoesNotExist` for `vendor-prefixed/joost/markdown-alternate/markdown-alternate.php`, `…/src/Plugin.php` and `…/src/Integration`, and `assertDirectoryDoesNotExist( 'vendor-prefixed/yahnis-elsts' )`. Paths are relative to `dirname( __DIR__, 2 )`.
  - `test_licenses_are_kept`: `assertFileExists` for `vendor-prefixed/joost/markdown-alternate/LICENSE` and `vendor-prefixed/league/html-to-markdown/LICENSE`.
  - `test_changed_files_say_so`: every `*.php` under `vendor-prefixed/joost/markdown-alternate/src` contains `Modified by BonBon for Markdown Alternate` and `https://github.com/ProgressPlanner/markdown-alternate`.

- [ ] **Step 3: Run it and confirm it fails.** Run `../../../../vendor/bin/phpunit --filter BundleTest`. Expected: 4 failures (missing classes, missing files).

- [ ] **Step 4: Write `composer.json`.** Use the config proven in the 2026-11-18 trial:
  - `name`: `jonathandesrosiers/bonbon-markdown-alternate`
  - `type`: `wordpress-plugin`; `license`: `GPL-2.0-or-later`
  - `repositories`: `[{"type":"vcs","url":"https://github.com/ProgressPlanner/markdown-alternate"}]`
  - `require`: `{"php":">=7.4","joost/markdown-alternate":"1.2.0"}`; `require-dev`: `{"brianhenryie/strauss":"0.22.6"}`
  - `config.allow-plugins`: `{"composer/installers":false}`
  - `extra.installer-paths`: `{"vendor/{$vendor}/{$name}":["type:wordpress-plugin"]}`
  - `extra.strauss`:
    - `target_directory`: `vendor-prefixed`
    - `namespace_prefix`: `BonBonMarkdownAlternate\\Vendor\\`
    - `classmap_prefix`: `BonBon_MA_`
    - `packages`: `["joost/markdown-alternate"]`
    - `exclude_from_copy.packages`: `["yahnis-elsts/plugin-update-checker"]`
    - `exclude_from_copy.file_patterns`: `["~markdown-alternate/(markdown-alternate\\.php|src/Plugin\\.php|src/Integration/|tests/)~"]`
    - `delete_vendor_packages`: `false`
  - `scripts`:
    - `prefix`: `["strauss", "@php bin/mark-modified.php"]`
    - `post-install-cmd` and `post-update-cmd`: `["@prefix"]`

- [ ] **Step 5: Write `bin/mark-modified.php`.** It walks `vendor-prefixed/joost/markdown-alternate/src/**/*.php` and replaces the leading `<?php\n` with `<?php\n/* Modified by BonBon for Markdown Alternate on <Y-m-d>: namespaces prefixed with Strauss. Original: https://github.com/ProgressPlanner/markdown-alternate (GPL-2.0-or-later). */\n`. It skips any file that already contains the marker, and exits non-zero if it finds no files.

- [ ] **Step 6: Build the bundle.** Run `composer install`. Expected: Strauss prints `Done`. `vendor-prefixed/` then holds exactly `autoload.php`, `composer/`, `joost/` and `league/`, about 336 KB.

- [ ] **Step 7: Load the bundle.** In `bonbon-markdown-alternate.php`, add `require __DIR__ . '/vendor-prefixed/autoload.php';` after the `ABSPATH` guard.

- [ ] **Step 8: Run the whole suite.** Run `../../../../vendor/bin/phpunit`. Expected: `OK (63 tests, …)`, with all 59 existing tests still passing. Then run `../../../../vendor/bin/phpcs` and expect exit 0.

- [ ] **Step 9: Commit.** Run `git add -A && git commit -m "Bundle the needed parts of Markdown Alternate 1.2.0 with Strauss"`.

---

### Task 2: Start the bundled copy, and stand down when standalone Markdown Alternate is active

**Files:**
- Modify: `bonbon-markdown-alternate.php` (header, boot function, activation hooks); `src/Plugin.php`; `src/Servable.php`, `src/ArchiveNegotiation.php` and every other `use MarkdownAlternate\…` under `src/` (switch to the prefixed namespace); `tests/bootstrap.php` (load only this plugin); `tests/e2e/install.php` (activate only this plugin)
- Test: `tests/phpunit/PluginTest.php`, plus the namespace switch in every test that names `MarkdownAlternate\…`

**Interfaces:**
- Consumes: the Task 1 classes.
- Produces:
  - `Plugin::__construct( bool $standalone_active )`
  - `Plugin::boot(): void`
  - `Plugin::get_components(): array`
  - `Plugin::standalone_active_notice(): void` (renamed from `missing_dependency_notice`)
  - `static Plugin::activate(): void` and `static Plugin::deactivate(): void`
  - `bonbon_markdown_alternate_boot()` passes `class_exists( 'MarkdownAlternate\Plugin', false )`

- [ ] **Step 1: Update `PluginTest` so it fails.**
  - `test_with_standalone_markdown_alternate_it_only_shows_a_notice`: `new Plugin( true )`, then `boot()`. Assert `get_components()` is `[]` and `has_action( 'admin_notices', [ $plugin, 'standalone_active_notice' ] )`.
  - `test_with_standalone_markdown_alternate_nothing_routes`: reset with `remove_all_actions( 'template_redirect' )` and `remove_all_actions( 'parse_request' )`, then `( new Plugin( true ) )->boot()`. Assert `has_action( 'template_redirect' )` and `has_action( 'parse_request' )` are both false.
  - `test_the_notice_says_to_deactivate_markdown_alternate`: as an administrator, the output contains `Markdown Alternate` and `deactivate`, and has the class `notice-warning`.
  - Keep `test_the_notice_is_only_for_people_who_manage_plugins`, updated to call `standalone_active_notice`.
  - `test_it_starts_its_parts`: `new Plugin( false )`. Assert the component classes include the prefixed `RewriteHandler`, the prefixed `AlternateLinkHandler`, `SiteVersion`, `NoindexPolicy`, `FrontMatter`, `Router`, `ContentLocation` and `ArchiveNegotiation`.
  - `test_activation_adds_the_md_rewrite_rules`: set the permalink structure, call `Plugin::activate()`, then `assertArrayHasKey( '(.+?)\.md$', get_option( 'rewrite_rules' ) )`.

- [ ] **Step 2: Run it and confirm it fails.** Run `../../../../vendor/bin/phpunit --filter PluginTest`. Expected: failures on the missing `standalone_active_notice` and `activate` methods and the missing components.

- [ ] **Step 3: Implement.**
  - `boot()`: with `$standalone_active`, hook only the notice. Otherwise start, in this order: `SiteVersion`, `NoindexPolicy`, the prefixed `RewriteHandler`, the prefixed `AlternateLinkHandler`, `FrontMatter`, `Router`, `ContentLocation`, `ArchiveNegotiation`.
  - The notice (`notice notice-warning`, `activate_plugins` users only) reads exactly: `Markdown Alternate is active as a separate plugin. BonBon for Markdown Alternate already includes it, so it has stopped until you deactivate Markdown Alternate.`
  - `activate()` calls the prefixed `RewriteHandler`'s `add_rewrite_rules()`, then `flush_rewrite_rules()`. `deactivate()` calls `flush_rewrite_rules()`. Register both with `register_activation_hook` and `register_deactivation_hook` in the main file.
  - In the header, delete `Requires Plugins`. Change the Description to: `Serves your posts and pages as Markdown for BonBon, the browser for the Markdown web, with BonBon front matter, a table of contents, site search and Content-Location. Includes parts of Markdown Alternate.`
  - Switch every `MarkdownAlternate\` reference in `src/` and `tests/phpunit/` to `BonBonMarkdownAlternate\Vendor\MarkdownAlternate\`.
  - Make `tests/bootstrap.php` require only `bonbon-markdown-alternate.php`.
  - In `tests/e2e/install.php`, set `active_plugins` to `[ 'bonbon-markdown-alternate/bonbon-markdown-alternate.php' ]`.

- [ ] **Step 4: Run everything.** Expected:
  - `../../../../vendor/bin/phpunit`: OK.
  - `tests/e2e/run.sh`: `30 passed, 0 failed`. Same behavior, now without the standalone plugin.
  - `../../../../vendor/bin/phpcs`: exit 0.

- [ ] **Step 5: Commit.** Run `git commit -am "Run the bundled Markdown Alternate; stand down when the standalone plugin is active"`.

---

### Task 3: Build `.md` addresses from the post's address on this site (Page Links To)

Page Links To swaps `get_permalink()` for an external URL through its public filter `page_links_to_link( $meta_link, $post, $original_link )` (PLT 3.4.1, `classes/plugin.php:733`), and adds `#new_tab` when "open in new tab" is set. The local `.md` addresses already answer with Markdown on the live site.

**Files:**
- Modify: `src/Servable.php`
- Test: `tests/phpunit/ServableTest.php` (new), `tests/phpunit/TableOfContentsTest.php`

**Interfaces:**
- Produces: `Servable::local_permalink( WP_Post $post ): string`. `Servable::markdown_url()` now builds from it, so the TOC, search, `urlPrev`/`urlNext` and Content-Location all get the fix. FrontMatter's `link` deliberately stays `get_permalink()`, because the external article is where the post lives.

- [ ] **Step 1: Write the failing tests.** Simulate PLT with a test-only helper that adds a `post_link` filter at priority 20. It returns `apply_filters( 'page_links_to_link', 'https://make.wordpress.org/core/x/', $post, $link ) . $suffix`.
  - `test_without_page_links_to_it_is_the_permalink`: `local_permalink( $p ) === get_permalink( $p )`.
  - `test_a_page_links_to_post_is_its_address_on_this_site`: with the simulation on, the result starts with `home_url()`. `get_permalink()` still returns the make.wordpress.org URL afterwards, which proves the filter was removed again.
  - `test_a_new_tab_fragment_is_dropped`: with `$suffix = '#new_tab'`, the result contains no `#`.
  - In `TableOfContentsTest`, `test_a_page_links_to_post_is_listed_at_its_md_on_this_site`: with the simulation on, every `stops()` URL starts with `home_url()`.

- [ ] **Step 2: Run them and confirm they fail.** Run `../../../../vendor/bin/phpunit --filter 'Servable|page_links_to'`. Expected: an undefined method error, then a URL assertion failure once a stub exists.

- [ ] **Step 3: Implement `local_permalink()`.** Add a `page_links_to_link` callback at `PHP_INT_MAX` that returns its third argument, call `get_permalink()`, remove the callback, and strip anything from `#` onward. Then point `markdown_url()` at it.

- [ ] **Step 4: Run everything.** Expected: phpunit OK, e2e 30/30, phpcs exit 0.

- [ ] **Step 5: Commit.** Run `git commit -am "Link Page Links To posts at their .md on this site"`.

---

### Task 4: `MarkdownRouter`, the bundled router with BonBon's redirect rule, replacing `ArchiveNegotiation`

**Files:**
- Create: `src/MarkdownRouter.php`
- Delete: `src/ArchiveNegotiation.php`, `tests/phpunit/ArchiveNegotiationTest.php`
- Modify: `src/Plugin.php` (start `MarkdownRouter` in place of both the prefixed `RewriteHandler` and `ArchiveNegotiation`; `activate()` uses it)
- Test: `tests/phpunit/MarkdownRouterTest.php`, `tests/phpunit/PluginTest.php`

**Interfaces:**
- Consumes: `Servable::is_servable()` and `Servable::markdown_url()` (Task 3).
- Produces: `class MarkdownRouter extends \BonBonMarkdownAlternate\Vendor\MarkdownAlternate\Router\RewriteHandler`, with:
  - `get_redirect_url(): ?string`
  - `handle_accept_negotiation(): void`, which overrides upstream

- [ ] **Step 1: Write the failing tests.** Each test sets `$_SERVER['HTTP_ACCEPT'] = 'text/markdown, text/x-opml'` (unset it in `tear_down`), calls `go_to()`, then `( new MarkdownRouter() )->get_redirect_url()`.
  - `test_a_post_is_sent_to_its_md`: equals `Servable::markdown_url( $post )`.
  - `test_a_page_is_sent_to_its_md`.
  - `test_the_static_front_page_is_sent_to_index_md`: equals `home_url( '/index.md' )`.
  - `test_author_date_and_category_archives_are_not`: null for each.
  - `test_a_password_protected_post_is_not`: null.
  - `test_not_without_text_markdown_in_accept`: with `Accept: text/html`, null.
  - `test_a_page_links_to_post_is_sent_to_its_md_on_this_site`: using Task 3's simulation, the result starts with `home_url()`.
  - `test_upstream_registers_our_redirect`: after `register()`, `has_action( 'template_redirect', [ $router, 'handle_accept_negotiation' ] ) === 1`.
  - `test_redirects_before_page_links_to`: the priority found in the previous test is less than 10.
  - In `PluginTest`, the components include `MarkdownRouter` and no longer include `ArchiveNegotiation` or the bare prefixed `RewriteHandler`.

- [ ] **Step 2: Run them and confirm they fail.** Run `../../../../vendor/bin/phpunit --filter 'MarkdownRouter|starts_its_parts'`. Expected: class not found, then assertion failures once a stub exists.

- [ ] **Step 3: Implement.**
  - `get_redirect_url()` returns null if any of these hold:
    - the `markdown_request` query var is set
    - `Accept` doesn't contain `text/markdown`
    - `! is_singular()`
    - the queried object isn't a `WP_Post` that passes `Servable::is_servable()`

    Otherwise it returns `Servable::markdown_url()`.
  - `handle_accept_negotiation()` returns when `get_redirect_url()` gives null. Otherwise it keeps upstream's cache handling exactly (`RewriteHandler.php:445-455` in 1.2.0):
    - the `markdown_alternate_disable_page_cache_on_redirect` filter, default true
    - `Cache-Control: private`
    - `DONOTCACHEPAGE`
    - the `litespeed_control_set_nocache` action

    Then it sends `Vary: Accept`, calls `wp_safe_redirect( $url, 303 )` and exits.
  - Delete `ArchiveNegotiation` and its test.

- [ ] **Step 4: Run everything.** Expected: phpunit OK, phpcs exit 0, and e2e 30/30. The three archive checks now pass because of `MarkdownRouter`.

- [ ] **Step 5: Mutation check.** Temporarily make `get_redirect_url()` skip the `is_singular()` guard and run `tests/e2e/run.sh`. Expected: the 3 archive checks FAIL. Restore the guard and confirm 30/30 again.

- [ ] **Step 6: Commit.** Run `git commit -am "Replace ArchiveNegotiation with a MarkdownRouter subclass of the bundled router"`.

---

### Task 5: Drop the category and tag links from front matter

Upstream writes `categories`/`tags` entries linking to `/category/x.md`, and those addresses 404 (live site, 2026-11-17).

**Files:**
- Modify: `src/FrontMatter.php` (`register()` adds `markdown_alternate_frontmatter_taxonomies` → `__return_empty_array`)
- Test: `tests/phpunit/FrontMatterTest.php`

- [ ] **Step 1: Write the failing test.** `test_no_category_or_tag_links`: a post with a category and a tag, rendered through the prefixed `ContentRenderer`. Assert the output contains neither `categories:` nor `tags:` nor `/category/`.
- [ ] **Step 2: Run it and confirm it fails.** Run `../../../../vendor/bin/phpunit --filter test_no_category_or_tag_links`. Expected: FAIL, output contains `categories:`.
- [ ] **Step 3: Implement** the filter in `FrontMatter::register()`.
- [ ] **Step 4: Run everything.** Expected: phpunit OK, e2e 30/30, phpcs exit 0.
- [ ] **Step 5: Commit.** Run `git commit -am "Leave out category and tag links that would 404"`.

---

### Task 6: End-to-end checks for the standalone plugin, plus README

**Files:**
- Modify: `tests/e2e/run.sh`, `README.md`

- [ ] **Step 1: Add the e2e check, and prove it can fail.** Under a new heading `echo "Standalone"`, check that `$BASE/2026/11/02/newer-rhubarb-post.md` (fetched with `$BONBON_ACCEPT`) answers `200 text/markdown` and its body has no `categories:` line. `install.php` activates only this plugin, so this shows the bundled pipeline serving on a real server. Temporarily remove the Task 5 filter, run `tests/e2e/run.sh`, and expect this check to FAIL. Then restore the filter.
- [ ] **Step 2: Update the README.**
  - Replace "It requires Markdown Alternate" with a **Credits and license** section: the bundled parts, upstream URL, authors (Joost de Valk / Progress Planner), GPL-2.0-or-later, and league/html-to-markdown under MIT.
  - Add an **Updating Markdown Alternate** section:
    1. Change the version in `composer.json`.
    2. Run `composer update joost/markdown-alternate`.
    3. Read `gh api repos/ProgressPlanner/markdown-alternate/compare/v<old>...v<new>`, especially `src/Router/RewriteHandler.php`.
    4. Run phpunit, e2e and phpcs.
    5. Commit `composer.lock` and `vendor-prefixed/`.
  - Rewrite **What it changes in Markdown Alternate** to describe `MarkdownRouter`, the Page Links To handling and the taxonomy removal.
  - Note that the standalone plugin must be deactivated.
  - Update the test counts to the actual numbers.
- [ ] **Step 3: Run everything.** Expected: phpunit OK, e2e all passed, phpcs exit 0.
- [ ] **Step 4: Commit.** Run `git commit -am "E2E checks and README for the standalone plugin"`.
