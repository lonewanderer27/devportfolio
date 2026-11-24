<?php
/**
 * Admin sidebar order (DPB-017 and the Contact scheduler): WordPress's Comments item sits after the portfolio items,
 * as the last item before Interests. Custom post types take whole-number positions (core ignores fractions and gives
 * them the next free slot), so every type in the sidebar has its own whole number.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Move core's Comments item (position 25) to position 34, right after Videos (33).
 */
function cptdp_move_comments_after_videos(): void {
	global $menu;

	if ( ! is_array( $menu ) || ! isset( $menu[25] ) || 'edit-comments.php' !== ( $menu[25][2] ?? '' ) ) {
		return;
	}

	$menu[34] = $menu[25];
	unset( $menu[25] );
}
add_action( 'admin_menu', 'cptdp_move_comments_after_videos', 999 );
