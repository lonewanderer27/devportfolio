# Plan: Seed sample collaterals, and collateral page images, names and thumbnails (DPC-005, DPC-023)

Tickets:
- **DPC-005** Seed Collaterals (Database, Highest). Depends on DPC-004 (collateral categories seeder, done), DPB-021 (software tools) and DPB-010 (collateral CPT).
- **DPC-023** Collateral page images, names and thumbnails (Backend, Highest). Depends on DPB-010, DPB-025 (document page images), DPB-030 (page names) and DPB-031 (page thumbnails).

Two parts:
- **Part A (DPC-005)**, the seeder, is implemented and green but not yet committed (sections A1-A5).
- **Part B (DPC-023)**, page images, names and thumbnails for collaterals, is new (sections B1-B9). It reuses the document pipeline in `includes/documents/` instead of copying it. It also carries two additions asked for while planning, both on Refs DPC-023:
  - the page-image settings, labelled as shared by Documents and Brand Collaterals;
  - a featured image made from page 1.

---

# Part A: collaterals seeder (as built)

## A1. Goal and acceptance criteria

`ddev wp cptdp seed collaterals` fills the Brand Collaterals page with sample items.

- Every CSV row creates one **published** `collateral` with title, description, one `collateral_category`, software tools, paper size, page count and statistic rows, plus `highlight` and menu order.
- Re-running skips titles that exist in any status, including Trash. Nothing is modified.
- The whole CSV is validated before any write. A bad category, an unknown tool title, a bad number or a bad statistic stops the run with a message naming the row.
- The set covers each category (Flyer, Logo), a multi-page portrait item, a landscape item, a single page and a 24-page item.
- `--dry-run` writes nothing.
- No uploaded files: the seeder is metadata only, like `documents`. Files and featured images are added in wp-admin.

## A2. Decisions

- **Orientation** has no field of its own. A landscape item is a row whose `paper_size` says so (`A4 Landscape`).
- **Statistics:** `|`-separated `label:value` pairs, split on the **first** colon (`Reach:12k|CTR:3.4%`). An empty cell means none. A pair without a colon, or with an empty label, is an error.
- **No shared extraction** from `z-documents.php`: copying is the repo's seeder convention.

## A3. CSV (`data/seeders/collaterals.csv`)

Header: `order,title,description,category,software_tools,paper_size,total_pages,statistics,highlight`

| order | title | category | paper_size | pages | covers |
|---|---|---|---|---|---|
| 10 | Product Launch Flyer | flyer | A4 | 1 | single page, Flyer |
| 20 | Event Programme Booklet | flyer | A5 | 4 | multi-page portrait |
| 30 | Open Day Landscape Flyer | flyer | A4 Landscape | 2 | landscape |
| 40 | Logo & Wordmark Pack | logo | A4 | 1 | Logo, `&` title |
| 50 | Brand Identity Guidelines | logo | A4 | 8 | multi-page portrait |
| 60 | Logo Usage Handbook | logo | A4 | 24 | 24-page item |

## A4. Files

| File | Notes |
|---|---|
| `includes/seeders/z-collaterals.php` | Registry key `collaterals`. Runs after `software-tools` and before `z-documents` (file-name order). |
| `data/seeders/collaterals.csv` | |
| `tests/seeders/test-collaterals.php` | `CPTDP_Test_Seeder_Collaterals` |

## A5. As-built notes

- The `collateral` CPT has no `excerpt` support (title/editor/thumbnail/page-attributes), so the description is stored in `post_content`, like the videos seeder. The test asserts `post_content`.
- Statistics are on rows 10, 20 and 50. Highlight is on row 10 only.
- Extra failure cases beyond the first draft: a statistic without a label, a non-numeric order, a highlight other than 0/1, an error message that names the title and row, and a bad later row writing nothing.
- Totals after the run: plugin 768 tests (12 skipped), theme 358 tests.
- `ddev snapshot` `devportfolio_20261110212410` was taken before the real run.

---

# Part B: collateral page images, names and thumbnails (DPC-023)

## B1. Goal and acceptance criteria

A collateral gets the same stored page images, page names and page thumbnails as a document, made by the same code. A later collateral viewer can then show a flyer instantly, with no pdf.js on the visitor's side.

**DPC-023 acceptance criteria, as given, and where each is met:**

| Ticket criterion | Met by |
|---|---|
| Saving a collateral with a PDF produces page images, names and thumbnails, and sets the status (`none`, `pending`, `ready`, `needs_browser`, `failed`). Replacing the file regenerates. | B1 registry and B2 (shared scheduler, converter, outline, thumbs); tests "Hooks", "PDF collateral, server/browser", "Names", "Lifecycle" |
| Same Page images meta box, WP-CLI (`cptdp pages ...`) and an API equal to `cptdp_get_document_pages()`. | B1 (meta box, CLI), B2 (`cptdp_get_collateral_pages()`) |
| `cptdp_total_pages` updated. | D5: every ready collateral set writes its count |
| The admin list shows the page-1 thumbnail when there is no featured image. | B2 thumbnail cell; test "Admin" |
| WP-CLI backfills existing collaterals. | `ddev wp cptdp pages generate --all --post_type=collateral` (plus `thumbs`, `names`, `featured`); test "CLI" |
| The payload for the theme has images, ratio, names and thumbs, valid only while the file fingerprint matches. | `cptdp_get_collateral_pages()` is that payload (`pages`, `ratio`, `names`, `name_ranges`, `thumbs`, …) and returns null once the file's fingerprint changes (shared `valid_manifest()`); test "Fingerprint" |
| No public route ever triggers conversion. | Only `edit_post` + nonce routes exist, `get_pages()` never writes, front-end views schedule nothing; test "No public conversion" |
| **Open: which file types a collateral accepts.** | **Decided in D3:** PDF and Office files as for documents, plus raster images (JPEG, PNG, WebP, GIF) as a one-page set. SVG is refused. |

