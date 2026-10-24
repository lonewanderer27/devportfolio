# Plan: Populate Software Tools

Ticket DPB-021 (population follow-up to `DPB-005-software-tool-cpt.md`; renamed from DPB-005-Populate-Software-Tools).

## Context
`software_tool` CPT exists (`includes/post-types/software-tool.php`): title, editor, thumbnail, `menu_order`, meta `cptdp_site_url`. It is empty. Videos and collaterals reference tools via `cptdp_software_tools` (array of `software_tool` IDs), so the tools must exist before those relations can be picked.

## Goals
- Create 31 `software_tool` posts, published, alphabetical, each with a title, a one-sentence description (`post_content`) and `cptdp_site_url`.
- `menu_order` encodes the alphabetical order (the admin list and archive sort by `menu_order title`).
- Repeatable on local and any other environment with one command.
- Safe to re-run: no duplicates, no overwriting manual edits.

## Non-goals
- Thumbnails/logos (uploaded manually in wp-admin; brand assets are not bundled in the repo).
- Any change to plugin behaviour, so **no version bump**.
- Seeding on plugin activation. Tools are content, not structural defaults (unlike the `aspect_ratio` terms): a seeder on activation would resurrect tools the user deleted.

## Tool list (alphabetical, case-insensitive)
`menu_order` steps by 10 so later additions can slot between entries without renumbering.

Titles: "Adobe CC" and "Adobe PDF" keep the supplied short names. Renamed per owner: Google → Google Workspace, Duo → Cisco Duo, Meta → Meta Business Suite. Added per owner: Claude.

URLs and descriptions come from a web search per tool (2026-10-24). Search rarely returned the vendors' own pages, so each URL is the vendor domain corroborated by third-party sources; the owner confirmed `chatgpt.com`, `metricool.com` and `zoom.com`. `claude.ai` was added without a search. Descriptions are paraphrased from search results.

| Order | Title | Site URL | Description |
|---|---|---|---|
| 10 | Adobe CC | https://www.adobe.com/creativecloud.html | Adobe's subscription suite of creative apps, including Photoshop, Illustrator, Premiere Pro and InDesign. |
| 20 | Adobe PDF | https://acrobat.adobe.com/us/en/acrobat.html | Adobe Acrobat, for creating, editing and sharing PDF documents. |
| 30 | Asana | https://asana.com | Work management platform for planning, tracking and delivering team work as tasks with owners and due dates, in list, board or calendar views. |
| 40 | Buffer | https://buffer.com | Social media tool for planning, scheduling and publishing posts across multiple networks from one dashboard. |
| 50 | Calendly | https://calendly.com | Scheduling tool that lets people book meetings from shared availability, syncing with calendars like Google Calendar and Outlook. |
| 60 | Canva | https://www.canva.com | Online drag-and-drop design platform with templates for social graphics, presentations, posters and print. |
| 70 | CapCut | https://www.capcut.com | ByteDance's all-in-one video editor, with auto captions, keyframe animation, background removal and 4K export. |
| 80 | ChatGPT | https://chatgpt.com | OpenAI's AI assistant for writing, research, brainstorming and problem solving. |
| 90 | Cisco Duo | https://duo.com | Cisco's access security platform, providing multi-factor authentication and single sign-on. |
| 100 | Claude | https://claude.ai | Anthropic's AI assistant for writing, analysis, research and coding. |
| 110 | ClickUp | https://clickup.com | All-in-one project management platform with tasks, docs, goals, whiteboards and built-in time tracking. |
| 120 | Discord | https://discord.com | Chat platform for communities and teams, with text, voice and video channels. |
| 130 | Dropbox | https://www.dropbox.com | Cloud storage for securely storing, syncing, sharing and collaborating on files. |
| 140 | Google Workspace | https://workspace.google.com | Gmail, Calendar, Meet and Chat for communication, Drive for storage, and the Docs editors for creating content. |
| 150 | Hootsuite | https://www.hootsuite.com | Social media management dashboard for scheduling and publishing posts, analytics and social listening. |
| 160 | HubSpot | https://www.hubspot.com | CRM platform connecting marketing, sales, service and website tools on one customer database. |
| 170 | LastPass | https://www.lastpass.com | Password manager that stores and autofills credentials securely for individuals and teams. |
| 180 | Loom | https://www.loom.com | Video messaging tool that records screen, camera and microphone into an instantly shareable link. |
| 190 | Mailchimp | https://mailchimp.com | Email marketing platform with campaigns, automation, audience segmentation, landing pages and reporting. |
| 200 | Meta Business Suite | https://business.facebook.com | Manage Facebook, Instagram and Messenger from one place, with post scheduling, a unified inbox and insights. |
| 210 | Metricool | https://metricool.com | Social media analytics and scheduling tool with website traffic and ad performance reporting. |
| 220 | Notion | https://www.notion.com | Connected workspace for docs, wikis, projects and databases. |
| 230 | OneDrive | https://www.microsoft.com/en-us/microsoft-365/onedrive/online-cloud-storage | Microsoft's cloud storage, integrated with Microsoft 365 for sharing and co-editing files. |
| 240 | Otter.ai | https://otter.ai | AI meeting assistant that records, transcribes and summarises meetings across Zoom, Google Meet and Microsoft Teams. |
| 250 | Salesforce | https://www.salesforce.com | Customer relationship management (CRM) platform for sales, service and marketing. |
| 260 | Shopify | https://www.shopify.com | E-commerce platform for building online stores and taking payments, including in-person point of sale. |
| 270 | Slack | https://slack.com | Workplace chat app with channels, direct messages, file sharing, calls and app integrations. |
| 280 | Telegram | https://telegram.org | Cloud-based messenger with apps for mobile, desktop and web. |
| 290 | WordPress | https://wordpress.org | Free, open-source content management system, and the platform this portfolio runs on. |
| 300 | Zendesk | https://www.zendesk.com | Customer support platform with ticketing across email, chat, phone and social, plus self-service help centers. |
| 310 | Zoom | https://www.zoom.com | Video communications platform for meetings, webinars and events. |

