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
	}
endif;
add_action( 'init', 'devportfolio_register_blocks' );

if ( ! function_exists( 'devportfolio_document_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published document single to its modal deeplink.
	 *
	 * @since 0.6.0
	 *
	 * @return string Empty when the current request is not a published document single.
	 */
	function devportfolio_document_single_redirect_url() {
		if ( ! is_singular( 'document' ) ) {
			return '';
		}

		$devportfolio_post = get_queried_object();
		if ( ! $devportfolio_post instanceof WP_Post || 'publish' !== $devportfolio_post->post_status ) {
			return '';
		}

		$devportfolio_archive = get_post_type_archive_link( 'document' );
		if ( ! $devportfolio_archive ) {
			return '';
		}

		return add_query_arg( 'document_slug', $devportfolio_post->post_name, $devportfolio_archive );
	}
endif;

if ( ! function_exists( 'devportfolio_redirect_document_single' ) ) :
	/**
	 * Redirect published document singles to the document modal deeplink.
	 *
	 * @since 0.6.0
	 */
	function devportfolio_redirect_document_single() {
		$devportfolio_url = devportfolio_document_single_redirect_url();
		if ( '' === $devportfolio_url ) {
			return;
		}

		wp_safe_redirect( $devportfolio_url, 302 );
		exit;
	}
endif;
add_action( 'template_redirect', 'devportfolio_redirect_document_single', 1 );

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
		$file_id = (int) get_post_meta( (int) $post_id, 'cptdp_document_file', true );
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

		$filetype = is_scalar( $filetype ) ? strtoupper( trim( (string) $filetype ) ) : '';
		if ( '' !== $filetype ) {
			$parts[] = $filetype;
		}

		$pages = ( is_int( $pages ) || ( is_string( $pages ) && ctype_digit( trim( $pages ) ) ) ) ? (int) $pages : 0;
		if ( 'slide' === devportfolio_document_unit( $kind ) ) {
			if ( 1 === $pages ) {
				$parts[] = '1 ' . _x( 'slide', 'slide count, singular', 'devportfolio' );
			} elseif ( $pages > 1 ) {
				$parts[] = $pages . ' ' . _x( 'slides', 'slide count, plural', 'devportfolio' );
			}
		} elseif ( 1 === $pages ) {
			$parts[] = '1 ' . _x( 'p', 'page count abbreviation, singular', 'devportfolio' );
		} elseif ( $pages > 1 ) {
			$parts[] = $pages . ' ' . _x( 'pp', 'page count abbreviation, plural', 'devportfolio' );
		}

		return implode( ' · ', $parts );
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
	 * @param int $post_id Document ID.
	 * @return array{mode:string,url:string}
	 */
	function devportfolio_document_download_target( $post_id ) {
		$post_id = (int) $post_id;

		$file_id = (int) get_post_meta( $post_id, 'cptdp_document_file', true );
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
		$tools    = array();
		$tool_ids = get_post_meta( (int) $post_id, 'cptdp_software_tools', true );
		foreach ( is_array( $tool_ids ) ? $tool_ids : array() as $tool_id ) {
			if ( 'software_tool' === get_post_type( (int) $tool_id ) && 'publish' === get_post_status( (int) $tool_id ) ) {
				$tools[] = get_the_title( (int) $tool_id );
			}
		}

		return $tools;
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

		$slugs = wp_get_object_terms( $post_id, 'document_category', array( 'fields' => 'slugs' ) );

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

		$tools = devportfolio_software_tool_names( $post_id );

		$pages    = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );
		$download = devportfolio_document_download_target( $post_id );
		$viewer   = devportfolio_document_viewer_data( $post_id );

		return array(
			'id'           => $post_id,
			// The slug is the deeplink key: /documents/?document_slug=<slug> opens this document (DPD-032).
			'slug'         => (string) get_post_field( 'post_name', $post_id ),
			// The script writes the title with textContent, so it must be plain text: get_the_title() returns
			// HTML entities ("&#038;" for "&").
			'title'        => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'description'  => devportfolio_document_description( $post_id ),
			'tools'        => $tools,
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
		);
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

		if ( 'document' !== get_post_type( $post_id ) ) {
			return $data;
		}

		$data['kind'] = devportfolio_document_file_kind( $post_id );
		$file_id      = (int) get_post_meta( $post_id, 'cptdp_document_file', true );

		// A presentation or a spreadsheet (DPD-026, DPD-027): its converted pages (the default view, and the native
		// viewer's fallback), plus the native viewer's address when the plugin allows it; never a PDF for pdf.js.
		$native_url = array(
			'pptx' => 'cptdp_document_native_pptx_url',
			'xlsx' => 'cptdp_document_native_xlsx_url',
			'docx' => 'cptdp_document_native_docx_url',
		);
		if ( 'slide' === devportfolio_document_unit( $data['kind'] ) || devportfolio_document_is_spreadsheet( $data['kind'] ) || devportfolio_document_is_word( $data['kind'] ) ) {
			if ( function_exists( 'cptdp_get_document_pages' ) ) {
				$data = array_merge( $data, devportfolio_document_normalise_stored_pages( cptdp_get_document_pages( $post_id ) ) );
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

		if ( function_exists( 'cptdp_get_document_pages' ) ) {
			$data = array_merge( $data, devportfolio_document_normalise_stored_pages( cptdp_get_document_pages( $post_id ) ) );
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
	 * The defaults live in blocks/document-modal/capability.js (DEFAULT_LIMITS);
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
