# Plan: DPB-004 "Video Category" taxonomy

## Context
Depends on DPB-000 (master plugin `custom-post-types-dp`, done). Loader globs `includes/post-types/*.php` (skips `index.php`); modules hook registration to `cptdp_register_post_types` (fired on `init` and plugin activation, then rewrite flush). Patterns proven by DPB-001 (`service.php`) and DPB-003 (`statistic.php`).

Ticket says "custom Video Category post type" but type is **Taxonomy** (confirmed by user), and AC says "available for assignment to Video entries". So: register a flat (non-hierarchical) taxonomy `video_category`, not a CPT.

Spec:
- Fields: title (string) = term `name`.
- Singular "Video Category", plural "Video Categories".
- Admin: sidebar item, create (Title), edit, delete, available for assignment to Video entries.

## Key issue: Video CPT does not exist yet
No `video` post type in repo. The Video CPT ticket will be implemented in a **separate PR** (confirmed by user), after this one. Consequences:
- Taxonomy registered now with no object type yet; Video module attaches it later via `taxonomies => array( 'video_category' )` or `register_taxonomy_for_object_type`.
- WP core only auto-adds the taxonomy menu under a post type's menu. With no post type, no sidebar item appears. Need a top-level admin menu entry pointing at `edit-tags.php?taxonomy=video_category`.
- AC 4 (assignment) cannot be fully verified until Video CPT exists. Verify via CLI/temporary attach (see Verification).

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/post-types/video-category.php
```
Same dir as CPTs so loader glob picks it up. No loader change.

## Files to edit
- `AGENTS.md`: Overview add taxonomy line: `video_category` (`includes/post-types/video-category.php`, flat taxonomy; not yet attached to any post type, Video CPT attaches it in a separate PR).
- `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: bump minor version (header + `CPTDP_VERSION`). Check current value at implementation time.

No `.gitignore` change (plugin dir allowlisted).

## Design

### `video-category.php`
Mirror `statistic.php` structure and comment density. `defined( 'ABSPATH' ) || exit;`, `@package CustomPostTypesDP` docblock.

Code comments must state the Video CPT dependency (plain comments, not TODO tags):
- File/function docblock on `register_taxonomy`: "Registered with no object type. The Video post type (separate PR) attaches it via its `taxonomies` argument."
- Docblock on the menu callback: "Temporary top-level menu. Skipped once the Video post type exists; core then lists the taxonomy under Video."

`cptdp_register_video_category(): void`, hooked on `cptdp_register_post_types`.
- `labels`: `name` Video Categories, `singular_name` Video Category, `menu_name` Video Categories, `all_items` All Video Categories, `edit_item` Edit Video Category, `view_item`, `update_item`, `add_new_item` Add New Video Category, `new_item_name`, `search_items`, `not_found` ("No video categories found."), `back_to_items`, `popular_items`, `items_list`, `items_list_navigation`. `esc_html_x` / `esc_html__`, text domain `custom-post-types-dp`.
- `register_taxonomy( 'video_category', array(), ... )`:
  - `hierarchical` => false (flat, confirmed). No Parent field in term form. Video editor uses tag-style entry (classic: tag box; block editor: token field) instead of checkboxes. Terms are still created/managed in Video Categories screen.
  - `public` => true, `show_ui` => true, `show_in_menu` => true, `show_admin_column` => true, `show_in_rest` => true (module convention, needed for block editor term picker).
  - `show_in_nav_menus` => true, `show_tagcloud` => false.
  - `rewrite` => `array( 'slug' => 'video-category', 'with_front' => false )`.
  - `query_var` => true.
  - No `capabilities` override (defaults: `manage_categories`, admins ok).
  - Term has name + slug + (description, parent). Only Title required; others are core defaults, left in place.

Sidebar entry (needed while no object type):
- `cptdp_video_category_menu(): void` on `admin_menu`: `add_menu_page( __( 'Video Categories' ), __( 'Video Categories' ), 'manage_categories', 'edit-tags.php?taxonomy=video_category', '', 'dashicons-category', 23 )`. Position 23, after Statistics (21), spacing for the future Video CPT at 22.
- Once Video CPT lands (separate PR), core shows the taxonomy under the Video menu automatically → this top-level item would duplicate. Guard now: only add if `post_type_exists( 'video' )` is false, so the Video PR needs no cleanup. Menu is registered on `admin_menu` priority 20+ so `post_type_exists` is reliable (CPTs registered on `init`).
- Menu highlight fix: filter `parent_file` so top-level item stays highlighted on the term edit screen (`edit-tags.php` / `term.php` with taxonomy `video_category`). Also `submenu_file` not needed (no submenu).

If core blocks `edit-tags.php?taxonomy=video_category` with empty `object_type` ("Invalid post type" / no screen), fallback: register taxonomy with a placeholder object type is wrong. Better fallback: build Video CPT stub in this ticket. Decide after the verify step below.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|-----------|---------------|
| 0 | Plugin active | precondition |
| 1 | Video Categories in sidebar | top-level `add_menu_page` (until Video CPT exists, then core submenu) |
| 2 | Create with Title | term `name` field (`show_ui`) |
| 3 | Edit existing | `edit_item` UI, `manage_categories` |
| 4 | Delete | row action Delete (`delete_term`) |
| 5 | Assignable to Video entries | taxonomy attached to `video` object type; verified via temp attach now, real attach in Video ticket |

## Verification
1. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp` → success, no PHP notices (`ddev logs`).
2. `ddev php -l` on `video-category.php`.
3. `ddev wp taxonomy get video_category --fields=name,hierarchical,public,show_ui,labels` → matches design.
4. Admin (`ddev launch` → wp-admin): "Video Categories" in sidebar, below Statistics. Click → Add form loads, no "Invalid post type" error (key check for the issue above).
   - Add term with Name only → appears in list.
   - Reload → persists.
   - Edit name → Update → reload → persists.
   - Delete → gone.
5. CLI sanity: `ddev wp term create video_category "Test" --porcelain`, `ddev wp term list video_category --fields=term_id,name,slug`, `ddev wp term delete video_category <id>`.
6. Assignment check (temp, not committed): `ddev wp eval 'register_taxonomy_for_object_type("video_category","post"); $p=wp_insert_post(["post_title"=>"t","post_status"=>"publish"]); var_dump(wp_set_object_terms($p,["Test"],"video_category"));'` then delete post and term. Confirms taxonomy can be assigned; full UI check in the Video ticket.
7. Frontend: `/video-category/<slug>/` rewrite present: `ddev wp rewrite list | grep video-category`. REST: `ddev wp eval 'echo isset(rest_get_server()->get_routes()["/wp/v2/video_category"]) ? "ok" : "missing";'`.
8. Regression: `ddev wp post-type get service` and `statistic` still work.
9. `git status` shows only `video-category.php`, plugin main file, `AGENTS.md`, plan file (plus pre-existing `.gitignore` change, leave untouched).
10. Commit: `feat(custom-post-types-dp): add Video Category taxonomy` (+ Co-Authored-By trailer).

## Out of scope
Video CPT itself (separate PR/ticket; will set `taxonomies => array( 'video_category' )`), term meta (colour/icon/order), default seed terms, frontend archive template, custom capabilities, REST customisation.

## Follow-up (separate PR)
Video CPT PR: register `video` with `taxonomies => array( 'video_category' )`. Then re-run AC 5 in the UI (assign category in Video editor) and confirm the top-level "Video Categories" menu from this PR disappears in favour of the core submenu under Video.

## Decisions (resolved)
- Taxonomy, not CPT.
- Flat, non-hierarchical.
- Video CPT implemented in a separate PR.
