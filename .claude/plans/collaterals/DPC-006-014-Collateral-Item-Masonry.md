# Plan: Collateral item and the collaterals masonry (DPC-006, DPC-014)

Tickets:
- **DPC-006** Collateral Item (Card, Medium). Depends on DPC-005 (seeded collaterals, done) and DPC-014.
- **DPC-014** Collaterals masonry (Layout, Medium). Depends on DPC-006 and on the category filter from DPC-003 (done).

The two tickets depend on each other, so they ship as one block on one branch. Theme only: the plugin already has everything this needs (`cptdp_get_collateral_pages()`, the featured image from page 1, `cptdp_paper_size`, `cptdp_total_pages`, `collateral_category`). No plugin change and no plugin version bump.

Related plans (history only): `collaterals/DPC-000-001-002-003-004-...` (the page shell, the taxonomy-aware filter, `assets/css/page.css`), `collaterals/DPC-005-023-...` (page images for collaterals, featured image from page 1), `documents/DPD-016-Shelf-Grid.md` (the documents shelf this mirrors: stretched title button, per-item view-transition names), `documents/DPD-034-...` (the *Reference page for future pages* checklist).

---

## 1. Goal and acceptance criteria

### DPC-006, the item

1. Each published collateral renders one item: a **preview** at its own aspect ratio, and an **info** block at the bottom of the preview with two lines:
   1. the title;
   2. category, paper size and page count, joined by ` · ` (`Flyer · A4 · 2 pp`). A missing part is dropped, one page reads `1 p`, and the whole line is left out when all three are missing.
2. Preview source, in this order:
   1. the page-1 **thumbnail** of a valid page set (`cptdp_get_collateral_pages()`), with the full page 1 image as the larger `srcset` candidate;
   2. page 1 of a valid set that has no thumbnails;
   3. the **featured image**;
   4. a **numbered blank placeholder** (a blank page showing `1`), never a broken image. The placeholder also shows when an image fails to load (JS hides the broken `<img>`).
3. **Mouse devices** (`@media (hover: hover)`): the info is hidden and appears on hover, and on keyboard focus (`:focus-within`). Moving away hides it.
4. **Touch devices** (no hover): the info is always shown.
5. The whole item is clickable. There is **one real button**, in the title. It carries the focus and the accessible name (`Open Product Launch Flyer, Flyer · A4 · 1 p`) and `data-collateral-id`. Its `::after` covers the whole item, as on the documents shelf.

### DPC-014, the masonry

6. Columns are as many as fit at the minimum tile width, **never fewer than 2**. Tiles share the leftover space evenly.
7. The minimum tile width depends on the content-area width `W`: 150px for `W` ≤ 420, 160px for `W` ≤ 700, 190px for `W` ≤ 1000, 220px above that.
8. Page padding is 16px on phones (viewport ≤ 700px) and 36px elsewhere. The gap between tiles is 8px on phones and 12px elsewhere.
9. The content stops growing at 1400px, padding included (so `W` ≤ 1328px), and stays centred.
10. Each tile, in DOM order (menu order, then title), goes into the **shortest column**; on a tie the leftmost wins. Columns end at different heights.
11. Items keep their own aspect ratio, so the tile height is `tileWidth / ratio`.
12. Choosing a category keeps only its items and re-flows the masonry with no gap or overlap. Items glide to their new place or fade in and out (View Transitions); the change is instant with reduced motion or without support.
13. Resizing re-computes the columns with no overlap.

The widths the ticket lists, checked against the rules above (`W` = viewport − 2 × padding, capped at 1328):

| Viewport | Padding / gap | `W` | Min tile | Columns | Tile |
|---|---|---|---|---|---|
| 320 | 16 / 8 | 288 | 150 | 1 → **2** (floor) | 140 |
| 390 | 16 / 8 | 358 | 150 | 2 | 175 |
| 600 | 16 / 8 | 568 | 160 | 3 | 184 |
| 768 | 36 / 12 | 696 | 160 | 4 | 165 |
| 1024 | 36 / 12 | 952 | 190 | 4 | 229 |
| 1440 | 36 / 12 | 1328 | 220 | 5 | 256 |
| 1920 | 36 / 12 | 1328 | 220 | 5 | 256, centred |

`columns = max( 2, floor( ( W + gap ) / ( min + gap ) ) )` and `tile = ( W − ( columns − 1 ) × gap ) / columns`. The table also fixes the phone breakpoint: 600 must use the phone padding (16) and 768 must not, so the breakpoint is **700px**, the same as the modal's.

## 2. Out of scope

