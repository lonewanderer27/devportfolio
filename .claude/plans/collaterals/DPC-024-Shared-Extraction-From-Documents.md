# Plan: Shared extraction from the Documents page (DPC-024)

Tickets:
- **DPC-024** Shared extraction (Foundation, Technical, Highest). Pull out of the Documents page what the Collaterals page needs: the generic category filter (taxonomy + shelf selector), the modal payload builder, the modal shell and viewer wiring (document-id attribute, dialog class names, deeplink key), the format-label helper and the shared tokens. **Do not rename or change Documents behaviour.**

Theme and plugin changes only: no plugin version bump, no new CPT, no new meta. The plugin is not touched.

Related plans (history): `collaterals/DPC-006-014-Collateral-Item-Masonry.md` and `collaterals/DPC-007-008-009-010-011-021-022-Collateral-Modal-Sidebar.md` (the two consumers that already exist), `documents/DPD-034-...` (the *Reference page for future pages* checklist), `documents/DPD-032-...` (deeplink), `documents/DPD-035-Reusable-Checkbox-Filter.md`.

---

## 0. Read first: sharing rule for brand-collateral features

<!-- FUTURE BRAND-COLLATERAL WORK: read this section before adding any collateral feature. The rule below is why this plan exists. -->

Rule: **a collateral feature reuses the shared piece in the table below, or extends it through an argument. It does not copy a Documents function, script or CSS block into a collateral file.** If the piece you need is not shared yet, extract it in your own ticket (AGENTS.md: the second consumer does the extraction), keep the Documents output byte-identical, and add a row here.

| Piece | Where it lives | Documents uses it | Collaterals uses it | Extend with |
|---|---|---|---|---|
| Category pills and `?category=` state | `blocks/document-categories/` now; `blocks/shared/category-filter.js` after Phase 3 | yes (with the shelf) | yes (the event only) | `param`, `onChange` |
| Category name | `devportfolio_document_category_name()` | yes | yes | `$taxonomy` |
| Page-count label (`24 pp`, `1 p`) | `devportfolio_page_count_label()` (Phase 1) | yes | yes | `$kind` |
| Media payload base | `devportfolio_media_payload_base()` (Phase 1) | yes | yes | `$file_meta_key` |
| Download target | `devportfolio_document_download_target()` | yes | yes | `$file_meta_key` |
| Published-post list | `devportfolio_published_ids()` (Phase 1, O1) | yes | yes | `$post_type`, `$args` |
| Per-request lookups | `devportfolio_memo()` (Phase 1, O2) | yes | yes | group and post ID |
| Software tags | `blocks/software-tags/`, `devportfolio_software_tool_names()` | yes | payload only | `postId` context |
| Modal open trigger | `blocks/shared/modal-trigger.js` (Phase 2) | `data-document-id` | `data-collateral-id` | attribute name |
| Deeplink key | `blocks/shared/deeplink.js`, `createDeeplink( param )` | `document_slug` | `collateral_slug` | param |
| Routing and download helpers (JS) | `blocks/shared/` after Phase 2 | yes | yes | none: pure modules |
| Glass, edge and other page tokens | `theme.json` `settings.custom` | yes | yes | token only |
| Stored page sets | plugin: `cptdp_get_document_pages()`, `cptdp_get_collateral_pages()` | yes | yes | none: read-only API |
| Golden-output check | Phase 0 scratchpad script (O7 proposes a repo script) | yes | yes (Collaterals page) | page list |

<!-- FUTURE BRAND-COLLATERAL WORK: if a row has no "yes" for Collaterals and you need one, the usual answer is to extend that row's argument, not to write a second function. -->

---

## 1. Goal and acceptance criteria

1. The Documents page is unchanged for users: the categories, the shelf and the modal produce **the same markup** for the same data. Checked by golden HTML (see Phase 0), not by eye.
2. The shared pieces live once and are used by both pages: the category filter, the modal payload base, the modal wiring (trigger and deeplink), the page-count label, and the tokens both pages use.
3. Every existing suite passes **without assertion changes**: the plugin suite, the theme PHP suite, the modal JS suite (`node --test`), the collateral modal JS and the collateral grid JS.
4. The extraction is recorded in this plan (section 11, As-built), with the list of what moved where and what stayed.

### Baseline: numbers to re-measure

The ticket quotes plugin 726, theme 331, modal JS 153. `AGENTS.md` quotes plugin 829, theme 424, modal JS 153, collateral modal JS 18, collateral grid JS 17. The two sets disagree. **Phase 0 measures the real baseline on the working tree before any edit**, and the acceptance check compares against that measured number, not either quoted one.

## 2. Out of scope

<!-- FUTURE BRAND-COLLATERAL WORK: "out of scope" here means not in this ticket, not "do not share". If a later collateral feature needs one of these, extend the shared piece in section 0; do not copy the Documents version. -->

