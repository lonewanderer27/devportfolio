# Plan: Document Book Item (cover, cover styles, hover animation)

Tickets DPD-005 (book cover content), DPD-006 (six cover styles, spine and stitch) and DPD-008 (hover / pressed animation). Branch suggestion `feat/DPD-005-006-008-Document-Book-Item`. Builds on `DPH-002-DPD-000-001-Documents-Page.md` (the `archive-document` template), `DPD-002-003-025-Header-Categories.md` (pills and the shelf contract) and `DPB-007-008-012-Document.md` (the `document` CPT and its meta). The shelf that lays books out in rows (DPD-016) and the viewer opened on click are **not** in this plan.

Design sources, newest first: the hi-fi `.claude/plans/documents/documents-hifi-wireframe` (`.book`, `.p0`-`.p5`: **the source of truth for the book**), the design system `.claude/plans/design-system.html` (tokens, motion rules) and the lo-fi `.claude/plans/documents/documents-lofi-wireframe.html` (layout and the pattern guide, section C). Revision 3 of this plan applies the hi-fi; the comparison is in "Design alignment".

## Context
- The `document` CPT already stores the data the cover needs: category via `document_category`, `cptdp_filetype` (string, e.g. `PDF`) and `cptdp_total_pages` (int). The owner mark is a constant (`DAVE`), not data.
- **No cover style meta exists yet.** DPD-006 says "the cover style is stored per document (see DPD-004)". DPD-004 is not in the repo or the plans, so this plan assumes it will provide post meta `cptdp_cover_style` holding one of the six slugs. See "Risks / open points" (decision needed before commit 5).
- The theme is a tiny block theme with one dynamic block today (`blocks/document-categories/`), a lo-fi palette in `theme.json` (`base`, `contrast`, `mid`, `line`, `fill`) and system fonts. The design system's tokens (`--book-shadow`, `--ease`, Hanken Grotesk, dark mode, `--grad`) are **not** in the theme yet; re-theming the whole site is a separate ticket. So the book carries its own small token set (decision 6) taken from the design system, and does not depend on `theme.json` colours.
- The shelf contract from DPD-002/003 expects items as direct children of `[data-documents-shelf]` carrying `data-document-categories="slug slug"`. The book is only the **cover** inside such an item; the description and tags sit under it (DPD-016).

## Design alignment
The hi-fi wireframe is the latest design and wins over the lo-fi, the design system's small `.book` sample and, where they disagree, the ticket wording. Differences from the ticket text are listed so any can be reverted with one CSS change.

| Aspect | Lo-fi / ticket | Hi-fi (used) |
|--------|----------------|--------------|
| Cover colours | greyscale, white text on every style (DPD-005) | black, white and grey; **Pinstripe and Dot Grid are light covers with dark text**; each style sets `--cv`, `--ct`, `--cat-c` |
| Midnight | solid dark gradient | solid black with an inner hairline frame (inset 10px 10px 10px 26px, 24% white) |
| Pinstripe | diagonal stripes | `surface-alt` ground, 135deg hairlines (9% black, 1.5px every 9px) |
| Horizon | circle over a gradient | dark gradient (165deg `#2c2c2e` to black) with a gradient sun (27% wide, glow); the only gradient |
| Split Sky | light top 40%, dark bottom | light top **30%** (`surface-alt`), black bottom; category in dark ink on the light part |
| Dot Grid | evenly spaced dots | `line` grey ground, 17px dot grid (22% black) |
| Two-Tone | vertical half split | `#2c2c2e` / `#6a6a6f` halves |
| Ratio, radius | 2 : 3, 3px / 8px | 2 : 3, `3px 6px 6px 3px` |
| Outline | thin | 1px `--edge` hairline |
| Shadow | `8px 10px 0` + outline ring | flat `8px 10px 0 --book-shadow` (mobile `5px 6px`) |
| Spine | 14px, light inner edge | 16px (mobile 11px), 20% black, **no light inner edge** |
| Stitch | full height, 5px | dashed, `left: 21px` (mobile 15px), **inset 8px top and bottom** (mobile 6px) |
| Padding | 16px 14px 14px 26px | `18px 14px 14px 32px` (mobile `12px 10px 10px 22px`), breakpoint 820px |
| Category | 9.5px, `.14em` | 10px, weight 600, `.1em`, opacity .9 (mobile 8.5px) |
| Title | Georgia 21px bold | sans, 23px, weight 500, `-.015em`, `padding-block: 10px` (mobile 17px) |
| Ornament | 34 x 2px, 80% | 34 x 2px, 75% (mobile 22 x 1.5px), the title's `::after` |
| Footer | 10px | 10px, weight 500, `.06em`, tabular numbers, opacity .9 |
| Pattern layer | none | an `aria-hidden` `i.pat` element, first child (needed for the frame and the sun) |
| Hover | lift 6px, tilt 1deg, shadow `12px 16px` | same, over **260ms** (`--dur-base`, DS easing) instead of the ticket's "about 0.2s" |
| Pressed | pressed state on touch | `:active` scales to **0.985** (all devices) |
| Reduced motion | not drawn | animations and transitions off |
| Dark mode | none | hi-fi tokens flip; the book keeps fixed light-mode values (theme has no dark mode yet) |

