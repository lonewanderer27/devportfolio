# Plan: Videos page shell, title, category filter and category state

Tickets **DPV-000** (page), **DPV-001** (title), **DPV-002** (categories), **DPV-003** (category state, URL). Branch `feat/DPV-000-003-Videos-Page`, created **from `main`**.

**How to read this plan (for the implementing agent):** every file you must create or change is given in full or as an exact "find this / replace with that" pair. Do the phases in section 6 **in order**. After each phase run its **Gate** commands and only continue when they pass. Do not "improve" anything that is not listed. If something you find does not match what this plan says (a line is missing, a test already exists), **stop and report it** instead of guessing. Do not commit or push anything unless the user asks; section 6A lists the commits to make *when asked*.

Related plans (history only, do not edit): `collaterals/DPC-000-001-002-003-004-Collaterals-Page-Header-Categories-Seed.md` (the page this one copies; it already made the filter block taxonomy-aware and moved the page CSS into `assets/css/page.css`, which is why this ticket is small), `documents/DPD-041-Header-Category-Filter-Dropdown-On-Mobile.md` (phone dropdown, already merged), `documents/DPD-034-Design-Redesign-According-To-Design-System.md` (section *Reference page for future pages*), `backend/DPB-004-video-category-taxonomy.md`, `backend/DPB-006-011-018-Video.md` (the `video` CPT, already shipped), `backend/DPB-024-seed-videos.md` (video seeder).

Paths below are relative to the repo root `/Users/adriane.puzon/Documents/PHP Projects/devportfolio` unless they start with `$T` or `$P`:

- `$T` = `wp-content/themes/devportfolio`
- `$P` = `wp-content/plugins/custom-post-types-dp`

---

## 1. Goal and acceptance criteria

1. `/videos/` renders from a new block template `$T/templates/archive-video.html`. It is an **archive template, not a Page**: a Page slugged `videos` would be shadowed by the `video` post type's archive rewrite (same reason as Documents and Collaterals). Do **not** create a Page.
2. The navigation link **Videos** (already in `$T/parts/header.html`, line 11, `kind: post-type-archive`) gets `aria-current="page"` on `/videos/` and on no other page. **Do not edit the header.** It is proven by a test.
3. **No intro line and no sticky "Hire me" bar** anywhere on the page. The page uses the shared header, Hanken Grotesk, theme tokens, and the same page width and phone rules as Documents and Brand Collaterals (the `.documents-page` rules in `$T/assets/css/page.css`).
4. Title: exactly `<h3 class="wp-block-heading documents-page__title">Videos</h3>`. Same look as the Documents title (44-72px, weight 200, -0.04em, smaller on phones). **No new CSS.**
5. Category filter: the existing block `devportfolio/document-categories` with attributes `taxonomy` = `video_category` and `label` = `Video categories`. It shows a synthetic "All" first (pressed by default), then one pill per term **in name order**: **AI Generated, Short Form, Video Edits**. Terms with no video are still shown. It sticks under the sticky header on a frosted band. At 820px and below the pills become a dropdown showing the active category. **All of this already exists in the block; you build nothing here.**
6. State (DPV-003): one pill active; clicking another sets it and shows `?category=<slug>` in the URL (All = no param); Back/Forward restore it without reload; `/videos/?category=short-form` loads with Short Form pressed; an unknown slug falls back to All; the change runs in a View Transition (none with reduced motion); the script dispatches `devportfolio:category-change` with `{ slug }`. **All of this already exists** in `$T/blocks/document-categories/view.js` and `render.php`; this ticket only adds tests that prove it for `video_category`.
7. With no published video the page renders the title and the filter with **no PHP notice, no error**.
8. The seeded video categories match the story: after `ddev wp cptdp seed video-categories` on an empty term table there are exactly `AI Generated`, `Short Form`, `Video Edits`.
9. Both PHP suites and the Node suite pass. **Documents and Collaterals tests stay unchanged** except the one edit to `test-page-styles.php` in Phase 2.

## 2. Out of scope (do NOT build, even if it looks easy)

- The video grid, video items, the video modal and their JavaScript (tickets DPV-004 onwards and DPV-026). Because there is no grid, the template must contain **only** the title and the categories block. Do not add a placeholder block, an HTML comment, an empty container or a "No videos" message.
- DPV-027 (empty-state message, hiding the pills when there are no videos).
- Renaming any `documents-page*` / `documents-categories__*` class, or copying/renaming the `document-*` blocks.
- Changes to `parts/header.html`, `theme.json`, `assets/css/page.css`, `blocks/document-categories/*`, or any other block.
- A migration or an "update existing terms" seeder (see D4).
- Adding an intro paragraph, a "Hire me" bar, buttons or any other content.

