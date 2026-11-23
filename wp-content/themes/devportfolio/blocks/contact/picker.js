/**
 * The calendar and the times of the Contact scheduler (DPH-025), used twice: in the desktop card (its model is the
 * card's state) and in the phone modal (its model is a draft the modal commits on "Confirm date & time").
 *
 * `model` is `{ date, time, month }`; the picker writes to it and calls `onChange()`. Rebuilding the DOM on every
 * change is cheap here (about 40 buttons), and the focused button is found again by its `data-key`.
 */
import { el } from '../shared/dom.js';
import { monthGrid, monthOf, shiftMonth, monthInRange, parseIso } from './calendar.js';

const text = ( node, value ) => {
	node.textContent = value;
	return node;
};

export function createPicker( { root, model, config, strings, locale, zone, onChange, timeLabel } ) {
	const utc = ( y, m, d ) => new Date( Date.UTC( y, m - 1, d ) );
	const monthFormat = new Intl.DateTimeFormat( locale, { month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const dayFormat = new Intl.DateTimeFormat( locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const weekdayFormat = new Intl.DateTimeFormat( locale, { weekday: 'short', timeZone: 'UTC' } );
	let notice = '';

	function button( className, key, label, content ) {
		const node = el( 'button', className, { type: 'button', 'data-key': key } );
		if ( label ) {
			node.setAttribute( 'aria-label', label );
		}
		node.textContent = content;
		return node;
	}

	function calendar() {
		const { y, m } = model.month;
		const box = el( 'div', 'contact-picker__cal' );

		const bar = el( 'div', 'contact-picker__bar' );
		const prev = button( 'contact-picker__nav', 'prev', strings.monthPrev, '‹' );
		const next = button( 'contact-picker__nav', 'next', strings.monthNext, '›' );
		prev.disabled = ! monthInRange( shiftMonth( model.month, -1 ), config.first, config.last );
		next.disabled = ! monthInRange( shiftMonth( model.month, 1 ), config.first, config.last );
		prev.addEventListener( 'click', () => {
			model.month = shiftMonth( model.month, -1 );
			render();
		} );
		next.addEventListener( 'click', () => {
			model.month = shiftMonth( model.month, 1 );
			render();
		} );
		const title = text( el( 'p', 'contact-picker__month', { 'aria-live': 'polite' } ), monthFormat.format( utc( y, m, 1 ) ) );
		bar.append( prev, title, next );

		const heads = el( 'div', 'contact-picker__weekdays', { 'aria-hidden': 'true' } );
		for ( let i = 0; i < 7; i++ ) {
			heads.append( text( el( 'span' ), weekdayFormat.format( utc( 2026, 1, 5 + i ) ) ) ); // 5 Jan 2026 is a Monday.
		}

		const grid = el( 'div', 'contact-picker__grid', { role: 'group', 'aria-label': monthFormat.format( utc( y, m, 1 ) ) } );
		monthGrid( model.month, config.first, config.last ).flat().forEach( ( cell ) => {
			if ( ! cell ) {
				grid.append( el( 'span', 'contact-picker__pad' ) );
				return;
			}
			const parts = parseIso( cell.iso );
			const day = button( 'contact-picker__day', `d:${ cell.iso }`, dayFormat.format( utc( parts.y, parts.m, parts.d ) ), String( cell.day ) );
			day.disabled = ! cell.selectable;
			day.setAttribute( 'aria-pressed', cell.iso === model.date ? 'true' : 'false' );
			day.addEventListener( 'click', () => {
				model.date = cell.iso;
				model.time = '';
				notice = '';
				render();
				onChange();
			} );
			grid.append( day );
		} );

		const legend = el( 'ul', 'contact-picker__legend' );
		legend.append(
			text( el( 'li', 'contact-picker__legend-free' ), strings.legendAvailable ),
			text( el( 'li', 'contact-picker__legend-selected' ), strings.legendSelected )
		);

		box.append( bar, heads, grid, legend );
		return box;
	}

	function times() {
		const box = el( 'div', 'contact-picker__times' );

		if ( ! model.date ) {
			box.append( text( el( 'p', 'contact-picker__hint' ), strings.timesHint ) );
		} else {
			box.append( text( el( 'p', 'contact-picker__times-title' ), strings.timesTitle ) );
			const list = el( 'div', 'contact-picker__slots', { role: 'group', 'aria-label': strings.timesTitle } );
			config.slots.forEach( ( slot ) => {
				const pill = button( 'contact-picker__time', `t:${ slot }`, '', timeLabel ? timeLabel( model.date, slot ) : slot );
				pill.setAttribute( 'aria-pressed', slot === model.time ? 'true' : 'false' );
				pill.addEventListener( 'click', () => {
					model.time = slot;
					render();
					onChange();
				} );
				list.append( pill );
			} );
			box.append( list );
		}

		box.append( text( el( 'p', 'contact-picker__zone' ), strings.timezone.replace( '{zone}', zone ) ) );

		if ( notice ) {
			box.append( text( el( 'p', 'contact__error', { role: 'alert' } ), notice ) );
		}
		return box;
	}

	function render() {
		if ( ! model.month ) {
			model.month = monthOf( model.date || config.first );
		}
		const active = root.contains( document.activeElement ) ? document.activeElement.dataset.key : '';
		root.replaceChildren( el( 'div', 'contact-picker' ) );
		root.firstChild.append( calendar(), times() );
		if ( active ) {
			const again = root.querySelector( `[data-key="${ active }"]` );
			if ( again && ! again.disabled ) {
				again.focus();
			}
		}
	}

	return {
		render,
		/** A line under the times ("That time is no longer available"); cleared by the next pick. */
		setNotice( message ) {
			notice = message;
		},
	};
}
