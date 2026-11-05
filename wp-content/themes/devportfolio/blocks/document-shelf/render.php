<?php
/**
 * Server render of the documents shelf (DPD-016).
 *
 * Contract with document-categories/view.js: the shelf carries
 * `data-documents-shelf`, every item is a direct child carrying
 * `data-document-categories`, and the planks are added by that script.
 * The whole item opens the document modal through the title button
 * (`data-document-id`, stretched over the item). Every item has its own
 * `view-transition-name` so a category change animates each book (see style.css).
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = get_posts(
	array(
		'post_type'      => 'document',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
		'no_found_rows'  => true,
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

// Settings > Documents > "Show the title under each book" (off by default, so titles are hidden).
$devportfolio_hide_titles = ! ( function_exists( 'cptdp_get_documents_settings' ) && cptdp_get_documents_settings( 'show_book_title' ) );

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class'                => 'documents-shelf' . ( $devportfolio_hide_titles ? ' documents-shelf--hide-titles' : '' ),
		'data-documents-shelf' => '',
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_id ) :
		$devportfolio_item = devportfolio_document_shelf_item( $devportfolio_id );
		if ( null === $devportfolio_item ) {
			continue;
		}

		$devportfolio_cover = ( new WP_Block(
			array( 'blockName' => 'devportfolio/document-book' ),
			array( 'postId' => $devportfolio_id )
		) )->render();
		$devportfolio_tags = ( new WP_Block(
			array( 'blockName' => 'devportfolio/software-tags' ),
			array( 'postId' => $devportfolio_id )
		) )->render();
		?>
		<article class="documents-item" style="view-transition-name:documents-item-<?php echo esc_attr( (string) $devportfolio_id ); ?>" data-document-categories="<?php echo esc_attr( implode( ' ', $devportfolio_item['categories'] ) ); ?>">
			<?php echo $devportfolio_cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the document-book block. ?>
			<div class="documents-item__info">
				<h3 class="documents-item__title"><button type="button" class="documents-item__open" data-document-id="<?php echo esc_attr( (string) $devportfolio_id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: document title, 2: format label such as "PDF · 24 pp". */ _x( 'Open %1$s, %2$s', 'document shelf item', 'devportfolio' ), $devportfolio_item['title'], $devportfolio_item['format'] ) ); ?>"><?php echo esc_html( $devportfolio_item['title'] ); ?></button></h3>
				<?php if ( '' !== $devportfolio_item['description'] ) : ?>
					<p class="documents-item__desc"><?php echo esc_html( $devportfolio_item['description'] ); ?></p>
				<?php endif; ?>
				<?php echo $devportfolio_tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the software-tags block. ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>