- **The collateral modal.** The ticket says "opens the modal", but no collateral modal exists yet. This plan delivers the click target (the stretched button with `data-collateral-id`); the modal ticket binds it, the way `document-modal/view.js` binds `data-document-id`. Until then a click focuses the button and does nothing else. See open question Q1.
- Software tags, description or highlight on the item (not asked for).
- A deeplink, Home page glimpse, entrance animation or hover lift.
- Renaming or generalising the `document-*` blocks (AGENTS: extract only what the second page needs).
- A larger thumbnail size in the plugin (see risk R2).

## 3. Facts established from the repo (do not re-derive)

- `templates/archive-collateral.html` holds the title and `devportfolio/document-categories {"taxonomy":"collateral_category","label":"Collateral categories"}`, then the comment `<!-- Collateral grid and modal blocks: added by the grid/modal tickets. -->`. `tests/test-collateral-page.php` only asserts that `document-shelf` and `document-modal` are absent, which stays true.
- `assets/css/page.css` holds the shared `.documents-page` shell (content size 1440px, `padding-inline: clamp(0px, 3vw, 32px)`, `overflow-x: clip`) and is enqueued on both archives (`devportfolio_enqueue_page_styles()`). It loads even when a block renders nothing, unlike a block's `style.css`.
- The sticky filter band is a full-width `::before`, so the `<main>` must stay full width; the 1400px cap is a content size, not a `max-width` on `<main>` (that would clip the band under `overflow-x: clip`).
- `blocks/document-categories/view.js` is a classic `viewScript`. `init()` runs on `DOMContentLoaded`. It finds the documents shelf with `document.querySelector( '[data-documents-shelf]' )`, guards every use with `if ( shelf )`, and runs `apply( slug )` (pills, shelf filter, trigger label) inside `document.startViewTransition()` unless the browser lacks it or the user prefers reduced motion. Back/Forward goes through the same `applyAnimated()`. `render.php` marks the `?category=` pill `aria-pressed="true"` on first paint.
- Script modules (`viewScriptModule`) run after parsing and **before** `DOMContentLoaded`, so a module's listener is in place before the filter's first `apply()`.
- `cptdp_get_collateral_pages( $id )` returns null, or `pages` (URLs), `width`, `height`, `thumbs` (URLs, `[]` when any is missing), `thumb_width`, `thumb_height`, … The thumbnails are 240px wide and made from the uncropped page. It is valid only while the file fingerprint matches, and it never writes.
- DPC-023 makes page 1 the featured image when a set becomes ready and there is none, so most collaterals with a file also have a featured image with WordPress sub-sizes.
- `devportfolio_document_format_label( '', $pages )` already returns just `1 p` / `n pp` / `''`. `devportfolio_document_category_name()` reads `document_category` only. The theme test pattern for page data is a pure normaliser tested with synthetic arrays (`devportfolio_document_normalise_stored_pages()`), since the theme tests must not depend on the plugin's test base classes.
- `collateral` has no excerpt. Seeded collaterals have no file (status `none`) and no featured image, so they show the placeholder until files are attached.

## 4. Decisions

