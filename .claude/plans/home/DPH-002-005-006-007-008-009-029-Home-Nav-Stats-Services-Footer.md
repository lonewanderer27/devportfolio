# Plan: Home navbar (900px), stats strip, "What I do" intro, three service rows, footer

Tickets **DPH-002** (navbar), **DPH-005** (stats strip), **DPH-006** (What I do intro), **DPH-007 / DPH-008 / DPH-009** (service rows 01, 02, 03), **DPH-029** (footer). Branch `feat/DPH-002-005-006-007-008-009-029` (already checked out, created from `main`).

Story source: `.claude/plans/home/Home-User-Stories-Revised.csv`. Visual reference: `.claude/plans/design-system.html` (sections *Navigation bar*, *Stats strip*, *Service list*, footer `.foot`) and `.claude/plans/home/home-lofi-wireframe.html`.

---

## 0. Rules for the implementing agent (read first, follow exactly)

1. **Do the steps in order, one at a time.** Each step says which file, what to do, and the exact content. Do not skip ahead, merge steps or "improve" anything.
2. **Copy code exactly** (tabs for PHP/CSS/HTML indentation, never spaces). A "find" text must match the file character for character, **including single quotes and spacing**. Use the Edit tool with that text as `old_string`. If Edit says the text is not found or not unique, **STOP and report**; do not retype it from memory or loosen it.
3. **After every phase run its Gate.** A Gate is a list of commands with the exact expected result. Continue only when every line matches. If one does not, fix only what the Gate points at, re-run it; if you cannot fix it in two tries, STOP and report the command and its full output.
4. **Stop and report** (do not guess) when: a Fact in section 3 is false; a file this plan says exists does not; a test fails for a reason this plan does not explain; the browser check numbers differ from the table.
5. **Do not commit or push** unless the user asks. When they do, use section 8 exactly.
6. Paths are relative to the repo root `/Users/adriane.puzon/Documents/PHP Projects/devportfolio` (the directory has a **space**: always quote paths in shell commands). Two shorthands are used everywhere:
   - `$T` = `wp-content/themes/devportfolio`
   - `$P` = `wp-content/plugins/custom-post-types-dp`
7. Text encoding is UTF-8. The character `©` is a real character in `render.php` (not `&copy;`).
8. Never edit: `wp-admin/`, `wp-includes/`, `wp-content/themes/twentytwentyfive/`, any `vendor/`.

### Commands used in the Gates (copy as is)

| Name | Command |
|---|---|
| THEME-TESTS | `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit` |
| THEME-TESTS-ONE | same as THEME-TESTS plus ` --filter <ClassName>` at the end |
| PLUGIN-TESTS | `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit` |
| JSON-OK | `python3 -c "import json,sys; json.load(open('wp-content/themes/devportfolio/theme.json')); print('theme.json OK')"` |

PHPUnit prints a last line like `OK (613 tests, 1234 assertions)`. "Green" means that line starts with `OK` and has **no** `Failures:`, `Errors:` or `Risky` section. The plugin run says `OK, but incomplete, skipped, or risky tests!` with 12 skipped: that is also green.

---

## 1. Goal and acceptance criteria

**DPH-002 Navbar** (one change to what is shipped, see Decision D1)
1. Desktop (901px and up): only the 7 links, centred, in this order: Home, Services, Documents, Videos, Collaterals, Testimonials, Contact. **No title, no logo, no button** (today the title shows on the left from 1024px and Contact is an outlined pill pinned right; both go).
2. Home is the active link (`is-current`) on the front page only; Testimonials and Contact are never active. (Already true and tested; must stay true.)
3. Documents, Videos, Collaterals open their archives; Services, Testimonials and Contact are `/#services`, `/#testimonials`, `/#contact` anchors (Services is the "What I do" section built here; Testimonials and Contact sections come in later tickets).
4. At **900px and below** the links collapse into the menu icon, the title (the site name, "Dave Portfolio") is on the left, and a **"Book a call" button** shows **on tablets only (601 to 900px)**. On phones (600px and below) the button is not in the bar; it is the last item inside the opened menu. "Book a call" links to `/#contact`.
5. Every link, the menu icon and the "Book a call" button have a tap area of at least **44 x 44px**.

**DPH-005 Stats strip**
6. Three numbers side by side under the hero (the hero is a later ticket; the strip is the first block in `<main>` until then), thin dividers, light weight: `+5 yrs` / `Experience`, `+40` / `Happy clients`, `24 hr` / `Typical reply time`.
7. Values and labels come from wp-admin **Statistics**: label = post title, value = post content (plain text, tags stripped), order = Order attribute. Changing a statistic changes the page; no code change.
8. The numbers are plain text: no link, no button, nothing happens on click.
9. The three columns stay side by side on phones (no wrapping at 320px).

**DPH-006 What I do intro**
10. Heading `What I do`, then exactly: `I help businesses grow their online presence through strategic content, marketing support and organized admin assistance, so you can focus on running your business.` The sentence appears only in this section (not in the hero).

**DPH-007 / 008 / 009 Service rows**
11. One row per **top-level published Service** (post parent 0), in Order then title order. Each shows the two-digit position `01`, `02`, `03`, the title, and the benefit line (post content, plain text). The seeded data gives exactly the story text (Social Media Marketing / Digital Marketing / Web Creation / Management).
12. **Grey tags, not a coloured pill:** the row's **child services** (post parent = the row's service, Order then title) as plain grey text separated by small dots. Not links.
13. **No arrow** at the end of the row and **no line under the rows**.
14. The **whole row is one link** to `{Services archive}#{service slug}` (Decision D4).
15. At 640px and below the tags are hidden and the row is one tap target at least 44px tall.

**DPH-029 Footer**
16. The footer (every page) shows `© {current year} {site name}` on the left (in 2026: `© 2026 Dave Portfolio`) and three links on the right: **LinkedIn, WhatsApp, Email**, from Settings > Contact (`cptdp_contact`: `linkedin_url`, `whatsapp_url`, `email`). A link with no value is not shown. LinkedIn and WhatsApp open in a new tab; Email is a `mailto:`.
17. On phones the same footer shows the links (they replace the hero pills hidden on phones, a later ticket); each link is 44px tall.
18. **No sticky "Hire me" bar** anywhere (nothing to build; a test proves nothing in templates/parts contains one).

## 2. Out of scope (do NOT build, even if it looks easy)

- The hero (DPH-001, 003, 004), Selected Work, Testimonials, Software Tools, the Contact section and scheduler (DPH-010 onwards). The template leaves one marked place for the hero.
- A Services page template. `/services/` keeps falling back to `archive.html`.
- Any JavaScript. Everything here is CSS plus the core Navigation block.
- Any plugin PHP change, new post type, setting or plugin version bump (only one seeder CSV value changes).
- Editing `twentytwentyfive`, WP core, or renaming/generalising any `document-*` block.

## 3. Facts already established (do not re-derive; verify only if a step fails)

- There is no `$T/templates/front-page.html`; `index.html` serves the front page today. A `front-page.html` template wins on the front page whatever the Reading setting is (no DB Page needed). `devportfolio_prepare_navigation_link()` in `$T/functions.php` already adds class `is-current` to the Home link when `is_front_page()`.
- `$T/parts/header.html` has a group `site-header__bar` (flex, `space-between`) holding `wp:site-title` (class `site-header__title`) then `wp:navigation` (`overlayMenu: "mobile"`, 7 `wp:navigation-link` items with root-relative URLs; the filter turns them into full URLs).
- Core's Navigation CSS switches to the menu icon at **600px**; this WordPress has **no breakpoint attribute** (`wp-includes/blocks/navigation/block.json` has only `overlayMenu`). So 900px needs CSS that overrides core's two `@media (min-width:600px)` rules: `.wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open)` (display block) and `.wp-block-navigation__responsive-container-open:not(.always-shown)` (display none). Styles of the opened overlay (`.is-menu-open`) are not width-limited, so a menu opened at 700px works.
- The header CSS is one long string at `styles.css` (top-level) in `$T/theme.json`; the nav link CSS is the string at `styles.blocks["core/navigation"].css`. The "find" strings below are substrings of that JSON (single quotes only, no JSON escaping needed).
- Core layout CSS gives `.wp-block-buttons` `display: flex` through `.is-layout-flex` (one class of specificity). So the header button's show/hide rules use `.wp-block-buttons.site-header__book` (two classes) to win.
- Tests that pin what this plan changes (Phase 1 rewrites them): in `$T/tests/test-navigation.php` the method `test_header_shows_the_site_title_on_phones_and_large_screens` and the helper `nav_items()`; in `$T/tests/test-design-system.php` the last three methods (`test_large_screen_header_is_as_wide_as_the_documents_page_with_the_title_on_the_left`, `test_contact_is_pinned_right_as_an_outlined_pill_from_1024px`, `test_title_is_hidden_only_between_phone_and_large_screen`). Everything else in those files stays.
- `statistic` CPT: title, editor (content), page-attributes (menu order). Seeder `statistics` reads `$P/data/seeders/statistics.csv`; its first row value is `<b>+5 yrs</b>` (wrong for "light weight"). A seeder skips an existing title, so an existing DB row keeps its markup; the block strips tags, so it renders right either way.
- `service` CPT: hierarchical, title, editor (content), page-attributes, archive `/services/`. Seeder `services` creates the 3 top-level services (content = benefit line) and 13 child services (content empty), with `menu_order` from the CSV.
- `cptdp_get_contact( ?string $key )` returns `email`, `whatsapp_url`, `linkedin_url`. Always guard with `function_exists( 'cptdp_get_contact' )`.
- `devportfolio_published_ids( $post_type, $args )` (in `$T/functions.php`) lists published post IDs, memoised; extra `$args` are merged over `post_status=publish, posts_per_page=-1, fields=ids`. `get_block_wrapper_attributes()` builds a dynamic block's wrapper. Blocks are registered in `devportfolio_register_blocks()` on `init`.
- Theme tokens: palette slugs `base`, `contrast`, `mid`, `line`, `fill`; `--wp--custom--edge` is the hairline colour; font-size preset `medium` exists; `scroll-padding-top` already offsets anchors under the sticky header.
- Test classes extend `WP_UnitTestCase`, are named `Devportfolio_Test_*`, carry `@group theme`, and in `set_up()` call `devportfolio_memo_flush()`, `do_action( 'cptdp_register_post_types' )`, `require_once` the theme `functions.php` and register the theme blocks (see any existing file, e.g. `$T/tests/test-document-shelf.php`). The test library rolls the database back after each test.
- **Baseline: theme suite = 606 tests** (AGENTS.md). Expected after each phase is in its Gate.

