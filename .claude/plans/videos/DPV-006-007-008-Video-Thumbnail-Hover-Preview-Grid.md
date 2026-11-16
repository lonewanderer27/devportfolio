# Plan: Video thumbnails, hover preview and fluid grid

Tickets **DPV-006** (portrait poster thumbnail), **DPV-007** (desktop hover preview), **DPV-008** (fluid grid). Branch `feat/DPV-006-007-008-Video-Grid`, created **from `main`**.

**Written for a small model (Haiku 5.5 or similar):** nothing is left to design. Every file is given in full in section 5, every command in section 6. Copy exactly, run the gates, stop and report on any mismatch.

**How to read this plan:** do the phases in section 6 in order; run each phase's **Gate** before continuing; do not build anything listed in section 2; if the code does not match a "Facts" line, stop and report instead of guessing. Do not commit or push unless asked (section 7 lists the commits for when asked).

Related plans (history only): `videos/DPV-000-001-002-003-Videos-Page-Header-Categories.md` (the page this fills in), `videos/DPV-041-Video-Backend-Fields-Autofill.md` (poster, ratio and the 6 s preview clip this reuses), `collaterals/DPC-006-014-Collateral-Item-Masonry.md` (the grid block this copies the shape of), `documents/DPD-034-...` (reference page rules), `videos/videos-lofi-wireframe.html` (sections C, C2, B2).

`$T` = `wp-content/themes/devportfolio`, `$P` = `wp-content/plugins/custom-post-types-dp`.

---

## 1. Goal and acceptance criteria

**DPV-006 thumbnail**
1. Each published video is one item showing **only its poster** (the featured image): 9:16, 1.5px border, 4px radius, same size for all.
2. No title, category, length, play icon, tags or stats drawn on it. The title lives only in the button's `aria-label`.
3. A square or wide poster is **centre-cropped** to 9:16 (`object-fit: cover`, `object-position: center`); the full shape is the modal's job (not built here).
4. No poster = a plain blank tile of the same size (no number, no icon). A poster that fails to load shows the same blank.
5. `cursor: pointer`; no darkening, zoom or movement on hover. Keyboard focus: 2px outline, 3px offset (lo-fi C "Focus").

