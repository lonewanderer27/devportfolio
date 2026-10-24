# Plan: Seed Services

Ticket DPB-023 (follow-up to `DPB-022-seed-statistics.md`; uses the seeder framework from `DPB-021-populate-software-tools.md` Phase 2).

## Context
`service` CPT exists (`includes/post-types/service.php`): **hierarchical**, supports title, editor (`post_content`), page-attributes (`menu_order`, parent), classic editor, archive `/services/`. No meta. The local DB holds 16 services, created by hand in wp-admin: 3 top-level categories and 13 children.

Existing data (pulled with `ddev wp post list --post_type=service --post_status=any`, all `publish`):

| ID | Title | Parent | `post_content` | `menu_order` |
|---|---|---|---|---|
| 14 | Social Media Marketing | (top) | Strategic content that builds your audience. | 1 |
| 15 | Digital Marketing | (top) | Marketing support that turns attention into customers. | 2 |
| 16 | Web Creation / Management | (top) | Websites built, updated and kept running. | 3 |
| 17 | Analytics | 14 | (empty) | 1 |
| 18 | Scheduled posting | 14 | (empty) | 2 |
| 19 | Content calendar | 14 | (empty) | 3 |
| 20 | Content creation | 14 | (empty) | 4 |
| 21 | Short-form videos | 14 | (empty) | 5 |
| 22 | AI-generated videos | 14 | (empty) | 6 |
| 23 | Ad analytics | 15 | (empty) | 1 |
| 24 | Brand collaterals | 15 | (empty) | 2 |
| 25 | Digital creation | 15 | (empty) | 3 |
| 26 | Product mockups | 15 | (empty) | 4 |
| 27 | Websites & portfolios | 16 | (empty) | 1 |
| 28 | GoDaddy | 16 | (empty) | 2 |
| 29 | WordPress | 16 | (empty) | 3 |

No meta beyond `_edit_last`/`_edit_lock`. Quirks:
- Only top-level rows have content; children are empty.
- `menu_order` restarts at 1 under each parent.
- ID 14 slug is `social-media-marketing-2` (a `social-media-marketing` slug was taken once, likely by a trashed/deleted draft). The seeder lets WP generate slugs, so a fresh DB gets `social-media-marketing`. Slug is not seeded.
- Titles are all unique across the set (needed, see Approach).

## Goals
- Add a `services` seeder (`wp cptdp seed services`) that recreates these 16 services, with hierarchy, on any environment (fresh DB, prod, CI).
- Data lives in `data/seeders/services.csv`, not in code.
- Safe to re-run: skip by title, never overwrite manual edits.

## Non-goals
- New CPT fields or plugin behaviour change, so **no version bump** (seeder only, like DPB-021/022).
- Seeding on activation (content, not structure).
- More than 2 hierarchy levels, new services beyond the 16, children content. Later additions are CSV edits.
- Changes to the shared helpers (see Approach).

## Data (`data/seeders/services.csv`)
Columns: `order,title,parent,content`. `parent` is the exact title of the parent service, empty for top-level. `content` maps to `post_content`, may be empty.

```csv
order,title,parent,content
1,Social Media Marketing,,Strategic content that builds your audience.
2,Digital Marketing,,Marketing support that turns attention into customers.
3,Web Creation / Management,,Websites built, updated and kept running.
1,Analytics,Social Media Marketing,
2,Scheduled posting,Social Media Marketing,
3,Content calendar,Social Media Marketing,
4,Content creation,Social Media Marketing,
5,Short-form videos,Social Media Marketing,
6,AI-generated videos,Social Media Marketing,
1,Ad analytics,Digital Marketing,
2,Brand collaterals,Digital Marketing,
3,Digital creation,Digital Marketing,
4,Product mockups,Digital Marketing,
1,Websites & portfolios,Web Creation / Management,
2,GoDaddy,Web Creation / Management,
3,WordPress,Web Creation / Management,
```

`order` keeps the live per-parent values so seeded and hand-made environments match. Parents come before their children (required, see below). Check `cptdp_seed_read_csv()` copes with the `&` and `/` characters and empty trailing fields (it should; confirm while writing the test).

## Approach
Copy `includes/seeders/statistics.php`, add parent resolution.

`includes/seeders/services.php`:
- Filter `cptdp_seeders` entry `services`: label `Services`, callback `cptdp_seed_services`, csv `CPTDP_PATH . 'data/seeders/services.csv'`, post_type `service`, columns `array( 'order', 'title', 'parent', 'content' )`.
- Callback `cptdp_seed_services( bool $dry_run, string $csv_path )`:
  1. `WP_Error` `cptdp_seed_no_post_type` unless `post_type_exists( 'service' )`.
  2. `cptdp_seed_read_csv()` with the exact header; return its `WP_Error`.
  3. Validate every row before any write (`cptdp_seed_csv_row` error, with row number):
     - `order` is `ctype_digit`.
     - `parent`, when set, equals the `title` of a row **earlier** in the CSV (so a parent is always created or already present before its child; also rejects self-parent, unknown parent, cycles).
     - Parent row is itself top-level (max 2 levels, matches the data).
     - Titles unique within the CSV (parent lookup and skip-by-title both key on title).
  4. Per row, in CSV order:
     - `cptdp_seed_title_exists( 'service', title )` then `skipped` (child of a skipped parent still gets resolved, see step 5).
     - Else (unless dry run) `wp_insert_post` with `post_type service`, `post_status publish`, `post_title`, `post_content`, `menu_order = (int) order`, `post_parent`, `true` for WP_Error.
  5. Parent ID resolution: keep a local map `title => ID`. When a row is created, store its new ID. When skipped, look up the existing ID with a small local helper (`get_posts` by `title`, `post_type service`, `post_parent 0`, any status incl. Trash, same status list as `cptdp_seed_title_exists`). A child whose parent cannot be resolved to an ID (parent was hand-deleted for good) returns `WP_Error` `cptdp_seed_parent_missing` rather than creating an orphan top-level service. Dry run: nothing is written, so parent IDs are unneeded; skip resolution there but still report created/skipped.
  6. Return `array( 'created' => ..., 'skipped' => ... )`.

