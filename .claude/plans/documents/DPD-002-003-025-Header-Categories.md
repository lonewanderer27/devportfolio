# Plan: Documents Header Categories (pill buttons and in-place filtering)

Tickets DPD-002 (category pills) and DPD-003 (active state and filtering). Branch suggestion `feat/DPD-002-003-Header-Categories`. Builds on `DPH-002-DPD-000-001-Documents-Page.md` (the `archive-document` template and its empty `documents-page__content` container) and `DPD-007-Seed-Document-Category.md` (the 4 seeded `document_category` terms). The shelf itself is DPD-016 (not in this plan). Wireframe: `.claude/plans/documents/documents-lofi-wireframe.html` (`.chip`, `.chip.on`, `.tabs`, `.rch.stk`).

## Context
- `templates/archive-document.html` holds `<h3>Documents</h3>` and an empty `div.documents-page__content`, reserved for the category buttons and the shelf.
- Terms in `document_category`: Executive Docs, Operations Docs, Presentation Decks, Reports (seeder `document-categories`). Terms have no order field; `get_terms()` sorts by name, which equals the seeded order (see DPD-007 "Display order").
- The theme is a tiny block theme: no JS, no build step, `functions.php` has one filter, `style.css` is a header only. Block templates cannot call PHP, and a static block cannot list terms, so the pills need a **dynamic block** (or equivalent) rendered server-side.
- No shelf exists yet, so DPD-003 filtering has nothing to filter. This plan defines the **markup contract** the shelf must follow and ships a filter script that works against it, verified with a test fixture. DPD-016 then only has to emit the contract.
- Lo-fi spec: pill = 1.5px ink border, `border-radius: 20px`, `4px 14px` padding, 13px; active = ink background, white text. Mobile (`.rch.stk`): 11px, single row, scrolls sideways, sticks under the nav, 1px `line` bottom border, white background.