| # | Decision | Why |
|---|---|---|
| D1 | **One new dynamic block, `devportfolio/collateral-grid`** (`blocks/collateral-grid/`), rendering the items itself. The item is markup from that render, not a separate block. | The item is never used outside the grid. Documents split out the cover because the cover is reused on its own. |
| D2 | **Masonry by JS with absolute positioning**, computed by a pure module `masonry.js` from the known ratios (no image needs to load, nothing is measured). The script sets each tile's `width` and `transform: translate(x, y)` and the grid's `height`. | CSS columns fill top to bottom, not "shortest column, left to right". Native CSS masonry (`grid-lanes`) is not shipped widely enough. With known ratios and the info drawn over the preview, a tile's height is exactly `width / ratio`, so the layout is pure and testable with `node --test`. |
| D3 | **No-JS fallback: CSS multi-column** (`column-width` at the minimum tile width, `break-inside: avoid`). The script switches it off by setting `data-masonry` on the grid. | Without JS the wall still shows every item at its ratio; only the placement order differs. |
| D4 | **The filter tells the grid through an event.** `document-categories/view.js` `apply()` dispatches `devportfolio:category-change` (bubbling, `detail: { slug }`) from the pill group, synchronously, right after it updates the pills. The grid script hides the items that do not match and re-lays out **in the same call**, so the View Transition's new snapshot already has the new layout. On start the grid reads the server-pressed pill (`[aria-pressed="true"]`), so it is right even before the first event. The documents path (`[data-documents-shelf]`, planks) is untouched. | One added line in the filter, no second item contract in it, no planks switch. The grid owns its items and layout. |
| D5 | **The grid owns its item attribute**: items are direct children `article.collaterals-item[data-collateral-categories="slug slug"]` of `div.collaterals-grid[data-collaterals-grid]`. Each item has an inline `view-transition-name: collaterals-item-<id>` and the CSS `view-transition-class: collaterals-item`; `::view-transition-group(*.collaterals-item)` runs 0.4s, none with reduced motion. | Same glide and fade as the documents shelf. Hidden items drop out of the new snapshot (fade out), shown ones fade in. |
| D6 | **The info is drawn over the bottom of the preview** (a glass band: `--wp--custom--glass--tint` and `--wp--custom--glass--filter`, ink text, title one line, meta line one line, both ellipsised). | It keeps tile height = preview height (D2), makes the hover reveal natural, and reuses the shared glass tokens. |
| D7 | **The tile ratio is the uncropped page ratio** (`width / height` of page 1), not the trimmed `ratio`. Featured image: its full-size width and height. Placeholder: `1 / √2`, or `√2` when `cptdp_paper_size` contains "landscape" (case-insensitive). | The thumbnail is uncropped, and on a flyer the white margin is part of the artwork. The seeded sizes are A4, A5 and A4 Landscape, so portrait vs landscape is the only distinction needed. |
| D8 | **Image markup.** Page set: `<img src="t-001" srcset="t-001 {thumb_width}w, p-001 {width}w" sizes="auto, (max-width: 700px) 50vw, 290px" width height alt="" loading="lazy" decoding="async">`. Featured: `wp_get_attachment_image( $id, 'medium_large', false, … )` with the same `sizes`, `alt`, `loading`. `alt=""` because the button names the item. | Lazy and sized, so no layout shift. The browser picks the candidate. |
| D9 | **The placeholder is always in the markup** under the image (`span.collaterals-item__blank`, `aria-hidden`, text `1`). The image covers it. When an image fails (`error` event, or `complete && naturalWidth === 0` at start) the script sets `img.hidden`, and the blank shows. | Meets "never a broken image" without a second render path. |
| D10 | **Phone breakpoint 700px** for padding and gap (from the table in §1). The minimum tile width depends on `W`, not the viewport, and lives in `masonry.js`. The gap is CSS (`--collaterals-gap`), and the script reads it with `getComputedStyle()`, so it is defined once. | One source each: CSS for spacing, JS for column maths. |
| D11 | **Page width:** `templates/archive-collateral.html` adds the class `collaterals-page` to `<main>`. `page.css` gets `.documents-page.collaterals-page { --wp--style--global--content-size: 1328px; padding-inline: 36px; }` and, at `max-width: 700px`, `padding-inline: 16px`. | 1328 + 2 × 36 = 1400. `<main>` stays full width, so the band is not clipped. The Documents page is unchanged. |
| D12 | **Helpers in `functions.php`** (prefix `devportfolio_`, `function_exists` guards): <br>• `devportfolio_document_category_name( $post_id, $taxonomy = 'document_category' )`, an optional argument (the default keeps Documents byte for byte; collateral terms have no singular meta, and `cptdp_get_term_singular_name()` already falls back to the name) <br>• `devportfolio_collateral_meta_line( $post_id )` <br>• `devportfolio_collateral_preview_from( $stored, $featured_id, $paper_size )`, pure, tested with synthetic arrays <br>• `devportfolio_collateral_preview( $post_id )` <br>• `devportfolio_collateral_item( $post_id )`, null for a non-collateral | Mirrors the document helpers, and keeps the render file thin. |

## 5. Design details

