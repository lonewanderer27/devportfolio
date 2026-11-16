<?php
/**
 * Server render of the Videos grid (DPV-006, DPV-007, DPV-008).
 *
 * Contract with view.js: the grid carries `data-videos-grid`; every item is a direct child
 * `article.videos-item` with `data-video-categories` (space-separated slugs) and, only for a video that
 * can preview, `data-preview-src` (plus `data-preview-clip` when the source is the stored 6 s clip).
 * The one control of an item is the empty `button.videos-item__open[data-video-id]`; nothing visible
 * carries the title. No videos renders nothing (the empty state is DPV-027).
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'video',
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
		'class'            => 'videos-grid',
		'data-videos-grid' => '',
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_id ) :
		$devportfolio_item = devportfolio_video_item( $devportfolio_id );
		if ( null === $devportfolio_item ) {
			continue;
		}

		$devportfolio_preview = $devportfolio_item['preview'];
		?>
		<article class="videos-item" style="view-transition-name:videos-item-<?php echo esc_attr( (string) $devportfolio_id ); ?>" data-video-categories="<?php echo esc_attr( implode( ' ', $devportfolio_item['categories'] ) ); ?>"<?php if ( '' !== $devportfolio_preview['url'] ) : ?> data-preview-src="<?php echo esc_url( $devportfolio_preview['url'] ); ?>"<?php endif; ?><?php if ( $devportfolio_preview['clip'] ) : ?> data-preview-clip<?php endif; ?>>
			<?php
			if ( $devportfolio_item['poster_id'] > 0 ) {
				echo wp_get_attachment_image(
					$devportfolio_item['poster_id'],
					'medium_large',
					false,
					array(
						'class'    => 'videos-item__img',
						'sizes'    => '(max-width: 620px) 34vw, 190px',
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
			}
			?>
			<span class="videos-item__bar" aria-hidden="true"></span>
			<span class="videos-item__mute" aria-hidden="true"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg></span>
			<button type="button" class="videos-item__open" data-video-id="<?php echo esc_attr( (string) $devportfolio_id ); ?>" aria-label="<?php echo esc_attr( $devportfolio_item['title'] ); ?>"></button>
		</article>
	<?php endforeach; ?>
</div>
