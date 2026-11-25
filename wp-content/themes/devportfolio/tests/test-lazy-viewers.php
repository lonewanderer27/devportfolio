<?php
/**
 * The three viewers load on first use (DPB-NNN F1): boot.js entries, the shared popstate listener and the
 * preloaded viewer stylesheets.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Lazy_Viewers extends WP_UnitTestCase {

	const BLOCKS = array( 'document-modal', 'collateral-modal', 'video-modal' );

	/**
	 * The block's own style handle: the Documents block lists the shared viewer first in its `style` array, so
	 * WordPress names its own file `-style-2`.
	 */
	private function style_handle( string $block ): string {
		return 'devportfolio-' . $block . ( 'document-modal' === $block ? '-style-2' : '-style' );
	}

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/' . $relative );
	}

	public function set_up(): void {
		parent::set_up();
		require_once $this->theme_dir() . '/functions.php';
	}

	public function test_each_modal_block_prints_only_its_boot_script(): void {
		foreach ( self::BLOCKS as $block ) {
			$json = json_decode( $this->read( 'blocks/' . $block . '/block.json' ), true );
			$this->assertSame( 'file:./boot.js', $json['viewScriptModule'], $block );
		}
	}

	public function test_each_boot_imports_the_lazy_loader_and_the_view_only_on_demand(): void {
		$handles = devportfolio_deferred_style_handles();
		foreach ( self::BLOCKS as $block ) {
			$boot = $this->read( 'blocks/' . $block . '/boot.js' );
			$this->assertSame( 1, preg_match_all( '/^import /m', $boot ), $block . ': one static import' );
			$this->assertStringContainsString( "from '../shared/lazy-modal.js'", $boot, $block );
			$this->assertStringContainsString( "load: () => import( './view.js' )", $boot, $block );
			$this->assertStringContainsString( "'devportfolio-viewer-css', '" . $this->style_handle( $block ) . "-css'", $boot, $block );
			$this->assertContains( $this->style_handle( $block ), $handles, $block );
		}
		$this->assertContains( 'devportfolio-viewer', $handles );
	}

	public function test_back_and_forward_reach_every_viewer_through_one_early_listener(): void {
		$hub = $this->read( 'blocks/shared/history-events.js' );
		$this->assertStringContainsString( "target.addEventListener( 'popstate', dispatch, true );", $hub );
		$this->assertStringContainsString( "import { historyEvents } from './history-events.js';", $this->read( 'blocks/shared/lazy-modal.js' ) );
		$this->assertStringContainsString( 'events.install();', $this->read( 'blocks/shared/lazy-modal.js' ) );

		foreach ( array( 'blocks/document-modal/view.js', 'blocks/collateral-modal/view.js', 'blocks/video-modal/view.js', 'blocks/shared/sheet-back.js' ) as $file ) {
			$js = $this->read( $file );
			$this->assertMatchesRegularExpression( "/historyEvents\\.addEventListener\\(\\s*'popstate'/", $js, $file );
			$this->assertDoesNotMatchRegularExpression( "/window\\.addEventListener\\(\\s*'popstate'/", $js, $file );
		}
	}

	public function test_viewer_styles_are_printed_as_a_preload_that_applies_itself(): void {
		$tag = devportfolio_defer_viewer_style(
			"<link rel='stylesheet' id='devportfolio-document-modal-style-2-css' href='https://example.org/a.css?ver=1' media='all' />\n",
			'devportfolio-document-modal-style-2',
			'https://example.org/a.css?ver=1',
			'all'
		);

		$this->assertStringContainsString( "<link rel='preload' as='style' id='devportfolio-document-modal-style-2-css'", $tag );
		$this->assertStringContainsString( "href='https://example.org/a.css?ver=1'", $tag );
		$this->assertStringContainsString( "onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
		$this->assertStringNotContainsString( "rel='stylesheet' id=", $tag );
		$this->assertStringContainsString( "add_filter( 'style_loader_tag', 'devportfolio_defer_viewer_style', 10, 4 );", $this->read( 'inc/viewer.php' ) );
	}

	public function test_other_styles_are_left_alone(): void {
		$tag = "<link rel='stylesheet' id='devportfolio-contact-style-css' href='https://example.org/c.css' media='all' />\n";
		$this->assertSame( $tag, devportfolio_defer_viewer_style( $tag, 'devportfolio-contact-style', 'https://example.org/c.css', 'all' ) );
	}

	public function test_admin_screens_keep_the_plain_stylesheet(): void {
		$tag = "<link rel='stylesheet' id='devportfolio-viewer-css' href='https://example.org/v.css' media='all' />\n";
		set_current_screen( 'edit-post' );
		$this->assertSame( $tag, devportfolio_defer_viewer_style( $tag, 'devportfolio-viewer', 'https://example.org/v.css', 'all' ) );
		set_current_screen( 'front' );
	}
}
