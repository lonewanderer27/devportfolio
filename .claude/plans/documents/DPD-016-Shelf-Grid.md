# Plan: Documents Shelf (responsive book grid, planks, info block, category animation)

Ticket DPD-016 (depends on DPD-007 categories seed and DPD-005 book cover). Branch suggestion `feat/DPD-016-Document-Shelf`. Builds on `DPD-005-006-008-Document-Book-Item.md` (cover), `DPD-002-003-025-Header-Categories.md` (the shelf contract and plank script) and `DPD-009-010-011-012-014-015-Document-Modal.md` (the `data-document-id` trigger contract).

**Out of scope:** modal body (DPD-013), mobile viewer (DPD-017/019), empty-category state (Risk 6).

Design source: hi-fi `.claude/plans/documents/documents-hifi-wireframe` (`.shelfzone`, `.shelf`, `.item`, `.info`, `.tags`, `.plank`). Tokens from `.claude/plans/design-system.html`.

## Changes from the ticket (decided during implementation)
| Ticket | Built | Why |
|---|---|---|
| 4 / 3 / 2 columns at 821 / 481 to 820 / 480 | Column count **follows the available width** (`auto-fill`, min 200px, 150px at 820px and below) | Owner wants "just the right amount" per screen |
| (implied) 1040px content width | Documents page is **up to 1440px** wide | At 1040px a large screen only ever showed 4 books |
| Software "pill" tags | **Dot-separated text** (gradient dot), as the hi-fi `.tags` and the modal header | Hi-fi is the design source |
| Title under each book always shown | Shown only when **Settings > Documents > "Show the title under each book"** is on; **off by default** | Owner request |
| (not in ticket) | **View Transitions** animate the books on a category change | Owner request |
| (not in ticket) | Seeder `documents` with 8 sample documents | Acceptance data needs documents |

Kept from the ticket against the hi-fi: bold title (hi-fi 500), description clamp and 2-tag limit at **480px and below** (hi-fi 820px).

## Context
- `archive-document.html` held an uncommitted **TEMP** Query Loop of `Placeholder:` books. It is replaced by the shelf block; TEMP lines are never committed.
- `document-categories/view.js` already defines the shelf contract: shelf `[data-documents-shelf]`, items are **direct children** with `data-document-categories="slug slug"`, filtering toggles `item.hidden`, planks `[data-shelf-plank]` are created by the script (one per row, from the computed `grid-template-columns`, rebuilt after each filter and on resize).
- `document-modal/view.js` opens on a click of any `[data-document-id]` (delegated `closest`).
- Info data already exists: `devportfolio_document_description()` (excerpt, else first 20 words), `cptdp_software_tools`, `document_category` terms, `devportfolio_document_format_label()`.

