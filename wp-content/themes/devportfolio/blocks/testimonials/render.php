<?php
/**
 * Server render of the Home testimonials (DPH-022).
 *
 * Plain text only. Every quote is in the markup (the first is active); view.js adds the dots and the rotation.
 * Nothing is rendered without a published testimonial, heading included.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'testimonial',
	array(
		'orderby' => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

$devportfolio_quotes = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_text = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $devportfolio_id ) ) );
	if ( '' === $devportfolio_text ) {
		continue;
	}

	$devportfolio_quotes[] = array(
		'quote'   => $devportfolio_text,
		'name'    => html_entity_decode( get_the_title( $devportfolio_id ), ENT_QUOTES, 'UTF-8' ),
		'company' => trim( (string) get_post_meta( $devportfolio_id, 'cptdp_company', true ) ),
	);
}

if ( empty( $devportfolio_quotes ) ) {
	return;
}

$devportfolio_count   = count( $devportfolio_quotes );
$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'testimonials home-section',
		'id'    => 'testimonials',
	)
);
?>
<section <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> aria-labelledby="testimonials-heading">
	<h2 id="testimonials-heading" class="testimonials__heading"><?php echo esc_html_x( 'What clients say', 'Home testimonials heading', 'devportfolio' ); ?></h2>

	<div class="testimonials__stage" data-testimonials-stage aria-live="off">
		<?php foreach ( $devportfolio_quotes as $devportfolio_i => $devportfolio_q ) : ?>
			<figure class="testimonials__slide<?php echo 0 === $devportfolio_i ? ' is-active' : ''; ?>" data-testimonials-slide>
				<blockquote class="testimonials__quote"><p><?php echo esc_html( $devportfolio_q['quote'] ); ?></p></blockquote>
				<figcaption class="testimonials__who">
					<span class="testimonials__name"><?php echo esc_html( $devportfolio_q['name'] ); ?></span>
					<?php if ( '' !== $devportfolio_q['company'] ) : ?>
						<span class="testimonials__company"><?php echo esc_html( $devportfolio_q['company'] ); ?></span>
					<?php endif; ?>
				</figcaption>
			</figure>
		<?php endforeach; ?>
	</div>

	<?php if ( $devportfolio_count > 1 ) : ?>
		<div class="testimonials__dots" role="group" aria-label="<?php esc_attr_e( 'Choose a testimonial', 'devportfolio' ); ?>" data-testimonials-dots hidden>
			<?php for ( $devportfolio_i = 0; $devportfolio_i < $devportfolio_count; $devportfolio_i++ ) : ?>
				<button type="button" class="testimonials__dot"<?php echo 0 === $devportfolio_i ? ' aria-current="true"' : ''; ?> aria-label="<?php echo esc_attr( sprintf( /* translators: 1: position, 2: total */ __( 'Show testimonial %1$d of %2$d', 'devportfolio' ), $devportfolio_i + 1, $devportfolio_count ) ); ?>"></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</section>
