# Plan: Collateral modal viewer, footer and mobile viewer (DPC-012, 013, 015, 016, 017, 018, 019)

Final location when implemented: `.claude/plans/collaterals/DPC-012-013-015-016-017-018-019-Collateral-Modal-Viewer-Footer-And-Mobile.md` (first commit copies this plan there; same section layout as `DPC-007-...-Collateral-Modal-Sidebar.md` and `DPC-024-Shared-Extraction-From-Documents.md`).
Theme root for every path below: `wp-content/themes/devportfolio/` (written `T/`). Plugin: `wp-content/plugins/custom-post-types-dp/` (`P/`).

Tickets:
- **DPC-012** Modal / Body - Viewer (High, needs DPC-021, DPC-023).
- **DPC-013** Modal / Body - Footer (Medium, needs DPC-012).
- **DPC-015** Modal - Mobile / Header (Medium, needs DPC-022).
- **DPC-016** Modal - Mobile / Footer (Medium, needs DPC-015).
- **DPC-017** Modal - Mobile / Footer - Pages (Medium, needs DPC-016, DPC-023).
- **DPC-018** Modal - Mobile / Footer - Details (Medium, needs DPC-016, DPC-008, DPC-009).
- **DPC-019** Modal - Mobile / Viewer - Landscape and Gestures (Medium, needs DPC-012, DPC-022).

The mobile stories describe what Documents already does (DPD-017..024, DPD-029..031), so each mobile ticket is "the shared Documents behaviour, wired to the collateral dialog". Section 2b maps every story line to the shared piece that gives it, and what is collateral-only.

---

## 1. Context

The Collaterals page opens `dialog.collaterals-modal` (desktop) and `dialog.collaterals-mobile` (mobile). DPC-007..011/021/022 built the sidebar, Next list, Download, Copy link, deeplink `?collateral_slug=`. The body is an **empty** `.collaterals-modal__stage` and the footer is an **empty** `<footer class="collaterals-modal__footer" hidden>` (`T/blocks/collateral-modal/render.php`, left for these two tickets). `view.js` already dispatches `collaterals:modal-change` / `collaterals:mobile-change` but always with `page: 1` (`emit()` in `T/blocks/collateral-modal/view.js`).

The Documents modal has all of what is needed (DPD-009..015, DPD-024): StPageFlip book, stored page images with pdf.js fallback behind a capability gate, arrows, keys, touch swipe/pinch/double-tap, footer pager, progress line, "can't be previewed". DPC-023 already stores page images, names and thumbnails for collaterals and exposes `cptdp_get_collateral_pages( $id )` (same array shape as `cptdp_get_document_pages()`, valid only while the file fingerprint matches, never converts).

**Rule from DPC-024 section 0: a collateral feature reuses a shared piece or extends it through an argument. It must not copy a Documents function, script or CSS block.** This plan follows it.

## 2. Goal and acceptance criteria

DPC-012 (body):
1. On opening a collateral the first page(s) show in the book viewer (stored images; pdf.js only as fallback behind the same gate).
2. Portrait with 2+ pages = spread **while the spread's pages stay big enough** (`spreadIsWorthIt()` for mouse, `spreadFits()` for touch), else one larger page. Landscape (`ratio > 1`) or 1 page = one page.
3. Round Previous/Next arrows at the left and right of the body; on hover for mouse devices, always visible on touch, visible on keyboard focus; `aria-disabled` + dimmed at the ends; **hidden for 1 page** (and for 0). Left/Right arrow keys turn.
4. Touch (desktop dialog on tablets): swipe turns, pinch and double-tap zoom.
5. Touch with a 2-page view: a **tap flips** the document, and the text **"Tap to Flip"** shows below the document.
6. No valid pages and no usable fallback: status message "This collateral can't be previewed." (plus the hint "Use Download to open it."); Download in the sidebar still works; arrows, footer and tap hint hidden.
7. Verification set: multi-page portrait, landscape, single-page, and one with no pages.

DPC-013 (footer):
1. Desktop footer under the body (sidebar keeps full height): pager Back, numbers, Next. Up to 9 pages all numbers, else 1, last and a window around the current page (reuse `pagerItems()`); visible page(s) filled (`.is-on`, `aria-current="page"`); Back/Next `aria-disabled="true"` at the ends.
2. Thin progress line on the footer's top edge; `role="progressbar"`, `aria-valuetext` like `Pages 3–4 of 24` / `Page 5 of 24`, `aria-valuenow` percent.
3. Whole footer hidden for fewer than 2 pages.
4. Clicking a number goes to that page(s); the pager and line update as the visitor turns by any means (arrows, keys, swipe, tap, drag of the book).

Full parity with the Documents viewer (everything below is in scope, nothing is left for "later"):
- **Every file kind a collateral accepts.** PDF, Office files (presentations `pptx/ppt/odp`, spreadsheets `xlsx/xls/ods`, Word `docx/doc/odt`) and raster images (`image`, one page). Converted pages go through the normal book; the unit wording follows the kind (`page`, `slide`, `sheet`): pill `PPTX · 12 slides`, `Slide 3 of 12`, pager and arrow labels.
- **Native viewers** when the matching Settings > Documents > Native viewer switch is on (PDF selectable text `native_pdf`, PowerPoint `native_pptx`, Excel `native_xlsx`, Word `native_docx`): same limits, same privacy options, same fallback to the stored pages or "can't be previewed" with the per-kind hint. The switches are shared settings, so no new setting.
- **Selectable text for PDFs** (`native_pdf`): pdf.js first with a real text layer, StPageFlip mouse and corner events off, pages turn by arrows, pager and keys.
- **Zoom controls** for the native Word viewer (round zoom buttons), the workbook grid and the slide canvas, as Documents.
- **Mobile dialog** (`dialog.collaterals-mobile`): the full mobile viewer (one page fitted to the width or two when they fit, swipe, edge taps, pinch and double-tap zoom, round hints, hint text, rotation fade, landscape chrome with floating Close and Download), the mobile footer (Pages, position, Details) with the progress bar, the round details/pages buttons, native scroll viewers (xlsx grid, docx scroll viewer, pptx canvas).
- **Pages sheet** (gallery of thumbnails and list of names, remembered view and scroll, drag to dismiss) for collaterals, using the stored `thumbs` and `names`.
- **Details sheet** on mobile with the collateral's sidebar content (category and size pills, title, description, Results, Made with, Next collaterals, Download, Copy link), using the shared bottom-sheet shell and sheet controller.
- **Capability gate** (`capability.js`), `data-limits`, the failure flag, data saver, and the `devportfolio_document_viewer_limits` filter (applies to both pages).
- **Deeplink and history** keep working with the sheets: Back closes only a sheet, Esc closes the sheet first, the router `popstate` rule from AGENTS.md applies (listeners registered while the script runs, not lazily).
- Accessibility parity: live regions, `aria-disabled`, focus kept on the pager control after redraw, reduced-motion handling, `inert` viewer behind a sheet.

## 2b. Mobile stories (DPC-015..019): acceptance and where each line comes from

Window 700px wide or less, or a touch screen held sideways (`routing.js`: `(max-width: 700px)` or `(pointer: coarse) and (orientation: landscape) and (max-height: 520px)`); otherwise the desktop dialog.

