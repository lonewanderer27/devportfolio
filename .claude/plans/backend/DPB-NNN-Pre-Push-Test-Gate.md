# Plan: Pre-push test gate

Ticket DPB-NNN (number to assign). *As a developer, `git push` shall run the tests of the components I created or touched, and when they fail the push shall fail.*
Branch suggestion `chore/DPB-NNN-Pre-Push-Test-Gate`. Tooling only: no plugin or theme behaviour change, no plugin version bump.

**Out of scope:** CI (GitHub Actions) as a server-side gate (see Risks: the hook can be skipped, CI cannot), a pre-commit hook, linting (none is configured), browser/puppeteer checks.

## Context (what exists today)
- No hooks: `.git/hooks` holds only samples, `core.hooksPath` is unset. `.git/` is never shared, so a hook has to live in a tracked folder and be enabled per clone.
- `.gitignore` is allowlist-style, so a new folder is ignored until it gets a `!` entry.
- Three runners, all inside DDEV (AGENTS.md > Tests):
  - plugin suite: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit` (690 tests, 12 skipped);
  - theme suite: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit` (224 tests, all passing);
  - JS: `node --test tests/*.test.mjs` in `wp-content/themes/devportfolio/blocks/document-modal/` (pure modules `pager`, `layout`, `mode`, `capability`, `deeplink`).
