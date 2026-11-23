/**
 * Home Contact scheduler (DPH-025 to DPH-028).
 *
 * State lives in memory only: nothing the visitor types is stored in the browser. On desktop the card is always
 * shown. On phones (800px and below) the four steps are one modal: it opens from "Check availability" and holds
 * the same header (✕ and the title) and footer (Back and Next) on every step. On phones the day and time are a draft
 * until Next on step 1; ✕, Esc and Back discard it. On desktop the draft mirrors the chosen slot at once.
 */
import { el } from '../shared/dom.js';
import { createSheetHistory } from '../shared/sheet-back.js';
import { createPicker } from './picker.js';
import { validateStep, crumbStates, nextStep, prevStep, summaryRows, LAST_STEP } from './steps.js';
import { localMs, formatInstant, formatClock } from './time.js';

const MOBILE_QUERY = '(max-width: 800px)';

const text = ( node, value ) => {
	node.textContent = value;
	return node;
};

function init( root ) {
	const payload = root.querySelector( '.contact__data' );
	if ( ! payload ) {
		return;
	}

	const config = JSON.parse( payload.textContent );
	const strings = config.strings;
	const locale = document.documentElement.lang || 'en';
	const mobile = window.matchMedia( MOBILE_QUERY );
	const visitorZone = ( new Intl.DateTimeFormat().resolvedOptions().timeZone ) || '';

	/** "Friendly" zone name for the visitor: the city of their zone ("Manila"), or '' when unknown. */
	const zone = visitorZone ? visitorZone.split( '/' ).pop().replace( /_/g, ' ' ) : '';

	/** The chosen time in the visitor's own time zone, e.g. "Tue 6 Oct, 4:30 pm (Manila time)". */
	function whenText( date, time ) {
		const when = formatInstant( localMs( date, time ), locale );
		return zone ? strings.slotLocal.replace( '{when}', when ).replace( '{zone}', zone ) : when;
	}

	/** A time pill: Dave's offered time ("9:00 am"). The visitor's own zone is the one the times are read in. */
	function timeLabel( date, slot ) {
		return formatClock( slot, locale );
	}

	const panels = [ ...root.querySelectorAll( '[data-contact-panel]' ) ];
	const crumbs = root.querySelector( '[data-contact-crumbs]' );
	const slotText = root.querySelector( '[data-contact-slot-text]' );
	const summary = root.querySelector( '[data-contact-summary]' );
	const formError = root.querySelector( '[data-contact-form-error]' );
	const sendButton = root.querySelector( '[data-contact-send]' );
	const nextOne = panels[ 0 ].querySelector( '[data-contact-next]' );
	const thanks = root.querySelector( '[data-contact-thanks]' );
	const nameInput = root.querySelector( '[data-contact-field="name"]' );
	const emailInput = root.querySelector( '[data-contact-field="email"]' );
	const messageInput = root.querySelector( '[data-contact-field="message"]' );
	const honeypot = root.querySelector( '[data-contact-honeypot]' );
	const serviceBoxes = [ ...root.querySelectorAll( '[data-contact-service]' ) ];
	const openButton = root.querySelector( '[data-contact-open]' );

	const state = { step: 1, returnTo: 0, date: '', time: '', name: '', email: '', services: [], message: '', sent: false, sending: false };
	// The day and time the calendar shows. On phones it is kept until Next; on desktop it always equals the chosen slot.
	const draft = { date: '', time: '', month: null };

	const isModal = () => root.classList.contains( 'is-modal' );

	// ---- steps ---------------------------------------------------------------------------------------------

	function renderCrumbs() {
		const labels = [ strings.crumbAvailability, strings.crumbAbout, strings.crumbNeed, strings.crumbConfirm ];
		const states = crumbStates( state.step, state.sent );

		crumbs.replaceChildren(
			...states.map( ( status, index ) => {
				const n = index + 1;
				const tappable = status === 'done' && ! state.sent;
				const item = el( 'li', `contact__crumb is-${ status }` );
				const inner = tappable ? el( 'button', 'contact__crumb-btn', { type: 'button' } ) : el( 'span', 'contact__crumb-btn' );
				const label = text( el( 'span', 'contact__crumb-label' ), labels[ index ] );

				if ( tappable ) {
					inner.addEventListener( 'click', () => {
						state.returnTo = 0;
						goTo( n );
					} );
				}
				if ( status === 'current' ) {
					inner.setAttribute( 'aria-current', 'step' );
					label.append( text( el( 'span', 'contact__sr' ), ` (${ strings.stepOf.replace( '{n}', n ).replace( '{total}', LAST_STEP ) })` ) );
				}
				inner.append( text( el( 'span', 'contact__crumb-num' ), String( n ) ), label );
				item.append( inner );
				return item;
			} )
		);
	}

	function renderSummary() {
		summary.replaceChildren(
			...summaryRows( state, config.services, strings, whenText( state.date, state.time ) ).map( ( row ) => {
				const line = el( 'div', 'contact__summary-row' );
				const value = text( el( 'dd' ), row.value );
				const edit = text( el( 'button', 'contact__link', { type: 'button', 'data-edit': String( row.step ), 'aria-label': `${ strings.edit } ${ row.label }` } ), strings.edit );
				value.append( edit );
				line.append( text( el( 'dt' ), row.label ), value );
				return line;
			} )
		);
	}

	function show( n, focus ) {
		// Forward slides the new step in from the right, Back from the left (style.css reads data-direction).
		root.dataset.direction = n < state.step ? 'back' : 'forward';
		state.step = n;
		if ( n === LAST_STEP ) {
			state.returnTo = 0;
			formError.textContent = '';
			renderSummary();
		}
		panels.forEach( ( panel, index ) => {
			panel.hidden = index + 1 !== n;
		} );
		root.dataset.step = String( n );
		renderCrumbs();
		if ( focus ) {
			panels[ n - 1 ].querySelector( '[data-contact-title]' ).focus();
		}
	}

	function goTo( n ) {
		if ( state.sent ) {
			return;
		}
		if ( n === 1 && mobile.matches ) {
			openModal();
			return;
		}
		show( n, true );
	}

	// ---- the modal (phones) --------------------------------------------------------------------------------

	// Created now, while the module runs: the history listener must be registered before the router's.
	const history = createSheetHistory( { isOpen: isModal, onBack: () => closeModal( false ) } );

	/** Opens the modal at step 1 with the chosen time as the draft (or the thank-you, once sent). */
	function openModal() {
		if ( ! isModal() ) {
			root.classList.add( 'is-modal' );
			history.push();
		}
		draft.date = state.date;
		draft.time = state.time;
		draft.month = null;
		picker.render();
		sync();
		if ( ! state.sent ) {
			show( 1, true );
		}
	}

	/** Closes the modal. `fromButton` is false when Back already left the history entry. */
	function closeModal( fromButton = true ) {
		if ( ! isModal() ) {
			return;
		}
		root.classList.remove( 'is-modal' );
		history.pop();
		if ( fromButton ) {
			openButton.focus();
		}
	}

	// ---- errors --------------------------------------------------------------------------------------------

	function setError( field, message ) {
		const line = root.querySelector( `[data-contact-error="${ field }"]` );
		if ( line ) {
			line.textContent = message || '';
		}
		const control = root.querySelector( `[data-contact-field="${ field }"]` );
		if ( control && control.tagName !== 'FIELDSET' ) {
			if ( message ) {
				control.setAttribute( 'aria-invalid', 'true' );
			} else {
				control.removeAttribute( 'aria-invalid' );
			}
		}
	}

	function focusField( field ) {
		const control = root.querySelector( `[data-contact-field="${ field }"]` );
		const target = control && control.tagName === 'FIELDSET' ? control.querySelector( 'input' ) : control;
		if ( target ) {
			target.focus();
		}
	}

	/** Step 1 commits the draft to the chosen time, then the step is checked like any other. */
	function advance() {
		if ( state.step === 1 ) {
			setSlot( draft.date, draft.time );
		}
		const errors = validateStep( state.step, state, strings );
		[ 'name', 'email', 'services' ].forEach( ( field ) => setError( field, errors[ field ] ) );

		const first = Object.keys( errors )[ 0 ];
		if ( first ) {
			focusField( first );
			return;
		}
		if ( state.step === LAST_STEP ) {
			send();
			return;
		}
		goTo( nextStep( state.step, state.returnTo ) );
	}

	// ---- sending -------------------------------------------------------------------------------------------

	function setBusy( busy ) {
		state.sending = busy;
		sendButton.disabled = busy;
		sendButton.textContent = busy ? strings.sending : strings.confirm;
	}

	function finish() {
		state.sent = true;
		root.classList.add( 'is-sent' );
		panels.forEach( ( panel ) => {
			panel.hidden = true;
		} );
		renderCrumbs();
		const title = thanks.querySelector( '[data-contact-thanks-title]' );
		text( title, strings.thanks.replace( '{name}', state.name.trim() ) );
		text( thanks.querySelector( '[data-contact-thanks-time]' ), strings.thanksTime.replace( '{date}', whenText( state.date, state.time ) ) );
		thanks.hidden = false;
		title.focus();
	}

	function failed( status, body ) {
		const field = body && body.data && body.data.field;

		if ( status === 422 && ( field === 'date' || field === 'time' ) ) {
			setSlot( '', '' );
			picker.setNotice( strings.slotGone );
			picker.render();
			goTo( 1 );
			return;
		}
		if ( status === 422 && ( field === 'name' || field === 'email' || field === 'services' ) ) {
			goTo( field === 'services' ? 3 : 2 );
			setError( field, body.message );
			focusField( field );
			return;
		}
		formError.textContent = status === 429 ? strings.tooMany : strings.sendError;
	}

	async function send() {
		if ( state.sending ) {
			return;
		}
		setBusy( true );
		formError.textContent = '';
		try {
			const response = await fetch( config.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					date: state.date,
					time: state.time,
					timezone: visitorZone,
					name: state.name.trim(),
					email: state.email.trim(),
					services: state.services,
					message: state.message.trim(),
					website: honeypot.value,
				} ),
			} );
			if ( response.ok ) {
				finish();
			} else {
				failed( response.status, await response.json().catch( () => ( {} ) ) );
			}
		} catch ( error ) {
			formError.textContent = strings.sendError;
		} finally {
			setBusy( false );
		}
	}

	// ---- the chosen time -----------------------------------------------------------------------------------

	/** Everything that follows the chosen slot: the time line, the layout class, and Next on step 1 (the draft). */
	function sync() {
		const has = Boolean( state.date && state.time );
		root.classList.toggle( 'has-slot', has );
		slotText.textContent = has ? whenText( state.date, state.time ) : '';
		nextOne.disabled = ! ( draft.date && draft.time );
	}

	/** Sets the chosen slot (and the draft to match). */
	function setSlot( date, time ) {
		state.date = date;
		state.time = time;
		draft.date = date;
		draft.time = time;
		sync();
	}

	const picker = createPicker( {
		root: root.querySelector( '[data-contact-picker]' ),
		model: draft,
		config,
		strings,
		locale,
		onChange: () => {
			// Desktop: the card's choice is the chosen time at once. Phones: only on Next.
			if ( ! mobile.matches ) {
				setSlot( draft.date, draft.time );
			} else {
				sync();
			}
		},
		zone,
		timeLabel,
	} );

	// ---- wiring --------------------------------------------------------------------------------------------

	root.querySelector( '[data-contact-close]' ).addEventListener( 'click', () => closeModal() );
	openButton.addEventListener( 'click', openModal );
	root.querySelector( '[data-contact-change]' ).addEventListener( 'click', openModal );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && isModal() ) {
			closeModal();
		}
	} );

	mobile.addEventListener( 'change', ( event ) => {
		if ( ! event.matches ) {
			closeModal( false ); // The desktop card is always shown: nothing to close.
		}
	} );

	panels.forEach( ( panel ) => {
		panel.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			advance();
		} );
		panel.querySelectorAll( '[data-contact-back]' ).forEach( ( back ) => {
			back.addEventListener( 'click', () => {
				state.returnTo = 0;
				if ( state.step === 1 ) {
					closeModal();
					return;
				}
				goTo( prevStep( state.step ) );
			} );
		} );
	} );

	summary.addEventListener( 'click', ( event ) => {
		const edit = event.target.closest( '[data-edit]' );
		if ( edit ) {
			state.returnTo = LAST_STEP;
			goTo( Number( edit.dataset.edit ) );
		}
	} );

	nameInput.addEventListener( 'input', () => {
		state.name = nameInput.value;
		setError( 'name', '' );
	} );
	emailInput.addEventListener( 'input', () => {
		state.email = emailInput.value;
		setError( 'email', '' );
	} );
	messageInput.addEventListener( 'input', () => {
		state.message = messageInput.value;
	} );
	serviceBoxes.forEach( ( box ) => {
		box.addEventListener( 'change', () => {
			state.services = serviceBoxes.filter( ( other ) => other.checked ).map( ( other ) => other.value );
			setError( 'services', '' );
		} );
	} );

	picker.render();
	sync();
	show( 1, false );
	root.dataset.ready = 'true';
}

document.querySelectorAll( '[data-contact]' ).forEach( init );