## 4. Decisions (made; do not reopen)

**D1. Follow the stories where they differ from what is shipped.** DPD-034 gave desktop a left title and an outlined Contact pill; DPH-002 AC1 and the design system say links only. Both are removed, the grid layout that served them goes, and the pinned tests are rewritten. Revert = restoring one CSS block.

**D2. "Book a call" is a core Button in the header part** (so it sits left of the menu icon on tablets). A core Button's URL is not expanded by the nav filter, so a small `render_block_core/button` filter expands a root-relative `href` to `home_url()` for the button whose class contains `site-header__book-button`, only. On phones the same label is an 8th navigation item (class `site-header__book-item`) shown only in the opened menu at 600px and below.

**D3. Dynamic blocks** for what reads the database: `devportfolio/stats-strip`, `devportfolio/service-list`, `devportfolio/site-footer` (each `block.json` + `render.php` + `style.css`). The "What I do" heading and sentence are static template text.

**D4. Row link = `{get_post_type_archive_link( 'service' )}#{post_name}`.** No Services page exists yet; the hash degrades to the plain archive. The future Services page ticket must give each item `id="{slug}"`.

**D5. Stats show at most 3** statistics (the layout is 3 columns by design).

**D6. Service rows have no lines at all** (AC: "no line under the rows"); separation is padding and a hover tint. Hairlines would be one `border-bottom` rule in `service-list/style.css`.

**D7. Dynamic year** (`wp_date( 'Y' )`), so in 2026 it reads exactly `© 2026 Dave Portfolio`.

**D8. Services nav link stays `/#services`** (pinned by tests; there is no Services page to open).

---

## 5. Phases

### Phase 0: environment and baseline

1. Run `git status` and `git branch --show-current`. Expected branch: `feat/DPH-002-005-006-007-008-009-029`. Untracked plan files of other tickets are normal: never stage them.
2. Run `ddev start`. Then `ddev wp option get blogname`. If the output is not exactly `Dave Portfolio`, run `ddev wp option update blogname "Dave Portfolio"`.
3. Run `ddev wp cptdp seed statistics` and `ddev wp cptdp seed services` (both idempotent; "skipped" lines are fine).
4. Run `ddev wp post list --post_type=service --fields=ID,post_title,post_parent,menu_order --format=table`. Expected: 3 rows with `post_parent` 0 (Social Media Marketing, Digital Marketing, Web Creation / Management) and 13 rows with a non-zero `post_parent`.
5. Run THEME-TESTS.

**Gate 0:** step 5 ends with `OK (606 tests, ...)`. If the WP test library is missing (error about `wordpress-tests-lib`), run once: `ddev composer --working-dir=wp-content/plugins/custom-post-types-dp install` and `ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"`, then re-run. If it still is not 606, STOP and report.

---

### Phase 1: header at 900px with "Book a call" (DPH-002)

**Step 1.1. Edit `$T/parts/header.html`** (two edits).

Edit A. old_string (exactly one line, one leading tab):
```
	<!-- wp:site-title {"level":0,"className":"site-header__title","style":{"typography":{"fontWeight":"700"}}} /-->
```
new_string:
```
	<!-- wp:site-title {"level":0,"className":"site-header__title","style":{"typography":{"fontWeight":"700"}}} /-->

	<!-- wp:buttons {"className":"site-header__book"} -->
	<div class="wp-block-buttons site-header__book"><!-- wp:button {"className":"site-header__book-button"} -->
	<div class="wp-block-button site-header__book-button"><a class="wp-block-button__link wp-element-button" href="/#contact">Book a call</a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
```

Edit B. old_string (two leading tabs):
```
		<!-- wp:navigation-link {"label":"Contact","url":"/#contact","kind":"custom","isTopLevelLink":true} /-->
```
new_string:
```
		<!-- wp:navigation-link {"label":"Contact","url":"/#contact","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Book a call","url":"/#contact","kind":"custom","className":"site-header__book-item","isTopLevelLink":true} /-->
```

**Step 1.2. Edit `$T/theme.json`**, four edits. After all four run JSON-OK; it must print `theme.json OK`.

Edit A (title rule). old_string:
```
@media (min-width: 600px) and (max-width: 1023px) { .site-header__title { display: none; } }
```
new_string:
```
.site-header__title { display: none; } @media (max-width: 900px) { .site-header__title { display: block; } }
```

Edit B (remove the desktop grid and the Contact pill; keep the 1440px width). old_string (one line):
```
@media (min-width: 1024px) { header.wp-block-template-part > .wp-block-group { --wp--style--global--content-size: 1440px; } .site-header__bar.is-layout-flex { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; } .site-header__bar::after { content: ''; } .site-header__bar { position: relative; } .site-header__bar .wp-block-navigation, .site-header__bar .wp-block-navigation .wp-block-navigation__responsive-container:not(.is-menu-open), .site-header__bar .wp-block-navigation__responsive-dialog { position: static; } .site-header__bar .wp-block-navigation-item:last-child { position: absolute; right: 0; top: 50%; transform: translateY(-50%); } .site-header__bar .wp-block-navigation-item:last-child > a { display: flex; align-items: center; padding: 0 18px; border: 1.5px solid var(--wp--preset--color--contrast); border-radius: 9999px; } }
```
new_string:
```
@media (min-width: 1024px) { header.wp-block-template-part > .wp-block-group { --wp--style--global--content-size: 1440px; } }
```

Edit C (the tail of the string; the closing `"` of the JSON string is NOT part of old/new, leave it). old_string:
```
@media (max-width: 599px) { .site-header__bar > .wp-block-navigation { flex: none; } }
```
new_string (one single line):
```
.wp-block-buttons.site-header__book { display: none; margin: 0; } .site-header__bar .wp-block-navigation-item.site-header__book-item { display: none; } .site-header__book .wp-block-button__link { position: relative; display: inline-flex; align-items: center; min-height: 34px; padding: 0 14px; border: 0; border-radius: 9999px; background: var(--wp--preset--color--contrast); color: var(--wp--preset--color--base); font-size: 13.5px; font-weight: 600; text-decoration: none; white-space: nowrap; } .site-header__book .wp-block-button__link::after { content: ''; position: absolute; inset: -5px 0; } @media (max-width: 900px) { .site-header__bar { gap: 8px; } .site-header__title { margin-right: auto; } .site-header__bar > .wp-block-navigation { flex: none; } .site-header__bar .wp-block-navigation__responsive-container:not(.is-menu-open):not(.hidden-by-default) { display: none; } .site-header__bar .wp-block-navigation__responsive-container-open:not(.always-shown) { display: flex; } } @media (min-width: 601px) and (max-width: 900px) { .wp-block-buttons.site-header__book { display: flex; } } @media (max-width: 600px) { .wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item.site-header__book-item { display: block; } .wp-block-navigation__responsive-container.is-menu-open .site-header__book-item > a { background: var(--wp--preset--color--contrast); color: var(--wp--preset--color--base); border-radius: 9999px; } }
```

Edit D (44px tap area for nav links; this one is inside `styles.blocks["core/navigation"].css`). old_string:
```
& a:hover { text-decoration: none; }
```
new_string:
```
& a:hover { text-decoration: none; } & a { position: relative; } & a::after { content: ''; position: absolute; inset: -5px 0; }
```
(The pill stays 34px tall; the invisible `::after` extends the tap area to 44px.)

**Step 1.3. Edit `$T/functions.php`.** old_string:
```
add_filter( 'render_block_data', 'devportfolio_prepare_navigation_link' );
```
new_string:
```
add_filter( 'render_block_data', 'devportfolio_prepare_navigation_link' );

if ( ! function_exists( 'devportfolio_expand_book_button_link' ) ) :
	/**
	 * Expands the root-relative link of the header's "Book a call" button (DPH-002).
	 *
	 * Navigation links are expanded by devportfolio_prepare_navigation_link(); a core Button keeps its URL in
	 * its markup, so it is rewritten here, for that one button only.
	 *
	 * @since 0.34.0
	 *
	 * @param string $content Rendered button.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	function devportfolio_expand_book_button_link( $content, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'site-header__book-button' ) ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag( 'a' ) ) {
			$href = (string) $processor->get_attribute( 'href' );
			if ( str_starts_with( $href, '/' ) && ! str_starts_with( $href, '//' ) ) {
				$processor->set_attribute( 'href', home_url( $href ) );
			}
		}

		return $processor->get_updated_html();
	}
endif;
add_filter( 'render_block_core/button', 'devportfolio_expand_book_button_link', 10, 2 );
```

**Step 1.4. Update the old tests that pinned the removed design** (do this in the same commit as Steps 1.1 to 1.3, otherwise the suite is red).

`$T/tests/test-navigation.php`, three edits:

Edit A. old_string:
```
		$this->assertStringContainsString( '@media (min-width: 600px) and (max-width: 1023px) { .site-header__title { display: none; } }', $css );
```
new_string:
```
		$this->assertStringContainsString( '.site-header__title { display: none; } @media (max-width: 900px) { .site-header__title { display: block; } }', $css );
```
Edit B. old_string:
```
		// From 1024px the title sits left with the links centred (equal side columns).
		$this->assertStringContainsString( 'grid-template-columns: 1fr auto 1fr', $css );
		// ...and the last link (Contact) leaves the row and sits right as an outlined pill; still the seventh link in the markup.
		$this->assertStringContainsString( '.site-header__bar .wp-block-navigation-item:last-child { position: absolute; right: 0;', $css );
```
new_string:
```
		// Desktop is links only: no grid for a side title, and Contact is a plain link, not a pinned pill.
		$this->assertStringNotContainsString( 'grid-template-columns: 1fr auto 1fr', $css );
		$this->assertStringNotContainsString( 'wp-block-navigation-item:last-child', $css );
```
Edit C (rename the method). old_string:
```
	public function test_header_shows_the_site_title_on_phones_and_large_screens(): void {
```
new_string:
```
	public function test_header_shows_the_site_title_at_900px_and_below_only(): void {
```
Edit D (the helper must skip the phone-only item). old_string:
```
		foreach ( ( new DOMXPath( $dom ) )->query( '//li[contains(@class,"wp-block-navigation-link")]' ) as $li ) {
			$a       = $li->getElementsByTagName( 'a' )->item( 0 );
```
new_string:
```
		foreach ( ( new DOMXPath( $dom ) )->query( '//li[contains(@class,"wp-block-navigation-link")]' ) as $li ) {
			// The 8th item ("Book a call") is shown in the phone menu only, it is not one of the 7 links.
			if ( str_contains( $li->getAttribute( 'class' ), 'site-header__book-item' ) ) {
				continue;
			}

			$a       = $li->getElementsByTagName( 'a' )->item( 0 );
```

