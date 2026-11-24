<?php
/**
 * Admin sidebar order: Comments moves after Videos (menu order).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group post-types
 */
class CPTDP_Test_Menu_Order extends CPTDP_Test_Case {

	public function test_comments_moves_from_25_to_34_after_videos(): void {
		global $menu;
		$menu = array(
			20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page' ),
			25 => array( 'Comments', 'edit_posts', 'edit-comments.php' ),
			33 => array( 'Videos', 'edit_posts', 'edit.php?post_type=video' ),
		);

		cptdp_move_comments_after_videos();

		$this->assertArrayNotHasKey( 25, $menu );
		$this->assertSame( 'edit-comments.php', $menu[34][2] );
		$this->assertSame( 'edit.php?post_type=video', $menu[33][2] );
		$menu = null;
	}

	public function test_nothing_moves_when_comments_is_not_at_25(): void {
		global $menu;
		$menu = array( 25 => array( 'Other', 'edit_posts', 'other.php' ) );

		cptdp_move_comments_after_videos();

		$this->assertArrayHasKey( 25, $menu );
		$this->assertArrayNotHasKey( 34, $menu );
		$menu = null;
	}
}
