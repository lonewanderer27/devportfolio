# Plan: Document page names (DPB-030, Document - Generate Page Names)

Plugin: `custom-post-types-dp` 0.22.0 -> **0.23.0** (shared with DPB-031, same release). **No theme change** (the theme side is DPD-020, which reads `names`). Thumbnails are the sibling ticket `DPB-031-Document-Generate-Page-Thumbnails.md`; the two are independent. Branch `feat/DPB-030-Document-Generate-Page-Names`, cut from `main`. Builds on DPB-025 (PDF page images), DPB-026/027/028 (PowerPoint, Excel, Word conversion).

> **Status: implemented, uncommitted.** Plugin suite green (726 tests), run on the 14 dev documents with `pages names --all` and one `pages generate 129 --force --sync` (Word). Not run on a real phone or in wp-admin by hand. See **As built** at the end for what differs from this plan.

## Goal
When a document's pages are made, also make a **name per page** (what the DPD-020 Pages list shows), stored with the page set, tied to the same fingerprint and returned by `cptdp_get_document_pages()`. Visitors see nothing new from this ticket.

## Context (what exists, do not rebuild)
- The pipeline: `CPTDP_Page_Scheduler::ensure()` -> WP-Cron job -> `CPTDP_Page_Converter::convert()` (Imagick, then Ghostscript) -> `CPTDP_Page_Store` (folder `uploads/cptdp-pages/<attachment_id>-<hash8>-<document_id>/p-001.webp`, manifest and status in non-REST meta `cptdp_document_pages`, `cptdp_document_pages_status`, `cptdp_document_pages_error`). Office files go through `CPTDP_Pptx_Converter::to_pdf()` to a temporary PDF that is removed afterwards. Browser-made sets (`needs_browser`, PDF only) are drawn by vendored pdf.js in the admin and uploaded through `class-page-rest.php`.
- Nothing reads names today: no field, no outline reader, no payload key. DDEV has Ghostscript 10.05 (`/usr/share/ghostscript/10.05.1/lib/pdf_info.ps`), Imagick and Python, but **no** qpdf, mutool or pdftk, and the plugin has no runtime Composer dependency.
- Visitors never trigger server work; this stays true (everything runs at conversion time or in the admin).

## Behaviour

### Names (one string per page, `''` where there is none)
| Source | Rule |
|---|---|
| **PDF** | The document outline (bookmarks) mapped to page numbers. A bookmark names its page **and every page after it until the next bookmark** (a 5-page chapter shows its title on all 5; **Decide**: only on its first page, recommended every page so no row is blank). Nested bookmarks: the **deepest entry that starts on or before the page** (**Decide**, recommended; alternative: top level only). Pages before the first bookmark have no name. No bookmarks, encrypted, unreadable or malformed outline = no names, never a failed conversion. |
| **Word** (`.docx`, `.doc`, `.odt`) | Converted to PDF by LibreOffice Writer first; its PDF export writes **bookmarks from headings** (`ExportBookmarks`, set explicitly in the export filter options). The same outline reader runs on the **temporary PDF before it is deleted**. No headings = no names. No extra parser for Word. |
| **Excel** (`.xlsx`, `.ods`) | The **visible sheet names, in order**, hidden sheets skipped (they are not drawn): `xl/workbook.xml` `<sheet name state>`, ODS `content.xml` `table:name` / `table:display`. One page per visible sheet, so the list is trusted **only when its length equals the converted page count**, else dropped. `.xls` (OLE): no names (**Decide**: ask LibreOffice, recommended no). |
| **PowerPoint** (`.pptx`, `.ppt`, `.odp`) | **Blank** (owner). Slide titles are a later ticket. |
Names are `sanitize_text_field()`, collapsed whitespace, max 200 characters, at most the page count. A name never contains markup.

