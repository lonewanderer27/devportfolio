<?php
/**
 * Server render of the Brand Collaterals masonry (DPC-006, DPC-014).
 *
 * Contract with view.js: the grid carries `data-collaterals-grid`, every item is a direct child
 * `article.collaterals-item` with `data-collateral-categories` and `data-ratio`. The title button is
 * the one real control of an item (`data-collateral-id`, its ::after covers the item). Each item has
 * its own `view-transition-name`, so a category change animates the items (see style.css).
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'collateral',
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

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class'                 => 'collaterals-grid',
		'data-collaterals-grid' => '',
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_id ) :
		$devportfolio_item = devportfolio_collateral_item( $devportfolio_id );
		if ( null === $devportfolio_item ) {
			continue;
		}

		$devportfolio_preview = $devportfolio_item['preview'];
		$devportfolio_title   = $devportfolio_item['title'];
		$devportfolio_meta    = $devportfolio_item['meta'];
		$devportfolio_label   = '' !== $devportfolio_meta
			/* translators: 1: collateral title, 2: details such as "Flyer · A4 · 2 pp". */
			? sprintf( _x( 'Open %1$s, %2$s', 'collateral item', 'devportfolio' ), $devportfolio_title, $devportfolio_meta )
			/* translators: %s: collateral title. */
			: sprintf( _x( 'Open %s', 'collateral item', 'devportfolio' ), $devportfolio_title );

		$devportfolio_sizes = 'auto, (max-width: 700px) 50vw, 290px';
		?>
		<article class="collaterals-item" data-collateral-categories="<?php echo esc_attr( implode( ' ', $devportfolio_item['categories'] ) ); ?>" style="view-transition-name:collaterals-item-<?php echo esc_attr( (string) $devportfolio_id ); ?>;--collaterals-ratio:<?php echo esc_attr( (string) $devportfolio_preview['ratio'] ); ?>" data-ratio="<?php echo esc_attr( (string) $devportfolio_preview['ratio'] ); ?>">
			<div class="collaterals-item__preview">
				<span class="collaterals-item__blank" aria-hidden="true">1</span>
				<?php if ( 'page' === $devportfolio_preview['kind'] ) : ?>
					<img class="collaterals-item__img" src="<?php echo esc_url( $devportfolio_preview['src'] ); ?>" srcset="<?php echo esc_attr( $devportfolio_preview['srcset'] ); ?>" sizes="<?php echo esc_attr( $devportfolio_sizes ); ?>" width="<?php echo esc_attr( (string) $devportfolio_preview['width'] ); ?>" height="<?php echo esc_attr( (string) $devportfolio_preview['height'] ); ?>" alt="" loading="lazy" decoding="async">
				<?php elseif ( 'featured' === $devportfolio_preview['kind'] ) : ?>
					<?php
					echo wp_get_attachment_image(
						$devportfolio_preview['featured_id'],
						'medium_large',
						false,
						array(
							'class'    => 'collaterals-item__img',
							'sizes'    => $devportfolio_sizes,
							'alt'      => '',
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					);
					?>
				<?php endif; ?>
			</div>
			<div class="collaterals-item__info">
				<h3 class="collaterals-item__title"><button type="button" class="collaterals-item__open" data-collateral-id="<?php echo esc_attr( (string) $devportfolio_id ); ?>" aria-label="<?php echo esc_attr( $devportfolio_label ); ?>"><?php echo esc_html( $devportfolio_title ); ?></button></h3>
				<?php if ( '' !== $devportfolio_meta ) : ?>
					<p class="collaterals-item__meta"><?php echo esc_html( $devportfolio_meta ); ?></p>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>