Details:

- When a collateral's file (`cptdp_collateral_file`) is added, replaced or removed, its page images are made, regenerated or cleared, exactly as for a document. This covers the meta hooks, `save_post_collateral` at priority 99, attachment edits, and post or attachment deletion.
- **PDF** collaterals follow the document rules unchanged:
  - The server converts in WP-Cron (Imagick, then Ghostscript).
  - The `pages_mode` setting applies. When the server cannot convert, the status becomes `needs_browser` and the Page images box renders the pages with pdf.js in the admin's browser.
  - Bottom trim, page names from bookmarks, and `t-NNN.webp` thumbnails all apply.
- **Office** collaterals (pptx, xlsx, docx and the rest) follow the document rules unchanged: LibreOffice, server only, a failure message recommending a PDF.
- **Images** are new, a source kind that only collaterals have:
  - A JPEG, PNG, WebP or GIF file becomes exactly **one page** (`p-001.webp`) plus its thumbnail (`t-001.webp`).
  - It is made on the server with WordPress's own image editor, never in the browser, and is never trimmed.
  - On success `cptdp_total_pages` is set to 1 (D5).
  - SVG and every other type give no pages (status `none`).
- `cptdp_get_collateral_pages( $id )` returns the same array as `cptdp_get_document_pages()`: `pages`, `count`, `width`, `height`, `ratio`, `crop_bottom`, `names`, `name_ranges`, `thumbs`, `thumb_width`, `thumb_height`. It never converts and never writes.
- **Collaterals list:**
  - The Thumbnail column shows the featured image, else the page-1 thumbnail.
  - A new **Page images** column shows the status.
  - A notice appears when any collateral is `needs_browser`.
- **Collateral edit screen:** the same **Page images** meta box as documents, with a Regenerate button.
- **WP-CLI:** `ddev wp cptdp pages generate|clear|status|recrop|thumbs|names` accept a collateral ID, and `--all` covers both types. `--post_type=document|collateral` narrows `--all`, and `pages status` gains a `type` column.
- **Settings that apply to both pages say so on screen.** Settings → Documents gets a section **"Page images (Documents and Brand Collaterals)"** holding `pages_mode`, `trim_bottom` and the new featured-image switch. Its intro says these settings apply to both the Documents page and the Brand Collaterals page, and the wording no longer says only "document". The collateral Page images box links to that section (D4).
- **Featured image from page 1 (in scope, both types).** When page images become ready and the post has no featured image, the plugin creates one from page 1 and sets it. It never replaces a featured image an editor chose, and it replaces its own generated one when the file changes. It is switched by a new checkbox, `featured_from_page` (default on), in the shared section above (D7).
- **No regressions:** every existing document test passes unchanged, apart from mechanical renames called out in B5.

## B2. Out of scope

- The theme collateral viewer and modal, and the theme code that turns the plugin data into the modal JSON. This ticket provides the plugin side of the payload, `cptdp_get_collateral_pages()`, which the theme ticket reads the way `devportfolio_document_viewer_data()` reads `cptdp_get_document_pages()`.
- A separate Settings → Collaterals page. The page-image settings stay on Settings → Documents, in a section labelled for both pages (D4).
- Native (selectable-text) viewers for collaterals.
- Uploading files from the seeder (Part A stays metadata only, so seeded collaterals have status `none` until a file is attached).

## B3. Facts established from the repo (do not re-derive)

The pipeline lives in `includes/documents/` (loaded by `class-loader.php`) and `includes/post-types/document-pages.php`. It is tied to `document` in these places, which are the exact edit points:

| Place | Tie to `document` |
|---|---|
| `CPTDP_Page_Store::pdf_attachment_id()`, `source_attachment_id()`, `source_kind()` | Read the meta key `cptdp_document_file` |
| `CPTDP_Page_Store::documents_using()` | Queries `post_type => document` with `meta_key => cptdp_document_file` |
| `CPTDP_Page_Scheduler` | `on_file_meta()` checks the key and type; `init()` hooks `save_post_document`; `on_before_delete_post()`, `ensure()` and `convert_now()` check `'document'`; `document_ids()` queries `document` |
| `CPTDP_Page_REST` | Route base `/documents/(?P<id>\d+)/pages`; `can_edit()` checks `'document'` |
| `CPTDP_Pages_Command::target_ids()` | Refuses non-documents |
| `document-pages.php` | Meta registration for `document`; `add_meta_boxes_document`; the enqueue checks `screen->post_type`; REST base `cptdp/v1/documents/<id>/pages`; the list notice on `edit-document` |
| `CPTDP_Page_Store::save_manifest()` | Overwrites `cptdp_total_pages` only for Word kinds |

Everything else is already post-agnostic and is reused as is:
- converting, trimming, names and thumbnails (`CPTDP_Page_Converter`, `CPTDP_Page_Analyzer`, `CPTDP_Page_Outline`, `CPTDP_Page_Thumbs`);
- the folder scheme `uploads/cptdp-pages/<attachment>-<hash8>-<post_id>/`, where post IDs are unique across types so two types never share a folder;
- the manifest, the lock, retries, and the browser run token;
- `assets/admin-document-pages.js`, which reads every URL from its localized `cptdpDocumentPages` config.

More facts:
- Collateral meta `cptdp_collateral_file` accepts any attachment except video and audio (`cptdp_collateral_sanitize_file()`), so images and SVG are possible.
- `cptdp_document_admin_thumb()` and `cptdp_document_admin_thumb_html()` only call `CPTDP_Page_Store::get_pages()`, so they work for any post once the store is type-agnostic.
- The test base `tests/class-cptdp-pages-test-case.php` has `make_pdf_attachment()`, `make_document()`, `fake_pages()`, `image_bytes( $format, $w, $h )`, the uploads redirect, and `require_server_tooling()`.

## B4. Decisions

