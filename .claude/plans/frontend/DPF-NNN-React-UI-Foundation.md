# Plan: React UI foundation (reusable components, Interactivity API for the rest)

Ticket DPF-NNN (number to assign; new area folder `frontend/`). *As a developer or AI agent, I want the modals, sheets and viewers built from a small library of typed, reusable React components, and the server-rendered filters and grids driven by the WordPress Interactivity API, so that a new feature is configuration and composition, nobody writes `addEventListener`, `matchMedia`, `history.pushState` or View Transition code by hand, and the code and docs we must read per task shrink.*
Branch for phase 0: `chore/DPF-NNN-TypeScript-Tooling`. Later phases get their own branch and plan.

**This is an umbrella plan.** It fixes the architecture, the rules, the order and the gates. **Phase 0 is exact** (an implementer can execute it without decisions). Phases 1-5 are roadmap: each gets its own plan, written in the repo's exact format, only after the previous gate passes (a plan written now would be guesses about code that does not exist yet). The "Plans must be implementable by a smaller model" convention applies to those phase plans.

**Out of scope here:** any runtime change in phase 0; converting server-rendered content (shelf, grid, header, category pills' first paint) to React; the plugin's wp-admin scripts (later ticket, `@wordpress/components`); a headless or full-React site; PHP changes beyond what a phase plan states.

## Goal and measurable outcome
"Don't worry about low-level stuff" is made checkable. Baseline measured today:

| Measure | Today | Target when done |
|---|---|---|
| Raw low-level calls in theme JS: `addEventListener` 83, `matchMedia` 17, `startViewTransition` 7, `history.pushState/replaceState` 12, hand-built DOM (`el(`/`createElement`) 134 | spread over 36 files | **0 outside `src/platform/`** (enforced by lint, below) |
| Theme JS source (no vendor/tests) | 7,469 lines in 36 files | proposal: at most 50% as TS/TSX, same behaviour |
| `AGENTS.md` | 108,705 bytes (~27k tokens loaded every session) | the UI sections replaced by pointers to typed components and one `src/README.md` |
| Documents vs Collaterals modal | two near-copies selected by a `prefix` string | one `ResourceModal` plus two small configs |
| Tests | 540 theme + 906 plugin PHP, 222 node | none lost; JS component tests added |

Numbers marked "proposal" are targets to confirm at the phase 1 gate.

## Facts
- WordPress 7.1.4, PHP 8.4, Node 24 (DDEV and host). Theme blocks register `viewScriptModule` today (4 blocks); there is no build step and no `package.json`/`tsconfig.json`. `/node_modules/` is already git-ignored; `package.json`, `package-lock.json`, `tsconfig.json`, `types/` are not ignored (no `.gitignore` edit).
- The Navigation block loads the Interactivity API and Router already, so the API costs nothing extra on the page. AGENTS.md documents that the Router answers every `popstate` and reloads the page for URLs it has not cached; listener order matters (the three `popstate` handlers are registered as their scripts run).
- One command runs all JS tests today (verified): `node --test "wp-content/themes/devportfolio/blocks/*/tests/*.test.mjs"` = 222 pass.
- Dry run `tsc` (5.9.3, `checkJs`, `strict: false`) over the 36 theme files: 104 errors in 17 files, 19 files clean. The 17 are listed in Phase 0's `exclude`.
- The sheet code is the best seam: `mobile.js` already loads `details-sheet.js` and `pages-sheet.js` lazily with `import()` through `sheet-controller.js`. Today's sheet code: `bottom-sheet.js` 241, `sheet-controller.js` 105, `sheet-back.js` 63, two `details-sheet.js` 383, `pages-sheet.js` 653 = 1,445 lines.
- `.claude/plans/documents/DPD-035-Reusable-Checkbox-Filter.md` (not yet implemented) plans a generic filter block in plain JS. **Re-plan it on this library** (filter UI in Interactivity API, its popover/sheet from `ui/`) instead of building it twice. Do not start DPD-035 step 3 before phase 4.

## Architecture decisions
1. **Split rule, one sentence:** *UI that does not exist until the visitor acts (modals, sheets, viewers, details) is React; content the visitor sees on first paint (header, shelf, grids, category pills, tags) stays server-rendered PHP, with behaviour from the Interactivity API.* Consequence: React islands are client-only, so there is no hydration, no layout shift and no SEO or no-JS loss (a closed modal has no content to lose).

   | Piece | Technology | Why |
   |---|---|---|
   | Document/Collateral modals (desktop + mobile), Pages and Details sheets, pager, status line, viewer shells | **React islands** (TS) | state-heavy, duplicated, built from the DOM by hand today |
   | StPageFlip, pdf.js, OOXML canvas viewers | imperative **adapters** inside a React component | they own their DOM; React must not touch it |
   | Category pills and dropdown, shelf/grid visibility, masonry trigger, `?category=` URL state | **Interactivity API** (`viewScriptModule`) | server-rendered, directive-shaped, already loaded |
   | Video hover preview | small TS module called from an Interactivity action | decorative, media-element specific |
   | Header, shelf, grid, covers, tags, templates, `theme.json` | PHP/HTML, unchanged | first paint |

2. **Contract between the three worlds** (so none knows how the others work): only (a) HTML `data-*` attributes, (b) bubbling DOM events named `devportfolio:*` (exists today: `devportfolio:category-change`), (c) URL query params, (d) a typed JSON payload in a `<script type="application/json">` that PHP already outputs. A React island mounts on `<div data-island="<name>">`; PHP `render.php` shrinks to that div, the payload script and the strings.
3. **Three-layer code layout** under `wp-content/themes/devportfolio/src/` (built to `build/`):
   - `platform/` the **only** place allowed to call browser APIs: `useMediaQuery`, `useEvent`, `useHistoryEntry` (the single history entry a sheet/modal owns, replaces `sheet-back.js`/`deeplink.js` plumbing), `useDeeplink( param )`, `useViewTransition`, `useVisible` (IntersectionObserver), `useSize` (ResizeObserver), `useGestures` (swipe, edge tap, double tap, pinch), `usePayload<T>()`, `useStorage`.
   - `ui/` generic components built only from `platform/`: `Modal` (native `<dialog>`, backdrop click, Esc, focus return, view-transition swap), `BottomSheet` (drag to dismiss, `inert` background, one history entry), `Pager`, `StatusLine`, `Pill`, `IconButton`, `Footer/ProgressBar`, `ViewerSurface`.
   - `features/<name>/` compose `ui/` with config and payload types: `resource-modal` (documents and collaterals), `pages-sheet`, `details-sheet`. A feature contains no browser API calls and almost no CSS.
4. **Viewers behind one interface.** `PageViewerAdapter { mount( el, doc, opts ): Promise<void>; goTo( page ): void; destroy(): void; onPage( cb ) }` with adapters `pageFlip` (StPageFlip), `pdfJs`, `ooxml` (pptx/xlsx/docx), `images`. `ViewerSurface` owns a `ref`'d `<div>` React never renders into, the loading/failed/pending states (today's `status.js`) and teardown. A new file type = a new adapter, nothing else.
5. **Enforcement, not convention.** ESLint (`wp-scripts lint-js`) with an override: outside `src/platform/**`, `no-restricted-properties`/`no-restricted-globals`/`no-restricted-syntax` ban `addEventListener`, `matchMedia`, `startViewTransition`, `IntersectionObserver`, `ResizeObserver`, `history.pushState/replaceState`, `document.createElement`, `querySelector*`, `localStorage`; in `features/**` additionally ban importing `platform/` directly (features use `ui/` and hook re-exports). A violation fails `npm run check`, so an agent cannot quietly reintroduce low-level code.
6. **Types are the documentation.** Every component prop, every hook signature and every payload (`DocumentPayload`, `CollateralPayload`) is a TS type; `src/README.md` (one page) lists the library. A PHP theme test writes a payload fixture (`tests/fixtures/payload-*.json`); a typecheck-only file assigns the fixture to the TS type (`resolveJsonModule`), so PHP/TS drift fails `typecheck`.
7. **Build and React source.** `@wordpress/scripts` builds `src/`. React comes from core, never bundled twice: import from `@wordpress/element` only (never `react`); islands are classic `viewScript` entries (auto `wp-element` dependency via `.asset.php`) because `@wordpress/element` is not a script module; Interactivity code is `viewScriptModule`. Heavy vendored libraries keep loading by URL with `import( /* webpackIgnore: true */ url )`, as today. **Phase 1 spike must prove** (a) no second React copy on the page, (b) the lazy-sheet entry loads only on first tap, (c) measured cost: wp-element on the page vs React inside the lazy chunk (expected roughly 40-50 KB gzip; unverified). Pick the cheaper per page.
8. **Tests.** Pure and `platform/` logic: `node --test` on `.ts` directly (Node 24 strips types; `erasableSyntaxOnly` in tsconfig keeps TS to that subset), files `src/**/*.test.ts`. Components: `wp-scripts test-unit-js` (Jest + jsdom) with `@testing-library/react`. PHP tests that parse `view.js`/`style.css` source (many in `tests/test-document-modal*.php` and siblings) are rewritten to assert on the island markup, the `block.json` entries and built assets, **in the same phase that moves the code**, never left red. Browser behaviour (Back/Forward with no reload, rotation, sheets) stays the headless-Chrome check used for DPD-020/032, 11 flows, 0 reloads.
9. **Built output is never committed.** Only TypeScript/TSX sources are tracked; `wp-content/themes/devportfolio/build/` is git-ignored (Phase 0 adds the line). Consequences: (a) every environment that needs the UI runs `npm run build` first: DDEV (`ddev exec npm run build`, add it to the install steps in AGENTS.md), the PHP test runs (several tests assert on built files) and CI; (b) the host never builds: the Hostinger plan has no Node.js (see Phase 6 facts), so **GitHub Actions builds and uploads the finished theme**; (c) until Phase 6 exists, production gets `build/` by a manual upload, so no phase that adds `build/` consumers ships to production before Phase 6.
10. **Strangler, never big bang.** One feature at a time, each behind the unchanged public contract (events, URLs, payload, CSS class names the PHP tests pin), legacy file deleted in the same PR that replaces it. Two systems exist only between phases.

