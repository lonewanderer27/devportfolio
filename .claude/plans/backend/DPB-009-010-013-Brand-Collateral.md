# Plan: DPB-009 + DPB-010 + DPB-013 (Collateral Category, Collateral, Post Statistics), phased

Branch: `feat/DPB-009-010-013-Brand-Collateral`. One PR, four phases (plus a plan-only Phase 0). Each phase is split into small atomic Conventional Commits, one concern per commit (see Commit convention). Each phase leaves the plugin working and passes its own gate before the commit. Mirrors the Document work (`DPB-007-008-012-Document.md`); `document.php`, `document-category.php`, `admin-document.js` are the templates. Software tool selection mirrors `video.php` (`cptdp_software_tools`).

Tickets:
- DPB-009: `collateral_category` taxonomy
- DPB-010: `collateral` CPT (depends DPB-000, DPB-005, DPB-009, DPB-013)
- DPB-013: `cptdp_post_statistics` repeater on Collateral (depends DPB-010)

## Resolved decisions
| Topic | Decision |
|---|---|
| Build order | Taxonomy (009) → CPT shell + fields (010) → statistics (013). DPB-010 lists 013 as a dependency, but the repeater is a meta box on the CPT, so it lands after the CPT exists. Ticket AC 9 ("create post statistics") is satisfied once Phase 4 lands. PR is not complete before then. |
| Naming | Post type slug `collateral`, taxonomy slug `collateral_category`. Labels singular "Collateral", plural "Collaterals" (per spec). Ticket prose says "Brand Collateral"; admin menu and labels use "Collaterals" and "Collateral Categories" per the spec's Singular/Plural Name. Flag if "Brand Collaterals" is wanted in the sidebar. |
| DPB-013 precondition / AC 5 typo | AC 5 says "persist after saving the Document". Read as "Collateral". Do not build against Document. Flag to ticket owner. |
| DPB-010 precondition | "At least one Brand Collateral & Software Tool exists". Software Tool CPT already shipped (DPB-005). Test data created per gate. |
| Collateral Category | Flat taxonomy, `public` true, same shape as `document_category`. Keep default tag box (multi). Spec says "foreign id" but AC says "assign a Collateral Category", multi is fine. |
| Taxonomy menu | Temporary top-level "Collateral Categories" menu only while `collateral` post type does not exist (same trick as `document-category.php`). Once Phase 2 lands it lists under Collaterals. |
| Collateral file | Meta `cptdp_collateral_file` (attachment ID). Spec field is `document_file`; key prefixed by CPT to avoid ambiguity with Document. Same rule as Document: attachment must exist and must not be `video/` or `audio/` (documents and images OK), else row deleted. Picker `wp.media()` with no type filter. |
| Embed URL | Meta `cptdp_embed_url`, http/https only, local `cptdp_collateral_sanitize_url` (no cross-module dependency). |
| Total pages | Meta `cptdp_total_pages`, `absint`, empty → delete. |
| Paper size | Meta `cptdp_paper_size`, free text string (spec: "enter a Paper Size value"), `sanitize_text_field`, e.g. `A4`. Placeholder "e.g. A4". Empty → delete. |
| Software tools | Meta `cptdp_software_tools`, array of `software_tool` post IDs. Checkbox list in meta box (same as Video). Own sanitizer `cptdp_collateral_sanitize_software_tools()`: absint, keep only IDs of existing `software_tool` posts, unique, reindex. Empty → delete. |
| Highlight | Meta `cptdp_highlight`, store `1`, delete when unchecked. Label "Mark as highlight (Selected work)". Ticket AC 13 says "document"; read as "collateral". |
| Post statistics | Rows `{ label, value }` strings, same shape as Document. Own sanitizer `cptdp_collateral_sanitize_statistics()`, own JS. No shared helper. |
| Editor | Classic, same as other CPTs. |
| File / embed | Both optional, no "at least one" validation. |
| Author | Not in spec. Do not add. |

## Commit convention
Spec: Conventional Commits 1.0.0. Matches the repo history (`feat(custom-post-types-dp): ...`, `docs(plans): ...`, `docs(agents): ...`), with two fixes to earlier practice: a blank line always separates subject from footer, and no subject exceeds 72 chars.

