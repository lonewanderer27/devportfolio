<?php
/**
 * Documents settings (Settings > Documents).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed values of the "Generate page images" setting.
 *
 * @return string[]
 */
function cptdp_documents_pages_modes(): array {
	return array( 'auto', 'server', 'browser' );
}

/**
 * Default Documents values.
 *
 * @return array<string,bool|string>
 */
function cptdp_documents_defaults(): array {
	$defaults = array(
		'show_book_title' => false,
		'pages_mode'      => 'auto',
		'trim_bottom'     => true,
	);

	foreach ( cptdp_documents_native_formats() as $format ) {
		$defaults[ 'native_' . $format ] = false;
	}

	return $defaults;
}

/**
 * The native viewer formats (Settings > Documents > Native viewer), in the order they are listed.
 * DOCX will join them in a later ticket.
 *
 * @return string[]
 */
function cptdp_documents_native_formats(): array {
	return array( 'pdf', 'pptx', 'xlsx' );
}

/**
 * The settings that are checkboxes: the two page options and one `native_<format>` per native viewer format.
 *
 * @return string[]
 */
function cptdp_documents_checkbox_keys(): array {
	return array_merge( array( 'show_book_title', 'trim_bottom' ), array_map( static fn( string $format ): string => 'native_' . $format, cptdp_documents_native_formats() ) );
}

/**
 * Get the saved Documents settings.
 *
 * `selectable_text` (the setting before the native viewer section existed) is still understood: it is
 * the PDF checkbox, `native_pdf`, so an existing site keeps its choice.
 *
 * @param string|null $key Optional single key: show_book_title, pages_mode, trim_bottom, native_pdf, native_pptx or native_xlsx.
 * @return array<string,bool|string>|bool|string
 */
function cptdp_get_documents_settings( ?string $key = null ) {
	$saved = get_option( 'cptdp_documents', array() );
	$saved = is_array( $saved ) ? $saved : array();

	if ( ! array_key_exists( 'native_pdf', $saved ) && array_key_exists( 'selectable_text', $saved ) ) {
		$saved['native_pdf'] = $saved['selectable_text'];
	}

	if ( 'selectable_text' === $key ) {
		$key = 'native_pdf';
	}

	$value = array_merge( cptdp_documents_defaults(), array_intersect_key( $saved, cptdp_documents_defaults() ) );

	$value['pages_mode'] = in_array( $value['pages_mode'], cptdp_documents_pages_modes(), true ) ? $value['pages_mode'] : 'auto';

	foreach ( cptdp_documents_checkbox_keys() as $checkbox ) {
		$value[ $checkbox ] = rest_sanitize_boolean( $value[ $checkbox ] );
	}

	if ( null === $key ) {
		return $value;
	}

	return $value[ $key ] ?? false;
}

/**
 * Register the setting, section and fields.
 */
function cptdp_documents_register_settings(): void {
	register_setting(
		'cptdp_documents',
		'cptdp_documents',
		array(
			'type'              => 'object',
			'default'           => cptdp_documents_defaults(),
			'sanitize_callback' => 'cptdp_documents_sanitize',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						array_fill_keys( cptdp_documents_checkbox_keys(), array( 'type' => 'boolean' ) ),
						array(
							'pages_mode'      => array(
								'type' => 'string',
								'enum' => cptdp_documents_pages_modes(),
							),
							// Deprecated name of native_pdf, still accepted.
							'selectable_text' => array( 'type' => 'boolean' ),
						)
					),
					'additionalProperties' => false,
				),
			),
		)
	);

}
add_action( 'admin_init', 'cptdp_documents_register_settings' );
add_action( 'rest_api_init', 'cptdp_documents_register_settings' );

/**
 * Register the section and fields of the Documents settings page.
 *
 * wp-admin only: the settings API functions are not loaded on REST requests.
 */
