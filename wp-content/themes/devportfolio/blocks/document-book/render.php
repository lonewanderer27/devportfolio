<?php
/**
 * Server render of the document book cover (DPD-005, DPD-006).
 *
 * Renders the cover only; the item wrapper, description and tags belong to the shelf (DPD-016).
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();

if ( $devportfolio_post_id <= 0 || 'document' !== get_post_type( $devportfolio_post_id ) ) {
	return;
}

$devportfolio_category = devportfolio_document_category_name( $devportfolio_post_id );

$devportfolio_format = devportfolio_document_format_label(
	get_post_meta( $devportfolio_post_id, 'cptdp_filetype', true ),
	get_post_meta( $devportfolio_post_id, 'cptdp_total_pages', true ),
	devportfolio_document_file_kind( $devportfolio_post_id )
);
$devportfolio_style  = devportfolio_sanitize_cover_style( get_post_meta( $devportfolio_post_id, 'cptdp_cover_style', true ) );

$devportfolio_title  = get_the_title( $devportfolio_post_id );
$devportfolio_length = function_exists( 'devportfolio_document_title_length' ) ? devportfolio_document_title_length( $devportfolio_title ) : 'short';

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class'            => 'documents-book',
		'data-cover-style' => $devportfolio_style,
		'data-document-id' => (string) $devportfolio_post_id,
		'data-title-length' => $devportfolio_length,
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<i class="documents-book__pat" aria-hidden="true"></i>
	<span class="documents-book__cat"><?php echo esc_html( $devportfolio_category ); ?></span>
	<span class="documents-book__ttl"><span class="documents-book__ttl-text"><?php echo esc_html( $devportfolio_title ); ?></span></span>
	<span class="documents-book__ft">
		<span class="documents-book__format"><?php echo esc_html( $devportfolio_format ); ?></span>
		<span class="documents-book__owner"><?php echo esc_html_x( 'DAVE', 'document cover owner mark', 'devportfolio' ); ?></span>
	</span>
</div>
