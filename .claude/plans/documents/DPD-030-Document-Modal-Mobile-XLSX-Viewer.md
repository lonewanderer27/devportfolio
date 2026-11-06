# Plan: Document modal, mobile body for spreadsheets (DPD-030, Modal - Mobile, Body / XLSX Viewer)

Theme: `devportfolio` (no version header to bump). **No plugin change** (DPB-027 already provides converted sheets and `cptdp_document_native_xlsx_url()`). Branch `feat/DPD-030-Document-Modal-Mobile-XLSX-Viewer`, cut from `main`.

> **Status: implemented (steps 1-7 below, in three commits), verified by the owner locally; not yet checked on a real phone.** As built: the sheet-by-sheet fallback (`XlsxSheetViewer`) was not needed, the composite grid is used. The details button stays shown (no hide-on-swipe). Our zoom buttons are not built in grid mode. Tests ride with their feature commits so every commit is green. Owner decision 1 applied: native Excel on phones is in, with parity with desktop (same limits, no mobile-only caps). The ticket text was not supplied, so the behaviour below is derived from DPD-027 (desktop XLSX viewer), DPD-021/024 (mobile PDF viewer) and DPD-029 (mobile PPTX viewer, whose "Decisions taken" names this ticket). Anything the ticket says that differs wins. Items marked **Decide** carry a recommendation; open questions are at the end.

## Goal
Today an `.xlsx`, `.xls` or `.ods` opened in the **mobile** dialog (700px or less, or a touch screen held sideways) shows "can't be previewed", because `canShowOnMobile()` in `mode.js` lets only PDFs and presentations through. Make the mobile body show spreadsheets:

1. **Converted sheets** (default; whenever DPB-027 made images): the existing mobile viewer, in **page** wording (`XLSX · 3 pp`, one page per sheet), with the same swipe, edge taps, pinch/double-tap zoom, hint, footer and round buttons as a PDF.
2. **Native viewer** (Settings -> Documents -> Native viewer -> Excel on, `.xlsx` only): the original workbook drawn by the bundled `XlsxViewer` grid (scroll, zoom, sheet tabs, cell selection and copy), **mobile-safe**, with the converted sheets as its fallback exactly as on desktop.
3. Everything else is unchanged: PDFs, presentations, and word documents, which still say "can't be previewed" until their own mobile ticket (DPD-031).

## Context (what already exists, do not rebuild)
- **Gate** (`mode.js`): `canShowOnMobile( doc )` is `pdf` or `unitOf( doc ) === 'slide'`, the one place to widen. `nativeAllowedOnMobile( doc )` is `pptx` only. `hintKeyFor()` already returns `hintSheet` for spreadsheets. `viewerMode()` is `native > images > pdf > unavailable`.
- **Mobile body** (`mobile-viewer.js`, `createMobileViewer()`): `show()` gates, tries `showNative()` when allowed, otherwise `acquireSource()` -> pages -> views -> gestures. Converted spreadsheets get their stored pages from the payload (no `viewerUrl`), so `imageSource` serves them; the plugin makes every sheet page the same 1800x1273 landscape canvas, so they render as one wide page per view with the landscape pills (`Rotate for a bigger view`, `Pinch to zoom`).
- **Desktop native grid** (`viewer.js` `showXlsx()`): `import( XLSX_LIB )`, `new lib.XlsxViewer( wrap, { wasmUrl, useGoogleFonts: false, enableHyperlinks: false, enableElementSelection: false, comments: false, hiddenSheetMode: 'skip', showZoomSlider: true, workerTimeoutMs, xlsxWorksheetLimits: { maxCells, maxRows }, onError } )`, `load( nativeUrl )`, reports one piece to `onGeometry`, `destroy()` in `reset()`, returns false to fall back. `limits.nativeMaxCells`/`nativeMaxRows` (2,000,000 / 200,000) and `nativeTimeoutMs` (30 s) already exist.
- **Shared native mount** (`native-pptx.js`): `readNativeEnvironment()`, `OOXML_VERSION`, `PPTX_LIB`/`PPTX_WASM`, and `openPptx()` as the single place that fixes the PowerPoint privacy options. There is no equivalent for the grid yet; the XLSX options live inline in `viewer.js`.
- **Mobile chrome** (`mobile.js`, DPD-017/018/019/024): footer (`Pages`, `3 / 12`, Details), progress bar, round Pages/details buttons, details panel, landscape-touch chrome. For a document that reports **one piece** the footer and progress bar are hidden and the round details button shows, which is exactly what a workbook grid needs.
- **Gestures** (`gestures.js`, `touch-zoom.js`): swipe turns, outer 20% taps turn, pinch/double-tap zoom the page frame with CSS, `touch-action: none` on the frame. These are what the grid must **not** inherit.

