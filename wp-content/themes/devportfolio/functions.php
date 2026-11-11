<?php
/**
 * Theme functions. Design lives in theme.json, templates and parts.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_prepare_navigation_link' ) ) :
	/**
	 * Prepares main navigation links before render.
	 *
	 * Root-relative URLs (e.g. `/documents/`) are stored in parts/header.html so no
	 * domain is hardcoded. Core only marks a post type archive link as current when
	 * its URL equals `get_post_type_archive_link()`, so archive links are resolved
	 * from their post type (correct for any permalink structure or CPT rewrite
	 * slug), falling back to the stored path. Other links are expanded with
	 * `home_url()`. The Home link is a custom link core never marks as current, so
	 * flag it on the front page.
	 *
	 * @since 0.1.0
	 *
	 * @param array $parsed_block The block being rendered.
	 * @return array
	 */
	function devportfolio_prepare_navigation_link( $parsed_block ) {
		if (
			'core/navigation-link' !== $parsed_block['blockName']
			|| empty( $parsed_block['attrs']['url'] )
			|| ! str_starts_with( $parsed_block['attrs']['url'], '/' )
		) {
			return $parsed_block;
		}

		if ( '/' === $parsed_block['attrs']['url'] && is_front_page() ) {
			$parsed_block['attrs']['className'] = 'is-current';
		}

		$archive_link = '';
		if ( 'post-type-archive' === ( $parsed_block['attrs']['kind'] ?? '' ) && ! empty( $parsed_block['attrs']['type'] ) ) {
			$archive_link = get_post_type_archive_link( $parsed_block['attrs']['type'] );
		}

		$parsed_block['attrs']['url'] = $archive_link ? $archive_link : home_url( $parsed_block['attrs']['url'] );
		return $parsed_block;
	}
endif;
add_filter( 'render_block_data', 'devportfolio_prepare_navigation_link' );

if ( ! function_exists( 'devportfolio_register_blocks' ) ) :
	/**
	 * Registers the theme's dynamic blocks.
	 *
	 * @since 0.2.0
	 */
	function devportfolio_register_blocks() {
		register_block_type( __DIR__ . '/blocks/document-categories' );
		register_block_type( __DIR__ . '/blocks/document-book' );
		register_block_type( __DIR__ . '/blocks/document-modal' );
		register_block_type( __DIR__ . '/blocks/software-tags' );
		register_block_type( __DIR__ . '/blocks/document-shelf' );
		register_block_type( __DIR__ . '/blocks/collateral-grid' );
		register_block_type( __DIR__ . '/blocks/collateral-modal' );
	}
endif;
add_action( 'init', 'devportfolio_register_blocks' );

if ( ! function_exists( 'devportfolio_enqueue_page_styles' ) ) :
	/**
	 * Enqueue styles shared by the Documents and Collaterals archives.
	 */
	function devportfolio_enqueue_page_styles() {
		if ( ! is_post_type_archive( array( 'document', 'collateral' ) ) ) {
			return;
		}

		wp_enqueue_style(
			'devportfolio-page',
			get_theme_file_uri( 'assets/css/page.css' ),
			array(),
			(string) filemtime( __DIR__ . '/assets/css/page.css' )
		);

		// The Collaterals modal draws its book with the Documents viewer rules (its selectors are listed next to the
		// Documents ones in blocks/document-modal/style.css, DPC-012 D5). The Documents modal already loads that file.
		if ( is_post_type_archive( 'collateral' ) ) {
			wp_enqueue_style(
				'devportfolio-viewer',
				get_theme_file_uri( 'blocks/document-modal/style.css' ),
				array(),
				(string) filemtime( __DIR__ . '/blocks/document-modal/style.css' )
			);
		}
	}
endif;
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_page_styles' );

