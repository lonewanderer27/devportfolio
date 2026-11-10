<?php
/**
 * Shared archive page stylesheet tests.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Page_Styles extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/assets/css/page.css' );
	}

	public function test_page_css_exists_and_holds_the_page_rules(): void {
		$css = $this->css();
		$this->assertStringContainsString( '--wp--style--global--content-size: 1440px', $css );
		$this->assertStringContainsString( 'overflow-x: clip', $css );
		$this->assertStringContainsString( 'font-size: clamp(44px, 6.4vw, 72px)', $css );
		$this->assertStringContainsString( 'font-weight: 200', $css );
		$this->assertStringContainsString( 'padding-top: 16px !important', $css );
		$this->assertStringContainsString( 'font-size: clamp(36px, 9vw, 52px)', $css );
	}

	public function test_shelf_css_no_longer_holds_page_rules(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/document-shelf/style.css' );
		$this->assertStringNotContainsString( '.documents-page {', $css );
		$this->assertStringNotContainsString( '.documents-page .documents-page__title', $css );
	}

	public function test_page_css_is_enqueued_on_both_archives_only(): void {
		require_once $this->theme_dir() . '/functions.php';
		add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_page_styles' );
		foreach ( array( 'document', 'collateral' ) as $type ) {
			wp_dequeue_style( 'devportfolio-page' );
			wp_deregister_style( 'devportfolio-page' );
			$this->go_to( get_post_type_archive_link( $type ) );
			do_action( 'wp_enqueue_scripts' );
			$this->assertTrue( wp_style_is( 'devportfolio-page', 'enqueued' ) );
			wp_dequeue_style( 'devportfolio-page' );
		}
		$this->go_to( home_url( '/' ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'devportfolio-page', 'enqueued' ) );
	}
}