### 5.1 `blocks/collateral-grid/block.json`

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/collateral-grid",
	"title": "Collateral Grid",
	"category": "widgets",
	"description": "The Brand Collaterals masonry: one preview per collateral at its own ratio, with the title and details at the bottom.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css",
	"viewScriptModule": "file:./view.js"
}
```
Register it in `devportfolio_register_blocks()` after `document-shelf`.

### 5.2 `render.php`

- Query: `get_posts()` with `post_type` `collateral`, `post_status` `publish`, `posts_per_page` -1, `fields` `ids`, `orderby` `menu_order` ASC then `title` ASC, `no_found_rows`. Empty means `return;` (nothing rendered; the page keeps its title and filter).
- Wrapper: `get_block_wrapper_attributes( array( 'class' => 'collaterals-grid', 'data-collaterals-grid' => '' ) )`.
- Per item, from `devportfolio_collateral_item( $id )`:

```html
<article class="collaterals-item" data-collateral-categories="flyer" style="view-transition-name:collaterals-item-12;--collaterals-ratio:0.7071" data-ratio="0.7071">
	<div class="collaterals-item__preview">
		<span class="collaterals-item__blank" aria-hidden="true">1</span>
		<img class="collaterals-item__img" src="…/t-001.webp" srcset="…/t-001.webp 240w, …/p-001.webp 1400w" sizes="auto, (max-width: 700px) 50vw, 290px" width="1400" height="1980" alt="" loading="lazy" decoding="async">
	</div>
	<div class="collaterals-item__info">
		<h3 class="collaterals-item__title"><button type="button" class="collaterals-item__open" data-collateral-id="12" aria-label="Open Product Launch Flyer, Flyer · A4 · 1 p">Product Launch Flyer</button></h3>
		<p class="collaterals-item__meta">Flyer · A4 · 1 p</p>
	</div>
