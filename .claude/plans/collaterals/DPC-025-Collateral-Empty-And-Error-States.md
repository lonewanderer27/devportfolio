# Plan: Collateral empty, error and loading states (DPC-025)

Ticket:
- **DPC-025** Page · Empty and error states · depends on DPC-000 · Low · User Interface. Define what the user sees when there is nothing to show: no collaterals at all, no collateral in the chosen category, a collateral whose pages could not be made.

Extra scope requested with the ticket: **a sample loading image** where a collateral's preview image is not ready yet, **on the site itself and in wp-admin → Brand Collaterals** (the list's Thumbnail column and the Page images box on the edit screen).

Story:
> As a user:
> - With no collaterals I see the title and no broken layout.
> - With a category that has none (empty terms are still listed) I see a short "No collaterals in this category" line.
> - A collateral whose preview failed still opens, shows "can't be previewed" and Download works.

Status: **draft, nothing implemented.** Plugin and theme: the plugin gets a status function, the loading image asset and the admin cell (section 4, D2, D5, D10), with a version bump to 0.27.0; the theme reads both from the plugin.

Related plans: `collaterals/DPC-006-014-Collateral-Item-Masonry.md` (the grid and its blank page), `collaterals/DPC-007-...-Collateral-Modal-Sidebar.md`, `collaterals/DPC-012-013-...-Viewer-Footer-And-Mobile.md` (the viewer), `collaterals/DPC-005-023-Seed-Collaterals-Page-Images.md` (how page sets and statuses are made), `collaterals/DPC-024-Shared-Extraction-From-Documents.md` (the sharing rule, section 0: extend a shared piece, never copy a Documents one).

---

## 1. Goal and acceptance criteria

The Collaterals page never shows a broken or silent screen. Five states are defined (A to D on the site, E in wp-admin), each with one visible result:

| # | State | What the visitor sees |
|---|---|---|
| A | No published collaterals | The page title and a short line, **"No collaterals yet."** The category pills are hidden (they would filter nothing). No empty grid box, no gap, no console error. |
| B | A category (term) with no collaterals | The pills stay, the grid is empty, and a short line, **"No collaterals in this category"**, takes its place. Choosing another pill (or All) removes it. Announced politely to screen readers. |
| C | A collateral whose pages could not be made (`failed`, `needs_browser`, or no file) | Its tile shows the numbered blank page (as today) and still opens. The modal says **"This collateral can't be previewed."** with the file-type hint, and **Download and Copy link work**. No pager, arrows or Tap to Flip hint over an empty stage. |
| D | A collateral whose pages are **not ready yet** (`pending`) | Its tile shows a **loading image** instead of the blank page, and the modal says **"Preview is being prepared"** with the same image, Download working. When the set is ready, the next page load shows the real preview. |
| E | Same `pending` collateral in **wp-admin → Brand Collaterals** | The list's **Thumbnail** cell shows the same loading image (50px wide, A4 ratio, tooltip "Preview is being prepared") instead of a dash, and the edit screen's **Page images** box shows it above the status text. A featured image or a real page-1 thumbnail still wins. `failed`, `needs_browser` and `none` keep the dash. |

Acceptance (observable):

1. State A: with every collateral trashed or unpublished, `/collaterals/` renders the title and the line, no `.collaterals-grid`, no pills, and the header and footer are intact at 1440, 820 and 390px.
2. State B: with a seeded empty term (`Logo` until a logo is added), clicking its pill shows the line inside the same View Transition, hides it again on All or another term, and `?category=logo` on first load shows it without a flash (server and script agree).
3. State C: a collateral set to `failed` opens its modal on desktop and mobile; the stage says "can't be previewed"; the footer pager and page arrows are hidden; `Download` (and `Copy link`) work; Back and Esc still close it; the deeplink `?collateral_slug=` still works.
4. State D: a `pending` collateral's tile shows the loading image at the right ratio (A4 or landscape from its paper size), without layout shift when the real image replaces it on a later load.
5. Everything honours `prefers-reduced-motion` (no pulse or spin beyond what the modal spinner already does), `forced-colors`, and has a text alternative or is `aria-hidden` with its meaning carried in the control's name.
6. No Documents behaviour changes: every existing suite passes without assertion changes except the ones this ticket names in section 7.

## 2. Out of scope

