# Plan: Home hero (name and portrait, role and call to action, external links) with the Job Role post type and a portrait setting

Tickets **DPH-001** (name and portrait), **DPH-003** (role and call to action), **DPH-004** (external links), all Medium, Homepage. Sources: the user stories as pasted in the request (they supersede `.claude/plans/home/Home-User-Stories-Revised.csv` where they differ, see "Story changes"), and the lo-fi wireframe `.claude/plans/home/home-lofi-wireframe.html` (CSS block `/* hero */` at lines 19 to 26, markup in `#hero`, phone rules at line 289).
Branch: `feat/DPH-001-003-004-Home-Hero`. Touches the **plugin** (new `job_role` CPT and seeder, a portrait field in Settings > Contact, minor version bump) and the **theme** (one dynamic block, Home template).

**Implementer rule:** every "Fact" below was checked in the repo on the day of writing. If the code does not match a Fact, stop and report; do not improvise. Haiku-level execution is intended: every new file is given in full, every edit is an exact find/replace.

## Story changes (what differs from the pasted text or the CSV)
1. **DPH-001 gains a setting:** Settings > Contact gets a **Portrait** field (media picker) so the developer uploads the cut-out in wp-admin. The theme ships no image file.
2. **DPH-003 changes its data source:** the role and the three specialities are no longer hard-coded or taken from Services. They come from a new **Job Role** post type with four seeded roles and a single "highlight" that picks the top role. The CSV row for DPH-003 says "the top-level Services"; the pasted story and this plan say Job Roles. Update the CSV (step D2).
3. **DPH-004:** the wireframe pill reads "Upwork"; the story (and Settings > Contact) says WhatsApp. **The story wins.**
4. **Phone breakpoint:** the wireframe uses 620px; the stories say 900px and below. **The stories win**, 900px everywhere in the hero.

## Out of scope
- A hero for the Documents, Videos or Collaterals pages, a "Book a call" nav button (wireframe only), animation or parallax on the portrait.
- Cropping or background removal of the uploaded photo. Dave uploads a ready **transparent PNG or WebP cut-out**; the plan only validates the type.
- A public page or archive for Job Roles (the CPT is admin-only data for the hero).
- Moving the footer links (see Decision 8: the footer already shows them at every width).

## Facts (checked)
1. `templates/front-page.html` has the placeholder comment `<!-- The hero (DPH-001, DPH-003, DPH-004) goes here, above the stats strip. -->` right after `<main class="wp-block-group documents-page home-page">` and before `<!-- wp:devportfolio/stats-strip /-->`. `<main>` is a constrained group, content width 1440px (`page.css`).
2. Blocks are registered in `devportfolio_register_blocks()` (`functions.php`, list of `register_block_type( __DIR__ . '/blocks/<name>' )`, last entry `contact`). A dynamic block is `block.json` (`"render": "file:./render.php"`, `"style": "file:./style.css"`) plus `render.php` and `style.css`; `stats-strip` is the simplest model. `devportfolio_published_ids( $post_type, $args )` returns memoised published IDs; `devportfolio_memo_flush()` clears it (tests call it).
3. Settings > Contact: `includes/settings/contact.php`, option `cptdp_contact` (`email`, `whatsapp_url`, `linkedin_url`), read with `cptdp_get_contact( ?string $key )`, sanitiser `cptdp_contact_sanitize()` (invalid values are blanked with `add_settings_error`; keys missing from the input become blank, as for the three existing fields), REST schema has `additionalProperties => false`, page slug `cptdp-contact` (hook suffix `settings_page_cptdp-contact`), a second option `cptdp_contact_times`. The footer block already prints LinkedIn, WhatsApp and Email from it at every width, a link without a value is omitted.
4. `tests/settings/test-contact.php` asserts the **exact** default array (three keys) in at least `test_defaults` and `test_get_contact_merges_saved_values_and_drops_unknown_keys`. Both must gain `'portrait_id' => 0`. Search the file for `'linkedin_url' => ''` to find every one.
5. A CPT module is one file in `includes/post-types/` hooked to `cptdp_register_post_types`; the loader globs the folder, so a new file needs no registration. A seeder is `includes/seeders/<name>.php` plus `data/seeders/<name>.csv` plus `tests/seeders/test-<name>.php` extending `CPTDP_Seeder_Test_Case` (model: `statistics`). `cptdp_seed_read_csv( $path, $header )` and `cptdp_seed_title_exists( $type, $title )` are in `includes/cli/seeder-helpers.php`.
6. Admin sidebar positions in use: 20 Pages, 21 Interests, 22 Statistics, 23 Services, 29 Testimonials, 30 Software tools, 31 Documents, 32 Collaterals, 33 Videos, 34 Comments. **24 is free** and sits between Services and Testimonials.
7. The existing `cptdp_highlight` meta on documents, collaterals and videos is a checkbox meta box saved with `update_post_meta( $id, 'cptdp_highlight', 1 )` / `delete_post_meta`. Job Role reuses the same key and the same box pattern (it is registered per post type, so it does not clash).
8. Plugin version is `0.34.0` (header and `CPTDP_VERSION` in `custom-post-types-dp.php`).
9. The site title is `Dave Portfolio` (checked with `ddev wp option get blogname`). The header already shows the site title at 900px and below (see `AGENTS.md`, "parts/header.html"); nothing to build for "the nav title reads Dave Portfolio", only to verify.
10. `cptdp_get_contact()` today returns strings; `portrait_id` will be the one integer.

## Decisions made (do not block)
1. **Job Role CPT:** slug `job_role`, labels "Job Role" / "Job Roles", **not public** (no archive, no front-end URL, no `rewrite`), `show_ui` and `show_in_rest` on, supports `title` and `page-attributes` (order), menu position 24, icon `dashicons-businessperson`, classic editor. Title = the role name.
2. **Single highlight is enforced by moving it.** Marking a role as highlight automatically clears the mark on every other Job Role. This is done on the meta hooks (`added_post_meta` / `updated_post_meta`), not only in the meta box, so the box, REST, WP-CLI and the seeder all obey the limit. Refusing the second mark was rejected: it leaves the editor stuck, and "move the star" is how a radio choice behaves. The meta box says so in words.
3. **Fallbacks, so the hero never breaks:** no highlighted role -> the first published role by order is the top role; no published role -> no role block (the button still shows).
4. **Seed data:** the seeder creates Virtual Assistant (order 1, highlight), Social Media Manager (2), Digital Marketer (3), Web Developer (4). The other three show on the next line in order, separated by dots.
5. **The big name is the page `<h1>`, taken from the site title** (`get_bloginfo( 'name' )`): the first word is the outlined one, the rest is solid (`DAVE` outlined, `PORTFOLIO` solid), upper-cased by CSS. Not hard-coded, so renaming the site renames the hero. "Hidden on phones" is **visually hidden** (clipped, still in the accessibility tree), so the page keeps its `h1`.
6. **Portrait storage:** `portrait_id` (attachment ID) inside option `cptdp_contact`. Only `image/png` and `image/webp` are accepted (a cut-out needs transparency); anything else is blanked with an error notice. Drawn in greyscale by CSS (`filter: grayscale(1)`), the file stays in colour. Alt text: the attachment's Alternative Text, else `Portrait of <site title>`.
7. **Preload:** `wp_head` prints `<link rel="preload" as="image" ... fetchpriority="high">` on the front page when a portrait is set, with the same `srcset` and `sizes` as the `<img>` (one helper owns the sizes string so they cannot drift). The `<img>` is `loading="eager"`, `fetchpriority="high"`, `decoding="async"`. "Compressed" is the editor's job (WordPress makes the `large` 1024px sub-size); the setting's help text says "WebP or PNG, transparent, about 1200px wide, under 500 KB".
8. **Pills on phones:** hidden at 900px and below. The footer already lists the same three links at every width, so nothing is added to the footer; this is what "appear in the footer instead" means.
9. **Call to action:** a plain `<a href="#contact">` (same page, no new tab, no script). Section `#contact` exists (`blocks/contact`). It is the only filled button in the hero. Smooth scrolling is turned on in CSS only when the visitor has not asked for reduced motion.
10. **Roles line "small caps":** drawn as the wireframe draws it (11px, upper case, 0.04em tracking, weight 600), not with `font-variant-caps` (Hanken Grotesk has no real small-caps face, so the browser would fake it).
11. **"Scroll down ↓"** is decorative (`aria-hidden`), not a link.
12. **Pill labels:** `in LinkedIn` (the `in` is `aria-hidden`, so the accessible name is `LinkedIn`), `WhatsApp`, `Email me`. Text only, so the portrait stays the only image above the fold.

13. **Default portrait (revision 2):** the supplied cut-out (`/Users/adriane.puzon/Downloads/mingyu-portrait.png`: PNG, RGBA with real transparency, 696 x 928 px, 406 KB) is bundled in the plugin as `data/seeders/portrait/portrait.png` and a new seeder `portrait` sideloads it into the Media Library and stores its ID in Settings > Contact (`portrait_id`). It is the **default / placeholder**: it is only used when no portrait is set (an existing choice is never overwritten, like every seeder), and Dave replaces it in Settings > Contact. The file is copied unchanged (no recompression: it is under the 500 KB guidance). Its alt text is left empty on purpose, so the hero's own fallback `Portrait of <site title>` applies. The file is not Dave's photo: see the first risk below.

## Open decisions (default applied; none blocks the work)
1. If Dave later wants **two** highlighted roles (a pair), the single-highlight rule is one function (`cptdp_job_role_single_highlight`) to delete.
2. A **draft** role marked as highlight is ignored by the hero (only published roles are read), so the first published role becomes the top one until it is published.

