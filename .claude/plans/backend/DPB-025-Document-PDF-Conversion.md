# Plan: Document PDF conversion (generate page images when a document is saved)

Ticket DPB-025 (backend, **phase 1 of 2**). **One shared branch for both phases: `feat/DPB-025-DPD-013-Document-Modal-PDF-Viewer`** (this plan's commits come first, then DPD-013's, so the whole feature is testable from one checkout; the two plans stay separate documents). Phase 2 is the frontend, `../documents/DPD-013-Document-PDF-Viewer.md`, which consumes the contract below. On the shared branch these commits land first: nothing in the theme changes in this phase, and the site behaves as before until the DPD-013 commits.

Plugin: `custom-post-types-dp` (bump `0.18.0` to **`0.19.0`**).

## Goal
When a `document`'s PDF is **created or changed** (wp-admin save, REST, WP-CLI, import, Replace Media), make sure its page images exist **before any visitor opens it**, so the viewer shows stored images by default and only falls back to rendering in the visitor's browser (pdf.js, heavy) when none are valid. At save time the plugin **decides who generates them**: the server when it can, otherwise the **admin's own browser**.

## Context
- `document` CPT: `cptdp_document_file` (attachment ID, non-image/video/audio), `cptdp_embed_url`, `cptdp_filetype`, `cptdp_total_pages` (editor-entered), see `includes/post-types/document.php` (meta box save, REST meta, admin columns, `assets/admin-document.js`).
- Loader (`includes/class-loader.php`) globs `includes/post-types/`, `includes/settings/`, `includes/seeders/`; WP-CLI commands load only under WP-CLI. Settings → Documents lives in `includes/settings/documents.php` (option `cptdp_documents`).
- DDEV has Imagick (PDF, JPEG, PNG, WebP) and Ghostscript. Production tooling is unverified (Risk 1).
- Plugin suite today: 458 tests (12 skipped); 561 after the page images pipeline and the bottom trim.

## The pipeline (who makes the images, in order)
```
PDF created or changed (any hook, decision 6)
   │  clear stale images, status = pending, dedupe
   ▼
Decide the producer (decision 4: mode auto | server | browser)
   ├─ server can convert ──> WP-Cron job (non-blocking) ── ok ──> status ready
   │                                         └─ fails ───────────> status needs_browser
   └─ server cannot (or mode = browser) ──> status needs_browser
                                              │
        admin opens the document (or just saved it: the edit screen is already open)
                                              ▼
        admin's browser renders with pdf.js, uploads each page ──> status ready
                                              │ fails
                                              ▼
                                           status failed (message shown, Retry button)

Visitor opens the document later: images ready = shown; otherwise DPD-013 falls back to pdf.js in the visitor's browser.
```
There is **no public on-demand conversion route** any more (it was in the earlier draft): visitors never trigger server work, which removes the CPU-abuse surface. Documents that predate this feature are backfilled with the CLI or the Regenerate button.

## Key decisions
1. **Layout.** New `includes/documents/`: `class-page-store.php` (manifest, validity, status, paths, cleanup), `class-page-converter.php` (server conversion), `class-page-scheduler.php` (hooks, decision, cron). `includes/post-types/document-pages.php` wires meta, REST, admin UI and enqueues. The loader requires `includes/documents/*.php` before the post-types glob.
2. **Storage.** `wp-content/uploads/cptdp-pages/<attachment_id>-<hash8>/p-001.webp` (JPEG when WebP is unavailable), generated files, not media-library items, not tracked in git. Width 1400px portrait, 1800px landscape, quality 82. Removed when the file changes, the document is deleted or on `pages clear`.
3. **Manifest and status** (post meta, non-public: `show_in_rest` false, `auth_callback` requires `edit_post`):
   - `cptdp_document_pages` = `{ source_id, fingerprint, hash, count, width, height, ext, dir, generated, producer: 'server' | 'browser' }`.
   - `cptdp_document_pages_status` = `none | pending | ready | needs_browser | failed`, with `cptdp_document_pages_error` (short message shown to the admin).
   **Valid** (the viewer may use the images) only when all hold: `source_id` is the current `cptdp_document_file`; the file's fingerprint (`filesize` + `filemtime`, then `md5_file` only when the fingerprint changed) still matches; `count > 0` and, when `cptdp_total_pages` is set, `count` equals it (a mismatch makes them invalid and sets `failed` with "PDF has N pages, Total pages says M"; the editor's number is never overwritten); first and last image files exist.