- **D1 One registry of page-image post types.** Add to `CPTDP_Page_Store`:
  ```php
  const SOURCE_META = array(
  	'document'   => 'cptdp_document_file',
  	'collateral' => 'cptdp_collateral_file',
  );
  public static function post_types(): array;                  // array_keys( SOURCE_META )
  public static function supports( int $post_id ): bool;       // isset( SOURCE_META[ get_post_type( $post_id ) ] )
  public static function file_meta_key( int $post_id ): string; // '' when not supported
  ```
  Every place in B3 asks the registry instead of comparing with `'document'`. A third type later is one array entry. There is no filter: the list is fixed, and a filter would be a speculative extension point.
- **D2 Meta keys keep their names.** `cptdp_document_pages`, `_status`, `_error` and `_source` are stored on collaterals too. Renaming them would mean migrating every existing document. The constants on `CPTDP_Page_Store` already hide the names, and the theme reads through the API, so the odd-looking prefix costs nothing. The cron hook `cptdp_convert_document_pages` also keeps its name, because renaming it would orphan queued events.
- **D3 Images are a new kind, `image`, made by the WordPress image editor and only for collaterals.**
  - `kind_of_attachment()` returns `image` for `image/jpeg`, `image/png`, `image/webp` and `image/gif`.
  - The caller allows it only when the post type is `collateral`, so a document stays PDF or Office as its sanitiser already enforces.
  - It is converted by a new `CPTDP_Image_Page_Converter::convert( $source, $folder )`, built on `wp_get_image_editor()`. This is core: Imagick or GD, whichever exists, so no new tooling and no browser path is needed.
  - **Server only**, like Office: `pages_mode` does not apply and `needs_browser` never happens. The browser upload routes answer 400 `cptdp_server_only`, a new code; the old `cptdp_pptx_server_only` stays for Office.
  - With no image editor that can write WebP, it falls back to JPEG (the store already accepts `jpg` pages). With no editor at all, the status is `failed` with "This server cannot process images."
- **D4 Shared settings, labelled as shared.** `pages_mode`, `trim_bottom`, the new `featured_from_page` and the page cap (`cptdp_pages_max_pages`) apply to both types. Images are never trimmed: the artwork is the content, and a white band at the bottom of a flyer is design.
  - The option stays `cptdp_documents`, with the same keys, so no stored value moves. Only the screen changes.
  - The page-image fields move out of the "Documents" section into a new section, `cptdp_documents_page_images`, titled **"Page images (Documents and Brand Collaterals)"**, anchor `#cptdp-page-images`.
  - The section intro reads: "These settings apply to both the Documents page and the Brand Collaterals page. Page images are made when a document or collateral with a file is saved."
  - The "Documents" section keeps only the book title, and its intro says "Options for the Documents page only."
  - Labels and help text say "document or collateral" wherever they now say "document":
    - the `browser` mode label ("Browser only (generated from the document or collateral edit screen)");
    - the `pages_mode` help text (line 279);
    - the `trim_bottom` help text (line 290).
  - The Page images meta box on both edit screens ends with "Page image settings (shared with Documents/Brand Collaterals)", a link to `options-general.php?page=cptdp-documents#cptdp-page-images`, shown only to users who can `manage_options`.
  - The Settings → Documents menu title stays "Documents". Renaming the page would change its slug and the links already in the Office warnings.
- **D3 note, the file-type decision DPC-023 leaves open.** A collateral accepts:
  - PDF, the ticket's minimum;
  - Office files, which come free with the shared pipeline;
  - JPEG, PNG, WebP and GIF, as a one-page set.

  SVG is **not** turned into pages, for two reasons:
  - Rasterising it needs Imagick with an SVG delegate (librsvg or MSVG), which is often missing or switched off by `policy.xml`.
  - An SVG is XML that can reference external files and carry scripts, so feeding it to ImageMagick is a known attack surface.

  An SVG collateral keeps status `none`, and the Page images box says "SVG files are not turned into page images; upload a PNG or PDF export to show it in the viewer." `cptdp_collateral_sanitize_file()` still accepts SVG as the downloadable file.
- **D5 Page count is updated for collaterals (DPC-023).** For a collateral, `save_manifest()` writes `cptdp_total_pages = count` for **every** kind, extending the branch Word uses today to `is_word_kind( $kind ) || 'collateral' === get_post_type( $post_id )`. The page count of a collateral therefore always follows its file, and a hand-typed or seeded count (Part A) is corrected rather than marking the set `failed`. Documents are unchanged: PDFs keep the count check and only Word overwrites.
- **D6 Generalise the module in place.** `document-pages.php` becomes the page-images module for every type in `CPTDP_Page_Store::post_types()`.
  - Its file name and existing function names stay, to avoid churn and to keep the theme's `function_exists()` calls working.
  - Add one public wrapper, `cptdp_get_collateral_pages()`, so the later viewer reads naturally.
  - Collateral columns stay in `collateral.php`.
