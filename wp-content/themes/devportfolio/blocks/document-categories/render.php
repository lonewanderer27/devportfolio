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
$devportfolio_list_id = wp_unique_id( 'documents-categories-list-' );
$devportfolio_active_name = _x( 'All', 'document category filter', 'devportfolio' );
foreach ( $devportfolio_terms as $devportfolio_term ) {
	if ( $devportfolio_term->slug === $devportfolio_active ) {
		$devportfolio_active_name = $devportfolio_term->name;
		break;
	}
}
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>>
	<span class="documents-categories__label" aria-hidden="true"><?php echo esc_html_x( 'Category:', 'document category dropdown; visible label beside the active category', 'devportfolio' ); ?></span>
	<button type="button" class="documents-categories__trigger<?php echo '' !== $devportfolio_active ? ' is-active' : ''; ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $devportfolio_list_id ); ?>">
		<span class="documents-categories__trigger-prefix"><?php echo esc_html_x( 'Category:', 'document category dropdown; read by screen readers before the active category', 'devportfolio' ); ?> </span>
		<span class="documents-categories__trigger-label"><?php echo esc_html( $devportfolio_active_name ); ?></span>
		<svg class="documents-categories__trigger-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
	</button>
	<div class="documents-categories__list" id="<?php echo esc_attr( $devportfolio_list_id ); ?>">
		<button type="button" class="documents-categories__pill" data-filter="" aria-pressed="<?php echo '' === $devportfolio_active ? 'true' : 'false'; ?>"><?php echo esc_html_x( 'All', 'document category filter', 'devportfolio' ); ?></button>
		<?php foreach ( $devportfolio_terms as $devportfolio_term ) : ?>
			<button type="button" class="documents-categories__pill" data-filter="<?php echo esc_attr( $devportfolio_term->slug ); ?>" aria-pressed="<?php echo $devportfolio_term->slug === $devportfolio_active ? 'true' : 'false'; ?>"><?php echo esc_html( $devportfolio_term->name ); ?></button>
		<?php endforeach; ?>
	</div>
</div>
