<?php
/**
 * Contact request (DPH-025 to DPH-028): the endpoint behind the Home scheduler.
 *
 * One public REST route: validate, check with Akismet, save an Interest (hidden as potential spam when Akismet says so),
 * then email Dave unless it is spam. Saving, the Akismet call and the email live in interest.php.
 * The slots, the bookable window and the service options live here so the theme (which draws them) and the endpoint
 * (which validates them) can never disagree. DPH-031 will replace cptdp_contact_time_slots() and cptdp_contact_window().
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * The times offered on every bookable day, "HH:MM" in the visitor's zone: the ticked ones on Settings > Contact, in day
 * order.
 *
 * @return string[]
 */
function cptdp_contact_time_slots(): array {
	$slots = apply_filters( 'cptdp_contact_time_slots', cptdp_contact_get_times() );
	if ( ! is_array( $slots ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$slots,
			static function ( $slot ) {
				return is_string( $slot ) && 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $slot );
			}
		)
	);
}

/**
 * The bookable days as "YYYY-MM-DD" in the site's time zone: tomorrow up to 365 days ahead. `today` is the server's
 * today (the page never decides it).
 *
 * @return array{today:string,first:string,last:string}
 */
function cptdp_contact_window(): array {
	$today = new DateTimeImmutable( 'now', wp_timezone() );

	return array(
		'today' => $today->format( 'Y-m-d' ),
		'first' => $today->modify( '+1 day' )->format( 'Y-m-d' ),
		'last'  => $today->modify( '+365 days' )->format( 'Y-m-d' ),
	);
}

/**
 * What the prospect can ask help with: the top-level published Services (menu order, then title, the order of the
 * "What I do" list), then "Something else". `id` is a string: the Service's post ID, or `other`.
 *
 * @return array<int,array{id:string,title:string}>
 */
function cptdp_contact_service_options(): array {
	$ids = get_posts(
		array(
			'post_type'      => 'service',
			'post_status'    => 'publish',
			'post_parent'    => 0,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);

	$options = array();
	foreach ( $ids as $id ) {
		$title = trim( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $title ) {
			$options[] = array(
				'id'    => (string) $id,
				'title' => $title,
			);
		}
	}

	$options[] = array(
		'id'    => 'other',
		'title' => __( 'Something else', 'custom-post-types-dp' ),
	);

	return $options;
}

/**
 * Everything the theme needs to draw the scheduler.
 *
 * @return array<string,mixed>
 */
function cptdp_contact_config(): array {
	return array_merge(
		cptdp_contact_window(),
		array(
			'endpoint' => rest_url( 'cptdp/v1/contact-request' ),
			'slots'    => cptdp_contact_time_slots(),
			'services' => cptdp_contact_service_options(),
		)
	);
}

/**
 * Validate and clean a request body.
 *
 * @param array<string,mixed> $input Decoded body.
 * @return array<string,mixed>|WP_Error Clean values, or a 422 error naming the first bad field.
 */
function cptdp_contact_validate( array $input ) {
	$str  = static function ( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	};
	$fail = static function ( string $field, string $message ): WP_Error {
		return new WP_Error(
			'cptdp_invalid_' . $field,
			$message,
			array(
				'status' => 422,
				'field'  => $field,
			)
		);
	};

	$name = trim( sanitize_text_field( $str( $input['name'] ?? '' ) ) );
	if ( '' === $name || mb_strlen( $name ) > 100 ) {
		return $fail( 'name', __( 'Please enter your name (100 characters at most).', 'custom-post-types-dp' ) );
	}

	$email = sanitize_email( $str( $input['email'] ?? '' ) );
	if ( ! is_email( $email ) || strlen( $email ) > 254 ) {
		return $fail( 'email', __( 'Please enter a valid email address.', 'custom-post-types-dp' ) );
	}

	$window = cptdp_contact_window();
	$date   = $str( $input['date'] ?? '' );
	$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date || $date < $window['first'] || $date > $window['last'] ) {
		return $fail( 'date', __( 'That day is not available.', 'custom-post-types-dp' ) );
	}

	$time = $str( $input['time'] ?? '' );
	if ( ! in_array( $time, cptdp_contact_time_slots(), true ) ) {
		return $fail( 'time', __( 'That time is not available.', 'custom-post-types-dp' ) );
	}

	// The visitor's zone: the day and time are their own wall clock. Dave's zone when it is missing or unknown.
	$zone = $str( $input['timezone'] ?? '' );
	$zone = in_array( $zone, timezone_identifiers_list(), true ) ? $zone : '';
	$tz   = '' === $zone ? wp_timezone() : new DateTimeZone( $zone );

	// The one stored form of the time: UTC, converted here with PHP's own zone data.
	$when = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
	if ( ! $when ) {
		return $fail( 'time', __( 'That time is not available.', 'custom-post-types-dp' ) );
	}
	$slot_utc = $when->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );

	$options = cptdp_contact_service_options();
	$known   = array_column( $options, 'id' );
	$given   = is_array( $input['services'] ?? null ) ? array_map( $str, $input['services'] ) : array();
	if ( array() === $given || count( $given ) > 10 || array_diff( $given, $known ) ) {
		return $fail( 'services', __( 'Please choose at least one thing you need help with.', 'custom-post-types-dp' ) );
	}

	$titles = array();
	foreach ( $options as $option ) {
		if ( in_array( $option['id'], $given, true ) ) {
			$titles[] = $option['title'];
		}
	}

	$message = trim( sanitize_textarea_field( $str( $input['message'] ?? '' ) ) );
	if ( mb_strlen( $message ) > 2000 ) {
		return $fail( 'message', __( 'Please keep the message under 2000 characters.', 'custom-post-types-dp' ) );
	}

	return array(
		'name'     => $name,
		'email'    => $email,
		'date'     => $date,
		'time'     => $time,
		'slot_utc' => $slot_utc,
		'local'    => $date . ' ' . $time, // The time as the visitor entered it, kept with the zone.
		'timezone' => $zone,
		'help'     => $titles,
		'message'  => $message,
	);
}