- **D7 Featured image from page 1, for documents and collaterals.** This changes documented behaviour: AGENTS says "No featured image is created automatically". That is why it is a setting, default on, in the shared section.
  - **New class** `CPTDP_Page_Featured`, in `includes/documents/class-page-featured.php`.
  - **When it runs.** `sync( int $post_id )` is called at the end of `CPTDP_Page_Store::save_manifest()`, once `reconcile()` has left the status `ready`. That covers both producers, the server (`record_conversion()`) and the browser (`CPTDP_Page_REST::finish()`). It never runs from `ensure()` or a page view.
  - **Ownership.** Post meta `cptdp_featured_generated` (integer, not in REST) holds the attachment the plugin made. The attachment carries `_cptdp_generated_for` (the post ID). Ours means `get_post_thumbnail_id( $post_id ) === (int) get_post_meta( $post_id, 'cptdp_featured_generated', true )`.
  - **Rules in `sync()`:**
    1. Setting off: do nothing. Generated images that already exist stay.
    2. The post has a featured image that is not ours: do nothing, and delete an orphaned generated attachment if one is recorded.
    3. No featured image, or ours: create a new attachment from page 1, set it with `set_post_thumbnail()`, record it, then delete the previous generated attachment (`wp_delete_attachment( $old, true )`).
  - **Source.** Page 1's full stored image, `p-001.<ext>` from `get_pages()['pages'][0]`, resolved to its path in the validated folder (never a URL fetch). The bottom crop is not applied, because the file is uncropped.
  - **Creating it.** Copy the image to `wp_tempnam()`, then `media_handle_sideload( array( 'name' => sanitize_file_name( $post->post_name . '-page-1.' . $ext ), 'tmp_name' => $tmp ), $post_id, $post->post_title )`, so WordPress makes the normal sub-sizes and the attachment's parent is the post. Load `wp-admin/includes/{file,media,image}.php` first, because it also runs in cron and CLI.
  - **Failures.** A failure is logged in `cptdp_document_pages_error`, prefixed "Featured image: ", and never changes the page status. The images are still ready.
  - **Clean-up.**
    - When the file is removed, `ensure()` with no source calls `CPTDP_Page_Featured::remove( $post_id )`, which deletes the generated attachment only while it is still the featured image.
    - When the file is replaced, the old generated image stays as the featured image until the new set is ready, then `sync()` swaps it, so the shelf never shows a blank.
    - `on_before_delete_post()` deletes the generated attachment.
    - `before_delete_post` on an attachment that has `_cptdp_generated_for` deletes the post's record, so an editor who deletes it in Media can let the next set make a new one.
  - **An editor removing the featured image** is not undone straight away: `sync()` only runs when a new page set is saved, or on `pages featured`.
  - **WP-CLI:** `ddev wp cptdp pages featured [<id>|--all] [--post_type=…] [--force]` backfills sets made before this feature. `--force` regenerates ours and never touches an editor's image.
  - **Admin.** The list Thumbnail columns (both types) already show the featured image first, so they now show the generated one. The page-1 thumbnail fallback stays for posts without one, such as when the setting is off.

## B5. Work packages (in order; each leaves both suites green)

### Phase B0: environment
Run `ddev start`. Make sure the test library is installed (`install-wp-tests.sh` after any restart). Baseline: plugin 768 tests (12 skipped), theme 358 tests.

### Phase B1: post-type registry (refactor, no behaviour change)
`includes/documents/class-page-store.php`:
1. Add `SOURCE_META`, `post_types()`, `supports()` and `file_meta_key()` (D1).
2. `pdf_attachment_id()`, `source_attachment_id()` and `source_kind()` read `get_post_meta( $post_id, self::file_meta_key( $post_id ), true )`. An unsupported post returns 0 or `''`.
3. Rename `documents_using( $attachment_id )` to `posts_using()`. It runs one `get_posts()` per entry of `SOURCE_META`, with that type and meta key, and merges the IDs. Update its callers in the scheduler.

`includes/documents/class-page-scheduler.php`:
- `on_file_meta()`: continue only when `CPTDP_Page_Store::supports( $post_id )` and `$meta_key === CPTDP_Page_Store::file_meta_key( $post_id )`.
- `init()`: loop over `post_types()` and add `save_post_{$type}` at priority 99.
- `on_before_delete_post()`, `ensure()` and `convert_now()`: replace `'document' ===` with `supports()`.
- Rename `document_ids()` to `post_ids( $status = 'any', array $types = array() )`, where empty means every registered type. Rename `documents_with_pdf()` to `posts_with_source( array $types = array() )`.

`includes/documents/class-page-rest.php`:
- `register_routes()` loops over `array( 'documents' => 'document', 'collaterals' => 'collateral' )` and registers the same five routes (state, `start`, `<n>`, `finish`, `retry`) under `/{base}/(?P<id>\d+)/pages`. Each route passes its post type to `can_edit()` through a closure.
- `can_edit( $request, $type )` answers 404 `cptdp_not_found` when `get_post_type( $id ) !== $type`. `edit_post` and the nonce are unchanged.
- Generalise the messages: "Not found." and "You are not allowed to edit this item."

`includes/cli/class-pages-command.php`:
- `target_ids()` accepts any ID where `supports()` is true (error: "{id} has no page images (not a document or collateral)."). It reads an optional `--post_type=<document|collateral>` for `--all`, and refuses an unknown value.
- `status` adds a `type` column.

`includes/post-types/document-pages.php`:
- `cptdp_register_document_pages_meta()` loops over `post_types()`.
- The meta box is added with `add_meta_boxes` for each type.
- The enqueue continues when `in_array( $screen->post_type, post_types() )`, and builds `$base` from a small map (`document` to `documents`, `collateral` to `collaterals`). Put the map once on `CPTDP_Page_REST` as `const ROUTE_BASES` and use it in both places.
- The box text says "document" or "collateral" through `get_post_type_object( $type )->labels->singular_name`.

Tests stay green with only mechanical updates: renamed helpers (`documents_using`, `document_ids`, `documents_with_pdf`), and REST messages if any assertion pins them. Grep `tests/` for each before renaming.

### Phase B2: collaterals get PDF and Office page images
This works once B1 is in. The remaining edits:
- `CPTDP_Page_Store::save_manifest()` writes `cptdp_total_pages` for collaterals of every kind (D5). `reconcile()` is unchanged, because the count then always matches.
- `cptdp_document_pages_render_box()` shows the SVG message from D3 when a collateral's file is `image/svg+xml`.
- `collateral.php` `cptdp_collateral_columns()` adds `cptdp_pages_status` ("Page images", after Pages). The cell prints `cptdp_document_pages_status_label( get_status(), count, kind )`, copied from `document.php`'s `cptdp_pages_status` case and moved into a shared helper `cptdp_pages_status_cell( $post_id )` in `document-pages.php`, so both lists call one function.
- The `thumbnail` cell falls back to `cptdp_document_admin_thumb_html( $post_id )` when there is no featured image, the same code as `document.php` lines 112-117.
- The admin CSS widens `.column-thumbnail` to 90px and sets `.column-cptdp_pages_status{width:140px}`.
- `cptdp_document_pages_list_notice()` runs on `edit-{type}` for every type and queries that screen's type. The wording uses the type's plural label.
- `document-pages.php` adds:
  ```php
  function cptdp_get_collateral_pages( int $post_id ): ?array {
  	return 'collateral' === get_post_type( $post_id ) ? CPTDP_Page_Store::get_pages( $post_id ) : null;
  }
  ```
  It sits inside `function_exists` like its siblings.

