# Plan: Home Testimonials (quote, rotation) and Software tools (logos)

Tickets **DPH-022** (Testimonial), **DPH-023** (Testimonial rotation), **DPH-024** (Software tools), all Medium, Homepage. Sources: the user stories as pasted, `.claude/plans/home/Home-User-Stories-Revised.csv` and the lo-fi wireframe `.claude/plans/home/home-lofi-wireframe.html` (sections C3, C4, D).
Branch: `feat/DPH-022-023-024-Testimonials-Software-Tools`. Touches the **plugin** (new `testimonial` CPT, minor version bump, logo files) and the **theme** (two blocks, Home template). Follows the shape of `DPH-010-...-Selected-Work.md` (dynamic block, pure `*.js` module with `node --test`, `view.js`, tests in `tests/test-*.php`).

**Implementer rule:** every "Fact" below was checked in the repo on the day of writing. If the code does not match a Fact, stop and report; do not improvise.

## Out of scope
- **FUTURE TICKET (to do): the Testimonials page and the "Read more testimonials →" link** (DPH-022 acceptance item 3). No ticket exists yet, so the link is **not rendered** in this release and the block has no archive link markup. The CPT keeps `has_archive` so the future page has a URL to build on. Create the ticket before the Home release is called complete.
- The Testimonials page design, the hero, Contact section, `DPH-030` as a whole (only the parts these blocks own are done here).
- Fetching logos at runtime, SVG upload support, dark-mode logos, a logo for a tool with no usable source (it gets the render-time monogram, no file).
- Reordering the existing tools in the database (Dave confirms the order; a one-line recipe is in "Populate and verify").