Deviations from the ticket text (all from the hi-fi): light covers with dark text (DPD-005 "light text on every style"), no inner spine edge and an inset stitch (DPD-006), 260ms motion (DPD-008).

## Key decisions
1. **Dynamic block `devportfolio/document-book`** (`blocks/document-book/`: `block.json`, `render.php`, `style.css`), registered in `functions.php` next to the categories block. It uses `usesContext: ["postId"]`, so it works inside a core Query Loop / Post Template and DPD-016 can also render it with `render_block()` / `do_blocks()` for a given post. Server-side, so the cover always reflects the DB. Slug prefix `devportfolio/` per AGENTS.md. The block outputs **only the cover** (`<div class="documents-book">`), not the item wrapper, the info block or any link.
2. **Cover markup (DOM order = reading order, DPD-005 "in order"); class names follow the hi-fi `.pat` / `.cat` / `.ttl` / `.ft`:**
   ```html
   <div class="documents-book" data-cover-style="midnight">
     <i class="documents-book__pat" aria-hidden="true"></i>
     <span class="documents-book__cat">Executive Docs</span>
     <span class="documents-book__ttl">Document title</span>
     <span class="documents-book__ft">
       <span class="documents-book__format">PDF · 24 pp</span>
       <span class="documents-book__owner">DAVE</span>
     </span>
   </div>
   ```
   Category top-left, title middle-left, format bottom-left with the owner mark bottom-right of the **same line**. The short ornament line is the title's `::after`. No status tag, description or tags (negative test). Plain text elements, no `role="img"` or `aria-label`: the item (DPD-016) provides the accessible name, as the hi-fi does with its `<button aria-label="Open ...">`. Spine, stitch and pattern are CSS or `aria-hidden`.
3. **Category label = first `document_category` term name** (by name order, the DPD-007 order). A document in several categories shows one on the cover; the full set drives filtering via `data-document-categories` on the item (DPD-016). No category: the span is rendered empty (kept so the title position does not move), not removed.
4. **Format line, one string, built in PHP:**
   - file type = `cptdp_filetype`, upper-cased (`strtoupper`), trimmed.
   - pages = `cptdp_total_pages`: `1` renders `1 p`, `n > 1` renders `n pp`, `0` / empty omitted. Abbreviations are two separate translatable strings (`esc_html_x( 'p', ... )`, `'pp'`).
   - joined by ` · ` (U+00B7 with spaces) only when both parts exist: `PDF · 24 pp`, `PDF`, `24 pp`. Both missing: empty span, no stray separator. Helper `devportfolio_document_format_label( $filetype, $pages )` in `functions.php` (pure, testable without rendering).
