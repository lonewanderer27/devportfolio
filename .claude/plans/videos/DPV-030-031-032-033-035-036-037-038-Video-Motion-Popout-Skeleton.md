# Plan: Video motion (outline, switching, stopping, thumbnail-to-video), long-press pop-out, reduced motion and loading skeleton

Tickets **DPV-030** (page blur and outline), **DPV-031** (switching previews), **DPV-032** (stopping previews), **DPV-033** (thumbnail to video, desktop modal), **DPV-035** (long-press, mobile), **DPV-036** (pop-out preview, mobile), **DPV-037** (reduced motion and data saver), **DPV-038** (loading skeleton). Lo-fi: `.claude/plans/videos/videos-lofi-wireframe.html` sections B2, B3, B4, C, E9. Branch `feat/DPV-030-031-032-033-035-036-037-038-Video-Motion`, created **from `main`**.

**Written for a small model (Haiku 5.5 or similar).** Every new file is given in full; every edit is an anchored find/replace; every gate is a command. Do the phases in section 6 in order, run each **Gate**, and **stop and report** when a "Fact" in section 3 is false or a gate fails. Do not build anything in section 2. Do not commit or push unless asked (section 11 lists the commits).

Related plans (history only, do not edit): `videos/DPV-006-007-008-Video-Thumbnail-Hover-Preview-Grid.md` (the grid and the hover preview this extends), `videos/DPV-009-010-012-013-014-015-016-Video-Modal-Desktop.md` (the dialog), `videos/DPV-011-019-020-021-023-Video-Modal-Mobile-Viewer.md` (the mobile viewer), `videos/DPV-041-Video-Backend-Fields-Autofill.md` (poster, 6 s clip).

`$T` = `wp-content/themes/devportfolio`. No plugin change, **no plugin version bump**.

---

## 0. What is already built, and what the stories still need (read first)

DPV-007 already built part of DPV-030 to DPV-032. Reading `blocks/video-grid/preview.js`, `style.css`, `view.js`:

| Story line | State today |
|---|---|
| Blur 7px under 40% veil, fades in 200ms, never blocks the pointer | Built (`body::after`). **But** it is `z-index: 1` while the sticky header is `z-index: 20` (`theme.json` line 129) and the category band is `z-index: 5` (`document-categories/style.css:9`), so **navigation and pills are NOT blurred today**. Fix in phase 2. |
| Blur fades out in ~200ms on leaving the grid | **Not true today.** `backdrop-filter` is only set by the class, so the blur snaps off and only the white veil fades. Fix in phase 2. |
| Blur stays over the gaps; leaves only with the grid | Built (`pointerleave` on the grid only). Keep. |
| 2.5px dark outline with white edge on the active thumbnail | **Missing.** |
| Switching: new video at once, no delay, no fade-in | Delay part built. The 120ms fade-in on `.videos-item__video` must go. |
| Outline slides (view transition ~240ms; CSS ~220ms fallback) | **Missing.** |
| Only previous video fades out (~150ms) with bar and badge | **Missing** (today the old video is paused and reset at once; bar and badge vanish instantly). |
| Click stops preview, outline and blur at once, then modal | **Missing** (nothing listens to the click; the veil fades 200ms and the modal opens over it). |
| Resize keeps the outline aligned | **Missing** (no outline yet). |
| Poster stays until first frame, no spinner | Built (`is-previewing` is added on `playing`). |
| Modal thumbnail-to-video view transition | **Missing.** Dialog only plays `videos-modal-up` (260ms). |
| Long-press and pop-out | **Missing.** |
| Skeleton | **Missing.** Posters are already `loading="lazy"` (`render.php`). |
| Reduced motion / data saver for hover | Built (`canPreview()`); the rest is audited in phase 9. |

### Conflicts and decisions found while planning

1. **Global root view transition is switched off.** `document-categories/style.css:115-118` sets `::view-transition-old(root), ::view-transition-new(root) { animation: none }` for the whole page. The modal's backdrop and details must fade, so the modal's transition re-enables a root cross-fade **only while `html.is-morphing-modal` is set** (a more specific selector wins). The pop-out and the outline never touch root.
2. **Hit testing during a view transition is unverified.** While a transition runs, the browser may report the pointer over the document instead of the grid, which would fire a false `pointerleave` and end the preview. Phase 0 is a spike that checks it. Whatever the result, `preview.js` ignores a grid `pointerleave` while a morph runs and re-checks `:hover` when it ends (section 5.5), so it is safe either way.
3. **One shared transition helper.** The outline, the modal and the pop-out all need "name an element, run `startViewTransition`, unname it, skip it when interrupted". It lives once in `blocks/shared/morph.js` (shared by two blocks, as `shared/` is for), not copied three times.
4. **Embed-only videos** have no file to play. Decision D7: a long-press still opens the pop-out with the still poster (no autoplay), the same card as reduced motion. Open question Q1.
5. **Mobile modal is not animated.** DPV-033 says "screen wider than 700px". The mobile full-screen viewer keeps its present open and close. Pop-out only runs on the mobile routing (700px or less, or touch held sideways, `shared/routing.js`).
6. **DPV-037 depends on DPV-038** (the skeleton must not pulse) and touches every other ticket; it is implemented inside each phase and audited in phase 9.
7. **Private clips (lo-fi 🔒)** are in no ticket and have no data model. Out of scope.

## 1. Goal and acceptance criteria

**DPV-030 (blur and outline, desktop mouse, wider than 700px)**
1. When a preview starts, the page behind blurs about 7px under a 40% white veil, fading in over 200ms. This includes the header, the title, the category pills and the other thumbnails.
2. The veil never takes the pointer.
3. A 2.5px dark outline with a white edge wraps the playing thumbnail.
4. The pointer in a gap between thumbnails changes neither blur nor outline.
5. Leaving the grid fades blur and outline out over about 200ms (the blur itself fades, it does not snap).

**DPV-031 (switching)**
6. Moving to another thumbnail while one plays starts the new video at once: no 800ms wait and no fade-in.
7. The outline slides to the new thumbnail with a view transition of about 240ms. Without view transitions it slides with a CSS transition of about 220ms, or jumps.
8. Only the previous video fades out (about 150ms) back to its poster, together with its bar and mute badge.
9. The blur stays on the whole time. Only one preview plays at a time.

**DPV-032 (stopping)**
10. Pointer leaves the grid: preview, outline and blur stop; the thumbnail shows its poster.
11. Click on a thumbnail: preview, outline and blur are gone **at once** (no fade), then the modal opens.
12. Window resize during a preview: the outline stays on the active thumbnail.
13. The poster stays until the first frame is ready; no spinner (already true, keep).

**DPV-033 (thumbnail to video, desktop dialog)**
14. A click on a thumbnail makes its picture move and grow into the dialog's video pane in about 400ms while the backdrop and details fade in. Only the picture travels, no text.
15. Closing with ✕, Esc, the backdrop or Back shrinks the pane into its thumbnail in about 400ms while the backdrop and details fade out. If that thumbnail is hidden by the filter (or missing) the pane only fades out.
16. Wide and square videos widen as they grow and show their full shape at the end.
17. Esc or a new click mid-way jumps to the end state; nothing sticks.
18. Without view transitions the dialog fades in and out quickly (about 160ms). A deeplink open or Back/Forward open uses the plain fade (there is no thumbnail to grow from).

**DPV-035 (long-press, touch, mobile routing)**
19. A press held 450ms opens the pop-out. A quick tap still opens the full-screen viewer.
20. While holding: the thumbnail shrinks to 93% in 150ms and dims, and a ring fills in its centre over 450ms. A light haptic tick fires when the pop-out opens, where `navigator.vibrate` exists.
21. Moving the finger more than 10px, or a scroll starting, before 450ms cancels the press. Scrolling is never blocked.
22. The browser long-press menu (save image, copy) is off on thumbnails.
23. Screen-reader users still reach the viewer from the thumbnail button (unchanged markup); the pop-out is `aria-hidden`.

**DPV-036 (pop-out)**
24. A card appears centred, about 70% of the screen width, in the video's own shape (9:16, 1:1, 16:9, 4:3), with a slim title header and a soft shadow. It grows out of the pressed thumbnail in about 400ms.
25. The video autoplays muted from 0:00 as a 6 s loop with a thin progress bar and a mute badge.
26. The rest of the page blurs about 7px under a 45% white veil. "Release to close" shows under the card.
27. Lifting the finger shrinks the card back into its thumbnail in about 400ms and fades the blur out. The card has no buttons. One pop-out at a time.

**DPV-037 (reduced motion and data saver)**
28. Reduced motion or data saver: no hover preview, no outline, no blur (already so; keep and test). Click still opens the modal.
29. Long-press pop-out shows the still poster, no autoplay.
30. Modal and pop-out appear and disappear instantly (reduced motion). The skeleton does not pulse.

**DPV-038 (skeleton)**
31. While a poster loads, the tile shows a striped grey skeleton that pulses softly (1.4s loop). Posters keep loading lazily.
32. A tile with no poster stays a plain blank tile (DPV-006 criterion 4): no stripes, no pulse. A poster that fails stops the pulse.

## 2. Out of scope (do NOT build)

- The mobile viewer's open and close animation, the mobile Pages and Details sheets.
- Private clip locks (E5, E6).
- A spinner, a dim or any other loading UI for the previews.
- Any plugin, settings or `theme.json` change. Any change to the document or collateral pages.
- Generalising `document-categories`, `masonry` or other blocks. The only new shared file is `blocks/shared/morph.js`.
- Server-side or CSS-only no-JS pop-out.

## 3. Facts (verified while planning; stop and report if false)