**DPC-015 Header** (shared mobile markup, D11/D14):
- One row: outline Close (X) left, title, outline Download right (same modes as DPC-011: `file`, `embed` in a new tab, `none` = `aria-disabled`; via `applyDownload` / `guardDisabledDownload`).
- A long title is truncated with an ellipsis (`min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap` on `.collaterals-mobile__title`), never wrapped over the buttons.
- Close and Back close the modal (the existing `finishClose` history logic stays).
- Held sideways on touch the header is gone: Close floats top left, Download top right (same 38px size and 12px inset as the other round controls).

**DPC-016 Footer** (shared mobile footer, `mobileFooterParts()` in `pager.js`):
- Footer row: Pages button (left), indicator centre in bold (`2 / 24`, `3–4 / 24` for a spread), Details button (right); progress bar for 2+ pages.
- One page: footer and progress bar hidden; a round Details button appears instead.
- Held sideways: no footer; round Pages button above the round Details button at the bottom right; progress bar stays for 2+ pages.
- Pages opens the Pages sheet (DPC-017), Details the Details sheet (DPC-018); the indicator follows the page(s) shown.

**DPC-017 Pages sheet** (shared `pages-sheet.js`, D12): header title "Pages" left, Gallery and List icon buttons right (active = filled dark, other = outlined). First open is Gallery, then it remembers the last view and the scroll position **per collateral while the page stays open** (in memory, `createSheetMemory()`). Gallery: 3 columns, 3 rows visible, scrolls; portrait thumbnail tiles (numbered blank page when none); current page with a thick dark outline. List: one row per page, small page square (thick outline for the current page), name in bold, "current" in lighter text after the current page's name, "Page" (or `Slide`/`Sheet`) when unnamed, thin separators. Tapping a page jumps to it and closes the sheet. Thumbnails load only when visible (one `IntersectionObserver`, about one row ahead; none in List; none with data saver). Close by tapping the dim area, dragging the handle down, Esc, or Back (Back closes only the sheet; the router `popstate` rule applies). Page names come from PDF bookmarks, Excel sheet names and Word headings through `pageNames` (D6).

