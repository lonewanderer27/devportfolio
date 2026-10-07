# devportfolio

Personal developer portfolio for Dave John Deluta, built on WordPress and developed locally with [DDEV](https://ddev.readthedocs.io/).

## Stack

- WordPress (block theme / Full Site Editing)
- Theme: Twenty Twenty-Five v1.5 (tracked in this repo; base for customisation)
- PHP 8.4, nginx-fpm
- MariaDB 11.8
- Node 24 (only needed for theme CSS build)
- Composer 2

## Prerequisites

- [Docker](https://www.docker.com/) (or compatible runtime)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/)

## Getting started

```bash
git clone <repo-url> devportfolio
cd devportfolio
ddev start
ddev launch          # open the site in a browser
```

First run: visit the site and complete the WordPress installer, or import an existing database:

```bash
ddev import-db --file=path/to/dump.sql.gz
ddev import-files --source=path/to/uploads   # restores wp-content/uploads
```

DDEV generates `wp-config-ddev.php` (DB credentials, `WP_HOME`, `WP_SITEURL`, `WP_DEBUG`) automatically. `wp-config.php` loads it when `IS_DDEV_PROJECT=true`. No manual DB config needed.

### Useful commands

```bash
ddev describe                      # URLs, DB info
ddev wp <command>                  # WP-CLI inside the container
ddev snapshot                      # save DB snapshot
ddev export-db --file=backup.sql.gz
ddev ssh                           # shell in web container
ddev stop
```

## Theme development

Active theme lives in `wp-content/themes/twentytwentyfive/`.

```bash
cd wp-content/themes/twentytwentyfive
npm install
npm run build    # style.css -> style.min.css (postcss + cssnano)
npm run watch
```

Most design is controlled by `theme.json`, `styles/`, `templates/`, `parts/` and `patterns/`. Edit those rather than adding bespoke PHP where possible.

## Repository layout

| Path | Purpose |
| --- | --- |
| `.ddev/config.yaml` | DDEV project config (tracked) |
| `wp-content/themes/twentytwentyfive/` | Tracked theme |
| `wp-content/plugins/your-custom-plugin/` | Place for custom plugin (whitelisted in `.gitignore`) |
| `wp-admin/`, `wp-includes/`, root `wp-*.php` | WordPress core, **git-ignored**, do not edit |
| `wp-content/uploads/` | Media, git-ignored |

Only custom code is committed. Core, third-party plugins, other themes, DB dumps (`*.sql`), `.env*`, and logs are ignored. See `.gitignore`.

## License

WordPress core is GPLv2 or later (see `license.txt`). Twenty Twenty-Five is GPL-2.0-or-later.
