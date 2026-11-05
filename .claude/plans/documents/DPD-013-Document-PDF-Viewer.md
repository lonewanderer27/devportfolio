# Plan: Document modal body, PDF viewer in an open-book style (St Page Flip)

Ticket DPD-013 (Modal / Body / PDF Viewer), **phase 2 of 2**. **One shared branch for both phases: `feat/DPB-025-DPD-013-Document-Modal-PDF-Viewer`.** **Depends on DPB-025** (`../backend/DPB-025-Document-PDF-Conversion.md`, plugin 0.19.0), whose commits come first on that branch: it generates the page images **when a document is saved**, so by the time a visitor opens a book the images normally already exist. Also builds on `DPD-009-010-011-012-014-015-Document-Modal.md` (shell, header, footer pager, arrows, the `documents:modal-change` event, the empty `.documents-modal__stage`) and `DPD-016-Shelf-Grid.md` (the item button that opens the modal).

**Out of scope:** everything server side and the admin generator (DPB-025), mobile viewer (DPD-017, DPD-019), zoom, locked pages, non-PDF previews (DOCX/XLSX).

## Acceptance (from the ticket)
1. Portrait PDF with more than one page: two portrait pages side by side, a fold between them, each page number at the bottom. Opens on page 1.
2. Odd total: the last view shows the last page alone on the left, the right side empty.
3. Landscape PDF: one wide page.
4. Single-page portrait PDF: one centred page.
5. White pages on the light grey stage (the existing dotted `--dm-surface-alt` body).
6. Test documents: even portrait, odd portrait, landscape, single-page portrait.