## Key decisions
1. **One dynamic block `devportfolio/document-shelf`** (`blocks/document-shelf/`: `block.json`, `render.php`, `style.css`), registered in `functions.php`, placed after `document-categories` in `archive-document.html`. Queries published `document` posts (`menu_order ASC, title ASC`, all, `no_found_rows`), renders nothing when there are none. Chosen over a Query Loop because planks and the filter need direct-child items carrying `data-document-categories`.
2. **Item markup** (direct child of the shelf): `article.documents-item[data-document-categories][style="view-transition-name:documents-item-<id>"]` holding the `document-book` cover (rendered through `WP_Block` with `postId` context, no markup duplicated) and `div.documents-item__info` with `h3 > button.documents-item__open[data-document-id][aria-label="Open <title>, <format>"]`, `p.documents-item__desc` (omitted when empty) and `ul.documents-item__tags` (omitted when no tools). The button's `::after` covers the whole item (one real control, valid HTML, one tab stop). Keyboard focus draws an outline on the item (`:has(...:focus-visible)`).
3. **Helpers in `functions.php`** (`function_exists` guarded): `devportfolio_software_tool_names( $post_id )` (published tool names, stored order, **any post type** that stores `cptdp_software_tools`; also used by the modal payload) and `devportfolio_document_shelf_item()` (title, description, format, category slugs).
4. **Grid:** `repeat(auto-fill, minmax(min(100%, 200px), 1fr))`, gaps `40px 32px`; at `max-width: 820px` `minmax(min(100%, 150px), 1fr)`, gaps `28px 16px`, smaller info sizes and plank (11px). No fixed column counts remain. The plank script needs no change for the count (it reads the computed columns, the resize handler re-runs it); it gained `aria-hidden` and the row transition name.
5. **Page width:** `.documents-page` sets `--wp--style--global--content-size: 1440px` (core's constrained layout caps every child at that variable, so a plain `max-width` loses to it) and `padding-inline: clamp(0px, 3vw, 32px)` on top of the theme's root padding, plus `overflow-x: clip`. Title, pills and shelf widen together. Result: 2 books on a phone, about 5 at 1440px, 6 at 1920px. Only the Documents page is wider; the rest of the site keeps 1040px.
6. **Look (hi-fi):** full-width dot grid behind the shelf with a line above and below (`.documents-shelf::before`, `left/right: -100vw`, `z-index: -1`, `isolation: isolate`); plank (14px, gradient, border, soft shadow, `margin-top: -8px`); info (title 17px bold, description 14.5px muted, tags 13.5px muted with a gradient dot between them). Items rise in over 360ms (off when View Transitions are supported and under reduced motion).
7. **Hover and pressed:** because the stretched button covers the cover, the cover never gets the pointer. The lift (`translateY(-6px) rotate(-1deg)`, deeper shadow, `@media (hover: hover)`) and the pressed scale (0.985) are driven from `.documents-item`. Reduced motion disables both.
8. **Software tags are a reusable block** (`blocks/software-tags/`, `devportfolio/software-tags`), because the software used will appear on other screens (videos, collaterals, the modal). `usesContext: ["postId"]`, attribute `limit` (default 2), renders `ul.software-tags` with `li.software-tags__item` for every published tool and nothing when there are none; any post type with `cptdp_software_tools` works. Look: text separated by a gradient dot (hi-fi `.tags`), local colour tokens, size from `--software-tags-size` (13.5px, 12.5px at 820px and below) so a parent can resize it. **Mobile (480px and below):** items after `limit` (`software-tags__item--extra`) are hidden and `li.software-tags__more` (`+N`, N = tools - limit, rendered only when there are extras, `title` lists the hidden names) is shown; above 480px every tag shows. The shelf renders it through `WP_Block` with the item's `postId`, like the cover. What stays in the shelf CSS: the description clamp (2 lines at 480px and below).
9. **Category animation (View Transitions):** the filter already runs in `document.startViewTransition()` (reduced motion or no support = instant). Each item has its own `view-transition-name` (`documents-item-<id>`) and each plank one per row (`documents-plank-<n>`, set in `renderPlanks()`), all with `view-transition-class: documents-item`. `::view-transition-group(*.documents-item)` and old/new animate over 0.4s with the theme ease: a staying book glides to its new spot, a new one fades in, a removed one fades out. `view.js` sets `data-view-transitions` on the shelf when supported so the CSS entrance animation is skipped. Needs Chrome 125+ / Safari 18.2+ for the class; older browsers get the default fade.
10. **Setting "Show the title under each book"** (plugin `includes/settings/documents.php`, Settings > Documents, option `cptdp_documents` `{ show_book_title: bool }`, default **false**, REST-exposed schema, `cptdp_get_documents_settings()`, sanitiser `cptdp_documents_sanitize()`). With it off the shelf gets `documents-shelf--hide-titles`: the title text is not drawn (`font-size: 0`, `line-height: 0`, no `display: none`, no positioning on the `h3`) but the button stays focusable and named by its `aria-label`, and the `::after` still covers the item, so the book still opens the modal. The cover keeps its title. Plugin version bumped to **0.18.0**.
11. **Seeder `documents`** (`includes/seeders/z-documents.php`, `data/seeders/documents.csv`; columns `order,title,description,category,software_tools,filetype,total_pages,author`): 8 published documents, 2 per category, tag counts 0 to 4, one long description, one long title; excerpt = description; categories by slug and tools by title (`|`-separated), validated before any write (a missing term or tool is an error naming the fix); no uploaded files, no cover style (default Midnight; `cptdp_cover_style` is not registered by the plugin yet). The file name starts with `z-` because seeders run in file-name order and it needs `document-categories` and `software-tools` first.

## Steps
One conventional commit each, **tests never in a feature commit**, both suites green after each. Local DB content (below) is not committed.

1. `feat(custom-post-types-dp): seed sample documents`, then `test(custom-post-types-dp): cover the documents seeder`.
2. `feat(custom-post-types-dp): add Documents settings to show the book title`, then `test(custom-post-types-dp): cover the Documents settings`. Includes the version bump to 0.18.0.
3. `feat(theme): add reusable software tags block`: the block, `devportfolio_software_tool_names()` (the modal payload now uses it), registration. Then `test(theme): cover the software tags block`.
4. `feat(documents): add document shelf block`: block files, registration, shelf helper, grid/plank/info/clamp CSS, uses the software tags block, hi-fi look (dot grid, hover from the item, entrance), page width, the hide-title class, per-item view transition names and CSS, template change (TEMP removed). Then `test(documents): cover the document shelf` (includes the transition tests).
5. `feat(documents): animate books with view transitions on a category change`: the `view.js` side (`data-view-transitions`, plank names, plank `aria-hidden`).
6. `docs(agents): document the document shelf and software tags` (Overview: seeder and `--all` order, Settings > Documents, plugin 0.18.0; theme architecture incl. the software tags block; test counts).
7. `docs(plans): add DPD-016 plan`, this file only, last.

Commit trailer on each: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. PR title: `feat(documents): DPD-016 responsive document shelf with planks, info, settings and category animation` (template `.github/pull_request_template.md`).

## Local content (not committed)
The owner's own documents were completed in the local DB only: **Customers** (ID 64) and **EA Workflow** (ID 61): excerpt, category Operations Docs, software tools (Google Workspace, HubSpot, Salesforce; Asana, Notion, Slack, Google Workspace), menu order 35 and 36. The descriptions, category and tools are guesses from the titles and file types and are to be confirmed by the owner. The `Placeholder:` documents stay in the DB and show on the shelf.

## Verification
```bash
ddev wp cptdp seed documents --dry-run && ddev wp cptdp seed documents
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal node --test tests/
```
Today: plugin 464 tests (12 skipped), theme 136 tests (the 2 known Services-link failures in `Devportfolio_Test_Navigation`). Manual on `ddev launch` → `/documents/` (hard refresh; check 375, 480, 481, 820, 821, 1280, 1440, 1920):
1. Books per row follow the width: 2 on a phone, more as it widens, about 5 at 1440px, 6 at 1920px. A plank under every row, including a short last row. Dot grid reaches both screen edges, no horizontal scroll.
2. Title, pills and shelf share one width; side margins stay generous but not wide.
3. Under each book: description and tags with gradient dots (the software tags block). **Titles are hidden by default**; tick Settings > Documents > "Show the title under each book" and the bold title appears. With titles hidden, clicking the book still opens the modal and Tab still stops on each book.
4. At 480px and below: a long description cuts at 2 lines; a book with more than 2 tags shows 2 and `+N`. At 481px both are gone. Reuse check: `<!-- wp:devportfolio/software-tags /-->` in a video or collateral template shows the same tags.
5. Hover a book (cover or text): the cover lifts and tilts; press: it scales down. Reduced motion: none of this.
6. Category pills: books glide to their new spot, new ones fade in, removed ones fade out, planks follow; Back/Forward the same; `?category=` still works; resizing across a column change does not animate. Reduced motion or no View Transitions: instant.
7. Click the item (cover, title area, description): modal opens above 700px; focus returns to that book on close.

## Risks / open points
1. **Auto-fill replaces the ticket's 4/3/2 steps.** Ticket owner should confirm; the "4 / 3 / 2" acceptance lines no longer describe the layout.
2. **Cover sizes** in `document-book/style.css` switch at 820px and assume wide-ish columns; check covers at the narrowest 3-column widths (about 480 to 600px).
3. **Wider Documents page** only; the header and the other pages stay at 1040px. Raising the cap or widening the whole site is a separate decision.
4. **Plank layout shift:** planks are added by JS after load, so the page grows slightly once the module runs. Accepted.
5. **Whole-item click via `::after`** blocks text selection of the description; accepted.
6. **Empty states:** an empty category shows an empty shelf. Not in this ticket.
7. **View Transition class support** (Chrome 125+, Safari 18.2+); older browsers get the default cross-fade. JS and the animation have no browser runner, so they are checked manually.
8. **Setting default hides titles:** a fresh install shows covers only until the setting is ticked (owner's choice); the cover title and the button's `aria-label` keep the name available.
9. **Seeder file name** `z-documents.php` is a deliberate load-order trick; a shared ordering mechanism would replace it if more cross-dependent seeders appear.
