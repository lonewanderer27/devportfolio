# Plan: Category filter as a dropdown on mobile

Ticket **DPD-041** (Header, Category Filter): *If the screen is quite small (mobile), rather than scrollable chips, I should see a dropdown which shows the active document category. Tapping the dropdown shows a list of categories.*

Branch: create `feat/DPD-041-Category-Dropdown-On-Mobile` **from `main`** (section 3). **Theme only** (`wp-content/themes/devportfolio/`); no plugin change, no plugin version bump. Written so that agents other than the author can build it: every file, selector, string, test name and command is spelled out. Read sections 1-4 first, then pick a work package from section 7.

Related plans (read only if you need the history): `DPD-002-003-025-Header-Categories.md` (the pills, `?category=`, sticky band), `DPD-034-Design-Redesign-According-To-Design-System.md` (tokens, 44px tap areas, glass band), `DPD-035-Reusable-Checkbox-Filter.md` is **not merged into `main`** and is not a dependency of this ticket (see section 12).

---

## 1. Goal and acceptance criteria

At **820px viewport width and below** the category pills are replaced, in the same place, by **one dropdown**:

1. A single trigger chip shows the **active category name** (`All` when none). First paint is correct without waiting for JavaScript (server-rendered from `?category=`).
2. Tapping the trigger opens a list of **all** categories ("All" first, then the terms in name order, empty terms included, exactly the order the pills have today). The active one is visibly filled.
3. Tapping a category applies it (shelf filters exactly as with pills today), updates the trigger label, **closes the list**, and pushes the usual `?category=<slug>` history entry (All = no param).
4. The list also closes on: outside tap, `Esc` (focus returns to the trigger), keyboard focus leaving the control, and the screen growing past 820px.
5. The scrolling single-row strip is gone on phones: **no `overflow-x: auto`** on the group.
6. At **821px and above nothing changes visually**: the wrapped pill row, the same sizes, the same sticky frosted band.
7. The dropdown is a **replacement** for the pill row on small screens, in the same spot (confirmed by the owner). Nothing is added beside it. (`main` has no Software filter yet; whenever DPD-035 lands, its block is neither moved nor restyled by this ticket.)
8. Back/Forward steps through categories, updates the trigger label, and never reloads the page (the popstate rules in `AGENTS.md` still hold: no new popstate listener).
9. Accessible: keyboard operable (section 6.4), the trigger announces its state (`aria-expanded`), the current category is exposed (`aria-pressed` on its row), 44px minimum tap targets, visible focus ring, `prefers-reduced-motion` respected.
10. Both PHP test suites and the Node tests pass (section 9), including new tests for all of the above.

## 2. Out of scope

- Desktop look and behaviour (above 820px).
- Changing the filter model (still single select, "All" synthetic and first, `?category=` URL contract, planks, View Transition).
- The Software checkbox filter (DPD-035): not on `main`, do not add, import or reference it.
- Singular category names (DPD-036: the *filter* stays plural; the trigger shows the same label as the pill).
- Other pages (Videos, Collaterals). Do **not** generalise or rename `document-categories` (see `AGENTS.md`, Conventions).
- A different breakpoint. 820px is the assumption (see section 12 for the three places to change it).

## 3. Baseline and branch: start from `main`