## Dev workflow (decided; implemented in phase 1)
**Tooling stays `@wordpress/scripts`.** Vite (true HMR) was considered and rejected: it needs custom PHP glue for the dev server, a React Fast Refresh preamble, DDEV HTTPS port exposure and import-map externals, which goes against staying with what WordPress supports. `src/` is bundler-agnostic, so this can be revisited without touching components.

What a developer or agent experiences:
- **`ddev start` is all you run.** A DDEV daemon runs the watcher (`wp-scripts start`) and rebuilds `build/` on every save of `src/` in about a second.
- **Edit, then refresh the page by hand.** There is no hot reload and no component state preserved. Script versions come from the `.asset.php` content hash, so no hard refresh is needed.
- **Instant, no build:** PHP, templates, CSS, `theme.json`, and any legacy `blocks/**/*.js` not yet migrated.
- **Component and platform tests have a watch mode:** `ddev exec npm run test:unit:watch` (`wp-scripts test-unit-js --watch`) reruns the affected tests on save, jsdom, no browser. `.test.ts` files under `src/` also run with `node --test` (`npm run test:node`).
- **Types:** `npm run typecheck` is the authority; watcher output may not show type errors.
- **Fresh clone or pull that changes `src/`:** `ddev restart` (the `post-start` hook installs and builds); to build once without the daemon, `ddev exec npm run build`.