---

## Phase A. Plugin

### A1. `wp-content/plugins/custom-post-types-dp/includes/post-types/job-role.php` (new, in full)

```php
<?php
/**
 * Job Role custom post type (DPH-003).
 *
 * Title = the role name, order = menu order, meta `cptdp_highlight` marks the ONE role the Home hero shows as the
 * top role (marking a role clears the mark on every other role). Not public: no archive, no front-end URL.
 * Callers must escape on output.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Job Role post type and its meta.
 */
function cptdp_register_job_role(): void {
	$labels = array(
		'name'               => esc_html_x( 'Job Roles', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'      => esc_html_x( 'Job Role', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'          => esc_html_x( 'Job Roles', 'admin menu', 'custom-post-types-dp' ),
		'add_new_item'       => esc_html__( 'Add New Job Role', 'custom-post-types-dp' ),
		'new_item'           => esc_html__( 'New Job Role', 'custom-post-types-dp' ),
		'edit_item'          => esc_html__( 'Edit Job Role', 'custom-post-types-dp' ),
		'view_item'          => esc_html__( 'View Job Role', 'custom-post-types-dp' ),
		'all_items'          => esc_html__( 'All Job Roles', 'custom-post-types-dp' ),
		'search_items'       => esc_html__( 'Search Job Roles', 'custom-post-types-dp' ),
		'not_found'          => esc_html__( 'No job roles found.', 'custom-post-types-dp' ),
		'not_found_in_trash' => esc_html__( 'No job roles found in Trash.', 'custom-post-types-dp' ),
	);

	register_post_type(
		'job_role',
		array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'has_archive'  => false,
			'rewrite'      => false,
			'hierarchical' => false,
			'supports'     => array( 'title', 'page-attributes' ),
			'menu_position' => 24, // After Services (23), before Testimonials (29).
			'menu_icon'    => 'dashicons-businessperson',
		)
	);

	register_post_meta(
		'job_role',
		'cptdp_highlight',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_job_role' );

/**
 * Force the classic editor for Job Roles. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_job_role_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'job_role' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_job_role_classic_editor', 10, 2 );

/**
 * Only one Job Role may be the highlight: when one is marked, clear the mark on all the others.
 *
 * Runs on the meta hooks so the meta box, REST, WP-CLI and the seeder all obey the same rule.
 *
 * @param int    $meta_id    Meta row ID (unused).
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Value just stored.
 */
function cptdp_job_role_single_highlight( $meta_id, $post_id, $meta_key, $meta_value ): void {
	static $running = false;

	if ( $running || 'cptdp_highlight' !== $meta_key || 'job_role' !== get_post_type( $post_id ) || ! rest_sanitize_boolean( $meta_value ) ) {
		return;
	}

	$running = true;

	$others = get_posts(
		array(
			'post_type'      => 'job_role',
			'post_status'    => array_values( get_post_stati() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'post__not_in'   => array( (int) $post_id ),
			'meta_key'       => 'cptdp_highlight', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of rows.
		)
	);

	foreach ( $others as $other_id ) {
		delete_post_meta( $other_id, 'cptdp_highlight' );
	}

	$running = false;
}
add_action( 'added_post_meta', 'cptdp_job_role_single_highlight', 10, 4 );
add_action( 'updated_post_meta', 'cptdp_job_role_single_highlight', 10, 4 );

/**
 * Add the Highlight meta box.
 */
function cptdp_job_role_add_meta_box(): void {
	add_meta_box(
		'cptdp_job_role_highlight',
		esc_html__( 'Highlight', 'custom-post-types-dp' ),
		'cptdp_job_role_render_meta_box',
		'job_role',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_job_role', 'cptdp_job_role_add_meta_box' );

/**
 * Render the Highlight meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_job_role_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'cptdp_job_role_highlight', 'cptdp_job_role_highlight_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="cptdp_highlight" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ) ); ?>>
			<?php esc_html_e( 'Show as the main role on the Home page', 'custom-post-types-dp' ); ?>
		</label>
	</p>
	<p class="description"><?php esc_html_e( 'Only one role can be the highlight. Ticking this one un-ticks the current one. The other roles are listed on the next line, in order.', 'custom-post-types-dp' ); ?></p>
	<?php
}

/**
 * Save the Highlight meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_job_role_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_job_role_highlight_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_job_role_highlight_nonce'] ) ), 'cptdp_job_role_highlight' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	empty( $_POST['cptdp_highlight'] ) ? delete_post_meta( $post_id, 'cptdp_highlight' ) : update_post_meta( $post_id, 'cptdp_highlight', 1 );
}
add_action( 'save_post_job_role', 'cptdp_job_role_save_meta' );

/**
 * Add Highlight and Order columns after Title.
 *
 * @param array $columns Existing columns.
 */
function cptdp_job_role_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['cptdp_highlight'] = esc_html__( 'Highlight', 'custom-post-types-dp' );
			$new['menu_order']      = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_job_role_posts_columns', 'cptdp_job_role_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_job_role_column_content( string $column, int $post_id ): void {
	if ( 'cptdp_highlight' === $column ) {
		echo get_post_meta( $post_id, 'cptdp_highlight', true ) ? '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Highlight', 'custom-post-types-dp' ) . '"></span>' : '&mdash;';
	} elseif ( 'menu_order' === $column ) {
		echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
	}
}
add_action( 'manage_job_role_posts_custom_column', 'cptdp_job_role_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_job_role_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-job_role_sortable_columns', 'cptdp_job_role_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_job_role_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'job_role' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_job_role_default_order' );
```

(Alignment note for the implementer: WPCS wants the `=>` arrows aligned inside each array; run the arrows of the `register_post_type` array to the longest key `menu_position`.)

### A2. `wp-content/plugins/custom-post-types-dp/data/seeders/job-roles.csv` (new, in full)

```csv
order,title,highlight
1,Virtual Assistant,1
2,Social Media Manager,0
3,Digital Marketer,0
4,Web Developer,0
```

### A3. `wp-content/plugins/custom-post-types-dp/includes/seeders/job-roles.php` (new, in full)

```php
<?php
/**
 * Job Roles seeder.
 *
 * Creates one published `job_role` per row of data/seeders/job-roles.csv; the row with highlight 1 is the top role.
 * Run: wp cptdp seed job-roles [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_job_roles( array $seeders ): array {
	$seeders['job-roles'] = array(
		'label'     => esc_html__( 'Job Roles', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_job_roles',
		'csv'       => CPTDP_PATH . 'data/seeders/job-roles.csv',
		'post_type' => 'job_role',
		'columns'   => array( 'order', 'title', 'highlight' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_job_roles' );

/**
 * Create the Job Roles listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_job_roles( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'job_role' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The job_role post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'highlight' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$highlights = 0;
	foreach ( $rows as $index => $row ) {
		if ( ! ctype_digit( $row['order'] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid order "%1$s" for "%2$s" (row %3$d).', $row['order'], $row['title'], $index + 1 ) );
		}

		if ( ! in_array( $row['highlight'], array( '0', '1' ), true ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Highlight must be 0 or 1 for "%1$s" (row %2$d).', $row['title'], $index + 1 ) );
		}

		$highlights += (int) $row['highlight'];
	}

	if ( $highlights > 1 ) {
		return new WP_Error( 'cptdp_seed_csv_row', 'Only one job role can be the highlight.' );
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( cptdp_seed_title_exists( 'job_role', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'job_role',
					'post_status' => 'publish',
					'post_title'  => $row['title'],
					'menu_order'  => (int) $row['order'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			if ( '1' === $row['highlight'] ) {
				update_post_meta( $post_id, 'cptdp_highlight', 1 );
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
```

### A3b. The default portrait: file, CSV and seeder `portrait`

**Copy the file (exact command, run from the repo root):**
```bash
mkdir -p wp-content/plugins/custom-post-types-dp/data/seeders/portrait
cp "/Users/adriane.puzon/Downloads/mingyu-portrait.png" wp-content/plugins/custom-post-types-dp/data/seeders/portrait/portrait.png
```
If the source path no longer exists, stop and ask for the file. Check afterwards: `file wp-content/plugins/custom-post-types-dp/data/seeders/portrait/portrait.png` must say `PNG image data, 696 x 928, 8-bit/color RGBA`. Do not resize, recolour or recompress it. Confirm with `git status` that the PNG shows as untracked, not ignored.

**`wp-content/plugins/custom-post-types-dp/data/seeders/portrait.csv`** (new, in full):
```csv
file
portrait.png
```

**`wp-content/plugins/custom-post-types-dp/includes/seeders/portrait.php`** (new, in full). A settings seeder, not a post seeder: it follows the registry contract (`label`, `callback`, `csv` at `data/seeders/<name>.csv`, `columns`) but has no `post_type` or `taxonomy`. Idempotent: when Settings > Contact already has a valid portrait it is skipped and nothing changes; the same file is never uploaded twice (the attachment is found again by the meta `_cptdp_portrait_source`); the other Contact values (email, links) are kept.

