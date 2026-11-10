<?php
/**
 * Tests for the mobile document modal shell (DPD-017, DPD-018, DPD-019).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Modal_Mobile extends WP_UnitTestCase {

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

	private function render_modal(): string {
		return do_blocks( '<!-- wp:devportfolio/document-modal /-->' );
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	public function test_renders_mobile_dialog_after_desktop_one(): void {
		$html = $this->render_modal();
		$this->assertSame( 1, substr_count( $html, '<dialog class="documents-modal"' ) );
		$this->assertSame( 1, substr_count( $html, '<dialog class="documents-mobile"' ) );
		$this->assertLessThan( strpos( $html, 'documents-modal__data' ), strpos( $html, 'documents-mobile' ) );
		$this->assertSame( 1, substr_count( $html, 'class="documents-modal__data"' ) );
	}

	public function test_mobile_dialog_has_header_body_footer_and_empty_stage(): void {
		$html = $this->render_modal();
		$header = strpos( $html, 'documents-mobile__header' );
		$body   = strpos( $html, 'documents-mobile__body' );
		$footer = strpos( $html, 'documents-mobile__footer' );
		$this->assertLessThan( $body, $header );
		$this->assertLessThan( $footer, $body );
		$this->assertStringContainsString( '<div class="documents-mobile__stage"></div>', $html );
	}

	public function test_mobile_header_is_accessible_and_in_order(): void {
		$html = $this->render_modal();
		$this->assertSame( 1, preg_match( '#<dialog class="documents-mobile" aria-labelledby="([^"]+)"#', $html, $matches ) );
		$this->assertStringContainsString( 'aria-label="Close viewer"', $html );
		$this->assertStringContainsString( 'M6 6l12 12M18 6L6 18', $html );
		$this->assertStringContainsString( 'role="button"', $html );
		$this->assertStringContainsString( 'M12 4v11M7 11l5 5 5-5M5 20h14', $html );
		$this->assertLessThan( strpos( $html, 'documents-mobile__title' ), strpos( $html, 'documents-mobile__close' ) );
		$this->assertLessThan( strpos( $html, 'documents-mobile__download' ), strpos( $html, 'documents-mobile__title' ) );
		$this->assertStringContainsString( 'id="' . $matches[1] . '"', $html );
	}

	public function test_mobile_footer_is_always_visible_and_progress_bar_is_in_body(): void {
		$html = $this->render_modal();
		$this->assertStringContainsString( 'documents-mobile__pages', $html );
		$this->assertStringContainsString( 'Pages', $html );
		$this->assertStringContainsString( 'documents-mobile__position', $html );
		$this->assertStringContainsString( 'documents-mobile__details', $html );
		$this->assertStringContainsString( 'Details', $html );
		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'aria-valuemin="0"', $html );
		$this->assertStringContainsString( 'aria-valuemax="100"', $html );
		$this->assertStringNotContainsString( 'documents-mobile__footer" hidden', $html );
		$body = substr( $html, strpos( $html, 'documents-mobile__body' ), strpos( $html, 'documents-mobile__footer' ) );
		$this->assertStringContainsString( 'documents-mobile__bar', $body );
	}

	public function test_mobile_footer_buttons_are_real_buttons_and_translatable(): void {
		$html = $this->render_modal();
		$this->assertSame( 2, preg_match_all( '#<button class="documents-mobile__(?:pages|details)[^>]+type="button"#', $html ) );
		$php = $this->read( 'blocks/document-modal/render.php' );
		$this->assertStringContainsString( "esc_html_x( 'Pages'", $php );
		$this->assertStringContainsString( "esc_html_x( 'Details'", $php );
	}

	public function test_mobile_css_is_full_screen_and_paired_with_desktop_breakpoint(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertStringContainsString( 'width: 100%;', $css );
		$this->assertStringContainsString( 'height: 100dvh;', $css );
		$this->assertStringContainsString( 'max-width: none;', $css );
		$this->assertStringContainsString( 'max-height: none;', $css );
		$this->assertStringContainsString( 'margin: 0;', $css );
		$this->assertStringContainsString( 'border: 0;', $css );
		$this->assertStringContainsString( 'border-radius: 0;', $css );
		$this->assertStringContainsString( '.documents-mobile::backdrop', $css );
		$this->assertStringContainsString( 'background: transparent', $css );
		$this->assertMatchesRegularExpression( '#@media \(min-width: 701px\)[^{]*\{\s*\.documents-mobile\s*\{\s*display: none#s', $css );
		$this->assertStringContainsString( 'grid-template-columns: 1fr auto 1fr', $css );
		$this->assertStringContainsString( 'transition: width var(--dm-dur)', $css );
		$this->assertStringContainsString( 'bottom: 0;', $css );
	}

	public function test_mobile_routing_and_swap_are_present(): void {
		$view = $this->read( 'blocks/document-modal/view.js' );
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertStringContainsString( "from './mobile.js'", $view );
		$this->assertStringContainsString( "from './download.js'", $view );
		$this->assertStringContainsString( 'startViewTransition', $view );
		$this->assertStringContainsString( 'prefers-reduced-motion', $view );
		$this->assertStringNotContainsString( 'history.', $mobile );
		$this->assertStringNotContainsString( "from './deeplink.js'", $mobile );
		$this->assertStringContainsString( 'els.bar.hidden = state.pages < 2', $mobile );
	}
}
