<?php
/**
 * Aspect Ratio taxonomy.
 *
 * Fixed set of seeded terms (16x9, 9x16, 1x1, 4x3), picked with a single
 * select on the Video edit screen.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seeded Aspect Ratio terms, in display order.
 *
 * @return string[]
 */
function cptdp_aspect_ratio_terms(): array {
	return array( '16x9', '9x16', '1x1', '4x3' );
}

/**
 * Register the Aspect Ratio taxonomy.
 *
 * Registered with no object type. The Video post type attaches it via its
 * `taxonomies` argument.
 */
function cptdp_register_aspect_ratio(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Aspect Ratios', 'taxonomy general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Aspect Ratio', 'taxonomy singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Aspect Ratios', 'admin menu', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Aspect Ratios', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Aspect Ratio', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Aspect Ratio', 'custom-post-types-dp' ),
		'update_item'           => esc_html__( 'Update Aspect Ratio', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Aspect Ratio', 'custom-post-types-dp' ),
		'new_item_name'         => esc_html__( 'New Aspect Ratio Name', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Aspect Ratios', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No aspect ratios found.', 'custom-post-types-dp' ),
		'back_to_items'         => esc_html__( '&larr; Go to Aspect Ratios', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Aspect Ratios list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Aspect Ratios list navigation', 'custom-post-types-dp' ),
	);

	register_taxonomy(
		'aspect_ratio',
		array(),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'show_tagcloud'      => false,
			'query_var'          => false,
			'rewrite'            => false,
			'meta_box_cb'        => 'cptdp_aspect_ratio_meta_box',
			'default_term'       => array(
				'name' => '16x9',
				'slug' => '16x9',
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_aspect_ratio' );

/**
 * Seed the four ratio terms once.
 *
 * Guarded by an option so terms an admin deletes are not recreated on every
 * request. Runs after registration (priority 20).
 */
function cptdp_seed_aspect_ratios(): void {
	if ( get_option( 'cptdp_aspect_ratio_seeded' ) ) {
		return;
	}

	foreach ( cptdp_aspect_ratio_terms() as $name ) {
		if ( ! term_exists( $name, 'aspect_ratio' ) ) {
			wp_insert_term( $name, 'aspect_ratio', array( 'slug' => $name ) );
		}
	}

	update_option( 'cptdp_aspect_ratio_seeded', '1' );
}
add_action( 'cptdp_register_post_types', 'cptdp_seed_aspect_ratios', 20 );

/**
 * Render the single-select Aspect Ratio meta box.
 *
 * Non-hierarchical taxonomies save `tax_input[aspect_ratio]` as a
 * comma-separated list of term names. A single select submits one name, so
 * a video holds at most one ratio from the UI.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_aspect_ratio_meta_box( WP_Post $post ): void {
	$terms = get_terms(
		array(
			'taxonomy'   => 'aspect_ratio',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return;
	}

	$order = array_flip( cptdp_aspect_ratio_terms() );

	usort(
		$terms,
		static function ( WP_Term $a, WP_Term $b ) use ( $order ): int {
			$pos_a = $order[ $a->name ] ?? PHP_INT_MAX;
			$pos_b = $order[ $b->name ] ?? PHP_INT_MAX;

			return $pos_a === $pos_b ? strcmp( $a->name, $b->name ) : $pos_a <=> $pos_b;
		}
	);

	$current  = wp_get_object_terms( $post->ID, 'aspect_ratio', array( 'fields' => 'names' ) );
	$selected = ! is_wp_error( $current ) && $current ? $current[0] : '16x9';
	?>
	<p>
		<label class="screen-reader-text" for="cptdp_aspect_ratio"><?php esc_html_e( 'Aspect Ratio', 'custom-post-types-dp' ); ?></label>
		<select id="cptdp_aspect_ratio" name="tax_input[aspect_ratio]" class="widefat">
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->name ); ?>" <?php selected( $selected, $term->name ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}