`$T/tests/test-design-system.php`, three edits (these are the last three methods of the class).

Edit A. old_string:
```
	public function test_large_screen_header_is_as_wide_as_the_documents_page_with_the_title_on_the_left(): void {
		$wide = $this->media( $this->global_css(), '(min-width: 1024px)' );

		$this->assertStringContainsString( '--wp--style--global--content-size: 1440px', $wide );
		$this->assertStringContainsString( 'grid-template-columns: 1fr auto 1fr', $wide );
		$this->assertStringContainsString( ".site-header__bar::after { content: ''; }", $wide );
	}
```
new_string:
```
	public function test_large_screen_header_is_as_wide_as_the_documents_page_and_has_no_grid(): void {
		$wide = $this->media( $this->global_css(), '(min-width: 1024px)' );

		$this->assertStringContainsString( '--wp--style--global--content-size: 1440px', $wide );
		$this->assertStringNotContainsString( 'grid-template-columns', $wide );
	}
```
Edit B. old_string: the whole method `test_contact_is_pinned_right_as_an_outlined_pill_from_1024px` (from `	public function test_contact_is_pinned_right_as_an_outlined_pill_from_1024px(): void {` through its closing `	}`; open the file, select exactly that method). new_string:
```
	public function test_contact_is_a_plain_link_with_no_pill_on_large_screens(): void {
		$css = $this->global_css();

		$this->assertStringNotContainsString( 'wp-block-navigation-item:last-child', $css );
		$this->assertStringNotContainsString( 'position: absolute; right: 0; top: 50%', $css );
	}
```
Edit C. old_string:
```
	public function test_title_is_hidden_only_between_phone_and_large_screen(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '@media (min-width: 600px) and (max-width: 1023px) { .site-header__title { display: none; } }', $css );
```
new_string:
```
	public function test_title_shows_only_at_900px_and_below(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '.site-header__title { display: none; } @media (max-width: 900px) { .site-header__title { display: block; } }', $css );
```

**Step 1.5. Create `$T/tests/test-header-book-call.php`** (7 new tests), full content:

```php
<?php
/**
 * Tests for the 900px header and the "Book a call" button (DPH-002).
 *
 * Markup is rendered with do_blocks(). The CSS is checked as text, since there is no browser runner.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Header_Book_Call extends WP_UnitTestCase {

	/**
	 * The 7 real links, in order.
	 *
	 * @var string[]
	 */
	private const LABELS = array( 'Home', 'Services', 'Documents', 'Videos', 'Collaterals', 'Testimonials', 'Contact' );

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		do_action( 'cptdp_register_post_types' );

		// The test library restores hooks after every test: add the theme filters again.
		require_once $this->theme_dir() . '/functions.php';
		add_filter( 'render_block_data', 'devportfolio_prepare_navigation_link' );
		add_filter( 'render_block_core/button', 'devportfolio_expand_book_button_link', 10, 2 );
	}

	private function read( string $path ): string {
		$file = $this->theme_dir() . '/' . $path;
		$this->assertFileExists( $file );

		return (string) file_get_contents( $file );
	}

	private function theme_json(): array {
		$json = json_decode( $this->read( 'theme.json' ), true );
		$this->assertIsArray( $json, 'theme.json must be valid JSON.' );

		return $json;
	}

	private function global_css(): string {
		return (string) ( $this->theme_json()['styles']['css'] ?? '' );
	}

	public function test_book_button_is_in_the_bar_with_an_expanded_link(): void {
		$this->go_to( home_url( '/' ) );

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . do_blocks( $this->read( 'parts/header.html' ) ) );
		libxml_clear_errors();

		$links = ( new DOMXPath( $dom ) )->query( '//a[contains(@class,"wp-block-button__link")]' );

		$this->assertSame( 1, $links->length );
		$this->assertSame( 'Book a call', trim( $links->item( 0 )->textContent ) );
		$this->assertSame( home_url( '/#contact' ), $links->item( 0 )->getAttribute( 'href' ) );
	}

	public function test_only_the_book_button_is_expanded(): void {
		$html = '<div class="wp-block-button"><a href="/x">A</a></div>';

		$other = array( 'attrs' => array( 'className' => 'other' ) );
		$this->assertSame( $html, devportfolio_expand_book_button_link( $html, $other ) );

		$book = array( 'attrs' => array( 'className' => 'site-header__book-button' ) );
		$this->assertStringContainsString( 'href="' . home_url( '/x' ) . '"', devportfolio_expand_book_button_link( $html, $book ) );

		$absolute = '<div class="wp-block-button"><a href="https://example.org/">A</a></div>';
		$this->assertSame( $absolute, devportfolio_expand_book_button_link( $absolute, $book ) );

		$protocol_relative = '<div class="wp-block-button"><a href="//cdn.example.org/x">A</a></div>';
		$this->assertSame( $protocol_relative, devportfolio_expand_book_button_link( $protocol_relative, $book ) );
	}

	public function test_phone_item_is_a_hidden_eighth_link_to_contact(): void {
		$header = $this->read( 'parts/header.html' );

		$this->assertSame( 8, preg_match_all( '/<!-- wp:navigation-link (\{.*?\}) \/-->/', $header, $matches ) );

		$items = array_map(
			static function ( $json ) {
				return json_decode( $json, true );
			},
			$matches[1]
		);

		$this->assertSame( self::LABELS, array_slice( wp_list_pluck( $items, 'label' ), 0, 7 ) );
		$this->assertSame( 'Book a call', $items[7]['label'] );
		$this->assertSame( 'site-header__book-item', $items[7]['className'] );
		$this->assertSame( '/#contact', $items[7]['url'] );
	}

	public function test_menu_collapses_at_900px_with_the_override_selectors(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '@media (max-width: 900px) {', $css );
		$this->assertStringContainsString( '.site-header__bar .wp-block-navigation__responsive-container:not(.is-menu-open):not(.hidden-by-default) { display: none; }', $css );
		$this->assertStringContainsString( '.site-header__bar .wp-block-navigation__responsive-container-open:not(.always-shown) { display: flex; }', $css );
	}

	public function test_book_button_is_tablet_only_and_the_menu_item_phone_only(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '.wp-block-buttons.site-header__book { display: none; margin: 0; }', $css );
		$this->assertStringContainsString( '.site-header__bar .wp-block-navigation-item.site-header__book-item { display: none; }', $css );
		$this->assertStringContainsString( '@media (min-width: 601px) and (max-width: 900px) { .wp-block-buttons.site-header__book { display: flex; } }', $css );
		$this->assertStringContainsString( '@media (max-width: 600px) { .wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item.site-header__book-item { display: block; }', $css );
	}

	public function test_tap_areas_reach_44px(): void {
		$json = $this->theme_json();
		$nav  = (string) ( $json['styles']['blocks']['core/navigation']['css'] ?? '' );
		$css  = $this->global_css();

		// Links: 34px pill plus a 5px invisible extension above and below.
		$this->assertStringContainsString( "& a::after { content: ''; position: absolute; inset: -5px 0; }", $nav );
		$this->assertStringContainsString( 'min-height: 34px', $nav );

		// Book a call: 34px plus 5px above and below.
		$this->assertStringContainsString( 'min-height: 34px', $css );
		$this->assertStringContainsString( ".site-header__book .wp-block-button__link::after { content: ''; position: absolute; inset: -5px 0; }", $css );

		// Menu icon: 36px plus 4px above and below.
		$this->assertStringContainsString( 'width: 36px; height: 36px', $css );
		$this->assertStringContainsString( ".wp-block-navigation__responsive-container-open::after { content: ''; position: absolute; inset: -4px; }", $css );
	}

	public function test_there_is_no_hire_me_bar_in_any_template_or_part(): void {
		$files = array_merge(
			(array) glob( $this->theme_dir() . '/templates/*.html' ),
			(array) glob( $this->theme_dir() . '/parts/*.html' )
		);

		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$this->assertStringNotContainsStringIgnoringCase( 'hire me', (string) file_get_contents( $file ), basename( $file ) );
		}
	}
}
```

**Gate 1.**
1. JSON-OK prints `theme.json OK`.
2. THEME-TESTS ends with `OK (613 tests, ...)` (606 + 7 new; the rewritten tests keep the count).
3. `grep -c "site-header__book" wp-content/themes/devportfolio/theme.json` prints a number of at least 1 (sanity).
4. Browser check (section 6) for the **header** part of the table, at the 8 widths. Every row must match. If at 700px the 7 links are still inline, the selector in Edit C did not out-rank core: STOP and report the computed `display` of `.wp-block-navigation__responsive-container` (do not add `!important`).

---

### Phase 2: footer (DPH-029)

