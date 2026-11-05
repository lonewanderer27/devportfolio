<?php
/**
 * Document page images: public PHP API, meta, "Page images" meta box and the admin-browser generator.
 *
 * The page images of a document's PDF are generated when the document is saved
 * (see CPTDP_Page_Scheduler). The viewer in the theme reads them through
 * `cptdp_get_document_pages()`.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

CPTDP_Page_Scheduler::init();
CPTDP_Page_REST::init();

if ( ! function_exists( 'cptdp_get_document_pages' ) ) :
	/**
	 * Stored page images of a document, when they are valid.
	 *
	 * Valid = made from the current PDF, the image count matches `cptdp_total_pages`
	 * when that is set, and the first and last files exist. Never converts, never writes.
	 *
	 * `crop_bottom` (0..0.25) is the share of each page's height that is empty and that
	 * the viewer hides; `ratio` already accounts for it. Both are 0 when Settings >
	 * Documents "Trim empty space" is off.
	 *
	 * @param int $post_id Document ID.
	 * @return array{pages:string[],count:int,width:int,height:int,ratio:float,crop_bottom:float}|null
	 */
	function cptdp_get_document_pages( int $post_id ): ?array {
		return CPTDP_Page_Store::get_pages( $post_id );
	}
endif;

if ( ! function_exists( 'cptdp_document_pdf_url' ) ) :
	/**
	 * URL of a document's PDF, or '' when its file is not a PDF.
	 *
	 * @param int $post_id Document ID.
	 */
	function cptdp_document_pdf_url( int $post_id ): string {
		$attachment_id = CPTDP_Page_Store::pdf_attachment_id( $post_id );
		$url           = $attachment_id > 0 ? wp_get_attachment_url( $attachment_id ) : '';

		return is_string( $url ) ? $url : '';
	}
endif;

if ( ! function_exists( 'cptdp_document_pdfjs_urls' ) ) :
	/**
	 * URLs of the pdf.js build the plugin ships (shared by the admin generator and the theme viewer).
	 *
	 * @return array{module:string,worker:string}
	 */
	function cptdp_document_pdfjs_urls(): array {
		return array(
			'module' => CPTDP_URL . 'assets/vendor/pdfjs/pdf.min.mjs',
			'worker' => CPTDP_URL . 'assets/vendor/pdfjs/pdf.worker.min.mjs',
		);
	}
endif;

/**
 * Register the (non-public) page image meta.
 */
