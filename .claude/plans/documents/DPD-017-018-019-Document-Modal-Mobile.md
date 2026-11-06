# Plan: Document modal, mobile shell (DPD-017 modal, DPD-018 header, DPD-019 footer)

This plan is written to be handed to another agent: it is self-contained. Read **Handoff notes** and **Context** first, then follow **Steps** in order. Anything marked *(owner decision)* is already decided; do not reopen it.

**Implementation status:** Implemented and reviewed in the theme. The mobile dialog, shared download handling, responsive routing/history, mobile footer state, resize swap, tests and documentation are in place. The implementation keeps desktop and mobile concerns separated, shares download and pager helpers, and avoids loading the desktop viewer for mobile. The mobile progress bar is positioned at the bottom of the body, matching the desktop placement, and is hidden when the page count is below 2. Validation completed with 231 theme PHPUnit tests, 690 plugin PHPUnit tests (12 skipped), 72 Node modal tests, 7 focused mobile-modal tests, and `git diff --check`.

## Tickets (verbatim intent)

**DPD-017, Modal, Mobile** (High, User Interface, device or browser window 700px wide or less). *As a user, when I tap a document AND I am using a really small device (screen width of 700px or less), then a separate blank modal specifically for mobile only should display.*
- The mobile modal covers the whole screen (no dimmed backdrop and no centred dialog).
- It has three areas, filled in later stories: header (DPD-018), body (DPD-021), footer (DPD-019).
- The device or browser Back button closes the modal instead of leaving the portfolio.
- Above 700px the desktop modal (DPD-009) is used.
- Acceptance: tapping a book at 700px or less shows a full-screen blank modal; pressing Back closes it and keeps me on the Documents page; wider than 700px shows the desktop modal instead.

**DPD-018, Modal - Mobile, Header** (Medium, depends on DPD-017, DPD-012, DPD-015). Elements: (1) Close (X) button, left, round icon button; (2) document title between the buttons, bold serif, one line, cut with `...` if too long; (3) Download button, right, round icon button (icon only). Tapping X or Download runs its action. The header is separated from the page area by a thin divider.
- Acceptance: X at the left, title in the middle, Download icon at the right; a long title is cut with `...` on one line; X closes the modal; Download downloads the file.

**DPD-019, Modal - Mobile, Footer** (Medium, depends on DPD-017; precondition in the ticket: a document with at least 3 pages). Row 1, spread across the width: text-icon button **Pages** (left), text **page indicator** (centre, bold, e.g. `2 / 24`), text-icon button **Details** (right). Row 2: **progress bar**, full width, filled up to the current page. The footer is separated from the page area by a thin divider.
- Acceptance: `Pages` at the left, `2 / 24` style indicator at the centre, `Details` at the right; a progress bar below filled in proportion to the current page; turning the page updates the indicator and the bar.

## Owner decisions (resolved, do not reopen)
1. **Crossing 700px while a modal is open swaps to the other modal** (same document, same page, same URL) **inside a View Transition** so the change is easy on the eyes. Without View Transitions, or with `prefers-reduced-motion: reduce`, the swap is instant.
2. **The mobile footer always shows**, whatever the page count (the Pages and Details buttons live there). The "at least 3 pages" precondition in the ticket is only the case the story was written for, not a visibility rule.
3. **`?document_slug=<slug>` opens the mobile modal on first load** at 700px or less (DPD-032 currently ignores it there). Update the DPD-032 sentence in `AGENTS.md`.

## Out of scope (later stories; the tickets say so)
- The mobile **body** and page rendering, swipe/tap-edge turning, double-tap zoom, the edge hints and hint chips (DPD-021). The body is an empty container here.
- The **Pages sheet** and **Details sheet** the footer buttons open (the buttons exist and fire an event; nothing opens).
- Any plugin change, any new dependency, any change to how documents are converted or stored.
- Dark mode (the whole modal is light-only today).

## Handoff notes (read before coding)

**Repo and branch.** Repo root `/Users/adriane.puzon/Documents/PHP Projects/devportfolio` (WordPress, DDEV). Work from `main` on a new branch `feat/DPD-017-018-019-Document-Modal-Mobile`. All code changes are in the **theme** `wp-content/themes/devportfolio/` (block `blocks/document-modal/`, tests in `tests/` and `blocks/document-modal/tests/`). The theme has no build step: ES modules and CSS are served as written.

**Conventions** (from `AGENTS.md`): WordPress Coding Standards for PHP (tabs, Yoda conditions, escape output with `esc_*`); text domain `devportfolio`; function prefix `devportfolio_`; wrap user-facing strings in `esc_html__()` / `esc_html_x()` / `esc_attr_x()`; JS uses tabs and the spaced-parens style already in `view.js`; Conventional Commits with scope (`feat(theme):`, `test(theme):`, `docs(agents):`, `docs(plans):`); every commit ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`; the PR description ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Do not commit unrelated untracked files (`.claude/plans/design-system.html`, `.claude/plans/home/`, the two wireframes, `DPB-NNN-Pre-Push-Test-Gate.md`) and do not stage the hunks of `AGENTS.md` that were already modified before you started; stage only your own hunks (`git add -p`).

**Commands** (run inside DDEV; `ddev start` first):
```bash
# one-time, and again after `ddev restart` (the WP test lib lives in /tmp of the container)
ddev composer --working-dir=wp-content/plugins/custom-post-types-dp install
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"

# theme PHPUnit suite (baseline today: 224 tests, all passing)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
#   filter: ... phpunit --filter Devportfolio_Test_Document_Modal_Mobile --testdox

# plugin suite (untouched by this work; run once at the end: 690 tests, 12 skipped)
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit

# JS unit tests (pure modules only)
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/document-modal 'node --test tests/*.test.mjs'
```
There is no browser test runner in the repo. Manual verification uses a browser (or headless Chrome with `puppeteer-core`, which an earlier ticket used) against `https://devportfolio.ddev.site/documents/`, with device emulation at 390x844.

**Test data.** `ddev wp cptdp seed --all` (or at least `document-categories`, `software-tools`, `documents`) creates 8 published documents with **no uploaded files** (so `downloadMode` is `none`, no images). To exercise Download and page counts, upload a PDF in wp-admin on one document (Documents -> edit -> Document File) and set Total pages; run `ddev wp cptdp pages generate --all --sync` to build page images. `ddev snapshot` before seeding a DB you care about.