Files and edits (phase 1 plan writes them in full; this fixes the shape):
- `package.json` scripts added: `"start": "wp-scripts start --webpack-src-dir=wp-content/themes/devportfolio/src --output-path=wp-content/themes/devportfolio/build"`, `"build"` (same flags, `wp-scripts build`), `"test:unit"` (`wp-scripts test-unit-js`), `"test:unit:watch"` (same with `--watch`), `"test:node"` (`node --test "wp-content/themes/devportfolio/src/**/*.test.ts"`), and `check` becomes `typecheck && lint && build && test:node && test:unit && test:js`.
- `.ddev/config.yaml` added (tracked file):
  ```yaml
  web_extra_daemons:
    - name: ui-watch
      command: "npm run start"
      directory: /var/www/html
  hooks:
    post-start:
      - exec: "[ -d node_modules ] || npm ci"
  ```
  Logs: `ddev logs -s web` (daemon output). Gate in the phase 1 plan: after `ddev restart`, saving a `src/` file changes `build/*.js` within 5 seconds, and `ddev exec supervisorctl status` shows `webextradaemons:ui-watch` RUNNING. Not yet tested in this repo; if the daemon races the `npm ci` hook on a fresh clone, run the install in the daemon command (`sh -c "[ -d node_modules ] || npm ci; npm run start"`) instead and drop the hook.
