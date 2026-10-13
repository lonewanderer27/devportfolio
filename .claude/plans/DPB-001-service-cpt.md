# Plan: DPB-001 "Service" custom post type

## Context
Depends on DPB-000 (master plugin `custom-post-types-dp`, done). Loader globs `includes/post-types/*.php` (skips `index.php`) on `plugins_loaded`; each module self-registers on `init`. Add first module: `Service` CPT.

Spec:
- Fields: title (string), editor (optional)
- Supports: hierarchical, page-attributes
- Singular "Service", plural "Services"

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/post-types/service.php
```

## Files to edit
- `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: add activation hook that flushes rewrite rules (see Design).
- `AGENTS.md`: Overview line "no CPTs registered yet" → mention `service`.

No `.gitignore` change needed (plugin dir already allowlisted).

## Design

### `service.php`
- `defined( 'ABSPATH' ) || exit;` guard.
- Function `cptdp_register_service()`, hooked on `init`.
- `register_post_type( 'service', $args )`:
  - `labels`: `name` Services, `singular_name` Service, `menu_name` Services, `add_new_item` Add New Service, `edit_item` Edit Service, `new_item`, `view_item`, `view_items`, `search_items`, `not_found`, `not_found_in_trash`, `all_items` All Services, `parent_item_colon` Parent Service:, `archives`. Use `esc_html_x` / `__()` with text domain `custom-post-types-dp`.
  - `public` => true, `show_ui` => true, `show_in_menu` => true (sidebar item "Services"), `menu_position` => 20, `menu_icon` => `dashicons-hammer` (or `dashicons-portfolio`).
  - `hierarchical` => true (parent/child; adds Parent dropdown in Page Attributes).
  - `supports` => `array( 'title', 'editor', 'page-attributes' )`. Editor listed but content not required → optional by default. `page-attributes` gives Order (`menu_order`) field.
  - `show_in_rest` => true (block editor + REST, per module convention).
  - `has_archive` => true, `rewrite` => `array( 'slug' => 'services', 'with_front' => false )`.
  - `capability_type` => `post` (default).
- Classic editor: filter `use_block_editor_for_post_type` returns `false` for `service` (keeps REST on; `show_in_rest => false` would drop the endpoint).
- Export nothing global beyond the prefixed function.

### Rewrite flush
Module convention: flush on activation only. Modules load on `plugins_loaded`, which has not run for this plugin during its own activation request, so `service` is not registered yet when the activation hook fires. In `custom-post-types-dp.php`:
```php
register_activation_hook( __FILE__, 'cptdp_activate' );
function cptdp_activate() {
	CPTDP_Loader::load_modules();   // require module files
	cptdp_register_service();       // register so rules exist
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
```
Check `class-loader.php`: if module loading is only in a hooked callback, expose a public static `load_modules()` and have the `plugins_loaded` callback call it. Simpler alternative for the activation path: loop `CPTDP_PATH . 'includes/post-types/*.php'` and call `do_action( 'init' )`-free direct register calls. Prefer exposing `load_modules()`. Future CPTs: register each via a shared `cptdp_activate` hook (e.g. action `cptdp_register_post_types`) so activation does not need per-CPT edits. Decide at implementation; keep it minimal.

### Conventions
WPCS (tabs, Yoda, escaping), `cptdp_` prefix, text domain `custom-post-types-dp`.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|-----------|---------------|
| 0 | Plugin active | precondition |
| 1 | Services in sidebar | `show_ui` + `show_in_menu` |
| 2 | Create with Title | `supports: title` |
| 3 | Optional content | `supports: editor`, no required validation |
| 4 | Order value | `page-attributes` → `menu_order` |
| 5 | Parent/child | `hierarchical => true` |
| 6 | Save + publish | default caps / REST |
| 7 | Order via Page Attributes | `page-attributes` panel |

## Verification
1. `ddev wp plugin activate custom-post-types-dp` (or deactivate/activate) → success, no PHP notices (`ddev logs`, debug.log).
2. `ddev wp post-type get service --fields=name,hierarchical,supports,labels` → hierarchical true; supports title/editor/page-attributes; labels Service/Services.
3. `ddev wp rewrite list | grep services` → rules present after activation.
4. Admin (`ddev launch` → wp-admin): "Services" in sidebar; Add New:
   - save Title only (no content) → publishes OK.
   - add content → saves.
   - Page Attributes panel shows Parent + Order; set Order = 5, save, reload, value persists.
   - create parent Service, then child with Parent set; list table shows child indented.
5. CLI sanity: `ddev wp post create --post_type=service --post_title=Test --post_status=publish --menu_order=3` then `ddev wp post list --post_type=service --fields=ID,post_title,post_parent,menu_order`; delete test posts after.
6. Frontend: `/services/<slug>/` and `/services/` resolve (no 404).
7. `git status` shows only `service.php`, plugin main file, `AGENTS.md`, plan file.
8. Commit: `feat(custom-post-types-dp): add Service custom post type` (+ Co-Authored-By trailer).

## Out of scope
Taxonomies, custom meta fields, block templates / patterns for Services, REST customisation, capabilities.

## Seed content (local DB, not tracked in git)
Added via WP-CLI on 2026-10-13. These are DB rows: re-create on other environments (or export/import DB). Parents carry the tagline + bullet `<ul>` as classic-editor HTML; children have no content. Order = `menu_order`, numbered from 1 per level.

| Parent (Order) | ID | Children (Order, ID) |
|---|---|---|
| Social Media Marketing (1) | 14 | Analytics (1, 17), Scheduled posting (2, 18), Content calendar (3, 19), Content creation (4, 20), Short-form videos (5, 21), AI-generated videos (6, 22) |
| Digital Marketing (2) | 15 | Ad analytics (1, 23), Brand collaterals (2, 24), Digital creation (3, 25), Product mockups (4, 26) |
| Web Creation / Management (3) | 16 | Websites & portfolios (1, 27), GoDaddy (2, 28), WordPress (3, 29) |

Taglines (parent content):
- Social Media Marketing: "Strategic content that builds your audience."
- Digital Marketing: "Marketing support that turns attention into customers."
- Web Creation / Management: "Websites built, updated and kept running."

Command pattern:
```bash
ddev wp post create --post_type=service --post_status=publish \
  --post_title="<title>" --post_parent=<parent id|0> --menu_order=<n> [--post_content="<html>"]
```

Notes:
- Parent bullets duplicate the children. Strip from parent content if the front end renders children.
- Earlier test rows (IDs 6, 7, 11–13) were deleted.
