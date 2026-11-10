# Plan: Brand Collaterals page shell, title, category filter and category seeder

Tickets **DPC-000** (page), **DPC-001** (title), **DPC-002** (categories), **DPC-003** (category state, URL), **DPC-004** (seed collateral categories). Branch `feat/DPC-000-004-Collaterals-Page`, created **from `main`**. Written so another agent can build it without asking: every file, selector, string, test name and command is spelled out. Read sections 1-4, then do the phases in section 6 in order.

Related plans (history only): `documents/DPH-002-DPD-000-001-Documents-Page.md` (the page shell this copies), `documents/DPD-002-003-025-Header-Categories.md` (pills, `?category=`, sticky band), `documents/DPD-041-Header-Category-Filter-Dropdown-On-Mobile.md` (the phone dropdown, **already merged**, so Collaterals gets it for free), `documents/DPD-034-Design-Redesign-According-To-Design-System.md` (section *Reference page for future pages*), `backend/DPB-009-010-013-Brand-Collateral.md` (the `collateral` CPT and `collateral_category` taxonomy, **already shipped**), `documents/DPD-007-Seed-Document-Category.md` (term seeder precedent).

---

## 1. Goal and acceptance criteria

1. `/collaterals/` renders from a new block template `templates/archive-collateral.html` (WordPress picks it by hierarchy for the `collateral` archive; no Page, no seeding, same reasoning as Documents: a Page slugged `collaterals` would be shadowed by the CPT archive rewrite).
2. The main navigation link **Collaterals** (already in `parts/header.html`, `kind: post-type-archive`) carries `aria-current="page"` on that page and on no other page. **No header change is needed**; this is proven by a test (section 7).
3. The page uses the shared header, Hanken Grotesk, the theme tokens, and the **same page width and phone rules as Documents** (the `.documents-page` rules, made shared in Phase 2).
4. Title: `<h3>Brand Collaterals</h3>`, same look as the Documents title (44-72px, weight 200, -0.04em, smaller on phones). Same `.documents-page__title` rules.
5. Category filter: the existing `devportfolio/document-categories` block, **made taxonomy-aware** (Phase 1), pointed at `collateral_category`. "All" first and pressed by default, then one pill per term in **name order**, **empty terms included**. Sticky under the sticky site header on the frosted band. On phones (<=820px) it is the **dropdown** from DPD-041 (this supersedes the story's "scrolling row until DPD-041 ships", because DPD-041 already shipped).
6. State: one active pill; clicking another updates `aria-pressed`, the trigger label and the URL `?category=<slug>` (All = no param, via `pushState`); first paint honours `?category=<slug>` (server rendered); Back/Forward re-applies without reload; an unknown slug falls back to All and the bad param is removed with `replaceState`. All of this is **existing behaviour of `view.js` and `render.php`**; this ticket only has to keep it working for another taxonomy.
7. Empty state: with no published collateral, the page shows the title and the filter only, no PHP notice, no error, no empty frame.
8. Seeder: `ddev wp cptdp seed collateral-categories` creates `Flyer` and `Logo`; a second run changes nothing; a term deleted in wp-admin is recreated by an explicit run; `seed list` shows it; `seed --all --dry-run` lists it and writes nothing.
9. Both PHP suites and the Node suite pass, with the **Documents tests unchanged** apart from the one file-path change called out in Phase 2.

## 2. Out of scope (later tickets, do not build)

- The collateral grid/items/tiles, the collateral modal and its viewer (the template leaves a clearly commented slot, see 5.2). Because there is no grid yet, **"filters the grid in place" and the fade/glide are verified by the grid ticket**; this ticket only guarantees the contract (5.1) and that the script is a no-op without a grid.
- A collaterals seeder (sample collateral posts). Thumbnails and files are added by hand.
- Renaming `documents-page*`, `documents-categories__*` classes or the block name. `AGENTS.md` says generalise only what the second page needs and keep Documents tests green; class renames are deferred to a later clean-up ticket and must not happen here.
- Header/nav changes, new fonts, new tokens, a new breakpoint.
- DPC-024 (listed as a dependency of DPC-000, not defined here): assume it is a later/parallel ticket and do not depend on it.

## 3. Facts established from the repo (do not re-derive)

- Plugin `custom-post-types-dp` 0.24.0. `collateral` CPT exists (`includes/post-types/collateral.php`, archive `/collaterals/`, rewrite slug `collaterals`, menu position 27). `collateral_category` exists (`includes/post-types/collateral-category.php`, flat, public, rewrite `/collateral-category/`, attached by the CPT). No `collateral_category` seeder exists. There is **no default term** for `collateral_category`.
- `parts/header.html` line 12 already has `<!-- wp:navigation-link {"label":"Collaterals","type":"collateral","url":"/collaterals/","kind":"post-type-archive","isTopLevelLink":true} /-->`; `devportfolio_prepare_navigation_link()` (functions.php) resolves it with `get_post_type_archive_link( 'collateral' )` so core marks it current.
- `blocks/document-categories/` (`block.json`, `render.php`, `style.css`, `view.js`) hardcodes taxonomy `document_category` and the aria-label "Document categories" in `render.php`. `view.js` binds to every `.wp-block-devportfolio-document-categories`, looks for the shelf with `document.querySelector( '[data-documents-shelf]' )` and **already guards `if ( shelf )`** everywhere, so it runs fine with no shelf. Its popstate handler checks `dialog.documents-modal[open], dialog.documents-mobile[open]`, which simply do not exist on this page.
- **Style loading trap:** block `style.css` files load only on pages that render that block. The page-level rules `.documents-page` (page width 1440px, `--wp--style--global--content-size`, side padding, `overflow-x: clip`), `.documents-page .documents-page__title` (44-72px etc.) and their `@media (max-width: 820px)` twins live in **`blocks/document-shelf/style.css`** (about lines 50-66 and 198-210). The collaterals page has no shelf block yet, so without Phase 2 it would render an unstyled title at the theme's 1040px width.
- Term seeder precedent: `includes/seeders/video-categories.php` + `data/seeders/video-categories.csv` (`title,slug`) + `tests/seeders/test-video-categories.php` (extends `CPTDP_Test_Case`, not the post seeder base, because terms have no Trash). `document-categories` additionally has a `singular` column; **collateral-categories does not** (not asked for).
- Seeders run in **file-name order** (`class-loader.php` globs `includes/seeders/*.php`), so `--all` becomes: `aspect-ratios`, `collateral-categories`, `document-categories`, `services`, `software-tools`, `statistics`, `video-categories`, `videos`, `documents`. Existing registry-order tests only assert pairwise order (ratios < categories < videos), so nothing breaks.

## 4. Decisions

| # | Decision | Why |
|---|----------|-----|
| D1 | Reuse the **same block** `devportfolio/document-categories`, add two attributes: `taxonomy` (string, default `document_category`) and `label` (string, default `Document categories`, the aria-label of the group). No new block, no copied files. | One copy of the filter, view transition, dropdown, sticky band. Defaults reproduce Documents byte for byte, so its tests stay unchanged. |
| D2 | Keep every class name, `data-*` name and the `view.js` shelf lookup as they are. Do **not** add shelf/item attributes now. | There is no collateral grid yet. The grid ticket decides the shelf contract (it will need `shelfSelector`/item attribute plumbing and a planks switch) and records it. Adding unused config now is speculative. |
| D3 | Move the page-level rules out of `document-shelf/style.css` into a new theme stylesheet `assets/css/page.css`, enqueued on both archives from `functions.php`. Rules are moved **verbatim** (same selectors, values, order), nothing else in the shelf CSS changes. | Fixes the style loading trap without duplicating CSS and without renaming classes. |
| D4 | The collaterals template **reuses** the classes `documents-page`, `documents-page__title`, `documents-page__content`. | The shared rules key on them (D3); renaming is out of scope. |
| D5 | The phone control is the DPD-041 dropdown (not the old scrolling strip). | It is merged; the story text anticipates exactly this. |
| D6 | Seeder CSV has only `title,slug`; Flyer/`flyer`, Logo/`logo`. | Matches `video-categories`; no singular names asked. |
| D7 | Plugin version bump `0.24.0` -> `0.25.0` (new seeder + CSV = shipped feature, per AGENTS Git rules). Theme has no version bump convention beyond `style.css`; leave it. | |

## 5. Design details

### 5.1 Block change (`wp-content/themes/devportfolio/blocks/document-categories/`)

`block.json`: add
```json
"attributes": {
	"taxonomy": { "type": "string", "default": "document_category" },
	"label":    { "type": "string", "default": "" }
}
```
(`label` default empty = use the translated default in PHP, so Documents keeps its translatable string.) Keep `supports.html: false`.

`render.php` (only these edits):
1. Before `get_terms`:
```php
$devportfolio_taxonomy = sanitize_key( $attributes['taxonomy'] ?? 'document_category' );
if ( ! taxonomy_exists( $devportfolio_taxonomy ) ) {
	return;
}
```
2. `'taxonomy' => $devportfolio_taxonomy` in the `get_terms()` args (still `hide_empty => false`, `orderby => name`, `order => ASC`).
3. aria-label: `'' !== trim( (string) ( $attributes['label'] ?? '' ) ) ? $attributes['label'] : __( 'Document categories', 'devportfolio' )`, output through `esc_attr()`. Keep `role="group"`. Today's code uses `esc_attr__( 'Document categories', 'devportfolio' )`; the default branch must produce the identical string.
4. Nothing else: "All", `data-filter`, `aria-pressed`, the trigger/list markup and the `?category=` whitelist (`in_array( ..., wp_list_pluck( $terms, 'slug' ), true )`, `sanitize_key`) stay as they are. The whitelist is per taxonomy automatically because `$terms` is now that taxonomy's terms, so a `document_category` slug in `/collaterals/?category=` falls back to All.

`view.js`, `style.css`: **no change**.

### 5.2 Template `templates/archive-collateral.html`

Copy `archive-document.html` and change only: the h3 text, the categories block attributes, and drop the shelf and modal blocks (leave a comment where they go). Exact file:

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"documents-page","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group documents-page" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">

	<!-- wp:heading {"level":3,"className":"documents-page__title"} -->
	<h3 class="wp-block-heading documents-page__title">Brand Collaterals</h3>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"documents-page__content","layout":{"type":"default"}} -->
	<div class="wp-block-group documents-page__content">

		<!-- wp:devportfolio/document-categories {"taxonomy":"collateral_category","label":"Collateral categories"} /-->

		<!-- Collateral grid and modal blocks: added by the grid/modal tickets. -->

	</div>
	<!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```
Do not use an HTML comment inside the block markup if the Site Editor round-trips it badly; if `wp:` parsing complains, drop the comment and keep the note in the plan/AGENTS only.

### 5.3 Shared page stylesheet (D3)

New `assets/css/page.css` containing, moved verbatim from `blocks/document-shelf/style.css`:
- the block comment and `.documents-page { ... }` rule (about lines 43-58),
- `.documents-page .documents-page__title { ... }` (about 60-66),
- inside `@media (max-width: 820px)`: `.documents-page { padding-top: 16px !important; }` and `.documents-page .documents-page__title { ... }` (about 198-208).
Use `grep -n "documents-page" blocks/document-shelf/style.css` to find every occurrence first; move **all** of them (also any further `.documents-page` rule found, e.g. overflow/dot-grid related) and leave rules that style shelf internals. The shelf rules must keep working: the shelf only renders inside `.documents-page`, and `page.css` is enqueued on that archive too.

`functions.php`, new function (prefix `devportfolio_`, `function_exists` guard like its neighbours):
```php
function devportfolio_enqueue_page_styles() {
	if ( ! is_post_type_archive( array( 'document', 'collateral' ) ) ) {
		return;
	}
	wp_enqueue_style(
		'devportfolio-page',
		get_theme_file_uri( 'assets/css/page.css' ),
		array(),
		(string) filemtime( get_theme_file_path( 'assets/css/page.css' ) )
	);
}
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_page_styles' );
```
(AGENTS says the theme "has no build step" and "only bundled assets are font files": update that sentence to include `assets/css/page.css`.)

**Tests that read the moved CSS** (`tests/test-design-system.php`, the Documents title and phone-size tests, and any other theme test that reads `blocks/document-shelf/style.css` for `.documents-page` rules; find with `grep -rn "documents-page" tests/`). Only change **which file they read** for those rules; every assertion stays identical. State in the PR body that this is the single permitted change to existing tests.

### 5.4 Seeder

`includes/seeders/collateral-categories.php`: copy of `video-categories.php` with: registry key `collateral-categories`; label `esc_html__( 'Collateral Categories', 'custom-post-types-dp' )`; callback `cptdp_seed_collateral_category_terms`; register function `cptdp_register_seeder_collateral_categories`; `'taxonomy' => 'collateral_category'`; `'csv' => CPTDP_PATH . 'data/seeders/collateral-categories.csv'`; `'columns' => array( 'title', 'slug' )`. Keep the file's logic: whole-CSV validation first (valid slug via `sanitize_title`, no duplicate titles or slugs) then writes; match an existing term by slug **or** name -> skipped, never modified; missing taxonomy -> `WP_Error( 'cptdp_seed_no_taxonomy', ... )`; dry run writes nothing; return `array( 'created' => [], 'skipped' => [] )`. Update the docblock text accordingly.

`data/seeders/collateral-categories.csv`:
```
title,slug
Flyer,flyer
Logo,logo
```

Plugin header `Version: 0.25.0` in `custom-post-types-dp.php` (and the version constant if the file defines one: `grep -n "0.24.0" custom-post-types-dp.php`).

## 6. Work packages (do in order; one commit each, gate before the next)

Branch: `git switch main && git switch -c feat/DPC-000-004-Collaterals-Page`. First commit stages only this plan file: `docs(plans): add DPC-000 to DPC-004 collaterals page plan`.

> The commit subjects mentioned inside each phase below are summaries. **Section 6A is the authoritative commit list** (it splits code and tests into separate commits).

### Phase 0: environment
`ddev start`; test library after any restart: `ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"`. Baseline: run both PHP suites and the Node suite once on untouched `main`; record the counts (AGENTS: plugin 726/12 skipped, theme 345, node 153; they may have moved, trust the baseline you measure).