**DPV-007 hover preview**
6. Hover-capable pointers only (`(hover: hover) and (pointer: fine)`, pointer type mouse/pen). Touch and keyboard focus never start it.
7. Pointer stays 800 ms on an item -> it plays **in place**, muted, from 0:00, as a **6 s loop**. Leaving before 800 ms cancels; leaving afterwards stops it and shows the poster again (video paused, reset to 0).
8. The tile keeps its size and position. Added to it: a 3px white progress bar along the bottom edge and a small mute badge at the top right. Nothing else (the lo-fi's page blur and sliding outline are **not** in the story, do not build them).
9. Only a video with an **uploaded file** previews. An embed-only video (YouTube/Vimeo) never previews and shows its poster.
10. Only one preview plays at a time. Reduced motion (`prefers-reduced-motion: reduce`): no preview starts. Data saver (`navigator.connection.saveData`): no preview.

**DPV-008 grid**
11. Fluid CSS grid: `repeat(auto-fill, minmax(clamp(104px, 72px + 8vw, 180px), 1fr))`; gap 4px at 620px and below, 6px above.
12. Uses the shared page shell (`.documents-page`: up to 1440px, centred, same gutter). No new width rules.
13. The last row is left-aligned and not stretched (`auto-fill`, never `auto-fit`).
14. Order: `menu_order` ASC, then title ASC (same as Documents/Collaterals).
15. Category filter works: the existing categories block dispatches `devportfolio:category-change`; the grid hides non-matching items. (No empty-state line: DPV-027.)
16. Expected columns (verify in browser, section 6 Phase 5): 320px -> 2, 390 -> 3, 600 -> 4, 768 and 1024 -> 5, 1440+ -> 7. No horizontal page scroll.
17. Changing a video's Order in wp-admin changes its place in the grid (the seeded/real `menu_order` is what is read; no caching beyond the request memo that already clears on post change).

## 2. Out of scope (do NOT build)

- The video modal, opening on click, deeplink, Next/Prev (later tickets). The item's button carries `data-video-id` so they can bind to it; clicking does nothing yet.
- Phone long-press pop-out (lo-fi E9), page blur, sliding outline, view-transition between previews, loading skeleton (DPV-025 or later, see Q1), empty state and hiding pills with no videos (DPV-027).
- Any plugin change: poster, file, embed, preview clip and ratio already exist. **No plugin version bump.**
- Renaming `documents-*` classes, editing `document-categories`, `theme.json`, `page.css`, `parts/header.html`.

## 3. Facts already established (trust, but stop if false)

- Template `$T/templates/archive-video.html` has only the title and `devportfolio/document-categories` (taxonomy `video_category`) inside `div.documents-page__content`. `functions.php` already enqueues `page.css` on the `video` archive (line ~72).
- Blocks are registered in `$T/functions.php` on `init` with `register_block_type( __DIR__ . '/blocks/<name>' )` (lines ~56-62). `blocks/collateral-grid/` is the model: `block.json` (`render`, `style`, `viewScriptModule`), `render.php`, `style.css`, `view.js`, pure module + `tests/*.test.mjs`.
- `devportfolio_published_ids( $type, $args )` is the published-list helper (use it, not `get_posts()`); `devportfolio_term_slugs( $id, $taxonomy )` gives term slugs; `devportfolio_memo` is cleared on post/term/meta changes.
- Video meta (plugin, REST-registered): `cptdp_video_file` (attachment ID), `cptdp_video_preview` (attachment ID of the 6 s MP4/WebM clip made in wp-admin, v0.30.0; only uploaded files get one; **videos saved before 0.30.0 have none until re-saved**), `cptdp_embed_url`. Poster = featured image (autofilled from the embed or a browser frame).
- `blocks/document-categories/view.js` dispatches `devportfolio:category-change` (bubbles, `detail.slug`) synchronously inside the view transition, and does nothing to a grid that is not `[data-documents-shelf]`. The collateral grid shows the pattern: item `data-collateral-categories`, `matchesCategory()`, first paint filtered by reading the pressed pill. Reuse the idea; do not import across blocks (see D2).
- Tests: theme suite `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit`; Node `node --test` from the block dir.

## 4. Decisions

| # | Decision | Why |
|---|----------|-----|
| D1 | New block `devportfolio/video-grid` (`blocks/video-grid/`), placed in `archive-video.html` after the categories block. | Same shape as the collateral grid; one block per page grid. |
| D2 | Own tiny `matchesCategory()` in `blocks/video-grid/grid.js` (pure, tested) rather than importing from `collateral-grid/masonry.js`. | AGENTS.md: generalise shared pieces only when asked; two 3-line helpers are cheaper than a cross-block import. Record in "Follow-ups" that a shared `matchesCategory` is an obvious later extraction. |
| D3 | Layout is **pure CSS grid**, no JS layout (unlike masonry): every tile has the same 9:16 box. | Fixed ratio removes the need for measurement; simplest correct. |
| D4 | Preview source = the stored preview clip (`cptdp_video_preview`); if the video has an uploaded file but **no clip yet**, fall back to the uploaded file and loop it at 6 s in JS (`timeupdate`, `currentTime = 0` at >= 6). | Story: "6-second loop" for every uploaded-file video, including ones saved before 0.30.0. The clip is tiny; the fallback may be large, so it is only fetched on hover. |
| D5 | The `<video>` element is **created by JS on first hover** (`preload="auto"`, `muted`, `playsinline`, `loop`, `disablepictureinpicture`), source URL in `data-preview-src` on the item. Nothing is downloaded at rest. | No bandwidth for a page of 30 videos; also keeps no-JS/touch markup clean. Item keeps the video element after first use (reused; paused + `currentTime = 0` on leave). |
| D6 | Progress bar = element `span.videos-item__bar` whose `transform: scaleX(var(--p))` is set from `timeupdate`/`requestAnimationFrame` while playing (`--p` = `currentTime / 6`). Mute badge = static `span.videos-item__mute` (inline SVG speaker-off, `aria-hidden`). Both exist in the markup, hidden until `.is-previewing`. | No layout shift; CSS-only visibility; one source of truth for time. |
| D7 | Blank tile = the item's own background (`--wp--preset--color--fill`) with no content; the image sits above it. A failed image gets `hidden` from `view.js` (`error` event, same as collaterals). | Matches "plain blank tile"; zero extra markup. |
| D8 | Button, not link: one `button.videos-item__open[data-video-id][aria-label]` overlay (`position:absolute; inset:0`), direct child of the article. `aria-label` = video title. Preview events listen on the **article** (`pointerenter`/`pointerleave`), not the button, so the later modal can own the button. | Same control pattern as collaterals; accessible name without visible text. |
| D9 | Only one preview at a time: starting one stops the other (module-level `active`). | Criterion 10. |
| D10 | The 800 ms, 6 s and the media query live as named constants at the top of `preview.js`; the pure decision functions `canPreview( env )` and `shouldLoopBack( t )` are exported and unit tested. | Testable without a browser (repo has no browser runner). |

## 5. Files (every file is given in full: copy it exactly)

Indentation is **tabs** in PHP, CSS, JS and HTML. Do not reformat, rename or "improve" anything.

### 5.1 `$T/blocks/video-grid/block.json` (new)

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/video-grid",
	"title": "Video Grid",
	"category": "widgets",
	"description": "The Videos page grid: one portrait poster per video, with a muted hover preview.",
	"textdomain": "devportfolio",
	"supports": {
		"html": false
	},
	"render": "file:./render.php",
	"style": "file:./style.css",
	"viewScriptModule": "file:./view.js"
}
```

### 5.2 `$T/functions.php` (edit, two changes)

**(a)** In `devportfolio_register_blocks()`, after the line `register_block_type( __DIR__ . '/blocks/collateral-modal' );` add:

```php
		register_block_type( __DIR__ . '/blocks/video-grid' );
