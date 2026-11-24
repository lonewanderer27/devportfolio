# DPB-NNN: Codebase cleanup and optimization

Ticket number to be assigned. Rename the file and the branches when it is.

## Goal

Make the repo smaller to read and cheaper to change, and fix the four problems the first pass found (F1 to F4), in **one branch and one pull request**. Most phases are refactors that keep every pass and skip count of the Phase 0 baseline. Four steps change counts or behaviour on purpose, each by the exact numbers it lists:

- **Phase 4 (F3)** adds behaviour: a daily WP-Cron event deletes stray LibreOffice temp folders; the plugin version goes to 0.36.0.
- **Phase 5 (F4)** adds a report-only PHPCS run (tooling, never fails a gate).
- **Phase 8 (F2)** moves the shared viewer CSS to its own file with the cascade order kept.
- **Phase 9 (F1)** changes **when** the three viewers load (on first use instead of up front) and prints their CSS without blocking the first paint. The one visible change: the very first open on a page can wait for the viewer to download (see Phase 9, Behaviour changes).

Anything else that turns out to need a behaviour change is out of scope: stop and report.

## Facts (measured 2026-11-25 on `main` at `584977f`; the branch `feat/DPB-NNN-Codebase-Cleanup` exists with no commits; re-check before each phase)

