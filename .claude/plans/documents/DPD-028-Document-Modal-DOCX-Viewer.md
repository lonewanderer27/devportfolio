# Plan: Document modal body, DOCX viewer (converted pages and the native Word viewer)

Ticket DPD-028 (Modal / Body / DOCX Viewer; the ticket list labels it "Modal - Mobile", see decision 9), **phase 2 of 2**. **One shared branch for both phases: `feat/DPB-028-DPD-028-Document-Modal-DOCX-Viewer`.** **Depends on DPB-028** (`../backend/DPB-028-Document-DOCX-Conversion.md`, plugin 0.22.0), whose commits come first: it converts `.docx`, `.doc` and `.odt` files to page images, adds the **Word (.docx)** checkbox (`native_docx`) to the Native viewer section and `cptdp_document_native_docx_url()`. Also builds on DPD-027 (`DPD-027-Document-Modal-XLSX-Viewer.md`: `showXlsx()`, vendored `ooxml`, `canDrawOffice()`, `destroyNative()`, `hintKey`), DPD-026 and DPD-013.

Theme: `devportfolio` (no version header to bump).

> **Status: draft, not implemented.** Items marked **Decide** carry a recommendation.

## Goal
1. **Converted word files** (the default once DPB-028 has made images) open in the existing open-book viewer as **pages**. Wording stays "page"; the cover and pill say `DOCX · 12 pp`. A converted page is a printed page, as for a PDF.
2. **The native viewer.** When Settings → Documents → Native viewer → Word is ticked, a `.docx` is drawn **from the original file in the visitor's browser** by the bundled viewer, with **selectable, copyable text**. The converted pages are its **fallback**. No LibreOffice needed, nothing sent to anyone; the cost is performance and a bigger first download.
3. **Discoverable native zoom.** While any native viewer is active, show a compact floating action button (FAB) with icon-only zoom-in and zoom-out controls. The controls make zoom available without requiring users to discover a gesture or library-specific shortcut.
4. Everything else unchanged: PDFs, decks, workbooks, and "can't be previewed" (with a document hint) when there is nothing to show; Download keeps working.

## Context
- `devportfolio_document_file_kind()` knows `pdf|pptx|ppt|odp|xlsx|xls|ods|other`; `devportfolio_document_unit()` returns `slide` for presentations, `page` otherwise; `devportfolio_document_viewer_data()` has one early-return branch for Office kinds with stored pages and a `nativeUrl` picked from a kind-to-function map; `viewerMode( doc )` is `native | images | pdf | unavailable`; `viewer.js` has `showPptx()` and `showXlsx()`, `destroyNative()`, `hintKey` (`hintSlide`, `hintSheet`, `hint`), `OOXML_VERSION`; `mode.js` has `canDrawOffice()` and the `PRESENTATIONS`/`SPREADSHEETS` constants.
- DPD-027 deferred to this ticket: merging `showPptx`/`showXlsx` into one loader once a third copy shows the common shape, and handing the kind lists to `mode.js` from the plugin instead of constants. See decision 6.
- The vendored folder (`blocks/document-modal/vendor/ooxml/`) holds the PPTX and XLSX entries. The package has a DOCX engine (`docx.mjs`, `docx_parser_bg.wasm` about 2.26 MB); the exact import graph, the viewer class and its option names are **unknown until the spike (step 3)**. Unlike slides and sheets, a DOCX viewer may be a single scrolling page flow rather than discrete pages.
- The modal only opens above 700px (DPD-017); nothing opens at 700px or less.

## Decision tree
```
open document
  payload.nativeUrl?       ── yes ──> native viewer draws the .docx ── fails ──┐
        │ no                                                                   │ (no WebAssembly or worker, load error, resource limit, timeout)
  payload.images non-empty? ── yes ──> StPageFlip, <img> pages  <──────────────┘ images exist, else "can't be previewed"
        │ no
  payload.viewerUrl?       ── yes ──> gate ──> pdf.js fallback            (PDFs only)
        │ no
  "can't be previewed" (document hint), Download works
```
`viewerMode()` keeps its shape; `canDrawOffice( env )` already covers WebAssembly, `Worker` and a 2D canvas.

