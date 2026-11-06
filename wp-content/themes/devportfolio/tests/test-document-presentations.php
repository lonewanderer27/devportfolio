<?php
/**
 * Tests for presentations in the document modal (DPD-026): file kind, slide wording, the payload keys
 * `kind` and `nativeUrl`, the native PPTX viewer and its structure. There is no browser
 * runner: the pure helpers (mode.js) are tested with node, see blocks/document-modal/tests/.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Presentations extends WP_UnitTestCase {

	private const MIMES = array(
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'odp'  => 'application/vnd.oasis.opendocument.presentation',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'doc'  => 'application/msword',
		'odt'  => 'application/vnd.oasis.opendocument.text',
	);

	/**
	 * Documents whose generated page images are removed after the test.
	 *
	 * @var int[]
	 */
	private array $with_pages = array();

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

		// Saving a document must never start a real conversion here.
		add_filter( 'cptdp_pptx_available', '__return_false' );
		add_filter( 'cptdp_pages_server_available', '__return_false' );
		add_filter( 'cptdp_pages_spawn_cron', '__return_false' );
		delete_option( 'cptdp_documents' );
	}

	public function tear_down(): void {
		foreach ( $this->with_pages as $id ) {
			CPTDP_Page_Store::clear( $id, true );
		}
		$this->with_pages = array();
		delete_option( 'cptdp_documents' );

		parent::tear_down();
	}

	private function attachment( string $mime, string $file ): int {
		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'post_title'     => 'file',
			)
		);
		update_post_meta( $id, '_wp_attached_file', $file );

		return $id;
	}

	/**
	 * A real file on disk, so size and URL work.
	 */
	private function real_attachment( string $kind, string $bytes = 'file bytes' ): int {
		$upload = wp_upload_dir();
		wp_mkdir_p( $upload['path'] );
		$path = $upload['path'] . '/dpd026-' . wp_generate_uuid4() . '.' . $kind;
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$id = $this->attachment( self::MIMES[ $kind ], '' );
		update_attached_file( $id, $path );

		return $id;
	}

	private function document( int $attachment = 0, array $meta = array() ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_title'  => 'Deck',
				'post_status' => 'publish',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		if ( $attachment > 0 ) {
			update_post_meta( $id, 'cptdp_document_file', $attachment );
		}

		return $id;
	}

	/**
	 * Give a document valid converted slides (placeholder files plus the manifest the plugin reads).
	 */
	private function convert( int $id, int $count = 3 ): void {
		$folder = CPTDP_Page_Store::folder_for( $id );
		wp_mkdir_p( $folder );
		for ( $i = 1; $i <= $count; $i++ ) {
			file_put_contents( $folder . '/' . CPTDP_Page_Store::page_filename( $i, 'webp' ), 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}
		CPTDP_Page_Store::save_manifest(
			$id,
			array(
				'count'  => $count,
				'width'  => 1654,
				'height' => 930,
				'ext'    => 'webp',
				'dir'    => basename( $folder ),
			)
		);
		$this->with_pages[] = $id;
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertNotFalse( $contents, $relative );
		if ( 'blocks/document-modal/viewer.js' === $relative ) {
			// The page sources and the status line moved to page-source.js and status.js (DPD-021): the viewer is read as one piece.
			$contents .= (string) file_get_contents( $this->theme_dir() . '/blocks/document-modal/page-source.js' ) . (string) file_get_contents( $this->theme_dir() . '/blocks/document-modal/status.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return $contents;
	}

	private function native_on(): void {
		update_option( 'cptdp_documents', array( 'native_pptx' => true ) );
	}

	private function native_docx_on(): void {
		update_option( 'cptdp_documents', array( 'native_docx' => true ) );
	}

	// --- File kind and wording ----------------------------------------------------------------

	public function test_the_file_kind_follows_the_mime_type(): void {
		foreach ( self::MIMES as $kind => $mime ) {
			$this->assertSame( $kind, devportfolio_document_file_kind( $this->document( $this->attachment( $mime, 'f.' . $kind ) ) ), $kind );
		}

		$this->assertSame( 'pdf', devportfolio_document_file_kind( $this->document( $this->attachment( 'application/pdf', 'f.pdf' ) ) ) );
		$this->assertSame( 'other', devportfolio_document_file_kind( $this->document( $this->attachment( 'image/png', 'f.png' ) ) ) );
		$this->assertSame( 'docx', devportfolio_document_file_kind( $this->document( $this->attachment( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'f.docx' ) ) ) );
		$this->assertSame( 'other', devportfolio_document_file_kind( $this->document() ), 'No file.' );
		$this->assertSame( 'other', devportfolio_document_file_kind( 0 ) );
	}

	public function test_a_generic_mime_type_uses_the_extension(): void {
		$zip = $this->attachment( 'application/zip', 'x.zip' );
		update_attached_file( $zip, '/tmp/Deck.PPTX' );
		$this->assertSame( 'pptx', devportfolio_document_file_kind( $this->document( $zip ) ) );

		update_attached_file( $zip, '/tmp/archive.zip' );
		$this->assertSame( 'other', devportfolio_document_file_kind( $this->document( $zip ) ) );
	}

	public function test_presentations_are_counted_in_slides_and_the_rest_in_pages(): void {
		foreach ( array( 'pptx', 'ppt', 'odp' ) as $kind ) {
			$this->assertSame( 'slide', devportfolio_document_unit( $kind ), $kind );
		}
		foreach ( array( 'pdf', 'other', '', 'xlsx', 'docx' ) as $kind ) {
			$this->assertSame( 'page', devportfolio_document_unit( $kind ), $kind );
		}
	}

	public function test_the_format_label_says_slides_for_presentations_only(): void {
		$this->assertSame( 'PPTX · 12 slides', devportfolio_document_format_label( 'pptx', 12, 'pptx' ) );
		$this->assertSame( 'PPTX · 1 slide', devportfolio_document_format_label( 'PPTX', 1, 'pptx' ) );
		$this->assertSame( 'PPT · 3 slides', devportfolio_document_format_label( 'ppt', '3', 'ppt' ) );
		$this->assertSame( 'ODP', devportfolio_document_format_label( 'odp', 0, 'odp' ) );
		$this->assertSame( '4 slides', devportfolio_document_format_label( '', 4, 'odp' ) );

		// Unchanged for everything else, with or without the new argument.
		$this->assertSame( 'PDF · 24 pp', devportfolio_document_format_label( 'PDF', 24 ) );
		$this->assertSame( 'PDF · 24 pp', devportfolio_document_format_label( 'PDF', 24, 'pdf' ) );
		$this->assertSame( 'PDF · 1 p', devportfolio_document_format_label( 'PDF', 1, 'other' ) );
		$this->assertSame( 'DOCX · 12 pp', devportfolio_document_format_label( 'DOCX', 12, 'docx' ) );
		$this->assertSame( 'ODT · 1 p', devportfolio_document_format_label( 'ODT', 1, 'odt' ) );
	}

	public function test_the_modal_and_the_shelf_use_the_slide_wording(): void {
		$id = $this->document( $this->attachment( self::MIMES['pptx'], 'd.pptx' ), array( 'cptdp_filetype' => 'pptx', 'cptdp_total_pages' => 12 ) );

		$this->assertSame( 'PPTX · 12 slides', devportfolio_document_modal_payload( $id )['format'] );
		$this->assertSame( 'PPTX · 12 slides', devportfolio_document_shelf_item( $id )['format'] );

		$pdf = $this->document( $this->attachment( 'application/pdf', 'd.pdf' ), array( 'cptdp_filetype' => 'pdf', 'cptdp_total_pages' => 12 ) );
		$this->assertSame( 'PDF · 12 pp', devportfolio_document_modal_payload( $pdf )['format'] );
	}

	public function test_the_unit_strings_cover_both_units(): void {
		$strings = devportfolio_document_unit_strings();

		$this->assertSame( array( 'page', 'slide' ), array_keys( $strings ) );
		foreach ( $strings as $words ) {
			$this->assertSame( array( 'one', 'many', 'prev', 'next', 'nav' ), array_keys( $words ) );
			$this->assertNotContains( '', $words );
		}
		$this->assertSame( 'Previous slide', $strings['slide']['prev'] );
		$this->assertSame( 'Previous page', $strings['page']['prev'] );
	}

	// --- Payload ------------------------------------------------------------------------------

	public function test_payload_for_an_unconverted_presentation(): void {
		$id      = $this->document( $this->real_attachment( 'pptx' ) );
		$payload = devportfolio_document_modal_payload( $id );

		$this->assertSame( 'pptx', $payload['kind'] );
		$this->assertSame( '', $payload['viewerUrl'], 'The browser cannot render a presentation: no pdf.js fallback.' );
		$this->assertSame( 0, $payload['pdfBytes'] );
		$this->assertSame( array(), $payload['images'] );
		$this->assertSame( '', $payload['nativeUrl'], 'The native viewer is off by default.' );
		$this->assertSame( 'file', $payload['downloadMode'], 'Download still gives the original file.' );
	}

	public function test_the_payload_title_is_plain_text_for_textcontent(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_status' => 'publish',
				'post_title'  => 'Bay Area Real Estate & Stager "Email" Workflow',
			)
		);

		$title = devportfolio_document_modal_payload( $id )['title'];

		$this->assertStringContainsString( 'Estate & Stager', $title );
		$this->assertStringNotContainsString( '&#038;', $title );
		$this->assertStringNotContainsString( '&amp;', $title );
		$this->assertStringContainsString( ' Workflow', $title );
	}

	public function test_payload_kind_for_other_files(): void {
		$this->assertSame( 'pdf', devportfolio_document_modal_payload( $this->document( $this->attachment( 'application/pdf', 'a.pdf' ) ) )['kind'] );
		$this->assertSame( 'other', devportfolio_document_modal_payload( $this->document() )['kind'] );
		$this->assertSame( '', devportfolio_document_modal_payload( $this->document() )['nativeUrl'] );
	}

	public function test_payload_for_converted_slides_has_images_and_no_trim(): void {
		$id = $this->document( $this->real_attachment( 'pptx' ) );
		$this->convert( $id, 3 );

		$payload = devportfolio_document_modal_payload( $id );

		$this->assertCount( 3, $payload['images'] );
		$this->assertSame( 0.0, $payload['cropBottom'] );
		$this->assertEqualsWithDelta( 1654 / 930, $payload['ratio'], 0.001 );
		$this->assertSame( 1654, $payload['imageWidth'] );
		$this->assertSame( '', $payload['nativeUrl'], 'With the native viewer off the converted slides are what visitors see.' );

		$this->native_on();
		$both = devportfolio_document_modal_payload( $id );
		$this->assertCount( 3, $both['images'], 'The converted slides are kept as the native viewer\'s fallback.' );
		$this->assertNotSame( '', $both['nativeUrl'], 'The native viewer takes over from them.' );
	}

	public function test_the_native_url_reaches_the_payload_only_for_a_pptx(): void {
		$this->native_on();

		$attachment = $this->real_attachment( 'pptx' );
		$id         = $this->document( $attachment );
		$this->assertSame( wp_get_attachment_url( $attachment ), devportfolio_document_modal_payload( $id )['nativeUrl'] );

		foreach ( array( 'ppt', 'odp' ) as $kind ) {
			$this->assertSame( '', devportfolio_document_modal_payload( $this->document( $this->real_attachment( $kind ) ) )['nativeUrl'], $kind . ': the viewer cannot read it.' );
		}
		$this->assertSame( '', devportfolio_document_modal_payload( $this->document( $this->attachment( 'application/pdf', 'a.pdf' ) ) )['nativeUrl'] );
	}

	public function test_the_native_url_stays_empty_unless_the_setting_is_on(): void {
		$id = $this->document( $this->real_attachment( 'pptx' ) );

		$this->assertSame( '', devportfolio_document_modal_payload( $id )['nativeUrl'] );

		$this->native_on();
		$this->assertNotSame( '', devportfolio_document_modal_payload( $id )['nativeUrl'] );
	}

	public function test_the_native_url_is_only_available_for_docx(): void {
		$this->native_docx_on();
		$docx = $this->real_attachment( 'docx' );
		$id   = $this->document( $docx, array( 'cptdp_filetype' => 'DOCX', 'cptdp_total_pages' => 1 ) );

		$this->assertSame( wp_get_attachment_url( $docx ), devportfolio_document_modal_payload( $id )['nativeUrl'] );
		$this->assertSame( 'DOCX · 1 p', devportfolio_document_modal_payload( $id )['format'] );

		foreach ( array( 'doc', 'odt' ) as $kind ) {
			$this->assertSame( '', devportfolio_document_modal_payload( $this->document( $this->real_attachment( $kind ) ) )['nativeUrl'], $kind . ': the native viewer cannot read it.' );
		}
	}

	public function test_the_payload_json_stays_safe_with_the_new_keys(): void {
		$this->native_on();
		$id = $this->document( $this->real_attachment( 'pptx' ), array( 'cptdp_filetype' => '</script><b>' ) );

		$html = do_blocks( '<!-- wp:devportfolio/document-modal /-->' );

		$this->assertSame( 1, substr_count( $html, '<script' ), 'Only the payload script.' );
		$this->assertStringContainsString( '"nativeUrl":"http', $html );
		$this->assertStringContainsString( '"kind":"pptx"', $html );
		unset( $id );
	}

	// --- Markup -------------------------------------------------------------------------------

	public function test_the_modal_carries_the_wording_and_the_messages_but_no_frame_or_canvas(): void {
		$this->native_on();
		$this->document( $this->real_attachment( 'pptx' ) );

		$html = do_blocks( '<!-- wp:devportfolio/document-modal /-->' );

		$this->assertStringNotContainsString( '<iframe', $html, 'Nothing third-party is ever framed.' );
		$this->assertStringNotContainsString( '<canvas', $html, 'The canvas is created by the script when a deck is drawn.' );
		$this->assertSame( 1, preg_match( '#data-units="([^"]*)"#', $html, $m ) );
		$units = json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true );
		$this->assertSame( 'Slides', $units['slide']['many'] );
		$this->assertStringContainsString( 'data-hint-slide="Presentations can’t always be previewed here. Download the file to open it in PowerPoint."', $html );
		$this->assertStringNotContainsString( 'native-note', $html, 'No explanatory note under the slide.' );
		$this->assertStringContainsString( 'data-hint="Use Download to open it."', $html, 'The PDF hint is unchanged.' );
	}

	// --- Script and style structure ------------------------------------------------------------

	public function test_the_native_viewer_loads_only_on_demand_and_is_locked_down(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );

		$this->assertStringContainsString( "import { viewerMode, unitOf, wording, pieceLabel, canDrawOffice, hintKeyFor } from './mode.js'", $js );
		$this->assertStringContainsString( 'const PPTX_LIB = new URL( `./vendor/ooxml/pptx.mjs?v=${ PPTX_VERSION }`, import.meta.url ).href;', $js );
		$this->assertStringContainsString( 'wasmUrl: PPTX_WASM', $js );
		$this->assertStringContainsString( 'import( PPTX_LIB )', $js, 'A dynamic import: nothing is downloaded for other documents.' );
		$this->assertDoesNotMatchRegularExpression( '#^import .* from \'\./vendor/ooxml#m', $js, 'No static import of the viewer.' );
		$this->assertStringContainsString( "if ( viewerMode( doc ) === 'native' )", $js );
		$this->assertStringContainsString( 'canDrawOffice( readNativeEnvironment() )', $js );

		foreach ( array( 'enableTextSelection: true', 'enableHyperlinks: false', 'enableMediaPlayback: false', 'enableElementSelection: false', 'useGoogleFonts: false' ) as $option ) {
			$this->assertStringContainsString( $option, $js, $option );
		}
		$this->assertStringContainsString( 'instance.load( doc.nativeUrl )', $js );
		$this->assertStringContainsString( "const nativeShow = doc.kind === 'xlsx' ? showXlsx : ( doc.kind === 'docx' ? showDocx : showPptx );", $js );
		$this->assertStringContainsString( 'The native viewer could not draw it: use the stored slides or pages when there are some.', $js, 'The converted slides are the fallback.' );
		$this->assertStringContainsString( 'limits.nativeTimeoutMs', $js );
		$this->assertStringNotContainsString( '<iframe', $js );
		$this->assertStringNotContainsString( "createElement( 'iframe' )", $js );
	}

	public function test_the_native_viewer_is_destroyed_on_close_and_driven_by_the_modal_controls(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );

		$this->assertStringContainsString( 'destroyNative( pptx )', $js, 'Closing frees the workers.' );
		$this->assertStringContainsString( 'pptx.goToSlide( target - 1 )', $js );
		$this->assertStringContainsString( 'return pptxBusy;', $js, 'A slide still being drawn ignores another turn.' );
		$this->assertStringContainsString( 'onGeometry( { pages: pptxCount, step } )', $js, 'Footer, pager and arrows show like for the book.' );
		$this->assertStringContainsString( 'canvas.setAttribute( \'aria-label\', pieceLabel( words, index + 1', $js );
		$this->assertStringNotContainsString( 'note', preg_replace( '#//.*|/\*.*?\*/#s', '', substr( $js, strpos( $js, 'const showPptx' ), 900 ) ), 'The canvas is the only thing in the stage.' );
		$this->assertStringNotContainsString( 'innerHTML', $js );

		$view = $this->read( 'blocks/document-modal/view.js' );
		$this->assertStringContainsString( "viewerMode( doc ) === 'native' ? 'landscape' : 'portrait'", $view, 'No two-page footer flash while the deck loads.' );
	}

	public function test_no_third_party_address_is_hardcoded_in_the_scripts_or_markup(): void {
		foreach ( array( 'blocks/document-modal/viewer.js', 'blocks/document-modal/view.js', 'blocks/document-modal/mode.js', 'blocks/document-modal/render.php', 'functions.php' ) as $file ) {
			$this->assertStringNotContainsString( 'officeapps', $this->read( $file ), $file );
			$this->assertStringNotContainsString( 'googleapis', $this->read( $file ), $file );
		}
	}

	public function test_the_entry_and_wasm_urls_carry_the_library_version_so_a_long_cache_cannot_go_stale(): void {
		$js     = $this->read( 'blocks/document-modal/viewer.js' );
		$readme = $this->read( 'blocks/document-modal/vendor/ooxml/README.md' );

		$this->assertSame( 1, preg_match( "#const OOXML_VERSION = '([0-9.]+)';#", $js, $m ), 'The version constant exists.' );
		$this->assertStringContainsString( '**' . $m[1] . '**', $readme, 'It matches the vendored release.' );
		$this->assertStringContainsString( 'pptx_parser_bg.wasm?v=${ PPTX_VERSION }', $js );
		$this->assertStringContainsString( 'pptx.mjs?v=${ PPTX_VERSION }', $js );

		// Every other file the viewer loads has a content hash in its name, so only these two can go stale.
		$unhashed = array_filter(
			array_map( 'basename', glob( $this->theme_dir() . '/blocks/document-modal/vendor/ooxml/*.{js,mjs,wasm}', GLOB_BRACE ) ),
			static function ( string $file ): bool {
				return 0 === preg_match( '/-[A-Za-z0-9_-]{8}\.m?js$/', $file );
			}
		);
		$unhashed = array_values( $unhashed );
		sort( $unhashed );
		$this->assertSame( array( 'docx-source-worker.mjs', 'docx.mjs', 'docx_parser_bg.wasm', 'pptx-source-worker.mjs', 'pptx.mjs', 'pptx_parser_bg.wasm', 'xlsx-source-worker.mjs', 'xlsx.mjs', 'xlsx_parser_bg.wasm' ), $unhashed );
	}

	public function test_the_canvas_is_drawn_at_the_size_it_is_shown(): void {
		$js = $this->read( 'blocks/document-modal/viewer.js' );

		$this->assertStringContainsString( 'const room = fitPage( { stageW: size.w, stageH: Math.max( 40, size.h ), ratio: 16 / 9 } );', $js );
		$this->assertStringContainsString( 'width: Math.max( 640, Math.min( 1920, room.pageW ) )', $js );
		$this->assertStringContainsString( 'dpr: Math.min( 2, window.devicePixelRatio || 1 )', $js, 'Sharp on retina, never beyond 2x.' );
		$this->assertStringNotContainsString( 'Math.max( 1100', $js, 'No fixed minimum that makes small stages draw big.' );
	}

	public function test_the_bundled_viewer_is_vendored_with_its_licence_and_pinned_version(): void {
		$dir = $this->theme_dir() . '/blocks/document-modal/vendor/ooxml';

		foreach ( array( 'pptx.mjs', 'pptx_parser_bg.wasm', 'LICENSE', 'THIRD_PARTY_NOTICES.md', 'README.md' ) as $file ) {
			$this->assertFileExists( $dir . '/' . $file, $file );
		}
		$this->assertNotEmpty( glob( $dir . '/assets/render-worker-*.js' ), 'The render worker sits next to the entry.' );
		$this->assertStringContainsString( 'MIT License', $this->read( 'blocks/document-modal/vendor/ooxml/LICENSE' ) );
		$this->assertStringContainsString( '**0.89.7**', $this->read( 'blocks/document-modal/vendor/ooxml/README.md' ) );
		$this->assertSame( "\0asm", substr( (string) file_get_contents( $dir . '/pptx_parser_bg.wasm', false, null, 0, 4 ), 0, 4 ), 'A real WebAssembly module.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Every file the entry imports is present (the import graph was copied whole).
		foreach ( glob( $dir . '/*.{js,mjs}', GLOB_BRACE ) as $file ) {
			preg_match_all( '#["\'`]\./([A-Za-z0-9_.\-/]+\.m?js)["\'`]#', (string) file_get_contents( $file ), $m ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			foreach ( $m[1] as $imported ) {
				$this->assertFileExists( $dir . '/' . $imported, basename( $file ) . ' imports ' . $imported );
			}
		}
	}

	public function test_the_pager_gives_the_focus_back_after_it_is_redrawn(): void {
		$view = $this->read( 'blocks/document-modal/view.js' );

		$this->assertStringContainsString( 'const focused = els.pager.contains( document.activeElement )', $view );
		$this->assertStringContainsString( 'again.focus()', $view );
		$this->assertLessThan( strpos( $view, 'els.pager.replaceChildren()' ), strpos( $view, 'const focused = els.pager.contains' ), 'It is read before the pager is rebuilt.' );
	}

	public function test_view_js_words_follow_the_unit(): void {
		$view = $this->read( 'blocks/document-modal/view.js' );

		$this->assertStringContainsString( "import { viewerMode, unitOf, wording, positionText } from './mode.js'", $view );
		$this->assertStringContainsString( "readJson( 'data-units' )", $view );
		$this->assertStringContainsString( 'positionText( words, first, both ? last : first, pages )', $view );
		$this->assertStringContainsString( "'aria-label': `\${ words.one } \${ item }`", $view );
		$this->assertStringContainsString( "els.prev.setAttribute( 'aria-label', words.prev )", $view );
		$this->assertStringContainsString( "els.next.setAttribute( 'aria-label', words.next )", $view );
		$this->assertStringNotContainsString( '`Pages ${', $view );
	}

	public function test_a_site_that_saved_the_old_selectable_text_setting_keeps_the_pdf_native_viewer(): void {
		update_option( 'cptdp_documents', array( 'selectable_text' => true ) );

		$this->assertTrue( devportfolio_document_selectable_text(), 'The theme reads the PDF checkbox through the plugin, old name included.' );

		update_option( 'cptdp_documents', array( 'native_pdf' => true ) );
		$this->assertTrue( devportfolio_document_selectable_text() );

		update_option( 'cptdp_documents', array( 'native_pptx' => true ) );
		$this->assertFalse( devportfolio_document_selectable_text(), 'The PowerPoint checkbox does not switch PDFs.' );
	}

	public function test_the_pdf_fallback_and_selectable_text_never_see_a_presentation(): void {
		$id = $this->document( $this->real_attachment( 'pptx' ) );
		update_option( 'cptdp_documents', array( 'native_pdf' => true ) );

		$this->assertSame( '', devportfolio_document_modal_payload( $id )['viewerUrl'] );
		$this->assertStringContainsString( 'if ( selectableText && Boolean( doc.viewerUrl ) )', $this->read( 'blocks/document-modal/viewer.js' ) );
	}

	public function test_css_for_the_native_viewer(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );

		$this->assertMatchesRegularExpression( '#\.documents-modal__native\s*\{[^}]*display: flex[^}]*flex-direction: column[^}]*height: 100%#s', $css );
		$this->assertMatchesRegularExpression( '#\.documents-modal__native-canvas\s*\{[^}]*display: block[^}]*max-width: 100%[^}]*background: \#fff#s', $css );
		$this->assertStringNotContainsString( 'native-note', $css );
		$this->assertStringNotContainsString( 'documents-modal--office', $css );
	}
}