## Facts (checked)
1. No `testimonial` post type, block, template or seeder exists. Header nav already links `Testimonials` to `/#testimonials` (an anchor, never "active"); `tests/test-navigation.php` pins that. Do not change the nav.
2. `templates/front-page.html` ends with the `#selected-work` group, then `</main>`. Sections are core `group` blocks with class `home-section`, padding top/bottom `var:preset|spacing|60`, and a core `heading` level 2 with inline `font-size:clamp(26px, 3.4vw, 34px);font-weight:800`. `tests/test-home-template.php` asserts the order of needles in that file.
3. Blocks are registered in `devportfolio_register_blocks()` (`functions.php`, a list of `register_block_type( __DIR__ . '/blocks/<name>' )`). `block.json` pattern: `"render": "file:./render.php"`, `"style": "file:./style.css"`, `"viewScriptModule": "file:./view.js"`. `devportfolio_published_ids( $post_type, $args )` returns memoised published IDs.
4. Fonts: Hanken Grotesk 200 to 800, **upright only** (no italic file). The `serif` preset is the same family. So "italic serif" in the story is drawn as Hanken Grotesk weight 300 with `font-style: italic` (the browser slants it). Decision made, not blocking; see Open decision 4.
5. `software_tool` supports `thumbnail`, `show_in_rest`, has meta `cptdp_site_url`, seeder `software-tools` with **31** rows (CSV columns `order,title,site_url,description`, orders 10 to 310). The story says 30 tiles; see Open decision 1.
6. `DPB-033-Software-Tool-Icon-Store-...md` (untracked, not yet implemented) already decides: the icon is the tool's **featured image**, bundled PNGs live in `wp-content/plugins/custom-post-types-dp/data/seeders/icons/<slug>.png` (`<slug>` = `sanitize_title( title )`, e.g. `adobe-cc`, `otter-ai`) with a `README.md` (file, source URL, licence, date), the seeder gets an `icon` CSV column and sideloads them, and a `cptdp-tool-icon` image size exists. **DPB-033 is a prerequisite of Phase C and D here.** Its "monogram PNG for tools with no logo" line and its 96x96 size are amended by this plan (Phase C, step 1).
7. No ImageMagick/pngquant on the host. The DDEV container has PHP Imagick (the plugin's page converter uses it) and Node 24.
8. Plugin version on `main` is 0.32.0 (header and constant).

## Decisions made (do not block)
- **Testimonial data model:** CPT `testimonial` (`/testimonials/`), title = client name, content = the quote (plain text), meta `cptdp_company`, order = menu order. A **seeder `testimonials`** (Phase A4) creates **3 sample testimonials** from a CSV so the section can be seen and tested. They are invented, with obviously fictional names and companies, and are **placeholders to delete or replace with Dave's real quotes before launch** (they would otherwise be fake social proof on a public site). The seeder is never run on activation, like the others.
- **One block per section.** `devportfolio/testimonials` renders its own `<section id="testimonials">` and heading, so with no published testimonial the whole section (heading too) disappears and the `#testimonials` anchor simply has nothing to scroll to. `devportfolio/software-tools` renders only the grid; the heading stays a core heading in the template, like the other sections.
- **Testimonials with 1 or 2 quotes** still render (dots only when there are 2 or more; rotation only when 2 or more). The story's "at least three" is Dave's content obligation, not a render rule.
- **Tools: every published tool is shown**, no cap, in `menu_order` then title. No filter, category, link or hint text.
- **Tool tile image:** the tool's featured image, drawn `alt=""` (the visible name is the button's label, so the image would be read twice). No featured image: a render-time monogram tile, the first two letters of the name, first capital and second lowercase (`Sl`), bold, in the same rounded square with a thin outline (the repo CSV's item 2; the pasted story dropped it, it costs one small helper and covers every tool Dave adds later). This replaces DPB-033's monogram PNGs: **no placeholder PNG is bundled.**
- **Tool buttons use real `<button type="button">`** with `aria-pressed`, so Enter and Space work natively and no key handler is written.
- **Colour is CSS only** (`filter: grayscale(1)` on the icon box, removed on hover or `.is-on`); the logo files are stored in full colour.

## Open decisions (default applied; none blocks the work)
1. **30 vs 31 tools.** The story says 30, the seeder has 31. Default: show all published. Dave either confirms 31 or sets one tool to Draft.
2. **Most-used-first order** (Google, Slack, Zoom, Notion, Canva, ChatGPT, then the rest). Dave confirms the full order; until then the order is the seeded one (alphabetical). Recipe in the last section.
3. **"Read more testimonials" link: decided, deferred.** Marked as a future ticket (see Out of scope). Not rendered now.
4. **Italic face.** Default: synthesised slant of Hanken Grotesk 300. Alternative: bundle an italic woff2 (new asset, changes `theme.json`). Not done here.

---

## Phase A. Plugin: the `testimonial` post type

### A1. `wp-content/plugins/custom-post-types-dp/includes/post-types/testimonial.php` (new, in full)
The loader globs this folder, so nothing else registers it. Mirrors `software-tool.php` (meta box, nonce, columns, default order, classic editor).

```php
<?php
/**
 * Testimonial custom post type (DPH-022).
 *
 * Title = client name, content = the quote (plain text), meta `cptdp_company` = the client's company.
 * Callers must escape on output.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Testimonial post type and its meta.
 */
function cptdp_register_testimonial(): void {
	$labels = array(
		'name'               => esc_html_x( 'Testimonials', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'      => esc_html_x( 'Testimonial', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'          => esc_html_x( 'Testimonials', 'admin menu', 'custom-post-types-dp' ),
		'add_new_item'       => esc_html__( 'Add New Testimonial', 'custom-post-types-dp' ),
		'new_item'           => esc_html__( 'New Testimonial', 'custom-post-types-dp' ),
		'edit_item'          => esc_html__( 'Edit Testimonial', 'custom-post-types-dp' ),
		'view_item'          => esc_html__( 'View Testimonial', 'custom-post-types-dp' ),
		'all_items'          => esc_html__( 'All Testimonials', 'custom-post-types-dp' ),
		'search_items'        => esc_html__( 'Search Testimonials', 'custom-post-types-dp' ),
		'not_found'          => esc_html__( 'No testimonials found.', 'custom-post-types-dp' ),
		'not_found_in_trash' => esc_html__( 'No testimonials found in Trash.', 'custom-post-types-dp' ),
		'archives'           => esc_html__( 'Testimonial Archives', 'custom-post-types-dp' ),
	);

	register_post_type(
		'testimonial',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 29,
			'menu_icon'     => 'dashicons-format-quote',
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'testimonials',
				'with_front' => false,
			),
		)
	);

	register_post_meta(
		'testimonial',
		'cptdp_company',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_testimonial' );

/**
 * Force the classic editor for Testimonials. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_testimonial_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'testimonial' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_testimonial_classic_editor', 10, 2 );

/**
 * Add the Company meta box.
 */
function cptdp_testimonial_add_meta_box(): void {
	add_meta_box(
		'cptdp_testimonial_company',
		esc_html__( 'Company', 'custom-post-types-dp' ),
		'cptdp_testimonial_render_meta_box',
		'testimonial',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_testimonial', 'cptdp_testimonial_add_meta_box' );

/**
 * Render the Company meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_testimonial_render_meta_box( WP_Post $post ): void {
	$value = (string) get_post_meta( $post->ID, 'cptdp_company', true );

	wp_nonce_field( 'cptdp_company', 'cptdp_company_nonce' );
	?>
	<p>
		<label class="screen-reader-text" for="cptdp_company"><?php esc_html_e( 'Company', 'custom-post-types-dp' ); ?></label>
		<input type="text" id="cptdp_company" name="cptdp_company" class="widefat" value="<?php echo esc_attr( $value ); ?>">
	</p>
	<p class="description"><?php esc_html_e( 'The title is the client name and the editor holds the quote (plain text).', 'custom-post-types-dp' ); ?></p>
	<?php
}

/**
 * Save the Company meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_testimonial_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_company_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_company_nonce'] ) ), 'cptdp_company' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$company = isset( $_POST['cptdp_company'] ) ? sanitize_text_field( wp_unslash( $_POST['cptdp_company'] ) ) : '';

	if ( '' === $company ) {
		delete_post_meta( $post_id, 'cptdp_company' );
	} else {
		update_post_meta( $post_id, 'cptdp_company', $company );
	}
}
add_action( 'save_post_testimonial', 'cptdp_testimonial_save_meta' );

/**
 * Add Company and Order columns after Title.
 *
 * @param array $columns Existing columns.
 */
function cptdp_testimonial_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['cptdp_company'] = esc_html__( 'Company', 'custom-post-types-dp' );
			$new['menu_order']    = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_testimonial_posts_columns', 'cptdp_testimonial_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_testimonial_column_content( string $column, int $post_id ): void {
	if ( 'cptdp_company' === $column ) {
		$company = (string) get_post_meta( $post_id, 'cptdp_company', true );
		echo '' === $company ? '&mdash;' : esc_html( $company );
	} elseif ( 'menu_order' === $column ) {
		echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
	}
}
add_action( 'manage_testimonial_posts_custom_column', 'cptdp_testimonial_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_testimonial_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-testimonial_sortable_columns', 'cptdp_testimonial_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_testimonial_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'testimonial' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_testimonial_default_order' );
```

### A2. `wp-content/plugins/custom-post-types-dp/tests/post-types/test-testimonial.php` (new)
Extends `CPTDP_CPT_Test_Case` (it already tests registration, supports, classic editor, columns after title, sortable Order, default order, explicit orderby). Add:

```php
<?php
/**
 * Testimonial post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group testimonial
 */
class CPTDP_Test_Testimonial extends CPTDP_CPT_Test_Case {

	protected function post_type(): string {
		return 'testimonial';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'testimonials',
			'menu_position' => 29,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'taxonomies'    => array(),
			'columns'       => array( 'cptdp_company', 'menu_order' ),
			'default_order' => true,
		);
	}

	private function make(): int {
		return self::factory()->post->create( array( 'post_type' => 'testimonial' ) );
	}

	public function test_company_meta_is_registered(): void {
		$registered = get_registered_meta_keys( 'post', 'testimonial' );

		$this->assertArrayHasKey( 'cptdp_company', $registered );
		$this->assertSame( 'string', $registered['cptdp_company']['type'] );
		$this->assertSame( '', $registered['cptdp_company']['default'] );
		$this->assertTrue( $registered['cptdp_company']['single'] );
		$this->assertTrue( $registered['cptdp_company']['show_in_rest'] );
	}

	public function test_company_is_saved_trimmed_and_stripped_of_markup(): void {
		$id = $this->make();
		$this->submit(
			'cptdp_testimonial_save_meta',
			$id,
			array(
				'cptdp_company'       => '  <b>Acme</b> Pty  ',
				'cptdp_company_nonce' => wp_create_nonce( 'cptdp_company' ),
			)
		);

		$this->assertSame( 'Acme Pty', get_post_meta( $id, 'cptdp_company', true ) );
	}

	public function test_company_is_removed_when_emptied(): void {
		$id = $this->make();
		update_post_meta( $id, 'cptdp_company', 'Acme' );
		$this->submit(
			'cptdp_testimonial_save_meta',
			$id,
			array(
				'cptdp_company'       => '',
				'cptdp_company_nonce' => wp_create_nonce( 'cptdp_company' ),
			)
		);

		$this->assertSame( '', get_post_meta( $id, 'cptdp_company', true ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_company' ) );
	}

	public function test_company_is_not_saved_without_a_nonce(): void {
		$id = $this->make();
		$this->submit( 'cptdp_testimonial_save_meta', $id, array( 'cptdp_company' => 'Acme' ) );

		$this->assertSame( '', get_post_meta( $id, 'cptdp_company', true ) );
	}

	public function test_company_column_shows_the_company_or_a_dash(): void {
		$id = $this->make();
		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_company', $id ) );

		update_post_meta( $id, 'cptdp_company', 'Acme & Co' );
		$this->assertSame( 'Acme &amp; Co', $this->render_column( 'cptdp_company', $id ) );
	}
}
```
Before writing, open `tests/post-types/test-software-tool.php` and confirm `submit()` and `render_column()` are the base-class helpers with these signatures (they are used there the same way); if not, adapt to what that file does.

### A4. Sample testimonials seeder (new)
Convention (AGENTS.md *Adding a seeder*): CSV, seeder file, test. Idempotent by title, validated before any write, never run on activation. Nothing depends on other seeders; its file name sorts between `statistics.php` and `video-categories.php`, so `--all` order stays valid.

**`data/seeders/testimonials.csv`** (columns `order,title,company,quote`; title = client name; all invented, marked sample):
```csv
order,title,company,quote
10,Sample Client A,Sample Co Alpha,"Sample testimonial. Dave turned our scattered social posts into a calm weekly rhythm, and our enquiries doubled within two months."
20,Sample Client B,Sample Co Beta,"Sample testimonial. Fast replies, tidy handovers and a calendar I finally trust. It feels like having an in-house team of one."
30,Sample Client C,Sample Co Gamma,"Sample testimonial. He rebuilt our newsletter and our admin workflow in a fortnight, and nothing has fallen through the cracks since."
```
Each quote starts with "Sample testimonial." on purpose, so nobody mistakes it for a real one.

**`includes/seeders/testimonials.php`**: copy `includes/seeders/software-tools.php` and change only these things: the function names to `cptdp_register_seeder_testimonials` / `cptdp_seed_testimonials`; the registry key `testimonials`; label `Testimonials (samples)`; csv `data/seeders/testimonials.csv`; `post_type` `testimonial`; columns `array( 'order', 'title', 'company', 'quote' )` (both in the registration and in the `cptdp_seed_read_csv()` call); the guard `post_type_exists( 'testimonial' )` and its error text; `cptdp_seed_title_exists( 'testimonial', ... )`; and the insert: `'post_type' => 'testimonial'`, `'post_content' => $row['quote']`, `'meta_input' => array( 'cptdp_company' => $row['company'] )`. Keep the order validation (`ctype_digit`) and the `created`/`skipped` result. Header comment must say the rows are invented placeholders to delete before launch.

**`tests/seeders/test-testimonials.php`**: copy `tests/seeders/test-software-tools.php` (class `CPTDP_Test_Seeder_Testimonials`, `@group testimonial`, `@group seeder`, `seeder_name()` returns `testimonials`) and replace its cases with:
1. `test_csv_has_three_sample_rows_marked_as_samples`: 3 rows; every `quote` starts with `Sample testimonial.`; every `company` is non-empty.
2. `test_columns_map_onto_the_created_posts`: after `run_seeder()`, each row is one `testimonial` post, `publish`, `menu_order` = order, `post_content` = quote, meta `cptdp_company` = company.
3. `test_listing_follows_csv_order`.
4. `test_existing_testimonial_keeps_its_company`: a post with the first title and meta `Real Company` is skipped and keeps `Real Company`.
5. `test_row_with_non_numeric_order_errors_before_writing`: temp CSV `"order,title,company,quote\n10,A,Co,Quote\nabc,B,Co,Quote\n"` gives a `cptdp_seed_csv_row` WP_Error and 0 items.
The generic dry-run, idempotence and missing-column cases come from `CPTDP_Seeder_Test_Case`.

Also in `AGENTS.md` (commit 12): add `testimonials` to the `--all` order list after `statistics`, and say it creates 3 sample rows to delete before launch.

### A3. Gate
`ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Testimonial` then the full plugin suite. Then `ddev wp rewrite flush` (a new archive slug).

---

## Phase B. Theme: the Testimonials block (DPH-022, DPH-023)

Folder `wp-content/themes/devportfolio/blocks/testimonials/`.

### B1. `block.json`
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/testimonials",
	"title": "Testimonials",
	"category": "widgets",
	"description": "The Home 'What clients say' section: one quote at a time, dots to switch, automatic rotation.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css",
	"viewScriptModule": "file:./view.js"
}
```

### B2. `render.php`
```php
<?php
/**
 * Server render of the Home testimonials (DPH-022).
 *
 * Plain text only. Every quote is in the markup (the first is active); view.js adds the dots and the rotation.
 * Nothing is rendered without a published testimonial, heading included.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'testimonial',
	array(
		'orderby' => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

$devportfolio_quotes = array();
foreach ( $devportfolio_ids as $devportfolio_id ) {
	$devportfolio_text = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $devportfolio_id ) ) );
	if ( '' === $devportfolio_text ) {
		continue;
	}

	$devportfolio_quotes[] = array(
		'quote'   => $devportfolio_text,
		'name'    => html_entity_decode( get_the_title( $devportfolio_id ), ENT_QUOTES, 'UTF-8' ),
		'company' => trim( (string) get_post_meta( $devportfolio_id, 'cptdp_company', true ) ),
	);
}

if ( empty( $devportfolio_quotes ) ) {
	return;
}

$devportfolio_count   = count( $devportfolio_quotes );
$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'testimonials home-section',
		'id'    => 'testimonials',
	)
);
?>
<section <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> aria-labelledby="testimonials-heading">
	<h2 id="testimonials-heading" class="testimonials__heading"><?php echo esc_html_x( 'What clients say', 'Home testimonials heading', 'devportfolio' ); ?></h2>

	<div class="testimonials__stage" data-testimonials-stage aria-live="off">
		<?php foreach ( $devportfolio_quotes as $devportfolio_i => $devportfolio_q ) : ?>
			<figure class="testimonials__slide<?php echo 0 === $devportfolio_i ? ' is-active' : ''; ?>" data-testimonials-slide>
				<blockquote class="testimonials__quote"><p><?php echo esc_html( $devportfolio_q['quote'] ); ?></p></blockquote>
				<figcaption class="testimonials__who">
					<span class="testimonials__name"><?php echo esc_html( $devportfolio_q['name'] ); ?></span>
					<?php if ( '' !== $devportfolio_q['company'] ) : ?>
						<span class="testimonials__company"><?php echo esc_html( $devportfolio_q['company'] ); ?></span>
					<?php endif; ?>
				</figcaption>
			</figure>
		<?php endforeach; ?>
	</div>

	<?php if ( $devportfolio_count > 1 ) : ?>
		<div class="testimonials__dots" role="group" aria-label="<?php esc_attr_e( 'Choose a testimonial', 'devportfolio' ); ?>" data-testimonials-dots hidden>
			<?php for ( $devportfolio_i = 0; $devportfolio_i < $devportfolio_count; $devportfolio_i++ ) : ?>
				<button type="button" class="testimonials__dot"<?php echo 0 === $devportfolio_i ? ' aria-current="true"' : ''; ?> aria-label="<?php echo esc_attr( sprintf( /* translators: 1: position, 2: total */ __( 'Show testimonial %1$d of %2$d', 'devportfolio' ), $devportfolio_i + 1, $devportfolio_count ) ); ?>"></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</section>
