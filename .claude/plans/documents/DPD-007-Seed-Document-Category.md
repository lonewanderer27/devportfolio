# Plan: Seed Document Categories

Ticket DPB-007 (follow-up to `DPB-024-seed-videos.md`, which added the `video-categories` term seeder; `document_category` taxonomy from `DPB-007-008-012-Document.md`; seeder framework from `DPB-021-populate-software-tools.md` Phase 2).

## Context
Ticket: populate sample document categories in the database, in this display order:
1. Executive Docs
2. Operations Docs
3. Presentation Decks
4. Reports

`document_category` exists (`includes/post-types/document-category.php`): flat, public, rewrite `/document-category/`, attached to the `document` CPT. It has no default term and no seeding yet. The `video-categories` seeder (`includes/seeders/video-categories.php`, `data/seeders/video-categories.csv` with columns `title,slug`) is the pattern to copy: it matches an existing term by slug or name and skips it, never modifies it, and an explicit run recreates a term deleted in wp-admin (terms have no Trash).

### Display order
Terms have no native order field (no `menu_order`; `term_order` is only a relation-table column and nothing in this plugin reads it). `get_terms()` and the wp-admin list sort by name by default. The requested order, Executive, Operations, Presentation, Reports, is already alphabetical, and rows are created in CSV order so term IDs ascend in the same order. So no extra ordering mechanism is needed: the order holds for the wp-admin list, the Documents edit box, and any front-end `get_terms()` call with defaults. The CSV order is the source of truth and a test pins it.

## Goals
- Add a `document-categories` seeder (`wp cptdp seed document-categories`) that creates the 4 terms on any environment.
- Data lives in `data/seeders/document-categories.csv`, not in code.
- Safe to re-run: skip by slug or name, never modify an existing term.

## Non-goals
- New taxonomy fields, term ordering (see above) or any plugin behaviour change, so **no version bump** (seeder only, like DPB-021/022/023/024).
- Seeding documents themselves or assigning categories to existing documents (a later ticket, as `videos` did in DPB-024).
- Changes to the shared helpers or the `video-categories` seeder. Some duplication between the two term seeders is accepted; extracting a shared term-seeder helper is not in scope.

## Data (`data/seeders/document-categories.csv`)
```csv
title,slug
Executive Docs,executive-docs
Operations Docs,operations-docs
Presentation Decks,presentation-decks
Reports,reports
```

## Approach
Copy `includes/seeders/video-categories.php`, swap the taxonomy.

`includes/seeders/document-categories.php`:
- Filter `cptdp_seeders` entry `document-categories`: label `Document Categories`, callback `cptdp_seed_document_category_terms`, csv `CPTDP_PATH . 'data/seeders/document-categories.csv'`, taxonomy `document_category`, columns `title`, `slug`.
- Callback `cptdp_seed_document_category_terms( bool $dry_run, string $csv_path )`:
  1. `WP_Error` `cptdp_seed_no_taxonomy` unless `taxonomy_exists( 'document_category' )`.
  2. `cptdp_seed_read_csv()` with header `title,slug`; return its `WP_Error`.
  3. Validate every row before any write (`cptdp_seed_csv_row`, with row number): slug non-empty and equal to `sanitize_title( slug )`; no duplicate title; no duplicate slug.
  4. Per row: `get_term_by( 'slug', ... )` or `get_term_by( 'name', ... )` then `skipped`. Else (unless dry run) `wp_insert_term( title, 'document_category', array( 'slug' => slug ) )`; return its `WP_Error`.
  5. Return `array( 'created' => ..., 'skipped' => ... )`.
- Loader globs `includes/seeders/*.php`; nothing else to register. `--all` runs seeders in registration (filename) order: `aspect-ratios`, `document-categories`, `services`, ... No dependency on other seeders.

## Files to create
- `wp-content/plugins/custom-post-types-dp/data/seeders/document-categories.csv`
- `wp-content/plugins/custom-post-types-dp/includes/seeders/document-categories.php`
- `wp-content/plugins/custom-post-types-dp/tests/seeders/test-document-categories.php`

## Files to edit
- `AGENTS.md`: Overview `document_category` entry (one clause: seeder `document-categories` creates the 4 terms, `ddev wp cptdp seed document-categories`); seeder section: `--all` order now `aspect-ratios`, `document-categories`, `services`, `software-tools`, `statistics`, `video-categories`, `videos`, and `document-categories` added to the list of term seeders that recreate deleted terms.
- `README.md`: "Available seeders" list and the term-seeder idempotency line (lines ~89 and ~92).

## Tests
`CPTDP_Test_Seeder_Document_Categories extends CPTDP_Test_Case` (not `CPTDP_Seeder_Test_Case`: terms have no titles, statuses or Trash). Mirror `tests/seeders/test-video-categories.php`, swapping the taxonomy, with `set_up()` deleting all `document_category` terms first:
- Registered with a complete entry (`label`, `callback`, `csv`, `taxonomy`, `columns`), taxonomy exists, CSV exists under `/data/seeders/`.
- CSV is well formed: titles are exactly `Executive Docs`, `Operations Docs`, `Presentation Decks`, `Reports` in that order, slugs sanitized.
- Creates every term with name and slug.
- **Display order**: after a run, `get_terms( taxonomy document_category, hide_empty false )` with default args returns the four names in the requested order, and ascending `term_id` matches CSV order.
- Dry run writes nothing; second run is idempotent (0 created, 4 skipped).
- Existing hand-made term matched by slug and by name (different slug) is skipped, keeps its description; the other three are still created.
- Restores a deleted term without touching the others.
- Temp-CSV errors: missing file (`cptdp_seed_csv_missing`), wrong header (`cptdp_seed_csv_header`), empty title (`cptdp_seed_csv_row`), and a data provider (empty slug, unsanitized slug, duplicate title, duplicate slug) asserting `cptdp_seed_csv_row` and nothing written.

Run: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Document_Categories`, then the full suite.

## Verification
```bash
ddev snapshot
ddev wp cptdp seed list                              # document-categories appears
ddev wp cptdp seed document-categories --dry-run     # 4 created (or skipped if present), writes nothing
ddev wp cptdp seed document-categories               # 4 created
ddev wp cptdp seed document-categories               # 0 created, 4 skipped
ddev wp term list document_category --fields=term_id,name,slug
```
Then open Documents → Document Categories in wp-admin and confirm the four terms list in the requested order, and that the category box on a Document edit screen shows them.

## Git
Branch `feat/DPB-007-Seed-Document-Category` off `main`. Commits:
- `feat(custom-post-types-dp): add document categories seeder`
- `test(custom-post-types-dp): cover document categories seeder`
- `docs(agents): note document categories seeder`
- `docs(readme): list document-categories seeder`
- `docs(plans): add DPB-007 seed document category plan`

## Decisions to confirm
1. Display order: rely on name sorting plus CSV/creation order (proposed, zero new code) or add real term ordering (a `cptdp_order` term meta, an admin column and a `get_terms` filter), which is only worthwhile if the order must differ from alphabetical later.
2. Slugs: `executive-docs`, `operations-docs`, `presentation-decks`, `reports` (proposed, derived from the names).
3. Term seeder duplication: copy `video-categories.php` (proposed) or first extract a shared term-seeder helper.
4. Plan number: this file reuses ticket number 007 as named in the request, alongside `DPB-007-008-012-Document.md`.
