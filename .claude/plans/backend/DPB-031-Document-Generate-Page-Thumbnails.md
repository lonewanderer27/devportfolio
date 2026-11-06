# Plan: Document page thumbnails (DPB-031, Document - Generate Page Thumbnails)

Plugin: `custom-post-types-dp` 0.22.0 -> **0.23.0** (shared with DPB-030, which shipped in the same release). **No theme change** (the theme side is DPD-020, which reads `thumbs`). Branch `feat/DPB-031-Document-Generate-Page-Thumbnails`, cut from `main`. Builds on DPB-025 (PDF page images), DPB-026/027/028 (PowerPoint, Excel, Word conversion). Split out of DPB-030 (page names) because each is big enough on its own.

> **Status: implemented, uncommitted.** Plugin suite green (726 tests); `pages thumbs --all` run on the 14 dev documents (2 to 10 KB per thumbnail, 560 KB in total); the list cell HTML was checked, the wp-admin list was not looked at by hand. See **As built** at the end.

## Goal
1. Make **one small thumbnail per page** when a document's pages are made, stored with the page set and returned by `cptdp_get_document_pages()`. DPD-020's Pages gallery uses them.
2. Fix the **empty Thumbnail column on the wp-admin Documents list** (every row shows `—`, owner screenshot): the column shows **the same thumbnail file** as the gallery tile of that page in the mobile Pages sheet (DPD-020), i.e. page 1, so the admin and the sheet always agree.
Visitors see nothing new from this ticket.

## Context (what exists, do not rebuild)
- The pipeline: `CPTDP_Page_Scheduler::ensure()` -> WP-Cron job -> `CPTDP_Page_Converter::convert()` (Imagick, then Ghostscript) -> `CPTDP_Page_Store` (folder `uploads/cptdp-pages/<attachment_id>-<hash8>-<document_id>/p-001.webp`, manifest and status in non-REST meta `cptdp_document_pages`, `cptdp_document_pages_status`, `cptdp_document_pages_error`). Office files go through `CPTDP_Pptx_Converter::to_pdf()` to a temporary PDF that is removed afterwards. Browser-made sets (`needs_browser`, PDF only) are drawn by vendored pdf.js in the admin and uploaded through `class-page-rest.php`.
- Page images are **1400px wide (1800 landscape and spreadsheet pages)**: far too big for a gallery of 24 small tiles on a phone (about 5 MB), hence separate small copies.
- Visitors never trigger server work; this stays true (everything runs at conversion time or in the admin).
- **Admin today (read from `includes/post-types/document.php`):** the Documents list has a **Thumbnail** column (`cptdp_document_column_content()`, `case 'thumbnail'`) that prints `get_the_post_thumbnail( $post_id, array( 50, 50 ) )`, i.e. the **featured image**, and `&mdash;` when there is none. The shelf cover is pure CSS (`cptdp_cover_style`), so no document needs a featured image and none has one: the column is empty for every row although the page images exist. The edit screen's Thumbnail (featured image) box is empty for the same reason.

## Behaviour

### Thumbnails
- One small webp per page, **about 240px wide** (landscape pages and sheets scale to the same width), quality tuned to roughly 8-15 KB, written **when the page images are made** (server conversion, and the admin browser run).
- Same folder and lifecycle as the pages: `t-001.webp` beside `p-001.webp`; deleted with the set (the existing "only direct children of `cptdp-pages` are ever deleted" rule), validated by name pattern, rebuilt on regenerate, stale on fingerprint change.
- Made from the already open page image (no second render). **Bottom trim:** thumbnails are made from the **uncropped** page, like the stored images (the crop is a view setting, `crop_bottom`), so changing Settings -> Documents -> Trim bottom needs no regeneration; the theme applies the crop.
- **Browser-made sets:** the admin script draws the small copy from the page it already rendered and uploads it with the page (**decided: yes**, an extra `thumb` part on the existing page route).
- A failed thumbnail never fails the set: the images stay `ready`, `thumbs` is `[]`.