### Phase B3: image sources (collaterals only)
`CPTDP_Page_Store`:
- Add `const KIND_IMAGE = 'image'` and `const IMAGE_MIMES = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' )`.
- `kind_of_attachment()` returns `image` for those mime types.
- Add `is_image_kind()`.
- `source_attachment_id()` refuses `image` unless `get_post_type( $post_id ) === 'collateral'`.
- `save_manifest()` marks image sets `source_kind = image` and `converted_via = image-editor`, and writes `cptdp_total_pages = 1` (D5).

New `includes/documents/class-image-page-converter.php`, `CPTDP_Image_Page_Converter`:
- `const MAX_PIXELS = 40000000` (filter `cptdp_image_max_pixels`), to refuse decompression bombs before decoding.
- `is_available(): bool`, true when `wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) )`.
- `convert( array $source, string $folder ): array|WP_Error`:
  1. **Validate by content.** `wp_getimagesize( $source['path'] )` must succeed, its mime must be in `IMAGE_MIMES` and match the attachment's mime, and `width * height <= MAX_PIXELS`. On failure return `WP_Error( 'cptdp_not_an_image', ... )`.
  2. Open it with `wp_get_image_editor( $source['path'] )`. An animated GIF yields its first frame, which is what the editor does by default.
  3. Choose the width as the converter does: `CPTDP_Page_Converter::WIDTH_PORTRAIT` (1400) when height ≥ width, else `WIDTH_LANDSCAPE` (1800). Call `resize( $w, null, false )`, never upscaling, so a small logo keeps its size.
  4. Output type: WebP when `wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) )`, else JPEG. Quality is `CPTDP_Page_Converter::QUALITY` (82). Transparent PNGs are flattened on white: JPEG does this by itself, and for WebP call the editor's `set_quality` and accept the editor's alpha, since the book background is white.
  5. Save as `$folder/p-001.<ext>`.
  6. Make the thumbnail by reopening the saved page and resizing to `CPTDP_Page_Thumbs::WIDTH` (240), quality 65, as `t-001.<ext>`. If it fails, leave no thumbnail: a set without thumbnails is valid (DPB-031).
  7. Return `count => 1`, `width`, `height` (from the saved page), `ext`, `crop_bottom => 0.0`, `thumb_width`, `thumb_height`, `page_names => array()`, `producer => 'server'`.

`CPTDP_Page_Converter::convert()` branches before the Office branch:
```php
if ( CPTDP_Page_Store::is_image_kind( $source['kind'] ) ) {
	if ( ! CPTDP_Image_Page_Converter::is_available() ) { return new WP_Error( 'cptdp_unavailable', ... ); }
	$folder = CPTDP_Page_Store::folder_for( $post_id ); // same delete_dir + wp_mkdir_p as convert_pdf_file()
	$result = CPTDP_Image_Page_Converter::convert( $source, $folder );
	// on error delete_dir( $folder ); on success add source_token, dir, producer like convert_pdf_file()
}
```
Extract the folder prepare and clean-up already in `convert_pdf_file()` (`delete_dir`, `wp_mkdir_p`, the error path) into one private `prepare_folder()`, so both branches share it instead of copying it.

`CPTDP_Page_Scheduler::ensure()` treats `is_image_kind()` like `is_office_kind()`: pending and scheduled when the image converter is available, else `failed` with its reason, whatever `pages_mode` says.

`CPTDP_Page_REST`: `start`, `<n>` and `finish` answer 400 `cptdp_server_only` for an image source, and `retry` accepts it. The meta box shows "Regenerate", the server button, for image sources, using the same branch as Office in `cptdp_document_pages_render_box()`. Status label: "Ready, 1 page (from an image)".

### Phase B3a: shared page-image settings, labelled for both pages (D4)
`includes/settings/documents.php`:
- Add the section `cptdp_documents_page_images` ("Page images (Documents and Brand Collaterals)"), with a render callback that prints the intro from D4 inside `<div id="cptdp-page-images">`.
- Move the `pages_mode` ("Generate page images") and `trim_bottom` ("Page margins") fields into it, changing only their section argument.
- Add the field **"Featured image"**: a checkbox for `featured_from_page`, label "Use page 1 as the featured image when a document or collateral has none". Help text: "Applies to the Documents page and the Brand Collaterals page. An image you choose yourself is never replaced. Off keeps the images already made."
- Change the "Documents" section intro to "Options for the Documents page only."
- Update the three strings listed in D4.
- `featured_from_page` goes into the defaults (`true`) and into `cptdp_documents_checkbox_keys()`, so the sanitiser, the REST schema and "partial update keeps missing keys" all pick it up with no other edit.

`includes/post-types/document-pages.php`: `cptdp_document_pages_render_box()` ends with the settings link from D4 for users who can `manage_options`.

### Phase B3b: featured image from page 1 (D7)
- New `includes/documents/class-page-featured.php` with:
  - `enabled(): bool`, reading `cptdp_get_documents_settings()['featured_from_page']`;
  - `sync( int $post_id, bool $force = false ): string`, returning `created`, `kept`, `skipped` or `failed`;
  - `remove( int $post_id ): void`;
  - `init()`, which hooks `before_delete_post` for attachments carrying `_cptdp_generated_for`.
