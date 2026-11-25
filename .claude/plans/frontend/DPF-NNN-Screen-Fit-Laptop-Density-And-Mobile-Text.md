# DPF-NNN: Screen fit (laptop density and mobile text)

## Goal

The site was designed and tuned on a 2560px-wide (2K) monitor. Two problems show up on the screens most visitors use:

1. **Laptops look oversized.** Every size in the theme is a fixed pixel value, so a 1366px or 1280px window shows the same 168px name, 72px page title, 44px stat values and 917px contact card in about half the space.
2. **Phones have text that is too small.** Book cover labels are 8.5px, software tool names 9px, the hero roles line 10.5px, and collateral meta lines, service tags and a details pill 11.5px. The contact form fields are 14px, so iPhone Safari zooms the whole page when one is tapped.
3. **Phones do not show the sub-services.** Each "What I do" row lists its child services as tags, but `@media (max-width: 640px)` hides them (`.service-list__tags { display: none; }`, from DPH-009 AC 15). Phone visitors only see the three top-level services.

This plan fixes all three:

- **Part A**, a laptop density tier: one media query appended to the stylesheets that cause the oversizing. 2K stays pixel-identical.
- **Part B**, a text floor: every piece of text is raised to a readable minimum on every screen, plus 16px form fields on touch screens.
- **Part C**, sub-services on phones: the tags show at every width. This supersedes DPH-009 AC 15 ("At 640px and below the tags are hidden").

## Rules this plan introduces (they also go into AGENTS.md)

- **Laptop tier:** `@media (min-width: 1025px) and (max-width: 1599px)`, appended once at the end of each stylesheet that needs it. It starts above every tablet and phone rule (header 1024, hero 1024, selected work 1000, viewer 900/1100 and below). It covers budget-laptop windows: 1280 (1920 at 150% scaling), 1366, 1440 and 1536 (1920 at 125%). 1600px and wider (2K) never matches.
- **Text floor:** 12px for mixed-case text; 11px for uppercase, letter-spaced micro-labels (cap height reads like 15px lowercase); 16px for text inputs and textareas on touch screens (`@media (pointer: coarse)`), so iOS does not zoom on focus. Two exceptions are drawn artwork scaled with their graphic: the book cover labels (10px) and the film-strip timecodes in Selected work (9.5px). `tests/test-screen-fit.php` lists every size below 12px that is allowed, so a new one fails the suite.

## Baseline (measured 2026-11-25, headless Chrome)

### Laptops (window height = usable area under the browser's toolbars, e.g. 1366×768 → 1366×657)

| Measure | 1280×632 | 1366×657 | 1536×750 | 2560×1305 (reference) |
|---|---|---|---|---|
| Archive: first book's top edge | 355px (56% of the window) | 355px (54%) | 355px (47%) | 355px (27%) |
| Documents: books per row (width) | 5 (218px) | 5 (235px) | 6 (213px) | 6 (213px) |
| Videos: tiles per row | 7 | 7 | 8 | 8 |
| Collaterals: columns | 5 | 5 | 6 | 6 |
| Hero name font size | 153.6px (84% of width) | 163.9px (84%) | 168px | 168px (57%) |
| Stats / services / contact height | 114 / 406 / 917 | 114 / 406 / 917 | same | same (contact 70% of the window) |
| Home page total height | 4307 | 4332 | 4316 | 4871 |

Books and tiles get **bigger** on a laptop than on 2K: the grids use `auto-fill` with a minimum track width, so the 1302px laptop column fits one fewer track and each one stretches.

### Phones and tablet (360×740, 375×667, 412×915, 768×1024; every visible text node and the open viewers scanned)

| Text | Size now | Where |
|---|---|---|
| Book cover category, format and owner (`.documents-book__cat`, `__ft`) | 8.5px | Documents shelf and Home, ≤820px |
| Software tool names (`.software-tools__name`) | 9px (11.5px from 601px) | Home |
| Film-strip timecode (`.work-strip__time`) | 9.5px | Home Selected work (artwork, kept) |
| Hero roles line (`.hero__roles`) | 10.5px uppercase | Home, ≤620px |
| Collateral meta line (`.collaterals-item__meta`) | 11.5px | Collaterals, ≤700px |
| Service tags (`.service-list__tag`) | 11.5px | Home, all sizes |
| Documents details-sheet category pill (`.documents-mobile__details-category`) | 11.5px | Documents viewer, phones |
| Up-next duration (`.videos-modal__nextlen`) | 11px | Videos viewer |
| Viewer captions and result labels (`*__caption`, `dt`) | 11px uppercase | Videos and Collaterals viewers (kept, uppercase floor) |
| Contact name, email and message fields | 14px | Home contact: iOS zooms the page on focus |
| Sub-service tags (`.service-list__tags`) | not shown | Home "What I do", ≤640px (`display: none`) |

## Expected result (values below were injected live and measured the same way)

| Measure | 1280×632 | 1366×657 | 2560×1305 |
|---|---|---|---|
| Filter bar top | 196 (was 233) | 196 (was 233) | 233 (unchanged) |
| Documents: books per row (width) | 6 (183px) | 6 (197px) | 6 (213px, unchanged) |
| Videos: tiles per row | 8 | 8 | unchanged |
| Collaterals: columns | 6 | 6 | 6 (unchanged) |
| Hero name font size | 115.2px | 122.9px (63% of width) | 168px (unchanged) |
| Stats / services / contact height | 93 / 336 / 722 | 93 / 336 / 722 | 114 / 406 / 917 (unchanged) |
| Home page total height | 3838 (−469) | 3863 (−469) | 4871 (unchanged) |