## Behaviour

### Converted sheets (images)
| Aspect | Behaviour |
|---|---|
| Gate | `canShowOnMobile()` also accepts `xlsx`, `xls`, `ods` (via `isSpreadsheet( doc )`). Word kinds still go to `unavailable()` with `hintDoc`. |
| Pages | One sheet per view, landscape, never a spread (`spreadFits()` needs portrait). Wording stays `page`: `Page 2 of 3`, footer label `Pages`. |
| Footer, hint, round buttons | Identical to DPD-029 converted slides (same table in DPD-021/024), only the words differ. |
| Crop | None (spreadsheets are never bottom-trimmed; `cropBottom` is 0). |
| No images | "Spreadsheets can't always be previewed here. Download the file to open it in Excel." (`data-hint-sheet`), Download works. |

### Native grid (`.xlsx`, setting on)
- Wins over the stored sheets (`viewerMode() === 'native'`), loaded with `import()` only for that document, never at page load; needs `canDrawOffice()` (WebAssembly, Worker, 2D canvas), else the stored sheets.
- **Mount.** The grid fills the page area in its own wrapper `.documents-mobile__grid`, **not** inside the swipe/zoom frame. A grid has its own scroll, pinch and tap semantics (cell selection), so the page gestures (swipe-to-turn, edge taps, double-tap zoom, round previous/next hints) are **not bound** for it and `touch-zoom.js` is not used. The workbook reports **one piece** to `onGeometry( { pages: 1, step: 1 } )`, so the **mobile footer (Pages, position, Details) and the progress bar are hidden, as on desktop**: the library's sheet tabs are the only navigation between sheets, and a `3 / 12` position would be meaningless. This falls out of the existing `footerGone = state.pages < 2` rule in `mobile.js` `drawFooter()`, and a test pins it (grid mode never reports more than one piece). Previous/next hints are hidden too; the round **details** button is shown instead, because the footer's Details button is gone (same rule as one-page PDFs).
- **Sheets:** the library's own tab strip switches sheets. Its pieces (tab strip, zoom slider) must be touch-sized (>= 40px targets) and restyled to the greyscale tokens, scoped under `.documents-mobile__grid`. **Decide:** if the library's tabs/zoom are unusable on a 390px screen, fall back to `XlsxSheetViewer` plus the mobile footer (`Sheets`, `2 / 5`, sheet names in the live region) instead of the composite; see risk 1.
- **Zoom:** the library's own zoom (`showZoomSlider`, and `zoomIn()`/`zoomOut()` through the existing round zoom buttons `.documents-mobile__zoomfabs` from the DPD-029 follow-up), not CSS scaling, so cells stay crisp. Pinch is the library's if it supports it (verify), else the zoom buttons only.
- **Copy:** cell selection by tap/drag and the library's copy; the settings text says "copy cells", not "select text" (as on desktop).
- **Limits: parity with desktop.** Same `xlsxWorksheetLimits`, same 30 s timeout, same 15 MB file ceiling already enforced by the plugin (`cptdp_native_xlsx_max_bytes`). No mobile-only cap, no new payload field. The mount takes `caps` so a later ticket can lower them without code changes.
- **Privacy:** hyperlinks, comments, element selection and web fonts off, hidden sheets skipped (the same options object as desktop, now defined once).
- **Fallback:** failure before or after the first paint (no WebAssembly, error, resource limit, timeout) destroys the viewer, empties the stage and continues with the stored sheets, else "can't be previewed" with the sheet hint.
- **Rotation / resize:** the existing rotation fade applies; on settle the grid is told to resize (`autoResize`, or a `resize()` call) and keeps its sheet, scroll and zoom. The mobile dialog <-> desktop dialog swap (View Transition) re-mounts the grid on the other side (same document, sheet is not preserved; **Decide** whether to keep the sheet index through the swap, recommended no for v1).
- **Keys:** the grid owns arrow keys and `+`/`-`/`0` while focused (the page-turn and zoom keys of the image viewer are not bound for it); Esc/Close/Back behaviour and the deeplink are unchanged.
- **Scroll chaining:** the dialog body must not scroll or pull-to-refresh behind the grid (`overscroll-behavior: contain`, `touch-action` per the library's needs).

### Chrome and FAB layout (checked against the current code)
Findings from reading `style.css`, `mobile.js` and the library: the grid is **not** safe to drop into the stage as is. Two overlays already sit in the stage's bottom corners and the library draws its own chrome there.

- **The library's chrome:** `XlsxViewer` appends a full-width **tab bar, 30px high, at the bottom of the grid** (`Rr = 30`): `◀ ▶` nav buttons (50px wide) at the left, the sheet tabs in the middle, the zoom slider/control at the right end. This is the sheet navigation, so it must stay fully visible and tappable.
- **Portrait (<= 700px):** the footer is hidden (one piece), so the grid gets the full body height. But `.documents-mobile__fabs` (round details button, `bottom: 12px`, right) and `.documents-mobile__zoomfabs` (zoom +/-, `bottom: 12px`, left) are absolutely positioned **over the stage's bottom corners**, i.e. exactly over the tab bar: the zoom buttons would cover the `◀ ▶` nav and first tab, the details button would cover the library's zoom control. **Conflict.**
- **Landscape touch (no header or footer):** Close (top left) and Download (top right) float **over the grid's top corners** (column/row headers and first cells); the Pages button (not shown for one piece) and details button sit bottom right and zoom +/- bottom left, again over the tab bar and zoom control. The bar is only 30px of a ~390px-high screen, so it also eats a lot of height. **Conflict.**
- **Gestures:** `.documents-modal__stage { touch-action: none }` under `(pointer: coarse)` (and `.documents-mobile__frame`) would stop the grid from scrolling with a finger. **Conflict**; the grid wrapper must set its own `touch-action` (what the library needs, verified in the spike).
- **Redundant zoom:** the library already has a zoom control in the tab bar, so our `.documents-mobile__zoomfabs` would be a second control for the same thing.

**Resolution (grid mode adds `.documents-mobile__stage--grid` on the stage; nothing changes for pages/slides):**
1. **Our zoom +/- buttons are not built in grid mode.** Zoom is the library's (tab bar slider, plus pinch if the spike shows it works). This removes the bottom-left overlap in both orientations. (If the spike shows the library's slider is unusable at 30px, we make the tab bar >= 40px high through CSS instead of reintroducing our buttons; **Decide** in the spike.)
2. **The round details button moves clear of the tab bar:** in grid mode `.documents-mobile__fabs` is offset by the bar height, `bottom: calc( var( --documents-grid-bar, 30px ) + 12px )` (the variable is set from the measured `.xlsx-tab-strip` parent height after mount, so a restyled bar needs no CSS change), keeping its right inset and the 38px size. It floats over the grid's cells, not over the bar, in portrait and landscape. It still hides on swipe down/returns on swipe up only if the grid reports scroll direction; otherwise it stays (**Decide** in the spike: simplest is to leave it shown).
3. **Landscape Close/Download:** in grid mode the grid wrapper gets a top inset equal to the round buttons' row (`12px + 38px + 8px`) so the column headers are never under them, **only in landscape touch** (same media query as the existing chrome rules); in portrait the header is a real bar and needs no inset.
4. **Safe areas:** the grid wrapper adds `padding-bottom: env( safe-area-inset-bottom )` so the tab bar is not under the home indicator, and the side insets match the 12px/inset used by the round buttons in landscape (notch).
5. **Touch targets:** the library's `◀ ▶`, tabs and slider thumb are scoped-restyled to >= 40px (bar height 40px on coarse pointers); the 30px default is too small for a thumb.
6. **Gesture isolation:** grid mode binds none of `bindGestures()`, `touch-zoom` or the edge-tap zones, and the wrapper sets `touch-action` per the spike (`pan-x pan-y pinch-zoom` if the library scrolls natively, `none` if it handles pointers itself); `overscroll-behavior: contain` stops the dialog scrolling behind it.