5. **Cover style = `data-cover-style` slug, patterns in CSS only.** Six slugs: `midnight`, `pinstripe`, `horizon`, `split-sky`, `dot-grid`, `two-tone`. An unknown, empty or missing value falls back to `midnight` (also the default if DPD-004 ships no meta yet) and is never echoed raw (whitelist lookup, `esc_attr`). The slug list lives in one PHP function `devportfolio_cover_styles()` returning slug => label.
6. **Colours are fixed `--book-*` tokens holding the design system's light-mode values:** `--book-ink #000`, `--book-ink-2 #2c2c2e` (primary-strong), `--book-surface #fff`, `--book-surface-alt #f2f2f7`, `--book-line #d1d1d6`, `--book-subtle #6a6a6f`, `--book-g1 26 214 253`, `--book-g2 29 98 240`, `--book-grad` (135deg g1 to g2), `--book-shadow rgb(12 27 30 / .22)`, `--book-edge rgb(0 0 0 / .1)`, `--book-ease`, `--book-dur 260ms`. Each cover style sets `--cv` (cover), `--ct` (text) and `--cat-c` (category) exactly like the hi-fi; the base rule reads them. White-on-dark and ink-on-light pairs are all above 5:1 (white on `#6a6a6f` is the tightest, about 5.3:1). The gradient is used only by Horizon's sun. When the theme adopts the DS tokens and dark mode, only the token block changes (the hi-fi flips these in dark mode; the book does not yet).
7. **Spine and stitch on every cover, via `::before` / `::after`:** spine `inset: 0 auto 0 0`, 16px, `rgb(0 0 0 / .2)`; stitch 1px dashed `color-mix( currentColor 45%, transparent )` at `left: 21px`, inset 8px top and bottom. Text starts at `padding-left: 32px`, right of both. Mobile (`max-width: 820px`, the hi-fi breakpoint) uses spine 11px, stitch `left: 15px` inset 6px, padding `12px 10px 10px 22px`. Asserted by CSS-structure tests.
8. **Cover size comes from the container, not the block.** `width: 100%`, `aspect-ratio: 2 / 3`; the column count and gaps belong to DPD-016's shelf (4 columns, 2 at 820px per the hi-fi). Layout is the hi-fi grid `auto 1fr auto` with the title centred in the middle row. Border `1px solid var(--book-edge)`, radius `3px 6px 6px 3px`, `overflow: hidden`, shadow `8px 10px 0 var(--book-shadow)`. Type is the hi-fi's fixed sizes (23px title, 10px category and footer) with the 820px media query for the small sizes; no container queries. Titles clamp to 3 lines (the full title sits under the cover in DPD-016). `font-family: inherit`, no serif.
9. **Hover and pressed (DPD-008) are CSS only, no JS, with the hi-fi motion:**
   - Base rule: `transition: transform var(--book-dur) var(--book-ease), box-shadow ...` (260ms, `cubic-bezier(.2,.8,.2,1)`). De-hover is the same transition in reverse.
   - Hover, inside `@media (hover: hover)`: `translateY(-6px) rotate(-1deg)`, shadow `12px 16px 0 var(--book-shadow)`.
   - Pressed: `.documents-book:active { transform: scale(.985) }` on every device (the hi-fi rule, which is what touch devices show on tap; they have no hover). iOS Safari only fires `:active` with a touch listener on the document, so if a real-iPhone check shows no pressed state, add `document.addEventListener( 'touchstart', function () {}, { passive: true } )` in a tiny `view.js` (not built speculatively).
   - `@media (prefers-reduced-motion: reduce)`: `transition: none; animation: none`, no transform.
   - **Hover target:** the hi-fi triggers it from the whole item (`.item:hover .book`); the block uses `.documents-book:hover` and DPD-016 can extend the selector to its item (one line).
   - No `will-change`.
10. **The cover is not interactive here.** The DS covers are `<button>`s that open a viewer on click, Enter or Space, with a 2px `--focus` ring. That behaviour belongs to the item and viewer (DPD-016+): the item wraps the cover in the button / link, so the pressed state above reacts to touches on the cover inside it.

