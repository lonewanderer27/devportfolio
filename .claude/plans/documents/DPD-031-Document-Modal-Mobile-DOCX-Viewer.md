# Plan: Document modal, mobile body for Word documents (DPD-031, Modal - Mobile, Body / DOCX Viewer)

Theme: `devportfolio` (no version header to bump). **No plugin change** (DPB-028, plugin 0.22.0, already converts `.docx`/`.doc`/`.odt` to stored pages and provides `cptdp_document_native_docx_url()`). Branch `feat/DPD-031-Document-Modal-Mobile-DOCX-Viewer`, cut from `main`.

> **Status: implemented and verified by the owner locally; not yet checked on a real phone.** As built: the spike was not run, `showGrid()` became `showScroll()` (neutral `.documents-mobile__scroll` classes) and the Word zoom buttons call the library's zoom. Cleanup done: items 1, 5 and 6 (aliases `canDrawPresentations` and `PPTX_VERSION`, `isMobilePdf`, stale comments). Items 2 and 4 (merging the three desktop native mounts, kind lists from the payload) were left out as optional. Commits are grouped by area rather than the exact step list below. The ticket text was not supplied, so the behaviour below is derived from DPD-028 (desktop DOCX viewer), DPD-029 (mobile PPTX) and DPD-030 (mobile XLSX, whose gate comment and risk 8 name this ticket). Anything the ticket says that differs wins. Items marked **Decide** carry a recommendation; open questions are at the end.

## Goal
Today a `.docx`, `.doc` or `.odt` opened in the **mobile** dialog (700px or less, or a touch screen held sideways) shows "can't be previewed", because `canShowOnMobile()` in `mode.js` lets only PDFs, presentations and workbooks through (its comment says "Word documents (DPD-031) have their own ticket"). Make the mobile body show Word documents:

1. **Converted pages** (default; whenever DPB-028 made images): the existing mobile page viewer, page wording (`DOCX · 12 pp`), same swipe, edge taps, pinch/double-tap zoom, hint, footer and round buttons as a PDF.
2. **Native viewer** (Settings -> Documents -> Native viewer -> Word on, `.docx` only): the original file drawn by the bundled `DocxScrollViewer` (continuous scroll, selectable text), with the converted pages as its fallback exactly as on desktop.
3. Everything else unchanged. This ticket closes the gap: after it every kind the modal knows opens on mobile, and `unavailable()` on mobile is only "no images and no native".

## Context (what already exists, do not rebuild)
- **Gate** (`mode.js`): `canShowOnMobile( doc )` = `pdf` or slide or spreadsheet. `isWord( doc )` and `hintKeyFor()` (`hintDoc`) exist. `nativeAllowedOnMobile()` is `pptx` or `xlsx`. `isNativeGrid()` is xlsx only. `viewerMode()` is `native > images > pdf > unavailable`.
- **Desktop DOCX** (`viewer.js` `showDocx()`, DPD-028): `import( DOCX_LIB )`, `new lib.DocxScrollViewer( wrap, { wasmUrl, useGoogleFonts: false, enableTextSelection: true, enableElementSelection: false, comments: false, enableHyperlinks: false, showTrackedChanges: false, pageShadow: false, refitOnResize: true, workerTimeoutMs, resourceLimits: { maxArchiveEntries: 5000, maxTotalInflatedBytes: 1 GB, maxArchiveEntryBytes: 100 MB }, onError } )`, `load( nativeUrl )`, reports **one piece** (`onGeometry( { pages: 1, step: 1 } )`), `showZoomFab()` with `zoomIn()`/`zoomOut()`/`getScale()`. Its options are **inline in `viewer.js`**, with `DOCX_LIB`/`DOCX_WASM` constants there too: the third inline copy that DPD-029/030 avoided for the other two.
- **Mobile shared mounts:** `native-pptx.js` (`openPptx`, `readNativeEnvironment`, `OOXML_VERSION`) and `native-xlsx.js` (`openXlsx`), each the single place for URLs with `?v=` and the privacy options.
- **Mobile viewer** (`mobile-viewer.js`): `show()` gates, then `showGrid()` (workbook: grid mounted OUTSIDE the gesture frame, one piece, details FAB only, `.documents-mobile__stage--grid`) or `showNative()` (deck: one canvas inside the frame). `makeDetails()`, `reset()` (`gridView`), `fail()`, `measure()` are reusable.
- **Chrome rule:** a document that reports one piece hides the footer and progress bar and shows the round details button (`mobile.js` `footerGone = pages < 2`). Exactly what a continuous scroller needs.
- **Converted Word pages** are portrait A4/Letter images (no bottom crop). On a phone in portrait this is one page fitted to the width; `spreadFits()` allows a spread only when the space does (tablet-width, held sideways).