```php
<?php
/**
 * Portrait seeder (DPH-001).
 *
 * Puts the bundled cut-out (data/seeders/portrait/<file>) in the Media Library and stores it as the Home hero portrait
 * in Settings > Contact, unless a portrait is already set. Run: wp cptdp seed portrait [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_portrait( array $seeders ): array {
	$seeders['portrait'] = array(
		'label'    => esc_html__( 'Home portrait', 'custom-post-types-dp' ),
		'callback' => 'cptdp_seed_portrait',
		'csv'      => CPTDP_PATH . 'data/seeders/portrait.csv',
		'columns'  => array( 'file' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_portrait' );

/**
 * Set the default portrait.
 *
 * @param bool   $dry_run  When true, report what would be done and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Names by outcome, or an error.
 */
function cptdp_seed_portrait( bool $dry_run, string $csv_path ) {
	$rows = cptdp_seed_read_csv( $csv_path, array( 'file' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	if ( 1 !== count( $rows ) ) {
		return new WP_Error( 'cptdp_seed_csv_row', 'The portrait CSV must have exactly one row.' );
	}

	$name = $rows[0]['file'];
	$path = CPTDP_PATH . 'data/seeders/portrait/' . $name;

	if ( basename( $name ) !== $name || ! is_file( $path ) ) {
		return new WP_Error( 'cptdp_seed_portrait_missing', sprintf( 'Portrait file "%s" was not found.', $name ) );
	}

	$check = wp_check_filetype_and_ext( $path, $name );
	if ( ! in_array( $check['type'], array( 'image/png', 'image/webp' ), true ) ) {
		return new WP_Error( 'cptdp_seed_portrait_type', sprintf( 'Portrait file "%s" is not a PNG or WebP image.', $name ) );
	}

	$result  = array(
		'created' => array(),
		'skipped' => array(),
	);
	$current = (int) cptdp_get_contact( 'portrait_id' );

	if ( $current && 'attachment' === get_post_type( $current ) ) {
		$result['skipped'][] = $name;
		return $result;
	}

	if ( ! $dry_run ) {
		$attachment_id = cptdp_seed_portrait_attachment( $name, $path );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$saved = get_option( 'cptdp_contact', array() );
		update_option( 'cptdp_contact', array_merge( is_array( $saved ) ? $saved : array(), array( 'portrait_id' => $attachment_id ) ) );
	}

	$result['created'][] = $name;

	return $result;
}

/**
 * The Media Library attachment of the bundled portrait: reuse the one made earlier, else sideload a copy.
 *
 * @param string $file_name File name, stored as the attachment's source.
 * @param string $path      Absolute path of the bundled file. It is copied, never moved.
 * @return int|WP_Error Attachment ID, or an error.
 */
function cptdp_seed_portrait_attachment( string $file_name, string $path ) {
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_cptdp_portrait_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $file_name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);

	if ( $found ) {
		return (int) $found[0];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( $file_name );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return new WP_Error( 'cptdp_seed_portrait_copy', sprintf( 'Could not copy portrait "%s".', $file_name ) );
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $file_name,
			'tmp_name' => $tmp,
		),
		0,
		__( 'Home portrait', 'custom-post-types-dp' )
	);

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	update_post_meta( $attachment_id, '_cptdp_portrait_source', $file_name );

	return $attachment_id;
}
```

### A4. `includes/settings/contact.php`: the Portrait setting (exact edits)

**Edit 1** (defaults). Find:
```php
function cptdp_contact_defaults(): array {
	return array(
		'email'        => '',
		'whatsapp_url' => '',
		'linkedin_url' => '',
	);
}
```
Replace with:
```php
function cptdp_contact_defaults(): array {
	return array(
		'email'        => '',
		'whatsapp_url' => '',
		'linkedin_url' => '',
		'portrait_id'  => 0,
	);
}
```
Also change the docblock above it from `@return array<string,string>` to `@return array<string,string|int>`.

**Edit 2** (reader). In `cptdp_get_contact()`, find:
```php
	$value = array_merge( cptdp_contact_defaults(), array_intersect_key( $saved, cptdp_contact_defaults() ) );

	if ( null === $key ) {
```
Replace with:
```php
	$value = array_merge( cptdp_contact_defaults(), array_intersect_key( $saved, cptdp_contact_defaults() ) );

	$value['portrait_id'] = (int) $value['portrait_id'];

	if ( null === $key ) {
```
Change its docblock `@param` to `Optional single key: email, whatsapp_url, linkedin_url, portrait_id.` and `@return array<string,string|int>|string|int`; change the last line `return $value[ $key ] ?? '';` (unchanged code).

**Edit 3** (REST schema). Find `'linkedin_url' => array( 'type' => 'string' ),` (inside `register_setting`, `properties`) and add a line after it:
```php
						'portrait_id'  => array( 'type' => 'integer' ),
```

**Edit 4** (section and field). In `cptdp_contact_register_sections()`, find the line `foreach ( $fields as $key => $field ) {` and insert **before** it:
```php
	add_settings_section(
		'cptdp_contact_portrait_section',
		esc_html__( 'Home page portrait', 'custom-post-types-dp' ),
		'cptdp_contact_render_portrait_intro',
		'cptdp-contact'
	);
	add_settings_field(
		'cptdp_contact_portrait_id',
		esc_html__( 'Portrait', 'custom-post-types-dp' ),
		'cptdp_contact_render_portrait',
		'cptdp-contact',
		'cptdp_contact_portrait_section',
		array( 'label_for' => 'cptdp_contact_portrait_select' )
	);

```
(Order on the page: Booking times, Home page portrait, then Contact fields are added by the loop after it. That is fine; if the Contact section must stay first, move the two calls to after the `foreach` instead. Not blocking.)

**Edit 5** (new functions). Insert this block **before** the docblock of `cptdp_contact_render_times_intro`:
```php
/**
 * Intro of the portrait section.
 */
function cptdp_contact_render_portrait_intro(): void {
	echo '<p>' . esc_html__( 'The cut-out photo shown in the Home page hero. Use a PNG or WebP with a transparent background, about 1200px wide and under 500 KB. It is shown in greyscale. Set its Alternative Text in the Media Library.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the portrait picker: preview, Choose and Remove buttons and the hidden attachment ID.
 */
function cptdp_contact_render_portrait(): void {
	$id  = (int) cptdp_get_contact( 'portrait_id' );
	$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : false;

	printf( '<input type="hidden" id="cptdp_contact_portrait_id" name="cptdp_contact[portrait_id]" value="%d" />', (int) $id );
	printf(
		'<p><img id="cptdp-portrait-preview" src="%1$s" alt="" style="max-width:200px;height:auto;background:#eee;%2$s" /></p>',
		esc_url( $url ? $url : '' ),
		$url ? '' : 'display:none;'
	);
	printf(
		'<button type="button" class="button" id="cptdp_contact_portrait_select">%1$s</button> <button type="button" class="button-link button-link-delete" id="cptdp-portrait-remove" %2$s>%3$s</button>',
		esc_html__( 'Choose image', 'custom-post-types-dp' ),
		$url ? '' : 'style="display:none"',
		esc_html__( 'Remove', 'custom-post-types-dp' )
	);
}

/**
 * Load the media picker script on Settings > Contact only.
 *
 * @param string $hook Admin page hook suffix.
 */
function cptdp_contact_enqueue_admin( string $hook ): void {
	if ( 'settings_page_cptdp-contact' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'cptdp-admin-contact',
		CPTDP_URL . 'assets/admin-contact.js',
		array( 'jquery' ),
		CPTDP_VERSION,
		true
	);
	wp_localize_script(
		'cptdp-admin-contact',
		'cptdpContact',
		array(
			'selectTitle'  => esc_html__( 'Select portrait', 'custom-post-types-dp' ),
			'selectButton' => esc_html__( 'Use this portrait', 'custom-post-types-dp' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cptdp_contact_enqueue_admin' );

```

**Edit 6** (sanitiser). In `cptdp_contact_sanitize()`, find the last two lines of the function:
```php
		$clean[ $key ] = $url;
	}

	return $clean;
```
Replace with:
```php
		$clean[ $key ] = $url;
	}

	$portrait = isset( $input['portrait_id'] ) ? absint( $input['portrait_id'] ) : 0;
	if ( $portrait && ( 'attachment' !== get_post_type( $portrait ) || ! in_array( get_post_mime_type( $portrait ), array( 'image/png', 'image/webp' ), true ) ) ) {
		add_settings_error( 'cptdp_contact', 'cptdp_contact_portrait_id', esc_html__( 'Invalid portrait. Choose a PNG or WebP image from the Media Library.', 'custom-post-types-dp' ) );
		$portrait = 0;
	}
	$clean['portrait_id'] = $portrait;

	return $clean;
```

### A5. `wp-content/plugins/custom-post-types-dp/assets/admin-contact.js` (new, in full)

```js
/* Settings > Contact: media picker for the Home page portrait. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#cptdp_contact_portrait_id' );
		var $preview = $( '#cptdp-portrait-preview' );
		var $remove = $( '#cptdp-portrait-remove' );

		$( '#cptdp_contact_portrait_select' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: cptdpContact.selectTitle,
					button: { text: cptdpContact.selectButton },
					library: { type: [ 'image/png', 'image/webp' ] },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var sizes = attachment.sizes || {};
					var shown = sizes.medium || sizes.full || { url: attachment.url };

					$input.val( attachment.id );
					$preview.attr( 'src', shown.url ).show();
					$remove.show();
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();

			$input.val( '0' );
			$preview.attr( 'src', '' ).hide();
			$remove.hide();
		} );
	} );
}( jQuery ) );
```

### A6. Plugin tests

