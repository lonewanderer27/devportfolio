<?php
/**
 * Interests in wp-admin (DPB-017): the list, the read-only view, the badge, Potential spam. Follows
 * .claude/plans/backend/wordpress-admin-lofi-wireframe.html (A item 7, D4): Date, Name, Email, Service; no Add New, no
 * Publish, no Update. Trash is enabled (the user's decision). The actions are View and Move to Trash, Not spam in the
 * Potential spam tab, and core's Restore and Delete permanently in the Trash tab.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * List columns: a checkbox, Date, Name (the title, links to the view), Email, Service.
 *
 * @param array<string,string> $columns Default columns.
 * @return array<string,string>
 */
function cptdp_interest_columns( array $columns ): array {
	return array(
		'cb'             => '<input type="checkbox" />',
		'cptdp_received' => esc_html__( 'Date', 'custom-post-types-dp' ),
		'title'          => esc_html__( 'Name', 'custom-post-types-dp' ),
		'cptdp_email'    => esc_html__( 'Email', 'custom-post-types-dp' ),
		'cptdp_service'  => esc_html__( 'Service', 'custom-post-types-dp' ),
	);
}
add_filter( 'manage_interest_posts_columns', 'cptdp_interest_columns' );

/**
 * A moment as a `<time>` element: the UTC instant in `datetime` (what is stored), the site-zone text inside for when
 * scripts are off. assets/admin-interest.js rewrites the text in the viewer's own time zone. Returns escaped HTML.
 *
 * @param int $timestamp Unix timestamp.
 */
function cptdp_interest_time_tag( int $timestamp ): string {
	return sprintf(
		'<time datetime="%1$s" data-cptdp-local>%2$s</time>',
		esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) ),
		esc_html( wp_date( 'D j M Y, H:i', $timestamp ) . ' (' . wp_timezone_string() . ')' )
	);
}

/**
 * How long ago a moment was, in the largest whole unit: "30 seconds ago", "3 hours ago", "2 weeks ago".
 *
 * @param int $timestamp Unix timestamp.
 */
function cptdp_interest_ago( int $timestamp ): string {
	$seconds = max( 0, time() - $timestamp );

	if ( $seconds < MINUTE_IN_SECONDS ) {
		return sprintf( _n( '%d second ago', '%d seconds ago', $seconds, 'custom-post-types-dp' ), $seconds );
	}
	if ( $seconds < HOUR_IN_SECONDS ) {
		$n = intdiv( $seconds, MINUTE_IN_SECONDS );
		return sprintf( _n( '%d minute ago', '%d minutes ago', $n, 'custom-post-types-dp' ), $n );
	}
	if ( $seconds < DAY_IN_SECONDS ) {
		$n = intdiv( $seconds, HOUR_IN_SECONDS );
		return sprintf( _n( '%d hour ago', '%d hours ago', $n, 'custom-post-types-dp' ), $n );
	}
	if ( $seconds < WEEK_IN_SECONDS ) {
		$n = intdiv( $seconds, DAY_IN_SECONDS );
		return sprintf( _n( '%d day ago', '%d days ago', $n, 'custom-post-types-dp' ), $n );
	}
	if ( $seconds < MONTH_IN_SECONDS ) {
		$n = intdiv( $seconds, WEEK_IN_SECONDS );
		return sprintf( _n( '%d week ago', '%d weeks ago', $n, 'custom-post-types-dp' ), $n );
	}
	if ( $seconds < YEAR_IN_SECONDS ) {
		$n = intdiv( $seconds, MONTH_IN_SECONDS );
		return sprintf( _n( '%d month ago', '%d months ago', $n, 'custom-post-types-dp' ), $n );
	}

	$n = intdiv( $seconds, YEAR_IN_SECONDS );
	return sprintf( _n( '%d year ago', '%d years ago', $n, 'custom-post-types-dp' ), $n );
}

/**
 * Load the script that shows the times in the viewer's own time zone on the Interests screens.
 */
