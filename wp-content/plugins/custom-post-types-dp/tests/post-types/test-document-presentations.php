<?php
/**
 * The presentation warning, the native viewer setting and PPTX URL, and the DDEV tooling (DPB-026).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group presentations
 */
class CPTDP_Test_Document_Presentations extends CPTDP_Pages_Test_Case {

	public function set_up(): void {
		parent::set_up();
		add_filter( 'cptdp_pptx_available', '__return_false' ); // Never convert while setting documents up.
	}

	private function preview( bool $on ): void {
		update_option( 'cptdp_documents', array( 'native_pptx' => $on ) );
	}

	// --- The warning ---------------------------------------------------------------------------

	public function test_the_warning_recommends_a_pdf_export_for_every_kind(): void {
		$this->acting_as( 'administrator' );

		$pptx = cptdp_pptx_warning( 'pptx' );
		$this->assertStringContainsString( 'PowerPoint files are converted automatically and the result may be imperfect', $pptx );
		$this->assertStringContainsString( 'export the presentation to PDF in PowerPoint', $pptx );
		$this->assertStringContainsString( 'upload that PDF instead', $pptx );
		$this->assertStringContainsString( 'notice-warning', $pptx, 'It warns, it does not block.' );

		$this->assertStringContainsString( 'PowerPoint files are converted', cptdp_pptx_warning( 'ppt' ) );

		$odp = cptdp_pptx_warning( 'odp' );
		$this->assertStringContainsString( 'may be imperfect', $odp );
		$this->assertStringContainsString( 'in LibreOffice', $odp );
		$this->assertStringContainsString( 'upload that PDF instead', $odp );
	}

	public function test_there_is_no_warning_for_other_files(): void {
		$this->assertSame( '', cptdp_pptx_warning( 'pdf' ) );
		$this->assertSame( '', cptdp_pptx_warning( '' ) );
		$this->assertSame( '', cptdp_pptx_warning( 'xlsx' ), 'The presentation warning is for presentations only.' );
	}

	public function test_an_admin_sees_a_link_to_the_native_viewer_setting_for_a_pptx(): void {
		$this->acting_as( 'administrator' );
		$this->preview( false );

		$html = cptdp_pptx_warning( 'pptx' );

		$this->assertStringContainsString( 'let the native viewer draw PowerPoint files', $html );
		$this->assertStringContainsString( 'options-general.php?page=cptdp-documents#cptdp-native-viewer', $html );
		$this->assertStringContainsString( 'target="_blank"', $html, 'It opens in a new tab so unsaved edits are kept.' );
		$this->assertStringContainsString( 'rel="noopener"', $html );
		$this->assertStringContainsString( 'nothing is sent to anyone', $html );
	}

	public function test_ppt_and_odp_get_no_preview_sentence_because_the_viewer_cannot_read_them(): void {
		$this->acting_as( 'administrator' );

		foreach ( array( 'ppt', 'odp' ) as $kind ) {
			$html = cptdp_pptx_warning( $kind );

			$this->assertStringContainsString( 'may be imperfect', $html, $kind );
			$this->assertStringNotContainsString( 'native viewer', $html, $kind );
			$this->assertStringNotContainsString( 'cptdp-documents', $html, $kind );
		}
	}

	public function test_with_the_viewer_on_the_link_becomes_a_status_line(): void {
		$this->acting_as( 'administrator' );
		$this->preview( true );

		$html = cptdp_pptx_warning( 'pptx' );

		$this->assertStringContainsString( 'The native viewer is on for PowerPoint files', $html );
		$this->assertStringContainsString( 'Change this setting', $html );
		$this->assertStringNotContainsString( 'let the native viewer draw', $html );
	}

	public function test_editors_without_the_settings_capability_see_no_link(): void {
		$this->acting_as( 'editor' );
		$this->preview( false );

		$html = cptdp_pptx_warning( 'pptx' );

		$this->assertStringContainsString( 'may be imperfect', $html );
		$this->assertStringNotContainsString( 'native viewer', $html );
		$this->assertStringNotContainsString( 'cptdp-documents', $html );
	}

