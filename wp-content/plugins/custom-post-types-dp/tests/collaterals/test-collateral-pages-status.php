<?php
/**
 * Collateral page set status and the sample loading image (DPC-025).
 *
 * @package CustomPostTypesDP
 */

/**
 * The read-only status API, the loading SVG and the admin loading image.
 */
class CPTDP_Test_Collateral_Pages_Status extends CPTDP_Pages_Test_Case {

	/**
	 * Render a list cell and return its HTML.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	private function column( string $column, int $post_id ): string {
		ob_start();
		cptdp_collateral_column_content( $column, $post_id );
		return (string) ob_get_clean();
	}

	/**
	 * Render the Page images box and return its HTML.
	 *
	 * @param int $post_id Post ID.
	 */
	private function pages_box( int $post_id ): string {
		ob_start();
		cptdp_document_pages_render_box( get_post( $post_id ) );
		return (string) ob_get_clean();
	}

	public function test_the_status_function_returns_the_stored_status_for_a_collateral(): void {
		$id = $this->make_collateral();

		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, cptdp_get_collateral_pages_status( $id ) );

		foreach ( array( 'pending', 'needs_browser', 'failed', 'ready', 'none' ) as $status ) {
			CPTDP_Page_Store::set_status( $id, $status );
			$this->assertSame( $status, cptdp_get_collateral_pages_status( $id ) );
		}
	}

	public function test_the_status_function_is_empty_for_other_posts(): void {
		$document = $this->make_document();

		$this->assertSame( '', cptdp_get_collateral_pages_status( $document ) );
		$this->assertSame( '', cptdp_get_collateral_pages_status( 0 ) );
		$this->assertSame( '', cptdp_get_collateral_pages_status( 999999 ) );
	}

	public function test_the_loading_image_is_a_local_svg_without_script_or_external_references(): void {
		$url  = cptdp_collateral_loading_image_url();
		$path = CPTDP_PATH . 'assets/collateral-loading.svg';

		$this->assertFileExists( $path );
		$this->assertSame( CPTDP_URL . 'assets/collateral-loading.svg', $url );

		$svg = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( '<svg', $svg );
		$this->assertStringNotContainsString( '<script', strtolower( $svg ) );
		$this->assertStringNotContainsString( 'href=', strtolower( $svg ) );
		$this->assertStringNotContainsString( 'onload', strtolower( $svg ) );
	}

	public function test_the_list_cell_shows_the_loading_image_for_a_pending_collateral(): void {
		$id = $this->make_collateral( $this->make_pdf_attachment() );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );

		$html = $this->column( 'thumbnail', $id );

		$this->assertStringContainsString( 'collateral-loading.svg', $html );
		$this->assertStringContainsString( 'alt=""', $html );
		$this->assertStringContainsString( 'width="50"', $html );
		$this->assertStringContainsString( 'Preview is being prepared', $html );
		$this->assertStringNotContainsString( '&mdash;', $html );
	}

	public function test_the_loading_image_follows_a_landscape_paper_size(): void {
		$id = $this->make_collateral( $this->make_pdf_attachment() );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );
		update_post_meta( $id, 'cptdp_paper_size', 'A4 Landscape' );

		$html = $this->column( 'thumbnail', $id );

		$this->assertStringContainsString( 'height="35"', $html );
		$this->assertStringContainsString( 'width="50"', $html );
	}

	public function test_the_loading_image_never_replaces_a_featured_image(): void {
		$id         = $this->make_collateral( $this->make_pdf_attachment() );
		$attachment = $this->make_attachment( 'image/png' );
		set_post_thumbnail( $id, $attachment );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );

		$html = $this->column( 'thumbnail', $id );

		$this->assertStringNotContainsString( 'collateral-loading.svg', $html );
		$this->assertStringContainsString( '<img', $html );
	}

	public function test_the_loading_image_never_replaces_a_real_page_thumbnail(): void {
		$id = $this->make_collateral( $this->make_pdf_attachment() );
		$this->fake_pages( $id, 2 );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );

		$html = $this->column( 'thumbnail', $id );

		$this->assertStringNotContainsString( 'collateral-loading.svg', $html );
		$this->assertStringContainsString( 'p-001.webp', $html );
	}

	public function test_failed_needs_browser_and_empty_sets_keep_the_dash(): void {
		foreach ( array( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::STATUS_NONE ) as $status ) {
			$id = $this->make_collateral( $this->make_pdf_attachment() );
			CPTDP_Page_Store::set_status( $id, $status );

			$html = $this->column( 'thumbnail', $id );

			$this->assertStringNotContainsString( 'collateral-loading.svg', $html, $status );
			$this->assertStringContainsString( '&mdash;', $html, $status );
		}
	}

	public function test_a_documents_list_cell_is_unchanged(): void {
		$id = $this->make_document( $this->make_pdf_attachment() );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );

		ob_start();
		cptdp_document_column_content( 'thumbnail', $id );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'collateral-loading.svg', $html );
		$this->assertStringContainsString( '&mdash;', $html );
	}

	public function test_the_page_images_box_shows_the_loading_image_only_while_pending(): void {
		$id = $this->make_collateral( $this->make_pdf_attachment() );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );
		$this->assertStringContainsString( 'collateral-loading.svg', $this->pages_box( $id ) );
		$this->assertStringContainsString( 'width="80"', $this->pages_box( $id ) );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_FAILED );
		$this->assertStringNotContainsString( 'collateral-loading.svg', $this->pages_box( $id ) );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER );
		$this->assertStringNotContainsString( 'collateral-loading.svg', $this->pages_box( $id ) );
	}
}
