<?php
/**
 * Document page image store: manifest, status, validity and cleanup.
 *
 * The page images of a document's PDF are generated when the document is saved
 * (by the server, or by the admin's browser) and stored under
 * `uploads/cptdp-pages/<attachment_id>-<hash8>/p-001.webp`. This class owns the
 * metadata that says whether those images may be used.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manifest, status and file helpers for the generated page images.
 */
class CPTDP_Page_Store {

	const META_MANIFEST = 'cptdp_document_pages';
	const META_STATUS   = 'cptdp_document_pages_status';
	const META_ERROR    = 'cptdp_document_pages_error';
	const META_SOURCE   = 'cptdp_document_pages_source';

	const STATUS_NONE          = 'none';
	const STATUS_PENDING       = 'pending';
	const STATUS_READY         = 'ready';
	const STATUS_NEEDS_BROWSER = 'needs_browser';
	const STATUS_FAILED        = 'failed';

	const MAX_PAGES = 300;

	const KIND_PDF  = 'pdf';
	const KIND_PPTX = 'pptx';
	const KIND_PPT  = 'ppt';
	const KIND_ODP  = 'odp';
	const KIND_XLSX = 'xlsx';
	const KIND_XLS  = 'xls';
	const KIND_ODS  = 'ods';
	const KIND_DOCX = 'docx';
	const KIND_DOC  = 'doc';
	const KIND_ODT  = 'odt';

	/**
	 * Mime types of the presentation files that are converted to page images (DPB-026).
	 */
	const PRESENTATION_MIMES = array(
		'application/vnd.openxmlformats-officedocument.presentationml.presentation' => self::KIND_PPTX,
		'application/vnd.ms-powerpoint'                                              => self::KIND_PPT,
		'application/vnd.oasis.opendocument.presentation'                            => self::KIND_ODP,
	);

	/**
	 * Mime types of the spreadsheet files that are converted to page images (DPB-027).
	 */
	const SPREADSHEET_MIMES = array(
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::KIND_XLSX,
		'application/vnd.ms-excel'                                           => self::KIND_XLS,
		'application/vnd.oasis.opendocument.spreadsheet'                     => self::KIND_ODS,
	);

	/**
	 * Mime types of the word-processing files that are converted to page images.
	 */
	const WORD_MIMES = array(
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::KIND_DOCX,
		'application/msword'                                                         => self::KIND_DOC,
		'application/vnd.oasis.opendocument.text'                                   => self::KIND_ODT,
	);

	/**
	 * All statuses.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_NONE,
			self::STATUS_PENDING,
			self::STATUS_READY,
			self::STATUS_NEEDS_BROWSER,
			self::STATUS_FAILED,
		);
	}

	/**
	 * Absolute path of the folder holding every generated set.
	 */
	public static function base_dir(): string {
		$upload = wp_upload_dir( null, false );

		return wp_normalize_path( trailingslashit( $upload['basedir'] ) . 'cptdp-pages' );
	}

	/**
	 * URL of the folder holding every generated set.
	 */
	public static function base_url(): string {
		$upload = wp_upload_dir( null, false );

		return trailingslashit( $upload['baseurl'] ) . 'cptdp-pages';
	}

