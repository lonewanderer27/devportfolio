# AGENTS.md

Guidance for AI coding agents working in this repo.

## Overview

WordPress portfolio site (`devportfolio`) for Dave John Deluta. Local dev via DDEV. Active theme is `devportfolio` (`wp-content/themes/devportfolio/`), a standalone, greyscale block theme built from the lo-fi wireframes in `.claude/plans/` (originally forked from Twenty Twenty-Five 1.5, which stays installed but unused). The Documents page is the `document` archive template (`templates/archive-document.html`), not a Page: a Page slugged `documents` would be shadowed by the CPT archive rewrite. Master plugin `custom-post-types-dp` (v0.16.0, `wp-content/plugins/custom-post-types-dp/`; `includes/class-loader.php` globs and requires every file in `includes/post-types/`, `includes/settings/` and `includes/seeders/` on `plugins_loaded`, skipping `index.php`) loads portfolio modules from `includes/post-types/*.php`. Each module hooks its `register_post_type` call to the `cptdp_register_post_types` action (fired on `init` and on plugin activation, followed by a rewrite flush). Registered CPTs: `service` (`includes/post-types/service.php`, hierarchical, title/editor/page-attributes, archive `/services/`, seeder `services` with `parent` column = parent title), `statistic` (`includes/post-types/statistic.php`, public, archive `/statistics/`, title/editor/order, seeder `statistics`), `software_tool` (`includes/post-types/software-tool.php`, public, archive `/software-tools/`, title/editor/thumbnail/order, meta `cptdp_site_url`, admin list columns), `video` (`includes/post-types/video.php`, public, archive `/videos/`, title/editor/thumbnail/order, menu position 22, taxonomies `video_category` and `aspect_ratio`, meta `cptdp_video_file` (video attachment ID), `cptdp_embed_url`, `cptdp_post_url`, `cptdp_length` (seconds), `cptdp_software_tools` (array of `software_tool` IDs), `cptdp_highlight`, `cptdp_post_statistics` (array of `{label, value}` string rows, repeater meta box); `custom-fields` support only exposes meta in REST, its meta box is removed; `assets/admin-video.js` media picker; admin list columns; seeder `videos` creates 6 test 9:16 videos from YouTube/Vimeo embed URLs and assigns one `aspect_ratio` and one `video_category` term each, `ddev wp cptdp seed videos`, needs both term seeders run first, no uploaded files). `document` (`includes/post-types/document.php`, public, archive `/documents/`, title/editor/thumbnail/order, menu position 25, taxonomy `document_category`, meta `cptdp_document_file` (attachment ID, non-image/video/audio), `cptdp_embed_url`, `cptdp_filetype`, `cptdp_author`, `cptdp_total_pages`, `cptdp_highlight`, `cptdp_post_statistics` (array of `{label, value}` string rows, repeater meta box); `custom-fields` support only exposes meta in REST, its meta box is removed; `assets/admin-document.js` media picker and statistics repeater; admin list columns). `collateral` (`includes/post-types/collateral.php`, public, archive `/collaterals/`, title/editor/thumbnail/order, menu position 27, taxonomy `collateral_category`, meta `cptdp_collateral_file` (attachment ID, non-video/audio), `cptdp_embed_url`, `cptdp_paper_size`, `cptdp_total_pages`, `cptdp_software_tools` (array of `software_tool` IDs), `cptdp_highlight`, `cptdp_post_statistics` (array of `{label, value}` string rows, repeater meta box); `custom-fields` support only exposes meta in REST, its meta box is removed; `assets/admin-collateral.js` media picker and statistics repeater; admin list columns). Registered taxonomies: `video_category` (`includes/post-types/video-category.php`, flat, public, rewrite `/video-category/`; attached to `video`, listed under the Videos menu, seeder `video-categories` creates `Content Creation`/`Short Form`/`AI-Generated`, skipping an existing term by slug or name, `ddev wp cptdp seed video-categories`; the temporary top-level menu only shows when `video` does not exist). `aspect_ratio` (`includes/post-types/aspect-ratio.php`, flat, not public, no rewrite, seeded once via option `cptdp_aspect_ratio_seeded` with terms `16x9`/`9x16`/`1x1`/`4x3`, default term `16x9`, single-select meta box; attached to `video`; seeder `aspect-ratios` restores missing terms on demand, `ddev wp cptdp seed aspect-ratios`). `document_category` (`includes/post-types/document-category.php`, flat, public, rewrite `/document-category/`; attached to `document`, listed under the Documents menu, seeder `document-categories` creates `Executive Docs`/`Operations Docs`/`Presentation Decks`/`Reports`, skipping an existing term by slug or name, `ddev wp cptdp seed document-categories`; the temporary top-level menu (position 26) only shows when `document` does not exist). `collateral_category` (`includes/post-types/collateral-category.php`, flat, public, rewrite `/collateral-category/`; attached to `collateral`, listed under the Collaterals menu; the temporary top-level menu (position 28) only shows when `collateral` does not exist). The loader also requires `includes/settings/*.php` (Settings API modules that self-hook on `admin_menu`/`admin_init`). Seeders: `includes/seeders/*.php` each register one seeder via the `cptdp_seeders` filter (`label`, `callback( bool $dry_run, string $csv_path )`, `csv`, `post_type` or, for a term seeder, `taxonomy`, `columns`); shared helpers in `includes/cli/seeder-helpers.php` (`cptdp_get_seeders()`, `cptdp_run_seeder()`, `cptdp_seed_read_csv()`, `cptdp_seed_title_exists()`), the `wp cptdp seed` command in `includes/cli/class-seed-command.php` (loaded only under WP-CLI). Settings pages: Settings → Contact (`includes/settings/contact.php`), stored in option `cptdp_contact` (`email`, `whatsapp_url`, `linkedin_url`); read via `cptdp_get_contact()`.

