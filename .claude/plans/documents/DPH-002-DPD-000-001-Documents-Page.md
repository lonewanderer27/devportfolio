# Plan: Main Navigation, Documents Page Shell and Title (new standalone `devportfolio` theme)

Tickets DPH-002 (Homepage navbar), DPD-000 (Documents page setup), DPD-001 (Documents title). Branch `feat/DPH-002-DPD-000-001-Documents-Page`. Wireframes: `.claude/plans/home/home-lofi-wireframe.html`, `.claude/plans/documents/documents-lofi-wireframe.html`. Later tickets fill the shell this plan leaves: DPD-002 (category buttons), DPD-016 (book shelf).

## Context
- No custom theme exists. The only tracked theme is `twentytwentyfive` (block theme; WP is 7.1.4). It carries ~8.7 MB of fonts, images, 98 patterns and 8 style variations a portfolio does not need.
- The `document` CPT already has archive `/documents/` (`has_archive => true`). Same for `/services/`, `/videos/`, `/collaterals/`. Testimonials and Contact are not pages or content types: they are **sections of the Home page**, reached by in-page anchors `/#testimonials` and `/#contact` (Contact details come from the Settings page `cptdp_contact`).
- Wireframe conventions (Home + Documents lo-fi): greyscale (ink `#222`, mid `#8a8a8a`, line `#c9c9c9`, fill `#eee`), system sans font with Georgia serif for book titles, 14px body, page title 34px / 800, 1040px content width, 1.5px ink borders with 6px radius on buttons. Nav: links only, centred, thin rule underneath; the active link is ink-coloured with a 2px underline. The Documents wireframe's older 4-link nav is superseded by the 7-link Home one.

## Key decisions
1. **DPD-000 is an archive template, not a Page.** A Page slugged `documents` would be shadowed by the CPT archive rewrite rule (registered before the catch-all page rule), so it would never render. The "Documents page" is the block template `templates/archive-document.html`, which WordPress picks automatically for the `document` archive. It is tracked in git, needs no seeding, and DPD-002/016 can query `document` posts and `document_category` terms natively. No plugin change, no version bump.
2. **Standalone theme, not a child theme.** `devportfolio` is a fork of Twenty Twenty-Five 1.5 with no `Template:` header, so it has no parent. It is then **pruned hard** to only what the lo-fi pages need (about 13 files, ~50 KB, no build step), instead of carrying the whole upstream theme. Twenty Twenty-Five is GPL-2.0-or-later: keep the licence header and credit the original in `style.css` and `readme.txt`.
3. **Navigation links are inline in the header part**, not a `wp_navigation` post, so the menu is versioned in git instead of living in the DB. Links store root-relative URLs (no domain hardcoded) and a small filter expands them at render time (see Step C).

## Scope
In: shared header nav with 7 links and active state (DPH-002), Documents archive template with empty content container (DPD-000), `Documents` h3 (DPD-001), the pruned theme they live in.
Out: tests for anything but the navbar, category buttons, shelf, logo / "Book a call" / 900px collapse polish from the wireframe (core mobile overlay only), Home page content (including the Testimonials and Contact sections themselves; the nav anchors only work once those sections carry `id="testimonials"` and `id="contact"`), Services/Videos/Collaterals templates (the links point at the existing archives).

## Steps

### Step A: allow the theme in git (do first)
`.gitignore` is allowlist-style. Under `!/wp-content/themes/twentytwentyfive/` add:
```
!/wp-content/themes/devportfolio/
```
Otherwise nothing created below is tracked.

### Step B: fork
```bash
cd wp-content/themes
cp -R twentytwentyfive devportfolio
cd devportfolio
rm -rf node_modules style.min.css contributing.txt .DS_Store
grep -rlE "twentytwentyfive|Twenty_Twenty_Five|Twenty Twenty-Five" . \
  | xargs sed -i '' -e 's/twentytwentyfive/devportfolio/g' \
                    -e 's/Twenty_Twenty_Five/Devportfolio/g' \
                    -e 's/Twenty Twenty-Five/devportfolio/g'
```
The `sed` also rewrites the words "Twenty Twenty-Five", so the credit lines are re-added by hand in Step D. Check: `grep -rniE twentytwenty .` returns nothing at this point.