**`tests/post-types/test-job-role.php`** (new, in full):
```php
<?php
/**
 * Job Role post type tests (DPH-003).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group job-role
 */
class CPTDP_Test_Job_Role extends CPTDP_Test_Case {

	private function make_role( string $title, int $order = 0, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'job_role',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);
	}

	private function is_highlight( int $id ): bool {
		return (bool) get_post_meta( $id, 'cptdp_highlight', true );
	}

	public function test_registration(): void {
		$object = get_post_type_object( 'job_role' );

		$this->assertInstanceOf( WP_Post_Type::class, $object );
		$this->assertFalse( $object->public, 'No front-end URL.' );
		$this->assertFalse( $object->has_archive );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_in_rest );
		$this->assertSame( 24, $object->menu_position );
		$this->assertTrue( post_type_supports( 'job_role', 'title' ) );
		$this->assertTrue( post_type_supports( 'job_role', 'page-attributes' ) );
		$this->assertFalse( post_type_supports( 'job_role', 'editor' ) );
		$this->assertSame( 'Job Roles', $object->labels->name );
		$this->assertSame( 'Job Role', $object->labels->singular_name );
	}

	public function test_highlight_meta_is_registered_for_rest(): void {
		$this->assertTrue( registered_meta_key_exists( 'post', 'cptdp_highlight', 'job_role' ) );
	}

	public function test_marking_a_role_clears_the_previous_highlight(): void {
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );

		update_post_meta( $first, 'cptdp_highlight', 1 );
		$this->assertTrue( $this->is_highlight( $first ) );

		update_post_meta( $second, 'cptdp_highlight', 1 );

		$this->assertTrue( $this->is_highlight( $second ) );
		$this->assertFalse( $this->is_highlight( $first ), 'Only one role can be the highlight.' );
	}

	public function test_a_draft_or_trashed_highlight_is_also_cleared(): void {
		$draft = $this->make_role( 'Draft', 1, 'draft' );
		$new   = $this->make_role( 'New', 2 );

		update_post_meta( $draft, 'cptdp_highlight', 1 );
		update_post_meta( $new, 'cptdp_highlight', 1 );

		$this->assertFalse( $this->is_highlight( $draft ) );
	}

	public function test_unmarking_leaves_no_highlight_and_touches_nothing_else(): void {
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );
		update_post_meta( $first, 'cptdp_highlight', 1 );

		delete_post_meta( $first, 'cptdp_highlight' );

		$this->assertFalse( $this->is_highlight( $first ) );
		$this->assertFalse( $this->is_highlight( $second ) );
	}

	public function test_a_false_value_does_not_clear_the_current_highlight(): void {
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );
		update_post_meta( $first, 'cptdp_highlight', 1 );

		update_post_meta( $second, 'cptdp_highlight', false );

		$this->assertTrue( $this->is_highlight( $first ) );
	}

	public function test_other_post_types_do_not_affect_job_roles(): void {
		$role = $this->make_role( 'A', 1 );
		update_post_meta( $role, 'cptdp_highlight', 1 );

		$document = self::factory()->post->create( array( 'post_type' => 'document' ) );
		update_post_meta( $document, 'cptdp_highlight', 1 );

		$this->assertTrue( $this->is_highlight( $role ) );
		$this->assertTrue( $this->is_highlight( $document ) );
	}

	public function test_rest_update_obeys_the_single_highlight_rule(): void {
		$this->acting_as( 'administrator' );
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );
		update_post_meta( $first, 'cptdp_highlight', 1 );

		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/job_role/' . $second );
		$request->set_body_params( array( 'meta' => array( 'cptdp_highlight' => true ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->is_highlight( $second ) );
		$this->assertFalse( $this->is_highlight( $first ) );

		$GLOBALS['wp_rest_server'] = null;
	}

	public function test_meta_box_saves_and_clears_the_highlight(): void {
		$this->acting_as( 'administrator' );
		$id = $this->make_role( 'A', 1 );

		$_POST = array(
			'cptdp_job_role_highlight_nonce' => wp_create_nonce( 'cptdp_job_role_highlight' ),
			'cptdp_highlight'                => '1',
		);
		cptdp_job_role_save_meta( $id );
		$this->assertTrue( $this->is_highlight( $id ) );

		$_POST = array( 'cptdp_job_role_highlight_nonce' => wp_create_nonce( 'cptdp_job_role_highlight' ) );
		cptdp_job_role_save_meta( $id );
		$this->assertFalse( $this->is_highlight( $id ) );
	}

	public function test_meta_box_ignores_a_missing_nonce_and_a_user_who_cannot_edit(): void {
		$id = $this->make_role( 'A', 1 );

		$_POST = array( 'cptdp_highlight' => '1' );
		$this->acting_as( 'administrator' );
		cptdp_job_role_save_meta( $id );
		$this->assertFalse( $this->is_highlight( $id ), 'No nonce, no write.' );

		$_POST = array(
			'cptdp_job_role_highlight_nonce' => wp_create_nonce( 'cptdp_job_role_highlight' ),
			'cptdp_highlight'                => '1',
		);
		$this->acting_as( 'subscriber' );
		cptdp_job_role_save_meta( $id );
		$this->assertFalse( $this->is_highlight( $id ), 'A subscriber cannot edit.' );
	}

	public function test_columns_follow_title(): void {
		$columns = cptdp_job_role_columns( array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) );

		$this->assertSame( array( 'cb', 'title', 'cptdp_highlight', 'menu_order', 'date' ), array_keys( $columns ) );
	}
}
```

**`tests/seeders/test-job-roles.php`** (new, in full):
```php
<?php
/**
 * Job Roles seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group job-role
 * @group seeder
 */
class CPTDP_Test_Seeder_Job_Roles extends CPTDP_Seeder_Test_Case {

	protected function seeder_name(): string {
		return 'job-roles';
	}

	public function test_the_four_default_roles_are_created_in_order(): void {
		$this->run_seeder();

		$this->assertSame(
			array( 'Virtual Assistant', 'Social Media Manager', 'Digital Marketer', 'Web Developer' ),
			wp_list_pluck( $this->items(), 'post_title' )
		);
	}

	public function test_virtual_assistant_is_the_only_highlight(): void {
		$this->run_seeder();

		$marked = array();
		foreach ( $this->items() as $item ) {
			if ( get_post_meta( $item->ID, 'cptdp_highlight', true ) ) {
				$marked[] = $item->post_title;
			}
		}

		$this->assertSame( array( 'Virtual Assistant' ), $marked );
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$found = get_posts(
				array(
					'post_type'   => 'job_role',
					'post_status' => 'any',
					'title'       => $row['title'],
				)
			);

			$this->assertCount( 1, $found, $row['title'] );
			$this->assertSame( (int) $row['order'], (int) $found[0]->menu_order, $row['title'] );
		}
	}

	public function test_row_with_a_bad_highlight_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,highlight\n1,A,1\n2,B,yes\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_two_highlights_error_before_writing(): void {
		$path   = $this->temp_csv( "order,title,highlight\n1,A,1\n2,B,1\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_row_with_non_numeric_order_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,highlight\nabc,A,0\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 0, $this->count_items() );
	}
}
```

**`tests/settings/test-contact.php`** (edit the existing file):
1. In every assertion that lists the three keys (`test_defaults`, `test_get_contact_merges_saved_values_and_drops_unknown_keys`, and any other found by searching `'linkedin_url' => ''`), add `'portrait_id'  => 0,` after the `linkedin_url` line.
2. Add these tests at the end of the class (before the final `}`):
```php
	private function make_image( string $mime ): int {
		return self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'post_title'     => 'portrait',
			)
		);
	}

	public function test_portrait_accepts_png_and_webp_attachments(): void {
		foreach ( array( 'image/png', 'image/webp' ) as $mime ) {
			$id    = $this->make_image( $mime );
			$clean = cptdp_contact_sanitize( array( 'portrait_id' => (string) $id ) );

			$this->assertSame( $id, $clean['portrait_id'], $mime );
		}
		$this->assertSame( array(), get_settings_errors( 'cptdp_contact' ) );
	}

	public function test_portrait_rejects_other_types_and_non_attachments(): void {
		$jpeg = $this->make_image( 'image/jpeg' );
		$post = self::factory()->post->create();

		foreach ( array( $jpeg, $post, 999999 ) as $bad ) {
			$GLOBALS['wp_settings_errors'] = array();
			$clean                         = cptdp_contact_sanitize( array( 'portrait_id' => (string) $bad ) );

			$this->assertSame( 0, $clean['portrait_id'], (string) $bad );
			$this->assertNotEmpty( get_settings_errors( 'cptdp_contact' ), (string) $bad );
		}
	}

	public function test_portrait_missing_or_zero_is_zero_without_an_error(): void {
		$this->assertSame( 0, cptdp_contact_sanitize( array() )['portrait_id'] );
		$this->assertSame( 0, cptdp_contact_sanitize( array( 'portrait_id' => '0' ) )['portrait_id'] );
		$this->assertSame( array(), get_settings_errors( 'cptdp_contact' ) );
	}

	public function test_get_contact_returns_the_portrait_as_an_integer(): void {
		update_option( 'cptdp_contact', array( 'portrait_id' => '42' ) );

		$this->assertSame( 42, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_portrait_field_renders_the_picker_and_the_saved_id(): void {
		$id = $this->make_image( 'image/png' );
		update_option( 'cptdp_contact', array( 'portrait_id' => $id ) );

		ob_start();
		cptdp_contact_render_portrait();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="cptdp_contact[portrait_id]" value="' . $id . '"', $html );
		$this->assertStringContainsString( 'id="cptdp_contact_portrait_select"', $html );
		$this->assertStringContainsString( 'id="cptdp-portrait-remove"', $html );
	}

	public function test_the_picker_script_loads_on_the_contact_page_only(): void {
		wp_dequeue_script( 'cptdp-admin-contact' );

		cptdp_contact_enqueue_admin( 'edit.php' );
		$this->assertFalse( wp_script_is( 'cptdp-admin-contact', 'enqueued' ) );

		cptdp_contact_enqueue_admin( 'settings_page_cptdp-contact' );
		$this->assertTrue( wp_script_is( 'cptdp-admin-contact', 'enqueued' ) );
	}
```
(`get_settings_errors()` is only defined in wp-admin; the existing tests in this file already use `$GLOBALS['wp_settings_errors']`, so if `get_settings_errors` is undefined in this suite, replace those three calls with `$this->assertEmpty( $GLOBALS['wp_settings_errors'] )` / `assertNotEmpty`, and require `ABSPATH . 'wp-admin/includes/template.php'` at the top of the test class is NOT needed. Check how the existing tests in the file read errors and copy that.)

