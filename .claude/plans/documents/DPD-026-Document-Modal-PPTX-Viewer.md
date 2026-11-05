# Plan: Document modal body, PPTX viewer (slide wording and the native viewer)

Ticket DPD-026 (Modal / Body / PPTX Viewer), **phase 2 of 2**. **One shared branch for both phases: `feat/DPB-026-DPD-026-Document-Modal-PPTX-Viewer`.** **Depends on DPB-026** (`../backend/DPB-026-Document-PPTX-Conversion.md`, plugin 0.20.0), whose commits come first on that branch: it converts `.pptx`, `.ppt` and `.odp` files to page images, adds the **Native viewer** section to Settings → Documents (`native_pdf`, `native_pptx`) and `cptdp_document_native_pptx_url()`. Also builds on `DPD-009-010-011-012-014-015-Document-Modal.md` (shell, header, footer pager, arrows, `documents:modal-change`) and `DPD-013-Document-PDF-Viewer.md` (`viewer.js`, `layout.js`, `capability.js`, states, lifecycle).

Theme: `devportfolio` (no version header to bump).

## Goal
Make the document modal show presentations well:
1. **Converted presentations** (the default once DPB-026 has made images) open in the existing open-book viewer, which already shows landscape pages as one wide page, and are described in **slide** words: `PPTX · 12 slides`, `Slide 3 of 12`, `Previous slide`.
2. **The native viewer.** When Settings → Documents → Native viewer → PowerPoint is ticked, a `.pptx` is drawn **from the original file, in the visitor's browser**, by a bundled viewer: one canvas per slide with a transparent text layer, so **text can be selected and copied**, and the modal's own pager, arrows and keys turn the slides. It takes over from the converted slides, which remain its **fallback**; it needs no LibreOffice on the server and sends nothing to anyone. The cost is performance, which is why the setting is opt-in.
3. Everything else is unchanged: PDFs show the book (or, with the PDF checkbox, the pdf.js text layer), and a document with nothing to show says "can't be previewed" (with a presentation-specific hint) while Download keeps working.

## Context
- Stored images already reach the modal through `devportfolio_document_viewer_data()` (`images`, `ratio`, `imageWidth`, `cropBottom`) and DPD-013's `viewer.js`; for a landscape deck `orientationOf` gives step 1, so a deck is already one wide page per view with the pager, arrows and keys. DPB-026 sets the crop of presentations to 0.
- `viewerUrl` (the PDF URL) stays `''` for presentations (`cptdp_document_pdf_url()` is PDF only), so pdf.js never sees them.
- Labels were `PDF · 24 pp` (`devportfolio_document_format_label( $filetype, $pages )`) and `Page i of n` in `viewer.js` and `view.js`.
- The modal only opens above 700px, so no phone layout is needed.
- The theme has **no build step** and no framework, so any viewer library must be vendored as ready-to-run files.

## Choosing the library
| | `pptx-react-viewer` (`ChristopherVR/ooxml`) | `@silurus/ooxml` (`yukiyokotani/office-open-xml-viewer`) |
|---|---|---|
| Fit | React component and full editor UI, a dozen peer dependencies (React, framer-motion, AI SDK, i18next, jspdf…) | Framework-agnostic ESM, read-only |
| Measured | bundled with esbuild: **13 MB, 4 MB gzipped** | vendored subset **5.9 MB** (the import graph of `pptx.mjs` plus the 2 MB wasm), **555 KB gzip** and 22 requests for a first open |
| Rendering | HTML/CSS, editing, collaboration, export | Rust/WebAssembly parser, Canvas 2D renderer, transparent DOM text layer |
| Network | not checked | none by default (web fonts only if `useGoogleFonts`, which the modal never sets) |
| Licence | Apache-2.0 | MIT |
Chosen: **`@silurus/ooxml` 0.89.7**. Also tried and removed: an opt-in iframe of Microsoft's online viewer (it sends the file's address to a third party, needs a public URL and cannot be controlled), replaced by this.