### Phase 1: seeder (DPC-004), plugin only
1. Add the CSV and `includes/seeders/collateral-categories.php` (5.4).
2. Add `tests/seeders/test-collateral-categories.php`, class `CPTDP_Test_Seeder_Collateral_Categories extends CPTDP_Test_Case`, `@group collateral-category` `@group seeder`. Copy `test-video-categories.php` and adapt (taxonomy `collateral_category`; its `set_up()` deletes every existing term of the taxonomy first). Tests: `test_seeder_is_registered_with_a_complete_entry` (key, label, callback callable, `taxonomy`, columns `title,slug`, csv path contains `/data/seeders/` and exists), `test_registry_order_is_before_documents_chain` (name appears in registry, after `aspect-ratios`, before `documents`), `test_csv_is_well_formed` (exactly 2 rows: Flyer/flyer, Logo/logo), `test_creates_every_term_with_name_and_slug`, `test_dry_run_writes_nothing` (no terms after, result lists both as created), `test_second_run_is_idempotent` (created empty, skipped has both, term count 2), `test_matches_an_existing_term_by_name_when_the_slug_differs`, `test_existing_term_is_not_modified`, `test_restores_a_deleted_term_without_touching_the_others`, `test_bad_csv_aborts_before_any_write` (duplicate slug, invalid slug), `test_missing_csv_errors`.
3. Bump plugin to 0.25.0. `AGENTS.md`: add `collateral-categories` to the seeder list in Overview (taxonomy sentence: "seeder `collateral-categories` creates `Flyer`/`Logo`, skipping an existing term by slug or name, `ddev wp cptdp seed collateral-categories`") and fix the `--all` order sentence (insert `collateral-categories` after `aspect-ratios`); add `collateral-categories` to the "Term seeders (...)" list.