- Renaming any `document-*` block, class, attribute, event or deeplink key. The categories block keeps `devportfolio/document-categories`; the classes `documents-categories__*`, `documents-modal*`, `documents-shelf*` and the attributes `data-document-id`, `data-documents-shelf`, `data-document-categories` and the `?document_slug=` / `?category=` keys stay. Renaming would change Documents markup and break the byte-identical rule. Naming debt is recorded, not paid (section 8).
- The Collaterals page's own markup, classes and behaviour. The collateral grid and collateral modal are only re-pointed at shared code where the extraction touches them; their output does not change.
- A third page. No generic "shelf" block, no config for fixed values, no abstract base classes (AGENTS: no speculative extension points).
- Changing the Documents payload contents. Only where the code is built (a shared helper), not what it returns.
- Bundling or a build step: the theme has none; shared JS stays plain ES modules.

## 3. Facts established from the repo (do not re-derive)

<!-- FUTURE BRAND-COLLATERAL WORK: these facts were true on the working tree when this plan was written. Line numbers drift; re-check the file and function name before relying on one. -->

Verified in the working tree (line numbers from `wp-content/themes/devportfolio/`):

- **The Collaterals page already exists and already consumes the shared pieces.** `templates/archive-collateral.html` places `devportfolio/document-categories {"taxonomy":"collateral_category"}`, `devportfolio/collateral-grid` and `devportfolio/collateral-modal`. So DPC-024 is not "before building the Collaterals page": it is an extraction that must now work with a second consumer already in place. Both pages stay green throughout.
- **The category filter is half generic.** `blocks/document-categories/render.php` already takes `taxonomy` and `label`. But `blocks/document-categories/view.js` hard-codes the shelf lookup `document.querySelector( '[data-documents-shelf]' )` (line ~103), the class names `.documents-categories__*`, the URL param `category`, and the init selector `.wp-block-devportfolio-document-categories` (line ~333). It dispatches the generic event `devportfolio:category-change` (line ~124, detail `{ slug }`), which the collateral grid already listens to (`blocks/collateral-grid/view.js`, `FILTER_EVENT`). So the Collaterals grid is filtered through the event, and the shelf lookup is a no-op on that page.
- **The collateral modal already reuses Documents modules by import.** `blocks/collateral-modal/view.js` imports `../document-modal/routing.js`, `../document-modal/deeplink.js` (`createDeeplink( 'collateral_slug' )`, line 33) and `../document-modal/download.js`. Shared code therefore lives inside the `document-modal` folder, which is the wrong owner.
- **The Documents modal is hard-wired.** `blocks/document-modal/view.js` reads `.documents-modal-root`, `dialog.documents-modal`, `.documents-mobile`, `.documents-modal__data`; opens on `[data-document-id]` (line ~367); emits `documents:modal-change` and `documents:mobile-change`. The collateral modal has its own copies (`collaterals-modal`, `[data-collateral-id]`, `collaterals:modal-change`).
- **Two payload builders with overlapping keys.** `devportfolio_document_modal_payload()` (`functions.php` line ~571) and `devportfolio_collateral_modal_payload()` (line ~1206) both build `id`, `slug`, `title` (`html_entity_decode` of the title), `description`, `category`, `tools`, `downloadMode`, `downloadUrl`. Their key order differs (documents: `tools` before `category`), and the document payload is written as JSON into the modal, so its order is part of the output.
- **The page-count wording is duplicated in spirit.** `devportfolio_document_format_label()` (line ~405) builds `PDF · 24 pp` and `1 p`, and the collateral pill (`devportfolio_collateral_pill()`, line ~1118, `Flyer · A4 · 2 pp`) and meta line (`devportfolio_collateral_meta_line()`, line ~983) need the same `n pp` / `1 p` part. Only the file-type / paper-size parts differ.
- **Tokens.** Shared tokens already live in `theme.json` `settings.custom`: `edge`, `glass.tint`, `glass.filter` (lines 11-17). Cover tokens (`--book-ink`, `--book-surface`, `--book-line`, `--book-g1/g2`, `--book-shadow`, `--book-edge`, ...) live in `blocks/document-book/style.css` lines 9-19 and are documents-only so far. Literal colours in the collateral and document block CSS have not been audited (Phase 0).
- Block registration: `functions.php` lines 56-62 (`register_block_type` per block directory).

## 4. Decisions

<!-- FUTURE BRAND-COLLATERAL WORK: each decision here is a sharing rule. When you add one, say which pieces it shares. -->

Each has a recommended default. Open ones are in *Open questions* at the end.