**`tests/seeders/test-portrait.php`** (new, in full). It extends `CPTDP_Test_Case`, not `CPTDP_Seeder_Test_Case` (that base assumes a post type and one item per CSV row). If `media_handle_sideload` fails here with an upload-directory error, stop and report.
```php
<?php
/**
 * Portrait seeder tests (DPH-001).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group seeder
 * @group settings
 */
class CPTDP_Test_Seeder_Portrait extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();

		delete_option( 'cptdp_contact' );
	}

	private function run_seeder( bool $dry_run = false ) {
		$seeder = cptdp_get_seeders()['portrait'];

		return call_user_func( $seeder['callback'], $dry_run, $seeder['csv'] );
	}

	private function temp_csv( string $contents ): string {
		$path = wp_tempnam( 'portrait.csv' );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $path;
	}

	public function test_it_is_registered_with_its_csv_and_the_bundled_png(): void {
		$seeders = cptdp_get_seeders();
		$png     = CPTDP_PATH . 'data/seeders/portrait/portrait.png';

		$this->assertArrayHasKey( 'portrait', $seeders );
		$this->assertSame( CPTDP_PATH . 'data/seeders/portrait.csv', $seeders['portrait']['csv'] );
		$this->assertFileExists( $png );
		$this->assertSame( 'image/png', wp_check_filetype_and_ext( $png, 'portrait.png' )['type'] );
		$this->assertSame( array( 696, 928 ), array_slice( getimagesize( $png ), 0, 2 ) );
	}

	public function test_it_sets_the_portrait_as_a_png_attachment_the_settings_accept(): void {
		$result = $this->run_seeder();

		$this->assertSame( array( 'portrait.png' ), $result['created'] );

		$id = cptdp_get_contact( 'portrait_id' );
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'attachment', get_post_type( $id ) );
		$this->assertSame( 'image/png', get_post_mime_type( $id ) );
		$this->assertSame( $id, cptdp_contact_sanitize( array( 'portrait_id' => (string) $id ) )['portrait_id'] );
	}

	public function test_it_keeps_the_other_contact_values(): void {
		update_option(
			'cptdp_contact',
			array(
				'email'        => 'dave@example.com',
				'linkedin_url' => 'https://www.linkedin.com/in/dave',
			)
		);

		$this->run_seeder();

		$this->assertSame( 'dave@example.com', cptdp_get_contact( 'email' ) );
		$this->assertSame( 'https://www.linkedin.com/in/dave', cptdp_get_contact( 'linkedin_url' ) );
		$this->assertGreaterThan( 0, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_a_second_run_changes_nothing_and_uploads_nothing(): void {
		$this->run_seeder();
		$id    = cptdp_get_contact( 'portrait_id' );
		$count = (int) wp_count_posts( 'attachment' )->inherit;

		$result = $this->run_seeder();

		$this->assertSame( array(), $result['created'] );
		$this->assertSame( array( 'portrait.png' ), $result['skipped'] );
		$this->assertSame( $id, cptdp_get_contact( 'portrait_id' ) );
		$this->assertSame( $count, (int) wp_count_posts( 'attachment' )->inherit );
	}

	public function test_an_existing_portrait_is_never_replaced(): void {
		$mine = self::factory()->attachment->create( array( 'post_mime_type' => 'image/webp' ) );
		update_option( 'cptdp_contact', array( 'portrait_id' => $mine ) );

		$result = $this->run_seeder();

		$this->assertSame( array( 'portrait.png' ), $result['skipped'] );
		$this->assertSame( $mine, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_a_dangling_portrait_id_is_replaced(): void {
		update_option( 'cptdp_contact', array( 'portrait_id' => 999999 ) );

		$this->run_seeder();

		$this->assertNotSame( 999999, cptdp_get_contact( 'portrait_id' ) );
		$this->assertGreaterThan( 0, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_after_the_portrait_is_removed_the_same_upload_is_reused(): void {
		$this->run_seeder();
		$first = cptdp_get_contact( 'portrait_id' );
		update_option( 'cptdp_contact', array( 'portrait_id' => 0 ) );

		$this->run_seeder();

		$this->assertSame( $first, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$count  = (int) wp_count_posts( 'attachment' )->inherit;
		$result = $this->run_seeder( true );

		$this->assertSame( array( 'portrait.png' ), $result['created'] );
		$this->assertSame( 0, cptdp_get_contact( 'portrait_id' ) );
		$this->assertSame( $count, (int) wp_count_posts( 'attachment' )->inherit );
	}

	public function test_a_file_name_with_a_path_errors_before_writing(): void {
		$path   = $this->temp_csv( "file\n../evil.png\n" );
		$result = cptdp_seed_portrait( false, $path );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_portrait_missing', $result->get_error_code() );
		$this->assertSame( 0, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_more_than_one_row_errors(): void {
		$path   = $this->temp_csv( "file\nportrait.png\nportrait.png\n" );
		$result = cptdp_seed_portrait( false, $path );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
	}
}
```

### A7. Plugin version
`custom-post-types-dp.php`: change `Version: 0.34.0` and `define( 'CPTDP_VERSION', '0.34.0' );` to `0.35.0` (both).

### Gate A
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter 'CPTDP_Test_Job_Role|CPTDP_Test_Seeder_Job_Roles|CPTDP_Test_Seeder_Portrait|CPTDP_Test_Contact'
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev wp cptdp seed job-roles --dry-run
ddev wp cptdp seed job-roles
ddev wp cptdp seed portrait --dry-run
ddev wp cptdp seed portrait
ddev wp option get cptdp_contact --format=json   # portrait_id is now a number
```
Expected: filtered tests green; full plugin suite green (it was 1035 tests, 12 skipped; it grows by the new tests, none fail). The dry run lists four creations; the real run creates them; `ddev wp post list --post_type=job_role --fields=post_title,menu_order` shows four rows, and `ddev wp post meta get <Virtual Assistant id> cptdp_highlight` prints `1`.

---

## Phase B. Theme: the `devportfolio/hero` block

### B1. `functions.php`: register the block and add the helpers

**Edit 1.** Find `		register_block_type( __DIR__ . '/blocks/contact' );` and add after it:
```php
		register_block_type( __DIR__ . '/blocks/hero' );