**Gotchas that will bite if ignored** (each is also in Risks):
1. **Never put `display: flex` (or `grid`) on a `<dialog>` selector.** It overrides the UA rule `dialog:not([open]) { display: none }` and the closed dialog becomes visible. The desktop modal avoids it with an inner `.documents-modal__panel`; the mobile modal does the same (`.documents-mobile__panel` is the flex column).
2. **`dialog.close()` fires its `close` event asynchronously** (queued task), after the code that called it keeps running. Flags such as `swapping` and `closingFromHistory` must be set before `close()` and **cleared inside the `close` handler**, never in a `finally` right after `close()`.
3. The UA stylesheet gives modal dialogs `max-width/max-height: calc(100% - 6px - 2em)`; the mobile dialog must set `max-width: none; max-height: none; margin: 0; inset: 0`.
4. `100vh` is wrong on iOS Safari (address bar). Use `100dvh` (with `100vh` on the line before it for old engines).
5. `test_viewer_scripts_have_no_external_urls` (`tests/test-document-viewer.php:544`) scans a fixed file list for `http(s)://`. New JS files must not contain a URL, and **must be added to that list** (step 6).
6. The existing test `test_css_shell_rules` (`tests/test-document-modal.php:334`) asserts `@media (max-width: 700px) { .documents-modal, .documents-modal__footer { display: none } }`. Keep that exact rule; do not "tidy" it.

## Context: what the code does today

Files (all under `wp-content/themes/devportfolio/`):
- `blocks/document-modal/block.json`: `render: file:./render.php`, `style: file:./style.css`, `viewScriptModule: file:./view.js`. The block is registered in `functions.php` (`devportfolio_register_blocks()`), placed once in `templates/archive-document.html`.
- `blocks/document-modal/render.php`: outputs `div.documents-modal-root` (attributes `data-pdfjs`, `data-limits`, `data-units`, optional `data-selectable-text`) containing desktop and mobile dialogs plus `<script type="application/json" class="documents-modal__data">` (payload of every published document, keyed by post ID). The mobile dialog has a header, blank body/stage with its bottom progress bar, and an always-visible footer containing Pages, the position, and Details controls.
- Payload entry fields (from `devportfolio_document_modal_payload()` in `functions.php:440`): `id`, `slug`, `title` (plain text), `description`, `tools`, `format`, `kind`, `pages` (int, from `cptdp_total_pages`, 0 when unset), `downloadMode` (`file`|`embed`|`none`), `downloadUrl`, `images` (stored page image URLs), `imageWidth`, `cropBottom`, `ratio`, `viewerUrl`, `nativeUrl`, `pdfBytes`. **No payload change is needed.**
- `blocks/document-modal/view.js`: routes clicks to the desktop or mobile dialog at the 701px breakpoint, owns shared history/scroll-lock/focus cleanup, and swaps the two dialogs with a View Transition when the viewport crosses 700px. Desktop paging and viewer behavior remain in this file.
- `blocks/document-modal/pager.js`: pure helpers (`stepFor`, `normalisePage`, `pagerItems`, `visiblePages`, `pagerState`, `turnPage`, `indicatorParts`). `normalisePage( page, pages, step )` clamps; `turnPage( page, pages, step, direction )` returns the next page or the same page at the ends.
- `blocks/document-modal/mode.js`: pure; `unitOf( doc )` (`page` or `slide`), `wording( unit, units )` (`{ one, many, prev, next, nav, ... }`), `positionText( words, first, last, count )` (`Page 5 of 24`), `viewerMode()`, `canDrawOffice()`.
- `blocks/document-modal/deeplink.js`: pure; `slugFromUrl`, `urlWithSlug`, `urlWithoutSlug` (`?document_slug=<slug>`, keeps other args and hash).
- `blocks/document-modal/style.css` (713 lines): the `--dm-ink`, `--dm-subtle`, `--dm-line`, `--dm-line-strong`, `--dm-edge`, `--dm-surface`, `--dm-surface-alt`, `--dm-on-primary`, `--dm-grad`, `--dm-ease`, `--dm-dur` tokens are declared **on `.documents-modal` itself** (first rule, line 2, together with the desktop box rules). Shared utility classes already exist: `.documents-modal__icon` (18px stroke icon). `@media (max-width: 700px) { .documents-modal, .documents-modal__footer { display: none; } }` at ~line 672 with the comment "no desktop modal (DPD-017) and no footer (DPD-019)". Reduced-motion block at the end.
- `blocks/document-categories/style.css:33-52`: the View Transition pattern in this repo: element gets `view-transition-name`, root old/new `animation: none`, per-name `animation-duration: 0.2s; animation-timing-function: ease`, plus a `prefers-reduced-motion` block removing the animation. `document-categories/view.js:115-125` is the JS pattern (`if ( ! document.startViewTransition || reduce ) { apply(); return; } document.startViewTransition( () => apply() )`).
- `document-shelf`: every shelf item has an inline `view-transition-name: documents-item-<id>` and every plank `documents-plank-<n>`; `::view-transition-group(*.documents-item)` has a 0.4s rule. These names matter for the swap (see Risks).
- `functions.php`: `devportfolio_redirect_document_single` (`/documents/<slug>/` -> `/documents/?document_slug=<slug>`), so the single-URL redirect already lands on the modal; nothing to change there.
- Hi-fi reference for the mobile look: `.claude/plans/documents/documents-hifi-wireframe.html`: markup lines ~460-489 (`.vhead`, `.vclose`, `.vdl-icon`, `.vfoot`, `.mbar`), CSS `@media (max-width: 700px)` at ~390-420 (`.vhead` padding with `env(safe-area-inset-*)`, `.vtitlebox h2` 17px nowrap ellipsis, `.vfoot` border-top `var(--line-strong)`, `.mbar .r` flex space-between, `.mbar b` 13px/600 tabular-nums, `.mbar .bar` 3px, `.mbar .bar i` gradient with `width` transition), icons (X `M6 6l12 12M18 6L6 18`, download `M12 4v11M7 11l5 5 5-5M5 20h14`, Pages = four rounded squares, Details = chevron `M6 9l6 6 6-6`).

Behaviours that stay exactly as they are: the desktop modal at 701px and above (markup, CSS, payload, viewer), the deeplink URL contract, the single-URL redirect, the shelf and categories.

## Key decisions (how it is built)

1. **A second `<dialog class="documents-mobile">`, not a restyled desktop dialog.** The ticket says "a separate blank modal"; the header/footer structures differ; a separate element leaves every desktop selector, test and `viewer.js` binding untouched. Both dialogs live inside the same `div.documents-modal-root`, next to the one JSON payload. Only one is ever open.
2. **CSS decides which one exists; JS agrees through the same media query.** Desktop dialog: hidden at `max-width: 700px` (existing rule). Mobile dialog: hidden at `min-width: 701px` (new rule). JS routes by `desktop.matches` (`(min-width: 701px)`), the same breakpoint.
3. **`view.js` stays the single owner of "which document is open" and of the URL/history.** The mobile modal's own logic (header, footer, paging state) lives in a new module `mobile.js` which knows nothing about history. Two dialogs, one URL: all history work (`pushed`, `closingFromHistory`, `swapping`, `pushState`, `replaceState`, `history.back()`, `popstate`) stays in `view.js`, in one shared close routine.
4. **Back button = a history entry, as DPD-032 already does.** Opening pushes `?document_slug=<slug>`; device/browser Back fires `popstate`, which closes the open dialog without pushing; closing with X/Esc steps back with `history.back()` when this modal pushed the entry, else `replaceState` removes the param. Result: Back closes the modal and stays on `/documents/`; no dead entries. This is the existing mechanism, only made viewport-agnostic.
5. **Footer page state on mobile is one page at a time** (`step` is always 1; the hi-fi mobile hides the second page of a spread). The footer shows `current / total`; there is no "1-2 / 24" form on mobile.
6. **Turning pages before the body exists:** the mobile dialog handles Left/Right arrow keys (same guards as desktop) and exposes `setPage( n )`. That is the interim trigger for "when I turn the page, the indicator and bar update", and the hook DPD-021's swipe/tap will call. Pages and Details buttons only dispatch events (`documents:mobile-pages`, `documents:mobile-details`) on the dialog.
7. **The shared Download link logic is extracted** from `drawHeader()` into a small module `download.js` so desktop and mobile cannot drift.
8. **Reuse, do not copy:** icon class `documents-modal__icon`, the `--dm-*` tokens (declared once for both dialogs), `normalisePage`/`turnPage` from `pager.js`, `unitOf`/`wording`/`positionText` from `mode.js`, the string pattern in `render.php`.

