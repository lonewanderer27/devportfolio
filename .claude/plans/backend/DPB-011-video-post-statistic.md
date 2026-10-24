# Plan: DPB-011 Video "Post Statistic" meta

## Source ticket
**DPB-011 · Video · Post Statistic** (Medium, User Story). Depends on DPB-006 (Video CPT).

Developer story: register an array post meta for Video. Each item has:
- `label` (string)
- `value` (string)

Admin story: Post Statistics section when creating/editing a Video:
1. Add a statistic row (Label + Value).
2. Add multiple rows.
3. Edit existing rows.
4. Remove rows.
5. Saved statistics persist after saving the Video.

Precondition: custom post types plugin activated.

## Context
- Resolves the DPB-006 plan open question "what is DPB-011": rows are `{ label, value }` strings (e.g. "Views" / "12K"). NOT references to the `statistic` CPT.
- DPB-006 plan already sketches the repeater (`cptdp_post_statistics`, "Post Statistics" meta box, `admin-video.js`). `video.php` does not exist yet. Two ways to land:
  - **A (recommended)**: DPB-006 ships the CPT with all other fields; DPB-011 adds the statistics meta, meta box, JS repeater on top (own branch/PR). Strip the repeater from DPB-006 implementation, or merge DPB-006 first and layer this.
  - **B**: implement both in one PR; DPB-011 plan becomes the acceptance checklist only.
- Do not start until `includes/post-types/video.php` exists and `video` is registered.

## Storage
| Item | Value |
|---|---|
| Meta key | `cptdp_post_statistics` |
| Type | array, `single` true, stored as serialized array of rows |
| Row | `array( 'label' => string, 'value' => string )` |
| REST | `show_in_rest` with schema: array of objects, `label`/`value` strings, `additionalProperties` false |
| Auth | `current_user_can( 'edit_posts' )` |
| Sanitize | per row `sanitize_text_field` on both; drop rows where both empty; `array_values` reindex; empty result → delete meta |

## Files
Edit (no new PHP file; keep in module style):
- `wp-content/plugins/custom-post-types-dp/includes/post-types/video.php`
  - `register_post_meta( 'video', 'cptdp_post_statistics', ... )` inside the `cptdp_register_post_types` callback.
  - `cptdp_video_sanitize_statistics( $value )` helper (used as `sanitize_callback` and by the save handler).
  - Meta box "Post Statistics" (`add_meta_boxes_video`, normal context): server-rendered rows + `<template>` row + "Add statistic" button. Inputs `cptdp_post_statistics[i][label]`, `[value]`. Reuse the Video nonce (`cptdp_video` / `cptdp_video_nonce`).
  - Save in existing `save_post_video` handler (autosave/nonce/cap checks already there). Read `$_POST['cptdp_post_statistics']` via `wp_unslash`, only when it is an array; sanitize; `update_post_meta` or `delete_post_meta`.
- `wp-content/plugins/custom-post-types-dp/assets/admin-video.js` (created in DPB-006; add repeater section): add row from `<template>`, remove row, reindex `name` indices on add/remove/submit. Plain jQuery, no build step.
- `AGENTS.md`: add `cptdp_post_statistics` to the `video` meta keys list.
- `custom-post-types-dp.php`: bump version 0.8.0 → 0.9.0 (header + `CPTDP_VERSION`).

No loader change. No `.gitignore` change.

## UI behaviour
- Row = two text inputs (Label placeholder "e.g. Views", Value placeholder "e.g. 12K") + Remove button.
- Empty state: no rows + "Add statistic" button. (Optionally pre-render one blank row on new Video; decide at implementation, default no.)
- Remove deletes the row from the DOM; persisted on Update. Removing all rows clears meta.
- Rows with both fields blank are discarded on save, so stray blank rows don't persist. Row with only one field filled is kept.
- Escape on output: `esc_attr` for values, `esc_html__` for strings, text domain `custom-post-types-dp`.
- Scripts already enqueued only on Video edit screens (DPB-006); no extra enqueue.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|---|---|
| Dev | Array meta with label/value strings | `register_post_meta` array + REST schema |
| 1 | Add row with Label and Value | "Add statistic" clones `<template>` |
| 2 | Multiple rows | indexed repeater `[i][label|value]` |
| 3 | Edit existing rows | rows rendered from saved meta, inputs editable |
| 4 | Remove rows | Remove button + save replaces whole array |
| 5 | Persist after save | `update_post_meta` in `save_post_video` |

## Verification
1. `ddev exec php -l wp-content/plugins/custom-post-types-dp/includes/post-types/video.php`.
2. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp`; no PHP notices (`ddev logs`).
3. `ddev wp eval 'print_r( get_registered_meta_keys( "post", "video" )["cptdp_post_statistics"] );'` → type `array`, single, show_in_rest.
4. Admin → Videos → Add New: Post Statistics box visible.
   - Add 3 rows (Views/12K, Likes/340, Shares/58) → Publish → reload: all 3 persist in order.
   - Edit one value, remove one row → Update → reload: reflects change.
   - Remove all rows → Update → meta gone (`ddev wp post meta get <ID> cptdp_post_statistics` errors/empty).
   - Add blank row + one real row → only real row saved.
   - `<script>alert(1)</script>` in label → stored stripped, rendered escaped.
5. Forge POST without nonce / as user lacking `edit_post` → meta unchanged. Autosave does not touch meta.
6. CLI: `ddev wp post meta get <ID> cptdp_post_statistics --format=json` → list of `{label,value}`.
7. REST: `GET /wp-json/wp/v2/video/<ID>` includes `meta.cptdp_post_statistics`; POST with invalid shape (extra keys) rejected/stripped.
8. Regression: other Video meta (file, URLs, tools, highlight) unaffected; `service`, `statistic`, `software_tool` still registered.
9. `git status`: only `video.php`, `admin-video.js`, plugin main file, `AGENTS.md`, this plan (leave pre-existing `.gitignore` change alone).
10. Commit: `feat(custom-post-types-dp): add Video post statistics meta` + Co-Authored-By trailer.

## Out of scope
Frontend display of statistics, drag-to-reorder rows, numeric/unit validation of `value`, linking rows to the `statistic` CPT, max row limit, seed data.

## Open questions
- Land order: option A (layer after DPB-006) vs B (same PR). Recommend A.
- Row order: insertion order only (assumed). Drag-reorder is out of scope.
- Limit on rows or max length per field: none assumed (WP default text sanitation only).
- Pre-render one blank row on new Video? Assumed no.
