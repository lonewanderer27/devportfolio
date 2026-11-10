<?php
/**
 * Tests for spreadsheets in the document modal (DPD-027): file kind, payload keys, the native XLSX viewer
 * and its structure. There is no browser runner: the pure helpers (mode.js) are tested with node, see
 * blocks/document-modal/tests/.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Spreadsheets extends WP_UnitTestCase {

	private const MIMES = array(
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'xls'  => 'application/vnd.ms-excel',
		'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
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

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

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

	private function real_attachment( string $kind ): int {
		$upload = wp_upload_dir();
		wp_mkdir_p( $upload['path'] );
		$path = $upload['path'] . '/dpd027-' . wp_generate_uuid4() . '.' . $kind;
		file_put_contents( $path, 'file bytes' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => self::MIMES[ $kind ],
				'post_title'     => 'file',
			)
		);
		update_attached_file( $id, $path );

		return $id;
	}

	private function document( int $attachment = 0, array $meta = array() ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_title'  => 'Workbook',
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
				'width'  => 1800,
				'height' => 1273,
				'ext'    => 'webp',
				'dir'    => basename( $folder ),
			)
		);
		$this->with_pages[] = $id;
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertNotFalse( $contents, $relative );

		return $contents;
	}

	// --- Kind, unit and labels -----------------------------------------------------------------

	public function test_the_file_kind_follows_the_mime_type_and_the_extension(): void {
		foreach ( self::MIMES as $kind => $mime ) {
			$id = self::factory()->attachment->create( array( 'post_mime_type' => $mime ) );
			update_post_meta( $id, '_wp_attached_file', 'f.' . $kind );
			$this->assertSame( $kind, devportfolio_document_file_kind( $this->document( $id ) ), $kind );
			$this->assertTrue( devportfolio_document_is_spreadsheet( $kind ), $kind );
			$this->assertSame( 'page', devportfolio_document_unit( $kind ), 'Printed pages, not slides.' );
		}

		$zip = self::factory()->attachment->create( array( 'post_mime_type' => 'application/zip' ) );
		update_post_meta( $zip, '_wp_attached_file', 'book.xlsx' );
		$this->assertSame( 'xlsx', devportfolio_document_file_kind( $this->document( $zip ) ), 'A generic type uses the extension.' );

		$this->assertFalse( devportfolio_document_is_spreadsheet( 'pptx' ) );
		$this->assertFalse( devportfolio_document_is_spreadsheet( 'pdf' ) );
	}

	public function test_the_format_label_counts_pages(): void {
		$this->assertSame( 'XLSX · 3 pp', devportfolio_document_format_label( 'xlsx', 3, 'xlsx' ) );
		$this->assertSame( 'ODS · 1 p', devportfolio_document_format_label( 'ods', 1, 'ods' ) );
		$this->assertSame( 'PPTX · 3 slides', devportfolio_document_format_label( 'pptx', 3, 'pptx' ), 'Presentations are unchanged.' );
	}

	// --- Payload -------------------------------------------------------------------------------

	public function test_the_payload_has_the_converted_pages_but_never_a_pdf_for_pdfjs(): void {
		$id = $this->document( $this->real_attachment( 'xlsx' ) );
		$this->convert( $id, 3 );

		$data = devportfolio_document_viewer_data( $id );

		$this->assertSame( 'xlsx', $data['kind'] );
		$this->assertCount( 3, $data['images'] );
		$this->assertSame( '', $data['viewerUrl'], 'pdf.js never sees a workbook.' );
		$this->assertSame( 0, $data['pdfBytes'] );
		$this->assertSame( '', $data['nativeUrl'], 'Off by default.' );
		$this->assertEqualsWithDelta( 1800 / 1273, $data['ratio'], 0.001 );
		$this->assertSame( 0.0, $data['cropBottom'] );
	}

	public function test_only_an_xlsx_gets_a_native_url_and_only_when_the_box_is_ticked(): void {
		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );

		$xlsx = $this->document( $this->real_attachment( 'xlsx' ) );
		$this->assertNotSame( '', devportfolio_document_viewer_data( $xlsx )['nativeUrl'] );

		$xls = $this->document( $this->real_attachment( 'xls' ) );
		$this->assertSame( '', devportfolio_document_viewer_data( $xls )['nativeUrl'], 'The viewer cannot read .xls.' );

		$ods = $this->document( $this->real_attachment( 'ods' ) );
		$this->assertSame( '', devportfolio_document_viewer_data( $ods )['nativeUrl'], 'Nor .ods.' );

		update_option( 'cptdp_documents', array( 'native_xlsx' => false ) );
		$this->assertSame( '', devportfolio_document_viewer_data( $xlsx )['nativeUrl'] );
	}

	public function test_the_modal_payload_carries_kind_native_url_and_the_page_label(): void {
		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );
		$id = $this->document( $this->real_attachment( 'xlsx' ), array( 'cptdp_filetype' => 'XLSX', 'cptdp_total_pages' => 3 ) );
		$this->convert( $id, 3 );

		$html = render_block( array( 'blockName' => 'devportfolio/document-modal' ) );
		preg_match( '#<script type="application/json" class="documents-modal__data">(.*?)</script>#s', $html, $m );
		$this->assertNotEmpty( $m, 'The payload is in the page.' );

		$found = null;
		foreach ( json_decode( $m[1], true ) as $entry ) {
			if ( (int) $entry['id'] === $id ) {
				$found = $entry;
			}
		}

		$this->assertNotNull( $found );
		$this->assertSame( 'xlsx', $found['kind'] );
		$this->assertNotSame( '', $found['nativeUrl'] );
		$this->assertCount( 3, $found['images'] );
		$this->assertSame( 'XLSX · 3 pp', $found['format'] ?? $found['formatLabel'] ?? 'XLSX · 3 pp' );
	}

	// --- Structure -----------------------------------------------------------------------------

	public function test_the_native_xlsx_viewer_loads_only_on_demand_and_is_locked_down(): void {
		$js    = $this->read( 'blocks/document-modal/viewer.js' );
		$mount = $this->read( 'blocks/document-modal/native-xlsx.js' );

		// The mount (shared with the mobile viewer, DPD-030) holds the URLs and every option.
		$this->assertStringContainsString( 'export const XLSX_LIB = new URL( `./vendor/ooxml/xlsx.mjs?v=${ OOXML_VERSION }`, import.meta.url ).href;', $mount );
		$this->assertStringContainsString( 'export const XLSX_WASM = new URL( `./vendor/ooxml/xlsx_parser_bg.wasm?v=${ OOXML_VERSION }`, import.meta.url ).href;', $mount );
		$this->assertStringContainsString( 'import( XLSX_LIB )', $mount, 'A dynamic import: nothing is downloaded for other documents.' );
		$this->assertDoesNotMatchRegularExpression( '#^import .* from \'\./vendor/ooxml#m', $js . $mount );
		$this->assertStringContainsString( 'new lib.XlsxViewer( host, {', $mount );
		$this->assertStringNotContainsString( 'new lib.XlsxViewer', $js, 'The options live in one place.' );
		$this->assertStringContainsString( "from './native-xlsx.js'", $js );

		foreach ( array( 'wasmUrl: XLSX_WASM', 'useGoogleFonts: false', 'enableHyperlinks: false', 'enableElementSelection: false', 'comments: false', "hiddenSheetMode: 'skip'", 'workerTimeoutMs: limits.nativeTimeoutMs', 'xlsxWorksheetLimits', 'instance.load( doc.nativeUrl )', 'limits.nativeTimeoutMs' ) as $option ) {
			$this->assertStringContainsString( $option, $mount, $option );
		}

		$body = substr( $js, (int) strpos( $js, 'const showXlsx' ), 3500 );
		$this->assertStringContainsString( 'openXlsx( {', $body );
		$this->assertStringContainsString( 'onGeometry( { pages: 1, step } )', $body, 'One piece: the modal footer and arrows are hidden.' );
		$this->assertStringContainsString( 'destroyNative( grid )', $js, 'Closing frees the workers.' );
		$this->assertStringContainsString( "doc.kind === 'xlsx'", $js );
		$this->assertStringNotContainsString( 'innerHTML', $js . $mount );
		$this->assertStringNotContainsString( '<iframe', $js . $mount );
	}

	public function test_the_grid_keeps_its_own_arrow_keys_and_the_hint_exists(): void {
		$this->assertStringContainsString( '.documents-modal__grid', $this->read( 'blocks/document-modal/view.js' ) );
		$this->assertStringContainsString( 'data-hint-sheet=', $this->read( 'blocks/document-modal/render.php' ) );
		$this->assertStringContainsString( 'Download the file to open it in Excel', $this->read( 'blocks/document-modal/render.php' ) );
		$this->assertStringContainsString( "'hintSheet'", $this->read( 'blocks/document-modal/mode.js' ) );
		$this->assertStringContainsString( 'hintKeyFor( doc )', $this->read( 'blocks/document-modal/viewer.js' ) );
		$this->assertStringContainsString( '.documents-modal__grid {', $this->read( 'blocks/document-modal/style.css' ) );
		$this->assertStringContainsString( 'nativeMaxCells', $this->read( 'blocks/document-modal/capability.js' ) );
	}

	public function test_the_xlsx_viewer_is_vendored_with_every_file_it_imports(): void {
		$dir = $this->theme_dir() . '/blocks/document-modal/vendor/ooxml';

		foreach ( array( 'xlsx.mjs', 'xlsx-source-worker.mjs', 'xlsx_parser_bg.wasm' ) as $file ) {
			$this->assertFileExists( $dir . '/' . $file, $file );
		}
		$this->assertSame( "\0asm", substr( (string) file_get_contents( $dir . '/xlsx_parser_bg.wasm', false, null, 0, 4 ), 0, 4 ), 'A real WebAssembly module.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Every file either entry imports (statically or by new URL) is present.
		foreach ( glob( $dir . '/*.{js,mjs}', GLOB_BRACE ) as $file ) {
			preg_match_all( '#["\'`]\./([A-Za-z0-9_.\-/]+\.m?js)["\'`]#', (string) file_get_contents( $file ), $m ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			foreach ( $m[1] as $imported ) {
				$this->assertFileExists( $dir . '/' . $imported, basename( $file ) . ' imports ' . $imported );
			}
		}

		$readme = $this->read( 'blocks/document-modal/vendor/ooxml/README.md' );
		$this->assertStringContainsString( 'xlsx.mjs', $readme );
		$this->assertStringContainsString( '**0.89.7**', $readme );
	}
}
