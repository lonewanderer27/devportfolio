<?php
/**
 * Interest post type (DPB-017): what the Home Contact scheduler receives. Not public, no REST, read-only in wp-admin
 * (screens in interest-admin.php). A submission is created straight into `publish` or `potential-spam`, so it is never
 * visible before the Akismet check. Callers must escape on output.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Interest post type and the Potential spam status.
 */
function cptdp_register_interest(): void {
	register_post_type(
		'interest',
		array(
			'labels'              => array(
				'name'          => esc_html__( 'Interests', 'custom-post-types-dp' ),
				'singular_name' => esc_html__( 'Interest', 'custom-post-types-dp' ),
				'menu_name'     => esc_html__( 'Interests', 'custom-post-types-dp' ),
				'all_items'     => esc_html__( 'All Interests', 'custom-post-types-dp' ),
				'search_items'  => esc_html__( 'Search Interests', 'custom-post-types-dp' ),
				'not_found'     => esc_html__( 'No interests yet.', 'custom-post-types-dp' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_position'       => 31,
			'menu_icon'           => 'dashicons-lock',
			'supports'            => false,
			'capability_type'     => 'post',
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'exclude_from_search' => true,
		)
	);

	register_post_status(
		'potential-spam',
		array(
			'label'                     => esc_html_x( 'Potential spam', 'post status', 'custom-post-types-dp' ),
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of items. */
			'label_count'               => _n_noop( 'Potential spam <span class="count">(%s)</span>', 'Potential spam <span class="count">(%s)</span>', 'custom-post-types-dp' ),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_interest' );

/**
 * Restore from the Trash puts an Interest back where it was (publish or potential spam), not as a draft.
 *
 * @param string $status   The status core would give.
 * @param int    $post_id  Post ID.
 * @param string $previous The status before it was trashed.
 */
function cptdp_interest_untrash_status( string $status, int $post_id, string $previous ): string {
	if ( 'interest' !== get_post_type( $post_id ) ) {
		return $status;
	}

	return in_array( $previous, array( 'publish', 'potential-spam' ), true ) ? $previous : $status;
}
add_filter( 'wp_untrash_post_status', 'cptdp_interest_untrash_status', 10, 3 );

/**
 * The Akismet key: the constant a host may define, else the option the Akismet plugin saves. '' = no check.
 */
function cptdp_akismet_key(): string {
	$key = defined( 'WPCOM_API_KEY' ) && WPCOM_API_KEY ? WPCOM_API_KEY : get_option( 'wordpress_api_key' );
	$key = is_string( $key ) ? trim( $key ) : '';
	$key = (string) apply_filters( 'cptdp_akismet_key', $key );

	return 1 === preg_match( '/^[A-Za-z0-9]+$/', $key ) ? $key : '';
}

/**
 * The visitor's IP address (filter `cptdp_contact_client_ip` for hosts behind a proxy), or '' when it is not a valid address.
 * Used for the rate limit (hashed), Akismet and the stored `cptdp_ip`.
 */
function cptdp_contact_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) apply_filters( 'cptdp_contact_client_ip', $ip );

	return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
}

/**
 * Ask Akismet whether a clean submission is spam. Fails open: any problem counts as genuine.
 *
 * @param array<string,mixed> $clean   Output of cptdp_contact_validate().
 * @param WP_REST_Request     $request The request (for the browser and referrer).
 * @return string `spam`, `ham`, `skipped` (no key) or `error`.
 */
function cptdp_akismet_check( array $clean, WP_REST_Request $request ): string {
	$key = cptdp_akismet_key();
	if ( '' === $key ) {
		return 'skipped';
	}

	$body = array(
		'blog'                 => home_url(),
		'user_ip'              => cptdp_contact_client_ip(),
		'user_agent'           => (string) $request->get_header( 'user_agent' ),
		'referrer'             => (string) $request->get_header( 'referer' ),
		'comment_type'         => 'contact-form',
		'comment_author'       => $clean['name'],
		'comment_author_email' => $clean['email'],
		'comment_content'      => $clean['message'],
		'blog_lang'            => get_locale(),
		'blog_charset'         => get_option( 'blog_charset' ),
	);
	if ( apply_filters( 'cptdp_akismet_is_test', false ) ) {
		$body['is_test'] = '1';
	}

	$response = wp_remote_post(
		'https://' . $key . '.rest.akismet.com/1.1/comment-check',
		array(
			'timeout'    => 5,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . ' | CPTDP/' . CPTDP_VERSION,
			'body'       => $body,
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return 'error';
	}

	$verdict = trim( wp_remote_retrieve_body( $response ) );
	if ( 'true' === $verdict ) {
		return 'spam';
	}

	return 'false' === $verdict ? 'ham' : 'error';
}

/**
 * Save a submission as an Interest.
 *
 * @param array<string,mixed> $clean  Output of cptdp_contact_validate() (incl. `slot_utc` and `timezone`), plus `ip` (cptdp_contact_client_ip()).
 * @param string              $status `publish` or `potential-spam`.
 * @param string              $check  The Akismet result, kept as `cptdp_spam_check`.
 * @return int|WP_Error Post ID.
 */
function cptdp_interest_create( array $clean, string $status, string $check ) {
	$status = 'potential-spam' === $status ? 'potential-spam' : 'publish';
	$now    = gmdate( 'Y-m-d H:i:s' ); // Stored in UTC (post_date_gmt); post_date is the same moment in the site zone.

	return wp_insert_post(
		wp_slash(
			array(
				'post_type'     => 'interest',
				'post_status'   => $status,
				'post_date_gmt' => $now,
				'post_date'     => get_date_from_gmt( $now ),
				'post_title'   => $clean['name'],
				'post_content' => $clean['message'],
				'meta_input'   => array(
					'cptdp_email'      => $clean['email'],
					'cptdp_ip'         => (string) ( $clean['ip'] ?? '' ),
					'cptdp_slot_utc'   => $clean['slot_utc'],
					'cptdp_visitor_local' => (string) ( $clean['local'] ?? '' ),
					'cptdp_visitor_tz' => (string) ( $clean['timezone'] ?? '' ),
					'cptdp_services'   => $clean['help'],
					'cptdp_spam_check' => $check,
				),
			)
		),
		true
	);
}

/**
 * The requested time as a UTC DateTimeImmutable, or null when the Interest has none.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_slot( int $post_id ): ?DateTimeImmutable {
	$raw  = (string) get_post_meta( $post_id, 'cptdp_slot_utc', true );
	$when = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s\Z', $raw, new DateTimeZone( 'UTC' ) );

	return $when ? $when : null;
}

/**
 * The visitor's stored time zone, or '' when it is missing or not a real zone.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_visitor_tz( int $post_id ): string {
	$zone = (string) get_post_meta( $post_id, 'cptdp_visitor_tz', true );

	return in_array( $zone, timezone_identifiers_list(), true ) ? $zone : '';
}

/**
 * The time as the visitor entered it, in their own zone ("Mon 14 Jan 2030, 15:30 (America/Los_Angeles)"), or '' when
 * the zone or the entry is missing.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_entered( int $post_id ): string {
	$zone  = cptdp_interest_visitor_tz( $post_id );
	$local = (string) get_post_meta( $post_id, 'cptdp_visitor_local', true );
	$when  = '' === $zone ? false : DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $local, new DateTimeZone( $zone ) );

	return $when ? $when->format( 'D j M Y, H:i' ) . ' (' . $zone . ')' : '';
}

/**
 * The requested time as one line for Dave: his site time zone, then the visitor's own entry when known.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_time_label( int $post_id ): string {
	$when = cptdp_interest_slot( $post_id );
	if ( ! $when ) {
		return '';
	}

	$label   = $when->setTimezone( wp_timezone() )->format( 'D j M Y, H:i' ) . ' (' . wp_timezone_string() . ')';
	$entered = cptdp_interest_entered( $post_id );
	if ( '' !== $entered ) {
		$label .= "; the visitor's time: " . $entered;
	}

	return $label;
}

/**
 * Email Dave one Interest and record whether it left (`cptdp_mail_sent`). No Contact address = no email, recorded as `no`.
 *
 * @param int $post_id Interest ID.
 * @return bool Whether the email was handed over.
 */
function cptdp_interest_notify( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'interest' !== $post->post_type ) {
		return false;
	}

	$to   = cptdp_get_contact( 'email' );
	$sent = false;

	if ( is_email( $to ) ) {
		$email = (string) get_post_meta( $post_id, 'cptdp_email', true );
		$help  = get_post_meta( $post_id, 'cptdp_services', true );
		$help  = is_array( $help ) ? $help : array();
		$label = cptdp_interest_time_label( $post_id );

		$lines = array(
			'Name: ' . $post->post_title,
			'Email: ' . $email,
			'Time: ' . $label,
			'Help with: ' . implode( ', ', $help ),
			'Message:',
			'' === trim( $post->post_content ) ? '(none)' : $post->post_content,
			'',
			'View: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		);

		$header_name = trim( (string) preg_replace( '/[^\p{L}\p{N} .\'-]/u', '', $post->post_title ) );
		$headers     = array( 'Reply-To: ' . ( '' === $header_name ? '' : $header_name . ' ' ) . '<' . $email . '>' );
		$subject     = sprintf(
			'[%s] Interest from %s',
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$post->post_title
		);

		$sent = (bool) wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}

	update_post_meta( $post_id, 'cptdp_mail_sent', $sent ? 'yes' : 'no' );

	return $sent;
}

/**
 * Dave says a potential spam item is genuine: make it visible and send the email that was held back.
 *
 * @param int $post_id Interest ID.
 * @return bool False when it is not a potential spam Interest.
 */
function cptdp_interest_mark_not_spam( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'interest' !== $post->post_type || 'potential-spam' !== $post->post_status ) {
		return false;
	}

	$result = wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		return false;
	}

	update_post_meta( $post_id, 'cptdp_spam_check', 'manual-ham' );
	cptdp_interest_notify( $post_id );

	return true;
}
