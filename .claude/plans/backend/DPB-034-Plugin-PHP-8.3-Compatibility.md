# DPB-034 Lower the plugin's PHP requirement from 8.4 to 8.3

> For the implementer (a smaller model): follow the steps in order. Every edit is an exact find/replace. Run every gate command and compare with "Expect". If any Fact is false, any "Expect" does not match, or a test fails, **stop and report the exact output; do not try to fix it**. Do not run `git add -A`, `git commit` or `git push` unless the user asks; the commit list at the end is what to use when they do.

## Why

The `deploy/wasmer` test deploy failed with `The PHP version on your server is 8.3.36, however the uploaded plugin requires 8.4.` The plugin declares `Requires PHP: 8.4` only because DDEV runs 8.4; the code does not appear to need it. Hostinger and Wasmer both offer 8.3, so lowering the floor keeps the plugin portable.

## Facts (verified 2026-11-25)

1. `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: line 5 ` * Version:           0.36.0`, line 7 ` * Requires PHP:      8.4`, line 20 `define( 'CPTDP_VERSION', '0.36.0' );`.
2. `wp-content/plugins/custom-post-types-dp/composer.json`: `"php": ">=8.4"` in `require`, and a `config` block with `allow-plugins`, `process-timeout`, `sort-packages`.
3. `composer.lock` and `vendor/` of the plugin are **git-ignored** (`.gitignore` lines 26 and 28). They are never committed. The lock currently has `doctrine/instantiator` 2.1.0 (needs `php ^8.4`, dev dependency of PHPUnit 9.6 which accepts `^1.5.0 || ^2`).
4. `php -l` under `php:8.3-cli` (8.3.37) over all 189 `.php` files of the plugin and theme (excluding `vendor/`) printed no syntax error.
5. A grep of the plugin found no PHP 8.4-only feature (property hooks, asymmetric visibility, `new X()->m()`, `array_find/any/all/find_key`, `mb_trim` family, `#[\Deprecated]`, lazy objects). The grep is **not proof**: step 4 is.
6. `tests/test-loader.php` only checks `CPTDP_VERSION` against the regex `^\d+\.\d+\.\d+$`, so a version bump needs no test change.
7. `.ddev/config.yaml` line 4 is `php_version: "8.4"`. `README.md` line 9 is `- PHP 8.4, nginx-fpm`. `AGENTS.md` line 29 starts `- PHP 8.4, nginx-fpm, MariaDB 11.8,`. The theme header already says `Requires PHP: 7.4` (no change).
8. Run all commands from the repo root `/Users/adriane.puzon/Documents/PHP Projects/devportfolio`. Docker and DDEV are installed.

## Decisions already made (do not reopen)

- New floor: **PHP 8.3**. Not 8.2.
- `composer.json` gets `config.platform.php = 8.3.0` so dependencies always resolve for the floor.
- DDEV moves to 8.3 too, so what we test is what we deploy.
- Plugin version becomes `0.36.1`.
- No PHP code change is expected. A failure in step 4 is a finding to report, not something to fix here.

Open question (does not block): move DDEV back to 8.4 once the hosts offer it.

## Out of scope

Wasmer's missing WordPress core, `wp-config.php` and database; refactors; the theme.

## Steps

### 0. Backup

Git protects the code. The dev database and `wp-content/uploads/` are not in git, and step 4 restarts the web container, so snapshot the database first:

```bash
ddev snapshot --name pre-dpb-034
ddev snapshot --list
```

Expect: `pre-dpb-034` appears in the list. If DDEV is not running, run `ddev start` first. `uploads/` is untouched by a PHP version change and the disposable test DB `wordpress_test` is not backed up. Restore with `ddev snapshot restore pre-dpb-034`; delete after the PR merges with `ddev snapshot --cleanup --name pre-dpb-034`.

### 1. Plugin header and version

File `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`. Three edits (use the Edit tool, each `old_string` is unique):

1. old ` * Version:           0.36.0` new ` * Version:           0.36.1`
2. old ` * Requires PHP:      8.4` new ` * Requires PHP:      8.3`
3. old `define( 'CPTDP_VERSION', '0.36.0' );` new `define( 'CPTDP_VERSION', '0.36.1' );`

Gate:

```bash
grep -n "0\.36\|Requires PHP" wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php
```

Expect exactly three lines: `Version: 0.36.1`, `Requires PHP: 8.3`, `CPTDP_VERSION', '0.36.1'`.

### 2. composer.json

File `wp-content/plugins/custom-post-types-dp/composer.json`. Two edits (tabs for indentation, as in the file):

1. old `"php": ">=8.4"` new `"php": ">=8.3"`
2. old
```
	"config": {
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		},
		"process-timeout": 0,
```
   new
```
	"config": {
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		},
		"platform": {
			"php": "8.3.0"
		},
		"process-timeout": 0,
```

Gate (valid JSON and valid composer file):