## Contract for DPD-016
- Item: `<article data-document-categories="slug slug">` (direct child of `[data-documents-shelf]`, unchanged from DPD-002/003) contains an interactive wrapper (button per the DS "Opening an item" rule) around `devportfolio/document-book`, then the info block (title, description clamped 2 lines, tags).
- The book renders with the current post from context (`postId`); outside a loop pass it via block context.
- Hover scope: cover only here; item-level hover is a DPD-016 one-line selector change (decision 9).
- The focus ring belongs on the interactive wrapper (`--focus` 2px per the DS), not on the cover.

## Scope
In: the block (markup, category, format line, owner mark), six cover styles with the design system colours, spine and stitch, fallback style, hover / de-hover / pressed / reduced-motion CSS, helper functions, tests, AGENTS.md update.
Out: the shelf, planks, item wrapper, info block, click-to-open and viewer (DPD-016+), the cover style field in wp-admin and its storage (DPD-004, see open points), thumbnails, loading Hanken Grotesk, dark mode, theme-wide adoption of the DS tokens, restyling the existing category pills to the DS `.chipbtn` look (the pills built in DPD-002/003 are still lo-fi; see open point 6), locked/open tags (explicitly not on the cover).

## Steps
Each step is one conventional commit (scope `documents`, per AGENTS.md), in this order. **Tests are never in a feature commit:** every `feat(documents)` commit is followed by its own `test(documents)` commit covering it (precedent: `480a91d` feat, `2addf81` test). Every commit leaves the theme suite green: a feature commit must not break existing tests, and the test commit only adds passing tests. See "Commit sequence" for the full order.

### Step 1 (commit 1 feat, commit 2 test): helpers
In `functions.php`, `function_exists`-guarded, prefix `devportfolio_`:
- `devportfolio_cover_styles()` returns `array( 'midnight' => 'Midnight', 'pinstripe' => 'Pinstripe', 'horizon' => 'Horizon', 'split-sky' => 'Split Sky', 'dot-grid' => 'Dot Grid', 'two-tone' => 'Two-Tone' )` (labels via `_x()`).
- `devportfolio_sanitize_cover_style( $value )`: lower-case, trimmed, whitelist, fallback `midnight`.
- `devportfolio_document_format_label( $filetype, $pages )` (decision 4).
Tests (commit 2, new file `tests/test-document-book.php`, class `Devportfolio_Test_Document_Book`, `@group theme`, later test commits extend it): format label matrix (`PDF`/24, `PDF`/1, `pdf` lower-case input, `PDF`/0, ``/24, ``/0, `"24"` numeric string, negative and non-numeric pages), whitelist (each of 6, unknown, empty, array, mixed case, markup).

### Step 2 (commit 3 feat, commit 4 test): the block, DPD-005
`blocks/document-book/block.json` (`apiVersion 3`, `name devportfolio/document-book`, `usesContext: ["postId"]`, `render: file:./render.php`, `style: file:./style.css`, `supports: { html: false }`), registered in `devportfolio_register_blocks()` (extend the existing function from DPD-002). `render.php`: bail to an empty string if no post or the post type is not `document`; read category (first term, `get_the_terms`), title (`get_the_title`), filetype and pages meta; output the markup of decision 2 with `esc_html` / `esc_attr`; `data-cover-style` through the sanitizer (still `midnight` for everything until commit 5 adds the meta read). `style.css` in this commit: the token block (decision 6), the grid layout, 2:3 box, hi-fi type (decision 8), the title `::after` ornament, a single flat `midnight` fill. No spine, patterns or motion yet.
Tests (commit 4; rendering recipe as in the existing tests: create a `document` post with `wp_insert_post` + meta, `go_to` the archive, `render_block` with block context):
1. Elements appear in order: category, title, format, owner.
2. Format and owner are in the same `__ft` element; owner text is exactly `DAVE`; format text matches `PDF · 24 pp` and `1 p`.
3. No `Open` / `Request` / `Locked` text, no `.tag`, no description element anywhere in the output.
4. Escaping: title `R&D <b>x</b>` and a category named the same render escaped.
5. No category: empty category span present; no filetype / pages: no stray ` · `.
6. Non-`document` post and missing post: empty output. Block is registered.
7. Draft / private document: renders (visibility is the query's job, not the block's).
8. CSS structure: the token block defines every token of decision 6 with the design system values; the `__ttl::after` ornament rule exists (`1.5px`, `currentColor`); the font family is not a serif (no `Georgia` in the file).