After Part B, the phone rescan finds no text below 12px except the 11px uppercase labels, the 10px cover labels and the 9.5px timecodes. At 360px the software tool names fit their three columns at 12px (longest: "Google Workspace", "Meta Business Suite", one line each). The hero roles line wraps to two balanced lines.

After Part C, at 360px each service row shows its sub-services as 12px chips under the benefit line, wrapping onto two or three lines (row 01: six chips on three lines; row 03: three chips on two lines). Nothing overflows the column.

## Decisions (already made)

1. **Each block owns its rules.** The laptop tier is appended to each stylesheet that owns the rule. Text floor changes edit the existing declarations in place. No global override sheet, no `zoom`, no new tokens.
2. **`zoom` / a global scale is rejected.** `zoom` on `html` also scales `100dvh` (hero, all three viewers) and changes what the viewer and masonry scripts measure.
3. **Part A leaves body text, navigation, filter pills, buttons and theme.json font presets alone.** 14px text is already small on a laptop. The 36px pills and 44px tap areas stay (design system, WCAG 2.5.8).
4. **Page shell (laptop):** top padding 48 → 32px, title `clamp(44px, 4vw, 56px)` with 18px below it (was 72px and 28px), Home section padding 48 → 32px. Home's main padding is not affected: `.wp-site-blocks > main:has(> .hero)` already forces `padding-top: 0 !important` with higher specificity.
5. **Grids (laptop):** shelf track 200 → 170px (gaps 40/32 → 32/24, top padding 44 → 28), with cover titles 23/19/16 → 20/17/14px scoped to `.documents-shelf`, so the Home book fan is unchanged. Video tiles `minmax(150px, 1fr)`. Collateral no-JS columns 220 → 190px. `masonry.js` `minTileWidth()` returns 190 below a 1440px content width. The content column is capped at 1440px, so that is the same split as the media query, give or take 64px.
6. **Hero (laptop):** `min(12vw, 168px)` → `min(9vw, 168px)`, close to the share of the width the name has on 2K. The portrait follows by itself (it is sized from `em` and `--hero-h`).
7. **Home sections (laptop):** stats value 44 → 36px with cell padding 16/20px; services row padding 24 → 16px and title 28 → 22px; testimonial quote 28 → 22px. Contact: band padding 56 → 32px, heading 34 → 28px, lead margin 28 → 20px, card padding 28 → 22px, and calendar days `aspect-ratio: 1` → `3 / 2` (`min-height: 40px` stays).
8. **Text floor values:**
   - Book cover labels on phones 8.5 → 10px, the same as desktop.
   - Software tool names 9px (and 11.5px from 601px) → 12px on every screen. The `min-width: 601px` override becomes redundant and is deleted.
   - Hero roles line on phones 10.5 → 11px (uppercase floor), plus `text-wrap: balance`.
   - Collateral meta line: the 11.5px phone override is deleted, so it keeps its 12px base.
   - Service tags, the documents details pill and the up-next duration → 12px.
   - Contact text fields → 16px under `(pointer: coarse)`. Mouse users keep 14px.
9. **These Part B values also apply on 2K** (tool names 11.5 → 12px, service tags 11.5 → 12px, up-next duration 11 → 12px). A floor is about legibility, not screen width; on 2K these are +0.5 to +1px.
10. **Sub-services show on phones (Part C).** The phone rule's `display: none` is replaced with `margin-top: 8px` (10px above it on wider screens; the phone row is tighter). The rows have no link, so the chips do not compete with a tap target. `test_phone_rules` in `tests/test-service-list.php` pinned the hiding, so it is updated in the same commit.

## Out of scope

