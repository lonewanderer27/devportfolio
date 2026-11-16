# Plan: Video autofill, the thumbnail, length, aspect ratio and 6 second preview clip are filled in for Dave

Ticket **DPV-041** (Backend / Video data - Autofill). Branch `feat/DPV-041-Video-Autofill`, created **from `main`**. Plugin bump `0.28.0` -> `0.29.0`.

**Status (2026-11-17, final):** everything below is implemented, browser-checked where noted, and committed in the order of section 12.3. Totals: plugin 906 tests (12 skipped), theme 523. Version 0.30.0.

**Earlier status:** sections 1-8 below were implemented and are green (plugin 886 tests, theme 523), with the additions listed in section 10 (aspect ratio, `wp_tempnam` fix, wording). **Section 11 (the 6 second preview clip, D10-D17) was approved and implemented 2026-11-17 (Phases 6-8, version 0.30.0, plugin 894 tests).** Browser-checked in headless Chrome: path B (6 s, 406x720 H.264, no audio, 216 KB from a 1080x1920 10 s source), the MediaRecorder fallback (VideoEncoder removed), the thumbnail-then-clip chain, and a preview set by someone else being left alone. Not checked: Safari and Firefox, a `.mov`, the media-picker click path (same code as the load path).

**How to read this plan (for the implementing agent):** do the phases in section 6 **in order**; after each phase run its **Gate** and only continue when it passes. Do not "improve" anything that is not listed. If something you find does not match what this plan says (a function is missing, a test already exists), **stop and report it** instead of guessing. Do not commit or push unless the user asks; section 6A lists the commits to make *when asked*.

Paths are relative to the repo root `/Users/adriane.puzon/Documents/PHP Projects/devportfolio` unless they start with `$P`:

- `$P` = `wp-content/plugins/custom-post-types-dp`

Related (history only, do not edit): `backend/DPB-006-011-018-Video.md` (the `video` CPT), `backend/DPB-024-seed-videos.md` (seeder), and the code this plan copies the idea from: `$P/includes/documents/class-page-featured.php` (DPC-023, the featured image made from page 1) and `$P/includes/documents/class-page-rest.php` (the admin-only upload route). The user story is the DPV-041 row of `Dave Portfolio _ User Stories - Videos (Revised).csv`.

---

## 1. Goal and acceptance criteria

When Dave saves a `video` in wp-admin, the thumbnail and the length are filled in for him. Nothing runs for a visitor.

1. **Length from a file.** Saving a video that has an uploaded file and an empty Length sets `cptdp_length` to the file's duration in whole seconds. The Videos list shows it as `0:28`.
2. **Length from Vimeo.** Saving a video with only a Vimeo embed URL and an empty Length sets it from the Vimeo oEmbed `duration`. A **YouTube** embed leaves it empty (decision D1); no notice.
3. **Length from the file wins** when a file and an embed URL both exist.
4. **Thumbnail from a file.** Choosing a video file in the media picker draws a frame (about 1 s in) in the admin's browser, uploads it to a new admin-only route, and sets it as the featured image **when the video has no image Dave chose**. The Thumbnail box shows it straight away.
5. **Thumbnail from an embed.** On save, with no uploaded file's frame and no image Dave chose, the server sets the provider's thumbnail (YouTube or Vimeo) as the featured image.
6. **A value Dave typed or chose is never replaced or deleted**: a Length that differs from the one the system wrote, and a featured image the plugin did not make. Only what the system generated is refreshed when the file or the embed URL changes, and deleted when the source is removed.
7. Length field and Thumbnail box stay editable. Removing the thumbnail and saving puts the generated one back (it was ours, DPV-041 line "made again on the next save only if I did not choose it"); a different image Dave picks replaces it for good.
8. **Failure never blocks the save.** Provider unreachable, unsupported file, no thumbnail available: the video saves, a one-time notice on the edit screen says what could not be filled, the fields stay as they were.
9. **Admin only.** The code runs only on the wp-admin form save (the nonce path of `cptdp_video_save_meta()`) and in the new route. It does **not** run for the seeder, WP-CLI, REST meta updates, `wp_insert_post()` from code, or any page view. `/videos/` makes no request to YouTube or Vimeo.
10. A filter `cptdp_video_autofill` (default `true`) switches the server side off for hosts that block outbound requests.
11. Plugin suite green, Documents and Collaterals tests **unchanged and green** (one count in `test-page-rest.php` moves, see section 10). Nothing in the theme changes.
12. **Aspect ratio (as built).** Saving a video with an uploaded file, or a Vimeo embed, sets the `aspect_ratio` term to the nearest of 16x9, 9x16, 1x1, 4x3 (more than 6% off all four: left alone); a YouTube link counts as 9x16 only when the embed or post URL has `youtube.com/shorts/`. A ratio Dave picked is kept.
13. **Preview clip (NEW, section 11).** Choosing (or saving) a video file makes a muted 6 second MP4 clip of its start in the admin's browser (WebCodecs through the vendored Mediabunny; fallback MediaRecorder), uploads it to an admin-only route and stores it in the new meta `cptdp_video_preview`. Progress is shown in the Video File section while Dave fills in the rest of the form. Only uploaded files get a preview (an embed cannot).
14. A preview Dave set himself (any `cptdp_video_preview` the plugin did not make) is never replaced or deleted. Ours is replaced when the file changes and deleted when the file is removed or the video deleted.
15. Failure of the preview never blocks the save and never touches the thumbnail.

## 2. Out of scope (do NOT build, even if it looks easy)

- YouTube Data API or any API key (D1). No new setting, no new option, no new dependency.
- ffmpeg or any server-side frame grab or clip encode (the server has none; frames and the preview clip are made in the browser).
- Any change to the theme (the grid and modal read the featured image and `cptdp_length` exactly as today).
- A bulk backfill WP-CLI command for existing videos (a video is filled the next time it is saved in wp-admin; say so in the final report, offer it as follow-up).
- The front end that plays the preview (hover, modal), captions, and a media picker for Dave's own preview file (DPV-040 and the other story that consumes `cptdp_video_preview`). This ticket only makes and stores the clip.
- Changing `cptdp_video_sanitize_*`, the other registered meta, the columns, or the seeder. (The one exception: the new meta `cptdp_video_preview`, section 11.)
- Deleting the old browser-side code paths of Documents. Documents and Collaterals behaviour must not change (Phase 1 only moves code).

## 3. Facts already established (do not re-derive; just trust them)

- Plugin `0.28.0` (header line 5 and `define( 'CPTDP_VERSION', '0.28.0' )` in `$P/custom-post-types-dp.php`).
- `$P/includes/post-types/video.php`: `cptdp_video_save_meta( int $post_id )` is hooked on `save_post_video`; it returns early without the `cptdp_video_nonce`, without `edit_post`, and on autosave. It writes `cptdp_video_file` (via `cptdp_video_sanitize_file`, an attachment ID that must be a video), `cptdp_embed_url`, `cptdp_post_url`, `cptdp_length` (`absint`, `> 0` stored else deleted), tools, statistics, highlight. The admin script `$P/assets/admin-video.js` (69 lines) is enqueued on `post.php`/`post-new.php` for `video` with `cptdpVideo` = `{ selectTitle, selectButton }`; the media picker sets `#cptdp_video_file`. The Length input is `#cptdp_length`. `supports` already has `thumbnail`; the label is "Thumbnail".
- The classic editor posts `_thumbnail_id` (`-1` for none). WordPress applies it inside `wp_insert_post()` **before** `save_post` fires, so at our hook the featured image is already what the form said. Therefore the browser must call `wp.media.featuredImage.set( id )` after it uploads, or the form's old value would undo the new image on the next Update.
- The seeded embed URLs are `https://www.youtube.com/embed/<11 chars>` and `https://player.vimeo.com/video/<digits>` (`$P/data/seeders/videos.csv`; the `post_url` column has the `youtube.com/shorts/<id>` and `vimeo.com/<id>` forms).
- Core: `WP_oEmbed` knows YouTube (watch, shorts, youtu.be, live, playlist) and Vimeo (`vimeo.com/...`) but **not** the `/embed/<id>` and `player.vimeo.com/video/<id>` forms we store; `_wp_oembed_get_object()->get_data( $url, $args )` returns the decoded provider object or `false` and fetches through `wp_safe_remote_get` (`oembed_remote_get_args` and `pre_http_request` apply). Vimeo's response has `thumbnail_url` and `duration`; YouTube's has a `thumbnail_url` (the 4:3 `hqdefault`, black bars on a 9:16 short) and no duration. `download_url( $url, $timeout )` fetches with `wp_safe_remote_get` (private addresses refused). `wp_read_video_metadata( $path )` (`wp-admin/includes/media.php`) returns `length` in seconds and core stores it in the attachment metadata on upload.
- `CPTDP_Page_Featured` (`$P/includes/documents/class-page-featured.php`, 221 lines) owns the rule "never touch an image an editor chose": post meta `cptdp_featured_generated` names ours, the attachment carries `_cptdp_generated_for` and `_cptdp_generated_from`. Its `sync()` and `create()` are Document specific (they read the page manifest and a setting), its `generated_id()`, `owner()`, `delete()`, `on_delete_attachment()` and the sideload are not.
- `CPTDP_Page_REST` (`$P/includes/documents/class-page-rest.php`) already has the constants `MAX_BYTES` (2 MB), `MAX_DIMENSION` (3000), `ALLOWED_MIMES` (webp, jpeg) and a **private** `inspect( string $body )` that validates raw image bytes. The loader (`$P/includes/class-loader.php`) requires every file in `includes/documents/` first, then `post-types/`, `settings/`, `seeders/`; there is no other class directory.
- Test base `CPTDP_Test_Case` (`$P/tests/class-cptdp-test-case.php`) re-runs `cptdp_register_post_types` in `set_up()`, has `acting_as( $role )`, `make_attachment( $mime )`, `submit( $handler, $id, $fields )`. `tests/post-types/test-video.php` extends `CPTDP_Media_CPT_Test_Case` and saves forms through `save( $id, $fields )`. The pages test case has `image_bytes( $format, $w, $h )`; the new test should not extend it (it pulls in conversion tooling), so it builds a JPEG with GD itself.

