# Plan: DPB-007 + DPB-008 + DPB-012 (Document Category, Document, Post Statistics), phased

Branch: `feat/DPB-007-008-012-Document`. One PR, four phases (plus a plan-only Phase 0). Each phase is split into small atomic Conventional Commits, one concern per commit (see Commit convention). Each phase leaves the plugin working and passes its own gate before the commit. Mirrors the Video work (`DPB-006-011-018-Video.md`); `video.php`, `video-category.php`, `admin-video.js` are the templates.

Tickets:
- DPB-007: `document_category` taxonomy
- DPB-008: `document` CPT (depends DPB-000, DPB-007, DPB-012)
- DPB-012: `cptdp_post_statistics` repeater on Document (depends DPB-008)

## Resolved decisions
| Topic | Decision |
|---|---|
| Build order | Taxonomy (007) → CPT shell + fields (008) → statistics (012). DPB-008 lists 012 as a dependency, but the repeater is a meta box on the CPT, so it lands after the CPT exists. The ticket's "post_statistics" and "10. create post statistics" acceptance rows are satisfied once Phase 4 lands. PR is not complete before then. |
| DPB-012 precondition typo | Ticket says "A video record exists". Read as "A Document record exists". Flag to ticket owner, do not build against Video. |
| Document Category | Flat taxonomy, `public` true, same shape as `video_category`. Keep default tag box (multi), same as Video. Spec says "foreign id" but ticket AC says "available for assignment", so multi is fine. |
| Taxonomy menu | Temporary top-level "Document Categories" menu only while `document` post type does not exist (same trick as `video-category.php`). Once Phase 2 lands it lists under Documents. |
| Document file | Meta `cptdp_document_file` (attachment ID). Any attachment type accepted by the WP uploader, but must exist and not be an image/video/audio attachment (`wp_attachment_is` negative checks), else row deleted. Picker uses `wp.media()` with no type filter. |
| File type | Meta `cptdp_filetype`, free text string (spec: "enter a File Type value"), `sanitize_text_field`, e.g. `PDF`. No auto-detect from file. |
| Author | Meta `cptdp_author`, string. Prefixed to avoid clash with core post author (`author` is not in `supports`). |
| Total pages | Meta `cptdp_total_pages`, `absint`, empty → delete. |
| Embed URL | Meta `cptdp_embed_url`, http/https only, local `cptdp_document_sanitize_url` (no cross-module dependency). |
| Highlight | Meta `cptdp_highlight`, store `1`, delete when unchecked. Label "Mark as highlight (Selected work)". |
| Post statistics | Rows `{ label, value }` strings, same shape as Video. Own sanitizer `cptdp_document_sanitize_statistics()`, own JS. No shared helper with Video. |
| Editor | Classic, same as other CPTs. |
| File / embed | Both optional, no "at least one" validation. |

## Commit convention
Spec: Conventional Commits 1.0. Matches existing history (`feat(custom-post-types-dp): ...`, `docs(plans): ...`, `docs(agents): ...`).

- Format: `<type>(<scope>): <subject>`. Subject imperative, lowercase after the colon, no trailing period, ≤72 chars. No ticket key in the subject; put it in the footer.
- One concern per commit. Each commit leaves the plugin activatable (`php -l` clean, no notices).
- Types and scopes used here:

| Concern | Type(scope) |
|---|---|
| Plugin PHP / JS code | `feat(custom-post-types-dp)` |
| Plan files | `docs(plans)` |
| `AGENTS.md` updates | `docs(agents)` |
| Version bump (header + `CPTDP_VERSION`) | `chore(custom-post-types-dp)` |
| Fix found during a phase gate, in already-committed code | `fix(custom-post-types-dp)` |

