<?php
/**
 * Testimonial post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group testimonial
 */
class CPTDP_Test_Testimonial extends CPTDP_CPT_Test_Case {

	protected function post_type(): string {
		return 'testimonial';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'testimonials',
			'menu_position' => 29,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'taxonomies'    => array(),
			'columns'       => array( 'cptdp_company', 'menu_order' ),
			'default_order' => true,
		);
	}

	private function make(): int {
		return self::factory()->post->create( array( 'post_type' => 'testimonial' ) );
	}

	public function test_company_meta_is_registered(): void {
		$registered = get_registered_meta_keys( 'post', 'testimonial' );

		$this->assertArrayHasKey( 'cptdp_company', $registered );
		$this->assertSame( 'string', $registered['cptdp_company']['type'] );
		$this->assertSame( '', $registered['cptdp_company']['default'] );
		$this->assertTrue( $registered['cptdp_company']['single'] );
		$this->assertTrue( $registered['cptdp_company']['show_in_rest'] );
	}

	public function test_company_is_saved_trimmed_and_stripped_of_markup(): void {
		$this->acting_as( 'editor' );
		$id = $this->make();
		$this->submit(
			'cptdp_testimonial_save_meta',
			$id,
			array(
				'cptdp_company'       => '  <b>Acme</b> Pty  ',
				'cptdp_company_nonce' => wp_create_nonce( 'cptdp_company' ),
			)
		);

		$this->assertSame( 'Acme Pty', get_post_meta( $id, 'cptdp_company', true ) );
	}

	public function test_company_is_removed_when_emptied(): void {
		$this->acting_as( 'editor' );
		$id = $this->make();
		update_post_meta( $id, 'cptdp_company', 'Acme' );
		$this->submit(
			'cptdp_testimonial_save_meta',
			$id,
			array(
				'cptdp_company'       => '',
				'cptdp_company_nonce' => wp_create_nonce( 'cptdp_company' ),
			)
		);

		$this->assertSame( '', get_post_meta( $id, 'cptdp_company', true ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_company' ) );
	}

	public function test_company_is_not_saved_without_a_nonce(): void {
		$id = $this->make();
		$this->submit( 'cptdp_testimonial_save_meta', $id, array( 'cptdp_company' => 'Acme' ) );

		$this->assertSame( '', get_post_meta( $id, 'cptdp_company', true ) );
	}

	public function test_company_column_shows_the_company_or_a_dash(): void {
		$id = $this->make();
		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_company', $id ) );

		update_post_meta( $id, 'cptdp_company', 'Acme & Co' );
		$this->assertSame( 'Acme &amp; Co', $this->render_column( 'cptdp_company', $id ) );
	}
}
