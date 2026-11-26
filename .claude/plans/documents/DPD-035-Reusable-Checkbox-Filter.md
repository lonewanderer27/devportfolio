# Plan: Reusable checkbox filter (shows what is chosen)

Ticket DPD-035 (Medium, User Interface): *As a visitor, I can tick checkboxes for the items I want to see, and I can always see which filters are chosen.*
Branch suggestion `feat/DPD-035-Reusable-Checkbox-Filter`. Theme only; no plugin change, no version bump. Follows the DPD-034 polish pass (`DPD-034-Redesign-According-To-Design-System`), which found that the software tags under each book wrap awkwardly on phones. Builds on `DPD-002-003-025-Header-Categories.md` (the pill filter, the `?category=` URL pattern, the shelf contract) and `DPD-016-Shelf-Grid.md`.

> **Assumption to confirm before step 1.** The request reads as "instead of the tags, let the visitor filter by what they pick": a filter control that (a) shows the chosen filters and (b) lets them tick several options. This plan builds that as a *new, generic block* and uses it first for **software tools** (the data the tags show today) next to the existing category pills. If the intent was to replace the category pills themselves, step 6 covers that as an option. Tell me which, and whether the tags under each book should then go away (see Decision 7).

**Out of scope:** server-side filtering or search (all filtering stays in the browser on the already-rendered shelf), a text search box, sorting, saving the choice across visits (URL only), filtering the Videos and Collaterals pages themselves (the block is built so they can adopt it; wiring them is their own tickets).

## Context (what the code does today)
- `devportfolio/document-categories` renders single-select pills ("All" + one `button[aria-pressed]` per `document_category`). `view.js` filters the shelf in place (items carry `data-document-categories="slug slug"`), keeps the choice in `?category=<slug>`, and animates with a View Transition. Mobile: one sticky, scrolling row.
- `devportfolio/software-tags` prints each item's `software_tool` names as text on the shelf; it is display only and wraps badly in a 150px column.
- Items expose their tools only as text, not as data, so nothing can filter on them yet.
- The shelf contract (`[data-documents-shelf]`, items as direct children, planks owned by the script and rebuilt after every filter) is what any new filter must respect.

## Key decisions
1. **A generic block, not a documents feature.** New dynamic block `devportfolio/checkbox-filter` (`blocks/checkbox-filter/`: `block.json`, `render.php`, `style.css`, `view.js`) that knows nothing about documents. Attributes: `label`, `taxonomy` or `source` key (which list of options to render), `param` (the URL key, e.g. `tools`), `target` (CSS selector of the list to filter, default `[data-filter-list]`), `itemAttr` (the data attribute on each item that holds its values, e.g. `data-filter-tools`), `mode` (`any` or `all`, default `any`). A PHP helper `devportfolio_checkbox_filter_options()` is the one place that turns terms or a post meta list into `{ value, label, count }`, so a later page passes its own options without touching the block.
2. **Markup is a real form control, not styled buttons.** One `<fieldset>` + `<legend>` with one `<label><input type="checkbox">` per option (native keyboard, screen readers, and a no-JS fallback that simply shows everything). No new ARIA beyond what the fieldset gives.
3. **Layout: a trigger and a summary.**
   - A pill-shaped **trigger button** `Software (2)` (design-system chip: 36px, 14px, 44px tap area) that opens a **panel** with the checkbox list, a Clear link and the count of matches (`Show 5 documents`).
   - **Desktop (above 820px):** the panel is a small popover under the trigger (native `popover` attribute, light dismiss, Esc closes).
   - **Mobile (820px and below):** the same markup as a bottom sheet, reusing the existing `bottom-sheet.js` shell if it can be imported from the theme root, otherwise a CSS-only sheet; one decision in step 3, no second implementation of drag-to-dismiss.
   - **The chosen filters are always visible** as removable chips in a row under the controls (`Notion x`, `WordPress x`, then `Clear all`), `aria-live="polite"`, hidden when nothing is chosen. This is the "display the chosen filter" requirement and it replaces the wrapped tags as the way to see a book's tools.