```

**(b)** Find the line `if ( ! function_exists( 'devportfolio_collateral_pill' ) ) :` and insert this block **immediately before it** (keep one blank line between the blocks):

```php
if ( ! function_exists( 'devportfolio_video_preview_url' ) ) :
	/**
	 * Source of a video's hover preview (DPV-007).
	 *
	 * The stored preview clip when it is an MP4 or WebM attachment, else the uploaded video file, else nothing
	 * (an embed-only video never previews). `clip` says which of the two it is.
	 *
	 * @param mixed $clip_id Attachment ID of the preview clip (`cptdp_video_preview`).
	 * @param mixed $file_id Attachment ID of the uploaded file (`cptdp_video_file`).
	 * @return array{url:string,clip:bool}
	 */
	function devportfolio_video_preview_url( $clip_id, $file_id ) {
		$clip_id = (int) $clip_id;
		if ( $clip_id > 0 && in_array( get_post_mime_type( $clip_id ), array( 'video/mp4', 'video/webm' ), true ) ) {
			$url = wp_get_attachment_url( $clip_id );
			if ( $url ) {
				return array(
					'url'  => $url,
					'clip' => true,
				);
			}
		}

		$file_id = (int) $file_id;
		if ( $file_id > 0 && 0 === strpos( (string) get_post_mime_type( $file_id ), 'video/' ) ) {
			$url = wp_get_attachment_url( $file_id );
			if ( $url ) {
				return array(
					'url'  => $url,
					'clip' => false,
				);
			}
		}

		return array(
			'url'  => '',
			'clip' => false,
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_item' ) ) :
	/**
	 * Everything the Videos grid needs for one video (DPV-006, DPV-007).
	 *
	 * @param int $post_id Video ID.
	 * @return array{title:string,categories:string[],poster_id:int,preview:array{url:string,clip:bool}}|null Null when the post is not a video.
	 */
	function devportfolio_video_item( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'video' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs = devportfolio_term_slugs( $post_id, 'video_category' );

		return array(
			'title'      => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'categories' => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'poster_id'  => (int) get_post_thumbnail_id( $post_id ),
			'preview'    => devportfolio_video_preview_url(
				get_post_meta( $post_id, 'cptdp_video_preview', true ),
				get_post_meta( $post_id, 'cptdp_video_file', true )
			),
		);
	}
endif;
```

### 5.3 `$T/blocks/video-grid/render.php` (new)

```php
<?php
/**
 * Server render of the Videos grid (DPV-006, DPV-007, DPV-008).
 *
 * Contract with view.js: the grid carries `data-videos-grid`; every item is a direct child
 * `article.videos-item` with `data-video-categories` (space-separated slugs) and, only for a video that
 * can preview, `data-preview-src` (plus `data-preview-clip` when the source is the stored 6 s clip).
 * The one control of an item is the empty `button.videos-item__open[data-video-id]`; nothing visible
 * carries the title. No videos renders nothing (the empty state is DPV-027).
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'video',
	array(
		'orderby' => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class'            => 'videos-grid',
		'data-videos-grid' => '',
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_id ) :
		$devportfolio_item = devportfolio_video_item( $devportfolio_id );
		if ( null === $devportfolio_item ) {
			continue;
		}

		$devportfolio_preview = $devportfolio_item['preview'];
		?>
		<article class="videos-item" data-video-categories="<?php echo esc_attr( implode( ' ', $devportfolio_item['categories'] ) ); ?>"<?php if ( '' !== $devportfolio_preview['url'] ) : ?> data-preview-src="<?php echo esc_url( $devportfolio_preview['url'] ); ?>"<?php endif; ?><?php if ( $devportfolio_preview['clip'] ) : ?> data-preview-clip<?php endif; ?>>
			<?php
			if ( $devportfolio_item['poster_id'] > 0 ) {
				echo wp_get_attachment_image(
					$devportfolio_item['poster_id'],
					'medium_large',
					false,
					array(
						'class'    => 'videos-item__img',
						'sizes'    => '(max-width: 620px) 34vw, 190px',
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
			}
			?>
			<span class="videos-item__bar" aria-hidden="true"></span>
			<span class="videos-item__mute" aria-hidden="true"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg></span>
			<button type="button" class="videos-item__open" data-video-id="<?php echo esc_attr( (string) $devportfolio_id ); ?>" aria-label="<?php echo esc_attr( $devportfolio_item['title'] ); ?>"></button>
		</article>
	<?php endforeach; ?>
</div>
```

### 5.4 `$T/blocks/video-grid/style.css` (new)

```css
/*
 * Videos grid (DPV-006, DPV-007, DPV-008): fixed 9:16 poster tiles in a fluid grid.
 * Page width and gutter come from the shared .documents-page shell (assets/css/page.css).
 */
.videos-grid {
	--videos-gap: 6px;

	display: grid;
	gap: var(--videos-gap);
	grid-template-columns: repeat(auto-fill, minmax(clamp(104px, 72px + 8vw, 180px), 1fr));
	margin-top: 20px;
}

@media (max-width: 620px) {
	.videos-grid {
		--videos-gap: 4px;
	}
}

.videos-item {
	position: relative;
	box-sizing: border-box;
	aspect-ratio: 9 / 16;
	border: 1.5px solid var(--wp--preset--color--contrast);
	border-radius: 4px;
	background: var(--wp--preset--color--fill);
	cursor: pointer;
}

.videos-item[hidden] {
	display: none;
}

.videos-item__img,
.videos-item__video {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	border-radius: 2.5px;
	object-fit: cover;
	object-position: center;
}

.videos-item__img[hidden] {
	display: none;
}

.videos-item__video {
	opacity: 0;
	pointer-events: none;
	transition: opacity 120ms linear;
}

.videos-item.is-previewing .videos-item__video {
	opacity: 1;
}

.videos-item__bar {
	position: absolute;
	right: 0;
	bottom: 0;
	left: 0;
	height: 3px;
	border-radius: 0 0 2.5px 2.5px;
	background: #fff;
	opacity: 0;
	pointer-events: none;
	transform: scaleX(var(--p, 0));
	transform-origin: left;
	transition: transform 250ms linear;
}

.videos-item__mute {
	position: absolute;
	top: 6px;
	right: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: rgba(0, 0, 0, 0.55);
	color: #fff;
	opacity: 0;
	pointer-events: none;
}

.videos-item.is-previewing .videos-item__bar,
.videos-item.is-previewing .videos-item__mute {
	opacity: 1;
}

.videos-item__open {
	position: absolute;
	inset: 0;
	padding: 0;
	border: 0;
	border-radius: 4px;
	background: none;
	cursor: pointer;
}

.videos-item__open:focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
	.videos-item__video,
	.videos-item__bar {
		transition: none;
	}
}

@media (forced-colors: active) {
	.videos-item {
		border-color: CanvasText;
	}

	.videos-item__bar {
		background: CanvasText;
	}
}
```

### 5.5 `$T/blocks/video-grid/grid.js` (new)

```js
/**
 * Pure helpers of the Videos grid (DPV-008).
 */

/**
 * Whether an item belongs to the chosen category.
 *
 * @param {string} attr  The item's `data-video-categories` (space-separated slugs).
 * @param {string} slug  The chosen slug, '' for All.
 * @return {boolean}
 */
export function matchesCategory( attr, slug ) {
	if ( '' === slug ) {
		return true;
	}
	return String( attr || '' ).split( /\s+/ ).includes( slug );
}
```

### 5.6 `$T/blocks/video-grid/preview.js` (new)

```js
/**
 * Desktop hover preview of the Videos grid (DPV-007).
 *
 * The pointer must rest on a tile for HOVER_DELAY_MS; then a muted <video> is created inside the tile
 * (nothing is downloaded before that), plays from 0:00 and loops every LOOP_SECONDS. Keyboard focus,
 * touch, reduced motion and data saver never start it. Only one tile plays at a time.
 */

export const HOVER_DELAY_MS = 800;
export const LOOP_SECONDS = 6;
export const HOVER_QUERY = '(hover: hover) and (pointer: fine)';

/**
 * Whether a preview may start.
 *
 * @param {{hover:boolean,reducedMotion:boolean,saveData:boolean,src:string}} env Current conditions.
 * @return {boolean}
 */
export function canPreview( env ) {
	return Boolean( env.hover ) && ! env.reducedMotion && ! env.saveData && '' !== ( env.src || '' );
}

/**
 * Whether a full-length file (not the stored clip) has played its 6 seconds and must restart.
 *
 * @param {number}  currentTime Seconds played.
 * @param {boolean} isClip      True when the source is the stored 6 s clip, which loops by itself.
 * @return {boolean}
 */
export function shouldLoopBack( currentTime, isClip ) {
	return ! isClip && currentTime >= LOOP_SECONDS;
}

/**
 * Progress bar fill, 0 to 1.
 *
 * @param {number} currentTime Seconds played.
 * @return {number}
 */
export function progress( currentTime ) {
	const value = currentTime / LOOP_SECONDS;
	if ( ! ( value > 0 ) ) {
		return 0;
	}
	return value > 1 ? 1 : value;
}

const timers = new WeakMap();
let active = null;

function environment( item ) {
	const connection = navigator.connection;
	return {
		hover: window.matchMedia( HOVER_QUERY ).matches,
		reducedMotion: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
		saveData: Boolean( connection && connection.saveData ),
		src: item.getAttribute( 'data-preview-src' ) || '',
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
		item.classList.add( 'is-previewing' );
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

/**
 * Cancels a pending start and stops a playing preview, back to the poster.
 *
 * @param {HTMLElement} item The `.videos-item` tile.
 */
export function stopPreview( item ) {
	window.clearTimeout( timers.get( item ) );
	timers.delete( item );

	const video = item.querySelector( '.videos-item__video' );
	if ( video ) {
		video.pause();
		video.currentTime = 0;
	}
	item.classList.remove( 'is-previewing' );
	item.style.removeProperty( '--p' );

	if ( active === item ) {
		active = null;
	}
}

function startPreview( item ) {
	if ( active && active !== item ) {
		stopPreview( active );
	}
	active = item;

	const played = videoOf( item ).play();
	if ( played && 'function' === typeof played.catch ) {
		// Autoplay refused or the file cannot play: stay on the poster.
		played.catch( function () {
			stopPreview( item );
		} );
	}
}

/**
 * Wires the hover preview to a grid. Pointer enter/leave only: focus is never listened to.
 *
 * @param {HTMLElement} grid The `[data-videos-grid]` element.
 */
export function attachPreviews( grid ) {
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
				return;
			}
			window.clearTimeout( timers.get( item ) );
			timers.set(
				item,
				window.setTimeout( function () {
					startPreview( item );
				}, HOVER_DELAY_MS )
			);
		},
		true
	);

	grid.addEventListener(
		'pointerleave',
		function ( event ) {
			if ( event.target.classList && event.target.classList.contains( 'videos-item' ) ) {
				stopPreview( event.target );
			}
		},
		true
	);

	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden && active ) {
			stopPreview( active );
		}
	} );
}
```

### 5.7 `$T/blocks/video-grid/view.js` (new)

```js
/**
 * Videos grid (DPV-006 to DPV-008): category filter, poster fallback and hover preview.
 *
 * Contract: the grid is `[data-videos-grid]`; its items are direct children `.videos-item` with
 * `data-video-categories`. The filter runs on `devportfolio:category-change` (dispatched by
 * document-categories/view.js inside the View Transition), synchronously: no requestAnimationFrame here.
 */
