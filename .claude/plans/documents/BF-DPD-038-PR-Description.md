# fix(theme): zoom the PowerPoint viewer on both axes

## Type of Change

- [ ] New feature (non-breaking change which adds functionality)
- [ ] Change Request (non-breaking change which changes user interface or app behaviour)
- [x] Bug fix (non-breaking change which fixes an issue)
- [ ] Breaking change (fix or feature that would cause existing functionality to change)
- [x] Code refactor
- [ ] Build configuration change
- [x] Documentation
- [ ] Chore

## Description

BF-DPD-038: in the desktop document modal, zooming a PowerPoint (with Settings → Documents → Native viewer → PowerPoint on) did not work on the x axis. The mobile viewer was not affected.

Four things were wrong, found by reproducing it in headless Chrome:

1. **Only the height grew.** The library zooms by redrawing the slide and setting the canvas's width and height. Our `max-width: 100%` on the canvas kept the width at the stage's, so the slide stretched vertically and the selectable text drifted off it.
2. **The zoomed slide stayed inside a small box.** It could only scroll inside the stage, which sits inside the body's padding, so it looked like the zoom happened "inside the viewer" instead of filling the body. The top and left of a large slide were also cut off.
3. **The zoom buttons jumped** to the body's edge on the first click.
4. **The zoom buttons were wider than the Next arrow** (46px against 38px) and not lined up with it.

Now a zoomed slide keeps its size on both axes and scrolls over the whole body, edge to edge, with every side reachable. The zoom buttons sit centred under the Next arrow, at its width, from the moment the slide opens, and do not move when you zoom. When nothing is zoomed, the slide is fitted inside the gutters exactly as before.

## Implementation

- **Fitted or zoomed** (`blocks/shared/viewer.js`, `showPptx()`): the viewer stores the scale the deck opened at. While the scale is the same, `fit()` sizes the canvas to the stage with CSS as before. Once it differs, `fit()` leaves the library's size alone and adds `__native--zoomed` to the wrapper and `__stage--zoomed` to the stage. `openPptx()` (`blocks/shared/native-pptx.js`) passes the library's `onScaleChange` through, so the check runs after each zoom is drawn. `reset()` removes the stage class, so the next document starts fitted. No re-render on resize, and nothing private in the library is used.
- **Zoomed CSS** (`blocks/document-modal/style.css`): the stage becomes `position: static` and the wrapper `position: absolute; inset: 0; overflow: auto` against the body. The slide is centred with `margin: auto` (auto margins never go negative, unlike `justify-content: center`), and the canvas `max-width` clamp is lifted. The arrows and zoom buttons stay on top.
- **Arrow variables (refactor):** the arrows' inset and size are now `--dm-arrow-inset` and `--dm-arrow-size` on the body, with the same values as before (18px/38px, 10px/34px at 1100px and below, 8px at 900px and below). The arrows do not move.
- **Zoom buttons:** they are appended to the body instead of the stage, so what they are placed against never changes. They are centred on the Next arrow's column (`right: calc(inset + size / 2)` with `translateX(50%)`). The group is `width: var(--dm-arrow-size)` with each button a square filling it (36×36, 32×32 at 1100px and below).
- **Worth a look:**
  - The zoom buttons are shared, so the desktop **Excel and Word** native viewers get the new placement and width too. Their zoom itself is unchanged.
  - Each zoom button's tap target is now the arrow's size.
  - When nothing is zoomed, the buttons sit about 18px lower and slightly further right than before, because they now line up with the body instead of the padded stage.
- **Known limits:**
  - The library's zoom steps do not always land back on the opening scale. After zooming out, the slide may stay at the library's size, centred, until the modal is reopened. It stays fully visible.
  - Resizing the window while zoomed keeps the zoom.
- **Out of scope:** the mobile viewer, the vendored library, and styles for `.collaterals-modal__zoom-fab` (it has no rules today).
- **Tests:**
  - Two new theme tests in `tests/test-document-presentations.php`: one for the zoom, one for the buttons' place and width.
  - Three existing assertions in `tests/test-document-modal.php` and `tests/test-collateral-viewer.php` pinned the literal `18px`/`10px`. They now pin the variables and the same values, in the refactor commit, so no commit is red.
- **Commits:** seven, each checked green before it was made. Code and its tests are in separate commits, except the refactor, which has to carry its test update.
- **Docs:** `AGENTS.md` (DPD-026 zoom notes, the arrow variables, theme test count 501) and the plan `.claude/plans/documents/BF-DPD-038-PPTX-Viewer-Zoom-X-Axis.md`.

## Screenshots

|         | before | after |
| ------- | ------ | ----- |
| Zoomed slide | _(slide stretched vertically, clipped to the stage)_ | _(slide fills the body on both axes)_ |
| Zoom buttons | _(46px group, jumps on the first click)_ | _(under the Next arrow, at its width)_ |

## How to Test

1. Run the theme suite and the JS tests:
   - `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit` (501 tests)
   - `node --test tests/*.test.mjs` in `blocks/shared` (158), `blocks/document-modal` (16) and `blocks/collateral-modal` (24), through `ddev exec --dir=...`
2. Turn on Settings → Documents → Native viewer → **PowerPoint**.
3. On a desktop window (1440px wide), open `/documents/?document_slug=october-2026-operations-review`.
   - The slide is fitted inside the grey area as before, and the zoom buttons sit centred under the Next arrow, at its width.
4. Click **+** three times.
   - The slide grows wider and taller, fills the whole grey body edge to edge, and scrolls to all four sides.
   - The zoom buttons do not move.
   - Selecting text on the slide lines up with the words.
5. Turn a slide with the arrows or the pager. It stays zoomed. Click **−** to zoom back out.
6. Close and reopen the modal. The slide starts fitted again.
7. Narrow the window to about 1000px, then 800px. The buttons follow the Next arrow and stay its width (34px).
8. With Native viewer → **Excel** and **Word** on, open a `.xlsx` and a `.docx`. They still zoom, with the buttons under the Next arrow.
9. On a phone width (390px), open the same deck. Pinch and the round zoom buttons work as before.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
