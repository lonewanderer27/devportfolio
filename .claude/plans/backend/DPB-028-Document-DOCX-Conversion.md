# Plan: Document word-processing files (DOCX, DOC, ODT): server conversion to page images and the Word native viewer setting

Ticket DPB-028 (backend, **phase 1 of 2**). **One shared branch for both phases: `feat/DPB-028-DPD-028-Document-Modal-DOCX-Viewer`** (this plan's commits first, then DPD-028's, as with DPB-027). Phase 2 is the frontend, `../documents/DPD-028-Document-Modal-DOCX-Viewer.md`, which shows converted pages and draws a `.docx` with the bundled native viewer when the setting below is on. Builds on DPB-025 (page images pipeline), DPB-026 (LibreOffice conversion, server-only producer) and DPB-027 (`DPB-027-Document-XLSX-Conversion.md`: Calc, Document conversion section, `cptdp_native_office_url()`). Nothing in the theme changes in this phase.

Plugin: `custom-post-types-dp` (bump `0.21.0` to **`0.22.0`**).

> **Status: draft, not implemented.** Items marked **Decide** carry a recommendation; confirm or change them before step 1.

## Goal
1. A `document` whose file (`cptdp_document_file`) is a word-processing file, **`.docx`, `.doc` or `.odt`**, gets page images like a PDF, a deck or a workbook does, made on the server with LibreOffice Writer (one image per printed page, under `uploads/cptdp-pages/`, shown in the open-book viewer).
2. The editor is **warned when they pick or upload a word-processing file** that layout can differ (fonts, page breaks, fields, tracked changes) and that a PDF export is the most predictable result. Never blocks the upload.
3. Settings → Documents → Native viewer gets a fourth checkbox, **Word (.docx)** (`native_docx`, off by default), that lets our own viewer draw the document in the visitor's browser, the converted pages being its fallback. This is the "DOCX is coming in a later ticket" item of DPB-026/027.

In this plan **"word file"** means `docx`, `doc` or `odt`. (`.rtf`, `.docm`, `.dotx`, `.pages` are out of scope.)

## Context
- DPB-026/027 made the pipeline kind-aware: `CPTDP_Page_Store` kinds and `is_presentation_kind()`, `is_spreadsheet_kind()`, `is_office_kind()`; `CPTDP_Pptx_Converter` (binary lookup, `to_pdf()`, `is_available( $kind )`, `unavailable_reason( $kind )`, `ole_status()`, `rels_have_external_object()`, `odf_has_external_object()`, lock `cptdp_pptx_lock`, 120 s timeout, throwaway folder and profile); `CPTDP_Xlsx_Converter` (`inspect()`, `export_filter()`, `write_profile()`, `calc_installed()`); the server-only branch of `ensure()`, the `retry` route, `includes/post-types/document-office.php` (`cptdp_office_warning()`, `cptdp_native_viewer_enabled()`, `cptdp_native_office_url()`), `cptdp_documents_native_formats()` (`pdf`, `pptx`, `xlsx`) and `cptdp_documents_conversion_formats()` (a new family is a table entry).
- DPB-027's "Deferred on purpose (do with the DOCX ticket)": rename `CPTDP_Pptx_Converter` to a neutral Office converter with a per-kind profile (inspector, export filter, size limit, availability probe), one kind registry, less per-format test duplication. **This is the third format, so the common shape is now visible: do that refactor here** (decision 1).
- `cptdp_document_sanitize_file()` already accepts any non-video/audio attachment; WordPress allows `docx`, `doc` and `odt` by default, so they are uploadable today but never get images.
- No admin-browser producer exists for word files (pdf.js cannot read them): server only, never `needs_browser`.
- DDEV installs `libreoffice-impress` and `libreoffice-calc`; Writer is a separate package (`libreoffice-writer`).
- The vendored library (`blocks/document-modal/vendor/ooxml/`, `@silurus/ooxml` 0.89.7) has no DOCX entry yet; the package ships a DOCX engine and `docx_parser_bg.wasm` (about 2.26 MB). Import graph size to be measured in DPD-028.