- Body only when the why is not obvious. Wrap at 72.
- Footer: `Refs: DPB-007` (or 008 / 012) on every code commit, then the trailer `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- No `BREAKING CHANGE`: nothing here changes existing behaviour.
- PR title uses the same format for the squashed result: `feat(custom-post-types-dp): add Document post type with category and statistics`. PR body follows `.github/pull_request_template.md` (tick "New feature"; Description, Implementation, How to Test filled from the phase gates; Screenshots table can stay empty or show the admin screens).

## Versions
Plugin is currently at `0.9.0` (header `Version:` and `CPTDP_VERSION`). Bump once per ticket, in that ticket's last phase, as its own `chore` commit, header and constant together.

| Ticket | Last phase | Bump |
|---|---|---|
| DPB-007 Document Category | Phase 1 | 0.9.0 → 0.10.0 |
| DPB-008 Document CPT | Phase 3 | 0.10.0 → 0.11.0 |
| DPB-012 Post Statistics | Phase 4 | 0.11.0 → 0.12.0 |

Exactly three bumps in the PR, each a standalone `chore(custom-post-types-dp): bump version to 0.x.0` commit touching only `custom-post-types-dp.php`. Never skip or batch them. After each bump:
- `grep -nE "Version:|CPTDP_VERSION'" wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php` shows the same version in both places.
- `git show --stat HEAD` lists only that file.

Final check: `git log --format=%s main..HEAD | grep -c "bump version"` → `3`.

## Files
Create:
```
wp-content/plugins/custom-post-types-dp/includes/post-types/document-category.php   (Phase 1)
wp-content/plugins/custom-post-types-dp/includes/post-types/document.php            (Phases 2-4)
wp-content/plugins/custom-post-types-dp/assets/admin-document.js                    (Phases 3-4)
```
Edit: `AGENTS.md` (each phase), `custom-post-types-dp.php` (version only).

No loader change (glob picks up `includes/post-types/*.php`). No `.gitignore` change (pre-existing local diff stays unstaged and untouched).

## Rules for every phase
- Stage explicit paths only. Never `git add -A`; `.gitignore` stays out.
- `AGENTS.md`: if it has a pre-existing local diff, run `git diff AGENTS.md` first, keep unrelated hunks out of feature commits (`git add -p`), or flag them.
- Gate before commit: `ddev exec php -l` on each changed PHP file; `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp`; no notices in `ddev logs`; the phase's checks pass; test data deleted.
- Commit messages follow Commit convention above. Commits are GPG-signed from global git config (`~/.gitconfig`). Do not pass `-S`, `--no-gpg-sign`, or `-c user.*` / `-c commit.gpgsign` overrides. Before the first commit run `git config --show-origin --get-regexp 'user\.|commit\.gpgsign'`; after each commit run `git log -1 --show-signature` and confirm a good signature. If signing fails, stop and report, never fall back to unsigned.
- If a gate fails, fix inside the phase. No commit on red.

---

## Phase 0: plan
Commits:
1. `docs(plans): add DPB-007, DPB-008, DPB-012 plan for Document`

Stage only `.claude/plans/DPB-007-008-012-Document.md`.

Gate: `git status` shows only the plan file staged.

## Phase 1: Document Category taxonomy (DPB-007)
Commits, in order:
1. `feat(custom-post-types-dp): add Document Category taxonomy` — `document-category.php` (taxonomy, temp menu, parent_file filter). Footer `Refs: DPB-007`.
2. `docs(agents): add document category taxonomy` — `AGENTS.md`.
3. `chore(custom-post-types-dp): bump version to 0.10.0` — `custom-post-types-dp.php`.

Files: `document-category.php` (new), `AGENTS.md`, `custom-post-types-dp.php` (→ 0.10.0).

- Mirror `video-category.php`. `register_taxonomy( 'document_category', array(), ... )`: flat, `public` true, `show_ui` true, `show_in_menu` true, `show_in_nav_menus` true, `show_admin_column` true, `show_in_rest` true, `show_tagcloud` false, `query_var` true, `rewrite` `document-category` (`with_front` false). Labels Document Category / Document Categories, text domain `custom-post-types-dp`. Field: title = term name (core).
- Hooked on `cptdp_register_post_types`.
- Temporary top-level menu `cptdp_document_category_menu()` on `admin_menu`, skipped when `post_type_exists( 'document' )`; menu position 26 (verified free: service 20, statistic 21, video 22, video category temp 23, software tool 24). `parent_file` filter keeps it highlighted, same guard.
- `AGENTS.md`: add `document_category` taxonomy line (flat, public, rewrite `/document-category/`, no object type yet, Document attaches it, temp menu note).

Gate:
- `ddev wp taxonomy get document_category --fields=name,hierarchical,public,show_ui,show_in_rest`
- Admin: Document Categories in sidebar. Create "Reports"; edit name; delete it; all work (AC 1-3).
- Temp attach (not committed): `ddev wp eval 'register_taxonomy_for_object_type("document_category","post"); $p=wp_insert_post(["post_title"=>"t","post_status"=>"publish"]); wp_set_object_terms($p,["Reports"],"document_category"); print_r(wp_get_object_terms($p,"document_category",["fields"=>"slugs"]));'` then delete post and term. (AC 4 fully verified in Phase 2.)
- `ddev wp rewrite list | grep document-category`; `/wp/v2/document_category` exists.
- Regression: `service`, `statistic`, `software_tool`, `video`, `video_category`, `aspect_ratio` registered.

## Phase 2: Document CPT shell (DPB-008, part 1)
Commits, in order:
1. `feat(custom-post-types-dp): register Document custom post type` — registration, labels, rewrite, supports, classic editor. Footer `Refs: DPB-008`.
2. `feat(custom-post-types-dp): add Document admin list columns` — thumbnail, order, sortable, default order, width CSS. Footer `Refs: DPB-008`.
3. `docs(agents): add document custom post type` — `AGENTS.md`.

Files: `document.php` (new), `AGENTS.md`.

- Template: `video.php` / `software-tool.php`. Labels Document/Documents, `not_found` "No documents found.", thumbnail labels "Thumbnail" / "Set thumbnail".
- `public` true, `has_archive` true, `rewrite` `documents` (`with_front` false), `show_ui`/`show_in_menu` true, `menu_position` 25 (verified free; see Phase 1 positions), `menu_icon` `dashicons-media-document`, `hierarchical` false, `supports` title/editor/thumbnail/page-attributes, `taxonomies` `document_category`, `show_in_rest` true, classic editor via `use_block_editor_for_post_type`. Hooked on `cptdp_register_post_types`.
- List columns: thumbnail, `menu_order` (sortable, default order `menu_order title` ASC), inline width CSS on `edit-document` only. Category column via `show_admin_column`. Filetype/pages/highlight columns land in Phase 3.
- `AGENTS.md`: add `document` CPT line; update `document_category` note (attached to `document`, temp menu gone).

Gate:
- `ddev wp post-type get document --fields=name,public,hierarchical,supports,taxonomies`
- Admin: Documents in sidebar, Document Categories listed under it, temp top-level item gone.
- Add New Document: title, content, thumbnail, category, Order → Publish → reload persists. `ddev wp post list --post_type=document --orderby=menu_order --fields=ID,post_title,menu_order`.
- `ddev wp rewrite list | grep -E "documents|document-category"`; `/documents/` and `/documents/<slug>/` resolve. `/wp/v2/document` exists.
- Regression: all other CPTs and taxonomies registered; `/videos/` still resolves.

## Phase 3: Document fields and meta boxes (DPB-008, part 2)
Commits, in order:
1. `feat(custom-post-types-dp): register Document post meta` — the six `register_post_meta` calls and sanitizers. Footer `Refs: DPB-008`.
2. `feat(custom-post-types-dp): add Document meta boxes` — Document and Highlight boxes, nonce, `save_post_document`, `admin-document.js` picker and enqueue. Footer `Refs: DPB-008`.
3. `feat(custom-post-types-dp): add file type, pages and highlight Document columns` — list columns. Footer `Refs: DPB-008`.
4. `docs(agents): add document fields` — `AGENTS.md` meta keys.
5. `chore(custom-post-types-dp): bump version to 0.11.0`.

Files: `document.php`, `admin-document.js` (new), `AGENTS.md`, `custom-post-types-dp.php` (→ 0.11.0).

- `register_post_meta` (all `single`, `show_in_rest`, auth `edit_posts`, own sanitize):
  - `cptdp_document_file`: int; absint; keep only if attachment exists and is not image/video/audio, else delete.
  - `cptdp_embed_url`: string; `esc_url_raw` http/https only via local `cptdp_document_sanitize_url`.
  - `cptdp_filetype`: string; `sanitize_text_field`; empty → delete.
  - `cptdp_author`: string; `sanitize_text_field`; empty → delete.
  - `cptdp_total_pages`: int; absint; empty → delete.
  - `cptdp_highlight`: bool; `1` or delete.
- Meta boxes via `add_meta_boxes_document`, one nonce `cptdp_document` / `cptdp_document_nonce`, one `save_post_document` handler (bail on autosave, bad nonce, no `edit_post`):
  1. Document (normal, high): file picker (button "Select document", hidden input, filename, Remove), Embed URL (`type=url`), File Type (`type=text`, placeholder "e.g. PDF"), Author (`type=text`), Total Pages (`type=number min=0 step=1`).
  2. Highlight (side): checkbox "Mark as highlight (Selected work)".
- Native: Document Category tag box, Thumbnail, Page Attributes (Order).
- `admin-document.js`: `wp.media()` open/select/remove. Enqueue only on `post.php`/`post-new.php` when `$typenow === 'document'`, `wp_enqueue_media()`, deps `jquery`, version `CPTDP_VERSION`, path via `CPTDP_URL . 'assets/admin-document.js'`.
- Columns added: file type, total pages, highlight (star / em dash).
- `AGENTS.md`: add meta keys `cptdp_document_file`, `cptdp_embed_url`, `cptdp_filetype`, `cptdp_author`, `cptdp_total_pages`, `cptdp_highlight`.

Gate:
- `ddev wp eval 'print_r( array_keys( get_registered_meta_keys( "post", "document" ) ) );'`: the six keys above.
- CLI round trip with `ddev wp post meta set/get` for each key.
- Security: `javascript:alert(1)` embed URL stored empty; forged image attachment ID in `cptdp_document_file` rejected; no nonce / no `edit_post` leaves meta unchanged; autosave does not touch meta.
- REST: `GET /wp/v2/document/<ID>` includes the meta.
- UI (manual, AC 2-14 except 10): content, thumbnail, pick PDF via picker, Remove, embed URL, "PDF", author, 12 pages, category, highlight, Order 3 → Publish → reload: file and embed URL persist (AC 12). Clear each, Update → cleared.
- Regression: Phase 2 checks pass; other CPTs registered.

## Phase 4: Post Statistics repeater (DPB-012)
Commits, in order:
1. `feat(custom-post-types-dp): register Document post statistics meta` — `register_post_meta` plus `cptdp_document_sanitize_statistics()`. Footer `Refs: DPB-012`.
2. `feat(custom-post-types-dp): add Document post statistics meta box` — repeater box, save handling, JS rows. Footer `Refs: DPB-012`.
3. `docs(agents): add document post statistics` — `AGENTS.md`.
4. `chore(custom-post-types-dp): bump version to 0.12.0`.

Files: `document.php`, `admin-document.js`, `AGENTS.md`, `custom-post-types-dp.php` (→ 0.12.0).

- `register_post_meta( 'document', 'cptdp_post_statistics', ... )`: `type` array, `single`, REST schema array of objects (`label`, `value` strings, `additionalProperties` false), auth `edit_posts`.
- `cptdp_document_sanitize_statistics()`: per row `sanitize_text_field` on both, drop rows with both empty, `array_values` reindex; empty result → delete meta. Used as `sanitize_callback` and by the save handler.
- Meta box "Post Statistics" (normal): server-rendered rows + `<template>` row + "Add statistic" button; inputs `cptdp_post_statistics[i][label]`, `[value]`; placeholders "e.g. Downloads" / "e.g. 1.2K"; Remove button. No pre-rendered blank row. Reuse Document nonce; read `$_POST['cptdp_post_statistics']` via `wp_unslash` only when array in existing `save_post_document`; `update_post_meta` or `delete_post_meta`. Escape with `esc_attr` / `esc_html__`.
- `admin-document.js`: add row from `<template>`, remove row, reindex `name` indices on add/remove/submit.
- `AGENTS.md`: add `cptdp_post_statistics` to the `document` meta keys.

Gate (AC 1-5 of DPB-012, AC 10 of DPB-008):
- `ddev wp eval 'print_r( get_registered_meta_keys( "post", "document" )["cptdp_post_statistics"] );'`: type `array`, single, show_in_rest.
- UI (manual): add Downloads/1.2K, Views/340, Shares/58 → Publish → reload, all three in order; edit one, remove one → Update; remove all → meta gone; blank row + one real row → only real row saved; `<script>alert(1)</script>` in label stored stripped, rendered escaped.
- `ddev wp post meta get <ID> cptdp_post_statistics --format=json`: list of `{label,value}`.
- REST: meta on `GET /wp/v2/document/<ID>`; extra keys rejected or stripped.
- Forged POST without nonce or `edit_post`: unchanged. Autosave does not touch.
- Regression: all Phase 3 fields unaffected; Video statistics still work; other CPTs registered.

---

## After Phase 4
- `git log --oneline` shows the commits in the order listed per phase (1 + 3 + 3 + 5 + 4 = 16), every subject matching `^(feat|fix|docs|chore)\((custom-post-types-dp|plans|agents)\): `. Check with `git log --format=%s main..HEAD | grep -vE '^(feat|fix|docs|chore)\((custom-post-types-dp|plans|agents)\): '` → empty.
- `git status`: only the pre-existing `.gitignore` change (and any held-back `AGENTS.md` hunks) unstaged.
- Open PR from `feat/DPB-007-008-012-Document` to `main` only when asked. Description ends with the Claude Code attribution line.

## Out of scope
Frontend rendering (templates, patterns, viewer / embed, highlight query), seed content, custom capabilities, oEmbed validation, file type / page count auto-detect from the attachment, drag-reorder of statistic rows, single-select Document Category, shared code with Video, REST customisation beyond meta.

## Open questions
- **DPB-012 precondition** says "A video record exists". Assumed typo for Document.
- **Menu positions**: 25 (Documents) and 26 (temp Document Categories menu). 24 is already Software Tool.
- **Document file restriction**: current plan rejects image/video/audio only. Alternative: allowlist (pdf, doc/docx, xls/xlsx, ppt/pptx, txt). Confirm if stricter wanted.
- **Document Category cardinality**: multi (tag box) chosen to match Video. Switch to single select if one category per document is intended.
