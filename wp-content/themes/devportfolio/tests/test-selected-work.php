<?php
/**
 * Tests for the Home Selected work cards (DPH-010 to DPH-017, DPH-019 to DPH-021).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Selected_Work extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/selected-work' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	/**
	 * Create a document.
	 *
	 * @param string $title     Title.
	 * @param int    $order     Menu order.
	 * @param bool   $highlight Whether it is picked for Selected work.
	 * @param string $status    Post status.
	 * @return int Post ID.
	 */
	private function make_document( string $title, int $order = 0, bool $highlight = true, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);
		if ( $highlight ) {
			update_post_meta( $id, 'cptdp_highlight', 1 );
		}

		return $id;
	}

	/**
	 * Create a video.
	 *
	 * @param string $title     Title.
	 * @param int    $order     Menu order.
	 * @param bool   $highlight Whether it is picked for Selected work.
	 * @param int    $length    Length in seconds.
	 * @param bool   $file      Whether it has an uploaded video file (so it can preview).
	 * @return int Post ID.
	 */
	private function make_video( string $title, int $order = 0, bool $highlight = true, int $length = 0, bool $file = false ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'video',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => 'publish',
			)
		);
		if ( $highlight ) {
			update_post_meta( $id, 'cptdp_highlight', 1 );
		}
		if ( $length > 0 ) {
			update_post_meta( $id, 'cptdp_length', $length );
		}
		if ( $file ) {
			$attachment = (int) self::factory()->attachment->create(
				array(
					'post_mime_type' => 'video/mp4',
					'file'           => 'sample.mp4',
				)
			);
			update_post_meta( $id, 'cptdp_video_file', $attachment );
		}

		return $id;
	}

	/**
	 * Create a collateral.
	 *
	 * @param string $title     Title.
	 * @param int    $order     Menu order.
	 * @param bool   $highlight Whether it is picked for Selected work.
	 * @param string $paper     Paper size (a landscape paper size makes a wide blank preview).
	 * @return int Post ID.
	 */
	private function make_collateral( string $title, int $order = 0, bool $highlight = true, string $paper = 'A4' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'collateral',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, 'cptdp_paper_size', $paper );
		if ( $highlight ) {
			update_post_meta( $id, 'cptdp_highlight', 1 );
		}

		return $id;
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/selected-work /-->' );
	}

	private function xpath( string $html ): DOMXPath {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		return new DOMXPath( $dom );
	}

	private function xpath_in( DOMNode $node, string $query ): DOMNodeList {
		return ( new DOMXPath( $node->ownerDocument ) )->query( $query, $node );
	}

	private function file( string $relative ): string {
		$path = $this->theme_dir() . '/' . $relative;
		$this->assertFileExists( $path );

		return (string) file_get_contents( $path );
	}

	/**
	 * The text of a CSS rule or at-rule body that starts with `$selector {`.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Exact selector text.
	 * @return string The declarations, or '' when the rule is not there.
	 */
	private function rule( string $css, string $selector ): string {
		// A rule starts at the beginning of the file or right after a `}`, a `{` (an at-rule opening) or a comment.
		if ( preg_match( '/(?:^|[{}\/])\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $css, $m ) ) {
			return $m[1];
		}

		return '';
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-010: layout and the three cards.
	// ---------------------------------------------------------------------------------------------

	public function test_three_cards_in_a_fixed_order(): void {
		$xp    = $this->xpath( $this->render() );
		$cards = $xp->query( '//article[contains(@class,"work-card")]' );

		$this->assertSame( 3, $cards->length );
		$this->assertStringContainsString( 'work-card--documents', $cards->item( 0 )->getAttribute( 'class' ) );
		$this->assertStringContainsString( 'work-card--videos', $cards->item( 1 )->getAttribute( 'class' ) );
		$this->assertStringContainsString( 'work-card--collaterals', $cards->item( 2 )->getAttribute( 'class' ) );

		$titles = array();
		foreach ( $xp->query( '//h3[contains(@class,"work-card__title")]' ) as $h3 ) {
			$titles[] = trim( $h3->textContent );
		}
		$this->assertSame( array( 'Documents', 'Videos', 'Brand collaterals' ), $titles );
	}

	public function test_the_cards_render_with_nothing_picked(): void {
		$xp = $this->xpath( $this->render() );

		$this->assertSame( 3, $xp->query( '//article[contains(@class,"work-card")]' )->length );
		$this->assertSame( 0, $xp->query( '//*[contains(@class,"work-card__visual")]' )->length );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-011, DPH-015, DPH-019: the text side.
	// ---------------------------------------------------------------------------------------------

	public function test_each_card_has_its_line_and_pill_text(): void {
		$xp = $this->xpath( $this->render() );

		$lines = array();
		foreach ( $xp->query( '//p[contains(@class,"work-card__line")]' ) as $p ) {
			$lines[] = trim( $p->textContent );
		}
		$this->assertSame(
			array(
				'SOPs, trackers, travel plans and reports.',
				'Short-form edits, social content and AI clips.',
				'Brochures, flyers, newsletters, vouchers and coupons.',
			),
			$lines
		);

		$buttons = array();
		foreach ( $xp->query( '//a[contains(@class,"work-card__btn")]' ) as $a ) {
			$buttons[] = trim( $a->textContent );
		}
		$this->assertSame( array( 'View all documents →', 'View all videos →', 'View all collaterals →' ), $buttons );
	}

	public function test_the_text_side_and_the_button_open_the_same_page(): void {
		$xp = $this->xpath( $this->render() );

		$expected = array(
			get_post_type_archive_link( 'document' ),
			get_post_type_archive_link( 'video' ),
			get_post_type_archive_link( 'collateral' ),
		);

		foreach ( $expected as $index => $url ) {
			$this->assertNotEmpty( $url );
			$card = $xp->query( '//article[contains(@class,"work-card")]' )->item( $index );
			$this->assertSame( $url, $xp->query( './/a[contains(@class,"work-card__text")]', $card )->item( 0 )->getAttribute( 'href' ) );
			$this->assertSame( $url, $xp->query( './/a[contains(@class,"work-card__btn")]', $card )->item( 0 )->getAttribute( 'href' ) );
		}
	}

	public function test_the_pill_never_wraps(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );

		$this->assertStringContainsString( 'white-space: nowrap', $this->rule( $css, '.work-card__btn' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-012: the book fan.
	// ---------------------------------------------------------------------------------------------

	public function test_only_picked_published_documents_show_in_order_and_at_most_four(): void {
		$this->make_document( 'Fifth', 50 );
		$this->make_document( 'Second', 20 );
		$this->make_document( 'First', 10 );
		$this->make_document( 'Not picked', 5, false );
		$this->make_document( 'Draft pick', 1, true, 'draft' );
		$this->make_document( 'Fourth', 40 );
		$this->make_document( 'Third', 30 );

		$buttons = $this->xpath( $this->render() )->query( '//button[contains(@class,"work-docs__open")]' );

		$labels = array();
		foreach ( $buttons as $button ) {
			$labels[] = $button->getAttribute( 'aria-label' );
		}
		$this->assertSame( array( 'First', 'Second', 'Third', 'Fourth' ), $labels );
	}

	public function test_each_book_is_a_button_named_by_its_title_that_carries_the_document_id(): void {
		$id = $this->make_document( 'Client Onboarding SOP' );

		$button = $this->xpath( $this->render() )->query( '//button[contains(@class,"work-docs__open")]' )->item( 0 );

		$this->assertSame( 'button', $button->getAttribute( 'type' ) );
		$this->assertSame( (string) $id, $button->getAttribute( 'data-document-id' ) );
		$this->assertSame( 'Client Onboarding SOP', $button->getAttribute( 'aria-label' ) );
	}

	public function test_an_ampersand_in_a_title_is_not_double_encoded(): void {
		$this->make_document( 'Terms & Conditions' );

		$button = $this->xpath( $this->render() )->query( '//button[contains(@class,"work-docs__open")]' )->item( 0 );

		$this->assertSame( 'Terms & Conditions', $button->getAttribute( 'aria-label' ) );
	}

	public function test_the_books_use_the_documents_page_cover(): void {
		$this->make_document( 'A' );

		$xp = $this->xpath( $this->render() );

		$this->assertSame( 1, $xp->query( '//div[contains(@class,"documents-book")][@data-cover-style]' )->length );
		$this->assertSame( 1, $xp->query( '//*[contains(@class,"documents-book__cat")]' )->length );
		$this->assertSame( 1, $xp->query( '//*[contains(@class,"documents-book__format")]' )->length );
		$this->assertSame( 'DAVE', trim( $xp->query( '//*[contains(@class,"documents-book__owner")]' )->item( 0 )->textContent ) );
	}

	public function test_the_first_book_is_in_front_and_only_it_can_be_reached(): void {
		foreach ( array( 'A', 'B', 'C', 'D' ) as $index => $title ) {
			$this->make_document( $title, $index );
		}

		$items = $this->xpath( $this->render() )->query( '//div[contains(@class,"work-docs__item")]' );

		$this->assertSame( 4, $items->length );
		$roles = array();
		foreach ( $items as $item ) {
			$roles[] = $item->getAttribute( 'data-role' );
			$this->assertSame( 'front' !== $item->getAttribute( 'data-role' ), $item->hasAttribute( 'inert' ) );
		}
		$this->assertSame( array( 'front', 'right', 'hidden', 'left' ), $roles );
	}

	public function test_first_paint_roles_match_the_script(): void {
		// The same tables as blocks/selected-work/tests/rotation.test.mjs, with the first item active.
		$books = array(
			4 => array( 'front', 'right', 'hidden', 'left' ),
			3 => array( 'front', 'right', 'left' ),
			2 => array( 'front', 'right' ),
			1 => array( 'front' ),
		);
		foreach ( $books as $count => $expected ) {
			$roles = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$roles[] = devportfolio_work_book_role( $i, $count );
			}
			$this->assertSame( $expected, $roles, "{$count} books" );
		}

		$frames = array(
			5 => array( 'active', 'next', 'far-next', 'far-prev', 'prev' ),
			3 => array( 'active', 'next', 'prev' ),
			2 => array( 'active', 'next' ),
			1 => array( 'active' ),
		);
		foreach ( $frames as $count => $expected ) {
			$roles = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$roles[] = devportfolio_work_frame_role( $i, $count );
			}
			$this->assertSame( $expected, $roles, "{$count} frames" );
		}
	}

	public function test_the_fan_geometry_follows_the_story(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );
		$fan = $this->rule( $css, '.work-docs' );

		$this->assertStringContainsString( '--work-book-w: 150px', $fan );
		$this->assertStringContainsString( '--work-book-h: 225px', $fan );
		$this->assertStringContainsString( '--work-spread: 40px', $fan );
		$this->assertStringContainsString( '--work-book-w: 112px', $css );
		$this->assertStringContainsString( '@media (min-width: 621px) and (max-width: 1200px)', $css );

		$this->assertStringContainsString( 'rotate(7deg)', $this->rule( $css, '.work-docs__item[data-role="right"]' ) );
		$this->assertStringContainsString( 'rotate(-7deg)', $this->rule( $css, '.work-docs__item[data-role="left"]' ) );
		$this->assertStringContainsString( 'brightness(0.82)', $this->rule( $css, '.work-docs__item[data-role="right"],' . "\n" . '.work-docs__item[data-role="left"]' ) );
		$this->assertStringContainsString( 'translate: 0 -8px', $this->rule( $css, '.work-docs__item[data-role="front"]:hover' ) );
		$this->assertStringContainsString( '--work-book-w: 170px', $css );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-016: the film strip.
	// ---------------------------------------------------------------------------------------------

	public function test_up_to_five_picked_clips_become_frames_in_order(): void {
		for ( $i = 6; $i >= 1; $i-- ) {
			$this->make_video( "Clip {$i}", $i * 10 );
		}
		$this->make_video( 'Not picked', 1, false );

		$frames = $this->xpath( $this->render() )->query( '//div[contains(@class,"work-strip__frame")]' );

		$labels = array();
		$roles  = array();
		foreach ( $frames as $frame ) {
			$labels[] = $this->xpath_in( $frame, './/button' )->item( 0 )->getAttribute( 'aria-label' );
			$roles[]  = $frame->getAttribute( 'data-role' );
		}
		$this->assertSame( array( 'Clip 1', 'Clip 2', 'Clip 3', 'Clip 4', 'Clip 5' ), $labels );
		$this->assertSame( array( 'active', 'next', 'far-next', 'far-prev', 'prev' ), $roles );
	}

	public function test_a_frame_is_a_button_named_by_the_clip_title_and_carries_its_length(): void {
		$id = $this->make_video( 'Product Launch Teaser', 1, true, 40 );

		$xp     = $this->xpath( $this->render() );
		$frame  = $xp->query( '//div[contains(@class,"work-strip__frame")]' )->item( 0 );
		$button = $xp->query( './/button[contains(@class,"work-strip__open")]', $frame )->item( 0 );

		$this->assertSame( 'button', $button->getAttribute( 'type' ) );
		$this->assertSame( (string) $id, $button->getAttribute( 'data-video-id' ) );
		$this->assertSame( 'Product Launch Teaser', $button->getAttribute( 'aria-label' ) );
		$this->assertSame( '40', $frame->getAttribute( 'data-length' ) );
		$this->assertFalse( $frame->hasAttribute( 'role' ) );
	}

	public function test_only_the_active_frame_can_be_reached(): void {
		foreach ( array( 'A', 'B', 'C' ) as $index => $title ) {
			$this->make_video( $title, $index );
		}

		$frames = $this->xpath( $this->render() )->query( '//div[contains(@class,"work-strip__frame")]' );

		$this->assertSame( 3, $frames->length );
		foreach ( $frames as $frame ) {
			$this->assertSame( 'active' !== $frame->getAttribute( 'data-role' ), $frame->hasAttribute( 'inert' ) );
		}
	}

	public function test_only_a_clip_with_a_stored_file_or_clip_has_a_preview_source(): void {
		$this->make_video( 'Has file', 1, true, 5, true );
		$this->make_video( 'Embed only', 2, true, 5, false );

		$frames = $this->xpath( $this->render() )->query( '//div[contains(@class,"work-strip__frame")]' );

		$this->assertTrue( $frames->item( 0 )->hasAttribute( 'data-preview-src' ) );
		$this->assertStringContainsString( 'sample.mp4', $frames->item( 0 )->getAttribute( 'data-preview-src' ) );
		$this->assertFalse( $frames->item( 1 )->hasAttribute( 'data-preview-src' ) );
	}

	public function test_the_server_never_writes_a_video_element_or_controls(): void {
		$this->make_video( 'A', 1, true, 5, true );

		$html = $this->render();

		$this->assertStringNotContainsString( '<video', $html );
		$this->assertStringNotContainsString( 'controls', $html );
		$this->assertStringNotContainsString( 'autoplay', $html );
	}

	public function test_only_the_active_frame_shows_the_badge_time_and_bar(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );

		$this->assertStringContainsString( ':not([data-role="active"])', $css );
		$this->assertStringContainsString( '.work-strip__eq, .work-strip__time, .work-strip__bar', $css );
	}

	public function test_the_strip_geometry_follows_the_story(): void {
		$css   = $this->file( 'blocks/selected-work/style.css' );
		$strip = $this->rule( $css, '.work-strip' );

		$this->assertStringContainsString( '--fw: min(100px, calc(100cqi / 3.2))', $strip );
		$this->assertStringContainsString( 'var(--wp--preset--color--contrast)', $strip );
		$this->assertStringContainsString( 'mask-image', $strip );
		$this->assertStringContainsString( 'scale(0.78)', $this->rule( $css, '.work-strip__frame[data-role="prev"]' ) );
		$this->assertStringContainsString( 'opacity: 0.55', $this->rule( $css, '.work-strip__frame[data-role="prev"],' . "\n" . '.work-strip__frame[data-role="next"]' ) );
		$this->assertStringContainsString( 'height: 4px', $this->rule( $css, '.work-strip__bar' ) );
		$this->assertStringContainsString( '--fw: calc(100cqi / 2.8)', $css );
		$this->assertStringContainsString( 'width: 100%', $css );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-020: the collaterals glimpse.
	// ---------------------------------------------------------------------------------------------

	public function test_pieces_are_buttons_named_by_title_with_their_real_ratio(): void {
		$id = $this->make_collateral( 'Open House Flyer' );
		$this->make_collateral( 'Wide Banner', 2, true, 'A4 Landscape' );

		$pieces = $this->xpath( $this->render() )->query( '//button[contains(@class,"work-piece")]' );

		$this->assertSame( 2, $pieces->length );
		$this->assertSame( (string) $id, $pieces->item( 0 )->getAttribute( 'data-collateral-id' ) );
		$this->assertSame( 'Open House Flyer', $pieces->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertStringContainsString( '--ratio:0.7071', $pieces->item( 0 )->getAttribute( 'style' ) );
		$this->assertStringContainsString( '--ratio:1.4142', $pieces->item( 1 )->getAttribute( 'style' ) );
	}

	public function test_only_picked_collaterals_show_in_order(): void {
		$this->make_collateral( 'B', 20 );
		$this->make_collateral( 'A', 10 );
		$this->make_collateral( 'Skipped', 5, false );

		$pieces = $this->xpath( $this->render() )->query( '//button[contains(@class,"work-piece")]' );

		$this->assertSame( 'A', $pieces->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertSame( 'B', $pieces->item( 1 )->getAttribute( 'aria-label' ) );
		$this->assertSame( 2, $pieces->length );
	}

	public function test_the_fourth_and_later_pieces_are_marked_for_the_ipad_cut(): void {
		foreach ( array( 'A', 'B', 'C', 'D', 'E' ) as $index => $title ) {
			$this->make_collateral( $title, $index );
		}

		$xp     = $this->xpath( $this->render() );
		$extra  = $xp->query( '//button[contains(@class,"work-piece--extra")]' );
		$labels = array();
		foreach ( $extra as $piece ) {
			$labels[] = $piece->getAttribute( 'aria-label' );
		}

		$this->assertSame( array( 'D', 'E' ), $labels );
		$this->assertSame( 2, $xp->query( '//div[contains(@class,"work-glimpse__col--extra")]' )->length );
		$this->assertSame( 3, $xp->query( '//div[contains(@class,"work-glimpse__col") and not(contains(@class,"--extra"))]' )->length );
	}

	public function test_the_ipad_size_shows_three_pieces_and_no_more(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );

		$this->assertStringContainsString( '@media (min-width: 621px) and (max-width: 900px)', $css );
		$this->assertStringContainsString( '.work-piece--extra,', $css );
		$this->assertStringContainsString( '--glimpse-h: min(216px, calc((100cqi - 32px) / 2.4))', $css );
	}

	public function test_each_card_title_shares_its_view_transition_name_with_its_archive_title(): void {
		$card  = $this->file( 'blocks/selected-work/style.css' );
		$page  = $this->file( 'assets/css/page.css' );
		$pairs = array(
			'documents'   => 'post-type-archive-document',
			'videos'      => 'post-type-archive-video',
			'collaterals' => 'post-type-archive-collateral',
		);

		foreach ( $pairs as $key => $body_class ) {
			$name = 'work-title-' . $key;
			$this->assertStringContainsString( ".work-card--{$key} .work-card__title {\n\tview-transition-name: {$name};", $card );
			$this->assertStringContainsString( "body.{$body_class} .documents-page__title {\n\tview-transition-name: {$name};", $page );
		}
	}

	public function test_the_archives_follow_the_navigation_with_a_view_transition(): void {
		$page = $this->file( 'assets/css/page.css' );

		$this->assertStringContainsString( "@view-transition {\n\tnavigation: auto;\n}", $page );
		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\) \{.*view-transition-group\(work-title-documents\)/s', $page );
	}

	public function test_a_voucher_and_a_coupon_share_one_column_and_nothing_is_cut_by_the_column_cap(): void {
		$pieces = array(
			array( 'ratio' => 0.8 ),
			array( 'ratio' => 0.6 ),
			array( 'ratio' => 0.7143 ),
			array( 'ratio' => 2.5 ),
			array( 'ratio' => 2.0 ),
			array( 'ratio' => 1.0 ),
		);

		$columns = devportfolio_work_piece_columns( $pieces );

		$this->assertSame( array( 1, 1, 1, 2, 1 ), array_map( 'count', $columns ) );
		$this->assertSame( 2.5, $columns[3][0]['ratio'] );
		$this->assertSame( 2.0, $columns[3][1]['ratio'] );
	}

	public function test_a_third_strip_starts_a_new_column_and_there_are_at_most_five_columns(): void {
		$strips = devportfolio_work_piece_columns( array( array( 'ratio' => 2.5 ), array( 'ratio' => 2.0 ), array( 'ratio' => 2.0 ) ) );
		$this->assertSame( array( 2, 1 ), array_map( 'count', $strips ) );

		$many = devportfolio_work_piece_columns( array_fill( 0, 8, array( 'ratio' => 0.7 ) ) );
		$this->assertCount( 5, $many );

		$this->assertSame( array(), devportfolio_work_piece_columns( array() ) );
	}

	public function test_the_glimpse_geometry_follows_the_story(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );

		$this->assertStringContainsString( '--glimpse-h: 176px', $this->rule( $css, '.work-glimpse' ) );
		$this->assertStringContainsString( 'gap: 16px', $this->rule( $css, '.work-glimpse' ) );
		$this->assertStringContainsString( 'overflow: hidden', $this->rule( $css, '.work-glimpse' ) );
		$this->assertStringContainsString( '--glimpse-h: min(216px, calc((100cqi - 64px) / 3.72))', $this->rule( $css, '.work-glimpse' ) );
		$this->assertStringContainsString( 'justify-content: flex-end', $this->rule( $css, '.work-glimpse' ) );
		$this->assertStringContainsString( '--glimpse-h: 84px', $css );

		$piece = $this->rule( $css, '.work-piece' );
		$this->assertStringContainsString( '1.5px solid', $piece );
		$this->assertStringContainsString( 'border-radius: 4px', $piece );
		$this->assertStringContainsString( 'box-shadow: 4px 4px 0', $piece );

		$this->assertStringContainsString( 'margin-bottom: 6px', $this->rule( $css, '.work-glimpse__col:nth-child(even)' ) );
		$this->assertStringContainsString( 'translate: 0 -8px', $this->rule( $css, '.work-glimpse__col:hover' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-010: the card surface and the breakpoints.
	// ---------------------------------------------------------------------------------------------

	public function test_the_card_surface_follows_the_story(): void {
		$card = $this->rule( $this->file( 'blocks/selected-work/style.css' ), '.work-card' );

		$this->assertStringContainsString( 'height: 300px', $card );
		$this->assertStringContainsString( 'padding: 30px', $card );
		$this->assertStringContainsString( 'border: 1.5px solid var(--wp--preset--color--contrast)', $card );
		$this->assertStringContainsString( 'border-radius: 14px', $card );
		$this->assertStringContainsString( 'background: var(--wp--preset--color--fill)', $card );
	}

	public function test_the_layout_is_two_equal_columns_with_a_wide_third_card_then_one_column(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );

		$this->assertStringContainsString( 'grid-template-columns: repeat(2, minmax(0, 1fr))', $this->rule( $css, '.work' ) );
		$this->assertStringContainsString( 'gap: 20px', $this->rule( $css, '.work' ) );
		$this->assertStringContainsString( 'grid-column: 1 / -1', $this->rule( $css, '.work-card--collaterals' ) );
		$this->assertStringContainsString( '@media (max-width: 1000px)', $css );
		$this->assertMatchesRegularExpression( '/@media \(max-width: 620px\) \{\s*\.work-card \{[^}]*grid-template-columns: minmax\(0, 1fr\);[^}]*height: auto;/', $css );
	}

	public function test_reduced_motion_removes_every_transition_and_animation(): void {
		$css = $this->file( 'blocks/selected-work/style.css' );

		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\) \{[^@]*\.work-docs__item[^{]*\{\s*transition: none;/', $css );
		$this->assertStringContainsString( 'animation: none', $css );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-013, DPH-017: the script contract.
	// ---------------------------------------------------------------------------------------------

	public function test_the_script_runs_the_two_cards_apart_and_pauses_for_a_viewer(): void {
		$js       = $this->file( 'blocks/selected-work/view.js' );
		$rotation = $this->file( 'blocks/selected-work/rotation.js' );

		$this->assertStringContainsString( 'export const BOOK_PERIOD = 3500;', $rotation );
		$this->assertStringContainsString( 'export const FRAME_PERIOD = 4000;', $rotation );
		$this->assertStringContainsString( "dialog[open]", $js );
		$this->assertStringContainsString( "'pointerenter'", $js );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $js );
		$this->assertStringContainsString( 'saveData', $js );
		$this->assertStringContainsString( 'IntersectionObserver', $js );
		$this->assertStringContainsString( 'video.muted = true;', $js );
		$this->assertStringNotContainsString( 'controls', $js );
		$this->assertStringNotContainsString( 'localStorage', $js );
	}

	public function test_block_json_registers_the_view_script_module(): void {
		$json = json_decode( $this->file( 'blocks/selected-work/block.json' ), true );

		$this->assertSame( 'devportfolio/selected-work', $json['name'] );
		$this->assertSame( 'file:./view.js', $json['viewScriptModule'] );
		$this->assertSame( 'file:./style.css', $json['style'] );
	}

	// ---------------------------------------------------------------------------------------------
	// DPH-010, DPH-014, DPH-021: the Home template.
	// ---------------------------------------------------------------------------------------------

	public function test_the_template_places_the_heading_the_cards_and_the_three_viewers_after_what_i_do(): void {
		$tpl = $this->file( 'templates/front-page.html' );

		$needles = array(
			'<!-- wp:devportfolio/service-list /-->',
			'id="selected-work"',
			'>Selected work</h2>',
			'<!-- wp:devportfolio/selected-work /-->',
			'<!-- wp:devportfolio/document-modal /-->',
			'<!-- wp:devportfolio/collateral-modal /-->',
			'<!-- wp:devportfolio/video-modal /-->',
			'<!-- wp:template-part {"slug":"footer"',
		);

		$last = -1;
		foreach ( $needles as $needle ) {
			$pos = strpos( $tpl, $needle );
			$this->assertNotFalse( $pos, "{$needle} missing." );
			$this->assertGreaterThan( $last, $pos, "{$needle} is out of order." );
			$last = $pos;
		}

		$this->assertStringContainsString( 'font-weight:800">Selected work</h2>', $tpl );
		$this->assertSame( 1, substr_count( $tpl, 'wp:devportfolio/selected-work' ) );
	}

	public function test_the_three_viewers_render_with_the_picked_posts_ready_to_open(): void {
		$document   = $this->make_document( 'A book' );
		$collateral = $this->make_collateral( 'A piece' );
		$video      = $this->make_video( 'A clip' );
		devportfolio_memo_flush();

		$html = do_blocks( '<!-- wp:devportfolio/document-modal /--><!-- wp:devportfolio/collateral-modal /--><!-- wp:devportfolio/video-modal /-->' );

		$this->assertStringContainsString( 'documents-modal-root', $html );
		$this->assertStringContainsString( 'collaterals-modal-root', $html );
		$this->assertStringContainsString( 'videos-modal-root', $html );

		// Each viewer's payload knows the id the Home button carries.
		$xp   = $this->xpath( $html );
		$docs = json_decode( $xp->query( '//script[contains(@class,"documents-modal__data")]' )->item( 0 )->textContent, true );
		$cols = json_decode( $xp->query( '//script[contains(@class,"collaterals-modal__data")]' )->item( 0 )->textContent, true );
		$this->assertArrayHasKey( (string) $document, $docs );
		$this->assertArrayHasKey( (string) $collateral, $cols['items'] );
		$vids = json_decode( $xp->query( '//script[contains(@class,"videos-modal__data")]' )->item( 0 )->textContent, true );
		$this->assertArrayHasKey( (string) $video, $vids['items'] );
	}

	public function test_the_home_viewers_have_no_locked_pages_and_no_request_form(): void {
		$this->make_document( 'A book' );
		$this->make_collateral( 'A piece' );
		devportfolio_memo_flush();

		$html = do_blocks( '<!-- wp:devportfolio/document-modal /--><!-- wp:devportfolio/collateral-modal /-->' );

		$this->assertStringNotContainsString( 'Request full sample', $html );
		$this->assertDoesNotMatchRegularExpression( '/\\blocked\\b/i', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	public function test_a_button_inside_the_cards_is_never_nested_in_a_link(): void {
		$this->make_document( 'A' );
		$this->make_collateral( 'B' );

		$xp = $this->xpath( $this->render() );

		$this->assertSame( 0, $xp->query( '//a//button' )->length );
		$this->assertSame( 0, $xp->query( '//a//a' )->length );
	}
}