</article>
```
- Everything is escaped (`esc_attr`, `esc_html`, `esc_url`). The ratio is a float rounded to 4 decimals (`> 0`, else the placeholder ratio). The `<img>` is left out for the blank case. The `<p>` is left out when the meta line is empty, and the aria-label is then just `Open <title>`. The label uses `_x( 'Open %1$s, %2$s', 'collateral item', 'devportfolio' )`.

### 5.3 Helpers (`functions.php`)

`devportfolio_collateral_meta_line( $post_id )`:
```php
$parts = array_filter( array(
	devportfolio_document_category_name( $post_id, 'collateral_category' ),
	trim( (string) get_post_meta( $post_id, 'cptdp_paper_size', true ) ),
	devportfolio_document_format_label( '', get_post_meta( $post_id, 'cptdp_total_pages', true ) ),
), 'strlen' );
return implode( ' · ', $parts );
```

`devportfolio_collateral_preview_from( $stored, int $featured_id, string $paper_size )` returns `array{ kind: 'page'|'featured'|'blank', src, srcset, width, height, ratio, featured_id }`:
- `page` when `$stored` is an array with `pages[0]` a string and `width`, `height` > 0. `src` is `thumbs[0]` when `thumbs` is a non-empty array and `thumb_width` > 0, else `pages[0]`. `srcset` lists the thumbnail (if any) and page 1, and `ratio` is `width / height`.
- else `featured` when `$featured_id` > 0 and `wp_get_attachment_image_src( $featured_id, 'full' )` gives width and height > 0.
- else `blank`, with the D7 ratio.

`devportfolio_collateral_preview( $post_id )` calls it with `function_exists( 'cptdp_get_collateral_pages' ) ? cptdp_get_collateral_pages( $post_id ) : null`, `get_post_thumbnail_id()` and the paper size.

`devportfolio_collateral_item( $post_id )` returns `title` (`get_the_title()`), `meta`, `categories` (slugs of `collateral_category`), `preview`, or null when the post is not a `collateral`.

### 5.4 `masonry.js` (pure ES module, no DOM)

```js
export const MIN_COLUMNS = 2;
export function minTileWidth( width ) { /* 150 ≤420, 160 ≤700, 190 ≤1000, else 220 */ }
export function columnCount( width, gap ) { /* max( 2, floor( ( width + gap ) / ( min + gap ) ) ) */ }
export function layout( width, gap, ratios ) {
	// → { columns, tileWidth, height, positions: [ { x, y, w, h } ] }, one entry per ratio, in order.
	// Each tile goes into the shortest column (ties: leftmost); h = tileWidth / ratio;
	// a column grows by h + gap; height = tallest column − gap (0 with no tiles).
	// A ratio that is not a finite number > 0 counts as 1 / Math.SQRT2.
}
```
`width` ≤ 0 returns 2 columns, tile 0 and height 0 (a hidden grid must not throw).

### 5.5 `view.js` (script module)

```js
import { layout } from './masonry.js';
```
For every `[data-collaterals-grid]`:
1. `items` = its direct `.collaterals-item` children. `slug` = `data-filter` of `.wp-block-devportfolio-document-categories [aria-pressed="true"]`, else `''`.
2. `filter( slug )`: `item.hidden = slug !== '' && ! slugs.includes( slug )`.
3. `relayout()`: `width = grid.clientWidth`, `gap = parseFloat( getComputedStyle( grid ).getPropertyValue( '--collaterals-gap' ) ) || 0`, and `layout()` over the visible items' `data-ratio`. It writes `style.width` and `style.transform` per visible item and `grid.style.height`. It sets `data-masonry` on the first run.
4. `document.addEventListener( 'devportfolio:category-change', e => { filter( e.detail.slug ); relayout(); } )`. This must be synchronous, with no `requestAnimationFrame`, because it runs inside the View Transition callback.
5. A `ResizeObserver` on the grid re-lays out on a `requestAnimationFrame` when the content width changes. It never triggers a View Transition.
6. Broken images (D9): a capture-phase `error` listener on the grid, plus a check of the already-finished images at start.

### 5.6 Filter change (`blocks/document-categories/view.js`)

At the end of `apply( slug )`:
```js
group.dispatchEvent( new CustomEvent( 'devportfolio:category-change', { bubbles: true, detail: { slug: slug } } ) );
```
Update the file's contract comment: "Other grids listen for `devportfolio:category-change` (detail `{ slug }`), dispatched synchronously on every change, including the first apply." Nothing else changes.

### 5.7 `style.css` (block) and `page.css`

Block `style.css`:
- `.collaterals-grid { --collaterals-gap: 12px; column-width: 220px; column-gap: var(--collaterals-gap); }` with `--collaterals-gap: 8px` and `column-width: 150px` at `max-width: 700px`. Items get `break-inside: avoid` and `margin-bottom: var(--collaterals-gap)` (fallback, D3).
- `.collaterals-grid[data-masonry] { columns: auto; position: relative; }` and `.collaterals-grid[data-masonry] > .collaterals-item { position: absolute; top: 0; left: 0; margin: 0; }`. `[hidden] { display: none; }`.
- `.collaterals-item { position: relative; view-transition-class: collaterals-item; }`.
- `__preview { aspect-ratio: var(--collaterals-ratio); overflow: hidden; border-radius: 6px; background: var(--wp--preset--color--fill); outline: 1px solid var(--wp--preset--color--line); }`. `__img` and `__blank` fill it (`position: absolute; inset: 0`, `object-fit: cover` on the image). `__blank` shows a centred `1` in `mid`.
- `__info { position: absolute; inset: auto 0 0; padding: 8px 10px; background: var(--wp--custom--glass--tint); backdrop-filter: var(--wp--custom--glass--filter); }`, plus the same `prefers-reduced-transparency` and `@supports not (backdrop-filter)` fallbacks as the header. Title 13.5px/600, meta 12px `mid`, both `white-space: nowrap; overflow: hidden; text-overflow: ellipsis`, and 12.5px/11.5px at `max-width: 700px`.
- `__open { all: unset; cursor: pointer; }` with `__open::after { content: ''; position: absolute; inset: 0; }` (the whole item). The focus ring goes on the item: `.collaterals-item:has(.collaterals-item__open:focus-visible) { outline: 2px solid var(--wp--preset--color--contrast); outline-offset: 2px; }`.
- Hover reveal: `@media (hover: hover) { .collaterals-item__info { opacity: 0; transform: translateY(4px); transition: opacity 160ms ease, transform 160ms ease; } .collaterals-item:hover .collaterals-item__info, .collaterals-item:focus-within .collaterals-item__info { opacity: 1; transform: none; } }`. No transition with reduced motion. Without hover there is no rule, so the info is always visible.
- View Transitions: `::view-transition-group(*.collaterals-item) { animation-duration: 0.4s; }`, the old/new fades, and `animation: none` under reduced motion (copy the documents shelf block).

`assets/css/page.css`: the D11 rules.

### 5.8 Template

`templates/archive-collateral.html`: `className` `documents-page collaterals-page` (block comment and `class` attribute), and replace the comment with:
```html
<!-- wp:devportfolio/collateral-grid /-->

<!-- Collateral modal block: added by the modal ticket. -->
```

## 6. Work packages (in order; each leaves every suite green)

### Phase 0: environment
`ddev start`, then the test library if the container restarted (`install-wp-tests.sh`). Measure the baseline: theme suite, plugin suite and Node suite (AGENTS: plugin 829 / 12 skipped, theme 358, modal JS 153; trust what you measure).

### Phase 1: masonry maths (DPC-014)
- `blocks/collateral-grid/masonry.js` (5.4).
- `blocks/collateral-grid/tests/masonry.test.mjs` (`node --test`):
  - The §1 table: one case per viewport, with `W` and the gap worked out in the test as the CSS does; columns exact, tile within 0.5px.
  - `minTileWidth` at 420 / 421, 700 / 701 and 1000 / 1001.
  - The 2-column floor at 200px, and no tiles giving height 0.
  - Shortest column: ratios `[0.5, 1, 1, 1]` in 2 columns put the third tile under the second (the short column), and equal heights go to the leftmost.
  - **No overlap:** 200 random ratios (seeded) at five widths. No two rectangles intersect, every tile is inside `[0, W]`, every column starts at `y = 0`, and gaps between tiles in a column equal `gap`.
  - The order is kept: `positions[i]` belongs to `ratios[i]`.
  - A bad ratio (0, NaN, −1) uses `1 / √2`, and `width` 0 does not throw.
- Gate: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/collateral-grid 'node --test tests/*.test.mjs'`.