Layout summary:
```
PORTRAIT (<=700px)                      LANDSCAPE touch (no header/footer)
+--------------------------+           +----------------------------------------------+
| header: Close  title  DL |           | (Close)  top inset keeps headers clear  (DL) |
+--------------------------+           |  grid grid grid grid grid grid grid          |
|  grid (library canvas)   |           |  grid grid grid grid grid grid grid      (i) | <- details FAB above the bar
|                      (i) | <- FAB    +----------------------------------------------+
+--------------------------+  above    | < > | Sheet1 | Sheet2 | ...      zoom ----o  | <- library tab bar, untouched
| < > | Sheet1 | Sheet2 |zm|  the bar  +----------------------------------------------+
+--------------------------+
(no mobile footer, no progress)         (no mobile footer, no progress, no zoom FABs)
```

## Architecture
Same principle as DPD-029: extract rather than copy a second time. The grid code (about 60 lines) is in `viewer.js` with its options inline.

New or changed files in `blocks/document-modal/`:

| File | Change |
|---|---|
| `native-xlsx.js` (new) | **Shared mount.** `openXlsx( { host, doc, limits, alive, onError, onCreate } )` -> the loaded `XlsxViewer` or `null`; `XLSX_LIB`/`XLSX_WASM` (`?v=OOXML_VERSION`), the options object (privacy flags and worksheet limits in one place). Imports `OOXML_VERSION`, `readNativeEnvironment`, `withTimeout`. Public shape free of Excel-only words so a DOCX mobile mount can follow the same pattern. |
| `viewer.js` | `showXlsx()` becomes a thin wrapper over `openXlsx()`; behaviour and zoom FAB unchanged. **Pure refactor first, its own commit.** (`XLSX_LIB`/`XLSX_WASM` constants move out.) |
| `mode.js` | `canShowOnMobile()` adds spreadsheets; `nativeAllowedOnMobile()` becomes per-kind (`pptx` or `xlsx` with a non-empty `nativeUrl`); `viewerMode()` stays the single decision. `isMobilePdf()` stays a strict PDF check. |
| `mobile-viewer.js` | `show()`: pick `showNative()` (slides) or the new `showGrid()` by `doc.kind`. `showGrid()` mounts `openXlsx()` into `.documents-mobile__grid` **outside** the gesture frame, reports one piece, wires the round details/zoom buttons, observes the stage for resize, and `reset()` calls its `destroy()`. `bindGestures()` is skipped in grid mode. |
| `mobile.js` | Verify the footer/round-button state for a one-piece native document (expected to need no change: `footerGone` is already true for `pages < 2`); wording (`Sheets`) is not needed in the composite case. |
| `style.css` | `.documents-mobile__grid` (fills the page area, `overflow: hidden`, white page, `overscroll-behavior: contain`, own `touch-action`), `.documents-mobile__stage--grid` rules (details FAB lifted by `--documents-grid-bar`, no zoom FABs, landscape top inset, safe-area padding) and scoped touch-size/greyscale overrides for the library's tab bar and zoom control (see "Chrome and FAB layout"). No routing media-query change (`routing.test.mjs` stays green). |
| `render.php`, `functions.php`, plugin | No change expected: `kind`, `nativeUrl`, stored pages and `data-hint-sheet` are already in the payload. |