	public function test_the_picker_gets_a_warning_for_each_kind(): void {
		$this->acting_as( 'administrator' );

		$warnings = cptdp_pptx_warnings();

		$this->assertSame( array( 'pptx', 'ppt', 'odp' ), array_keys( $warnings ) );
		foreach ( $warnings as $html ) {
			$this->assertStringContainsString( 'imperfect', $html );
		}
	}

	public function test_the_document_file_box_renders_the_warning_only_for_a_presentation(): void {
		$this->acting_as( 'administrator' );
		$pptx = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$pdf  = $this->make_document( $this->make_pdf_attachment( 1 ) );
		$none = $this->make_document();
		$box  = static function ( int $id ): string {
			ob_start();
			cptdp_document_render_details_box( get_post( $id ) );

			return (string) ob_get_clean();
		};

		$this->assertStringContainsString( 'may be imperfect', $box( $pptx ) );
		$this->assertStringNotContainsString( 'id="cptdp-pptx-warning" hidden', $box( $pptx ) );

		foreach ( array( $pdf, $none ) as $id ) {
			$html = $box( $id );
			$this->assertStringContainsString( 'id="cptdp-pptx-warning" hidden', $html, 'An empty container the picker script fills.' );
			$this->assertStringNotContainsString( 'may be imperfect', $html );
		}
	}

	public function test_the_picker_script_shows_and_hides_the_warning_and_the_data_reaches_it(): void {
		$js = (string) file_get_contents( CPTDP_PATH . 'assets/admin-document.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( 'cptdpDocument.pptxWarnings', $js );
		$this->assertStringContainsString( 'showPresentationWarning( attachment )', $js );
		$this->assertStringContainsString( 'showPresentationWarning( null )', $js, 'Removing the file hides it.' );
		$this->assertMatchesRegularExpression( '/pptx\|ppt\|odp\|xlsx\|xls\|ods/', $js );

		$php = (string) file_get_contents( CPTDP_PATH . 'includes/post-types/document.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( "'pptxWarnings' => cptdp_office_warnings()", $php );
	}

	public function test_the_one_time_notice_shows_once_after_a_presentation_is_chosen(): void {
		$this->acting_as( 'administrator' );
		$id = $this->make_document();
		update_post_meta( $id, 'cptdp_document_file', $this->make_presentation_attachment( 'pptx' ) );

		$GLOBALS['post']    = get_post( $id );
		$GLOBALS['typenow'] = 'document';
		set_current_screen( 'post' );
		get_current_screen()->post_type = 'document';

		$first  = $this->capture( 'cptdp_pptx_after_save_notice' );
		$second = $this->capture( 'cptdp_pptx_after_save_notice' );

		$this->assertStringContainsString( 'may be imperfect', $first );
		$this->assertSame( '', $second, 'Shown once.' );

		// A PDF change leaves nothing to show.
		update_post_meta( $id, 'cptdp_document_file', $this->make_pdf_attachment( 1 ) );
		$this->assertSame( '', $this->capture( 'cptdp_pptx_after_save_notice' ) );
		set_current_screen( 'front' );
	}

	// --- Settings ------------------------------------------------------------------------------

	private function conversion_status( string $family ): string {
		return $this->capture(
			static function () use ( $family ): void {
				cptdp_documents_render_conversion_status( array( 'family' => $family ) );
			}
		);
	}

	public function test_the_settings_page_shows_whether_conversion_is_available(): void {
		remove_all_filters( 'cptdp_pptx_available' );
		add_filter( 'cptdp_pptx_available', '__return_false' );
		$this->assertStringContainsString( 'Unavailable', $this->conversion_status( 'presentations' ) );

		remove_all_filters( 'cptdp_pptx_available' );
		add_filter( 'cptdp_pptx_available', '__return_true' );
		$this->assertStringContainsString( 'Available', $this->conversion_status( 'presentations' ) );
	}

	// --- The native viewer URL -----------------------------------------------------------------

	public function test_the_native_url_is_the_file_url_for_a_pptx_when_the_setting_is_on(): void {
		$this->preview( true );
		$attachment = $this->make_presentation_attachment( 'pptx' );
		$id         = $this->make_document( $attachment );

		$this->assertSame( wp_get_attachment_url( $attachment ), cptdp_document_native_pptx_url( $id ) );
	}

	public function test_the_native_url_is_empty_unless_every_rule_holds(): void {
		$pptx = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );

		$this->preview( false );
		$this->assertSame( '', cptdp_document_native_pptx_url( $pptx ), 'Off by default.' );

		$this->preview( true );
		$this->assertNotSame( '', cptdp_document_native_pptx_url( $pptx ) );

		$ppt = $this->make_document( $this->make_presentation_attachment( 'ppt' ) );
		$this->assertSame( '', cptdp_document_native_pptx_url( $ppt ), 'The viewer cannot read the legacy .ppt format.' );

		$odp = $this->make_document( $this->make_presentation_attachment( 'odp' ) );
		$this->assertSame( '', cptdp_document_native_pptx_url( $odp ), 'Nor OpenDocument.' );

		$pdf = $this->make_document( $this->make_pdf_attachment( 1 ) );
		$this->assertSame( '', cptdp_document_native_pptx_url( $pdf ), 'PDF has its own checkbox.' );

		$this->assertSame( '', cptdp_document_native_pptx_url( $this->make_document() ), 'No file.' );
	}

	public function test_the_native_viewer_takes_over_even_when_converted_slides_exist(): void {
		$this->preview( true );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$this->fake_pages( $id, 3 );
		$this->assertNotNull( cptdp_get_document_pages( $id ), 'The slides are valid.' );

		$this->assertNotSame( '', cptdp_document_native_pptx_url( $id ), 'Still offered: the converted slides are its fallback.' );
	}

	public function test_a_file_over_the_size_limit_is_not_offered_to_the_browser(): void {
		$this->preview( true );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		add_filter(
			'cptdp_native_pptx_max_bytes',
			static function (): int {
				return 10;
			}
		);

		$this->assertSame( '', cptdp_document_native_pptx_url( $id ) );
	}

	public function test_it_works_on_a_local_site_too(): void {
		$this->preview( true );
		add_filter(
			'home_url',
			static function (): string {
				return 'http://devportfolio.ddev.site';
			},
			99
		);
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );

		$this->assertNotSame( '', cptdp_document_native_pptx_url( $id ), 'Nothing leaves the site.' );
	}