## 4. Decisions

- **D1 YouTube length stays empty** (the story's recommendation): no API key. The Length field gets one description line saying so.
- **D2 Where the frame is drawn:** the admin's browser (hidden `<video>`, canvas, same origin), uploaded to `POST cptdp/v1/videos/<id>/poster`. If the canvas is tainted (uploads on another origin), the codec is unsupported or the browser cannot seek, the script does nothing visible except an inline note, and the server falls back to the embed thumbnail when there is one.
- **D2a Target host is WordPress shared hosting, not a VPS** (confirmed by the user): no ffmpeg, no shell tools, no long-running jobs, no way to install packages. So the design uses **only** what a stock WordPress on PHP provides: the browser draws the frame, PHP reads the duration with core's bundled getID3 (`wp_read_video_metadata()`, pure PHP), and outbound calls use core's HTTP API (`wp_safe_remote_get`). Do **not** add an ffmpeg, `exec`, `proc_open` or cron dependency for this ticket, and do not add an "ffmpeg first, browser second" tier.
- **D3 YouTube thumbnail source:** the server tries `https://i.ytimg.com/vi/<id>/maxresdefault.jpg`, then `.../hqdefault.jpg` directly, **not** oEmbed. This is a deliberate change from the story's build note ("through oEmbed"): the oEmbed thumbnail is the letterboxed 4:3 image, wrong for the portrait grid, and the direct URL needs no oEmbed call. Vimeo still uses oEmbed because it also gives the duration. **Verify in Phase 5** on the three seeded YouTube shorts; if `maxresdefault` is also letterboxed, report it to the user before changing anything.
- **D4 One ownership rule, not two:** move the type-independent part of `CPTDP_Page_Featured` into a new `CPTDP_Generated_Image` and make Page_Featured use it (same meta keys, same public members, so nothing is migrated and its tests stay unchanged). It lives in `includes/documents/` only because that is the one folder the loader requires first.
- **D5 Source key.** Every generated thing remembers where it came from as a string: `file:<attachment id>` or `embed:<canonical provider URL>`. A change of source is a change of key. A video with a file always prefers `file:`.
- **D6 Length ownership:** post meta `cptdp_length_auto` (not in REST) = the number the system wrote, `cptdp_length_source` = its source key. A stored length that equals `cptdp_length_auto` is ours; anything else is Dave's.
- **D7 Admin form only:** the autofill is called at the end of `cptdp_video_save_meta()`, after the nonce, capability and autosave checks. That is what keeps the seeder, WP-CLI and REST untouched.
- **D8 Self-healing thumbnail:** on save, if the featured image is empty and an image of ours made from the **current** source exists, it is set again (covers "Dave removed it", and the race where the browser finished uploading after he clicked Update).
- **D9 Stale generated image:** if ours was made from a source that no longer exists and nothing can replace it right now, delete ours (better blank than the wrong video). If the browser is going to replace it (file source), the old one stays until the new one arrives.

- **D10 (new) Preview clip:** the first 6 seconds of the file (the whole file when shorter), no audio track, H.264 in an MP4, longest side at most 720 px (even width and height), about 1 Mbps. Made in the admin's browser, never on the server (D2a).
- **D11 (new) Primary and fallback encoder:** primary = **Mediabunny** (WebCodecs decode, trim, scale, H.264 encode, MP4 mux). Fallback = native `MediaRecorder` on a canvas fed by a hidden muted `<video>` (real time, about 6 s, output WebM or MP4 depending on `MediaRecorder.isTypeSupported`). If both fail: inline note, nothing saved.
- **D12 (new) Library:** vendor one pinned browser bundle of `mediabunny@1.66.0` (MPL-2.0, maintained: last release 2026-11-13) in `$P/assets/vendor/mediabunny/` with its LICENSE and a README (version, source, how to update), like `assets/vendor/pdfjs/`. No npm build step in the plugin. It is only enqueued on the Video edit screen. `mp4-muxer` and `webm-muxer` are deprecated in favour of it; `ffmpeg.wasm` is rejected (about 30 MB).
- **D13 (new) Storage and ownership:** the clip is a normal attachment (`video/mp4` or `video/webm`) attached to the video and referenced by the REST-visible meta `cptdp_video_preview` (attachment ID). What we made is recorded in the non-REST post meta `cptdp_video_preview_generated`; the attachment carries `_cptdp_generated_for` and `_cptdp_generated_from` = `file:<video file id>` (the same attachment keys `CPTDP_Generated_Image` uses; the post meta key differs, so the featured image rule is untouched). A stored preview that is not ours is Dave's (or another ticket's) and is never replaced or deleted.
- **D14 (new) Background feel:** "asynchronous" means the browser encodes while Dave keeps editing the form; the Video File section shows a status line and a `<progress>` bar. It is **not** a server job (D2a). Leaving the page cancels it (a `beforeunload` warning shows only while it runs) and the edit screen starts it again on the next load while the preview is missing or from an old file.
- **D15 (new) Order:** the thumbnail frame (fast) runs first, then the preview clip; never both at once (one `running` guard).
- **D16 (new) Server side on save:** nothing is generated on the server. `cptdp_video_autofill()` only removes ours when the video no longer has a file. A file change keeps the old clip until the browser's new one replaces it (same idea as D9).
- **D17 (new) Aspect ratio ownership (as built):** post meta `cptdp_ratio_auto` and `cptdp_ratio_source` (not in REST). With a record, only a term equal to ours is replaced; without one, only no term or the unchosen default 16x9 is replaced.

## 5. Exact file contents

### 5.1 New `$P/includes/documents/class-generated-image.php`

```php
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
```

### 5.2 Edit `$P/includes/documents/class-page-featured.php` (make it use the shared class)

Behaviour and public API stay identical. Before editing, run `grep -rn "CPTDP_Page_Featured::" $P/includes $P/tests` and keep **every** member found there callable.

- Constants become aliases: `const META = CPTDP_Generated_Image::META;` `const META_FOR = CPTDP_Generated_Image::META_FOR;` `const META_FROM = CPTDP_Generated_Image::META_FROM;` (keep `ERROR_PREFIX`).
- `init()` body: `CPTDP_Generated_Image::init();` (replaces its own `add_action`).
- `generated_id()`, `owner()`, `on_delete_attachment()`, `remove()` and the private `delete()` become one-line delegates to the same-named `CPTDP_Generated_Image` method (keep them `public`/`private` as they are now; `delete` stays private).
- In `sync()`: replace the two blocks that set the thumbnail and delete the old image
  ```php
  set_post_thumbnail( $post_id, $created );
  update_post_meta( $post_id, self::META, $created );

  // The new image is in place first, so the shelf never shows a blank.
  if ( $ours > 0 ) {
      wp_delete_attachment( $ours, true );
  }
  ```
  with `CPTDP_Generated_Image::adopt( $post_id, $created, (string) $manifest['dir'] );`. (`adopt()` writes `META_FOR`/`META_FROM` itself, so `create()` stops writing them.)
- In `create()`: replace everything from `// Cron and WP-CLI do not load...` to the end with: build `$name` the same way, then `return CPTDP_Generated_Image::sideload( $post_id, $page, $name );`. Keep the "Page 1 is missing" check at the top.

### 5.3 New `$P/includes/post-types/video-autofill.php`

One module, functions only, like its neighbours. Requires nothing at load time (the class files are already loaded).

