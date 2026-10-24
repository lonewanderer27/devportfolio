# Plan: DPB-003 "Statistic" custom post type

## Context
Depends on DPB-000 (master plugin `custom-post-types-dp`, done). Loader globs `includes/post-types/*.php` (skips `index.php`); each module hooks its `register_post_type` call to `cptdp_register_post_types` (fired on `init` and on plugin activation, then rewrite flush). Pattern already proven by DPB-001 (`service.php`). Add second module: `Statistic` CPT.

Spec:
- Fields: title (string), editor (string)
- Singular "Statistic", plural "Statistics"
- Added (user request): Order field (`menu_order`) to control display order.
- Admin only needs: sidebar item, create (Title), edit, delete, persists after refresh.

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/post-types/statistic.php
```

## Files to edit
- `AGENTS.md`: Overview "Registered CPTs: `service` (...)" → add `statistic` (`includes/post-types/statistic.php`).
- `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: bump minor version (header + `CPTDP_VERSION`). Check current value at implementation time.

No loader change (glob picks file up). No activation change (hook `cptdp_register_post_types` already covers new modules). No `.gitignore` change (plugin dir allowlisted).

## Design

### `statistic.php`
Mirror `service.php` structure/comment density.
- `defined( 'ABSPATH' ) || exit;` guard, `@package CustomPostTypesDP` docblock.
- `cptdp_register_statistic(): void`, hooked on `cptdp_register_post_types`.
- `labels`: `name` Statistics, `singular_name` Statistic, `menu_name` Statistics, `name_admin_bar` Statistic, `add_new`, `add_new_item` Add New Statistic, `new_item`, `edit_item` Edit Statistic, `view_item`, `view_items`, `all_items` All Statistics, `search_items`, `not_found` ("No statistics found."), `not_found_in_trash`, `archives`, `filter_items_list`, `items_list_navigation`, `items_list`. `esc_html_x` / `esc_html__`, text domain `custom-post-types-dp`. Omit `parent_item_colon` (non-hierarchical).
- `register_post_type( 'statistic', ... )`:
  - `public` => true, `has_archive` => true, `rewrite` => `array( 'slug' => 'statistics', 'with_front' => false )`: public like Services (single + archive pages). Activation flush covers rules.
  - `show_ui` => true, `show_in_menu` => true (sidebar item), `menu_position` => 21 (right after Services at 20), `menu_icon` => `dashicons-chart-bar`.
  - `hierarchical` => false (no Parent dropdown; Page Attributes panel shows Order only).
  - `supports` => `array( 'title', 'editor', 'page-attributes' )`. Editor listed, content not required. `page-attributes` gives Order (`menu_order`) field, stored in `wp_posts.menu_order`. Same mechanism as Services.
  - Admin list: add sortable "Order" column? Skip (minimal). Default list sorts by date; use `orderby=menu_order` in queries / `ddev wp post list --orderby=menu_order`.
  - `show_in_rest` => true (module convention; keeps REST for later block/pattern use).
  - `capability_type` => `post` (default).
- Classic editor filter `use_block_editor_for_post_type` → `false` for `statistic`, same as Service. Rationale: editor field is a plain string (e.g. value "120+"), classic editor is the lighter fit. Drop if block editor preferred.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|-----------|---------------|
| 0 | Plugin active | precondition |
| 1 | Statistic in sidebar | `show_ui` + `show_in_menu` |
| 2 | Create with Title | `supports: title` |
| 3 | Edit existing | default edit caps / `edit_item` UI |
| 4 | Delete | default delete caps (Trash → Delete Permanently) |
| 5 | Persist after refresh | saved as `wp_posts` row |
| 6 | Order field (added) | `page-attributes` → `menu_order` |

## Verification
1. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp` → success, no PHP notices (`ddev logs`).
2. `ddev php -l` on `statistic.php`.
3. `ddev wp post-type get statistic --fields=name,public,hierarchical,supports,labels` → supports title/editor/page-attributes; labels Statistic/Statistics.
4. Admin (`ddev launch` → wp-admin): "Statistics" in sidebar below Services.
   - Add New → Title only (no content) → publish OK.
   - Reload → item still listed.
   - Edit title + content → Update → reload → persists.
   - Page Attributes panel shows Order (no Parent); set Order = 5, save, reload → persists.
   - Trash → Trash view → Delete Permanently → gone.
5. CLI sanity: `ddev wp post create --post_type=statistic --post_title="Test" --post_content="120+" --post_status=publish --menu_order=3`, then `ddev wp post list --post_type=statistic --orderby=menu_order --order=ASC --fields=ID,post_title,post_status,menu_order`; delete test posts after.
6. Frontend: `/statistics/` and `/statistics/<slug>/` resolve (no 404). REST: `ddev wp eval 'echo rest_get_server()->get_routes()["/wp/v2/statistic"] ? "ok" : "missing";'` (rest base defaults to post type key).
7. Regression: `ddev wp post-type get service` still works; `ddev wp rewrite list | grep services` still present.
8. `git status` shows only `statistic.php`, plugin main file, `AGENTS.md`, plan file (plus pre-existing `.gitignore` change, leave untouched).
9. Commit: `feat(custom-post-types-dp): add Statistic custom post type` (+ Co-Authored-By trailer).

## Out of scope
Numeric/unit/label meta fields, admin Order column, taxonomies, frontend rendering (blocks/patterns/home stats section), seed content, REST customisation, custom capabilities.

## Open questions
- Classic editor OK for the editor field, or block editor?
- Want an admin list "Order" column (sortable)? Excluded for now.
