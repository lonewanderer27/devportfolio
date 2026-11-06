# Plan: Document modal, mobile Details sheet (DPD-023, Modal - Mobile, Footer - Details)

Theme: `devportfolio` (no version header). **Theme only**, no plugin change. Branch `feat/DPD-023-Document-Modal-Mobile-Details-Sheet`, cut from `main`. Depends on DPD-019 (footer), DPD-020 (Pages sheet, which this reuses and partly refactors), DPD-021/024 (mobile viewer).

> **Status: implemented, uncommitted, owner-reviewed in the browser ("this is fire").** Theme PHPUnit (297) and modal JS (148) pass. See **As built** at the end for what differs from the plan below.

## Ticket (verbatim intent)
**DPD-023, Modal - Mobile, Footer - Details** (Medium, User Interface, depends on DPD-019; device width 700px or less). Tapping **Details** in the mobile footer opens **another modal on top: a bottom sheet over the dimmed viewer**:
- **Header:** Title (bold, serif), Close (X) button.
- **Body:** Category (outlined pill), Description, Softwares Used (pills).
- **Body, info rows, separated by thin lines:** `Format` (leading) : file type (ending); `Pages` (leading) : number (ending).
- **Footer:** Download button, full width, filled dark, with the download icon. Tapping it downloads the file.
- Closes by tapping the X, tapping the dimmed area, or swiping down.

## Owner's additional requests (this plan)
1. **Deprecate the popover that the Info FAB opens**, in portrait **and** landscape. The Details sheet replaces it everywhere; the popover code is removed, not hidden.
2. **Same implementation approach as the Pages sheet, but do not copy its code.** Extract the parts both sheets need into reusable components and refactor the Pages sheet onto them. The refactor is spelled out below (see **Refactor**), because it touches DPD-020 code and its tests.

## Context (what exists today)
- **Three entry points, one popover.** The footer `Details` button (`.documents-mobile__details`, `mobile.js` -> `viewer.toggleDetails()`), and the round Info FAB (`makeDetails()` in `mobile-viewer.js`, shown when the footer is gone: one page, or touch landscape). Both toggle `div.documents-mobile__details-panel`, a small card anchored above the FABs with title, description, tools joined by ` · ` and the format label. It has no category, no Download, no page count, no dismiss gesture.
- **The popover lives inside the viewer.** State `hasDetails`, `details`, `setDetails()`, `toggleDetails()`, the `aria-expanded`/`aria-controls` pair on the FAB, `setDetails( false )` calls in `syncControls()`, `setFabAway()` and three reset paths, plus the `.documents-mobile__details-*` CSS. The viewer therefore knows document metadata it has no business knowing.
- **The Pages sheet (DPD-020) is the model to follow**: a sheet inside the mobile `<dialog>` (not a second dialog), `div.documents-mobile__dim` + `div.documents-mobile__sheet`, `panel.inert = true` while open, grab handle and title row drag down to dismiss (`dragOutcome()`, 96px or a 0.6px/ms flick), dim-layer click, Esc intercepted through the dialog's `cancel`, focus returned to the opener, one history entry so Back closes only the sheet (`sheet-back.js`), loaded with `import()` on the first tap, reduced motion honoured, safe-area bottom padding, landscape cap (80%).
- **Reusable already:** `download.js` (`applyDownload`, `guardDisabledDownload`), `dom.js` (`el`, `svgIcon`), `sheet-back.js` (generic: takes `isOpen`/`onBack`), `mode.js` (`unitOf`, `wording().many` = `Pages` / `Slides`), the `--dm-*` tokens and the `.documents-mobile__sheet*` / `__grab` / `__dim` CSS (all already neutral, nothing in them is Pages-specific except `--pg-*` custom properties and the `sheet-body` row/tile rules).
- **What the payload lacks:** the category name and a bare file type. `devportfolio_document_modal_payload()` has `format` (`PDF · 24 pp`, so the type and the count are already fused), `pages`, `tools`, `description`, `title`, `downloadMode`/`downloadUrl`, `kind`. The category is computed inline in `blocks/document-book/render.php` and nowhere else.
- **Footer button:** `Details` with a chevron-down icon (suggests an in-place expansion, which is what the popover was). It stays; see Open question 3.

