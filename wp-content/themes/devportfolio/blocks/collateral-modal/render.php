<?php
/**
 * Render callback for the devportfolio/collateral-modal block (DPC-007 to DPC-011, DPC-021, DPC-022).
 *
 * The desktop dialog (sidebar, body, footer) and the mobile dialog, plus a JSON payload with the
 * published collaterals in the grid's order (`order`) and their sidebar data (`items`, see
 * devportfolio_collateral_modal_payload()). view.js fills the dialogs when a `[data-collateral-id]`
 * is clicked. Every empty region is hidden in the markup, so the page is clean without JavaScript.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = get_posts(
	array(
		'post_type'      => 'collateral',
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

$devportfolio_order = array();
$devportfolio_items = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_item = devportfolio_collateral_modal_payload( $devportfolio_id );
	if ( null === $devportfolio_item ) {
		continue;
	}
	$devportfolio_order[]                       = (int) $devportfolio_id;
	$devportfolio_items[ (string) $devportfolio_id ] = $devportfolio_item;
}

$devportfolio_payload = wp_json_encode(
	array(
		'order' => $devportfolio_order,
		'items' => (object) $devportfolio_items,
	),
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

$devportfolio_title_id    = wp_unique_id( 'collaterals-modal-title-' );
$devportfolio_results_id  = wp_unique_id( 'collaterals-modal-results-' );
$devportfolio_made_id     = wp_unique_id( 'collaterals-modal-made-' );
$devportfolio_next_id     = wp_unique_id( 'collaterals-modal-next-' );
$devportfolio_mobile_id   = wp_unique_id( 'collaterals-mobile-title-' );
$devportfolio_download    = '<svg class="collaterals-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>';
$devportfolio_close_icon  = '<svg class="collaterals-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
$devportfolio_wrapper     = get_block_wrapper_attributes(
	array(
		'class'                 => 'collaterals-modal-root',
		'data-strings'          => wp_json_encode( devportfolio_collateral_modal_strings() ),
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<dialog class="collaterals-modal" aria-labelledby="<?php echo esc_attr( $devportfolio_title_id ); ?>">
		<div class="collaterals-modal__panel">
			<aside class="collaterals-modal__side">
				<div class="collaterals-modal__bar">
					<button class="collaterals-modal__close" type="button" aria-label="<?php echo esc_attr_x( 'Close viewer', 'collateral modal', 'devportfolio' ); ?>"><?php echo $devportfolio_close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
				</div>
				<div class="collaterals-modal__scroll">
					<header class="collaterals-modal__head">
						<p class="collaterals-modal__pills" hidden>
							<span class="collaterals-modal__pill collaterals-modal__pill--cat" hidden></span>
							<span class="collaterals-modal__pill collaterals-modal__pill--size" hidden></span>
						</p>
						<h2 class="collaterals-modal__title" id="<?php echo esc_attr( $devportfolio_title_id ); ?>" tabindex="-1"></h2>
						<p class="collaterals-modal__desc" hidden></p>
					</header>
					<section class="collaterals-modal__results" aria-labelledby="<?php echo esc_attr( $devportfolio_results_id ); ?>" hidden>
						<h3 class="collaterals-modal__caption" id="<?php echo esc_attr( $devportfolio_results_id ); ?>"><?php echo esc_html_x( 'Results', 'collateral modal', 'devportfolio' ); ?></h3>
						<dl class="collaterals-modal__stats"></dl>
					</section>
					<section class="collaterals-modal__tools" aria-labelledby="<?php echo esc_attr( $devportfolio_made_id ); ?>" hidden>
						<h3 class="collaterals-modal__caption" id="<?php echo esc_attr( $devportfolio_made_id ); ?>"><?php echo esc_html_x( 'Made with', 'collateral modal', 'devportfolio' ); ?></h3>
						<ul class="collaterals-modal__toollist"></ul>
					</section>
					<nav class="collaterals-modal__next" aria-labelledby="<?php echo esc_attr( $devportfolio_next_id ); ?>" hidden>
						<h3 class="collaterals-modal__caption" id="<?php echo esc_attr( $devportfolio_next_id ); ?>"><?php echo esc_html_x( 'Next collaterals', 'collateral modal', 'devportfolio' ); ?></h3>
						<ul class="collaterals-modal__nextlist"></ul>
					</nav>
				</div>
				<div class="collaterals-modal__actions">
					<a class="collaterals-modal__download" role="button"><?php echo $devportfolio_download; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php echo esc_html_x( 'Download', 'collateral modal button', 'devportfolio' ); ?></span></a>
					<button class="collaterals-modal__copy" type="button"><?php echo esc_html_x( 'Copy link', 'collateral modal button', 'devportfolio' ); ?></button>
					<input class="collaterals-modal__copyfield" type="text" readonly tabindex="-1" aria-label="<?php echo esc_attr_x( 'Link to this collateral', 'collateral modal', 'devportfolio' ); ?>">
					<span class="collaterals-modal__live" role="status" aria-live="polite"></span>
				</div>
			</aside>
			<div class="collaterals-modal__main">
				<div class="collaterals-modal__body">
					<div class="collaterals-modal__stage"></div>
				</div>
				<footer class="collaterals-modal__footer" hidden></footer>
			</div>
		</div>
	</dialog>
	<dialog class="collaterals-mobile" aria-labelledby="<?php echo esc_attr( $devportfolio_mobile_id ); ?>">
		<div class="collaterals-mobile__panel">
			<header class="collaterals-mobile__header">
				<button class="collaterals-mobile__close" type="button" aria-label="<?php echo esc_attr_x( 'Close viewer', 'collateral modal', 'devportfolio' ); ?>"><?php echo $devportfolio_close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
				<h2 class="collaterals-mobile__title" id="<?php echo esc_attr( $devportfolio_mobile_id ); ?>" tabindex="-1"></h2>
			</header>
			<div class="collaterals-mobile__body">
				<div class="collaterals-mobile__stage"></div>
			</div>
			<footer class="collaterals-mobile__footer"></footer>
		</div>
	</dialog>
	<script type="application/json" class="collaterals-modal__data"><?php echo $devportfolio_payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & hex-escaped. ?></script>
</div>
