<?php
/**
 * Ownership of featured images the plugin makes itself (DPC-023, DPV-041).
 *
 * The plugin only ever replaces or deletes an image it made: the post meta `cptdp_featured_generated`
 * names it, and the attachment carries `_cptdp_generated_for` (the post) and `_cptdp_generated_from`
 * (where it came from: a page set folder, `file:<id>` or `embed:<url>`). An image an editor chose is
 * never touched. Used by CPTDP_Page_Featured (documents, collaterals) and the video autofill.
 *
 * It sits in includes/documents/ only because the loader requires that folder first.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates, marks, swaps and removes generated featured images.
 */
class CPTDP_Generated_Image {

	const META      = 'cptdp_featured_generated';
	const META_FOR  = '_cptdp_generated_for';
	const META_FROM = '_cptdp_generated_from';

	/**
	 * Attach the hooks (safe to call twice).
	 */
	public static function init(): void {
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete_attachment' ) );
	}

	/**
	 * The attachment the plugin made for a post, 0 when none (or when it no longer exists).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function generated_id( int $post_id ): int {
		$id = (int) get_post_meta( $post_id, self::META, true );

		return $id > 0 && 'attachment' === get_post_type( $id ) ? $id : 0;
	}

	/**
	 * Whose featured image a post shows: `generated`, `editor`, or '' when it has none.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function owner( int $post_id ): string {
		$thumb = (int) get_post_thumbnail_id( $post_id );

		if ( $thumb < 1 ) {
			return '';
		}

		return self::generated_id( $post_id ) === $thumb ? 'generated' : 'editor';
	}

	/**
	 * Where ours was made from, '' when there is none.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function source_of( int $post_id ): string {
		$ours = self::generated_id( $post_id );

		return $ours > 0 ? (string) get_post_meta( $ours, self::META_FROM, true ) : '';
	}

	/**
	 * Add a local file to Media, attached to the post (the file is copied; the caller keeps its own).
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $path     Readable image file.
	 * @param string $filename Name to give the copy, with extension.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function sideload( int $post_id, string $path, string $filename ) {
		// Cron, WP-CLI and REST do not load the admin media functions.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$post = get_post( $post_id );
		$tmp  = wp_tempnam( 'cptdp-generated' );

		if ( ! $post instanceof WP_Post || '' === $tmp || ! copy( $path, $tmp ) ) {
			return new WP_Error( 'cptdp_generated_copy', __( 'The image could not be copied.', 'custom-post-types-dp' ) );
		}

		$id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $filename ),
				'tmp_name' => $tmp,
			),
			$post_id,
			$post->post_title
		);

		if ( is_file( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		return $id;
	}

	/**
	 * Make a new attachment the post's generated featured image and drop the old generated one.
	 *
	 * The new image is in place first, so a page never shows a blank. Does nothing to an editor's image:
	 * the caller must check `owner()` before it makes an attachment.
	 *
	 * @param int    $post_id       Post ID.
	 * @param int    $attachment_id New attachment.
	 * @param string $from          Where it was made from.
	 */
	public static function adopt( int $post_id, int $attachment_id, string $from ): void {
		$old = self::generated_id( $post_id );

		update_post_meta( $attachment_id, self::META_FOR, $post_id );
		update_post_meta( $attachment_id, self::META_FROM, $from );
		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, self::META, $attachment_id );

		if ( $old > 0 && $old !== $attachment_id ) {
			wp_delete_attachment( $old, true );
		}
	}

	/**
	 * Put our existing image back as the featured image (the editor removed it, or a form undid it).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function restore( int $post_id ): void {
		$ours = self::generated_id( $post_id );

		if ( $ours > 0 && (int) get_post_thumbnail_id( $post_id ) < 1 ) {
			set_post_thumbnail( $post_id, $ours );
		}
	}

	/**
	 * Delete ours, always when the post is being deleted, otherwise only while it is still the featured image.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $deleting The post itself is being deleted.
	 */
	public static function remove( int $post_id, bool $deleting = false ): void {
		$ours = self::generated_id( $post_id );

		if ( $ours < 1 ) {
			delete_post_meta( $post_id, self::META );

			return;
		}

		if ( $deleting || (int) get_post_thumbnail_id( $post_id ) === $ours ) {
			self::delete( $post_id, $ours );
		}
	}

	/**
	 * An attachment is being deleted: when it is our image (an editor deleted it in Media), forget it.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function on_delete_attachment( $attachment_id ): void {
		$post_id = (int) get_post_meta( (int) $attachment_id, self::META_FOR, true );

		if ( $post_id > 0 && (int) get_post_meta( $post_id, self::META, true ) === (int) $attachment_id ) {
			delete_post_meta( $post_id, self::META );
		}
	}

	/**
	 * Forget and delete our attachment.
	 *
	 * @param int $post_id       Post ID.
	 * @param int $attachment_id Our attachment.
	 */
	public static function delete( int $post_id, int $attachment_id ): void {
		delete_post_meta( $post_id, self::META );
		wp_delete_attachment( $attachment_id, true );
	}
}
