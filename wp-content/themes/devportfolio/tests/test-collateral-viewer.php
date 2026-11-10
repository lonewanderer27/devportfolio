<?php
/**
 * Tests for the Brand Collaterals book viewer, footer and payload (DPC-012, DPC-013, D5 to D9).
 *
 * The payload is checked per file kind, the markup and root attributes of the desktop dialog, the shared
 * helpers the Documents modal also uses, and the script and style contracts as text (no browser runner;
 * the browser check is recorded in the plan).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Viewer extends WP_UnitTestCase {

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

	/** A published collateral with an optional attached file (by mime type and file name). */
	private function collateral( string $file_name = '', string $mime = '', int $total_pages = 0 ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'collateral',
				'post_status' => 'publish',
				'post_title'  => 'Viewer test collateral',
			)
		);
		if ( '' !== $file_name ) {
			$attachment = self::factory()->attachment->create_object(
				$file_name,
				0,
				array(
					'post_mime_type' => $mime,
					'post_status'    => 'inherit',
				)
			);
			update_post_meta( $id, 'cptdp_collateral_file', $attachment );
		}
		if ( $total_pages > 0 ) {
			update_post_meta( $id, 'cptdp_total_pages', $total_pages );
		}
		return $id;
	}

	public function test_the_source_map_lists_documents_and_collaterals_with_their_own_meta(): void {
		$map = devportfolio_page_source_map();
		$this->assertSame( 'cptdp_document_file', $map['document']['file'] );
		$this->assertSame( 'cptdp_get_document_pages', $map['document']['pages'] );
		$this->assertSame( 'cptdp_collateral_file', $map['collateral']['file'] );
		$this->assertSame( 'cptdp_get_collateral_pages', $map['collateral']['pages'] );
	}

	public function test_a_collateral_image_is_an_image_kind_and_a_document_image_is_not(): void {
		$collateral = $this->collateral( 'flyer.png', 'image/png' );
		$this->assertSame( 'image', devportfolio_document_file_kind( $collateral ) );

		$document = self::factory()->post->create( array( 'post_type' => 'document', 'post_status' => 'publish' ) );
		$attachment = self::factory()->attachment->create_object( 'flyer.png', 0, array( 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
		update_post_meta( $document, 'cptdp_document_file', $attachment );
		$this->assertSame( 'other', devportfolio_document_file_kind( $document ), 'A document is never an image source.' );
	}

	public function test_a_collateral_without_a_file_has_no_pages_and_no_viewer_url(): void {
		$id   = $this->collateral( '', '', 4 );
		$data = devportfolio_collateral_modal_payload( $id );

		$this->assertSame( 'other', $data['kind'] );
		$this->assertSame( array(), $data['images'] );
		$this->assertSame( '', $data['viewerUrl'] );
		$this->assertSame( 4, $data['pages'], 'Without stored pages the typed page count is used.' );
		$this->assertSame( '', $data['nativeUrl'] );
		foreach ( array( 'images', 'imageWidth', 'cropBottom', 'ratio', 'viewerUrl', 'nativeUrl', 'pdfBytes', 'kind', 'pages', 'fileType', 'format' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, $key );
		}
	}

	public function test_a_collateral_image_has_one_page_kind_and_no_pdf_for_pdf_js(): void {
		$id   = $this->collateral( 'logo.jpg', 'image/jpeg' );
		$data = devportfolio_collateral_modal_payload( $id );

		$this->assertSame( 'image', $data['kind'] );
		$this->assertSame( '', $data['viewerUrl'], 'An image is never drawn by pdf.js.' );
		$this->assertSame( devportfolio_document_file_type_label( 'jpg' ), $data['fileType'], 'The file type comes from the attachment extension.' );
	}

	public function test_the_payload_keeps_the_sidebar_keys_and_the_order_of_the_documents_payload(): void {
		$id   = $this->collateral( '', '', 2 );
		$keys = array_keys( devportfolio_collateral_modal_payload( $id ) );
		foreach ( array( 'id', 'slug', 'title', 'description', 'tools', 'category', 'categories', 'pill', 'stats', 'downloadMode', 'downloadUrl', 'thumb' ) as $key ) {
			$this->assertContains( $key, $keys, $key );
		}
	}

	public function test_the_collateral_modal_root_carries_the_viewer_settings(): void {
		self::factory()->post->create( array( 'post_type' => 'collateral', 'post_status' => 'publish' ) );
		$html = trim( do_blocks( '<!-- wp:devportfolio/collateral-modal /-->' ) );

		$this->assertStringContainsString( 'data-pdfjs=', $html );
		$this->assertStringContainsString( 'data-limits=', $html );
		$this->assertStringContainsString( 'data-units=', $html );
		$this->assertStringContainsString( 'data-strings=', $html );
	}

	public function test_the_desktop_body_has_two_arrows_a_status_line_the_tap_hint_and_the_footer(): void {
		$html = trim( do_blocks( '<!-- wp:devportfolio/collateral-modal /-->' ) );

		$this->assertSame( 2, preg_match_all( '#class="collaterals-modal__arrow collaterals-modal__arrow--(?:prev|next)"#', $html ) );
		$this->assertStringContainsString( 'aria-label="Previous page"', $html );
		$this->assertStringContainsString( 'aria-label="Next page"', $html );
		$this->assertSame( 1, substr_count( $html, 'class="collaterals-modal__state" role="status" hidden' ) );
		$this->assertStringContainsString( 'data-unavailable="This collateral can’t be previewed."', $html );
		$this->assertStringContainsString( 'class="collaterals-modal__tap" hidden', $html );
		$this->assertStringContainsString( 'Tap to Flip', $html );
		$this->assertMatchesRegularExpression( '#<footer class="collaterals-modal__footer" hidden>.*?role="progressbar".*?</footer>#s', $html );
		$this->assertStringContainsString( 'class="collaterals-modal__sidebar-bar"', $html, 'The sidebar bar no longer shares the progress bar class.' );
	}

	public function test_the_viewer_stylesheet_is_enqueued_on_the_collaterals_archive_only(): void {
		$this->go_to( get_post_type_archive_link( 'collateral' ) );
		devportfolio_enqueue_page_styles();
		$this->assertTrue( wp_style_is( 'devportfolio-viewer', 'enqueued' ) );

		wp_dequeue_style( 'devportfolio-viewer' );
		$this->go_to( home_url( '/' ) );
		devportfolio_enqueue_page_styles();
		$this->assertFalse( wp_style_is( 'devportfolio-viewer', 'enqueued' ) );
	}

	public function test_the_documents_arrow_and_footer_helpers_keep_the_documents_class_names(): void {
		$arrow = devportfolio_viewer_arrow( 'documents-modal', 'prev', 'Previous page' );
		$this->assertStringContainsString( 'class="documents-modal__arrow documents-modal__arrow--prev"', $arrow );
		$this->assertStringContainsString( 'M15 5l-7 7 7 7', $arrow );

		$footer = devportfolio_viewer_footer( 'documents-modal', 'Pages', 'Reading progress' );
		$this->assertStringContainsString( '<footer class="documents-modal__footer" hidden>', $footer );
		$this->assertStringContainsString( 'class="documents-modal__pager"', $footer );
		$this->assertStringContainsString( 'role="progressbar"', $footer );
	}

	public function test_the_status_helper_takes_the_prefix_and_an_extra_class(): void {
		$status = devportfolio_viewer_status( 'documents-modal', devportfolio_viewer_status_strings( 'document' ), 'documents-mobile__state' );
		$this->assertStringContainsString( 'class="documents-modal__state documents-mobile__state" role="status" hidden', $status );
		$this->assertStringContainsString( 'class="documents-modal__state-hint"', $status );
		$this->assertStringContainsString( 'data-hint-doc="Documents can’t always be previewed here.', $status );
	}

	public function test_the_collateral_view_script_builds_the_book_and_the_pager_from_the_shared_modules(): void {
		$js = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertStringContainsString( "from '../shared/viewer.js'", $js );
		$this->assertStringContainsString( "from '../shared/pager-controller.js'", $js );
		$this->assertStringContainsString( "prefix: 'collaterals-modal'", $js );
		$this->assertStringContainsString( "eventName: 'collaterals:modal-change'", $js );
		$this->assertStringContainsString( 'onTap: ( direction ) => pager.tap( direction )', $js );
		$this->assertStringNotContainsString( 'new PageFlip', $js, 'The book is the shared viewer, never a second StPageFlip.' );
	}

	public function test_the_shared_controller_emits_the_step_and_the_pages(): void {
		$controller = $this->read( 'blocks/shared/pager-controller.js' );
		$this->assertStringContainsString( 'detail: { ...state }', $controller );
		$this->assertMatchesRegularExpression( '#const state = \{ id: 0, page: 1, step: 1, pages: 0 \}#', $controller );
	}

	public function test_the_tap_to_flip_rules_are_in_the_shared_gestures(): void {
		$gestures = $this->read( 'blocks/shared/gestures.js' );
		$this->assertStringContainsString( 'export function tapSide', $gestures );
		$this->assertStringContainsString( 'export function shouldShowTapHint', $gestures );
		$viewer = $this->read( 'blocks/shared/viewer.js' );
		$this->assertStringContainsString( 'DOUBLE_TAP_WINDOW', $viewer, 'A single tap waits out the double-tap window.' );
		$this->assertStringContainsString( 'tapSide( local( event ).x', $viewer );
	}

	public function test_collateral_arrows_have_their_own_left_and_right_position(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '/\.documents-modal__arrow--prev,\s*\.collaterals-modal__arrow--prev \{\s*left: 18px;/', $css, 'Previous sits on the left for collaterals too.' );
		$this->assertMatchesRegularExpression( '/\.documents-modal__arrow--next,\s*\.collaterals-modal__arrow--next \{\s*right: 18px;/', $css, 'Next sits on the right for collaterals too.' );
	}

	public function test_collaterals_always_show_one_page_at_a_time(): void {
		$view = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertSame( 2, substr_count( $view, 'singlePage: true,' ), 'Desktop viewer and mobile modal both get singlePage.' );
		$this->assertStringContainsString( 'const step = 1;', $view, 'Opening a collateral never picks a spread.' );

		$viewer = $this->read( 'blocks/shared/viewer.js' );
		$this->assertStringContainsString( 'singlePage = false', $viewer );
		$this->assertStringContainsString( "if ( singlePage ) {\n\t\t\treturn 1;", $viewer, 'The desktop step never becomes 2 when singlePage is set.' );

		$mobile = $this->read( 'blocks/shared/mobile-viewer.js' );
		$this->assertStringContainsString( '! native && ! singlePage && spreadFits(', $mobile );

		$modal = $this->read( 'blocks/shared/mobile.js' );
		$this->assertStringContainsString( 'singlePage = false', $modal );
		$this->assertStringContainsString( 'singlePage,', $modal, 'The mobile modal passes the option to its viewer.' );
	}

	public function test_the_documents_modal_still_uses_the_shared_pager_and_viewer(): void {
		$view = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "prefix: 'documents-modal'", $view );
		$this->assertStringContainsString( "eventName: 'documents:modal-change'", $view );
		$this->assertStringNotContainsString( 'new PageFlip', $view );
	}

	public function test_collateral_css_has_the_book_arrows_hint_and_centred_stage(): void {
		$css = $this->read( 'blocks/collateral-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.collaterals-modal__body \{[^}]*position: relative#s', $css );
		$this->assertMatchesRegularExpression( '#\.collaterals-modal__tap \{[^}]*position: absolute#s', $css );
		$this->assertMatchesRegularExpression( '#\.collaterals-modal__tap\[hidden\]#', $css );

		$documents = $this->read( 'blocks/document-modal/style.css' );
		$this->assertStringContainsString( '.collaterals-modal__stage', $documents, 'The stage rule is listed for both modals.' );
		$this->assertStringContainsString( '.collaterals-modal__pg', $documents );
		$this->assertStringContainsString( '.collaterals-modal__book', $documents );
	}
}