```bash
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp validate --no-check-lock
```

Expect: `./composer.json is valid` (a warning about the lock file being out of date is fine; step 3 fixes it).

### 3. Regenerate the (git-ignored) lock and vendor for 8.3

```bash
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp update
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp show doctrine/instantiator
```

Expect: the update succeeds, and `show` prints `versions : * 2.0.0` (any `2.0.x`, **not** `2.1.0`). `composer.lock` and `vendor/` are git-ignored, so `git status` must not list them.

### 4. Switch DDEV to PHP 8.3 and run both suites (the gate)

```bash
ddev config --php-version=8.3
ddev restart
ddev exec php -v
```

Expect: the first line starts with `PHP 8.3.`.

```bash
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```

Expect: plugin suite `OK (1024 tests ...)` with 12 skipped, theme suite `OK (787 tests ...)`. A slightly different total is not a failure (`AGENTS.md` may have drifted); any `FAILURES`, `ERRORS`, `Fatal error` or `Call to undefined function` is: stop and report the full output. Warnings or `Deprecated` lines that mention PHP 8.3 are also to be reported.

Then confirm the real site still works:

```bash
ddev wp plugin list --fields=name,status,version
ddev wp cptdp pages status
```

Expect: `custom-post-types-dp` is `active` with version `0.36.1`; `pages status` prints a table (an empty one is fine) with no PHP error.

### 5. Static check (record only, no install)

```bash
docker run --rm -v "$PWD/wp-content":/w:ro php:8.3-cli sh -c 'find /w/plugins/custom-post-types-dp /w/themes/devportfolio -name "*.php" -not -path "*/vendor/*" -print0 | xargs -0 -n1 php -l | grep -v "^No syntax errors"'
```

Expect: no output.

### 6. Docs (three exact edits)

1. `.ddev/config.yaml`: step 4 already changed line 4. Gate: `sed -n 4p .ddev/config.yaml` prints `php_version: "8.3"`. If not, edit old `php_version: "8.4"` (the line without a leading `#`) new `php_version: "8.3"`.
2. `README.md`: old `- PHP 8.4, nginx-fpm` new `- PHP 8.3, nginx-fpm`
3. `AGENTS.md`: old `- PHP 8.4, nginx-fpm, MariaDB 11.8,` new `- PHP 8.3, nginx-fpm, MariaDB 11.8,`. Then, on the line right after the line that starts `- PHP 8.3, nginx-fpm` (the end of that bullet, before the next `- ` bullet), add a new bullet: `- The plugin requires PHP 8.3 (`Requires PHP` header and `composer.json`), the version Wasmer and Hostinger run; it was 8.4 until DPB-034. Do not use 8.4-only features (property hooks, asymmetric visibility, `array_find()`, `mb_trim()`).`

Gate: `grep -rn "8\.4" README.md AGENTS.md .ddev/config.yaml | cut -c1-120` must show no `PHP 8.4` (the `MariaDB 11.8`, `8.4` MySQL comment in `.ddev/config.yaml` and the commented `# php_version: "8.4"` line 40 are fine).

### 7. Final check

```bash
git status --short
git diff --stat
```

Expect tracked changes only in: `custom-post-types-dp.php`, `composer.json`, `.ddev/config.yaml`, `README.md`, `AGENTS.md` (plus this plan as untracked). No `composer.lock`, `vendor/` or `.phpunit.result.cache`.

Then, for the user: after committing and cherry-picking onto `deploy/wasmer`, redeploy. The "requires 8.4" error must be gone; whatever Wasmer reports next (core files, `wp-config.php`, database) is a separate task.

## Rollback

`git revert` the commits (or `git checkout -- <files>` before committing), then `ddev config --php-version=8.4 && ddev restart`. Nothing about this change is stored in the database; if the dev DB is damaged anyway, `ddev snapshot restore pre-dpb-034`.

## Conventional commits

Branch: `fix/DPB-034-Plugin-PHP-8.3` (from `main`). PR title: `fix(custom-post-types-dp): require PHP 8.3 instead of 8.4`

Every commit ends with the footer lines `Refs: DPB-034` and `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths only.

1. `fix(custom-post-types-dp): require PHP 8.3 instead of 8.4`
   Stage: `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`, `wp-content/plugins/custom-post-types-dp/composer.json`
   Body: the 8.4 floor only mirrored DDEV; lint and both suites pass on 8.3.
2. `build: run DDEV on PHP 8.3`
   Stage: `.ddev/config.yaml`
3. `docs(agents): document the PHP 8.3 requirement`
   Stage: `AGENTS.md`, `README.md`
4. `docs(plans): add the DPB-034 PHP 8.3 compatibility plan`
   Stage: `.claude/plans/backend/DPB-034-Plugin-PHP-8.3-Compatibility.md`

PR body from `.github/pull_request_template.md` (tick the change type; Description, Implementation, How to Test), ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.