```
The dots container is `hidden` in the markup and `view.js` shows it, so without script there are no dead buttons.

### B3. `rotation.js` (pure, no DOM)
```js
/**
 * Pure rules for the Home testimonials (DPH-022, DPH-023). No DOM here, so node --test can run it.
 */

export const PERIOD = 6000;
export const SWIPE_MIN = 48;

/**
 * Next index in a ring.
 *
 * @param {number} index     Current index.
 * @param {number} count     How many slides.
 * @param {number} direction 1 for next, -1 for previous.
 * @return {number}
 */
export function stepIndex( index, count, direction ) {
	if ( count < 1 ) {
		return 0;
	}
	return ( ( ( index + direction ) % count ) + count ) % count;
}

/**
 * Which way a finger stroke turns the quote: -1 previous, 0 nothing, 1 next. Mostly vertical or short strokes do nothing.
 *
 * @param {number} dx Horizontal travel in px (negative = left).
 * @param {number} dy Vertical travel in px.
 * @return {number}
 */
export function swipeDirection( dx, dy ) {
	if ( Math.abs( dx ) < SWIPE_MIN || Math.abs( dx ) < Math.abs( dy ) * 1.5 ) {
		return 0;
	}
	return dx < 0 ? 1 : -1;
}

/**
 * Whether the automatic rotation may run now.
 *
 * @param {{count:number,reducedMotion:boolean,hover:boolean,focus:boolean,hidden:boolean}} s State.
 * @return {boolean}
 */
