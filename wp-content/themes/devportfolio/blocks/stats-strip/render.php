<?php
/**
 * Server render of the stats strip (DPH-005): the first three published Statistics.
 *
 * Label = title, value = content as plain text. Plain text only: nothing here is a link or a control.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'statistic',
	array(
		'posts_per_page' => 3,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

$devportfolio_stats = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_value = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $devportfolio_id ) ) );
	if ( '' === $devportfolio_value ) {
		continue;
	}

	$devportfolio_stats[] = array(
		'value' => $devportfolio_value,
		'label' => get_the_title( $devportfolio_id ),
	);
}

if ( empty( $devportfolio_stats ) ) {
	return;
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'stats-strip' ) );
?>
<ul <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php foreach ( $devportfolio_stats as $devportfolio_stat ) : ?>
		<li class="stats-strip__item home-reveal"><b class="stats-strip__value"><?php echo esc_html( $devportfolio_stat['value'] ); ?></b><span class="stats-strip__label"><?php echo esc_html( $devportfolio_stat['label'] ); ?></span></li>
	<?php endforeach; ?>
</ul>