```php
<?php
/**
 * Video autofill (DPV-041): thumbnail and length are filled in when a video is saved in wp-admin.
 *
 * Runs only from the Video edit form (see cptdp_video_save_meta()) and from the poster route below, never
 * for a visitor, the seeder, WP-CLI or a REST meta update. Anything Dave typed or chose is left alone.
 *
 * Source keys: `file:<attachment id>` or `embed:<canonical provider url>`. Meta (not in REST):
 * `cptdp_length_auto` (the length the system wrote), `cptdp_length_source` (its source key).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

CPTDP_Generated_Image::init();

/**
 * Whether the server side may call out to YouTube and Vimeo.
 */
function cptdp_video_autofill_enabled(): bool {
	return (bool) apply_filters( 'cptdp_video_autofill', true );
}

/**
 * Provider, id and the URL the provider answers to, for a stored embed URL. Empty array when it is neither.
 *
 * Accepts the forms we store (`youtube.com/embed/<id>`, `player.vimeo.com/video/<id>`) and the usual
 * watch, shorts, youtu.be and vimeo.com/<id> forms.
 *
 * @param string $embed Embed or post URL.
 * @return array{provider:string,id:string,url:string}|array{}
 */
function cptdp_video_provider( string $embed ): array {
	$parts = wp_parse_url( trim( $embed ) );

	if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
		return array();
	}

	$host  = strtolower( (string) preg_replace( '/^www\./i', '', (string) ( $parts['host'] ?? '' ) ) );
	$path  = (string) ( $parts['path'] ?? '' );
	$query = array();
	wp_parse_str( (string) ( $parts['query'] ?? '' ), $query );
	$id = '';

	if ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com' ), true ) ) {
		$id = (string) ( $query['v'] ?? '' );

		if ( preg_match( '#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $m ) ) {
			$id = $m[1];
		}
	} elseif ( 'youtu.be' === $host && preg_match( '#^/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $m ) ) {
		$id = $m[1];
	}

	if ( 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
		return array(
			'provider' => 'youtube',
			'id'       => $id,
			'url'      => 'https://www.youtube.com/watch?v=' . $id,
		);
	}

	if ( 'player.vimeo.com' === $host && preg_match( '#^/video/(\d+)#', $path, $m ) ) {
		$id = $m[1];
	} elseif ( 'vimeo.com' === $host && preg_match( '#^/(?:[^/]+/)*(\d+)(?:/|$)#', $path, $m ) ) {
		$id = $m[1];
	}

	return '' !== $id && ctype_digit( $id )
		? array(
			'provider' => 'vimeo',
			'id'       => $id,
			'url'      => 'https://vimeo.com/' . $id,
		)
		: array();
}

/**
 * The video's current source: its uploaded file, else its embed, else none.
 *
 * @param int $post_id Video ID.
 * @return array{key:string,file:int,embed:array<string,string>}
 */
function cptdp_video_source( int $post_id ): array {
	$file  = (int) get_post_meta( $post_id, 'cptdp_video_file', true );
	$file  = $file > 0 && 'attachment' === get_post_type( $file ) ? $file : 0;
	$embed = cptdp_video_provider( (string) get_post_meta( $post_id, 'cptdp_embed_url', true ) );
	$key   = $file > 0 ? 'file:' . $file : ( $embed ? 'embed:' . $embed['url'] : '' );

	return array(
		'key'   => $key,
		'file'  => $file,
		'embed' => $embed,
	);
}

/**
 * Duration in whole seconds of an uploaded video, 0 when core could not read it.
 *
 * @param int $file_id Attachment ID.
 */
function cptdp_video_file_length( int $file_id ): int {
	$meta   = wp_get_attachment_metadata( $file_id );
	$length = is_array( $meta ) ? (int) ( $meta['length'] ?? 0 ) : 0;

	if ( $length < 1 ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$path = (string) get_attached_file( $file_id );
		$read = '' !== $path && is_file( $path ) ? wp_read_video_metadata( $path ) : false;

		$length = is_array( $read ) ? (int) ( $read['length'] ?? 0 ) : 0;
	}

	return max( 0, $length );
}

/**
 * Vimeo's oEmbed answer for a canonical Vimeo URL: thumbnail URL (https only) and duration.
 *
 * @param string $url Canonical `https://vimeo.com/<id>` URL.
 * @return array{thumbnail:string,duration:int}|WP_Error
 */
function cptdp_video_vimeo_data( string $url ) {
	$data = _wp_oembed_get_object()->get_data(
		$url,
		array(
			'discover' => false,
			'width'    => 720,
			'height'   => 1280,
		)
	);

	if ( ! is_object( $data ) ) {
		return new WP_Error( 'cptdp_autofill_vimeo', __( 'Vimeo did not answer.', 'custom-post-types-dp' ) );
	}

	return array(
		'thumbnail' => isset( $data->thumbnail_url ) ? (string) esc_url_raw( (string) $data->thumbnail_url, array( 'https' ) ) : '',
		'duration'  => isset( $data->duration ) ? absint( $data->duration ) : 0,
	);
}

/**
 * The one-line reasons shown after a save (user and post scoped, shown once).
 *
 * @param int    $post_id Video ID.
 * @param string $message What could not be filled.
 */
function cptdp_video_autofill_note( int $post_id, string $message ): void {
	$key   = 'cptdp_video_autofill_' . get_current_user_id() . '_' . $post_id;
	$notes = get_transient( $key );
	$notes = is_array( $notes ) ? $notes : array();

	$notes[] = $message;
	set_transient( $key, array_values( array_unique( $notes ) ), 5 * MINUTE_IN_SECONDS );
}

/**
 * Fill the length: from the file, else from Vimeo. Never replaces a length Dave typed.
 *
 * @param int                                                       $post_id Video ID.
 * @param array{key:string,file:int,embed:array<string,string>}     $source  Current source.
 */
function cptdp_video_autofill_length( int $post_id, array $source ): void {
	$current = (int) get_post_meta( $post_id, 'cptdp_length', true );
	$auto    = (int) get_post_meta( $post_id, 'cptdp_length_auto', true );
	$from    = (string) get_post_meta( $post_id, 'cptdp_length_source', true );

	// Typed (or changed) by Dave: leave it.
	if ( $current > 0 && $current !== $auto ) {
		return;
	}

	// Ours and still from this source: nothing to do.
	if ( $current > 0 && $from === $source['key'] ) {
		return;
	}

	$length = 0;

	if ( $source['file'] > 0 ) {
		$length = cptdp_video_file_length( $source['file'] );

		if ( $length < 1 ) {
			cptdp_video_autofill_note( $post_id, __( 'The length could not be read from the video file. Enter it by hand.', 'custom-post-types-dp' ) );
		}
	} elseif ( $source['embed'] && 'vimeo' === $source['embed']['provider'] && cptdp_video_autofill_enabled() ) {
		$data = cptdp_video_vimeo_data( $source['embed']['url'] );

		if ( is_wp_error( $data ) ) {
			cptdp_video_autofill_note( $post_id, __( 'The length could not be read from Vimeo. Enter it by hand.', 'custom-post-types-dp' ) );
		} else {
			$length = $data['duration'];
		}
	}

	if ( $length > 0 ) {
		update_post_meta( $post_id, 'cptdp_length', $length );
		update_post_meta( $post_id, 'cptdp_length_auto', $length );
		update_post_meta( $post_id, 'cptdp_length_source', $source['key'] );

		return;
	}

	// Ours, and the source is gone: drop it. Dave's own value never gets here.
	if ( $current > 0 && $current === $auto ) {
		delete_post_meta( $post_id, 'cptdp_length' );
		delete_post_meta( $post_id, 'cptdp_length_auto' );
		delete_post_meta( $post_id, 'cptdp_length_source' );
	}
}

/**
 * Download an image URL and return the local temporary file, or a WP_Error. Private addresses are refused by core.
 *
 * @param string $url https URL.
 * @return string|WP_Error Temporary file path (the caller deletes it).
 */
function cptdp_video_download_image( string $url ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$tmp = download_url( $url, 10 );

	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$mime = wp_get_image_mime( $tmp );

	if ( ! $mime || ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		wp_delete_file( $tmp );

		return new WP_Error( 'cptdp_autofill_image', __( 'The thumbnail is not a usable image.', 'custom-post-types-dp' ) );
	}

	return $tmp;
}

/**
 * The provider's thumbnail as a local file, or a WP_Error. YouTube: maxres then hq (D3); Vimeo: its oEmbed thumbnail.
 *
 * @param array<string,string> $embed Result of cptdp_video_provider().
 * @return string|WP_Error Temporary file path.
 */
function cptdp_video_embed_thumbnail( array $embed ) {
	if ( 'youtube' === $embed['provider'] ) {
		foreach ( array( 'maxresdefault', 'hqdefault' ) as $name ) {
			$tmp = cptdp_video_download_image( 'https://i.ytimg.com/vi/' . $embed['id'] . '/' . $name . '.jpg' );

			if ( ! is_wp_error( $tmp ) ) {
				return $tmp;
			}
		}

		return new WP_Error( 'cptdp_autofill_youtube', __( 'YouTube has no thumbnail for this video.', 'custom-post-types-dp' ) );
	}

	$data = cptdp_video_vimeo_data( $embed['url'] );

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	if ( '' === $data['thumbnail'] ) {
		return new WP_Error( 'cptdp_autofill_vimeo', __( 'Vimeo has no thumbnail for this video.', 'custom-post-types-dp' ) );
	}

	return cptdp_video_download_image( $data['thumbnail'] );
}

/**
 * Fill the thumbnail from the embed, never over an image Dave chose (the file's frame is the browser's job).
 *
 * @param int                                                   $post_id Video ID.
 * @param array{key:string,file:int,embed:array<string,string>} $source  Current source.
 */