### Phase 2: helpers (DPC-006)
- `functions.php`: the D12 helpers, including the optional `$taxonomy` argument on `devportfolio_document_category_name()`.
- Gate: the existing theme suite unchanged and green, which proves the default argument keeps Documents identical.

### Phase 3: grid block, item and filter event (DPC-006, DPC-014)
- `blocks/collateral-grid/{block.json,render.php,style.css,view.js}` (5.1, 5.2, 5.5, 5.7), and the registration in `functions.php`.
- `blocks/document-categories/view.js`: the event (5.6).
- `assets/css/page.css` (D11) and the template (5.8).

### Phase 4: tests
`tests/test-collateral-grid.php`, `Devportfolio_Test_Collateral_Grid`, `@group theme`. In `set_up()`, `do_action( 'cptdp_register_post_types' )` and `require_once` `functions.php` as the other classes do. Render with `do_blocks( '<!-- wp:devportfolio/collateral-grid /-->' )`.

- **Query:**
  - Nothing is rendered without published collaterals (a draft and a document exist).
  - Order is `menu_order`, then title (two items share an order).
  - Drafts are excluded.
- **Item structure:**
  - `article.collaterals-item` items are direct children of `[data-collaterals-grid]`.
  - `data-collateral-categories` lists the slugs.
  - The inline `view-transition-name:collaterals-item-<id>` is present.
  - There is exactly one `<button>` per item, with `data-collateral-id`, and it sits in the `h3`.
  - The aria-label is `Open <title>, <meta>`, or `Open <title>` without meta.
  - A title with `&`, `<b>` and quotes is escaped.
- **Meta line:**
  - `Flyer · A4 · 2 pp` and `Logo · A4 · 1 p`.
  - No category gives `A4 · 2 pp`. No paper size gives `Flyer · 2 pp`. No pages, 0 or `abc` gives `Flyer · A4`.
  - With nothing set, no `<p>` is printed.
  - With two terms, the first by name wins.
  - A `document_category` term never shows.
- **Preview, pure** (`devportfolio_collateral_preview_from()` with synthetic arrays):
  - A set with thumbs gives `page`, `src` = thumb, a `srcset` with both widths, and `ratio` = `width / height` to 4 decimals.
  - A set without thumbs gives `src` = page 1.
  - null, `[]`, a missing `pages`, or width 0 falls through to featured, then blank.
  - The blank ratio is `0.7071` for `A4` and `1.4142` for `A4 Landscape` / `a3 landscape`.
- **Preview, rendered:**
  - A featured image (`canola.jpg` from `DIR_TESTDATA`) prints an `<img>` with `alt=""` and `loading="lazy"`, and its ratio is set from the attachment size.
  - No file and no featured image print no `<img>`, the blank with `1`, and the portrait ratio.
  - The blank span is always present and `aria-hidden`.
- **Template:**
  - `archive-collateral.html` has `collaterals-page` on `<main>` and `wp:devportfolio/collateral-grid` after the categories block, inside `documents-page__content`.
  - It still has no `document-shelf` or `document-modal`.
- **CSS structure** (parsed from the files, as `test-design-system.php` does):
  - The info is hidden only inside `@media (hover: hover)`, and is revealed by `:hover` and `:focus-within`.
  - `__open::after` has `inset: 0`.
  - `[data-masonry] > .collaterals-item` is `position: absolute`.
  - The gap is 12px, and 8px at 700px.
  - `view-transition-class: collaterals-item` is set, and reduced motion sets `animation: none`.
  - `page.css` has the 1328px content size, padding 36px, and 16px at 700px. The `.documents-page` rule is unchanged.
- **Scripts:**
  - `collateral-grid/view.js` imports `./masonry.js`, listens for `devportfolio:category-change`, uses `ResizeObserver`, and has no `requestAnimationFrame` inside the category handler.
  - `document-categories/view.js` dispatches `devportfolio:category-change` inside `apply()`.