	/**
	 * Attachment ID of a document's file when that file is a PDF, else 0.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function pdf_attachment_id( int $post_id ): int {
		$attachment_id = (int) get_post_meta( $post_id, 'cptdp_document_file', true );

		return $attachment_id > 0 && 'application/pdf' === get_post_mime_type( $attachment_id ) ? $attachment_id : 0;
	}

	/**
	 * What kind of page source an attachment is: `pdf`, `pptx`, `ppt`, `odp`, or '' for anything else.
	 *
	 * The mime type decides. Some hosts report an Office or OpenDocument file as a generic
	 * zip/binary type, so the file extension is used then (the content is checked again
	 * before anything is converted).
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function kind_of_attachment( int $attachment_id ): string {
		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return '';
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( 'application/pdf' === $mime ) {
			return self::KIND_PDF;
		}

		if ( isset( self::PRESENTATION_MIMES[ $mime ] ) ) {
			return self::PRESENTATION_MIMES[ $mime ];
		}

		if ( isset( self::SPREADSHEET_MIMES[ $mime ] ) ) {
			return self::SPREADSHEET_MIMES[ $mime ];
		}

		if ( isset( self::WORD_MIMES[ $mime ] ) ) {
			return self::WORD_MIMES[ $mime ];
		}

		if ( in_array( $mime, array( 'application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2' ), true ) ) {
			$extension = strtolower( (string) pathinfo( (string) get_attached_file( $attachment_id ), PATHINFO_EXTENSION ) );

			if ( in_array( $extension, array( self::KIND_PPTX, self::KIND_PPT, self::KIND_ODP, self::KIND_XLSX, self::KIND_XLS, self::KIND_ODS, self::KIND_DOCX, self::KIND_DOC, self::KIND_ODT ), true ) ) {
				return $extension;
			}
		}

		return '';
	}

	/**
	 * Attachment ID of a document's file when it is a PDF or a presentation, else 0.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function source_attachment_id( int $post_id ): int {
		$attachment_id = (int) get_post_meta( $post_id, 'cptdp_document_file', true );

		return '' !== self::kind_of_attachment( $attachment_id ) ? $attachment_id : 0;
	}

	/**
	 * The kind of file a document points at: `pdf`, `pptx`, `ppt`, `odp`, or '' for none.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function source_kind( int $post_id ): string {
		return self::kind_of_attachment( (int) get_post_meta( $post_id, 'cptdp_document_file', true ) );
	}

	/**
	 * Whether a kind is a presentation (converted with LibreOffice, server only).
	 *
	 * @param string $kind Kind from kind_of_attachment().
	 */
	public static function is_presentation_kind( string $kind ): bool {
		return in_array( $kind, array( self::KIND_PPTX, self::KIND_PPT, self::KIND_ODP ), true );
	}

	/**
	 * Whether a kind is a spreadsheet (converted with LibreOffice Calc, server only; DPB-027).
	 *
	 * @param string $kind Kind from kind_of_attachment().
	 */
	public static function is_spreadsheet_kind( string $kind ): bool {
		return in_array( $kind, array( self::KIND_XLSX, self::KIND_XLS, self::KIND_ODS ), true );
	}

	/**
	 * Whether a kind is a word-processing file (converted with LibreOffice Writer).
	 *
	 * @param string $kind Kind from kind_of_attachment().
	 */
	public static function is_word_kind( string $kind ): bool {
		return in_array( $kind, array( self::KIND_DOCX, self::KIND_DOC, self::KIND_ODT ), true );
	}

	/**
	 * Whether a kind is an Office file converted with LibreOffice (presentation or spreadsheet), server only.
	 *
	 * @param string $kind Kind from kind_of_attachment().
	 */
	public static function is_office_kind( string $kind ): bool {
		return self::is_presentation_kind( $kind ) || self::is_spreadsheet_kind( $kind ) || self::is_word_kind( $kind );
	}