Keep the **one gate** (`mode.js`) and the **one routing definition** (`routing.js`); no new media query.

## Decisions and rationale
1. **Converted sheets first.** A gate change plus words: it ships value on its own and is the safe default when the Excel box is off.
2. **Native grid on mobile is in scope, with desktop parity** (as DPD-029 decision 2). Performance is the price the owner already accepted on desktop; on a phone it is felt more (about 0.7 MB gzip of wasm plus the shared JS and a whole workbook parsed in memory), so risk 2 is the first thing to measure on a real phone.
3. **The grid is not forced into the page-turner.** Swipe/edge-tap/CSS-zoom would fight the grid's scroll and selection. It gets its own mount and one-piece chrome; this is the main difference from DPD-029.
4. **Library zoom, not CSS zoom**, so text stays sharp (unlike PDF pinch, a known limit of DPD-021).
5. **`.xls` and `.ods` use stored sheets only** (the native library cannot read them), same as desktop.
6. **One shared options object** (`native-xlsx.js`) so privacy flags cannot drift between desktop and mobile; a test pins it.

## Risks and open points
1. **Touch behaviour of the library grid is unverified.** The composite `XlsxViewer` targets mouse + keyboard on desktop; whether it scrolls with a finger (inertia, `touch-action`), supports pinch, selects cells by tap/long-press and gives usable tab targets must be settled in a **spike before step 3** on a real phone (and Chrome touch emulation). Outcome decides between the composite grid and the `XlsxSheetViewer` + footer fallback in the Decide above.
2. **Memory and weight on phones:** the 15 MB / 2M-cell ceilings were chosen for desktop. iOS Safari may kill the tab on a large workbook plus the wasm. Measure 1, 5 and 15 MB workbooks; if needed lower `nativeMaxCells` for coarse pointers through the existing `devportfolio_document_viewer_limits` filter (no code change).
3. **Hidden content is downloaded** (as desktop risk 3): the visitor receives the whole `.xlsx`, hidden sheets and comments included; on mobile data this is also a cost. The settings text already says so.
4. **Library age** (0.89.x): charts, conditional formats, pivots approximated; no recalculation (cached values). Fallback bounds the damage.
5. **Gesture conflicts at the edges:** the dialog's Back/Close, iOS edge-swipe-back and the grid's horizontal scroll compete near the screen edge; keep the 8px dead strip and test.
6. **Landscape-touch chrome and FAB overlap:** resolved by design in "Chrome and FAB layout"; must be confirmed on a real phone in both orientations and with a notch (the tab bar, the details FAB and the Close/Download buttons must never overlap, and the first column header must stay readable).
7. **Host settings** (`.wasm` as `application/wasm`, compression) matter more on mobile networks; the vendor README already lists them.
8. **Overlap with DPD-031** (DOCX mobile): both widen `canShowOnMobile()` and want a native mount; `native-xlsx.js` is written so a DOCX one is a copy of the shape, not of the code.