4. **The decision: server or browser.** Setting **Settings → Documents → "Generate page images"** (option `cptdp_documents.pages_mode`): `auto` (default), `server`, `browser`.
   - `auto`: **server** when `CPTDP_Page_Converter::is_available()` (Imagick with a PDF delegate, or Ghostscript runnable through `proc_open`, and the functions not in `disable_functions`), else **browser**.
   - `server`: server only; if unavailable, status `failed` (message names the missing tool).
   - `browser`: always the admin's browser (useful when the host's server tooling is unreliable).
   A server conversion that fails in `auto` falls through to `needs_browser` instead of `failed`, so an admin can still finish it.
5. **Server generation (non-blocking).** The save request never converts. It schedules a single WP-Cron event `cptdp_convert_document_pages( $post_id )` (deduped with `wp_next_scheduled`) and calls `spawn_cron()`. When the host runs `DISABLE_WP_CRON`, a real system cron must call `wp-cron.php` (documented); the CLI `pages generate` is the manual path. A per-document lock (transient) stops overlapping runs. The converter: Imagick first (`readImage( file[ $i ] )` per page, density 150, flatten onto white, sRGB, strip, `Imagick::setResourceLimit`), else Ghostscript through `proc_open` with an **argument array** and `-dSAFER` (no shell string, no request input). Limits: 300 pages, 20 s per page, 120 s total, memory guard; over a limit or any failure removes partial output and sets the status per decision 4. Only the file attached as `cptdp_document_file`, only when its mime type is `application/pdf`.
6. **Hooks (every way a PDF appears or changes).**
   - `added_post_meta` and `updated_post_meta` for `cptdp_document_file` (meta box, REST, CLI, imports, scripts), and `deleted_post_meta` (clear).
   - `save_post_document` at a **late priority (99)** so the plugin's own meta box save (and `cptdp_total_pages`) has run; calls the same "ensure" routine, which is a no-op when the images are already valid. Covers a document saved with an unchanged PDF whose images were never made (legacy), drafts included (images are ready by publish); skips auto-drafts, revisions and Trash.
   - `edit_attachment` / `attachment_updated` / `update_attached_file`: when the file behind an attachment changes (Replace Media), find the documents that use it (meta query on `cptdp_document_file`) and ensure each.
   - `before_delete_post` (document) and `delete_attachment`: remove the folder and manifest.
   Documents in Trash keep their images until deleted permanently.
   Every hook ends in one routine, `CPTDP_Page_Scheduler::ensure( $post_id )`: invalid or missing images, set `pending`, clear stale files, run the decision (4), schedule or mark `needs_browser`. All writes are idempotent so several hooks firing for one save do the work once.
