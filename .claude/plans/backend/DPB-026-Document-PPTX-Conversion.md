# Plan: Document presentations (PPTX, PPT, ODP): server conversion to page images, editor warning and the Native viewer settings

Ticket DPB-026 (backend, **phase 1 of 2**). **One shared branch for both phases: `feat/DPB-026-DPD-026-Document-Modal-PPTX-Viewer`** (this plan's commits come first, then DPD-026's, as with DPB-025 and DPD-013; the plans stay separate documents). Phase 2 is the frontend, `../documents/DPD-026-Document-Modal-PPTX-Viewer.md`, which shows converted slides in slide wording and draws a `.pptx` with the bundled native viewer when the setting below is on. Builds on DPB-025 (`DPB-025-Document-PDF-Conversion.md`, the page images pipeline, plugin 0.19.0). Nothing in the theme changes in this phase.

Plugin: `custom-post-types-dp` (bump `0.19.0` to **`0.20.0`**).

## Goal
1. A `document` whose file (`cptdp_document_file`) is a presentation, **`.pptx`, `.ppt` or `.odp`**, gets page images exactly like a PDF does (one image per slide, under `uploads/cptdp-pages/`, shown in the open-book viewer), made on the server with LibreOffice.
2. Because a presentation is not a PDF the conversion is **never perfect**, so the editor is **warned when they pick or upload one** that the result may differ and that the best result is to export to PDF and upload that PDF. The warning never blocks the upload.
3. Settings → Documents gets **one "Native viewer" section** with a checkbox per format (PDF and PowerPoint today) that lets our own viewer take over the rendering: text can be selected, the file is drawn from the original, at the expense of performance. DOCX and XLSX will be added by a later ticket.

In this plan **"presentation"** means `pptx`, `ppt` or `odp`.

