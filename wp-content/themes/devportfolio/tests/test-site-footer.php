<?php
/**
 * Tests for the footer block (DPH-029).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Site_Footer extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/site-footer' ) ) {
			devportfolio_register_blocks();
		}

		$this->set_contact( array() );
	}

	/**
	 * Store the contact settings (empty values by default, then the given ones).
	 *
	 * @param array $values Keys email, whatsapp_url, linkedin_url.
	 */
	private function set_contact( array $values ): void {
		update_option(
			'cptdp_contact',
			array_merge(
				array(
					'email'        => '',
					'whatsapp_url' => '',
					'linkedin_url' => '',
				),
				$values
			)
		);
	}

	private function full_contact(): array {
		return array(
			'email'        => 'dave@example.com',
			'whatsapp_url' => 'https://wa.me/61400000000',
			'linkedin_url' => 'https://www.linkedin.com/in/dave',
		);
	}

	private function render(): string {
		return do_blocks( '<!-- wp:devportfolio/site-footer /-->' );
	}

	/**
	 * The footer links as DOM elements.
	 *
	 * @param string $html Rendered footer.
	 * @return DOMElement[]
	 */
	private function links( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		$found = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//a[contains(@class,"site-footer__link")]' ) as $a ) {
			$found[] = $a;
		}

		return $found;
	}

	public function test_copyright_uses_the_year_and_the_site_name(): void {
		$this->set_contact( $this->full_contact() );

		$this->assertStringContainsString( '© ' . wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ), $this->render() );
	}

	/**
	 * The label a link shows when the email is shortened: its own text, or the short "Email" span.
	 *
	 * @param DOMElement $a Link.
	 * @return string
	 */
	private function label( DOMElement $a ): string {
		foreach ( $a->getElementsByTagName( 'span' ) as $span ) {
			if ( 'site-footer__short' === $span->getAttribute( 'class' ) ) {
				return trim( $span->textContent );
			}
		}

		return trim( $a->textContent );
	}

	public function test_three_links_in_order_with_targets(): void {
		$this->set_contact( $this->full_contact() );
		$links = $this->links( $this->render() );

		$this->assertCount( 3, $links );
		$this->assertSame( array( 'LinkedIn', 'WhatsApp', 'Email' ), array_map( fn( $a ) => $this->label( $a ), $links ) );
		$this->assertSame( 'https://www.linkedin.com/in/dave', $links[0]->getAttribute( 'href' ) );
		$this->assertSame( 'https://wa.me/61400000000', $links[1]->getAttribute( 'href' ) );
		$this->assertSame( 'mailto:dave@example.com', $links[2]->getAttribute( 'href' ) );
	}

	public function test_external_links_open_in_a_new_tab_and_email_does_not(): void {
		$this->set_contact( $this->full_contact() );
		$links = $this->links( $this->render() );

		foreach ( array( 0, 1 ) as $i ) {
			$this->assertSame( '_blank', $links[ $i ]->getAttribute( 'target' ) );
			$this->assertSame( 'noopener noreferrer', $links[ $i ]->getAttribute( 'rel' ) );
		}

		$this->assertSame( '', $links[2]->getAttribute( 'target' ) );
		$this->assertSame( '', $links[2]->getAttribute( 'rel' ) );
	}

	public function test_a_link_without_a_value_is_not_shown(): void {
		$values                 = $this->full_contact();
		$values['linkedin_url'] = '';
		$this->set_contact( $values );

		$this->assertSame( array( 'WhatsApp', 'Email' ), array_map( fn( $a ) => $this->label( $a ), $this->links( $this->render() ) ) );

		$this->set_contact( array() );
		$html = $this->render();
		$this->assertSame( array(), $this->links( $html ) );
		$this->assertStringNotContainsString( '<nav', $html );
		$this->assertStringContainsString( '© ' . wp_date( 'Y' ), $html );
	}

	public function test_email_shows_the_address_when_there_is_room_and_email_otherwise(): void {
		$this->set_contact( $this->full_contact() );
		$email = $this->links( $this->render() )[2];

		$full  = null;
		$short = null;
		foreach ( $email->getElementsByTagName( 'span' ) as $span ) {
			if ( 'site-footer__full' === $span->getAttribute( 'class' ) ) {
				$full = trim( $span->textContent );
			}
			if ( 'site-footer__short' === $span->getAttribute( 'class' ) ) {
				$short = trim( $span->textContent );
			}
		}

		$this->assertSame( 'dave@example.com', $full );
		$this->assertSame( 'Email', $short );

		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/site-footer/style.css' );
		$this->assertStringContainsString( '@media (min-width: 601px)', $css );
	}

	public function test_urls_are_escaped(): void {
		$this->set_contact( array( 'email' => 'a"b@example.com' ) );

		$this->assertStringNotContainsString( 'a"b', $this->render() );
	}

	public function test_footer_part_uses_the_block_and_keeps_its_rule(): void {
		$footer = (string) file_get_contents( $this->theme_dir() . '/parts/footer.html' );

		$this->assertStringContainsString( '<!-- wp:devportfolio/site-footer /-->', $footer );
		$this->assertStringNotContainsString( 'wp:site-title', $footer );
		$this->assertStringContainsString( 'border-top-color:var(--wp--preset--color--line)', $footer );
	}

	public function test_footer_is_as_wide_as_the_header_so_the_copyright_lines_up_with_the_title(): void {
		$json = json_decode( (string) file_get_contents( $this->theme_dir() . '/theme.json' ), true );
		$css  = (string) ( $json['styles']['css'] ?? '' );

		$this->assertStringContainsString( 'footer.wp-block-template-part > .wp-block-group { --wp--style--global--content-size: 1440px; }', $css );
	}

	public function test_css_contract(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/site-footer/style.css' );

		$this->assertMatchesRegularExpression( '/\.site-footer__link \{[^}]*min-height: 44px;/', $css );
		$this->assertStringContainsString( '@media (max-width: 480px)', $css );
	}
}