Source list had 30 entries; Claude makes 31.

The same table is saved as `wp-content/plugins/custom-post-types-dp/bin/software-tools.csv` (columns `order,title,site_url,description`, header row, descriptions quoted). **That CSV is the single source of data.** The seeder (`bin/seed-software-tools.php`) reads it and the tests read it. To add or change a tool, edit the CSV, not the script. This table is a readable copy only; if they differ, the CSV wins.

## Approach
A WP-CLI script, run on demand: `ddev wp eval-file wp-content/plugins/custom-post-types-dp/bin/seed-software-tools.php`.

Why a script, not a migration/activation hook: matches "content, not structure" above; keeps plugin runtime code untouched; the existing `bin/` dir already holds dev tooling.

### Script behaviour (`bin/seed-software-tools.php`)
1. Guard: bail unless `defined( 'WP_CLI' )` and `post_type_exists( 'software_tool' )`.
2. Load the list from `bin/software-tools.csv` (same directory, `__DIR__ . '/software-tools.csv'`) with `fgetcsv`, header row to keys. Abort with `WP_CLI::error` if the file is missing/unreadable, the header is not exactly `order,title,site_url,description`, or any row lacks a title. No data is hardcoded in the script.
3. For each entry, look up an existing `software_tool` by exact title (`get_page_by_title` is deprecated; use `WP_Query` with `title`, `post_status => any`, `fields => ids`, `posts_per_page => 1`).
   - Missing: `wp_insert_post` with `post_status => publish`, `post_content => $description` (plain text; classic editor), `menu_order`, `meta_input => [ 'cptdp_site_url' => $url ]`. The registered `sanitize_callback` runs on `meta_input`, so URLs are validated http(s) only.
   - Exists: skip, log `skip`. Never overwrite title, content, thumbnail, order or URL (manual edits win).
4. Log `created`/`skipped` per row via `WP_CLI::log`, final `WP_CLI::success( "N created, M skipped" )`.
5. Dry run: `ddev wp eval-file <file> dry-run` (read `$args[0]`; `eval-file` has no custom flags). Logs what would be created, writes nothing.

Code style: WPCS (tabs, Yoda). WP-CLI runs with no current user, so `wp_insert_post` applies no kses filtering and plain-text content is stored as is.

## Files to create
- `wp-content/plugins/custom-post-types-dp/bin/software-tools.csv` (done; tracked, `bin/` is not ignored)
- `wp-content/plugins/custom-post-types-dp/bin/seed-software-tools.php` (reads the CSV above)
- `wp-content/plugins/custom-post-types-dp/tests/test-seed-software-tools.php` (see Tests)

## Files to edit
- `.gitignore`: confirm `bin/` is allowlisted (added by DPB-019-020; `install-wp-tests.sh` already tracked). Add nothing if so.
- `AGENTS.md`: Commands section, one line for the seed command; Overview `software_tool` entry mention that the seeder exists.
- `README.md`: only if it documents setup steps (check first; skip otherwise).