- `blocks/video-grid/preview.js` exports `HOVER_DELAY_MS`, `LOOP_SECONDS`, `HOVER_QUERY`, `canPreview`, `shouldLoopBack`, `progress`, `stopPreview`, `attachPreviews`; the tests in `blocks/video-grid/tests/preview.test.mjs` import only the constants and the three pure functions.
- `view.js` imports `stopPreview` and calls it in `filter()` for hidden items; `tests/test-video-grid.php::test_view_script_contract` forbids the text `requestAnimationFrame(` in `view.js` (so no rAF there; rAF is allowed in the other files).
- `style.css` has `body::after` (z-index 1, `rgba(255, 255, 255, 0.4)`, `pointer-events: none`) and `body.is-videos-previewing::after` with `backdrop-filter: blur(7px)`; `tests/test-video-grid.php::test_page_veil_contract` pins both with regexes and the text `opacity 200ms`.
- `render.php` gives each `article.videos-item` an inline `view-transition-name:videos-item-<id>` (category filter). The tile is `position: relative`. The grid has no `position`.
- `theme.json` line 129: `header.wp-block-template-part { position: sticky; z-index: 20 }`. `document-categories/style.css:9`: `z-index: 5` on the sticky pill group.
- `video-modal/view.js`: `open( item, gesture )` calls `fill()` then `dialog.showModal()`; closing is `dialog.close()` from the backdrop click, the ✕ button, `popstate`, and the native Esc `cancel`; the `close` event runs `player.unload()` and `finishClose()`; `show( id, { push, trigger } )` stores `opener = trigger`; `push` is true only for a click.
- `video-modal/player.js` line ~121 sets `video.poster = item.poster`, so the dialog's new view-transition snapshot shows the poster, not black.
- `shared/routing.js` exports `createRouting()` with `isMobile()`.
- Node tests run as `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-grid 'node --test tests/*.test.mjs'` (same for `blocks/shared` and `blocks/video-modal`).
- Theme suite: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit`.

## 4. Decisions

| # | Decision | Why |
|---|---|---|
| D1 | `blocks/shared/morph.js`: `morph( { from, to, update, name, className } )` names `from` (old state) and `to` (new state) the same view-transition name, runs `document.startViewTransition`, removes the names afterwards, and returns `{ animated, finished, skip }`. Without support or with reduced motion it just calls `update()`. `settleMorph()` skips the running one. Starting a morph first settles the previous one. | One tested implementation for outline, modal and pop-out; "Esc/click mid-way jumps to the end" and "one at a time" fall out of the settle rule. |
| D2 | Names exist only while a transition runs; a tile's own inline name (`videos-item-<id>`) is saved and restored. | A permanent name makes an element a backdrop root (documented in AGENTS.md); also avoids two names on one tile. |
| D3 | The outline is one `div.videos-outline` inside the grid (`position: absolute`, grid gets `position: relative`), moved with `transform/width/height` from `offsetLeft/Top/Width/Height`. It morphs with `from = to = itself` and name `videos-outline`. | Resize and filter only need `refresh()`; the grid's scroll position never matters because it lives in the grid. |
| D4 | Veil layering: veil `z-index: 30` (above header 20 and pills 5), playing tile `31`, outline `32`, pop-out `40`. | Fixes "navigation, title, pills blur". |
| D5 | Veil fade-out: `backdrop-filter: blur(0)` at rest, `visibility: hidden` after the fade (`visibility 0s linear 200ms`), so the layer costs nothing when idle. A click adds `body.is-videos-instant` (`transition: none`) for two frames. | Real 200ms blur fade and a true "at once". |
| D6 | Previous video fade-out is the class `is-fading` for 150ms (`FADE_MS`), then pause and rewind. New video: no transition on the way in. | Story 8 and 6. |
| D7 | Pop-out for an embed-only or fileless video shows the still poster (same as reduced motion). | One card, one rule. Q1. |
| D8 | Long-press is `pointerdown/move/up/cancel` on the grid, `pointerType === 'touch'` and `routing.isMobile()` only; the click that follows a fired press is swallowed by a capture listener. | Keeps tap-to-open untouched; no `contextmenu`/save-image menu. |
| D9 | Touch scroll after the pop-out opened is blocked with a non-passive `touchmove` listener that calls `preventDefault()` only while the pop-out is open. | `touch-action` cannot change mid-touch; otherwise a drifting finger would pan and cancel. |
| D10 | Modal uses the morph for a click open and any desktop close. Deeplink open and Back/Forward open use the CSS fade. Fallback close is a 160ms class fade. | Stories 14-18. |
| D11 | Skeleton is a `::before` stripe layer on `.videos-item--poster:not(.is-loaded)`, under the image. `view.js` sets `is-loaded` on load (or when already `complete`) and `is-failed` on error. | No spinner; no pulse for blank or failed tiles; pulse is an opacity animation (compositor only). |

## 5. Files

Indentation is **tabs** in PHP, CSS, JS. Do not reformat existing code.

### 5.1 `$T/blocks/shared/morph.js` (new)

```js
/**
 * One element transition for the Videos page (DPV-031, DPV-033, DPV-036): a picture, or the hover outline,
 * moves from one element to another with the View Transitions API.
 *
 * `from` holds the name in the OLD state, `to` in the NEW state (they may be the same element). The names
 * exist only while the transition runs, and an element's own inline name is put back afterwards. Without
 * support, or with reduced motion, `update()` just runs. Starting a morph settles the one still running, so
 * an Esc or a new click mid-way jumps to the end and nothing is left named.
 */

export const MORPH_NAME = 'videos-media';

/**
 * @param {Document} doc Document (injectable for tests).
 * @param {Window}   win Window (injectable for tests).
 * @return {{supported:boolean,reducedMotion:boolean}}
 */
export function morphEnv( doc = document, win = window ) {
	return {
		supported: 'function' === typeof doc.startViewTransition,
		reducedMotion: Boolean( win.matchMedia && win.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ),
	};
}

/**
 * @param {{supported:boolean,reducedMotion:boolean}} env From morphEnv().
 * @return {boolean}
 */
export function canMorph( env ) {
	return Boolean( env.supported ) && ! env.reducedMotion;
}

function setName( el, name ) {
	if ( ! el ) {
		return () => {};
	}
	const previous = el.style.viewTransitionName;
	let done = false;
	el.style.viewTransitionName = name;
	return () => {
		if ( ! done ) {
			done = true;
			el.style.viewTransitionName = previous;
		}
	};
}

let running = null;

/** @return {boolean} True while a morph is running. */
export function isMorphing() {
	return null !== running;
}

/**
 * Jumps the running morph to its end state.
 *
 * @return {boolean} True when there was one.
 */
export function settleMorph() {
	if ( ! running ) {
		return false;
	}
	running.settle();
	return true;
}

/**
 * @param {Object}           o
 * @param {Element|null}     [o.from]      Named in the old state.
 * @param {Element|null}     [o.to]        Named in the new state (after update()).
 * @param {Function}         o.update      Changes the DOM.
 * @param {string}           [o.name]      View transition name.
 * @param {string}           [o.className] Class put on <html> while it runs (CSS hooks).
 * @param {Document}         [o.doc]
 * @param {Window}           [o.win]
 * @return {{animated:boolean,finished:Promise<void>,skip?:Function}}
 */
export function morph( { from = null, to = null, update, name = MORPH_NAME, className = 'is-morphing', doc = document, win = window } ) {
	settleMorph();

	if ( ! canMorph( morphEnv( doc, win ) ) ) {
		update();
		return { animated: false, finished: Promise.resolve() };
	}

	const restoreFrom = setName( from, name );
	let restoreTo = () => {};
	let transition = null;
	let settled = false;

	const run = {
		settle() {
			if ( settled ) {
				return;
			}
			settled = true;
			if ( transition ) {
				transition.skipTransition();
			}
			restoreFrom();
			restoreTo();
			doc.documentElement.classList.remove( className );
			if ( running === run ) {
				running = null;
			}
		},
	};

	running = run;
	doc.documentElement.classList.add( className );
	transition = doc.startViewTransition( () => {
		restoreFrom();
		update();
		if ( ! settled ) {
			restoreTo = setName( to, name );
		}
	} );

	const finished = transition.finished.then( run.settle, run.settle );
	return { animated: true, finished, skip: run.settle };
}
```

### 5.2 `$T/blocks/shared/tests/morph.test.mjs` (new)

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { canMorph, morph, morphEnv, isMorphing, settleMorph, MORPH_NAME } from '../morph.js';

const el = ( name = '' ) => ( { style: { viewTransitionName: name } } );

function fakeDoc( { supported = true } = {} ) {
	const classes = new Set();
	const doc = {
		documentElement: { classList: { add: ( c ) => classes.add( c ), remove: ( c ) => classes.delete( c ) } },
		classes,
		skipped: 0,
	};
	if ( supported ) {
		doc.startViewTransition = ( callback ) => {
			callback();
			return { finished: new Promise( () => {} ), skipTransition: () => { doc.skipped += 1; } };
		};
	}
	return doc;
}

const win = ( reduced = false ) => ( { matchMedia: () => ( { matches: reduced } ) } );

test( 'canMorph needs support and no reduced motion', () => {
	assert.equal( canMorph( { supported: true, reducedMotion: false } ), true );
	assert.equal( canMorph( { supported: false, reducedMotion: false } ), false );
	assert.equal( canMorph( { supported: true, reducedMotion: true } ), false );
} );

test( 'morphEnv reads the document and the media query', () => {
	assert.deepEqual( morphEnv( fakeDoc(), win( true ) ), { supported: true, reducedMotion: true } );
	assert.deepEqual( morphEnv( fakeDoc( { supported: false } ), win() ), { supported: false, reducedMotion: false } );
} );

test( 'unsupported or reduced motion: update runs once, nothing is named', () => {
	for ( const [ doc, w ] of [ [ fakeDoc( { supported: false } ), win() ], [ fakeDoc(), win( true ) ] ] ) {
		const from = el();
		let calls = 0;
		const result = morph( { from, update: () => { calls += 1; }, doc, win: w } );
		assert.equal( calls, 1 );
		assert.equal( result.animated, false );
		assert.equal( from.style.viewTransitionName, '' );
		assert.equal( isMorphing(), false );
	}
} );

test( 'names: from is cleared before update, to is named after it', () => {
	const doc = fakeDoc();
	const from = el( 'videos-item-7' );
	const to = el();
	let fromDuring = null;
	let toDuring = null;
	const result = morph( {
		from,
		to,
		doc,
		win: win(),
		update: () => {
			fromDuring = from.style.viewTransitionName;
			toDuring = to.style.viewTransitionName;
		},
	} );
	assert.equal( result.animated, true );
	assert.equal( fromDuring, 'videos-item-7' ); // restored to its own name before the new state
	assert.equal( toDuring, '' );
	assert.equal( to.style.viewTransitionName, MORPH_NAME );
	assert.equal( doc.classes.has( 'is-morphing' ), true );
	assert.equal( isMorphing(), true );
	settleMorph();
	assert.equal( to.style.viewTransitionName, '' );
	assert.equal( doc.classes.has( 'is-morphing' ), false );
	assert.equal( isMorphing(), false );
} );

test( 'one element can be both from and to (the outline)', () => {
	const doc = fakeDoc();
	const outline = el();
	morph( { from: outline, to: outline, name: 'videos-outline', className: 'is-morphing-outline', doc, win: win(), update() {} } );
	assert.equal( outline.style.viewTransitionName, 'videos-outline' );
	assert.equal( doc.classes.has( 'is-morphing-outline' ), true );
	settleMorph();
	assert.equal( outline.style.viewTransitionName, '' );
} );

test( 'starting a morph settles the running one', () => {
	const doc = fakeDoc();
	const a = el();
	morph( { to: a, doc, win: win(), update() {} } );
	assert.equal( a.style.viewTransitionName, MORPH_NAME );
	const b = el();
	morph( { to: b, doc, win: win(), update() {} } );
	assert.equal( a.style.viewTransitionName, '' );
	assert.equal( doc.skipped, 1 );
	assert.equal( b.style.viewTransitionName, MORPH_NAME );
	settleMorph();
} );

test( 'settleMorph is false when nothing runs', () => {
	assert.equal( settleMorph(), false );
} );
```

