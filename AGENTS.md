# AGENTS.md

Guidance for AI coding agents working in this repo.

## Overview

WordPress portfolio site (`devportfolio`) for Dave John Deluta. Local dev via DDEV. Block theme (Twenty Twenty-Five 1.5) is the base. No custom theme/plugin exists yet.

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

`.gitignore` is allowlist-style. Tracked: `.ddev/config.yaml`, `README.md`, `AGENTS.md`, root `index.php`, `license.txt`, `wp-content/index.php`, and `wp-content/themes/twentytwentyfive/`.

Ignored (do **not** edit expecting it to persist, do not commit):
- WP core: `wp-admin/`, `wp-includes/`, root `wp-*.php` (including `wp-config.php`), `xmlrpc.php`
- `wp-content/uploads|upgrade|cache|backups|backup-db`
- All plugins except `wp-content/plugins/your-custom-plugin/`
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

- Branch `main`. History is short (initial commit + project setup). Use Conventional Commit style (`build:`, `feat:`, `fix:`, `docs:`), matching existing `build: setup project files`.