## Behaviour

### Converted pages (images)
| Aspect | Behaviour |
|---|---|
| Gate | `canShowOnMobile()` also accepts `docx`, `doc`, `odt` (`isWord( doc )`). After this nothing known is gated out; the gate stays the one place to widen. |
| Pages | Page wording (`Page 2 of 12`, footer `Pages`), portrait, one page per view (spread only when `spreadFits()`), 1x-4x pinch/double-tap, swipe, edge taps, hint `swipe or tap edges`. |
| Footer, round buttons | As a PDF: footer with `Pages`, position, Details and the progress bar; one page hides the footer and shows the details button; landscape-touch chrome unchanged. |
| No images | `data-hint-doc`: "Documents can't always be previewed here. Download the file to open it in Word." Download works. `.doc`/`.odt` with no images land here. |

### Native viewer (`.docx`, setting on)
- Wins over the stored pages (`viewerMode() === 'native'`); loaded with `import()` only for that document; needs `canDrawOffice()`, else the stored pages.
- **Mount: a continuous scroller, treated like the workbook grid, not like the deck canvas.** The library owns vertical scroll, text selection and its own page layout (`refitOnResize: true`), so it is mounted OUTSIDE the swipe/zoom frame in a wrapper (**Decide** the class: reuse `.documents-mobile__grid` renamed to a neutral `.documents-mobile__native-view` plus a `--doc` modifier, recommended, so the CSS is written once). Swipe-to-turn, edge taps, previous/next hints, CSS zoom and `touch-zoom.js` are **not bound** (they would fight scrolling and selection). It reports **one piece**, so the footer and progress bar stay hidden and the round details button shows; the Pages round button is hidden (no pager to open).
- **No library tab bar**, unlike the grid: the DOCX viewer has no chrome of its own at the bottom, so the details FAB does not need lifting by `--documents-grid-bar`. Verify in the spike; if the viewer draws any bar, reuse the measured-variable mechanism.
- **Zoom:** the library's own (`zoomIn()`, `zoomOut()`, `getScale()`), so text stays sharp, and `fitWidth()` on open (DPD-028 decision 12) so the page is readable at once on a 390px screen. Pinch is the library's if it supports it (spike), via `touch-action: pan-x pan-y pinch-zoom` on the wrapper. **Decide:** the desktop zoom FAB is a pair of icon buttons in the stage; on mobile reuse the round `.documents-mobile__zoomfabs` (bottom left, 38px, same inset) wired to the library's zoom instead of `zoomBy()`. Recommended yes, because the library zoom is the only one that keeps text crisp and double-tap is not reliable inside a scroller. Disabled at `getScale()` limits (0.1-4 as on desktop).
- **Selectable text:** a long press selects (as the deck canvas: `user-select: text`, `-webkit-touch-callout: default`). The settings text already says "select text" for Word.
- **Limits and privacy: parity with desktop.** The one options object (hyperlinks, comments, tracked changes, element selection and web fonts off; `resourceLimits` and `workerTimeoutMs` from `limits`), the 10 MB ceiling the plugin already enforces. No mobile-only cap, no new payload field; a lower cap, if ever needed, goes through `devportfolio_document_viewer_limits`.
- **Fallback:** any failure before or after the first paint (no WebAssembly, error, resource limit, timeout) destroys the viewer, empties the stage and continues with the stored pages, else "can't be previewed" with the document hint. `isBenignRenderError` behaviour as on desktop.
- **Rotation / resize:** the existing rotation fade applies; `refitOnResize` re-fits on settle. Keep scroll position if the library does; the dialog swap (desktop <-> mobile) re-mounts from the top (same as DPD-030).
- **Keys:** the scroller owns arrows, Space, `+`/`-`/`0` while focused; page-turn and zoom keys of the image viewer are not bound for it (same rule `mobile.js` uses for the grid). Esc/Close/Back and the deeplink are unchanged.
- **Scroll chaining:** `overscroll-behavior: contain` so the dialog does not scroll or pull-to-refresh behind it.
- **Chrome in landscape touch:** Close/Download float over the top corners: the wrapper gets the same top inset as the grid (reused media query), plus the safe-area insets.