- Both PHP suites share one WP test lib and one DB (`wordpress_test`), so they must run **one after the other**, never in parallel. The test lib lives in the container's `/tmp`, so after `ddev restart` it is gone until `bin/install-wp-tests.sh` runs again.
- A plugin change can break theme tests (the theme's nav links to CPT archives), so the gate has to cross over.

## Key decisions
1. **Tracked hook + opt-in install.** `.githooks/pre-push` (tracked) and `bin/install-hooks.sh` (`git config core.hooksPath .githooks`). One-time per clone, idempotent, documented in AGENTS.md. Needs `.gitignore` allowlist entries for `.githooks/` and `bin/`.
2. **"Touched" = what is being pushed, not what is dirty.** Git feeds the hook `<local ref> <local sha> <remote ref> <remote sha>` lines on stdin. Per line:
   - local sha all zeros = branch delete: skip;
   - remote sha all zeros = new branch: range is `merge-base(origin/main, local sha)..local sha`;
   - otherwise `remote sha..local sha`.
   The changed files come from `git diff --name-only --diff-filter=d <range>` (deletions excluded from the file list but a deleted PHP file still counts as touching its suite: use `--name-only` without the filter for the mapping, with it for existence checks).
3. **Granularity = suite, not single test file.** Mapping from path to what runs:

   | Touched path | Runs |
   |---|---|
   | `wp-content/plugins/custom-post-types-dp/**` (php, json, csv, assets, fixtures) | plugin suite, then theme suite |
   | `wp-content/themes/devportfolio/**` php, html templates/parts, `theme.json`, `blocks/*/{block.json,render.php,style.css}`, `tests/**` | theme suite |
   | `wp-content/themes/devportfolio/blocks/**/*.js`, `*.mjs`, `tests/*.test.mjs` (incl. `vendor/`) | JS tests, plus the theme suite (several theme tests parse `view.js`/`style.css` and check vendored version strings) |
   | `.ddev/config.yaml` | nothing automatic (print a reminder to run the suites after `ddev restart`) |
   | only `.claude/plans/**`, `AGENTS.md`, `README.md`, `.github/**`, `license.txt` | nothing, push passes |
   | anything else unmapped | nothing, push passes (printed as "no tests mapped") |

   The mapping lives in one `case` table at the top of the hook so a new component is one line. Narrowing to a single test file (e.g. `includes/post-types/video.php` -> `--filter CPTDP_Test_Video`) is a possible later optimisation, deliberately not v1: the name-to-class mapping is not uniform (`document-*` modules, base test cases) and a wrong narrowing gives false confidence, which is worse than a slower gate.
4. **Run order: cheapest first, stop on first failure.** JS (seconds) -> theme -> plugin only when needed, plugin before theme if both apply because a plugin break explains theme failures. Fail fast; print which suite failed and the PHPUnit/node output tail, exit 1. Exit 0 only when every mapped suite passed.
5. **Known failures are an explicit baseline, not a silent skip.** Run PHPUnit with `--log-junit <tmp>`; the hook fails on any failing test that is not listed in `.githooks/known-failures.txt` (one `Class::method` per line, a comment says why and the ticket that fixes it). A test listed there that now passes is reported as "baseline can shrink" but does not fail the push. Today that file holds the 2 `Devportfolio_Test_Navigation` failures, if they still fail. Prefer fixing them over baselining; the file exists so the gate is usable from day one.
6. **Preconditions fail loudly, never silently pass.**
   - `ddev` not on PATH or project not running: exit 1 with `run ddev start` (the hook does not start DDEV itself: a push should not trigger a multi-minute boot).
   - Plugin `vendor/bin/phpunit` missing: exit 1 with the `ddev composer ... install` line.
   - WP test lib missing in the container (`/tmp/wordpress-tests-lib`, the path `install-wp-tests.sh` uses; confirm in the script): run `bin/install-wp-tests.sh` automatically, because it is the known post-restart state and is safe (its guard refuses the dev DB `db`).
   - Never point anything at the dev DB. The hook only calls the existing runners, which already carry that guard.
7. **Bypass is allowed but visible.** `git push --no-verify` skips all hooks and cannot be blocked client side. The hook prints `SKIP_PREPUSH=1` as the documented emergency bypass (checked first, prints a warning) so the escape hatch is deliberate and greppable. Agents (Claude Code) must not use `--no-verify` or `SKIP_PREPUSH` unless the user says so; add that line to AGENTS.md Git.
8. **Time.** Expect roughly the suite times of today (measure in step 1 and write the numbers in the PR). If the full gate is too slow for pushing WIP, the documented answer is the `SKIP_PREPUSH` escape plus CI later, not weakening the mapping.

## Files
- `.githooks/pre-push` — POSIX/bash, `set -euo pipefail`; reads stdin, computes the range, maps paths, runs suites via `ddev exec`, evaluates the baseline, exits non-zero on failure.
- `.githooks/lib/changed-files.sh` and `.githooks/lib/junit-failures.sh` (or one lib file) — range and JUnit parsing, so they can be tested without git or DDEV. JUnit parsing: use `php`/`xmllint` inside DDEV (host may have neither); decide in step 2.
- `.githooks/known-failures.txt` — the baseline.
- `bin/install-hooks.sh` — sets `core.hooksPath`, `chmod +x` the hook, prints what it enabled.
- `.githooks/tests/test-pre-push.sh` — shell tests that feed fake stdin to the mapping function (no DDEV): docs-only push -> no suites; plugin file -> plugin + theme; theme `view.js` -> JS + theme; new branch (zero remote sha) -> merge-base range; branch delete -> skipped; unmapped path -> pass.
- `.gitignore` — add `!/.githooks/`, `!/.githooks/**` and `!/bin/`, `!/bin/install-hooks.sh` entries (check the allowlist pattern used for the plugin folder).
- `AGENTS.md` — Commands section: install line, what the gate runs, baseline file, bypass policy. Update Tests: "Run both before a PR" now points at the hook.

## Steps
1. Baseline: run both PHP suites and the JS tests on `main`, record counts, durations and any failing test names (decides `known-failures.txt`).
2. Write `.githooks/lib` functions (range, mapping, JUnit failure list) with the shell tests first; make them pass without DDEV.
3. Write `.githooks/pre-push` on top of the lib; add the preconditions and the baseline logic.
4. `bin/install-hooks.sh`, `.gitignore` entries, AGENTS.md.
5. Manual verification (below), then commit as `chore(tooling): add pre-push test gate` and `docs(agents): document the pre-push gate`.

## Verification
- `bin/install-hooks.sh`, then `git config core.hooksPath` prints `.githooks`.
- Green path: push a branch whose diff touches only a plugin file; plugin then theme suites run, push succeeds.
- Red path: temporarily break an assertion in a theme test (or a plugin class); `git push` must exit non-zero and the remote must not receive the ref (`git ls-remote`). Revert.
- Docs-only push: no suite starts, push succeeds in under a second.
- New branch push (no upstream) and force-push both compute a sensible range.
- Branch delete (`git push origin :branch`) is not blocked.
- `ddev stop`, then push a plugin change: fails with the `ddev start` message, not a pass.
- `ddev restart`, then push: the test lib is reinstalled and the suites run.
- Baseline: with the 2 known Navigation failures listed, a push passes; add a third real failure and it blocks.

## Risks / open questions
- **Client-side only.** Anyone (or any agent) can bypass with `--no-verify`, and a fresh clone has no hook until `bin/install-hooks.sh` runs. Real enforcement needs CI (a GitHub Actions workflow running the same three commands) plus branch protection; proposed as the follow-up ticket. Decision needed: is the local gate enough for now?
- **Whole-suite cost.** A one-line theme CSS change runs the full theme suite. Accepted for v1 (see decision 3); revisit with measured times.
- **Baseline drift.** A permanent baseline hides real regressions in the listed tests. Keep it to named tests only, each with a reason, and fix the Services-link failures in their own ticket.
- **Host tools.** The hook assumes only `git`, `bash` and `ddev` on the host; JS tests run via `ddev exec` (Node 24 in the container) rather than host Node to keep one version.
- **Unverified:** exact path of the WP test lib in the container, and whether `0ee39ed` already fixed the 2 Navigation failures: both are checked in step 1 and step 2.
