# Plan: Seed Videos

Ticket DPB-024 (follow-up to `DPB-023-seed-services.md`; uses the seeder framework from `DPB-021-populate-software-tools.md` Phase 2; video CPT from `DPB-006-011-018-Video.md`).

## Context
`video` CPT exists (`includes/post-types/video.php`): title, editor, thumbnail, order; taxonomies `video_category` and `aspect_ratio`; meta `cptdp_video_file` (video attachment ID), `cptdp_embed_url`, `cptdp_post_url`, `cptdp_length` (seconds, `absint`), `cptdp_software_tools`, `cptdp_highlight` (bool), `cptdp_post_statistics`. URL meta goes through `cptdp_video_sanitize_url` (http/https only, invalid becomes empty). `aspect_ratio` is a flat, non-public taxonomy with terms `16x9`, `9x16`, `1x1`, `4x3` seeded once by option `cptdp_aspect_ratio_seeded`; default term `16x9`.

No real videos exist yet. Need **test data** with mobile 9:16 (vertical) videos so the video archive/templates can be built against real vertical embeds. Uploads (`cptdp_video_file`) cannot be seeded from a CSV (needs a file in the media library), so seeded videos use **embed URLs** only. Thumbnails are added manually in wp-admin, as for other seeders.

## Test videos (YouTube Shorts and Vimeo, vertical)
Found by web search, then checked on 2026-10-24 with each host's oEmbed (responds, embeddable, 9:16 embed size). YouTube: 113x200, length from the watch page (`lengthSeconds`). Vimeo: 240x426, length from oEmbed `duration`.

YouTube:

| Video ID | Source title | Channel | Length (s) |
|---|---|---|---|
| `KlE9YewV7SU` | #Shorts Vertical 9:16 Cinematic Vibes | Quiet Masculine Studio | 5 |
| `MAkPGtxU9iM` | How to Resize Any Video to 9:16 Shorts Format | REMASTERED TUTORIAL | 40 |
| `XjFGjPAKImI` | Vertical Videos Are Worse Than They Should Be | Jam2go | 101 |

Vimeo (`https://vimeo.com/<id>`, embed `https://player.vimeo.com/video/<id>`):

| Video ID | Source title | Channel | Length (s) |
|---|---|---|---|
| `674533939` | 1080x1920 (vertical video).mp4 | Wildfire | 7 |
| `1228877855` | Header Video 9:16 | IP-SUISSE | 44 |
| `1175466808` | Trailer - vertical - HD 1080p | digital:works | 85 |

Also verified but not used (spares): `758119528` "REEL - 1080x1920 (2).mp4" (Gravity Global, 16s). `1175466808` is on a Plus account (`is_plus: 1`); if its embed ever shows a domain-privacy or paywall screen, swap in the spare.

Spread of lengths (5s to 101s on YouTube, 7s to 85s on Vimeo) is deliberate: exercises the `m:ss` length formatting and short/long layouts. These are third-party videos, used only as local/test embeds. Seeded titles are our own placeholders ("Test Vertical Video N"), not the creators' titles. Replace with real portfolio videos before launch (CSV edit, then delete the test rows by hand; the seeder never deletes).

Rejected: `jU198COyGAg` (oEmbed returns `Unauthorized`, not embeddable).

## Goals
- Add a `videos` seeder (`wp cptdp seed videos`) that creates the 6 test videos (3 YouTube, 3 Vimeo) on any environment.
- Data lives in `data/seeders/videos.csv`, not in code.
- Each video gets `aspect_ratio` term `9x16`, embed URL, post URL, length, highlight flag.
- Safe to re-run: skip by title, never overwrite manual edits.

## Non-goals
- New CPT fields or plugin behaviour change, so **no version bump** (seeder only, like DPB-021/022/023).
- Seeding `cptdp_video_file` (needs media-library attachments), thumbnails, `video_category`, `cptdp_software_tools`, `cptdp_post_statistics`. Add columns later when real data exists.
- Seeding the `aspect_ratio` terms themselves (already handled by `cptdp_aspect_ratio_seeded`).
- Changes to the shared helpers.

