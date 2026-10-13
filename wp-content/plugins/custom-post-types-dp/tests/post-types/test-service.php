<?php
/**
 * Service post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group service
 */
class CPTDP_Test_Service extends CPTDP_CPT_Test_Case {

	protected function post_type(): string {
		return 'service';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'services',
			'menu_position' => 20,
			'hierarchical'  => true,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'taxonomies'    => array(),
			'columns'       => array(),
			'default_order' => false,
		);
	}

	public function test_services_can_have_parents(): void {
		$parent = self::factory()->post->create( array( 'post_type' => 'service' ) );
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'service',
				'post_parent' => $parent,
			)
		);

		$this->assertSame( $parent, wp_get_post_parent_id( $child ) );
	}
}
