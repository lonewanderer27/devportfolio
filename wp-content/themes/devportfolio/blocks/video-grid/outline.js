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
