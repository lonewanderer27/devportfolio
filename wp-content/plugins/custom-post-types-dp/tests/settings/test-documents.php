<?php
/**
 * Documents settings tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Documents_Settings extends CPTDP_Test_Case {

	public function test_defaults_hide_the_book_title(): void {
		$this->assertSame( array( 'show_book_title' => false ), cptdp_get_documents_settings() );
		$this->assertFalse( cptdp_get_documents_settings( 'show_book_title' ) );
		$this->assertFalse( cptdp_get_documents_settings( 'unknown' ) );
	}

	public function test_saved_value_is_read_and_unknown_keys_dropped(): void {
		update_option(
			'cptdp_documents',
			array(
				'show_book_title' => true,
				'evil'            => 'x',
			)
		);

		$this->assertSame( array( 'show_book_title' => true ), cptdp_get_documents_settings() );
		$this->assertTrue( cptdp_get_documents_settings( 'show_book_title' ) );
	}

	public function test_corrupt_option_falls_back_to_defaults(): void {
		update_option( 'cptdp_documents', 'garbage' );

		$this->assertFalse( cptdp_get_documents_settings( 'show_book_title' ) );
	}

	public function test_sanitize(): void {
		$this->assertSame( array( 'show_book_title' => true ), cptdp_documents_sanitize( array( 'show_book_title' => '1' ) ) );
		$this->assertSame( array( 'show_book_title' => false ), cptdp_documents_sanitize( array( 'show_book_title' => '0' ) ) );
		$this->assertSame( array( 'show_book_title' => false ), cptdp_documents_sanitize( array() ) );
		$this->assertSame( array( 'show_book_title' => false ), cptdp_documents_sanitize( 'x' ) );
	}

	public function test_settings_page_requires_manage_options_and_registers_under_settings(): void {
		global $submenu;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		cptdp_documents_add_menu();

		$slugs = isset( $submenu['options-general.php'] ) ? wp_list_pluck( $submenu['options-general.php'], 2 ) : array();
		$this->assertContains( 'cptdp-documents', $slugs );
	}

	public function test_checkbox_reflects_the_saved_value_and_is_off_by_default(): void {
		ob_start();
		cptdp_documents_render_show_book_title();
		$this->assertStringNotContainsString( 'checked', ob_get_clean() );

		update_option( 'cptdp_documents', array( 'show_book_title' => true ) );
		ob_start();
		cptdp_documents_render_show_book_title();
		$this->assertStringContainsString( 'checked', ob_get_clean() );
	}
}
