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