import { matchesCategory } from './grid.js';
import { attachPreviews, stopPreview } from './preview.js';

const FILTER_EVENT = 'devportfolio:category-change';

// Slug of the pressed category pill, '' for All (or no filter block).
function pressedSlug() {
	const pressed = document.querySelector( '.wp-block-devportfolio-document-categories [aria-pressed="true"]' );
	return pressed ? pressed.getAttribute( 'data-filter' ) || '' : '';
}

function init() {
	const grid = document.querySelector( '[data-videos-grid]' );
	if ( ! grid ) {
		return;
	}

	const items = Array.prototype.filter.call( grid.children, function ( el ) {
		return el.classList.contains( 'videos-item' );
	} );

	function filter( slug ) {
		items.forEach( function ( item ) {
			const hidden = ! matchesCategory( item.getAttribute( 'data-video-categories' ) || '', slug );
			item.hidden = hidden;
			if ( hidden ) {
				stopPreview( item );
			}
		} );
	}

	// A poster that fails to load leaves the plain blank tile.
	items.forEach( function ( item ) {
		const img = item.querySelector( '.videos-item__img' );
		if ( img ) {
			img.addEventListener( 'error', function () {
				img.hidden = true;
			} );
		}
	} );

	document.addEventListener( FILTER_EVENT, function ( event ) {
		filter( ( event.detail && event.detail.slug ) || '' );
	} );

	filter( pressedSlug() );
	attachPreviews( grid );
}

