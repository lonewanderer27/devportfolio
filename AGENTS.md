# AGENTS.md

Guidance for AI coding agents working in this repo.

## Overview

WordPress portfolio site (`devportfolio`) for Dave John Deluta. Local dev via DDEV. Active theme is `devportfolio` (`wp-content/themes/devportfolio/`), a standalone, greyscale block theme built from the lo-fi wireframes in `.claude/plans/` (originally forked from Twenty Twenty-Five 1.5, which is not tracked in this repo). The Documents page is the `document` archive template (`templates/archive-document.html`), not a Page: a Page slugged `documents` would be shadowed by the CPT archive rewrite.

## Where to look

Detail lives in `docs/agents/`. Read the file for the area you are about to touch; update only that file when you ship.

| Topic | File | Read it when you touch |
|---|---|---|
| Plugin content model | `docs/agents/plugin-cpts.md` | `includes/post-types/`, `includes/settings/`, meta, taxonomies |
| Page image pipeline | `docs/agents/documents-pipeline.md` | `includes/documents/`, conversion, page images, thumbnails |
| Seeders | `docs/agents/seeders.md` | `includes/seeders/`, `data/seeders/` |
| Tests in detail | `docs/agents/tests.md` | any `tests/` folder |
| Theme core | `docs/agents/theme-core.md` | `theme.json`, `templates/`, `parts/`, `functions.php`, `inc/` |
| Documents page | `docs/agents/documents-page.md` | `blocks/document-*` |
| Brand Collaterals page | `docs/agents/collaterals-page.md` | `blocks/collateral-*` |
| Videos page | `docs/agents/videos-page.md` | `blocks/video-*` |
| Shared viewer | `docs/agents/viewer-shared.md` | `blocks/shared/` |
| Home page | `docs/agents/home.md` | `templates/front-page.html`, `blocks/hero`, `contact`, `stats-strip`, `service-list`, `selected-work`, `testimonials`, `software-tools` |

## Environment

- DDEV project `devportfolio`, type `wordpress`, docroot `.` (repo root = WP root).
- PHP 8.4, nginx-fpm, MariaDB 11.8, Node 24, Composer 2, LibreOffice Impress and Calc 25.x with Liberation, Carlito and Caladea fonts (for PPTX/PPT/ODP conversion, DPB-026).
- Run WP/PHP tooling inside the container: `ddev wp ...`, `ddev composer ...`, `ddev exec ...`.
- `wp-config.php` loads `wp-config-ddev.php` (DDEV-generated, git-ignored) when `IS_DDEV_PROJECT=true`. Don't hardcode DB creds or URLs.

## Commands

```bash
ddev start | stop | restart
ddev launch                          # open site
ddev wp <cmd>                        # WP-CLI
ddev snapshot / ddev export-db       # DB backups
ddev wp theme activate devportfolio  # theme has no build step
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary  # PHPCS report, never fails
```

### Seeders

```bash
ddev wp cptdp seed list                      # registered seeders
ddev wp cptdp seed software-tools            # run one
ddev wp cptdp seed --all --dry-run           # preview everything, writes nothing
ddev wp cptdp seed --all                     # run all, in registration order
```

### Tests (PHPUnit 9 + `WP_UnitTestCase`, two suites)