4. **Semantics.** Within one filter, `any` means an item shows if it has at least one ticked value (OR); `all` means it must have every ticked value (AND). Default `any`. Across filters (category pills **and** tools) the result is AND, so a category plus two tools narrows. Options whose count would be 0 under the current other filters are disabled, not hidden, with their count shown (`WordPress (0)`), so the visitor never gets an empty shelf by surprise. An empty result shows a short "No documents match" message with a Clear button.
5. **State lives in the URL**, same pattern as `?category=`: `?tools=notion,wordpress` (comma-separated slugs, bad or unknown values dropped with `replaceState`). `pushState` on change, `popstate` re-applies, other args and the hash are kept. The pure helpers go in `checkbox-filter/filter.js` (`parseParam()`, `withParam()`, `matches( itemValues, selected, mode )`, `counts()`), tested with `node --test` like `pager.js` and `deeplink.js`.
6. **Do not fight `document-categories`.** Both scripts filter the same shelf, so the visibility rule moves to one shared module (`blocks/shelf-filter.js`, a tiny registry: each filter registers a predicate, any change re-runs all predicates, runs the existing View Transition once, then rebuilds the planks). `document-categories/view.js` is refactored to register its predicate instead of toggling `hidden` itself; behaviour and its tests stay the same. This also keeps the popstate-handler ordering rule in `AGENTS.md` intact: listeners are added when the scripts run, never in `DOMContentLoaded` or a lazy import, and each handler does nothing unless its own param changed (so the Interactivity Router never reloads the page).
7. **Tags under each book.** Recommended: keep them (they are the visible reason to open the filter) but make them clickable-free text as today, and fix the phone wrapping separately. Alternative if the owner prefers "chips replace tags": remove `software-tags` from the shelf item and show only the summary chips plus the modal's tags. Needs the owner's call; nothing in steps 1-5 depends on it.
8. **Data.** `render.php` of the shelf item adds `data-filter-tools="slug slug"` from `cptdp_software_tools` (published tools only, through `devportfolio_software_tool_names()`'s source), mirroring `data-document-categories`. The option list is every published `software_tool` that at least one published document uses.
9. **Motion and accessibility.** The shelf change uses the existing View Transition and obeys `prefers-reduced-motion`. Focus stays on the control being used; the live region announces `3 documents shown`. Contrast and sizes follow the design system (chips 14px/36px, 13px/34px on mobile, 1.5px ink outline like the pills).
10. **Reusable by other pages.** Another page adds `<!-- wp:devportfolio/checkbox-filter {"label":"Tool","param":"tools","target":".videos-grid","itemAttr":"data-filter-tools"} /-->` and gives its items the data attribute. The only documents-specific code is the options helper call in the shelf's `render.php`.

## Steps
One conventional commit each, tests separate, both suites green after each.

1. `feat(theme): add the filter options helper and the item data attribute` (`functions.php`, `document-shelf/render.php`), then `test(theme): cover the options and the attribute` (`tests/test-checkbox-filter.php`, `Devportfolio_Test_Checkbox_Filter`).
2. `feat(theme): add the checkbox filter block` (markup, styles, escaping, no-JS fallback), then `test(theme): cover the checkbox filter markup and CSS` (fieldset and labels, counts, disabled zero options, escaping, CSS structure).
3. `feat(theme): filter the shelf by ticked checkboxes`: `filter.js` (pure) and `node --test` tests; `shelf-filter.js` shared module; the trigger, popover and mobile sheet; the chosen-filter chips and the live region.
4. `refactor(theme): let the category pills use the shared shelf filter` (no behaviour change; existing category tests must pass untouched, plus a test that a category and a tool combine with AND).
5. `feat(theme): keep the ticked filters in the URL`: `?tools=`, `popstate`, bad value cleanup; check there are no page reloads on Back/Forward (headless Chrome at 390x844, same flows as the DPD-020 check).
6. (optional, if the pills should become checkboxes) `feat(theme): use the checkbox filter for categories as well`.
7. `docs(agents): document the checkbox filter` (theme architecture, shelf contract, test counts), then `docs(plans): add DPD-035 plan`, this file only, last.

## Risks and checks
- The shelf animates planks and per-item `view-transition-name`s; the shared module must rebuild planks exactly once per change.
- A long option list (many tools): the panel scrolls and the list is capped at about 60vh; a search field is the follow-up if there are more than about 15 options.
- Browser check in headless Chrome at 390, 820, 1280 and 1920px; there is no browser test runner in the repo.

Commit trailer on each: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. PR title: `feat(documents): DPD-035 reusable checkbox filter` (template `.github/pull_request_template.md`).