1. **Shared JS moves to `blocks/shared/`** (not left inside `document-modal/`): `routing.js`, `deeplink.js`, `download.js`, plus a new `modal-trigger.js` and `category-filter.js`. Ownership: a module used by two blocks belongs to neither. *Cost:* the node test files that import these modules change their import paths (not their assertions). See open question Q1.
2. **Attributes and class names are not renamed.** The shared helpers take the attribute name or selector as an argument. Documents passes `data-document-id`, `[data-documents-shelf]`, `documents-` classes; Collaterals passes its own. No markup change anywhere in Documents.
3. **Category filter split in two.** `blocks/shared/category-filter.js` owns the pill group behaviour (one active pill, `?<param>=<slug>` in the URL with `pushState` / `replaceState` / `popstate`, the dropdown on small screens, the View Transition wrapper, and the synchronous `devportfolio:category-change` event). The shelf-specific part (matching `data-document-categories` items, planks, the plank view-transition names) stays in `blocks/document-categories/view.js` as the Documents consumer, registered through the shared module with its shelf selector. The Collaterals page keeps the event as its only link (it already works that way).
4. **Payload: a shared base, the order kept.** A new helper `devportfolio_media_payload_base( $post_id, $file_meta_key )` returns the keys both payloads share, **in the order the Documents payload already writes them** (so the Documents JSON is byte-identical), and each payload appends its own keys. The collateral payload's key order may change (it is not under the byte-identical rule), but its tests are updated only where they assert order.
5. **Page-count label: one helper.** `devportfolio_page_count_label( $pages, $kind = '' )` returns `24 pp`, `1 p`, `12 slides` or `''`, used by `devportfolio_document_format_label()` (which keeps its signature and output), `devportfolio_collateral_pill()` and `devportfolio_collateral_meta_line()`.
6. **Modal wiring: a trigger and a deeplink, not a base class.** `modal-trigger.js` exports `bindOpen( root, attribute, open )`: one delegated click listener for `[<attribute>]` (`closest`), passing the id. `createDeeplink()` (already generic, takes the param) is used by both. The dialog and state classes stay per page. No shared "modal" object: the two modals differ too much (pages, mobile dialog, pager) to share a shell without distortion. Documents `view.js` and Collaterals `view.js` keep their own state machines.
7. **Tokens: promote only what both pages use with the same value.** Glass and edge already are. Anything else goes to `theme.json` `settings.custom` only if both the Documents and the Collaterals CSS use the same literal. Cover tokens (`--book-*`) stay in `document-book/style.css`: a cover is a Documents-only object. No token is renamed.

## 5. Design details

<!-- FUTURE BRAND-COLLATERAL WORK: the helpers in this section are the ones collateral features call. Add a parameter to them rather than a second function for a collateral. -->

### 5.1 `blocks/shared/category-filter.js` (ES module, DOM)

- `createCategoryFilter( group, { param = 'category', onChange } )`: finds `.documents-categories__pill`, `.documents-categories__trigger`, `.documents-categories__list` (class names unchanged for now: naming debt, section 8), keeps the existing behaviour and returns `{ slug, set( slug ), destroy() }`.
- Calls `onChange( slug )` and dispatches `devportfolio:category-change` synchronously, as now.
- The View Transition wrapper and the `is-view-transitioning` class handling move with it.
- Pure URL helpers (`urlForSlug`, `slugFromUrl`) are exported for tests.

### 5.2 `blocks/document-categories/view.js` (Documents consumer, shrinks)

- Calls `createCategoryFilter( group, { onChange: applyShelf } )`, where `applyShelf( slug )` is today's `apply()` body that filters `[data-documents-shelf]` items and rebuilds planks. The shelf selector stays `[data-documents-shelf]`.
- The init selector `.wp-block-devportfolio-document-categories` stays, so the Documents page is wired exactly as before.

### 5.3 `blocks/shared/modal-trigger.js`

<!-- FUTURE BRAND-COLLATERAL WORK: any new collateral trigger (a new button, a Next thumbnail, a card in another grid) binds through bindOpen(). Do not add a second click listener on the collateral page. -->

- `bindOpen( scope, attribute, open )` attaches one `click` listener on `scope`, finds `event.target.closest( '[' + attribute + ']' )`, reads the value and calls `open( value, trigger )`.
- The Documents `view.js` calls it with `'data-document-id'`; the collateral `view.js` with `'data-collateral-id'`. The Documents listener still checks `dialog.open` the same way as today; only the lookup moves.

### 5.4 `blocks/shared/routing.js`, `deeplink.js`, `download.js`

- Moved from `blocks/document-modal/`. Contents unchanged. `deeplink.js` keeps its `createDeeplink( param )` factory and the `PARAM` export for the Documents key.
- Imports updated in `document-modal/view.js`, `document-modal/mobile.js` and the other Documents modules that import them, and in `collateral-modal/view.js`.

### 5.5 PHP helpers (`functions.php`)

<!-- FUTURE BRAND-COLLATERAL WORK: take the post type, taxonomy or meta key as an argument, and name the helper for what it does. The existing devportfolio_document_* names are legacy: the function is shared, so the name is the debt (section 11, naming debt). Do not add a collateral_* copy of a helper that already takes an argument. -->

- `devportfolio_page_count_label( $pages, $kind = '' )`, placed next to `devportfolio_document_format_label()`.
- `devportfolio_media_payload_base( $post_id, $file_meta_key )`: `id`, `slug`, `title`, `description`, `tools` (the Documents order), `category`, `downloadMode`, `downloadUrl`. The `category` key is the one the two payloads both need; the Collaterals payload today has `category` after `description` and `categories` after it, so the collateral payload appends `categories`, `pill`, `stats`, `thumb` after the base.
- `devportfolio_document_modal_payload()` and `devportfolio_collateral_modal_payload()` become `base + own keys`. The Documents keys keep their order by construction: the base has the Documents order, the document's own keys are appended in the same order as before.

### 5.6 Tokens

- `theme.json` `settings.custom`: only tokens both pages use with the same value (decided in Phase 0 from the audit list).
- Each moved token is replaced by `var(--wp--custom--…)` in the CSS that used the literal. Computed styles are compared in the browser (Phase 5).