### Step C: prune to what the wireframes need
Delete:
- `patterns/` (all 98), `styles/` (8 variations), `assets/` (fonts, images, `editor-style.css`), `screenshot.png`
- `package.json`, `package-lock.json`, `node_modules/`, `style.min.css` (no build step is needed once `style.css` is header-only)
- `templates/home.html`, `templates/page-no-title.html` (`index.html` covers the blog home)
- parts `footer-columns`, `footer-newsletter`, `header-large-title`, `sidebar`, `vertical-header`

Resulting tree (13 files, before the tests are added in Step E): `functions.php`, `readme.txt`, `style.css`, `theme.json`, `parts/{header,footer}.html`, `templates/{index,archive,search,single,page,404,archive-document}.html`.

### Step D: write the theme files

#### `style.css` (header only; no CSS body, no `Template:`)
```css
/*
Theme Name: devportfolio
Description: Portfolio theme for Dave John Deluta. Standalone, greyscale, built from the lo-fi wireframes. Originally forked from Twenty Twenty-Five by the WordPress team.
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Version: 0.1.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: devportfolio
Tags: one-column, custom-colors, custom-menu, full-site-editing, translation-ready, accessibility-ready, portfolio
*/
```

#### `readme.txt`
Standard header (`=== devportfolio ===`, `Requires at least: 6.7`, `Tested up to: 7.1`, `Requires PHP: 7.4`, `Stable tag: 0.1.0`, GPLv2 or later) and one description line: "Portfolio block theme for Dave John Deluta, built from the lo-fi wireframes. Originally forked from Twenty Twenty-Five 1.5, (C) the WordPress team, GPL-2.0-or-later."

#### `theme.json` (rewritten from scratch, version 3)
- `settings`: `appearanceTools` and `useRootPaddingAwareAlignments` true; `layout` content `1040px`, wide `1200px`; `color` default palette/gradients/duotone off, palette `base #ffffff`, `contrast #222222` (ink), `mid #8a8a8a`, `line #c9c9c9`, `fill #eeeeee`; `typography` fluid off, default font sizes off, families `sans` (`-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif`) and `serif` (`Georgia, 'Times New Roman', serif`), sizes `small 12.5px`, `medium 14px`, `large 22px`, `x-large 28px`, `xx-large 34px`; `spacing` default sizes off, scale `20: 8px`, `30: 16px`, `40: 24px`, `50: 32px`, `60: 48px`, `70: 64px`.
- `styles`: background `base`, text `contrast`; root left/right padding `spacing|40`; typography sans, `medium`, line-height 1.4; `elements.link` colour `contrast` with underline on hover; `elements.heading` weight 800, letter-spacing `-0.02em`, line-height 1.15; `h3` size `xx-large` (the page title), `h4` size `large`.
- `styles.blocks."core/navigation"`: follows the lo-fi `.nav .links` / `.nav .on` rules. Font size `medium`, **text colour `mid` (`#8a8a8a`) set on the nav itself** so inactive links are the faded grey, links `textDecoration: none`, and this `css` string (active link = ink text with a solid 2px ink bar under it via `border-bottom`, not a text underline; Documents gets it from `aria-current="page"` / `.current-menu-item`, Home from the `is-current` class set by the filter below; inactive links turn ink on hover):
```json
"css": "& a { font-weight: 400; text-decoration: none; } & a:hover { text-decoration: none; } & .wp-block-navigation-item:hover { color: var(--wp--preset--color--contrast); } & .current-menu-item { color: var(--wp--preset--color--contrast); } & .is-current { color: var(--wp--preset--color--contrast); } & a[aria-current='page'] { border-bottom: 2px solid var(--wp--preset--color--contrast); } & .is-current > a { border-bottom: 2px solid var(--wp--preset--color--contrast); }"
```
  Three traps, all verified against the rendered CSS: (1) core forces `.wp-block-navigation .wp-block-navigation-item__content.wp-block-navigation-item__content { color: inherit }`, which beats anything in this string, so never set the link `color` here: set the grey on the nav and the ink on the active/hovered `<li>` (links inherit it); (2) comma-separated selector lists (`a, b { }`) are mangled (the first selector is left orphaned and the rule silently never applies), so write one rule per selector; (3) a `body.home &` selector is mangled too (Home would be styled everywhere).