### Step 3 (commit 5 feat, commit 6 test): cover styles, DPD-006
Read `cptdp_cover_style` meta in `render.php` (default and unknown = `midnight`). In `style.css` add the spine / stitch pseudo-elements (decision 7), the `__pat` layer and the six styles from the hi-fi. Selectors are `.documents-book[data-cover-style="..."]`, each setting `--cv` / `--ct` (and `--cat-c` where needed):
| slug | `--cv` / `--ct` | pattern |
|------|------|------|
| `midnight` | `ink` / `surface` | `__pat` inset `10px 10px 10px 26px`, 1px border at 24% white (mobile inset `6px 6px 6px 18px`) |
| `pinstripe` | `surface-alt` / `ink` | `repeating-linear-gradient( 135deg, 9% ink 0 1.5px, transparent 1.5px 9px )` |
| `horizon` | `linear-gradient( 165deg, ink-2, ink )` / `surface` | `__pat::after` sun: `top: 15%`, `right: 10%`, `width: 27%`, round, `--book-grad`, glow `0 0 34px 6px` g1 at 35% |
| `split-sky` | `linear-gradient( surface-alt 0 30%, ink 30% )` / `surface`, `--cat-c: ink` | none |
| `dot-grid` | `line` / `ink` | `radial-gradient( 22% ink 1.6px, transparent 2.2px ) 0 0 / 17px 17px` |
| `two-tone` | `linear-gradient( 90deg, ink-2 50%, subtle 50% )` / `surface` | none |
Tests (commit 6; assert on rule structure, per the AGENTS.md gotcha): every style rule sets `--cv` and `--ct`; light styles (`pinstripe`, `dot-grid`) use `--ct: var(--book-ink)` and dark ones `var(--book-surface)`; Split Sky sets `--cat-c` to ink; only Horizon references the gradient or `--book-g1`; no hex literal outside the token block; spine (`inset: 0 auto 0 0`, 16px) and stitch (`dashed`, `left: 21px`, `top`/`bottom: 8px`) values and their mobile values; padding-left 32px clears both; the `__pat` patterns exist (frame, 135deg stripes, dots, 30% / 50% splits); the cover has `aspect-ratio: 2 / 3`, `border-radius: 3px 6px 6px 3px`, hairline border and a flat `8px 10px 0` shadow. Render tests: the `__pat` layer is `aria-hidden` and first; each of the six values appears as `data-cover-style`; unknown / missing / `<script>` fall back to `midnight`.

### Step 4 (commit 7 feat, commit 8 test): hover and pressed, DPD-008
Add the transition to the base rule and the hover / pressed / reduced-motion blocks (decision 9).
Tests (commit 8, CSS structure): the base rule transitions `transform` and `box-shadow` with `--book-dur` and `--book-ease` (no `all`); the `translateY(-6px) rotate(-1deg)` lift and the `12px 16px` shadow exist **only** inside `@media (hover: hover)` (never at top level, so touch devices cannot get stuck in hover); `.documents-book:active` is `scale(.985)`; the `prefers-reduced-motion` block sets `transition: none`, `animation: none` and no transform. JS is not used, so no JS tests.

### Step 5 (commit 9): docs and housekeeping
- AGENTS.md Overview / Theme architecture: add `blocks/document-book/`, the six cover slugs, the `cptdp_cover_style` dependency, the format label rule, the token block and its source (design system), the hover contract, helper names, and the new test file plus updated theme total (38 + new count).
- No plugin version bump unless the cover meta is added here (open point 1).

### Step 6 (commit 10): plan
`docs(plans): add DPD-005 DPD-006 DPD-008 plan`, this file only, last so it reflects what was built.

