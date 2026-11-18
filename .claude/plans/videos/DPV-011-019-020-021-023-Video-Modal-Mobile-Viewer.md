# Plan: Video modal, mobile viewer (DPV-011, DPV-019, DPV-020, DPV-021, DPV-023)

Tickets **DPV-011** (the mobile dialog and Back), **DPV-019** (close ✕), **DPV-020** (▲ ▼ and swipe), **DPV-021** (title ›, ⓘ and the details sheet), **DPV-023** (gestures, wide/square, landscape). Lo-fi: `.claude/plans/videos/videos-lofi-wireframe.html` section E (E3, E4, E7, E8). Branch `feat/DPV-011-019-020-021-023-Video-Modal-Mobile`, created **from `main`**.

**Shape of the change.** The mobile viewer plays like YouTube Shorts and Instagram Reels (section 0b): autoplay, loop, tap to pause, a separate mute button, a thin progress line, vertical swipe between videos. The player is split in two: **`player.js` is only the engine** (video element, autoplay, state, loop, idle, a small event API) and **every button is its own module** in `video-modal/controls/`. A viewer mounts exactly the controls it is given. The desktop modal mounts the full set (its markup is untouched); the mobile viewer mounts the set that **Settings → Videos** (a new wp-admin page) allows, and PHP renders markup only for those controls.

**Written for a small model (Haiku 5.5 or similar).** New files are given in full; edits to existing files are anchored find/replace with the new code. Every gate is a command. **Stop and report if a "Fact" in section 3 is false.** Where the plan says "mirror X", open X first.

`$T` = `wp-content/themes/devportfolio`. `$P` = `wp-content/plugins/custom-post-types-dp`.

---

## 0. Conflicts and stale items found while planning (read first)