- `styles.blocks."core/button"`: border colour `contrast`, 1.5px solid, radius 6px.
- `templateParts`: `header` (area header) and `footer` (area footer) only.

#### `functions.php` (one filter, nothing else)
Core marks a post type archive link as current only when its `url` equals `get_post_type_archive_link()`, so a stored path like `/documents/` can never match reliably (with plain permalinks the real link is `?post_type=document`). The filter therefore resolves archive links from their post type, expands other root-relative links with `home_url()`, and flags the Home link (a custom link core never marks) on the front page:
```php
<?php
/**
 * Theme functions. Design lives in theme.json, templates and parts.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_prepare_navigation_link' ) ) :
	/**
	 * Prepares main navigation links before render.
	 *
	 * @since 0.1.0
	 *
	 * @param array $parsed_block The block being rendered.
	 * @return array
	 */
	function devportfolio_prepare_navigation_link( $parsed_block ) {
		if (
			'core/navigation-link' !== $parsed_block['blockName']
			|| empty( $parsed_block['attrs']['url'] )
			|| ! str_starts_with( $parsed_block['attrs']['url'], '/' )
		) {
			return $parsed_block;
		}

		if ( '/' === $parsed_block['attrs']['url'] && is_front_page() ) {
			$parsed_block['attrs']['className'] = 'is-current';
		}

		$archive_link = '';
		if ( 'post-type-archive' === ( $parsed_block['attrs']['kind'] ?? '' ) && ! empty( $parsed_block['attrs']['type'] ) ) {
			$archive_link = get_post_type_archive_link( $parsed_block['attrs']['type'] );
		}

		$parsed_block['attrs']['url'] = $archive_link ? $archive_link : home_url( $parsed_block['attrs']['url'] );
		return $parsed_block;
	}
endif;
add_filter( 'render_block_data', 'devportfolio_prepare_navigation_link' );
```
(Include the full docblock from the real file; it explains the three behaviours above.) The theme's own stylesheet is not enqueued (it has no CSS).

#### `parts/header.html` (DPH-002)
Links only, centred, 22px gap between links (lo-fi `gap:22px`), 1px line underneath. The wrapper group is a `div` because the template part already outputs the `<header>` element. **Border gotcha:** setting a border colour makes core add `has-border-color`, whose rule `border-style: solid` applies to all four sides, which draws a box. So the one real side is declared `solid` and the other three explicitly `none`, in both the block comment and the inline style (they must match or the editor flags the block as invalid).
```html
<!-- wp:group {"tagName":"div","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"border":{"bottom":{"color":"var:preset|color|line","width":"1px","style":"solid"},"top":{"style":"none"},"right":{"style":"none"},"left":{"style":"none"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-border-color" style="border-top-style:none;border-right-style:none;border-bottom-color:var(--wp--preset--color--line);border-bottom-style:solid;border-bottom-width:1px;border-left-style:none;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:navigation {"ariaLabel":"Main","overlayMenu":"mobile","overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"22px"}}} -->
		<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Services","type":"service","url":"/services/","kind":"post-type-archive","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Documents","type":"document","url":"/documents/","kind":"post-type-archive","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Videos","type":"video","url":"/videos/","kind":"post-type-archive","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Collaterals","type":"collateral","url":"/collaterals/","kind":"post-type-archive","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Testimonials","url":"/#testimonials","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Contact","url":"/#contact","kind":"custom","isTopLevelLink":true} /-->
	<!-- /wp:navigation -->
</div>
<!-- /wp:group -->
```
Testimonials and Contact are Home-page section anchors, not pages: `/#testimonials` and `/#contact`. The label is "Contact", matching the wireframe (the story says "Contacts"). Anchor links never get `aria-current`, so only the six page links can show the active underline; the filter still expands them to `home_url( '/#contact' )`, so they work from every page. The Home link is the exact URL `/` and is unaffected.

