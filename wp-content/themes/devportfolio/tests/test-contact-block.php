<?php
/**
 * Tests for the Home Contact scheduler block (DPH-025 to DPH-028).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Contact_Block extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/contact/' . $relative );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/contact' ) ) {
			devportfolio_register_blocks();
		}

		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );
		devportfolio_memo_flush();
	}

	private function service( string $title, int $order ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'service',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => 'publish',
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/contact /-->' );
	}

	/** @return array<string,mixed> */
	private function payload( string $html ): array {
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="contact__data">(.*?)</script>#s', $html, $match ) );

		return json_decode( $match[1], true );
	}

	public function test_the_section_is_the_contact_anchor_with_its_heading(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'id="contact"', $html );
		$this->assertStringContainsString( 'aria-labelledby="contact-heading"', $html );
		$strings = devportfolio_contact_strings();
		$this->assertStringContainsString( '<h2 id="contact-heading" class="contact__heading home-reveal">' . esc_html( $strings['heading'] ) . '</h2>', $html );
		$this->assertStringContainsString( 'data-contact-open', $html );
	}

	public function test_the_nav_contact_link_and_the_section_share_the_anchor(): void {
		$header = (string) file_get_contents( $this->theme_dir() . '/parts/header.html' );

		$this->assertStringContainsString( '"url":"/#contact"', $header );
		$this->assertStringContainsString( 'id="contact"', $this->render() );
	}

	public function test_there_are_four_step_forms_and_no_dialog(): void {
		$html = $this->render();

		foreach ( array( 1, 2, 3, 4 ) as $step ) {
			$this->assertSame( 1, substr_count( $html, 'data-contact-panel="' . $step . '"' ), "step {$step}" );
		}
		$this->assertSame( 4, substr_count( $html, '<form class="contact__panel"' ) );
		$this->assertSame( 4, substr_count( $html, 'novalidate' ) );
		$this->assertSame( 0, substr_count( $html, '<dialog' ) ); // Phones use the card as one modal.
		$this->assertSame( 3, substr_count( $html, 'hidden>' ) - substr_count( $html, 'data-contact-thanks role="status" hidden>' ) );
	}

	public function test_step_one_next_starts_disabled(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '/data-contact-next disabled>/', $html );
	}

	public function test_chips_are_the_top_level_services_then_something_else(): void {
		$first  = $this->service( 'Social Media Marketing', 1 );
		$second = $this->service( 'Digital Marketing', 2 );
		self::factory()->post->create(
			array(
				'post_type'   => 'service',
				'post_title'  => 'Analytics',
				'post_parent' => $first,
				'post_status' => 'publish',
			)
		);
		$this->service( 'Draft Service', 3 );
		wp_update_post( array( 'ID' => $this->service( 'Hidden', 4 ), 'post_status' => 'draft' ) );

		$html = $this->render();

		$this->assertSame( 3 + 1, substr_count( $html, 'data-contact-service' ) );
		$this->assertLessThan( strpos( $html, 'Digital Marketing' ), strpos( $html, 'Social Media Marketing' ) );
		$this->assertStringNotContainsString( '>Analytics<', $html );
		$this->assertStringNotContainsString( '>Hidden<', $html );
		$this->assertStringContainsString( 'value="' . $first . '"', $html );
		$this->assertStringContainsString( 'value="' . $second . '"', $html );
		$this->assertStringContainsString( 'value="other"', $html );
		$this->assertGreaterThan( strpos( $html, 'Draft Service' ), strpos( $html, 'Something else' ) );
	}

	public function test_the_payload_carries_the_endpoint_window_slots_and_strings(): void {
		$payload = $this->payload( $this->render() );
		$window  = cptdp_contact_window();

		$this->assertStringContainsString( 'cptdp/v1/contact-request', $payload['endpoint'] );
		$this->assertSame( $window['first'], $payload['first'] );
		$this->assertSame( $window['last'], $payload['last'] );
		$this->assertSame( $window['today'], $payload['today'] );
		$this->assertCount( 6, $payload['slots'] );
		$this->assertSame( '10:00', $payload['slots'][0] );
		$this->assertSame( 'other', end( $payload['services'] )['id'] );
		$strings = devportfolio_contact_strings();
		$this->assertSame( $strings['heading'], $payload['strings']['heading'] );
		$this->assertStringContainsString( '<p class="contact__lead home-reveal">' . esc_html( $strings['lead'] ) . '</p>', $this->render() );
	}

	public function test_a_service_title_cannot_close_the_script_element(): void {
		// Created as an administrator: for a logged-out author WordPress's kses filter would strip the tag before the
		// block sees it, and the test would prove nothing.
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->service( 'Bad </script><b>x</b> & "q"', 1 );

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '</script>' ) );
		$this->assertStringNotContainsString( '</script><b>', $html );
		$payload = $this->payload( $html );
		$this->assertStringContainsString( '</script>', $payload['services'][0]['title'] );
	}

	public function test_the_honeypot_is_hidden_from_people_and_assistive_tech(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '/<div class="contact__hp" aria-hidden="true">/', $html );
		$this->assertMatchesRegularExpression( '/name="website" tabindex="-1" autocomplete="off"/', $html );
	}

	public function test_the_noscript_line_shows_the_email(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '#<noscript>.*mailto:.*</noscript>#s', $html );

		delete_option( 'cptdp_contact' );
		$this->assertStringNotContainsString( 'mailto:', $this->render() );
	}

	public function test_fields_are_labelled_and_errors_are_live_regions(): void {
		$html = $this->render();

		foreach ( array( 'name', 'email', 'message' ) as $field ) {
			$this->assertStringContainsString( 'for="contact-' . $field . '"', $html );
			$this->assertStringContainsString( 'id="contact-' . $field . '"', $html );
		}
		$this->assertSame( 4, substr_count( $html, 'role="alert"' ) );
		$this->assertSame( 4, substr_count( $html, 'data-contact-title' ) );
		$this->assertSame( 4, substr_count( $html, 'tabindex="-1" data-contact-title' ) );
	}

	public function test_every_string_the_scripts_and_the_render_use_exists(): void {
		$strings = devportfolio_contact_strings();

		$used = array();
		foreach ( array( 'view.js', 'picker.js', 'steps.js' ) as $file ) {
			preg_match_all( '/strings\.(\w+)/', $this->read( $file ), $found );
			$used = array_merge( $used, $found[1] );
		}
		preg_match_all( "/\\\$s\\['(\w+)'\\]/", $this->read( 'render.php' ), $found );
		$used = array_unique( array_merge( $used, $found[1] ) );

		$this->assertNotEmpty( $used );
		foreach ( $used as $key ) {
			$this->assertArrayHasKey( $key, $strings, "strings.{$key} is used but not defined." );
			$this->assertNotSame( '', $strings[ $key ], "strings.{$key} is empty." );
		}
	}

	public function test_every_data_hook_the_scripts_query_is_in_the_markup_or_built_by_the_scripts(): void {
		$render = $this->read( 'render.php' );
		$built  = array( 'data-edit', 'data-key' ); // Added by the scripts themselves.

		foreach ( array( 'view.js', 'picker.js' ) as $file ) {
			preg_match_all( '/\[(data-contact[\w-]*|data-edit|data-key)[\]=]/', $this->read( $file ), $found );
			foreach ( array_unique( $found[1] ) as $hook ) {
				if ( in_array( $hook, $built, true ) ) {
					continue;
				}
				$this->assertStringContainsString( $hook, $render, "{$file} queries [{$hook}] but render.php never writes it." );
			}
		}
	}

	public function test_the_page_sends_the_visitor_zone_and_never_a_utc_instant(): void {
		$js = $this->read( 'view.js' );

		$this->assertStringContainsString( 'timezone: visitorZone', $js );
		$this->assertStringContainsString( 'resolvedOptions().timeZone', $js );
		// slotInstant() only words the time pills for the visitor; the request never carries an instant, the server makes it.
		$this->assertDoesNotMatchRegularExpression( '/slot_utc|toISOString/', $js );
		$this->assertStringContainsString( 'date: state.date,', $js );
	}

	public function test_the_mobile_query_is_800px_in_the_script_and_the_css(): void {
		$this->assertStringContainsString( "const MOBILE_QUERY = '(max-width: 800px)';", $this->read( 'view.js' ) );
		$this->assertStringContainsString( '@media (max-width: 800px)', $this->read( 'style.css' ) );
	}

	public function test_the_script_keeps_nothing_in_the_browser_and_uses_the_shared_history(): void {
		$js = $this->read( 'view.js' ) . $this->read( 'picker.js' );

		$this->assertDoesNotMatchRegularExpression( '/localStorage|sessionStorage|indexedDB|document\.cookie/', $js );
		$this->assertStringContainsString( "from '../shared/sheet-back.js'", $js );
		$this->assertMatchesRegularExpression( '/const history = createSheetHistory\(/', $js );
	}

	public function test_the_css_has_the_phone_rules_and_motion_guards(): void {
		$css = $this->read( 'style.css' );

		$this->assertStringContainsString( '.contact:not([data-ready]) .contact__card', $css );
		$this->assertStringContainsString( '.contact.is-modal .contact__card', $css );
		$this->assertStringContainsString( 'position: sticky', $css );
		$this->assertStringContainsString( 'html:has(.contact.is-modal)', $css );
		$this->assertStringContainsString( '@media (prefers-reduced-motion: no-preference)', $css );
		$this->assertStringContainsString( '@media (prefers-reduced-motion: reduce)', $css );
		$this->assertStringContainsString( '.contact [hidden]', $css );
	}

	public function test_the_motion_is_only_transform_and_opacity_and_off_for_reduced_motion(): void {
		$css = $this->read( 'style.css' );

		$this->assertStringContainsString( '@media (prefers-reduced-motion: no-preference)', $css );
		$this->assertStringContainsString( '@keyframes contact-modal-in', $css );
		$this->assertStringContainsString( '.contact.is-modal .contact__card', $css );
		$this->assertStringContainsString( '.contact__panel:not([hidden])', $css );
		$this->assertStringContainsString( '.contact__thanks:not([hidden])', $css );
		$this->assertStringContainsString( '[data-contact-send]:disabled', $css );
	}

	public function test_the_block_renders_nothing_without_the_plugin_config(): void {
		$file = $this->read( 'render.php' );

		$this->assertStringContainsString( 'if ( empty( $devportfolio_config ) )', $file );
		$this->assertStringContainsString( "function_exists( 'cptdp_contact_config' )", (string) file_get_contents( $this->theme_dir() . '/functions.php' ) );
	}
}
