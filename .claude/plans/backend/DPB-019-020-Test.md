# Plan: PHPUnit tests (WP_UnitTestCase) for `custom-post-types-dp`

Tickets DPB-019 and DPB-020.

## Context
No test suite exists. Plugin is v0.15.0 with 7 CPTs/taxonomies (`service`, `statistic`, `software_tool`, `video`, `document`, `collateral`; `video_category`, `aspect_ratio`, `document_category`, `collateral_category`) and one settings module (Contact). Logic worth testing is mostly pure sanitizers, meta registration, taxonomy seeding, and save handlers. Modules self-register via `cptdp_register_post_types` (fired on `init`), so the test bootstrap must load the plugin before `init`.

## Goals
- Regression safety when adding CPTs/meta (each new ticket adds a test file).
- Run locally via `ddev` with one command: `ddev composer test`.
- No change to shipped plugin behaviour.

## Non-goals
- Browser/JS tests (`assets/admin-*.js` media pickers, repeaters).
- Theme tests. Visual/block-template tests.
- CI wiring (separate ticket; `.github/` has only a PR template).

## Tooling decisions
- **PHPUnit 9.x** + **`yoast/phpunit-polyfills` ^2** (required by WP test lib). WP core test suite is not compatible with PHPUnit 10+.
- **WP test library** (`wordpress-develop` `tests/phpunit/includes`) via the standard `bin/install-wp-tests.sh`, installed into `/tmp/wordpress-tests-lib` inside the web container.
- **Separate test DB** (`wordpress_test`) on the DDEV `db` host. The test lib **drops all tables** in its DB, so it must never point at the dev DB `db`. Guard in bootstrap: abort if `DB_NAME` is `db`.
- Dev deps live in a **plugin-level `composer.json`** (`wp-content/plugins/custom-post-types-dp/composer.json`, `require-dev` only) so `vendor/` stays git-ignored and nothing leaks into WP root.

## Files to create
```
wp-content/plugins/custom-post-types-dp/
  composer.json                 # require-dev, "test": "phpunit" script
  phpunit.xml.dist              # bootstrap, testsuite dir tests/
  bin/install-wp-tests.sh       # stock WP-CLI scaffold script
  tests/
    bootstrap.php               # load WP test lib, tests_add_filter('muplugins_loaded') -> require plugin
    class-cptdp-test-case.php   # base class (helpers, see below)
    post-types/
      test-service.php
      test-statistic.php
      test-software-tool.php
      test-video.php
      test-document.php
      test-collateral.php
    taxonomies/
      test-video-category.php
      test-aspect-ratio.php
      test-document-category.php
      test-collateral-category.php
    settings/
      test-contact.php
    test-loader.php
```
Prefix test classes `CPTDP_Test_*`; file names `test-*.php`.

## Files to edit
- `.gitignore` (allowlist-style): add `!` entries for the new tracked files (`composer.json`, `phpunit.xml.dist`, `bin/`, `tests/`) and ignore `wp-content/plugins/custom-post-types-dp/vendor/` and `.phpunit.result.cache`. Do this **first**; per AGENTS.md, untracked paths silently stay ignored.
- `AGENTS.md`: Commands section (`ddev composer test`, one-time setup), "What is tracked" list, and replace "No test suite" line.
- `custom-post-types-dp.php`: bump `0.15.0` -> `0.16.0` (header + `CPTDP_VERSION`) only if the repo convention counts tooling as a feature; otherwise skip (open question).

## Bootstrap
`tests/bootstrap.php`:
1. Resolve `WP_TESTS_DIR` (env, default `/tmp/wordpress-tests-lib`).
2. Require `includes/functions.php`; `tests_add_filter( 'muplugins_loaded', ... )` requires `custom-post-types-dp.php`.
3. Require `includes/bootstrap.php`.
4. The loader loads modules on `plugins_loaded`; `init` then fires `cptdp_register_post_types`, so CPTs exist by the time tests run. No activation hook is run in bootstrap; activation path (register + flush) gets its own test.

## Base test case (`CPTDP_Test_Case extends WP_UnitTestCase`)
- Helpers: `make_video( array $meta = [] )`, `make_software_tool()`, `make_attachment( string $mime )` (via `$this->factory()->attachment->create_upload_object` or a fake attachment post with `post_mime_type`), `acting_as( 'editor' )`.
- `set_up()` calls `parent::set_up()`; reset `cptdp_aspect_ratio_seeded` option where seeding is under test.
- Use `$this->factory()->post->create( [ 'post_type' => 'video' ] )` not hand-built `wp_insert_post` where possible.

## Test matrix

### Registration (every CPT, data-provider driven)
`post_type_exists`, `get_post_type_object()` assertions: `public`, `has_archive`, `rewrite['slug']`, `supports` list, `menu_position`, `show_in_rest`, taxonomies attached. One provider row per CPT mirrors the AGENTS.md overview, so doc drift fails a test.

