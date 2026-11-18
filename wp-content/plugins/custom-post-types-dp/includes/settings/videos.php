<?php
/**
 * Videos settings (Settings > Videos): which extra controls and behaviours the mobile video viewer has.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * The switches, in page order. The single source of truth: defaults, the sanitiser, the REST schema and the
 * form are all derived from it, so a new switch is one entry here (and one control module in the theme).
 *
 * @return array<string,array{section:string,default:bool,label:string,help:string}>
 */
function cptdp_videos_mobile_options(): array {
	return array(
		'mute'            => array(
			'section' => 'controls',
			'default' => true,
			'label'   => __( 'Mute button', 'custom-post-types-dp' ),
			'help'    => __( 'A round sound on/off button at the top right of the mobile video. Not shown when the volume slider is on, which does the same job. Without either, a tap that resumes a paused video turns the sound on.', 'custom-post-types-dp' ),
		),
		'progress'        => array(
			'section' => 'controls',
			'default' => true,
			'label'   => __( 'Progress line', 'custom-post-types-dp' ),
			'help'    => __( 'A thin, read-only line along the bottom edge showing how far the video has played.', 'custom-post-types-dp' ),
		),
		'scrub'           => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Drag to seek', 'custom-post-types-dp' ),
			'help'    => __( 'Lets visitors drag the progress line to jump around. Turns the progress line on.', 'custom-post-types-dp' ),
		),
		'time'            => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Time', 'custom-post-types-dp' ),
			'help'    => __( 'Shows 0:08 / 0:28 above the progress line.', 'custom-post-types-dp' ),
		),
		'volume'          => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Volume slider', 'custom-post-types-dp' ),
			'help'    => __( 'Note: enabling this does nothing on iPhone and iPad (iOS), where only the device buttons set the volume. It works on Android and other browsers.', 'custom-post-types-dp' ),
		),
		'speed'           => array(
			'section' => 'controls',
			'default' => false,
			'label'   => __( 'Playback speed', 'custom-post-types-dp' ),
			'help'    => __( 'A menu from 0.5× to 2×.', 'custom-post-types-dp' ),
		),
		'fit'             => array(
			'section' => 'controls',
			'default' => true,
			'label'   => __( 'Fit, zoom and stretch button', 'custom-post-types-dp' ),
			'help'    => __( 'A round button that cycles the picture between fit (the whole video, with bars where it does not fill the screen), zoom (fills the screen, cropping the edges) and stretch (fills it, distorting the picture). The choice is kept while the page is open.', 'custom-post-types-dp' ),
		),
		'loop'            => array(
			'section' => 'behaviour',
			'default' => true,
			'label'   => __( 'Loop the video', 'custom-post-types-dp' ),
			'help'    => __( 'Off: the video plays twice, then stops with a Replay button (the desktop behaviour).', 'custom-post-types-dp' ),
		),
		'double_tap_skip' => array(
			'section' => 'behaviour',
			'default' => true,
			'label'   => __( 'Double-tap to skip 5 seconds', 'custom-post-types-dp' ),
			'help'    => __( 'Right half forward, left half back. Off: a tap pauses at once.', 'custom-post-types-dp' ),
		),
	);
}

/**
 * The switch keys.
 *
 * @return string[]
 */
function cptdp_videos_keys(): array {
	return array_keys( cptdp_videos_mobile_options() );
}

/**
 * Default values.
 *
 * @return array<string,bool>
 */
function cptdp_videos_defaults(): array {
	return array_map( static fn( array $option ): bool => $option['default'], cptdp_videos_mobile_options() );
}

/**
 * Get the saved settings (booleans). Dragging needs the progress line, so `scrub` implies `progress`.
 *
 * @param string|null $key Optional single key.
 * @return array<string,bool>|bool
 */
