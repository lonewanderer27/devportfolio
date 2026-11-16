<?php
/**
 * Video autofill (DPV-041): thumbnail and length are filled in when a video is saved in wp-admin.
 *
 * Runs only from the Video edit form (see cptdp_video_save_meta()) and from the poster route below, never
 * for a visitor, the seeder, WP-CLI or a REST meta update. Anything Dave typed or chose is left alone.
 *
 * Source keys: `file:<attachment id>` or `embed:<canonical provider url>`. Meta (not in REST):
 * `cptdp_length_auto` (the length the system wrote), `cptdp_length_source` (its source key), and the same
 * pair for the aspect ratio term: `cptdp_ratio_auto`, `cptdp_ratio_source`.
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
 * @return array{thumbnail:string,duration:int,width:int,height:int}|WP_Error
 */
function cptdp_video_vimeo_data( string $url ) {
	// One request per URL per page load (object cache is per request): length, ratio and thumbnail all read it.
	$cached = wp_cache_get( $url, 'cptdp_video_vimeo' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

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

	$result = array(
		'thumbnail' => isset( $data->thumbnail_url ) ? (string) esc_url_raw( (string) $data->thumbnail_url, array( 'https' ) ) : '',
		'duration'  => isset( $data->duration ) ? absint( $data->duration ) : 0,
		'width'     => isset( $data->width ) ? absint( $data->width ) : 0,
		'height'    => isset( $data->height ) ? absint( $data->height ) : 0,
	);

	wp_cache_set( $url, $result, 'cptdp_video_vimeo' );

	return $result;
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
 * @param int                                                   $post_id Video ID.
 * @param array{key:string,file:int,embed:array<string,string>} $source  Current source.
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
 * Crop to the picture between two hard, mirrored vertical seams (a portrait short that YouTube centres in a 16:9 frame and
 * fills the sides with a darkened, blurred copy of itself).
 *
 * A seam is a column where the colour jumps on at least 30% of the sampled lines; two seams count only
 * when they sit mirrored about the centre (within 1px), the jump is at least 5x the image's average, and at least
 * 20% of the image is left. Normal photos have no such pair and are returned untouched.
 *
 * @param GdImage $im Image.
 * @return GdImage The same image when nothing was cropped, else a cropped copy.
 */
function cptdp_video_crop_seams( $im ) {
	// Columns only: a picture centred sideways is the pillarbox case; rows would cut real content (UI, subtitles).
	foreach ( array( true ) as $columns ) {
		$w      = imagesx( $im );
		$h      = imagesy( $im );
		$size   = $columns ? $w : $h;
		$length = $columns ? $h : $w;
		$step   = max( 1, (int) floor( $length / 96 ) );
		$cover  = array_fill( 0, $size, 0.0 );

		for ( $i = 1; $i < $size; $i++ ) {
			$lines = 0;
			$jumps = 0;

			for ( $j = 0; $j < $length; $j += $step ) {
				$a = $columns ? imagecolorat( $im, $i, $j ) : imagecolorat( $im, $j, $i );
				$b = $columns ? imagecolorat( $im, $i - 1, $j ) : imagecolorat( $im, $j, $i - 1 );

				++$lines;

				if ( abs( ( ( $a >> 16 ) & 255 ) - ( ( $b >> 16 ) & 255 ) ) + abs( ( ( $a >> 8 ) & 255 ) - ( ( $b >> 8 ) & 255 ) ) + abs( ( $a & 255 ) - ( $b & 255 ) ) > 24 ) {
					++$jumps;
				}
			}

			$cover[ $i ] = $jumps / $lines;
		}

		$mean = array_sum( $cover ) / $size;
		$best = 0;
		$edge = 0.0;

		for ( $start = (int) ceil( 0.05 * $size ); $start <= (int) floor( 0.4 * $size ); $start++ ) {
			$end   = $size - $start;
			$score = min(
				max( $cover[ $start - 1 ], $cover[ $start ], $cover[ $start + 1 ] ),
				max( $cover[ $end - 1 ], $cover[ $end ], $cover[ min( $size - 1, $end + 1 ) ] )
			);

			if ( $score > $edge ) {
				$edge = $score;
				$best = $start;
			}
		}

		if ( $edge < 0.3 || $edge < 5 * $mean ) {
			continue;
		}

		$rect = $columns
			? array( 'x' => $best, 'y' => 0, 'width' => $size - 2 * $best, 'height' => $h )
			: array( 'x' => 0, 'y' => $best, 'width' => $w, 'height' => $size - 2 * $best );

		$cropped = imagecrop( $im, $rect );

		if ( $cropped ) {
			$im = $cropped;
		}
	}

	return $im;
}

/**
 * Crop solid black bars (a widescreen clip in a 4:3 frame, bars inside a short's picture).
 *
 * An axis is cropped only when BOTH opposite edges are black bars of at least 2% (a bar is black when 98% of its
 * pixels are near black) and at least 20% of the image is left, so a dark scene or a one-sided shadow is never cut.
 *
 * @param GdImage $im Image.
 * @return GdImage The same image when nothing was cropped, else a cropped copy.
 */
function cptdp_video_crop_black( $im ) {
	$w = imagesx( $im );
	$h = imagesy( $im );

	// Whether one column (or row) is black.
	$black = static function ( int $fixed, bool $column ) use ( $im, $w, $h ): bool {
		$length = $column ? $h : $w;
		$step   = max( 1, (int) floor( $length / 64 ) );
		$total  = 0;
		$dark   = 0;

		for ( $i = 0; $i < $length; $i += $step ) {
			$c = $column ? imagecolorat( $im, $fixed, $i ) : imagecolorat( $im, $i, $fixed );

			++$total;

			if ( max( ( $c >> 16 ) & 255, ( $c >> 8 ) & 255, $c & 255 ) <= 40 ) {
				++$dark;
			}
		}

		return $total > 0 && $dark / $total >= 0.98;
	};

	// Run of black lines from each end of one axis.
	$runs = static function ( int $size, bool $column ) use ( $black ): array {
		$start = 0;
		$end   = 0;

		while ( $start < $size && $black( $start, $column ) ) {
			++$start;
		}

		while ( $end < $size - $start && $black( $size - 1 - $end, $column ) ) {
			++$end;
		}

		return array( $start, $end );
	};

	list( $left, $right ) = $runs( $w, true );
	list( $top, $bottom ) = $runs( $h, false );

	$inset = 2; // Skips the soft edge JPEG leaves next to a bar.
	$x     = 0;
	$y     = 0;
	$cw    = $w;
	$ch    = $h;

	if ( $left >= $w * 0.02 && $right >= $w * 0.02 && $w - $left - $right >= $w * 0.2 ) {
		$x  = $left + $inset;
		$cw = $w - $left - $right - 2 * $inset;
	}

	if ( $top >= $h * 0.02 && $bottom >= $h * 0.02 && $h - $top - $bottom >= $h * 0.2 ) {
		$y  = $top + $inset;
		$ch = $h - $top - $bottom - 2 * $inset;
	}

	if ( ( $cw === $w && $ch === $h ) || $cw < 1 || $ch < 1 ) {
		return $im;
	}

	$cropped = imagecrop(
		$im,
		array(
			'x'      => $x,
			'y'      => $y,
			'width'  => $cw,
			'height' => $ch,
		)
	);

	return $cropped ? $cropped : $im;
}

/**
 * Trim the bars off a generated thumbnail so only the picture is left (see the two functions above).
 *
 * The file is rewritten as a JPEG only when something was cropped. Needs GD; without it nothing happens.
 *
 * @param string $path Image file (rewritten in place when cropped).
 * @return bool Whether the file was cropped.
 */
function cptdp_video_trim_bars( string $path ): bool {
	if ( ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $path ) ) {
		return false;
	}

	$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$im    = false !== $bytes ? @imagecreatefromstring( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if ( ! $im ) {
		return false;
	}

	imagepalettetotruecolor( $im );

	$out = cptdp_video_crop_black( cptdp_video_crop_seams( $im ) );

	if ( $out === $im ) {
		return false;
	}

	return imagejpeg( $out, $path, 90 );
}

/**
 * File extension for a downloaded or generated image file, from its content.
 *
 * @param string $path Image file.
 */
function cptdp_video_image_ext( string $path ): string {
	$mime = wp_get_image_mime( $path );

	return 'image/png' === $mime ? 'png' : ( 'image/webp' === $mime ? 'webp' : 'jpg' );
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

	cptdp_video_trim_bars( $tmp );

	$id = CPTDP_Generated_Image::sideload( $post_id, $tmp, 'video-' . $post_id . '-thumbnail.' . cptdp_video_image_ext( $tmp ) );

	wp_delete_file( $tmp );

	if ( is_wp_error( $id ) ) {
		cptdp_video_autofill_note( $post_id, $id->get_error_message() . ' ' . __( 'Set the thumbnail by hand.', 'custom-post-types-dp' ) );

		return;
	}

	CPTDP_Generated_Image::adopt( $post_id, (int) $id, 'embed:' . $source['embed']['url'] );
}

/**
 * Pixel size of an uploaded video, as core stored it at upload or reads it now.
 *
 * @param int $file_id Attachment ID.
 * @return array{0:int,1:int} Width, height (0, 0 when unknown).
 */
function cptdp_video_file_size( int $file_id ): array {
	$meta = wp_get_attachment_metadata( $file_id );
	$w    = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
	$h    = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;

	if ( $w < 1 || $h < 1 ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$path = (string) get_attached_file( $file_id );
		$read = '' !== $path && is_file( $path ) ? wp_read_video_metadata( $path ) : false;
		$w    = is_array( $read ) ? (int) ( $read['width'] ?? 0 ) : 0;
		$h    = is_array( $read ) ? (int) ( $read['height'] ?? 0 ) : 0;
	}

	return array( max( 0, $w ), max( 0, $h ) );
}

/**
 * The `aspect_ratio` term slug a size is closest to, '' when it is none of them (more than 6% off).
 *
 * @param int $width  Width.
 * @param int $height Height.
 */
function cptdp_video_ratio_slug( int $width, int $height ): string {
	if ( $width < 1 || $height < 1 ) {
		return '';
	}

	$ratio = $width / $height;
	$best  = '';
	$off   = 0.06;

	foreach ( array(
		'16x9' => 16 / 9,
		'9x16' => 9 / 16,
		'1x1'  => 1,
		'4x3'  => 4 / 3,
	) as $slug => $target ) {
		$gap = abs( $ratio - $target ) / $target;

		if ( $gap < $off ) {
			$best = $slug;
			$off  = $gap;
		}
	}

	return $best;
}

/**
 * Fill the aspect ratio: from the file's size, else Vimeo's, else a YouTube Shorts link.
 *
 * A ratio Dave picked is kept: ours is recorded in `cptdp_ratio_auto` (and its source in `cptdp_ratio_source`),
 * and without a record only the unchosen default (16x9) or no term at all may be replaced.
 *
 * @param int                                                   $post_id Video ID.
 * @param array{key:string,file:int,embed:array<string,string>} $source  Current source.
 */
function cptdp_video_autofill_ratio( int $post_id, array $source ): void {
	$terms   = wp_get_object_terms( $post_id, 'aspect_ratio', array( 'fields' => 'slugs' ) );
	$current = is_array( $terms ) && $terms ? (string) $terms[0] : '';
	$auto    = (string) get_post_meta( $post_id, 'cptdp_ratio_auto', true );

	if ( '' !== $auto ? $current !== $auto : ! in_array( $current, array( '', '16x9' ), true ) ) {
		return; // Dave's choice.
	}

	if ( '' !== $auto && (string) get_post_meta( $post_id, 'cptdp_ratio_source', true ) === $source['key'] ) {
		return;
	}

	$slug = '';

	if ( $source['file'] > 0 ) {
		$slug = cptdp_video_ratio_slug( ...cptdp_video_file_size( $source['file'] ) );
	} elseif ( $source['embed'] && 'vimeo' === $source['embed']['provider'] && cptdp_video_autofill_enabled() ) {
		$data = cptdp_video_vimeo_data( $source['embed']['url'] );
		$slug = is_wp_error( $data ) ? '' : cptdp_video_ratio_slug( $data['width'], $data['height'] );
	} elseif ( $source['embed'] && 'youtube' === $source['embed']['provider'] ) {
		$shorts = (string) get_post_meta( $post_id, 'cptdp_embed_url', true ) . ' ' . (string) get_post_meta( $post_id, 'cptdp_post_url', true );
		$slug   = false !== strpos( $shorts, 'youtube.com/shorts/' ) ? '9x16' : '';
	}

	if ( '' === $slug || ! term_exists( $slug, 'aspect_ratio' ) ) {
		return;
	}

	wp_set_object_terms( $post_id, $slug, 'aspect_ratio' );
	update_post_meta( $post_id, 'cptdp_ratio_auto', $slug );
	update_post_meta( $post_id, 'cptdp_ratio_source', $source['key'] );
}

/**
 * The preview clip the plugin made for a video, 0 when none (or it no longer exists).
 *
 * @param int $post_id Video ID.
 */
function cptdp_video_preview_generated( int $post_id ): int {
	$id = (int) get_post_meta( $post_id, 'cptdp_video_preview_generated', true );

	return $id > 0 && 'attachment' === get_post_type( $id ) ? $id : 0;
}

/**
 * Whose preview a video has: `generated`, `editor` (set by anyone else) or '' when it has none.
 *
 * @param int $post_id Video ID.
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
 *
 * @param int $post_id Video ID.
 */
function cptdp_video_preview_source( int $post_id ): string {
	$ours = cptdp_video_preview_generated( $post_id );

	return $ours > 0 ? (string) get_post_meta( $ours, CPTDP_Generated_Image::META_FROM, true ) : '';
}

/**
 * Forget and delete ours.
 *
 * @param int $post_id Video ID.
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
 * Largest clip the route accepts, in bytes.
 */
function cptdp_video_preview_max_bytes(): int {
	return max( 1, (int) apply_filters( 'cptdp_video_preview_max_bytes', 8 * MB_IN_BYTES ) );
}

/**
 * Container of an uploaded clip from its first bytes: `mp4`, `webm` or ''.
 *
 * @param string $body Raw bytes.
 */
function cptdp_video_preview_kind( string $body ): string {
	if ( 'ftyp' === substr( $body, 4, 4 ) ) {
		return 'mp4';
	}

	return "\x1A\x45\xDF\xA3" === substr( $body, 0, 4 ) ? 'webm' : '';
}

/**
 * The browser makes the clip (D16): the server only drops ours when the video has no file any more.
 *
 * @param int                                                   $post_id Video ID.
 * @param array{key:string,file:int,embed:array<string,string>} $source  Current source.
 */
function cptdp_video_autofill_preview( int $post_id, array $source ): void {
	if ( 'editor' !== cptdp_video_preview_owner( $post_id ) && $source['file'] < 1 ) {
		cptdp_video_preview_remove( $post_id );
	}
}

/**
 * Entry point, called at the end of cptdp_video_save_meta() (D7).
 *
 * @param int $post_id Video ID.
 */
function cptdp_video_autofill( int $post_id ): void {
	$source = cptdp_video_source( $post_id );

	cptdp_video_autofill_length( $post_id, $source );
	cptdp_video_autofill_ratio( $post_id, $source );
	cptdp_video_autofill_preview( $post_id, $source );
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
		cptdp_video_preview_remove( (int) $post_id );
	}
}
add_action( 'before_delete_post', 'cptdp_video_autofill_cleanup' );

/**
 * An attachment is being deleted: when it is our preview clip (deleted in Media), forget it.
 *
 * @param int $attachment_id Attachment ID.
 */
function cptdp_video_preview_on_delete_attachment( $attachment_id ): void {
	$post_id = (int) get_post_meta( (int) $attachment_id, CPTDP_Generated_Image::META_FOR, true );

	if ( $post_id < 1 || (int) get_post_meta( $post_id, 'cptdp_video_preview_generated', true ) !== (int) $attachment_id ) {
		return;
	}

	delete_post_meta( $post_id, 'cptdp_video_preview_generated' );

	if ( (int) get_post_meta( $post_id, 'cptdp_video_preview', true ) === (int) $attachment_id ) {
		delete_post_meta( $post_id, 'cptdp_video_preview' );
	}
}
add_action( 'delete_attachment', 'cptdp_video_preview_on_delete_attachment' );

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

/**
 * Who may use the video routes: someone who can edit that video.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function cptdp_video_route_permission( WP_REST_Request $request ) {
	$post_id = (int) $request['id'];

	if ( 'video' !== get_post_type( $post_id ) ) {
		return new WP_Error( 'cptdp_not_found', __( 'Not found.', 'custom-post-types-dp' ), array( 'status' => 404 ) );
	}

	return current_user_can( 'edit_post', $post_id )
		? true
		: new WP_Error( 'cptdp_forbidden', __( 'You are not allowed to edit this item.', 'custom-post-types-dp' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
}

/**
 * Arguments of the video routes.
 *
 * @return array<string,array<string,mixed>>
 */
function cptdp_video_route_args(): array {
	return array(
		'id'   => array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		),
		'file' => array(
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
		),
	);
}

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
			'permission_callback' => 'cptdp_video_route_permission',
			'args'                => cptdp_video_route_args(),
		)
	);

	register_rest_route(
		CPTDP_Page_REST::NAMESPACE_ID,
		'/videos/(?P<id>\d+)/preview',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'cptdp_video_preview_upload',
			'permission_callback' => 'cptdp_video_route_permission',
			'args'                => cptdp_video_route_args(),
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

	// REST requests do not load the admin file functions.
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$tmp = wp_tempnam( 'cptdp-poster' );

	if ( '' === $tmp || false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		return new WP_Error( 'cptdp_poster_write', __( 'The image could not be saved.', 'custom-post-types-dp' ), array( 'status' => 500 ) );
	}

	cptdp_video_trim_bars( $tmp );

	$id = CPTDP_Generated_Image::sideload( $post_id, $tmp, 'video-' . $post_id . '-thumbnail.' . cptdp_video_image_ext( $tmp ) );
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

/**
 * Store the uploaded 6 second clip as the video's generated preview.
 *
 * POST cptdp/v1/videos/<id>/preview?file=<attachment id>   body: raw MP4 or WebM, edit_post + REST nonce.
 * 200 { id, url, kept } where `kept` is true (and nothing was made) when the preview is not ours.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function cptdp_video_preview_upload( WP_REST_Request $request ) {
	$post_id = (int) $request['id'];
	$file    = (int) $request['file'];

	if ( $file < 1 || ! wp_attachment_is( 'video', $file ) ) {
		return new WP_Error( 'cptdp_bad_file', __( 'The file is not a video.', 'custom-post-types-dp' ), array( 'status' => 400 ) );
	}

	if ( 'editor' === cptdp_video_preview_owner( $post_id ) ) {
		return rest_ensure_response( array( 'kept' => true ) );
	}

	$body = $request->get_body();

	if ( '' === $body || strlen( $body ) > cptdp_video_preview_max_bytes() ) {
		return new WP_Error( 'cptdp_bad_clip', __( 'The clip is empty or too large.', 'custom-post-types-dp' ), array( 'status' => 413 ) );
	}

	$kind = cptdp_video_preview_kind( $body );

	if ( '' === $kind ) {
		return new WP_Error( 'cptdp_bad_clip', __( 'The clip must be an MP4 or WebM video.', 'custom-post-types-dp' ), array( 'status' => 415 ) );
	}

	// REST requests do not load the admin file functions.
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$tmp = wp_tempnam( 'cptdp-preview' );

	if ( '' === $tmp || false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		return new WP_Error( 'cptdp_preview_write', __( 'The clip could not be saved.', 'custom-post-types-dp' ), array( 'status' => 500 ) );
	}

	$id = CPTDP_Generated_Image::sideload( $post_id, $tmp, 'video-' . $post_id . '-preview.' . $kind );
	wp_delete_file( $tmp );

	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'cptdp_preview_failed', $id->get_error_message(), array( 'status' => 500 ) );
	}

	$old = cptdp_video_preview_generated( $post_id );

	update_post_meta( (int) $id, CPTDP_Generated_Image::META_FOR, $post_id );
	update_post_meta( (int) $id, CPTDP_Generated_Image::META_FROM, 'file:' . $file );
	update_post_meta( $post_id, 'cptdp_video_preview', (int) $id );
	update_post_meta( $post_id, 'cptdp_video_preview_generated', (int) $id );

	// The new clip is in place first, so a page never shows a blank.
	if ( $old > 0 && $old !== (int) $id ) {
		wp_delete_attachment( $old, true );
	}

	return rest_ensure_response(
		array(
			'id'   => (int) $id,
			'url'  => (string) wp_get_attachment_url( (int) $id ),
			'kept' => false,
		)
	);
}
