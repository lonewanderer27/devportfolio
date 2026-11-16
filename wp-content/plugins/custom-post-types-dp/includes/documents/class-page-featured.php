<?php
/**
 * Featured image made from page 1 (DPC-023, documents and collaterals).
 *
 * When a post's page images become ready and it has no featured image, page 1 is added to Media
 * and set as the featured image. The plugin only ever replaces or deletes an image it made itself:
 * the post meta `cptdp_featured_generated` names it, and the attachment carries
 * `_cptdp_generated_for` (the post) and `_cptdp_generated_from` (the page set folder it was made
 * from). An image an editor chose is never touched. Switch: Settings > Documents > Page images >
 * Featured image (`featured_from_page`, on by default).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates, swaps and removes the generated featured image.
 */
class CPTDP_Page_Featured {

	const META         = CPTDP_Generated_Image::META;
	const META_FOR     = CPTDP_Generated_Image::META_FOR;
	const META_FROM    = CPTDP_Generated_Image::META_FROM;
	const ERROR_PREFIX = 'Featured image: ';

	/**
	 * Attach the hooks.
	 */
	public static function init(): void {
		CPTDP_Generated_Image::init();
	}

	/**
	 * Whether the setting is on.
	 */
	public static function enabled(): bool {
		return (bool) cptdp_get_documents_settings( 'featured_from_page' );
	}

	/**
	 * The attachment the plugin made for a post, 0 when none (or when it no longer exists).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function generated_id( int $post_id ): int {
		return CPTDP_Generated_Image::generated_id( $post_id );
	}

	/**
	 * Whose featured image a post shows: `generated`, `editor`, or '' when it has none.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function owner( int $post_id ): string {
		return CPTDP_Generated_Image::owner( $post_id );
	}

	/**
	 * Make the featured image from page 1 when the post has none, or swap ours for the current set.
	 *
	 * Runs when a page set is saved (both producers) and from `wp cptdp pages featured`, never on a page view.
	 *
	 * @param int  $post_id Document or collateral ID.
	 * @param bool $force   Make ours again even when it was made from the current set.
	 * @return string `created`, `kept`, `skipped` or `failed`.
	 */
	public static function sync( int $post_id, bool $force = false ): string {
		if ( ! CPTDP_Page_Store::supports( $post_id ) || ! self::enabled() ) {
			return 'skipped';
		}

		$ours  = self::generated_id( $post_id );
		$thumb = (int) get_post_thumbnail_id( $post_id );

		// The editor chose an image: keep it, and drop ours if it is no longer used.
		if ( $thumb > 0 && $thumb !== $ours ) {
			if ( $ours > 0 ) {
				self::delete( $post_id, $ours );
			}

			return 'kept';
		}

		$manifest = null !== CPTDP_Page_Store::get_pages( $post_id ) ? CPTDP_Page_Store::get_manifest( $post_id ) : null;

		if ( null === $manifest ) {
			return 'skipped';
		}

		if ( $ours > 0 && ! $force && (string) get_post_meta( $ours, self::META_FROM, true ) === (string) $manifest['dir'] ) {
			return 'kept';
		}

		$created = self::create( $post_id, $manifest );

		if ( is_wp_error( $created ) ) {
			CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::get_status( $post_id ), self::ERROR_PREFIX . $created->get_error_message() );

			return 'failed';
		}

		CPTDP_Generated_Image::adopt( $post_id, $created, (string) $manifest['dir'] );

		return 'created';
	}

	/**
	 * Delete our image while it is still the featured image (the file was removed), or always when the post is deleted.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $deleting The post itself is being deleted.
	 */
	public static function remove( int $post_id, bool $deleting = false ): void {
		CPTDP_Generated_Image::remove( $post_id, $deleting );
	}

	/**
	 * An attachment is being deleted: when it is our image (an editor deleted it in Media), forget it,
	 * so the next set can make a new one.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function on_delete_attachment( $attachment_id ): void {
		CPTDP_Generated_Image::on_delete_attachment( $attachment_id );
	}

	/**
	 * Forget and delete our attachment.
	 *
	 * @param int $post_id       Post ID.
	 * @param int $attachment_id Our attachment.
	 */
	private static function delete( int $post_id, int $attachment_id ): void {
		CPTDP_Generated_Image::delete( $post_id, $attachment_id );
	}

	/**
	 * Add page 1 of a valid set to Media, attached to the post.
	 *
	 * The file is read from the validated set folder (never fetched by URL) and copied, so WordPress
	 * makes its usual sub-sizes from the copy. The bottom crop is not applied.
	 *
	 * @param int                 $post_id  Post ID.
	 * @param array<string,mixed> $manifest Valid manifest.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function create( int $post_id, array $manifest ) {
		$ext  = (string) $manifest['ext'];
		$page = CPTDP_Page_Store::base_dir() . '/' . (string) $manifest['dir'] . '/' . CPTDP_Page_Store::page_filename( 1, $ext );

		if ( ! is_file( $page ) ) {
			return new WP_Error( 'cptdp_featured_missing', __( 'Page 1 is missing.', 'custom-post-types-dp' ) );
		}

		$post = get_post( $post_id );
		$slug = $post instanceof WP_Post && '' !== $post->post_name ? $post->post_name : ( $post instanceof WP_Post ? sanitize_title( $post->post_title ) : '' );
		$name = ( '' !== $slug ? $slug : 'post-' . $post_id ) . '-page-1.' . $ext;

		return CPTDP_Generated_Image::sideload( $post_id, $page, $name );
	}
}
