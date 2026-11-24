<?php
/**
 * Home page helpers: hero, Selected work and Contact.
 *
 * Required by functions.php.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_hero_roles' ) ) :
	/**
	 * The Home hero's roles (DPH-003): the main (highlighted) role and the others, from the Job Role post type.
	 *
	 * No highlighted role: the first published role by order is the main one. No published role: both are empty.
	 *
	 * @since 0.35.0
	 *
	 * @return array{main: string, others: string[]}
	 */
	function devportfolio_hero_roles() {
		$ids = devportfolio_published_ids(
			'job_role',
			array(
				'orderby' => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		if ( empty( $ids ) ) {
			return array(
				'main'   => '',
				'others' => array(),
			);
		}

		$main_id = (int) $ids[0];
		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, 'cptdp_highlight', true ) ) {
				$main_id = (int) $id;
				break;
			}
		}

		$others = array();
		foreach ( $ids as $id ) {
			if ( (int) $id !== $main_id ) {
				$others[] = get_the_title( $id );
			}
		}

		return array(
			'main'   => get_the_title( $main_id ),
			'others' => $others,
		);
	}
endif;

if ( ! function_exists( 'devportfolio_hero_name_parts' ) ) :
	/**
	 * The site title split for the hero (DPH-001): the first word is outlined, the rest is solid.
	 *
	 * @since 0.35.0
	 *
	 * @return array{first: string, rest: string}
	 */
	/**
	 * The hero title (DPH-001): the Hero title setting in Settings > Contact, else the site title when that is blank.
	 *
	 * @since 0.35.0
	 */
	function devportfolio_hero_title() {
		$title = function_exists( 'cptdp_get_contact' ) ? trim( (string) cptdp_get_contact( 'hero_title' ) ) : '';

		return '' !== $title ? $title : trim( (string) get_bloginfo( 'name' ) );
	}

	function devportfolio_hero_name_parts() {
		$parts = preg_split( '/\s+/', devportfolio_hero_title(), 2 );

		return array(
			'first' => isset( $parts[0] ) ? (string) $parts[0] : '',
			'rest'  => isset( $parts[1] ) ? (string) $parts[1] : '',
		);
	}
endif;

if ( ! function_exists( 'devportfolio_hero_portrait_id' ) ) :
	/**
	 * The portrait's attachment ID from Settings > Contact, or 0 when none is set or the file is gone.
	 *
	 * @since 0.35.0
	 */
	function devportfolio_hero_portrait_id() {
		$id = function_exists( 'cptdp_get_contact' ) ? (int) cptdp_get_contact( 'portrait_id' ) : 0;

		return ( $id && wp_get_attachment_image_url( $id, 'large' ) ) ? $id : 0;
	}
endif;

if ( ! function_exists( 'devportfolio_hero_portrait_sizes' ) ) :
	/**
	 * The `sizes` attribute of the portrait: about 42% of the width on desktop (370px at most), 56% on phones.
	 * The <img> and the preload link both use it, so they always pick the same file.
	 *
	 * @since 0.35.0
	 */
	function devportfolio_hero_portrait_sizes() {
		return '(max-width: 900px) 56vw, min(370px, 42vw)';
	}
endif;

if ( ! function_exists( 'devportfolio_hero_preload_portrait' ) ) :
	/**
	 * Preload the portrait on the front page: it is the only image above the fold (DPH-001).
	 *
	 * @since 0.35.0
	 */
	function devportfolio_hero_preload_portrait() {
		if ( ! is_front_page() ) {
			return;
		}

		$id = devportfolio_hero_portrait_id();
		if ( ! $id ) {
			return;
		}

		$srcset = wp_get_attachment_image_srcset( $id, 'large' );

		printf(
			'<link rel="preload" as="image" href="%1$s"%2$s imagesizes="%3$s" fetchpriority="high">' . "\n",
			esc_url( (string) wp_get_attachment_image_url( $id, 'large' ) ),
			$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
			esc_attr( devportfolio_hero_portrait_sizes() )
		);
	}