function cptdp_interest_admin_assets(): void {
	$screen = get_current_screen();
	if ( $screen && 'interest' === $screen->post_type ) {
		wp_enqueue_script( 'cptdp-admin-interest', CPTDP_URL . 'assets/admin-interest.js', array(), CPTDP_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'cptdp_interest_admin_assets' );

/**
 * Cell content for Date, Email and Service.
 *
 * @param string $column  Column key.
 * @param int    $post_id Interest ID.
 */
function cptdp_interest_column( string $column, int $post_id ): void {
	if ( 'cptdp_received' === $column ) {
		echo cptdp_interest_time_tag( (int) get_post_time( 'U', true, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cptdp_interest_time_tag().
	}
	if ( 'cptdp_email' === $column ) {
		echo esc_html( (string) get_post_meta( $post_id, 'cptdp_email', true ) );
	}
	if ( 'cptdp_service' === $column ) {
		$help = get_post_meta( $post_id, 'cptdp_services', true );
		echo esc_html( is_array( $help ) ? implode( ', ', $help ) : '' );
	}
}
add_action( 'manage_interest_posts_custom_column', 'cptdp_interest_column', 10, 2 );

/**
 * The link of the "Not spam" action (admin-post, with a nonce per item).
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_not_spam_url( int $post_id ): string {
	return wp_nonce_url( admin_url( 'admin-post.php?action=cptdp_interest_not_spam&post=' . $post_id ), 'cptdp_not_spam_' . $post_id );
}

/**
 * Row actions: View and Move to Trash; in the Potential spam tab also Not spam; in the Trash tab core's Restore and
 * Delete permanently. No Edit or Quick Edit.
 *
 * @param array<string,string> $actions Default actions.
 * @param WP_Post              $post    The row.
 * @return array<string,string>
 */
function cptdp_interest_row_actions( array $actions, $post ): array {
	if ( 'interest' !== $post->post_type ) {
		return $actions;
	}

	if ( 'trash' === $post->post_status ) {
		return array_intersect_key( $actions, array_flip( array( 'untrash', 'delete' ) ) );
	}

	$out = array(
		'view' => sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post->ID ) ), esc_html__( 'View', 'custom-post-types-dp' ) ),
	);

	if ( 'potential-spam' === $post->post_status ) {
		$out['notspam'] = sprintf( '<a href="%s">%s</a>', esc_url( cptdp_interest_not_spam_url( $post->ID ) ), esc_html__( 'Not spam', 'custom-post-types-dp' ) );
	}
	if ( isset( $actions['trash'] ) ) {
		$out['trash'] = $actions['trash'];
	}

	return $out;
}
add_filter( 'post_row_actions', 'cptdp_interest_row_actions', 10, 2 );

/**
 * Bulk actions: core's, without Edit (nothing here can be edited).
 *
 * @param array<string,string> $actions Default actions.
 * @return array<string,string>
 */
function cptdp_interest_bulk_actions( array $actions ): array {
	unset( $actions['edit'] );

	return $actions;
}
add_filter( 'bulk_actions-edit-interest', 'cptdp_interest_bulk_actions' );

/**
 * Status tabs: "All (n) | Potential spam (n)" (no "Published" tab).
 *
 * @param array<string,string> $views Default views.
 * @return array<string,string>
 */
function cptdp_interest_views( array $views ): array {
	unset( $views['publish'] );

	return $views;
}
add_filter( 'views_edit-interest', 'cptdp_interest_views' );

/**
 * The Not spam link.
 */
function cptdp_interest_handle_not_spam(): void {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	check_admin_referer( 'cptdp_not_spam_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'custom-post-types-dp' ), '', array( 'response' => 403 ) );
	}

	cptdp_interest_mark_not_spam( $post_id );
	wp_safe_redirect( admin_url( 'edit.php?post_type=interest' ) );
	exit;
}
add_action( 'admin_post_cptdp_interest_not_spam', 'cptdp_interest_handle_not_spam' );

/**
 * Submissions not opened yet (visible ones only; potential spam never counts).
 */
function cptdp_interest_unseen_count(): int {
	$query = new WP_Query(
		array(
			'post_type'      => 'interest',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'cptdp_seen',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	return (int) $query->found_posts;
}

/**
 * The count badge on the sidebar item.
 */
function cptdp_interest_menu_badge(): void {
	global $menu;

	$count = cptdp_interest_unseen_count();
	if ( $count < 1 || ! is_array( $menu ) ) {
		return;
	}

	foreach ( $menu as $position => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=interest' === $item[2] ) {
			$menu[ $position ][0] .= ' <span class="awaiting-mod">' . (int) $count . '</span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
add_action( 'admin_menu', 'cptdp_interest_menu_badge', 99 );

/**
 * Opening a visible Interest marks it seen.
 */
function cptdp_interest_mark_seen_on_open(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only records that it was opened.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( $post_id && 'interest' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
		update_post_meta( $post_id, 'cptdp_seen', '1' );
	}
}
add_action( 'load-post.php', 'cptdp_interest_mark_seen_on_open' );

/**
 * Replace the edit screen's boxes with the one read-only box (no Publish box, no Update).
 */
function cptdp_interest_add_box(): void {
	remove_meta_box( 'submitdiv', 'interest', 'side' );
	add_meta_box( 'cptdp_interest_view', esc_html__( 'Submission', 'custom-post-types-dp' ), 'cptdp_interest_render_box', 'interest', 'normal', 'high' );
}
add_action( 'add_meta_boxes_interest', 'cptdp_interest_add_box', 99 );

/**
 * One labelled fact of the view: a term and its (already escaped) value.
 *
 * @param string $label Label, plain text.
 * @param string $html  Value, escaped HTML.
 */
function cptdp_interest_fact( string $label, string $html ): string {
	return '<div class="cptdp-fact"><dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
}

/**
 * The read-only view. The facts an admin reads first: when it was sent, who, what for, the message and the requested
 * time in both zones. Spam check, email notification and IP address sit under "Details", closed by default.
 *
 * @param WP_Post $post The Interest.
 */
function cptdp_interest_render_box( $post ): void {
	$help     = get_post_meta( $post->ID, 'cptdp_services', true );
	$slot     = cptdp_interest_slot( $post->ID );
	$entered  = cptdp_interest_entered( $post->ID );
	$checks   = array(
		'ham'        => __( 'Not spam (Akismet)', 'custom-post-types-dp' ),
		'manual-ham' => __( 'Marked not spam by you', 'custom-post-types-dp' ),
		'spam'       => __( 'Potential spam (Akismet)', 'custom-post-types-dp' ),
		'skipped'    => __( 'Not checked (no Akismet key)', 'custom-post-types-dp' ),
		'error'      => __( 'Not checked (Akismet could not be reached)', 'custom-post-types-dp' ),
	);
	$check    = (string) get_post_meta( $post->ID, 'cptdp_spam_check', true );
	$mail     = (string) get_post_meta( $post->ID, 'cptdp_mail_sent', true );
	$services = is_array( $help ) ? implode( ', ', $help ) : '';
	$message  = trim( (string) $post->post_content );

	echo '<style>.cptdp-facts{display:grid;gap:14px;margin:0}.cptdp-fact dt{margin:0 0 3px;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#666}.cptdp-fact dd{margin:0;font-size:14px}.cptdp-none{color:#8a8a8a;font-style:italic}.cptdp-more{margin-top:22px;padding-top:14px;border-top:1px solid #c9c9c9}.cptdp-more summary{cursor:pointer;font-weight:600}.cptdp-more .cptdp-facts{margin-top:14px}.cptdp-badge{display:inline-block;padding:2px 8px;border:1px solid #222;border-radius:9px;font-size:11px}.cptdp-ago{position:relative;display:inline-block;border-bottom:1px dotted #8a8a8a;cursor:help}.cptdp-ago:focus{outline:2px solid #2271b1;outline-offset:2px}.cptdp-ago__when{display:none;position:absolute;left:0;top:calc(100% + 6px);z-index:10;padding:6px 10px;border-radius:6px;background:#1d2327;color:#fff;font-size:12px;white-space:nowrap}.cptdp-ago:hover .cptdp-ago__when,.cptdp-ago:focus .cptdp-ago__when{display:block}.cptdp-ago__when time{color:#fff}</style>';
	echo '<p><span class="cptdp-badge">🔒 ' . esc_html__( 'Read-only', 'custom-post-types-dp' ) . '</span></p>';

	if ( 'potential-spam' === $post->post_status ) {
		echo '<p><a class="button button-primary" href="' . esc_url( cptdp_interest_not_spam_url( $post->ID ) ) . '">' . esc_html__( 'Not spam', 'custom-post-types-dp' ) . '</a> <span class="description">' . esc_html__( 'Makes it visible in Interests and sends the email.', 'custom-post-types-dp' ) . '</span></p>';
	}

	$requested = $slot ? cptdp_interest_time_tag( $slot->getTimestamp() ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cptdp_interest_time_tag().

	echo '<dl class="cptdp-facts">';
	$submitted = (int) get_post_time( 'U', true, $post );
	$tip_id    = 'cptdp-when-' . $post->ID;
	echo cptdp_interest_fact( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		__( 'Submitted', 'custom-post-types-dp' ),
		'<span class="cptdp-ago" tabindex="0" aria-describedby="' . esc_attr( $tip_id ) . '">' . esc_html( cptdp_interest_ago( $submitted ) ) . '<span class="cptdp-ago__when" id="' . esc_attr( $tip_id ) . '" role="tooltip">' . cptdp_interest_time_tag( $submitted ) . '</span></span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cptdp_interest_time_tag().
	);
	echo cptdp_interest_fact( __( 'Name', 'custom-post-types-dp' ), esc_html( $post->post_title ) );
	echo cptdp_interest_fact( __( 'Email', 'custom-post-types-dp' ), esc_html( (string) get_post_meta( $post->ID, 'cptdp_email', true ) ) );
	echo cptdp_interest_fact( __( 'Service', 'custom-post-types-dp' ), esc_html( $services ) );
	echo cptdp_interest_fact( __( 'Message', 'custom-post-types-dp' ), '' === $message ? '<span class="cptdp-none">' . esc_html__( 'No message', 'custom-post-types-dp' ) . '</span>' : nl2br( esc_html( $message ) ) );
	echo cptdp_interest_fact( __( 'Requested Time', 'custom-post-types-dp' ), $requested ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	echo '</dl>';

	echo '<details class="cptdp-more"><summary>' . esc_html__( 'Details: spam check, email and IP address', 'custom-post-types-dp' ) . '</summary>';
	echo '<dl class="cptdp-facts">';
	if ( '' !== $entered ) {
		echo cptdp_interest_fact( __( 'Requested Time (visitor\'s time zone)', 'custom-post-types-dp' ), esc_html( $entered ) );
	}
	echo cptdp_interest_fact( __( 'Spam check', 'custom-post-types-dp' ), esc_html( $checks[ $check ] ?? '' ) );
	echo cptdp_interest_fact( __( 'Email notification', 'custom-post-types-dp' ), esc_html( 'yes' === $mail ? __( 'Sent', 'custom-post-types-dp' ) : ( 'no' === $mail ? __( 'Not sent', 'custom-post-types-dp' ) : '' ) ) );
	echo cptdp_interest_fact( __( 'IP address', 'custom-post-types-dp' ), esc_html( (string) get_post_meta( $post->ID, 'cptdp_ip', true ) ) );
	echo '</dl></details>';

	$trash = 'trash' === $post->post_status ? '' : get_delete_post_link( $post->ID );
	if ( $trash ) {
		echo '<p><a class="submitdelete" href="' . esc_url( $trash ) . '">' . esc_html__( 'Move to Trash', 'custom-post-types-dp' ) . '</a></p>';
	}
}

/**
 * A warning on the Interests list while there is no Akismet key (nothing is being checked).
 */
function cptdp_interest_notices(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-interest' !== $screen->id || '' !== cptdp_akismet_key() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Spam check is off: no Akismet key was found. New submissions are shown and emailed without being checked. Install Akismet and connect it to turn the check on.', 'custom-post-types-dp' ) . '</p></div>';
}
add_action( 'admin_notices', 'cptdp_interest_notices' );