### 5.7 Shared query and lookup helpers (Phase 1; optimisations O1 and O2)

<!-- FUTURE BRAND-COLLATERAL WORK: every block that lists published posts or looks up the same post twice goes through these two helpers, so a new collateral block does not add another query for the same list or a second copy of the same lookup. -->

- `devportfolio_published_ids( string $post_type, array $args = array() ): int[]`: `get_posts` with `post_status => publish`, `fields => ids`, `posts_per_page => -1`, `no_found_rows => true`, with the caller's `$args` (such as `orderby`) merged in. Memoised per request by post type and `$args`. Callers: `document-shelf` (`menu_order`, `title`), `document-modal` (no order: the payload is keyed by ID), `collateral-grid` and `collateral-modal` (the order the grid uses; read `collateral-grid/render.php` before the change so the grid order stays the same).
- `devportfolio_memo( string $group, int $post_id, callable $build )`: one static store per request. Used for `devportfolio_software_tool_names()`, `devportfolio_document_category_name()` (per taxonomy), `devportfolio_collateral_preview()` and the collateral category slugs. A document's tools and category, and a collateral's preview and categories, are then computed once per request however many blocks ask.
- Invalidation: clear the store on `save_post`, `transition_post_status`, `delete_post`, `set_object_terms`, `added_post_meta`, `updated_post_meta` and `deleted_post_meta`. Static, not the WordPress object cache: the object cache can persist across requests (Redis) and would serve stale tool names.
- Tests: the theme suite extends `WP_UnitTestCase` directly and creates posts in every test, so the store is cleared in the test `set_up()`. This is the same trap as the plugin's `CPTDP_Test_Case` (AGENTS).
- The output must not change: the Phase 5 golden diff is the check.

---

## 6. Work packages (in order; each leaves every suite green)

Commit grouping in section 9. Every package runs the full baseline before and after.

### Phase 0: baseline and golden output (no code change)

<!-- FUTURE BRAND-COLLATERAL WORK: the golden check built here is the template for every later page. Keep its normalisation list short and written down, so the next page reuses the same check and does not invent its own. -->

1. Run the plugin suite, the theme PHP suite and the four node suites (`document-modal/tests/*.test.mjs`, `collateral-modal`, `collateral-grid`). Record the **measured** counts in section 11. Environment: `ddev start`, and `install-wp-tests.sh` if the test lib is missing (AGENTS).
2. **Golden HTML.** With `ddev` on the seeded local DB, save the rendered HTML of `/documents/`, `/documents/?category=<slug>` for two categories, and `/collaterals/` to the scratchpad (outside the repo). Also save the JSON of the modal data `<script class="documents-modal__data">` and the collateral data script. These files are the byte-identical reference; strip only the per-request `wp_unique_id` counters (`documents-categories-list-N`, `documents-modal-title-N`), and note in the plan that the strip is the only normalisation.
3. Audit the literal colours and sizes in `document-*` and `collateral-*` CSS (grep for hex/`rgb(`/`px` values appearing in both). Output: the token list for 5.6, written into section 11.

### Phase 1: PHP helpers (no markup change)

1. Add `devportfolio_page_count_label()`; switch `devportfolio_document_format_label()` to it. Test: the existing format tests pass unchanged; add one unit test for the label helper (1 p, n pp, slides, empty).
2. Switch `devportfolio_collateral_pill()` and `devportfolio_collateral_meta_line()` to it. Existing collateral tests pass unchanged.
3. Add `devportfolio_media_payload_base()`; rebuild the two payload functions on it. Golden check: the Documents JSON is byte-identical; the collateral JSON may change key order only (its tests are updated only where they assert order).
4. Commit: `refactor(theme): share the page count label and the media payload base`. Tests in a separate commit only if a test is updated (see section 9).
5. **Published lists (O1).** Add `devportfolio_published_ids()` (5.7) and replace the four `get_posts` calls in the `render.php` of `document-shelf`, `document-modal`, `collateral-grid` and `collateral-modal`. Golden check: the shelf and grid order is unchanged.
6. **Per-request lookups (O2).** Add `devportfolio_memo()` (5.7) and use it in `devportfolio_software_tool_names()`, `devportfolio_document_category_name()`, `devportfolio_collateral_preview()` and the collateral category reads. Add the store reset to the theme test `set_up()`. Golden check: no change.
7. Commit: `refactor(theme): read each published list and per-post lookup once per request`.

<!-- FUTURE BRAND-COLLATERAL WORK: a new collateral block calls devportfolio_published_ids() and devportfolio_memo(), never get_posts() or a second copy of the lookup. If the helper does not fit, extend it with an argument. -->


### Phase 2: modal wiring (JS, moved modules)

<!-- FUTURE BRAND-COLLATERAL WORK: a new collateral modal behaviour (a sidebar action, a new trigger) goes in the shared module when another page could use it. Keep it behaviour-neutral for Documents and add a node test for each pure function. -->