**DPC-018 Details sheet** (collateral-specific content on the shared shell, D13): bottom sheet over the dimmed viewer; title (bold) and X top right; category pill (filled) and paper size + pages pill (outline) (`item.category`, `item.pill`); description; "MADE WITH" with a square pill per tool; "Results": one row per statistic with label (start, uppercase) and value (end, bold); footer: full-width filled dark Download button with the download icon and a Copy link outline button. **A section shows only when it has data.** Download works as DPC-011, Copy link copies the deeplink (`linkFor`, `copyText`, same fallbacks as desktop). Closes by X, dimmed area, swipe down, Esc or Back, returning to the viewer on the same page. Held sideways it stays open on rotation and splits into two columns (CSS only). (The desktop sidebar's "Next collaterals" is not in the story, so it is **not** in this sheet.)

**DPC-019 Viewer: landscape and gestures** (shared `mobile-viewer.js`, `gestures.js`, `touch-zoom.js`, `layout.js`, D11):
- Portrait phone upright: one page fitted to the width on light grey; swipe left = next, right = previous, the page follows the finger; tapping the outer 20% of either side turns (8px dead strip at the edge); round hints on the edges hide at the ends and while zoomed.
- Held sideways, or any screen where two pages fit (`spreadFits()`: 2+ pages and `stageW >= 2 * stageH * ratio`, re-checked on resize), a portrait collateral with 2+ pages shows a two-page book and turns two at a time; a landscape collateral stays one page.
- Zoom 1x-4x by double-tap (centre 60%) or pinch; while zoomed a drag pans, and swipe and edge taps are off. Keys: arrows, `+`/`=`, `-`, `0`.
- Hint "swipe or tap edges" under the page until the first touch, turn, zoom or key; a landscape page shows the pills "Rotate for a bigger view" (dark, rotate icon) and "Pinch to zoom" (lighter) instead.
- Rotation fades the pages out (120 ms) and back in (260 ms) once the size is still for 250 ms; the page and URL are kept. No fold shading in a mobile spread.
- A single page shows no pager or thumbnails (footer hidden, round Details button only).
- The "Tap to Flip" hint of DPC-012 is the **desktop dialog on a touch tablet** only; the mobile dialog uses the DPC-019 hint and edge taps.


## 3. Out of scope
Only what does not apply to collaterals or is not a viewer feature:
- **SVG sources** (the plugin refuses to rasterise them; the Page images box already tells the editor to upload a PNG or PDF export; the SVG stays a download).
- **A page-number deeplink** (Documents has none either).
- **New Settings.** The native viewer switches, page image options and the render gate are the shared Documents settings.
- **Changing what the plugin stores** (page images, names, thumbnails, manifest). Only read through `cptdp_get_collateral_pages()` and the `cptdp_document_*` helpers that already resolve by post type (verify with the tests below; add a small plugin change only if a helper turns out to be document-only, see D6).
- **Plugin admin screens.** Unchanged.

## 4. Facts verified in the repo (do not re-derive)

**Documents viewer** `T/blocks/document-modal/`:
- `viewer.js` (904 lines) `createViewer({ stage, status, pdfjs, limits, units, selectableText, onGeometry, onFlip, onSwipe })` returns `{ show(doc,page), isTurning(), goTo(page,{animate}), destroy() }`. It loads StPageFlip from `./vendor/page-flip.browser.js` (`FLIP_SRC`, `loadFlipLibrary()`), owns `session`, `source`, `pageEls`, `container`, `flip`, `observer`, `resizeTimer`, `step`, `current`, `builtFor`, `touchZoom`, `bindTouch()`, `fadeBook()`, `onResize()`, `stepForStage()`, `buildBook()`, `buildPages()`, `ensureWindow()`, `draw()`, `fail()`, `unavailable()`, `reset()`. `unavailable()` calls `onGeometry({pages:0, step:1})`. The viewer never owns the current page: `onGeometry({pages, step})` **returns** the page to show; `onFlip(page)` reports a drag/corner turn; `onSwipe(dir)` reports a swipe (only when `(pointer: coarse)`).
- Hard-coded class names inside `viewer.js`: `documents-modal__book`, `__book--selectable`, `__book--cropped` (+ custom property `--documents-crop`), `__page--left/right`, plus `documents-modal__zoom-*` (native only).
- Native/Office code in `viewer.js`: `showPptx`, `showXlsx`, `showDocx`, `showZoomFab`, `hideZoomFab`, `destroyNative`, the `grid/pptx/docx/pptxBusy/pptxCount/zoomFab` variables, and the `viewerMode( doc ) === 'native'` branch in `show()`; they import `./mode.js`, `./native-*.js`.
- `page-source.js` (344 lines): `withTimeout`, `loadImage`, `imageSource( urls, ratio, imageWidth, preloaded, crop )`, `pdfSource`, `rememberFailure`, `tryImages`, `tryPdf`, `acquireSource( doc, alive, { pdfjs, limits, selectableText } )`, `buildPageEls( count, label )` (hard-codes `documents-modal__page/__sheet/__pn`), `mountWindow`. Imports `./pager.js`, `./layout.js`, `./dom.js`, `./capability.js`.
- Pure/generic: `layout.js` (`fitPage`, `renderWindow`, `canvasScale`, `orientationOf`, `spreadFits`, `spreadIsWorthIt`), `pager.js` (`stepFor`, `normalisePage`, `pagerItems`, `visiblePages`, `pagerState`, `turnPage`, `indicatorParts`, `mobileFooterParts`, `visibleRange`), `gestures.js` (`swipeDirection`, `classifyTap`, `EDGE_ZONE`, `DEAD_STRIP`), `touch-zoom.js` (`createTouchZoom`), `capability.js` (`budgetFor`, `mergeLimits`, `clientRenderTier`, `safeStorage`, `clearFailure`, `markFailure`, `isBenignRenderError`, `readEnvironment`).
- `dom.js` `el()`, `svgIcon()` (hard-codes class `documents-modal__icon`); `status.js` `createStatus({stage,status})` with `set('loading'|'unavailable'|null)` and `setHintKey()` (reads `.documents-modal__state-text`, `__state-hint`, `__spinner`, and `data-loading`, `data-unavailable`, `data-hint*`).
- Generic modules are also imported by `mobile-viewer.js`, `mobile.js`, `pages-sheet.js`, `details-sheet.js`, `bottom-sheet.js`, `native-*.js`, `touch-zoom.js`, and tested in `document-modal/tests/{capability,gestures,layout,pager,touch-zoom}.test.mjs`.
- `view.js` (497 lines) footer/controller functions, all inside one closure: `emit()` (event `documents:modal-change`, detail `{...state}`), `makeButton`, `drawFooter()` (arrows `aria-disabled` via `pagerState`, hidden when `pages < 2`; footer/pager/indicator hidden when `pages < 2`; progress `aria-valuetext` via `positionText( words, first, both ? last : first, pages )` from `mode.js`, width `percent%`, `aria-valuenow`; pager rebuilt from `pagerItems` with Back/Next buttons `data-turn="-1|1"` and number buttons `data-page`, `aria-current` on visible pages, class `is-on`; restores focus to the same control after rebuild), `syncFromViewer( page )`, `setGeometry({pages,step})` (sets state, `drawFooter()`, `emit()`, returns page), `setPage( page, animate )` (normalise, `drawFooter`, `emit`, `viewer.goTo`), `turn( direction )` (ignored while `viewer.isTurning()`; uses `turnPage`), arrow click handlers, `dialog` keydown (Left/Right, ignored in form fields and `.documents-modal__grid`), `els.pager` click handler (`data-turn` / `data-page`), `openDesktop` (computes `state.pages` from `images.length || pages`, `state.step = stepFor( pages, landscape ? 'landscape' : 'portrait' )`).
- `render.php` of Documents holds the arrow buttons (`documents-modal__arrow--prev/--next`, SVG chevrons `M15 5l-7 7 7 7` / `M9 5l7 7-7 7`), the stage, the status block (role=status, `data-loading`, `data-unavailable`, `data-hint`, spinner/state-text/state-hint spans) and the footer markup (nav `.__pager`, `.__indicator` > `.__bar[role=progressbar] > i`).
- CSS in `document-modal/style.css` (1971 lines): `__footer` 278, `__arrow[hidden]` 350, focus rings 441, `__body` 448, `__stage` 461, `__book/__page/__sheet/__pn/__page--left/right/broken` 470-600, `__state/__spinner` 686-730, `__arrow` 731-761, hover/disabled 871-875, `__footer/__pager/__pg/__gap/__indicator/__bar` 897-980.

**Collateral modal** `T/blocks/collateral-modal/`:
- `render.php`: body is `.collaterals-modal__main > .collaterals-modal__body > .collaterals-modal__stage` then `<footer class="collaterals-modal__footer" hidden></footer>`; root attrs only `data-strings`. No arrows, no status element, no `data-pdfjs`/`data-limits`.
- `view.js`: `show( id, {push, trigger} )`, `swapTo( id )` (Next thumbnail: replaces collateral, keeps modal open), `swapDialogs()` (desktop<->mobile on resize via View Transition), `closeOpen`, `finishClose`, `emit()` with `{ id: currentId, page: 1 }`. Imports `../shared/{routing,modal-trigger,deeplink,download}.js`, `./next.js`, `./copy-link.js`.
- `style.css`: `.collaterals-modal__main` (grid rows `minmax(0,1fr) auto`), `__body` (flex, `--cm-surface-alt`), `__stage` (flex 1), `__footer[hidden]` at lines ~366-390; own `--cm-*` tokens.
- `block.json`: `"style": "file:./style.css"`, `"viewScriptModule": "file:./view.js"`.
- Payload `devportfolio_collateral_modal_payload()` (`T/functions.php` ~1399): `devportfolio_media_payload_base()` + `categories, pill, stats, downloadMode, downloadUrl, thumb{src,srcset,ratio}`. **Missing:** `images`, `imageWidth`, `cropBottom`, `ratio` (full page, not `thumb.ratio`), `pages`, `viewerUrl`, `pdfBytes`, `kind`.
- `devportfolio_document_viewer_data()` (`functions.php` ~966) returns early for non-documents and reads `cptdp_document_file`. `devportfolio_document_normalise_stored_pages()` is already generic. `devportfolio_document_pdfjs_urls()` and `devportfolio_document_viewer_limits()` are post-agnostic.
- `P/includes/post-types/document-pages.php`: `cptdp_document_pdf_url( $id )` already resolves the file meta through `CPTDP_Page_Store::file_meta_key()`, so it works for collaterals despite its name. `pdf` URL is '' for non-PDF sources (Office/image) - then only stored images are used.
- A collateral source may be an **image** (one page, kind `image`), PDF, or Office file (converted). All produce stored images; only PDF has a pdf.js fallback.
- "Tap to Flip" exists nowhere.

**Tests that read files by path** (must be updated if a file moves): `T/tests/test-document-viewer.php` (lines ~66, ~548), `test-document-presentations.php` (~137), `test-document-modal.php` (~380), `test-document-modal-mobile-viewer.php` (~77, 277, 284, 321), `test-document-spreadsheets.php` (~239), `test-document-modal-mobile-pages.php` (~273). Node tests: `document-modal/tests/{capability,gestures,layout,pager,touch-zoom}.test.mjs`.

## 5. Decisions

- **D1 Move the generic modules to `T/blocks/shared/`** with `git mv` (history kept): `layout.js`, `pager.js`, `gestures.js`, `touch-zoom.js`, `capability.js`, `page-source.js`, `dom.js`, `status.js`. Update every import (`./x.js` -> `../shared/x.js`) in the document-modal files listed above. Move the five matching Node tests to `shared/tests/` and fix their import paths. Update the PHP source-text tests to read the new paths (mechanical).
- **D2 Class prefix is an argument, not a rewrite.** `dom.js` `svgIcon( ...paths )` stays but takes the icon class through a new `svgIcon( paths, className = 'documents-modal__icon' )`-style option kept backward compatible (check all call sites with grep). `buildPageEls( count, label, prefix = 'documents-modal' )` and `createStatus({ stage, status, prefix = 'documents-modal' })` read the prefix. The collateral passes `'collaterals-modal'`. Default keeps Documents behaviour and tests unchanged.
- **D3 One generic viewer, not a wrapper.** Because collaterals need every Documents feature (native Office viewers, selectable text, zoom fab), do **not** split `viewer.js` into a "book part" and a "native part". Move the whole viewer to `T/blocks/shared/viewer.js` (`git mv`) and parameterise it: `createViewer({ stage, status, prefix, pdfjs, limits, units, selectableText, onGeometry, onFlip, onSwipe, onTap = null })`. Moves with it, unchanged in behaviour: `loadFlipLibrary`, state, `buildPages`, `ensureWindow`, `fail`, `draw`, `measure`, `buildBook`, `stepForStage`, `touchZoom`, `bindTouch`, `fadeBook`, `onResize`, `showPptx`, `showXlsx`, `showDocx`, `showZoomFab`, `hideZoomFab`, `destroyNative`, `reset`, `unavailable`, and `show`, `isTurning`, `goTo`, `destroy`. Class names become `${ prefix }__book`, `__book--selectable`, `__book--cropped`, `__page--left/right`, `__zoom-fab`, `__zoom-button`, `__native*`, `__grid`; the crop custom property becomes the shared `--book-crop` (CSS and its one test change in the same commit). The StPageFlip file moves to `T/blocks/shared/vendor/page-flip.browser.js` and the OOXML bundle `document-modal/vendor/ooxml/` to `T/blocks/shared/vendor/ooxml/` (`git mv`; fix `FLIP_SRC`, the `import()` URLs in `native-*.js`, the `?v=` version checks, `vendor/ooxml/README.md`, and every PHP test or asset check that names the path: `grep -rn "vendor/ooxml\|page-flip" T/tests T/blocks`). Also move to `shared/`: `mode.js` (`viewerMode`, `unitOf`, `wording`, `pieceLabel`, `positionText`, `hintKeyFor`, `canShowOnMobile`, `nativeAllowedOnMobile`, `isNativeScroll`, `isNativeGrid`, `canDrawOffice`, kind helpers; it is generic once the kind comes from the payload), `native-pptx.js`, `native-xlsx.js`, `native-docx.js`. `document-modal/viewer.js` is deleted; `document-modal/view.js` imports `../shared/viewer.js` with `prefix: 'documents-modal'`. Collateral passes `'collaterals-modal'`, and `selectableText` from `data-selectable-text` as Documents does.

- **D4 One generic pager controller.** Create `T/blocks/shared/pager-controller.js` exporting `createPagerController({ els, prefix, viewer, words, eventName, target, positionText })` where `els = { footer, pager, indicator, bar, prev, next }`. It owns `state { id, page, step, pages }` and implements, by moving the existing code: `drawFooter()`, `syncFromViewer()`, `setGeometry()`, `setPage()`, `turn()`, arrow click handlers, the Left/Right key handler (skipping form fields and elements matching an optional `ignore` selector), the pager click handler, and `emit()` (dispatches `eventName` on `target` with `detail: { ...state }`). It returns `{ state, drawFooter, setGeometry, syncFromViewer, setPage, turn, open( id, pages, step, page ), setWords( words ) }`. `viewer` is injected after creation (viewer needs `onGeometry` = controller.setGeometry; controller needs `viewer.goTo/isTurning`), via `controller.attach( viewer )`. `makeButton` moves into it. Documents `view.js` is rewired to use it with `prefix: 'documents-modal'`, `eventName: 'documents:modal-change'`; only the controller wiring changes - header, mobile, deeplink, history code stays.
- **D5 CSS written once.** New shared stylesheet `T/assets/css/viewer.css` holding the book/page/sheet/pn/state/spinner/arrow/footer/pager/pg/gap/indicator/bar rules moved out of `document-modal/style.css` (lines listed in section 4). To keep one rule body per rule, selectors are lists over both prefixes (`.documents-modal__book, .collaterals-modal__book { ... }`), and colours come from custom properties that each modal sets on its root (`--viewer-ink`, `--viewer-surface`, `--viewer-line`, `--viewer-accent` etc., defaulting to the Documents tokens) so the collateral's `--cm-*` palette maps in one place. Enqueue it in `devportfolio_enqueue_page_styles()` (`functions.php` ~69, archive of `document` and `collateral` only) with `filemtime` version, handle `devportfolio-viewer`. Shared CSS also takes the native viewer, zoom fab, mobile viewer, mobile footer, round buttons and the sheet chrome (all selectors listed for both prefixes, or generated from the prefix rewrite); only Documents-only layout (header, shelf-specific pieces, Documents Details sheet content) stays in `document-modal/style.css`. Add the collateral-only layout bits (arrows positioning inside `.collaterals-modal__body`, footer placement under `.collaterals-modal__main`, "Tap to Flip" hint) to `collateral-modal/style.css`.
  Fallback if D5 proves too invasive for the Documents CSS tests: keep the moved rules in `document-modal/style.css`, give the collateral modal the same selectors by adding the `.collaterals-modal__*` selectors to those rule lists, and note it in the as-built. Never duplicate a rule body.
- **D6 Payload (full parity).** Generalise `devportfolio_document_viewer_data( $post_id )` in `functions.php` instead of writing a second function: take the post type from the post (`document` or `collateral`) and look up the file meta key (`cptdp_document_file` / `cptdp_collateral_file`) and the stored-pages function (`cptdp_get_document_pages` / `cptdp_get_collateral_pages`) from one small map `devportfolio_page_source_map()`. Everything else stays: `devportfolio_document_file_kind()` (also generalised with the file meta key; it must return `image` for `image/jpeg|png|webp|gif` attachments and `other` for SVG), the Office branch (converted pages plus `nativeUrl` from `cptdp_document_native_{pptx,xlsx,docx}_url`), the PDF branch (`viewerUrl` via `cptdp_document_pdf_url()`, `pdfBytes` from the right file meta). The plugin functions `cptdp_native_office_url()`, `cptdp_document_pdf_url()` and `CPTDP_Page_Store::source_attachment_id()` already resolve the file through `file_meta_key()`, so they work for collaterals; **write a plugin test first that proves it** (`cptdp_document_native_pptx_url( $collateral_id )` returns the URL with `native_pptx` on and a `.pptx` source; '' when off or over the size filter) and only if it fails make the minimal fix in the plugin.
  Add to `devportfolio_collateral_modal_payload()`: `kind`, `images`, `imageWidth`, `cropBottom`, `ratio`, `viewerUrl`, `nativeUrl`, `pdfBytes`, `pages` (`count( images )`, else `cptdp_total_pages`), `fileType` and `format` (use `devportfolio_document_format_label( $filetype, $pages, $kind )` so Office kinds read `PPTX · 12 slides`; for a collateral with no `cptdp_filetype` derive it from the attachment extension), plus the Pages-sheet extras `pageNames` and `thumbs` from `devportfolio_document_pages_extras()` (generalised the same way, omitted when empty). Keep `thumb`, `pill`, `stats`, `categories`, `downloadMode/Url`, `tools`, `category`. The payload JSON is read by view.js only; keep `JSON_HEX_TAG | JSON_HEX_AMP`.
  Collateral `render.php` root attributes: `data-pdfjs`, `data-limits`, `data-units` (`devportfolio_document_unit_strings()`), `data-mobile-strings` (`devportfolio_document_mobile_strings()` generalised: the words are not document-specific; add the collateral's own strings for Details such as `copyLink`), `data-selectable-text="1"` when `devportfolio_document_selectable_text()`, and the existing `data-strings` extended with `unavailable`, `loading`, `hint`, `hintSheet`, `hintSlide`, `hintDoc`, `tapToFlip`, `previous`, `next`, `pagesLabel`, `reading`, `back`, `forward`. Shared PHP render helpers in `functions.php` (used by both render files, Documents refactored onto them): `devportfolio_viewer_arrow( $prefix, $dir, $label )`, `devportfolio_viewer_status( $prefix, $strings )` (with all `data-hint*` attributes), `devportfolio_viewer_footer( $prefix )`, `devportfolio_viewer_mobile_footer( $prefix )`, `devportfolio_viewer_mobile_stage( $prefix )`. Markup output for Documents must stay byte-compatible for the attributes its tests assert.

- **D7 Tap to Flip.** Rendered below the stage inside `.collaterals-modal__body`; shown only when ALL of: `(pointer: coarse)`, current view is a **2-page spread** (`state.step === 2`), pages not zoomed, and no flip happened yet in this opening. Hidden on first flip/swipe/tap and on `swapTo`. Behaviour: in `createViewer`, add `onTap( side )` for a single tap (no movement, `< 10px`, same threshold as `bindTouch` today) that is NOT part of a double-tap: delay the tap action by the double-tap window from `touch-zoom.js` (read its constant; do not invent a new one) and cancel if `touchZoom.tap()` registers a double-tap; tap on the right half = next view, left half = previous (edge dead strip per `gestures.js` `DEAD_STRIP`). Zoomed state: taps never flip (consistent with swipe being off while zoomed). The controller owns the hint's visibility (`hintVisible` in state, set by `setGeometry`, cleared by any `turn`). Text via `data-strings.tapToFlip` ("Tap to Flip"). Aria: hint is `aria-hidden` (the arrows/keys cover assistive tech); `prefers-reduced-motion` unaffected.
- **D8 Events.** `collaterals:modal-change` detail becomes `{ id, page, step, pages }` from the controller; `collaterals:mobile-change`, `collaterals:mobile-pages` and `collaterals:mobile-details` mirror the Documents `documents:mobile-*` events with the same detail shapes (`createMobileModal` takes the event prefix). `collateral-modal/view.js` `emit()` is replaced by the controller on desktop and by the mobile controller on mobile.
- **D9 Lifecycle in `collateral-modal/view.js`.**
  - Build the viewer once after querying `els` (stage, status, arrows, footer parts), `createViewer({ stage, status, prefix: 'collaterals-modal', pdfjs, limits: mergeLimits( data-limits ), words, onGeometry: controller.setGeometry, onFlip: controller.syncFromViewer, onSwipe: controller.turn, onTap })`.
  - `show()` (desktop target only): `controller.open( id, pagesFromPayload, stepFromPayload )` BEFORE `showModal()` so the footer/arrows are right on first paint, then after `showModal()` call `viewer.show( item, 1 )`. Same in `swapTo()` and in `swapDialogs()` when the new dialog is desktop (keep the current page: `viewer.show( item, controller.state.page )`).
  - On close of the desktop dialog or switching to the mobile dialog: `viewer.destroy()`, hide footer/arrows/hint.
  - Initial step as Documents `openDesktop`: `stepFor( pages, ratio > 1 ? 'landscape' : 'portrait' )` using payload `images.length || pages` and `ratio`.
  - The mobile dialog mounts the shared `createMobileModal()` (D11): `show( item, page )`, `swapTo`, `swapDialogs()` (page and collateral kept across the 700px swap, desktop viewer destroyed and mobile one built, and back), Pages and Details sheets (D12, D13).
  - Do not break `finishClose`, history, `enforceSingleDialog`, `copy-link`, `next.js`.
- **D10 Words.** The shared controller and viewer take `words = { one, prev, next, nav, ... }` like `wording( 'page', units )` in `mode.js`. `mode.js` moves to `shared/` with the viewer (D3), so `positionText`, `pieceLabel` and `wording` are shared as they are; the collateral builds `words` with `wording( unitOf( item ), units )` from `data-units`, exactly like Documents (page, slide or sheet).
- **D11 Mobile viewer shared.** Move to `T/blocks/shared/`: `mobile-viewer.js` (`createMobileViewer`), `mobile.js` (`createMobileModal`, parameterised by `prefix` (`documents-mobile` / `collaterals-mobile`), event prefix, strings, and a `content` adapter object `{ headerTitle( item ), detailsSheet: () => import(...), downloadMarkup }`), `sheet-controller.js`, `sheet-back.js`, `bottom-sheet.js`, `pages-sheet.js`. Class names `documents-mobile__*` become `${ prefix }__*` everywhere (stage, footer, row, pages, position, details, round buttons, hints, zoom pills, `--scroll`, `--doc`, grid bar custom property `--documents-grid-bar` -> `--grid-bar`). The mobile dialog markup comes from the shared PHP helpers (D6). Documents `view.js` / `mobile.js` imports updated; behaviour byte-identical (all `test-document-modal-mobile-*.php` and the node tests stay green). The `history` rules from AGENTS.md (router `popstate` listeners registered as scripts run; sheet entry popped on dismiss) are preserved by moving, not rewriting, the code.
- **D12 Pages sheet for collaterals.** `pages-sheet.js` already takes `pageNames` ranges, the `thumbs` descriptor and the unit words from the payload; nothing is collateral-specific. It is loaded with `import()` through the shared `sheet-controller.js` registered by `mobile.js`. Persisted view and scroll memory is per collateral id (`createSheetMemory()` keyed by id; the id space is separate per page so no collision).
- **D13 Details sheet for collaterals.** New `T/blocks/collateral-modal/details-sheet.js` built on the shared `createBottomSheet()` and registered by name in the shared `sheet-controller.js` (`closeOnRotate: false`, like Documents). Pure `collateralDetailsModel( item, strings )` (unit-tested) decides content and which sections show: category pill (filled, `item.category`) and paper size + pages pill (outline, `item.pill`), title, description, `Made with` (`item.tools`, one square pill per tool) and `Results` (`item.stats`, label uppercase at the start, value bold at the end), each only when it has data, then the full-width filled dark Download (`applyDownload` / `guardDisabledDownload` from `shared/download.js`) and Copy link (`copyText`, `linkFor` from `copy-link.js`). Held sideways it stays open on rotation with the two-column layout (CSS only, same markup), as the Documents Details sheet. The mobile footer `Details` button and the round info button emit `collaterals:mobile-details`. The `Documents`-specific `details-sheet.js` keeps its own model; both share the shell, the controller and the CSS for the sheet chrome (move the chrome CSS to `viewer.css`).
- **D14 Interim mobile Close.** The existing interim `.collaterals-mobile__header` (title and Close) and empty footer are replaced by the shared mobile markup (header with Close, title, Download; body with stage, status, progress bar; footer row with Pages, position, Details). Keep `.collaterals-mobile__close` and `.collaterals-mobile__title` class names so `view.js` focus handling and the existing DPC-021/022 tests keep working.

## 6. Work packages (each leaves all suites green)

WP1 - Move the pure generic modules (D1) with their tests and the PHP source-reading tests. Gate: all suites.
WP2 - Prefix arguments (D2) for `dom.js`, `status.js`, `buildPageEls`; defaults unchanged. Gate: Documents tests unchanged.
WP3 - Move the viewer, `mode.js`, `native-*.js` and the vendored bundles to `shared/` and parameterise by prefix (D3). Gate: Documents desktop browser smoke test (PDF, landscape pptx, native pptx/xlsx/docx, selectable text) identical to before.
WP4 - Extract `shared/pager-controller.js` (D4) with the pure `footerModel()`; rewire Documents `view.js`.
WP5 - Move the mobile viewer, mobile modal, sheets and sheet plumbing to `shared/` and parameterise (D11, D12). Gate: all `test-document-modal-mobile-*.php` and the node tests.
WP6 - PHP: generalise viewer data and payload helpers, shared render helpers, Documents `render.php` onto them (D6). Plugin test for the native URL functions on collaterals.
WP7 - CSS: `assets/css/viewer.css` (book, arrows, footer, status, mobile viewer, sheets chrome), enqueue, Documents CSS trimmed, collateral layout CSS (D5, D14).
WP8 - Collateral desktop integration (DPC-012, DPC-013): `render.php`, `view.js` (D7, D8, D9), footer.
WP9 - Collateral mobile integration, in this order (each leaves the suites green):
  - 9a Header (DPC-015): shared header markup, title truncation CSS, Download modes, floating Close/Download held sideways.
  - 9b Viewer and gestures (DPC-019): mount the shared mobile viewer in `.collaterals-mobile__stage`, hint and pills strings, rotation fade, `swapDialogs()` keeps the collateral and page across the 700px swap (and back).
  - 9c Footer (DPC-016): Pages, indicator, Details, progress bar, one-page round Details button, sideways round buttons.
  - 9d Pages sheet (DPC-017): register `pages` in the shared sheet controller, thumbs and names from the payload.
  - 9e Details sheet (DPC-018): `collateral-modal/details-sheet.js`, model, CSS, rotation behaviour.
WP10 - Tests, browser check, docs.

## 7. Tests

PHP theme (`T/tests/`):
- New `test-collateral-viewer.php` (`Devportfolio_Test_Collateral_Viewer`, `@group theme`), or extend `test-collateral-modal.php`: payload has the viewer keys for a collateral with stored pages (use the plugin test helpers `make_collateral()`, `make_image_attachment()` from `CPTDP_Pages_Test_Case` pattern; if unavailable in the theme suite, write a ready page set to the uploads dir the way `test-document-viewer.php` does), for one with none (`images: []`, `pages` from `cptdp_total_pages`), single-page, landscape (`ratio > 1`), image-kind source (`viewerUrl: ''`); `pages` count; root has `data-pdfjs`, `data-limits`, strings; markup has two arrows with `aria-label`s, a status element with `role="status"`, footer with `.collaterals-modal__pager`, `__indicator`, `__bar[role=progressbar]`, hint element; footer still `hidden` in markup.
- Documents render helpers: markup output of `devportfolio_viewer_arrow/footer/status` equals the previous markup (compare strings or key attributes) so `test-document-viewer.php`/`test-document-modal.php` stay green.
- CSS/JS contracts: `collaterals-modal__book`, `__arrow`, `__pager`, `__bar` selectors present in `viewer.css`; `viewer.css` enqueued only on document/collateral archives; no `.collaterals-modal__*` rule body duplicates a Documents rule body (assert the selector list approach); `collateral-modal/view.js` imports `../shared/viewer.js` and `../shared/pager-controller.js` and contains no `new PageFlip`; `emit` detail contains `step` and `pages`.
- Update path-reading assertions in the six tests listed in section 4.

Node (`node --test tests/*.test.mjs`): moved tests pass from `blocks/shared`; new `pager-controller.test.mjs` for `footerModel` (1 page -> hideAll; 2 pages portrait step 2; 24 pages `valuetext` `Pages 3–4 of 24`; first/last arrows off; <=9 pages all numbers; >9 window with gaps) and for the tap-to-flip rule (a pure `tapSide( x, width )` / `shouldShowTapHint( { coarse, step, flipped, zoomed } )` helper in `shared/gestures.js` or `pager-controller.js`, tested).

Additional PHP tests for full parity:
- Payload per kind (PDF, `pptx`, `xlsx`, `docx`, `image`, no file): `kind`, `nativeUrl` only when the shared switch is on and the size is within the filter, no `viewerUrl` for Office or image, `format` wording (`PPTX · 12 slides`, `XLSX · 3 pp`), `pageNames`/`thumbs` present only when stored.
- Root attributes: `data-selectable-text` follows `native_pdf`; `data-units`, `data-mobile-strings`, `data-limits` present.
- Mobile markup: shared helpers produce `collaterals-mobile__stage`, `__footer`, `__row`, `__pages`, `__position`, `__details`, `__bar`, status element; `.collaterals-mobile__close` and `__title` still exist.
- Plugin: native URL helpers and `cptdp_document_pdf_url()` for a collateral (see D6).
- Moved-code guards: no `documents-modal` / `documents-mobile` literal remains inside `shared/*.js` except as the default prefix argument.
Story tests (add to `T/tests/test-collateral-modal-mobile.php`, class `Devportfolio_Test_Collateral_Modal_Mobile`, `@group theme`, mirroring `test-document-modal-mobile-viewer.php`, `-pages.php`, `-details.php`):
- DPC-015: header order (close, title, download), title truncation CSS, `applyDownload` modes wired, sideways rule hides the header and floats Close and Download.
- DPC-016: footer parts and `3–4 / 24` wording (node `mobileFooterParts`), one-page hides footer and bar and shows the round Details button, sideways rules, button events `collaterals:mobile-pages` / `collaterals:mobile-details`.
- DPC-017: payload `thumbs` descriptor and `pageNames` ranges, sheet memory per collateral id, Back closes only the sheet, listeners registered at script run (source-order check as in the Documents test).
- DPC-018: `collateralDetailsModel()` with and without tools, stats, description, category, pill; Copy link and Download present; sheet stays open on rotation (`closeOnRotate: false`).
- DPC-019: gestures constants shared (`EDGE_ZONE` 0.2, `DEAD_STRIP` 8, zoom 1-4), `spreadFits`, hint strings and the two landscape pills present in `data-mobile-strings`, rotation fade timings, no fold shading class on mobile spreads.
Additional Node tests: `shared/tests/` for `collateralDetailsModel`, `footerModel`, `tapSide`/`shouldShowTapHint`; the moved Documents tests keep passing from their new location; `pages-sheet.test.mjs`, `bottom-sheet.test.mjs`, `sheet-controller.test.mjs`, `mode.test.mjs`, `details-sheet.test.mjs` move or keep importing from `shared/` as appropriate.

Suites to run before the PR:
```
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
for b in document-modal collateral-modal collateral-grid shared; do
  ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/$b 'node --test tests/*.test.mjs'; done
```
Expected totals in `AGENTS.md` (plugin 829/12 skipped, theme 424, modal JS 153, collateral modal 18, grid 17) change only by the tests added or moved; update the numbers in AGENTS.md.

## 8. Browser check (headless Chrome via puppeteer-core, same as DPD-026; no runner in repo)
Seed: `ddev wp cptdp seed collateral-categories`, `software-tools`, `collaterals`, then make page sets: `ddev wp cptdp pages generate --all --post_type=collateral --sync` after attaching a multi-page PDF, a landscape PDF, a single image and a collateral with no file (via wp-admin or `wp media import` + meta update; do not commit uploads).
At `https://devportfolio.ddev.site/collaterals/`:
1. 24-page portrait at 1440x900: spread, footer pager `1 2 3 … 24`, bar `Pages 1–2 of 24`; click `5` -> pages 5-6 (numbers `.is-on`), Next/Back, ArrowLeft/Right, Back disabled on first, Next on last; progress `aria-valuetext` updates.
2. Same at a tall narrow window (e.g. 800x1200): one larger page (spread not worth it).
3. Landscape PDF: one wide page. Single page: no arrows, no footer, no pager.
4. No pages, no PDF: status "can't be previewed", Download link works (href), no arrows/footer/hint.
5. Touch emulation (`pointer: coarse`, 1024x768 tablet): swipe turns, pinch and double-tap zoom, single tap on right/left half flips, "Tap to Flip" shows below the spread and disappears after the first flip, absent for 1-page or landscape.
6. Next-collateral swap keeps the modal, resets to page 1, footer redraws. Resize across 700px swaps dialogs without errors; Esc/close destroys the viewer (no console errors). Back/Forward behaviour unchanged (deeplink).
7. Native and kinds (switches on in Settings > Documents > Native viewer): `.pptx` canvas with selectable text and slide pager, `.xlsx` grid with sheet tabs (no footer, arrows hidden), `.docx` scroll viewer with zoom buttons; with the switches off the converted pages show; kill the network for the wasm to see the fallback to stored pages and the per-kind hint. PDF with `native_pdf` on: text selectable, pages turn by arrows/pager/keys only. Image collateral: one page, no arrows or footer.
8. Mobile (390x844 touch): open a collateral, spread/single page by space, swipe, edge taps, pinch and double-tap zoom, hint disappears on first action, rotate to landscape (floating Close/Download, round Pages and Details buttons, progress bar), Pages sheet (gallery then list, lazy thumbnails, remembered view and scroll, drag to dismiss, Back closes only the sheet, Esc first closes the sheet), Details sheet (content, Download, Copy link, Next collaterals swap, stays open on rotation). 11 Back/Forward flows with zero page reloads (router rule). Also check: 24-page footer `2 / 24` then `3–4 / 24` in a spread, one-page collateral (round Details only), landscape collateral hint pills, long title truncated at 320px, Download/Close floating when sideways, Details sections hidden when empty (collateral with no stats and no tools).
9. Regression: Documents modal desktop PDF, landscape pptx, native xlsx still work.

## 9. Docs
Update `AGENTS.md`: the `collateral-modal` bullet (stage and footer no longer empty; viewer, controller, events payload), the `blocks/shared/` bullet (new modules), the document-modal bullets that name moved files, the tests section totals and the new test file. Add the plan's As-built notes.

## 10. Risks
- Moving files breaks source-reading PHP tests and imports: do WP1 alone and run all suites before continuing.
- Extracting the viewer (WP3) is the riskiest step; the Documents wrapper must keep the same public API. Compare behaviour in the browser before and after.
- The `--documents-crop` rename must move together with CSS and its test.
- The tap-to-flip must not fight double-tap zoom or swipe; use the existing `touch-zoom.js` double-tap window and the swipe threshold.
- `.collaterals-modal__body` is `display: flex`; arrows must be positioned absolutely like Documents (`__arrow--prev/--next`) without changing the sidebar layout.
- Payload size: images array per collateral is in the JSON of all collaterals; acceptable (Documents does the same), no names/thumbs added.
- Parity multiplies the surface: the moved mobile viewer (1033 lines), sheets and native viewers must stay byte-identical for Documents. Move first (WP1-WP5), integrate later (WP8-WP9); never edit logic while moving.
- The vendored OOXML bundle (about 6 MB) moves to `shared/vendor/`: check every path, the `?v=` version test, `.gitignore` allowlist and the host's `.wasm`/`.mjs` MIME types; do not duplicate the bundle.
- Class-prefix rename in CSS is large; do it with a scripted selector rewrite and diff-check the Documents computed styles in the browser.
- Payload for all collaterals now carries `pageNames` and `thumbs`; they are omitted when empty (same rule as Documents) to keep the JSON small.

## 11. Conventional commits
Branch `feat/DPC-012-013-Collateral-Viewer-Footer` from `main`. Subjects <= 72 chars. Each commit ends with `Refs: DPC-012[, DPC-013]` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths only. Every commit leaves all suites green.
1. `docs(plans): add the DPC-012 and DPC-013 plan` - this file.
2. `refactor(theme): move generic viewer modules to shared` - pure modules (`layout`, `pager`, `gestures`, `touch-zoom`, `capability`, `page-source`, `dom`, `status`), their tests, imports, PHP path tests.
3. `refactor(theme): take the class prefix as an argument` - `shared/{dom,status,page-source}.js`.
4. `refactor(theme): move the viewer and native viewers to shared` - `shared/viewer.js`, `mode.js`, `native-*.js`, `vendor/` moves, version and path tests.
5. `refactor(theme): extract the pager controller into shared` - `shared/pager-controller.js`, Documents `view.js`, node test.
6. `refactor(theme): move the mobile viewer and sheets to shared` - `mobile*.js`, sheets, controller, tests.
7. `refactor(theme): share the viewer styles` - `assets/css/viewer.css`, Documents CSS, enqueue.
8. `refactor(theme): share the viewer markup helpers` - PHP helpers, Documents `render.php`.
9. `refactor(theme): generalise the viewer payload by post type` - `functions.php` map and helpers, tests for Documents unchanged.
10. `test(custom-post-types-dp): cover native urls for collaterals` - plugin test (and a `fix(custom-post-types-dp)` commit before it only if a helper turns out to be document-only).
11. `feat(theme): add viewer data to the collateral payload` - payload, root attributes, strings. Refs DPC-012.
12. `test(theme): cover the collateral viewer payload`.
13. `feat(theme): show collateral pages in the modal body` - markup, `view.js`, CSS (desktop, all kinds, native, selectable text, Tap to Flip). Refs DPC-012.
14. `feat(theme): add the collateral modal footer pager` - footer markup, CSS, wiring. Refs DPC-013.
15. `feat(theme): add the collateral mobile header` - markup, title truncation, Download modes, sideways floating buttons. Refs DPC-015.
15b. `feat(theme): add the collateral mobile viewer` - shared mobile viewer, gestures, hints, rotation, dialog swap. Refs DPC-019.
15c. `feat(theme): add the collateral mobile footer` - Pages, indicator, Details, progress bar, round buttons. Refs DPC-016.
15d. `feat(theme): add the collateral pages sheet` - thumbnails, names, remembered view. Refs DPC-017.
16. `feat(theme): add the collateral details sheet` - `collateral-modal/details-sheet.js`, model, CSS. Refs DPC-018.
17. `test(theme): cover the collateral viewer, footer and mobile contracts` - PHP and Node tests.
18. `docs(agents): record the collateral viewer and footer` - `AGENTS.md`.
19. `docs(plans): record the DPC-012 and DPC-013 as-built` - As-built section.

PR title: `feat(theme): collateral modal viewer and footer` (<= 72). Body from `.github/pull_request_template.md` (tick the change type; Description, Implementation, How to Test), ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit.

## 12. Open questions (defaults used; change before implementing if you disagree)
- Q1 Shared extraction (D1-D5) rather than a collateral-only copy. Default: shared, per DPC-024.
- Q2 "Tap to Flip": a tap on the left/right half flips; shown only for touch + 2-page view; hidden after first flip. Default: as D7.
- Q3 Footer sits under the body only; the sidebar keeps the full height (DPC-007 Q5). Default: yes.
- Q4 Mobile dialog, Pages sheet and Details sheet are in scope (full Documents parity, per the request). Default: yes; the collateral Details sheet shows the sidebar content (D13).
- Q5 Unavailable text: "This collateral can't be previewed." / hint "Use Download to open it." Default: as written.
- Q6 The shared native-viewer settings (PDF, PowerPoint, Excel, Word) apply to both pages, since the setting lives under Settings > Documents. Default: yes, no new switch.


## 13. As-built (this branch's working tree; not committed)

Status per work package:
- WP1 to WP6: done (earlier state, see the commit list).
- WP7 (CSS): partial. D5 fallback: the collateral's mobile dialog uses the Documents mobile rules through selector lists. `blocks/document-modal/style.css` now lists every `.documents-mobile` selector with its `.collaterals-mobile` counterpart in front of it (scripted, no rule body copied; the token root covers `.collaterals-mobile`), and the collateral's interim mobile header, body and footer rules were removed. The collateral Details sheet styles (pills, captions, Results, Copy link) are in `collateral-modal/style.css`. NOT done: `assets/css/viewer.css` (the header and sheet chrome were not split out of `document-modal/style.css`).
- WP8 (collateral desktop, DPC-012/013): done (earlier state).
- WP9 (collateral mobile, DPC-015..019): done.
  - 9a header: `devportfolio_viewer_mobile_header()` builds the row for both dialogs (Close left, title with ellipsis, Download right). Held sideways the header is `display: contents`, Close and Download float at 12px.
  - 9b viewer: `createMobileModal()` (shared `mobile.js`) with `prefix: 'collaterals-mobile'`, `viewPrefix: 'collaterals-modal'`, `eventPrefix: 'collaterals'`. `data-mobile-strings` is `devportfolio_collateral_mobile_strings()` (the Documents words plus the collateral's). `swapDialogs()` keeps the collateral and the page across the 700px swap (the page is read before the view transition; a second media change while a swap is queued is ignored).
  - 9c footer: `devportfolio_viewer_mobile_footer()`, `devportfolio_viewer_mobile_body()` (also used by Documents). One page hides the footer and bar and shows the round Details button.
  - 9d Pages sheet: `pages` registered by the shared mobile modal; `pages-sheet.js` now passes its `prefix` to the shell (it did not before, so a collateral sheet would have used the Documents classes).
  - 9e Details sheet: `collateral-modal/details-sheet.js` with the pure `collateralDetailsModel( item, strings )` and `createDetailsSheet()` on `createBottomSheet( { prefix } )`; sections are left out when empty; Download and Copy link; `closeOnRotate` is false.
  - Fixed while checking in the browser: the one-page collateral is an `image` kind, which `canShowOnMobile()` did not know; it now is (`shared/mode.js`, one test case added).
- WP10 (tests and docs): done with the notes below.
  - Theme PHPUnit: 472 tests, all pass (new `tests/test-collateral-modal-mobile.php`, 22 tests).
  - Plugin PHPUnit: 834 tests, 12 skipped (baseline 829 + the 5 new in `tests/collaterals/test-collateral-native-urls.php`). No plugin code change was needed: the native URL and PDF URL helpers resolve a collateral's file already.
  - Node: document-modal 16, collateral-modal 24 (new `details-sheet.test.mjs`, 6 tests), collateral-grid 17, shared 154 (`mode.test.mjs` gained `image`).
  - AGENTS.md updated (collateral bullet, test totals, new test files).

Deviations chosen:
- Four Documents structural regexes (`test-document-modal-mobile-viewer.php` x3, `test-document-modal-mobile.php` x1) were relaxed to allow the collateral selector in front of the Documents one, and one string check now looks for `_x( 'Pages'` (the footer moved into `devportfolio_viewer_mobile_footer()`). The Documents assertions still check the same rules. No Documents behaviour changed.
- Icons keep the class `documents-modal__icon` in both modals (one rule, not duplicated).
- `details-sheet.js` (Documents content) and the Documents header stay in `document-modal/`.
- The image kind is returned for collaterals only (a Documents image attachment stays `other`).

Browser checks (headless Chrome, puppeteer-core, `https://devportfolio.ddev.site`, scripts in the session scratchpad, not in the repo):
- Mobile 390x844 touch, 5-page PDF collateral: the dialog opens; the header order and the title; the footer `1 / 5` and the progress `Page 1 of 5`; the Pages sheet shows 5 gallery tiles; List view; a row tap goes to page 2; the Details sheet shows the title, pills, Made with, Results, Download (href) and Copy link; Esc closes the sheet and keeps the modal; Back closes a sheet and keeps the modal; landscape 844x390 hides the header and floats Close at (12, 12) with the round buttons; Close closes and the URL is cleared; no page errors.
- 1-page image collateral at 390: the round `Collateral details` button is visible, the footer hidden, the flyer drawn.
- Desktop 1440x900: the footer pager and ArrowRight turn the page (`Page 2 of 5`); resize to 390 swaps to the mobile dialog on page 2 (`2 / 5`); resize back to 1440 swaps to the desktop dialog; Esc closes; no errors.
- Documents regression at 390 (touch, mobile): the dialog, `1 / 6`, the Pages sheet with 6 tiles, Escape, close; no errors.

Not verified in a browser: a two-page spread and Tap to Flip on a touch tablet; the Next-collateral swap on desktop after this change (unchanged code path); native PowerPoint, Excel and Word viewers on collaterals (switches on); the rotation fade timing; the Details sheet held sideways (two columns); the round Pages and Details buttons' sizes beyond the visibility check; the 24-page footer `2 / 24`.

Note for the browser checks: Puppeteer's mobile emulation (`isMobile: true`) itself reloads the page when it is switched on, with or without a modal (checked on `/documents/`). The checks therefore keep `isMobile` off for the width swap and set it once at the start for the mobile runs.