## Behaviour

| Aspect | Behaviour |
|---|---|
| Container | A bottom sheet **inside the mobile `<dialog>`**, same construction as the Pages sheet (dim layer + `role="dialog" aria-modal="true"` sheet, `aria-labelledby` its title, rest of the dialog `inert`). One sheet at a time (the viewer behind is inert, so the second cannot be reached; the controller also refuses). |
| Open | Tap footer `Details` **or** the round Info FAB (portrait one-page, landscape). Slides up like the Pages sheet; none with reduced motion. Focus goes to the Close button; returns to the opener on close. |
| Header | Grab handle (visual and draggable), then a row: **title** (serif, bold, same face as the desktop modal title; wraps up to 3 lines then clamps) and the **X** button (38px target, same icon as the modal Close). The handle and the title row drag down to dismiss. |
| Body (scrolls if tall) | 1. **Category**: one outlined pill (`document_category`, first by name, as on the cover); omitted when the document has none. 2. **Description**: omitted when empty. 3. **Softwares used**: one pill per tool, **no heading** (lo-fi wireframe F2b has none); the group is omitted when there are no tools. 4. **Info rows** separated by thin `--dm-line` rules: `Format` -> `PDF` (leading label, ending value), `Pages` -> `24`. A row with no value is omitted (no `Format` row when the type is unknown; no `Pages` row when the count is 0). The `Pages` label follows the unit wording: `Slides` for presentations (same rule as the cover and modal pill; converted spreadsheets stay `Pages`). |
| Footer | **Download**, full width, filled dark (`--dm-ink` on `--dm-surface` text), download icon + `Download`. It is the same link semantics as the header's: `file` mode = `href` + `download`, `embed` = new tab with `rel=noopener`, `none` = `aria-disabled="true"` and dimmed, tapping does nothing. Pinned (not inside the scroll area) so it is always visible. The sheet stays open after the download starts (nothing to dismiss; same as the header link). |
| Close | X, dim-layer tap, swipe/drag down on the handle or title row, Esc (closes the sheet first; a second Esc closes the viewer), Back (one history entry, as the Pages sheet). Closing the viewer, or a document change, closes it too. |
| Landscape touch (<= 520px high) | The sheet **stays open when the phone is rotated** and re-lays out in place. Held sideways it is wider (`max-width: 720px`, `height: min(86%, 320px)`) and uses **two columns** under a **full-width title row** (so the X sits in the sheet's top-right corner): left, the scrolling content (category pill, description, software pills); right, a fixed column with the `Format` and `Pages` rows and the full-width Download button pinned at its bottom. Nothing needs scrolling for a typical document; the left column scrolls if the description is long. Pure CSS on the existing sideways media query (no new query, `routing.test.mjs` stays green); the DOM is identical in both orientations, so rotating changes nothing but layout. The drag handle stays on top. Unlike today's popover this works in landscape, where the title is not shown anywhere else. |
| Round Info FAB | Shown whenever the footer is gone (`setChrome( { info } )`, no page-count condition; owner decision 1). It stops being a disclosure button: `aria-expanded` / `aria-controls` are dropped, `aria-haspopup="dialog"` added. It still counts as an action (`dismissHint()`). Hiding on swipe-down is unchanged. |
| Details content when a native viewer is used | Identical. The data comes from the payload, not the viewer. |

## Decisions and rationale
1. **Data moves out of the viewer.** The Details sheet is built from the open document (`openDoc` in `mobile.js`, the payload entry), not from `createMobileViewer()` state. The viewer no longer stores `details`/`hasDetails`. FAB visibility becomes `chrome.info` only (it was `chrome.info && hasDetails`), and `drawFooter()` no longer requires `state.pages > 0`, so a document with no pages still gets the button.
2. **Event symmetry with Pages.** The viewer's Info FAB calls an `onDetails` callback (like `onPages`), `mobile.js` emits `documents:mobile-details` and a single listener opens the sheet. The footer button emits the same event (it already did, after toggling the popover). Nothing else toggles anything.
3. **Category and file type are added to the payload, not guessed in JS.** New `devportfolio_document_category_name( $post_id )` in `functions.php` (the sort-by-name rule currently inline in `document-book/render.php`, which then calls the helper: one source of truth, the cover and the sheet can never disagree). New payload keys `category` (string, `''` when none) and `fileType` (upper-case type for the `Format` row, from `cptdp_filetype`, falling back to the kind; `''` when unknown). `format` is untouched (cover and modal pill still use it).
4. **Row labels follow the document's unit** through the existing `wording( unitOf( doc ), units ).many`, so no new unit strings; only genuinely new words go to `devportfolio_document_mobile_strings()`.
5. **Lazy-loaded like the Pages sheet.** `details-sheet.js` is imported on the first Details tap. Rationale is consistency and a single sheet controller (below), not size.

## Refactor: shared components (do not copy the Pages sheet)
The Pages sheet today mixes three concerns in one 824-line file: generic bottom-sheet behaviour, Pages-specific content, and its own lifecycle plumbing in `mobile.js`. The Details sheet needs only the first and third. Extract them, move the Pages sheet onto them, and keep all DPD-020 behaviour identical.

| New / changed file | What it is | Moved from |
|---|---|---|
| **`bottom-sheet.js`** (new, framework-free) | `createBottomSheet( { dialog, id, label, onClose, handleLabel } )` -> `{ sheet, head, body, footer, open( { opener, focus } ), close( silent ), isOpen(), destroy() }`. Owns everything that is not content: dim layer, sheet element with ARIA, grab handle, **drag-to-dismiss** on the handle and `head` (pointer capture, `moveSheet`, `finishDrag`, tap vs drag, `swallowClick`), `panel.inert`, dim click, Esc via `keydown` and the dialog's `cancel` interception, focus return, reduced-motion check, `clearDrag`. Exposes `head` so a sheet can fill the title row (Pages puts its view toggle there, Details its X). The title `id` is unique per sheet (`documents-mobile-<name>-title`; Pages currently hardcodes `documents-mobile-pages-title`). Also exports the **pure** drag helpers: `dragOutcome`, `dragOffset`, `DRAG_*`. | `pages-sheet.js` lines 177-203 (pure), 260-290 and 596-770 (DOM) |
| **`sheet-controller.js`** (new) | `createSheetController( { dialog, history } )`. A registry of sheets by name: `register( name, { load } )`, `open( name, args )`, `closeActive( { fromHistory } )`, `isAnyOpen()`. Lazy-loads each module once, guards double taps while loading (`sheetLoading`), allows **one** open sheet, pushes/pops/releases the single history entry through `sheet-back.js`, `reset()` for document change and viewer close. | `mobile.js` (`sheet`, `sheetLoading`, `openPages`, `closeSheet`, `sheetHistory` wiring, `reset`/`release` in `close`, `isOpen` guard in `keydown`) |
| `pages-sheet.js` (slimmed) | `createPagesSheet()` keeps only content: model, tiles/rows, thumbnail observer, view toggle, `createSheetMemory()`. It calls `createBottomSheet()` instead of building dim/sheet/handle/drag itself. Its public shape (`open`, `close`, `isOpen`, `reset`) is unchanged. | (stays) |
| **`details-sheet.js`** (new) | `createDetailsSheet( { dialog, strings, units } )`: builds header (title + X), body (category pill, description, tools, info rows), footer (Download) on a `createBottomSheet()`. `open( { doc, opener } )` re-fills text only (the nodes are built once, like the Pages tiles). Pure model `detailsModel( { doc, strings, units } )` -> `{ title, category, description, tools, rows: [ { label, value } ] }` so the content rules run under `node --test`. | new |
| `mobile.js` | Shrinks: builds `createSheetController`, registers `pages` and `details`, forwards the two events, closes the active sheet on viewer close (and on rotation only for sheets registered with `closeOnRotate`, i.e. Pages), and uses `controller.isAnyOpen()` in the `keydown` guard. No sheet-specific branches. | refactor |
| `mobile-viewer.js` | **Deletes** the popover (see Removal). Adds `onDetails`. | refactor |
| `download.js` | Already shared; the sheet's link uses `applyDownload` / `guardDisabledDownload` unchanged. | (unchanged) |
| `sheet-back.js`, `deeplink.js`, `view.js` | **Unchanged.** Already generic (`isOpen` / `onBack`, `hasSheetState`). The new sheet inherits the Back, Forward, router-reload and leftover-entry handling (and its tests) for free; this is the main reason to go through one controller and one `createSheetHistory`. | (unchanged) |
| `style.css` | The shell classes (`__dim`, `__sheet`, `__grab`, `__sheet-head`, `__sheet-title`, `__sheet-body`) are already neutral and stay. Pages-only rules (`--pg-*`, tiles, rows, toggle) gain nothing new. New: a `documents-mobile__sheet--details` modifier and the details content classes. | extend |

**Why not a shared base class or one generic "sheet" with a content callback:** two consumers do not justify a framework. The shell is a plain factory returning the slots; each sheet fills them. No change to the rule "no build step, no dependencies".

**Behaviour to keep exactly (regression list for the refactor commit):** drag distances and flick speed, spring-back timings (160ms / 180ms), `swallowClick`, `inert` set/cleared, Esc closes the sheet then the viewer, focus return, memory of view and scroll, thumbnail observer lifecycle, history entry push/pop/release, `documents:mobile-pages` still the event that opens Pages.

## Removal (deprecating the popover)
Delete, do not leave dead code:
- `mobile-viewer.js`: `details`, `hasDetails`, `setDetails()`, `toggleDetails()` and its public export, the `panel`/`panelId`/`detailLines` block inside `makeDetails()` (and `panel` from its return and from `ui`), the `setDetails( false )` calls in `syncControls()`, `setFabAway()`, the reset paths and `show()`'s `details = {...}` assignment. `fabs.append( panel, pagesFab, fab )` becomes `fabs.append( pagesFab, fab )`. `aria-expanded` / `aria-controls` removed from the FAB.
- `style.css`: `.documents-mobile__details-panel` (and `[hidden]`), `-title`, `-desc`, `-tools`, `-format`, `p + p`.
- `mobile.js`: `viewer.toggleDetails()` call.
- Tests that assert the popover (`tests/test-document-modal-mobile-viewer.php` lines ~142, 284, 287-298, 344-349; `test-document-modal-mobile.php` ~71-86 stays for the footer button) are rewritten for the new behaviour, not just deleted.
- Docs: AGENTS.md sentences describing the "details panel" (mobile viewer section, "round details button ... opens a panel with the title...") are replaced.

## Architecture (files)
| File | Change |
|---|---|
| `functions.php` | `devportfolio_document_category_name()` (new helper); payload keys `category`, `fileType`; mobile strings `detailsTitle` (`Document details`, sheet accessible name; the visible title is the document title), `closeDetails` (`Close details`), `formatRow` (`Format`), `download` (`Download`). The existing `details` string stays (the FAB's `aria-label`). |
| `blocks/document-book/render.php` | Use `devportfolio_document_category_name()` instead of the inline block. Output must stay byte-identical (cover tests prove it). |
| `blocks/document-modal/bottom-sheet.js` | New (above). |
| `blocks/document-modal/sheet-controller.js` | New (above). |
| `blocks/document-modal/details-sheet.js` | New (above). |
| `blocks/document-modal/pages-sheet.js` | Slimmed onto the shell; drag helpers and their tests move. |
| `blocks/document-modal/mobile.js`, `mobile-viewer.js` | Refactor and removal (above). |
| `blocks/document-modal/style.css` | Details sheet block next to the Pages sheet block; popover CSS removed; tokens only (`--dm-*`), greyscale, no new colours, **no new media query** (`routing.test.mjs` stays green); reduced motion covered by the existing shared rule. |
| `tests/` | See Tests. |
| `AGENTS.md` | Mobile viewer section: replace the popover wording, document the three shared components and the single-sheet rule. |

### Details sheet markup (built by JS, once)
```
div.documents-mobile__dim
div.documents-mobile__sheet.documents-mobile__sheet--details[role=dialog][aria-modal=true][aria-labelledby]
  button.documents-mobile__grab
  div.documents-mobile__sheet-head
    h3.documents-mobile__details-title            (serif, bold)
    button.documents-mobile__sheet-close          (X)
  div.documents-mobile__sheet-body                (scrolls)
    span.documents-mobile__pill--outline          (category)
    p.documents-mobile__details-desc
    ul.documents-mobile__details-tools            (one li per tool, no heading)
    dl.documents-mobile__details-rows             (dt label / dd value, thin rules)
  div.documents-mobile__sheet-foot
    a.documents-mobile__details-download          (full width, filled dark, download icon)
```
`documents-mobile__pill` already exists for the viewer's hint pills; check whether it can carry the category and tool pills before adding a class (reuse over new CSS).

## Accessibility
Same as the Pages sheet: `role="dialog"`, `aria-modal`, labelled by the title, focus to Close on open and back to the opener on close, rest of the dialog `inert`, Esc closes the sheet first. The info rows are a real `<dl>`. Download is a link with `role="button"` like the header's and has a visible label (not just an icon). The category pill is plain text (no role). Targets >= 38px (X, Download 48px high). `aria-haspopup="dialog"` on both openers.

## Risks and open points
1. **Refactoring shipped code (DPD-020).** The Pages sheet has history, drag and thumbnail behaviour verified only in headless Chrome. The refactor must be its own commit with the Pages tests untouched in intent, and re-run the DPD-020 browser checklist (11 Back/Forward flows, 0 reloads; drag to dismiss; Esc order). Do the extraction **before** adding the Details sheet so a regression is attributable.
2. **Source-grep tests will break.** Many PHP tests assert on strings in `mobile.js` and `pages-sheet.js` (`import( './pages-sheet.js' )`, `sheetHistory.push()`, `sheet.isOpen()` regexes). They must move with the code (to `sheet-controller.js` / `bottom-sheet.js`) in the same commit; keep their intent, change their target file.
3. **Listener order matters.** `sheet-back.js` registers its `popstate` listener when `createSheetHistory()` runs, which must stay at script start ahead of the Interactivity Router (documented in AGENTS.md). The controller must create the history **eagerly in `mobile.js`**, never inside the lazy import. Add a test that asserts it.
4. **Focus and `inert` when both a sheet and the native grid exist.** The grid/scroller lives inside the panel, which is made inert; check xlsx and docx documents (library focus traps) in a browser.
5. **iOS Safari:** same caveats as DPD-020 (dim layer, `inert` on a `<dialog>`, `overscroll-behavior`). A real-phone check is still outstanding for both sheets; no browser runner exists (headless Chrome with puppeteer-core at 390x844 and 844x390).
6. **Long descriptions and many tools** (20+ tools, 1000-char descriptions): the content scrolls, Download stays pinned; verify nothing pushes it off screen at 844x390 in the two-column layout.
8. **Rotation with the sheet open** while the viewer behind re-lays out and fades (the DPD-024 rotation fade): the dim layer must stay put, and focus must not be lost when columns change.
7. **Format row vs the pill.** `fileType` comes from `cptdp_filetype`; for a presentation the cover says `PPTX`, a converted `.docx` says `DOCX`. Confirm the two never disagree (use the same source as `devportfolio_document_format_label()`; ideally derive both from one helper).

## Steps (theme only; one conventional commit each; tests never inside a feature commit, except where the refactor must move existing tests with it; both suites green at every commit)
1. `refactor(theme): extract the bottom sheet shell and sheet controller` (`bottom-sheet.js`, `sheet-controller.js`; Pages sheet and `mobile.js` moved onto them; behaviour unchanged; existing Pages tests retargeted, drag tests move to `tests/bottom-sheet.test.mjs`).
2. `feat(theme): add category and file type to the modal payload` (`functions.php` helper and keys; `document-book/render.php` uses the helper; strings).
3. `test(theme): cover the category helper and payload keys` (`tests/test-document-modal-mobile-details.php`: helper order rule, empty category, `fileType` fallback, keys present in the rendered payload and unchanged cover markup).
4. `feat(theme): add the mobile Details sheet` (`details-sheet.js`, `detailsModel()`, CSS, registration in `mobile.js`).
5. `refactor(theme): remove the details popover` (viewer, CSS, `toggleDetails`, FAB ARIA; FAB and footer button emit `documents:mobile-details`).
6. `test(theme): cover the Details sheet and the removed popover` (PHP structure/CSS/strings/a negative test that no `details-panel` selector or `toggleDetails` remains; `tests/details-sheet.test.mjs` for `detailsModel()`: omitted category/description/tools, row omission, `Slides` vs `Pages`, `fileType` fallback, plain-text safety; `tests/sheet-controller.test.mjs` with a fake dialog: one sheet at a time, double tap while loading, close-from-history releases without popping).
7. `docs(agents): document the Details sheet and the shared sheet components` (replace the popover wording, list the three modules, update test totals).

Verify in the browser (`https://devportfolio.ddev.site/documents/`, headless Chrome at 390x844 with touch and 844x390): footer Details and the round Info FAB both open the same sheet; category pill, description, tool pills, `Format`/`Pages` rows (`Slides` for a deck), Download works and is disabled when `none`; X, dim tap, drag down and Esc close it; Back closes only the sheet, then the viewer; 11 Back/Forward flows with 0 page reloads; opening Pages after Details and the reverse; rotation keeps it open and switches to the two-column layout (and back); Pages still closes on rotation; a document with 0 pages still shows the FAB; reduced motion has no animation; xlsx/docx native viewers still reachable after close; long description and 20 tools scroll with Download pinned.

## Owner answers (resolved)
1. **FAB with zero pages: show it.** The sheet holds information that is shown nowhere else (software used, category, Download), so the Info FAB is shown whenever the footer is gone, whatever the page count. `setChrome( { info } )` loses its `state.pages > 0` condition (`mobile.js` `drawFooter()`); the footer Details button is unchanged.
2. **Rotation does not close the sheet.** It stays open and re-lays out in place (see the landscape row above). The controller's "close on landscape change" applies to the Pages sheet only, through a per-sheet option `closeOnRotate` (Pages `true`, Details `false`).
3. **Footer `Details` icon: unchanged.**

## Wireframe check (resolves the former Open question 4)
`documents-lofi-wireframe.html`, frame **F2b - Details sheet**: title (serif) with an X; the category as a small outlined chip; the description; the tags; then two info rows (grey label, value at the end, thin lines); then a full-width dark `Download` button. **No labels above the category or the tags**, so none are built. The frame also draws the footer's Details button filled dark with a chevron up while the sheet is open; per owner decision 3 the footer icon is left as it is (the dim layer covers it).

## Open questions
None.

## As built
Implemented as planned, with these differences and additions.

**Files**
- New: `bottom-sheet.js`, `sheet-controller.js`, `details-sheet.js`; tests `tests/{bottom-sheet,details-sheet,sheet-controller}.test.mjs` and `tests/test-document-modal-mobile-details.php`.
- Changed: `pages-sheet.js` (content only, built on the shell), `mobile.js` (registers both sheets with the controller), `mobile-viewer.js` (popover removed, `onDetails` callback, FAB `aria-haspopup="dialog"`), `style.css`, `functions.php`, `blocks/document-book/render.php` (uses the category helper), `AGENTS.md`.
- Unchanged, as planned: `sheet-back.js`, `deeplink.js`, `view.js`, `download.js`, `dom.js`.

**Deviations from the plan**
1. **Single commit set, not yet committed.** Steps 1-7 were done in one pass and are all in the working tree; split them per the Steps list when committing.
2. **Controller contract.** A sheet is `{ open( args ) => boolean, close( silent ), isOpen(), reset() }` and calls the `onClose` it is given when the *visitor* dismisses it. The controller exposes `register`, `open`, `close`, `closeOnRotate`, `isAnyOpen`, `activeName`, `reset`, `release`; the history entry is created inside the controller (when `mobile.js` builds it), never in a lazy import (a PHP test enforces it).
3. **Shell contract.** `createBottomSheet( { dialog, titleId, handleLabel, modifier, onDismiss } )` returns `{ sheet, head, body, grab, show, hide, isOpen }`. The shell never closes itself; every dismiss gesture calls `onDismiss`. Header controls with their own click carry `data-no-drag` (replaces the Pages-only `[data-view]` check; the X needs it too).
4. **Pre-existing bug fixed in the refactor.** In DPD-020, dismissing the Pages sheet by dim tap, drag or Esc never popped its history entry (`onClose` ran after `state.open` was already false, so `closeSheet()` returned early), leaving one stray entry. The controller now pops the entry on every visitor dismissal; covered by a node test.
5. **CSS scoping.** The Pages three-row `max-height` is now `.documents-mobile__sheet--pages .documents-mobile__sheet-body`; the shell classes stay neutral.
6. **Landscape layout.** The title row spans both columns (areas `grab / head head / main rows / main foot`) and the sheet has a fixed `height: min(86%, 320px)` so the left column scrolls reliably. The first version put the title in the left column only, which left the X floating mid-sheet; fixed after review.
7. **Payload.** `category` is `html_entity_decode()`d (WordPress stores `&amp;` in term names, and the script writes it with `textContent`); the helper itself still returns the stored name so the cover's `esc_html()` is unchanged. `fileType` comes from `devportfolio_document_file_type_label()`, which `devportfolio_document_format_label()` now also uses, so the two cannot disagree (Risk 7).
8. **Strings.** Added `closeDetails`, `formatRow`, `download`. No `detailsTitle`, `categoryLabel` or `toolsLabel` (wireframe F2b has no headings; the sheet is labelled by the document title).
9. **Owner decisions applied:** FAB shown whenever the footer is gone, even with 0 pages (`info: footerGone`); Details sheet stays open on rotation (`closeOnRotate` only on Pages); footer chevron unchanged; no pill labels.

**Verification done** (headless Chrome via puppeteer-core in the scratchpad, `https://devportfolio.ddev.site/documents/`, 390x844 and 844x390 touch): footer Details and the round Info button both open the sheet; title, category, description, tool pills, Format and Pages rows, full-width black Download with the right href; X, dim tap and Back close only the sheet and leave the viewer open; `inert` is set while open and cleared after; no old popover element; Pages sheet regression (open, pick page 4, drag the handle down, Back, no page reload); landscape two-column layout with the X in the top-right corner (screenshot).

**Not verified:** a real phone and iOS Safari, a Pages-sheet pass beyond the flows above (the 11 Back/Forward flows from DPD-020 were not repeated), xlsx/docx native viewers behind the sheet (focus and `inert`, Risk 4), a 20-tool or very long description document, a presentation (`Slides` label), a document with no category or no tools, a disabled-Download document, and the portrait X placement (left as in the wireframe; options if it feels off: larger outlined circle, or X above the title beside the handle).
