# Plan: Document modal, mobile body for presentations (DPD-029, Modal - Mobile, Body / PPTX Viewer)

Theme: `devportfolio` (no version header to bump). **No plugin change** (DPB-026 already provides converted slides and `cptdp_document_native_pptx_url()`). Branch `feat/DPD-029-Document-Modal-Mobile-PPTX-Viewer`, cut from `main`.

> **Status: implemented (steps 1-4 of the commit list, merged where noted), not yet checked in a browser or on a real phone.** Deviations from the draft: `isMobilePdf()` stays a strict PDF check and `canShowOnMobile()` is new (no alias); `openPptx()` has no `caps` parameter (parity: the desktop literals are used by both); the extraction commit also updated the tests' `read()` helpers to include `native-pptx.js`; the feature commit carries its tests so every commit is green; no `render.php`/`functions.php`/`style.css` routing change (only a `.documents-mobile__native` rule).
>
> **Draft note:** owner decisions applied. Decided: native PowerPoint on phones is in; limits are the same as desktop (no mobile-specific file-size cap); long-press-to-select on the canvas is accepted; XLSX and DOCX mobile are DPD-030 and DPD-031.
>
> **Original note:** The ticket text was not supplied, so the behaviour below is derived from DPD-021/024 (mobile PDF viewer), DPD-026 (desktop PPTX viewer) and the "How to extend this" note in `DPD-021-024-Document-Modal-Mobile-Viewer.md`. Anything the ticket says that differs wins; open questions are at the end.

## Goal
Today a `.pptx`, `.ppt` or `.odp` opened in the **mobile** dialog (700px or less, or a touch screen held sideways) shows "can't be previewed", because `isMobilePdf()` in `mode.js` lets only PDFs through. Make the mobile body show presentations:

1. **Converted slides** (default; whenever DPB-026 made images): the existing mobile viewer, in **slide** wording, one wide slide per view, with the same swipe, edge taps, double-tap/pinch zoom, hint, footer and round buttons as a PDF.
2. **Native viewer** (Settings -> Documents -> Native viewer -> PowerPoint on, `.pptx` only): the original file drawn on a canvas with selectable text, **mobile-safe** (memory cap, same gestures), with the converted slides as its fallback exactly as on desktop.
3. Everything else is unchanged: PDFs, and XLSX/DOCX which still say "can't be previewed" until their own mobile tickets.

## Context (what already exists, do not rebuild)
- **Routing and chrome** (`routing.js`, `mobile.js`): which dialog, footer (`Pages`, `3 / 12`, `Details`), round buttons, progress bar, details panel. Presentations already get slide wording from `unitOf()`/`wording()`/`data-units`, and `data-mobile-strings` exists.
- **Body** (`mobile-viewer.js`, `createMobileViewer()`): `show( doc, page )` rejects non-PDFs at the gate, then `acquireSource( doc, alive, { pdfjs, limits, selectableText } )` -> pages -> views -> gestures. A landscape source (`orientationOf( source.ratio, 1 ) === 'landscape'`) already gets one wide page and the pills `Rotate for a bigger view` / `Pinch to zoom`. Decks are landscape, so most of the layout work is already done.
- **Sources** (`page-source.js`): `imageSource` works for any `doc.images`; the payload already gives presentations their stored slides, no `viewerUrl`, so `acquireSource` returns images or nothing (pdf.js is never tried).
- **Native desktop viewer** (`viewer.js` `showPptx()`, lines ~544-655): `import( PPTX_LIB )`, `new lib.PptxViewer( canvas, { wasmUrl, width, dpr, enableTextSelection: true, hyperlinks/media/selection/googleFonts off, workerTimeoutMs, onSlideChange, onError } )`, `load( nativeUrl )`, `goToSlide()`, fit with `fitPage`, `destroy()` on reset, returns false to fall back. `mode.js` has `viewerMode()` (`native` > `images`) and `canDrawOffice()`; `touch-zoom.js` and `gestures.js` are the shared pinch/pan/swipe pieces.
- `hintKeyFor()` already returns `hintSlide` for presentations.

## Behaviour

### Converted slides (images)
| Aspect | Behaviour |
|---|---|
| Gate | `isMobilePdf()` becomes a per-kind check (`canShowOnMobile( doc )`): `pdf`, `pptx`, `ppt`, `odp`. xlsx/xls/ods and docx/doc/odt still go to `unavailable()` with their hint. |
| Pages | One slide per view; a deck is landscape so it never spreads (`spreadFits()` needs a portrait ratio). Slide ratio 16:9 or 4:3 comes from the stored images. |
| Footer | Portrait, 2+ slides: footer `Slides`, `3 / 12`, Details, progress bar. One slide: no footer, no progress bar, details round button. Landscape touch: no header or footer, floating Close/Download, round Pages and details buttons. Same table as DPD-021/024; only the words change (`Slide 3 of 12`, `Previous slide`, footer label `Slides`). |
| Hint | A landscape slide in a portrait phone shows `Rotate for a bigger view` + `Pinch to zoom` (already built for landscape documents); held sideways it shows `swipe or tap edges`. |
| Crop | None (presentations are never bottom-trimmed; the viewer already ignores `cropBottom` of 0). |
| No images | "Presentations can't always be previewed here. Download the file to open it in PowerPoint." (`data-hint-slide`), Download works. |

