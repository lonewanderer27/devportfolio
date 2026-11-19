<?php
/**
 * Video autofill tests (DPV-041).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group video
 * @group autofill
 */
class CPTDP_Test_Video_Autofill extends CPTDP_Test_Case {

	const YT    = 'KlE9YewV7SU';
	const VIMEO = '674533939';

	/** @var string[] Requested URLs. */
	private array $seen = array();

	/** @var WP_REST_Server */
	private $server;

	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'imagejpeg' ) ) {
			$this->markTestSkipped( 'GD is missing.' );
		}

		$this->seen = array();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->acting_as( 'editor' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'cptdp_video_autofill' );
		remove_all_filters( 'cptdp_video_warn_bytes' );

		parent::tear_down();
	}

	private function jpeg(): string {
		$img = imagecreatetruecolor( 40, 70 );
		ob_start();
		imagejpeg( $img );

		return (string) ob_get_clean();
	}

	/**
	 * Mock HTTP. Routes: substring => 'image' | 'fail' | array (oEmbed JSON).
	 *
	 * @param array<string,mixed> $routes Routes.
	 */
	private function http( array $routes ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $routes ) {
				$this->seen[] = $url;

				foreach ( $routes as $needle => $answer ) {
					if ( false === strpos( $url, $needle ) ) {
						continue;
					}

					if ( 'image' === $answer ) {
						file_put_contents( $args['filename'], $this->jpeg() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
						return array(
							'response' => array( 'code' => 200 ),
							'headers'  => array(),
							'body'     => '',
							'filename' => $args['filename'],
						);
					}

					if ( is_array( $answer ) ) {
						return array(
							'response' => array( 'code' => 200 ),
							'headers'  => array( 'content-type' => 'application/json' ),
							'body'     => wp_json_encode( $answer ),
						);
					}

					return new WP_Error( 'http_request_failed', 'down' );
				}

				return new WP_Error( 'http_request_failed', 'no network in tests' );
			},
			10,
			3
		);
	}

	private function video( array $meta = array() ): int {
		return self::factory()->post->create( array( 'post_type' => 'video', 'post_status' => 'publish', 'meta_input' => $meta ) );
	}

	private function save_form( int $id, array $fields ): void {
		$fields += array( 'cptdp_video_nonce' => wp_create_nonce( 'cptdp_video' ) );
		$this->submit( 'cptdp_video_save_meta', $id, $fields );
	}

	private function make_video_file( int $seconds, int $w = 0, int $h = 0 ): int {
		$id = $this->make_attachment( 'video/mp4' );
		wp_update_attachment_metadata( $id, array( 'length' => $seconds, 'width' => $w, 'height' => $h ) );

		return $id;
	}

	private function yt( string $path = 'embed/' ): string {
		return 'https://www.youtube.com/' . $path . self::YT;
	}

	private function vimeo_json( array $extra = array() ): array {
		return $extra + array(
			'thumbnail_url' => 'https://i.vimeocdn.com/video/1.jpg',
			'duration'      => 7,
		);
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function providers(): array {
		$yt = 'https://www.youtube.com/watch?v=' . self::YT;
		$vm = 'https://vimeo.com/' . self::VIMEO;

		return array(
			'yt embed'     => array( 'https://www.youtube.com/embed/' . self::YT, 'youtube', $yt ),
			'yt nocookie'  => array( 'https://www.youtube-nocookie.com/embed/' . self::YT, 'youtube', $yt ),
			'yt watch'     => array( 'https://www.youtube.com/watch?v=' . self::YT . '&t=5', 'youtube', $yt ),
			'yt shorts'    => array( 'https://www.youtube.com/shorts/' . self::YT, 'youtube', $yt ),
			'youtu.be'     => array( 'https://youtu.be/' . self::YT, 'youtube', $yt ),
			'vimeo player' => array( 'https://player.vimeo.com/video/' . self::VIMEO, 'vimeo', $vm ),
			'vimeo plain'  => array( 'https://vimeo.com/' . self::VIMEO, 'vimeo', $vm ),
			'vimeo chan'   => array( 'https://vimeo.com/channels/staff/' . self::VIMEO, 'vimeo', $vm ),
			'other'        => array( 'https://example.com/embed', '', '' ),
			'js'           => array( 'javascript:alert(1)', '', '' ),
			'short id'     => array( 'https://www.youtube.com/embed/short', '', '' ),
			'lookalike'    => array( 'https://notvimeo.com/123', '', '' ),
			'empty'        => array( '', '', '' ),
		);
	}

	/**
	 * @dataProvider providers
	 */
	public function test_provider( string $in, string $provider, string $url ): void {
		$out = cptdp_video_provider( $in );

		if ( '' === $provider ) {
			$this->assertSame( array(), $out );

			return;
		}

		$this->assertSame( $provider, $out['provider'] );
		$this->assertSame( $url, $out['url'] );
	}

	public function test_length_from_file(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 28 );

		$this->save_form( $id, array( 'cptdp_video_file' => $file ) );

		$this->assertSame( 28, (int) get_post_meta( $id, 'cptdp_length', true ) );
		$this->assertSame( 28, (int) get_post_meta( $id, 'cptdp_length_auto', true ) );
		$this->assertSame( 'file:' . $file, get_post_meta( $id, 'cptdp_length_source', true ) );
		$this->assertStringContainsString( '0:28', $this->capture( fn() => cptdp_video_column_content( 'cptdp_length', $id ) ) );
	}

	public function test_typed_length_is_kept_then_empty_is_filled(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 28 );

		$this->save_form( $id, array( 'cptdp_video_file' => $file, 'cptdp_length' => '90' ) );
		$this->assertSame( 90, (int) get_post_meta( $id, 'cptdp_length', true ) );
		$this->assertSame( '', (string) get_post_meta( $id, 'cptdp_length_auto', true ) );

		$this->save_form( $id, array( 'cptdp_video_file' => $file, 'cptdp_length' => '' ) );
		$this->assertSame( 28, (int) get_post_meta( $id, 'cptdp_length', true ) );
	}

	public function test_auto_length_follows_the_file_but_not_a_typed_one(): void {
		$id = $this->video();
		$a  = $this->make_video_file( 28 );
		$b  = $this->make_video_file( 40 );

		$this->save_form( $id, array( 'cptdp_video_file' => $a ) );
		$this->save_form( $id, array( 'cptdp_video_file' => $b, 'cptdp_length' => '28' ) );
		$this->assertSame( 40, (int) get_post_meta( $id, 'cptdp_length', true ) );

		$this->save_form( $id, array( 'cptdp_video_file' => $b, 'cptdp_length' => '55' ) );
		$c = $this->make_video_file( 12 );
		$this->save_form( $id, array( 'cptdp_video_file' => $c, 'cptdp_length' => '55' ) );
		$this->assertSame( 55, (int) get_post_meta( $id, 'cptdp_length', true ) );
	}

	public function test_removing_the_file_clears_auto_length_only(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 28 );

		$this->save_form( $id, array( 'cptdp_video_file' => $file ) );
		$this->save_form( $id, array( 'cptdp_length' => '28' ) );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_length', true ) );
		$this->assertSame( '', (string) get_post_meta( $id, 'cptdp_length_auto', true ) );

		$other = $this->video();
		$this->save_form( $other, array( 'cptdp_video_file' => $file, 'cptdp_length' => '90' ) );
		$this->save_form( $other, array( 'cptdp_length' => '90' ) );
		$this->assertSame( 90, (int) get_post_meta( $other, 'cptdp_length', true ) );
	}

	public function test_length_from_vimeo_not_youtube_and_file_wins(): void {
		$this->http( array( 'vimeo.com/api/oembed' => $this->vimeo_json(), 'vimeocdn' => 'image' ) );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://player.vimeo.com/video/' . self::VIMEO ) );
		$this->assertSame( 7, (int) get_post_meta( $id, 'cptdp_length', true ) );
		$this->assertStringContainsString( rawurlencode( 'https://vimeo.com/' . self::VIMEO ), $this->seen[0] );

		remove_all_filters( 'pre_http_request' );
		$this->http( array( 'ytimg' => 'image' ) );
		$yt = $this->video();
		$this->seen = array();
		$this->save_form( $yt, array( 'cptdp_embed_url' => $this->yt() ) );
		$this->assertSame( 0, (int) get_post_meta( $yt, 'cptdp_length', true ) );
		$this->assertFalse( get_transient( 'cptdp_video_autofill_' . get_current_user_id() . '_' . $yt ), 'no note for a YouTube length' );

		$both = $this->video();
		$this->seen = array();
		$this->save_form( $both, array( 'cptdp_video_file' => $this->make_video_file( 28 ), 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->assertSame( 28, (int) get_post_meta( $both, 'cptdp_length', true ) );
	}

	public function test_unreadable_file_length_leaves_note(): void {
		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_video_file' => $this->make_attachment( 'video/mp4' ) ) );

		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_length', true ) );
		$this->assertNotEmpty( get_transient( 'cptdp_video_autofill_' . get_current_user_id() . '_' . $id ) );
	}

	public function test_youtube_thumbnail_maxres_then_hq(): void {
		$this->http( array( 'hqdefault' => 'image' ) );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );

		$this->assertSame( 'generated', CPTDP_Generated_Image::owner( $id ) );
		$this->assertSame( 'embed:https://www.youtube.com/watch?v=' . self::YT, CPTDP_Generated_Image::source_of( $id ) );
		$this->assertSame(
			array( 'https://i.ytimg.com/vi/' . self::YT . '/maxresdefault.jpg', 'https://i.ytimg.com/vi/' . self::YT . '/hqdefault.jpg' ),
			$this->seen
		);
	}

	public function test_vimeo_thumbnail_and_non_https_refused(): void {
		$this->http( array( 'vimeo.com/api/oembed' => $this->vimeo_json(), 'vimeocdn' => 'image' ) );
		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->assertSame( 'generated', CPTDP_Generated_Image::owner( $id ) );

		remove_all_filters( 'pre_http_request' );
		$this->http( array( 'vimeo.com/api/oembed' => $this->vimeo_json( array( 'thumbnail_url' => 'http://i.vimeocdn.com/1.jpg' ) ) ) );
		$bad = $this->video();
		$this->save_form( $bad, array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->assertSame( '', CPTDP_Generated_Image::owner( $bad ) );
		$this->assertNotEmpty( get_transient( 'cptdp_video_autofill_' . get_current_user_id() . '_' . $bad ) );
	}

	public function test_an_editors_image_is_never_touched(): void {
		$this->http( array( 'ytimg' => 'image' ) );

		$id    = $this->video();
		$mine  = $this->make_attachment( 'image/png' );
		set_post_thumbnail( $id, $mine );

		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );
		$this->save_form( $id, array() );

		$this->assertSame( $mine, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( array(), $this->seen );
		$this->assertSame( 'attachment', get_post_type( $mine ) );
	}

	public function test_generated_image_lifecycle(): void {
		$this->http( array( 'ytimg' => 'image' ) );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );
		$first = (int) get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $first );

		// Embed changes: swapped, old one deleted.
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://www.youtube.com/embed/AAAAAAAAAAA' ) );
		$second = (int) get_post_thumbnail_id( $id );
		$this->assertNotSame( $first, $second );
		$this->assertNull( get_post( $first ) );

		// Removed in the form, same source: put back with no request.
		$this->seen = array();
		update_post_meta( $id, '_thumbnail_id', -1 );
		delete_post_thumbnail( $id );
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://www.youtube.com/embed/AAAAAAAAAAA' ) );
		$this->assertSame( $second, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( array(), $this->seen );

		// Source gone: ours is deleted.
		$this->save_form( $id, array() );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $id ) );
		$this->assertNull( get_post( $second ) );
	}

	public function test_deleting_ours_in_media_and_deleting_the_video(): void {
		$this->http( array( 'ytimg' => 'image' ) );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );
		$ours = (int) get_post_thumbnail_id( $id );

		wp_delete_attachment( $ours, true );
		$this->assertSame( '', (string) get_post_meta( $id, CPTDP_Generated_Image::META, true ) );

		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );
		$again = (int) get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $again );

		wp_delete_post( $id, true );
		$this->assertNull( get_post( $again ) );
	}

	public function test_filter_switches_the_server_side_off(): void {
		$this->http( array( 'vimeo.com/api/oembed' => $this->vimeo_json(), 'ytimg' => 'image' ) );
		add_filter( 'cptdp_video_autofill', '__return_false' );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->assertSame( array(), $this->seen );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_length', true ) );

		$file = $this->video();
		$this->save_form( $file, array( 'cptdp_video_file' => $this->make_video_file( 28 ) ) );
		$this->assertSame( 28, (int) get_post_meta( $file, 'cptdp_length', true ) );
	}

	public function test_nothing_runs_outside_the_admin_form(): void {
		$this->http( array( 'vimeo.com/api/oembed' => $this->vimeo_json(), 'vimeocdn' => 'image' ) );

		$id = wp_insert_post( array( 'post_type' => 'video', 'post_title' => 'x', 'post_status' => 'publish', 'meta_input' => array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) ) );
		update_post_meta( $id, 'cptdp_embed_url', 'https://vimeo.com/' . self::VIMEO );

		cptdp_run_seeder( 'video-categories' );
		cptdp_run_seeder( 'aspect-ratios' );
		cptdp_run_seeder( 'videos' );

		$req = new WP_REST_Request( 'POST', '/wp/v2/videos/' . $id );
		$req->set_body_params( array( 'meta' => array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) ) );
		$this->server->dispatch( $req );

		$this->assertSame( array(), $this->seen );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $id ) );

		// No nonce, or a user who cannot edit: nothing.
		$this->submit( 'cptdp_video_save_meta', $id, array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->acting_as( 'subscriber' );
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->assertSame( array(), $this->seen );
	}

	public function test_failure_note_prints_once(): void {
		$this->http( array() );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );
		$this->assertNotEmpty( get_transient( 'cptdp_video_autofill_' . get_current_user_id() . '_' . $id ) );

		set_current_screen( 'video' );
		get_current_screen()->base = 'post';
		$GLOBALS['post']           = get_post( $id );

		$first = $this->capture( 'cptdp_video_autofill_notice' );
		$this->assertStringContainsString( 'notice-warning', $first );
		$this->assertSame( '', $this->capture( 'cptdp_video_autofill_notice' ) );
	}

	/**
	 * @return array<string,array{0:int,1:int,2:string}>
	 */
	public static function ratios(): array {
		return array(
			'1080p'    => array( 1920, 1080, '16x9' ),
			'portrait' => array( 1080, 1920, '9x16' ),
			'square'   => array( 1080, 1080, '1x1' ),
			'4:3'      => array( 640, 480, '4x3' ),
			'odd'      => array( 2000, 500, '' ),
			'unknown'  => array( 0, 0, '' ),
		);
	}

	/**
	 * @dataProvider ratios
	 */
	public function test_ratio_slug( int $w, int $h, string $slug ): void {
		$this->assertSame( $slug, cptdp_video_ratio_slug( $w, $h ) );
	}

	private function ratio_of( int $id ): string {
		$t = wp_get_object_terms( $id, 'aspect_ratio', array( 'fields' => 'slugs' ) );

		return $t ? (string) $t[0] : '';
	}

	public function test_ratio_from_file_follows_it_but_not_a_chosen_one(): void {
		cptdp_run_seeder( 'aspect-ratios' );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_video_file' => $this->make_video_file( 5, 1080, 1920 ) ) );
		$this->assertSame( '9x16', $this->ratio_of( $id ) );

		// A new file changes what we set.
		$this->save_form( $id, array( 'cptdp_video_file' => $this->make_video_file( 5, 1080, 1080 ) ) );
		$this->assertSame( '1x1', $this->ratio_of( $id ) );

		// Dave picks another ratio: kept for later files.
		wp_set_object_terms( $id, '4x3', 'aspect_ratio' );
		$this->save_form( $id, array( 'cptdp_video_file' => $this->make_video_file( 5, 1920, 1080 ) ) );
		$this->assertSame( '4x3', $this->ratio_of( $id ) );

		// A ratio chosen before any autofill (not the default) is kept too.
		$other = $this->video();
		wp_set_object_terms( $other, '1x1', 'aspect_ratio' );
		$this->save_form( $other, array( 'cptdp_video_file' => $this->make_video_file( 5, 1080, 1920 ) ) );
		$this->assertSame( '1x1', $this->ratio_of( $other ) );
	}

	public function test_ratio_from_vimeo_and_shorts_link(): void {
		cptdp_run_seeder( 'aspect-ratios' );
		$this->http( array( 'vimeo.com/api/oembed' => $this->vimeo_json( array( 'width' => 720, 'height' => 1280 ) ), 'vimeocdn' => 'image', 'ytimg' => 'image' ) );

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => 'https://vimeo.com/' . self::VIMEO ) );
		$this->assertSame( '9x16', $this->ratio_of( $id ) );

		$yt = $this->video();
		$this->save_form( $yt, array( 'cptdp_embed_url' => $this->yt(), 'cptdp_post_url' => $this->yt( 'shorts/' ) ) );
		$this->assertSame( '9x16', $this->ratio_of( $yt ) );

		$plain = $this->video();
		$this->save_form( $plain, array( 'cptdp_embed_url' => $this->yt() ) );
		$this->assertNotSame( '9x16', $this->ratio_of( $plain ) );
	}

	private function preview( int $id, string $body, int $file, string $type = 'video/mp4' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/cptdp/v1/videos/' . $id . '/preview' );
		$request->set_header( 'Content-Type', $type );
		$request->set_query_params( array( 'file' => $file ) );
		$request->set_body( $body );

		return $this->server->dispatch( $request );
	}

	private function clip( string $ext = 'mp4' ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/preview.' . $ext );
	}

	public function test_preview_meta_is_registered_and_only_takes_a_video(): void {
		$keys = get_registered_meta_keys( 'post', 'video' );
		$this->assertArrayHasKey( 'cptdp_video_preview', $keys );
		$this->assertTrue( (bool) $keys['cptdp_video_preview']['show_in_rest'] );
		$this->assertSame( 0, cptdp_video_sanitize_file( $this->make_attachment( 'image/png' ) ) );
	}

	public function test_preview_route_guards(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->preview( $id, $this->clip(), $file )->get_status() );

		$this->acting_as( 'subscriber' );
		$this->assertSame( 403, $this->preview( $id, $this->clip(), $file )->get_status() );

		$this->acting_as( 'editor' );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertSame( 404, $this->preview( $page, $this->clip(), $file )->get_status() );
		$this->assertSame( 400, $this->preview( $id, $this->clip(), $this->make_attachment( 'image/png' ) )->get_status() );
		$this->assertSame( 415, $this->preview( $id, 'not a video at all', $file, 'text/plain' )->get_status() );

		add_filter( 'cptdp_video_preview_max_bytes', static fn() => 100 );
		$this->assertSame( 413, $this->preview( $id, $this->clip(), $file )->get_status() );
	}

	public function test_preview_route_stores_swaps_and_works_for_webm(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );

		$res = $this->preview( $id, $this->clip(), $file );
		$this->assertSame( 200, $res->get_status() );
		$first = (int) $res->get_data()['id'];
		$this->assertSame( $first, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertSame( $first, (int) get_post_meta( $id, 'cptdp_video_preview_generated', true ) );
		$this->assertSame( 'file:' . $file, cptdp_video_preview_source( $id ) );
		$this->assertSame( 'generated', cptdp_video_preview_owner( $id ) );

		$res = $this->preview( $id, $this->clip( 'webm' ), $file, 'video/webm' );
		$this->assertSame( 200, $res->get_status() );
		$second = (int) $res->get_data()['id'];
		$this->assertNotSame( $first, $second );
		$this->assertNull( get_post( $first ) );
		$this->assertSame( $second, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
	}

	public function test_a_preview_that_is_not_ours_is_kept(): void {
		$id    = $this->video();
		$file  = $this->make_video_file( 5 );
		$other = $this->make_video_file( 6 );
		update_post_meta( $id, 'cptdp_video_preview', $other );

		$before = (int) wp_count_posts( 'attachment' )->inherit;
		$data   = $this->preview( $id, $this->clip(), $file )->get_data();
		$this->assertTrue( $data['kept'] );
		$this->assertSame( $before, (int) wp_count_posts( 'attachment' )->inherit );

		// Saving the form without a file never removes it.
		$this->save_form( $id, array() );
		$this->assertSame( $other, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertNotNull( get_post( $other ) );
	}

	public function test_preview_follows_the_file_on_save(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );
		$this->save_form( $id, array( 'cptdp_video_file' => $file ) );

		$ours = (int) $this->preview( $id, $this->clip(), $file )->get_data()['id'];

		// A different file keeps the old clip until the browser's new one arrives (D16).
		$this->save_form( $id, array( 'cptdp_video_file' => $this->make_video_file( 7 ) ) );
		$this->assertSame( $ours, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );

		// No file: ours goes.
		$this->save_form( $id, array() );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertNull( get_post( $ours ) );
	}

	public function test_deleting_the_preview_in_media_or_the_video(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );

		$ours = (int) $this->preview( $id, $this->clip(), $file )->get_data()['id'];
		wp_delete_attachment( $ours, true );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_video_preview_generated', true ) );

		$again = (int) $this->preview( $id, $this->clip(), $file )->get_data()['id'];
		wp_delete_post( $id, true );
		$this->assertNull( get_post( $again ) );
	}

	public function test_nothing_makes_a_preview_outside_the_route(): void {
		cptdp_run_seeder( 'video-categories' );
		cptdp_run_seeder( 'aspect-ratios' );
		cptdp_run_seeder( 'videos' );
		$id = wp_insert_post( array( 'post_type' => 'video', 'post_title' => 'x', 'post_status' => 'publish' ) );
		$this->save_form( $id, array( 'cptdp_video_file' => $this->make_video_file( 5 ) ) );

		$ids = get_posts( array( 'post_type' => 'video', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any' ) );
		foreach ( $ids as $one ) {
			$this->assertSame( 0, (int) get_post_meta( $one, 'cptdp_video_preview', true ) );
		}
	}

	public function test_mediabunny_is_vendored_and_only_loaded_on_the_video_screen(): void {
		$dir = dirname( __DIR__, 2 ) . '/assets/vendor/mediabunny/';
		foreach ( array( 'mediabunny.min.js', 'LICENSE', 'README.md' ) as $name ) {
			$this->assertFileExists( $dir . $name );
		}

		global $wp_scripts;
		$wp_scripts = new WP_Scripts();
		set_current_screen( 'edit-page' );
		cptdp_video_enqueue_admin( 'post.php' );
		$this->assertFalse( wp_script_is( 'cptdp-mediabunny', 'enqueued' ) );

		set_current_screen( 'video' );
		cptdp_video_enqueue_admin( 'post.php' );
		$this->assertTrue( wp_script_is( 'cptdp-mediabunny', 'enqueued' ) );
	}

	/**
	 * A test image: $w x $h, colour content, black bars of the given sizes (left, right, top, bottom).
	 */
	private function barred( int $w, int $h, int $l, int $r, int $t, int $b ): string {
		$im = imagecreatetruecolor( $w, $h );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 0, 0, 0 ) );
		imagefilledrectangle( $im, $l, $t, $w - 1 - $r, $h - 1 - $b, imagecolorallocate( $im, 200, 120, 60 ) );
		$path = wp_tempnam( 'bars' );
		imagejpeg( $im, $path, 95 );

		return $path;
	}

	/**
	 * @return array<string,array{0:array<int>,1:array<int>|null}>
	 */
	public static function bars(): array {
		return array(
			'pillarbox'     => array( array( 320, 180, 100, 100, 0, 0 ), array( 120, 180 ) ),
			'letterbox'     => array( array( 320, 240, 0, 0, 40, 40 ), array( 320, 160 ) ),
			'both'          => array( array( 320, 240, 40, 40, 40, 40 ), array( 240, 160 ) ),
			'no bars'       => array( array( 320, 180, 0, 0, 0, 0 ), null ),
			'one side only' => array( array( 320, 180, 100, 0, 0, 0 ), null ),
			'thin bars'     => array( array( 320, 180, 3, 3, 0, 0 ), null ),
			'almost black'  => array( array( 320, 180, 150, 150, 0, 0 ), null ),
		);
	}

	/**
	 * @dataProvider bars
	 */
	public function test_black_bars_are_trimmed( array $args, ?array $size ): void {
		$path   = $this->barred( ...$args );
		$before = (string) file_get_contents( $path );

		$this->assertSame( null !== $size, cptdp_video_trim_bars( $path ) );

		$got = getimagesize( $path );

		if ( null === $size ) {
			$this->assertSame( $before, (string) file_get_contents( $path ), 'untouched' );
		} else {
			// 2px inset on each side of a cropped axis.
			$this->assertEqualsWithDelta( $size[0], $got[0], 6 );
			$this->assertEqualsWithDelta( $size[1], $got[1], 6 );
		}

		wp_delete_file( $path );
	}

	public function test_a_pillarboxed_embed_thumbnail_is_stored_cropped(): void {
		$bars = $this->barred( 320, 180, 100, 100, 0, 0 );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $bars ) {
				if ( false === strpos( $url, 'maxresdefault' ) ) {
					return new WP_Error( 'http_request_failed', 'no' );
				}
				copy( $bars, $args['filename'] );

				return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '', 'filename' => $args['filename'] );
			},
			10,
			3
		);

		$id = $this->video();
		$this->save_form( $id, array( 'cptdp_embed_url' => $this->yt() ) );

		$meta = wp_get_attachment_metadata( (int) get_post_thumbnail_id( $id ) );
		$this->assertLessThan( 140, (int) $meta['width'] );
		$this->assertSame( 180, (int) $meta['height'] );
	}

	/**
	 * A short as YouTube frames it: the picture in the middle, a dark blurred copy on both sides (no black).
	 */
	private function blurred_sides( int $w, int $h, int $picture ): string {
		$im = imagecreatetruecolor( $w, $h );
		$x0 = (int) ( ( $w - $picture ) / 2 );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$inside = $x >= $x0 && $x < $x0 + $picture;
				$v      = (int) ( 100 + 80 * sin( $x / 9 ) * cos( $y / 7 ) ); // Busy picture, lots of edges.
				$c      = $inside ? imagecolorallocate( $im, $v, 255 - $v, 120 ) : imagecolorallocate( $im, 38 + ( $x % 3 ), 30, 28 );
				imagesetpixel( $im, $x, $y, $c );
			}
		}

		$path = wp_tempnam( 'seam' );
		imagejpeg( $im, $path, 95 );

		return $path;
	}

	public function test_blurred_side_fill_is_cropped_to_the_picture(): void {
		$path = $this->blurred_sides( 640, 360, 203 );

		$this->assertTrue( cptdp_video_trim_bars( $path ) );

		$got = getimagesize( $path );
		$this->assertEqualsWithDelta( 203, $got[0], 4 );
		$this->assertSame( 360, $got[1] );
		wp_delete_file( $path );
	}

	public function test_a_busy_photo_without_seams_is_not_cropped(): void {
		$path = $this->blurred_sides( 640, 360, 640 );
		$this->assertFalse( cptdp_video_trim_bars( $path ) );
		wp_delete_file( $path );
	}

	public function test_a_frame_drawn_from_an_uploaded_video_is_trimmed_too(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );
		$path = $this->barred( 320, 180, 100, 100, 0, 0 );

		$res = $this->poster( $id, (string) file_get_contents( $path ), $file );
		$this->assertSame( 200, $res->get_status() );

		$meta = wp_get_attachment_metadata( (int) get_post_thumbnail_id( $id ) );
		$this->assertLessThan( 140, (int) $meta['width'] );
		$this->assertSame( 180, (int) $meta['height'] );
		wp_delete_file( $path );
	}

	public function test_big_file_note_and_server_limit_hint(): void {
		$this->assertSame( '', cptdp_video_big_note( 10 * MB_IN_BYTES ) );
		$this->assertStringContainsString( '210 MB', cptdp_video_big_note( 210 * MB_IN_BYTES ) );
		$this->assertStringContainsString( 'embed URL', cptdp_video_big_note( 210 * MB_IN_BYTES ) );

		add_filter( 'cptdp_video_warn_bytes', static fn() => 5 * MB_IN_BYTES );
		$this->assertNotSame( '', cptdp_video_big_note( 10 * MB_IN_BYTES ) );

		$id   = $this->video();
		$html = $this->capture( fn() => cptdp_video_render_details_box( get_post( $id ) ) );
		$this->assertStringContainsString( size_format( wp_max_upload_size() ), $html );
		$this->assertStringContainsString( 'cptdp-video-size-note', $html );
	}

	public function test_alias_keeps_documents_and_videos_on_one_key(): void {
		$this->assertSame( CPTDP_Generated_Image::META, CPTDP_Page_Featured::META );
		$this->assertSame( CPTDP_Generated_Image::META_FROM, CPTDP_Page_Featured::META_FROM );
	}

	private function poster( int $id, string $body, int $file, string $type = 'image/jpeg' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/cptdp/v1/videos/' . $id . '/poster' );
		$request->set_header( 'Content-Type', $type );
		$request->set_query_params( array( 'file' => $file ) );
		$request->set_body( $body );

		return $this->server->dispatch( $request );
	}

	public function test_poster_route_guards(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->poster( $id, $this->jpeg(), $file )->get_status() );

		$this->acting_as( 'subscriber' );
		$this->assertSame( 403, $this->poster( $id, $this->jpeg(), $file )->get_status() );

		$this->acting_as( 'editor' );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertSame( 404, $this->poster( $page, $this->jpeg(), $file )->get_status() );
		$this->assertSame( 400, $this->poster( $id, $this->jpeg(), $this->make_attachment( 'image/png' ) )->get_status() );
		$this->assertSame( 415, $this->poster( $id, 'not an image', $file, 'text/plain' )->get_status() );
		$this->assertSame( 413, $this->poster( $id, str_repeat( 'a', 2097153 ), $file )->get_status() );
	}

	public function test_poster_route_sets_swaps_and_keeps(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );

		$res = $this->poster( $id, $this->jpeg(), $file );
		$this->assertSame( 200, $res->get_status() );
		$first = (int) get_post_thumbnail_id( $id );
		$this->assertSame( $first, $res->get_data()['id'] );
		$this->assertSame( 'file:' . $file, CPTDP_Generated_Image::source_of( $id ) );

		$this->poster( $id, $this->jpeg(), $file );
		$this->assertNotSame( $first, (int) get_post_thumbnail_id( $id ) );
		$this->assertNull( get_post( $first ) );

		$mine = $this->make_attachment( 'image/png' );
		set_post_thumbnail( $id, $mine );
		$count = (int) wp_count_posts( 'attachment' )->inherit + (int) wp_count_posts( 'attachment' )->private;
		$data  = $this->poster( $id, $this->jpeg(), $file )->get_data();
		$this->assertTrue( $data['kept'] );
		$this->assertSame( $mine, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( $count, (int) wp_count_posts( 'attachment' )->inherit + (int) wp_count_posts( 'attachment' )->private );
	}

	public function test_file_frame_replaces_the_embed_fallback(): void {
		$this->http( array( 'ytimg' => 'image' ) );

		$id   = $this->video();
		$file = $this->make_video_file( 5 );
		$this->save_form( $id, array( 'cptdp_video_file' => $file, 'cptdp_embed_url' => $this->yt() ) );

		$embed = (int) get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $embed );

		$this->poster( $id, $this->jpeg(), $file );
		$this->assertNotSame( $embed, (int) get_post_thumbnail_id( $id ) );
		$this->assertNull( get_post( $embed ) );
		$this->assertSame( 'file:' . $file, CPTDP_Generated_Image::source_of( $id ) );
	}

	/** A readable attachment whose file is a real clip (or the given bytes): the manual preview field needs one. */
	private function picked_clip( string $body = '', string $ext = 'mp4' ): int {
		$id   = $this->make_attachment( 'video/mp4' );
		$path = wp_tempnam( 'clip.' . $ext );
		file_put_contents( $path, '' === $body ? $this->clip( $ext ) : $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		update_attached_file( $id, $path );

		return $id;
	}

	/** DPV-040 manual preview: a picked clip replaces the one the plugin made; the plugin's own removal path deletes that one only. */
	public function test_a_manual_clip_replaces_a_generated_one(): void {
		$id   = $this->video();
		$file = $this->make_video_file( 5 );
		$this->save_form( $id, array( 'cptdp_video_file' => $file ) );
		$ours = (int) $this->preview( $id, $this->clip(), $file )->get_data()['id'];

		$manual = $this->picked_clip();
		$this->save_form( $id, array( 'cptdp_video_file' => $file, 'cptdp_video_preview_pick' => (string) $manual ) );

		$this->assertSame( $manual, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertSame( 'editor', cptdp_video_preview_owner( $id ) );
		$this->assertNull( get_post( $ours ) );
	}

	public function test_a_manual_clip_is_never_deleted_by_the_system(): void {
		$id     = $this->video();
		$file   = $this->make_video_file( 5 );
		$manual = $this->picked_clip();
		$this->save_form( $id, array( 'cptdp_video_file' => $file, 'cptdp_video_preview_pick' => (string) $manual ) );

		// The video file is removed: the clip stays linked and in the Media library.
		$this->save_form( $id, array( 'cptdp_video_file' => 0, 'cptdp_video_preview_pick' => (string) $manual ) );

		$this->assertSame( $manual, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertNotNull( get_post( $manual ) );
	}

	public function test_clearing_the_field_unlinks_a_manual_clip_and_keeps_the_media_item(): void {
		$id     = $this->video();
		$manual = $this->picked_clip();
		$this->save_form( $id, array( 'cptdp_video_preview_pick' => (string) $manual ) );

		$this->save_form( $id, array( 'cptdp_video_preview_pick' => '0' ) );

		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
		$this->assertNotNull( get_post( $manual ) );
	}

	public function test_an_invalid_or_oversized_clip_is_ignored_and_a_webm_is_accepted(): void {
		$id   = $this->video();
		$text = $this->picked_clip( 'not a video' );
		$this->save_form( $id, array( 'cptdp_video_preview_pick' => (string) $text ) );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );

		$cap = static fn() => 10;
		add_filter( 'cptdp_video_preview_max_bytes', $cap );
		$big = $this->picked_clip();
		$this->save_form( $id, array( 'cptdp_video_preview_pick' => (string) $big ) );
		remove_filter( 'cptdp_video_preview_max_bytes', $cap );
		$this->assertSame( 0, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );

		$webm = $this->picked_clip( '', 'webm' );
		$this->save_form( $id, array( 'cptdp_video_preview_pick' => (string) $webm ) );
		$this->assertSame( $webm, (int) get_post_meta( $id, 'cptdp_video_preview', true ) );
	}
}

