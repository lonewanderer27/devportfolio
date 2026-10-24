<?php
/**
 * Videos seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group video
 * @group seeder
 */
class CPTDP_Test_Seeder_Videos extends CPTDP_Seeder_Test_Case {

	/**
	 * The test lib wipes terms after bootstrap while the seeded flag outlives
	 * them. Seed again for a known state.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( 'cptdp_aspect_ratio_seeded' );
		cptdp_seed_aspect_ratios();
	}

	protected function seeder_name(): string {
		return 'videos';
	}

	private function find( string $title ): WP_Post {
		$found = get_posts(
			array(
				'post_type'   => 'video',
				'post_status' => 'any',
				'title'       => $title,
			)
		);

		$this->assertCount( 1, $found, $title );

		return $found[0];
	}

	private function header(): string {
		return implode( ',', $this->seeder()['columns'] ) . "\n";
	}

	public function test_csv_has_ascending_order_vertical_ratio_and_matching_ids(): void {
		$previous = -1;

		foreach ( $this->csv_rows() as $row ) {
			$this->assertGreaterThan( $previous, (int) $row['order'], $row['title'] . ' order must ascend.' );
			$previous = (int) $row['order'];

			$this->assertSame( '9x16', $row['aspect_ratio'], $row['title'] );
			$this->assertStringStartsWith( 'https://', $row['embed_url'], $row['title'] );
			$this->assertStringStartsWith( 'https://', $row['post_url'], $row['title'] );

			$this->assertSame(
				basename( $row['embed_url'] ),
				basename( $row['post_url'] ),
				$row['title'] . ' embed and post URLs must point at the same video.'
			);
		}
	}

	public function test_csv_has_both_youtube_and_vimeo_rows(): void {
		$embeds = wp_list_pluck( $this->csv_rows(), 'embed_url' );

		$this->assertNotEmpty( preg_grep( '#^https://www\.youtube\.com/embed/#', $embeds ) );
		$this->assertNotEmpty( preg_grep( '#^https://player\.vimeo\.com/video/#', $embeds ) );
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$post = $this->find( $row['title'] );

			$this->assertSame( (int) $row['order'], (int) $post->menu_order, $row['title'] );
			$this->assertSame( $row['description'], $post->post_content, $row['title'] );
			$this->assertSame( $row['embed_url'], get_post_meta( $post->ID, 'cptdp_embed_url', true ), $row['title'] );
			$this->assertSame( $row['post_url'], get_post_meta( $post->ID, 'cptdp_post_url', true ), $row['title'] );
			$this->assertSame( (int) $row['length'], (int) get_post_meta( $post->ID, 'cptdp_length', true ), $row['title'] );
			$this->assertSame( '1' === $row['highlight'], (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ), $row['title'] );
			$this->assertSame( array( $row['aspect_ratio'] ), wp_get_object_terms( $post->ID, 'aspect_ratio', array( 'fields' => 'slugs' ) ), $row['title'] );
		}
	}

	public function test_listing_follows_csv_order(): void {
		$this->run_seeder();

		$this->assertSame(
			wp_list_pluck( $this->csv_rows(), 'title' ),
			wp_list_pluck( $this->items(), 'post_title' )
		);
	}

	public function test_existing_item_keeps_its_meta_and_terms(): void {
		$first = $this->csv_rows()[0]['title'];

		$id = self::factory()->post->create(
			array(
				'post_type'  => 'video',
				'post_title' => $first,
			)
		);
		update_post_meta( $id, 'cptdp_embed_url', 'https://www.youtube.com/embed/custom' );
		wp_set_object_terms( $id, array( '16x9' ), 'aspect_ratio' );

		$this->run_seeder();

		$this->assertSame( 'https://www.youtube.com/embed/custom', get_post_meta( $id, 'cptdp_embed_url', true ) );
		$this->assertSame( array( '16x9' ), wp_get_object_terms( $id, 'aspect_ratio', array( 'fields' => 'slugs' ) ) );
	}

	/**
	 * @dataProvider invalid_rows
	 */
	public function test_invalid_row_errors_before_writing( string $row ): void {
		$path = $this->temp_csv(
			$this->header()
			. "10,Good,https://www.youtube.com/embed/abc,https://www.youtube.com/shorts/abc,5,9x16,0,Ok\n"
			. $row . "\n"
		);

		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function invalid_rows(): array {
		return array(
			'non-numeric order'      => array( 'abc,Bad,https://www.youtube.com/embed/x,https://www.youtube.com/shorts/x,5,9x16,0,' ),
			'non-numeric length'     => array( '20,Bad,https://www.youtube.com/embed/x,https://www.youtube.com/shorts/x,long,9x16,0,' ),
			'highlight out of range' => array( '20,Bad,https://www.youtube.com/embed/x,https://www.youtube.com/shorts/x,5,9x16,2,' ),
			'http post url'          => array( '20,Bad,https://www.youtube.com/embed/x,http://www.youtube.com/shorts/x,5,9x16,0,' ),
			'unsupported embed host' => array( '20,Bad,https://example.com/embed/x,https://www.youtube.com/shorts/x,5,9x16,0,' ),
			'unknown aspect ratio'   => array( '20,Bad,https://www.youtube.com/embed/x,https://www.youtube.com/shorts/x,5,2x1,0,' ),
		);
	}

	public function test_missing_term_errors_before_writing(): void {
		$term = get_term_by( 'slug', '9x16', 'aspect_ratio' );
		wp_delete_term( $term->term_id, 'aspect_ratio' );

		$result = $this->run_seeder();

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_term_missing', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
		$this->assertNull( term_exists( '9x16', 'aspect_ratio' ), 'The term must not be recreated.' );
	}
}