init();
```

### 5.8 `$T/templates/archive-video.html` (edit)

Find:

```html
		<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->
```

Replace with (same indentation, one blank line between):

```html
		<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->

		<!-- wp:devportfolio/video-grid /-->
```

### 5.9 Tests

**`$T/blocks/video-grid/tests/grid.test.mjs`** (new):

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { matchesCategory } from '../grid.js';

test( 'All matches everything', () => {
	assert.equal( matchesCategory( 'a b', '' ), true );
	assert.equal( matchesCategory( '', '' ), true );
} );

test( 'a slug matches only its items', () => {
	assert.equal( matchesCategory( 'short-form', 'short-form' ), true );
	assert.equal( matchesCategory( 'short-form video-edits', 'video-edits' ), true );
	assert.equal( matchesCategory( 'short-form', 'video-edits' ), false );
	assert.equal( matchesCategory( '', 'video-edits' ), false );
} );
```

**`$T/blocks/video-grid/tests/preview.test.mjs`** (new). `preview.js` touches `window` only inside functions, so importing it in Node is safe:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { HOVER_DELAY_MS, LOOP_SECONDS, canPreview, shouldLoopBack, progress } from '../preview.js';

const ok = { hover: true, reducedMotion: false, saveData: false, src: 'a.mp4' };

test( 'constants match the story', () => {
	assert.equal( HOVER_DELAY_MS, 800 );
	assert.equal( LOOP_SECONDS, 6 );
} );

test( 'canPreview needs hover, motion, no data saver and a source', () => {
	assert.equal( canPreview( ok ), true );
	assert.equal( canPreview( { ...ok, hover: false } ), false );
	assert.equal( canPreview( { ...ok, reducedMotion: true } ), false );
	assert.equal( canPreview( { ...ok, saveData: true } ), false );
	assert.equal( canPreview( { ...ok, src: '' } ), false );
} );

