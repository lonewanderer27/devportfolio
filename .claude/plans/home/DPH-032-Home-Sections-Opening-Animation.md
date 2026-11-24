# Plan: Home sections opening animation (scroll reveal)

Ticket **DPH-032** (new, Low, Homepage; builds on the reduced-motion rule of **DPH-030**). Every Home section **after the hero** fades in and rises a few pixels, once, when it first scrolls into view. Sections already on screen at load animate on the first paint. Scrolling back never replays it. The hero keeps its own entrance (DPH-001, `blocks/hero/style.css`) and is not touched.

Branch: `feat/DPH-032-Home-Sections-Opening-Animation`. Touches the **theme only** (`wp-content/themes/devportfolio/`): `theme.json`, one new stylesheet, one new script, `functions.php`, `templates/front-page.html`, seven `render.php` files, two existing test files (one assertion each follows the new class), one new test file. Plus `AGENTS.md` and the stories CSV. No plugin change, no plugin version bump.

**Implementer rule:** every item under "Facts" was checked in the repo on the day this plan was written. If the code does not match a Fact (an anchor string is missing, or appears more than once where the plan says once), **stop and report**; do not improvise. Do not change anything that is not listed here.

## Story (to add to the stories CSV, step D2)

"As a visitor, I want each Home section after the hero to fade in gently as I reach it, so that the page feels alive without getting in my way."

1. Each section below the hero (stats, What I do, Selected work, What clients say, Software tools, Contact, footer) fades in and rises about 14px over about half a second, once, the first time it scrolls into view. Items of a section that arrive together follow each other by a short step.
2. Sections already on screen when the page opens animate straight away. Scrolling back up never replays it.
3. Nothing moves with "reduce motion" on, without JavaScript, in print, or in an old browser: everything shows at its final place.
4. Anchor links (`/#services`, `/#selected-work`, `/#testimonials`, `/#contact`) land exactly where they did before, and nothing on the page shifts.
5. Going Back from an archive to Home keeps the Selected work title morph.

## Out of scope

- **The hero** (`blocks/hero/`): its CSS-only entrance stays exactly as it is (its own easing `cubic-bezier(.2, .7, .2, 1)` is not switched to the new token).
- **Every other page** (Documents, Videos, Collaterals, archives, singles, 404, search). The footer block gets the class on every page, but it only does something on Home (the class on `<html>` is only added there).
- The Selected work rotation, the testimonial rotation, the software tools lift, the contact steps' own motion, the modals' own motion, the nav spy (`assets/js/nav-spy.js`). None of their files change except the one-word class additions listed in Phase B.
- Re-reading the reduced-motion preference after load for a page already on screen (the CSS query does that by itself: turning reduced motion on shows every hidden element at once).
- A browser test runner (there is none in the repo; the browser check is manual, Phase D1).

## Facts (checked)

1. `templates/front-page.html` holds, in order: the header part; `<main class="wp-block-group documents-page home-page">`; `<!-- wp:devportfolio/hero /-->`; `<!-- wp:devportfolio/stats-strip /-->`; the group `<div id="services" class="wp-block-group home-section" …>` with an `h2` "What I do", the intro paragraph, a separator and `<!-- wp:devportfolio/service-list /-->`; the group `<div id="selected-work" class="wp-block-group home-section" …>` with an `h2` "Selected work", `<!-- wp:devportfolio/selected-work /-->` and then **the three viewer blocks `document-modal`, `collateral-modal`, `video-modal` as direct children of that group**; `<!-- wp:devportfolio/testimonials /-->`; the group `<div id="software-tools" class="wp-block-group home-section" …>` with an `h2` "Software tools" and `<!-- wp:devportfolio/software-tools /-->`; `<!-- wp:devportfolio/contact /-->`; `</main>`; the footer part. The three `h2` comment lines are identical (`<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->`), so every heading edit below includes the following `h2` line to be unique. Lines are indented with tabs (two tabs inside a group).
2. **Testimonials exists on Home.** `blocks/testimonials/` (DPH-022/023, plan `DPH-022-023-024-Home-Testimonials-Software-Tools.md`) renders `<section class="wp-block-devportfolio-testimonials testimonials home-section" id="testimonials" aria-labelledby="testimonials-heading">` with an `h2#testimonials-heading.testimonials__heading`, a `div.testimonials__stage` (the slides) and, with two or more quotes, `div.testimonials__dots` (with the `hidden` attribute until its script runs). `blocks/software-tools/` (DPH-024) renders `ul.software-tools[data-software-tools]`. Neither block is described in the AGENTS.md theme bullets (only the `#testimonials` anchor is mentioned); that is not fixed here.
3. `blocks/contact/render.php` renders `section.contact.home-section#contact` whose direct children are `h2#contact-heading.contact__heading`, `p.contact__lead`, `button.contact__open[data-contact-open]`, `div.contact__card[data-contact-card]` (one tab of indentation in the file) and a `noscript`. In `blocks/contact/style.css`, at 800px and below, `.contact__card` is `display: none` and `.contact.is-modal .contact__card` is a **`position: fixed` full-screen modal** with its own `animation: contact-modal-in 280ms ease-out both` (inside `@media (prefers-reduced-motion: no-preference)`); `.is-modal` is used nowhere else in the theme. So the `section.contact` itself must never be animated, and the card must be left alone while it is the modal.
4. Selected work: `blocks/selected-work/render.php` has exactly three lines `\t<article class="work-card work-card--documents">`, `\t<article class="work-card work-card--videos">`, `\t<article class="work-card work-card--collaterals">` inside `get_block_wrapper_attributes( array( 'class' => 'work' ) )`. `.work-card` sets no `transform`, `translate`, `opacity` or `animation` (it has `position: relative; overflow: hidden`); `translate` and `transform` are used only on inner elements (`.work-docs`, `.work-docs__item`, `.work-glimpse__col`). The card titles carry `view-transition-name: work-title-documents|videos|collaterals` (`blocks/selected-work/style.css`), and `assets/css/page.css` opts Home and the archives into cross-document view transitions with `@view-transition { navigation: auto; }`. The rotation clocks in `view.js` use their own IntersectionObserver; opacity does not affect it.
5. `blocks/stats-strip/render.php` line: `\t\t<li class="stats-strip__item"><b class="stats-strip__value">…`. `blocks/service-list/render.php` line: `\t\t<li class="service-list__row">`. `blocks/software-tools/render.php` line: `$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'software-tools' ) );`. `blocks/site-footer/render.php` line: `$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'site-footer' ) );`. None of these elements has an animation, `transform`, `translate` or `opacity` of its own.
6. The hero entrance (`blocks/hero/style.css`) is CSS only, inside `@media (min-width: 621px) and (prefers-reduced-motion: no-preference) {`, with `cubic-bezier(.2, .7, .2, 1)`, 0.9 to 1.1 s and 48px moves, and it moves the portrait with `translate` so its `transform` centring is untouched. `.claude/plans/design-system.html` ("Motion and accessibility") defines `ease cubic-bezier(.2,.8,.2,1)` ("everything that moves: fast start, soft landing"), `dur-fast 160 ms`, `dur-base 260 ms`, and "movement is small and slow … every effect has an off switch tied to the visitor's system settings". No motion token exists in `theme.json` yet.
7. `theme.json` `settings.custom` ends with (tabs as shown):
   ```
   			"glass": {
   				"tint": "rgb(255 255 255 / 0.8)",
   				"filter": "blur(20px) saturate(180%)"
   			}
   		},
   ```
   and `tests/test-design-system.php` asserts only `custom.edge` and `custom.glass.*` (adding a key does not break it).