#### `parts/footer.html`
Site title only, small and mid grey, 1px line on top; same border rule as the header (top `solid`, right/bottom/left `none`).
```html
<!-- wp:group {"tagName":"footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}},"border":{"top":{"color":"var:preset|color|line","width":"1px","style":"solid"},"right":{"style":"none"},"bottom":{"style":"none"},"left":{"style":"none"}}},"layout":{"type":"constrained"}} -->
<footer class="wp-block-group has-border-color" style="border-top-color:var(--wp--preset--color--line);border-top-style:solid;border-top-width:1px;border-right-style:none;border-bottom-style:none;border-left-style:none;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:site-title {"level":0,"fontSize":"small","textColor":"mid"} /-->
</footer>
<!-- /wp:group -->
```

#### `templates/archive-document.html` (DPD-000, DPD-001)
```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"documents-page","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group documents-page" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">

	<!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">Documents</h3>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"documents-page__content","layout":{"type":"default"}} -->
	<div class="wp-block-group documents-page__content"></div>
	<!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```
- The title is a literal level-3 heading with the exact text `Documents` (not `post-title` / `query-title`, so casing never depends on the CPT label). Left-aligned by default; bold and 34px come from `theme.json` (`h3`), not per-block overrides.
- No intro, subtitle, archive description or query loop: nothing else above or below the title. The empty `documents-page__content` group is the container DPD-002 (category buttons, directly under the h3) and DPD-016 (shelf) will fill.
- The `<main>` is the group's `tagName`; header and footer parts supply `<header>` / `<footer>`.

#### Other templates
All share the same shape: header part, a constrained `<main>` (top padding `spacing|60`, bottom `spacing|70`), footer part, no pattern references.
- `index.html`: a `core/query` (`inherit: true`) with `post-template` (`post-title` level 4 linked + `post-excerpt`), `query-pagination` (previous / numbers / next) and `query-no-results` ("Nothing found.").
- `archive.html`, `search.html`: the same loop preceded by `<!-- wp:query-title {"type":"archive","level":3} /-->` / `{"type":"search","level":3}`.
- `page.html`, `single.html`: `post-title` level 3 plus `post-content` (constrained layout).
- `404.html`: a level-3 heading "Page not found".

### Step E: navbar tests (theme folder)
The navbar tests live with the theme they test, in `wp-content/themes/devportfolio/tests/`, with their own `phpunit.xml.dist` (suite `devportfolio`, globs `tests/test-*.php`) and `tests/bootstrap.php`. The theme has no Composer dependencies: the runner is the plugin's PHPUnit (`../../plugins/custom-post-types-dp/vendor/bin/phpunit`) and the existing WP test library and test DB, so the one-time setup in AGENTS.md (plugin `composer install`, `install-wp-tests.sh`) is a prerequisite.