### 5.3 `$T/blocks/video-grid/outline.js` (new)

```js
/**
 * The sliding outline of the Videos grid (DPV-030, DPV-031, DPV-032): one element that wraps the playing
 * thumbnail, slides to the next one with a view transition (CSS transition without support) and fades out
 * with the page blur.
 */
import { morph, isMorphing } from '../shared/morph.js';

export const OUTLINE_PAD = 3;
export const OUTLINE_NAME = 'videos-outline';

/**
 * The outline box for a tile, relative to the grid (the grid is `position: relative`).
 *
 * @param {{offsetLeft:number,offsetTop:number,offsetWidth:number,offsetHeight:number}} item Tile.
 * @param {number} pad Space between the tile and the outline.
 * @return {{x:number,y:number,w:number,h:number}}
 */
export function outlineBox( item, pad = OUTLINE_PAD ) {
	return {
		x: item.offsetLeft - pad,
		y: item.offsetTop - pad,
		w: item.offsetWidth + pad * 2,
		h: item.offsetHeight + pad * 2,
	};
}

/** Does nothing; used when a grid has no outline element. */
export const NO_OUTLINE = {
	show() {
		return null;
	},
	hide() {},
	refresh() {},
	busy: () => false,
};

/**
 * @param {HTMLElement} el The `.videos-outline` element.
 */
export function createOutline( el ) {
	let current = null;

	const place = ( item ) => {
		const box = outlineBox( item );
		el.style.transform = `translate(${ box.x }px, ${ box.y }px)`;
		el.style.width = `${ box.w }px`;
		el.style.height = `${ box.h }px`;
	};

	// Writes the box with no CSS transition (first show, resize, filter).
	const placeNow = ( item ) => {
		el.classList.add( 'is-instant' );
		place( item );
		void el.offsetWidth;
		el.classList.remove( 'is-instant' );
	};

	return {
		/**
		 * Puts the outline on a tile: appears in place the first time, slides after that.
		 *
		 * @param {HTMLElement} item Tile.
		 * @return {Promise<void>|null} Resolves when a slide ends, null when there is none.
		 */
		show( item ) {
			if ( ! current ) {
				placeNow( item );
				void el.offsetWidth;
				el.classList.add( 'is-on' );
				current = item;
				return null;
			}
			if ( current === item ) {
				return null;
			}
			current = item;
			// Supported: the view transition moves it. Not supported: update() runs and the CSS transition slides.
			return morph( {
				from: el,
				to: el,
				name: OUTLINE_NAME,
				className: 'is-morphing-outline',
				update: () => place( item ),
			} ).finished;
		},

		/**
		 * Fades the outline out, or removes it at once.
		 *
		 * @param {boolean} instant No fade.
		 */
		hide( instant = false ) {
			current = null;
			if ( instant ) {
				el.classList.add( 'is-instant' );
				el.classList.remove( 'is-on' );
				void el.offsetWidth;
				el.classList.remove( 'is-instant' );
				return;
			}
			el.classList.remove( 'is-on' );
		},

		/** Re-aligns after a resize or a filter change. */
		refresh() {
			if ( current ) {
				placeNow( current );
			}
		},

		/** @return {boolean} True while the slide runs. */
		busy: isMorphing,
	};
}
```

### 5.4 `$T/blocks/video-grid/tests/outline.test.mjs` (new)

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { outlineBox, OUTLINE_PAD, NO_OUTLINE } from '../outline.js';

test( 'the box wraps the tile with the pad on every side', () => {
	const tile = { offsetLeft: 100, offsetTop: 40, offsetWidth: 120, offsetHeight: 213 };
	assert.deepEqual( outlineBox( tile ), { x: 97, y: 37, w: 126, h: 219 } );
	assert.deepEqual( outlineBox( tile, 0 ), { x: 100, y: 40, w: 120, h: 213 } );
	assert.equal( OUTLINE_PAD, 3 );
} );

test( 'the empty outline is inert', () => {
	assert.equal( NO_OUTLINE.show( {} ), null );
	assert.equal( NO_OUTLINE.busy(), false );
	assert.doesNotThrow( () => { NO_OUTLINE.hide(); NO_OUTLINE.refresh(); } );
} );
```

### 5.5 `$T/blocks/video-grid/preview.js` (edit)

Keep the header comment, the constants, `canPreview`, `shouldLoopBack` and `progress` **unchanged**. Make these edits.

**(a)** Replace the imports/constants block. Find:

```js
// Set on <body> while a preview plays; style.css veils the rest of the page behind it (lo-fi B2).
const PAGE_CLASS = 'is-videos-previewing';
```

Replace with:

```js
import { NO_OUTLINE, createOutline } from './outline.js';

export const FADE_MS = 150;

// Set on <body> while a preview plays; style.css veils the rest of the page behind it (lo-fi B2).
const PAGE_CLASS = 'is-videos-previewing';
// Set for two frames when the veil must go at once (a click), see style.css.
const INSTANT_CLASS = 'is-videos-instant';
```

(`import` must be the first statement in a module after comments: move the `import` line to the top of the file, directly under the header comment, and keep `export const FADE_MS` and the two `const` lines where the old block was.)

**(b)** Replace from the line `// The one tile waiting out HOVER_DELAY_MS, as { item, timer }, and the tile that is playing.` **to the end of the file** with:

```js
// The one tile waiting out HOVER_DELAY_MS, as { item, timer }, and the tile that is playing.
let pending = null;
let active = null;
let outline = NO_OUTLINE;
let grid = null;
let leftWhileBusy = false;
// Tiles whose video is fading out: item -> timer.
const fading = new Map();

function cancelPending() {
	if ( pending ) {
		window.clearTimeout( pending.timer );
		pending = null;
	}
}

/**
 * Reduced motion and data saver, read at each event (the visitor can change them live).
 *
 * @param {string} src Preview source ('' when the tile has none).
 * @return {{reducedMotion:boolean,saveData:boolean,src:string}}
 */
export function motionEnvironment( src ) {
	const connection = navigator.connection;
	return {
		reducedMotion: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
		saveData: Boolean( connection && connection.saveData ),
		src: src || '',
	};
}

function environment( item ) {
	return {
		hover: window.matchMedia( HOVER_QUERY ).matches,
		...motionEnvironment( item.getAttribute( 'data-preview-src' ) ),
	};
}

// The tile's <video>, created on first use and kept.
function videoOf( item ) {
	let video = item.querySelector( '.videos-item__video' );
	if ( video ) {
		return video;
	}

	const isClip = item.hasAttribute( 'data-preview-clip' );
	video = document.createElement( 'video' );
	video.className = 'videos-item__video';
	video.muted = true;
	video.defaultMuted = true;
	video.playsInline = true;
	video.loop = isClip;
	video.preload = 'auto';
	video.disablePictureInPicture = true;
	video.tabIndex = -1;
	video.setAttribute( 'aria-hidden', 'true' );
	video.src = item.getAttribute( 'data-preview-src' );

	video.addEventListener( 'playing', function () {
		// Only the playing tile shows its video; a video that starts after the pointer moved on stays hidden.
		if ( active === item ) {
			item.classList.add( 'is-previewing' );
		}
	} );
	video.addEventListener( 'timeupdate', function () {
		if ( shouldLoopBack( video.currentTime, isClip ) ) {
			video.currentTime = 0;
		}
		item.style.setProperty( '--p', String( progress( video.currentTime ) ) );
	} );

	item.insertBefore( video, item.querySelector( '.videos-item__bar' ) );
	return video;
}

// After the fade (or at once): pause, rewind, back to the poster.
function finishFade( item ) {
	const timer = fading.get( item );
	if ( timer ) {
		window.clearTimeout( timer );
		fading.delete( item );
	}
	item.classList.remove( 'is-fading', 'is-previewing' );
	item.style.removeProperty( '--p' );
	const video = item.querySelector( '.videos-item__video' );
	if ( video ) {
		video.pause();
		video.currentTime = 0;
	}
}

/**
 * Cancels a pending start and stops one tile's preview, back to the poster. The video, its bar and its
 * mute badge fade out over FADE_MS unless `instant`. This does not touch the page blur or the outline.
 *
 * @param {HTMLElement} item    The `.videos-item` tile.
 * @param {{instant?:boolean}} [options] `instant` skips the fade.
 */
export function stopPreview( item, { instant = false } = {} ) {
	if ( pending && pending.item === item ) {
		cancelPending();
	}
	if ( active === item ) {
		active = null;
	}

	if ( instant || ! item.classList.contains( 'is-previewing' ) ) {
		finishFade( item );
		return;
	}
	item.classList.remove( 'is-previewing' );
	item.classList.add( 'is-fading' );
	fading.set( item, window.setTimeout( function () {
		finishFade( item );
	}, FADE_MS ) );
}

// The page blur. `instant` removes it with no fade (a click, so the modal opens over a clean page).
function setVeil( on, instant ) {
	const body = document.body;
	if ( instant && ! on ) {
		body.classList.add( INSTANT_CLASS );
		body.classList.remove( PAGE_CLASS );
		window.requestAnimationFrame( function () {
			window.requestAnimationFrame( function () {
				body.classList.remove( INSTANT_CLASS );
			} );
		} );
		return;
	}
	body.classList.toggle( PAGE_CLASS, on );
}

// Ends the whole session: video, outline and blur.
function endSession( instant ) {
	cancelPending();
	leftWhileBusy = false;
	if ( active ) {
		stopPreview( active, { instant } );
	}
	outline.hide( instant );
	setVeil( false, instant );
}

/**
 * A tile was hidden by the filter or is otherwise gone: stop it, and end the session when it was the playing one.
 *
 * @param {HTMLElement} item The `.videos-item` tile.
 */
export function releasePreview( item ) {
	if ( active === item || ( pending && pending.item === item ) ) {
		endSession( true );
		return;
	}
	stopPreview( item, { instant: true } );
}

/** Re-aligns the outline after the filter moved the tiles. */
export function refreshPreviews() {
	outline.refresh();
}

function startPreview( item ) {
	const previous = active;
	finishFade( item ); // entering a tile whose video is still fading out
	active = item;
	if ( previous && previous !== item ) {
		stopPreview( previous ); // fades out while the outline slides over
	}
	setVeil( true, false );

	const slide = outline.show( item );
	if ( slide ) {
		slide.then( recheckLeave );
	}

	const played = videoOf( item ).play();
	if ( played && 'function' === typeof played.catch ) {
		// Autoplay refused or the file cannot play: stay on the poster.
		played.catch( function () {
			if ( active === item ) {
				endSession( true );
			}
		} );
	}
}

// A pointerleave that arrived while the outline slid is judged when the slide ends.
function recheckLeave() {
	if ( leftWhileBusy ) {
		leftWhileBusy = false;
		if ( grid && ! grid.matches( ':hover' ) ) {
			endSession( false );
		}
	}
}

/**
 * Wires the hover preview, the outline and the page blur to a grid. Pointer enter/leave only: focus is never
 * listened to.
 *
 * @param {HTMLElement} gridEl The `[data-videos-grid]` element.
 */
export function attachPreviews( gridEl ) {
	grid = gridEl;
	const outlineEl = grid.querySelector( '.videos-outline' );
	outline = outlineEl ? createOutline( outlineEl ) : NO_OUTLINE;

	grid.addEventListener(
		'pointerenter',
		function ( event ) {
			const item = event.target;
			if ( ! item.classList || ! item.classList.contains( 'videos-item' ) ) {
				return;
			}
			if ( 'mouse' !== event.pointerType && 'pen' !== event.pointerType ) {
				return;
			}
			if ( ! canPreview( environment( item ) ) ) {
				endSession( false );
				return;
			}
			if ( active === item ) {
				cancelPending();
				return;
			}
			cancelPending();
			if ( active ) {
				// The page is already veiled: moving to the next tile switches at once, with no delay.
				startPreview( item );
				return;
			}
			pending = {
				item: item,
				timer: window.setTimeout( function () {
					pending = null;
					startPreview( item );
				}, HOVER_DELAY_MS ),
			};
		},
		true
	);

	// Moving between tiles, or into a gap, keeps the playing one, the outline and the veil; leaving the grid
	// ends all three. While the outline slides the browser may report the pointer elsewhere: judge it after.
	grid.addEventListener( 'pointerleave', function ( event ) {
		if ( event.target !== grid ) {
			return;
		}
		if ( outline.busy() ) {
			leftWhileBusy = true;
			return;
		}
		endSession( false );
	} );

	// A click opens the modal: everything goes at once, so the modal opens over a clean page (capture phase,
	// before the modal's own listener on the document).
	grid.addEventListener(
		'click',
		function () {
			endSession( true );
		},
		true
	);

	// The grid changes width: keep the outline on the tile.
	if ( 'function' === typeof ResizeObserver ) {
		new ResizeObserver( function () {
			outline.refresh();
		} ).observe( grid );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			endSession( true );
		}
	} );
}
```

