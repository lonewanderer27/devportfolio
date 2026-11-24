<?php
/**
 * Helpers shared by every page: single-to-deeplink redirects, the per-request memo, published IDs, term slugs, page sources, page-count label, software tool names and the media payload base.
 *
 * Required by functions.php.
 *
 * @package devportfolio
 */

if ( ! function_exists( 'devportfolio_modal_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published single of a modal post type to its archive deeplink (DPD-032, DPC-020).
	 *
	 * @since 0.9.0
	 *
	 * @param string $post_type Post type, e.g. "document".
	 * @param string $param     Query parameter that holds the slug, e.g. "document_slug".
	 * @return string Empty when the current request is not a published single of that type.
	 */
	function devportfolio_modal_single_redirect_url( $post_type, $param ) {
		if ( ! is_singular( $post_type ) ) {
			return '';
		}

		$devportfolio_post = get_queried_object();
		if ( ! $devportfolio_post instanceof WP_Post || 'publish' !== $devportfolio_post->post_status ) {
			return '';
		}

		$devportfolio_archive = get_post_type_archive_link( $post_type );
		if ( ! $devportfolio_archive ) {
			return '';
		}

		return add_query_arg( $param, $devportfolio_post->post_name, $devportfolio_archive );
	}
endif;

if ( ! function_exists( 'devportfolio_document_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published document single to its modal deeplink.
	 *
	 * @since 0.6.0
	 *
	 * @return string Empty when the current request is not a published document single.
	 */
	function devportfolio_document_single_redirect_url() {
		return devportfolio_modal_single_redirect_url( 'document', 'document_slug' );
	}
endif;

if ( ! function_exists( 'devportfolio_collateral_single_redirect_url' ) ) :
	/**
	 * URL for redirecting a published collateral single to its modal deeplink (DPC-020).
	 *
	 * @since 0.9.0
	 *
	 * @return string Empty when the current request is not a published collateral single.
	 */
	function devportfolio_collateral_single_redirect_url() {
		return devportfolio_modal_single_redirect_url( 'collateral', 'collateral_slug' );
	}
endif;

if ( ! function_exists( 'devportfolio_redirect_document_single' ) ) :
	/**
	 * Redirects published document and collateral singles to their modal deeplinks (DPD-032, DPC-020).
	 *
	 * Hooked on template_redirect; the name is kept from DPD-032 so existing references still resolve.
	 *
	 * @since 0.6.0
	 */
	function devportfolio_redirect_document_single() {
		foreach ( array( 'devportfolio_document_single_redirect_url', 'devportfolio_collateral_single_redirect_url', 'devportfolio_video_single_redirect_url' ) as $devportfolio_resolver ) {
			$devportfolio_url = $devportfolio_resolver();
			if ( '' !== $devportfolio_url ) {
				wp_safe_redirect( $devportfolio_url, 302 );
				exit;
			}
		}
	}
endif;
add_action( 'template_redirect', 'devportfolio_redirect_document_single', 1 );

if ( ! function_exists( 'devportfolio_memo_store' ) ) :
	/**
	 * The per-request store behind devportfolio_memo(). Static, not the object cache: a persistent cache
	 * (Redis) would serve stale tool names across requests.
	 *
	 * @return array<string,mixed>
	 */
	function &devportfolio_memo_store() {
		static $store = array();

		return $store;
	}
endif;

if ( ! function_exists( 'devportfolio_memo' ) ) :
	/**
	 * Runs $build once per request for a group and post ID, then returns the same value (DPC-024, O2).
	 *
	 * Every block that reads the same tools, category, preview or term list of a post goes through here, so
	 * each read happens once however many blocks ask for it. The store is cleared by devportfolio_memo_flush()
	 * whenever a post, term or post meta changes.
	 *
	 * @param string   $group   Group name, e.g. 'software_tools'.
	 * @param int      $post_id Post ID, or 0 when the value is not per post.
	 * @param callable $build   Returns the value.
	 * @return mixed
	 */
	function devportfolio_memo( $group, $post_id, $build ) {
		$store = &devportfolio_memo_store();
		$key   = $group . '|' . (int) $post_id;
		if ( ! array_key_exists( $key, $store ) ) {
			$store[ $key ] = $build();
		}

		return $store[ $key ];
	}

	/**
	 * Empties the per-request store. Hooked to every change a memoised value can depend on.
	 */
	function devportfolio_memo_flush() {
		$store = &devportfolio_memo_store();
		$store = array();
	}
endif;

if ( ! function_exists( 'devportfolio_memo_add_hooks' ) ) :
	/**
	 * Clears the memo store whenever a post, its terms or its meta change. Adding a hook twice is a no-op, so
	 * tests may call it again after WordPress restores the hooks between tests.
	 */
	function devportfolio_memo_add_hooks() {
		foreach ( array( 'save_post', 'transition_post_status', 'delete_post', 'set_object_terms', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, 'devportfolio_memo_flush' );
		}
	}
endif;

devportfolio_memo_add_hooks();

if ( ! function_exists( 'devportfolio_published_ids' ) ) :
	/**
	 * IDs of the published posts of one type, in the order asked for, memoised per request (DPC-024, O1).
	 *
	 * The shelf, the grid and both modals list the same posts; each list is read once per request.
	 *
	 * @param string $post_type Post type.
	 * @param array  $args      Extra get_posts() arguments, e.g. orderby. Merged over the defaults.
	 * @return int[]
	 */
	function devportfolio_published_ids( $post_type, $args = array() ) {
		return devportfolio_memo(
			'published:' . $post_type . ':' . md5( serialize( $args ) ),
			0,
			static function () use ( $post_type, $args ) {
				return get_posts(
					array_merge(
						array(
							'post_type'      => $post_type,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
						),
						$args
					)
				);
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_term_slugs' ) ) :
	/**
	 * Slugs of a post's terms in one taxonomy, memoised per request (DPC-024, O2).
	 *
	 * Returns what wp_get_object_terms() returns, WP_Error included, so callers keep their is_wp_error() check.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]|WP_Error
	 */
	function devportfolio_term_slugs( $post_id, $taxonomy ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'term_slugs:' . $taxonomy,
			$post_id,
			static function () use ( $post_id, $taxonomy ) {
				return wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_page_source_map' ) ) :
	/**
	 * The post types whose file the modal viewer draws, and what each one stores (DPD-013, DPC-012, D6).
	 *
	 * `file` is the attachment meta key; `pages` the plugin function that reads the stored page images (never
	 * converts); `native` the plugin's native-viewer URL functions by kind. One entry per post type: a third modal
	 * is one more entry here, not a second copy of the viewer data.
	 *
	 * @since 0.7.0
	 *
	 * @return array<string,array{file:string,pages:string}>
	 */
	function devportfolio_page_source_map() {
		return array(
			'document'   => array(
				'file'  => 'cptdp_document_file',
				'pages' => 'cptdp_get_document_pages',
			),
			'collateral' => array(
				'file'  => 'cptdp_collateral_file',
				'pages' => 'cptdp_get_collateral_pages',
			),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_page_count_label' ) ) :
	/**
	 * The page count of a file, e.g. "24 pp" or "1 p" (DPD-005), or "12 slides" / "1 slide" for a presentation (DPD-026).
	 *
	 * Shared by the document cover and modal and by the collateral pill and meta line (DPC-007, DPC-006).
	 * Zero, negative and non-numeric counts give ''. Plain text.
	 *
	 * @param mixed  $pages Page count.
	 * @param string $kind  Optional file kind from devportfolio_document_file_kind(). Presentations count slides.
	 * @return string
	 */
	function devportfolio_page_count_label( $pages, $kind = '' ) {
		$pages = ( is_int( $pages ) || ( is_string( $pages ) && ctype_digit( trim( $pages ) ) ) ) ? (int) $pages : 0;

		if ( 'slide' === devportfolio_document_unit( $kind ) ) {
			if ( 1 === $pages ) {
				return '1 ' . _x( 'slide', 'slide count, singular', 'devportfolio' );
			}

			return $pages > 1 ? $pages . ' ' . _x( 'slides', 'slide count, plural', 'devportfolio' ) : '';
		}

		if ( 1 === $pages ) {
			return '1 ' . _x( 'p', 'page count abbreviation, singular', 'devportfolio' );
		}

		return $pages > 1 ? $pages . ' ' . _x( 'pp', 'page count abbreviation, plural', 'devportfolio' ) : '';
	}
endif;

if ( ! function_exists( 'devportfolio_software_tool_names' ) ) :
	/**
	 * Names of the published software tools of a post, in stored order.
	 *
	 * Works for any post type that stores `cptdp_software_tools` (document,
	 * video, collateral); used by the software tags block and the document modal.
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	function devportfolio_software_tool_names( $post_id ) {
		$post_id = (int) $post_id;

		return devportfolio_memo(
			'software_tools',
			$post_id,
			static function () use ( $post_id ) {
				$tools    = array();
				$tool_ids = get_post_meta( $post_id, 'cptdp_software_tools', true );
				foreach ( is_array( $tool_ids ) ? $tool_ids : array() as $tool_id ) {
					if ( 'software_tool' === get_post_type( (int) $tool_id ) && 'publish' === get_post_status( (int) $tool_id ) ) {
						$tools[] = get_the_title( (int) $tool_id );
					}
				}

				return $tools;
			}
		);
	}
endif;

if ( ! function_exists( 'devportfolio_tool_monogram' ) ) :
	/**
	 * The two letters shown for a software tool with no icon: first capital, second lowercase ("Sl" for Slack).
	 *
	 * @param string $title Tool name.
	 * @return string
	 */
	function devportfolio_tool_monogram( $title ) {
		$letters = preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $title );
		$first   = mb_strtoupper( mb_substr( $letters, 0, 1 ) );
		$second  = mb_strtolower( mb_substr( $letters, 1, 1 ) );

		return $first . $second;
	}
endif;

if ( ! function_exists( 'devportfolio_media_payload_base' ) ) :
	/**
	 * The keys every media payload shares, in the order the Documents modal already writes them (DPD-009, DPC-007, DPC-024).
	 *
	 * `id`, `slug` (the deeplink key: /documents/?document_slug=<slug>, DPD-032), `title`, `description`, `tools`,
	 * `category`. The download keys are not here: the Documents payload writes its own file keys (fileType, format,
	 * kind, pages) between `category` and `downloadMode`, so each payload appends its own keys after this base.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Category taxonomy: document_category or collateral_category.
	 * @param string $title    Plain-text title, decided by the caller: Documents pass the decoded get_the_title(),
	 *                         Collaterals the raw post title (wptexturize would curl its quotes).
	 * @return array<string,mixed>
	 */
	function devportfolio_media_payload_base( $post_id, $taxonomy, $title ) {
		$post_id = (int) $post_id;

		return array(
			'id'          => $post_id,
			'slug'        => (string) get_post_field( 'post_name', $post_id ),
			'title'       => $title,
			'description' => devportfolio_document_description( $post_id ),
			'tools'       => devportfolio_software_tool_names( $post_id ),
			'category'    => html_entity_decode( devportfolio_document_category_name( $post_id, $taxonomy ), ENT_QUOTES, 'UTF-8' ),
		);
	}
endif;