**Step 2.1. Create `$T/blocks/site-footer/block.json`:**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/site-footer",
	"title": "Site Footer",
	"category": "widgets",
	"description": "Copyright on the left, LinkedIn, WhatsApp and Email links from Settings > Contact on the right.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css"
}
```

**Step 2.2. Create `$T/blocks/site-footer/render.php`:**
```php
<?php
/**
 * Server render of the footer content (DPH-029).
 *
 * Copyright text plus the contact links of Settings > Contact. A link without a value is not shown.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_contact = function_exists( 'cptdp_get_contact' ) ? cptdp_get_contact() : array();
$devportfolio_links   = array();

if ( ! empty( $devportfolio_contact['linkedin_url'] ) ) {
	$devportfolio_links[] = array(
		'label'    => __( 'LinkedIn', 'devportfolio' ),
		'href'     => $devportfolio_contact['linkedin_url'],
		'external' => true,
	);
}

if ( ! empty( $devportfolio_contact['whatsapp_url'] ) ) {
	$devportfolio_links[] = array(
		'label'    => __( 'WhatsApp', 'devportfolio' ),
		'href'     => $devportfolio_contact['whatsapp_url'],
		'external' => true,
	);
}

if ( ! empty( $devportfolio_contact['email'] ) ) {
	$devportfolio_links[] = array(
		'label'    => __( 'Email', 'devportfolio' ),
		'href'     => 'mailto:' . $devportfolio_contact['email'],
		'external' => false,
	);
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'site-footer' ) );
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<p class="site-footer__copy"><?php echo esc_html( sprintf( '© %1$s %2$s', wp_date( 'Y' ), get_bloginfo( 'name' ) ) ); ?></p>
	<?php if ( ! empty( $devportfolio_links ) ) : ?>
		<nav class="site-footer__nav" aria-label="<?php echo esc_attr__( 'Contact links', 'devportfolio' ); ?>">
			<ul class="site-footer__links">
				<?php foreach ( $devportfolio_links as $devportfolio_link ) : ?>
					<li>
						<a class="site-footer__link" href="<?php echo esc_url( $devportfolio_link['href'] ); ?>"<?php echo $devportfolio_link['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $devportfolio_link['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</div>
```

**Step 2.3. Create `$T/blocks/site-footer/style.css`:**
```css
.site-footer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 8px 24px;
	font-size: 14px;
	color: var(--wp--preset--color--mid);
}

.site-footer__copy {
	margin: 0;
}

.site-footer__links {
	display: flex;
	flex-wrap: wrap;
	gap: 0 16px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.site-footer__link {
	display: inline-flex;
	align-items: center;
	min-height: 44px;
	padding: 0 4px;
	color: var(--wp--preset--color--contrast);
	text-decoration: none;
}

.site-footer__link:hover {
	text-decoration: underline;
}

@media (max-width: 480px) {
	.site-footer {
		flex-direction: column;
		align-items: flex-start;
	}
}
```

**Step 2.4. Edit `$T/parts/footer.html`.** old_string (one leading tab):
```
	<!-- wp:site-title {"level":0,"fontSize":"small","textColor":"mid"} /-->
```
new_string:
```
	<!-- wp:devportfolio/site-footer /-->
```

**Step 2.5. Edit `$T/functions.php`** (register the block). old_string:
```
		register_block_type( __DIR__ . '/blocks/video-modal' );
```
new_string:
```
		register_block_type( __DIR__ . '/blocks/video-modal' );
		register_block_type( __DIR__ . '/blocks/site-footer' );
```

**Step 2.6. Create `$T/tests/test-site-footer.php`** (7 tests):
```php
<?php
/**
 * Tests for the footer block (DPH-029).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Site_Footer extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/site-footer' ) ) {
			devportfolio_register_blocks();
		}

		$this->set_contact( array() );
	}

	/**
	 * Store the contact settings (empty values by default, then the given ones).
	 *
	 * @param array $values Keys email, whatsapp_url, linkedin_url.
	 */
	private function set_contact( array $values ): void {
		update_option(
			'cptdp_contact',
			array_merge(
				array(
					'email'        => '',
					'whatsapp_url' => '',
					'linkedin_url' => '',
				),
				$values
			)
		);
	}

	private function full_contact(): array {
		return array(
			'email'        => 'dave@example.com',
			'whatsapp_url' => 'https://wa.me/61400000000',
			'linkedin_url' => 'https://www.linkedin.com/in/dave',
		);
	}

	private function render(): string {
		return do_blocks( '<!-- wp:devportfolio/site-footer /-->' );
	}

	/**
	 * The footer links as DOM elements.
	 *
	 * @param string $html Rendered footer.
	 * @return DOMElement[]
	 */
	private function links( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		$found = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//a[contains(@class,"site-footer__link")]' ) as $a ) {
			$found[] = $a;
		}

		return $found;
	}

	public function test_copyright_uses_the_year_and_the_site_name(): void {
		$this->set_contact( $this->full_contact() );

		$this->assertStringContainsString( '© ' . wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ), $this->render() );
	}

	public function test_three_links_in_order_with_targets(): void {
		$this->set_contact( $this->full_contact() );
		$links = $this->links( $this->render() );

		$this->assertCount( 3, $links );
		$this->assertSame( array( 'LinkedIn', 'WhatsApp', 'Email' ), array_map( static fn( $a ) => trim( $a->textContent ), $links ) );
		$this->assertSame( 'https://www.linkedin.com/in/dave', $links[0]->getAttribute( 'href' ) );
		$this->assertSame( 'https://wa.me/61400000000', $links[1]->getAttribute( 'href' ) );
		$this->assertSame( 'mailto:dave@example.com', $links[2]->getAttribute( 'href' ) );
	}

	public function test_external_links_open_in_a_new_tab_and_email_does_not(): void {
		$this->set_contact( $this->full_contact() );
		$links = $this->links( $this->render() );

		foreach ( array( 0, 1 ) as $i ) {
			$this->assertSame( '_blank', $links[ $i ]->getAttribute( 'target' ) );
			$this->assertSame( 'noopener noreferrer', $links[ $i ]->getAttribute( 'rel' ) );
		}

		$this->assertSame( '', $links[2]->getAttribute( 'target' ) );
		$this->assertSame( '', $links[2]->getAttribute( 'rel' ) );
	}

	public function test_a_link_without_a_value_is_not_shown(): void {
		$values                 = $this->full_contact();
		$values['linkedin_url'] = '';
		$this->set_contact( $values );

		$this->assertSame( array( 'WhatsApp', 'Email' ), array_map( static fn( $a ) => trim( $a->textContent ), $this->links( $this->render() ) ) );

		$this->set_contact( array() );
		$html = $this->render();
		$this->assertSame( array(), $this->links( $html ) );
		$this->assertStringNotContainsString( '<nav', $html );
		$this->assertStringContainsString( '© ' . wp_date( 'Y' ), $html );
	}

	public function test_urls_are_escaped(): void {
		$this->set_contact( array( 'email' => 'a"b@example.com' ) );

		$this->assertStringNotContainsString( 'a"b', $this->render() );
	}

	public function test_footer_part_uses_the_block_and_keeps_its_rule(): void {
		$footer = (string) file_get_contents( $this->theme_dir() . '/parts/footer.html' );

		$this->assertStringContainsString( '<!-- wp:devportfolio/site-footer /-->', $footer );
		$this->assertStringNotContainsString( 'wp:site-title', $footer );
		$this->assertStringContainsString( 'border-top-color:var(--wp--preset--color--line)', $footer );
	}

	public function test_css_contract(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/site-footer/style.css' );

		$this->assertMatchesRegularExpression( '/\.site-footer__link \{[^}]*min-height: 44px;/', $css );
		$this->assertStringContainsString( '@media (max-width: 480px)', $css );
	}
}
```

**Gate 2.**
1. THEME-TESTS ends with `OK (620 tests, ...)`.
2. Browser check (section 6), footer rows of the table.

---

### Phase 3: stats strip (DPH-005)

**Step 3.1. Create `$T/blocks/stats-strip/block.json`:**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/stats-strip",
	"title": "Stats Strip",
	"category": "widgets",
	"description": "Three key numbers from the Statistics post type, side by side.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css"
}
```

**Step 3.2. Create `$T/blocks/stats-strip/render.php`:**
```php
<?php
/**
 * Server render of the stats strip (DPH-005): the first three published Statistics.
 *
 * Label = title, value = content as plain text. Plain text only: nothing here is a link or a control.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'statistic',
	array(
		'posts_per_page' => 3,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

$devportfolio_stats = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_value = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $devportfolio_id ) ) );
	if ( '' === $devportfolio_value ) {
		continue;
	}

	$devportfolio_stats[] = array(
		'value' => $devportfolio_value,
		'label' => get_the_title( $devportfolio_id ),
	);
}

if ( empty( $devportfolio_stats ) ) {
	return;
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'stats-strip' ) );
?>
<ul <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php foreach ( $devportfolio_stats as $devportfolio_stat ) : ?>
		<li class="stats-strip__item"><b class="stats-strip__value"><?php echo esc_html( $devportfolio_stat['value'] ); ?></b><span class="stats-strip__label"><?php echo esc_html( $devportfolio_stat['label'] ); ?></span></li>
	<?php endforeach; ?>
</ul>
```

**Step 3.3. Create `$T/blocks/stats-strip/style.css`:**
```css
.stats-strip {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	margin: 0;
	padding: 0;
	list-style: none;
	border-top: 1px solid var(--wp--custom--edge);
	border-bottom: 1px solid var(--wp--custom--edge);
}

.stats-strip__item {
	display: grid;
	gap: 2px;
	padding: 22px 24px;
}

.stats-strip__item + .stats-strip__item {
	border-left: 1px solid var(--wp--custom--edge);
}

.stats-strip__value {
	font-size: clamp(30px, 4vw, 44px);
	font-weight: 200;
	letter-spacing: -0.04em;
	line-height: 1.05;
	font-variant-numeric: tabular-nums;
}

.stats-strip__label {
	font-size: 14px;
	color: var(--wp--preset--color--mid);
}

@media (max-width: 520px) {
	.stats-strip__item {
		padding: 16px 12px;
	}

	.stats-strip__label {
		font-size: 12.5px;
	}
}
```

**Step 3.4. Edit `$T/functions.php`.** old_string:
```
		register_block_type( __DIR__ . '/blocks/site-footer' );
```
new_string:
```
		register_block_type( __DIR__ . '/blocks/site-footer' );
		register_block_type( __DIR__ . '/blocks/stats-strip' );
```

**Step 3.5. Edit `$P/data/seeders/statistics.csv`.** old_string: `1,Experience,<b>+5 yrs</b>` new_string: `1,Experience,+5 yrs`. Then run PLUGIN-TESTS (see Gate 3).