## Decision tree
```
open document
  payload.nativeUrl?      ── yes ──> native viewer draws the .pptx on a canvas ── fails ──┐
        │ no                                                                              │ (no WebAssembly or worker, load error, 30 s timeout)
  payload.images non-empty? ── yes ──> StPageFlip, <img> pages  <────────────────────────┘ images exist, else "can't be previewed"
        │ no
  payload.viewerUrl?      ── yes ──> gate ──> pdf.js fallback            (DPD-013; PDFs only)
        │ no
  "can't be previewed" (hint for presentations), Download works
```
The choice is one pure function, `viewerMode( doc )` in `blocks/document-modal/mode.js` (`native` | `images` | `pdf` | `unavailable`), unit-tested. `unitOf( doc )`, `wording()`, `pieceLabel()`, `positionText()` and `canDrawPresentations( env )` live there too.

## Key decisions
1. **Payload (theme).** `devportfolio_document_file_kind( $post_id )` (`pdf|pptx|ppt|odp|other`, mime type or extension, core functions only), `devportfolio_document_unit( $kind )` (`slide` for presentations, else `page`) and `devportfolio_document_unit_strings()` (translated words for both units). `devportfolio_document_viewer_data()` and the modal payload gain `kind` and `nativeUrl` (the plugin's `cptdp_document_native_pptx_url()` through `function_exists`, `esc_url_raw`). For a presentation the function returns early with no `viewerUrl` and no `pdfBytes` but with the stored slides from the plugin, so the converted slides are always there as the fallback. `devportfolio_document_format_label( $filetype, $pages, $kind = '' )` says `1 slide` / `12 slides` for presentations and is unchanged for everything else (cover and modal pill). The payload title is now plain text (`html_entity_decode`): the script writes it with `textContent` and `&#038;` showed on screen.
2. **Slide wording.** `data-units` on the block root carries the unit strings; `view.js` sets the arrow and pager `aria-label`s per document, the progress bar's `aria-valuetext` (`Slide 5 of 12`, `Slides 1–2 of 12`) and the pager number labels; `viewer.js` labels each page and the canvas.
3. **The native viewer** (`viewer.js` `showPptx()`):
   - Loaded with `import( PPTX_LIB )` only when a native document opens (never a static import; StPageFlip is not loaded either unless it has to fall back). `canDrawPresentations()` needs WebAssembly, `Worker` and a 2D canvas.
   - Options: `enableTextSelection: true`; `enableHyperlinks`, `enableMediaPlayback`, `enableElementSelection` and `useGoogleFonts` all `false` (pinned by a test), so it requests the file and its own modules and nothing else; `workerTimeoutMs` and the import and load timeouts are `limits.nativeTimeoutMs` (30 s, `DEFAULT_LIMITS`, overridable through the existing `data-limits`).
   - **One canvas, the modal's controls.** The viewer reports the real slide count through `onGeometry()`, so footer, pager, arrows, keys and the progress line work exactly as for the book; `goTo()` calls `goToSlide()`, `isTurning()` is true while a slide is being drawn, `onSlideChange( index, total )` keeps the canvas label (`Slide 2 of 6`) and echoes only changes the modal did not make. `state.step` starts landscape so no two-page footer flashes while the deck loads.
   - **Drawn at the size it is shown:** render width = the stage fitted at 16:9 (640 to 1920 px), `dpr` capped at 2, then scaled with CSS (`fitPage` and a `ResizeObserver`); about 2.6 MP and 10 MB on a retina screen instead of a fixed 3.5 MP.
   - **Fallback:** `showPptx()` returns false (after destroying its worker and emptying the stage) when it cannot draw the file; `show()` then continues with the stored slides, or says "can't be previewed" when there are none. A render error after the deck is showing ends the viewer the same way.
   - **Lifecycle:** `reset()` calls `destroy()` on the viewer, so closing or switching documents frees the workers; every await checks the session token; the canvas note is `textContent` only.
   - **No note under the slide:** the canvas is the only thing in the stage. Presentation hint when nothing can be shown (`data-hint-slide`): "Presentations can’t always be previewed here. Download the file to open it in PowerPoint."
4. **Vendoring (`blocks/document-modal/vendor/ooxml/`).** The import graph of `pptx.mjs` (static and dynamic), `assets/render-worker-*.js`, `pptx_parser_bg.wasm`, `LICENSE`, `THIRD_PARTY_NOTICES.md` and a README (version, what is left out, how to update, host settings): 36 files, 5.9 MB of the package's 21 MB. Left out: the DOCX and XLSX engines, MathJax (equations show placeholders), the 3D, region-map and chart-ex add-ons and the legacy `.ppt` add-on. A test checks that every file the entry imports is present.
5. **Cache-safe URLs.** Every chunk has a content hash in its name; the three files that do not (`pptx.mjs`, `pptx-source-worker.mjs`, `pptx_parser_bg.wasm`) are the only ones that could go stale. `viewer.js` requests the entry and the wasm (`wasmUrl`) with `?v=${PPTX_VERSION}`; a test checks `PPTX_VERSION` equals the version in the README and that exactly those three files lack a hash.
6. **Host requirements** (documented in the vendor README and AGENTS.md, not enforceable from the theme): serve `.mjs` as JavaScript and `.wasm` as `application/wasm` (DDEV does); **compress `application/wasm`** (1.99 MB raw, 0.75 MB gzip, 0.57 MB brotli, about 1.2 MB saved per first open; DDEV's nginx does not, and a snippet for it was considered and not applied); allow a long cache for the vendor folder (safe thanks to decision 5).
7. **PDFs.** `devportfolio_document_selectable_text()` (name kept) now reads the plugin's `native_pdf`, which accepts the old `selectable_text`; the viewer behaviour (pdf.js first, text layer, no corner dragging, fall back to the images) is DPD-013's and unchanged, including `data-selectable-text` on the block root.
8. **Pager focus fix.** After the pager redraws, the focus goes back to the control that had it (matched by `data-turn` or `data-page`); before, clicking a page number dropped the focus to the page and the arrow keys stopped reaching the dialog (also for the book). Found while checking the native viewer.

## Out of scope (and what comes next)
- **A later ticket will add DOCX and XLSX** to the Native viewer section of Settings → Documents and to this modal: new `kind` values, a checkbox each, the library's DOCX and XLSX engines vendored, the same takeover-with-fallback rule. `unitOf()`, `viewerMode()` and `devportfolio_document_file_kind()` are the places to extend.
- Native viewing of `.ppt` (needs the library's experimental add-on) and `.odp` (unsupported), deck-style transitions, a thumbnail strip, fullscreen, speaker notes, equations, search inside a slide.

## Risks / open points
1. **Depends on DPB-026.** Without it `nativeUrl` and the images are empty for presentations and the modal says "can't be previewed" as before (no regression).
2. **Performance is the price** (the reason the box is opt-in): the first open downloads about 555 KB gzip plus the wasm and draws on the visitor's device; large decks and weak phones will feel it. The 30 s timeout and the image fallback bound the worst case.
3. **The library is young** (0.89, written largely with AI coding agents by its own account): pinned and vendored, no animations or equations, charts and 3D partly approximated. Re-test real decks on each update.
4. **Host settings** (decision 6) decide how heavy the first open really is.
5. **`.pptx` files with external content** are only drawn by the viewer, never fetched from elsewhere (hyperlinks and media are off); the conversion path has its own checks (DPB-026).
6. **No browser test runner in the repo:** the pure helpers are unit-tested, the DOM wiring is checked by source-structure tests and by headless Chrome runs done by hand.

## Steps
One conventional commit each, scope `documents`, **tests never in a feature commit**, both suites green at every commit. (The work exists in the working tree uncommitted; this is the intended split.)
1. **Payload and wording** (feat, test): file kind, unit and strings, `kind` and `nativeUrl`, slide labels, plain-text title.
2. **Mode helpers** (feat, test): `mode.js` and `mode.test.mjs`, `nativeTimeoutMs`.
3. **Vendored viewer** (build, test): `vendor/ooxml/` with its README; the vendoring test.
4. **The native viewer** (feat, test): `showPptx()`, fallback, cache-safe URLs, canvas sizing, text selection, strings in `render.php`, CSS, `view.js` wording.
5. **Pager focus** (fix, test).
6. **Docs** (docs): AGENTS.md.
7. **Plan** (docs): this plan, with DPB-026's, last of the branch.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```
Manual (`/documents/`; DDEV has LibreOffice after DPB-026's `ddev restart`). Fixtures: a real `.pptx` deck, a one-slide deck, a 4:3 deck, a `.ppt`, an `.odp`, a PDF, a corrupt `.pptx`.
1. **Converted deck, box off:** the book opens with one wide slide, pill `PPTX · N slides` (when the total is set), `Slide 1 of N`, `Previous slide`/`Next slide`, pager label `Slides`; PDFs still say `Pages 1–2 of 4`.
2. **Native viewer, box on:** the same deck opens on a canvas (no book), the note shows, Next, the pager numbers and the Left/Right keys turn slides and the progress text follows; drag across slide text selects it and copying works; the Network tab shows only the site's own host, the entry and wasm with `?v=0.89.7`, and nothing before the modal is opened.
3. **Fallback:** block the `.wasm` request (DevTools): the same deck shows the stored slides and navigates; a corrupt `.pptx` with no images says "can't be previewed" with the slide hint and hides the footer and arrows.
4. **Lifecycle:** close and reopen, switch between a deck and a PDF: no canvas left over, no console errors, the workers are gone.
5. **`.ppt`, `.odp`:** never get the native viewer (no `nativeUrl`), they show their converted slides or the "can't be previewed" state.
6. **Pager focus:** click a pager number, then press ArrowLeft: the slide (or page) turns.
7. **PDF native box:** with it ticked a PDF opens as the selectable book (text layers present); with everything off both formats show the book as before.

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `feat(documents): add file kind, native url and slide wording to the modal payload`
2. `test(documents): cover the presentation payload and slide labels`
3. `feat(documents): add viewer mode selection and the native viewer gate helpers`
4. `test(documents): cover the viewer mode helpers`
5. `build(documents): vendor the ooxml pptx viewer`
6. `test(documents): cover the vendored viewer and its cache-safe urls`
7. `feat(documents): draw pptx documents with the native viewer in the modal`
8. `test(documents): cover the native viewer structure and fallback`
9. `fix(documents): keep the keyboard focus on the pager after it redraws`
10. `docs(agents): document the presentation viewer`
11. `docs(plans): add DPD-026 plan` (with the DPB-026 plan, last of the branch)

One PR for the shared branch (see the DPB-026 plan).

## As built
Final theme suite: **211 tests** (the 2 known Services navigation failures), JS **63 passing** (53 before); plugin 664 (DPB-026).
- **Measured** in headless Chrome against DDEV (cache disabled, retina viewport): Documents page HTML 19 KB gzip with a 13 KB inline JSON payload for 12 documents (about 1 KB per document); a native-viewer first open makes 22 requests and transfers 555 KB gzip (2.2 MB decoded) plus the 1.99 MB wasm, shows the first slide in 180 to 260 ms, turns a slide in about 300 ms and uses about 10 MB of JS heap; a second open fetches only the deck (80 KB).
- **Verified in headless Chrome** (puppeteer-core, scripts kept outside the repo): converted deck as the book in slide wording; native viewer with both boxes ticked on a deck that has LibreOffice images (canvas, "Slide 1 of 8", Next works, drag selection of "Operations Review / Jobs, hours and workload…" works, a PDF opens as the selectable book with four text layers); with the `.wasm` blocked the same deck falls back to the stored slides and navigates; a corrupt `.pptx` says "can't be previewed" with the slide hint in about half a second; closing leaves no canvas or iframe; only the site's own host is contacted; everything off behaves as before. The dev settings were restored afterwards.
- **Known and not investigated:** a triple-click on slide text selects nothing (drag selection works). **Not verified:** the book with a deck of every shape (odd last slide, 4:3), phones (the modal is desktop only), real hosts' `.wasm` handling.
- **Left alone on purpose:** the 1.7 MB `assets/render-worker-*.js` is not requested in the main-thread mode we use but may be needed by other decks, so it stays (repository weight only); the inline payload grows by about 1 KB per document (revisit at hundreds); host compression for `application/wasm` is documented, not applied to DDEV.