## 3. Facts already established (do not re-derive; just trust them)

- Plugin `custom-post-types-dp` is version `0.27.0` (header line 5 and `define( 'CPTDP_VERSION', '0.27.0' )` on line 20 of `$P/custom-post-types-dp.php`).
- The `video` CPT has archive `/videos/`. `video_category` is a flat, public taxonomy attached to `video`, rewrite `/video-category/`, and has **no default term**.
- Template precedent: `$T/templates/archive-collateral.html` (header part, `main.documents-page.collaterals-page`, h3, `div.documents-page__content` with the categories block, grid and modal, footer part). The video template is the same file minus grid and modal.
- `$T/blocks/document-categories/render.php` (already taxonomy-aware): reads `taxonomy` (default `document_category`) and `label`; unknown taxonomy renders nothing; lists terms with `orderby => name`, `hide_empty => false`; validates `?category=` against that taxonomy's slugs; outputs `role="group"`, `aria-label`, one trigger button (`documents-categories__trigger`, label span `documents-categories__trigger-label`), and inside `div.documents-categories__list` one `button.documents-categories__pill[data-filter][aria-pressed]` for All (`data-filter=""`) and one per term. So a rendered filter has **N+1 `data-filter="` occurrences** for N terms.
- `$T/functions.php` lines 68-95: `devportfolio_enqueue_page_styles()` enqueues `assets/css/page.css` only when `is_post_type_archive( array( 'document', 'collateral' ) )`. **Without a change `/videos/` would load with no page width and an unstyled title.** This is the one theme PHP change.
- `$T/tests/test-page-styles.php` has the method `test_page_css_is_enqueued_on_both_archives_only` that loops `array( 'document', 'collateral' )`.
- `$T/tests/test-navigation.php` already has `test_links_point_to_the_right_targets` (covers the Videos href) and, for Collaterals, `test_collaterals_link_is_current_on_its_archive_only` and `test_collaterals_link_targets_the_collateral_archive` (lines ~133-147). The helpers `nav_items( $url )` and `active_labels( $items )` are **private methods of that class**, so the Videos nav tests must be added **inside that same file**.
- Existing seed data: `$P/data/seeders/video-categories.csv` is
  ```
  title,slug
  Content Creation,content-creation
  Short Form,short-form
  AI-Generated,ai-generated
  ```
  and `$P/data/seeders/videos.csv` assigns category `content-creation` to the rows "Test Vertical Video 4" (order 40) and "Test Vertical Video 6" (order 60). Term seeders skip a term that already exists by slug or name and never modify it.
- Places that name the old categories: `$P/tests/seeders/test-video-categories.php`, the two CSVs, and `AGENTS.md` (the `video_category` sentence in the Overview, line 7). `$P/tests/seeders/test-videos.php` does **not** mention `content-creation` (checked), so it needs no change. `.claude/plans/backend/DPB-024-seed-videos.md` is history: leave it.

## 4. Decisions

| # | Decision | Why |
|---|----------|-----|
| D1 | Reuse `devportfolio/document-categories` unchanged. | Block already does everything (story notes say so). |
| D2 | Template classes: `documents-page videos-page` on `<main>`; `videos-page` has **no CSS rules**. Title reuses `documents-page__title`; content wrapper reuses `documents-page__content`. | Shared rules key on the `documents-*` classes; `videos-page` is just a hook for later tickets (same as `collaterals-page`). |
| D3 | Change the seeded categories to the story's three: `AI Generated`/`ai-generated`, `Short Form`/`short-form`, `Video Edits`/`video-edits`; and move the two videos from `content-creation` to `video-edits`. **Order the CSV alphabetically** (AI Generated, Short Form, Video Edits) so the seeder's `created` list is in the same order as the pills. | The story lists exactly these; name order then gives the listed order with no custom field. |
| D4 | Existing databases keep their old terms (a seeder never modifies a term). Document a one-off manual rename (section 6, Phase 1 step 7); add no migration. | Seeders are idempotent content, not structure. |
| D5 | Bump the plugin version `0.27.0` -> `0.28.0` in **both** places in `$P/custom-post-types-dp.php`. | A shipped seed change = a feature bump (AGENTS.md Git rules). |
| D6 | Template contains no HTML comments and no placeholder for the grid. | The Site Editor can mangle comments; the grid ticket adds its own block. |

## 5. Exact file contents

### 5.1 New file `$T/templates/archive-video.html`

Copy exactly (tabs for indentation, as in `archive-collateral.html`):

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"documents-page videos-page","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group documents-page videos-page" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">

	<!-- wp:heading {"level":3,"className":"documents-page__title"} -->
	<h3 class="wp-block-heading documents-page__title">Videos</h3>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"documents-page__content","layout":{"type":"default"}} -->
	<div class="wp-block-group documents-page__content">

		<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->

	</div>
	<!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