## Key decisions
1. **Dynamic block `devportfolio/document-categories`** registered from the theme (`blocks/document-categories/block.json` + `render.php`, `register_block_type` in `functions.php`). Server-side so the pills always reflect the DB, no hardcoded labels. Pattern slug rule from AGENTS.md applies (`devportfolio/` prefix). Placed in the template inside `documents-page__content`, directly under the title (DPD-002 "one group directly under the title").
2. **"All" is synthetic.** Rendered first, never queried, never stored, always present (even with 0 terms). Slug value `all`. A real term slugged `all` would collide with it, so the filter value for terms is the term slug and "All" uses an empty `data-filter=""` to avoid any collision.
3. **Order = `get_terms( orderby => name, order => ASC )`, `hide_empty => false`.** Matches the seeded order today and stays deterministic for categories added later. Empty categories still show (ticket says each category gets a pill); revisit only if the owner wants them hidden. Alternative `orderby => term_id` follows insertion order exactly but changes if a term is deleted and re-seeded, so it is rejected.
4. **Pills are `<button type="button">` elements** in a `<div role="group" aria-label="Document categories">`, not links. State via `aria-pressed="true|false"` (also the CSS hook for the filled style). Buttons give keyboard activation for free. The active category is mirrored in the URL (decision 9, commit 5).
5. **Progressive enhancement:** without JS the pills do nothing and every book shows (All state is the server default, `aria-pressed="true"` on All). Acceptable for a lo-fi portfolio; noted, not solved.
6. **Filtering is client-side, in place.** Vanilla JS view script (`blocks/document-categories/view.js`, no jQuery, no build step), loaded via `block.json` `viewScript` so it only loads on pages with the block. Hides non-matching items with the `hidden` attribute (no layout shift from partial CSS, accessible).
7. **Row-and-plank layout survives filtering** ("shelf keeps its row-and-plank layout"): the wireframe interleaves a `.plank` element after every 4th item, so hiding items would orphan planks and break rows. Contract: planks are elements with `data-shelf-plank`, owned by the script; after each filter the script removes them and re-inserts one after every Nth *visible* item and after the last visible item (N = number of columns read from `getComputedStyle(shelf).gridTemplateColumns`, so the 4 → 2 column mobile change in the wireframe works). DPD-016 renders the same planks server-side for first paint; the script just regenerates them.
8. **Sticky on mobile:** `position: sticky; top: <nav height>` inside a `max-width: 820px` media query (wireframe breakpoint), `overflow-x: auto; white-space: nowrap; flex-wrap: nowrap`, pills `flex: none`, hidden scrollbar. The nav is core's header part; the sticky offset must equal the header's height, so the header part gets `position: sticky; top: 0` wrapper only if it is not already sticky (verify in browser; if the header is not sticky, pills stick at `top: 0` and the nav scrolls away, which still meets "stays visible while the shelf scrolls" but not "under the navigation" – decide during Step 5).
9. **Active category is stored in the URL (commit 5). Decided: `pushState`, param `category`, bad slug falls back to All.**
   - **Format:** query string `?category=<term-slug>` on the Documents archive (e.g. `/documents/?category=reports`). "All" is the absence of the param (clean `/documents/`), never `?category=all`. A hash (`#reports`) was rejected: it is not visible to PHP, so the server could not render the right pill on first paint, and it collides with anchor links.
   - **On click:** `history.pushState( { category: slug }, '', url )` after the filter runs; no reload, one history entry per switch, so Back/Forward step through categories. A `popstate` listener re-reads the param and re-applies the state (pills and shelf) without pushing again. Clicking the active pill does nothing (no duplicate entry). Other query params and the hash are preserved (build from `new URL( location.href )`).
   - **On load (server):** `render.php` reads `$_GET['category']`, sanitises it with `sanitize_key()`, and only honours it if it equals the slug of an existing term; otherwise All. The matching pill is rendered `aria-pressed="true"` and All `false`, so there is no flash of the wrong state.
   - **On load (client):** `view.js` reads the same param, validates it against the pills' `data-filter` values, and runs the normal filter (and plank rebuild) once the shelf exists. Unknown or empty values fall back to All and the bad param is cleaned from the URL with `replaceState` (not `pushState`, so Back is not polluted).
   - **Why a plain query arg:** `category` is not a registered public query var on the `document` archive, so it does not change the main query or trigger a redirect; it is read only by this block. The reserved `?category_name=` / `cat` core vars are avoided on purpose.
   - **Caching note:** the block output now varies by query string; fine for DDEV and typical page caches (they key on the full URL). No cookies or storage involved.
   - **Decided:** `pushState`, param `category`, bad slug silently falls back to All (no 404).

## Markup contract (for the block and DPD-016)
```html
<div class="documents-categories" role="group" aria-label="Document categories">
  <button type="button" class="documents-categories__pill" data-filter="" aria-pressed="true">All</button>
  <button type="button" class="documents-categories__pill" data-filter="executive-docs" aria-pressed="false">Executive Docs</button>
  ...
</div>

<!-- DPD-016 shelf; any element with data-document-categories is a filterable item -->
<div class="documents-shelf" data-documents-shelf>
  <article data-document-categories="executive-docs reports">...</article>   <!-- space-separated term slugs -->
  <div data-shelf-plank></div>
</div>
```
Script scope: the block's wrapper finds `[data-documents-shelf]` in the document (single shelf per page). A book with no category is only visible under All.

## Scope
In: the block (markup, ordering, "All", URL state), pill styles (desktop wrap, mobile sticky scroll), active state and single-select behaviour, in-place filtering script, plank regeneration, tests, AGENTS.md update.
Out: the shelf and book markup, thumbnails, document viewer (DPD-016+), hiding empty categories, plugin changes (no plugin version bump; no new CPT/meta).

## Steps
Each step is one conventional commit (scope `theme`, per AGENTS.md), in this order. Every commit leaves the theme suite green and is independently revertable; tests ship in the same commit as the behaviour they cover. See "Commit sequence" at the end for the summary.