1. Create `blocks/shared/`; move `routing.js`, `deeplink.js`, `download.js`. Update every import. Node tests that import them change their path only.
2. Add `modal-trigger.js`; switch the Documents and the collateral `view.js` to `bindOpen()`. Golden check: the Documents markup is unchanged (the trigger is JS only, so markup is trivially the same; the golden check still runs).
3. Browser check (headless Chrome, puppeteer-core, as for DPD-026): open a document from the shelf, Back, Forward, deeplink load; open a collateral, Back, Forward, deeplink load. Same as before the change.
4. Commits: `refactor(theme): move the modal routing, deeplink and download modules to shared` and `refactor(theme): bind the modal triggers through one shared helper`.

### Phase 3: category filter

<!-- FUTURE BRAND-COLLATERAL WORK: the category filter is the piece a new collateral feature (another filter, another grid) is most likely to copy. Do not copy it: pass a different param or onChange. -->

1. Add `blocks/shared/category-filter.js` with the logic moved from `document-categories/view.js`. The Documents script calls it with the shelf `onChange`.
2. Node tests for the pure URL helpers in `blocks/shared/tests/` (the Documents filter has no runner today; the pure part gets one, the DOM part is checked in the browser).
3. Browser check: All / a category / Back / Forward / the mobile dropdown / reduced motion on `/documents/`; the Collaterals filter still filters the grid through the event.
4. Commit: `refactor(theme): move the category pill behaviour to a shared filter`.

### Phase 4: tokens

<!-- FUTURE BRAND-COLLATERAL WORK: when a collateral style needs a colour or size that already exists as a token, use the token. Do not add a literal that matches one. -->

1. Apply the list from Phase 0 step 3. Replace the literals with the `--wp--custom` tokens. No visual change intended.
2. Browser check: computed styles of the listed elements, before and after, on both pages (a small script reading `getComputedStyle` for the audited selectors; the results are compared, not eyeballed).
3. Commit: `refactor(theme): promote the tokens both pages share`.

### Phase 5: tests, docs and the record

<!-- FUTURE BRAND-COLLATERAL WORK: the As-built record (section 11) is what the next collateral ticket reads first. Write it so someone who has not seen this plan can find the shared piece and the rule for using it. -->

1. Full run of all suites: plugin, theme PHP, four node suites. Compare with the Phase 0 measured counts: same passing tests, plus the new ones (label helper, URL helpers).
2. Golden diff: `/documents/`, the category links, the modal payload. Must be empty.
3. Record in section 11 (As-built): what moved where, the measured counts, the golden result, and the naming debt. Also record which section 10 optimisations were done here and which became follow-ups.
4. Update `AGENTS.md` only if a new shared path or helper needs to be known to future agents (`blocks/shared/`, the helper names). One short bullet, not a rewrite.
5. Commit: `docs(plans): record the DPC-024 shared extraction`.

## 7. File inventory

<!-- FUTURE BRAND-COLLATERAL WORK: a new collateral file that duplicates one of these is a review blocker. Check section 0 first. -->

New:
- `wp-content/themes/devportfolio/blocks/shared/routing.js` (moved from `document-modal/`)
- `wp-content/themes/devportfolio/blocks/shared/deeplink.js` (moved)
- `wp-content/themes/devportfolio/blocks/shared/download.js` (moved)
- `wp-content/themes/devportfolio/blocks/shared/modal-trigger.js`
- `wp-content/themes/devportfolio/blocks/shared/category-filter.js`
- `wp-content/themes/devportfolio/blocks/shared/tests/category-filter.test.mjs` (pure helpers)
- `wp-content/themes/devportfolio/tests/test-shared-extraction.php` (page count label, payload base, golden-key order, published-list and memo reset)

Changed:
- `wp-content/themes/devportfolio/functions.php` (page count label, payload base, both payload builders, format label, collateral pill and meta line, `devportfolio_published_ids()`, `devportfolio_memo()`)
- `wp-content/themes/devportfolio/blocks/document-shelf/render.php`, `document-modal/render.php`, `collateral-grid/render.php`, `collateral-modal/render.php` (the four `get_posts` calls become `devportfolio_published_ids()`)
- `wp-content/themes/devportfolio/blocks/document-categories/view.js` (shrinks to the Documents consumer)
- `wp-content/themes/devportfolio/blocks/document-modal/view.js`, `mobile.js` and any file importing the moved modules (import paths only)
- `wp-content/themes/devportfolio/blocks/collateral-modal/view.js` (import paths, `bindOpen()`)
- `wp-content/themes/devportfolio/blocks/document-modal/tests/*.test.mjs` that import the moved modules (import paths only)
- `wp-content/themes/devportfolio/theme.json` (tokens both pages use)
- `wp-content/themes/devportfolio/blocks/*/style.css` where a literal becomes a token
- `.claude/plans/collaterals/DPC-024-Shared-Extraction-From-Documents.md` (this file, As-built)
- `AGENTS.md` (one bullet, only if needed)

Not touched: plugin code, templates (the two archive templates keep their block markup), block render markup, the `document-*` block names.

## 8. Risks