## The contract this plan consumes (from DPB-025)
PHP API in the plugin (the theme calls through `function_exists` checks so the theme suite still runs without it):
- `cptdp_get_document_pages( $post_id )`: `{ pages: string[], count, width, height, ratio }` when the stored images are **valid** (match the current PDF and the page total), else `null`. Never converts.
- `cptdp_document_pdf_url( $post_id )`: attachment URL only when it is a PDF, else `''`.
- `cptdp_document_pdfjs_urls()`: `{ module, worker }` URLs of the pdf.js build the plugin vendors (one copy, shared with the admin generator).
The server converts at **save time** (or the admin's browser does, when the server cannot). The viewer never triggers conversion.

## Architecture: stored images by default, pdf.js only as a fallback
1. **Stored images are valid (the normal case):** StPageFlip with `<img>` pages. pdf.js is never downloaded.
2. **No valid images** (legacy document not backfilled yet, generation pending or failed, page total mismatch): the **hybrid StPageFlip + pdf.js** viewer renders pages in the visitor's browser, in memory. This is the heavy fallback, so it is protected by the **client render gate** (decision 11).
3. **Gate refuses the device, or pdf.js fails:** "can't be previewed", the Download button still works.

```
open document
  payload.images non-empty? ──yes──> StPageFlip, <img> pages          (no pdf.js, normal case)
        │ no
  payload.viewerUrl? ──no──> "can't be previewed"
        │ yes
  client render gate allows? ──no──> "can't be previewed" (Download works)
        │ yes
  pdf.js in memory ─ok──> StPageFlip, <canvas> pages                  (fallback, tier full or light)
        │ fails (remember the failure, decision 11)
        └──> "can't be previewed" (Download works)
```
Both producers implement one **page source** interface so the flip code exists once: `{ count, ratio, mount( index, el ), release( index ) }` (`imageSource( data )`, `pdfSource( pdf )`).

## Key decisions
1. **Payload (theme).** `devportfolio_document_modal_payload()` already has `pages` (the integer page count), so the new keys avoid that name: `images` (URLs, `[]` unless valid), `imageWidth` (pixel width of one stored image, 0 when unknown; caps how far a page is scaled up), `ratio` (0 when unknown), `viewerUrl` (PDF attachment URL or `''`), `pdfBytes` (PDF file size, 0 when unknown; used by the gate) and, on the modal root, `data-pdfjs` (module and worker URLs from the plugin) and `data-limits` (gate limits, overridable through the filter `devportfolio_document_viewer_limits`). Helper `devportfolio_document_viewer_data()` wraps the plugin API with `function_exists` guards. Nothing is converted while rendering a page. There is no `convertUrl`.
2. **Libraries.** StPageFlip is vendored in the theme: `blocks/document-modal/vendor/page-flip.browser.js` (2.0.7, MIT, `window.St.PageFlip`), with licence and a `vendor/README.md`; loaded on the first open of any document with something to show. **pdf.js is not vendored in the theme**: the plugin ships it (DPB-025 decision 9) and the viewer imports it dynamically from `data-pdfjs`, only on the fallback path. No CDN.
3. **Orientation and step.** Stored path: `ratio` is in the payload, so `step` is right on first paint. Fallback path: page 1's viewport decides (`width > height` = landscape) and `view.js` updates `state` once. Rule: `step = ( pages > 1 && portrait ) ? 2 : 1`. `pager.js` `stepFor( pages, orientation = 'portrait' )` (default keeps today's behaviour). The image count or `pdf.numPages` is the truth for `state.pages`.
4. **StPageFlip settings.** Spread (portrait, more than one page): `usePortrait: false`, `showCover: false` (pairs 1-2, 3-4; an odd last page alone on the left), `size: 'stretch'`, `drawShadow: true`, `maxShadowOpacity` ~0.45, `flippingTime` 600, `useMouseEvents: true` (drag-to-flip). One-page view (landscape or single portrait): `usePortrait: true`, landscape page at full stage width, portrait page centred. Page ratio from page 1 / the payload; mixed sizes use page 1's ratio.
5. **Sizing.** New pure `layout.js`: `fitPage( { stageW, stageH, ratio, step } )` returns `{ pageW, pageH }` for a spread or a single page inside the stage, never above source size; `orientationOf( w, h )`. Called on open and from a debounced `ResizeObserver`.
6. **Lazy rendering.** All page divs exist at once (white, with the page-number span). Images: `src` set for the current spread plus 2 views either side, later pages on `flip` (stored images are small WebP files, so this is cheap). Canvases (fallback only): the same window at display width × `devicePixelRatio` (tier caps in decision 11), re-render on a size-bucket change, release canvases far from the view.
7. **One source of truth for the page.** `state.page` stays the authority. Pager and arrows call `setPage()` then `flip.turnToPage()` (instant, number click) or `flipNext()/flipPrev()` (animated). The library's `flip` event (drag, corner click) calls `setPage( e.data + 1 )` behind an echo guard. Left/Right keys use the same path.
8. **Page numbers and look.** Our own `span.documents-modal__pn` at the bottom of every page (tabular numerals, `--subtle`), independent of the file. White pages, 1px `--dm-edge`, soft shadow; the library's fold shadow plus a CSS inner-gutter gradient on the spine edge; the empty right side of an odd last view is transparent.
9. **States.** Loading (`aria-busy`) and "can't be previewed" (no PDF, non-PDF file, gate refused, corrupt file, pdf.js failed). Templates hidden in `render.php` (`esc_html__`). The Download button always keeps working.
10. **Lifecycle.** Opening aborts the previous load (token check after each `await`), destroys the previous flip instance and PDF; closing destroys both and clears the stage. Reduced motion: `flippingTime: 0`. The 700px-or-less behaviour is unchanged. Keyboard: Left/Right turn pages while the dialog is open and focus is not in a form control.
11. **Client render gate (the pdf.js fallback only).** Rendering PDFs in memory is the part most likely to stall a weak device, so it runs only when a pure function allows it. New `capability.js`: `clientRenderTier( env, limits, doc )` returns `{ tier: 'full' | 'light' | 'refused', reason }`; `env` is read once by `readEnvironment()` so the logic is unit-testable with plain objects. When refused, pdf.js is **never downloaded**. First match wins:
    1. **Missing features** (feature detection, not UA sniffing): dynamic `import()`, module `Worker`, 2D canvas, `ReadableStream`, `structuredClone`, `fetch`, `AbortController`, `ResizeObserver`, `Promise.withResolvers`, `AbortSignal.any` (`OffscreenCanvas` is not required: pdf.js falls back without it).
    2. **Minimum browser version, as a backstop:** `navigator.userAgentData.brands` where present, else a small UA parse. Defaults (`limits.minVersions`): **Chrome/Edge 119, Firefox 124, Safari/iOS 17.4**. Confirmed against the vendored pdf.js 4.10.38 build, which uses `Promise.withResolvers` and `AbortSignal.any` with no polyfill (first available in those releases); both are also required **features** in check 1, so a browser that reports an old UA but has them is still judged on features. Unknown browser = allowed. Re-check these floors whenever the plugin's pdf.js is upgraded.
    3. **Data saver / slow network:** `navigator.connection.saveData` true or `effectiveType` of `slow-2g`/`2g`/`3g`. API absent = allowed.
    4. **Memory:** `navigator.deviceMemory` at or below `limits.minDeviceMemoryGB` (default 2). Absent (Safari, Firefox) = allowed; `hardwareConcurrency` of 2 or less on a coarse-pointer device caps the tier at `light`.
    5. **Document size** from the payload, no bytes fetched: `pdfBytes` over `limits.maxPdfBytes` (25 MB mobile-class, 80 MB desktop) or `pages` over `limits.maxPages` (60 mobile-class, 300 desktop).
    6. **Previously failed here:** a `localStorage` flag (`documents-viewer-client-failed`, try/catch, 7-day expiry, cleared by a later success) set when the fallback failed or timed out. A pdf.js error that blames the file (`PasswordException`, `InvalidPDFException`, `MissingPDFException`, `UnexpectedResponseException`, `FormatError`) does **not** set it: one bad PDF must not block every other document on that browser.
    7. **Mobile-class** (`(pointer: coarse)`, or touch with a viewport under 1024px) gets `light`; everything else `full`.
    **Tier budgets:** `full` = pixel density cap 2, current view plus 2 either side, up to 12 live canvases. `light` = cap 1.5, current plus 1 either side, up to 6 canvases released as they leave the window, each canvas at most 8 megapixels (under the iOS Safari canvas limit of about 16.7 MP). A canvas that cannot be created, or a draw error, counts as a failure (sets the flag, shows "can't be previewed"). All limits live in one `limits` object, tunable through the PHP filter. **Scope:** the modal opens only above 700px (DPD-017/019 own phones), so today the gate protects tablets, touch laptops, narrow windows and weak machines; it is built and tested now so the phone viewer reuses it.