### Step 1 (commit 1): register the block
`wp-content/themes/devportfolio/blocks/document-categories/block.json` (`apiVersion 3`, name `devportfolio/document-categories`, `render: file:./render.php`, `viewScript: file:./view.js`, `style: file:./style.css`, no editor script, no attributes, `supports` html false). In `functions.php` add `devportfolio_register_blocks()` on `init` calling `register_block_type( get_theme_file_path( 'blocks/document-categories' ) )`. Wrap in `function_exists`, prefix `devportfolio_`, same style as the existing filter.

### Step 2 (commit 1): `render.php` (DPD-002)
- `get_terms( array( 'taxonomy' => 'document_category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) )`; bail to an empty string on `WP_Error`.
- Output the wrapper (`get_block_wrapper_attributes()` with class `documents-categories`), the "All" button first (`aria-pressed="true"`), then one button per term. Escape: `esc_html( $term->name )`, `esc_attr( $term->slug )`. Label "All" via `esc_html_x( 'All', 'document category filter', 'devportfolio' )`.

#### Step 2b (commit 5): URL state (see decision 9)
- `render.php`: read `$_GET['category']` (`sanitize_key( wp_unslash( ... ) )`, no nonce needed: read-only display state), validate against `$devportfolio_terms` slugs, set `aria-pressed` accordingly; unknown value = All.
- `view.js`: add `getActiveFromUrl()` / `setUrl( slug )` (`''` deletes the param); apply on `DOMContentLoaded` before binding clicks; call `setUrl` (`pushState`) after a successful click switch; add a `popstate` listener that re-applies from the URL. Keep `filterShelf` pure.
- Tests (in `test-document-categories.php`): with `$_GET['category'] = 'reports'`, Reports is pressed and All is not; unknown slug and empty value render All pressed; value is not echoed raw (`<script>` as value changes nothing); an existing term slug that matches no pill never errors. Client behaviour is added to the manual checklist (Verification 7-9).

### Step 3 (commit 2): template
In `templates/archive-document.html`, inside `documents-page__content`, insert `<!-- wp:devportfolio/document-categories /-->` directly under the `<h3>`. Keep the container otherwise empty for DPD-016. Margins from the wireframe: 16px under the title (already on the h3 style), 30px below the pills.

### Step 4 (commit 3): styles (`blocks/document-categories/style.css`, loaded only with the block)
Use palette CSS variables (`--wp--preset--color--contrast|base`), not literals.
- Base `.documents-categories__pill`: `border: 1.5px solid contrast; border-radius: 20px (use 9999px so any size stays fully rounded); padding: 4px 14px; font: inherit at 13px; background: base; color: contrast; cursor: pointer`.
- `[aria-pressed="true"]`: `background: contrast; color: base`.
- `:focus-visible` outline (keyboard users), no `:hover` fill change that could be mistaken for the active state (use `border-color`/underline-free subtle change or none).
- Desktop: `display: flex; flex-wrap: wrap; gap: 8px; align-items: center`.
- `@media (max-width: 820px)`: `flex-wrap: nowrap; overflow-x: auto; white-space: nowrap; position: sticky; top: <header height>; background: base; border-bottom: 1px solid line; padding: 6px 14px 10px; z-index: 5`; pills 11px, `padding: 2px 10px`, `flex: none`; hide scrollbar (`scrollbar-width: none`), keep `-webkit-overflow-scrolling: touch`.

### Step 5 (commit 4): `view.js` (DPD-003)
- On click of a pill (event delegation on the wrapper): if it is already the active one, do nothing (stays active). Otherwise set `aria-pressed="false"` on the previous, `"true"` on the new (exactly one active).
- Filter: for each `[data-document-categories]` in the shelf, `hidden = filter !== '' && !slugs.split(/\s+/).includes(filter)`.
- Regenerate planks (decision 7) after every filter and on `resize` (debounced, because the column count changes at 820px). Skip planks when no items are visible.
- No fetch, no reload, no `location` changes. Initial state: do nothing on load beyond binding (server already renders All active and all books visible).
- Mobile: scroll the active pill into view (`scrollIntoView({ inline: 'nearest', block: 'nearest' })`) after a click.

