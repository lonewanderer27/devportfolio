<?php
/**
 * Tests for the Brand Collaterals archive shell.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Page extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function test_template_has_the_brand_collaterals_shell(): void {
		$markup = (string) file_get_contents( $this->theme_dir() . '/templates/archive-collateral.html' );
		$this->assertStringContainsString( '<h3 class="wp-block-heading documents-page__title">Brand Collaterals</h3>', $markup );
		$this->assertStringContainsString( 'documents-page__content', $markup );
		$this->assertStringContainsString( '"taxonomy":"collateral_category"', $markup );
		$this->assertStringContainsString( 'template-part {"slug":"header"', $markup );
		$this->assertStringContainsString( 'template-part {"slug":"footer"', $markup );
		$this->assertStringNotContainsString( 'document-shelf', $markup );
		$this->assertStringNotContainsString( 'document-modal', $markup );
	}

	public function test_template_is_selected_for_the_collateral_archive(): void {
		$this->assertFileExists( $this->theme_dir() . '/templates/archive-collateral.html' );
		$this->assertStringContainsString( 'archive-collateral.html', basename( $this->theme_dir() . '/templates/archive-collateral.html' ) );
	}
}