- Making a failed set work again (retry, regenerate, browser upload): that is the plugin's Page images box and WP-CLI (DPB-025/026). This ticket only **shows** the state.
- Live polling or a "ready" push to the open page. Visitors never trigger or watch conversion (AGENTS.md: no public conversion route). A pending tile updates on the next load.
- The Documents page's empty states. `devportfolio/document-shelf` also renders nothing without documents; the same fix is cheap but is a **separate ticket** (DPC-024's rule: do not change Documents behaviour here). Recorded as a follow-up.
- A skeleton screen for the whole grid while the page loads, and changing how a featured image or real page 1 is chosen.
- New CPT meta or settings.

## 3. Facts verified in the repo (do not re-derive)

Line numbers are from `wp-content/themes/devportfolio/` and drift; re-check the function name before relying on one.

- **A: nothing at all.** `blocks/collateral-grid/render.php` returns early when `devportfolio_published_ids( 'collateral', ... )` is empty, so the grid outputs nothing. The title is in the template and renders. But `blocks/document-categories/render.php` still renders "All" plus every term (empty terms included), so a page with no collaterals shows a pill row that filters nothing.
- **B: empty term.** The pills list every term (`hide_empty => false`). `blocks/collateral-grid/view.js` `filter( slug )` only sets `item.hidden`; `relayout()` then sets the grid height from zero visible items (`layout()` gives height 0), so an empty category is a collapsed, silent page. There is no message anywhere.
- **Synchronous filter contract.** The grid's `devportfolio:category-change` handler must stay synchronous (no `requestAnimationFrame`): the View Transition's new snapshot has to already show the new state (DPC-006/014). The empty line must be toggled in the same handler.
- **C/D: the preview chain.** `devportfolio_collateral_preview_from()` picks, in order, page 1 of a valid set, the featured image, then `kind: 'blank'` with the numbered blank (`.collaterals-item__blank`, always in the markup). `view.js` hides a failed `<img>` so the blank shows. There is **no notion of "pending"** in the theme: a `pending` and a `failed` collateral look the same (blank).
- **Status is not exposed to the theme.** `CPTDP_Page_Store::get_status( $id )` returns `none|pending|ready|needs_browser|failed` from non-REST post meta `cptdp_document_pages_status`; `cptdp_get_collateral_pages()` returns `null` for anything but a valid ready set. The theme must not read the private meta key directly.
- **Modal with no pages.** The collateral payload comes from `devportfolio_collateral_modal_payload()` through `devportfolio_document_viewer_data()`. A failed Office, Word or image source returns empty `images`; a PDF without images keeps `viewerUrl`, so the viewer **falls back to pdf.js in the browser** (a real preview, so not an error state). With neither, `shared/viewer.js` `acquireSource()` returns null and `unavailable()` shows `status.set( 'unavailable' )`, i.e. the strings already in `devportfolio_collateral_viewer_strings()` ("This collateral can’t be previewed." plus `hintSheet`/`hintSlide`/`hintDoc`/`hint`), through `devportfolio_viewer_status()` and `shared/status.js`. So **C may largely work already**; this ticket proves it and closes the gaps below.
- **Likely gap in C.** `collateral-modal/view.js` `openPages()` sets the pager from `item.images.length` **or the typed `item.pages`** (`cptdp_total_pages`). For a failed set with a typed page count, the footer pager and arrows would show N pages over an "unavailable" stage. `unavailable()` calls `onGeometry( { pages: 0, step: 1 } )`, which should reset it afterwards; Phase 0 verifies the first paint and the mobile footer.
- **The admin cell today.** `cptdp_collateral_column_content()` (`includes/post-types/collateral.php`) shows the featured image, else `cptdp_document_admin_thumb_html()` (page-1 thumbnail, 50px), else `&mdash;`. The column is 90px wide (inline style in `admin_head`), and the **Page images** column already prints the status text (`cptdp_pages_status_cell()`), so admins can read `pending`, but the Thumbnail cell is an empty dash for it. The Page images meta box shows the same thumbnail.
- **The plugin cannot depend on the theme.** An image the admin shows must ship in the plugin (`assets/`, next to `admin-collateral.js`); the theme can read the plugin's file, not the reverse. So the asset's one home is the plugin.
- **No image assets in the theme.** The theme bundles fonts and CSS only (AGENTS.md: "no images"). The modal already has a CSS spinner (`__spinner`, `documents-modal-spin`) and `--wp--preset--color--fill`/`line`/`mid` tokens.
- The tile's accessible name is the open button's `aria-label` (`Open %1$s, %2$s`); the image is decorative (`alt=""`).

