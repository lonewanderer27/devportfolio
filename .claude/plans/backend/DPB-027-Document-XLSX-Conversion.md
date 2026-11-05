# Plan: Document spreadsheets (XLSX, XLS, ODS): server conversion to page images and the Excel native viewer setting

Ticket DPB-027 (backend, **phase 1 of 2**). **One shared branch for both phases: `feat/DPB-027-DPD-027-Document-Modal-XLSX-Viewer`** (this plan's commits first, then DPD-027's, as with DPB-026). Phase 2 is the frontend, `../documents/DPD-027-Document-Modal-XLSX-Viewer.md`, which shows converted pages and draws a `.xlsx` with the bundled native viewer when the setting below is on. Builds on DPB-025 (page images pipeline) and DPB-026 (`DPB-026-Document-PPTX-Conversion.md`: LibreOffice conversion, server-only producer, Native viewer settings section). Nothing in the theme changes in this phase.

Plugin: `custom-post-types-dp` (bump `0.20.0` to **`0.21.0`**).

> **Status: implemented, uncommitted (see "As built" at the end).** Items marked **Decide** were settled with the plan's recommendations.

## Goal
1. A `document` whose file (`cptdp_document_file`) is a spreadsheet, **`.xlsx`, `.xls` or `.ods`**, gets page images like a PDF or a presentation does, made on the server with LibreOffice Calc (one image per printed page, under `uploads/cptdp-pages/`, shown in the open-book viewer).
2. The editor is **warned when they pick or upload a spreadsheet** that a spreadsheet is a poor fit for pages (wide sheets split, page breaks follow the print setup, formulas show their saved values) and that a PDF export is the most predictable result. Never blocks the upload.
3. Settings → Documents → **Native viewer** gets a third checkbox, **Excel (.xlsx)** (`native_xlsx`, off by default), that lets our own viewer draw the workbook in the visitor's browser (scroll, zoom, sheet tabs, copy cells), the converted pages being its fallback. This is the "DOCX and XLSX will be added by a later ticket" item of DPB-026; **DOCX stays out** (DPB-028 or similar).

In this plan **"spreadsheet"** means `xlsx`, `xls` or `ods`.

