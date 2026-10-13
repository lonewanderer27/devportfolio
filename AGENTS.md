# AGENTS.md

Guidance for AI coding agents working in this repo.

## Overview

WordPress portfolio site (`devportfolio`) for Dave John Deluta. Local dev via DDEV. Block theme (Twenty Twenty-Five 1.5) is the base. No custom theme exists yet. Master plugin `custom-post-types-dp` (v0.12.0, `wp-content/plugins/custom-post-types-dp/`; `includes/class-loader.php` globs and requires every file in `includes/post-types/` and `includes/settings/` on `plugins_loaded`, skipping `index.php`) loads portfolio modules from `includes/post-types/*.php`. Each module hooks its `register_post_type` call to the `cptdp_register_post_types` action (fired on `init` and on plugin activation, followed by a rewrite flush). Registered CPTs: `service` (`includes/post-types/service.php`), `statistic` (`includes/post-types/statistic.php`, public, archive `/statistics/`, title/editor/order), `software_tool` (`includes/post-types/software-tool.php`, public, archive `/software-tools/`, title/editor/thumbnail/order, meta `cptdp_site_url`, admin list columns), `video` (`includes/post-types/video.php`, public, archive `/videos/`, title/editor/thumbnail/order, menu position 22, taxonomies `video_category` and `aspect_ratio`, meta `cptdp_video_file` (video attachment ID), `cptdp_embed_url`, `cptdp_post_url`, `cptdp_length` (seconds), `cptdp_software_tools` (array of `software_tool` IDs), `cptdp_highlight`, `cptdp_post_statistics` (array of `{label, value}` string rows, repeater meta box); `custom-fields` support only exposes meta in REST, its meta box is removed; `assets/admin-video.js` media picker; admin list columns). `document` (`includes/post-types/document.php`, public, archive `/documents/`, title/editor/thumbnail/order, menu position 25, taxonomy `document_category`, meta `cptdp_document_file` (attachment ID, non-image/video/audio), `cptdp_embed_url`, `cptdp_filetype`, `cptdp_author`, `cptdp_total_pages`, `cptdp_highlight`, `cptdp_post_statistics` (array of `{label, value}` string rows, repeater meta box); `custom-fields` support only exposes meta in REST, its meta box is removed; `assets/admin-document.js` media picker and statistics repeater; admin list columns). Registered taxonomies: `video_category` (`includes/post-types/video-category.php`, flat, public, rewrite `/video-category/`; attached to `video`, listed under the Videos menu; the temporary top-level menu only shows when `video` does not exist). `aspect_ratio` (`includes/post-types/aspect-ratio.php`, flat, not public, no rewrite, seeded once via option `cptdp_aspect_ratio_seeded` with terms `16x9`/`9x16`/`1x1`/`4x3`, default term `16x9`, single-select meta box; attached to `video`). `document_category` (`includes/post-types/document-category.php`, flat, public, rewrite `/document-category/`; attached to `document`, listed under the Documents menu; the temporary top-level menu (position 26) only shows when `document` does not exist). `collateral_category` (`includes/post-types/collateral-category.php`, flat, public, rewrite `/collateral-category/`; no object type yet, the `collateral` post type will attach it; temporary top-level menu (position 28) shows while `collateral` does not exist). The loader also requires `includes/settings/*.php` (Settings API modules that self-hook on `admin_menu`/`admin_init`). Settings pages: Settings → Contact (`includes/settings/contact.php`), stored in option `cptdp_contact` (`email`, `whatsapp_url`, `linkedin_url`); read via `cptdp_get_contact()`.

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
cd wp-content/themes/twentytwentyfive
npm install
npm run build                        # style.css -> style.min.css (postcss + cssnano)
npm run watch
```

No test suite, linter, or CI configured.

## What is tracked vs ignored

`.gitignore` is allowlist-style. Tracked: `.ddev/config.yaml`, `.github/pull_request_template.md`, `.claude/plans/` (DPB-NNN implementation plans per ticket), `README.md`, `AGENTS.md`, root `index.php`, `license.txt`, `readme.html`, `wp-content/index.php`, `wp-content/themes/twentytwentyfive/`, and `wp-content/plugins/custom-post-types-dp/`.

Ignored (do **not** edit expecting it to persist, do not commit):
- WP core: `wp-admin/`, `wp-includes/`, root `wp-*.php` (including `wp-config.php`), `xmlrpc.php`
- `wp-content/uploads|upgrade|cache|backups|backup-db`
- All plugins except `wp-content/plugins/custom-post-types-dp/`
- All themes except `twentytwentyfive`
- `vendor/`, `node_modules/`, `composer.lock`, `style.min.css` in themes, `*.sql`, `.env*`, `*.log`

Note: `style.min.css` is ignored by the pattern `/wp-content/themes/*/style.min.css` but may appear in the working tree; regenerate with `npm run build`.

To track a new custom theme or plugin, add a `!` allowlist entry in `.gitignore` first.

## Theme architecture (`wp-content/themes/twentytwentyfive/`)

Block (FSE) theme. Design lives in data, not PHP:
- `theme.json` — global settings, palette, typography, spacing, block styles
- `styles/` — style variations (`blocks/`, `colors/`, `sections/`, `typography/`)
- `templates/` — block templates (HTML)
- `parts/` — header/footer/sidebar template parts (HTML)
- `patterns/` — PHP-registered block patterns (many; includes `page-portfolio-home.php`, `page-cv-bio.php` relevant to the portfolio)
- `functions.php` — small: theme setup, pattern categories, block styles
- `assets/` — fonts (woff2) and images (webp)
- `style.css` — theme header + minimal CSS; `style.min.css` is the built output

## Conventions

- Prefer editing `theme.json`, templates, parts, and patterns over adding PHP/CSS.
- Follow WordPress Coding Standards for PHP (tabs, Yoda conditions, escaping output with `esc_*`).
- Pattern files need a valid header comment (`Title`, `Slug`, `Categories`, etc.); slugs prefixed `twentytwentyfive/`.
- Text domain: `twentytwentyfive`. Wrap user-facing strings in `esc_html__()` / `esc_html_x()` etc.
- Don't modify WP core files. Customise via theme, child theme, or plugin.
- For substantial customisation, create a child theme (or fork under a new name) rather than hacking upstream Twenty Twenty-Five, so upstream updates stay mergeable.
- Never commit secrets, DB dumps, or `wp-config.php`.

## Git

- Branch `main`; work lands via feature branches and PRs (e.g. `feat/DPB-007-008-012-Document`). Use Conventional Commit style with plugin scope (`feat(custom-post-types-dp):`, `fix(...)`, `docs(agents):`, `docs(plans):`, `chore(...): bump version to X.Y.Z`, `build:`).
- Each ticket gets a plan in `.claude/plans/DPB-NNN-*.md`. Bump the plugin `Version` header when a feature ships, and update the Overview section here with new CPTs/taxonomies/meta/settings.
