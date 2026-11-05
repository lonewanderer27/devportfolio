<?php
/**
 * Spreadsheet (XLSX, XLS, ODS) source kinds, validation, conversion, settings and warning (DPB-027).
 *
 * Conversion tests skip when LibreOffice Calc is not installed.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group spreadsheets
 */
class CPTDP_Test_Spreadsheets extends CPTDP_Pages_Test_Case {

	/**
	 * Temporary files written by write(), removed in tear_down().
	 *
	 * @var string[]
	 */
	private array $written = array();

	public function tear_down(): void {
		foreach ( $this->written as $file ) {
			wp_delete_file( $file );
		}
		$this->written = array();

		parent::tear_down();
	}

	private function write( string $bytes, string $kind ): string {
		$base = wp_tempnam( 'cptdp-test-' );
		$file = $base . '.' . $kind;
		rename( $base, $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		file_put_contents( $file, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$this->written[] = $file;

		return $file;
	}

	/**
	 * @param array<string,string> $extra Extra or replaced entries.
	 * @return array<string,string>
	 */
	private function xlsx_entries( array $extra = array() ): array {
		return array_merge(
			array(
				'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>',
				'xl/workbook.xml'     => '<workbook xmlns="x"/>',
			),
			$extra
		);
	}

	/**
	 * @param array<string,string> $extra Extra or replaced entries.
	 * @return array<string,string>
	 */
	private function ods_entries( array $extra = array() ): array {
		return array_merge(
			array(
				'mimetype'    => 'application/vnd.oasis.opendocument.spreadsheet',
				'content.xml' => '<office:document-content xmlns:table="t" xmlns:xlink="x"/>',
			),
			$extra
		);
	}

	private function assertRefused( string $code, string $bytes, string $kind ): void {
		$result = CPTDP_Xlsx_Converter::inspect( $this->write( $bytes, $kind ), $kind );

		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertStringContainsString( 'PDF', $result->get_error_message(), 'Every refusal recommends a PDF.' );
	}

	// --- Kinds ---------------------------------------------------------------------------------

	public function test_spreadsheet_kinds_follow_the_mime_type_and_the_extension(): void {
		foreach ( array( 'xlsx', 'xls', 'ods' ) as $kind ) {
			$id = $this->make_document( $this->make_spreadsheet_attachment( $kind ) );

			$this->assertSame( $kind, CPTDP_Page_Store::source_kind( $id ), $kind );
			$this->assertSame( $kind, CPTDP_Page_Store::get_source( $id )['kind'], $kind );
			$this->assertTrue( CPTDP_Page_Store::is_spreadsheet_kind( $kind ) );
			$this->assertTrue( CPTDP_Page_Store::is_office_kind( $kind ) );
			$this->assertFalse( CPTDP_Page_Store::is_presentation_kind( $kind ) );
			$this->assertSame( '', cptdp_document_pdf_url( $id ), 'Never handed to pdf.js.' );
		}

		$this->assertFalse( CPTDP_Page_Store::is_spreadsheet_kind( 'pptx' ) );
		$this->assertTrue( CPTDP_Page_Store::is_office_kind( 'pptx' ) );
		$this->assertFalse( CPTDP_Page_Store::is_office_kind( 'pdf' ) );
	}

	public function test_a_generic_mime_type_falls_back_to_the_extension(): void {
		$id = $this->make_spreadsheet_attachment( 'xlsx' );
		wp_update_post(
			array(
				'ID'             => $id,
				'post_mime_type' => 'application/zip',
			)
		);

		$this->assertSame( 'xlsx', CPTDP_Page_Store::kind_of_attachment( $id ) );
	}

	// --- Validation ----------------------------------------------------------------------------

	public function test_the_committed_fixtures_are_valid(): void {
		foreach ( array( 'xlsx', 'xls', 'ods' ) as $kind ) {
			$this->assertTrue( CPTDP_Xlsx_Converter::inspect( __DIR__ . '/../fixtures/sample.' . $kind, $kind ), $kind );
			$this->assertTrue( CPTDP_Pptx_Converter::inspect( __DIR__ . '/../fixtures/sample.' . $kind, $kind ), $kind . ' through the shared entry point.' );
		}
	}

	public function test_files_that_are_not_what_their_kind_says_are_refused(): void {
		$this->assertRefused( 'cptdp_xlsx_invalid', 'just some text', 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_invalid', 'just some text', 'ods' );
		$this->assertRefused( 'cptdp_xlsx_invalid', 'just some text', 'xls' );
		$this->assertRefused( 'cptdp_xlsx_invalid', $this->zip_bytes( array( 'hello.txt' => 'x' ) ), 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_invalid', $this->zip_bytes( array( '[Content_Types].xml' => '<Types/>' ) ), 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_invalid', $this->zip_bytes( array( 'content.xml' => '<x/>', 'mimetype' => 'application/vnd.oasis.opendocument.spreadsheet' ) ), 'ods' );
		$this->assertRefused( 'cptdp_xlsx_invalid', $this->zip_bytes( $this->ods_entries( array( 'mimetype' => 'application/vnd.oasis.opendocument.text' ) ) ), 'ods' );
	}

	public function test_macros_are_refused(): void {
		$this->assertRefused( 'cptdp_xlsx_macros', $this->zip_bytes( $this->xlsx_entries( array( 'xl/vbaProject.bin' => 'x' ) ) ), 'xlsx' );
		$this->assertRefused(
			'cptdp_xlsx_macros',
			$this->zip_bytes( $this->xlsx_entries( array( '[Content_Types].xml' => '<Types><Override ContentType="application/vnd.ms-excel.sheet.macroEnabled.main+xml"/></Types>' ) ) ),
			'xlsx'
		);
		$this->assertRefused( 'cptdp_xlsx_macros', $this->zip_bytes( $this->ods_entries( array( 'Basic/script.xml' => 'x' ) ) ), 'ods' );
		$this->assertRefused( 'cptdp_xlsx_macros', $this->zip_bytes( $this->ods_entries( array( 'Scripts/python/a.py' => 'x' ) ) ), 'ods' );
		$this->assertRefused( 'cptdp_xlsx_macros', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( 'x', 100 ) . "_\0V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T\0", 'xls' );
	}

	public function test_external_data_is_refused_but_ordinary_links_are_not(): void {
		$rel = static function ( string $type, string $mode = 'External' ): string {
			return '<Relationships><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $type . '" Target="http://example.test/a" TargetMode="' . $mode . '"/></Relationships>';
		};

		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->xlsx_entries( array( 'xl/externalLinks/externalLink1.xml' => '<x/>' ) ) ), 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->xlsx_entries( array( 'xl/connections.xml' => '<x/>' ) ) ), 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->xlsx_entries( array( 'xl/queryTables/queryTable1.xml' => '<x/>' ) ) ), 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->xlsx_entries( array( 'xl/worksheets/_rels/sheet1.xml.rels' => $rel( 'oleObject' ) ) ) ), 'xlsx' );
		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->ods_entries( array( 'content.xml' => '<draw:object xlink:href="http://example.test/x"/>' ) ) ), 'ods' );
		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->ods_entries( array( 'content.xml' => '<table:table table:name="a" xlink:href="file:///etc/passwd"/>' ) ) ), 'ods' );
		$this->assertRefused( 'cptdp_xlsx_external', $this->zip_bytes( $this->ods_entries( array( 'content.xml' => '<table:cell-range-source xlink:href="../other.ods"/>' ) ) ), 'ods' );

		$link = $this->write( $this->zip_bytes( $this->xlsx_entries( array( 'xl/worksheets/_rels/sheet1.xml.rels' => $rel( 'hyperlink' ) ) ) ), 'xlsx' );
		$this->assertTrue( CPTDP_Xlsx_Converter::inspect( $link, 'xlsx' ), 'A hyperlink is only text.' );

		$inside = $this->write( $this->zip_bytes( $this->ods_entries( array( 'content.xml' => '<draw:object xlink:href="./Object 1"/>' ) ) ), 'ods' );
		$this->assertTrue( CPTDP_Xlsx_Converter::inspect( $inside, 'ods' ), 'An object inside the package is fine.' );
	}

	public function test_a_zip_bomb_and_an_oversized_file_are_refused(): void {
		// A small file that expands about a thousand times.
		$archive = wp_tempnam( 'cptdp-bomb' );
		$zip     = new ZipArchive();
		$zip->open( $archive, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $this->xlsx_entries() as $name => $content ) {
			$zip->addFromString( $name, $content );
		}
		$zip->addFromString( 'xl/media/big.bin', str_repeat( "\0", 3 * MB_IN_BYTES ) );
		$zip->close();
		$file = $this->write( (string) file_get_contents( $archive ), 'xlsx' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $archive );
		$this->assertSame( 'cptdp_pptx_bomb', CPTDP_Xlsx_Converter::inspect( $file, 'xlsx' )->get_error_code() );

		add_filter(
			'cptdp_xlsx_max_bytes',
			static function (): int {
				return 1024;
			}
		);
		$result = CPTDP_Xlsx_Converter::inspect( __DIR__ . '/../fixtures/sample.xlsx', 'xlsx' );
		$this->assertSame( 'cptdp_xlsx_too_large', $result->get_error_code() );
	}

	// --- Calc settings -------------------------------------------------------------------------

	public function test_the_export_filter_gives_one_page_per_sheet_unless_told_otherwise(): void {
		$this->assertStringContainsString( 'calc_pdf_Export', CPTDP_Xlsx_Converter::export_filter() );
		$this->assertStringContainsString( '"SinglePageSheets":{"type":"boolean","value":"true"}', CPTDP_Xlsx_Converter::export_filter() );

		add_filter( 'cptdp_xlsx_single_page_sheets', '__return_false' );
		$this->assertSame( 'pdf:calc_pdf_Export', CPTDP_Xlsx_Converter::export_filter() );
	}

	public function test_the_profile_turns_off_link_updates_recalculation_and_macros(): void {
		$dir = trailingslashit( get_temp_dir() ) . 'cptdp-pptx-test-' . wp_generate_password( 8, false );
		CPTDP_Xlsx_Converter::write_profile( $dir . '/profile' );
		$xml = (string) file_get_contents( $dir . '/profile/user/registrymodifications.xcu' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertNotFalse( simplexml_load_string( $xml ), 'Valid XML.' );
		$this->assertMatchesRegularExpression( '#Content/Update"><prop oor:name="Link" oor:op="fuse"><value>2</value>#', $xml, 'Never update links.' );
		$this->assertStringContainsString( 'OOXMLRecalcMode', $xml );
		$this->assertStringContainsString( 'DisableMacrosExecution', $xml );

		CPTDP_Pptx_Converter::cleanup( $dir );
		wp_delete_file( $dir . '/profile/user/registrymodifications.xcu' );
	}

	public function test_availability_needs_calc_and_has_its_own_reason(): void {
		add_filter( 'cptdp_pptx_soffice_path', '__return_empty_array' );
		$this->assertFalse( CPTDP_Pptx_Converter::is_available( 'xlsx' ) );
		$this->assertStringContainsString( 'Excel', CPTDP_Pptx_Converter::unavailable_reason( 'xlsx' ) );
		$this->assertStringContainsString( 'PDF in Excel', CPTDP_Pptx_Converter::unavailable_reason( 'xls' ) );
		$this->assertStringContainsString( 'PowerPoint', CPTDP_Pptx_Converter::unavailable_reason( 'pptx' ), 'Presentations keep their wording.' );

		remove_filter( 'cptdp_pptx_soffice_path', '__return_empty_array' );
		add_filter( 'cptdp_xlsx_available', '__return_false' );
		$this->assertFalse( CPTDP_Pptx_Converter::is_available( 'ods' ) );
	}

	// --- Conversion ----------------------------------------------------------------------------

	public function test_each_sheet_becomes_one_page_of_the_same_shape(): void {
		$this->require_libreoffice();

		if ( ! CPTDP_Pptx_Converter::is_available( 'xlsx' ) ) {
			$this->markTestSkipped( 'LibreOffice Calc is not installed.' );
		}

		foreach ( array( 'xlsx', 'xls', 'ods' ) as $kind ) {
			$id     = $this->make_document( $this->make_spreadsheet_attachment( $kind ) );
			$result = CPTDP_Page_Converter::convert( $id );

			$this->assertNotWPError( $result, $kind );
			$this->assertSame( 3, $result['count'], $kind . ': three sheets, three pages.' );
			$this->assertSame( CPTDP_Page_Converter::SHEET_WIDTH, $result['width'], $kind );
			$this->assertSame( CPTDP_Page_Converter::SHEET_HEIGHT, $result['height'], $kind );
			$this->assertSame( 0.0, (float) $result['crop_bottom'], 'Never trimmed.' );

			foreach ( range( 1, 3 ) as $n ) {
				$size = getimagesize( CPTDP_Page_Store::folder_for( $id ) . '/' . CPTDP_Page_Store::page_filename( $n, $result['ext'] ) );
				$this->assertSame( array( CPTDP_Page_Converter::SHEET_WIDTH, CPTDP_Page_Converter::SHEET_HEIGHT ), array( $size[0], $size[1] ), $kind . ' page ' . $n );
			}
		}

		$this->assertSame( array(), glob( trailingslashit( get_temp_dir() ) . 'cptdp-pptx-*' ) ?: array(), 'No temporary folder left behind.' );
	}

	public function test_a_converted_spreadsheet_is_ready_marked_and_never_cropped(): void {
		$this->require_libreoffice();

		if ( ! CPTDP_Pptx_Converter::is_available( 'xlsx' ) ) {
			$this->markTestSkipped( 'LibreOffice Calc is not installed.' );
		}

		$id = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ) );
		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::get_status( $id ) );
		$manifest = CPTDP_Page_Store::get_manifest( $id );
		$this->assertSame( 'xlsx', $manifest['source_kind'] );
		$this->assertSame( 'libreoffice', $manifest['converted_via'] );

		$pages = cptdp_get_document_pages( $id );
		$this->assertCount( 3, $pages['pages'] );
		$this->assertSame( 0.0, (float) $pages['crop_bottom'] );
		$this->assertEqualsWithDelta( 1800 / 1273, (float) $pages['ratio'], 0.001 );
	}

	public function test_a_macro_workbook_ends_failed_with_the_pdf_advice(): void {
		$this->require_libreoffice();

		$id = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx', $this->zip_bytes( $this->xlsx_entries( array( 'xl/vbaProject.bin' => 'x' ) ) ) ) );
		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ), 'Never handed to the browser.' );
		$this->assertStringContainsString( 'macros', CPTDP_Page_Store::get_error( $id ) );
	}

	// --- Scheduling without Calc ---------------------------------------------------------------

	public function test_without_calc_a_spreadsheet_fails_unless_the_native_viewer_takes_it(): void {
		add_filter( 'cptdp_xlsx_available', '__return_false' );
		add_filter( 'cptdp_pptx_available', '__return_false' );

		update_option( 'cptdp_documents', array( 'native_xlsx' => false ) );
		$xlsx = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $xlsx ) );
		$this->assertStringContainsString( 'PDF', CPTDP_Page_Store::get_error( $xlsx ) );

		$xls = $this->make_document( $this->make_spreadsheet_attachment( 'xls' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $xls ) );

		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );
		$native = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Store::get_status( $native ), 'Working as chosen: the native viewer draws it.' );
		$this->assertSame( '', CPTDP_Page_Store::get_error( $native ) );

		$xls2 = $this->make_document( $this->make_spreadsheet_attachment( 'xls' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $xls2 ), 'The native viewer cannot draw .xls.' );
	}

	public function test_the_browser_upload_routes_refuse_a_spreadsheet(): void {
		$this->acting_as( 'administrator' );
		$id = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ) );

		foreach ( array( 'start', '1', 'finish' ) as $route ) {
			$request  = new WP_REST_Request( 'POST', '/cptdp/v1/documents/' . $id . '/pages/' . $route );
			$response = rest_get_server()->dispatch( $request );
			$this->assertSame( 400, $response->get_status(), $route );
			$this->assertSame( 'cptdp_pptx_server_only', $response->get_data()['code'], $route );
		}
	}

	// --- Labels, warning, settings -------------------------------------------------------------

	public function test_the_status_label_says_pages_converted_from_excel(): void {
		$this->assertSame( 'Ready, 3 pages (converted from Excel)', cptdp_document_pages_status_label( 'ready', 3, 'xlsx' ) );
		$this->assertSame( 'Ready, 1 page (converted from Excel)', cptdp_document_pages_status_label( 'ready', 1, 'xls' ) );
		$this->assertSame( 'Ready, 2 pages (converted from OpenDocument)', cptdp_document_pages_status_label( 'ready', 2, 'ods' ) );
		$this->assertSame( 'Ready, 4 slides (converted from PowerPoint)', cptdp_document_pages_status_label( 'ready', 4, 'pptx' ) );
	}

	public function test_a_page_total_that_disagrees_names_the_spreadsheet(): void {
		$id = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ), 9 );
		$this->fake_pages( $id, 3 );
		$manifest                = CPTDP_Page_Store::get_manifest( $id );
		$manifest['source_kind'] = 'xlsx';
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );
		CPTDP_Page_Store::reconcile( $id );

		$this->assertStringContainsString( 'The spreadsheet has 3 pages, Total pages says 9.', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_the_warning_recommends_a_pdf_and_links_only_for_xlsx_and_administrators(): void {
		$this->acting_as( 'administrator' );

		$xlsx = cptdp_xlsx_warning( 'xlsx' );
		$this->assertStringContainsString( 'Excel files are converted automatically and the result may be imperfect', $xlsx );
		$this->assertStringContainsString( 'File → Save As → PDF', $xlsx );
		$this->assertStringContainsString( 'Everything on the visible sheets is published.', $xlsx );
		$this->assertStringContainsString( 'cptdp-native-viewer', $xlsx );
		$this->assertStringContainsString( 'hidden sheets', $xlsx );
		$this->assertStringContainsString( 'rel="noopener"', $xlsx );
		$this->assertStringContainsString( 'notice-warning', $xlsx );

		$this->assertStringNotContainsString( 'cptdp-native-viewer', cptdp_xlsx_warning( 'xls' ) );
		$this->assertStringNotContainsString( 'cptdp-native-viewer', cptdp_xlsx_warning( 'ods' ) );
		$this->assertStringContainsString( 'LibreOffice (File → Export as PDF)', cptdp_xlsx_warning( 'ods' ) );
		$this->assertSame( '', cptdp_xlsx_warning( 'pptx' ) );
		$this->assertSame( '', cptdp_xlsx_warning( 'pdf' ) );

		$this->acting_as( 'editor' );
		$this->assertStringNotContainsString( 'cptdp-native-viewer', cptdp_xlsx_warning( 'xlsx' ), 'Editors cannot open the settings.' );

		$this->acting_as( 'administrator' );
		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );
		$this->assertStringContainsString( 'Change this setting', cptdp_xlsx_warning( 'xlsx' ) );
	}

	public function test_the_picker_gets_a_warning_for_every_office_kind(): void {
		$all = cptdp_office_warnings();

		foreach ( array( 'pptx', 'ppt', 'odp', 'xlsx', 'xls', 'ods' ) as $kind ) {
			$this->assertNotSame( '', $all[ $kind ], $kind );
			$this->assertSame( cptdp_office_warning( $kind ), $all[ $kind ], $kind );
		}
	}

	public function test_the_native_url_is_empty_unless_every_rule_holds(): void {
		$xlsx = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ) );

		update_option( 'cptdp_documents', array( 'native_xlsx' => false ) );
		$this->assertSame( '', cptdp_document_native_xlsx_url( $xlsx ), 'Off by default.' );

		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );
		$this->assertSame( wp_get_attachment_url( get_post_meta( $xlsx, 'cptdp_document_file', true ) ), cptdp_document_native_xlsx_url( $xlsx ) );

		$this->assertSame( '', cptdp_document_native_xlsx_url( $this->make_document( $this->make_spreadsheet_attachment( 'xls' ) ) ), 'The viewer cannot read .xls.' );
		$this->assertSame( '', cptdp_document_native_xlsx_url( $this->make_document( $this->make_spreadsheet_attachment( 'ods' ) ) ), 'Nor .ods.' );
		$this->assertSame( '', cptdp_document_native_xlsx_url( $this->make_document( $this->make_presentation_attachment( 'pptx' ) ) ), 'PowerPoint has its own checkbox.' );
		$this->assertSame( '', cptdp_document_native_xlsx_url( $this->make_document() ), 'No file.' );

		update_option( 'cptdp_documents', array( 'native_pptx' => true ) );
		$this->assertSame( '', cptdp_document_native_xlsx_url( $xlsx ), 'Each format is its own switch.' );
	}

	public function test_a_workbook_over_the_native_size_limit_is_not_offered(): void {
		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );
		$id = $this->make_document( $this->make_spreadsheet_attachment( 'xlsx' ) );
		$this->assertNotSame( '', cptdp_document_native_xlsx_url( $id ) );

		add_filter(
			'cptdp_native_xlsx_max_bytes',
			static function (): int {
				return 10;
			}
		);
		$this->assertSame( '', cptdp_document_native_xlsx_url( $id ) );
	}

	public function test_the_settings_page_has_an_excel_checkbox_and_a_status_row(): void {
		ob_start();
		cptdp_documents_render_native_formats();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="cptdp_documents[native_xlsx]"', $html );
		$this->assertStringContainsString( 'Excel (.xlsx)', $html );
		$this->assertStringContainsString( 'hidden sheets', $html );

		ob_start();
		cptdp_documents_render_conversion_status( array( 'family' => 'spreadsheets' ) );
		$this->assertStringContainsString( 'One page per sheet', (string) ob_get_clean() );

		$this->assertTrue( cptdp_documents_sanitize( array( 'native_xlsx' => '1' ) )['native_xlsx'] );

		update_option( 'cptdp_documents', array( 'native_xlsx' => true ) );
		$this->assertTrue( cptdp_documents_sanitize( array( 'native_pdf' => '1' ) )['native_xlsx'], 'A partial update keeps the rest.' );
	}

	public function test_ddev_installs_libreoffice_calc(): void {
		$config = dirname( CPTDP_PATH, 3 ) . '/.ddev/config.yaml';

		if ( ! is_readable( $config ) ) {
			$this->markTestSkipped( 'No .ddev/config.yaml next to the plugin.' );
		}

		$this->assertStringContainsString( '- libreoffice-calc', (string) file_get_contents( $config ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