### Storage and API
- Manifest key (inside non-REST meta `cptdp_document_pages`): `thumb_width` (int, 0 = none). **No admin field, no new meta key, no REST exposure**, valid only while the PDF fingerprint matches, cleared with the set.
- `cptdp_get_document_pages()` returns `thumbs`: array of URLs, one per page, or `[]` when any is missing (a half set is never used), next to the existing `images`.
- A set with images but no thumbnails is **valid** and is **not** re-queued (that would regenerate every old document on its next save); it has `thumbs = []`. Documents made before this ticket get thumbnails after `ddev wp cptdp pages thumbs --all` (fast, no reconversion; see Performance).
- WP-CLI: `pages status` gains a `thumbs` column (count); `pages generate` is unchanged; new `pages thumbs [<id>|--all] [--force]` backfills from the stored images.

### Admin: the page-1 thumbnail (confirmed screen: the Documents list)
- **One image, two places.** The list's Thumbnail cell uses `thumbs[0]`, the very file the Pages sheet gallery draws for page 1 (`t-001.webp`); no second image is made for the admin.
- **Column header wraps** (screenshot: `Thumbn / ail`): the column is 60px wide and the header breaks mid-word. Widen it (about 90px) and keep the 50px-wide image inside it; the existing inline style in `document.php` (`.column-thumbnail{width:60px}`) is the place to change.
- The list's **Thumbnail** column shows, in order: the **featured image** if there is one (a real image still wins), else the **page-1 thumbnail** (`thumbs[0]`) at 50px wide, else (old set without thumbs) the **first stored page image** scaled by CSS (`width: 50px`, `loading="lazy"`) so the list is right **before** a regenerate (**decided: yes**, admin only and the list is paged at 20), else `&mdash;` (no file, not converted yet, failed). Alt text empty (decorative; the title is in the next column); URLs escaped.
- The edit screen's **Page images** meta box (DPB-025, `post-types/document-pages.php`) shows the page-1 thumbnail beside its status line when the set is ready. The featured-image box is left as it is.
- **No featured image is created automatically** (see Decision 3).
- One helper, `cptdp_document_admin_thumb_url( $post_id )`, holds the choice so the list and the box agree.
- **Only visible thumbnails are fetched** (owner), also in the admin: the list cell's `<img>` is `loading="lazy"` and `decoding="async"` with fixed `width`/`height` attributes (the list is paged at 20, so at most 20 requests, none for rows scrolled out of view). The theme-side rule for the Pages sheet (data-src plus an `IntersectionObserver`, about one row ahead, nothing in List view, never the pages above the open page) is in DPD-020. This ticket's part is to make that possible: thumbnails are **individually addressable files with stable URLs** (`t-NNN.webp`, no zip, no sprite sheet, no base64 in the payload) and the payload only carries the **URL list**, never the image bytes, so a 300-page document costs a few KB of JSON and zero image requests until a tile is shown. Sprite sheets are rejected because they would force every tile's pixels to download at once.
- **Not changed:** the front end. The shelf still draws the CSS cover.
- **Per kind** (the list in the screenshot mixes them): PDF and Word show page 1; Excel shows sheet 1; PowerPoint shows slide 1, but only once LibreOffice has converted it (a `.pptx` with status `none` or `failed` keeps `&mdash;`). Those PowerPoint rows also show `—` under Pages today; that is the existing total-pages field and is not changed here.

## Architecture

