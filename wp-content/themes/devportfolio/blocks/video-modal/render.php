<?php
/**
 * Render callback for the devportfolio/video-modal block (DPV-009 to DPV-016).
 *
 * One desktop dialog (video pane left, details pane right) and a JSON payload with the published videos in the
 * grid's order (`order`) and their data (`items`, see devportfolio_video_modal_payload()). view.js fills the
 * dialog when a `[data-video-id]` is clicked. Every empty region is hidden in the markup.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'video',
	array(
		'orderby' => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

$devportfolio_order = array();
$devportfolio_items = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_item = devportfolio_video_modal_payload( $devportfolio_id );
	if ( null === $devportfolio_item ) {
		continue;
	}
	$devportfolio_order[]                            = (int) $devportfolio_id;
	$devportfolio_items[ (string) $devportfolio_id ] = $devportfolio_item;
}

$devportfolio_payload = wp_json_encode(
	array(
		'order' => $devportfolio_order,
		'items' => (object) $devportfolio_items,
	),
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

$devportfolio_title_id   = wp_unique_id( 'videos-modal-title-' );
$devportfolio_results_id = wp_unique_id( 'videos-modal-results-' );
$devportfolio_made_id    = wp_unique_id( 'videos-modal-made-' );
$devportfolio_next_id    = wp_unique_id( 'videos-modal-next-' );
$devportfolio_close_icon = '<svg class="videos-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class'        => 'videos-modal-root',
		'data-strings' => wp_json_encode( devportfolio_video_modal_strings() ),
	)
);
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<dialog class="videos-modal" data-pane="wide" aria-labelledby="<?php echo esc_attr( $devportfolio_title_id ); ?>">
		<div class="videos-modal__panel">
			<div class="videos-modal__video" data-state="paused">
				<div class="videos-modal__stage"></div>
				<p class="videos-modal__state" role="status" hidden data-loading="<?php echo esc_attr_x( 'Loading video…', 'video modal', 'devportfolio' ); ?>" data-unavailable="<?php echo esc_attr_x( 'This video can’t be previewed here.', 'video modal', 'devportfolio' ); ?>" data-hint="<?php echo esc_attr_x( 'Use the original post.', 'video modal', 'devportfolio' ); ?>"><span class="videos-modal__state-text"></span><span class="videos-modal__state-hint"></span></p>
				<button class="videos-modal__bigplay" type="button" aria-label="<?php echo esc_attr_x( 'Play', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg></button>
				<button class="videos-modal__replay" type="button"><?php echo esc_html_x( 'Replay', 'video modal', 'devportfolio' ); ?></button>
				<div class="videos-modal__bar">
					<button class="videos-modal__playpause" type="button" aria-label="<?php echo esc_attr_x( 'Play', 'video modal', 'devportfolio' ); ?>"><svg class="videos-modal__icon videos-modal__icon--play" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg><svg class="videos-modal__icon videos-modal__icon--pause" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg></button>
					<input class="videos-modal__scrub" type="range" min="0" max="0" step="any" value="0" aria-label="<?php echo esc_attr_x( 'Seek', 'video modal', 'devportfolio' ); ?>">
					<span class="videos-modal__time" aria-hidden="true">0:00 / 0:00</span>
					<button class="videos-modal__mute" type="button" aria-label="<?php echo esc_attr_x( 'Mute', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 9v6h4l5 4V5L7 9z"/></svg></button>
					<input class="videos-modal__volume" type="range" min="0" max="1" step="0.05" value="1" aria-label="<?php echo esc_attr_x( 'Volume', 'video modal', 'devportfolio' ); ?>">
					<select class="videos-modal__rate" aria-label="<?php echo esc_attr_x( 'Playback speed', 'video modal', 'devportfolio' ); ?>">
						<option value="0.5">0.5×</option>
						<option value="0.75">0.75×</option>
						<option value="1" selected>1×</option>
						<option value="1.25">1.25×</option>
						<option value="1.5">1.5×</option>
						<option value="2">2×</option>
					</select>
					<button class="videos-modal__fullscreen" type="button" aria-label="<?php echo esc_attr_x( 'Full screen', 'video modal', 'devportfolio' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>
				</div>
			</div>
			<aside class="videos-modal__side">
				<div class="videos-modal__sidebar-bar">
					<button class="videos-modal__close" type="button" aria-label="<?php echo esc_attr_x( 'Close video', 'video modal', 'devportfolio' ); ?>"><?php echo $devportfolio_close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
				</div>
				<div class="videos-modal__scroll">
					<header class="videos-modal__head">
						<p class="videos-modal__pills" hidden>
							<span class="videos-modal__pill videos-modal__pill--cat" hidden></span>
							<span class="videos-modal__pill videos-modal__pill--size" hidden></span>
						</p>
						<h2 class="videos-modal__title" id="<?php echo esc_attr( $devportfolio_title_id ); ?>" tabindex="-1"></h2>
						<p class="videos-modal__desc" hidden></p>
					</header>
					<section class="videos-modal__results" aria-labelledby="<?php echo esc_attr( $devportfolio_results_id ); ?>" hidden>
						<h3 class="videos-modal__caption" id="<?php echo esc_attr( $devportfolio_results_id ); ?>"><?php echo esc_html_x( 'Results', 'video modal', 'devportfolio' ); ?></h3>
						<dl class="videos-modal__stats"></dl>
					</section>
					<section class="videos-modal__tools" aria-labelledby="<?php echo esc_attr( $devportfolio_made_id ); ?>" hidden>
						<h3 class="videos-modal__caption" id="<?php echo esc_attr( $devportfolio_made_id ); ?>"><?php echo esc_html_x( 'Made with', 'video modal', 'devportfolio' ); ?></h3>
						<ul class="videos-modal__toollist"></ul>
					</section>
					<nav class="videos-modal__next" aria-labelledby="<?php echo esc_attr( $devportfolio_next_id ); ?>" hidden>
						<h3 class="videos-modal__caption" id="<?php echo esc_attr( $devportfolio_next_id ); ?>"><?php echo esc_html_x( 'Next up', 'video modal', 'devportfolio' ); ?></h3>
						<ul class="videos-modal__nextlist"></ul>
					</nav>
				</div>
				<div class="videos-modal__actions">
					<a class="videos-modal__original" target="_blank" rel="noopener" role="button" hidden><span><?php echo esc_html_x( 'Open original post', 'video modal button', 'devportfolio' ); ?></span><span aria-hidden="true"> ↗</span></a>
					<button class="videos-modal__copy" type="button"><?php echo esc_html_x( 'Copy link', 'video modal button', 'devportfolio' ); ?></button>
					<input class="videos-modal__copyfield" type="text" readonly tabindex="-1" aria-label="<?php echo esc_attr_x( 'Link to this video', 'video modal', 'devportfolio' ); ?>">
					<span class="videos-modal__live" role="status" aria-live="polite"></span>
				</div>
			</aside>
		</div>
	</dialog>
	<script type="application/json" class="videos-modal__data"><?php echo $devportfolio_payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & hex-escaped. ?></script>
</div>
