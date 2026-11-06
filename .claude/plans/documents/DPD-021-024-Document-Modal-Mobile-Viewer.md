# Plan (as built): Document modal, mobile body and landscape (DPD-021 body / PDF viewer, DPD-024 landscape support)

Theme: `devportfolio` (no version header to bump). No plugin change. Branch `feat/DPD-021-024-Document-Modal-Mobile-Viewer`.

> **Status: implemented, uncommitted, awaiting the owner's hands-on review.** This document describes what was **built**. The earlier draft of this plan made several decisions that the owner changed during review; they are listed under **What changed from the first draft** so nobody re-implements them by mistake. Checked with headless Chrome (touch emulation) against real PDFs; **not yet checked on a real phone or a real foldable**.

> ## PDF documents only, for now
> The mobile body, the landscape layout and the adaptive spread are built and tested for `doc.kind === 'pdf'`. **Separate tickets will be created for PPTX, DOCX and XLSX on mobile.** Until then a non-PDF document opened in the mobile dialog shows "can't be previewed" with its kind-specific hint (`hintKeyFor()`), and Download still works. The gate is one function: **`isMobilePdf( doc )` in `mode.js`**; each later ticket widens it in that one place.

## Tickets (verbatim intent)

**DPD-021, Modal - Mobile, Body / PDF Viewer** (Medium; device width 700px or less; a document with at least 3 pages): one page at a time fitted to the width on light grey; light round hints on the left and right edges, tapping an edge also turns the page; swipe left = next, swipe right = previous; double-tap or pinch zooms; the backward hint is hidden on the first page and the forward hint on the last; the text `swipe or tap edges` at the bottom of the page area.

**DPD-024, Modal - Mobile, Viewer Landscape Support** (Low; depends on DPD-013): when the device is rotated, the viewer shows 2 pages as a book, backward/forward turn two pages, swiping works; a landscape document shows the pills `Rotate for a bigger view` (dark, with a rotate icon) and `Pinch to zoom` (lighter) below the page; single-page documents show no pager or thumbnails; pinching zooms.

## Behaviour (what the user sees)

### Which dialog a screen gets
Defined **once**, in `routing.js` (`createRouting()`):

| Screen | Dialog |
|---|---|
| 700px wide or less | **mobile** |
| Touch screen held sideways: `(pointer: coarse) and (orientation: landscape) and (max-height: 520px)` | **mobile** (even when wider than 700px) |
| Everything else (desktop, tablet, unfolded foldable, mouse windows) | **desktop** |

`view.js` and `mobile.js` import the queries; `style.css` repeats the same two media queries and `tests/routing.test.mjs` fails if they differ; `view.js` `enforceSingleDialog()` closes the wrong dialog if both were ever open. Rotating or resizing while a document is open swaps dialogs in place (same document, page and URL) inside a View Transition. The 700px rule of DPD-017 is therefore **extended**, not replaced.

### Mobile dialog states

| State | Header | Footer | Progress bar | Round buttons |
|---|---|---|---|---|
| Portrait, 2+ pages | title, Close, Download | Pages, `3–4 / 24`, Details | shown | none |
| Portrait, 1 page | title, Close, Download | **hidden** | **hidden** | details ("i") |
| Landscape (touch), 2+ pages | **none** | **hidden** | shown | Pages **above** details ("i"), bottom right |
| Landscape (touch), 1 page | **none** | **hidden** | **hidden** | details ("i") |

- **Landscape controls:** Close floats at the top left and Download at the top right as round buttons; the title stays in the DOM for screen readers. The previous/next hints, Close, Download and the round buttons are all 38px with a 12px side inset, so they line up in two columns.
- **Details button and panel:** the round "i" button opens a panel with the document's **title** (the only place it shows in landscape), description, software tools and format. It is visible **only when the footer is not** (one page, or landscape). The footer's own Details button toggles the same panel. The round buttons hide on a swipe down and return after a swipe up, or after 2 s without one.
- **Pages button:** in the footer in portrait, a round button in landscape; it emits `documents:mobile-pages`. **Nothing opens yet** (the Pages sheet is a later ticket). Hidden for one-page documents.

