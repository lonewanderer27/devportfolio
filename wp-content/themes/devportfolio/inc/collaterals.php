<?php
/**
 * Brand Collaterals page helpers: grid item, preview, pill, stats, thumbnails, modal payload and strings.
 *
 * Required by functions.php.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_collateral_meta_line' ) ) :
	/**
	 * The detail line of a collateral item (DPC-006): category, paper size and page count, joined by " · ".
	 *
	 * A missing part is left out ("1 p" for one page), and the line is '' when all three are missing.
	 * Plain text (escape on output).
	 *
	 * @param int $post_id Collateral ID.
	 * @return string
	 */
	function devportfolio_collateral_meta_line( $post_id ) {
		$post_id = (int) $post_id;
		$parts   = array_filter(
			array(
				devportfolio_document_category_name( $post_id, 'collateral_category' ),
				trim( (string) get_post_meta( $post_id, 'cptdp_paper_size', true ) ),
				devportfolio_page_count_label( get_post_meta( $post_id, 'cptdp_total_pages', true ) ),
			),
			'strlen'
		);

		return implode( ' · ', $parts );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_preview_from' ) ) :
	/**
	 * Chooses what a collateral tile shows, from already-read data (DPC-006).
	 *
	 * In order: page 1 of a valid page set (its thumbnail, with page 1 as the larger candidate), the featured
	 * image, then the numbered blank page. A `pending` page set with neither becomes `loading` instead of the
	 * blank, while every other state keeps the blank (DPC-025). The tile ratio is the uncropped page ratio
	 * (DPC-014 D7); the placeholder is A4 (portrait), or landscape when the paper size says so.
	 *
	 * Pure except for wp_get_attachment_image_src() when a featured image is given.
	 *
	 * @param array|null $stored     Result of cptdp_get_collateral_pages(), or null.
	 * @param int        $featured_id Featured image attachment ID, 0 for none.
	 * @param string     $paper_size  Paper size, e.g. "A4 Landscape".
	 * @param string     $state      Page set status (DPC-025): none, pending, ready, needs_browser or failed.
	 * @return array{kind:string,src:string,srcset:string,width:int,height:int,ratio:float,featured_id:int}
	 */
	function devportfolio_collateral_preview_from( $stored, int $featured_id, string $paper_size, string $state = '' ) {
		if ( is_array( $stored ) ) {
			$page   = $stored['pages'][0] ?? '';
			$width  = (int) ( $stored['width'] ?? 0 );
			$height = (int) ( $stored['height'] ?? 0 );

			if ( is_string( $page ) && '' !== $page && $width > 0 && $height > 0 ) {
				$thumb     = ( isset( $stored['thumbs'] ) && is_array( $stored['thumbs'] ) ) ? ( $stored['thumbs'][0] ?? '' ) : '';
				$thumb_w   = (int) ( $stored['thumb_width'] ?? 0 );
				$has_thumb = is_string( $thumb ) && '' !== $thumb && $thumb_w > 0;

				return array(
					'kind'        => 'page',
					'src'         => $has_thumb ? $thumb : $page,
					'srcset'      => $has_thumb ? $thumb . ' ' . $thumb_w . 'w, ' . $page . ' ' . $width . 'w' : $page . ' ' . $width . 'w',
					'width'       => $width,
					'height'      => $height,
					'ratio'       => round( $width / $height, 4 ),
					'featured_id' => 0,
				);
			}
		}

		if ( $featured_id > 0 ) {
			$image = wp_get_attachment_image_src( $featured_id, 'full' );
			if ( is_array( $image ) && (int) $image[1] > 0 && (int) $image[2] > 0 ) {
				return array(
					'kind'        => 'featured',
					'src'         => '',
					'srcset'      => '',
					'width'       => (int) $image[1],
					'height'      => (int) $image[2],
					'ratio'       => round( (int) $image[1] / (int) $image[2], 4 ),
					'featured_id' => $featured_id,
				);
			}
		}

		$landscape = false !== stripos( $paper_size, 'landscape' );

		return array(
			'kind'        => 'pending' === $state ? 'loading' : 'blank',
			'src'         => '',
			'srcset'      => '',
			'width'       => 0,
			'height'      => 0,
			'ratio'       => round( $landscape ? M_SQRT2 : 1 / M_SQRT2, 4 ),
			'featured_id' => 0,
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_preview' ) ) :
	/**
	 * The preview of a collateral, read from its stored page images, featured image, paper size and status.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array{kind:string,src:string,srcset:string,width:int,height:int,ratio:float,featured_id:int}
	 */
	function devportfolio_collateral_preview( $post_id ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'collateral_preview',
			$post_id,
			static function () use ( $post_id ) {
				$stored = function_exists( 'cptdp_get_collateral_pages' ) ? cptdp_get_collateral_pages( $post_id ) : null;
				$state  = function_exists( 'cptdp_get_collateral_pages_status' ) ? cptdp_get_collateral_pages_status( $post_id ) : '';

				return devportfolio_collateral_preview_from(
					$stored,
					(int) get_post_thumbnail_id( $post_id ),
					(string) get_post_meta( $post_id, 'cptdp_paper_size', true ),
					(string) $state
				);
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_item' ) ) :
	/**
	 * Everything the collateral grid shows for one collateral (DPC-006).
	 *
	 * @param int $post_id Collateral ID.
	 * @return array{title:string,meta:string,categories:string[],preview:array}|null Null when the post is not a collateral.
	 */
	function devportfolio_collateral_item( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'collateral' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs = devportfolio_term_slugs( $post_id, 'collateral_category' );

		return array(
			'title'      => get_the_title( $post_id ),
			'meta'       => devportfolio_collateral_meta_line( $post_id ),
			'categories' => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'preview'    => devportfolio_collateral_preview( $post_id ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_pill' ) ) :
	/**
	 * The paper size and page count pill of the collateral sidebar, e.g. "A4 · 2 pp" (DPC-007).
	 *
	 * A missing part is left out ("A4", "2 pp"); '' when both are missing. Plain text.
	 *
	 * @param mixed $paper_size Paper size, e.g. "A4".
	 * @param mixed $pages      Page count.
	 * @return string
	 */
	function devportfolio_collateral_pill( $paper_size, $pages ) {
		$parts = array_filter(
			array(
				is_scalar( $paper_size ) ? trim( (string) $paper_size ) : '',
				devportfolio_page_count_label( $pages ),
			),
			'strlen'
		);

		return implode( ' · ', $parts );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_stats' ) ) :
	/**
	 * The Results rows of a collateral: `cptdp_post_statistics`, in saved order (DPC-008).
	 *
	 * Only well-formed rows are kept; a row with both label and value empty is dropped. Plain text.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array<int,array{label:string,value:string}>
	 */
	function devportfolio_collateral_stats( $post_id ) {
		$rows = get_post_meta( (int) $post_id, 'cptdp_post_statistics', true );
		$text = static function ( $row, $key ) {
			return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? trim( wp_strip_all_tags( (string) $row[ $key ] ) ) : '';
		};

		$stats = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = $text( $row, 'label' );
			$value = $text( $row, 'value' );
			if ( '' === $label && '' === $value ) {
				continue;
			}
			$stats[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $stats;
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_thumb' ) ) :
	/**
	 * The thumbnail of a Next collaterals item, from a collateral preview (DPC-010).
	 *
	 * A stored page uses its thumbnail set; a featured image its medium size (the full size when there is none);
	 * a pending page set uses the loading image (DPC-025); a blank page has no `src`, so the script draws the
	 * numbered blank.
	 *
	 * @param array $preview Result of devportfolio_collateral_preview().
	 * @return array{src:string,srcset:string,ratio:float}
	 */
	function devportfolio_collateral_thumb( array $preview ) {
		$kind   = $preview['kind'] ?? 'blank';
		$src    = '';
		$srcset = '';

		if ( 'page' === $kind ) {
			$src    = (string) $preview['src'];
			$srcset = (string) $preview['srcset'];
		} elseif ( 'featured' === $kind ) {
			$src = (string) wp_get_attachment_image_url( (int) $preview['featured_id'], 'medium' );
		} elseif ( 'loading' === $kind && function_exists( 'cptdp_collateral_loading_image_url' ) ) {
			$src = cptdp_collateral_loading_image_url();
		}

		return array(
			'src'    => $src,
			'srcset' => $srcset,
			'ratio'  => (float) ( $preview['ratio'] ?? 0 ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_modal_payload' ) ) :
	/**
	 * Everything the collateral modal sidebar and Next list need for one collateral (DPC-007 to DPC-011, D5).
	 *
	 * Plain text and URLs only, written with textContent by the script. Null when the post is not a collateral.
	 * The render passes published collaterals only.
	 *
	 * @param int $post_id Collateral ID.
	 * @return array<string,mixed>|null
	 */
	function devportfolio_collateral_modal_payload( $post_id ) {
		$post_id = (int) $post_id;
		if ( 'collateral' !== get_post_type( $post_id ) ) {
			return null;
		}

		$slugs    = devportfolio_term_slugs( $post_id, 'collateral_category' );
		$download = devportfolio_document_download_target( $post_id, 'cptdp_collateral_file' );
		$viewer   = devportfolio_document_viewer_data( $post_id );

		// The file type: the stored one, else the extension of the attached file (a collateral has no filetype field).
		$filetype = (string) get_post_meta( $post_id, 'cptdp_filetype', true );
		$file_id  = (int) get_post_meta( $post_id, 'cptdp_collateral_file', true );
		if ( '' === $filetype && $file_id > 0 ) {
			$filetype = strtolower( (string) pathinfo( (string) get_attached_file( $file_id ), PATHINFO_EXTENSION ) );
		}
		$total = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );

		// What the viewer can show (DPC-025): a real preview, one on its way, or nothing.
		$page_status = function_exists( 'cptdp_get_collateral_pages_status' ) ? cptdp_get_collateral_pages_status( $post_id ) : '';
		if ( ! empty( $viewer['images'] ) || '' !== $viewer['viewerUrl'] ) {
			$preview_state = 'ready';
		} elseif ( 'pending' === $page_status ) {
			$preview_state = 'pending';
		} else {
			$preview_state = 'unavailable';
		}

		return devportfolio_media_payload_base(
			$post_id,
			'collateral_category',
			// The raw title: get_the_title() runs wptexturize, which would curl the quotes in the payload.
			html_entity_decode( (string) get_post_field( 'post_title', $post_id ), ENT_QUOTES, 'UTF-8' )
		) + array(
			'categories'   => is_wp_error( $slugs ) ? array() : array_values( $slugs ),
			'pill'         => devportfolio_collateral_pill(
				get_post_meta( $post_id, 'cptdp_paper_size', true ),
				get_post_meta( $post_id, 'cptdp_total_pages', true )
			),
			'stats'        => devportfolio_collateral_stats( $post_id ),
			// The viewer (DPC-012): the same keys as a document (D6). The stored pages win over the typed count.
			'fileType'     => devportfolio_document_file_type_label( $filetype ),
			'format'       => devportfolio_document_format_label( $filetype, $total, $viewer['kind'] ),
			'kind'         => $viewer['kind'],
			'pages'        => count( $viewer['images'] ) > 0 ? count( $viewer['images'] ) : max( 0, $total ),
			'downloadMode' => $download['mode'],
			'downloadUrl'  => $download['url'],
			'images'       => $viewer['images'],
			'imageWidth'   => $viewer['imageWidth'],
			'cropBottom'   => $viewer['cropBottom'],
			'ratio'        => $viewer['ratio'],
			'viewerUrl'    => $viewer['viewerUrl'],
			'nativeUrl'    => $viewer['nativeUrl'],
			'pdfBytes'     => $viewer['pdfBytes'],
			'previewState' => $preview_state,
			'thumb'        => devportfolio_collateral_thumb( devportfolio_collateral_preview( $post_id ) ),
		) + devportfolio_document_pages_extras( $post_id );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_mobile_strings' ) ) :
	/**
	 * The mobile viewer's and the mobile Details sheet's words for a collateral (`data-mobile-strings`, DPC-015 to DPC-019).
	 *
	 * The shared mobile body words (devportfolio_document_mobile_strings()) with the collateral's own names where
	 * they differ: the region, the round Details button, and the sidebar's Copy link wording for the sheet.
	 *
	 * @return array<string,string>
	 */
	function devportfolio_collateral_mobile_strings() {
		return array_merge(
			devportfolio_document_mobile_strings(),
			array(
				'region'    => _x( 'Collateral pages', 'collateral modal mobile', 'devportfolio' ),
				'details'   => _x( 'Collateral details', 'collateral modal mobile', 'devportfolio' ),
				'results'   => _x( 'Results', 'collateral modal mobile details sheet', 'devportfolio' ),
				'madeWith'  => _x( 'Made with', 'collateral modal mobile details sheet', 'devportfolio' ),
				'copyLink'  => _x( 'Copy link', 'collateral modal mobile details sheet button', 'devportfolio' ),
				'copied'    => _x( 'Copied', 'collateral modal mobile details sheet', 'devportfolio' ),
				'copyHint'  => _x( 'Press Ctrl/⌘ + C', 'collateral modal mobile details sheet', 'devportfolio' ),
			)
		);
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_modal_strings' ) ) :
	/**
	 * The few strings the collateral modal script writes, as `data-strings` on its root (no English in JS).
	 *
	 * @return array<string,string>
	 */
	function devportfolio_collateral_modal_strings() {
		return array(
			/* translators: %s: collateral title. */
			'open'     => _x( 'Open %s', 'collateral modal', 'devportfolio' ),
			'copied'   => _x( 'Copied', 'collateral modal', 'devportfolio' ),
			'copyHint' => _x( 'Press Ctrl/⌘ + C', 'collateral modal', 'devportfolio' ),
		);
	}
endif;
