<?php
/**
 * Render callback for the devportfolio/document-modal block (DPD-009 to DPD-012).
 *
 * One shared <dialog> (header, body, footer) plus a JSON payload of every
 * published document. view.js fills the dialog from the payload when an element
 * with data-document-id is clicked. The body holds the page arrows and the
 * stage where viewer.js builds the open book.
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
		'no_found_rows'  => true,
	)
);

$devportfolio_payload = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_item = devportfolio_document_modal_payload( $devportfolio_id );
	if ( null !== $devportfolio_item ) {
		$devportfolio_payload[ (string) $devportfolio_id ] = $devportfolio_item;
	}
}

$devportfolio_title_id = wp_unique_id( 'documents-modal-title-' );
$devportfolio_pdfjs    = devportfolio_document_pdfjs_urls();
$devportfolio_root     = array(
	'class'       => 'documents-modal-root',
	'data-pdfjs'  => wp_json_encode( (object) ( '' !== $devportfolio_pdfjs['module'] ? $devportfolio_pdfjs : array() ) ),
	'data-limits' => wp_json_encode( (object) devportfolio_document_viewer_limits() ),
	'data-units'  => wp_json_encode( devportfolio_document_unit_strings() ),
);
if ( devportfolio_document_selectable_text() ) {
	// Settings > Documents > Selectable text: the viewer draws pages with pdf.js and a real text layer.
	$devportfolio_root['data-selectable-text'] = '1';
}
$devportfolio_download = '<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>';
?>
<div <?php echo get_block_wrapper_attributes( $devportfolio_root ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<dialog class="documents-modal" aria-labelledby="<?php echo esc_attr( $devportfolio_title_id ); ?>">
		<div class="documents-modal__panel">
			<header class="documents-modal__header">
				<div class="documents-modal__titlebox">
					<h2 class="documents-modal__title" id="<?php echo esc_attr( $devportfolio_title_id ); ?>"></h2>
					<p class="documents-modal__desc"></p>
				</div>
				<div class="documents-modal__meta">
					<ul class="documents-modal__tags" hidden></ul>
					<span class="documents-modal__format" hidden></span>
					<a class="documents-modal__download" role="button">
						<?php echo $devportfolio_download; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<span><?php echo esc_html_x( 'Download', 'document modal button', 'devportfolio' ); ?></span>
					</a>
					<button class="documents-modal__close" type="button" aria-label="<?php echo esc_attr_x( 'Close viewer', 'document modal', 'devportfolio' ); ?>">
						<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
					</button>
				</div>
			</header>
			<div class="documents-modal__body">
				<button class="documents-modal__arrow documents-modal__arrow--prev" type="button" aria-label="<?php echo esc_attr_x( 'Previous page', 'document modal', 'devportfolio' ); ?>">
					<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>
				</button>
				<div class="documents-modal__stage"></div>
				<button class="documents-modal__arrow documents-modal__arrow--next" type="button" aria-label="<?php echo esc_attr_x( 'Next page', 'document modal', 'devportfolio' ); ?>">
					<svg class="documents-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>
				</button>
				<div
					class="documents-modal__state"
					role="status"
					hidden
					data-loading="<?php echo esc_attr_x( 'Loading pages…', 'document modal viewer', 'devportfolio' ); ?>"
					data-unavailable="<?php echo esc_attr_x( 'This document can’t be previewed.', 'document modal viewer', 'devportfolio' ); ?>"
					data-hint="<?php echo esc_attr_x( 'Use Download to open it.', 'document modal viewer', 'devportfolio' ); ?>"
					data-hint-sheet="<?php echo esc_attr_x( 'Spreadsheets can’t always be previewed here. Download the file to open it in Excel.', 'document modal viewer', 'devportfolio' ); ?>"
					data-hint-slide="<?php echo esc_attr_x( 'Presentations can’t always be previewed here. Download the file to open it in PowerPoint.', 'document modal viewer', 'devportfolio' ); ?>"
				>
					<span class="documents-modal__spinner" aria-hidden="true"></span>
					<span class="documents-modal__state-text"></span>
					<span class="documents-modal__state-hint"></span>
				</div>
			</div>
			<footer class="documents-modal__footer" hidden>
				<nav class="documents-modal__pager" aria-label="<?php echo esc_attr_x( 'Pages', 'document modal pager', 'devportfolio' ); ?>" hidden></nav>
				<div class="documents-modal__indicator" hidden>
					<div class="documents-modal__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php echo esc_attr_x( 'Reading progress', 'document modal', 'devportfolio' ); ?>"><i></i></div>
				</div>
			</footer>
		</div>
	</dialog>
	<script type="application/json" class="documents-modal__data"><?php echo wp_json_encode( $devportfolio_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with hex-escaped tags. ?></script>
</div>