| What | Measured | Why it matters |
|---|---|---|
| `AGENTS.md` | 133,342 bytes, **215 lines**, 12 real headings (17 lines start with `#`, 5 of them are bash comments inside code fences), 24 lines over 1,500 characters, longest line 15,551 (line 9) | Loaded into every agent session. Topics are scattered: 9 feature paragraphs (lines 199 to 215) sit after the `## Git` section. The Phase 1 split script asserts the 215 lines, so nothing may edit `AGENTS.md` before it (Phase 2 edits it after, by text). |
| Stale facts in `AGENTS.md` | `(v0.31.0, ...)` and "Plugin version 0.32.0 (the constant had drifted at 0.30.0)" while the plugin header and `CPTDP_VERSION` are **0.35.0**; loader described as globbing post-types, settings, seeders (it also globs `includes/documents/` first); "`.gitignore` is allowlist-style" (it is not: only `wp-content/plugins/*` and `wp-content/themes/*` are allowlisted, a new root file or `docs/` folder is tracked by default); `blocks/document-modal/vendor/ooxml/` and `blocks/document-modal/pages-sheet.js` (both live in `blocks/shared/`); node tests listed under `blocks/document-modal/tests/{gestures,touch-zoom,layout,mode,pager,sheet-controller,bottom-sheet}` (they live in `blocks/shared/tests/`); `cptdp_document_file` "non-image/video/audio" (code allows images, rejects only video and audio); JS totals (below) | Wrong docs make the next agent wrong. |
| `wp-content/themes/twentytwentyfive/` | **234 of 817** tracked files (8.7 MB), version 1.5, inactive (`ddev wp theme list`; options `template` and `stylesheet` are `devportfolio`). Tracked only because of `.gitignore` line 21 `!/wp-content/themes/twentytwentyfive/`. `twentytwentythree` and `twentytwentyfour` sit on disk untracked already. | Phase 2 untracks it (decision; was open question 3). |
| Who depends on `twentytwentyfive` | **Nothing.** `devportfolio/style.css` has no `Template:` header (standalone, not a child theme). The WP test library is configured with `WP_DEFAULT_THEME` `default` (`bin/install-wp-tests.sh` line 69; that theme lives in the test library's `data/themes`), the theme tests `require_once` `functions.php` directly, no test, DDEV file, WP-CLI command or script names it. The only mentions outside plans: `.gitignore:21`, `AGENTS.md` lines 7, 107, 113, 183, `README.md` lines 8, 140, 171, and the attribution lines "Originally forked from Twenty Twenty-Five" in `devportfolio/style.css` and `readme.txt` (kept: they are the GPL credit). `git rm --cached` leaves the files on disk, so this site keeps it installed; WordPress only falls back to it when the active theme is broken, and a fresh WordPress core download ships it anyway. | Safe to untrack. |
| Theme `functions.php` | 2,683 lines, 93 functions in `if ( ! function_exists() ) :` blocks (two blocks hold two functions each: `hero_title`+`hero_name_parts`, `memo`+`memo_flush`), one top-level call (`devportfolio_memo_add_hooks();`, line 440), no `require` | One file holds every page. Every function is referenced at least once (no dead theme PHP). |
| Path-sensitive code in `functions.php` | `__DIR__` only in `devportfolio_register_blocks()` (lines 55 to 72) and the four enqueue/print functions (lines 209 to 305) | Those must stay in `functions.php`: moving them to `inc/` changes what `__DIR__` means. Phases 8 and 9 edit two of them in place. |
| Theme tests that read `functions.php` text | `test-video-modal.php:304` (`register_block_type( __DIR__ . '/blocks/video-modal' );`), `test-home-motion.php:142,161` (two `add_action` lines), `test-contact-block.php:262` (`function_exists( 'cptdp_contact_config' )`), `test-document-spreadsheets.php:235-236` (`'data-hint-sheet'`, `Download the file to open it in Excel`), `test-document-presentations.php:380` (negative check: no `officeapps`/`googleapis`) | Phase 6 must update the last three. 34 other test files only `require_once` it. |
| Media CPT duplication (`document.php` 715, `collateral.php` 655, `video.php` 725 lines) | Byte-identical after replacing the type name: `sanitize_url`, `sanitize_software_tools`, `sanitize_statistics`, `render_software_tools_box`, `render_statistics_box`, `render_highlight_box`, `classic_editor`, `default_order`, `sortable_columns`. `render_statistics_row` is identical except the video placeholders (`e.g. Views` / `e.g. 12K` vs `e.g. Downloads` / `e.g. 1.2K`). `sanitize_file` is identical for document and collateral, different for video. Everything else differs in substance. | 21 functions collapse to 6 shared ones (Phase 3). |
| Hidden callers | `tests/class-cptdp-media-cpt-test-case.php:71` calls `call_user_func( 'cptdp_' . $this->post_type() . '_' . $suffix )` for `sanitize_url`, `sanitize_statistics`, `render_statistics_box`, `render_highlight_box`, `render_details_box`; a plain grep for the old names misses these. Direct callers: `tests/post-types/test-{document,collateral,video}.php`. | Phase 3 must edit the base class `call()`. |
| Admin JS | `admin-document.js` 86, `admin-collateral.js` 66, `admin-video.js` 215 lines. Only the **Post Statistics repeater** (about 25 lines) is the same in all three. | Share only the repeater. |
| Dead CSS | One dead rule: `.videos-modal__blank` (`blocks/video-modal/style.css` lines 265 to 275). | Phase 7. |
| Dead code | Plugin: `cptdp_pptx_warnings()` (`document-presentations.php:65`) is called only by its test. Theme JS: `isNativeGrid` (`blocks/shared/mode.js:113`) and `withoutSheetState` (`blocks/shared/deeplink.js:91`) are imported only by tests. Theme PHP: none. | Phases 4 and 7. |
| F3: stray temp folders | `CPTDP_Pptx_Converter::stray_folders()` (`includes/documents/class-pptx-converter.php:693`, `cptdp-pptx-*` folders in `get_temp_dir()` older than a day) is **never called**. `cleanup()` (line 655) is the safe delete (only `cptdp-pptx-*`, never a link, then `remove_tree()`). A crashed conversion (fatal, killed worker) leaves its folder, which holds a copy of the uploaded file, for ever. The plugin has no recurring cron event; deactivation is `register_deactivation_hook( __FILE__, 'flush_rewrite_rules' )` (`custom-post-types-dp.php:48`), asserted by `tests/test-loader.php:105`. | Phase 4 adds the missing cleanup (a behaviour addition). |
| F2: shared viewer CSS | `blocks/document-modal/style.css`: 56,650 bytes, md5 `54e6c9e1f60e06cda2660d9c7571a636`, 263 items: 257 rules (top level or inside 19 `@media` blocks) and 6 `@keyframes`; no nesting, no `@supports`/`@import`. **198** rules name `collaterals-` or `videos-` selectors. It is the block style on `/` and `/documents/`, and handle `devportfolio-viewer` (enqueued by `devportfolio_enqueue_page_styles()`) on `/videos/` and `/collaterals/`, where it is printed **after** `global-styles` and `page.css` (link 17 of 17), while each modal's own style is printed at position 9 (on `/collaterals/` `collateral-modal/style.css` is even inlined). **40 references in 12 theme PHP test files** read it, all as the exact expression `$this->read( 'blocks/document-modal/style.css' )`, plus `blocks/document-modal/tests/routing.test.mjs:31`. | Phase 8. Keeping each page's link order is what keeps the cascade. |
| F1: what `/` loads | 8 theme module scripts (`selected-work`, `document-modal`, `collateral-modal`, `video-modal`, `testimonials`, `software-tools`, `contact` view.js, `assets/js/nav-spy.js`) whose static import graph is **62 files, 301,700 bytes** (unminified; the three modal graphs alone are 50 files, 257,920 bytes), one `modulepreload` (core interactivity), and 8 render-blocking `<link rel='stylesheet'>`, of which the theme block ones are `selected-work` 14,174, `document-modal` 56,650, `collateral-modal` 13,089, `video-modal` 42,684 and `contact` 16,450 bytes (**143,047 bytes**). The three dialogs are closed `<dialog>` elements (`display: none`): the modal CSS styles nothing visible before a dialog opens except `[data-document-id] { cursor: pointer; }`. Small block styles are inlined (WordPress's 20,000-byte inline budget). The vendored libraries (`blocks/shared/vendor`, plugin `assets/vendor`) are already lazy. | Phase 9. |
| F1: history listener order | `AGENTS.md`: the modal (`view.js`) and sheet (`shared/sheet-back.js`) `popstate` listeners must be added while the scripts run, ahead of WordPress's Interactivity Router, which reloads the page for a URL it has not cached. Five `window.addEventListener( 'popstate' ... true )` calls exist: `document-modal/view.js:257`, `collateral-modal/view.js:469`, `video-modal/view.js:492`, `shared/sheet-back.js:23` (used by the modals' sheet controller and by `contact/view.js:146`) and `document-categories/view.js:322` (a classic script, not touched). On all four pages measured today the import map lists only `@wordpress/interactivity` (the router is not loaded), but the plan keeps the guarantee anyway. Tests pin it: `test-collateral-modal-mobile.php:239,242` (literal `window.addEventListener(\n\t\t'popstate',`), `test-collateral-modal.php:570`, `test-document-modal.php:413`, `test-document-modal-mobile-pages.php:326`, `test-video-modal-mobile.php:310-311`, `blocks/shared/tests/sheet-controller.test.mjs:63-68`. | A lazily loaded view would add its listeners too late, so Phase 9 adds one early listener that every view and sheet registers through. |
| Node suites (TAP, run in DDEV by `gates.sh`) | collateral-grid 18, collateral-modal 24, contact 20, document-modal 17, selected-work 14, shared 179, testimonials 5, video-grid 17, video-modal 49; all pass. `blocks/software-tools/tests/` is an empty, untracked folder (skipped). AGENTS.md says contact 23, video grid 15, video modal 44 and omits testimonials. | Baseline for the gate. |
| F4: linters | None configured (no phpcs, eslint, prettier, `.editorconfig`; the code already carries `phpcs:ignore` comments). A probe in the container's `/tmp` (removed afterwards, no tracked file touched) with PHPCS 3.13.6, WPCS 3.4.1 and the ruleset of Phase 5 over plugin and theme PHP (tests, `bin/` and `vendor/` excluded, 83 files) found **133 errors and 173 warnings in 48 files** (plugin 123 errors and 73 warnings in 37 files, theme 10 and 100 in 11), 178 of them fixable by phpcbf; top sources: `WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned` 93, `Generic.Formatting.MultipleStatementAlignment.NotSameWarning` 55, `WordPress.Files.FileName.InvalidClassFileName` 16. The repo has no `package.json`. | Phase 5 (report only). |
| PHP and Node on the host | No `php` on the host; Node 24 on the host and in DDEV. | Lint PHP with `ddev exec`; run node through `gates.sh` (DDEV). |
| Tracked files | 817 | 594 after Phase 2 (817 + `.editorconfig` + 10 `docs/agents/` files − 234). |

## Non-goals

- No renaming of stored meta keys, option names (incl. the `selectable_text` fallback), REST routes and error codes (incl. `cptdp_pptx_server_only`), cron hooks that exist today, block names, CSS class names, existing script/style handles or meta box IDs.
- No generalising of the `document-*` blocks (AGENTS.md says that waits for a second page ticket). Phase 8 moves rules between two files; it renames nothing.
- No collapsing of `*_classic_editor` (8 CPTs) or `*_default_order` (6 CPTs) into one list: considered and rejected, each CPT file stays self-contained.
- No new build step, bundler, framework or `package.json`; no ESLint (decided in Phase 5). No mass reformatting: PHPCS only reports, phpcbf is **not** run.
- No change to what Home shows or how a dialog behaves once open; Phase 9 only changes when the viewer code and CSS arrive.

## Rules for every phase

1. **One branch, one PR.** Branch `feat/DPB-NNN-Codebase-Cleanup` (exists, no commits; `git switch feat/DPB-NNN-Codebase-Cleanup`). The 29 commits below are grouped by major phase; keep that order. One concern per commit. A move and the test path fixes it forces go in **one** commit.
2. **Every commit leaves all three gates green** with the counts in the phase table below. Not only the docs commits: a commit that changes no code still passes, so a checkpoint is the only place the gates must actually be run for it. Amend before pushing when a commit turns out red; never leave a red commit in the history.
3. **Gates are one script, run sequentially.** Save the script below as `<scratchpad>/gates.sh` (your session scratchpad, never the repo) and run it from the repo root on the host: `bash <scratchpad>/gates.sh <label>`. It runs the plugin PHPUnit suite, then the theme suite, then every node suite, **one after the other**. Never run two gates at once (no `&`, no second terminal, no parallel agents): both PHPUnit suites use the same test database `wordpress_test` and the WP test library drops its tables, so parallel runs corrupt each other. Run it after each commit that changes code (label `commit-NN`) and **always** at each phase checkpoint (label `phase-N`), and paste the summary lines into the PR description table (Conventional commits section).
4. **Moves are done by the scripts in this plan**, which refuse to run when a line number, byte count or checksum no longer matches. If a script stops, **stop and report**; do not adjust numbers by hand. Scripts and screenshots live in the scratchpad, never in the repo.
5. If a gate fails in a way this plan does not predict (a test that reads a file the plan did not list, a count off by one), **stop and report** with the failing test names; do not "fix" the test to make it pass.

`<scratchpad>/gates.sh` (exact content; the node part is the one-liner `AGENTS.md` documents, run inside DDEV):

```bash
#!/usr/bin/env bash
# DPB-NNN gates: the plugin PHPUnit suite, the theme PHPUnit suite and every node suite, ONE AFTER THE OTHER.
# Never run two of these at the same time (no `&`, no second terminal): both PHPUnit suites use the same test
# database (wordpress_test) and the WP test library drops its tables, so parallel runs corrupt each other.
#
# Usage, from the repo root on the host:   bash <scratchpad>/gates.sh <label>
#   <label> names the log, e.g. baseline, phase-3, commit-07.
# Prints one summary line per suite; the full output is in <scratchpad>/gates-<label>.log.
# Exit status 0 only when every suite passed. GATES="node" (or "plugin theme") runs a subset, for a quick look only:
# a checkpoint always runs all three.
set -u

label="${1:-run}"
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
log="$here/gates-$label.log"
gates="${GATES:-plugin theme node}"
status=0
: > "$log"

phpunit_summary() {
	grep -E '^(OK \(|OK, but|Tests: |FAILURES!|ERRORS!|No tests executed)' | tr '\n' ' '
}

run_phpunit() {
	local name="$1" dir="$2" runner="$3" out rc
	echo "== $name" >> "$log"
	out="$(ddev exec --dir="$dir" "$runner" 2>&1)"
	rc=$?
	printf '%s\n' "$out" >> "$log"
	echo "$name rc=$rc $(printf '%s\n' "$out" | phpunit_summary)"
	[ "$rc" -eq 0 ] || status=1
}

for gate in $gates; do
	case "$gate" in
		plugin)
			run_phpunit plugin /var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
			;;
		theme)
			run_phpunit theme /var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
			;;
		node)
			echo "== node" >> "$log"
			out="$(ddev exec --dir=/var/www/html/wp-content/themes/devportfolio bash -s 2>&1 <<'NODE'
rc=0
for d in blocks/*/tests; do
	set -- "$d"/*.test.mjs
	[ -e "$1" ] || continue
	block="${d%/tests}"
	res="$(cd "$block" && node --test --test-reporter=tap tests/*.test.mjs 2>&1)" || rc=1
	printf '%s\n' "$res" > "/tmp/gates-node-${block#blocks/}.tap"
	echo "node $block $(printf '%s\n' "$res" | grep -E '^# (tests|pass|fail)' | tr '\n' ' ')"
done
exit $rc
NODE
)"
			rc=$?
			printf '%s\n' "$out" >> "$log"
			printf '%s\n' "$out" | grep '^node '
			[ "$rc" -eq 0 ] || status=1
			;;
		*)
			echo "unknown gate $gate" >&2
			status=1
			;;
	esac
done

echo "gates $label: $( [ "$status" -eq 0 ] && echo PASS || echo FAIL ) (log: $log)"
exit "$status"
```

The node part was dry-run on `584977f` (`GATES=node`): nine `node blocks/<name> # tests N # pass N # fail 0` lines and `PASS`. A PHPUnit line reads `plugin rc=0 OK, but incomplete, skipped, or risky tests! Tests: 1024, Assertions: ..., Skipped: 12.` or `theme rc=0 OK (787 tests, ... assertions)`. The PHPUnit part was not run while writing this plan.

## Phases and expected counts

`P`, `S` and `T` are the plugin total, plugin skipped and theme total that Phase 0 records (expected 1024, 12, 787). Node suites not listed stay at their Facts value.

| Phase | What | Commits | Plugin | Theme | Node changes |
|---|---|---|---|---|---|
| 0 | baseline, `.editorconfig` | 1 | P (S skipped) | T | none |
| 1 | split `AGENTS.md` into `docs/agents/` | 2-4 | P | T | none |
| 2 | untrack `twentytwentyfive` | 5 | P | T | none |
| 3 | plugin: share the media meta helpers | 6-7 | P | T | none |
| 4 | plugin: dead code, F3 temp-folder sweep, version 0.36.0 (+ optional value classes) | 8-13 | P-1 after 8, **P+4** from 10 | T | none |
| 5 | F4: PHPCS report | 14-15 | P+4 | T | none |
| 6 | theme: split `functions.php` | 16-17 | P+4 | T | none |
| 7 | theme: dead CSS and JS | 18-19 | P+4 | T | shared **178** |
| 8 | F2: `blocks/shared/viewer.css` | 20-22 | P+4 | **T+3** from 21 | none |
| 9 | F1: viewers on first use, non-blocking viewer CSS | 23-28 | P+4 | **T+9** from 27 | shared **192** from 24 |
| - | the plan itself | 29 | P+4 | T+9 | - |

Final, with the expected baseline: plugin 1028 (12 skipped), theme 796, shared 192, every other node suite unchanged.

## Phase 0: baseline and editorconfig

1. `git switch feat/DPB-NNN-Codebase-Cleanup` and confirm `git log main..HEAD --oneline` prints nothing.
2. `ddev snapshot --name pre-cleanup`.
3. Save `<scratchpad>/gates.sh` (Rules) and run `bash <scratchpad>/gates.sh baseline`. Write `P`, `S`, `T` and the nine node counts into the `baseline` row of the PR description's checkpoint table. Expected: plugin 1024 (12 skipped), theme 787, node as in Facts. If they differ, record what you see; those are the numbers every later row is computed from.
4. Create `/.editorconfig` (tracked by default, no `.gitignore` change):
   ```ini
   root = true

   [*]
   charset = utf-8
   end_of_line = lf
   insert_final_newline = true
   trim_trailing_whitespace = true
   indent_style = tab
   indent_size = 4

   [*.{yml,yaml}]
   indent_style = space
   indent_size = 2

   [*.md]
   trim_trailing_whitespace = false

   [**/vendor/**]
   charset = unset
   end_of_line = unset
   insert_final_newline = unset
   trim_trailing_whitespace = unset
   indent_style = unset
   indent_size = unset
   ```
   (The leading three spaces are Markdown list indentation, not part of the file.)
5. Do **not** touch `AGENTS.md` here: the Phase 1 script needs the untouched 215-line file. The convention bullet for `.editorconfig` is added in commit 4:
   ```
   - `.editorconfig` at the repo root sets tabs, LF and a final newline (spaces for YAML; vendored files are left alone). Apply it to files you touch; never reformat untouched files.
   ```

Commit 1: `chore(agents): add an editorconfig` (stage `.editorconfig` only).

**Checkpoint 0:** `bash <scratchpad>/gates.sh phase-0`: same counts as the baseline.

## Phase 1: split AGENTS.md

### Commit 2: move detail into `docs/agents/` (verbatim)

Save as `split-agents.py` in the scratchpad and run `python3 <scratchpad>/split-agents.py .` from the repo root. It writes `AGENTS.md` and ten files in `docs/agents/`, moves every line unchanged (lines 7 and 9 are cut at a sentence boundary), adds a "Where to look" table after the Overview, and asserts that every original line appears in the output exactly once. Dry run on `584977f`: `AGENTS.md` 10,499 bytes, 111 lines.

```python
#!/usr/bin/env python3
"""Move AGENTS.md detail into docs/agents/*.md verbatim (DPB-NNN Phase 1, commit 2).

Usage: python3 split-agents.py <repo_root> [<out_root>]
Line numbers are for AGENTS.md at commit 584977f (215 lines). Refuses to run if the file changed.
"""
import sys, os

root = sys.argv[1]
out = sys.argv[2] if len(sys.argv) > 2 else root
L = open(os.path.join(root, 'AGENTS.md'), encoding='utf-8').read().split('\n')
if L[-1] == '':
    L = L[:-1]
assert len(L) == 215, f'AGENTS.md has {len(L)} lines, expected 215: stop and report'

EXPECT = {
    7: 'WordPress portfolio site', 9: '**Preview clip (v0.30.0', 11: '**Spreadsheets (v0.21.0', 39: 'Data lives in',
    41: 'Adding a seeder:', 63: '#### Plugin suite', 103: 'No linter or CI', 105: '## What is tracked', 118: '## Theme architecture',
    125: '- `blocks/document-categories/`', 128: '- `blocks/collateral-grid/`', 130: '- `blocks/hero/`', 132: '- `blocks/software-tags/`',
    136: '- `blocks/video-grid/`', 140: '- `blocks/shared/`', 142: '- `blocks/document-modal/`', 150: '**Mobile viewer (DPD-021',
    162: '**Home page (DPH-002', 168: '**Job Roles and the Home hero', 170: '**Interests and the Contact', 172: 'Testimonials and Contact',
    174: '## Conventions', 186: '## Git', 199: '**Word documents (v0.22.0', 201: '**Page names and thumbnails', 203: '**Mobile Pages sheet',
    205: '**Mobile Details sheet', 207: '**Singular document category', 209: '**Collateral page images', 211: '**Shared page image settings',
    213: '**Featured image from page 1', 215: '**Collateral empty, loading',
}
for n, prefix in EXPECT.items():
    assert L[n - 1].startswith(prefix), f'line {n} does not start with {prefix!r}: stop and report'

SPLIT7 = 'shadowed by the CPT archive rewrite. '
SPLIT9 = '**Document page images (v0.19.0, DPB-025):**'
assert L[6].count(SPLIT7) == 1 and L[8].count(SPLIT9) == 1
l7_keep, l7_move = L[6].split(SPLIT7)
l7_keep += SPLIT7.rstrip()
l9_cpt, l9_pipe = L[8].split(SPLIT9)
l9_cpt = l9_cpt.rstrip()
l9_pipe = SPLIT9 + l9_pipe

def lines(*ranges):
    res = []
    for a, b in ranges:
        res.extend(L[a - 1:b])
    return res

DOCS = {
    'plugin-cpts.md': ('Plugin content model', 'The `custom-post-types-dp` plugin: loader, post types, taxonomies, meta, settings, Job Roles and Interests.',
        [l7_move, '', l9_cpt, ''] + lines((168, 170), (207, 207))),
    'documents-pipeline.md': ('Document and collateral page pipeline', 'Page images, Office and Word conversion, page names, thumbnails and the featured image, for documents and collaterals.',
        [l9_pipe, ''] + lines((11, 12), (199, 202), (209, 213))),
    'seeders.md': ('Seeders', 'Seeder data, rules and how to add one. The commands stay in AGENTS.md.', lines((39, 41))),
    'tests.md': ('Test suites in detail', 'Per-suite layout, gotchas and what each theme test file covers. The commands stay in AGENTS.md.', lines((63, 102))),
    'theme-core.md': ('Theme core', 'theme.json, templates, parts, functions.php and the reusable software tags block.', lines((118, 124), (132, 134))),
    'documents-page.md': ('Documents page', 'Categories, book, shelf and the document modal, incl. its mobile sheets and deeplink.', lines((125, 127), (142, 148), (203, 205))),
    'collaterals-page.md': ('Brand Collaterals page', 'Collateral grid, modal and the empty, loading and error states.', lines((128, 129), (215, 215))),
    'videos-page.md': ('Videos page', 'Video grid and video modal, incl. motion.', lines((136, 139))),
    'viewer-shared.md': ('Shared viewer', '`blocks/shared/`, routing and the mobile viewer used by all three modals.', lines((140, 141), (149, 160))),
    'home.md': ('Home page', 'Hero, contact, stats, services, Selected work, opening animation and the Home anchors.', lines((130, 131), (161, 166), (172, 172))),
}
KEEP = [L[0], L[1], L[2], L[3], L[4], L[5], l7_keep, ''] + lines((13, 38), (43, 62), (103, 117), (174, 198))
WHERE = [
    '## Where to look', '',
    'Detail lives in `docs/agents/`. Read the file for the area you are about to touch; update only that file when you ship.', '',
    '| Topic | File | Read it when you touch |', '|---|---|---|',
    '| Plugin content model | `docs/agents/plugin-cpts.md` | `includes/post-types/`, `includes/settings/`, meta, taxonomies |',
    '| Page image pipeline | `docs/agents/documents-pipeline.md` | `includes/documents/`, conversion, page images, thumbnails |',
    '| Seeders | `docs/agents/seeders.md` | `includes/seeders/`, `data/seeders/` |',
    '| Tests in detail | `docs/agents/tests.md` | any `tests/` folder |',
    '| Theme core | `docs/agents/theme-core.md` | `theme.json`, `templates/`, `parts/`, `functions.php`, `inc/` |',
    '| Documents page | `docs/agents/documents-page.md` | `blocks/document-*` |',
    '| Brand Collaterals page | `docs/agents/collaterals-page.md` | `blocks/collateral-*` |',
    '| Videos page | `docs/agents/videos-page.md` | `blocks/video-*` |',
    '| Shared viewer | `docs/agents/viewer-shared.md` | `blocks/shared/` |',
    '| Home page | `docs/agents/home.md` | `templates/front-page.html`, `blocks/hero`, `contact`, `stats-strip`, `service-list`, `selected-work`, `testimonials`, `software-tools` |',
    '',
]
new_agents = KEEP[:8] + WHERE + KEEP[8:]

os.makedirs(os.path.join(out, 'docs', 'agents'), exist_ok=True)
for name, (title, scope, body) in DOCS.items():
    while body and body[-1] == '':
        body = body[:-1]
    text = ['# ' + title, '', scope, ''] + body
    open(os.path.join(out, 'docs', 'agents', name), 'w', encoding='utf-8').write('\n'.join(text) + '\n')
open(os.path.join(out, 'AGENTS.md'), 'w', encoding='utf-8').write('\n'.join(new_agents).rstrip('\n') + '\n')

# Verify: every non-empty original fragment appears exactly once in the output.
frags = [l7_keep, l7_move, l9_cpt, l9_pipe] + [l for i, l in enumerate(L, 1) if i not in (7, 9) and l.strip()]
outputs = [open(os.path.join(out, 'AGENTS.md'), encoding='utf-8').read()] + [open(os.path.join(out, 'docs', 'agents', n), encoding='utf-8').read() for n in DOCS]
blob_lines = [x for o in outputs for x in o.split('\n')]
missing = [f[:60] for f in frags if f.strip() and f not in blob_lines]
assert not missing, f'missing after move: {missing}'
dupes = [f[:60] for f in set(frags) if f.strip() and not f.startswith(('```', '- ', '  - ', '#')) and blob_lines.count(f) > frags.count(f)]
assert not dupes, f'duplicated after move: {dupes}'
print('ok', len(new_agents), 'lines in AGENTS.md')
```

If the script stops on an assertion, `AGENTS.md` changed after `584977f` (for example another ticket merged): stop and report; do not renumber by hand.

Gate: script prints `ok`; `wc -c AGENTS.md` under 12,000; `git diff --stat` shows only `AGENTS.md` and the ten new files.

Commit 2: `docs(agents): move detail into docs/agents` (stage `AGENTS.md docs/agents/`).

### Commit 3: break the longest lines at their bold labels

```bash
perl -pi -e 's/ (\*\*[A-Z][^*]{1,80}:\*\*)/\n\n$1/g' docs/agents/*.md
```
This only inserts a blank line before every mid-line `**Label:**` (no word changes). Dry run: longest line goes from 15,551 to 7,405 characters. Gate: `git diff --word-diff=porcelain docs/agents | grep -E '^[-+][^-+]' | head` prints nothing (only whitespace changed).

Commit 3: `docs(agents): split long paragraphs at their labels`.

### Commit 4: correct stale facts (exact replacements)

Use Edit (find, replace) in the named file; each find string occurs once.

| File | Find | Replace |
|---|---|---|
| `docs/agents/plugin-cpts.md` | `(v0.31.0, \`wp-content/plugins/custom-post-types-dp/\`;` | `(version in the plugin header of \`custom-post-types-dp.php\`, \`wp-content/plugins/custom-post-types-dp/\`;` |
| `docs/agents/plugin-cpts.md` | `globs and requires every file in \`includes/post-types/\`, \`includes/settings/\` and \`includes/seeders/\`` | `globs and requires every file in \`includes/documents/\`, \`includes/post-types/\`, \`includes/settings/\` and \`includes/seeders/\`, in that order and by file name within each folder,` |
| `docs/agents/plugin-cpts.md` | ` Plugin version 0.32.0 (the constant had drifted at 0.30.0).` | (empty) |
| `docs/agents/plugin-cpts.md` | `\`cptdp_document_file\` (attachment ID, non-image/video/audio)` | `\`cptdp_document_file\` (attachment ID, anything but video or audio; images are allowed)` |
| `docs/agents/plugin-cpts.md` | `(\`includes/settings/videos.php\`, plugin 0.31.0)` | `(\`includes/settings/videos.php\`)` |
| `docs/agents/documents-pipeline.md` | `\`blocks/document-modal/vendor/ooxml/\`` | `\`blocks/shared/vendor/ooxml/\`` |
| `docs/agents/documents-page.md` | `(\`blocks/document-modal/pages-sheet.js\`` | `(\`blocks/shared/pages-sheet.js\`` |
| `docs/agents/documents-page.md` | `blocks/document-modal/tests/{details-sheet,sheet-controller,bottom-sheet}.test.mjs` | `blocks/document-modal/tests/details-sheet.test.mjs\` and \`blocks/shared/tests/{sheet-controller,bottom-sheet}.test.mjs` |
| `docs/agents/viewer-shared.md` | `blocks/document-modal/tests/{gestures,touch-zoom,routing,layout,mode,pager}.test.mjs` | `blocks/shared/tests/{gestures,touch-zoom,layout,mode,pager}.test.mjs\` and \`blocks/document-modal/tests/routing.test.mjs` |
| `AGENTS.md` | `` `.gitignore` is allowlist-style. Tracked: `` | `` `.gitignore` ignores WordPress core, generated content, dependencies and every plugin and theme except the allowlisted ones; anything else (a new root file such as `.editorconfig`, the `docs/` folder) is tracked by default. Tracked: `` |
| `AGENTS.md` | `Expected totals today: plugin 1024 tests (12 skipped), theme 787 tests, contact JS 23 tests, Documents modal JS 17 tests, video modal JS 44 tests, shared JS 179 tests, collateral modal JS 24 tests, collateral grid JS 18 tests, video grid JS 15 tests and Selected work JS 14 tests` | `Expected totals today: plugin <P> tests (<S> skipped), theme <T> tests, contact JS 20 tests, Documents modal JS 17 tests, video modal JS 49 tests, shared JS 179 tests, collateral modal JS 24 tests, collateral grid JS 18 tests, video grid JS 17 tests, testimonials JS 5 tests and Selected work JS 14 tests` (fill `<P>`, `<S>`, `<T>` from Phase 0) |
| `AGENTS.md` | the line `- Never commit secrets, DB dumps, or \`wp-config.php\`.` | that line, then the editorconfig bullet from Phase 0 step 5, then `- Ship a feature by updating the matching \`docs/agents/*.md\` file (see Where to look). Edit AGENTS.md itself only when a convention, command or the table changes.` |

Also add these two lines to the bash block of `AGENTS.md` `### Tests`, directly after the line `# filters: --filter Devportfolio_Test_Navigation | --group theme | --testdox` (the node part of `gates.sh`, for people):
```bash

# every node suite (one line per block)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio 'for d in blocks/*/tests; do set -- $d/*.test.mjs; [ -e "$1" ] || continue; printf "%s " "${d%/tests}"; (cd "${d%/tests}" && node --test --test-reporter=tap tests/*.test.mjs 2>&1 | grep -E "^# (tests|fail)" | tr "\n" " "); echo; done'
```

Gate: `grep -rn "0.31.0\|0.32.0\|allowlist-style\|document-modal/vendor\|document-modal/pages-sheet" AGENTS.md docs/agents` prints nothing.

Commit 4: `docs(agents): correct stale versions, paths and counts`.

**Checkpoint 1:** `bash <scratchpad>/gates.sh phase-1`: counts unchanged (docs only).

## Phase 2: stop tracking `twentytwentyfive`

Decision (was open question 3): the theme is unused, so it leaves the repo. It runs **after** Phase 1 on purpose: the split script asserts the untouched 215-line `AGENTS.md`, and every edit below is a find/replace on text that commit 2 kept in `AGENTS.md` (lines 7, 107, 113 and 183 of today's file are all in its kept ranges). Nothing depends on the theme (Facts), so no code or test changes.

### Commit 5

1. Untrack, keeping the files on disk:
   ```bash
   git rm -r --cached --quiet wp-content/themes/twentytwentyfive
   test -f wp-content/themes/twentytwentyfive/style.css && echo "still on disk"
   ```
   The second line must print `still on disk`.
2. `.gitignore`: delete the line `!/wp-content/themes/twentytwentyfive/` (today line 21, the line after `/wp-content/themes/*`). Then `git check-ignore -q wp-content/themes/twentytwentyfive/style.css && echo ignored` prints `ignored`, and `git check-ignore -q wp-content/themes/devportfolio/style.css || echo tracked` prints `tracked`.
3. Exact replacements (Edit; each find string occurs once):

   | File | Find | Replace |
   |---|---|---|
   | `AGENTS.md` | `(originally forked from Twenty Twenty-Five 1.5, which stays installed but unused)` | `(originally forked from Twenty Twenty-Five 1.5, which is not tracked in this repo)` |
   | `AGENTS.md` | `` `wp-content/index.php`, `wp-content/themes/twentytwentyfive/`, `wp-content/themes/devportfolio/` `` | `` `wp-content/index.php`, `wp-content/themes/devportfolio/` `` |
   | `AGENTS.md` | ``- All themes except `twentytwentyfive` and `devportfolio` `` | ``- All themes except `devportfolio` (WordPress's bundled themes, such as `twentytwentyfive`, may sit on disk untracked)`` |
   | `AGENTS.md` | `` `twentytwentyfive` is reference only; do not edit it. `` | `` WordPress's bundled themes (`twentytwentythree` to `twentytwentyfive`) are not tracked; do not edit them. `` |
   | `README.md` | `(standalone, tracked in this repo; Twenty Twenty-Five stays installed as a fallback)` | `(standalone, tracked in this repo; WordPress's bundled default themes are not tracked)` |
   | `README.md` | the whole line `` | `wp-content/themes/twentytwentyfive/` | Tracked, unused reference theme | `` | (delete the line) |
   | `README.md` | `Twenty Twenty-Five is GPL-2.0-or-later.` | `` The `devportfolio` theme, forked from Twenty Twenty-Five, is GPL-2.0-or-later. `` |

   Leave the "Originally forked from Twenty Twenty-Five" credit in `wp-content/themes/devportfolio/style.css` and `readme.txt` as it is.
4. Checks: `git grep -n -i "twentytwentyfive\|twenty twenty-five" -- . ':!.claude'` prints only the new `AGENTS.md` lines (3), the `README.md` credit line and the two devportfolio credit lines; `git ls-files | wc -l` prints `594` (817 + `.editorconfig` + the 10 `docs/agents/` files − 234); `ddev wp theme list --status=active --field=name` prints `devportfolio`; `curl -sk -o /dev/null -w '%{http_code}\n' https://devportfolio.ddev.site/` prints `200`.

Commit 5: `chore(agents): stop tracking the unused twentytwentyfive theme` (stage `.gitignore AGENTS.md README.md`; the `git rm --cached` deletions are already staged; `git status --short | grep -c "^D  wp-content/themes/twentytwentyfive/"` prints `234`).

**Checkpoint 2:** `bash <scratchpad>/gates.sh phase-2`: counts unchanged.

## Phase 3: plugin, share the media meta helpers

Re-check the Facts row "Media CPT duplication" first: for each of the six names below, `diff <(sed -n '/^function cptdp_document_NAME(/,/^}/p' document.php | sed 's/document/T/g') <(sed -n '/^function cptdp_video_NAME(/,/^}/p' video.php | sed 's/video/T/g')` (from `includes/post-types/`) must print nothing (for `render_statistics_row`, only the two placeholder lines). Stop and report otherwise.

### Commit 6: `includes/post-types/media-meta.php`

1. Create `wp-content/plugins/custom-post-types-dp/includes/post-types/media-meta.php` with exactly this content. The loader requires it by name (after `job-role.php`, before `menu-order.php`); it only defines functions, so the order does not matter.
   ```php
   <?php
   /**
    * Meta helpers shared by the Document, Collateral and Video post types: the URL, Software Tools and
    * Post Statistics sanitisers and the Software Tools, Post Statistics and Highlight meta boxes.
    *
    * @package CustomPostTypesDP
    */

   defined( 'ABSPATH' ) || exit;

   /**
    * Sanitize a URL: http(s) only, empty string when invalid.
    *
    * @param mixed $url Raw value.
    */
   function cptdp_media_sanitize_url( $url ): string {
   	return esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
   }

   /**
    * Sanitize Software Tools: unique IDs of existing `software_tool` posts.
    *
    * @param mixed $ids Raw value.
    * @return int[]
    */
   function cptdp_media_sanitize_software_tools( $ids ): array {
   	if ( ! is_array( $ids ) ) {
   		return array();
   	}

   	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

   	return array_values(
   		array_filter(
   			$ids,
   			static function ( int $id ): bool {
   				return 'software_tool' === get_post_type( $id );
   			}
   		)
   	);
   }

   /**
    * Sanitize Post Statistics: rows of `label` and `value` text.
    *
    * Drops non-array rows and rows with both fields empty, then reindexes.
    *
    * @param mixed $rows Raw value.
    * @return array<int, array{label: string, value: string}>
    */
   function cptdp_media_sanitize_statistics( $rows ): array {
   	if ( ! is_array( $rows ) ) {
   		return array();
   	}

   	$clean = array();

   	foreach ( $rows as $row ) {
   		if ( ! is_array( $row ) ) {
   			continue;
   		}

   		$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
   		$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';

   		if ( '' === $label && '' === $value ) {
   			continue;
   		}

   		$clean[] = array(
   			'label' => $label,
   			'value' => $value,
   		);
   	}

   	return $clean;
   }

   /**
    * Render the Software Tools meta box.
    *
    * @param WP_Post $post Post being edited.
    */
   function cptdp_media_render_software_tools_box( WP_Post $post ): void {
   	$selected = array_map( 'absint', (array) get_post_meta( $post->ID, 'cptdp_software_tools', true ) );
   	$tools    = get_posts(
   		array(
   			'post_type'      => 'software_tool',
   			'post_status'    => 'publish',
   			'posts_per_page' => -1,
   			'orderby'        => 'menu_order title',
   			'order'          => 'ASC',
   		)
   	);

   	if ( ! $tools ) {
   		printf(
   			'<p>%s <a href="%s">%s</a></p>',
   			esc_html__( 'No software tools yet.', 'custom-post-types-dp' ),
   			esc_url( admin_url( 'post-new.php?post_type=software_tool' ) ),
   			esc_html__( 'Add a Software Tool', 'custom-post-types-dp' )
   		);
   		return;
   	}

   	foreach ( $tools as $tool ) {
   		printf(
   			'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="cptdp_software_tools[]" value="%1$d" %2$s> %3$s</label>',
   			(int) $tool->ID,
   			checked( in_array( $tool->ID, $selected, true ), true, false ),
   			esc_html( get_the_title( $tool ) )
   		);
   	}
   }

   /**
    * Render one Post Statistics row.
    *
    * @param string $index     Row index, or a placeholder for the template row.
    * @param string $label     Label.
    * @param string $value     Value.
    * @param string $post_type Post type being edited (videos get view-count placeholders).
    */
   function cptdp_media_render_statistics_row( string $index, string $label, string $value, string $post_type = '' ): void {
   	$label_hint = 'video' === $post_type ? __( 'e.g. Views', 'custom-post-types-dp' ) : __( 'e.g. Downloads', 'custom-post-types-dp' );
   	$value_hint = 'video' === $post_type ? __( 'e.g. 12K', 'custom-post-types-dp' ) : __( 'e.g. 1.2K', 'custom-post-types-dp' );
   	?>
   	<div class="cptdp-stat-row" style="display:flex;gap:8px;margin-bottom:8px">
   		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][label]" placeholder="<?php echo esc_attr( $label_hint ); ?>" value="<?php echo esc_attr( $label ); ?>">
   		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][value]" placeholder="<?php echo esc_attr( $value_hint ); ?>" value="<?php echo esc_attr( $value ); ?>">
   		<button type="button" class="button cptdp-stat-remove"><?php esc_html_e( 'Remove', 'custom-post-types-dp' ); ?></button>
   	</div>
   	<?php
   }

   /**
    * Render the Post Statistics meta box.
    *
    * @param WP_Post $post Post being edited.
    */
   function cptdp_media_render_statistics_box( WP_Post $post ): void {
   	$rows = cptdp_media_sanitize_statistics( get_post_meta( $post->ID, 'cptdp_post_statistics', true ) );
   	?>
   	<div id="cptdp-stat-rows">
   		<?php
   		foreach ( $rows as $i => $row ) {
   			cptdp_media_render_statistics_row( (string) $i, $row['label'], $row['value'], $post->post_type );
   		}
   		?>
   	</div>
   	<template id="cptdp-stat-template">
   		<?php cptdp_media_render_statistics_row( '__INDEX__', '', '', $post->post_type ); ?>
   	</template>
   	<p><button type="button" class="button" id="cptdp-stat-add"><?php esc_html_e( 'Add statistic', 'custom-post-types-dp' ); ?></button></p>
   	<?php
   }

   /**
    * Render the Highlight meta box.
    *
    * @param WP_Post $post Post being edited.
    */
   function cptdp_media_render_highlight_box( WP_Post $post ): void {
   	?>
   	<label>
   		<input type="checkbox" name="cptdp_highlight" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ) ); ?>>
   		<?php esc_html_e( 'Mark as highlight (Selected work)', 'custom-post-types-dp' ); ?>
   	</label>
   	<?php
   }
   ```
   (In the file, indentation is tabs; the leading three spaces above are only Markdown indentation.)
2. Delete the old definitions, **bottom-up so line numbers stay valid** (each range is docblock through closing brace plus the blank line after it; verified on `584977f`). From `wp-content/plugins/custom-post-types-dp`:
   ```bash
   sed -i '' -e '543,632d' -e '227,285d' -e '191,199d' includes/post-types/document.php
   sed -i '' -e '489,578d' -e '232,290d' -e '196,204d' includes/post-types/collateral.php
   sed -i '' -e '516,605d' -e '197,255d' -e '176,184d' includes/post-types/video.php
   ```
   (`sed` applies all `-e` deletions against the original numbering in one pass.) Before running, confirm each start line is `/**` and the line before it is blank: `sed -n '191p;227p;543p' includes/post-types/document.php` prints three `/**` lines (same check with `196p;232p;489p` for collateral and `176p;197p;516p` for video). Stop and report otherwise.
3. Rename the remaining callers (meta `sanitize_callback` strings, `add_meta_box` callbacks, `save_meta` calls, and the direct test calls):
   ```bash
   perl -pi -e 's/\bcptdp_(document|collateral|video)_(sanitize_url|sanitize_software_tools|sanitize_statistics|render_software_tools_box|render_statistics_box|render_highlight_box)\b/cptdp_media_$2/g' includes/post-types/document.php includes/post-types/collateral.php includes/post-types/video.php tests/post-types/test-document.php tests/post-types/test-collateral.php tests/post-types/test-video.php
   ```
4. In `tests/class-cptdp-media-cpt-test-case.php` replace
   ```php
   	protected function call( string $suffix, ...$args ) {
   		return call_user_func( 'cptdp_' . $this->post_type() . '_' . $suffix, ...$args );
   	}
   ```
   with
   ```php
   	protected function call( string $suffix, ...$args ) {
   		$shared   = array( 'sanitize_url', 'sanitize_software_tools', 'sanitize_statistics', 'render_software_tools_box', 'render_statistics_box', 'render_highlight_box' );
   		$function = in_array( $suffix, $shared, true ) ? 'cptdp_media_' . $suffix : 'cptdp_' . $this->post_type() . '_' . $suffix;

   		return call_user_func( $function, ...$args );
   	}
   ```
   and change its docblock line `@param string $suffix Function name after \`cptdp_{type}_\`.` to `@param string $suffix Function name after \`cptdp_{type}_\` (or \`cptdp_media_\` for the shared helpers).`. The sanitiser data providers keep running once per CPT, so the test count does not change.
5. Checks: `grep -rnE "cptdp_(document|collateral|video)_(sanitize_url|sanitize_software_tools|sanitize_statistics|render_software_tools_box|render_statistics_row|render_statistics_box|render_highlight_box)" wp-content` prints nothing; `ddev exec php -l wp-content/plugins/custom-post-types-dp/includes/post-types/media-meta.php`; plugin suite count unchanged. In the browser, open a Document, a Collateral and a Video edit screen: the Post Statistics placeholders read `e.g. Downloads`/`e.g. 1.2K` (Video: `e.g. Views`/`e.g. 12K`), the Software Tools and Highlight boxes look as before, and saving keeps the values.
6. In `docs/agents/plugin-cpts.md`, after the sentence ending `loads portfolio modules from \`includes/post-types/*.php\`.` add: ` The Document, Collateral and Video types share their URL, Software Tools and Post Statistics sanitisers and their Software Tools, Post Statistics and Highlight meta boxes through \`includes/post-types/media-meta.php\` (\`cptdp_media_*\`).`

Commit 6: `refactor(custom-post-types-dp): share media meta sanitisers and boxes` (stage the four PHP files, the four test files, `docs/agents/plugin-cpts.md`).

### Commit 7: share the Post Statistics repeater script

1. Create `wp-content/plugins/custom-post-types-dp/assets/admin-statistics.js` (tabs):
   ```js
   /* Post Statistics repeater, shared by the Document, Collateral and Video edit screens. */
   ( function ( $ ) {
   	'use strict';

   	$( function () {
   		var $rows = $( '#cptdp-stat-rows' );
   		var template = document.getElementById( 'cptdp-stat-template' );

   		if ( ! $rows.length || ! template ) {
   			return;
   		}

   		function reindex() {
   			$rows.children( '.cptdp-stat-row' ).each( function ( index ) {
   				$( this ).find( 'input' ).each( function () {
   					this.name = this.name.replace( /cptdp_post_statistics\[[^\]]*\]/, 'cptdp_post_statistics[' + index + ']' );
   				} );
   			} );
   		}

   		$( '#cptdp-stat-add' ).on( 'click', function () {
   			$rows.append( template.innerHTML );
   			reindex();
   			$rows.children().last().find( 'input' ).first().trigger( 'focus' );
   		} );

   		$rows.on( 'click', '.cptdp-stat-remove', function () {
   			$( this ).closest( '.cptdp-stat-row' ).remove();
   			reindex();
   		} );

   		$( '#post' ).on( 'submit', reindex );
   	} );
   }( jQuery ) );
   ```
2. Delete the repeater from each script (blank line before `// Post Statistics repeater.` through `$( '#post' ).on( 'submit', reindex );`). From `wp-content/plugins/custom-post-types-dp/assets`: confirm `sed -n '61p;84p' admin-document.js`, `sed -n '41p;64p' admin-collateral.js`, `sed -n '188p;213p' admin-video.js` each print `// Post Statistics repeater.` then `$( '#post' ).on( 'submit', reindex );`, then run
   ```bash
   sed -i '' '60,84d' admin-document.js
   sed -i '' '40,64d' admin-collateral.js
   sed -i '' '187,213d' admin-video.js
   ```
   and in each file's first line replace `media picker and Post Statistics repeater.` with `media picker (the Post Statistics repeater is admin-statistics.js).`.