- **R1 Byte-identical output is easy to break silently.** Key order in the payload, attribute order in the markup and whitespace from a moved `echo` all change bytes. Mitigation: golden files from Phase 0, and the Phase 5 diff must be empty for Documents.
- **R2 Import paths in browser ES modules.** A moved file breaks every importer at once, and only in the browser. Mitigation: grep for every importer before moving; browser check after Phase 2.
- **R3 The Collaterals page is live in parallel.** Its tests and modal are the second consumer. A failing collateral test is a regression even if Documents passes. Mitigation: both suites in every commit.
- **R4 View Transitions.** The `view-transition-name` on the pill group and the header is set only during a change (`is-view-transitioning`). Moving the code must keep that exactly: a permanent name turns the header into a backdrop root and switches off its blur (AGENTS). Checked in the browser on the mobile and desktop widths.
- **R5 Naming debt.** The shared pill group keeps the `documents-categories__*` classes, so the Collaterals page carries Documents names. Accepted: renaming changes Documents markup, which the ticket forbids. Recorded in section 11 for the ticket that renames them, once both pages are stable.
- **R6 Scope creep.** A shared "shelf" or "modal shell" is tempting and would be premature (AGENTS: do not generalise ahead of the page that needs it). Only the pieces in section 4 move.
<!-- FUTURE BRAND-COLLATERAL WORK: R6 is the risk most likely to come back with a collateral feature. Share a piece when a second page needs it, not before. -->
- **R7 Stale per-request store.** The store in 5.7 could serve an old tool name or preview after an edit in the same request (an admin save, then a render). Mitigation: the invalidation hooks in 5.7; nothing is cached across requests.
- **R8 An optimisation changes output.** O1 and O2 are output-neutral only if the order and the values stay the same. O3 and O4 (section 10) change the modal payload, so they are not in this ticket.

## 9. Conventional commits

Branch: `feat/DPC-024-Shared-Extraction` from `main` (AGENTS branch rule).

Ordered commits, each leaves every suite green. Commit only when asked.

1. `refactor(theme): share the page count label and the media payload base`
   - Files: `functions.php`, `tests/test-shared-extraction.php`, and the collateral test file only if it asserts key order.
   - Footer: `Refs: DPC-024`
2. `refactor(theme): read each published list and per-post lookup once per request`
   - Files: `functions.php`, the four `render.php` files that call `get_posts`, `tests/test-shared-queries.php`, and the theme test `set_up()`.
   - Footer: `Refs: DPC-024`
3. `refactor(theme): move the modal routing, deeplink and download modules to shared`
   - Files: `blocks/shared/{routing,deeplink,download}.js`, the importers, the node test import paths.
   - Footer: `Refs: DPC-024`
4. `refactor(theme): bind the modal triggers through one shared helper`
   - Files: `blocks/shared/modal-trigger.js`, `blocks/document-modal/view.js`, `blocks/collateral-modal/view.js`.
   - Footer: `Refs: DPC-024`
5. `refactor(theme): move the category pill behaviour to a shared filter`
   - Files: `blocks/shared/category-filter.js`, `blocks/shared/tests/category-filter.test.mjs`, `blocks/document-categories/view.js`.
   - Footer: `Refs: DPC-024`
6. `refactor(theme): promote the tokens both pages share`
   - Files: `theme.json`, the affected `blocks/*/style.css`.
   - Footer: `Refs: DPC-024`
7. `docs(plans): record the DPC-024 shared extraction`
   - Files: this plan, `AGENTS.md` if changed.
   - Footer: `Refs: DPC-024`

