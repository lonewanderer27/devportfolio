<?php
/**
 * Page names: PDF outline reader, Excel sheet names, storage and the public API (DPB-030).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group page-names
 */
class CPTDP_Test_Page_Names extends CPTDP_Pages_Test_Case {

	/**
	 * Temporary files, removed in tear_down().
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

	private function write( string $bytes, string $ext = 'pdf' ): string {
		$base = wp_tempnam( 'cptdp-names-' );
		$file = $base . '.' . $ext;
		rename( $base, $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		file_put_contents( $file, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$this->written[] = $file;

		return $file;
	}

	/**
	 * A PDF whose pages have an outline.
	 *
	 * @param int                                         $pages Page count.
	 * @param array<int,array{0:string,1:int|string,2?:array}> $items [ title, page (1-based) or a destination name, children ].
	 * @param array<string,mixed>                         $opts  `named` (name => page), `utf16` (encode titles), `encrypt`, `objstm` (put the outline in an object stream).
	 */
	private function outline_pdf( int $pages, array $items, array $opts = array() ): string {
		$objects = array();
		$kids    = array();

		for ( $i = 0; $i < $pages; $i++ ) {
			$objects[ 10 + $i ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << >> >>';
			$kids[]             = ( 10 + $i ) . ' 0 R';
		}

		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $pages . ' >>';

		$next    = 100;
		$outline = array();
		$build   = function ( array $list, int $parent ) use ( &$build, &$next, &$outline, $opts ): array {
			$numbers = array();

			foreach ( $list as $item ) {
				$numbers[] = $next++;
			}

			foreach ( $list as $i => $item ) {
				$n    = $numbers[ $i ];
				$dest = is_int( $item[1] )
					? '/Dest [' . ( 9 + $item[1] ) . ' 0 R /Fit]'
					: '/Dest (' . $item[1] . ')';
				$text = ! empty( $opts['utf16'] ) ? '<' . bin2hex( "\xFE\xFF" . mb_convert_encoding( $item[0], 'UTF-16BE', 'UTF-8' ) ) . '>' : '(' . str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $item[0] ) . ')';
				$body = "<< /Title {$text} /Parent {$parent} 0 R {$dest}";

				if ( $i > 0 ) {
					$body .= ' /Prev ' . $numbers[ $i - 1 ] . ' 0 R';
				}

				if ( $i < count( $list ) - 1 ) {
					$body .= ' /Next ' . $numbers[ $i + 1 ] . ' 0 R';
				}

				if ( ! empty( $item[2] ) ) {
					$children = $build( $item[2], $n );
					$body    .= ' /First ' . $children[0] . ' 0 R /Last ' . end( $children ) . ' 0 R /Count ' . count( $children );
				}

				$outline[ $n ] = $body . ' >>';
			}

			return $numbers;
		};

		$top = $items ? $build( $items, 3 ) : array();

		if ( $items ) {
			$outline[3] = '<< /Type /Outlines /First ' . $top[0] . ' 0 R /Last ' . end( $top ) . ' 0 R /Count ' . count( $top ) . ' >>';
		}

		$catalog = '<< /Type /Catalog /Pages 2 0 R' . ( $items ? ' /Outlines 3 0 R' : '' );

		if ( ! empty( $opts['named'] ) ) {
			$pairs = '';

			foreach ( $opts['named'] as $name => $page ) {
				$pairs .= '(' . $name . ') [' . ( 9 + $page ) . ' 0 R /Fit] ';
			}

			$catalog .= ' /Names << /Dests << /Names [ ' . $pairs . '] >> >>';
		}

		$objects[1] = $catalog . ' >>';

		if ( empty( $opts['objstm'] ) ) {
			$objects += $outline;
		}

		ksort( $objects );

		$pdf     = "%PDF-1.5\n";

		foreach ( $objects as $number => $body ) {
			$pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
		}

		if ( ! empty( $opts['objstm'] ) ) {
			ksort( $outline );
			$header = '';
			$body   = '';

			foreach ( $outline as $number => $value ) {
				$header .= $number . ' ' . strlen( $body ) . ' ';
				$body   .= $value . "\n";
			}

			$stream = gzcompress( $header . $body );
			$pdf   .= '200 0 obj' . "\n" . '<< /Type /ObjStm /N ' . count( $outline ) . ' /First ' . strlen( $header ) . ' /Filter /FlateDecode /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
		}

		$pdf .= "trailer\n<< /Root 1 0 R" . ( ! empty( $opts['encrypt'] ) ? ' /Encrypt 99 0 R' : '' ) . " >>\n%%EOF\n";

		return $pdf;
	}

	// --- PDF outline ---------------------------------------------------------------------------