## Commit sequence
Branch `feat/DPD-005-006-008-Document-Book-Item` from `main`. Do not commit the throwaway shelf fixture (a temporary static or looped block of the 6 styles in `archive-document.html`, deleted before the final commit). Each commit message ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

| # | Message | Contents | Gate before committing |
|---|---------|----------|------------------------|
| 1 | `feat(documents): add document cover helpers` | `functions.php` helpers only | theme suite green (existing tests) |
| 2 | `test(documents): cover document cover helpers` | new `tests/test-document-book.php` with the helper tests | theme suite green |
| 3 | `feat(documents): add document book cover block` | `blocks/document-book/` (json, render, token block and base layout), registration | theme suite green (existing tests) |
| 4 | `test(documents): cover document book block` | block tests 1-8 | theme suite green |
| 5 | `feat(documents): add document cover styles` | `style.css` patterns, spine, stitch, `render.php` meta read | theme suite green, visual check of all six at 1280px and 390px |
| 6 | `test(documents): cover document cover styles` | style structure and render fallback tests | theme suite green |
| 7 | `feat(documents): animate document book hover` | transition, hover, pressed, reduced motion CSS | theme suite green, manual checklist 6-9 |
| 8 | `test(documents): cover document book hover` | hover / pressed / reduced-motion CSS tests | theme suite green |
| 9 | `docs(agents): document book block and cover styles` | `AGENTS.md` | none |
| 10 | `docs(plans): add DPD-005 DPD-006 DPD-008 plan` | this file | none |

Rule: no commit mixes production code and tests. A bug found by the tests is fixed with a `fix(documents):` commit (or amend / fixup before pushing), not folded into the test commit.

Single PR, template `.github/pull_request_template.md`, title `feat(documents): DPD-005 DPD-006 DPD-008 document book cover, styles and hover`.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit   # unchanged, must stay green
```
Manual (browser, `ddev launch` → `/documents/`), with a throwaway fixture of 6 documents, one per cover style, with different categories, file types and page counts (including a 1-page document and one with no pages / no category):
1. Each cover shows, top to bottom: category (top left), title with a short line under it (middle left), `PDF · 24 pp` (bottom left) and `DAVE` (bottom right, same line). One page shows `1 p`.
2. No `Open` / `Request` tag, no description or tags on the cover.
3. The six covers match section C of the wireframe in pattern and the design system in colour and type (compare side by side with the `.book` covers in `documents-hifi-wireframe`): flat offset shadow, 16px spine, inset stitch, Midnight's inner frame, Horizon's sun.
4. Every cover has the spine, the dashed stitch line, and text starting right of the spine (no overlap), including a long title clamped to 3 lines.
5. Text is readable on all six: Pinstripe and Dot Grid use dark text on light, and Split Sky's category is dark on its light top.
6. Desktop mouse: hover lifts about 6px, tilts about 1°, shadow deepens, in about 0.26s; moving off returns smoothly; rapid in/out never leaves it stuck.
7. DevTools device mode with touch: no hover effect after tap (no stuck lifted state); a pressed state (slight scale down) shows while the finger is down. Check on a real iPhone (decision 9, the `:active` caveat).
8. `prefers-reduced-motion: reduce` (DevTools rendering panel): no lift, no tilt, no animation.
9. Cover size stays 2:3 at 1280px and 390px; below 820px the small sizes apply (17px title, 8.5px category).
10. Dark system theme: covers keep their light-mode colours (fixed tokens).

## Risks / open points
1. **DPD-004 (cover style storage) is not in the repo.** Decision needed before commit 5: (a) wait for DPD-004 and use its meta (this plan assumes `cptdp_cover_style`, slugs as in decision 5); (b) add the meta here in the plugin (`cptdp_cover_style`, string, REST-exposed, admin select with the six labels, plugin version bump to 0.17.0, plugin tests, AGENTS.md update) and treat DPD-004 as superseded; or (c) ship commits 1-4 and 7-8 now with everything on `midnight`, and finish commits 5-6 when DPD-004 lands. Default recommendation: (a)/(c); if DPD-004 uses another meta key or storage, only the one line in `render.php` changes.
2. **Design alignment** (see the table): everything follows the hi-fi; the only remaining ticket-driven choice is the 2:3 ratio, which the hi-fi also uses.
3. **Ticket wording vs the hi-fi:** DPD-005 says light text on every cover, DPD-006 a light spine edge and a full-height stitch, DPD-008 about 0.2s. The hi-fi has two light covers with dark text, no spine edge, an inset stitch and 260ms. The hi-fi is followed; the tickets' acceptance text should be updated to match.
4. **Hover scope** (cover vs whole item) depends on DPD-016 markup (decision 9).
5. **iOS `:active`** needs a real-device check; fallback is a no-op touchstart listener.
6. **Theme is still lo-fi.** No Hanken Grotesk, no DS tokens, no dark mode, and the category pills still use the lo-fi outlined style rather than the DS `.chipbtn` (36px, 1px `--line` border, black fill with a check when pressed). The book is self-contained so it does not wait for that; a separate "adopt design system tokens" ticket should replace the book's token block and restyle the pills. Until then the book's font follows whatever the theme loads.
7. **Seeded documents:** no document seeder exists (DPD-007 seeds categories only), so the manual fixture is built by hand; a documents seeder with cover styles is a natural follow-up.
8. **Category on the cover vs multiple categories:** shows the first term only (decision 3).

## Commit messages (full text)
**1**
```
feat(documents): add document cover helpers