- `CPTDP_Page_Store::save_manifest()` calls `CPTDP_Page_Featured::sync( $post_id )` after `reconcile()` when the status is `ready`.
- `CPTDP_Page_Scheduler::ensure()`, on the no-source branch, calls `CPTDP_Page_Featured::remove()`. `on_before_delete_post()` calls it too.
- `document-pages.php` registers `cptdp_featured_generated` for every type in `post_types()` (`show_in_rest` false) and calls `CPTDP_Page_Featured::init()` next to the scheduler and REST.
- `class-pages-command.php` adds the `featured` subcommand from D7. `status` gains a `featured` column: `generated`, `editor` or `-`.

### Phase B4: tests
New folder `tests/collaterals/`, picked up by `phpunit.xml.dist` (check its directories and add the folder if it globs by name). All classes extend `CPTDP_Pages_Test_Case`. Add a helper `make_collateral( int $attachment_id = 0, int $total_pages = 0 ): int` next to `make_document()`, and `make_image_attachment( string $format, int $w, int $h ): int` built from the existing `image_bytes()`.

`tests/collaterals/test-collateral-pages.php` (`CPTDP_Test_Collateral_Pages`, `@group collateral`, `@group pages`):

- **Registry:**
  - `post_types()` equals `['document','collateral']`.
  - `file_meta_key()` is right for each type and `''` for a page.
  - `supports()` is false for `post` and `video`.
- **Hooks:** adding, updating or deleting `cptdp_collateral_file` calls `ensure()`. The status becomes `pending` or `needs_browser` and the cron event is scheduled, the same assertions as `test-page-scheduler.php`. The same meta key on a `post` does nothing.
- **PDF collateral, server:** `require_server_tooling()`, a 3-page PDF, then `convert_now()`.
  - `cptdp_get_collateral_pages()` returns 3 pages, 3 thumbs, a ratio, and folder `<att>-<hash8>-<collateral id>`.
  - `cptdp_get_document_pages()` on a document is unaffected.
- **PDF collateral, browser:** `pages_mode=browser` gives `needs_browser`. The REST `start`, `<n>` and `finish` routes under `/collaterals/<id>/pages` work. `/documents/<collateral id>/pages` answers 404, and so does `/collaterals/<document id>/pages`.
- **Image, portrait PNG 1080x1350:**
  - one page, 1400px wide at most and not upscaled (1080 stays 1080);
  - a `webp` or `jpg` extension;
  - a thumbnail 240 wide;
  - `crop_bottom` 0, `cptdp_total_pages` 1, status `ready`;
  - the manifest has `source_kind = image`.
- **Image, landscape PNG 2000x700:** width capped at 1800 and `ratio > 1`.
- **Image safety:**
  - A `.png` whose bytes are text gives `failed`, `cptdp_not_an_image`, and no folder left behind.
  - Over `cptdp_image_max_pixels` (filtered to 100) gives `failed`.
  - An SVG gives status `none`.
  - An image set on a **document** gives `none`, because its sanitiser refuses images anyway; assert through `source_attachment_id()`.
- **Names (DPB-030 on collaterals):** a 3-page PDF built with `pdf_bytes()` and an outline (as in `tests/documents/test-page-names.php`) gives `names` such as `['Cover','Inside','Inside']` and matching `name_ranges`. A PDF without bookmarks gives `names === []`.
- **Page count (D5):** a collateral with `cptdp_total_pages = 24` and a 3-page PDF ends `ready` with `cptdp_total_pages = 3`. The same setup on a document still ends `failed`, the unchanged document rule.
- **Fingerprint:** after `ready`, rewrite the file with `overwrite_attachment_file()` (new size and mtime). `cptdp_get_collateral_pages()` returns null before any regeneration, and `ensure()` then queues a new set.
- **No public conversion:**
  - As a logged-out visitor, GET, `start`, `<n>`, `finish` and `retry` under `/collaterals/<id>/pages` answer 401. As a subscriber they answer 403.
  - `go_to()` the collateral archive and the single URL, with a source whose images are missing: no cron event is scheduled (`wp_next_scheduled( CRON_HOOK, [ $id ] )` is false), and the status and meta are unchanged.
  - `cptdp_get_collateral_pages()` never writes meta: compare `get_post_meta( $id )` before and after.
  - No route under `cptdp/v1` is registered with `permission_callback` `__return_true`: assert over `rest_get_server()->get_routes()`.
- **Image server-only:** `start` answers 400 `cptdp_server_only`. `retry` queues the job. `pages_mode=browser` still gives `pending`.
- **Lifecycle:**
  - Replacing the file clears the old folder.
  - Deleting the collateral deletes its folder.
  - Deleting the attachment clears every collateral and document using it (`posts_using()`).
  - Two collaterals sharing one PDF keep separate folders.
- **Admin:**
  - The list has the `cptdp_pages_status` column.
  - The thumbnail cell prints the page-1 `<img>` when there is no featured image, and the featured image when there is one.
  - The Page images meta box is registered for `collateral`.
  - The enqueue localizes `stateUrl` with `/collaterals/<id>/pages`.
- **CLI:** `target_ids()` with a collateral ID passes and with a page ID errors. `--post_type=collateral` limits `--all`. Test through the command class with `WP_CLI` stubbed, as the existing CLI tests do; if none exist, test `CPTDP_Page_Scheduler::post_ids()` directly.

`tests/settings/test-documents.php` (extend the existing class):
- **Shared section:**
  - The section `cptdp_documents_page_images` exists, and its title contains both "Documents" and "Brand Collaterals".
  - `pages_mode`, `trim_bottom` and `featured_from_page` are registered in it and no longer in the "Documents" section.
  - The rendered intro contains "Documents page and the Brand Collaterals page".
- **Featured setting:**
  - `featured_from_page` defaults to true.
  - It is in the REST schema.
  - A partial REST update keeps it.
  - The settings form treats an unticked box as off.