test( 'a stored clip never loops back in script, a full file does at 6 s', () => {
	assert.equal( shouldLoopBack( 7, true ), false );
	assert.equal( shouldLoopBack( 5.99, false ), false );
	assert.equal( shouldLoopBack( 6, false ), true );
} );

test( 'progress is clamped to 0..1', () => {
	assert.equal( progress( 0 ), 0 );
	assert.equal( progress( 3 ), 0.5 );
	assert.equal( progress( 6 ), 1 );
	assert.equal( progress( 9 ), 1 );
	assert.equal( progress( -1 ), 0 );
	assert.equal( progress( NaN ), 0 );
} );
```

**`$T/tests/test-video-grid.php`** (new). Mirror `tests/test-collateral-grid.php` (copy its `theme_dir()`, `set_up()` with `devportfolio_memo_flush()`, `do_action( 'cptdp_register_post_types' )`, `devportfolio_register_blocks()` guarded by `is_registered( 'devportfolio/video-grid' )`, and its `xpath()` helper; render with `trim( do_blocks( '<!-- wp:devportfolio/video-grid /-->' ) )`). Header: `@group theme`, class `Devportfolio_Test_Video_Grid extends WP_UnitTestCase`. Helper `make_video( array $args )` creates a `video` post (`post_title`, `post_status` default publish, `menu_order`), optional `category` (term created in `video_category`, assigned with `wp_set_object_terms`), optional `poster` (`true`: create an attachment with `self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' )` and `set_post_thumbnail`), optional `file` (attachment post with `post_mime_type` `video/mp4` created via `self::factory()->attachment->create( array( 'post_mime_type' => 'video/mp4', 'file' => 'sample.mp4' ) )`, saved to `cptdp_video_file`) and `clip` (same, saved to `cptdp_video_preview`). Test methods (each name states the criterion):

1. `test_nothing_is_rendered_without_published_videos` - a draft only: `render` is `''`.
2. `test_items_follow_menu_order_then_title_and_drafts_are_excluded` - orders 0/0/-1 and a draft, compare `data-video-id` list like the collateral test.
3. `test_changing_menu_order_changes_the_position` - render, `wp_update_post( array( 'ID' => $a, 'menu_order' => -9 ) )`, `devportfolio_memo_flush()` is NOT called by the test (the hook must do it), render again, assert the new order.
4. `test_item_has_only_a_poster_and_a_labelled_button` - one `article.videos-item` direct child of the grid, exactly one `button[@data-video-id]` with `aria-label` equal to the title, zero `h1..h6`, zero `p`, and `normalize-space()` of the article is `''` (no visible text at all).
5. `test_title_is_escaped_in_the_label` - title `Fish & <b>Chips</b>` -> the attribute is the decoded plain text and the HTML contains no raw `<b>`.
6. `test_poster_renders_an_image_only_when_there_is_one` - with poster: one `img.videos-item__img` with `alt=""`; without: zero `img`.
7. `test_categories_are_slugs` - `data-video-categories` equals the term slug; no term -> `""`.
8. `test_preview_source` - table: file only -> `data-preview-src` = file URL and no `data-preview-clip`; file and clip -> clip URL and `data-preview-clip` present; embed only (meta `cptdp_embed_url`) -> no `data-preview-src`; clip with mime `image/jpeg` ignored -> falls back to the file or nothing. Also call `devportfolio_video_preview_url()` directly for the same table.
9. `test_template_places_the_grid_after_the_filter` - read `templates/archive-video.html`; `strpos` of `wp:devportfolio/video-grid` is greater than that of `wp:devportfolio/document-categories`, and both sit before `</main>`.
10. `test_css_contract` - read `blocks/video-grid/style.css` as text; assert it contains `repeat(auto-fill, minmax(clamp(104px, 72px + 8vw, 180px), 1fr))`, does not contain `auto-fit`, contains `--videos-gap: 6px`, `--videos-gap: 4px` inside `@media (max-width: 620px)`, `aspect-ratio: 9 / 16`, `border: 1.5px solid`, `border-radius: 4px`, `object-fit: cover`, `height: 3px`, `prefers-reduced-motion: reduce`; and has no `.videos-item:hover` selector.
11. `test_script_contract` - `view.js` contains `devportfolio:category-change` and `./grid.js` and `./preview.js` and no `requestAnimationFrame`; `preview.js` contains `pointerenter` and `pointerleave` and no `focus`.
12. `test_page_css_is_loaded_on_the_video_archive` - same pattern as `test-page-styles.php`: `go_to( get_post_type_archive_link( 'video' ) )`, run `wp_enqueue_scripts`, assert the style is enqueued (this already passes; it pins the page width for this page).