7. **Admin-browser generation (when the server cannot).** On the document edit screen a **"Page images" meta box** shows the status (`Ready, 24 pages, made by server/browser` / `Pending` / `Needs your browser` / `Failed: <message>`) and a **Regenerate** button. When the status is `needs_browser` (including right after the save that created it, since the editor is on that screen) `assets/admin-document-pages.js` starts automatically:
   1. loads the plugin's vendored pdf.js (`assets/vendor/pdfjs/`, same pinned build the viewer uses; see decision 9) and opens the PDF by its attachment URL (same origin);
   2. renders each page to a canvas at the target width, `toBlob` as WebP (JPEG if WebP is not supported by the browser);
   3. uploads each page with `POST cptdp/v1/documents/<id>/pages/<n>` (raw image body), a progress bar `Page 7 of 24`;
   4. calls `POST cptdp/v1/documents/<id>/pages/finish` with `{ count, width, height, ext, fingerprint }`; the server validates and writes the manifest (status `ready`, producer `browser`).
   If the admin leaves the screen, nothing is lost: status stays `needs_browser`, partial files are overwritten on the next run. The same script powers **Regenerate** (forces a re-run from the admin's browser regardless of mode).
8. **Admin REST routes (authenticated, `edit_post` on the document, cookie nonce).** `POST cptdp/v1/documents/<id>/pages/<n>` and `.../pages/finish`. The server never trusts the browser: each upload is checked with `getimagesizefromstring` (mime must be WebP or JPEG, width and height at most 3000px, body at most 2 MB), `n` must be 1..300, file names are generated by the server (`p-%03d.ext`) inside the document's own folder, the fingerprint sent at finish must equal the current file's, all pages 1..count must exist, and the count rules of decision 3 apply. A `failed` finish deletes the partial set. There is **no unauthenticated route**.
9. **pdf.js lives in the plugin.** Vendor once under `custom-post-types-dp/assets/vendor/pdfjs/` (`pdf.min.mjs`, `pdf.worker.min.mjs`, licence, `README.md` with the exact version, Apache-2.0). The admin uses it here; the theme viewer (DPD-013) reads its URL from `cptdp_document_pdfjs_urls()` instead of shipping a second copy. StPageFlip stays in the theme (only the viewer uses it).
10. **PHP API (the contract with DPD-013).** `function_exists`-guarded:
    - `cptdp_get_document_pages( int $post_id ): ?array` returns `{ pages: string[], count, width, height, ratio }` when the images are **valid**, else `null`. Never converts, never writes; cheap enough for a payload loop.
    - `cptdp_document_pdf_url( int $post_id ): string`: attachment URL only for a PDF, else `''`.
    - `cptdp_document_pdfjs_urls(): array` returns `{ module, worker }` URLs of the vendored pdf.js.
11. **Admin list column "Pages"** (`document` list): `Ready (24)`, `Pending`, `Needs browser`, `Failed`, `-` (no PDF); sortable not needed. Failed and needs-browser rows link to the edit screen.
12. **WP-CLI.** `ddev wp cptdp pages generate [<id>|--all] [--force] [--sync]` (backfills legacy documents; always uses the server converter, `--sync` skips cron), `pages clear [<id>|--all]`, `pages status` (per document: status, producer, count, valid or stale). Loaded only under WP-CLI.
13. **Settings page field.** One select added to Settings → Documents (`pages_mode`), sanitised against the three values, read through `cptdp_get_documents_settings()` (default `auto`). Plugin tests extend `tests/settings/`.
14. **Other Settings → Documents fields shipped with this work** (all in `cptdp_documents`, read via `cptdp_get_documents_settings()`): `trim_bottom` (boolean, default true: trim empty bottom margin, see the trim decision above) and `selectable_text` (boolean, default false: the viewer draws pages with pdf.js plus a text layer so text can be selected, with a lag warning next to the checkbox; the viewer behaviour is described in `../documents/DPD-013-Document-PDF-Viewer.md`).

14. **Bottom trim (added after the first manual check).** Pages that end well above their bottom edge showed a band of white. Generation now also finds the empty strip and stores ONE crop per document; the viewer hides it at display time and the image files are never changed.
    - **Analysis** (`CPTDP_Page_Analyzer`, `includes/documents/class-page-analyzer.php`): each page image is scaled to 300px wide (Imagick, GD fallback) and only its lower 30% is read, row by row from the bottom. A row is blank when at least 99.8% of its pixels have every channel within 8/255 of white (survives WebP/JPEG noise); the first row with content stops the scan; a full-bleed colour page therefore gives 0.
    - **Document crop** = the SMALLEST blank share over ALL pages (every page of a book keeps the same size), minus a 2.5% kept margin; under 2% it is 0; capped at 25%. A footer, page number or any mark on any page keeps the whole bottom, so printed footers are never cut (and are not removed by this feature either).
    - **Storage and API:** `crop_bottom` (float 0..0.25) in the manifest. `cptdp_get_document_pages()` returns `crop_bottom` and a `ratio` already for the visible part (`width / ( height * ( 1 - crop ) )`). Invalid stored values count as 0. The setting **Settings → Documents "Trim empty space at the bottom of pages"** (`trim_bottom`, default on) makes the API return crop 0 without regenerating.
    - **Producers:** the server conversion runs the analysis on the files it wrote. The admin-browser script runs the same maths on a downscaled copy of each canvas (`getImageData`) and sends `cropBottom` in the finish call; the route stores it when it is a number from 0 to 0.25, otherwise 0. The thresholds are named constants in both files and a test compares them.
    - **CLI:** `wp cptdp pages recrop [<id>|--all]` recomputes the crop from the stored images (no conversion); `pages status` has a `crop` column.

## Risks / open points
1. **Production tooling unknown.** If the host has no Imagick PDF or Ghostscript, `auto` falls to the admin's browser (works, but depends on an admin opening the document). Confirm the host, or accept.
2. **Public on-demand route removed** (a change from the earlier draft). Visitors on devices that the viewer's render gate refuses, opening a document with no images yet, see "can't be previewed" (Download works). Backfill legacy documents once with `pages generate --all` and the gap closes. If you want the lazy public route back, it is a small add (lock, cooldown, 202/429) but it reopens the CPU-abuse surface.
3. **Documents created without anyone opening wp-admin** (CLI, REST, import) on a host with no server tooling stay `needs_browser` until an admin opens them. The list column and a one-line admin notice on the document list ("N documents need page images, open them to generate") make that visible; a bulk generator screen is a follow-up.
4. **WP-Cron reliability.** Low-traffic sites may run the job late; `DISABLE_WP_CRON` needs a system cron. Mitigation: `spawn_cron()` right after scheduling and the CLI.
5. **Large or hostile PDFs:** limits in decision 5; admin uploads validated per decision 8.
6. **Disk use:** about 100-200 KB per page as WebP; folders removed on invalidation. `pages status` finds strays.
7. **Hook storms:** several hooks fire for one save; `ensure()` is idempotent and the cron event is deduped.
8. **Tests need a fixture PDF** (minimal hand-written PDF bytes generated in the test); conversion tests skip when no tool exists (the suite already skips 12). The admin JS and the pdf.js rendering are manual (no browser runner).

## Steps
One conventional commit each, scope `custom-post-types-dp`, **tests never in a feature commit**, suite green at every commit.

### Step 1 (commit 1 feat, 2 test): page store, status and API
`class-page-store.php`, meta registration (manifest, status, error), validity rules, paths, cleanup on delete, loader glob, `cptdp_get_document_pages()`, `cptdp_document_pdf_url()`. Tests (`tests/documents/test-page-store.php`): valid manifest returns pages and ratio; invalid on changed fingerprint, changed attachment, `cptdp_total_pages` mismatch (status `failed` with the message), missing first/last file; none when never made; cleanup on delete; meta not in REST; PDF URL only for PDFs.

### Step 2 (commit 3 feat, 4 test): server converter
`class-page-converter.php` with `is_available()`, Imagick, Ghostscript, limits, partial cleanup, WebP/JPEG choice. Tests: N images and a valid manifest (producer `server`) for a fixture PDF; page cap; `unavailable` when forced off; cleanup on failure; landscape fixture gives `width > height`; non-PDF refused. Skips cleanly with no tool.

### Step 3 (commit 5 feat, 6 test): save-time scheduler and the decision
`class-page-scheduler.php`: the hooks of decision 6, `ensure()`, the mode decision of decision 4, the cron event and lock, status transitions, `pages_mode` setting, admin column, the list notice. Tests: every hook reaches `ensure()` once (meta added, updated, file replaced via attachment edit, `save_post_document`), idempotent, drafts included and auto-drafts/revisions skipped, `auto` picks server when available and browser when not (availability filterable for the test), `server` mode failing when unavailable, server failure falls to `needs_browser` in `auto`, stale files cleared on change, dedupe of the cron event, setting sanitised, column output.

### Step 4 (commit 7 feat, 8 test): admin-browser generation
Vendored pdf.js in `assets/vendor/pdfjs/` and `cptdp_document_pdfjs_urls()`, the REST routes of decision 8, the "Page images" meta box and `assets/admin-document-pages.js` (auto-start, progress, Regenerate, error and retry). Tests: routes require `edit_post` and a nonce, reject non-images, oversize, wrong mime, bad `n`, bad fingerprint, missing pages; finish writes a valid manifest with producer `browser`; count mismatch fails and cleans up; file names are server generated; meta box renders each status; pdf.js files exist and the README version matches.

### Step 5 (commit 9 feat, 10 test): WP-CLI and version
`includes/cli/class-pages-command.php` (`generate`, `clear`, `status`), plugin `Version` `0.19.0`. Tests where practical (registered, `--all` selection, `--force`, `--sync`, status classification).

### Step 6 (commit 11): docs
`docs(agents): document the document page images pipeline`: Overview (meta, status, classes, hooks, decision, routes, API, CLI, setting, 0.19.0), the contract for DPD-013, commands, test totals.

### Step 7 (commit 12): plan
`docs(plans): add DPB-025 plan`. On the shared branch this and the DPD-013 plan commit go **last of all** (one `docs(plans)` commit may add both).

## Verification
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit   # unchanged, must stay green
```
Manual (DDEV has server tooling; to test the browser route set the mode to `browser` in Settings → Documents):
1. **Server route:** create a document with a 4-page PDF and save. Status goes `Pending` then `Ready (4)` within a few seconds (run `ddev wp cron event run --due-now` if cron is idle); `uploads/cptdp-pages/<id>-<hash>/p-001.webp..p-004.webp` exist; `ddev wp eval 'var_dump( cptdp_get_document_pages( <id> ) );'` returns 4 URLs, width, height, ratio. The save request itself returns quickly (no conversion in it).
2. **Replace the PDF** (edit the document, pick another file; and separately use Replace Media): old folder removed, status back to `Pending`, then `Ready` with the new count.
3. **Count mismatch:** set Total pages to a wrong number: status `Failed: PDF has 4 pages, Total pages says 6`; images are not used (`cptdp_get_document_pages()` is `null`); fixing the number and saving makes it `Ready`.
4. **Browser route:** set mode to `browser`, save a document: the meta box says `Needs your browser`, the script starts by itself, a progress bar counts pages, the status becomes `Ready` (producer `browser`); reload keeps it. Close the tab halfway, reopen the document: it resumes and completes.
5. **Fallback chain:** mode `auto` with Imagick and `gs` unavailable (rename in a throwaway shell): status goes straight to `Needs your browser`. With a corrupt PDF: server fails, then `needs_browser`, then the browser fails too, ending in `Failed` with a message and a working Retry.
6. **Security:** as a logged-out user and as a user without `edit_post`, POST to both routes: refused. As an editor, upload a text file named `.webp`, a 5 MB image, page `0` and page `999`: all refused.
7. `ddev wp cptdp pages generate --all --sync` backfills legacy documents; `pages status` lists them valid; `pages clear --all` removes everything; permanently deleting a document removes its folder.
8. Documents list: the Pages column reflects each status; the notice appears when documents need the browser.
9. Both suites green; the front end is unchanged (nothing in the theme is touched).

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `feat(custom-post-types-dp): add document page image store and status`
2. `test(custom-post-types-dp): cover the document page store`
3. `feat(custom-post-types-dp): convert document pdf pages to images on the server`
4. `test(custom-post-types-dp): cover the page converter`
5. `feat(custom-post-types-dp): generate document page images on save`
6. `test(custom-post-types-dp): cover save-time page generation`
7. `feat(custom-post-types-dp): generate document page images in the admin browser`
8. `test(custom-post-types-dp): cover admin browser page generation`
9. `feat(custom-post-types-dp): add pages wp-cli command and bump version to 0.19.0`
10. `test(custom-post-types-dp): cover the pages command`
11. `docs(agents): document the document page images pipeline`
12. `docs(plans): add DPB-025 plan`

One PR for the shared branch, covering both tickets: `feat(documents): DPB-025 DPD-013 document PDF conversion and open-book viewer` (template `.github/pull_request_template.md`). Call out in the description that commits 1-10 of DPB-025 are the backend (plugin 0.19.0) and the DPD-013 commits are the frontend, so they can be reviewed in two passes.

## As built (differences from the plan above, after review)
Read this section as the source of truth where it disagrees with the text above. Final plugin suite: 593 tests, 12 skipped.
- **Routes:** four admin-only routes, not two: `GET cptdp/v1/documents/<id>/pages` (state, polled by the meta box), `POST .../pages/start` (begins a browser run, returns a **run token**; answers 409 while another tab holds a run unless `force` is sent; clears the folder), `POST .../pages/<n>` and `POST .../pages/finish` (both need the run token while a run is active). `finish` accepts `cropBottom` and `{ error }`; width and height are derived on the server from the uploaded images (not trusted from the client); it deletes files beyond `count`, rejects pages whose shape differs from page 1 by more than 3%, and releases the run claim on error.
- **Engines:** Imagick first; if Imagick fails on the engine itself (for example an ImageMagick policy that denies PDF) and Ghostscript exists it retries with Ghostscript; limit and timeout errors are not retried. Ghostscript outputs JPEG at 170 dpi, runs with one page beyond the cap so a 400-page PDF is refused exactly as with Imagick, and its pipes are drained while it runs. The cap is filterable (`cptdp_pages_max_pages`); `cptdp_pages_imagick_source` is a test seam. A PDF must start with `%PDF-` and Imagick reads it as `PDF:`.
- **Scheduling:** a replaced PDF during a running conversion is detected (`convert()` returns the source token; `record_conversion()` deletes the stale folder and queues the new PDF); a held lock reschedules the job one minute later, up to 10 times, then hands over to the browser (`failed` in server mode). The attachment hook is `attachment_updated`-style (fires after `_wp_attached_file` is saved), replacing the `update_attached_file` filter. The CLI spawns WP-Cron once at the end.
- **Storage:** folder name is `<attachment_id>-<hash8>-<document_id>` (one folder per document, so documents sharing an attachment cannot delete each other's images; older `<attachment_id>-<hash8>` sets still work). A stored `dir` is only used if it matches `^\d+-[0-9a-f]{8}(-\d+)?$`, and `delete_dir()` only deletes direct children of `cptdp-pages`. When the mtime drifts but the hash matches, the new fingerprint is written back once (no repeated `md5_file`).
- **Trim:** `class-page-analyzer.php` computes `crop_bottom` (300px-wide copy, lower 30%, row blank at 99.8% within 8/255 of white, minimum across all pages minus a 2.5% margin, ignored under 2%, capped at 25%, a stored value above 0.25 is treated as 0); stored in the manifest, the API's `ratio` already accounts for it, Settings → Documents `trim_bottom` (default on) turns it off without regenerating; the admin script computes the same value (constants are parity-tested); CLI `pages recrop`, and `pages status` shows `crop`.
- **Settings → Documents:** `pages_mode`, `trim_bottom`, `selectable_text` (the last drives the viewer, see DPD-013). A partial REST update keeps the saved values of missing keys; only the settings form (`option_page`) treats an unticked box as off.
- **Admin UI labels:** list column and meta box are "Page images", statuses read "Ready, N pages", button "Regenerate in this browser" (no separate Retry button); the meta box polls `pending` (30 x 4 s) and auto-starts on `needs_browser`; the admin tab shows "Another tab is already generating these pages" on a 409. `pages status` shows `valid` yes/no.
- **Unrelated fix included:** `contact.php` and `documents.php` called `add_settings_section()` on `rest_api_init` (fatal on every REST request on `main`); registration of the setting and of the admin sections are now separate functions (`*_register_sections`).
- **Not verified in a browser:** `assets/admin-document-pages.js` (including the run-token handling) and the CLI spawn change.