**Step 3.6. Create `$T/tests/test-stats-strip.php`** (7 tests):
```php
<?php
/**
 * Tests for the Home stats strip (DPH-005).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Stats_Strip extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/stats-strip' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	/**
	 * Create a statistic.
	 *
	 * @param string $title   Label.
	 * @param string $value   Value (post content).
	 * @param int    $order   Menu order.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	private function make_stat( string $title, string $value, int $order, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'statistic',
				'post_title'   => $title,
				'post_content' => $value,
				'menu_order'   => $order,
				'post_status'  => $status,
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/stats-strip /-->' );
	}

	/**
	 * Value and label of each rendered item.
	 *
	 * @param string $html Rendered block.
	 * @return array<int, array{0:string,1:string}>
	 */
	private function items( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		$items = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//li[contains(@class,"stats-strip__item")]' ) as $li ) {
			$items[] = array(
				trim( $li->getElementsByTagName( 'b' )->item( 0 )->textContent ),
				trim( $li->getElementsByTagName( 'span' )->item( 0 )->textContent ),
			);
		}

		return $items;
	}

	public function test_three_statistics_render_in_menu_order(): void {
		// Created out of order on purpose.
		$this->make_stat( 'Typical reply time', '24 hr', 3 );
		$this->make_stat( 'Experience', '+5 yrs', 1 );
		$this->make_stat( 'Happy clients', '+40', 2 );

		$this->assertSame(
			array(
				array( '+5 yrs', 'Experience' ),
				array( '+40', 'Happy clients' ),
				array( '24 hr', 'Typical reply time' ),
			),
			$this->items( $this->render() )
		);
	}

	public function test_markup_in_the_value_is_stripped(): void {
		$this->make_stat( 'Experience', '<b>+5 yrs</b>', 1 );
		$html = $this->render();

		$this->assertSame( array( array( '+5 yrs', 'Experience' ) ), $this->items( $html ) );
		$this->assertStringNotContainsString( '<b>+5', $html );
		$this->assertStringNotContainsString( '&lt;b', $html );
	}

	public function test_only_the_first_three_render(): void {
		foreach ( array( 1, 2, 3, 4 ) as $n ) {
			$this->make_stat( 'Stat ' . $n, (string) $n, $n );
		}

		$this->assertCount( 3, $this->items( $this->render() ) );
	}

	public function test_drafts_and_empty_values_are_skipped(): void {
		$this->make_stat( 'Draft', '99', 1, 'draft' );
		$this->make_stat( 'Empty', '', 2 );
		$this->make_stat( 'Real', '7', 3 );

		$this->assertSame( array( array( '7', 'Real' ) ), $this->items( $this->render() ) );
	}

	public function test_nothing_renders_without_statistics(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_the_numbers_are_not_interactive(): void {
		$this->make_stat( 'Experience', '+5 yrs', 1 );
		$html = $this->render();

		foreach ( array( '<a ', '<button', 'onclick', 'tabindex' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html );
		}
	}

	public function test_css_contract(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/stats-strip/style.css' );

		$this->assertStringContainsString( 'grid-template-columns: repeat(3, minmax(0, 1fr))', $css );
		$this->assertStringContainsString( 'font-weight: 200', $css );

		// The three columns never change on a smaller screen.
		$this->assertStringNotContainsString( 'grid-template-columns', substr( $css, (int) strpos( $css, '@media' ) ) );
	}
}
```

**Gate 3.**
1. PLUGIN-TESTS ends green (`OK, but incomplete, skipped, or risky tests!` with `Tests: 918, ... Skipped: 12` or an `OK (918 tests` line; any `Failures` or `Errors` = stop).
2. THEME-TESTS ends with `OK (627 tests, ...)`.

---

### Phase 4: service rows (DPH-007, DPH-008, DPH-009)

