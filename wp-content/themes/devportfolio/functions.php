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
	}
endif;
add_action( 'init', 'devportfolio_register_blocks' );

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

if ( ! function_exists( 'devportfolio_document_format_label' ) ) :
	/**
	 * Builds the cover format line, e.g. "PDF · 24 pp" (DPD-005).
	 *
	 * The file type is upper-cased. One page reads "1 p", more "n pp"; zero,
	 * negative or non-numeric counts are left out. The parts are joined by a
	 * middle dot only when both exist. Returns plain text (escape on output).
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $filetype File type, e.g. "pdf".
	 * @param mixed $pages    Page count.
	 * @return string
	 */
	function devportfolio_document_format_label( $filetype, $pages ) {
		$parts = array();

		$filetype = is_scalar( $filetype ) ? strtoupper( trim( (string) $filetype ) ) : '';
		if ( '' !== $filetype ) {
			$parts[] = $filetype;
		}

		$pages = ( is_int( $pages ) || ( is_string( $pages ) && ctype_digit( trim( $pages ) ) ) ) ? (int) $pages : 0;
		if ( 1 === $pages ) {
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

		return array(
			'id'           => $post_id,
			'title'        => get_the_title( $post_id ),
			'description'  => devportfolio_document_description( $post_id ),
			'tools'        => $tools,
			'format'       => devportfolio_document_format_label(
				get_post_meta( $post_id, 'cptdp_filetype', true ),
				get_post_meta( $post_id, 'cptdp_total_pages', true )
			),
			'pages'        => max( 0, $pages ),
			'downloadMode' => $download['mode'],
			'downloadUrl'  => $download['url'],
		);
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
