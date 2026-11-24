<?php
/**
 * Server render of the Home service rows (DPH-007, DPH-008, DPH-009).
 *
 * One row per top-level published Service. Plain text only: no link, no button. The tags are the child Services
 * as chips. No arrow, no lines under the rows.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_order = array(
	'menu_order' => 'ASC',
	'title'      => 'ASC',
);

$devportfolio_ids = devportfolio_published_ids(
	'service',
	array(
		'post_parent' => 0,
		'orderby'     => $devportfolio_order,
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'service-list' ) );
?>
<ul <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_index => $devportfolio_id ) :
		$devportfolio_benefit = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $devportfolio_id ) ) );
		$devportfolio_tags    = devportfolio_published_ids(
			'service',
			array(
				'post_parent' => $devportfolio_id,
				'orderby'     => $devportfolio_order,
			)
		);
		?>
		<li class="service-list__row home-reveal">
			<span class="service-list__number"><?php echo esc_html( str_pad( (string) ( $devportfolio_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
			<div class="service-list__body">
				<h3 class="service-list__title"><?php echo esc_html( get_the_title( $devportfolio_id ) ); ?></h3>
				<?php if ( '' !== $devportfolio_benefit ) : ?>
					<p class="service-list__benefit"><?php echo esc_html( $devportfolio_benefit ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $devportfolio_tags ) ) : ?>
					<ul class="service-list__tags">
						<?php foreach ( $devportfolio_tags as $devportfolio_tag_id ) : ?>
							<li class="service-list__tag"><?php echo esc_html( get_the_title( $devportfolio_tag_id ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