### 5.6 `$T/blocks/video-grid/tests/preview.test.mjs` (edit)

Append after the last test:

```js
import { FADE_MS, motionEnvironment } from '../preview.js';

test( 'the previous video fades out for 150 ms', () => {
	assert.equal( FADE_MS, 150 );
} );

test( 'motionEnvironment reads reduced motion, data saver and the source', () => {
	globalThis.window = { matchMedia: () => ( { matches: true } ) };
	globalThis.navigator = { connection: { saveData: true } };
	assert.deepEqual( motionEnvironment( 'a.mp4' ), { reducedMotion: true, saveData: true, src: 'a.mp4' } );
	assert.deepEqual( motionEnvironment( undefined ), { reducedMotion: true, saveData: true, src: '' } );
} );
```

(Move the new `import` to the top of the file next to the existing one if the runner complains; ES imports are hoisted so either works.)

### 5.7 `$T/blocks/video-grid/view.js` (edit)

**(a)** Find `import { attachPreviews, stopPreview } from './preview.js';` replace with:

```js
import { attachPreviews, releasePreview, refreshPreviews } from './preview.js';
import { attachLongPress } from './longpress.js';
import { createPopout } from './popout.js';
```

**(b)** In `filter( slug )`, find:

```js
			if ( hidden ) {
				stopPreview( item );
			}
		} );
	}
```

replace with:

```js
			if ( hidden ) {
				releasePreview( item );
			}
		} );
		refreshPreviews();
	}
```

**(c)** Replace the poster block (`// A poster that fails to load leaves the plain blank tile.` through its closing `} );`) with:

```js
	// The skeleton shows until the poster is in (DPV-038). A poster that fails leaves the plain blank tile
	// and the skeleton stops.
	items.forEach( function ( item ) {
		const img = item.querySelector( '.videos-item__img' );
		if ( ! img ) {
			return;
		}
		if ( img.complete && img.naturalWidth > 0 ) {
			item.classList.add( 'is-loaded' );
		}
		img.addEventListener( 'load', function () {
			item.classList.add( 'is-loaded' );
		} );
		img.addEventListener( 'error', function () {
			img.hidden = true;
			item.classList.add( 'is-failed' );
		} );
	} );
```

**(d)** Find `	attachPreviews( grid );` replace with:

```js
	attachPreviews( grid );
	attachLongPress( grid, createPopout( grid ) );
```

### 5.8 `$T/blocks/video-grid/longpress.js` (new)

```js
/**
 * Long-press on a touch screen (DPV-035): hold a thumbnail for HOLD_MS and the pop-out opens; lift the
 * finger and it closes. A quick tap is untouched (the click still opens the full-screen viewer). Only the
 * mobile routing gets it (shared/routing.js), and only a touch pointer.
 */
import { createRouting } from '../shared/routing.js';

export const HOLD_MS = 450;
export const MOVE_TOLERANCE = 10;

/**
 * Whether a finger has moved far enough to cancel the press.
 *
 * @param {number} dx Horizontal distance from where it landed, px.
 * @param {number} dy Vertical distance, px.
 * @return {boolean}
 */
export function movedTooFar( dx, dy ) {
	return Math.hypot( dx, dy ) > MOVE_TOLERANCE;
}

/**
 * Whether a pointer may start a press.
 *
 * @param {{pointerType:string,isPrimary:boolean,mobile:boolean}} env Pointer and screen.
 * @return {boolean}
 */
export function canLongPress( env ) {
	return 'touch' === env.pointerType && Boolean( env.isPrimary ) && Boolean( env.mobile );
}

/**
 * @param {HTMLElement} grid    The `[data-videos-grid]` element.
 * @param {{open:Function,close:Function,isOpen:Function}} popout From createPopout().
 * @param {{isMobile:Function}} [routing] Injectable for tests.
 */
export function attachLongPress( grid, popout, routing = createRouting() ) {
	let press = null;
	let swallowClick = false;
	let swallowTimer = 0;

	function cancel() {
		if ( press ) {
			window.clearTimeout( press.timer );
			press.item.classList.remove( 'is-pressing' );
			press = null;
		}
	}

	function fire() {
		if ( ! press ) {
			return;
		}
		const item = press.item;
		window.clearTimeout( press.timer );
		press = null;
		swallowClick = true;
		if ( navigator.vibrate ) {
			navigator.vibrate( 10 );
		}
		popout.open( item ); // removes is-pressing when the card is shown
	}

	grid.addEventListener( 'pointerdown', function ( event ) {
		if ( press || ! canLongPress( { pointerType: event.pointerType, isPrimary: event.isPrimary, mobile: routing.isMobile() } ) ) {
			return;
		}
		const item = event.target.closest ? event.target.closest( '.videos-item' ) : null;
		if ( ! item ) {
			return;
		}
		window.clearTimeout( swallowTimer );
		swallowClick = false;
		press = { item: item, x: event.clientX, y: event.clientY, timer: window.setTimeout( fire, HOLD_MS ) };
		item.classList.add( 'is-pressing' );
	} );

	grid.addEventListener( 'pointermove', function ( event ) {
		if ( press && movedTooFar( event.clientX - press.x, event.clientY - press.y ) ) {
			cancel();
		}
	} );

	function release() {
		cancel();
		if ( popout.isOpen() ) {
			popout.close();
			// The click that follows a long press is not a tap: swallow it, then forget it.
			swallowTimer = window.setTimeout( function () {
				swallowClick = false;
			}, 600 );
		}
	}
	grid.addEventListener( 'pointerup', release );
	grid.addEventListener( 'pointercancel', release );

	// A click after a fired press must not open the full-screen viewer.
	grid.addEventListener(
		'click',
		function ( event ) {
			if ( swallowClick ) {
				swallowClick = false;
				event.preventDefault();
				event.stopPropagation();
			}
		},
		true
	);

	// No save-image / copy menu on a thumbnail.
	grid.addEventListener( 'contextmenu', function ( event ) {
		event.preventDefault();
	} );

	// Once the card is open the finger must not pan the page (touch-action cannot change mid-touch).
	grid.addEventListener(
		'touchmove',
		function ( event ) {
			if ( popout.isOpen() && event.cancelable ) {
				event.preventDefault();
			}
		},
		{ passive: false }
	);
}
```

### 5.9 `$T/blocks/video-grid/tests/longpress.test.mjs` (new)

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { HOLD_MS, MOVE_TOLERANCE, movedTooFar, canLongPress } from '../longpress.js';

test( 'the numbers match the story', () => {
	assert.equal( HOLD_MS, 450 );
	assert.equal( MOVE_TOLERANCE, 10 );
} );

test( 'movement up to 10 px keeps the press, more cancels it', () => {
	assert.equal( movedTooFar( 0, 0 ), false );
	assert.equal( movedTooFar( 10, 0 ), false );
	assert.equal( movedTooFar( 6, 8 ), false ); // exactly 10
	assert.equal( movedTooFar( 7, 8 ), true );
	assert.equal( movedTooFar( 0, -11 ), true );
} );