## Environment

- DDEV project `devportfolio`, type `wordpress`, docroot `.` (repo root = WP root).
- PHP 8.4, nginx-fpm, MariaDB 11.8, Node 24, Composer 2.
- Run WP/PHP tooling inside the container: `ddev wp ...`, `ddev composer ...`, `ddev exec ...`.
- `wp-config.php` loads `wp-config-ddev.php` (DDEV-generated, git-ignored) when `IS_DDEV_PROJECT=true`. Don't hardcode DB creds or URLs.

## Commands

```bash
ddev start | stop | restart
ddev launch                          # open site
ddev wp <cmd>                        # WP-CLI
ddev snapshot / ddev export-db       # DB backups
ddev wp theme activate devportfolio  # theme has no build step
```

### Seeders

```bash
ddev wp cptdp seed list                      # registered seeders
ddev wp cptdp seed software-tools            # run one
ddev wp cptdp seed --all --dry-run           # preview everything, writes nothing
ddev wp cptdp seed --all                     # run all, in registration order
```

Data lives in `wp-content/plugins/custom-post-types-dp/data/seeders/<name>.csv` (edit the CSV, not the code). Seeders are idempotent: an item whose title already exists (any status, incl. Trash) is skipped, never modified. Content, not structure: never run on activation. The whole CSV is validated before any write. Thumbnails are added manually in wp-admin. `ddev snapshot` before a real run on a DB you care about.

Adding a seeder: (1) CSV in `data/seeders/<name>.csv` with a header row incl. `title`; (2) `includes/seeders/<name>.php` that adds its entry to the `cptdp_seeders` filter and defines the callback (copy `software-tools.php`; use `cptdp_seed_read_csv()` and `cptdp_seed_title_exists()`); (3) `tests/seeders/test-<name>.php` extending `CPTDP_Seeder_Test_Case` (implement `seeder_name()`, add column-mapping assertions). `list` is a reserved seeder name. `--all` order: `aspect-ratios`, `document-categories`, `services`, `software-tools`, `statistics`, `video-categories`, `videos` (the videos seeder needs both term seeders first). Term seeders (`aspect-ratios`, `document-categories`, `video-categories`) recreate a term deleted in wp-admin, since terms have no Trash; WP always recreates a taxonomy's default term (`16x9`) on the next request.

### Tests (plugin `custom-post-types-dp`, PHPUnit 9 + `WP_UnitTestCase`)

