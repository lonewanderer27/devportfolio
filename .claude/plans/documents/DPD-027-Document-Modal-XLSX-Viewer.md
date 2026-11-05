# Plan: Document modal body, XLSX viewer (converted pages and the native Excel viewer)

Ticket DPD-027 (Modal / Body / XLSX Viewer), **phase 2 of 2**. **One shared branch for both phases: `feat/DPB-027-DPD-027-Document-Modal-XLSX-Viewer`.** **Depends on DPB-027** (`../backend/DPB-027-Document-XLSX-Conversion.md`, plugin 0.21.0), whose commits come first: it converts `.xlsx`, `.xls` and `.ods` files to page images, adds the **Excel (.xlsx)** checkbox (`native_xlsx`) to the Native viewer section and `cptdp_document_native_xlsx_url()`. Also builds on DPD-026 (`DPD-026-Document-Modal-PPTX-Viewer.md`: `mode.js`, `showPptx()`, vendored `ooxml`, fallback rule, cache-safe URLs) and DPD-013 (`viewer.js`, lifecycle).

Theme: `devportfolio` (no version header to bump).

> **Status: implemented, uncommitted (see "As built" at the end).** The library options named below were confirmed against its type definitions and in headless Chrome.

## Goal
1. **Converted spreadsheets** (the default once DPB-027 has made images) open in the existing open-book viewer as **pages** (one per sheet with the recommended export). Wording stays "page"; the cover and pill say `XLSX · 3 pp`. (A converted sheet is a print page, not a slide: no new unit.)
2. **The native viewer.** When Settings → Documents → Native viewer → Excel is ticked, a `.xlsx` is drawn **from the original file in the visitor's browser** by the bundled viewer: a scrollable, zoomable grid on a canvas with the workbook's **sheet tabs**, cell selection and **copy**. The converted pages are its **fallback**. No LibreOffice needed, nothing sent to anyone; the cost is performance and a bigger first download.
3. Everything else unchanged: PDFs, presentations, and "can't be previewed" (with a spreadsheet hint) when there is nothing to show; Download keeps working.

## Context
- `devportfolio_document_file_kind()` knows `pdf|pptx|ppt|odp|other`; `devportfolio_document_unit()` returns `slide` for presentations and `page` otherwise; `devportfolio_document_viewer_data()` returns early for slide kinds with stored pages and `nativeUrl`; `viewerMode( doc )` is `native | images | pdf | unavailable`; `showPptx()` is the only native drawer; `PPTX_LIB`/`PPTX_VERSION` in `viewer.js`.
- The vendored library is a PPTX subset (5.9 MB, 36 files). XLSX needs `xlsx.mjs`, `xlsx-source-worker.mjs`, `xlsx_parser_bg.wasm` (1.93 MB) and the files `xlsx.mjs` imports, about **2.3 MB of JS in 33 files, most already vendored** (`plot-area-frame`, `bounded-raw-part-cache`, `renderer-*`, `emf`, `three-d`, `pixel-budget` …) plus new ones (`xlsx-*.js`, `worksheet-pull-client`, `workbook-model-source`, `worker-source-C29EQNUj`, `grid-geometry`, `delimited-text-worker-host`, `render-worker-host-CZHIKTtZ`, `renderer-B4KIpz-2`, `drawingml-shape`, `host-layout`, `comment-ui-runtime-BtP_TpvT`). Exact delta measured in step 3.
- The library offers **two viewers**: `XlsxViewer` (composite: grid, scrollbars, sheet tabs, zoom slider, selection, copy) and `XlsxSheetViewer` (one canvas, `goToSheet()`, `nextSheet()`, `sheetCount`, `sheetNames`, `scrollToCell()`, `setScale()`).
- The modal only opens above 700px.

## Decision tree
```
open document
  payload.nativeUrl?       ── yes ──> native viewer draws the .xlsx ── fails ──┐
        │ no                                                                   │ (no WebAssembly or worker, load error, resource limit, timeout)
  payload.images non-empty? ── yes ──> StPageFlip, <img> pages  <──────────────┘ images exist, else "can't be previewed"
        │ no
  payload.viewerUrl?       ── yes ──> gate ──> pdf.js fallback            (PDFs only)
        │ no
  "can't be previewed" (spreadsheet hint), Download works
```
`viewerMode()` is unchanged in shape; only `canDrawPresentations( env )` is generalised to `canDrawOffice( env )` (WebAssembly, `Worker`, 2D canvas; the old name stays as an alias for tests).