### 5.2 Edit `$T/functions.php`

Find (about lines 68-74):

```php
	/**
	 * Enqueue styles shared by the Documents and Collaterals archives.
	 */
	function devportfolio_enqueue_page_styles() {
		if ( ! is_post_type_archive( array( 'document', 'collateral' ) ) ) {
			return;
		}
```

Replace with:

```php
	/**
	 * Enqueue styles shared by the Documents, Collaterals and Videos archives.
	 */
	function devportfolio_enqueue_page_styles() {
		if ( ! is_post_type_archive( array( 'document', 'collateral', 'video' ) ) ) {
			return;
		}
```

Change nothing else in that function. (The `devportfolio-viewer` style below it stays collateral-only.)

### 5.3 Edit `$T/tests/test-page-styles.php`

Find the whole method:

```php
	public function test_page_css_is_enqueued_on_both_archives_only(): void {
		require_once $this->theme_dir() . '/functions.php';
		add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_page_styles' );
		foreach ( array( 'document', 'collateral' ) as $type ) {
```

Replace the method name and the array only:

```php
	public function test_page_css_is_enqueued_on_the_page_archives_only(): void {
		require_once $this->theme_dir() . '/functions.php';
		add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_page_styles' );
		foreach ( array( 'document', 'collateral', 'video' ) as $type ) {
```

Leave the rest of the method (dequeue, `go_to`, `do_action`, the assertions, the front-page `assertFalse`) exactly as it is.

### 5.4 Seed data

`$P/data/seeders/video-categories.csv` (replace the whole file; keep the trailing newline the old file had):

```
title,slug
AI Generated,ai-generated
Short Form,short-form
Video Edits,video-edits
```

`$P/data/seeders/videos.csv`: in the **two** rows whose `category` column is `content-creation` (order 40 and order 60), change that value to `video-edits`. Change nothing else on any row. Check afterwards: `grep -c content-creation $P/data/seeders/videos.csv` prints `0` and `grep -c video-edits $P/data/seeders/videos.csv` prints `2`.

`$P/custom-post-types-dp.php`: line 5 ` * Version:           0.27.0` -> ` * Version:           0.28.0`; line 20 `define( 'CPTDP_VERSION', '0.27.0' );` -> `define( 'CPTDP_VERSION', '0.28.0' );`. Run `grep -rn "0\.27\.0" $P --include=*.php | grep -v vendor | grep -v tests` and fix any other occurrence that is the plugin version (not a comment about the DPC-025 feature in AGENTS.md).

`$P/includes/seeders/video-categories.php`: **no change** (its docblock only mentions "Short Form", which stays valid).

## 6. Work packages (in order; one gate per phase)

### Phase 0: environment and baseline

```bash
cd "/Users/adriane.puzon/Documents/PHP Projects/devportfolio"
git switch main && git switch -c feat/DPV-000-003-Videos-Page
ddev start
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"   # needed after any ddev restart
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit 2>&1 | tail -5
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit 2>&1 | tail -5
```
Write down the two baseline test counts (AGENTS.md says plugin 847 tests / 12 skipped, theme 501; trust what you measure). If a baseline run already fails, **stop and report**; do not continue on a red baseline. Untracked plan files from other tickets exist in the working tree: leave them alone.

### Phase 1: seed data and plugin version (DPV-002)

1. Edit the two CSVs and the plugin version exactly as in 5.4.
2. Edit `$P/tests/seeders/test-video-categories.php`. Make **these exact replacements** (the file is a copy of the structure shown; find each method by name):
   - `test_csv_is_well_formed`: replace the two `assertSame` lines with
     ```php
     		$this->assertSame( array( 'AI Generated', 'Short Form', 'Video Edits' ), wp_list_pluck( $rows, 'title' ) );
     		$this->assertSame( array( 'ai-generated', 'short-form', 'video-edits' ), wp_list_pluck( $rows, 'slug' ) );
     ```
   - `test_creates_every_term_with_name_and_slug`: first assertion becomes `$this->assertSame( array( 'AI Generated', 'Short Form', 'Video Edits' ), $result['created'] );`; and the last assertion `$this->assertSame( 'AI-Generated', $term->name );` becomes `$this->assertSame( 'AI Generated', $term->name );`.
   - `test_matches_the_existing_hand_made_short_form_term`: `$this->assertSame( array( 'Content Creation', 'AI-Generated' ), $result['created'] );` becomes `$this->assertSame( array( 'AI Generated', 'Video Edits' ), $result['created'] );` and `$this->assertSame( array( 'ai-generated', 'content-creation', 'short-form' ), $this->slugs() );` becomes `$this->assertSame( array( 'ai-generated', 'short-form', 'video-edits' ), $this->slugs() );`.
   - `test_matches_an_existing_term_by_name_when_the_slug_differs`: `array( 'ai-generated', 'content-creation', 'shorts' )` becomes `array( 'ai-generated', 'shorts', 'video-edits' )`.
   - `test_restores_a_deleted_term_without_touching_the_others`: `$this->assertSame( array( 'AI-Generated' ), $result['created'] );` becomes `$this->assertSame( array( 'AI Generated' ), $result['created'] );`.
   - Nothing else in the file changes (the three-term counts stay `3`).
   - Afterwards run `grep -n "Content Creation\|content-creation\|AI-Generated" $P/tests/seeders/test-video-categories.php`: it must print nothing.
