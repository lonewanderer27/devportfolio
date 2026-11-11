<?php
/**
 * The native viewer URLs and the PDF URL resolve a Brand Collateral's file the way they resolve a document's (DPC-023, DPC-024).
 *
 * The theme's modal payload calls these helpers for a collateral; they resolve the file through the source
 * registry (CPTDP_Page_Store::file_meta_key()), so no plugin change was needed. These tests keep it that way.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group collaterals
 * @group native-viewer
 */
class CPTDP_Test_Collateral_Native_Urls extends CPTDP_Pages_Test_Case {

	public function set_up(): void {
		parent::set_up();
		add_filter( 'cptdp_pptx_available', '__return_false' ); // Never convert while the fixtures are made.
	}

	private function switches( array $on ): void {
		update_option( 'cptdp_documents', $on );
	}

	public function test_the_native_pptx_url_is_the_collateral_file_when_the_setting_is_on(): void {
		$this->switches( array( 'native_pptx' => true ) );
		$attachment = $this->make_presentation_attachment( 'pptx' );
		$id         = $this->make_collateral( $attachment );

		$this->assertSame( wp_get_attachment_url( $attachment ), cptdp_document_native_pptx_url( $id ) );
	}

	public function test_the_native_pptx_url_is_empty_unless_every_rule_holds_for_a_collateral(): void {
		$pptx = $this->make_collateral( $this->make_presentation_attachment( 'pptx' ) );

		$this->switches( array( 'native_pptx' => false ) );
		$this->assertSame( '', cptdp_document_native_pptx_url( $pptx ), 'Off by default.' );

		$this->switches( array( 'native_pptx' => true ) );
		$this->assertNotSame( '', cptdp_document_native_pptx_url( $pptx ) );

		$ppt = $this->make_collateral( $this->make_presentation_attachment( 'ppt' ) );
		$this->assertSame( '', cptdp_document_native_pptx_url( $ppt ), 'The viewer cannot read the legacy .ppt format.' );

		$odp = $this->make_collateral( $this->make_presentation_attachment( 'odp' ) );
		$this->assertSame( '', cptdp_document_native_pptx_url( $odp ), 'Nor OpenDocument.' );

		$this->assertSame( '', cptdp_document_native_pptx_url( $this->make_collateral() ), 'No file.' );
	}

	public function test_a_collateral_pptx_over_the_size_limit_is_not_offered(): void {
		$this->switches( array( 'native_pptx' => true ) );
		$id = $this->make_collateral( $this->make_presentation_attachment( 'pptx' ) );
		add_filter(
			'cptdp_native_pptx_max_bytes',
			static function (): int {
				return 10;
			}
		);

		$this->assertSame( '', cptdp_document_native_pptx_url( $id ) );
	}

	public function test_the_native_xlsx_url_is_the_collateral_file_when_the_setting_is_on(): void {
		$this->switches( array( 'native_xlsx' => true ) );
		$attachment = $this->make_spreadsheet_attachment( 'xlsx' );
		$id         = $this->make_collateral( $attachment );

		$this->assertSame( wp_get_attachment_url( $attachment ), cptdp_document_native_xlsx_url( $id ) );

		$this->switches( array( 'native_xlsx' => false ) );
		$this->assertSame( '', cptdp_document_native_xlsx_url( $id ), 'Off again: no URL.' );
	}

	public function test_the_pdf_url_is_the_collateral_pdf_and_empty_for_a_presentation(): void {
		$pdf = $this->make_pdf_attachment( 2 );
		$id  = $this->make_collateral( $pdf );

		$this->assertSame( wp_get_attachment_url( $pdf ), cptdp_document_pdf_url( $id ) );
		$this->assertSame( '', cptdp_document_pdf_url( $this->make_collateral( $this->make_presentation_attachment( 'pptx' ) ) ), 'The viewer never hands a presentation to pdf.js.' );
	}
}
