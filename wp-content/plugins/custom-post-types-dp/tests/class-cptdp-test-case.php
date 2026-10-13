<?php
/**
 * Base test case with shared helpers.
 *
 * @package CustomPostTypesDP
 */

/**
 * Helpers for creating users, attachments and submitting meta box forms.
 */
abstract class CPTDP_Test_Case extends WP_UnitTestCase {

	/**
	 * Re-register meta.
	 *
	 * WP_UnitTestCase unregisters all meta keys after every test, but the
	 * plugin registers them once on `init`, so run the registration again.
	 */
	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );
	}

	/**
	 * Reset request and rewrite globals touched by tests.
	 */
	public function tear_down(): void {
		global $wp_rewrite;

		$_POST = array();
		$_GET  = array();

		if ( '' !== (string) $wp_rewrite->permalink_structure ) {
			$this->set_permalink_structure( '' );
		}

		parent::tear_down();
	}

	/**
	 * Create a user with a role and make them the current user.
	 *
	 * @param string $role Role slug.
	 * @return int User ID.
	 */
	protected function acting_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Create an attachment with a mime type and a matching attached file.
	 *
	 * @param string $mime Mime type, e.g. video/mp4.
	 * @return int Attachment ID.
	 */
	protected function make_attachment( string $mime ): int {
		$extensions = array(
			'video/mp4'       => 'mp4',
			'audio/mpeg'      => 'mp3',
			'image/png'       => 'png',
			'application/pdf' => 'pdf',
		);

		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'post_title'     => 'file',
			)
		);

		update_post_meta( $id, '_wp_attached_file', 'file.' . ( $extensions[ $mime ] ?? 'bin' ) );

		return $id;
	}

	/**
	 * Submit a meta box form by calling a save handler directly.
	 *
	 * @param callable             $handler Save handler.
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  $_POST fields.
	 */
	protected function submit( callable $handler, int $post_id, array $fields ): void {
		$_POST = $fields;
		$handler( $post_id );
		$_POST = array();
	}

	/**
	 * Capture output of a callable.
	 *
	 * @param callable $callback Callback that echoes.
	 */
	protected function capture( callable $callback ): string {
		ob_start();
		$callback();

		return (string) ob_get_clean();
	}
}