function cptdp_documents_register_sections(): void {
	add_settings_section(
		'cptdp_documents_section',
		esc_html__( 'Documents', 'custom-post-types-dp' ),
		'cptdp_documents_render_section',
		'cptdp-documents'
	);

	add_settings_field(
		'cptdp_documents_show_book_title',
		esc_html__( 'Book title', 'custom-post-types-dp' ),
		'cptdp_documents_render_show_book_title',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_show_book_title' )
	);

	add_settings_field(
		'cptdp_documents_pages_mode',
		esc_html__( 'Generate page images', 'custom-post-types-dp' ),
		'cptdp_documents_render_pages_mode',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_pages_mode' )
	);

	add_settings_field(
		'cptdp_documents_trim_bottom',
		esc_html__( 'Page margins', 'custom-post-types-dp' ),
		'cptdp_documents_render_trim_bottom',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_trim_bottom' )
	);

	add_settings_section(
		'cptdp_documents_conversion_section',
		esc_html__( 'Document conversion', 'custom-post-types-dp' ),
		'cptdp_documents_render_conversion_section',
		'cptdp-documents'
	);

	foreach ( cptdp_documents_conversion_formats() as $family => $format ) {
		add_settings_field(
			'cptdp_documents_conversion_' . $family,
			esc_html( $format['label'] ),
			'cptdp_documents_render_conversion_status',
			'cptdp-documents',
			'cptdp_documents_conversion_section',
			array( 'family' => $family )
		);
	}

	add_settings_section(
		'cptdp_documents_native_section',
		esc_html__( 'Native viewer', 'custom-post-types-dp' ),
		'cptdp_documents_render_native_section',
		'cptdp-documents'
	);

	add_settings_field(
		'cptdp_documents_native',
		esc_html__( 'Let the native viewer draw', 'custom-post-types-dp' ),
		'cptdp_documents_render_native_formats',
		'cptdp-documents',
		'cptdp_documents_native_section'
	);
}
add_action( 'admin_init', 'cptdp_documents_register_sections' );

/**
 * Add Settings > Documents.
 */
function cptdp_documents_add_menu(): void {
	add_options_page(
		esc_html__( 'Documents', 'custom-post-types-dp' ),
		esc_html__( 'Documents', 'custom-post-types-dp' ),
		'manage_options',
		'cptdp-documents',
		'cptdp_documents_render_page'
	);
}
add_action( 'admin_menu', 'cptdp_documents_add_menu' );

/**
 * Render the settings page.
 */