Message layout (every commit):
```
<type>(<scope>): <description>
<blank line>
[optional body]
<blank line>
<footer>: <value>
<footer>: <value>
```
- Subject: `type(scope): description`. Type and scope lowercase, scope a noun in parentheses, one space after the colon. Description imperative, lowercase first letter (proper nouns such as Collateral keep capitals), no trailing period, whole line ≤72 chars.
- Body: only when the why is not obvious. Starts one blank line after the subject, wrapped at 72.
- Footers: start one blank line after the body, or after the subject when there is no body. Git-trailer format `Token: value`. Order: `Refs: DPB-NNN` first (code, docs and chore commits that belong to a ticket), then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Plan-only commit uses `Refs` for all three tickets.
- No ticket key in the subject.
- `BREAKING CHANGE`: none. Nothing here changes existing behaviour, so no `!` and no breaking footer.
- One concern per commit. Each commit leaves the plugin activatable (`php -l` clean, no notices).

| Concern | Type(scope) |
|---|---|
| New plugin PHP / JS behaviour | `feat(custom-post-types-dp)` |
| Plan files | `docs(plans)` |
| `AGENTS.md` updates | `docs(agents)` |
| Version bump (header + `CPTDP_VERSION`) | `chore(custom-post-types-dp)` |
| Fix found during a phase gate, in already-committed code | `fix(custom-post-types-dp)` |