### Taxonomies
- Exists, hierarchical flag, `public`, rewrite slug (`video-category`, `document-category`, `collateral-category`; `aspect_ratio` has none), attached object types.
- `aspect_ratio`: seeded terms exactly `16x9`, `9x16`, `1x1`, `4x3`; seeding idempotent (run twice, count stays 4); option `cptdp_aspect_ratio_seeded` set; deleting a term is not re-seeded; default term `16x9` assigned to a new video.
- Temporary top-level menus (`video_category` etc.): appear only when parent CPT missing. Assert via `$GLOBALS['menu']` after `do_action( 'admin_menu' )` as admin; likely low value, mark optional.

### Sanitizers (pure functions; highest value, cheapest)
Data providers for each:
- `cptdp_video_sanitize_url` (+ document/collateral equivalents): http/https pass; `javascript:`, `ftp:`, `data:`, relative, whitespace-padded, non-string.
- `cptdp_video_sanitize_file` and document/collateral file sanitizers: valid-type attachment passes; wrong mime (e.g. image for video), missing ID, string, negative, 0 -> `0`. Document: rejects image/video/audio. Collateral: rejects video/audio.
- `*_sanitize_software_tools`: non-array, dupes removed, non-`software_tool` IDs dropped, reindexed.
- `*_sanitize_statistics`: non-array, non-array rows, both-empty rows dropped, HTML stripped via `sanitize_text_field`, reindexed.
- `cptdp_video_format_length`: `0`, `59`, `60`, `3599`, `3600`, `3661`.

### Meta registration and REST
- `registered_meta_key_exists( 'post', key, 'video' )` for each key; type, `single`, `default`, `show_in_rest`.
- Round trip via `update_post_meta` -> `get_post_meta` shows sanitization applied (sanitize_callback fires on `update_post_meta`).
- `auth_callback`: subscriber cannot edit meta via `current_user_can( 'edit_post_meta', ... )`; editor can.
- REST: `rest_do_request( new WP_REST_Request( 'POST', '/wp/v2/videos/ID' ) )` as editor sets meta; invalid shape (extra key in a statistics row) -> 400 (`additionalProperties: false`). Unauthenticated -> 401/403.

### Admin behaviour (need `set_current_screen`/`wp_set_current_user`)
- Classic-editor filter: `use_block_editor_for_post_type( 'video' )` false; other types unchanged.
- List columns: `apply_filters( 'manage_video_posts_columns', ... )` includes thumbnail/length/highlight/menu_order after title; column renderer output for length (`1:01`), highlight, empty `&mdash;`. Capture with `ob_start`.
- Default order: `pre_get_posts` sets `menu_order title` ASC only for admin main query on that type and no `$_GET['orderby']`.
- Meta box registration: after `do_meta_boxes`/`add_meta_boxes`, `$wp_meta_boxes['video']` has details + software tools; `postcustom` removed.
- Save handlers (meta box POST): build `$_POST` with valid nonce, call the save callback; assert meta stored. Negative cases: missing nonce, bad nonce, autosave (`DOING_AUTOSAVE`), user without `edit_post`, wrong post type. Confirm exact callback names/nonce action names while implementing (read `video.php` below the meta box section, then `document.php`, `collateral.php`).

### Settings: Contact
- `cptdp_get_contact()` defaults when option missing (all three keys, empty strings).
- Sanitize callback: valid email kept, invalid email blanked, URLs restricted to http(s), unknown keys dropped, whitespace trimmed.
- `register_setting` present; settings page registered under Settings; capability `manage_options` (editor denied).

### Loader and activation
- All files in `includes/post-types/` and `includes/settings/` are required, `index.php` skipped (assert one known function per module `function_exists`).
- Activation path: call the activation callback; CPTs registered; rewrite rules flushed (`get_option( 'rewrite_rules' )` contains `videos/?$`, `documents/?$`, `collaterals/?$`, `software-tools/?$`, `statistics/?$`).
- `uninstall.php`: confirm what it does; test only if it deletes options.

### Query/front-end (small)
- Archives resolve: `go_to( get_post_type_archive_link( 'video' ) )` -> `is_post_type_archive( 'video' )`. Requires `$this->set_permalink_structure( '/%postname%/' )` + `flush_rewrite_rules()` in `set_up`.
- Single view of a published video returns 200 query state; draft not publicly visible.

## Committing (Conventional Commits)

Repo convention (see `AGENTS.md` Git): branch + PR, Conventional Commit subjects with the plugin scope, one logical change per commit. No plugin version bump: no shipped behaviour changes (see Open questions).

### Branch
Work is on `main`; branch first, matching the existing naming (`feat/DPB-007-008-012-Document`):

```bash
git switch -c test/DPB-019-020-Test
```

