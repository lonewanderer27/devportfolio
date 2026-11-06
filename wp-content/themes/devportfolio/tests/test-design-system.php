<?php
/**
 * Tests for the design system pass on the Documents page and the shared header (DPD-034).
 *
 * There is no browser runner, so these check the CSS and data structure: fonts, page title,
 * category pills, the phone grid, the sticky glass header, separators and the header layout.
 *
 * @package devportfolio
 * @group theme
 */

/**
 * Design system structure tests.
 */
class Devportfolio_Test_Design_System extends WP_UnitTestCase {

	/**
	 * Theme directory.
	 *
	 * @return string
	 */
	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	/**
	 * File contents relative to the theme.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	private function read( string $path ): string {
		$this->assertFileExists( $this->theme_dir() . '/' . $path );

		return (string) file_get_contents( $this->theme_dir() . '/' . $path );
	}

	/**
	 * Decoded theme.json.
	 *
	 * @return array<string, mixed>
	 */
	private function json(): array {
		$data = json_decode( $this->read( 'theme.json' ), true );
		$this->assertIsArray( $data, 'theme.json must be valid JSON.' );

		return $data;
	}

	/**
	 * The top-level `styles.css` string of theme.json.
	 *
	 * @return string
	 */
	private function global_css(): string {
		return (string) ( $this->json()['styles']['css'] ?? '' );
	}

	/**
	 * Body of the first rule for a selector (only top-level or already extracted CSS).
	 *
	 * @param string $css      CSS.
	 * @param string $selector Exact selector.
	 * @return string Declarations with whitespace collapsed, or ''.
	 */
	private function rule( string $css, string $selector ): string {
		if ( 1 === preg_match( '/(?:^|[}\s])' . preg_quote( $selector, '/' ) . '\s*\{([^{}]*)\}/', $css, $m ) ) {
			return trim( (string) preg_replace( '/\s+/', ' ', $m[1] ) );
		}

		return '';
	}

	/**
	 * Inner CSS of a media query (balanced braces).
	 *
	 * @param string $css   CSS.
	 * @param string $query Text after "@media " up to the opening brace, e.g. "(max-width: 820px)".
	 * @return string
	 */
	private function media( string $css, string $query ): string {
		$start = strpos( $css, '@media ' . $query );
		$this->assertNotFalse( $start, "Media query {$query} missing." );

		$open  = strpos( $css, '{', (int) $start );
		$depth = 0;
		$len   = strlen( $css );
		for ( $i = (int) $open; $i < $len; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
				if ( 0 === $depth ) {
					return substr( $css, (int) $open + 1, $i - (int) $open - 1 );
				}
			}
		}