## Steps (one conventional commit each, scope `theme`; tests never in a feature commit; both suites green at every commit)
0. **Spike (no commit):** open a real `.xlsx` with the desktop `XlsxViewer` in a 390x844 touch-emulated dialog, then on a phone; record scroll, pinch, tab targets, selection and copy. Decide composite vs sheet viewer; update this plan.
1. `refactor(theme): extract the native Excel mount from the desktop viewer` (`native-xlsx.js`, `viewer.js`).
2. `test(theme): pin the Excel mount options and teardown` (every privacy flag off, worksheet limits wired, `?v=` URLs, `destroy()` on reset).
3. `feat(theme): show converted spreadsheets in the mobile viewer` (`mode.js` gate, hints; wording check in `mobile.js`).
4. `test(theme): cover the mobile spreadsheet gate and page wording` (`mode.test.mjs`, PHPUnit mobile structure test).
5. `feat(theme): draw native Excel in the mobile viewer with a fallback` (`showGrid()`, `.documents-mobile__grid`, one-piece chrome).
6. `test(theme): cover the mobile native Excel path` (`nativeAllowedOnMobile` per kind, structure and CSS).
7. `docs(agents): document the mobile spreadsheet viewer`.
8. `docs(plans): add the DPD-030 plan` (this file, updated to "as built").