## Key decisions
1. **Which viewer class. Decide (recommended: `XlsxViewer`, the composite).** A sheet has to scroll and zoom, and a tab strip is the natural sheet control. `XlsxSheetViewer` would let the modal's own pager turn sheets (as slides), but then scrolling, zoom and a sheet-name label would all be rebuilt by us. With the composite the modal's **footer pager, page arrows and progress line are hidden** for a native workbook (the same CSS the "fewer than 2 pages" case already uses, driven by a `data-viewer="native-grid"` on the body) and Left/Right keys are left to the grid. Cost: the library's own UI (tabs, zoom slider with `showZoomSlider`) must be restyled to the greyscale theme and checked inside the dialog; if it cannot be, fall back to `XlsxSheetViewer` + our pager with the unit "sheet".
2. **Payload (theme).** `devportfolio_document_file_kind()` returns `xlsx|xls|ods` (mime map plus the same extension fallback for generic types); `devportfolio_document_unit()` stays `page` for them; a new `devportfolio_document_is_spreadsheet( $kind )`. `devportfolio_document_viewer_data()` and the modal payload: `kind` for spreadsheets, `nativeUrl` from `cptdp_document_native_xlsx_url()` through `function_exists`, the stored pages from the plugin like a presentation (early return, no `viewerUrl`, no `pdfBytes`). `devportfolio_document_format_label()` for a spreadsheet: `XLSX · 3 pp` (`ODS`, `XLS` likewise), reusing the existing page wording.
3. **`showXlsx()` in `viewer.js`**, modelled on `showPptx()`:
   - `import( XLSX_LIB )` only when a native spreadsheet opens (never static; StPageFlip not loaded unless it must fall back). One shared constant `OOXML_VERSION` replaces `PPTX_VERSION` (entry, worker and wasm requested with `?v=`); the README, a test and `PPTX_VERSION`'s alias keep working.
   - Options (**all to be verified against the 0.89.7 `XlsxSheetViewerOptions` type in step 3**): fetch only the workbook file; **hyperlinks off** (no `openExternalHyperlink`), no external fonts, no comments UI; the viewer must **not show hidden sheets** (`HiddenSheetMode` = never) so the visitor is not shown what Excel hid, but see Risk 3: the file still contains them; `showZoomSlider: true`; resource limits (`OoxmlResourceLimits`) set to the modal's `limits` (max cells, decoded image budget) so a hostile or huge workbook ends in the fallback, not in a frozen tab; timeout `limits.nativeTimeoutMs` (30 s).
   - **Fit:** the viewer fills the stage (`autoResize`), the stage is `overflow: hidden`, and the modal's keyboard handling yields to the grid while it has focus (Esc still closes).
   - **Fallback:** `showXlsx()` returns false after `destroy()` and emptying the stage when it cannot draw the file; `show()` continues with the stored pages or "can't be previewed". A render error after the workbook is showing ends the viewer the same way. A `OoxmlResourceLimitError` is treated as "cannot draw", not as an error.
   - **Lifecycle:** `reset()` calls `destroy()`; every await checks the session token; notes are `textContent` only.
   - **Text selection:** the grid offers cell selection and `copy()` (`XlsxCopyResult`); there is no DOM text layer, so the Native viewer text must say "copy cells", not "select text". Copy is bounded by the library (`MAX_SELECTION_CONTEXT_CELLS`).
4. **Vendoring.** Add the XLSX graph to `vendor/ooxml/` (same version 0.89.7, same README, updated "what is included"): `xlsx.mjs`, `xlsx-source-worker.mjs`, `xlsx_parser_bg.wasm`, the new shared chunks, and whichever `assets/render-worker-*.js` the workbook path loads (three exist; **find out which in a browser**, vendor only those). Left out as before: DOCX engine, MathJax, 3D beyond what the import graph forces (`three-d` is in the graph, 27 KB), `.xls` add-on. The vendor README gains a table of what each format loads, and the "Updating" script copies by import graph for both entries. Test: every file either entry imports exists; the stable-name files (now `pptx.mjs`, `pptx-source-worker.mjs`, `pptx_parser_bg.wasm`, `xlsx.mjs`, `xlsx-source-worker.mjs`, `xlsx_parser_bg.wasm`) are the **only** ones without a content hash and are requested with `?v=`.
5. **Host requirements** as DPD-026 decision 6; the extra wasm (1.93 MB raw, expected about 0.7 MB gzip, to be measured) makes **compressing `application/wasm`** more important. Nothing downloads for visitors who open other documents.
6. **Wording and CSS.** New strings in `render.php`'s `data-` set: the spreadsheet "can't be previewed" hint (`data-hint-sheet`): "Spreadsheets can’t always be previewed here. Download the file to open it in Excel." The grid's accessible name: `aria-label` on the stage container "Spreadsheet preview". Styles for the library's tab strip and zoom control are scoped under `.documents-modal__stage--grid`, tokens only, no new colours.
7. **PDFs and presentations** unchanged; `data-selectable-text` and `native_pptx` flows are untouched.
8. **Native viewer settings text** (plugin, DPB-027 decision 9) is the user-facing explanation; this ticket only has to match it (copy cells, no macros, `.xls`/`.ods` use the converted pages).