function cptdp_video_autofill_thumbnail( int $post_id, array $source ): void {
	if ( 'editor' === CPTDP_Generated_Image::owner( $post_id ) ) {
		return;
	}

	$ours = CPTDP_Generated_Image::source_of( $post_id );

	// Nothing to show for this video any more: drop ours (D9).
	if ( '' === $source['key'] ) {
		CPTDP_Generated_Image::remove( $post_id );

		return;
	}

	// Ours is from the current source: make sure it is in place (D8).
	if ( $ours === $source['key'] ) {
		CPTDP_Generated_Image::restore( $post_id );

		return;
	}

	// A file's frame comes from the browser; keep what we have until it arrives. Fall back to the embed only when there is none.
	if ( ! $source['embed'] || ! cptdp_video_autofill_enabled() ) {
		if ( '' === $ours || 0 === strpos( $ours, 'embed:' ) ) {
			CPTDP_Generated_Image::remove( $post_id );
		}

		return;
	}

	if ( $source['file'] > 0 && '' !== $ours ) {
		return;
	}

	$tmp = cptdp_video_embed_thumbnail( $source['embed'] );

	if ( is_wp_error( $tmp ) ) {
		cptdp_video_autofill_note( $post_id, $tmp->get_error_message() . ' ' . __( 'Set the thumbnail by hand.', 'custom-post-types-dp' ) );

		return;
	}

	$ext = 'image/png' === wp_get_image_mime( $tmp ) ? 'png' : ( 'image/webp' === wp_get_image_mime( $tmp ) ? 'webp' : 'jpg' );
	$id  = CPTDP_Generated_Image::sideload( $post_id, $tmp, 'video-' . $post_id . '-thumbnail.' . $ext );

	wp_delete_file( $tmp );

	if ( is_wp_error( $id ) ) {
		cptdp_video_autofill_note( $post_id, $id->get_error_message() . ' ' . __( 'Set the thumbnail by hand.', 'custom-post-types-dp' ) );

		return;
	}

	CPTDP_Generated_Image::adopt( $post_id, (int) $id, 'embed:' . $source['embed']['url'] );
}

/**
 * Entry point, called at the end of cptdp_video_save_meta() (D7).
 *
 * @param int $post_id Video ID.
 */
function cptdp_video_autofill( int $post_id ): void {
	$source = cptdp_video_source( $post_id );

	cptdp_video_autofill_length( $post_id, $source );
	cptdp_video_autofill_thumbnail( $post_id, $source );
}

/**
 * A video is being deleted: delete the image we made for it.
 *
 * @param int $post_id Post ID.
 */
function cptdp_video_autofill_cleanup( $post_id ): void {
	if ( 'video' === get_post_type( (int) $post_id ) ) {
		CPTDP_Generated_Image::remove( (int) $post_id, true );
	}
}
add_action( 'before_delete_post', 'cptdp_video_autofill_cleanup' );

/**
 * Show what could not be filled, once, on the edit screen after a save.
 */
function cptdp_video_autofill_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'video' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}

	$post_id = (int) get_the_ID();
	$key     = 'cptdp_video_autofill_' . get_current_user_id() . '_' . $post_id;
	$notes   = get_transient( $key );

	if ( ! is_array( $notes ) || ! $notes ) {
		return;
	}

	delete_transient( $key );

	foreach ( $notes as $note ) {
		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( (string) $note ) );
	}
}
add_action( 'admin_notices', 'cptdp_video_autofill_notice' );
```

Notes for the implementer: the last `if` in `cptdp_video_autofill_thumbnail()` is deliberate (file present, ours exists from an old source: leave it until the browser's frame replaces it; D9). Keep the function bodies as written; do not add a settings screen.

### 5.4 Add the poster route to the same file (append)

```php
/**
 * Route used by the admin browser to upload the frame it drew from the chosen video file.
 *
 * POST cptdp/v1/videos/<id>/poster?file=<attachment id>   body: raw JPEG or WebP, edit_post + REST nonce.
 * 200 { id, url, kept } where `kept` is true (and nothing was made) when the video has an image Dave chose.
 */
