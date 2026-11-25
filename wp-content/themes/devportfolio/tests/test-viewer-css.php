<?php
/**
 * Where the viewer rules live (DPB-NNN F2): the rules the three modals share are in blocks/shared/viewer.css,
 * blocks/document-modal/style.css keeps the Documents-only ones.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Viewer_Css extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/' . $relative );
	}

	public function set_up(): void {
		parent::set_up();
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
	}

	public function test_the_documents_stylesheet_keeps_only_documents_rules(): void {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $this->read( 'blocks/document-modal/style.css' ) );

		$this->assertDoesNotMatchRegularExpression( '/\.(?:collaterals|videos)-/', $css );
		$this->assertStringContainsString( '.documents-modal {', $css );
	}

	public function test_the_shared_rules_live_in_blocks_shared_and_load_first_on_documents(): void {
		$css = $this->read( 'blocks/shared/viewer.css' );
		$this->assertStringStartsWith( '/* Viewer rules shared by the Documents, Collaterals and Videos modals', $css );
		$this->assertStringContainsString( ".collaterals-mobile,\n.documents-modal,\n.collaterals-modal,\n.documents-mobile,\n.videos-mobile {", $css );

		$json = json_decode( $this->read( 'blocks/document-modal/block.json' ), true );
		$this->assertSame( array( 'devportfolio-viewer', 'file:./style.css' ), $json['style'] );
	}

	public function test_the_archives_load_the_shared_viewer_file(): void {
		$this->go_to( get_post_type_archive_link( 'video' ) );
		devportfolio_enqueue_page_styles();

		$this->assertTrue( wp_style_is( 'devportfolio-viewer', 'enqueued' ) );
		$this->assertStringEndsWith( '/blocks/shared/viewer.css', wp_styles()->registered['devportfolio-viewer']->src );
		wp_dequeue_style( 'devportfolio-viewer' );
	}
}