- `AGENTS.md` gets a short "UI dev loop" block with the five bullets above (replaces explaining it per session).

## Phases and gates
| # | Scope | Gate to continue |
|---|---|---|
| **0** | Tooling: `package.json`, `tsconfig.json`, type check of the 19 clean legacy files, one JS test command, AGENTS.md pointer. **Exact below.** | `npm run check` green; 222 JS tests |
| **1** | **Pilot**: `@wordpress/scripts`, ESLint rules, Jest; `platform/` (`usePayload`, `useHistoryEntry`, `useMediaQuery`, `useGestures` drag part) and `ui/BottomSheet`; rewrite the Pages sheet and both Details sheets as React behind `sheet-controller`'s lazy `import()` seam (no change to `mobile.js` callers). Delete `bottom-sheet.js`, `sheet-back.js`, `details-sheet.js` x2, `pages-sheet.js`. | all of: sheet source <= 50% of 1,445 lines (proposal); first-load JS of the Documents and Collaterals archives grows <= 10 KB gz (proposal); lazy cost measured and reported; PHP suites 540/906 green; 11 browser flows 0 reloads; lint rule demonstrably fails on a raw `addEventListener` in `features/` |
| **2** | `ui/Modal`, `features/resource-modal` for **Collaterals first** (desktop + mobile shells, Results, Next, Download, deeplink), then **Documents as config** over the same component; remove the `prefix` duplication. | Documents needs <= 150 lines of its own feature code (proposal); both pages pass the DPD/DPC browser checks |
| **3** | Viewer adapters + `ViewerSurface` + `useGestures` (swipe, pinch, edge tap, double tap), mobile viewer; delete `viewer.js`, `mobile-viewer.js`, `page-source.js`, `touch-zoom.js`, `gestures.js`, `pager-controller.js`. | every file kind still renders (pdf, pptx, xlsx, docx, image) with the same fallbacks |
| **4** | Interactivity API: category pills/dropdown, shelf and grid filtering, `?category=`, masonry trigger, video preview action; re-plan **DPD-035** on it. | Back/Forward 0 reloads (the Router `popstate` rule is the main risk); View Transitions unchanged |
| **5** | Retire the legacy `blocks/shared` JS, remove the debt list, slim AGENTS.md (UI sections to pointers), update test totals. | AGENTS.md shrinks (target proposal: below 60,000 bytes); no raw low-level calls outside `platform/` |
| **6** | **CI/CD** (GitHub Actions): on PR run `npm ci`, `npm run check`, `npm run build`, the PHP suites; on merge to `main` build and upload the theme and plugin to Hostinger. Its own plan. **Must precede any production release of phase 1+ code** (decision 9c). | a test deploy to a staging path works end to end, rolls back, and never uploads `node_modules`, `src/`, tests or secrets |

### Phase 6 facts: what is and is not verified about Hostinger
Searched on 2026-11-17. **Not verified for "Managed WordPress Premium" specifically; verify in hPanel before writing the phase 6 plan** (sources disagree or are silent):
- Hostinger's managed WordPress page names three tiers (Premium, Unlimited, Cloud Startup) and says generally "WP-CLI, SSH access, Git integration, and PHP version control" without a per-plan table.
- Hostinger support says SSH "is available on Premium Web higher plans, it isn't available on Single Web plans", and that SSH is off until enabled in hPanel (SSH Access page, or Advanced). Third parties disagree about which tier has SSH. Consistent with Premium having it, not proof.
- Port: Hostinger's docs show 65002 for shared hosting (the generic docs page says 22); **read the host, user and port from hPanel**. SSH keys are supported (public key added in hPanel).
- Hostinger's comparison table lists **Node.js: No for Premium**, so builds cannot run on the host. This is why CI builds.
- Hostinger's Git feature (hPanel, Advanced, Git) pulls a repository; since `build/` is not committed it would deploy sources only, so it is **not** usable as-is.
- No official Hostinger guide for GitHub Actions exists; the common pattern is `rsync -e "ssh -p <port>"` over SSH from the runner with the key in GitHub secrets (Hostinger documents rsync and says to dry-run first).