Run only this file while writing it: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter Devportfolio_Test_Video_Grid`.

## 6. Phases and gates

**Commands used by every gate** (run from the repo root):
- Theme PHP: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit`
- Plugin PHP: `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit`
- Video grid JS: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-grid 'node --test tests/*.test.mjs'`
- Other JS (must stay green): the `node --test` commands for `document-modal`, `shared`, `collateral-grid` and `collateral-modal` in AGENTS.md.
- If the PHP suites cannot connect, run the one-time setup from AGENTS.md (Tests) first.

**Phase 0 - Baseline.** `git checkout main && git checkout -b feat/DPV-006-007-008-Video-Grid`. Run both PHP suites and the Node suites once; note totals (AGENTS.md: theme 523). Gate: all green before any edit.

**Phase 1 - Grid and thumbnail (DPV-006, DPV-008).** Files 5.1, 5.2, 5.3, 5.4, 5.5, 5.7 and 5.8 (write `preview.js` from 5.6 now too, since `view.js` imports it), and the tests `grid.test.mjs` and test cases 1-7, 9, 10 of `test-video-grid.php` from 5.9 (order, markup, poster, categories, empty, template, CSS grid contract) plus `grid.test.mjs`. Gate: theme suite and `node --test` in the block dir pass; `ddev wp cptdp seed videos` then load `/videos/`: tiles are 9:16 posters, filter pills hide and show items, no horizontal scroll at 320px.

**Phase 2 - Hover preview (DPV-007).** Nothing new to write (it is already in 5.3, 5.6, 5.7); add `preview.test.mjs` and test cases 8, 11, 12. Gate: Node and theme suites pass.

**Phase 3 - Real-browser check** (no runner exists; headless Chrome over CDP as in earlier tickets, or manual). Seed data needs one video of each shape (9:16, 1:1, 16:9), one with no poster, one with an uploaded file and a clip, one with an uploaded file and **no** clip, one embed only (the seeder creates embed-only videos with no files: upload two files by hand in wp-admin). Check:
1. Hover 400 ms then leave: nothing plays (network: no video request).
2. Hover 800 ms: plays muted, 3px bar fills over 6 s and restarts, mute badge top right, tile size and position unchanged (compare `getBoundingClientRect()` before/during).
3. Move to another tile: the first stops, the second starts after its own 800 ms.
4. Embed-only tile: never plays. Tab to a tile: never plays, 2px outline visible with 3px gap.
5. Touch emulation: no preview. `prefers-reduced-motion` emulation: no preview.
6. Square and wide posters are centre-cropped; no-poster tile is plain and the same size.
7. Columns at widths 320, 390, 600, 768, 1024, 1440, 1920 (see Q2); no horizontal scroll; last row left-aligned (e.g. 11 videos).
8. Change a video's Order in wp-admin: grid position changes after reload.

**Phase 4 - Docs.** Update `AGENTS.md` (Overview of the theme architecture: new `blocks/video-grid/` entry; the Videos template line now says "title, filter, then `devportfolio/video-grid`"; theme test total; mention `tests/test-video-grid.php`; Node tests command). No plugin version change.

**Phase 5 - Final gate.** Both PHP suites and every `node --test` folder pass; `git status` shows only the files above; no untracked plan files of other tickets are staged.

## 7. Conventional commits (only when asked)

Branch `feat/DPV-006-007-008-Video-Grid`; PR title `feat(theme): add video grid, thumbnails and hover preview` (<= 72 chars). Subjects are lowercase, imperative, <= 72 chars; footers `Refs: DPV-006, DPV-007, DPV-008` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths only.

1. `feat(theme): add the video grid block with poster thumbnails` - `blocks/video-grid/{block.json,render.php,style.css,grid.js,view.js}`, `functions.php`, `templates/archive-video.html`. Refs DPV-006, DPV-008.
2. `test(theme): cover the video grid and its category filter` - `tests/test-video-grid.php` (grid cases), `blocks/video-grid/tests/grid.test.mjs`. Refs DPV-006, DPV-008.
3. `feat(theme): preview a video on desktop hover` - `blocks/video-grid/preview.js`, `style.css` (bar, mute, video), `view.js`, `render.php` preview attribute. Refs DPV-007.
4. `test(theme): cover the video hover preview` - `tests/test-video-grid.php` (preview cases), `blocks/video-grid/tests/preview.test.mjs`. Refs DPV-007.
5. `docs(agents): document the video grid and hover preview` - `AGENTS.md`. Refs DPV-006, DPV-007, DPV-008.
6. `docs(plans): add the DPV-006-007-008 video grid plan` - this file.

Every commit leaves both suites green (commit 1 needs the grid-only tests in the same commit if the suite would otherwise lack coverage; squash 1+2 if so).

## 8. Open questions (resolve before or during Phase 1; none block the plan)

- **Q1: DPV-025** (listed as a dependency of DPV-006 and DPV-007) is not defined in this repo or any plan. Assumed to be a data/backend or loading ticket (poster/preview availability). If it defines a loading skeleton or a field this plan does not read, add it to 5.3/5.4 before Phase 1.
- **Q2: 1024px column count.** With the formula given (min tile = `clamp(104px, 72px + 8vw, 180px)` = 153.9px at 1024) and only the shared page gutter (3vw), the arithmetic gives **6** columns at 1024px, but the story expects **5**. At 1024 the shared shell also has the theme's root side padding, which may bring it to 5. Measure in Phase 3 step 7; if it is 6, **do not change the formula silently**: report the numbers to the owner (the story's table and the story's formula disagree at that width). The other widths (320 -> 2, 390 -> 3, 600 -> 4, 768 -> 5, 1440 -> 7) are consistent with the formula.
- **Q3: lo-fi vs story differences.** The lo-fi (v2.12) still shows page blur, a sliding outline and a 1400px cap; the story says only bar + badge and the shared 1440px width. The plan follows the **story**.
- **Q4: pre-0.30.0 videos have no preview clip.** D4 falls back to the original file (can be 100 MB+, fetched only on hover and only the first seconds via range requests). If that is unwanted, drop the fallback and previews appear only after the video is re-saved in wp-admin (`ddev wp` has no backfill for the clip, it is made by the admin's browser).

## 9. Follow-ups (not this ticket)

DPV-026 modal (binds to `[data-video-id]`); DPV-027 empty state; extract a shared `matchesCategory` and the "filter on `devportfolio:category-change`" wiring once a third grid needs it; mobile long-press preview (lo-fi E9).

## 10. As-built

**Status:** implemented on `feat/DPV-006-007-008-Video-Grid`, one commit per the list below. Theme suite 540 (523 + 17 in `Devportfolio_Test_Video_Grid`), plugin suite 906 (12 skipped, unchanged), Node: `blocks/video-grid` 6, `collateral-grid` 18, `collateral-modal` 24, `shared` 158, `document-modal` 16, all green.

**Deviations from sections 1 to 5**

1. **Page blur built (criterion 8 said no).** The user asked for the lo-fi B2 blur. `preview.js` sets `body.is-videos-previewing` while a preview plays; `style.css` draws a fixed `body::after` veil (7px `backdrop-filter`, 40% white, 200 ms fade, `pointer-events: none`), and the playing tile sits above it (`z-index: 2`). The header element is not blurred (it traps the fixed phone menu, see AGENTS.md).
2. **Switching keeps the veil (B2 "Switch", "Gaps").** The plan's version cleared the page on every tile change and waited 800 ms again. Now one pending start (`pending`) is cancelled by any tile enter; entering a tile while a preview is playing starts the new one at once; a gap between tiles keeps the current tile and veil; leaving the grid (`pointerleave` on the grid element) ends the preview and the veil.
3. **View Transitions added for the filter.** Each tile carries `view-transition-name: videos-item-<id>` inline (as the shelf does), `view-transition-class: videos-item`, and group/old/new rules (0.4 s, none with reduced motion). The category block already runs the filter inside `startViewTransition()`, so no new transition code is needed. The preview switch keeps the existing 120 ms opacity fade (a View Transition between previews stays out of scope, section 2).
4. **Q2 measured: 1024px gives 6 columns, not 5.** Headless Chrome at 1024px: 6 columns, 155px tiles, no horizontal scroll. Formula left unchanged, decision for the owner. Other widths match: 320→2, 390→3, 600→4, 768→5, 1440→7, 1920→7.
5. **Test harness.** (a) The WordPress test library removes the hooks after each test, so `set_up()` calls `devportfolio_memo_add_hooks()`; without it the memo kept a stale order after a reorder. (b) Contract assertions match code forms (`requestAnimationFrame(`, `'focus`) because the plan's comments contain the words.
6. **Commit grouping.** `view.js` imports `preview.js`, and the preview CSS shares `style.css` with the grid. Section 7 commits 1 and 3 are therefore one commit (`blocks/video-grid/` as a whole), so no commit is left importing a missing module. Commits 2, 4 (tests) and 5, 6 (docs) are as listed.

**Checked in headless Chrome over CDP (not in the suites):** column counts at seven widths; hover: 400 ms and leave starts nothing, 800 ms starts with the veil and an unchanged tile rect, the progress variable advances, switching while veiled is immediate, a gap keeps the playing tile, embed-only never plays, a 6 s loop on an uploaded file, leaving clears; category pill click starts a view transition and filters correctly. Screenshots in the scratchpad (see the PR description).

**Not verified:** touch and reduced-motion emulation in a browser; keyboard focus outline; wp-admin reorder in a browser (covered by PHP test); the visual smoothness of the filter transition (checked that it starts and names exist, not by eye); Q1 (DPV-025 not defined in the repo).

**Test data:** I seeded the videos and categories into the local dev DB and attached a generated clip for the preview checks, then removed the clip links. The six seeded `Test Vertical Video` posts were removed by the user and then by me; the dev DB has the five real videos only.

**Commits (actual):**
1. `feat(theme): add the video grid block with poster thumbnails` (includes the preview, veil and transitions; see deviation 6)
2. `test(theme): cover the video grid, preview and category transition`
3. `docs(agents): document the video grid and hover preview`
4. `docs(plans): add the DPV-006-007-008 video grid plan`