| File (`includes/` unless noted) | Change |
|---|---|
| `documents/class-page-converter.php` | Write `t-NNN.webp` for every page from the open page image (`thumbnailImage`, strip metadata, webp quality from the spike), return `thumb_width`. Spreadsheet pages (1800x1273 canvas) and landscape slides use the same width. A failure is logged to `cptdp_document_pages_error` only as a note and never changes the status. |
| `documents/class-page-store.php` | Manifest `thumb_width`; `thumb_url()`; deletion, folder validation and fingerprint handling extended to `t-` files (name pattern `t-\d{3,}\.webp`); `cptdp_get_document_pages()` returns `thumbs`. |
| `documents/class-page-scheduler.php` | A set with images and no thumbs stays valid (no re-queue). |
| `documents/class-page-rest.php` + `assets/admin-document.js` | Browser runs draw and upload the thumbnail with each page; each part validated (type, size cap, name pattern) like pages. PDF only (presentations never use the browser route). |
| `post-types/document.php` | `case 'thumbnail'` uses `cptdp_document_admin_thumb_url()`. |
| `post-types/document-pages.php` | Page images box shows the thumbnail. |
| `cli/` (pages command) | `pages status` `thumbs` column; new `pages thumbs` subcommand that resizes the stored `p-NNN.webp` files (shares the thumbnail writer with the converter). |
| `custom-post-types-dp.php` | Version bump. |
| `tests/documents/` and `tests/post-types/test-document.php` | New and extended tests (see steps). |

## Performance (cheap to make, cheap to serve, cheap to send)
**Making them**
- **Downscale from the in-memory raster, not from the stored webp** (no double lossy compression, crisper at 240px), with a Lanczos filter and a light unsharp pass; encode once. Width **240px is right for 3 columns at 2x on a 390px phone** (about 110 CSS px per tile); do not make a second size.
- **Encoder settings for tiny files:** webp quality about 60-70, `webp:method=6` (slowest, smallest; fine for 240px), metadata and colour profile stripped, `webp:auto-filter` on; target at most 8 KB, hard cap 20 KB (a larger result is re-encoded once at lower quality). Settled in the spike with real pages.
- **Stream, don't hold:** one page at a time, `clear()`/`destroy()` the Imagick object after each, so a 300-page document does not hold 300 rasters. Thumbnails are written after all page images, inside the same cron run, within the existing time budget; if the budget runs out the set stays `ready` with `thumbs = []` and a note, never half a set.
- **Backfill without re-converting:** a new command `ddev wp cptdp pages thumbs [<id>|--all]` makes thumbnails **from the stored `p-NNN.webp` files** (resize only: no LibreOffice, no Ghostscript, no pdf.js, seconds not minutes), sequentially, one document at a time, memory freed between pages. It replaces `pages generate --all --force` as the backfill for this ticket. Idempotent: a document that already has all thumbnails is skipped unless `--force`.
- **Page size known up front:** store `thumb_width` and `thumb_height` (and use the page `ratio`) so tiles reserve their box before the image arrives: no layout shift, no re-flow of a 3x3 grid.

**Serving them**
- **Immutable URLs, far-future caching.** The set folder name already carries the file fingerprint (`<attachment_id>-<hash8>-<document_id>`), so a changed file means a new URL. Thumbnails can therefore be cached **forever**: document the host header (`Cache-Control: public, max-age=31536000, immutable` for `uploads/cptdp-pages/**.webp`; DDEV nginx snippet and the equivalent `.htaccess`/Apache note in the vendored README of the plugin docs). A visitor downloads each thumbnail at most once, ever.
- **Small, separate, addressable files** (already decided): no sprite, no base64, no zip; HTTP/2 multiplexing handles the few requests that are made.
- **Never the full-size pages for the gallery**, and the theme never falls back to them for tiles.

**Sending the list**
- The modal payload carries **every published document**, so URL lists would bloat the Documents page HTML (300 URLs for one big document). `cptdp_get_document_pages()` still returns the full `thumbs` array for PHP callers, but the theme sends a **compact descriptor** (`{ base, ext, count, width, height }`, the page URL is `base + 't-' + pad( n, 3 ) + ext`) when the URLs follow the pattern, which they always do (guard with a check, else send the list). About 100 bytes per document instead of tens of KB. Specified in DPD-020.
- Documents with no thumbnails send nothing (no empty keys).

**Admin**
- List cell: `loading="lazy"`, `decoding="async"`, `fetchpriority="low"`, explicit `width`/`height`; page-1 thumbnail only (one tiny file per row).
- The old-set fallback (full-size page 1 scaled by CSS) is temporary and disappears after `pages thumbs --all`.

