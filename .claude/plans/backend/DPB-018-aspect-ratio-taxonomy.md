# Plan: DPB-018 "Aspect Ratio" taxonomy

## Context
Depends on DPB-006 (Video CPT; plan exists, not yet implemented in repo). Master plugin `custom-post-types-dp` loader globs `includes/post-types/*.php`; modules hook registration to `cptdp_register_post_types` (fired on `init` and plugin activation, then rewrite flush). Closest template: `video-category.php` (DPB-004), also a taxonomy registered before its object type exists.

Spec:
- Type: Taxonomy (ticket says "taxonomy for Video object").
- Fields: title (string) = term `name`.
- Singular "Aspect Ratio", plural "Aspect Ratios".
- Admin: Video create/edit screen shows an Aspect Ratio field offering exactly `16x9`, `9x16`, `1x1`, `4x3`; value saves on the video.

## Conflict with DPB-006 plan (needs resolving)
DPB-006 plan stores aspect ratio as post meta `cptdp_aspect_ratio` with allowlist `16:9`, `9:16`, `4:3`, `1:1`, `21:9`. DPB-018 supersedes: ratio is now a **taxonomy** with `16x9`, `9x16`, `1x1`, `4x3` (no `21x9`, `x` not `:`). Consequence: when DPB-006 is implemented, drop `cptdp_aspect_ratio` meta, its select, sanitizer, REST registration, and the "aspect ratio" row in its acceptance table. Update DPB-006 plan accordingly (small edit, in this PR or the Video PR).

## Key issue: Video CPT does not exist yet
Same as DPB-004. Taxonomy registered with no object type; Video module attaches via `taxonomies => array( 'video_category', 'aspect_ratio' )`. AC 1-3 (UI on Video screen) fully verifiable only after DPB-006. Verify now via temp attach (see Verification).

