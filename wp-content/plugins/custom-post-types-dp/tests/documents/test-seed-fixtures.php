<?php
/**
 * Tests for committed document seed fixtures.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document
 */
class CPTDP_Test_Seed_Fixtures extends CPTDP_Test_Case {

	private function fixture( string $name ): string {
		return CPTDP_PATH . 'data/seeders/files/documents/' . $name;
	}

	public function test_fixtures_have_expected_signatures(): void {
		$files = array(
			'pdf-portrait-1p.pdf' => '%PDF-',
			'pdf-portrait-3pp.pdf' => '%PDF-',
			'pdf-portrait-4pp.pdf' => '%PDF-',
			'pdf-landscape-1p.pdf' => '%PDF-',
			'pdf-landscape-3pp.pdf' => '%PDF-',
			'pdf-portrait-24pp.pdf' => '%PDF-',
			'pdf-corrupt.pdf' => '%PDF-',
			'docx-1p.docx' => 'PK',
			'docx-12pp.docx' => 'PK',
			'pptx-1-slide.pptx' => 'PK',
			'pptx-4x3.pptx' => 'PK',
			'xlsx-hidden-sheet.xlsx' => 'PK',
		);

		foreach ( $files as $name => $signature ) {
			$this->assertFileExists( $this->fixture( $name ) );
			$this->assertSame( $signature, file_get_contents( $this->fixture( $name ), false, null, 0, strlen( $signature ) ), $name );
		}

		foreach ( array( 'sample.doc', 'sample.ppt', 'sample.xls' ) as $name ) {
			$this->assertSame( hex2bin( 'D0CF11E0A1B11AE1' ), file_get_contents( $this->fixture( $name ), false, null, 0, 8 ), $name );
		}
	}

	public function test_corrupt_fixtures_are_truncated(): void {
		$this->assertLessThan( 2048, filesize( $this->fixture( 'pdf-corrupt.pdf' ) ) );
		$this->assertStringNotContainsString( '%%EOF', file_get_contents( $this->fixture( 'pdf-corrupt.pdf' ) ) );

		foreach ( array( 'pptx-corrupt.pptx', 'xlsx-corrupt.xlsx', 'docx-corrupt.docx' ) as $name ) {
			$zip = new ZipArchive();
			$this->assertNotSame( true, $zip->open( $this->fixture( $name ) ), $name );
		}
	}

	public function test_hidden_sheet_fixture_contains_hidden_state(): void {
		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $this->fixture( 'xlsx-hidden-sheet.xlsx' ) ) );
		$workbook = $zip->getFromName( 'xl/workbook.xml' );
		$zip->close();
		$this->assertStringContainsString( 'state="hidden"', $workbook );
	}
}