Every commit ends with: `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.

PR title (72 characters or fewer): `refactor(theme): share the category filter, modal wiring and payload with Collaterals`
PR body from `.github/pull_request_template.md`, change type `refactor`, ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit.

## 10. Optimisations noticed

<!-- FUTURE BRAND-COLLATERAL WORK: this section is a running list. When you notice something in the Documents or Collaterals code that could be faster, smaller or simpler, add a row with the evidence here. Fix it in your ticket only if it is in your ticket's scope. -->

Found while reading the code for this plan. Call sites were checked with grep; costs are **not measured yet**. The "Where it goes" column says whether DPC-024 takes the item or it becomes a follow-up. Anything that changes output is never in DPC-024.

| ID | Where | What was seen | Proposed | Where it goes | Output change |
|---|---|---|---|---|---|
| O1 | `document-shelf`, `document-modal`, `collateral-grid`, `collateral-modal` `render.php` | Four `get_posts()` calls for the published list. On one page the Documents list is read twice and the Collaterals list twice. | `devportfolio_published_ids()`, memoised per request (5.7) | DPC-024, Phase 1 step 5 | None |
| O2 | `functions.php`: `devportfolio_software_tool_names()`, `devportfolio_document_category_name()`, `devportfolio_collateral_preview()`, the collateral `wp_get_object_terms()` calls | A document's tools are read by the shelf tags and again by the modal payload; its category by the cover and by the payload. A collateral's preview and categories are read by the grid item and again by the modal payload (`functions.php` around lines 1101 and 1206-1241). | `devportfolio_memo()` (5.7) | DPC-024, Phase 1 step 6 | None |
| O3 | `document-modal/render.php` and `devportfolio_document_modal_payload()` (`functions.php` around 571-622) | The archive page builds the full viewer payload for **every** published document (page images, sizes, PDF bytes, stored-page extras), though a visitor opens at most one. The title, description and tools are already in the shelf markup, so the JSON repeats them. `devportfolio_document_viewer_data()` and `devportfolio_document_pages_extras()` both call `cptdp_get_document_pages()` (around lines 741 and 819/846): the stored set may be read twice per document (to verify with a query count). | Measure first: payload bytes and query count at 5 and 50 documents (Query Monitor or `SAVEQUERIES`). If it grows, fetch one document's viewer data on open from a cached REST route, and keep only the list in the page. | Follow-up ticket, with its own golden update and browser check | Yes: the modal JSON |
| O4 | `collateral-modal/render.php` and `devportfolio_collateral_modal_payload()` (around 1206) | The same pattern for collaterals: `thumb`, `stats` and `pill` are built for every collateral up front. | Same measurement; reuse the O3 route if it is built. | Follow-up, with O3 | Yes |
| O5 | `devportfolio_document_modal_payload()` and `devportfolio_collateral_modal_payload()` | Both build the same base keys in separate code. | `devportfolio_media_payload_base()` (Phase 1 step 3) | DPC-024, Phase 1 step 3 | Key order only, for collaterals |
| O6 | `blocks/document-modal/mobile-viewer.js` (1033 lines), `viewer.js` (904), `pages-sheet.js` (652) | Three very large modules. Not measured as a load-time problem; the cost is that the shared pieces are hard to find in them. | Do not split in DPC-024. When a collateral viewer needs one of these, extract the piece it needs then (AGENTS: second consumer). | Follow-up, on demand | None |
| O7 | Golden-output check (Phase 0) | Built as a one-off in the scratchpad. Every later page needs the same check. | Move it into the repo as a small script (location to decide) that saves and diffs the pages it is given. | Follow-up after DPC-024, once the Collaterals page needs it | None |

<!-- FUTURE BRAND-COLLATERAL WORK: before you add a collateral feature that lists or measures posts, check O1 to O4. The same query or payload pattern for collaterals should go through the shared helper, not be repeated. -->

## 11. As-built notes

Branch `feat/DPC-024-Shared-Extraction`, from `main`. Commits: `79517fc` (Phase 1), `412469b` (Phase 2), `d9b148b` (Phase 4). Phase 3 was not done (see below).

**Measured baseline (Phase 0, before any edit):** plugin 829 tests (12 skipped), theme 424, modal JS 153, collateral modal JS 18, collateral grid JS 17. This matches AGENTS.md, not the ticket's 726 / 331. After the work: plugin 829 (unchanged, no plugin code touched), theme 435 (424 existing, unchanged assertions, plus 11 in `tests/test-shared-extraction.php`), modal JS 153, collateral modal JS 18, collateral grid JS 17, shared JS 5 (`blocks/shared/tests/modal-trigger.test.mjs`, new).

**Golden result (Phase 5):** Documents page (`/documents/`, `?category=executive-docs`, `?category=reports`): the shelf, category pills, item markup and the modal payload JSON are **identical** to the baseline. The only difference is seven new `--wp--custom--modal--*` custom properties in the inline `:root` style block (Phase 4), which the golden copy does not have. Collaterals: the markup is identical; the modal payload has the same values, and `tools` moved ahead of `category` (key order only, the change the plan allows). The golden check is a scratchpad script, not in the repo (O7 stays a follow-up).

**Browser checks** (headless Chrome driven over CDP from a scratch script, not puppeteer, not in the repo): `/documents/` open, Back, Forward, category filter, Back/Forward restores the category with no page reload, deeplink load (`?document_slug=`); `/collaterals/` open, Back, deeplink load (`?collateral_slug=`), category pill; no console errors except the existing favicon 404. Plus a computed-style snapshot of every element in both modals and the two grids/pills at 1280px and 390px: identical before and after the token change (Phase 4).

**Token audit (Phase 0 step 3) and what was promoted.** The literals used by both modals (`--dm-*` and `--cm-*`): `#000`, `#6a6a6f`, `#d1d1d6`, `#1c1c1e`, `#fff`, `#f2f2f7`, `rgb(0 0 0 / 0.1)`. Promoted to `theme.json` `settings.custom.modal`: `ink`, `subtle`, `line`, `line-strong`, `surface`, `surface-alt`, `on-primary`. `rgb(0 0 0 / 0.1)` maps to the existing `edge` token. Not promoted: literals used by one page only (the Documents surface `#fff` at the modal's background, the dot grid, the shadows at `rgb(0 0 0 / 0.1)`, the Documents-only `#e5e5ea`), and the cover tokens (`--book-*`, Documents only). The `--cm-line: #6a6a6f` override in the collateral contrast query now reads `--wp--custom--modal--subtle` (same value). Custom property names unchanged.

