<?php
/**
 * Page images of documents and collaterals: public PHP API, meta, "Page images" meta box and the admin-browser generator.
 *
 * The page images of a post's file are generated when the post is saved (see CPTDP_Page_Scheduler),
 * for every type in CPTDP_Page_Store::post_types(). The theme reads them through
 * `cptdp_get_document_pages()` and `cptdp_get_collateral_pages()`.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

CPTDP_Page_Scheduler::init();
CPTDP_Page_REST::init();
CPTDP_Page_Featured::init();

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
	 * Also `names` / `name_ranges` (DPB-030) and `thumbs` / `thumb_width` / `thumb_height` (DPB-031),
	 * see CPTDP_Page_Store::get_pages().
	 *
	 * @param int $post_id Document ID.
	 * @return array{pages:string[],count:int,width:int,height:int,ratio:float,crop_bottom:float,names:string[],name_ranges:array,thumbs:string[],thumb_width:int,thumb_height:int}|null
	 */
	function cptdp_get_document_pages( int $post_id ): ?array {
		return CPTDP_Page_Store::get_pages( $post_id );
	}
endif;

if ( ! function_exists( 'cptdp_get_collateral_pages' ) ) :
	/**
	 * Stored page images of a collateral, when they are valid (DPC-023): the same array as
	 * `cptdp_get_document_pages()`, or null for anything that is not a collateral. Never converts, never writes.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array{pages:string[],count:int,width:int,height:int,ratio:float,crop_bottom:float,names:string[],name_ranges:array,thumbs:string[],thumb_width:int,thumb_height:int}|null
	 */
	function cptdp_get_collateral_pages( int $post_id ): ?array {
		return 'collateral' === get_post_type( $post_id ) ? CPTDP_Page_Store::get_pages( $post_id ) : null;
	}
endif;

if ( ! function_exists( 'cptdp_get_collateral_pages_status' ) ) :
	/**
	 * Status of a collateral's page image set (DPC-025): `none|pending|ready|needs_browser|failed`.
	 *
	 * `''` for a post that is not a collateral. Read-only: never converts and never writes, so the
	 * theme can show a loading or error state without reading the plugin's private meta key.
	 *
	 * @param int $post_id Collateral ID.
	 * @return string
	 */
	function cptdp_get_collateral_pages_status( int $post_id ): string {
		return 'collateral' === get_post_type( $post_id ) ? CPTDP_Page_Store::get_status( $post_id ) : '';
	}
endif;

if ( ! function_exists( 'cptdp_collateral_loading_image_url' ) ) :
	/**
	 * URL of the sample loading image shown while a collateral's page images are being made (DPC-025).
	 *
	 * The file ships in the plugin so both the site and wp-admin can use it; it is a static
	 * greyscale SVG drawn from the theme palette, with no script or external reference.
	 *
	 * @return string
	 */
	function cptdp_collateral_loading_image_url(): string {
		return CPTDP_URL . 'assets/collateral-loading.svg';
	}
endif;

if ( ! function_exists( 'cptdp_document_admin_thumb' ) ) :
	/**
	 * The picture of a document for the admin (DPB-031): page 1, the same file the Pages sheet draws.
	 *
	 * The small thumbnail when the set has them, else (a set made before thumbnails existed) the first
	 * stored page image, which the caller scales by CSS. Null when there are no valid page images.
	 *
	 * @param int $post_id Document ID.
	 * @return array{url:string,width:int,height:int,small:bool}|null
	 */
	function cptdp_document_admin_thumb( int $post_id ): ?array {
		$pages = CPTDP_Page_Store::get_pages( $post_id );

		if ( null === $pages ) {
			return null;
		}

		if ( ! empty( $pages['thumbs'] ) ) {
			return array(
				'url'    => (string) $pages['thumbs'][0],
				'width'  => (int) $pages['thumb_width'],
				'height' => (int) $pages['thumb_height'],
				'small'  => true,
			);
		}

		return empty( $pages['pages'][0] ) ? null : array(
			'url'    => (string) $pages['pages'][0],
			'width'  => (int) $pages['width'],
			'height' => (int) $pages['height'],
			'small'  => false,
		);
	}
