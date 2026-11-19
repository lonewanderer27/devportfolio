# Plan: Videos empty and error states, phone grid, accessibility, media loading and field QA (DPV-025, DPV-027, DPV-034, DPV-039, DPV-040)

Tickets:
- **DPV-025** Backend · Video data · done in DPB-006/011/018 · Low. No build work: a **QA check** that every stored video field reaches the grid and the modals, and that a video with no original post link hides "Open original post".
- **DPV-027** Page · Empty and error states · depends on DPV-008 · Medium. No videos; a category with no videos; a video that fails to load (message plus "Try again"; a watch link for an embed).
- **DPV-034** Layout · Videos mobile · depends on DPV-008 · Medium. Phone grid: about 2/3/4 columns, posters only, nothing plays while scrolling, dropdown under the nav.
- **DPV-039** Foundation · Accessibility · depends on DPV-009 · High. Keyboard and screen reader; 44 x 44px touch targets.
- **DPV-040** Backend · Performance · depends on DPV-005, DPV-025 · Low. Posters lazy, a video loads only when opened, only the next one is preloaded (metadata), previews load on hover only.

Status: **draft, nothing implemented.** Theme changes, plus one plugin change: **a manual preview upload** (phase 8, confirmed by Dave after this plan was written). The plugin change bumps the plugin `Version` header and the AGENTS.md overview.

**Written for a small model (Haiku 5.5 or similar).** Every new code block is final; every edit names its anchor; every gate is a command. Do the phases in order, run each **Gate**, and **stop and report** when a "Fact" in section 2 is false or a gate fails. Do not build anything listed in section 1 under "Out of scope".

`$T` = `wp-content/themes/devportfolio`. Related plans (history only, do not edit): `collaterals/DPC-025-Collateral-Empty-And-Error-States.md` (the pattern copied in phase 1), `videos/DPV-006-007-008-...-Grid.md`, `videos/DPV-009-...-Video-Modal-Desktop.md`, `videos/DPV-011-...-Mobile-Viewer.md`, `videos/DPV-017-018-029-...-Player-And-Controls.md`, `videos/DPV-030-...-Motion-Popout-Skeleton.md`, `videos/DPV-041-Video-Backend-Fields-Autofill.md`.

---

## 0. What is already built (read first)

Most of these four tickets is already true. Reading the code on `main` (`54e6570`):

| Story line | State today |
|---|---|
| DPV-025 fields reach the page | **Built.** `devportfolio_video_modal_payload()` carries title, description, `categories`, `ratio`/`pane`/`shape`, `stats`, `length`, `fileUrl`, `embedSrc`, `postUrl`, `poster`, tools (via the payload base); the grid uses title, poster, categories, ratio and menu order. "Open original post" is hidden without a `postUrl` in the desktop dialog (`view.js` `fill()`) and the mobile sheet (`details-sheet.js`); `postUrl` is accepted only as http(s). `cptdp_highlight` and the published date are stored but **not read by the theme** (see section 8). |
| DPV-027 poster skeleton | **Built** (DPV-038: `is-loaded` / `is-failed` in `video-grid/view.js`). Only verified here. |
| DPV-027 no videos / empty category | **Missing.** `video-grid/render.php` returns nothing when there are no videos; the pills still render; a category with no videos collapses silently. |
| DPV-027 file fails in the modal | **Half.** `player.js` `error` listener shows "This video can’t be previewed here." and the hint, no "Try again", no link. |
| DPV-034 columns, posters only, no autoplay | **Built.** Grid is `repeat(auto-fill, minmax(clamp(104px, 72px + 8vw, 180px), 1fr))`; the bar, mute badge and ring have `opacity: 0` at rest; hover preview needs `(hover: hover) and (pointer: fine)` (`preview.js` `canPreview()`). Dropdown under the nav is DPD-003, shared. Only measured and pinned here. |
| DPV-039 tab, Enter/Space, 2px outline + 3px gap | **Built** (`.videos-item__open` is a `<button>` named by the title; `:focus-visible` is `outline: 2px solid` with `outline-offset: 3px`). Esc, focus return to the tile: **built** (`finishClose()` in `video-modal/view.js`). Space play/pause: **built** (`player.js` keydown). Scrub is `input[type=range]` with a label: **built**. |
| DPV-039 ← → step between videos | **Missing on desktop.** `view.js` has no key handler; the mobile dialog uses ArrowUp/Down for previous/next and ←/→ for seek (keep). |
| DPV-039 44 x 44px on touch | **Not met.** `video-modal/style.css` has buttons at 30, 36, 40 and 42px (lines ~86, 300, 407, 644, 701, 921, 1405). |
| DPV-040 posters lazy, no video/iframe on load | **Built** (`loading="lazy"` on posters; `<video>` and iframes are created on first use). |
| DPV-040 `preload="none"` everywhere, metadata for the next only | **Missing.** `preview.js`, `popout.js` and `player.js` all set `preload = 'auto'`; nothing preloads the next video's metadata. |
| DPV-040 open question "first 6 s or a separate file" | **Already decided** by DPV-041: the admin's browser makes a 6 s clip (`cptdp_video_preview`), `preview.js` loops it, and a file with no clip is cut at 6 s in script. Close the question. |

