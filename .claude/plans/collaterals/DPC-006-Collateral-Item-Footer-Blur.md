# Plan: Collateral item footer — blurred background (DPC-006)

Story: **Collateral Item · Footer · DPC-006 · Blur out the footer background. Make sure to adhere to accessibility settings of the browser.**

Follow-up to `collaterals/DPC-006-014-Collateral-Item-Masonry.md`. Theme only: no plugin change, no plugin version bump.

---

## 1. Goal

The info band at the bottom of a collateral item (title plus `Flyer · A4 · 2 pp`) becomes a frosted band: the page image behind it is blurred through the shared glass tokens (`--wp--custom--glass--tint` over `--wp--custom--glass--filter`, the same pair as the site header), instead of the flat 80% white tint it had.

Browser accessibility settings win over the effect: the band is plain opaque white with no blur when the visitor asks for less transparency, more contrast or forced colours, and in a browser without `backdrop-filter`.

The band is the item's **footer**, not the modal's footer: the ticket is on the item (DPC-006), which is the grid tile.

## 2. The constraint and the change

The masonry plan (deviation note in `DPC-006-014`) recorded that the band must not be a containing block: any filter, `backdrop-filter` included, makes the element a containing block for absolute descendants, so the title button's stretched `::after` would cover only the band and the thumbnail would stop being clickable. That is why the band had no blur.

Confirmed in headless Chrome: with `backdrop-filter` on the band, an absolutely positioned probe inside it resolves its `offsetParent` to the band (300 × 100) instead of the item (300 × 400).

So the hit area moves out of the band, and the band is free to blur:

- `render.php`: the title is plain text in its `h3` again, and the item's one control is an empty `button.collaterals-item__open[data-collateral-id]` — the `aria-label` it already carried is now its only name — as a **direct child of the article**, after the band.
- `style.css`: the button is `position: absolute; inset: 0; z-index: 2`, so a click or tap anywhere on the tile still opens the item (verified with `elementFromPoint` at the top, the band and the bottom-right corner, desktop and phone). The band carries the blur; `::after` is gone.
- Semantics: heading (`h3`) plus a named button instead of a button inside the heading. The focus ring is unchanged (`:has(.collaterals-item__open:focus-visible)`), the hover reveal is unchanged.

## 3. Accessibility settings handled

| Setting | Result |
|---|---|
| `prefers-reduced-transparency: reduce` | opaque `base`, `backdrop-filter: none` |
| `prefers-contrast: more` | opaque `base`, `backdrop-filter: none` |
| `forced-colors: active` | `Canvas`, `backdrop-filter: none` |
| no `backdrop-filter` support (`@supports not …`) | opaque `base` |

The last three keep the ink text on a solid background, so contrast never depends on what the page image happens to show.

## 4. Tests

`tests/test-collateral-grid.php` (theme suite, 478 → 481 tests):

- `test_each_item_has_exactly_one_button_that_is_a_direct_child_of_the_item` (was `… _in_its_title`): one button per item, a direct child of the article, none inside the band, and the title keeps its `h3`.
- `test_title_is_escaped_as_text`: now reads the `h3`.
- `test_the_open_button_covers_the_whole_item`: `position: absolute` + `inset: 0`, and no `.collaterals-item__open::after`.
- `test_the_info_band_blurs_what_is_behind_it`: the tint and both `backdrop-filter` declarations (the `-webkit-` one too).
- `test_the_blur_is_dropped_when_the_browser_asks_for_less_transparency_or_more_contrast`.
- `test_the_blur_is_dropped_in_forced_colors_and_without_backdrop_filter_support`.
- New private helper `rule()` (white space collapsed, comments stripped).

## 5. Browser check

Headless Chrome over CDP against `https://devportfolio.ddev.site/collaterals/`, no DB changes:

- band computed style: `backdrop-filter: blur(20px) saturate(1.8)`, background `rgba(255, 255, 255, 0.8)`, radius `0 0 6px 6px`;
- hit test at the tile top, the band centre and the bottom-right corner returns `BUTTON.collaterals-item__open` at 1440 and 390; clicking the thumbnail's centre opens `dialog.collaterals-modal`;
- emulated `prefers-reduced-transparency: reduce`, `prefers-contrast: more` and `forced-colors: active` each give `backdrop-filter: none` and an opaque background, and the normal state returns when the emulation is cleared;
- with black/white stripes painted behind the band, the screenshot shows them washed out and softened under the tint (the blur runs); text stays crisp.

Not verified: `(hover: none)` touch reveal (CDP media emulation does not change `(hover: hover)`, as noted in the masonry plan), and the blur's cost on a slow phone.

## 6. Conventional commits

Branch: `feat/DPC-006-Collateral-Item-Footer-Blur`.

| # | Subject | Stages | Refs |
|---|---|---|---|
| 1 | `docs(plans): add the collateral item footer blur plan` | this plan | DPC-006 |
| 2 | `feat(theme): blur the collateral item footer` | `blocks/collateral-grid/{render.php,style.css}` | DPC-006 |
| 3 | `test(theme): cover the collateral item footer blur` | `tests/test-collateral-grid.php` | DPC-006 |
| 4 | `docs(agents): document the collateral item footer blur` | `AGENTS.md` | DPC-006 |

PR title: `feat(theme): blur the collateral item footer` (72 characters or fewer).