function cptdp_register_document_pages_meta(): void {
	$auth = static function ( $allowed, $meta_key, $post_id ): bool {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', (int) $post_id );
	};

	register_post_meta(
		'document',
		CPTDP_Page_Store::META_MANIFEST,
		array(
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'document',
		CPTDP_Page_Store::META_STATUS,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => CPTDP_Page_Store::STATUS_NONE,
			'show_in_rest'      => false,
			'sanitize_callback' => static function ( $status ): string {
				return in_array( $status, CPTDP_Page_Store::statuses(), true ) ? (string) $status : CPTDP_Page_Store::STATUS_NONE;
			},
			'auth_callback'     => $auth,
		)
	);

	foreach ( array( CPTDP_Page_Store::META_ERROR, CPTDP_Page_Store::META_SOURCE ) as $key ) {
		register_post_meta(
			'document',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
	}
}
add_action( 'cptdp_register_post_types', 'cptdp_register_document_pages_meta', 17 );

/**
 * Human label for a status.
 *
 * @param string $status Status.
 * @param int    $count  Number of images when ready.
 */
function cptdp_document_pages_status_label( string $status, int $count = 0, string $kind = '' ): string {
	switch ( $status ) {
		case CPTDP_Page_Store::STATUS_READY:
			if ( CPTDP_Page_Store::is_presentation_kind( $kind ) ) {
				$from = CPTDP_Page_Store::KIND_ODP === $kind ? __( 'OpenDocument', 'custom-post-types-dp' ) : __( 'PowerPoint', 'custom-post-types-dp' );

				/* translators: 1: number of slides, 2: the format the slides were converted from (PowerPoint or OpenDocument). */
				return sprintf( _n( 'Ready, %1$d slide (converted from %2$s)', 'Ready, %1$d slides (converted from %2$s)', $count, 'custom-post-types-dp' ), $count, $from );
			}

			/* translators: %d: number of pages. */
			return sprintf( _n( 'Ready, %d page', 'Ready, %d pages', $count, 'custom-post-types-dp' ), $count );
		case CPTDP_Page_Store::STATUS_PENDING:
			return __( 'Pending (the server is generating the pages)', 'custom-post-types-dp' );
		case CPTDP_Page_Store::STATUS_NEEDS_BROWSER:
			return __( 'Needs your browser', 'custom-post-types-dp' );
		case CPTDP_Page_Store::STATUS_FAILED:
			return __( 'Failed', 'custom-post-types-dp' );
	}

	return __( 'No page images', 'custom-post-types-dp' );
}

/**
 * Add the "Page images" meta box to the Document edit screen.
 */
function cptdp_document_pages_add_meta_box(): void {
	add_meta_box(
		'cptdp_document_pages',
		esc_html__( 'Page images', 'custom-post-types-dp' ),
		'cptdp_document_pages_render_box',
		'document',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_document', 'cptdp_document_pages_add_meta_box' );

/**
 * Render the "Page images" meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_document_pages_render_box( WP_Post $post ): void {
	$source   = CPTDP_Page_Store::get_source( $post->ID );
	$status   = CPTDP_Page_Store::get_status( $post->ID );
	$error    = CPTDP_Page_Store::get_error( $post->ID );
	$manifest = CPTDP_Page_Store::get_manifest( $post->ID );
	$count    = null !== $manifest ? (int) $manifest['count'] : 0;
	$producer = null !== $manifest ? (string) ( $manifest['producer'] ?? '' ) : '';

	if ( null === $source ) {
		echo '<p>' . esc_html__( 'Select a PDF (or a PowerPoint or OpenDocument presentation) as the document file, and save, to generate page images for the viewer.', 'custom-post-types-dp' ) . '</p>';
		return;
	}

	$kind = $source['kind'];
	?>
	<div id="cptdp-pages-box" data-status="<?php echo esc_attr( $status ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>">
		<?php
		if ( CPTDP_Page_Store::is_presentation_kind( $kind ) ) {
			echo cptdp_pptx_warning( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		?>
		<p>
			<strong id="cptdp-pages-status"><?php echo esc_html( cptdp_document_pages_status_label( $status, $count, $kind ) ); ?></strong>
			<?php if ( CPTDP_Page_Store::STATUS_READY === $status && '' !== $producer ) : ?>
				<br><span id="cptdp-pages-producer" class="description">
					<?php
					echo esc_html(
						'browser' === $producer
							? __( 'Made in a browser.', 'custom-post-types-dp' )
							: __( 'Made by the server.', 'custom-post-types-dp' )
					);
					?>
				</span>
			<?php endif; ?>
		</p>
		<p id="cptdp-pages-error" class="description" style="color:#b32d2e" <?php echo '' === $error ? 'hidden' : ''; ?>><?php echo esc_html( $error ); ?></p>
		<progress id="cptdp-pages-progress" max="100" value="0" style="width:100%" hidden></progress>
		<p id="cptdp-pages-progress-label" class="description" hidden></p>
		<p>
			<button type="button" class="button" id="cptdp-pages-regenerate"><?php echo CPTDP_Page_Store::is_presentation_kind( $kind ) ? esc_html__( 'Regenerate', 'custom-post-types-dp' ) : esc_html__( 'Regenerate in this browser', 'custom-post-types-dp' ); ?></button>
		</p>
		<p class="description">
			<?php
			echo CPTDP_Page_Store::is_presentation_kind( $kind )
				? esc_html__( 'Page images are generated by the server when you save, so visitors see the document straight away.', 'custom-post-types-dp' )
				: esc_html__( 'Page images are generated when you save, so visitors see the document straight away. Keep this screen open while it runs in your browser.', 'custom-post-types-dp' );
			?>
		</p>
	</div>
	<?php
}

/**
 * Enqueue the page generator on Document edit screens.
 *
 * @param string $hook Current admin page.
 */
function cptdp_document_pages_enqueue_admin( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'document' !== $screen->post_type ) {
		return;
	}

	$post_id = (int) get_the_ID();
	$source  = $post_id ? CPTDP_Page_Store::get_source( $post_id ) : null;

	if ( null === $source ) {
		return;
	}

	$base = 'cptdp/v1/documents/' . $post_id . '/pages';

	wp_enqueue_script(
		'cptdp-admin-document-pages',
		CPTDP_URL . 'assets/admin-document-pages.js',
		array(),
		CPTDP_VERSION,
		true
	);
	wp_localize_script(
		'cptdp-admin-document-pages',
		'cptdpDocumentPages',
		array(
			'postId'      => $post_id,
			'kind'        => $source['kind'],
			'retryUrl'    => rest_url( $base . '/retry' ),
			'status'      => CPTDP_Page_Store::get_status( $post_id ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'stateUrl'    => rest_url( $base ),
			'startUrl'    => rest_url( $base . '/start' ),
			'finishUrl'   => rest_url( $base . '/finish' ),
			'uploadUrl'   => rest_url( $base . '/__PAGE__' ),
			'pdfUrl'      => cptdp_document_pdf_url( $post_id ),
			'pdfjs'       => cptdp_document_pdfjs_urls(),
			'fingerprint' => $source['fingerprint'],
			'maxPages'    => CPTDP_Page_Store::MAX_PAGES,
			'widths'      => array(
				'portrait'  => CPTDP_Page_Converter::WIDTH_PORTRAIT,
				'landscape' => CPTDP_Page_Converter::WIDTH_LANDSCAPE,
			),
			'quality'     => CPTDP_Page_Converter::QUALITY / 100,
			'i18n'        => array(
				'ready'       => __( 'Ready', 'custom-post-types-dp' ),
				/* translators: %d: number of slides. */
				'readySlides' => __( 'Ready, %d slides', 'custom-post-types-dp' ),
				'pending'     => __( 'Pending (the server is generating the pages)', 'custom-post-types-dp' ),
				'working'     => __( 'Generating in your browser…', 'custom-post-types-dp' ),
				'needs'       => __( 'Needs your browser', 'custom-post-types-dp' ),
				'failed'      => __( 'Failed', 'custom-post-types-dp' ),
				/* translators: 1: current page, 2: total pages. */
				'progress'    => __( 'Page %1$d of %2$d', 'custom-post-types-dp' ),
				'tooMany'     => __( 'The PDF has too many pages.', 'custom-post-types-dp' ),
				'uploadError' => __( 'A page could not be uploaded.', 'custom-post-types-dp' ),
				'loadError'   => __( 'The PDF could not be read in this browser.', 'custom-post-types-dp' ),
				'anotherTab'  => __( 'Another tab is already generating these pages.', 'custom-post-types-dp' ),
			),
		)
	);
	// pdf.js is an ES module and the generator loads it with import(); the script itself is a classic one.
}
add_action( 'admin_enqueue_scripts', 'cptdp_document_pages_enqueue_admin' );

/**
 * Notice on the Documents list when documents are waiting for a browser.
 */
function cptdp_document_pages_list_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-document' !== $screen->id || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'document',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => CPTDP_Page_Store::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => CPTDP_Page_Store::STATUS_NEEDS_BROWSER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if ( ! $ids ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %d: number of documents. */
				_n(
					'%d document needs page images. Open it in the editor and keep the screen open while your browser generates them.',
					'%d documents need page images. Open each in the editor and keep the screen open while your browser generates them.',
					count( $ids ),
					'custom-post-types-dp'
				),
				count( $ids )
			)
		)
	);
}
add_action( 'admin_notices', 'cptdp_document_pages_list_notice' );