### Storage and API
- Manifest key (inside non-REST meta `cptdp_document_pages`): `page_names`, stored as **ranges** `[ [ first_page, title ], ... ]` (compact; omitted when there are no names) and expanded by the API to one string per page. **No admin field, no new meta key, no REST exposure**, valid only while the PDF fingerprint matches, cleared with the set.
- `cptdp_get_document_pages()` returns `names` (always an array the length of the page count, or `[]` when none) next to the existing `images`.
- Documents made before this ticket have no names until backfilled: `ddev wp cptdp pages names --all` (PDF and Excel, fast, no reconversion) and `ddev wp cptdp pages generate <id> --force` for Word documents. Their `names` is simply `[]` (the theme shows unit words).
- WP-CLI: `pages status` gains a `names` column (count of non-empty names); `pages generate` is unchanged; new `pages names [<id>|--all]` backfills from the stored source file without re-rendering.

## Architecture

| File (`includes/documents/` unless noted) | Change |
|---|---|
| `class-page-outline.php` (new) | `CPTDP_Page_Outline::from_pdf( $path, $page_count )` -> `string[]` (name per page, `''` where none): reads the outline with the reader chosen in the spike, resolves destinations to page numbers, applies the chapter and nesting rules, sanitises. Never throws; any failure returns `[]`. A time and size cap (filter `cptdp_outline_max_entries`, default 2000) guards huge outlines. |
| `class-xlsx-converter.php` | `sheet_names( $path, $kind )` for `.xlsx` and `.ods` (visible sheets only), reusing the existing zip-bomb limits and content checks; `.xls` returns `[]`. |
| `class-pptx-converter.php` (and the Word conversion path from DPB-028) | After `to_pdf()` and before the throwaway folder is removed: for Word, call the outline reader on the temporary PDF; set `ExportBookmarks` in the Writer filter options. Impress (PowerPoint) is skipped on purpose. |
| `class-page-converter.php` | PDFs call the outline reader on the source PDF and return the names to the caller. |
| `class-page-store.php` | Manifest key `page_names`; `names()` accessor; fingerprint and clearing handled with the set; `cptdp_get_document_pages()` returns `names`. |
| `class-page-rest.php` + `assets/admin-document.js` | Browser runs send names (from `pdf.getOutline()` and `getPageIndex()`, the same rules) on `finish`, validated (array of strings, length, max 200 characters each, sanitised). PDF only (presentations never use the browser route). |
| `includes/cli/` (pages command) | `pages status` `names` column. |
| `custom-post-types-dp.php` | Version 0.23.0. |
| `tests/documents/` (and `tests/post-types/test-document.php`) | New and extended tests (see steps). Fixtures: a bookmarked PDF (nested, multi-page chapters), a PDF without bookmarks, a Word file with headings (regenerate from `sample.fodt`, as DPB-028 does), a workbook with a hidden sheet, a malformed outline. |

## Performance
- **Run only when it can help, and once.** The outline is read in the same cron run that makes the images, from a file already on disk (the source PDF, or the temporary Writer PDF for Word, **no extra LibreOffice run**). PowerPoint skips it entirely. No visitor request ever reads an outline, a workbook or a PDF.
- **Bounded work:** a time cap (filter `cptdp_outline_timeout`, default 15 s), an entry cap (2000), a file-size cap (the same as conversion) and, for a pure-PHP reader, **streaming**: read the trailer, cross-reference data and only the outline objects, never the whole PDF into memory. Any cap hit means no names, never a failed or slow conversion.
- **Excel is a single zip entry:** `ZipArchive::getFromName( 'xl/workbook.xml' )` (or `content.xml`, size-capped) without extracting the archive, then a streaming `XMLReader` over the sheet list.
- **Store compactly:** the manifest keeps chapters as ranges (`[ [ first_page, title ], ... ]`, a 300-page report with 6 chapters stores 6 entries), and `cptdp_get_document_pages()` expands them to one string per page. A document with no names stores **nothing** (key omitted, `names = []`).
- **Send compactly:** the theme sends `pageNames` only for documents that have at least one name, and as the ranges (the sheet expands them): a few hundred bytes per document in the modal payload that carries every published document. Specified in DPD-020.
- **Backfill without re-rendering:** `ddev wp cptdp pages names [<id>|--all]` reads names from the stored source file only (PDF outline, workbook sheet names): no image work, seconds. Word needs its converted PDF, which is deleted, so Word documents need `pages generate --force` once; PowerPoint is blank anyway.
- Sequential, memory freed per document; `--all` never runs documents in parallel.

