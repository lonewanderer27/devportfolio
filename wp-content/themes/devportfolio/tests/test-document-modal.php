<?php
/**
 * Tests for the document modal (DPD-009, DPD-010, DPD-011, DPD-012).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Modal extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		add_action( 'template_redirect', 'devportfolio_redirect_document_single', 1 );
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * @param array $args { title, status, excerpt, content, meta: array }.
	 */
	private function make_document( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'document',
				'post_title'   => $args['title'] ?? 'Quarterly Report',
				'post_status'  => $args['status'] ?? 'publish',
				'post_excerpt' => $args['excerpt'] ?? '',
				'post_content' => $args['content'] ?? '',
			)
		);
		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $post_id;
	}

	private function render_modal(): string {
		return do_blocks( '<!-- wp:devportfolio/document-modal /-->' );
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	private function payload_from( string $html ): array {
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="documents-modal__data">(.*?)</script>#s', $html, $m ) );
		$data = json_decode( $m[1], true );
		$this->assertIsArray( $data );
		return $data;
	}

	// --- Helpers ------------------------------------------------------------

	public function test_download_target_prefers_file_then_embed_then_none(): void {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$file_doc   = $this->make_document( array( 'meta' => array( 'cptdp_document_file' => $attachment, 'cptdp_embed_url' => 'https://example.com/embed' ) ) );
		$embed_doc  = $this->make_document( array( 'meta' => array( 'cptdp_embed_url' => 'https://example.com/embed' ) ) );
		$none_doc   = $this->make_document();

		$file = devportfolio_document_download_target( $file_doc );
		$this->assertSame( 'file', $file['mode'] );
		$this->assertSame( wp_get_attachment_url( $attachment ), $file['url'] );

		$this->assertSame(
			array( 'mode' => 'embed', 'url' => 'https://example.com/embed' ),
			devportfolio_document_download_target( $embed_doc )
		);
		$this->assertSame( array( 'mode' => 'none', 'url' => '' ), devportfolio_document_download_target( $none_doc ) );
	}

	public function test_download_target_rejects_non_http_embed(): void {
		$id = $this->make_document( array( 'meta' => array( 'cptdp_embed_url' => 'javascript:alert(1)' ) ) );
		$this->assertSame( 'none', devportfolio_document_download_target( $id )['mode'] );
	}

	public function test_description_uses_excerpt_then_trimmed_content(): void {
		$with_excerpt = $this->make_document( array( 'excerpt' => 'Short <b>summary</b>.', 'content' => 'Ignored body.' ) );
		$this->assertSame( 'Short summary.', devportfolio_document_description( $with_excerpt ) );

		$long    = implode( ' ', array_fill( 0, 40, 'word' ) );
		$content = $this->make_document( array( 'content' => "<p>$long</p>" ) );
		$this->assertSame( 20, count( explode( ' ', rtrim( devportfolio_document_description( $content ), '…' ) ) ) );

		$this->assertSame( '', devportfolio_document_description( $this->make_document() ) );
	}

	public function test_payload_fields(): void {
		$tool = self::factory()->post->create( array( 'post_type' => 'software_tool', 'post_title' => 'InDesign' ) );
		$draft = self::factory()->post->create( array( 'post_type' => 'software_tool', 'post_title' => 'Hidden', 'post_status' => 'draft' ) );
		$id   = $this->make_document(
			array(
				'excerpt' => 'A summary',
				'meta'    => array(
					'cptdp_filetype'       => 'pdf',
					'cptdp_total_pages'    => 24,
					'cptdp_software_tools' => array( $tool, $draft, 999999 ),
				),
			)
		);

		$payload = devportfolio_document_modal_payload( $id );
		$this->assertSame( $id, $payload['id'] );
		$this->assertSame( get_post_field( 'post_name', $id ), $payload['slug'] );
		$this->assertNotSame( '', $payload['slug'] );
		$this->assertSame( 'Quarterly Report', $payload['title'] );
		$this->assertSame( 'A summary', $payload['description'] );
		$this->assertSame( array( 'InDesign' ), $payload['tools'] );
		$this->assertSame( 'PDF · 24 pp', $payload['format'] );
		$this->assertSame( 24, $payload['pages'] );
		$this->assertSame( 'none', $payload['downloadMode'] );
	}

	public function test_payload_is_null_for_other_post_types(): void {
		$this->assertNull( devportfolio_document_modal_payload( self::factory()->post->create() ) );
	}

	public function test_published_document_single_redirects_to_modal_deeplink(): void {
		$id = $this->make_document( array( 'title' => 'A Document' ) );
		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			add_query_arg( 'document_slug', get_post_field( 'post_name', $id ), get_post_type_archive_link( 'document' ) ),
			devportfolio_document_single_redirect_url()
		);
	}

	public function test_document_single_redirect_ignores_unpublished_posts(): void {
		$id = $this->make_document( array( 'status' => 'draft' ) );
		$this->go_to( get_permalink( $id ) );

		$this->assertSame( '', devportfolio_document_single_redirect_url() );
	}

	public function test_document_redirect_ignores_non_document_singles(): void {
		$id = self::factory()->post->create();
		$this->go_to( get_permalink( $id ) );

		$this->assertSame( '', devportfolio_document_single_redirect_url() );
	}

	public function test_document_redirect_hook_is_registered(): void {
		$this->assertSame(
			1,
			has_action( 'template_redirect', 'devportfolio_redirect_document_single' )
		);
	}

	// --- Block markup (DPD-009 shell, DPD-010 header, DPD-011 footer, DPD-012 download) ---

	public function test_desktop_and_mobile_dialogs_are_rendered_in_order(): void {
		$html = $this->render_modal();

		$this->assertSame( 2, substr_count( $html, '<dialog ' ) );
		$header = strpos( $html, 'class="documents-modal__header"' );
		$body   = strpos( $html, 'class="documents-modal__body"' );
		$footer = strpos( $html, 'class="documents-modal__footer"' );
		$this->assertNotFalse( $header );
		$this->assertLessThan( $body, $header );
		$this->assertLessThan( $footer, $body );
		$this->assertMatchesRegularExpression( '#<dialog[^>]+aria-labelledby="(documents-modal-title-\d+)"#', $html );
		$this->assertMatchesRegularExpression( '#<h2 class="documents-modal__title" id="documents-modal-title-\d+"#', $html );
	}

	public function test_body_has_empty_stage_and_two_arrows(): void {
		$html = $this->render_modal();
		$this->assertMatchesRegularExpression( '#<div class="documents-modal__stage"></div>#', $html );
		$prev  = strpos( $html, 'documents-modal__arrow--prev' );
		$stage = strpos( $html, 'documents-modal__stage' );
		$next  = strpos( $html, 'documents-modal__arrow--next' );
		$this->assertLessThan( $stage, $prev );
		$this->assertLessThan( $next, $stage );
		$this->assertStringContainsString( 'aria-label="Previous page"', $html );
		$this->assertStringContainsString( 'aria-label="Next page"', $html );
	}

	public function test_css_arrows_are_round_centred_and_hover_only(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__arrow\s*\{[^}]*top: 50%[^}]*border-radius: 50%[^}]*translateY\(-50%\)#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__arrow--prev\s*\{\s*left: 18px#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__arrow--next\s*\{\s*right: 18px#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__arrow\[aria-disabled="true"\]\s*\{[^}]*opacity: 0\.3#', $css );
		$this->assertMatchesRegularExpression( '#@media \(hover: hover\)\s*\{\s*\.documents-modal__arrow\s*\{\s*opacity: 0;#', $css );
		$this->assertStringContainsString( '.documents-modal__body:hover .documents-modal__arrow', $css );
		$this->assertStringContainsString( '.documents-modal__arrow:focus-visible', $css );
	}

	public function test_arrow_wiring(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "'aria-disabled', String( arrows.backOff )", $js );
		$this->assertStringContainsString( 'arrow === els.next ? 1 : -1', $js );
	}

	public function test_header_order_title_description_tags_format_download_close(): void {
		$html      = $this->render_modal();
		$positions = array();
		foreach ( array( 'documents-modal__title"', 'documents-modal__desc"', 'documents-modal__tags"', 'documents-modal__format"', 'documents-modal__download"', 'documents-modal__close"' ) as $needle ) {
			$pos = strpos( $html, 'class="' . $needle );
			$this->assertNotFalse( $pos, $needle );
			$positions[] = $pos;
		}
		$sorted = $positions;
		sort( $sorted );
		$this->assertSame( $sorted, $positions );
	}

	public function test_close_button_sits_in_the_same_row_as_download(): void {
		$html = $this->render_modal();
		$this->assertSame( 1, preg_match( '#<div class="documents-modal__meta">(.*?)</div>\s*</header>#s', $html, $m ) );
		$download = strpos( $m[1], 'documents-modal__download' );
		$close    = strpos( $m[1], 'documents-modal__close' );
		$this->assertNotFalse( $download );
		$this->assertNotFalse( $close );
		$this->assertLessThan( $close, $download, 'Close is the last (far right) control of the header.' );
		$this->assertStringContainsString( 'type="button"', substr( $m[1], $close - 30, 80 ) );
	}

	public function test_css_close_is_aligned_with_download(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertSame( 1, preg_match( '#\.documents-modal__close\s*\{([^}]*)\}#', $css, $close ) );
		$this->assertSame( 1, preg_match( '#\.documents-modal__download\s*\{([^}]*)\}#', $css, $download ) );
		$this->assertStringNotContainsString( 'position: absolute', $close[1] );
		preg_match( '#height: (\d+)px#', $close[1], $h_close );
		preg_match( '#min-height: (\d+)px#', $download[1], $h_download );
		$this->assertSame( $h_download[1], $h_close[1], 'Same height so both centre on one line.' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__meta\s*\{[^}]*align-items: center#s', $css );
	}

	public function test_dismissal_wiring(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "els.close.addEventListener( 'click', () => dialog.close() )", $js );
		$this->assertStringContainsString( 'event.target === dialog', $js );
		// Esc is the native dialog behaviour; nothing may cancel it.
		$this->assertStringNotContainsString( "'cancel'", $js );
	}

	public function test_download_button_and_close_have_labels(): void {
		$html = $this->render_modal();
		$this->assertStringContainsString( 'Download</span>', $html );
		$this->assertStringContainsString( 'aria-label="Close viewer"', $html );
	}

	public function test_pager_and_indicator_are_in_the_footer(): void {
		$html   = $this->render_modal();
		$footer = substr( $html, strpos( $html, '<footer' ) );
		$this->assertStringContainsString( 'documents-modal__pager', $footer );
		$this->assertStringContainsString( 'role="progressbar"', $footer );
	}

	public function test_the_footer_has_no_visible_page_count_or_spread_label(): void {
		$html = $this->render_modal();
		$this->assertStringNotContainsString( 'documents-modal__count', $html );
		$this->assertStringNotContainsString( 'documents-modal__legend', $html );
		$this->assertStringContainsString( '<footer class="documents-modal__footer" hidden>', $html, 'Hidden until a document with 2+ pages is open.' );
	}

	public function test_css_progress_is_a_thin_line_on_the_top_edge_of_the_footer(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__footer\s*\{[^}]*position: relative#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__indicator\s*\{[^}]*position: absolute[^}]*top: -1px[^}]*height: 3px#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__footer\[hidden\]#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__arrow\[hidden\]#', $css, 'Arrows are hidden for one-page documents.' );
		$this->assertStringNotContainsString( '.documents-modal__legend', $css );
	}

	public function test_view_script_gives_the_progress_line_its_position_as_text_and_hides_the_footer_for_one_page(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "els.bar.setAttribute( 'aria-valuetext'", $js );
		$this->assertStringContainsString( 'if ( pages < 2 )', $js );
		$this->assertStringContainsString( 'els.footer.hidden = true', $js );
		$this->assertStringContainsString( 'els.prev.hidden = pages < 2', $js );
		$this->assertStringContainsString( 'els.next.hidden = pages < 2', $js );
		$this->assertStringNotContainsString( 'els.legend', $js );
		$this->assertStringNotContainsString( 'els.count', $js );
	}

	public function test_payload_json_contains_published_documents_only_and_is_script_safe(): void {
		global $wpdb;
		$published = $this->make_document();
		// Bypass kses so the raw title reaches the payload.
		$wpdb->update( $wpdb->posts, array( 'post_title' => 'Evil </script><b>x</b>' ), array( 'ID' => $published ) );
		clean_post_cache( $published );
		$draft     = $this->make_document( array( 'title' => 'Draft doc', 'status' => 'draft' ) );

		$html = $this->render_modal();
		$data = $this->payload_from( $html );

		$this->assertArrayHasKey( (string) $published, $data );
		$this->assertArrayNotHasKey( (string) $draft, $data );
		$this->assertSame( 'Evil </script><b>x</b>', $data[ (string) $published ]['title'] );
		$this->assertSame( 1, substr_count( $html, '</script>' ) );
	}

	public function test_book_carries_the_open_trigger_attribute(): void {
		$id    = $this->make_document();
		$block = new WP_Block(
			array(
				'blockName'    => 'devportfolio/document-book',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array( 'postId' => $id )
		);
		$this->assertStringContainsString( 'data-document-id="' . $id . '"', $block->render() );
	}

	public function test_template_places_the_modal_block(): void {
		$this->assertStringContainsString( '<!-- wp:devportfolio/document-modal /-->', $this->read( 'templates/archive-document.html' ) );
	}

	// --- CSS structure (parsed text; there is no browser runner) -----------------

	public function test_css_shell_rules(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertStringContainsString( 'width: min(1680px, calc(100% - 32px))', $css );
		$this->assertStringContainsString( 'border-radius: 14px', $css );
		$this->assertStringContainsString( '.documents-modal::backdrop', $css );
		$this->assertMatchesRegularExpression( '#@media \(max-width: 1200px\)\s*\{\s*\.documents-modal\s*\{[^}]*width: 100%[^}]*height: 100dvh[^}]*border-radius: 0#s', $css );
		$this->assertMatchesRegularExpression( '#@media \(max-width: 700px\)[^{]*\{[^}]*\.documents-modal,\s*\.documents-modal__footer\s*\{\s*display: none#s', $css );
		$this->assertMatchesRegularExpression( '#@media \(prefers-reduced-motion: reduce\)#', $css );
	}

	public function test_css_header_pager_rules(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '#\.documents-modal__header\s*\{[^}]*border-bottom: 1px solid#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__title\s*\{[^}]*Georgia[^}]*font-weight: 700#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__desc\s*\{[^}]*white-space: nowrap#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__pg\.is-on\s*\{[^}]*background: var\(--dm-ink\)#', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__pg\[aria-disabled="true"\]\s*\{[^}]*opacity: 0\.35#', $css );
	}

	public function test_view_script_wiring(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( '(min-width: 701px)', $this->read( 'blocks/document-modal/routing.js' ), 'The one definition of the 700px rule.' );
		$this->assertStringContainsString( 'createRouting()', $js );
		$this->assertStringContainsString( 'event.target === dialog', $js );
		$this->assertStringContainsString( 'showModal()', $js );
		$this->assertStringContainsString( 'documents:modal-change', $js );
		$this->assertStringContainsString( "import {", $js );
	}

	public function test_view_script_deeplink_wiring(): void {
		$js = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "from './deeplink.js'", $js );
		$this->assertStringContainsString( 'history.pushState', $js );
		$this->assertStringContainsString( 'history.back()', $js );
		$this->assertStringContainsString( 'history.replaceState', $js );
		$this->assertStringContainsString( "addEventListener( 'popstate'", $js );
		$this->assertStringContainsString( 'idBySlug', $js );
	}
}