8. `functions.php` has `add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_nav_spy' );` exactly once (right after `devportfolio_enqueue_nav_spy()`, which enqueues `assets/js/nav-spy.js` on the front page only). `devportfolio_hero_preload_portrait()` is hooked to `wp_head` at priority 2. Theme functions are wrapped in `if ( ! function_exists( … ) ) : … endif;`. `assets/js/` holds only `nav-spy.js`; `assets/css/` holds only `page.css`.
9. The sticky frosted header is `header.wp-block-template-part` with its blur on `::before`; it is outside `<main>` and is never marked. The Documents category glass band is not on Home.
10. Existing assertions that compare the exact opening tag of an element this plan marks (they must change in the same commit as the markup, Phase B):
    - `tests/test-contact-block.php` line 65: `$this->assertStringContainsString( '<h2 id="contact-heading" class="contact__heading">' . esc_html( $strings['heading'] ) . '</h2>', $html );`
    - `tests/test-contact-block.php` line 133: `$this->assertStringContainsString( '<p class="contact__lead">' . esc_html( $strings['lead'] ) . '</p>', $this->render() );`
    - `tests/test-testimonials.php` line 101: `$this->assertStringContainsString( '<h2 id="testimonials-heading" class="testimonials__heading">What clients say</h2>', $html );`
    Every other test that touches a marked element uses `contains(@class, …)`, a needle that still matches (`>What I do</h2>`, `<p class="has-mid-color`, `font-weight:800">Selected work</h2>`, `data-testimonials-dots hidden>`), or checks CSS only. Re-check with `grep -n "contact__heading\|contact__lead\|testimonials__heading\|testimonials__stage\|testimonials__dots\"\|contact__open\|data-contact-card\|stats-strip__item\"\|service-list__row\"\|work-card--[a-z]*\">" wp-content/themes/devportfolio/tests/*.php` before Phase B: it must list only the three lines above (plus CSS-only lines in `test-contact-block.php` 238, 239, 252 that mention `.contact__card` in selectors).
11. The front-page template is rendered from the file: `ddev wp post list --post_type=wp_template --fields=post_name --format=csv` must not list `front-page` (a Site Editor copy in the database would shadow the file). If it does, stop and report.
12. Theme tests run with the plugin's PHPUnit (AGENTS.md). Theme tests re-`require_once` `functions.php` in `set_up()`; the test library removes hooks added during a test, so hooks are asserted on the source text and functions are called directly (as `tests/test-hero.php` and `tests/test-page-styles.php` do).
13. The next free Home ticket number is **DPH-032**: the stories CSV ends at DPH-031 ("Contact - Availability Management (Future)") and DPH-030 is "Motion and Accessibility (all of Home)".

## Approach (decided)