## Tests
Add `CPTDP_Test_Seed_Software_Tools` extending `CPTDP_Test_Case`. The script is a flat file, so refactor the data+logic into a function in the script (`cptdp_seed_software_tools( bool $dry_run ): array` returning `[ created, skipped ]`) and `require_once` it from the test (the WP-CLI entry call at file bottom is guarded by `defined( 'WP_CLI' )`).
The CSV is the source of truth for the tests. A helper `csv_rows()` loads `bin/software-tools.csv` with `fgetcsv` (header row to keys), resolved as `dirname( __DIR__ ) . '/bin/software-tools.csv'` from `tests/`. The test fails (not skips) if the file is missing, since the seeder depends on it too. Tests do not hardcode counts or titles.
- CSV sanity: header is exactly `order,title,site_url,description`; every row has 4 non-empty fields; titles unique; `order` ints strictly ascending; titles alphabetical (case-insensitive); URLs `https://`.
- Seeding an empty DB creates one published `software_tool` per CSV row (count = `count( csv_rows() )`).
- For each CSV row, the created post matches: `post_title`, `menu_order` = `order`, `post_content` = `description`, meta `cptdp_site_url` = `site_url`.
- Listing the posts by `menu_order` returns titles in the CSV's order.
- Second run creates 0, skips all rows (idempotent).
- Pre-existing tool with same title as a CSV row is not modified (custom URL, content and order preserved).
- Dry run writes nothing.

Seeder and tests share the one CSV, so there is no drift to guard against. The seeder tests also cover its CSV loading: missing file, wrong header and a row with an empty title each abort without creating posts (use a temp CSV path passed to `cptdp_seed_software_tools( bool $dry_run, string $csv_path = <default> )`).

Run: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seed_Software_Tools`.

## Verification
```bash
ddev wp eval-file wp-content/plugins/custom-post-types-dp/bin/seed-software-tools.php dry-run
ddev wp eval-file wp-content/plugins/custom-post-types-dp/bin/seed-software-tools.php
ddev wp post list --post_type=software_tool --orderby=menu_order --order=ASC --fields=ID,post_title,menu_order,post_status
ddev wp post meta get <ID> cptdp_site_url
ddev wp eval-file wp-content/plugins/custom-post-types-dp/bin/seed-software-tools.php   # expect 0 created, 31 skipped
```
Then check wp-admin Software Tools list (alphabetical, Site URL column populated, thumbnails empty) and `/software-tools/` archive. Click-check all 31 URLs (curl `-I -L`) before merging, since search did not fetch them.

Take `ddev snapshot` before the first real run on any non-throwaway DB.

## Git
Branch `feat/DPB-021-Populate-Software-Tools`. Commits:
- `feat(custom-post-types-dp): add software tool seed script`
- `test(custom-post-types-dp): cover software tool seeder`
- `docs(agents): document software tool seeding`
- `docs(plans): add DPB-021 populate software tools plan`

## Decisions made
- "Adobe CC" / "Adobe PDF" keep short names.
- Google → Google Workspace, Duo → Cisco Duo, Meta → Meta Business Suite.
- Claude added (31 tools).
- URLs confirmed by owner: `chatgpt.com`, `metricool.com`, `zoom.com`.
- Descriptions go in `post_content`, one sentence each.

## Open questions
1. Production run: via SSH `wp eval-file`, or re-run on prod after DB sync from local? Local DB is not the source of truth for prod content.

## Phase 2: Seeder framework (planned, not started)
More seeders are coming (e.g. services, statistics, then videos/collaterals that reference tools). Phase 1 above is a one-off `bin/` script run with `eval-file`; each new seeder would repeat the CSV, skip and logging code and need its own long command. Phase 2 replaces it with one WP-CLI command and one small file per seeder. Phase 1 behaviour and tests are preserved.

### Layout
```
custom-post-types-dp/
  includes/
    cli/
      class-seed-command.php     # `wp cptdp seed ...`, loaded only when WP_CLI
      seeder-helpers.php         # read_csv(), find_by_title(), result reporting
    seeders/
      software-tools.php         # registers one seeder, defines cptdp_seed_software_tools()
  data/seeders/
    software-tools.csv           # data apart from code (moved from bin/)
  tests/seeders/
    class-cptdp-seeder-test-case.php   # shared CSV-sanity and idempotency helpers
    test-software-tools.php            # moved from tests/post-types/test-seed-software-tools.php
  bin/                           # dev tooling only (install-wp-tests.sh)