export function shouldRun( s ) {
	return s.count > 1 && ! s.reducedMotion && ! s.hover && ! s.focus && ! s.hidden;
}
```

### B4. `tests/rotation.test.mjs`
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PERIOD, SWIPE_MIN, stepIndex, swipeDirection, shouldRun } from '../rotation.js';

test( 'the period is six seconds', () => {
	assert.equal( PERIOD, 6000 );
} );

test( 'stepIndex wraps both ways', () => {
	assert.equal( stepIndex( 0, 3, 1 ), 1 );
	assert.equal( stepIndex( 2, 3, 1 ), 0 );
	assert.equal( stepIndex( 0, 3, -1 ), 2 );
	assert.equal( stepIndex( 0, 0, 1 ), 0 );
} );

test( 'a left swipe goes next, a right swipe goes back', () => {
	assert.equal( swipeDirection( -SWIPE_MIN, 0 ), 1 );
	assert.equal( swipeDirection( SWIPE_MIN, 0 ), -1 );
} );

test( 'short or mostly vertical strokes do nothing', () => {
	assert.equal( swipeDirection( -( SWIPE_MIN - 1 ), 0 ), 0 );
	assert.equal( swipeDirection( -100, 80 ), 0 );
	assert.equal( swipeDirection( -100, 60 ), 1 );
} );

const base = { count: 3, reducedMotion: false, hover: false, focus: false, hidden: false };

test( 'rotation runs only when nothing stops it', () => {
	assert.equal( shouldRun( base ), true );
	assert.equal( shouldRun( { ...base, hover: true } ), false );
	assert.equal( shouldRun( { ...base, focus: true } ), false );
	assert.equal( shouldRun( { ...base, hidden: true } ), false );
	assert.equal( shouldRun( { ...base, reducedMotion: true } ), false );
	assert.equal( shouldRun( { ...base, count: 1 } ), false );
} );
```
Run: `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/testimonials 'node --test tests/*.test.mjs'`.

### B5. `view.js`
```js
/**
 * Home testimonials (DPH-022, DPH-023): the dots, the swipe and the six-second rotation.
 *
 * The rotation stops while the mouse is over the section (touch is ignored: a tap leaves a "hover" behind), while a
 * control inside it has keyboard focus (:focus-visible), and while the tab is hidden. With reduced motion it never
 * starts: quotes change only through the dots or a swipe. The stage is aria-live="off" while it rotates by itself and
 * "polite" once the visitor has changed a quote.
 */
import { PERIOD, stepIndex, swipeDirection, shouldRun } from './rotation.js';

const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

function start( root ) {
	const stage = root.querySelector( '[data-testimonials-stage]' );
	const slides = [ ...root.querySelectorAll( '[data-testimonials-slide]' ) ];
	const dotsBox = root.querySelector( '[data-testimonials-dots]' );
	if ( ! stage || slides.length < 2 || ! dotsBox ) {
		return;
	}

	const dots = [ ...dotsBox.querySelectorAll( 'button' ) ];
	const state = { index: 0, hover: false, focus: false };
	let timer = 0;

	dotsBox.hidden = false;

	function show( index ) {
		state.index = index;
		slides.forEach( ( slide, i ) => slide.classList.toggle( 'is-active', i === index ) );
		dots.forEach( ( dot, i ) => {
			if ( i === index ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
	}

	function schedule() {
		clearTimeout( timer );
		if ( ! shouldRun( { count: slides.length, reducedMotion: reducedMotion.matches, hover: state.hover, focus: state.focus, hidden: document.hidden } ) ) {
			return;
		}
		timer = setTimeout( () => {
			show( stepIndex( state.index, slides.length, 1 ) );
			schedule();
		}, PERIOD );
	}

	function choose( index ) {
		stage.setAttribute( 'aria-live', 'polite' );
		show( index );
		schedule();
	}

	dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => choose( i ) ) );

	let from = null;
	stage.addEventListener( 'pointerdown', ( event ) => {
		from = event.pointerType === 'touch' ? { x: event.clientX, y: event.clientY } : null;
	} );
	stage.addEventListener( 'pointerup', ( event ) => {
		if ( ! from ) {
			return;
		}
		const direction = swipeDirection( event.clientX - from.x, event.clientY - from.y );
		from = null;
		if ( direction !== 0 ) {
			choose( stepIndex( state.index, slides.length, direction ) );
		}
	} );
	stage.addEventListener( 'pointercancel', () => {
		from = null;
	} );

	root.addEventListener( 'pointerenter', ( event ) => {
		state.hover = event.pointerType !== 'touch';
		schedule();
	} );
	root.addEventListener( 'pointerleave', () => {
		state.hover = false;
		schedule();
	} );
	root.addEventListener( 'focusin', ( event ) => {
		state.focus = event.target.matches( ':focus-visible' );
		schedule();
	} );
	root.addEventListener( 'focusout', () => {
		state.focus = false;
		schedule();
	} );
	document.addEventListener( 'visibilitychange', schedule );
	reducedMotion.addEventListener( 'change', schedule );

	schedule();
}

document.querySelectorAll( '.testimonials' ).forEach( start );
```

### B6. `style.css`
```css
/*
 * Home testimonials (DPH-022, DPH-023). Greyscale tokens only. Slides share one grid cell, so the section is as high
 * as the longest quote and nothing jumps when a quote changes.
 */

.testimonials {
	text-align: center;
	padding-top: var(--wp--preset--spacing--60);
	padding-bottom: var(--wp--preset--spacing--60);
}

.testimonials__heading {
	margin: 0 0 28px;
	font-size: clamp(26px, 3.4vw, 34px);
	font-weight: 800;
}

.testimonials__stage {
	display: grid;
	max-width: 760px;
	margin: 0 auto;
	touch-action: pan-y;
}

.testimonials__slide {
	grid-area: 1 / 1;
	margin: 0;
	opacity: 0;
	visibility: hidden;
	transition: opacity 0.3s, visibility 0s 0.3s;
}

.testimonials__slide.is-active {
	opacity: 1;
	visibility: visible;
	transition: opacity 0.3s;
}

.testimonials__quote {
	margin: 0;
	padding: 0;
	border: 0;
}

.testimonials__quote p {
	margin: 0;
	font-size: clamp(20px, 2.6vw, 28px);
	font-weight: 300;
	font-style: italic;
	line-height: 1.4;
	letter-spacing: -0.01em;
}

.testimonials__quote p::before {
	content: '\201C';
}

.testimonials__quote p::after {
	content: '\201D';
}

.testimonials__who {
	margin-top: 18px;
	font-size: 14px;
}

.testimonials__name {
	font-weight: 700;
}

.testimonials__company {
	color: var(--wp--preset--color--mid);
}

.testimonials__company::before {
	content: ' \00B7  ';
}

/* The dot is small; the button around it is the 44px tap area (DPH-030). */
.testimonials__dots {
	display: flex;
	justify-content: center;
	margin-top: 6px;
}

.testimonials__dots[hidden] {
	display: none;
}

.testimonials__dot {
	position: relative;
	width: 44px;
	height: 44px;
	padding: 0;
	border: 0;
	background: none;
	cursor: pointer;
}

.testimonials__dot::before {
	content: '';
	position: absolute;
	top: 50%;
	left: 50%;
	width: 8px;
	height: 8px;
	margin: -4px 0 0 -4px;
	border-radius: 50%;
	background: var(--wp--preset--color--line);
	transition: background-color 0.2s, transform 0.2s;
}

.testimonials__dot[aria-current='true']::before {
	background: var(--wp--preset--color--contrast);
	transform: scale(1.25);
}

.testimonials__dot:focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: -6px;
	border-radius: 50%;
}

@media (prefers-reduced-motion: reduce) {
	.testimonials__slide,
	.testimonials__slide.is-active,
	.testimonials__dot::before {
		transition: none;
	}
}
```
Note: the wrapper carries `home-section` only for its padding class parity; the padding is set above, so if `.home-section` has its own rules (grep `home-section` in `assets/css/page.css`/`theme.json`; the spec check found only `.home-section__rule`), keep whichever is not duplicated. The `<hr>` separators other sections use are not added here.

