# Plan: Collateral modal shell, sidebar and deeplink (DPC-020, DPC-021, DPC-022, DPC-007 to DPC-011)

Tickets:
- **DPC-021** Desktop modal (High). Depends on DPC-006 (done).
- **DPC-022** Mobile modal (High). Depends on DPC-021.
- **DPC-007** Sidebar header: Close, pills, title, description (Medium). Depends on DPC-021.
- **DPC-008** Sidebar Results (statistics) (Medium). Depends on DPC-007 and DPB-013 (done: `cptdp_post_statistics` is stored on collaterals).
- **DPC-009** Sidebar Made with (software) (Medium). Depends on DPC-007 and DPB-021. The tool icon waits for DPD-037 / DPB-033; this plan shows the name.
- **DPC-010** Sidebar Next collaterals (Medium). Depends on DPC-007 and DPC-006.
- **DPC-011** Sidebar footer: Download, Copy link (Medium). Depends on DPC-007; its link is the DPC-020 deeplink.
- **DPC-020** Deeplink (Medium). Depends on DPC-021 and DPC-022. In scope here (it was Q2 in the first draft).

One block, one branch: the eight stories are the shell of one dialog and the content of its sidebar, and the sidebar cannot be tested without the shell. Theme only. The plugin already holds every field (`cptdp_collateral_file`, `cptdp_embed_url`, `cptdp_paper_size`, `cptdp_total_pages`, `cptdp_software_tools`, `cptdp_post_statistics`, `collateral_category`), so **no plugin change and no plugin version bump**.

Related plans (history only): `collaterals/DPC-006-014-Collateral-Item-Masonry.md` (the grid and the click target this binds, §2 and Q1 there), `documents/DPD-009-010-011-012-014-015-Document-Modal.md` and `DPD-017-018-019-Document-Modal-Mobile.md` (the dialogs this mirrors), `documents/DPD-032-Document-Modal-Deeplink.md` (the URL and History rules this reuses), `documents/DPD-034-...` (*Reference page for future pages*).

---

## 1. Goal and acceptance criteria

### DPC-021 desktop modal

1. Clicking **anywhere on a collateral item** opens one native `<dialog>`, centred over a dimmed backdrop, thin outline, rounded corners (14px, as the Documents modal). The click target already exists: the title button `button.collaterals-item__open[data-collateral-id]` whose `::after` covers the item.
2. Applies at **701px and wider, except a touch screen held sideways** (the single definition in `routing.js`, §4 D3).
3. The dialog is never wider than the screen (`min( W, 100% - 32px )`). The width is a design decision (Q1); the default is one CSS custom property so the answer is a one-line change.
4. Three areas: **sidebar** (DPC-007 to 011, built here), **body** (DPC-012, empty `.collaterals-modal__stage`) and **footer** (DPC-013, empty, `hidden`).
5. Closes by backdrop click, Esc or the Close button. Focus goes back to the item's button.
6. At 700px or less the mobile modal opens instead, **never both** (`enforceSingleDialog()`).
7. Resizing across 700px swaps the dialog in place: same collateral, same page, same URL.

### DPC-022 mobile modal

8. At 700px or less, **or on a touch device held sideways** (`(pointer: coarse) and (orientation: landscape) and (max-height: 520px)`), a **full-screen** dialog opens: no backdrop, not centred, no radius.
9. Three blank areas (header DPC-015, body DPC-012/019, footer DPC-016) as empty elements. One interim control: a Close button (Q3).
10. The device or browser **Back** button closes it and keeps the visitor on `/collaterals/`; the category in `?category=` stays.
11. Above 700px (and not sideways) the desktop modal is used.

### DPC-007 sidebar header (top to bottom)

12. Top bar: **Close** (outline, X). Then line 1 pills: **category** (filled, singular name) and **paper size and page count** (outline, `A4 · 2 pp`). Then the **title** (bold) and the **description** (wraps).
13. A missing paper size or page count is left out of the pill (`A4`, `2 pp`, none); the pill is not drawn when both are missing; a missing category leaves no filled pill; a missing description leaves no empty line.
14. Admin text is escaped: it is written with `textContent`, so markup shows as text.

### DPC-008 Results

15. Caption **Results**, then one box per `cptdp_post_statistics` row in saved order: **value** (bold, as typed) over **label** (uppercase by CSS, the stored text is unchanged).
16. The whole block (caption included) is not drawn without rows. A row with only a label or only a value still shows; long text wraps inside its box.

### DPC-009 Made with

17. Caption **MADE WITH** (uppercase), then one square pill per **published** `software_tool` of the collateral (name now, icon with DPD-037). A draft or trashed tool is not shown. Nothing is drawn without tools.

### DPC-010 Next collaterals

