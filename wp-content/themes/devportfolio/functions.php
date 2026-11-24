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
		register_block_type( __DIR__ . '/blocks/video-grid' );
		register_block_type( __DIR__ . '/blocks/video-modal' );
		register_block_type( __DIR__ . '/blocks/site-footer' );
		register_block_type( __DIR__ . '/blocks/stats-strip' );
		register_block_type( __DIR__ . '/blocks/service-list' );
		register_block_type( __DIR__ . '/blocks/selected-work' );
		register_block_type( __DIR__ . '/blocks/testimonials' );
		register_block_type( __DIR__ . '/blocks/software-tools' );
		register_block_type( __DIR__ . '/blocks/contact' );
		register_block_type( __DIR__ . '/blocks/hero' );
	}
endif;
add_action( 'init', 'devportfolio_register_blocks' );

require_once __DIR__ . '/inc/core.php';
require_once __DIR__ . '/inc/documents.php';
require_once __DIR__ . '/inc/collaterals.php';
require_once __DIR__ . '/inc/videos.php';
require_once __DIR__ . '/inc/viewer.php';
require_once __DIR__ . '/inc/home.php';

if ( ! function_exists( 'devportfolio_enqueue_page_styles' ) ) :
	/**
	 * Enqueue styles shared by the Documents, Collaterals and Videos archives.
	 */
	function devportfolio_enqueue_page_styles() {
		if ( ! is_front_page() && ! is_post_type_archive( array( 'document', 'collateral', 'video' ) ) ) {
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
		if ( is_post_type_archive( array( 'collateral', 'video' ) ) ) {
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

if ( ! function_exists( 'devportfolio_enqueue_nav_spy' ) ) :
	/**
	 * Home only: moves the active main navigation item as sections scroll into view.
	 */
	function devportfolio_enqueue_nav_spy() {
		if ( ! is_front_page() ) {
			return;
		}
		wp_enqueue_script_module(
			'devportfolio-nav-spy',
			get_theme_file_uri( 'assets/js/nav-spy.js' ),
			array(),
			(string) filemtime( __DIR__ . '/assets/js/nav-spy.js' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_nav_spy' );

if ( ! function_exists( 'devportfolio_enqueue_home_motion' ) ) :
	/**
	 * Home only: the sections' opening animation styles (DPH-032).
	 *
	 * Every rule is scoped to `html.has-home-reveal`, which only the inline script adds.
	 *
	 * @since 0.36.0
	 */
	function devportfolio_enqueue_home_motion() {
		if ( ! is_front_page() ) {
			return;
		}

		wp_enqueue_style(
			'devportfolio-home-motion',
			get_theme_file_uri( 'assets/css/home-motion.css' ),
			array(),
			(string) filemtime( __DIR__ . '/assets/css/home-motion.css' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_home_motion' );

if ( ! function_exists( 'devportfolio_print_home_reveal_script' ) ) :
	/**
	 * Home only: prints the sections' opening animation script inline at the top of the head (DPH-032).
	 *
	 * Inline and first, so the class that hides the start state is on <html> before the first paint and a failed
	 * request can never leave a section hidden. Without JavaScript nothing is hidden.
	 *
	 * @since 0.36.0
	 */
	function devportfolio_print_home_reveal_script() {
		if ( ! is_front_page() ) {
			return;
		}

		$file = __DIR__ . '/assets/js/home-reveal.js';
		if ( ! is_readable( $file ) ) {
			return;
		}

		wp_print_inline_script_tag(
			(string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local theme file.
			array( 'id' => 'devportfolio-home-reveal' )
		);
	}
endif;
add_action( 'wp_head', 'devportfolio_print_home_reveal_script', 1 );