endif;
add_action( 'wp_head', 'devportfolio_hero_preload_portrait', 2 );

if ( ! function_exists( 'devportfolio_work_picks' ) ) :
	/**
	 * IDs of the posts the owner picked for the Home "Selected work" cards (DPH-012, DPH-016, DPH-020).
	 *
	 * "Picked" is the existing `cptdp_highlight` checkbox ("Mark as highlight (Selected work)") that Documents, Videos
	 * and Collaterals already have, so nothing new is stored for Home. Published only, in Order then title order, at
	 * most `$limit`. No fallback: a post type with no highlight shows nothing on its card.
	 *
	 * @param string $post_type document, video or collateral.
	 * @param int    $limit     Most posts to return.
	 * @return int[]
	 */
	function devportfolio_work_picks( $post_type, $limit ) {
		return array_map(
			'intval',
			devportfolio_published_ids(
				$post_type,
				array(
					'posts_per_page' => max( 1, (int) $limit ),
					'meta_query'     => array(
						array(
							'key'   => 'cptdp_highlight',
							'value' => '1',
						),
					),
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
				)
			)
		);
	}
endif;

if ( ! function_exists( 'devportfolio_work_card_text' ) ) :
	/**
	 * The text side of a Selected work card: the title and line (one link) and the pill button (DPH-011, DPH-015, DPH-019).
	 *
	 * Both links open the same page. The title block and the pill are separate links because a button inside a link
	 * is not valid HTML.
	 *
	 * @param string $title  Card title.
	 * @param string $line   One line of grey text.
	 * @param string $button Pill label, e.g. "View all documents →".
	 * @param string $url    The page both links open.
	 * @return string HTML, escaped.
	 */
	function devportfolio_work_card_text( $title, $line, $button, $url ) {
		return sprintf(
			'<div class="work-card__body"><a class="work-card__text" href="%1$s"><h3 class="work-card__title">%2$s</h3><p class="work-card__line">%3$s</p></a><a class="work-card__btn" href="%1$s">%4$s</a></div>',
			esc_url( $url ),
			esc_html( $title ),
			esc_html( $line ),
			esc_html( $button )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_work_archive_url' ) ) :
	/**
	 * The archive page a Selected work card opens.
	 *
	 * @param string $post_type document, video or collateral.
	 * @return string
	 */
	function devportfolio_work_archive_url( $post_type ) {
		$url = get_post_type_archive_link( $post_type );

		return $url ? $url : home_url( '/' );
	}
endif;

if ( ! function_exists( 'devportfolio_work_book_role' ) ) :
	/**
	 * Where a book of the Documents fan sits on first paint, with the first book in front (DPH-012).
	 *
	 * The same rule as `bookRole()` in blocks/selected-work/rotation.js, which takes over once the script runs
	 * (a test keeps the two tables equal).
	 *
	 * @param int $index Book position in the list.
	 * @param int $count Number of books.
	 * @return string front, right, left or hidden.
	 */
	function devportfolio_work_book_role( $index, $count ) {
		if ( 0 === $index ) {
			return 'front';
		}
		if ( 1 === $index ) {
			return 'right';
		}

		return ( $count >= 3 && $index === $count - 1 ) ? 'left' : 'hidden';
	}
endif;

if ( ! function_exists( 'devportfolio_work_frame_role' ) ) :
	/**
	 * What a film frame is on first paint, with the first frame active (DPH-016).
	 *
	 * The same rule as `frameRole()` in blocks/selected-work/rotation.js.
	 *
	 * @param int $index Frame position in the list.
	 * @param int $count Number of frames.
	 * @return string active, prev, next, far-prev or far-next.
	 */
	function devportfolio_work_frame_role( $index, $count ) {
		$offset = $index > $count / 2 ? $index - $count : $index;

		if ( 0 === $offset ) {
			return 'active';
		}
		if ( -1 === $offset ) {
			return 'prev';
		}
		if ( 1 === $offset ) {
			return 'next';
		}

		return $offset < 0 ? 'far-prev' : 'far-next';
	}
endif;

if ( ! function_exists( 'devportfolio_work_piece_columns' ) ) :
	/**
	 * Groups the collateral pieces into the glimpse's columns (DPH-020).
	 *
	 * A wide strip (ratio 1.8 or more, such as a 5:2 voucher or a 2:1 coupon) shares a column with the strip before
	 * it, so the two are stacked; every other piece has a column of its own. At most `$max` columns.
	 *
	 * @param array<int,array{ratio:float}> $pieces Pieces in display order.
	 * @param int                           $max    Most columns.
	 * @return array<int,array<int,array{ratio:float}>>
	 */
	function devportfolio_work_piece_columns( array $pieces, $max = 5 ) {
		$columns = array();

		foreach ( $pieces as $piece ) {
			$last     = count( $columns ) - 1;
			$is_strip = $piece['ratio'] >= 1.8;

			if ( $is_strip && $last >= 0 && 1 === count( $columns[ $last ] ) && $columns[ $last ][0]['ratio'] >= 1.8 ) {
				$columns[ $last ][] = $piece;
				continue;
			}

			if ( count( $columns ) >= $max ) {
				break;
			}

			$columns[] = array( $piece );
		}

		return $columns;
	}
endif;

if ( ! function_exists( 'devportfolio_work_piece_art' ) ) :
	/**
	 * The artwork inside a glimpse piece: the same picture the Brand Collaterals page shows (DPH-020).
	 *
	 * Page 1 of the stored page images (its thumbnail, with the larger page as the other candidate), else the featured
	 * image, else nothing (the piece stays a blank page).
	 *
	 * @param array $preview Result of devportfolio_collateral_preview().
	 * @return string HTML, escaped.
	 */
	function devportfolio_work_piece_art( array $preview ) {
		if ( 'page' === $preview['kind'] ) {
			return sprintf(
				'<img class="work-piece__img" src="%1$s" srcset="%2$s" sizes="180px" width="%3$d" height="%4$d" alt="" loading="lazy" decoding="async">',
				esc_url( $preview['src'] ),
				esc_attr( $preview['srcset'] ),
				(int) $preview['width'],
				(int) $preview['height']
			);
		}

		if ( 'featured' === $preview['kind'] ) {
			return wp_get_attachment_image(
				$preview['featured_id'],
				'medium',
				false,
				array(
					'class'    => 'work-piece__img',
					'sizes'    => '180px',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
		}

		return '';
	}
endif;

if ( ! function_exists( 'devportfolio_contact_strings' ) ) :
	/**
	 * Every word of the Contact scheduler (DPH-025 to DPH-028). `{name}`, `{n}`, `{total}`, `{date}` and `{zone}` are
	 * replaced by view.js. A test checks that every `strings.<key>` used by the scripts and every `$s['<key>']` used by
	 * render.php is a key here.
	 *
	 * @return array<string,string>
	 */
	function devportfolio_contact_strings() {
		return array(
			'heading'           => __( 'Need an extra pair of hands?', 'devportfolio' ),
			'lead'              => __( 'Pick a day that suits you, tell me what you need, and I’ll reply within 24 hours.', 'devportfolio' ),
			'open'              => __( 'Check availability', 'devportfolio' ),
			'progress'          => __( 'Progress', 'devportfolio' ),
			'stepOf'            => __( 'step {n} of {total}', 'devportfolio' ),
			'crumbAvailability' => __( 'Availability', 'devportfolio' ),
			'crumbAbout'        => __( 'About you', 'devportfolio' ),
			'crumbNeed'         => __( 'Your need', 'devportfolio' ),
			'crumbConfirm'      => __( 'Confirm', 'devportfolio' ),
			'title1'            => __( 'Pick a day and time', 'devportfolio' ),
			'title2'            => __( 'Introduce yourself', 'devportfolio' ),
			'title3'            => __( 'What do you need help with?', 'devportfolio' ),
			'title4'            => __( 'Confirm the details', 'devportfolio' ),
			'monthPrev'         => __( 'Previous month', 'devportfolio' ),
			'monthNext'         => __( 'Next month', 'devportfolio' ),
			'legendAvailable'   => __( 'Available', 'devportfolio' ),
			'legendSelected'    => __( 'Selected', 'devportfolio' ),
			'timesHint'         => __( 'Pick a day to see the times.', 'devportfolio' ),
			'timesTitle'        => __( 'Available times', 'devportfolio' ),
			'timezone'          => __( 'Times are in {zone} time.', 'devportfolio' ),
			'slotLocal'         => __( '{when} ({zone} time)', 'devportfolio' ),
			'nameLabel'         => __( 'Name', 'devportfolio' ),
			'emailLabel'        => __( 'Email', 'devportfolio' ),
			'messageLabel'      => __( 'Tell me more', 'devportfolio' ),
			'messageHint'       => __( 'Optional', 'devportfolio' ),
			'honeypotLabel'     => __( 'Leave this field empty', 'devportfolio' ),
			'back'              => __( 'Back', 'devportfolio' ),
			'next'              => __( 'Next', 'devportfolio' ),
			'edit'              => __( 'Edit', 'devportfolio' ),
			'change'            => __( 'Change', 'devportfolio' ),
			'confirm'           => __( 'Confirm my interest ↗', 'devportfolio' ),
			'sending'           => __( 'Sending…', 'devportfolio' ),
			'summaryTime'       => __( 'Time', 'devportfolio' ),
			'summaryName'       => __( 'Name', 'devportfolio' ),
			'summaryEmail'      => __( 'Email', 'devportfolio' ),
			'summaryHelp'       => __( 'Help with', 'devportfolio' ),
			'summaryMessage'    => __( 'Message', 'devportfolio' ),
			'noMessage'         => __( 'No message', 'devportfolio' ),
			'slotRequired'      => __( 'Choose a day and a time to continue.', 'devportfolio' ),
			'nameRequired'      => __( 'Please enter your name.', 'devportfolio' ),
			'emailRequired'     => __( 'Please enter your email address.', 'devportfolio' ),
			'emailInvalid'      => __( 'That email address does not look right.', 'devportfolio' ),
			'needRequired'      => __( 'Choose at least one, so I know how to help.', 'devportfolio' ),
			'slotGone'          => __( 'That time is no longer available. Please pick another.', 'devportfolio' ),
			'sendError'         => __( 'That did not send. Please try again.', 'devportfolio' ),
			'tooMany'           => __( 'Too many tries. Please wait a while and try again.', 'devportfolio' ),
			'thanks'            => __( 'Thanks, {name}. Interest received.', 'devportfolio' ),
			'thanksTime'        => __( 'Your time: {date}', 'devportfolio' ),
			'thanksNote'        => __( 'I\'ll confirm by email.', 'devportfolio' ),
			'modalTitle'        => __( 'Check my availability', 'devportfolio' ),
			'close'             => __( 'Close', 'devportfolio' ),
			'noscript'          => __( 'The booking form needs JavaScript.', 'devportfolio' ),
			'noscriptEmail'     => __( 'You can email me instead:', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_contact_config' ) ) :
	/**
	 * What the Contact scheduler needs, or an empty array when the plugin is not active (the block then renders nothing).
	 *
	 * @return array<string,mixed>
	 */
	function devportfolio_contact_config() {
		if ( ! function_exists( 'cptdp_contact_config' ) ) {
			return array();
		}

		return array_merge( cptdp_contact_config(), array( 'strings' => devportfolio_contact_strings() ) );
	}
endif;
