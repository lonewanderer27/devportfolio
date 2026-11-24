<?php
/**
 * Server render of the Home Contact scheduler (DPH-025 to DPH-028).
 *
 * Markup only: the four step forms (on phones they are one modal, with the header and the footer of every step) and a JSON payload. The crumbs, the calendar, the times and the
 * summary are built by view.js. Without JavaScript the card and the button stay hidden and the noscript line shows
 * the Contact email. Nothing is rendered when the plugin is not active.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_config = devportfolio_contact_config();
if ( empty( $devportfolio_config ) ) {
	return;
}

$s     = $devportfolio_config['strings'];
$email = function_exists( 'cptdp_get_contact' ) ? (string) cptdp_get_contact( 'email' ) : '';

$devportfolio_json = wp_json_encode(
	$devportfolio_config,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'contact home-section',
		'id'    => 'contact',
	)
);
?>
<section <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> aria-labelledby="contact-heading" data-contact data-step="1">
	<h2 id="contact-heading" class="contact__heading home-reveal"><?php echo esc_html( $s['heading'] ); ?></h2>
	<p class="contact__lead home-reveal"><?php echo esc_html( $s['lead'] ); ?></p>

	<button type="button" class="contact__open home-reveal" data-contact-open><?php echo esc_html( $s['open'] ); ?></button>

	<div class="contact__card home-reveal" data-contact-card>
		<div class="contact__modal-head">
			<button type="button" class="contact__close" data-contact-close aria-label="<?php echo esc_attr( $s['close'] ); ?>">
				<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</button>
			<p class="contact__modal-title"><?php echo esc_html( $s['modalTitle'] ); ?></p>
		</div>
		<ol class="contact__crumbs" data-contact-crumbs aria-label="<?php echo esc_attr( $s['progress'] ); ?>"></ol>

		<p class="contact__slot">
			<span data-contact-slot-text></span>
			<button type="button" class="contact__link" data-contact-change><?php echo esc_html( $s['change'] ); ?></button>
		</p>

		<form class="contact__panel" data-contact-panel="1" novalidate>
			<h3 class="contact__title contact__sr" tabindex="-1" data-contact-title><?php echo esc_html( $s['title1'] ); ?></h3>
			<div class="contact__body" data-contact-picker></div>
			<footer class="contact__footer">
				<button type="submit" class="contact__btn contact__btn--fill" data-contact-next disabled><?php echo esc_html( $s['next'] ); ?></button>
			</footer>
		</form>

		<form class="contact__panel" data-contact-panel="2" novalidate hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title2'] ); ?></h3>
			<div class="contact__body">
				<div class="contact__field">
					<label for="contact-name"><?php echo esc_html( $s['nameLabel'] ); ?></label>
					<input id="contact-name" type="text" name="name" autocomplete="name" maxlength="100" aria-required="true" aria-describedby="contact-name-error" data-contact-field="name">
					<p class="contact__error" id="contact-name-error" role="alert" data-contact-error="name"></p>
				</div>
				<div class="contact__field">
					<label for="contact-email"><?php echo esc_html( $s['emailLabel'] ); ?></label>
					<input id="contact-email" type="email" name="email" autocomplete="email" maxlength="254" aria-required="true" aria-describedby="contact-email-error" data-contact-field="email">
					<p class="contact__error" id="contact-email-error" role="alert" data-contact-error="email"></p>
				</div>
				<div class="contact__hp" aria-hidden="true">
					<label for="contact-website"><?php echo esc_html( $s['honeypotLabel'] ); ?></label>
					<input id="contact-website" type="text" name="website" tabindex="-1" autocomplete="off" data-contact-honeypot>
				</div>
			</div>
			<footer class="contact__footer">
				<button type="button" class="contact__btn" data-contact-back><?php echo esc_html( $s['back'] ); ?></button>
				<button type="submit" class="contact__btn contact__btn--fill"><?php echo esc_html( $s['next'] ); ?></button>
			</footer>
		</form>

		<form class="contact__panel" data-contact-panel="3" novalidate hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title3'] ); ?></h3>
			<div class="contact__body">
				<fieldset class="contact__chips" aria-describedby="contact-services-error" data-contact-field="services">
					<legend class="contact__sr"><?php echo esc_html( $s['title3'] ); ?></legend>
					<?php foreach ( $devportfolio_config['services'] as $devportfolio_service ) : ?>
						<label class="contact__chip">
							<input type="checkbox" name="services" value="<?php echo esc_attr( $devportfolio_service['id'] ); ?>" data-contact-service>
							<span><?php echo esc_html( $devportfolio_service['title'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="contact__error" id="contact-services-error" role="alert" data-contact-error="services"></p>
				<div class="contact__field">
					<label for="contact-message"><?php echo esc_html( $s['messageLabel'] ); ?> <span class="contact__optional">(<?php echo esc_html( $s['messageHint'] ); ?>)</span></label>
					<textarea id="contact-message" name="message" rows="5" maxlength="2000" data-contact-field="message"></textarea>
				</div>
			</div>
			<footer class="contact__footer">
				<button type="button" class="contact__btn" data-contact-back><?php echo esc_html( $s['back'] ); ?></button>
				<button type="submit" class="contact__btn contact__btn--fill"><?php echo esc_html( $s['next'] ); ?></button>
			</footer>
		</form>

		<form class="contact__panel" data-contact-panel="4" novalidate hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title4'] ); ?></h3>
			<div class="contact__body">
				<dl class="contact__summary" data-contact-summary></dl>
				<p class="contact__error" role="alert" data-contact-form-error></p>
			</div>
			<footer class="contact__footer">
				<button type="button" class="contact__btn" data-contact-back><?php echo esc_html( $s['back'] ); ?></button>
				<button type="submit" class="contact__btn contact__btn--fill" data-contact-send><?php echo esc_html( $s['confirm'] ); ?></button>
			</footer>
		</form>

		<div class="contact__thanks" data-contact-thanks role="status" hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-thanks-title></h3>
			<p data-contact-thanks-time></p>
			<p><?php echo esc_html( $s['thanksNote'] ); ?></p>
		</div>
	</div>

	<noscript>
		<p class="contact__noscript">
			<?php echo esc_html( $s['noscript'] ); ?>
			<?php if ( is_email( $email ) ) : ?>
				<?php echo esc_html( $s['noscriptEmail'] ); ?>
				<a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
			<?php endif; ?>
		</p>
	</noscript>


	<script type="application/json" class="contact__data"><?php echo $devportfolio_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with the HEX flags, safe inside a script element. ?></script>
</section>