## 1. Goal, acceptance criteria and scope

**DPV-025 (QA only, no code)**
0. A video created with every field in the ticket shows in the grid (title as the tile's label, poster, category filter, position from order) and fills the desktop and mobile dialogs (description, shape, length, results, made-with tools, file or embed, original-post link); a video with no post URL has no "Open original post" in either dialog; no captions field exists.

**DPV-027**
1. No published videos: the title, one line `No videos yet.` (`role="status"`), no category pills, no grid.
2. A category with no videos (also `?category=<empty>` on first load): `No videos in this category` shows under the (empty) grid, toggled in the same synchronous handler as the filter.
3. A video file that fails to load in either dialog: the message, a `Try again` button (remounts the video), and, when the video has a post URL, a `Watch on the original site` link.
4. A video with neither a file nor a playable embed: the message and the watch link, no `Try again`.

**DPV-034**
5. At 390px the grid has 3 columns, at 320px 2 columns (measured, pinned by a CSS contract test).
6. No text, icon or control is visible on a tile at rest; nothing plays while scrolling.

**DPV-039**
7. In the desktop dialog `←` and `→` open the previous and next video of the active filter (not on the volume or seek sliders, which keep the arrows).
8. On a touch screen (`pointer: coarse`) every button, pill and thumbnail in the Videos page and both dialogs is at least 44 x 44px (the thumbnails already are).

**DPV-040**
9. No `<video>` is created with `preload` other than `none` or `metadata`, except the player of the open video (`auto`, it is about to play).
10. Opening a video preloads the **metadata** of the next one only (desktop: the next in the active filter; mobile: the ▼ video), never with data saver, and releases it on close.
11. First page load requests no video file and no YouTube/Vimeo script (verified, not built).

**DPV-040 manual preview (phase 8, confirmed by Dave)**
10a. The Video edit screen offers an optional **Preview clip** upload next to the Video File. An admin-chosen clip is used by the grid exactly like the generated one (`cptdp_video_preview`, loops, 6 s). A manual clip **replaces** the generated one; the generated attachment is deleted then (it is ours). A manual clip is **never deleted by the system** (AGENTS.md: a preview we did not make is never deleted). Clearing the field, or removing the video file, only unlinks the clip from the video (`cptdp_video_preview`); the attachment stays in the Media library.
10b. Limits and checks are those of the existing preview route: MP4 or WebM by magic bytes, 8 MB cap (filter `cptdp_video_preview_max_bytes`), attachment on the video's file meta. Nothing is transcoded or cut on the server; a longer clip is cut in script at 6 s as for files with no clip.

**Out of scope (do not build):** adaptive streaming, 360p renditions, a `cptdp_preview_file` meta (DPV-040 says so); detecting a failed YouTube/Vimeo iframe (cross-origin, no event: see section 6); captions; the Documents/Collaterals pages; a shared empty-state block (follow-up, see section 8). Plugin changes are limited to phase 8.

## 2. Facts verified in the repo (re-check the name before relying on a line number; stop and report if false)

- `$T/blocks/video-grid/render.php` does `if ( empty( $devportfolio_ids ) ) { return; }` and ends with `<div class="videos-outline" aria-hidden="true"></div></div>`; items are direct children `article.videos-item`.
- `$T/blocks/video-grid/view.js` `filter( slug )` loops `items` (direct `.videos-item` children), sets `item.hidden`, calls `releasePreview( item )` then `refreshPreviews()`; the handler is synchronous on `devportfolio:category-change`; the first `filter( pressedSlug() )` runs at load.
- `$T/blocks/video-grid/grid.js` exports only `matchesCategory( attr, slug )`.
- `$T/blocks/collateral-grid/style.css` holds `.collaterals-empty` (margin 44px 0 0, padding 56px 16px, min-height 160px, `--wp--preset--color--mid`, 14px, centred; 700px query: margin-top 28px, padding 40px 8px, min-height 120px), `.collaterals-empty[hidden] { display: none }` and `.documents-page__content:has(.collaterals-empty--none) .wp-block-devportfolio-document-categories { display: none; }`.
- `$T/blocks/video-modal/player.js` `createPlayer( dialog, strings, { prefix } )` finds elements as `.${prefix}__name`; `mount( item, gesture )` calls `unload()` then `mountFile`, `mountEmbed` or the no-source branch (`setState( 'embed' ); status.set( 'unavailable' )`); the file's `error` listener does `status.set( 'unavailable' ); setState( 'paused' )`; `unload()` ends with `status.set( null ); setState( 'paused' )`. Both dialogs use it (`prefix` `videos-modal` and `videos-mobile`). No control module reads `data-state` (grep `dataset.state|data-state` in `controls/` and `mobile.js` is empty); only `style.css` does.
- Payload items carry `fileUrl`, `embedSrc` and `postUrl` (`devportfolio_video_modal_payload()`); the status element is `p.videos-modal__state[role=status]` / `p.videos-mobile__state` with `.__state-text` and `.__state-hint`; `shared/status.js` is shared with Documents and Collaterals and **must not change**.
- `$T/blocks/video-modal/neighbours.js` `neighbours( order, items, currentId, slug )` returns `{ prev, next, index, count }` (ids or null, no wrapping). `view.js` imports `nextItems` etc. but **not** `neighbours`; `mobile.js` does and has `near()`.
- `view.js`: `fill( item, gesture )` ends with `player.mount( item, gesture ); renderNext();`; `swapTo( id )` changes video in place and focuses the title; `activeFilter()`; the two `close` listeners call `player.unload()` / `mobile.unload()`.
- `$T/blocks/video-grid/preview.js` `videoOf()` sets `video.preload = 'auto'`; `popout.js` line ~84 the same; `player.js` `mountFile()` the same.
- Existing tests to edit: `tests/test-video-grid.php::test_nothing_is_rendered_without_published_videos` (asserts `''`), and any test of `video-modal/render.php` that pins the state element's neighbours (run the suite to find it).

## 3. Decisions

- **D1. Copy the DPC-025 pattern, do not extract it yet.** AGENTS.md says extraction happens when the second page needs it; this ticket adds the second consumer, but a shared block is a separate change (section 8). The ~25 CSS lines are copied with `videos-` names into `video-grid/style.css`.
- **D2. Pure count in `grid.js`.** `visibleCount( attrs, slug )` next to `matchesCategory()`, covered by `node --test`; `view.js` stays without a runner.
- **D3. Error UI lives in `player.js`**, not in `shared/status.js`: only the Videos dialogs have the retry markup, so the shared Documents/Collaterals path is untouched. The two buttons are siblings of the status `<p>` (a button inside a live region is announced as noise).
- **D4. A new `data-state="error"`** (set with `setState( 'error' )`) so the big Play and the control bar stay hidden over a failed video (their rules only match `paused`, `playing`, `ended`).
- **D5. Next-video metadata probe is a detached `<video preload="metadata">`** in a 20-line module, released on close. Alternative rejected: `<link rel="preload" as="video">` (not honoured consistently, cannot be cancelled).
- **D6. ←/→ on desktop only.** The mobile dialog keeps ▲/▼ for previous/next and ←/→ for seek (DPV-020/021); the ticket's wording is about the modal, which is the desktop dialog.
- **D7. Touch targets by one `@media (pointer: coarse)` block**, not by growing the desktop mouse sizes.

## 4. Work packages (each leaves all suites green)

### Phase 0: measure (no edits)

1. Baseline: run the theme suite and the node suites from `AGENTS.md` and write the totals in section 9.
2. Headless Chrome (puppeteer-core, scratch folder outside the repo, as in earlier plans) against `https://devportfolio.ddev.site/videos/`: at widths 320, 360, 390, 430, 480, 620 print the number of columns (distinct `getBoundingClientRect().left` of visible `.videos-item`). Expected: 2 at 320, 3 at 390. **Record all six**; a large phone (430px) is 3 today, 4 starts near 500px (open question Q1).
3. At 390px with touch emulation, list every `button, a, [role=button], input[type=range]` in `.videos-modal`, `.videos-mobile` and the page whose box is under 44 x 44px. Keep this list: it is the input of phase 4.
4. `ddev snapshot` before any browser check that edits data.

### Phase 1: empty states (DPV-027 lines 1 and 2)

**1a. `$T/blocks/video-grid/grid.js`**: append

```js

/**
 * How many items a category shows.
 *
 * @param {string[]} attrs Each item's `data-video-categories`.
 * @param {string}   slug  The chosen slug, '' for All.
 * @return {number}
 */
export function visibleCount( attrs, slug ) {
	return attrs.filter( ( attr ) => matchesCategory( attr, slug ) ).length;
}
```

**1b. `$T/blocks/video-grid/render.php`**: replace

```php
if ( empty( $devportfolio_ids ) ) {
	return;
}
```

with

```php
if ( empty( $devportfolio_ids ) ) {
	?>
	<p class="videos-empty videos-empty--none" role="status"><?php echo esc_html_x( 'No videos yet.', 'video grid empty', 'devportfolio' ); ?></p>
	<?php
	return;
}
```

and replace the file header sentence `No videos renders nothing (the empty state is DPV-027).` with `No videos renders the "No videos yet." line and no grid (DPV-027).` Then, after the grid's closing `</div>` (the last line of the file), add:

```php
<p class="videos-empty" data-videos-empty role="status" hidden><?php echo esc_html_x( 'No videos in this category', 'video grid empty', 'devportfolio' ); ?></p>
```

**1c. `$T/blocks/video-grid/view.js`**: change the import to `import { matchesCategory, visibleCount } from './grid.js';`; after `const items = ...` add

```js
	// The line after the grid (a sibling, so the grid's own rules never touch it).
	const empty = grid.nextElementSibling && grid.nextElementSibling.hasAttribute( 'data-videos-empty' ) ? grid.nextElementSibling : null;
```

and at the end of `filter( slug )`, just before `refreshPreviews();`, add

```js
		if ( empty ) {
			// Same tick as the item changes, so a View Transition's new snapshot already shows it.
			empty.hidden = visibleCount( items.map( ( item ) => item.getAttribute( 'data-video-categories' ) || '' ), slug ) !== 0;
		}
```

**1d. `$T/blocks/video-grid/style.css`**: append

```css

/*
 * Empty lines (DPV-027), as DPC-025's `.collaterals-empty`: a sibling after the grid for "no videos in this
 * category", the same element with `--none` and no grid when there are no videos at all.
 */
.videos-empty {
	margin: 44px 0 0;
	padding: 56px 16px;
	min-height: 160px;
	color: var(--wp--preset--color--mid);
	font-size: 14px;
	line-height: 1.5;
	text-align: center;
}

.videos-empty[hidden] {
	display: none;
}

/* Nothing to filter: the pills would only filter an empty page. */
.documents-page__content:has(.videos-empty--none) .wp-block-devportfolio-document-categories {
	display: none;
}

@media (max-width: 700px) {
	.videos-empty {
		margin-top: 28px;
		padding: 40px 8px;
		min-height: 120px;
	}
}
```

**Gate 1:** `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-grid 'node --test tests/*.test.mjs'` (after the node tests of section 5) and the theme suite.

### Phase 2: error state with Try again and watch link (DPV-027 lines 3 and 4)

**2a. `$T/blocks/video-modal/render.php`**: directly after the desktop `<p class="videos-modal__state" ...>...</p>` line add

```php
				<button class="videos-modal__retry" type="button" hidden><?php echo esc_html_x( 'Try again', 'video modal', 'devportfolio' ); ?></button>
				<a class="videos-modal__watch" target="_blank" rel="noopener" hidden><?php echo esc_html_x( 'Watch on the original site', 'video modal', 'devportfolio' ); ?></a>
```

and directly after the mobile `<p class="videos-mobile__state" ...>...</p>` (it ends with `</p>` on its own line) add the same two lines with the classes `videos-mobile__retry` and `videos-mobile__watch`.

**2b. `$T/blocks/video-modal/player.js`**:

1. After `const status = createStatus( ... );` add
```js
	const retryButton = $( 'retry' );
	const watchLink = $( 'watch' );
```
2. After `let idleTimer = 0;` add `let current = null;`
3. After the `setState` helper add
```js
	/** A video that cannot play: the message, "Try again" when there is something to load, the original post when known. */
	const fail = ( item ) => {
		status.set( 'unavailable' );
		setState( 'error' );
		if ( retryButton ) {
			retryButton.hidden = ! ( item.fileUrl || item.embedSrc );
		}
		if ( watchLink ) {
			watchLink.hidden = ! item.postUrl;
			if ( item.postUrl ) {
				watchLink.href = item.postUrl;
			}
		}
	};
```
4. In `unload()`, after `status.set( null );` add
```js
		if ( retryButton ) {
			retryButton.hidden = true;
		}
		if ( watchLink ) {
			watchLink.hidden = true;
		}
```
5. In `mountFile()` replace the `error` listener body
```js
			status.set( 'unavailable' );
			setState( 'paused' );
```
with `fail( item );`.
6. In `mountEmbed()` insert at the top, before `setState( 'embed' );`
```js
		if ( navigator.onLine === false ) {
			fail( item );
			return;
		}
```
7. In `mount()` replace the last `else { setState( 'embed' ); status.set( 'unavailable' ); }` with `else { fail( item ); }`, and add `current = item;` right after `unload();`.
8. Before `return { mount, ... }` add
```js
	if ( retryButton ) {
		retryButton.addEventListener( 'click', () => {
			if ( current ) {
				// The click is a gesture: sound is allowed, as for the first open.
				mount( current, true );
				root.tabIndex = -1;
				root.focus( { preventScroll: true } );
			}
		} );
	}
```

**2c. `$T/blocks/video-modal/style.css`**: after the `.videos-modal__state-hint:empty, .videos-mobile__state-hint:empty { display: none; }` rule add

```css

/* Failure actions (DPV-027): under the message, 44px tall. */
.videos-modal__retry,
.videos-mobile__retry,
.videos-modal__watch,
.videos-mobile__watch {
	position: absolute;
	left: 50%;
	z-index: 5;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	min-width: 44px;
	min-height: 44px;
	padding: 0 20px;
	transform: translateX( -50% );
	border: 1px solid #fff;
	border-radius: 999px;
	background: transparent;
	color: #fff;
	font: inherit;
	font-size: 14px;
	text-decoration: none;
	cursor: pointer;
}

.videos-modal__retry,
.videos-mobile__retry {
	top: calc( 50% + 40px );
}

.videos-modal__watch,
.videos-mobile__watch {
	top: calc( 50% + 96px );
	border-color: transparent;
	text-decoration: underline;
}

.videos-modal__retry[hidden],
.videos-mobile__retry[hidden],
.videos-modal__watch[hidden],
.videos-mobile__watch[hidden] {
	display: none;
}

.videos-modal__retry:focus-visible,
.videos-mobile__retry:focus-visible,
.videos-modal__watch:focus-visible,
.videos-mobile__watch:focus-visible {
	outline: 2px solid #fff;
	outline-offset: 3px;
}

/* An error state shows no Play and no bar (their rules only match paused, playing and ended). */
@media (forced-colors: active) {
	.videos-modal__retry,
	.videos-mobile__retry {
		border-color: ButtonText;
		color: ButtonText;
	}
}
```

(Do not delete the existing `.videos-mobile__state { z-index: 5; }`; the actions use `z-index: 5` too and come after it in the DOM.)

**Gate 2:** theme suite, `blocks/video-modal` node suite, and the browser check items 3 and 4 of section 7.

### Phase 3: phone grid (DPV-034)

No code. Pin what phase 0 measured: add the CSS-contract test of section 5 (grid template, 4px phone gap, tile `aspect-ratio: 9 / 16`, the bar, mute and ring at `opacity: 0` at rest, `canPreview` requires hover). If phase 0 shows a different column count at 320 or 390, **stop and report** (do not tune the clamp).

### Phase 4: keyboard and touch targets (DPV-039)

**4a. `$T/blocks/video-modal/view.js`**: add `import { neighbours } from './neighbours.js';` after the `createPlayer` import, and, after the `els.close.addEventListener( 'click', closeDesktop );` line, add

```js
	// ← and → open the previous and next video of the active filter. The seek and volume sliders keep the arrows.
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) || event.target.closest( 'input, textarea, select' ) || fadingOut || flight ) {
			return;
		}
		const near = neighbours( order, items, currentId, activeFilter() );
		const id = event.key === 'ArrowLeft' ? near.prev : near.next;
		if ( id !== null ) {
			event.preventDefault();
			swapTo( id );
		}
	} );
```

Check first that `fadingOut` and `flight` are declared before this line (they are `let` at module scope above `open`); if the order is wrong, move the block below their declarations.

**4b. `$T/blocks/video-modal/style.css`**: from the phase 0 list, append one block (selectors are the offenders the list printed; this is the expected set, **add or drop selectors to match the list, never use `*`**):

```css

/* Touch targets (DPV-039): at least 44 x 44px wherever a finger is the pointer. */
@media (pointer: coarse) {
	.videos-modal__close,
	.videos-modal__playpause,
	.videos-modal__volbtn,
	.videos-modal__ratebtn,
	.videos-modal__rateopt,
	.videos-modal__fullscreen,
	.videos-modal__copy,
	.videos-modal__original,
	.videos-modal__nextitem,
	.videos-mobile__btn {
		min-width: 44px;
		min-height: 44px;
	}

	.videos-modal__scrub,
	.videos-modal__volume {
		min-height: 44px;
	}
}
```

If a button's visible size must stay small (the 30px mobile big-play icon is an icon inside a 44px box, leave it), enlarge its hit area with `padding`, not its glyph. Round buttons keep `border-radius: 50%`.

**Gate 4:** re-run the phase 0 step 3 audit under touch emulation: the list must be empty.

### Phase 5: media loading (DPV-040)

**5a.** Replace `video.preload = 'auto';` with `video.preload = 'none';` in `$T/blocks/video-grid/preview.js` (`videoOf()`) and `$T/blocks/video-grid/popout.js` (line ~84). The play call still loads the file; this only stops anything loading earlier. `player.js` keeps `'auto'` (open video, about to play), with its comment unchanged.

**5b. New `$T/blocks/video-modal/preload-next.js`:**

```js
/**
 * Loads the metadata (not the file) of the one video that will probably open next (DPV-040).
 *
 * `set( item )` replaces the previous probe; an item with no file, or data saver, probes nothing. `release()`
 * drops the probe so the browser stops fetching.
 */
export function createNextPreloader() {
	let probe = null;

	const release = () => {
		if ( probe ) {
			probe.removeAttribute( 'src' );
			probe.load();
			probe = null;
		}
	};

	return {
		set( item ) {
			release();
			if ( ! item || ! item.fileUrl || ( typeof navigator !== 'undefined' && navigator.connection && navigator.connection.saveData ) ) {
				return;
			}
			probe = document.createElement( 'video' );
			probe.preload = 'metadata';
			probe.muted = true;
			probe.src = item.fileUrl;
		},
		release,
	};
}
```

**5c. `view.js`:** `import { createNextPreloader } from './preload-next.js';`; after `const player = createPlayer( dialog, strings );` add `const preloadNext = createNextPreloader();`; in `fill()` after `renderNext();` add

```js
		preloadNext.set( items[ neighbours( order, items, currentId, activeFilter() ).next ] );
```

and call `preloadNext.release();` as the first line of the desktop `dialog` `close` listener and of the `mobileDialog` `close` listener.

**5d. `mobile.js`:** same import; in `createMobileVideo` next to `preloadNeighbours` create `const preloadNext = createNextPreloader();`; in `show()` after `preloadNeighbours();` add `const ahead = near().next; preloadNext.set( ahead === null ? null : items[ ahead ] );`; in `unload()` add `preloadNext.release();`.

**Gate 5:** node and theme suites; browser check items 5 and 6.

### Phase 6: field QA (DPV-025, no code)

1. Run the existing proofs: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Video` and the theme `--filter 'Devportfolio_Test_Video_Modal|Devportfolio_Test_Video_Grid'`. `test_payload_has_every_key_the_script_reads`, `test_shape_length_and_stats_follow_the_saved_data` and `test_post_url_must_be_http_or_https` already cover the payload; list their results in section 9.
2. Add **one** theme test, `test_a_video_with_every_field_reaches_the_grid_and_the_payload` in `tests/test-video-modal.php`: create a video with title, content, a `video_category` term, an `aspect_ratio` term, a featured image, `cptdp_video_file`, `cptdp_post_url`, `cptdp_length`, `cptdp_post_statistics`, `cptdp_software_tools` and `menu_order`; assert the grid item has the title label, the category slug, `data-ratio` and the poster, and the payload item has each matching key with the saved value. Add a second case with an empty `cptdp_post_url` asserting `postUrl === ''` (the script hides the button on that value) and a script contract that `view.js` sets `els.original.hidden = true` in the `else` branch and `details-sheet.js` sets `original.hidden = model.postUrl === ''`.
3. Browser check (section 7 item 9): create one video with every field in wp-admin; confirm grid, desktop dialog and 390px dialog show each field; clear the post link and confirm the button is gone in both; reorder with the order field and see the grid follow; trash it and see it leave. No captions field in the edit screen.

### Phase 8: manual preview upload (DPV-040, plugin, confirmed by Dave)

Done last, after phases 1–7 are green. Read `includes/post-types/video.php` and the preview code of `video-autofill.php` (`cptdp_video_preview`, `cptdp_video_preview_generated`, the REST route `cptdp/v1/videos/<id>/preview`) and the Video File box in `assets/admin-video.js` before editing; reuse the route's validation (magic bytes, 8 MB cap) for the manual path, do not duplicate it.

- **8a.** Meta box field "Preview clip" under the Video File (optional; a hidden `cptdp_video_preview_pick` input holds the attachment ID, a button opens the media picker as for the Video File, Remove sets the field to 0). On save: a new pick that is an MP4/WebM under the 8 MB cap (magic bytes checked with `cptdp_video_preview_kind()`) becomes `cptdp_video_preview` after `cptdp_video_preview_remove()` drops our generated clip; a 0 clears an editor clip only (`cptdp_video_preview_owner()` = `editor`); an invalid pick is ignored and the box shows what was kept. The owner model is the existing one: a clip that is not `cptdp_video_preview_generated` is `editor` and the REST route keeps it.
- **8b.** Tests: plugin test that a manual clip is stored and replaces a generated one, a non-MP4/WebM is refused, and a file removal deletes only a clip this video owns. Theme: the grid's `data-preview-clip` still set for the manual clip.
- **8c.** Plugin `Version` bump (minor) and AGENTS.md overview line. Gate: plugin suite and theme suite.

### Phase 7: docs

`AGENTS.md`: in the video grid paragraph, say "no videos renders `p.videos-empty--none` and the pills are hidden with `:has()`; the sibling `p[data-videos-empty]` is toggled from `filter()` through `visibleCount()`"; in the video modal paragraph, add the `error` state, `Try again` / watch link (`player.js` `fail()`), desktop `←`/`→` stepping, the 44px coarse-pointer block and `preload-next.js`; update the test totals. This plan's section 9 (As-built).

## 5. Tests

**Node (`node --test`)**
- `blocks/video-grid/tests/grid.test.mjs`: `visibleCount( [ 'a', 'a b', '', 'c' ], '' )` is 4; `( …, 'a' )` is 2; `( …, 'zzz' )` is 0; `( [], '' )` is 0.
- New `blocks/video-modal/tests/preload-next.test.mjs`: stub `globalThis.document = { createElement: () => fake }` where `fake` records `preload`, `src`, `muted` and `load()`/`removeAttribute()` calls. Assert: `set( { fileUrl: 'x.mp4' } )` creates one probe with `preload === 'metadata'` and `src === 'x.mp4'`; a second `set` releases the first (its `src` removed, `load` called) and creates another; `set( null )` and `set( { fileUrl: '' } )` create none; with `globalThis.navigator = { connection: { saveData: true } }` nothing is created; `release()` twice is safe.

**Theme PHP (`tests/test-video-grid.php`, `tests/test-video-modal.php`, `tests/test-video-modal-mobile.php`, `tests/test-design-system.php` untouched)**
- Edit `test_nothing_is_rendered_without_published_videos` into `test_no_videos_renders_the_empty_line_and_no_grid`: output contains `videos-empty videos-empty--none`, `role="status"`, `No videos yet.`, no `data-videos-grid`.
- New: with one video the output contains, **after** the grid's closing, `<p class="videos-empty" data-videos-empty role="status" hidden>` with `No videos in this category`; the line is not inside `[data-videos-grid]` (DOMDocument: its parent is not the grid).
- New CSS/script contracts (parse the file as text): `video-grid/style.css` has `.videos-empty`, `.videos-empty[hidden]`, the `:has(.videos-empty--none)` pills rule and the 700px rule; `video-grid/view.js` imports `visibleCount` and sets `empty.hidden` inside `filter()` with no `requestAnimationFrame` in the handler; **DPV-034 pins:** `.videos-grid` has `minmax(clamp(104px, 72px + 8vw, 180px), 1fr)`, `--videos-gap: 4px` at `max-width: 620px`, `.videos-item` has `aspect-ratio: 9 / 16`, `.videos-item__bar`, `.videos-item__ring` have `opacity: 0` outside `.is-previewing`/`.is-pressing`, and `preview.js` `HOVER_QUERY` is `(hover: hover) and (pointer: fine)`.
- Modal render: the desktop and mobile dialogs each contain a `retry` button and a `watch` link, both `hidden`, outside the state `<p>` (not its descendants); `player.js` contains `fail(`, `setState( 'error' )` and the `retry` click handler; `style.css` has the `[hidden]` rules for the four action classes.
- Keyboard (script contract): `view.js` imports `neighbours` and its keydown handler names `ArrowLeft`, `ArrowRight` and ignores `input, textarea, select`; `mobile.js` still maps ArrowLeft/ArrowRight to `player.skip` (unchanged).
- Touch (CSS contract): a `@media (pointer: coarse)` block in `video-modal/style.css` containing `min-height: 44px` and each selector the phase 0 list printed.
- Preload (contract): none of `preview.js`, `popout.js` contains `preload = 'auto'`; `player.js` contains it once; `view.js` and `mobile.js` import `preload-next.js` and call `release()` in the close paths.

No new plugin test. Expected totals: recorded in phase 0, then +N for each added test, listed in section 9.

## 6. Edge cases to decide while building

- **Embed failure is invisible.** A YouTube/Vimeo iframe fires `load` even for the provider's error page, and the cross-origin frame cannot be inspected, so only "no source at all" and "browser offline" reach `fail()` for an embed. Say so in the as-built notes; do not add a timeout guess.
- **Retry on a deeplink open** uses `gesture = true` (the click is a gesture), so the retried video starts with sound unless the visitor muted it.
- **Retry while the file is still 404.** The button reappears with the same message; no counter, no backoff.
- **`←`/`→` with the Next-up buttons focused:** a `button` target still steps (only inputs keep their arrows).
- **Filter changes behind the open dialog** cannot happen (the dialog is modal); `activeFilter()` is read at keypress, so it is always current.
- **First paint of `?category=<empty>`:** the pressed pill is server-rendered; the line is toggled by the module script on load, as DPC-025 does, so a one-frame absence is possible before the script runs. Same trade-off as the collateral page.
- **Data saver / no hover:** the probe is skipped under data saver; touch devices never hover-preview (unchanged).

## 7. Browser check (headless Chrome via puppeteer-core, no runner in the repo; snapshot first)

1. Trash all videos: title and `No videos yet.` show, no pills, no grid, no console errors, at 1440 and 390.
2. `?category=<an empty term>`: the line shows without a flash; click All, then the empty term, then another: toggles inside the transition; Back/Forward consistent.
3. Point one video's `cptdp_video_file` at a missing attachment URL (or block its URL in DevTools): open it on desktop and at 390. Message, `Try again`, and the watch link (when it has a post URL) show; no big Play or bar; Try again remounts and, once the URL is allowed again, plays; focus lands on the video area, not on `body`.
4. An embed-only video with no post URL and `navigator.onLine` forced false: message only, `Try again` shown (embed), no link.
5. First page load, Network panel filtered to media and `youtube|vimeo`: **zero** requests. Hover a tile 800 ms: one request, for that tile only. Open a video: its file plus the next video's metadata (a small range request), nothing else. Close: the probe's request is cancelled. With `Save-Data` on: no probe.
6. Keyboard: Tab through tiles (2px ring, 3px gap), Enter opens, Space pauses, `→` opens the next in the filter (and not past the last), `←` the previous, the volume and seek sliders still use the arrows, Esc closes and focus returns to the tile.
7. Phase 0 widths again after the work (columns unchanged), and the touch-target audit under touch emulation: empty list.
8. `prefers-reduced-motion` and `forced-colors`: the retry and watch actions stay readable.
9. DPV-025 QA: phase 6 step 3.

Not verifiable here: a real stuck provider embed and a real screen reader (state it in the as-built notes).

## 8. Risks and follow-ups

- **Copied empty-state CSS** (`.collaterals-empty`, `.videos-empty`): two copies now; extract a shared rule when a third page needs it (the Documents page has none yet, DPC-025 open question 5).
- **`data-state="error"` is new.** Anything later that styles by `data-state` must handle it; the check in section 2 found no script that reads it today.
- **`preload = 'none'` on previews** can delay the first frame by one round trip on slow links; the poster stays until the first frame, so nothing is visible. If the check shows a worse feel, use `'metadata'` there only.
- **Stored but unused fields (DPV-025).** `cptdp_highlight` and the published date are not read by any theme block today; the ticket lists them as stored fields, not as shown ones. QA confirms they are stored and REST-readable only; surfacing a highlight is a new ticket. Say so in the as-built notes.
- **Column count on large phones** (Q1) is a design question, not a bug.

## 9. As-built notes

**Totals.** Baseline: theme PHP 607, plugin 918 (12 skipped), node video-grid 16, video-modal 44, shared 179, document-modal 17, collateral-modal 24, collateral-grid 18. As built: theme PHP 619, plugin 922 (12 skipped), video-grid node 17, video-modal node 49 (+5 `preload-next`); the other node suites unchanged.

**Phase 0 (measured, headless Chrome, 2026-11-19).** Columns: 320→2, 360→3, 390→3, 430→3, 480→3, 620→4. Expected 2 at 320 and 3 at 390 held; the Q1 question is still open (4 appears by 620px).

**Deviations.**
- D4 was wrong for the big Play: its default display is visible and only the `paused` rule shows it. Added an explicit `[data-state="error"]` rule that hides the big Play, bar, scrub and replay (desktop and mobile), as the embed state does.
- A rejected `play()` after a failed file set the state back to `paused`. `player.js` now has a `failed` flag: only `unload()` (the next mount) clears it.
- The speed button's own `min-width: 42px` rule beat the coarse-pointer rule by specificity; added a matching 44px rule.
- The category dropdown trigger (`.documents-categories__trigger`) got a coarse-pointer 44px rule in `document-categories/style.css`. That block is shared, so the Documents and Collaterals pages get it too.
- Not fixed (site header, shared chrome, outside this ticket's CSS): the skip link (screen-reader only), the mobile menu toggle (36px), the header nav links (34px tall at 800px) and the site title link. The touch audit is empty for everything in the Videos page content and both dialogs; these header items remain.
- Embed failure is detected only for no source and offline (plan section 6, Q3).
- Manual preview clip: a manual clip is never deleted by the system (AGENTS.md rule). Clearing the field or removing the video file only unlinks it. This replaces the plan's earlier wording in 10a.

**Browser checks run.** Against `https://devportfolio.ddev.site/videos/`, no data was edited:
- Blocked `.mov` on desktop 1280 and mobile 390: message, `Try again` shown, no big Play or bar (root `data-state="error"`). Try again remounts, plays, and focus lands on the video.
- Embed-only video with `navigator.onLine` forced false: message, `Try again`, and the watch link (the video has a post URL).
- `←`/`→` step to the next/previous video in the active filter; sliders are not affected.
- The empty-category line appears and hides through the real filter handler. No real empty category exists in the data, so the check dispatches the category-change event with an unknown slug.
- First page load: zero media requests. Opening a video requests its file and the next video's first range (the probe); the detached probe leaves no DOM element. Close releases it.
- Mobile dialog touch audit: empty. Desktop dialog at 800px: only the header items above.

**Not verified.**
- Phase 7 step 1 (trash all videos, no-videos line): needs data changes, not run.
- Phase 6 step 3 and the manual-clip admin UI in wp-admin: not run in a browser; covered by plugin tests only.
- Reduced motion, forced-colors, a real screen reader, a real stuck provider embed.
- Not committed. Branch `feat/DPV-025-027-034-039-040-Video-Empty-States-A11y-Loading`, uncommitted working tree.

## 10. Conventional commits

Branch: `feat/DPV-025-027-034-039-040-Video-Empty-States-A11y-Loading`.

| # | Subject | Stages | Refs |
|---|---|---|---|
| 1 | `docs(plans): add the video empty states and loading plan` | this plan | DPV-025, DPV-027, DPV-034, DPV-039, DPV-040 |
| 2 | `feat(theme): show empty states on the video grid` | `blocks/video-grid/{grid.js,render.php,view.js,style.css}` | DPV-027 |
| 3 | `feat(theme): add Try again and a watch link to video errors` | `blocks/video-modal/{render.php,player.js,style.css}` | DPV-027 |
| 4 | `feat(theme): step videos with arrow keys and grow touch targets` | `blocks/video-modal/{view.js,style.css}` | DPV-039 |
| 5 | `feat(theme): preload only the next video's metadata` | `blocks/video-modal/{preload-next.js,view.js,mobile.js}`, `blocks/video-grid/{preview.js,popout.js}` | DPV-040 |
| 6 | `test(theme): cover video empty, error, keyboard and loading rules` | `tests/test-video-*.php` (incl. the field QA test), `blocks/video-grid/tests/grid.test.mjs`, `blocks/video-modal/tests/preload-next.test.mjs` | DPV-025, DPV-027, DPV-034, DPV-039, DPV-040 |
| 7 | `docs(agents): document video empty states and loading` | `AGENTS.md` | DPV-025, DPV-027, DPV-034, DPV-039, DPV-040 |

Commits 4 and 5 both edit `view.js` and `video-modal/style.css`: stage hunks with `git add -p`. Each commit leaves every suite green (the edited grid test goes in commit 2 with its code if commit 6 would otherwise be red). Footer `Refs: <tickets>` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths (untracked plans of other tickets sit in the working tree). PR title: `feat(theme): add video empty states, a11y and lazy loading` (72 characters or fewer).

## 11. Open questions (defaults used; none block)

1. **Q1. Four columns on large phones.** DPV-034 says 2 / 3 / 4, but its checks only name 320px (2) and 390px (3). The fluid rule gives 4 from about 500px. Default: leave it and report the measured table. Change it only if design wants 4 at 430px (it would raise the tile minimum's slope, a DPV-008 change).
2. **Q2. Wording** of `No videos yet.`, `No videos in this category`, `Try again`, `Watch on the original site` is proposed ("to confirm with design" in the ticket).
3. **Q3. Embed failures.** Accept that only no-source and offline are detectable (section 6), or add a visible "Watch on the original site" link under every embed? Default: the former.
4. **Q4. DPV-040's preview question** is closed by DPV-041 (generated 6 s clip). **Resolved:** Dave wants a manual preview upload as well (section 1, 10a–10b; phase 8).
