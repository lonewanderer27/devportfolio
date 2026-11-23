<?php
/**
 * Booking times on Settings > Contact (DPH-025 to DPH-028).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Contact_Times extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();

		delete_option( 'cptdp_contact_times' );
		$GLOBALS['wp_settings_errors'] = array();
	}

	public function test_the_list_is_every_half_hour_of_the_day(): void {
		$times = cptdp_contact_all_times();

		$this->assertCount( 48, $times );
		$this->assertSame( '00:00', $times[0] );
		$this->assertSame( '00:30', $times[1] );
		$this->assertSame( '23:30', $times[47] );
	}

	public function test_until_chosen_the_six_default_times_are_offered(): void {
		$this->assertSame( array( '10:00', '11:00', '13:00', '14:00', '15:00', '16:00' ), cptdp_contact_time_slots() );
	}

	public function test_the_ticked_times_are_offered_in_day_order(): void {
		update_option( 'cptdp_contact_times', array( '16:00', '09:30', '00:00' ) );

		$this->assertSame( array( '00:00', '09:30', '16:00' ), cptdp_contact_time_slots() );
	}

	public function test_sanitise_keeps_only_half_hours_in_day_order(): void {
		$this->assertSame( array( '09:00', '23:30' ), cptdp_contact_times_sanitize( array( '23:30', '09:00', '10:15', 'nope', 5 ) ) );
	}

	public function test_nothing_ticked_saves_an_empty_list_and_warns(): void {
		$this->assertSame( array(), cptdp_contact_times_sanitize( null ) );

		$codes = wp_list_pluck( get_settings_errors( 'cptdp_contact' ), 'code' );
		$this->assertContains( 'cptdp_contact_times_none', $codes );
		update_option( 'cptdp_contact_times', array() );
		$this->assertSame( array(), cptdp_contact_time_slots() ); // Nothing offered until Dave ticks a time.
	}

	public function test_the_screen_ticks_exactly_the_offered_times(): void {
		update_option( 'cptdp_contact_times', array( '10:00' ) );

		ob_start();
		cptdp_contact_render_times();
		$html = ob_get_clean();

		$this->assertSame( 48, substr_count( $html, 'name="cptdp_contact_times[]"' ) );
		$this->assertSame( 1, substr_count( $html, "checked='checked'" ) );
		$this->assertMatchesRegularExpression( "/value=\"10:00\"\\s+checked='checked'/", $html );
	}

	public function test_the_endpoint_refuses_a_time_that_is_not_ticked(): void {
		update_option( 'cptdp_contact_times', array( '10:00' ) );
		$window = cptdp_contact_window();

		$result = cptdp_contact_validate(
			array(
				'name'  => 'Jane',
				'email' => 'jane@example.com',
				'date'  => $window['first'],
				'time'  => '11:00',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'time', $result->get_error_data()['field'] );
	}
}