**Step 4.1. Create `$T/blocks/service-list/block.json`:**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/service-list",
	"title": "Service List",
	"category": "widgets",
	"description": "The top-level Services as numbered rows, each with its benefit line and its child services as grey tags. The whole row is one link.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css"
}
```

**Step 4.2. Create `$T/blocks/service-list/render.php`:**
```php
<?php
/**
 * Server render of the Home service rows (DPH-007, DPH-008, DPH-009).
 *
 * One row per top-level published Service. The row is ONE link (the title link's `::after` is stretched over
 * the row, see style.css); the tags are the child Services as plain text. No arrow, no lines under the rows.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_order = array(
	'menu_order' => 'ASC',
	'title'      => 'ASC',
);

$devportfolio_ids = devportfolio_published_ids(
	'service',
	array(
		'post_parent' => 0,
		'orderby'     => $devportfolio_order,
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

$devportfolio_archive = get_post_type_archive_link( 'service' );
$devportfolio_archive = $devportfolio_archive ? $devportfolio_archive : home_url( '/services/' );
$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'service-list' ) );
?>
<ul <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_index => $devportfolio_id ) :
		$devportfolio_slug    = (string) get_post_field( 'post_name', $devportfolio_id );
		$devportfolio_benefit = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $devportfolio_id ) ) );
		$devportfolio_tags    = devportfolio_published_ids(
			'service',
			array(
				'post_parent' => $devportfolio_id,
				'orderby'     => $devportfolio_order,
			)
		);
		?>
		<li class="service-list__row">
			<span class="service-list__number"><?php echo esc_html( str_pad( (string) ( $devportfolio_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
			<div class="service-list__body">
				<h3 class="service-list__title"><a class="service-list__link" href="<?php echo esc_url( $devportfolio_archive . '#' . $devportfolio_slug ); ?>"><?php echo esc_html( get_the_title( $devportfolio_id ) ); ?></a></h3>
				<?php if ( '' !== $devportfolio_benefit ) : ?>
					<p class="service-list__benefit"><?php echo esc_html( $devportfolio_benefit ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $devportfolio_tags ) ) : ?>
					<ul class="service-list__tags">
						<?php foreach ( $devportfolio_tags as $devportfolio_tag_id ) : ?>
							<li class="service-list__tag"><?php echo esc_html( get_the_title( $devportfolio_tag_id ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
```

**Step 4.3. Create `$T/blocks/service-list/style.css`:**
```css
.service-list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.service-list__row {
	position: relative;
	display: grid;
	grid-template-columns: 64px minmax(0, 1fr);
	gap: 4px 20px;
	min-height: 44px;
	padding: 24px 8px;
	border-radius: 12px;
	transition: background-color 160ms ease;
}

.service-list__row:hover,
.service-list__row:focus-within {
	background: var(--wp--preset--color--fill);
}

.service-list__row:has(.service-list__link:focus-visible) {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: -2px;
}

.service-list__number {
	padding-top: 6px;
	font-size: 14px;
	font-variant-numeric: tabular-nums;
	color: var(--wp--preset--color--mid);
}

.service-list__title {
	margin: 0 0 4px;
	font-size: clamp(22px, 3vw, 28px);
	font-weight: 400;
	letter-spacing: -0.02em;
	line-height: 1.2;
}

.service-list__link {
	color: inherit;
	text-decoration: none;
	outline: none;
}

.service-list__link::after {
	content: '';
	position: absolute;
	inset: 0;
}

.service-list__benefit {
	margin: 0;
	color: var(--wp--preset--color--mid);
}

.service-list__tags {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 0;
	margin: 10px 0 0;
	padding: 0;
	list-style: none;
	font-size: 13.5px;
	color: var(--wp--preset--color--mid);
}

.service-list__tag + .service-list__tag::before {
	content: '';
	display: inline-block;
	width: 4px;
	height: 4px;
	margin: 0 10px 2px;
	border-radius: 50%;
	background: var(--wp--preset--color--mid);
	vertical-align: middle;
}

@media (max-width: 640px) {
	.service-list__row {
		grid-template-columns: 40px minmax(0, 1fr);
		gap: 4px 12px;
		padding: 16px 4px;
	}

	.service-list__tags {
		display: none;
	}
}
```

**Step 4.4. Edit `$T/functions.php`.** old_string:
```
		register_block_type( __DIR__ . '/blocks/stats-strip' );
```
new_string:
```
		register_block_type( __DIR__ . '/blocks/stats-strip' );
		register_block_type( __DIR__ . '/blocks/service-list' );
```

**Step 4.5. Create `$T/tests/test-service-list.php`** (10 tests):
```php
<?php
/**
 * Tests for the Home service rows (DPH-007, DPH-008, DPH-009).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Service_List extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/service-list' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	/**
	 * Create a service.
	 *
	 * @param string $title   Title.
	 * @param int    $order   Menu order.
	 * @param int    $parent  Parent service ID, 0 for a top-level service.
	 * @param string $content Benefit line.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	private function make_service( string $title, int $order = 0, int $parent = 0, string $content = '', string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'service',
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent,
				'menu_order'   => $order,
				'post_status'  => $status,
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/service-list /-->' );
	}

	private function xpath( string $html ): DOMXPath {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		return new DOMXPath( $dom );
	}

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/service-list/style.css' );
	}

	/**
	 * Body of the first rule for an exact selector.
	 *
	 * @param string $css      CSS.
	 * @param string $selector Selector text before the brace.
	 * @return string
	 */
	private function rule( string $css, string $selector ): string {
		$this->assertSame( 1, preg_match( '/(?:^|\})\s*' . preg_quote( $selector, '/' ) . ' \{([^}]*)\}/', $css, $m ), "Rule {$selector} missing." );

		return $m[1];
	}

	public function test_rows_are_the_top_level_services_only_in_order(): void {
		$b = $this->make_service( 'Service B', 2 );
		$a = $this->make_service( 'Service A', 1 );
		$this->make_service( 'Child Of A', 1, $a );

		$x    = $this->xpath( $this->render() );
		$rows = $x->query( '//li[contains(@class,"service-list__row")]' );

		$this->assertSame( 2, $rows->length );
		$this->assertSame( 'Service A', trim( $x->query( './/h3', $rows->item( 0 ) )->item( 0 )->textContent ) );
		$this->assertSame( 'Service B', trim( $x->query( './/h3', $rows->item( 1 ) )->item( 0 )->textContent ) );
		$this->assertSame( '01', trim( $x->query( './/*[contains(@class,"service-list__number")]', $rows->item( 0 ) )->item( 0 )->textContent ) );
		$this->assertSame( '02', trim( $x->query( './/*[contains(@class,"service-list__number")]', $rows->item( 1 ) )->item( 0 )->textContent ) );
		$this->assertNotSame( 0, $b );
	}

	public function test_row_shows_title_benefit_and_tags_in_order(): void {
		$parent = $this->make_service( 'Social Media Marketing', 1, 0, 'Strategic content that builds your audience.' );
		$this->make_service( 'Scheduled posting', 2, $parent );
		$this->make_service( 'Analytics', 1, $parent );

		$x = $this->xpath( $this->render() );

		$this->assertSame( 'Social Media Marketing', trim( $x->query( '//h3' )->item( 0 )->textContent ) );
		$this->assertSame( 'Strategic content that builds your audience.', trim( $x->query( '//p[contains(@class,"service-list__benefit")]' )->item( 0 )->textContent ) );

		$tags = array();
		foreach ( $x->query( '//li[contains(@class,"service-list__tag")]' ) as $li ) {
			$tags[] = trim( $li->textContent );
		}

		$this->assertSame( array( 'Analytics', 'Scheduled posting' ), $tags );
	}

	public function test_tags_are_plain_text_not_links_or_pills(): void {
		$parent = $this->make_service( 'Parent', 1 );
		$this->make_service( 'Tag', 1, $parent );

		$x = $this->xpath( $this->render() );
		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__tag")]//a' )->length );
		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__tag")]//button' )->length );

		$css  = $this->css();
		$tags = $this->rule( $css, '.service-list__tags' );
		$this->assertStringNotContainsString( 'border', $tags );
		$this->assertStringNotContainsString( 'background', $tags );
		$this->assertStringNotContainsString( '.service-list__tag {', $css, 'A tag has no box of its own.' );
	}

	public function test_whole_row_is_one_link_to_the_service_anchor(): void {
		$parent = $this->make_service( 'Digital Marketing', 1 );
		$this->make_service( 'Ad analytics', 1, $parent );

		$x    = $this->xpath( $this->render() );
		$row  = $x->query( '//li[contains(@class,"service-list__row")]' )->item( 0 );
		$link = $x->query( './/a', $row );

		$this->assertSame( 1, $link->length );
		$this->assertSame( get_post_type_archive_link( 'service' ) . '#' . get_post_field( 'post_name', $parent ), $link->item( 0 )->getAttribute( 'href' ) );

		$css = $this->css();
		$this->assertStringContainsString( 'position: relative', $this->rule( $css, '.service-list__row' ) );
		$after = $this->rule( $css, '.service-list__link::after' );
		$this->assertStringContainsString( 'position: absolute', $after );
		$this->assertStringContainsString( 'inset: 0', $after );
	}

	public function test_no_arrow_and_no_lines(): void {
		$this->make_service( 'Parent', 1 );
		$html = $this->render();

		foreach ( array( '→', '›', '»', '<svg' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html );
		}

		$css = $this->css();
		$this->assertStringNotContainsString( 'border-bottom', $css );
		$this->assertStringNotContainsString( 'border-top', $css );
		$this->assertDoesNotMatchRegularExpression( '/\bborder\s*:/', $css );
	}

	public function test_drafts_are_skipped_and_a_row_without_children_has_no_tag_list(): void {
		$this->make_service( 'Draft parent', 1, 0, '', 'draft' );
		$parent = $this->make_service( 'Live parent', 2 );
		$this->make_service( 'Draft child', 1, $parent, '', 'draft' );

		$x = $this->xpath( $this->render() );

		$this->assertSame( 1, $x->query( '//li[contains(@class,"service-list__row")]' )->length );
		$this->assertSame( 0, $x->query( '//ul[contains(@class,"service-list__tags")]' )->length );
		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__tag")]' )->length );
	}

	public function test_output_is_escaped(): void {
		$id = $this->make_service( 'Tom & Jerry', 1, 0, 'Plain <script>alert(1)</script> text' );

		$html = $this->render();

		$this->assertStringContainsString( esc_html( get_the_title( $id ) ), $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_nothing_renders_without_services(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_phone_rules(): void {
		$css   = $this->css();
		$start = strpos( $css, '@media (max-width: 640px)' );

		$this->assertNotFalse( $start );
		$phone = substr( $css, (int) $start );
		$this->assertMatchesRegularExpression( '/\.service-list__tags \{[^}]*display: none;/', $phone );
		$this->assertStringContainsString( 'min-height: 44px', $this->rule( $css, '.service-list__row' ) );
	}

	public function test_seeded_content_matches_the_stories(): void {
		$path = WP_PLUGIN_DIR . '/custom-post-types-dp/data/seeders/services.csv';
		if ( ! is_file( $path ) ) {
			$this->markTestSkipped( 'services.csv not found.' );
		}

		$handle = fopen( $path, 'r' );
		fgetcsv( $handle ); // Header: order,title,parent,content.
		$parents  = array();
		$children = array();
		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			if ( '' === $row[2] ) {
				$parents[ $row[1] ] = $row[3];
			} else {
				$children[ $row[2] ][] = $row[1];
			}
		}
		fclose( $handle );

		$this->assertSame(
			array(
				'Social Media Marketing'    => 'Strategic content that builds your audience.',
				'Digital Marketing'         => 'Marketing support that turns attention into customers.',
				'Web Creation / Management' => 'Websites built, updated and kept running.',
			),
			$parents
		);
		$this->assertSame( array( 'Analytics', 'Scheduled posting', 'Content calendar', 'Content creation', 'Short-form videos', 'AI-generated videos' ), $children['Social Media Marketing'] );
		$this->assertSame( array( 'Ad analytics', 'Brand collaterals', 'Digital creation', 'Product mockups' ), $children['Digital Marketing'] );
		$this->assertSame( array( 'Websites & portfolios', 'GoDaddy', 'WordPress' ), $children['Web Creation / Management'] );
	}
}
```

**Gate 4.** THEME-TESTS ends with `OK (637 tests, ...)`. Also run `ddev wp cptdp seed --all --dry-run`: no line starts with `Error`.

---

### Phase 5: Home template (DPH-006, ties everything together)

**Step 5.1. Create `$T/templates/front-page.html`:**
```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"home-page","layout":{"type":"constrained"}} -->
<main class="wp-block-group home-page">

	<!-- The hero (DPH-001, DPH-003, DPH-004) goes here, above the stats strip. -->

	<!-- wp:devportfolio/stats-strip /-->

	<!-- wp:group {"anchor":"services","className":"home-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
	<div id="services" class="wp-block-group home-section" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

		<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">What I do</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"textColor":"mid","fontSize":"medium"} -->
		<p class="has-mid-color has-text-color has-medium-font-size">I help businesses grow their online presence through strategic content, marketing support and organized admin assistance, so you can focus on running your business.</p>
		<!-- /wp:paragraph -->

		<!-- wp:devportfolio/service-list /-->

	</div>
	<!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

**Step 5.2. Create `$T/tests/test-home-template.php`** (2 tests):
```php
<?php
/**
 * Tests for the Home template (DPH-006 and the order of the Home sections).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Home_Template extends WP_UnitTestCase {

	private const INTRO = 'I help businesses grow their online presence through strategic content, marketing support and organized admin assistance, so you can focus on running your business.';

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function template(): string {
		$file = $this->theme_dir() . '/templates/front-page.html';
		$this->assertFileExists( $file );

		return (string) file_get_contents( $file );
	}

	public function test_sections_are_in_order(): void {
		$tpl = $this->template();

		$needles = array(
			'<!-- wp:template-part {"slug":"header"',
			'<!-- wp:devportfolio/stats-strip /-->',
			'id="services"',
			'>What I do</h2>',
			'<!-- wp:devportfolio/service-list /-->',
			'<!-- wp:template-part {"slug":"footer"',
		);

		$last = -1;
		foreach ( $needles as $needle ) {
			$pos = strpos( $tpl, $needle );
			$this->assertNotFalse( $pos, "{$needle} missing." );
			$this->assertGreaterThan( $last, $pos, "{$needle} is out of order." );
			$last = $pos;
		}

		$this->assertStringContainsString( 'class="wp-block-group home-section"', $tpl );
	}

	public function test_the_intro_sentence_is_exact_and_appears_only_here(): void {
		$this->assertSame( 1, substr_count( $this->template(), self::INTRO ) );

		$files = array_merge(
			(array) glob( $this->theme_dir() . '/templates/*.html' ),
			(array) glob( $this->theme_dir() . '/parts/*.html' )
		);

		$count = 0;
		foreach ( $files as $file ) {
			$count += substr_count( (string) file_get_contents( $file ), 'I help businesses grow' );
		}

		$this->assertSame( 1, $count, 'The sentence must not be repeated in the hero or anywhere else.' );
	}
}
```

**Gate 5.**
1. THEME-TESTS ends with `OK (639 tests, ...)`.
2. Browser check (section 6): every row of the table (header, stats, services, footer, anchors).
3. `curl -sk https://devportfolio.ddev.site/ | grep -c "service-list__row"` prints `3` (after `ddev wp cptdp seed services`).

---

### Phase 6: documentation (AGENTS.md)

Edit `AGENTS.md` (each old_string must be found exactly once):

1. old_string: `site title (phones only, under 600px, left of the menu button) and main navigation, 7 inline links`
   new_string: `site title (shown at 900px and below, on the left), the "Book a call" button (601 to 900px only, a core Button whose root-relative link is expanded by \`devportfolio_expand_book_button_link()\`) and main navigation, 7 inline links (plus an 8th, phone-only "Book a call" item with class \`site-header__book-item\`, shown in the opened menu at 600px and below; core collapses at 600px and has no breakpoint attribute, so theme.json overrides its two \`min-width: 600px\` rules at 900px; on desktop the header is links only, no title and no button)`
2. old_string: `` `parts/footer.html` — site title ``
   new_string: `` `parts/footer.html` — the `devportfolio/site-footer` block (copyright with the current year and the site name on the left, LinkedIn, WhatsApp and Email from Settings → Contact on the right; a link without a value is not shown) ``
3. old_string: `theme 606 tests`
   new_string: `theme 639 tests`
4. old_string (the start of this line, unique): `Testimonials and Contact are Home-page sections, linked as anchors`
   new_string: the following paragraph **followed by a blank line and then the original sentence start**:
```
**Home page (DPH-002, 005, 006, 007, 008, 009, 029):** `templates/front-page.html` (hero slot first, then the stats strip, then the "What I do" section `#services`). `blocks/stats-strip/` (`devportfolio/stats-strip`): the first 3 published Statistics by menu order then title, value = post content as plain text (tags stripped), label = title, plain `<ul>`, no link or button, 3 columns at every width. `blocks/service-list/` (`devportfolio/service-list`): one row per top-level published Service (menu order, then title), number from the position (`01`), benefit = content as plain text, tags = child Services as plain grey text with dots, no arrow, no lines (rows separated by padding and a hover tint); the row is one link to `{services archive}#{slug}` through the title link's stretched `::after` (the future Services page must give each item `id="{slug}"`); tags hidden at 640px and below. `blocks/site-footer/` (`devportfolio/site-footer`) feeds `parts/footer.html`. Tests: `test-header-book-call.php`, `test-site-footer.php`, `test-stats-strip.php`, `test-service-list.php`, `test-home-template.php`. Plan: `.claude/plans/home/DPH-002-005-006-007-008-009-029-Home-Nav-Stats-Services-Footer.md`.
```
(Keep the original sentence `Testimonials and Contact are Home-page sections, linked as anchors` unchanged after the new paragraph.)

**Gate 6.** `git diff --stat AGENTS.md` shows only AGENTS.md changed with the 4 edits applied (`git diff AGENTS.md | grep -c '^[+-][^+-]'` is greater than 0), and THEME-TESTS is still `OK (639 tests, ...)`.

---

## 6. Browser check (run at the Gates of Phases 1 to 5)

There is no browser test runner in the repo; use a throwaway folder **outside the repo**.

1. Create the folder and install: `mkdir -p "$HOME/dp-check" && cd "$HOME/dp-check" && npm init -y >/dev/null && npm i puppeteer-core`.
2. Save this as `$HOME/dp-check/check.mjs` (set `CHROME` if Chrome is elsewhere):

```js
import puppeteer from 'puppeteer-core';

const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const URLS = ['https://devportfolio.ddev.site/', 'https://devportfolio.ddev.site/documents/'];
const WIDTHS = [1280, 901, 900, 700, 601, 600, 390, 320];

const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, ignoreHTTPSErrors: true, args: ['--ignore-certificate-errors'] });
const page = await browser.newPage();