There are two independent suites: **plugin** (`custom-post-types-dp`, the content model) and **theme** (`devportfolio`, the main navigation). Both share one PHPUnit install (the plugin's `vendor/`), one WP test library and one test DB, so the setup is done once. Both run against the dedicated DB `wordpress_test` (table prefix `wptests_`). The WP test library **drops every table in its DB**, so never point it at the dev DB `db`; both `tests/bootstrap.php` files and the plugin's `bin/install-wp-tests.sh` refuse that.

```bash
# one-time (and after `ddev restart`/rebuild: the test lib lives in the container's /tmp)
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp install
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"

# plugin suite
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
# filters: --filter CPTDP_Test_Video | --group settings | --testdox

# theme suite (runner is the plugin's PHPUnit; the theme has no Composer deps of its own)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
# filters: --filter Devportfolio_Test_Navigation | --group theme | --testdox

# every node suite (one line per block)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio 'for d in blocks/*/tests; do set -- $d/*.test.mjs; [ -e "$1" ] || continue; printf "%s " "${d%/tests}"; (cd "${d%/tests}" && node --test --test-reporter=tap tests/*.test.mjs 2>&1 | grep -E "^# (tests|fail)" | tr "\n" " "); echo; done'
```

Run both before a PR that touches the plugin or the theme: a plugin change (e.g. a CPT's archive slug) can break theme tests. `ddev composer ... test` also works for the plugin but hides PHPUnit's output on failure; prefer the `ddev exec` form. Expected totals today: plugin 1088 tests (12 skipped), theme 787 tests, contact JS 20 tests, Documents modal JS 17 tests, video modal JS 49 tests, shared JS 178 tests, collateral modal JS 24 tests, collateral grid JS 18 tests, video grid JS 17 tests, testimonials JS 5 tests and Selected work JS 14 tests (`node --test`), all passing (the two earlier `Devportfolio_Test_Navigation` Services-link failures are fixed, so there are no known failures).

PHPCS with WordPress Coding Standards is a report, never a gate: `ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary` (counts) or `lint` (every finding), ruleset `wp-content/plugins/custom-post-types-dp/phpcs.xml.dist` (plugin and theme PHP, tests excluded). See `docs/agents/tests.md`. No ESLint, no CI.

## What is tracked vs ignored

`.gitignore` ignores WordPress core, generated content, dependencies and every plugin and theme except the allowlisted ones; anything else (a new root file such as `.editorconfig`, the `docs/` folder) is tracked by default. Tracked: `.ddev/config.yaml`, `.github/pull_request_template.md`, `.claude/plans/` (DPB-NNN implementation plans per ticket), `README.md`, `AGENTS.md`, root `index.php`, `license.txt`, `readme.html`, `wp-content/index.php`, `wp-content/themes/devportfolio/`, and `wp-content/plugins/custom-post-types-dp/`.

Ignored (do **not** edit expecting it to persist, do not commit):
- WP core: `wp-admin/`, `wp-includes/`, root `wp-*.php` (including `wp-config.php`), `xmlrpc.php`
- `wp-content/uploads|upgrade|cache|backups|backup-db`
- All plugins except `wp-content/plugins/custom-post-types-dp/`
- All themes except `devportfolio` (WordPress's bundled themes, such as `twentytwentyfive`, may sit on disk untracked)
- `vendor/` (root and `wp-content/plugins/custom-post-types-dp/vendor/`), `node_modules/`, `composer.lock`, `.phpunit.result.cache`, `style.min.css` in themes, `*.sql`, `.env*`, `*.log`

To track a new custom theme or plugin, add a `!` allowlist entry in `.gitignore` first.

## Conventions

- **Plans must be implementable by a smaller model (Haiku 5.5 or similar).** Every plan in `.claude/plans/` is written so a lower-tier model can execute it without design decisions: give each new file **in full** (tabs, final code, no "similar to X" or "pick one"), each edit as an exact find/replace pair with the anchor line, the exact shell command for every gate, exact test cases with the values to assert, and the decisions already made (list open questions separately and say they do not block). Say what is out of scope, and tell the implementer to stop and report when the code does not match the plan's "Facts". Do not ask the user to request this again.

- **The Documents page is the reference page** (DPD-034). Future pages (Videos, Collaterals, Services, Home sections) are modelled after it: reuse the shared header, the Hanken Grotesk font and the theme tokens (glass tint and blur and the soft edge line live once in `theme.json` `settings.custom` as `--wp--custom--glass--tint`, `--wp--custom--glass--filter` and `--wp--custom--edge`), copy the `archive-document.html` skeleton (title, filter, shelf, viewer) and its phone rules, and reuse or generalise the blocks instead of writing a second copy. `tests/test-design-system.php` pins the shared look; see `.claude/plans/documents/DPD-034-Design-Redesign-According-To-Design-System.md` (section *Reference page for future pages*) for the checklist. **More of the Documents page is meant to become reusable (generic filter, shelf and item blocks, a shared filter and plank module, more shared tokens), but only when the other pages are implemented:** do not generalise or rename the `document-*` blocks ahead of that; the ticket that builds the second page extracts what it needs first, with the Documents tests unchanged and green, and records it in its plan.

- Prefer editing `theme.json`, templates and parts over adding PHP/CSS.
- Follow WordPress Coding Standards for PHP (tabs, Yoda conditions, escaping output with `esc_*`).
- Text domain: `devportfolio`; function prefix `devportfolio_`; any new pattern slug prefixed `devportfolio/`. Wrap user-facing strings in `esc_html__()` / `esc_html_x()` etc.
- Don't modify WP core files. Customise via the `devportfolio` theme or plugin. WordPress's bundled themes (`twentytwentythree` to `twentytwentyfive`) are not tracked; do not edit them.
- Never commit secrets, DB dumps, or `wp-config.php`.
- `.editorconfig` at the repo root sets tabs, LF and a final newline (spaces for YAML; vendored files are left alone). Apply it to files you touch; never reformat untouched files.
- Ship a feature by updating the matching `docs/agents/*.md` file (see Where to look). Edit AGENTS.md itself only when a convention, command or the table changes.

## Git

- Branch `main`; work lands via feature branches and PRs (e.g. `feat/DPB-007-008-012-Document`). Use Conventional Commit style with plugin scope (`feat(custom-post-types-dp):`, `fix(...)`, `feat(theme):`, `test(theme):`, `docs(agents):`, `docs(plans):`, `chore(...): bump version to X.Y.Z`, `build:`).
- Each ticket gets a plan in `.claude/plans/DPB-NNN-*.md`. Bump the plugin `Version` header when a feature ships, and update the Overview section here with new CPTs/taxonomies/meta/settings.
- **Every plan has a "Conventional commits" section**: the ordered commit list (subject, files staged, footer), the branch name and the PR title. Plans live in `.claude/plans/<area>/` (`backend/`, `documents/`, `collaterals/`, `home/`); the file name lists every ticket it covers (`DPC-000-001-002-003-004-...`).
- **Commit rules:**
  - Subject `type(scope): description`: type and scope lowercase, one space after the colon, imperative description starting lowercase (proper nouns keep capitals), no trailing period, **72 characters or fewer**. Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `build`. Scopes: `custom-post-types-dp` (plugin), `theme` (`wp-content/themes/devportfolio/`), `agents` (`AGENTS.md`), `plans` (`.claude/plans/`).
  - One concern per commit; code and its tests in separate commits (a small pair may be squashed), never code from different phases in one commit. Every commit leaves the suites green: no red commit in history (amend before pushing, or add a `fix(...)` commit).
  - Body optional, wrapped at 72, says why. Footer `Refs: <TICKET>[, <TICKET>]`, then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>` on agent commits.
  - Stage explicit paths, never `git add -A` (untracked plan files of other tickets sit in the working tree). Never commit seeded DB rows, `wp-config*`, dumps or `.phpunit.result.cache`.
  - Commit or push only when asked. Branch from `main`: `feat/<TICKETS>-<Short-Name>`.
  - PR: title in the same format (72 characters or fewer), body from `.github/pull_request_template.md` (tick the change type; Description, Implementation, How to Test), ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit, do not squash the commit list away.