12. **Bottom trim for stored images.** The plugin finds the empty strip at the bottom of the pages (DPB-025 decision 14) and the payload carries `cropBottom` (0 to 0.25, anything else is ignored) next to a `ratio` that is already for the visible part, so `fitPage()` and the layout are unchanged. For stored-image books the book gets `documents-modal__book--cropped` and `--documents-crop`: each `<img>` is `calc(100% / (1 - var(--documents-crop)))` tall with `object-fit: cover; object-position: top`, the sheet is `display: block; overflow: hidden`, so the page clips the empty strip. The page number stays at the bottom of the visible page. **The pdf.js fallback is not cropped** (its ratio is the PDF's natural one). A printed footer counts as content, so documents that have one are not cropped.

13. **Selectable text (setting, off by default).** Stored images cannot be selected. Settings → Documents `selectable_text` (with an explicit warning that it can be laggy) makes the viewer try pdf.js FIRST and lay a real text layer over each canvas (`pdfjs.TextLayer`, a transparent `.documents-modal__text` layer sized by `--scale-factor`). StPageFlip's mouse events and corner hints are switched off in that mode (dragging selects text; pages turn with the arrows, pager and keys). Pages that cannot be drawn by pdf.js (gate refusal or failure) fall back to the stored images, without selectable text. The theme reads the setting through `devportfolio_document_selectable_text()` and passes `data-selectable-text="1"` on the modal root.

## Risks / open points
1. **Depends on DPB-025.** Without it `images` is always empty and every document takes the pdf.js fallback (still works, behind the gate). With it, the fallback is rare.
2. **No server round trip for visitors.** A device refused by the gate, opening a document with no images yet (legacy, pending, failed), sees "can't be previewed" with Download. Backfill once with `ddev wp cptdp pages generate --all` after deploying. The earlier draft's public convert route was dropped on purpose (DPB-025 Risk 2).
3. **Gate thresholds are a first guess.** `deviceMemory` exists only in Chromium; version floors depend on pdf.js's support table. Tune on real devices (iPhone Safari, a low-end Android, an iPad) before merge.
4. **pdf.js now lives in the plugin.** The theme viewer's fallback therefore needs the plugin active (already true for stored images). Its `.mjs`/worker MIME types must be served correctly by the host (DDEV nginx: verify; else the plugin ships them as `.js`).
5. **StPageFlip odd-last-page behaviour** with `showCover: false` is verified in the browser; fallback is a transparent blank page to complete the pair. The library is unmaintained: pinned and vendored.
6. **No browser test runner:** pure helpers (`pager.js`, `layout.js`, `capability.js`) are unit-tested, DOM and flip wiring is manual (as for the categories block).
7. **No real PDFs in the repo:** QA needs four fixtures uploaded in wp-admin (not committed).

## Steps
One conventional commit each, scope `documents`, **tests never in a feature commit**, both suites green at every commit. These commits come after the DPB-025 commits on the shared branch.

### Step 1 (commit 1 feat, 2 test): payload
Payload keys and helper of decision 1 (`images`, `ratio`, `viewerUrl`, `pdfBytes`, `data-pdfjs`, `data-limits`, the `devportfolio_document_viewer_limits` filter). Tests in `tests/test-document-modal.php`: `images` only when the plugin reports valid images, `ratio`, `viewerUrl` only for a PDF, `pdfBytes`, the existing integer `pages` is untouched, nothing breaks when the plugin API is absent, filter overrides reach `data-limits`, non-document stays `null`.

### Step 2 (commit 3 feat, 4 test): pure JS helpers
`pager.js` `stepFor( pages, orientation )`, new `layout.js`. Tests with `node --test`: landscape = step 1 for any count; 1/3/4-page navigation sequences; fit helper (width-bound, height-bound, single, landscape, tiny stage, cap); `orientationOf`.

### Step 3 (commit 5 feat, 6 test): client render gate
`capability.js` (`clientRenderTier`, `readEnvironment`, default `limits`, failure-flag helpers with try/catch storage). Tests in `blocks/document-modal/tests/capability.test.mjs` with plain `env` objects: each missing feature refuses; versions just below and at each floor; unknown browser allowed; `saveData` and slow `effectiveType`; `deviceMemory` 2 refused, 4 allowed, absent allowed; oversize PDF and page count on mobile-class vs desktop; failure flag set, expired, cleared; coarse pointer or narrow touch = `light`, desktop = `full`; first matching rule wins and `reason` names it; limit overrides; storage throwing tolerated. Version floors here are frozen after the plugin vendors pdf.js (DPB-025 step 4).

### Step 4 (commit 7 build, 8 test): vendor StPageFlip
`vendor/page-flip.browser.js`, licence, README with the version. Tests: file and licence exist, README version matches, no external URL in `view.js`/`render.php`, default version floors in `capability.js` match the plugin's pdf.js README.

### Step 5 (commit 9 feat, 10 test): the viewer
`view.js`: page source interface, image and pdf sources, the decision tree with the gate, tier budgets, flip wiring, lazy windows, failure-flag writes, states, lifecycle, keyboard; `render.php` state templates; `style.css`. Tests (structure only): stage templates, page and page-number classes, white pages on grey, reduced-motion rule, `data-pdfjs` and `data-limits` on the root.

### Step 6 (commit 11): docs
`docs(agents): document the document viewer`: theme architecture (viewer paths, the gate and its limits/filter, payload keys, state flow), test counts and the `node --test` commands.

### Step 7 (commit 12): plan
`docs(plans): add DPD-013 plan` (with the DPB-025 plan, last of the branch).

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal node --test tests/
```
Manual (`ddev launch`, `/documents/`), four PDFs on four documents saved in wp-admin: **A** 4-page portrait, **B** 3-page portrait, **C** 1-page landscape (and a 3-page landscape), **D** 1-page portrait; plus one with no file and one DOCX.
1. **Normal case:** each document shows `Ready` in the Pages column after saving; open A: pages 1-2 side by side with a fold and numbers 1 and 2; Next, arrows, pager, drag and Left/Right keys all agree with the indicator. The Network tab shows **page images and StPageFlip only, no pdf.js, no worker**.
2. B last view: page 3 left, right side empty, `Page 3 of 3`. C: one wide page; D: one centred page, no pager.
3. **Fallback:** `ddev wp cptdp pages clear <A>`, reload and open A: pdf.js and its worker load from the plugin, pages render from canvases, behaviour identical. Replace A's PDF in wp-admin: images regenerate on save and the stored path returns.
4. **Mismatch:** set A's total pages wrong: the Pages column says Failed, the viewer uses the pdf.js fallback and opens with the PDF's real count.
5. **Gate:** with no images for a document, lower a limit through the filter (`maxPages` below A's count, or `minVersions` above the browser's) and open it: no pdf.js request, "can't be previewed", Download works. Repeat with network throttled to "Slow 3G" and with data-saver emulation. On a real iPhone Safari in a >700px window or iPad, a low-end Android and an iPad: note each tier; none stalls or reloads the tab on a 24-page document in the fallback. Block the worker: the failure flag is set, the next open skips pdf.js without waiting, a later success clears it; clear site storage to reset.
6. No-file and DOCX documents show "can't be previewed" with no requests.
7. Resize (720, 1280, 1920px) and DPR 2: pages refit and stay sharp. Close and reopen another document: no leftovers, starts on page 1. No console errors. `prefers-reduced-motion`: instant turns. Closing mid-load cancels cleanly.

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `feat(documents): add page images and pdf url to the modal payload`
2. `test(documents): cover the viewer payload keys`
3. `feat(documents): add orientation aware step and page fit helpers`
4. `test(documents): cover orientation aware step and page fit helpers`
5. `feat(documents): add client render gate for the pdf viewer`
6. `test(documents): cover the client render gate`
7. `build(documents): vendor st-page-flip`
8. `test(documents): cover the vendored viewer library`
9. `feat(documents): show document pages as an open book in the modal`
10. `test(documents): cover the document viewer structure`
11. `docs(agents): document the document viewer`
12. `docs(plans): add DPD-013 plan` (with the DPB-025 plan, last of the branch)

One PR for the shared branch (see the DPB-025 plan): `feat(documents): DPB-025 DPD-013 document PDF conversion and open-book viewer`.

## As built (differences from the plan above, after review)
Read this section as the source of truth where it disagrees with the text above. Final theme suite: 186 tests (the 2 known Services navigation failures), JS 53 passing.
- **Files:** the viewer is `viewer.js` (loaded by `view.js`), with `layout.js` (also `renderWindow`, `canvasScale`) and `capability.js` (also `mergeLimits`, `parseBrowser`, `budgetFor`, `isBenignRenderError`, `safeStorage`, the failure-flag helpers). StPageFlip is loaded with a script tag, pdf.js with `import()` from `data-pdfjs`.
- **StPageFlip:** `size: 'fixed'` with `autoSize: false` and a computed container size (not `stretch`); `flippingTime` is 1 under reduced motion (not 0); `isTurning()` only counts `flipping` and `user_fold`.
- **Payload and data attributes:** also `cropBottom`, `imageWidth`, and `data-selectable-text` on the root. Limits come from `data-limits` as overrides only.
- **Gate:** `hardwareConcurrency <= 2` on any device gives tier `light` (`low-cpu`); floors are Chrome/Edge 119, Firefox 124, Safari 17.4 (pdf.js 4.10.38 needs `Promise.withResolvers` and `AbortSignal.any`), applied per browser family (CriOS, FxiOS, EdgiOS by their own version tokens, feature checks are the real guard). `OffscreenCanvas` is not required. `maxCanvases` is not enforced; the render window (`budget.ahead`) and `maxPixels` through `canvasScale` bound memory. Teardown errors (`AbortException`, `RenderingCancelledException`, "Worker was destroyed" and similar) never set the failure flag; bad-file errors (password, invalid PDF) do not either.
- **Crop:** stored images show the top `1 - crop` of each image through a CSS custom property; the pdf.js fallback is not cropped; selectable-text books use the PDF's own ratio and have no crop.
- **Selectable text (Settings → Documents):** pdf.js first with a transparent text layer, corner dragging off (arrows, pager and keys still turn pages); if pdf.js is refused or fails at open the stored images are used (no selectable text); a draw failure after the book is open shows "can't be previewed" and does not fall back.
- **Stored images:** a page whose image fails to load gets `documents-modal__page--broken` (faint hatch); for a spread the second page is preloaded before the book shows; a broken first or second image falls through to the pdf.js fallback.
- **Footer (changes DPD-011's text):** "Pages 1-2 of 6" and "Two-page spread" are removed; the progress bar is a thin line along the footer's top edge (`role="progressbar"` with `aria-valuetext` carrying the position); footer and page arrows are hidden for documents with fewer than 2 pages; the 700px-and-below footer rule is unchanged.
- **Open animation:** fade and 10px rise only (no scale), and the stage is measured by its layout size (`clientWidth`/`clientHeight`) instead of `getBoundingClientRect()`.
- **Lifecycle:** every async path reports against the session that started it (`fail( error, mine )`), so closing or switching documents mid-render cannot wipe the new book or set the failure flag; `safeStorage()` guards blocked site data; the module-worker probe runs once per window and revokes its blob URL; pdf.js is destroyed if `getPage(1)` throws; the stage has no `aria-live`.
- **Test data:** documents in the dev database use the theme's cover styles through post meta `cptdp_cover_style` (the plugin does not register it yet).
- **Not verified in a browser:** the whole viewer (odd last page alone on the left, hard-page flip look, text-layer alignment, gate decisions on real devices) and the lifecycle races (the PHP tests are source-text checks).