## Decisions and rationale
1. **Names are read at conversion time and stored**, never in the visitor's browser: stored-image books never load pdf.js, and the browser cannot read a Word or Excel file's structure cheaply.
2. **Word reuses the PDF outline reader**, because the pages already come from a Writer-made PDF with heading bookmarks: one reader, two document families.
3. **Excel names are exact or absent** (count must match), so a name is never attached to the wrong sheet.
4. **PowerPoint blank** by owner decision; the schema allows filling it later with no migration.
5. **Everything degrades silently:** a failed outline or a wrong sheet count leaves the page images and the document's status untouched (`ready`); names are simply `[]`.

## Risks and open points
1. **PDF outline extraction is the unknown.** Options in order: (a) Ghostscript (`pdf_info.ps`, or a short PostScript/`-dPDFINFO` job) to dump outlines with page numbers, (b) the admin-browser pdf.js run for everything (works for PDFs but not for server-converted Word without a second PDF round trip), (c) a small vendored pure-PHP outline reader (object streams and cross-reference streams make this the heaviest). The spike decides; the PHP class hides it.
2. **Outline quality varies:** bookmarks that point by name or action rather than page, outlines pointing past the last page, duplicate titles, very long titles, non-UTF-8 titles. All must map or drop cleanly. Word names are only as good as the author's heading styles.
3. **LibreOffice Writer keeps bookmarks** in this DDEV image and with the profile `CPTDP_Docx_Converter` writes: to confirm in the spike.
4. **Host differences:** a host with Imagick only (no Ghostscript) gets no PDF names from option (a); it should still convert (names `[]`).
5. **Privacy:** names come from content the owner uploaded and publishes with the pages; Excel sheet names are visible in the converted pages anyway. Hidden sheets are skipped (and noted: hidden sheet names are never exposed by this ticket).

## Steps (one conventional commit each, scope `custom-post-types-dp`; tests never inside a feature commit; plugin and theme suites green at every commit)
0. **Spike (no commit):** prove the PDF outline reader on the DDEV box against a bookmarked PDF, a Writer-made PDF with headings and a PDF with none; confirm Writer's PDF export keeps bookmarks. Record the choice here.
1. `feat(custom-post-types-dp): read page names from PDF and Word outlines` (`class-page-outline.php` with time/entry/size caps, converter and Word hook, manifest `page_names` as ranges, API `names`, `pages names` backfill).
2. `test(custom-post-types-dp): cover PDF and Word page names` (mapping incl. chapters, nesting, no bookmarks, malformed outline, Word fixture, never blocks conversion, PowerPoint blank, cleared with the set).
3. `feat(custom-post-types-dp): read sheet names from Excel files` (`sheet_names()`, count check, hidden sheets).
4. `test(custom-post-types-dp): cover Excel sheet names` (xlsx and ods, hidden sheet skipped, count mismatch drops names, `.xls` empty).
5. `docs(agents): document page names` (Overview section, manifest key, `pages status`, backfill command, version, test totals).

Verify: `ddev wp cptdp pages names --all` (and `pages generate <id> --force --sync` for a Word document) then `ddev wp cptdp pages status` shows the names counts (bookmarked PDF, Word with headings, workbook, PowerPoint blank); a document with no outline shows 0 and still converts.

## Built with the plan's recommendations (no separate owner answer)
The owner said to implement the plans as written, so the recommended answers were used: a PDF bookmark's title shows on **every** page of its chapter, the deepest bookmark that starts on or before a page names it, and `.xls` workbooks get no sheet names. Easy to change later (`CPTDP_Page_Outline::expand()` and `CPTDP_Xlsx_Converter::sheet_names()`).

## Questions for the owner (before the build)
1. **PDF chapters:** a bookmark's title on every page of its chapter (recommended) or only on its first page? Nested bookmarks: deepest entry on or before the page (recommended) or top level only?
2. **`.xls` workbooks:** no sheet names (recommended) or ask LibreOffice for them?

## As built (DPB-030)