test( 'only the first touch finger on the mobile routing may press', () => {
	const ok = { pointerType: 'touch', isPrimary: true, mobile: true };
	assert.equal( canLongPress( ok ), true );
	assert.equal( canLongPress( { ...ok, pointerType: 'mouse' } ), false );
	assert.equal( canLongPress( { ...ok, pointerType: 'pen' } ), false );
	assert.equal( canLongPress( { ...ok, isPrimary: false } ), false );
	assert.equal( canLongPress( { ...ok, mobile: false } ), false );
} );
```

### 5.10 `$T/blocks/video-grid/popout.js` (new)

```js
/**
 * The long-press pop-out (DPV-036): a centred card in the video's own shape that grows out of the pressed
 * thumbnail, plays the muted 6 s loop (still poster with reduced motion, data saver or no file), blurs the page
 * and shrinks back on release. It has no buttons and is hidden from assistive technology: the thumbnail's
 * button still opens the full viewer.
 */
import { morph, settleMorph } from '../shared/morph.js';
import { canPreview, shouldLoopBack, progress, motionEnvironment } from './preview.js';

const BODY_CLASS = 'is-videos-popout';
const RATIOS = { '9x16': 9 / 16, '1x1': 1, '16x9': 16 / 9, '4x3': 4 / 3 };
// Static markup (no data): the same speaker-off icon the tiles carry.
const MUTE_SVG = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg>';

/**
 * The card's width / height for a `data-ratio` slug (9:16 when missing or unknown, like the tile).
 *
 * @param {string} slug 9x16, 1x1, 16x9 or 4x3.
 * @return {number}
 */
export function ratioValue( slug ) {
	return RATIOS[ slug ] || RATIOS[ '9x16' ];
}

function node( tag, className ) {
	const el = document.createElement( tag );
	el.className = className;
	return el;
}

/**
 * @param {HTMLElement} grid The `[data-videos-grid]` element (its `data-hint` is the release hint).
 */
export function createPopout( grid ) {
	let root = null;
	let media = null;
	let img = null;
	let titleEl = null;
	let bar = null;
	let video = null;
	let current = null;

	function build() {
		if ( root ) {
			return;
		}
		root = node( 'div', 'videos-popout' );
		root.hidden = true;
		root.setAttribute( 'aria-hidden', 'true' );
		const card = node( 'div', 'videos-popout__card' );
		const head = node( 'div', 'videos-popout__head' );
		titleEl = node( 'span', 'videos-popout__title' );
		head.append( titleEl );
		media = node( 'div', 'videos-popout__media' );
		img = node( 'img', 'videos-popout__img' );
		img.alt = '';
		bar = node( 'span', 'videos-popout__bar' );
		const mute = node( 'span', 'videos-popout__mute' );
		mute.innerHTML = MUTE_SVG;
		media.append( img, bar, mute );
		card.append( head, media );
		const hint = node( 'p', 'videos-popout__hint' );
		hint.textContent = grid.getAttribute( 'data-hint' ) || '';
		root.append( card, hint );
		document.body.append( root );
	}

	function play( item ) {
		const src = item.getAttribute( 'data-preview-src' ) || '';
		if ( ! canPreview( { hover: true, ...motionEnvironment( src ) } ) ) {
			return; // the still poster stays
		}
		const isClip = item.hasAttribute( 'data-preview-clip' );
		video = node( 'video', 'videos-popout__video' );
		video.muted = true;
		video.defaultMuted = true;
		video.playsInline = true;
		video.loop = isClip;
		video.preload = 'auto';
		video.disablePictureInPicture = true;
		video.src = src;
		video.addEventListener( 'playing', function () {
			root.classList.add( 'is-playing' );
		} );
		video.addEventListener( 'timeupdate', function () {
			if ( shouldLoopBack( video.currentTime, isClip ) ) {
				video.currentTime = 0;
			}
			bar.style.setProperty( '--p', String( progress( video.currentTime ) ) );
		} );
		media.insertBefore( video, bar );
		const played = video.play();
		if ( played && 'function' === typeof played.catch ) {
			played.catch( function () {} );
		}
	}

	function stop() {
		if ( video ) {
			video.pause();
			video.remove();
			video = null;
		}
		if ( root ) {
			root.classList.remove( 'is-playing' );
		}
		if ( bar ) {
			bar.style.removeProperty( '--p' );
		}
	}

	const hide = function () {
		root.hidden = true;
		document.body.classList.remove( BODY_CLASS );
	};

	function open( item ) {
		if ( current ) {
			return;
		}
		build();
		const button = item.querySelector( '.videos-item__open' );
		const poster = item.querySelector( '.videos-item__img' );
		const src = poster && ! poster.hidden ? poster.currentSrc || poster.src : '';
		titleEl.textContent = button ? button.getAttribute( 'aria-label' ) || '' : '';
		img.hidden = '' === src;
		if ( src ) {
			img.src = src;
		} else {
			img.removeAttribute( 'src' );
		}
		root.style.setProperty( '--ratio', String( ratioValue( item.getAttribute( 'data-ratio' ) || '' ) ) );
		current = { item: item };

		morph( {
			from: item,
			to: media,
			className: 'is-morphing-popout',
			update: function () {
				item.classList.remove( 'is-pressing' );
				root.hidden = false;
				document.body.classList.add( BODY_CLASS );
			},
		} );
		play( item );
	}

	function close() {
		if ( ! current ) {
			return;
		}
		const item = current.item;
		current = null;
		stop();
		morph( {
			from: media,
			to: item.hidden ? null : item,
			className: 'is-morphing-popout',
			update: hide,
		} );
	}

	// Leaving the page or the tab closes it at once.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden && current ) {
			current = null;
			stop();
			settleMorph();
			hide();
		}
	} );

	return { open: open, close: close, isOpen: () => null !== current };
}
```

### 5.11 `$T/blocks/video-grid/tests/popout.test.mjs` (new)

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { ratioValue } from '../popout.js';

test( 'each ratio slug gives width / height', () => {
	assert.equal( ratioValue( '9x16' ), 9 / 16 );
	assert.equal( ratioValue( '1x1' ), 1 );
	assert.equal( ratioValue( '16x9' ), 16 / 9 );
	assert.equal( ratioValue( '4x3' ), 4 / 3 );
} );

test( 'a missing or unknown slug is the tile shape, 9:16', () => {
	assert.equal( ratioValue( '' ), 9 / 16 );
	assert.equal( ratioValue( 'banana' ), 9 / 16 );
	assert.equal( ratioValue( undefined ), 9 / 16 );
} );
```

### 5.12 `$T/functions.php` (edit)

In `devportfolio_video_item()` find:

```php
			'poster_id'  => (int) get_post_thumbnail_id( $post_id ),
			'preview'    => devportfolio_video_preview_url(
```

replace with:

```php
			'poster_id'  => (int) get_post_thumbnail_id( $post_id ),
			'ratio'      => devportfolio_video_ratio_slug( $post_id ),
			'preview'    => devportfolio_video_preview_url(
```

(`devportfolio_video_ratio_slug()` is defined below it in the same file; it is only called at render time, so order does not matter.)

### 5.13 `$T/blocks/video-grid/render.php` (edit)

**(a)** Wrapper attributes. Find:

```php
		'class'            => 'videos-grid',
		'data-videos-grid' => '',
```

replace with:

```php
		'class'            => 'videos-grid',
		'data-videos-grid' => '',
		'data-hint'        => __( 'Release to close', 'devportfolio' ),
```

**(b)** The article. Find the whole line starting `<article class="videos-item" style=` and replace it with:

```php
		<article class="videos-item<?php if ( $devportfolio_item['poster_id'] > 0 ) : ?> videos-item--poster<?php endif; ?>" style="view-transition-name:videos-item-<?php echo esc_attr( (string) $devportfolio_id ); ?>" data-video-categories="<?php echo esc_attr( implode( ' ', $devportfolio_item['categories'] ) ); ?>"<?php if ( '' !== $devportfolio_item['ratio'] ) : ?> data-ratio="<?php echo esc_attr( $devportfolio_item['ratio'] ); ?>"<?php endif; ?><?php if ( '' !== $devportfolio_preview['url'] ) : ?> data-preview-src="<?php echo esc_url( $devportfolio_preview['url'] ); ?>"<?php endif; ?><?php if ( $devportfolio_preview['clip'] ) : ?> data-preview-clip<?php endif; ?>>
```

**(c)** The ring, between the mute badge and the open button. Find the line starting `<button type="button" class="videos-item__open"` and insert **before** it:

```php
			<span class="videos-item__ring" aria-hidden="true"><svg viewBox="0 0 40 40"><circle class="videos-item__ring-track" cx="20" cy="20" r="16"/><circle class="videos-item__ring-fill" cx="20" cy="20" r="16"/></svg></span>
```

**(d)** The outline, after the loop. Find:

```php
	<?php endforeach; ?>
</div>
```

replace with:

```php
	<?php endforeach; ?>
	<div class="videos-outline" aria-hidden="true"></div>
</div>
```

### 5.14 `$T/blocks/video-grid/style.css` (edit)

**(a) Grid positioned, tile touch rules.** Find:

```css
.videos-grid {
	--videos-gap: 6px;

	display: grid;
```

replace with:

```css
.videos-grid {
	--videos-gap: 6px;

	position: relative;
	display: grid;
```

Find `	cursor: pointer;\n	view-transition-class: videos-item;\n}` (the end of the `.videos-item` rule) and replace with:

```css
	cursor: pointer;
	view-transition-class: videos-item;
	-webkit-touch-callout: none;
	-webkit-user-select: none;
	user-select: none;
	-webkit-tap-highlight-color: transparent;
	transition: transform 150ms ease-out;
}
```

**(b) Skeleton (DPV-038).** Insert after the `.videos-item[hidden] { display: none; }` rule:

```css
/*
 * Loading skeleton (DPV-038, lo-fi C): a striped grey layer under the poster pulses softly until the poster
 * is in (it covers the layer). A tile with no poster stays a plain blank; a failed poster stops the pulse.
 */
.videos-item--poster:not(.is-loaded, .is-failed)::before {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: 2.5px;
	background: repeating-linear-gradient(135deg, var(--wp--preset--color--fill) 0 8px, #e2e2e2 8px 16px);
	animation: videos-pulse 1.4s ease-in-out infinite;
}

@keyframes videos-pulse {
	0%,
	100% {
		opacity: 1;
	}
	50% {
		opacity: 0.55;
	}
}
```

