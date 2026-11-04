<?php
/**
 * Render callback for the devportfolio/document-categories block (DPD-002).
 *
 * "All" is synthetic: always first, never stored, and always present. The other
 * pills are the document_category terms in name order (the DPD-007 seeded order),
 * empty terms included. `data-filter` is the term slug; "All" uses an empty value
 * so a real term can never collide with it.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_terms = get_terms(
	array(
		'taxonomy'   => 'document_category',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);

if ( is_wp_error( $devportfolio_terms ) ) {
	return;
}

// Active category from the URL (?category=<slug>), display state only. Anything that is not an existing term slug falls back to All.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display state.
$devportfolio_active = isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '';
if ( ! in_array( $devportfolio_active, wp_list_pluck( $devportfolio_terms, 'slug' ), true ) ) {
	$devportfolio_active = '';
}

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'role'       => 'group',
		'aria-label' => esc_attr__( 'Document categories', 'devportfolio' ),
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>>
	<button type="button" class="documents-categories__pill" data-filter="" aria-pressed="<?php echo '' === $devportfolio_active ? 'true' : 'false'; ?>"><?php echo esc_html_x( 'All', 'document category filter', 'devportfolio' ); ?></button>
	<?php foreach ( $devportfolio_terms as $devportfolio_term ) : ?>
		<button type="button" class="documents-categories__pill" data-filter="<?php echo esc_attr( $devportfolio_term->slug ); ?>" aria-pressed="<?php echo $devportfolio_term->slug === $devportfolio_active ? 'true' : 'false'; ?>"><?php echo esc_html( $devportfolio_term->name ); ?></button>
	<?php endforeach; ?>
</div>
