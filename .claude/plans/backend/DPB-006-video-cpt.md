# Plan: DPB-006 "Video" custom post type

## Context
Depends on DPB-000 (master plugin, done), DPB-004 (`video_category` taxonomy, done), DPB-005 (`software_tool` CPT, done), DPB-011 (not in repo; assumed Statistic-related, see Open questions). Loader globs `includes/post-types/*.php`; each module hooks to `cptdp_register_post_types`. Pattern proven by `service.php`, `statistic.php`, `software-tool.php`. `software-tool.php` is the closest template (thumbnail, meta box, nonce save, list columns, classic editor).

Video is the biggest CPT so far: media picker, multi-select relation, repeater meta, taxonomy attach.

Spec fields:
| Spec field | Storage |
|---|---|
| title, editor | core |
| thumbnail (attachment id) | `supports: thumbnail` → `_thumbnail_id` |
| video_file (attachment id) | meta `cptdp_video_file` (int) |
| embed_url | meta `cptdp_embed_url` (string, url) |
| post_url | meta `cptdp_post_url` (string, url) |
| length (seconds) | meta `cptdp_length` (int ≥ 0) |
| software_tools (array of ids) | meta `cptdp_software_tools` (array of int, `software_tool` post IDs) |
| video_category (foreign id) | taxonomy `video_category` (term relation, not meta) |
| aspect_ratio | taxonomy `aspect_ratio` (term relation, not meta). **Superseded by DPB-018.** |
| post_statistics (repeater) | meta `cptdp_post_statistics` (array of rows) |
| highlight (boolean) | meta `cptdp_highlight` (bool) |

Supports `page-attributes` (Order → `menu_order`). Singular "Video", plural "Videos".

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/post-types/video.php
wp-content/plugins/custom-post-types-dp/assets/admin-video.js   (media picker + repeater rows)
```
(If more than ~300 lines of PHP, split meta box render/save into `includes/post-types/video-meta.php`; loader glob would load it too, so keep it a plain include from `video.php` instead, or leave as one file to match `software-tool.php`.)

## Files to edit
- `AGENTS.md`: Overview CPT list → add `video` (`includes/post-types/video.php`, public, archive `/videos/`, title/editor/thumbnail/order, taxonomy `video_category`, meta keys). Update `video_category` note: now attached to `video`; temp top-level menu gone.
- `custom-post-types-dp.php`: bump version 0.7.0 → 0.8.0 (header + `CPTDP_VERSION`).
- `includes/post-types/video-category.php`: no change expected. Its `cptdp_video_category_menu()` already skips when `post_type_exists( 'video' )`, so the temp menu disappears and the taxonomy lists under Videos. Verify only.

No loader change. No `.gitignore` change (plugin dir allowlisted, assets inside it).

## Design

### Post type `video` (≤20 chars)
- Labels: same set as `software-tool.php` with Video/Videos; thumbnail labels "Thumbnail"/"Set thumbnail"/etc.; `not_found` "No videos found." Text domain `custom-post-types-dp`.
- `public` true, `has_archive` true, `rewrite` `array( 'slug' => 'videos', 'with_front' => false )`.
- `show_ui`/`show_in_menu` true, `menu_position` 22 (reserved in DPB-004/005), `menu_icon` `dashicons-video-alt3`.
- `hierarchical` false. `supports` `array( 'title', 'editor', 'thumbnail', 'page-attributes' )`.
- `taxonomies` `array( 'video_category', 'aspect_ratio' )` (attaches the taxonomy; `show_admin_column` already true there).
- `show_in_rest` true. Classic editor forced via `use_block_editor_for_post_type`, same as other CPTs (meta boxes stay simple).
- Hook on `cptdp_register_post_types`.

### Meta registration (`register_post_meta`, same function, after `register_post_type`)
All `single` true, `show_in_rest` true, `auth_callback` → `current_user_can( 'edit_posts' )`, own `sanitize_callback`:
- `cptdp_video_file`: integer; sanitize `absint`; save only if attachment exists and `wp_attachment_is( 'video', $id )`, else delete.
- `cptdp_embed_url`, `cptdp_post_url`: string; `esc_url_raw( trim(), array( 'http', 'https' ) )`. Reuse/generalise `cptdp_software_tool_sanitize_url` → either call it directly (cross-module dependency) or add `cptdp_video_sanitize_url`. Prefer a tiny duplicate to keep modules independent (matches existing style).
- `cptdp_length`: integer seconds; `absint`; empty input → delete row.
- `cptdp_highlight`: boolean; checkbox; store `1`, delete when unchecked.
- `cptdp_software_tools`: `type` `array`, `show_in_rest` schema `array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) )`. Sanitize: `array_values( array_unique( array_filter( array_map( 'absint', $v ) ) ) )`, keep only IDs where `get_post_type() === 'software_tool'`.
- `cptdp_post_statistics`: `type` `array`, REST schema array of objects (see below).

### Meta boxes (all `add_meta_boxes_video`, one nonce `cptdp_video` / `cptdp_video_nonce`, one `save_post_video` handler: bail on autosave, bad nonce, `! current_user_can( 'edit_post' )`)
1. **Video** (normal, high): Video File picker (button "Select video" → `wp.media({ library: { type: 'video' } })`, hidden input `cptdp_video_file`, shows filename + Remove link), Embed URL (`type=url`), Original Post URL (`type=url`), Length (`type=number min=0 step=1`, "seconds"). Aspect Ratio is the `aspect_ratio` taxonomy box (DPB-018).
2. **Software Tools** (side or normal): checkbox list of all published `software_tool` posts ordered by `menu_order title`; `name="cptdp_software_tools[]"`. Empty state: link to Add New Software Tool.
3. **Post Statistics** (normal): repeater. Rows rendered server-side, JS adds/removes rows via `<template>`; inputs `cptdp_post_statistics[i][label]`, `[value]`. Sanitize: `sanitize_text_field`, drop rows with both empty, reindex.
4. **Highlight** (side): checkbox "Mark as highlight (Selected work)".
- Video Category: native taxonomy box (flat taxonomy → tag-style box). See Open questions on single vs multiple.
- Thumbnail + Page Attributes: native.

### Admin JS (`assets/admin-video.js`)
- Enqueue on `admin_enqueue_scripts` only for `post.php`/`post-new.php` when `$typenow === 'video'`; `wp_enqueue_media()`; deps `jquery`; version = `CPTDP_VERSION`. Path via `plugins_url( 'assets/admin-video.js', CPTDP_FILE )` (check actual constant name in main file).
- Media picker open/select/remove; repeater add/remove row; reindex names on submit.

### Admin list columns (`manage_video_posts_columns`)
After Title: `thumbnail`, `cptdp_length` (formatted `m:ss`/`h:mm:ss`), `cptdp_highlight` (star/em dash), `menu_order`. Taxonomy column already added by `show_admin_column`. `menu_order` sortable; default admin order `menu_order title` ASC. Inline width CSS via `admin_head-edit.php` scoped to `edit-video`. Same shape as `software-tool.php`.

### Output helpers (for later frontend work, keep tiny)
- `cptdp_get_video_meta( int $post_id ): array` returning typed values with defaults. Optional, only if cheap. Skip if it pads scope.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|---|---|
| 0 | ≥1 Video Category & Software Tool exist | precondition (DPB-004, DPB-005 done) |
| 1 | Videos in sidebar | `show_ui` + `show_in_menu`, pos 22 |
| 2 | Title | `supports: title` |
| 3 | Description in Editor | `supports: editor` |
| 4 | Upload/assign Video File | Video meta box + wp.media picker, `cptdp_video_file` |
| 5 | Embed URL | `cptdp_embed_url` |
| 6 | Length in seconds | `cptdp_length` |
| 7 | Select ≥1 Software Tools | checkbox list, `cptdp_software_tools` |
| 8 | Assign Video Category | `taxonomies => video_category` |
| 9 | Create ≥1 post statistics | repeater, `cptdp_post_statistics` |
| 10 | Save and publish | default publish + `save_post_video` |
| 11 | Order via Page Attributes | `page-attributes` → `menu_order` |
| 12 | Original published URL | `cptdp_post_url` |
| 13 | Mark as highlight | `cptdp_highlight` |
| + | Thumbnail, aspect ratio (spec fields) | `thumbnail` support; `aspect_ratio` taxonomy (DPB-018) |

## Verification
1. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp`; no PHP notices (`ddev logs`).
2. `ddev php -l` on `video.php`.
3. `ddev wp post-type get video --fields=name,public,hierarchical,supports,taxonomies` → supports title/editor/thumbnail/page-attributes; taxonomies includes `video_category`.
4. `ddev wp eval 'print_r( array_keys( get_registered_meta_keys( "post", "video" ) ) );'` → the 6 `cptdp_*` keys (no `cptdp_aspect_ratio`).
5. Admin: Videos in sidebar (pos 22), Video Categories now under Videos, old top-level Video Categories item gone.
   - Add New: title, description, thumbnail, upload video via picker, embed URL, post URL, length 125, aspect ratio, 2 software tools, category, 2 statistic rows, highlight, Order 3 → Publish. Reload: everything persists.
   - Remove video file / clear URL / uncheck highlight / delete a stat row → Update → rows cleared.
   - `javascript:alert(1)` in URLs → stored empty. Non-video attachment ID forged in POST → rejected.
   - List: columns show values; Order sortable.