3. `AGENTS.md`: make these exact replacements (use `grep -n` first to confirm each string appears once):
   - `creates \`Content Creation\`/\`Short Form\`/\`AI-Generated\`` -> `creates \`AI Generated\`/\`Short Form\`/\`Video Edits\``
   - `Master plugin \`custom-post-types-dp\` (v0.27.0,` -> `Master plugin \`custom-post-types-dp\` (v0.28.0,`
   - Leave the DPC-025 paragraph's `(v0.27.0, DPC-025)` as it is (it names the version a feature shipped in).
4. Run the gate:
   ```bash
   ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter 'CPTDP_Test_Seeder_Video_Categories' --testdox
   ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter 'Video' 
   ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
   ```
   All must pass (full run: same total as baseline, 0 failures).
5. Dry run, writes nothing: `ddev wp cptdp seed --all --dry-run` (must list `video-categories` and `videos` and report no error).
6. (Only when the user asks you to touch the dev database; take `ddev snapshot` first.) Rename the old terms by hand, see step 7.
7. **One-off for databases that already have the old terms** (put this in the PR "How to Test"; do not automate it):
   ```bash
   ddev wp term list video_category --fields=term_id,name,slug
   ddev wp term update video_category <id of content-creation> --name="Video Edits" --slug=video-edits
   ddev wp term update video_category <id of ai-generated> --name="AI Generated"
   ```
   The `videos` that were in Content Creation keep their assignment because the term id does not change.

### Phase 2: load the shared page styles on `/videos/` (DPV-000)

1. Apply 5.2 (`functions.php`).
2. Apply 5.3 (`test-page-styles.php`).
3. Gate:
   ```bash
   ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter Devportfolio_Test_Page_Styles --testdox
   ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
   ```
   Both green.

### Phase 3: the page, filter tests and nav tests (DPV-000, DPV-001, DPV-002, DPV-003)

1. Create `$T/templates/archive-video.html` exactly as in 5.1.
2. Create `$T/tests/test-video-page.php` with exactly this content:

