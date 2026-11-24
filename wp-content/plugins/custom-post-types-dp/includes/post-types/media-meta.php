<?php
/**
 * Meta helpers shared by the Document, Collateral and Video post types: the URL, Software Tools and
 * Post Statistics sanitisers and the Software Tools, Post Statistics and Highlight meta boxes.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize a URL: http(s) only, empty string when invalid.
 *
 * @param mixed $url Raw value.
 */
function cptdp_media_sanitize_url( $url ): string {
	return esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
}

/**
 * Sanitize Software Tools: unique IDs of existing `software_tool` posts.
 *
 * @param mixed $ids Raw value.
 * @return int[]
 */
function cptdp_media_sanitize_software_tools( $ids ): array {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

	return array_values(
		array_filter(
			$ids,
			static function ( int $id ): bool {
				return 'software_tool' === get_post_type( $id );
			}
		)
	);
}

/**
 * Sanitize Post Statistics: rows of `label` and `value` text.
 *
 * Drops non-array rows and rows with both fields empty, then reindexes.
 *
 * @param mixed $rows Raw value.
 * @return array<int, array{label: string, value: string}>
 */
function cptdp_media_sanitize_statistics( $rows ): array {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$clean = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
		$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';

		if ( '' === $label && '' === $value ) {
			continue;
		}

		$clean[] = array(
			'label' => $label,
			'value' => $value,
		);
	}

	return $clean;
}

/**
 * Render the Software Tools meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_media_render_software_tools_box( WP_Post $post ): void {
	$selected = array_map( 'absint', (array) get_post_meta( $post->ID, 'cptdp_software_tools', true ) );
	$tools    = get_posts(
		array(
			'post_type'      => 'software_tool',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	if ( ! $tools ) {
		printf(
			'<p>%s <a href="%s">%s</a></p>',
			esc_html__( 'No software tools yet.', 'custom-post-types-dp' ),
			esc_url( admin_url( 'post-new.php?post_type=software_tool' ) ),
			esc_html__( 'Add a Software Tool', 'custom-post-types-dp' )
		);
		return;
	}

	foreach ( $tools as $tool ) {
		printf(
			'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="cptdp_software_tools[]" value="%1$d" %2$s> %3$s</label>',
			(int) $tool->ID,
			checked( in_array( $tool->ID, $selected, true ), true, false ),
			esc_html( get_the_title( $tool ) )
		);
	}
}

/**
 * Render one Post Statistics row.
 *
 * @param string $index     Row index, or a placeholder for the template row.
 * @param string $label     Label.
 * @param string $value     Value.
 * @param string $post_type Post type being edited (videos get view-count placeholders).
 */
function cptdp_media_render_statistics_row( string $index, string $label, string $value, string $post_type = '' ): void {
	$label_hint = 'video' === $post_type ? __( 'e.g. Views', 'custom-post-types-dp' ) : __( 'e.g. Downloads', 'custom-post-types-dp' );
	$value_hint = 'video' === $post_type ? __( 'e.g. 12K', 'custom-post-types-dp' ) : __( 'e.g. 1.2K', 'custom-post-types-dp' );
	?>
	<div class="cptdp-stat-row" style="display:flex;gap:8px;margin-bottom:8px">
		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][label]" placeholder="<?php echo esc_attr( $label_hint ); ?>" value="<?php echo esc_attr( $label ); ?>">
		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][value]" placeholder="<?php echo esc_attr( $value_hint ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<button type="button" class="button cptdp-stat-remove"><?php esc_html_e( 'Remove', 'custom-post-types-dp' ); ?></button>
	</div>
	<?php
}

/**
 * Render the Post Statistics meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_media_render_statistics_box( WP_Post $post ): void {
	$rows = cptdp_media_sanitize_statistics( get_post_meta( $post->ID, 'cptdp_post_statistics', true ) );
	?>
	<div id="cptdp-stat-rows">
		<?php
		foreach ( $rows as $i => $row ) {
			cptdp_media_render_statistics_row( (string) $i, $row['label'], $row['value'], $post->post_type );
		}
		?>
	</div>
	<template id="cptdp-stat-template">
		<?php cptdp_media_render_statistics_row( '__INDEX__', '', '', $post->post_type ); ?>
	</template>
	<p><button type="button" class="button" id="cptdp-stat-add"><?php esc_html_e( 'Add statistic', 'custom-post-types-dp' ); ?></button></p>
	<?php
}

/**
 * Render the Highlight meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_media_render_highlight_box( WP_Post $post ): void {
	?>
	<label>
		<input type="checkbox" name="cptdp_highlight" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ) ); ?>>
		<?php esc_html_e( 'Mark as highlight (Selected work)', 'custom-post-types-dp' ); ?>
	</label>
	<?php
}