### Step 6 (split across commits 1-3): tests (`tests/test-document-categories.php`, class `Devportfolio_Test_Document_Categories`, `@group theme`)
Tests are written with the commit that introduces the behaviour: cases 1-5 and 8 in commit 1, case 6 in commit 2, case 7 in commit 3. Extend `WP_UnitTestCase`. `set_up()` registers terms in `document_category` (the plugin is loaded by the theme bootstrap; terms are wiped between tests, so create them per test). Cover:
1. First pill is "All", `aria-pressed="true"`, `data-filter=""`, present with zero terms and with N terms.
2. Remaining pills = term names in name order (create terms out of order to prove ordering), exact label equals term name, one pill per term (count = terms + 1).
3. Exactly one pill has `aria-pressed="true"` in the server output.
4. Escaping: a term named `R&D <b>x</b>` renders escaped.
5. Empty terms are listed (`hide_empty => false`).
6. Block is in `archive-document.html` after the `<h3>` and inside `documents-page__content` (parse the template file), and the block is registered (`WP_Block_Type_Registry`).
7. `style.css` contains the pill rules: rounded radius, `[aria-pressed="true"]` filled, mobile media query with `position: sticky` and `overflow-x: auto`. Assert on structure per the AGENTS.md gotcha, not only presence of a string.
8. Rendering recipe: `do_blocks( '<!-- wp:devportfolio/document-categories /-->' )` after `go_to( get_post_type_archive_link( 'document' ) )`, same as the navigation tests; re-register the block's hooks in `set_up()` if the test lib restores them.

JS has no runner in this repo and none is added (no build step). The behaviour in Step 5 is covered by the manual checklist below; keep `view.js` small and pure (a `filterShelf( shelf, slug )` function with no globals) so a runner can be added later without a rewrite.

### Step 7 (commit 6): docs and housekeeping
- AGENTS.md Overview/Theme architecture: add the `blocks/document-categories/` entry, the "All is synthetic" rule, the markup contract (`data-filter`, `data-document-categories`, `data-documents-shelf`, `data-shelf-plank`), and the new test file with updated expected theme total (15 + new count).
- No plugin version bump (no plugin changes).

## Commit sequence
Branch `feat/DPD-002-003-Header-Categories` from `main`. Do not commit the throwaway shelf fixture (keep it unstaged and delete it before the final commit). Each commit message ends with the attribution line required for this session.

| # | Message | Contents | Gate before committing |
|---|---------|----------|------------------------|
| 1 | `feat(theme): add document category pills block` | `blocks/document-categories/block.json`, `render.php`, `functions.php` registration, tests 1-5 and 8 | theme suite green |
| 2 | `feat(theme): place category pills under documents title` | `templates/archive-document.html`, test 6 | theme suite green |
| 3 | `feat(theme): style document category pills` | `style.css` (desktop wrap, active fill, mobile sticky scroll), test 7 | theme suite green, visual check at 1280px and 390px |
| 4 | `feat(theme): filter documents shelf by category` | `view.js`, `viewScript` in `block.json` | manual checklist (Verification 2-6) with the fixture |
| 5 | `feat(theme): keep active document category in the url` | `render.php`, `view.js`, URL tests (Step 2b) | theme suite green, manual checklist 7-10 |
| 6 | `docs(agents): document category pills and filter contract` | `AGENTS.md` (incl. the `?category=` param) | none |
| 7 | `docs(plans): add DPD-002 DPD-003 plan` | this file only, last so it reflects what was actually built | none |