**One tiny IntersectionObserver script, not CSS scroll-driven animations.** `animation-timeline: view()` ties the animation to the scroll position instead of time: it plays backwards when the visitor scrolls back up (the story says never replay), it cannot play "on the first paint" for sections already on screen (they would sit at whatever scroll progress they are at), the last elements on the page (the contact card's lower part, the footer) can never scroll through the whole range and would stay half transparent unless each range is tuned by hand, and browser support is recent and uneven, so an `@supports` branch would give two different behaviours. A once-only, time-based reveal needs to know "this has been seen", which is state, so a script is the honest tool. The script is about 100 lines, has no dependency, is printed **inline at the top of `<head>`** (so it cannot fail to load and the class that hides the start state is on `<html>` before the first paint), and all timing lives in CSS tokens.

How it works:

- Elements to animate carry the class **`home-reveal`** (Phase B lists every one).
- `assets/js/home-reveal.js` (inline in `<head>`, front page only) adds **`has-home-reveal`** to `<html>` only when IntersectionObserver exists and `(prefers-reduced-motion: no-preference)` matches. When parsing has finished (`readystatechange` to `interactive`, which is earlier than `DOMContentLoaded`: that one also waits for every script module on Home) it observes every `.home-reveal` with `threshold: 0.15`. The first time an element is 15% on screen it is unobserved (once only) and gets `is-revealing` plus `--home-reveal-index` (its position among the elements that arrived in the same callback, capped at 5, for the stagger). When its animation ends (or after a 2 s fallback) `is-revealing` is swapped for **`is-revealed`** and the index is removed, so no animation, opacity or translate is left on the element afterwards (an element with a running or filling animation on `opacity`/`translate` is a stacking context and a containing block; removing the class ends that).
- `assets/css/home-motion.css` (front page only) holds the only rules, all inside `@media screen and (prefers-reduced-motion: no-preference)` and all under `.has-home-reveal`: the start state (`opacity: 0; translate: 0 14px`) for a marked element that is not revealing, not revealed and has no focus inside, and the `home-reveal` keyframes (`opacity` 0 to 1, `translate` 14px to `none`) with the stagger delay.
- Tokens, once, in `theme.json` `settings.custom.motion`: `ease` `cubic-bezier(.2, .8, .2, 1)` (the design system's ease), `reveal-duration` `520ms`, `reveal-distance` `14px`, `reveal-stagger` `80ms`. CSS reads them as `var(--wp--custom--motion--…, <same value>)` (the fallback pattern `page.css` and the header use for the glass tokens).

Safety rails (all decided):

- **No hidden flash, no hidden content.** No JavaScript, no IntersectionObserver, reduced motion, print (`screen` only) or an old browser that cannot parse the script: the class is never added and nothing is hidden. A script error removes the class (`try`/`catch`). If parsing has not finished 3 s after the head script ran (a slow blocking script), the class is removed and everything shows.
- **Cross-document view transition.** When Home is entered through a view transition (Back from an archive in Chrome/Edge 126+ or Safari 18.2+; `pagereveal` with `event.viewTransition`), the script removes the class at once: the page cross-fade and the Selected work title morph are the entrance, and the new page is captured as it is drawn, so hidden cards would be captured invisible. On those visits the sections simply show; first visits, reloads and links from outside still get the reveal.
- **Never on a wrapper that holds a dialog, a fixed or sticky element or a backdrop filter** (opacity and translate on an ancestor make a containing block and a backdrop root): `<main>`, the `#services`, `#selected-work` (holds the three viewers) and `#software-tools` groups, `section.testimonials`, `section.contact` (holds the fixed phone card) and the header are never marked; their children are.
- **The phone contact card** (`.contact.is-modal > .contact__card`, a fixed modal with its own `contact-modal-in`) is excluded from both rules with `:not(.is-modal > *)`, so it opens at once with its own animation.
- **Anchors and layout shift.** Only `opacity` and `translate` change, which never move the layout: `#services`, `#selected-work`, `#testimonials`, `#contact` land where they did, and no layout shift is recorded. `translate` is a separate property from `transform`, so an element's own transforms (hover lifts) are untouched. No `will-change` (nothing is animated long or often enough to justify a permanent layer; it would also make a permanent containing block).
- **Accessibility.** `opacity: 0` keeps content in the accessibility tree (no `visibility`, `display`, `aria-hidden` or `inert`). An element with focus inside is never hidden (`:not(:focus-within)` in CSS, and the script skips the animation for it), so tabbing never lands on something invisible; focusing also scrolls it into view, which reveals it.

## Decisions made (do not block)

- **What is marked** (one class, `home-reveal`): the three section `h2` headings and the intro paragraph in the template; each stats item (`li.stats-strip__item`); each service row (`li.service-list__row`); each Selected work card (`article.work-card`); the testimonials heading, stage and dots; the software tools list (`ul.software-tools`, as one piece: its many tiles would make a long stagger); the contact heading, lead, phone button and card; the footer block (`div.site-footer`). The "What I do" separator line stays still.
- **Values:** 520 ms, 14px up, design-system ease, 80 ms stagger, at most 5 steps (the 6th and later elements of one batch share the last delay, so a batch is done in at most 920 ms). Threshold 15% of the element.
- **All widths, not only 621px and up.** The hero's phone exclusion exists because its phone layout is a different flow; the sections have no such reason, and a 14px fade is calm on phones. (Open question 1.)
- **The script is a classic inline script, not a script module**, so it runs before the first paint. It has no pure maths worth a separate module, so **there is no node test**; the PHP test reads the file's contract.
- **The footer block carries the class on every page**; only Home adds `has-home-reveal`, so it is inert elsewhere.
- Testimonial dots that are still `hidden` are never intersecting; they reveal when their script shows them. The phone-only "Check availability" button is `display: none` on desktop and likewise reveals only where it shows.

## Open questions (default applied; none blocks the work)

1. Should phones (620px and below) skip the reveal like the hero does? Default: no, they animate too. To change it later, add `and (min-width: 621px)` to the one media query and to the script's `matchMedia` string.
2. Should below-the-fold sections still reveal after an in-site navigation with a view transition? Default: no (the whole page shows at once). Doing it safely needs a render-blocking `<link rel="expect">` and per-element measuring; a later ticket if wanted.
3. Should the hero switch to the shared `--wp--custom--motion--ease` token? Default: no, the hero is out of scope.

## Phase A. Tokens, stylesheet, script, wiring (commit 1)

Nothing is marked yet after this phase, so the page looks and behaves exactly as before (the script finds no `.home-reveal` and removes its class).

### A1. `theme.json`: the motion tokens

File `wp-content/themes/devportfolio/theme.json`. Find (tabs as in the file):

```
			"glass": {
				"tint": "rgb(255 255 255 / 0.8)",
				"filter": "blur(20px) saturate(180%)"
			}
		},
```

Replace with:

```
			"glass": {
				"tint": "rgb(255 255 255 / 0.8)",
				"filter": "blur(20px) saturate(180%)"
			},
			"motion": {
				"ease": "cubic-bezier(.2, .8, .2, 1)",
				"reveal-duration": "520ms",
				"reveal-distance": "14px",
				"reveal-stagger": "80ms"
			}
		},
```

WordPress prints them as `--wp--custom--motion--ease`, `--wp--custom--motion--reveal-duration`, `--wp--custom--motion--reveal-distance`, `--wp--custom--motion--reveal-stagger`.

### A2. New file `wp-content/themes/devportfolio/assets/css/home-motion.css` (in full)

```css
/*
 * Home sections: opening animation (DPH-032).
 *
 * Every element with the class `home-reveal` fades in and rises a few pixels, once, the first time it scrolls into
 * view. assets/js/home-reveal.js (printed inline in the head, front page only) adds `has-home-reveal` to <html>,
 * then `is-revealing` and finally `is-revealed` to each element. Nothing here applies without that class, so with
 * no JavaScript, no IntersectionObserver, reduced motion, in print or after a cross-document view transition every
 * element shows at its final place.
 *
 * Only opacity and translate change: the layout never moves, so anchor links land where they should and no layout
 * shift is recorded. The translate property is separate from an element's own transforms, so hover lifts keep
 * working. Never put `home-reveal` on a wrapper that holds a dialog, a fixed or sticky element or a backdrop blur:
 * opacity and translate on an ancestor change how those are drawn. An element with focus inside is never hidden.
 * The phone contact card (`.contact.is-modal > .contact__card`, a fixed modal with its own animation) is left alone.
 *
 * Tokens: theme.json settings.custom.motion (ease, reveal-duration, reveal-distance, reveal-stagger).
 * The hero has its own entrance (blocks/hero/style.css) and carries no `home-reveal`.
 */

@media screen and (prefers-reduced-motion: no-preference) {
	.has-home-reveal .home-reveal:not(.is-revealing):not(.is-revealed):not(:focus-within):not(.is-modal > *) {
		opacity: 0;
		translate: 0 var(--wp--custom--motion--reveal-distance, 14px);
	}

	.has-home-reveal .home-reveal.is-revealing:not(.is-modal > *) {
		animation: home-reveal var(--wp--custom--motion--reveal-duration, 520ms) var(--wp--custom--motion--ease, cubic-bezier(.2, .8, .2, 1)) calc(var(--home-reveal-index, 0) * var(--wp--custom--motion--reveal-stagger, 80ms)) both;
	}

	@keyframes home-reveal {
		from {
			opacity: 0;
			translate: 0 var(--wp--custom--motion--reveal-distance, 14px);
		}

		to {
			opacity: 1;
			translate: none;
		}
	}
}
```

### A3. New file `wp-content/themes/devportfolio/assets/js/home-reveal.js` (in full)

```js
/**
 * Home sections: opening animation (DPH-032).
 *
 * Printed inline at the top of <head> on the front page only (functions.php), so it runs before the first paint and
 * can never fail to load. It adds `has-home-reveal` to <html>; assets/css/home-motion.css hides `.home-reveal`
 * elements only under that class. Each element fades and rises in once, the first time 15% of it is on screen;
 * elements that arrive together are staggered (index capped at STAGGER_CAP). Elements on screen at load animate on
 * the first paint. Nothing repeats when scrolling back.
 *
 * Without IntersectionObserver or with reduced motion the class is never added. A script error, or a page whose
 * parsing takes longer than GIVE_UP_MS, removes it, so nothing stays hidden. Entering through a cross-document view
 * transition (Back to Home from an archive) removes it at once: the page cross-fade and the Selected work title morph
 * are the entrance there.
 */
( () => {
	const root = document.documentElement;
	const READY = 'has-home-reveal';
	const TARGET = '.home-reveal';
	const THRESHOLD = 0.15;
	const STAGGER_CAP = 5;
	const FALLBACK_MS = 2000;
	const GIVE_UP_MS = 3000;

	let observer = null;
	let started = false;

	const stop = () => {
		root.classList.remove( READY );
		if ( observer ) {
			observer.disconnect();
			observer = null;
		}
	};

	try {
		if (
			! ( 'IntersectionObserver' in window ) ||
			! window.matchMedia ||
			! window.matchMedia( '(prefers-reduced-motion: no-preference)' ).matches
		) {
			return;
		}
		root.classList.add( READY );
	} catch ( error ) {
		stop();
		return;
	}

	// Back to Home from an archive in a browser with cross-document view transitions: the new page is captured as it
	// is drawn, so show every section now and let the cross-fade and the title morph be the entrance.
	window.addEventListener( 'pagereveal', ( event ) => {
		if ( event.viewTransition ) {
			stop();
		}
	} );

	// Leave nothing behind: no animation, no index, so the element is not a stacking context any more.
	const finish = ( el ) => {
		el.classList.remove( 'is-revealing' );
		el.classList.add( 'is-revealed' );
		el.style.removeProperty( '--home-reveal-index' );
	};

	const reveal = ( el, index ) => {
		// Focus is inside: it is already shown (CSS), so do not fade it out and in again.
		if ( el.matches( ':focus-within' ) ) {
			finish( el );
			return;
		}

		let done = false;
		const end = ( event ) => {
			if ( done || ( event && event.target !== el ) ) {
				return;
			}
			done = true;
			el.removeEventListener( 'animationend', end );
			el.removeEventListener( 'animationcancel', end );
			finish( el );
		};

		el.addEventListener( 'animationend', end );
		el.addEventListener( 'animationcancel', end );
		el.style.setProperty( '--home-reveal-index', String( Math.min( index, STAGGER_CAP ) ) );
		el.classList.add( 'is-revealing' );
		// The animation may never run (the phone contact modal, an element that is not displayed): finish anyway.
		window.setTimeout( end, FALLBACK_MS );
	};

	const start = () => {
		started = true;
		if ( ! root.classList.contains( READY ) ) {
			return;
		}

		const targets = document.querySelectorAll( TARGET );
		if ( ! targets.length ) {
			stop();
			return;
		}

		observer = new IntersectionObserver(
			( entries ) => {
				let index = 0;
				entries.forEach( ( entry ) => {
					if ( ! observer || ! entry.isIntersecting || entry.intersectionRatio < THRESHOLD ) {
						return;
					}
					observer.unobserve( entry.target );
					reveal( entry.target, index );
					index += 1;
				} );
			},
			{ threshold: THRESHOLD }
		);

		targets.forEach( ( el ) => observer.observe( el ) );
	};

	const boot = () => {
		try {
			start();
		} catch ( error ) {
			stop();
		}
	};

	// A slow blocking script in the page can hold the parser: never keep a section hidden for long.
	window.setTimeout( () => {
		if ( ! started ) {
			stop();
		}
	}, GIVE_UP_MS );

	// Start as soon as the parser has the whole page ("interactive"). The later DOM-ready event also waits for every
	// deferred script and script module on Home, which would keep on-screen sections hidden for longer.
	if ( 'loading' !== document.readyState ) {
		boot();
	} else {
		const onState = () => {
			if ( 'loading' !== document.readyState ) {
				document.removeEventListener( 'readystatechange', onState );
				boot();
			}
		};
		document.addEventListener( 'readystatechange', onState );
	}
} )();
```

### A4. `functions.php`: enqueue the stylesheet, print the script

File `wp-content/themes/devportfolio/functions.php`. Find (exactly once):

```
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_nav_spy' );
```

Replace with (tabs inside the functions):

```
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_nav_spy' );

if ( ! function_exists( 'devportfolio_enqueue_home_motion' ) ) :
	/**
	 * Home only: the sections' opening animation styles (DPH-032).
	 *
	 * Every rule is scoped to `html.has-home-reveal`, which only the inline script adds.
	 *
	 * @since 0.36.0
	 */
	function devportfolio_enqueue_home_motion() {
		if ( ! is_front_page() ) {
			return;
		}

		wp_enqueue_style(
			'devportfolio-home-motion',
			get_theme_file_uri( 'assets/css/home-motion.css' ),
			array(),
			(string) filemtime( __DIR__ . '/assets/css/home-motion.css' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_home_motion' );

if ( ! function_exists( 'devportfolio_print_home_reveal_script' ) ) :
	/**
	 * Home only: prints the sections' opening animation script inline at the top of the head (DPH-032).
	 *
	 * Inline and first, so the class that hides the start state is on <html> before the first paint and a failed
	 * request can never leave a section hidden. Without JavaScript nothing is hidden.
	 *
	 * @since 0.36.0
	 */
	function devportfolio_print_home_reveal_script() {
		if ( ! is_front_page() ) {
			return;
		}

		$file = __DIR__ . '/assets/js/home-reveal.js';
		if ( ! is_readable( $file ) ) {
			return;
		}

		wp_print_inline_script_tag(
			(string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local theme file.
			array( 'id' => 'devportfolio-home-reveal' )
		);
	}
endif;
add_action( 'wp_head', 'devportfolio_print_home_reveal_script', 1 );
```

(`@since 0.36.0` follows the plugin-version-style tags already used in `functions.php`; the theme has no version bump in this ticket.)

### A5. Gate A

Run one at a time (the PHP suites share the `wordpress_test` database):

```bash
ddev exec php -l wp-content/themes/devportfolio/functions.php
ddev exec node --check wp-content/themes/devportfolio/assets/js/home-reveal.js
ddev exec php -r 'json_decode( file_get_contents( "wp-content/themes/devportfolio/theme.json" ), true, 512, JSON_THROW_ON_ERROR ); echo "theme.json ok\n";'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```

Expected: no syntax errors, `theme.json ok`, theme suite green with the same total as before this phase.

## Phase B. Mark the sections (commit 2)

Every edit below is an exact find/replace; each find string occurs exactly once in its file (stop and report if not).

### B1. `templates/front-page.html`

Edit 1 (What I do heading). Find:

```
		<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">What I do</h2>
```

Replace with:

```
		<!-- wp:heading {"level":2,"className":"home-reveal","style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading home-reveal" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">What I do</h2>
```

Edit 2 (intro paragraph). Find:

```
		<!-- wp:paragraph {"textColor":"mid","fontSize":"medium"} -->
		<p class="has-mid-color has-text-color has-medium-font-size">
```

Replace with:

```
		<!-- wp:paragraph {"className":"home-reveal","textColor":"mid","fontSize":"medium"} -->
		<p class="has-mid-color has-text-color has-medium-font-size home-reveal">
```

Edit 3 (Selected work heading). Find:

```
		<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">Selected work</h2>
```

Replace with:

```
		<!-- wp:heading {"level":2,"className":"home-reveal","style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading home-reveal" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">Selected work</h2>
```

Edit 4 (Software tools heading). Find:

```
		<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">Software tools</h2>
```

Replace with:

```
		<!-- wp:heading {"level":2,"className":"home-reveal","style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading home-reveal" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">Software tools</h2>
```

Do **not** touch the `<main>`, the three `wp:group` blocks, the separator, the hero, or the three modal blocks. (The editor's block validation compares classes as a set, so `home-reveal` at the end of the paragraph's class list is valid.)

### B2. `blocks/stats-strip/render.php`

Find: `<li class="stats-strip__item">`
Replace: `<li class="stats-strip__item home-reveal">`

### B3. `blocks/service-list/render.php`

Find: `<li class="service-list__row">`
Replace: `<li class="service-list__row home-reveal">`

### B4. `blocks/selected-work/render.php` (three edits)

- Find `<article class="work-card work-card--documents">`, replace `<article class="work-card work-card--documents home-reveal">`.
- Find `<article class="work-card work-card--videos">`, replace `<article class="work-card work-card--videos home-reveal">`.
- Find `<article class="work-card work-card--collaterals">`, replace `<article class="work-card work-card--collaterals home-reveal">`.

Do not touch `get_block_wrapper_attributes( array( 'class' => 'work' ) )`.

### B5. `blocks/testimonials/render.php` (three edits)

- Find `<h2 id="testimonials-heading" class="testimonials__heading">`, replace `<h2 id="testimonials-heading" class="testimonials__heading home-reveal">`.
- Find `<div class="testimonials__stage" data-testimonials-stage aria-live="off">`, replace `<div class="testimonials__stage home-reveal" data-testimonials-stage aria-live="off">`.
- Find `<div class="testimonials__dots" role="group"`, replace `<div class="testimonials__dots home-reveal" role="group"`.

Do not touch `'class' => 'testimonials home-section',` (the section holds the dot-grid backdrop and is the nav-spy target).

### B6. `blocks/software-tools/render.php`

Find: `$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'software-tools' ) );`
Replace: `$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'software-tools home-reveal' ) );`

### B7. `blocks/contact/render.php` (four edits)

- Find `<h2 id="contact-heading" class="contact__heading">`, replace `<h2 id="contact-heading" class="contact__heading home-reveal">`.
- Find `<p class="contact__lead">`, replace `<p class="contact__lead home-reveal">`.
- Find `<button type="button" class="contact__open" data-contact-open>`, replace `<button type="button" class="contact__open home-reveal" data-contact-open>`.
- Find `<div class="contact__card" data-contact-card>`, replace `<div class="contact__card home-reveal" data-contact-card>`.

Do not touch `'class' => 'contact home-section',` (the section holds the fixed phone card).

### B8. `blocks/site-footer/render.php`

Find: `$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'site-footer' ) );`
Replace: `$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'site-footer home-reveal' ) );`

### B9. Existing tests that follow the new class (same commit, or the suite goes red)

`tests/test-contact-block.php`:

- Find `'<h2 id="contact-heading" class="contact__heading">'`, replace `'<h2 id="contact-heading" class="contact__heading home-reveal">'`.
- Find `'<p class="contact__lead">'`, replace `'<p class="contact__lead home-reveal">'`.

`tests/test-testimonials.php`:

- Find `'<h2 id="testimonials-heading" class="testimonials__heading">What clients say</h2>'`, replace `'<h2 id="testimonials-heading" class="testimonials__heading home-reveal">What clients say</h2>'`.

### B10. Gate B

```bash
ddev wp post list --post_type=wp_template --fields=post_name --format=csv
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/selected-work 'node --test tests/*.test.mjs'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/contact 'node --test tests/*.test.mjs'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/testimonials 'node --test tests/*.test.mjs'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/software-tools 'node --test tests/*.test.mjs'
```

Expected: the template list has no `front-page` row; the theme suite green with the same total as Gate A; every node suite green (none of them reads the changed lines).

## Phase C. Tests (commit 3)

### C1. New file `wp-content/themes/devportfolio/tests/test-home-motion.php` (in full)

```php
<?php
/**
 * Tests for the Home sections opening animation (DPH-032).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Home_Motion extends WP_UnitTestCase {

	private const MEDIA = '@media screen and (prefers-reduced-motion: no-preference) {';

	private const HIDDEN = '.has-home-reveal .home-reveal:not(.is-revealing):not(.is-revealed):not(:focus-within):not(.is-modal > *)';

	private const REVEALING = '.has-home-reveal .home-reveal.is-revealing:not(.is-modal > *)';

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/stats-strip' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	private function file( string $relative ): string {
		$path = $this->theme_dir() . '/' . $relative;
		$this->assertFileExists( $path );

		return (string) file_get_contents( $path );
	}

	/**
	 * The motion stylesheet without its comments, so prose never satisfies or breaks an assertion.
	 */
	private function css(): string {
		return (string) preg_replace( '#/\*.*?\*/#s', '', $this->file( 'assets/css/home-motion.css' ) );
	}

	/**
	 * The declarations of a rule that starts with `$selector {`.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Exact selector text.
	 * @return string The declarations, or '' when the rule is not there.
	 */
	private function rule( string $css, string $selector ): string {
		if ( preg_match( '/(?:^|[{}\/])\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $css, $m ) ) {
			return $m[1];
		}

		return '';
	}

	private function keyframes(): string {
		$this->assertSame( 1, preg_match( '/@keyframes home-reveal \{(.*?)\n\t\}\n/s', $this->css(), $m ), 'The home-reveal keyframes are missing.' );

		return $m[1];
	}

	// ---------------------------------------------------------------------------------------------
	// Tokens and stylesheet.
	// ---------------------------------------------------------------------------------------------

	public function test_the_motion_tokens_are_defined_once_in_theme_json(): void {
		$json = json_decode( $this->file( 'theme.json' ), true );

		$this->assertSame(
			array(
				'ease'            => 'cubic-bezier(.2, .8, .2, 1)',
				'reveal-duration' => '520ms',
				'reveal-distance' => '14px',
				'reveal-stagger'  => '80ms',
			),
			$json['settings']['custom']['motion']
		);
	}

	public function test_every_rule_sits_inside_one_screen_no_preference_query(): void {
		$css   = $this->css();
		$start = strpos( $css, self::MEDIA );

		$this->assertNotFalse( $start, 'The reduced-motion query is missing.' );
		$this->assertSame( '', trim( substr( $css, 0, $start ) ), 'No rule may sit before the query.' );
		$this->assertSame( 1, substr_count( $css, '@media' ), 'One query holds every rule.' );
		$this->assertStringEndsWith( '}', rtrim( $css ) );
	}

	public function test_the_hidden_start_state_needs_the_script_class_and_spares_focus_and_the_phone_modal(): void {
		$css  = $this->css();
		$rule = $this->rule( $css, self::HIDDEN );

		$this->assertStringContainsString( 'opacity: 0;', $rule );
		$this->assertStringContainsString( 'translate: 0 var(--wp--custom--motion--reveal-distance, 14px);', $rule );

		// No rule may hide a marked element without the class the script adds.
		$this->assertSame( 0, preg_match( '/(?:^|[{}])\s*\.home-reveal/', $css ) );
		$this->assertSame( 2, substr_count( $css, '.has-home-reveal .home-reveal' ) );
	}

	public function test_the_animation_reads_the_tokens_and_the_stagger_index(): void {
		$rule = $this->rule( $this->css(), self::REVEALING );

		$this->assertStringContainsString(
			'animation: home-reveal var(--wp--custom--motion--reveal-duration, 520ms) var(--wp--custom--motion--ease, cubic-bezier(.2, .8, .2, 1)) calc(var(--home-reveal-index, 0) * var(--wp--custom--motion--reveal-stagger, 80ms)) both;',
			$rule
		);
	}

	public function test_only_opacity_and_translate_change(): void {
		$frames = $this->keyframes();

		preg_match_all( '/([a-z-]+)\s*:/', $frames, $props );
		$this->assertSame( array( 'opacity', 'translate' ), array_values( array_unique( $props[1] ) ) );
		$this->assertStringContainsString( "from {\n\t\t\topacity: 0;\n\t\t\ttranslate: 0 var(--wp--custom--motion--reveal-distance, 14px);", $frames );
		$this->assertStringContainsString( "to {\n\t\t\topacity: 1;\n\t\t\ttranslate: none;", $frames );

		$css = $this->css();
		foreach ( array( 'transform', 'will-change', 'visibility', 'display', 'filter', 'height', 'margin', 'top' ) as $prop ) {
			$this->assertSame( 0, preg_match( '/(?:^|[\s;{])' . $prop . '\s*:/', $css ), "{$prop} must not change." );
		}
	}

	public function test_the_phone_contact_card_keeps_its_own_modal_animation(): void {
		$this->assertStringEndsWith( ':not(.is-modal > *)', self::HIDDEN );
		$this->assertStringEndsWith( ':not(.is-modal > *)', self::REVEALING );
		$this->assertStringContainsString( "\t<div class=\"contact__card home-reveal\" data-contact-card>", $this->file( 'blocks/contact/render.php' ) );
		$this->assertStringContainsString( ".contact.is-modal .contact__card {\n\t\tanimation: contact-modal-in", $this->file( 'blocks/contact/style.css' ) );
	}

	public function test_the_stylesheet_is_enqueued_on_the_front_page_only(): void {
		$this->assertStringContainsString( "add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_home_motion' );", $this->file( 'functions.php' ) );

		wp_dequeue_style( 'devportfolio-home-motion' );
		wp_deregister_style( 'devportfolio-home-motion' );
		$this->go_to( get_post_type_archive_link( 'document' ) );
		devportfolio_enqueue_home_motion();
		$this->assertFalse( wp_style_is( 'devportfolio-home-motion', 'enqueued' ), 'Not on an archive.' );

		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		devportfolio_enqueue_home_motion();
		$this->assertTrue( wp_style_is( 'devportfolio-home-motion', 'enqueued' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Script.
	// ---------------------------------------------------------------------------------------------

	public function test_the_script_is_printed_inline_at_the_top_of_the_head_on_the_front_page_only(): void {
		$this->assertStringContainsString( "add_action( 'wp_head', 'devportfolio_print_home_reveal_script', 1 );", $this->file( 'functions.php' ) );

		$this->go_to( home_url( '/?p=' . self::factory()->post->create() ) );
		ob_start();
		devportfolio_print_home_reveal_script();
		$this->assertSame( '', ob_get_clean(), 'Not the front page.' );

		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		ob_start();
		devportfolio_print_home_reveal_script();
		$out = (string) ob_get_clean();

		// Core may put a type attribute first when the theme has no html5 script support, so match the id anywhere.
		$this->assertMatchesRegularExpression( '/<script[^>]* id="devportfolio-home-reveal"/', $out );
		$this->assertStringContainsString( "const READY = 'has-home-reveal';", $out );
		$this->assertStringNotContainsString( ' src=', $out );
	}

	public function test_the_script_only_hides_with_intersection_observer_and_without_reduced_motion(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		foreach (
			array(
				"const READY = 'has-home-reveal';",
				"const TARGET = '.home-reveal';",
				'const THRESHOLD = 0.15;',
				'const STAGGER_CAP = 5;',
				'const GIVE_UP_MS = 3000;',
				"'IntersectionObserver' in window",
				"window.matchMedia( '(prefers-reduced-motion: no-preference)' ).matches",
				'root.classList.add( READY );',
				'} catch ( error ) {',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $js );
		}

		// The class is added only after both checks passed.
		$this->assertLessThan( strpos( $js, 'root.classList.add( READY );' ), strpos( $js, '(prefers-reduced-motion: no-preference)' ) );
	}

	public function test_the_script_reveals_each_element_once_and_cleans_up(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		foreach (
			array(
				'{ threshold: THRESHOLD }',
				'observer.unobserve( entry.target );',
				"el.style.setProperty( '--home-reveal-index', String( Math.min( index, STAGGER_CAP ) ) );",
				"el.classList.add( 'is-revealing' );",
				"el.classList.remove( 'is-revealing' );",
				"el.classList.add( 'is-revealed' );",
				"el.style.removeProperty( '--home-reveal-index' );",
				"el.matches( ':focus-within' )",
				"'animationend'",
				"'animationcancel'",
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $js );
		}
	}

	public function test_the_script_starts_early_and_steps_aside_for_a_view_transition(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		$this->assertStringContainsString( "window.addEventListener( 'pagereveal', ( event ) => {", $js );
		$this->assertStringContainsString( 'if ( event.viewTransition ) {', $js );
		$this->assertStringContainsString( "document.addEventListener( 'readystatechange', onState );", $js );
		$this->assertStringNotContainsString( "addEventListener( 'DOMContentLoaded'", $js );
	}

	public function test_the_script_stores_nothing_and_is_a_classic_script(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		$this->assertStringNotContainsString( 'localStorage', $js );
		$this->assertStringNotContainsString( 'sessionStorage', $js );
		$this->assertStringNotContainsString( 'will-change', $js );
		$this->assertSame( 0, preg_match( '/^\s*(import|export)\s/m', $js ) );
	}

	// ---------------------------------------------------------------------------------------------
	// What is marked, and what never is.
	// ---------------------------------------------------------------------------------------------

	public function test_the_template_marks_the_three_headings_and_the_intro(): void {
		$tpl = $this->file( 'templates/front-page.html' );

		$this->assertSame( 4, substr_count( $tpl, '"className":"home-reveal"' ) );
		$this->assertSame( 3, substr_count( $tpl, 'class="wp-block-heading home-reveal"' ) );
		foreach ( array( 'What I do', 'Selected work', 'Software tools' ) as $heading ) {
			$this->assertStringContainsString( '<h2 class="wp-block-heading home-reveal" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">' . $heading . '</h2>', $tpl );
		}
		$this->assertStringContainsString( '<p class="has-mid-color has-text-color has-medium-font-size home-reveal">', $tpl );
	}

	public function test_the_blocks_mark_their_items(): void {
		$marks = array(
			'blocks/stats-strip/render.php'    => array( '<li class="stats-strip__item home-reveal">' ),
			'blocks/service-list/render.php'   => array( '<li class="service-list__row home-reveal">' ),
			'blocks/selected-work/render.php'  => array(
				'<article class="work-card work-card--documents home-reveal">',
				'<article class="work-card work-card--videos home-reveal">',
				'<article class="work-card work-card--collaterals home-reveal">',
			),
			'blocks/testimonials/render.php'   => array(
				'<h2 id="testimonials-heading" class="testimonials__heading home-reveal">',
				'<div class="testimonials__stage home-reveal" data-testimonials-stage aria-live="off">',
				'<div class="testimonials__dots home-reveal" role="group"',
			),
			'blocks/software-tools/render.php' => array( "array( 'class' => 'software-tools home-reveal' )" ),
			'blocks/contact/render.php'        => array(
				'<h2 id="contact-heading" class="contact__heading home-reveal">',
				'<p class="contact__lead home-reveal">',
				'<button type="button" class="contact__open home-reveal" data-contact-open>',
				'<div class="contact__card home-reveal" data-contact-card>',
			),
			'blocks/site-footer/render.php'    => array( "array( 'class' => 'site-footer home-reveal' )" ),
		);

		foreach ( $marks as $file => $needles ) {
			$src = $this->file( $file );
			foreach ( $needles as $needle ) {
				$this->assertStringContainsString( $needle, $src, "{$file}: {$needle}" );
			}
			$this->assertSame( count( $needles ), substr_count( $src, 'home-reveal' ), "{$file} marks something else too." );
		}
	}

	public function test_no_wrapper_that_holds_a_dialog_a_fixed_or_a_sticky_element_is_marked(): void {
		$tpl = $this->file( 'templates/front-page.html' );

		$this->assertStringContainsString( '<main class="wp-block-group documents-page home-page">', $tpl );
		foreach ( array( 'services', 'selected-work', 'software-tools' ) as $id ) {
			$this->assertStringContainsString( '<div id="' . $id . '" class="wp-block-group home-section"', $tpl );
		}
		$this->assertStringNotContainsString( '"className":"home-section home-reveal"', $tpl );
		$this->assertStringContainsString( "'class' => 'contact home-section',", $this->file( 'blocks/contact/render.php' ) );
		$this->assertStringContainsString( "'class' => 'testimonials home-section',", $this->file( 'blocks/testimonials/render.php' ) );
		$this->assertStringContainsString( "get_block_wrapper_attributes( array( 'class' => 'work' ) )", $this->file( 'blocks/selected-work/render.php' ) );

		foreach (
			array(
				'blocks/hero/render.php',
				'blocks/hero/style.css',
				'blocks/document-modal/render.php',
				'blocks/collateral-modal/render.php',
				'blocks/video-modal/render.php',
				'parts/header.html',
				'parts/footer.html',
			) as $file
		) {
			$this->assertStringNotContainsString( 'home-reveal', $this->file( $file ), "{$file} must not be marked." );
		}
	}

	public function test_the_hero_keeps_its_own_entrance(): void {
		$this->assertStringContainsString( '@media (min-width: 621px) and (prefers-reduced-motion: no-preference) {', $this->file( 'blocks/hero/style.css' ) );
	}

	public function test_rendered_blocks_carry_the_mark(): void {
		self::factory()->post->create(
			array(
				'post_type'    => 'statistic',
				'post_title'   => 'Clients',
				'post_content' => '40+',
				'menu_order'   => 1,
				'post_status'  => 'publish',
			)
		);
		devportfolio_memo_flush();

		$this->assertStringContainsString( '<li class="stats-strip__item home-reveal">', do_blocks( '<!-- wp:devportfolio/stats-strip /-->' ) );
		$this->assertStringContainsString( 'site-footer home-reveal', do_blocks( '<!-- wp:devportfolio/site-footer /-->' ) );
	}
}
```

The file holds **17 tests**. Notes for the implementer: `test_the_blocks_mark_their_items()` also counts `home-reveal` per file, so a stray extra mark fails it; `test_only_opacity_and_translate_change()` relies on the exact indentation of A2 (one tab for the keyframes, two for `from`/`to`, three for declarations).

### C2. Gate C

```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter Devportfolio_Test_Home_Motion
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
```

Expected: 17 tests green in the filtered run; the theme suite green with **Gate A's total + 17**; the plugin suite green and unchanged (AGENTS.md asks for both suites before a PR). Record both totals for the PR and D2.

## Phase D. Browser check, docs

### D1. Manual browser check (no runner in the repo)

`ddev launch`, Home page, hard reload each time. DevTools > Rendering has "Emulate CSS media feature prefers-reduced-motion"; Performance panel shows layout shifts.

1. Desktop 1280 x 800, Chrome: on load, whatever is on screen below the hero fades and rises in once; scroll down slowly: stats items, the What I do heading, intro and rows, the Selected work heading and cards (Documents and Videos together, a short step apart, then Brand collaterals), testimonials heading, quote and dots, the Software tools heading and list, the contact heading, lead and card, then the footer each appear once. Scroll back up and down: nothing replays.
2. The hero's own entrance looks exactly as before (name down, portrait up, corners fade in) and does not wait for anything.
3. `<html>` has `has-home-reveal` (Elements panel). After the motion settles, every marked element has `is-revealed`, no `is-revealing` and no inline `--home-reveal-index`.
4. Header: still frosted while sections animate under it (blur intact); the nav spy still moves the active item between Services, Testimonials and Contact.
5. Selected work: the books rotate and the film strip advances as before; click a book, a frame and a collateral piece: each viewer opens full screen above the page, correctly positioned (not inside the card), and closes back to Home.
6. From the Documents archive, click the browser Back button to Home (Chrome 126+): the "Documents" title morphs into the card title, every section is visible at once (no blank cards), and `<html>` has no `has-home-reveal`.
7. Anchors: from `/documents/`, open `/#contact` and `/#selected-work`, and click Services and Testimonials in the nav: each lands with the heading just under the header, exactly as before; the Performance panel records no layout shift (CLS 0 from these animations).
8. Phone 390 x 844 (device toolbar, touch): sections reveal the same way; the contact band shows heading, lead and "Check availability"; tap it: the full-screen scheduler opens at once with its own rise-in (never invisible, never 14px low), every step works, Close returns to the page.
9. Keyboard: reload with the page scrolled to the top, press Tab repeatedly through the whole page: every focused control is visible (never transparent), the focus ring shows, and the page scrolls to it.
10. Reduced motion on (emulated), reload: nothing moves, nothing is ever transparent, `<html>` has no `has-home-reveal`.
11. JavaScript off (DevTools > Settings > Debugger > Disable JavaScript), reload: everything shows at once (the contact card stays hidden as before: it needs its script; the `noscript` line shows).
12. Print preview (Cmd+P) right after load: every section is printed.
13. Firefox and Safari (latest): steps 1, 7 and 9 behave the same (Firefox has no `pagereveal`; a Back navigation there replays the reveal for what is on screen, which is fine).
14. Other pages (`/documents/`, `/videos/`, `/collaterals/`): no change at all; the footer shows at once.

### D2. `AGENTS.md`

File `/AGENTS.md` (repo root). Find the start of this paragraph (exactly once):

```
**Job Roles and the Home hero (DPH-001, DPH-003, DPH-004, plugin 0.35.0):**
```

Insert **before** it this paragraph followed by one blank line:

```
**Home sections opening animation (DPH-032):** every Home section after the hero fades in and rises 14px once, the first time 15% of it is on screen (elements that arrive together are staggered by 80ms, at most 5 steps; elements on screen at load animate on the first paint; scrolling back never replays it). One mechanism for all of them: elements carry the class `home-reveal` (in `templates/front-page.html` the What I do, Selected work and Software tools headings and the intro; each `stats-strip__item`, `service-list__row` and `work-card`; the testimonials heading, stage and dots; `ul.software-tools`; the contact heading, lead, phone button and card; the `site-footer` block, inert on other pages). `assets/js/home-reveal.js` is printed inline at the top of `<head>` on the front page only (`devportfolio_print_home_reveal_script()`, `wp_head` priority 1): with IntersectionObserver and without reduced motion it adds `has-home-reveal` to `<html>`, then, once parsing has ended (`readystatechange`, not DOM-ready, which waits for every script module), gives each element `is-revealing` with `--home-reveal-index` and, when the animation ends, `is-revealed` (nothing left on the element); a script error, or parsing longer than 3 s, removes the class, and a page entered through the cross-document view transition (`pagereveal` with `viewTransition`, Back from an archive) removes it at once so the Selected work title morph is untouched. `assets/css/home-motion.css` (front page only, `devportfolio_enqueue_home_motion()`) is one `@media screen and (prefers-reduced-motion: no-preference)` block: the start state and the `home-reveal` keyframes, `opacity` and `translate` only (no layout change: anchors land where they did, no layout shift). Tokens: `theme.json` `settings.custom.motion` (`ease` `cubic-bezier(.2, .8, .2, 1)`, `reveal-duration` 520ms, `reveal-distance` 14px, `reveal-stagger` 80ms; `--wp--custom--motion--*`). Rules: never put `home-reveal` on the hero or on a wrapper that holds a dialog, a fixed or sticky element or a backdrop blur (the `#selected-work` group holds the three viewers, `section.contact` holds the fixed phone card, `section.testimonials` holds the dot grid); the phone contact card (`.is-modal > *`) is excluded and keeps its own `contact-modal-in`; an element with focus inside is never hidden. A new Home section opts in by adding the class to its heading and items. Tests: `tests/test-home-motion.php` (`Devportfolio_Test_Home_Motion`). Plan: `.claude/plans/home/DPH-032-Home-Sections-Opening-Animation.md`.
```

Also in `AGENTS.md`, in the Tests section, find `theme 733 tests` and replace it with `theme <N> tests`, where `<N>` is the theme total printed by Gate C (if the text there is no longer `theme 733 tests`, replace whatever theme total is written there with `<N>`).

### D3. Stories CSV

Append these lines to the end of `.claude/plans/home/Home-User-Stories-Revised.csv` (after its final newline; the file ends with the DPH-031 row). Keep the trailing newline:

```
DPH-032,Homepage,Sections - Opening Animation,"As a visitor, I want each Home section after the hero to fade in gently as I reach it, so that the page feels alive without getting in my way.",Low,User Story,DPH-030,"1.) Each section below the hero (stats, What I do, Selected work, What clients say, Software tools, Contact, footer) fades in and rises about 14px over about half a second, once, the first time it scrolls into view. Items of a section that arrive together follow each other by a short step.
2.) Sections already on screen when the page opens animate straight away. Scrolling back up never replays it.
3.) Nothing moves with ""reduce motion"" on, without JavaScript, in print, or in an old browser: everything shows at its final place.
4.) Anchor links (Services, Selected work, Testimonials, Contact) land exactly where they did before, and nothing on the page shifts.
5.) Going Back from an archive to Home keeps the Selected work title morph.",
```

## Conventional commits

Branch `feat/DPH-032-Home-Sections-Opening-Animation` from `main`. Stage explicit paths only, never `git add -A` (untracked plan files of other tickets are in the working tree). Footer on every commit:

```
Refs: DPH-032
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
```

Run each commit's gate before committing; every commit leaves both suites green. Paths below are relative to the repo root.

1. `feat(theme): add the Home motion tokens and section reveal script` (64 characters). Files: `wp-content/themes/devportfolio/theme.json`, `wp-content/themes/devportfolio/assets/css/home-motion.css`, `wp-content/themes/devportfolio/assets/js/home-reveal.js`, `wp-content/themes/devportfolio/functions.php`. Gate A. Body: "Nothing is marked yet, so the page is unchanged; the script finds no element and removes its class."
2. `feat(theme): mark the Home sections for the opening animation` (61). Files: `wp-content/themes/devportfolio/templates/front-page.html`, `wp-content/themes/devportfolio/blocks/stats-strip/render.php`, `wp-content/themes/devportfolio/blocks/service-list/render.php`, `wp-content/themes/devportfolio/blocks/selected-work/render.php`, `wp-content/themes/devportfolio/blocks/testimonials/render.php`, `wp-content/themes/devportfolio/blocks/software-tools/render.php`, `wp-content/themes/devportfolio/blocks/contact/render.php`, `wp-content/themes/devportfolio/blocks/site-footer/render.php`, `wp-content/themes/devportfolio/tests/test-contact-block.php`, `wp-content/themes/devportfolio/tests/test-testimonials.php`. Gate B. Body: "The two existing tests compare the exact opening tag of a marked heading and lead, so their three assertions change with the markup to keep the suite green."
3. `test(theme): cover the Home sections opening animation` (54). Files: `wp-content/themes/devportfolio/tests/test-home-motion.php`. Gate C.
4. `docs(agents): document the Home sections opening animation` (59). Files: `AGENTS.md`. Step D2.
5. `docs(plans): add the DPH-032 Home sections opening animation plan` (65). Files: `.claude/plans/home/DPH-032-Home-Sections-Opening-Animation.md`, `.claude/plans/home/Home-User-Stories-Revised.csv`. Step D3.

**PR title:** `feat(theme): add a subtle opening animation to the Home sections` (63 characters). Body from `.github/pull_request_template.md`: tick Feature; Description (the story above), Implementation (the Approach paragraph and the commit list), How to Test (the Gate C totals for the theme and plugin suites, and the D1 checklist with what was checked in which browser); end with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Merge with a normal merge commit.

## Risks to watch

- **Slow first paint of on-screen sections on a very slow page:** sections in view stay transparent until parsing ends (at most 3 s, then everything shows). The hero is not affected. If this is noticed, lower `GIVE_UP_MS`.
- **Nav spy during the motion:** `assets/js/nav-spy.js` measures the sections, which are never translated, so it is unaffected; only their children move, by at most 14px for about half a second.
- **A future block added to Home** shows at once unless its heading and items get `home-reveal`; never add the class to a wrapper that contains a dialog, a fixed or sticky element or a backdrop blur (see the AGENTS.md rule).
- **Old browsers:** both rules use a complex `:not(.is-modal > *)`. A browser that cannot parse it drops both rules, so nothing is hidden there (safe), it just does not animate.
- **Site Editor copy of the template:** if someone customises `front-page` in the Site Editor, the template marks (B1) live in the database copy instead; the block marks still work.
