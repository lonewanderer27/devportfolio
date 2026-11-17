# Plan: Video modal player, control bar and seeking (DPV-017, DPV-018, DPV-029)

Tickets **DPV-017** (body: viewer, autoplay, centre play button, replay, embed), **DPV-018** (footer: control bar, scrub, time, mute, idle fade) and **DPV-029** (seeking on the scrub bar; a duplicate of DPV-018's seeking, kept here as its QA check, see section 1 item 6b and the browser check). Branch `feat/DPV-017-018-029-Video-Player-Controls`, created **from `main`**.

**Written for a small model (Haiku 5.5 or similar):** no design decisions are left. New files are given in full; every edit is a find/replace pair with the anchor; every gate is a command. Stop and report if a "Fact" is false.

`$T` = `wp-content/themes/devportfolio`.

---

## 0. Conflicts found while planning (read first)

1. **DPV-018 is marked "Done" in the story, but the code has no control bar.** `view.js` `mountPlayer()` (line ~84) sets `video.controls = true`, i.e. the browser's native controls. This plan builds the custom bar and removes `controls`. If "Done" meant something else, stop and ask.
2. **DPV-029** (Seeking, Low, "In Progress") is a duplicate of DPV-018's seeking. The user asked for it to be included, so this plan covers it as a QA check (section 1 item 6b, browser check in section 5.9). The tracker still shows it open: closing it as merged into DPV-018 is a tracker change, not done here.
3. **Autoplay changes DPV-009.** `.claude/plans/videos/DPV-009-…-Video-Modal-Desktop.md` says "No autoplay; the visitor presses play". DPV-017 reverses that for a click.
4. **Branding source.** The theme's `theme.json` palette is greyscale (lo-fi), but the design system (`.claude/plans/design-system.html`, DPD-034) adds one accent: the **gradient** `--grad` (`rgb(26 214 253)` to `rgb(29 98 240)`, 135deg). Its rule: the gradient is "the only colour in the system… progress and video frames… never sits behind text and never fills a button". This plan follows that rule (section 4, D12 to D15). The gradient is added to `theme.json` as a modal token (section 5.6).

## 1. Goal and acceptance criteria

1. **Clicking a video tile starts it automatically** from 0:00 with sound on. Clicking a Next up thumbnail does the same (it is a click).
2. If the browser blocks sound, the video starts **muted** and the mute button shows "Unmute".
3. **Deeplink or Back/Forward open** (no click) start muted, not with sound (DPV-024: no gesture, so no unmuted autoplay).
4. Clicking the video, or pressing Space while focus is not on a control, toggles play/pause. A large centre play button shows while paused.
5. The control bar (DPV-018) on a dark gradient scrim at the bottom: play/pause button, scrub bar (`input[type=range]`), time `0:08 / 0:28`, mute button, volume slider, playback-rate select, fullscreen button. Order left to right: play/pause, scrub, time, mute, volume, rate, fullscreen.
5b. **Parity with the built-in player (option 2):** the uploaded-file bar keeps what the browser's native controls gave: fullscreen, playback speed (0.5× to 2×) and a volume slider. Embeds keep the provider's own controls. Volume and speed are remembered for the page like mute (D20). The fullscreen button is hidden where the browser cannot do fullscreen for the video.
6. The scrub bar seeks on click, drag and Left/Right arrows (±5 s). Its `aria-valuetext` is `0:08 of 0:28`.
6b. **DPV-029 seeking (QA):** clicking the middle of the scrub bar jumps to about half way (the range's native click sets `value` to about `duration / 2`, the `input` handler sets `video.currentTime`); dragging seeks continuously; the readout and `aria-valuetext` follow the new time at once. A click before metadata loads does nothing (the range max is still 0).
7. Mute choice is kept **in memory for the page** (module variable, not `localStorage`). Only the visitor's button press sets it; an automatic fallback to muted does not.
8. While playing, the bar fades after 3 s without pointer movement or focus inside the player; it returns on pointer move or focus.
9. At the end the video **replays once**. The second end stops on the last frame and shows a **Replay** button. Each user-started play is a new session (counter reset).
10. **No captions**: no `<track>`, no CC pill, no captions toggle in markup or JS.
11. **Embed-only** videos (`embedSrc`, no `fileUrl`): an iframe, no control bar, no centre button. Clicking a tile adds `autoplay=1` to the embed URL; a deeplink load does not. The provider's player shows its own controls.
12. Loading and "can't be previewed" text come from `shared/status.js` `createStatus()`.
13. Closing unloads the video (existing `unload()`); the mute preference survives.
14. **Branding (section 5.7):** the player uses the theme's type (Hanken Grotesk through `--wp--preset--font-family--sans`), the modal's ink and surface tokens, the design system's radii and motion, and the gradient only for the scrub progress and the frame of a playing video. No gradient behind text or as a button fill. Focus rings use the system focus colour.

## 2. Out of scope (do NOT build)

- Captions, `<track>`, `.vtt` handling, captions upload (none exists in wp-admin).
- Keyboard shortcuts other than Space and the arrows on the scrub bar (no M, F or number keys).
- Captions/subtitles, picture-in-picture, quality selection.
- Mobile viewer (DPV-011). Hover preview (`preview.js`) untouched.
- Dark-mode switching of the player: the video pane is always dark (`#111` in `style.css`), so the player uses fixed light-on-dark colours.
- Any plugin change. **No plugin version bump.**
- Persisting mute in `localStorage`.
- Changing the modal's existing sidebar styles.

## 3. Facts (verified; stop if false)

- `$T/blocks/video-modal/view.js`: `fill( item )` at ~line 153; `mountPlayer( item )` called at ~line 196 inside `fill`; `show( id, { push, trigger } )` calls `fill( item )` at ~line 240; `swapTo( id )` calls `fill( item )` at ~line 252; `unload()` at ~line 73 with `video.removeAttribute('src')`; `els.nopreview` at ~line 46 and ~line 86.
- `$T/blocks/video-modal/render.php`: `<div class="videos-modal__stage"></div>` then `<p class="videos-modal__nopreview" hidden>…</p>` inside `.videos-modal__video`.
- `$T/blocks/video-modal/style.css` maps the modal tokens to `--vm-*` custom properties (for example `var( --vm-ink )`, `var( --vm-on-primary )`); the pills and copy button use them. Confirm the names with `grep -n "\-\-vm-" $T/blocks/video-modal/style.css` before step 5.7. The video pane background is `#111` (around line 360).
- `$T/theme.json` `settings.custom.modal` (lines 13 to 21) holds `ink`, `subtle`, `line`, `line-strong`, `surface`, `surface-alt`, `on-primary`. Line 20 is `"on-primary": "#fff"`.
- `$T/blocks/shared/status.js` exports `createStatus( { stage, status, prefix } )` and reads `status.dataset.loading`, `.unavailable`, `.hint` (and `.pending`, optional) and the children `.${prefix}__state-text`, `.${prefix}__state-hint`. Spinner and image are optional (null-safe). It sets `aria-busy` on `stage`.
- `$T/functions.php` `devportfolio_video_modal_strings()` (~line 1566) returns `open`, `copied`, `copyHint`, `playerFor`.
- `$T/tests/test-video-modal.php` does not reference `nopreview`, `controls` or `track` (grep confirmed). It will need new assertions (section 5.9).
- `$T/blocks/video-modal/` has no `tests/` directory yet.
- Design system tokens (`.claude/plans/design-system.html`, light mode): `--g1-rgb 26 214 253`, `--g2-rgb 29 98 240`, `--grad`, `--focus: rgb(var(--g2-rgb))`, `--r-pill: 999px`, `--ease: cubic-bezier(0.2, 0.8, 0.2, 1)`, `--dur-fast: 160ms`, `--dur-base: 260ms`. The theme does not define these yet (the theme's `theme.json` has no gradient or focus token); section 5.6 adds the two it needs.
- Node: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'`.

## 4. Decisions

| # | Decision | Why |
|---|----------|-----|
| D1 | Pure maths in `blocks/video-modal/player-model.js` (time text, seek by key, replay decision); DOM wiring in `blocks/video-modal/player.js`. | Story asks for a pure module with a node test. |
| D2 | `fill( item, gesture )`. `gesture` is `true` from `show()` when `push` is true (a click) and from `swapTo()` (a Next click). Deeplink first load and popstate pass `push: false` → `gesture` false. | Click = user activation, so unmuted autoplay is allowed; a deeplink load is not. |
| D3 | Autoplay: `video.muted = preferMuted \|\| ! gesture`, then `play()`. On rejection: set `muted = true`, `play()` again. If that also rejects, state `paused` (big play button shows). | Story: falls back to muted. |
| D4 | `preferMuted` is set only by the mute button. | Story: the choice carries over, the automatic fallback does not. |
| D5 | Replay: `afterEnd( autoReplays )` returns `replay` when `autoReplays < 1`, else `stop`. Every user-started play (click, Space, big play, Replay button) sets `autoReplays = 0`. | "Replays once, then stops" per session. |
| D6 | State lives in `data-state` on `.videos-modal__video`: `paused`, `playing`, `ended`, `embed`. CSS shows/hides parts from it. | No JS show/hide per element. |
| D7 | Space toggles when `event.target` is not an interactive element (`button, input, a, textarea, select`) inside the dialog. Listener on the dialog, added once. | Space must not click buttons or type into the copy field. |
| D8 | Scrub: `input[type=range]`, `step="any"`, `min=0`, `max=duration`. Arrow keys handled in JS (±5 s, `preventDefault`), no-op when duration unknown. Dragging suppresses the `timeupdate` write to the slider. The progress fill is a CSS variable `--p` (percent) written by `syncTime()`. | Native arrows step by `step`, not seconds; the fill needs the position. |
| D9 | Idle fade: class `is-idle` on the player after 3000 ms of playing with no `pointermove`/`focusin`/`keydown` inside the player. Removed on pause, end and any wake. | Story: fade after 3 s. |
| D10 | Status: `createStatus` with `prefix: 'videos-modal'`; markup `p.videos-modal__state[role=status]` replaces `p.videos-modal__nopreview`. Its `data-loading`, `data-unavailable`, `data-hint` come from `render.php` as static text. | Story asks for `createStatus`. |
| D11 | Embed autoplay: `embedSrc` plus `autoplay=1` (`&` if the URL already has `?`) only when `gesture` is true. | Story: plays in the provider's player; a click starts it. |
| D12 | **Gradient only for progress and the playing frame.** The scrub fill is `--vm-grad`; a 2px gradient frame (`::after` with a mask) shows around the player while `data-state="playing"`. | Design system: "progress and video frames". |
| D13 | **No gradient behind text or on a button.** The bar scrim is ink (black at 70% to 0%), the buttons are white glyphs, the Replay button is a white outline pill. | Design system rule. |
| D14 | Type: `font-family: var( --wp--preset--font-family--sans, inherit )` on the player; time and status 12px/14px, weight 600 for the Replay label, tabular numerals for the time. | Hanken Grotesk everywhere (theme rule). |
| D15 | Focus: `:focus-visible` outline `2px solid var( --wp--custom--modal--focus )` (the design system's blue, `rgb(29 98 240)`), offset 2px. Hover and press states on buttons use the existing 160ms `--ease`. | Consistent with the system's focus colour and motion tokens. |
| D17 | **Fullscreen:** the button calls `playerEl.requestFullscreen()` (the player wrapper, so the bar goes with the video) and `document.exitFullscreen()` when already fullscreen. Where `requestFullscreen` is missing but `video.webkitEnterFullscreen` exists (iPhone Safari), that is called on the `<video>` instead. Where neither exists, the button stays `hidden`. The icon and `aria-label` switch on `fullscreenchange`. | Built-in parity; iPhone needs the video-element call. |
| D18 | **Playback rate:** a native `<select class="videos-modal__rate">` with options `0.5`, `0.75`, `1`, `1.25`, `1.5`, `2` (labels `0.5×` … `2×`), values from `PLAYBACK_RATES` in `player-model.js`. `change` sets `video.playbackRate`. A native select is keyboard and screen-reader accessible without extra code. | Built-in parity without a custom menu. |
| D19 | **Volume:** `input[type=range]` `min=0 max=1 step=0.05`, value `video.volume`. `input` sets `video.volume` through `clampVolume()` and, when above 0, unmutes (`video.muted = false`). Setting it to 0 mutes. The mute button and the slider stay in step through `volumechange`. | Built-in parity; one volume state. |
| D20 | **Carry-over:** `preferMuted`, `preferVolume` and `preferRate` are module variables in `player.js` (memory only, reset on reload). Every new `<video>` is given them in `mountFile()`. The automatic fallback to muted (D3) does not change them. | Same rule as mute (D4) for the other two settings. |
| D16 | Radii: buttons and the Replay pill use `999px`; the big play button is a circle (`50%`), matching the design system's round controls. Shadows: none on the player (the pane is dark already). | Design system radii; no shadow tokens apply on a dark pane. |

## 5. Files

New:
- `$T/blocks/video-modal/player-model.js`
- `$T/blocks/video-modal/player.js`
- `$T/blocks/video-modal/tests/player-model.test.mjs`

Edited:
- `$T/blocks/video-modal/render.php`
- `$T/blocks/video-modal/view.js`
- `$T/blocks/video-modal/style.css`
- `$T/theme.json` (two modal tokens, section 5.6)
- Volume slider, rate select and fullscreen button styles are in the 5.7 block (appended in the same `style.css`).
- `$T/functions.php` (strings)
- `$T/tests/test-video-modal.php` (new cases)
- `$T/AGENTS.md` (Theme architecture: one bullet for the player)

### 5.1 New file `player-model.js` (full)

```js
/**
 * Time text, seek maths and the replay rule for the video player (DPV-017, DPV-018). Pure: no DOM.
 * `formatTime()` matches devportfolio_video_length_label(): m:ss, h:mm:ss from one hour.
 */
export const SEEK_STEP = 5;
export const MAX_AUTO_REPLAYS = 1;
export const PLAYBACK_RATES = [ 0.5, 0.75, 1, 1.25, 1.5, 2 ];

/** A volume from 0 to 1. A missing or non-finite value is full volume. */
export function clampVolume( value ) {
	if ( ! Number.isFinite( value ) ) {
		return 1;
	}
	return Math.min( Math.max( value, 0 ), 1 );
}

/** m:ss, or h:mm:ss from one hour. A missing, negative or non-finite value is 0:00. */
export function formatTime( seconds ) {
	const total = Number.isFinite( seconds ) && seconds > 0 ? Math.floor( seconds ) : 0;
	const hours = Math.floor( total / 3600 );
	const minutes = Math.floor( ( total % 3600 ) / 60 );
	const secs = String( total % 60 ).padStart( 2, '0' );
	if ( hours > 0 ) {
		return `${ hours }:${ String( minutes ).padStart( 2, '0' ) }:${ secs }`;
	}
	return `${ minutes }:${ secs }`;
}

/** "0:08 / 0:28" for the time readout. */
export function timeLabel( time, duration ) {
	return `${ formatTime( time ) } / ${ formatTime( duration ) }`;
}

/** "0:08 of 0:28" for the slider's aria-valuetext. */
export function ariaValueText( time, duration ) {
	return `${ formatTime( time ) } of ${ formatTime( duration ) }`;
}

/** Keeps a time inside [0, duration]. An unknown duration gives 0. */
export function clampTime( time, duration ) {
	if ( ! Number.isFinite( duration ) || duration <= 0 || ! Number.isFinite( time ) ) {
		return 0;
	}
	return Math.min( Math.max( time, 0 ), duration );
}

/** Progress as a percentage 0 to 100 for the CSS `--p` variable. */
export function progressPercent( time, duration ) {
	if ( ! Number.isFinite( duration ) || duration <= 0 ) {
		return 0;
	}
	return ( clampTime( time, duration ) / duration ) * 100;
}

/**
 * The new time for an arrow key on the scrub bar, or null when the key is not an arrow or the
 * duration is not known yet (so the default key handling is left alone).
 */
export function seekForKey( key, current, duration ) {
	if ( ! Number.isFinite( duration ) || duration <= 0 ) {
		return null;
	}
	if ( key === 'ArrowLeft' ) {
		return clampTime( current - SEEK_STEP, duration );
	}
	if ( key === 'ArrowRight' ) {
		return clampTime( current + SEEK_STEP, duration );
	}
	return null;
}

/** 'replay' while the automatic replays of this session are used up to MAX_AUTO_REPLAYS, else 'stop'. */
export function afterEnd( autoReplays ) {
	return autoReplays < MAX_AUTO_REPLAYS ? 'replay' : 'stop';
}
```

### 5.2 New file `tests/player-model.test.mjs` (full)

```js
/**
 * Run: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatTime, timeLabel, ariaValueText, clampTime, clampVolume, progressPercent, seekForKey, afterEnd, PLAYBACK_RATES } from '../player-model.js';

test( 'formatTime gives m:ss and h:mm:ss', () => {
	assert.equal( formatTime( 0 ), '0:00' );
	assert.equal( formatTime( 8 ), '0:08' );
	assert.equal( formatTime( 28 ), '0:28' );
	assert.equal( formatTime( 59.9 ), '0:59' );
	assert.equal( formatTime( 65 ), '1:05' );
	assert.equal( formatTime( 3600 ), '1:00:00' );
	assert.equal( formatTime( 3661 ), '1:01:01' );
} );

test( 'formatTime treats missing, negative and non-finite values as 0:00', () => {
	assert.equal( formatTime( NaN ), '0:00' );
	assert.equal( formatTime( Infinity ), '0:00' );
	assert.equal( formatTime( -3 ), '0:00' );
	assert.equal( formatTime( undefined ), '0:00' );
} );

test( 'timeLabel and ariaValueText use the story wording', () => {
	assert.equal( timeLabel( 8, 28 ), '0:08 / 0:28' );
	assert.equal( ariaValueText( 8, 28 ), '0:08 of 0:28' );
	assert.equal( timeLabel( 0, NaN ), '0:00 / 0:00' );
} );

test( 'clampTime keeps the time inside the duration', () => {
	assert.equal( clampTime( -1, 28 ), 0 );
	assert.equal( clampTime( 30, 28 ), 28 );
	assert.equal( clampTime( 10, 28 ), 10 );
	assert.equal( clampTime( 10, NaN ), 0 );
	assert.equal( clampTime( 10, 0 ), 0 );
} );

test( 'progressPercent gives the fill position', () => {
	assert.equal( progressPercent( 14, 28 ), 50 );
	assert.equal( progressPercent( 0, 28 ), 0 );
	assert.equal( progressPercent( 40, 28 ), 100 );
	assert.equal( progressPercent( 5, NaN ), 0 );
} );

test( 'seekForKey moves five seconds and clamps at the ends', () => {
	assert.equal( seekForKey( 'ArrowRight', 10, 28 ), 15 );
	assert.equal( seekForKey( 'ArrowLeft', 10, 28 ), 5 );
	assert.equal( seekForKey( 'ArrowLeft', 3, 28 ), 0 );
	assert.equal( seekForKey( 'ArrowRight', 25, 28 ), 28 );
} );

test( 'seekForKey ignores other keys and an unknown duration', () => {
	assert.equal( seekForKey( 'a', 10, 28 ), null );
	assert.equal( seekForKey( 'ArrowRight', 10, NaN ), null );
	assert.equal( seekForKey( 'ArrowRight', 10, 0 ), null );
} );

test( 'clampVolume keeps 0 to 1 and treats missing values as full', () => {
	assert.equal( clampVolume( -0.2 ), 0 );
	assert.equal( clampVolume( 1.4 ), 1 );
	assert.equal( clampVolume( 0.35 ), 0.35 );
	assert.equal( clampVolume( NaN ), 1 );
} );

test( 'PLAYBACK_RATES has the built-in player steps', () => {
	assert.deepEqual( PLAYBACK_RATES, [ 0.5, 0.75, 1, 1.25, 1.5, 2 ] );
} );

test( 'afterEnd replays once, then stops', () => {
	assert.equal( afterEnd( 0 ), 'replay' );
	assert.equal( afterEnd( 1 ), 'stop' );
	assert.equal( afterEnd( 2 ), 'stop' );
} );
```

### 5.3 New file `player.js` (full)

```js
/**
 * The video player inside the desktop video modal (DPV-017 body, DPV-018 control bar, DPV-029 seeking).
 *
 * `createPlayer( dialog, strings )` returns { mount( item, gesture ), unload() }. `mount` builds the
 * uploaded-file player (native <video> without controls, custom bar) or the embed iframe; `gesture` is
 * true when a click opened or moved to the video (sound allowed), false for a deeplink or Back/Forward.
 * The mute choice lives in `preferMuted` for the page only.
 */
import { createStatus } from '../shared/status.js';
import { timeLabel, ariaValueText, progressPercent, clampVolume, seekForKey, afterEnd, PLAYBACK_RATES } from './player-model.js';

const IDLE_MS = 3000;
const INTERACTIVE = 'button, input, a, textarea, select';

let preferMuted = false;
let preferVolume = 1;
let preferRate = 1;

export function createPlayer( dialog, strings = {} ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const stage = $( '.videos-modal__stage' );
	const playerEl = $( '.videos-modal__video' );
	const statusEl = $( '.videos-modal__state' );
	const status = createStatus( { stage, status: statusEl, prefix: 'videos-modal' } );
	const playPause = $( '.videos-modal__playpause' );
	const bigPlay = $( '.videos-modal__bigplay' );
	const replay = $( '.videos-modal__replay' );
	const scrub = $( '.videos-modal__scrub' );
	const time = $( '.videos-modal__time' );
	const mute = $( '.videos-modal__mute' );
	const volume = $( '.videos-modal__volume' );
	const rate = $( '.videos-modal__rate' );
	const fullscreen = $( '.videos-modal__fullscreen' );

	const fullscreenTarget = () => {
		// The wrapper goes fullscreen so the control bar goes with the video.
		if ( playerEl.requestFullscreen ) {
			return playerEl;
		}
		return null;
	};

	const syncFullscreen = () => {
		const on = Boolean( document.fullscreenElement );
		fullscreen.setAttribute( 'aria-label', on ? strings.exitFullscreen || 'Exit full screen' : strings.fullscreen || 'Full screen' );
	};

	const syncVolume = () => {
		if ( ! video ) {
			return;
		}
		volume.value = String( video.muted ? 0 : video.volume );
	};

	let video = null;
	let autoReplays = 0;
	let idleTimer = 0;
	let dragging = false;

	const setState = ( state ) => {
		playerEl.dataset.state = state;
	};

	const wake = () => {
		playerEl.classList.remove( 'is-idle' );
		window.clearTimeout( idleTimer );
		if ( video && ! video.paused && ! video.ended ) {
			idleTimer = window.setTimeout( () => playerEl.classList.add( 'is-idle' ), IDLE_MS );
		}
	};

	const syncMute = () => {
		const label = video && video.muted ? strings.unmute || 'Unmute' : strings.mute || 'Mute';
		mute.setAttribute( 'aria-label', label );
		mute.setAttribute( 'title', label );
		mute.setAttribute( 'aria-pressed', video && video.muted ? 'true' : 'false' );
	};

	const syncTime = () => {
		if ( ! video ) {
			return;
		}
		const duration = Number.isFinite( video.duration ) ? video.duration : 0;
		time.textContent = timeLabel( video.currentTime, duration );
		scrub.setAttribute( 'aria-valuetext', ariaValueText( video.currentTime, duration ) );
		scrub.style.setProperty( '--p', `${ progressPercent( video.currentTime, duration ) }%` );
		if ( ! dragging ) {
			scrub.value = String( video.currentTime );
		}
	};

	/** A user-started play is a new session: the automatic replay counter starts again. */
	const userPlay = () => {
		autoReplays = 0;
		video.play().catch( () => setState( 'paused' ) );
	};

	const toggle = () => {
		if ( ! video ) {
			return;
		}
		if ( video.paused ) {
			userPlay();
		} else {
			video.pause();
		}
	};

	const start = ( gesture ) => {
		video.muted = preferMuted || ! gesture;
		video.play().catch( () => {
			// Sound was blocked: start muted. This is not a choice, so preferMuted stays as it was.
			video.muted = true;
			video.play().catch( () => setState( 'paused' ) );
		} );
	};

	const unload = () => {
		window.clearTimeout( idleTimer );
		playerEl.classList.remove( 'is-idle' );
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
		video.setAttribute( 'aria-label', ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title ) );
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
		video.addEventListener( 'loadedmetadata', () => {
			const duration = Number.isFinite( video.duration ) ? video.duration : 0;
			scrub.max = String( duration );
			syncTime();
		} );
		video.addEventListener( 'timeupdate', syncTime );
		video.addEventListener( 'play', () => {
			setState( 'playing' );
			playPause.setAttribute( 'aria-label', strings.pause || 'Pause' );
			wake();
		} );
		video.addEventListener( 'pause', () => {
			if ( video && ! video.ended ) {
				setState( 'paused' );
			}
			playPause.setAttribute( 'aria-label', strings.play || 'Play' );
			window.clearTimeout( idleTimer );
			playerEl.classList.remove( 'is-idle' );
		} );
		video.addEventListener( 'volumechange', syncMute );
		video.addEventListener( 'ended', () => {
			if ( afterEnd( autoReplays ) === 'replay' ) {
				autoReplays += 1;
				video.currentTime = 0;
				video.play().catch( () => setState( 'ended' ) );
			} else {
				setState( 'ended' );
			}
		} );

		setState( 'paused' );
		video.volume = preferVolume;
		video.playbackRate = preferRate;
		rate.value = String( preferRate );
		fullscreen.hidden = ! ( playerEl.requestFullscreen || video.webkitEnterFullscreen );
		syncMute();
		syncVolume();
		syncTime();
		start( gesture );
	};

	const mountEmbed = ( item, gesture ) => {
		setState( 'embed' );
		const frame = document.createElement( 'iframe' );
		frame.title = ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title );
		frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
		frame.allowFullscreen = true;
		frame.referrerPolicy = 'strict-origin-when-cross-origin';
		frame.src = gesture ? `${ item.embedSrc }${ item.embedSrc.includes( '?' ) ? '&' : '?' }autoplay=1` : item.embedSrc;
		stage.append( frame );
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

	// Wiring that does not depend on a mounted video is added once.
	playPause.addEventListener( 'click', () => { wake(); toggle(); } );
	bigPlay.addEventListener( 'click', () => { wake(); toggle(); } );
	replay.addEventListener( 'click', () => {
		if ( video ) {
			video.currentTime = 0;
			userPlay();
		}
	} );
	mute.addEventListener( 'click', () => {
		if ( video ) {
			preferMuted = ! video.muted;
			video.muted = preferMuted;
		}
	} );
	playerEl.addEventListener( 'click', ( event ) => {
		// The video itself toggles; the bar and the centre button handle their own clicks.
		if ( video && event.target === video ) {
			toggle();
		}
	} );
	scrub.addEventListener( 'pointerdown', () => { dragging = true; } );
	window.addEventListener( 'pointerup', () => {
		if ( dragging ) {
			dragging = false;
			syncTime();
		}
	} );
	scrub.addEventListener( 'input', () => {
		if ( video ) {
			video.currentTime = Number( scrub.value );
			syncTime();
		}
	} );
	scrub.addEventListener( 'keydown', ( event ) => {
		if ( ! video ) {
			return;
		}
		const next = seekForKey( event.key, video.currentTime, video.duration );
		if ( next !== null ) {
			event.preventDefault();
			video.currentTime = next;
			syncTime();
		}
	} );
	playerEl.addEventListener( 'pointermove', wake );
	playerEl.addEventListener( 'focusin', wake );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( ! video ) {
			return;
		}
		if ( event.key === ' ' && ! event.target.closest( INTERACTIVE ) ) {
			event.preventDefault();
			wake();
			toggle();
		}
	} );

	volume.addEventListener( 'input', () => {
		if ( ! video ) {
			return;
		}
		preferVolume = clampVolume( Number( volume.value ) );
		video.volume = preferVolume;
		// Moving the slider off zero unmutes; dropping it to zero mutes (D19).
		preferMuted = preferVolume === 0;
		video.muted = preferMuted;
	} );
	rate.addEventListener( 'change', () => {
		preferRate = PLAYBACK_RATES.includes( Number( rate.value ) ) ? Number( rate.value ) : 1;
		if ( video ) {
			video.playbackRate = preferRate;
		}
	} );
	fullscreen.addEventListener( 'click', () => {
		if ( document.fullscreenElement ) {
			document.exitFullscreen();
			return;
		}
		const target = fullscreenTarget();
		if ( target ) {
			target.requestFullscreen().catch( () => {} );
		} else if ( video && video.webkitEnterFullscreen ) {
			video.webkitEnterFullscreen();
		}
	} );
	document.addEventListener( 'fullscreenchange', syncFullscreen );
	syncFullscreen();

	return { mount, unload };
}
```

### 5.4 `render.php` edits

Replace the line
`				<p class="videos-modal__nopreview" hidden><?php echo esc_html_x( 'This video can’t be previewed here.', 'video modal', 'devportfolio' ); ?></p>`
with:

```php
				<p class="videos-modal__state" role="status" hidden data-loading="<?php echo esc_attr_x( 'Loading video…', 'video modal', 'devportfolio' ); ?>" data-unavailable="<?php echo esc_attr_x( 'This video can’t be previewed here.', 'video modal', 'devportfolio' ); ?>" data-hint="<?php echo esc_attr_x( 'Use the original post.', 'video modal', 'devportfolio' ); ?>"><span class="videos-modal__state-text"></span><span class="videos-modal__state-hint"></span></p>
				<button class="videos-modal__bigplay" type="button" aria-label="<?php echo esc_attr_x( 'Play', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg></button>
				<button class="videos-modal__replay" type="button"><?php echo esc_html_x( 'Replay', 'video modal', 'devportfolio' ); ?></button>
				<div class="videos-modal__bar">
					<button class="videos-modal__playpause" type="button" aria-label="<?php echo esc_attr_x( 'Play', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg></button>
					<input class="videos-modal__scrub" type="range" min="0" max="0" step="any" value="0" aria-label="<?php echo esc_attr_x( 'Seek', 'video modal', 'devportfolio' ); ?>">
					<span class="videos-modal__time" aria-hidden="true">0:00 / 0:00</span>
					<button class="videos-modal__mute" type="button" aria-label="<?php echo esc_attr_x( 'Mute', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 9v6h4l5 4V5L7 9z"/></svg></button>
					<input class="videos-modal__volume" type="range" min="0" max="1" step="0.05" value="1" aria-label="<?php echo esc_attr_x( 'Volume', 'video modal', 'devportfolio' ); ?>">
					<select class="videos-modal__rate" aria-label="<?php echo esc_attr_x( 'Playback speed', 'video modal', 'devportfolio' ); ?>">
						<option value="0.5">0.5×</option>
						<option value="0.75">0.75×</option>
						<option value="1" selected>1×</option>
						<option value="1.25">1.25×</option>
						<option value="1.5">1.5×</option>
						<option value="2">2×</option>
					</select>
					<button class="videos-modal__fullscreen" type="button" aria-label="<?php echo esc_attr_x( 'Full screen', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>
				</div>
```

Also change `<div class="videos-modal__video">` to `<div class="videos-modal__video" data-state="paused">`.

The time readout is `aria-hidden` on purpose: the slider's `aria-valuetext` already speaks the time.

Volume and playback rate: the `volumechange` listener already calls `syncMute`; also call `syncVolume` there (replace `video.addEventListener( 'volumechange', syncMute );` with `video.addEventListener( 'volumechange', () => { syncMute(); syncVolume(); } );`). The mute button's handler sets `preferMuted` as before.

### 5.5 `functions.php` strings

In `devportfolio_video_modal_strings()`, after the `'playerFor'` line, add (keep `playerFor`, add commas):

```php
			'play'      => _x( 'Play', 'video modal', 'devportfolio' ),
			'pause'     => _x( 'Pause', 'video modal', 'devportfolio' ),
			'mute'      => _x( 'Mute', 'video modal', 'devportfolio' ),
			'unmute'    => _x( 'Unmute', 'video modal', 'devportfolio' ),
			'fullscreen'     => _x( 'Full screen', 'video modal', 'devportfolio' ),
			'exitFullscreen' => _x( 'Exit full screen', 'video modal', 'devportfolio' ),
```

### 5.6 `theme.json` tokens

In `settings.custom.modal` (after `"on-primary": "#fff"`), add two keys. The line becomes:

```json
				"on-primary": "#fff",
				"grad": "linear-gradient(135deg, rgb(26 214 253) 0%, rgb(29 98 240) 100%)",
				"focus": "rgb(29 98 240)"
```

This creates `--wp--custom--modal--grad` and `--wp--custom--modal--focus`. Only the player uses them; the design system says the gradient is never behind text, so no other rule may use `grad`.

In `$T/blocks/video-modal/style.css`, map them where the other `--vm-*` variables are defined (grep first; the map sits in the block's root rule). Add:

```css
	--vm-grad: var( --wp--custom--modal--grad, linear-gradient(135deg, rgb(26 214 253), rgb(29 98 240)) );
	--vm-focus: var( --wp--custom--modal--focus, rgb(29 98 240) );
```

### 5.7 `style.css` (append at the end of the file)

Replaces the earlier draft: this is the only player CSS. It uses the `--vm-*` tokens (from `--wp--custom--modal--*`), Hanken Grotesk, the design system's radii (999px pill, 50% circle), its motion (`cubic-bezier(0.2, 0.8, 0.2, 1)`, 160ms and 260ms) and the gradient only for the scrub fill and the playing frame.

```css
/* DPV-017 / DPV-018 / DPV-029: player, control bar, seeking. Colours are the modal tokens; the
   gradient is used only for the scrub fill and the playing frame (design system: never behind text,
   never a button fill). The pane is always dark, so the player has no dark-mode switch. */

.videos-modal__video {
	position: relative;
	font-family: var( --wp--preset--font-family--sans, inherit );
	color: #fff;
}

.videos-modal__video::after {
	content: "";
	position: absolute;
	inset: 0;
	z-index: 4;
	padding: 2px;
	border-radius: 0;
	background: var( --vm-grad );
	-webkit-mask: linear-gradient( #000 0 0 ) content-box, linear-gradient( #000 0 0 );
	-webkit-mask-composite: xor;
	mask-composite: exclude;
	pointer-events: none;
	opacity: 0;
	transition: opacity 260ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__video[data-state="playing"]::after {
	opacity: 1;
}

.videos-modal__state {
	position: absolute;
	top: 50%;
	right: 16px;
	left: 16px;
	z-index: 2;
	margin: 0;
	transform: translateY( -50% );
	text-align: center;
	font-size: 14px;
	line-height: 1.4;
	color: #fff;
}

.videos-modal__state[hidden] {
	display: none;
}

.videos-modal__state-hint {
	display: block;
	margin-top: 4px;
	font-size: 12px;
	color: #c9c9c9;
}

.videos-modal__state-hint:empty {
	display: none;
}

.videos-modal__bigplay,
.videos-modal__replay,
.videos-modal__bar {
	display: none;
}

.videos-modal__video[data-state="paused"] .videos-modal__bar,
.videos-modal__video[data-state="playing"] .videos-modal__bar,
.videos-modal__video[data-state="ended"] .videos-modal__bar {
	display: flex;
}

.videos-modal__video[data-state="paused"] .videos-modal__bigplay {
	display: flex;
}

.videos-modal__video[data-state="ended"] .videos-modal__replay {
	display: inline-flex;
}

.videos-modal__bigplay {
	position: absolute;
	top: 50%;
	left: 50%;
	z-index: 3;
	box-sizing: border-box;
	align-items: center;
	justify-content: center;
	width: 72px;
	height: 72px;
	padding: 0 0 0 4px;
	transform: translate( -50%, -50% );
	border: 1px solid rgb( 255 255 255 / 0.85 );
	border-radius: 50%;
	background: rgb( 0 0 0 / 0.45 );
	color: #fff;
	cursor: pointer;
	transition: background-color 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 ), transform 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__bigplay:hover {
	background: rgb( 0 0 0 / 0.6 );
}

.videos-modal__bigplay:active {
	transform: translate( -50%, -50% ) scale( 0.96 );
}

.videos-modal__bigplay svg {
	width: 30px;
	height: 30px;
	fill: currentColor;
}

.videos-modal__replay {
	position: absolute;
	top: 50%;
	left: 50%;
	z-index: 3;
	box-sizing: border-box;
	align-items: center;
	height: 36px;
	padding: 0 18px;
	transform: translate( -50%, -50% );
	border: 1px solid #fff;
	border-radius: 999px;
	background: rgb( 0 0 0 / 0.45 );
	color: #fff;
	font: inherit;
	font-size: 14px;
	font-weight: 600;
	cursor: pointer;
	transition: background-color 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__replay:hover {
	background: rgb( 0 0 0 / 0.6 );
}

.videos-modal__bar {
	position: absolute;
	right: 0;
	bottom: 0;
	left: 0;
	z-index: 3;
	align-items: center;
	gap: 10px;
	padding: 28px 12px 10px;
	background: linear-gradient( to top, rgb( 0 0 0 / 0.7 ), rgb( 0 0 0 / 0 ) );
	color: #fff;
	transition: opacity 260ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__video.is-idle .videos-modal__bar {
	opacity: 0;
	pointer-events: none;
}

.videos-modal__bar button {
	display: inline-flex;
	flex: none;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	width: 32px;
	height: 32px;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: 999px;
	background: transparent;
	color: #fff;
	cursor: pointer;
	transition: background-color 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__bar button:hover {
	background: rgb( 255 255 255 / 0.16 );
}

.videos-modal__bar svg {
	width: 20px;
	height: 20px;
	fill: currentColor;
}

/* Mute: the slashed speaker is the mute state's icon; the open speaker shows while sound is on. */
.videos-modal__mute[aria-pressed="true"] svg {
	opacity: 0.6;
}

/* Scrub: a 5px track, the gradient is the played part (--p is written by player.js). */
.videos-modal__scrub {
	-webkit-appearance: none;
	appearance: none;
	flex: 1;
	min-width: 0;
	height: 16px;
	margin: 0;
	padding: 0;
	background: transparent;
	cursor: pointer;
}

.videos-modal__scrub:focus-visible {
	outline: 2px solid var( --vm-focus );
	outline-offset: 2px;
	border-radius: 2px;
}

.videos-modal__scrub::-webkit-slider-runnable-track {
	height: 5px;
	border-radius: 999px;
	background: linear-gradient( to right, var( --vm-grad ) 0 var( --p, 0% ), rgb( 255 255 255 / 0.32 ) var( --p, 0% ) 100% );
}

.videos-modal__scrub::-moz-range-track {
	height: 5px;
	border-radius: 999px;
	background: linear-gradient( to right, var( --vm-grad ) 0 var( --p, 0% ), rgb( 255 255 255 / 0.32 ) var( --p, 0% ) 100% );
}

.videos-modal__scrub::-webkit-slider-thumb {
	-webkit-appearance: none;
	appearance: none;
	width: 12px;
	height: 12px;
	margin-top: -3.5px;
	border: 0;
	border-radius: 50%;
	background: #fff;
	opacity: 0;
	transition: opacity 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__scrub::-moz-range-thumb {
	width: 12px;
	height: 12px;
	border: 0;
	border-radius: 50%;
	background: #fff;
	opacity: 0;
	transition: opacity 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__scrub:hover::-webkit-slider-thumb,
.videos-modal__scrub:focus-visible::-webkit-slider-thumb,
.videos-modal__scrub:active::-webkit-slider-thumb {
	opacity: 1;
}

.videos-modal__scrub:hover::-moz-range-thumb,
.videos-modal__scrub:focus-visible::-moz-range-thumb,
.videos-modal__scrub:active::-moz-range-thumb {
	opacity: 1;
}

.videos-modal__time {
	flex: none;
	font-size: 12px;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
	color: #fff;
}

.videos-modal__bigplay:focus-visible,
.videos-modal__replay:focus-visible,
.videos-modal__bar button:focus-visible {
	outline: 2px solid var( --vm-focus );
	outline-offset: 2px;
}

/* Embed-only videos: the provider's own player has its controls, so none of ours show. */
.videos-modal__video[data-state="embed"] .videos-modal__bar,
.videos-modal__video[data-state="embed"] .videos-modal__bigplay,
.videos-modal__video[data-state="embed"] .videos-modal__replay {
	display: none;
}

/* Volume slider, playback-rate select and fullscreen button (DPV-018 parity with the built-in player). */
.videos-modal__volume {
	-webkit-appearance: none;
	appearance: none;
	flex: none;
	width: 72px;
	height: 16px;
	margin: 0;
	padding: 0;
	background: transparent;
	cursor: pointer;
}

.videos-modal__volume::-webkit-slider-runnable-track {
	height: 5px;
	border-radius: 999px;
	background: linear-gradient( to right, var( --vm-grad ) 0 calc( var( --v, 100 ) * 1% ), rgb( 255 255 255 / 0.32 ) 0 100% );
}

.videos-modal__volume::-moz-range-track {
	height: 5px;
	border-radius: 999px;
	background: rgb( 255 255 255 / 0.32 );
}

.videos-modal__volume::-moz-range-progress {
	height: 5px;
	border-radius: 999px;
	background: var( --vm-grad );
}

.videos-modal__volume::-webkit-slider-thumb {
	-webkit-appearance: none;
	appearance: none;
	width: 12px;
	height: 12px;
	margin-top: -3.5px;
	border: 0;
	border-radius: 50%;
	background: #fff;
}

.videos-modal__volume::-moz-range-thumb {
	width: 12px;
	height: 12px;
	border: 0;
	border-radius: 50%;
	background: #fff;
}

.videos-modal__volume:focus-visible {
	outline: 2px solid var( --vm-focus );
	outline-offset: 2px;
	border-radius: 2px;
}

.videos-modal__rate {
	-webkit-appearance: none;
	-moz-appearance: none;
	appearance: none;
	flex: none;
	box-sizing: border-box;
	height: 28px;
	margin: 0;
	padding: 0 24px 0 10px;
	border: 1px solid rgb( 255 255 255 / 0.6 );
	border-radius: 999px;
	/* Chevron drawn inline (no image file): white, 10 x 6 px, 9px from the right edge. */
	background-color: transparent;
	background-image: url( "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='white' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E" );
	background-repeat: no-repeat;
	background-position: right 9px center;
	background-size: 10px 6px;
	color: #fff;
	color-scheme: dark;
	font: inherit;
	font-size: 12.5px;
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	line-height: 1;
	cursor: pointer;
	transition: background-color 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 ), border-color 160ms cubic-bezier( 0.2, 0.8, 0.2, 1 );
}

.videos-modal__rate:hover {
	background-color: rgb( 255 255 255 / 0.16 );
	border-color: #fff;
}

/* The open list follows the pane: ink background, white text (color-scheme dark keeps Chrome's list dark too). */
.videos-modal__rate option {
	color: #fff;
	background: #111;
}

.videos-modal__rate:focus-visible {
	outline: 2px solid var( --vm-focus );
	outline-offset: 2px;
}

.videos-modal__fullscreen[hidden] {
	display: none;
}

@media ( prefers-reduced-motion: reduce ) {
	.videos-modal__video::after,
	.videos-modal__bar,
	.videos-modal__bigplay,
	.videos-modal__replay,
	.videos-modal__bar button,
	.videos-modal__scrub::-webkit-slider-thumb,
	.videos-modal__scrub::-moz-range-thumb {
		transition: none;
	}
}

@media ( forced-colors: active ) {
	.videos-modal__bar,
	.videos-modal__bigplay,
	.videos-modal__replay {
		forced-color-adjust: auto;
	}

	.videos-modal__video::after {
		display: none;
	}
}
```

Also in `view.js`, `els.nopreview` is removed (section 5.6 of the earlier draft is still the step: delete `nopreview` from `els` and delete `mountPlayer`, which moves to `player.js`).

### 5.8 `view.js` edits (exact find/replace)

a) Imports: after the line `import { linkFor, copyText } from '../shared/copy-link.js';` add:
```js
import { createPlayer } from './player.js';
```

b) Inside `if ( dialog && dataEl ) {`, after the line `const routing = createRouting();` add:
```js
	const player = createPlayer( dialog, strings );
```

c) In `els`, remove the line `nopreview: $( '.videos-modal__nopreview' ),`.

d) Delete the whole `mountPlayer` block: from `/** The player for one video: the uploaded file, else the embed, else the "can't be previewed" line. */` through its closing `};`. Replace the `unload` function (the block starting `/** Stops and unloads whatever the stage holds`) with:
```js
	const unload = () => player.unload();
```

e) In `fill`: change `const fill = ( item ) => {` to `const fill = ( item, gesture = false ) => {`. Change `mountPlayer( item );` to `player.mount( item, gesture );`.