```

### Commands
```bash
ddev wp cptdp seed list                       # show registered seeders
ddev wp cptdp seed software-tools             # run one
ddev wp cptdp seed --all [--dry-run]          # run all, in registration order
```
`--dry-run` is a real flag now (Phase 1's positional `dry-run` arg goes away).

### Contract
- Each seeder file registers itself via a `cptdp_seeders` filter: `name => [ 'label', 'callback', 'csv' ]`. Callback signature `( bool $dry_run, string $csv_path )`, returns `[ created, skipped ]` (title lists) or `WP_Error`.
- The loader globs `includes/seeders/*.php` the same way it does `includes/post-types` and `includes/settings` (skipping `index.php`). `includes/cli/` is required only `if ( defined( 'WP_CLI' ) && WP_CLI )`, so there is no cost on web requests. Seeder files only define functions and a filter, so loading them in tests is safe.
- Shared helpers in `seeder-helpers.php` replace the Phase 1 copies: `cptdp_seed_read_csv( $path, array $header )` (exact header, per-row validation, whole file validated before any write), `cptdp_seed_find_by_title( $post_type, $title )` (any status incl. Trash), and result formatting for the CLI.
- `--all` runs seeders in registration order. Seeders that depend on others (videos reference `software_tool` IDs) register later and look dependencies up by title; a missing dependency is a `WP_Error`, not a silent skip.

### Rules every seeder keeps
- Idempotent: skip by key (title or slug), never overwrite manual edits or resurrect deleted/trashed items.
- Content, not structure: never run on activation. Structural defaults (e.g. `aspect_ratio` terms) stay in their modules.
- Validate the whole CSV before writing anything.
- Plain-text CSV in `data/seeders/<name>.csv`, header row, one file per seeder.

### Steps
1. `git mv` the CSV to `data/seeders/software-tools.csv`; `git mv` the script to `includes/seeders/software-tools.php`, dropping the `eval-file` guard block and WP_CLI output (moved to the command class).
2. Extract `read_csv` and the title lookup into `includes/cli/seeder-helpers.php`; keep function names stable where tests use them, or update tests.
3. Add `includes/cli/class-seed-command.php` with `list`, `<name>`, `--all`, `--dry-run`. Errors via `WP_CLI::error`, per-row `WP_CLI::log`, final `WP_CLI::success`.
4. Extend `class-loader.php`: glob `includes/seeders/`, and require `includes/cli/` under the `WP_CLI` guard. Register the command with `WP_CLI::add_command( 'cptdp seed', ... )`.
5. Tests: add `CPTDP_Seeder_Test_Case` (CSV well-formed, seeds one per row, order, idempotent, existing not modified, trashed not recreated, dry-run, missing/wrong-header/empty-title errors), make the software-tools test extend it and keep only tool-specific assertions. Add loader test: seeders registered via the filter, `list` data complete (name, label, existing CSV). Command class itself is not unit-tested; verify manually.
6. Docs: `AGENTS.md` (replace the "Seed Software Tools" section with the `wp cptdp seed` commands, update the Overview paths and the layout/tests notes), README if it mentions seeding. Add a short "Adding a seeder" checklist: CSV in `data/seeders/`, one file in `includes/seeders/` registering the filter, one test extending the base class.
7. Bump the plugin `Version` header (new CLI feature shipped in the plugin): 0.15.0 to 0.16.0, in both header and `CPTDP_VERSION`, per the repo convention (confirm below).

### Verification
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit      # full suite green
ddev wp cptdp seed list
ddev wp cptdp seed --all --dry-run                     # 0 written
ddev wp cptdp seed software-tools                      # on seeded DB: 0 created, 31 skipped
```
Take `ddev snapshot` before any run that writes.

### Git
Same branch or a new one, decided below. Commits: `refactor(custom-post-types-dp): move software tool seeder into seeder framework`, `feat(custom-post-types-dp): add wp cptdp seed command`, `test(custom-post-types-dp): add seeder base test case`, `docs(agents): document seeder framework`, `chore(custom-post-types-dp): bump version to 0.16.0`.

### Open questions (Phase 2)
1. Land on this branch before the PR, or merge Phase 1 first and do Phase 2 as its own ticket/branch (suggest the latter: Phase 1 is reviewable and working).
2. Version bump to 0.16.0 for a CLI-only feature, or skip (as for Phase 1)?
3. Data dir name: `data/seeders/` vs `seeders/data/`.

## Status
Phase 1 executed 2026-10-24 on branch `feat/DPB-021-Populate-Software-Tools`. Ticket number settled as DPB-021 (plan file renamed). Local dev DB seeded (31 created; re-run: 0 created, 31 skipped); snapshot `pre-seed-software-tools` taken first. Full PHPUnit suite green (314 tests, 12 skipped, same as before). Test file lives at `tests/post-types/test-seed-software-tools.php`. Production run still to do.