/**
 * The key of the rate-limit counter for this client (a hash, never the address).
 */
function cptdp_contact_client_key(): string {
	return 'cptdp_contact_rl_' . md5( cptdp_contact_client_ip() );
}

/**
 * Handle POST /cptdp/v1/contact-request.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function cptdp_contact_handle_request( WP_REST_Request $request ) {
	$input = $request->get_json_params();
	$input = is_array( $input ) ? $input : (array) $request->get_params();

	// Honeypot: a person never sees this field. Answer as if it worked, save and send nothing.
	if ( '' !== trim( is_scalar( $input['website'] ?? '' ) ? (string) $input['website'] : '' ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	$clean = cptdp_contact_validate( $input );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}
	$clean['ip'] = cptdp_contact_client_ip();

	// Before Akismet, so a flood never reaches the service.
	$key   = cptdp_contact_client_key();
	$count = (int) get_transient( $key );
	$limit = (int) apply_filters( 'cptdp_contact_rate_limit', 10 );
	if ( $count >= $limit ) {
		return new WP_Error(
			'cptdp_contact_rate_limited',
			__( 'Too many requests. Please try again later.', 'custom-post-types-dp' ),
			array( 'status' => 429 )
		);
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	// Check first, save second: the Interest is created straight into its final status, so it is never visible
	// before the check has run.
	$check = cptdp_akismet_check( $clean, $request );
	$spam  = 'spam' === $check;
	$id    = cptdp_interest_create( $clean, $spam ? 'potential-spam' : 'publish', $check );
	if ( is_wp_error( $id ) ) {
		return new WP_Error(
			'cptdp_contact_save_failed',
			__( 'The message could not be saved. Please try again.', 'custom-post-types-dp' ),
			array( 'status' => 500 )
		);
	}

	if ( ! $spam ) {
		cptdp_interest_notify( $id );
	}

	// The same answer for spam, so a bot learns nothing.
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Register the route. Public on purpose: the visitors are anonymous prospects. The honeypot, the rate limit and the
 * validation above are what protect it.
 */
function cptdp_contact_register_route(): void {
	register_rest_route(
		'cptdp/v1',
		'/contact-request',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'cptdp_contact_handle_request',
			'permission_callback' => '__return_true', // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions -- public by design, see the docblock.
		)
	);
}
add_action( 'rest_api_init', 'cptdp_contact_register_route' );