6. CLI: create post, `ddev wp post meta get <ID> cptdp_software_tools` etc.; `ddev wp post list --post_type=video --orderby=menu_order --fields=ID,post_title,menu_order`. Delete test data after.
7. Frontend: `/videos/`, `/videos/<slug>/`, `/video-category/<term>/` resolve. REST `/wp/v2/video` route exists and returns meta.
8. Regression: `service`, `statistic`, `software_tool` still registered; `ddev wp rewrite list | grep -E "services|statistics|software-tools|videos"`.
9. `git status`: only `video.php`, `admin-video.js`, plugin main file, `AGENTS.md`, plan file (leave pre-existing `.gitignore` change alone).
10. Commit: `feat(custom-post-types-dp): add Video custom post type` + Co-Authored-By trailer.

## Out of scope
Frontend rendering (templates, patterns, video player, highlight query), seed content, custom capabilities, oEmbed validation/reachability checks, duration auto-detect from file, REST customisation beyond meta.

## Open questions
- **DPB-011**: what is it? If it defines the `post_statistics` row shape, follow it. Default assumption: rows of `{ label, value }` strings (e.g. "Views" / "12K"). Alternative: reference `statistic` CPT IDs plus per-video value.
- **Video Category cardinality**: spec says "a foreign id" (single). Flat taxonomy gives multi-select tag box. Options: (a) leave multi (simplest); (b) replace box with a single `<select>` via `meta_box_cb` on the taxonomy and enforce one term on save. Recommend (b) if "one category per video" is intended.
- **Aspect ratio options**: resolved by DPB-018 (taxonomy; `16x9`, `9x16`, `1x1`, `4x3`).
- **Software Tools storage**: array in one meta row (chosen; matches spec "array of ids"). Alternative: one row per ID (`single` false), easier `meta_query` filtering later.
- **Editor**: classic (consistent with other CPTs) assumed.
- **Video file vs embed**: both optional, neither required. Add "at least one" validation? Assumed no.
