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

## Seeding content

The `custom-post-types-dp` plugin ships WP-CLI seeders that populate placeholder content (and some taxonomy terms) from CSV files. Run them inside DDEV:

```bash
ddev wp cptdp seed list                      # registered seeders
ddev wp cptdp seed software-tools            # run one seeder
ddev wp cptdp seed --all --dry-run           # preview everything, writes nothing
ddev wp cptdp seed --all                     # run all, in registration order
```

Available seeders: `aspect-ratios`, `services`, `software-tools`, `statistics`, `video-categories`, `videos`.

- **Data** lives in `wp-content/plugins/custom-post-types-dp/data/seeders/<name>.csv`. Edit the CSV, not the code.
- **Idempotent**: an item whose title already exists (any status, including Trash) is skipped, never modified. Term seeders (`aspect-ratios`, `video-categories`) recreate a term that was deleted in wp-admin.
- **Validated first**: the whole CSV is checked before anything is written. Use `--dry-run` to preview.
- **Order matters**: `videos` needs the `aspect-ratios` and `video-categories` seeders to have run first. `--all` handles this.
- **Content, not structure**: seeders never run on plugin activation.
- **Thumbnails and uploaded files** are not seeded; add them manually in wp-admin. Seeded videos use YouTube/Vimeo embed URLs.
- Take a `ddev snapshot` before a real run on a database you care about.

To add a seeder, create `data/seeders/<name>.csv` (header row including `title`), `includes/seeders/<name>.php` (copy `software-tools.php`) and `tests/seeders/test-<name>.php`. See `AGENTS.md` for details.

## Testing

The `custom-post-types-dp` plugin has a PHPUnit suite built on WordPress's `WP_UnitTestCase`. It covers CPT and taxonomy registration, meta sanitizers, meta box saving, REST access, admin columns, Contact settings, and the loader.

Tests run inside DDEV against a separate `wordpress_test` database. The WP test library drops every table in its database, so it must never point at the dev DB (`db`). The bootstrap and install script refuse to.

One-time setup (repeat the second command after `ddev restart` or a rebuild, since the test library lives in the container's `/tmp`):

```bash
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp install
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"
```

Run the suite:

```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
```

Useful flags: `--testdox` (readable list), `--filter CPTDP_Test_Video` (one class), `--group settings` (one group; groups: `video`, `document`, `collateral`, `service`, `statistic`, `software-tool`, `taxonomies`, `settings`, `loader`).

Tests live in `wp-content/plugins/custom-post-types-dp/tests/`. When adding a CPT, add a test class extending `CPTDP_CPT_Test_Case` (or `CPTDP_Media_CPT_Test_Case` for the file/URL/statistics pattern). See `AGENTS.md` for gotchas.

## Repository layout

| Path | Purpose |
| --- | --- |
| `.ddev/config.yaml` | DDEV project config (tracked) |
| `wp-content/themes/twentytwentyfive/` | Tracked theme |
| `wp-content/plugins/custom-post-types-dp/` | Master plugin: portfolio CPTs, taxonomies and settings (tracked) |
| `.claude/plans/` | Per-ticket implementation plans (`DPB-NNN-*.md`) |
| `.github/pull_request_template.md` | PR template |
| `wp-admin/`, `wp-includes/`, root `wp-*.php` | WordPress core, **git-ignored**, do not edit |
| `wp-content/uploads/` | Media, git-ignored |

Only custom code is committed. Core, third-party plugins, other themes, DB dumps (`*.sql`), `.env*`, and logs are ignored. See `.gitignore`.

## Custom plugin

`custom-post-types-dp` registers the portfolio content model. Modules in `includes/post-types/` and `includes/settings/` are auto-loaded.

| Type | Slug | Notes |
| --- | --- | --- |
| CPT | `service` | Services offered |
| CPT | `statistic` | Archive `/statistics/` |
| CPT | `software_tool` | Archive `/software-tools/`, site URL meta |
| CPT | `video` | Archive `/videos/`; file/embed/post URL, length, tools, highlight, post statistics |
| CPT | `document` | Archive `/documents/`; file/embed, file type, author, pages, highlight, post statistics |
| Taxonomy | `video_category`, `aspect_ratio`, `document_category` | Aspect ratio is private and seeded once (`16x9`, `9x16`, `1x1`, `4x3`) |
| Settings | Settings → Contact | Email, WhatsApp and LinkedIn URLs (option `cptdp_contact`) |

See `AGENTS.md` for field-level detail.

## Contributing

Branch from `main`, use Conventional Commits (`feat(custom-post-types-dp): ...`), run the [tests](#testing), and open a PR using the template in `.github/`.

## License

WordPress core is GPLv2 or later (see `license.txt`). Twenty Twenty-Five is GPL-2.0-or-later.