**Two-minute verification (owner, before phase 6):** hPanel, Advanced, SSH Access: enable it, add a public key, then `ssh -p <port> <user>@<host> 'echo ok && php -v && which rsync wp'`. If it works, deploy by rsync. If SSH is not offered on the plan: fall back to SFTP/FTP upload from the Action (for example `SamKirkland/FTP-Deploy-Action`), or make the Action push the built theme to a `deploy` branch and let hPanel Git pull that branch. Either fallback keeps `main` source-only.

## Phase 0 (exact): TypeScript tooling and one test command
No runtime change, no build output, no version bump. Stop and report if a Fact above is wrong.

### `package.json` (new, repo root)
```json
{
	"name": "devportfolio",
	"private": true,
	"description": "Tooling only. Nothing here is shipped.",
	"scripts": {
		"typecheck": "tsc -p tsconfig.json",
		"test:js": "node --test \"wp-content/themes/devportfolio/blocks/*/tests/*.test.mjs\"",
		"check": "npm run typecheck && npm run test:js"
	},
	"devDependencies": {
		"typescript": "^5.9.3"
	}
}
```

### `tsconfig.json` (new, repo root)
```json
{
	"compilerOptions": {
		"target": "ES2022",
		"module": "ES2022",
		"moduleResolution": "Bundler",
		"lib": ["ES2024", "DOM", "DOM.Iterable"],
		"allowJs": true,
		"checkJs": true,
		"noEmit": true,
		"strict": false,
		"skipLibCheck": true
	},
	"include": [
		"wp-content/themes/devportfolio/blocks/**/*.js",
		"types/**/*.d.ts"
	],
	"exclude": [
		"node_modules",
		"wp-content/themes/devportfolio/blocks/**/vendor/**",
		"wp-content/themes/devportfolio/blocks/**/tests/**",

		"wp-content/themes/devportfolio/blocks/collateral-grid/view.js",
		"wp-content/themes/devportfolio/blocks/collateral-modal/details-sheet.js",
		"wp-content/themes/devportfolio/blocks/collateral-modal/view.js",
		"wp-content/themes/devportfolio/blocks/document-categories/view.js",
		"wp-content/themes/devportfolio/blocks/document-modal/view.js",
		"wp-content/themes/devportfolio/blocks/shared/bottom-sheet.js",
		"wp-content/themes/devportfolio/blocks/shared/capability.js",
		"wp-content/themes/devportfolio/blocks/shared/mobile-viewer.js",
		"wp-content/themes/devportfolio/blocks/shared/mobile.js",
		"wp-content/themes/devportfolio/blocks/shared/modal-trigger.js",
		"wp-content/themes/devportfolio/blocks/shared/page-source.js",
		"wp-content/themes/devportfolio/blocks/shared/pager-controller.js",
		"wp-content/themes/devportfolio/blocks/shared/pages-sheet.js",
		"wp-content/themes/devportfolio/blocks/shared/sheet-controller.js",
		"wp-content/themes/devportfolio/blocks/shared/viewer.js",
		"wp-content/themes/devportfolio/blocks/video-grid/preview.js",
		"wp-content/themes/devportfolio/blocks/video-grid/view.js"
	]
}
```
The 17 listed files are the **debt list**: they are replaced, not repaired, by phases 1-5 (a file leaves the list by being deleted). Step 2 regenerates it from the real run: `exclude` must hold exactly the files that fail. Phase 1 adds `"wp-content/themes/devportfolio/src/**/*.{ts,tsx}"` to `include` and `"erasableSyntaxOnly": true`, `"jsx": "react-jsx"`.

### `types/globals.d.ts` (new)
```ts
export {};

declare global {
	interface Window {
		/** StPageFlip, set by the vendored blocks/shared/vendor/page-flip.browser.js. */
		St: { PageFlip: any };
	}
	interface Navigator {
		/** Network Information API (not in lib.dom). */
		connection?: { saveData?: boolean; effectiveType?: string };
	}
}
```