```php
<?php
/**
 * Tests for the Videos archive shell (DPV-000, DPV-001).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Page extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function template(): string {
		return (string) file_get_contents( $this->theme_dir() . '/templates/archive-video.html' );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) ) {
			devportfolio_register_blocks();
		}
	}

	public function tear_down(): void {
		unset( $_GET['category'] );
		parent::tear_down();
	}

	/**
	 * Render the categories block exactly as the template places it, on the video archive.
	 */
	private function render_filter(): string {
		$category = $_GET['category'] ?? null;
		$this->go_to( get_post_type_archive_link( 'video' ) );
		if ( null !== $category ) {
			$_GET['category'] = $category;
		}

		return do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->' );
	}

	public function test_template_file_exists(): void {
		$this->assertFileExists( $this->theme_dir() . '/templates/archive-video.html' );
	}

	public function test_title_is_an_h3_with_the_documents_title_class(): void {
		$this->assertStringContainsString(
			'<h3 class="wp-block-heading documents-page__title">Videos</h3>',
			$this->template()
		);
		$this->assertStringContainsString( '<!-- wp:heading {"level":3,"className":"documents-page__title"} -->', $this->template() );
	}

	public function test_template_has_the_shared_page_shell(): void {
		$markup = $this->template();
		$this->assertStringContainsString( 'template-part {"slug":"header"', $markup );
		$this->assertStringContainsString( 'template-part {"slug":"footer"', $markup );
		$this->assertStringContainsString( 'class="wp-block-group documents-page videos-page"', $markup );
		$this->assertStringContainsString( '"layout":{"type":"constrained"}', $markup );
		$this->assertStringContainsString( 'class="wp-block-group documents-page__content"', $markup );
	}

	public function test_template_order_is_title_then_content_then_categories_block(): void {
		$markup = $this->template();
		$title   = strpos( $markup, 'documents-page__title">Videos</h3>' );
		$content = strpos( $markup, 'class="wp-block-group documents-page__content"' );
		$block   = strpos( $markup, '<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->' );

		$this->assertNotFalse( $title );
		$this->assertNotFalse( $content );
		$this->assertNotFalse( $block );
		$this->assertLessThan( $content, $title );
		$this->assertLessThan( $block, $content );
	}

	public function test_template_has_no_intro_line_and_no_hire_me_bar(): void {
		$markup = $this->template();
		$this->assertStringNotContainsStringIgnoringCase( 'hire me', $markup );
		$this->assertStringNotContainsString( 'wp:paragraph', $markup );
		$this->assertStringNotContainsString( 'wp:button', $markup );
		$this->assertStringNotContainsString( 'wp:buttons', $markup );
	}

	public function test_template_has_no_document_or_collateral_blocks_yet(): void {
		$markup = $this->template();
		$this->assertStringNotContainsString( 'document-shelf', $markup );
		$this->assertStringNotContainsString( 'document-modal', $markup );
		$this->assertStringNotContainsString( 'collateral-grid', $markup );
		$this->assertStringNotContainsString( 'collateral-modal', $markup );
	}

	public function test_page_styles_have_no_videos_page_rules(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/assets/css/page.css' );
		$this->assertStringNotContainsString( 'videos-page', $css );
	}

	public function test_page_renders_title_and_filter_with_no_video(): void {
		wp_insert_term( 'Short Form', 'video_category' );
		wp_insert_term( 'AI Generated', 'video_category' );
		wp_insert_term( 'Video Edits', 'video_category' );

		$html = $this->render_filter();

		$this->assertStringContainsString( 'aria-label="Video categories"', $html );
		$this->assertStringContainsString( 'role="group"', $html );
		// All + 3 terms = 4 pills.
		$this->assertSame( 4, substr_count( $html, 'data-filter="' ) );
		$this->assertStringNotContainsString( 'Notice', $html );
		$this->assertStringNotContainsString( 'Warning', $html );
	}

	public function test_page_renders_with_no_terms_and_no_video(): void {
		$html = $this->render_filter();

		$this->assertSame( 1, substr_count( $html, 'data-filter="' ) );
		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
	}

	public function test_page_with_a_published_video_still_renders_the_filter(): void {
		$term = wp_insert_term( 'Short Form', 'video_category' );
		$id   = self::factory()->post->create(
			array(
				'post_type'   => 'video',
				'post_status' => 'publish',
				'post_title'  => 'Test video',
			)
		);
		wp_set_object_terms( $id, array( (int) $term['term_id'] ), 'video_category' );

		$html = $this->render_filter();

		$this->assertSame( 2, substr_count( $html, 'data-filter="' ) );
		$this->assertStringContainsString( 'data-filter="short-form"', $html );
	}
}
```

3. Create `$T/tests/test-video-categories.php` with exactly this content:

```php
<?php
/**
 * Tests for the category filter on the Videos archive (DPV-002, DPV-003).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Categories extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) ) {
			devportfolio_register_blocks();
		}
	}

	public function tear_down(): void {
		unset( $_GET['category'] );
		parent::tear_down();
	}

	/**
	 * Create the three story categories in a deliberately scrambled order, so only name order can sort them.
	 */
	private function create_terms(): void {
		wp_insert_term( 'Video Edits', 'video_category' );
		wp_insert_term( 'AI Generated', 'video_category' );
		wp_insert_term( 'Short Form', 'video_category' );
	}

	private function render(): string {
		$category = $_GET['category'] ?? null;
		$this->go_to( get_post_type_archive_link( 'video' ) );
		if ( null !== $category ) {
			$_GET['category'] = $category;
		}

		return do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->' );
	}

	/**
	 * data-filter values of the pills, in document order.
	 *
	 * @return string[]
	 */
	private function filters( string $html ): array {
		preg_match_all( '/data-filter="([^"]*)"/', $html, $matches );

		return $matches[1];
	}

	public function test_lists_video_terms_in_name_order_with_all_first(): void {
		$this->create_terms();

		$this->assertSame( array( '', 'ai-generated', 'short-form', 'video-edits' ), $this->filters( $this->render() ) );
	}

	public function test_all_is_pressed_by_default(): void {
		$this->create_terms();
		$html = $this->render();

		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'data-filter="short-form" aria-pressed="false"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">All<', $html );
	}

	public function test_empty_terms_are_listed_and_other_taxonomies_do_not_leak(): void {
		wp_insert_term( 'Reports', 'document_category' );
		wp_insert_term( 'Flyer', 'collateral_category' );
		wp_insert_term( 'Empty Category', 'video_category' );

		$html = $this->render();

		$this->assertStringContainsString( '>Empty Category<', $html );
		$this->assertStringNotContainsString( '>Reports<', $html );
		$this->assertStringNotContainsString( '>Flyer<', $html );
	}

	public function test_url_category_marks_that_pill_pressed(): void {
		$this->create_terms();
		$_GET['category'] = 'short-form';

		$html = $this->render();

		$this->assertStringContainsString( 'data-filter="short-form" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'data-filter="" aria-pressed="false"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">Short Form<', $html );
	}

	/**
	 * @dataProvider unknown_categories
	 */
	public function test_unknown_category_falls_back_to_all( string $value ): void {
		$this->create_terms();
		wp_insert_term( 'Reports', 'document_category' );
		$_GET['category'] = $value;

		$html = $this->render();

		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">All<', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function unknown_categories(): array {
		return array(
			'unknown slug'                => array( 'nope' ),
			'empty'                       => array( '' ),
			'slug of another taxonomy'    => array( 'reports' ),
			'markup'                      => array( '<script>' ),
		);
	}

	public function test_group_is_labelled_video_categories(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'aria-label="Video categories"', $html );
		$this->assertStringContainsString( 'role="group"', $html );
	}

	public function test_view_script_keeps_the_filter_contract_the_video_grid_will_use(): void {
		$js = (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/view.js' );

		$this->assertStringContainsString( 'devportfolio:category-change', $js );
		$this->assertStringContainsString( 'pushState', $js );
		$this->assertStringContainsString( 'popstate', $js );
		$this->assertStringContainsString( 'startViewTransition', $js );
	}
}
```

   If `test_view_script_keeps_the_filter_contract_the_video_grid_will_use` fails because one of those four words is not in `view.js` (run `grep -n "category-change\|pushState\|popstate\|startViewTransition" $T/blocks/document-categories/view.js` to see), **do not change `view.js`**: delete only the failing `assertStringContainsString` line and mention it in your report.

