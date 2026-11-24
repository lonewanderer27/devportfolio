<?php
/**
 * Server render of the Home software tools grid (DPH-024).
 *
 * One button per published Software Tool (menu order, then title). The tile image is the tool's featured image, drawn
 * decorative (the visible name labels the button); a tool without one gets its two-letter monogram. Not links.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'software_tool',
	array(
		'orderby' => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'software-tools home-reveal' ) );
?>
<ul <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> data-software-tools>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_id ) :
		$devportfolio_name  = html_entity_decode( get_the_title( $devportfolio_id ), ENT_QUOTES, 'UTF-8' );
		$devportfolio_thumb = (int) get_post_thumbnail_id( $devportfolio_id );
		$devportfolio_img   = $devportfolio_thumb
			? wp_get_attachment_image(
				$devportfolio_thumb,
				'cptdp-tool-icon',
				false,
				array(
					'alt'      => '',
					'class'    => 'software-tools__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			)
			: '';
		?>
		<li class="software-tools__item">
			<button type="button" class="software-tools__tile" aria-pressed="false">
				<?php if ( '' !== $devportfolio_img ) : ?>
					<span class="software-tools__icon"><?php echo $devportfolio_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by core. ?></span>
				<?php else : ?>
					<span class="software-tools__icon software-tools__icon--text" aria-hidden="true"><?php echo esc_html( devportfolio_tool_monogram( $devportfolio_name ) ); ?></span>
				<?php endif; ?>
				<span class="software-tools__name"><?php echo esc_html( $devportfolio_name ); ?></span>
			</button>
		</li>
	<?php endforeach; ?>
</ul>