## 4. Decisions

Defaults chosen; the open ones are in section 12.

**D1. One empty line, two meanings, one element.** The grid block emits `<p class="collaterals-empty" data-collaterals-empty role="status" hidden>No collaterals in this category</p>` as a **sibling after** `.collaterals-grid`, not a child. Reason: the masonry sets absolute positions and an explicit grid height, so a child message would collapse with it, and `view.js` already treats only `.collaterals-item` children as items. `view.js` finds it as the grid's next sibling. State A reuses the same classes with another text and no grid (D3).

**D2. The theme gets the status through a plugin function, not the meta key.** Add `cptdp_get_collateral_pages_status( int $post_id ): string` next to `cptdp_get_collateral_pages()` in `includes/post-types/document-pages.php`: `''` for a post that is not a collateral, else `CPTDP_Page_Store::get_status()`. Read-only, no conversion, no write. Plugin version 0.27.0. Next to it, `cptdp_collateral_loading_image_url(): string` returns the URL of the loading SVG (D5) so the theme and the admin share one file. The theme calls both through `function_exists`, like every other plugin call. *Alternative rejected:* reading `cptdp_document_pages_status` from the theme couples it to a key the plugin keeps private.

**D3. State A is rendered by the grid block too.** With no published collaterals `render.php` outputs the empty line ("No collaterals yet.", visible, not `hidden`) instead of returning nothing, and the page hides the pills with CSS: `.documents-page__content:has( .collaterals-empty--none ) .wp-block-devportfolio-document-categories { display: none; }`. The pills block stays generic (it knows nothing about collaterals). *Alternative:* teach the pills block to hide when its taxonomy has no published posts. Rejected: it changes a shared block that Documents uses, for a one-page need.

**D4. Preview state is a fourth kind.** `devportfolio_collateral_preview_from()` gains a `state` input (a string, so it stays pure) and returns `kind: 'loading'` when no page and no featured image exist **and** the state is `pending`; otherwise the existing `blank`. Order stays: real page 1, featured image, then loading or blank. A real image always beats the placeholder. `ratio` is the blank's (A4 or landscape). `needs_browser` and `failed` stay `blank`: for a visitor nothing is coming.

**D5. The "sample loading image".** One static SVG shipped **in the plugin** (D10): `wp-content/plugins/custom-post-types-dp/assets/collateral-loading.svg`, served to the site through `cptdp_collateral_loading_image_url()`; a greyscale page outline with a few text bars, drawn from the palette (`fill`, `line`, `mid` hex values, since an `<img>` cannot read CSS variables), 2:3-ish viewBox, no external requests. The tile renders it inside `.collaterals-item__preview` as `<img class="collaterals-item__loading" alt="" aria-hidden="true">` (decorative), `object-fit: cover`, so it follows the tile ratio. The motion is a **CSS opacity pulse on the element** (2 s, `ease-in-out`), not an animated SVG, so one rule turns it off under `prefers-reduced-motion: reduce` and `forced-colors` shows a plain `Canvas` box with the numbered blank. The same SVG is reused in the modal stage (D7). *Alternative if you prefer no new asset:* a CSS-only shimmer on the existing blank. Cheaper, but then "an image" in the request is a gradient; I recommend the SVG because it gives one visible, testable, reusable asset. The theme therefore stays free of image files (AGENTS.md "no images" is unchanged); without the plugin function the tile falls back to the blank.

**D10. Admin loading image.** `cptdp_document_admin_thumb_html()` is shared with documents and stays unchanged for them. For a `collateral` whose status is `pending` and which has no featured image and no page-1 thumbnail, `cptdp_collateral_column_content()` and the Page images meta box print a new `cptdp_collateral_admin_loading_html( $post_id )` instead: the same SVG as an `<img>` of 50px wide at the paper-size ratio, `alt=""`, `title="Preview is being prepared"`, the same border as the thumbnail. **Static in wp-admin** (no pulse): a list can have many rows and admins want a quiet table; the Page images column beside it already says "Pending" in text. The cell never starts or retries work, it only shows the state.