4. Edit `$T/tests/test-navigation.php`: insert these two methods **directly after** the method `test_collaterals_link_targets_the_collateral_archive` (and before `test_header_part_stores_no_hardcoded_domain`):

```php
	public function test_videos_link_is_current_on_its_archive_only(): void {
		$active = $this->active_labels( $this->nav_items( get_post_type_archive_link( 'video' ) ) );
		$this->assertSame( array( 'Videos' ), $active );

		foreach ( array( 'document', 'collateral' ) as $type ) {
			$active = $this->active_labels( $this->nav_items( get_post_type_archive_link( $type ) ) );
			$this->assertNotContains( 'Videos', $active );
		}
	}

	public function test_videos_link_targets_the_video_archive(): void {
		$items  = $this->nav_items( get_post_type_archive_link( 'video' ) );
		$videos = array_values( array_filter( $items, static fn( array $item ): bool => 'Videos' === $item['label'] ) );
		$this->assertCount( 1, $videos );
		$this->assertSame( get_post_type_archive_link( 'video' ), $videos[0]['href'] );
		$this->assertTrue( $videos[0]['aria_current'] );
	}
```

5. Run the gate:
   ```bash
   T="ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit"
   $T --filter Devportfolio_Test_Video_Page --testdox
   $T --filter Devportfolio_Test_Video_Categories --testdox
   $T --filter Devportfolio_Test_Navigation --testdox
   $T                                   # whole theme suite
   ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
   ```
   All green. If a **new** test fails, fix the test or the template to match section 5; if an **existing** test fails, you broke something: undo your last change and report.

6. Browser check (no browser test runner in the repo; do it by hand or with headless Chrome/puppeteer-core). Prepare data:
   ```bash
   ddev wp cptdp seed video-categories
   ddev wp cptdp seed videos          # needs the aspect ratios too: run `ddev wp cptdp seed aspect-ratios` first if it complains
   ddev wp rewrite flush
   ```
   Then open `https://devportfolio.ddev.site/videos/` and verify each line (write pass/fail in your report):
   - 1440px wide: large light title "Videos"; content width 1440px (same as `/documents/`); pills **All, AI Generated, Short Form, Video Edits** with All filled; the pill band stays under the header while scrolling (make the window short or add content to scroll); **no intro line, no "Hire me" bar**; nothing below the pills (no grid yet is expected).
   - 820px and 390px: a dropdown trigger showing "Category: All" replaces the pills; tapping lists every category; choosing "Short Form" closes the list, the trigger says "Short Form", the URL becomes `?category=short-form`.
   - Load `/videos/?category=short-form`: Short Form is active before any click. Load `/videos/?category=nope`: All is active and the `category` param disappears from the URL after load.
   - Click Short Form, then Video Edits, then browser Back twice and Forward once: the active pill and the URL follow every step, the page does **not** reload (the tab does not flash; `performance.getEntriesByType('navigation').length` stays 1), the console shows no errors.
   - With `prefers-reduced-motion: reduce` the change applies instantly, no errors.
   - The header link "Videos" looks active; "Documents" and "Collaterals" do not.
   - Spot check `/documents/` and `/collaterals/`: unchanged.
   - Expected and **not a failure**: nothing filters in place because there is no video grid yet.