Add devportfolio_cover_styles(), a whitelist sanitizer that falls back
to midnight, and devportfolio_document_format_label() which builds the
"PDF · 24 pp" line (1 page shows "1 p"; missing parts are dropped
without a stray separator) (DPD-005, DPD-006).

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**2**
```
test(documents): cover document cover helpers

Add Devportfolio_Test_Document_Book with tests for the format label
(file type and page count combinations, singular page, missing parts)
and the cover style whitelist and its midnight fallback.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**3**
```
feat(documents): add document book cover block

Add the dynamic block devportfolio/document-book (DPD-005). It renders
the cover only: category top left, title with a short ornament line,
file type and page count bottom left and the DAVE mark bottom right on
the same line. No status tag, description or tags on the cover. Type,
spacing and the cover colour tokens follow the design system. Output
is escaped.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**4**
```
test(documents): cover document book block

Cover the order of cover elements, the format and owner sharing one
line, the absence of any Open/Request tag, escaping, missing category
and page data, empty output for non-document posts, and the design
system token block and title ornament.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**5**
```
feat(documents): add document cover styles

Add the six cover styles (midnight, pinstripe, horizon, split-sky,
dot-grid, two-tone) following the hi-fi wireframe, a spine strip and a
dashed stitch line on every cover, with the text starting
right of the spine (DPD-006). The style is read per document and
unknown values fall back to midnight.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**6**
```
test(documents): cover document cover styles

Assert the six style selectors, the spine and stitch rules, the text
clearing the spine, the 2:3 ratio and flat shadow, token-only pattern
colours, and that unknown cover style values render as midnight.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**7**
```
feat(documents): animate document book hover

On a desktop pointer the book lifts 6px, tilts 1 degree and deepens
its flat shadow over 260ms with the design system easing, and returns
smoothly on de-hover (DPD-008). Touch devices get a 1px pressed state
instead of hover (a slight scale down). Reduced motion disables the movement.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**8**
```
test(documents): cover document book hover

Assert the transition on the base rule, that the hover lift sits only
inside a hover: hover media query, that the pressed state sits inside
hover: none, and that reduced motion disables movement.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**9**
```
docs(agents): document book block and cover styles

Record the document-book block, the cover style slugs, the format label
rule, the design system token block, the hover contract and the new
theme tests in AGENTS.md.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
**10**
```
docs(plans): add DPD-005 DPD-006 DPD-008 plan

Plan for the document book cover (DPD-005), its six cover styles
(DPD-006) and the hover and pressed animation (DPD-008).

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
