<?php
/**
 * Server render of the Home hero (DPH-001, DPH-003, DPH-004).
 *
 * Name from the site title, portrait from Settings > Contact, roles from the Job Role post type, links from
 * Settings > Contact. A part without data is left out; the call to action always shows.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_name    = devportfolio_hero_name_parts();
$devportfolio_roles   = devportfolio_hero_roles();
$devportfolio_pid     = devportfolio_hero_portrait_id();
$devportfolio_contact = function_exists( 'cptdp_get_contact' ) ? cptdp_get_contact() : array();
$devportfolio_links   = array();

if ( ! empty( $devportfolio_contact['linkedin_url'] ) ) {
	$devportfolio_links[] = array(
		'prefix' => 'in',
		'label'  => __( 'LinkedIn', 'devportfolio' ),
		'href'   => $devportfolio_contact['linkedin_url'],
		'blank'  => true,
	);
}

if ( ! empty( $devportfolio_contact['whatsapp_url'] ) ) {
	$devportfolio_links[] = array(
		'prefix' => '',
		'label'  => __( 'WhatsApp', 'devportfolio' ),
		'href'   => $devportfolio_contact['whatsapp_url'],
		'blank'  => true,
	);
}

if ( ! empty( $devportfolio_contact['email'] ) ) {
	$devportfolio_links[] = array(
		'prefix' => '',
		'label'  => __( 'Email me', 'devportfolio' ),
		'href'   => 'mailto:' . $devportfolio_contact['email'],
		'blank'  => false,
	);
}

$devportfolio_alt = '';
if ( $devportfolio_pid ) {
	$devportfolio_alt = trim( (string) get_post_meta( $devportfolio_pid, '_wp_attachment_image_alt', true ) );
	if ( '' === $devportfolio_alt ) {
		/* translators: %s: site title. */
		$devportfolio_alt = sprintf( __( 'Portrait of %s', 'devportfolio' ), devportfolio_hero_title() );
	}
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'hero' ) );
?>
<section <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<div class="hero__stage">
		<?php if ( '' !== $devportfolio_name['first'] ) : ?>
			<h1 class="hero__name"><span class="hero__name-first"><?php echo esc_html( $devportfolio_name['first'] ); ?></span><?php echo '' !== $devportfolio_name['rest'] ? ' <span class="hero__name-rest">' . esc_html( $devportfolio_name['rest'] ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here. ?></h1>
		<?php endif; ?>

		<?php if ( $devportfolio_pid ) : ?>
			<div class="hero__portrait">
				<?php
				echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes.
					$devportfolio_pid,
					'large',
					false,
					array(
						'class'         => 'hero__portrait-img',
						'alt'           => $devportfolio_alt,
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
						'sizes'         => devportfolio_hero_portrait_sizes(),
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>

	<div class="hero__intro">
		<?php if ( '' !== $devportfolio_roles['main'] ) : ?>
			<p class="hero__role"><?php echo esc_html( $devportfolio_roles['main'] ); ?></p>
			<?php if ( ! empty( $devportfolio_roles['others'] ) ) : ?>
				<p class="hero__roles"><?php echo esc_html( implode( ' · ', $devportfolio_roles['others'] ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<a class="hero__cta" href="#contact"><?php echo esc_html__( 'Let’s work together', 'devportfolio' ); ?> <span aria-hidden="true">↗</span></a>
	</div>

	<?php if ( ! empty( $devportfolio_links ) ) : ?>
		<ul class="hero__links">
			<?php foreach ( $devportfolio_links as $devportfolio_link ) : ?>
				<li>
					<a class="hero__pill" href="<?php echo esc_url( $devportfolio_link['href'] ); ?>"<?php echo $devportfolio_link['blank'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo '' !== $devportfolio_link['prefix'] ? '<span aria-hidden="true">' . esc_html( $devportfolio_link['prefix'] ) . '</span> ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here. ?><?php echo esc_html( $devportfolio_link['label'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="hero__scroll" aria-hidden="true"><?php echo esc_html__( 'Scroll down', 'devportfolio' ); ?> <span class="hero__scroll-arrow">↓</span></p>
</section>