## Key decisions
1. **Which viewer shape. Decide after the spike (recommended: the library's paged mode, if it has one).** Two possibilities: (a) a **paged** viewer (`goToPage()`, page count) that the modal's own pager, arrows and progress line can drive, exactly like slides (`showPptx`), with the unit "page"; (b) a **continuous scrolling** viewer, which behaves like the Excel grid: the footer pager, arrows and progress line are hidden (`data-viewer="native-grid"`, already built) and the keys are left to the viewer. (a) keeps one interaction model across documents and reports real page numbers; (b) is what you get if the library has no pages. Step 3 decides; both end in the same fallback rule.
2. **Payload (theme).** `devportfolio_document_file_kind()` returns `docx|doc|odt` (mime map plus the same extension fallback for generic types); `devportfolio_document_unit()` stays `page`; new `devportfolio_document_is_word( $kind )`. `devportfolio_document_viewer_data()` and the modal payload: `kind`, `nativeUrl` from `cptdp_document_native_docx_url()` through `function_exists` (one more entry in the kind-to-function map), the stored pages from the plugin through the same early return (no `viewerUrl`, no `pdfBytes`). `devportfolio_document_format_label()` gives `DOCX · 12 pp` (`DOC`, `ODT` likewise).
3. **`showDocx()` in `viewer.js`**, modelled on `showPptx()`/`showXlsx()`:
   - `import( DOCX_LIB )` only when a native `.docx` opens (never static; StPageFlip not loaded unless it must fall back). The entry, worker and wasm are requested with `?v=${OOXML_VERSION}`.
   - Options **verified against the 0.89.7 type definitions in the spike**: fetch only the document file; **hyperlinks off**, no external fonts, **no comments UI, no tracked-changes UI**; resource limits (`OoxmlResourceLimits`) taken from the modal's `limits` so a hostile or huge document ends in the fallback, not in a frozen tab; timeout `limits.nativeTimeoutMs` (30 s).
   - **Fallback:** returns false after `destroyNative()` and emptying the stage when it cannot draw the file; `show()` continues with the stored pages or "can't be previewed". A `OoxmlResourceLimitError` is "cannot draw", not an error. A render error after the document is showing ends the viewer the same way.
   - **Lifecycle:** `reset()` calls `destroyNative()`; every await checks the session token; notes are `textContent` only.
   - **Text selection:** if the library offers a text layer, say "select text"; if only cell-style copy, say "copy". The Native viewer text (plugin) is what the visitor sees, so the spike's answer decides its wording.
4. **Vendoring.** Add the DOCX graph to `vendor/ooxml/` (same version 0.89.7, same README, updated "what is included" table): `docx.mjs`, its worker if any, `docx_parser_bg.wasm` and the chunks the entry imports that are not already vendored, plus whichever `assets/render-worker-*.js` the document path loads (**find out in a browser**, vendor only those). Left out as before: MathJax, `.doc` add-on, anything the import graph does not force. The "Updating" script copies by import graph for all three entries. Test: every file the entries import exists; the stable-name files (now also `docx.mjs`, its worker and `docx_parser_bg.wasm`) are the **only** ones without a content hash and are requested with `?v=`.
5. **Host requirements** as DPD-026 decision 6; the third wasm (2.26 MB raw, about 0.8 MB gzip to be measured) makes compressing `application/wasm` more important still. Nothing downloads for visitors who open other documents.
6. **Do the deferred consolidation here, small.** With three copies the common shape is visible: one `loadNative( doc )` that picks the entry by kind (a table `{ pptx, xlsx, docx } → { lib, mount, hintKey }`), imports it, mounts, applies the timeout and the fallback rule, and returns the handle `destroyNative()` frees; `showPptx`, `showXlsx` and `showDocx` become thin mounts. And `mode.js` receives the kind lists from the payload (`data-kinds` on the block root, written from the plugin or a theme helper) instead of `PRESENTATIONS`/`SPREADSHEETS` constants. **Decide:** do this refactor as the first viewer commit with the tests green and no behaviour change (recommended), or add `showDocx()` as a third copy and consolidate later (smaller PR, more debt).
7. **Wording and CSS.** New `data-` strings in `render.php`: the document "can't be previewed" hint (`data-hint-doc`): "Documents can’t always be previewed here. Download the file to open it in Word."; the stage's `aria-label` "Document preview". Styles for any library UI (page indicator, zoom) scoped under `.documents-modal__stage--doc`, tokens only, no new colours. If the library draws a page on a grey surround, the stage background must stay the modal's own.
8. **PDFs, presentations and workbooks** unchanged; `data-selectable-text`, `native_pptx` and `native_xlsx` flows are untouched.
9. **"Modal - Mobile" in the ticket label. Decide / confirm.** This plan treats DPD-028 as the DOCX viewer in the existing document modal, which is desktop only (above 700px, DPD-017), as DPD-026 and DPD-027 were. If the ticket also means a mobile body for the modal (a full-screen DOCX reader at 700px or less), that is a separate piece of work: the modal's opening rule, deep link and footer all assume desktop today. Confirm before step 1. If it is in scope, add a phase: open the same viewer in a full-screen sheet at 700px or less for **all** document kinds (not only DOCX), a back control, and the deep link without the desktop-only guard.
10. **Native viewer settings text** (plugin, DPB-028 decision 10) is the user-facing explanation; this ticket only has to match it (what can be selected or copied, no macros, `.doc`/`.odt` use the converted pages, whole file downloaded).
11. **Native zoom controls.** Add one shared FAB to the native viewer stage, visible only while `viewerMode()` is `native`; it contains two icon-only `<button>` controls, zoom out followed by zoom in. Each button has a stable `aria-label` ("Zoom out"/"Zoom in"), a tooltip via `title`, and a disabled state at the viewer's minimum or maximum zoom. Use the library's zoom API where it exists (`showPptx`, `showXlsx`, and `showDocx`); do not apply a generic CSS transform that would desynchronise text selection, scrolling, canvas sizing, or the library's own layout. The FAB is positioned inside the stage at the lower end corner, remains above the native content, respects safe-area insets, and is keyboard reachable without trapping focus. The control group is hidden when the native viewer falls back to images or becomes unavailable, and it is not shown for pdf.js or stored-image rendering. The icons themselves carry `aria-hidden="true"` and no visible text; accessible names come from the buttons.
12. **Native default zoom.** Native viewers must open at a readable, content-fitting scale rather than an arbitrary library default. After the document is loaded and the stage has measurable dimensions, call the library's `fitWidth()` method when available; otherwise use `fitPage()` or the viewer's equivalent. The initial scale must be clamped to the library's supported minimum and maximum, must not exceed the available stage width, and must preserve the library's own text, canvas, and scroll layout. The user can then adjust it with the zoom FAB, and reopening a document starts from this predictable fit-to-width default rather than carrying stale zoom state between documents.

## Out of scope (and what comes next)
- Native `.doc` / `.odt`; editing, comments, tracked-changes display, find-in-document UI, printing, outline/navigation pane; mobile (decision 9, unless confirmed); RTF.

## Risks / open points
1. **Depends on DPB-028.** Without it `nativeUrl` and images are empty for word files and the modal says "can't be previewed" as today (no regression).
2. **The DOCX engine is the least known piece.** Pagination in the browser will not match Word or LibreOffice exactly, so page numbers in native mode may not match the cover's `DOCX · N pp` (which comes from the converted count). Decide in the spike whether to hide the count for native mode or accept it; do not promise equality.
3. **Performance is the price** (why the box is opt-in): the first open downloads the DOCX graph and the 2.26 MB wasm, parses the whole file in the visitor's browser and keeps it in memory. The 10 MB cap (plugin), resource limits, the 30 s timeout and the page fallback bound the worst case. Measure a 100 KB, 2 MB and 10 MB document in headless Chrome and record requests, transfer, first paint and heap in "As built".
4. **Hidden content is downloaded.** In native mode the visitor receives the whole `.docx`: comments, tracked changes, hidden text, document properties and author names are all in the file whether or not the viewer draws them. Converted pages are drawn by Writer with comments and revisions off. The Native viewer text and the editor warning must say so; nothing in the theme can fix it.
5. **The library is young** (0.89): tables with merged cells, text boxes, SmartArt, equations (MathJax not vendored), headers/footers and fields are approximated. Re-test real documents on each update.
6. **Library UI inside a native `<dialog>`:** focus, Esc and keyboard events may fight the modal's. The spike must prove Esc closes, Tab leaves the viewer, and arrow keys scroll or turn pages without both happening.
7. **Page-count drift** between the converted pages (LibreOffice) and the native viewer (decision 1 and Risk 2).
8. **No browser test runner in the repo:** pure helpers are unit-tested; DOM wiring by source-structure tests and headless Chrome runs by hand, as DPD-026/027.

## Steps
One conventional commit each, scope `documents`, **tests never in a feature commit**, both suites green at every commit.
1. **Payload and labels** (feat, test): word kinds in `devportfolio_document_file_kind()`, `is_word`, `nativeUrl`, `DOCX · 12 pp`, the document hint string.
2. **Mode helpers** (feat, test): kind lists from the payload (decision 6), any new `viewerMode`/wording cases, `mode.test.mjs`.
3. **Spike, then vendoring** (build, test): run the DOCX viewer in headless Chrome against the dev site; settle decision 1 (paged or continuous), confirm option names, text selection, hyperlinks, comments and limits, record which render worker loads; then vendor the graph and extend the vendoring tests. Stop and report if the library cannot be shown inside the dialog or has no usable limits.
4. **Consolidate the native loader** (refactor, test; decision 6): one `loadNative()`, thin mounts, no behaviour change.
5. **The native viewer** (feat, test): `showDocx()`, fallback, lifecycle, cache-safe URLs, CSS, footer/arrow handling per decision 1, strings in `render.php`, the native-only zoom FAB with shared state, limits, focus behavior, and icon buttons, plus the readable fit-to-width default zoom.
6. **Docs** (docs): AGENTS.md (kinds, payload, `showDocx()`, vendoring table, host settings, totals).
7. **Plan** (docs): this plan, with DPB-028's, last of the branch.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```
Expected totals before: theme 220 (2 known Services failures), JS 66.

Manual (`/documents/`; fixtures: a one-page document, a 12-page document with a table, an image, a footer and a landscape section, a 10 MB document, a `.doc`, an `.odt`, a corrupt `.docx`, a PDF, a `.pptx`, a `.xlsx`):
1. **Converted document, box off:** the book opens at page 1, pill `DOCX · N pp`, page wording; PDFs, decks and workbooks unchanged.
2. **Native viewer, box on:** the document is drawn with selectable or copyable text per decision 3; scroll or page turning works per decision 1; **Network tab: only the site's own host, the DOCX entry and wasm with `?v=0.89.7`, nothing before the modal opens, no request when clicking a hyperlink.** Esc closes; Tab leaves the viewer.
3. **Native zoom FAB:** the icon-only controls are visible for DOCX, PPTX, and XLSX native viewers, have the correct accessible names and tooltips, zoom the content through the library API, disable at both limits, and remain usable with keyboard focus. They are absent from stored-image, pdf.js, fallback, and unavailable states.
4. **Native default zoom:** DOCX, PPTX, and XLSX open at a readable fit-to-width scale (or fit-page fallback), never at an unexpectedly tiny or oversized scale; the initial scale respects the viewer limits and resets when switching documents.
5. **Fallback:** block the DOCX wasm: the same document shows its converted pages and navigates; the corrupt `.docx` with no images says "can't be previewed" with the document hint and hides footer, arrows, and the FAB; the 10 MB document above a lowered `nativeTimeoutMs` falls back cleanly.
6. **Lifecycle:** close and reopen, switch between a document, a workbook, a deck and a PDF: nothing left over, no console errors, workers gone, and no stale FAB or zoom listeners.
7. **`.doc`, `.odt`:** never get the native viewer (no `nativeUrl`), they show converted pages or "can't be previewed".
8. **Everything off** behaves as before DPD-028; at 700px or less nothing opens (unless decision 9 changes that).

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `feat(documents): add word kinds, native url and labels to the modal payload`
2. `test(documents): cover the word payload and labels`
3. `feat(documents): take the office kind lists from the payload`
4. `test(documents): cover the office kind lists`
5. `build(documents): vendor the ooxml docx viewer`
6. `test(documents): cover the vendored docx viewer and its cache-safe urls`
7. `refactor(documents): load the native office viewers through one function`
8. `test(documents): cover the shared native loader`
9. `feat(documents): draw docx documents with the native viewer in the modal`
10. `test(documents): cover the docx viewer structure and fallback`
11. `docs(agents): document the word viewer`
12. `docs(plans): add DPD-028 plan` (with the DPB-028 plan, last of the branch)

One PR for the shared branch (see the DPB-028 plan).