**(c) Previous video fade-out, no fade-in (DPV-031).** Find and **delete** the line `	transition: opacity 120ms linear;` inside `.videos-item__video { ... }`. Then insert after the `.videos-item.is-previewing .videos-item__bar, .videos-item.is-previewing .videos-item__mute { opacity: 1; }` rule:

```css
/* The previous tile only (DPV-031): video, bar and badge fade out together; the new one has no fade-in. */
.videos-item.is-fading .videos-item__video,
.videos-item.is-fading .videos-item__bar,
.videos-item.is-fading .videos-item__mute {
	transition: opacity 150ms ease-in;
}
```

**(d) Veil, replacing the old veil block.** Replace everything from the comment `/*\n * Page veil while a preview plays` through the rule `.videos-item.is-previewing {\n	z-index: 2;\n}` with:

```css
/*
 * Page veil while a preview plays (lo-fi B2) or a pop-out is open (lo-fi E9): the rest of the page blurs about
 * 7px under a white veil, 40% for the hover preview and 45% for the pop-out. It sits above the sticky header (20)
 * and the category band (5) so they blur too, never takes the pointer, and the playing tile (31), the outline
 * (32) and the pop-out (40) sit above it. At rest it is hidden (no cost); the blur itself fades in and out.
 */
body::after {
	content: '';
	position: fixed;
	inset: 0;
	z-index: 30;
	pointer-events: none;
	background: rgba(255, 255, 255, 0.4);
	opacity: 0;
	visibility: hidden;
	-webkit-backdrop-filter: blur(0);
	backdrop-filter: blur(0);
	transition: opacity 200ms linear, backdrop-filter 200ms linear, -webkit-backdrop-filter 200ms linear, visibility 0s linear 200ms;
}

body.is-videos-previewing::after,
body.is-videos-popout::after {
	opacity: 1;
	visibility: visible;
	-webkit-backdrop-filter: blur(7px);
	backdrop-filter: blur(7px);
	transition-delay: 0s;
}

body.is-videos-popout::after {
	background: rgba(255, 255, 255, 0.45);
}

/* A click: the veil goes at once, so the modal opens over a clean page (preview.js adds this for two frames). */
body.is-videos-instant::after {
	transition: none;
}

.videos-item.is-previewing {
	z-index: 31;
}
```

**(e) Outline (DPV-030/031/032).** Append at the end of the file, before the first `@media (prefers-reduced-motion` block is fine; keep the existing media blocks at the end:

```css
/*
 * Sliding outline (lo-fi B2): one element that wraps the playing tile, 2.5px ink with a white edge so it reads
 * on the blur. It moves with a view transition (named only while it runs); without support, the CSS transition
 * below slides it. `is-instant` writes a position with no animation (first show, resize, a click).
 */
.videos-outline {
	position: absolute;
	top: 0;
	left: 0;
	z-index: 32;
	box-sizing: border-box;
	border: 2.5px solid var(--wp--preset--color--contrast);
	border-radius: 7px;
	box-shadow: 0 0 0 2px #fff;
	opacity: 0;
	pointer-events: none;
	transition: opacity 200ms linear, transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1), width 220ms cubic-bezier(0.2, 0.8, 0.2, 1), height 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.videos-outline.is-on {
	opacity: 1;
}

.videos-outline.is-instant {
	transition: none;
}

/* The view transition owns the slide: no CSS transition on the position at the same time. */
html.is-morphing-outline .videos-outline {
	transition: opacity 200ms linear;
}

::view-transition-group(videos-outline) {
	animation-duration: 240ms;
	animation-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
}

::view-transition-old(videos-outline) {
	display: none;
}

::view-transition-new(videos-outline) {
	animation: none;
}
```

**(f) Press feedback and ring (DPV-035).**

```css
/* Long-press feedback (lo-fi E9): shrinks to 93% in 150ms, dims, and a ring fills over 450ms (HOLD_MS). */
.videos-item.is-pressing {
	transform: scale(0.93);
}

.videos-item.is-pressing .videos-item__img {
	filter: brightness(0.85);
}

.videos-item__ring {
	position: absolute;
	top: 50%;
	left: 50%;
	width: 44px;
	height: 44px;
	margin: -22px 0 0 -22px;
	opacity: 0;
	pointer-events: none;
}

.videos-item__ring svg {
	width: 100%;
	height: 100%;
	transform: rotate(-90deg);
	fill: none;
	stroke-width: 3;
}

.videos-item__ring-track {
	stroke: rgba(255, 255, 255, 0.35);
}

.videos-item__ring-fill {
	stroke: #fff;
	stroke-linecap: round;
	stroke-dasharray: 100.53;
	stroke-dashoffset: 100.53;
}

.videos-item.is-pressing .videos-item__ring {
	opacity: 1;
}

.videos-item.is-pressing .videos-item__ring-fill {
	animation: videos-ring 450ms linear forwards;
}

@keyframes videos-ring {
	to {
		stroke-dashoffset: 0;
	}
}
```

**(g) Pop-out card (DPV-036).**

```css
/* Pop-out (lo-fi E9): a centred card in the video's own shape, about 70% of the screen width. */
.videos-popout {
	position: fixed;
	inset: 0;
	z-index: 40;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 14px;
	pointer-events: none;
}

.videos-popout[hidden] {
	display: none;
}

.videos-popout__card {
	width: min(70vw, calc((78dvh - 44px) * var(--ratio, 0.5625)));
	overflow: hidden;
	border-radius: 14px;
	background: #fff;
	box-shadow: 0 18px 50px rgba(0, 0, 0, 0.28);
}

.videos-popout__head {
	display: flex;
	align-items: center;
	height: 40px;
	padding: 0 14px;
	border-bottom: 1px solid var(--wp--custom--edge);
	font-size: 14px;
	font-weight: 700;
}

.videos-popout__title {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.videos-popout__media {
	position: relative;
	aspect-ratio: var(--ratio, 0.5625);
	background: var(--wp--preset--color--fill);
}

.videos-popout__img,
.videos-popout__video {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.videos-popout__img[hidden] {
	display: none;
}

.videos-popout__video {
	opacity: 0;
}

.videos-popout.is-playing .videos-popout__video {
	opacity: 1;
}

.videos-popout__bar {
	position: absolute;
	right: 0;
	bottom: 0;
	left: 0;
	height: 3px;
	background: #fff;
	opacity: 0;
	transform: scaleX(var(--p, 0));
	transform-origin: left;
}

.videos-popout__mute {
	position: absolute;
	top: 8px;
	right: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	border-radius: 50%;
	background: rgba(0, 0, 0, 0.55);
	color: #fff;
	opacity: 0;
}

.videos-popout.is-playing .videos-popout__bar,
.videos-popout.is-playing .videos-popout__mute {
	opacity: 1;
}

.videos-popout__hint {
	margin: 0;
	font-size: 13px;
	font-weight: 600;
	color: var(--wp--preset--color--contrast);
}
```

**(h) The shared picture transition (DPV-033, DPV-036).**

```css
/*
 * The picture that travels (shared/morph.js, name `videos-media`): a thumbnail grows into the dialog's video pane
 * or the pop-out card and back, in 400ms. A wide or square pane widens as it grows: both images are cropped to
 * the group (cover), so the full shape shows at the end.
 */
::view-transition-group(videos-media) {
	animation-duration: 400ms;
	animation-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
	overflow: clip;
}

::view-transition-old(videos-media),
::view-transition-new(videos-media) {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
```

**(i) Reduced motion and forced colours.** In the existing `@media (prefers-reduced-motion: reduce)` block, add inside it (keep what is there):

```css
	.videos-item {
		transition: none;
	}

	.videos-item.is-pressing {
		transform: none;
	}

	.videos-item--poster:not(.is-loaded, .is-failed)::before {
		animation: none;
	}

	.videos-outline {
		transition: none;
	}
```

In the existing `@media (forced-colors: active)` block add:

```css
	.videos-outline {
		border-color: CanvasText;
		box-shadow: 0 0 0 2px Canvas;
	}

	.videos-item--poster:not(.is-loaded, .is-failed)::before {
		background: Canvas;
		border: 1px solid CanvasText;
		animation: none;
	}
```

### 5.15 `$T/blocks/video-modal/view.js` (edit)

**(a)** Add the import after `import { createMobileVideo } from './mobile.js';`:

```js
import { morph, morphEnv, canMorph, settleMorph } from '../shared/morph.js';
```

**(b)** Add helpers just before `/** Fills and shows the dialog this screen gets: ...` :

```js
	// The visible thumbnail of a video, or null (hidden by the filter, or not in the grid).
	const tileFor = ( id ) => {
		const button = document.querySelector( `.videos-item__open[data-video-id="${ id }"]` );
		const tile = button ? button.closest( '.videos-item' ) : null;
		return tile && ! tile.hidden ? tile : null;
	};

	let fadingOut = false;

	/** Closes the desktop dialog: the pane shrinks into its thumbnail, else a quick fade, else at once. */
	const closeDesktop = () => {
		if ( ! dialog.open || fadingOut ) {
			return;
		}
		const env = morphEnv();
		if ( env.reducedMotion ) {
			dialog.close();
			return;
		}
		if ( canMorph( env ) ) {
			morph( { from: els.stage, to: tileFor( currentId ), className: 'is-morphing-modal', update: () => dialog.close() } );
			return;
		}
		fadingOut = true;
		dialog.classList.add( 'is-closing' );
		window.setTimeout( () => {
			fadingOut = false;
			dialog.classList.remove( 'is-closing' );
			if ( dialog.open ) {
				dialog.close();
			}
		}, 160 );
	};
```

**(c)** In `open( item, gesture )` replace the desktop branch:

```js
		if ( routing.isDesktop() ) {
			fill( item, gesture );
			dialog.showModal();
			els.close.focus();
			return;
		}
```

with:

```js
		if ( routing.isDesktop() ) {
			const reveal = () => {
				fill( item, gesture );
				dialog.showModal();
				els.close.focus();
			};
			// A click grows its thumbnail into the pane; a deeplink or Back/Forward has no thumbnail to grow from.
			const tile = gesture && opener ? opener.closest( '.videos-item' ) : null;
			if ( tile ) {
				morph( { from: tile, to: els.stage, className: 'is-morphing-modal', update: reveal } );
			} else {
				reveal();
			}
			return;
		}
```