f) In `show`: change the `fill( item );` line that comes before `dialog.showModal();` to `fill( item, push );`.

g) In `swapTo`: change `fill( item );` to `fill( item, true );`.

### 5.9 Tests

Theme PHP, `tests/test-video-modal.php`: add `test_player_markup_has_no_captions_and_the_status_line()` that renders the block after `go_to()` on a published video with an upload (reuse the file's fixture helpers) and asserts:
- output contains `videos-modal__bar`, `videos-modal__scrub`, `videos-modal__bigplay`, `videos-modal__state`;
- output does not contain `<track`, `CC`, `captions`, `controls`, `videos-modal__nopreview`;
- `devportfolio_video_modal_strings()` has keys `play`, `pause`, `mute`, `unmute`.

Add `test_player_styles_follow_the_brand()` that reads `blocks/video-modal/style.css` and asserts:
- it contains `var( --vm-grad )` exactly in the scrub track and the playing frame (count two in those rules) and in no `color`, `background` of a text or button rule: check that the only `--vm-grad` uses are in `::after`, `.videos-modal__scrub::-webkit-slider-runnable-track` and `::-moz-range-track`;
- it contains `cubic-bezier( 0.2, 0.8, 0.2, 1 )` (the design system ease) and no `transition: all`;
- it contains `:focus-visible` for the bar buttons.

Markup test (same method): output contains `videos-modal__volume`, `videos-modal__rate` (six options `0.5` to `2`, `1` selected) and `videos-modal__fullscreen`.

Add `test_player_js_has_no_native_controls_or_captions()` that reads `blocks/video-modal/player.js` and asserts it does not contain `.controls` and does not contain `track`.

Node: `tests/player-model.test.mjs` (section 5.2).

Gates:
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/shared 'node --test tests/*.test.mjs'
```
Expected: theme suite green (560 before, plus the new cases); video-modal node tests green; shared node tests unchanged (171).

Browser check (not automatable here): headless Chrome (puppeteer-core) on `https://devportfolio.ddev.site/videos/` with one uploaded video, at 1440px and 390px wide. Check:
- click tile → `data-state` becomes `playing`, `muted` is false when autoplay with sound is allowed; the 2px gradient frame shows; open with `?video_slug=` → muted;
- Space toggles; the bar fades after 3 s of playing and returns on pointer move;
- volume slider: drag to 0 → muted and the mute button says "Unmute"; drag up → unmuted; the setting survives opening the next video;
- rate select: choose 2× → `video.playbackRate` is 2 and stays 2 for the next video;
- rate select: the chevron shows on the right, the select is a 28px white-outline pill like the other controls, its open list is dark with white text, and the select is reachable with Tab and changes with the arrow keys;
- fullscreen button: enters fullscreen with the bar visible, the label becomes "Exit full screen", Escape or the button leaves it; on iPhone Safari the native video fullscreen opens;
- scrub arrow moves 5 s;
- **DPV-029:** click the scrub bar's middle → `video.currentTime` within 5% of `duration / 2` and the readout matches; drag from left to right → time follows the pointer and ends at the duration; the slider's `aria-valuetext` equals `ariaValueText()` for the new time; the gradient fill follows the thumb (`--p`);
- Replay button appears after the second end (use a short test clip);
- **Brand check:** the gradient appears only on the scrub played part and the playing frame; no gradient behind the time, the status text or any button; Hanken Grotesk is the font (computed `font-family`); focus rings are blue (`rgb(29, 98, 240)`) with keyboard Tab; motion is off with reduced motion on.

## 6. Commit plan (AGENTS.md rules)

1. `test(theme): cover the video player maths` — `tests/player-model.test.mjs` with `player-model.js` in the same commit (a pair; squash if needed so no commit is red).
2. `feat(theme): add the video player and control bar` — `blocks/video-modal/player.js`, `render.php`, `view.js`, `functions.php`.
3. `feat(theme): style the video player to the design system` — `style.css`, `theme.json`.
4. `test(theme): cover the video player markup, captions rule and styles` — `tests/test-video-modal.php`.
5. `docs(agents): document the video player` — `AGENTS.md`.
6. `docs(plans): add the DPV-017, DPV-018 and DPV-029 player plan` — this file.

Each commit must leave the suites green. Stage explicit paths only.

Branch: `feat/DPV-017-018-029-Video-Player-Controls`. PR title (72 characters or fewer): `feat(theme): add the video modal player, control bar and seeking`.

Footer: `Refs: DPV-017, DPV-018, DPV-029` and `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.

## 7. Risks and open questions (do not block the build)

- Browser autoplay rules differ (Safari needs `play()` inside the click's task). `start()` is called synchronously from `fill()` inside the click handler, so it stays in that task. Check on Safari in the browser pass.
- DPV-029 is a duplicate of DPV-018; the tracker entry still needs closing by a person.
- The gradient frame and progress fill are the only gradient uses; if the design system later allows a gradient focus ring or button, revisit D12 and D13.
- Mobile layout of the bar (DPV-011) is not covered; the desktop modal is the scope.

## 8. As built (supersedes sections 1 to 6 where they differ)

Branch `feat/DPV-017-018-029-Video-Player-Controls`, from `main`, not pushed. Commits, oldest first:

1. `88a2d91` feat(theme): add the video player maths
2. `eaf62b4` test(theme): cover the video player maths
3. `f6bb1b9` feat(theme): add the video player and control bar
4. `e136970` feat(theme): style the video player to the design system
5. `8f66fab` test(theme): cover the video player markup, captions rule and styles
6. `823e5a8` docs(agents): document the video player
7. `3180431` docs(plans): add the DPV-017, DPV-018 and DPV-029 player plan
8. `80a397c` fix(theme): draw the video fills and swap the play icon
9. `fc95a11` test(theme): cover the video fills and the pause icon
10. `76348f8` feat(theme): put volume and speed in bar popovers
11. `dde1af4` test(theme): cover the bar popovers and the speed list
12. `f4ad0e8` feat(theme): remove the mute button, keep mute in volume
13. `e7b5e70` test(theme): drop the mute strings from the player test
14. `4ad89d2` fix(theme): line the player bar up with the Copy link button
15. `7cbe1ab` feat(theme): bring volume, speed and full screen closer

Where the build differs from the plan:

| Plan | As built | Why |
|------|----------|-----|
| D18: native `<select>` for speed | Speed is a 42px text button (`.videos-modal__ratebtn`, no border) that opens a popover of six `.videos-modal__rateopt` buttons with `aria-pressed` | The user asked for a popover at the Copy link button's size |
| D19: volume `input` in the bar | Volume is a 42px button (`.videos-modal__volbtn`) that opens a popover holding the slider | Same request |
| D4: a mute button sets `preferMuted` | No mute button. The volume slider sets `preferMuted`: 0 mutes, above 0 unmutes. The volume button gets `data-muted` and dims its icon while muted | Mute is already in the slider; the user asked for the button to go |
| Bar buttons 32px, icons 20px | Bar controls 42px, icons 22px | Match the Copy link button (42px) |
| Bar padding `10px` bottom | Bar and sidebar footer share `--vm-foot-inset: 18px`, so the bottom rows line up | The speed button and the Copy link button had different centre lines |
| Volume and speed at the 10px gap | Volume, speed and full screen sit 6px closer (`margin-left: -6px`) | The user asked for them closer |
| Gradient fill as `linear-gradient(to right, var(--vm-grad) 0 …)` | Gradient as a sized background layer: `var(--vm-grad) 0 0 / var(--p) 100% no-repeat` over translucent white | A gradient is an image, not a colour stop; the plan's form was invalid CSS and drew no track |
| Volume fill reads `--v`, nothing sets it | `player.js` sets `--v` (0 to 100) from `syncVolume()` | The plan omitted the write |
| Play button changes only `aria-label` | Two icons (`.videos-modal__icon--play` and `--pause`) shown by `data-state` | The icon did not change |
| Strings `mute`, `unmute` | Removed. `play`, `pause`, `fullscreen`, `exitFullscreen` kept | Mute button removed |
| Escape and outside clicks not in the plan | Escape closes an open popover first (the dialog stays open); a `pointerdown` outside closes it | Popovers need a way to close |
| Test: the word `track` is absent from `player.js` | Test checks `<track` and `createElement( 'track' )` absent | The word appears in a comment about the volume fill |
| `AGENTS.md` at `$T/AGENTS.md` | Edited the repo-root `AGENTS.md` (the theme has no AGENTS.md) | Path error in the plan |
| Commit 2 bundles feat and test | Split: `f6bb1b9` (feat) breaks one theme assertion until `8f66fab` (test) | The user asked for feat and test commits to be separate |

Verification:

- Theme suite: 570 passing (560 before, plus 10 new cases).
- `blocks/video-modal` node tests: 10 passing (`player-model.test.mjs`).
- `blocks/shared` node tests: 171 passing, unchanged.
- The user checked the bar visually in screenshots (fills, play icon, alignment, spacing).

Not verified:

- The headless-browser checklist in section 5.9 was not run. That covers the autoplay and muted fallback, the 3-second idle fade, the replay, the DPV-029 seek accuracy, and the aria values.
- Safari autoplay, iPhone fullscreen (`webkitEnterFullscreen`), and reduced motion.
- Keyboard use of the popovers (Tab, Escape, arrow keys).
- Narrow portrait panes: the bar at 423px wide may crowd the time readout.

Open:

- DPV-029 is still open in the tracker. Closing it as merged into DPV-018 is a person's call.
- "Align it vertically with the copy link button" was read as aligning the bar's bottom row with the Copy link row. If another alignment was meant, say so.