## Target behaviour in detail

### A. Routing (DPD-017)
- Tap on any `[data-document-id]` (a shelf item's stretched button, or a cover): if `desktop.matches` open the desktop dialog (unchanged), else open the mobile dialog. The click handler no longer returns early on small screens.
- Mobile modal: native `showModal()`; no backdrop (`::backdrop { background: transparent }`); fills the viewport (`100%` x `100dvh`, no border, radius, shadow or margin); rises in 260ms (translate 24px + fade; none under reduced motion or during a swap).
- Page behind is scroll-locked the same way as desktop (`document.documentElement.style.overflow = 'hidden'`, cleared on close).
- Focus: Close button on open; on close the tapped book gets focus back (`opener`), as desktop.
- Esc closes (native). There is no backdrop click (the dialog is the whole screen).
- First load with `?document_slug=<known slug>`: at 700px or less open the mobile modal; focus goes back to the matching shelf button on close. Unknown or unpublished slug: dropped with `replaceState` as today. (Owner decision 3.)
- Back/Forward: `popstate` closes the open dialog when the URL has no slug; opens the matching document (on whichever dialog fits the viewport) when it has one.

### B. Resize across 700px with a modal open: swap (owner decision 1)
- Trigger: `desktop` media query `change` while either dialog is open and the open dialog is the wrong one for the new width.
- Same document, same page (desktop first page of a spread -> mobile page; mobile page -> desktop `normalisePage( page, pages, step )`, which snaps to the odd page of a spread). URL, `pushed`, `opener` and the scroll lock are **not** touched.
- Animation: `document.startViewTransition( run )`, where `run` synchronously closes the old dialog and shows the new one. Both dialogs share `view-transition-name: documents-modal`, so the browser morphs the framed desktop modal into the full-screen mobile one (and back) over ~0.3s. Fallback (no API, or reduced motion): `run()` immediately.
- While swapping, the dialogs' own open animation is suppressed (it would replay on the new `showModal()` and double up), and the shelf's per-item `view-transition-name`s are neutralised so books behind the modal do not take part in the transition (Risks 4-6).
- Re-entrancy: ignore a new `change` while a swap is running; when it finishes, re-check `desktop.matches` against the open dialog and swap again if the viewport moved on.

### C. Mobile header (DPD-018)
Structure inside `header.documents-mobile__header`, left to right, one flex row (`nowrap`, `gap: 8px`):
1. `button.documents-mobile__close.documents-mobile__btn` (type button, `aria-label` "Close viewer", X icon). 40x40, `border-radius: 999px` (round), 1px `--dm-line` border, transparent background; hover border `--dm-line-strong`. Tap area enlarged with an `::after { inset: -4px }`.
2. `h2.documents-mobile__title` (`id` used by `aria-labelledby`): Georgia/serif stack as desktop, `font-weight: 700`, 17px, `line-height: 1.2`, `flex: 1 1 auto; min-width: 0; margin: 0`, left-aligned in the space between the buttons, `white-space: nowrap; overflow: hidden; text-overflow: ellipsis`. Written with `textContent` (the payload title is already plain text).
3. `a.documents-mobile__download.documents-mobile__btn[role=button]`: icon only (download icon), same 40px round style; `aria-label` "Download <title>"; behaviour by `downloadMode`: `file` -> `href` + `download`; `embed` -> `href`, `target=_blank`, `rel=noopener`; `none` -> `aria-disabled="true"` (dimmed 0.42, no pointer events), click prevented. Identical logic to desktop (shared `download.js`).

Divider: `border-bottom: 1px solid var(--dm-line-strong)` (hi-fi mobile token). Padding: `calc(8px + env(safe-area-inset-top, 0px)) max(10px, env(safe-area-inset-right, 0px)) 8px max(10px, env(safe-area-inset-left, 0px))`. Background `--dm-surface`. Description, tags and format pill are **not** shown on mobile (they belong to the later Details sheet).

### D. Mobile footer (DPD-019)
`footer.documents-mobile__footer` (always visible, no `hidden` attribute; owner decision 2), `display: grid; gap: 10px`, `border-top: 1px solid var(--dm-line-strong)`, padding `10px max(14px, env(safe-area-inset-right, 0px)) calc(12px + env(safe-area-inset-bottom, 0px)) max(14px, env(safe-area-inset-left, 0px))`, background `--dm-surface`.
- **Row 1** `div.documents-mobile__row`: `display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 10px` (so the indicator is truly centred whatever the button widths):
  - `button.documents-mobile__pages.documents-mobile__textbtn` (`justify-self: start`): four-squares icon then the text "Pages".
  - `b.documents-mobile__position`: bold (600-700), 13px, `font-variant-numeric: tabular-nums`, shows `2 / 24`.
  - `button.documents-mobile__details.documents-mobile__textbtn` (`justify-self: end`): text "Details" then the chevron-down icon.
  - Text-icon button style: `min-height: 40px; padding: 0 14px; display: inline-flex; align-items: center; gap: 8px; font: inherit; font-size: 14px; font-weight: 500; border: 1px solid var(--dm-line); border-radius: 999px; background: transparent; color: var(--dm-ink); cursor: pointer`; hover border `--dm-line-strong`; `:focus-visible` outline `2px solid #1d62f0; outline-offset: 2px` (same as desktop).
- **Body progress bar** `div.documents-mobile__bar[role=progressbar][aria-valuemin=0][aria-valuemax=100][aria-valuenow][aria-valuetext][aria-label="Reading progress"]` is positioned at the bottom edge of the body, matching the desktop progress line. It is full width and 3px high, with track `--dm-line`, fill `--dm-grad`, and `i { width: <percent>%; transition: width var(--dm-dur) var(--dm-ease) }` (transition off under reduced motion). It is hidden when `pages < 2`; unknown page counts therefore show no label and no bar.
- **Numbers:** label is `${page} / ${pages}`; percent is `page / pages * 100` (page 1 of 24 shows 4.17%, the last page 100%; "filled up to the current page", as the ticket says). With an unknown page count (`pages` 0, e.g. a document without `cptdp_total_pages` and no images) the label is empty, the bar is 0 and `aria-valuetext` is empty. With 1 page: `1 / 1`, bar full; the footer still shows both buttons.
- `aria-valuetext` uses `positionText( words, page, page, pages )` so slides read "Slide 3 of 12" and pages "Page 5 of 24".
- Buttons are real `<button type="button">`; on click each dispatches a bubbling `CustomEvent` on the mobile dialog: `documents:mobile-pages` / `documents:mobile-details`, `detail` = a copy of the mobile state (`{ id, page, pages }`). Nothing else happens (the sheets are later stories).

### E. Mobile body (blank for now)
`div.documents-mobile__body` (`flex: 1 1 auto; min-height: 0; position: relative; overflow: hidden; background: var(--dm-surface-alt)`) contains the empty `div.documents-mobile__stage` and the progress bar anchored to its bottom edge. No viewer is created for the mobile dialog (so a phone downloads no pdf.js/ooxml/StPageFlip because of this change). DPD-021 fills the stage and listens to `documents:mobile-change`.

### F. Events
`documents:mobile-change` on the mobile dialog after open and every page change, `detail: { id, page, pages }`; same idea as the desktop `documents:modal-change`.

## Implementation: file by file

### 1. `blocks/document-modal/render.php`
Keep everything as is; after the closing `</dialog>` of the desktop modal (and before the `<script type="application/json" ...>`), add the mobile dialog. New PHP variable (prefix `$devportfolio_`): `$devportfolio_mobile_title_id = wp_unique_id( 'documents-mobile-title-' );`; reuse `$devportfolio_download` (the download SVG, already defined) for the header icon. Reference markup (copy the phpcs ignore comments used for the existing static markup):

```php
<dialog class="documents-mobile" aria-labelledby="<?php echo esc_attr( $devportfolio_mobile_title_id ); ?>">
	<div class="documents-mobile__panel">
		<header class="documents-mobile__header">
			<button class="documents-mobile__close documents-mobile__btn" type="button" aria-label="<?php echo esc_attr_x( 'Close viewer', 'document modal', 'devportfolio' ); ?>">
				<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
			<h2 class="documents-mobile__title" id="<?php echo esc_attr( $devportfolio_mobile_title_id ); ?>"></h2>
			<a class="documents-mobile__download documents-mobile__btn" role="button" aria-label="<?php echo esc_attr_x( 'Download', 'document modal button', 'devportfolio' ); ?>">
				<?php echo $devportfolio_download; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
			</a>
		</header>
		<div class="documents-mobile__body">
			<div class="documents-mobile__stage"></div>
			<div class="documents-mobile__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="" aria-label="<?php echo esc_attr_x( 'Reading progress', 'document modal', 'devportfolio' ); ?>" hidden><i></i></div>
		</div>
		<footer class="documents-mobile__footer">
			<div class="documents-mobile__row">
				<button class="documents-mobile__pages documents-mobile__textbtn" type="button">
					<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>
					<span><?php echo esc_html_x( 'Pages', 'document modal mobile footer', 'devportfolio' ); ?></span>
				</button>
				<b class="documents-mobile__position"></b>
				<button class="documents-mobile__details documents-mobile__textbtn" type="button">
					<span><?php echo esc_html_x( 'Details', 'document modal mobile footer', 'devportfolio' ); ?></span>
					<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg>
				</button>
			</div>
		</footer>
	</div>
</dialog>
```
Notes: the existing `.documents-modal__icon` rule applies `fill: none; stroke: currentcolor` and the 18px size, which the rects need too. The `<a role="button">` has no `href` until JS sets it (as on desktop). No `http(s)://` anywhere in this file (inline SVG in HTML needs no `xmlns`). Update the file docblock to mention the mobile dialog. Update `block.json` `description` (it says "desktop only") to say the block holds a desktop and a mobile modal.

### 2. `blocks/document-modal/download.js` (new, ~35 lines, no URLs)
```js
/** Download link of the modal, shared by the desktop and the mobile header (DPD-018). */
export function applyDownload( link, doc ) {
	link.removeAttribute( 'href' );
	link.removeAttribute( 'download' );
	link.removeAttribute( 'target' );
	link.removeAttribute( 'rel' );
	link.removeAttribute( 'aria-disabled' );
	link.setAttribute( 'aria-label', `Download ${ doc.title }` );
	if ( doc.downloadMode === 'file' ) {
		link.href = doc.downloadUrl;
		link.setAttribute( 'download', '' );
	} else if ( doc.downloadMode === 'embed' ) {
		link.href = doc.downloadUrl;
		link.target = '_blank';
		link.rel = 'noopener';
	} else {
		link.setAttribute( 'aria-disabled', 'true' );
	}
}

/** A disabled Download link does nothing when tapped. Call once per link. */
export function guardDisabledDownload( link ) {
	link.addEventListener( 'click', ( event ) => {
		if ( link.getAttribute( 'aria-disabled' ) === 'true' ) {
			event.preventDefault();
		}
	} );
}
```
This is a copy of the existing `drawHeader()` block and the existing `els.download` click guard in `view.js`; desktop behaviour must stay identical.

### 3. `blocks/document-modal/pager.js`: add one pure helper
```js
/**
 * What the mobile footer shows (DPD-019): `2 / 24` and the progress percent, one page at a time.
 * An unknown count (0) gives an empty label and 0 percent instead of `0 / 0`.
 */
export function mobileFooterParts( page, pages ) {
	const count = Math.max( 0, Math.floor( Number( pages ) ) || 0 );
	if ( count < 1 ) {
		return { page: 1, label: '', percent: 0 };
	}
	const current = normalisePage( page, count, 1 );
	return {
		page: current,
		label: `${ current } / ${ count }`,
		percent: Math.round( ( current / count ) * 10000 ) / 100,
	};
}
```

### 4. `blocks/document-modal/mobile.js` (new): the mobile dialog's own logic
API: `createMobileModal( { dialog, units, onClose } )` returns `{ show( doc, page = 1 ), close(), isOpen(), state(), setPage( page ) }`. It imports `normalisePage`, `turnPage`, `mobileFooterParts` from `./pager.js`; `unitOf`, `wording`, `positionText` from `./mode.js`; `applyDownload`, `guardDisabledDownload` from `./download.js`. It must **not** import `deeplink.js` and must not touch `history`. Reference implementation:

```js
export function createMobileModal( { dialog, units = {}, onClose } ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		title: $( '.documents-mobile__title' ),
		close: $( '.documents-mobile__close' ),
		download: $( '.documents-mobile__download' ),
		pages: $( '.documents-mobile__pages' ),
		details: $( '.documents-mobile__details' ),
		position: $( '.documents-mobile__position' ),
		bar: $( '.documents-mobile__bar' ),
	};
	const state = { id: 0, page: 1, pages: 0 };
	let words = wording( 'page', units );

	const emit = ( name ) => dialog.dispatchEvent( new CustomEvent( name, { bubbles: true, detail: { ...state } } ) );

	const drawFooter = () => {
		const { page, label, percent } = mobileFooterParts( state.page, state.pages );
		state.page = page;
		els.position.textContent = label;
		els.bar.hidden = state.pages < 2;
		els.bar.firstElementChild.style.width = `${ percent }%`;
		els.bar.setAttribute( 'aria-valuenow', String( Math.round( percent ) ) );
		els.bar.setAttribute( 'aria-valuetext', state.pages > 0 ? positionText( words, page, page, state.pages ) : '' );
	};

	const setPage = ( page ) => {
		state.page = normalisePage( page, state.pages, 1 );
		drawFooter();
		emit( 'documents:mobile-change' );
	};

	guardDisabledDownload( els.download );
	els.close.addEventListener( 'click', () => dialog.close() );
	els.pages.addEventListener( 'click', () => emit( 'documents:mobile-pages' ) );
	els.details.addEventListener( 'click', () => emit( 'documents:mobile-details' ) );
	dialog.addEventListener( 'close', () => onClose && onClose() );

	// Left and Right turn the page until the body story adds swipe and tap (same guards as the desktop keys).
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
			return;
		}
		if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
			return;
		}
		if ( event.target && event.target.closest && event.target.closest( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}
		event.preventDefault();
		setPage( turnPage( state.page, state.pages, 1, event.key === 'ArrowRight' ? 1 : -1 ) );
	} );

	return {
		show( doc, page = 1 ) {
			state.id = doc.id;
			words = wording( unitOf( doc ), units );
			state.pages = ( Array.isArray( doc.images ) && doc.images.length ) || doc.pages || 0;
			state.page = normalisePage( page, state.pages, 1 );
			els.title.textContent = doc.title;
			applyDownload( els.download, doc );
			drawFooter();
			dialog.showModal();
			els.close.focus();
			emit( 'documents:mobile-change' );
		},
		close() {
			if ( dialog.open ) {
				dialog.close();
			}
		},
		isOpen: () => dialog.open,
		state: () => ( { ...state } ),
		setPage,
	};
}
```
Check: `normalisePage( page, 0, 1 )` returns 1 (`Math.min( x, 0 )` -> 0, then `Math.max( 1, 0 )` -> 1), so an unknown count is safe; `turnPage` with `pages` 0 returns the same page (both ends disabled), so the arrow keys do nothing. Read `mode.js` before wiring: `wording()` is already called as `wording( unitOf( doc ), units )` in `view.js` `open()`; mirror that exactly.

### 5. `blocks/document-modal/view.js`: routing, history, swap (the careful part)
Make these changes, keeping all existing desktop behaviour:

1. **Imports and header comment.** Add `import { createMobileModal } from './mobile.js';` and `import { applyDownload, guardDisabledDownload } from './download.js';`. Update the file docblock (it says "on screens wider than 700px").
2. **Elements.** After `dialog`/`dataEl`: `const mobileDialog = root.querySelector( '.documents-mobile' );`. Keep `desktop = window.matchMedia( '(min-width: 701px)' )`.
3. **`drawHeader()`**: replace the whole `link` block with `applyDownload( els.download, doc );`, and replace the `els.download` click-guard listener near the bottom with `guardDisabledDownload( els.download );`. (Pure refactor.)
4. **Create the mobile modal** after `units = readJson( 'data-units' )`: `const mobile = mobileDialog ? createMobileModal( { dialog: mobileDialog, units, onClose: () => finishClose( 'mobile' ) } ) : null;`. Declare `let swapping = false;` next to `pushed` / `closingFromHistory`.
5. **Helpers:**
   ```js
   const isOpen = () => dialog.open || ( mobile !== null && mobile.isOpen() );
   const closeOpen = () => {
   	if ( dialog.open ) {
   		dialog.close();
   	} else if ( mobile ) {
   		mobile.close();
   	}
   };
   ```
6. **Split `open()`** into `openDesktop( doc, page )` (everything the current function does from `state.id = doc.id` onward, taking the page: `state.page = normalisePage( page, state.pages, state.step )` instead of `1`; it still calls `drawHeader`, `drawFooter`, `dialog.showModal()`, `els.close.focus()`, `emit()` and `viewer.show( doc, state.page )`) and a new `open( id, trigger, { push = true, page = 1 } = {} )`:
   ```js
   const open = ( id, trigger, { push = true, page = 1 } = {} ) => {
   	const doc = documents[ id ];
   	if ( ! doc || isOpen() ) {
   		return;
   	}
   	if ( push && doc.slug ) {
   		window.history.pushState( { documentSlug: doc.slug }, '', urlWithSlug( window.location.href, doc.slug ) );
   		pushed = true;
   	}
   	opener = trigger;
   	document.documentElement.style.overflow = 'hidden';
   	if ( desktop.matches || ! mobile ) {
   		openDesktop( doc, page );
   	} else {
   		mobile.show( doc, page );
   	}
   };
   ```
   `openDesktop` no longer sets `overflow` or `opener` itself (moved to `open()`; the swap path touches neither because the lock and the opener are already in place). Callers that used `open( id, trigger, false )` become `open( id, trigger, { push: false } )`.
7. **Click handler:** delete the `|| ! desktop.matches` early return so a tap on a small screen reaches `open()`.
8. **One close routine, called by both dialogs' `close` events:**
   ```js
   const finishClose = ( which ) => {
   	if ( which === 'desktop' ) {
   		viewer.destroy();
   	}
   	if ( swapping ) {
   		// The other dialog took over: keep the scroll lock, the opener and the URL as they are.
   		swapping = false;
   		return;
   	}
   	document.documentElement.style.overflow = '';
   	if ( opener && document.contains( opener ) ) {
   		opener.focus();
   	}
   	opener = null;
   	/* ...the existing three-way URL cleanup (closingFromHistory / pushed / replaceState) unchanged... */
   };
   dialog.addEventListener( 'close', () => finishClose( 'desktop' ) );
   ```
   The desktop `viewer.destroy()` must still run on a swap to mobile (the viewer holds workers/canvases), and must **not** run when the closing dialog is the mobile one (a desktop viewer was just started). `swapping` is cleared here, in the handler, because `close` fires asynchronously (gotcha 2).
9. **`popstate`:** drop the `desktop.matches` condition and use the helpers:
   ```js
   window.addEventListener( 'popstate', () => {
   	if ( swapping ) {
   		return;
   	}
   	const slug = slugFromUrl( window.location.href );
   	if ( ! slug && isOpen() ) {
   		closingFromHistory = true;
   		closeOpen();
   	} else if ( slug && ! isOpen() && idBySlug[ slug ] ) {
   		open( idBySlug[ slug ], null, { push: false } );
   		pushed = true;
   	}
   } );
   ```
10. **First-load deeplink:** remove the `else if ( desktop.matches )` gate; a known slug always opens, on the dialog that fits: `open( idBySlug[ linked ], document.querySelector( ...shelf button... ), { push: false } )` (keep the existing `pushed` semantics: false, because the entry was not pushed by the modal). An unknown slug is still dropped with `replaceState`.
11. **Replace the final `desktop.addEventListener( 'change', ... )`** (which closes) with the swap:
   ```js
   const swap = () => {
   	if ( swapping || ! mobile || ! isOpen() ) {
   		return;
   	}
   	const mobileOpen = mobile.isOpen();
   	if ( mobileOpen === ! desktop.matches ) {
   		return; // already the right dialog
   	}
   	const doc = documents[ mobileOpen ? mobile.state().id : state.id ];
   	const page = mobileOpen ? mobile.state().page : state.page;
   	const run = () => {
   		swapping = true;
   		if ( mobileOpen ) {
   			mobile.close();
   			openDesktop( doc, page );
   		} else {
   			dialog.close();
   			mobile.show( doc, page );
   		}
   	};
   	const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
   	if ( ! document.startViewTransition || reduce ) {
   		run();
   		return;
   	}
   	const html = document.documentElement;
   	html.setAttribute( 'data-documents-swapping', '' );
   	const transition = document.startViewTransition( run );
   	transition.finished.finally( () => {
   		html.removeAttribute( 'data-documents-swapping' );
   		swap(); // the viewport may have crossed 700px again meanwhile
   	} );
   };
   desktop.addEventListener( 'change', swap );
   ```
   If `swapping` is still true when `finished` resolves (the old dialog's `close` event has not run yet), the guard at the top returns; therefore also call `swap()` at the end of `finishClose()` right after it clears `swapping`, so a viewport that moved on during the transition still gets corrected. `swap()` is a no-op when the open dialog already fits the viewport. `openDesktop` receives the full `doc` object and does not reset `opener`.
12. If `mobile` is null (the mobile markup is missing, e.g. a stale cached page), `open()` falls back to `openDesktop`, which CSS hides at 700px or less. Accept this degenerate case; do not add more code for it.

### 6. `blocks/document-modal/style.css`
1. **Tokens shared.** Split the first rule (line 2): move the custom properties (`--dm-*`) into `.documents-modal,\n.documents-mobile { ... }` and keep the desktop box rules (`box-sizing`, `width`, `height`, `margin`, `border`, `border-radius`, `box-shadow`, ...) under `.documents-modal` unchanged. Do not change any desktop value.
2. **Mobile section** "Mobile modal (DPD-017 to DPD-019)", placed before the existing `@media (max-width: 1200px)` block, using the declarations in "Target behaviour" C/D/E. Reference CSS for the dialog and panel:
   ```css
   .documents-mobile {
   	box-sizing: border-box;
   	width: 100%;
   	max-width: none;
   	height: 100vh;
   	height: 100dvh;
   	max-height: none;
   	margin: 0;
   	padding: 0;
   	inset: 0;
   	overflow: hidden;
   	color: var(--dm-ink);
   	background: var(--dm-surface);
   	border: 0;
   	border-radius: 0;
   	box-shadow: none;
   }
   .documents-mobile::backdrop { background: transparent; }
   .documents-mobile[open] { animation: documents-mobile-up var(--dm-dur) var(--dm-ease); }
   @keyframes documents-mobile-up { from { opacity: 0; transform: translateY(24px); } }
   .documents-mobile__panel { display: flex; flex-direction: column; height: 100%; }
   ```
   No `display` on `.documents-mobile` itself (gotcha 1). Then header, `.documents-mobile__btn`, `.documents-mobile__title`, body/stage, footer, row, text buttons, position, bar, focus-visible, as specified in C, D, E.
3. **Breakpoint rules.** Keep the existing `@media (max-width: 700px) { .documents-modal, .documents-modal__footer { display: none; } }` exactly (update only its comment: the desktop modal is hidden because the mobile modal takes over). Add `@media (min-width: 701px) { .documents-mobile { display: none; } }`.
4. **View Transition for the swap** (modelled on `document-categories/style.css:33-52`):
   ```css
   .documents-modal,
   .documents-mobile { view-transition-name: documents-modal; }
   ::view-transition-group(documents-modal),
   ::view-transition-old(documents-modal),
   ::view-transition-new(documents-modal) {
   	animation-duration: 0.3s;
   	animation-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
   }
   /* While swapping: no second open animation, and the books behind do not join the transition. */
   html[data-documents-swapping] .documents-modal[open],
   html[data-documents-swapping] .documents-mobile[open],
   html[data-documents-swapping] .documents-modal[open]::backdrop { animation: none; }
   html[data-documents-swapping] .documents-item,
   html[data-documents-swapping] .documents-shelf__plank { view-transition-name: none !important; }
   ```
   (`!important` is needed because the shelf sets the names inline.) The root transition is already disabled globally by `document-categories/style.css` (`::view-transition-old(root), ::view-transition-new(root) { animation: none }`); do not duplicate it, but verify it applies (the Documents page always has the categories block).
5. **Reduced motion:** extend the existing `@media (prefers-reduced-motion: reduce)` block: `.documents-mobile[open] { animation: none }`, `.documents-mobile__bar i { transition: none }`, and `::view-transition-group(documents-modal), ::view-transition-old(documents-modal), ::view-transition-new(documents-modal) { animation: none }`.
6. **High contrast:** the mobile dividers use `--dm-line-strong`, which is already high contrast; just verify with the browser's forced-contrast emulation that the dividers stay visible. No extra rule expected.

### 7. `AGENTS.md` (docs step)
- In the theme-architecture `document-modal` paragraph add a "Mobile modal (DPD-017/018/019)" block: the second dialog `.documents-mobile`, files `mobile.js`, `download.js`, `pager.js` `mobileFooterParts`, the events `documents:mobile-change` / `-pages` / `-details`, the swap with View Transition (`data-documents-swapping`, shared `view-transition-name: documents-modal`), blank body for the next story.
- In the **Deeplink (DPD-032)** paragraph, replace "Nothing opens at 700px or less (DPD-017)" with: the link opens the mobile modal at 700px or less (DPD-017) and Back closes it.
- Update the "Expected totals" line for the theme suite to the real count after your tests.
- Do not touch unrelated lines of the already modified `AGENTS.md`; stage only your hunks.

## Steps (one commit each; both suites green after each)

1. **Pure helpers and shared module.** `feat(theme): add the mobile footer helper and shared download link`: add `mobileFooterParts()` to `pager.js`, create `download.js`, switch `view.js` `drawHeader()` and the click guard to it (desktop behaviour unchanged). Then `test(theme): cover the mobile footer helper` (`blocks/document-modal/tests/pager.test.mjs` additions, see Tests). Run the node tests and the theme suite (the document-modal tests must still pass).
2. **Markup and CSS shell (DPD-017).** `feat(theme): add the mobile document modal shell`: `render.php` mobile dialog (header, body, footer markup from the start so later steps only add behaviour), `style.css` (tokens split, mobile dialog, breakpoint rules, reduced motion), `block.json` description. No JS yet: verify at 390px that a tap still does nothing and at 1200px nothing changed.
3. **Routing, Back button, first load (DPD-017).** `feat(theme): open the mobile modal and close it with the Back button`: `mobile.js` (full file is fine, it is one unit), `view.js` changes 1-10 (imports, `open()` split, click routing, `finishClose`, `popstate`, first-load deeplink). Then `test(theme): cover the mobile modal shell`.
4. **Header (DPD-018).** `feat(theme): add the mobile modal header`: header CSS and ellipsis; confirm title, Download wiring and close from step 3. Then `test(theme): cover the mobile modal header`.
5. **Footer (DPD-019).** `feat(theme): add the mobile modal footer`: footer CSS, footer drawing, arrow-key paging, Pages/Details events (JS already in `mobile.js`; this commit lands the CSS and any glue). Then `test(theme): cover the mobile modal footer`.
6. **Swap with View Transition.** `feat(theme): swap between the desktop and mobile modals on resize`: `view.js` change 11 + the View Transition CSS. Then `test(theme): cover the modal swap`. Also add `mobile.js` and `download.js` to the file list in `test_viewer_scripts_have_no_external_urls`.
7. **Docs.** `docs(agents): document the mobile document modal`.
8. **Plan.** `docs(plans): add DPD-017-018-019 plan`: this file only, last.

If you prefer fewer commits, steps 2 to 5 may be merged by ticket (DPD-017, DPD-018, DPD-019), but keep tests in separate commits and keep the swap (step 6) separate.

Open a PR: title `feat(documents): DPD-017/018/019 mobile document modal`, body from `.github/pull_request_template.md`, listing the three owner decisions and the manual checks you ran.

## Tests

### PHPUnit (theme): new file `tests/test-document-modal-mobile.php`, class `Devportfolio_Test_Document_Modal_Mobile`, `@group theme`
Copy the `set_up()`, `theme_dir()`, `read()`, `make_document()`, `render_modal()` and `payload_from()` helpers from `tests/test-document-modal.php` (they are private there; duplicate them rather than refactor the existing class, as the other theme test classes do). `set_up()` must call `do_action( 'cptdp_register_post_types' )`, `require_once` the theme `functions.php`, re-add `add_action( 'template_redirect', 'devportfolio_redirect_document_single', 1 )` and register the blocks if needed, exactly like the existing class. Structure and CSS are asserted on text; there is no browser runner.
1. `test_renders_the_mobile_dialog_after_the_desktop_one`: `render_modal()` contains exactly one `<dialog class="documents-mobile"` and one `<dialog class="documents-modal"`, desktop first, and exactly one JSON payload script.
2. `test_mobile_dialog_has_header_body_footer_in_order`: header, then `documents-mobile__body`, then footer (positions via `strpos`), all inside `documents-mobile__panel`; the body contains an empty `documents-mobile__stage`.
3. `test_mobile_header_elements_and_accessible_names`: close button has `aria-label="Close viewer"` and the X path; `h2.documents-mobile__title` has an `id` and the dialog's `aria-labelledby` equals it; the download link has `role="button"`, an `aria-label`, the download path and **no visible text** (no `<span>` inside).
4. `test_mobile_header_order_close_title_download`: close, title, download appear in that order inside the header.
5. `test_mobile_footer_buttons_and_bar`: `documents-mobile__pages` (text "Pages", before the position), `documents-mobile__position`, and `documents-mobile__details` (text "Details", after the position); the footer has **no `hidden` attribute** (owner decision 2). The progressbar is inside the body, anchored to its bottom edge, and its visibility is controlled by the page count.
6. `test_mobile_footer_buttons_are_real_buttons`: both are `<button type="button">`.
7. `test_mobile_strings_are_translatable`: `render.php` source contains `esc_html_x( 'Pages'` and `esc_html_x( 'Details'` with the `devportfolio` domain, and no hard-coded English outside those calls.
8. `test_payload_is_unchanged_and_has_what_mobile_needs`: a published document's payload still has `id`, `slug`, `title`, `pages`, `downloadMode`, `downloadUrl`, `images`; a draft is excluded.
9. CSS (`blocks/document-modal/style.css`, regex on the text):
   - `test_css_mobile_dialog_is_full_screen`: `.documents-mobile` rule has `width: 100%`, `height: 100dvh`, `max-width: none`, `max-height: none`, `margin: 0`, `border-radius: 0`, `border: 0`; `.documents-mobile::backdrop` has `background: transparent`; the `.documents-mobile {` rule has **no** `display:` declaration (gotcha 1); `.documents-mobile__panel` has `display: flex` and `flex-direction: column`.
   - `test_css_breakpoints_pair_up`: the existing max-width 700 rule hiding `.documents-modal, .documents-modal__footer` is still there, and `@media (min-width: 701px)` hides `.documents-mobile`.
   - `test_css_mobile_title_is_one_line_serif_bold`: `.documents-mobile__title` has `Georgia`, `font-weight: 700`, `white-space: nowrap`, `overflow: hidden`, `text-overflow: ellipsis`, `min-width: 0`.
   - `test_css_mobile_buttons_are_round`: `.documents-mobile__btn` has `border-radius: 999px` and `40px` width and height.
   - `test_css_mobile_dividers`: header has `border-bottom: 1px solid`, footer has `border-top: 1px solid`.
   - `test_css_mobile_footer_row_centres_the_indicator`: `.documents-mobile__row` has `grid-template-columns: 1fr auto 1fr`.
   - `test_css_mobile_bar_fill_uses_the_gradient_and_transitions_width`: `.documents-mobile__bar i` has `var(--dm-grad)` and `transition: width`; the bar is positioned at the bottom of `.documents-mobile__body`.
   - `test_css_tokens_shared`: the token rule's selector list contains both `.documents-modal` and `.documents-mobile`.
   - `test_css_swap_transition`: both dialogs get `view-transition-name: documents-modal`; `::view-transition-group(documents-modal)` exists; `html[data-documents-swapping]` neutralises `.documents-item` names with `!important`; the reduced-motion block turns the swap animation, the mobile open animation and the bar transition off.
10. JS structure (read the source and assert strings, as `test-document-modal.php` does for `view.js`): `view.js` imports `./mobile.js` and `./download.js`; `view.js` contains `startViewTransition` and `prefers-reduced-motion`; the old close-on-shrink handler is gone (no `desktop.addEventListener( 'change'` followed by `dialog.close()`); `mobile.js` does not contain `history.` and does not import `deeplink.js`; `mobile.js` and `download.js` contain no `http://` or `https://`.
11. In `tests/test-document-viewer.php:544` add `'mobile.js'` and `'download.js'` to the file list of `test_viewer_scripts_have_no_external_urls`.
12. `tests/test-document-modal.php`: change only what your work invalidates. The rule at line 334 must pass untouched. If the first-rule split breaks `test_css_shell_rules` assertions (`width: min(1680px, ...)`, `border-radius: 14px`), the cause is the selector split: fix the CSS, not the test.

### Node (`blocks/document-modal/tests/pager.test.mjs`, add to the existing file; use `node:test` and `node:assert/strict` as it does)
`mobileFooterParts`: `( 1, 24 )` -> `{ page: 1, label: '1 / 24', percent: 4.17 }`; `( 2, 24 )` -> `'2 / 24'`, `8.33`; `( 24, 24 )` -> `100`; `( 1, 1 )` -> `'1 / 1'`, `100`; `( 5, 0 )` -> `{ page: 1, label: '', percent: 0 }`; `( 99, 24 )` clamps to page 24; `( 0, 24 )` and `( -3, 24 )` -> page 1; `( 2.7, 24 )` -> page 2; string input `( '3', '24' )` -> `'3 / 24'`; `( 3, undefined )` -> empty label. Also assert `turnPage( 24, 24, 1, 1 )` stays 24 and `turnPage( 1, 24, 1, -1 )` stays 1 (the mobile keys rely on it).

### Manual verification (no browser runner exists; do all of these and report the results in the PR)
Use device emulation 390x844 (touch), then desktop 1280x800, at `https://devportfolio.ddev.site/documents/`. Use a document with an uploaded PDF (24 pages ideal) and one seeded document with no file.

| # | Case | Expect |
|---|---|---|
| 1 | Tap a book at 390px | Full-screen modal, no dimmed backdrop, header + blank body + footer; URL gains `?document_slug=<slug>`; background does not scroll |
| 2 | Browser/device Back | Modal closes, still on `/documents/`, shelf and scroll position as before; Forward reopens it |
| 3 | Close with X; with Esc | Modal closes, URL param gone, **one** Back leaves the page (no extra dead entry) |
| 4 | Header layout | X left, title between, download icon right, thin divider below; title on one line |
| 5 | Very long title | Cut with `...`, buttons not pushed off screen |
| 6 | Download with a file | File downloads; an `embed` doc opens a new tab; a no-file doc: icon dimmed, tap does nothing |
| 7 | Footer layout | `Pages` left, bold `1 / 24` exactly centred, `Details` right; divider above the footer; progress bar spans the bottom edge of the body |
| 8 | Left/Right arrow keys (external keyboard or emulator) | `2 / 24`, bar 8.33%; stops at `1 / 24` and `24 / 24` |
| 9 | 1-page doc and a doc with unknown page count | Footer still shown; `1 / 1` has no progress bar, and unknown count has an empty label with no progress bar |
| 10 | Tap Pages, Details | Event dispatched (check in the console with an `addEventListener`), nothing opens, no error |
| 11 | Open `/documents/?document_slug=<slug>` fresh at 390px | Mobile modal opens on that document; Back closes it and stays on the page; an unknown slug is removed from the URL |
| 12 | Same URL at 1280px | Desktop modal, unchanged |
| 13 | Open at 1280px, drag the window to 600px | View Transition: the framed modal morphs/fades into the full-screen mobile one; same document and page; URL unchanged; shelf books do not flicker or paint over it; the reverse works at 600 -> 800 (desktop book lands on the odd page of the spread) |
| 14 | Same with OS "reduce motion" on, and in a browser without `startViewTransition` | Instant swap, no animation, no error, no stuck `data-documents-swapping` attribute |
| 15 | Wiggle the window across 700px repeatedly | Never two dialogs open, never a stuck scroll lock, history length unchanged, Back still closes once |
| 16 | After a swap, close with X, then Back | Focus returns to the tapped book; URL clean; one Back leaves the page |
| 17 | iOS Safari / Android Chrome (or their emulation) | Footer not clipped by the address bar (`dvh`), safe areas respected, 40px+ touch targets |
| 18 | Desktop regression pass | Click book; header, pager, arrows, keys, Download, Close, backdrop, Esc, deeplink, Back all as before |

## Acceptance criteria (definition of done)
- Every ticket acceptance bullet above is demonstrably true (manual rows 1-8, 11, 12).
- The three owner decisions are implemented (rows 9, 11, 13-16).
- Theme PHPUnit suite and Node tests pass with the new tests; the plugin suite still passes untouched; no `https?://` in the new JS/PHP files; no console errors at either viewport.
- Desktop behaviour is unchanged (row 18) and the existing assertion for the `max-width: 700px` rule still passes.
- `AGENTS.md` updated (theme architecture, DPD-032 sentence, suite totals); plan file committed.

## Risks and mitigations
1. **Two dialogs, one URL.** Both `close` events funnel through `finishClose()`; the flags `pushed`, `closingFromHistory` and `swapping` live in one place. Test: rows 2, 3, 15, 16.
2. **`history.back()` on a deeplink-opened modal would leave the site.** The existing `pushed` flag guards it (a deeplink open leaves `pushed` false; the `popstate` reopen path sets it true). Re-test on mobile first.
3. **Async `close` event vs flags** (gotcha 2): clear `swapping` / `closingFromHistory` in the handler only. A swap must not unlock scroll, restore focus or touch the URL.
4. **Shelf items and planks carry their own `view-transition-name`s** and would be captured as transition groups during the swap (they have a 0.4s group rule), causing flicker. Mitigation: `html[data-documents-swapping]` sets their names to `none !important`; the attribute is removed when the transition finishes. If a browser still flickers, also give `::view-transition-group(documents-modal)` a `z-index` above the shelf groups.
5. **Top-layer dialog and `::backdrop` in a View Transition.** Current Chrome and Safari capture the dialog (named) and its backdrop with it. If the backdrop pops at the start or end, replace the geometry morph with a cross-fade: `::view-transition-group(documents-modal) { animation: none }` and keep the old/new opacity fades.
6. **`dialog.showModal()` replays the open animation** (`documents-modal-pop` / `documents-mobile-up`): suppressed via `data-documents-swapping` (CSS above).
7. **iOS Safari `100vh` and the address bar:** use `100dvh`; the footer lives inside the flex column, never `position: fixed`.
8. **Shared `drawHeader()` refactor touches desktop code:** keep `applyDownload()` identical to the old block; the existing desktop modal tests and manual row 18 guard it.
9. **A footer count the body cannot yet confirm.** The body story may report a different page count; the footer always redraws from `state`, never caches a count, and `setPage()` re-clamps.
10. **`viewScriptModule` with new relative imports** works the same as the existing `./pager.js` import; no registration change. If a cache serves the old `view.js` with new markup (or the reverse), the desktop path still works and CSS hides what does not apply.
11. **Slug renames break old links** (unchanged from DPD-032; not this ticket's concern).

## Questions
None open: all three owner decisions are resolved above. If something in the code contradicts a statement in this plan (line numbers drift as the branch moves), trust the code, keep the intent, and note the difference in the PR.
