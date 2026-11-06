<?php
/**
 * Documents seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document
 * @group seeder
 */
class CPTDP_Test_Seeder_Documents extends CPTDP_Seeder_Test_Case {

	/**
	 * Terms are wiped between tests; the documents seeder needs the categories
	 * and the software tools to exist.
	 */
	public function set_up(): void {
		parent::set_up();

		remove_action( 'added_post_meta', array( 'CPTDP_Page_Scheduler', 'on_file_meta' ), 10 );
		remove_action( 'updated_post_meta', array( 'CPTDP_Page_Scheduler', 'on_file_meta' ), 10 );
		$this->assertIsArray( cptdp_run_seeder( 'document-categories' ) );
		$this->assertIsArray( cptdp_run_seeder( 'software-tools' ) );
	}

	protected function seeder_name(): string {
		return 'documents';
	}

	private function find( string $title ): WP_Post {
		$found = get_posts(
			array(
				'post_type'   => 'document',
				'post_status' => 'any',
				'title'       => $title,
			)
		);

		$this->assertCount( 1, $found, $title );

		return $found[0];
	}

	public function test_csv_has_ascending_order(): void {
		$previous = -1;

		foreach ( $this->csv_rows() as $row ) {
			$this->assertGreaterThan( $previous, (int) $row['order'], $row['title'] );
			$previous = (int) $row['order'];
		}
	}

	public function test_csv_meets_the_shelf_acceptance_data(): void {
		$rows = $this->csv_rows();

		$this->assertGreaterThanOrEqual( 8, count( $rows ) );

		$seeder = cptdp_get_seeders()['document-categories'];
		$known  = wp_list_pluck( cptdp_seed_read_csv( $seeder['csv'], $seeder['columns'] ), 'slug' );
		$this->assertEqualSets( $known, array_unique( wp_list_pluck( $rows, 'category' ) ), 'Every category needs a document.' );

		$tag_counts = array_map(
			static function ( $row ) {
				return count( array_filter( explode( '|', $row['software_tools'] ) ) );
			},
			$rows
		);
		$this->assertGreaterThan( 2, max( $tag_counts ), 'One document needs more than 2 tags.' );
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$post = $this->find( $row['title'] );

			$this->assertSame( (int) $row['order'], (int) $post->menu_order, $row['title'] );
			$this->assertSame( $row['description'], $post->post_excerpt, $row['title'] );
			$this->assertSame( $row['filetype'], get_post_meta( $post->ID, 'cptdp_filetype', true ), $row['title'] );
			$this->assertSame( (int) $row['total_pages'], (int) get_post_meta( $post->ID, 'cptdp_total_pages', true ), $row['title'] );
			$this->assertSame( $row['author'], get_post_meta( $post->ID, 'cptdp_author', true ), $row['title'] );
			$this->assertSame( array( $row['category'] ), wp_get_object_terms( $post->ID, 'document_category', array( 'fields' => 'slugs' ) ), $row['title'] );
			$file = get_post_meta( $post->ID, 'cptdp_document_file', true );
			if ( '' === $row['file'] ) {
				$this->assertEmpty( $file, $row['title'] );
			} else {
				$this->assertNotEmpty( $file, $row['title'] );
			}

			$names = array_map(
				static function ( $id ) {
					return get_the_title( $id );
				},
				(array) get_post_meta( $post->ID, 'cptdp_software_tools', true )
			);
			$this->assertSame( array_values( array_filter( explode( '|', $row['software_tools'] ) ) ), $names, $row['title'] );
		}
	}

	public function test_unknown_software_tool_errors_and_writes_nothing(): void {
		$csv = $this->temp_csv(
			implode( ',', $this->seeder()['columns'] ) . "\n" .
			"10,A Doc,Desc,reports,No Such Tool,PDF,3,Dave,\n"
		);

		$this->assertWPError( $this->run_seeder( false, $csv ) );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_unknown_category_errors_and_writes_nothing(): void {
		$csv = $this->temp_csv(
			implode( ',', $this->seeder()['columns'] ) . "\n" .
			"10,A Doc,Desc,no-such-category,,PDF,3,Dave,\n"
		);

		$this->assertWPError( $this->run_seeder( false, $csv ) );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_csv_has_a_file_column_and_every_listed_file_exists(): void {
		$this->assertContains( 'file', $this->seeder()['columns'] );
		foreach ( $this->csv_rows() as $row ) {
			if ( '' !== $row['file'] ) {
				$this->assertFileExists( cptdp_seed_documents_files_dir() . $row['file'] );
			}
		}
	}

	public function test_every_new_kind_is_covered(): void {
		$extensions = array();
		foreach ( $this->csv_rows() as $row ) {
			if ( '' !== $row['file'] ) {
				$extensions[] = strtolower( pathinfo( $row['file'], PATHINFO_EXTENSION ) );
			}
		}
		$this->assertEqualSets( array( 'pdf', 'docx', 'doc', 'odt', 'pptx', 'ppt', 'odp', 'xlsx', 'xls', 'ods' ), array_unique( $extensions ) );
		$this->assertContains( '', wp_list_pluck( $this->csv_rows(), 'file' ) );
	}

	public function test_dry_run_attaches_nothing(): void {
		$before = count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) ) );
		$this->run_seeder( true );
		$this->assertSame( 0, $this->count_items() );
		$this->assertSame( $before, count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) ) ) );
	}

	/**
	 * @dataProvider bad_file_values
	 */
	public function test_bad_file_values_are_rejected_before_any_write( string $file ): void {
		$csv = $this->temp_csv( implode( ',', $this->seeder()['columns'] ) . "\n10,A Doc,Desc,reports,,PDF,1,Dave," . $file . "\n" );
		$this->assertWPError( $this->run_seeder( false, $csv ) );
		$this->assertSame( 0, $this->count_items() );
	}

	public function bad_file_values(): array {
		return array( array( '../x.pdf' ), array( '/etc/passwd.pdf' ), array( 'a b.pdf' ), array( 'evil.exe' ), array( 'missing.pdf' ) );
	}

	public function test_second_run_does_not_attach_twice(): void {
		$this->run_seeder();
		$this->run_seeder();
		$expected = count( array_filter( wp_list_pluck( $this->csv_rows(), 'file' ) ) );
		$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
		$this->assertCount( $expected, $attachments );
	}
}