---

## Phase C. Software tool logos (the "special section")

Goal: an official, **transparent-background** logo for each tool, **downsized so all tiles load fast**, kept in the plugin as one file per tool, then loaded into the database as each tool's featured image by DPB-033's seeder.

### C0. Prerequisite
Implement `DPB-033` first (its steps 1 and 3: image size and helper, CSV `icon` column and seeder). This phase supplies its step 2, the files. If DPB-033 is not implemented when you reach Phase C, stop and report.

### C1. Amend DPB-033 (edit that plan file, two lines)
- Image size `cptdp-tool-icon`: **128x128** (was 96x96), so a 58px tile is sharp at 2x (116px) and a 44px phone tile at 3x (132px, close enough, downscaled by the browser).
- Tools with no usable logo get **no file** (was a monogram PNG); the theme draws the monogram at render time (DPH-024). The seeder already treats an empty `icon` cell as "no icon". Remove the "placeholder" flag from its README wording.

### C2. Finding the logos (internet research; use WebSearch and WebFetch)
Work for each of the 31 tools in this order, stop at the first source that passes C3:
1. **The vendor's own brand or press kit page** (search "<tool> brand assets", "<tool> press kit", "<tool> logo guidelines"). Prefer SVG, else a PNG at least 256px on the long side.
2. **Simple Icons** (`https://github.com/simple-icons/simple-icons`, artwork CC0, brand guidelines still apply). Fetch the SVG pinned to an exact package version, `https://cdn.jsdelivr.net/npm/simple-icons@<exact version>/icons/<slug>.svg`, and the brand colour from that version's `data/simple-icons.json` (`hex`). The SVG is single-colour black, so add `fill="#<hex>"` to the root `<svg>` before use. Some brands are absent from the set; then go to the next source.
3. Nothing usable: **no file**; record `none (monogram)` in the README.
Rules: use the **mark or the horizontal-free square mark**, not a wordmark (it must read at 44px); never recolour, distort or redraw beyond the vendor's guidelines (a single brand-colour fill of a Simple Icons mark is the allowed exception); do not use a logo found only on a third-party aggregator. Record every file's source URL, licence/terms and date in `data/seeders/icons/README.md`.

Likely hard cases (check, do not assume): Cisco Duo, Meta Business Suite, Metricool, Otter.ai, Adobe PDF, Adobe CC, Salesforce, Zendesk, OneDrive, Google Workspace.

### C3. Quality gate for a raw file (reject and re-source if any fails)
- **Transparent background:** the four corner pixels of the result have alpha 0 (the script checks it). A JPEG, or a PNG with a solid or white square behind the mark, is rejected; do not try to cut the background out.
- Raster sources: long side at least 112px (a smaller file would be upscaled and blurry).
- Not a wordmark; one mark per file; no drop shadow or glow.