Unlike DPB-004, **no temporary top-level menu**. Terms are a fixed seeded set; nothing to manage until Video exists. Once attached, core lists "Aspect Ratios" under Videos automatically.

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/post-types/aspect-ratio.php
```
Loader glob picks it up. No loader change.

## Files to edit
- `AGENTS.md`: Overview, add taxonomy line: `aspect_ratio` (`includes/post-types/aspect-ratio.php`, flat, public false, seeded terms `16x9`/`9x16`/`1x1`/`4x3`, single-select box; no object type yet, Video CPT attaches it).
- `custom-post-types-dp.php`: bump version 0.6.0 → 0.7.0 (header + `CPTDP_VERSION`).
- `.claude/plans/DPB-006-video-cpt.md`: remove `cptdp_aspect_ratio` meta, add `aspect_ratio` to taxonomies list and acceptance row (see conflict above).

No `.gitignore` change.

## Design

### `aspect-ratio.php`
Mirror `video-category.php` structure and comment density. `defined( 'ABSPATH' ) || exit;`, `@package CustomPostTypesDP`.

`cptdp_register_aspect_ratio(): void`, hooked on `cptdp_register_post_types`.
- Labels: `name` Aspect Ratios, `singular_name` Aspect Ratio, `menu_name`, `all_items`, `edit_item`, `view_item`, `update_item`, `add_new_item`, `new_item_name`, `search_items`, `not_found` ("No aspect ratios found."), `back_to_items`, `items_list`, `items_list_navigation`. `esc_html_x` / `esc_html__`, text domain `custom-post-types-dp`.
- `register_taxonomy( 'aspect_ratio', array(), ... )`:
  - `hierarchical` false (flat; no Parent field).
  - `public` false, `publicly_queryable` false, `rewrite` false, `query_var` false (a ratio archive has no value; avoids rewrite clutter). `show_ui` true, `show_in_menu` true, `show_in_nav_menus` false, `show_tagcloud` false, `show_admin_column` true, `show_in_rest` true.
  - `meta_box_cb` => `cptdp_aspect_ratio_meta_box` (single-select, see below).
  - `default_term` => `array( 'name' => '16x9', 'slug' => '16x9' )` so every video gets a ratio even if untouched. Confirm desired (see Open questions).
  - No `capabilities` override.

### Seeding the four terms
Spec requires the four ratios to exist. Seed idempotently:
- `cptdp_seed_aspect_ratios(): void` on `cptdp_register_post_types` after registration (priority 20): for each of `16x9`, `9x16`, `1x1`, `4x3`, `term_exists( $slug, 'aspect_ratio' )` else `wp_insert_term( $name, 'aspect_ratio', array( 'slug' => $slug ) )`.
- Guard with option `cptdp_aspect_ratio_seeded` (version string) so it does not hit the DB every request, and so an admin who deletes a term is not overridden on every `init`. Run on activation path too (same action). Reseed only if option missing.
- Term order: `wp_insert_term` creation order gives ID order; admin list sorted by name by default. Dropdown render sorts explicitly in spec order (below).

### Single-select meta box
Ratios are mutually exclusive, so replace the default tag box.
- `cptdp_aspect_ratio_meta_box( WP_Post $post, array $box ): void`: loads all terms (`hide_empty` false), orders by fixed array `16x9, 9x16, 1x1, 4x3` then any extras by name. Renders `<select name="tax_input[aspect_ratio]">` (non-hierarchical taxonomies accept a comma-separated string via `tax_input`, core handles `wp_set_post_terms`). Preselect current term; first option "None" only if no `default_term` (see Open questions). Escape with `esc_attr`/`esc_html`.
- Rely on core's `tax_input` save path (nonce + cap checks included, `assign_terms` cap). No custom save handler, one value per submit guarantees one term.
- Only one term can come from the select, but REST/CLI can still assign several; acceptable, out of scope to enforce.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|---|---|
| 0 | WP running, plugin available | precondition |
| 1 | Aspect Ratio field on Video create/edit | taxonomy attached to `video` (DPB-006) + `meta_box_cb` select |
| 2 | Options `16x9`, `9x16`, `1x1`, `4x3` | seeded terms, rendered in that order |
| 3 | Save on a video | core `tax_input` handling on `save_post` |

## Verification
1. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp`; no PHP notices (`ddev logs`).
2. `ddev php -l` on `aspect-ratio.php`.
3. `ddev wp taxonomy get aspect_ratio --fields=name,hierarchical,public,show_ui,show_in_rest`.
4. `ddev wp term list aspect_ratio --fields=term_id,name,slug` → exactly the four terms. Re-run deactivate/activate → still four (no duplicates).
5. Delete one term, reload admin twice → not recreated (option guard). Recreate via `ddev wp term create aspect_ratio 9x16 --slug=9x16`.
6. Temp attach (not committed): `ddev wp eval 'register_taxonomy_for_object_type("aspect_ratio","post"); $p=wp_insert_post(["post_title"=>"t","post_status"=>"publish"]); var_dump(wp_set_object_terms($p,["4x3"],"aspect_ratio")); print_r(wp_get_object_terms($p,"aspect_ratio",["fields"=>"slugs"]));'` then delete the post.
7. After DPB-006 lands, UI: Add New Video shows Aspect Ratio select with four options in order; pick `9x16` → Publish → reload → persists; change to `1x1` → Update → persists; Videos list column shows the ratio.
8. No frontend archive: `ddev wp rewrite list | grep aspect` → empty. REST route `/wp/v2/aspect_ratio` exists.
9. Regression: `service`, `statistic`, `software_tool`, `video_category` still registered.
10. `git status`: only `aspect-ratio.php`, plugin main file, `AGENTS.md`, DPB-006 plan edit, this plan (leave pre-existing `.gitignore` change alone).
11. Commit: `feat(custom-post-types-dp): add Aspect Ratio taxonomy` + Co-Authored-By trailer.

## Out of scope
Video CPT (DPB-006), frontend use of the ratio (CSS `aspect-ratio`, player sizing), term meta (e.g. numeric width/height), validation of ratio against actual file dimensions, custom capabilities, REST customisation.

## Open questions
- **Default term**: assume `16x9` default so no video lacks a ratio. Alternative: no default, "None" option. Confirm.
- **Menu visibility**: assume Aspect Ratios stays under Videos menu (editable list). Alternative: `show_ui` false on the term screens so admins cannot add/delete ratios, only pick. Spec reads "should see ... following ratios", which suggests a fixed set. Default plan keeps UI editable.
- **Taxonomy vs meta**: this plan treats the ticket as authoritative over DPB-006's meta field. Confirm DPB-006 plan should be edited to drop `cptdp_aspect_ratio` and `21:9`.
- **Naming**: terms use `x` (`16x9`) per ticket, not `16:9`.