### The page area
- **One page, or two when the space allows it.** A portrait document with 2+ pages shows a spread when `stageW >= 2 * stageH * ratio` (`spreadFits()` in `layout.js`): each page of the spread is then as large as a single page would be. Examples: a phone in portrait shows one page, a phone in landscape two, a near-square foldable inner screen one larger page. Landscape documents and one-page documents never spread. Re-checked on every resize, so rotation, folding and split screen re-lay out in place and keep the page (a spread always starts on an odd page). **It follows the space, not a breakpoint.** No fold shading is drawn in a mobile spread.
- **Sources (parity with desktop):** the same order and gate as the desktop book (`page-source.js`): pdf.js with a real text layer when Native viewer > PDF is on, stored page images, pdf.js fallback behind `capability.js`; the bottom crop is honoured. StPageFlip is **never loaded** on mobile; the page turn is a slide that follows the finger (reduced motion: instant). A 3D page-flip was considered and left out to keep the viewer light; a CSS page turn is the proposed option if it is wanted later.
- **Hint:** `swipe or tap edges` floats under the document (plain text). For a landscape document the pills `Rotate for a bigger view` and `Pinch to zoom` replace it. It disappears at the **first action in the body** (any touch, turn, zoom or key).
- **Rotation fade:** when the size starts changing the pages fade out (120 ms); once the size has been still for 250 ms the layout is rebuilt and the pages fade in (260 ms) after they are drawn (waits at most 1.5 s). None with reduced motion. Applies to the mobile frame and to the desktop book.

### Gestures and keys

| Input | Effect |
|---|---|
| Swipe left / right | next / previous view (a view is 1 page, or 2 in a spread) |
| Tap outer 20% of either side (8px dead strip at the screen edge) | previous / next |
| Round edge hints | previous / next; hidden at the ends and while zoomed |
| Double-tap (centre 60%) | zoom 2.5x, again to 1x |
| Pinch | zoom 1x-4x around the fingers |
| Drag while zoomed | pan inside the page; swiping, edge taps and the hints are off while zoomed |
| Left / Right arrows | turn |
| `+` / `=`, `-`, `0` | zoom in, out, reset (accessibility; announced as `Zoom 150%` in a polite live region) |

Turning, closing, resizing and changing the spread reset the zoom to 1x.

### Desktop dialog on touch (tablets, unfolded foldables: wider than 700px and taller than 520px)
Under `(pointer: coarse)`: StPageFlip's mouse and corner events are off, `viewer.js` binds swipe (`onSwipe` in `view.js` calls `turn()`), pinch and double-tap to the book (`touch-zoom.js`), and one or two pages follow `spreadFits()`. Mouse desktops are unchanged (`stepFor()`: always a spread when portrait).

## Architecture

New or changed files in `blocks/document-modal/`:

| File | Role |
|---|---|
| `routing.js` | which dialog (queries and `createRouting()`) |
| `mobile-viewer.js` | the mobile body: views, gestures, hint, round buttons, rotation fade |
| `mobile.js` | the mobile dialog: page state, footer, Pages/Details, keys; mounts the viewer; `viewer.setChrome( { info, pages } )` says which round buttons the footer cannot show |
| `page-source.js` | **shared with the desktop book:** `imageSource`, `pdfSource`, `tryImages`, `tryPdf`, `acquireSource`, `rememberFailure`, `withTimeout`, `buildPageEls`, `mountWindow` (extracted from `viewer.js`, no behaviour change) |
| `status.js` | **shared:** loading / "can't be previewed" line (`createStatus`) |
| `touch-zoom.js` | **shared:** pinch, pan, double-tap and key-zoom state on top of `gestures.js` |
| `gestures.js` | pure maths: tap zones, swipe, vertical intent, pinch, zoom steps, pan clamp |
| `dom.js` | `el`, `svgIcon` |
| `mode.js` | `isMobilePdf()`, `hintKeyFor()` |
| `layout.js` | `spreadFits()` |
| `pager.js` | `mobileFooterParts( page, pages, step )` (`3–4 / 24`) |
| `viewer.js` | desktop book: imports the shared modules; touch handling and `spreadFits()` under coarse pointers; rotation fade |
| `view.js` | routing, swap, `enforceSingleDialog()`, `onSwipe` |
| `render.php`, `functions.php` | status element for the mobile body; `devportfolio_document_mobile_strings()` as `data-mobile-strings` |
| `style.css` | mobile body, round buttons, landscape layout, routing media queries |

