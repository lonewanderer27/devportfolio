<?php
/**
 * Contact request endpoint tests (DPH-027).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Contact_Request extends CPTDP_Test_Case {

	/** @var int[] Top-level service IDs, in menu order. */
	private $services = array();

	/** @var array<int,array<string,mixed>> Akismet calls seen by with_akismet(). */
	private $akismet_calls = array();

	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
		reset_phpmailer_instance();
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );
		update_option( 'timezone_string', 'Australia/Sydney' );
		$this->akismet_calls = array();

		$first          = self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Social Media Marketing', 'menu_order' => 1, 'post_status' => 'publish' ) );
		$second         = self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Digital Marketing', 'menu_order' => 2, 'post_status' => 'publish' ) );
		$this->services = array( $first, $second );
		self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Analytics', 'post_parent' => $first, 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Hidden Draft', 'menu_order' => 3, 'post_status' => 'draft' ) );
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		unset( $_SERVER['REMOTE_ADDR'] );

		parent::tear_down();
	}

	/**
	 * A valid body, with overrides.
	 *
	 * @param array<string,mixed> $override Values to replace.
	 * @return array<string,mixed>
	 */
	private function valid( array $override = array() ): array {
		$window = cptdp_contact_window();

		return array_merge(
			array(
				'date'     => $window['first'],
				'time'     => '10:00',
				'timezone' => 'America/Los_Angeles',
				'name'     => 'Jane Citizen',
				'email'    => 'jane@example.com',
				'services' => array( (string) $this->services[0], 'other' ),
				'message'  => 'I need a plan.',
				'website'  => '',
			),
			$override
		);
	}

	/**
	 * Give the site an Akismet key and answer its calls with `$answer` (a body, or a WP_Error).
	 *
	 * @param string|WP_Error $answer Body of the reply.
	 * @param int             $code   HTTP status of the reply.
	 */
	private function with_akismet( $answer, int $code = 200 ): void {
		add_filter(
			'cptdp_akismet_key',
			static function () {
				return 'abc123def456';
			}
		);
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $answer, $code ) {
				$this->akismet_calls[] = array(
					'url'  => $url,
					'body' => $args['body'],
				);
				if ( is_wp_error( $answer ) ) {
					return $answer;
				}

				return array(
					'headers'  => array(),
					'body'     => $answer,
					'response' => array(
						'code'    => $code,
						'message' => '',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Interest IDs in one status.
	 *
	 * @return int[]
	 */
	private function interest_ids( string $status ): array {
		return get_posts(
			array(
				'post_type'      => 'interest',
				'post_status'    => $status,
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);
	}

	private function post( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/cptdp/v1/contact-request' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'User-Agent', 'TestBrowser/9' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_get_server()->dispatch( $request );
	}

	public function test_slots_are_six_sensible_times_in_order(): void {
		$this->assertSame( array( '10:00', '11:00', '13:00', '14:00', '15:00', '16:00' ), cptdp_contact_time_slots() );
	}

	public function test_slots_filter_drops_malformed_entries(): void {
		add_filter(
			'cptdp_contact_time_slots',
			static function () {
				return array( '08:00', '25:00', 'noon', 12 );
			}
		);

		$this->assertSame( array( '08:00' ), cptdp_contact_time_slots() );
	}

	public function test_window_starts_tomorrow_and_ends_365_days_later(): void {
		$window = cptdp_contact_window();
		$today  = new DateTimeImmutable( $window['today'], wp_timezone() );

		$this->assertSame( $today->modify( '+1 day' )->format( 'Y-m-d' ), $window['first'] );
		$this->assertSame( $today->modify( '+365 days' )->format( 'Y-m-d' ), $window['last'] );
	}

	public function test_service_options_are_top_level_published_in_order_then_other(): void {
		$options = cptdp_contact_service_options();

		$this->assertSame(
			array( (string) $this->services[0], (string) $this->services[1], 'other' ),
			wp_list_pluck( $options, 'id' )
		);
		$this->assertSame( array( 'Social Media Marketing', 'Digital Marketing', 'Something else' ), wp_list_pluck( $options, 'title' ) );
	}

	public function test_a_valid_request_is_saved_visible_and_emailed(): void {
		$response = $this->post( $this->valid() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'ok' => true ), $response->get_data() );

		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'Jane Citizen', get_the_title( $ids[0] ) );
		$this->assertSame( 'jane@example.com', get_post_meta( $ids[0], 'cptdp_email', true ) );
		$this->assertSame( 'I need a plan.', get_post_field( 'post_content', $ids[0] ) );
		$this->assertSame( array( 'Social Media Marketing', 'Something else' ), get_post_meta( $ids[0], 'cptdp_services', true ) );
		$this->assertSame( 'skipped', get_post_meta( $ids[0], 'cptdp_spam_check', true ) );
		$this->assertSame( 'yes', get_post_meta( $ids[0], 'cptdp_mail_sent', true ) );
		$this->assertSame( 'America/Los_Angeles', get_post_meta( $ids[0], 'cptdp_visitor_tz', true ) );

		// 10:00 on the chosen day in the visitor's own zone (Los Angeles), stored as the same instant in UTC.
		$window   = cptdp_contact_window();
		$expected = ( new DateTimeImmutable( $window['first'] . ' 10:00', new DateTimeZone( 'America/Los_Angeles' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$this->assertSame( $window['first'] . ' 10:00', get_post_meta( $ids[0], 'cptdp_visitor_local', true ) );
		$this->assertSame( $expected, get_post_meta( $ids[0], 'cptdp_slot_utc', true ) );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();
		$this->assertNotFalse( $sent );
		$this->assertSame( 'dave@example.com', $sent->to[0][0] );
		$this->assertStringContainsString( 'Interest from Jane Citizen', $sent->subject );
		$this->assertStringContainsString( 'Name: Jane Citizen', $sent->body );
		$this->assertStringContainsString( 'Email: jane@example.com', $sent->body );
		$this->assertStringContainsString( '10:00', $sent->body );
		$this->assertStringContainsString( 'Australia/Sydney', $sent->body );
		$this->assertStringNotContainsString( 'UTC', $sent->body ); // Times are shown in local zones, never as UTC.
		$this->assertStringContainsString( "the visitor's time: ", $sent->body );
		$this->assertStringContainsString( '(America/Los_Angeles)', $sent->body );
		$this->assertStringContainsString( 'Help with: Social Media Marketing, Something else', $sent->body );
		$this->assertStringContainsString( 'I need a plan.', $sent->body );
		$this->assertStringContainsString( 'post.php?post=' . $ids[0] . '&action=edit', $sent->body );
		$this->assertStringContainsString( 'Reply-To: Jane Citizen <jane@example.com>', $sent->header );
		$this->assertFalse( $mailer->get_sent( 1 ) );
	}

	public function test_an_empty_message_is_allowed(): void {
		$this->assertSame( 200, $this->post( $this->valid( array( 'message' => '' ) ) )->get_status() );
		$this->assertStringContainsString( '(none)', tests_retrieve_phpmailer_instance()->get_sent()->body );
	}

	public function test_each_invalid_field_is_a_422_naming_the_field_and_saves_nothing(): void {
		$window = cptdp_contact_window();
		$today  = new DateTimeImmutable( $window['today'], wp_timezone() );

		$cases = array(
			'name empty'       => array( 'name', array( 'name' => '   ' ) ),
			'name too long'    => array( 'name', array( 'name' => str_repeat( 'a', 101 ) ) ),
			'email bad'        => array( 'email', array( 'email' => 'not-an-email' ) ),
			'date today'       => array( 'date', array( 'date' => $window['today'] ) ),
			'date past'        => array( 'date', array( 'date' => '2000-01-01' ) ),
			'date too far'     => array( 'date', array( 'date' => $today->modify( '+366 days' )->format( 'Y-m-d' ) ) ),
			'date impossible'  => array( 'date', array( 'date' => '2027-02-31' ) ),
			'date missing'     => array( 'date', array( 'date' => '' ) ),
			'time not offered' => array( 'time', array( 'time' => '03:00' ) ),
			'services empty'   => array( 'services', array( 'services' => array() ) ),
			'services unknown' => array( 'services', array( 'services' => array( '999999' ) ) ),
			'services string'  => array( 'services', array( 'services' => 'other' ) ),
			'message long'     => array( 'message', array( 'message' => str_repeat( 'a', 2001 ) ) ),
		);

		foreach ( $cases as $label => $case ) {
			$response = $this->post( $this->valid( $case[1] ) );

			$this->assertSame( 422, $response->get_status(), $label );
			$this->assertSame( $case[0], $response->get_data()['data']['field'], $label );
		}

		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertSame( array(), $this->interest_ids( 'potential-spam' ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_without_a_contact_email_it_is_still_saved_and_no_email_is_sent(): void {
		delete_option( 'cptdp_contact' );

		$response = $this->post( $this->valid() );

		$this->assertSame( 200, $response->get_status() );
		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'no', get_post_meta( $ids[0], 'cptdp_mail_sent', true ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_the_honeypot_answers_200_and_saves_and_sends_nothing(): void {
		$this->with_akismet( 'false' );

		$response = $this->post( $this->valid( array( 'website' => 'http://spam.example' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertSame( array(), $this->akismet_calls );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_the_rate_limit_answers_429_after_the_limit_and_never_reaches_akismet(): void {
		$this->with_akismet( 'false' );
		add_filter(
			'cptdp_contact_rate_limit',
			static function () {
				return 2;
			}
		);

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
		$this->assertSame( 429, $this->post( $this->valid() )->get_status() );
		$this->assertCount( 2, $this->akismet_calls );
		$this->assertCount( 2, $this->interest_ids( 'publish' ) );
	}

	public function test_invalid_requests_do_not_use_up_the_rate_limit(): void {
		add_filter(
			'cptdp_contact_rate_limit',
			static function () {
				return 1;
			}
		);

		$this->assertSame( 422, $this->post( $this->valid( array( 'name' => '' ) ) )->get_status() );
		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
	}

	public function test_a_name_cannot_inject_headers(): void {
		$this->post( $this->valid( array( 'name' => "Eve\r\nBcc: victim@example.com" ) ) );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertNotFalse( $sent );
		$this->assertStringNotContainsString( "\nBcc:", $sent->header );
		$this->assertStringNotContainsString( 'victim@example.com', $sent->header );
	}

	public function test_a_failed_mail_still_saves_the_interest_and_says_so(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'no', get_post_meta( $ids[0], 'cptdp_mail_sent', true ) );
	}

	public function test_a_save_failure_is_a_500_and_sends_nothing(): void {
		add_filter( 'wp_insert_post_empty_content', '__return_true' ); // Makes wp_insert_post() refuse and return a WP_Error.

		$response = $this->post( $this->valid() );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_the_route_is_public(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
	}

	public function test_akismet_says_genuine_so_it_is_visible_and_emailed(): void {
		$this->with_akismet( 'false' );

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$this->assertCount( 1, $this->akismet_calls );
		$call = $this->akismet_calls[0];
		$this->assertSame( 'https://abc123def456.rest.akismet.com/1.1/comment-check', $call['url'] );
		$this->assertSame( 'contact-form', $call['body']['comment_type'] );
		$this->assertSame( 'Jane Citizen', $call['body']['comment_author'] );
		$this->assertSame( 'jane@example.com', $call['body']['comment_author_email'] );
		$this->assertSame( 'I need a plan.', $call['body']['comment_content'] );
		$this->assertSame( '203.0.113.7', $call['body']['user_ip'] );
		$this->assertSame( 'TestBrowser/9', $call['body']['user_agent'] );
		$this->assertSame( home_url(), $call['body']['blog'] );

		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'ham', get_post_meta( $ids[0], 'cptdp_spam_check', true ) );
		$this->assertNotFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_akismet_says_spam_so_it_is_saved_hidden_and_not_emailed_and_the_visitor_sees_success(): void {
		$this->with_akismet( 'true' );

		$response = $this->post( $this->valid() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'ok' => true ), $response->get_data() );
		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertSame( array(), $this->interest_ids( 'any' ) );

		$spam = $this->interest_ids( 'potential-spam' );
		$this->assertCount( 1, $spam );
		$this->assertSame( 'spam', get_post_meta( $spam[0], 'cptdp_spam_check', true ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
		$this->assertSame( 0, cptdp_interest_unseen_count() );
	}

	public function test_akismet_trouble_fails_open(): void {
		$cases = array(
			'network error' => array( new WP_Error( 'http_request_failed', 'timeout' ), 200 ),
			'bad key'       => array( 'invalid', 200 ),
			'server error'  => array( 'true', 500 ),
			'nonsense'      => array( '<html>', 200 ),
		);

		foreach ( $cases as $label => $case ) {
			remove_all_filters( 'pre_http_request' );
			remove_all_filters( 'cptdp_akismet_key' );
			reset_phpmailer_instance();
			$before = count( $this->interest_ids( 'publish' ) );

			$this->with_akismet( $case[0], $case[1] );
			$this->assertSame( 200, $this->post( $this->valid() )->get_status(), $label );

			$ids = $this->interest_ids( 'publish' );
			$this->assertCount( $before + 1, $ids, $label );
			$this->assertSame( 'error', get_post_meta( $ids[0], 'cptdp_spam_check', true ), $label );
			$this->assertNotFalse( tests_retrieve_phpmailer_instance()->get_sent(), $label );
		}
	}

	public function test_without_a_key_akismet_is_not_called(): void {
		add_filter(
			'pre_http_request',
			function () {
				$this->akismet_calls[] = 'called';

				return new WP_Error( 'blocked', 'no network in tests' );
			}
		);

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$this->assertSame( array(), $this->akismet_calls );
		$ids = $this->interest_ids( 'publish' );
		$this->assertSame( 'skipped', get_post_meta( $ids[0], 'cptdp_spam_check', true ) );
	}

	public function test_an_unsafe_key_is_never_put_in_a_url(): void {
		$this->with_akismet( 'false' );
		remove_all_filters( 'cptdp_akismet_key' );
		add_filter(
			'cptdp_akismet_key',
			static function () {
				return 'evil.example/x';
			}
		);

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$this->assertSame( array(), $this->akismet_calls );
	}

	public function test_the_ip_is_saved_on_the_interest_and_the_browser_is_not_stored(): void {
		global $wpdb;
		$this->with_akismet( 'false' );

		$this->post( $this->valid() );

		$this->assertSame( '203.0.113.7', $this->akismet_calls[0]['body']['user_ip'] );
		$this->assertSame( 'TestBrowser/9', $this->akismet_calls[0]['body']['user_agent'] );

		$ids = $this->interest_ids( 'publish' );
		$this->assertSame( '203.0.113.7', get_post_meta( $ids[0], 'cptdp_ip', true ) );

		// The address lives in exactly one place: the Interest's `cptdp_ip`. The browser lives nowhere.
		$ip = '%' . $wpdb->esc_like( '203.0.113.7' ) . '%';
		$ua = '%' . $wpdb->esc_like( 'TestBrowser' ) . '%';
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $ip ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $ua ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_title LIKE %s OR post_content LIKE %s", $ip, $ip, $ua ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s OR option_value LIKE %s", $ip, $ip, $ua ) ) );
	}

	public function test_a_spam_submission_keeps_its_ip_too(): void {
		$this->with_akismet( 'true' );

		$this->post( $this->valid() );

		$spam = $this->interest_ids( 'potential-spam' );
		$this->assertSame( '203.0.113.7', get_post_meta( $spam[0], 'cptdp_ip', true ) );
	}

	public function test_an_invalid_address_is_stored_as_empty(): void {
		$_SERVER['REMOTE_ADDR'] = 'not an ip';

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$ids = $this->interest_ids( 'publish' );
		$this->assertSame( '', get_post_meta( $ids[0], 'cptdp_ip', true ) );
	}

	public function test_the_stored_time_is_utc_whatever_the_visitor_zone(): void {
		update_option( 'timezone_string', 'Australia/Sydney' ); // Dave's zone does not decide it.

		foreach ( array( 'Australia/Sydney', 'America/Los_Angeles', 'UTC', 'Asia/Kolkata' ) as $zone ) {
			$window = cptdp_contact_window();
			$ids    = $this->interest_ids( 'publish' );

			$this->assertSame( 200, $this->post( $this->valid( array( 'date' => $window['first'], 'timezone' => $zone ) ) )->get_status(), $zone );

			$new = array_values( array_diff( $this->interest_ids( 'publish' ), $ids ) );
			$this->assertCount( 1, $new, $zone );
			$expected = ( new DateTimeImmutable( $window['first'] . ' 10:00', new DateTimeZone( $zone ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
			$this->assertSame( $expected, get_post_meta( $new[0], 'cptdp_slot_utc', true ), $zone );
			$this->assertSame( $window['first'] . ' 10:00', get_post_meta( $new[0], 'cptdp_visitor_local', true ), $zone );
		}
	}

	public function test_the_browser_cannot_send_its_own_utc_instant(): void {
		$window = cptdp_contact_window();

		$this->post( $this->valid( array( 'slot_utc' => '1999-01-01T00:00:00Z', 'slot' => '1999-01-01T00:00:00Z' ) ) );

		$ids      = $this->interest_ids( 'publish' );
		$expected = ( new DateTimeImmutable( $window['first'] . ' 10:00', new DateTimeZone( 'America/Los_Angeles' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$this->assertSame( $expected, get_post_meta( $ids[0], 'cptdp_slot_utc', true ) );
	}

	public function test_an_unknown_visitor_zone_is_stored_as_empty_and_does_not_fail_the_request(): void {
		foreach ( array( 'Mars/Phobos', '', 'x; DROP TABLE', 123 ) as $zone ) {
			$response = $this->post( $this->valid( array( 'timezone' => $zone ) ) );
			$this->assertSame( 200, $response->get_status() );
		}

		foreach ( $this->interest_ids( 'publish' ) as $id ) {
			$this->assertSame( '', get_post_meta( $id, 'cptdp_visitor_tz', true ) );
		}
	}

	public function test_config_carries_everything_the_theme_draws(): void {
		$config = cptdp_contact_config();

		$this->assertStringContainsString( 'cptdp/v1/contact-request', $config['endpoint'] );
		foreach ( array( 'today', 'first', 'last', 'slots', 'services' ) as $key ) {
			$this->assertArrayHasKey( $key, $config );
		}
	}
}