18. Thumbnails of the **next two** collaterals after the current one: menu order, then title (the grid's order), **wrapping** from the last to the first, **within the active category filter** when one is active, **none when fewer than 2 other collaterals** exist (a block with one thumbnail is never drawn).
19. Clicking one swaps the modal to that collateral **without closing it**: header, statistics, tools, next list, footer and URL update; the body (DPC-012) is told through the change event.

### DPC-020 Deeplink

23. The open collateral is `/collaterals/?collateral_slug=<slug>`, next to `?category=`; other parameters and the hash are kept.
24. **First load** with a known slug opens that collateral (the mobile dialog when routing says mobile). An **unknown or unpublished** slug (draft, trash, wrong type) is dropped with `replaceState` and the page loads normally.
25. **A click pushes** one history entry. **Closing steps back** when the modal added the entry, otherwise removes the parameter with `replaceState`. **Back and Forward open and close the modal without reloading the page.**
26. **`/collaterals/<slug>/` redirects** to the archive deeplink for a **published** collateral only; drafts, other statuses and other single pages are not redirected.
27. Choosing a Next collateral updates the slug (D8). Focus returns to the item on close (D9).
28. A link from Copy link (DPC-011) opens the right collateral in a new tab.

### DPC-011 Footer buttons (pinned to the bottom of the sidebar)

20. **Download** (filled), same three modes as Documents: `file` = attachment URL with `download`; `embed` = embed URL in a new tab (`rel="noopener"`); `none` = `aria-disabled`, does nothing.
21. **Copy link** (outline) puts `<origin>/collaterals/?collateral_slug=<slug>` on the clipboard and the label becomes **Copied** for about 2s. If the clipboard is blocked a fallback selects the text so the visitor can copy it.
22. Both are reachable and operable by keyboard.

---

## 2. Out of scope

- **The body** (DPC-012, page viewer for collaterals), **the footer** (DPC-013), **the mobile header, body and footer content** (DPC-015, DPC-016, DPC-019). Their containers and the change events exist; nothing is drawn in them.
- **Tool icons** (DPD-037 / DPB-033). Pills show names; see Q7 for the payload shape when icons land.
- Home page glimpse, animation of the sidebar blocks, next-collateral prefetch.
- **Refactoring `document-modal/view.js`** into a shared session module (§4 D2). The Documents blocks, tests and AGENTS text stay unchanged.
- Any plugin change.

## 3. Facts established from the repo (do not re-derive)

- `templates/archive-collateral.html` ends with `<!-- Collateral modal block: added by the modal ticket. -->` inside `.documents-page__content`, after `devportfolio/collateral-grid`. `tests/test-collateral-page.php` only asserts that `document-shelf` and `document-modal` are absent (`collateral-modal` does not contain `document-modal`).
- `blocks/collateral-grid/render.php` already outputs `article.collaterals-item[data-collateral-categories]` with `button.collaterals-item__open[data-collateral-id]` (one per item, `::after` covers the item). Its `view.js` hides items with `hidden` per category and never touches the modal.
- `blocks/document-categories/view.js` dispatches `devportfolio:category-change` (`detail: { slug }`), marks the active pill `aria-pressed="true"` and keeps `?category=<slug>`. `render.php` marks the URL's pill pressed on first paint. A module script runs before `DOMContentLoaded` (before the filter's first `apply()`), so a deeplink opened from a module reads the pressed pill, not the `hidden` flags.
- `document-modal/routing.js` (`createRouting()`, `WIDE_QUERY`, `SIDEWAYS_QUERY`) is pure and has no document-specific code. `download.js` (`applyDownload`, `guardDisabledDownload`) reads `doc.title`, `doc.downloadMode`, `doc.downloadUrl` only. `deeplink.js` has the parameter name **hard-coded** (`PARAM = 'document_slug'`).
- `devportfolio_document_single_redirect_url()` (`functions.php`) and its `template_redirect` hook (priority 1, `wp_safe_redirect( …, 302 )`) are the redirect pattern: `is_singular( 'document' )`, published only, `add_query_arg( 'document_slug', post_name, get_post_type_archive_link() )`. Tests: `test-document-modal.php`, the cases that call `devportfolio_document_single_redirect_url()`.
- `document-modal/view.js` pattern to copy: `open()` pushes `?document_slug`, a capture-phase `popstate` listener **stops the event** whenever it acts (the Interactivity Router reloads the page for URLs it has not cached), `finishClose()` steps back with `history.back()` when the modal added the entry, `swap()` runs inside a View Transition marked `data-documents-swapping`, `enforceSingleDialog()` closes the wrong dialog. **Listeners run in registration order; do not move the popstate listener into `DOMContentLoaded` or a lazy import.**
- The Documents modal sizes itself `min( 1680px, 100% - 32px )` x `calc( 100dvh - 32px )` and goes full viewport at `max-width: 1200px`; its tokens are `--dm-*` on `.documents-modal, .documents-mobile`. A block's `style.css` only loads when the block renders.
- `devportfolio_document_description( $id )` (excerpt, else first 20 words of the content, plain text) works for any post; a `collateral` has no excerpt support, so it is always the first 20 words (Q4). `devportfolio_software_tool_names( $id )` works for any post type and already filters to published tools. `devportfolio_document_category_name( $id, 'collateral_category' )` returns the singular name with a fallback to the term name. `devportfolio_document_format_label( '', $pages )` returns `1 p` / `n pp` / `''`.
- `devportfolio_document_download_target( $id )` **hard-codes** `cptdp_document_file`; the collateral file key is `cptdp_collateral_file`. Both types use `cptdp_embed_url`.
- `cptdp_post_statistics` is sanitised on save (`cptdp_collateral_sanitize_statistics()`: `sanitize_text_field`, rows with both empty dropped). Read it defensively anyway (a REST write or an old value).
- `devportfolio_collateral_preview( $id )` returns `kind` (`page`/`featured`/`blank`), `src`, `srcset`, `ratio`, `featured_id` (the grid's source of truth). `featured` has an empty `src`, resolved in the grid with `wp_get_attachment_image()`.
- AGENTS rule: the second page extracts what it needs **with the Documents tests unchanged and green**. Several Documents tests parse `document-modal/*.js` and `style.css` by path, so nothing there is moved or renamed.
- The `collateral` CPT is public with archive `/collaterals/`. A public query var named `collateral` exists (the CPT), exactly as `document` did for DPD-032, so the deeplink parameter must not be `collateral`.

## 4. Decisions

| # | Decision | Why |
|---|---|---|
| D1 | **A new block `devportfolio/collateral-modal`** (`blocks/collateral-modal/`: `block.json`, `render.php`, `style.css`, `view.js`, plus small pure modules), placed once in `archive-collateral.html`. | The sidebar layout, payload and behaviours differ from the Documents viewer; extending `document-modal` would put collateral branches in a 500-line file that Documents tests parse. |
| D2 | **Reuse the pure Documents modules by import; do not refactor `document-modal/view.js`.** `collateral-modal/view.js` imports `../document-modal/routing.js` and `../document-modal/download.js` as they are, and a **parametrised deeplink**: `deeplink.js` gains `createDeeplink( param )` (returns `slugFromUrl`, `urlWithSlug`, `urlWithoutSlug`), and the existing named exports are rebuilt from `createDeeplink( 'document_slug' )` with the same behaviour. The open/close/History/swap flow (about 150 lines) is **re-written compactly in the collateral `view.js`**. | Consolidation without touching the riskiest file: the pure pieces are shared (one definition of the 700px rule, one download behaviour, one URL implementation); the Documents tests stay unchanged. Recorded debt: the session flow now exists twice; the **third** modal (Videos) extracts `createModalSession()` first, with a trigger in AGENTS. |
| D3 | **One breakpoint definition.** The CSS repeats the two media queries from `routing.js` exactly (`@media (min-width: 701px) and (not (SIDEWAYS))` hides the mobile dialog, `@media (max-width: 700px), SIDEWAYS` hides the desktop dialog), and a node test compares `collateral-modal/style.css` with the constants, like `routing.test.mjs`. | Same guarantee as Documents: script and CSS cannot disagree. |
| D4 | **Payload built in PHP, written with `textContent`.** `devportfolio_collateral_modal_payload( $id )` returns plain text and URLs. The render outputs `<script type="application/json" class="collaterals-modal__data">` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE`, shape `{ "order": [ids], "items": { "<id>": {...} } }`. | A JSON object keyed by integer ids loses the menu order in JS (`Object.values` sorts integer keys), and "next" needs the grid's order. One explicit `order` array keeps it. Published collaterals only; `null` for a non-collateral. |
| D5 | **Item fields:** `id`, `slug`, `title` (plain, `html_entity_decode`), `description`, `category` (singular, plain), `categories` (slugs, for the filter), `pill` (`A4 · 2 pp`, from `devportfolio_collateral_pill()`), `stats` (`[{label,value}]`), `tools` (names), `downloadMode`, `downloadUrl`, `thumb` (`{src, srcset, ratio}` for the next list, `src` `''` for the blank). The body ticket (DPC-012) adds its own fields (`pages`, images) to the same helper. | Everything the sidebar draws, nothing else. |
| D6 | **Download target is generalised, not copied:** `devportfolio_document_download_target( $post_id, $file_meta = 'cptdp_document_file' )`. The collateral payload calls it with `cptdp_collateral_file`. Embed URLs must start with `http(s)://`, as today. | One implementation of the three modes; the documents call sites and tests are unchanged (default argument). |
| D7 | **Next collaterals are computed in JS from the payload** by a pure `nextTwo( order, items, currentId, slug )` in `collateral-modal/next.js`: candidates = `order` minus the current id, filtered by `slug` when not `''`; fewer than 2 candidates returns `[]`; otherwise the first two candidates **after the current id's position in `order`, wrapping**. The active filter is the pressed pill (`.wp-block-devportfolio-document-categories [aria-pressed="true"]`, read when the modal opens or swaps). | Pure, testable, works when the current collateral is outside the filter (a deeplink to a Logo while Flyer is active), and independent of the grid's `hidden` state on first load. |
| D8 | **Swap without closing = `replaceState`, not `pushState`.** The first open pushes `?collateral_slug=<slug>` (so Back closes the modal and keeps the category); choosing a next thumbnail replaces that entry. | One Back always closes the modal, as DPC-022 requires. A trail of pushed entries would make Back walk through collaterals behind an open modal. Q6. |
| D9 | **Focus.** On open, focus the Close button. After a swap the clicked thumbnail is gone, so focus moves to the new title (`tabindex="-1"`), which also makes a screen reader read it. On close, focus goes to the grid button of the **currently shown** collateral (the original opener may not be the one open after swaps); if it is hidden by the filter, to the original opener. | DPC-021: "focus returns to the item". |
| D10 | **Copy link builds the URL from `location.origin + location.pathname` plus only `collateral_slug`** (no `?category=`), exactly as the story's `/collaterals/?collateral_slug=<slug>`. The clipboard work is a small pure-ish module `copy-link.js` with the clipboard injected (`navigator.clipboard.writeText`, then a hidden readonly input with `select()` and `execCommand( 'copy' )`, then, when both fail, the readonly input is revealed under the buttons with the URL selected and the label reads *Press Ctrl/⌘ + C*). | The story's fallback "selects the text". Testable without a browser. |
| D11 | **Layout.** Panel is a grid: sidebar (full height, `--cm-sidebar`, default `clamp( 300px, 30%, 380px )`) and a right column with the body (`1fr`) over the footer (auto, `hidden`). The sidebar is a flex column: the top bar and the scrolling middle (`header`, Results, Made with, Next) and the footer buttons **pinned** under it. Dialog: `width: min( var( --cm-width ), calc( 100% - 32px ) )`, `--cm-width: 1280px`, `height: calc( 100dvh - 32px )`, full viewport at `max-width: 1200px` as Documents. | Width and the footer's span are design questions (Q1, Q5); each is one token or one grid line to change. |
| D12 | **Tokens.** `collateral-modal/style.css` declares its own `--cm-*` tokens with the same values as `--dm-*` (ink, line, edge, surface, ease). The values are copied, not imported, because `document-modal/style.css` does not load on this page. A later cleanup moves them to `theme.json` `settings.custom` when the third modal needs them. | A block stylesheet only loads with its block. |
| D13 | **Event for the body ticket:** `collaterals:modal-change` on the dialog with `{ id, page }`, dispatched after every open, swap and (later) page change; the mobile dialog dispatches `collaterals:mobile-change`. The stage elements `.collaterals-modal__stage` and `.collaterals-mobile__stage` stay empty. | Same contract as `documents:modal-change`; DPC-012 only listens. |
| D15 | **The single-URL redirect is generalised once, not copied.** A helper `devportfolio_modal_single_redirect_url( $post_type, $param )` holds the body of today's function; `devportfolio_document_single_redirect_url()` becomes a one-line wrapper (`'document'`, `'document_slug'`) with the same return values, and `devportfolio_collateral_single_redirect_url()` is the second wrapper (`'collateral'`, `'collateral_slug'`). One hook function `devportfolio_redirect_modal_singles()` tries both on `template_redirect`; `devportfolio_redirect_document_single()` stays as a thin alias so nothing external breaks. | One implementation of "published single becomes archive deeplink"; the Documents tests run unchanged against the wrappers. |
| D14 | **Click binding is event delegation on `[data-collateral-id]`**; the Next thumbnails use `data-collateral-next` so the delegated opener never treats them as grid items. | A thumbnail is a swap, not an open. |

## 5. Design details

### 5.1 `blocks/collateral-modal/block.json`

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/collateral-modal",
	"title": "Collateral Modal",
	"category": "widgets",
	"description": "The collateral modals opened from a collateral item: desktop dialog with sidebar, body and footer, and the full-screen mobile dialog.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css",
	"viewScriptModule": "file:./view.js"
}
```
Register after `collateral-grid` in `devportfolio_register_blocks()`. In `archive-collateral.html` replace the placeholder comment with `<!-- wp:devportfolio/collateral-modal /-->`.

### 5.2 `render.php` structure

```html
<div class="wp-block-devportfolio-collateral-modal collaterals-modal-root">
	<dialog class="collaterals-modal" aria-labelledby="collaterals-modal-title-1">
		<div class="collaterals-modal__panel">
			<aside class="collaterals-modal__side">
				<div class="collaterals-modal__bar">
					<button class="collaterals-modal__close" type="button" aria-label="Close viewer">X</button>
				</div>
				<div class="collaterals-modal__scroll">
					<header class="collaterals-modal__head">
						<p class="collaterals-modal__pills">
							<span class="collaterals-modal__pill collaterals-modal__pill--cat" hidden></span>
							<span class="collaterals-modal__pill collaterals-modal__pill--size" hidden></span>
						</p>
						<h2 class="collaterals-modal__title" id="collaterals-modal-title-1" tabindex="-1"></h2>
						<p class="collaterals-modal__desc" hidden></p>
					</header>
					<section class="collaterals-modal__results" aria-labelledby="…" hidden>
						<h3 class="collaterals-modal__caption" id="…">Results</h3>
						<dl class="collaterals-modal__stats"></dl>
					</section>
					<section class="collaterals-modal__tools" aria-labelledby="…" hidden>
						<h3 class="collaterals-modal__caption" id="…">Made with</h3>
						<ul class="collaterals-modal__toollist"></ul>
					</section>
					<nav class="collaterals-modal__next" aria-labelledby="…" hidden>
						<h3 class="collaterals-modal__caption" id="…">Next collaterals</h3>
						<ul class="collaterals-modal__nextlist"></ul>
					</nav>
				</div>
				<div class="collaterals-modal__actions">
					<a class="collaterals-modal__download" role="button">…Download</a>
					<button class="collaterals-modal__copy" type="button">Copy link</button>
					<input class="collaterals-modal__copyfield" type="text" readonly hidden aria-label="Link">
					<span class="collaterals-modal__live" role="status" aria-live="polite"></span>
				</div>
			</aside>
			<div class="collaterals-modal__main">
				<div class="collaterals-modal__body"><div class="collaterals-modal__stage"></div></div>
				<footer class="collaterals-modal__footer" hidden></footer>
			</div>
		</div>
	</dialog>
	<dialog class="collaterals-mobile" aria-labelledby="collaterals-mobile-title-1">
		<div class="collaterals-mobile__panel">
			<header class="collaterals-mobile__header"><button class="collaterals-mobile__close" …/><h2 class="collaterals-mobile__title" id="…"></h2></header>
			<div class="collaterals-mobile__body"><div class="collaterals-mobile__stage"></div></div>
			<footer class="collaterals-mobile__footer"></footer>
		</div>
	</dialog>
	<script type="application/json" class="collaterals-modal__data">{…}</script>