Notes: the plan is committed last (commit 7), after the implementation, so any deviations found while building can be folded in first. Until then it stays untracked. Commit 1 alone renders nothing visible (block not yet in a template), which is intended so the markup and ordering are reviewed before presentation. Commit 4 is a `feat` (new user-facing behaviour, DPD-003). If a commit gate fails, fix forward in a `fix(theme):` commit only after the original is pushed; before pushing, amend or fixup instead. One PR for the branch, description following `.github/pull_request_template.md`, titled `feat(theme): DPD-002 DPD-003 document category pills and filter`.

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit   # unchanged, must stay green
ddev wp cptdp seed --all
```
Manual (browser, `ddev launch` → `/documents/`), with a throwaway shelf fixture (temporary static HTML in the template, **removed before commit**) of at least 2 books in different categories:
1. Pills appear directly under the title: All, Executive Docs, Operations Docs, Presentation Decks, Reports. All is filled, every book shown.
2. Click Reports: Reports filled, All outlined, only Reports books visible, no reload (network tab quiet, scroll position kept), planks sit under complete rows.
3. Click Reports again: still active. Click All: all books back.
4. Desktop 1280px narrowed: pills wrap to a second line. ≤820px: single scrolling row; scroll the page and the row stays pinned under the navigation.
5. Keyboard: Tab to a pill, Space/Enter selects, focus ring visible.
6. JS disabled: All active, all books visible, nothing broken.
7. Click Reports: URL becomes `/documents/?category=reports` with no reload; click All: URL is `/documents/` again.
8. Open `/documents/?category=reports` directly: Reports is filled on first paint and only Reports books show; `?category=nope` falls back to All.
9. Existing query args and hash survive a switch (`/documents/?foo=1#x`).
10. Click Reports then Executive Docs, press Back: Reports is active and filtered; Back again: All; Forward restores Reports. Manual checklist for commit 5 is 7-10.

## Risks / open points
- **Shelf not built:** filtering is verified only against a fixture until DPD-016. Mitigated by the written contract; DPD-016's plan must reference it.
- **Sticky under the nav** depends on whether the header part is sticky and its height (core mobile overlay menu); settle in Step 5 with a browser check, and keep the offset in one CSS custom property.
- **Plank regeneration** relies on CSS grid column count; if DPD-016 uses another layout (flex/masonry) the column read must change.
- Empty categories show a pill that yields an empty shelf; consider an empty-state message in DPD-016.

## Commit messages (full text)
Subject lines are imperative, lower case, under 72 characters. Each ends with the attribution trailer.

**1**
```
feat(theme): add document category pills block

Add the dynamic block devportfolio/document-categories (DPD-002). It
renders an "All" button first, which is never stored and always
present, then one button per document_category term in name order
(the DPD-007 seeded order), including empty terms. Buttons carry
data-filter and aria-pressed; All is pressed by default. Output is
escaped.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

**2**
```
feat(theme): place category pills under documents title

Insert the document categories block into archive-document.html,
inside documents-page__content directly under the title (DPD-002).
The container is otherwise left empty for the shelf (DPD-016).

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

**3**
```
feat(theme): style document category pills

Outlined, fully rounded pills with a filled dark active state
(DPD-002, DPD-003). Desktop wraps onto further lines; at 820px and
below the pills sit in one horizontally scrolling row that sticks
under the navigation. Colours come from the theme palette.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

**4**
```
feat(theme): filter documents shelf by category

Add a view script that makes one category active at a time and
filters the shelf in place without a reload (DPD-003). All shows
every item; clicking the active pill keeps it active. Planks are
regenerated after each filter and on resize so the row-and-plank
layout holds. Items opt in with data-document-categories.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

**5**
```
feat(theme): keep active document category in the url

Mirror the active category in the query string (?category=<slug>)
without a reload; All is the absence of the param. The block reads the
param on render so the right pill is pressed on first paint, and the
script re-applies it on load. Unknown values fall back to All.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

**6**
```
docs(agents): document category pills and filter contract

Record the document-categories block, the synthetic "All" rule, the
shelf markup contract and the new theme test file in AGENTS.md.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

**7**
```
docs(plans): add DPD-002 DPD-003 plan

Plan for the Documents header category pills (DPD-002) and their
active state and in-place shelf filtering (DPD-003).

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```