### C4. Processing script `wp-content/plugins/custom-post-types-dp/bin/build-tool-icons.php` (new, in full)
Run inside DDEV: `ddev exec php wp-content/plugins/custom-post-types-dp/bin/build-tool-icons.php <raw-dir> <out-dir>`. It is a one-off developer tool, not loaded by WordPress. Each raw file must be named `<slug>.svg|png|webp` (the tool's slug).

```php
<?php
/**
 * Build the bundled software tool icons: trim, fit and save 128x128 transparent PNGs.
 *
 * Usage: php bin/build-tool-icons.php <raw-dir> <out-dir>
 * Not loaded by WordPress.
 *
 * @package CustomPostTypesDP
 */

if ( 'cli' !== PHP_SAPI || ! class_exists( 'Imagick' ) ) {
	fwrite( STDERR, "Run in the DDEV container (PHP CLI with Imagick).\n" );
	exit( 1 );
}

const SIZE    = 128; // Canvas, px. A 58px tile at 2x is 116px.
const FIT     = 112; // The mark fits inside this, leaving a 8px margin.
const MAX_KB  = 10;  // A single icon must stay under this.

$raw = rtrim( $argv[1] ?? '', '/' );
$out = rtrim( $argv[2] ?? '', '/' );
if ( '' === $raw || '' === $out || ! is_dir( $raw ) ) {
	fwrite( STDERR, "Usage: php build-tool-icons.php <raw-dir> <out-dir>\n" );
	exit( 1 );
}
if ( ! is_dir( $out ) ) {
	mkdir( $out, 0755, true );
}

$failed = 0;
$total  = 0;

foreach ( glob( $raw . '/*.{svg,png,webp}', GLOB_BRACE ) as $file ) {
	$slug = pathinfo( $file, PATHINFO_FILENAME );
	try {
		$im = new Imagick();
		$im->setBackgroundColor( new ImagickPixel( 'transparent' ) );
		if ( 'svg' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			$im->setResolution( 384, 384 ); // Rasterise large, then reduce: crisp edges.
		}
		$im->readImage( $file );
		$im->setImageFormat( 'png32' );
		$im->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );

		if ( max( $im->getImageWidth(), $im->getImageHeight() ) < FIT && 'svg' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			throw new RuntimeException( 'source smaller than ' . FIT . 'px' );
		}

		$im->trimImage( 0 ); // Drops the transparent border.
		$im->setImagePage( 0, 0, 0, 0 );
		$im->thumbnailImage( FIT, FIT, true ); // Best fit, keeps the ratio, never crops.

		$canvas = new Imagick();
		$canvas->newImage( SIZE, SIZE, new ImagickPixel( 'transparent' ), 'png' );
		$canvas->compositeImage(
			$im,
			Imagick::COMPOSITE_OVER,
			intdiv( SIZE - $im->getImageWidth(), 2 ),
			intdiv( SIZE - $im->getImageHeight(), 2 )
		);

		// The transparent-background gate: all four corners fully transparent.
		foreach ( array( array( 0, 0 ), array( SIZE - 1, 0 ), array( 0, SIZE - 1 ), array( SIZE - 1, SIZE - 1 ) ) as $xy ) {
			if ( $canvas->getImagePixelColor( $xy[0], $xy[1] )->getColorValue( Imagick::COLOR_ALPHA ) > 0.0 ) {
				throw new RuntimeException( 'has a background (corner is not transparent)' );
			}
		}

		$canvas->setImageFormat( 'png32' );
		$canvas->setImageCompressionQuality( 95 ); // PNG: zlib level 9, adaptive filtering.
		$canvas->stripImage();
		$target = $out . '/' . $slug . '.png';
		$canvas->writeImage( $target );

		$kb = filesize( $target ) / 1024;
		if ( $kb > MAX_KB ) {
			// Fewer colours: logos are flat, so this is visually lossless.
			$canvas->quantizeImage( 64, Imagick::COLORSPACE_SRGB, 0, false, false );
			$canvas->setImageFormat( 'png32' );
			$canvas->writeImage( $target );
			$kb = filesize( $target ) / 1024;
		}
		if ( $kb > MAX_KB ) {
			throw new RuntimeException( sprintf( '%.1f KB is over the %d KB limit', $kb, MAX_KB ) );
		}

		$total += filesize( $target );
		printf( "ok    %-24s %5.1f KB\n", $slug, $kb );
	} catch ( Throwable $e ) {
		++$failed;
		if ( isset( $target ) && file_exists( $target ) ) {
			unlink( $target );
		}
		fprintf( STDERR, "FAIL  %-24s %s\n", $slug, $e->getMessage() );
	}
}

printf( "\n%d failed, total %.0f KB\n", $failed, $total / 1024 );
exit( $failed ? 1 : 0 );
```
If `readImage` of an SVG fails or draws blank, the container's ImageMagick has no SVG delegate: add `librsvg2-bin` to `webimage_extra_packages` in `.ddev/config.yaml`, `ddev restart`, retry (same way LibreOffice was added). Do not rasterise elsewhere.

### C5. Steps
1. Create a scratch folder per run, outside the repo and separate from the script: `<scratchpad>/tool-logos/raw` (downloads are untrusted data: never run anything from that folder, open files only through the script and the image viewer). Save each chosen source as `<slug>.<ext>`.
2. Run the script into `<scratchpad>/tool-logos/out`. Fix every FAIL by re-sourcing (C2), not by loosening the script.
3. **Look at the result.** Build one contact sheet on mid-grey and open it with the Read tool: `ddev exec php -r '$m=new Imagick(glob("<out>/*.png"));...'` is not needed; simpler: view each PNG with Read, or montage with `Imagick::montageImage` on a `#8a8a8a` background. Check: transparent edge (no white box), centred, recognisable at small size, not a wordmark.
4. Copy the passing files to `wp-content/plugins/custom-post-types-dp/data/seeders/icons/` and write `README.md` there (table: file, tool, source URL, licence or terms, date, note `none (monogram)` for tools without a file). Budget: each file at most 10 KB (typically 2 to 6 KB), the folder under about 250 KB in total.
5. Fill the `icon` column of `data/seeders/software-tools.csv` with the file name per row (empty for no file). That column is DPB-033's; this step only fills it.

---

## Phase D. Theme: the Software tools block (DPH-024)

Folder `wp-content/themes/devportfolio/blocks/software-tools/`.

### D1. `functions.php`: one helper, next to the other `devportfolio_` helpers
```php
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
```

### D2. `block.json`
Same as B1 with `"name": "devportfolio/software-tools"`, `"title": "Software Tools"`, `"description": "The grid of software tools from the Software Tool post type. Greyscale until hovered or tapped."`.

### D3. `render.php`
```php
<?php
/**
 * Server render of the Home software tools grid (DPH-024).
 *
 * One button per published Software Tool (menu order, then title). The tile image is the tool's featured image, drawn
 * decorative (the visible name labels the button); a tool without one gets its two-letter monogram. Not links.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_ids = devportfolio_published_ids(
	'software_tool',
	array(
		'orderby' => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

if ( empty( $devportfolio_ids ) ) {
	return;
}

$devportfolio_wrapper = get_block_wrapper_attributes( array( 'class' => 'software-tools' ) );
?>
<ul <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> data-software-tools>
	<?php
	foreach ( $devportfolio_ids as $devportfolio_id ) :
		$devportfolio_name  = html_entity_decode( get_the_title( $devportfolio_id ), ENT_QUOTES, 'UTF-8' );
		$devportfolio_thumb = (int) get_post_thumbnail_id( $devportfolio_id );
		$devportfolio_img   = $devportfolio_thumb
			? wp_get_attachment_image(
				$devportfolio_thumb,
				'cptdp-tool-icon',
				false,
				array(
					'alt'      => '',
					'class'    => 'software-tools__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			)
			: '';
		?>
		<li class="software-tools__item">
			<button type="button" class="software-tools__tile" aria-pressed="false">
				<?php if ( '' !== $devportfolio_img ) : ?>
					<span class="software-tools__icon"><?php echo $devportfolio_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by core. ?></span>
				<?php else : ?>
					<span class="software-tools__icon software-tools__icon--text" aria-hidden="true"><?php echo esc_html( devportfolio_tool_monogram( $devportfolio_name ) ); ?></span>
				<?php endif; ?>
				<span class="software-tools__name"><?php echo esc_html( $devportfolio_name ); ?></span>
			</button>
		</li>
	<?php endforeach; ?>
</ul>
```
`wp_get_attachment_image` already sets width, height and `srcset` for the registered size, so there is no layout shift.

### D4. `view.js`
```js
/**
 * Home software tools (DPH-024): one tool is in colour at a time.
 *
 * A tap (or Enter, Space: they are buttons) colours that tool and moves the colour off the previous one; the same tool
 * again turns it grey. On a device with hover the CSS colours a tool under the pointer by itself.
 */
function start( list ) {
	list.addEventListener( 'click', ( event ) => {
		const tile = event.target.closest( '.software-tools__tile' );
		if ( ! tile || ! list.contains( tile ) ) {
			return;
		}
		const wasOn = tile.getAttribute( 'aria-pressed' ) === 'true';
		list.querySelectorAll( '.software-tools__tile[aria-pressed="true"]' ).forEach( ( other ) => {
			other.setAttribute( 'aria-pressed', 'false' );
		} );
		if ( ! wasOn ) {
			tile.setAttribute( 'aria-pressed', 'true' );
		}
	} );
}

document.querySelectorAll( '[data-software-tools]' ).forEach( start );
```

### D5. `style.css`
```css
/*
 * Home software tools (DPH-024), wireframe .claude/plans/home/home-lofi-wireframe.html section D.
 * Greyscale until hover (devices with hover) or tap (aria-pressed).
 */

.software-tools {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 18px 4px;
	margin: 24px 0 0;
	padding: 0;
	list-style: none;
}

@media (min-width: 601px) {
	.software-tools {
		grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
		gap: 18px 10px;
	}
}

.software-tools__item {
	margin: 0;
	padding: 0;
}

.software-tools__tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 7px;
	width: 100%;
	min-height: 44px;
	padding: 6px 0;
	border: 0;
	border-radius: 8px;
	background: none;
	font: inherit;
	color: inherit;
	cursor: pointer;
	-webkit-tap-highlight-color: transparent;
}

.software-tools__icon {
	display: grid;
	place-items: center;
	box-sizing: border-box;
	width: 44px;
	height: 44px;
	border: 1.5px solid var(--wp--preset--color--contrast);
	border-radius: 10px;
	background: var(--wp--preset--color--base);
	filter: grayscale(1);
	transition: filter 0.25s, transform 0.2s;
}

@media (min-width: 601px) {
	.software-tools__icon {
		width: 58px;
		height: 58px;
		border-radius: 14px;
	}
}

.software-tools__img {
	display: block;
	width: 62%;
	height: 62%;
	object-fit: contain;
}

.software-tools__icon--text {
	font-size: 15px;
	font-weight: 800;
	line-height: 1;
}

@media (min-width: 601px) {
	.software-tools__icon--text {
		font-size: 20px;
	}
}

.software-tools__name {
	max-width: 100%;
	overflow-wrap: anywhere;
	font-size: 9px;
	line-height: 1.25;
	text-align: center;
	color: var(--wp--preset--color--mid);
	transition: color 0.2s;
}

@media (min-width: 601px) {
	.software-tools__name {
		font-size: 11.5px;
	}
}

.software-tools__tile[aria-pressed='true'] .software-tools__icon {
	filter: none;
	transform: translateY(-3px);
}

.software-tools__tile[aria-pressed='true'] .software-tools__name {
	color: var(--wp--preset--color--contrast);
}

@media (hover: hover) {
	.software-tools__tile:hover .software-tools__icon {
		filter: none;
		transform: translateY(-3px);
	}

	.software-tools__tile:hover .software-tools__name {
		color: var(--wp--preset--color--contrast);
	}
}

.software-tools__tile:focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
	.software-tools__icon,
	.software-tools__name {
		transition: none;
	}

	.software-tools__tile[aria-pressed='true'] .software-tools__icon,
	.software-tools__tile:hover .software-tools__icon {
		transform: none;
	}
}
```
The plugin's `cptdp-tool-icon` image is 128px square; at 62% of 58px the image is about 36px, so 128px is 3.5x: sharper than needed but small (2 to 6 KB). Do not add a second size.

---

## Phase E. Wire into Home

1. `functions.php`, `devportfolio_register_blocks()`: append
```php
		register_block_type( __DIR__ . '/blocks/testimonials' );
		register_block_type( __DIR__ . '/blocks/software-tools' );
```
2. `templates/front-page.html`: insert after the closing `<!-- /wp:group -->` of `#selected-work` and before `</main>`'s closing group (find the line `</main>`; the `<!-- /wp:group -->` right before the `<!-- /wp:group -->` that closes main stays last):
```html
	<!-- wp:devportfolio/testimonials /-->

	<!-- wp:group {"anchor":"software-tools","className":"home-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
	<div id="software-tools" class="wp-block-group home-section" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

		<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(26px, 3.4vw, 34px)","fontWeight":"800"}}} -->
		<h2 class="wp-block-heading" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">Software tools</h2>
		<!-- /wp:heading -->

		<!-- wp:devportfolio/software-tools /-->

	</div>
	<!-- /wp:group -->
```
The Contact section (DPH-025+) will go after these, per the wireframe order (Selected work, Testimonial, Software tools, Contact).
3. The Home page already loads `page.css` (`is_front_page()`); nothing to enqueue (block `style` and `viewScriptModule` load with the block).

---

## Phase F. Theme tests (PHP, `@group theme`)

Pattern: copy the `set_up()` of `tests/test-stats-strip.php` (`do_action( 'cptdp_register_post_types' )`, `require_once functions.php`, register blocks, `devportfolio_memo_flush()`), plus a `render()` that runs `do_blocks( '<!-- wp:devportfolio/<name> /-->' )`.

### F1. `tests/test-testimonials.php` (`Devportfolio_Test_Testimonials`), cases (assert exactly these)
1. No published testimonial: render is `''` (no heading either). A draft one and one with an empty quote are not counted.
2. Three published: three `data-testimonials-slide`, quote text in `blockquote.testimonials__quote`, name in `.testimonials__name`, company in `.testimonials__company`; **only the first** has `is-active`.
3. Order: `menu_order` then title (create with orders 2, 1, 1 and titles, assert the sequence).
4. Heading text is exactly `What clients say` in an `h2`; section has `id="testimonials"`; `aria-labelledby` points at the heading id.
5. Dots: with 3 quotes exactly 3 `testimonials__dot` buttons, `aria-label` `Show testimonial 1 of 3`, the first `aria-current="true"`, the group `hidden`; with 1 quote no dots at all.
6. No photo: no `<img` in the output.
7. No link yet (future ticket): the output contains no `<a ` and no `Read more`.
8. Escaping: a quote `<script>alert(1)</script>` and a name `A & B` render with no `<script` and `A &amp; B`; a company with `<b>` is stripped.
9. CSS and script contracts (read the files): `style.css` contains `font-style: italic`, `.testimonials__dot` with `width: 44px` and `height: 44px`, a `prefers-reduced-motion: reduce` block, `touch-action: pan-y`; `view.js` contains `PERIOD`, `pointerType`, `focus-visible`, `visibilitychange` and imports `./rotation.js`; `rotation.js` contains `export const PERIOD = 6000;`.
10. Registered: `WP_Block_Type_Registry` has `devportfolio/testimonials`.

### F2. `tests/test-software-tools-block.php` (`Devportfolio_Test_Software_Tools_Block`), cases
1. No tools: render is `''`.
2. Published tools only, in `menu_order` then title; a draft tool is absent.
3. One `<button type="button" class="software-tools__tile"` per tool with `aria-pressed="false"`, the tool name as visible text, and **no** `<a ` in the output (not links).
4. A tool with a featured image (create an attachment with `self::factory()->attachment->create_upload_object()` or `set_post_thumbnail` with a factory attachment) renders an `<img` with `alt=""`, `loading="lazy"`, width and height attributes, and no monogram span.
5. A tool without one renders `software-tools__icon--text` with the monogram; `devportfolio_tool_monogram` data provider: `Slack` => `Sl`, `ChatGPT` => `Ch`, `Adobe CC` => `Ad`, `Otter.ai` => `Ot`, `x` => `X`, `Æther` => `Æt`, `''` => `''`.
6. No categories, filters or hint text: output has no `<select`, no `data-filter`, no `aria-expanded`.
7. Escaping: a tool titled `A & B <i>` renders `A &amp; B` and no `<i>`.
8. CSS contract (read `style.css`): `grid-template-columns: repeat(3, minmax(0, 1fr))` at base, `repeat(auto-fill, minmax(112px, 1fr))` inside `@media (min-width: 601px)`, `gap: 18px 10px`, `58px` and `44px`, `filter: grayscale(1)`, `transition: filter 0.25s`, `translateY(-3px)` inside `@media (hover: hover)`, a `prefers-reduced-motion: reduce` block that sets `transform: none`, `min-height: 44px`.
9. Script contract: `view.js` contains `aria-pressed` and no `keydown` (buttons handle Enter and Space natively).

### F3. Edit `tests/test-home-template.php`
Add a test (do not change the existing needle list):
```php
	public function test_testimonials_and_tools_follow_selected_work(): void {
		$tpl     = $this->template();
		$needles = array(
			'id="selected-work"',
			'<!-- wp:devportfolio/video-modal /-->',
			'<!-- wp:devportfolio/testimonials /-->',
			'id="software-tools"',
			'>Software tools</h2>',
			'<!-- wp:devportfolio/software-tools /-->',
			'<!-- wp:template-part {"slug":"footer"',
		);

		$last = -1;
		foreach ( $needles as $needle ) {
			$pos = strpos( $tpl, $needle );
			$this->assertNotFalse( $pos, "{$needle} missing." );
			$this->assertGreaterThan( $last, $pos, "{$needle} is out of order." );
			$last = $pos;
		}
	}
```
Also assert the existing nav test still passes unchanged (Testimonials stays `/#testimonials`).

### F4. Gates (run all; paste failures, do not hide them)
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/testimonials 'node --test tests/*.test.mjs'
```
Existing totals (918 plugin with 12 skipped, 687 theme, and the Node suites) must stay green; the new counts are added to AGENTS.md.

---

## Populate and verify (local, then every environment)
1. `ddev snapshot`.
2. Plugin active, `ddev wp rewrite flush`. Run DPB-033's `ddev wp cptdp seed software-tools --dry-run` then without `--dry-run`: the tools receive their logos (needs C5 done).
3. `ddev wp cptdp seed testimonials --dry-run`, then without `--dry-run`: three **sample** testimonials appear. **Before launch, Trash them (or edit them in place) and enter Dave's real quotes in wp-admin.** Do not run this seeder on production.
4. Apply Dave's confirmed order (once): `ddev wp post update <id> --menu_order=10` per tool, or drag the Order column in wp-admin. Recipe to find ids: `ddev wp post list --post_type=software_tool --fields=ID,post_title,menu_order`.
5. **Browser check** (headless Chrome with puppeteer-core as in the earlier Home tickets, or manually) at 1280px and 390x844 on `/`:
   - Testimonials: heading, one italic quote centred, name and company, three dots (first filled), link target `/testimonials/`; the quote changes after 6 s with its name, company and dot; hovering stops it; Tab onto a dot stops it; with DevTools "Emulate prefers-reduced-motion: reduce" it never changes by itself but a dot click and a swipe (touch emulation) do; the nav `Testimonials` link scrolls to the section.
   - Tools: tiles grey; mouse hover gives colour, a 3px lift and a black name in 0.25s; with touch emulation a tap colours one, another tap moves it, the same tap greys it; Enter and Space on a focused tile do the same; 3 columns at 390px with 44px icons, 112px-minimum tiles at 1280px with 58px icons, last row left-aligned; no tile is a link.
   - Network tab: each logo is one small request (a few KB, `loading="lazy"`), 128px PNG, no image larger than 10 KB, the total of all tool images under about 250 KB.

## Conventional commits (ordered; one concern each; stage explicit paths; suites green at each commit)
Branch `feat/DPH-022-023-024-Testimonials-Software-Tools` from `main`. PR title: `feat(theme): DPH-022 DPH-023 DPH-024 Home testimonials and software tools`. Body from `.github/pull_request_template.md`, ending with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Every commit ends with the footer `Refs: <tickets>` then `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Normal merge commit, no squash.

1. `feat(custom-post-types-dp): add the Testimonial post type`. Files: `includes/post-types/testimonial.php`. Refs: DPH-022.
2. `test(custom-post-types-dp): cover the Testimonial post type`. Files: `tests/post-types/test-testimonial.php`. Refs: DPH-022.
2a. `feat(custom-post-types-dp): seed sample testimonials`. Files: `data/seeders/testimonials.csv`, `includes/seeders/testimonials.php`. Refs: DPH-022.
2b. `test(custom-post-types-dp): cover the testimonials seeder`. Files: `tests/seeders/test-testimonials.php`. Refs: DPH-022.
3. `feat(theme): add the Home testimonials block`. Files: `blocks/testimonials/{block.json,render.php,rotation.js,view.js,style.css}`, `functions.php` (register `testimonials` only). Refs: DPH-022, DPH-023.
4. `test(theme): cover the Home testimonials`. Files: `blocks/testimonials/tests/rotation.test.mjs`, `tests/test-testimonials.php`. Refs: DPH-022, DPH-023.
5. `docs(plans): amend DPB-033 icon size and monogram` (edit the two lines of Phase C1 in the DPB-033 plan, **only if that file is tracked by then**; it is untracked today, so edit it in the working tree and commit it with its own commit `docs(plans): add DPB-033 plan` as DPB-033 specifies, not here). Skip this commit when DPB-033 owns it.
6. `feat(custom-post-types-dp): add the tool icon build script`. Files: `bin/build-tool-icons.php`. Refs: DPH-024.
7. `feat(custom-post-types-dp): bundle the software tool logos`. Files: `data/seeders/icons/*.png`, `data/seeders/icons/README.md`, the `icon` column values in `data/seeders/software-tools.csv` (the column itself is DPB-033's commit). Refs: DPH-024, DPB-033.
8. `feat(theme): add the Home software tools block`. Files: `blocks/software-tools/{block.json,render.php,view.js,style.css}`, `functions.php` (helper and registration). Refs: DPH-024.
9. `test(theme): cover the Home software tools`. Files: `tests/test-software-tools-block.php`. Refs: DPH-024.
10. `feat(theme): add testimonials and software tools to Home`. Files: `templates/front-page.html`, `tests/test-home-template.php`. Refs: DPH-022, DPH-023, DPH-024.
11. `chore(custom-post-types-dp): bump version to 0.33.0`. Files: the plugin header `Version` and the version constant (use the next minor after whatever `main` has at merge time; DPB-033 may already have taken 0.33.0). Refs: DPH-022.
12. `docs(agents): document Home testimonials and software tools`. Files: `AGENTS.md` (Overview: the `testimonial` CPT and meta, plugin version, the logo folder and the build script; Theme architecture: the two blocks, the `devportfolio_tool_monogram()` helper; the new test counts and the new Node test command). Refs: DPH-022, DPH-023, DPH-024.
13. `docs(plans): add DPH-022-023-024 plan`. Files: this file only, last.

## Risks and what to report back
- **Logo licensing and trademark:** the owner must review the README before launch; some vendors forbid use outside their guidelines. A tool with no clean source ships without a file (monogram), not with a doubtful one.
- **Google Workspace and similar:** pick the product mark the vendor publishes, not another product's.
- **No SVG delegate in DDEV** is the likeliest blocker; fix by the `.ddev/config.yaml` package, as described in C4.
- **Sample testimonials are fake:** they say "Sample testimonial." on purpose, but must be removed before launch. Open the future ticket for the Testimonials page and the "Read more" link so DPH-022 item 3 is not forgotten.
- **Few testimonials at launch:** with fewer than three the section still shows; tell Dave how many are live.
- Nothing here changes existing behaviour: the nav, the Selected work section and the plugin's current seeders are untouched.