## How to extend this (next tickets)

- **PPTX / DOCX / XLSX on mobile:** widen `isMobilePdf()` (rename it to a per-kind check), reuse `acquireSource()` for the converted pages and add each native viewer's mount next to the `showPptx`/`showXlsx`/`showDocx` logic; the status hints already exist per kind (`hintKeyFor()`).
- **Pages sheet and Details sheet:** the footer and landscape buttons already emit `documents:mobile-pages` / `documents:mobile-details`; drive the viewer with `mobile.setPage( n )`. The details button already opens a panel that a sheet can replace.
- **A page-turn animation (3D CSS flip):** it belongs in `slideTo()` / `finishTurn()` in `mobile-viewer.js`; keep it off under reduced motion.
- **Another round button:** add it to the `fabs` container in `buildUi()` and to `setChrome()`.
- Keep the one gate and the one routing definition; do not add a second media query anywhere without adding it to `routing.js` and its test.

## What changed from the first draft (do not reopen)

1. **Information button.** The draft's "viewer help" button (turn/zoom/keys tips) became the **details button** (title, description, tools, format). The tips text is gone; the `swipe or tap edges` hint is plain floating text that disappears at the first action.
2. **Details button visibility:** only when the footer is not shown (one page, or landscape).
3. **Footer rules:** one page hides the **whole footer and the progress bar** (DPD-019's "footer always shows" is superseded); landscape hides the whole footer.
4. **Landscape uses the mobile dialog, not the desktop one** (the draft assumed a rotated phone gets the desktop dialog). The Pages button becomes a round button above the details button; the header is removed and Close/Download float.
5. **Two pages follow the space** (`spreadFits()`), not a breakpoint or "being a phone in landscape".
6. **Feature parity with desktop** for PDFs (sources, gate, text layer, crop), by sharing code rather than copying it.
7. **No seam** between the two pages of a mobile spread; **no page-flip library** on mobile.
8. **Rotation fade** (out, wait for the rotation to settle, in).

## Commit order

Branch `feat/DPD-021-024-Document-Modal-Mobile-Viewer`, cut from `main`. One conventional commit each, scope `theme` (`docs(agents)` and `docs(plans)` for documentation), in this order. Every commit ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`; the PR description ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. **Both suites were run against each commit's own content** (everything not staged was stashed), and each is green: the shared pieces first, the desktop viewer next, and the mobile viewer together with the routing change (which sends landscape phones to the mobile dialog) after the viewer it relies on exists.

| # | Commit | Files | Suites after |
|---|---|---|---|
| 1 | `feat(theme): add the shared modules and pure helpers for the touch viewer` | `page-source.js`, `status.js`, `dom.js`, `gestures.js`, `touch-zoom.js`, `routing.js`, `layout.js` (`spreadFits`), `mode.js` (`isMobilePdf`, `hintKeyFor`), `pager.js` (`mobileFooterParts` step) | theme 231, JS 72 |
| 2 | `test(theme): cover the gesture, zoom, spread, gate and footer helpers` | `tests/gestures`, `touch-zoom`, `layout`, `mode`, `pager` | theme 231, JS 93 |
| 3 | `refactor(theme): use the shared modules in the desktop viewer and add touch to its book` | `viewer.js` (extraction, touch, spread, rotation fade), the structure tests that read `viewer.js` (`test-document-viewer`, `-presentations`, `-spreadsheets`) | theme 231, JS 93 |
| 4 | `feat(theme): add the mobile viewer for PDFs and use it for a touch screen held sideways` | `mobile-viewer.js`, `mobile.js`, `view.js`, `render.php`, `functions.php`, `style.css`, `tests/routing.test.mjs`, the two existing mobile/modal structure tests whose media-query regexes changed | theme 231, JS 95 |
| 5 | `test(theme): cover the mobile viewer, routing and landscape structure` | `tests/test-document-modal-mobile-viewer.php` | theme 244, JS 95 |
| 6 | `docs(agents): document the mobile viewer, routing and landscape layout` | `AGENTS.md` | unchanged |
| 7 | `docs(plans): add the DPD-021 and DPD-024 plan and note what it supersedes` | this plan, the note in the DPD-017/018/019 plan | unchanged |

Deviations from the first version of this table, and why: `git add -p` is interactive and was not available, so files touched by several steps (`viewer.js`, `view.js`, `style.css`, `mobile.js`) were not split by hunk. The neighbouring steps were merged instead, as this plan allowed: the viewer's extraction, touch and rotation fade are one commit (3); the mobile viewer, the landscape layout and the routing change are one commit (4), because the stylesheet and `view.js` must switch dialogs together. Not staged: `.claude/plans/design-system.html`, `.claude/plans/home/`, the two wireframes, `.claude/plans/backend/DPB-NNN-Pre-Push-Test-Gate.md`. Run the real-device checks under Known limits before opening the PR and report them in its description.

## Tests

- PHPUnit (`tests/test-document-modal-mobile-viewer.php`, `@group theme`): strings, status elements, shared sources and gate, PDF-only gate, crop and text layer, gestures, zoom keys, round buttons, details panel, routing, landscape CSS, rotation fade. Existing structure tests read `viewer.js` together with `page-source.js` and `status.js`.
- Node (`blocks/document-modal/tests/`): `gestures`, `touch-zoom`, `routing` (also checks the stylesheet repeats the queries), `layout` (`spreadFits`), `mode` (`isMobilePdf`, `hintKeyFor`), `pager` (spread footer).
- Totals at the time of writing: theme **244**, plugin **690** (12 skipped, untouched), modal JS **95**.
- No browser runner exists. Verified by hand with headless Chrome (puppeteer-core, touch emulation) against `https://devportfolio.ddev.site/documents/`: 390x844, 844x390, 700x340, 700x640, 1024x768 and rotations between them (stress test: 28 resizes with touch and mouse emulation, exactly one dialog open and visible every time).

## Known limits and open points

1. **No real-device check yet** (iOS edge-swipe Back, long-press selection with the text layer, a real foldable). Check these first.
2. **Zoom scales the drawn page**; pdf.js canvases are not re-rendered sharper at 4x.
3. **The StPageFlip touch spike was not run**; the desktop touch path uses the safer design (mouse events off, own swipe).
4. **Memory on low-end phones:** up to three views (1-2 pages each) are decoded at once; `devportfolio_document_viewer_limits()` may need a mobile cap.
5. **The Pages and Details sheets are not built**; their buttons only emit events.
6. `viewer.js` still holds the native PPTX/XLSX/DOCX viewers (about 960 lines); splitting them out is the natural first step of the next ticket that touches them.
7. Browser console: a 404 for `/favicon.ico` on the dev site is unrelated.

## Verification

```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```
Manual (390x844 and 844x390, touch): open a 10-page PDF, a 1-page PDF, a landscape PDF and a `.pptx`; swipe, tap edges, double-tap, pinch, keys; check the footer/round-button table above; rotate while open (fade, one dialog, same page); with Native viewer > PDF on, long-press to select text.