This ticket is built on **`main`** (HEAD `0473260`, Merge PR #38 DPD-036, at the time of writing), **not** on the DPD-035 branch. On `main` the Documents template contains only `document-categories`, `document-shelf` and `document-modal`; there is **no** `checkbox-filter` block, **no** `blocks/shelf-filter.js`, no `shelf:clear-all-filters` event, and `view.js` filters the shelf itself (`filterShelf()`, `renderPlanks()`). Section 5 describes exactly that code. Do not import or recreate anything from DPD-035.

Create the branch:

```bash
git status                       # tracked files must be clean; untracked plan/wireframe files are not yours
git switch main && git pull --ff-only
git switch -c feat/DPD-041-Category-Dropdown-On-Mobile
```

- The working tree contains **untracked** files that belong to other tickets (`.claude/plans/backend/DPB-032..033*`, `DPB-NNN-*`, `.claude/plans/design-system.html`, `.claude/plans/documents/DPD-035-*`, `DPD-037-*`, the two wireframe html files, `.claude/plans/home/`). **Never `git add -A` / `git add .`**; add files by path. The only plan file this ticket commits is this one.
- If `git status` shows modified **tracked** files, stop and ask the user (do not stash or discard them).
- Line numbers below are approximate; search by name.

## 4. File map

| File | Action | Notes |
|---|---|---|
| `blocks/document-categories/render.php` | edit | add trigger and list wrapper (6.1) |
| `blocks/document-categories/style.css` | edit | dropdown styles, replace the phone strip block (6.2) |
| `blocks/document-categories/view.js` | edit | open/close, keyboard, label sync (6.3, 6.4) |
| `blocks/document-categories/block.json` | none | no new assets; `style` and `viewScript` unchanged |
| `tests/test-document-categories-dropdown.php` | **new** | all new tests (markup, CSS, script); class `Devportfolio_Test_Document_Categories_Dropdown`, `@group theme` |
| `tests/test-document-categories.php` | edit (small) | fix `pills()` helper, rewrite one test (section 8) |
| `tests/test-design-system.php` | edit (small) | rewrite three phone-strip tests (section 8) |
| `AGENTS.md` | edit | document the dropdown in the `blocks/document-categories/` entry and update the theme test total (331 on `main`) from the real run |
| `.claude/plans/documents/DPD-041-...md` | this file | commit last |

Read-only context: `templates/archive-document.html`, `theme.json` (`settings.custom`: `glass`, `edge`; top-level `styles.css`: `--site-header-height`, `--site-sticky-top`), `blocks/document-shelf/style.css` (`--documents-gutter`, defined on `.documents-page`).

## 5. What the code does today

`render.php` (49 lines): reads `document_category` terms (`orderby name`, `hide_empty false`), takes the active slug from `?category=` (`sanitize_key`; anything not an existing term slug becomes `''` = All) and prints

```html
<div class="wp-block-devportfolio-document-categories" role="group" aria-label="Document categories">
  <button type="button" class="documents-categories__pill" data-filter="" aria-pressed="true">All</button>
  <button type="button" class="documents-categories__pill" data-filter="reports" aria-pressed="false">Reports</button>
  ...
</div>
```

`view.js` (IIFE, classic script, not a module; 213 lines on `main`): `init( group )` runs on `DOMContentLoaded` for each `.wp-block-devportfolio-document-categories`. It owns the filtering itself: `filterShelf( shelf, slug )` sets `item.hidden` from `data-document-categories`, then `renderPlanks( shelf )`. `apply( slug )` writes `aria-pressed` on every `.documents-categories__pill` and calls `filterShelf`. `applyAnimated( slug )` calls `apply()` directly (no View Transitions, or reduced motion) or inside `document.startViewTransition()`, adding `is-view-transitioning` to the header and the group for the duration (the class gives the group `view-transition-name: documents-categories`; a permanent name would switch off the band's backdrop blur). The `click` handler on the group ignores a click on an already pressed pill, then calls `applyAnimated( slug )`, `history.pushState( ..., urlForSlug( slug ) )` and `pill.scrollIntoView(...)`. `onPopState` (installed by the capture-phase `popstate` listener at the bottom of the file) re-applies the URL's category and calls `event.stopImmediatePropagation()` so WordPress's Interactivity Router does not reload the page; that listener must stay registered when the script runs, never inside `DOMContentLoaded` or a lazy import.

`style.css`: the group is `position: sticky` under the header at every size (`top: calc(var(--site-sticky-top, 0px) + var(--site-header-height, 0px))`, `z-index: 5`), `display: flex; flex-wrap: wrap; gap: 8px`. Large screens: a `::before` layer (`inset: 0 -100vw`) is the full-width frosted band. A `@media (orientation: landscape) and (max-height: 520px)` rule makes the group `position: static`. The **last** block, `@media (max-width: 820px)`, turns the group into the scrolling strip (`overflow-x: auto`, `flex-wrap: nowrap`, bleed margins, glass background and hairline on the group itself, `::before { display: none }`, smaller 34px / 13px pills).

## 6. Design spec

### 6.1 Markup (`render.php`)

Keep everything already computed. Add: a slug-to-name lookup for the active term, a unique list id, and the new elements. Result:

```html
<div class="wp-block-devportfolio-document-categories" role="group" aria-label="Document categories">
  <button type="button" class="documents-categories__trigger [is-active]" aria-expanded="false" aria-controls="documents-categories-list-1">
    <span class="documents-categories__trigger-prefix">Category: </span>
    <span class="documents-categories__trigger-label">Reports</span>
    <svg class="documents-categories__trigger-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
  <div class="documents-categories__list" id="documents-categories-list-1">
    <button type="button" class="documents-categories__pill" data-filter="" aria-pressed="false">All</button>
    ... the existing pills, unchanged ...
  </div>
</div>
```

Rules:
- The existing pill buttons are **unchanged** (same class, `data-filter`, `aria-pressed`, order, labels). They move one level down into `.documents-categories__list`. Nothing else in the theme selects them by parent (`view.js` uses `querySelectorAll( '.documents-categories__pill' )`).
- Trigger label text = the active term's name (`$term->name`), or the same "All" string the pill uses (`_x( 'All', 'document category filter', 'devportfolio' )`) when the active slug is `''`. Escape with `esc_html()`.
- `is-active` class on the trigger only when the active slug is not `''`.
- Prefix: `esc_html_x( 'Category:', 'document category dropdown; read by screen readers before the active category', 'devportfolio' )` followed by a space; visually hidden by CSS (6.2), still read by screen readers.
- List id: `wp_unique_id( 'documents-categories-list-' )`; `aria-controls` on the trigger uses the same value (`esc_attr`). The trigger starts `aria-expanded="false"`.
- The group keeps `role="group"` and the `aria-label` (existing test asserts them).
- WPCS: tabs, Yoda conditions, `esc_*` on all output, variable prefix `$devportfolio_`, text domain `devportfolio`. The wrapper still comes from `get_block_wrapper_attributes()`.
- No-JS note: the pills themselves need JavaScript to filter, so there is nothing to preserve without JS. The trigger is therefore **shown on phones by CSS from the first paint** (no `js` class, no layout shift when scripts load).

### 6.2 CSS (`style.css`)

Edit in place; keep every existing desktop rule. Add the new base rules after the existing pill rules and **keep the `@media (max-width: 820px)` block last in the file** (an existing test anchors on it with `$`, see section 8).

**Base (all sizes), new:**

```css
/* The dropdown only exists on small screens (see the 820px block at the end). */
.documents-categories__trigger {
	display: none;
}

/* On large screens the wrapper vanishes so the pills stay direct flex items of the group. */
.documents-categories__list {
	display: contents;
}

/* Read by screen readers only. */
.documents-categories__trigger-prefix {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: -1px;
	padding: 0;
	overflow: hidden;
	clip-path: inset(50%);
	white-space: nowrap;
	border: 0;
}
```

**Landscape short screen (existing rule, small change):** the group is `position: static` today, which would make it **not** the containing block of the absolutely positioned list. Change that one rule to `position: relative; top: auto;` (same layout: no offsets; `z-index: 5` now applies, which is what it has at every other size). The matching existing test asserts `position: static` and must be updated (section 8).

**Motion, before the final media block:**

```css
@media (max-width: 820px) and (prefers-reduced-motion: no-preference) {
	.documents-categories__trigger[aria-expanded="true"] + .documents-categories__list {
		animation: documents-categories-open 160ms ease;
	}

	@keyframes documents-categories-open {
		from { opacity: 0; transform: translateY(-4px); }
		to { opacity: 1; transform: none; }
	}
}
```

**Final block `@media (max-width: 820px)` (replace the old strip rules):**

```css
@media (max-width: 820px) {
	.wp-block-devportfolio-document-categories {
		--categories-inset: calc(var(--wp--preset--spacing--40) + var(--documents-gutter, 0px));
		flex-wrap: nowrap;
		margin: 0 calc(-1 * (var(--wp--preset--spacing--40) + var(--documents-gutter, 0px)));
		padding: 8px calc(var(--wp--preset--spacing--40) + var(--documents-gutter, 0px)) 10px;
		border-bottom: 1px solid var(--wp--custom--edge, rgb(0 0 0 / 0.1));
		background: var(--wp--custom--glass--tint, rgb(255 255 255 / 0.8));
		-webkit-backdrop-filter: var(--wp--custom--glass--filter, blur(20px) saturate(180%));
		backdrop-filter: var(--wp--custom--glass--filter, blur(20px) saturate(180%));
	}
	/* no overflow-x here: it would clip the list */

	.wp-block-devportfolio-document-categories::before {
		display: none; /* the group itself is the band */
	}

	.documents-categories__trigger { /* chip: design-system pill at phone size */
		position: relative;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		max-width: 100%;
		min-height: 34px;
		box-sizing: border-box;
		border: 1.5px solid var(--wp--preset--color--contrast);
		border-radius: 9999px;
		padding: 0 12px;
		background: var(--wp--preset--color--base);
		color: var(--wp--preset--color--contrast);
		font: inherit;
		font-size: 13px;
		font-weight: 500;
		line-height: 1.4;
		cursor: pointer;
		transition: background-color 160ms ease, color 160ms ease;
	}
	.documents-categories__trigger::after { content: ""; position: absolute; inset: -5px 0; } /* 44px tap area */
	.documents-categories__trigger.is-active { background: var(--wp--preset--color--contrast); color: var(--wp--preset--color--base); }
	.documents-categories__trigger:focus-visible { outline: 2px solid var(--wp--preset--color--contrast); outline-offset: 2px; }
	.documents-categories__trigger-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.documents-categories__trigger-arrow { flex: none; transition: transform 160ms ease; }
	.documents-categories__trigger[aria-expanded="true"] .documents-categories__trigger-arrow { transform: rotate(180deg); }

	.documents-categories__list { /* closed by default */
		display: none;
		position: absolute;
		top: calc(100% + 6px);
		left: var(--categories-inset);
		z-index: 1;
		flex-direction: column;
		gap: 2px;
		box-sizing: border-box;
		width: min(320px, calc(100% - 2 * var(--categories-inset)));
		max-height: min(60vh, 360px);
		overflow-y: auto;
		overscroll-behavior: contain;
		padding: 6px;
		border: 1.5px solid var(--wp--preset--color--contrast);
		border-radius: 12px;
		background: var(--wp--preset--color--base); /* solid: no nested backdrop blur */
		box-shadow: 0 8px 24px rgb(0 0 0 / 0.12);
	}
	.documents-categories__trigger[aria-expanded="true"] + .documents-categories__list { display: flex; }

	/* A pill becomes a full-width row. */
	.documents-categories__list .documents-categories__pill {
		display: flex;
		width: 100%;
		min-height: 44px;
		border: 0;
		border-radius: 8px;
		padding: 0 12px;
		background: transparent;
		font-size: 14px;
	}
	.documents-categories__list .documents-categories__pill::after { display: none; } /* rows are already 44px */
	.documents-categories__list .documents-categories__pill[aria-pressed="true"] {
		background: var(--wp--preset--color--contrast);
		color: var(--wp--preset--color--base);
	}
}
```

Specificity notes for implementers: the base hover rule `.documents-categories__pill:hover:not([aria-pressed="true"])` (inside `@media (hover: hover)`) keeps working for rows (it is more specific than the row rule). The row's explicit `[aria-pressed="true"]` rule is **required**, otherwise the later `background: transparent` would cancel the base active fill. The old 34px/13px phone rule for `.documents-categories__pill` is **removed** (rows are 44px/14px).

The frosted band keeps its blur through the **group's** own `backdrop-filter`; the list is a solid white card so no nested blur is relied on. The group's `view-transition-name` stays opt-in via `.is-view-transitioning` (unchanged).

### 6.3 Script behaviour (`view.js`)

Keep the file an IIFE, classic script. Do **not** touch the capture-phase `popstate` listener at the bottom, and add **no new `popstate` listener**. Do not touch `filterShelf()`, `renderPlanks()`, `slugFromUrl()` or `urlForSlug()`. Changes inside `init( group )`:

1. **Look up** `trigger = group.querySelector( '.documents-categories__trigger' )`, `list = group.querySelector( '.documents-categories__list' )`, `label = trigger && trigger.querySelector( '.documents-categories__trigger-label' )`, `mobile = window.matchMedia( '(max-width: 820px)' )`. If `trigger` or `list` is missing (old cached markup) skip all dropdown wiring; the pills keep working.
2. `syncTrigger( slug )`: find the pill whose `data-filter === slug`; set `label.textContent` to that pill's trimmed `textContent`; `trigger.classList.toggle( 'is-active', '' !== slug )`. Call it at the end of the existing `apply( slug )`. `apply()` already runs inside the View Transition callback, so the label change is part of the group's fade, and it also runs for the initial state, Back/Forward and the no-animation path.
3. `setOpen( open, focusTrigger )`: sets `trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' )`; when closing and `focusTrigger` is true, `trigger.focus()`. Opening is **not** a history entry.
4. **Trigger click:** toggles. If the click came from the keyboard (`event.detail === 0`) and it opened, move focus to the pressed row (`[aria-pressed="true"]`), else to the first row. A touch or mouse open does not move focus.
5. **Pill click (existing handler):** when `mobile.matches`, call `setOpen( false, true )` **before** `applyAnimated( slug )` (the list must be hidden before `startViewTransition` snapshots the group, or the open panel would fade with it). That call goes before the existing "already pressed" early return too, so tapping the active row just closes the list. Only call `pill.scrollIntoView(...)` when `! mobile.matches`.
6. `applyAnimated( slug )` also starts with `setOpen( false )` so Back/Forward closes an open list.
7. **Outside tap:** `document.addEventListener( 'pointerdown', ... )`: if open and `! group.contains( event.target )`, `setOpen( false )` (no focus move).
8. **Focus leaves:** `group.addEventListener( 'focusout', ... )`: if open and `event.relatedTarget` is non-null and outside the group, `setOpen( false )`. Ignore a null `relatedTarget` (iOS Safari does not focus tapped buttons; the pointerdown handler covers that case).
9. **Viewport grows:** `mobile.addEventListener( 'change', function ( e ) { if ( ! e.matches ) { setOpen( false ); } } )`. CSS ignores the open state above 820px anyway; this just resets `aria-expanded`.
10. The initial `apply( initial )` already exists, so a server-rendered label is corrected if it ever disagrees with the client's reading of the URL.

### 6.4 Keyboard and screen reader behaviour

| Where | Key | Result |
|---|---|---|
| Trigger | Enter / Space | toggles (native click; focus moves into the list when it opened, see 6.3 step 4) |
| Trigger | ArrowDown / ArrowUp | opens and focuses the pressed row (ArrowUp: last row), `preventDefault()` |
| A row | ArrowDown / ArrowUp | focus the next / previous row (no wrap), `preventDefault()` |
| A row | Home / End | first / last row |
| A row | Enter / Space | selects (native click on the button) |
| Trigger or a row | Escape | closes, focus returns to the trigger, `stopPropagation()` |
| A row | Tab | native; leaving the group closes it (9) |

Screen reader reading of the trigger: "Category: Reports, button, collapsed". Rows read as toggle buttons ("Reports, toggle button, pressed"). The `aria-pressed` on rows is the single source of truth for the current category; do not add `role="listbox"` / `role="option"` (a disclosure with toggle buttons is simpler and already correct).

## 7. Work packages

Packages touch disjoint files except where noted, so agents can work **in parallel** after reading sections 1-6: **WP-A (markup)** and **WP-B (CSS)** are independent; **WP-C (script)** needs WP-A's markup to test against; **WP-D (docs)** goes last. New tests all live in the new file so two agents never edit the same test file; edits to the two existing test files are listed per package. One conventional commit per package step, **feature commit then its test commit**, both suites green after each. Commit trailer on each: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

### WP-A: markup (`render.php`)
Commit 1 `feat(theme): add the category dropdown trigger and list wrapper`: implement 6.1.
Commit 2 `test(theme): cover the category dropdown markup`: create `tests/test-document-categories-dropdown.php` (copy the `set_up()`, `make_term()`, `render_block_on_archive()` and `tear_down()` pattern from `tests/test-document-categories.php`; class `Devportfolio_Test_Document_Categories_Dropdown`, `@group theme`). Parse with `DOMDocument` + `DOMXPath` and select by class (`contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__pill ')`). Tests:
- `test_trigger_comes_first_and_shows_all_by_default`: trigger precedes the list; label text `All`; no `is-active`; `aria-expanded="false"`.
- `test_trigger_shows_the_active_category_name` (`$_GET['category'] = 'reports'` with terms Reports, Executive Docs): label `Reports`, trigger has `is-active`.
- `test_trigger_falls_back_to_all_for_a_bad_category` with a data provider (`nope`, empty, `all`, markup).
- `test_trigger_label_is_escaped`: a term named `R&D <b>` renders `R&amp;D` and no `<b>` in the label.
- `test_trigger_controls_the_list`: `aria-controls` equals the list's `id`; the list contains the "All" pill first then every term pill in name order, same `data-filter` and `aria-pressed` values as before.
- `test_trigger_has_a_screen_reader_prefix_and_a_hidden_arrow`: prefix span text `Category:`; the `svg` has `aria-hidden="true"`.
- `test_group_role_and_label_are_unchanged`.
- `test_two_blocks_get_different_list_ids` (render twice, ids differ).
Also edit `tests/test-document-categories.php`: change the `pills()` helper to read only `.documents-categories__pill` buttons (it currently reads **every** `button`, which would now include the trigger and break `test_all_is_first_*`, `test_terms_follow_*`, `test_only_all_is_pressed_by_default` and friends).

### WP-B: styles (`style.css`)
Commit 1 `feat(theme): show the category filter as a dropdown on phones`: implement 6.2, including the landscape rule change and the removal of the scrolling strip rules. Keep the 820px block last.
Commit 2 `test(theme): cover the category dropdown CSS`: in the new test file read the CSS with `file_get_contents( $this->theme_dir() . '/blocks/document-categories/style.css' )` and reuse the `rule()` and `media()` helpers from `tests/test-design-system.php` (copy them into the new class; `media()` needs the exact text after `@media `). Tests:
- `test_trigger_is_hidden_and_the_list_is_transparent_to_layout_on_large_screens`: base `.documents-categories__trigger` has `display: none`; base `.documents-categories__list` has `display: contents`; the base `.wp-block-devportfolio-document-categories` rule still has `flex-wrap: wrap`.
- `test_phone_block_is_last_and_has_no_sideways_scroll`: the 820px block exists and ends the file (`/@media \(max-width: 820px\)\s*\{.*\}\s*$/s`); inside it, the group rule has `flex-wrap: nowrap` and does **not** contain `overflow-x`; `::before` is `display: none`.
- `test_phone_trigger_is_a_design_system_chip`: inside the block: `display: inline-flex`, `min-height: 34px`, `font-size: 13px`, `font-weight: 500`, `border: 1.5px solid var(--wp--preset--color--contrast)`, `border-radius: 9999px`; `::after` with `inset: -5px 0`; `.is-active` filled ink; `:focus-visible` outline `2px solid`.
- `test_phone_list_is_a_closed_scrolling_card_that_opens_with_aria_expanded`: list `display: none`, `position: absolute`, `overflow-y: auto`, a `max-height`, solid `background: var(--wp--preset--color--base)`; a rule `.documents-categories__trigger[aria-expanded="true"] + .documents-categories__list` with `display: flex`.
- `test_phone_rows_are_44px_and_the_active_row_is_filled`: `.documents-categories__list .documents-categories__pill` has `min-height: 44px`, `width: 100%`; the `[aria-pressed="true"]` row rule fills with `contrast` and uses `base` text.
- `test_open_animation_respects_reduced_motion`: the `@keyframes documents-categories-open` exists only inside a query that includes `prefers-reduced-motion: no-preference`.
- `test_short_landscape_screen_keeps_the_group_a_containing_block`: the `(orientation: landscape) and (max-height: 520px)` rule for the group has `position: relative` and `top: auto`.
- `test_the_gutter_variable_is_still_used_for_the_bleed`: phone group rule contains `var(--documents-gutter, 0px)` in `margin` and `padding`.
Also edit existing tests (section 8) in the same commit as the CSS change so the suite stays green.

### WP-C: script (`view.js`)
Commit 1 `feat(theme): open, choose and close the category dropdown`: implement 6.3 and 6.4.
Commit 2 `test(theme): cover the category dropdown script`: in the new test file, string assertions on `view.js` (there is no JS runner for this file, same approach as `test_category_script_names_the_header_only_while_a_change_runs`): `.documents-categories__trigger`, `'aria-expanded'`, `window.matchMedia( '(max-width: 820px)' )`, `'Escape'`, `'ArrowDown'`, `'pointerdown'`, `'focusout'`, `syncTrigger`, `scrollIntoView` appears only inside a `! mobile.matches` condition, exactly **one** `addEventListener(\n\t\t'popstate'` / `'popstate'` registration in the whole file (the capture-phase one at the bottom), and the popstate listener is not inside `DOMContentLoaded`. Keep the existing `test_category_script_names_the_header_only_while_a_change_runs` passing.

### WP-D: docs (last)
Commit `docs(agents): document the category dropdown`: in `AGENTS.md`, extend the `blocks/document-categories/` bullet (markup: trigger + list; at 820px and below the pills become rows in a dropdown, no scrolling strip; open state is `aria-expanded` on the trigger; not a history entry; label synced in `apply()`; the landscape-short rule is `position: relative; top: auto` so the list can anchor) and update the theme test total (331 on `main`) from the **actual** run (do not guess). Then `docs(plans): add DPD-041 plan` containing only this file.

## 8. Existing tests that must change (they describe the old scrolling strip)

All run against the CSS/markup as parsed text; there is no browser runner.

| File | Test | Change |
|---|---|---|
| `tests/test-document-categories.php` | helper `pills()` | select only buttons with class `documents-categories__pill` |
| same | `test_group_is_sticky_everywhere_and_a_single_scrolling_row_on_mobile` | rename (e.g. `test_group_is_sticky_everywhere_and_a_dropdown_on_mobile`); keep the sticky and `flex-wrap: nowrap` assertions; **replace** `overflow-x: auto` with `assertStringNotContainsString( 'overflow-x', $rule )`. Its regex `/@media \(max-width: 820px\)\s*\{(.*)\}\s*$/s` requires the 820px block to be the **last** thing in the file: keep it last. |
| `tests/test-design-system.php` | `test_phone_pills_are_a_little_smaller` | now asserts the phone **trigger** is 34px / 13px, and the **row** `.documents-categories__list .documents-categories__pill` is `min-height: 44px` (the helper `rule()` matches `selector\s*\{` after whitespace, so it will find this row rule when asked for `.documents-categories__pill` inside the phone block: ask for the full row selector) |
| same | `test_phone_strip_bleeds_to_the_screen_edges_and_is_the_band_itself` | keep the `margin` assertion and the `::before { display: none }` assertion; **remove** the `overflow-x: auto` assertion (replace with "no `overflow-x`") |
| same | `test_strip_stops_sticking_on_a_short_screen_held_sideways_but_not_on_a_short_portrait_screen` | expect `position: relative` and `top: auto` instead of `position: static` (keep the "no bare `@media (max-height: 520px)`" assertion) |
| same | `test_phone_strip_is_frosted_glass_with_a_soft_hairline`, `test_the_gutter_variable_is_shared_with_the_category_strip`, `test_strip_sticks_under_the_header_at_every_size`, `test_large_screen_strip_has_a_full_width_frosted_layer_behind_the_pills` | **must pass unchanged**; if one fails the CSS drifted from the spec |

`theme.json` has a rule `body:has(.wp-block-devportfolio-document-categories) header.wp-block-template-part > .wp-block-group { border-bottom-color: transparent; }` (header and strip read as one block with one line). It still holds because the group keeps its `border-bottom` on phones. Do not edit `theme.json`.

## 9. Commands

Run inside DDEV. First time only (and after `ddev restart`): `ddev composer --working-dir=wp-content/plugins/custom-post-types-dp install` then `ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"`.

```bash
# theme suite (all)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
# only the categories tests
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter 'Devportfolio_Test_Document_Categories'
# design system tests
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter Devportfolio_Test_Design_System
# JS unit tests (unchanged by this ticket, run to prove nothing regressed)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
# plugin suite: only if something outside the theme was touched (it should not be)
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
```

There is no PHP on the host (run PHP only through `ddev`). The WP test library drops every table in `wordpress_test`; never point it at the dev DB.

## 10. Manual verification (required before the PR; there is no browser test runner)

Use headless Chrome (puppeteer-core) against `https://devportfolio.ddev.site/documents/`, as in DPD-020/026. Seed categories first if the database is empty: `ddev wp cptdp seed document-categories` (and `ddev wp cptdp seed documents` for books); the theme has no build step. Check at widths **320, 390, 820 (boundary, must be the dropdown), 821 (must be pills), 1280, 1920**, portrait and held sideways (667x375):

1. 390px: one chip `All` under the header, no horizontal scroll, no pills visible. Tap: list opens under the chip, 44px rows, `All` filled.
2. Tap `Reports`: list closes, chip reads `Reports` and is filled, URL `?category=reports`, shelf filtered, planks rebuilt, the group fades (View Transition) **without the list in the snapshot**.
3. Open and tap outside (on a book, on the header): closes, nothing else happens. Open and press Esc (attach a keyboard in Chrome emulation): closes, focus on the chip.
4. Back, Forward, Back: label follows, shelf follows, **zero page reloads** (add `window.__loaded = 1` before navigating and confirm it survives).
5. Load `?category=reports` directly: chip says `Reports` immediately (server render). Load `?category=nope`: chip says `All`, URL cleaned.
6. Resize 390 -> 1280 with the list open: pills appear, no stray panel; back to 390: chip, list closed.
7. Landscape 667x375: group is not sticky (existing behaviour), the list still opens directly under the chip (not at the top of the page).
8. A long list (add 12 test terms): the list scrolls inside its card and does not push the page; the page behind does not scroll while the list is scrolled to its end (`overscroll-behavior`).
9. Keyboard (section 6.4 table), VoiceOver or NVDA reading of the chip and rows.
10. The open list sits above the shelf and its stretched item buttons (z-order) and below the sticky header; tapping a book while the list is open only closes the list and does not open the book.
11. `prefers-reduced-motion: reduce`: no open animation, no fade.

## 11. Gotchas collected while reading the code

- `AGENTS.md` rule: the popstate listeners in `view.js` must be registered as the script runs, never in `DOMContentLoaded` or a lazy import, and the category handler must do nothing unless the category really changed. This ticket adds no popstate code.
- The group is the **band** on phones (it carries the glass background). Anything `overflow` on it clips the list; this is why the scrolling strip must go, and why the list is a sibling inside the group rather than a portal.
- `position: static` on the short-landscape rule breaks absolute anchoring; fix per 6.2. Do not make the list `position: fixed` (a `backdrop-filter` ancestor also becomes the containing block for fixed descendants, so it would not escape the band anyway).
- `rule()` helpers in the two existing test files are regex based (`[^}]*` / `[^{}]*`): no nested rules, no `}` inside values, and they return the **first** match. Define the base rules before the phone-block rules, and give new rows the exact selector `.documents-categories__list .documents-categories__pill`.
- Do not add a permanent `view-transition-name` to the group or the list (it would make the band a backdrop root and switch off the blur); the opt-in class stays.
- Do not call `scrollIntoView` for rows on mobile.
- The label shows the same text as the pill (plural term name). DPD-036's singular names are for the book cover, not for this filter.
- **Future merge with DPD-035** (not on `main`): that branch rewrites `view.js` (shared `shelf-filter.js`, `shelf:clear-all-filters`). To keep that later merge small, this ticket's script changes are additive helpers inside `init()` (6.3) plus two one-line additions to existing functions (`syncTrigger()` in `apply()`, `setOpen( false )` in `applyAnimated()`); do not reshape `filterShelf()`/`applyAnimated()`. Whoever merges second resolves conflicts; the list must still close before the View Transition snapshot.
- `AGENTS.md` conventions: do not edit WP core or `twentytwentyfive`; prefer data (`theme.json`) over PHP/CSS, but the shared tokens (`glass`, `edge`) already exist and are consumed here.

## 12. Assumptions and how to change them

- **Breakpoint 820px** ("quite small (mobile)"). It is the width the pills already switch to the strip at and where the shelf changes. If the owner wants 480px: change the media query in `style.css` (three occurrences: the final block, the motion block), the `matchMedia( '(max-width: 820px)' )` string in `view.js`, and the test assertions and `media()` queries that name `(max-width: 820px)` in the new test file and in the edited tests of section 8. Keep the new block last.
- **No separate no-JS state.** Decided: the trigger is visible on phones from first paint; the pills need JavaScript to filter anyway.
- **Trigger filled when a category other than All is active**, as a filter chip that is "on". Cheap to drop (remove the `is-active` toggle and rule) if the owner prefers it always outlined.

- **DPD-035 is separate.** This plan assumes `main`. If DPD-035 merges first, rebase the branch, keep 6.3 steps 5-6 (close before the transition), and re-run all suites; this ticket must not touch the Software filter.

## 13. Definition of done

- Acceptance criteria 1-10 (section 1) met; section 10 checks done and noted in the PR description.
- Theme suite and the document-modal Node tests all green; counts updated in `AGENTS.md` from the real run.
- No change outside `wp-content/themes/devportfolio/`, `AGENTS.md` and `.claude/plans/`; files added by path only, none of the unrelated untracked files.
- Conventional commits, small, feature before test, plan commit last. PR title `feat(documents): DPD-041 category dropdown on mobile`, body from `.github/pull_request_template.md`, ending with the generated-with-Claude-Code line.
