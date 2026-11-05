<?php
/**
 * Shared helpers for the document page image tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * Builds real (tiny) PDF fixtures, documents that use them, and cleans up.
 */
abstract class CPTDP_Pages_Test_Case extends CPTDP_Test_Case {

	/**
	 * Files created by the test, deleted in tear_down().
	 *
	 * @var string[]
	 */
	private array $created_files = array();

	/**
	 * Throwaway uploads folder for this test (never the real wp-content/uploads).
	 */
	private string $uploads = '';

	public function set_up(): void {
		parent::set_up();

		$this->uploads = wp_normalize_path( trailingslashit( get_temp_dir() ) . 'cptdp-uploads-' . wp_generate_uuid4() );
		wp_mkdir_p( $this->uploads );
		add_filter( 'upload_dir', array( $this, 'redirect_uploads' ) );

		// Never ping wp-cron over HTTP from tests.
		add_filter( 'cptdp_pages_spawn_cron', '__return_false' );
		delete_option( 'cptdp_documents' );
	}

	public function tear_down(): void {
		foreach ( $this->created_files as $file ) {
			wp_delete_file( $file );
		}
		$this->created_files = array();

		$this->remove_tree( $this->uploads );

		parent::tear_down();
	}

	/**
	 * `upload_dir` filter: send every upload (PDF fixtures and page images) to the temp folder.
	 *
	 * @param array<string,mixed> $dirs Upload dirs.
	 * @return array<string,mixed>
	 */
	public function redirect_uploads( array $dirs ): array {
		$dirs['basedir'] = $this->uploads;
		$dirs['baseurl'] = 'http://example.org/uploads';
		$dirs['path']    = $this->uploads . $dirs['subdir'];
		$dirs['url']     = 'http://example.org/uploads' . $dirs['subdir'];

		return $dirs;
	}

	/**
	 * Recursively delete a folder created by the test.
	 *
	 * @param string $dir Folder.
	 */
	private function remove_tree( string $dir ): void {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			is_dir( $dir . '/' . $entry ) ? $this->remove_tree( $dir . '/' . $entry ) : unlink( $dir . '/' . $entry );
		}

