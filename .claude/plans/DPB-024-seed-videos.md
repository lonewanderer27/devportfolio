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

## Status
Done. Branch `dpb-024-seed-videos`. All five decisions took the proposed option.
- Deviation: the term-missing check runs for dry runs too, and assignment uses the term ID rather than the slug string. Dropped the "invalid URL dropped by sanitizer" test: `esc_url_raw` percent-encodes a space rather than emptying the URL, and the seeder's own validation already rejects unsupported hosts and non-https.
- Tests: `CPTDP_Test_Seeder_Videos` 24 tests OK; full suite 380 tests OK (12 pre-existing skips).
- Verified on dev DB: dry run 6 would be created; real run 6 created (IDs 173-178, term `9x16`, meta set); re-run 0 created, 6 skipped. Seeded rows left in the dev DB. Snapshot `devportfolio_20261024234747` taken beforehand.