function cptdp_documents_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cptdp_documents' );
			do_settings_sections( 'cptdp-documents' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Render the section intro.
 */
function cptdp_documents_render_section(): void {
	echo '<p>' . esc_html__( 'Options for the Documents page.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the "hide book title" checkbox.
 */
function cptdp_documents_render_show_book_title(): void {
	printf(
		'<label><input type="checkbox" id="cptdp_documents_show_book_title" name="cptdp_documents[show_book_title]" value="1" %s /> %s</label><p class="description">%s</p>',
		checked( cptdp_get_documents_settings( 'show_book_title' ), true, false ),
		esc_html__( 'Show the title under each book', 'custom-post-types-dp' ),
		esc_html__( 'When off, the title is only on the cover (screen readers still read it). Off by default.', 'custom-post-types-dp' )
	);
}

/**
 * Render the "Generate page images" select.
 */
function cptdp_documents_render_pages_mode(): void {
	$current = (string) cptdp_get_documents_settings( 'pages_mode' );
	$labels  = array(
		'auto'    => esc_html__( 'Automatic (server when it can, otherwise your browser)', 'custom-post-types-dp' ),
		'server'  => esc_html__( 'Server only', 'custom-post-types-dp' ),
		'browser' => esc_html__( 'Browser only (generated from the document edit screen)', 'custom-post-types-dp' ),
	);

	echo '<select id="cptdp_documents_pages_mode" name="cptdp_documents[pages_mode]">';
	foreach ( cptdp_documents_pages_modes() as $mode ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $mode ),
			selected( $current, $mode, false ),
			esc_html( $labels[ $mode ] )
		);
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'Page images are made when a document with a PDF is saved, so visitors see them straight away. Automatic uses the server (Imagick or Ghostscript) when available and falls back to the browser of whoever is editing the document.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the "Trim empty space" checkbox.
 */
function cptdp_documents_render_trim_bottom(): void {
	printf(
		'<label><input type="checkbox" id="cptdp_documents_trim_bottom" name="cptdp_documents[trim_bottom]" value="1" %s /> %s</label><p class="description">%s</p>',
		checked( cptdp_get_documents_settings( 'trim_bottom' ), true, false ),
		esc_html__( 'Trim empty space at the bottom of pages', 'custom-post-types-dp' ),
		esc_html__( 'When a document\'s pages all end well above their bottom edge, the viewer hides that empty strip (the page images are not changed, and printed footers are kept). The strip is measured when the images are made; switching this off or on takes effect straight away.', 'custom-post-types-dp' )
	);
}

/**
 * The document families the server converts to page images with LibreOffice (Settings > Documents > Document
 * conversion), in the order they are listed. A new format is a new entry: its label, the file kind used to ask
 * whether the server can convert it, the converter's name, and the one line that is specific to it.
 *
 * @return array<string,array{label:string,kind:string,product:string,detail:string}>
 */
function cptdp_documents_conversion_formats(): array {
	return array(
		'presentations' => array(
			'label'   => __( 'PowerPoint (.pptx, .ppt) and OpenDocument (.odp)', 'custom-post-types-dp' ),
			'kind'    => CPTDP_Page_Store::KIND_PPTX,
			'product' => 'LibreOffice',
			'detail'  => __( 'One page per slide. Missing fonts are replaced, animations and transitions are not exported, and speaker notes and hidden slides are left out.', 'custom-post-types-dp' ),
		),
		'spreadsheets'  => array(
			'label'   => __( 'Excel (.xlsx, .xls) and OpenDocument (.ods)', 'custom-post-types-dp' ),
			'kind'    => CPTDP_Page_Store::KIND_XLSX,
			'product' => 'LibreOffice Calc',
			'detail'  => __( 'One page per sheet. Very wide sheets are shrunk, the saved values are shown (formulas are not recalculated), hidden sheets are left out, and workbooks with macros or external links are refused.', 'custom-post-types-dp' ),
		),
	);
}

/**
 * Render the Document conversion section intro: what is true of every format, said once.
 */
function cptdp_documents_render_conversion_section(): void {
	echo '<p>' . esc_html__( 'PDF files are drawn into page images in this server or in the editor\'s browser. Other files are converted on the server when a document is saved (visitors never trigger it), so their pages are made from a copy of the original. The result may differ from the original, so a PDF export is still the best option. When a format cannot be converted here, editors are asked to upload a PDF instead.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the read-only conversion status of one document family, with only what is specific to it.
 *
 * @param array{family?:string} $args Field arguments.
 */
function cptdp_documents_render_conversion_status( array $args = array() ): void {
	$formats = cptdp_documents_conversion_formats();
	$family  = (string) ( $args['family'] ?? '' );

	if ( ! isset( $formats[ $family ] ) ) {
		return;
	}

	$format = $formats[ $family ];

	if ( CPTDP_Pptx_Converter::is_available( $format['kind'] ) ) {
		$version = CPTDP_Pptx_Converter::version();

		echo '<p><strong>' . esc_html__( 'Available', 'custom-post-types-dp' ) . '</strong>' . ( '' !== $version ? ' (' . esc_html( $format['product'] . ' ' . $version ) . ')' : '' ) . '</p>';
	} else {
		echo '<p><strong>' . esc_html__( 'Unavailable', 'custom-post-types-dp' ) . '</strong></p>';
		echo '<p class="description">' . esc_html( CPTDP_Pptx_Converter::unavailable_reason( $format['kind'] ) ) . '</p>';
	}

	echo '<p class="description">' . esc_html( $format['detail'] ) . '</p>';
}

/**
 * Render the native viewer section intro.
 */
function cptdp_documents_render_native_section(): void {
	echo '<p>' . esc_html__( 'By default a document is shown from page images that the server makes when it is saved: quick to open and light on every device. Tick a format to let our own viewer take over instead. It draws the original file in the visitor\'s browser, so text can be selected and copied, and it follows the file more closely.', 'custom-post-types-dp' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'This comes at the expense of performance:', 'custom-post-types-dp' ) . '</strong> '
		. esc_html__( 'documents take longer to open and to turn, large ones and phones or older computers feel it most, and the viewer code is downloaded first. When the native viewer cannot draw a document, the stored page images are shown instead. Nothing is sent to anyone: the file is read from this site. Every format is off by default.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the native viewer checkboxes, one per format. The fieldset carries the id the editor warning links to.
 */
function cptdp_documents_render_native_formats(): void {
	$formats = array(
		'pdf'  => array(
			'label' => __( 'PDF', 'custom-post-types-dp' ),
			'help'  => __( 'Draws the pages with the browser\'s own PDF engine (pdf.js) and real, selectable text, instead of the stored images. Dragging a page corner to turn it is switched off so that dragging selects text. Documents that cannot be drawn this way use the images.', 'custom-post-types-dp' ),
		),
		'pptx' => array(
			'label' => __( 'PowerPoint (.pptx)', 'custom-post-types-dp' ),
			'help'  => __( 'Draws the slides from the original file with a bundled viewer (about 2 MB the first time), with selectable text. It does not need LibreOffice on the server, so it also works on hosts that cannot convert presentations. No animations or equations; some charts and effects differ. Older .ppt and OpenDocument .odp files are not supported.', 'custom-post-types-dp' ),
		),
		'xlsx' => array(
			'label' => __( 'Excel (.xlsx)', 'custom-post-types-dp' ),
			'help'  => __( 'Draws the workbook from the original file with a bundled viewer (a few MB the first time): visitors can scroll, zoom, switch sheets and copy cells. It does not need LibreOffice on the server. No macros; charts and some formatting differ. Note that the visitor downloads the whole file, including hidden sheets and comments. Older .xls and OpenDocument .ods files are not supported (they use the converted pages).', 'custom-post-types-dp' ),
		),
	);

	echo '<fieldset id="cptdp-native-viewer"><legend class="screen-reader-text"><span>' . esc_html__( 'Let the native viewer draw', 'custom-post-types-dp' ) . '</span></legend>';

	foreach ( cptdp_documents_native_formats() as $format ) {
		printf(
			'<p><label><input type="checkbox" id="cptdp_documents_native_%1$s" name="cptdp_documents[native_%1$s]" value="1" %2$s /> <strong>%3$s</strong></label><br><span class="description">%4$s</span></p>',
			esc_attr( $format ),
			checked( cptdp_get_documents_settings( 'native_' . $format ), true, false ),
			esc_html( $formats[ $format ]['label'] ),
			esc_html( $formats[ $format ]['help'] )
		);
	}

	echo '</fieldset><style>#cptdp-native-viewer:target{background:#fff8c5;outline:6px solid #fff8c5}</style>';
}

/**
 * Whether the value being saved comes from the Settings > Documents form.
 *
 * `options.php` always posts `option_page`. A browser form cannot send an unticked
 * checkbox, so there a missing checkbox means "off". Anything else (a partial
 * `PUT /wp/v2/settings`, `update_option()` from code) only names the keys it changes.
 */
function cptdp_documents_is_form_post(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check of which screen posted; options.php has already verified the nonce.
	return isset( $_POST['option_page'] ) && 'cptdp_documents' === sanitize_key( wp_unslash( $_POST['option_page'] ) );
}

/**
 * Sanitise submitted values.
 *
 * From the settings form every field is read as submitted (unticked = off). Otherwise
 * the keys that are not part of the input keep their current saved value, so a
 * partial update never resets the other settings.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,bool|string>
 */
function cptdp_documents_sanitize( $input ): array {
	$form    = cptdp_documents_is_form_post();
	$current = $form ? cptdp_documents_defaults() : cptdp_get_documents_settings();
	$clean   = $current;

	if ( ! is_array( $input ) || array() === $input ) {
		return $form ? cptdp_documents_defaults() : $current;
	}

	// The old name of the PDF checkbox: a partial update (REST, code) that still sends it means native_pdf.
	if ( ! $form && ! array_key_exists( 'native_pdf', $input ) && array_key_exists( 'selectable_text', $input ) ) {
		$input['native_pdf'] = $input['selectable_text'];
	}

	foreach ( cptdp_documents_checkbox_keys() as $key ) {
		if ( $form ) {
			// Unticked checkboxes are not submitted, so a submitted form without them means "off".
			$clean[ $key ] = isset( $input[ $key ] ) && rest_sanitize_boolean( $input[ $key ] );
		} elseif ( array_key_exists( $key, $input ) ) {
			$clean[ $key ] = rest_sanitize_boolean( $input[ $key ] );
		}
	}

	if ( array_key_exists( 'pages_mode', $input ) || $form ) {
		$mode                = isset( $input['pages_mode'] ) ? sanitize_key( (string) $input['pages_mode'] ) : 'auto';
		$clean['pages_mode'] = in_array( $mode, cptdp_documents_pages_modes(), true ) ? $mode : 'auto';
	}

	return $clean;
}
