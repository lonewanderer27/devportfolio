<?php
/**
 * Tests for the Brand Collaterals page arrows: the wiring in view.js and the hover reveal in the shared CSS.
 *
 * Regression: the arrows were hidden while active and visible while disabled, because the hover rule named only
 * the Documents body, and `els.next` in view.js was defined twice so the sidebar's "Next collaterals" logic hid
 * the page arrow. CSS and JS are read as text (no browser runner).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Arrows extends WP_UnitTestCase {

	private function read( string $relative ): string {
		$contents = file_get_contents( dirname( __DIR__ ) . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	/**
	 * The text of the `@media (hover: hover)` block of the arrow reveal.
	 */
	private function hover_block(): string {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertSame( 1, preg_match( '/@media \(hover: hover\) \{\s*\.documents-modal__arrow,.*?\n\}\n/s', $css, $m ), 'Arrow hover block not found.' );
		return $m[0];
	}

	public function test_arrows_are_hidden_with_a_pointer_for_both_modals(): void {
		$this->assertMatchesRegularExpression( '/\.collaterals-modal__arrow\s*\{\s*opacity: 0;/', $this->hover_block() );
	}

	public function test_hovering_the_collateral_body_shows_the_arrows(): void {
		$this->assertMatchesRegularExpression(
			'/\.collaterals-modal__body:hover \.collaterals-modal__arrow[,\s][^{]*\{\s*opacity: 1;/',
			$this->hover_block()
		);
	}

	public function test_hovering_keeps_a_disabled_collateral_arrow_dimmed(): void {
		$this->assertMatchesRegularExpression(
			'/\.collaterals-modal__body:hover \.collaterals-modal__arrow\[aria-disabled="true"\][^{]*\{\s*opacity: 0\.3;/',
			$this->hover_block()
		);
	}

	public function test_view_script_keeps_the_arrow_and_the_sidebar_section_apart(): void {
		$js = $this->read( 'blocks/collateral-modal/view.js' );

		// One `next:` key in the element map (the arrow); the sidebar section has its own name.
		$this->assertSame( 1, preg_match_all( '/^\s*next: \$\(/m', $js ) );
		$this->assertStringContainsString( "next: \$( '.collaterals-modal__arrow--next' )", $js );
		$this->assertStringContainsString( "nextSection: \$( '.collaterals-modal__next' )", $js );
		$this->assertStringContainsString( 'els.nextSection.hidden = ids.length === 0', $js );
		$this->assertStringNotContainsString( 'els.next.hidden', $js );
	}
}