### Phase 4: docs

1. `AGENTS.md`, line 112 (the `templates/` bullet): after the text `archive-collateral\` (Brand Collaterals: ... )` entry, add a new entry. Do it with `grep -n "archive-collateral" AGENTS.md` to find the bullet, then append, immediately after the collateral entry's closing parenthesis and before the next ` — ` or sentence, this text: ``, `archive-video` (Videos page: `<h3>Videos</h3>` plus the `devportfolio/document-categories` block with `taxonomy` `video_category` and label "Video categories"; `<main>` has `documents-page videos-page`, `videos-page` has no rules of its own; no grid or modal yet)``.
2. In `AGENTS.md`, find the sentence that says where `assets/css/page.css` is enqueued (`grep -n "page.css" AGENTS.md`). If one exists, change "the `document` and `collateral` archives" to "the `document`, `collateral` and `video` archives". If none exists, add to the `functions.php` bullet in "Theme architecture": "`devportfolio_enqueue_page_styles()` loads `assets/css/page.css` (the shared `.documents-page` rules) on the `document`, `collateral` and `video` archives."
3. In `AGENTS.md` "Theme suite" notes add one paragraph, in the same style as the neighbours: "Also `tests/test-video-page.php` (`Devportfolio_Test_Video_Page`, DPV-000/001): the template file, h3, shell classes, order, no intro or Hire me bar, no grid or modal blocks yet, no `videos-page` CSS rules, the filter rendering with and without videos. `tests/test-video-categories.php` (`Devportfolio_Test_Video_Categories`, DPV-002/003): name order with All first, pressed state, empty terms, no taxonomy leaks, `?category=` handling, label, the view script contract. The Videos nav link tests are in `tests/test-navigation.php`."
4. Update the expected test totals sentence in the Tests section with the numbers you measured at the end of Phase 3 and the plugin run (same sentence format: "plugin N tests (12 skipped), theme M tests").
5. Append a section `## 10. As-built notes` to **this plan file** listing what was built, the final test counts, and any deviation from the plan.
6. Gate: `git status` shows only the files in section 7 plus the untracked plan files that were already untracked at the start; `git diff --stat` looks reasonable.

## 6A. Conventional commits (make these only when the user asks you to commit)

Rules (AGENTS.md Git section):
- Subject `type(scope): description`: lowercase type and scope, one space after the colon, imperative description starting lowercase, **no trailing period, 72 characters or fewer**. Types used: `feat`, `test`, `chore`, `docs`. Scopes: `custom-post-types-dp` (anything under `$P/`), `theme` (anything under `$T/`), `agents` (`AGENTS.md`), `plans` (`.claude/plans/`).
- One concern per commit. Every commit leaves the plugin and theme suites green.
- Stage **explicit paths** (`git add <path> ...`), never `git add -A` or `git add .`. Never stage the other untracked plan files, `wp-config*`, dumps, seeded database rows or `.phpunit.result.cache`.
- Message = subject, optional body wrapped at 72 saying why, a footer `Refs: DPV-NNN[, DPV-NNN]`, then a last line `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Commit order below matters (each commit's tests need the earlier commits).

| # | Phase | Subject | Stage exactly | Footer |
|---|-------|---------|---------------|--------|
| 1 | 0 | `docs(plans): add DPV-000 to DPV-003 videos page plan` | `.claude/plans/videos/DPV-000-001-002-003-Videos-Page-Header-Categories.md` | `Refs: DPV-000, DPV-001, DPV-002, DPV-003` |
| 2 | 1 | `feat(custom-post-types-dp): align video categories with the page` | `$P/data/seeders/video-categories.csv`, `$P/data/seeders/videos.csv` | `Refs: DPV-002` |
| 3 | 1 | `test(custom-post-types-dp): cover the renamed video categories` | `$P/tests/seeders/test-video-categories.php` | `Refs: DPV-002` |
| 4 | 1 | `chore(custom-post-types-dp): bump version to 0.28.0` | `$P/custom-post-types-dp.php` | `Refs: DPV-002` |
| 5 | 2 | `feat(theme): load the shared page styles on the Videos archive` | `$T/functions.php` | `Refs: DPV-000` |
| 6 | 2 | `test(theme): cover the shared page styles on the Videos archive` | `$T/tests/test-page-styles.php` | `Refs: DPV-000` |
| 7 | 3 | `feat(theme): add the Videos page` | `$T/templates/archive-video.html` | `Refs: DPV-000, DPV-001, DPV-002, DPV-003` |
| 8 | 3 | `test(theme): cover the Videos page, filter and nav link` | `$T/tests/test-video-page.php`, `$T/tests/test-video-categories.php`, `$T/tests/test-navigation.php` | `Refs: DPV-000, DPV-001, DPV-002, DPV-003` |
| 9 | 4 | `docs(agents): document the Videos page and video categories` | `AGENTS.md`, this plan file | `Refs: DPV-000, DPV-001, DPV-002, DPV-003` |

Commit 5 must come before commit 7 only in the sense that both are green on their own; commit 6 is green because the page-styles test only needs the enqueue condition.

Example full message (commit 2):

```
feat(custom-post-types-dp): align video categories with the page

