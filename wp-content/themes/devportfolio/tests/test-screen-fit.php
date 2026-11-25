<?php
/**
 * Screen fit tests (DPF-NNN): the laptop density tier and the text floor.
 *
 * There is no browser runner, so these check the CSS: every stylesheet the laptop tier touches closes with the
 * same media query once, each tier holds its values, the small text was raised, and no new text size below 12px
 * appears outside the allowed list. The 2K values are pinned by the existing block tests.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Screen_Fit extends WP_UnitTestCase {

	const QUERY = '(min-width: 1025px) and (max-width: 1599px)';

	const TIER_FILES = array(
		'assets/css/page.css',
		'blocks/document-shelf/style.css',
		'blocks/video-grid/style.css',
		'blocks/collateral-grid/style.css',
		'blocks/hero/style.css',
		'blocks/stats-strip/style.css',
		'blocks/service-list/style.css',
		'blocks/testimonials/style.css',
		'blocks/contact/style.css',
	);

	/**
	 * File contents relative to the theme.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	private function read( string $path ): string {
		$file = dirname( __DIR__ ) . '/' . $path;
		$this->assertFileExists( $file );

		return (string) file_get_contents( $file );
	}

	/**
	 * Inner CSS of the first media query in a file, whitespace collapsed.
	 *
	 * @param string $path  Relative path.
	 * @param string $query Text after "@media ".
	 * @return string
	 */
	private function media( string $path, string $query ): string {
		$css   = $this->read( $path );
		$start = strpos( $css, '@media ' . $query );
		$this->assertNotFalse( $start, "@media {$query} missing in {$path}." );

		$open  = strpos( $css, '{', (int) $start );
		$depth = 0;
		$len   = strlen( $css );
		for ( $i = (int) $open; $i < $len; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
				if ( 0 === $depth ) {
					return trim( (string) preg_replace( '/\s+/', ' ', substr( $css, (int) $open + 1, $i - (int) $open - 1 ) ) );
				}
			}
		}

		$this->fail( "@media {$query} in {$path} is not closed." );
	}

	/**
	 * Inner CSS of the laptop tier in one file.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	private function tier( string $path ): string {
		return $this->media( $path, self::QUERY );
	}

	// ----- Laptop density tier ------------------------------------------------------------

	public function test_every_tier_file_closes_with_the_query_once(): void {
		foreach ( self::TIER_FILES as $path ) {
			$css = $this->read( $path );
			$this->assertSame( 1, substr_count( $css, '@media ' . self::QUERY ), "{$path} must hold the laptop tier once." );
			$this->assertGreaterThan( (int) strrpos( $css, '@media (max-width' ), (int) strpos( $css, '@media ' . self::QUERY ), "{$path}: the tier comes after the narrower rules." );
		}
	}

	public function test_page_shell_is_tighter(): void {
		$tier = $this->tier( 'assets/css/page.css' );
		$this->assertStringContainsString( '.documents-page { padding-top: 32px !important; }', $tier );
		$this->assertStringContainsString( 'margin-bottom: 18px; font-size: clamp(44px, 4vw, 56px);', $tier );
		$this->assertStringContainsString( '.home-page > .home-section { padding-top: 32px !important; padding-bottom: 32px !important; }', $tier );
	}

	public function test_shelf_fits_six_books_and_steps_the_titles_down(): void {
		$tier = $this->tier( 'blocks/document-shelf/style.css' );
		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(min(100%, 170px), 1fr)); gap: 32px 24px; padding-top: 28px;', $tier );
		$this->assertStringContainsString( '.documents-shelf .documents-book__ttl { font-size: 20px; }', $tier );
		$this->assertStringContainsString( '.documents-shelf .documents-book[data-title-length="long"] .documents-book__ttl { font-size: 17px; }', $tier );
		$this->assertStringContainsString( '.documents-shelf .documents-book[data-title-length="xlong"] .documents-book__ttl { font-size: 14px; }', $tier );
	}

	public function test_video_and_collateral_grids_use_narrower_tracks(): void {
		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));', $this->tier( 'blocks/video-grid/style.css' ) );

		$collaterals = $this->tier( 'blocks/collateral-grid/style.css' );
		$this->assertStringContainsString( 'column-width: 190px; margin-top: 28px;', $collaterals );
		$this->assertStringContainsString( '.collaterals-grid::before { top: -28px; }', $collaterals );
	}

	public function test_masonry_uses_190px_below_a_full_column(): void {
		$js = $this->read( 'blocks/collateral-grid/masonry.js' );
		$this->assertStringContainsString( 'if ( width < 1440 ) {', $js );
		$this->assertStringNotContainsString( 'if ( width <= 1000 ) {', $js );
	}

	public function test_hero_name_keeps_its_2k_share_of_the_width(): void {
		$this->assertStringContainsString( '.hero__stage { font-size: min(9vw, 168px); }', $this->tier( 'blocks/hero/style.css' ) );
	}

	public function test_home_sections_are_tighter(): void {
		$stats = $this->tier( 'blocks/stats-strip/style.css' );
		$this->assertStringContainsString( '.stats-strip__item { padding: 16px 20px; }', $stats );
		$this->assertStringContainsString( '.stats-strip__value { font-size: 36px; }', $stats );

		$services = $this->tier( 'blocks/service-list/style.css' );
		$this->assertStringContainsString( '.service-list__row { padding: 16px 8px; }', $services );
		$this->assertStringContainsString( '.service-list__title { font-size: 22px; }', $services );

		$this->assertStringContainsString( '.testimonials__quote p { font-size: 22px; }', $this->tier( 'blocks/testimonials/style.css' ) );

		$contact = $this->tier( 'blocks/contact/style.css' );
		$this->assertStringContainsString( '.contact { padding: 32px; }', $contact );
		$this->assertStringContainsString( '.contact__heading { font-size: 28px; }', $contact );
		$this->assertStringContainsString( '.contact__lead { margin-bottom: 20px; }', $contact );
		$this->assertStringContainsString( '.contact__card { --card-pad: 22px; }', $contact );
		$this->assertStringContainsString( '.contact-picker__day { aspect-ratio: 3 / 2; }', $contact );
	}

	// ----- Text floor ---------------------------------------------------------------------

	public function test_phone_book_cover_labels_match_the_desktop_10px(): void {
		$this->assertStringContainsString( '.documents-book__cat, .documents-book__ft { font-size: 10px; }', $this->media( 'blocks/document-book/style.css', '(max-width: 820px)' ) );
		$this->assertStringNotContainsString( '8.5px', $this->read( 'blocks/document-book/style.css' ) );
	}

	public function test_software_tool_names_are_12px_on_every_screen(): void {
		$css = $this->read( 'blocks/software-tools/style.css' );
		$this->assertMatchesRegularExpression( '/\.software-tools__name \{[^}]*font-size: 12px;/', $css );
		$this->assertStringNotContainsString( 'font-size: 9px', $css );
		$this->assertStringNotContainsString( '11.5px', $css );
	}

	public function test_hero_roles_are_11px_and_balanced_on_phones(): void {
		$this->assertStringContainsString( 'margin: 0 0 12px; font-size: 11px; text-wrap: balance;', $this->media( 'blocks/hero/style.css', '(max-width: 620px)' ) );
		$this->assertStringNotContainsString( '10.5px', $this->read( 'blocks/hero/style.css' ) );
	}

	public function test_collateral_meta_keeps_its_12px_on_phones(): void {
		$css = $this->read( 'blocks/collateral-grid/style.css' );
		$this->assertMatchesRegularExpression( '/\.collaterals-item__meta \{[^}]*font-size: 12px;/', $css );
		$this->assertStringNotContainsString( '11.5px', $css );
	}

	public function test_service_tags_details_pill_and_up_next_length_are_12px(): void {
		$this->assertMatchesRegularExpression( '/\.service-list__tag \{[^}]*font-size: 12px;/', $this->read( 'blocks/service-list/style.css' ) );
		$this->assertMatchesRegularExpression( '/\.documents-mobile__details-category \{[^}]*font-size: 12px;/', $this->read( 'blocks/shared/viewer.css' ) );
		$this->assertMatchesRegularExpression( '/\.videos-modal__nextlen \{[^}]*font-size: 12px;/', $this->read( 'blocks/video-modal/style.css' ) );
	}

	public function test_contact_fields_are_16px_on_touch_screens(): void {
		$this->assertStringContainsString(
			".contact input[type='text'], .contact input[type='email'], .contact textarea { font-size: 16px; }",
			$this->media( 'blocks/contact/style.css', '(pointer: coarse)' )
		);
	}

	public function test_no_text_is_below_the_floor_outside_the_allowed_list(): void {
		// 11px: uppercase, letter-spaced labels. 10px: book cover artwork. 9.5px: film-strip timecodes (artwork).
		$allowed = array(
			'blocks/collateral-modal/style.css' => array( '11px' => 4 ),
			'blocks/document-book/style.css'    => array( '10px' => 3 ),
			'blocks/hero/style.css'             => array( '11px' => 2 ),
			'blocks/selected-work/style.css'    => array( '9.5px' => 1 ),
			'blocks/video-modal/style.css'      => array( '11px' => 4 ),
		);

		$theme = dirname( __DIR__ );
		$files = array_merge( glob( $theme . '/blocks/*/*.css' ) ?: array(), glob( $theme . '/assets/css/*.css' ) ?: array() );
		$found = array();
		foreach ( $files as $file ) {
			preg_match_all( '/font-size:\s*([0-9.]+)px/', (string) file_get_contents( $file ), $m );
			foreach ( $m[1] as $size ) {
				if ( (float) $size < 12 ) {
					$rel                         = substr( $file, strlen( $theme ) + 1 );
					$found[ $rel ][ $size . 'px' ] = ( $found[ $rel ][ $size . 'px' ] ?? 0 ) + 1;
				}
			}
		}
		ksort( $found );

		$this->assertSame( $allowed, $found, 'Text below 12px must be an uppercase label (11px) or artwork; see DPF-NNN.' );
	}
}