## Data (`data/seeders/videos.csv`)
Columns: `order,title,embed_url,post_url,length,aspect_ratio,highlight,description`.
- `order` digits, maps to `menu_order`.
- `embed_url` `https://www.youtube.com/embed/<id>` or `https://player.vimeo.com/video/<id>`; `post_url` `https://www.youtube.com/shorts/<id>` or `https://vimeo.com/<id>`.
- `length` digits (seconds).
- `aspect_ratio` one of the seeded term slugs; here `9x16`.
- `highlight` `1` or `0`.
- `description` maps to `post_content`, may be empty.

```csv
order,title,embed_url,post_url,length,aspect_ratio,highlight,description
10,Test Vertical Video 1,https://www.youtube.com/embed/KlE9YewV7SU,https://www.youtube.com/shorts/KlE9YewV7SU,5,9x16,1,Placeholder 9:16 short for layout testing.
20,Test Vertical Video 2,https://www.youtube.com/embed/MAkPGtxU9iM,https://www.youtube.com/shorts/MAkPGtxU9iM,40,9x16,0,Placeholder 9:16 short for layout testing.
30,Test Vertical Video 3,https://www.youtube.com/embed/XjFGjPAKImI,https://www.youtube.com/shorts/XjFGjPAKImI,101,9x16,0,Placeholder 9:16 short for layout testing.
40,Test Vertical Video 4,https://player.vimeo.com/video/674533939,https://vimeo.com/674533939,7,9x16,0,Placeholder 9:16 Vimeo video for layout testing.
50,Test Vertical Video 5,https://player.vimeo.com/video/1228877855,https://vimeo.com/1228877855,44,9x16,0,Placeholder 9:16 Vimeo video for layout testing.
60,Test Vertical Video 6,https://player.vimeo.com/video/1175466808,https://vimeo.com/1175466808,85,9x16,0,Placeholder 9:16 Vimeo video for layout testing.
```

`order` uses 10/20/30 like `software-tools.csv`, leaving room to insert.

## Approach
Copy `includes/seeders/software-tools.php`, add meta and term assignment.

`includes/seeders/videos.php`:
- Filter `cptdp_seeders` entry `videos`: label `Videos`, callback `cptdp_seed_videos`, csv `CPTDP_PATH . 'data/seeders/videos.csv'`, post_type `video`, columns as above.
- Callback `cptdp_seed_videos( bool $dry_run, string $csv_path )`:
  1. `WP_Error` `cptdp_seed_no_post_type` unless `post_type_exists( 'video' )`.
  2. `cptdp_seed_read_csv()` with the exact header; return its `WP_Error`.
  3. Validate every row before any write (`cptdp_seed_csv_row`, with row number): `order` and `length` are `ctype_digit`; `highlight` is `0` or `1`; `embed_url` and `post_url` start with `https://`, and `embed_url` starts with `https://www.youtube.com/embed/` or `https://player.vimeo.com/video/` (hosts the front end can embed); `aspect_ratio` is one of `cptdp_aspect_ratio_terms()`.
  4. Per row: `cptdp_seed_title_exists( 'video', title )` then `skipped`. Else (unless dry run) `wp_insert_post` with `post_type video`, `post_status publish`, `post_title`, `post_content`, `menu_order`, `meta_input` (`cptdp_embed_url`, `cptdp_post_url`, `cptdp_length` as int, `cptdp_highlight` as bool). Then `wp_set_object_terms( $post_id, $row['aspect_ratio'], 'aspect_ratio' )`; return its `WP_Error`.
  5. Return `array( 'created' => ..., 'skipped' => ... )`.
- Wrap `wp_insert_post` in `kses_remove_filters()` / `kses_init_filters()` exactly as `services.php` does (DPB-023 deviation: without a privileged user kses mangles `&` in titles and breaks idempotency). Titles here have no `&`, but descriptions and future rows may.
- Term assignment by slug: `wp_set_object_terms` with a string value matches name or slug; for a non-hierarchical taxonomy a string that matches no term would **create** one. Step 3 validation against the fixed term list prevents that. If the term is missing (option seeded flag set but an admin deleted it), call `cptdp_seed_aspect_ratios()` is not an option (guarded by the option), so return `WP_Error` `cptdp_seed_term_missing` when `term_exists( slug, 'aspect_ratio' )` is falsy, before any write.
- Loader globs `includes/seeders/*.php`; nothing else to register.

## Files to create
- `wp-content/plugins/custom-post-types-dp/data/seeders/videos.csv`
- `wp-content/plugins/custom-post-types-dp/includes/seeders/videos.php`
- `wp-content/plugins/custom-post-types-dp/tests/seeders/test-videos.php`