	public function test_without_libreoffice_a_pptx_is_not_marked_failed_when_the_native_viewer_draws_it(): void {
		add_filter( 'cptdp_pptx_available', '__return_false' );

		$this->preview( false );
		$off = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $off ), 'Nothing draws it: the editor is told to upload a PDF.' );

		$this->preview( true );
		$on = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Store::get_status( $on ), 'A host that chose the native viewer has not failed.' );
		$this->assertSame( '', CPTDP_Page_Store::get_error( $on ) );

		$ppt = $this->make_document( $this->make_presentation_attachment( 'ppt' ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $ppt ), 'The native viewer cannot draw .ppt.' );
	}

	public function test_no_microsoft_address_is_left_in_the_plugin(): void {
		foreach ( array( 'includes/post-types/document-presentations.php', 'includes/settings/documents.php', 'assets/admin-document.js', 'assets/admin-document-pages.js' ) as $file ) {
			$this->assertStringNotContainsString( 'officeapps', (string) file_get_contents( CPTDP_PATH . $file ), $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	// --- DDEV ----------------------------------------------------------------------------------

	public function test_ddev_installs_libreoffice_and_the_metric_compatible_fonts(): void {
		$config = dirname( CPTDP_PATH, 3 ) . '/.ddev/config.yaml';

		if ( ! is_readable( $config ) ) {
			$this->markTestSkipped( 'No .ddev/config.yaml next to the plugin.' );
		}

		$yaml = (string) file_get_contents( $config ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertMatchesRegularExpression( '/^webimage_extra_packages:/m', $yaml );
		foreach ( array( 'libreoffice-impress', 'fonts-liberation', 'fonts-crosextra-carlito', 'fonts-crosextra-caladea' ) as $package ) {
			$this->assertStringContainsString( '- ' . $package, $yaml );
		}
	}
}