### Native viewer (`.pptx`, setting on)
- Wins over the stored slides (`viewerMode() === 'native'`), loaded with `import()` only for that document, never at page load; needs `canDrawOffice()` (WebAssembly, Worker, 2D canvas), else the stored slides.
- One canvas fitted to the stage width (`fitPage`), transparent text layer, so long-press selects and copies text. Hyperlinks, media, element selection and web fonts stay off (the test that pins this on desktop must also cover the mobile mount).
- **Gestures on the canvas:** the same as the image viewer, through the shared `touch-zoom.js`/`gestures.js` bound to the canvas wrapper. Swipe and edge taps call `goToSlide()` (no slide-in animation, since the canvas is redrawn in place; a short fade under `prefers-reduced-motion: no-preference`). Pinch/double-tap zoom the **canvas with CSS** (the same limit as pdf.js on mobile, known limit 2 of DPD-021).
- **Limits: parity with desktop.** Same render rules as `showPptx()` (width = stage fitted at 16:9, 640-1920 px, `dpr` capped at 2, `workerTimeoutMs` = `limits.nativeTimeoutMs`) and the same 30 MB file ceiling already enforced by the plugin (`cptdp_native_pptx_max_bytes`). **No new limit, no `nativeBytes` payload field, no mobile-only filter.** The shared mount still takes `caps` so a later ticket can lower them without code changes.
- **Fallback:** failure before or after the first slide destroys the viewer, empties the stage and continues with the stored slides, else "can't be previewed" (same contract as desktop `showPptx()`).
- **Rotation:** the existing rotation fade applies; on settle the canvas is refitted (not redrawn at a new resolution unless the width changed by more than 25%).
- Keys: arrows turn, `+`/`-`/`0` zoom, announced in the live region.

## Architecture

The desktop `showPptx()` is already ~110 lines inside the 960-line `viewer.js` (known limit 6 of DPD-021 says to split before the next ticket touches it). This ticket does that split rather than copying the code a third time.

New or changed files in `blocks/document-modal/`:

| File | Change |
|---|---|
| `native-pptx.js` (new) | **Shared mount.** `mountPptx( { stage, doc, limits, words, alive, env, caps, onGeometry, onSlideChange, onError } )` -> `{ goTo( n ), count, refit(), destroy() }` or `null` (cannot draw). Holds the options object (the privacy flags in one place), `PPTX_LIB`/`PPTX_WASM` URLs and `withTimeout`. `caps` is `{ maxWidth, maxDpr }`, the same 1920/2 for desktop and mobile. |
| `viewer.js` | `showPptx()` becomes a thin wrapper over `mountPptx()`; behaviour and zoom FAB unchanged. Pure refactor first, its own commit. |
| `mobile-viewer.js` | `show()`: replace the `isMobilePdf` gate with `canShowOnMobile`; if `viewerMode( doc ) === 'native'` and the mobile limits allow, try `showNative()` (mounts `mountPptx` into a `.documents-mobile__native` wrapper, binds `touch-zoom` and swipe, reports `onGeometry`), else the existing image path. `reset()` also calls the native `destroy()`. |
| `mode.js` | `canShowOnMobile( doc )` (replaces `isMobilePdf`, keep the old name as an alias for one release so existing tests and imports keep working), `nativeAllowedOnMobile( doc )` (pure: kind `pptx` and a non-empty `nativeUrl`; `viewerMode() === 'native'` stays the single decision). |
| `render.php`, `functions.php` | No change expected: `kind` and `nativeUrl` are already in the payload. |
| `style.css` | `.documents-mobile__native` (centred, fits the page area, `touch-action: none` on the wrapper like the page frame, canvas `max-width: 100%`). No change to routing media queries. |
| `status.js` | No change expected (hint key already exists). |

Keep the **one gate** (`mode.js`) and the **one routing definition** (`routing.js`); no new media query.