## Tests
- Node (`blocks/document-modal/tests/mode.test.mjs`): `canShowOnMobile` per kind (pdf/pptx/ppt/odp/xlsx/xls/ods true; docx/doc/odt false), `nativeAllowedOnMobile` (pptx and xlsx with URL true; xlsx without URL, xls/ods with a URL, docx false), `hintKeyFor` unchanged (`hintSheet`).
- PHPUnit (`Devportfolio_Test_Document_Modal_Mobile_Viewer` and `Devportfolio_Test_Document_Spreadsheets`, `@group theme`): `mobile-viewer.js` imports `native-xlsx.js`, the options pin (no hyperlinks, comments, element selection, web fonts; hidden sheets skipped; limits from `limits`), `viewer.js` uses the same mount, grid mode does not call `bindGestures`/`touchZoom` and builds no zoom FABs, grid mode reports exactly one piece (footer and progress bar hidden), the grid wrapper sets `touch-action`/`overscroll-behavior`, the stage-grid CSS lifts `.documents-mobile__fabs` by the bar variable and insets the landscape top and the safe areas, word kinds still reach `unavailable()`, CSS wrapper rules, `routing.test.mjs` still green.
- Expected totals move from theme 244 / JS 95 (plus whatever DPD-029 and DPD-028 added); plugin untouched.
- Manual (no browser runner; headless Chrome with touch emulation against `https://devportfolio.ddev.site/documents/`, 390x844 and 844x390; then a real phone): DPD-027 fixtures (multi-sheet `.xlsx` with a hidden sheet, one-sheet, wide, large, `.xls`, `.ods`, corrupt `.xlsx`) plus a PDF and a `.docx`. Check (portrait and landscape, with a notch/safe-area device or emulation): no mobile footer or progress bar over a native workbook; tab bar fully visible and tappable (nav, tabs, zoom) and not covered by any round button; details FAB sits above the bar and opens its panel; Close/Download do not cover the column headers; grid scrolls with a finger and the dialog does not scroll behind it; converted sheets open with page wording and the landscape pills; box on -> grid, scroll, zoom, sheet tabs, cell copy; hidden sheet not shown; Network shows only the site's host and the wasm with `?v=0.89.7`; corrupt workbook -> sheets or "can't be previewed" with the sheet hint; rotate and the 700px dialog swap while open; Back closes the modal; `.docx` still unavailable.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```

## Questions for the owner
1. ~~Is native Excel on phones in?~~ **Decided: yes, parity with desktop** (see below).
2. If the library grid is poor on touch, accept the `XlsxSheetViewer` + footer fallback (sheet-by-sheet, no scroll inside the page)?
3. Keep the sheet through the desktop/mobile dialog swap, or reopen on the first sheet?
4. Does the ticket also cover `.docx` on mobile? (Plan assumes no: DPD-031.)

## Decisions taken (owner)
1. Native Excel on phones: **yes**, with **parity with desktop**: same worksheet limits (2,000,000 cells, 200,000 rows), 30 s timeout and 15 MB file ceiling; no mobile-only cap, no new payload field. Risk 2 stays a measurement task, not a code change (a lower cap, if ever needed, goes through the existing `devportfolio_document_viewer_limits` filter).