function cptdp_video_register_poster_route(): void {
	register_rest_route(
		CPTDP_Page_REST::NAMESPACE_ID,
		'/videos/(?P<id>\d+)/poster',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'cptdp_video_poster_upload',
			'permission_callback' => static function ( WP_REST_Request $request ) {
				$post_id = (int) $request['id'];

				if ( 'video' !== get_post_type( $post_id ) ) {
					return new WP_Error( 'cptdp_not_found', __( 'Not found.', 'custom-post-types-dp' ), array( 'status' => 404 ) );
				}

				return current_user_can( 'edit_post', $post_id )
					? true
					: new WP_Error( 'cptdp_forbidden', __( 'You are not allowed to edit this item.', 'custom-post-types-dp' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
			},
			'args'                => array(
				'id'   => array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'file' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'cptdp_video_register_poster_route' );

/**
 * Store the uploaded frame as the video's generated thumbnail.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function cptdp_video_poster_upload( WP_REST_Request $request ) {
	$post_id = (int) $request['id'];
	$file    = (int) $request['file'];

	if ( $file < 1 || ! wp_attachment_is( 'video', $file ) ) {
		return new WP_Error( 'cptdp_bad_file', __( 'The file is not a video.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
	}

	if ( 'editor' === CPTDP_Generated_Image::owner( $post_id ) ) {
		return rest_ensure_response( array( 'kept' => true ) );
	}

	$body = $request->get_body();

	if ( '' === $body || strlen( $body ) > CPTDP_Page_REST::MAX_BYTES ) {
		return new WP_Error( 'cptdp_bad_image', __( 'The image is empty or too large.', 'custom-post-types-dp' ), array( 'status' => 413 ) );
	}

	$info = CPTDP_Page_REST::inspect( $body );

	if ( is_wp_error( $info ) ) {
		return $info;
	}

	$tmp = wp_tempnam( 'cptdp-poster' );

	if ( '' === $tmp || false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		return new WP_Error( 'cptdp_poster_write', __( 'The image could not be saved.', 'custom-post-types-dp' ), array( 'status' => 500 ) );
	}

	$id = CPTDP_Generated_Image::sideload( $post_id, $tmp, 'video-' . $post_id . '-thumbnail.' . $info['ext'] );
	wp_delete_file( $tmp );

	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'cptdp_poster_failed', $id->get_error_message(), array( 'status' => 500 ) );
	}

	CPTDP_Generated_Image::adopt( $post_id, (int) $id, 'file:' . $file );

	return rest_ensure_response(
		array(
			'id'   => (int) $id,
			'url'  => (string) wp_get_attachment_url( (int) $id ),
			'kept' => false,
		)
	);
}
```

The route trusts `file` only as a source label (it must be a video attachment); it does not have to equal the saved `cptdp_video_file`, because on a new video the picker's choice is not saved yet.

### 5.5 Edit `$P/includes/documents/class-page-rest.php`

Change `private static function inspect( string $body )` to `public static function inspect( string $body )` (one word; update its docblock first line to say the video poster route reuses it). Nothing else in the file changes.

### 5.6 Edit `$P/includes/post-types/video.php`

1. At the end of `cptdp_video_save_meta()`, after the last `update_post_meta( $post_id, 'cptdp_highlight', 1 )` line, add `cptdp_video_autofill( $post_id );`. (The function is defined in `video-autofill.php`, loaded by the same loader; call it unconditionally.)
2. In `cptdp_video_render_details_box()` add under the Length input a description line: `<span class="description">Filled in for you from the uploaded file or Vimeo. Enter it by hand for a YouTube video.</span>` (escaped with `esc_html__()`).
3. In `cptdp_video_enqueue_admin()` extend the `cptdpVideo` array with: `'posterUrl' => esc_url_raw( rest_url( 'cptdp/v1/videos/' . get_the_ID() . '/poster' ) )`, `'nonce' => wp_create_nonce( 'wp_rest' )`, `'fileUrl' => <URL of the saved file, '' when none>`, `'fileId' => <saved file id or 0>`, `'needsPoster' => <true when the saved file exists, the video has no featured image, and CPTDP_Generated_Image::source_of() is not 'file:<id>'>`, `'failed' => esc_html__( 'The thumbnail could not be made from this video. Set it by hand.', ... )`. `get_the_ID()` inside `admin_enqueue_scripts` is not set: use `get_post()` / the `$_GET['post']`-free way, i.e. `$post = get_post();` and `0` when null (on `post-new.php` WordPress has already created the auto-draft, `get_post()` returns it; if it returns null skip the localization keys and let the script do nothing).
4. Update the file's top docblock: add one sentence "Thumbnail and length are autofilled, see video-autofill.php (DPV-041)."

### 5.7 Edit `$P/assets/admin-video.js`

Add inside the existing `$( function () { ... } )`, after the Remove handler:

```js
		// DPV-041: draw a frame of the chosen video and send it as the thumbnail.
		var $note = $( '<p class="description" id="cptdp-video-poster-note" hidden></p>' ).insertAfter( '#cptdp-video-file-name' ).parent();
		var running = 0;

		function say( text ) {
			$( '#cptdp-video-poster-note' ).text( text || '' ).prop( 'hidden', ! text );
		}

		function frameAt( duration ) {
			return Math.min( 1, duration / 2 );
		}

		function fit( w, h, max ) {
			var scale = Math.min( 1, max / Math.max( w, h ) );

			return { w: Math.round( w * scale ), h: Math.round( h * scale ) };
		}

		function capture( url ) {
			return new Promise( function ( resolve, reject ) {
				var video = document.createElement( 'video' );
				var timer = setTimeout( function () { reject( new Error( 'timeout' ) ); }, 20000 );

				function fail( e ) { clearTimeout( timer ); reject( e || new Error( 'video' ) ); }

				video.muted = true;
				video.preload = 'auto';
				video.playsInline = true;
				video.addEventListener( 'error', fail );
				video.addEventListener( 'loadedmetadata', function () {
					if ( ! video.videoWidth || ! video.videoHeight ) { return fail(); }
					video.currentTime = frameAt( video.duration || 0 );
				} );
				video.addEventListener( 'seeked', function () {
					var size = fit( video.videoWidth, video.videoHeight, 1280 );
					var canvas = document.createElement( 'canvas' );

					canvas.width = size.w;
					canvas.height = size.h;
					canvas.getContext( '2d' ).drawImage( video, 0, 0, size.w, size.h );

					try {
						canvas.toBlob( function ( blob ) {
							clearTimeout( timer );
							blob ? resolve( blob ) : reject( new Error( 'blob' ) );
						}, 'image/jpeg', 0.82 );
					} catch ( e ) {
						fail( e ); // a tainted canvas (file on another origin)
					}
				} );
				video.src = url;
			} );
		}

		function makePoster( fileId, url ) {
			if ( ! cptdpVideo.posterUrl || ! url || running ) { return; }

			running++;
			say( '' );

			capture( url ).then( function ( blob ) {
				return fetch( cptdpVideo.posterUrl + '?file=' + encodeURIComponent( fileId ), {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': cptdpVideo.nonce, 'Content-Type': blob.type },
					body: blob
				} );
			} ).then( function ( response ) {
				if ( ! response.ok ) { throw new Error( 'upload' ); }
				return response.json();
			} ).then( function ( data ) {
				if ( data && ! data.kept && data.id && window.wp && wp.media && wp.media.featuredImage ) {
					wp.media.featuredImage.set( data.id ); // keeps the form's _thumbnail_id in step
				}
			} ).catch( function () {
				say( cptdpVideo.failed );
			} ).then( function () { running--; } );
		}
```

Then, in the existing `frame.on( 'select', ... )` handler, after `$remove.show();` add `makePoster( attachment.id, attachment.url );`, and just before the final closing of the ready callback add `if ( cptdpVideo.needsPoster ) { makePoster( cptdpVideo.fileId, cptdpVideo.fileUrl ); }`. Remove the unused `$note` variable (the helper `say()` finds the element by id) and create the `<p>` with `$( '<p ...>' ).insertAfter( '#cptdp-video-file-name' )`. Keep the file's `'use strict'`, ES5 style (`var`, `function`) is not required: this file already uses `var`; `Promise` and `fetch` are fine in wp-admin browsers.

### 5.8 Tests (plugin suite)

New `$P/tests/post-types/test-video-autofill.php`, class `CPTDP_Test_Video_Autofill extends CPTDP_Test_Case`, `@group video`, `@group autofill`. Helpers inside the class:

- `jpeg(): string` builds bytes with GD (`imagecreatetruecolor( 40, 70 )`, `ob_start(); imagejpeg(); ob_get_clean()`); skip the test class with `markTestSkipped` when GD is missing.
- `http( array $routes, array &$seen )`: adds a `pre_http_request` filter that records each URL in `$seen` and answers by substring: for `download_url()` (it passes `stream => true` and a `filename`) the closure writes the bytes to `$args['filename']` and returns `array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '', 'filename' => $args['filename'] )`; for oEmbed it returns `array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( ... ) )`; an unmatched URL returns `new WP_Error( 'http_request_failed', 'no network in tests' )`. `tear_down()` removes the filter.
- `save_form( int $post_id, array $fields )`: sets the current user to an editor, builds `$_POST` with `cptdp_video_nonce` = `wp_create_nonce( 'cptdp_video' )` and the fields, and calls `cptdp_video_save_meta( $post_id )` (use the base's `submit()`).
- `make_video_file( int $seconds ): int`: `make_attachment( 'video/mp4' )` plus `wp_update_attachment_metadata( $id, array( 'length' => $seconds ) )`.

Cases (one `test_` method each; use a data provider for the URL table):

1. `cptdp_video_provider()` table: `https://www.youtube.com/embed/KlE9YewV7SU`, `https://www.youtube-nocookie.com/embed/<id>`, `https://www.youtube.com/watch?v=<id>&t=5`, `https://www.youtube.com/shorts/<id>`, `https://youtu.be/<id>` all give provider `youtube` and url `https://www.youtube.com/watch?v=<id>`; `https://player.vimeo.com/video/674533939` and `https://vimeo.com/674533939` and `https://vimeo.com/channels/staff/674533939` give provider `vimeo` and url `https://vimeo.com/674533939`; `https://example.com/embed`, `javascript:alert(1)`, `https://www.youtube.com/embed/short`, `https://notvimeo.com/123`, `''` give `array()`.
2. Length from a file: video with file (28 s metadata) and empty field -> `cptdp_length` 28, `cptdp_length_auto` 28, source `file:<id>`; the list column renders `0:28`.
3. A typed length is kept: field `90` with a file of 28 s -> stays 90 and no auto meta; a second save with an empty field -> filled with 28 (the "empty" rule).
4. Auto length refreshes when the file changes (file A 28 s, then file B 40 s -> 40); but once Dave changes the field to 55, a new file leaves 55.
5. Removing the file clears an auto length, and leaves a typed one.
6. Vimeo embed only: mocked oEmbed (`duration` 7) -> length 7; the request URL contains `vimeo.com/api/oembed` and the canonical `https://vimeo.com/674533939`; a YouTube embed makes **no** length and **no** note; file and Vimeo both -> the file's length and no oEmbed request for the length.
7. File with no readable length (no metadata, no real file) -> length stays empty and the transient note exists.
8. Embed thumbnail: YouTube mocked (`maxresdefault` 404 as `WP_Error`, `hqdefault` jpeg) -> featured image set, `CPTDP_Generated_Image::owner()` is `generated`, source `embed:https://www.youtube.com/watch?v=<id>`, requests were `maxresdefault` then `hqdefault` and nothing else; Vimeo mocked (oEmbed with `thumbnail_url` https, then the image) -> set; a non-https `thumbnail_url` -> note, no image.
9. Ownership: an image Dave set as featured is never replaced or deleted (no HTTP request made at all); ours is swapped when the embed URL changes (old attachment deleted, new one in place); ours is deleted when the embed and the file are both removed; Dave deleting ours in Media clears `cptdp_featured_generated`; deleting the video deletes ours; saving with no featured image and ours from the current source puts it back without any HTTP request (D8).
10. Both a file and an embed with nothing generated yet -> the embed thumbnail is made as the fallback; then the poster route (case 13) replaces it with the file's frame and deletes the embed one.
11. `add_filter( 'cptdp_video_autofill', '__return_false' )` -> no HTTP request, no thumbnail, no Vimeo length; a file's length is still read (it is local).
12. Admin only: `wp_insert_post()` of a video with a Vimeo embed, `update_post_meta()`, the seeder (`cptdp_run_seeder` for `videos`, the pattern used in `tests/seeders/test-videos.php`) and a REST meta update all make **zero** HTTP requests; a save without the nonce or by a user who cannot edit does nothing.
13. Poster route (use `rest_do_request`, as `tests/collaterals/test-collateral-pages.php` does for its routes; reset `$wp_rest_server` in `tear_down`): anonymous 401, subscriber 403, an id that is not a video 404, `file` that is not a video 400, a text body 415, a body over 2 MB 413, a valid JPEG by an editor 200 and sets the featured image with source `file:<id>`; a second upload swaps it and deletes the first; with an editor-chosen image the answer is `{ kept: true }` and **no attachment is created**.
14. Notices: after a failed embed thumbnail the transient holds the message; `cptdp_video_autofill_notice()` on the video edit screen prints it once and the second call prints nothing.
15. Plugin-level: `CPTDP_Generated_Image` keeps `CPTDP_Page_Featured::META` equal to its `META` (alias check) so the Documents code and the video code agree on the key.

Also update `tests/post-types/test-video.php` only if a suite run shows it needs the `pre_http_request` guard (its URLs are `example.com`, which are not providers, so it should not).

## 6. Work packages (in order; one gate per phase)

Test commands (from `AGENTS.md`):

```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```

### Phase 0: environment and baseline

1. `git switch -c feat/DPV-041-Video-Autofill` from an up-to-date `main`. Do not stage the untracked plan files of other tickets.
2. `ddev start`; if the WP test library is missing run `ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"`.
3. Run both PHPUnit suites. **Gate:** both green. Write the plugin and theme totals down (AGENTS.md expects 847 and 523 today; use what the run really says).

### Phase 1: shared ownership class (refactor, no behaviour change)

1. Run `grep -rn "CPTDP_Page_Featured::\|_cptdp_generated\|cptdp_featured_generated" $P/includes $P/tests` and note every member used.
2. Create `class-generated-image.php` (5.1) and edit `class-page-featured.php` (5.2).
3. **Gate:** the plugin suite is green with **no change to any existing test** (same total as Phase 0), especially `--filter CPTDP_Test_Page_Featured` and `--group featured`. If a test fails, the delegation changed behaviour: fix the class, never the test.

### Phase 2: server autofill (length and embed thumbnail)

1. Create `video-autofill.php` without the poster route yet (5.3). Edit `video.php` for the save call, the Length description and the docblock (5.6 items 1, 2, 4).
2. Write `tests/post-types/test-video-autofill.php` cases 1 to 12, 14 and 15 (5.8). Write each test first, see it fail for the right reason, then pass.
3. **Gate:** `--filter CPTDP_Test_Video` (both classes) and the whole plugin suite green; the new class has no skipped test unless GD is missing.

### Phase 3: poster route

1. Make `CPTDP_Page_REST::inspect()` public (5.5), append the route (5.4), add test cases 10 and 13.
2. **Gate:** whole plugin suite green.

### Phase 4: admin script

1. Edit `video.php` item 3 of 5.6 (localized data) and `admin-video.js` (5.7).
2. No JS runner exists. Check by hand in the browser (see section 8, item 4). **Gate:** the manual checks pass; `node --check $P/assets/admin-video.js` reports no syntax error.

### Phase 5: real-world check, docs and version

1. On the DDEV site: save one of the six seeded videos (they have YouTube and Vimeo embeds and no file) and confirm a thumbnail and, for Vimeo, a length appear. Open each of the three seeded YouTube shorts and look at the thumbnail: if `maxresdefault` came back letterboxed, stop and tell the user (D3).
2. Bump `Version` and `CPTDP_VERSION` to `0.29.0` in `$P/custom-post-types-dp.php`.
3. `AGENTS.md`: in the Overview, in the `video` entry, add a short sentence (autofill on the admin save, `video-autofill.php`, `CPTDP_Generated_Image` shared with `CPTDP_Page_Featured`, the `cptdp_video_autofill` filter, route `cptdp/v1/videos/<id>/poster`, D1 and D3), update the plugin version in the first paragraph, and update the expected test totals to the real numbers. Add a row-level note that existing videos are filled the next time they are saved.
4. **Gate:** both PHPUnit suites green; the totals in `AGENTS.md` match.

## 6A. Conventional commits (make these only when the user asks you to commit)

Branch `feat/DPV-041-Video-Autofill`, from `main`. Subjects 72 characters or fewer, `Refs: DPV-041`, then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Stage explicit paths. Every commit leaves both suites green.

1. `refactor(custom-post-types-dp): share the generated image ownership rule` - `includes/documents/class-generated-image.php`, `includes/documents/class-page-featured.php`.
2. `feat(custom-post-types-dp): fill video length and embed thumbnail on save` - `includes/post-types/video-autofill.php`, `includes/post-types/video.php`.
3. `test(custom-post-types-dp): cover the video autofill` - `tests/post-types/test-video-autofill.php` (cases 1-12, 14, 15). (A small code and test pair may be squashed.)
4. `feat(custom-post-types-dp): add the video poster upload route` - `includes/post-types/video-autofill.php` (route), `includes/documents/class-page-rest.php`.
5. `test(custom-post-types-dp): cover the video poster route` - `tests/post-types/test-video-autofill.php` (cases 10, 13).
6. `feat(custom-post-types-dp): draw a frame of the chosen video file` - `assets/admin-video.js`, `includes/post-types/video.php`.
7. `chore(custom-post-types-dp): bump version to 0.29.0` - `custom-post-types-dp.php`.
8. `docs(agents): document the video autofill` - `AGENTS.md`.
9. `docs(plans): add the DPV-041 video autofill plan` - this file.

PR title: `feat(custom-post-types-dp): autofill video thumbnail and length` (58 characters). Body from `.github/pull_request_template.md` (tick the feature type; Description, Implementation, How to Test), ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

## 7. File inventory

| File | Change |
|---|---|
| `$P/includes/documents/class-generated-image.php` | new (5.1) |
| `$P/includes/documents/class-page-featured.php` | delegates to it (5.2) |
| `$P/includes/documents/class-page-rest.php` | `inspect()` private -> public (5.5) |
| `$P/includes/post-types/video-autofill.php` | new (5.3, 5.4) |
| `$P/includes/post-types/video.php` | save call, Length description, localized data (5.6) |
| `$P/assets/admin-video.js` | frame capture and upload (5.7) |
| `$P/tests/post-types/test-video-autofill.php` | new (5.8) |
| `$P/custom-post-types-dp.php` | version `0.29.0` (section 11: `0.30.0`) |
| `$P/assets/vendor/mediabunny/` | new, section 11 |
| `$P/assets/admin-video-preview.js` | new, section 11 |
| `$P/tests/fixtures/` (tiny MP4 and WebM) | new, section 11 |
| `AGENTS.md` | Overview and totals |
| `.claude/plans/videos/DPV-041-Video-Backend-Fields-Autofill.md` | this plan |

Not touched: the theme, `theme.json`, the seeders and their CSVs, the Documents and Collaterals tests, `video-category`/`aspect-ratio`.

## 8. Final verification (run all before reporting done)

1. Both PHPUnit suites green; Documents and Collaterals tests untouched (`git diff --stat -- '*/tests/*'` shows only the new test file).
2. `node --check $P/assets/admin-video.js`.
3. `git diff` shows nothing outside the inventory; no untracked plan file of another ticket is staged.
4. **Browser check** (headless Chrome with puppeteer-core against `https://devportfolio.ddev.site/wp-admin/`, as used for earlier tickets, or by hand). You need a short MP4 (H.264) uploaded to Media; if none is at hand, ask the user for one rather than inventing it:
   - New video, **Select video** (the MP4), the Thumbnail box fills within a few seconds with a frame, no reload; **Publish**; the Videos list shows the thumbnail and `0:xx`.
   - Edit it, **Remove** the thumbnail, choose your own image, Update: your image stays; select another MP4, Update: your image stays and the Length (if you typed one) stays.
   - Video with only a Vimeo URL: Update fills thumbnail and length; with only a YouTube URL: thumbnail yes, length empty, no notice.
   - Block the network (or use an invalid Vimeo id): the video saves, the yellow notice appears once and is gone after a reload.
   - Open `/videos/` with the network panel open: no request to youtube.com, ytimg.com or vimeo.com.
5. Report which of the three seeded YouTube shorts were letterboxed (D3), if any.

## 9. Risks, edge cases and what to do about them

- **Outbound calls on every save while the provider is down.** Each save with an empty thumbnail and a YouTube or Vimeo embed tries again (5 s oEmbed timeout, 10 s image download). Accepted: admin only and rare; the filter `cptdp_video_autofill` switches it off. If it is annoying in practice, add a short failure transient later (not now).
- **Canvas tainted or codec unsupported.** The script shows the inline note and the server falls back to the embed thumbnail when there is one. Uploads served from a CDN origin need CORS headers; not handled.
- **Shared hosting limits (D2a).** (1) Some shared hosts block outbound HTTP or set a short PHP time limit: the embed thumbnail and the Vimeo length then fail with a notice and the filter `cptdp_video_autofill` turns the calls off; the save still succeeds because each call is capped (5 s oEmbed, 10 s image download, so at most about 25 s on a fully dead provider; if that is too slow on the real host, lower `download_url()`'s timeout). (2) `wp_read_video_metadata()` runs getID3 over the file in PHP memory; core already does it at upload, and the plan only calls it when the stored metadata has no length, so a large file costs at most one extra read on a save. (3) A host's upload size limit (`upload_max_filesize`) decides whether Dave can upload the MP4 at all; nothing in this ticket changes it, and a video that cannot be uploaded can still use an embed URL. (4) The frame is drawn on Dave's own computer, so the host's CPU is not used for it.
- **Large or long-GOP videos.** The browser downloads enough of the file to seek to about 1 s (`preload="auto"`); on a multi-hundred-MB file that can be slow. The 20 s timeout ends it and the note tells Dave to set it by hand. Acceptable for the expected short MP4s (DPV-040).
- **Race with the Update button.** If the upload finishes after Dave clicks Update, the form's old `_thumbnail_id` is saved; D8 puts ours back on that same save (it exists by then) or on the next one.
- **Dave cannot leave a video with no thumbnail** once an image was generated for it (D8 puts it back). He can choose another image, or the filter can be turned off. This follows the story line "made again on the next save".
- **`maxresdefault` missing or letterboxed (D3).** Falls back to `hqdefault`. If both look wrong for portrait shorts the answer is a different source, not a code workaround; ask the user.
- **Core cannot read the duration** (odd container). The note says to enter it by hand; nothing else breaks.
- **`_wp_oembed_get_object()->get_data()` argument handling** was read in core 6.x; if a later core changes it, the Phase 2 Vimeo tests fail loudly because they mock the HTTP layer and check the request URL.
- **Refactor risk (Phase 1).** If any Documents or Collaterals test changes result, stop; the point of D4 is that it is a pure move.
- **Existing videos** are not backfilled until saved. Offer a `wp cptdp videos autofill` command as a follow-up if Dave wants it.


## 10. As built (sections 1-8, done 2026-11-17)

Differences from the text above, all verified by the suites:

- **Aspect ratio added** (goal 12, D17) in `video-autofill.php`: `cptdp_video_file_size()`, `cptdp_video_ratio_slug()`, `cptdp_video_autofill_ratio()`; the Vimeo oEmbed answer is cached in the object cache (`cptdp_video_vimeo`) so length, ratio and thumbnail share one request, and carries `width` and `height`.
- **Bug found on the real site:** the poster route called `wp_tempnam()`, which REST requests do not load (the PHPUnit run always has it): it now requires `wp-admin/includes/file.php` first. **Lesson for section 11:** every new REST callback must `require_once` what it uses; the tests cannot catch it.
- `tests/documents/test-page-rest.php` counted every route in `cptdp/v1` (10); the poster route makes it 11 (section 11 makes it 12).
- The Length description reads: "Generated automatically when possible, from the uploaded file or the video link. If it stays empty, enter it by hand." (we cannot promise Vimeo or YouTube data).
- D3 check: the three seeded YouTube shorts return a 1280x720 `maxresdefault` with black side bars (pillarboxed). **Open question for the user**, nothing changed.
- Totals at this point: plugin 886 tests (12 skipped), theme 523.

## 11. Preview clip (done)

Do these as **Phases 6, 7 and 8** after the earlier phases. Same rules: do not "improve" anything not listed; stop and report on a mismatch.

### 11.1 Vendor Mediabunny (Phase 6)

1. In the scratchpad (not the repo): `npm pack mediabunny@1.66.0`, unpack, find the browser bundle that exposes the global `Mediabunny` (the README says a script-tag build; it is a `.cjs`/IIFE file under `dist/bundles/`). Copy **one** minified file to `$P/assets/vendor/mediabunny/mediabunny.min.js`, plus the package `LICENSE` and a short `README.md` (version, source URL, the file copied, date, how to update, that it is MPL-2.0 and must keep its notice). If there is no standalone bundle or the file is above about 1 MB, **stop and report** (do not add a build step).
2. Register it in `video.php` `cptdp_video_enqueue_admin()` as `cptdp-mediabunny` (version `1.66.0`, in footer) and make the new script depend on it. Never enqueue it elsewhere.

### 11.2 Server (Phase 7)

New meta, registered in `cptdp_register_video_meta()` next to `cptdp_video_file`: `cptdp_video_preview`, integer, `show_in_rest`, default 0, `sanitize_callback` `cptdp_video_sanitize_file` (an existing video attachment), same `auth_callback` as its neighbours. It is not part of the form: `cptdp_video_save_meta()` never reads or writes it. Update `tests/post-types/test-video.php` `expected_meta()` with `'cptdp_video_preview' => array( 'integer', 0 )` (the one allowed change to an existing test, with the route count).

Append to `video-autofill.php`:

```php
/**
 * The preview clip the plugin made for a video, 0 when none (or it no longer exists).
 */
function cptdp_video_preview_generated( int $post_id ): int {
	$id = (int) get_post_meta( $post_id, 'cptdp_video_preview_generated', true );

	return $id > 0 && 'attachment' === get_post_type( $id ) ? $id : 0;
}

/**
 * Whose preview a video has: `generated`, `editor` (set by anyone else), or '' when none.
 */
function cptdp_video_preview_owner( int $post_id ): string {
	$current = (int) get_post_meta( $post_id, 'cptdp_video_preview', true );

	if ( $current < 1 ) {
		return '';
	}

	return cptdp_video_preview_generated( $post_id ) === $current ? 'generated' : 'editor';
}

/**
 * Where ours was made from (`file:<id>`), '' when there is none.
 */
function cptdp_video_preview_source( int $post_id ): string {
	$ours = cptdp_video_preview_generated( $post_id );

	return $ours > 0 ? (string) get_post_meta( $ours, CPTDP_Generated_Image::META_FROM, true ) : '';
}

/**
 * Forget and delete ours.
 */
function cptdp_video_preview_remove( int $post_id ): void {
	$ours = cptdp_video_preview_generated( $post_id );

	delete_post_meta( $post_id, 'cptdp_video_preview_generated' );

	if ( $ours > 0 ) {
		if ( (int) get_post_meta( $post_id, 'cptdp_video_preview', true ) === $ours ) {
			delete_post_meta( $post_id, 'cptdp_video_preview' );
		}

		wp_delete_attachment( $ours, true );
	}
}

/**
 * Container of an uploaded clip from its first bytes: `mp4`, `webm` or ''.
 */
function cptdp_video_preview_kind( string $body ): string {
	if ( 'ftyp' === substr( $body, 4, 4 ) ) {
		return 'mp4';
	}

	return "\x1A\x45\xDF\xA3" === substr( $body, 0, 4 ) ? 'webm' : '';
}
```

- In `cptdp_video_autofill()` add a third call `cptdp_video_autofill_preview( $post_id, $source )`: `if ( 'editor' === cptdp_video_preview_owner( $post_id ) ) { return; }` then `if ( $source['file'] < 1 ) { cptdp_video_preview_remove( $post_id ); }` and nothing else (D16). Add `cptdp_video_autofill_preview` as a small function above it.
- `cptdp_video_autofill_cleanup()` (before delete post): also call `cptdp_video_preview_remove( (int) $post_id )`.
- A new `delete_attachment` handler (`cptdp_video_preview_on_delete_attachment`): when the attachment is the `cptdp_video_preview_generated` of the post named by its `_cptdp_generated_for`, delete `cptdp_video_preview_generated` and, when equal, `cptdp_video_preview`.
- Route, registered next to the poster route in `cptdp_video_register_poster_route()` or its own function hooked on `rest_api_init`: `POST cptdp/v1/videos/<id>/preview?file=<video attachment id>`, same `permission_callback` and args as the poster route (copy it; do not weaken it). Callback `cptdp_video_preview_upload`:
  1. `$file` must be `wp_attachment_is( 'video', $file )`, else 400 `cptdp_bad_file`.
  2. `'editor' === cptdp_video_preview_owner( $id )` -> 200 `{ kept: true }`, creates nothing.
  3. `$body = $request->get_body()`; empty or longer than `apply_filters( 'cptdp_video_preview_max_bytes', 8 * MB_IN_BYTES )` -> 413 `cptdp_bad_clip`.
  4. `cptdp_video_preview_kind( $body )` empty -> 415 `cptdp_bad_clip` ("The clip must be an MP4 or WebM video.").
  5. `require_once ABSPATH . 'wp-admin/includes/file.php';` **before** `wp_tempnam()` (section 10 lesson), write the body, `CPTDP_Generated_Image::sideload( $id, $tmp, 'video-' . $id . '-preview.' . $kind )`, delete the temp file, on error 500 `cptdp_preview_failed`.
  6. Adopt: `update_post_meta( $att, CPTDP_Generated_Image::META_FOR, $id )`, `META_FROM` = `'file:' . $file`, `update_post_meta( $id, 'cptdp_video_preview', $att )`, remember the old ours, `update_post_meta( $id, 'cptdp_video_preview_generated', $att )`, then delete the old ours attachment (the new one is in place first).
  7. 200 `{ id, url, kept: false }`.
- Wording of every error uses `__()` with the `custom-post-types-dp` domain.

Localized data (`cptdp_video_enqueue_admin()`, same `$post instanceof WP_Post` guard) gains: `previewUrl` (`rest_url( 'cptdp/v1/videos/<id>/preview' )`), `previewNeeded` (a file exists, `cptdp_video_preview_owner()` is not `editor`, and `cptdp_video_preview_source()` is not `file:<id>`), `previewMax` (the filter's value in bytes) and the strings `previewMaking`, `previewReady`, `previewFailed`, `previewUnsupported`.

### 11.3 Admin script (Phase 8)

New file `$P/assets/admin-video-preview.js` (enqueued with `cptdp-admin-video` as a dependency, so `admin-video.js` can call it). It defines `window.cptdpVideoPreview = { start( fileId, url ) }` and owns the status UI; `admin-video.js` calls it (a) after a successful `makePoster()` or when that fails or is skipped, from the picker's `select` handler, and (b) on load when `cptdpVideo.previewNeeded` (D15: poster first, preview second; if no poster is needed, start the preview directly). Do **not** copy the encoder into `admin-video.js`.

Behaviour:

1. **UI.** Insert, below the file name, `<p id="cptdp-video-preview" class="description"><span class="cptdp-preview-text"></span> <progress max="100" hidden></progress></p>`. States: preparing, `Making the preview clip... 45%` (progress shown), `Preview clip ready (6 s, 0.6 MB)`, and a one-line failure message. `aria-live="polite"` on the paragraph; nothing is shown for a video with no file. A new `start()` cancels the run in progress.
2. **Path B (Mediabunny).** `new Mediabunny.Input( { source: new Mediabunny.UrlSource( url ), formats: Mediabunny.ALL_FORMATS } )`, `Output` with `Mp4OutputFormat` and a `BufferTarget`, then `Conversion.init( { input, output, trim: { start: 0, end: 6 }, video: { width/height from fit(720), fit: 'contain', codec: 'avc', quality: new Mediabunny.Quality( { bitrate: 1e6 } ) }, audio: { discard: true } } )`, `conversion.onProgress = ( p ) => ...`, `await conversion.execute()`. If `! conversion.isValid` (the video track was discarded because AVC cannot be encoded) or anything throws, go to path A. Verify every name above against the vendored version's typings or README before use; if an API differs, adapt it and say so in the report.
3. **Path A (fallback).** Hidden muted `<video>` with `src = url`, canvas up to 720 px, `canvas.captureStream( 24 )`, `MediaRecorder` with the first supported of `video/mp4;codecs=avc1`, `video/webm;codecs=vp9`, `video/webm`, play for `min( 6, duration )` seconds, drive the progress bar from `currentTime`, stop, `Blob` of the recorded type. A tainted canvas or an unsupported type fails with the `previewUnsupported` note.
4. **Upload.** Refuse a blob over `previewMax` before sending. `fetch( cptdpVideo.previewUrl + '?file=' + fileId, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cptdpVideo.nonce, 'Content-Type': blob.type }, body: blob } )`; a `kept: true` answer shows "A preview you chose is kept." and ends. Nothing in the form changes (the meta is written by the route; the form never posts it).
5. **Leaving.** While a run is active, `beforeunload` warns; it is removed when the run ends. A cancelled run leaves nothing on the server.
6. ES5-style `var` is fine (the plugin's admin scripts use it); `Promise`, `fetch`, `async` are fine. `node --check` must pass.

### 11.4 Tests (Phase 7 server, extend `test-video-autofill.php`)

JS has no runner: manual checks in section 8. Server cases:

1. Meta `cptdp_video_preview` is registered (REST, integer) and rejects an attachment that is not a video.
2. Route guards: anonymous 401, subscriber 403, an id that is not a video 404, `file` that is not a video 400, a body of text 415, a body over the cap 413 (set the filter small).
3. A valid MP4 by an editor -> 200; `cptdp_video_preview` and `cptdp_video_preview_generated` equal the new attachment, its `_cptdp_generated_from` is `file:<id>`; a WebM body works too. **Fixture:** a real tiny MP4 and WebM in `tests/fixtures/` (a few KB each, made once with Mediabunny in Node or ffmpeg on the developer's machine; commit them with a line in the fixtures README if there is one). If `media_handle_sideload()` rejects a hand-made file in the test environment, filter `wp_check_filetype_and_ext` in that test only; say so in the report.
4. A second upload swaps it and deletes the first attachment.
5. A preview set by someone else (`update_post_meta( $id, 'cptdp_video_preview', $other )`) answers `{ kept: true }` and **creates no attachment**; saving the form with no file leaves it alone.
6. Saving the form with the file removed deletes ours and both meta; saving with the file changed keeps ours (D16).
7. Deleting ours in Media clears both meta; deleting the video deletes ours.
8. The seeder, `wp_insert_post()` and a REST meta write create no preview and make no HTTP request (admin only).
9. The route count in `test-page-rest.php` becomes 12.
10. Vendored bundle: a test asserts `assets/vendor/mediabunny/mediabunny.min.js`, `LICENSE` and `README.md` exist and that the script is only enqueued on the Video edit screen (use the existing enqueue test pattern if there is one; else `wp_scripts` after `cptdp_video_enqueue_admin()` with a video screen set).

### 11.5 Phases

- **Phase 6: vendor Mediabunny** (11.1). **Gate:** files in place, size reported to the user, `git diff` shows nothing else.
- **Phase 7: server** (11.2, 11.4). **Gate:** whole plugin suite green; Documents and Collaterals tests unchanged except the route count.
- **Phase 8: admin script and UI** (11.3). **Gate:** `node --check` on both scripts, then the browser checks in section 8 item 6.
- Then: bump the version to `0.30.0` (new feature), update `AGENTS.md` (the `video` entry: preview meta, route, vendored library, D10-D17, the real totals), and re-run both suites.

### 11.6 Conventional commits (only when the user asks)

1. `build(custom-post-types-dp): vendor Mediabunny 1.66.0 for video previews` - `assets/vendor/mediabunny/`.
2. `feat(custom-post-types-dp): add the video preview clip meta and route` - `video-autofill.php`, `video.php`.
3. `test(custom-post-types-dp): cover the video preview clip` - the test files and fixtures, including the route count and `expected_meta` edits.
4. `feat(custom-post-types-dp): make the video preview clip in the browser` - `assets/admin-video-preview.js`, `assets/admin-video.js`, `video.php` (localized data, enqueue).
5. `chore(custom-post-types-dp): bump version to 0.30.0` and `docs(agents): document the video preview clip`.

### 11.7 Browser checks (add to section 8)

Use the same short MP4 (H.264) and the user's `.mov`:

- New video, **Select video**: the thumbnail appears, then the status line counts up and ends with `Preview clip ready`; Publish; `ddev wp post meta get <id> cptdp_video_preview` returns an attachment of 6 s or less, no audio, at most 720 px.
- Change the file: the old clip is deleted, a new one appears. Remove the file and Update: the clip is deleted.
- Set `cptdp_video_preview` to another attachment by WP-CLI: choosing a file shows "A preview you chose is kept."
- Force path A (in the console set `window.VideoEncoder = undefined` before choosing a file): a WebM or MP4 clip is still made.
- Block the file origin (or use an unreadable file): the one-line failure note shows, the video still saves.
- Reload the edit screen with a file but no clip: it starts again by itself.
- Leave the page while it runs: the browser warns.

### 11.8 Extra risks

- **Browser support.** Path B needs WebCodecs with an AVC encoder (Chrome, Edge, Safari 16.4+/26, Firefox 130+ desktop); the script checks and falls back. Path A needs `MediaRecorder` and `captureStream`. Neither exists in very old browsers: the video saves, the note says so.
- **Real time and tabs.** Path A takes about 6 s and may be throttled in a background tab; path B is faster than real time but still stops if the tab closes.
- **Library weight and licence.** MPL-2.0: keep the notice with the vendored file. The file is only loaded on the Video edit screen, never for visitors.
- **Media library clutter.** Each video can now own two generated attachments (thumbnail, clip), both attached to the post and deleted with it or when replaced.
- **Hosts.** A host that blocks `.webm` or `.mp4` uploads, or caps request bodies below the clip size, makes the upload fail with the note; the cap is the filter `cptdp_video_preview_max_bytes`.
- **Same-origin only.** A file served from another origin needs CORS headers for both the thumbnail and the clip (same as the poster).
- **Mismatch rule.** If the vendored Mediabunny API differs from 11.3, adapt to the installed version and report it; do not change the design.

**As built (section 11):** the preview script reads `window.cptdpVideo` lazily (it runs before the localized data is printed, because `admin-video.js` depends on it); `Conversion` is given `width`/`height` computed from the track's display size with `fit: 'fill'` (same aspect, even numbers); `data.seconds` is not returned by the route. Fixtures `tests/fixtures/preview.mp4|webm` were made with ffmpeg (instructions in the fixtures README).

**Letterbox fix (D3 resolved, 2026-11-17):** the seeded shorts' `maxresdefault` is a 1280x720 frame with a darkened, blurred copy on both sides (not black) and, for one, black bars inside the picture. `cptdp_video_trim_bars()` (video-autofill.php) now runs on every generated thumbnail (embed downloads and the browser's uploaded frame): `cptdp_video_crop_seams()` crops to the picture between two mirrored vertical seams (a jump on 30% or more of the sampled rows, at least 5x the image's average, at least 20% left; columns only, rows cut real UI in a screen-recording short), then `cptdp_video_crop_black()` crops black bars when both opposite edges have them. Real results: 1280x720 -> 406x720, 406x720, 406x536. Needs GD, otherwise nothing is cropped. Thumbnails made before this change are not re-cropped (remove the thumbnail and save to make it again).

## 12. Final additions and the commits as made

### 12.1 Added after the first review

- **Thumbnail bars (D3 resolved).** See the letterbox paragraph in section 11: `cptdp_video_trim_bars()` runs on every generated thumbnail, embed download and browser frame alike.
- **Big file warning.** `cptdp_video_warn_bytes()` (default **100 MB**, filter `cptdp_video_warn_bytes`) and `cptdp_video_big_note()` in `video.php`; the media picker asks for confirmation (the file stays in Media if refused) and a note stays under the file name, suggesting YouTube or Vimeo and the Embed URL. The Video File box shows the server's real upload limit (`wp_max_upload_size()`), because a WordPress filter cannot raise PHP's limit.
- **Upload limit.** `.ddev/php/upload.ini` sets `upload_max_filesize` and `post_max_size` to 1024M (the lowest Hostinger plan, Web Single: 1024 MB upload and POST, 1024 MB PHP memory, 360 s). Plan maximums: Single 1024 MB, Premium 1536 MB, Unlimited 2048 MB, Cloud Startup 3072 MB (https://www.hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/). **On Hostinger set `upload_max_filesize` and `post_max_size` in hPanel > PHP Configuration** (or `.htaccess`, keeping `post_max_size` at least the upload size); the defaults for a new site were not found, so check the PHP Info page. Not verified: any CDN or proxy cap in front of the site; test one real large upload after deploying.
- Totals: plugin 906 tests (12 skipped), theme 523.

### 12.2 Not verified (carry over)

Safari and Firefox for the preview clip; a `.mov` file; the media-picker click path (the load path is the same code); a CDN request-size cap on the live host; existing thumbnails made before the bar trimming are not re-cropped (remove the thumbnail and save to make it again).

### 12.3 Commits as made

Branch `feat/DPV-041-Video-Autofill` from `main`. The earlier lists (6A, 11.6) are replaced by this one: the server code lands before its tests only together with the two existing-test edits it forces (route count and meta list), so every commit leaves both suites green. Footer on each: `Refs: DPV-041` and `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

1. `refactor(custom-post-types-dp): share the generated image ownership rule`
2. `build: raise the local PHP upload limit for videos` (`.ddev/php/upload.ini`)
3. `build(custom-post-types-dp): vendor Mediabunny 1.66.0 for video previews`
4. `feat(custom-post-types-dp): draw the video thumbnail and preview clip in the browser` (`assets/admin-video.js`, `assets/admin-video-preview.js`)
5. `feat(custom-post-types-dp): autofill video length, ratio, thumbnail and preview` (`video-autofill.php`, `video.php`, `class-page-rest.php`, plus the `test-page-rest.php` and `test-video.php` edits)
6. `test(custom-post-types-dp): cover the video autofill and preview` (`test-video-autofill.php`, fixtures)
7. `chore(custom-post-types-dp): bump version to 0.30.0`
8. `docs(agents): document the video autofill and preview clip`
9. `docs(plans): add the DPV-041 video autofill plan`

PR title: `feat(custom-post-types-dp): autofill video thumbnail, length and preview` (70 characters). Body from `.github/pull_request_template.md`, ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. No push unless asked.