`tests/bootstrap.php` (same safety rules as the plugin's): fail with a clear message if the WP test lib or the plugin is missing; read `wp-tests-config.php` and **refuse to run if `DB_NAME` is `db` (the dev DB)** since the test lib drops every table; default `WP_TESTS_PHPUNIT_POLYFILLS_PATH` to the plugin's `vendor/yoast/phpunit-polyfills`; on `muplugins_loaded` require `custom-post-types-dp.php` (the nav links to its CPT archives); force `flush_rewrite_rules_hard` off so a flush never writes `.htaccess`; then load the test lib bootstrap. Variables are prefixed `$devportfolio_` to avoid globals clashes.

`tests/test-navigation.php`: class `Devportfolio_Test_Navigation extends WP_UnitTestCase` (no dependency on the plugin's test base classes), `@group theme`. `set_up()` calls `parent::set_up()` and `do_action( 'cptdp_register_post_types' )` (the test lib unregisters meta between tests), `markTestSkipped` if `parts/header.html` is missing, `require_once`s the theme's `functions.php` and re-adds the `render_block_data` filter (the test lib restores hooks after every test). `tear_down()` resets a non-empty permalink structure. The theme dir is `dirname( __DIR__ )`. Helper `nav_items( $url )` does `go_to( $url )`, renders `parts/header.html` with `do_blocks()`, and reads each `li.wp-block-navigation-link` with `DOMDocument`/`DOMXPath` into `label`, `href`, `aria_current` (the `<a>`) and `class` (the `<li>`). `active_labels()` returns the items with `aria-current="page"` or the `is-current` class.

Tests (15 tests, 42 assertions):
1. `test_links_and_order`: labels exactly `Home, Services, Documents, Videos, Collaterals, Testimonials, Contact`.
2. `test_links_point_to_the_right_targets`: Home `home_url('/')`, the four CPT links equal `get_post_type_archive_link()`, Testimonials `home_url('/#testimonials')`, Contact `home_url('/#contact')`.
3. `test_header_part_stores_no_hardcoded_domain`: header markup contains neither `http` nor `ddev.site`.
4. `test_urls_follow_the_site_url_with_pretty_permalinks`: after `set_permalink_structure( '/%postname%/' )`, re-registering and flushing, Documents and Videos hrefs are `home_url( '/documents/' )` / `home_url( '/videos/' )`.
5. `test_archive_page_marks_only_its_own_link_active` (data provider: service/Services, document/Documents, video/Videos, collateral/Collaterals): on that archive exactly one link is active, the matching one.
6. `test_documents_archive_underlines_documents_via_aria_current`: on `/documents/` Documents has `aria-current="page"`, Home has neither `aria-current` nor `is-current`.
7. `test_front_page_marks_only_home_active`: on `/` only Home, via the `is-current` class.
8. `test_anchor_links_are_never_active`: Testimonials and Contact are never active, on the front page or on an archive.
9. `test_unrelated_pages_mark_nothing_active`: a single post, a page and a search results page mark nothing.
10. `test_filter_expands_only_root_relative_navigation_links`: a root-relative link becomes `home_url()` and gets no class; a `post-type-archive` link with a wrong stored path resolves to `get_post_type_archive_link()`; an unknown post type falls back to `home_url()`; absolute URLs, links without `url` and non-navigation blocks are returned untouched.
11. `test_home_link_is_flagged_only_on_the_front_page`: `className` `is-current` on `/`, absent on an archive.
12. `test_theme_json_styles_inactive_grey_and_active_ink_with_bar`: the nav text colour is the `mid` slug (`#8a8a8a`), `contrast` is `#222222`; the css sets ink on `.current-menu-item` / `.is-current`, the 2px ink `border-bottom` on `a[aria-current='page']` / `.is-current > a`, weight 400; and it contains no link-level `color` rule (core overrides it), no `body.home` and no comma selector lists (the mangled-selector trap).

Write the tests before the filter's archive resolution to confirm they fail on the naive version: with the first filter (`home_url()` only) the archive and `aria-current` tests fail under the suite's plain permalinks, which is what exposed the need for `get_post_type_archive_link()`.

Run: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --testdox`. The plugin suite is unaffected (438 tests, 12 skipped) and is not part of this run.

### Step F: environment
1. `ddev snapshot` first. Check no saved template overrides exist for the old theme: `ddev wp post list --post_type=wp_template,wp_template_part` (a fresh dev DB is empty).
2. `ddev wp theme activate devportfolio` (per-database state; also in README setup).
3. The site may have plain permalinks (empty `permalink_structure`), in which case `/documents/` etc. all serve the home page. Set pretty permalinks: `ddev wp rewrite structure '/%postname%/' --hard`, then `ddev wp rewrite flush`.
4. `twentytwentyfive` stays in the repo, inactive and untouched (reference / fallback only).
5. No placeholder pages are needed. Until the Home page has sections with `id="testimonials"` and `id="contact"`, those two links just load the home page without scrolling.

### Step G: docs
- `AGENTS.md`: add the theme-tests command to the Tests section and a `tests/` + `phpunit.xml.dist` line to Theme architecture; Overview says the active theme is the standalone `devportfolio` (forked from Twenty Twenty-Five 1.5, which stays installed but unused) and that the Documents page is the archive template, not a Page (and why); remove the `npm install` / `npm run build` / `npm run watch` block from Commands and replace it with `ddev wp theme activate devportfolio`; add `wp-content/themes/devportfolio/` to the tracked list and "All themes except `twentytwentyfive` and `devportfolio`" to the ignore list; delete the `style.min.css` note; rewrite "Theme architecture" for the pruned theme (palette, fonts, 1040px width, the file list above, the one filter, Testimonials/Contacts placeholders); conventions: text domain `devportfolio`, function prefix `devportfolio_`, any new pattern slug prefixed `devportfolio/`, prefer `theme.json` / templates / parts over PHP/CSS, `twentytwentyfive` is reference only.
- `README.md`: theme bullet becomes `devportfolio` (standalone, tracked); drop the Node bullet; "Theme development" says there is no build step and gives the activation + permalink commands; tracked-files table lists both themes.

## Verification (navbar covered by the theme tests, Step E; the checks below cover what tests cannot)
1. `git status` shows the theme tracked and no `node_modules` / `style.min.css`; `ddev wp theme list` shows `devportfolio` active, no parent; `grep -rniE twentytwenty wp-content/themes/devportfolio` finds nothing except the credit lines; `ddev exec php -l wp-content/themes/devportfolio/functions.php`; `python3 -c "import json;json.load(open('theme.json'))"`.
2. Every URL returns the expected status with no PHP warnings: `/`, `/services/`, `/documents/`, `/videos/`, `/collaterals/`, a page, `/?s=x` all 200; an unknown URL 404; `/#testimonials` and `/#contact` load the home page (200) and only scroll once the Home sections with those ids exist. Archives with zero posts must still return 200.
3. `curl -sk https://devportfolio.ddev.site/documents/`: exactly one `<h3 class="wp-block-heading">Documents</h3>`, after the nav and before `documents-page__content`, no other text in `<main>`; seven nav links in order with correct hrefs (the last two `…/#testimonials` and `…/#contact`); exactly one link with `aria-current="page"` and it is Documents. On `/videos/` only Videos is current; on `/` only the `is-current` Home item.
4. Borders: the header group has only `border-bottom-style:solid` and the footer only `border-top-style:solid`, the other sides `none` (no box).
5. Visual check at desktop and ~390px: links centred, faded grey inactive links and an ink link with a 2px bar under it for the current page, mobile overlay opens, thin lines only under the nav and above the footer, title left-aligned, bold, large.
6. Site Editor: open "Archive: Document", the Header part and the Footer part with no block-validation warnings; if the editor normalises markup on save, copy it back from the Code editor into the files before committing.
7. Theme tests (15 tests) per Step E, and the plugin suite stays green: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit` (438 tests, 12 skipped).

## Open questions (defaults chosen)
1. **Testimonials / Contact targets.** Decided: in-page anchors on the Home page (`/#testimonials`, `/#contact`). The Home sections must use those exact ids.
2. **Mobile collapse breakpoint.** Wireframe collapses at 900px with a logo and "Book a call" button; core's overlay collapses at 600px with neither. Default: accept core behaviour for this ticket.
3. **"Contacts" vs "Contact".** The navbar uses "Contact" (wireframe), not the story's "Contacts".
4. **Permalinks.** Changing the dev DB to `/%postname%/` is required for the archive URLs; restore the snapshot to revert.

## Suggested commits
- `chore(gitignore): track devportfolio theme`
- `feat(theme): add devportfolio theme with main navigation and Documents page (DPH-002, DPD-000, DPD-001)`
- `docs(agents): note devportfolio theme, main navigation and Documents template`
- `docs(readme): update theme setup`
- `test(theme): cover devportfolio main navigation`
- `docs(plans): add DPH-002 DPD-000 DPD-001 plan`
