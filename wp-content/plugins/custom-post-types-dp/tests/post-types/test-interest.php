<?php
/**
 * Interest post type and screens tests (DPB-017).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group post-types
 */
class CPTDP_Test_Interest extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();

		reset_phpmailer_instance();
		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		unset( $_GET['post'] );

		parent::tear_down();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function clean( array $override = array() ): array {
		return array_merge(
			array(
				'name'     => 'Jane Citizen',
				'email'    => 'jane@example.com',
				'ip'       => '198.51.100.9',
				'date'     => '2030-01-14',
				'time'     => '15:30',
				'local'    => '2030-01-14 15:30', // As the visitor entered it, in Los Angeles.
				'slot_utc' => '2030-01-14T23:30:00Z', // 15:30 on 14 Jan in Los Angeles (UTC-8), which is 10:30 on 15 Jan in Sydney.
				'timezone' => 'America/Los_Angeles',
				'help'     => array( 'Social Media Marketing', 'Something else' ),
				'message'  => 'Hello there.',
			),
			$override
		);
	}

	private function make( string $status = 'publish', string $check = 'ham' ): int {
		$id = cptdp_interest_create( $this->clean(), $status, $check );
		$this->assertIsInt( $id );

		return $id;
	}

	public function test_the_type_is_private_and_cannot_be_created_by_hand(): void {
		$type = get_post_type_object( 'interest' );

		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->show_in_rest );
		$this->assertTrue( $type->show_ui );
		$this->assertSame( 'do_not_allow', $type->cap->create_posts );
		$this->assertFalse( post_type_supports( 'interest', 'title' ) );
		$this->assertFalse( post_type_supports( 'interest', 'editor' ) );
		$this->assertSame( 31, $type->menu_position );
	}

	public function test_potential_spam_is_hidden_from_all_but_has_its_own_tab(): void {
		$status = get_post_status_object( 'potential-spam' );

		$this->assertNotNull( $status );
		$this->assertFalse( $status->show_in_admin_all_list );
		$this->assertTrue( $status->show_in_admin_status_list );
		$this->assertTrue( $status->exclude_from_search );
		$this->assertNotContains( 'potential-spam', get_post_stati( array( 'show_in_admin_all_list' => true ) ) );
		$this->assertContains( 'publish', get_post_stati( array( 'show_in_admin_all_list' => true ) ) );
	}

	public function test_create_saves_everything_in_its_final_status(): void {
		$id = $this->make( 'publish', 'ham' );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'interest', get_post_type( $id ) );
		$this->assertSame( 'Jane Citizen', get_the_title( $id ) );
		$this->assertSame( 'Hello there.', get_post_field( 'post_content', $id ) );
		$this->assertSame( 'jane@example.com', get_post_meta( $id, 'cptdp_email', true ) );
		$this->assertSame( '198.51.100.9', get_post_meta( $id, 'cptdp_ip', true ) );
		$this->assertSame( '2030-01-14T23:30:00Z', get_post_meta( $id, 'cptdp_slot_utc', true ) );
		$this->assertSame( 'America/Los_Angeles', get_post_meta( $id, 'cptdp_visitor_tz', true ) );
		$this->assertSame( array( 'Social Media Marketing', 'Something else' ), get_post_meta( $id, 'cptdp_services', true ) );
		$this->assertSame( 'ham', get_post_meta( $id, 'cptdp_spam_check', true ) );
	}

	public function test_create_keeps_backslashes_and_quotes_in_the_message(): void {
		$id = cptdp_interest_create( $this->clean( array( 'message' => 'It\'s a "test" \\ done' ) ), 'publish', 'ham' );

		$this->assertSame( 'It\'s a "test" \\ done', get_post_field( 'post_content', $id ) );
	}

	public function test_a_spam_submission_is_created_hidden_and_never_published_first(): void {
		$seen = array();
		add_action(
			'transition_post_status',
			static function ( $new, $old, $post ) use ( &$seen ) {
				if ( 'interest' === $post->post_type ) {
					$seen[] = $new;
				}
			},
			10,
			3
		);

		$id = $this->make( 'potential-spam', 'spam' );

		$this->assertSame( 'potential-spam', get_post_status( $id ) );
		$this->assertNotContains( 'publish', $seen );
	}

	public function test_notify_emails_dave_with_the_details_and_a_link(): void {
		$id = $this->make();

		$this->assertTrue( cptdp_interest_notify( $id ) );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'dave@example.com', $sent->to[0][0] );
		$this->assertStringContainsString( 'Interest from Jane Citizen', $sent->subject );
		$this->assertStringContainsString( 'Help with: Social Media Marketing, Something else', $sent->body );
		$this->assertStringContainsString( 'Time: Tue 15 Jan 2030, 10:30 (Australia/Sydney)', $sent->body );
		$this->assertStringContainsString( "the visitor's time: Mon 14 Jan 2030, 15:30 (America/Los_Angeles)", $sent->body );
		$this->assertStringContainsString( 'post.php?post=' . $id . '&action=edit', $sent->body );
		$this->assertStringContainsString( 'Reply-To: Jane Citizen <jane@example.com>', $sent->header );
		$this->assertSame( 'yes', get_post_meta( $id, 'cptdp_mail_sent', true ) );
	}

	public function test_notify_without_a_contact_address_sends_nothing_and_records_no(): void {
		delete_option( 'cptdp_contact' );
		$id = $this->make();

		$this->assertFalse( cptdp_interest_notify( $id ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
		$this->assertSame( 'no', get_post_meta( $id, 'cptdp_mail_sent', true ) );
	}

	public function test_notify_ignores_anything_that_is_not_an_interest(): void {
		$this->assertFalse( cptdp_interest_notify( self::factory()->post->create() ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_not_spam_publishes_records_and_sends_the_held_back_email(): void {
		$id = $this->make( 'potential-spam', 'spam' );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );

		$this->assertTrue( cptdp_interest_mark_not_spam( $id ) );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'manual-ham', get_post_meta( $id, 'cptdp_spam_check', true ) );
		$this->assertNotFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_not_spam_refuses_a_visible_item_and_other_types(): void {
		$id = $this->make( 'publish' );

		$this->assertFalse( cptdp_interest_mark_not_spam( $id ) );
		$this->assertFalse( cptdp_interest_mark_not_spam( self::factory()->post->create() ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_times_are_stored_in_utc(): void {
		$id = $this->make();

		$this->assertSame( '2030-01-14T23:30:00Z', get_post_meta( $id, 'cptdp_slot_utc', true ) );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) get_post_meta( $id, 'cptdp_slot_utc', true ) );

		$slot = cptdp_interest_slot( $id );
		$this->assertSame( 'UTC', $slot->getTimezone()->getName() );
		$this->assertSame( '2030-01-14 23:30', $slot->format( 'Y-m-d H:i' ) );

		// WordPress keeps the moment received in UTC (post_date_gmt) and the same moment in the site zone (post_date).
		$post = get_post( $id );
		$this->assertEqualsWithDelta( time(), strtotime( $post->post_date_gmt . ' UTC' ), 5 );
		$this->assertSame( get_date_from_gmt( $post->post_date_gmt ), $post->post_date );
	}

	public function test_a_missing_or_wrong_visitor_zone_is_ignored_when_labelling(): void {
		$id = cptdp_interest_create( $this->clean( array( 'timezone' => '' ) ), 'publish', 'ham' );
		$this->assertSame( '', cptdp_interest_visitor_tz( $id ) );
		$this->assertStringNotContainsString( "visitor's time", cptdp_interest_time_label( $id ) );

		update_post_meta( $id, 'cptdp_visitor_tz', 'Mars/Phobos' );
		$this->assertSame( '', cptdp_interest_visitor_tz( $id ) );
		$this->assertStringContainsString( 'Australia/Sydney', cptdp_interest_time_label( $id ) );
	}

	public function test_a_time_tag_carries_the_utc_instant_and_site_zone_text(): void {
		$tag = cptdp_interest_time_tag( gmmktime( 23, 30, 0, 1, 14, 2030 ) );

		$this->assertStringContainsString( 'datetime="2030-01-14T23:30:00Z"', $tag );
		$this->assertStringContainsString( 'data-cptdp-local', $tag );
		$this->assertStringContainsString( 'Tue 15 Jan 2030, 10:30 (', $tag ); // Sydney, UTC+11 in January, in the visitor's form.
	}

	public function test_the_date_cell_is_a_time_tag_in_utc(): void {
		$id = $this->make();

		ob_start();
		cptdp_interest_column( 'cptdp_received', $id );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<time datetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z" data-cptdp-local>/', $html );
	}

	public function test_the_list_columns_follow_the_wireframe(): void {
		$this->assertSame(
			array( 'cb', 'cptdp_received', 'title', 'cptdp_email', 'cptdp_service' ),
			array_keys( cptdp_interest_columns( array() ) )
		);
	}

	public function test_the_client_ip_is_validated(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertSame( '203.0.113.7', cptdp_contact_client_ip() );

		$_SERVER['REMOTE_ADDR'] = 'not an ip';
		$this->assertSame( '', cptdp_contact_client_ip() );

		add_filter(
			'cptdp_contact_client_ip',
			static function () {
				return '2001:db8::1';
			}
		);
		$this->assertSame( '2001:db8::1', cptdp_contact_client_ip() );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_the_cells_show_email_and_service_escaped(): void {
		$id = cptdp_interest_create( $this->clean( array( 'help' => array( '<b>x</b>' ) ) ), 'publish', 'ham' );

		ob_start();
		cptdp_interest_column( 'cptdp_email', $id );
		cptdp_interest_column( 'cptdp_service', $id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'jane@example.com', $html );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_rows_offer_view_and_trash_spam_adds_not_spam_and_trash_has_restore_and_delete(): void {
		$core    = array(
			'edit'    => 'e',
			'inline'  => 'i',
			'trash'   => 't',
			'untrash' => 'u',
			'delete'  => 'd',
		);
		$visible = get_post( $this->make( 'publish' ) );
		$spam    = get_post( $this->make( 'potential-spam', 'spam' ) );
		$gone    = get_post( $this->make( 'publish' ) );
		wp_trash_post( $gone->ID );
		$gone = get_post( $gone->ID );

		$this->assertSame( array( 'view', 'trash' ), array_keys( cptdp_interest_row_actions( $core, $visible ) ) );

		$actions = cptdp_interest_row_actions( $core, $spam );
		$this->assertSame( array( 'view', 'notspam', 'trash' ), array_keys( $actions ) );
		$this->assertStringContainsString( '_wpnonce=', $actions['notspam'] );
		$this->assertStringContainsString( 'action=cptdp_interest_not_spam', $actions['notspam'] );

		$this->assertSame( array( 'untrash', 'delete' ), array_keys( cptdp_interest_row_actions( $core, $gone ) ) );
	}

	public function test_a_trashed_potential_spam_item_is_restored_as_potential_spam(): void {
		$id = $this->make( 'potential-spam', 'spam' );

		wp_trash_post( $id );
		wp_untrash_post( $id );

		$this->assertSame( 'potential-spam', get_post_status( $id ) );
	}

	public function test_other_post_types_keep_their_row_actions(): void {
		$post = get_post( self::factory()->post->create() );

		$this->assertSame( array( 'edit' => 'x' ), cptdp_interest_row_actions( array( 'edit' => 'x' ), $post ) );
	}

	public function test_bulk_actions_are_core_s_without_edit(): void {
		$this->assertSame(
			array( 'trash', 'untrash' ),
			array_keys( cptdp_interest_bulk_actions( array( 'edit' => 'e', 'trash' => 't', 'untrash' => 'u' ) ) )
		);
	}

	public function test_there_is_no_published_tab(): void {
		$this->assertSame( array( 'all' => 'a', 'potential-spam' => 'b' ), cptdp_interest_views( array( 'all' => 'a', 'publish' => 'p', 'potential-spam' => 'b' ) ) );
	}

	public function test_the_badge_counts_only_unopened_visible_items_and_opening_one_clears_it(): void {
		$first = $this->make( 'publish' );
		$this->make( 'publish' );
		$this->make( 'potential-spam', 'spam' );

		$this->assertSame( 2, cptdp_interest_unseen_count() );

		$_GET['post'] = (string) $first;
		cptdp_interest_mark_seen_on_open();

		$this->assertSame( 1, cptdp_interest_unseen_count() );
	}

	public function test_opening_a_potential_spam_item_does_not_mark_it_seen(): void {
		$id           = $this->make( 'potential-spam', 'spam' );
		$_GET['post'] = (string) $id;

		cptdp_interest_mark_seen_on_open();

		$this->assertSame( '', get_post_meta( $id, 'cptdp_seen', true ) );
	}

	public function test_the_badge_is_added_to_the_menu_item(): void {
		global $menu;

		$this->make( 'publish' );
		$menu = array( 99 => array( 'Interests', 'edit_posts', 'edit.php?post_type=interest' ) );

		cptdp_interest_menu_badge();

		$this->assertStringContainsString( '<span class="awaiting-mod">1</span>', $menu[99][0] );
	}

	public function test_the_view_is_read_only_escaped_and_shows_how_it_was_handled(): void {
		$id = cptdp_interest_create( $this->clean( array( 'name' => 'Eve <script>x</script>', 'message' => "Line one\nLine <b>two</b>" ) ), 'publish', 'ham' );
		cptdp_interest_notify( $id );

		ob_start();
		cptdp_interest_render_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Read-only', $html );
		$this->assertStringContainsString( '<dt>Requested Time</dt>', $html );
		$this->assertStringContainsString( 'Eve &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Line one<br />', $html );
		$this->assertStringContainsString( 'Not spam (Akismet)', $html );
		$this->assertStringContainsString( 'IP address', $html );
		$this->assertStringContainsString( '198.51.100.9', $html );
		$this->assertStringContainsString( '<time datetime="2030-01-14T23:30:00Z" data-cptdp-local>', $html );
		$this->assertStringContainsString( '<dt>Requested Time (visitor&#039;s time zone)</dt><dd>Mon 14 Jan 2030, 15:30 (America/Los_Angeles)</dd>', $html ); // esc_html() encodes the apostrophe.
		$this->assertStringContainsString( '<details class="cptdp-more">', $html );
		$this->assertLessThan( strpos( $html, '<details' ), strpos( $html, 'Submitted' ) ); // The facts come before the closed details.
		$this->assertLessThan( strpos( $html, '>Email</dt>' ), strpos( $html, '>Name</dt>' ) ); // Email comes straight after Name.
		$this->assertLessThan( strpos( $html, '>Service</dt>' ), strpos( $html, '>Email</dt>' ) );
		$this->assertStringContainsString( '<dd>jane@example.com</dd>', $html );
		$this->assertGreaterThan( strpos( $html, '<details' ), strpos( $html, 'IP address' ) ); // The IP address is under Details.
		$this->assertGreaterThan( strpos( $html, '<details' ), strpos( $html, "Requested Time (visitor&#039;s time zone)" ) ); // The visitor's entry is under Details too.
		$this->assertStringContainsString( 'Sent', $html );
		$this->assertStringContainsString( 'Move to Trash', $html );
		$this->assertStringNotContainsString( 'cptdp_interest_not_spam', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}

	public function test_the_submitted_time_reads_in_the_largest_whole_unit(): void {
		$now = time();

		$this->assertMatchesRegularExpression( '/^\d+ seconds ago$/', cptdp_interest_ago( $now - 30 ) );
		$this->assertSame( '1 minute ago', cptdp_interest_ago( $now - 60 ) );
		$this->assertSame( '2 hours ago', cptdp_interest_ago( $now - 2 * HOUR_IN_SECONDS ) );
		$this->assertSame( '3 days ago', cptdp_interest_ago( $now - 3 * DAY_IN_SECONDS ) );
		$this->assertSame( '2 weeks ago', cptdp_interest_ago( $now - 14 * DAY_IN_SECONDS ) );
		$this->assertSame( '2 months ago', cptdp_interest_ago( $now - 2 * MONTH_IN_SECONDS ) );
		$this->assertSame( '1 year ago', cptdp_interest_ago( $now - 400 * DAY_IN_SECONDS ) );
	}

	public function test_the_real_time_is_a_tooltip_on_the_phrase_not_a_separate_line(): void {
		$id = $this->make();

		ob_start();
		cptdp_interest_render_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<span class="cptdp-ago" tabindex="0"', $html );
		$this->assertStringContainsString( 'role="tooltip"', $html );
		$this->assertMatchesRegularExpression( '/class="cptdp-ago__when" id="[^"]+" role="tooltip"><time datetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z" data-cptdp-local>/', $html );
	}

	public function test_an_empty_message_says_no_message(): void {
		$id = cptdp_interest_create( $this->clean( array( 'message' => '' ) ), 'publish', 'ham' );

		ob_start();
		cptdp_interest_render_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<span class="cptdp-none">No message</span>', $html );
	}

	public function test_a_spam_item_view_offers_not_spam(): void {
		$id = $this->make( 'potential-spam', 'spam' );

		ob_start();
		cptdp_interest_render_box( get_post( $id ) );

		$this->assertStringContainsString( 'cptdp_interest_not_spam', ob_get_clean() );
	}

	public function test_the_edit_screen_loses_the_publish_box(): void {
		global $wp_meta_boxes;

		add_meta_box( 'submitdiv', 'Publish', '__return_true', 'interest', 'side' );
		cptdp_interest_add_box();

		$this->assertFalse( $wp_meta_boxes['interest']['side']['core']['submitdiv'] ?? false );
		$this->assertArrayHasKey( 'cptdp_interest_view', $wp_meta_boxes['interest']['normal']['high'] );
	}

	public function test_the_admin_script_loads_only_on_the_interest_screens(): void {
		set_current_screen( 'edit-interest' );
		cptdp_interest_admin_assets();
		$this->assertTrue( wp_script_is( 'cptdp-admin-interest', 'enqueued' ) );

		wp_dequeue_script( 'cptdp-admin-interest' );
		set_current_screen( 'edit-post' );
		cptdp_interest_admin_assets();
		$this->assertFalse( wp_script_is( 'cptdp-admin-interest', 'enqueued' ) );
	}

	public function test_the_admin_script_file_rewrites_time_tags_only(): void {
		$js = (string) file_get_contents( CPTDP_PATH . 'assets/admin-interest.js' );

		$this->assertStringContainsString( 'time[data-cptdp-local]', $js );
		$this->assertStringContainsString( 'resolvedOptions().timeZone', $js ); // The viewer's own zone, shown in brackets.
		$this->assertStringNotContainsString( 'timeZone:', $js ); // Never a fixed zone.
	}

	public function test_the_list_warns_while_there_is_no_akismet_key(): void {
		set_current_screen( 'edit-interest' );

		ob_start();
		cptdp_interest_notices();
		$this->assertStringContainsString( 'Spam check is off', ob_get_clean() );

		add_filter(
			'cptdp_akismet_key',
			static function () {
				return 'abc123def456';
			}
		);
		ob_start();
		cptdp_interest_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_the_key_comes_from_the_option_and_only_plain_keys_are_accepted(): void {
		$this->assertSame( '', cptdp_akismet_key() );

		update_option( 'wordpress_api_key', ' abc123def456 ' );
		$this->assertSame( 'abc123def456', cptdp_akismet_key() );

		update_option( 'wordpress_api_key', 'evil.example/x' );
		$this->assertSame( '', cptdp_akismet_key() );
	}
}