const measure = () => {
	const q = (s) => document.querySelector(s);
	const vis = (el) => {
		if (!el) return false;
		const s = getComputedStyle(el);
		const b = el.getBoundingClientRect();
		return s.display !== 'none' && s.visibility !== 'hidden' && b.width > 0 && b.height > 0;
	};
	const h = (el) => (el ? Math.round(el.getBoundingClientRect().height) : null);
	const links = [...document.querySelectorAll('header .wp-block-navigation-item.wp-block-navigation-link:not(.site-header__book-item) > a')];
	const stats = [...document.querySelectorAll('.stats-strip__item')];
	const rows = [...document.querySelectorAll('.service-list__row')];
	const foot = [...document.querySelectorAll('.site-footer__link')];
	return {
		title: vis(q('.site-header__title')),
		book: vis(q('.wp-block-buttons.site-header__book')),
		burger: vis(q('.wp-block-navigation__responsive-container-open')),
		inlineLinks: links.filter(vis).length,
		homeActive: !!q('header .is-current'),
		statsOneRow: stats.length === 3 && new Set(stats.map((e) => e.offsetTop)).size === 1,
		rowCount: rows.length,
		rowMinH: rows.length ? Math.min(...rows.map(h)) : null,
		tagsVisible: [...document.querySelectorAll('.service-list__tags')].some(vis),
		footerLinks: foot.map((a) => a.textContent.trim()).join('|'),
		footerLinkMinH: foot.length ? Math.min(...foot.map(h)) : null,
		hOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
	};
};

for (const url of URLS) {
	for (const w of WIDTHS) {
		await page.setViewport({ width: w, height: 800 });
		await page.goto(url, { waitUntil: 'networkidle0' });
		console.log(url.endsWith('/documents/') ? 'DOCS' : 'HOME', w, JSON.stringify(await page.evaluate(measure)));
	}
}

// Opened menu at a tablet and a phone width.
await page.goto(URLS[0], { waitUntil: 'networkidle0' });
for (const w of [700, 390]) {
	await page.setViewport({ width: w, height: 800 });
	await page.goto(URLS[0], { waitUntil: 'networkidle0' });
	await page.click('.wp-block-navigation__responsive-container-open');
	await new Promise((r) => setTimeout(r, 400));
	const items = await page.evaluate(() =>
		[...document.querySelectorAll('.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item')]
			.filter((el) => { const b = el.getBoundingClientRect(); return getComputedStyle(el).display !== 'none' && b.height > 0; })
			.map((el) => el.textContent.trim() + ':' + Math.round(el.querySelector('a').getBoundingClientRect().height))
	);
	console.log('MENU', w, items.join(' | '));
}

await browser.close();
```
3. Run it: `cd "$HOME/dp-check" && node check.mjs`. It prints one JSON line per page and width, then two `MENU` lines.

**Expected (HOME lines):**

| Widths | title | book | burger | inlineLinks | other |
|---|---|---|---|---|---|
| 1280, 901 | false | false | false | 7 | `homeActive` true |
| 900, 700, 601 | true | true | true | 0 | |
| 600, 390, 320 | true | false | true | 0 | |

For **all** widths on HOME (Phase 3 onwards): `statsOneRow` true, `rowCount` 3 (Phase 4 onwards), `hOverflow` false. For widths 640 and below (`600, 390, 320`): `tagsVisible` false and `rowMinH` at least 44; for 700 and up `tagsVisible` true. `footerLinks` is `LinkedIn|WhatsApp|Email` when all three are set in Settings → Contact (set them in wp-admin first, or the line shows only the ones with a value); `footerLinkMinH` is at least 44. DOCS lines must show the same `title/book/burger/inlineLinks` values as HOME and the same footer values.

**Expected (MENU lines):** at 700 the list is the 7 labels with no "Book a call": `Home:… | Services:… | … | Contact:…` (each height number at least 44); at 390 the list has 8 entries, the last is `Book a call:` with a height of at least 44.

Report every number that differs; do not change CSS to make a number pass without understanding why.

## 7. Failure hints (what the common errors mean)

| Symptom | Cause and fix |
|---|---|
| PHPUnit `Class ... not found` or "no tests executed" | The new test file name or class name differs from this plan: file `test-*.php`, class `Devportfolio_Test_*` exactly as given. |
| A render test returns `''` | The block is not registered: Step "Edit `functions.php`" for that block was not applied, or `block.json` has a typo. Run `ddev exec php -l <file>` on `render.php`. |
| `test_urls_are_escaped` fails | `render.php` outputs the raw email somewhere; every URL must pass `esc_url()`. |
| theme.json edit "not found" | The find text contains a different spacing. Open the file with Read, search for the first 40 characters, and compare. Do not paraphrase; STOP and report. |
| `JSON-OK` prints a `JSONDecodeError` | An edit broke the string: a stray `"` was inserted inside the CSS (only single quotes are allowed there) or the closing `"` was removed. Compare with the old text via `git diff wp-content/themes/devportfolio/theme.json`. |
| At 700px the links are still inline | See Gate 1 step 4. |
| Old navigation tests fail on the number of links | Step 1.4 Edit D (skip the `site-header__book-item` item) was not applied. |

## 8. Conventional commits (make these only when the user asks you to commit)

Branch `feat/DPH-002-005-006-007-008-009-029` (exists). **Stage explicit paths only** (never `git add -A`; other tickets' plan files sit untracked). Subject lines are at most 72 characters. Each commit's footer is two lines: `Refs: <tickets>` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. After each commit THEME-TESTS must be green; the order below keeps that true.

| # | Subject | Stage exactly these paths | Refs |
|---|---|---|---|
| 1 | `feat(theme): collapse the header at 900px with a Book a call button` | `$T/parts/header.html`, `$T/theme.json`, `$T/functions.php`, `$T/tests/test-header-book-call.php`, `$T/tests/test-navigation.php`, `$T/tests/test-design-system.php` | DPH-002 |
| 2 | `feat(theme): add the footer with copyright and contact links` | `$T/blocks/site-footer/block.json`, `$T/blocks/site-footer/render.php`, `$T/blocks/site-footer/style.css`, `$T/parts/footer.html`, `$T/functions.php` | DPH-029 |
| 3 | `test(theme): cover the footer block` | `$T/tests/test-site-footer.php` | DPH-029 |
| 4 | `fix(custom-post-types-dp): seed the first statistic without markup` | `$P/data/seeders/statistics.csv` | DPH-005 |
| 5 | `feat(theme): add the stats strip block` | `$T/blocks/stats-strip/block.json`, `$T/blocks/stats-strip/render.php`, `$T/blocks/stats-strip/style.css`, `$T/functions.php` | DPH-005 |
| 6 | `test(theme): cover the stats strip` | `$T/tests/test-stats-strip.php` | DPH-005 |
| 7 | `feat(theme): add the Home service rows block` | `$T/blocks/service-list/block.json`, `$T/blocks/service-list/render.php`, `$T/blocks/service-list/style.css`, `$T/functions.php` | DPH-007, DPH-008, DPH-009 |
| 8 | `test(theme): cover the Home service rows` | `$T/tests/test-service-list.php` | DPH-007, DPH-008, DPH-009 |
| 9 | `feat(theme): add the Home template with the What I do intro` | `$T/templates/front-page.html` | DPH-006 |
| 10 | `test(theme): cover the Home template` | `$T/tests/test-home-template.php` | DPH-006 |
| 11 | `docs(agents): document the Home page blocks and the 900px header` | `AGENTS.md` | DPH-002, DPH-005, DPH-029 |
| 12 | `docs(plans): add the Home nav, stats, services and footer plan` | `.claude/plans/home/DPH-002-005-006-007-008-009-029-Home-Nav-Stats-Services-Footer.md` | DPH-002, DPH-005, DPH-006, DPH-007, DPH-008, DPH-009, DPH-029 |

Commit 1 holds code and its test updates together on purpose: the `theme.json` change makes the old header tests fail, so splitting them would leave a red commit. `functions.php` is edited in several phases; stage it in the commit of the phase that changed it (use `git add -p` or commit right after each phase's edit).

**PR title:** `feat(theme): add Home navbar, stats, services and footer` (under 72 characters). Body from `.github/pull_request_template.md`: tick the feature type; fill Description, Implementation and How to Test (the section 6 browser check), ending with the line `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit.

## 9. File inventory

New (14): `$T/templates/front-page.html`; `$T/blocks/stats-strip/{block.json,render.php,style.css}`; `$T/blocks/service-list/{block.json,render.php,style.css}`; `$T/blocks/site-footer/{block.json,render.php,style.css}`; `$T/tests/test-header-book-call.php`, `test-site-footer.php`, `test-stats-strip.php`, `test-service-list.php`, `test-home-template.php`.