**(d)** Replace the three desktop close calls:

- `dialog.addEventListener( 'click', ( event ) => { if ( event.target === dialog ) { dialog.close(); } } );` → call `closeDesktop();` instead of `dialog.close();`.
- `els.close.addEventListener( 'click', () => dialog.close() );` → `els.close.addEventListener( 'click', closeDesktop );`.
- In the `popstate` handler `shownDialog().close();` → `shownDialog() === dialog ? closeDesktop() : mobileDialog.close();`.

**(e)** Add after `els.close.addEventListener( 'click', closeDesktop );`:

```js
	// Esc: a morph still running jumps to its end and the dialog closes at once; otherwise it closes animated.
	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		if ( settleMorph() ) {
			dialog.close();
		} else {
			closeDesktop();
		}
	} );
	// A new press while a morph runs jumps it to the end (nothing is left half-way).
	window.addEventListener( 'pointerdown', () => settleMorph(), true );
```

**(f)** In `routing.onChange( () => { ... } )` add `settleMorph();` as the first statement of the callback.

### 5.16 `$T/blocks/video-modal/style.css` (edit)

**(a)** Find:

```css
@media (prefers-reduced-motion: reduce) {
	.videos-modal[open] {
		animation: none;
	}
}
```

replace with:

```css
@media (prefers-reduced-motion: reduce) {
	.videos-modal[open] {
		animation: none;
	}

	.videos-modal[open]::backdrop {
		animation: none;
	}
}

/* DPV-033: no view transitions, the dialog and its backdrop fade quickly in and out. */
.videos-modal[open]::backdrop {
	animation: videos-fade-in 200ms linear;
}

.videos-modal.is-closing {
	opacity: 0;
	transition: opacity 160ms linear;
}

.videos-modal.is-closing::backdrop {
	opacity: 0;
	transition: opacity 160ms linear;
}

@keyframes videos-fade-in {
	from {
		opacity: 0;
	}
}

/*
 * DPV-033, view transitions: the picture travels (video-grid/style.css, name `videos-media`) while the page
 * cross-fades, which fades the backdrop and the details in and out. The page's own root transition is off
 * (document-categories/style.css), so it is switched on here only while the modal morphs. The dialog's own
 * entrance and backdrop fade are off then, so nothing fades twice, and the control bar is hidden so no text
 * travels with the picture.
 */
html.is-morphing-modal::view-transition-old(root) {
	animation: videos-fade-out 400ms linear both;
}

html.is-morphing-modal::view-transition-new(root) {
	animation: videos-fade-in 400ms linear both;
}

html.is-morphing-modal .videos-modal[open],
html.is-morphing-modal .videos-modal[open]::backdrop {
	animation: none;
}

html.is-morphing-modal .videos-modal__bar {
	visibility: hidden;
}

@keyframes videos-fade-out {
	to {
		opacity: 0;
	}
}
```

