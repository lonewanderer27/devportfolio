# Plan: PPTX viewer zoom works on both axes

Bug BF-DPD-038 (Medium, Bug Fix), area *Modal / PPTX Viewer*: *Zoom in does not work in the x axis.*
Desktop document modal only; the mobile viewer has its own pinch and CSS-transform zoom and was never affected.
Builds on `DPD-026-Document-Modal-PPTX-Viewer.md` (the native `.pptx` viewer, `showPptx()`) and the shared zoom buttons
in `blocks/shared/viewer.js` (`showZoomFab()`).

**Out of scope:** the mobile viewer, the library itself (`blocks/shared/vendor/ooxml/`, vendored, untouched), zooming
the stored page images or the StPageFlip book, and styling the zoom buttons for the `collaterals-modal` prefix
(`.collaterals-modal__zoom-fab` has no rules today; a separate ticket if collaterals ever show a native viewer with zoom).

## Context (what the code did)
- `showPptx()` puts one `<canvas>` in `.documents-modal__native` (a centred flex column filling the stage) and fits it
  to the stage with CSS (`fit()`: `canvas.style.width/height` from `fitPage()`), re-fitted by a `ResizeObserver`.
- The library (`@silurus/ooxml` 0.89.7, `PptxViewer`) zooms with `setScale()`: it redraws the slide larger and **sets
  the canvas's inline `width` and `height`** itself (`renderCurrentSlide()`). It wraps the canvas in its own
  `position:relative; display:inline-block` div together with the text layer.
- `.documents-modal__body` has the padding `30px 76px` (12px at 1100px and below, 0 at 900px and below) that keeps the
  page arrows clear of an unzoomed page; `.documents-modal__stage` inside it is `position: relative`.
- The zoom buttons (`.documents-modal__zoom-fab`, minus over plus) were appended to the stage at `right/bottom: 12px`,
  46px wide (36px buttons + 4px padding + 1px border). The Next arrow is 38px (34px at 1100px and below), 18px / 10px
  / 8px from the body's right edge.

## Root causes (reproduced in headless Chrome at 1440x900, "October 2026 Operations Review")
1. **Only the height grew.** `.documents-modal__native-canvas { max-width: 100% }` capped the library's new width
   while its inline height grew, so the slide stretched vertically and the text layer drifted off the slide.
