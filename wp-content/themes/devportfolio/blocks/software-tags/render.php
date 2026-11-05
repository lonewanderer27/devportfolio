<?php
/**
 * Server render of the software tags (DPD-016).
 *
 * Reusable on any post type that stores `cptdp_software_tools` (document,
 * video, collateral). Every tool is rendered; at 480px and below the tools
 * after the first `limit` are hidden by CSS and a "+N" tag is shown instead.
 * Renders nothing when the post has no published software tools.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$devportfolio_tools   = $devportfolio_post_id > 0 ? devportfolio_software_tool_names( $devportfolio_post_id ) : array();

if ( empty( $devportfolio_tools ) ) {
	return;
}

$devportfolio_limit = isset( $attributes['limit'] ) ? max( 1, (int) $attributes['limit'] ) : 2;
$devportfolio_extra = max( 0, count( $devportfolio_tools ) - $devportfolio_limit );
?>
<ul <?php echo get_block_wrapper_attributes( array( 'class' => 'software-tags' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php foreach ( $devportfolio_tools as $devportfolio_index => $devportfolio_tool ) : ?>
		<li class="software-tags__item<?php echo $devportfolio_index >= $devportfolio_limit ? ' software-tags__item--extra' : ''; ?>"><?php echo esc_html( $devportfolio_tool ); ?></li>
	<?php endforeach; ?>
	<?php if ( $devportfolio_extra > 0 ) : ?>
		<li class="software-tags__more" title="<?php echo esc_attr( implode( ', ', array_slice( $devportfolio_tools, $devportfolio_limit ) ) ); ?>">+<?php echo esc_html( (string) $devportfolio_extra ); ?></li>
	<?php endif; ?>
</ul>