Example (no body, ticket footer):
```
feat(custom-post-types-dp): add Collateral Category taxonomy

Refs: DPB-009
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

Validation after the last commit:
- Subject format: `git log --format=%s main..HEAD | grep -vE '^(feat|fix|docs|chore)\((custom-post-types-dp|plans|agents)\): [a-z].{0,}[^.]$'` → empty.
- Length: `git log --format=%s main..HEAD | awk 'length>72'` → empty.
- Blank line after subject: `git log --format='%b' main..HEAD` has no commit whose first body line is non-empty; check with `for c in $(git rev-list main..HEAD); do git show -s --format=%B $c | sed -n 2p | grep -q . && echo "bad $c"; done` → empty.
- Footers: every commit body contains `Co-Authored-By:`; every non-plan commit contains `Refs: DPB-0(09|10|13)`.

PR title (same format, ≤72): `feat(custom-post-types-dp): add Collateral post type and taxonomy`. PR body follows `.github/pull_request_template.md` (tick "New feature"; Description, Implementation, How to Test from the phase gates). Body ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

## Versions
Plugin is currently at `0.12.0` (header `Version:` and `CPTDP_VERSION`). Bump once per ticket, in that ticket's last phase, as its own `chore` commit, header and constant together.

| Ticket | Last phase | Bump |
|---|---|---|
| DPB-009 Collateral Category | Phase 1 | 0.12.0 → 0.13.0 |
| DPB-010 Collateral CPT | Phase 3 | 0.13.0 → 0.14.0 |
| DPB-013 Post Statistics | Phase 4 | 0.14.0 → 0.15.0 |

Exactly three bumps in the PR, each a standalone `chore(custom-post-types-dp): bump version to 0.x.0` commit touching only `custom-post-types-dp.php`. After each bump:
- `grep -nE "Version:|CPTDP_VERSION'" wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php` shows the same version in both places.
- `git show --stat HEAD` lists only that file.

Final check: `git log --format=%s main..HEAD | grep -c "bump version"` → `3`.

## Files
Create:
```
wp-content/plugins/custom-post-types-dp/includes/post-types/collateral-category.php   (Phase 1)
wp-content/plugins/custom-post-types-dp/includes/post-types/collateral.php            (Phases 2-4)
wp-content/plugins/custom-post-types-dp/assets/admin-collateral.js                    (Phases 3-4)
```
Edit: `AGENTS.md` (each phase), `custom-post-types-dp.php` (version only).

No loader change (glob picks up `includes/post-types/*.php`). The pre-existing local `.gitignore` diff (plugin allowlist path fix) stays unstaged and untouched.

## Rules for every phase
- Stage explicit paths only. Never `git add -A`; `.gitignore` stays out.
- `AGENTS.md`: run `git diff AGENTS.md` first, keep unrelated hunks out of feature commits (`git add -p`), or flag them.
- Gate before commit: `ddev exec php -l` on each changed PHP file; `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp`; no notices in `ddev logs`; the phase's checks pass; test data deleted.
- Commits are GPG-signed from global git config. Do not pass `-S`, `--no-gpg-sign`, or `-c user.*` / `-c commit.gpgsign`. Before the first commit run `git config --show-origin --get-regexp 'user\.|commit\.gpgsign'`; after each commit run `git log -1 --show-signature` and confirm a good signature. If signing fails, stop and report, never fall back to unsigned.
- If a gate fails, fix inside the phase. No commit on red.

---

## Phase 0: plan
Commits:
1. `docs(plans): add DPB-009, DPB-010, DPB-013 plan for Collateral`

Stage only `.claude/plans/DPB-009-010-013-Brand-Collateral.md`.

Gate: `git status` shows only the plan file staged.

## Phase 1: Collateral Category taxonomy (DPB-009)
Commits, in order:
1. `feat(custom-post-types-dp): add Collateral Category taxonomy` — `collateral-category.php`. Footer `Refs: DPB-009`.
2. `docs(agents): add collateral category taxonomy` — `AGENTS.md`. Footer `Refs: DPB-009`.
3. `chore(custom-post-types-dp): bump version to 0.13.0` — `custom-post-types-dp.php`. Footer `Refs: DPB-009`.

Files: `collateral-category.php` (new), `AGENTS.md`, `custom-post-types-dp.php` (→ 0.13.0).

- Mirror `document-category.php`. `register_taxonomy( 'collateral_category', array(), ... )`: flat, `public` true, `show_ui` true, `show_in_menu` true, `show_in_nav_menus` true, `show_admin_column` true, `show_in_rest` true, `show_tagcloud` false, `query_var` true, `rewrite` `collateral-category` (`with_front` false). Labels Collateral Category / Collateral Categories, text domain `custom-post-types-dp`. Field: title = term name (core).
- Hooked on `cptdp_register_post_types`.
- Temporary top-level menu `cptdp_collateral_category_menu()` on `admin_menu`, skipped when `post_type_exists( 'collateral' )`; menu position 28. Verified free: service 20, statistic 21, video 22, video category temp 23, software tool 24, document 25, document category temp 26, collateral 27 (Phase 2). `parent_file` filter keeps it highlighted, same guard.
- `AGENTS.md`: add `collateral_category` line (flat, public, rewrite `/collateral-category/`, no object type yet, Collateral attaches it, temp menu note).

Gate:
- `ddev wp taxonomy get collateral_category --fields=name,hierarchical,public,show_ui,show_in_rest`
- Admin: Collateral Categories in sidebar. Create "Brochures"; edit name; delete it (AC 1-3).
- Temp attach (not committed): `ddev wp eval 'register_taxonomy_for_object_type("collateral_category","post"); $p=wp_insert_post(["post_title"=>"t","post_status"=>"publish"]); wp_set_object_terms($p,["Brochures"],"collateral_category"); print_r(wp_get_object_terms($p,"collateral_category",["fields"=>"slugs"]));'` then delete post and term. (AC 4 fully verified in Phase 2.)
- `ddev wp rewrite list | grep collateral-category`; `/wp/v2/collateral_category` exists.
- Regression: `service`, `statistic`, `software_tool`, `video`, `document`, `video_category`, `aspect_ratio`, `document_category` registered.

## Phase 2: Collateral CPT shell (DPB-010, part 1)
Commits, in order:
1. `feat(custom-post-types-dp): register Collateral custom post type` — registration, labels, rewrite, supports, classic editor. Footer `Refs: DPB-010`.
2. `feat(custom-post-types-dp): add Collateral admin list columns` — thumbnail, order, sortable, default order, width CSS. Footer `Refs: DPB-010`.
3. `docs(agents): add collateral custom post type` — `AGENTS.md`. Footer `Refs: DPB-010`.

Files: `collateral.php` (new), `AGENTS.md`.

- Template: `document.php`. Labels Collateral/Collaterals, `not_found` "No collaterals found.", thumbnail labels "Thumbnail" / "Set thumbnail".
- `public` true, `has_archive` true, `rewrite` `collaterals` (`with_front` false), `show_ui`/`show_in_menu` true, `menu_position` 27, `menu_icon` `dashicons-portfolio`, `hierarchical` false, `supports` title/editor/thumbnail/page-attributes/custom-fields (custom-fields only exposes meta in REST; meta box removed in Phase 3), `taxonomies` `collateral_category`, `show_in_rest` true, classic editor via `use_block_editor_for_post_type`. Hooked on `cptdp_register_post_types` (priority 15).
- List columns: thumbnail, `menu_order` (sortable, default order `menu_order title` ASC), inline width CSS on `edit-collateral` only. Category column via `show_admin_column`. Paper size / pages / highlight columns land in Phase 3.
- `AGENTS.md`: add `collateral` CPT line; update `collateral_category` note (attached to `collateral`, temp menu gone).

Gate:
- `ddev wp post-type get collateral --fields=name,public,hierarchical,supports,taxonomies`
- Admin: Collaterals in sidebar, Collateral Categories listed under it, temp top-level item gone.
- Add New Collateral: title, content, thumbnail, category, Order → Publish → reload persists (AC 1-3, 8, 12). `ddev wp post list --post_type=collateral --orderby=menu_order --fields=ID,post_title,menu_order`.
- `ddev wp rewrite list | grep -E "collaterals|collateral-category"`; `/collaterals/` and `/collaterals/<slug>/` resolve. `/wp/v2/collateral` exists.
- Regression: all other CPTs and taxonomies registered; `/documents/` and `/videos/` still resolve.

## Phase 3: Collateral fields and meta boxes (DPB-010, part 2)
Commits, in order:
1. `feat(custom-post-types-dp): register Collateral post meta` — `register_post_meta` calls and sanitizers. Footer `Refs: DPB-010`.
2. `feat(custom-post-types-dp): add Collateral meta boxes` — Collateral, Software Tools and Highlight boxes, nonce, `save_post_collateral`, `admin-collateral.js` picker and enqueue. Footer `Refs: DPB-010`.
3. `feat(custom-post-types-dp): add Collateral detail columns` — paper size, pages, highlight list columns. Footer `Refs: DPB-010`.
4. `docs(agents): add collateral fields` — `AGENTS.md` meta keys. Footer `Refs: DPB-010`.
5. `chore(custom-post-types-dp): bump version to 0.14.0`. Footer `Refs: DPB-010`.

Files: `collateral.php`, `admin-collateral.js` (new), `AGENTS.md`, `custom-post-types-dp.php` (→ 0.14.0).

- `register_post_meta` (all `single`, `show_in_rest`, auth `edit_posts`, own sanitize):
  - `cptdp_collateral_file`: int; absint; keep only if attachment exists and MIME does not start with `video/` or `audio/`, else delete.
  - `cptdp_embed_url`: string; http/https only via `cptdp_collateral_sanitize_url`.
  - `cptdp_paper_size`: string; `sanitize_text_field`; empty → delete.
  - `cptdp_total_pages`: int; absint; empty → delete.
  - `cptdp_software_tools`: array of integers (REST schema `items` integer); `cptdp_collateral_sanitize_software_tools()`.
  - `cptdp_highlight`: bool; `1` or delete.
- Meta boxes via `add_meta_boxes_collateral`; `remove_meta_box( 'postcustom', 'collateral', 'normal' )`. One nonce `cptdp_collateral` / `cptdp_collateral_nonce`, one `save_post_collateral` handler (bail on autosave, bad nonce, no `edit_post`):
  1. Collateral (normal, high): file picker (button "Select file", hidden input, filename, Remove), Embed URL (`type=url`), Paper Size (`type=text`, placeholder "e.g. A4"), Total Pages (`type=number min=0 step=1`).
  2. Software Tools (side or normal, default): checkbox list of published `software_tool` posts, `cptdp_software_tools[]`; same markup and empty-state message as Video.
  3. Highlight (side): checkbox "Mark as highlight (Selected work)".
- Native: Collateral Category tag box, Thumbnail, Page Attributes (Order).
- `admin-collateral.js`: `wp.media()` open/select/remove. Enqueue only on `post.php`/`post-new.php` when screen post type is `collateral`, `wp_enqueue_media()`, deps `jquery`, version `CPTDP_VERSION`, localized object `cptdpCollateral`.
- Columns added: paper size, total pages, highlight (star / em dash).
- `AGENTS.md`: add meta keys `cptdp_collateral_file`, `cptdp_embed_url`, `cptdp_paper_size`, `cptdp_total_pages`, `cptdp_software_tools`, `cptdp_highlight`.

Gate:
- `ddev wp eval 'print_r( array_keys( get_registered_meta_keys( "post", "collateral" ) ) );'`: the six keys above.
- CLI round trip with `ddev wp post meta set/get` for each key.
- Security: `javascript:alert(1)` embed URL stored empty; forged video/audio attachment ID and non-attachment post ID in `cptdp_collateral_file` rejected; PDF and image IDs accepted; forged tool IDs (non-`software_tool` posts, strings, negatives, duplicates) dropped; no nonce / no `edit_post` leaves meta unchanged; autosave does not touch meta.
- REST: `GET /wp/v2/collateral/<ID>` includes the meta.
- UI (manual, AC 2-8, 10-13 except statistics): content, thumbnail, pick PDF via picker, Remove, embed URL, paper size "A4", 12 pages, two software tools, category, highlight, Order 3 → Publish → reload: file, embed URL, tools persist (AC 11). Clear each, Update → cleared.
- Regression: Phase 2 checks pass; other CPTs registered.

## Phase 4: Post Statistics repeater (DPB-013)
Commits, in order:
1. `feat(custom-post-types-dp): register Collateral post statistics meta` — `register_post_meta` plus `cptdp_collateral_sanitize_statistics()`. Footer `Refs: DPB-013`.
2. `feat(custom-post-types-dp): add Collateral post statistics meta box` — repeater box, save handling, JS rows. Footer `Refs: DPB-013`.
3. `docs(agents): add collateral post statistics` — `AGENTS.md`. Footer `Refs: DPB-013`.
4. `chore(custom-post-types-dp): bump version to 0.15.0`. Footer `Refs: DPB-013`.

Files: `collateral.php`, `admin-collateral.js`, `AGENTS.md`, `custom-post-types-dp.php` (→ 0.15.0).

- `register_post_meta( 'collateral', 'cptdp_post_statistics', ... )`: `type` array, `single`, REST schema array of objects (`label`, `value` strings, `additionalProperties` false), auth `edit_posts`.
- `cptdp_collateral_sanitize_statistics()`: per row `sanitize_text_field` on both, drop non-array rows and rows with both empty, `array_values` reindex; empty result → delete meta. Used as `sanitize_callback` and by the save handler.
- Meta box "Post Statistics" (normal): server-rendered rows + `<template>` row + "Add statistic" button; inputs `cptdp_post_statistics[i][label]`, `[value]`; placeholders "e.g. Downloads" / "e.g. 1.2K"; Remove button. No pre-rendered blank row. Reuse Collateral nonce; read `$_POST['cptdp_post_statistics']` via `wp_unslash` in existing `save_post_collateral`; `update_post_meta` or `delete_post_meta`. Escape with `esc_attr` / `esc_html__`.
- `admin-collateral.js`: add row from `<template>`, remove row, reindex `name` indices on add/remove/submit.
- `AGENTS.md`: add `cptdp_post_statistics` to the `collateral` meta keys.

Gate (AC 1-5 of DPB-013, AC 9 of DPB-010):
- `ddev wp eval 'print_r( get_registered_meta_keys( "post", "collateral" )["cptdp_post_statistics"] );'`: type `array`, single, show_in_rest.
- UI (manual): add Downloads/1.2K, Views/340, Shares/58 → Publish → reload, all three in order; edit one, remove one → Update; remove all → meta gone; blank row + one real row → only real row saved; `<script>alert(1)</script>` in label stored stripped, rendered escaped.
- `ddev wp post meta get <ID> cptdp_post_statistics --format=json`: list of `{label,value}`.
- REST: meta on `GET /wp/v2/collateral/<ID>`; extra keys rejected or stripped.
- Forged POST without nonce or `edit_post`: unchanged. Autosave does not touch.
- Regression: all Phase 3 fields unaffected; Document and Video statistics still work; other CPTs registered.

---

## After Phase 4
- `git log --oneline` shows the commits in the order listed per phase (1 + 3 + 3 + 5 + 4 = 16). Run the validation checks from Commit convention; all return empty.
- `git status`: only the pre-existing `.gitignore` change (and any held-back `AGENTS.md` hunks) unstaged.
- `AGENTS.md` Overview lists `collateral`, `collateral_category` and all meta keys; plugin version there matches `0.15.0`.
- Open PR from `feat/DPB-009-010-013-Brand-Collateral` to `main` only when asked.

## Out of scope
Frontend rendering (templates, patterns, viewer / embed, highlight query), seed content, custom capabilities, oEmbed validation, page count / paper size auto-detect from the attachment, paper size dropdown / presets, drag-reorder of statistic rows, single-select Collateral Category, shared code with Document or Video, REST customisation beyond meta.

## Open questions
- **DPB-013 AC 5 and DPB-010 AC 13** say "Document". Assumed typos for Collateral.
- **Sidebar label**: spec plural is "Collaterals"; ticket prose says "Brand Collaterals". Using "Collaterals". Confirm.
- **Menu positions**: 27 (Collaterals) and 28 (temp Collateral Categories menu). 26 is the Document Categories temp slot.
- **Collateral file restriction**: documents and images accepted, video and audio rejected (denylist), same as Document. Alternative: strict allowlist.
- **Collateral Category cardinality**: multi (tag box) chosen to match Document and Video. Switch to single select if one category per collateral is intended.
- **Paper size**: free text. Alternative: fixed select (A3, A4, A5, Letter, DL, Custom).
- **Meta key for file**: `cptdp_collateral_file` (spec says `document_file`). Confirm no frontend consumer expects `cptdp_document_file`.