- `tests/test-page-styles.php`: add an assertion for the `collaterals-page` rules if the class there is the natural home. Do not change any existing assertion.

Gate: the new class, then the full theme suite, the plugin suite and both Node suites.

### Phase 5: browser check (no browser runner in the repo; headless Chrome with puppeteer-core against `https://devportfolio.ddev.site/collaterals/`, as for DPD-026)
- Attach files to three seeded collaterals (as in DPC-023 §B9: a PDF, a portrait PNG and a landscape PNG). Leave the other three without a file, so they show the placeholder.
- At 320, 390, 600, 768, 1024, 1440 and 1920 wide, read the column count (the distinct `x` values) and the tile width, and compare them with the §1 table. Check that no two tile rectangles intersect.
- Pick Logo, then Flyer, then All, then Back and Forward. Only matching items stay, with no gap or overlap, and the items glide. With reduced motion emulated, the change is instant.
- Resize from 1440 to 390 while the page is open: the tiles re-flow and do not overlap.
- Mouse emulation: the info is hidden until hover, and Tab reveals it with a focus ring on the item. Touch emulation (`hasTouch`, `hover: none`): the info is always shown.
- A click anywhere on a tile focuses its button (the modal ticket opens the modal).
- Change one image URL to a 404 in DevTools, or delete the file in `uploads/`: the tile shows the blank `1` and no broken-image icon.
- Check `/documents/`: the shelf, filter, planks and modal are unchanged.

### Phase 6: docs
`AGENTS.md`:
- Add a `blocks/collateral-grid/` paragraph in Theme architecture covering the item markup, preview order, ratio rule, masonry rules, `devportfolio:category-change`, the no-JS fallback and the Node test command.
- Extend the `document-categories` paragraph with the event.
- Extend the `archive-collateral` template note (grid placed, `collaterals-page`) and the `page.css` note.
- Add `test-collateral-grid.php` to the theme suite list, and update the totals.

Also fill this plan's section 10 (as-built notes).

## 7. File inventory

New:
- `wp-content/themes/devportfolio/blocks/collateral-grid/block.json`, `render.php`, `style.css`, `view.js`, `masonry.js`, `tests/masonry.test.mjs`
- `wp-content/themes/devportfolio/tests/test-collateral-grid.php`

Changed:
- `wp-content/themes/devportfolio/functions.php`: block registration, helpers, and the optional `$taxonomy` argument
- `wp-content/themes/devportfolio/blocks/document-categories/view.js`: one dispatch and the contract comment
- `wp-content/themes/devportfolio/templates/archive-collateral.html`
- `wp-content/themes/devportfolio/assets/css/page.css`
- `AGENTS.md`

Not touched: the plugin, `document-shelf`, `document-modal`, `theme.json`, `parts/`.

## 8. Risks

- **R1 First paint.** The module script runs after parsing, so a slow device may paint the CSS-columns fallback for a frame before the masonry. It is the same items in a different order. If this shows in the browser check, hide the grid under `@media (scripting: enabled)` until `data-masonry` is set, with a 1s CSS animation that reveals it anyway so a script error never leaves it blank.
- **R2 Image weight on desktop.** The thumbnails are 240px wide and desktop tiles are 229-290px, so a 1x desktop screen picks the full page image (1400px webp, about 150-250 KB each, lazy). That is acceptable for a portfolio-sized wall. The fix, if needed, is a larger thumbnail in the plugin, or using the generated featured image's sub-sizes when it is page 1. Both are follow-ups.
- **R3 The filter event is a new contract.** It is dispatched on every `apply()`, including on the Documents page, where nothing listens. It is pinned by a test so the next page can rely on it.
- **R4 Absolute positioning and View Transitions.** The glide needs the new layout written synchronously inside the transition callback. A test pins "no rAF in the category handler", and the browser check confirms it.
- **R5 Hover-only info** stays readable by screen readers (opacity, not `display: none`), so the accessible name never depends on hover.

## 9. Conventional commits