**Files**
- New: `includes/documents/class-page-outline.php` (`CPTDP_Page_Outline`, plus three tiny value classes `CPTDP_Pdf_Name`, `CPTDP_Pdf_Str`, `CPTDP_Pdf_Ref` in the same file), `tests/documents/test-page-names.php` (16 tests, group `page-names`).
- Changed: `class-page-converter.php` (`convert_pdf_file()` calls the new public `page_names()`; names are stored as `page_names`), `class-docx-converter.php` (`export_filter()` now `pdf:writer_pdf_Export:{"ExportBookmarks":{"type":"boolean","value":"true"}}`), `class-xlsx-converter.php` (`sheet_names()`), `class-page-store.php` (`get_pages()` returns `names` and `name_ranges`; `refresh_names()`), `class-page-rest.php` (the browser `finish` route accepts `pageNames`, sanitised), `assets/admin-document-pages.js` (`readNames( doc )` from `getOutline()` and `getPageIndex()`), `cli/class-pages-command.php` (`pages names`, `names` column in `pages status`), `custom-post-types-dp.php` (0.23.0).

**Spike result (step 0):** Ghostscript has no outline dump (`pdf_info.ps` has none) and the DDEV box has no qpdf, mutool or pdftk, so option (c) was built: a small read-only **pure-PHP reader**. It finds objects by scanning `N G obj` (later definitions win, so incremental updates work), reads the catalog, page tree, outline, `/Dests` dictionary and `/Names /Dests` name tree, and inflates object streams (`FlateDecode`, no predictor). It was proved on a LibreOffice-made PDF and on a Ghostscript `pdfwrite` PDF with object streams before any wiring. LibreOffice Writer keeps heading bookmarks with `ExportBookmarks`.

**Differences from the plan**
- Filters: `cptdp_outline_max_bytes` (50 MB) and `cptdp_outline_timeout` (15 s); the entry cap (2000) and the page cap (5000) are constants. The whole file is read into memory up to the size cap (no streaming parser); this is why the cap exists.
- Encrypted files are detected by an `/Encrypt` entry anywhere in the file and give no names. A page tree whose size differs from the converter's page count gives no names.
- Titles: UTF-16BE/LE and UTF-8 BOM are decoded, anything else is read as Windows-1252; `wp_strip_all_tags()`, whitespace collapsed, 200 characters. A title containing `<script>x</script>Two` becomes `Two`.
- `sanitize_ranges()` is the single clean-up used on read from storage, on the browser upload and in the converter (sorted by page, one range per page, the last one given wins).
- Excel reads with `XMLReader` over `zip://path#entry`: `xl/workbook.xml` for `.xlsx` (`state` of `hidden` or `veryHidden` skipped), `content.xml` for `.ods` (tables that are direct children of `office:spreadsheet`, `table:display="false"` skipped, cells skipped with `next()`), 10 s and 2,000,000 node caps. Trusted only when the count equals the converted page count.
- `refresh_names()` (the backfill) supports PDF and Excel; Word needs `pages generate <id> --force` once, presentations return `unsupported`.
- `pages status` shows `names` (count of non-empty names per page).
- Version is **0.23.0 shared with DPB-031**.

**Results on the dev site:** bookmarked PDFs, a browser-made PDF (11 pages), the Word playbook (6 headings) and the Excel tracker (Summary, Monthly Budget, Headcount) all got names; PDFs without bookmarks and PowerPoint got none. The payload on `/documents/` carries `pageNames` as ranges only for documents that have them.

**Tests (16):** flat outline and chapter spans, pages before the first bookmark, nested bookmarks (deepest wins), named destinations and UTF-16 titles, an outline inside an object stream, no outline / page-count mismatch / encrypted / not a PDF / truncated / malformed / missing file, a looping outline (the reader stops with what it had), the size cap filter, range clean-up, sample workbooks (`.xlsx` and `.ods` give three names, `.xls` none), hidden sheets skipped, the full convert flow stores and returns names, a PDF without an outline still converts, `refresh_names()` without converting, and a manifest with bad names is never trusted.

**Not done / still open:** hidden-sheet names are never exposed (as planned). Word names are only as good as the author's heading styles. No browser-made set with bookmarks was uploaded by hand through wp-admin; the admin script path was only checked through the REST tests and by the backfill on the existing browser-made document.