## Decisions and rationale
1. **Server-made, small, optional thumbnails.** Their absence never invalidates a set (older documents keep working); their size makes a 24-page gallery cost about 250 KB instead of about 5 MB.
2. **Uncropped source**, so the Trim bottom setting stays a pure view setting.
3. **The admin thumbnail is a fallback, not a featured image.** Creating a real featured image per document would add Media Library attachments, orphans on delete and a second source of truth that goes stale when the file is replaced. Reading the page-1 thumbnail we already store is free and always in step with the file.
4. **One helper for the admin choice**, so the list column and the meta box cannot disagree.

## Risks and open points
1. **Disk and time:** about 10 KB per page and one extra resize per page, in the existing cron job; the 120 s soffice timeout is not affected (thumbnails are made after the PDF exists).
2. **Admin list weight** for not-yet-regenerated documents (full-size image scaled by CSS) until `pages thumbs --all` is run (quick, no reconversion).
3. **Quality of tiny pages:** text is unreadable at 240px by design; sheets and wide slides may look pale. Check one of each kind.
4. **Browser-made sets** cost one more small upload per page; the vendored pdf.js already has the page rendered, so no extra render.
5. **Replaced file mid-conversion:** thumbnails follow the same stale-output deletion as the images (the existing re-queue rule).

## Steps (one conventional commit each, scope `custom-post-types-dp`; tests never inside a feature commit; plugin and theme suites green at every commit)
0. **Spike (no commit):** make 240px webp thumbnails of a PDF page, a slide, a sheet page and a Word page on the DDEV box; settle width and quality for roughly 10 KB; check the Thumbnail column header no longer wraps at the new width. Record the choice here.
1. `feat(custom-post-types-dp): make small page thumbnails` (`t-NNN.webp`, manifest `thumb_width`/`thumb_height`, one-page-at-a-time memory use, time budget, `pages thumbs` backfill, store/cleanup/validation, API `thumbs`, `pages status` column).
2. `test(custom-post-types-dp): cover page thumbnails` (written for each kind, size cap and one re-encode, `pages thumbs` makes them from stored images without calling the converter and skips complete sets, deleted with the set, name pattern validated, partial set gives `thumbs = []`, old sets stay valid and are not re-queued, regenerate backfills, uncropped source).
3. `feat(custom-post-types-dp): upload thumbnails from the admin browser run` (REST part, admin script, validation).
4. `test(custom-post-types-dp): cover browser-made thumbnails` (valid part stored, wrong type/size/name refused).
5. `feat(custom-post-types-dp): show the page-1 thumbnail in the document admin` (list column fallback, wider column so the header does not wrap, `cptdp_document_admin_thumb_url()`, Page images box).
6. `test(custom-post-types-dp): cover the document admin thumbnail` (featured image wins, thumbnail fallback, old-set fallback, empty `&mdash;`, escaping, box shows it only when ready).
7. `docs(agents): document page thumbnails and the admin thumbnail` (Overview section, manifest key, `pages status`, backfill command, version, test totals).

Verify: `ddev wp cptdp pages thumbs --all`, then `ddev wp cptdp pages status` shows thumbs counts; open a thumbnail URL and check its size (about 8 KB, never over 20 KB) and its cache header (immutable on a host that has the snippet); the Documents list and the edit screen show the page-1 thumbnail (a document with a real featured image still shows that one); delete a document and confirm the `t-` files go with the folder.

## Decided by the owner
1. Browser-made page sets **also upload thumbnails** from the admin script (step 3 stays in).
2. Documents not yet regenerated **fall back to the first full-size page image scaled by CSS** in the admin list.

## Answered by the owner
3. The missing thumbnail is on the **wp-admin Documents list** and must be **the same thumbnail as the Pages sheet's thumbnail view** (page 1). The media picker is out of scope.

## Questions for the owner
None open.

## As built (DPB-031)