Suite lives in `wp-content/plugins/custom-post-types-dp/tests/` (config `phpunit.xml.dist`, dev deps in the plugin's `composer.json`, `vendor/` git-ignored). It runs against a dedicated DB `wordpress_test` (table prefix `wptests_`). The WP test library **drops every table in its DB**, so never point it at the dev DB `db`; `tests/bootstrap.php` and `bin/install-wp-tests.sh` both refuse that.

```bash
# one-time (and after `ddev restart`/rebuild: the test lib lives in the container's /tmp)
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp install
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"

# run
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
# filters: --filter CPTDP_Test_Video | --group settings | --testdox

# devportfolio theme tests (wp-content/themes/devportfolio/tests/, own phpunit.xml.dist + bootstrap;
# reuses the plugin's PHPUnit install and the same test DB / test lib, and loads the plugin)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```

`ddev composer ... test` also works but hides PHPUnit's output on failure; prefer the `ddev exec` form.

Layout: shared base classes `tests/class-cptdp-*-test-case.php` (`CPTDP_CPT_Test_Case` for registration/columns/order/archive, `CPTDP_Media_CPT_Test_Case` for the video/document/collateral file+URL+statistics+save+REST pattern); one `test-*.php` per module under `tests/post-types/`, `tests/taxonomies/`, `tests/settings/`, plus `tests/test-loader.php`. A new CPT = a new test class extending the matching base and implementing its `expected*()` methods.

Gotchas: `WP_UnitTestCase` unregisters all meta after every test and wipes terms after bootstrap (options survive), so `CPTDP_Test_Case::set_up()` re-runs `cptdp_register_post_types`, and the aspect ratio tests re-seed. Core only assigns default terms when the current user can assign terms. Bootstrap forces `flush_rewrite_rules_hard` off so tests never write `.htaccess`.

No linter or CI configured.

## What is tracked vs ignored

`.gitignore` is allowlist-style. Tracked: `.ddev/config.yaml`, `.github/pull_request_template.md`, `.claude/plans/` (DPB-NNN implementation plans per ticket), `README.md`, `AGENTS.md`, root `index.php`, `license.txt`, `readme.html`, `wp-content/index.php`, `wp-content/themes/twentytwentyfive/`, `wp-content/themes/devportfolio/`, and `wp-content/plugins/custom-post-types-dp/`.

Ignored (do **not** edit expecting it to persist, do not commit):
- WP core: `wp-admin/`, `wp-includes/`, root `wp-*.php` (including `wp-config.php`), `xmlrpc.php`
- `wp-content/uploads|upgrade|cache|backups|backup-db`
- All plugins except `wp-content/plugins/custom-post-types-dp/`
- All themes except `twentytwentyfive` and `devportfolio`
- `vendor/` (root and `wp-content/plugins/custom-post-types-dp/vendor/`), `node_modules/`, `composer.lock`, `.phpunit.result.cache`, `style.min.css` in themes, `*.sql`, `.env*`, `*.log`

To track a new custom theme or plugin, add a `!` allowlist entry in `.gitignore` first.

## Theme architecture (`wp-content/themes/devportfolio/`)

Block (FSE) theme, intentionally tiny (no patterns, style variations, fonts, images or build step). Design lives in data, not PHP, and follows the lo-fi wireframes: greyscale (palette `base` #fff, `contrast` #222, `mid` #8a8a8a, `line` #c9c9c9, `fill` #eee), system sans font plus Georgia serif, 14px body, h3 as the 34px/800 page title, 1040px content width.
- `theme.json` — palette, typography, spacing, layout, global and block styles (incl. main navigation active-link style)
- `templates/` — `index`, `archive`, `search`, `single`, `page`, `404`, `archive-document` (Documents page: `<h3>Documents</h3>` plus empty `documents-page__content` container for the category buttons and shelf)
- `parts/header.html` — main navigation, 7 inline links (Home, Services, Documents, Videos, Collaterals, Testimonials, Contact) with root-relative URLs, centred, core mobile overlay; `parts/footer.html` — site title
- `functions.php` — one filter: resolves nav links (post type archives via `get_post_type_archive_link()`, others via `home_url()`) so core marks the current archive link with `aria-current` and flags Home on the front page
- `style.css` — theme header only
- `tests/` + `phpunit.xml.dist` — main navigation tests (`Devportfolio_Test_Navigation`, `@group theme`); the bootstrap loads the WP test lib and the plugin (nav links to its archives); see Tests for the command

Testimonials and Contact are Home-page sections, linked as anchors `/#testimonials` and `/#contact`; the Home page sections need matching `id`s. Anchor links are never marked active.

## Conventions

- Prefer editing `theme.json`, templates and parts over adding PHP/CSS.
- Follow WordPress Coding Standards for PHP (tabs, Yoda conditions, escaping output with `esc_*`).
- Text domain: `devportfolio`; function prefix `devportfolio_`; any new pattern slug prefixed `devportfolio/`. Wrap user-facing strings in `esc_html__()` / `esc_html_x()` etc.
- Don't modify WP core files. Customise via the `devportfolio` theme or plugin. `twentytwentyfive` is reference only; do not edit it.
- Never commit secrets, DB dumps, or `wp-config.php`.

## Git

- Branch `main`; work lands via feature branches and PRs (e.g. `feat/DPB-007-008-012-Document`). Use Conventional Commit style with plugin scope (`feat(custom-post-types-dp):`, `fix(...)`, `docs(agents):`, `docs(plans):`, `chore(...): bump version to X.Y.Z`, `build:`).
- Each ticket gets a plan in `.claude/plans/DPB-NNN-*.md`. Bump the plugin `Version` header when a feature ships, and update the Overview section here with new CPTs/taxonomies/meta/settings.