</div>
```
- Query: `get_posts()` as the grid (`collateral`, `publish`, `-1`, `fields ids`, `menu_order` ASC then `title` ASC, `no_found_rows`); `order` is that id list, `items` the payload per id. No collaterals still renders the shells (cheap) with an empty payload, and `view.js` does nothing.
- Every empty region is `hidden` in the markup and shown by the script, so the no-JS page is clean. The *Copied* text goes in the `role="status"` span, the label of the button only changes visually (so the accessible name never changes mid-announcement).
- Strings with `esc_html_x()` / `esc_attr_x()` and context `collateral modal`; the Close label is `Close viewer`.
- Unique ids with `wp_unique_id()`.

### 5.3 Helpers (`functions.php`, prefix `devportfolio_`, `function_exists` guards)

- `devportfolio_collateral_pill( $paper_size, $pages )` (pure): `A4 · 2 pp` from the trimmed paper size and `devportfolio_document_format_label( '', $pages )`; a missing part is dropped; `''` when both are missing.
- `devportfolio_collateral_stats( $post_id )`: reads `cptdp_post_statistics`, returns only rows that are arrays, casts `label` and `value` to trimmed plain strings (`wp_strip_all_tags`), drops rows with both empty, keeps order.
- `devportfolio_collateral_thumb( array $preview )` (pure around `wp_get_attachment_image_url()`): `{ src, srcset, ratio }`; `page` uses the preview's `src`/`srcset`; `featured` uses `wp_get_attachment_image_url( $featured_id, 'medium' )`; `blank` returns `src ''` so the script draws the numbered blank.
- `devportfolio_document_download_target( $post_id, $file_meta = 'cptdp_document_file' )` (D6).
- `devportfolio_collateral_modal_payload( $post_id )` (D4, D5): `null` for a non-collateral.
- `devportfolio_collateral_modal_strings()`: the few strings the script writes (`Open %s`, `Copied`, `Press Ctrl/⌘ + C`, `Next collaterals`), as `data-strings` on the root, so no English is hard-coded in JS.

### 5.4 Script modules (`blocks/collateral-modal/`)

| File | Purpose | Pure and `node --test`ed |
|---|---|---|
| `next.js` | `nextTwo( order, items, currentId, slug )` (D7) | yes |
| `copy-link.js` | `linkFor( location, slug )` and `copyText( text, deps )` (D10) | yes, dependencies injected |
| `view.js` | opens, closes, History, swap, fills the sidebar from the payload | no (headless Chrome check, as DPD-026) |

`view.js` flow (modelled on `document-modal/view.js`, trimmed):
1. Read the JSON; build `idBySlug`.
2. `routing = createRouting()` (D2); `desktop = routing.isDesktop()`.
3. `fill( item )` writes the sidebar with `textContent` only: pills (hidden when empty), title, description, Results (`dl` with one `div` per row: `dd` value first line, `dt` label; the block hidden without rows), tools (`li` names, hidden without), `renderNext()` (`ul` of `button[data-collateral-next]`: image with `srcset` or the numbered blank, title as the accessible name via `aria-label` from `strings`; hidden when `nextTwo()` is empty), download (`applyDownload( link, item )` and `guardDisabledDownload()` once), copy label reset.
4. `open( id, trigger, { push } )`: ignores unknown ids or when already open; pushes `?collateral_slug` when `push` (D8); sets `overflow: hidden` on `<html>`; `showModal()` on the right dialog; emits the change event.
5. `swapTo( id )` (thumbnail click): `fill()`, `history.replaceState`, focus the title (D9), emit.
6. Close handlers: backdrop (`event.target === dialog`), Esc (native), Close buttons; `finishClose()` as documents (step back when pushed, else clean the URL with `replaceState`), restore overflow and focus (D9).
7. **Capture-phase `popstate`** registered at module top level (never in `DOMContentLoaded`): no slug in the URL while open closes the modal (flag `closingFromHistory`); a slug while closed opens it without pushing; each case calls `stopImmediatePropagation()`, otherwise the Interactivity Router reloads the page.
8. First load: a known `collateral_slug` opens (with the grid button as the opener), an unknown one is dropped with `replaceState`.
9. `routing.onChange( swap )`: the document-modal `swap()`, view-transition name `collaterals-modal`, attribute `data-collaterals-swapping` on `<html>`, no transition with reduced motion or without support.
10. Download uses `applyDownload()` from the Documents module. Copy uses `copyText()`.

The mobile dialog is the same `open()` with a different shell; its content in this ticket is the title and the interim Close (Q3).

### 5.5 CSS (`style.css`) key rules

- `.collaterals-modal`: D11 size and look; `::backdrop` dimmed (`rgb( 0 0 0 / 0.4 )`, light blur only without `prefers-reduced-transparency`); `[open]` fade-up (200ms) and none with reduced motion.
- `.collaterals-mobile`: `inset: 0`, `width: 100%`, `height: 100dvh`, `border-radius: 0`, `::backdrop { background: transparent }`, safe-area padding; slides up like `documents-mobile-up` unless reduced motion.
- Sidebar: `position`-free flex column; `.collaterals-modal__scroll { overflow: auto; min-height: 0; flex: 1 }`; `.collaterals-modal__actions { border-top; padding; display: grid; gap: 8px }` pinned by being outside the scroller. The sidebar and the body share grid row 1 (`grid-row: 1` on both, `grid-template-rows: minmax( 0, 1fr )` on the panel), so the sidebar runs the full dialog height and the footer buttons sit on its bottom edge.
- Close (desktop and mobile): 36px round outline, 1px ink ring, ink X on the surface (no fill).
- Pills: 28px tall, 12.5px/600; filled uses ink background with white text, outline uses 1px ink border. Statistic box: 1px line border, 10px radius, value `font-weight: 800`, label `text-transform: uppercase; font-size: 11px; letter-spacing: .06em; overflow-wrap: anywhere`. Tool pill: square (radius 6px), 1px line, name; the icon slot is a future `::before`.
- Next thumbnail: the image at its own ratio, `max-height` 160px, `object-fit: contain` on a `--cm-surface-alt` tile, focus ring 2px ink offset 2px.
- Both breakpoints exactly as D3. `@media ( max-width: 900px )` narrows the sidebar token to 280px.
- Forced colours: outline pills keep a `1px solid CanvasText` border; `prefers-contrast: more` uses the stronger line token.

### 5.6 History and URL (DPC-020)

- Parameter `collateral_slug`. `createDeeplink( 'collateral_slug' )` supplies the three URL helpers. Other arguments and the hash are kept, so `/collaterals/?category=flyer` becomes `/collaterals/?category=flyer&collateral_slug=product-launch-flyer`.
- Open from a click: `pushState( { collateralSlug }, '', url )`. Close from the UI: `history.back()` when this modal added the entry (the category stays because the previous entry is the page URL). Back: the popstate listener closes the dialog and does not push.
- **Server redirect (D15):** `template_redirect` priority 1: a published `collateral` single answers 302 to `get_post_type_archive_link( 'collateral' )` with `collateral_slug=<post_name>`. Draft, pending, private or trashed collaterals (reachable only by someone allowed to preview them), documents, pages and posts are not redirected. No loop is possible: the archive is not a singular request.
- **First load:** the module reads the slug before `DOMContentLoaded`; a known slug opens with `push: false` and the grid button as opener; an unknown one (a draft is never in the payload, which is published only) is dropped with `replaceState` and nothing opens.
- **Simpler than Documents:** the collateral modal has no sheet history entries, so the `extra` step and `skipNextPop` are left out; only `pushed` and `closingFromHistory` remain.
- **Router trap:** Back or Forward onto a `?collateral_slug=` entry would make the Interactivity Router reload the page; the capture listener (step 7) prevents it and the §8 flows prove 0 reloads (a marker set on `window` before navigating disappears after a reload).

## 6. Work packages (in order; each leaves every suite green)

**WP1: shared pure pieces (Documents unchanged).**
1. `document-modal/deeplink.js`: add `createDeeplink( param )`; re-export the old names built from `createDeeplink( 'document_slug' )`; keep `SHEET_KEY` helpers untouched.
2. `functions.php`: generalise `devportfolio_document_download_target()` (D6, default argument).
3. `functions.php`: generalise the single redirect (D15): `devportfolio_modal_single_redirect_url()`, the two wrappers, the hook function; keep the document function and hook name working.
4. Run the plugin, theme and modal JS suites: counts unchanged (theme 388, modal JS 153).

**WP2: data layer (PHP only).** `devportfolio_collateral_pill()`, `devportfolio_collateral_stats()`, `devportfolio_collateral_thumb()`, `devportfolio_collateral_modal_payload()`, `devportfolio_collateral_modal_strings()` in `functions.php`.

**WP3: pure modules.** `collateral-modal/next.js`, `collateral-modal/copy-link.js`.

**WP4: the block (desktop shell and sidebar).** `block.json`, `render.php`, `style.css` (desktop part), `view.js` up to open/close/focus/History, register the block, put it in `archive-collateral.html`. Sidebar `fill()` for DPC-007 first, then Results, Made with, Next and footer (each a small, separately reviewed step in the same file).

**WP5: the mobile dialog (DPC-022).** Mobile shell in `render.php`, `style.css`, the swap and `enforceSingleDialog()` in `view.js`.

**WP6: tests** (§7).

**WP7: browser check** (§8), then **docs**: update `AGENTS.md` (Overview block list, theme architecture entry for `collateral-modal`, the new test totals) and this plan's As-built section.

## 7. Tests

### Theme PHP: `tests/test-collateral-modal.php` (`Devportfolio_Test_Collateral_Modal`, `@group theme`, extends `WP_UnitTestCase`)

- **Pill helper** (pure): `A4 · 2 pp`, `A4`, `2 pp`, `1 p`, both missing returns `''`, spaces trimmed.
- **Stats helper**: order kept; a non-array row skipped; both empty dropped; only a label or only a value kept; markup in a value stripped.
- **Payload**: `null` for a post or a document; fields for a full collateral (slug, plain title with `&` and quotes decoded, category singular name, `categories` slugs, description from the content words, `pill`, stats, tools); a draft and a trashed tool are left out; a collateral without any optional data has empty strings and arrays (no notices); `thumb.src` is `''` for a blank, a URL for a featured image.
- **Download modes**: attachment file (`file`, attachment URL), embed only (`embed`, `https`), a `javascript:` embed (`none`), neither (`none`); a document still resolves with its old key (regression for D6).
- **Render**: one `dialog.collaterals-modal` and one `dialog.collaterals-mobile`; labelled by their titles; payload JSON valid, `order` follows menu order then title, only published collaterals, `</script>` and `<` in a title are hex-escaped; every region is `hidden` in the markup; footer `hidden`; Close buttons have names.
- **Redirect** (DPC-020): a published collateral single gives `…/collaterals/?collateral_slug=<slug>`; draft, pending, private and trash give `''`; a document single still gives its `document_slug` URL (regression); a post and a page give `''`; the archive itself gives `''`; the hook is on `template_redirect` at priority 1 (assert with `has_action()`).
- **Template**: `archive-collateral.html` contains `wp:devportfolio/collateral-modal` once, after the grid, inside `documents-page__content`; the placeholder comment is gone; the block is registered.
- **CSS contract** (parsed from `style.css`): both media queries identical to `routing.js`; mobile dialog `inset: 0`, `border-radius: 0`, transparent `::backdrop`; desktop width uses `min(` and `100% - 32px`; the actions container is outside the scroller; reduced-motion rules exist; statistic label has `text-transform: uppercase` and the value does not.
- **JS structure** (regex on `view.js`, as `test-document-modal.php`): drops an unknown slug with `replaceState` and opens a known one without `pushState`; uses `textContent`, never `innerHTML`; `popstate` is registered with capture `true` at top level and calls `stopImmediatePropagation`; `collateral_slug` appears only through `createDeeplink`; no `requestAnimationFrame` in the swap path.

Keep `test-collateral-page.php` as is (it stays true).

### Node (`ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/collateral-modal 'node --test tests/*.test.mjs'`)

- `tests/next.test.mjs`: two after the current; wrap from the last (`[a,b,c,d]`, current `d` gives `a,b`; current `c` gives `d,a`); filter keeps only the slug; current outside the filter still gets the next two in the filter after its position; exactly 2 candidates; 1 or 0 candidates returns `[]`; unknown current id starts from the first; no mutation of the input.
- `tests/copy-link.test.mjs`: `linkFor` keeps origin and path, drops `?category=` and hash, encodes the slug; the clipboard API path; the `execCommand` path; both failing reports `failed` with the text selected; a rejected promise is handled (no unhandled rejection).
- `tests/routing.test.mjs`: the stylesheet repeats the same two queries as the constants (D3).
- `tests/deeplink.test.mjs` (collateral-modal): `createDeeplink( 'collateral_slug' )` set, read, drop, other args and hash kept, path-only and absolute URLs. The existing Documents deeplink test is unchanged and must pass against the refactored module (WP1).

### Plugin

No change. Run the suite once to prove it (829 tests, 12 skipped).

## 8. Browser check (no runner exists; headless Chrome over CDP against `https://devportfolio.ddev.site/collaterals/`, as DPD-026 and DPC-006)

Seeded collaterals have no files and no thumbnails, so first attach a PDF to two of them (or use the existing seeded ones and test the blank thumbnails), and give them statistics and tools in wp-admin or with `ddev wp post meta update`. Snapshot first: `ddev snapshot`.

1. 1440, 1024, 800, 701: click each item; the dialog opens, centred, never wider than the screen, sidebar order correct; Esc, backdrop and Close each close and focus returns to the same item's button.
2. 700 and 390 and 844x390 touch emulation: the mobile dialog opens, full screen; Back closes it and the URL is `/collaterals/?category=…` unchanged; **no flash or page reload** on Back and Forward (the Interactivity Router trap), checked through 6 open/close/Back/Forward flows.
3. Resize 1024 to 600 and back while open: same collateral, same URL, no two dialogs ever open (`document.querySelectorAll( 'dialog[open]' ).length <= 1` at every step).
4. Deeplink (DPC-020): `/collaterals/?collateral_slug=<slug>` opens on load (mobile dialog at 390); an unknown slug and a **draft's** slug (create one for the check) are cleaned, the plain page shows and no history entry is added; `/collaterals/<published-slug>/` answers 302 to the deeplink and a draft single does not; other params (`?utm=1`) and a `#hash` survive open and close; a click grows `history.length` by 1 and Close restores it; a deeplink load then Close removes the parameter and stays on the site; Back closes, Forward reopens, 0 reloads; the Copy link URL in a **new tab** opens the same collateral; with `?category=flyer&collateral_slug=<a logo>` the next list is the Flyer items.
5. Next: two thumbnails, wrap on the last item, none with a category holding fewer than 3 items (sidebar shows no Next), click swaps content and `replaceState` (one Back closes the modal), focus is on the title.
6. Statistics and tools: hidden without data, wrap with a 120-character value; a draft tool never shown.
7. Download: file triggers a download, embed opens a new tab, none is inert. Copy link: label `Copied` for about 2s and the clipboard holds `https://devportfolio.ddev.site/collaterals/?collateral_slug=…`; with `navigator.clipboard` overridden to reject, the fallback field appears with the text selected.
8. Tab order is Close, Download, Copy link, Next, in a sensible order; keyboard-only operation of every control; reduced motion removes animations.
9. Mouse check of the touch states that headless Chrome cannot emulate is listed in As-built (as in DPC-006).

## 9. File inventory

New:
- `blocks/collateral-modal/{block.json,render.php,style.css,view.js,next.js,copy-link.js}`
- `blocks/collateral-modal/tests/{next,copy-link,routing,deeplink}.test.mjs`
- `tests/test-collateral-modal.php`

Changed:
- `functions.php` (register the block; helpers; `devportfolio_document_download_target()` default argument; shared single-URL redirect)
- `blocks/document-modal/deeplink.js` (factory; same exports)
- `templates/archive-collateral.html`
- `AGENTS.md` (docs commit)

Unchanged and must stay green: every Documents test, `test-collateral-page.php`, `test-collateral-grid.php`, the plugin suite.

## 10. Risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | The Interactivity Router reloads the page on Back or Forward onto `?collateral_slug=…` (it happened with documents). | Capture-phase `popstate` registered at module top level with `stopImmediatePropagation()`, tested by structure and the 6-flow browser check. |
| R2 | Refactoring `deeplink.js` regresses the Documents modal. | The old exports are rebuilt from the factory and the existing Documents tests run unchanged in WP1 before anything else. |
| R3 | Sidebar width plus a narrow desktop window leave the body too small (701 to 900px). | Sidebar token narrows at 900px; the dialog is full viewport at 1200px and below; Q1 asks design to confirm. |
| R4 | Next thumbnails use the featured image `medium` size, which may not exist for a tiny upload. | `wp_get_attachment_image_url()` falls back to full; the script hides a failed image and shows the numbered blank, as in the grid. |
| R5 | The payload carries every collateral (small now). | Plain text and URLs only, thumbnails not inlined; revisit only if the catalog reaches hundreds. |
| R6 | Two code paths for open/close (documents and collaterals) drift. | D2 records the debt and the trigger for extraction; shared pieces are imported, not copied. |
| R7 | Clipboard is unavailable on plain HTTP and in some in-app browsers. | Fallback chain (D10) and the selectable field; the DDEV site is HTTPS. |
| R8 | A collateral open while the filter pills change (Back/Forward changing the category). | Closing the modal wins (popstate order); next is read at open and swap only. |

## 11. Conventional commits

Branch `feat/DPC-007-008-009-010-011-020-021-022-Collateral-Modal` from `main` **after the DPC-006/014 branch (`feat/DPC-006-014-Collateral-Item-Masonry`) is merged**; if it is not merged yet, branch from it and note the dependency in the PR. Rules as in `AGENTS.md`: subject `type(scope): description` (72 characters or fewer); footer `Refs:` with the tickets, then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`; stage explicit paths, never `git add -A` (other tickets' plan files are untracked in the tree); every commit leaves the suites green.

| # | Subject | Stages | Refs |
|---|---|---|---|
| 1 | `docs(plans): add the collateral modal, sidebar and deeplink plan` | this plan | DPC-020, DPC-021, DPC-022, DPC-007, DPC-008, DPC-009, DPC-010, DPC-011 |
| 2 | `refactor(theme): parametrise the modal deeplink helpers` | `blocks/document-modal/deeplink.js` | DPC-021 |
| 3 | `refactor(theme): let the download target take a file meta key` | `functions.php` (download target only) | DPC-011 |
| 3b | `refactor(theme): share the single-to-deeplink redirect between modals` | `functions.php` (redirect helpers and hook) | DPC-020 |
| 4 | `feat(theme): add the collateral modal payload helpers` | `functions.php` (pill, stats, thumb, payload, strings) | DPC-007, DPC-008, DPC-009, DPC-010, DPC-011 |
| 5 | `feat(theme): add the collateral next list and copy link modules` | `blocks/collateral-modal/{next.js,copy-link.js}` | DPC-010, DPC-011 |
| 6 | `feat(theme): add the collateral desktop modal and sidebar` | `blocks/collateral-modal/{block.json,render.php,style.css,view.js}`, `functions.php` (register), `templates/archive-collateral.html` | DPC-020, DPC-021, DPC-007, DPC-008, DPC-009, DPC-010, DPC-011 |
| 7 | `feat(theme): add the collateral mobile modal` | `render.php`, `style.css`, `view.js` mobile parts | DPC-022 |
| 8 | `test(theme): cover the collateral modal helpers and markup` | `tests/test-collateral-modal.php` | DPC-020, DPC-021, DPC-022, DPC-007, DPC-008, DPC-009, DPC-010, DPC-011 |
| 9 | `test(theme): cover the collateral modal scripts` | `blocks/collateral-modal/tests/*.test.mjs` | DPC-010, DPC-011, DPC-021 |
| 10 | `docs(agents): document the collateral modal and sidebar` | `AGENTS.md`, this plan's As-built section | all eight |

Commits 2, 3 and 3b are green on their own (default arguments, same exports). Commits 6 and 8 may be squashed if the suite needs the tests to pass the gate (AGENTS allows a small pair); keep 7 separate because it is a separate phase.

PR title: `feat(theme): add the Brand Collaterals modal and sidebar` (under 72 characters). The body comes from `.github/pull_request_template.md` (tick New feature; Description, Implementation, How to Test = the §8 checklist). It states that the body, footer and mobile header and footer come with their own tickets (DPC-012, 013, 015, 016), and ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit.

## 12. As-built notes

Built on `feat/DPC-006-014-Collateral-Item-Masonry` (not yet merged to `main`; the PR must note the dependency). Nothing is committed.

**Totals:** baseline theme PHP 388, plugin 829 (12 skipped), modal JS 153, collateral grid JS 17. Now: theme PHP **424** (+36 in `tests/test-collateral-modal.php`), plugin 829 (12 skipped, unchanged), modal JS 153 (unchanged), collateral grid JS 17 (unchanged), collateral modal JS **18** (new: `next`, `copy-link`, `routing`, `deeplink`).

**Deviations from the plan:**
- **Redirect hook name kept.** `devportfolio_redirect_document_single` stays the `template_redirect` hook (the existing test asserts it) and now tries the document, then the collateral resolver. The separate `devportfolio_redirect_modal_singles()` was dropped.
- **`createDeeplink()` returns plain functions** (no `this`), so the Documents named exports are plain references. `PARAM` stays exported.
- **Payload title is the raw `post_title`** (html-decoded), not `get_the_title()`: that runs `wptexturize`, which curled the quotes in the JSON.
- **The copy field is visually hidden, not `hidden`**, because `execCommand('copy')` needs a selectable element. It is revealed (`.is-shown`) only for the fallback.
- **Next swap has no View Transition** (the plan lists none for it); only the desktop/mobile swap on resize uses one.
- **`copyText()` returns `copied` or `failed`** (the plan's `failed` with the text selected). The view handles the reveal and the hint.
- Payload has `order` and `items` as in D4; the item carries `thumb` as `{ src, srcset, ratio }` (D5).

**Checks run:** theme PHPUnit 424 OK; plugin PHPUnit 829 (12 skipped) OK; node suites above all green; `php -l` on the changed files.

**Browser check (headless Chrome over CDP, 1440 / 390 / resize, `https://devportfolio.ddev.site/collaterals/`):** passed:
- desktop click opens, `?collateral_slug=` added, one history entry, Esc closes and removes the parameter;
- Next thumbnail swaps the content, keeps the modal open and replaces the entry (history length unchanged), focus on the title;
- deeplink load opens; `utm` and the hash survive; an unknown slug is dropped and nothing opens;
- phone (390, touch) opens the mobile dialog only; Back closes it and keeps `?category=flyer`;
- desktop Back closes the modal, keeps `?category=flyer`, and the page keeps its JS state (no reload);
- resize 1024 to 600 while open swaps to the mobile dialog, same collateral and URL;
- Next list follows the Flyer filter when a Logo deeplink is open.

**Browser checks that failed or were not verified:**
- **Copy link:** the clipboard permission was refused in headless Chrome, so the `execCommand` path also failed and the fallback showed `Press Ctrl/⌘ + C` with the link selected. The clipboard-success path and the "Copied" label were **not** observed. Needs a manual check in a real browser.
- Not checked: keyboard-only tab order, reduced motion, forced colours, touch landscape (`390x844` sideways), 6 Back/Forward flows with a reload count, the Results/Made with rendering on a seeded collateral with statistics and tools (the sample data was checked only for the payload), the Download modes on screen (checked in PHP only), reading the on-screen layout (CSS not visually reviewed).
- The body (DPC-012) and footer (DPC-013) are empty by design; DPC-015/016/019 mobile content is not in this ticket.

**Not done:** no commits (none asked for); AGENTS.md was updated in the working tree only.

**Changed after build:**
- Close button changed from a filled to an outline button (same size, ink ring and X), on request (DPC-007 item 12 now says outline).
- Footer buttons pinned to the bottom of the sidebar. Placing the sidebar on the right (explicit `grid-column`) had put the body in a second grid row, so the sidebar stopped short of the dialog's bottom edge. Both now share row 1 (see 5.5). Measured in headless Chrome at 1440x900 and 1440x560: the actions end on the sidebar's bottom edge.

## Open questions (each has a default that this plan already follows)

- **Q1 Dialog and sidebar width (design).** The story says width is "to be confirmed by design because of the sidebar". **Default:** `--cm-width: 1280px`, sidebar `clamp( 300px, 30%, 380px )`, full viewport at 1200px and below. One token each.
- **Q2 Scope of DPC-020 (deeplink).** **Resolved:** DPC-020 is part of this plan (§5.6, D15, tests, browser check); no separate plan.
- **Q3 Interim Close on the mobile dialog.** DPC-022 says "blank", but a dialog with no control other than Back is a trap on some devices. **Default:** one Close button in the empty header, which DPC-015 replaces.
- **Q4 Description length.** DPC-007 says "full text, wraps" and also "the post excerpt, else the first 20 words of the content". Collaterals have no excerpt, so the text is the first 20 words with `…`. **Default:** reuse `devportfolio_document_description()` unchanged. If the full content should show, say so and the payload takes `wp_strip_all_tags( post_content )` with a length cap.
- **Q5 Footer position (DPC-013).** **Default:** the footer sits under the body only and the sidebar runs the full height with its own pinned buttons. A footer spanning the whole width is one grid-area change.
- **Q6 History for Next.** **Default:** `replaceState` (D8), so one Back always closes the modal. If each collateral should be its own entry, the swap pushes and Back walks backwards through them.
- **Q7 Tool icons.** **Default:** `tools` is a list of names. DPD-037 / DPB-033 change it to `[{ name, icon }]` for Documents; when they land this block follows in the same ticket (one function `fillTools()`).
