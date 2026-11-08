<?php
/**
 * Document Category singular name form tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group taxonomies
 */
class CPTDP_Test_Document_Category_Singular extends CPTDP_Test_Case {

	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down(): void {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_add_form_contains_the_field_and_nonce(): void {
		ob_start();
		cptdp_document_category_add_form_fields();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="cptdp_singular_name"', $html );
		$this->assertStringContainsString( 'name="cptdp_document_category_singular_nonce"', $html );
		$this->assertStringContainsString( 'Operations Doc', $html );
	}

	public function test_edit_form_escapes_the_existing_value(): void {
		$inserted = wp_insert_term( 'Operations Docs', 'document_category' );
		update_term_meta( $inserted['term_id'], 'cptdp_singular_name', 'Doc "test"' );

		ob_start();
		cptdp_document_category_edit_form_fields( get_term( $inserted['term_id'], 'document_category' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="Doc &quot;test&quot;"', $html );
	}

	public function test_save_creates_updates_and_clears_the_meta(): void {
		$inserted = wp_insert_term( 'Operations Docs', 'document_category' );
		$_POST    = array(
			'cptdp_singular_name'           => 'Operations Doc',
			'cptdp_document_category_singular_nonce' => wp_create_nonce( 'cptdp_document_category_singular_name' ),
		);

		cptdp_save_document_category_singular_name( $inserted['term_id'] );
		$this->assertSame( 'Operations Doc', get_term_meta( $inserted['term_id'], 'cptdp_singular_name', true ) );

		$_POST['cptdp_singular_name'] = '   ';
		cptdp_save_document_category_singular_name( $inserted['term_id'] );
		$this->assertSame( '', get_term_meta( $inserted['term_id'], 'cptdp_singular_name', true ) );
	}

	public function test_save_requires_a_valid_nonce_and_capability(): void {
		$inserted = wp_insert_term( 'Operations Docs', 'document_category' );
		$_POST    = array( 'cptdp_singular_name' => 'Operations Doc' );
		cptdp_save_document_category_singular_name( $inserted['term_id'] );
		$this->assertSame( '', get_term_meta( $inserted['term_id'], 'cptdp_singular_name', true ) );

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$_POST['cptdp_document_category_singular_nonce'] = wp_create_nonce( 'cptdp_document_category_singular_name' );
		cptdp_save_document_category_singular_name( $inserted['term_id'] );
		$this->assertSame( '', get_term_meta( $inserted['term_id'], 'cptdp_singular_name', true ) );
	}
}