**D6. Tell assistive tech what the visitor sees.** The tile's open button gets a state suffix in its `aria-label` only for `loading`: `Open Flyer, A4 · 2 pp (preview is being prepared)`. The visible blank and loading image stay `aria-hidden`. The empty line is `role="status"` so it is announced when `hidden` is removed (the text is set before the region is shown, as `shared/status.js` does).

**D7. Modal.** The payload gains `previewState`: `ready | pending | unavailable`, derived from the plugin function (D2) and the stored images (`ready` when images or a `viewerUrl` exist, since a PDF can still be drawn by pdf.js). `shared/viewer.js` `unavailable()` passes the state to `shared/status.js`, which learns one new kind, `pending`: text from `data-pending` (**"Preview is being prepared. You can still download the file."**), the spinner hidden, the shared loading SVG shown. Optional key, so the Documents modal is unchanged (its payload has no `previewState`, so it keeps the current `unavailable` path). Strings live in `devportfolio_collateral_viewer_strings()` / `devportfolio_viewer_status()` next to the existing ones (add `pending`).

**D8. No page chrome over nothing.** In `unavailable` and `pending`, `collateral-modal/view.js` makes the footer pager, page arrows and Tap to Flip hint hidden (the pager already hides itself for fewer than 2 pages; this sets pages to 0 **before first paint** instead of using the typed count) and `mobile` hides the footer and keeps the round details button (the existing "no footer" rule). Download, Copy link, the sidebar and the deeplink are untouched.

**D9. No new gate for opening.** A tile with no preview is a normal item: it carries the same open button, the same deeplink, the same Next list. The Next collaterals thumbnails (`devportfolio_collateral_thumb()`) already fall back to a numbered blank; they take the loading image for `pending` through the same `preview_from` result.

## 5. Work packages (each leaves all suites green)

**Phase 0: characterise before changing (no edits).** Measure the baseline test counts on the working tree (AGENTS.md quotes totals; re-measure). Script a headless-Chrome check (puppeteer-core, as in earlier plans) of: grid with zero collaterals; an empty term; a `failed` collateral on desktop and mobile (what the stage, footer and arrows show on first paint). Record what already works so Phase 2 only fixes real gaps. Seed state by setting `cptdp_document_pages_status` on a scratch collateral; restore afterwards; `ddev snapshot` first.

**Phase 1: plugin (D2, D5, D10).** `cptdp_get_collateral_pages_status()`, `cptdp_collateral_loading_image_url()` and the SVG in `assets/`; `cptdp_collateral_admin_loading_html()` used by the Collaterals list cell and the Page images box; version bump 0.27.0; plugin tests (see section 7). `AGENTS.md` Overview line for the API.

**Phase 2: theme PHP.**
1. `devportfolio_collateral_preview_from()` takes the state (D4); `devportfolio_collateral_preview()` reads it (D2) inside the existing memo, so it is invalidated with the meta (the memo already clears on meta changes).
2. `blocks/collateral-grid/render.php`: state A text (D3), the `data-collaterals-empty` sibling (D1), the loading image branch (D5) and the label suffix (D6).
3. `functions.php`: `devportfolio_collateral_modal_payload()` adds `previewState`; strings `pending` (D7). `devportfolio_collateral_thumb()` follows the preview kind.
4. The tile and modal use `cptdp_collateral_loading_image_url()`; no image file is added to the theme.

**Phase 3: theme CSS/JS.**
1. `collateral-grid/style.css`: `.collaterals-empty` (greyscale, 14px, centred under the pills, `min-height` so the sticky pill band does not jump, same width rule as the grid), `.collaterals-empty--none`, the `:has()` rule that hides the pills (D3), `.collaterals-item__loading` and its pulse, reduced-motion and forced-colors rules.
2. `collateral-grid/view.js`: `filter()` returns the number shown; the filter handler and the first layout toggle the sibling's `hidden`; the pressed pill on first load already comes from the server, so the first pass shows it correctly. The handler stays synchronous.
3. `shared/status.js` and `shared/viewer.js` (D7), `collateral-modal/view.js` `openPages()` and the no-chrome rule (D8), `document-modal/style.css` selectors (the loading image in the stage, both prefixes listed as the file already does). `shared/mobile-viewer.js` shows the same status for the mobile dialog.