3. In each of `includes/post-types/document.php`, `collateral.php`, `video.php` replace the single line `	wp_enqueue_media();` with
   ```php
   	wp_enqueue_media();
   	wp_enqueue_script( 'cptdp-admin-statistics', CPTDP_URL . 'assets/admin-statistics.js', array( 'jquery' ), CPTDP_VERSION, true );
   ```
4. Gate: plugin suite; by hand on each of the three edit screens: Add statistic adds a row and focuses it, Remove removes it, Save stores the rows in order (reload shows them), and on Video the file picker, poster and preview still work.

Commit 7: `refactor(custom-post-types-dp): share the statistics repeater script`.

**Checkpoint 3:** `bash <scratchpad>/gates.sh phase-3`: counts unchanged.

## Phase 4: plugin dead code, the temp-folder sweep (F3) and version 0.36.0

Line numbers are at `584977f`; Phase 3 does not touch these files. Before each `sed`, print the first and last line of the range (`sed -n 'Ap;Bp' file`) and compare with the text given; stop and report on a mismatch. Each removed item was checked for string callbacks, `call_user_func` and dynamic calls.

### Commit 8: `cptdp_pptx_warnings()`

From `wp-content/plugins/custom-post-types-dp`:
```bash
sed -n '60p;74p' includes/post-types/document-presentations.php        # "/**" and an empty line
sed -n '95p;105p' tests/post-types/test-document-presentations.php     # "public function test_the_picker_gets_a_warning_for_each_kind(): void {" and an empty line
sed -i '' '60,74d' includes/post-types/document-presentations.php
sed -i '' '95,105d' tests/post-types/test-document-presentations.php
```
Gate: plugin suite **P-1**, no failures; `grep -rn cptdp_pptx_warnings wp-content AGENTS.md docs` prints nothing.

Commit 8: `refactor(custom-post-types-dp): remove the unused pptx warnings list`.

### Commit 9: sweep stray LibreOffice temp folders daily (F3, behaviour addition)

What changes for a site: a recurring WP-Cron event `cptdp_sweep_temp_folders` (daily, first run an hour after it is scheduled) deletes `cptdp-pptx-*` folders in `get_temp_dir()` that are older than a day, through the existing `CPTDP_Pptx_Converter::stray_folders()` and `cleanup()`. A conversion that is running uses a folder whose modification time is recent (its timeout is 120 s), so it is never touched. The event is scheduled on `init` when missing (one read of the autoloaded `cron` option) and removed on deactivation. `wp cptdp pages sweep [--dry-run]` runs it by hand.

1. Create `wp-content/plugins/custom-post-types-dp/includes/documents/temp-sweep.php` (the loader requires every file in `includes/documents/` after the `class-*.php` files, by name; it only defines functions and hooks):
```php
<?php
/**
 * Daily removal of LibreOffice temporary folders left behind by a crashed conversion (DPB-NNN F3).
 *
 * CPTDP_Pptx_Converter::to_pdf() makes a `cptdp-pptx-*` folder in the temp dir (a copy of the uploaded file, the
 * PDF and a throwaway profile) and removes it when it is done. A PHP fatal or a killed worker skips that, so the
 * folder stayed for ever. A daily WP-Cron event deletes the ones older than a day with the converter's own
 * safe delete (only `cptdp-pptx-*` folders, never through a link).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

const CPTDP_TEMP_SWEEP_HOOK = 'cptdp_sweep_temp_folders';

/**
 * Schedule the daily sweep once (on every request it only reads the cron option).
 */
function cptdp_schedule_temp_sweep(): void {
	if ( ! wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', CPTDP_TEMP_SWEEP_HOOK );
	}
}
add_action( 'init', 'cptdp_schedule_temp_sweep' );

/**
 * Delete the stray temporary folders.
 *
 * @return int Number of folders removed.
 */
function cptdp_sweep_temp_folders(): int {
	$removed = 0;

	foreach ( CPTDP_Pptx_Converter::stray_folders() as $dir ) {
		CPTDP_Pptx_Converter::cleanup( $dir );

		if ( ! is_dir( $dir ) ) {
			++$removed;
		}
	}

	return $removed;
}
add_action( CPTDP_TEMP_SWEEP_HOOK, 'cptdp_sweep_temp_folders' );

/**
 * Remove the event when the plugin is deactivated.
 */
function cptdp_unschedule_temp_sweep(): void {
	wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );
}
```
2. In `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php` replace
```php
register_activation_hook( __FILE__, 'cptdp_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
```
with
```php
register_activation_hook( __FILE__, 'cptdp_activate' );

/**
 * Remove the plugin's recurring cron event, then flush rewrite rules.
 */
function cptdp_deactivate(): void {
	cptdp_unschedule_temp_sweep();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cptdp_deactivate' );
```
3. In `tests/test-loader.php` (same commit, or the suite goes red) replace `$this->assertNotFalse( has_action( 'deactivate_' . $basename, 'flush_rewrite_rules' ) );` with `$this->assertNotFalse( has_action( 'deactivate_' . $basename, 'cptdp_deactivate' ) );`.
4. In `includes/cli/class-pages-command.php`, insert this method directly before the line `	 * A crop as a percentage for the tables.` (that is, before its `	/**` line, keeping one blank line between methods):
```php
	/**
	 * Delete LibreOffice temporary folders older than a day (a crashed conversion leaves them; WP-Cron does this
	 * daily, DPB-NNN F3).
	 *
	 * [--dry-run]
	 * : List the folders, delete nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp pages sweep --dry-run
	 *
	 * @param string[]             $args       Positional args (none).
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function sweep( array $args, array $assoc_args ): void {
		$folders = CPTDP_Pptx_Converter::stray_folders();

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			foreach ( $folders as $dir ) {
				WP_CLI::log( $dir );
			}
			WP_CLI::success( sprintf( '%d folder(s) would be removed.', count( $folders ) ) );
			return;
		}

		WP_CLI::success( sprintf( 'Removed %d folder(s).', cptdp_sweep_temp_folders() ) );
	}

```
5. Checks: `for f in includes/documents/temp-sweep.php custom-post-types-dp.php includes/cli/class-pages-command.php tests/test-loader.php; do ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp php -l $f; done`; after one page load `ddev wp cron event list --fields=hook,recurrence | grep cptdp_sweep_temp_folders` shows the hook with a recurrence of `1 day`; `ddev wp cptdp pages sweep --dry-run` ends with `Success: N folder(s) would be removed.`; plugin suite **P-1** (no new tests yet).

Commit 9: `feat(custom-post-types-dp): sweep stray LibreOffice temp folders daily` (stage `includes/documents/temp-sweep.php custom-post-types-dp.php includes/cli/class-pages-command.php tests/test-loader.php`).

### Commit 10: tests for the sweep (+5)