		$this->fail( "Media query {$query} is not closed." );
	}

	// ----- Font ----------------------------------------------------------------------------

	public function test_sans_preset_is_self_hosted_hanken_grotesk_in_seven_weights(): void {
		$families = $this->json()['settings']['typography']['fontFamilies'];
		$by_slug  = array_column( $families, null, 'slug' );

		$this->assertStringStartsWith( "'Hanken Grotesk'", $by_slug['sans']['fontFamily'] );
		$this->assertStringContainsString( 'sans-serif', $by_slug['sans']['fontFamily'], 'A fallback stack is needed while the font loads.' );
		$this->assertSame( array( '200', '300', '400', '500', '600', '700', '800' ), array_column( $by_slug['sans']['fontFace'], 'fontWeight' ) );

		foreach ( $by_slug['sans']['fontFace'] as $face ) {
			$this->assertSame( 'swap', $face['fontDisplay'] );
			$this->assertSame( 'Hanken Grotesk', $face['fontFamily'] );
			$this->assertSame( 'normal', $face['fontStyle'] );
		}
	}

	public function test_every_font_file_exists_is_woff2_and_is_local(): void {
		$families = array_column( $this->json()['settings']['typography']['fontFamilies'], null, 'slug' );

		foreach ( $families['sans']['fontFace'] as $face ) {
			$src = $face['src'][0];
			$this->assertStringStartsWith( 'file:./assets/fonts/', $src, 'Fonts are bundled, never requested from a third party.' );

			$path = $this->theme_dir() . '/' . substr( $src, strlen( 'file:./' ) );
			$this->assertFileExists( $path );
			$this->assertSame( 'wOF2', substr( (string) file_get_contents( $path, false, null, 0, 4 ), 0, 4 ), basename( $path ) . ' is not a woff2 file.' );
			$this->assertLessThan( 40000, filesize( $path ), basename( $path ) . ' should stay a small latin subset.' );
		}
	}

	public function test_serif_preset_uses_the_same_family_and_no_block_style_names_georgia(): void {
		$families = array_column( $this->json()['settings']['typography']['fontFamilies'], null, 'slug' );

		$this->assertStringStartsWith( "'Hanken Grotesk'", $families['serif']['fontFamily'] );

		foreach ( glob( $this->theme_dir() . '/blocks/*/style.css' ) as $file ) {
			$this->assertStringNotContainsStringIgnoringCase( 'georgia', (string) file_get_contents( $file ), basename( dirname( $file ) ) . ' still names Georgia.' );
		}
	}

	// ----- Page title ----------------------------------------------------------------------

	public function test_template_title_has_the_documents_title_class(): void {
		$markup = $this->read( 'templates/archive-document.html' );

		$this->assertStringContainsString( '"className":"documents-page__title"', $markup );
		$this->assertStringContainsString( '<h3 class="wp-block-heading documents-page__title">Documents</h3>', $markup );
	}

	public function test_title_is_light_and_fluid_like_the_hifi(): void {
		$css  = $this->read( 'blocks/document-shelf/style.css' );
		$rule = $this->rule( $css, '.documents-page .documents-page__title' );

		$this->assertStringContainsString( 'font-size: clamp(44px, 6.4vw, 72px)', $rule );
		$this->assertStringContainsString( 'font-weight: 200', $rule );
		$this->assertStringContainsString( 'letter-spacing: -0.04em', $rule );
		$this->assertStringContainsString( 'line-height: 1', $rule );
	}

	public function test_phone_title_and_page_padding_leave_room_for_the_books(): void {
		$tablet = $this->media( $this->read( 'blocks/document-shelf/style.css' ), '(max-width: 820px)' );

		$this->assertStringContainsString( 'padding-top: 16px !important', $this->rule( $tablet, '.documents-page' ), 'Overrides the 48px set inline by the template.' );

		$title = $this->rule( $tablet, '.documents-page .documents-page__title' );
		$this->assertStringContainsString( 'font-size: clamp(36px, 9vw, 52px)', $title );
		$this->assertStringContainsString( 'margin-bottom: 12px', $title );
	}

	// ----- Phone grid ----------------------------------------------------------------------

	public function test_phones_always_get_two_columns_without_a_fixed_count(): void {
		$phone = $this->media( $this->read( 'blocks/document-shelf/style.css' ), '(max-width: 480px)' );
		$rule  = $this->rule( $phone, '.documents-shelf' );

		$this->assertStringContainsString( 'repeat(auto-fill, minmax(max(110px, calc(50% - 16px)), 1fr))', $rule );
		$this->assertStringContainsString( 'padding-top: 16px', $rule );
		$this->assertStringContainsString( 'row-gap: 20px', $rule );
		$this->assertStringContainsString( 'padding-top: 10px', $this->rule( $phone, '.documents-item__info' ) );
	}

	public function test_software_tags_are_hidden_under_a_book_on_phones_only(): void {
		$css   = $this->read( 'blocks/document-shelf/style.css' );
		$phone = $this->media( $css, '(max-width: 480px)' );

		$this->assertStringContainsString( 'display: none', $this->rule( $phone, '.documents-item .software-tags' ) );
		$outside = str_replace( $phone, '', $css );
		$this->assertSame( '', $this->rule( $outside, '.documents-item .software-tags' ), 'Tags show above 480px.' );
	}

	public function test_only_the_sticky_strip_draws_the_line_above_the_shelf(): void {
		$css = $this->read( 'blocks/document-shelf/style.css' );

		$this->assertStringNotContainsString( 'border-top', $this->rule( $css, '.documents-shelf::before' ), 'A second line under the sticky strip looked doubled.' );
		$this->assertStringContainsString( 'border-bottom: 1px solid var(--shelf-line)', $this->rule( $css, '.documents-shelf::before' ) );
	}

	public function test_the_gutter_variable_is_shared_with_the_category_strip(): void {
		$page = $this->rule( $this->read( 'blocks/document-shelf/style.css' ), '.documents-page' );
		$this->assertStringContainsString( '--documents-gutter: clamp(0px, 3vw, 32px)', $page );

		$strip = $this->media( $this->read( 'blocks/document-categories/style.css' ), '(max-width: 820px)' );
		$this->assertStringContainsString( 'var(--documents-gutter, 0px)', $strip );
	}

	// ----- Category pills ------------------------------------------------------------------

	public function test_pill_matches_the_design_system_chip(): void {
		$css  = $this->read( 'blocks/document-categories/style.css' );
		$rule = $this->rule( $css, '.documents-categories__pill' );

		$this->assertStringContainsString( 'min-height: 36px', $rule );
		$this->assertStringContainsString( 'font-size: 14px', $rule );
		$this->assertStringContainsString( 'font-weight: 500', $rule );
		$this->assertStringContainsString( 'white-space: nowrap', $rule );
		$this->assertStringContainsString( 'transition: background-color 160ms ease', $rule );
	}

	public function test_pill_has_a_44px_tap_area_and_a_hover_fill_for_mouse_users_only(): void {
		$css = $this->read( 'blocks/document-categories/style.css' );

		$this->assertStringContainsString( 'inset: -4px 0', $this->rule( $css, '.documents-categories__pill::after' ) );

		$hover = $this->media( $css, '(hover: hover)' );
		$this->assertStringContainsString( 'var(--wp--preset--color--fill)', $this->rule( $hover, '.documents-categories__pill:hover:not([aria-pressed="true"])' ) );
	}

	public function test_phone_pills_are_a_little_smaller(): void {
		$phone = $this->media( $this->read( 'blocks/document-categories/style.css' ), '(max-width: 820px)' );
		$rule  = $this->rule( $phone, '.documents-categories__pill' );

		$this->assertStringContainsString( 'min-height: 34px', $rule );
		$this->assertStringContainsString( 'font-size: 13px', $rule );
	}

	public function test_strip_sticks_under_the_header_at_every_size(): void {
		$rule = $this->rule( $this->read( 'blocks/document-categories/style.css' ), '.wp-block-devportfolio-document-categories' );

		$this->assertStringContainsString( 'position: sticky', $rule );
		$this->assertStringContainsString( 'top: calc(var(--site-sticky-top, 0px) + var(--site-header-height, 0px))', $rule );
		$this->assertStringContainsString( 'z-index: 5', $rule );
		$this->assertStringContainsString( 'margin: 0;', $rule, 'The gap under the strip is gone: its line is the shelf\'s top line.' );
	}

	public function test_large_screen_strip_has_a_full_width_frosted_layer_behind_the_pills(): void {
		$layer = $this->rule( $this->read( 'blocks/document-categories/style.css' ), '.wp-block-devportfolio-document-categories::before' );

		$this->assertStringContainsString( 'inset: 0 -100vw', $layer );
		$this->assertStringContainsString( 'z-index: -1', $layer );
		$this->assertStringContainsString( 'var(--wp--custom--glass--tint, rgb(255 255 255 / 0.8))', $layer );
		$this->assertStringContainsString( 'var(--wp--custom--glass--filter, blur(20px) saturate(180%))', $layer );
		$this->assertStringContainsString( 'border-bottom: 1px solid var(--wp--custom--edge, rgb(0 0 0 / 0.1))', $layer );
	}

	public function test_phone_strip_bleeds_to_the_screen_edges_and_is_the_band_itself(): void {
		$phone = $this->media( $this->read( 'blocks/document-categories/style.css' ), '(max-width: 820px)' );
		$rule  = $this->rule( $phone, '.wp-block-devportfolio-document-categories' );

		$this->assertStringContainsString( 'margin: 0 calc(-1 * (var(--wp--preset--spacing--40) + var(--documents-gutter, 0px)))', $rule );
		$this->assertStringContainsString( 'overflow-x: auto', $rule );
		$this->assertStringContainsString( 'display: none', $this->rule( $phone, '.wp-block-devportfolio-document-categories::before' ), 'A layer inside an overflow container would be clipped.' );
	}

	public function test_phone_strip_is_frosted_glass_with_a_soft_hairline(): void {
		$phone = $this->media( $this->read( 'blocks/document-categories/style.css' ), '(max-width: 820px)' );
		$rule  = $this->rule( $phone, '.wp-block-devportfolio-document-categories' );

		$this->assertStringContainsString( 'background: var(--wp--custom--glass--tint, rgb(255 255 255 / 0.8))', $rule );
		$this->assertStringContainsString( 'backdrop-filter: var(--wp--custom--glass--filter, blur(20px) saturate(180%))', $rule );
		$this->assertStringContainsString( 'border-bottom: 1px solid var(--wp--custom--edge, rgb(0 0 0 / 0.1))', $rule );
	}

	public function test_strip_stops_sticking_on_a_short_screen_held_sideways_but_not_on_a_short_portrait_screen(): void {
		$css  = $this->read( 'blocks/document-categories/style.css' );
		$land = $this->media( $css, '(orientation: landscape) and (max-height: 520px)' );

		$this->assertStringContainsString( 'position: static', $this->rule( $land, '.wp-block-devportfolio-document-categories' ) );
		$this->assertStringNotContainsString( '@media (max-height: 520px)', $css, 'Height alone would also match a short portrait phone.' );
	}

	public function test_category_script_names_the_header_only_while_a_change_runs(): void {
		$js = $this->read( 'blocks/document-categories/view.js' );

		$this->assertStringContainsString( "classList.add( 'is-view-transitioning' )", $js );
		$this->assertStringContainsString( "classList.remove( 'is-view-transitioning' )", $js );
		$this->assertStringContainsString( 'transition.finished.then( clearHeader, clearHeader )', $js, 'The class must also be removed when the transition is skipped.' );
		$this->assertStringContainsString( "group.classList.add( 'is-view-transitioning' )", $js, 'The pill group is named only while a change runs too.' );
		$this->assertStringContainsString( "group.classList.remove( 'is-view-transitioning' )", $js );
	}

	// ----- Header --------------------------------------------------------------------------

	public function test_header_part_has_no_inline_border_and_the_bar_group(): void {
		$header = $this->read( 'parts/header.html' );

		$this->assertStringNotContainsString( 'border-bottom', $header, 'The separator is plain CSS now so a media query can change it.' );
		$this->assertStringContainsString( 'site-header__bar', $header );
		$this->assertStringContainsString( 'padding-top:12px;padding-bottom:12px', $header );
		$this->assertStringContainsString( '"blockGap":"2px"', $header );
		$this->assertSame( 7, substr_count( $header, 'wp:navigation-link' ) );
	}

	public function test_header_separator_is_the_soft_edge_and_goes_when_the_strip_sticks(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( 'header.wp-block-template-part > .wp-block-group { border-bottom: 1px solid var(--wp--custom--edge); }', $css );
		// Whenever the strip sticks (every size except a short screen held sideways) the header and the strip read as one block with a single line.
		$this->assertStringContainsString( '@media not ((orientation: landscape) and (max-height: 520px)) { body:has(.wp-block-devportfolio-document-categories) header.wp-block-template-part > .wp-block-group { border-bottom-color: transparent; } }', $css );
	}

	public function test_glass_tint_is_80_percent_white_with_the_design_system_blur(): void {
		$custom = $this->json()['settings']['custom'];

		// One home for the shared values: the header, the pill strip and the modal bar read these.
		$this->assertSame( 'rgb(255 255 255 / 0.8)', $custom['glass']['tint'] );
		$this->assertSame( 'blur(20px) saturate(180%)', $custom['glass']['filter'] );
		$this->assertSame( 'rgb(0 0 0 / 0.1)', $custom['edge'] );

		$this->assertMatchesRegularExpression( '/header\.wp-block-template-part::before \{[^}]*background: var\(--wp--custom--glass--tint\)[^}]*backdrop-filter: var\(--wp--custom--glass--filter\)/', $this->global_css() );
	}

	public function test_blocks_use_the_shared_glass_and_edge_tokens_with_a_fallback(): void {
		foreach ( array( 'document-categories', 'document-modal' ) as $block ) {
			$css = $this->read( "blocks/{$block}/style.css" );

			$this->assertStringContainsString( 'var(--wp--custom--glass--filter, blur(20px) saturate(180%))', $css, "{$block} must use the shared blur." );
			$this->assertStringNotContainsString( 'backdrop-filter: blur(20px) saturate(180%)', $css, "{$block} still hard-codes the blur." );
		}

		$strip = $this->read( 'blocks/document-categories/style.css' );
		$this->assertStringNotContainsString( 'background: rgb(255 255 255 / 0.8)', $strip );
		$this->assertStringNotContainsString( 'border-bottom: 1px solid rgb(0 0 0 / 0.1)', $strip );
	}

	public function test_menu_button_draws_three_centred_bars(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '.wp-block-navigation__responsive-container-open svg { display: none; }', $css );
		$this->assertStringContainsString( 'background-size: 18px 2px', $css );
		$this->assertStringContainsString( 'background-position: center 10px, center 17px, center 24px', $css );
		$this->assertSame( 3, substr_count( $css, 'linear-gradient(var(--wp--preset--color--contrast), var(--wp--preset--color--contrast))' ), 'One gradient per bar.' );
	}

	public function test_phone_menu_overlay_has_large_targets_and_a_centred_label(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item { font-size: 18px; }', $css );
		$this->assertMatchesRegularExpression( '/\.is-menu-open \.wp-block-navigation-item \.wp-block-navigation-item__content \{[^}]*display: flex; align-items: center; justify-content: center; padding: 0 16px; min-height: 44px;/', $css );
	}

	public function test_large_screen_header_is_as_wide_as_the_documents_page_with_the_title_on_the_left(): void {
		$wide = $this->media( $this->global_css(), '(min-width: 1024px)' );

		$this->assertStringContainsString( '--wp--style--global--content-size: 1440px', $wide );
		$this->assertStringContainsString( 'grid-template-columns: 1fr auto 1fr', $wide );
		$this->assertStringContainsString( ".site-header__bar::after { content: ''; }", $wide );
	}

	public function test_contact_is_pinned_right_as_an_outlined_pill_from_1024px(): void {
		$wide = $this->media( $this->global_css(), '(min-width: 1024px)' );

		$item = $this->rule( $wide, '.site-header__bar .wp-block-navigation-item:last-child' );
		$this->assertStringContainsString( 'position: absolute', $item );
		$this->assertStringContainsString( 'right: 0', $item );

		$link = $this->rule( $wide, '.site-header__bar .wp-block-navigation-item:last-child > a' );
		$this->assertStringContainsString( 'display: flex', $link, 'A block link top-aligns its label.' );
		$this->assertStringContainsString( 'border: 1.5px solid var(--wp--preset--color--contrast)', $link );
		$this->assertStringContainsString( 'border-radius: 9999px', $link );

		$this->assertStringContainsString( ':not(.is-menu-open)', $wide, 'The phone overlay must keep its own positioning.' );
	}

	public function test_title_is_hidden_only_between_phone_and_large_screen(): void {
		$css = $this->global_css();

		$this->assertStringContainsString( '@media (min-width: 600px) and (max-width: 1023px) { .site-header__title { display: none; } }', $css );
		$this->assertStringContainsString( ':root { --site-header-height: 61px; --site-sticky-top: 0px; }', $css );
	}
}