Branch `feat/DPC-006-014-Collateral-Item-Masonry` from `main`. Rules as in `AGENTS.md`:
- subject `type(scope): description`, 72 characters or fewer;
- footer `Refs:` with the tickets in the table's last column, then `Co-Authored-By: Claude Code <noreply@anthropic.com>`;
- stage explicit paths, never `git add -A` (other tickets' plan files are untracked in the tree);
- every commit green.

| # | Subject | Stages | Refs |
|---|---|---|---|
| 1 | `docs(plans): add DPC-006 and DPC-014 collateral masonry plan` | this plan | DPC-006, DPC-014 |
| 2 | `feat(theme): add the collateral masonry layout maths` | `blocks/collateral-grid/masonry.js` | DPC-014 |
| 3 | `test(theme): cover the collateral masonry layout maths` | `blocks/collateral-grid/tests/masonry.test.mjs` | DPC-014 |
| 4 | `feat(theme): announce category changes from the filter block` | `blocks/document-categories/view.js` | DPC-014 |
| 5 | `feat(theme): add the collateral grid and item block` | `blocks/collateral-grid/{block.json,render.php,style.css,view.js}`, `functions.php`, `assets/css/page.css`, `templates/archive-collateral.html` | DPC-006, DPC-014 |
| 6 | `test(theme): cover the collateral grid and item` | `tests/test-collateral-grid.php`, `tests/test-page-styles.php` if changed | DPC-006, DPC-014 |
| 7 | `docs(agents): document the collateral grid` | `AGENTS.md`, this plan's section 10 | DPC-006, DPC-014 |

Commit 4 is safe on its own: nothing listens yet, and the Documents tests stay green. Commits 5 and 6 may be squashed if the suite needs the tests to pass the gate (AGENTS allows a small pair).

PR title: `feat(theme): add the Brand Collaterals masonry and items`. The body comes from `.github/pull_request_template.md` (tick New feature; Description, Implementation, How to Test = the Phase 5 checklist). It notes that the click target is ready and the modal comes with its own ticket, and it ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit.

## 10. As-built notes

**Test totals (baseline → now):** theme PHP 358 → 388 (30 new: `test-collateral-grid.php`, one `test-page-styles.php` method); plugin 829 (12 skipped), unchanged; modal JS 153, unchanged; collateral grid JS 17 (`masonry.test.mjs`, new).

**Deviations from the plan:**
- **Info band is in the preview's grid cell, not `position: absolute`.** Absolute placement, or a `transform` or `backdrop-filter` on the band, makes it a containing block, so the title button's `::after` would cover only the band. The band is `align-self: end` in the same grid cell as the preview, the hover reveal is opacity only (no 4px translate), and the band has no blur (its tint is already 80% white). Covered by a CSS test and the browser check.
- The `[hidden]` rule is scoped to `.collaterals-item[hidden]` and `.collaterals-item__img[hidden]`, not global.
- `view.js` re-lays out on a `ResizeObserver` only when the width actually changes (the grid's own height change must not start another pass).
- No `@since` tags on the new helpers: the theme header says 0.1.0 while the functions are tagged up to 0.8.0, so no version was guessed.
- The info band needs `z-index: 1`. Without it the band is painted under the preview's image, because the preview is positioned and the band is not. The earlier browser checks read the band's opacity, not its paint order, so they missed this; the fix was found by a hover report and confirmed with `elementFromPoint` on the band.
- Commits follow §9 on `feat/DPC-006-014-Collateral-Item-Masonry`.

**Browser check** (headless Chrome over CDP against `https://devportfolio.ddev.site/collaterals/`, no DB changes; the seeded collaterals have no files, so tile ratios were spread client-side to exercise the masonry):
- Columns and tile widths match the §1 table at 320 / 390 / 600 / 768 / 1024 / 1440 / 1920: 2/140, 2/175, 3/184, 4/165, 4/229, 5/256, 5/256 (centred, left 296px at 1920).
- No overlapping tile rectangles at any width, including after resizing.
- Category pills: Flyer and Logo each leave only their 3 matching items, the masonry re-flows (grid height changes), All restores 6.
- Mouse (hover) default: info opacity 0. Broken image (injected 404) is hidden, so the blank shows. No page exceptions.

**Not verified in the browser:**
- Touch (`hover: none`) reveal: CDP's hover-media emulation did not change `(hover: hover)`, so the always-visible touch state is only covered by the CSS structure test.
- The glide and fade of the View Transition itself and reduced motion (timing, not measured).
- Back/Forward through categories with the grid, and the 1440→390 resize while open (resizing was exercised at each width, but not as one continuous drag).

## Open questions

- **Q1 The modal.** DPC-006's acceptance says a click "opens the modal for that collateral", but no collateral modal exists. This plan defers it (§2): the button is wired by the modal ticket. If DPC-006 must ship with a working modal, either pull the modal ticket into this branch, or open the existing document modal for collaterals. The second needs a collateral payload and is the bigger change. **Default: defer.**
- **Q2 Paper-size ratio for placeholders.** Only portrait vs landscape is told apart (D7). If editors will enter other shapes (`Square`, `DL`, `Letter`), say so and the helper gets a small lookup table.
