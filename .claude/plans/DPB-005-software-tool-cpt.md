# Plan: DPB-005 "Software Tool" custom post type

## Context
Depends on DPB-000 (master plugin `custom-post-types-dp`, done). Loader globs `includes/post-types/*.php` (skips `index.php`); each module hooks its `register_post_type` call to `cptdp_register_post_types` (fired on `init` and on plugin activation, then rewrite flush). Pattern proven by DPB-001 (`service.php`) and DPB-003 (`statistic.php`). Add third module: `Software Tool` CPT. First CPT with a custom meta field (`site_url`) and a featured image.

Spec:
- Fields: title (string), editor (string), thumbnail (media attachment id), site_url (string)
- Supports: `page-attributes`
- Singular "Software Tool", plural "Software Tools"

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/post-types/software-tool.php
```

## Files to edit
- `AGENTS.md`: Overview "Registered CPTs: ..." → add `software_tool` (`includes/post-types/software-tool.php`, public, archive `/software-tools/`, title/editor/thumbnail/order, meta `cptdp_site_url`).
- `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: bump minor version (header + `CPTDP_VERSION`). Currently `0.4.0`; recheck at implementation time (DPB-004 may have bumped it).

No loader change (glob picks file up). No activation change. No `.gitignore` change (plugin dir allowlisted).

## Design

### Post type key
`software_tool` (≤20 chars, lowercase, underscore). Slug `software-tools`.

### `software-tool.php`
Mirror `statistic.php` structure/comment density.
- `defined( 'ABSPATH' ) || exit;` guard, `@package CustomPostTypesDP` docblock.
- `cptdp_register_software_tool(): void`, hooked on `cptdp_register_post_types`.
- `labels`: `name` Software Tools, `singular_name` Software Tool, `menu_name` Software Tools, `name_admin_bar` Software Tool, `add_new`, `add_new_item` Add New Software Tool, `new_item`, `edit_item` Edit Software Tool, `view_item`, `view_items`, `all_items` All Software Tools, `search_items`, `not_found` ("No software tools found."), `not_found_in_trash`, `archives`, `filter_items_list`, `items_list_navigation`, `items_list`, plus thumbnail labels: `featured_image` "Thumbnail", `set_featured_image` "Set thumbnail", `remove_featured_image` "Remove thumbnail", `use_featured_image` "Use as thumbnail". `esc_html_x` / `esc_html__`, text domain `custom-post-types-dp`. Omit `parent_item_colon` (non-hierarchical).
- `register_post_type( 'software_tool', ... )`:
  - `public` => true, `has_archive` => true, `rewrite` => `array( 'slug' => 'software-tools', 'with_front' => false )`.
  - `show_ui` / `show_in_menu` => true, `menu_position` => 24 (20 Services, 21 Statistics, 22 reserved Video, 23 Video Categories per DPB-004), `menu_icon` => `dashicons-admin-tools`.
  - `hierarchical` => false (Page Attributes shows Order only, no Parent).
  - `supports` => `array( 'title', 'editor', 'thumbnail', 'page-attributes' )`.
    - `thumbnail` → "Thumbnail" meta box; stores attachment ID in `_thumbnail_id`; uploads via Media Library modal. Also needs theme `post-thumbnails`; block themes (TT25) enable it already, verify.
    - `page-attributes` → Order (`menu_order`).
  - `show_in_rest` => true.
  - `capability_type` => `post` (default).
- Classic editor filter `use_block_editor_for_post_type` → `false` for `software_tool`, same as Service/Statistic. Confirmed by user. Keeps the `cptdp_site_url` meta box simple.

### `cptdp_site_url` field (post meta)
Meta key is `cptdp_site_url` (plugin-prefixed, collision-safe). Spec field name `site_url` maps to this key.
- `register_post_meta( 'software_tool', 'cptdp_site_url', ... )` on `init` (inside `cptdp_register_post_types` callback or separate `init` hook, since registered post type must exist first; prefer same function, after `register_post_type`):
  - `type` => `string`, `single` => true, `show_in_rest` => true, `default` => `''`
  - `sanitize_callback` => `esc_url_raw` (schemes `http`, `https`), `auth_callback` => `current_user_can( 'edit_posts' )`.
- Meta box `cptdp_software_tool_site_url`, title "Site URL", context `normal`, priority `high`:
  - `add_meta_boxes_software_tool` hook → `add_meta_box(...)`.
  - Render: `wp_nonce_field( 'cptdp_site_url', 'cptdp_site_url_nonce' )`, `<input type="url" name="cptdp_site_url" class="widefat" placeholder="https://example.com" value="<?php echo esc_attr( ... ); ?>">` with a `<label>`.
  - Save on `save_post_software_tool`: bail on `DOING_AUTOSAVE`, bail on missing/invalid nonce, bail on `! current_user_can( 'edit_post', $post_id )`; read `wp_unslash( $_POST['cptdp_site_url'] )`, `esc_url_raw( ..., array( 'http', 'https' ) )`; empty → `delete_post_meta`, else `update_post_meta`.
