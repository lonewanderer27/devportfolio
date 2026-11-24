<?php
/**
 * Server render of the Home "Selected work" cards (DPH-010 to DPH-017, DPH-019 to DPH-021).
 *
 * Three cards in a fixed order: Documents, Videos, Brand collaterals. What each shows comes from the posts the owner
 * ticked "Mark as highlight (Selected work)" on (devportfolio_work_picks()); nothing is created for Home.
 *
 * Contract with view.js: `.work-card--documents` holds `.work-docs__item[data-role]` (the books), and
 * `.work-card--videos` holds `.work-strip__frame[data-role]` (the frames, with `data-length` and, when the video
 * has a preview clip or file, `data-preview-src`). The Collaterals card has no script.
 *
 * Contract with the modal blocks (placed next to this block in templates/front-page.html): a book's button carries
 * `data-document-id`, a frame's button `data-video-id` and a piece's button `data-collateral-id`; those blocks open
 * their viewers from them. Only the front book and the active frame are reachable (the others are `inert`).
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_books  = devportfolio_work_picks( 'document', 4 );
$devportfolio_clips  = devportfolio_work_picks( 'video', 5 );
$devportfolio_pieces = array();
foreach ( devportfolio_work_picks( 'collateral', 6 ) as $devportfolio_id ) {
	$devportfolio_item = devportfolio_collateral_item( $devportfolio_id );
	if ( null === $devportfolio_item ) {
		continue;
	}

	$devportfolio_pieces[] = array(
		'id'      => $devportfolio_id,
		'rank'    => count( $devportfolio_pieces ),
		'title'   => html_entity_decode( $devportfolio_item['title'], ENT_QUOTES, 'UTF-8' ),
		'ratio'   => (float) $devportfolio_item['preview']['ratio'],
		'preview' => $devportfolio_item['preview'],
	);
}
$devportfolio_columns = devportfolio_work_piece_columns( $devportfolio_pieces );

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'work' ) );
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>

	<article class="work-card work-card--documents home-reveal">
		<?php
		echo devportfolio_work_card_text( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
			_x( 'Documents', 'selected work card', 'devportfolio' ),
			_x( 'SOPs, trackers, travel plans and reports.', 'selected work card', 'devportfolio' ),
			_x( 'View all documents →', 'selected work card', 'devportfolio' ),
			devportfolio_work_archive_url( 'document' )
		);
		?>
		<?php if ( ! empty( $devportfolio_books ) ) : ?>
			<div class="work-card__visual">
				<div class="work-docs">
					<?php
					foreach ( $devportfolio_books as $devportfolio_index => $devportfolio_id ) :
						$devportfolio_role = devportfolio_work_book_role( $devportfolio_index, count( $devportfolio_books ) );
						$devportfolio_cover = ( new WP_Block(
							array( 'blockName' => 'devportfolio/document-book' ),
							array( 'postId' => $devportfolio_id )
						) )->render();
						?>
						<div class="work-docs__item" data-role="<?php echo esc_attr( $devportfolio_role ); ?>"<?php echo 'front' === $devportfolio_role ? '' : ' inert'; ?>>
							<div class="work-docs__scale"><?php echo $devportfolio_cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the document-book block. ?></div>
							<button type="button" class="work-docs__open" data-document-id="<?php echo esc_attr( (string) $devportfolio_id ); ?>" aria-label="<?php echo esc_attr( html_entity_decode( get_the_title( $devportfolio_id ), ENT_QUOTES, 'UTF-8' ) ); ?>"></button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</article>

	<article class="work-card work-card--videos home-reveal">
		<?php
		echo devportfolio_work_card_text( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
			_x( 'Videos', 'selected work card', 'devportfolio' ),
			_x( 'Short-form edits, social content and AI clips.', 'selected work card', 'devportfolio' ),
			_x( 'View all videos →', 'selected work card', 'devportfolio' ),
			devportfolio_work_archive_url( 'video' )
		);
		?>
		<?php if ( ! empty( $devportfolio_clips ) ) : ?>
			<div class="work-card__visual">
				<div class="work-strip">
					<?php
					foreach ( $devportfolio_clips as $devportfolio_index => $devportfolio_id ) :
						$devportfolio_clip = devportfolio_video_item( $devportfolio_id );
						if ( null === $devportfolio_clip ) {
							continue;
						}
						$devportfolio_role = devportfolio_work_frame_role( $devportfolio_index, count( $devportfolio_clips ) );
						$devportfolio_secs = (int) get_post_meta( $devportfolio_id, 'cptdp_length', true );
						?>
						<div class="work-strip__frame" data-role="<?php echo esc_attr( $devportfolio_role ); ?>"<?php echo 'active' === $devportfolio_role ? '' : ' inert'; ?> data-length="<?php echo esc_attr( (string) max( 0, $devportfolio_secs ) ); ?>"<?php if ( '' !== $devportfolio_clip['preview']['url'] ) : ?> data-preview-src="<?php echo esc_url( $devportfolio_clip['preview']['url'] ); ?>"<?php endif; ?>>
							<div class="work-strip__art">
								<?php
								if ( $devportfolio_clip['poster_id'] > 0 ) {
									echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core builds and escapes the tag.
										$devportfolio_clip['poster_id'],
										'medium',
										false,
										array(
											'class'    => 'work-strip__poster',
											'sizes'    => '80px',
											'alt'      => '',
											'loading'  => 'lazy',
											'decoding' => 'async',
										)
									);
								}
								?>
								<span class="work-strip__eq" aria-hidden="true"><i></i><i></i><i></i></span>
								<span class="work-strip__time" aria-hidden="true">0:00</span>
								<span class="work-strip__bar" aria-hidden="true"></span>
							</div>
							<button type="button" class="work-strip__open" data-video-id="<?php echo esc_attr( (string) $devportfolio_id ); ?>" aria-label="<?php echo esc_attr( $devportfolio_clip['title'] ); ?>"></button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</article>

	<article class="work-card work-card--collaterals home-reveal">
		<?php
		echo devportfolio_work_card_text( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
			_x( 'Brand collaterals', 'selected work card', 'devportfolio' ),
			_x( 'Brochures, flyers, newsletters, vouchers and coupons.', 'selected work card', 'devportfolio' ),
			_x( 'View all collaterals →', 'selected work card', 'devportfolio' ),
			devportfolio_work_archive_url( 'collateral' )
		);
		?>
		<?php if ( ! empty( $devportfolio_columns ) ) : ?>
			<div class="work-card__visual">
				<div class="work-glimpse">
					<?php foreach ( $devportfolio_columns as $devportfolio_column ) : ?>
						<div class="work-glimpse__col<?php echo $devportfolio_column[0]['rank'] >= 3 ? ' work-glimpse__col--extra' : ''; ?>">
							<?php foreach ( $devportfolio_column as $devportfolio_piece ) : ?>
								<button type="button" class="work-piece<?php echo $devportfolio_piece['rank'] >= 3 ? ' work-piece--extra' : ''; ?>" style="--ratio:<?php echo esc_attr( (string) $devportfolio_piece['ratio'] ); ?>" data-collateral-id="<?php echo esc_attr( (string) $devportfolio_piece['id'] ); ?>" aria-label="<?php echo esc_attr( $devportfolio_piece['title'] ); ?>">
									<?php echo devportfolio_work_piece_art( $devportfolio_piece['preview'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</article>

</div>