Gate:
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Collateral_Categories --testdox
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev wp cptdp seed list                                 # shows collateral-categories
ddev wp cptdp seed collateral-categories --dry-run      # would create Flyer, Logo; writes nothing
ddev snapshot                                           # before a real run
ddev wp cptdp seed collateral-categories                # creates Flyer, Logo
ddev wp cptdp seed collateral-categories                # second run: nothing created
ddev wp term list collateral_category --fields=name,slug
ddev wp cptdp seed --all --dry-run                      # lists it, writes nothing
ddev wp term delete collateral_category $(ddev wp term get collateral_category flyer --by=slug --field=term_id) && ddev wp cptdp seed collateral-categories   # Flyer comes back
```
Commits: `feat(custom-post-types-dp): add collateral categories seeder` (CSV, seeder, test), `chore(custom-post-types-dp): bump version to 0.25.0`, `docs(agents): document collateral categories seeder`. Each with footer `Refs: DPC-004`.

### Phase 2: shared page styles (D3), theme only, **no visual change to Documents**
1. Create `assets/css/page.css` by moving the rules (5.3); remove them from `document-shelf/style.css`.
2. Add `devportfolio_enqueue_page_styles()` to `functions.php`.
3. Update the file path the existing theme tests read for those rules (5.3), nothing else in them.
4. New test class file `tests/test-page-styles.php`, `Devportfolio_Test_Page_Styles`, `@group theme`: `test_page_css_exists_and_holds_the_page_rules` (the `.documents-page` rule sets `--wp--style--global--content-size: 1440px` and `overflow-x: clip`; the title rule has `clamp(44px, 6.4vw, 72px)`, `font-weight: 200`, `letter-spacing: -0.04em`; the 820px media block has the phone title `clamp(36px, 9vw, 52px)` and `padding-top: 16px !important`), `test_shelf_css_no_longer_holds_page_rules` (no `.documents-page {` or `.documents-page .documents-page__title` selector left in `document-shelf/style.css`, so nothing is defined twice), `test_page_css_is_enqueued_on_both_archives` (`go_to( get_post_type_archive_link( 'document' ) )` then `do_action( 'wp_enqueue_scripts' )` -> `wp_style_is( 'devportfolio-page', 'enqueued' )`; same for `collateral`; **not** enqueued on the front page).
Gate: theme suite fully green with unchanged assertions; open `https://devportfolio.ddev.site/documents/` at 1440, 820 and 390px and confirm it looks identical to `main` (width, title size, phone padding). Commit `refactor(theme): share the page layout styles between archives`, footer `Refs: DPC-000`.

### Phase 3: taxonomy-aware categories block (DPC-002, DPC-003)
1. Edit `block.json` and `render.php` (5.1).
2. New tests in `tests/test-collateral-categories.php`, class `Devportfolio_Test_Collateral_Categories`, `@group theme`. `set_up()` as in `test-document-categories.php` (`do_action( 'cptdp_register_post_types' )`, `require_once functions.php`, register blocks if needed). Render helper: `go_to( get_post_type_archive_link( 'collateral' ) )`, restoring `$_GET['category']` like `render_block_on_archive()` does, then `do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"collateral_category","label":"Collateral categories"} /-->' )`. Tests:
   - `test_lists_collateral_terms_in_name_order_with_all_first` (create `Logo` then `Flyer`; pills = All, Flyer, Logo; `data-filter` = `''`, `flyer`, `logo`)
   - `test_all_only_when_there_are_no_terms`
   - `test_all_is_pressed_by_default`
   - `test_empty_terms_are_listed` (terms with no collateral still render)
   - `test_document_terms_do_not_leak_into_the_collateral_filter`
   - `test_url_category_marks_that_pill_pressed` (`?category=logo`), `test_unknown_category_falls_back_to_all` (`nope`, `''`, a valid `document_category` slug, `<script>`), `test_url_value_is_never_echoed`
   - `test_trigger_shows_active_category_name` (dropdown first paint: trigger label `Logo` for `?category=logo`, `All` otherwise)
   - `test_group_is_labelled_collateral_categories` (`aria-label="Collateral categories"`, `role="group"`)
   - `test_default_attributes_still_render_document_categories` (no attrs -> `aria-label="Document categories"`, document terms)
   - `test_unknown_taxonomy_renders_nothing` (`{"taxonomy":"nope"}` -> empty string)
   - `test_taxonomy_attribute_is_sanitised` (`{"taxonomy":"collateral_category\"><b>"}` -> empty or safe, no markup injected)
   - `test_view_script_needs_no_shelf` (read `view.js`; contains `if ( shelf )` guard around `filterShelf`, and `querySelectorAll( '.wp-block-devportfolio-document-categories' )`; this pins the "no grid yet" contract)
Gate: new class green, then `Devportfolio_Test_Document_Categories` and `Devportfolio_Test_Document_Categories_Dropdown` **unchanged and green**, then the full theme suite. Commit `feat(theme): let the category filter block read any taxonomy`, footer `Refs: DPC-002, DPC-003`.

### Phase 4: the page (DPC-000, DPC-001)
1. Add `templates/archive-collateral.html` (5.2).
2. Tests in `tests/test-collateral-page.php`, class `Devportfolio_Test_Collateral_Page`, `@group theme`:
   - `test_template_exists_and_is_the_collateral_archive_template` (file exists; `get_block_templates()` / `resolve_block_template` for the `collateral` archive returns slug `archive-collateral`; if the resolver is awkward, assert the file and `locate_block_template( ..., 'archive', array( 'archive-collateral.php', 'archive.php' ) )` is not required, file-level assertion is the minimum)
   - `test_title_is_an_h3_brand_collaterals_with_the_documents_title_class` (exact `<h3 class="wp-block-heading documents-page__title">Brand Collaterals</h3>`, level 3)
   - `test_template_order_is_title_then_content_then_categories_block` (as `test_template_places_block_under_title_inside_content_container`), and the block markup carries `"taxonomy":"collateral_category"`
   - `test_main_uses_the_shared_page_classes` (`documents-page`, `documents-page__content`, constrained layout; header and footer parts present)
   - `test_template_has_no_document_blocks` (no `document-shelf`, no `document-modal`)
   - `test_page_renders_title_and_filter_without_collateral` (zero collateral, terms Flyer/Logo: render the template with `do_blocks` of its markup after `go_to` the archive; contains `Brand Collaterals`, the group, three pills; no `PHP` notice text; `error_get_last()` unchanged) and `test_page_renders_with_no_terms_and_no_collateral` (only All, no exception)
   - `test_page_with_a_published_collateral_still_renders_the_same` (create one published `collateral` + term assignment; same output: no shelf expected yet)
   Navigation, appended to `tests/test-navigation.php` (or new `tests/test-collateral-navigation.php` if the existing file's structure resists): `test_collaterals_link_is_current_on_the_collateral_archive` (`go_to` archive, `do_blocks` of `parts/header.html` as the existing tests do, the Collaterals `<a>` has `aria-current="page"`, no other link does), `test_collaterals_link_is_not_current_on_documents_archive`, `test_collaterals_link_target_is_the_archive_link`.
3. Manual/browser check (no browser runner in the repo; precedent: headless Chrome with puppeteer-core against `https://devportfolio.ddev.site/collaterals/`, or by hand):
   - Seed categories (Phase 1) and create one published collateral in wp-admin (or `ddev wp post create --post_type=collateral --post_title="Test flyer" --post_status=publish`) and one more run with none.
   - 1440px: title weight 200 large, page 1440px wide, pills Flyer, Logo with All pressed, band sticky under the header while scrolling (add `ddev wp post generate`-style filler or shrink the window height to scroll), nothing else below the filter.
   - 820px and 390px: dropdown trigger "Category: All" replaces the pills, list opens, choosing Logo closes it, label updates, URL `?category=logo`; page padding-top trimmed; title 36-52px.
   - `/collaterals/?category=logo` loads with Logo pressed and trigger "Logo" before any click. `/collaterals/?category=nope` loads with All and the URL loses `category` after load.
   - Click Logo then Flyer then Back, Back, Forward: pressed pill/label follow the URL, **no full page reload** (check `performance.getEntriesByType('navigation').length` stays 1 or watch for a flash), console shows no errors.
   - `prefers-reduced-motion: reduce`: change applies instantly, no errors.
   - Header: Collaterals underlined/filled as the active link; Documents is not.
   - `/documents/` unchanged (spot check: title, pills, books, modal still work).
Gate: the whole theme suite, then everything in section 8. Commit `feat(theme): add the Brand Collaterals page`, footer `Refs: DPC-000, DPC-001`.

### Phase 5: docs
`AGENTS.md` edits: Theme architecture section: add `templates/archive-collateral.html` to the templates list (Brand Collaterals page: `<h3>Brand Collaterals</h3>` plus the categories block with `taxonomy` `collateral_category`, no grid yet); extend the `blocks/document-categories/` paragraph with the two attributes (`taxonomy` default `document_category`, `label` default "Document categories"; unknown taxonomy renders nothing; classes and `view.js` unchanged; still no-op without a shelf); add `assets/css/page.css` (the shared `.documents-page` rules, enqueued on the `document` and `collateral` archives) and fix "only bundled assets are font files"; update expected test totals (use the numbers measured in Phase 5's final run); add the new test files to the Theme suite list. Commit `docs(agents): document the Collaterals page`, footer `Refs: DPC-000`. Also mark in this plan's section 10 what was actually built.

## 6A. Conventional commits

### Rules (from `AGENTS.md` Git section and the DPB-009 plan)
- Format: `type(scope): description`. Type and scope lowercase, one space after the colon, description imperative with a lowercase first letter (proper nouns such as Collaterals keep capitals), no trailing period, whole subject line **72 characters or fewer**.
- Types used here: `feat`, `refactor`, `test`, `docs`, `chore`. Scopes: `custom-post-types-dp` for plugin files, `theme` for `wp-content/themes/devportfolio/`, `agents` for `AGENTS.md`, `plans` for `.claude/plans/`.
- One concern per commit. Each commit leaves both suites green (run the phase gate before committing).
- Body (optional, wrapped at 72): why, not what. Footer line `Refs: DPC-NNN[, DPC-NNN]`. End every commit message with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Stage explicit paths (never `git add -A`): untracked plan files from other tickets are in the working tree and must not be swept in.
- Do not commit seeded database rows, `wp-config*`, dumps or `.phpunit.result.cache`.

### Commit list (in order)

| # | Phase | Subject | Stages | Footer |
|---|-------|---------|--------|--------|
| 1 | 0 | `docs(plans): add DPC-000 to DPC-004 collaterals page plan` | this plan file only | `Refs: DPC-000, DPC-001, DPC-002, DPC-003, DPC-004` |
| 2 | 1 | `feat(custom-post-types-dp): add collateral categories seeder` | `data/seeders/collateral-categories.csv`, `includes/seeders/collateral-categories.php` | `Refs: DPC-004` |
| 3 | 1 | `test(custom-post-types-dp): cover collateral categories seeder` | `tests/seeders/test-collateral-categories.php` | `Refs: DPC-004` |
| 4 | 1 | `chore(custom-post-types-dp): bump version to 0.25.0` | `custom-post-types-dp.php` | `Refs: DPC-004` |
| 5 | 1 | `docs(agents): document collateral categories seeder` | `AGENTS.md` | `Refs: DPC-004` |
| 6 | 2 | `refactor(theme): share the page layout styles between archives` | `assets/css/page.css`, `blocks/document-shelf/style.css`, `functions.php`, the existing tests' changed read path | `Refs: DPC-000` |
| 7 | 2 | `test(theme): cover the shared page styles` | `tests/test-page-styles.php` | `Refs: DPC-000` |
| 8 | 3 | `feat(theme): let the category filter block read any taxonomy` | `blocks/document-categories/block.json`, `render.php` | `Refs: DPC-002, DPC-003` |
| 9 | 3 | `test(theme): cover the collateral category filter` | `tests/test-collateral-categories.php` | `Refs: DPC-002, DPC-003` |
| 10 | 4 | `feat(theme): add the Brand Collaterals page` | `templates/archive-collateral.html` | `Refs: DPC-000, DPC-001` |
| 11 | 4 | `test(theme): cover the Brand Collaterals page and nav link` | `tests/test-collateral-page.php`, `tests/test-navigation.php` (or its new sibling) | `Refs: DPC-000, DPC-001` |
| 12 | 5 | `docs(agents): document the Collaterals page` | `AGENTS.md`, this plan's section 10 | `Refs: DPC-000, DPC-001, DPC-002, DPC-003` |

A code commit and its test commit within one phase may be squashed when the test file is small, but never merge code from different phases into one commit. If a gate fails, amend the commit that introduced the problem (before pushing) or add a `fix(theme): ...` / `fix(custom-post-types-dp): ...` commit with the same footer; never leave a red commit in the history.

### Example full message
```
feat(custom-post-types-dp): add collateral categories seeder

Creates Flyer and Logo from data/seeders/collateral-categories.csv.
Matches an existing term by slug or name and skips it, so a second
run changes nothing; an explicit run recreates a deleted term.

Refs: DPC-004
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

### Branch and pull request
- Branch `feat/DPC-000-004-Collaterals-Page` from `main`; push only when asked.
- PR title (72 characters or fewer, same format): `feat(theme): add the Brand Collaterals page and category seeder`. Body follows `.github/pull_request_template.md` (tick "New feature"; Description, Implementation, How to Test from the phase gates) and ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.
- Merge with a normal PR merge commit (`Merge pull request #N from ...`, as in recent history); do not squash away the commit list above.

## 7. File inventory

New:
- `.claude/plans/collaterals/DPC-000-001-002-003-004-Collaterals-Page-Header-Categories-Seed.md` (this file)
- `wp-content/plugins/custom-post-types-dp/data/seeders/collateral-categories.csv`
- `wp-content/plugins/custom-post-types-dp/includes/seeders/collateral-categories.php`
- `wp-content/plugins/custom-post-types-dp/tests/seeders/test-collateral-categories.php`
- `wp-content/themes/devportfolio/templates/archive-collateral.html`
- `wp-content/themes/devportfolio/assets/css/page.css`
- `wp-content/themes/devportfolio/tests/test-page-styles.php`, `test-collateral-categories.php`, `test-collateral-page.php` (+ nav tests in `test-navigation.php`)

Changed:
- `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php` (version)
- `wp-content/themes/devportfolio/blocks/document-categories/{block.json,render.php}`
- `wp-content/themes/devportfolio/blocks/document-shelf/style.css` (rules moved out)
- `wp-content/themes/devportfolio/functions.php` (enqueue)
- existing theme tests: only the path they read the moved rules from
- `AGENTS.md`

Not touched: `parts/header.html`, `view.js`, `document-categories/style.css`, `theme.json`, any other plugin file.

## 8. Final verification (before opening the PR)

```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
ddev wp cptdp seed --all --dry-run
ddev wp rewrite flush && curl -sI https://devportfolio.ddev.site/collaterals/ | head -1   # 200
```
`git status` must show no stray files (`.phpunit.result.cache`, vendor and uploads are ignored). Do not commit the seeded DB rows; they are data, not code.

PR (target `main`): title `feat(theme): add the Brand Collaterals page and category seeder`, body from `.github/pull_request_template.md` (tick "New feature"; Description, Implementation, How to Test = the gate commands and the browser checklist in Phase 4), plus the line "Only existing-test change: file path of the moved `.documents-page` rules".

## 9. Risks and how to handle them

- **Moving the page CSS (Phase 2)** is the only change that can alter Documents. Mitigation: verbatim move, `test_shelf_css_no_longer_holds_page_rules`, side-by-side visual check at three widths. If a moved rule turns out to depend on shelf-only variables (`--documents-gutter` is defined in the same rule; check `grep -rn "documents-gutter" blocks/`), keep that variable's definition where it is used or move its users too; do not leave it undefined.
- **Specificity/order:** `page.css` loads as a separate stylesheet; the moved rules do not conflict with rules in block CSS (same selectors were in one file before). Confirm no rule in the shelf CSS relied on appearing after them.
- **The Site Editor** shows the new template as "Archive: Collateral" and the block with defaults in the inspector (no UI for the attributes); acceptable, the attributes are template-authored.
- **Dependency "DPC-024"** is undefined here; if it turns out to be the grid/modal, it extends 5.2's slot and needs its own shelf-contract decision (D2).
- No grid means DPC-003's in-place filtering and fade/glide cannot be seen on this page yet; say so in the PR rather than faking a shelf.

## 10. As-built notes
Implemented without the deferred collateral grid or modal. Added the archive template, shared archive stylesheet, taxonomy-aware category block, navigation coverage, and the `Flyer`/`Logo` term seeder. The plugin suite passes 742 tests with 12 skipped; the theme suite passes 358 tests; the modal Node suite passes 153 tests. Existing Documents CSS assertions now read the moved shared stylesheet; no behavior or selectors were renamed.

The implementation was split into the 12 commits in section 6A on `feat/DPC-000-004-Collaterals-Page`; no seeded database rows or unrelated plan files were committed.