Create `wp-content/plugins/custom-post-types-dp/tests/documents/test-temp-sweep.php` (five tests, no data provider, nothing skipped):
```php
<?php
/**
 * Daily sweep of stray LibreOffice temporary folders (DPB-NNN F3).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group presentations
 */
class CPTDP_Test_Temp_Sweep extends CPTDP_Test_Case {

	/** @var string[] Folders a test made, removed in tear_down. */
	private $made = array();

	public function tear_down(): void {
		foreach ( $this->made as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}
		wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );
		parent::tear_down();
	}

	private function folder( string $prefix, int $age ): string {
		$dir = trailingslashit( get_temp_dir() ) . $prefix . wp_generate_password( 8, false );
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		touch( $dir, time() - $age );
		$this->made[] = $dir;
		return $dir;
	}

	public function test_the_sweep_is_scheduled_daily_on_init(): void {
		$this->assertSame( 10, has_action( 'init', 'cptdp_schedule_temp_sweep' ) );
		wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );

		cptdp_schedule_temp_sweep();

		$this->assertNotFalse( wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( CPTDP_TEMP_SWEEP_HOOK ) );
	}

	public function test_scheduling_again_keeps_one_event(): void {
		wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );
		cptdp_schedule_temp_sweep();
		$first = wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK );

		cptdp_schedule_temp_sweep();

		$this->assertSame( $first, wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) );
		$count = 0;
		foreach ( (array) _get_cron_array() as $hooks ) {
			$count += isset( $hooks[ CPTDP_TEMP_SWEEP_HOOK ] ) ? count( $hooks[ CPTDP_TEMP_SWEEP_HOOK ] ) : 0;
		}
		$this->assertSame( 1, $count );
	}

	public function test_the_cron_event_runs_the_sweep(): void {
		$this->assertSame( 10, has_action( CPTDP_TEMP_SWEEP_HOOK, 'cptdp_sweep_temp_folders' ) );
	}

	public function test_the_sweep_removes_only_old_folders_of_its_own(): void {
		$old   = $this->folder( 'cptdp-pptx-', 2 * DAY_IN_SECONDS );
		$fresh = $this->folder( 'cptdp-pptx-', 60 );
		$other = $this->folder( 'not-ours-', 2 * DAY_IN_SECONDS );

		$removed = cptdp_sweep_temp_folders();

		$this->assertGreaterThanOrEqual( 1, $removed );
		$this->assertDirectoryDoesNotExist( $old );
		$this->assertDirectoryExists( $fresh, 'A folder a running conversion may still use is kept.' );
		$this->assertDirectoryExists( $other, 'Only cptdp-pptx-* folders are touched.' );
	}

	public function test_deactivation_removes_the_event(): void {
		cptdp_schedule_temp_sweep();

		cptdp_deactivate();

		$this->assertFalse( wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) );
	}
}
```
(`assertGreaterThanOrEqual( 1, ... )`, not `assertSame( 1, ... )`: an older stray folder from a real crashed run may also be in the container's temp dir.) Lint: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp php -l tests/documents/test-temp-sweep.php`. Gate: plugin **P+4** (P-1+5), skipped S; `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Temp_Sweep` shows 5 tests.

Commit 10: `test(custom-post-types-dp): cover the temp folder sweep`.

### Commit 11: document the sweep

1. In `docs/agents/documents-pipeline.md`, after `the temporary folder is always removed.` (occurs once) insert: `` A crashed run can still leave one behind: a daily WP-Cron event (`cptdp_sweep_temp_folders`, `includes/documents/temp-sweep.php`, scheduled on `init`, removed by `cptdp_deactivate()`) deletes `cptdp-pptx-*` folders older than a day with `CPTDP_Pptx_Converter::stray_folders()` and `cleanup()`; `ddev wp cptdp pages sweep [--dry-run]` does it now. `` (with one leading space).
2. In `AGENTS.md`, in the `Expected totals today:` line, replace `plugin <P> tests` (the number commit 4 wrote) with `plugin <P+4> tests`.

Commit 11: `docs(agents): document the temp folder sweep` (stage `AGENTS.md docs/agents/documents-pipeline.md`).

### Commit 12: version

Decision: **bump to 0.36.0.** `AGENTS.md` says to bump the plugin `Version` when a feature ships, and commit 9 is one. In `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php` replace ` * Version:           0.35.0` with ` * Version:           0.36.0` and `define( 'CPTDP_VERSION', '0.35.0' );` with `define( 'CPTDP_VERSION', '0.36.0' );`. (`tests/test-loader.php:15` only checks the `x.y.z` shape.) `grep -rn "0\.35\.0" wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php` prints nothing.

Commit 12: `chore(custom-post-types-dp): bump version to 0.36.0`.

### Commit 13 (optional): one class per file in the PDF outline reader

`includes/documents/class-page-outline.php` (876 lines) starts with three small value classes, `CPTDP_Pdf_Name` (line 23), `CPTDP_Pdf_Str` (34) and `CPTDP_Pdf_Ref` (45), before `CPTDP_Page_Outline` (58). Move the three, verbatim with their docblocks, to a new `includes/documents/class-pdf-values.php` (file header `<?php`, a `/** PDF value objects used by CPTDP_Page_Outline. @package CustomPostTypesDP */` docblock and `defined( 'ABSPATH' ) || exit;`). No class extends another and nothing instantiates them at load, so the loader's alphabetical order does not matter. Plugin suite unchanged. Skip this commit if anything about it is unclear; the commit numbers after it stay as they are.

Commit 13: `refactor(custom-post-types-dp): split the PDF value classes out`.

**Checkpoint 4:** `bash <scratchpad>/gates.sh phase-4`: plugin **P+4** (S skipped), theme T, node unchanged.

## Phase 5: PHPCS report (F4)

Decisions:
- **PHP_CodeSniffer with WordPress Coding Standards, report only**, as plugin `require-dev` (the plugin already holds the only Composer install, which the theme suite also uses). One ruleset at the plugin root covers the plugin **and** the theme (both text domains and both prefixes allowed). `tests/`, `bin/` and every `vendor/` are excluded, as are non-PHP files (`extensions=php`, so `assets/vendor` and `blocks/shared/vendor` are never read).
- **Report only by construction:** the ruleset sets `ignore_errors_on_exit` and `ignore_warnings_on_exit`, so `phpcs` exits 0 whatever it finds. It is **not** part of `gates.sh` and must never fail a commit, a checkpoint or the future pre-push gate. Nothing is fixed in this ticket (no phpcbf run): fixing the 306 findings is a later ticket.
- **No ESLint.** The repo has no `package.json` or npm toolchain (node is only used for `node --test`); adding one is a new build dependency for a report nobody gates on. Left for a later ticket (Residual).

### Commit 14

1. Replace `wp-content/plugins/custom-post-types-dp/composer.json` with exactly (tabs):
```json
{
	"name": "lonewanderer27/custom-post-types-dp",
	"description": "Portfolio custom post types and settings. Dev dependencies for the PHPUnit suite and the PHPCS report.",
	"type": "wordpress-plugin",
	"license": "MIT",
	"require": {
		"php": ">=8.4"
	},
	"require-dev": {
		"dealerdirect/phpcodesniffer-composer-installer": "^1.0",
		"phpunit/phpunit": "^9.6",
		"wp-coding-standards/wpcs": "^3.1",
		"yoast/phpunit-polyfills": "^2.0"
	},
	"config": {
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		},
		"process-timeout": 0,
		"sort-packages": true
	},
	"scripts": {
		"test": "phpunit",
		"lint": "phpcs",
		"lint:summary": "phpcs --report=summary"
	}
}
```
2. Create `wp-content/plugins/custom-post-types-dp/phpcs.xml.dist` with exactly (tabs):
```xml
<?xml version="1.0"?>
<ruleset name="devportfolio">
	<description>WordPress Coding Standards for the custom-post-types-dp plugin and the devportfolio theme. Report only (DPB-NNN F4): it never fails a gate.</description>

	<file>.</file>
	<file>../../themes/devportfolio</file>

	<exclude-pattern>*/vendor/*</exclude-pattern>
	<exclude-pattern>*/node_modules/*</exclude-pattern>
	<exclude-pattern>*/tests/*</exclude-pattern>
	<exclude-pattern>*/bin/*</exclude-pattern>

	<arg name="extensions" value="php"/>
	<arg name="basepath" value="."/>
	<arg name="parallel" value="8"/>
	<arg value="sp"/>

	<config name="ignore_errors_on_exit" value="1"/>
	<config name="ignore_warnings_on_exit" value="1"/>
	<config name="minimum_wp_version" value="6.7"/>

	<rule ref="WordPress"/>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array">
				<element value="custom-post-types-dp"/>
				<element value="devportfolio"/>
			</property>
		</properties>
	</rule>

	<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
		<properties>
			<property name="prefixes" type="array">
				<element value="cptdp"/>
				<element value="devportfolio"/>
			</property>
		</properties>
	</rule>
</ruleset>
```
3. Install without touching the other packages (`composer.lock` is git-ignored, so only `composer.json` is committed). Record `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --version` first, then:
```bash
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp update --no-interaction --with-dependencies dealerdirect/phpcodesniffer-composer-installer wp-coding-standards/wpcs
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --version
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpcs -i
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary
echo "exit=$?"
```
   The PHPUnit version line is the same as before; `phpcs -i` lists `WordPress, WordPress-Core, WordPress-Docs and WordPress-Extra`; the summary ends with `A TOTAL OF <E> ERRORS AND <W> WARNINGS WERE FOUND IN <F> FILES` and `exit=0`. Write E, W and F in the PR description (reference, measured on `584977f` before Phases 3 and 4 moved and added plugin code: 133, 173, 48). A different number is not a failure; a non-zero exit is (stop and report).
4. `git status --short wp-content/plugins/custom-post-types-dp` shows only `composer.json` (modified) and `phpcs.xml.dist` (new); `vendor/` and `composer.lock` stay ignored.
5. Gates (the PHPUnit runner was reinstalled): `bash <scratchpad>/gates.sh commit-14`, counts as checkpoint 4.

Commit 14: `build(custom-post-types-dp): add a report-only PHPCS run with WPCS` (stage `composer.json phpcs.xml.dist`).

### Commit 15: document it

1. `AGENTS.md`: replace the line `No linter or CI configured.` with `` PHPCS with WordPress Coding Standards is a report, never a gate: `ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary` (counts) or `lint` (every finding), ruleset `wp-content/plugins/custom-post-types-dp/phpcs.xml.dist` (plugin and theme PHP, tests excluded). See `docs/agents/tests.md`. No ESLint, no CI. ``
2. `AGENTS.md` `## Commands` bash block: after the line `ddev wp theme activate devportfolio  # theme has no build step` add the line `ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary  # PHPCS report, never fails`.
3. Append to the end of `docs/agents/tests.md` (one blank line before):
```
## PHPCS (report only, DPB-NNN F4)

`wp-content/plugins/custom-post-types-dp/phpcs.xml.dist` runs the `WordPress` standard (WPCS 3) over the plugin and the theme PHP; `tests/`, `bin/` and `vendor/` are excluded, and only `.php` files are read. It sets `ignore_errors_on_exit` and `ignore_warnings_on_exit`, so it always exits 0. Run `ddev composer --working-dir=wp-content/plugins/custom-post-types-dp lint:summary` for the counts per file, or `lint` for every finding (`ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpcs path/to/file.php` for one file). Baseline when it was added: 133 errors and 173 warnings in 48 of 83 files (plugin 123 and 73 in 37, theme 10 and 100 in 11), 178 fixable by `phpcbf`. Do not run `phpcbf` over the tree as a drive-by: fix the files you touch. The pre-push gate (`.claude/plans/backend/DPB-NNN-Pre-Push-Test-Gate.md`, not built yet) may print `lint:summary` but must never fail on it.
```

Commit 15: `docs(agents): document the PHPCS report` (stage `AGENTS.md docs/agents/tests.md`).

**Checkpoint 5:** `bash <scratchpad>/gates.sh phase-5`: plugin P+4, theme T, node unchanged.

## Phase 6: theme, split functions.php by domain

The map (every line of `functions.php` at `584977f` is in exactly one place; the script checks this):

| File | Lines of today's `functions.php` | Functions |
|---|---|---|
| `functions.php` (stays) | 1-75, 209-305 | `prepare_navigation_link` (+ its `render_block_data` filter), `register_blocks` (`__DIR__`), `enqueue_page_styles`, `enqueue_nav_spy`, `enqueue_home_motion`, `print_home_reveal_script` (all `__DIR__`; the three `wp_enqueue_scripts` callbacks keep their registration order, which is the CSS order) |
| `inc/core.php` | 307-380, 382-495, 538-562, 822-850, 920-967, 1049-1075 | `modal_single_redirect_url`, `document_single_redirect_url`, `collateral_single_redirect_url`, `redirect_document_single` (+ `template_redirect` priority 1), `memo_store`, `memo`, `memo_flush`, `memo_add_hooks` (+ the top-level `devportfolio_memo_add_hooks();` call), `published_ids`, `term_slugs`, `page_source_map`, `page_count_label`, `software_tool_names`, `tool_monogram`, `media_payload_base` (15) |
| `inc/documents.php` | 497-536, 564-820, 852-918, 969-1047, 1077-1437 | `cover_styles`, `sanitize_cover_style`, the 22 `document_*` helpers (24) |
| `inc/collaterals.php` | 1439-1583, 1995-2154, 2365-2404 | the 10 `collateral_*` helpers |
| `inc/videos.php` | 1585-1993 | the 15 `video_*` helpers |
| `inc/viewer.php` | 2156-2363 | the 8 `viewer_*` helpers |
| `inc/home.php` | 77-207, 2406-2683 | 6 `hero_*` (+ `wp_head` priority 2), 7 `work_*`, `contact_strings`, `contact_config` (15) |

6 + 15 + 24 + 10 + 15 + 8 + 15 = 93. Result (dry run, `php -l` clean on all seven files): `functions.php` 180 lines, `inc/` 2,558 lines. No hook shares a hook name and priority with another moved hook, so the move does not change any callback order.

### Commit 16: move

1. Save as `split-functions.py` in the scratchpad and run `python3 <scratchpad>/split-functions.py wp-content/themes/devportfolio` from the repo root (it overwrites `functions.php` and creates `inc/`):
   ```python
   #!/usr/bin/env python3
   """Split wp-content/themes/devportfolio/functions.php into inc/*.php by line range (DPB-NNN Phase 6).

   Usage: python3 split-functions.py <theme_dir> [<out_dir>]
   Line numbers are for functions.php at commit 584977f. The script refuses to run if any boundary moved.
   """
   import sys, os

   theme = sys.argv[1]
   out = sys.argv[2] if len(sys.argv) > 2 else theme
   src = os.path.join(theme, 'functions.php')
   L = open(src, encoding='utf-8').read().split('\n')
   if L and L[-1] == '':
       L = L[:-1]

   KEEP = [(1, 75), (209, 305)]
   FILES = [
       ('core', 'Helpers shared by every page: single-to-deeplink redirects, the per-request memo, published IDs, term slugs, page sources, page-count label, software tool names and the media payload base.', [(307, 380), (382, 495), (538, 562), (822, 850), (920, 967), (1049, 1075)]),
       ('documents', 'Documents page helpers: cover styles, file kinds and units, format labels, download target, shelf item, modal payload and viewer data.', [(497, 536), (564, 820), (852, 918), (969, 1047), (1077, 1437)]),
       ('collaterals', 'Brand Collaterals page helpers: grid item, preview, pill, stats, thumbnails, modal payload and strings.', [(1439, 1583), (1995, 2154), (2365, 2404)]),
       ('videos', 'Videos page helpers: grid item, preview URL, length and shape labels, embeds, modal payload, mobile controls and strings.', [(1585, 1993)]),
       ('viewer', 'Viewer markup shared by the Documents, Collaterals and Videos modals: arrows, status line, footer and the mobile header, body and footer.', [(2156, 2363)]),
       ('home', 'Home page helpers: hero, Selected work and Contact.', [(77, 207), (2406, 2683)]),
   ]

   assert len(L) == 2683, f'functions.php has {len(L)} lines, expected 2683: stop and report'
   covered = set()
   for a, b in KEEP + [r for _, _, rs in FILES for r in rs]:
       for i in range(a, b + 1):
           assert i not in covered, f'line {i} in two ranges'
           covered.add(i)
   for i in range(1, len(L) + 1):
       if i not in covered:
           assert L[i - 1].strip() == '', f'line {i} is not blank and not in any range: stop and report'
   for _, _, rs in FILES:
       for a, b in rs:
           assert L[a - 1].startswith('if ( ! function_exists('), f'range starting at {a} does not start a function_exists block'
           assert L[b - 1] == 'endif;' or L[b - 1].startswith('add_action('), f'range ending at {b} does not end a block: {L[b - 1]!r}'

   os.makedirs(os.path.join(out, 'inc'), exist_ok=True)
   for name, desc, rs in FILES:
       body = []
       for a, b in sorted(rs):
           if body:
               body.append('')
           body.extend(L[a - 1:b])
       head = ['<?php', '/**', ' * ' + desc, ' *', ' * Required by functions.php.', ' *', ' * @package devportfolio', ' */', '']
       open(os.path.join(out, 'inc', name + '.php'), 'w', encoding='utf-8').write('\n'.join(head + body) + '\n')

   keep = L[0:75] + [''] + ["require_once __DIR__ . '/inc/%s.php';" % n for n, _, _ in FILES] + [''] + L[208:305]
   open(os.path.join(out, 'functions.php'), 'w', encoding='utf-8').write('\n'.join(keep) + '\n')
   print('ok')
   ```
   The `require_once` lines sit after `add_action( 'init', 'devportfolio_register_blocks' );`. `require_once` with `__DIR__` is safe with the 34 tests that `require_once` `functions.php` again.
2. Lint: `for f in functions.php inc/*.php; do ddev exec --dir=/var/www/html/wp-content/themes/devportfolio php -l $f; done`.
3. Point the three text-reading tests at the new files (same commit, or the theme suite goes red):
   - `tests/test-contact-block.php` line 262: `file_get_contents( $this->theme_dir() . '/functions.php' )` -> `file_get_contents( $this->theme_dir() . '/inc/home.php' )`.
   - `tests/test-document-spreadsheets.php` lines 234-236: comment `in functions.php (WP6).` -> `in inc/viewer.php (WP6).`, and both `$this->read( 'functions.php' )` -> `$this->read( 'inc/viewer.php' )`.
   - `tests/test-document-presentations.php` line 380: in the array, after `'functions.php'` add `, 'inc/core.php', 'inc/documents.php', 'inc/collaterals.php', 'inc/videos.php', 'inc/viewer.php', 'inc/home.php'` (keeps the negative check meaningful).
4. Gate: theme suite count unchanged; then `curl -sk -o /dev/null -w '%{http_code}\n'` for `https://devportfolio.ddev.site/`, `/documents/`, `/videos/`, `/collaterals/` prints `200` four times, `curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' https://devportfolio.ddev.site/documents/<any-published-slug>/` still redirects to `?document_slug=`, and `ddev logs -s web --tail 50 | grep -i "php \(fatal\|warning\)"` prints nothing.

Commit 16: `refactor(theme): move functions.php helpers into inc by domain` (stage `wp-content/themes/devportfolio/functions.php`, `wp-content/themes/devportfolio/inc/` and the three tests).

### Commit 17: document the map

In `docs/agents/theme-core.md`, replace the bullet that starts `` - `functions.php` — one filter: `` (whole line) with:
```
- `functions.php` — the navigation link filter, block registration, the page/Home enqueues and the Home reveal script (everything that uses `__DIR__` or whose hook order is the CSS order), then `require_once` of `inc/core.php` (redirects, memo, published IDs, term slugs, page sources, shared labels and payload base), `inc/documents.php`, `inc/collaterals.php`, `inc/videos.php`, `inc/viewer.php` (modal markup shared by the three viewers) and `inc/home.php` (hero, Selected work, contact). A new helper goes in the file of its page; a helper two pages use goes in `inc/core.php` or `inc/viewer.php`. Never move a function that uses `__DIR__` into `inc/`.
```
Commit 17: `docs(agents): map the theme include files`.

**Checkpoint 6:** `bash <scratchpad>/gates.sh phase-6`: plugin P+4, theme T, node unchanged.

## Phase 7: theme dead CSS and JS

Each item was checked for string callbacks, `call_user_func`, namespace imports (`import * as`, none exist) and dynamic imports (only `details-sheet.js`, `pages-sheet.js`, the vendored libraries and pdf.js are dynamic).

Line numbers below are at `584977f`; no earlier phase touches these files. Before each `sed`, print the first and last line of the range (`sed -n 'Ap;Bp' file`) and compare with the text given; stop and report on a mismatch.

### Commit 18: the dead `videos-modal__blank` rule

Only the measured dead rule. In `wp-content/themes/devportfolio/blocks/video-modal/style.css` delete lines 265 to 276 (the `.videos-modal__blank { ... }` rule and the blank line after it). Before deleting, `sed -n '265p;275p' blocks/video-modal/style.css` must print `.videos-modal__blank {` and `}`, and `grep -rn "__blank" wp-content/themes/devportfolio/blocks --include=*.js --include=*.php | grep -v vendor` must list only `collaterals-item__blank` and `collaterals-modal__blank`. Gate: theme suite and node suites unchanged.

Commit 18: `refactor(theme): remove the unused videos-modal blank rule`.

### Commit 19: `isNativeGrid` and `withoutSheetState`

From `wp-content/themes/devportfolio/blocks`:
```bash
sed -n '109p;116p' shared/mode.js                  # "/**" and an empty line (isNativeGrid docblock .. blank after "}")
sed -n '89p;98p' shared/deeplink.js                # an empty line and "}" (withoutSheetState is the last function)
sed -n '123p;129p;139p' shared/tests/mode.test.mjs # "test( 'only a .xlsx with a native URL ...", an empty line, the isNativeGrid docx assert
sed -i '' '109,116d' shared/mode.js
sed -i '' '89,98d' shared/deeplink.js
sed -i '' -e '139d' -e '123,129d' shared/tests/mode.test.mjs
sed -i '' '48,50d' document-modal/tests/deeplink.test.mjs
perl -pi -e 's/ isNativeGrid, / /' shared/tests/mode.test.mjs
perl -pi -e 's/, withoutSheetState \}/ }/' document-modal/tests/deeplink.test.mjs
```
(`document-modal/tests/deeplink.test.mjs` lines 48 to 50 are the three `withoutSheetState` asserts; the test keeps its `withSheetState( null )` assert.) In `docs/agents/viewer-shared.md` replace `` (`nativeAllowedOnMobile()`, `isNativeScroll()`; `isNativeGrid()` is the xlsx-only check for the tab bar) `` with `` (`nativeAllowedOnMobile()`, `isNativeScroll()`) ``.

In `AGENTS.md`, in the `Expected totals today:` line, replace `shared JS 179 tests` with `shared JS 178 tests`.

Gate: `blocks/shared` **178** tests (one fewer), `blocks/document-modal` 17, everything else unchanged; `grep -rn "isNativeGrid\|withoutSheetState" wp-content/themes/devportfolio AGENTS.md docs` prints nothing.

Commit 19: `refactor(theme): remove two test-only viewer helpers`.

**Checkpoint 7:** `bash <scratchpad>/gates.sh phase-7`: plugin P+4, theme T, shared **178**, other node suites unchanged.

## Phase 8: shared viewer CSS in `blocks/shared/viewer.css` (F2)

### Design and decisions

- **Order: F2 before F1, in separate phases.** F2 is a pure move whose only check is "every page looks the same"; F1 changes when CSS and JS arrive. Doing them together would make a visual regression impossible to pin on one of them, and F1's style list (Phase 9) names the handle `devportfolio-viewer` that this phase gives its own file.
- **What moves:** the classification is mechanical and lives in the script below. A rule whose selector names `collaterals-` or `videos-` moves to `blocks/shared/viewer.css`; an `@keyframes` used by a moved rule moves with it; every other rule (only `documents-*`, plus `[data-document-id] { cursor: pointer; }`) stays in `blocks/document-modal/style.css`. Comments travel with the rule after them; an `@media` block is split into one block per file with the same prelude.
- **Cascade: nothing changes on any page.** Two load orders, both kept:
  - On `/` and `/documents/` the Documents block now lists `["devportfolio-viewer", "file:./style.css"]` in `block.json`, so `viewer.css` prints where `document-modal/style.css` printed and the Documents-only rest right after it. Moving shared rules **before** the Documents-only rules would flip any pair where a Documents rule used to come first and competes with a later shared rule (same subject class, same specificity, a common property). The script finds those and moves the Documents rule along with them ("promotion"), repeated until nothing changes, so every such pair keeps its order. Dry run on `584977f`: **2 promoted** (`.documents-modal__download`, which a later `prefers-reduced-motion` rule sets to `transition: none`, a real case; and `.documents-modal__state[data-kind="unavailable"] .documents-modal__state-text`).
  - On `/collaterals/` and `/videos/` the archive keeps its own late `wp_enqueue_style( 'devportfolio-viewer', ... )` in `devportfolio_enqueue_page_styles()`, now pointing at `blocks/shared/viewer.css`. It stays out of the Collaterals and Videos `block.json` on purpose: as a block style it would print before their own `style.css` (position 9) instead of after `global-styles` and `page.css` (position 17), and a cross-file check found **11** competing pairs with `collateral-modal/style.css` (for example `.collaterals-mobile[open]` `animation`) and **1** with `video-modal/style.css` (`.videos-mobile__details-category`) whose winner would flip.
- **What it saves:** `/collaterals/` and `/videos/` load 47,319 instead of 56,650 bytes of viewer CSS; the Documents page and Home load the same bytes in two files. (`/videos/` still gets the `collaterals-*` rules, because most shared rules list several prefixes in one selector; splitting by prefix is not this ticket, see Residual.) After the move `document-modal/style.css` is 10,359 bytes and may fall under WordPress's inline budget on some pages (it is then printed as `<style id="devportfolio-document-modal-style-inline-css">` in the same place): that is expected, same order.

### Commit 20: move (script, PHP, block.json and the test paths in one commit)

1. Save as `<scratchpad>/split-viewer-css.mjs` and run `node <scratchpad>/split-viewer-css.mjs wp-content/themes/devportfolio --check` from the repo root. It must print exactly these counts (and the two promoted rules), then `ok`:
   ```
   rules 263: shared 204, documents 59
   keyframes in viewer.css: documents-mobile-up documents-modal-spin documents-mobile-fade documents-mobile-sheet-up
   promoted 2:
   bytes: document-modal/style.css 10359, shared/viewer.css 47319
   ```
   Then run it without `--check` (writes both files) and confirm `md5 -q wp-content/themes/devportfolio/blocks/document-modal/style.css wp-content/themes/devportfolio/blocks/shared/viewer.css` prints `4fbca5b90c4e6b7ec00dc6365e1a4049` and `2ba05efc56b3026a36bd584ad9632faf`. Any other number or checksum: `git checkout -- wp-content/themes/devportfolio/blocks/document-modal/style.css`, `rm -f wp-content/themes/devportfolio/blocks/shared/viewer.css`, stop and report.

```js
#!/usr/bin/env node
/**
 * Move the viewer rules shared by the Documents, Collaterals and Videos modals out of
 * blocks/document-modal/style.css into blocks/shared/viewer.css (DPB-NNN Phase 8, F2).
 *
 * Usage: node split-viewer-css.mjs <theme_dir> [<out_dir>] [--check]
 *   --check  write nothing, print the counts only.
 *
 * Classification (decided in the plan, never by hand):
 *   1. a rule whose selector text contains `collaterals-` or `videos-`                  -> shared
 *   2. an @keyframes whose name is used by an `animation` in a shared rule                -> shared
 *   3. a documents rule that comes BEFORE a shared rule it may compete with (same subject
 *      class, same specificity, a common property) is promoted to shared, repeated until
 *      nothing changes: viewer.css loads first, so this keeps every such pair in its
 *      original order                                                                     -> shared
 *   4. everything else                                                                    -> documents
 * A comment travels with the item that follows it. An @media block is split in two @media blocks with the
 * same prelude, one per file; an empty one is dropped. Order inside each file is the original order.
 */
import fs from 'node:fs';
import path from 'node:path';

const args = process.argv.slice( 2 );
const check = args.includes( '--check' );
const [ theme, outArg ] = args.filter( ( a ) => a !== '--check' );
const out = outArg || theme;
const css = fs.readFileSync( path.join( theme, 'blocks/document-modal/style.css' ), 'utf8' );

const EXPECT_BYTES = 56650;
if ( Buffer.byteLength( css ) !== EXPECT_BYTES ) {
	console.error( `document-modal/style.css is ${ Buffer.byteLength( css ) } bytes, expected ${ EXPECT_BYTES }: stop and report` );
	process.exit( 1 );
}

function skipQuoted( text, k ) {
	const q = text[ k ];
	for ( k++; k < text.length; k++ ) {
		if ( text[ k ] === '\\' ) {
			k++;
		} else if ( text[ k ] === q ) {
			return k;
		}
	}
	throw new Error( 'unclosed string' );
}

/** Split CSS text into items { lead, head, body, raw } at one nesting level; the last entry is { tail }. */
function items( text ) {
	const res = [];
	let start = 0;
	let i = 0;
	while ( i <= text.length ) {
		let j = i;
		for ( ;; ) {
			while ( j < text.length && /\s/.test( text[ j ] ) ) {
				j++;
			}
			if ( text.startsWith( '/*', j ) ) {
				j = text.indexOf( '*/', j + 2 ) + 2;
				continue;
			}
			break;
		}
		if ( j >= text.length ) {
			res.push( { tail: text.slice( start ) } );
			return res;
		}
		let open = j;
		for ( ; text[ open ] !== '{'; open++ ) {
			if ( text[ open ] === '"' || text[ open ] === "'" ) {
				open = skipQuoted( text, open );
			} else if ( text[ open ] === ';' || open >= text.length ) {
				throw new Error( `unsupported statement near: ${ text.slice( j, j + 60 ) }` );
			}
		}
		let depth = 0;
		let close = open;
		for ( ; close < text.length; close++ ) {
			const c = text[ close ];
			if ( c === '"' || c === "'" ) {
				close = skipQuoted( text, close );
			} else if ( text.startsWith( '/*', close ) ) {
				close = text.indexOf( '*/', close + 2 ) + 1;
			} else if ( c === '{' ) {
				depth++;
			} else if ( c === '}' && --depth === 0 ) {
				break;
			}
		}
		if ( depth !== 0 ) {
			throw new Error( 'unbalanced braces' );
		}
		res.push( { lead: text.slice( start, j ), head: text.slice( j, open ).trim(), body: text.slice( open + 1, close ), raw: text.slice( start, close + 1 ) } );
		i = close + 1;
		start = i;
	}
	return res;
}

const top = items( css );
const tail = top.pop().tail;
if ( ! top[ 0 ].lead.startsWith( '/* Document modal' ) ) {
	console.error( 'the file header comment moved: stop and report' );
	process.exit( 1 );
}

// Flatten to rules with their @media context, in source order, keeping a handle on each top-level item.
const flat = [];
top.forEach( ( it ) => {
	if ( it.head.startsWith( '@media' ) ) {
		it.inner = items( it.body );
		it.innerTail = it.inner.pop().tail;
		it.inner.forEach( ( r ) => flat.push( Object.assign( r, { ctx: it.head } ) ) );
	} else if ( it.head.startsWith( '@' ) && ! it.head.startsWith( '@keyframes' ) ) {
		throw new Error( `unexpected at-rule ${ it.head }: stop and report` );
	} else {
		flat.push( Object.assign( it, { ctx: '' } ) );
	}
} );
flat.forEach( ( r, index ) => {
	r.index = index;
	r.keyframes = r.head.startsWith( '@keyframes' );
} );

// 1 and 4.
flat.forEach( ( r ) => {
	r.side = ! r.keyframes && /collaterals-|videos-/.test( r.head ) ? 'shared' : 'documents';
} );

// 3. Promotion to a fixpoint.
const props = ( body ) => new Set( [ ...body.matchAll( /(?:^|;|\{)\s*(-?[a-z-]+)\s*:/g ) ].map( ( m ) => m[ 1 ] ) );
const subjects = ( head ) => head.split( ',' ).map( ( sel ) => {
	const parts = sel.trim().split( /[\s>+~]+/ ).filter( Boolean );
	const last = parts[ parts.length - 1 ] || '';
	const a = ( sel.match( /#[\w-]+/g ) || [] ).length;
	const b = ( sel.match( /\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g ) || [] ).length;
	const c = ( sel.match( /(?:^|[\s>+~])[a-z][\w-]*|::[\w-]+/g ) || [] ).length;
	return { classes: new Set( last.match( /\.[\w-]+/g ) || [] ), spec: `${ a },${ b },${ c }` };
} );
flat.forEach( ( r ) => {
	if ( ! r.keyframes ) {
		r.props = props( r.body );
		r.subjects = subjects( r.head );
	}
} );
const competes = ( d, s ) => [ ...s.props ].some( ( p ) => d.props.has( p ) )
	&& d.subjects.some( ( x ) => s.subjects.some( ( y ) => x.spec === y.spec && [ ...x.classes ].some( ( k ) => y.classes.has( k ) ) ) );
const promoted = [];
for ( let changed = true; changed; ) {
	changed = false;
	for ( const d of flat ) {
		if ( d.keyframes || d.side !== 'documents' ) {
			continue;
		}
		const s = flat.find( ( x ) => ! x.keyframes && x.side === 'shared' && x.index > d.index && competes( d, x ) );
		if ( s ) {
			d.side = 'shared';
			promoted.push( `#${ d.index } [${ d.ctx || 'top' }] ${ d.head.replace( /\s+/g, ' ' ).slice( 0, 80 ) }  (before #${ s.index } ${ s.head.replace( /\s+/g, ' ' ).slice( 0, 50 ) })` );
			changed = true;
		}
	}
}