2. **The zoomed slide was clipped to the stage.** After 3 zoom-ins the canvas was 1600x900 but could only scroll
   inside the stage (1254x646, inside the body's padding), not the body (1406x706): it looked like the zoom happened
   "inside the PPT viewer", and the centring flexbox cut off the top and left of an overflowing slide.
3. **The zoom buttons jumped on the first click.** Fixing 2 makes the stage `position: static` while zoomed, so the
   buttons' containing block switched from the stage to the body and they moved to the body's edge.
4. **The buttons were wider than the Next arrow** (46px vs 38px) and not on its column.

## Key decisions
1. **Fitted vs zoomed is decided from the library's scale.** `showPptx()` stores `fittedScale = instance.getScale()`
   once the deck is showing; `isZoomed()` compares the current scale with it. Fitted: `fit()` keeps sizing the canvas
   with CSS (unchanged behaviour, cheap on resize). Zoomed: `fit()` leaves the library's size alone (both axes).
   `openPptx()` passes the library's `onScaleChange` through, called after the zoomed slide is drawn, which calls
   `fit()`. No re-render on resize, no use of the library's private fields.
2. **Zoomed = scroll over the whole body.** `fit()` toggles `__native--zoomed` on the wrapper and `__stage--zoomed` on
   the stage. CSS: the stage becomes `position: static`, the wrapper `position: absolute; inset: 0; overflow: auto;
   padding: 12px` against the body, and its child is centred with `margin: auto` (auto margins never go negative, so
   every side stays reachable, unlike `justify-content: center`); the canvas width clamp is lifted. The arrows
   (z-index 2) and the zoom buttons (3) stay on top. `reset()` removes the stage class, so the next document starts
   fitted. The unzoomed layout, gutters included, is unchanged.
3. **Zoom buttons live in the body, centred on the Next arrow's column.** `showZoomFab()` appends to
   `stage.closest( '.<prefix>__body' )` (fallback: the stage), so the containing block never changes. The arrow inset
   and size become two variables on the body, `--dm-arrow-inset` (18px / 10px / 8px) and `--dm-arrow-size`
   (38px / 34px), used by the arrows and by the buttons: `right: calc(inset + size / 2)` with
   `transform: translateX(50%)` puts the group's centre on the arrow's centre line whatever its width. Same values as
   before, so the arrows do not move.
4. **Same width as the Next arrow.** The group is `box-sizing: border-box; width: var(--dm-arrow-size); padding: 0`
   (keeping its 1px border, like the arrow's), each button `width: 100%; aspect-ratio: 1` (36x36, 32x32 at 1100px and
   below). The tap target is now the arrow's size; accepted in review.
5. **The zoom buttons are shared**, so 3 and 4 also apply to the desktop Excel and Word native viewers (`showXlsx()`,
   `showDocx()`); 1 and 2 are PowerPoint only (the other two scroll inside the library's own viewer).

## Steps (one conventional commit each; every commit leaves both suites green)
`viewer.js` and `style.css` change in more than one commit: stage only the hunks of each step (no interactive
`git add -p` here: build the step's file state, or `git apply --cached` a filtered patch), and run the theme suite
before each commit.

1. `fix(theme): zoom native slides on both axes over the whole body`
   Files: `blocks/shared/native-pptx.js` (`onScaleChange` option), `blocks/shared/viewer.js` (`fittedScale`,
   `isZoomed()`, `fit()` toggling `__native--zoomed` / `__stage--zoomed`, `onScaleChange` in `showPptx()`, the stage
   class removed in `reset()`), `blocks/document-modal/style.css` (the `__native--zoomed`, its child, the canvas
   `max-width: none` and the `__stage--zoomed` rules).
2. `test(theme): cover zooming a native slide`
   Files: `tests/test-document-presentations.php`, `test_a_zoomed_slide_grows_on_both_axes_and_scrolls()` (the zoomed
   CSS, the stage rule, the class toggles, the stage class cleared in `reset()`, and `onScaleChange` in `viewer.js` and
   `native-pptx.js`).
3. `refactor(theme): hold the modal arrow inset and size in variables`
   Files: `blocks/document-modal/style.css` (`--dm-arrow-inset` / `--dm-arrow-size` on both bodies and in the 1100px
   and 900px queries; the arrow rules use them), `tests/test-document-modal.php` and
   `tests/test-collateral-viewer.php` (the three assertions that pinned `left/right: 18px` and `left: 10px` now pin the
   variables and their values). Code and test squashed on purpose: the old assertions fail without the update.
4. `fix(theme): line the zoom buttons up with the next arrow`
   Files: `blocks/shared/viewer.js` (`showZoomFab()` appends to the body), `blocks/document-modal/style.css`
   (`.documents-modal__zoom-fab` `right`/`transform`, `box-sizing`, `width`, `padding: 0`;
   `.documents-modal__zoom-button` `width: 100%; aspect-ratio: 1`).
5. `test(theme): cover the zoom buttons' place and width`
   Files: `tests/test-document-presentations.php`, `test_the_zoom_buttons_sit_under_the_next_arrow_at_its_width()`
   (the append target, the arrow variable, the group's `right` and `translateX(50%)`, its width and the square
   buttons).
6. `docs(agents): document the desktop native zoom`
   Files: `AGENTS.md` (DPD-026 notes: zoomed slides keep the library's size and scroll over the body, the zoom buttons
   sit in the body under the Next arrow; the arrow variables; theme test count 499 to 501).
7. `docs(plans): add the BF-DPD-038 plan`
   Files: this file only, last.

Footer on every commit: `Refs: BF-DPD-038`, then `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
Stage explicit paths only (untracked plans of other tickets sit in the tree).

- **Branch:** `fix/BF-DPD-038-PPTX-Viewer-Zoom-X-Axis` (from `main`)
- **PR title:** `fix(theme): zoom the PowerPoint viewer on both axes`
- **PR body:** `.github/pull_request_template.md`, type *Bug fix*; Description = root causes 1 to 4; How to Test =
  the browser checks below; ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Normal merge
  commit, no squash.

## Checks
- Theme suite: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit`
  (501 tests, green). JS: `node --test tests/*.test.mjs` in `blocks/shared` (158), `blocks/document-modal` (16) and
  `blocks/collateral-modal` (24), green. The plugin is untouched.
- Browser (headless Chrome via puppeteer-core, Native viewer > PowerPoint on), `/documents/?document_slug=october-2026-operations-review`:
  - 1440x900, 3x zoom in: canvas 1600x900 on both axes, scroll area = body (1406x706), all four edges reachable.
  - 1440, 1000 and 800px wide: the zoom group's centre equals the Next arrow's centre before zooming, after zooming in
    and after zooming out; group width = arrow width (38 / 34 / 34px), buttons 36x36 / 32x32 / 32x32.
  - Unzoomed: slide fitted inside the gutters as before.
  - Mobile (390x844): not touched by this fix (its own viewer, `mobile-viewer.js`); check it still zooms by pinch and
    the round buttons before the PR.
- Manual: zoom in, turn a slide (stays zoomed), zoom back out, close and reopen (starts fitted); select text on a
  zoomed slide (the text layer lines up); a `.xlsx` and a `.docx` with their native viewers on still zoom, with the
  buttons under the Next arrow.

## Known limits
- The library's zoom steps do not always land back on the opening scale: after zooming out the slide may stay at the
  library's size, centred, until the modal is reopened (it is still fully visible and scrollable).
- Resizing the window while zoomed keeps the zoom (no refit) until the visitor zooms back.

## As built
Commits on `fix/BF-DPD-038-PPTX-Viewer-Zoom-X-Axis`, in the order above, each checked green before it was made
(theme PHPUnit, and the `shared`, `document-modal` and `collateral-modal` node tests for the code commits):
`a7b3088` fix, `9f6d6bb` test (500 tests), `5679acd` refactor (with the three updated assertions), `7608d67` fix,
`0546078` test (501 tests), `63519f5` docs(agents), then this plan. The intermediate file states were rebuilt from
`main` by applying only each step's hunks of the final diff; the last code commit's files equal the reviewed ones
byte for byte.