## Context
- DPB-026 made the pipeline kind-aware: `CPTDP_Page_Store::kind_of_attachment()` / `source_attachment_id()` / `source_kind()` / `is_presentation_kind()`, `CPTDP_Pptx_Converter` (binary lookup, `inspect()`, `to_pdf()`, site-wide lock `cptdp_pptx_lock`, timeout, throwaway folder and profile), `CPTDP_Page_Converter::convert()` = inspect + convert + `convert_pdf_file()`, the scheduler's server-only branch, the `retry` route, `cptdp_pptx_warning()`, `cptdp_document_native_pptx_url()` and the Native viewer section (`cptdp_documents_native_formats()`).
- `cptdp_document_sanitize_file()` already accepts any non-video/audio attachment; WordPress allows `xlsx` (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`), `xls` (`application/vnd.ms-excel`) and `ods` (`application/vnd.oasis.opendocument.spreadsheet`) by default, so spreadsheets are uploadable today but never get images.
- A browser cannot render spreadsheets through pdf.js, so like presentations there is **no admin-browser producer**.
- DDEV installs `libreoffice-impress` only; Calc is a separate package (`libreoffice-calc`).
- The vendored viewer (`blocks/document-modal/vendor/ooxml/`, `@silurus/ooxml` 0.89.7) is a PPTX-only subset; the package also ships `xlsx.mjs`, `xlsx_parser_bg.wasm` (1.93 MB) and `xlsx-source-worker.mjs`. The XLSX import graph is about 2.3 MB of JS (33 files, most of it chunks that PPTX already vendors) plus the wasm. Measured with a script over the package, not yet in a browser.

## The pipeline for a spreadsheet
Same as DPB-026 with a different file check and export filter:
```
Spreadsheet created or changed (same hooks)
   ▼ ensure(): LibreOffice Calc available? ── no ──> native box ticked and a .xlsx? ── yes ──> status none
   │                                                  └─ no ──> status failed, message recommends a PDF (never needs_browser)
   ▼ pending
WP-Cron job (same event, same per-document lock, same retries; one soffice at a time site-wide)
   1. inspect the file by content (decision 4)
   2. soffice --convert-to pdf:calc_pdf_Export:<options>  ──> temporary PDF in a throwaway folder and profile
   3. temporary PDF ──> the unchanged PDF code ──> p-001.webp ...   (no trim)
   4. manifest: source_kind, converted_via 'libreoffice'
   5. delete the temporary folder, always
   ▼ ready     (any failure ──> failed with a short message that recommends a PDF)
```

## Key decisions
1. **Generalise the soffice code instead of copying it.** Extract from `CPTDP_Pptx_Converter` a `CPTDP_Soffice` runner (binary lookup, version, throwaway folder and profile, `proc_open` with argument array, timeout, pipe draining, `%PDF-` check, the site-wide lock, cleanup). `CPTDP_Pptx_Converter` keeps `inspect()` for presentations and calls it; the new `CPTDP_Xlsx_Converter` (`includes/documents/class-xlsx-converter.php`) holds `inspect()` for spreadsheets. **No behaviour change for presentations** (first commit, existing tests stay green). The lock transient becomes `cptdp_soffice_lock`; the `cptdp_pptx_soffice_path`, `cptdp_pptx_timeout` and `CPTDP_SOFFICE_PATH` names stay valid (new `cptdp_soffice_*` aliases read the old ones as defaults). **Decide:** keep the old names only, or rename with aliases (recommended: aliases, because the filters now cover Calc too).
2. **Source kinds.** `CPTDP_Page_Store` gains `KIND_XLSX|KIND_XLS|KIND_ODS`, the mime map for the three types (and the extension fallback for generic `application/zip`, `application/octet-stream`, `application/x-ole-storage`, `application/CDFV2`), and `is_spreadsheet_kind()`; `is_presentation_kind()` stays as is. A new `is_office_kind()` (presentation or spreadsheet) replaces the places in `ensure()`, the converter and the REST routes that currently test only presentations. `pdf_attachment_id()` and `cptdp_document_pdf_url()` stay PDF only. The manifest's `source_kind` and `converted_via` are written for spreadsheets too; **spreadsheets are never bottom-trimmed** (`crop_bottom` 0), as presentations.
3. **Export filter and page shape. Decide (recommended: one page per sheet).** LibreOffice's PDF export normally follows each sheet's print setup, which splits a wide sheet across many pages and prints empty ones. Calc's `SinglePageSheets` export option puts **each sheet on one page of the size it needs** (supported since 7.4; the DDEV package is 25.x): `--convert-to 'pdf:calc_pdf_Export:{"SinglePageSheets":{"type":"boolean","value":"true"}}'`. Result: pages = visible sheets, a page may be very wide or very tall. Guard: the existing 300-page cap stays, plus a **maximum page area** (filter `cptdp_xlsx_max_page_px`, default 8000 px on the long side at the render DPI); a sheet beyond it is scaled down by the converter so the image stays readable-ish, and the warning (decision 8) says so. Alternative: the print layout (more faithful to what the author printed, many pages, awkward splits). Verify both on three real workbooks before step 3 and record the choice in "As built".
4. **Validation before LibreOffice sees the file** (`CPTDP_Xlsx_Converter::inspect()`), same zip-bomb limits as presentations (5000 entries, 1 GB expanded, ratio 200, 100 MB, filter `cptdp_xlsx_max_bytes`):
   - **`.xlsx`:** zip with `[Content_Types].xml` and `xl/workbook.xml`; refuses macros (`macroEnabled` or `vbaProject` in the content types, `xl/vbaProject.bin`, so `.xlsm`/`.xlsb`-style packages), **external links** (`xl/externalLinks/` parts, any `.rels` relationship with `TargetMode="External"` of type `oleObject`, `package`, `externalLink`, `connections`), and data connections / query tables (`xl/connections.xml`, `xl/queryTables/`), which would make Calc open the network. Hyperlinks and linked pictures are allowed as in DPB-026.
   - **`.ods`:** zip whose first entry `mimetype` is `application/vnd.oasis.opendocument.spreadsheet`, plus `content.xml`; refuses `Basic/`, `Scripts/`, and any external `xlink:href` on `table:table`, `draw:object`, `draw:object-ole`, `draw:plugin` or `table:cell-range-source`; same zip limits.
   - **`.xls`:** OLE2 signature, the `_VBA_PROJECT` scan from `.ppt` (bounded, 1 MB chunks with overlap), plus a refusal of a `Workbook` stream containing an `EXTERNSHEET`/`SUPBOOK` external reference is **not** attempted (no OLE parser); same risk note as `.ppt` (Risks 4).
   Failures are `WP_Error`s whose message becomes the status error.
5. **Calc must not touch the network or run anything.** On top of the profile used for presentations, the throwaway profile sets Calc's **link update mode to never** (`registrymodifications.xcu`: `org.openoffice.Office.Calc/Content/Update/Link` = 2) and macro security to "very high", written by `CPTDP_Soffice` before launch. Formulas show their **saved** values; recalculation on load is left at LibreOffice's default for files it did not save (`never recalculate` is set for Excel 2007+ files so numbers match what Excel showed, through `org.openoffice.Office.Calc/Formula/Load/OOXMLRecalcMode` = 1; `ODFRecalcMode` = 1). **Decide:** keep never-recalculate (matches the author's view; stale numbers if a workbook was saved by a tool that does not cache values) or LibreOffice's default (recalculates, may differ from Excel).
6. **Availability.** `CPTDP_Xlsx_Converter::is_available()` = soffice available **and** Calc present (`soffice --version` does not prove it: check for `libreoffice-calc` by running a tiny `--convert-to csv` once on a bundled fixture and caching the result for a day, or look for `program/libscalo.so` next to the binary; **Decide** between the two, recommended the cached trial conversion since it also catches missing fonts and a broken profile). Settings → Documents shows a second read-only row "Spreadsheet conversion" (Available, version / Unavailable and why). Filter `cptdp_xlsx_available`.
7. **Scheduler, REST and CLI.** The server-only branch of `ensure()` handles spreadsheets with the same exception: without LibreOffice a spreadsheet is `failed` with a PDF recommendation, except a `.xlsx` with `native_xlsx` ticked, which is `none`. The `retry` route and the 400 `cptdp_pptx_server_only` on `start`/`<n>`/`finish` apply to all Office kinds (**the error code is renamed `cptdp_office_server_only`; the old code stays for presentations**, a test pins both). Status text: `Ready, 6 pages (converted from Excel)` (`OpenDocument` for `.ods`); the state payload's `kind` already exists. `wp cptdp pages status` shows the new kinds; `generate` warns once when Calc is missing.
8. **The editor warning.** `cptdp_xlsx_warning( $kind )` in `includes/post-types/document-presentations.php`'s sibling `document-spreadsheets.php` (do not grow the presentations file): "Excel files are converted automatically and each sheet becomes one page, so very wide or long sheets are shrunk and some formatting, charts and formulas can differ. For the best result, export the workbook to PDF in Excel (File → Save As → PDF) and upload that PDF instead." (`.ods`: LibreOffice wording). Same three places as DPB-026 decision 10 (live in the media picker via `cptdpDocument.pptxWarnings` extended to `officeWarnings`, persistently in the Document File and Page images boxes, once after saving); for `.xlsx` and users who can `manage_options` a link to the Native viewer section ("…or let the native viewer draw Excel files: visitors can scroll, zoom and copy cells; slower to open; nothing is sent to anyone"). `.xls` and `.ods` get no link. `assets/admin-document.js` is the only existing file that needs a generalisation (extension → kind → warning).
9. **Native viewer settings.** `cptdp_documents_native_formats()` gains **Excel (.xlsx)** (`native_xlsx`, default false) after PowerPoint, with the explanation text "scroll, zoom, switch sheets and copy cells; `.xls` and `.ods` are not supported; no macros, charts and some formatting may differ". REST schema, sanitiser, partial-update behaviour and the form's unticked-means-off rule follow `native_pptx`. `cptdp_document_native_xlsx_url( $post_id )`: attachment URL of a `.xlsx` when the box is on and the file is within `cptdp_native_xlsx_max_bytes` (**15 MB**, lower than PowerPoint's 30 MB because the whole workbook is parsed in the visitor's browser), else `''`; independent of converted pages existing. To avoid three copies the two URL helpers share a private `cptdp_document_native_office_url( $post_id, $kind, $setting, $filter, $default_limit )`.
10. **DDEV tooling and fixtures.** `.ddev/config.yaml` `webimage_extra_packages` gains `libreoffice-calc` (`ddev restart`, then `install-wp-tests.sh` again). `tests/fixtures/` gets `sample.xlsx`, `.xls` and `.ods` (3 sheets: a small table, a wide sheet of 40 columns, a sheet with a formula column and a number format; made by LibreOffice from a readable `sample.fods`, with the README note on regenerating). Cases for bombs, macros, external links, connections and the size limit are built in memory.
11. **A "Document conversion" section in Settings → Documents, next to "Native viewer" (to do).** Today the conversion status lives in the first "Documents" section as one read-only row per family ("PowerPoint conversion", "Excel conversion", `cptdp_documents_render_pptx_status()` and `cptdp_documents_render_xlsx_status()` in `includes/settings/documents.php`), and **each row repeats the same sentence**: "The result may differ from the original, so a PDF export is still the best option." A third format (DOCX) would repeat it a third time. Fix it the way the Native viewer section is built:
    - **A new section "Document conversion"** (`cptdp_documents_conversion_section`, registered between "Documents" and "Native viewer"; the order on the page is Documents, Document conversion, Native viewer). Its intro says, **once**, what is common to every format: files that are not PDFs are converted to page images on the server when a document is saved (visitors never trigger it), the result may differ from the original, so a PDF export is still the best option, and a host without the converter shows the PDF advice to editors.
    - **One field per format family with only what is specific to it** (no repeated sentence): the status (**Available** with the LibreOffice version, or **Unavailable** with the reason from `unavailable_reason( $kind )`) and one line of format detail. PowerPoint (.pptx, .ppt, .odp): fonts are substituted, animations and transitions are not exported, speaker notes and hidden slides are left out. Excel (.xlsx, .xls, .ods): one page per sheet, very wide sheets are shrunk, the saved values show (formulas are not recalculated), hidden sheets are not exported, macros and external links are refused. A later DOCX row adds its own line the same way.
    - **One shared renderer** instead of two near-identical functions: `cptdp_documents_render_conversion_status( string $kind, string $detail )` (availability, version, reason), called by a field per family; the families and their detail text come from one small table so a new format is a table entry, like `cptdp_documents_native_formats()`.
    - **Optional, same section:** a PDF row (Imagick or Ghostscript available or not, the engine in use), and moving the "Page image mode" and "Page margins" (`pages_mode`, `trim_bottom`) fields here because they are conversion options. They stay where they are unless that is wanted, so the REST schema and saved option keys do not change either way.
    - **No behaviour change:** no option keys, REST schema, filters or availability logic change; only the section, the intro text and the rendering of the status rows. The `#cptdp-native-viewer` anchor is untouched.

## Out of scope (and what comes next)
- **DOCX** (separate ticket; same Native viewer section, `docx_parser_bg.wasm` is 2.26 MB).
- Native viewing of `.xls` and `.ods`; CSV/TSV documents; charts as separate images; keeping the temporary PDF as a download; a "print area only" option; conversion of `.xlsm`, `.xlsb`, `.numbers`.

## Risks / open points
1. **Production tooling unknown** (as DPB-026 Risk 1): production now needs `libreoffice-calc` as well as Impress. Without it spreadsheets end `failed` with the PDF advice (or `none` for a `.xlsx` with the native box ticked).
2. **Spreadsheets are the worst fit for page images**: big grids, tiny text, one-page-per-sheet can be unreadable at book size. This is why the native viewer is offered; copy must not promise fidelity.
3. **Memory and time.** Calc loads whole workbooks in memory; the 100 MB file cap, the 120 s timeout, the one-at-a-time lock and the 300-page cap bound it, but a 50 MB workbook can take minutes and 1 GB. Consider a lower cap for spreadsheets (**Decide**, suggest 25 MB, filter `cptdp_xlsx_max_bytes`).
4. **`.xls` is the weakest input** (no cheap external-link check, macro detection by scan). If the scan proves unreliable refuse `.xls` with the PDF advice.
5. **Formula injection is not a risk here** (nothing is exported to CSV or re-saved); recorded so nobody adds an export later without sanitising.
6. **Sensitive data.** Spreadsheets are the format most likely to hold personal or financial data. Conversion stays on the server and the native viewer sends nothing to anyone, but stored page images are public files under `uploads/cptdp-pages/` for any published document. Add one sentence to the editor warning: "Everything in the workbook's visible sheets is published." Hidden sheets are not exported by Calc, but in native mode the whole file is downloaded by the visitor, **including hidden sheets and cell comments** (the viewer's options for hidden sheets must be set to "never show", see DPD-027). Flag this clearly in the Native viewer text.
7. **Fonts** as DPB-026 decision 8; Calc also needs the same metric-compatible set (already installed for Impress).
8. **The settings page and editor scripts are tested by source structure and rendered HTML only**, as before.

## Steps
One conventional commit each, scope `custom-post-types-dp`, **tests never in a feature commit**, suite green at every commit.
1. **Extract the soffice runner** (refactor, test): `CPTDP_Soffice`, `CPTDP_Pptx_Converter` delegates, lock rename, filter aliases; presentations behave exactly as before.
2. **Spreadsheet kinds** (feat, test): kind constants, mime map, `is_spreadsheet_kind()`, `is_office_kind()`, manifest marks, no trim, status wording.
3. **Calc converter** (feat, test): `class-xlsx-converter.php` (`inspect()`, export options, profile settings, `is_available()`), `convert()` dispatch, fixtures.
4. **Scheduler and REST** (feat, test): Office branch of `ensure()`, the native exception for `.xlsx`, `retry`, `cptdp_office_server_only`, CLI.
5. **The warning** (feat, test): `document-spreadsheets.php`, the picker script generalisation, after-save notice, Settings status row.
6. **Native viewer settings** (feat, test): `native_xlsx`, REST schema, `cptdp_document_native_xlsx_url()`, shared helper, the warning's link.
7. **Document conversion section** (feat, test; follow-up, decision 11): the new section and intro, the shared `cptdp_documents_render_conversion_status()`, one field per family with only its own detail, the repeated sentence removed from the PowerPoint and Excel rows; update `tests/settings/test-documents.php` and the spreadsheet and presentation tests that read the status rows.
8. **DDEV and version** (chore, test): `libreoffice-calc`, plugin `Version` 0.21.0.
9. **Docs** (docs): AGENTS.md (kinds, validation, settings, DDEV, totals).
10. **Plan** (docs): this plan, last on the shared branch with DPD-027's.

## Verification
```bash
ddev restart                      # once: installs Calc
ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"
ddev exec "soffice --version"
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```
Manual:
1. **Warning on pick:** pick or upload a `.xlsx`, `.xls`, `.ods`: warning at once with the PDF advice and the publication sentence; link only for `.xlsx` and administrators; a PDF or Remove hides it; one-time notice after saving.
2. **Conversion:** `Pending` then `Ready, N pages (converted from Excel)` (`ddev wp cron event run --due-now`); one page per visible sheet (or the chosen layout), hidden sheets absent, no `cptdp-pptx-*`/soffice folder left in `/tmp`, the wide fixture shrunk not cut.
3. **No Calc / no LibreOffice:** `Failed` with the PDF advice in every `pages_mode`, never `needs_browser`; `.xlsx` with the box ticked is `none`; Regenerate retries on the server.
4. **Bad files:** renamed text file, `.xlsx` with an external link, one with `connections.xml`, an `.xlsm`, an `.ods` with Basic, an `.xls` with a VBA project, over-limit file: each `Failed` with its own message and nothing left behind. A workbook with a cell formula pointing at a URL (`WEBSERVICE`) makes **no outbound request** (check with a local listener).
5. **Settings:** Native viewer section has PDF, PowerPoint, Excel; a separate **Document conversion** section (decision 11) states once that the result may differ and a PDF export is best, then shows one status row per family with only its own detail (the sentence appears exactly once on the page); a REST `PUT` with one key keeps the others.
6. **Security:** `retry` refused without `edit_post`; `start`, `<n>`, `finish` return 400 for a spreadsheet.
7. `ddev wp cptdp pages generate --all --sync` covers spreadsheets; `pages status` shows `kind`.

## Commit messages
Each ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
1. `refactor(custom-post-types-dp): extract the libreoffice runner from the pptx converter`
2. `test(custom-post-types-dp): cover the shared libreoffice runner`
3. `feat(custom-post-types-dp): treat spreadsheets as a document page source`
4. `test(custom-post-types-dp): cover the spreadsheet source kinds`
5. `feat(custom-post-types-dp): convert xlsx, xls and ods documents to page images with libreoffice calc`
6. `test(custom-post-types-dp): cover the spreadsheet converter`
7. `feat(custom-post-types-dp): run spreadsheets through the server-only pipeline`
8. `test(custom-post-types-dp): cover spreadsheet scheduling and the office retry route`
9. `feat(custom-post-types-dp): warn that spreadsheet conversion may be imperfect`
10. `test(custom-post-types-dp): cover the spreadsheet warning`
11. `feat(custom-post-types-dp): add excel to the native viewer settings`
12. `test(custom-post-types-dp): cover the excel native viewer setting and url`
13. `chore(custom-post-types-dp): add libreoffice calc to ddev and bump version to 0.21.0`
14. `docs(agents): document spreadsheets and the excel native viewer`
15. `docs(plans): add DPB-027 plan` (with the DPD-027 plan, last of the branch)

One PR for the shared branch: `feat(documents): DPB-027 DPD-027 spreadsheet conversion and Excel native viewer` (template `.github/pull_request_template.md`). Call out: DDEV needs `ddev restart` for Calc; production needs `libreoffice-calc`; DPB-027 commits are the plugin and DPD-027 commits are the theme, for two review passes.

## As built
Plugin suite **687 tests** (12 skipped; 664 before), all green. Verified for real in DDEV (LibreOffice and Calc 25.2.3): the `sample.xlsx`, `.xls` and `.ods` fixtures (a believable Tidy Teams budget tracker, 3 sheets: Summary with budget against actual, a 15-column FY2027 Monthly Budget, and a Headcount roster; styled headers, currency and percentage formats, formulas including cross-sheet ones) each convert in about 0.4 s to **3 pages of 1800 x 1273**, formulas show their values, no `cptdp-pptx-*` folder is left.
- **Not as planned:** (1) **No separate `CPTDP_Soffice` runner.** `CPTDP_Pptx_Converter` was made kind-aware (`is_available( $kind )`, `unavailable_reason( $kind )`, `to_pdf()` picks the export filter and writes the Calc profile for spreadsheets) and `CPTDP_Xlsx_Converter` holds only the spreadsheet checks, filter and profile. Same behaviour, much less moved code; the `cptdp_pptx_*` filter names and the `cptdp_pptx_lock` transient are unchanged. (2) The REST error code stays `cptdp_pptx_server_only` for spreadsheets (no `cptdp_office_server_only`). (3) **Calc detection** is a file check (`libsclo.*` next to the soffice binary), not a cached trial conversion. (4) **Uniform page canvas** (decision 3 follow-up, found by looking at the first output: sheets came out at 605x151, 6045x484 and 302x937, which the book cannot show): every sheet page is placed on one 1800x1273 white canvas, top left, small sheets re-drawn up to 2x and big ones shrunk. Only the Imagick path does this; the Ghostscript fallback would leave sheets at their own sizes. (5) Recalculation is **never** (the saved numbers show), link updates never, macros off, written to the throwaway profile (`CPTDP_Xlsx_Converter::write_profile()`; the file is valid XML, not yet proven to change LibreOffice's behaviour for a workbook that really needs recalculating). (6) The size cap for spreadsheets is **25 MB** (`cptdp_xlsx_max_bytes`).
- **Known:** a very wide sheet (the 15-column Monthly Budget fixture) is shrunk to fit and is small in the book: that is what the native viewer and the warning are for. The `.xls` macro scan and external-link rules are as weak as for `.ppt`. The warning, settings page and Regenerate flow were checked by rendered HTML only, not in wp-admin.
- Dev database: a test document (ID 125, "Q3 2026 Operations Budget Tracker (Mockup)", a Tidy Teams budget workbook with category, author, tool, description and cover style set) was created and the option `cptdp_documents` was set to `native_xlsx` on, everything else at its default.

### Cleanup pass (after the first implementation)
A four-angle review (reuse, simplification, efficiency, altitude) was applied; behaviour and test totals are unchanged (plugin 687).
- **Shared instead of copied:** `CPTDP_Pptx_Converter::ole_status()` (the `_VBA_PROJECT` scan for `.ppt` and `.xls`), `rels_have_external_object( $xml, $extra )` and `odf_has_external_object( $xml, $elements )` are used by both families; `CPTDP_Xlsx_Converter` keeps only `EXTRA_REL_TYPES` (`/connections`) and `ODS_LINKED_ELEMENTS`. Two native URL functions became wrappers of `cptdp_native_office_url()`; two warning builders share `cptdp_office_warning_html()`; all in the new `includes/post-types/document-office.php` (with `cptdp_native_viewer_enabled( $kind )`, which the scheduler now uses instead of a hardcoded pptx/xlsx pair).
- **Settings:** defaults, sanitiser, REST schema and the form loop derive from `cptdp_documents_native_formats()` through `cptdp_documents_checkbox_keys()`.
- **Efficiency:** each sheet is drawn once (size from a 72 dpi ping, then one read at the fitting dpi), the canvas uses `extentImage`, the intermediate Imagick object is cleared, `calc_installed()` is cached per request.
- **Simplified:** `is_available()` flattened, the page-count mismatch message is an if/else.
- **Deferred on purpose (do with the DOCX ticket):** rename `CPTDP_Pptx_Converter` to a neutral Office converter with a per-kind profile (inspector, export filter, size limit, availability probe), one kind registry shared by plugin, theme and JS (the theme copy of the mime map is deliberate: it works without the plugin), and the per-format test duplication.
- **Caught on the way:** an ungrouped regex alternation briefly let external `.ods` objects through (the test caught it); the settings reader briefly clobbered its `$key` argument.
