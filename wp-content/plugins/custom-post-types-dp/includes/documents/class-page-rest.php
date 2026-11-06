<?php
/**
 * Authenticated REST routes used by the admin browser to upload page images.
 *
 * Routes (all require edit rights on the document, cookie nonce):
 *
 * - GET  cptdp/v1/documents/<id>/pages         current status (the edit screen polls it)
 * - POST cptdp/v1/documents/<id>/pages/start   clear and begin a browser run (returns the run token;
 *                                              409 while another tab holds a run, unless `force`)
 * - POST cptdp/v1/documents/<id>/pages/<n>     raw image body for page n (header `X-CPTDP-Run`)
 * - POST cptdp/v1/documents/<id>/pages/finish  validate everything and write the manifest (header `X-CPTDP-Run`)
 * - POST cptdp/v1/documents/<id>/pages/retry   queue the server conversion again (the only way to redo a presentation)
 *
 * Presentations (PPTX, PPT, ODP) are converted by the server only: `start`, `<n>` and `finish`
 * answer 400 `cptdp_pptx_server_only` for them.
 *
 * There is no unauthenticated route: visitors never trigger conversion.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin-browser page upload endpoints.
 */
class CPTDP_Page_REST {

	const NAMESPACE_ID   = 'cptdp/v1';

	/**
	 * Seconds a browser run keeps its claim on a document without uploading anything.
	 */
	const RUN_TTL        = 120;
	const RUN_HEADER     = 'x_cptdp_run';
	const MAX_RATIO_DRIFT = 0.03;
	const MAX_BYTES      = 2097152;
	const MAX_DIMENSION  = 3000;
	const ALLOWED_MIMES  = array(
		'image/webp' => 'webp',
		'image/jpeg' => 'jpg',
	);

	/**
	 * Attach the hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function register_routes(): void {
		$base = '/documents/(?P<id>\d+)/pages';
		$args = array(
			'id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			self::NAMESPACE_ID,
			$base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response( self::state( (int) $request['id'] ) );
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			$base . '/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'start' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			$base . '/retry',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'retry' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			$base . '/finish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'finish' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			$base . '/(?P<n>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'upload' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array_merge(
					$args,
					array(
						'n' => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);
	}

	/**
	 * Permission: a logged-in user who can edit this document.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function can_edit( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];

		if ( 'document' !== get_post_type( $post_id ) ) {
			return new WP_Error( 'cptdp_not_found', __( 'Document not found.', 'custom-post-types-dp' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'cptdp_forbidden',
				__( 'You are not allowed to edit this document.', 'custom-post-types-dp' ),
				array( 'status' => is_user_logged_in() ? 403 : 401 )
			);
		}

		return true;
	}

	/**
	 * Begin a browser run: drop any images, mark the document as needing the browser.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function start( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$source  = CPTDP_Page_Store::get_source( $post_id );

		if ( null === $source ) {
			return new WP_Error( 'cptdp_no_pdf', __( 'The document has no PDF file.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
		}

		if ( CPTDP_Page_Store::is_office_kind( $source['kind'] ) ) {
			return self::server_only();
		}

		// Another tab is already generating these pages: do not clear its folder from under it.
		// An explicit "Regenerate" click sends `force` and takes over.
		$params = $request->get_json_params();
		$force  = is_array( $params ) && ! empty( $params['force'] );

		if ( false !== get_transient( self::run_key( $post_id ) ) && ! $force ) {
			return new WP_Error(
				'cptdp_run_active',
				__( 'Another tab is already generating these pages.', 'custom-post-types-dp' ),
				array( 'status' => 409 )
			);
		}

		$run = wp_generate_password( 20, false );
		set_transient( self::run_key( $post_id ), $run, self::RUN_TTL );

		CPTDP_Page_Store::clear( $post_id );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $post_id ) );
		update_post_meta( $post_id, CPTDP_Page_Store::META_SOURCE, $source['token'] );
		CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER );

		return rest_ensure_response(
			array(
				'status'      => CPTDP_Page_Store::STATUS_NEEDS_BROWSER,
				'fingerprint' => $source['fingerprint'],
				'maxPages'    => CPTDP_Page_Store::MAX_PAGES,
				'run'         => $run,
			)
		);
	}

	/**
	 * Transient holding the token of the browser run that owns a document.
	 *
	 * @param int $post_id Document ID.
	 */
	private static function run_key( int $post_id ): string {
		return 'cptdp_pages_run_' . $post_id;
	}