	public function test_a_flat_outline_names_its_pages_and_a_chapter_runs_to_the_next_bookmark(): void {
		$file   = $this->write( $this->outline_pdf( 6, array( array( 'Summary', 1 ), array( 'Plan', 3 ), array( 'Wrap-up', 6 ) ) ) );
		$ranges = CPTDP_Page_Outline::ranges( $file, 6 );

		$this->assertSame( array( array( 1, 'Summary' ), array( 3, 'Plan' ), array( 6, 'Wrap-up' ) ), $ranges );
		$this->assertSame( array( 'Summary', 'Summary', 'Plan', 'Plan', 'Plan', 'Wrap-up' ), CPTDP_Page_Outline::expand( $ranges, 6 ) );
	}

	public function test_pages_before_the_first_bookmark_have_no_name(): void {
		$file   = $this->write( $this->outline_pdf( 4, array( array( 'Chapter', 3 ) ) ) );
		$ranges = CPTDP_Page_Outline::ranges( $file, 4 );

		$this->assertSame( array( '', '', 'Chapter', 'Chapter' ), CPTDP_Page_Outline::expand( $ranges, 4 ) );
	}

	public function test_the_deepest_bookmark_that_starts_on_or_before_a_page_names_it(): void {
		$items = array(
			array( 'Part 1', 1, array( array( '1.1', 1 ), array( '1.2', 3 ) ) ),
			array( 'Part 2', 5 ),
		);
		$file  = $this->write( $this->outline_pdf( 6, $items ) );

		$this->assertSame( array( '1.1', '1.1', '1.2', '1.2', 'Part 2', 'Part 2' ), CPTDP_Page_Outline::expand( CPTDP_Page_Outline::ranges( $file, 6 ), 6 ) );
	}

	public function test_named_destinations_and_utf16_titles_are_read(): void {
		$file = $this->write(
			$this->outline_pdf(
				3,
				array( array( 'Résumé ✓', 'intro' ), array( 'End', 3 ) ),
				array(
					'named' => array( 'intro' => 1 ),
					'utf16' => true,
				)
			)
		);

		$this->assertSame( array( array( 1, 'Résumé ✓' ), array( 3, 'End' ) ), CPTDP_Page_Outline::ranges( $file, 3 ) );
	}

	public function test_an_outline_inside_an_object_stream_is_read(): void {
		$file = $this->write( $this->outline_pdf( 3, array( array( 'One', 1 ), array( 'Two', 2 ) ), array( 'objstm' => true ) ) );

		$this->assertSame( array( array( 1, 'One' ), array( 2, 'Two' ) ), CPTDP_Page_Outline::ranges( $file, 3 ) );
	}