```

**Edit 2.** Insert this block **before** the line `if ( ! function_exists( 'devportfolio_enqueue_page_styles' ) ) :`:
```php
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
	function devportfolio_hero_name_parts() {
		$parts = preg_split( '/\s+/', trim( (string) get_bloginfo( 'name' ) ), 2 );

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

```

### B2. `blocks/hero/block.json` (new, in full)
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/hero",
	"title": "Hero",
	"category": "widgets",
	"description": "The Home page hero: the big name, the portrait, the role, the call to action and the contact links.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css"
}
```

### B3. `blocks/hero/render.php` (new, in full)
```php
<?php
/**
 * Server render of the Home hero (DPH-001, DPH-003, DPH-004).
 *
 * Name from the site title, portrait from Settings > Contact, roles from the Job Role post type, links from
 * Settings > Contact. A part without data is left out; the call to action always shows.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_name    = devportfolio_hero_name_parts();
$devportfolio_roles   = devportfolio_hero_roles();
$devportfolio_pid     = devportfolio_hero_portrait_id();
$devportfolio_contact = function_exists( 'cptdp_get_contact' ) ? cptdp_get_contact() : array();
$devportfolio_links   = array();

if ( ! empty( $devportfolio_contact['linkedin_url'] ) ) {
	$devportfolio_links[] = array(
		'prefix' => 'in',
		'label'  => __( 'LinkedIn', 'devportfolio' ),
		'href'   => $devportfolio_contact['linkedin_url'],
		'blank'  => true,
	);
}

if ( ! empty( $devportfolio_contact['whatsapp_url'] ) ) {
	$devportfolio_links[] = array(
		'prefix' => '',
		'label'  => __( 'WhatsApp', 'devportfolio' ),
		'href'   => $devportfolio_contact['whatsapp_url'],
		'blank'  => true,
	);
}

if ( ! empty( $devportfolio_contact['email'] ) ) {
	$devportfolio_links[] = array(
		'prefix' => '',
		'label'  => __( 'Email me', 'devportfolio' ),
		'href'   => 'mailto:' . $devportfolio_contact['email'],
		'blank'  => false,
	);
}

$devportfolio_alt = '';
if ( $devportfolio_pid ) {
	$devportfolio_alt = trim( (string) get_post_meta( $devportfolio_pid, '_wp_attachment_image_alt', true ) );
	if ( '' === $devportfolio_alt ) {
		/* translators: %s: site title. */
		$devportfolio_alt = sprintf( __( 'Portrait of %s', 'devportfolio' ), get_bloginfo( 'name' ) );
	}
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'hero' ) );
?>
<section <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php if ( '' !== $devportfolio_name['first'] ) : ?>
		<h1 class="hero__name"><span class="hero__name-first"><?php echo esc_html( $devportfolio_name['first'] ); ?></span><?php echo '' !== $devportfolio_name['rest'] ? ' <span class="hero__name-rest">' . esc_html( $devportfolio_name['rest'] ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here. ?></h1>
	<?php endif; ?>

	<?php if ( $devportfolio_pid ) : ?>
		<div class="hero__portrait">
			<?php
			echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes.
				$devportfolio_pid,
				'large',
				false,
				array(
					'class'         => 'hero__portrait-img',
					'alt'           => $devportfolio_alt,
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'decoding'      => 'async',
					'sizes'         => devportfolio_hero_portrait_sizes(),
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="hero__intro">
		<?php if ( '' !== $devportfolio_roles['main'] ) : ?>
			<p class="hero__role"><?php echo esc_html( $devportfolio_roles['main'] ); ?></p>
			<?php if ( ! empty( $devportfolio_roles['others'] ) ) : ?>
				<p class="hero__roles"><?php echo esc_html( implode( ' · ', $devportfolio_roles['others'] ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<a class="hero__cta" href="#contact"><?php echo esc_html__( 'Let’s work together', 'devportfolio' ); ?> <span aria-hidden="true">↗</span></a>
	</div>

	<?php if ( ! empty( $devportfolio_links ) ) : ?>
		<ul class="hero__links">
			<?php foreach ( $devportfolio_links as $devportfolio_link ) : ?>
				<li>
					<a class="hero__pill" href="<?php echo esc_url( $devportfolio_link['href'] ); ?>"<?php echo $devportfolio_link['blank'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo '' !== $devportfolio_link['prefix'] ? '<span aria-hidden="true">' . esc_html( $devportfolio_link['prefix'] ) . '</span> ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here. ?><?php echo esc_html( $devportfolio_link['label'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="hero__scroll" aria-hidden="true"><?php echo esc_html__( 'Scroll down', 'devportfolio' ); ?> ↓</p>
</section>
```

### B4. `blocks/hero/style.css` (new, in full)

Values come from the wireframe hero CSS (lines 19 to 26) and its phone rule (line 289), with the 900px breakpoint of the story. Palette tokens: `contrast` #222, `mid` #8a8a8a, `line` #c9c9c9, `base` #fff.

```css
.hero {
	position: relative;
	height: 500px;
	overflow: hidden;
	background: linear-gradient(#fff, #f4f4f4);
}

/* DPH-001: real text, one line, scales with the width. DAVE is outlined, the rest solid. */
.hero__name {
	position: absolute;
	inset-inline: 0;
	top: 46px;
	margin: 0;
	text-align: center;
	font-size: clamp(48px, 8.6vw, 96px);
	font-weight: 900;
	letter-spacing: -2px;
	line-height: 1;
	text-transform: uppercase;
	white-space: nowrap;
	color: var(--wp--preset--color--contrast);
}

.hero__name-first {
	color: transparent;
	-webkit-text-stroke: 2.5px var(--wp--preset--color--contrast);
}

/* The portrait sits on the bottom edge, over the lower half of the name. Greyscale in CSS; the file keeps its colour. */
.hero__portrait {
	position: absolute;
	left: 50%;
	bottom: 0;
	z-index: 2;
	width: min(370px, 42%);
	transform: translateX(-50%);
}

.hero__portrait-img {
	display: block;
	width: 100%;
	height: auto;
	max-height: 460px;
	object-fit: contain;
	object-position: bottom center;
	filter: grayscale(1);
}

/* DPH-003 */
.hero__intro {
	position: absolute;
	left: clamp(16px, 3vw, 36px);
	bottom: 44px;
	z-index: 3;
	max-width: 290px;
}

.hero__role {
	margin: 0 0 4px;
	font-size: 21px;
	font-weight: 700;
	line-height: 1.2;
}

.hero__roles {
	margin: 0 0 12px;
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0.04em;
	line-height: 1.5;
	text-transform: uppercase;
}

.hero__cta {
	display: inline-block;
	padding: 7px 14px;
	border: 1.5px solid var(--wp--preset--color--contrast);
	border-radius: 6px;
	background: var(--wp--preset--color--contrast);
	color: var(--wp--preset--color--base);
	font-size: 13px;
	text-align: center;
	text-decoration: none;
}

.hero__cta:hover {
	background: var(--wp--preset--color--base);
	color: var(--wp--preset--color--contrast);
}

/* DPH-004 */
.hero__links {
	position: absolute;
	right: clamp(16px, 3vw, 36px);
	bottom: 44px;
	z-index: 3;
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.hero__pill {
	display: inline-block;
	padding: 5px 14px;
	border: 1.5px solid var(--wp--preset--color--line);
	border-radius: 20px;
	background: var(--wp--preset--color--base);
	color: var(--wp--preset--color--contrast);
	font-size: 12px;
	text-decoration: none;
}

.hero__pill:hover {
	border-color: var(--wp--preset--color--contrast);
}

.hero__scroll {
	position: absolute;
	left: clamp(16px, 3vw, 36px);
	bottom: 14px;
	z-index: 3;
	margin: 0;
	font-size: 11px;
	color: var(--wp--preset--color--mid);
}

.hero__cta:focus-visible,
.hero__pill:focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 2px;
}

@media (prefers-reduced-motion: no-preference) {
	html:has(.hero) {
		scroll-behavior: smooth;
	}
}

@media (max-width: 1100px) {
	.hero__intro {
		max-width: 200px;
	}
}

/* Phones and small tablets (900px and below): the name is hidden (still read by screen readers, it is the h1),
   the portrait leads, the role and a full-width button sit under it, the roles line, pills and hint are hidden
   (the footer lists the same links). */
@media (max-width: 900px) {
	.hero {
		display: flex;
		flex-direction: column;
		align-items: center;
		height: auto;
		padding: 30px 16px 26px;
	}

	.hero__name {
		position: absolute;
		width: 1px;
		height: 1px;
		margin: -1px;
		padding: 0;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}

	.hero__portrait {
		position: relative;
		left: auto;
		bottom: auto;
		width: 55%;
		transform: none;
		translate: 8% 0;
	}

	.hero__intro {
		position: relative;
		left: auto;
		bottom: auto;
		width: 100%;
		max-width: none;
		padding-top: 12px;
		text-align: center;
	}

	.hero__role {
		font-size: 24px;
	}

	.hero__cta {
		display: block;
		padding: 12px;
	}

	.hero__roles,
	.hero__links,
	.hero__scroll {
		display: none;
	}
}
```

### B5. `templates/front-page.html`: place the block

Find:
```html
	<!-- The hero (DPH-001, DPH-003, DPH-004) goes here, above the stats strip. -->
```
Replace with:
```html
	<!-- wp:devportfolio/hero /-->
```

### B6. Verify the Contact section is not hidden under the sticky header after the jump
Open `/` in a browser, click "Let's work together". If the Contact heading lands under the sticky header, add to `blocks/contact/style.css`:
```css
.contact {
	scroll-margin-top: calc(var(--site-header-height, 0px) + 16px);
}
```
(If the file already has a `.contact {` rule, add the declaration inside it instead.) Do not add it if the heading is visible.

---

## Phase C. Theme tests

### C1. `tests/test-hero.php` (new, in full)
```php
<?php
/**
 * Tests for the Home hero (DPH-001, DPH-003, DPH-004).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Hero extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/hero' ) ) {
			devportfolio_register_blocks();
		}

		update_option( 'blogname', 'Dave Portfolio' );
		update_option( 'cptdp_contact', array() );
		devportfolio_memo_flush();
	}

	private function make_role( string $title, int $order, bool $highlight = false, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'job_role',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);

		if ( $highlight ) {
			update_post_meta( $id, 'cptdp_highlight', 1 );
		}

		return $id;
	}

	private function make_portrait( string $alt = '' ): int {
		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'portrait',
				'file'           => 'portrait.png',
			)
		);

		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}

		update_option( 'cptdp_contact', array( 'portrait_id' => $id ) );

		return $id;
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/hero /-->' );
	}

	// DPH-001: the name.

	public function test_name_is_real_text_in_the_h1_split_into_outlined_and_solid(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '#<h1 class="hero__name"><span class="hero__name-first">Dave</span> <span class="hero__name-rest">Portfolio</span></h1>#', $html );
		$this->assertStringNotContainsString( '<img', substr( $html, (int) strpos( $html, '<h1' ), (int) strpos( $html, '</h1>' ) - (int) strpos( $html, '<h1' ) ), 'The name is not an image.' );
	}

	public function test_a_one_word_site_title_has_no_solid_part(): void {
		update_option( 'blogname', 'Dave' );

		$html = $this->render();

		$this->assertStringContainsString( '<span class="hero__name-first">Dave</span></h1>', $html );
		$this->assertStringNotContainsString( 'hero__name-rest', $html );
	}

	public function test_the_site_title_is_escaped(): void {
		update_option( 'blogname', 'A <script>x</script> B' );

		$this->assertStringNotContainsString( '<script>x', $this->render() );
	}

	// DPH-001: the portrait.

	public function test_portrait_is_the_only_image_with_alt_eager_and_high_priority(): void {
		$this->make_portrait( 'Dave smiling' );
		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<img' ) );
		$this->assertStringContainsString( 'class="hero__portrait-img', $html );
		$this->assertStringContainsString( 'alt="Dave smiling"', $html );
		$this->assertStringContainsString( 'loading="eager"', $html );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'sizes="' . devportfolio_hero_portrait_sizes() . '"', $html );
	}

	public function test_portrait_alt_falls_back_to_the_site_title(): void {
		$this->make_portrait();

		$this->assertStringContainsString( 'alt="Portrait of Dave Portfolio"', $this->render() );
	}

	public function test_without_a_portrait_the_rest_of_the_hero_still_renders(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'hero__portrait', $html );
		$this->assertStringContainsString( 'hero__cta', $html );
	}

	public function test_a_deleted_portrait_file_renders_no_image(): void {
		$id = $this->make_portrait();
		wp_delete_attachment( $id, true );

		$this->assertStringNotContainsString( '<img', $this->render() );
	}

	public function test_portrait_is_preloaded_on_the_front_page_only(): void {
		$id = $this->make_portrait();

		$this->go_to( home_url( '/?p=' . self::factory()->post->create() ) );
		ob_start();
		devportfolio_hero_preload_portrait();
		$this->assertSame( '', ob_get_clean(), 'Not the front page.' );

		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		ob_start();
		devportfolio_hero_preload_portrait();
		$out = (string) ob_get_clean();

		$this->assertStringContainsString( 'rel="preload" as="image"', $out );
		$this->assertStringContainsString( 'fetchpriority="high"', $out );
		$this->assertStringContainsString( 'imagesizes="' . devportfolio_hero_portrait_sizes() . '"', $out );
		$this->assertStringContainsString( (string) wp_get_attachment_image_url( $id, 'large' ), $out );
	}

	public function test_nothing_is_preloaded_without_a_portrait(): void {
		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );

		ob_start();
		devportfolio_hero_preload_portrait();
		$this->assertSame( '', ob_get_clean() );
	}

	// DPH-003: roles and call to action.

	public function test_highlighted_role_is_on_top_and_the_others_follow_in_order(): void {
		$this->make_role( 'Virtual Assistant', 1 );
		$this->make_role( 'Social Media Manager', 2 );
		$this->make_role( 'Digital Marketer', 3, true );
		$this->make_role( 'Web Developer', 4 );

		$html = $this->render();

		$this->assertStringContainsString( '<p class="hero__role">Digital Marketer</p>', $html );
		$this->assertStringContainsString( '<p class="hero__roles">Virtual Assistant · Social Media Manager · Web Developer</p>', $html );
	}

	public function test_without_a_highlight_the_first_role_by_order_is_on_top(): void {
		$this->make_role( 'Web Developer', 4 );
		$this->make_role( 'Virtual Assistant', 1 );

		$html = $this->render();

		$this->assertStringContainsString( '<p class="hero__role">Virtual Assistant</p>', $html );
		$this->assertStringContainsString( '<p class="hero__roles">Web Developer</p>', $html );
	}

	public function test_a_draft_role_is_never_shown(): void {
		$this->make_role( 'Virtual Assistant', 1, true );
		$this->make_role( 'Secret', 2, false, 'draft' );

		$this->assertStringNotContainsString( 'Secret', $this->render() );
	}

	public function test_a_draft_highlight_is_ignored_and_the_first_published_role_leads(): void {
		$this->make_role( 'Hidden', 1, true, 'draft' );
		$this->make_role( 'Virtual Assistant', 2 );

		$this->assertStringContainsString( '<p class="hero__role">Virtual Assistant</p>', $this->render() );
	}

	public function test_a_single_role_has_no_second_line(): void {
		$this->make_role( 'Virtual Assistant', 1, true );

		$html = $this->render();

		$this->assertStringContainsString( 'hero__role"', $html );
		$this->assertStringNotContainsString( 'hero__roles', $html );
	}

	public function test_no_roles_leaves_out_the_role_block_but_keeps_the_button(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<p class="hero__role"', $html );
		$this->assertStringNotContainsString( 'hero__roles', $html );
		$this->assertStringContainsString( 'class="hero__cta" href="#contact"', $html );
	}

	public function test_role_names_are_escaped(): void {
		$this->make_role( '<b>x</b>', 1, true );

		$this->assertStringNotContainsString( '<b>x</b>', $this->render() );
	}

	public function test_the_button_scrolls_to_contact_in_the_same_tab_and_is_the_only_one(): void {
		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, 'hero__cta' ) );
		$this->assertMatchesRegularExpression( '#<a class="hero__cta" href="\#contact">Let’s work together <span aria-hidden="true">↗</span></a>#u', $html );
		$this->assertStringNotContainsString( 'target=', substr( $html, (int) strpos( $html, 'hero__cta' ), 80 ) );
	}

	public function test_scroll_hint_is_decorative(): void {
		$this->assertStringContainsString( '<p class="hero__scroll" aria-hidden="true">Scroll down ↓</p>', $this->render() );
	}

	// DPH-004: the links.

	public function test_three_pills_with_new_tab_for_linkedin_and_whatsapp_and_mailto_for_email(): void {
		update_option(
			'cptdp_contact',
			array(
				'linkedin_url' => 'https://www.linkedin.com/in/dave',
				'whatsapp_url' => 'https://wa.me/639346973102',
				'email'        => 'dave@example.com',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'href="https://www.linkedin.com/in/dave" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">in</span> LinkedIn</a>', $html );
		$this->assertStringContainsString( 'href="https://wa.me/639346973102" target="_blank" rel="noopener noreferrer">WhatsApp</a>', $html );
		$this->assertStringContainsString( 'href="mailto:dave@example.com">Email me</a>', $html );
		$this->assertSame( 3, substr_count( $html, 'class="hero__pill"' ) );
	}

	public function test_a_link_without_a_value_is_not_shown(): void {
		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );

		$html = $this->render();

		$this->assertStringNotContainsString( 'LinkedIn', $html );
		$this->assertStringNotContainsString( 'WhatsApp', $html );
		$this->assertSame( 1, substr_count( $html, 'class="hero__pill"' ) );
	}

	public function test_no_links_means_no_list(): void {
		$this->assertStringNotContainsString( 'hero__links', $this->render() );
	}

	public function test_link_urls_are_escaped(): void {
		update_option( 'cptdp_contact', array( 'linkedin_url' => 'https://www.linkedin.com/in/a"onmouseover="x' ) );

		$this->assertStringNotContainsString( '"onmouseover="x', $this->render() );
	}

	// CSS contract (no browser runner in the repo).

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/hero/style.css' );
	}

	private function phone_css(): string {
		$css = $this->css();
		$pos = strpos( $css, '@media (max-width: 900px)' );

		$this->assertNotFalse( $pos, 'The phone rules are at 900px and below.' );

		return substr( $css, (int) $pos );
	}

	public function test_css_outlines_the_first_word_with_a_2_5px_stroke_and_scales_the_name(): void {
		$css = $this->css();

		$this->assertStringContainsString( '-webkit-text-stroke: 2.5px', $css );
		$this->assertStringContainsString( 'font-size: clamp(48px, 8.6vw, 96px)', $css );
		$this->assertStringContainsString( 'white-space: nowrap', $css );
	}

	public function test_css_draws_the_portrait_in_greyscale_about_40_percent_wide_on_the_bottom_edge(): void {
		$css = $this->css();

		$this->assertStringContainsString( 'filter: grayscale(1)', $css );
		$this->assertStringContainsString( 'width: min(370px, 42%)', $css );
		$this->assertMatchesRegularExpression( '/\.hero__portrait \{[^}]*bottom: 0;/s', $css );
	}

	public function test_css_phone_rules_hide_the_name_visually_and_the_extras_and_widen_the_portrait(): void {
		$phone = $this->phone_css();

		$this->assertMatchesRegularExpression( '/\.hero__name \{[^}]*clip-path: inset\(50%\)/s', $phone );
		$this->assertStringNotContainsString( 'display: none', substr( $phone, (int) strpos( $phone, '.hero__name {' ), 200 ), 'The h1 stays in the accessibility tree.' );
		$this->assertMatchesRegularExpression( '/\.hero__roles,\s*\.hero__links,\s*\.hero__scroll \{\s*display: none;/', $phone );
		$this->assertMatchesRegularExpression( '/\.hero__portrait \{[^}]*width: 55%;/s', $phone );
		$this->assertMatchesRegularExpression( '/\.hero__cta \{[^}]*display: block;/s', $phone );
	}

	public function test_css_has_no_other_breakpoint_for_the_phone_layout(): void {
		$this->assertStringNotContainsString( '620px', $this->css() );
	}

	// The preload helper and the image share one sizes string.

	public function test_sizes_string_is_defined_once(): void {
		$this->assertSame( 1, substr_count( (string) file_get_contents( $this->theme_dir() . '/blocks/hero/render.php' ), 'devportfolio_hero_portrait_sizes()' ) );
		$this->assertStringNotContainsString( '42vw', (string) file_get_contents( $this->theme_dir() . '/blocks/hero/render.php' ) );
	}
}
```

If `self::factory()->attachment->create` with `'file'` does not give `wp_get_attachment_image_url()` a URL in this WP version, stop and report (do not rewrite the helper to suit the test).

### C2. `tests/test-home-template.php`: add one test
Add before the final `}` of the class (it already has a `template()` helper, used by `test_testimonials_and_tools_follow_selected_work`):
```php
	public function test_hero_comes_first_then_the_stats_strip(): void {
		$tpl = $this->template();

		$hero  = strpos( $tpl, '<!-- wp:devportfolio/hero /-->' );
		$stats = strpos( $tpl, '<!-- wp:devportfolio/stats-strip /-->' );

		$this->assertNotFalse( $hero, 'The hero block is in the Home template.' );
		$this->assertNotFalse( $stats );
		$this->assertLessThan( $stats, $hero );
		$this->assertStringNotContainsString( 'goes here', $tpl, 'The placeholder comment is gone.' );
	}
```

### Gate C
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit --filter 'Devportfolio_Test_Hero|Devportfolio_Test_Home_Template'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
```
All green (theme was 734 tests). Node suites are untouched; no JS was added to the theme.

---

## Phase D. Browser check, docs

### D1. Browser check (no runner in the repo; do it by hand and record the result in the PR)
1. wp-admin > Settings > Contact: **Home page portrait** shows "Choose image"; the picker lists only PNG and WebP; after choosing, the preview shows; Save; reload keeps it; Remove then Save clears it. A JPEG ID forced through REST is refused.
2. wp-admin > Job Roles: four roles after `ddev wp cptdp seed job-roles`, star on Virtual Assistant. Tick the box on Digital Marketer, Update: the star moves; the list shows one star.
3. After `ddev wp cptdp seed portrait`, Settings > Contact shows the default portrait in the preview. `/` at 1440px: `DAVE` outlined, `PORTFOLIO` solid, one line; portrait centred about 40% wide on the bottom edge over the lower half of the name, face not cut off, greyscale; Digital Marketer large, the other three on the next line with dots; one black button; pills at the bottom right; "Scroll down ↓" bottom left.
4. Click the button: smooth-scrolls to Contact, no new tab, the Contact heading is visible under the sticky header (else do B6).
5. 1280, 1024, 901px: the text block does not cover the face.
6. 390px and 900px: name not visible, nav shows "Dave Portfolio", portrait about 55% wide shifted slightly right, role and a full-width button below, roles line, pills and hint hidden, footer still lists LinkedIn, WhatsApp and Email.
7. DevTools Network on `/`: the portrait is the only image requested above the fold, requested early (preload, "High" priority). Page source shows the `<link rel="preload" as="image" ...>` in `<head>`.
8. Remove the portrait and the links in wp-admin: hero still renders without errors.

### D2. Story files
`.claude/plans/home/Home-User-Stories-Revised.csv`: replace the DPH-001, DPH-003 and DPH-004 rows with the pasted text of the three stories, then add to DPH-001 a fifth acceptance line "5.) The portrait is uploaded in Settings > Contact (PNG or WebP)." and to DPH-003 "5.) The role and the specialities come from Job Roles; the highlighted role is on top." Keep the CSV's quoting (double the inner quotes).

### D3. `AGENTS.md`
- Overview: add `job_role` to the registered CPTs list (`includes/post-types/job-role.php`, not public, no archive, title and order, menu position 24, meta `cptdp_highlight` with **one highlight at a time enforced on the meta hooks**, seeder `job-roles`, `ddev wp cptdp seed job-roles`); bump the plugin version to 0.35.0; in the Settings > Contact sentence add `portrait_id` (attachment ID of a PNG or WebP, media picker `assets/admin-contact.js`, read with `cptdp_get_contact( 'portrait_id' )`, an integer).
- Seeders: add `job-roles` and `portrait` to the `--all` order list (after `document-categories`, before `services`; neither has dependencies); note that `portrait` is a settings seeder (no post type; sets `cptdp_contact.portrait_id` from `data/seeders/portrait/portrait.png` only when none is set).
- Admin sidebar: Job Roles is at position 24.
- Theme: add a `blocks/hero/` bullet (dynamic block `devportfolio/hero`, first in `front-page.html`; name from the site title as the `h1`, phone rules at 900px, portrait helpers `devportfolio_hero_portrait_id()`/`_sizes()` and the `wp_head` preload, `devportfolio_hero_roles()`, `devportfolio_hero_name_parts()`); add `tests/test-hero.php` to the theme tests list.
- Expected totals: update plugin and theme test counts after Gate C.

---

## Conventional commits

Branch `feat/DPH-001-003-004-Home-Hero` from `main` (already created; the work is still uncommitted in the working tree). Stage explicit paths only, never `git add -A` (untracked plan files of other tickets are in the working tree). Footer on every commit: `Refs: <tickets>`, then `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.

**Before the first commit, run the gates and record the totals in the PR:** the plugin suite (`ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit`), the theme suite (`ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit`) and the filtered Job Role, Contact and Hero runs. Run them one at a time: both use the `wordpress_test` database. Last recorded (final state, all changes in): plugin 1084 tests, 12 skipped, no failures; theme 770 tests, no failures. Only the final state was gated, not each intermediate commit.

**Not in these commits (blocked, see Risks):**
- `wp-content/plugins/custom-post-types-dp/data/seeders/portrait/portrait.png` is a **test photo** (copied from the Downloads folder, 696 x 543 PNG, approved by the user for testing). It is committed with the portrait seeder. Replace it with Dave's own transparent cut-out before the site goes live, and check its rights first.
- The stories CSV (plan step D2): the DPH-001, DPH-003 and DPH-004 criteria were edited to match the build (commit 9). The original pasted story text was not available, so the rows were not replaced with it.
- `AGENTS.md` (plan step D3): done in commit 8 (Overview, seeders, hero bullet, Contact keys). The test totals are in the Gate line below.

**Changes since the plan was written** (all in the commits below): the Contact page is one "Contact" section (Portrait field first, then Email, WhatsApp and LinkedIn; Booking times last); a "Hero title" setting (default "David John") drives the name and the photo's alt text, and the name falls back to the site title when it is blank; the hero is full height on desktop and tablets (half the viewport on tablets, the desktop layout), with the wireframe's mobile layout on phones (620px and below); the portrait is 46% of the hero column, its top at mid-letter, with no bottom fade; the name and portrait fade and slide in on desktop and tablets, and the bottom-left group (intro and "Scroll down" hint) and the pills fade in after them; the scroll hint's arrow bobs down, up and down, and the hint hides for the rest of the visit once the visitor scrolls (a refresh brings it back). The Job Role type supports `custom-fields` (REST needs it for the highlight) and removes the core meta box.

**Plugin** (`custom-post-types-dp`, version 0.35.0):

1. `feat(custom-post-types-dp): add the Job Role post type and seeder`. Files: `includes/post-types/job-role.php`, `includes/seeders/job-roles.php`, `data/seeders/job-roles.csv`. Refs: DPH-003.
2. `test(custom-post-types-dp): cover the Job Role type and seeder`. Files: `tests/post-types/test-job-role.php`, `tests/seeders/test-job-roles.php`. Refs: DPH-003.
3. `feat(custom-post-types-dp): add portrait and hero title to Contact`. Files: `includes/settings/contact.php`, `assets/admin-contact.js`, `tests/settings/test-contact.php`. Refs: DPH-001, DPH-003. (Squashes the plan's commits 3 and 4: the defaults change turns the Contact tests red without the test edit. It also carries the `hero_title` field, its default and its tests.)
4. `feat(custom-post-types-dp): seed a Home portrait from a bundled file`. Files: `includes/seeders/portrait.php`, `data/seeders/portrait.csv`, `data/seeders/portrait/portrait.png`, `tests/seeders/test-portrait.php`. Refs: DPH-001. Commit only after the Contact commit (it calls `cptdp_get_contact( 'portrait_id' )`). Files also include `data/seeders/portrait/portrait.png` (the test photo).
5. `chore(custom-post-types-dp): bump version to 0.35.0`. Files: `custom-post-types-dp.php`. Refs: DPH-001, DPH-003.

**Theme** (`devportfolio`):

6. `feat(theme): add the Home hero block`. Files: `blocks/hero/block.json`, `blocks/hero/render.php`, `blocks/hero/style.css`, `blocks/hero/view.js`, `functions.php`, `templates/front-page.html`. Refs: DPH-001, DPH-003, DPH-004. (One feature commit: the layout, the motion and the scroll hint live in the same CSS and view files, so splitting them would leave the hero half-built. `view.js` is the scroll-hint script and is referenced from `block.json`.)
7. `test(theme): cover the Home hero`. Files: `tests/test-hero.php`, `tests/test-home-template.php`. Refs: DPH-001, DPH-003, DPH-004. Last run: all green (Devportfolio_Test_Hero 35 tests, theme suite 770).

**Docs:**

8. `docs(agents): document the hero, Job Roles and the portrait setting`. Files: `AGENTS.md` (not yet edited; write it first). Refs: DPH-001, DPH-003, DPH-004. Content: Overview (`job_role` CPT, `cptdp_contact` keys `portrait_id` and `hero_title`, version 0.35.0), seeders (`job-roles`, `portrait` and their order in `--all`), admin sidebar (Job Roles at 24), the `blocks/hero/` theme bullet, and the test totals.
9. `docs(plans): add the DPH-001-003-004 hero plan`. Files: `.claude/plans/home/DPH-001-003-004-Home-Hero.md`, `.claude/plans/home/Home-User-Stories-Revised.csv`. Refs: DPH-001, DPH-003, DPH-004. The stories CSV: the DPH-001 criteria gained the portrait-upload and hero-title lines and the phone and motion lines were updated; the DPH-003 specialities now come from Job Roles and the scroll hint behaviour is described; the DPH-004 pills follow the 620px phone rule. The original pasted story text was not used.

**PR title:** `feat(theme): add the Home hero with Job Roles and a portrait setting` (68 characters). Body from `.github/pull_request_template.md`: tick Feature, list the commits, put the gate totals and the browser checks in How to Test, note the blocked portrait file, and end with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

## Risks to watch
- **The default portrait is not Dave.** It is a stand-in so the hero can be built and reviewed. Before the site goes public, confirm you may publish this photo (its file name suggests it is someone else's picture, possibly under copyright) or replace it in Settings > Contact with Dave's own cut-out. The seeder never overwrites a portrait that is already set.
- **Highlight on trashed or draft roles:** the single-highlight rule clears every status including Trash; a role restored from Trash comes back un-highlighted (acceptable, stated in Open decision 2).
- **Face cropping:** the portrait is `object-fit: contain` inside a box capped at 460px high in a 500px hero; a very tall photo is scaled down, not cropped, but check D1 step 3 with Dave's real photo.
- **Preload mismatch:** if the preload link and the `<img>` ever pick different candidates the browser downloads the image twice. They share `devportfolio_hero_portrait_sizes()` and `'large'`; keep it that way.
- **Settings page order:** the portrait section appears before the Contact fields (Edit 4 note). Cosmetic.