**Files**
- New: `includes/documents/class-page-thumbs.php` (`CPTDP_Page_Thumbs`: `WIDTH` 240, `QUALITY` 65, `QUALITY_LOW` 45, `MAX_BYTES` 20480, `filename()`, `write()`, `make_for_folder()`, `exist()`, `delete()`), `tests/documents/test-page-thumbs.php` (20 tests, group `page-thumbs`).
- Changed: `class-page-converter.php` (thumbnails written from the page already in memory in the Imagick loop; Ghostscript sets and failures fall back to `make_for_folder()`; a failure removes all thumbnails and never fails the set), `class-page-store.php` (`get_pages()` returns `thumbs`, `thumb_width`, `thumb_height`; `make_thumbs()` backfill), `class-page-rest.php` (`?thumb=1` upload, `checked_thumbs()`, `[pt]-*` cleanup of strays), `assets/admin-document-pages.js` (`thumbBlob()`, uploads each thumbnail after its page, never fails the run), `post-types/document-pages.php` (`cptdp_document_admin_thumb()`, `cptdp_document_admin_thumb_html()`, localised `thumbWidth`, thumbnail in the Page images box), `post-types/document.php` (Thumbnail column: featured image, else page 1, else a dash; column width 90px), `cli/class-pages-command.php` (`pages thumbs [<id>|--all] [--force]`, `thumbs` column), `custom-post-types-dp.php` (0.23.0, shared with DPB-030).

**Differences from the plan**
- Thumbnails are encoded from the downscaled 1400px raster in memory, not from the full-resolution render; Lanczos resize plus a light unsharp, `webp:method=6` and `webp:auto-filter`, metadata stripped; one re-encode at quality 45 when over 20 KB.
- `thumb_width`/`thumb_height` come from the first thumbnail; the existence check in `get_pages()` looks only at the first and last file (`CPTDP_Page_Thumbs::exist()`), so a hole in the middle is not detected there.
- The browser upload accepts thumbnails up to 280px wide and 100 KB; the finish step keeps them only when every page has one of the right type and size, otherwise it deletes them and tries `make_for_folder()` (which only works when the server has Imagick). A failed thumbnail upload never stops the run.
- The Page images box shows an 80px thumbnail only when the status is `ready`.
- The old-set fallback in the list (first full-size page image scaled by CSS) is live until `pages thumbs --all` is run.
- Cache headers: not set by the plugin. They are a host setting, documented in `AGENTS.md` (`Cache-Control: public, max-age=31536000, immutable` for `uploads/cptdp-pages/**.webp`).
- No step-0 numbers were recorded beyond the dev-site results below.

**Results on the dev site:** every document got `t-001...` (webp, 240px wide; 311, 339, 170, 135 or 124 px tall by page shape); sizes 1.9 to 10.4 KB for page 1. The `/documents/` payload carries `thumbs` as `{ base, ext, count, width, height }`, about 100 bytes per document, and the whole payload is about 18 KB for 14 documents. The list cell renders `<img ... width="50" height="65" loading="lazy" decoding="async" fetchpriority="low">`.

**Tests (20):** one thumbnail per page with width and size caps, uncropped shape, landscape width, API order and location, a missing file means no thumbnails, an old set is valid, clearing removes them, backfill without the converter (`made`, `skipped`, forced), nothing to do, a broken image leaves no partial set, the admin picture is page 1 of the thumbnails or the old fallback or none, the list cell HTML, a featured image wins, the Page images box only when ready, the browser upload (pages plus thumbnails, an oversized thumbnail refused, a missing thumbnail never leaves half a set, stray thumbnails past the count removed).

**Decided and answered:** browser-made sets upload thumbnails (yes); the old-set fallback in the list is the scaled first page image (yes); the missing thumbnail was the wp-admin Documents list (confirmed by the owner).

**Not done / still open:** the immutable cache header is not configured on any host; the wp-admin list was not viewed in a real browser; no thumbnail was made by a real browser run (admin script) by hand.