endif;

if ( ! function_exists( 'cptdp_document_admin_thumb_html' ) ) :
	/**
	 * `<img>` of the admin thumbnail at 50px wide, lazy and low priority, or '' when there is none.
	 *
	 * @param int $post_id Document ID.
	 * @param int $width   Width in pixels.
	 */
	function cptdp_document_admin_thumb_html( int $post_id, int $width = 50 ): string {
		$thumb = cptdp_document_admin_thumb( $post_id );

		if ( null === $thumb || '' === $thumb['url'] ) {
			return '';
		}

		$height = $thumb['width'] > 0 && $thumb['height'] > 0 ? (int) round( $width * $thumb['height'] / $thumb['width'] ) : 0;

		return sprintf(
			'<img src="%1$s" alt="" width="%2$d"%3$s loading="lazy" decoding="async" fetchpriority="low" style="width:%2$dpx;height:%4$s;border:1px solid #c3c4c7;background:#fff;display:block">',
			esc_url( $thumb['url'] ),
			$width,
			$height > 0 ? ' height="' . $height . '"' : '',
			$height > 0 ? $height . 'px' : 'auto'
		);
	}
endif;

if ( ! function_exists( 'cptdp_collateral_admin_loading_html' ) ) :
	/**
	 * The static loading image of a collateral whose page images are still being made (DPC-025).
	 *
	 * `''` unless the post is a collateral in the `pending` state. It is the same SVG the site shows,
	 * 50px wide at the paper-size ratio, decorative (`alt=""`) and named by its title. Nothing here
	 * starts or retries work: it only shows the state.
	 *
	 * @param int $post_id Collateral ID.
	 * @param int $width   Width in pixels.
	 */
	function cptdp_collateral_admin_loading_html( int $post_id, int $width = 50 ): string {
		if ( CPTDP_Page_Store::STATUS_PENDING !== cptdp_get_collateral_pages_status( $post_id ) ) {
			return '';
		}

		$paper_size = (string) get_post_meta( $post_id, 'cptdp_paper_size', true );
		$height     = (int) round( $width * ( false !== stripos( $paper_size, 'landscape' ) ? 1 / M_SQRT2 : M_SQRT2 ) );

		return sprintf(
			'<img src="%1$s" alt="" width="%2$d" height="%3$d" title="%4$s" loading="lazy" decoding="async" fetchpriority="low" style="width:%2$dpx;height:%3$dpx;border:1px solid #c3c4c7;background:#fff;display:block">',
			esc_url( cptdp_collateral_loading_image_url() ),
			$width,
			$height,
			esc_attr__( 'Preview is being prepared', 'custom-post-types-dp' )
		);
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
 * Register the (non-public) page image meta, for every post type with page images.
 */
function cptdp_register_document_pages_meta(): void {
	foreach ( CPTDP_Page_Store::post_types() as $type ) {
		cptdp_register_pages_meta_for( $type );
	}
}
add_action( 'cptdp_register_post_types', 'cptdp_register_document_pages_meta', 17 );

/**
 * Register the page image meta of one post type.
 *
 * @param string $type Post type.
 */
function cptdp_register_pages_meta_for( string $type ): void {
	$auth = static function ( $allowed, $meta_key, $post_id ): bool {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', (int) $post_id );
	};

	register_post_meta(
		$type,
		CPTDP_Page_Featured::META,
		array(
			'type'          => 'integer',
			'single'        => true,
			'default'       => 0,
			'show_in_rest'  => false,
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		$type,
		CPTDP_Page_Store::META_MANIFEST,
		array(
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		$type,
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
			$type,
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

			if ( CPTDP_Page_Store::is_spreadsheet_kind( $kind ) ) {
				$from = CPTDP_Page_Store::KIND_ODS === $kind ? __( 'OpenDocument', 'custom-post-types-dp' ) : __( 'Excel', 'custom-post-types-dp' );

				/* translators: 1: number of pages, 2: the format the pages were converted from (Excel or OpenDocument). */
				return sprintf( _n( 'Ready, %1$d page (converted from %2$s)', 'Ready, %1$d pages (converted from %2$s)', $count, 'custom-post-types-dp' ), $count, $from );
			}

			if ( CPTDP_Page_Store::is_image_kind( $kind ) ) {
				return __( 'Ready, 1 page (from an image)', 'custom-post-types-dp' );
			}

			if ( CPTDP_Page_Store::is_word_kind( $kind ) ) {
				$from = CPTDP_Page_Store::KIND_ODT === $kind ? __( 'OpenDocument', 'custom-post-types-dp' ) : __( 'Word', 'custom-post-types-dp' );

				/* translators: 1: number of pages, 2: the format the pages were converted from. */
				return sprintf( _n( 'Ready, %1$d page (converted from %2$s)', 'Ready, %1$d pages (converted from %2$s)', $count, 'custom-post-types-dp' ), $count, $from );
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
 * The "Page images" list cell of a document or collateral: the status label, a link to the edit
 * screen when someone has to act, the error as its title, or a dash when the post has no file.
 *
 * @param int $post_id Post ID.
 * @return string HTML.
 */
function cptdp_pages_status_cell( int $post_id ): string {
	if ( null === CPTDP_Page_Store::get_source( $post_id ) ) {
		return '&mdash;';
	}

	$status   = CPTDP_Page_Store::get_status( $post_id );
	$manifest = CPTDP_Page_Store::get_manifest( $post_id );
	$label    = cptdp_document_pages_status_label( $status, null !== $manifest ? (int) $manifest['count'] : 0, CPTDP_Page_Store::source_kind( $post_id ) );
	$error    = CPTDP_Page_Store::get_error( $post_id );

	if ( in_array( $status, array( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::STATUS_FAILED ), true ) ) {
		return sprintf(
			'<a href="%1$s" title="%2$s">%3$s</a>',
			esc_url( (string) get_edit_post_link( $post_id ) ),
			esc_attr( $error ),
			esc_html( $label )
		);
	}

	return '<span title="' . esc_attr( $error ) . '">' . esc_html( $label ) . '</span>';
}

/**
 * Add the "Page images" meta box to the edit screen of every post type with page images.
 *
 * @param string $post_type Post type of the screen.
 */
function cptdp_document_pages_add_meta_box( string $post_type = 'document' ): void {
	if ( ! in_array( $post_type, CPTDP_Page_Store::post_types(), true ) ) {
		return;
	}

	add_meta_box(
		'cptdp_document_pages',
		esc_html__( 'Page images', 'custom-post-types-dp' ),
		'cptdp_document_pages_render_box',
		$post_type,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'cptdp_document_pages_add_meta_box' );

/**
 * The link to the shared page image settings, for administrators only (DPC-023), or ''.
 */
function cptdp_pages_settings_link(): string {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	return sprintf(
		'<p class="description"><a href="%1$s">%2$s</a></p>',
		esc_url( admin_url( 'options-general.php?page=cptdp-documents#cptdp-page-images' ) ),
		esc_html__( 'Page image settings (shared with Documents/Brand Collaterals)', 'custom-post-types-dp' )
	);
}

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
	$link     = cptdp_pages_settings_link();

	if ( null === $source ) {
		$file = (int) get_post_meta( $post->ID, CPTDP_Page_Store::file_meta_key( $post->ID ), true );

		if ( 'collateral' === $post->post_type && $file > 0 && 'image/svg+xml' === get_post_mime_type( $file ) ) {
			echo '<p>' . esc_html__( 'SVG files are not turned into page images; upload a PNG or PDF export to show it in the viewer.', 'custom-post-types-dp' ) . '</p>';
		} elseif ( 'collateral' === $post->post_type ) {
			echo '<p>' . esc_html__( 'Select a PDF, an image (JPEG, PNG, WebP or GIF) or a PowerPoint, Excel, Word or OpenDocument file as the collateral file, and save, to generate page images for the viewer.', 'custom-post-types-dp' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Select a PDF or a PowerPoint, Excel, Word or OpenDocument file as the document file, and save, to generate page images for the viewer.', 'custom-post-types-dp' ) . '</p>';
		}

		echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		return;
	}

	$kind        = $source['kind'];
	$server_only = CPTDP_Page_Store::is_server_only_kind( $kind );
	?>
	<div id="cptdp-pages-box" data-status="<?php echo esc_attr( $status ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>">
		<?php
		if ( CPTDP_Page_Store::is_office_kind( $kind ) ) {
			echo cptdp_office_warning( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		?>
		<?php
		$cptdp_thumb = CPTDP_Page_Store::STATUS_READY === $status ? cptdp_document_admin_thumb_html( $post->ID, 80 ) : '';

		if ( '' === $cptdp_thumb && 'collateral' === $post->post_type && CPTDP_Page_Store::STATUS_PENDING === $status ) {
			$cptdp_thumb = cptdp_collateral_admin_loading_html( $post->ID, 80 );
		}

		if ( '' !== $cptdp_thumb ) {
			echo '<p>' . $cptdp_thumb . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by cptdp_document_admin_thumb_html()/cptdp_collateral_admin_loading_html().
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
			<button type="button" class="button" id="cptdp-pages-regenerate"><?php echo $server_only ? esc_html__( 'Regenerate', 'custom-post-types-dp' ) : esc_html__( 'Regenerate in this browser', 'custom-post-types-dp' ); ?></button>
		</p>
		<p class="description">
			<?php
			$cptdp_singular = strtolower( (string) get_post_type_object( $post->post_type )->labels->singular_name );

			echo esc_html(
				$server_only
					/* translators: %s: post type name, e.g. document or collateral. */
					? sprintf( __( 'Page images are generated by the server when you save, so visitors see the %s straight away.', 'custom-post-types-dp' ), $cptdp_singular )
					/* translators: %s: post type name, e.g. document or collateral. */
					: sprintf( __( 'Page images are generated when you save, so visitors see the %s straight away. Keep this screen open while it runs in your browser.', 'custom-post-types-dp' ), $cptdp_singular )
			);
			?>
		</p>
		<?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
	</div>
	<?php
}

/**
 * Enqueue the page generator on the edit screens of every post type with page images.
 *
 * @param string $hook Current admin page.
 */
function cptdp_document_pages_enqueue_admin( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->post_type, CPTDP_Page_Store::post_types(), true ) ) {
		return;
	}

	$post_id = (int) get_the_ID();
	$source  = $post_id ? CPTDP_Page_Store::get_source( $post_id ) : null;
	$route   = $post_id ? CPTDP_Page_REST::route_for( $post_id ) : '';

	if ( null === $source || '' === $route ) {
		return;
	}

	$base = CPTDP_Page_REST::NAMESPACE_ID . '/' . $route;

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
			'thumbWidth'  => CPTDP_Page_Thumbs::WIDTH,
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
 * Notice on the Documents and Collaterals lists when posts are waiting for a browser.
 */
function cptdp_document_pages_list_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->post_type, CPTDP_Page_Store::post_types(), true ) || 'edit-' . $screen->post_type !== $screen->id || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => $screen->post_type,
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

	$count = count( $ids );

	if ( 'collateral' === $screen->post_type ) {
		/* translators: %d: number of collaterals. */
		$message = _n(
			'%d collateral needs page images. Open it in the editor and keep the screen open while your browser generates them.',
			'%d collaterals need page images. Open each in the editor and keep the screen open while your browser generates them.',
			$count,
			'custom-post-types-dp'
		);
	} else {
		/* translators: %d: number of documents. */
		$message = _n(
			'%d document needs page images. Open it in the editor and keep the screen open while your browser generates them.',
			'%d documents need page images. Open each in the editor and keep the screen open while your browser generates them.',
			$count,
			'custom-post-types-dp'
		);
	}

	printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( sprintf( $message, $count ) ) );
}
add_action( 'admin_notices', 'cptdp_document_pages_list_notice' );