function cptdp_get_videos_settings( ?string $key = null ) {
	$saved = get_option( 'cptdp_videos', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$value = array_map( 'rest_sanitize_boolean', array_merge( cptdp_videos_defaults(), array_intersect_key( $saved, cptdp_videos_defaults() ) ) );

	$value['progress'] = $value['progress'] || $value['scrub'];

	return null === $key ? $value : ( $value[ $key ] ?? false );
}

/**
 * Register the setting.
 */
function cptdp_videos_register_settings(): void {
	register_setting(
		'cptdp_videos',
		'cptdp_videos',
		array(
			'type'              => 'object',
			'default'           => cptdp_videos_defaults(),
			'sanitize_callback' => 'cptdp_videos_sanitize',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array_fill_keys( cptdp_videos_keys(), array( 'type' => 'boolean' ) ),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'admin_init', 'cptdp_videos_register_settings' );
add_action( 'rest_api_init', 'cptdp_videos_register_settings' );

/**
 * Whether the value being saved comes from the Settings > Videos form. A browser form cannot send an
 * unticked checkbox, so there a missing key means "off"; a partial REST update only names what it changes.
 */
function cptdp_videos_is_form_post(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check of which screen posted; options.php has already verified the nonce.
	return isset( $_POST['option_page'] ) && 'cptdp_videos' === sanitize_key( wp_unslash( $_POST['option_page'] ) );
}

/**
 * Sanitise submitted values. Unlike Settings > Documents, a form with nothing ticked is valid here (every
 * switch off), so an empty submission is not replaced by the defaults.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,bool>
 */
function cptdp_videos_sanitize( $input ): array {
	$form  = cptdp_videos_is_form_post();
	$input = is_array( $input ) ? $input : array();
	$clean = $form ? array_fill_keys( cptdp_videos_keys(), false ) : cptdp_get_videos_settings();

	foreach ( cptdp_videos_keys() as $key ) {
		if ( $form || array_key_exists( $key, $input ) ) {
			$clean[ $key ] = isset( $input[ $key ] ) && rest_sanitize_boolean( $input[ $key ] );
		}
	}

	$clean['progress'] = $clean['progress'] || $clean['scrub'];

	return $clean;
}

/**
 * Register the sections and fields (wp-admin only: the settings API is not loaded on REST requests).
 */
function cptdp_videos_register_sections(): void {
	add_settings_section(
		'cptdp_videos_controls',
		esc_html__( 'Mobile video controls', 'custom-post-types-dp' ),
		static function (): void {
			echo '<p>' . esc_html__( 'The mobile viewer is a full-screen player like YouTube Shorts and Instagram Reels. Tap pauses; swiping or the up and down buttons change video. Choose which extra controls it shows. Applies to screens 700px wide or less and phones held sideways; the desktop viewer is unchanged.', 'custom-post-types-dp' ) . '</p>';
		},
		'cptdp-videos'
	);
	add_settings_section( 'cptdp_videos_behaviour', esc_html__( 'Behaviour', 'custom-post-types-dp' ), '__return_empty_string', 'cptdp-videos' );

	foreach ( cptdp_videos_mobile_options() as $key => $option ) {
		add_settings_field(
			'cptdp_videos_' . $key,
			esc_html( $option['label'] ),
			'cptdp_videos_render_field',
			'cptdp-videos',
			'cptdp_videos_' . $option['section'],
			array(
				'key'       => $key,
				'label_for' => 'cptdp_videos_' . $key,
			)
		);
	}
}
add_action( 'admin_init', 'cptdp_videos_register_sections' );

/**
 * Render one checkbox with its help text.
 *
 * @param array<string,string> $args Field args.
 */
function cptdp_videos_render_field( array $args ): void {
	$key    = $args['key'];
	$option = cptdp_videos_mobile_options()[ $key ];

	printf(
		'<label><input type="checkbox" id="%1$s" name="cptdp_videos[%2$s]" value="1" %3$s /> %4$s</label><p class="description">%5$s</p>',
		esc_attr( $args['label_for'] ),
		esc_attr( $key ),
		checked( cptdp_get_videos_settings( $key ), true, false ),
		esc_html__( 'On', 'custom-post-types-dp' ),
		esc_html( $option['help'] )
	);
}

/**
 * Add Settings > Videos.
 */
function cptdp_videos_add_menu(): void {
	add_options_page(
		esc_html__( 'Videos', 'custom-post-types-dp' ),
		esc_html__( 'Videos', 'custom-post-types-dp' ),
		'manage_options',
		'cptdp-videos',
		'cptdp_videos_render_page'
	);
}
add_action( 'admin_menu', 'cptdp_videos_add_menu' );

/**
 * Render the settings page.
 */
function cptdp_videos_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cptdp_videos' );
			do_settings_sections( 'cptdp-videos' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