**What moved where (section 7 as built):**
- Phase 1: `functions.php` gained `devportfolio_page_count_label()`, `devportfolio_media_payload_base()`, `devportfolio_published_ids()`, `devportfolio_term_slugs()`, `devportfolio_memo()`, `devportfolio_memo_store()`, `devportfolio_memo_flush()` and `devportfolio_memo_add_hooks()`. The format label, the collateral pill and meta line use the count helper; both payload builders use the base; the four `render.php` files (`document-modal`, `document-shelf`, `collateral-grid`, `collateral-modal`) call `devportfolio_published_ids()`; software tool names, document category name, collateral preview and the term slug reads go through the memo. The 17 theme test files' `set_up()` clear the memo (guarded call).
- Phase 2: `blocks/document-modal/{routing,deeplink,download}.js` moved (git mv) to `blocks/shared/`. New `blocks/shared/modal-trigger.js` (`bindOpen()`), used by `document-modal/view.js` (`data-document-id`) and `collateral-modal/view.js` (`data-collateral-id`). Importers, node test paths and the PHP assertions that read these files now use the new paths (path strings only; no assertion changed in meaning).
- Phase 4: `theme.json` (`settings.custom.modal`), `blocks/document-modal/style.css` and `blocks/collateral-modal/style.css` (the `--dm-*` / `--cm-*` declarations read the tokens).
- Phase 5: this record and one `AGENTS.md` bullet (`blocks/shared/`), with the stale module paths there corrected.

**Deviations from the plan, and why:**
- **Phase 3 (category filter) not done.** The pill group is already one piece of code: the `document-categories` block renders on both pages with a `taxonomy` attribute, and Collaterals filters through `devportfolio:category-change`. Nothing is copied, so moving it would remove no duplicate. `view.js` is a classic script whose `popstate` listener must register before the Interactivity router (the file's own comment). Turning it into an ES module changes that timing, and the browser checks would be the only proof. Recommended as a follow-up when a second pill group is needed (AGENTS: the second consumer does the extraction).
- **Payload base signature** is `( $post_id, $taxonomy, $title )`, not `( $post_id, $file_meta_key )`. The base holds `id, slug, title, description, tools, category`, the shared prefix of the Documents order. `downloadMode` and `downloadUrl` are not in it: in the Documents payload they come after `fileType`, `format`, `kind` and `pages`, so putting them in the base would reorder the Documents JSON. The title is passed in because Documents use `get_the_title()` (wptexturize) and Collaterals the raw title on purpose.
- **Phase 1 commits** are one commit (`79517fc`), not two: `functions.php` and the test file hold both concerns and splitting them by hunk was not worth the risk. The test file is `tests/test-shared-extraction.php` (the plan's `test-shared-queries.php` was merged into it).
- **Memo invalidation hooks** are added by `devportfolio_memo_add_hooks()`, which the test file calls again in `set_up()`. WordPress restores hooks after each test, so hooks added at first `require_once` are gone for later tests. Without this the invalidation tests failed.
- **Phase 2 is one commit** (`412469b`), not two: the module move and the `bindOpen()` switch touch the same importers.
- **`bindOpen()` has no `dialog.open` check.** The plan (5.3) mentions one, but the current Documents listener has none, so none was added.
- **No `destroy()` or `set()` on the category filter**: Phase 3 was not done, so there is nothing to return.

**Section 10 items:** done here: **O1** (four `get_posts` calls to `devportfolio_published_ids()`), **O2** (memo for tools, category name, collateral preview and term slugs), **O5** (payload base). Follow-ups: **O3** and **O4** (the viewer payload per document and per collateral), **O6**, **O7**.
- Measured (local seeded data, 14 published documents, 6 published collaterals; WP-CLI, `$wpdb->num_queries` around the payload loop): documents **19,522 bytes, 81 queries** (about 1.4 KB and 5.8 queries each); collaterals **2,243 bytes, 25 queries** (about 0.4 KB and 4.2 each). The plan asked for 5 and 50 documents: 50 was not measured (it needs more seeded documents). A linear extrapolation to 50 documents is about 70 KB and about 290 queries, so the follow-up should measure it on a real set first.

**Naming debt kept (R5):** `documents-categories__*`, `documents-modal*`, `documents-shelf*`, `data-document-id`, `document_slug`, `document-*` block names all stay. The Collaterals page carries Documents names through the shared pill group. For the ticket that renames them once both pages are stable.

**Other notes:** the shared palette and `edge` are the only new tokens. The `devportfolio_memo` store is per request and static, so a persistent object cache is not involved. `devportfolio_document_category_name()` and the collateral reads through the memo are cleared by the term and meta hooks; a term edit (singular name) in admin does not fire a meta hook, which only matters inside one request (no such request exists).

## Open questions

- **Q1. Move the shared JS, or leave it in `document-modal/`?** Recommended: move it to `blocks/shared/`. The node test files that import these modules change their import path; their assertions do not change. The ticket's "Documents test suites pass unchanged" is read as "the assertions and results are unchanged", not "no test file is touched". If the strict reading is wanted, leave the modules in place and keep the collateral imports from `document-modal/` (the current state), and move only the new `modal-trigger.js` and `category-filter.js`.
- **Q2. Re-measured test baseline.** The ticket and `AGENTS.md` disagree (726 / 331 against 829 / 424). The plan uses the measured number from Phase 0. No decision needed unless the measured number is unexpected.
- **Q3. Measure O3 and O4 now or later?** Recommended: measure in Phase 0 (payload bytes and query count at 5 and 50 documents), so the follow-up ticket is sized on numbers. This adds about one run of the local site and changes nothing in the code. Otherwise the O3 and O4 follow-up is written without numbers.