(If `.videos-modal__bar` is not the control bar's class, run `grep -n "__bar\b" blocks/video-modal/render.php blocks/video-modal/style.css`, use the real class, and mention it in the report. Hide every text overlay of the stage the same way.)

### 5.17 Tests added to the PHP suites

`$T/tests/test-video-grid.php`:

1. **Edit** `test_page_veil_contract()`: keep the first assertion; replace the second with
   `$this->assertMatchesRegularExpression( '/body\.is-videos-previewing::after,\s*body\.is-videos-popout::after \{[^}]*backdrop-filter: blur\(7px\)/s', $css );` and add
   `$this->assertMatchesRegularExpression( '/body::after \{[^}]*z-index: 30;/s', $css );` (above header 20), `$this->assertMatchesRegularExpression( '/body::after \{[^}]*backdrop-filter: blur\(0\)/s', $css );` (the blur itself fades), `$this->assertMatchesRegularExpression( '/body\.is-videos-popout::after \{[^}]*rgba\(255, 255, 255, 0\.45\)/s', $css );`, `$this->assertStringContainsString( 'body.is-videos-instant::after', $css );`.
2. **New** `test_outline_and_tile_layers()`: CSS has `.videos-item.is-previewing {\n\tz-index: 31;`, `.videos-outline` with `z-index: 32`, `border: 2.5px solid`, `pointer-events: none`, `::view-transition-group(videos-outline)` with `animation-duration: 240ms`, `html.is-morphing-outline .videos-outline`, `transition: opacity 200ms linear, transform 220ms`. The grid CSS has `position: relative;` inside `.videos-grid`.
3. **New** `test_grid_markup_has_outline_ring_hint_and_ratio()`: render with one video with a poster and an `aspect_ratio` term `16x9`: exactly one `div.videos-outline[aria-hidden=true]` child of the grid, the article has class `videos-item--poster` and `data-ratio="16x9"`, contains `span.videos-item__ring` with two `circle`, the grid root has `data-hint="Release to close"`. A video with no poster has no `videos-item--poster`; no ratio term means no `data-ratio` attribute.
4. **New** `test_previous_video_fades_and_new_one_does_not()`: `.videos-item__video` rule has no `transition`; `.videos-item.is-fading .videos-item__video` rule has `transition: opacity 150ms ease-in`; `preview.js` contains `FADE_MS = 150`.
5. **New** `test_skeleton_contract()`: CSS has `.videos-item--poster:not(.is-loaded, .is-failed)::before` with `repeating-linear-gradient` and `1.4s ease-in-out infinite`; the reduced-motion block has `animation: none` for it; `view.js` contains `'is-loaded'` and `'is-failed'`; `render.php` still has `'loading'  => 'lazy'`.
6. **New** `test_long_press_and_popout_contract()`: `longpress.js` contains `HOLD_MS = 450`, `MOVE_TOLERANCE = 10`, `contextmenu`, `{ passive: false }`; CSS contains `scale(0.93)`, `animation: videos-ring 450ms linear forwards`, `-webkit-touch-callout: none`; `.videos-popout__card` has `70vw`; `body.is-videos-popout::after` exists; `popout.js` contains `aria-hidden`, `shared/morph.js`; `view.js` calls `attachLongPress(` and `createPopout(`.
7. **New** `test_reduced_motion_contract()`: `preview.js` still has `canPreview` using `reducedMotion` and `saveData`; `shared/morph.js` contains `prefers-reduced-motion: reduce`; `popout.js` calls `motionEnvironment(`; the reduced-motion CSS block sets `.videos-item.is-pressing { transform: none; }`.
8. **Check and fix only the assertions that enumerate an item's children** in `test_item_has_only_a_poster_and_a_labelled_button()` (the ring span is new, and `data-ratio` is new on the article). Do not weaken any other assertion.

`$T/tests/test-video-modal.php`: **new** `test_modal_morph_contract()`: `view.js` contains `../shared/morph.js`, `closeDesktop`, `is-morphing-modal`, `'cancel'`, `settleMorph`, `is-closing`; no remaining bare `dialog.close()` in the backdrop-click and ✕ handlers (assert the text `els.close.addEventListener( 'click', closeDesktop )`); CSS contains `html.is-morphing-modal::view-transition-old(root)`, `animation: videos-fade-out 400ms`, `.videos-modal.is-closing`, and `.videos-modal[open]::backdrop` inside a reduced-motion block with `animation: none`.

`$T/tests/test-video-page.php` or `test-design-system.php`: no change expected; run them.

## 6. Phases and gates

Run every command from the repo root. `NODE` = `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/<dir> 'node --test tests/*.test.mjs'`.

**Phase 0 – Spike (no code kept; 15 minutes).** In headless Chrome (puppeteer-core in the scratchpad, as earlier tickets did) on `https://devportfolio.ddev.site/videos/` with at least 4 videos with uploaded files:
1. Confirm the veil is **not** above the header today (screenshot during a preview: header sharp). This confirms conflict 0 row 2.
2. In the console: add a `pointerleave` logger on `[data-videos-grid]`, hover a tile, then call `document.startViewTransition(() => {})` while the mouse stays still inside the grid, and report whether `pointerleave` or `pointerenter` fire during the 240ms.
Record the answers in section 12. The plan is correct either way (the guard in 5.5 covers a false leave). **If a false leave does fire**, also add `::view-transition { pointer-events: none; }` to `video-grid/style.css` and re-test; keep it only if it fixes it.

**Phase 1 – Morph helper.** Create 5.1 and 5.2.
Gate: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/shared 'node --test tests/*.test.mjs'` all pass (171 existing plus the new morph tests; update the count in AGENTS.md later from the real output).

**Phase 2 – DPV-030: veil and outline.** Create 5.3, 5.4. Apply 5.13 (a-d), 5.12, 5.14 (a, d, e, i) and the outline part of 5.5/5.7 (steps 5.5 a-b, 5.7 a, b, d without the long-press import yet: leave `attachLongPress`/`createPopout` for phase 7).
Gate: `NODE` for `video-grid` passes; theme PHP suite `--filter Devportfolio_Test_Video_Grid` passes after you apply the test edits of 5.17 items 1, 2, 3, 8 (for what exists so far).

**Phase 3 – DPV-031: switching.** Apply 5.14 (c), the `fading` code in 5.5, 5.6.
Gate: `NODE` passes; `--filter Devportfolio_Test_Video_Grid` passes with 5.17 item 4.

**Phase 4 – DPV-032: stopping.** The click capture, `ResizeObserver`, `releasePreview` and `refreshPreviews` are already in 5.5/5.7; this phase is the browser check only (section 8, rows 1-12).
Gate: section 8 rows pass.

**Phase 5 – DPV-033: modal morph.** Apply 5.15, 5.16, the `videos-media` CSS of 5.14 (h) and the modal test of 5.17.
Gate: `--filter Devportfolio_Test_Video_Modal` passes; section 8 rows 13-19 pass.

**Phase 6 – DPV-038: skeleton.** Apply 5.14 (b) and 5.7 (c) if not yet done; test 5.17 item 5.
Gate: `--filter Devportfolio_Test_Video_Grid` passes; section 8 rows 20-21.

**Phase 7 – DPV-035: long-press.** Create 5.8, 5.9; add the `attachLongPress` import and call (5.7 a, d); apply 5.14 (f); test 5.17 item 6 (long-press part).
Gate: `NODE` passes (longpress tests).

**Phase 8 – DPV-036: pop-out.** Create 5.10, 5.11; apply 5.14 (g); finish test 5.17 item 6.
Gate: `NODE` passes; section 8 rows 22-30.

**Phase 9 – DPV-037: reduced motion audit.** Apply 5.14 (i) fully; add test 5.17 item 7. Run section 8 rows 31-35.
Gate: full suites below.

**Phase 10 – Docs.** Update `AGENTS.md` (section 9). Final gates:

```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-grid 'node --test tests/*.test.mjs'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/shared 'node --test tests/*.test.mjs'
```

All green (the plugin suite is untouched; it runs only to prove it).

## 7. Unit test summary (exact values to assert)

| File | Cases |
|---|---|
| `shared/tests/morph.test.mjs` | `canMorph` truth table; `morphEnv`; no-op when unsupported or reduced; names set/cleared and own name restored; same element as from and to; a new morph settles the running one (`skipped === 1`); `settleMorph()` false when idle |
| `video-grid/tests/outline.test.mjs` | `outlineBox` with pad 3 and 0; `OUTLINE_PAD === 3`; `NO_OUTLINE` inert |
| `video-grid/tests/preview.test.mjs` | existing + `FADE_MS === 150`; `motionEnvironment` |
| `video-grid/tests/longpress.test.mjs` | `HOLD_MS === 450`, `MOVE_TOLERANCE === 10`; `movedTooFar` boundary (6,8 is 10, not more; 7,8 is more); `canLongPress` table |
| `video-grid/tests/popout.test.mjs` | `ratioValue` for the four slugs and the 9:16 default |
| PHP `Devportfolio_Test_Video_Grid` | section 5.17 items 1-8 |
| PHP `Devportfolio_Test_Video_Modal` | `test_modal_morph_contract` |

## 8. Browser check (headless Chrome with puppeteer-core in the scratchpad; the repo has no browser runner)

Desktop, 1280x800, mouse, 4+ videos with uploaded files, `https://devportfolio.ddev.site/videos/`:

1. Hover a tile 700ms: nothing. At 800ms the video plays, the outline appears, the blur fades in over about 200ms.
2. The **header, title, pills and the other tiles are blurred**; the playing tile is sharp.
3. Move into a gap: outline and blur unchanged.
4. Move to the next tile: video starts at once, no fade-in; outline slides (about 240ms); the old video fades out (about 150ms) with its bar and badge; blur never dips (sample `getComputedStyle(document.body,'::after').opacity` every 20ms during the move: always 1).
5. Only one `video` has `is-previewing` at any sample.
6. Move out of the grid: preview stops, outline and blur fade out in about 200ms; the blur value itself ramps (not a snap).
7. A pointer move across the blurred header area never gets blocked (`pointer-events: none` on the veil).
8. Click a tile while previewing: at the first frame after the click the body has no `is-videos-previewing`, the outline has no `is-on`, and the dialog opens.
9. Resize the window 1280 to 900 during a preview: the outline box equals the tile box plus 3px each side (compare `getBoundingClientRect()` within 1px).
10. Filter to a category while previewing: the outline follows or disappears with a hidden tile.
11. No view transitions (`document.startViewTransition = undefined` before load): the outline slides by CSS in about 220ms.
12. The spike answer on `pointerleave` during the slide holds: no preview stops when the pointer stays on the grid.

Modal:

13. Click a tile: the picture grows into the pane in about 400ms; backdrop and details fade in; no text travels (control bar hidden during the morph).
14. ✕, Esc, backdrop click and browser Back each shrink the pane into its tile (about 400ms).
15. Step with Next up to a video whose tile is hidden by the filter, close: the pane only fades out.
16. A 16:9 and a 1:1 video: the picture widens while growing and shows its full shape at the end.
17. Press Esc 100ms into the open: dialog closes at once, `document.documentElement.className` has no `is-morphing-modal`, no element keeps a `view-transition-name` of `videos-media`.
18. No view transitions: quick fade in (200ms backdrop) and fade out (160ms), dialog closes.
19. Open by a `?video_slug=` deeplink: plain fade, no morph; Back/Forward open and close: no stuck state.

Skeleton:

20. Network throttled "Slow 3G": striped grey tiles pulse (opacity between 0.55 and 1 over 1.4s) until each poster appears; a poster far below the fold requests only when scrolling towards it.
21. A tile with no poster stays plain (no `::before`); a tile whose poster URL 404s stops pulsing.

Mobile, 390x844 touch emulation (`pointerType: touch`):

22. Quick tap opens the full-screen viewer (unchanged).
23. Hold 449ms then lift: nothing opens, no pop-out; hold 450ms: ring completes, pop-out opens, `navigator.vibrate` called once.
24. While holding the tile is at 93% scale (150ms) and dimmed, the ring fills.
25. Hold, then move 11px before 450ms: press cancelled, page scrolls. Start a scroll first: no pop-out.
26. The browser menu does not appear (`contextmenu` default prevented).
27. Pop-out sizes: 9:16 card about 273px wide (70% of 390) with a 40px title header; 16:9 card also 273px wide; the card is centred; "Release to close" under it; veil is 45% and 7px.
28. It plays muted from 0:00 with progress bar and mute badge, loops (6s).
29. Lift the finger: the card shrinks into the tile in about 400ms and the blur fades; the click that follows does not open the viewer.
30. Only one pop-out: a second finger does nothing.

Reduced motion (`prefers-reduced-motion: reduce` via emulation) and data saver (`navigator.connection.saveData = true`):

31. Desktop hover: no video, no veil, no outline.
32. Modal opens and closes instantly (no morph, no animation).
33. Long-press: pop-out shows the still poster, no `video` element.
34. Skeleton does not animate.
35. Long-press without reduced motion on an embed-only video: pop-out with the still poster.

## 9. AGENTS.md update (phase 10)

In the **Videos grid** bullet (`blocks/video-grid/`) add: the veil layering (30/31/32/40), `outline.js`, `longpress.js`, `popout.js`, `blocks/shared/morph.js`, the `is-fading` rule, the skeleton classes, `data-ratio`, `data-hint`, the new tests and the browser-check notes. In the **Videos modal** bullet add the thumbnail-to-pane morph, `closeDesktop()`, `html.is-morphing-modal`, the Esc/pointerdown settle and the fallback fade. In the shared bullet add `morph.js`. Update the expected Node and PHP test totals from the real run output (do not guess). Commit `docs(agents)`.

## 10. Open questions (none block)

- **Q1: embed-only pop-out.** Built: long-press opens the card with the still poster. To disable it for videos with no file, return early in `attachLongPress` when the tile has no `data-preview-src` (one line plus a test).
- **Q2: tablets.** A touch device wider than 700px gets the desktop dialog and no hover, so it has neither hover preview nor long-press. DPV-035 says "700px wide or less". Extending the pop-out to every touch screen is one condition in `canLongPress`.
- **Q3: first start fade.** Per the story the new video has no fade-in, including the first one after the 800ms wait (the old 120ms fade-in was removed). If you want a soft first start, add `transition: opacity 120ms` on `.videos-item.is-previewing .videos-item__video` only.
- **Q4: haptics on iOS.** `navigator.vibrate` is not available in iOS Safari, so no tick there.
- **Q5: closing morph target.** The close morphs into the tile of `currentId`. After "Next up" swaps, that is the tile of the video now shown; the opener tile is not used.
- **Q6: control bar class** in 5.16 must be checked against the real markup (see note there).

## 11. Conventional commits (only when asked)

Branch `feat/DPV-030-031-032-033-035-036-037-038-Video-Motion`; PR title `feat(theme): add video motion, long-press pop-out and skeleton` (59 chars). Footers `Refs: <TICKETS>` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths only (untracked plan files of other tickets sit in the working tree). Each commit leaves every suite green.

1. `feat(theme): add the shared element morph helper` — `blocks/shared/morph.js`, `blocks/shared/tests/morph.test.mjs`. Refs DPV-031, DPV-033, DPV-036.
2. `feat(theme): add the hover outline and fix the page blur layering` — `video-grid/{outline.js,preview.js,view.js,render.php,style.css}`, `functions.php`, `video-grid/tests/{outline,preview}.test.mjs`, the matching edits in `tests/test-video-grid.php`. Refs DPV-030, DPV-031, DPV-032, DPV-037.
3. `feat(theme): animate the thumbnail into the video modal` — `video-modal/{view.js,style.css}`, `video-grid/style.css` (the `videos-media` rules), `tests/test-video-modal.php`. Refs DPV-033, DPV-037.
4. `feat(theme): add the video loading skeleton` — `video-grid/{style.css,view.js}`, its tests in `test-video-grid.php`. Refs DPV-038, DPV-037.
5. `feat(theme): add the long-press pop-out to the video grid` — `video-grid/{longpress.js,popout.js,view.js,style.css,render.php}`, `video-grid/tests/{longpress,popout}.test.mjs`, the matching PHP tests. Refs DPV-035, DPV-036, DPV-037.
6. `docs(agents): document the video motion and pop-out` — `AGENTS.md`. Refs same.
7. `docs(plans): add the video motion plan` — this file. Refs same.

(Code and its tests are paired in commits 2-5 because each is a small pair; split them if the diff is large.)

## 12. As-built

Implemented on `feat/DPV-030-031-032-033-035-036-037-038-Video-Motion` (uncommitted at the time of writing).

- Plan facts checked: all section 3 facts held. Two differences: `preview.test.mjs` needs `Object.defineProperty` for `navigator` (Node 24 defines it as a getter), and the modal reduced-motion `::backdrop` rule was moved after the fade rule so it wins (same specificity).
- Fix found in the browser check: `morph.settle()` now runs the pending change when the browser has not called the callback yet (the plan's version skipped it, so a click or Esc mid-open never reached its end state). The skipped transition's `ready` rejection is handled.
- Phase 0 spike (pointerleave during a view transition) was not run as a separate spike. The browser check passed row 12 (no false leave: the preview stayed on the grid during the slide), and the `pointerleave` guard stays in place.
- Browser check (headless Chrome, 1280x800 and 390x844 touch, `/videos/`): veil above the header, outline on, switch with one playing tile, leave ends veil and outline, click clears veil and outline at once and opens the modal, reopen after Esc works, skeleton clears after load, reduced motion has no preview, 500 ms hold opens the pop-out and a tap opens the viewer. Only console error: `favicon.ico` 404. Not checked in the browser: rows 7, 11, 16, 18-19 and 22-35 in full (the mobile pop-out size, the ring and the reduced-motion pop-out with a still poster).
- Esc during the opening morph (row 17): the dialog closes at once; a closing morph still ran afterwards in headless Chrome, so this row is not confirmed exactly.
