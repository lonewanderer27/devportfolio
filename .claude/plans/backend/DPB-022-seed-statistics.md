# Plan: Seed Statistics

Ticket DPB-022 (follow-up to `DPB-003-statistic-cpt.md`; uses the framework from `DPB-021-populate-software-tools.md` Phase 2).

## Context
`statistic` CPT exists (`includes/post-types/statistic.php`): title, editor (`post_content`), `menu_order`, classic editor. It has no meta. The local DB holds 3 statistics, created by hand in wp-admin on 2026-10-13. Their content is the displayed value and may carry inline HTML.

Existing data (pulled with `ddev wp post list --post_type=statistic`, all `publish`):

| ID | Title | `post_content` | `menu_order` |
|---|---|---|---|
| 32 | Experience | `<b>+5 yrs</b>` | 1 |
| 33 | Happy clients | `+40` | 2 |
| 34 | Typical reply time | `24 hr` | 3 |

No meta beyond `_edit_last`/`_edit_lock`. Note the inconsistency: only "Experience" is wrapped in `<b>`.

## Goals
- Add a `statistics` seeder (`wp cptdp seed statistics`) that recreates these 3 statistics on any environment (fresh DB, prod, CI).
- Data lives in `data/seeders/statistics.csv`, not in code.
- Safe to re-run: skip by title, never overwrite manual edits.

## Non-goals
- New CPT fields, plugin behaviour change or `menu_order` renumbering of existing posts, so **no version bump** (seeder only, like Phase 1).
- Seeding on activation (content, not structure).
- Adding new statistics beyond the 3 that exist. More rows are a CSV edit later.

## Data (`data/seeders/statistics.csv`)
Columns: `order,title,value`. `value` maps to `post_content`. Stored verbatim from the DB, including the `<b>` tag on Experience.

```csv
order,title,value
1,Experience,<b>+5 yrs</b>
2,Happy clients,+40
3,Typical reply time,24 hr
```

`order` keeps the existing 1/2/3 so seeded and hand-made environments match. (Software tools step by 10; statistics keep the live values rather than renumber. Decision below.)

## Approach
Copy `includes/seeders/software-tools.php`.

`includes/seeders/statistics.php`:
- Filter `cptdp_seeders` entry `statistics`: label `Statistics`, callback `cptdp_seed_statistics`, csv `CPTDP_PATH . 'data/seeders/statistics.csv'`, post_type `statistic`, columns `array( 'order', 'title', 'value' )`.
- Callback `cptdp_seed_statistics( bool $dry_run, string $csv_path )`:
  1. `WP_Error` `cptdp_seed_no_post_type` unless `post_type_exists( 'statistic' )`.
  2. `cptdp_seed_read_csv()` with the exact header; return its `WP_Error`.
  3. Validate every row before any write: `order` is `ctype_digit`, `value` non-empty (`cptdp_seed_csv_row` error, with row number).
  4. Per row: `cptdp_seed_title_exists( 'statistic', title )` then `skipped`; else (unless dry run) `wp_insert_post` with `post_type statistic`, `post_status publish`, `post_title`, `post_content = value`, `menu_order = (int) order`, `true` for WP_Error.
  5. Return `array( 'created' => ..., 'skipped' => ... )`.

HTML in `value`: WP-CLI has no current user, so `wp_insert_post` applies no kses and `<b>` is stored as is. No meta, so no sanitizer involved. Nothing else to do in the loader: it globs `includes/seeders/*.php`.

## Files to create
- `wp-content/plugins/custom-post-types-dp/data/seeders/statistics.csv`
- `wp-content/plugins/custom-post-types-dp/includes/seeders/statistics.php`
- `wp-content/plugins/custom-post-types-dp/tests/seeders/test-statistics.php`

## Files to edit
- `AGENTS.md`: Overview `statistic` entry, one clause noting it has a seeder (`ddev wp cptdp seed statistics`). The Seeders section needs no change (generic).

## Tests
`CPTDP_Test_Seeder_Statistics extends CPTDP_Seeder_Test_Case`, `seeder_name()` returns `statistics`. Generic behaviour (CSV well-formed, one post per row, idempotent, existing not modified, trashed not recreated, dry run, missing/wrong header/empty title) comes from the base class. Add only:
- Columns map onto posts: `title`, `menu_order` = `order`, `post_content` = `value` verbatim (assert the `<b>` row is not stripped; run as a user with no kses, i.e. no `wp_set_current_user`).
- Listing by `menu_order` follows CSV order.
- Row with non-numeric `order` errors with `cptdp_seed_csv_row` and writes nothing (temp CSV).
- Row with empty `value` errors with `cptdp_seed_csv_row` and writes nothing.
- Existing statistic with the same title keeps its content (pre-create "Experience" with `Custom`, run, assert unchanged).

Run: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Statistics`, then the full suite.

## Verification
```bash
ddev snapshot
ddev wp cptdp seed list                      # statistics appears
ddev wp cptdp seed statistics --dry-run      # on current DB: 0 created, 3 skipped
ddev wp cptdp seed statistics                # on current DB: 0 created, 3 skipped, nothing modified
```
Fresh-DB check (throwaway): trash and empty the 3 statistics, or import a snapshot without them, run the seeder, then `ddev wp post list --post_type=statistic --orderby=menu_order --order=ASC --fields=ID,post_title,post_content,menu_order` and confirm 3 rows matching the table above. Note: trashed items count as existing, so empty the Trash first (`ddev wp post delete <ids> --force`) for this check.

## Git
Branch `feat/DPB-022-Seed-Statistics`, created off `main` at `fff0a24` (PR #12, DPB-021 seeder framework, merged). Commits:
- `feat(custom-post-types-dp): add statistics seeder`
- `test(custom-post-types-dp): cover statistics seeder`
- `docs(agents): note statistics seeder`
- `docs(plans): add DPB-022 seed statistics plan`

## Decisions to confirm
1. `order` values: keep live 1/2/3 (proposed, matches existing data) or renumber 10/20/30 like software tools to leave room for inserts.
2. Experience `<b>` wrapper: keep verbatim (proposed, faithful to existing data) or drop it and style via theme/pattern, which would match the other two values.
3. ~~Branch base~~ Decided: DPB-021 merged to `main` (PR #12); branch is based on `main`.

## Status
(empty)