	/**
	 * While a run holds a document, only that run's token may upload or finish, so a stale
	 * tab cannot write into a newer run's set. With no run active the call is accepted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $post_id Document ID.
	 * @return WP_Error|null Error when the token does not match.
	 */
	private static function check_run( WP_REST_Request $request, int $post_id ): ?WP_Error {
		$held = get_transient( self::run_key( $post_id ) );

		if ( false === $held ) {
			return null;
		}

		$sent = (string) $request->get_header( self::RUN_HEADER );

		if ( '' === $sent || ! hash_equals( (string) $held, $sent ) ) {
			return new WP_Error(
				'cptdp_run_mismatch',
				__( 'Another tab is generating these pages now.', 'custom-post-types-dp' ),
				array( 'status' => 409 )
			);
		}

		set_transient( self::run_key( $post_id ), $held, self::RUN_TTL ); // Keep the claim while pages arrive.

		return null;
	}

	/**
	 * Store one page image.
	 *
	 * @param WP_REST_Request $request Request (raw image body).
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$page    = (int) $request['n'];
		$folder  = CPTDP_Page_Store::folder_for( $post_id );

		if ( CPTDP_Page_Store::is_office_kind( CPTDP_Page_Store::source_kind( $post_id ) ) ) {
			return self::server_only();
		}

		$stale = self::check_run( $request, $post_id );

		if ( null !== $stale ) {
			return $stale;
		}

		if ( '' === $folder ) {
			return new WP_Error( 'cptdp_no_pdf', __( 'The document has no PDF file.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
		}

		if ( $page < 1 || $page > CPTDP_Page_Store::MAX_PAGES ) {
			return new WP_Error( 'cptdp_bad_page', __( 'Page number out of range.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
		}

		$body = $request->get_body();

		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return new WP_Error( 'cptdp_bad_image', __( 'The image is empty or too large.', 'custom-post-types-dp' ), array( 'status' => 413 ) );
		}

		$info = self::inspect( $body );

		if ( is_wp_error( $info ) ) {
			return $info;
		}

		// A thumbnail (DPB-031): the same checks, a smaller size, saved as t-NNN beside the page.
		if ( $request->get_param( 'thumb' ) ) {
			if ( $info['width'] > CPTDP_Page_Thumbs::WIDTH + 40 || strlen( $body ) > 100 * 1024 || ! is_dir( $folder ) ) {
				return new WP_Error( 'cptdp_bad_image', __( 'The thumbnail is too large.', 'custom-post-types-dp' ), array( 'status' => 413 ) );
			}

			$other = 'webp' === $info['ext'] ? 'jpg' : 'webp';
			wp_delete_file( $folder . '/' . CPTDP_Page_Thumbs::filename( $page, $other ) );

			if ( false === file_put_contents( $folder . '/' . CPTDP_Page_Thumbs::filename( $page, $info['ext'] ), $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
				return new WP_Error( 'cptdp_write_failed', __( 'Could not save the thumbnail.', 'custom-post-types-dp' ), array( 'status' => 500 ) );
			}

			return rest_ensure_response( array( 'page' => $page, 'thumb' => true ) );
		}

		if ( ! wp_mkdir_p( $folder ) ) {
			return new WP_Error( 'cptdp_write_failed', __( 'Could not create the page images folder.', 'custom-post-types-dp' ), array( 'status' => 500 ) );
		}

		// One extension per set: drop a page saved earlier with the other format.
		$other = 'webp' === $info['ext'] ? 'jpg' : 'webp';
		wp_delete_file( $folder . '/' . CPTDP_Page_Store::page_filename( $page, $other ) );

		$file = $folder . '/' . CPTDP_Page_Store::page_filename( $page, $info['ext'] );

		if ( false === file_put_contents( $file, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
			return new WP_Error( 'cptdp_write_failed', __( 'Could not save the page image.', 'custom-post-types-dp' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'page'   => $page,
				'width'  => $info['width'],
				'height' => $info['height'],
			)
		);
	}

	/**
	 * Validate the uploaded set and write the manifest, or record a failure.
	 *
	 * Body: `{ count, ext, fingerprint, cropBottom }`, or `{ error }` when the browser gave up.
	 * `cropBottom` is the empty share of the page bottom the browser measured (0..0.25);
	 * anything else is stored as 0.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function finish( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$source  = CPTDP_Page_Store::get_source( $post_id );

		if ( null === $source ) {
			return new WP_Error( 'cptdp_no_pdf', __( 'The document has no PDF file.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
		}

		if ( CPTDP_Page_Store::is_office_kind( $source['kind'] ) ) {
			return self::server_only();
		}

		$stale = self::check_run( $request, $post_id );

		if ( null !== $stale ) {
			return $stale;
		}

		delete_transient( self::run_key( $post_id ) ); // The run ends here, whatever the outcome.

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$folder = CPTDP_Page_Store::folder_for( $post_id );

		if ( ! empty( $params['error'] ) ) {
			CPTDP_Page_Store::delete_dir( $folder );
			CPTDP_Page_Store::set_status(
				$post_id,
				CPTDP_Page_Store::STATUS_FAILED,
				sanitize_text_field( (string) $params['error'] )
			);

			return rest_ensure_response( self::state( $post_id ) );
		}

		$count = isset( $params['count'] ) ? absint( $params['count'] ) : 0;
		$ext   = isset( $params['ext'] ) ? sanitize_key( (string) $params['ext'] ) : '';
		$print = isset( $params['fingerprint'] ) ? sanitize_text_field( (string) $params['fingerprint'] ) : '';

		$problem = null;

		if ( ! hash_equals( $source['fingerprint'], $print ) ) {
			$problem = __( 'The PDF changed while the pages were being generated. Try again.', 'custom-post-types-dp' );
		} elseif ( $count < 1 || $count > CPTDP_Page_Store::MAX_PAGES ) {
			$problem = __( 'Invalid number of pages.', 'custom-post-types-dp' );
		} elseif ( ! in_array( $ext, self::ALLOWED_MIMES, true ) ) {
			$problem = __( 'Invalid image format.', 'custom-post-types-dp' );
		}

		$width  = 0;
		$height = 0;

		if ( null === $problem ) {
			for ( $i = 1; $i <= $count; $i++ ) {
				$file = $folder . '/' . CPTDP_Page_Store::page_filename( $i, $ext );
				$size = is_file( $file ) ? getimagesize( $file ) : false;

				if ( ! $size || self::mime_ext( (string) $size['mime'] ) !== $ext ) {
					$problem = sprintf(
						/* translators: %d: page number. */
						__( 'Page %d is missing or invalid.', 'custom-post-types-dp' ),
						$i
					);
					break;
				}

				if ( 1 === $i ) {
					$width  = (int) $size[0];
					$height = (int) $size[1];
				} elseif ( abs( ( $size[0] / $size[1] ) - ( $width / $height ) ) / ( $width / $height ) > self::MAX_RATIO_DRIFT ) {
					$problem = sprintf(
						/* translators: %d: page number. */
						__( 'Page %d is not the same shape as page 1.', 'custom-post-types-dp' ),
						$i
					);
					break;
				}
			}
		}

		if ( null === $problem ) {
			self::delete_extra_files( $folder, $count, $ext );
		}

		if ( null !== $problem ) {
			CPTDP_Page_Store::delete_dir( $folder );
			CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_FAILED, $problem );

			return rest_ensure_response( self::state( $post_id ) );
		}

		$manifest = array(
			'count'       => $count,
			'width'       => $width,
			'height'      => $height,
			'ext'         => $ext,
			'dir'         => basename( $folder ),
			'producer'    => 'browser',
			'crop_bottom' => CPTDP_Page_Store::clamp_crop( $params['cropBottom'] ?? 0 ),
		);

		// Thumbnails the browser uploaded (DPB-031): kept only when the whole set is there and valid.
		$thumb = self::checked_thumbs( $folder, $count, $ext );

		if ( null === $thumb ) {
			CPTDP_Page_Thumbs::delete( $folder, $ext, $count );
			CPTDP_Page_Thumbs::delete( $folder, 'webp' === $ext ? 'jpg' : 'webp', $count );
			$thumb = CPTDP_Page_Thumbs::make_for_folder( $folder, $ext, $count ); // Only when the server has Imagick.
		}

		if ( null !== $thumb ) {
			$manifest['thumb_width']  = $thumb['width'];
			$manifest['thumb_height'] = $thumb['height'];
		}

		// Names from the PDF's bookmarks (DPB-030): clean ranges or nothing.
		$names = CPTDP_Page_Outline::sanitize_ranges( $params['pageNames'] ?? array(), $count );

		if ( $names ) {
			$manifest['page_names'] = $names;
		}

		CPTDP_Page_Store::save_manifest( $post_id, $manifest );

		return rest_ensure_response( self::state( $post_id ) );
	}

	/**
	 * Check the thumbnails a browser run uploaded.
	 *
	 * @param string $folder Set folder.
	 * @param int    $count  Number of pages.
	 * @param string $ext    Extension of the set.
	 * @return array{width:int,height:int}|null The first thumbnail's size, or null when any is missing or invalid.
	 */
	private static function checked_thumbs( string $folder, int $count, string $ext ): ?array {
		$first = null;

		for ( $i = 1; $i <= $count; $i++ ) {
			$file = $folder . '/' . CPTDP_Page_Thumbs::filename( $i, $ext );
			$size = is_file( $file ) ? getimagesize( $file ) : false;

			if ( ! $size || self::mime_ext( (string) $size['mime'] ) !== $ext || $size[0] > CPTDP_Page_Thumbs::WIDTH + 40 ) {
				return null;
			}

			if ( null === $first ) {
				$first = array(
					'width'  => (int) $size[0],
					'height' => (int) $size[1],
				);
			}
		}

		return $first;
	}

	/**
	 * Remove page files that are not part of the finished set: pages after `count`, and
	 * pages saved earlier in the other format.
	 *
	 * @param string $folder Set folder.
	 * @param int    $count  Number of pages.
	 * @param string $ext    Extension of the set.
	 */
	private static function delete_extra_files( string $folder, int $count, string $ext ): void {
		foreach ( (array) glob( $folder . '/[pt]-*' ) as $file ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			if ( 1 !== preg_match( '/^[pt]-(\d{3,})\.(webp|jpg)$/', basename( $file ), $m ) || (int) $m[1] > $count || $m[2] !== $ext ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Queue the server conversion again (Regenerate for presentations, or a PDF retry).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function retry( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];

		if ( null === CPTDP_Page_Store::get_source( $post_id ) ) {
			return new WP_Error( 'cptdp_no_pdf', __( 'The document has no PDF file.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
		}

		CPTDP_Page_Scheduler::ensure( $post_id, true );

		return rest_ensure_response( self::state( $post_id ) );
	}

	/**
	 * The error for the browser-upload routes on a presentation.
	 */
	private static function server_only(): WP_Error {
		return new WP_Error(
			'cptdp_pptx_server_only',
			__( 'Presentations and spreadsheets are converted by the server only. Use Regenerate, or upload a PDF.', 'custom-post-types-dp' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Status payload returned to the admin script.
	 *
	 * @param int $post_id Document ID.
	 * @return array<string,mixed>
	 */
	public static function state( int $post_id ): array {
		$manifest = CPTDP_Page_Store::get_manifest( $post_id );

		return array(
			'status'   => CPTDP_Page_Store::get_status( $post_id ),
			'error'    => CPTDP_Page_Store::get_error( $post_id ),
			'count'    => null !== $manifest ? (int) $manifest['count'] : 0,
			'producer' => null !== $manifest ? (string) ( $manifest['producer'] ?? '' ) : '',
			'kind'     => CPTDP_Page_Store::source_kind( $post_id ),
		);
	}

	/**
	 * Validate an uploaded image: real WebP or JPEG, sane size.
	 *
	 * @param string $body Raw bytes.
	 * @return array{ext:string,width:int,height:int}|WP_Error
	 */
	private static function inspect( string $body ) {
		$size = getimagesizefromstring( $body );

		if ( ! $size || '' === self::mime_ext( (string) $size['mime'] ) ) {
			return new WP_Error( 'cptdp_bad_image', __( 'Only WebP or JPEG images are accepted.', 'custom-post-types-dp' ), array( 'status' => 415 ) );
		}

		if ( $size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION ) {
			return new WP_Error( 'cptdp_bad_image', __( 'The image dimensions are out of range.', 'custom-post-types-dp' ), array( 'status' => 413 ) );
		}

		return array(
			'ext'    => self::mime_ext( (string) $size['mime'] ),
			'width'  => (int) $size[0],
			'height' => (int) $size[1],
		);
	}

	/**
	 * Extension for an allowed mime type, or ''.
	 *
	 * @param string $mime Mime type.
	 */
	private static function mime_ext( string $mime ): string {
		return self::ALLOWED_MIMES[ $mime ] ?? '';
	}
}
