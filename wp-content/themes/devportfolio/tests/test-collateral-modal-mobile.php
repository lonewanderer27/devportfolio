<?php
/**
 * Tests for the Brand Collaterals mobile dialog (DPC-015 to DPC-019): the header, the viewer, the footer, the Pages
 * and Details sheets, and the history rules. Markup and CSS are checked as text; the browser check is in the plan.
 *
 * The mobile body is the shared Documents one (shared/mobile.js, mobile-viewer.js, sheet-controller.js), so these tests
 * check that the collateral dialog is wired to it with its own prefix, events and sheet, not that it has copies.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Modal_Mobile extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/collateral-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	private function render_modal(): string {
		return trim( do_blocks( '<!-- wp:devportfolio/collateral-modal /-->' ) );
	}

	private function xpath( string $html ): DOMXPath {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	/** The first rule whose selector list contains $selector (the Documents rules list both prefixes now). */
	private function rule_containing( string $css, string $selector ): string {
		$pattern = '/' . preg_quote( $selector, '/' ) . '[^{};]*\{([^}]*)\}/s';
		return preg_match( $pattern, $css, $match ) ? $match[1] : '';
	}

	// ---- DPC-015 header ----

	public function test_the_mobile_header_is_close_then_title_then_download(): void {
		$xpath  = $this->xpath( $this->render_modal() );
		$header = $xpath->query( "//dialog[contains(@class,'collaterals-mobile')]//header[contains(@class,'collaterals-mobile__header')]" )->item( 0 );
		$this->assertNotNull( $header, 'The mobile dialog has its header row.' );

		$order = array();
		foreach ( $header->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$order[] = $child->getAttribute( 'class' );
			}
		}
		$this->assertStringContainsString( 'collaterals-mobile__close', $order[0] );
		$this->assertStringContainsString( 'collaterals-mobile__title', $order[1] );
		$this->assertStringContainsString( 'collaterals-mobile__download', $order[2] );
	}

	public function test_the_close_button_and_the_title_keep_their_class_names_and_names(): void {
		$xpath = $this->xpath( $this->render_modal() );
		$close = $xpath->query( "//button[contains(@class,'collaterals-mobile__close')]" )->item( 0 );
		$this->assertSame( 'Close viewer', $close->getAttribute( 'aria-label' ) );
		$title = $xpath->query( "//*[contains(@class,'collaterals-mobile__title')]" )->item( 0 );
		$this->assertNotNull( $title );
		$this->assertNotSame( '', $title->getAttribute( 'id' ), 'The dialog is labelled by its title.' );
	}

	public function test_a_long_title_is_truncated_with_an_ellipsis_in_the_shared_rule(): void {
		$block = $this->rule_containing( $this->read( 'blocks/document-modal/style.css' ), '.collaterals-mobile__title' );
		$this->assertStringContainsString( 'text-overflow: ellipsis', $block );
		$this->assertStringContainsString( 'white-space: nowrap', $block );
		$this->assertStringContainsString( 'min-width: 0', $block );
	}

	public function test_held_sideways_the_header_goes_and_close_and_download_float_at_the_corners(): void {
		$css      = $this->read( 'blocks/document-modal/style.css' );
		$sideways = '(pointer: coarse) and (orientation: landscape) and (max-height: 520px)';
		$this->assertStringContainsString( "@media {$sideways}", $css );
		$this->assertMatchesRegularExpression( '#\.collaterals-mobile__header[^{]*\{\s*display: contents#', $css, 'The header row is not drawn held sideways.' );
		$this->assertMatchesRegularExpression( '#\.collaterals-mobile__close[^{]*\{\s*left: max\(12px#', $css );
		$this->assertMatchesRegularExpression( '#\.collaterals-mobile__download[^{]*\{\s*right: max\(12px#', $css );
	}

	public function test_the_dialog_stylesheet_mirrors_the_documents_mobile_rules_with_its_own_prefix(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertStringContainsString( '.collaterals-mobile,', $css, 'The token root covers the collateral mobile dialog.' );
		$this->assertStringContainsString( '.collaterals-mobile__stage', $css );
		$this->assertStringContainsString( '.collaterals-mobile__state', $css );
		$this->assertStringContainsString( '.collaterals-mobile__sheet--details', $css );
	}

	// ---- DPC-016 footer and round buttons ----

	public function test_the_footer_has_pages_position_and_details_as_real_buttons(): void {
		$xpath = $this->xpath( $this->render_modal() );
		$this->assertNotNull( $xpath->query( "//footer[contains(@class,'collaterals-mobile__footer')]//button[contains(@class,'collaterals-mobile__pages')]" )->item( 0 ) );
		$this->assertNotNull( $xpath->query( "//footer[contains(@class,'collaterals-mobile__footer')]//b[contains(@class,'collaterals-mobile__position')]" )->item( 0 ) );
		$this->assertNotNull( $xpath->query( "//footer[contains(@class,'collaterals-mobile__footer')]//button[contains(@class,'collaterals-mobile__details')]" )->item( 0 ) );
	}

	public function test_the_progress_bar_is_a_progressbar_hidden_until_there_are_two_pages(): void {
		$xpath = $this->xpath( $this->render_modal() );
		$bar   = $xpath->query( "//*[contains(@class,'collaterals-mobile__bar')]" )->item( 0 );
		$this->assertSame( 'progressbar', $bar->getAttribute( 'role' ) );
		$this->assertTrue( $bar->hasAttribute( 'hidden' ) );
	}

	public function test_the_mobile_status_line_is_the_viewer_status_with_the_mobile_state_class(): void {
		$xpath  = $this->xpath( $this->render_modal() );
		$status = $xpath->query( "//*[contains(@class,'collaterals-mobile__state')]" )->item( 0 );
		$this->assertNotNull( $status );
		$this->assertSame( 'status', $status->getAttribute( 'role' ) );
		$this->assertStringContainsString( 'collaterals-modal__state', $status->getAttribute( 'class' ) );
	}

	public function test_one_page_hides_the_footer_and_the_bar_and_sideways_the_round_buttons_take_over(): void {
		$mobile = $this->read( 'blocks/shared/mobile.js' );
		$this->assertStringContainsString( 'const footerGone = state.pages < 2 || landscape.matches;', $mobile );
		$this->assertStringContainsString( "viewer.setChrome( { info: footerGone, pages: landscape.matches && state.pages > 1 } );", $mobile );
		$this->assertStringContainsString( "els.bar.hidden = state.pages < 2;", $mobile );
	}

	public function test_the_footer_and_round_buttons_emit_the_collateral_events(): void {
		$view = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertStringContainsString( "eventPrefix: 'collaterals'", $view );
		$mobile = $this->read( 'blocks/shared/mobile.js' );
		$this->assertStringContainsString( '`${ eventPrefix }:mobile-pages`', $mobile );
		$this->assertStringContainsString( '`${ eventPrefix }:mobile-details`', $mobile );
	}

	// ---- DPC-017 Pages sheet ----

	public function test_the_pages_sheet_is_registered_and_closes_on_rotation(): void {
		$mobile = $this->read( 'blocks/shared/mobile.js' );
		$this->assertStringContainsString( "sheets.register( 'pages', {", $mobile );
		$this->assertStringContainsString( 'closeOnRotate: true,', $mobile );
		$this->assertStringContainsString( 'mod.createPagesSheet( {', $mobile );
	}

	public function test_the_pages_sheet_shell_gets_the_modal_prefix(): void {
		$sheet = $this->read( 'blocks/shared/pages-sheet.js' );
		$this->assertMatchesRegularExpression( '#onDismiss: \(\) => close\( false \),\s*prefix,#', $sheet, 'The shell reads the modal\'s own classes, not the Documents ones.' );
	}

	public function test_the_sheet_memory_is_kept_per_collateral_id(): void {
		$sheet = $this->read( 'blocks/shared/pages-sheet.js' );
		$this->assertStringContainsString( 'memory.save( cache.id', $sheet );
		$this->assertStringContainsString( 'memory.viewFor( cache.id )', $sheet );
	}

	public function test_the_payload_carries_thumbs_and_names_only_when_stored(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'collateral', 'post_status' => 'publish', 'post_title' => 'No pages yet' ) );
		$item = devportfolio_collateral_modal_payload( $id );
		$this->assertArrayNotHasKey( 'thumbs', $item, 'Omitted when none are stored (the same rule as Documents).' );
		$this->assertArrayNotHasKey( 'pageNames', $item );
		$this->assertArrayHasKey( 'images', $item );
	}

	// ---- DPC-018 Details sheet ----

	public function test_the_details_sheet_is_a_collateral_module_on_the_shared_shell_and_does_not_close_on_rotation(): void {
		$mobile = $this->read( 'blocks/shared/mobile.js' );
		$this->assertStringContainsString( "sheets.register( 'details', {", $mobile );
		$this->assertSame( 1, substr_count( $mobile, 'closeOnRotate: true,' ), 'Only the Pages sheet closes on rotation.' );

		$details = $this->read( 'blocks/collateral-modal/details-sheet.js' );
		$this->assertStringContainsString( "import { createBottomSheet } from '../shared/bottom-sheet.js';", $details );
		$this->assertStringContainsString( 'prefix,', $details, 'The shell is built with the collateral prefix.' );
		$this->assertStringContainsString( 'applyDownload( download, item );', $details );
		$this->assertStringContainsString( 'linkFor( window.location, current.slug )', $details );
		$this->assertStringContainsString( 'copyText( link, {', $details );
	}

	public function test_the_details_sheet_keeps_the_collateral_view_loaded_from_its_own_folder(): void {
		$view = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertStringContainsString( "loadDetails: () => import( './details-sheet.js' )", $view );
	}

	// ---- DPC-019 viewer and gestures ----

	public function test_the_gesture_constants_are_the_ones_the_documents_viewer_uses(): void {
		$gestures = $this->read( 'blocks/shared/gestures.js' );
		$this->assertStringContainsString( 'export const EDGE_ZONE = 0.2;', $gestures );
		$this->assertStringContainsString( 'export const DEAD_STRIP = 8;', $gestures );
		$this->assertStringContainsString( 'export const ZOOM_MIN = 1;', $gestures );
		$this->assertStringContainsString( 'export const ZOOM_MAX = 4;', $gestures );
	}

	public function test_the_hint_and_the_two_landscape_pills_are_in_the_mobile_strings(): void {
		$xpath = $this->xpath( $this->render_modal() );
		$root  = $xpath->query( "//*[contains(@class,'collaterals-modal-root')]" )->item( 0 );
		$this->assertNotNull( $root );
		$strings = json_decode( $root->getAttribute( 'data-mobile-strings' ), true );
		$this->assertSame( 'swipe or tap edges', $strings['hint'] );
		$this->assertSame( 'Rotate for a bigger view', $strings['rotate'] );
		$this->assertSame( 'Pinch to zoom', $strings['pinch'] );
		$this->assertSame( 'Collateral details', $strings['details'] );
		$this->assertSame( 'Copy link', $strings['copyLink'] );
	}

	public function test_the_rotation_fade_timings_are_in_the_shared_viewer(): void {
		$viewer = $this->read( 'blocks/shared/mobile-viewer.js' );
		$this->assertStringContainsString( "'opacity 120ms ease-out'", $viewer, 'Pages fade out in 120 ms.' );
		$this->assertStringContainsString( "'opacity 260ms ease-in'", $viewer, 'and in over 260 ms.' );
		$this->assertStringContainsString( '}, 250 );', $viewer, 'Once the size has been still for 250 ms.' );
	}

	public function test_the_mobile_dialog_is_mounted_by_the_shared_modal_and_the_viewer_is_not_built_twice(): void {
		$view = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertStringContainsString( "import { createMobileModal } from '../shared/mobile.js';", $view );
		$this->assertSame( 0, substr_count( $view, 'createMobileViewer(' ), 'The shared mobile modal builds the viewer.' );
		$this->assertStringNotContainsString( 'new PageFlip', $view );
		$this->assertStringContainsString( 'mobile.show( item, 1 );', $view );
		$this->assertStringContainsString( 'mobile.state().page', $view, 'The page is kept across the 700px swap.' );
	}

	// ---- History (AGENTS.md router rule) ----

	public function test_back_closes_only_a_sheet_and_the_popstate_listener_is_registered_at_script_run(): void {
		$view = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertStringContainsString( "window.addEventListener(\n\t\t'popstate',", $view );
		$this->assertLessThan(
			strpos( $view, 'routing.onChange( swapDialogs );' ),
			strpos( $view, "window.addEventListener(\n\t\t'popstate'" ),
			'The listener is registered while the script runs, not inside a lazy import.'
		);
		$this->assertStringContainsString( 'hasSheetState( event.state )', $view, 'Forward onto a sheet entry does nothing.' );
		$this->assertStringContainsString( 'finishClose( extra )', $view, 'The mobile close reconciles the sheet entry.' );
	}

	public function test_the_sheet_controller_owns_its_history_listener_outside_the_lazy_import(): void {
		$controller = $this->read( 'blocks/shared/sheet-controller.js' );
		$this->assertStringContainsString( 'createSheetHistory', $controller );
		$this->assertStringContainsString( 'never inside a lazy', $controller );
	}
}
