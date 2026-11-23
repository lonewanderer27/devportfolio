<?php
/**
 * Server render of the footer content (DPH-029).
 *
 * Copyright text plus the contact links of Settings > Contact. A link without a value is not shown.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_contact = function_exists( 'cptdp_get_contact' ) ? cptdp_get_contact() : array();
$devportfolio_links   = array();

if ( ! empty( $devportfolio_contact['linkedin_url'] ) ) {
	$devportfolio_links[] = array(
		'label'    => __( 'LinkedIn', 'devportfolio' ),
		'href'     => $devportfolio_contact['linkedin_url'],
		'external' => true,
	);
}

if ( ! empty( $devportfolio_contact['whatsapp_url'] ) ) {
	$devportfolio_links[] = array(
		'label'    => __( 'WhatsApp', 'devportfolio' ),
		'href'     => $devportfolio_contact['whatsapp_url'],
		'external' => true,
	);
}

if ( ! empty( $devportfolio_contact['email'] ) ) {
	$devportfolio_links[] = array(
		'label'    => __( 'Email', 'devportfolio' ),
		'value'    => $devportfolio_contact['email'],
		'href'     => 'mailto:' . $devportfolio_contact['email'],
		'external' => false,
	);
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'site-footer' ) );
?>
<div <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<p class="site-footer__copy"><?php echo esc_html( sprintf( '© %1$s %2$s', wp_date( 'Y' ), get_bloginfo( 'name' ) ) ); ?></p>
	<?php if ( ! empty( $devportfolio_links ) ) : ?>
		<nav class="site-footer__nav" aria-label="<?php echo esc_attr__( 'Contact links', 'devportfolio' ); ?>">
			<ul class="site-footer__links">
				<?php foreach ( $devportfolio_links as $devportfolio_link ) : ?>
					<li>
						<a class="site-footer__link" href="<?php echo esc_url( $devportfolio_link['href'] ); ?>"<?php echo $devportfolio_link['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php if ( isset( $devportfolio_link['value'] ) ) : ?><span class="site-footer__full"><?php echo esc_html( $devportfolio_link['value'] ); ?></span><span class="site-footer__short"><?php echo esc_html( $devportfolio_link['label'] ); ?></span><?php else : ?><?php echo esc_html( $devportfolio_link['label'] ); ?><?php endif; ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</div>
