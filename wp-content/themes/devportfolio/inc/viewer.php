<?php
/**
 * Viewer markup shared by the Documents, Collaterals and Videos modals: arrows, status line, footer and the mobile header, body and footer.
 *
 * Required by functions.php.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_viewer_arrow' ) ) :
	/**
	 * One round page arrow of a book viewer (DPD-014, DPC-012): the markup both modals share.
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix Modal class prefix: 'documents-modal' or 'collaterals-modal'.
	 * @param string $dir    'prev' or 'next'.
	 * @param string $label  The accessible name.
	 * @return string
	 */
	function devportfolio_viewer_arrow( $prefix, $dir, $label ) {
		$path = 'prev' === $dir ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7';
		return sprintf(
			'<button class="%1$s__arrow %1$s__arrow--%2$s" type="button" aria-label="%3$s"><svg class="%1$s__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%4$s"/></svg></button>',
			esc_attr( $prefix ),
			esc_attr( $dir ),
			esc_attr( $label ),
			esc_attr( $path )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_status_strings' ) ) :
	/**
	 * The strings of a viewer's status line, translated for one modal (DPD-013, DPC-012).
	 *
	 * @since 0.7.0
	 *
	 * @param string $modal 'document' or 'collateral' (the noun in the wording and the translation context).
	 * @return array<string,string>
	 */
	function devportfolio_viewer_status_strings( $modal ) {
		if ( 'collateral' === $modal ) {
			return array(
				'loading'    => _x( 'Loading pages…', 'collateral modal viewer', 'devportfolio' ),
				'unavailable' => _x( 'This collateral can’t be previewed.', 'collateral modal viewer', 'devportfolio' ),
				'pending'    => _x( 'Preview is being prepared. You can still download the file.', 'collateral modal viewer', 'devportfolio' ),
				'hint'       => _x( 'Use Download to open it.', 'collateral modal viewer', 'devportfolio' ),
				'hintSheet'  => _x( 'Spreadsheets can’t always be previewed here. Download the file to open it in Excel.', 'collateral modal viewer', 'devportfolio' ),
				'hintSlide'  => _x( 'Presentations can’t always be previewed here. Download the file to open it in PowerPoint.', 'collateral modal viewer', 'devportfolio' ),
				'hintDoc'    => _x( 'Documents can’t always be previewed here. Download the file to open it in Word.', 'collateral modal viewer', 'devportfolio' ),
				'loadingImage' => function_exists( 'cptdp_collateral_loading_image_url' ) ? cptdp_collateral_loading_image_url() : '',
			);
		}
		return array(
			'loading'     => _x( 'Loading pages…', 'document modal viewer', 'devportfolio' ),
			'unavailable' => _x( 'This document can’t be previewed.', 'document modal viewer', 'devportfolio' ),
			'hint'        => _x( 'Use Download to open it.', 'document modal viewer', 'devportfolio' ),
			'hintSheet'   => _x( 'Spreadsheets can’t always be previewed here. Download the file to open it in Excel.', 'document modal viewer', 'devportfolio' ),
			'hintSlide'   => _x( 'Presentations can’t always be previewed here. Download the file to open it in PowerPoint.', 'document modal viewer', 'devportfolio' ),
			'hintDoc'     => _x( 'Documents can’t always be previewed here. Download the file to open it in Word.', 'document modal viewer', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_status' ) ) :
	/**
	 * The loading / "can't be previewed" line inside a viewer stage (`role="status"`, DPD-013, DPC-012).
	 *
	 * The children take the modal's prefix (`<prefix>__spinner`, `__state-text`, `__state-hint`); `$extra` adds
	 * classes to the status element (the mobile dialog's own `<prefix-mobile>__state`).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix  Modal class prefix: 'documents-modal' or 'collaterals-modal'.
	 * @param array  $strings The viewer strings (devportfolio_viewer_status_strings()).
	 * @param string $extra   Extra classes for the status element.
	 * @return string
	 */
	function devportfolio_viewer_status( $prefix, array $strings, $extra = '' ) {
		$attr = static function ( $name, $key ) use ( $strings ) {
			$value = (string) ( $strings[ $key ] ?? '' );
			return '' !== $value ? sprintf( ' %s="%s"', $name, esc_attr( $value ) ) : '';
		};
		return sprintf(
			'<div class="%1$s__state%2$s" role="status" hidden%3$s%4$s%5$s%6$s%7$s%8$s%9$s%10$s><span class="%1$s__spinner" aria-hidden="true"></span><img class="%1$s__state-image" alt="" hidden><span class="%1$s__state-text"></span><span class="%1$s__state-hint"></span></div>',
			esc_attr( $prefix ),
			'' !== $extra ? ' ' . esc_attr( $extra ) : '',
			$attr( 'data-loading', 'loading' ),
			$attr( 'data-unavailable', 'unavailable' ),
			$attr( 'data-pending', 'pending' ),
			$attr( 'data-hint', 'hint' ),
			$attr( 'data-hint-sheet', 'hintSheet' ),
			$attr( 'data-hint-slide', 'hintSlide' ),
			$attr( 'data-hint-doc', 'hintDoc' ),
			$attr( 'data-loading-image', 'loadingImage' )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_footer' ) ) :
	/**
	 * The desktop footer under a book viewer: the pager and the progress line (DPD-013, DPC-013).
	 *
	 * Hidden until the script has more than one page; the pager's accessible name comes from the script.
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix Modal class prefix.
	 * @param string $label  The pager's accessible name (fallback until the unit words arrive).
	 * @param string $bar    The progress line's accessible name.
	 * @return string
	 */
	function devportfolio_viewer_footer( $prefix, $label, $bar ) {
		return sprintf(
			'<footer class="%1$s__footer" hidden><nav class="%1$s__pager" aria-label="%2$s" hidden></nav><div class="%1$s__indicator" hidden><div class="%1$s__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="%3$s"><i></i></div></div></footer>',
			esc_attr( $prefix ),
			esc_attr( $label ),
			esc_attr( $bar )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_mobile_header' ) ) :
	/**
	 * The header row of a mobile viewer dialog: outline Close, the title (truncated) and outline Download (DPD-015, DPC-015).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix         Mobile class prefix: 'documents-mobile' or 'collaterals-mobile'.
	 * @param string $view_prefix    Icon class prefix: 'documents-modal' or 'collaterals-modal'.
	 * @param string $title_id      Id of the title (the dialog's accessible name).
	 * @param string $close_label    Accessible name of Close.
	 * @param string $download_label Accessible name of Download.
	 * @return string
	 */
	function devportfolio_viewer_mobile_header( $prefix, $view_prefix, $title_id, $close_label, $download_label ) {
		return sprintf(
			'<header class="%1$s__header"><button class="%1$s__close %1$s__btn" type="button" aria-label="%2$s">%3$s</button><h2 class="%1$s__title" id="%4$s"></h2><a class="%1$s__download %1$s__btn" role="button" aria-label="%5$s">%6$s</a></header>',
			esc_attr( $prefix ),
			esc_attr( $close_label ),
			devportfolio_viewer_icon( $view_prefix, 'M6 6l12 12M18 6L6 18' ),
			esc_attr( $title_id ),
			esc_attr( $download_label ),
			devportfolio_viewer_icon( $view_prefix, 'M12 4v11M7 11l5 5 5-5M5 20h14' )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_icon' ) ) :
	/**
	 * One decorative 24px stroke icon of a viewer (the class is the viewer's icon class).
	 *
	 * @since 0.7.0
	 *
	 * @param string $view_prefix Icon class prefix ('documents-modal' or 'collaterals-modal').
	 * @param string $path        SVG path data.
	 * @return string
	 */
	function devportfolio_viewer_icon( $view_prefix, $path ) {
		return sprintf(
			'<svg class="%1$s__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%2$s"/></svg>',
			esc_attr( $view_prefix ),
			esc_attr( $path )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_mobile_body' ) ) :
	/**
	 * The body of a mobile viewer dialog: the stage the viewer builds, the status line and the progress bar (DPD-021, DPC-019).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix      Mobile class prefix.
	 * @param string $view_prefix Viewer class prefix (the status children).
	 * @param array  $strings     The viewer status strings (devportfolio_viewer_status_strings()).
	 * @param string $bar         The progress bar's accessible name.
	 * @return string
	 */
	function devportfolio_viewer_mobile_body( $prefix, $view_prefix, array $strings, $bar ) {
		return sprintf(
			'<div class="%1$s__body"><div class="%1$s__stage"></div>%2$s<div class="%1$s__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="" aria-label="%3$s" hidden><i></i></div></div>',
			esc_attr( $prefix ),
			devportfolio_viewer_status( $view_prefix, $strings, $prefix . '__state' ),
			esc_attr( $bar )
		);
	}
endif;

if ( ! function_exists( 'devportfolio_viewer_mobile_footer' ) ) :
	/**
	 * The footer row of a mobile viewer dialog: Pages, the position (filled by the script) and Details (DPD-016, DPC-016).
	 *
	 * @since 0.7.0
	 *
	 * @param string $prefix      Mobile class prefix.
	 * @param string $view_prefix Icon class prefix.
	 * @param string $pages       Visible label of the Pages button.
	 * @param string $details     Visible label of the Details button.
	 * @return string
	 */
	function devportfolio_viewer_mobile_footer( $prefix, $view_prefix, $pages, $details ) {
		$grid = sprintf(
			'<svg class="%s__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>',
			esc_attr( $view_prefix )
		);
		return sprintf(
			'<footer class="%1$s__footer"><div class="%1$s__row"><button class="%1$s__pages %1$s__textbtn" type="button">%2$s<span>%3$s</span></button><b class="%1$s__position"></b><button class="%1$s__details %1$s__textbtn" type="button"><span>%4$s</span>%5$s</button></div></footer>',
			esc_attr( $prefix ),
			$grid,
			esc_html( $pages ),
			esc_html( $details ),
			devportfolio_viewer_icon( $view_prefix, 'M6 9l6 6 6-6' )
		);
	}
endif;