Changed (8): `$T/parts/header.html`, `$T/parts/footer.html`, `$T/theme.json`, `$T/functions.php`, `$T/tests/test-navigation.php`, `$T/tests/test-design-system.php`, `$P/data/seeders/statistics.csv`, `AGENTS.md`.

Not touched: plugin PHP, plugin version, `twentytwentyfive`, any JavaScript.

## 10. Final verification (run all before reporting done)

1. THEME-TESTS: `OK (639 tests, ...)`. PLUGIN-TESTS: green (918 tests, 12 skipped).
2. `ddev wp cptdp seed --all --dry-run`: no error.
3. Section 6 browser check done at all widths; report any line not run.
4. `git status --short` lists only the files of section 9 (plus the untracked plan files that were already there).
5. Report honestly: failing or skipped tests, any browser number that differed, anything you could not do.

## 11. Risks and open questions (none block implementation)

- **The 900px override fights core's 600px rule.** The selectors in Phase 1 Edit C out-rank core by specificity; the browser check is the proof.
- **Site name.** If `blogname` is not `Dave Portfolio` the header title and footer show another name; Phase 0 fixes the dev DB; production needs the same one-time setting.
- **Existing DB rows.** The seeder never changes existing rows: a statistic still holding `<b>+5 yrs</b>` renders right because the block strips tags.
- **`/services/#slug`** does nothing special until the Services page adds ids (D4).
- **Footer reaches every page**; the whole theme suite is the proof, not only the new classes.
- **D1 is a visible change to a shipped design** (title and Contact pill on desktop); the three rewritten tests are the whole blast radius.
- Open questions with defaults already chosen: hairlines between service rows (D6); the Services nav link target (D8); whether wp-admin should enforce exactly three statistics (D5).

---

## 12. As-built changes (what was done differently from this plan)

Implemented on branch `feat/DPH-002-005-006-007-008-009-029`. Nothing committed yet. Final counts: theme suite **656 tests, all green**; plugin suite **922 tests, 12 skipped, green**.

### 12.1 Deviations made during implementation (the plan was followed except where listed)

| # | Plan said | What was done | Why |
|---|---|---|---|
| 1 | Gate 0: baseline theme suite is 606 tests; STOP if not | Baseline was 619 (tests already merged on this branch). Went ahead, tracked deltas from 619. | Green baseline; the count drift is not caused by this plan. |
| 2 | Phase 0 step 2: site name `Dave Portfolio` | Dev DB site name was `Dev Portfolio`; set to `Dave Portfolio`. | Required for the header and footer text. |
| 3 | Step 1.2 Edit C: hide the phone-only item with `.site-header__bar .wp-block-navigation-item.site-header__book-item { display: none; }` | Selector is `.site-header__bar .wp-block-navigation-item.wp-block-navigation-link.site-header__book-item`. | Core's overlay rule (specificity 0,4,0) set `display:flex` on the item, so "Book a call" showed in the menu at 601–900px. Browser check caught it. |
| 4 | Step 1.4 Edit D: `nav_items()` helper indented three tabs | The file has two tabs; the same change was applied at the file's indentation. | Plan indentation did not match the file. |
| 5 | Step 1.4: `test-design-system` strings | One extra update: the navigation-link count in the header is now 8 (7 links + the phone-only item). | The new item is intentional. |
| 6 | Step 4.5 test code: `fgetcsv( $handle )` | `fgetcsv( $handle, 0, ',', '"', '' )`. | PHP 8.4 deprecation notice for the `$escape` argument. |
| 7 | Section 6: `statsOneRow` true "from Phase 3 onwards" | The strip is only placed on Home in Phase 5 (`front-page.html`), so it is checked from Phase 5. | The plan's own phases disagree. |
| 8 | Step 6 AGENTS.md: theme total `639 tests` | Written as the real total at the time (652; now 656). | The plan's total assumed the 606 baseline. |
| 9 | Step 2.6 footer tests | Email assertions were added later (see 12.2). | Follow-up request. |

### 12.2 Changes requested after the plan was implemented (supersede parts of the plan)

These are the owner's changes made once the plan's phases were done. Where they contradict an earlier decision, the later change wins.

| Change | Supersedes | Result |
|---|---|---|
| Desktop header restored to the outlined **Contact pill** pinned right, with the title **Dave Portfolio** on the left and the links centred, from **1024px**. Contact is targeted as `.site-header__bar .wp-block-navigation-item:has(+ .site-header__book-item)` (the phone-only item is now the last child). | D1 (links only on desktop) and Phase 1 Edit B | Title shows at ≤900px and ≥1024px; hidden 901–1023px. "Book a call" stays on 601–900px only. |
| Child services render as **chips** (1px light outline, 12px radius, 11.5px grey text, white fill, 5px gap, no dots), per the home lo-fi wireframe. | Phase 4 step 4.3 (plain text with dot separators) | Chips are list items, not links. Hidden at 640px and below. |
| Service rows are **plain text, not links**. The stretched link, its focus outline and `position: relative` were removed. The rows link nowhere. | D4 (row link to `{services archive}#{slug}`) and Phase 4 "whole row is one link" test | The header "Services" nav link still points to `/#services`. |
| **Row hover highlight** on the whole row (`fill` background, 12px radius, 160ms). | The removed row tint (D6 said no lines; the tint was removed with the link) | Only the hovered row changes. |
| Home `<main>` uses the shared **`documents-page`** shell, and `page.css` is enqueued on the front page too. | Phase 5 `front-page.html` `<main class="home-page">` and the page-styles test that asserted Home gets no page CSS | Home gutters and width now match Documents, Videos and Collaterals (measured at 1440, 1024, 800, 390px). |
| The **footer** part is 1440px wide (`footer.wp-block-template-part > .wp-block-group { --wp--style--global--content-size: 1440px; }` in `theme.json`), as the header is. | Footer default 1040px width | The copyright lines up with the header title at every width. |
| A **1px separator** between the "What I do" paragraph and the first service row (core `wp:separator`, `line` colour, height set to 1px in `theme.json`). | Phase 5 template (no separator) | Class `home-section__rule`. |
| The footer **Email link shows the address** from 601px up and the word **Email** at 600px and below (`site-footer__full` / `site-footer__short` spans). Still `mailto:`. | Phase 2 footer render (label only) | Tests check both spans. |

### 12.3 Final decisions table (replaces D1, D4, D6 where they conflict)

- **D1 (superseded):** desktop has the title on the left and the Contact pill on the right, from 1024px. Between 901px and 1023px: links only.
- **D4 (superseded):** service rows are not links. No `#slug` anchors are generated.
- **D6 (partly superseded):** still no lines under the rows. The row hover highlight is allowed.
- **D8 (unchanged):** the Services nav link stays `/#services`.

### 12.4 CSV updated

`.claude/plans/home/Home-User-Stories-Revised.csv` (story source for this plan). Acceptance criteria changed for six stories; no other rows touched; the file still parses to 32 rows of 9 columns.

- **DPH-002** criterion 1: desktop layout (title left, links centred, Contact pill right from 1024px; links only from 901px to 1023px; no button on desktop).
- **DPH-006** new criterion 3: a thin horizontal line between the sentence and the first service row.
- **DPH-007, DPH-008, DPH-009**: tags are grey **chips** (outlined, rounded, not links); the row is **plain text, not a link**, and hovering highlights the whole row; on phones the tags are hidden (the 44px tap-target rule is removed).
- **DPH-029** criterion 1: the Email link shows the address when there is room (601px and wider) and the word Email below that; the copyright lines up with the header title.

Backup of the CSV before this change: `$HOME/dp-check/Home-User-Stories-Revised.before.csv` (scratch, outside the repo).

### 12.5 Files changed beyond section 9 (inventory addition)

- `wp-content/themes/devportfolio/blocks/service-list/{render.php,style.css}` (chips, no link, hover row)
- `wp-content/themes/devportfolio/blocks/site-footer/{render.php,style.css}` (email address span)
- `wp-content/themes/devportfolio/templates/front-page.html` (shell class, separator)
- `wp-content/themes/devportfolio/functions.php` (page styles on the front page)
- `wp-content/themes/devportfolio/theme.json` (desktop header, footer width, separator height)
- `wp-content/themes/devportfolio/tests/` (test-home-template, test-page-styles, test-service-list, test-site-footer, test-design-system, test-navigation)
- `AGENTS.md` (header, footer, Home and service rows descriptions)
- `.claude/plans/home/Home-User-Stories-Revised.csv` (section 12.4)

### 12.6 Commit list

Section 8 is out of date: the commit split must be redone. Stage explicit paths only; the plan's commit list still applies to the original phases, and the later changes in 12.2 need their own commit(s) (for example `feat(theme): restore the desktop Contact pill and title`, `feat(theme): render service children as chips`, `feat(theme): highlight service rows on hover`) before any commit is made.

### 12.7 "Book a call" removed (owner request)

The header's "Book a call" button (tablets 601–900px) and its phone menu item were removed. They came from the DPH-002 story's criterion 4 and are no longer in the design.

- Removed: `parts/header.html` (the button and the phone-only nav item), the header CSS for them in `theme.json`, the link filter `devportfolio_expand_book_button_link()` in `functions.php`, and `tests/test-header-book-call.php`.
- The desktop Contact pill is targeted with `.site-header__bar .wp-block-navigation-item:last-child` again (it is the last link now).
- Story CSV: DPH-002 criterion 4 no longer mentions the button; the "Book a call" button mentioned in the Contact story (the nav and hero buttons) now refers only to the hero button.
- Theme tests: the header-book-call file is deleted, and the header tests expect 7 links.

### 12.8 Contact pill kept between 901px and 1023px (owner request)

Between 901px and 1023px the title is hidden, and the Contact link now stays an outlined pill at the right edge, with the nav links aligned to the left. Previously it was a plain link inside the centred nav.

- `theme.json`: the pill look moved to `@media (min-width: 901px)`; the 901–1023px query sets the nav list to `justify-content: flex-start; width: 100%` and pushes Contact to the end with `margin-left: auto`. From 1024px the Contact pill is still pinned right (absolute), and the nav stays centred.
- Tests: the pill test reads the 901px query; a new test covers the 901–1023px layout. Theme suite: 650 tests, all green.
- Browser check: at 901px, 950px and 1023px the first link starts 24px from the left and Contact ends 24px from the right. At 1024px and wider the layout is unchanged.
