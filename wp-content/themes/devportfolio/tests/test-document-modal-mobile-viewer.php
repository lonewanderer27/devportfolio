<?php
/**
 * Tests for the mobile viewer body and the adaptive spread (DPD-021, DPD-024).
 *
 * There is no browser runner in the repo: the pure maths is tested with node (gestures, layout, mode),
 * and this file checks the markup, the strings and the structure of the scripts and styles.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Modal_Mobile_Viewer extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	private function render_modal(): string {
		return do_blocks( '<!-- wp:devportfolio/document-modal /-->' );
	}

	public function test_the_root_carries_the_mobile_strings_as_json(): void {
		$html = $this->render_modal();
		$this->assertMatchesRegularExpression( '/data-mobile-strings="([^"]+)"/', $html );
		preg_match( '/data-mobile-strings="([^"]+)"/', $html, $m );
		$strings = json_decode( html_entity_decode( $m[1] ), true );
		$this->assertIsArray( $strings );
		$this->assertSame( 'swipe or tap edges', $strings['hint'] );
		$this->assertSame( 'Rotate for a bigger view', $strings['rotate'] );
		$this->assertSame( 'Pinch to zoom', $strings['pinch'] );
		$this->assertStringContainsString( '{n}', $strings['zoom'] );
		foreach ( array_keys( devportfolio_document_mobile_strings() ) as $key ) {
			$this->assertNotSame( '', $strings[ $key ], $key );
		}
	}

	public function test_the_mobile_body_has_a_status_element_with_every_hint(): void {
		$html   = $this->render_modal();
		$mobile = substr( $html, (int) strpos( $html, 'documents-mobile__body' ) );
		$this->assertStringContainsString( 'documents-mobile__state', $mobile );
		$this->assertSame( 2, substr_count( $html, 'role="status"' ), 'One status element per dialog.' );
		foreach ( array( 'data-loading', 'data-unavailable', 'data-hint=', 'data-hint-sheet', 'data-hint-slide', 'data-hint-doc' ) as $attribute ) {
			$this->assertSame( 2, substr_count( $html, $attribute ), $attribute );
		}
	}

	public function test_the_mobile_viewer_reuses_the_desktop_sources_and_gate(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( "from './page-source.js'", $js );
		$this->assertStringContainsString( 'acquireSource( doc, alive', $js );
		$this->assertStringContainsString( 'selectableText', $js );
		$this->assertStringNotContainsString( 'loadFlipLibrary', $js, 'The mobile viewer never loads the page-flip library.' );
		$this->assertStringNotContainsString( 'import( pdfjs', $js, 'pdf.js is imported by page-source.js only.' );

		$sources = $this->read( 'blocks/document-modal/page-source.js' );
		$this->assertStringContainsString( 'await import( pdfjs.module )', $sources );
		$this->assertStringContainsString( 'clientRenderTier( readEnvironment(', $sources );
		$this->assertStringContainsString( 'export async function acquireSource', $sources );
	}

	public function test_every_known_kind_is_drawn_on_mobile(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( '! canShowOnMobile( doc )', $js );
		$this->assertLessThan( strpos( $js, 'acquireSource( doc, alive' ), strpos( $js, '! canShowOnMobile( doc )' ), 'The gate comes before anything is fetched.' );
		$mode = $this->read( 'blocks/document-modal/mode.js' );
		$this->assertStringContainsString( "doc.kind === 'pdf' || unitOf( doc ) === 'slide' || isSpreadsheet( doc ) || isWord( doc )", $mode );
		$this->assertStringNotContainsString( 'isMobilePdf', $mode, 'The strict PDF check was dropped with the last doctype.' );
		$this->assertStringNotContainsString( 'canDrawPresentations', $mode, 'The old alias is gone.' );
	}

	public function test_the_native_powerpoint_viewer_on_mobile_shares_the_desktop_mount(): void {
		$mobile  = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$desktop = $this->read( 'blocks/document-modal/viewer.js' );
		$mount   = $this->read( 'blocks/document-modal/native-pptx.js' );

		$this->assertStringContainsString( "from './native-pptx.js'", $mobile );
		$this->assertStringContainsString( "from './native-pptx.js'", $desktop );
		$this->assertStringContainsString( 'nativeAllowedOnMobile( doc ) && viewerMode( doc ) === \'native\'', $mobile );
		$this->assertStringContainsString( 'canDrawOffice( readNativeEnvironment() )', $mobile );
		$this->assertStringNotContainsString( 'new lib.PptxViewer', $mobile, 'The options live in one place.' );
		$this->assertStringNotContainsString( 'import( PPTX_LIB )', $mobile );

		// The privacy options are set once, for both viewers, and match the desktop limits.
		foreach ( array( 'enableTextSelection: true', 'enableHyperlinks: false', 'enableMediaPlayback: false', 'enableElementSelection: false', 'useGoogleFonts: false', 'Math.min( 2, window.devicePixelRatio || 1 )', 'limits.nativeTimeoutMs' ) as $option ) {
			$this->assertStringContainsString( $option, $mount, $option );
		}
		$this->assertStringNotContainsString( 'nativeMobile', $mobile . $mount, 'No mobile-only limit.' );
	}

	public function test_the_native_excel_grid_on_mobile_shares_the_desktop_mount_and_its_limits(): void {
		$mobile  = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$desktop = $this->read( 'blocks/document-modal/viewer.js' );
		$mount   = $this->read( 'blocks/document-modal/native-xlsx.js' );

		$this->assertStringContainsString( "from './native-xlsx.js'", $mobile );
		$this->assertStringContainsString( "from './native-xlsx.js'", $desktop );
		$this->assertStringNotContainsString( 'new lib.XlsxViewer', $mobile . $desktop, 'The options live in one place.' );
		$this->assertStringNotContainsString( 'import( XLSX_LIB )', $mobile . $desktop );
		$this->assertStringContainsString( 'canDrawOffice( readNativeEnvironment() )', $mobile );
		$this->assertStringContainsString( 'isNativeScroll( doc ) ? showScroll( doc, alive ) : showNative( doc, alive )', $mobile );

		// Same privacy options and limits as desktop, set once.
		foreach ( array( 'wasmUrl: XLSX_WASM', 'useGoogleFonts: false', 'enableHyperlinks: false', 'enableElementSelection: false', 'comments: false', "hiddenSheetMode: 'skip'", 'workerTimeoutMs: limits.nativeTimeoutMs', 'xlsxWorksheetLimits: { maxCells: limits.nativeMaxCells, maxRows: limits.nativeMaxRows }', 'instance.load( doc.nativeUrl )', 'onCreate( instance )' ) as $option ) {
			$this->assertStringContainsString( $option, $mount, $option );
		}
		$this->assertStringContainsString( '?v=${ OOXML_VERSION }', $mount );
		$this->assertStringContainsString( 'import( XLSX_LIB )', $mount, 'A dynamic import: nothing is downloaded for other documents.' );
		$this->assertStringNotContainsString( 'nativeMobile', $mobile . $mount, 'No mobile-only limit.' );
		$this->assertStringNotContainsString( 'innerHTML', $mobile . $mount );
	}

	public function test_the_native_grid_is_one_piece_with_no_footer_no_page_gestures_and_no_zoom_buttons(): void {
		$js  = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$app = $this->read( 'blocks/document-modal/mobile.js' );
		$body = substr( $js, (int) strpos( $js, 'const showScroll' ), (int) strpos( $js, '/* ---- public' ) - (int) strpos( $js, 'const showScroll' ) );

		// One piece: mobile.js hides the footer and progress bar when pages < 2, as on desktop.
		$this->assertStringContainsString( 'onGeometry( { pages: 1, step } )', $body );
		$this->assertStringContainsString( 'const footerGone = state.pages < 2 || landscape.matches;', $app );

		// Nothing of the page viewer is built for the grid.
		foreach ( array( 'bindGestures', 'buildUi', 'touchZoom', 'zoomFabs', 'zoomBy', 'documents-mobile__edge', 'documents-mobile__frame' ) as $page_only ) {
			$this->assertStringNotContainsString( $page_only, $body, $page_only );
		}
		$this->assertStringContainsString( 'makeDetails()', $body, 'Only the details button is kept.' );
		$this->assertStringContainsString( "documents-mobile__scroll", $body );
		$this->assertStringContainsString( 'if ( ui && scrollView )', $js );

		// Closing frees the workers and the stage loses its grid class and variable.
		$this->assertStringContainsString( 'scrollView.instance.destroy()', $js );
		$this->assertStringContainsString( "stage.classList.remove( 'documents-mobile__stage--scroll', 'documents-mobile__stage--doc' )", $js );
		$this->assertStringContainsString( "stage.style.removeProperty( '--documents-grid-bar' )", $js );

		// A failed grid falls back to the stored sheets, else "can't be previewed".
		$this->assertStringContainsString( 'The native viewer could not draw it: use the stored slides when there are some.', $js );

		// The grid owns the arrow, zoom and 0 keys while it has the focus.
		$this->assertStringContainsString( "closest( '.documents-mobile__scroll' )", $app );
	}

	public function test_the_grid_keeps_clear_of_the_floating_buttons_in_portrait_and_landscape(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );

		// Own scrolling: not the frame's touch-action: none; the dialog does not scroll behind it.
		$this->assertMatchesRegularExpression( '#\.documents-mobile__scroll\s*\{[^}]*touch-action:\s*pan-x pan-y pinch-zoom#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__scroll\s*\{[^}]*overscroll-behavior:\s*contain#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__scroll\s*\{[^}]*env\(safe-area-inset-bottom#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__stage--scroll\s*\{[^}]*touch-action:\s*auto#', $css );

		// The details button sits above the library's tab bar (height measured after mount), in both orientations.
		$this->assertMatchesRegularExpression( '#\.documents-mobile__stage--scroll \.documents-mobile__fabs\s*\{[^}]*bottom:\s*calc\(\s*var\(--documents-grid-bar, 30px\) \+ 12px#', $css );

		// Thumb-sized tab bar on touch screens.
		$this->assertMatchesRegularExpression( '#\(pointer: coarse\)\s*\{\s*\.documents-mobile__scroll :has\( > \.xlsx-tab-strip \)\s*\{\s*height:\s*40px !important#', $css );

		// Held sideways Close and Download float over the top corners: the grid starts below them.
		$this->assertMatchesRegularExpression( '#orientation: landscape\) and \(max-height: 520px\)\s*\{\s*\.documents-mobile__scroll\s*\{[^}]*top:\s*calc\(\s*max\(12px, env\(safe-area-inset-top, 0px\)\) \+ 38px \+ 8px\s*\)#', $css );

		// A workbook gets no zoom buttons (its tab bar has its own zoom), so nothing sits over the bar's left end:
		// they are built only for a document, and only after the 'isDoc' check.
		$js   = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$body = substr( $js, (int) strpos( $js, 'const showScroll' ), (int) strpos( $js, '/* ---- public' ) - (int) strpos( $js, 'const showScroll' ) );
		$this->assertMatchesRegularExpression( '#if \( isDoc \) \{\s*buildZoom\(\);#', $body );
	}

	public function test_the_native_word_scroller_on_mobile_shares_the_desktop_mount_and_its_limits(): void {
		$mobile  = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$desktop = $this->read( 'blocks/document-modal/viewer.js' );
		$mount   = $this->read( 'blocks/document-modal/native-docx.js' );
		$mode    = $this->read( 'blocks/document-modal/mode.js' );

		$this->assertStringContainsString( "from './native-docx.js'", $mobile );
		$this->assertStringContainsString( "from './native-docx.js'", $desktop );
		$this->assertStringNotContainsString( 'new lib.DocxScrollViewer', $mobile . $desktop, 'The options live in one place.' );
		$this->assertStringNotContainsString( 'import( DOCX_LIB )', $mobile . $desktop );
		$this->assertStringContainsString( 'openDocx : openXlsx', $mobile );
		$this->assertStringContainsString( "doc.kind === 'pptx' || doc.kind === 'xlsx' || doc.kind === 'docx'", $mode );

		// Same privacy options and limits as desktop, set once.
		foreach ( array( 'wasmUrl: DOCX_WASM', 'useGoogleFonts: false', 'enableTextSelection: true', 'enableHyperlinks: false', 'enableElementSelection: false', 'comments: false', 'showTrackedChanges: false', 'workerTimeoutMs: limits.nativeTimeoutMs', 'resourceLimits', 'instance.load( doc.nativeUrl )', 'onCreate( instance )', 'instance.fitWidth()' ) as $option ) {
			$this->assertStringContainsString( $option, $mount, $option );
		}
		$this->assertStringContainsString( '?v=${ OOXML_VERSION }', $mount );
		$this->assertStringContainsString( 'import( DOCX_LIB )', $mount, 'A dynamic import: nothing is downloaded for other documents.' );
		$this->assertStringNotContainsString( 'nativeMobile', $mobile . $mount, 'No mobile-only limit.' );
		$this->assertStringNotContainsString( 'innerHTML', $mobile . $mount );
	}

	public function test_the_word_scroller_zoom_buttons_call_the_library_and_stop_at_its_limits(): void {
		$js  = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$css = $this->read( 'blocks/document-modal/style.css' );

		$this->assertStringContainsString( "stepBy( 'zoomIn' )", $js );
		$this->assertStringContainsString( "stepBy( 'zoomOut' )", $js );
		$this->assertStringContainsString( 'instance.getScale()', $js );
		$this->assertStringContainsString( 'documents-mobile__scroll--doc', $js );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__stage--doc \.documents-mobile__fabs,\s*\.documents-mobile__stage--doc \.documents-mobile__zoomfabs\s*\{[^}]*bottom:\s*calc\(12px \+ env\(safe-area-inset-bottom#', $css, 'No tab bar: both buttons keep the normal inset.' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__scroll--doc\s*\{[^}]*overflow:\s*auto#', $css );
	}

	public function test_held_sideways_the_word_scroller_fills_the_screen_but_the_grid_keeps_clear_of_the_buttons(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );

		// A spreadsheet grid starts below the floating Close and Download buttons (its tab bar sits at the bottom).
		$this->assertMatchesRegularExpression( '#orientation: landscape\) and \(max-height: 520px\)\s*\{\s*\.documents-mobile__scroll\s*\{[^}]*top:\s*calc\(\s*max\(12px, env\(safe-area-inset-top, 0px\)\) \+ 38px \+ 8px\s*\)#', $css );

		// A Word document scrolls under the round buttons like the page viewer: no visible bar across the top.
		$this->assertMatchesRegularExpression( '#orientation: landscape\) and \(max-height: 520px\)\s*\{[^@]*\.documents-mobile__scroll--doc\s*\{\s*top:\s*0;#', $css, 'The Word viewer must start at the very top held sideways.' );
	}

	public function test_the_native_canvas_is_redrawn_in_place_freed_on_close_and_falls_back_to_the_slides(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );

		$this->assertStringContainsString( 'native.instance.goToSlide( target - 1 )', $js );
		$this->assertStringContainsString( 'created.destroy()', $js, 'Closing frees the workers (reset() calls source.destroy()).' );
		$this->assertStringContainsString( 'The native viewer could not draw it: use the stored slides when there are some.', $js );
		$this->assertStringContainsString( 'native ? native.box : makeBook( first )', $js );
		$this->assertStringContainsString( 'if ( gesture === \'drag\' && ! native )', $js, 'The canvas does not follow the finger.' );
		$this->assertStringNotContainsString( 'innerHTML', $js );

		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__native\s*\{[^}]*user-select:\s*text#', $css, 'Text on the slide can be selected.' );
	}

	public function test_zoom_buttons_and_the_native_canvas_is_refitted_after_every_slide(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );

		// The library resets the canvas's CSS size to its drawing size on each render: a turn must fit it again.
		$this->assertMatchesRegularExpression( '#native\.instance\.goToSlide\( target - 1 \)\.then\(\s*\(\) => \{.*?fitNative\(\);#s', $js );
		$this->assertStringContainsString( 'zoomIn.addEventListener( \'click\', () => zoomBy( 1 ) )', $js );
		$this->assertStringContainsString( 'zoomOut.addEventListener( \'click\', () => zoomBy( -1 ) )', $js );
		$this->assertStringContainsString( 'touchZoom.step( direction )', $js, 'Buttons and keys take the same step.' );
		$this->assertStringContainsString( 'ui.zoomOut.disabled = touchZoom.get().scale <= ZOOM_MIN', $js );

		$strings = devportfolio_document_mobile_strings();
		foreach ( array( 'zoomIn', 'zoomOut', 'zoomControls' ) as $key ) {
			$this->assertNotSame( '', $strings[ $key ], $key );
		}
		$this->assertMatchesRegularExpression( '#\.documents-mobile__zoomfabs\s*\{[^}]*left:#', $this->read( 'blocks/document-modal/style.css' ) );
	}

	public function test_the_crop_and_the_text_layer_are_honoured(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( "'documents-modal__book--cropped'", $js );
		$this->assertStringContainsString( "'--documents-crop'", $js );
		$this->assertStringContainsString( "'documents-modal__book--selectable'", $js );
	}

	public function test_gestures_follow_the_ticket(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		foreach ( array( 'classifyTap(', 'swipeDirection(', 'verticalIntent(', 'touchZoom.movePinch(', 'touchZoom.tap(', 'touchZoom.movePan(' ) as $call ) {
			$this->assertStringContainsString( $call, $js, $call );
		}
		// The zoom state is shared with the desktop book's touch handling.
		$zoom = $this->read( 'blocks/document-modal/touch-zoom.js' );
		foreach ( array( 'pinchScale(', 'doubleTapTarget(', 'isDoubleTap(', 'clampPan(', 'zoomAround(' ) as $call ) {
			$this->assertStringContainsString( $call, $zoom, $call );
		}
		$this->assertStringContainsString( "from './touch-zoom.js'", $this->read( 'blocks/document-modal/viewer.js' ) );
		$this->assertStringContainsString( "addEventListener( 'pointerdown'", $js );
		$this->assertStringContainsString( "addEventListener( 'pointercancel'", $js );
		$gestures = $this->read( 'blocks/document-modal/gestures.js' );
		$this->assertStringContainsString( 'EDGE_ZONE = 0.2', $gestures );
		$this->assertStringContainsString( 'DEAD_STRIP = 8', $gestures );
		$this->assertStringContainsString( 'ZOOM_MAX = 4', $gestures );
	}

	public function test_zoom_keys_and_arrow_keys_are_wired_in_the_mobile_modal(): void {
		$js = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertStringContainsString( 'viewer.zoomStep( 1 )', $js );
		$this->assertStringContainsString( 'viewer.zoomStep( -1 )', $js );
		$this->assertStringContainsString( 'viewer.resetZoom()', $js );
		$this->assertStringContainsString( "event.key === 'ArrowRight' ? 1 : -1", $js );
		$this->assertStringContainsString( 'els.pages.hidden = state.pages < 2', $js );
		$this->assertStringContainsString( 'els.footer.hidden = footerGone', $js, 'One page: no footer.' );
		$this->assertStringContainsString( 'els.bar.hidden = state.pages < 2', $js, 'One page: no progress bar.' );
		$this->assertStringContainsString( "els.details.addEventListener( 'click', () => emit( 'documents:mobile-details' ) )", $js, 'The footer Details button opens the Details sheet.' );
		$this->assertStringNotContainsString( 'toggleDetails', $js );
	}

	public function test_the_details_button_hides_on_a_swipe_down_and_returns(): void {
		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( "intent === 'down'", $js );
		$this->assertStringContainsString( "intent === 'up'", $js );
		$this->assertStringContainsString( 'setFabAway( false ), 2000 )', $js, 'It also returns after 2s of quiet.' );
		// The popover is gone (DPD-023): the button asks mobile.js to open the Details sheet.
		$this->assertStringNotContainsString( "'aria-expanded'", $js );
		$this->assertStringNotContainsString( "'aria-controls'", $js );
		$this->assertStringNotContainsString( 'details-panel', $js );
		$this->assertStringNotContainsString( 'setDetails', $js );
		$this->assertStringContainsString( "'aria-haspopup': 'dialog'", $js );
		$this->assertStringContainsString( 'documents-mobile__hint-text', $js );
		$this->assertStringContainsString( "// Any touch in the body counts as having read the hint.", $js );
		$this->assertMatchesRegularExpression( "#fab\\.addEventListener\\( 'click', \\(\\) => \\{\\s*dismissHint\\(\\);\\s*if \\( onDetails \\) \\{\\s*onDetails\\(\\);#", $js, 'Opening the details counts as an action.' );
		$this->assertStringContainsString( "'aria-live': 'polite'", $js );
	}

	public function test_the_spread_follows_the_space_on_touch_devices_too(): void {
		$layout = $this->read( 'blocks/document-modal/layout.js' );
		$this->assertStringContainsString( 'export function spreadFits', $layout );
		$this->assertStringContainsString( 'stageW ) >= 2 * Number( stageH ) * r', $layout );

		$mobile = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( 'spreadFits( {', $mobile );
		$this->assertStringContainsString( 'new ResizeObserver( onResize )', $mobile );

		$viewer = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( "matchMedia( '(pointer: coarse)' )", $viewer );
		$this->assertStringContainsString( 'useMouseEvents: ! source.textLayer && ! coarse.matches', $viewer );
		$this->assertStringContainsString( 'stepForStage( source )', $viewer );

		$view = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( 'onSwipe: ( direction ) => turn( direction )', $view );
		$this->assertStringContainsString( "readJson( 'data-mobile-strings' )", $view );
	}

	public function test_css_has_the_mobile_body_rules(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__frame\s*\{[^}]*touch-action: none#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__fabs\.is-away\s*\{[^}]*opacity: 0#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__footer\[hidden\],\s*\.documents-mobile__bar\[hidden\][^{]*\{[^}]*display: none#s', $css, 'hidden must beat display: grid/block.' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__book \.documents-modal__page--left::after,\s*\.documents-mobile__book \.documents-modal__page--right::after\s*\{\s*display: none#', $css, 'No seam on mobile.' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__pill--dark\s*\{#', $css );
		$this->assertMatchesRegularExpression( '#@media \(pointer: coarse\) and \(orientation: landscape\) and \(max-height: 520px\)#', $css );
		// A dialog selector never carries display flex or grid (it would show a closed dialog).
		$this->assertDoesNotMatchRegularExpression( '#(^|\n)\.documents-mobile\s*\{[^}]*display:\s*(flex|grid)#', $css );
	}

	public function test_a_touch_screen_held_sideways_gets_the_mobile_dialog_with_round_buttons(): void {
		$view = $this->read( 'blocks/document-modal/view.js' );
		$routing = $this->read( 'blocks/document-modal/routing.js' );
		$this->assertStringContainsString( "'(pointer: coarse) and (orientation: landscape) and (max-height: 520px)'", $routing );
		$this->assertStringContainsString( 'Boolean( wide.matches ) && ! sideways.matches', $routing );
		$this->assertStringContainsString( 'routing.onChange( swap )', $view );
		$this->assertStringContainsString( 'enforceSingleDialog', $view, 'Never both dialogs at once.' );
		$this->assertStringContainsString( 'SIDEWAYS_QUERY', $this->read( 'blocks/document-modal/mobile.js' ) );

		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertStringContainsString( 'const footerGone = state.pages < 2 || landscape.matches', $mobile );
		$this->assertStringContainsString( 'info: footerGone,', $mobile, 'The details button is shown whenever the footer is gone, even with no pages (DPD-023).' );
		$this->assertStringContainsString( 'pages: landscape.matches && state.pages > 1', $mobile );

		$js = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( 'fabs.append( pagesFab, fab )', $js, 'Pages on top of details.' );
		$this->assertStringContainsString( 'ui.fab.hidden = ! chrome.info', $js );

		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#@media \(min-width: 701px\) and \(not \(\(pointer: coarse\) and \(orientation: landscape\)#', $css, 'The mobile dialog is not hidden when sideways.' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__footer\s*\{\s*display: none#', $css );
	}

	public function test_rotating_fades_the_pages_out_and_back_in(): void {
		$mobile = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		$this->assertStringContainsString( 'fade( true )', $mobile );
		$this->assertStringContainsString( 'fade( false )', $mobile );
		$this->assertStringContainsString( "'opacity 120ms ease-out'", $mobile );
		$this->assertStringContainsString( "'opacity 260ms ease-in'", $mobile );
		$this->assertStringContainsString( 'reducedMotion.matches', $mobile, 'No fade with reduced motion.' );
		$this->assertStringContainsString( '}, 250 );', $mobile, 'It waits for the rotation to settle.' );

		$viewer = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( 'fadeBook( true )', $viewer );
		$this->assertStringContainsString( 'fadeBook( false )', $viewer );
	}

	public function test_landscape_has_no_header_and_the_round_buttons_line_up(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__header\s*\{\s*display: contents#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__close\s*\{\s*left: max\(12px#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__download\s*\{\s*right: max\(12px#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__edge--prev\s*\{\s*left: max\(12px#', $css, 'Same inset as the close button.' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__edge--next\s*\{\s*right: max\(12px#', $css, 'Same inset as the round buttons.' );
		$this->assertMatchesRegularExpression( '#\.documents-mobile__edge\s*\{[^}]*width: 38px[^}]*height: 38px#s', $css );
	}
}