- The **Selected work** cards (`blocks/selected-work/`) on laptops. Their 300px height drives the book fan, film-strip and collateral glimpse geometry (`216px` inner-height constants and the `--fw` maths), so they need their own ticket.
- Laptop sizing in the three **viewer modals**. They already fit short windows and were checked at 1366×657.
- The **site header** (61px), the footer and the inline 34px section headings in `templates/front-page.html`.
- **Text colour contrast.** Secondary text uses `--wp--preset--color--mid` (`#8a8a8a`, 3.4:1 on white, below WCAG AA's 4.5:1 for text under 18px). That makes small grey text (tool names, tags, meta lines, stat labels) harder to read on phones too. It is listed under Open questions because changing a palette colour affects every page.
- Phone **layout** changes (only text sizes change on phones).

## Facts (check before editing; stop and report if any is false)

Paths are relative to `wp-content/themes/devportfolio/`.

1. `assets/css/page.css` holds `.documents-page .documents-page__title {` with `font-size: clamp(44px, 6.4vw, 72px);`.
2. `templates/front-page.html` has `<main class="wp-block-group documents-page home-page">`, and its three `home-section` groups are direct children with inline `padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)`.
3. `blocks/hero/style.css` has `.wp-site-blocks > main:has(> .hero) {` with `padding-top: 0 !important;`, `.hero__stage {` with `font-size: min(12vw, 168px);`, and inside `@media (max-width: 620px)` the `.hero__roles` rule `margin: 0 0 12px;` / `font-size: 10.5px;`.
4. `blocks/document-shelf/style.css` `.documents-shelf {` has `grid-template-columns: repeat(auto-fill, minmax(min(100%, 200px), 1fr));`, `gap: 40px 32px;`, `padding: 44px 0 0;`.
5. `blocks/document-book/style.css` has `.documents-book__ttl {` `font-size: 23px;`; the `long` and `xlong` rules use 19px and 16px; and inside `@media (max-width: 820px)` the rule `.documents-book__cat,` / `.documents-book__ft {` / `font-size: 8.5px;`.
6. `blocks/video-grid/style.css` `.videos-grid {` has `grid-template-columns: repeat(auto-fill, minmax(clamp(104px, 72px + 8vw, 180px), 1fr));`.
7. `blocks/collateral-grid/style.css` `.collaterals-grid {` has `column-width: 220px;` and `margin-top: 44px;`; `.collaterals-grid::before {` has `top: -44px;`; `.collaterals-item__meta {` has `font-size: 12px;`; and there is a `@media (max-width: 700px)` block holding exactly `.collaterals-item__title { font-size: 12.5px; }` and `.collaterals-item__meta { font-size: 11.5px; }`.
8. `blocks/collateral-grid/masonry.js` `minTileWidth()` has `if ( width <= 1000 ) {` / `return 190;` followed by `return 220;`.
9. `blocks/collateral-grid/tests/masonry.test.mjs` has the test `'minimum tile width steps at 420, 700 and 1000'` ending with `minTileWidth( 1000 ), 190` and `minTileWidth( 1001 ), 220`.
10. `blocks/stats-strip/style.css` `.stats-strip__item` has `padding: 22px 24px;`; `.stats-strip__value` has `font-size: clamp(30px, 4vw, 44px);`.
11. `blocks/service-list/style.css` `.service-list__row` has `padding: 24px 8px;`; `.service-list__title` has `font-size: clamp(22px, 3vw, 28px);`; `.service-list__tag` has `padding: 1px 10px;` then `font-size: 11.5px;`.
12. `blocks/testimonials/style.css` `.testimonials__quote p` has `font-size: clamp(20px, 2.6vw, 28px);`.
13. `blocks/contact/style.css`: `.contact {` has `padding: clamp(24px, 5vw, 56px);`; `.contact__heading` has `font-size: clamp(26px, 3.4vw, 34px);`; `.contact__lead` has `margin: 0 auto 28px;`; `.contact__card` has `--card-pad: 28px;`; `.contact-picker__day` has `aspect-ratio: 1;` and `min-height: 40px;`; the rule `.contact input[type='text'], .contact input[type='email'], .contact textarea {` has `font: inherit;`.
14. `blocks/software-tools/style.css` `.software-tools__name {` has `font-size: 9px;` then `line-height: 1.25;`, followed by a `@media (min-width: 601px)` block holding only `.software-tools__name { font-size: 11.5px; }`.
15. `blocks/shared/viewer.css` has the rule for `.videos-mobile__details-category,` / `.collaterals-mobile__details-category,` / `.documents-mobile__details-category {` with `padding: 2px 10px;` then `font-size: 11.5px;`.
16. `blocks/video-modal/style.css` `.videos-modal__nextlen {` has `padding: 1px 5px;` then `font-size: 11px;`.
16b. `blocks/service-list/style.css` has, inside `@media (max-width: 640px)`, the rule `.service-list__tags {` / `display: none;` / `}`. `tests/test-service-list.php` `test_phone_rules()` ends with `$this->assertMatchesRegularExpression( '/\.service-list__tags \{[^}]*display: none;/', $phone );`. `docs/agents/home.md` contains the phrase `tags hidden at 640px and below`.
17. No stylesheet contains `1599px` yet: `grep -rn "1599px" wp-content/themes/devportfolio/blocks wp-content/themes/devportfolio/assets` prints nothing.
18. Every stylesheet the steps append to ends with a newline.

Each find string in Part B was checked to occur exactly once in its file.

## Implementation

Run every command from the repo root and do the steps in order, committing after each one (see Conventional commits). Appended blocks and edits use **tabs**; keep them.

### Part A: laptop density tier

#### Step 1: page shell (`assets/css/page.css`)

```bash
cat >> wp-content/themes/devportfolio/assets/css/page.css <<'EOF'

/*
 * Laptop density tier (DPF-NNN): laptop windows (1025px to 1599px) get the same layout with tighter spacing and a
 * smaller title, so the first row of content sits higher on a short screen. 1600px and wider (2K) is unchanged.
 * The same query closes every stylesheet the tier touches (tests/test-screen-fit.php).
 */
@media (min-width: 1025px) and (max-width: 1599px) {
	/* The archive templates set 48px inline; Home keeps 0 (hero/style.css wins with a more specific !important). */
	.documents-page {
		padding-top: 32px !important;
	}

	.documents-page .documents-page__title {
		margin-bottom: 18px;
		font-size: clamp(44px, 4vw, 56px);
	}

	/* front-page.html sets 48px inline on each section. */
	.home-page > .home-section {
		padding-top: 32px !important;
		padding-bottom: 32px !important;
	}
}
EOF
```

#### Step 2: grids (`document-shelf`, `video-grid`, `collateral-grid`, masonry)

```bash
cat >> wp-content/themes/devportfolio/blocks/document-shelf/style.css <<'EOF'

/* Laptop density tier (DPF-NNN): six books per row on a 1280 to 1440px column, as on 2K, instead of five larger ones. */
@media (min-width: 1025px) and (max-width: 1599px) {
	.documents-shelf {
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 170px), 1fr));
		gap: 32px 24px;
		padding-top: 28px;
	}

	/* The covers are narrower, so their titles step down too (scoped to the shelf: the Home book fan keeps its sizes). */
	.documents-shelf .documents-book__ttl {
		font-size: 20px;
	}

	.documents-shelf .documents-book[data-title-length="long"] .documents-book__ttl {
		font-size: 17px;
	}

	.documents-shelf .documents-book[data-title-length="xlong"] .documents-book__ttl {
		font-size: 14px;
	}
}
EOF

cat >> wp-content/themes/devportfolio/blocks/video-grid/style.css <<'EOF'

/* Laptop density tier (DPF-NNN): eight tiles per row on a 1280 to 1440px column instead of seven larger ones. */
@media (min-width: 1025px) and (max-width: 1599px) {
	.videos-grid {
		grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
	}
}
EOF

cat >> wp-content/themes/devportfolio/blocks/collateral-grid/style.css <<'EOF'

/* Laptop density tier (DPF-NNN): the no-JS columns match masonry.js (190px below a 1440px column), and the dot field above the first row is shorter. */
@media (min-width: 1025px) and (max-width: 1599px) {
	.collaterals-grid {
		column-width: 190px;
		margin-top: 28px;
	}

	.collaterals-grid::before {
		top: -28px;
	}
}
EOF
```

Edit `wp-content/themes/devportfolio/blocks/collateral-grid/masonry.js`:

Find:
```js
	if ( width <= 1000 ) {
		return 190;
	}
	return 220;
```
Replace with:
```js
	// Below a full 1440px column (laptop windows, DPF-NNN) 190px gives six columns instead of five wide ones.
	if ( width < 1440 ) {
		return 190;
	}
	return 220;
```

Edit `wp-content/themes/devportfolio/blocks/collateral-grid/tests/masonry.test.mjs`:

Find:
```js
test( 'minimum tile width steps at 420, 700 and 1000', () => {
```
Replace with:
```js
test( 'minimum tile width steps at 420, 700 and 1440', () => {
```

Find:
```js
	assert.equal( minTileWidth( 1000 ), 190 );
	assert.equal( minTileWidth( 1001 ), 220 );
```
Replace with:
```js
	assert.equal( minTileWidth( 1000 ), 190 );
	assert.equal( minTileWidth( 1001 ), 190 );
	assert.equal( minTileWidth( 1439 ), 190 );
	assert.equal( minTileWidth( 1440 ), 220 );
```

If another assertion in that file now fails (a `columnCount` or `layout` case at a width between 1001 and 1439 that expected 220-based columns), stop and report it; do not change its expected value.

#### Step 3: home hero and sections (laptop)

```bash
cat >> wp-content/themes/devportfolio/blocks/hero/style.css <<'EOF'

/* Laptop density tier (DPF-NNN): the name keeps about the share of the width it has on 2K (12vw filled 84% of a laptop window). */
@media (min-width: 1025px) and (max-width: 1599px) {
	.hero__stage {
		font-size: min(9vw, 168px);
	}
}
EOF

cat >> wp-content/themes/devportfolio/blocks/stats-strip/style.css <<'EOF'

/* Laptop density tier (DPF-NNN). */
@media (min-width: 1025px) and (max-width: 1599px) {
	.stats-strip__item {
		padding: 16px 20px;
	}

	.stats-strip__value {
		font-size: 36px;
	}
}
EOF

cat >> wp-content/themes/devportfolio/blocks/service-list/style.css <<'EOF'

/* Laptop density tier (DPF-NNN). */
@media (min-width: 1025px) and (max-width: 1599px) {
	.service-list__row {
		padding: 16px 8px;
	}

	.service-list__title {
		font-size: 22px;
	}
}
EOF

cat >> wp-content/themes/devportfolio/blocks/testimonials/style.css <<'EOF'

/* Laptop density tier (DPF-NNN). */
@media (min-width: 1025px) and (max-width: 1599px) {
	.testimonials__quote p {
		font-size: 22px;
	}
}
EOF

cat >> wp-content/themes/devportfolio/blocks/contact/style.css <<'EOF'

/* Laptop density tier (DPF-NNN): the band was taller than a laptop window. Days stay at least 40px tall (min-height). */
@media (min-width: 1025px) and (max-width: 1599px) {
	.contact {
		padding: 32px;
	}

	.contact__heading {
		font-size: 28px;
	}

	.contact__lead {
		margin-bottom: 20px;
	}

	.contact__card {
		--card-pad: 22px;
	}

	.contact-picker__day {
		aspect-ratio: 3 / 2;
	}
}
EOF
```

### Part B: text floor

#### Step 4: raise small text (edits in place)

`wp-content/themes/devportfolio/blocks/document-book/style.css` (inside `@media (max-width: 820px)`):

Find:
```css
	.documents-book__cat,
	.documents-book__ft {
		font-size: 8.5px;
	}
```
Replace with:
```css
	/* Cover artwork: the labels keep the desktop 10px (the text floor's artwork exception, DPF-NNN). */
	.documents-book__cat,
	.documents-book__ft {
		font-size: 10px;
	}
```

`wp-content/themes/devportfolio/blocks/software-tools/style.css`:

Find:
```css
	font-size: 9px;
	line-height: 1.25;
```
Replace with:
```css
	font-size: 12px;
	line-height: 1.25;
```

Find (and delete, including the blank line after it):
```css
@media (min-width: 601px) {
	.software-tools__name {
		font-size: 11.5px;
	}
}

```
Replace with nothing (empty string).

`wp-content/themes/devportfolio/blocks/hero/style.css` (inside `@media (max-width: 620px)`):

Find:
```css
		margin: 0 0 12px;
		font-size: 10.5px;
	}
```
Replace with:
```css
		margin: 0 0 12px;
		font-size: 11px;
		text-wrap: balance;
	}
```

`wp-content/themes/devportfolio/blocks/collateral-grid/style.css`:

Find:
```css
@media (max-width: 700px) {
	.collaterals-item__title {
		font-size: 12.5px;
	}

	.collaterals-item__meta {
		font-size: 11.5px;
	}
}
```
Replace with:
```css
@media (max-width: 700px) {
	.collaterals-item__title {
		font-size: 12.5px;
	}
}
```

`wp-content/themes/devportfolio/blocks/service-list/style.css`:

Find:
```css
	padding: 1px 10px;
	font-size: 11.5px;
```
Replace with:
```css
	padding: 1px 10px;
	font-size: 12px;
```

`wp-content/themes/devportfolio/blocks/shared/viewer.css`:

Find:
```css
	padding: 2px 10px;
	font-size: 11.5px;
```
Replace with:
```css
	padding: 2px 10px;
	font-size: 12px;
```

`wp-content/themes/devportfolio/blocks/video-modal/style.css`:

Find:
```css
	padding: 1px 5px;
	font-size: 11px;
```
Replace with:
```css
	padding: 1px 5px;
	font-size: 12px;
```

#### Step 5: contact fields on touch screens

```bash
cat >> wp-content/themes/devportfolio/blocks/contact/style.css <<'EOF'

/* Touch screens (DPF-NNN): iOS Safari zooms the page into any text field under 16px when it gets focus. */
@media (pointer: coarse) {
	.contact input[type='text'],
	.contact input[type='email'],
	.contact textarea {
		font-size: 16px;
	}
}
EOF
```

### Part C: sub-services on phones

#### Step 5b: show the tags at every width

`wp-content/themes/devportfolio/blocks/service-list/style.css` (inside `@media (max-width: 640px)`):

Find:
```css
	.service-list__tags {
		display: none;
	}
```
Replace with:
```css
	/* Phones list the sub-services too (DPF-NNN, replacing DPH-009 AC 15), a little closer to the benefit line. */
	.service-list__tags {
		margin-top: 8px;
	}
```

`wp-content/themes/devportfolio/tests/test-service-list.php` (in `test_phone_rules()`):

Find:
```php
		$this->assertMatchesRegularExpression( '/\.service-list__tags \{[^}]*display: none;/', $phone );
```
Replace with:
```php
		// The sub-services show on phones too (DPF-NNN); only the gap above them is tighter.
		$this->assertMatchesRegularExpression( '/\.service-list__tags \{[^}]*margin-top: 8px;/', $phone );
		$this->assertStringNotContainsString( 'display: none', $phone );
```

`docs/agents/home.md`: replace `tags hidden at 640px and below` with `tags shown at every width (8px above them at 640px and below, DPF-NNN)`.

### Step 6: test (`wp-content/themes/devportfolio/tests/test-screen-fit.php`, new file)

```php
<?php
/**
 * Screen fit tests (DPF-NNN): the laptop density tier and the text floor.
 *
 * There is no browser runner, so these check the CSS: every stylesheet the laptop tier touches closes with the
 * same media query once, each tier holds its values, the small text was raised, and no new text size below 12px
 * appears outside the allowed list. The 2K values are pinned by the existing block tests.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Screen_Fit extends WP_UnitTestCase {

	const QUERY = '(min-width: 1025px) and (max-width: 1599px)';

	const TIER_FILES = array(
		'assets/css/page.css',
		'blocks/document-shelf/style.css',
		'blocks/video-grid/style.css',
		'blocks/collateral-grid/style.css',
		'blocks/hero/style.css',
		'blocks/stats-strip/style.css',
		'blocks/service-list/style.css',
		'blocks/testimonials/style.css',
		'blocks/contact/style.css',
	);

	/**
	 * File contents relative to the theme.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	private function read( string $path ): string {
		$file = dirname( __DIR__ ) . '/' . $path;
		$this->assertFileExists( $file );

		return (string) file_get_contents( $file );
	}

	/**
	 * Inner CSS of the first media query in a file, whitespace collapsed.
	 *
	 * @param string $path  Relative path.
	 * @param string $query Text after "@media ".
	 * @return string
	 */
	private function media( string $path, string $query ): string {
		$css   = $this->read( $path );
		$start = strpos( $css, '@media ' . $query );
		$this->assertNotFalse( $start, "@media {$query} missing in {$path}." );

		$open  = strpos( $css, '{', (int) $start );
		$depth = 0;
		$len   = strlen( $css );
		for ( $i = (int) $open; $i < $len; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
				if ( 0 === $depth ) {
					return trim( (string) preg_replace( '/\s+/', ' ', substr( $css, (int) $open + 1, $i - (int) $open - 1 ) ) );
				}
			}
		}

		$this->fail( "@media {$query} in {$path} is not closed." );
	}

	/**
	 * Inner CSS of the laptop tier in one file.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	private function tier( string $path ): string {
		return $this->media( $path, self::QUERY );
	}

	// ----- Laptop density tier ------------------------------------------------------------

	public function test_every_tier_file_closes_with_the_query_once(): void {
		foreach ( self::TIER_FILES as $path ) {
			$css = $this->read( $path );
			$this->assertSame( 1, substr_count( $css, '@media ' . self::QUERY ), "{$path} must hold the laptop tier once." );
			$this->assertGreaterThan( (int) strrpos( $css, '@media (max-width' ), (int) strpos( $css, '@media ' . self::QUERY ), "{$path}: the tier comes after the narrower rules." );
		}
	}

	public function test_page_shell_is_tighter(): void {
		$tier = $this->tier( 'assets/css/page.css' );
		$this->assertStringContainsString( '.documents-page { padding-top: 32px !important; }', $tier );
		$this->assertStringContainsString( 'margin-bottom: 18px; font-size: clamp(44px, 4vw, 56px);', $tier );
		$this->assertStringContainsString( '.home-page > .home-section { padding-top: 32px !important; padding-bottom: 32px !important; }', $tier );
	}

	public function test_shelf_fits_six_books_and_steps_the_titles_down(): void {
		$tier = $this->tier( 'blocks/document-shelf/style.css' );
		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(min(100%, 170px), 1fr)); gap: 32px 24px; padding-top: 28px;', $tier );
		$this->assertStringContainsString( '.documents-shelf .documents-book__ttl { font-size: 20px; }', $tier );
		$this->assertStringContainsString( '.documents-shelf .documents-book[data-title-length="long"] .documents-book__ttl { font-size: 17px; }', $tier );
		$this->assertStringContainsString( '.documents-shelf .documents-book[data-title-length="xlong"] .documents-book__ttl { font-size: 14px; }', $tier );
	}

	public function test_video_and_collateral_grids_use_narrower_tracks(): void {
		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));', $this->tier( 'blocks/video-grid/style.css' ) );

		$collaterals = $this->tier( 'blocks/collateral-grid/style.css' );
		$this->assertStringContainsString( 'column-width: 190px; margin-top: 28px;', $collaterals );
		$this->assertStringContainsString( '.collaterals-grid::before { top: -28px; }', $collaterals );
	}

	public function test_masonry_uses_190px_below_a_full_column(): void {
		$js = $this->read( 'blocks/collateral-grid/masonry.js' );
		$this->assertStringContainsString( 'if ( width < 1440 ) {', $js );
		$this->assertStringNotContainsString( 'if ( width <= 1000 ) {', $js );
	}

	public function test_hero_name_keeps_its_2k_share_of_the_width(): void {
		$this->assertStringContainsString( '.hero__stage { font-size: min(9vw, 168px); }', $this->tier( 'blocks/hero/style.css' ) );
	}

	public function test_home_sections_are_tighter(): void {
		$stats = $this->tier( 'blocks/stats-strip/style.css' );
		$this->assertStringContainsString( '.stats-strip__item { padding: 16px 20px; }', $stats );
		$this->assertStringContainsString( '.stats-strip__value { font-size: 36px; }', $stats );

		$services = $this->tier( 'blocks/service-list/style.css' );
		$this->assertStringContainsString( '.service-list__row { padding: 16px 8px; }', $services );
		$this->assertStringContainsString( '.service-list__title { font-size: 22px; }', $services );

		$this->assertStringContainsString( '.testimonials__quote p { font-size: 22px; }', $this->tier( 'blocks/testimonials/style.css' ) );

		$contact = $this->tier( 'blocks/contact/style.css' );
		$this->assertStringContainsString( '.contact { padding: 32px; }', $contact );
		$this->assertStringContainsString( '.contact__heading { font-size: 28px; }', $contact );
		$this->assertStringContainsString( '.contact__lead { margin-bottom: 20px; }', $contact );
		$this->assertStringContainsString( '.contact__card { --card-pad: 22px; }', $contact );
		$this->assertStringContainsString( '.contact-picker__day { aspect-ratio: 3 / 2; }', $contact );
	}

	// ----- Text floor ---------------------------------------------------------------------

	public function test_phone_book_cover_labels_match_the_desktop_10px(): void {
		$this->assertStringContainsString( '.documents-book__cat, .documents-book__ft { font-size: 10px; }', $this->media( 'blocks/document-book/style.css', '(max-width: 820px)' ) );
		$this->assertStringNotContainsString( '8.5px', $this->read( 'blocks/document-book/style.css' ) );
	}

	public function test_software_tool_names_are_12px_on_every_screen(): void {
		$css = $this->read( 'blocks/software-tools/style.css' );
		$this->assertMatchesRegularExpression( '/\.software-tools__name \{[^}]*font-size: 12px;/', $css );
		$this->assertStringNotContainsString( 'font-size: 9px', $css );
		$this->assertStringNotContainsString( '11.5px', $css );
	}

	public function test_hero_roles_are_11px_and_balanced_on_phones(): void {
		$this->assertStringContainsString( 'margin: 0 0 12px; font-size: 11px; text-wrap: balance;', $this->media( 'blocks/hero/style.css', '(max-width: 620px)' ) );
		$this->assertStringNotContainsString( '10.5px', $this->read( 'blocks/hero/style.css' ) );
	}

	public function test_collateral_meta_keeps_its_12px_on_phones(): void {
		$css = $this->read( 'blocks/collateral-grid/style.css' );
		$this->assertMatchesRegularExpression( '/\.collaterals-item__meta \{[^}]*font-size: 12px;/', $css );
		$this->assertStringNotContainsString( '11.5px', $css );
	}

	public function test_service_tags_details_pill_and_up_next_length_are_12px(): void {
		$this->assertMatchesRegularExpression( '/\.service-list__tag \{[^}]*font-size: 12px;/', $this->read( 'blocks/service-list/style.css' ) );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-category \{[^}]*font-size: 12px;/', $this->read( 'blocks/shared/viewer.css' ) );
		$this->assertMatchesRegularExpression( '/\.videos-modal__nextlen \{[^}]*font-size: 12px;/', $this->read( 'blocks/video-modal/style.css' ) );
	}

	public function test_contact_fields_are_16px_on_touch_screens(): void {
		$this->assertStringContainsString(
			".contact input[type='text'], .contact input[type='email'], .contact textarea { font-size: 16px; }",
			$this->media( 'blocks/contact/style.css', '(pointer: coarse)' )
		);
	}

	public function test_no_text_is_below_the_floor_outside_the_allowed_list(): void {
		// 11px: uppercase, letter-spaced labels. 10px: book cover artwork. 9.5px: film-strip timecodes (artwork).
		$allowed = array(
			'blocks/collateral-modal/style.css' => array( '11px' => 4 ),
			'blocks/document-book/style.css'    => array( '10px' => 3 ),
			'blocks/hero/style.css'             => array( '11px' => 2 ),
			'blocks/selected-work/style.css'    => array( '9.5px' => 1 ),
			'blocks/video-modal/style.css'      => array( '11px' => 4 ),
		);

		$theme = dirname( __DIR__ );
		$files = array_merge( glob( $theme . '/blocks/*/*.css' ) ?: array(), glob( $theme . '/assets/css/*.css' ) ?: array() );
		$found = array();
		foreach ( $files as $file ) {
			preg_match_all( '/font-size:\s*([0-9.]+)px/', (string) file_get_contents( $file ), $m );
			foreach ( $m[1] as $size ) {
				if ( (float) $size < 12 ) {
					$rel                         = substr( $file, strlen( $theme ) + 1 );
					$found[ $rel ][ $size . 'px' ] = ( $found[ $rel ][ $size . 'px' ] ?? 0 ) + 1;
				}
			}
		}
		ksort( $found );

		$this->assertSame( $allowed, $found, 'Text below 12px must be an uppercase label (11px) or artwork; see DPF-NNN.' );
	}
}
```

Write it with tabs as shown (WPCS: `array()`, Yoda conditions, aligned assignments).

### Step 7: docs

Append to `docs/agents/theme-core.md`:

```bash
cat >> docs/agents/theme-core.md <<'EOF'

## Screen fit: laptop density tier and text floor (DPF-NNN)

The theme was tuned on a 2560px monitor with fixed pixel sizes. That looked oversized on budget laptops and left text too small on phones.

- **Laptop tier.** `@media (min-width: 1025px) and (max-width: 1599px)` closes each stylesheet that owns an oversized rule: `assets/css/page.css`, `document-shelf`, `video-grid`, `collateral-grid`, `hero`, `stats-strip`, `service-list`, `testimonials` and `contact`. It tightens the page shell (top padding 32px, title `clamp(44px, 4vw, 56px)`, Home section padding 32px). It narrows the grid tracks: shelf 170px with 20/17/14px cover titles scoped to `.documents-shelf`, videos 150px, collaterals 190px no-JS columns. It shrinks the hero name to `min(9vw, 168px)` and the stats, services, testimonial and contact sizes. `masonry.js` `minTileWidth()` returns 190 below a 1440px content width. Body text, navigation, pills, buttons, the header, Selected work and the viewers are unchanged, and so is everything at 1600px and wider (2K) or 1024px and below.
- **Text floor.** 12px for mixed-case text and 11px for uppercase, letter-spaced labels. Text inputs are 16px under `(pointer: coarse)`, so iOS does not zoom on focus. The only artwork exceptions are the book cover labels (10px) and the Selected work film-strip timecodes (9.5px).

`tests/test-screen-fit.php` checks that the tier appears once per file after the narrower rules, and lists every allowed size below 12px, so a new one fails the suite.
EOF
```

In `AGENTS.md`:

1. In the Tests paragraph, replace `theme 796 tests` with `theme <N> tests`, where `<N>` is the total the theme suite prints in the gate below (expected 810: 796 + 14).
2. In the Conventions list, after the bullet that starts with `- Prefer editing \`theme.json\`, templates and parts over adding PHP/CSS.`, insert this bullet:

```markdown
- **Screen fit** (DPF-NNN): laptop-only sizing goes in `@media (min-width: 1025px) and (max-width: 1599px)` at the end of the block's stylesheet (2K stays unchanged). Text floor: 12px for mixed-case text, 11px for uppercase letter-spaced labels, 16px for text fields under `(pointer: coarse)`. Smaller sizes are allowed only for artwork (book covers, film-strip timecodes) and must be added to the list in `tests/test-screen-fit.php`. See `docs/agents/theme-core.md`.
```

Change nothing else in AGENTS.md.

## Gates

```bash
# theme suite (expected: previous total + 14, all passing)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter Devportfolio_Test_Screen_Fit --testdox

# collateral grid node tests (expected 18 tests, 0 fail)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/collateral-grid 'node --test tests/*.test.mjs'

# every node suite (one line per block; totals as in AGENTS.md)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio 'for d in blocks/*/tests; do set -- $d/*.test.mjs; [ -e "$1" ] || continue; printf "%s " "${d%/tests}"; (cd "${d%/tests}" && node --test --test-reporter=tap tests/*.test.mjs 2>&1 | grep -E "^# (tests|fail)" | tr "\n" " "); echo; done'

# plugin suite (unchanged; run before the PR as AGENTS.md asks)
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit

# PHPCS report (never fails; the new test file should add no findings)
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary
```

If the test setup is missing (after `ddev restart`), run the one-time setup from AGENTS.md first.

### Manual check (Chrome DevTools, device toolbar)

Laptop ("Responsive": **1366×657**, **1280×632**, **2560×1305**; hard-reload each):

- `/documents/`: 6 books per row at 1366 and 1280; the filter pills sit about 37px higher than on `main`. At 2560: 6 books at 213px, title 72px.
- `/videos/`: 8 tiles per row at 1366 and 1280.
- `/collaterals/`: 6 columns at 1366 and 1280, and still 6 at 2560. Resize from 1600 to 1280 and back: the masonry re-lays out with no overlap.
- `/` (Home): the hero name is about 123px at 1366 and does not fill the width; the portrait still reaches the foot of the first screen. The contact band is about 722px tall, and calendar days are wider than tall but at least 40px.
- At 1024 and at 1600, nothing changed compared with `main` except the Part B text sizes.

Phone (device "iPhone SE" 375×667 and a 360×740 "Responsive" phone; touch emulation on):

- `/`: the roles line under "Virtual Assistant" is 11px on two balanced lines. Software tool names are 12px and none breaks mid-word. Under "What I do", every service row shows its sub-service chips (12px) under the benefit line, wrapped inside the column.
- `/documents/`: cover labels (category, `PDF · 1 p`, `DAVE`) are 10px and still fit on one line on every cover. Open a book, then Details: the category pill is 12px.
- `/collaterals/`: the meta line under each title is 12px and ellipsises instead of wrapping.
- `/videos/` on a 768px tablet: in the viewer, the up-next duration badge is 12px.
- On a real iPhone, if one is available: tap the contact form's name field. The page must not zoom in.

## Conventional commits

Branch: `feat/DPF-NNN-Screen-Fit` (from `main`).

1. `feat(theme): tighten the page shell on laptop screens`
   Stage: `wp-content/themes/devportfolio/assets/css/page.css`
2. `feat(theme): fit more books, videos and collaterals per row on laptops`
   Stage: `wp-content/themes/devportfolio/blocks/document-shelf/style.css`, `wp-content/themes/devportfolio/blocks/video-grid/style.css`, `wp-content/themes/devportfolio/blocks/collateral-grid/style.css`, `wp-content/themes/devportfolio/blocks/collateral-grid/masonry.js`, `wp-content/themes/devportfolio/blocks/collateral-grid/tests/masonry.test.mjs` (the masonry code and its test change together, so the node suite stays green)
3. `feat(theme): scale the home hero and sections down on laptops`
   Stage: `wp-content/themes/devportfolio/blocks/hero/style.css`, `wp-content/themes/devportfolio/blocks/stats-strip/style.css`, `wp-content/themes/devportfolio/blocks/service-list/style.css`, `wp-content/themes/devportfolio/blocks/testimonials/style.css`, `wp-content/themes/devportfolio/blocks/contact/style.css`
4. `feat(theme): raise text below the readable floor`
   Stage: `wp-content/themes/devportfolio/blocks/document-book/style.css`, `wp-content/themes/devportfolio/blocks/software-tools/style.css`, `wp-content/themes/devportfolio/blocks/hero/style.css`, `wp-content/themes/devportfolio/blocks/collateral-grid/style.css`, `wp-content/themes/devportfolio/blocks/service-list/style.css`, `wp-content/themes/devportfolio/blocks/shared/viewer.css`, `wp-content/themes/devportfolio/blocks/video-modal/style.css`
5. `fix(theme): stop iOS zooming into the contact form fields`
   Stage: `wp-content/themes/devportfolio/blocks/contact/style.css`
6. `fix(theme): show the sub-services on phones`
   Stage: `wp-content/themes/devportfolio/blocks/service-list/style.css`, `wp-content/themes/devportfolio/tests/test-service-list.php` (the rule and the test that pinned it change together)
7. `test(theme): pin the laptop tier and the text floor`
   Stage: `wp-content/themes/devportfolio/tests/test-screen-fit.php`
8. `docs(agents): document the laptop tier, text floor and phone tags`
   Stage: `docs/agents/theme-core.md`, `docs/agents/home.md`, `AGENTS.md`
9. `docs(plans): add the DPF-NNN screen fit plan`
   Stage: `.claude/plans/frontend/DPF-NNN-Screen-Fit-Laptop-Density-And-Mobile-Text.md`

Commits 3, 4 and 6 all touch `service-list/style.css`; commits 3 and 4 both touch `hero/style.css` and `collateral-grid/style.css`; commits 3 and 5 both touch `contact/style.css`. Do the steps in order and commit after each one, so each commit holds only its own step.

Every commit footer:

```
Refs: DPF-NNN
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

Stage explicit paths only (never `git add -A`). Run the theme suite and the node suites before commits 2 to 7.

PR title: `feat(theme): fit the site to laptop and phone screens`. Body from `.github/pull_request_template.md` (type: New feature; Description: the two baseline tables; Implementation: the rules and decisions; How to Test: the manual check), ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a merge commit.

## Open questions (do not block)

- **Grey text contrast:** `--wp--preset--color--mid` (`#8a8a8a`) is 3.4:1 on white, below WCAG AA's 4.5:1 for small text. Darkening it to about `#6e6e6e` (5.1:1) would make the tool names, tags, meta lines and stat labels easier to read on every screen. It changes the palette site-wide, so it needs a design decision and its own ticket.
- Should **Selected work** get a laptop variant (card height 300 → about 250)? Its glimpse geometry would need to be re-derived.
- Should the **site header** go from 61px to about 53px on laptops? `--site-header-height` would need to change in the same tier (the sticky filter bar, hero height and scroll padding read it).
- The inline 34px section headings in `front-page.html`: moving them to a class would let the laptop tier set them to 28px.
- Replace `DPF-NNN` with the real ticket number once it exists (file name, comments, test docblock, commits, AGENTS.md bullet).
