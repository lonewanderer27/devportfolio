<?php
/**
 * Tests for the mobile Pages sheet (DPD-020): the wording, the payload helpers (names as ranges, thumbnails as a
 * descriptor, both left out when empty) and the structure of the scripts and styles.
 *
 * There is no browser runner in the repo: the pure maths (names, thumbnail URLs, which tiles are due, the history
 * marker) is tested with node (`blocks/document-modal/tests/pages-sheet.test.mjs`, `deeplink.test.mjs`), and this
 * file checks the PHP side and that the markup, scripts and CSS keep the structure the plan asks for.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Modal_Mobile_Pages extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();
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

	/** The CSS from the Pages sheet block to the end of the file. */
	private function sheet_css(): string {
		$css   = $this->read( 'blocks/document-modal/style.css' );
		$start = strpos( $css, 'Mobile Pages sheet (DPD-020)' );
		$this->assertNotFalse( $start, 'The Pages sheet CSS block is missing.' );
		return substr( $css, (int) $start );
	}

	/* ---- Strings ---------------------------------------------------- */

	public function test_the_mobile_strings_carry_the_sheet_wording(): void {
		$strings = devportfolio_document_mobile_strings();
		foreach ( array( 'pagesTitle', 'galleryView', 'listView', 'current', 'pageLabel', 'slideLabel', 'sheetLabel', 'closePages' ) as $key ) {
			$this->assertArrayHasKey( $key, $strings, $key );
			$this->assertNotSame( '', $strings[ $key ], $key );
		}
		$this->assertSame( 'Pages', $strings['pagesTitle'] );
		$this->assertSame( 'current', $strings['current'] );
		foreach ( array( 'pageLabel', 'slideLabel', 'sheetLabel' ) as $key ) {
			$this->assertStringContainsString( '{n}', $strings[ $key ], $key );
		}
	}

	public function test_the_rendered_root_hands_the_sheet_strings_to_the_script(): void {
		$html = do_blocks( '<!-- wp:devportfolio/document-modal /-->' );
		preg_match( '/data-mobile-strings="([^"]+)"/', $html, $m );
		$strings = json_decode( html_entity_decode( $m[1] ), true );
		$this->assertSame( 'Gallery view', $strings['galleryView'] );
		$this->assertSame( 'List view', $strings['listView'] );
	}

	/* ---- Names as ranges -------------------------------------------- */

	public function test_names_are_packed_into_ranges(): void {
		$ranges = devportfolio_document_name_ranges( array( 'Intro', 'Intro', 'Plan', 'Plan', 'Plan' ) );
		$this->assertSame( array( array( 1, 'Intro' ), array( 3, 'Plan' ) ), $ranges );
	}

	public function test_a_blank_after_a_name_starts_an_empty_range(): void {
		$ranges = devportfolio_document_name_ranges( array( '', 'A', 'A', '', '' ) );
		$this->assertSame( array( array( 2, 'A' ), array( 4, '' ) ), $ranges );
	}

	public function test_no_names_gives_no_ranges(): void {
		$this->assertSame( array(), devportfolio_document_name_ranges( array( '', '', '' ) ) );
		$this->assertSame( array(), devportfolio_document_name_ranges( array() ) );
		$this->assertSame( array(), devportfolio_document_name_ranges( null ) );
		$this->assertSame( array(), devportfolio_document_name_ranges( 'Intro' ) );
	}

	public function test_names_are_plain_text_and_capped(): void {
		$ranges = devportfolio_document_name_ranges( array( '<b>Q3</b> &amp; Q4   plan', str_repeat( 'x', 300 ) ) );
		$this->assertSame( 'Q3 & Q4 plan', $ranges[0][1] );
		$this->assertSame( 200, strlen( $ranges[1][1] ) );
		$this->assertStringNotContainsString( '<', wp_json_encode( $ranges ) );
	}

	/* ---- Thumbnails as a descriptor --------------------------------- */

	public function test_thumbnails_that_follow_the_pattern_become_a_descriptor(): void {
		$base   = 'https://example.org/wp-content/uploads/cptdp-pages/12-ab12cd34-5/';
		$thumbs = array( $base . 't-001.webp', $base . 't-002.webp', $base . 't-003.webp' );
		$this->assertSame(
			array(
				'base'   => $base,
				'ext'    => '.webp',
				'count'  => 3,
				'width'  => 240,
				'height' => 320,
			),
			devportfolio_document_thumbs_descriptor( $thumbs, 240, 320 )
		);
	}

	public function test_thumbnails_that_do_not_follow_the_pattern_are_sent_as_a_list(): void {
		$thumbs = array( 'https://example.org/a/t-001.webp', 'https://example.org/b/t-002.webp' );
		$this->assertSame( $thumbs, devportfolio_document_thumbs_descriptor( $thumbs ) );
		$odd = array( 'https://example.org/a/first.webp', 'https://example.org/a/second.webp' );
		$this->assertSame( $odd, devportfolio_document_thumbs_descriptor( $odd ) );
	}

	public function test_an_incomplete_or_empty_thumbnail_set_sends_nothing(): void {
		$this->assertSame( array(), devportfolio_document_thumbs_descriptor( array() ) );
		$this->assertSame( array(), devportfolio_document_thumbs_descriptor( null ) );
		$this->assertSame( array(), devportfolio_document_thumbs_descriptor( array( 'https://example.org/a/t-001.webp', '' ) ) );
	}

	/* ---- The payload ------------------------------------------------- */

	public function test_a_document_without_stored_pages_leaves_both_keys_out(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_status' => 'publish',
				'post_title'  => 'No pages yet',
			)
		);

		$payload = devportfolio_document_modal_payload( $id );
		$this->assertIsArray( $payload );
		$this->assertArrayNotHasKey( 'pageNames', $payload );
		$this->assertArrayNotHasKey( 'thumbs', $payload );
		$this->assertSame( array(), devportfolio_document_pages_extras( $id ) );
	}

	/* ---- Scripts: loading, history, visible-only thumbnails ---------- */

	public function test_the_sheet_code_is_loaded_on_demand(): void {
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertStringContainsString( "import( './pages-sheet.js' )", $mobile );
		$this->assertDoesNotMatchRegularExpression( "/^import .* from '\.\/pages-sheet\.js';/m", $mobile, 'pages-sheet.js must not be a static import.' );
		$this->assertStringContainsString( "'documents:mobile-pages'", $mobile );
	}

	public function test_selecting_a_page_closes_the_sheet_and_turns_the_viewer(): void {
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertMatchesRegularExpression( '/onSelect:\s*\(\s*page\s*\)\s*=>\s*\{\s*closeSheet\(\s*\);\s*setPage\(\s*page\s*\);/', $mobile );
	}

	public function test_back_closes_only_the_sheet(): void {
		$history = $this->read( 'blocks/document-modal/sheet-back.js' );
		$this->assertStringContainsString( 'withSheetState( window.history.state )', $history );
		$this->assertStringContainsString( 'window.history.pushState', $history );
		$this->assertStringContainsString( 'hasSheetState( event.state )', $history );
		$this->assertStringContainsString( 'expectPop', $history );

		// mobile.js knows nothing about history: it only asks sheet-back.js (DPD-017 test).
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertStringContainsString( 'sheetHistory.push()', $mobile );
		$this->assertStringContainsString( 'sheetHistory.pop()', $mobile );
		$this->assertStringContainsString( 'sheetHistory.release()', $mobile );

		$view = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( 'skipNextPop', $view );
		$this->assertStringContainsString( 'window.history.go( -( 1 + extra ) )', $view );
		$this->assertStringContainsString( 'onClose: ( extra ) => finishClose( \'mobile\', extra )', $view );
	}

	public function test_the_page_keys_do_nothing_behind_the_open_sheet(): void {
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertMatchesRegularExpression( '/sheet\.isOpen\(\s*\)\s*\)\s*\{\s*return;/', $mobile );
	}

	public function test_thumbnails_are_fetched_only_when_visible(): void {
		$js = $this->read( 'blocks/document-modal/pages-sheet.js' );
		$this->assertStringContainsString( 'IntersectionObserver', $js );
		$this->assertStringContainsString( 'dataset.src', $js );
		$this->assertStringContainsString( 'ROWS_AHEAD', $js );
		// No `src` at build time, and not the browser's own lazy loading (it fetches well beyond the viewport).
		$this->assertStringNotContainsString( "loading: 'lazy'", $js );
		$this->assertStringContainsString( "fetchpriority: 'low'", $js );
		$this->assertStringContainsString( "decoding: 'async'", $js );
	}

	public function test_the_list_view_and_data_saver_request_no_images(): void {
		$js = $this->read( 'blocks/document-modal/pages-sheet.js' );
		$this->assertStringContainsString( 'saveData', $js );
		$this->assertStringContainsString( "view === 'list'", $js );
		$this->assertStringContainsString( 'cancelPending', $js );
	}

	public function test_the_sheet_is_built_once_and_listens_once(): void {
		$js = $this->read( 'blocks/document-modal/pages-sheet.js' );
		// One delegated listener per view, not one per tile.
		$this->assertSame( 2, substr_count( $js, "addEventListener( 'click', choose )" ) );
		$this->assertStringContainsString( 'applyCurrent', $js );
		$this->assertStringContainsString( "dialog.addEventListener( 'cancel'", $js );
		$this->assertStringContainsString( 'panel.inert = true', $js );
	}

	/* ---- Styles ------------------------------------------------------ */

	public function test_the_gallery_has_three_columns_capped_at_three_rows(): void {
		$css = $this->sheet_css();
		$this->assertMatchesRegularExpression( '/\.documents-mobile__pgr\s*\{[^}]*grid-template-columns:\s*repeat\(\s*3,/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__sheet-body\s*\{[^}]*max-height:\s*calc\(\s*3 \*/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__sheet-body\s*\{[^}]*overflow-y:\s*auto/', $css );
	}

	public function test_the_active_view_button_is_filled_dark_and_the_other_outlined(): void {
		$css = $this->sheet_css();
		$this->assertMatchesRegularExpression( '/\.documents-mobile__sheet-view\s*\{[^}]*background:\s*transparent;[^}]*border:\s*1px solid var\(--dm-ink\)/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__sheet-view\[aria-pressed="true"\]\s*\{[^}]*color:\s*var\(--dm-on-primary\);[^}]*background:\s*var\(--dm-ink\)/', $css );
	}

	public function test_the_current_page_has_a_thick_dark_outline_and_the_list_says_current(): void {
		$css = $this->sheet_css();
		$this->assertMatchesRegularExpression( '/\.documents-mobile__tile\.is-current\s*\{[^}]*box-shadow:\s*0 0 0 3px var\(--dm-ink\)/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__prow\.is-current \.documents-mobile__prow-n\s*\{[^}]*box-shadow:\s*0 0 0 2px var\(--dm-ink\)/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__prow-text\s*\{[^}]*font-weight:\s*700/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__prow-cur\s*\{[^}]*display:\s*none/', $css );
	}

	public function test_long_lists_skip_layout_and_paint_outside_the_view(): void {
		$css = $this->sheet_css();
		$this->assertStringContainsString( 'content-visibility: auto', $css );
		$this->assertStringContainsString( 'contain-intrinsic-size', $css );
		$this->assertStringContainsString( 'contain: layout paint', $css );
		$this->assertStringContainsString( 'overscroll-behavior: contain', $css );
	}

	public function test_the_sheet_adds_no_new_media_query(): void {
		// The routing media queries are shared with routing.js; the sheet only reuses existing conditions.
		preg_match_all( '/@media\s*([^{]+)\{/', $this->sheet_css(), $m );
		$allowed = array(
			'(pointer: coarse) and (orientation: landscape) and (max-height: 520px)',
			'(prefers-reduced-motion: reduce)',
		);
		foreach ( $m[1] as $condition ) {
			$this->assertContains( trim( $condition ), $allowed, 'New media query in the Pages sheet CSS: ' . trim( $condition ) );
		}
	}

	public function test_reduced_motion_turns_the_sheet_animation_off(): void {
		$css = $this->sheet_css();
		$this->assertMatchesRegularExpression( '/prefers-reduced-motion: reduce\)\s*\{[^}]*\.documents-mobile__dim,\s*\.documents-mobile__sheet\s*\{\s*animation:\s*none/s', $css );
	}

	public function test_visible_range_is_exported_for_the_sheet(): void {
		$this->assertStringContainsString( 'export function visibleRange(', $this->read( 'blocks/document-modal/pager.js' ) );
	}

	public function test_the_sheet_remembers_the_view_and_scroll_of_each_document(): void {
		$js = $this->read( 'blocks/document-modal/pages-sheet.js' );

		// One memory for the life of the sheet (not per open, not stored), written before the sheet hides.
		$this->assertStringContainsString( 'export function createSheetMemory(', $js );
		$this->assertSame( 1, substr_count( $js, '= createSheetMemory()' ), 'One memory per sheet.' );
		$this->assertDoesNotMatchRegularExpression( '/localStorage|sessionStorage/', $js, 'Kept in memory only.' );
		$this->assertMatchesRegularExpression( '/remember\(\);\s*\/\/ Before the sheet is hidden/', $js );

		// It opens in the remembered view at the remembered position, and each view keeps its own.
		$this->assertStringContainsString( 'memory.viewFor( cache.id )', $js );
		$this->assertStringContainsString( 'setView( view, memory.scrollFor( cache.id, view ) )', $js );
		$this->assertStringContainsString( 'memory.scrollFor( state.cache.id, next )', $js );

		// reset() drops the built nodes but not the memory: it survives closing and reopening the viewer.
		preg_match( '/reset\(\)\s*\{(.*?)\n\t\t\},?\n\t\};/s', $js, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( 'memory', $m[1] );
	}

	public function test_the_handle_can_be_dragged_to_dismiss_and_still_taps_and_keys(): void {
		$js  = $this->read( 'blocks/document-modal/pages-sheet.js' );
		$css = $this->sheet_css();

		// Pointer events on the handle and the title row, with the tap and keyboard paths kept.
		$this->assertStringContainsString( '[ grab, head ].forEach(', $js );
		foreach ( array( 'pointerdown', 'pointermove', 'pointerup', 'pointercancel' ) as $event ) {
			$this->assertStringContainsString( "'" . $event . "'", $js, $event );
		}
		$this->assertStringContainsString( 'setPointerCapture', $js );
		$this->assertStringContainsString( "grab.addEventListener( 'click'", $js, 'A tap, Enter and Space still close it.' );
		$this->assertStringContainsString( 'swallowClick', $js, 'The click that ends a drag must not close it twice.' );
		$this->assertStringContainsString( 'dragOutcome(', $js );

		// The view-switch buttons in the title row never start a drag; reduced motion skips the animation.
		$this->assertStringContainsString( "closest( '[data-view]' )", $js );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $js );

		// The drag leaves nothing behind when the sheet hides or shows.
		$this->assertGreaterThanOrEqual( 3, substr_count( $js, 'clearDrag()' ) );

		// The browser must not scroll or select while the handle is dragged.
		$this->assertMatchesRegularExpression( '/\.documents-mobile__grab,\s*\.documents-mobile__sheet-head\s*\{[^}]*touch-action:\s*none/s', $css );
	}

	public function test_the_sheets_own_history_moves_never_reach_the_router_or_the_filters(): void {
		$js = $this->read( 'blocks/document-modal/sheet-back.js' );

		// WordPress's Interactivity Router reloads the page on a popstate to a URL it has not cached
		// (`?document_slug=...`), which re-opened the whole modal the first time a page was chosen.
		$this->assertMatchesRegularExpression( "/addEventListener\(\s*'popstate',.*?\n\t\t(?:true|\x60?true)\s*\n\t\);/s", $js, 'Registered in the capture phase, so it runs before the router.' );
		$this->assertSame( 2, substr_count( $js, 'event.stopImmediatePropagation()' ), 'Both the sheet\'s own pop and Back from the sheet are stopped.' );

		// The category filters must not start a View Transition (a snapshot of the whole page, modal included)
		// for a history move that does not change the category, and a real category change is kept from the router.
		// Listeners run in the order they were added, so the one that stops the event is added as the script runs
		// (ahead of the router, which is imported later), not at DOMContentLoaded.
		$categories = $this->read( 'blocks/document-categories/view.js' );
		$this->assertMatchesRegularExpression( '/onPopState = function \( event \) \{\s*var slug = slugFromUrl\( group \);\s*if \( slug === activeSlug\(\) \) \{\s*return;\s*\}\s*applyAnimated\( slug \);/', $categories );
		$this->assertStringContainsString( 'event.stopImmediatePropagation()', $categories );
		$this->assertLessThan( strpos( $categories, "document.addEventListener( 'DOMContentLoaded'" ), strpos( $categories, "window.addEventListener(\n\t\t'popstate'" ), 'Added before DOMContentLoaded.' );

		// The modal's own Back/Forward handler stops the event when it acts on it (and on the sheet's stale entry).
		$view = $this->read( 'blocks/document-modal/view.js' );
		$this->assertGreaterThanOrEqual( 5, substr_count( $view, 'event.stopImmediatePropagation()' ) );
		$this->assertStringContainsString( 'slug && isOpen() && hasSheetState( event.state )', $view );
	}
}
