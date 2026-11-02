<?php
/**
 * Tests for the devportfolio main navigation (DPH-002).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Navigation extends WP_UnitTestCase {

	/**
	 * Nav labels in the required order (DPH-002).
	 *
	 * @var string[]
	 */
	private const LABELS = array( 'Home', 'Services', 'Documents', 'Videos', 'Collaterals', 'Testimonials', 'Contact' );

	/**
	 * Theme directory.
	 *
	 * @return string
	 */
	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The test lib unregisters post types' meta between tests; make sure the plugin's CPTs exist.
		do_action( 'cptdp_register_post_types' );

		if ( ! is_file( $this->theme_dir() . '/parts/header.html' ) ) {
			$this->markTestSkipped( 'devportfolio theme not found.' );
		}

		// The test lib restores hooks after every test, so attach the theme filter each time.
		require_once $this->theme_dir() . '/functions.php';
		add_filter( 'render_block_data', 'devportfolio_prepare_navigation_link' );
	}

	public function tear_down(): void {
		global $wp_rewrite;

		if ( '' !== (string) $wp_rewrite->permalink_structure ) {
			$this->set_permalink_structure( '' );
		}

		parent::tear_down();
	}

	/**
	 * Raw header part markup.
	 *
	 * @return string
	 */
	private function header_markup(): string {
		return (string) file_get_contents( $this->theme_dir() . '/parts/header.html' );
	}

	/**
	 * Render the header part on the given URL and return its nav items.
	 *
	 * @param string $url URL to simulate, or '' for the home page.
	 * @return array<int, array{label:string,href:string,aria_current:bool,class:string}>
	 */
	private function nav_items( string $url = '' ): array {
		$this->go_to( '' === $url ? home_url( '/' ) : $url );

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . do_blocks( $this->header_markup() ) );
		libxml_clear_errors();

		$items = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//li[contains(@class,"wp-block-navigation-link")]' ) as $li ) {
			$a       = $li->getElementsByTagName( 'a' )->item( 0 );
			$items[] = array(
				'label'        => trim( $a->textContent ),
				'href'         => $a->getAttribute( 'href' ),
				'aria_current' => 'page' === $a->getAttribute( 'aria-current' ),
				'class'        => $li->getAttribute( 'class' ),
			);
		}

		return $items;
	}

	/**
	 * Labels of items marked as the current page (aria-current or is-current).
	 *
	 * @param array $items Items from nav_items().
	 * @return string[]
	 */
	private function active_labels( array $items ): array {
		$active = array();
		foreach ( $items as $item ) {
			if ( $item['aria_current'] || str_contains( $item['class'], 'is-current' ) ) {
				$active[] = $item['label'];
			}
		}

		return $active;
	}

	public function test_links_and_order(): void {
		$items = $this->nav_items();

		$this->assertSame( self::LABELS, wp_list_pluck( $items, 'label' ) );
	}

	public function test_links_point_to_the_right_targets(): void {
		$expected = array(
			'Home'         => home_url( '/' ),
			'Services'     => get_post_type_archive_link( 'service' ),
			'Documents'    => get_post_type_archive_link( 'document' ),
			'Videos'       => get_post_type_archive_link( 'video' ),
			'Collaterals'  => get_post_type_archive_link( 'collateral' ),
			'Testimonials' => home_url( '/#testimonials' ),
			'Contact'      => home_url( '/#contact' ),
		);

		$this->assertSame( $expected, wp_list_pluck( $this->nav_items(), 'href', 'label' ) );
	}

	public function test_header_part_stores_no_hardcoded_domain(): void {
		$this->assertStringNotContainsString( 'http', $this->header_markup() );
		$this->assertStringNotContainsString( 'ddev.site', $this->header_markup() );
	}

	public function test_urls_follow_the_site_url_with_pretty_permalinks(): void {
		$this->set_permalink_structure( '/%postname%/' );
		do_action( 'cptdp_register_post_types' );
		flush_rewrite_rules( false );

		$hrefs = wp_list_pluck( $this->nav_items(), 'href', 'label' );

		$this->assertSame( home_url( '/documents/' ), $hrefs['Documents'] );
		$this->assertSame( home_url( '/videos/' ), $hrefs['Videos'] );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function archive_provider(): array {
		return array(
			'services'    => array( 'service', 'Services' ),
			'documents'   => array( 'document', 'Documents' ),
			'videos'      => array( 'video', 'Videos' ),
			'collaterals' => array( 'collateral', 'Collaterals' ),
		);
	}

	/**
	 * @dataProvider archive_provider
	 *
	 * @param string $post_type Post type slug.
	 * @param string $label     Nav label expected to be active.
	 */
	public function test_archive_page_marks_only_its_own_link_active( string $post_type, string $label ): void {
		self::factory()->post->create( array( 'post_type' => $post_type ) );

		$items = $this->nav_items( get_post_type_archive_link( $post_type ) );

		$this->assertSame( array( $label ), $this->active_labels( $items ) );
	}

	public function test_documents_archive_underlines_documents_via_aria_current(): void {
		$items = $this->nav_items( get_post_type_archive_link( 'document' ) );
		$by    = array_column( $items, null, 'label' );

		$this->assertTrue( $by['Documents']['aria_current'] );
		$this->assertFalse( $by['Home']['aria_current'] );
		$this->assertStringNotContainsString( 'is-current', $by['Home']['class'] );
	}

	public function test_front_page_marks_only_home_active(): void {
		$items = $this->nav_items();

		$this->assertSame( array( 'Home' ), $this->active_labels( $items ) );
		$this->assertStringContainsString( 'is-current', array_column( $items, null, 'label' )['Home']['class'] );
	}

	public function test_anchor_links_are_never_active(): void {
		foreach ( array( '', get_post_type_archive_link( 'document' ) ) as $url ) {
			$active = $this->active_labels( $this->nav_items( $url ) );

			$this->assertNotContains( 'Testimonials', $active );
			$this->assertNotContains( 'Contact', $active );
		}
	}

	public function test_unrelated_pages_mark_nothing_active(): void {
		$post_id = self::factory()->post->create();
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( array(), $this->active_labels( $this->nav_items( get_permalink( $post_id ) ) ) );
		$this->assertSame( array(), $this->active_labels( $this->nav_items( get_permalink( $page_id ) ) ) );
		$this->assertSame( array(), $this->active_labels( $this->nav_items( home_url( '/?s=nothing' ) ) ) );
	}

	public function test_filter_expands_only_root_relative_navigation_links(): void {
		$block = static function ( string $name, array $attrs ): array {
			return array(
				'blockName' => $name,
				'attrs'     => $attrs,
			);
		};

		$this->go_to( get_post_type_archive_link( 'document' ) );

		$expanded = devportfolio_prepare_navigation_link( $block( 'core/navigation-link', array( 'url' => '/documents/' ) ) );
		$this->assertSame( home_url( '/documents/' ), $expanded['attrs']['url'] );
		$this->assertArrayNotHasKey( 'className', $expanded['attrs'] );

		$archive = devportfolio_prepare_navigation_link(
			$block(
				'core/navigation-link',
				array(
					'url'  => '/not-the-real-path/',
					'kind' => 'post-type-archive',
					'type' => 'document',
				)
			)
		);
		$this->assertSame( get_post_type_archive_link( 'document' ), $archive['attrs']['url'] );

		$unknown_type = devportfolio_prepare_navigation_link(
			$block(
				'core/navigation-link',
				array(
					'url'  => '/x/',
					'kind' => 'post-type-archive',
					'type' => 'no_such_type',
				)
			)
		);
		$this->assertSame( home_url( '/x/' ), $unknown_type['attrs']['url'] );

		$absolute = $block( 'core/navigation-link', array( 'url' => 'https://example.org/x/' ) );
		$this->assertSame( $absolute, devportfolio_prepare_navigation_link( $absolute ) );

		$no_url = $block( 'core/navigation-link', array( 'label' => 'x' ) );
		$this->assertSame( $no_url, devportfolio_prepare_navigation_link( $no_url ) );

		$other = $block( 'core/paragraph', array( 'url' => '/documents/' ) );
		$this->assertSame( $other, devportfolio_prepare_navigation_link( $other ) );
	}

	public function test_home_link_is_flagged_only_on_the_front_page(): void {
		$home = array(
			'blockName' => 'core/navigation-link',
			'attrs'     => array( 'url' => '/' ),
		);

		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'is-current', devportfolio_prepare_navigation_link( $home )['attrs']['className'] );

		$this->go_to( get_post_type_archive_link( 'document' ) );
		$this->assertArrayNotHasKey( 'className', devportfolio_prepare_navigation_link( $home )['attrs'] );
	}

	public function test_theme_json_styles_inactive_grey_and_active_ink_with_bar(): void {
		$json = json_decode( (string) file_get_contents( $this->theme_dir() . '/theme.json' ), true );
		$nav  = $json['styles']['blocks']['core/navigation'] ?? array();
		$css  = $nav['css'] ?? '';

		// Inactive links inherit the faded grey (core forces `color: inherit` on links, so it is set on the nav).
		$this->assertSame( 'var:preset|color|mid', $nav['color']['text'] ?? '' );
		$this->assertSame( '#8a8a8a', $this->palette_color( $json, 'mid' ) );
		$this->assertSame( '#222222', $this->palette_color( $json, 'contrast' ) );

		// Active item: ink colour on the <li> (links inherit it) and a 2px ink bar under the link.
		$this->assertStringContainsString( '.current-menu-item { color: var(--wp--preset--color--contrast)', $css );
		$this->assertStringContainsString( '.is-current { color: var(--wp--preset--color--contrast)', $css );
		$this->assertStringContainsString( "a[aria-current='page'] { border-bottom: 2px solid var(--wp--preset--color--contrast)", $css );
		$this->assertStringContainsString( '.is-current > a { border-bottom: 2px solid var(--wp--preset--color--contrast)', $css );
		$this->assertStringContainsString( 'font-weight: 400', $css );

		// Core overrides link colours (`color: inherit`, higher specificity) and mangles two things in this string.
		$this->assertDoesNotMatchRegularExpression( '/(^|\})\s*&\s*a\s*\{[^}]*\bcolor:/', $css );
		$this->assertStringNotContainsString( 'body.home', $css );
		$this->assertDoesNotMatchRegularExpression( '/[^{}]*,[^{}]*\{/', $css );
	}

	/**
	 * Palette colour by slug.
	 *
	 * @param array  $json theme.json data.
	 * @param string $slug Palette slug.
	 * @return string
	 */
	private function palette_color( array $json, string $slug ): string {
		foreach ( $json['settings']['color']['palette'] as $color ) {
			if ( $slug === $color['slug'] ) {
				return $color['color'];
			}
		}

		return '';
	}
}