if ( ! function_exists( 'devportfolio_modal_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published single of a modal post type to its archive deeplink (DPD-032, DPC-020).
	 *
	 * @since 0.9.0
	 *
	 * @param string $post_type Post type, e.g. "document".
	 * @param string $param     Query parameter that holds the slug, e.g. "document_slug".
	 * @return string Empty when the current request is not a published single of that type.
	 */
	function devportfolio_modal_single_redirect_url( $post_type, $param ) {
		if ( ! is_singular( $post_type ) ) {
			return '';
		}

		$devportfolio_post = get_queried_object();
		if ( ! $devportfolio_post instanceof WP_Post || 'publish' !== $devportfolio_post->post_status ) {
			return '';
		}

		$devportfolio_archive = get_post_type_archive_link( $post_type );
		if ( ! $devportfolio_archive ) {
			return '';
		}

		return add_query_arg( $param, $devportfolio_post->post_name, $devportfolio_archive );
	}
endif;

if ( ! function_exists( 'devportfolio_document_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published document single to its modal deeplink.
	 *
	 * @since 0.6.0
	 *
	 * @return string Empty when the current request is not a published document single.
	 */
	function devportfolio_document_single_redirect_url() {
		return devportfolio_modal_single_redirect_url( 'document', 'document_slug' );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published collateral single to its modal deeplink (DPC-020).
	 *
	 * @since 0.9.0
	 *
	 * @return string Empty when the current request is not a published collateral single.
	 */
	function devportfolio_collateral_single_redirect_url() {
		return devportfolio_modal_single_redirect_url( 'collateral', 'collateral_slug' );
	}
endif;

if ( ! function_exists( 'devportfolio_redirect_document_single' ) ) :
	/**
	 * Redirects published document and collateral singles to their modal deeplinks (DPD-032, DPC-020).
	 *
	 * Hooked on template_redirect; the name is kept from DPD-032 so existing references still resolve.
	 *
	 * @since 0.6.0
	 */
	function devportfolio_redirect_document_single() {
		foreach ( array( 'devportfolio_document_single_redirect_url', 'devportfolio_collateral_single_redirect_url' ) as $devportfolio_resolver ) {
			$devportfolio_url = $devportfolio_resolver();
			if ( '' !== $devportfolio_url ) {
				wp_safe_redirect( $devportfolio_url, 302 );
				exit;
			}
		}
	}
endif;
add_action( 'template_redirect', 'devportfolio_redirect_document_single', 1 );

if ( ! function_exists( 'devportfolio_memo_store' ) ) :
	/**
	 * The per-request store behind devportfolio_memo(). Static, not the object cache: a persistent cache
	 * (Redis) would serve stale tool names across requests.
	 *
	 * @return array<string,mixed>
	 */
	function &devportfolio_memo_store() {
		static $store = array();

		return $store;
	}
endif;

if ( ! function_exists( 'devportfolio_memo' ) ) :
	/**
	 * Runs $build once per request for a group and post ID, then returns the same value (DPC-024, O2).
	 *
	 * Every block that reads the same tools, category, preview or term list of a post goes through here, so
	 * each read happens once however many blocks ask for it. The store is cleared by devportfolio_memo_flush()
	 * whenever a post, term or post meta changes.
	 *
	 * @param string   $group   Group name, e.g. 'software_tools'.
	 * @param int      $post_id Post ID, or 0 when the value is not per post.
	 * @param callable $build   Returns the value.
	 * @return mixed
	 */
	function devportfolio_memo( $group, $post_id, $build ) {
		$store = &devportfolio_memo_store();
		$key   = $group . '|' . (int) $post_id;
		if ( ! array_key_exists( $key, $store ) ) {
			$store[ $key ] = $build();
		}

		return $store[ $key ];
	}

	/**
	 * Empties the per-request store. Hooked to every change a memoised value can depend on.
	 */
	function devportfolio_memo_flush() {
		$store = &devportfolio_memo_store();
		$store = array();
	}
endif;

if ( ! function_exists( 'devportfolio_memo_add_hooks' ) ) :
	/**
	 * Clears the memo store whenever a post, its terms or its meta change. Adding a hook twice is a no-op, so
	 * tests may call it again after WordPress restores the hooks between tests.
	 */
	function devportfolio_memo_add_hooks() {
		foreach ( array( 'save_post', 'transition_post_status', 'delete_post', 'set_object_terms', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, 'devportfolio_memo_flush' );
		}
	}
endif;

devportfolio_memo_add_hooks();

if ( ! function_exists( 'devportfolio_published_ids' ) ) :
	/**
	 * IDs of the published posts of one type, in the order asked for, memoised per request (DPC-024, O1).
	 *
	 * The shelf, the grid and both modals list the same posts; each list is read once per request.
	 *
	 * @param string $post_type Post type.
	 * @param array  $args      Extra get_posts() arguments, e.g. orderby. Merged over the defaults.
	 * @return int[]
	 */
	function devportfolio_published_ids( $post_type, $args = array() ) {
		return devportfolio_memo(
			'published:' . $post_type . ':' . md5( serialize( $args ) ),
			0,
			static function () use ( $post_type, $args ) {
				return get_posts(
					array_merge(
						array(
							'post_type'      => $post_type,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
						),
						$args
					)
				);
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_term_slugs' ) ) :
	/**
	 * Slugs of a post's terms in one taxonomy, memoised per request (DPC-024, O2).
	 *
	 * Returns what wp_get_object_terms() returns, WP_Error included, so callers keep their is_wp_error() check.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]|WP_Error
	 */
	function devportfolio_term_slugs( $post_id, $taxonomy ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'term_slugs:' . $taxonomy,
			$post_id,
			static function () use ( $post_id, $taxonomy ) {
				return wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_cover_styles' ) ) :
	/**
	 * Cover styles of a document book (DPD-006), slug => label.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string,string>
	 */
	function devportfolio_cover_styles() {
		return array(
			'midnight'  => _x( 'Midnight', 'document cover style', 'devportfolio' ),
			'pinstripe' => _x( 'Pinstripe', 'document cover style', 'devportfolio' ),
			'horizon'   => _x( 'Horizon', 'document cover style', 'devportfolio' ),
			'split-sky' => _x( 'Split Sky', 'document cover style', 'devportfolio' ),
			'dot-grid'  => _x( 'Dot Grid', 'document cover style', 'devportfolio' ),
			'two-tone'  => _x( 'Two-Tone', 'document cover style', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_sanitize_cover_style' ) ) :
	/**
	 * Returns a valid cover style slug; unknown, empty or non-string values fall back to midnight.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function devportfolio_sanitize_cover_style( $value ) {
		if ( is_string( $value ) ) {
			$slug = strtolower( trim( $value ) );
			if ( array_key_exists( $slug, devportfolio_cover_styles() ) ) {
				return $slug;
			}
		}
		return 'midnight';
	}
endif;

if ( ! function_exists( 'devportfolio_page_source_map' ) ) :
	/**
	 * The post types whose file the modal viewer draws, and what each one stores (DPD-013, DPC-012, D6).
	 *
	 * `file` is the attachment meta key; `pages` the plugin function that reads the stored page images (never
	 * converts); `native` the plugin's native-viewer URL functions by kind. One entry per post type: a third modal
	 * is one more entry here, not a second copy of the viewer data.
	 *
	 * @since 0.7.0
	 *
	 * @return array<string,array{file:string,pages:string}>
	 */
	function devportfolio_page_source_map() {
		return array(
			'document'   => array(
				'file'  => 'cptdp_document_file',
				'pages' => 'cptdp_get_document_pages',
			),
			'collateral' => array(
				'file'  => 'cptdp_collateral_file',
				'pages' => 'cptdp_get_collateral_pages',
			),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_file_kind' ) ) :
	/**
	 * What kind of file a document points at: PDF, Office, Word or `other`.
	 *
	 * Decided by the attachment's mime type, or its extension when the host reports a generic
	 * type. Core functions only, so it works without the plugin.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Document ID.
	 * @return string
	 */
	function devportfolio_document_file_kind( $post_id ) {
		$post_id = (int) $post_id;
		$type    = get_post_type( $post_id );
		$map     = devportfolio_page_source_map();
		if ( ! isset( $map[ $type ] ) ) {
			return 'other';
		}
		$file_id = (int) get_post_meta( $post_id, $map[ $type ]['file'], true );
		if ( $file_id < 1 || 'attachment' !== get_post_type( $file_id ) ) {
			return 'other';
		}

		$by_mime = array(
			'application/pdf' => 'pdf',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
			'application/vnd.ms-powerpoint' => 'ppt',
			'application/vnd.oasis.opendocument.presentation' => 'odp',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/vnd.ms-excel' => 'xls',
			'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/msword' => 'doc',
			'application/vnd.oasis.opendocument.text' => 'odt',
		);
		$mime    = (string) get_post_mime_type( $file_id );
		if ( isset( $by_mime[ $mime ] ) ) {
			return $by_mime[ $mime ];
		}

		// A collateral may be a raster image: one page (DPC-023). A document is never an image source.
		if ( 'collateral' === $type && in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return 'image';
		}

		if ( in_array( $mime, array( 'application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2' ), true ) ) {
			$extension = strtolower( (string) pathinfo( (string) get_attached_file( $file_id ), PATHINFO_EXTENSION ) );
			if ( in_array( $extension, array( 'pptx', 'ppt', 'odp', 'xlsx', 'xls', 'ods', 'docx', 'doc', 'odt' ), true ) ) {
				return $extension;
			}
		}

		return 'other';
	}
endif;

if ( ! function_exists( 'devportfolio_document_is_word' ) ) :
	/**
	 * Whether a file kind is a word-processing document.
	 *
	 * @param string $kind File kind.
	 * @return bool
	 */
	function devportfolio_document_is_word( $kind ) {
		return in_array( $kind, array( 'docx', 'doc', 'odt' ), true );
	}
endif;

if ( ! function_exists( 'devportfolio_document_is_spreadsheet' ) ) :
	/**
	 * Whether a file kind is a spreadsheet (`xlsx`, `xls`, `ods`), converted to pages by the plugin (DPD-027).
	 *
	 * @since 0.7.0
	 *
	 * @param string $kind File kind from devportfolio_document_file_kind().
	 * @return bool
	 */
	function devportfolio_document_is_spreadsheet( $kind ) {
		return in_array( $kind, array( 'xlsx', 'xls', 'ods' ), true );
	}
endif;

if ( ! function_exists( 'devportfolio_document_unit' ) ) :
	/**
	 * The word a document's pieces are called: `slide` for presentations, `page` for everything else (DPD-026).
	 *
	 * @since 0.6.0
	 *
	 * @param string $kind File kind from devportfolio_document_file_kind().
	 * @return string `slide` or `page`.
	 */
	function devportfolio_document_unit( $kind ) {
		return in_array( $kind, array( 'pptx', 'ppt', 'odp' ), true ) ? 'slide' : 'page';
	}
endif;

if ( ! function_exists( 'devportfolio_document_unit_strings' ) ) :
	/**
	 * The modal's wording for pages and slides, for view.js (`data-units` on the block root) (DPD-026).
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,string>>
	 */
	function devportfolio_document_unit_strings() {
		return array(
			'page'  => array(
				'one'  => _x( 'Page', 'document modal unit, singular', 'devportfolio' ),
				'many' => _x( 'Pages', 'document modal unit, plural', 'devportfolio' ),
				'prev' => _x( 'Previous page', 'document modal', 'devportfolio' ),
				'next' => _x( 'Next page', 'document modal', 'devportfolio' ),
				'nav'  => _x( 'Pages', 'document modal pager', 'devportfolio' ),
			),
			'slide' => array(
				'one'  => _x( 'Slide', 'document modal unit, singular', 'devportfolio' ),
				'many' => _x( 'Slides', 'document modal unit, plural', 'devportfolio' ),
				'prev' => _x( 'Previous slide', 'document modal', 'devportfolio' ),
				'next' => _x( 'Next slide', 'document modal', 'devportfolio' ),
				'nav'  => _x( 'Slides', 'document modal pager', 'devportfolio' ),
			),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_mobile_strings' ) ) :
	/**
	 * The mobile body's wording, for mobile-viewer.js (`data-mobile-strings` on the block root) (DPD-021, DPD-024).
	 *
	 * `zoom` holds a `{n}` placeholder for the percentage.
	 *
	 * @since 0.7.0
	 *
	 * @return array<string,string>
	 */
	function devportfolio_document_mobile_strings() {
		return array(
			'region'    => _x( 'Document pages', 'document modal mobile', 'devportfolio' ),
			'hint'      => _x( 'swipe or tap edges', 'document modal mobile', 'devportfolio' ),
			'rotate'    => _x( 'Rotate for a bigger view', 'document modal mobile', 'devportfolio' ),
			'pinch'     => _x( 'Pinch to zoom', 'document modal mobile', 'devportfolio' ),
			'details'   => _x( 'Document details', 'document modal mobile', 'devportfolio' ),
			'pagesLabel' => _x( 'Pages', 'document modal mobile round button', 'devportfolio' ),
			'zoom'      => _x( 'Zoom {n}%', 'document modal mobile, {n} is a number', 'devportfolio' ),
			'zoomIn'    => _x( 'Zoom in', 'document modal mobile', 'devportfolio' ),
			'zoomOut'   => _x( 'Zoom out', 'document modal mobile', 'devportfolio' ),
			'zoomControls' => _x( 'Zoom controls', 'document modal mobile', 'devportfolio' ),
			'pageError' => _x( 'This page could not be loaded.', 'document modal mobile', 'devportfolio' ),
			// The Pages sheet (DPD-020). `{n}` is the page number; a row without a name shows the label without it.
			'pagesTitle' => _x( 'Pages', 'document modal mobile pages sheet title', 'devportfolio' ),
			'galleryView' => _x( 'Gallery view', 'document modal mobile pages sheet', 'devportfolio' ),
			'listView'  => _x( 'List view', 'document modal mobile pages sheet', 'devportfolio' ),
			'current'   => _x( 'current', 'document modal mobile pages sheet, the page on screen', 'devportfolio' ),
			'pageLabel' => _x( 'Page {n}', 'document modal mobile pages sheet, {n} is a number', 'devportfolio' ),
			'slideLabel' => _x( 'Slide {n}', 'document modal mobile pages sheet, {n} is a number', 'devportfolio' ),
			'sheetLabel' => _x( 'Sheet {n}', 'document modal mobile pages sheet, {n} is a number', 'devportfolio' ),
			'closePages' => _x( 'Close pages', 'document modal mobile pages sheet', 'devportfolio' ),
			// The Details sheet (DPD-023). Its title is the document's own; "Pages" and "Slides" reuse data-units.
			'closeDetails' => _x( 'Close details', 'document modal mobile details sheet', 'devportfolio' ),
			'formatRow' => _x( 'Format', 'document modal mobile details sheet row label', 'devportfolio' ),
			'download'  => _x( 'Download', 'document modal mobile details sheet button', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_file_type_label' ) ) :
	/**
	 * The upper-cased file type of a document, e.g. "PDF" (DPD-005, DPD-023).
	 *
	 * The one place the type is cleaned, so the cover line ("PDF · 24 pp") and the Format row of the mobile
	 * Details sheet can never disagree. Returns an empty string for a missing or non-scalar value.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $filetype File type, e.g. "pdf".
	 * @return string
	 */
	function devportfolio_document_file_type_label( $filetype ) {
		return is_scalar( $filetype ) ? strtoupper( trim( (string) $filetype ) ) : '';
	}
endif;

if ( ! function_exists( 'devportfolio_document_category_name' ) ) :
	/**
	 * The category name of a document: its first `document_category` term by name (DPD-005, DPD-023).
	 *
	 * Used by the cover and by the mobile Details sheet. Returns an empty string when the post has none.
	 * Collaterals pass their own taxonomy (DPC-006).
	 *
	 * @since 0.8.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Category taxonomy.
	 * @return string Plain text (escape on output).
	 */
	function devportfolio_document_category_name( $post_id, $taxonomy = 'document_category' ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'category_name:' . $taxonomy,
			$post_id,
			static function () use ( $post_id, $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( ! is_array( $terms ) || empty( $terms ) ) {
					return '';
				}
				$terms = array_values( $terms );
				usort(
					$terms,
					static function ( $a, $b ) {
						return strcasecmp( $a->name, $b->name );
					}
				);
				if ( function_exists( 'cptdp_get_term_singular_name' ) ) {
					return cptdp_get_term_singular_name( $terms[0] );
				}

				return $terms[0]->name;
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_format_label' ) ) :
	/**
	 * Builds the cover format line, e.g. "PDF · 24 pp" (DPD-005).
	 *
	 * The file type is upper-cased. One page reads "1 p", more "n pp"; zero,
	 * negative or non-numeric counts are left out. The parts are joined by a
	 * middle dot only when both exist. Returns plain text (escape on output).
	 *
	 * For a presentation (`pptx`, `ppt`, `odp`, see devportfolio_document_unit()) the
	 * count is in slides: "PPTX · 12 slides", "1 slide" (DPD-026).
	 *
	 * @since 0.3.0
	 *
	 * @param mixed  $filetype File type, e.g. "pdf".
	 * @param mixed  $pages    Page count.
	 * @param string $kind     Optional file kind from devportfolio_document_file_kind().
	 * @return string
	 */
	function devportfolio_document_format_label( $filetype, $pages, $kind = '' ) {
		$parts = array();

		$filetype = devportfolio_document_file_type_label( $filetype );
		if ( '' !== $filetype ) {
			$parts[] = $filetype;
		}

		$count = devportfolio_page_count_label( $pages, $kind );
		if ( '' !== $count ) {
			$parts[] = $count;
		}

		return implode( ' · ', $parts );
	}
endif;

if ( ! function_exists( 'devportfolio_page_count_label' ) ) :
	/**
	 * The page count of a file, e.g. "24 pp" or "1 p" (DPD-005), or "12 slides" / "1 slide" for a presentation (DPD-026).
	 *
	 * Shared by the document cover and modal and by the collateral pill and meta line (DPC-007, DPC-006).
	 * Zero, negative and non-numeric counts give ''. Plain text.
	 *
	 * @param mixed  $pages Page count.
	 * @param string $kind  Optional file kind from devportfolio_document_file_kind(). Presentations count slides.
	 * @return string
	 */
	function devportfolio_page_count_label( $pages, $kind = '' ) {
		$pages = ( is_int( $pages ) || ( is_string( $pages ) && ctype_digit( trim( $pages ) ) ) ) ? (int) $pages : 0;

		if ( 'slide' === devportfolio_document_unit( $kind ) ) {
			if ( 1 === $pages ) {
				return '1 ' . _x( 'slide', 'slide count, singular', 'devportfolio' );
			}

			return $pages > 1 ? $pages . ' ' . _x( 'slides', 'slide count, plural', 'devportfolio' ) : '';
		}

		if ( 1 === $pages ) {
			return '1 ' . _x( 'p', 'page count abbreviation, singular', 'devportfolio' );
		}

		return $pages > 1 ? $pages . ' ' . _x( 'pp', 'page count abbreviation, plural', 'devportfolio' ) : '';
	}
endif;

if ( ! function_exists( 'devportfolio_document_download_target' ) ) :
	/**
	 * Where the modal Download button points (DPD-012).
	 *
	 * "file" = the uploaded attachment URL (downloaded), "embed" = the embed URL
	 * (opened in a new tab), "none" = nothing to download (button shown disabled).
	 *
	 * @since 0.4.0
	 *
	 * @param int    $post_id   Document or collateral ID.
	 * @param string $file_meta Meta key of the attachment ID (`cptdp_collateral_file` for collaterals).
	 * @return array{mode:string,url:string}
	 */
	function devportfolio_document_download_target( $post_id, $file_meta = 'cptdp_document_file' ) {
		$post_id = (int) $post_id;

		$file_id = (int) get_post_meta( $post_id, $file_meta, true );
		if ( $file_id > 0 ) {
			$url = wp_get_attachment_url( $file_id );
			if ( $url ) {
				return array(
					'mode' => 'file',
					'url'  => $url,
				);
			}
		}

		$embed = (string) get_post_meta( $post_id, 'cptdp_embed_url', true );
		if ( '' !== $embed && preg_match( '#^https?://#i', $embed ) ) {
			return array(
				'mode' => 'embed',
				'url'  => $embed,
			);
		}

		return array(
			'mode' => 'none',
			'url'  => '',
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_description' ) ) :
	/**
	 * One-line plain-text description of a document (DPD-010).
	 *
	 * The excerpt when set, otherwise the first 20 words of the content.
	 *
	 * @since 0.4.0
	 *
	 * @param int $post_id Document ID.
	 * @return string
	 */
	function devportfolio_document_description( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return '';
		}

		$text = trim( (string) $post->post_excerpt );
		if ( '' === $text ) {
			$text = wp_trim_words( strip_shortcodes( (string) $post->post_content ), 20, '…' );
		}

		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	}
endif;

if ( ! function_exists( 'devportfolio_software_tool_names' ) ) :
	/**
	 * Names of the published software tools of a post, in stored order.
	 *
	 * Works for any post type that stores `cptdp_software_tools` (document,
	 * video, collateral); used by the software tags block and the document modal.
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	function devportfolio_software_tool_names( $post_id ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'software_tools',
			$post_id,
			static function () use ( $post_id ) {
				$tools    = array();
				$tool_ids = get_post_meta( $post_id, 'cptdp_software_tools', true );
				foreach ( is_array( $tool_ids ) ? $tool_ids : array() as $tool_id ) {
					if ( 'software_tool' === get_post_type( (int) $tool_id ) && 'publish' === get_post_status( (int) $tool_id ) ) {
						$tools[] = get_the_title( (int) $tool_id );
					}
				}

				return $tools;
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_shelf_item' ) ) :
	/**
	 * Data of one shelf item under the book cover (DPD-016).
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Document ID.
	 * @return array{title:string,description:string,format:string,categories:string[]}|null Null when the post is not a document.
	 */
	function devportfolio_document_shelf_item( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'document' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs = devportfolio_term_slugs( $post_id, 'document_category' );

		return array(
			'title'       => get_the_title( $post_id ),
			'description' => devportfolio_document_description( $post_id ),
			'format'      => devportfolio_document_format_label(
				get_post_meta( $post_id, 'cptdp_filetype', true ),
				get_post_meta( $post_id, 'cptdp_total_pages', true ),
				devportfolio_document_file_kind( $post_id )
			),
			'categories'  => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_modal_payload' ) ) :
	/**
	 * Data the document modal shows for one document (DPD-009 to DPD-012).
	 *
	 * Plain text and URLs only; the script writes them with textContent.
	 *
	 * @since 0.4.0
	 *
	 * @param int $post_id Document ID.
	 * @return array<string,mixed>|null Null when the post is not a document.
	 */
	function devportfolio_document_modal_payload( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'document' !== get_post_type( $post_id ) ) {
			return null;
		}

		$pages    = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );
		$download = devportfolio_document_download_target( $post_id );
		$viewer   = devportfolio_document_viewer_data( $post_id );

		return devportfolio_media_payload_base(
			$post_id,
			'document_category',
			// The script writes the title with textContent, so it must be plain text: get_the_title() returns
			// HTML entities ("&#038;" for "&").
			html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' )
		) + array(
			// The mobile Details sheet (DPD-023): the Format row.
			'fileType'     => devportfolio_document_file_type_label( get_post_meta( $post_id, 'cptdp_filetype', true ) ),
			'format'       => devportfolio_document_format_label(
				get_post_meta( $post_id, 'cptdp_filetype', true ),
				get_post_meta( $post_id, 'cptdp_total_pages', true ),
				$viewer['kind']
			),
			'kind'         => $viewer['kind'],
			'pages'        => max( 0, $pages ),
			'downloadMode' => $download['mode'],
			'downloadUrl'  => $download['url'],
			'images'       => $viewer['images'],
			'imageWidth'   => $viewer['imageWidth'],
			'cropBottom'   => $viewer['cropBottom'],
			'ratio'        => $viewer['ratio'],
			'viewerUrl'    => $viewer['viewerUrl'],
			'nativeUrl'   => $viewer['nativeUrl'],
			'pdfBytes'     => $viewer['pdfBytes'],
		) + devportfolio_document_pages_extras( $post_id );
	}
endif;

if ( ! function_exists( 'devportfolio_media_payload_base' ) ) :
	/**
	 * The keys every media payload shares, in the order the Documents modal already writes them (DPD-009, DPC-007, DPC-024).
	 *
	 * `id`, `slug` (the deeplink key: /documents/?document_slug=<slug>, DPD-032), `title`, `description`, `tools`,
	 * `category`. The download keys are not here: the Documents payload writes its own file keys (fileType, format,
	 * kind, pages) between `category` and `downloadMode`, so each payload appends its own keys after this base.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Category taxonomy: document_category or collateral_category.
	 * @param string $title    Plain-text title, decided by the caller: Documents pass the decoded get_the_title(),
	 *                         Collaterals the raw post title (wptexturize would curl its quotes).
	 * @return array<string,mixed>
	 */
	function devportfolio_media_payload_base( $post_id, $taxonomy, $title ) {
		$post_id = (int) $post_id;

		return array(
			'id'          => $post_id,
			'slug'        => (string) get_post_field( 'post_name', $post_id ),
			'title'       => $title,
			'description' => devportfolio_document_description( $post_id ),
			'tools'       => devportfolio_software_tool_names( $post_id ),
			'category'    => html_entity_decode( devportfolio_document_category_name( $post_id, $taxonomy ), ENT_QUOTES, 'UTF-8' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_name_ranges' ) ) :
	/**
	 * Packs one name per page into ranges for the payload (DPD-020): `[ [ first_page, name ], ... ]`.
	 *
	 * A name holds from its first page until the next range, so a six-chapter report is six entries. A page
	 * without a name starts a range with an empty name only when a named range came before it. Names are plain
	 * text (the script writes them with textContent), tags removed, at most 200 characters. Nothing at all
	 * (an empty array) when no page has a name.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $names One name per page, as `cptdp_get_document_pages()['names']`.
	 * @return array<int,array{0:int,1:string}>
	 */
	function devportfolio_document_name_ranges( $names ) {
		if ( ! is_array( $names ) ) {
			return array();
		}

		$ranges = array();
		$last   = '';
		$any    = false;
		foreach ( array_values( $names ) as $index => $name ) {
			$name = is_string( $name ) ? trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' ) ) ) : '';
			$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 200 ) : substr( $name, 0, 200 );
			if ( $name !== $last ) {
				$ranges[] = array( $index + 1, $name );
				$last     = $name;
			}
			if ( '' !== $name ) {
				$any = true;
			}
		}

		return $any ? $ranges : array();
	}
endif;

if ( ! function_exists( 'devportfolio_document_thumbs_descriptor' ) ) :
	/**
	 * Describes the page thumbnails for the payload (DPD-020, DPB-031).
	 *
	 * The modal payload carries every published document, so a 300-page document sends
	 * `{ base, ext, count, width, height }` (page n is `base . 't-' . pad( n, 3 ) . ext`) instead of 300 URLs.
	 * When the URLs do not follow that pattern (or there is not one per page) the plain list is sent, and an
	 * incomplete or empty set sends nothing, so a half set is never used.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $thumbs One URL per page.
	 * @param mixed $width  Thumbnail width in pixels, 0 when unknown.
	 * @param mixed $height Thumbnail height in pixels, 0 when unknown.
	 * @return array<string,mixed>|string[]
	 */
	function devportfolio_document_thumbs_descriptor( $thumbs, $width = 0, $height = 0 ) {
		if ( ! is_array( $thumbs ) || array() === $thumbs ) {
			return array();
		}

		$urls = array();
		foreach ( array_values( $thumbs ) as $url ) {
			$url = is_string( $url ) ? esc_url_raw( $url ) : '';
			if ( '' === $url ) {
				return array();
			}
			$urls[] = $url;
		}

		$ext  = '';
		$base = '';
		if ( preg_match( '#^(.*/)t-(\d{3,})(\.[a-z0-9]+)$#i', $urls[0], $match ) ) {
			$base = $match[1];
			$ext  = $match[3];
		}
		if ( '' !== $base ) {
			$follows = true;
			foreach ( $urls as $index => $url ) {
				if ( sprintf( '%s%s%03d%s', $base, 't-', $index + 1, $ext ) !== $url ) {
					$follows = false;
					break;
				}
			}
			if ( $follows ) {
				return array(
					'base'   => $base,
					'ext'    => $ext,
					'count'  => count( $urls ),
					'width'  => max( 0, (int) $width ),
					'height' => max( 0, (int) $height ),
				);
			}
		}

		return $urls;
	}
endif;

if ( ! function_exists( 'devportfolio_document_pages_extras' ) ) :
	/**
	 * The payload keys the mobile Pages sheet adds (DPD-020): `pageNames` (ranges) and `thumbs` (descriptor).
	 *
	 * Both come from the plugin's page store through a function_exists guard (DPB-030, DPB-031), and a key is
	 * left out entirely when the document has nothing for it (no names, no thumbnails, plugin missing), so the
	 * payload that carries every published document stays small and the sheet falls back to numbered tiles and
	 * the unit word.
	 *
	 * @since 0.8.0
	 *
	 * @param int $post_id Document ID.
	 * @return array<string,mixed>
	 */
	function devportfolio_document_pages_extras( $post_id ) {
		$map = devportfolio_page_source_map();
		$fn  = isset( $map[ get_post_type( (int) $post_id ) ] ) ? $map[ get_post_type( (int) $post_id ) ]['pages'] : '';
		if ( '' === $fn || ! function_exists( $fn ) ) {
			return array();
		}
		$stored = call_user_func( $fn, (int) $post_id );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$extras = array();
		$names  = devportfolio_document_name_ranges( $stored['names'] ?? array() );
		if ( array() !== $names ) {
			$extras['pageNames'] = $names;
		}
		$thumbs = devportfolio_document_thumbs_descriptor( $stored['thumbs'] ?? array(), $stored['thumb_width'] ?? 0, $stored['thumb_height'] ?? 0 );
		if ( array() !== $thumbs ) {
			$extras['thumbs'] = $thumbs;
		}

		return $extras;
	}
endif;

if ( ! function_exists( 'devportfolio_document_viewer_data' ) ) :
	/**
	 * What the modal viewer needs to show a document's pages (DPD-013).
	 *
	 * Reads the plugin's page image store (DPB-025) through function_exists
	 * guards, so the theme works, and the viewer falls back to rendering the
	 * PDF in the browser, when the plugin API is missing. Never converts.
	 *
	 * - images:     URLs of the stored page images, empty unless they are valid.
	 * - imageWidth: pixel width of one stored image (0 when unknown).
	 * - cropBottom: share of each page's height (0 to 0.25) that is empty at the
	 *               bottom and that the viewer hides; 0 when nothing is trimmed.
	 * - ratio:      page width / visible height, already accounting for cropBottom
	 *               (0 when unknown).
	 * - viewerUrl:  URL of the PDF the browser can render, empty for other files.
	 * - pdfBytes:   size of the PDF file (0 when unknown), used by the render gate.
	 * - kind:       `pdf`, `pptx`, `ppt`, `odp` or `other` (DPD-026).
	 * - nativeUrl: URL of a `.pptx` the visitor's browser should draw itself with the bundled
	 *               native viewer (selectable text, from the original file), only when
	 *               Settings > Documents > Native viewer > PowerPoint is on (DPB-026), else ''.
	 *               It takes over from the converted slides, which stay as its fallback.
	 *
	 * Presentations have no `viewerUrl` (pdf.js cannot read them); their stored page images
	 * come from the plugin like a PDF's (converted slides).
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Document ID.
	 * @return array{images:string[],imageWidth:int,cropBottom:float,ratio:float,viewerUrl:string,nativeUrl:string,pdfBytes:int,kind:string}
	 */
	function devportfolio_document_viewer_data( $post_id ) {
		$post_id = (int) $post_id;
		$map     = devportfolio_page_source_map();
		$type    = get_post_type( $post_id );
		$data    = array(
			'images'     => array(),
			'imageWidth' => 0,
			'cropBottom' => 0.0,
			'ratio'      => 0.0,
			'viewerUrl'  => '',
			'nativeUrl' => '',
			'pdfBytes'   => 0,
			'kind'       => 'other',
		);

		if ( ! isset( $map[ $type ] ) ) {
			return $data;
		}

		$data['kind'] = devportfolio_document_file_kind( $post_id );
		$file_id      = (int) get_post_meta( $post_id, $map[ $type ]['file'], true );
		$stored_fn    = $map[ $type ]['pages'];

		// A presentation or a spreadsheet (DPD-026, DPD-027): its converted pages (the default view, and the native
		// viewer's fallback), plus the native viewer's address when the plugin allows it; never a PDF for pdf.js.
		$native_url = array(
			'pptx' => 'cptdp_document_native_pptx_url',
			'xlsx' => 'cptdp_document_native_xlsx_url',
			'docx' => 'cptdp_document_native_docx_url',
		);
		if ( 'slide' === devportfolio_document_unit( $data['kind'] ) || devportfolio_document_is_spreadsheet( $data['kind'] ) || devportfolio_document_is_word( $data['kind'] ) || 'image' === $data['kind'] ) {
			if ( function_exists( $stored_fn ) ) {
				$data = array_merge( $data, devportfolio_document_normalise_stored_pages( call_user_func( $stored_fn, $post_id ) ) );
			}
			if ( isset( $native_url[ $data['kind'] ] ) && function_exists( $native_url[ $data['kind'] ] ) ) {
				$native            = call_user_func( $native_url[ $data['kind'] ], $post_id );
				$data['nativeUrl'] = is_string( $native ) ? esc_url_raw( $native ) : '';
			}

			return $data;
		}

		if ( function_exists( 'cptdp_document_pdf_url' ) ) {
			$data['viewerUrl'] = (string) cptdp_document_pdf_url( $post_id );
		} elseif ( $file_id > 0 && 'application/pdf' === get_post_mime_type( $file_id ) ) {
			$data['viewerUrl'] = (string) wp_get_attachment_url( $file_id );
		}

		if ( '' === $data['viewerUrl'] ) {
			// Not a PDF: nothing to show in the viewer, whatever else is stored.
			return $data;
		}

		$path = $file_id > 0 ? get_attached_file( $file_id ) : false;
		if ( $path && is_readable( $path ) ) {
			$data['pdfBytes'] = (int) filesize( $path );
		}

		if ( function_exists( $stored_fn ) ) {
			$data = array_merge( $data, devportfolio_document_normalise_stored_pages( call_user_func( $stored_fn, $post_id ) ) );
		}

		return $data;
	}
endif;

if ( ! function_exists( 'devportfolio_document_normalise_stored_pages' ) ) :
	/**
	 * Turns the plugin's stored-pages answer into the viewer's payload keys (DPD-013).
	 *
	 * Anything that is not a non-empty list of page URLs gives no images, so a
	 * missing, invalid or malformed answer sends the viewer to its fallback.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $stored What cptdp_get_document_pages() returned: array or null.
	 * @return array{images:string[],imageWidth:int,cropBottom:float,ratio:float}
	 */
	function devportfolio_document_normalise_stored_pages( $stored ) {
		$none = array(
			'images'     => array(),
			'imageWidth' => 0,
			'cropBottom' => 0.0,
			'ratio'      => 0.0,
		);
		if ( ! is_array( $stored ) || empty( $stored['pages'] ) || ! is_array( $stored['pages'] ) ) {
			return $none;
		}

		$images = array();
		foreach ( $stored['pages'] as $url ) {
			$url = is_string( $url ) ? esc_url_raw( $url ) : '';
			if ( '' === $url ) {
				// One bad URL would leave a hole in the book: use none of them.
				return $none;
			}
			$images[] = $url;
		}

		// A crop outside 0 to 0.25 is not trusted: show the whole page.
		$crop = (float) ( $stored['crop_bottom'] ?? 0 );
		$crop = ( $crop > 0 && $crop <= 0.25 ) ? round( $crop, 4 ) : 0.0;

		return array(
			'images'     => $images,
			'imageWidth' => max( 0, (int) ( $stored['width'] ?? 0 ) ),
			'cropBottom' => $crop,
			'ratio'      => max( 0.0, round( (float) ( $stored['ratio'] ?? 0 ), 4 ) ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_document_selectable_text' ) ) :
	/**
	 * Whether visitors may select and copy the text of document pages.
	 *
	 * Settings > Documents > Native viewer > PDF in the plugin (`native_pdf`, off by default; it was
	 * called "Selectable text"). On, the viewer draws pages with pdf.js and a real text layer
	 * instead of the stored images.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	function devportfolio_document_selectable_text() {
		return function_exists( 'cptdp_get_documents_settings' ) && (bool) cptdp_get_documents_settings( 'native_pdf' );
	}
endif;

if ( ! function_exists( 'devportfolio_document_viewer_limits' ) ) :
	/**
	 * Overrides for the viewer's client render gate (DPD-013).
	 *
	 * The defaults live in blocks/shared/capability.js (DEFAULT_LIMITS);
	 * this returns only what a site wants to change, e.g. a lower page cap:
	 *
	 *     add_filter( 'devportfolio_document_viewer_limits', function ( $limits ) {
	 *         $limits['maxPages'] = array( 'mobile' => 30, 'desktop' => 200 );
	 *         return $limits;
	 *     } );
	 *
	 * @since 0.5.0
	 *
	 * @return array<string,mixed>
	 */
	function devportfolio_document_viewer_limits() {
		$limits = apply_filters( 'devportfolio_document_viewer_limits', array() );
		return is_array( $limits ) ? $limits : array();
	}
endif;

if ( ! function_exists( 'devportfolio_document_pdfjs_urls' ) ) :
	/**
	 * URLs of the pdf.js build the plugin ships (DPB-025), for the viewer's fallback.
	 *
	 * @since 0.5.0
	 *
	 * @return array{module:string,worker:string} Empty strings when the plugin does not provide them.
	 */
	function devportfolio_document_pdfjs_urls() {
		$urls = array(
			'module' => '',
			'worker' => '',
		);
		if ( function_exists( 'cptdp_document_pdfjs_urls' ) ) {
			$found = cptdp_document_pdfjs_urls();
			if ( is_array( $found ) ) {
				$urls['module'] = isset( $found['module'] ) ? esc_url_raw( (string) $found['module'] ) : '';
				$urls['worker'] = isset( $found['worker'] ) ? esc_url_raw( (string) $found['worker'] ) : '';
			}
		}
		return $urls;
	}
endif;

if ( ! function_exists( 'devportfolio_document_title_length' ) ) :
	/**
	 * Size bucket of a document title for the book cover: "short", "long" or "xlong".
	 *
	 * The cover shrinks the title type for longer titles so they show in full
	 * instead of being clipped (the modal always shows the whole title).
	 *
	 * @since 0.4.0
	 *
	 * @param string $title Plain-text title.
	 * @return string
	 */
	function devportfolio_document_title_length( $title ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( trim( (string) $title ) ) : strlen( trim( (string) $title ) );
		if ( $length > 70 ) {
			return 'xlong';
		}
		return $length > 40 ? 'long' : 'short';
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_meta_line' ) ) :
	/**
	 * The detail line of a collateral item (DPC-006): category, paper size and page count, joined by " · ".
	 *
	 * A missing part is left out ("1 p" for one page), and the line is '' when all three are missing.
	 * Plain text (escape on output).
	 *
	 * @param int $post_id Collateral ID.
	 * @return string
	 */
	function devportfolio_collateral_meta_line( $post_id ) {
		$post_id = (int) $post_id;
		$parts   = array_filter(
			array(
				devportfolio_document_category_name( $post_id, 'collateral_category' ),
				trim( (string) get_post_meta( $post_id, 'cptdp_paper_size', true ) ),
				devportfolio_page_count_label( get_post_meta( $post_id, 'cptdp_total_pages', true ) ),
			),
			'strlen'
		);

		return implode( ' · ', $parts );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_preview_from' ) ) :
	/**
	 * Chooses what a collateral tile shows, from already-read data (DPC-006).
	 *
	 * In order: page 1 of a valid page set (its thumbnail, with page 1 as the larger candidate), the featured
	 * image, then the numbered blank page. A `pending` page set with neither becomes `loading` instead of the
	 * blank, while every other state keeps the blank (DPC-025). The tile ratio is the uncropped page ratio
	 * (DPC-014 D7); the placeholder is A4 (portrait), or landscape when the paper size says so.
	 *
	 * Pure except for wp_get_attachment_image_src() when a featured image is given.
	 *
	 * @param array|null $stored     Result of cptdp_get_collateral_pages(), or null.
	 * @param int        $featured_id Featured image attachment ID, 0 for none.
	 * @param string     $paper_size  Paper size, e.g. "A4 Landscape".
	 * @param string     $state      Page set status (DPC-025): none, pending, ready, needs_browser or failed.
	 * @return array{kind:string,src:string,srcset:string,width:int,height:int,ratio:float,featured_id:int}
	 */
	function devportfolio_collateral_preview_from( $stored, int $featured_id, string $paper_size, string $state = '' ) {
		if ( is_array( $stored ) ) {
			$page   = $stored['pages'][0] ?? '';
			$width  = (int) ( $stored['width'] ?? 0 );
			$height = (int) ( $stored['height'] ?? 0 );

			if ( is_string( $page ) && '' !== $page && $width > 0 && $height > 0 ) {
				$thumb     = ( isset( $stored['thumbs'] ) && is_array( $stored['thumbs'] ) ) ? ( $stored['thumbs'][0] ?? '' ) : '';
				$thumb_w   = (int) ( $stored['thumb_width'] ?? 0 );
				$has_thumb = is_string( $thumb ) && '' !== $thumb && $thumb_w > 0;

				return array(
					'kind'        => 'page',
					'src'         => $has_thumb ? $thumb : $page,
					'srcset'      => $has_thumb ? $thumb . ' ' . $thumb_w . 'w, ' . $page . ' ' . $width . 'w' : $page . ' ' . $width . 'w',
					'width'       => $width,
					'height'      => $height,
					'ratio'       => round( $width / $height, 4 ),
					'featured_id' => 0,
				);
			}
		}

		if ( $featured_id > 0 ) {
			$image = wp_get_attachment_image_src( $featured_id, 'full' );
			if ( is_array( $image ) && (int) $image[1] > 0 && (int) $image[2] > 0 ) {
				return array(
					'kind'        => 'featured',
					'src'         => '',
					'srcset'      => '',
					'width'       => (int) $image[1],
					'height'      => (int) $image[2],
					'ratio'       => round( (int) $image[1] / (int) $image[2], 4 ),
					'featured_id' => $featured_id,
				);
			}
		}

		$landscape = false !== stripos( $paper_size, 'landscape' );

		return array(
			'kind'        => 'pending' === $state ? 'loading' : 'blank',
			'src'         => '',
			'srcset'      => '',
			'width'       => 0,
			'height'      => 0,
			'ratio'       => round( $landscape ? M_SQRT2 : 1 / M_SQRT2, 4 ),
			'featured_id' => 0,
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_preview' ) ) :
	/**
	 * The preview of a collateral, read from its stored page images, featured image, paper size and status.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array{kind:string,src:string,srcset:string,width:int,height:int,ratio:float,featured_id:int}
	 */
	function devportfolio_collateral_preview( $post_id ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'collateral_preview',
			$post_id,
			static function () use ( $post_id ) {
				$stored = function_exists( 'cptdp_get_collateral_pages' ) ? cptdp_get_collateral_pages( $post_id ) : null;
				$state  = function_exists( 'cptdp_get_collateral_pages_status' ) ? cptdp_get_collateral_pages_status( $post_id ) : '';

				return devportfolio_collateral_preview_from(
					$stored,
					(int) get_post_thumbnail_id( $post_id ),
					(string) get_post_meta( $post_id, 'cptdp_paper_size', true ),
					(string) $state
				);
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_item' ) ) :
	/**
	 * Everything the collateral grid shows for one collateral (DPC-006).
	 *
	 * @param int $post_id Collateral ID.
	 * @return array{title:string,meta:string,categories:string[],preview:array}|null Null when the post is not a collateral.
	 */
	function devportfolio_collateral_item( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'collateral' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs = devportfolio_term_slugs( $post_id, 'collateral_category' );

		return array(
			'title'      => get_the_title( $post_id ),
			'meta'       => devportfolio_collateral_meta_line( $post_id ),
			'categories' => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'preview'    => devportfolio_collateral_preview( $post_id ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_pill' ) ) :
	/**
	 * The paper size and page count pill of the collateral sidebar, e.g. "A4 · 2 pp" (DPC-007).
	 *
	 * A missing part is left out ("A4", "2 pp"); '' when both are missing. Plain text.
	 *
	 * @param mixed $paper_size Paper size, e.g. "A4".
	 * @param mixed $pages      Page count.
	 * @return string
	 */
	function devportfolio_collateral_pill( $paper_size, $pages ) {
		$parts = array_filter(
			array(
				is_scalar( $paper_size ) ? trim( (string) $paper_size ) : '',
				devportfolio_page_count_label( $pages ),
			),
			'strlen'
		);

		return implode( ' · ', $parts );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_stats' ) ) :
	/**
	 * The Results rows of a collateral: `cptdp_post_statistics`, in saved order (DPC-008).
	 *
	 * Only well-formed rows are kept; a row with both label and value empty is dropped. Plain text.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array<int,array{label:string,value:string}>
	 */
	function devportfolio_collateral_stats( $post_id ) {
		$rows = get_post_meta( (int) $post_id, 'cptdp_post_statistics', true );
		$text = static function ( $row, $key ) {
			return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? trim( wp_strip_all_tags( (string) $row[ $key ] ) ) : '';
		};

		$stats = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = $text( $row, 'label' );
			$value = $text( $row, 'value' );
			if ( '' === $label && '' === $value ) {
				continue;
			}
			$stats[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $stats;
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_thumb' ) ) :
	/**
	 * The thumbnail of a Next collaterals item, from a collateral preview (DPC-010).
	 *
	 * A stored page uses its thumbnail set; a featured image its medium size (the full size when there is none);
	 * a pending page set uses the loading image (DPC-025); a blank page has no `src`, so the script draws the
	 * numbered blank.
	 *
	 * @param array $preview Result of devportfolio_collateral_preview().
	 * @return array{src:string,srcset:string,ratio:float}
	 */
	function devportfolio_collateral_thumb( array $preview ) {
		$kind   = $preview['kind'] ?? 'blank';
		$src    = '';
		$srcset = '';

		if ( 'page' === $kind ) {
			$src    = (string) $preview['src'];
			$srcset = (string) $preview['srcset'];
		} elseif ( 'featured' === $kind ) {
			$src = (string) wp_get_attachment_image_url( (int) $preview['featured_id'], 'medium' );
		} elseif ( 'loading' === $kind && function_exists( 'cptdp_collateral_loading_image_url' ) ) {
			$src = cptdp_collateral_loading_image_url();
		}

		return array(
			'src'    => $src,
			'srcset' => $srcset,
			'ratio'  => (float) ( $preview['ratio'] ?? 0 ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_modal_payload' ) ) :
	/**
	 * Everything the collateral modal sidebar and Next list need for one collateral (DPC-007 to DPC-011, D5).
	 *
	 * Plain text and URLs only, written with textContent by the script. Null when the post is not a collateral.
	 * The render passes published collaterals only.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array<string,mixed>|null
	 */
	function devportfolio_collateral_modal_payload( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'collateral' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs    = devportfolio_term_slugs( $post_id, 'collateral_category' );
		$download = devportfolio_document_download_target( $post_id, 'cptdp_collateral_file' );
		$viewer   = devportfolio_document_viewer_data( $post_id );

		// The file type: the stored one, else the extension of the attached file (a collateral has no filetype field).
		$filetype = (string) get_post_meta( $post_id, 'cptdp_filetype', true );
		$file_id  = (int) get_post_meta( $post_id, 'cptdp_collateral_file', true );
		if ( '' === $filetype && $file_id > 0 ) {
			$filetype = strtolower( (string) pathinfo( (string) get_attached_file( $file_id ), PATHINFO_EXTENSION ) );
		}
		$total = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );

		// What the viewer can show (DPC-025): a real preview, one on its way, or nothing.
		$page_status = function_exists( 'cptdp_get_collateral_pages_status' ) ? cptdp_get_collateral_pages_status( $post_id ) : '';
		if ( ! empty( $viewer['images'] ) || '' !== $viewer['viewerUrl'] ) {
			$preview_state = 'ready';
		} elseif ( 'pending' === $page_status ) {
			$preview_state = 'pending';
		} else {
			$preview_state = 'unavailable';
		}

		return devportfolio_media_payload_base(
			$post_id,
			'collateral_category',
			// The raw title: get_the_title() runs wptexturize, which would curl the quotes in the payload.
			html_entity_decode( (string) get_post_field( 'post_title', $post_id ), ENT_QUOTES, 'UTF-8' )
		) + array(
			'categories'   => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'pill'         => devportfolio_collateral_pill(
				get_post_meta( $post_id, 'cptdp_paper_size', true ),
				get_post_meta( $post_id, 'cptdp_total_pages', true )
			),
			'stats'        => devportfolio_collateral_stats( $post_id ),
			// The viewer (DPC-012): the same keys as a document (D6). The stored pages win over the typed count.
			'fileType'     => devportfolio_document_file_type_label( $filetype ),
			'format'       => devportfolio_document_format_label( $filetype, $total, $viewer['kind'] ),
			'kind'         => $viewer['kind'],
			'pages'        => count( $viewer['images'] ) > 0 ? count( $viewer['images'] ) : max( 0, $total ),
			'downloadMode' => $download['mode'],
			'downloadUrl'  => $download['url'],
			'images'       => $viewer['images'],
			'imageWidth'   => $viewer['imageWidth'],
			'cropBottom'   => $viewer['cropBottom'],
			'ratio'        => $viewer['ratio'],
			'viewerUrl'    => $viewer['viewerUrl'],
			'nativeUrl'    => $viewer['nativeUrl'],
			'pdfBytes'     => $viewer['pdfBytes'],
			'previewState' => $preview_state,
			'thumb'        => devportfolio_collateral_thumb( devportfolio_collateral_preview( $post_id ) ),
		) + devportfolio_document_pages_extras( $post_id );
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_arrow' ) ) :
	/**
	 * One round page arrow of a book viewer (DPD-014, DPC-012): the markup both modals share.
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix Modal class prefix: 'documents-modal' or 'collaterals-modal'.
	 * @param string $dir    'prev' or 'next'.
	 * @param string $label  The accessible name.
	 * @return string
	 */
	function devportfolio_viewer_arrow( $prefix, $dir, $label ) {
		$path = 'prev' === $dir ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7';
		return sprintf(
			'<button class="%1$s__arrow %1$s__arrow--%2$s" type="button" aria-label="%3$s"><svg class="%1$s__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%4$s"/></svg></button>',
			esc_attr( $prefix ),
			esc_attr( $dir ),
			esc_attr( $label ),
			esc_attr( $path )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_status_strings' ) ) :
	/**
	 * The strings of a viewer's status line, translated for one modal (DPD-013, DPC-012).
	 *
	 * @since 0.7.0
	 *
	 * @param string $modal 'document' or 'collateral' (the noun in the wording and the translation context).
	 * @return array<string,string>
	 */
	function devportfolio_viewer_status_strings( $modal ) {
		if ( 'collateral' === $modal ) {
			return array(
				'loading'    => _x( 'Loading pages…', 'collateral modal viewer', 'devportfolio' ),
				'unavailable' => _x( 'This collateral can’t be previewed.', 'collateral modal viewer', 'devportfolio' ),
				'pending'    => _x( 'Preview is being prepared. You can still download the file.', 'collateral modal viewer', 'devportfolio' ),
				'hint'       => _x( 'Use Download to open it.', 'collateral modal viewer', 'devportfolio' ),
				'hintSheet'  => _x( 'Spreadsheets can’t always be previewed here. Download the file to open it in Excel.', 'collateral modal viewer', 'devportfolio' ),
				'hintSlide'  => _x( 'Presentations can’t always be previewed here. Download the file to open it in PowerPoint.', 'collateral modal viewer', 'devportfolio' ),
				'hintDoc'    => _x( 'Documents can’t always be previewed here. Download the file to open it in Word.', 'collateral modal viewer', 'devportfolio' ),
				'loadingImage' => function_exists( 'cptdp_collateral_loading_image_url' ) ? cptdp_collateral_loading_image_url() : '',
			);
		}
		return array(
			'loading'     => _x( 'Loading pages…', 'document modal viewer', 'devportfolio' ),
			'unavailable' => _x( 'This document can’t be previewed.', 'document modal viewer', 'devportfolio' ),
			'hint'        => _x( 'Use Download to open it.', 'document modal viewer', 'devportfolio' ),
			'hintSheet'   => _x( 'Spreadsheets can’t always be previewed here. Download the file to open it in Excel.', 'document modal viewer', 'devportfolio' ),
			'hintSlide'   => _x( 'Presentations can’t always be previewed here. Download the file to open it in PowerPoint.', 'document modal viewer', 'devportfolio' ),
			'hintDoc'     => _x( 'Documents can’t always be previewed here. Download the file to open it in Word.', 'document modal viewer', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_status' ) ) :
	/**
	 * The loading / "can't be previewed" line inside a viewer stage (`role="status"`, DPD-013, DPC-012).
	 *
	 * The children take the modal's prefix (`<prefix>__spinner`, `__state-text`, `__state-hint`); `$extra` adds
	 * classes to the status element (the mobile dialog's own `<prefix-mobile>__state`).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix  Modal class prefix: 'documents-modal' or 'collaterals-modal'.
	 * @param array  $strings The viewer strings (devportfolio_viewer_status_strings()).
	 * @param string $extra   Extra classes for the status element.
	 * @return string
	 */
	function devportfolio_viewer_status( $prefix, array $strings, $extra = '' ) {
		$attr = static function ( $name, $key ) use ( $strings ) {
			$value = (string) ( $strings[ $key ] ?? '' );
			return '' !== $value ? sprintf( ' %s="%s"', $name, esc_attr( $value ) ) : '';
		};
		return sprintf(
			'<div class="%1$s__state%2$s" role="status" hidden%3$s%4$s%5$s%6$s%7$s%8$s%9$s%10$s><span class="%1$s__spinner" aria-hidden="true"></span><img class="%1$s__state-image" alt="" hidden><span class="%1$s__state-text"></span><span class="%1$s__state-hint"></span></div>',
			esc_attr( $prefix ),
			'' !== $extra ? ' ' . esc_attr( $extra ) : '',
			$attr( 'data-loading', 'loading' ),
			$attr( 'data-unavailable', 'unavailable' ),
			$attr( 'data-pending', 'pending' ),
			$attr( 'data-hint', 'hint' ),
			$attr( 'data-hint-sheet', 'hintSheet' ),
			$attr( 'data-hint-slide', 'hintSlide' ),
			$attr( 'data-hint-doc', 'hintDoc' ),
			$attr( 'data-loading-image', 'loadingImage' )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_footer' ) ) :
	/**
	 * The desktop footer under a book viewer: the pager and the progress line (DPD-013, DPC-013).
	 *
	 * Hidden until the script has more than one page; the pager's accessible name comes from the script.
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix Modal class prefix.
	 * @param string $label  The pager's accessible name (fallback until the unit words arrive).
	 * @param string $bar    The progress line's accessible name.
	 * @return string
	 */
	function devportfolio_viewer_footer( $prefix, $label, $bar ) {
		return sprintf(
			'<footer class="%1$s__footer" hidden><nav class="%1$s__pager" aria-label="%2$s" hidden></nav><div class="%1$s__indicator" hidden><div class="%1$s__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="%3$s"><i></i></div></div></footer>',
			esc_attr( $prefix ),
			esc_attr( $label ),
			esc_attr( $bar )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_mobile_header' ) ) :
	/**
	 * The header row of a mobile viewer dialog: outline Close, the title (truncated) and outline Download (DPD-015, DPC-015).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix         Mobile class prefix: 'documents-mobile' or 'collaterals-mobile'.
	 * @param string $view_prefix    Icon class prefix: 'documents-modal' or 'collaterals-modal'.
	 * @param string $title_id      Id of the title (the dialog's accessible name).
	 * @param string $close_label    Accessible name of Close.
	 * @param string $download_label Accessible name of Download.
	 * @return string
	 */
	function devportfolio_viewer_mobile_header( $prefix, $view_prefix, $title_id, $close_label, $download_label ) {
		return sprintf(
			'<header class="%1$s__header"><button class="%1$s__close %1$s__btn" type="button" aria-label="%2$s">%3$s</button><h2 class="%1$s__title" id="%4$s"></h2><a class="%1$s__download %1$s__btn" role="button" aria-label="%5$s">%6$s</a></header>',
			esc_attr( $prefix ),
			esc_attr( $close_label ),
			devportfolio_viewer_icon( $view_prefix, 'M6 6l12 12M18 6L6 18' ),
			esc_attr( $title_id ),
			esc_attr( $download_label ),
			devportfolio_viewer_icon( $view_prefix, 'M12 4v11M7 11l5 5 5-5M5 20h14' )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_icon' ) ) :
	/**
	 * One decorative 24px stroke icon of a viewer (the class is the viewer's icon class).
	 *
	 * @since 0.7.0
	 *
	 * @param string $view_prefix Icon class prefix ('documents-modal' or 'collaterals-modal').
	 * @param string $path        SVG path data.
	 * @return string
	 */
	function devportfolio_viewer_icon( $view_prefix, $path ) {
		return sprintf(
			'<svg class="%1$s__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%2$s"/></svg>',
			esc_attr( $view_prefix ),
			esc_attr( $path )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_mobile_body' ) ) :
	/**
	 * The body of a mobile viewer dialog: the stage the viewer builds, the status line and the progress bar (DPD-021, DPC-019).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix      Mobile class prefix.
	 * @param string $view_prefix Viewer class prefix (the status children).
	 * @param array  $strings     The viewer status strings (devportfolio_viewer_status_strings()).
	 * @param string $bar         The progress bar's accessible name.
	 * @return string
	 */
	function devportfolio_viewer_mobile_body( $prefix, $view_prefix, array $strings, $bar ) {
		return sprintf(
			'<div class="%1$s__body"><div class="%1$s__stage"></div>%2$s<div class="%1$s__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="" aria-label="%3$s" hidden><i></i></div></div>',
			esc_attr( $prefix ),
			devportfolio_viewer_status( $view_prefix, $strings, $prefix . '__state' ),
			esc_attr( $bar )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_mobile_footer' ) ) :
	/**
	 * The footer row of a mobile viewer dialog: Pages, the position (filled by the script) and Details (DPD-016, DPC-016).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix      Mobile class prefix.
	 * @param string $view_prefix Icon class prefix.
	 * @param string $pages       Visible label of the Pages button.
	 * @param string $details     Visible label of the Details button.
	 * @return string
	 */
	function devportfolio_viewer_mobile_footer( $prefix, $view_prefix, $pages, $details ) {
		$grid = sprintf(
			'<svg class="%s__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>',
			esc_attr( $view_prefix )
		);
		return sprintf(
			'<footer class="%1$s__footer"><div class="%1$s__row"><button class="%1$s__pages %1$s__textbtn" type="button">%2$s<span>%3$s</span></button><b class="%1$s__position"></b><button class="%1$s__details %1$s__textbtn" type="button"><span>%4$s</span>%5$s</button></div></footer>',
			esc_attr( $prefix ),
			$grid,
			esc_html( $pages ),
			esc_html( $details ),
			devportfolio_viewer_icon( $view_prefix, 'M6 9l6 6 6-6' )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_mobile_strings' ) ) :
	/**
	 * The mobile viewer's and the mobile Details sheet's words for a collateral (`data-mobile-strings`, DPC-015 to DPC-019).
	 *
	 * The shared mobile body words (devportfolio_document_mobile_strings()) with the collateral's own names where
	 * they differ: the region, the round Details button, and the sidebar's Copy link wording for the sheet.
	 *
	 * @return array<string,string>
	 */
	function devportfolio_collateral_mobile_strings() {
		return array_merge(
			devportfolio_document_mobile_strings(),
			array(
				'region'    => _x( 'Collateral pages', 'collateral modal mobile', 'devportfolio' ),
				'details'   => _x( 'Collateral details', 'collateral modal mobile', 'devportfolio' ),
				'results'   => _x( 'Results', 'collateral modal mobile details sheet', 'devportfolio' ),
				'madeWith'  => _x( 'Made with', 'collateral modal mobile details sheet', 'devportfolio' ),
				'copyLink'  => _x( 'Copy link', 'collateral modal mobile details sheet button', 'devportfolio' ),
				'copied'    => _x( 'Copied', 'collateral modal mobile details sheet', 'devportfolio' ),
				'copyHint'  => _x( 'Press Ctrl/⌘ + C', 'collateral modal mobile details sheet', 'devportfolio' ),
			)
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_modal_strings' ) ) :
	/**
	 * The few strings the collateral modal script writes, as `data-strings` on its root (no English in JS).
	 *
	 * @return array<string,string>
	 */
	function devportfolio_collateral_modal_strings() {
		return array(
			/* translators: %s: collateral title. */
			'open'     => _x( 'Open %s', 'collateral modal', 'devportfolio' ),
			'copied'   => _x( 'Copied', 'collateral modal', 'devportfolio' ),
			'copyHint' => _x( 'Press Ctrl/⌘ + C', 'collateral modal', 'devportfolio' ),
		);
	}
endif;