## Out of scope (and what comes next)
- **DOCX** (next ticket): `kind` `docx`, a checkbox, `docx_parser_bg.wasm`; `viewerMode()`/`devportfolio_document_file_kind()` are the extension points, as this ticket extends them.
- Native `.xls` / `.ods`; formula bar and editing; find-in-sheet UI; printing; export to CSV; comments and threaded notes; pivot interaction; freezing the sheet header while paging.

## Risks / open points
1. **Depends on DPB-027.** Without it `nativeUrl` and images are empty for spreadsheets and the modal says "can't be previewed" as today (no regression).
2. **Performance is the price** (why the box is opt-in): the first open downloads the vendored XLSX graph and the wasm, parses the whole workbook in the visitor's browser and keeps it in memory; big workbooks on weak devices will feel it. The 15 MB file cap (plugin), the resource limits, the 30 s timeout and the page fallback bound the worst case. Measure a 1 MB, 5 MB and 15 MB workbook in headless Chrome and record requests, transfer, first paint and heap in "As built".
3. **Hidden content is downloaded.** In native mode the visitor receives the whole `.xlsx`: hidden sheets, hidden rows and columns, cell comments, defined names and document properties are all in the file even if the viewer does not draw them. Converted pages only show visible sheets. The Native viewer text and the editor warning (DPB-027 Risk 6) must say so; nothing in the theme can fix it.
4. **The library is young** (0.89): charts, conditional formats, pivot tables, sparklines, data validation and some number formats are approximated; no recalculation (cached values are shown). Re-test real workbooks on each update.
5. **Library UI inside a native `<dialog>`:** focus trapping, Esc handling and keyboard events from the grid may fight the modal's. Step 3 must prove Esc closes, Tab leaves the grid, arrow keys move cells without turning pages.
6. **Host settings** (decision 5) decide how heavy the first open really is.
7. **No browser test runner in the repo:** pure helpers are unit-tested; DOM wiring by source-structure tests and headless Chrome runs by hand, as DPD-026.

## Steps
One conventional commit each, scope `documents`, **tests never in a feature commit**, both suites green at every commit.
1. **Payload and labels** (feat, test): spreadsheet kinds in `devportfolio_document_file_kind()`, `is_spreadsheet`, `nativeUrl`, `XLSX · 3 pp`, spreadsheet hint string.
2. **Mode helpers** (feat, test): `canDrawOffice()` (alias kept), `OOXML_VERSION`, any new `viewerMode`/wording cases, `mode.test.mjs`.
3. **Spike, then vendoring** (build, test): run the XLSX viewer in headless Chrome against the dev site, confirm the option names and the hidden-sheet, hyperlink and limit behaviour, record which render worker loads; then vendor the graph and extend the vendoring tests. If a Risk-5 or Decision-1 problem appears here, switch to `XlsxSheetViewer` before step 4.
4. **The native viewer** (feat, test): `showXlsx()`, fallback, lifecycle, cache-safe URLs, grid CSS and footer/arrow hiding, strings in `render.php`.
5. **Docs** (docs): AGENTS.md (kinds, payload, `showXlsx()`, vendoring table, host settings, totals).
6. **Plan** (docs): this plan, with DPB-027's, last of the branch.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```
Manual (`/documents/`; fixtures: a small workbook, a 3-sheet workbook with a hidden sheet and a wide sheet, a 10 MB workbook, an `.xls`, an `.ods`, a corrupt `.xlsx`, a PDF, a `.pptx`):
1. **Converted workbook, box off:** the book opens at page 1, pill `XLSX · N pp`, normal page wording; PDFs and decks unchanged.
2. **Native viewer, box on:** the workbook opens as a grid with sheet tabs; scroll, zoom, tab switch and cell copy work; the hidden sheet has no tab; **Network tab: only the site's own host, `xlsx.mjs` and the wasm with `?v=0.89.7`, nothing before the modal opens, no request when clicking a hyperlink cell.** Footer, arrows and progress hidden; Esc closes; Tab leaves the grid.
3. **Fallback:** block the `.xlsx` wasm: the same workbook shows its converted pages and navigates; the corrupt `.xlsx` with no images says "can't be previewed" with the spreadsheet hint and hides footer and arrows; the 10 MB workbook above a lowered `nativeTimeoutMs` falls back cleanly.
4. **Lifecycle:** close and reopen, switch between a workbook, a deck and a PDF: no grid or canvas left over, no console errors, workers gone.
5. **`.xls`, `.ods`:** never get the native viewer (no `nativeUrl`), they show converted pages or "can't be previewed".
6. **Everything off** behaves as before DPD-027.

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `feat(documents): add spreadsheet kinds, native url and labels to the modal payload`
2. `test(documents): cover the spreadsheet payload and labels`
3. `feat(documents): generalise the native viewer gate and version helpers for office files`
4. `test(documents): cover the office viewer helpers`
5. `build(documents): vendor the ooxml xlsx viewer`
6. `test(documents): cover the vendored xlsx viewer and its cache-safe urls`
7. `feat(documents): draw xlsx documents with the native viewer in the modal`
8. `test(documents): cover the xlsx viewer structure and fallback`
9. `docs(agents): document the spreadsheet viewer`
10. `docs(plans): add DPD-027 plan` (with the DPB-027 plan, last of the branch)