- Reading on frontend: callers escape with `esc_url()`. Document in docblock (matches contact settings convention).

### Admin list columns
Columns on All Software Tools list, added via filters in `software-tool.php`:
- `manage_software_tool_posts_columns`: insert after Title (before Date): `thumbnail` ("Thumbnail"), `cptdp_site_url` ("Site URL"), `menu_order` ("Order"). Keep checkbox + Title first, Date last.
- `manage_software_tool_posts_custom_column`:
  - `thumbnail` → `get_the_post_thumbnail( $post_id, array( 50, 50 ) )`, else em dash.
  - `cptdp_site_url` → `<a href="esc_url()" target="_blank" rel="noopener noreferrer">esc_html( host/url )</a>`, else em dash.
  - `menu_order` → `(int) get_post_field( 'menu_order', $post_id )`.
- `manage_edit-software_tool_sortable_columns`: make `menu_order` sortable (`orderby=menu_order` is native to `WP_Query`, no `pre_get_posts` needed).
- Default list order: `pre_get_posts` (admin, main query, `software_tool`, no `orderby` set) → `menu_order ASC`, so list matches display order. Skip if it feels intrusive.
- Column width: small inline admin CSS via `admin_head-edit.php` scoped to `software_tool` screen (`.column-thumbnail { width: 60px; }`, `.column-menu_order { width: 70px; }`). Prefer this over a CSS file.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|-----------|---------------|
| 0 | Plugin active | precondition |
| 1 | Software Tools in sidebar | `show_ui` + `show_in_menu` |
| 2 | Title | `supports: title` |
| 3 | Description in Editor | `supports: editor` |
| 4 | Upload/assign Thumbnail | `supports: thumbnail` (Media Library, `_thumbnail_id`) |
| 5 | Enter Site URL | meta box + `cptdp_site_url` post meta |
| 6 | Save and publish | default publish flow + meta save handler |
| 7 | Order via Page Attributes | `page-attributes` → `menu_order` |
| 8 | List columns (added) | column filters for Thumbnail, Site URL, Order |

## Verification
1. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp` → success, no PHP notices (`ddev logs`).
2. `ddev php -l wp-content/plugins/custom-post-types-dp/includes/post-types/software-tool.php`.
3. `ddev wp post-type get software_tool --fields=name,public,hierarchical,supports,labels` → supports title/editor/thumbnail/page-attributes; labels Software Tool/Software Tools.
4. Admin (`ddev launch` → wp-admin): "Software Tools" in sidebar below Statistics (gap at 22/23 for Video).
   - Add New → Title, description, Set thumbnail (upload image), Site URL `https://example.com`, Order = 3 → Publish.
   - Reload → all values persist (thumbnail shown, URL filled, Order = 3).
   - Clear Site URL → Update → meta row deleted. Invalid input (`javascript:alert(1)`) → stored empty.
   - Page Attributes panel shows Order, no Parent.
   - List view: Thumbnail, Site URL, Order columns show values (em dash when empty); Order header sortable asc/desc; Site URL link opens new tab.
5. CLI sanity:
   - `ddev wp post create --post_type=software_tool --post_title="Test" --post_content="desc" --post_status=publish --menu_order=2`
   - `ddev wp post meta update <ID> cptdp_site_url https://example.com`
   - `ddev wp post list --post_type=software_tool --orderby=menu_order --order=ASC --fields=ID,post_title,post_status,menu_order`
   - `ddev wp post meta get <ID> _thumbnail_id` after assigning in admin.
   - Delete test posts after.
6. Frontend: `/software-tools/` and `/software-tools/<slug>/` resolve (no 404). REST: `ddev wp eval 'echo isset(rest_get_server()->get_routes()["/wp/v2/software_tool"]) ? "ok" : "missing";'`; `ddev wp eval` check `get_registered_meta_keys('post','software_tool')` has `cptdp_site_url`.
7. Regression: `ddev wp post-type get service` and `statistic` still work; `ddev wp rewrite list | grep -E "services|statistics"` still present.
8. `git status` shows only `software-tool.php`, plugin main file, `AGENTS.md`, plan file (plus pre-existing `.gitignore` change and DPB-004 plan, leave untouched).
9. Commit: `feat(custom-post-types-dp): add Software Tool custom post type` (+ Co-Authored-By trailer).

## Out of scope
Frontend rendering (blocks/patterns/tools section), taxonomies, seed content, REST customisation, custom capabilities, URL reachability validation, `rel`/`target` handling for links.

## Open questions
- ~~Classic vs block editor~~ Decided: classic editor (confirmed by user).
- ~~Meta key~~ Decided: prefixed `cptdp_site_url` (confirmed by user).
- ~~Admin list columns~~ Decided: include Thumbnail, Site URL, Order (confirmed by user).
- Restrict URL to `https` only (like contact settings) or allow `http` too? Plan allows both.