	public function test_nothing_is_read_when_there_is_nothing_to_trust(): void {
		$with = $this->outline_pdf( 3, array( array( 'One', 1 ) ) );

		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( $this->outline_pdf( 3, array() ) ), 3 ), 'No outline.' );
		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( $with ), 5 ), 'The page tree disagrees with the converter.' );
		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( $this->outline_pdf( 3, array( array( 'One', 1 ) ), array( 'encrypt' => true ) ) ), 3 ), 'Encrypted.' );
		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( 'not a pdf at all' ), 1 ) );
		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( substr( $with, 0, 160 ) ), 3 ), 'Truncated.' );
		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Outlines [[[[\n" ), 1 ), 'Malformed.' );
		$this->assertSame( array(), CPTDP_Page_Outline::ranges( '/nonexistent/file.pdf', 3 ) );
	}

	public function test_a_looping_outline_ends(): void {
		$pdf = str_replace( '/Next 101 0 R', '/Next 100 0 R', $this->outline_pdf( 3, array( array( 'A', 1 ), array( 'B', 2 ) ) ) );

		// A's next points back at A: the reader stops there, with what it had.
		$this->assertSame( array( array( 1, 'A' ) ), CPTDP_Page_Outline::ranges( $this->write( $pdf ), 3 ) );
	}

	public function test_the_file_size_cap_is_respected(): void {
		add_filter(
			'cptdp_outline_max_bytes',
			static function (): int {
				return 50;
			}
		);

		$this->assertSame( array(), CPTDP_Page_Outline::ranges( $this->write( $this->outline_pdf( 3, array( array( 'One', 1 ) ) ) ), 3 ) );
	}

	public function test_ranges_are_cleaned_clamped_and_one_per_page(): void {
		$clean = CPTDP_Page_Outline::sanitize_ranges(
			array(
				array( 3, '  <b>Late</b>  ' ),
				array( 1, "First\n\ttitle" ),
				array( 3, 'Later wins' ),
				array( 9, 'Past the end' ),
				array( 0, 'Before the start' ),
				array( 2, '' ),
				'junk',
				array( 'x' ),
			),
			4
		);

		$this->assertSame( array( array( 1, 'First title' ), array( 3, 'Later wins' ) ), $clean );
		$this->assertSame( 200, mb_strlen( CPTDP_Page_Outline::clean_title( str_repeat( 'é', 500 ) ) ) );
		$this->assertSame( array(), CPTDP_Page_Outline::expand( array(), 3 ) );
		$this->assertSame( array(), CPTDP_Page_Outline::sanitize_ranges( 'nope', 3 ) );
	}

	// --- Excel sheet names ---------------------------------------------------------------------

	public function test_the_sample_workbooks_give_their_sheet_names(): void {
		$expected = array( 'Summary', 'Monthly Budget', 'Headcount' );

		$this->assertSame( $expected, CPTDP_Xlsx_Converter::sheet_names( __DIR__ . '/../fixtures/sample.xlsx', 'xlsx' ) );
		$this->assertSame( $expected, CPTDP_Xlsx_Converter::sheet_names( __DIR__ . '/../fixtures/sample.ods', 'ods' ) );
		$this->assertSame( array(), CPTDP_Xlsx_Converter::sheet_names( __DIR__ . '/../fixtures/sample.xls', 'xls' ), 'The binary format has no cheap reader.' );
		$this->assertSame( array(), CPTDP_Xlsx_Converter::sheet_names( '/nonexistent.xlsx', 'xlsx' ) );
	}

	public function test_hidden_sheets_are_skipped(): void {
		$xlsx = $this->write(
			$this->zip_bytes(
				array(
					'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Shown" sheetId="1"/><sheet name="Hidden" sheetId="2" state="hidden"/><sheet name="Secret" sheetId="3" state="veryHidden"/><sheet name="Last" sheetId="4" state="visible"/></sheets></workbook>',
				)
			),
			'xlsx'
		);
		$ods  = $this->write(
			$this->zip_bytes(
				array(
					'mimetype'    => 'application/vnd.oasis.opendocument.spreadsheet',
					'content.xml' => '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:spreadsheet><table:table table:name="Shown"><table:table-row><table:table-cell/></table:table-row></table:table><table:table table:name="Hidden" table:display="false"/><table:table table:name="Last"/></office:spreadsheet></office:body></office:document-content>',
				)
			),
			'ods'
		);

		$this->assertSame( array( 'Shown', 'Last' ), CPTDP_Xlsx_Converter::sheet_names( $xlsx, 'xlsx' ) );
		$this->assertSame( array( 'Shown', 'Last' ), CPTDP_Xlsx_Converter::sheet_names( $ods, 'ods' ) );
	}

	// --- The whole flow ------------------------------------------------------------------------

	public function test_a_converted_pdf_stores_its_names_and_the_api_returns_them(): void {
		$this->require_server_tooling();
		$bytes = $this->outline_pdf( 4, array( array( 'Intro', 1 ), array( 'Details', 3 ) ) );
		$id    = $this->make_document( $this->make_pdf_attachment( 4, 595, 842, $bytes ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		$manifest = CPTDP_Page_Store::get_manifest( $id );
		$this->assertSame( array( array( 1, 'Intro' ), array( 3, 'Details' ) ), $manifest['page_names'] );

		$pages = cptdp_get_document_pages( $id );
		$this->assertSame( array( 'Intro', 'Intro', 'Details', 'Details' ), $pages['names'] );
		$this->assertSame( array( array( 1, 'Intro' ), array( 3, 'Details' ) ), $pages['name_ranges'] );
	}

	public function test_a_pdf_without_an_outline_has_no_names_and_still_converts(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );
		$this->assertArrayNotHasKey( 'page_names', CPTDP_Page_Store::get_manifest( $id ) );
		$this->assertSame( array(), cptdp_get_document_pages( $id )['names'] );
	}

	public function test_names_are_read_again_from_the_stored_file_without_converting(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 3, 595, 842, $this->outline_pdf( 3, array( array( 'Only', 2 ) ) ) ) );
		CPTDP_Page_Scheduler::convert_now( $id );

		$manifest = CPTDP_Page_Store::get_manifest( $id );
		unset( $manifest['page_names'] );
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );
		$this->assertSame( array(), cptdp_get_document_pages( $id )['names'] );

		$this->assertSame( 'made', CPTDP_Page_Store::refresh_names( $id ) );
		$this->assertSame( array( '', 'Only', 'Only' ), cptdp_get_document_pages( $id )['names'] );
	}

	public function test_refreshing_names_says_when_it_cannot(): void {
		$this->assertSame( 'none', CPTDP_Page_Store::refresh_names( $this->make_document() ), 'No pages yet.' );

		$id = $this->make_document( $this->make_presentation_attachment() );
		$this->fake_pages( $id, 2 );
		$this->assertSame( 'unsupported', CPTDP_Page_Store::refresh_names( $id ), 'Presentations have no names.' );
	}

	public function test_a_stored_manifest_with_bad_names_is_never_trusted(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		$this->fake_pages( $id, 3 );

		$manifest               = CPTDP_Page_Store::get_manifest( $id );
		$manifest['page_names'] = array( array( 2, '<script>x</script>Two' ), array( 99, 'Nope' ), 'junk' );
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );

		$this->assertSame( array( '', 'Two', 'Two' ), cptdp_get_document_pages( $id )['names'] );
	}
}