	/**
	 * The file behind a document: a PDF, or a presentation that is converted to one.
	 *
	 * @param int $post_id Document ID.
	 * @return array{id:int,path:string,fingerprint:string,token:string,kind:string}|null Null when there is no readable file.
	 */
	public static function get_source( int $post_id ): ?array {
		$attachment_id = self::source_attachment_id( $post_id );

		if ( $attachment_id < 1 ) {
			return null;
		}

		$path = get_attached_file( $attachment_id );

		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) || ! is_file( $path ) ) {
			return null;
		}

		$fingerprint = self::fingerprint( $path );

		return array(
			'id'          => $attachment_id,
			'path'        => $path,
			'fingerprint' => $fingerprint,
			'token'       => $attachment_id . ':' . $fingerprint,
			'kind'        => self::kind_of_attachment( $attachment_id ),
		);
	}

	/**
	 * Cheap fingerprint of a file: size and modification time.
	 *
	 * @param string $path File path.
	 */
	public static function fingerprint( string $path ): string {
		return (string) filesize( $path ) . '-' . (string) filemtime( $path );
	}

	/**
	 * Folder (absolute path) for the images of the current PDF of a document.
	 *
	 * The name is `<attachment>-<hash8>-<document>`: it includes the document, so two
	 * documents that share one PDF each own a folder and clearing, regenerating or
	 * deleting one can never remove the other's images. (Sets made before this
	 * naming, `<attachment>-<hash8>`, are still read through their manifest.)
	 *
	 * @param int $post_id Document ID.
	 * @return string Empty when the document has no PDF.
	 */
	public static function folder_for( int $post_id ): string {
		$source = self::get_source( $post_id );

		if ( null === $source ) {
			return '';
		}

		return self::base_dir() . '/' . $source['id'] . '-' . substr( md5( $source['fingerprint'] ), 0, 8 ) . '-' . $post_id;
	}

	/**
	 * Whether a folder name is one this plugin would create: `<attachment>-<hash8>`
	 * or `<attachment>-<hash8>-<document>`. Anything else (`.`, `..`, paths, other
	 * names) is refused wherever a stored `dir` is turned into a path.
	 *
	 * @param string $dir Folder name (no slashes).
	 */
	public static function is_valid_dir( string $dir ): bool {
		return 1 === preg_match( '/^\d+-[0-9a-f]{8}(-\d+)?$/D', $dir );
	}

	/**
	 * File name of a page image.
	 *
	 * @param int    $page Page number, 1-based.
	 * @param string $ext  Extension without dot.
	 */
	public static function page_filename( int $page, string $ext ): string {
		return sprintf( 'p-%03d.%s', $page, $ext );
	}

	/**
	 * Stored manifest.
	 *
	 * @param int $post_id Document ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_manifest( int $post_id ): ?array {
		$manifest = get_post_meta( $post_id, self::META_MANIFEST, true );

		if ( ! is_array( $manifest ) || empty( $manifest['dir'] ) || empty( $manifest['count'] ) ) {
			return null;
		}

		// The meta is not underscore-protected: never turn a stored `dir` into a path unless it is one of ours.
		if ( ! is_string( $manifest['dir'] ) || ! self::is_valid_dir( $manifest['dir'] ) ) {
			return null;
		}

		return $manifest;
	}

	/**
	 * Write a manifest, then reconcile the status with the page total.
	 *
	 * A manifest may carry `source_token` (the PDF the images were made from, see
	 * CPTDP_Page_Converter::convert()). If the document's PDF is no longer that
	 * file (replaced while the conversion ran) nothing is written, so old pages are
	 * never attached to a new PDF.
	 *
	 * @param int                 $post_id  Document ID.
	 * @param array<string,mixed> $manifest Manifest (count, width, height, ext, dir, producer).
	 * @return bool False when nothing was saved (no PDF, or the PDF changed).
	 */
	public static function save_manifest( int $post_id, array $manifest ): bool {
		$source = self::get_source( $post_id );

		if ( null === $source ) {
			return false;
		}

		if ( isset( $manifest['source_token'] ) && (string) $manifest['source_token'] !== $source['token'] ) {
			return false;
		}

		unset( $manifest['source_token'] );

		// Only Office files are marked (a missing `source_kind` means a PDF, as before DPB-026).
		if ( self::is_office_kind( $source['kind'] ) ) {
			$manifest['source_kind']  = $source['kind'];
			$manifest['converted_via'] = 'libreoffice';
		}

		$manifest = array_merge(
			array(
				'source_id'   => $source['id'],
				'fingerprint' => $source['fingerprint'],
				'hash'        => (string) md5_file( $source['path'] ),
				'generated'   => time(),
				'producer'    => 'server',
			),
			$manifest
		);

		update_post_meta( $post_id, self::META_MANIFEST, $manifest );
		update_post_meta( $post_id, self::META_SOURCE, $source['token'] );
		if ( self::is_word_kind( $source['kind'] ) && ! empty( $manifest['count'] ) ) {
			update_post_meta( $post_id, 'cptdp_total_pages', (int) $manifest['count'] );
		}
		self::reconcile( $post_id );

		return true;
	}

	/**
	 * Make the status match what is on disk: ready when the images are valid,
	 * failed with a message when the page total disagrees with the image count.
	 *
	 * Does nothing when there is no manifest or it belongs to another file.
	 *
	 * @param int $post_id Document ID.
	 * @return string The status after reconciling.
	 */
	public static function reconcile( int $post_id ): string {
		$manifest = self::get_manifest( $post_id );

		if ( null === $manifest || ! self::manifest_matches_source( $post_id, $manifest ) || ! self::files_exist( $manifest ) ) {
			return self::get_status( $post_id );
		}

		$total = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );
		$count = (int) $manifest['count'];

		if ( $total > 0 && $total !== $count ) {
			$kind = (string) ( $manifest['source_kind'] ?? '' );

			if ( self::is_word_kind( $kind ) ) {
				update_post_meta( $post_id, 'cptdp_total_pages', $count );
				self::set_status( $post_id, self::STATUS_READY );

				return self::STATUS_READY;
			}

			if ( self::is_spreadsheet_kind( $kind ) ) {
				/* translators: 1: pages found in the spreadsheet, 2: the Total Pages field. */
				$format = __( 'The spreadsheet has %1$d pages, Total pages says %2$d.', 'custom-post-types-dp' );
			} elseif ( self::is_presentation_kind( $kind ) ) {
				/* translators: 1: slides found in the presentation, 2: the Total Pages field. */
				$format = __( 'The presentation has %1$d slides, Total pages says %2$d.', 'custom-post-types-dp' );
			} else {
				/* translators: 1: pages found in the PDF, 2: the Total Pages field. */
				$format = __( 'PDF has %1$d pages, Total pages says %2$d.', 'custom-post-types-dp' );
			}

			self::set_status( $post_id, self::STATUS_FAILED, sprintf( $format, $count, $total ) );

			return self::STATUS_FAILED;
		}

		self::set_status( $post_id, self::STATUS_READY );

		return self::STATUS_READY;
	}

	/**
	 * Whether the manifest was made from the document's current PDF.
	 *
	 * @param int                 $post_id  Document ID.
	 * @param array<string,mixed> $manifest Manifest.
	 */
	public static function manifest_matches_source( int $post_id, array $manifest ): bool {
		$source = self::get_source( $post_id );

		if ( null === $source || (int) ( $manifest['source_id'] ?? 0 ) !== $source['id'] ) {
			return false;
		}

		if ( (string) ( $manifest['fingerprint'] ?? '' ) === $source['fingerprint'] ) {
			return true;
		}

		// Fingerprint changed (e.g. the file was touched, restored or copied): fall back to a content hash.
		$hash = (string) ( $manifest['hash'] ?? '' );

		if ( '' === $hash || ! hash_equals( $hash, (string) md5_file( $source['path'] ) ) ) {
			return false;
		}

		// Same content: remember the new fingerprint so the whole PDF is hashed once, not on every request.
		$manifest['fingerprint'] = $source['fingerprint'];
		update_post_meta( $post_id, self::META_MANIFEST, $manifest );

		return true;
	}

	/**
	 * Whether the first and last image of a manifest exist on disk.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 */
	public static function files_exist( array $manifest ): bool {
		if ( ! self::is_valid_dir( (string) ( $manifest['dir'] ?? '' ) ) ) {
			return false;
		}

		$dir   = self::base_dir() . '/' . (string) $manifest['dir'];
		$ext   = (string) ( $manifest['ext'] ?? '' );
		$count = (int) ( $manifest['count'] ?? 0 );

		if ( '' === $ext || $count < 1 ) {
			return false;
		}

		return is_file( $dir . '/' . self::page_filename( 1, $ext ) )
			&& is_file( $dir . '/' . self::page_filename( $count, $ext ) );
	}

	/**
	 * Whether the stored images may be used by the viewer.
	 *
	 * Valid when the manifest belongs to the current PDF, the image count agrees
	 * with `cptdp_total_pages` when that is set, and the first and last images
	 * exist. The only write is a one-time refresh of the stored fingerprint when the
	 * file was touched but its content is unchanged.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function is_valid( int $post_id ): bool {
		return null !== self::valid_manifest( $post_id );
	}

	/**
	 * The manifest when its images may be used (see is_valid), else null.
	 *
	 * @param int $post_id Document ID.
	 * @return array<string,mixed>|null
	 */
	private static function valid_manifest( int $post_id ): ?array {
		$manifest = self::get_manifest( $post_id );

		if ( null === $manifest || ! self::manifest_matches_source( $post_id, $manifest ) || ! self::files_exist( $manifest ) ) {
			return null;
		}

		$total = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );

		return $total < 1 || $total === (int) $manifest['count'] ? $manifest : null;
	}

	/**
	 * Valid page data for the viewer, or null.
	 *
	 * `crop_bottom` is the share of each page's height (0..0.25) the viewer hides
	 * because it is empty; `ratio` already accounts for it (width / visible height).
	 * It is 0 when Settings > Documents "Trim empty space" is off, so switching the
	 * setting needs no regeneration.
	 *
	 * @param int $post_id Document ID.
	 * @return array{pages:string[],count:int,width:int,height:int,ratio:float,crop_bottom:float}|null
	 */
	public static function get_pages( int $post_id ): ?array {
		$manifest = self::valid_manifest( $post_id );

		if ( null === $manifest ) {
			return null;
		}

		$base     = self::base_url() . '/' . ltrim( (string) $manifest['dir'], '/' );
		$count    = (int) $manifest['count'];
		$ext      = (string) $manifest['ext'];
		$width    = (int) ( $manifest['width'] ?? 0 );
		$height   = (int) ( $manifest['height'] ?? 0 );
		$crop     = self::effective_crop( $manifest );
		$pages    = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$pages[] = $base . '/' . self::page_filename( $i, $ext );
		}

		return array(
			'pages'       => $pages,
			'count'       => $count,
			'width'       => $width,
			'height'      => $height,
			'ratio'       => $width > 0 && $height > 0 ? round( $width / ( $height * ( 1 - $crop ) ), 4 ) : 0.0,
			'crop_bottom' => $crop,
		);
	}

	/**
	 * The stored crop of a manifest (what the analysis found), 0 when invalid.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 */
	public static function stored_crop( array $manifest ): float {
		return self::clamp_crop( $manifest['crop_bottom'] ?? 0 );
	}

	/**
	 * The crop the viewer applies: the stored one, or 0 when trimming is switched off.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 */
	public static function effective_crop( array $manifest ): float {
		// A slide or sheet has a fixed shape: Office files are never trimmed.
		if ( self::is_office_kind( (string) ( $manifest['source_kind'] ?? '' ) ) ) {
			return 0.0;
		}

		if ( function_exists( 'cptdp_get_documents_settings' ) && ! cptdp_get_documents_settings( 'trim_bottom' ) ) {
			return 0.0;
		}

		return self::stored_crop( $manifest );
	}

	/**
	 * A valid crop: a finite number from 0 to MAX_CROP. Anything else (out of range,
	 * not a number) is 0, so a bad value never changes how pages look.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function clamp_crop( $value ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		$crop = (float) $value;

		if ( ! is_finite( $crop ) || $crop < 0 || $crop > CPTDP_Page_Analyzer::MAX_CROP ) {
			return 0.0;
		}

		return round( $crop, 4 );
	}

	/**
	 * Recompute the crop from the stored images, without converting again.
	 *
	 * @param int $post_id Document ID.
	 * @return float|null The new crop, or null when there is no valid set of images.
	 */
	public static function recrop( int $post_id ): ?float {
		$manifest = self::get_manifest( $post_id );

		if ( null === $manifest || ! self::manifest_matches_source( $post_id, $manifest ) || ! self::files_exist( $manifest ) ) {
			return null;
		}

		$crop = self::is_office_kind( (string) ( $manifest['source_kind'] ?? '' ) )
			? 0.0
			: CPTDP_Page_Analyzer::analyze_folder(
				self::base_dir() . '/' . ltrim( (string) $manifest['dir'], '/' ),
				(string) $manifest['ext'],
				(int) $manifest['count']
			);

		$manifest['crop_bottom'] = $crop;
		update_post_meta( $post_id, self::META_MANIFEST, $manifest );

		return $crop;
	}

	/**
	 * Current status.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function get_status( int $post_id ): string {
		$status = (string) get_post_meta( $post_id, self::META_STATUS, true );

		return in_array( $status, self::statuses(), true ) ? $status : self::STATUS_NONE;
	}

	/**
	 * Current error message.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function get_error( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_ERROR, true );
	}

	/**
	 * Set status and message. The message is cleared unless given.
	 *
	 * @param int    $post_id Document ID.
	 * @param string $status  One of the STATUS_ constants.
	 * @param string $error   Short message for the admin.
	 */
	public static function set_status( int $post_id, string $status, string $error = '' ): void {
		if ( ! in_array( $status, self::statuses(), true ) ) {
			return;
		}

		if ( self::STATUS_NONE === $status ) {
			delete_post_meta( $post_id, self::META_STATUS );
		} else {
			update_post_meta( $post_id, self::META_STATUS, $status );
		}

		'' !== $error ? update_post_meta( $post_id, self::META_ERROR, mb_substr( $error, 0, 300 ) ) : delete_post_meta( $post_id, self::META_ERROR );
	}

	/**
	 * Delete the generated images and the manifest of a document.
	 *
	 * @param int  $post_id      Document ID.
	 * @param bool $reset_status Also drop status, message and source token.
	 */
	public static function clear( int $post_id, bool $reset_status = false ): void {
		$manifest = self::get_manifest( $post_id );

		if ( null !== $manifest ) {
			self::delete_dir( self::base_dir() . '/' . ltrim( (string) $manifest['dir'], '/' ) );
		}

		$current = self::folder_for( $post_id );

		if ( '' !== $current ) {
			self::delete_dir( $current );
		}

		delete_post_meta( $post_id, self::META_MANIFEST );

		if ( $reset_status ) {
			delete_post_meta( $post_id, self::META_STATUS );
			delete_post_meta( $post_id, self::META_ERROR );
			delete_post_meta( $post_id, self::META_SOURCE );
		}
	}

	/**
	 * Delete every folder made from an attachment (used when it is deleted).
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function clear_attachment_folders( int $attachment_id ): void {
		$folders = glob( self::base_dir() . '/' . $attachment_id . '-*', GLOB_ONLYDIR );

		foreach ( false === $folders ? array() : $folders as $folder ) {
			self::delete_dir( $folder );
		}
	}

	/**
	 * Delete one generated set, only when it is a direct child of the pages folder
	 * with a name this plugin creates (so `.`, `..`, the pages folder itself and
	 * anything outside it are refused).
	 *
	 * @param string $dir Absolute folder path.
	 */
	public static function delete_dir( string $dir ): void {
		$dir  = untrailingslashit( wp_normalize_path( $dir ) );
		$base = self::base_dir();

		if ( '' === $dir || dirname( $dir ) !== $base || ! self::is_valid_dir( basename( $dir ) ) ) {
			return;
		}

		self::remove_tree( $dir );
	}

	/**
	 * Recursively remove a folder (callers have already checked the path).
	 *
	 * @param string $dir Absolute folder path.
	 */
	private static function remove_tree( string $dir ): void {
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}

		$entries = scandir( $dir );
		foreach ( false === $entries ? array() : $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::remove_tree( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Documents that use an attachment as their file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int[]
	 */
	public static function documents_using( int $attachment_id ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'document',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => 'cptdp_document_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
	}
}
