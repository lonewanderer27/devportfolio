<?php
/**
 * Presentations as a document page source: kinds, manifest, crop and wording (DPB-026).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group presentations
 */
class CPTDP_Test_Presentation_Source extends CPTDP_Pages_Test_Case {

	public function set_up(): void {
		parent::set_up();
		// Keep the scheduler from converting while these tests set documents up.
		add_filter( 'cptdp_pptx_available', '__return_false' );
		add_filter( 'cptdp_pages_server_available', '__return_false' );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function mimes(): array {
		return array(
			'pptx' => array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx' ),
			'ppt'  => array( 'application/vnd.ms-powerpoint', 'ppt' ),
			'odp'  => array( 'application/vnd.oasis.opendocument.presentation', 'odp' ),
			'pdf'  => array( 'application/pdf', 'pdf' ),
			'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '' ),
			'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx' ),
			'text' => array( 'text/plain', '' ),
		);
	}

	/**
	 * @dataProvider mimes
	 */
	public function test_the_kind_follows_the_mime_type(string $mime, string $kind): void {
		$id = $this->make_attachment( $mime );

		$this->assertSame( $kind, CPTDP_Page_Store::kind_of_attachment( $id ) );
	}

	public function test_a_generic_mime_falls_back_to_the_extension(): void {
		$zip = $this->make_attachment( 'application/zip' );
		update_attached_file( $zip, '/tmp/deck.PPTX' );
		$this->assertSame( 'pptx', CPTDP_Page_Store::kind_of_attachment( $zip ) );

		update_attached_file( $zip, '/tmp/archive.zip' );
		$this->assertSame( '', CPTDP_Page_Store::kind_of_attachment( $zip ) );

		$bin = $this->make_attachment( 'application/octet-stream' );
		update_attached_file( $bin, '/tmp/old.ppt' );
		$this->assertSame( 'ppt', CPTDP_Page_Store::kind_of_attachment( $bin ) );

		$this->assertSame( '', CPTDP_Page_Store::kind_of_attachment( 0 ) );
		$this->assertSame( '', CPTDP_Page_Store::kind_of_attachment( self::factory()->post->create() ), 'Not an attachment.' );
	}

	public function test_the_source_includes_presentations_but_the_pdf_helpers_stay_pdf_only(): void {
		$pptx = $this->make_presentation_attachment( 'pptx' );
		$id   = $this->make_document( $pptx );

		$this->assertSame( $pptx, CPTDP_Page_Store::source_attachment_id( $id ) );
		$this->assertSame( 0, CPTDP_Page_Store::pdf_attachment_id( $id ) );
		$this->assertSame( 'pptx', CPTDP_Page_Store::source_kind( $id ) );
		$this->assertSame( '', cptdp_document_pdf_url( $id ), 'The viewer must not treat a presentation as a PDF.' );

		$source = CPTDP_Page_Store::get_source( $id );
		$this->assertIsArray( $source );
		$this->assertSame( 'pptx', $source['kind'] );
		$this->assertSame( $pptx . ':' . $source['fingerprint'], $source['token'] );

		$pdf = $this->make_document( $this->make_pdf_attachment( 1 ) );
		$this->assertSame( 'pdf', CPTDP_Page_Store::get_source( $pdf )['kind'] );
		$this->assertSame( 'pdf', CPTDP_Page_Store::source_kind( $pdf ) );
		$this->assertNotSame( '', cptdp_document_pdf_url( $pdf ) );
	}

	public function test_other_files_are_not_a_source(): void {
		$id = $this->make_document( $this->make_attachment( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) );

		$this->assertNull( CPTDP_Page_Store::get_source( $id ) );
		$this->assertSame( '', CPTDP_Page_Store::source_kind( $id ) );
	}

	public function test_the_token_changes_when_the_presentation_is_replaced(): void {
		$attachment = $this->make_presentation_attachment( 'pptx' );
		$id         = $this->make_document( $attachment );
		$before     = CPTDP_Page_Store::get_source( $id )['token'];

		$this->overwrite_attachment_file( $attachment, str_repeat( 'x', 50 ) );

		$this->assertNotSame( $before, CPTDP_Page_Store::get_source( $id )['token'] );
	}

	public function test_the_manifest_marks_presentations_only(): void {
		$deck = $this->make_document( $this->make_presentation_attachment( 'odp' ) );
		$this->fake_pages( $deck, 3 );
		$manifest = CPTDP_Page_Store::get_manifest( $deck );
		$this->assertSame( 'odp', $manifest['source_kind'] );
		$this->assertSame( 'libreoffice', $manifest['converted_via'] );

		$pdf = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $pdf, 2 );
		$this->assertArrayNotHasKey( 'source_kind', CPTDP_Page_Store::get_manifest( $pdf ), 'A missing source_kind still means a PDF.' );
	}

	public function test_presentations_are_never_cropped_whatever_is_stored(): void {
		$deck = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$this->fake_pages( $deck, 2 );
		$manifest                = CPTDP_Page_Store::get_manifest( $deck );
		$manifest['crop_bottom'] = 0.2;
		update_post_meta( $deck, CPTDP_Page_Store::META_MANIFEST, $manifest );

		$pages = cptdp_get_document_pages( $deck );
		$this->assertSame( 0.0, $pages['crop_bottom'] );
		$this->assertSame( round( 1400 / 1980, 4 ), $pages['ratio'], 'The ratio is the whole slide.' );
		$this->assertSame( 0.0, CPTDP_Page_Store::recrop( $deck ), 'recrop does not analyse slides.' );

		$pdf = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $pdf, 2 );
		$manifest                = CPTDP_Page_Store::get_manifest( $pdf );
		$manifest['crop_bottom'] = 0.2;
		update_post_meta( $pdf, CPTDP_Page_Store::META_MANIFEST, $manifest );
		$this->assertSame( 0.2, cptdp_get_document_pages( $pdf )['crop_bottom'], 'PDFs keep their trim.' );
	}

	public function test_a_total_pages_mismatch_talks_about_slides(): void {
		$deck = $this->make_document( $this->make_presentation_attachment( 'pptx' ), 5 );
		$this->fake_pages( $deck, 3 );

		$this->assertSame( 'failed', CPTDP_Page_Store::get_status( $deck ) );
		$this->assertStringContainsString( 'presentation has 3 slides', CPTDP_Page_Store::get_error( $deck ) );
		$this->assertNull( cptdp_get_document_pages( $deck ) );
	}

	public function test_the_status_label_counts_slides_for_presentations(): void {
		$this->assertSame( 'Ready, 12 slides (converted from PowerPoint)', cptdp_document_pages_status_label( 'ready', 12, 'pptx' ) );
		$this->assertSame( 'Ready, 1 slide (converted from PowerPoint)', cptdp_document_pages_status_label( 'ready', 1, 'ppt' ) );
		$this->assertSame( 'Ready, 4 slides (converted from OpenDocument)', cptdp_document_pages_status_label( 'ready', 4, 'odp' ) );
		$this->assertSame( 'Ready, 24 pages', cptdp_document_pages_status_label( 'ready', 24, 'pdf' ) );
		$this->assertSame( 'Ready, 24 pages', cptdp_document_pages_status_label( 'ready', 24 ) );
	}

	public function test_documents_with_a_source_include_presentations(): void {
		$deck = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$pdf  = $this->make_document( $this->make_pdf_attachment( 1 ) );
		$none = $this->make_document();

		$ids = CPTDP_Page_Scheduler::documents_with_pdf();

		$this->assertContains( $deck, $ids );
		$this->assertContains( $pdf, $ids );
		$this->assertNotContains( $none, $ids );
	}
}