## Files to edit
- `AGENTS.md`: Overview `video` entry, one clause noting it has a seeder (`ddev wp cptdp seed videos`, embed URLs only).

## Tests
`CPTDP_Test_Seeder_Videos extends CPTDP_Seeder_Test_Case`, `seeder_name()` returns `videos`. Generic behaviour comes from the base class. Add only:
- Columns map: `menu_order`, `post_content`, `cptdp_embed_url`, `cptdp_post_url`, `cptdp_length` (int), `cptdp_highlight` (bool), `aspect_ratio` term slug.
- CSV sanity: ascending `order`, every `aspect_ratio` is `9x16`, URLs are https, the video ID in `embed_url` and `post_url` matches per row (YouTube and Vimeo).
- Listing follows CSV order.
- Existing item keeps its meta and terms (pre-create "Test Vertical Video 1" with a custom `cptdp_embed_url` and `16x9`; run; unchanged).
- Temp-CSV validation errors (`cptdp_seed_csv_row`, nothing written): non-numeric `order`, non-numeric `length`, `highlight` of `2`, `http://` URL, `embed_url` on an unsupported host, unknown `aspect_ratio`.
- Missing term: delete the `9x16` term, run, `cptdp_seed_term_missing`, nothing written.
- Invalid URL dropped by the meta sanitizer (as in the software-tools test).

Gotcha (AGENTS.md): the test lib wipes terms after bootstrap, so re-seed the aspect ratio terms in `set_up()` (reuse what the aspect ratio tests do). Core only assigns default terms when the current user can assign terms, so assert the explicit `wp_set_object_terms` result, not the default.