### `AGENTS.md` edits
- **Commands**: add `ddev exec npm ci` (once), `ddev exec npm run typecheck`, `ddev exec npm run test:js`, `ddev exec npm run check`; one line: "UI direction and rules: `.claude/plans/frontend/DPF-NNN-React-UI-Foundation.md`; new UI goes in `src/` (from phase 1), never add raw browser calls outside `src/platform/`".
- **Tests**: replace each per-block `ddev exec --dir=... 'node --test tests/*.test.mjs'` instruction with `npm run test:js` (keep the pure-module descriptions); JS total 222.
- **What is tracked**: add `package.json`, `package-lock.json`, `tsconfig.json`, `types/`.

### `.gitignore` (edit)
Under `# Dependencies and generated build output`, add the line `/wp-content/themes/*/build/` (the theme folder is allowlisted wholesale, so without it `build/` would be tracked). Gate: `git check-ignore -v wp-content/themes/devportfolio/build/x.js` prints the rule. Add `.gitignore` to commit 1.

### Steps (stop on a red gate)
1. Create `package.json`; `ddev exec npm install`. Gate: `ddev exec npx tsc -v` prints `Version 5.9.x`; `git status --short` shows `package.json` and `package-lock.json`, no `node_modules`.
2. Create `tsconfig.json` and `types/globals.d.ts`. Gate: `ddev exec npm run typecheck` = 0 errors. If a file outside `exclude` fails, add it; if a listed file is clean, remove it; record both in the PR. Then `ddev exec npx tsc -p tsconfig.json --listFilesOnly | grep -c "blocks/.*\.js"` = 19 (or the adjusted number).
3. Gate: `ddev exec npm run test:js` = `tests 222`, `pass 222`, `fail 0`.
4. Prove the checker bites: in a scratch copy of a call to a clean module, pass a wrong argument type, see `typecheck` fail, revert. Do not commit.
5. Edit `AGENTS.md`. Gate: `grep -n "node --test tests" AGENTS.md` returns nothing.

### Phase 0 conventional commits
Branch `chore/DPF-NNN-TypeScript-Tooling`; PR title `chore: add TypeScript checking and one JS test command`.
1. `build: add npm tooling for type checking and JS tests` (`package.json`, `package-lock.json`, `tsconfig.json`, `types/globals.d.ts`), footer `Refs: DPF-NNN`.
2. `docs(agents): document npm check and the single JS test command` (`AGENTS.md`), footer `Refs: DPF-NNN`.
3. `docs(plans): add the DPF-NNN React UI foundation plan` (this file), footer `Refs: DPF-NNN`.
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`; PR body from `.github/pull_request_template.md`.

## Risks
- **Two systems during the migration.** Mitigated by one feature at a time, same public contract, legacy deleted in the same PR.
- **React as a classic script vs script modules.** Decided in the phase 1 spike with measurements; the split rule (client-only islands) keeps either outcome cheap.
- **Interactivity Router `popstate` reloads** (AGENTS.md): phase 4 reuses the three-handler ordering rule inside one `useHistoryEntry`/store module so it lives in one place; verified by the 11-flow browser check.
- **React and `<dialog>`/View Transitions/StPageFlip.** `Modal` uses a ref and `showModal()` in a layout effect; transitions wrap `flushSync`; adapters own a div React never renders into. All three are proven in phases 1-3, not assumed.
- **PHP tests that parse JS/CSS source.** Rewritten in the phase that moves the code (decision 8).
- **`build/` not committed:** a forgotten build means missing scripts. Mitigated by `npm run build` in `npm run check`, a PHP test asserting each `block.json` entry exists (it fails with "run npm run build"), and phase 6 as the only production path. Local WordPress without a build shows the server-rendered page but no modals.
- **Bus factor / dependency weight:** `@wordpress/scripts` is large (dev only). Lockfile tracked, `npm ci` only.
- **Abstraction overreach.** The library grows only from what a phase needs; no component is written ahead of its first consumer.

## Open decisions (none block phase 0)
1. ~~Commit `build/`?~~ Decided: no. Remaining owner check: SSH on the Hostinger plan (Phase 6 facts); blocks phase 6, not phases 0-5.
2. Confirm the proposal thresholds at the phase 1 gate (50% lines, +10 KB gz, 150-line Documents config, AGENTS.md under 60,000 bytes).
3. Re-scope DPD-035 onto this library (phase 4).