Why a local lookup, not a change to `cptdp_seed_title_exists()`: that helper is shared by all seeders and returns bool. A tiny `cptdp_seed_service_id_by_title()` in `services.php` keeps the blast radius to this file. If a third seeder needs it, promote it to `seeder-helpers.php` then.

Trashed parent: counts as existing (same rule as other seeders), child is created under the trashed parent's ID. Restoring the parent from Trash brings it back intact. Documented, not special-cased.

Content: WP-CLI has no current user, so no kses on `post_content`. All current values are plain text anyway. Nothing else to do in the loader: it globs `includes/seeders/*.php`.

## Files to create
- `wp-content/plugins/custom-post-types-dp/data/seeders/services.csv`
- `wp-content/plugins/custom-post-types-dp/includes/seeders/services.php`
- `wp-content/plugins/custom-post-types-dp/tests/seeders/test-services.php`

## Files to edit
- `AGENTS.md`: Overview `service` entry, one clause noting it has a seeder (`ddev wp cptdp seed services`). The Seeders section needs no change (generic).

## Tests
`CPTDP_Test_Seeder_Services extends CPTDP_Seeder_Test_Case`, `seeder_name()` returns `services`. Generic behaviour (CSV well-formed, one post per row, idempotent, existing not modified, trashed not recreated, dry run, missing/wrong header/empty title) comes from the base class. Add only:
- Columns map onto posts: `title`, `menu_order` = `order`, `post_content` = `content`.
- Hierarchy: every child's `post_parent` is the ID of the post titled `parent`; top-level rows have `post_parent` 0; exactly 3 top-level, 13 children.
- Siblings listed by `menu_order` under a parent follow CSV order.
- Partial re-run: pre-create "Digital Marketing" (content `Custom`), run; it is unchanged and its 4 children attach to it (not to a duplicate).
- Parent hand-deleted for good, child missing: errors with `cptdp_seed_parent_missing`.
- Temp-CSV validation errors (`cptdp_seed_csv_row`, nothing written): non-numeric `order`, unknown parent, parent listed after its child, duplicate title, grandchild (parent that is itself a child).

Run: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Services`, then the full suite.

## Verification
```bash
ddev snapshot
ddev wp cptdp seed list                      # services appears
ddev wp cptdp seed services --dry-run        # on current DB: 0 created, 16 skipped
ddev wp cptdp seed services                  # on current DB: 0 created, 16 skipped, nothing modified
```
Fresh-DB check (throwaway): `ddev wp post delete $(ddev wp post list --post_type=service --post_status=any --format=ids) --force`, run the seeder, then `ddev wp post list --post_type=service --orderby=menu_order --order=ASC --fields=ID,post_title,post_parent,menu_order,post_content` and confirm 16 rows matching the table above (IDs will differ; parent links must match by title). Trashed items count as existing, so force-delete rather than trash. Restore the snapshot afterwards if the real IDs matter.

## Git
Branch `dpb-023-seed-services` off `main` (after PR #13, DPB-022). Commits:
- `feat(custom-post-types-dp): add services seeder`
- `test(custom-post-types-dp): cover services seeder`
- `docs(agents): note services seeder`
- `docs(plans): add DPB-023 seed services plan`

## Decisions to confirm
1. Parent column: parent **title** (proposed, human-editable CSV, titles are unique) or parent `order` + level, which is harder to read.
2. `order` values: keep live per-parent 1..n (proposed, matches existing data) or renumber 10/20/30 to leave room for inserts.
3. Children with empty content: keep empty (proposed, faithful to existing data) or write short descriptions now.
4. Parent missing at run time: error `cptdp_seed_parent_missing` (proposed) or fall back to creating the child top-level.

## Status
Done. Branch `dpb-023-seed-services`. All four decisions took the proposed option.
- Deviation: seeder wraps `wp_insert_post` in `kses_remove_filters()` / `kses_init_filters()`. Without a privileged user kses stored "Websites & portfolios" as `Websites &amp; portfolios`, so the title never matched its CSV row (broke idempotency). `services.csv` also quotes the "Websites built, updated and kept running." content (contains commas).
- Tests: `CPTDP_Test_Seeder_Services` 17 tests OK; full suite 357 tests OK (12 pre-existing skips).
- Verified on dev DB: dry run and real run give 0 created, 16 skipped. Fresh-DB run (all services force-deleted) created 16 with correct parents/orders; snapshot restored afterwards.