`tests/collaterals/test-page-featured.php` (`CPTDP_Test_Page_Featured`, `@group pages`). Run every case once for a document and once for a collateral (data provider):
- **Created:** a ready set with no thumbnail creates an attachment whose parent is the post and whose file is a copy of page 1, sets it as the thumbnail, and records `cptdp_featured_generated` and `_cptdp_generated_for`.
- **Editor's image:** a post with its own featured image keeps it, and no attachment is created.
- **Setting off:** off means no attachment. Switching it off later keeps the existing generated one.
- **File replaced:** a new ready set replaces our attachment with a new one and deletes the old one. The thumbnail is never empty in between: assert it after `ensure()` and before the conversion.
- **File removed:** our attachment is deleted while it is still the featured image. An editor's image survives.
- **Post deleted:** our attachment is deleted.
- **Media deletion:** an editor deleting our attachment in Media clears the record, and `pages featured <id>` makes a new one.
- **Failure:** a forced `media_handle_sideload` failure (filter `wp_handle_sideload_prefilter` returning an error) leaves the status `ready` and the error prefixed "Featured image: ".
- **Browser path:** REST `finish` on a PDF collateral creates the featured image too.
- **Meta box:** the box shows the settings link to an administrator and hides it from an editor.

Existing document suites: run them all unchanged as the regression proof for B1. If a document test asserts that no thumbnail exists after conversion, it is now expected to fail; switch `featured_from_page` off in that test's `set_up()`, and note it in the commit body.

### Phase B5: version and docs
- `custom-post-types-dp.php`: keep `Version: 0.26.0`. It is not released yet, so one bump covers the whole ticket.
- `AGENTS.md`:
  - Add a **Collateral page images, names and thumbnails (v0.26.0, DPC-023)** paragraph: the accepted file types (D3, SVG refused), `cptdp_total_pages` following the file (D5), the registry, image kind, API `cptdp_get_collateral_pages()`, routes `cptdp/v1/collaterals/<id>/pages...`, CLI `--post_type`, and that meta keys keep the `cptdp_document_pages*` names.
  - Describe the shared **"Page images (Documents and Brand Collaterals)"** settings section.
  - Describe the featured image from page 1 (D7, `cptdp_featured_generated`, `pages featured`), and **replace** the existing sentence "No featured image is created automatically." in the DPB-031 paragraph.
  - Extend the `collateral` bullet with the new column and meta box.
  - Update the test totals.

## B6. File inventory (Part B)

| File | Change |
|---|---|
| `includes/documents/class-page-store.php` | registry, image kind, `posts_using()`, image manifest marks and page count |
| `includes/documents/class-page-scheduler.php` | type-agnostic hooks and queries, image treated as server-only |
| `includes/documents/class-page-rest.php` | `ROUTE_BASES`, routes per type, `can_edit( $type )`, `cptdp_server_only` |
| `includes/documents/class-page-converter.php` | image branch, `prepare_folder()` |
| `includes/documents/class-image-page-converter.php` | **new** |
| `includes/cli/class-pages-command.php` | any supported ID, `--post_type`, `type` column |
| `includes/post-types/document-pages.php` | meta, meta box, enqueue and notice for every type; `cptdp_pages_status_cell()`; `cptdp_get_collateral_pages()` |
| `includes/post-types/document.php` | status cell calls `cptdp_pages_status_cell()` |
| `includes/post-types/collateral.php` | Page images column, thumbnail fallback, column CSS |
| `includes/settings/documents.php` | shared "Page images (Documents and Brand Collaterals)" section, `featured_from_page`, wording |
| `includes/documents/class-page-featured.php` | **new** |
| `tests/class-cptdp-pages-test-case.php` | `make_collateral()`, `make_image_attachment()` |
| `tests/collaterals/test-collateral-pages.php` | **new** |
| `tests/collaterals/test-page-featured.php` | **new** |
| `tests/settings/test-documents.php` | shared section and `featured_from_page` cases |
| `tests/documents/*` | mechanical renames only |
| `AGENTS.md` | docs |

`assets/admin-document-pages.js` needs no change, because its URLs come from PHP. Check it once on a collateral edit screen.

## B7. Risks

- **Renaming shared helpers** (`documents_using`, `document_ids`, `documents_with_pdf`) could miss a caller. Grep the plugin **and the theme** (`grep -rn "documents_using\|document_ids\|documents_with_pdf" wp-content/`) before and after.
- **Image editor differences:** GD and Imagick produce slightly different sizes and alpha. Tests assert limits (≤ 1400, ratio, extension), not exact bytes.
- **Large images:** the pixel cap is checked from the header before decoding. `wp_get_image_editor()` still decodes the full image, so memory follows the cap (40 MP ≈ 160 MB RGBA). Lower the filter on small hosts.
- **Shared meta key names** (D2) could confuse a reader. The AGENTS paragraph states it explicitly.
- **Featured images change existing documents.** With the default on, the next conversion, or `pages featured --all`, gives every document without a featured image a page-1 cover image. That adds one Media item per post. The setting and the "never replace an editor's image" rule limit this; say so in the PR body.
- **Media clutter from repeated regenerations** is bounded: each new generated image deletes the previous one.
- **Seeded collaterals have no file**, so the pipeline is only exercised once a file is attached. Manual check in B9.

## B8. Conventional commits

Branch `feat/DPC-005-023-Collaterals-Seed-Page-Images` from `main`. Rules as in `AGENTS.md`:
- subject `type(scope): description` of 72 characters or fewer;
- footer `Refs:` with the ticket(s) in the table's last column, then `Co-Authored-By: Claude Code <noreply@anthropic.com>`;
- stage explicit paths and never `git add -A`, because other tickets' plan files are untracked in the tree;
- every commit green.