// 2.
const animations = new Set();
flat.filter( ( r ) => ! r.keyframes && r.side === 'shared' ).forEach( ( r ) => {
	for ( const m of r.body.matchAll( /animation(?:-name)?\s*:\s*([\w-]+)/g ) ) {
		animations.add( m[ 1 ] );
	}
} );
flat.filter( ( r ) => r.keyframes ).forEach( ( r ) => {
	r.side = animations.has( r.head.split( /\s+/ )[ 1 ] ) ? 'shared' : 'documents';
} );

function build( side ) {
	let text = '';
	top.forEach( ( it ) => {
		if ( it.inner ) {
			const kept = it.inner.filter( ( r ) => r.side === side );
			if ( kept.length ) {
				text += it.lead + it.head + ' {' + kept.map( ( r ) => r.raw ).join( '' ) + it.innerTail + '}';
			}
		} else if ( it.side === side ) {
			text += it.raw;
		}
	} );
	return text.replace( /^\s+/, '' ) + tail;
}
let documents = build( 'documents' );
let shared = build( 'shared' );
if ( ! documents.startsWith( '/* Document modal' ) ) {
	documents = '/* Document modal (DPD-009 to DPD-012). */\n' + documents;
}
documents = documents.replace( /^\/\* Document modal[^\n]*\*\/\n/, '/* Document modal (DPD-009 to DPD-012): rules only the Documents viewer uses. The rules it shares with the Collaterals and Videos viewers are in blocks/shared/viewer.css (handle devportfolio-viewer), which loads first. */\n' );
shared = '/* Viewer rules shared by the Documents, Collaterals and Videos modals (moved from blocks/document-modal/style.css, DPB-NNN F2). Handle devportfolio-viewer: listed first in the Documents modal block.json style, enqueued on the Collaterals and Videos archives by devportfolio_enqueue_page_styles(). Tokens follow the hi-fi viewer (.vmodal / .vhead / .vfoot). */\n' + shared.replace( /^\/\* Document modal[^\n]*\*\/\n/, '' );

// Verify: re-parse both outputs; the multiset of (context, selector, body) equals the original, order kept per file.
const key = ( r ) => `${ r.ctx }\u0000${ r.head }\u0000${ r.body }`;
const reparse = ( text ) => {
	const list = [];
	const t = items( text );
	t.pop();
	t.forEach( ( it ) => {
		if ( it.head.startsWith( '@media' ) ) {
			const inner = items( it.body );
			inner.pop();
			inner.forEach( ( r ) => list.push( key( { ...r, ctx: it.head } ) ) );
		} else {
			list.push( key( { ...it, ctx: '' } ) );
		}
	} );
	return list;
};
const outDocs = reparse( documents );
const outShared = reparse( shared );
const expectDocs = flat.filter( ( r ) => r.side === 'documents' ).map( key );
const expectShared = flat.filter( ( r ) => r.side === 'shared' ).map( key );
if ( JSON.stringify( outDocs ) !== JSON.stringify( expectDocs ) || JSON.stringify( outShared ) !== JSON.stringify( expectShared ) ) {
	throw new Error( 'a rule was lost, duplicated or reordered: stop and report' );
}
if ( outDocs.length + outShared.length !== flat.length ) {
	throw new Error( 'rule count changed: stop and report' );
}