## The pipeline for a word file
Same as DPB-026/027 with a different file check and export filter:
```
Word file created or changed (same hooks)
   ▼ ensure(): LibreOffice Writer available? ── no ──> native box ticked and a .docx? ── yes ──> status none
   │                                                    └─ no ──> status failed, message recommends a PDF (never needs_browser)
   ▼ pending
WP-Cron job (same event, same per-document lock, same retries; one soffice at a time site-wide)
   1. inspect the file by content (decision 4)
   2. soffice --convert-to pdf:writer_pdf_Export  ──> temporary PDF in a throwaway folder and profile
   3. temporary PDF ──> the unchanged PDF code ──> p-001.webp ...
   4. manifest: source_kind, converted_via 'libreoffice'
   5. delete the temporary folder, always
   ▼ ready     (any failure ──> failed with a short message that recommends a PDF)
```

## Key decisions
1. **Generalise the converter now (follows DPB-027 "Deferred").** First commit, a pure refactor with the suite green: rename `CPTDP_Pptx_Converter` to `CPTDP_Office_Converter` with a per-kind descriptor table (inspector callback, export filter, profile writer, size limit filter and default, Writer/Calc/Impress presence probe, label). `CPTDP_Xlsx_Converter` and the new `CPTDP_Docx_Converter` keep only their `inspect()`, export filter and profile. A `class_alias` keeps `CPTDP_Pptx_Converter` for one release. Filter and constant names stay valid (`cptdp_pptx_soffice_path`, `cptdp_pptx_timeout`, `CPTDP_SOFFICE_PATH`, `cptdp_pptx_available`, `cptdp_xlsx_available`, the `cptdp_pptx_lock` transient); the new `cptdp_docx_available` follows the pattern. **Decide:** do the rename in this ticket (recommended, the third copy justifies it) or add `CPTDP_Docx_Converter` beside the existing two and rename later.
2. **Source kinds.** `CPTDP_Page_Store` gains `KIND_DOCX|KIND_DOC|KIND_ODT`, the mime map (`application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `application/msword`, `application/vnd.oasis.opendocument.text`) with the extension fallback for generic `application/zip`, `application/octet-stream`, `application/x-ole-storage`, `application/CDFV2`, and `is_word_kind()`. `is_office_kind()` becomes presentation, spreadsheet or word, so the scheduler, converter, REST routes and manifest marks pick it up. `pdf_attachment_id()` and `cptdp_document_pdf_url()` stay PDF only.
3. **Export filter and page shape.** `writer_pdf_Export`: one PDF page per printed page, at the document's own page size (A4, Letter, custom). Unlike a sheet, a Word page already has a book shape, so **no canvas normalisation** (the PDF image code handles it). Options: `ExportNotes` off, `ExportBookmarks` off, `ExportFormFields` off, comments and tracked changes not drawn (**Decide**: LibreOffice shows tracked changes as marked up by default when the file has them visible; recommended to export the accepted-looking view, `RecordChanges` is not touched but the profile sets "show changes" off if the spike proves a setting exists, else the warning says so). The 300-page cap stays (filter `cptdp_pages_max_pages`). Mixed portrait and landscape sections make pages of different shapes; the book uses the first page's ratio and `object-fit: contain` as for PDFs (DPD-013), so no special case.
4. **Validation before LibreOffice sees the file** (`CPTDP_Docx_Converter::inspect()`), same zip-bomb limits (5000 entries, 1 GB expanded, ratio 200), size cap **25 MB** (filter `cptdp_docx_max_bytes`):
   - **`.docx`:** zip with `[Content_Types].xml` and `word/document.xml`; refuses macros (`macroEnabled` or `vbaProject` in the content types, `word/vbaProject.bin`, so `.docm`-style packages); refuses external relationships of the types that make Writer fetch or run something: `attachedTemplate` (remote template), `oleObject`, `package`, `frame`, `subDocument`, `externalLink`, and a mail-merge data source (`mailMerge` in `word/settings.xml`, `mailMergeSource` relationships). **Linked pictures and hyperlinks (`TargetMode="External"` on `image`/`hyperlink`) are allowed** as in DPB-026/027, but the profile never updates links, see decision 5. Also refuses field codes that load content: `INCLUDETEXT`, `INCLUDEPICTURE` with a URL, `DDE`, `DDEAUTO`, `LINK` inside `w:instrText` or `w:fldSimple` (bounded scan of `word/document.xml`, headers, footers).
   - **`.odt`:** zip whose first entry `mimetype` is `application/vnd.oasis.opendocument.text`, plus `content.xml`; refuses `Basic/`, `Scripts/`, and an external `xlink:href` on `draw:object`, `draw:object-ole`, `draw:plugin`, **`text:section-source`** and `text:bibliography-source`-style linked sections; reuse `odf_has_external_object( $xml, $elements )`.
   - **`.doc`:** OLE2 signature plus the `_VBA_PROJECT` scan via the shared `ole_status()`; also scans for the `Macros` storage name. Same risk note as `.ppt` and `.xls`: no OLE parser, so linked fields are not checked (Risks 4).
   Failures are `WP_Error`s whose message becomes the status error.
5. **Writer must not touch the network or run anything.** Throwaway profile (written like `write_profile()` for Calc): **link update mode never** for Writer (`org.openoffice.Office.Writer/Content/Update/Link`, value to be confirmed in the spike; value for "never"), macros disabled / security very high, and **field update on load off** so dates and `PAGE`/`NUMPAGES` fields show what the author saved (**Decide**: keep saved field values, matching the author's view, or let LibreOffice update them; recommended keep saved, and note that page numbers in fields are recomputed by pagination anyway). Never "ask": headless would hang.
6. **Availability.** `is_available( $kind )` for a word file also needs Writer (`libswlo.*` next to the soffice binary, the same file check as Calc, cached per request). `unavailable_reason( $kind )` has Word wording. Filter `cptdp_docx_available`.
7. **Scheduler, REST and CLI.** The server-only branch handles word files; without LibreOffice or Writer a word file is `failed` with a PDF recommendation, except a `.docx` with `native_docx` ticked, which is `none` (`cptdp_native_viewer_enabled( 'docx' )` already generalises this). Error code on the browser routes stays `cptdp_pptx_server_only`. Status text `Ready, 12 pages (converted from Word)` (`OpenDocument` for `.odt`). `pages status` shows the kinds; `generate` warns once when Writer is missing. Word files are **never bottom-trimmed** (`crop_bottom` 0), consistent with the other Office kinds (**Decide**: trim is possible because a Word PDF is paginated like any PDF, but the analyzer would run on LibreOffice's pagination, which is not what the author saw; recommended no trim).
8. **`cptdp_total_pages` and the page count. Decide (recommended: ignore a mismatch for word files).** The store marks a page set valid only while the image count equals `cptdp_total_pages` when that is set. For a PDF the editor types the true count; for a Word file LibreOffice's pagination often differs from Word's by a page or two (fonts, spacing), so an editor entering Word's count would turn every conversion into `failed`. Recommended: for word files the **converted count wins**, `cptdp_total_pages` is overwritten with it on success (as a page set is the source of truth), and the field's help text says it is filled in automatically. Same treatment is **not** applied to PDFs. Test both. If this is judged too invasive, alternative: show a non-fatal notice and keep the editor's number, with the book using the image count.
9. **The editor warning.** `cptdp_docx_warning( $kind )` in a new `includes/post-types/document-word.php` (do not grow `document-presentations.php`): "Word files are converted automatically, so fonts, page breaks, fields, tracked changes and complex layouts can differ from what you see in Word, and the page count may change. For the best result, export the document to PDF (File → Save As → PDF) and upload that PDF instead." plus "Everything in the document, including text that is hidden or in comments, may be published" for native mode only (see Risks 6); `.odt`: LibreOffice wording. Same three places as before (live in the media picker through `cptdpDocument.officeWarnings`, persistently in the Document File and Page images boxes, once after saving) via the shared `cptdp_office_warning_html()`; for `.docx` and users who can `manage_options` a link to the Native viewer section. `.doc` and `.odt` get no link. `assets/admin-document.js` only needs the extension → kind table extended.
10. **Native viewer settings.** `cptdp_documents_native_formats()` gains `docx` after `xlsx` (`native_docx`, default false); defaults, sanitiser, REST schema and the form loop already derive from it. Explanation text: "reads the Word file in the visitor's browser: text can be selected and copied, pages are laid out by the viewer, so they may differ from Word; slower to open; nothing is sent to anyone; no macros; `.doc` and `.odt` are not supported; **the visitor downloads the whole file, including comments, tracked changes and hidden text**". `cptdp_document_native_docx_url( $post_id )`: one-line wrapper of `cptdp_native_office_url()` for a `.docx` (never `.doc`/`.odt`), box on, file within `cptdp_native_docx_max_bytes` (**10 MB**: the whole document is parsed in the browser and Word files are mostly text, so lower than Excel's 15 MB; **Decide**).
11. **Document conversion section.** A third row in `cptdp_documents_conversion_formats()`: `documents` ("Word (.docx, .doc) and OpenDocument (.odt)", kind `docx`, product `LibreOffice Writer`, detail "One page per printed page. Fonts that are missing are replaced, page breaks and field results can differ, tracked changes and comments are not marked up in the pages, and documents with macros or external links are refused"). No other change to the section.
12. **DDEV tooling and fixtures.** `.ddev/config.yaml` `webimage_extra_packages` gains `libreoffice-writer` (`ddev restart`, then `install-wp-tests.sh` again). `tests/fixtures/` gets `sample.docx`, `.doc` and `.odt` made by LibreOffice from a readable `sample.fodt` (a believable multi-page document: headings, a table, a list, an image, a footer with a page number, one landscape section; README note on regenerating). Bombs, macros, external templates, mail merge, field codes and the size limit are built in memory.

## Out of scope (and what comes next)
- `.rtf`, `.docm`, `.dotx`, `.pages`, Google Docs links; tracked-changes or comment review UI; mail-merge or form fields; fidelity checks against Word; keeping the temporary PDF as a download; native `.doc`/`.odt`.
- After this ticket every format in the Native viewer section has an owner; a future "text" family (Markdown, TXT) is a new table entry.

## Risks / open points
1. **Production tooling unknown** (as DPB-026 Risk 1): production now also needs `libreoffice-writer`. Without it word files end `failed` with the PDF advice (or `none` for a `.docx` with the native box ticked).
2. **Fidelity.** Fonts (Calibri, Cambria and Aptos are replaced by Carlito, Caladea and a fallback), complex tables, text boxes, SmartArt, equations and content controls differ. The warning must not promise fidelity.
3. **Page count drift** (decision 8) is the one place this ticket touches PDF-era rules; get it right and tested.
4. **`.doc` is the weakest input** (no cheap external-link or field check). If the macro scan proves unreliable, refuse `.doc` with the PDF advice, as for `.xls`.
5. **Resource use.** A 100-page document with large images can take over a minute and hundreds of MB; the 25 MB cap, the 120 s timeout, the one-at-a-time lock and the 300-page cap bound it.
6. **Sensitive content.** Word files often hold tracked changes, comments, author names and hidden text. Converted pages show what Writer draws (comments and revisions off, per decision 3, to be verified), but in native mode the visitor downloads the whole file with all of it. The warning and the Native viewer text say so, as for Excel.
7. **Formula/field injection** is not a risk (nothing is re-exported); noted so nobody adds an export without sanitising.
8. **The settings page and editor scripts are tested by source structure and rendered HTML only**, as before.

## Steps
One conventional commit each, scope `custom-post-types-dp`, **tests never in a feature commit**, suite green at every commit.
1. **Neutral Office converter** (refactor, test): decision 1, no behaviour change for presentations and spreadsheets.
2. **Word kinds** (feat, test): kind constants, mime map, `is_word_kind()`, `is_office_kind()`, manifest marks, no trim, status wording.
3. **Writer converter** (feat, test): `class-docx-converter.php` (`inspect()`, export filter, profile, availability), `convert()` dispatch, fixtures. Spike first: convert the three fixtures and a real multi-page `.docx`, confirm the profile keys and the tracked-changes behaviour, record in "As built".
4. **Scheduler and REST** (feat, test): word branch of `ensure()`, the native exception for `.docx`, `retry`, the page-count rule (decision 8), CLI.
5. **The warning** (feat, test): `document-word.php`, picker script table, after-save notice, conversion status row.
6. **Native viewer settings** (feat, test): `native_docx`, REST schema, `cptdp_document_native_docx_url()`, the warning's link.
7. **DDEV and version** (chore, test): `libreoffice-writer`, plugin `Version` 0.22.0.
8. **Docs** (docs): AGENTS.md (kinds, validation, settings, DDEV, totals).
9. **Plan** (docs): this plan, last on the shared branch with DPD-028's.

## Verification
```bash
ddev restart                      # once: installs Writer
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"
ddev exec "soffice --version"
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```
Expected totals before: plugin 690 (12 skipped), theme 220 (2 known failures).

Manual:
1. **Warning on pick:** pick or upload a `.docx`, `.doc`, `.odt`: warning at once with the PDF advice; link only for `.docx` and administrators; a PDF or Remove hides it; one-time notice after saving.
2. **Conversion:** `Pending` then `Ready, N pages (converted from Word)` (`ddev wp cron event run --due-now`); page count matches the fixture, landscape section kept, tracked changes and comments not drawn, no `cptdp-pptx-*` folder left in `/tmp`.
3. **Page count:** a `.docx` with `cptdp_total_pages` set to a wrong number still becomes `Ready` and the field is corrected; a PDF with a wrong number still fails as before.
4. **No Writer / no LibreOffice:** `Failed` with the PDF advice in every `pages_mode`, never `needs_browser`; `.docx` with the box ticked is `none`; Regenerate retries on the server.
5. **Bad files:** renamed text file, `.docx` with an `attachedTemplate` URL, one with mail merge, one with an `INCLUDETEXT`/`DDE` field, a `.docm`, an `.odt` with Basic or a linked section, a `.doc` with a VBA project, over-limit file: each `Failed` with its own message and nothing left behind; a document with a remote linked image makes **no outbound request** (local listener).
6. **Settings:** Native viewer shows PDF, PowerPoint, Excel, Word; Document conversion shows three rows, the common sentence once; a REST `PUT` with one key keeps the others.
7. **Security:** `retry` refused without `edit_post`; `start`, `<n>`, `finish` return 400 for a word file.
8. `ddev wp cptdp pages generate --all --sync` covers word files; `pages status` shows `kind`.

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `refactor(custom-post-types-dp): make the office converter neutral across formats`
2. `test(custom-post-types-dp): cover the neutral office converter`
3. `feat(custom-post-types-dp): treat word files as a document page source`
4. `test(custom-post-types-dp): cover the word source kinds`
5. `feat(custom-post-types-dp): convert docx, doc and odt documents to page images with libreoffice writer`
6. `test(custom-post-types-dp): cover the word converter`
7. `feat(custom-post-types-dp): run word files through the server-only pipeline`
8. `test(custom-post-types-dp): cover word scheduling and the converted page count`
9. `feat(custom-post-types-dp): warn that word conversion may be imperfect`
10. `test(custom-post-types-dp): cover the word warning`
11. `feat(custom-post-types-dp): add word to the native viewer settings`
12. `test(custom-post-types-dp): cover the word native viewer setting and url`
13. `chore(custom-post-types-dp): add libreoffice writer to ddev and bump version to 0.22.0`
14. `docs(agents): document word files and the word native viewer`
15. `docs(plans): add DPB-028 plan` (with the DPD-028 plan, last of the branch)

One PR for the shared branch: `feat(documents): DPB-028 DPD-028 word conversion and Word native viewer` (template `.github/pull_request_template.md`). Call out: DDEV needs `ddev restart` for Writer; production needs `libreoffice-writer`; the `CPTDP_Pptx_Converter` rename; DPB-028 commits are the plugin and DPD-028 commits are the theme, for two review passes.