## Decisions and rationale
1. **Converted slides first.** It is mostly a gate change because DPD-021/024 already handle landscape pages, wording and the footer; it ships value on its own and is the safe default when the native box is off.
2. **Native viewer on mobile is in scope, with desktop parity.** Same opt-in setting, same limits, same fallback to the slides. Performance is the price the owner already accepted on desktop; on a phone it is felt more (about 0.55 MB gzip plus the 2 MB wasm and a canvas of up to ~2.6 MP), so risk 2 below is the first thing to measure on a real phone.
3. **No new gestures library or flip animation** (consistent with DPD-021 decision 7).
4. **Pinch zoom is CSS-scaled**, so text is not re-rendered sharper at 4x (same known limit as PDFs). A re-render at the zoomed width (debounced) is a possible later improvement, not part of this ticket.
5. **`.ppt` and `.odp` use stored slides only** (the native library cannot read them), same as desktop.

## Risks and open points
1. **No real-device check yet** (as for DPD-021): iOS Safari memory with a large wasm + canvas, long-press text selection on the native canvas, and a real foldable. Check these first.
2. **Memory:** up to three decoded views for images (existing budget) vs one live canvas for native. The native path should destroy the viewer on close and on kind change; test for `destroy()` being called on `reset()`.
3. **Gesture conflicts on the native canvas:** the text layer wants long-press and drag to select, while swipe and pan want drags. Proposed rule: a drag that starts on text only selects after a 400 ms hold, otherwise it is a swipe; unzoomed horizontal drags turn, zoomed drags pan. This needs a hands-on pass and may need a "select text" affordance if it feels wrong.
4. **Library age** (0.89.x, no animations, equations as placeholders, charts approximated): unchanged from DPD-026; the fallback to slides bounds the damage.
5. **Host settings** (`.wasm` served as `application/wasm`, compression) matter more on mobile networks; the vendor README already lists them.
6. **Overlap with the sibling tickets:** DPD-030 (XLSX) and DPD-031 (DOCX) on mobile will widen the same gate and reuse `native-pptx.js`'s structure (a generic `mountNative( kind )` is the natural next step); keep `mountPptx` free of PPTX-only assumptions in its public shape.

## Steps (one conventional commit each, scope `theme`; tests never in a feature commit; both suites green at every commit)
1. `refactor(theme): extract the native PowerPoint mount from the desktop viewer` (`native-pptx.js`, `viewer.js`).
2. `test(theme): pin the mount options, caps and teardown` (structure test that every privacy flag is `false`, `destroy()` on reset, `?v=` URLs).
3. `feat(theme): show converted presentations in the mobile viewer` (`mode.js` gate and hints, `mobile-viewer.js` gate, wording check in `mobile.js`).
4. `test(theme): cover the mobile gate and slide wording` (`mode.test.mjs`, PHPUnit mobile structure test).
5. `feat(theme): draw native PowerPoint in the mobile viewer with a fallback` (`showNative()`, CSS).
6. `test(theme): cover the mobile native path` (`mode.test.mjs` `nativeAllowedOnMobile`, PHPUnit structure and CSS).
7. `docs(agents): document the mobile presentation viewer`.
8. `docs(plans): add the DPD-029 plan` (this file, updated to "as built").

## Tests
- Node (`blocks/document-modal/tests/mode.test.mjs`): `canShowOnMobile` per kind (pdf/pptx/ppt/odp true; xlsx/docx false), `isMobilePdf` alias, `nativeAllowedOnMobile` (kind, URL present or missing), `hintKeyFor` unchanged.
- PHPUnit (`Devportfolio_Test_Document_Modal_Mobile_Viewer` and `Devportfolio_Test_Document_Presentations`, `@group theme`): the mobile viewer source imports `native-pptx.js`, the options pin (no hyperlinks, media, selection, web fonts), same caps as desktop, non-PPTX kinds still reach `unavailable()`, CSS wrapper rules.
- Expected totals move from theme 244 / JS 95; plugin 690 untouched.
- Manual (no browser runner; headless Chrome with touch emulation against `https://devportfolio.ddev.site/documents/`, 390x844 and 844x390): fixtures from DPD-026 (real deck, one-slide deck, 4:3 deck, `.ppt`, `.odp`, corrupt `.pptx`) plus a PDF and an `.xlsx`. Check: converted deck opens with slide wording and the landscape pills; swipe, edge tap, pinch, double-tap; box on -> canvas, text selectable, Network shows only the site's host and the wasm with `?v=0.89.7`; corrupt deck -> slides or "can't be previewed" with the slide hint; rotate while open (fade, same slide); `.xlsx` still unavailable.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```

## Decisions taken (owner)
1. Native PowerPoint on phones: **yes**.
2. File-size and render limits: **parity with desktop**.
3. Long-press to select text, swipe to turn on the native canvas: **accepted** (still needs a hands-on pass, risk 3).
4. Sibling tickets: **DPD-030** Modal - Mobile, Body / XLSX Viewer; **DPD-031** Modal - Mobile, Body / DOCX Viewer. Each widens `canShowMobile()` for its kinds and reuses the shared native mount.
