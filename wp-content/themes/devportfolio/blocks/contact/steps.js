/**
 * The steps of the Contact card (DPH-025 to DPH-027). Pure: validation, crumb states, the next step and the
 * summary rows. Every word comes from the `strings` the server hands over; dates and times are time.js's job.
 */
export const LAST_STEP = 4;

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Errors for one step as `{ field: message }`; empty when the step is fine. Fields: slot, name, email, services.
 */
export function validateStep( step, data, strings ) {
	const errors = {};

	if ( step === 1 && ! ( data.date && data.time ) ) {
		errors.slot = strings.slotRequired;
	}
	if ( step === 2 ) {
		if ( ! data.name.trim() ) {
			errors.name = strings.nameRequired;
		}
		const email = data.email.trim();
		if ( ! email ) {
			errors.email = strings.emailRequired;
		} else if ( ! EMAIL.test( email ) ) {
			errors.email = strings.emailInvalid;
		}
	}
	if ( step === 3 && data.services.length === 0 ) {
		errors.services = strings.needRequired;
	}

	return errors;
}

/** 'done' | 'current' | 'todo' for the four crumbs. Once sent, all four are done. */
export function crumbStates( current, finished ) {
	return [ 1, 2, 3, 4 ].map( ( n ) => {
		if ( finished || n < current ) {
			return 'done';
		}
		return n === current ? 'current' : 'todo';
	} );
}

/** Next goes to `returnTo` while the visitor is editing from the summary, else one step on. */
export function nextStep( current, returnTo ) {
	if ( returnTo && current < returnTo ) {
		return returnTo;
	}
	return Math.min( LAST_STEP, current + 1 );
}

export function prevStep( current ) {
	return Math.max( 1, current - 1 );
}

/**
 * The summary card: `{ key, step, label, value }` per row. `step` is where its Edit link goes.
 *
 * @param {Object} data      `{ name, email, services: string[], message }`
 * @param {Array}  services  `[ { id, title } ]` in display order
 * @param {string} timeValue The chosen time, already worded for this visitor (see time.js and `describe()` in view.js)
 */
export function summaryRows( data, services, strings, timeValue ) {
	const help = services.filter( ( service ) => data.services.includes( service.id ) ).map( ( service ) => service.title );

	return [
		{ key: 'time', step: 1, label: strings.summaryTime, value: timeValue },
		{ key: 'name', step: 2, label: strings.summaryName, value: data.name.trim() },
		{ key: 'email', step: 2, label: strings.summaryEmail, value: data.email.trim() },
		{ key: 'help', step: 3, label: strings.summaryHelp, value: help.join( ', ' ) },
		{ key: 'message', step: 3, label: strings.summaryMessage, value: data.message.trim() || strings.noMessage },
	];
}
