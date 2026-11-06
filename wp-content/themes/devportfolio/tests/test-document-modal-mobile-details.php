<?php
/**
 * Tests for the mobile Details sheet (DPD-023): the category and file type helpers and payload keys, the
 * wording, the shared sheet components, the removal of the old popover and the structure of the styles.
 *
 * There is no browser runner in the repo: the content rules of the sheet are tested with node
 * (`blocks/document-modal/tests/details-sheet.test.mjs`, `sheet-controller.test.mjs`, `bottom-sheet.test.mjs`),
 * and this file checks the PHP side and that the scripts and CSS keep the structure the plan asks for.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Modal_Mobile_Details extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	/** The CSS of the Details sheet, from its block comment to the sideways Pages rules. */
	private function details_css(): string {
		$css   = $this->read( 'blocks/document-modal/style.css' );
		$start = strpos( $css, 'Mobile Details sheet (DPD-023)' );
		$this->assertNotFalse( $start, 'The Details sheet CSS block is missing.' );
		return substr( $css, (int) $start );
	}

	private function document( array $categories = array(), string $filetype = 'pdf', int $pages = 24 ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_status' => 'publish',
				'post_title'  => 'Inbox Clean-up Report',
			)
		);
		foreach ( $categories as $name ) {
			if ( ! term_exists( $name, 'document_category' ) ) {
				wp_insert_term( $name, 'document_category' );
			}
		}
		if ( $categories ) {
			wp_set_object_terms( $post_id, $categories, 'document_category' );
		}
		update_post_meta( $post_id, 'cptdp_filetype', $filetype );
		update_post_meta( $post_id, 'cptdp_total_pages', $pages );
		return $post_id;
	}

	/* ---- Data ------------------------------------------------------- */

	public function test_the_category_is_the_first_term_by_name(): void {
		$id = $this->document( array( 'Reports', 'Admin & Email' ) );
		$this->assertSame( 'Admin &amp; Email', devportfolio_document_category_name( $id ), 'The term name as WordPress stores it (the cover escapes it).' );
	}

	public function test_no_category_is_an_empty_string(): void {
		$this->assertSame( '', devportfolio_document_category_name( $this->document() ) );
		$this->assertSame( '', devportfolio_document_category_name( 0 ) );
	}

	public function test_the_file_type_is_upper_cased_and_never_non_text(): void {
		$this->assertSame( 'PDF', devportfolio_document_file_type_label( ' pdf ' ) );
		$this->assertSame( '', devportfolio_document_file_type_label( '' ) );
		$this->assertSame( '', devportfolio_document_file_type_label( null ) );
		$this->assertSame( '', devportfolio_document_file_type_label( array( 'pdf' ) ) );
	}

	public function test_the_format_label_and_the_file_type_agree(): void {
		$this->assertSame( 'PDF · 24 pp', devportfolio_document_format_label( 'pdf', 24 ) );
		$this->assertSame( 'PPTX · 12 slides', devportfolio_document_format_label( 'pptx', 12, 'pptx' ) );
		$this->assertSame( '24 pp', devportfolio_document_format_label( '', 24 ) );
	}

	public function test_the_payload_carries_the_category_and_the_file_type(): void {
		$id      = $this->document( array( 'Admin & Email' ), 'pdf', 24 );
		$payload = devportfolio_document_modal_payload( $id );
		$this->assertSame( 'Admin & Email', $payload['category'], 'Plain text, as textContent writes it.' );
		$this->assertSame( 'PDF', $payload['fileType'] );
		$this->assertSame( 24, $payload['pages'] );
		$this->assertSame( 'PDF · 24 pp', $payload['format'], 'The cover line is unchanged.' );
	}

	public function test_a_document_without_a_category_has_an_empty_one(): void {
		$payload = devportfolio_document_modal_payload( $this->document() );
		$this->assertSame( '', $payload['category'] );
	}

	public function test_the_cover_still_shows_the_category(): void {
		$id    = $this->document( array( 'Reports' ) );
		$block = new WP_Block( array( 'blockName' => 'devportfolio/document-book' ), array( 'postId' => $id ) );
		$html  = $block->render();
		$this->assertSame( 1, preg_match( '#<span class="documents-book__cat">([^<]*)</span>#', $html, $m ) );
		$this->assertSame( 'Reports', $m[1] );
		$this->assertStringContainsString( 'devportfolio_document_category_name(', $this->read( 'blocks/document-book/render.php' ), 'One source of truth for the category.' );
	}

	/* ---- Wording ---------------------------------------------------- */

	public function test_the_strings_carry_the_sheet_wording(): void {
		$html = do_blocks( '<!-- wp:devportfolio/document-modal /-->' );
		preg_match( '/data-mobile-strings="([^"]+)"/', $html, $m );
		$strings = json_decode( html_entity_decode( $m[1] ), true );
		$this->assertSame( 'Close details', $strings['closeDetails'] );
		$this->assertSame( 'Format', $strings['formatRow'] );
		$this->assertSame( 'Download', $strings['download'] );
		$this->assertArrayNotHasKey( 'toolsLabel', $strings, 'The wireframe has no heading above the tools.' );
	}

	/* ---- Scripts: shared components, no copies ----------------------- */

	public function test_the_details_sheet_is_loaded_on_demand_and_registered(): void {
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertStringContainsString( "import( './details-sheet.js' )", $mobile );
		$this->assertDoesNotMatchRegularExpression( "/^import .* from '\.\/details-sheet\.js';/m", $mobile );
		$this->assertStringContainsString( "sheets.register( 'details'", $mobile );
		$this->assertStringContainsString( "dialog.addEventListener( 'documents:mobile-details', openDetails )", $mobile );
		$this->assertStringContainsString( "onDetails: () => emit( 'documents:mobile-details' )", $mobile );
	}

	public function test_both_sheets_use_the_shared_shell_and_none_copies_it(): void {
		foreach ( array( 'pages-sheet.js', 'details-sheet.js' ) as $file ) {
			$js = $this->read( 'blocks/document-modal/' . $file );
			$this->assertStringContainsString( 'createBottomSheet(', $js, $file );
			foreach ( array( 'pointerdown', 'setPointerCapture', 'panel.inert', "'cancel'", 'documents-mobile__dim' ) as $shell_only ) {
				$this->assertStringNotContainsString( $shell_only, $js, $file . ' must not copy the shell: ' . $shell_only );
			}
		}
	}

	public function test_the_details_sheet_reuses_the_download_helpers(): void {
		$js = $this->read( 'blocks/document-modal/details-sheet.js' );
		$this->assertStringContainsString( "from './download.js'", $js );
		$this->assertStringContainsString( 'applyDownload( download, doc )', $js );
		$this->assertStringContainsString( 'guardDisabledDownload( download )', $js );
		$this->assertStringContainsString( "'data-no-drag'", $js, 'The X must not start a drag.' );
	}

	public function test_the_details_sheet_is_built_once(): void {
		$js = $this->read( 'blocks/document-modal/details-sheet.js' );
		$this->assertSame( 1, substr_count( $js, 'createBottomSheet(' ) );
		$this->assertStringContainsString( 'replaceChildren(', $js, 'Reopening rewrites the text; nothing is rebuilt.' );
	}

	public function test_only_pages_closes_when_the_phone_is_rotated(): void {
		$mobile = $this->read( 'blocks/document-modal/mobile.js' );
		$this->assertMatchesRegularExpression( "/sheets\.register\( 'pages', \{\s*closeOnRotate: true/", $mobile );
		$this->assertStringContainsString( 'sheets.closeOnRotate()', $mobile );
		$this->assertDoesNotMatchRegularExpression( "/sheets\.register\( 'details', \{\s*closeOnRotate/", $mobile );
	}

	public function test_the_popover_is_gone(): void {
		$viewer = $this->read( 'blocks/document-modal/mobile-viewer.js' );
		foreach ( array( 'details-panel', 'setDetails', 'toggleDetails', 'hasDetails', 'aria-expanded' ) as $gone ) {
			$this->assertStringNotContainsString( $gone, $viewer, $gone );
		}
		$this->assertStringNotContainsString( 'toggleDetails', $this->read( 'blocks/document-modal/mobile.js' ) );
		$this->assertStringNotContainsString( 'documents-mobile__details-panel', $this->read( 'blocks/document-modal/style.css' ) );
	}

	/* ---- Styles ----------------------------------------------------- */

	public function test_the_download_button_is_full_width_dark_and_pinned(): void {
		$css = $this->details_css();
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-download\s*\{[^}]*width:\s*100%/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-download\s*\{[^}]*background:\s*var\(--dm-ink\)/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__sheet-foot\s*\{[^}]*flex:\s*none/', $css, 'It stays visible while the text scrolls.' );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-download\[aria-disabled="true"\]/', $css );
	}

	public function test_the_category_pill_is_outlined_and_the_rows_have_thin_lines(): void {
		$css = $this->details_css();
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-category\s*\{[^}]*border:\s*1px solid var\(--dm-ink\)/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-row\s*\{[^}]*justify-content:\s*space-between/', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-row\s*\{[^}]*border-bottom:\s*1px solid var\(--dm-line\)/', $css );
	}

	public function test_the_title_is_serif_and_bold(): void {
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-title\s*\{[^}]*font-family:\s*Georgia[^}]*font-weight:\s*700/s', $this->details_css() );
	}

	public function test_held_sideways_the_sheet_has_two_columns_without_a_new_query(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );
		$this->assertMatchesRegularExpression( '/@media \(pointer: coarse\) and \(orientation: landscape\) and \(max-height: 520px\) \{.*?\.documents-mobile__sheet--details\s*\{[^}]*display:\s*grid[^}]*grid-template-areas/s', $css );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__sheet--details \.documents-mobile__sheet-body\s*\{[^}]*display:\s*contents/', $css );
	}
}