const count = ( side ) => flat.filter( ( r ) => r.side === side ).length;
console.log( `rules ${ flat.length }: shared ${ count( 'shared' ) }, documents ${ count( 'documents' ) }` );
console.log( `keyframes in viewer.css: ${ flat.filter( ( r ) => r.keyframes && r.side === 'shared' ).map( ( r ) => r.head.split( /\s+/ )[ 1 ] ).join( ' ' ) }` );
console.log( `promoted ${ promoted.length }:` );
promoted.forEach( ( p ) => console.log( '  ' + p ) );
console.log( `bytes: document-modal/style.css ${ Buffer.byteLength( documents ) }, shared/viewer.css ${ Buffer.byteLength( shared ) }` );
if ( ! check ) {
	fs.mkdirSync( path.join( out, 'blocks/shared' ), { recursive: true } );
	fs.mkdirSync( path.join( out, 'blocks/document-modal' ), { recursive: true } );
	fs.writeFileSync( path.join( out, 'blocks/document-modal/style.css' ), documents );
	fs.writeFileSync( path.join( out, 'blocks/shared/viewer.css' ), shared );
}
console.log( 'ok' );
```

2. `wp-content/themes/devportfolio/blocks/document-modal/block.json`: replace `	"style": "file:./style.css",` with `	"style": [ "devportfolio-viewer", "file:./style.css" ],`. Leave the Collaterals and Videos `block.json` files alone (decision above).
3. `wp-content/themes/devportfolio/functions.php`, in `devportfolio_register_blocks()`: replace
```php
	function devportfolio_register_blocks() {
		register_block_type( __DIR__ . '/blocks/document-categories' );
```
with
```php
	function devportfolio_register_blocks() {
		// Shared viewer rules (DPB-NNN F2): blocks/document-modal/block.json lists this handle first in its `style`.
		wp_register_style(
			'devportfolio-viewer',
			get_theme_file_uri( 'blocks/shared/viewer.css' ),
			array(),
			(string) filemtime( __DIR__ . '/blocks/shared/viewer.css' )
		);

		register_block_type( __DIR__ . '/blocks/document-categories' );
```
4. Same file, in `devportfolio_enqueue_page_styles()`: replace
```php
		// The Collaterals modal draws its book with the Documents viewer rules (its selectors are listed next to the
		// Documents ones in blocks/document-modal/style.css, DPC-012 D5). The Documents modal already loads that file.
		if ( is_post_type_archive( array( 'collateral', 'video' ) ) ) {
			wp_enqueue_style(
				'devportfolio-viewer',
				get_theme_file_uri( 'blocks/document-modal/style.css' ),
				array(),
				(string) filemtime( __DIR__ . '/blocks/document-modal/style.css' )
			);
		}
```
with
```php
		// The Collaterals and Videos modals draw with the shared viewer rules (blocks/shared/viewer.css, DPB-NNN F2;
		// before, they were part of blocks/document-modal/style.css, DPC-012 D5). Enqueued here, late, so the archives
		// keep the cascade order they had; the Documents modal lists the same handle in its block.json `style`.
		if ( is_post_type_archive( array( 'collateral', 'video' ) ) ) {
			wp_enqueue_style(
				'devportfolio-viewer',
				get_theme_file_uri( 'blocks/shared/viewer.css' ),
				array(),
				(string) filemtime( __DIR__ . '/blocks/shared/viewer.css' )
			);
		}
```
5. Point the 40 PHP test reads at the CSS as the Documents page loads it (viewer first, then the Documents rest), so every existing assertion keeps its meaning:
```bash
cd wp-content/themes/devportfolio
grep -rcF "\$this->read( 'blocks/document-modal/style.css' )" tests | awk -F: '{s+=$2} END{print s}'     # 40
perl -pi -e "s/\\\$this->read\\( 'blocks\\/document-modal\\/style\\.css' \\)/( \\\$this->read( 'blocks\\/shared\\/viewer.css' ) . \\\$this->read( 'blocks\\/document-modal\\/style.css' ) )/g" tests/*.php
grep -rcF "( \$this->read( 'blocks/shared/viewer.css' ) . \$this->read( 'blocks/document-modal/style.css' ) )" tests | awk -F: '{s+=$2} END{print s}'   # 40
cd -
```
   The 12 files: `test-collateral-arrows.php` (1), `test-collateral-modal-mobile.php` (3), `test-collateral-viewer.php` (2), `test-document-modal-mobile-details.php` (3), `test-document-modal-mobile-pages.php` (1), `test-document-modal-mobile-viewer.php` (8), `test-document-modal-mobile.php` (1), `test-document-modal.php` (7), `test-document-presentations.php` (3), `test-document-spreadsheets.php` (1), `test-document-viewer.php` (9), `test-video-modal-mobile.php` (1). If the first count is not 40, stop and report.
6. `wp-content/themes/devportfolio/blocks/document-modal/tests/routing.test.mjs`: the two routing media queries are now in the shared file. Replace `	const css = readFileSync( new URL( '../style.css', import.meta.url ), 'utf8' );` (line 31, the first of the two `readFileSync` lines; the second reads `../../video-modal/style.css` and stays) with `	const css = readFileSync( new URL( '../../shared/viewer.css', import.meta.url ), 'utf8' );`.
7. Lint and gates: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio php -l functions.php`; `python3 -m json.tool wp-content/themes/devportfolio/blocks/document-modal/block.json > /dev/null`; `bash <scratchpad>/gates.sh commit-20`: counts as checkpoint 7 (theme T, shared 178, document-modal 17). A failing theme test here means an assertion compares positions across the two files: stop and report it, do not reorder the files.
8. Link order check (exact):
```bash
for p in / /documents/ /collaterals/ /videos/; do echo "== $p"; curl -sk "https://devportfolio.ddev.site$p" | grep -oE "<link rel='stylesheet' id='[^']*'|<style id=\"[^\"]*-inline-css\"" | grep -nE "viewer|modal|page-css|global-styles"; done
```
   `/` and `/documents/`: `devportfolio-viewer-css` directly before `devportfolio-document-modal-style-css` (or its `-inline-css`), at the place the Documents style had before. `/collaterals/` and `/videos/`: `devportfolio-viewer-css` still last, after `devportfolio-page-css`. Nothing else moved.
9. Screenshots (below, "F2 screenshot comparison"): shoot `<scratchpad>/shots/before` on the commit before this one (`git stash` is not needed: shoot before step 1), shoot `<scratchpad>/shots/after` now, compare. Every file must print `0 px differ`, except `*-videos-open.png` where a playing embed can differ: open its `.diff.png` and confirm the difference is inside the video frame only. Anything else: stop and report.

Commit 20: `refactor(theme): move shared viewer rules to blocks/shared/viewer.css` (stage `blocks/shared/viewer.css blocks/document-modal/style.css blocks/document-modal/block.json functions.php blocks/document-modal/tests/routing.test.mjs` and the 12 test files, all under `wp-content/themes/devportfolio/`).

### Commit 21: pin where the rules live (+3 theme tests)

Create `wp-content/themes/devportfolio/tests/test-viewer-css.php`:
```php
<?php
/**
 * Where the viewer rules live (DPB-NNN F2): the rules the three modals share are in blocks/shared/viewer.css,
 * blocks/document-modal/style.css keeps the Documents-only ones.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Viewer_Css extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/' . $relative );
	}

	public function set_up(): void {
		parent::set_up();
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
	}

	public function test_the_documents_stylesheet_keeps_only_documents_rules(): void {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $this->read( 'blocks/document-modal/style.css' ) );

		$this->assertDoesNotMatchRegularExpression( '/\.(?:collaterals|videos)-/', $css );
		$this->assertStringContainsString( '.documents-modal {', $css );
	}

	public function test_the_shared_rules_live_in_blocks_shared_and_load_first_on_documents(): void {
		$css = $this->read( 'blocks/shared/viewer.css' );
		$this->assertStringStartsWith( '/* Viewer rules shared by the Documents, Collaterals and Videos modals', $css );
		$this->assertStringContainsString( ".collaterals-mobile,\n.documents-modal,\n.collaterals-modal,\n.documents-mobile,\n.videos-mobile {", $css );

		$json = json_decode( $this->read( 'blocks/document-modal/block.json' ), true );
		$this->assertSame( array( 'devportfolio-viewer', 'file:./style.css' ), $json['style'] );
	}

	public function test_the_archives_load_the_shared_viewer_file(): void {
		$this->go_to( get_post_type_archive_link( 'video' ) );
		devportfolio_enqueue_page_styles();

		$this->assertTrue( wp_style_is( 'devportfolio-viewer', 'enqueued' ) );
		$this->assertStringEndsWith( '/blocks/shared/viewer.css', wp_styles()->registered['devportfolio-viewer']->src );
		wp_dequeue_style( 'devportfolio-viewer' );
	}
}
```
Lint with `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio php -l tests/test-viewer-css.php`. Gate: theme **T+3**.

Commit 21: `test(theme): pin where the viewer rules live`.

### Commit 22: document it

| File | Find | Replace |
|---|---|---|
| `docs/agents/tests.md` | ``The hover reveal in `document-modal/style.css` must name`` | ``The hover reveal in `shared/viewer.css` (moved from `document-modal/style.css`, DPB-NNN F2) must name`` |
| `docs/agents/collaterals-page.md` | ``Its Documents viewer CSS is the same `blocks/document-modal/style.css` (selectors listed for both prefixes, enqueued on the Collaterals archive as `devportfolio-viewer`).`` | ``Its viewer CSS is `blocks/shared/viewer.css` (handle `devportfolio-viewer`, selectors listed for both prefixes), enqueued late on the Collaterals archive by `devportfolio_enqueue_page_styles()`.`` |
| `docs/agents/videos-page.md` | ``The Videos archive loads `devportfolio-viewer` (`document-modal/style.css`) for the shared sheet shell`` | ``The Videos archive loads `devportfolio-viewer` (`shared/viewer.css`) for the shared sheet shell`` |
| `AGENTS.md` | `theme <T> tests` (in `Expected totals today:`) | `theme <T+3> tests` |

and append to the end of `docs/agents/viewer-shared.md` (one blank line before):
```
**Viewer CSS (DPB-NNN F2):** the rules the three modals share live in `blocks/shared/viewer.css` (handle `devportfolio-viewer`, registered in `devportfolio_register_blocks()`); `blocks/document-modal/style.css` keeps only Documents rules. The Documents block lists `["devportfolio-viewer", "file:./style.css"]` in its `block.json`; the Collaterals and Videos archives enqueue the handle late in `devportfolio_enqueue_page_styles()` and their `block.json` files do not list it, because that keeps the cascade order each page had. A new shared rule goes in `viewer.css`; a rule that must beat a shared rule goes after it in the same file or in a file that loads later on every page that uses it.
```

Commit 22: `docs(agents): document blocks/shared/viewer.css` (stage `AGENTS.md docs/agents/`).

**Checkpoint 8:** `bash <scratchpad>/gates.sh phase-8`: plugin P+4, theme **T+3**, shared 178, other node suites unchanged; the screenshot comparison of commit 20 is recorded in the PR.

## Phase 9: the viewers load on first use, their CSS stops blocking the first paint (F1)

### Design (decided)

**JavaScript.** Each modal block prints one tiny entry, `boot.js`, instead of `view.js`. `view.js` itself does not change, except that it registers its `popstate` handler through the new shared listener. `boot.js` calls `createLazyModal()` (`blocks/shared/lazy-modal.js`), which `import()`s `./view.js` (and so its whole graph) when the first of these happens:

| Trigger | Pages | Why |
|---|---|---|
| a deeplink for this modal in the URL (`?document_slug=`, `?collateral_slug=`, `?video_slug=`) | all | `view.js` then opens it exactly as today (its own top-level deeplink code runs when it loads) |
| `pointerover`, `focusin` or `touchstart` on a trigger (`[data-document-id]` etc.) | all | intent: the download starts 50 to 300 ms before the click |
| a click on a trigger | all | the click is handed to `modal-trigger.js` (`queueOpen()`) and replayed by `view.js`'s own `bindOpen()` once it has run, so push, focus return and the video's "a click starts with sound" rule behave as for any later click |
| the browser is idle after `load` (`requestIdleCallback`, 4 s timeout), unless data saver is on | only where the modal is the page's main action: the shelf (`[data-documents-shelf]`) and the two grids | an archive visitor almost always opens one; Home stays intent-only |
| Back/Forward onto this modal's deeplink before the view loaded | all | the event is stopped (the router must not reload) and the view loads, then opens the slug |

**History order.** A late `view.js` would add its `popstate` listener after anything registered meanwhile, breaking the rule in Facts. New `blocks/shared/history-events.js` owns **one** capture-phase `window` listener, installed when `boot.js` runs (the same moment the old `view.js` listener was added, or earlier: a module's own code runs in document order, and `boot.js` no longer waits for 23 to 32 imports). `view.js` (three files) and `shared/sheet-back.js` register their handlers through it with the same call shape (`historyEvents.addEventListener( 'popstate', handler, true )`); handlers run in the order they were added and `event.stopImmediatePropagation()` in one stops the later ones and every later window listener, as before. Relative order is kept: inside a view, the sheet handler is added (by `createMobileModal()`) before the view's own, as today. Contact's sheet (`contact/view.js`, not lazy) also goes through it, still ahead of any later listener.

**CSS.** The handles `devportfolio-viewer` (Phase 8) and the three modal block styles are printed as `<link rel='preload' as='style' ... onload="this.onload=null;this.rel='stylesheet'">` (filter `style_loader_tag`, `devportfolio_defer_viewer_style()` in `inc/viewer.php`; admin screens and REST requests keep the normal tag). The link stays where WordPress printed it, so the cascade order is unchanged once it applies. Closed dialogs are `display: none`, so nothing visible depends on these files before a dialog opens (Facts). `lazy-modal.js` waits for the links of its modal (`styleReady()`: a `preload` link is switched to `stylesheet` at once, then it waits for `load`, an error or 8 s) **before** it imports `view.js`, so no dialog, including a deeplink on load, ever opens unstyled. A stylesheet that WordPress inlined (`<style id="...-inline-css">`) has no link and counts as ready. Only the viewer files are deferred: `selected-work/style.css` and `contact/style.css` style visible Home sections (contact is the `/#contact` anchor target), so they stay render-blocking (Residual).

### Behaviour changes (unavoidable, kept minimal)

1. **First open on a page waits for the download** when it comes before any intent or idle load (a click with no hover on Home, or a tap on a phone before the page is idle). The trigger gets `aria-busy="true"` meanwhile, and the dialog opens when the view has run. After the first load nothing changes.
2. **`[data-document-id] { cursor: pointer; }`** (in `document-modal/style.css`) applies once that file has loaded (it now loads without blocking), so for the first moments the pointer over a Selected work book or shelf item can be the default arrow.
3. **Back/Forward onto a deeplink before its view loaded** (only reachable while the view is still downloading) opens it as on first load, so closing it afterwards uses `replaceState` instead of `history.back()` (today's `pushed = true` path is skipped). In practice this cannot happen: those history entries are only created by an already loaded view.

### Risks and what must be checked in a real browser

- **Autoplay with sound (videos).** The replayed click runs after an `await`. Chrome keeps the page's user activation (sticky activation), so sound works; Safari uses a short transient activation and may start a slow first open **muted** (the existing fallback "a blocked start falls back to muted" applies). Checked in Chrome by `lazy-check.mjs`; check Safari by hand once (open a Home video from the film strip with a cold cache: it plays, muted or not).
- **Listener order.** Pinned by node tests (order, stop) and PHP text tests; checked in Chrome by the phone Back-closes-sheet flow, with a marker that a reload would wipe.
- **The preload swap needs JS** (inline `onload`). Without JS the viewers cannot open anyway.
- **Cache-busting** is unchanged in practice: `boot.js` carries WordPress's `?ver=`, `view.js` and every shared module are imported by relative URL without one, exactly as the static imports were before.
- **The idle load on archives** still downloads the viewer on every archive visit (about the same bytes as today, but after `load` instead of before `DOMContentLoaded`).

### Measure before (on commit 22) and after (on commit 26)

Save as `<scratchpad>/graph.mjs` (static import graph of theme modules, relative imports only, `import()` ignored):
```js
// Usage (from wp-content/themes/devportfolio): node <scratchpad>/graph.mjs <entry.js> [...]
import fs from 'node:fs';
import path from 'node:path';

const re = /^\s*(?:import|export)\s[^'"]*?from\s*['"]([^'"]+)['"]|^\s*import\s*['"]([^'"]+)['"]/gm;
const all = new Set();
for ( const entry of process.argv.slice( 2 ) ) {
	const seen = new Set();
	const stack = [ path.resolve( entry ) ];
	while ( stack.length ) {
		const file = stack.pop();
		if ( seen.has( file ) ) {
			continue;
		}
		seen.add( file );
		const src = fs.readFileSync( file, 'utf8' );
		let m;
		re.lastIndex = 0;
		while ( ( m = re.exec( src ) ) ) {
			const spec = m[ 1 ] || m[ 2 ];
			if ( spec.startsWith( '.' ) ) {
				stack.push( path.resolve( path.dirname( file ), spec ) );
			}
		}
	}
	seen.forEach( ( f ) => all.add( f ) );
}
let bytes = 0;
all.forEach( ( f ) => { bytes += fs.statSync( f ).size; } );
console.log( `UNION ${ all.size } files ${ bytes } bytes` );
```
Then, with `<label>` = `before` or `after`:
```bash
curl -sk https://devportfolio.ddev.site/ -o <scratchpad>/home-<label>.html
grep -o "<link rel='stylesheet' id='[^']*'" <scratchpad>/home-<label>.html
grep -o "<link rel='preload' as='style' id='[^']*'" <scratchpad>/home-<label>.html
grep -o '<style id="devportfolio-[a-z-]*-inline-css"' <scratchpad>/home-<label>.html
cd wp-content/themes/devportfolio && node <scratchpad>/graph.mjs $(grep -o 'src="https://devportfolio.ddev.site/wp-content/themes/devportfolio/[^"?]*' <scratchpad>/home-<label>.html | sed 's#.*/themes/devportfolio/##') ; cd -
```
Expected **before**: 8 `rel='stylesheet'` ids (navigation, selected-work, document-modal, collateral-modal, video-modal, contact, page, home-motion; after Phase 8 `devportfolio-viewer-css` is a ninth, directly before document-modal), no preload, `UNION 63 files 302426 bytes` (measured on `584977f`: the 62-file, 301,700-byte module graph plus the classic `hero/view.js`, 726 bytes, whose `src` the grep also picks up; Phase 7 trims `mode.js` and `deeplink.js` by a few hundred bytes). Expected **after**: the stylesheet list keeps navigation, selected-work, contact, page and home-motion; `devportfolio-viewer-css`, `devportfolio-document-modal-style-css`, `devportfolio-collateral-modal-style-css` and `devportfolio-video-modal-style-css` each appear either as a preload or as an `-inline-css` style; `UNION 22 files` and about 62,000 bytes (62,069 measured in a dry run before Phase 7's trims). Render-blocking theme block CSS on Home: 143,047 bytes before (selected-work, the three modals, contact), 30,624 after (selected-work, contact). Paste both outputs in the PR.

### Commit 23: the shared listener and the lazy loader (not used yet)

1. Create `wp-content/themes/devportfolio/blocks/shared/history-events.js`:
```js
/**
 * One capture-phase `popstate` listener on window for every viewer module (DPB-NNN F1).
 *
 * Back and Forward must reach the modals and their sheets before anything that is registered later: WordPress's
 * Interactivity Router answers every popstate and reloads the page for a URL it has not cached
 * (`?document_slug=...`). Since the viewers load on first use, their own listeners are added late; this module is
 * imported by each modal's boot.js, so its one window listener is added while the page's scripts run, and every
 * handler added later runs inside it, in the order it was added. A handler that calls
 * `event.stopImmediatePropagation()` stops the handlers after it as well as every later window listener, exactly
 * as when each module had its own listener.
 */

/**
 * @param {EventTarget|null} target Normally `window`; null where there is none (node --test).
 * @return {{install: Function, addEventListener: Function, count: Function}}
 */
export function createHistoryEvents( target ) {
	const handlers = [];
	let installed = false;

	const dispatch = ( event ) => {
		let stopped = false;
		const stop = event.stopImmediatePropagation;
		event.stopImmediatePropagation = function () {
			stopped = true;
			if ( stop ) {
				stop.call( event );
			}
		};
		for ( const handler of handlers.slice() ) {
			handler( event );
			if ( stopped ) {
				return;
			}
		}
	};

	const api = {
		/** Adds the one window listener (once). */
		install() {
			if ( ! installed && target ) {
				target.addEventListener( 'popstate', dispatch, true );
				installed = true;
			}
		},
		/**
		 * Same call shape as `window.addEventListener( 'popstate', handler, true )`; always the capture phase.
		 *
		 * @param {string}   type    Must be 'popstate'.
		 * @param {Function} handler Called with the PopStateEvent.
		 */
		addEventListener( type, handler ) {
			if ( 'popstate' !== type ) {
				throw new Error( 'historyEvents only carries popstate' );
			}
			api.install();
			handlers.push( handler );
		},
		/** Number of handlers (for tests). */
		count: () => handlers.length,
	};
	return api;
}

export const historyEvents = createHistoryEvents( typeof window === 'undefined' ? null : window );
```
2. Create `wp-content/themes/devportfolio/blocks/shared/lazy-modal.js`:
```js
/**
 * Loads a modal's viewer on first use (DPB-NNN F1).
 *
 * Each modal block's boot.js calls createLazyModal(). Nothing heavy is fetched while the page loads: the modal's
 * view.js (and its whole import graph) is imported when a deeplink is in the URL, on the first sign of intent
 * (pointer over, focus or touch on a trigger), on a click, or, on the pages whose main content opens it (the
 * shelf and the grids), when the browser is idle after `load`. Its stylesheets are printed as
 * `<link rel="preload" as="style">` (see devportfolio_defer_viewer_style()), so they never block the first paint;
 * the view is imported only once they apply, so a dialog never opens unstyled.
 *
 * A click that arrives before the view is loaded is handed to modal-trigger.js (queueOpen) and replayed by the
 * view's own bindOpen(), so the dialog, the deeplink push and the focus return behave as for any later click.
 */
import { historyEvents } from './history-events.js';
import { createDeeplink } from './deeplink.js';
import { queueOpen } from './modal-trigger.js';

export const STYLE_TIMEOUT_MS = 8000;
export const IDLE_TIMEOUT_MS = 4000;
export const INTENT_EVENTS = [ 'pointerover', 'focusin', 'touchstart' ];

/**
 * Resolves when the stylesheet `link` applies (or failed, or after the timeout). A missing link (the style was
 * printed inline, or is not on this page) resolves at once. A `preload` link is turned into a stylesheet here, so
 * the wait never depends on the inline onload.
 *
 * @param {HTMLLinkElement|null} link     The `<link>` element.
 * @param {Function}             setTimer setTimeout.
 * @return {Promise<void>}
 */
export function styleReady( link, setTimer = setTimeout ) {
	return new Promise( ( resolve ) => {
		if ( ! link || ( link.rel === 'stylesheet' && link.sheet ) ) {
			resolve();
			return;
		}
		const onLoad = () => {
			if ( link.rel === 'stylesheet' && link.sheet ) {
				link.removeEventListener( 'load', onLoad );
				resolve();
			}
		};
		link.addEventListener( 'load', onLoad );
		link.addEventListener( 'error', () => resolve(), { once: true } );
		setTimer( resolve, STYLE_TIMEOUT_MS );
		if ( link.rel === 'preload' ) {
			link.rel = 'stylesheet';
		}
	} );
}

/**
 * @param {Object}   config
 * @param {string}   config.root      Selector of the modal block's root (nothing happens without it).
 * @param {string}   config.attribute Trigger attribute, e.g. 'data-document-id'.
 * @param {string}   config.param     Deeplink query parameter, e.g. 'document_slug'.
 * @param {string[]} config.styles    Ids of the `<link>` elements the view needs (`<handle>-css`).
 * @param {string}   config.idle      Selector that, when present, makes the view load when the browser is idle.
 * @param {Function} config.load      `() => import( './view.js' )`.
 * @param {Object}   env              Injected for tests: { win, doc, events }.
 * @return {{load: Function, isLoaded: Function}|null}
 */
export function createLazyModal( config, env = {} ) {
	const win = env.win || window;
	const doc = env.doc || document;
	const events = env.events || historyEvents;
	const { root, attribute, param, styles, idle, load } = config;

	events.install();
	if ( ! doc.querySelector( root ) ) {
		return null;
	}

	const deeplink = createDeeplink( param );
	const triggerOf = ( event ) => ( event.target && event.target.closest ? event.target.closest( `[${ attribute }]` ) : null );
	let loading = null;
	let loaded = false;

	const run = () => {
		if ( ! loading ) {
			loading = Promise.all( styles.map( ( id ) => styleReady( doc.getElementById( id ), win.setTimeout ? win.setTimeout.bind( win ) : setTimeout ) ) )
				.then( () => load() )
				.then( () => {
					loaded = true;
				} )
				.catch( ( error ) => {
					// A failed fetch (offline): the next intent or click tries again.
					loading = null;
					throw error;
				} );
		}
		return loading;
	};

	const onIntent = ( event ) => {
		if ( ! loaded && triggerOf( event ) ) {
			run().catch( () => {} );
		}
	};
	INTENT_EVENTS.forEach( ( type ) => doc.addEventListener( type, onIntent, { passive: true } ) );

	doc.addEventListener( 'click', ( event ) => {
		if ( loaded ) {
			return;
		}
		const trigger = triggerOf( event );
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		queueOpen( attribute, trigger );
		trigger.setAttribute( 'aria-busy', 'true' );
		run()
			.catch( () => {} )
			.then( () => trigger.removeAttribute( 'aria-busy' ) );
	} );

	// Back or Forward onto this modal's deeplink before the view is loaded: keep it from the router and load; the
	// view opens the slug in the URL when it runs. Once loaded, the view's own handler (added later) decides.
	events.addEventListener(
		'popstate',
		( event ) => {
			if ( ! loaded && deeplink.slugFromUrl( win.location.href ) ) {
				event.stopImmediatePropagation();
				run().catch( () => {} );
			}
		},
		true
	);

	if ( deeplink.slugFromUrl( win.location.href ) ) {
		run().catch( () => {} );
	} else if ( idle && doc.querySelector( idle ) && ! ( win.navigator && win.navigator.connection && win.navigator.connection.saveData ) ) {
		const whenIdle = () => {
			if ( win.requestIdleCallback ) {
				win.requestIdleCallback( () => run().catch( () => {} ), { timeout: IDLE_TIMEOUT_MS } );
			} else {
				win.setTimeout( () => run().catch( () => {} ), 1 );
			}
		};
		if ( doc.readyState === 'complete' ) {
			whenIdle();
		} else {
			win.addEventListener( 'load', whenIdle, { once: true } );
		}
	}

	return { load: run, isLoaded: () => loaded };
}
```
3. `wp-content/themes/devportfolio/blocks/shared/modal-trigger.js`, three edits (the file has no other content to keep in mind; today it is 21 lines):
   - Insert at the very top of the file (before the line `/**` that starts `* Opens a modal from a click`):
```js
const queued = {};

/**
 * Remembers a click on a trigger that arrived before the modal's view.js was loaded (shared/lazy-modal.js). The
 * next bindOpen() for the same attribute replays it; a later click replaces an earlier one.
 *
 * @param {string}  attribute Attribute name, e.g. 'data-document-id'.
 * @param {Element} trigger   The clicked trigger.
 */
export function queueOpen( attribute, trigger ) {
	queued[ attribute ] = trigger;
}

```
   - Replace
```js
 * @param {EventTarget} scope     Element that receives the clicks (normally `document`).
 * @param {string}      attribute Attribute name, e.g. 'data-document-id'.
 * @param {(value: string, trigger: Element) => void} open Called with the attribute value and the trigger.
 */
export function bindOpen( scope, attribute, open ) {
```
     with
```js
 * A click queued with queueOpen() before the modal's view.js was loaded (DPB-NNN F1) is replayed once, after
 * the calling module has finished running.
 *
 * @param {EventTarget} scope     Element that receives the clicks (normally `document`).
 * @param {string}      attribute Attribute name, e.g. 'data-document-id'.
 * @param {(value: string, trigger: Element) => void} open Called with the attribute value and the trigger.
 * @param {Function}    defer     Runs the replay later (queueMicrotask; injected by tests).
 */
export function bindOpen( scope, attribute, open, defer = queueMicrotask ) {
```
   - Replace the last three lines of the file
```js
		open( trigger.getAttribute( attribute ), trigger );
	} );
}
```
     with
```js
		open( trigger.getAttribute( attribute ), trigger );
	} );

	const queuedTrigger = queued[ attribute ];
	if ( queuedTrigger ) {
		delete queued[ attribute ];
		defer( () => open( queuedTrigger.getAttribute( attribute ), queuedTrigger ) );
	}
}
```
4. Syntax: `cd wp-content/themes/devportfolio && for f in blocks/shared/history-events.js blocks/shared/lazy-modal.js blocks/shared/modal-trigger.js; do node --input-type=module --check < $f || echo "FAIL $f"; done; cd -` prints nothing. Gates: counts as checkpoint 8 (nothing imports the new files yet; the existing `modal-trigger.test.mjs` cases still pass).

Commit 23: `feat(theme): add a shared popstate listener and a lazy modal loader`.

### Commit 24: node tests for them (+14 in `blocks/shared`)

1. Create `wp-content/themes/devportfolio/blocks/shared/tests/history-events.test.mjs` (4 tests):
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHistoryEvents } from '../history-events.js';

// A window stand-in that records its listeners and can fire a popstate at them in order.
function fakeWindow() {
	const listeners = [];
	return {
		listeners,
		addEventListener( type, fn, capture ) {
			listeners.push( { type, fn, capture } );
		},
		fire() {
			const event = { native: 0, stopImmediatePropagation() { this.native++; } };
			for ( const l of listeners ) {
				l.fn( event );
			}
			return event;
		},
	};
}

test( 'one capture-phase window listener, however many handlers', () => {
	const win = fakeWindow();
	const events = createHistoryEvents( win );
	events.install();
	events.addEventListener( 'popstate', () => {} );
	events.addEventListener( 'popstate', () => {}, true );
	assert.equal( win.listeners.length, 1 );
	assert.equal( win.listeners[ 0 ].type, 'popstate' );
	assert.equal( win.listeners[ 0 ].capture, true );
	assert.equal( events.count(), 2 );
} );

test( 'handlers run in the order they were added', () => {
	const win = fakeWindow();
	const events = createHistoryEvents( win );
	const order = [];
	events.addEventListener( 'popstate', () => order.push( 'sheet' ) );
	events.addEventListener( 'popstate', () => order.push( 'modal' ) );
	win.fire();
	assert.deepEqual( order, [ 'sheet', 'modal' ] );
} );

test( 'stopImmediatePropagation stops the later handlers and still stops the native event', () => {
	const win = fakeWindow();
	const events = createHistoryEvents( win );
	const order = [];
	events.addEventListener( 'popstate', ( event ) => {
		order.push( 'first' );
		event.stopImmediatePropagation();
	} );
	events.addEventListener( 'popstate', () => order.push( 'second' ) );
	const event = win.fire();
	assert.deepEqual( order, [ 'first' ] );
	assert.equal( event.native, 1 );
} );

test( 'only popstate is carried, and no target means no window listener', () => {
	const events = createHistoryEvents( null );
	assert.throws( () => events.addEventListener( 'click', () => {} ) );
	events.addEventListener( 'popstate', () => {} );
	assert.equal( events.count(), 1 );
} );
```
2. Create `wp-content/themes/devportfolio/blocks/shared/tests/lazy-modal.test.mjs` (8 tests):
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createLazyModal, styleReady, INTENT_EVENTS } from '../lazy-modal.js';
import { createHistoryEvents } from '../history-events.js';

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