| # | Subject | Stages | Refs |
|---|---|---|---|
| 1 | `docs(plans): add DPC-005 and DPC-023 collaterals plan` | this plan | DPC-005, DPC-023 |
| 2 | `feat(custom-post-types-dp): add collaterals seeder` | `data/seeders/collaterals.csv`, `includes/seeders/z-collaterals.php` | DPC-005 |
| 3 | `test(custom-post-types-dp): cover collaterals seeder` | `tests/seeders/test-collaterals.php` | DPC-005 |
| 4 | `refactor(custom-post-types-dp): let page images serve any post type` | B1 files + the mechanical test renames | DPC-023 |
| 5 | `feat(custom-post-types-dp): generate page images for collaterals` | B2: `collateral.php`, `document.php`, `document-pages.php`, `class-page-store.php` (D5 page count) | DPC-023 |
| 6 | `feat(custom-post-types-dp): turn collateral images into one page` | B3: `class-image-page-converter.php`, `class-page-store.php`, `class-page-converter.php`, `class-page-scheduler.php`, `class-page-rest.php`, `document-pages.php` | DPC-023 |
| 7 | `test(custom-post-types-dp): cover collateral page images` | `tests/class-cptdp-pages-test-case.php`, `tests/collaterals/test-collateral-pages.php`, `phpunit.xml.dist` if changed | DPC-023 |
| 8 | `feat(custom-post-types-dp): share page image settings with Collaterals` | B3a: `includes/settings/documents.php`, `document-pages.php` (settings link) | DPC-023 |
| 9 | `test(custom-post-types-dp): cover the shared page image settings` | `tests/settings/test-documents.php` | DPC-023 |
| 10 | `feat(custom-post-types-dp): use page 1 as the featured image` | B3b: `class-page-featured.php`, `class-page-store.php`, `class-page-scheduler.php`, `document-pages.php`, `class-pages-command.php`, any document test that now switches `featured_from_page` off | DPC-023 |
| 11 | `test(custom-post-types-dp): cover featured images from page 1` | `tests/collaterals/test-page-featured.php` | DPC-023 |
| 12 | `chore(custom-post-types-dp): bump version to 0.26.0` | `custom-post-types-dp.php` | DPC-005, DPC-023 |
| 13 | `docs(agents): document collaterals seeder and page images` | `AGENTS.md` | DPC-005, DPC-023 |

Commits 5, 6, 8 and 10 must each pass the suite on their own. If a feature commit cannot pass without its tests, squash it with the test commit that follows, which AGENTS allows for a small pair.

**Commits 8 and 10 change both pages, so their bodies say so in plain words**, for the reviewer and the Git history:

```
feat(custom-post-types-dp): share page image settings with Collaterals

Affects both the Documents page and the Brand Collaterals page.
Settings > Documents now has a "Page images (Documents and Brand
Collaterals)" section with the page image mode and the bottom trim,
which collaterals read too. Stored option keys are unchanged.

Refs: DPC-023
Co-Authored-By: Claude Code <noreply@anthropic.com>
```

```
feat(custom-post-types-dp): use page 1 as the featured image

Affects both the Documents page and the Brand Collaterals page.
When page images are ready and a document or collateral has no
featured image, page 1 becomes one. An image chosen by an editor
is never replaced. Switch: Settings > Documents > Page images >
Featured image (on by default). Backfill: wp cptdp pages featured --all.

Refs: DPC-023
Co-Authored-By: Claude Code <noreply@anthropic.com>
```

PR title: `feat(custom-post-types-dp): seed collaterals and make their page images` (covers DPC-005 and DPC-023; the PR body lists both tickets and the DPC-023 file-type decision from D3). The body comes from `.github/pull_request_template.md` (tick New feature) and ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Its Description has a short **"Affects Documents and Brand Collaterals"** list:
- the shared settings section;
- the featured image from page 1 and its switch;
- that existing documents get a featured image on their next conversion or after `pages featured --all`.

Merge with a normal merge commit.

## B9. Verification

```bash
P=/var/www/html/wp-content/plugins/custom-post-types-dp
ddev exec --dir=$P vendor/bin/phpunit --filter CPTDP_Test_Collateral_Pages
ddev exec --dir=$P vendor/bin/phpunit            # full plugin suite, document tests unchanged
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
grep -rn "documents_using\|document_ids\|documents_with_pdf" wp-content/   # no stale callers
```

Manual check in wp-admin. Attach these files to three seeded collaterals; the files stay in `~/Documents` and are not committed:

| File | Expected |
|---|---|
| `flyer-Everything RayneBowe newsletter 3.pdf` | Ready, 1 page (A4 portrait) |
| `flyer-Pink and Black ... (Instagram Post (4_5)).png` | Ready, 1 page (from an image), portrait |
| `logo-TPTG cover.png` | Ready, 1 page, landscape (851 px, not upscaled) |

Then:
- The Collaterals list shows page-1 thumbnails and the Page images status.
- `ddev wp cptdp pages status --post_type=collateral` lists the three with `type collateral`, plus `thumbs` and `names`.
- `ddev wp cptdp pages generate --all --post_type=collateral --sync` regenerates them.
- With `pages_mode=browser`, the PDF collateral shows "Needs your browser". The box then generates in the browser and ends Ready, while the PNGs still go to the server.
- Deleting one collateral removes its `uploads/cptdp-pages/<att>-<hash>-<id>/` folder.
- Settings → Documents shows the section "Page images (Documents and Brand Collaterals)" with mode, trim and featured image. The collateral and document Page images boxes link to it.
- Each of the three collaterals now has a featured image made from page 1, its own `…-page-1` item in Media. The `/collaterals/` and `/documents/` lists show it.
- Choose a different featured image on one collateral, then run `ddev wp cptdp pages featured <id> --force`: the editor's image stays.
- Remove the file from a collateral: the generated featured image is deleted from Media.
- `ddev wp cptdp pages featured --all --post_type=document` gives the seeded documents that have files a featured image. The `featured` column in `pages status` reads `generated`.