		rmdir( $dir );
	}

	/**
	 * Skip when the server has neither Imagick (with PDF) nor Ghostscript.
	 */
	protected function require_server_tooling(): void {
		if ( null === CPTDP_Page_Converter::engine() ) {
			$this->markTestSkipped( 'Neither Imagick with PDF support nor Ghostscript is available.' );
		}
	}

	/**
	 * Bytes of a minimal valid PDF.
	 *
	 * @param int      $pages   Number of pages.
	 * @param int      $width   Page width in points.
	 * @param int      $height  Page height in points.
	 * @param string[] $streams Content stream per page (index 0 = page 1); pages without one get a big blue block.
	 */
	protected function pdf_bytes( int $pages, int $width = 595, int $height = 842, array $streams = array() ): string {
		$objects = array(
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
		);
		$kids    = array();

		for ( $i = 0; $i < $pages; $i++ ) {
			$page_obj    = 3 + ( 2 * $i );
			$content_obj = 4 + ( 2 * $i );
			$stream      = $streams[ $i ] ?? '0.2 0.4 0.8 rg 40 40 ' . ( $width - 80 ) . ' ' . ( $height - 80 ) . ' re f';

			$kids[]                = $page_obj . ' 0 R';
			$objects[ $page_obj ]    = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$width} {$height}] /Contents {$content_obj} 0 R /Resources << >> >>";
			$objects[ $content_obj ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n{$stream}\nendstream";
		}

		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $pages . ' >>';
		ksort( $objects );

		$pdf     = "%PDF-1.4\n";
		$offsets = array();

		foreach ( $objects as $number => $body ) {
			$offsets[ $number ] = strlen( $pdf );
			$pdf               .= "{$number} 0 obj\n{$body}\nendobj\n";
		}

		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";

		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}

		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

		return $pdf;
	}

	/**
	 * Write a PDF into uploads and create an attachment for it.
	 *
	 * @param int    $pages  Number of pages.
	 * @param int    $width  Width in points.
	 * @param int    $height Height in points.
	 * @param string $bytes  Use these bytes instead of a generated PDF.
	 * @return int Attachment ID.
	 */
	protected function make_pdf_attachment( int $pages = 3, int $width = 595, int $height = 842, string $bytes = '' ): int {
		$upload = wp_upload_dir();
		wp_mkdir_p( $upload['path'] );
		$file = $upload['path'] . '/cptdp-test-' . wp_generate_uuid4() . '.pdf';

		file_put_contents( $file, '' !== $bytes ? $bytes : $this->pdf_bytes( $pages, $width, $height ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$this->created_files[] = $file;

		return (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'fixture',
				'post_status'    => 'inherit',
			),
			$file
		);
	}

	/**
	 * Skip when LibreOffice (and the PDF to image tooling) is not available.
	 */
	protected function require_libreoffice(): void {
		$this->require_server_tooling();

		if ( null === CPTDP_Pptx_Converter::binary() ) {
			$this->markTestSkipped( 'LibreOffice is not installed.' );
		}
	}

	/**
	 * Write a presentation into uploads and create an attachment for it.
	 *
	 * @param string $kind  `pptx`, `ppt` or `odp`.
	 * @param string $bytes Use these bytes instead of the committed fixture.
	 * @return int Attachment ID.
	 */
	protected function make_presentation_attachment( string $kind = 'pptx', string $bytes = '' ): int {
		$mimes = array_flip( CPTDP_Page_Store::PRESENTATION_MIMES );
		$upload = wp_upload_dir();
		wp_mkdir_p( $upload['path'] );
		$file = $upload['path'] . '/cptdp-test-' . wp_generate_uuid4() . '.' . $kind;

		file_put_contents( $file, '' !== $bytes ? $bytes : (string) file_get_contents( __DIR__ . '/fixtures/sample.' . $kind ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->created_files[] = $file;

		return (int) wp_insert_attachment(
			array(
				'post_mime_type' => $mimes[ $kind ],
				'post_title'     => 'fixture ' . $kind,
				'post_status'    => 'inherit',
			),
			$file
		);
	}

	/**
	 * Bytes of a zip with the given entries (name => content), in order, stored uncompressed.
	 *
	 * @param array<string,string> $entries Entries.
	 */
	protected function zip_bytes( array $entries ): string {
		$file = wp_tempnam( 'cptdp-zip' );
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		foreach ( $entries as $name => $content ) {
			$zip->addFromString( $name, $content );
			$zip->setCompressionName( $name, ZipArchive::CM_STORE );
		}

		$zip->close();
		$bytes = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $file );

		return $bytes;
	}

	/**
	 * Replace the bytes of an attachment's file (and make mtime differ).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $bytes         New bytes.
	 */
	protected function overwrite_attachment_file( int $attachment_id, string $bytes ): void {
		$path = (string) get_attached_file( $attachment_id );
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		clearstatcache( true, $path );
	}

	/**
	 * Create a published document, optionally with a PDF.
	 *
	 * @param int $attachment_id PDF attachment ID, 0 for none.
	 * @param int $total_pages   `cptdp_total_pages`, 0 for none.
	 * @return int Document ID.
	 */
	protected function make_document( int $attachment_id = 0, int $total_pages = 0 ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_status' => 'publish',
				'post_title'  => 'Fixture document',
			)
		);

		if ( $total_pages > 0 ) {
			update_post_meta( $id, 'cptdp_total_pages', $total_pages );
		}

		if ( $attachment_id > 0 ) {
			update_post_meta( $id, 'cptdp_document_file', $attachment_id );
		}

		return $id;
	}

	/**
	 * Write placeholder image files for a document and a matching manifest.
	 *
	 * @param int    $post_id Document ID.
	 * @param int    $count   Number of pages.
	 * @param string $ext     Image extension.
	 */
	protected function fake_pages( int $post_id, int $count, string $ext = 'webp' ): void {
		$folder = CPTDP_Page_Store::folder_for( $post_id );
		wp_mkdir_p( $folder );

		for ( $i = 1; $i <= $count; $i++ ) {
			file_put_contents( $folder . '/' . CPTDP_Page_Store::page_filename( $i, $ext ), 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}

		CPTDP_Page_Store::save_manifest(
			$post_id,
			array(
				'count'  => $count,
				'width'  => 1400,
				'height' => 1980,
				'ext'    => $ext,
				'dir'    => basename( $folder ),
			)
		);
	}

	/**
	 * A real image's bytes.
	 *
	 * @param string $format webp or jpeg.
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 */
	protected function image_bytes( string $format = 'jpeg', int $width = 40, int $height = 56 ): string {
		$image = imagecreatetruecolor( $width, $height );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 240, 240, 240 ) );
		ob_start();
		'webp' === $format ? imagewebp( $image, null, 80 ) : imagejpeg( $image, null, 80 );

		return (string) ob_get_clean();
	}
}