function fakeLink( rel, sheet = null ) {
	const listeners = {};
	return {
		rel,
		sheet,
		addEventListener( type, fn ) {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
		removeEventListener( type, fn ) {
			listeners[ type ] = ( listeners[ type ] || [] ).filter( ( x ) => x !== fn );
		},
		fire( type ) {
			( listeners[ type ] || [] ).slice().forEach( ( fn ) => fn() );
		},
	};
}

function trigger( attribute ) {
	const attrs = { [ attribute ]: '7' };
	const el = {
		attrs,
		getAttribute: ( name ) => attrs[ name ] ?? null,
		setAttribute: ( name, value ) => {
			attrs[ name ] = value;
		},
		removeAttribute: ( name ) => {
			delete attrs[ name ];
		},
		closest: ( selector ) => ( selector === `[${ attribute }]` ? el : null ),
	};
	return el;
}

function setup( { href = 'https://example.test/', root = true, idleTarget = false, saveData = false, readyState = 'complete' } = {} ) {
	const listeners = [];
	const winListeners = [];
	const idleCalls = [];
	const doc = {
		readyState,
		querySelector: ( selector ) => {
			if ( selector === '.root' ) {
				return root ? {} : null;
			}
			return selector === '[data-shelf]' && idleTarget ? {} : null;
		},
		getElementById: () => null,
		addEventListener: ( type, fn ) => listeners.push( { type, fn } ),
	};
	const win = {
		location: { href },
		navigator: { connection: { saveData } },
		setTimeout,
		requestIdleCallback: ( fn ) => idleCalls.push( fn ),
		addEventListener: ( type, fn ) => winListeners.push( { type, fn } ),
	};
	const popWin = { listeners: [], addEventListener( type, fn ) { this.listeners.push( fn ); } };
	const events = createHistoryEvents( popWin );
	let loads = 0;
	const lazy = createLazyModal(
		{ root: '.root', attribute: 'data-thing-id', param: 'thing_slug', styles: [ 'a-css' ], idle: '[data-shelf]', load: async () => { loads++; } },
		{ win, doc, events }
	);
	const fire = ( type, event ) => listeners.filter( ( l ) => l.type === type ).forEach( ( l ) => l.fn( event ) );
	const pop = () => {
		const event = { stopped: false, stopImmediatePropagation() { this.stopped = true; } };
		popWin.listeners.forEach( ( fn ) => fn( event ) );
		return event;
	};
	return { lazy, listeners, winListeners, idleCalls, fire, pop, win, loads: () => loads };
}

test( 'styleReady resolves at once without a link and for an applied stylesheet', async () => {
	await styleReady( null );
	await styleReady( fakeLink( 'stylesheet', {} ) );
} );

test( 'styleReady turns a preload link into a stylesheet and waits for it to apply', async () => {
	const link = fakeLink( 'preload' );
	let done = false;
	const ready = styleReady( link, () => {} ).then( () => {
		done = true;
	} );
	assert.equal( link.rel, 'stylesheet' );
	await tick();
	assert.equal( done, false );
	link.sheet = {};
	link.fire( 'load' );
	await ready;
	assert.equal( done, true );
} );

test( 'without the modal root nothing is bound', () => {
	const { lazy, listeners } = setup( { root: false } );
	assert.equal( lazy, null );
	assert.equal( listeners.length, 0 );
} );

test( 'a deeplink in the URL loads the view at once', async () => {
	const { loads } = setup( { href: 'https://example.test/?thing_slug=hello' } );
	await tick();
	assert.equal( loads(), 1 );
} );

test( 'without a deeplink the view loads on the first intent, once', async () => {
	const { fire, loads } = setup();
	await tick();
	assert.equal( loads(), 0 );
	fire( INTENT_EVENTS[ 0 ], { target: { closest: () => null } } );
	await tick();
	assert.equal( loads(), 0, 'not over a trigger' );
	const t = trigger( 'data-thing-id' );
	fire( 'pointerover', { target: t } );
	fire( 'focusin', { target: t } );
	await tick();
	assert.equal( loads(), 1 );
} );

test( 'a click before the view is loaded is kept and loads it; after that the boot leaves clicks alone', async () => {
	const { fire, loads, lazy } = setup();
	const t = trigger( 'data-thing-id' );
	const first = { target: t, prevented: false, preventDefault() { this.prevented = true; } };
	fire( 'click', first );
	assert.equal( first.prevented, true );
	assert.equal( t.attrs[ 'aria-busy' ], 'true' );
	await tick();
	assert.equal( loads(), 1 );
	assert.equal( lazy.isLoaded(), true );
	assert.equal( t.attrs[ 'aria-busy' ], undefined );
	const second = { target: t, prevented: false, preventDefault() { this.prevented = true; } };
	fire( 'click', second );
	assert.equal( second.prevented, false, 'the view handles it' );
} );

test( 'idle loading only on a page with the idle target and without data saver', async () => {
	assert.equal( setup( { idleTarget: true } ).idleCalls.length, 1 );
	assert.equal( setup( { idleTarget: false } ).idleCalls.length, 0 );
	assert.equal( setup( { idleTarget: true, saveData: true } ).idleCalls.length, 0 );
	const waiting = setup( { idleTarget: true, readyState: 'interactive' } );
	assert.equal( waiting.idleCalls.length, 0 );
	assert.equal( waiting.winListeners[ 0 ].type, 'load' );
} );

test( 'Back or Forward onto the deeplink before loading is stopped and loads the view', async () => {
	const s = setup();
	s.win.location.href = 'https://example.test/?thing_slug=hello';
	const event = s.pop();
	assert.equal( event.stopped, true );
	await tick();
	assert.equal( s.loads(), 1 );
	s.win.location.href = 'https://example.test/';
	assert.equal( s.pop().stopped, false, 'loaded: the view decides' );
} );
```
3. `wp-content/themes/devportfolio/blocks/shared/tests/modal-trigger.test.mjs` (+2): replace `import { bindOpen } from '../modal-trigger.js';` with `import { bindOpen, queueOpen } from '../modal-trigger.js';` and append at the end of the file:
```js

test( 'a click queued before the view loaded is replayed once, after the caller finished (DPB-NNN F1)', () => {
	const calls = [];
	const later = [];
	const button = element( { 'data-video-id': '5' } );
	queueOpen( 'data-video-id', button );
	bindOpen( fakeScope(), 'data-video-id', ( id, trigger ) => calls.push( [ id, trigger ] ), ( fn ) => later.push( fn ) );
	assert.deepEqual( calls, [], 'not during bindOpen' );
	later.forEach( ( fn ) => fn() );
	assert.deepEqual( calls, [ [ '5', button ] ] );
	bindOpen( fakeScope(), 'data-video-id', ( id ) => calls.push( [ id ] ), ( fn ) => fn() );
	assert.equal( calls.length, 1, 'replayed only once' );
} );

test( 'a queued click replays only for its own attribute', () => {
	const calls = [];
	queueOpen( 'data-collateral-id', element( { 'data-collateral-id': '3' } ) );
	bindOpen( fakeScope(), 'data-document-id', ( id ) => calls.push( [ 'document', id ] ), ( fn ) => fn() );
	bindOpen( fakeScope(), 'data-collateral-id', ( id ) => calls.push( [ 'collateral', id ] ), ( fn ) => fn() );
	assert.deepEqual( calls, [ [ 'collateral', '3' ] ] );
} );
```
4. Gate: `blocks/shared` **192** (178 + 4 + 8 + 2), every other suite unchanged. (Dry run on a scratch copy of `584977f` plus these files: 193 = 179 + 14, all passing.)

Commit 24: `test(theme): cover the shared popstate listener and the lazy loader`.

### Commit 25: load the three viewers on first use

1. Create the three entries (tabs):

`wp-content/themes/devportfolio/blocks/document-modal/boot.js`
```js
/**
 * Documents modal entry (DPB-NNN F1): the only script this block prints. It loads view.js on first use; see
 * shared/lazy-modal.js for when that is.
 */
import { createLazyModal } from '../shared/lazy-modal.js';

createLazyModal( {
	root: '.documents-modal-root',
	attribute: 'data-document-id',
	param: 'document_slug',
	styles: [ 'devportfolio-viewer-css', 'devportfolio-document-modal-style-css' ],
	idle: '[data-documents-shelf]',
	load: () => import( './view.js' ),
} );
```
`wp-content/themes/devportfolio/blocks/collateral-modal/boot.js`
```js
/**
 * Brand Collaterals modal entry (DPB-NNN F1): the only script this block prints. It loads view.js on first use; see
 * shared/lazy-modal.js for when that is.
 */
import { createLazyModal } from '../shared/lazy-modal.js';

createLazyModal( {
	root: '.collaterals-modal-root',
	attribute: 'data-collateral-id',
	param: 'collateral_slug',
	styles: [ 'devportfolio-viewer-css', 'devportfolio-collateral-modal-style-css' ],
	idle: '[data-collaterals-grid]',
	load: () => import( './view.js' ),
} );
```
`wp-content/themes/devportfolio/blocks/video-modal/boot.js`
```js
/**
 * Videos modal entry (DPB-NNN F1): the only script this block prints. It loads view.js on first use; see
 * shared/lazy-modal.js for when that is.
 */
import { createLazyModal } from '../shared/lazy-modal.js';

createLazyModal( {
	root: '.videos-modal-root',
	attribute: 'data-video-id',
	param: 'video_slug',
	styles: [ 'devportfolio-viewer-css', 'devportfolio-video-modal-style-css' ],
	idle: '[data-videos-grid]',
	load: () => import( './view.js' ),
} );
```
2. In each of `blocks/document-modal/block.json`, `blocks/collateral-modal/block.json`, `blocks/video-modal/block.json` replace `	"viewScriptModule": "file:./view.js"` with `	"viewScriptModule": "file:./boot.js"`.
3. Register the four popstate handlers through the shared listener (each find string occurs once; from `wp-content/themes/devportfolio/blocks`):

| File | Find | Replace |
|---|---|---|
| `document-modal/view.js` | `import { applyDownload, guardDisabledDownload } from '../shared/download.js';` | that line, then a new line `import { historyEvents } from '../shared/history-events.js';` |
| `document-modal/view.js` | `	window.addEventListener( 'popstate', ( event ) => {` | `	historyEvents.addEventListener( 'popstate', ( event ) => {` |
| `collateral-modal/view.js` | `import { linkFor, copyText } from './copy-link.js';` | that line, then `import { historyEvents } from '../shared/history-events.js';` |
| `collateral-modal/view.js` | `	window.addEventListener(` followed on the next line by `		'popstate',` | `	historyEvents.addEventListener(` (the `'popstate',` line stays) |
| `video-modal/view.js` | `import { frameOf, flyTo, flightDuration, FLIGHT_EASE } from '../shared/flight.js';` | that line, then `import { historyEvents } from '../shared/history-events.js';` |
| `video-modal/view.js` | `	window.addEventListener(` followed by `		'popstate',` | `	historyEvents.addEventListener(` |
| `shared/sheet-back.js` | `import { withSheetState, hasSheetState } from './deeplink.js';` | that line, then `import { historyEvents } from './history-events.js';` |
| `shared/sheet-back.js` | `	window.addEventListener(` followed by `		'popstate',` | `	historyEvents.addEventListener(` |

   Checks (from `wp-content/themes/devportfolio/blocks`): `grep -c historyEvents document-modal/view.js collateral-modal/view.js video-modal/view.js shared/sheet-back.js` prints `2` for each file; `grep -n "window.addEventListener( 'popstate'" document-modal/view.js` prints nothing; `grep -n -A1 "window.addEventListener($" collateral-modal/view.js video-modal/view.js shared/sheet-back.js | grep popstate` prints nothing.
4. The tests that pinned the old call (same commit, or the suites go red):
   - `tests/test-collateral-modal-mobile.php` (lines 239 and 242): `"window.addEventListener(\n\t\t'popstate',"` -> `"historyEvents.addEventListener(\n\t\t'popstate',"` and `"window.addEventListener(\n\t\t'popstate'"` -> `"historyEvents.addEventListener(\n\t\t'popstate'"`. The assertion that it is registered before `routing.onChange( swapDialogs );` stays: it is still added while `view.js` runs, and the shared listener it goes through was added when `boot.js` ran.
   - `tests/test-document-viewer.php` (line 590): `$this->assertSame( 'file:./view.js', $block['viewScriptModule'] );` -> `$this->assertSame( 'file:./boot.js', $block['viewScriptModule'] );`.
   - `blocks/shared/tests/sheet-controller.test.mjs`: replace `const { createSheetController } = await import( '../sheet-controller.js' );` with that line followed by `const { historyEvents } = await import( '../history-events.js' );`, and replace the test
```js
test( 'the history listener is registered when the controller is created', () => {
	const before = calls.listeners.length;
	setup();
	assert.equal( calls.listeners.length, before + 1 );
	assert.equal( calls.listeners[ calls.listeners.length - 1 ][ 0 ], 'popstate' );
} );
```
     with
```js
test( 'the history listener is registered when the controller is created', () => {
	const before = historyEvents.count();
	setup();
	assert.equal( historyEvents.count(), before + 1 );
	assert.equal( calls.listeners.filter( ( l ) => l[ 0 ] === 'popstate' ).length, 1, 'one window listener carries every handler' );
} );
```
   - Unchanged and still meaningful (they read `view.js`/`sheet-back.js` text that keeps its shape): `test-collateral-modal.php:570` (`addEventListener\(\s*'popstate'`, capture `true`), `test-document-modal.php:413` (`addEventListener( 'popstate'`), `test-document-modal-mobile-pages.php:322-326` (the sheet's capture registration), `test-video-modal-mobile.php:310-311` (one `'popstate'`, capture `true`), `test-video-modal.php:368` (`bindOpen` import), `test-contact-block.php:231`. Every other theme test that reads a modal `view.js` reads code that did not change. Commit 27 adds the tests for the new entry and listener.
5. Syntax: `cd wp-content/themes/devportfolio && for f in blocks/*-modal/boot.js blocks/*-modal/view.js blocks/shared/sheet-back.js; do node --input-type=module --check < $f || echo "FAIL $f"; done; for b in document-modal collateral-modal video-modal; do python3 -m json.tool blocks/$b/block.json > /dev/null || echo "FAIL $b"; done; cd -` prints nothing.
6. Gates: plugin P+4, theme T+3, shared 192, others unchanged. Then the browser check (below) steps 1 to 6 on this commit: the CSS is still render-blocking here, so every dialog check must pass already.

Commit 25: `feat(theme): load the three viewers on first use` (stage the three `boot.js`, the three `block.json`, the three `view.js`, `blocks/shared/sheet-back.js`, `blocks/shared/tests/sheet-controller.test.mjs`, `tests/test-collateral-modal-mobile.php`, `tests/test-document-viewer.php`).

### Commit 26: print the viewer stylesheets as preloads

Only after commit 25: `boot.js` waits for these links, so a dialog can never open before its CSS applies. Append to the end of `wp-content/themes/devportfolio/inc/viewer.php` (the file ends with `endif;` and a newline; add one blank line, then):
```php
if ( ! function_exists( 'devportfolio_deferred_style_handles' ) ) :
	/**
	 * Style handles only an open viewer dialog needs (DPB-NNN F1). They are printed as a preload, so they never
	 * block the first paint; blocks/shared/lazy-modal.js waits for them before a dialog opens.
	 *
	 * @return string[]
	 */
	function devportfolio_deferred_style_handles() {
		return array( 'devportfolio-viewer', 'devportfolio-document-modal-style', 'devportfolio-collateral-modal-style', 'devportfolio-video-modal-style' );
	}
endif;

if ( ! function_exists( 'devportfolio_defer_viewer_style' ) ) :
	/**
	 * Prints a viewer stylesheet as `<link rel="preload" as="style">` that turns itself into a stylesheet once it
	 * has loaded. Admin screens and REST requests (the editor) keep the plain stylesheet.
	 *
	 * @param string $tag    The `<link>` tag WordPress built.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Media attribute.
	 * @return string
	 */
	function devportfolio_defer_viewer_style( $tag, $handle, $href, $media ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! in_array( $handle, devportfolio_deferred_style_handles(), true ) ) {
			return $tag;
		}

		return sprintf(
			"<link rel='preload' as='style' id='%s' href='%s' media='%s' onload=\"this.onload=null;this.rel='stylesheet'\" />\n",
			esc_attr( $handle . '-css' ),
			esc_url( $href ),
			esc_attr( $media ? $media : 'all' )
		);
	}
endif;
add_filter( 'style_loader_tag', 'devportfolio_defer_viewer_style', 10, 4 );
```
Lint `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio php -l inc/viewer.php`, run the gates (counts as commit 25), take the **after** measurement, then the browser check again, all steps. Open a Document edit screen and the Site Editor once: the editor still shows the blocks styled (it gets the normal tag).

Commit 26: `feat(theme): print the viewer stylesheets as preloads`.

### Commit 27: theme tests (+6)

Create `wp-content/themes/devportfolio/tests/test-lazy-viewers.php` (six tests, no data provider):
```php
<?php
/**
 * The three viewers load on first use (DPB-NNN F1): boot.js entries, the shared popstate listener and the
 * preloaded viewer stylesheets.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Lazy_Viewers extends WP_UnitTestCase {

	const BLOCKS = array( 'document-modal', 'collateral-modal', 'video-modal' );

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/' . $relative );
	}

	public function set_up(): void {
		parent::set_up();
		require_once $this->theme_dir() . '/functions.php';
	}

	public function test_each_modal_block_prints_only_its_boot_script(): void {
		foreach ( self::BLOCKS as $block ) {
			$json = json_decode( $this->read( 'blocks/' . $block . '/block.json' ), true );
			$this->assertSame( 'file:./boot.js', $json['viewScriptModule'], $block );
		}
	}

	public function test_each_boot_imports_the_lazy_loader_and_the_view_only_on_demand(): void {
		$handles = devportfolio_deferred_style_handles();
		foreach ( self::BLOCKS as $block ) {
			$boot = $this->read( 'blocks/' . $block . '/boot.js' );
			$this->assertSame( 1, preg_match_all( '/^import /m', $boot ), $block . ': one static import' );
			$this->assertStringContainsString( "from '../shared/lazy-modal.js'", $boot, $block );
			$this->assertStringContainsString( "load: () => import( './view.js' )", $boot, $block );
			$this->assertStringContainsString( "'devportfolio-viewer-css', 'devportfolio-" . $block . "-style-css'", $boot, $block );
			$this->assertContains( 'devportfolio-' . $block . '-style', $handles, $block );
		}
		$this->assertContains( 'devportfolio-viewer', $handles );
	}

	public function test_back_and_forward_reach_every_viewer_through_one_early_listener(): void {
		$hub = $this->read( 'blocks/shared/history-events.js' );
		$this->assertStringContainsString( "target.addEventListener( 'popstate', dispatch, true );", $hub );
		$this->assertStringContainsString( "import { historyEvents } from './history-events.js';", $this->read( 'blocks/shared/lazy-modal.js' ) );
		$this->assertStringContainsString( 'events.install();', $this->read( 'blocks/shared/lazy-modal.js' ) );

		foreach ( array( 'blocks/document-modal/view.js', 'blocks/collateral-modal/view.js', 'blocks/video-modal/view.js', 'blocks/shared/sheet-back.js' ) as $file ) {
			$js = $this->read( $file );
			$this->assertMatchesRegularExpression( "/historyEvents\\.addEventListener\\(\\s*'popstate'/", $js, $file );
			$this->assertDoesNotMatchRegularExpression( "/window\\.addEventListener\\(\\s*'popstate'/", $js, $file );
		}
	}

	public function test_viewer_styles_are_printed_as_a_preload_that_applies_itself(): void {
		$tag = devportfolio_defer_viewer_style(
			"<link rel='stylesheet' id='devportfolio-document-modal-style-css' href='https://example.org/a.css?ver=1' media='all' />\n",
			'devportfolio-document-modal-style',
			'https://example.org/a.css?ver=1',
			'all'
		);

		$this->assertStringContainsString( "<link rel='preload' as='style' id='devportfolio-document-modal-style-css'", $tag );
		$this->assertStringContainsString( "href='https://example.org/a.css?ver=1'", $tag );
		$this->assertStringContainsString( "onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
		$this->assertStringNotContainsString( "rel='stylesheet' id=", $tag );
		$this->assertStringContainsString( "add_filter( 'style_loader_tag', 'devportfolio_defer_viewer_style', 10, 4 );", $this->read( 'inc/viewer.php' ) );
	}

	public function test_other_styles_are_left_alone(): void {
		$tag = "<link rel='stylesheet' id='devportfolio-contact-style-css' href='https://example.org/c.css' media='all' />\n";
		$this->assertSame( $tag, devportfolio_defer_viewer_style( $tag, 'devportfolio-contact-style', 'https://example.org/c.css', 'all' ) );
	}

	public function test_admin_screens_keep_the_plain_stylesheet(): void {
		$tag = "<link rel='stylesheet' id='devportfolio-viewer-css' href='https://example.org/v.css' media='all' />\n";
		set_current_screen( 'edit-post' );
		$this->assertSame( $tag, devportfolio_defer_viewer_style( $tag, 'devportfolio-viewer', 'https://example.org/v.css', 'all' ) );
		set_current_screen( 'front' );
	}
}
```
Lint, then gates: theme **T+9**.

Commit 27: `test(theme): cover the lazy viewers and the preloaded styles`.

### Commit 28: document it

1. Append to the end of `docs/agents/viewer-shared.md` (one blank line before):
```
**Viewers load on first use (DPB-NNN F1):** each modal block's `viewScriptModule` is `boot.js`, which calls `createLazyModal()` (`blocks/shared/lazy-modal.js`); the modal's `view.js` is `import()`ed on a deeplink in the URL, on intent (`pointerover`, `focusin`, `touchstart` on a trigger), on a click (queued with `queueOpen()` in `modal-trigger.js` and replayed by the view's `bindOpen()`), or when idle after `load` on the archives (`idle` selector: shelf or grid; not with data saver). It waits first for the modal's stylesheets, which `devportfolio_defer_viewer_style()` (`inc/viewer.php`, handles in `devportfolio_deferred_style_handles()`) prints as `<link rel="preload" as="style">`. Every viewer `popstate` handler goes through `blocks/shared/history-events.js` (`historyEvents.addEventListener( 'popstate', handler, true )`), whose one capture listener on `window` is added when `boot.js` runs; never add a viewer `popstate` listener with `window.addEventListener`. A new modal gets a `boot.js` like the three existing ones and its style handle in `devportfolio_deferred_style_handles()`. Tests: `tests/test-lazy-viewers.php`, `blocks/shared/tests/{history-events,lazy-modal,modal-trigger}.test.mjs`.
```
2. In `docs/agents/documents-page.md`, after ``do not move them into `DOMContentLoaded` or a lazy import.`` (occurs once) insert: `` Since DPB-NNN F1 the modal and sheet handlers are added through `blocks/shared/history-events.js`, whose one window listener is added when the modal's `boot.js` runs, so `view.js` itself may load late. `` (with one leading space).
3. `AGENTS.md`, `Expected totals today:` line: `theme <T+3> tests` -> `theme <T+9> tests` and `shared JS 178 tests` -> `shared JS 192 tests`.

Commit 28: `docs(agents): document the lazy viewers` (stage `AGENTS.md docs/agents/`).

**Checkpoint 9:** `bash <scratchpad>/gates.sh phase-9`: plugin P+4 (S skipped), theme **T+9**, shared **192**, collateral-grid 18, collateral-modal 24, contact 20, document-modal 17, selected-work 14, testimonials 5, video-grid 17, video-modal 49; the before/after measurement and both browser-check runs are in the PR.

## Browser checks (headless Chrome with puppeteer-core, as the repo's other tickets did; nothing goes in the repo)

Setup once: `npm install --prefix <scratchpad>/shots puppeteer-core@23.11.1 pngjs@7.0.0 pixelmatch@5.3.0`, then save the two scripts below in `<scratchpad>/shots/`. They use the installed Google Chrome (`/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`). Slugs: `SLUGS="{\"document\":\"$(ddev wp post list --post_type=document --post_status=publish --orderby=menu_order --order=ASC --field=post_name --posts_per_page=1)\",\"collateral\":\"$(ddev wp post list --post_type=collateral --post_status=publish --orderby=menu_order --order=ASC --field=post_name --posts_per_page=1)\",\"video\":\"$(ddev wp post list --post_type=video --post_status=publish --orderby=menu_order --order=ASC --field=post_name --posts_per_page=1)\"}"`; pick a document with 2 or more pages if the first one has one (the phone check opens its Pages sheet). Neither script was run while writing this plan: if one fails for a reason that is not the code under test (a selector that does not exist on this content, a timeout), fix the script, not the theme, and say so in the PR.

### F2 screenshot comparison (Phase 8, commit 20)

Shoots `/`, `/documents/`, `/videos/`, `/collaterals/` and the three deeplinked dialogs at 390x844 (touch, 2x) and 1280x800, with reduced motion (stops the Selected work rotation, the Home reveal and the dialog animations) and every `<video>` paused at 0. `node <scratchpad>/shots/shots.mjs shoot <scratchpad>/shots/before` before the move, `... shoot <scratchpad>/shots/after` after it, `node <scratchpad>/shots/shots.mjs compare <scratchpad>/shots/before <scratchpad>/shots/after`.

`<scratchpad>/shots/shots.mjs`:
```js
// DPB-NNN Phase 8 (F2) screenshot comparison. Not part of the repo.
// Setup once:  npm install --prefix <scratchpad>/shots puppeteer-core@23.11.1 pngjs@7.0.0 pixelmatch@5.3.0
// Shoot:       SLUGS='{"document":"<slug>","collateral":"<slug>","video":"<slug>"}' node <scratchpad>/shots/shots.mjs shoot <dir>
// Compare:     node <scratchpad>/shots/shots.mjs compare <beforeDir> <afterDir>
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const [ mode, a, b ] = process.argv.slice( 2 );
const BASE = 'https://devportfolio.ddev.site';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const SIZES = {
	phone: { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
	desktop: { width: 1280, height: 800, deviceScaleFactor: 1 },
};

async function shoot( dir ) {
	const puppeteer = require( 'puppeteer-core' );
	const slugs = JSON.parse( process.env.SLUGS || '{}' );
	const pages = {
		home: '/',
		documents: '/documents/',
		videos: '/videos/',
		collaterals: '/collaterals/',
		'documents-open': `/documents/?document_slug=${ slugs.document }`,
		'collaterals-open': `/collaterals/?collateral_slug=${ slugs.collateral }`,
		'videos-open': `/videos/?video_slug=${ slugs.video }`,
	};
	fs.mkdirSync( dir, { recursive: true } );
	const browser = await puppeteer.launch( { executablePath: CHROME, headless: true, args: [ '--ignore-certificate-errors', '--autoplay-policy=user-gesture-required' ] } );
	for ( const [ size, viewport ] of Object.entries( SIZES ) ) {
		for ( const [ name, url ] of Object.entries( pages ) ) {
			const page = await browser.newPage();
			await page.setViewport( viewport );
			// Reduced motion stops the Selected work rotation, the Home reveal and the modal animations.
			await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'reduce' } ] );
			await page.goto( BASE + url, { waitUntil: 'networkidle0', timeout: 60000 } );
			await page.evaluate( () => document.querySelectorAll( 'video' ).forEach( ( v ) => { v.pause(); v.currentTime = 0; } ) );
			await new Promise( ( r ) => setTimeout( r, 1500 ) );
			await page.screenshot( { path: path.join( dir, `${ size }-${ name }.png` ), fullPage: ! name.endsWith( '-open' ) } );
			await page.close();
		}
	}
	await browser.close();
}

function compare( before, after ) {
	const { PNG } = require( 'pngjs' );
	const pixelmatch = require( 'pixelmatch' );
	let bad = 0;
	for ( const file of fs.readdirSync( before ).filter( ( f ) => f.endsWith( '.png' ) && ! f.includes( '.diff' ) ) ) {
		const x = PNG.sync.read( fs.readFileSync( path.join( before, file ) ) );
		const y = PNG.sync.read( fs.readFileSync( path.join( after, file ) ) );
		if ( x.width !== y.width || x.height !== y.height ) {
			console.log( `${ file }: size ${ x.width }x${ x.height } -> ${ y.width }x${ y.height }` );
			bad++;
			continue;
		}
		const diff = new PNG( { width: x.width, height: x.height } );
		const n = pixelmatch( x.data, y.data, diff.data, x.width, x.height, { threshold: 0.1 } );
		if ( n ) {
			fs.writeFileSync( path.join( after, file.replace( '.png', '.diff.png' ) ), PNG.sync.write( diff ) );
			bad++;
		}
		console.log( `${ file }: ${ n } px differ` );
	}
	console.log( bad ? `${ bad } screenshot(s) differ: look at the .diff.png files` : 'identical' );
	process.exit( bad ? 1 : 0 );
}

if ( mode === 'shoot' ) {
	await shoot( a );
} else if ( mode === 'compare' ) {
	compare( a, b );
} else {
	console.error( 'usage: shots.mjs shoot <dir> | compare <before> <after>' );
	process.exit( 2 );
}
```

### F1 check (Phase 9, after commit 25 and again after commit 26)

`SLUGS=... node <scratchpad>/shots/lazy-check.mjs` prints one `PASS`/`FAIL` line per check and `all checks passed`. What it checks: (1) Home requests no modal `view.js` without interaction; (2) a click on a Selected work book with no hover opens the Documents dialog, styled (the theme's `box-sizing` and the `--dm-ink` token, which a bare `<dialog>` has neither of), pushes `?document_slug=`, and Back closes it without a reload; (3) each deeplink (`/?document_slug=`, `/documents/?document_slug=`, `/collaterals/?collateral_slug=`, `/videos/?video_slug=`) opens on load, styled; (4) each archive loads its viewer when idle without interaction; (5) on a 390x844 touch screen a tap opens the mobile viewer, Back closes only the Pages sheet, the next Back closes the viewer, no reload; (6) a click on the Home film strip opens the video and an uploaded file starts with sound. By hand, once: Safari (cold cache) open a Home video from the film strip (plays, muted is acceptable on a slow first open), and the Document edit screen and Site Editor still style the blocks.

`<scratchpad>/shots/lazy-check.mjs`:
```js
// DPB-NNN Phase 9 (F1) browser check in headless Chrome. Not part of the repo.
// Setup: the same npm install as shots.mjs.
// Run:   SLUGS='{"document":"<slug>","collateral":"<slug>","video":"<slug>"}' node <scratchpad>/shots/lazy-check.mjs
// Prints one PASS/FAIL line per check; exit 1 when any failed.
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const puppeteer = require( 'puppeteer-core' );
const BASE = 'https://devportfolio.ddev.site';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const slugs = JSON.parse( process.env.SLUGS || '{}' );
const VIEW = /\/blocks\/(document|collateral|video)-modal\/view\.js/;
let failed = 0;
const check = ( name, ok, extra = '' ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ name }${ extra ? ' (' + extra + ')' : '' }` );
	failed += ok ? 0 : 1;
};

const browser = await puppeteer.launch( { executablePath: CHROME, headless: true, args: [ '--ignore-certificate-errors' ] } );

async function open( url, viewport = { width: 1280, height: 800 } ) {
	const page = await browser.newPage();
	await page.setViewport( viewport );
	const requested = [];
	page.on( 'request', ( r ) => requested.push( r.url() ) );
	await page.goto( BASE + url, { waitUntil: 'load', timeout: 60000 } );
	// A marker that a reload would wipe.
	await page.evaluate( () => { window.__noReload = true; } );
	return { page, requested };
}
const views = ( requested ) => requested.filter( ( u ) => VIEW.test( u ) ).length;
const settle = ( ms = 800 ) => new Promise( ( r ) => setTimeout( r, ms ) );

// 1. Home: no viewer module and no blocking modal stylesheet at load.
{
	const { page, requested } = await open( '/' );
	await settle( 3000 );
	check( 'home loads no viewer view.js without interaction', views( requested ) === 0, `${ views( requested ) } requested` );

	// 2. A click on a Selected work book with no hover first: the documents dialog opens, styled, with the deeplink.
	await page.evaluate( () => [ ...document.querySelectorAll( '.work-docs__open[data-document-id]' ) ].find( ( b ) => ! b.closest( '[inert]' ) ).click() );
	await page.waitForFunction( () => document.querySelector( '.documents-modal' )?.open, { timeout: 15000 } ).catch( () => {} );
	const state = await page.evaluate( () => {
		const d = document.querySelector( '.documents-modal' );
		return { open: !! d?.open, radius: d ? getComputedStyle( d ).borderTopLeftRadius : '', url: location.search };
	} );
	check( 'first click opens the documents dialog', state.open );
	check( 'the dialog is styled when it opens', state.radius === '14px', state.radius );
	check( 'the click pushed ?document_slug=', state.url.includes( 'document_slug=' ), state.url );
	await page.goBack();
	await settle();
	const after = await page.evaluate( () => ( { open: !! document.querySelector( '.documents-modal' )?.open, marker: window.__noReload === true } ) );
	check( 'Back closes it without a reload', ! after.open && after.marker );
	await page.close();
}

// 3. Deeplinks open on load, styled, on Home and on each archive.
// "Styled" = the theme's box-sizing and the token of the file that must have applied (a bare <dialog> has neither).
for ( const [ url, selector, token ] of [
	[ `/?document_slug=${ slugs.document }`, '.documents-modal', '--dm-ink' ],
	[ `/documents/?document_slug=${ slugs.document }`, '.documents-modal', '--dm-ink' ],
	[ `/collaterals/?collateral_slug=${ slugs.collateral }`, '.collaterals-modal', '--dm-ink' ],
	[ `/videos/?video_slug=${ slugs.video }`, '.videos-modal', '--vm-side' ],
] ) {
	const { page } = await open( url );
	const ok = await page.waitForFunction( ( s ) => document.querySelector( s )?.open, { timeout: 15000 }, selector ).then( () => true, () => false );
	const style = await page.evaluate( ( s, t ) => {
		const cs = getComputedStyle( document.querySelector( s ) );
		return { box: cs.boxSizing, token: cs.getPropertyValue( t ).trim() };
	}, selector, token );
	check( `deeplink ${ url } opens on load`, ok );
	check( `deeplink ${ url } is styled`, style.box === 'border-box' && style.token !== '', JSON.stringify( style ) );
	await page.close();
}

// 4. The archives load their viewer when idle, without interaction.
for ( const url of [ '/documents/', '/collaterals/', '/videos/' ] ) {
	const { requested, page } = await open( url );
	await settle( 6000 );
	check( `${ url } loads its viewer when idle`, views( requested ) === 1, `${ views( requested ) } requested` );
	await page.close();
}

// 5. Phone: tap a shelf item, open the Pages sheet, Back closes the sheet, Back closes the viewer, no reload.
{
	const { page } = await open( '/documents/', { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true } );
	await page.tap( '.documents-item__open' );
	const opened = await page.waitForFunction( () => document.querySelector( '.documents-mobile' )?.open, { timeout: 15000 } ).then( () => true, () => false );
	check( 'phone: a tap opens the mobile viewer', opened );
	const pages = await page.$( '.documents-mobile__pages' );
	if ( pages ) {
		await pages.tap();
		await settle();
		await page.goBack();
		await settle();
		const s1 = await page.evaluate( () => ( { viewer: !! document.querySelector( '.documents-mobile' )?.open, marker: window.__noReload === true } ) );
		check( 'phone: Back closes only the Pages sheet', s1.viewer && s1.marker );
	} else {
		check( 'phone: the footer Pages button exists (the document needs 2+ pages)', false );
	}
	await page.goBack();
	await settle();
	const s2 = await page.evaluate( () => ( { viewer: !! document.querySelector( '.documents-mobile' )?.open, marker: window.__noReload === true } ) );
	check( 'phone: the next Back closes the viewer without a reload', ! s2.viewer && s2.marker );
	await page.close();
}

// 6. Home video: a click on the film strip starts the video with sound (Chrome).
{
	const { page } = await open( '/' );
	await page.evaluate( () => [ ...document.querySelectorAll( '.work-strip__open[data-video-id]' ) ].find( ( b ) => ! b.closest( '[inert]' ) ).click() );
	await page.waitForFunction( () => document.querySelector( '.videos-modal' )?.open, { timeout: 15000 } ).catch( () => {} );
	await settle( 1500 );
	const v = await page.evaluate( () => {
		const video = document.querySelector( '.videos-modal video' );
		return video ? { muted: video.muted, paused: video.paused } : null;
	} );
	check( 'home video opens from the strip', v !== null || ( await page.evaluate( () => !! document.querySelector( '.videos-modal iframe' ) ) ) );
	if ( v ) {
		check( 'a click starts the uploaded video with sound', v.muted === false, JSON.stringify( v ) );
	}
	await page.close();
}

await browser.close();
console.log( failed ? `${ failed } check(s) failed` : 'all checks passed' );
process.exit( failed ? 1 : 0 );
```

## Residual (measured, not in this ticket)

- **ESLint / Prettier:** not added (no npm toolchain in the repo; Phase 5). A later ticket can add them with a `package.json` if wanted.
- **PHPCS findings:** 306 at baseline, reported only. Fixing them (and maybe making PHPCS a gate) is a later ticket; the pre-push gate plan's Context still says "linting (none is configured)" and quotes old test counts, so update that plan when it is picked up.
- **Home `selected-work/style.css` (14.2 KB) and `contact/style.css` (16.5 KB) stay render-blocking:** they style visible sections, and `/#contact` lands on the contact block, so deferring them risks a flash or a layout shift.
- **`viewer.css` still holds every prefix** (`/videos/` downloads `collaterals-*` rules): most shared rules list several prefixes in one selector; splitting by page needs the selectors rewritten, which is a design change.
- **Large plugin files without a measured seam:** `video-autofill.php` 1,056, `class-page-store.php` 970, `class-pptx-converter.php` 730, `class-page-rest.php` 617 lines.
- **CI:** none; see `.claude/plans/backend/DPB-NNN-Pre-Push-Test-Gate.md` for the local gate and its CI follow-up.

## Conventional commits

Branch `feat/DPB-NNN-Codebase-Cleanup` (exists, no commits; `feat/` is the prefix `AGENTS.md` prescribes). **One PR**, title: `refactor: clean up docs, plugin and theme; load viewers on first use` (69 characters). Merge with a normal merge commit.

| # | Phase | Subject | Stage (relative to the plugin or theme folder where not rooted) | Plugin | Theme | Node change |
|---|---|---|---|---|---|---|
| 1 | 0 | `chore(agents): add an editorconfig` | `.editorconfig` | P | T | - |
| 2 | 1 | `docs(agents): move detail into docs/agents` | `AGENTS.md docs/agents/` | P | T | - |
| 3 | 1 | `docs(agents): split long paragraphs at their labels` | `docs/agents/` | P | T | - |
| 4 | 1 | `docs(agents): correct stale versions, paths and counts` | `AGENTS.md docs/agents/` | P | T | - |
| 5 | 2 | `chore(agents): stop tracking the unused twentytwentyfive theme` | `.gitignore AGENTS.md README.md` + the staged `git rm --cached` | P | T | - |
| 6 | 3 | `refactor(custom-post-types-dp): share media meta sanitisers and boxes` | `includes/post-types/{media-meta,document,collateral,video}.php`, `tests/class-cptdp-media-cpt-test-case.php`, `tests/post-types/test-{document,collateral,video}.php`, `docs/agents/plugin-cpts.md` | P | T | - |
| 7 | 3 | `refactor(custom-post-types-dp): share the statistics repeater script` | `assets/admin-{statistics,document,collateral,video}.js`, `includes/post-types/{document,collateral,video}.php` | P | T | - |
| 8 | 4 | `refactor(custom-post-types-dp): remove the unused pptx warnings list` | `includes/post-types/document-presentations.php`, `tests/post-types/test-document-presentations.php` | P-1 | T | - |
| 9 | 4 | `feat(custom-post-types-dp): sweep stray LibreOffice temp folders daily` | `includes/documents/temp-sweep.php custom-post-types-dp.php includes/cli/class-pages-command.php tests/test-loader.php` | P-1 | T | - |
| 10 | 4 | `test(custom-post-types-dp): cover the temp folder sweep` | `tests/documents/test-temp-sweep.php` | P+4 | T | - |
| 11 | 4 | `docs(agents): document the temp folder sweep` | `AGENTS.md docs/agents/documents-pipeline.md` | P+4 | T | - |
| 12 | 4 | `chore(custom-post-types-dp): bump version to 0.36.0` | `custom-post-types-dp.php` | P+4 | T | - |
| 13 | 4 | (optional) `refactor(custom-post-types-dp): split the PDF value classes out` | `includes/documents/class-page-outline.php includes/documents/class-pdf-values.php` | P+4 | T | - |
| 14 | 5 | `build(custom-post-types-dp): add a report-only PHPCS run with WPCS` | `composer.json phpcs.xml.dist` | P+4 | T | - |
| 15 | 5 | `docs(agents): document the PHPCS report` | `AGENTS.md docs/agents/tests.md` | P+4 | T | - |
| 16 | 6 | `refactor(theme): move functions.php helpers into inc by domain` | `functions.php`, `inc/`, `tests/test-{contact-block,document-spreadsheets,document-presentations}.php` | P+4 | T | - |
| 17 | 6 | `docs(agents): map the theme include files` | `docs/agents/theme-core.md` | P+4 | T | - |
| 18 | 7 | `refactor(theme): remove the unused videos-modal blank rule` | `blocks/video-modal/style.css` | P+4 | T | - |
| 19 | 7 | `refactor(theme): remove two test-only viewer helpers` | `blocks/shared/{mode,deeplink}.js`, `blocks/shared/tests/mode.test.mjs`, `blocks/document-modal/tests/deeplink.test.mjs`, `docs/agents/viewer-shared.md`, `AGENTS.md` | P+4 | T | shared 178 |
| 20 | 8 | `refactor(theme): move shared viewer rules to blocks/shared/viewer.css` | `blocks/shared/viewer.css`, `blocks/document-modal/{style.css,block.json}`, `functions.php`, `blocks/document-modal/tests/routing.test.mjs`, the 12 `tests/test-*.php` of commit 20 step 5 | P+4 | T | - |
| 21 | 8 | `test(theme): pin where the viewer rules live` | `tests/test-viewer-css.php` | P+4 | T+3 | - |
| 22 | 8 | `docs(agents): document blocks/shared/viewer.css` | `AGENTS.md docs/agents/` | P+4 | T+3 | - |
| 23 | 9 | `feat(theme): add a shared popstate listener and a lazy modal loader` | `blocks/shared/{history-events,lazy-modal,modal-trigger}.js` | P+4 | T+3 | - |
| 24 | 9 | `test(theme): cover the shared popstate listener and the lazy loader` | `blocks/shared/tests/{history-events,lazy-modal,modal-trigger}.test.mjs` | P+4 | T+3 | shared 192 |
| 25 | 9 | `feat(theme): load the three viewers on first use` | `blocks/{document,collateral,video}-modal/{boot.js,block.json,view.js}`, `blocks/shared/sheet-back.js`, `blocks/shared/tests/sheet-controller.test.mjs`, `tests/test-collateral-modal-mobile.php`, `tests/test-document-viewer.php` | P+4 | T+3 | - |
| 26 | 9 | `feat(theme): print the viewer stylesheets as preloads` | `inc/viewer.php` | P+4 | T+3 | - |
| 27 | 9 | `test(theme): cover the lazy viewers and the preloaded styles` | `tests/test-lazy-viewers.php` | P+4 | T+9 | - |
| 28 | 9 | `docs(agents): document the lazy viewers` | `AGENTS.md docs/agents/` | P+4 | T+9 | - |
| 29 | - | `docs(plans): add the DPB-NNN codebase cleanup plan` | `.claude/plans/backend/DPB-NNN-Codebase-Cleanup-And-Optimization.md` (this file, renamed when the ticket number is known) | P+4 | T+9 | - |

Every subject is 72 characters or fewer. Footer on each: `Refs: DPB-NNN`, then `Co-Authored-By: <the model that made the commit> <noreply@anthropic.com>` (for example `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`). Stage explicit paths, never `git add -A`: the untracked plans, wireframes and the zip in the working tree must not be staged.

**PR description** (from `.github/pull_request_template.md`; tick Refactor, Feature and Docs), starting with this table filled from the `gates.sh` summary lines of each checkpoint:

| Checkpoint | Plugin (tests / skipped) | Theme | Node (block: tests) | `gates.sh` |
|---|---|---|---|---|
| baseline (`main`) | | | all nine | |
| phase-0 | | | | |
| phase-1 | | | | |
| phase-2 | | | | |
| phase-3 | | | | |
| phase-4 | | | | |
| phase-5 | | | | |
| phase-6 | | | | |
| phase-7 | | | shared | |
| phase-8 | | | | |
| phase-9 | | | shared | |

then the PHPCS summary line (commit 14), the screenshot comparison result (commit 20), the F1 before/after measurement and the two `lazy-check.mjs` runs, and the three Behaviour changes of Phase 9 verbatim. End the body with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

## Open questions (none blocks a phase)

1. **Commit scope for root files.** `AGENTS.md` lists the scopes `custom-post-types-dp`, `theme`, `agents`, `plans`; there is none for root files (`.editorconfig`, `.gitignore`, `README.md`) or a cross-cutting PR title. This plan uses `agents` for the root-file commits and no scope in the PR title. Add a `repo` scope to the rules?
2. **Ticket number.** Rename this file and, if wanted, the branch when it is assigned (the branch already exists as `feat/DPB-NNN-Codebase-Cleanup`; renaming it is optional).
3. **Idle preloading on the archives (F1).** Decided as on (the archives' main action is opening a viewer). If bandwidth on archive visits matters more than a fast first open, drop the `idle` key from the three `boot.js` files; nothing else changes.
