<?php
/**
 * Videos page helpers: grid item, preview URL, length and shape labels, embeds, modal payload, mobile controls and strings.
 *
 * Required by functions.php.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_video_preview_url' ) ) :
	/**
	 * Source of a video's hover preview (DPV-007).
	 *
	 * The stored preview clip when it is an MP4 or WebM attachment, else the uploaded video file, else nothing
	 * (an embed-only video never previews). `clip` says which of the two it is.
	 *
	 * @param mixed $clip_id Attachment ID of the preview clip (`cptdp_video_preview`).
	 * @param mixed $file_id Attachment ID of the uploaded file (`cptdp_video_file`).
	 * @return array{url:string,clip:bool}
	 */
	function devportfolio_video_preview_url( $clip_id, $file_id ) {
		$clip_id = (int) $clip_id;
		if ( $clip_id > 0 && in_array( get_post_mime_type( $clip_id ), array( 'video/mp4', 'video/webm' ), true ) ) {
			$url = wp_get_attachment_url( $clip_id );
			if ( $url ) {
				return array(
					'url'  => $url,
					'clip' => true,
				);
			}
		}

		$file_id = (int) $file_id;
		if ( $file_id > 0 && 0 === strpos( (string) get_post_mime_type( $file_id ), 'video/' ) ) {
			$url = wp_get_attachment_url( $file_id );
			if ( $url ) {
				return array(
					'url'  => $url,
					'clip' => false,
				);
			}
		}

		return array(
			'url'  => '',
			'clip' => false,
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_item' ) ) :
	/**
	 * Everything the Videos grid needs for one video (DPV-006, DPV-007).
	 *
	 * @param int $post_id Video ID.
	 * @return array{title:string,categories:string[],poster_id:int,preview:array{url:string,clip:bool}}|null Null when the post is not a video.
	 */
	function devportfolio_video_item( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'video' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs = devportfolio_term_slugs( $post_id, 'video_category' );

		return array(
			'title'      => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'categories' => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'poster_id'  => (int) get_post_thumbnail_id( $post_id ),
			'ratio'      => devportfolio_video_ratio_slug( $post_id ),
			'preview'    => devportfolio_video_preview_url(
				get_post_meta( $post_id, 'cptdp_video_preview', true ),
				get_post_meta( $post_id, 'cptdp_video_file', true )
			),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_length_label' ) ) :
	/**
	 * A length in seconds as m:ss, or h:mm:ss from one hour (DPV-015). '' for 0 or less.
	 *
	 * @param mixed $seconds Length in seconds.
	 * @return string
	 */
	function devportfolio_video_length_label( $seconds ) {
		$seconds = (int) $seconds;
		if ( $seconds <= 0 ) {
			return '';
		}
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		return $h > 0 ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
	}
endif;

if ( ! function_exists( 'devportfolio_video_ratio_slug' ) ) :
	/**
	 * The slug of the video's aspect_ratio term (16x9, 9x16, 1x1, 4x3), '' when none (DPV-010, DPV-012).
	 *
	 * @param int $post_id Video ID.
	 * @return string
	 */
	function devportfolio_video_ratio_slug( $post_id ) {
		$slugs = devportfolio_term_slugs( (int) $post_id, 'aspect_ratio' );
		if ( is_wp_error( $slugs ) || empty( $slugs ) ) {
			return '';
		}
		$slug = (string) reset( $slugs );
		return in_array( $slug, array( '16x9', '9x16', '1x1', '4x3' ), true ) ? $slug : '';
	}
endif;

if ( ! function_exists( 'devportfolio_video_shape_label' ) ) :
	/**
	 * The shape badge text: 9x16 -> 9:16 ('' for an unknown slug) (DPV-012).
	 *
	 * @param string $slug Ratio slug.
	 * @return string
	 */
	function devportfolio_video_shape_label( $slug ) {
		return in_array( $slug, array( '16x9', '9x16', '1x1', '4x3' ), true ) ? str_replace( 'x', ':', $slug ) : '';
	}
endif;

if ( ! function_exists( 'devportfolio_video_embed_src' ) ) :
	/**
	 * The iframe source for a YouTube or Vimeo page URL, '' for anything else (DPV-010, D8).
	 *
	 * @param mixed $url Stored embed URL (`cptdp_embed_url`).
	 * @return string
	 */
	function devportfolio_video_embed_src( $url ) {
		$url   = is_string( $url ) ? trim( $url ) : '';
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return '';
		}
		$host = strtolower( preg_replace( '/^(www\.|m\.)/', '', $parts['host'] ) );
		$path = (string) ( $parts['path'] ?? '' );
		$id   = '';

		if ( 'youtu.be' === $host ) {
			$id = trim( $path, '/' );
		} elseif ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com' ), true ) ) {
			if ( preg_match( '#^/(?:embed|shorts|live)/([\w-]{6,20})#', $path, $m ) ) {
				$id = $m[1];
			} elseif ( '/watch' === $path ) {
				parse_str( (string) ( $parts['query'] ?? '' ), $query );
				$id = isset( $query['v'] ) && is_string( $query['v'] ) ? $query['v'] : '';
			}
			if ( preg_match( '/^[\w-]{6,20}$/', $id ) ) {
				return 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&playsinline=1';
			}
			return '';
		} elseif ( in_array( $host, array( 'vimeo.com', 'player.vimeo.com' ), true ) ) {
			if ( preg_match( '#/(\d{5,12})(?:/|$)#', $path, $m ) ) {
				return 'https://player.vimeo.com/video/' . $m[1] . '?playsinline=1';
			}
			return '';
		}

		return ( '' !== $id && preg_match( '/^[\w-]{6,20}$/', $id ) ) ? 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&playsinline=1' : '';
	}
endif;

if ( ! function_exists( 'devportfolio_video_modal_payload' ) ) :
	/**
	 * Everything the video modal and its Next up list need for one video (DPV-009 to DPV-016).
	 *
	 * Plain text and URLs only, written with textContent / properties by the script. Null when the post is not a video.
	 *
	 * @param int $post_id Video ID.
	 * @return array<string,mixed>|null
	 */
	function devportfolio_video_modal_payload( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'video' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs    = devportfolio_term_slugs( $post_id, 'video_category' );
		$ratio    = devportfolio_video_ratio_slug( $post_id );
		$file_id  = (int) get_post_meta( $post_id, 'cptdp_video_file', true );
		$file_url = ( $file_id > 0 && 0 === strpos( (string) get_post_mime_type( $file_id ), 'video/' ) ) ? (string) wp_get_attachment_url( $file_id ) : '';
		$post_url = (string) get_post_meta( $post_id, 'cptdp_post_url', true );
		$post_url = ( '' !== $post_url && in_array( wp_parse_url( $post_url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) ? esc_url_raw( $post_url ) : '';
		$poster   = (int) get_post_thumbnail_id( $post_id );

		return devportfolio_media_payload_base(
			$post_id,
			'video_category',
			html_entity_decode( (string) get_post_field( 'post_title', $post_id ), ENT_QUOTES, 'UTF-8' )
		) + array(
			'categories' => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'ratio'      => $ratio,
			'pane'       => '9x16' === $ratio ? 'tall' : 'wide',
			'shape'      => devportfolio_video_shape_label( $ratio ),
			'stats'      => devportfolio_collateral_stats( $post_id ),
			'length'     => devportfolio_video_length_label( get_post_meta( $post_id, 'cptdp_length', true ) ),
			'fileUrl'    => $file_url,
			'embedSrc'   => '' === $file_url ? devportfolio_video_embed_src( get_post_meta( $post_id, 'cptdp_embed_url', true ) ) : '',
			'postUrl'    => $post_url,
			'poster'     => $poster > 0 ? (string) wp_get_attachment_image_url( $poster, 'medium_large' ) : '',
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_modal_strings' ) ) :
	/**
	 * Strings the video modal script needs (DPV-015, DPV-016).
	 *
	 * @return array<string,string>
	 */
	function devportfolio_video_modal_strings() {
		return array(
			/* translators: %s: video title. */
			'open'      => _x( 'Open %s', 'video modal', 'devportfolio' ),
			'copied'    => _x( 'Copied', 'video modal', 'devportfolio' ),
			'copyHint'  => _x( 'Press Ctrl/⌘ + C', 'video modal', 'devportfolio' ),
			/* translators: %s: video title. */
			'playerFor' => _x( 'Video: %s', 'video modal', 'devportfolio' ),
			'play'      => _x( 'Play', 'video modal', 'devportfolio' ),
			'pause'     => _x( 'Pause', 'video modal', 'devportfolio' ),
			'fullscreen'     => _x( 'Full screen', 'video modal', 'devportfolio' ),
			'exitFullscreen' => _x( 'Exit full screen', 'video modal', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_mobile_options' ) ) :
	/**
	 * The mobile viewer's switches (Settings → Videos). Same keys and defaults as the plugin's
	 * cptdp_videos_mobile_options(); a test pins that.
	 *
	 * @return array<string,bool>
	 */
	function devportfolio_video_mobile_options() {
		$defaults = array(
			'mute'            => true,
			'progress'        => true,
			'scrub'           => false,
			'time'            => false,
			'volume'          => false,
			'speed'           => false,
			'fit'             => true,
			'loop'            => true,
			'double_tap_skip' => true,
		);
		$saved    = function_exists( 'cptdp_get_videos_settings' ) ? cptdp_get_videos_settings() : array();
		$options  = array_map( 'boolval', array_merge( $defaults, is_array( $saved ) ? array_intersect_key( $saved, $defaults ) : array() ) );
		// Dragging needs the line.
		$options['progress'] = $options['progress'] || $options['scrub'];
		return $options;
	}
endif;

if ( ! function_exists( 'devportfolio_video_mobile_control_keys' ) ) :
	/**
	 * The control keys the mobile viewer has, in mount and markup order. One list drives both the markup and the
	 * script (`data-mobile-options.controls`), so they cannot disagree.
	 *
	 * @param array<string,bool> $options devportfolio_video_mobile_options().
	 * @return string[]
	 */
	function devportfolio_video_mobile_control_keys( array $options ) {
		$wanted = array(
			'bigplay'    => true,
			'replay'     => ! $options['loop'],
			'progress'   => $options['progress'],
			'time'       => $options['time'],
			// The volume slider mutes and unmutes too, so it replaces the mute button when it is on.
			'mute'       => $options['mute'] && ! $options['volume'],
			'volume'     => $options['volume'],
			'speed'      => $options['speed'],
			'fit'        => $options['fit'],
		);
		return array_keys( array_filter( $wanted ) );
	}
endif;

if ( ! function_exists( 'devportfolio_video_mobile_script_options' ) ) :
	/**
	 * What the script needs from the switches (`data-mobile-options` on the block root).
	 *
	 * @param array<string,bool> $options devportfolio_video_mobile_options().
	 * @return array<string,mixed>
	 */
	function devportfolio_video_mobile_script_options( array $options ) {
		return array(
			'loop'          => $options['loop'],
			'doubleTapSkip' => $options['double_tap_skip'],
			'unmuteOnPlay'  => ! $options['mute'],
			'scrub'         => $options['scrub'],
			'controls'      => devportfolio_video_mobile_control_keys( $options ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_mobile_controls' ) ) :
	/**
	 * The markup of the mobile viewer's controls: one block per enabled key. Mute, volume, speed and full screen
	 * share the extras column.
	 *
	 * @param array<string,bool> $options devportfolio_video_mobile_options().
	 * @return string
	 */
	function devportfolio_video_mobile_controls( array $options ) {
		$extras_keys = array( 'mute', 'volume', 'speed', 'fit' );
		$main        = '';
		$extras      = '';
		foreach ( devportfolio_video_mobile_control_keys( $options ) as $key ) {
			$html = devportfolio_video_mobile_control_html( $key, $options );
			if ( in_array( $key, $extras_keys, true ) ) {
				$extras .= $html;
			} else {
				$main .= $html;
			}
		}
		return $main . ( '' === $extras ? '' : '<div class="videos-mobile__extras">' . $extras . '</div>' );
	}
endif;

if ( ! function_exists( 'devportfolio_video_mobile_control_html' ) ) :
	/**
	 * The markup of one mobile control. Class names are `videos-mobile__<element>`: the same names the control
	 * modules in blocks/video-modal/controls/ look for.
	 *
	 * @param string             $key     A control key.
	 * @param array<string,bool> $options devportfolio_video_mobile_options().
	 * @return string
	 */
	function devportfolio_video_mobile_control_html( $key, array $options ) {
		$icon = static fn( $path ) => devportfolio_viewer_icon( 'videos-mobile', $path );
		switch ( $key ) {
			case 'bigplay':
				return '<span class="videos-mobile__bigplay" aria-hidden="true">' . $icon( 'M8 5l11 7-11 7z' ) . '</span>';
			case 'replay':
				return '<button class="videos-mobile__replay" type="button">' . esc_html_x( 'Replay', 'video modal', 'devportfolio' ) . '</button>';
			case 'progress':
				return sprintf(
					'<input class="videos-mobile__scrub" type="range" min="0" max="0" step="any" value="0" aria-label="%s"%s>',
					esc_attr_x( 'Seek', 'video modal', 'devportfolio' ),
					$options['scrub'] ? '' : ' inert aria-hidden="true"'
				);
			case 'time':
				return '<span class="videos-mobile__time" aria-hidden="true">0:00 / 0:00</span>';
			case 'mute':
				return sprintf(
					'<button class="videos-mobile__mutebtn videos-mobile__btn" type="button" aria-pressed="false" aria-label="%s">%s%s</button>',
					esc_attr_x( 'Mute', 'video modal mobile', 'devportfolio' ),
					str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--on"', $icon( 'M4 10v4h4l5 4V6L8 10H4zM16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11' ) ),
					str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--off"', $icon( 'M4 10v4h4l5 4V6L8 10H4zM17 9l4 6M21 9l-4 6' ) )
				);
			case 'volume':
				return sprintf(
					'<div class="videos-mobile__pop"><button class="videos-mobile__volbtn videos-mobile__btn" type="button" aria-expanded="false" aria-controls="videos-mobile-volume" aria-label="%1$s">%2$s</button><div class="videos-mobile__popover videos-mobile__popover--volume" id="videos-mobile-volume" hidden><input class="videos-mobile__volume" type="range" min="0" max="1" step="0.05" value="1" aria-label="%1$s"></div></div>',
					esc_attr_x( 'Volume', 'video modal', 'devportfolio' ),
					$icon( 'M4 10v4h4l5 4V6L8 10H4zM16 9a4 4 0 0 1 0 6' )
				);
			case 'speed':
				$options_html = '';
				foreach ( array( '0.5', '0.75', '1', '1.25', '1.5', '2' ) as $rate ) {
					$options_html .= sprintf( '<button class="videos-mobile__rateopt" type="button" data-rate="%1$s" aria-pressed="%2$s">%1$s×</button>', esc_attr( $rate ), '1' === $rate ? 'true' : 'false' );
				}
				return sprintf(
					'<div class="videos-mobile__pop"><button class="videos-mobile__ratebtn videos-mobile__btn" type="button" data-label="%1$s" aria-expanded="false" aria-controls="videos-mobile-rate">1×</button><div class="videos-mobile__popover videos-mobile__popover--rate" id="videos-mobile-rate" hidden>%2$s</div></div>',
					esc_attr_x( 'Playback speed', 'video modal', 'devportfolio' ),
					$options_html
				);
			case 'fit':
				// One icon per mode; the stylesheet shows the one for the current mode (data-fit, set by controls/fit.js).
				return sprintf(
					'<button class="videos-mobile__fit videos-mobile__btn" type="button" data-fit="fit" aria-label="%s">%s%s%s</button>',
					esc_attr_x( 'Fit to screen', 'video modal', 'devportfolio' ),
					str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--fit"', $icon( 'M3 7h18v10H3zM7 10v4' ) ),
					str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--zoom"', $icon( 'M10.5 4a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM20 20l-4.5-4.5M10.5 7.5v6M7.5 10.5h6' ) ),
					str_replace( 'class="videos-mobile__icon"', 'class="videos-mobile__icon videos-mobile__icon--stretch"', $icon( 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5' ) )
				);
		}
		return '';
	}
endif;

if ( ! function_exists( 'devportfolio_video_mobile_strings' ) ) :
	/**
	 * Words the mobile viewer and its sheet need (`data-mobile-strings`).
	 *
	 * @return array<string,string>
	 */
	function devportfolio_video_mobile_strings() {
		return array(
			/* translators: %s: video title. */
			'playerFor'      => _x( 'Video: %s', 'video modal', 'devportfolio' ),
			'results'        => _x( 'Results', 'video modal mobile', 'devportfolio' ),
			'madeWith'       => _x( 'Made with', 'video modal mobile', 'devportfolio' ),
			'openOriginal'   => _x( 'Open original post', 'video modal mobile', 'devportfolio' ),
			'copy'           => _x( 'Copy link', 'video modal button', 'devportfolio' ),
			'copied'         => _x( 'Copied', 'video modal', 'devportfolio' ),
			'handle'         => _x( 'Close details', 'video modal mobile', 'devportfolio' ),
			'fit'            => _x( 'Fit to screen', 'video modal', 'devportfolio' ),
			'zoom'           => _x( 'Zoom to fill', 'video modal', 'devportfolio' ),
			'stretch'        => _x( 'Stretch to fill', 'video modal', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_video_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published video single to its modal deeplink (DPV-009).
	 *
	 * @return string
	 */
	function devportfolio_video_single_redirect_url() {
		return devportfolio_modal_single_redirect_url( 'video', 'video_slug' );
	}
endif;
