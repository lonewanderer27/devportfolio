<?php
/**
 * Tests for the Brand Collaterals modal and sidebar (DPC-007 to DPC-011, DPC-020 to DPC-022).
 *
 * Helpers and markup are checked with the WordPress test library; the CSS and scripts are checked as
 * text (there is no browser runner; the browser check is recorded in the plan).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Modal extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		add_action( 'template_redirect', 'devportfolio_redirect_document_single', 1 );
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/collateral-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	private function term( string $name, string $taxonomy ): int {
		$found = term_exists( $name, $taxonomy );
		if ( $found ) {
			return (int) ( is_array( $found ) ? $found['term_id'] : $found );
		}
		return (int) wp_insert_term( $name, $taxonomy )['term_id'];
	}

	/**
	 * @param array $args { title, status, order, category, meta: array, content }.
	 */
	private function make_collateral( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'collateral',
				'post_title'   => $args['title'] ?? 'Product Launch Flyer',
				'post_status'  => $args['status'] ?? 'publish',
				'menu_order'   => $args['order'] ?? 0,
				'post_content' => $args['content'] ?? '',
				// Collaterals have no excerpt support: the description is always the first 20 words.
				'post_excerpt' => '',
			)
		);
		if ( isset( $args['category'] ) ) {
			wp_set_object_terms( $post_id, array( $this->term( $args['category'], 'collateral_category' ) ), 'collateral_category' );
		}
		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $post_id;
	}

	private function make_document( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_title'  => $args['title'] ?? 'Quarterly Report',
				'post_status' => $args['status'] ?? 'publish',
			)
		);
		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $post_id;
	}

	private function make_tool( string $title, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'software_tool',
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
	}

	/**
	 * An attachment with a mime type and an attached file name (no real file needed for the payload).
	 */
	private function make_attachment( string $mime, string $file = 'file.bin' ): int {
		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'post_title'     => 'file',
				'post_status'    => 'inherit',
			)
		);
		update_post_meta( $id, '_wp_attached_file', $file );
		return (int) $id;
	}

	private function render_modal(): string {
		return trim( do_blocks( '<!-- wp:devportfolio/collateral-modal /-->' ) );
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	private function xpath( string $html ): DOMXPath {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	private function payload_from( string $html ): array {
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="collaterals-modal__data">(.*?)</script>#s', $html, $m ) );
		$data = json_decode( $m[1], true );
		$this->assertIsArray( $data );
		return $data;
	}

	// ---- Pill ----

	public function test_pill_joins_paper_size_and_pages(): void {
		$this->assertSame( 'A4 · 2 pp', devportfolio_collateral_pill( 'A4', 2 ) );
		$this->assertSame( 'A4 · 1 p', devportfolio_collateral_pill( 'A4', 1 ) );
	}

	public function test_pill_leaves_out_a_missing_part(): void {
		$this->assertSame( 'A4', devportfolio_collateral_pill( 'A4', 0 ) );
		$this->assertSame( '2 pp', devportfolio_collateral_pill( '', '2' ) );
		$this->assertSame( '', devportfolio_collateral_pill( '', '' ) );
	}

	public function test_pill_trims_the_paper_size(): void {
		$this->assertSame( 'A4 Landscape · 3 pp', devportfolio_collateral_pill( '  A4 Landscape ', 3 ) );
	}

	// ---- Results ----

	public function test_stats_keep_order_and_skip_bad_rows(): void {
		$id = $this->make_collateral(
			array(
				'meta' => array(
					'cptdp_post_statistics' => array(
						array(
							'label' => 'Reach',
							'value' => '1.2M',
						),
						'not a row',
						array(
							'label' => '',
							'value' => '',
						),
						array(
							'label' => 'Clicks',
							'value' => '',
						),
						array(
							'label' => '',
							'value' => '4%',
						),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'label' => 'Reach',
					'value' => '1.2M',
				),
				array(
					'label' => 'Clicks',
					'value' => '',
				),
				array(
					'label' => '',
					'value' => '4%',
				),
			),
			devportfolio_collateral_stats( $id )
		);
	}

	public function test_stats_strip_markup(): void {
		$id    = $this->make_collateral( array( 'meta' => array( 'cptdp_post_statistics' => array( array( 'label' => '<b>Views</b>', 'value' => '<i>9</i>' ) ) ) ) );
		$stats = devportfolio_collateral_stats( $id );
		$this->assertSame( 'Views', $stats[0]['label'] );
		$this->assertSame( '9', $stats[0]['value'] );
	}

	public function test_stats_are_empty_without_meta(): void {
		$this->assertSame( array(), devportfolio_collateral_stats( $this->make_collateral() ) );
	}

	// ---- Payload ----

	public function test_payload_is_null_for_a_non_collateral(): void {
		$this->assertNull( devportfolio_collateral_modal_payload( $this->make_document() ) );
		$this->assertNull( devportfolio_collateral_modal_payload( self::factory()->post->create() ) );
	}

	public function test_payload_has_the_sidebar_fields(): void {
		$tool_a = $this->make_tool( 'Figma' );
		$tool_d = $this->make_tool( 'Draft Tool', 'draft' );
		$id     = $this->make_collateral(
			array(
				'title'    => 'Logo & Mark "Pack"',
				'category' => 'Logo',
				'content'  => 'One two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty twenty-one',
				'meta'     => array(
					'cptdp_paper_size'      => 'A4 Landscape',
					'cptdp_total_pages'     => 2,
					'cptdp_software_tools'  => array( $tool_a, $tool_d ),
					'cptdp_post_statistics' => array(
						array(
							'label' => 'Views',
							'value' => '9',
						),
					),
				),
			)
		);

		$item = devportfolio_collateral_modal_payload( $id );
		$this->assertSame( $id, $item['id'] );
		$this->assertSame( 'Logo & Mark "Pack"', $item['title'], 'plain text, entities decoded' );
		$this->assertSame( 'Logo', $item['category'] );
		$this->assertSame( array( 'logo' ), $item['categories'] );
		$this->assertSame( 'One two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty…', $item['description'] );
		$this->assertSame( 'A4 Landscape · 2 pp', $item['pill'] );
		$this->assertSame( array( 'Figma' ), $item['tools'], 'a draft tool is not shown' );
		$this->assertSame( 'Views', $item['stats'][0]['label'] );
		$this->assertSame( 'none', $item['downloadMode'] );
		$this->assertSame( '', $item['thumb']['src'], 'no page set and no image: the numbered blank' );
		$this->assertGreaterThan( 0, $item['thumb']['ratio'] );
	}

	public function test_a_collateral_without_optional_data_has_empty_values_and_no_notices(): void {
		$id   = $this->make_collateral();
		$item = devportfolio_collateral_modal_payload( $id );

		$this->assertSame( '', $item['category'] );
		$this->assertSame( array(), $item['categories'] );
		$this->assertSame( '', $item['pill'] );
		$this->assertSame( array(), $item['stats'] );
		$this->assertSame( array(), $item['tools'] );
	}

	public function test_the_payload_carries_the_preview_state(): void {
		$pdf = $this->make_attachment( 'application/pdf', 'report.pdf' );
		$id  = $this->make_collateral( array( 'meta' => array( 'cptdp_collateral_file' => $pdf ) ) );

		// A PDF can be drawn by pdf.js even when no page images exist: it is ready, not unavailable.
		$this->assertSame( 'ready', devportfolio_collateral_modal_payload( $id )['previewState'] );

		$image = $this->make_attachment( 'image/png', 'poster.png' );
		$id    = $this->make_collateral( array( 'meta' => array( 'cptdp_collateral_file' => $image ) ) );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );
		$this->assertSame( 'pending', devportfolio_collateral_modal_payload( $id )['previewState'] );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_FAILED );
		$this->assertSame( 'unavailable', devportfolio_collateral_modal_payload( $id )['previewState'] );

		$this->assertSame( 'unavailable', devportfolio_collateral_modal_payload( $this->make_collateral() )['previewState'] );
	}

	public function test_the_document_payload_has_no_preview_state(): void {
		$payload = devportfolio_document_modal_payload( $this->make_document() );

		$this->assertArrayNotHasKey( 'previewState', $payload );
	}

	public function test_a_pending_thumb_uses_the_loading_image(): void {
		$image = $this->make_attachment( 'image/png', 'poster.png' );
		$id    = $this->make_collateral( array( 'meta' => array( 'cptdp_collateral_file' => $image ) ) );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );

		$item = devportfolio_collateral_modal_payload( $id );

		$this->assertStringContainsString( 'assets/collateral-loading.svg', $item['thumb']['src'] );
	}


	public function test_thumb_is_the_featured_image_when_there_is_no_page_set(): void {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$id         = $this->make_collateral();
		set_post_thumbnail( $id, $attachment );

		$thumb = devportfolio_collateral_thumb( devportfolio_collateral_preview( $id ) );
		$this->assertNotSame( '', $thumb['src'] );
		$this->assertStringContainsString( 'canola', $thumb['src'] );
		$this->assertSame( '', $thumb['srcset'] );
	}

	public function test_thumb_of_a_blank_is_empty(): void {
		$thumb = devportfolio_collateral_thumb( array( 'kind' => 'blank', 'ratio' => 0.7071, 'src' => '', 'srcset' => '', 'featured_id' => 0 ) );
		$this->assertSame( '', $thumb['src'] );
		$this->assertSame( 0.7071, $thumb['ratio'] );
	}

	public function test_strings_are_the_three_the_script_writes(): void {
		$strings = devportfolio_collateral_modal_strings();
		$this->assertSame( array( 'open', 'copied', 'copyHint' ), array_keys( $strings ) );
		$this->assertSame( 'Open %s', $strings['open'] );
	}

	// ---- Download ----

	public function test_collateral_download_modes(): void {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$file = $this->make_collateral( array( 'meta' => array( 'cptdp_collateral_file' => $attachment ) ) );
		$this->assertSame( 'file', devportfolio_document_download_target( $file, 'cptdp_collateral_file' )['mode'] );

		$embed = $this->make_collateral( array( 'meta' => array( 'cptdp_embed_url' => 'https://example.com/e' ) ) );
		$this->assertSame( 'embed', devportfolio_document_download_target( $embed, 'cptdp_collateral_file' )['mode'] );

		$script = $this->make_collateral( array( 'meta' => array( 'cptdp_embed_url' => 'javascript:alert(1)' ) ) );
		$this->assertSame( 'none', devportfolio_document_download_target( $script, 'cptdp_collateral_file' )['mode'] );

		$none = $this->make_collateral();
		$this->assertSame( 'none', devportfolio_document_download_target( $none, 'cptdp_collateral_file' )['mode'] );
	}

	public function test_a_document_still_resolves_its_file_with_the_default_key(): void {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$doc        = $this->make_document( array( 'meta' => array( 'cptdp_document_file' => $attachment ) ) );

		$this->assertSame( 'file', devportfolio_document_download_target( $doc )['mode'] );
		$this->assertSame( 'none', devportfolio_document_download_target( $doc, 'cptdp_collateral_file' )['mode'] );
	}

	// ---- Markup ----

	public function test_one_desktop_and_one_mobile_dialog_labelled_by_their_titles(): void {
		$html = $this->render_modal();
		$this->assertSame( 2, substr_count( $html, '<dialog ' ) );

		$xpath = $this->xpath( $html );
		foreach ( array( 'collaterals-modal', 'collaterals-mobile' ) as $class ) {
			$dialog = $xpath->query( "//dialog[contains(@class,'{$class}')]" )->item( 0 );
			$this->assertNotNull( $dialog, $class );
			$labelled = $dialog->getAttribute( 'aria-labelledby' );
			$this->assertNotSame( '', $labelled );
			$this->assertSame( 1, $xpath->query( "//*[@id='{$labelled}']" )->length, "$class title exists" );
		}
	}

	public function test_payload_order_follows_menu_order_then_title_and_drafts_are_left_out(): void {
		$this->make_collateral(
			array(
				'title' => 'Zed',
				'order' => 0,
			)
		);
		$this->make_collateral(
			array(
				'title' => 'Alpha',
				'order' => 0,
			)
		);
		$this->make_collateral(
			array(
				'title' => 'First',
				'order' => -1,
			)
		);
		$this->make_collateral(
			array(
				'title'  => 'Draft',
				'status' => 'draft',
			)
		);

		$data = $this->payload_from( $this->render_modal() );
		$titles = array_map( fn( $id ) => $data['items'][ (string) $id ]['title'], $data['order'] );
		$this->assertSame( array( 'First', 'Alpha', 'Zed' ), $titles );
		$this->assertCount( 3, $data['items'] );
	}

	public function test_script_breaking_text_in_a_title_is_hex_escaped(): void {
		// Core would strip a script tag from a title saved without unfiltered_html; the payload must still be safe.
		kses_remove_filters();
		$this->make_collateral( array( 'title' => '</script><b>&' ) );
		kses_init_filters();
		$html = $this->render_modal();

		$this->assertSame( 1, substr_count( $html, '</script>' ), 'only the closing tag of the payload' );
		$this->assertStringNotContainsString( '<b>', $html );
		$data = $this->payload_from( $html );
		$this->assertSame( '</script><b>&', $data['items'][ (string) $data['order'][0] ]['title'] );
	}

	public function test_empty_regions_are_hidden_in_the_markup(): void {
		$this->make_collateral();
		$xpath = $this->xpath( $this->render_modal() );

		foreach ( array( 'pills', 'desc', 'results', 'tools', 'next' ) as $region ) {
			$node = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' collaterals-modal__{$region} ')]" )->item( 0 );
			$this->assertNotNull( $node, $region );
			$this->assertTrue( $node->hasAttribute( 'hidden' ), "$region is hidden until the script fills it" );
		}
		$this->assertTrue( $xpath->query( "//footer[contains(@class,'collaterals-modal__footer')]" )->item( 0 )->hasAttribute( 'hidden' ) );
	}

	public function test_close_buttons_and_the_copy_control_have_names(): void {
		$xpath = $this->xpath( $this->render_modal() );
		foreach ( array( "//button[contains(@class,'collaterals-modal__close')]", "//button[contains(@class,'collaterals-mobile__close')]" ) as $query ) {
			$node = $xpath->query( $query )->item( 0 );
			$this->assertNotNull( $node );
			$this->assertSame( 'Close viewer', $node->getAttribute( 'aria-label' ) );
		}
		$this->assertNotNull( $xpath->query( "//button[contains(@class,'collaterals-modal__copy')]" )->item( 0 ) );
	}

	public function test_the_block_is_registered(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/collateral-modal' ) );
	}

	public function test_template_places_the_modal_once_after_the_grid_inside_the_content(): void {
		$template = $this->read( 'templates/archive-collateral.html' );

		$this->assertSame( 1, substr_count( $template, 'wp:devportfolio/collateral-modal' ) );
		$this->assertStringNotContainsString( 'added by the modal ticket', $template );
		$grid  = strpos( $template, 'wp:devportfolio/collateral-grid' );
		$modal = strpos( $template, 'wp:devportfolio/collateral-modal' );
		$this->assertGreaterThan( $grid, $modal );
		$this->assertGreaterThan( strpos( $template, 'documents-page__content' ), $modal );
	}

	// ---- Redirect (DPC-020) ----

	public function test_a_published_collateral_single_redirects_to_its_deeplink(): void {
		$id = $this->make_collateral( array( 'title' => 'Product Launch Flyer' ) );
		$this->go_to( get_permalink( $id ) );

		$url = devportfolio_collateral_single_redirect_url();
		$this->assertStringContainsString( 'collateral_slug=product-launch-flyer', $url );
	}

	public function test_drafts_pending_private_and_trashed_collaterals_are_not_redirected(): void {
		foreach ( array( 'draft', 'pending', 'private', 'trash' ) as $status ) {
			$id = $this->make_collateral( array( 'title' => "Item $status", 'status' => $status ) );
			$this->go_to( get_permalink( $id ) );
			$this->assertSame( '', devportfolio_collateral_single_redirect_url(), $status );
		}
	}

	public function test_a_document_single_still_redirects_with_its_own_parameter(): void {
		$id = $this->make_document( array( 'title' => 'Quarterly Report' ) );
		$this->go_to( get_permalink( $id ) );

		$this->assertStringContainsString( 'document_slug=quarterly-report', devportfolio_document_single_redirect_url() );
		$this->assertSame( '', devportfolio_collateral_single_redirect_url() );
	}

	public function test_the_archive_and_a_post_are_not_redirected(): void {
		$this->make_collateral();
		$this->go_to( get_post_type_archive_link( 'collateral' ) );
		$this->assertSame( '', devportfolio_collateral_single_redirect_url() );

		$post = self::factory()->post->create( array( 'post_title' => 'Note' ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( '', devportfolio_collateral_single_redirect_url() );
	}

	public function test_the_redirect_hook_is_on_template_redirect_at_priority_1(): void {
		$this->assertSame( 1, has_action( 'template_redirect', 'devportfolio_redirect_document_single' ) );
	}

	// ---- CSS contract ----

	private function media_block( string $css, string $query ): string {
		$pattern = '/@media ' . preg_quote( $query, '/' ) . '\s*\{(.*?)\n\}/s';
		return preg_match( $pattern, $css, $match ) ? $match[1] : '';
	}

	public function test_both_breakpoints_match_routing_js(): void {
		$css = $this->read( 'blocks/collateral-modal/style.css' );
		$sideways = '(pointer: coarse) and (orientation: landscape) and (max-height: 520px)';

		$this->assertStringContainsString( "@media (max-width: 700px), {$sideways} {", $css );
		$this->assertStringContainsString( "@media (min-width: 701px) and (not ({$sideways})) {", $css );
		$this->assertStringContainsString( '(min-width: 701px)', $this->read( 'blocks/shared/routing.js' ) );
	}

	public function test_the_mobile_dialog_is_full_screen_without_radius_or_backdrop(): void {
		$css   = $this->read( 'blocks/collateral-modal/style.css' );
		$block = $this->rule( $css, '.collaterals-mobile' );
		$this->assertStringContainsString( 'inset: 0;', $block );
		$this->assertStringContainsString( 'border-radius: 0;', $block );
		$this->assertStringContainsString( 'background: transparent;', $this->rule( $css, '.collaterals-mobile::backdrop' ) );
	}

	public function test_the_desktop_width_uses_min_and_leaves_32px(): void {
		$css = $this->read( 'blocks/collateral-modal/style.css' );
		$this->assertStringContainsString( 'width: min( var( --cm-width ), calc( 100% - 32px ) );', $css );
		$this->assertStringContainsString( '--cm-width: 1680px;', $css );
	}

	public function test_the_actions_sit_outside_the_scrolling_middle(): void {
		$html = $this->render_modal();
		$scroll = strpos( $html, 'collaterals-modal__scroll' );
		$close  = strpos( $html, '</div>', strpos( $html, 'collaterals-modal__next' ) );
		$actions = strpos( $html, 'collaterals-modal__actions' );
		$this->assertGreaterThan( $scroll, $actions );
		$this->assertGreaterThan( $close, $actions, 'actions come after the scroller closes' );
	}

	public function test_reduced_motion_turns_the_animations_off(): void {
		$css = $this->read( 'blocks/collateral-modal/style.css' );
		$this->assertStringContainsString( '@media (prefers-reduced-motion: reduce)', $css );
		$this->assertStringContainsString( 'animation: none;', $css );
	}

	public function test_the_statistic_label_is_uppercase_and_the_value_is_not(): void {
		$css = $this->read( 'blocks/collateral-modal/style.css' );
		$this->assertStringContainsString( 'text-transform: uppercase;', $this->rule( $css, '.collaterals-modal__stats dt' ) );
		$this->assertStringNotContainsString( 'text-transform', $this->rule( $css, '.collaterals-modal__stats dd' ) );
	}

	public function test_the_desktop_body_has_the_same_dotted_stage_as_the_documents_modal(): void {
		$css  = $this->read( 'blocks/collateral-modal/style.css' );
		$body = $this->rule( $css, '.collaterals-modal__body' );

		$this->assertStringContainsString( 'radial-gradient', $body );
		$this->assertStringContainsString( 'background-size: 22px 22px', $body );
		$this->assertStringContainsString( 'var( --cm-surface-alt )', $body );
	}

	/**
	 * The bodies of the rules whose selector line is exactly $selector (top level, not inside @media).
	 */
	private function rule( string $css, string $selector ): string {
		$pattern = '/(?:^|\n)' . preg_quote( $selector, '/' ) . ' \{(.*?)\n\}/s';
		return preg_match_all( $pattern, $css, $matches ) ? implode( "\n", $matches[1] ) : '';
	}

	// ---- Script contract ----

	public function test_the_script_writes_text_only_and_uses_the_shared_helpers(): void {
		$js = $this->read( 'blocks/collateral-modal/view.js' );

		$this->assertStringNotContainsString( 'innerHTML', $js );
		$this->assertStringContainsString( "createDeeplink( 'collateral_slug' )", $js );
		$this->assertStringNotContainsString( "'collateral_slug'", str_replace( "createDeeplink( 'collateral_slug' )", '', $js ), 'the parameter name lives only in createDeeplink' );
		$this->assertStringContainsString( "from '../shared/routing.js'", $js );
		$this->assertStringContainsString( "from '../shared/download.js'", $js );
	}

	public function test_back_and_forward_are_handled_in_the_capture_phase_at_top_level(): void {
		$js = $this->read( 'blocks/collateral-modal/view.js' );

		$this->assertMatchesRegularExpression( "/addEventListener\\(\\s*'popstate'/", $js );
		$this->assertMatchesRegularExpression( '/\\},\\s*true\\s*\\);/', $js, 'capture flag' );
		$this->assertStringContainsString( 'stopImmediatePropagation()', $js );
		$this->assertStringNotContainsString( 'DOMContentLoaded', $js );
	}

	public function test_an_unknown_slug_is_dropped_and_a_known_one_opens_without_a_push(): void {
		$js = $this->read( 'blocks/collateral-modal/view.js' );
		$this->assertMatchesRegularExpression( '/if \\( ! idBySlug\\[ linked \\] \\) \\{\\s*window\\.history\\.replaceState/', $js );
		$this->assertMatchesRegularExpression( '/show\\( id, \\{\\s*push: false/', $js );
	}

	public function test_the_swap_path_has_no_animation_frame(): void {
		$js = $this->read( 'blocks/collateral-modal/view.js' );
		$start = strpos( $js, 'const swapTo' );
		$end   = strpos( $js, 'const swapDialogs' );
		$this->assertNotFalse( $start );
		$this->assertStringNotContainsString( 'requestAnimationFrame', substr( $js, $start, $end - $start ) );
	}
}