One PR for the shared branch (see the DPB-027 plan).

## As built
Theme suite **219 tests** (the 2 known Services navigation failures), JS **66 passing** (63 before). `XlsxViewer` (the composite) works inside the `<dialog>`; `XlsxSheetViewer` was not needed.
- **Verified in headless Chrome** (puppeteer-core, scripts kept outside the repo) against `https://devportfolio.ddev.site/documents/` with `native_xlsx` on: the workbook opens as a grid with the tabs Summary / Monthly Budget / Headcount, zoom control and the formula results; the first open makes **23 requests** (the entry and the wasm with `?v=0.89.7`), only the site's own host, **nothing is requested before the modal is opened**, first grid in about 1.6 s on DDEV; footer pager and arrows are hidden; ArrowRight leaves the modal open; Esc closes it; closing and opening another document leaves no grid; with the `.wasm` blocked the same document shows its **3 converted pages** in the book.
- **Not as planned:** vendoring added only **13 files and the wasm** (the other 20 files of the XLSX import graph were already there for PPTX); the XLSX-specific `assets/render-worker-B3hF7Akg.js` (1.7 MB) is **not** vendored because the main-thread mode used here never requests it (revisit if a workbook needs it). `PPTX_VERSION` became an alias of `OOXML_VERSION`; `canDrawPresentations()` is an alias of `canDrawOffice()`. New limits `nativeMaxCells` (2,000,000) and `nativeMaxRows` (200,000) are passed as `xlsxWorksheetLimits`.
- **Not verified:** a large workbook (5 or 15 MB) on a weak device, charts, conditional formats and pivot tables, copying cells with the clipboard, the library's tab strip and zoom slider styling beyond its defaults (they look neutral and fit the modal), real hosts' `.wasm` compression (the second wasm adds 1.9 MB raw).

### Cleanup pass (after the first implementation)
Behaviour and totals are unchanged (theme 219 with the 2 known failures, JS 66).
- `functions.php`: the slide and spreadsheet branches of `devportfolio_document_viewer_data()` are one branch; `nativeUrl` comes from a kind to function map (`pptx` to `cptdp_document_native_pptx_url`, `xlsx` to `cptdp_document_native_xlsx_url`).
- `viewer.js`: `destroyNative()` replaces three copies of the destroy-and-ignore block; the "can't be previewed" hint is picked by `hintKey` (`hintSlide`, `hintSheet`, `hint`) instead of a nested ternary and a `sheetDoc` flag; `show()` dispatches with `( doc.kind === 'xlsx' ? showXlsx : showPptx )`. Tests assert `destroyNative( pptx )`, `destroyNative( grid )` and the `'hintSheet'` key.
- **Deferred (DOCX ticket):** merging `showPptx` and `showXlsx` into one loader (wait for the third copy to show the common shape), and handing the kind lists to `mode.js` from the plugin instead of the `PRESENTATIONS`/`SPREADSHEETS` constants.