1. **DPV-020 and DPV-023 both claim "swipe down".** DPV-020: down = previous video. DPV-023: down from the top edge = close. Resolution (D6): a swipe down that **starts in the top 64px** closes; anywhere else it opens the previous video; a swipe up opens the next. DPV-020's "up or down does the same as ▼ and ▲" is read as up = ▼ (next), down = ▲ (previous), as the lo-fi shows.
2. **DPV-023 "captions".** Removed in the DPV-017 plan (section 2). Nothing about captions is built or tested.
3. **Lo-fi E5/E6 (private clips, 🔒 scrubber) and E9 (long-press pop-out)** are in no ticket here and have no data model. Out of scope.
4. **Sound.** DPV-023: "sound follows the device volume". With every extra control off the viewer has no sound control; a tap that resumes playback unmutes (`unmuteOnPlay`, D16). The defaults switch the **mute button on**, because a deeplink open starts muted.
5. **Width rule.** The lo-fi says 900px; the tickets and `shared/routing.js` say 700px or less, or a touch screen held sideways. **Follow routing.**
6. **DPV-019 says "markup as collaterals-mobile / documents-mobile".** Those have a header row (`devportfolio_viewer_mobile_header()`); the video viewer has **no header**. Only the outline round-button look and `devportfolio_viewer_icon()` are reused.
7. **Current code blocks this ticket on purpose.** `view.js` `show()` returns when `! routing.isDesktop()`, and `routing.onChange` closes the desktop dialog on a mobile screen. Both are replaced (section 5.8). The desktop plan (`DPV-009-…`, section 10) lists this as the follow-up.
8. **Desktop arrows (DPV-028, cited by DPV-020's note) do not exist in the repo.** DPV-020 builds the "current filter's list" helper itself (`neighbours.js`), using the same filter rule as `shared/next-items.js`.
9. **Defaults vs DPV-021.** DPV-021 and the lo-fi say no scrub bar, time or mute on the video. The user asked for parity with desktop, then refined it to the Shorts/Reels pattern with admin-selectable controls. The defaults add two Shorts/Reels staples (mute button, read-only progress line); everything else is off. See Q1.
10. **DPV-022 (the details sheet content) is not in this ticket list**, but DPV-021's title and ⓘ must open "the same sheet". This plan builds a sheet with the details pane's content and a link to the original post, and **leaves Next up out of it** (D19, Q4).

## 0b. Research: how Shorts and Reels behave (what is adopted)

Secondary sources only; no official Instagram/Meta documentation was found, and nothing authoritative on Reels' progress bar. Rows marked *weak* should be checked on a device.

| Behaviour | YouTube Shorts | Instagram Reels | Adopted here |
|-----------|----------------|-----------------|--------------|
| Open | Autoplays, loops; vertical swipe to next/previous | Same | Autoplay (sound only if the visitor's click opened it), loop (`loop`, default on), swipe and ▲ ▼ |
| Tap | Tap pauses; a **mute icon** then appears (YouTube blog) | Since March 2026 a single tap pauses and the mute control appears after pausing; older: tap = mute, press-and-hold = pause (staged rollout) | Tap = pause/resume, centred play glyph while paused. Mute is a **separate button** |
| Double tap | Not a skip on mobile Shorts | Likes the reel | Ticket DPV-023 wants skip 5s: kept, switchable (`double_tap_skip`, default on). No like |
| Progress | Thin bottom bar, hidden by default, draggable once visible (*weak*) | Not covered (*weak*) | Thin read-only line (`progress`, default on); draggable only with `scrub` (default off) |
| Speed | 2x by press-and-hold + swipe down | Not covered | Not a gesture. Optional speed menu (`speed`, off) |
| Clear Screen | One tap hides all icons (Shorts update) | Not covered | Not adopted (the tap is pause) |
| Volume slider, time, full screen | Not shown | Not shown | Optional (`volume`, `time`, `fullscreen`), off |

Sources: YouTube blog "How we're updating the YouTube Shorts experience"; Social Media Today, Android Authority and Metricool coverage of Instagram's single-tap pause (March 2026); Previews and extension pages for the Shorts progress bar (weak).

## 1. Goal and acceptance criteria

1. On a screen **700px wide or less, or a touch screen held sideways** (`routing.isDesktop() === false`), tapping a thumbnail opens `dialog.videos-mobile`: full screen, no backdrop. The desktop dialog never opens there; the mobile one never opens on desktop.
2. The URL gets `?video_slug=<slug>` (one pushed entry), as on desktop. **Back closes the viewer** and leaves the Videos page; with the details sheet open **Back closes the sheet first**.
3. **Close (DPV-019):** an outline round ✕, 38px, 12px inset, top left. No header bar, title bar or Download. It closes and steps back in history when the viewer pushed the entry.
4. **▲ ▼ (DPV-020):** two round buttons on the right edge, vertically centred; ▲ = previous, ▼ = next, within the active category filter (the pressed `document-categories` pill). ▲ is `aria-disabled` and dimmed on the first video of the filter, ▼ on the last; no wrap; hidden when the filter has fewer than 2 videos. The URL is **replaced**, not pushed.
5. **Swipe (DPV-020, DPV-023):** up = ▼, down = ▲, except a swipe down starting in the top 64px closes the viewer.
6. **Footer overlay (DPV-021):** over the bottom of the video, on a dark-to-transparent gradient: the title with a `›` (bottom left, one line, ellipsis) and a round ⓘ (bottom right). Both open the **details sheet**. No tags or category on the video.
7. **Details sheet (lo-fi E4, without Next up):** category and shape pills, title, description, Made with, Results, "Open original post". Closes by drag down, tap outside, Esc or Back. Sections without data are left out.
8. **Playback (Shorts/Reels style):** a tap plays or pauses; while paused a large play glyph shows. With `double_tap_skip` on, a double tap skips 5s (right half forward, left half back; nothing in the 8px edge strips) and a single tap acts after 300ms; with it off a tap acts at once. A click that opened the viewer starts with sound; a deeplink or Back/Forward open starts muted; a blocked start falls back to muted without changing the choice. At the end the video **loops** (`loop` on) or, with `loop` off, replays once then shows Replay (the desktop rule).
9. **Optional controls (Settings → Videos, section 5.11)**, each rendered and mounted only when on; the viewer works with all off:
   - `mute` (default on): round sound button in the extras column, `aria-pressed`; the choice is shared with the desktop dialog.
   - `progress` (on): a 2px read-only line on the bottom edge (4px while touched), `inert`.
   - `scrub` (off; implies `progress`): the line is draggable (24px touch area) and seeks live.
   - `time` (off): `0:08 / 0:28` above the progress line.
   - `volume` (off): round button + slider popover opening to the left.
   - `speed` (off): round button showing the rate + six-option popover (0.5× to 2×).
   - `fullscreen` (off): round button, hidden where unsupported.
   - The extras (mute, volume, speed, full screen) stack under ✕'s rules at the top right; held sideways they sit in a row.
10. **Wide and square (DPV-023):** a 9:16 video fills the screen; square and wide are centred and letterboxed with the same controls. **Wide** videos (`16x9`, `4x3`) show a "Rotate for full screen" pill in portrait that disappears at the first tap or turn; it is not shown sideways. Square shows none.
11. **Sideways (DPV-023):** a touch screen held sideways keeps the mobile dialog; the video fills the screen **height** with the same controls. Rotating keeps the same video and URL; an open sheet stays open.
12. **Embed-only videos:** an iframe fills the stage with the provider's own controls; gestures and the settings controls do not apply (the iframe takes the touches); ✕, ▲ ▼, title and ⓘ still work. `autoplay=1` only when a click opened it.
13. Closing unloads the video (`removeAttribute('src')`, `load()`).
14. Crossing the routing boundary with a viewer open **swaps** to the right dialog in place: same video and URL, playback restarts at 0:00, muted.
15. `prefers-reduced-motion: reduce` removes the open animation and the skip flash.

## 2. Out of scope (do NOT build)

- Private clips, lock, request sheet (E5, E6); long-press pop-out (E9); captions; picture-in-picture; quality selection; Clear Screen; press-and-hold 2x; like/heart.
- Next up inside the sheet (D19).
- Per-video control overrides (the settings are site-wide); a settings screen for desktop controls (the desktop modal looks and behaves as before).
- Desktop arrows; a Copy link button in the sheet (lo-fi E4 has none).
- Anything in `localStorage`.
- `shared/mobile.js` `createMobileModal()` (a page viewer). Reused on purpose: `createSheetController()`, `createBottomSheet()`, `createRouting()`, `createDeeplink()`, `bindOpen()`, `createStatus()`.

## 3. Facts (verified; stop if false)

- `$T/blocks/video-modal/view.js` (317 lines): module `if ( dialog && dataEl )`; vars `currentId`, `opener`, `pushed`, `closingFromHistory`; `unload = () => player.unload()`; `activeFilter()` reads `.wp-block-devportfolio-document-categories [aria-pressed="true"]`; `fill( item, gesture )` ends with `player.mount( item, gesture ); renderNext();`; `focusTarget()`; `finishClose()` (unload, overflow, `history.go( -1 )` when `pushed`); `show( id, { push, trigger } )` returns early on `! routing.isDesktop()`; `swapTo( id )`; one capture-phase `popstate` listener at module top level; `routing.onChange` closes the dialog on mobile; `dialog.addEventListener( 'close', finishClose )`.
- `$T/blocks/video-modal/player.js` (362 lines): `createPlayer( dialog, strings )` → `{ mount, unload }`; every element via `.videos-modal__*`; `createStatus( …, prefix: 'videos-modal' )`; module variables `preferMuted`, `preferVolume`, `preferRate`; the bar's handlers (play/pause, big play, replay, scrub, volume, rate, fullscreen), popovers, a click-on-`<video>` toggle, `pointermove`/`focusin` wake, a dialog `keydown` (Escape closes a popover, Space toggles), a document `pointerdown` closing popovers; state in `data-state` of `.videos-modal__video`.
- `$T/blocks/video-modal/player-model.js` exports `SEEK_STEP` (5), `clampTime`, `formatTime`, `timeLabel`, `ariaValueText`, `progressPercent`, `clampVolume`, `seekForKey( key, time, duration )` (new time or `null`), `afterEnd( autoReplays )`, `PLAYBACK_RATES`.
- `$T/blocks/video-modal/render.php`: one `dialog.videos-modal` then `script.videos-modal__data` (`{ order, items }`); wrapper `div.videos-modal-root` with `data-strings`. Payload items (`devportfolio_video_modal_payload()`) carry `id`, `slug`, `title`, `description`, `category`, `tools`, `stats` (`{label,value}`), `categories`, `ratio` (`16x9|9x16|1x1|4x3`), `pane`, `shape`, `length`, `fileUrl`, `embedSrc`, `postUrl`, `poster`. **No payload change.**
- `$T/blocks/shared/routing.js`: `createRouting()` → `{ isDesktop(), isMobile(), onChange( cb ) }`.
- `$T/blocks/shared/sheet-controller.js` `createSheetController( { dialog } )`: `register( name, { create, closeOnRotate } )`, `async open( name, args )` (args go to the sheet's `open( args )`), `close()`, `isAnyOpen()`, `reset()`, `release()`. A sheet module returns `{ open( args ) => boolean, close( silent ), isOpen(), reset() }` and calls the `onClose` it was given when the visitor dismisses it. The controller creates its `popstate` listener when called.
- `$T/blocks/shared/bottom-sheet.js` `createBottomSheet( { dialog, titleId, handleLabel, modifier, onDismiss, prefix } )` → `{ sheet, head, body, grab, show( { opener, focus } ), hide( silent ), isOpen() }`; needs `.${prefix}__panel` in the dialog and the shell CSS for `${prefix}__dim`, `__sheet`, `__grab`…
- `$T/blocks/collateral-modal/details-sheet.js` is the template for 5.6 (its `createDetailsSheet` shape, `collateralDetailsModel()` purity, `el`/`svgIcon` from `shared/dom.js`). `$T/blocks/collateral-modal/view.js` (~lines 126 to 420) is the template for the history code in 5.8.
- `$T/blocks/document-modal/style.css` lists the sheet shell rules for `.documents-mobile__*` and `.collaterals-mobile__*`; `devportfolio-viewer` is the handle that loads it (`$T/functions.php` ~line 89).
- `$T/blocks/shared/gestures.js`: `DOUBLE_TAP_WINDOW` (300), `DEAD_STRIP` (8), `tapSide( x, width )`.
- `$T/theme.json` `settings.custom.modal` holds `ink`, `subtle`, `line`, `line-strong`, `surface`, `surface-alt`, `on-primary`, `focus`; no scrim.
- `$P/includes/settings/documents.php`: `cptdp_documents_is_form_post()` and `cptdp_documents_sanitize()` (unticked = off only for the settings form; partial REST updates keep saved values); `contact.php` shows the page, section and field skeleton. The loader requires every file in `includes/settings/`. Plugin `Version` is `0.30.0`.
- Node: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'`. Existing there: `tests/player-model.test.mjs` only.

## 4. Decisions

| # | Decision | Why |
|---|----------|-----|
| D1 | A second `dialog.videos-mobile` in the video-modal block, chosen by `shared/routing.js`. `mobile.js` (`createMobileVideo()`) fills and drives it; `view.js` only orchestrates (which dialog, history, swap). | Ticket note; keeps `view.js` readable. |
| D2 | **Engine and controls are separate.** `player.js` keeps what is not a button; each button is `controls/<key>.js`; `controls/index.js` is the registry and `mountControls()`. Markup decides what exists (PHP renders only enabled keys) and JS mounts only the keys it is told. Remembered mute/volume/speed live in `preferences.js`. | Selective controls by construction; no optional-element checks inside a god function. |
| D3 | `neighbours( order, items, currentId, slug )` is pure: the filter rule of `nextItems()` except the open id always counts; no wrap. | DPV-020. |
| D4 | One pure gesture module `mobile-gestures.js` (`classifyStroke()`, `tapAction()`); DOM wiring stays in `mobile.js`. | Testable under `node --test`. |
| D5 | Gestures bind to `.videos-mobile__stage` (holds the `<video>`). Every control is a sibling painted above it, never a descendant. | A tap on a control never reaches the gesture code; no `stopPropagation`. |
| D6 | Stroke: tap = under 10px and 300ms. Vertical swipe = `|dy| > 1.5 |dx|` and (`|dy| >= 48`, or `>= 24` at 0.4 px/ms). `dy < 0` next; `dy > 0` close when it started at `y <= 64`, else previous. | Section 0 item 1. |
| D7 | With `double_tap_skip` on, a single tap toggles after `DOUBLE_TAP_WINDOW` (300ms) unless a second tap arrives. With it off, a tap toggles at once. | A double tap must not also pause and resume. |
| D8 | Start, muted fallback: the engine's code. `loop` is an engine option (`video.loop`); off restores replay-once + Replay. | Section 0b. |
| D9 | The sheet is `createBottomSheet( { prefix: 'videos-mobile' } )` registered in `createSheetController` as `details`, `closeOnRotate: false`. Shell CSS: add `.videos-mobile__*` to the existing selector lists in `document-modal/style.css`; enqueue `devportfolio-viewer` on the Videos archive. | One shell; no copy of 28 rules. |
| D10 | History mirrors `collateral-modal/view.js`: push one entry on a click open, replace on a change, `history.go( -( 1 + extra ) )` on close with `extra = mobile.release()`, one capture-phase `popstate` listener at module top level. | The Interactivity Router reloads the page otherwise (DPD-020 notes). |
| D11 | `data-shape` on `.videos-mobile__video`: `tall` (`9x16`), `square` (`1x1`), else `wide`. | Letterbox rules and the hint. |
| D12 | Scrim: `linear-gradient( to top, var( --wp--custom--modal--scrim ), transparent )`, new token `scrim: rgb( 0 0 0 / 0.6 )`. The brand gradient is not used (design system: never behind text). | DPV-021 note. |
| D13 | Hint pill: the Documents viewer's pill markup (`span.videos-mobile__pill`, rotate icon path `M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5`), string "Rotate for full screen". | DPV-023 note. |
| D14 | Keyboard: Space comes from the engine, Escape-closes-popover from `controls/popover.js`, the scrub's arrows from `controls/progress.js`. `mobile.js` adds only ArrowUp/Down (previous/next) and ArrowLeft/Right (∓5s) when focus is not in a control; all ignored while the sheet is open. | No duplicate handlers. |
| D15 | A prev/next change is a gesture, so the new video starts with sound. `view.js` `goTo( id )` replaces the URL and calls `mobile.show( item, { gesture: true } )`. | As the desktop Next up swap. |
| D16 | Settings drive markup and behaviour: PHP reads `cptdp_get_videos_settings()` (guarded; the theme's defaults equal the plugin's), renders the enabled controls, and puts `{ loop, doubleTapSkip, unmuteOnPlay, scrub, controls }` in `data-mobile-options`. `unmuteOnPlay` is `! mute`. `scrub` implies `progress`. | An admin choice must not strand a visitor without sound. |
| D17 | Skip feedback: a brief `videos-mobile__flash` (`forward` / `back`) only; pause/play feedback is the centred glyph. | State is already visible. |
| D18 | Layout: ✕ top left; extras column top right; ▲ ▼ right edge centred; title bottom left, ⓘ bottom right, both **above** the 24px progress touch zone; time pill above the title; progress line on the bottom edge. | Chrome at the edges, the middle free. |
| D19 | The sheet has no Next up. | A sheet swap would race the history pop of the sheet's own entry (the URL replace lands on the sheet entry, and Back then restores the old URL). DPV-022 owns the sheet content; add Next up there with its own history handling. |
| D20 | Each dialog unloads its own player on its own `close` event; `finishClose()` does not unload. | During a swap the other dialog's new video must not be unloaded. |

## 5. Files

New, in `$T/blocks/video-modal/`:
- `neighbours.js`, `mobile-gestures.js`, `mobile.js`, `details-sheet.js`, `preferences.js`
- `controls/` `index.js`, `popover.js`, `find.js`, `bigplay.js`, `playpause.js`, `replay.js`, `progress.js`, `time.js`, `mute.js`, `volume.js`, `speed.js`, `fullscreen.js`
- `tests/` `neighbours.test.mjs`, `mobile-gestures.test.mjs`, `details-model.test.mjs`, `controls.test.mjs`

New elsewhere: `$T/tests/test-video-modal-mobile.php`; plugin `$P/includes/settings/videos.php`, `$P/tests/settings/test-videos.php`.

Edited: `player.js` (the engine, 5.3), `render.php`, `view.js`, `style.css` (this block), `$T/blocks/document-modal/style.css` (sheet selectors), `$T/blocks/collateral-modal/style.css` (sheet content selectors, if that is where `collaterals-mobile__details-*` live), `$T/functions.php` (helpers, strings, enqueue), `$T/theme.json` (scrim), `$T/blocks/document-modal/tests/routing.test.mjs`, `$T/tests/test-video-modal.php` (only if an assertion fails, section 6), `$P/custom-post-types-dp.php` (version `0.31.0`), `AGENTS.md`.

### 5.1 `neighbours.js` (full)

```js
/**
 * The previous and next video inside the active category filter (DPV-020). Pure: runs under `node --test`.
 *
 * Same filter rule as shared/next-items.js (`slug` '' = all; an item matches when its `categories` has the
 * slug), except that the open video always counts, so a deeplink to a video outside the pressed filter still
 * has a position. No wrapping: the first video has no previous, the last has no next.
 *
 * @param {number[]} order     Ids in the grid's order.
 * @param {Object}   items     Payload items keyed by id.
 * @param {number}   currentId Id of the open video.
 * @param {string}   slug      Active category slug, or ''.
 * @return {{prev:?number,next:?number,index:number,count:number}}
 */
export function neighbours( order, items, currentId, slug ) {
	const inFilter = ( id ) => id === currentId || slug === '' || ( items[ id ]?.categories ?? [] ).includes( slug );
	const list = order.filter( inFilter );
	const index = list.indexOf( currentId );
	return {
		prev: index > 0 ? list[ index - 1 ] : null,
		next: index >= 0 && index < list.length - 1 ? list[ index + 1 ] : null,
		index,
		count: list.length,
	};
}
```

### 5.2 `mobile-gestures.js` (full)

```js
/**
 * Touch maths of the mobile video viewer (DPV-020, DPV-023). Pure: runs under `node --test`.
 */
import { tapSide, DOUBLE_TAP_WINDOW } from '../shared/gestures.js';
import { SEEK_STEP } from './player-model.js';

export const TOP_EDGE = 64; // a swipe down that starts this close to the top closes the viewer
export const TAP_MAX_MOVE = 10;
export const TAP_MAX_TIME = 300;
export const SWIPE_MIN = 48;
export const SWIPE_FAST_MIN = 24;
export const SWIPE_FAST_SPEED = 0.4; // px per ms

/**
 * What a finished touch was.
 *
 * @param {{x0:number,y0:number,x1:number,y1:number,dt:number}} stroke Start and end (px, screen) and duration (ms).
 * @return {'tap'|'next'|'prev'|'close'|'none'}
 */
export function classifyStroke( { x0, y0, x1, y1, dt } ) {
	const dx = x1 - x0;
	const dy = y1 - y0;
	const ax = Math.abs( dx );
	const ay = Math.abs( dy );
	if ( ax < TAP_MAX_MOVE && ay < TAP_MAX_MOVE ) {
		return dt <= TAP_MAX_TIME ? 'tap' : 'none';
	}
	if ( ay <= ax * 1.5 ) {
		return 'none';
	}
	const fast = dt > 0 && ay >= SWIPE_FAST_MIN && ay / dt >= SWIPE_FAST_SPEED;
	if ( ay < SWIPE_MIN && ! fast ) {
		return 'none';
	}
	if ( dy < 0 ) {
		return 'next';
	}
	return y0 <= TOP_EDGE ? 'close' : 'prev';
}

/**
 * What a tap does.
 *
 * - `skip` off: toggle play/pause at once.
 * - second tap inside the window: skip 5 seconds by the side of this tap (nothing in the edge strips);
 * - otherwise: toggle once the window has passed without a second tap.
 *
 * @param {{x:number,width:number,now:number,last:?{x:number,t:number},skip?:boolean}} args `last` is the previous tap.
 * @return {{kind:'toggle-now'}|{kind:'toggle-later'}|{kind:'skip',seconds:number}|{kind:'none'}}
 */
export function tapAction( { x, width, now, last, skip = true } ) {
	if ( ! skip ) {
		return { kind: 'toggle-now' };
	}
	if ( last && now - last.t <= DOUBLE_TAP_WINDOW ) {
		const side = tapSide( x, width );
		return side === 0 ? { kind: 'none' } : { kind: 'skip', seconds: side * SEEK_STEP };
	}
	return { kind: 'toggle-later' };
}
```

### 5.3 The engine: `preferences.js` (new, full) and `player.js` (replaced, full)

This is a **move**: every behaviour below already exists in `player.js` today; the buttons' code moved to `controls/` (5.4). Desktop must behave exactly as before.

`preferences.js`:

```js
/**
 * What the visitor chose in this page session (memory only, never storage), shared by the desktop and the
 * mobile viewer. Only a button press or slider move writes it; the automatic muted fallback does not.
 */
export const preferences = { muted: false, volume: 1, rate: 1 };
```

`player.js` (replace the whole file):

```js
/**
 * The video player engine of the Videos modals (DPV-017 body, DPV-018, DPV-029): the <video> or embed, autoplay
 * and the muted fallback, loop or replay-once, the playing/paused/idle state and a small event API. It has no
 * buttons: every control is a module in controls/ that binds to this API (see controls/index.js).
 *
 * `createPlayer( dialog, strings, options )`; the elements are found by class prefix (`videos-modal` or
 * `videos-mobile`). `mount( item, gesture )`: `gesture` is true when a click opened or moved to the video (sound
 * allowed), false for a deeplink or Back/Forward.
 *
 * Events (`player.on( type, fn )`, returns an unsubscribe): `mount`, `unmount`, `meta`, `time`, `play`, `pause`,
 * `ended`, `volume`.
 */
import { createStatus } from '../shared/status.js';
import { afterEnd, clampTime } from './player-model.js';
import { preferences } from './preferences.js';

const IDLE_MS = 3000;
const INTERACTIVE = 'button, input, a, textarea, select';
// <video> event -> engine event, for the ones that are only forwarded.
const FORWARDED = { loadedmetadata: 'meta', timeupdate: 'time', play: 'play', pause: 'pause', volumechange: 'volume' };

/**
 * @param {Element} dialog
 * @param {Object}  strings
 * @param {Object}  [options]
 * @param {string}  [options.prefix='videos-modal']
 * @param {boolean} [options.clickToggles=true] A click on the <video> toggles playback (desktop). The mobile viewer reads taps itself.
 * @param {boolean} [options.loop=false]        Loop instead of replaying once.
 * @param {boolean} [options.unmuteOnPlay=false] A visitor-started play turns the sound on (no mute button on screen).
 */
export function createPlayer( dialog, strings = {}, { prefix = 'videos-modal', clickToggles = true, loop = false, unmuteOnPlay = false } = {} ) {
	const $ = ( name ) => dialog.querySelector( `.${ prefix }__${ name }` );
	const stage = $( 'stage' );
	const root = $( 'video' );
	const status = createStatus( { stage, status: $( 'state' ), prefix } );
	const title = ( item ) => ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title );

	let video = null;
	let autoReplays = 0;
	let idleTimer = 0;
	const listeners = {};

	const on = ( type, fn ) => {
		( listeners[ type ] ||= [] ).push( fn );
		return () => {
			listeners[ type ] = listeners[ type ].filter( ( f ) => f !== fn );
		};
	};
	const emit = ( type ) => ( listeners[ type ] || [] ).forEach( ( fn ) => fn() );
	const setState = ( state ) => {
		root.dataset.state = state;
	};

	/** The controls fade 3 seconds after the last pointer move, touch or focus while playing. */
	const wake = () => {
		root.classList.remove( 'is-idle' );
		window.clearTimeout( idleTimer );
		if ( video && ! video.paused && ! video.ended ) {
			idleTimer = window.setTimeout( () => root.classList.add( 'is-idle' ), IDLE_MS );
		}
	};

	/** A visitor-started play is a new session: the automatic replay counter starts again. */
	const userPlay = () => {
		if ( ! video ) {
			return;
		}
		autoReplays = 0;
		if ( unmuteOnPlay ) {
			preferences.muted = false;
			video.muted = false;
		}
		video.play().catch( () => setState( 'paused' ) );
	};

	const toggle = () => {
		if ( video ) {
			video.paused ? userPlay() : video.pause();
		}
	};

	/** Skips `seconds` (negative = back), kept inside the video. */
	const skip = ( seconds ) => {
		if ( video && Number.isFinite( video.duration ) ) {
			video.currentTime = clampTime( video.currentTime + seconds, video.duration );
			emit( 'time' );
		}
	};

	const start = () => {
		video.play().catch( () => {
			// Sound was blocked: start muted. This is not a choice, so the remembered mute stays as it was.
			video.muted = true;
			video.play().catch( () => setState( 'paused' ) );
		} );
	};

	const unload = () => {
		window.clearTimeout( idleTimer );
		root.classList.remove( 'is-idle' );
		emit( 'unmount' );
		if ( video ) {
			video.pause();
			video.removeAttribute( 'src' );
			video.load();
			video = null;
		}
		stage.replaceChildren();
		status.set( null );
		setState( 'paused' );
	};

	const mountFile = ( item, gesture ) => {
		video = document.createElement( 'video' );
		video.playsInline = true;
		video.preload = 'auto';
		video.loop = loop;
		video.setAttribute( 'aria-label', title( item ) );
		if ( item.poster ) {
			video.poster = item.poster;
		}
		video.src = item.fileUrl;
		stage.append( video );

		status.set( 'loading' );
		video.addEventListener( 'canplay', () => status.set( null ) );
		video.addEventListener( 'error', () => {
			status.set( 'unavailable' );
			setState( 'paused' );
		} );
		video.addEventListener( 'play', () => {
			setState( 'playing' );
			wake();
		} );
		video.addEventListener( 'pause', () => {
			if ( ! video.ended ) {
				setState( 'paused' );
			}
			window.clearTimeout( idleTimer );
			root.classList.remove( 'is-idle' );
		} );
		video.addEventListener( 'ended', () => {
			if ( afterEnd( autoReplays ) === 'replay' ) {
				autoReplays += 1;
				video.currentTime = 0;
				video.play().catch( () => setState( 'ended' ) );
			} else {
				setState( 'ended' );
			}
		} );
		Object.entries( FORWARDED ).forEach( ( [ domEvent, type ] ) => video.addEventListener( domEvent, () => emit( type ) ) );

		setState( 'paused' );
		video.muted = preferences.muted || ! gesture;
		video.volume = preferences.volume;
		video.playbackRate = preferences.rate;
		emit( 'mount' );
		start();
	};

	const mountEmbed = ( item, gesture ) => {
		setState( 'embed' );
		const frame = document.createElement( 'iframe' );
		frame.title = title( item );
		frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
		frame.allowFullscreen = true;
		frame.referrerPolicy = 'strict-origin-when-cross-origin';
		frame.src = gesture ? `${ item.embedSrc }${ item.embedSrc.includes( '?' ) ? '&' : '?' }autoplay=1` : item.embedSrc;
		stage.append( frame );
		emit( 'mount' );
	};

	const mount = ( item, gesture ) => {
		unload();
		if ( item.fileUrl ) {
			mountFile( item, gesture );
		} else if ( item.embedSrc ) {
			mountEmbed( item, gesture );
		} else {
			setState( 'embed' );
			status.set( 'unavailable' );
		}
	};

	if ( clickToggles ) {
		root.addEventListener( 'click', ( event ) => {
			// The video itself toggles; the controls handle their own clicks.
			if ( video && event.target === video ) {
				toggle();
			}
		} );
	}
	root.addEventListener( 'pointermove', wake );
	root.addEventListener( 'pointerdown', wake );
	root.addEventListener( 'focusin', wake );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( video && event.key === ' ' && ! event.target.closest( INTERACTIVE ) ) {
			event.preventDefault();
			wake();
			toggle();
		}
	} );

	return { mount, unload, toggle, userPlay, skip, wake, on, video: () => video, root };
}
```

### 5.4 Controls (`video-modal/controls/`)

Every control is `create<Key>( { dialog, prefix, player, strings, seek, popovers } )`: it finds its own elements, **returns `null` when they are not in the DOM**, subscribes through `player.on`, writes nothing outside its own elements, and imports no other control. The three code blocks below are the shared pieces; the nine controls follow.

`find.js`:

```js
/** An element of a viewer by class prefix: `find( dialog, 'videos-mobile', 'mutebtn' )`. */
export const find = ( dialog, prefix, name ) => dialog.querySelector( `.${ prefix }__${ name }` );
```

`popover.js` (volume and speed popovers: one open at a time, outside press and Escape close it):

```js
/**
 * The popovers of one viewer. Volume and speed never open together; a press outside an open popover closes
 * it; Escape closes it first (the dialog stays open) and returns focus to its button.
 */
export function createPopovers( dialog ) {
	const items = [];
	const setOpen = ( item, open ) => {
		item.pop.hidden = ! open;
		item.trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};
	const closeAll = ( keep = null ) => {
		items.forEach( ( item ) => {
			if ( item !== keep ) {
				setOpen( item, false );
			}
		} );
	};

	document.addEventListener( 'pointerdown', ( event ) => {
		items.forEach( ( item ) => {
			if ( ! item.pop.hidden && ! item.pop.contains( event.target ) && ! item.trigger.contains( event.target ) ) {
				setOpen( item, false );
			}
		} );
	} );
	dialog.addEventListener( 'keydown', ( event ) => {
		const open = items.find( ( item ) => ! item.pop.hidden );
		if ( open && event.key === 'Escape' ) {
			event.preventDefault();
			closeAll();
			open.trigger.focus();
		}
	} );

	return {
		register: ( item ) => items.push( item ),
		toggle( item ) {
			const open = item.pop.hidden;
			closeAll( item );
			setOpen( item, open );
		},
		closeAll,
	};
}
```

`index.js`:

```js
import { createBigPlay } from './bigplay.js';
import { createPlayPause } from './playpause.js';
import { createReplay } from './replay.js';
import { createProgress } from './progress.js';
import { createTime } from './time.js';
import { createMute } from './mute.js';
import { createVolume } from './volume.js';
import { createSpeed } from './speed.js';
import { createFullscreen } from './fullscreen.js';
import { createPopovers } from './popover.js';

/** Every control of the video player, by key. A new button is one file and one line here. */
export const CONTROLS = {
	bigplay: createBigPlay,
	playpause: createPlayPause,
	replay: createReplay,
	progress: createProgress,
	time: createTime,
	mute: createMute,
	volume: createVolume,
	speed: createSpeed,
	fullscreen: createFullscreen,
};

/** What the desktop modal mounts: everything its markup has (it has no separate mute button). */
export const DESKTOP_CONTROLS = [ 'bigplay', 'playpause', 'replay', 'progress', 'time', 'volume', 'speed', 'fullscreen' ];

/**
 * Mounts the named controls on a player. Unknown keys are ignored; a control whose elements are not in the DOM
 * returns null and is dropped, so a viewer only ever has what its markup rendered.
 *
 * @param {Object}   args
 * @param {Element}  args.dialog
 * @param {string}   args.prefix  Class prefix of the viewer.
 * @param {Object}   args.player  The engine (createPlayer).
 * @param {Object}   [args.strings]
 * @param {string[]} [args.keys]  Control keys, in mount order.
 * @param {boolean}  [args.seek]  The progress control may seek (desktop; mobile `scrub`).
 * @return {Object[]} The controls that mounted.
 */
export function mountControls( { dialog, prefix, player, strings = {}, keys = [], seek = false } ) {
	const popovers = createPopovers( dialog );
	return keys
		.filter( ( key ) => Object.prototype.hasOwnProperty.call( CONTROLS, key ) )
		.map( ( key ) => CONTROLS[ key ]( { dialog, prefix, player, strings, seek, popovers } ) )
		.filter( Boolean );
}
```

`bigplay.js`:

```js
import { find } from './find.js';

/** The large play glyph / button. On mobile it is a non-interactive span (CSS), so it only shows the paused state. */
export function createBigPlay( { dialog, prefix, player } ) {
	const button = find( dialog, prefix, 'bigplay' );
	if ( ! button ) {
		return null;
	}
	button.addEventListener( 'click', () => {
		player.wake();
		player.toggle();
	} );
	return { button };
}
```

`playpause.js` (desktop bar):

```js
import { find } from './find.js';

export function createPlayPause( { dialog, prefix, player, strings } ) {
	const button = find( dialog, prefix, 'playpause' );
	if ( ! button ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		button.setAttribute( 'aria-label', video && ! video.paused ? strings.pause || 'Pause' : strings.play || 'Play' );
	};
	button.addEventListener( 'click', () => {
		player.wake();
		player.toggle();
	} );
	[ 'mount', 'play', 'pause' ].forEach( ( type ) => player.on( type, draw ) );
	draw();
	return { button };
}
```

`replay.js`:

```js
import { find } from './find.js';

/** The Replay button shown at the end of a video that does not loop. */
export function createReplay( { dialog, prefix, player } ) {
	const button = find( dialog, prefix, 'replay' );
	if ( ! button ) {
		return null;
	}
	button.addEventListener( 'click', () => {
		const video = player.video();
		if ( video ) {
			video.currentTime = 0;
			player.userPlay();
		}
	} );
	return { button };
}
```

`progress.js` (the scrub bar on desktop, the thin line on mobile):

```js
import { ariaValueText, progressPercent, seekForKey } from '../player-model.js';
import { find } from './find.js';

/**
 * The progress range. It always shows how far the video has played (`--p`, `aria-valuetext`); with `seek` it
 * also seeks on drag, click and the arrow keys. Without `seek` (the mobile line) nothing is bound for input.
 */
export function createProgress( { dialog, prefix, player, seek } ) {
	const scrub = find( dialog, prefix, 'scrub' );
	if ( ! scrub ) {
		return null;
	}
	let dragging = false;

	const draw = () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		const duration = Number.isFinite( video.duration ) ? video.duration : 0;
		scrub.max = String( duration );
		scrub.setAttribute( 'aria-valuetext', ariaValueText( video.currentTime, duration ) );
		scrub.style.setProperty( '--p', `${ progressPercent( video.currentTime, duration ) }%` );
		if ( ! dragging ) {
			scrub.value = String( video.currentTime );
		}
	};
	[ 'mount', 'meta', 'time' ].forEach( ( type ) => player.on( type, draw ) );

	if ( seek ) {
		scrub.addEventListener( 'pointerdown', () => {
			dragging = true;
		} );
		window.addEventListener( 'pointerup', () => {
			if ( dragging ) {
				dragging = false;
				draw();
			}
		} );
		scrub.addEventListener( 'input', () => {
			const video = player.video();
			if ( video ) {
				video.currentTime = Number( scrub.value );
				draw();
			}
		} );
		scrub.addEventListener( 'keydown', ( event ) => {
			const video = player.video();
			const next = video ? seekForKey( event.key, video.currentTime, video.duration ) : null;
			if ( next !== null ) {
				event.preventDefault();
				video.currentTime = next;
				draw();
			}
		} );
	}
	return { scrub };
}
```

`time.js`:

```js
import { timeLabel } from '../player-model.js';
import { find } from './find.js';

export function createTime( { dialog, prefix, player } ) {
	const label = find( dialog, prefix, 'time' );
	if ( ! label ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		const duration = video && Number.isFinite( video.duration ) ? video.duration : 0;
		label.textContent = timeLabel( video ? video.currentTime : 0, duration );
	};
	[ 'mount', 'meta', 'time' ].forEach( ( type ) => player.on( type, draw ) );
	return { label };
}
```

`mute.js` (new; the label is static in the markup, only the pressed state changes):

```js
import { preferences } from '../preferences.js';
import { find } from './find.js';

/** A sound on/off button (mobile). `aria-pressed` is true while muted. */
export function createMute( { dialog, prefix, player } ) {
	const button = find( dialog, prefix, 'mutebtn' );
	if ( ! button ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		const muted = video ? video.muted : preferences.muted;
		button.setAttribute( 'aria-pressed', String( muted ) );
		button.dataset.muted = String( muted );
	};
	button.addEventListener( 'click', () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		preferences.muted = ! video.muted;
		if ( ! preferences.muted && video.volume === 0 ) {
			preferences.volume = 1;
			video.volume = 1;
		}
		video.muted = preferences.muted;
	} );
	[ 'mount', 'volume' ].forEach( ( type ) => player.on( type, draw ) );
	draw();
	return { button };
}
```

`volume.js`:

```js
import { clampVolume } from '../player-model.js';
import { preferences } from '../preferences.js';
import { find } from './find.js';

/** The volume button with its slider popover. A slider at 0 is the mute; moving it off 0 unmutes. */
export function createVolume( { dialog, prefix, player, popovers } ) {
	const button = find( dialog, prefix, 'volbtn' );
	const pop = find( dialog, prefix, 'popover--volume' );
	const slider = find( dialog, prefix, 'volume' );
	if ( ! button || ! pop || ! slider ) {
		return null;
	}
	const item = { pop, trigger: button };
	popovers.register( item );

	const draw = () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		const level = video.muted ? 0 : video.volume;
		slider.value = String( level );
		// The slider's fill is the --v percentage (0 to 100) read by its track in style.css.
		slider.style.setProperty( '--v', String( Math.round( level * 100 ) ) );
		button.dataset.muted = String( video.muted );
	};
	slider.addEventListener( 'input', () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		preferences.volume = clampVolume( Number( slider.value ) );
		preferences.muted = preferences.volume === 0;
		video.volume = preferences.volume;
		video.muted = preferences.muted;
	} );
	button.addEventListener( 'click', () => popovers.toggle( item ) );
	[ 'mount', 'volume' ].forEach( ( type ) => player.on( type, draw ) );
	draw();
	return { button };
}
```

`speed.js`:

```js
import { PLAYBACK_RATES } from '../player-model.js';
import { preferences } from '../preferences.js';
import { find } from './find.js';

/** The playback speed button (shows the rate) with its option popover. */
export function createSpeed( { dialog, prefix, player, popovers } ) {
	const button = find( dialog, prefix, 'ratebtn' );
	const pop = find( dialog, prefix, 'popover--rate' );
	if ( ! button || ! pop ) {
		return null;
	}
	const options = Array.from( pop.querySelectorAll( `.${ prefix }__rateopt` ) );
	const name = button.dataset.label || 'Playback speed';
	const item = { pop, trigger: button };
	popovers.register( item );

	const draw = () => {
		button.textContent = `${ preferences.rate }×`;
		button.setAttribute( 'aria-label', `${ name }, ${ preferences.rate }×` );
		options.forEach( ( option ) => option.setAttribute( 'aria-pressed', String( Number( option.dataset.rate ) === preferences.rate ) ) );
	};
	options.forEach( ( option ) => {
		option.addEventListener( 'click', () => {
			const rate = Number( option.dataset.rate );
			preferences.rate = PLAYBACK_RATES.includes( rate ) ? rate : 1;
			const video = player.video();
			if ( video ) {
				video.playbackRate = preferences.rate;
			}
			draw();
			popovers.closeAll();
			button.focus();
		} );
	} );
	button.addEventListener( 'click', () => popovers.toggle( item ) );
	player.on( 'mount', draw );
	draw();
	return { button };
}
```

`fullscreen.js`:

```js
import { find } from './find.js';

/**
 * Full screen for the player wrapper (so the controls go with the video); iPhone Safari only allows the
 * <video> element itself, so that is the fallback. The button is hidden where neither exists.
 */
export function createFullscreen( { dialog, prefix, player, strings } ) {
	const button = find( dialog, prefix, 'fullscreen' );
	if ( ! button ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		button.hidden = ! ( player.root.requestFullscreen || ( video && video.webkitEnterFullscreen ) );
		button.setAttribute( 'aria-label', document.fullscreenElement ? strings.exitFullscreen || 'Exit full screen' : strings.fullscreen || 'Full screen' );
	};
	button.addEventListener( 'click', () => {
		if ( document.fullscreenElement ) {
			document.exitFullscreen();
			return;
		}
		const video = player.video();
		if ( player.root.requestFullscreen ) {
			player.root.requestFullscreen().catch( () => {} );
		} else if ( video && video.webkitEnterFullscreen ) {
			video.webkitEnterFullscreen();
		}
	} );
	player.on( 'mount', draw );
	document.addEventListener( 'fullscreenchange', draw );
	draw();
	return { button };
}
```

### 5.5 `mobile.js` (full)

```js
/**
 * The mobile video viewer (DPV-011, DPV-019 to DPV-023): fills `dialog.videos-mobile`, plays through the shared
 * engine (player.js) with the controls the settings allow (controls/), reads touches through mobile-gestures.js
 * and owns the details sheet. It knows nothing about the URL: view.js decides what opening, changing and
 * closing do to the history.
 */
import { createSheetController } from '../shared/sheet-controller.js';
import { DOUBLE_TAP_WINDOW } from '../shared/gestures.js';
import { createPlayer } from './player.js';
import { mountControls } from './controls/index.js';
import { classifyStroke, tapAction } from './mobile-gestures.js';
import { neighbours } from './neighbours.js';
import { SEEK_STEP } from './player-model.js';

const WIDE_RATIOS = [ '16x9', '4x3' ];
const SHAPES = { '9x16': 'tall', '1x1': 'square' }; // any other ratio is 'wide'
const FLASH_MS = 700;

/**
 * @param {Object}   args
 * @param {Element}  args.dialog    The mobile dialog.
 * @param {number[]} args.order     Ids in the grid's order.
 * @param {Object}   args.items     Payload items by id.
 * @param {Object}   args.strings   `data-mobile-strings`.
 * @param {{loop?:boolean,doubleTapSkip?:boolean,unmuteOnPlay?:boolean,scrub?:boolean,controls?:string[]}} args.options `data-mobile-options` (Settings → Videos).
 * @param {Function} args.getFilter Returns the active category slug ('' = all).
 * @param {Function} args.onGo      Called with a video id when the visitor asks for another video (▲ ▼, swipe).
 * @param {Function} args.onClose   Called when the visitor asks to close (✕, swipe down from the top edge).
 */
export function createMobileVideo( { dialog, order, items, strings, options = {}, getFilter, onGo, onClose } ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const frame = $( '.videos-mobile__video' );
	const stage = $( '.videos-mobile__stage' );
	const rail = $( '.videos-mobile__rail' );
	const up = $( '.videos-mobile__up' );
	const down = $( '.videos-mobile__down' );
	const titleText = $( '.videos-mobile__title-text' );
	const hint = $( '.videos-mobile__hint' );
	const flashEl = $( '.videos-mobile__flash' );

	const player = createPlayer( dialog, strings, {
		prefix: 'videos-mobile',
		clickToggles: false,
		loop: options.loop !== false,
		unmuteOnPlay: options.unmuteOnPlay === true,
	} );
	mountControls( { dialog, prefix: 'videos-mobile', player, strings, keys: options.controls || [], seek: options.scrub === true } );

	// Created here, at start, never inside a lazy import: its history listener must precede the Router's.
	const sheets = createSheetController( { dialog } );
	sheets.register( 'details', {
		closeOnRotate: false,
		create: async ( { dialog: host, onClose: closed } ) => {
			const { createDetailsSheet } = await import( './details-sheet.js' );
			return createDetailsSheet( { dialog: host, strings, onClose: closed } );
		},
	} );

	let current = null;
	let lastTap = null; // { x, t } of the previous tap
	let tapTimer = 0;
	let flashTimer = 0;
	let stroke = null;

	const near = () => neighbours( order, items, current.id, getFilter() );

	const drawRail = () => {
		const { prev, next, count } = near();
		rail.hidden = count < 2;
		up.setAttribute( 'aria-disabled', String( prev === null ) );
		down.setAttribute( 'aria-disabled', String( next === null ) );
	};

	/** @param {'prev'|'next'} which */
	const goTo = ( which ) => {
		if ( ! current || sheets.isAnyOpen() ) {
			return;
		}
		const id = near()[ which ];
		if ( id !== null ) {
			onGo( id );
		}
	};

	const flash = ( kind ) => {
		flashEl.dataset.kind = kind;
		flashEl.classList.remove( 'is-on' );
		void flashEl.offsetWidth; // restart the animation
		flashEl.classList.add( 'is-on' );
		window.clearTimeout( flashTimer );
		flashTimer = window.setTimeout( () => flashEl.classList.remove( 'is-on' ), FLASH_MS );
	};

	const cancelTap = () => {
		window.clearTimeout( tapTimer );
		lastTap = null;
	};

	const onTap = ( x ) => {
		hint.hidden = true;
		player.wake();
		const action = tapAction( { x, width: stage.clientWidth, now: Date.now(), last: lastTap, skip: options.doubleTapSkip !== false } );
		window.clearTimeout( tapTimer );
		if ( action.kind === 'skip' ) {
			lastTap = null;
			player.skip( action.seconds );
			flash( action.seconds > 0 ? 'forward' : 'back' );
		} else if ( action.kind === 'toggle-now' ) {
			player.toggle();
		} else if ( action.kind === 'toggle-later' ) {
			lastTap = { x, t: Date.now() };
			tapTimer = window.setTimeout( () => {
				lastTap = null;
				player.toggle();
			}, DOUBLE_TAP_WINDOW );
		} else {
			lastTap = null;
		}
	};

	// Touches on the stage only; every control is a sibling painted above it (D5).
	stage.addEventListener( 'pointerdown', ( event ) => {
		stroke = { x0: event.clientX, y0: event.clientY, t0: event.timeStamp, id: event.pointerId };
	} );
	stage.addEventListener( 'pointerup', ( event ) => {
		const start = stroke;
		stroke = null;
		if ( ! start || start.id !== event.pointerId || sheets.isAnyOpen() ) {
			return;
		}
		const kind = classifyStroke( { x0: start.x0, y0: start.y0, x1: event.clientX, y1: event.clientY, dt: event.timeStamp - start.t0 } );
		if ( kind === 'tap' ) {
			onTap( event.clientX - stage.getBoundingClientRect().left );
		} else if ( kind === 'next' || kind === 'prev' ) {
			hint.hidden = true;
			goTo( kind );
		} else if ( kind === 'close' ) {
			onClose();
		}
	} );
	stage.addEventListener( 'pointercancel', () => {
		stroke = null;
	} );

	$( '.videos-mobile__close' ).addEventListener( 'click', () => onClose() );
	up.addEventListener( 'click', () => goTo( 'prev' ) );
	down.addEventListener( 'click', () => goTo( 'next' ) );
	const openDetails = () => {
		if ( current ) {
			hint.hidden = true;
			sheets.open( 'details', { doc: current, opener: document.activeElement } );
		}
	};
	$( '.videos-mobile__title' ).addEventListener( 'click', openDetails );
	$( '.videos-mobile__details' ).addEventListener( 'click', openDetails );

	// Space, the progress keys and Escape on a popover belong to the engine and the controls (D14).
	const KEYS = {
		ArrowUp: () => goTo( 'prev' ),
		ArrowDown: () => goTo( 'next' ),
		ArrowLeft: () => player.skip( -SEEK_STEP ),
		ArrowRight: () => player.skip( SEEK_STEP ),
	};
	dialog.addEventListener( 'keydown', ( event ) => {
		const run = KEYS[ event.key ];
		if ( run && ! sheets.isAnyOpen() && ! event.target.closest( 'button, a, input, select, textarea' ) ) {
			event.preventDefault();
			run();
		}
	} );

	return {
		isOpen: () => dialog.open,
		/** Fills the viewer for `item` and plays it. The caller shows the dialog (`showModal()`) the first time. */
		show( item, { gesture } ) {
			current = item;
			cancelTap();
			sheets.reset();
			frame.dataset.shape = SHAPES[ item.ratio ] || 'wide';
			titleText.textContent = item.title;
			hint.hidden = ! WIDE_RATIOS.includes( item.ratio );
			drawRail();
			player.mount( item, gesture );
			player.wake();
		},
		unload() {
			cancelTap();
			player.unload();
		},
		/** History entries a sheet still holds (see collateral-modal/view.js `finishClose( extra )`). */
		release: () => sheets.release(),
	};
}
```

### 5.6 `details-sheet.js` (full)

```js
/**
 * The mobile Details sheet of a video (DPV-021): a bottom sheet over the dimmed viewer with the details pane's
 * content (category and shape pills, title, description, Made with, Results) and a link to the original post.
 * The shell (dim layer, handle, drag to dismiss, Esc, history) is shared (shared/bottom-sheet.js); mobile.js
 * loads this module with import() on the first tap, through shared/sheet-controller.js. Template:
 * collateral-modal/details-sheet.js.
 *
 * `videoDetailsModel()` is pure, so the content rules run under `node --test`. A section is left out when it
 * has no data. All text is written with textContent.
 */
import { el, svgIcon } from '../shared/dom.js';
import { createBottomSheet } from '../shared/bottom-sheet.js';

const CLOSE_ICON = 'M6 6l12 12M18 6L6 18';
const clean = ( value ) => ( typeof value === 'string' ? value.trim() : '' );

/**
 * What the sheet shows for one video (the payload item). Pure.
 *
 * @param {Object} item    title, category, shape, description, tools, stats, postUrl.
 * @param {Object} strings `data-mobile-strings`: `results`, `madeWith`, `openOriginal`.
 */
export function videoDetailsModel( item, strings = {} ) {
	const d = item && typeof item === 'object' ? item : {};
	const s = strings && typeof strings === 'object' ? strings : {};
	const postUrl = clean( d.postUrl );
	return {
		title: clean( d.title ),
		category: clean( d.category ),
		shape: clean( d.shape ),
		description: clean( d.description ),
		tools: Array.isArray( d.tools ) ? d.tools.map( clean ).filter( Boolean ) : [],
		stats: Array.isArray( d.stats )
			? d.stats.map( ( row ) => ( { label: clean( row && row.label ), value: clean( row && row.value ) } ) ).filter( ( row ) => row.label !== '' || row.value !== '' )
			: [],
		postUrl: /^https?:\/\//i.test( postUrl ) ? postUrl : '',
		resultsLabel: clean( s.results ) || 'Results',
		madeLabel: clean( s.madeWith ) || 'Made with',
		originalLabel: clean( s.openOriginal ) || 'Open original post',
	};
}

/**
 * Builds the sheet inside the mobile dialog (once; reopening rewrites the text only).
 *
 * @param {Object}   args
 * @param {Element}  args.dialog  The mobile dialog.
 * @param {Object}   args.strings `data-mobile-strings`.
 * @param {Function} args.onClose Called when the visitor closes the sheet (not on `close( true )`).
 * @param {string}   [args.prefix='videos-mobile']
 * @return {{open:Function,close:Function,isOpen:Function,reset:Function}}
 */
export function createDetailsSheet( { dialog, strings = {}, onClose, prefix = 'videos-mobile' } ) {
	const titleId = `${ prefix }-details-title`;
	const closeLabel = clean( strings.handle ) || 'Close details';
	const shell = createBottomSheet( {
		dialog,
		titleId,
		handleLabel: closeLabel,
		modifier: `${ prefix }__sheet--details`,
		onDismiss: () => close( false ),
		prefix,
	} );
	const { head, body } = shell;

	const title = el( 'h3', `${ prefix }__details-title`, { id: titleId } );
	const closeBtn = el( 'button', `${ prefix }__sheet-close`, { type: 'button', 'aria-label': closeLabel, 'data-no-drag': '' } );
	closeBtn.appendChild( svgIcon( CLOSE_ICON ) );
	head.append( title, closeBtn );

	/** A captioned section: `{ wrap, caption }`, the list is appended to `wrap` by the caller. */
	const group = ( name ) => {
		const wrap = el( 'section', `${ prefix }__details-${ name }` );
		const caption = el( 'h4', `${ prefix }__details-caption` );
		wrap.append( caption );
		return { wrap, caption };
	};

	const pills = el( 'p', `${ prefix }__details-pills` );
	const category = el( 'span', `${ prefix }__details-category` );
	const shape = el( 'span', `${ prefix }__details-size` );
	pills.append( category, shape );
	const description = el( 'p', `${ prefix }__details-desc` );
	const made = group( 'made' );
	const tools = el( 'ul', `${ prefix }__details-tools` );
	made.wrap.append( tools );
	const results = group( 'results' );
	const rows = el( 'dl', `${ prefix }__details-rows` );
	results.wrap.append( rows );
	const original = el( 'a', `${ prefix }__details-original`, { target: '_blank', rel: 'noopener', role: 'button' } );
	body.append( pills, description, made.wrap, results.wrap, original );

	const fill = ( item ) => {
		const model = videoDetailsModel( item, strings );
		title.textContent = model.title;
		category.textContent = model.category;
		category.hidden = model.category === '';
		shape.textContent = model.shape;
		shape.hidden = model.shape === '';
		pills.hidden = model.category === '' && model.shape === '';
		description.textContent = model.description;
		description.hidden = model.description === '';
		made.caption.textContent = model.madeLabel;
		tools.replaceChildren(
			...model.tools.map( ( name ) => {
				const li = el( 'li', `${ prefix }__details-tool` );
				li.textContent = name;
				return li;
			} )
		);
		made.wrap.hidden = model.tools.length === 0;
		results.caption.textContent = model.resultsLabel;
		rows.replaceChildren(
			...model.stats.map( ( stat ) => {
				const row = el( 'div', `${ prefix }__details-row` );
				const label = el( 'dt' );
				label.textContent = stat.label;
				const value = el( 'dd' );
				value.textContent = stat.value;
				row.append( label, value );
				return row;
			} )
		);
		results.wrap.hidden = model.stats.length === 0;
		original.textContent = model.originalLabel;
		original.hidden = model.postUrl === '';
		if ( model.postUrl ) {
			original.href = model.postUrl;
		} else {
			original.removeAttribute( 'href' );
		}
		body.scrollTop = 0;
	};

	const close = ( silent = false ) => {
		if ( ! shell.isOpen() ) {
			return;
		}
		shell.hide( silent );
		if ( ! silent && onClose ) {
			onClose();
		}
	};
	closeBtn.addEventListener( 'click', () => close( false ) );

	return {
		/** @param {Object} args { doc (the payload item), opener } */
		open( { doc, opener } ) {
			if ( ! doc ) {
				return false;
			}
			fill( doc );
			shell.show( { opener, focus: closeBtn } );
			return true;
		},
		close,
		isOpen: () => shell.isOpen(),
		/** Another video opens, or the viewer closes. */
		reset() {
			close( true );
		},
	};
}
```

### 5.7 PHP

#### 5.7a `functions.php`: options, control keys, markup and strings (full)

Add next to `devportfolio_video_modal_strings()`, each inside `if ( ! function_exists( 'name' ) ) :` … `endif;`.

```php
/**
 * The mobile viewer's switches (Settings → Videos). Same keys and defaults as the plugin's
 * cptdp_videos_mobile_options(); a test pins that.
 *
 * @return array<string,bool>
 */
function devportfolio_video_mobile_options() {
	$defaults = array(
		'mute'            => true,
		'progress'        => true,
		'scrub'           => false,
		'time'            => false,
		'volume'          => false,
		'speed'           => false,
		'fullscreen'      => false,
		'loop'            => true,
		'double_tap_skip' => true,
	);
	$saved    = function_exists( 'cptdp_get_videos_settings' ) ? cptdp_get_videos_settings() : array();
	$options  = array_map( 'boolval', array_merge( $defaults, is_array( $saved ) ? array_intersect_key( $saved, $defaults ) : array() ) );
	// Dragging needs the line.
	$options['progress'] = $options['progress'] || $options['scrub'];
	return $options;
}

/**
 * The control keys the mobile viewer has, in mount and markup order. One list drives both the markup and the
 * script (`data-mobile-options.controls`), so they cannot disagree.
 *
 * @param array<string,bool> $options devportfolio_video_mobile_options().
 * @return string[]
 */
function devportfolio_video_mobile_control_keys( array $options ) {
	$wanted = array(
		'bigplay'    => true,
		'replay'     => ! $options['loop'],
		'progress'   => $options['progress'],
		'time'       => $options['time'],
		'mute'       => $options['mute'],
		'volume'     => $options['volume'],
		'speed'      => $options['speed'],
		'fullscreen' => $options['fullscreen'],
	);
	return array_keys( array_filter( $wanted ) );
}

/**
 * What the script needs from the switches (`data-mobile-options` on the block root).
 *
 * @param array<string,bool> $options devportfolio_video_mobile_options().
 * @return array<string,mixed>
 */
function devportfolio_video_mobile_script_options( array $options ) {
	return array(
		'loop'          => $options['loop'],
		'doubleTapSkip' => $options['double_tap_skip'],
		'unmuteOnPlay'  => ! $options['mute'],
		'scrub'         => $options['scrub'],
		'controls'      => devportfolio_video_mobile_control_keys( $options ),
	);
}

/**
 * The markup of the mobile viewer's controls: one block per enabled key. Mute, volume, speed and full screen
 * share the extras column.
 *
 * @param array<string,bool> $options devportfolio_video_mobile_options().
 * @return string
 */
function devportfolio_video_mobile_controls( array $options ) {
	$extras_keys = array( 'mute', 'volume', 'speed', 'fullscreen' );
	$main        = '';
	$extras      = '';
	foreach ( devportfolio_video_mobile_control_keys( $options ) as $key ) {
		$html = devportfolio_video_mobile_control_html( $key, $options );
		if ( in_array( $key, $extras_keys, true ) ) {
			$extras .= $html;
		} else {
			$main .= $html;
		}
	}
	return $main . ( '' === $extras ? '' : '<div class="videos-mobile__extras">' . $extras . '</div>' );
}

/**
 * The markup of one mobile control. Class names are `videos-mobile__<element>`: the same names the control
 * modules in blocks/video-modal/controls/ look for.
 *
 * @param string             $key     A control key.
 * @param array<string,bool> $options devportfolio_video_mobile_options().
 * @return string
 */
function devportfolio_video_mobile_control_html( $key, array $options ) {
	$icon  = static fn( $path ) => devportfolio_viewer_icon( 'videos-mobile', $path );
	switch ( $key ) {
		case 'bigplay':
			return '<span class="videos-mobile__bigplay" aria-hidden="true">' . $icon( 'M8 5l11 7-11 7z' ) . '</span>';
		case 'replay':
			return '<button class="videos-mobile__replay" type="button">' . esc_html_x( 'Replay', 'video modal', 'devportfolio' ) . '</button>';
		case 'progress':
			return sprintf(
				'<input class="videos-mobile__scrub" type="range" min="0" max="0" step="any" value="0" aria-label="%s"%s>',
				esc_attr_x( 'Seek', 'video modal', 'devportfolio' ),
				$options['scrub'] ? '' : ' inert aria-hidden="true"'
			);
		case 'time':
			return '<span class="videos-mobile__time" aria-hidden="true">0:00 / 0:00</span>';
		case 'mute':
			return sprintf(
				'<button class="videos-mobile__mutebtn videos-mobile__btn" type="button" aria-pressed="false" aria-label="%s">%s%s</button>',
				esc_attr_x( 'Mute', 'video modal mobile', 'devportfolio' ),
				str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--on"', $icon( 'M4 10v4h4l5 4V6L8 10H4zM16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11' ) ),
				str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--off"', $icon( 'M4 10v4h4l5 4V6L8 10H4zM17 9l4 6M21 9l-4 6' ) )
			);
		case 'volume':
			return sprintf(
				'<div class="videos-mobile__pop"><button class="videos-mobile__volbtn videos-mobile__btn" type="button" aria-expanded="false" aria-controls="videos-mobile-volume" aria-label="%1$s">%2$s</button><div class="videos-mobile__popover videos-mobile__popover--volume" id="videos-mobile-volume" hidden><input class="videos-mobile__volume" type="range" min="0" max="1" step="0.05" value="1" aria-label="%1$s"></div></div>',
				esc_attr_x( 'Volume', 'video modal', 'devportfolio' ),
				$icon( 'M4 10v4h4l5 4V6L8 10H4zM16 9a4 4 0 0 1 0 6' )
			);
		case 'speed':
			$options_html = '';
			foreach ( array( '0.5', '0.75', '1', '1.25', '1.5', '2' ) as $rate ) {
				$options_html .= sprintf( '<button class="videos-mobile__rateopt" type="button" data-rate="%1$s" aria-pressed="%2$s">%1$s×</button>', esc_attr( $rate ), '1' === $rate ? 'true' : 'false' );
			}
			return sprintf(
				'<div class="videos-mobile__pop"><button class="videos-mobile__ratebtn videos-mobile__btn" type="button" data-label="%1$s" aria-expanded="false" aria-controls="videos-mobile-rate">1×</button><div class="videos-mobile__popover videos-mobile__popover--rate" id="videos-mobile-rate" hidden>%2$s</div></div>',
				esc_attr_x( 'Playback speed', 'video modal', 'devportfolio' ),
				$options_html
			);
		case 'fullscreen':
			return sprintf(
				'<button class="videos-mobile__fullscreen videos-mobile__btn" type="button" aria-label="%s">%s</button>',
				esc_attr_x( 'Full screen', 'video modal', 'devportfolio' ),
				$icon( 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5' )
			);
	}
	return '';
}

/**
 * Words the mobile viewer and its sheet need (`data-mobile-strings`).
 *
 * @return array<string,string>
 */
function devportfolio_video_mobile_strings() {
	return array(
		/* translators: %s: video title. */
		'playerFor'      => _x( 'Video: %s', 'video modal', 'devportfolio' ),
		'fullscreen'     => _x( 'Full screen', 'video modal', 'devportfolio' ),
		'exitFullscreen' => _x( 'Exit full screen', 'video modal', 'devportfolio' ),
		'results'        => _x( 'Results', 'video modal mobile', 'devportfolio' ),
		'madeWith'       => _x( 'Made with', 'video modal mobile', 'devportfolio' ),
		'openOriginal'   => _x( 'Open original post', 'video modal mobile', 'devportfolio' ),
		'handle'         => _x( 'Close details', 'video modal mobile', 'devportfolio' ),
	);
}
```

`devportfolio_viewer_icon( 'videos-mobile', … )` returns the 24px stroke SVG with class `videos-mobile__icon`; the `str_replace` adds the `--on` / `--off` modifier for the mute button's two icons.

#### 5.7b `render.php` (edits)

1. After `$devportfolio_close_icon = …;` add:

```php
$devportfolio_mobile_title_id = wp_unique_id( 'videos-mobile-title-' );
$devportfolio_mobile_options  = devportfolio_video_mobile_options();
$devportfolio_pill_icon       = '<svg class="videos-mobile__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/></svg>';
```

2. In the `get_block_wrapper_attributes()` array, after `'data-strings' => …,` add:

```php
		'data-mobile-strings' => wp_json_encode( devportfolio_video_mobile_strings() ),
		'data-mobile-options' => wp_json_encode( devportfolio_video_mobile_script_options( $devportfolio_mobile_options ) ),
```

3. Insert after the desktop `</dialog>` and before the `<script … videos-modal__data>`:

```php
	<dialog class="videos-mobile" aria-labelledby="<?php echo esc_attr( $devportfolio_mobile_title_id ); ?>">
		<div class="videos-mobile__panel">
			<div class="videos-mobile__video" data-state="paused" data-shape="tall">
				<div class="videos-mobile__stage"></div>
				<p class="videos-mobile__state" role="status" hidden data-loading="<?php echo esc_attr_x( 'Loading video…', 'video modal', 'devportfolio' ); ?>" data-unavailable="<?php echo esc_attr_x( 'This video can’t be played here.', 'video modal', 'devportfolio' ); ?>" data-hint="">
					<span class="videos-mobile__state-text"></span>
					<span class="videos-mobile__state-hint"></span>
				</p>
				<span class="videos-mobile__flash" aria-hidden="true" data-kind="forward"></span>
				<?php echo devportfolio_video_mobile_controls( $devportfolio_mobile_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?>
			</div>
			<div class="videos-mobile__scrim" aria-hidden="true"></div>
			<button class="videos-mobile__close videos-mobile__btn" type="button" aria-label="<?php echo esc_attr_x( 'Close video', 'video modal', 'devportfolio' ); ?>"><?php echo devportfolio_viewer_icon( 'videos-mobile', 'M6 6l12 12M18 6L6 18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></button>
			<div class="videos-mobile__rail" hidden>
				<button class="videos-mobile__up videos-mobile__btn" type="button" aria-disabled="false" aria-label="<?php echo esc_attr_x( 'Previous video', 'video modal mobile', 'devportfolio' ); ?>"><?php echo devportfolio_viewer_icon( 'videos-mobile', 'M6 15l6-6 6 6' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></button>
				<button class="videos-mobile__down videos-mobile__btn" type="button" aria-disabled="false" aria-label="<?php echo esc_attr_x( 'Next video', 'video modal mobile', 'devportfolio' ); ?>"><?php echo devportfolio_viewer_icon( 'videos-mobile', 'M6 9l6 6 6-6' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></button>
			</div>
			<span class="videos-mobile__pill videos-mobile__hint" hidden><?php echo $devportfolio_pill_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><?php echo esc_html_x( 'Rotate for full screen', 'video modal mobile', 'devportfolio' ); ?></span>
			<button class="videos-mobile__title" id="<?php echo esc_attr( $devportfolio_mobile_title_id ); ?>" type="button" aria-haspopup="dialog"><span class="videos-mobile__title-text"></span><?php echo devportfolio_viewer_icon( 'videos-mobile', 'M9 6l6 6-6 6' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></button>
			<button class="videos-mobile__details videos-mobile__btn" type="button" aria-haspopup="dialog" aria-label="<?php echo esc_attr_x( 'Video details', 'video modal mobile', 'devportfolio' ); ?>"><?php echo devportfolio_viewer_icon( 'videos-mobile', 'M12 11v6M12 7.5v.01M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></button>
		</div>
	</dialog>
```

The stage is first in `.videos-mobile__video`, so everything the helper prints paints above it (D5). The controls that must go full screen with the video (progress line, time, extras, big play, Replay) live inside `.videos-mobile__video`; ✕, ▲ ▼, title, ⓘ, the hint and the scrim are in the panel. The title button holds the title text, so it is the dialog's accessible name.

### 5.8 `view.js` (edits)

Read `$T/blocks/collateral-modal/view.js` lines 126 to 420 first: this follows it. Edits, in file order:

1. **Imports.** After `import { createPlayer } from './player.js';` add:

```js
import { mountControls, DESKTOP_CONTROLS } from './controls/index.js';
import { createMobileVideo } from './mobile.js';
```

2. **Elements and guard.** After `const dialog = …;` add `const mobileDialog = root ? root.querySelector( '.videos-mobile' ) : null;` and change `if ( dialog && dataEl ) {` to `if ( dialog && mobileDialog && dataEl ) {`.

3. **Desktop controls.** Right after `const player = createPlayer( dialog, strings );` add:

```js
	mountControls( { dialog, prefix: 'videos-modal', player, strings, keys: DESKTOP_CONTROLS, seek: true } );
```

4. **State.** After `let closingFromHistory = false;` add `let swapping = false;` and `let skipNextPop = false;`.

5. **Replace the `unload` helper and its comment** with nothing (D20: each dialog unloads its own player), and **after `activeFilter`** add the mobile instance and the dialog helpers:

```js
	const mobile = createMobileVideo( {
		dialog: mobileDialog,
		order,
		items,
		strings: readJson( root.getAttribute( 'data-mobile-strings' ) ),
		options: readJson( root.getAttribute( 'data-mobile-options' ) ),
		getFilter: activeFilter,
		onGo: ( id ) => goTo( id ),
		onClose: () => mobileDialog.close(),
	} );

	const isOpen = () => dialog.open || mobileDialog.open;
	const shownDialog = () => ( dialog.open ? dialog : mobileDialog.open ? mobileDialog : null );
```

6. **`finishClose`.** Replace the whole function with:

```js
	/**
	 * A dialog closed. `extra` is the number of history entries a mobile sheet left open. Mirrors
	 * collateral-modal/view.js: a swap only reconciles, a sheet's entry goes with the viewer's.
	 */
	const finishClose = ( extra = 0 ) => {
		if ( swapping ) {
			// The other dialog is taking over; only the sheet's entry (same URL) is left to remove.
			swapping = false;
			if ( extra > 0 ) {
				skipNextPop = true;
				window.history.go( -extra );
			}
			return;
		}
		document.documentElement.style.overflow = '';
		const target = focusTarget();
		opener = null;
		if ( closingFromHistory ) {
			closingFromHistory = false;
			pushed = false;
		} else if ( pushed ) {
			pushed = false;
			window.history.go( -( 1 + extra ) );
		} else if ( extra > 0 ) {
			skipNextPop = true;
			window.history.go( -extra );
		} else if ( deeplink.slugFromUrl( window.location.href ) ) {
			window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
		}
		if ( target ) {
			target.focus();
		}
	};
```

7. **`show`, `open`, `replaceUrl`, `goTo`.** Replace the whole `show` function and add the three helpers; change `swapTo`'s `window.history.replaceState( … )` line to `replaceUrl( item );`:

```js
	const replaceUrl = ( item ) => window.history.replaceState( window.history.state, '', deeplink.urlWithSlug( window.location.href, item.slug ) );

	/** Fills and shows the dialog this screen gets: desktop (side by side) or mobile (full screen). */
	const open = ( item, gesture ) => {
		if ( routing.isDesktop() ) {
			fill( item, gesture );
			dialog.showModal();
			els.close.focus();
			return;
		}
		mobile.show( item, { gesture } );
		mobileDialog.showModal();
		mobileDialog.querySelector( '.videos-mobile__close' ).focus();
	};

	const show = ( id, { push, trigger } ) => {
		const item = items[ id ];
		if ( ! item || isOpen() ) {
			return;
		}
		currentId = Number( id );
		if ( push && item.slug ) {
			window.history.pushState( { videoSlug: item.slug }, '', deeplink.urlWithSlug( window.location.href, item.slug ) );
			pushed = true;
		}
		opener = trigger;
		document.documentElement.style.overflow = 'hidden';
		open( item, push );
	};

	/** The mobile viewer's ▲ ▼ and swipes: another video, the URL replaced, sound allowed (a gesture). */
	const goTo = ( id ) => {
		const item = items[ id ];
		if ( item ) {
			currentId = Number( id );
			replaceUrl( item );
			mobile.show( item, { gesture: true } );
		}
	};
```

8. **Close listeners.** Replace `dialog.addEventListener( 'close', finishClose );` with:

```js
	dialog.addEventListener( 'close', () => {
		player.unload();
		finishClose();
	} );
	mobileDialog.addEventListener( 'close', () => {
		mobile.unload();
		finishClose( mobile.release() );
	} );
```

9. **`popstate`.** Replace the listener body (keep it at module top level, capture phase, not in `DOMContentLoaded`):

```js
		( event ) => {
			if ( skipNextPop ) {
				skipNextPop = false;
				event.stopImmediatePropagation();
				return;
			}
			const slug = deeplink.slugFromUrl( window.location.href );
			if ( ! slug && isOpen() ) {
				event.stopImmediatePropagation();
				closingFromHistory = true;
				shownDialog().close();
			} else if ( slug && ! isOpen() && idBySlug[ slug ] ) {
				event.stopImmediatePropagation();
				pushed = true;
				show( idBySlug[ slug ], { push: false, trigger: null } );
			} else if ( slug && isOpen() && hasSheetState( event.state ) ) {
				event.stopImmediatePropagation();
			}
		},
```

10. **`routing.onChange`.** Replace the body (and its "DPV-011" comment) with the swap:

```js
	// Crossing the 700px / sideways boundary with a viewer open: the other dialog takes over, same video and URL.
	routing.onChange( () => {
		const shown = shownDialog();
		if ( ! shown || ( shown === dialog ) === routing.isDesktop() ) {
			return;
		}
		swapping = true;
		shown.close();
		open( items[ currentId ], false );
	} );
```

11. Update the file's top comment (the mobile viewer exists; `mobile.js` drives it).

### 5.9 CSS

#### `video-modal/style.css` (append a block headed `/* DPV-011, DPV-019 to DPV-023: mobile viewer */`)

Use the modal tokens already mapped to `--vm-*` (`grep -n "\-\-vm-" style.css`). All selectors start with `.videos-mobile`.

- **Which dialog shows.** Repeat the two routing queries **verbatim** from `shared/routing.js` (copy the exact media-query text from where `document-modal/style.css` does this for `.documents-modal` / `.documents-mobile`): the desktop-only query hides `.videos-mobile`; the mobile query hides `.videos-modal`. A node test compares them (5.10).
- `.videos-mobile`: `position: fixed; inset: 0; width: 100%; height: 100dvh; max-width: none; max-height: none; margin: 0; padding: 0; border: 0; background: #000; color: #fff;`; `::backdrop { background: none; }`; open fade 160ms, none under `prefers-reduced-motion: reduce`.
- `__panel`: `position: relative; width: 100%; height: 100%; overflow: hidden;` (the sheet shell needs this class).
- `__video`: `position: absolute; inset: 0;`. `__stage`: `position: absolute; inset: 0; display: grid; place-items: center; touch-action: none;`. In the stage: `video, iframe { width: 100%; height: 100%; border: 0; object-fit: contain; }` (letterboxes square and wide; a tall video fills); `[data-shape="wide"] iframe { aspect-ratio: 16 / 9; height: auto; max-height: 100%; }`. Held sideways the stage keeps `height: 100%`, so the video fills the screen height.
- `__scrim`: `position: absolute; inset: auto 0 0 0; height: 30%; pointer-events: none; background: linear-gradient( to top, var( --wp--custom--modal--scrim ), transparent );`.
- `__btn` (✕, ▲, ▼, ⓘ and the extras' buttons): `width: 38px; height: 38px; border-radius: 999px; display: grid; place-items: center; padding: 0; background: transparent; color: #fff; border: 1.5px solid rgb( 255 255 255 / 0.85 );` plus a `::before` extending the hit area to 44px (`inset: -3px`). `:focus-visible`: `outline: 2px solid var( --wp--custom--modal--focus ); outline-offset: 2px`. `[aria-disabled="true"]`: `opacity: 0.35`.
- Positions (add `env( safe-area-inset-* )` to every edge offset): `__close` `position: absolute; top: 12px; left: 12px`; `__rail` `position: absolute; right: 12px; top: 50%; transform: translateY( -50% ); display: grid; gap: 12px` (its buttons static); `__details` `position: absolute; right: 12px; bottom: 28px`; `__title` `position: absolute; left: 16px; right: 66px; bottom: 28px; display: flex; align-items: center; gap: 4px; min-height: 44px; background: none; border: 0; padding: 0; color: #fff; font: inherit; font-size: 16px; font-weight: 700; text-align: left; text-shadow: 0 1px 2px rgb( 0 0 0 / 0.5 )`; `__title-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }`; the chevron icon `flex: none; width: 18px; height: 18px`. **Title and ⓘ sit at `bottom: 28px`, above the 24px progress touch zone** (D18).
- `__hint` (`__pill`): `position: absolute; left: 50%; bottom: 92px; transform: translateX( -50% ); display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 999px; background: rgb( 0 0 0 / 0.6 ); color: #fff; font-size: 13px; font-weight: 600; pointer-events: none;`. Hidden sideways: `@media ( orientation: landscape ) { .videos-mobile__hint { display: none; } }`.
- `__flash`: centred 64px round dark glyph; `[data-kind="forward"]` and `[data-kind="back"]` choose one of two small `background-image` SVG data URIs (no emoji or text glyphs as icons); `opacity: 0`; `.is-on { animation: videos-mobile-flash 600ms ease-out; }` (scale 0.9 to 1.1, fade out); `animation: none` under `prefers-reduced-motion: reduce`.
- `__state`: the look of `.videos-modal__state` (centred white text); add the selector to its rule.
- **Controls** (the markup of 5.7 exists only for enabled keys, so no rule needs to know the settings):
  - `__bigplay`: `position: absolute; inset: 0; margin: auto; width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; background: rgb( 0 0 0 / 0.45 ); pointer-events: none; opacity: 0; transition: opacity 160ms;` and `[data-state="paused"] .videos-mobile__bigplay { opacity: 1; }`.
  - `__replay`: the desktop Replay pill look (add the selector to that rule), shown only at `data-state="ended"`.
  - `__scrub`: `position: absolute; left: 0; right: 0; bottom: env( safe-area-inset-bottom ); height: 24px; margin: 0; appearance: none; background: transparent;` a 2px track filled to `--p` (white fill, `rgb( 255 255 255 / 0.35 )` rest; 4px while `:active` or `:focus-visible`), a 12px white thumb only when not `[inert]`; `[inert] { pointer-events: none; }`. White, never the brand gradient.
  - `__time`: `position: absolute; left: 16px; bottom: calc( 80px + env( safe-area-inset-bottom ) ); font-size: 12px; font-variant-numeric: tabular-nums; color: #fff; text-shadow: 0 1px 2px rgb( 0 0 0 / 0.5 );`.
  - `__extras`: `position: absolute; top: calc( 12px + env( safe-area-inset-top ) ); right: calc( 12px + env( safe-area-inset-right ) ); display: grid; gap: 10px;`; its buttons use `__btn`. **Held sideways** (the sideways query) `grid-auto-flow: column` so four extras do not collide with the rail.
  - `__pop { position: relative; }`; `__popover { position: absolute; right: calc( 100% + 8px ); top: 0; max-width: calc( 100vw - 80px ); }` (opens to the left; sideways: `top: calc( 100% + 8px ); right: 0`). Reuse the desktop look of `__popover`, `__volume` and `__rateopt` by adding the `.videos-mobile__X` selector to the existing lists.
  - `__mutebtn[data-muted="true"] .videos-mobile__icon--on, __mutebtn[data-muted="false"] .videos-mobile__icon--off { display: none; }`. `__volbtn[data-muted="true"]` dims as on desktop.
  - `[data-state="embed"]` hides `__bigplay`, `__scrub`, `__time`, `__extras`, `__replay`.
- `[hidden]` rules: `__rail[hidden], __hint[hidden], __state[hidden], __popover[hidden] { display: none; }` (the author rules above set `display`).

#### Sheet shell (shared)

In `$T/blocks/document-modal/style.css` find every rule whose selector list contains `.collaterals-mobile__dim`, `.collaterals-mobile__sheet…` or `.collaterals-mobile__grab…` (`grep -n "collaterals-mobile__\(dim\|sheet\|grab\)" $T/blocks/document-modal/style.css`) and append the same selector with `.videos-mobile` in place of `.collaterals-mobile` to each list. Do **not** touch rules about `__page`, `__viewer`, `__footer`, `__pages`. Then add the details content: `grep -rn "collaterals-mobile__details-\(pills\|category\|size\|desc\|made\|results\|caption\|tools\|tool\|rows\|row\|title\)" $T/blocks/*/style.css` and add the `.videos-mobile__details-*` selectors to those lists (the new `__details-original` is a full-width outline button: copy the declarations of `collaterals-mobile__details-copy`). Finally in `$T/functions.php` (the code that enqueues `devportfolio-viewer`, ~line 89) add the Videos archive (`is_post_type_archive( 'video' )`) to its condition; update any PHP test that pins the list of pages.

#### `theme.json`

In `settings.custom.modal` add `"scrim": "rgb( 0 0 0 / 0.6 )"` after the last token (keep the file valid JSON).

### 5.10 Other edits

- `$T/blocks/document-modal/tests/routing.test.mjs`: it compares the CSS media queries with `routing.js` for the document and collateral stylesheets. Add `video-modal/style.css` the same way (read the test first; add one row if it takes a list).
- `$P/custom-post-types-dp.php`: `Version: 0.31.0`.
- `AGENTS.md`: in the `blocks/video-modal/` bullet replace "No mobile viewer yet (DPV-011): a mobile screen opens nothing, and a resize to mobile closes the dialog." with a paragraph on the second dialog, the engine/controls split (`player.js`, `preferences.js`, `controls/`), `mobile.js`, `mobile-gestures.js`, `neighbours.js`, `details-sheet.js`, the shared sheet shell and the `devportfolio-viewer` enqueue on the Videos archive; add Settings → Videos (`includes/settings/videos.php`, option `cptdp_videos`, `cptdp_get_videos_settings()`, version 0.31.0) to the plugin Overview; add the new test files to the test notes. Update the expected totals once the suites have run.

### 5.11 Plugin: Settings → Videos (`$P/includes/settings/videos.php`, full)

The loader requires every file in `includes/settings/`; no registration elsewhere.

```php
<?php
/**
 * Videos settings (Settings > Videos): which extra controls and behaviours the mobile video viewer has.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * The switches, in page order. The single source of truth: defaults, the sanitiser, the REST schema and the
 * form are all derived from it, so a new switch is one entry here (and one control module in the theme).
 *
 * @return array<string,array{section:string,default:bool,label:string,help:string}>
 */
function cptdp_videos_mobile_options(): array {
	return array(
		'mute'            => array(
			'section' => 'controls',
			'default' => true,
			'label'   => __( 'Mute button', 'custom-post-types-dp' ),
			'help'    => __( 'A round sound on/off button at the top right of the mobile video. Without it, a tap that resumes a paused video turns the sound on.', 'custom-post-types-dp' ),
		),
		'progress'        => array(
			'section' => 'controls',
			'default' => true,
			'label'   => __( 'Progress line', 'custom-post-types-dp' ),
			'help'    => __( 'A thin, read-only line along the bottom edge showing how far the video has played.', 'custom-post-types-dp' ),
		),
		'scrub'           => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Drag to seek', 'custom-post-types-dp' ),
			'help'    => __( 'Lets visitors drag the progress line to jump around. Turns the progress line on.', 'custom-post-types-dp' ),
		),
		'time'            => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Time', 'custom-post-types-dp' ),
			'help'    => __( 'Shows 0:08 / 0:28 above the progress line.', 'custom-post-types-dp' ),
		),
		'volume'          => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Volume slider', 'custom-post-types-dp' ),
			'help'    => __( 'Note: enabling this does nothing on iPhone and iPad (iOS), where only the device buttons set the volume. It works on Android and other browsers.', 'custom-post-types-dp' ),
		),
		'speed'           => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Playback speed', 'custom-post-types-dp' ),
			'help'    => __( 'A menu from 0.5× to 2×.', 'custom-post-types-dp' ),
		),
		'fullscreen'      => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Full screen button', 'custom-post-types-dp' ),
			'help'    => __( 'Hidden automatically on browsers that cannot do it.', 'custom-post-types-dp' ),
		),
		'loop'            => array(
			'section' => 'behaviour',
			'default' => true,
			'label'   => __( 'Loop the video', 'custom-post-types-dp' ),
			'help'    => __( 'Off: the video plays twice, then stops with a Replay button (the desktop behaviour).', 'custom-post-types-dp' ),
		),
		'double_tap_skip' => array(
			'section' => 'behaviour',
			'default' => true,
			'label'   => __( 'Double-tap to skip 5 seconds', 'custom-post-types-dp' ),
			'help'    => __( 'Right half forward, left half back. Off: a tap pauses at once.', 'custom-post-types-dp' ),
		),
	);
}

/**
 * The switch keys.
 *
 * @return string[]
 */
function cptdp_videos_keys(): array {
	return array_keys( cptdp_videos_mobile_options() );
}

/**
 * Default values.
 *
 * @return array<string,bool>
 */
function cptdp_videos_defaults(): array {
	return array_map( static fn( array $option ): bool => $option['default'], cptdp_videos_mobile_options() );
}

/**
 * Get the saved settings (booleans). Dragging needs the progress line, so `scrub` implies `progress`.
 *
 * @param string|null $key Optional single key.
 * @return array<string,bool>|bool
 */
function cptdp_get_videos_settings( ?string $key = null ) {
	$saved = get_option( 'cptdp_videos', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$value = array_map( 'rest_sanitize_boolean', array_merge( cptdp_videos_defaults(), array_intersect_key( $saved, cptdp_videos_defaults() ) ) );

	$value['progress'] = $value['progress'] || $value['scrub'];

	return null === $key ? $value : ( $value[ $key ] ?? false );
}

/**
 * Register the setting.
 */
function cptdp_videos_register_settings(): void {
	register_setting(
		'cptdp_videos',
		'cptdp_videos',
		array(
			'type'              => 'object',
			'default'           => cptdp_videos_defaults(),
			'sanitize_callback' => 'cptdp_videos_sanitize',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array_fill_keys( cptdp_videos_keys(), array( 'type' => 'boolean' ) ),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'admin_init', 'cptdp_videos_register_settings' );
add_action( 'rest_api_init', 'cptdp_videos_register_settings' );

/**
 * Whether the value being saved comes from the Settings > Videos form. A browser form cannot send an
 * unticked checkbox, so there a missing key means "off"; a partial REST update only names what it changes.
 */
function cptdp_videos_is_form_post(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check of which screen posted; options.php has already verified the nonce.
	return isset( $_POST['option_page'] ) && 'cptdp_videos' === sanitize_key( wp_unslash( $_POST['option_page'] ) );
}

/**
 * Sanitise submitted values. Unlike Settings > Documents, a form with nothing ticked is valid here (every
 * switch off), so an empty submission is not replaced by the defaults.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,bool>
 */
function cptdp_videos_sanitize( $input ): array {
	$form  = cptdp_videos_is_form_post();
	$input = is_array( $input ) ? $input : array();
	$clean = $form ? array_fill_keys( cptdp_videos_keys(), false ) : cptdp_get_videos_settings();

	foreach ( cptdp_videos_keys() as $key ) {
		if ( $form || array_key_exists( $key, $input ) ) {
			$clean[ $key ] = isset( $input[ $key ] ) && rest_sanitize_boolean( $input[ $key ] );
		}
	}

	$clean['progress'] = $clean['progress'] || $clean['scrub'];

	return $clean;
}

/**
 * Register the sections and fields (wp-admin only: the settings API is not loaded on REST requests).
 */
function cptdp_videos_register_sections(): void {
	add_settings_section(
		'cptdp_videos_controls',
		esc_html__( 'Mobile video controls', 'custom-post-types-dp' ),
		static function (): void {
			echo '<p>' . esc_html__( 'The mobile viewer is a full-screen player like YouTube Shorts and Instagram Reels. Tap pauses; swiping or the up and down buttons change video. Choose which extra controls it shows. Applies to screens 700px wide or less and phones held sideways; the desktop viewer is unchanged.', 'custom-post-types-dp' ) . '</p>';
		},
		'cptdp-videos'
	);
	add_settings_section( 'cptdp_videos_behaviour', esc_html__( 'Behaviour', 'custom-post-types-dp' ), '__return_empty_string', 'cptdp-videos' );

	foreach ( cptdp_videos_mobile_options() as $key => $option ) {
		add_settings_field(
			'cptdp_videos_' . $key,
			esc_html( $option['label'] ),
			'cptdp_videos_render_field',
			'cptdp-videos',
			'cptdp_videos_' . $option['section'],
			array(
				'key'       => $key,
				'label_for' => 'cptdp_videos_' . $key,
			)
		);
	}
}
add_action( 'admin_init', 'cptdp_videos_register_sections' );

/**
 * Render one checkbox with its help text.
 *
 * @param array<string,string> $args Field args.
 */
function cptdp_videos_render_field( array $args ): void {
	$key    = $args['key'];
	$option = cptdp_videos_mobile_options()[ $key ];

	printf(
		'<label><input type="checkbox" id="%1$s" name="cptdp_videos[%2$s]" value="1" %3$s /> %4$s</label><p class="description">%5$s</p>',
		esc_attr( $args['label_for'] ),
		esc_attr( $key ),
		checked( cptdp_get_videos_settings( $key ), true, false ),
		esc_html__( 'On', 'custom-post-types-dp' ),
		esc_html( $option['help'] )
	);
}

/**
 * Add Settings > Videos.
 */
function cptdp_videos_add_menu(): void {
	add_options_page(
		esc_html__( 'Videos', 'custom-post-types-dp' ),
		esc_html__( 'Videos', 'custom-post-types-dp' ),
		'manage_options',
		'cptdp-videos',
		'cptdp_videos_render_page'
	);
}
add_action( 'admin_menu', 'cptdp_videos_add_menu' );

/**
 * Render the settings page.
 */
function cptdp_videos_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cptdp_videos' );
			do_settings_sections( 'cptdp-videos' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
```

## 6. Phases and gates

| Phase | Work | Gate |
|-------|------|------|
| 1 | `neighbours.js`, `mobile-gestures.js`, their node tests (7.1) | `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'` green |
| 2 | Plugin: `videos.php`, version bump, plugin tests (7.3) | `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit` green |
| 3 | Engine/controls split (5.3, 5.4) and the desktop `mountControls` (5.8: the `mountControls, DESKTOP_CONTROLS` import of item 1, and item 3; the `createMobileVideo` import waits for phase 5) | node tests (incl. `controls.test.mjs`) green; theme suite green; **desktop checked by hand** at `https://devportfolio.ddev.site/videos/` at 1280px: autoplay, bar, scrub, volume and speed popovers, full screen, Escape closes a popover first, Replay, mute memory |
| 4 | `details-sheet.js`, `mobile.js`, PHP helpers, `render.php`, `theme.json`, strings | `ddev exec "php -l wp-content/themes/devportfolio/functions.php"` and the same for `render.php`; theme suite green |
| 5 | `view.js` (rest of 5.8), CSS (5.9), routing test row | theme suite and `routing.test.mjs` green |
| 6 | PHP tests (7.2), AGENTS.md | `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit` green; `… blocks/shared 'node --test tests/*.test.mjs'` green; `… blocks/document-modal 'node --test tests/*.test.mjs'` green |
| 7 | Browser check (section 8) | every line ticked or reported |

An existing assertion in `tests/test-video-modal.php` may fail because the render now has a second dialog or the old comment text is gone, or because a test reads strings that moved from `player.js` to `controls/`. Fix the assertion to the new contract in the same commit and say so in the as-built; do not weaken any other assertion.

## 7. Tests (exact cases)

### 7.1 Node (`$T/blocks/video-modal/tests/`)

`neighbours.test.mjs` (`import { neighbours } from '../neighbours.js'`; `order = [ 1, 2, 3, 4 ]`; `items`: 1 `['a']`, 2 `['a','b']`, 3 `['b']`, 4 `['a']`):
- slug `''`, current 2 → `{ prev: 1, next: 3, index: 1, count: 4 }`.
- slug `''`, current 1 → `prev: null`, `next: 2`; current 4 → `next: null`, `prev: 3`.
- slug `'a'`, current 2 → `prev: 1`, `next: 4`, `count: 3` (video 3 skipped).
- slug `'a'`, current 3 (outside the filter) → the list is `[1,2,3,4]`: `index: 2`, `prev: 2`, `next: 4`, `count: 4`.
- slug `'zzz'`, current 2 → `{ prev: null, next: null, index: 0, count: 1 }`.
- an item without `categories` does not throw and does not match a slug.

`mobile-gestures.test.mjs` (`classifyStroke`, `tapAction`):
- `{ x0:100,y0:300,x1:104,y1:303,dt:120 }` → `'tap'`; `dt: 400` → `'none'`.
- up `{ x0:200,y0:500,x1:205,y1:400,dt:300 }` → `'next'`.
- down from the middle `{ x0:200,y0:300,x1:200,y1:420,dt:300 }` → `'prev'`.
- down from the top `{ x0:200,y0:20,x1:200,y1:140,dt:300 }` → `'close'`; `y0: 64` → `'close'`; `y0: 65` → `'prev'`.
- short and slow `{ x0:0,y0:300,x1:0,y1:330,dt:600 }` → `'none'`; short and fast (`dt: 50`) → `'prev'`.
- mostly sideways `{ x0:0,y0:300,x1:120,y1:400,dt:200 }` → `'none'`.
- `tapAction( { x: 300, width: 400, now: 1000, last: null } )` → `{ kind: 'toggle-later' }`.
- second tap right half `{ x: 300, width: 400, now: 1200, last: { x: 290, t: 1000 } }` → `{ kind: 'skip', seconds: 5 }`; left half `x: 100` → `seconds: -5`.
- second tap in the edge strip (`x: 4`) → `{ kind: 'none' }`; after the window (`now: 1400`, `last.t: 1000`) → `{ kind: 'toggle-later' }`.
- `skip: false` → `{ kind: 'toggle-now' }`, even with a `last` inside the window.

`details-model.test.mjs` (`import { videoDetailsModel } from '../details-sheet.js'` — it imports `../shared/dom.js` and `bottom-sheet.js`, which must not touch `document` at import time; if either does, move `videoDetailsModel` to `details-model.js` and re-export it from `details-sheet.js`): a full item gives trimmed `title`, `category`, `shape`, `description`; tools without empty strings; stats without rows that have neither label nor value; `postUrl` kept for `https://example.com/p`, dropped for `javascript:alert(1)` and `''`; defaults `Results`, `Made with`, `Open original post` when `strings` is `{}`; never throws for `null`.

`controls.test.mjs` (no DOM runner: fakes). `dialog = { querySelector: () => null, querySelectorAll: () => [], addEventListener() {} }`, `player = { on: () => () => {}, video: () => null, root: {}, toggle() {}, wake() {}, userPlay() {} }`, and a `document` stub on `globalThis` with `addEventListener() {}` and `fullscreenElement: null`:
- `mountControls( { dialog, prefix: 'x', player, keys: Object.keys( CONTROLS ) } )` returns `[]` and does not throw.
- an unknown key (`'nope'`) is ignored.
- `Object.keys( CONTROLS )` equals `[ 'bigplay', 'playpause', 'replay', 'progress', 'time', 'mute', 'volume', 'speed', 'fullscreen' ]`.
- `DESKTOP_CONTROLS` is a subset of the registry and does not contain `'mute'`.
- a `dialog` whose `querySelector` returns a stub element (`{ textContent: '' }`) only for `.x__time` mounts exactly one control for `keys: [ 'time', 'mute' ]`.
- `createPopovers( dialog )`: `toggle( item )` opens a hidden popover and sets `aria-expanded`, and opening a second item closes the first (fake `pop` `{ hidden: true, contains: () => false }`, fake `trigger` with `setAttribute`).

### 7.2 PHP (`$T/tests/test-video-modal-mobile.php`, class `Devportfolio_Test_Video_Modal_Mobile`, `@group theme`)

Copy the `set_up()` and the render helper of `tests/test-video-modal.php`. Cases:
1. `test_renders_second_dialog`: `dialog class="videos-mobile"` once and `dialog class="videos-modal"` once.
2. `test_four_overlay_controls_and_no_header`: contains `videos-mobile__close`, `__up`, `__down`, `__title`, `__details`; the part of the HTML inside `videos-mobile` does **not** contain `videos-mobile__download`, `videos-mobile__header` or `<track`.
3. `test_default_controls`: with no saved option the mobile markup contains `videos-mobile__bigplay`, `__mutebtn`, `__extras`, and `__scrub` **with** `inert`; it does **not** contain `__time`, `__volbtn`, `__ratebtn`, `__fullscreen`, `__replay`, `__playpause`.
4. `test_each_switch_renders_its_control` (data provider over `update_option( 'cptdp_videos', … )`): `time` on → `__time`; `volume` on → `__volbtn` and `id="videos-mobile-volume"`; `speed` on → `__ratebtn` and six `__rateopt`; `fullscreen` on → `__fullscreen`; `mute` off with the other extras off → no `__extras`; `loop` off → `__replay`; `scrub` on → `__scrub` without `inert`; `scrub` on with `progress` off still renders the line.
5. `test_data_mobile_options`: by default `data-mobile-options` decodes to `{ loop: true, doubleTapSkip: true, unmuteOnPlay: false, scrub: false, controls: [ 'bigplay', 'progress', 'mute' ] }`; `mute` off → `unmuteOnPlay: true` and no `mute` in `controls`; `double_tap_skip` off → `doubleTapSkip: false`.
6. `test_controls_list_matches_markup`: for each option set of case 4, every key in `data-mobile-options.controls` has its element class in the markup (`bigplay` → `__bigplay`, `replay` → `__replay`, `progress` → `__scrub`, `time` → `__time`, `mute` → `__mutebtn`, `volume` → `__volbtn`, `speed` → `__ratebtn`, `fullscreen` → `__fullscreen`), and no other of those classes is present.
7. `test_engine_and_controls_are_separate` (reads files): `controls/index.js` lists the nine keys; each `controls/<key>.js` file exists; `player.js` contains none of `scrub`, `volbtn`, `ratebtn`, `fullscreen`, `popover` and contains `prefix = 'videos-modal'`, `clickToggles`, `unmuteOnPlay`; `mobile.js` imports `./controls/index.js` and `./player.js` and no `controls/<name>.js` directly; `view.js` contains `mountControls(` with `DESKTOP_CONTROLS`.
8. `test_close_is_first_control_and_labelled`: in the mobile markup `videos-mobile__close` comes before `videos-mobile__rail` and has `aria-label="Close video"`.
9. `test_title_and_details_open_a_dialog`: `videos-mobile__title` and `videos-mobile__details` carry `aria-haspopup="dialog"`; the rail buttons carry `aria-disabled`.
10. `test_mobile_strings_present`: `data-mobile-strings` decodes to JSON with `playerFor`, `fullscreen`, `exitFullscreen`, `results`, `madeWith`, `openOriginal`, `handle`.
11. `test_rotate_hint_text`: `Rotate for full screen` inside `videos-mobile__hint` with `hidden`.
12. `test_view_script_contract` (reads the file): `view.js` contains `createMobileVideo`, `createRouting`, exactly one `'popstate'` listener registered with `true` (capture), and no `DOMContentLoaded`; `mobile.js` contains `createSheetController`, no `DOMContentLoaded`, no `localStorage`, and its only `import(` is `./details-sheet.js`.
13. `test_css_contract` (reads `style.css`): contains `min-width: 701px`, the sideways query text read from `shared/routing.js` (regex on `SIDEWAYS_QUERY`), `.videos-mobile__scrim`, `--wp--custom--modal--scrim`, `prefers-reduced-motion`, and no `--grad` inside rules that start with `.videos-mobile`.
14. `test_scrim_token`: `theme.json` decodes and `settings.custom.modal.scrim` is a non-empty string.
15. `test_sheet_shell_css_lists_videos_mobile`: `document-modal/style.css` contains `.videos-mobile__sheet` and `.videos-mobile__dim`.
16. `test_viewer_style_loads_on_video_archive`: `go_to( get_post_type_archive_link( 'video' ) )`, then the enqueue hook the existing collateral test uses; `wp_style_is( 'devportfolio-viewer', 'enqueued' )` is true.
17. `test_theme_defaults_match_plugin`: `devportfolio_video_mobile_options()` with no option equals `cptdp_get_videos_settings()` for every key of `cptdp_videos_mobile_options()` (same keys, same values).

### 7.3 Plugin (`$P/tests/settings/test-videos.php`, class `CPTDP_Test_Settings_Videos`, group `settings`; copy the shape of `test-documents.php`)

1. Defaults equal the table (nine keys, booleans: `mute`, `progress`, `loop`, `double_tap_skip` on, the rest off).
2. `cptdp_get_videos_settings()` merges a partial saved option over the defaults and drops unknown keys; `( 'mute' )` returns a bool; `scrub` saved true with `progress` saved false reads `progress` true.
3. The form sanitiser (`$_POST['option_page'] = 'cptdp_videos'`): only `progress` ticked saves every other key false; **nothing ticked saves every key false** (not the defaults); `scrub` ticked saves `progress` true.
4. A partial REST update (`{ "speed": true }`) keeps every other saved value.
5. The REST schema lists the nine keys, `additionalProperties: false`; a non-admin cannot read or write it.
6. The page: Settings → Videos exists for `manage_options`, renders one checkbox per key with the labels above and the two section titles ("Mobile video controls", "Behaviour").
7. The Volume slider's help text mentions iPhone (`assertStringContainsString( 'iPhone', … )` on the rendered page).
8. The REST schema key set equals the keys of `cptdp_videos_mobile_options()` (derived, not duplicated).

## 8. Browser check (headless Chrome or a real phone; the repo has no runner)

Seed a video per shape (`ddev wp cptdp seed videos` makes 9:16 only; set one video's `aspect_ratio` to `1x1` and one to `16x9` in wp-admin) and give two a category. At 390x844 with touch emulation:

1. Tap a tile: the viewer opens full screen, URL has `?video_slug=`, no backdrop; ✕, ▲ ▼, title, ⓘ, mute and the progress line show; no time, volume, speed or full screen. Back closes it and leaves `/videos/`.
2. Tap ⓘ: the sheet opens (entry added); Back closes only the sheet; Back again closes the viewer. Repeat with the title. Drag the handle down: it closes. "Open original post" opens a new tab.
3. ▼ opens the next video, ▲ the previous; ▲ dimmed on the first, ▼ on the last; with the "Short Form" pill pressed the order follows that filter; the URL is replaced (history length unchanged). Swipe up = next, swipe down = previous, swipe down from the very top = close.
4. Tap = pause after about 0.3s with the big play glyph; tap again = play; double tap right = +5s, left = −5s with a brief flash; no pause/resume flicker on a double tap.
5. Mute button toggles sound; the choice carries to the desktop modal in the same page session. The progress line fills and cannot be dragged. Then in Settings → Videos switch on `scrub`, `time`, `volume`, `speed`, `fullscreen` one by one and reload: each appears, popovers open to the left and stay on screen, dragging the line seeks live, full screen takes the controls along (on iPhone Safari the native player takes over). Switch `mute` off: no button, and a deeplinked (muted) video gets sound after a pause-then-tap. Switch `loop` off: it plays twice, then Replay. Switch `double_tap_skip` off: a tap acts at once and a double tap does nothing special. Untick everything and save: the viewer still works (tap, ▲ ▼, ✕, title, ⓘ).
6. Wide video: bars and the "Rotate for full screen" pill, gone after the first tap. Square: bars, no pill. Embed-only video: iframe, no gestures, ✕ ▲ ▼ title ⓘ work.
7. Rotate to 844x390: the mobile viewer stays, the video fills the height, hint hidden, extras (if several) in a row clear of the rail. Rotate with the sheet open: it stays open.
8. Resize the window across 700px with a video open: dialogs swap, same video and URL, playback restarts muted.
9. Count page reloads over all Back/Forward flows (must be 0). `prefers-reduced-motion`: no open animation, no flash animation.
10. Desktop at 1280px: the modal behaves exactly as before.

## 9. Open questions (none block)

- **Q1: defaults vs DPV-021.** DPV-021 and the lo-fi say no scrub bar, time or mute on the mobile video. The defaults add a mute button and a read-only progress line. To match the tickets to the letter, set both to off in the plugin table and in `devportfolio_video_mobile_options()`; nothing else changes.
- **Q2: second tap side.** DPV-023 gives no direction. Built: right half forward, left half back. For forward-only, change `tapAction()` and its test.
- **Q3: single-tap latency.** With double-tap skip on, a tap waits 300ms. Turn the switch off for instant taps.
- **Q4: Next up in the sheet.** Left out (D19). DPV-022 should add it together with a history-safe swap.
- **Q5: swap animation.** Documents swaps dialogs inside a View Transition; this plan does not.
- **Q6: volume on iOS.** `video.volume` is read-only on iOS Safari; the settings help says so.
- **Q7: research gaps.** Reels' progress bar and Shorts' drag-to-seek rest on weak sources (0b); defaults follow the stable parts only.
- **Q8: desktop is refactored too.** Moving the buttons out of `player.js` changes the desktop modal's code (not its markup or CSS). It is what lets both viewers share one implementation of each button. Gate: the existing desktop tests plus the manual pass in phase 3. Fallback if that is too much for this ticket: ship `controls/` with only the mobile set and migrate the desktop in a follow-up; the old `player.js` then stays for desktop and the engine is a second file.
- **Q9: iPhone full screen.** There `webkitEnterFullscreen` is used on the `<video>`, so the custom controls are not in the full-screen view.

## 10. Follow-ups (not these tickets)

Next up in the sheet (DPV-022); private clips (lo-fi E5, E6); long-press pop-out (E9); desktop arrows; a shared overlay helper if a third viewer needs the same four-control layout; one prefix-free class for the sheet shell's selectors.

## 11. Conventional commits (only when asked)

Branch `feat/DPV-011-019-020-021-023-Video-Modal-Mobile`; PR title `feat(theme): add the mobile video viewer` (≤ 72 chars). Footers `Refs: <TICKETS>` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths only (untracked plan files of other tickets sit in the working tree).

1. `feat(theme): add video neighbours and mobile gesture maths` — `blocks/video-modal/{neighbours.js,mobile-gestures.js}`, their two node tests. Refs DPV-020, DPV-023.
2. `feat(custom-post-types-dp): add the videos settings page` — `includes/settings/videos.php`, plugin header `0.31.0`. Refs DPV-011.
3. `test(custom-post-types-dp): cover the videos settings` — `tests/settings/test-videos.php`. Refs DPV-011.
4. `refactor(theme): split the video player into an engine and controls` — `player.js`, `preferences.js`, `controls/*`, `view.js` (desktop `mountControls` only), `tests/controls.test.mjs`, `tests/test-video-modal.php` if needed. Refs DPV-017, DPV-018, DPV-011.
5. `feat(theme): add the mobile video viewer markup and logic` — `render.php`, `functions.php` (5.7a), `mobile.js`, `details-sheet.js`, `tests/details-model.test.mjs`, `theme.json`. Refs DPV-011, DPV-019, DPV-020, DPV-021, DPV-023.
6. `feat(theme): open the mobile video viewer from the grid` — `view.js` (the rest of 5.8). Refs DPV-011, DPV-019, DPV-020.
7. `feat(theme): style the mobile video viewer and sheet` — `video-modal/style.css`, `document-modal/style.css`, the collateral style file if edited, `functions.php` (enqueue), `document-modal/tests/routing.test.mjs`. Refs DPV-011, DPV-021, DPV-023.
8. `test(theme): cover the mobile video viewer` — `tests/test-video-modal-mobile.php`. Refs same.
9. `docs(agents): document the mobile video viewer and settings` — `AGENTS.md`. Refs same.
10. `docs(plans): add the mobile video viewer plan` — this file.

Commits 5 and 6 each leave the suites green because the new dialog is inert until `view.js` calls it; if commit 5 trips a `test-video-modal.php` count, fix the assertion in commit 5.

## 12. As-built

Implemented on branch `feat/DPV-011-019-020-021-023-Video-Modal-Mobile` from `main`, as seven commits (see the branch history). Nothing is pushed.

### Deviations from the plan
1. **Shell selectors are prepended, not appended** (§5.9): existing Documents tests pin `…documents-mobile__grab, …sheet-head {`, and appended copies break them.
2. **`--dm-*` token scope** also lists `.videos-mobile` (not in the plan): the shared shell reads `--dm-*`.
3. **Details rules live in `video-modal/style.css`** (pills, size, caption, made, results, the original-post button) with `--vm-*` tokens: the collateral stylesheet is not loaded on the Videos archive. Title, description, tools and rows extend `document-modal/style.css`.
4. **Desktop look selectors** for the state line, Replay, popovers and the volume slider were copied to `.videos-mobile__*` by script (only where every class is in an allowed set). The speed options have their own rules, because the desktop ones are scoped to `.videos-modal__bar`.
5. **Existing assertions changed**: the gradient-users list (the mobile progress line joins it) and the sized-gradient count (3 → 4). The volume slider rules list both prefixes.
6. **`player.js` contains `fullscreen`** in the embed iframe's `allow` attribute; the §7.2 word list leaves it out.
7. **The mobile full-screen switch became the fit button** (see below). The desktop modal keeps its real full-screen button.
8. **Copy link and Open original post are in the details sheet**, in a pinned footer. §2 and D19 said there would be no Copy link in the sheet; it was added after review.

### Changes after the first build (these supersede §4 D18 and §1.10–11 where they differ)
- **Layout.** ✕ sits top left. The title (bottom left) and ⓘ (bottom right) share a centre line: both are 44 px high at `bottom: 28px`. ▲ ▼ are one 44 px-wide capsule directly above ⓘ (`bottom: 84px`). Mute, volume, speed and fit are one capsule at the top right, with hairline dividers between them. Its children span the full width and drop the desktop bar's negative popover margins. Round controls and capsule buttons are 44 px and their icons 22 px. The paused play glyph is 30 px inside a 72 px circle with a blurred background, like the other controls.
- **Mute and volume.** With the volume slider on, the mute button is not rendered, because the slider mutes at zero. The mute switch's help text in Settings → Videos says so.
- **Progress line.** The mobile line follows the design system's progress treatment: 3 px, the gradient for the played part, translucent white for the rest. The thumb shows only while the line is pressed or focused, as on desktop. It is read-only (`inert`) unless drag-to-seek is on.
- **Speed popover.** Options get their own rules, so the list is dark with white labels and a highlighted pressed option.
- **Fit button** (replaces the mobile full-screen switch; settings key `fit`, **on by default**). It cycles fit (`object-fit: contain`) → zoom (`cover`) → stretch (`fill`). The choice is kept in memory for the page session (`preferences.fit`). The video is absolutely positioned in the stage: a tall video was stretching the stage row, so a portrait video in landscape zoomed in instead of showing side bars.
- **Slide.** Changing video (drag, ▲ ▼, or keys) is a slide. The video stage follows the finger; the neighbour's poster (the "peek") comes up from the edge. Controls and overlays fade out while dragging or sliding (`data-sliding` on the dialog) and fade back in once it settles; only the stage moves. The slide completes when the finger passes a third of the screen in the same direction, or on a fast flick (at least 24 px and 0.4 px/ms); otherwise it springs back. A drag with no neighbour moves at 30%. A drag down from the top 64 px still closes. Reduced motion: no animation.
- **Peek.** It is framed with the current fit mode. The neighbours' posters are preloaded when a video is shown. It leaves when the incoming video reports its first time update, or after 600 ms, so there is no gap.
- **Loading.** The mobile player passes `quietLoading`: no "Loading video…" text and no paused glyph while the file loads (state `loading`). The desktop modal is unchanged.
- **Details sheet footer.** Open original post (filled, shown when the video has a post URL) sits above Copy link (outlined). Both are 10 px rounded and 48 px high, like the Brand Collaterals sheet, whose category pill is also filled. Copy link uses `shared/copy-link.js`, with a temporary textarea inside the dialog.
- **Sideways.** The top-right capsule runs as a row, and its popovers open above their buttons.

### Tests (green on the consolidated branch)
- Node, `blocks/video-modal`: 44 (neighbours 6, mobile-gestures 15, player-model 10, controls 6, details-model 7).
- Plugin `vendor/bin/phpunit`: 918 tests, 12 skipped (the videos settings: 12).
- Theme `vendor/bin/phpunit`: 599 tests, of which 96 are the video viewer's.

### Browser check (headless Chrome, puppeteer-core in the scratchpad, not in the repo)
- 390×844, touch: open, pause and resume, the ⓘ sheet and Back, ▼ replacing the URL, ✕, and Back closing the viewer. No page reload (a window marker survives).
- Drag: the video tracks the finger; a release past a third commits; a slow short drag springs back; the rail slides; the controls fade and return; the mute column stays in place.
- 844×390 with a portrait video: side bars; the fit button cycles fit → zoom → stretch → fit.
- Screenshots were saved to the Desktop for review; they are not part of the repository.

### Not covered
- Real devices: iPhone Safari (`volume` is read-only there) and Android.
- Double-tap skip, the drag-to-dismiss sheet, embeds (iframes ignore `object-fit`, so the fit button does nothing for them), reduced motion, and every Settings → Videos switch toggled in wp-admin.
- The peek is the poster, not the first frame: if the poster differs from the first frame, the swap can still be visible for a moment.
- The desktop volume and speed popovers were not checked by hand (phase 3 gate).

### Open questions (none block)
- Q1: defaults. Mute, progress and fit are on; the others are off. Q2 and Q3 are as before.