The Videos page lists AI Generated, Short Form and Video Edits. Rename
the seeded terms to match and move the two videos that used Content
Creation to Video Edits. Existing databases keep their old terms; the
seeder never modifies a term.

Refs: DPV-002
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

Branch: `feat/DPV-000-003-Videos-Page` from `main`. Push only when asked.
PR title (72 characters or fewer): `feat(theme): add the Videos page and align video categories`. Body from `.github/pull_request_template.md`: tick "New feature"; **Description** (what the page is, the category rename), **Implementation** (the template, the one-condition enqueue change, seed data, tests), **How to Test** (the Phase 1-3 gate commands, the D4 rename commands and the browser checklist), plus the line "Only existing-test change: the archive list in `test-page-styles.php`" and "No video grid yet, so in-place filtering is not visible on this page". End the body with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit; do not squash.

## 7. File inventory

New:
- `.claude/plans/videos/DPV-000-001-002-003-Videos-Page-Header-Categories.md` (this file)
- `$T/templates/archive-video.html`
- `$T/tests/test-video-page.php`
- `$T/tests/test-video-categories.php`

Changed:
- `$T/functions.php` (one array in one condition + its docblock)
- `$T/tests/test-page-styles.php` (method name + array)
- `$T/tests/test-navigation.php` (two methods added)
- `$P/data/seeders/video-categories.csv`
- `$P/data/seeders/videos.csv` (two cells)
- `$P/custom-post-types-dp.php` (version in two places)
- `$P/tests/seeders/test-video-categories.php` (strings only)
- `AGENTS.md`

Must NOT be touched: `$T/parts/header.html`, `$T/theme.json`, `$T/assets/css/page.css`, anything in `$T/blocks/`, `$P/includes/**`, `$P/tests/seeders/test-videos.php`, `.claude/plans/backend/*`.

## 8. Final verification (run all before reporting done)

```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
ddev wp cptdp seed --all --dry-run
ddev wp rewrite flush && curl -sI https://devportfolio.ddev.site/videos/ | head -1     # expect: HTTP/2 200
git status --short
```
Expected: 0 failures in each suite (test counts = baseline + the new tests), the dry run lists seeders and writes nothing, `/videos/` answers 200, and `git status` shows only the section 7 files plus the plan files that were untracked before you started.

## 9. Risks, edge cases and what to do about them

- **Old terms in the dev database.** After Phase 1 the *tests* use a clean term table and pass, but the dev database still has `Content Creation` and `AI-Generated`, so the page there shows the old names until the D4 rename is done. This is expected. Do not edit the database yourself unless the user asks (take `ddev snapshot` first).
- **A test fails only on the full suite, not alone.** Theme tests share one DB; each new test class resets its state in `set_up()`/`tear_down()` (memo flush, `do_action( 'cptdp_register_post_types' )`, `unset( $_GET['category'] )`). If you see leakage, check you copied those exactly.
- **`devportfolio_register_blocks` missing.** It is defined in `$T/functions.php` (line ~55) and the tests `require_once` that file in `set_up()`. Do not move it.
- **Pill counting.** The block renders the trigger button plus the list; only the list buttons carry `data-filter`. That is why a filter with 3 terms has 4 `data-filter="` matches. If your count is off by one, re-read `render.php`, do not change it.
- **"Title only" when there is no video.** The pills still show (empty terms are listed on purpose). Hiding them is DPV-027. Do not add a `:has()` rule or a message.
- **No grid yet.** DPV-003's in-place filtering and the glide animation can only be seen once the grid ticket adds a block that listens for `devportfolio:category-change` and toggles items by `data-video-categories`. This plan only pins the filter side. Say so in the PR; do not fake a grid.
- **Mid-run surprises.** If the baseline is red, a file does not match the "find" text in section 5, or a command fails for an environment reason (DDEV not running, test library missing: re-run the `install-wp-tests.sh` command), stop and report exactly what you saw rather than working around it.