## Context
- DPB-025 pipeline: every change ends in `CPTDP_Page_Scheduler::ensure()`, which picks a producer (server via WP-Cron, or the admin's browser with pdf.js); `CPTDP_Page_Converter::convert()` turns a PDF into WebP/JPEG pages (Imagick, then Ghostscript) and `CPTDP_Page_Analyzer` measures the bottom trim. Everything was keyed to a PDF: `pdf_attachment_id()` returned 0 unless the mime type was `application/pdf`.
- `cptdp_document_sanitize_file()` already accepts any non-video/audio attachment and WordPress allows these uploads by default (`pptx` `application/vnd.openxmlformats-officedocument.presentationml.presentation`, `ppt` `application/vnd.ms-powerpoint`, `odp` `application/vnd.oasis.opendocument.presentation`), so presentations were uploadable but never got images.
- A browser cannot render presentations through pdf.js, so unlike PDFs there is **no admin-browser producer** (the native viewer of DPD-026 draws them at view time instead).
- DDEV had Imagick and Ghostscript but **no LibreOffice**; production tooling is unknown (Risk 1).
- Settings → Documents (`includes/settings/documents.php`) had `show_book_title`, `pages_mode`, `trim_bottom` and `selectable_text` (the PDF text layer, shipped in 0.19.0).

## The pipeline for a presentation
```
Presentation created or changed (same hooks as PDF, DPB-025 decision 6)
   │  clear stale images, dedupe                          (ensure(), one new branch)
   ▼
LibreOffice available? ── no ──> PowerPoint box ticked and a .pptx? ── yes ──> status none (the native viewer does the work)
   │                                   └─ no ──> status failed, message recommends uploading a PDF (never needs_browser)
   │ yes: status pending
   ▼
WP-Cron job (same event, same per-document lock, same retries as PDF; one soffice at a time site-wide)
   1. inspect the file by content (decision 5)
   2. soffice --headless --convert-to pdf  ──> temporary PDF in a throwaway folder and profile
   3. temporary PDF ──> the unchanged PDF code (Imagick/Ghostscript) ──> p-001.webp ...   (no trim for slides)
   4. manifest: producer 'server', source_kind, converted_via 'libreoffice'
   5. delete the temporary folder, always
   ▼
status ready     (any failure ──> status failed with a short message that recommends a PDF; Regenerate retries on the server)
```
The save request never converts. Visitors never trigger conversion.

## Key decisions
1. **Engine: LibreOffice Impress, headless.** `soffice --headless --norestore --nologo --nodefault --nolockcheck -env:UserInstallation=file://<fresh profile> --convert-to pdf:impress_pdf_Export --outdir <dir> <file>` through `proc_open` with an **argument array** (no shell string), a minimal environment (`PATH`, a writable `HOME`/`TMPDIR`, `LANG`), pipes drained while it runs, a hard timeout (120 s, filter `cptdp_pptx_timeout`) that kills the process, and a result that must be a file starting with `%PDF-`. The input is copied to `input.<kind>` inside a `cptdp-pptx-<random>` folder (LibreOffice picks its import filter from the extension; an odd attachment name never reaches the command line). Macros stay disabled and macro-enabled formats are refused.
2. **Reuse the PDF pipeline, do not fork it.** `CPTDP_Page_Store` gets `kind_of_attachment()` (mime type, or the extension when the host reports a generic type such as `application/zip`), `source_attachment_id()`, `source_kind()` and `is_presentation_kind()`; `get_source()` returns `kind` (`pdf|pptx|ppt|odp`) and the same token (`attachment:size-mtime`), so replacing the file invalidates images as before. `pdf_attachment_id()` and `cptdp_document_pdf_url()` stay PDF only, so the viewer never hands a presentation to pdf.js. `CPTDP_Page_Converter::convert()` now inspects and converts a presentation to a temporary PDF first and then calls the extracted `convert_pdf_file()`, which is the old body unchanged (`%PDF-` check, 300 page cap, Imagick then Ghostscript, WebP or JPEG). The temporary folder is removed in a `finally`.
3. **Server only, never `needs_browser`.** For a presentation `ensure()` ignores `pages_mode`: with LibreOffice it queues the cron job, without it the status is `failed` with a message that recommends a PDF. **Exception:** when Settings → Documents → Native viewer → PowerPoint is ticked and the file is a `.pptx`, a host without LibreOffice is working as chosen, so the status is `none` and no error (nothing failed; the native viewer draws it). A failed conversion is final, also in `auto` mode (a PDF would be handed to the browser; a presentation cannot be). A held site-wide lock reschedules the job (one minute, up to ten times, then `failed`).
4. **Admin REST.** `POST cptdp/v1/documents/<id>/pages/retry` (`edit_post`, cookie nonce) calls `ensure( $id, true )` and returns the state; the Regenerate button of a presentation uses it. `start`, `<n>` and `finish` (the admin-browser upload routes) answer **400 `cptdp_pptx_server_only`** for a presentation. The state payload gains `kind`.
5. **Validation before LibreOffice sees the file** (`CPTDP_Pptx_Converter::inspect()`): decided by content, the name only picks the check. File at most 100 MB (filter `cptdp_pptx_max_bytes`).
   - **`.pptx`:** zip (`PK\x03\x04`) with `[Content_Types].xml` and `ppt/presentation.xml`; at most 5000 entries, 1 GB expanded, per-entry ratio at most 200; refuses macros (`macroEnabled`/`vbaProject` in the content types or `ppt/vbaProject.bin`, so `.pptm`/`.ppsm`) and any `.rels` relationship with `TargetMode="External"` of type `oleObject`, `package`, `frame` or `externalLink`. Hyperlinks and linked pictures are allowed.
   - **`.odp`:** zip whose first entry `mimetype` is `application/vnd.oasis.opendocument.presentation`, plus `content.xml`; same zip limits; refuses `Basic/` and `Scripts/` folders and a `draw:object`, `draw:object-ole` or `draw:plugin` whose `xlink:href` has a scheme, is absolute or climbs out of the package.
   - **`.ppt`:** OLE2 signature (`D0 CF 11 E0 A1 B1 1A E1`), not a zip; refused when the file contains the UTF-16 name `_VBA_PROJECT` (a bounded scan in 1 MB chunks with an overlap, no OLE parser). A slide that merely contains that exact text is refused too, accepted for the old format.
   Failures are `WP_Error`s whose message becomes the status error.
6. **One soffice at a time.** The site-wide transient `cptdp_pptx_lock` (5 minutes, always released) stops two cron jobs starting two Impress instances (300 MB or more each); a busy answer (`cptdp_pptx_busy`) reschedules the document.
7. **Availability.** `CPTDP_Pptx_Converter::binary()` checks `/usr/bin`, `/usr/local/bin`, `/opt/homebrew/bin`, `/opt/libreoffice*/program` and the macOS app path, requires `proc_open`, and can be overridden by the `CPTDP_SOFFICE_PATH` constant and the `cptdp_pptx_soffice_path` filter. `is_available()` needs LibreOffice **and** a way to draw the PDF (`CPTDP_Page_Converter::is_available()`) and is filterable (`cptdp_pptx_available`). `version()` caches `soffice --version` for a day. Settings → Documents shows a read-only "PowerPoint conversion" row: Available (LibreOffice 25.2.3.2) or Unavailable with the reason.
8. **Fonts are the main source of imperfection.** LibreOffice substitutes missing fonts; DDEV gets the metric-compatible set (Liberation for Arial and Times, Carlito for Calibri, Caladea for Cambria). Animations, transitions, SmartArt edge cases, embedded media and some effects differ or are dropped; speaker notes and hidden slides are not exported.
9. **Storage and manifest.** Same folder and file names as PDFs. The manifest gains `source_kind` and `converted_via: libreoffice` for presentations only (a missing `source_kind` means a PDF). **Presentations are never bottom-trimmed**: `crop_bottom` is 0 (the analyzer is skipped, `effective_crop()` and `recrop()` return 0), because a white slide with content only at the top would otherwise be cropped into another shape. A `cptdp_total_pages` mismatch says "The presentation has N slides, Total pages says M."
10. **The editor warning.** `cptdp_pptx_warning( $kind )` (`includes/post-types/document-presentations.php`) returns one non-blocking `notice-warning` for every presentation kind: **"PowerPoint files are converted automatically and the result may be imperfect (fonts, spacing, animations and some effects can differ). For the best result, export the presentation to PDF in PowerPoint (File → Save As → PDF) and upload that PDF instead."** (`.odp`: "OpenDocument presentations…", "in LibreOffice (File → Export as PDF)"). It shows (1) **live in the media picker** the moment a presentation is chosen or uploaded, before saving (`assets/admin-document.js` reads the file extension on the `select` event, the HTML comes from the server through `cptdpDocument.pptxWarnings`, a PDF or Remove hides it), (2) **persistently** in the Document File and Page images meta boxes, (3) **once after saving** (a transient per user and document set when the file meta changes). For a **`.pptx`** and users who can `manage_options` it ends with a second sentence and a link (new tab, `rel="noopener"`) to the Native viewer section (`options-general.php?page=cptdp-documents#cptdp-native-viewer`): "Can't install LibreOffice, or want selectable text and a closer match to the original? You can **let the native viewer draw PowerPoint files** instead (visitors then see the presentation drawn in their own browser, which is slower to open; nothing is sent to anyone)." When the box is ticked this becomes a status line with a "Change this setting" link. `.ppt` and `.odp` get no link (the native viewer cannot read them); editors without the capability see the base warning only.
11. **Status wording and CLI.** Column and meta box read `Ready, 12 slides (converted from PowerPoint)` (`OpenDocument` for `.odp`); the Regenerate button of a presentation says "Regenerate" and calls the retry route; the Page images box never auto-starts the browser generator for a presentation. `wp cptdp pages status` has a `kind` column; `generate` warns once when LibreOffice is missing and still handles PDFs; `--sync` runs LibreOffice in the CLI process.
12. **Settings → Documents revamp.** Two sections: **"Documents"** (book title, page image mode, margins, the PowerPoint conversion status) and one **"Native viewer"** section. The section explains that by default a document is shown from stored page images, that ticking a format lets our own viewer draw the original file in the visitor's browser (text can be selected and copied, a closer match to the file), **at the expense of performance** (slower to open and turn, heavier on phones and older computers, viewer code downloaded first), that the stored images are shown when the native viewer cannot draw a document, and that nothing is sent to anyone. One field, "Let the native viewer draw", holds one checkbox per format (`cptdp_documents_native_formats()`): **PDF** (`native_pdf`, the former "Selectable text": pdf.js with a real text layer, dragging a page corner switched off so that dragging selects text, documents that cannot be drawn this way use the images) and **PowerPoint (.pptx)** (`native_pptx`, the bundled viewer of DPD-026, no LibreOffice needed, no animations or equations, `.ppt` and `.odp` not supported). Both are off by default. The fieldset carries `id="cptdp-native-viewer"` (highlighted with `:target`) for the warning's link. **Compatibility:** an existing stored `selectable_text` is read as `native_pdf` (`cptdp_get_documents_settings( 'selectable_text' )` too), a partial update or REST call that still sends it changes `native_pdf`, and the REST schema still accepts it; saving rewrites the option under the new name. The settings form still reads an unticked box as off and a partial update keeps the other values.
13. **`cptdp_document_native_pptx_url( $post_id )`** (the plugin's half of the PowerPoint checkbox): the attachment URL for a `.pptx` when `native_pptx` is on and the file is within `cptdp_native_pptx_max_bytes` (30 MB); `''` for `.ppt`, `.odp`, PDFs, no file, the box off or an oversized file. It does **not** depend on converted slides existing: the native viewer takes over and the slides are its fallback. No local-site or public-site rule (nothing leaves the site).
14. **DDEV tooling and fixtures.** `.ddev/config.yaml` gets `webimage_extra_packages` (`libreoffice-impress`, `fonts-liberation`, `fonts-crosextra-carlito`, `fonts-crosextra-caladea`; needs `ddev restart`, then `install-wp-tests.sh` again). `tests/fixtures/` holds `sample.pptx`, `.odp` and `.ppt` (the same three 16:9 slides, made by LibreOffice from the readable `sample.fodp`, with a README on regenerating them); other cases (bombs, macros, external links, size limit) are built in memory.

## Decisions reversed during the work
- **Microsoft Office online viewer** (an opt-in iframe of `view.officeapps.live.com` for hosts without LibreOffice, with a public-site rule, a size limit and a privacy note): built and tested, then **replaced** by the in-browser native viewer, which sends nothing to a third party, needs no public URL and works on DDEV. Nothing of it remains in the code.
- **`pptx-react-viewer`** (the `ChristopherVR/ooxml` library) was evaluated first and rejected (see DPD-026).
- **Native viewer as a fallback only** became **a takeover with the converted slides as its fallback** (decision 13), driven by the request for selectable text and a more accurate rendering.

## Out of scope (and what comes next)
- **A later ticket will add DOCX and XLSX** to the same Native viewer section (a checkbox each) and to the modal. The library DPD-026 vendors already has engines for both; they are not bundled yet.
- Native viewing of `.ppt` (the library's experimental add-on is not bundled) and `.odp` (unsupported); speaker notes; keeping the temporary PDF to offer a PDF download or selectable text for a converted deck; a setting to switch conversion off (the availability row and `cptdp_pptx_available` filter are enough); conversion of `.pps`, `.ppsx`, `.key`.

## Risks / open points
1. **Production tooling unknown.** Without LibreOffice a presentation ends `failed` with the PDF advice, unless the PowerPoint native viewer box is ticked (then `.pptx` still works and `.ppt`/`.odp` fail). Confirm the host can install `libreoffice-impress` (several hundred MB with dependencies).
2. **Imperfect output is expected**, not a bug (decision 8); no copy should promise fidelity.
3. **Memory and time.** Impress takes 300 MB or more; a real 8-slide deck converts in about 1 second here. Limits and the site-wide lock in decisions 1 and 6; the CLI `--sync` is the manual path.
4. **External content in a presentation.** External OLE-style objects are refused; linked external pictures are not, and LibreOffice fetches them if the host allows outbound requests. Options if that matters: run `soffice` as a user without network access, or also refuse external image relationships (stricter). Default: allow.
5. **`.ppt` is the weakest input** (old format, no cheap external-link check, macro detection by a scan). If the scan proves unreliable, refuse `.ppt` with the PDF advice rather than weaken the checks.
6. **Temporary files** are removed in a `finally`; `CPTDP_Pptx_Converter::stray_folders()` exists (folders older than a day) but is not wired into `pages status` or `pages clear` yet.
7. **Upload may be denied by the host** (multisite file types, security plugins): WordPress's own error, not handled here.
8. **The settings page and the editor scripts are tested by source structure and rendered HTML only** (no browser runner for wp-admin).

## Steps
One conventional commit each, scope `custom-post-types-dp`, **tests never in a feature commit**, suite green at every commit. (The work exists in the working tree uncommitted; this is the intended split.)

1. **Source kinds** (feat, test): `kind_of_attachment()`, `source_attachment_id()`, `source_kind()`, `get_source()` with `kind`, manifest marks, no trim, slide wording for a total mismatch, `cptdp_document_pages_status_label( $status, $count, $kind )`.
2. **PPTX converter** (feat, test): `class-pptx-converter.php` (`binary()`, `is_available()`, `version()`, `inspect()`, `to_pdf()`, lock, timeout, `cleanup()`, `stray_folders()`), `CPTDP_Page_Converter::convert()` split into `convert()` and `convert_pdf_file()`, public `drain()` shared by both converters; fixtures and the shared test helpers.
3. **Scheduler and REST** (feat, test): the presentation branch of `ensure()` (including the native-viewer exception), failure and busy handling in `record_conversion()`/`convert_now()`/`retry_later()`, the `retry` route, the 400 on the browser routes, `kind` in the state, the Page images box and `assets/admin-document-pages.js`, CLI `kind` column.
4. **The warning** (feat, test): `document-presentations.php`, the Document File box container, the picker script, the after-save notice, the Settings status row.
5. **Native viewer settings** (feat, test): the revamped settings page and the `native_pdf`/`native_pptx` keys with the legacy alias and REST schema, `cptdp_document_native_pptx_url()`, the warning's link.
6. **DDEV and version** (chore, test): `webimage_extra_packages`, plugin `Version` 0.20.0.
7. **Docs** (docs): AGENTS.md (pipeline, kinds, validation, settings, native viewer, DDEV, totals).
8. **Plan** (docs): this plan, last on the shared branch with DPD-026's.

## Verification
```bash
ddev restart                      # once: installs LibreOffice and the fonts
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"
ddev exec "soffice --version"
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```
Manual:
1. **Warning on pick:** in a document pick or upload a `.pptx`, then a `.ppt` and an `.odp`: the warning appears at once with the PDF advice; the `.pptx` one has the link to the Native viewer section (new tab, row highlighted, unsaved edits kept); `.ppt`/`.odp` have no link; an Editor sees no link; picking a PDF or removing the file hides it. After saving, the one-time notice shows and the persistent ones stay.
2. **Conversion:** status goes `Pending` then `Ready, N slides (converted from PowerPoint)` (`ddev wp cron event run --due-now` if idle); landscape images exist; no `cptdp-pptx-*` folder is left in `/tmp`; the Documents list and the open-book viewer show the slides.
3. **No LibreOffice** (rename `soffice` in a throwaway shell): status `Failed` with the PDF advice, never `needs_browser`, in every `pages_mode`; with the PowerPoint box ticked a `.pptx` is `none` instead; `.ppt` still fails; Regenerate retries on the server.
4. **Bad files:** a renamed text file, a `.pptx` with an external OLE object, a `.odp` with a Basic folder, a `.ppt` with a VBA project, an over-limit file: each ends `Failed` with its own message and nothing left behind.
5. **Settings:** one "Native viewer" section with PDF and PowerPoint checkboxes, the performance and fallback text, the conversion status row above it; a site that stored `selectable_text` still has PDF ticked; a REST `PUT` with one key keeps the others.
6. **Security:** logged out and without `edit_post`, POST to `.../pages/retry` is refused; as an editor `start`, `<n>` and `finish` return 400 for a presentation.
7. `ddev wp cptdp pages generate --all --sync` covers presentations; `pages status` shows `kind`.

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `feat(custom-post-types-dp): treat presentations as a document page source`
2. `test(custom-post-types-dp): cover the document source kinds`
3. `feat(custom-post-types-dp): convert pptx, ppt and odp documents to page images with libreoffice`
4. `test(custom-post-types-dp): cover the presentation converter`
5. `feat(custom-post-types-dp): run presentations through the server-only pipeline with a retry route`
6. `test(custom-post-types-dp): cover presentation scheduling and the retry route`
7. `feat(custom-post-types-dp): warn that presentation conversion may be imperfect`
8. `test(custom-post-types-dp): cover the presentation warning`
9. `feat(custom-post-types-dp): add a native viewer section to the documents settings`
10. `test(custom-post-types-dp): cover the native viewer settings and pptx url`
11. `chore(custom-post-types-dp): add libreoffice to ddev and bump version to 0.20.0`
12. `docs(agents): document presentations and the native viewer settings`
13. `docs(plans): add DPB-026 plan` (with the DPD-026 plan, last of the branch)

One PR for the shared branch covering both tickets: `feat(documents): DPB-026 DPD-026 presentation conversion and native viewer` (template `.github/pull_request_template.md`). Call out that DDEV needs `ddev restart` for LibreOffice, that production needs LibreOffice Impress or presentations fail with the PDF message (unless the PowerPoint native viewer is ticked, which still covers `.pptx`), and that the DPB-026 commits are the plugin and the DPD-026 commits are the theme, for two review passes.

## As built
Final plugin suite: **664 tests, 12 skipped** (593 before). Verified for real in DDEV (LibreOffice 25.2.3.2 with the Carlito, Liberation and Caladea fonts): the three fixtures convert to 3 slides of 1654 x 930, no trim, no temporary folders left; two real decks already in the dev database (6 and 8 slides) convert in about 1 second each and look right.
- **Not as planned:** the `.ppt` macro check is a scan for `_VBA_PROJECT`, not an OLE directory reader; `sample.ppt` is 464 KB (the legacy format embeds LibreOffice's master data), far over the 30 KB first assumed; `stray_folders()` is not wired into the CLI; the retry route is not limited to presentations (for a PDF it re-runs `ensure( $id, true )` and follows the mode setting); a presentation that waits on another conversion ends `failed` after the ten retries, never in the browser.
- **After-save notice:** remembered per user and document in a transient when the file meta changes to a presentation, shown once on the edit screen.
- **Not verified in a browser:** the media picker warning, the Regenerate button's retry flow and the settings page (all covered by source-structure tests and rendered HTML only). DDEV note: the real `webimage_extra_packages` setting is near the top of `.ddev/config.yaml`; the commented example further down does not count.
