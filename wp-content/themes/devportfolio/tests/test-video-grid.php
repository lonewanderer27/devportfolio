<?php
/**
 * Tests for the Videos grid, its thumbnails and its hover preview (DPV-006, DPV-007, DPV-008).
 *
 * Markup is checked with DOMDocument. The CSS and scripts are checked as text, since there is no browser runner.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Grid extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		// The test library restores hooks after every test: re-add the memo's clearing hooks (a no-op when present).
		if ( function_exists( 'devportfolio_memo_add_hooks' ) ) {
			devportfolio_memo_add_hooks();
		}

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/video-grid' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Term ID of a term, created when missing.
	 */
	private function term( string $name, string $taxonomy ): int {
		$found = term_exists( $name, $taxonomy );
		if ( $found ) {
			return (int) ( is_array( $found ) ? $found['term_id'] : $found );
		}
		return (int) wp_insert_term( $name, $taxonomy )['term_id'];
	}

	/**
	 * A video attachment (or any attachment) of the given mime type.
	 */
	private function attachment( string $mime = 'video/mp4' ): int {
		return (int) self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'file'           => 'sample.mp4',
			)
		);
	}

	/**
	 * Create a video.
	 *
	 * @param array $args {
	 *     title, status, order, category, poster (bool), file (bool|mime), clip (bool|mime), embed (url).
	 * }
	 * @return int Post ID.
	 */
	private function make_video( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'video',
				'post_title'   => $args['title'] ?? 'Sunset Reel',
				'post_status'  => $args['status'] ?? 'publish',
				'menu_order'   => $args['order'] ?? 0,
				'post_content' => '',
			)
		);

		if ( isset( $args['category'] ) ) {
			wp_set_object_terms( $post_id, array( $this->term( $args['category'], 'video_category' ) ), 'video_category' );
		}
		if ( ! empty( $args['poster'] ) ) {
			$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $post_id );
			set_post_thumbnail( $post_id, $image );
		}
		if ( ! empty( $args['file'] ) ) {
			update_post_meta( $post_id, 'cptdp_video_file', $this->attachment( is_string( $args['file'] ) ? $args['file'] : 'video/mp4' ) );
		}
		if ( ! empty( $args['clip'] ) ) {
			update_post_meta( $post_id, 'cptdp_video_preview', $this->attachment( is_string( $args['clip'] ) ? $args['clip'] : 'video/mp4' ) );
		}
		if ( isset( $args['embed'] ) ) {
			update_post_meta( $post_id, 'cptdp_embed_url', $args['embed'] );
		}

		return $post_id;
	}

	private function render_grid(): string {
		return trim( do_blocks( '<!-- wp:devportfolio/video-grid /-->' ) );
	}

	private function xpath( string $html ): DOMXPath {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/video-grid/style.css' );
	}

	private function js( string $file ): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/video-grid/' . $file );
	}

	// ---------------------------------------------------------------------------------------------
	// DPV-008 and DPV-006: order, markup, poster, categories, template.
	// ---------------------------------------------------------------------------------------------

	public function test_nothing_is_rendered_without_published_videos(): void {
		$this->make_video( array( 'status' => 'draft' ) );

		$this->assertSame( '', $this->render_grid() );
	}

	public function test_items_follow_menu_order_then_title_and_drafts_are_excluded(): void {
		$bravo = $this->make_video( array( 'title' => 'Bravo', 'order' => 0 ) );
		$alpha = $this->make_video( array( 'title' => 'Alpha', 'order' => 0 ) );
		$zed   = $this->make_video( array( 'title' => 'Zed', 'order' => -1 ) );
		$this->make_video( array( 'title' => 'Draft', 'status' => 'draft', 'order' => -5 ) );

		$xpath = $this->xpath( $this->render_grid() );
		$ids   = array();
		foreach ( $xpath->query( '//button[@data-video-id]' ) as $button ) {
			$ids[] = (int) $button->getAttribute( 'data-video-id' );
		}

		$this->assertSame( array( $zed, $alpha, $bravo ), $ids );
	}

	public function test_changing_menu_order_changes_the_position(): void {
		$a = $this->make_video( array( 'title' => 'Alpha', 'order' => 0 ) );
		$b = $this->make_video( array( 'title' => 'Bravo', 'order' => 0 ) );

		$first = $this->xpath( $this->render_grid() )->query( '//button[@data-video-id][1]' )->item( 0 );
		$this->assertSame( (string) $a, $first->getAttribute( 'data-video-id' ) );

		// The hook on post save must clear the memo; the test does not flush it.
		wp_update_post(
			array(
				'ID'         => $b,
				'menu_order' => -9,
			)
		);

		$first = $this->xpath( $this->render_grid() )->query( '//button[@data-video-id][1]' )->item( 0 );
		$this->assertSame( (string) $b, $first->getAttribute( 'data-video-id' ) );
	}

	public function test_item_has_only_a_poster_and_a_labelled_button(): void {
		$id = $this->make_video( array( 'title' => 'Sunset Reel' ) );

		$xpath = $this->xpath( $this->render_grid() );

		$this->assertSame( 1, $xpath->query( "//div[@data-videos-grid]/article[contains(concat(' ', normalize-space(@class), ' '), ' videos-item ')]" )->length );
		$buttons = $xpath->query( '//button[@data-video-id]' );
		$this->assertSame( 1, $buttons->length );
		$this->assertSame( (string) $id, $buttons->item( 0 )->getAttribute( 'data-video-id' ) );
		$this->assertSame( 'Sunset Reel', $buttons->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertSame( 0, $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6|//p' )->length );
		$this->assertSame( '', trim( $xpath->query( '//article' )->item( 0 )->textContent ) );
	}

	public function test_title_is_escaped_in_the_label(): void {
		$id    = $this->make_video( array( 'title' => 'Fish & <b>Chips</b>' ) );
		$title = html_entity_decode( get_post_field( 'post_title', $id ), ENT_QUOTES, 'UTF-8' );
		$html  = $this->render_grid();

		$button = $this->xpath( $html )->query( '//button[@data-video-id]' )->item( 0 );
		$this->assertSame( $title, $button->getAttribute( 'aria-label' ) );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_poster_renders_an_image_only_when_there_is_one(): void {
		$this->make_video( array( 'title' => 'With poster', 'poster' => true, 'order' => 0 ) );
		$this->make_video( array( 'title' => 'No poster', 'order' => 1 ) );

		$xpath = $this->xpath( $this->render_grid() );
		$imgs  = $xpath->query( '//img' );

		$this->assertSame( 1, $imgs->length );
		$this->assertSame( 1, $xpath->query( "//img[contains(@class, 'videos-item__img')][@alt='']" )->length );
	}

	public function test_categories_are_slugs(): void {
		$this->make_video( array( 'title' => 'Tagged', 'category' => 'Short Form', 'order' => 0 ) );
		$this->make_video( array( 'title' => 'Untagged', 'order' => 1 ) );

		$articles = $this->xpath( $this->render_grid() )->query( '//article' );

		$this->assertSame( 'short-form', $articles->item( 0 )->getAttribute( 'data-video-categories' ) );
		$this->assertSame( '', $articles->item( 1 )->getAttribute( 'data-video-categories' ) );
	}

	public function test_template_places_the_grid_after_the_filter(): void {
		$template = (string) file_get_contents( $this->theme_dir() . '/templates/archive-video.html' );

		$categories = strpos( $template, 'wp:devportfolio/document-categories' );
		$grid       = strpos( $template, 'wp:devportfolio/video-grid' );
		$main_end   = strpos( $template, '</main>' );

		$this->assertNotFalse( $categories );
		$this->assertNotFalse( $grid );
		$this->assertNotFalse( $main_end );
		$this->assertGreaterThan( $categories, $grid );
		$this->assertLessThan( $main_end, $grid );
	}

	public function test_each_tile_has_its_own_view_transition_name(): void {
		$first  = $this->make_video( array( 'title' => 'Alpha', 'order' => 0 ) );
		$second = $this->make_video( array( 'title' => 'Bravo', 'order' => 1 ) );

		$articles = $this->xpath( $this->render_grid() )->query( '//article' );

		$this->assertSame( 'view-transition-name:videos-item-' . $first, $this->style_of( $articles->item( 0 ) ) );
		$this->assertSame( 'view-transition-name:videos-item-' . $second, $this->style_of( $articles->item( 1 ) ) );

		$css = $this->css();
		$this->assertStringContainsString( 'view-transition-class: videos-item;', $css );
		$this->assertStringContainsString( '::view-transition-group(*.videos-item)', $css );
		$this->assertStringContainsString( '::view-transition-old(*.videos-item)', $css );
		$this->assertStringContainsString( '::view-transition-new(*.videos-item)', $css );
	}

	private function style_of( DOMElement $element ): string {
		return $element->getAttribute( 'style' );
	}

	public function test_css_contract(): void {
		$css = $this->css();

		$this->assertStringContainsString( 'repeat(auto-fill, minmax(clamp(104px, 72px + 8vw, 180px), 1fr))', $css );
		$this->assertStringNotContainsString( 'auto-fit', $css );
		$this->assertStringContainsString( '--videos-gap: 6px', $css );
		$this->assertMatchesRegularExpression( '/@media \(max-width: 620px\) \{\s*\.videos-grid \{\s*--videos-gap: 4px;/', $css );
		$this->assertStringContainsString( 'aspect-ratio: 9 / 16', $css );
		$this->assertStringContainsString( 'border: 1.5px solid', $css );
		$this->assertStringContainsString( 'border-radius: 4px', $css );
		$this->assertStringContainsString( 'object-fit: cover', $css );
		$this->assertStringContainsString( 'height: 3px', $css );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $css );
		$this->assertStringNotContainsString( '.videos-item:hover', $css );
	}

	// ---------------------------------------------------------------------------------------------
	// DPV-007: hover preview source and the script contract.
	// ---------------------------------------------------------------------------------------------

	public function test_preview_source(): void {
		$file = $this->attachment( 'video/mp4' );
		$clip = $this->attachment( 'video/mp4' );
		$jpeg = $this->attachment( 'image/jpeg' );

		$this->assertSame(
			array( 'url' => wp_get_attachment_url( $file ), 'clip' => false ),
			devportfolio_video_preview_url( 0, $file )
		);
		$this->assertSame(
			array( 'url' => wp_get_attachment_url( $clip ), 'clip' => true ),
			devportfolio_video_preview_url( $clip, $file )
		);
		$this->assertSame(
			array( 'url' => wp_get_attachment_url( $file ), 'clip' => false ),
			devportfolio_video_preview_url( $jpeg, $file )
		);
		$this->assertSame(
			array( 'url' => '', 'clip' => false ),
			devportfolio_video_preview_url( $jpeg, 0 )
		);
		$this->assertSame(
			array( 'url' => '', 'clip' => false ),
			devportfolio_video_preview_url( 0, 0 )
		);
	}

	public function test_preview_source_in_the_markup(): void {
		$file_only = $this->make_video( array( 'title' => 'File only', 'file' => true, 'order' => 0 ) );
		$both      = $this->make_video( array( 'title' => 'File and clip', 'file' => true, 'clip' => true, 'order' => 1 ) );
		$embed     = $this->make_video( array( 'title' => 'Embed only', 'embed' => 'https://www.youtube.com/watch?v=abc', 'order' => 2 ) );

		$xpath    = $this->xpath( $this->render_grid() );
		$articles = $xpath->query( '//article' );

		// File only: the uploaded file, not a clip.
		$this->assertSame( wp_get_attachment_url( get_post_meta( $file_only, 'cptdp_video_file', true ) ), $articles->item( 0 )->getAttribute( 'data-preview-src' ) );
		$this->assertFalse( $articles->item( 0 )->hasAttribute( 'data-preview-clip' ) );

		// File and clip: the clip wins.
		$this->assertSame( wp_get_attachment_url( get_post_meta( $both, 'cptdp_video_preview', true ) ), $articles->item( 1 )->getAttribute( 'data-preview-src' ) );
		$this->assertTrue( $articles->item( 1 )->hasAttribute( 'data-preview-clip' ) );

		// Embed only: never previews.
		$this->assertFalse( $articles->item( 2 )->hasAttribute( 'data-preview-src' ) );
	}

	public function test_preview_source_ignores_a_clip_that_is_not_video(): void {
		$jpeg_clip = $this->make_video( array( 'title' => 'Image clip', 'file' => true, 'clip' => 'image/jpeg' ) );

		$article = $this->xpath( $this->render_grid() )->query( '//article' )->item( 0 );
		$this->assertSame( wp_get_attachment_url( get_post_meta( $jpeg_clip, 'cptdp_video_file', true ) ), $article->getAttribute( 'data-preview-src' ) );
		$this->assertFalse( $article->hasAttribute( 'data-preview-clip' ) );
	}

	public function test_view_script_contract(): void {
		$view = $this->js( 'view.js' );

		$this->assertStringContainsString( 'devportfolio:category-change', $view );
		$this->assertStringContainsString( './grid.js', $view );
		$this->assertStringContainsString( './preview.js', $view );
		// Checked as a call: the header comment mentions the word.
		$this->assertStringNotContainsString( 'requestAnimationFrame(', $view );
	}

	public function test_preview_script_contract(): void {
		$preview = $this->js( 'preview.js' );

		$this->assertStringContainsString( 'pointerenter', $preview );
		$this->assertStringContainsString( 'pointerleave', $preview );
		// Checked as a quoted event name: the comments mention keyboard focus.
		$this->assertStringNotContainsString( "'focus", $preview );
		$this->assertStringNotContainsString( "'blur'", $preview );
		$this->assertStringContainsString( "'is-videos-previewing'", $preview );
	}

	public function test_page_veil_contract(): void {
		$css = $this->css();

		// Lo-fi B2: the rest of the page blurs about 7px under a 40% white veil that does not take the pointer.
		$this->assertMatchesRegularExpression( '/body::after \{[^}]*pointer-events: none;[^}]*rgba\(255, 255, 255, 0\.4\)/s', $css );
		$this->assertMatchesRegularExpression( '/body\.is-videos-previewing::after,\s*body\.is-videos-popout::after \{[^}]*backdrop-filter: blur\(7px\)/s', $css );
		$this->assertMatchesRegularExpression( '/body::after \{[^}]*z-index: 30;/s', $css ); // above the header (20)
		$this->assertMatchesRegularExpression( '/body::after \{[^}]*backdrop-filter: blur\(0\)/s', $css ); // the blur itself fades
		$this->assertMatchesRegularExpression( '/body\.is-videos-popout::after \{[^}]*rgba\(255, 255, 255, 0\.45\)/s', $css );
		$this->assertStringContainsString( 'body.is-videos-instant::after', $css );
		$this->assertStringContainsString( 'opacity 200ms', $css );
	}

	public function test_outline_and_tile_layers(): void {
		$css = $this->css();

		$this->assertStringContainsString( ".videos-item.is-previewing,\n.videos-item.is-highlighted {\n\tz-index: 31;", $css );
		$this->assertStringContainsString( 'highlightOnly', $this->js( 'preview.js' ) ); // no preview file: highlight, keep the blur
		$this->assertMatchesRegularExpression( '/\.videos-outline \{[^}]*z-index: 32;/s', $css );
		$this->assertMatchesRegularExpression( '/\.videos-outline \{[^}]*border: 2\.5px solid/s', $css );
		$this->assertMatchesRegularExpression( '/\.videos-outline \{[^}]*pointer-events: none;/s', $css );
		$this->assertStringContainsString( '::view-transition-group(videos-outline)', $css );
		$this->assertMatchesRegularExpression( '/::view-transition-group\(videos-outline\) \{\s*animation-duration: 240ms;/', $css );
		$this->assertStringContainsString( 'html.is-morphing-outline .videos-outline', $css );
		$this->assertStringContainsString( 'transition: opacity 200ms linear, transform 220ms', $css );
		$this->assertMatchesRegularExpression( '/\.videos-grid \{[^}]*position: relative;/s', $css );
	}

	public function test_previous_video_fades_and_new_one_does_not(): void {
		$css = $this->css();

		// The new video has no fade-in: the video rule has no transition of its own.
		$this->assertMatchesRegularExpression( '/\.videos-item__video \{[^}]*\}/s', $css );
		$this->assertDoesNotMatchRegularExpression( '/\.videos-item__video \{[^}]*transition:/s', $css );
		$this->assertStringContainsString( ".videos-item.is-fading .videos-item__video,\n.videos-item.is-fading .videos-item__bar,\n.videos-item.is-fading .videos-item__mute {\n\ttransition: opacity 150ms ease-in;", $css );
		$this->assertStringContainsString( 'FADE_MS = 150', $this->js( 'preview.js' ) );
	}

	public function test_entrance_rises_once(): void {
		$css = $this->css();

		// As the Documents shelf: opacity and an 8px rise over 360ms, played once and not replayed by the filter.
		$this->assertMatchesRegularExpression( '/\.videos-item \{\s*animation: videos-item-rise 360ms cubic-bezier\(0\.2, 0\.8, 0\.2, 1\) backwards;/', $css );
		$this->assertMatchesRegularExpression( '/@keyframes videos-item-rise \{\s*from \{\s*opacity: 0;\s*transform: translateY\(8px\);/', $css );
		$this->assertMatchesRegularExpression( '/\.videos-grid\[data-entered\] \.videos-item \{\s*animation: none;/', $css );
		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\) \{.*?\.videos-item \{\s*animation: none;/s', $css );
		$this->assertStringContainsString( "'data-entered'", $this->js( 'view.js' ) );
	}

	public function test_skeleton_contract(): void {
		$css = $this->css();

		$this->assertMatchesRegularExpression( '/\.videos-item--poster:not\(\.is-loaded, \.is-failed\)::before \{[^}]*repeating-linear-gradient/s', $css );
		$this->assertMatchesRegularExpression( '/\.videos-item--poster:not\(\.is-loaded, \.is-failed\)::before \{[^}]*animation: videos-pulse 1\.4s ease-in-out infinite/s', $css );
		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\) \{.*?\.videos-item--poster:not\(\.is-loaded, \.is-failed\)::before \{\s*animation: none;/s', $css );

		$view = $this->js( 'view.js' );
		$this->assertStringContainsString( "'is-loaded'", $view );
		$this->assertStringContainsString( "'is-failed'", $view );

		$this->assertStringContainsString( "'loading'  => 'lazy'", (string) file_get_contents( $this->theme_dir() . '/blocks/video-grid/render.php' ) );
	}

	public function test_long_press_and_popout_contract(): void {
		$css   = $this->css();
		$press = $this->js( 'longpress.js' );
		$popup = $this->js( 'popout.js' );
		$view  = $this->js( 'view.js' );

		$this->assertStringContainsString( 'HOLD_MS = 450', $press );
		$this->assertStringContainsString( 'MOVE_TOLERANCE = 10', $press );
		$this->assertStringContainsString( 'contextmenu', $press );
		$this->assertStringContainsString( '{ passive: false }', $press );

		$this->assertStringContainsString( 'scale(0.93)', $css );
		$this->assertStringContainsString( 'animation: videos-ring 450ms linear forwards', $css );
		$this->assertStringContainsString( '-webkit-touch-callout: none', $css );
		$this->assertMatchesRegularExpression( '/\.videos-popout__card \{[^}]*70vw/s', $css );
		$this->assertStringContainsString( 'body.is-videos-popout::after', $css );

		$this->assertStringContainsString( 'aria-hidden', $popup );
		$this->assertStringContainsString( 'element.animate', $popup ); // the card grows itself, no view transition

		$this->assertStringContainsString( 'attachLongPress(', $view );
		$this->assertStringContainsString( 'createPopout(', $view );
	}

	public function test_reduced_motion_contract(): void {
		$preview = $this->js( 'preview.js' );
		$popup   = $this->js( 'popout.js' );
		$morph   = (string) file_get_contents( $this->theme_dir() . '/blocks/shared/morph.js' );
		$css     = $this->css();

		// Reduced motion or data saver: no hover preview (canPreview), and the pop-out reads the same rule.
		$this->assertStringContainsString( 'env.reducedMotion', $preview );
		$this->assertStringContainsString( 'env.saveData', $preview );
		$this->assertStringContainsString( 'motionEnvironment(', $popup );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $morph );

		// The press shrink is off with reduced motion.
		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\) \{.*?\.videos-item\.is-pressing \{\s*transform: none;/s', $css );
	}

	public function test_grid_markup_has_outline_ring_hint_and_ratio(): void {
		$id = $this->make_video( array( 'title' => 'Wide', 'poster' => true ) );
		wp_set_object_terms( $id, array( $this->term( '16x9', 'aspect_ratio' ) ), 'aspect_ratio' );
		$this->make_video( array( 'title' => 'Plain', 'order' => 1 ) );

		$xpath = $this->xpath( $this->render_grid() );

		$this->assertSame( 1, $xpath->query( "//div[@data-videos-grid]/div[contains(concat(' ', normalize-space(@class), ' '), ' videos-outline ')][@aria-hidden='true']" )->length );

		$articles = $xpath->query( '//article' );
		$this->assertSame( 1, $xpath->query( "//article[contains(concat(' ', normalize-space(@class), ' '), ' videos-item--poster ')][@data-ratio='16x9']" )->length );
		// One ring per tile (two videos here), each with a track and a fill circle.
		$this->assertSame( 2, $xpath->query( "//span[contains(concat(' ', normalize-space(@class), ' '), ' videos-item__ring ')]/svg/circle[contains(@class, 'videos-item__ring-track')]" )->length );
		$this->assertSame( 2, $xpath->query( "//span[contains(concat(' ', normalize-space(@class), ' '), ' videos-item__ring ')]/svg/circle[contains(@class, 'videos-item__ring-fill')]" )->length );
		$this->assertSame( 'Release to close', $xpath->query( '//div[@data-videos-grid]' )->item( 0 )->getAttribute( 'data-hint' ) );

		// The tile without a poster or a ratio term gets neither the skeleton class nor data-ratio.
		$this->assertFalse( $articles->item( 1 )->hasAttribute( 'data-ratio' ) );
		$this->assertSame( 1, $xpath->query( "//article[contains(concat(' ', normalize-space(@class), ' '), ' videos-item--poster ')]" )->length );
	}

	public function test_page_css_is_loaded_on_the_video_archive(): void {
		add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_page_styles' );

		$this->go_to( get_post_type_archive_link( 'video' ) );
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'devportfolio-page', 'enqueued' ) );
	}
}