Run: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Videos`, then the full suite.

## Verification
```bash
ddev snapshot
ddev wp cptdp seed list                      # videos appears
ddev wp cptdp seed videos --dry-run          # 6 created (or skipped if present), writes nothing
ddev wp cptdp seed videos                    # 6 created
ddev wp cptdp seed videos                    # 0 created, 6 skipped
ddev wp post list --post_type=video --fields=ID,post_title,menu_order
ddev wp post term list <id> aspect_ratio     # 9x16
ddev wp post meta get <id> cptdp_embed_url
```
Then open `/videos/` and each video in wp-admin: confirm length column (`0:05`, `0:40`, `1:41`, `0:07`, `0:44`, `1:25`), highlight column, and that the YouTube and Vimeo embeds play vertically.

## Git
Branch `dpb-024-seed-videos` off `main`. Commits:
- `feat(custom-post-types-dp): add videos seeder`
- `test(custom-post-types-dp): cover videos seeder`
- `docs(agents): note videos seeder`
- `docs(plans): add DPB-024 seed videos plan`

## Decisions to confirm
1. Video source: 3 YouTube Shorts + 3 Vimeo embeds (proposed) or upload a local file and seed `cptdp_video_file` by filename (needs a file and media sideload logic, bigger scope).
2. Titles: placeholder "Test Vertical Video N" (proposed) or the creators' original titles.
3. `video_category` column: leave out (proposed) or seed a "Test" category.
4. Third-party content: OK as throwaway test embeds (proposed) or use own footage only.
5. Missing `aspect_ratio` term at run time: error `cptdp_seed_term_missing` (proposed) or let WP create the term.

## Phase 2: Aspect ratios seeder

### Context
`aspect_ratio` terms (`16x9`, `9x16`, `1x1`, `4x3`) are created today by `cptdp_seed_aspect_ratios()` (`includes/post-types/aspect-ratio.php`), hooked to `cptdp_register_post_types` at priority 20 and guarded by option `cptdp_aspect_ratio_seeded`, so a term an admin deletes is not recreated on every request. The fixed list lives in `cptdp_aspect_ratio_terms()` and also drives the meta box order.

Gap: once the option is set there is no supported way to bring a deleted term back (the videos seeder then fails with `cptdp_seed_term_missing`). Phase 2 adds an explicit, on-demand seeder for them, so `wp cptdp seed --all` is self-contained and the videos seeder can depend on it.

### Goals
- Add an `aspect-ratios` seeder (`wp cptdp seed aspect-ratios`) that creates any missing term from `data/seeders/aspect-ratios.csv`.
- Run before `videos` in `--all` (the loader globs `includes/seeders/*.php` alphabetically, so `aspect-ratios.php` registers before `videos.php`; assert this in a test, do not rely on it silently).
- Safe to re-run: existing terms are skipped, never modified.

### Non-goals
- Removing or changing the automatic once-per-install seeding on `init`. It stays: the `video` default term (`16x9`) needs the terms to exist before any seeder runs. The seeder is the explicit "restore" path.
- New terms beyond the four, term meta, term descriptions. The set is fixed by `cptdp_aspect_ratio_terms()`.
- Resetting `cptdp_aspect_ratio_seeded`. The seeder neither reads nor writes the option.
- Plugin behaviour change, so **no version bump**.

### Data (`data/seeders/aspect-ratios.csv`)
Columns: `title,slug`. `title` is the term name (the shared helpers require a `title` column), `slug` the term slug. Today both are identical, as in `wp_insert_term( $name, ..., array( 'slug' => $name ) )`.

```csv
title,slug
16x9,16x9
9x16,9x16
1x1,1x1
4x3,4x3
```

Two sources of truth (CSV and `cptdp_aspect_ratio_terms()`) can drift, so a test asserts the CSV titles equal `cptdp_aspect_ratio_terms()` in order. The seeder validates each row against that function too (unknown term = `cptdp_seed_csv_row`), so a CSV edit cannot create a ratio the meta box does not offer.

### Approach
Terms are not posts, so the registry entry takes a `taxonomy` key **instead of** `post_type`. Only the test base class reads `post_type` (`tests/class-cptdp-seeder-test-case.php:52,127`); `seeder-helpers.php` and the WP-CLI command do not, so no production code changes. Update the docblock of `cptdp_get_seeders()` to say "`post_type` or `taxonomy`".

`includes/seeders/aspect-ratios.php` (copy `software-tools.php`):
- Filter `cptdp_seeders` entry `aspect-ratios`: label `Aspect Ratios`, callback `cptdp_seed_aspect_ratio_terms`, csv `CPTDP_PATH . 'data/seeders/aspect-ratios.csv'`, taxonomy `aspect_ratio`, columns `array( 'title', 'slug' )`.
- Callback `cptdp_seed_aspect_ratio_terms( bool $dry_run, string $csv_path )` (distinct name from the existing `cptdp_seed_aspect_ratios()`):
  1. `WP_Error` `cptdp_seed_no_taxonomy` unless `taxonomy_exists( 'aspect_ratio' )`.
  2. `cptdp_seed_read_csv()` with the exact header; return its `WP_Error`.
  3. Validate every row before any write (`cptdp_seed_csv_row`, with row number): `title` is in `cptdp_aspect_ratio_terms()`, `slug` equals `title`, titles unique.
  4. Per row: `term_exists( slug, 'aspect_ratio' )` then `skipped`. Else (unless dry run) `wp_insert_term( title, 'aspect_ratio', array( 'slug' => slug ) )`; return its `WP_Error`.
  5. Return `array( 'created' => ..., 'skipped' => ... )` (titles).

Difference from post seeders: terms have no Trash, so a term an admin deleted **is recreated** by an explicit run. That is the point of the seeder (restore on demand), and it never runs automatically. Documented in the file header and in AGENTS.md.

### Files to create
- `wp-content/plugins/custom-post-types-dp/data/seeders/aspect-ratios.csv`
- `wp-content/plugins/custom-post-types-dp/includes/seeders/aspect-ratios.php`
- `wp-content/plugins/custom-post-types-dp/tests/seeders/test-aspect-ratios.php`

### Files to edit
- `includes/cli/seeder-helpers.php`: docblock only (`taxonomy` as an alternative to `post_type`).
- `AGENTS.md`: Overview `aspect_ratio` entry, one clause noting the seeder (`ddev wp cptdp seed aspect-ratios`, restores deleted terms); Seeders section: one line that an entry may carry `taxonomy` instead of `post_type`.

### Tests
`CPTDP_Test_Seeder_Aspect_Ratios extends CPTDP_Test_Case` (not the post-based `CPTDP_Seeder_Test_Case`, whose generic tests assume posts, titles and Trash). `set_up()` as in `test-aspect-ratio.php`: delete the seeded option, start from a known term state. Cover:
- Registered with `label`, `callback`, `csv`, `taxonomy`, `columns`; callable; CSV exists under `/data/seeders/`; has a `title` column; registered before `videos` in `cptdp_get_seeders()` order.
- CSV titles equal `cptdp_aspect_ratio_terms()`, slug equals title, unique.
- Run on an empty term table creates all four with the right name and slug; dry run writes nothing (reports 4 created).
- Idempotent: second run creates 0, skips 4, term count unchanged.
- Restores a deleted term (delete `4x3`, run, `4x3` back, others untouched). Does not touch `cptdp_aspect_ratio_seeded`.
- Existing term not modified (rename `1x1` to a custom name keeping the slug, run, name unchanged, no duplicate).
- Missing CSV, wrong header, empty title (`cptdp_seed_csv_header` / `cptdp_seed_csv_row`), as in the base class.
- Temp-CSV validation errors, nothing written: title not in the fixed list, slug differing from title, duplicate title.
- Chain: delete the `9x16` term, run `aspect-ratios` then `videos`, videos get created (phase 1's `cptdp_seed_term_missing` no longer blocks).

Run: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Aspect_Ratios`, then the full suite.

### Verification
```bash
ddev snapshot
ddev wp cptdp seed list                          # aspect-ratios appears, before videos
ddev wp cptdp seed aspect-ratios --dry-run       # 0 created, 4 skipped
ddev wp cptdp seed aspect-ratios                 # 0 created, 4 skipped
ddev wp term delete aspect_ratio $(ddev wp term get aspect_ratio 4x3 --by=slug --field=term_id)
ddev wp cptdp seed aspect-ratios                 # 1 created (4x3), 3 skipped
ddev wp term list aspect_ratio --fields=name,slug
ddev wp cptdp seed --all                         # nothing new on a seeded DB
```
Confirm the video default term still works: a new video saved without choosing a ratio gets `16x9` (unchanged, covered by existing tests).

### Git
Same branch `dpb-024-seed-videos`. Commits:
- `feat(custom-post-types-dp): add aspect ratios seeder`
- `test(custom-post-types-dp): cover aspect ratios seeder`
- `docs(agents): note aspect ratios seeder`
- `docs(plans): add DPB-024 phase 2 aspect ratios seeder`

### Decisions to confirm (phase 2)
1. Registry shape: `taxonomy` key instead of `post_type` (proposed, two lines in the test base unaffected) or keep `post_type => video` and special-case the generic tests.
2. Deleted term on explicit run: recreate (proposed, the seeder's purpose) or skip like trashed posts (then the seeder could never restore anything).
3. Keep the automatic on-`init` seeding (proposed) or move it behind the seeder and require a seed run after install (breaks the `video` default term on fresh installs).
4. Keep a CSV at all (proposed, matches the framework, drift covered by a test) or hard-code the list in the seeder (no CSV, but the registry requires one).

## Status
Phase 1 (videos): done. Branch `dpb-024-seed-videos`. All five decisions took the proposed option.
- Deviation: the term-missing check runs for dry runs too, and assignment uses the term ID rather than the slug string. Dropped the "invalid URL dropped by sanitizer" test: `esc_url_raw` percent-encodes a space rather than emptying the URL, and the seeder's own validation already rejects unsupported hosts and non-https.
- Tests: `CPTDP_Test_Seeder_Videos` 24 tests OK; full suite 380 tests OK (12 pre-existing skips).
- Verified on dev DB: dry run 6 would be created; real run 6 created (IDs 173-178, term `9x16`, meta set); re-run 0 created, 6 skipped. Seeded rows left in the dev DB. Snapshot `devportfolio_20261024234747` taken beforehand.

Phase 2 (aspect ratios): done. All four decisions took the proposed option.
- Tests: `CPTDP_Test_Seeder_Aspect_Ratios` 16 tests OK; full suite 396 tests OK (12 pre-existing skips). Tests must `delete_option( 'default_term_aspect_ratio' )` before deleting terms: WP refuses to delete a taxonomy's default term (`16x9`).
- Verified on dev DB (snapshot `devportfolio_20261024235845`): cleared aspect ratios and videos, re-seeded both. `16x9` cannot stay deleted: WP recreates the default term on the next request (new term ID 19), so only 3 of 4 were created by the seeder. Videos re-seeded as IDs 186-191, all `9x16`, lengths 5/40/101/7/44/85. Featured images re-attached to the kept attachments 179-184 (seeder does not set thumbnails). `seed --all`: 0 created, 60 skipped.