**Phase 4: docs.** `AGENTS.md`: Overview (plugin API, 0.27.0), the collateral grid and modal paragraphs (states, the loading asset, the `previewState` key, the exception to "no images"). This plan's As-built section.

## 6. Edge cases to decide while building

- **Unpublished or trashed in the middle of a session:** the grid is server-rendered once; a stale tile opens a modal for a payload that no longer lists it. Already handled by the deeplink's "unknown slug is dropped"; no new code.
- **A category with only unpublished posts:** counts as empty (the grid is built from published posts only). Correct for B.
- **Empty term while "All" has items, then switching to All:** the line must be hidden before the layout (same handler, same tick), or the line flashes inside the transition.
- **No JavaScript:** all items show in the CSS-column fallback and the empty line stays `hidden`. State A still works because it is server-rendered.
- **Pending older than a day (cron stuck):** the tile would load forever. Not solved here; it stays a normal `pending` visual. Recorded as a risk: if the plugin later exposes a timestamp, treat it as `blank` after N hours.
- **Featured image generated from page 1 (DPC-023) while the set is `pending`:** the featured image wins over the loading image (D4).
- **Right-to-left, 200% zoom, narrow phone:** the line wraps, never clips.

## 7. Tests

Theme suite (`tests/test-collateral-grid.php`, `tests/test-collateral-modal.php`, `tests/test-collateral-viewer.php`), plugin suite for D2, node for the JS.

Plugin: `tests/collaterals/test-collateral-pages-status.php` (class `CPTDP_Test_Collateral_Pages_Status`): the status function for a collateral in each status, for a document (empty string), for a missing ID; `cptdp_collateral_loading_image_url()` points at a file that exists and is a valid SVG with no script or external reference; the **admin cell**: a `pending` collateral with no featured image and no thumbnail renders the loading `<img>` (`alt=""`, 50px, title, landscape ratio for a landscape paper size); with a featured image, or a valid page set, the existing thumbnail wins; `failed`, `needs_browser` and `none` still render the dash; a document's cell is unchanged; the Page images box shows the image only for `pending`.

Theme PHP:
- `devportfolio_collateral_preview_from()`: pending without page or featured gives `loading` with the blank's ratio (portrait and landscape); pending with a page set or a featured image gives `page`/`featured`; `failed`, `needs_browser`, `none` give `blank`.
- Grid markup: zero collaterals renders the state A line and no grid; with collaterals the hidden sibling exists after the grid and is `hidden` with `role="status"`; a pending item has the loading `<img>`, `alt=""`, and the label suffix; a failed item has the blank only; the escaping of both strings.
- Payload: `previewState` for ready, pending, failed, and a PDF with no images but a `viewerUrl` (stays `ready`); the Documents payload has no such key (golden output unchanged).
- CSS contracts (the file is parsed, there is no browser runner): `.collaterals-empty` rules, the `:has()` hiding rule, the pulse and its `prefers-reduced-motion` removal, the `forced-colors` rule.
- Existing tests that assert the old behaviour and are edited, named in the PR: any that assert the grid renders nothing without collaterals, and the open button's `aria-label` exact text for a non-pending item (unchanged for ready items).

JS (`node --test`): `status.js` with `pending` (text, hint cleared, `aria-busy`); the grid's `filter()` count (move the pure part into `masonry.js` or a small `visible.js` if it needs a test without a DOM, like `next.js`); `view.js` stays without a runner and is covered by the browser check.

## 8. Browser check (headless Chrome via puppeteer-core, no runner in the repo)

Against `https://devportfolio.ddev.site/collaterals/`, with a snapshot taken first and restored after:

1. Trash all collaterals: title and line show, pills gone, no grid, no console error at 1440 and 390.
2. `?category=logo` (empty): the line shows on first paint, no flash; click All then Logo then Flyer: toggles inside the transition; Back and Forward keep it consistent (the shared popstate rules are untouched).
3. Set one collateral to `failed`, one to `needs_browser`, one to `pending`: tiles show blank, blank, loading image at the right ratio; each opens on desktop and on a 390px phone; the stage says the right line; no pager, arrows or tap hint; Download downloads the file; deeplink and Esc work.
4. wp-admin → Brand Collaterals: the `pending` row shows the image in the Thumbnail column (not stretched, aligned with other rows' 50px thumbnails, 90px column unchanged), the `failed` row keeps the dash, a row with a featured image is unchanged; the edit screen's Page images box shows the image for `pending` only. Checked at the 782px admin breakpoint (the column is hidden in the narrow list, as it is today).
5. Emulate `prefers-reduced-motion`, `forced-colors`, `prefers-contrast: more`: no pulse, readable text.
6. Measure layout shift when a pending tile's real image replaces the loading one (the ratio is the stored page ratio in one case and A4 in the other, so a small change is possible when a page set is not A4; note it).

Not verifiable here: a real stuck conversion, and the touch reveal (as in earlier plans).

## 9. Risks

- **Ratio shift on swap.** A pending tile uses the paper-size ratio; the real page ratio can differ (a non-A4 PDF). Same trade-off the blank already has; documented, not hidden.
- **Cached pages.** A page cache keeps the pending tile until it is purged; the plugin does not purge on `ready`. If the host caches the archive, a ready preview appears late. Check whether a `ready` transition should call `clean_post_cache` or a purge hook (open question 4).
- **`:has()` support.** Falls back to showing the pills above the line in older browsers, which is untidy but not broken. The browser floor in `capability.js` is modern (Chrome 119+, Safari 17.4), so it is acceptable.
- **Shared viewer change.** `status.js` and `viewer.js` are shared with Documents. The new kind is only reached when `previewState === 'pending'`, which only the collateral payload sets; a Documents golden-output test (DPC-024 Phase 0) and the shared JS tests guard it.

## 10. Conventional commits

Branch: `feat/DPC-025-Collateral-Empty-And-Error-States`.

| # | Subject | Stages | Refs |
|---|---|---|---|
| 1 | `docs(plans): add the collateral empty and error states plan` | this plan | DPC-025 |
| 2 | `feat(custom-post-types-dp): expose the collateral page set status` | `includes/post-types/document-pages.php`, plugin header (0.27.0) | DPC-025 |
| 3 | `feat(custom-post-types-dp): add a collateral loading image` | `assets/collateral-loading.svg`, `includes/post-types/{document-pages,collateral}.php` (admin list cell, Page images box) | DPC-025 |
| 4 | `test(custom-post-types-dp): cover the collateral status and loading image` | `tests/collaterals/test-collateral-pages-status.php`, `tests/post-types/test-collateral.php` | DPC-025 |
| 5 | `feat(theme): add the preview state to collaterals` | `functions.php` | DPC-025 |
| 6 | `feat(theme): show empty states on the collateral grid` | `blocks/collateral-grid/{render.php,view.js,style.css}` | DPC-025 |
| 7 | `feat(theme): show a pending and unavailable preview in the modal` | `blocks/shared/{status.js,viewer.js,mobile-viewer.js}`, `blocks/collateral-modal/view.js`, `blocks/document-modal/style.css` | DPC-025 |
| 8 | `test(theme): cover the collateral empty, loading and error states` | `tests/test-collateral-*.php`, `blocks/shared/tests/` | DPC-025 |
| 9 | `docs(agents): document the collateral empty and error states` | `AGENTS.md` | DPC-025 |

Each commit leaves every suite green; footer `Refs: DPC-025` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths (the working tree has other tickets' untracked plans and two modified CSS files that are not part of this ticket). PR title: `feat(theme): add collateral empty, loading and error states` (72 characters or fewer).

## 11. As-built notes (this branch's working tree; not committed)

**Test totals (baseline → now):** plugin 834 (12 skipped) → 844 (12 skipped); theme 483 → 498; collateral grid JS 17 → 18; shared JS 154 → 158. All green, run sequentially (the two PHPUnit suites share `wordpress_test`).

**What shipped, per pass:**
- **Plugin** (`includes/post-types/`): `cptdp_get_collateral_pages_status()`, `cptdp_collateral_loading_image_url()` and `cptdp_collateral_admin_loading_html()` in `document-pages.php`; the loading image in the Collaterals list Thumbnail cell (`collateral.php`) and in the Page images meta box; `assets/collateral-loading.svg`; plugin header to 0.27.0.
- **Theme PHP** (`functions.php`): `devportfolio_collateral_preview_from()` gained `string $state = ''` and returns `loading`; `devportfolio_collateral_preview()` reads the plugin status; `devportfolio_collateral_thumb()` handles `loading`; the modal payload gained `previewState`; the collateral viewer strings gained `pending` and `loadingImage`; `devportfolio_viewer_status()` emits `data-pending`, `data-loading-image` and a hidden `<img class="<prefix>__state-image">`.
- **Grid** (`blocks/collateral-grid/`): state A markup (line instead of the grid), the hidden `[data-collaterals-empty]` sibling, the loading `<img>` and the aria-label suffix; `masonry.js` `matchesCategory()`/`visibleCount()`; `view.js` toggles the line synchronously from `filter()`'s count; `.collaterals-empty`, the `:has()` pill hiding, the pulse with its reduced-motion and `forced-colors` rules.
- **Viewer** (`blocks/shared/`, `blocks/collateral-modal/view.js`, `blocks/document-modal/style.css`): `status.js` supports `pending` (text, no hint, `aria-busy`, spinner ↔ image); `viewer.js`/`mobile-viewer.js` pass `previewState` as `emptyState`; `openPages()` (desktop) and `mobile.js` zero the pages for `pending`/`unavailable` before first paint; the state image and pending styles.
- **Tests**: `tests/collaterals/test-collateral-pages-status.php` (10 tests) in the plugin; `tests/test-collateral-grid.php` and `tests/test-collateral-modal.php` in the theme; `blocks/collateral-grid/tests/masonry.test.mjs` and the new `blocks/shared/tests/status.test.mjs` for node.

**Deviations from the plan:**
- **`tests/post-types/test-collateral.php` was not touched** (commit 4 lists it). Nothing in it asserted the old behaviour, so the list-cell coverage lives in the new `test-collateral-pages-status.php` only. The new file is the single home for the admin cell, the Page images box and the status API.
- **`filter()` returns a count instead of moving the whole matcher into a new `visible.js`.** `masonry.js` got the two pure helpers the count needs (`matchesCategory()`, `visibleCount()`) and `view.js` keeps the DOM work, which the plan allowed as the alternative.
- **No `previewState` on documents.** `devportfolio_document_viewer_data()` is untouched, so the Documents payload is byte-identical; the Documents status strings still have no `pending`/`loadingImage` keys. The only shared-markup change on Documents is the unused hidden `<img class="documents-modal__state-image">`, guarded by the existing golden-output test.
- **The `:has()` pill hiding lives in `blocks/collateral-grid/style.css`**, not in `page.css`: the rule names `.documents-page__content` but the state it reads belongs to the grid block, and the block stylesheet is what the archive loads.
- **`needs_browser` stays blank, as decided (D3).** It resolves the next time an admin opens the edit screen, so a visitor cannot wait it out.

**Not done:** the §8 headless-Chrome check was not run. The states are covered by markup/CSS contract tests (`test-collateral-grid.php`, `test-collateral-modal.php`) and the new node suites, but the visual checks — the pulse, the reduced-motion and `forced-colors` output, the pending→ready swap and its small ratio shift, and the admin column at the 782px breakpoint — still need a browser pass against a snapshot. Record the outcome here when it is run.

## 12. Open questions (defaults used; change before implementing if you disagree)

1. **State A wording.** The ticket says "I see the title and no broken layout". Default: also a one-line "No collaterals yet." and the pills hidden. Say if you want the title alone.
2. **Loading image.** Default: one greyscale SVG in the plugin, pulsing on the site and static in wp-admin (D5, D10). Alternative: CSS-only shimmer on the site and nothing new in admin. Is there a brand loading graphic to use instead? Should the admin one pulse too?
3. **Which statuses count as "not ready yet".** Default: only `pending` shows the loading image; `needs_browser` and `failed` show the blank, since a visitor cannot make either complete. Do you want `needs_browser` treated as loading (it resolves the next time an admin opens the edit screen)?
4. **Cache purge on `ready`.** Should the plugin clear the post cache or fire an action when a set becomes ready, so a page cache can purge the archive? Default: no, out of scope.
5. **Documents page.** Same empty-state fix there as a follow-up ticket? Default: yes, separate ticket, not here.
6. **Plugin change.** Default: a small plugin function (D2). If you would rather keep this ticket theme-only, the fallback is the theme reading the status meta directly, which I advise against.