## Architecture
Same principle as DPD-029/030: extract the shared mount first, then add the mobile path. The desktop `showDocx()` becomes the third `open*()` consumer.

| File | Change |
|---|---|
| `native-docx.js` (new) | **Shared mount.** `openDocx( { host, doc, limits, alive, onError, onCreate } )` -> the loaded `DocxScrollViewer` or `null`; `DOCX_LIB`/`DOCX_WASM` (`?v=OOXML_VERSION`) move here; the options object (privacy flags, `resourceLimits`, timeouts) in one place; applies `fitWidth()` after load. Same public shape as `openXlsx()`. |
| `viewer.js` | `showDocx()` becomes a thin wrapper over `openDocx()`; behaviour and zoom FAB unchanged. **Pure refactor first, its own commit.** |
| `mode.js` | `canShowOnMobile()` adds `isWord( doc )`; `nativeAllowedOnMobile()` adds `docx`; `isNativeGrid()` is generalised (**Decide**: a new `isNativeScroll( doc )` = `xlsx` or `docx` with native mode, keeping `isNativeGrid` as the xlsx-only check for the one place that needs the tab-bar logic). The mobile comment about DPD-031 is updated. |
| `mobile-viewer.js` | `show()`: pick `showGrid()` / `showScroll()` / `showNative()` by kind. Prefer **generalising `showGrid()`** (mount function and tab-bar sync passed in) over a near-copy: shared parts are the wrapper, one-piece report, details FAB, observer, fallback; differing parts are `openXlsx` vs `openDocx`, `syncBar()` (xlsx only) and the zoom FABs (docx only). `reset()` frees whichever instance is mounted. `bindGestures()` is skipped for both. |
| `mobile.js` | Verify state for a one-piece native Word document (expected no change) and that arrow/zoom keys are left to the scroller (extend the existing grid exception). |
| `style.css` | Rename or alias the grid wrapper rules to the neutral class, add `--doc` modifier (no lift variable, zoom FABs shown, `touch-action: pan-x pan-y pinch-zoom`, `overscroll-behavior: contain`, white page on the modal's own surround). No change to the routing media queries (`routing.test.mjs` stays green). |
| `render.php`, `functions.php`, plugin | No change expected: `kind`, `nativeUrl`, stored pages and `data-hint-doc` are already in the payload. |

Keep the **one gate** (`mode.js`) and the **one routing definition** (`routing.js`).

## Decisions and rationale
1. **Converted pages first.** A gate change plus words: ships value alone and is the safe default when the Word box is off.
2. **Native Word on phones is in scope, with desktop parity** (as DPD-029/030 owner decisions). Cost on a phone is higher: 2.26 MB wasm (about 0.8 MB gzip) plus the shared JS, parsed in memory; measure first on a real phone (risk 2).
3. **The scroller is not forced into the page turner.** It is one piece with its own scroll and selection, like the workbook grid, not like a deck.
4. **Library zoom, not CSS zoom**, so text stays sharp and selection stays aligned; the mobile round zoom buttons call it.
5. **`.doc` and `.odt` use stored pages only** (the native library cannot read them), as on desktop.
6. **One options object** (`native-docx.js`) so privacy and limits cannot drift between desktop and mobile; a test pins it. After this the three native mounts share a shape (`openPptx`, `openXlsx`, `openDocx`); DPD-028's deferred "one `loadNative()`" consolidation stays a separate refactor, not part of this ticket.

## Risks and open points
1. **Touch behaviour of `DocxScrollViewer` is unverified.** Inertial scroll, pinch, long-press selection, and whether it fights iOS edge-swipe-back or the dialog's Back must be settled in a **spike before step 4** on a real phone and Chrome touch emulation. If it is unusable on touch, fall back to converted pages only on mobile (**Decide** in the spike) and ship step 1-2 alone.
2. **Memory and weight on phones:** the 10 MB cap and resource limits were chosen for desktop; iOS Safari may kill the tab on a large document plus wasm. Measure 100 KB, 2 MB and 10 MB documents; lower via the existing limits filter if needed (no code change).
3. **Page-count drift:** native pagination will not match LibreOffice's `DOCX · N pp` (DPD-028 risk 2). The native mount reports one piece, so no wrong count shows; the details panel still names the converted count. Do not promise equality.
4. **Hidden content is downloaded** (comments, tracked changes, hidden text, author names are in the file); on mobile data it is also a cost. The settings text already says so.
5. **Library age (0.89.x):** tables with merged cells, text boxes, SmartArt, equations (MathJax not vendored), headers/footers approximated; the fallback bounds the damage.
6. **Selection vs scrolling:** long-press selection can start a scroll or a callout; test iOS and Android.
7. **Zoom FAB overlap:** bottom-left zoom buttons and bottom-right details button must not cover text the reader needs; confirm with a notch and in landscape.
8. **Host settings** (`.wasm` as `application/wasm`, compression) matter more on mobile networks; the vendor README lists them.
9. **No browser test runner in the repo:** pure helpers are unit-tested; DOM wiring by source-structure tests; headless Chrome touch emulation and a real phone by hand.

## Steps (one conventional commit each, scope `theme`; tests never in a feature commit; both suites green at every commit)
0. **Spike (no commit):** open a real `.docx` with the desktop `DocxScrollViewer` in a 390x844 touch-emulated dialog, then on a phone; record scroll, pinch, selection, `fitWidth()`, any bottom chrome, and what `getScale()` limits are. Decide scroller vs converted-only on mobile; update this plan.
1. `refactor(theme): extract the native Word mount from the desktop viewer` (`native-docx.js`, `viewer.js`).
2. `test(theme): pin the Word mount options and teardown` (every privacy flag off, `resourceLimits`, `?v=` URLs, `fitWidth()`, `destroy()` on reset).
3. `feat(theme): show converted Word documents in the mobile viewer` (`mode.js` gate, hint wiring).
4. `test(theme): cover the mobile Word gate and page wording` (`mode.test.mjs`, PHPUnit mobile structure test).
5. `feat(theme): draw native Word in the mobile viewer with a fallback` (generalised `showGrid()` / `showScroll()`, neutral wrapper CSS, library zoom wired to the round buttons, one-piece chrome).
6. `test(theme): cover the mobile native Word path` (`nativeAllowedOnMobile` per kind, structure and CSS).
7. `refactor(theme): remove the Office viewer aliases and leftovers` and `test(theme): follow the renamed helpers` (see "Cleanup"; the last doctype is done, so these are safe to drop now).
8. `docs(agents): document the mobile Word viewer` (Overview and mobile viewer notes, totals).
9. `docs(plans): add the DPD-031 plan` (this file, updated to "as built").

## Tests
- Node (`blocks/document-modal/tests/mode.test.mjs`): `canShowOnMobile` true for every known kind (pdf, pptx/ppt/odp, xlsx/xls/ods, docx/doc/odt) and false for `other`; `nativeAllowedOnMobile` true for pptx/xlsx/docx with a URL and false for docx without one and for doc/odt with one; `isNativeScroll`/`isNativeGrid` per kind; `hintKeyFor` unchanged (`hintDoc`).
- PHPUnit (`Devportfolio_Test_Document_Modal_Mobile_Viewer`, new `Devportfolio_Test_Document_Word` cases, `@group theme`): `mobile-viewer.js` imports `native-docx.js`; `viewer.js` uses the same mount; the options pin (no hyperlinks, comments, tracked changes, element selection or web fonts; text selection on; `resourceLimits`; timeout from `limits`); scroller mode does not call `bindGestures`/`touchZoom` and reports exactly one piece (footer and progress bar hidden); the zoom buttons call the library; CSS wrapper rules (`touch-action`, `overscroll-behavior`, landscape inset, safe areas); `.doc`/`.odt` never reach the native path; `routing.test.mjs` still green.
- Totals move from the current expected (plugin 690 (12 skipped), theme 244, JS 95, plus whatever DPD-029/030/032 added); plugin untouched.
- Manual (headless Chrome with touch emulation against `https://devportfolio.ddev.site/documents/`, 390x844 and 844x390; then a real phone; fixtures from DPB-028: one-page, 12-page with table/image/footer/landscape section, 10 MB, `.doc`, `.odt`, corrupt `.docx`, plus a PDF, `.pptx`, `.xlsx`): box off -> converted pages with page wording, swipe/edge taps/pinch/footer; box on -> scroll with a finger, readable fit-to-width on open, library zoom through the round buttons (disabled at limits), long-press selects and copies, no footer or progress bar, details FAB opens its panel; Network shows only the site's host and the DOCX wasm with `?v=0.89.7`, nothing before the modal opens, no request on tapping a link; corrupt `.docx` -> pages or "can't be previewed" with the document hint; `.doc`/`.odt` -> pages only; rotate and the 700px dialog swap while open; Back closes the modal; other kinds unchanged.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```

## Cleanup (this is the last doctype, so the compatibility layers can go)
Found by grep in `blocks/document-modal/`, `tests/` and `functions.php`; all behaviour-neutral, each with its tests updated in the same follow-up commit pair, both suites green:
1. **Aliases kept for earlier tickets:** `canDrawPresentations` (alias of `canDrawOffice`, `mode.js`, used only by `mode.test.mjs`) and `PPTX_VERSION` (alias of `OOXML_VERSION`, `native-pptx.js`; `PPTX_LIB`/`PPTX_WASM` should read `OOXML_VERSION` directly). Remove, and point the tests at the real names (the vendoring test that compares versions follows).
2. **Three inline native mounts in `viewer.js`:** after `native-docx.js`, `showPptx`/`showXlsx`/`showDocx` share the shape (import, mount, timeout, fallback, `destroyNative`). The state fields `pptx`, `docx`, `grid`, `pptxCount` and the three `reset()` branches become one `nativeView` handle. This is the consolidation DPD-028 decision 6 deferred; do it as its own `refactor` commit with no behaviour change, only if the spike shows the diff stays small. Otherwise leave it and say so in "As built".
3. **Mobile `showGrid()`/`showNative()`:** share the wrapper, one-piece report, details FAB, observer and fallback; the generalisation in Architecture removes the near-copy rather than adding a second one.
4. **`mode.js` kind lists:** `PRESENTATIONS`, `SPREADSHEETS`, `WORD` constants duplicate the PHP `devportfolio_document_file_kind()` lists. Now that all three families exist, hand them over once (`data-kinds` on the block root, as DPD-027/028 suggested) and have `mode.js` read them, so a new kind is one PHP edit. Optional, since it touches the payload; do it only if the JS tests stay pure (default lists remain as fallback).
5. **`isMobilePdf()`:** "stays a strict PDF check" for a reason that no longer exists once `canShowOnMobile()` is the only caller of kind checks. If nothing but its own test uses it, delete it.
6. **Stale comments and strings:** the "Word documents (DPD-031) have their own ticket" comments in `mode.js` and `mobile-viewer.js`, the `mode.js` header that lists only `.pptx`/`.xlsx` as native, and `AGENTS.md` sentences saying DPD-031 "will cover the rest".
7. **Out of scope on purpose:** the vendored library, plugin code, PHP helpers, and the `needs_browser` code kept for compatibility in the plugin (backend ticket, not this one).
Each removal is checked with a repo-wide grep before deleting (nothing outside tests and the file itself may reference it).

## Decisions taken (owner)
1. Native Word on phones: **yes, feature parity with desktop** (same limits, 10 MB ceiling, timeout, privacy options; no mobile-only cap).
2. Reuse the round zoom buttons for the library zoom: **yes**.
3. Generalise `showGrid()` and use a neutral wrapper class: **left to my judgement, chosen yes** (one code path and one set of CSS for the two continuous viewers; the xlsx-only tab-bar sync stays a conditional).
4. Scope: **Word only**, plus the cleanup above because it is the last doctype. No Pages sheet or other feature work.
