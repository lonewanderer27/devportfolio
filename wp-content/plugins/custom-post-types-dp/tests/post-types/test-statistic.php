<?php
/**
 * Statistic post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group statistic
 */
class CPTDP_Test_Statistic extends CPTDP_CPT_Test_Case {

	protected function post_type(): string {
		return 'statistic';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'statistics',
			'menu_position' => 21,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'taxonomies'    => array(),
			'columns'       => array(),
			'default_order' => false,
		);
	}
}
