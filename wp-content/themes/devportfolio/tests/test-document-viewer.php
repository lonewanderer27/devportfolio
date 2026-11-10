<?php
/**
 * Tests for the document viewer in the modal body (DPD-013).
 *
 * Covers the payload keys, the modal root data, the block markup and the
 * structure of the CSS and scripts. There is no browser runner: the pure
 * helpers (pager, layout, render gate) are tested with node, see
 * blocks/document-modal/tests/.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Viewer extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	public function tear_down(): void {
		remove_all_filters( 'devportfolio_document_viewer_limits' );
		parent::tear_down();
	}

	private function make_document( array $meta = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_title'  => 'Viewer Test',
				'post_status' => 'publish',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $post_id;
	}

	private function render_modal(): string {
		return do_blocks( '<!-- wp:devportfolio/document-modal /-->' );
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		if ( 'blocks/document-modal/viewer.js' === $relative ) {
			// The page sources and the status line moved to page-source.js and status.js (DPD-021): the viewer is read as one piece.
			$contents .= (string) file_get_contents( $this->theme_dir() . '/blocks/document-modal/page-source.js' ) . (string) file_get_contents( $this->theme_dir() . '/blocks/document-modal/status.js' ) . (string) file_get_contents( $this->theme_dir() . '/blocks/document-modal/native-pptx.js' );
		}
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	private function root_attribute( string $html, string $name ): string {
		$this->assertSame( 1, preg_match( '#<div[^>]*class="[^"]*documents-modal-root[^"]*"[^>]*>#', $html, $tag ), 'modal root' );
		$this->assertSame( 1, preg_match( '#\s' . preg_quote( $name, '#' ) . '="([^"]*)"#', $tag[0], $m ), $name );
		return html_entity_decode( $m[1], ENT_QUOTES );
	}

	private function pdf_attachment(): int {
		return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/test-alpha.pdf' );
	}

	// --- Payload keys ----------------------------------------------------------

	public function test_payload_without_a_file_has_no_viewer_data(): void {
		$id      = $this->make_document( array( 'cptdp_total_pages' => 12 ) );
		$payload = devportfolio_document_modal_payload( $id );

		$this->assertSame( array(), $payload['images'] );
		$this->assertSame( 0, $payload['imageWidth'] );
		$this->assertSame( 0.0, $payload['cropBottom'] );
		$this->assertSame( 0.0, $payload['ratio'] );
		$this->assertSame( '', $payload['viewerUrl'] );
		$this->assertSame( 0, $payload['pdfBytes'] );
		$this->assertSame( 12, $payload['pages'], 'The existing integer page count is untouched.' );
	}

	public function test_payload_for_a_pdf_has_its_url_and_size_but_no_images_before_conversion(): void {
		$attachment = $this->pdf_attachment();
		$this->assertGreaterThan( 0, $attachment );
		$id = $this->make_document( array( 'cptdp_document_file' => $attachment ) );

		$payload = devportfolio_document_modal_payload( $id );
		$this->assertSame( wp_get_attachment_url( $attachment ), $payload['viewerUrl'] );
		$this->assertSame( (int) filesize( get_attached_file( $attachment ) ), $payload['pdfBytes'] );
		$this->assertGreaterThan( 0, $payload['pdfBytes'] );
		$this->assertSame( array(), $payload['images'], 'Nothing is converted while a page renders.' );
		$this->assertSame( 'file', $payload['downloadMode'] );
	}

	public function test_payload_for_a_non_pdf_file_has_no_viewer_url(): void {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$id         = $this->make_document( array( 'cptdp_document_file' => $attachment ) );

		$payload = devportfolio_document_modal_payload( $id );
		$this->assertSame( '', $payload['viewerUrl'] );
		$this->assertSame( 0, $payload['pdfBytes'] );
		$this->assertSame( 'file', $payload['downloadMode'], 'Download still works for any file.' );
	}

	public function test_viewer_data_is_empty_for_other_post_types(): void {
		$data = devportfolio_document_viewer_data( self::factory()->post->create() );
		$this->assertSame( array(), $data['images'] );
		$this->assertSame( '', $data['viewerUrl'] );
	}

	public function test_stored_pages_are_normalised_for_the_viewer(): void {
		$stored = devportfolio_document_normalise_stored_pages(
			array(
				'pages'  => array( 'https://example.com/p-001.webp', 'https://example.com/p-002.webp' ),
				'count'  => 2,
				'width'  => 1400,
				'height' => 1812,
				'ratio'  => 0.772626,
			)
		);
		$this->assertSame( array( 'https://example.com/p-001.webp', 'https://example.com/p-002.webp' ), $stored['images'] );
		$this->assertSame( 1400, $stored['imageWidth'] );
		$this->assertSame( 0.7726, $stored['ratio'] );
		$this->assertSame( 0.0, $stored['cropBottom'], 'No crop from the plugin means the whole page.' );
	}

	public function test_the_bottom_crop_and_its_ratio_pass_through_from_the_plugin(): void {
		$stored = devportfolio_document_normalise_stored_pages(
			array(
				'pages'       => array( 'https://example.com/p-001.webp' ),
				'width'       => 1400,
				'height'      => 1980,
				'ratio'       => 0.8837,
				'crop_bottom' => 0.2,
			)
		);
		$this->assertSame( 0.2, $stored['cropBottom'] );
		$this->assertSame( 0.8837, $stored['ratio'], 'The ratio is already for the visible part: it is not changed here.' );
	}

	public function test_an_out_of_range_crop_is_not_trusted(): void {
		foreach ( array( 0.26, 0.9, 1, -0.1, 'abc', null ) as $bad ) {
			$stored = devportfolio_document_normalise_stored_pages(
				array(
					'pages'       => array( 'https://example.com/p-001.webp' ),
					'crop_bottom' => $bad,
				)
			);
			$this->assertSame( 0.0, $stored['cropBottom'], wp_json_encode( $bad ) );
		}

		$edge = devportfolio_document_normalise_stored_pages(
			array(
				'pages'       => array( 'https://example.com/p-001.webp' ),
				'crop_bottom' => 0.25,
			)
		);
		$this->assertSame( 0.25, $edge['cropBottom'] );
	}

	public function test_missing_or_malformed_stored_pages_give_no_images(): void {
		$none = array(
			'images'     => array(),
			'imageWidth' => 0,
			'cropBottom' => 0.0,
			'ratio'      => 0.0,
		);
		$this->assertSame( $none, devportfolio_document_normalise_stored_pages( null ) );
		$this->assertSame( $none, devportfolio_document_normalise_stored_pages( 'nope' ) );
		$this->assertSame( $none, devportfolio_document_normalise_stored_pages( array() ) );
		$this->assertSame( $none, devportfolio_document_normalise_stored_pages( array( 'pages' => array() ) ) );
		$this->assertSame( $none, devportfolio_document_normalise_stored_pages( array( 'pages' => 'x' ) ) );
		$this->assertSame(
			$none,
			devportfolio_document_normalise_stored_pages( array( 'pages' => array( 'https://example.com/a.webp', '', 'https://example.com/c.webp' ) ) ),
			'A hole in the list would leave a hole in the book.'
		);
		$this->assertSame(
			$none,
			devportfolio_document_normalise_stored_pages( array( 'pages' => array( 'https://example.com/a.webp', array( 'x' ) ) ) )
		);
	}

	public function test_stored_page_numbers_are_never_negative(): void {
		$stored = devportfolio_document_normalise_stored_pages(
			array(
				'pages' => array( 'https://example.com/a.webp' ),
				'width' => -5,
				'ratio' => -1,
			)
		);
		$this->assertSame( 0, $stored['imageWidth'] );
		$this->assertSame( 0.0, $stored['ratio'] );
	}

	public function test_stored_page_urls_are_sanitised(): void {
		$stored = devportfolio_document_normalise_stored_pages( array( 'pages' => array( 'javascript:alert(1)' ) ) );
		$this->assertSame( array(), $stored['images'], 'A non-http(s) URL is dropped, which empties the list.' );
	}

	public function test_payload_json_carries_the_viewer_keys(): void {
		$attachment = $this->pdf_attachment();
		$id         = $this->make_document( array( 'cptdp_document_file' => $attachment ) );

		$html = $this->render_modal();
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="documents-modal__data">(.*?)</script>#s', $html, $m ) );
		$data = json_decode( $m[1], true );
		$item = $data[ (string) $id ];
		foreach ( array( 'images', 'imageWidth', 'cropBottom', 'ratio', 'viewerUrl', 'pdfBytes', 'pages' ) as $key ) {
			$this->assertArrayHasKey( $key, $item, $key );
		}
		$this->assertSame( wp_get_attachment_url( $attachment ), $item['viewerUrl'] );
		$this->assertIsArray( $item['images'] );
	}

	// --- Modal root data (limits and pdf.js URLs) ---------------------------------

	public function test_limits_default_to_no_overrides(): void {
		$this->assertSame( array(), devportfolio_document_viewer_limits() );
		$this->assertSame( '{}', $this->root_attribute( $this->render_modal(), 'data-limits' ) );
	}

	public function test_limit_overrides_reach_the_modal_root(): void {
		add_filter(
			'devportfolio_document_viewer_limits',
			static function ( $limits ) {
				$limits['maxPages']          = array( 'mobile' => 30 );
				$limits['minDeviceMemoryGB'] = 4;
				return $limits;
			}
		);
		$decoded = json_decode( $this->root_attribute( $this->render_modal(), 'data-limits' ), true );
		$this->assertSame( array( 'mobile' => 30 ), $decoded['maxPages'] );
		$this->assertSame( 4, $decoded['minDeviceMemoryGB'] );
	}

	public function test_a_filter_returning_junk_gives_no_overrides(): void {
		add_filter( 'devportfolio_document_viewer_limits', '__return_null' );
		$this->assertSame( array(), devportfolio_document_viewer_limits() );
		$this->assertSame( '{}', $this->root_attribute( $this->render_modal(), 'data-limits' ) );
	}

	public function test_pdfjs_urls_come_from_the_plugin_or_are_empty(): void {
		$urls = devportfolio_document_pdfjs_urls();
		$this->assertSame( array( 'module', 'worker' ), array_keys( $urls ) );

		$attribute = json_decode( $this->root_attribute( $this->render_modal(), 'data-pdfjs' ), true );
		if ( function_exists( 'cptdp_document_pdfjs_urls' ) ) {
			$this->assertNotSame( '', $urls['module'] );
			$this->assertNotSame( '', $urls['worker'] );
			$this->assertSame( $urls, $attribute );
		} else {
			$this->assertSame( array( 'module' => '', 'worker' => '' ), $urls );
			$this->assertSame( array(), $attribute, 'Without the plugin there is nothing for the fallback to load.' );
		}
	}

	// --- Markup ---------------------------------------------------------------------

	public function test_stage_is_empty_and_followed_by_a_status_message_inside_the_body(): void {
		$html = $this->render_modal();
		$this->assertStringContainsString( '<div class="documents-modal__stage"></div>', $html );

		$body   = substr( $html, strpos( $html, 'class="documents-modal__body"' ) );
		$body   = substr( $body, 0, strpos( $body, '<footer' ) );
		$stage  = strpos( $body, 'documents-modal__stage' );
		$status = strpos( $body, 'documents-modal__state"' );
		$this->assertNotFalse( $status );
		$this->assertLessThan( $status, $stage );
	}

	public function test_status_message_is_hidden_by_default_and_carries_its_strings(): void {
		$html = $this->render_modal();
		$this->assertSame( 1, preg_match( '#<div\s+class="documents-modal__state"([^>]*)>#s', $html, $m ) );
		$this->assertStringContainsString( 'role="status"', $m[1] );
		$this->assertMatchesRegularExpression( '#\shidden[\s>]#', $m[1] . '>' );
		$this->assertStringContainsString( 'data-loading="Loading pages…"', $m[1] );
		$this->assertStringContainsString( 'data-unavailable="This document can’t be previewed."', $m[1] );
		$this->assertStringContainsString( 'data-hint="Use Download to open it."', $m[1] );
		$this->assertStringContainsString( 'documents-modal__spinner', $html );
		$this->assertStringContainsString( 'documents-modal__state-text', $html );
		$this->assertStringContainsString( 'documents-modal__state-hint', $html );
	}

	// --- CSS --------------------------------------------------------------------------

	public function test_css_stage_and_book_layout(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__body\s*\{[^}]*display: flex[^}]*overflow: hidden#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__stage\s*\{[^}]*display: grid[^}]*place-items: center[^}]*min-width: 0#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__book\s*\{\s*position: relative#', $css );
	}

	public function test_the_modal_opens_without_changing_size(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertSame( 1, preg_match( '#@keyframes documents-modal-pop\s*\{(.*?)\n\}#s', $css, $m ) );
		$this->assertStringNotContainsString( 'scale', $m[1], 'A scale in the open animation makes the modal grow a few pixels.' );

		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( 'stage.clientWidth', $js );
		$this->assertStringNotContainsString( 'getBoundingClientRect', $js, 'The rect follows transforms and is wrong while the modal animates.' );
	}

	// --- Selectable text (Settings > Documents) -----------------------------------------

	public function test_selectable_text_is_off_unless_the_plugin_setting_is_on(): void {
		delete_option( 'cptdp_documents' );
		$this->assertFalse( devportfolio_document_selectable_text() );
		$this->assertStringNotContainsString( 'data-selectable-text', $this->render_modal() );

		update_option( 'cptdp_documents', array( 'native_pdf' => true ) );
		$this->assertTrue( devportfolio_document_selectable_text() );
		$this->assertSame( '1', $this->root_attribute( $this->render_modal(), 'data-selectable-text' ) );

		delete_option( 'cptdp_documents' );
	}

	public function test_viewer_draws_pdf_pages_with_a_text_layer_first_when_text_is_selectable(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( 'selectableText = false', $js );
		$this->assertStringContainsString( 'if ( selectableText && Boolean( doc.viewerUrl ) )', $js );
		$this->assertStringContainsString( 'selectableText ? lib.TextLayer : null', $js );
		$this->assertStringContainsString( "layer.style.setProperty( '--scale-factor'", $js );
		$this->assertStringContainsString( 'page.streamTextContent()', $js );
		// A pdf.js failure falls back to the stored images (not selectable) when there are some.
		$this->assertMatchesRegularExpression( '#catch \( error \) \{\s*if \( ! Array\.isArray\( doc\.images \) \|\| ! doc\.images\.length \) \{\s*throw error;#', $js );
	}

	public function test_text_layers_leave_the_mouse_to_the_text(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( 'useMouseEvents: ! source.textLayer', $js );
		$this->assertStringContainsString( 'showPageCorners: ! reducedMotion.matches && ! source.textLayer', $js );
		$this->assertStringContainsString( "'documents-modal__book--selectable'", $js );

		$view = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "selectableText: root.getAttribute( 'data-selectable-text' ) === '1'", $view );
	}

	public function test_css_has_a_transparent_selectable_text_layer(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__text\s*\{[^}]*position: absolute[^}]*inset: 0#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__text :is\(span, br\)\s*\{[^}]*position: absolute[^}]*color: transparent[^}]*white-space: pre#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__book--selectable[^{]*\{[^}]*user-select: text#s', $css );
	}

	public function test_css_pages_are_white_on_the_grey_stage(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__page\s*\{[^}]*background: \#fff#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__page\s*\{[^}]*box-shadow#s', $css );
		// The grey, dotted body is the existing stage background.
		$this->assertMatchesRegularExpression( '#\.documents-modal__body\s*\{[^}]*background-color: var\(--dm-surface-alt\)#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__sheet img\s*\{[^}]*object-fit: contain#s', $css );
	}

	public function test_css_clips_the_empty_bottom_strip_of_cropped_stored_images(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__book--cropped \.documents-modal__sheet\s*\{[^}]*display: block[^}]*overflow: hidden#s', $css );
		$this->assertSame( 1, preg_match( '#\.documents-modal__book--cropped \.documents-modal__sheet img\s*\{([^}]*)\}#', $css, $m ) );
		$this->assertStringContainsString( 'height: calc(100% / (1 - var(--documents-crop, 0)))', $m[1] );
		$this->assertStringContainsString( 'object-fit: cover', $m[1] );
		$this->assertStringContainsString( 'object-position: top', $m[1] );
	}

	public function test_viewer_script_applies_the_crop_to_stored_images_only(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( 'doc.cropBottom', $js );
		$this->assertStringContainsString( "container.classList.add( 'documents-modal__book--cropped' )", $js );
		$this->assertStringContainsString( "container.style.setProperty( '--documents-crop'", $js );
		$this->assertMatchesRegularExpression( '#if \( source\.crop > 0 \)#', $js, 'The class is set from the source, which only the image source fills.' );
		$this->assertMatchesRegularExpression( '#function pdfSource\([^)]*\)\s*\{(?:(?!function ).)*\}#s', $js );
		$pdf = substr( $js, (int) strpos( $js, 'function pdfSource' ), 900 );
		$this->assertStringNotContainsString( 'crop', $pdf, 'The pdf.js fallback path is not cropped.' );
	}

	public function test_css_page_number_sits_at_the_bottom_centre_of_every_page(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertSame( 1, preg_match( '#\.documents-modal__pn\s*\{([^}]*)\}#', $css, $m ) );
		$this->assertStringContainsString( 'position: absolute', $m[1] );
		$this->assertMatchesRegularExpression( '#bottom: [\d.]+%#', $m[1] );
		$this->assertStringContainsString( 'left: 50%', $m[1] );
		$this->assertStringContainsString( 'translateX(-50%)', $m[1] );
		$this->assertStringContainsString( 'font-variant-numeric: tabular-nums', $m[1] );
	}

	public function test_css_fold_shades_the_spine_edge_of_each_page_in_a_spread(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__page--left::after\s*\{[^}]*right: 0[^}]*linear-gradient\(to right#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__page--right::after\s*\{[^}]*left: 0[^}]*linear-gradient\(to left#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__page--left::after,\s*\.documents-modal__page--right::after\s*\{[^}]*pointer-events: none#s', $css );
	}

	public function test_css_status_message_and_reduced_motion(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__state\s*\{[^}]*position: absolute[^}]*inset: 0[^}]*pointer-events: none#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__state\[data-kind="unavailable"\] \.documents-modal__spinner\s*\{\s*display: none#', $css );
		$this->assertMatchesRegularExpression( '#@media \(prefers-reduced-motion: reduce\)\s*\{.*\.documents-modal__spinner\s*\{\s*animation: none#s', $css );
	}

	// --- Scripts ------------------------------------------------------------------------

	public function test_view_script_hands_the_book_to_the_viewer(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "import { createViewer } from './viewer.js'", $js );
		$this->assertStringContainsString( 'viewer.show( doc, state.page )', $js );
		$this->assertStringContainsString( 'viewer.goTo( state.page, { animate } )', $js );
		$this->assertStringContainsString( 'viewer.destroy()', $js );
		$this->assertStringContainsString( 'viewer.isTurning()', $js );
		$this->assertStringContainsString( "readJson( 'data-pdfjs' )", $js );
		$this->assertStringContainsString( "mergeLimits( readJson( 'data-limits' ) )", $js );
		// The footer shows no "Two-page spread" / count text any more (only the progress line).
		$this->assertStringNotContainsString( 'Two-page spread', $js );
	}

	public function test_view_script_turns_pages_with_the_arrow_keys(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "'ArrowLeft'", $js );
		$this->assertStringContainsString( "'ArrowRight'", $js );
		$this->assertStringContainsString( 'input, textarea, select, [contenteditable]', $js );
		$this->assertStringContainsString( "event.key === 'ArrowRight' ? 1 : -1", $js );
	}

	public function test_a_page_turned_on_the_book_syncs_back_without_echoing(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertMatchesRegularExpression( '#const syncFromViewer = \( page \) => \{\s*const next = normalisePage\( page, state\.pages, state\.step \);\s*if \( next !== state\.page \)#', $js );
	}

	public function test_viewer_script_book_settings(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		// Pairs 1-2, 3-4; an odd last page sits alone on the left.
		$this->assertStringContainsString( 'showCover: false', $js );
		// A spread needs the library's landscape mode, a single page its portrait mode.
		$this->assertStringContainsString( 'usePortrait: step === 1', $js );
		$this->assertStringContainsString( "size: 'fixed'", $js );
		$this->assertStringContainsString( 'autoSize: false', $js );
		$this->assertStringContainsString( 'container.style.width = `${ step === 2 ? pageW * 2 : pageW }px`', $js );
		$this->assertStringContainsString( 'flip.loadFromHTML( pageEls )', $js );
		$this->assertStringContainsString( "flip.on( 'flip'", $js );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $js );
	}

	public function test_viewer_script_page_markup(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( "el( 'div', 'documents-modal__page'", $js );
		$this->assertStringContainsString( "el( 'div', 'documents-modal__sheet' )", $js );
		$this->assertStringContainsString( "el( 'span', 'documents-modal__pn'", $js );
		$this->assertStringContainsString( 'number.textContent = String( i + 1 )', $js );
		$this->assertStringContainsString( "'documents-modal__page--left', step === 2 && i % 2 === 0", $js );
		$this->assertStringContainsString( "'documents-modal__page--right', step === 2 && i % 2 === 1", $js );
	}

	public function test_viewer_script_decision_order(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		// Default order (the else branch of the selectable-text switch): stored images, then pdf.js.
		$default = (int) strpos( $js, '} else {' . "\n\t\t" . 'images = await tryImages( doc, alive, limits )' );
		$images  = strpos( $js, 'images = await tryImages( doc, alive, limits )', $default );
		$pdf     = strpos( $js, 'pdf = await tryPdf( doc, alive, options )', $default );
		$this->assertGreaterThan( 0, $default );
		$this->assertNotFalse( $images );
		$this->assertNotFalse( $pdf );
		$this->assertLessThan( $pdf, $images, 'Stored images are tried before pdf.js.' );
		// pdf.js is imported only inside the PDF attempt, and only after the gate allowed it.
		$this->assertSame( 1, substr_count( $js, 'await import( pdfjs.module )' ) );
		$this->assertLessThan( strpos( $js, 'await import( pdfjs.module )' ), strpos( $js, "verdict.tier === 'refused'" ) );
		$this->assertStringContainsString( 'clientRenderTier( readEnvironment(', $js );
	}

	public function test_viewer_script_cleans_up(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( 'flip.destroy()', $js );
		$this->assertStringContainsString( 'pdf.destroy()', $js );
		$this->assertStringContainsString( 'observer.disconnect()', $js );
		$this->assertStringContainsString( 'entry.task.cancel()', $js );
		$this->assertStringContainsString( "stage.removeAttribute( 'aria-live' )", $js );
		// A stale open must not touch the viewer that replaced it.
		$this->assertGreaterThanOrEqual( 4, substr_count( $js, 'alive()' ) );
		// A bad document is not the browser's fault: it must not set the failure flag.
		$this->assertStringContainsString( 'PasswordException', $js );
		// Reading window.localStorage can itself throw: only the guarded accessor is used.
		$this->assertStringContainsString( 'markFailure( safeStorage()', $js );
		$this->assertStringContainsString( 'clearFailure( safeStorage() )', $js );
		$this->assertStringNotContainsString( 'window.localStorage', $js );
	}

	public function test_a_late_failure_cannot_touch_a_newer_book(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		// fail() takes the session it was raised for and drops errors from an older one.
		$this->assertStringContainsString( 'const fail = ( error, mine = session ) => {', $js );
		$this->assertStringContainsString( 'mine !== session || isBenignRenderError( error )', $js );
		// Every async path reports against the session that started it.
		$this->assertStringContainsString( 'ensureWindow( page ).catch( ( error ) => fail( error, mine ) )', $js );
		$this->assertStringContainsString( 'fail( error, mine )', $js );
		$this->assertStringContainsString( 'if ( ! source || mine !== session )', $js );
		// No path calls the old unguarded `.catch( fail )`.
		$this->assertStringNotContainsString( '.catch( fail )', $js );
		// pdf.js teardown errors from our own cancel()/destroy() are not failures.
		$this->assertStringContainsString( 'entry.cancelled || isBenignRenderError( error )', $js );
	}

	public function test_only_a_moving_page_blocks_a_second_turn(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertStringContainsString( "[ 'flipping', 'user_fold' ].includes( flip.getState() )", $js );
		$this->assertStringNotContainsString( "getState() !== 'read'", $js );
	}

	public function test_pdf_is_destroyed_when_the_first_page_cannot_be_read(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$this->assertMatchesRegularExpression( '/await pdf\.getPage\( 1 \);.*?catch \( error \) \{\s*pdf\.destroy\(\);\s*throw error;/s', $js );
	}

	public function test_a_broken_stored_image_is_marked_and_a_broken_first_spread_falls_back(): void {
		$js  = $this->read( 'blocks/document-modal/viewer.js' );
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertStringContainsString( "pageEl.classList.add( 'documents-modal__page--broken' )", $js );
		$this->assertStringContainsString( 'documents-modal__page--broken', $css );
		// The second page of a spread is loaded before the book is shown, like the first.
		$this->assertStringContainsString( 'preloaded[ 2 ] = await withTimeout( loadImage( doc.images[ 1 ] )', $js );
	}

	public function test_status_text_is_set_before_the_region_is_shown(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );
		$set = strpos( $js, 'textEl.textContent = kind ===' );
		$show = strpos( $js, 'status.hidden = false;' );
		$this->assertNotFalse( $set );
		$this->assertNotFalse( $show );
		$this->assertLessThan( $show, $set, 'A screen reader only announces a status region whose text is in place when it appears.' );
	}

	public function test_the_stage_is_not_a_live_region(): void {
		$this->assertStringNotContainsString( 'aria-live', $this->read( 'blocks/document-modal/render.php' ) );
	}

	public function test_module_worker_probe_is_cached_and_revokes_its_blob_url(): void {
		$js = $this->read( 'blocks/document-modal/capability.js' );
		$this->assertStringContainsString( 'workerProbes', $js );
		$this->assertStringContainsString( 'revokeObjectURL', $js );
	}

	public function test_viewer_scripts_have_no_external_urls(): void {
		foreach ( array( 'viewer.js', 'view.js', 'capability.js', 'layout.js', 'pager.js', 'mobile.js', 'download.js', 'render.php' ) as $file ) {
			$source = $this->read( 'blocks/document-modal/' . $file );
			// The SVG namespace is an identifier for createElementNS(), not a host that is contacted.
			$source = str_replace( 'http://www.w3.org/2000/svg', '', $source );
			$this->assertDoesNotMatchRegularExpression( '#https?://#i', $source, $file . ' must not name an external host.' );
		}
	}

	// --- Vendored library ---------------------------------------------------------------

	public function test_page_flip_is_vendored_with_its_licence(): void {
		$dir = $this->theme_dir() . '/blocks/document-modal/vendor';
		$this->assertFileExists( $dir . '/page-flip.browser.js' );
		$this->assertFileExists( $dir . '/LICENSE' );
		$this->assertFileExists( $dir . '/README.md' );
		$this->assertStringContainsString( 'MIT License', (string) file_get_contents( $dir . '/LICENSE' ) );
		$this->assertStringContainsString( '2.0.7', (string) file_get_contents( $dir . '/README.md' ) );
		$this->assertStringContainsString( 'PageFlip', (string) file_get_contents( $dir . '/page-flip.browser.js' ) );
	}

	public function test_gate_version_floors_match_the_readme(): void {
		$readme = $this->read( 'blocks/document-modal/vendor/README.md' );
		$js     = $this->read( 'blocks/document-modal/capability.js' );
		$this->assertStringContainsString( 'Chrome and Edge 119, Firefox 124, Safari 17.4', $readme );
		$this->assertStringContainsString( 'minVersions: { chrome: 119, edge: 119, firefox: 124, safari: 17.4 }', $js );
		$this->assertStringContainsString( 'Promise.withResolvers', $readme );
		$this->assertStringContainsString( 'AbortSignal.any', $readme );
		$this->assertStringContainsString( 'promiseWithResolvers', $js );
		$this->assertStringContainsString( 'abortSignalAny', $js );
	}

	public function test_block_json_still_loads_the_view_as_a_module(): void {
		$block = json_decode( $this->read( 'blocks/document-modal/block.json' ), true );
		$this->assertSame( 'file:./view.js', $block['viewScriptModule'] );
	}
}