### Commits
Stage explicit paths per commit (never `git add -A`; `vendor/` and `.phpunit.result.cache` are ignored, but be deliberate). Plugin dir below is `wp-content/plugins/custom-post-types-dp/`. Order matters: each commit leaves the tree consistent, and the suite is runnable from commit 3 onwards.

| # | Subject | Paths |
| --- | --- | --- |
| 1 | `chore(.gitignore): ignore plugin vendor and PHPUnit cache` | `.gitignore` |
| 2 | `build(custom-post-types-dp): add PHPUnit tooling and test lib installer` | `composer.json`, `phpunit.xml.dist`, `bin/install-wp-tests.sh` |
| 3 | `test(custom-post-types-dp): add WP_UnitTestCase bootstrap and base classes` | `tests/bootstrap.php`, `tests/class-cptdp-test-case.php`, `tests/class-cptdp-cpt-test-case.php`, `tests/class-cptdp-media-cpt-test-case.php` |
| 4 | `test(custom-post-types-dp): cover post types, meta, saving and REST` | `tests/post-types/` |
| 5 | `test(custom-post-types-dp): cover taxonomies and aspect ratio seeding` | `tests/taxonomies/` |
| 6 | `test(custom-post-types-dp): cover contact settings and loader` | `tests/settings/`, `tests/test-loader.php` |
| 7 | `docs(agents): document PHPUnit workflow` | `AGENTS.md` |
| 8 | `docs(readme): add testing section` | `README.md` |
| 9 | `docs(plans): add DPB-019-020 test plan` | `.claude/plans/DPB-019-020-Test.md` |

Commit 2 uses `build:` (tooling/dependencies), commits 3-6 use `test:`. Subjects are imperative, lowercase after the colon, no trailing period, 72 chars or fewer. Bodies only where the why is not obvious (commit 3: note that the test lib wipes its DB and the bootstrap refuses the dev DB `db`).

Every commit message ends with the attribution trailer:

```
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

### Before each push
- `git status` clean apart from intended paths; confirm nothing from `vendor/`, `.env*` or `*.sql` is staged (`git diff --cached --stat`).
- Run the suite once on the final commit (command in the One-time setup section) and expect all green (12 intentional skips).

### Pull request
- Push the branch and open a PR against `main` using `.github/pull_request_template.md`.
- Title: `test(custom-post-types-dp): add PHPUnit suite (DPB-019, DPB-020)`.
- Body: what is covered, how to run it (link to the README Testing section), the DB safety note, and the known gaps (no autosave test, no JS tests, no CI). End the body with the Claude Code attribution line.
- Merge style: follow the repo (merge commit; history shows `Merge pull request #N`). Do not squash, so the split above survives.

## One-time setup (documented in AGENTS.md)
```bash
ddev exec "cd wp-content/plugins/custom-post-types-dp && composer install"
ddev exec "cd wp-content/plugins/custom-post-types-dp && bash bin/install-wp-tests.sh wordpress_test root root db latest true"
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp test
```
`/tmp/wordpress-tests-lib` is lost on container rebuild; script is idempotent. Consider a `ddev` custom command or `post-start` hook later.

## Verification
- `composer test` green from clean `ddev restart`.
- Dev site DB (`db`) untouched: confirm tables/row counts before and after; bootstrap guard test aborts when pointed at `db`.
- Mutation spot-check: break one sanitizer (e.g. allow `javascript:`) and one CPT arg (`has_archive`), confirm a test fails for each, then revert.
- `ddev snapshot` before first run as cheap insurance.

## Risks
- **Test lib wipes its DB**: mitigated by separate DB + bootstrap guard.
- **PHP 8.4 + PHPUnit 9 deprecations**: WP core test lib may emit deprecation notices on 8.4; pin `WP_TESTS_TAG`/`latest` and, if needed, set `failOnDeprecation=false` in `phpunit.xml.dist` (do not silence plugin deprecations).
- **Global state**: CPTs/taxonomies/meta registered once on `init`, so tests that unregister must restore in `tear_down`.
- **Seeded-option side effects** leak between tests; WP_UnitTestCase rolls back DB via transactions, but `wp_cache` and options autoload need `wp_cache_flush()` where asserted.
- **Attachment fixtures**: real uploads write to `uploads/`; prefer fake attachment posts with `post_mime_type` + `_wp_attached_file` meta, since sanitizers use `wp_attachment_is()` (checks mime only for non-image types).

## Open questions
1. Bump plugin version for tooling-only change? (Recommend no; bump only for shipped behaviour.)
2. CI (GitHub Actions matrix) in this ticket or a follow-up? (Recommend follow-up.)
3. Keep `vendor/` per-plugin or a repo-root `composer.json`? (Recommend per-plugin; root is WP core territory.)
4. Test temporary top-level taxonomy menus, or skip as low value? (Recommend skip.)
