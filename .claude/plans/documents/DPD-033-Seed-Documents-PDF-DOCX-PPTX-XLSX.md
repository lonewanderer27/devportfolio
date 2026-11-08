# DPD-033 Seed documents: PDF, DOCX, PPTX and XLSX test fixtures

**For the implementing agent.** This plan is written to be followed without asking questions. Every decision is already made (section "Decisions"). If something here disagrees with the code you find, the code wins: fix the plan's "As built" section at the end and carry on. Read `AGENTS.md` (repo root) first; it describes every class named below.

## 1. Goal

Today `ddev wp cptdp seed documents` creates 8 documents **without files**, so none of them exercises the document viewer. The manual test lists of four tickets need real files of every kind:

| Ticket plan | File kinds it needs |
|---|---|
| `.claude/plans/documents/DPD-013-Document-PDF-Viewer.md` | PDFs: even portrait, odd portrait, landscape (1 and 3 pages), single page portrait, long, plus a document with no file |
| `.claude/plans/documents/DPD-026-Document-Modal-PPTX-Viewer.md` + `.claude/plans/backend/DPB-026-Document-PPTX-Conversion.md` | `.pptx` real deck, one-slide, 4:3, `.ppt`, `.odp`, corrupt `.pptx` |
| `.claude/plans/documents/DPD-027-Document-Modal-XLSX-Viewer.md` + `.claude/plans/backend/DPB-027-Document-XLSX-Conversion.md` | `.xlsx` small, 3 sheets with a hidden sheet and a wide sheet, `.xls`, `.ods`, corrupt `.xlsx` |
| `.claude/plans/documents/DPD-028-Document-Modal-DOCX-Viewer.md` + `.claude/plans/backend/DPB-028-Document-DOCX-Conversion.md` | `.docx` one page, 12 pages with table/image/footer/landscape section, `.doc`, `.odt`, corrupt `.docx` |

**Deliverable:** after `ddev wp cptdp seed --all` and `ddev wp cptdp pages generate --all --sync` on an empty database, `/documents/` shows a shelf where each case above has a document, and `ddev wp cptdp pages status` shows each as `ready` (or the expected failure for the corrupt ones).

**Out of scope:** any change to viewers, converters, scheduler or theme; committing the real Tidy Teams files; seeding the two 10 MB files (generated on demand only, see Decisions 4).

## 2. Context you need (verified against the repo, 6 Nov 2026, plugin 0.22.0)

- Plugin: `wp-content/plugins/custom-post-types-dp/` (call it `PLUGIN/`). All commands run through DDEV from the repo root: `ddev wp ...`, `ddev exec ...`. Never run PHP on the host.
- Seeder: `PLUGIN/includes/seeders/z-documents.php` registers seeder `documents` (CSV `PLUGIN/data/seeders/documents.csv`, columns `order,title,description,category,software_tools,filetype,total_pages,author`). Its callback `cptdp_seed_documents( bool $dry_run, string $csv_path )` validates every row first, resolves category terms and software tools, skips a title that already exists (`cptdp_seed_title_exists()`), and creates the post with `wp_insert_post()` (`meta_input`: `cptdp_filetype`, `cptdp_total_pages`, `cptdp_author`, `cptdp_software_tools`), then `wp_set_object_terms()`. It does **not** attach a file. The file name starts with `z-` on purpose (seeders run in file-name order and this one needs the others first). Return value: `array{created: string[], skipped: string[]}` or `WP_Error`.
- File meta: `cptdp_document_file` holds an **attachment ID**. `cptdp_document_sanitize_file()` (`PLUGIN/includes/post-types/document.php`) accepts any attachment that is not `video/*` or `audio/*`. Setting that meta (`added_post_meta`/`updated_post_meta`) makes `CPTDP_Page_Scheduler::ensure()` (`PLUGIN/includes/documents/class-page-scheduler.php`) queue page-image conversion in WP-Cron, so **attaching through `update_post_meta()` is enough**; do not call the converters yourself.
- Conversion kinds and limits are in `AGENTS.md` ("Document page images", "Presentations", "Spreadsheets", Word). LibreOffice **Writer, Impress and Calc** and Ghostscript are installed in DDEV (`soffice`, `gs`, `python3`). Python libraries `python-docx`, `openpyxl` and `python-pptx` are **not** installed: use LibreOffice and the Python standard library only.
- Fixtures that already exist for PHPUnit, in `PLUGIN/tests/fixtures/`: `sample.pptx`, `sample.ppt`, `sample.odp` (3 slides, 16:9), `sample.xlsx`, `sample.xls`, `sample.ods` (3 visible sheets, "Q3 2026 Operations Budget Tracker"), `sample.docx`, `sample.doc`, `sample.odt` (2 pages), `sample-operations-playbook.docx` (6 pages), readable sources `sample.fodp`, `sample.fods`, `sample.fodt`, and a `README.md` showing the soffice command pattern. **Do not move or edit them** (tests use them).
- Seeder test base class: `PLUGIN/tests/class-cptdp-seeder-test-case.php` (`seeder()`, `csv_rows()`, `run_seeder( $dry_run, $csv_path )`, `temp_csv( $contents )`, `items()`, `count_items()`), and `PLUGIN/tests/seeders/test-documents.php` extends it. The base class has generic tests (one published item per CSV row, idempotent second run, existing item not modified, trashed item not recreated) that **must keep passing**.
- Software tools that exist in `data/seeders/software-tools.csv` (use only these names in the CSV): Canva, Google Workspace, Claude, Notion, Slack, Asana, Adobe CC, ChatGPT, Salesforce, HubSpot. Categories (slugs): `executive-docs`, `operations-docs`, `presentation-decks`, `reports`.
- The existing dev database also holds 14 hand-made documents (IDs 93-129, titles such as "EA Workflow"). Titles in this plan are all prefixed `Sample ` so they can never collide with them.
- `.gitignore` is allowlist-style (see `AGENTS.md`, "What is tracked vs ignored"): everything under `wp-content/plugins/custom-post-types-dp/` is tracked except `vendor/`. Check with `git status` that new files show up as untracked, and add an ignore rule only for the generated folder below.

## 3. Decisions (already made, do not reopen)

1. **Append, do not rewrite.** The 8 existing CSV rows stay exactly as they are (they are also the "document with no file" case; their file cell is empty). New rows are appended with `order` 100 and up, in steps of 10. Existing tests about order and categories keep passing.
2. **Commit the fixtures**, synthetic content only (invented text, no real names or financials), in `PLUGIN/data/seeders/files/documents/`. Estimated total under 1.5 MB (the copied `sample.ppt` is 464 KB).
3. **One generator script in Python 3 standard library + LibreOffice**, `PLUGIN/bin/make-document-fixtures.py`, no committed XML sources: the script builds the flat-XML sources in a temp folder (so page counts are loops, not copy-paste) and converts them with `soffice`. Run it inside DDEV. Committed fixtures are the output; the script exists so they can be regenerated.
4. **The two 10 MB files are not seeded.** `make-document-fixtures.py --large` writes `workbook-10mb.xlsx` and `word-10mb.docx` into `PLUGIN/data/seeders/files/documents/generated/` (git-ignored); attach them by hand in wp-admin for the timeout test. Reason: a row whose file may be absent breaks the base seeder tests ("one published item per CSV row").
5. **Every file named in the CSV must exist** at seed time; if one is missing the whole run returns a `WP_Error` before writing anything (same all-or-nothing rule as the other validations).
6. **Word rows**: `total_pages` is only a hint (LibreOffice paginates differently from Word; a mismatch is corrected, not failed, by DPB-028). PDF rows must carry the **true** page count, otherwise the page set is marked invalid.
7. **Version bump**: plugin `0.22.0` becomes `0.23.0` in `PLUGIN/custom-post-types-dp.php` (`Version:` header and any version constant if present; grep for `0.22.0`).

## 4. The fixture files

Output folder `PLUGIN/data/seeders/files/documents/` (flat, no sub-folders except `generated/`). The generator writes/copies exactly these 25 files:

| # | File | How it is made | Pages / slides / sheets | Purpose |
|---|---|---|---|---|
| 1 | `pdf-portrait-1p.pdf` | Writer, A4 portrait | 1 | DPD-013 case D |
| 2 | `pdf-portrait-3pp.pdf` | Writer, A4 portrait | 3 | DPD-013 case B (odd count, empty right side on the last view) |
| 3 | `pdf-portrait-4pp.pdf` | Writer, A4 portrait | 4 | DPD-013 case A (even) |
| 4 | `pdf-landscape-1p.pdf` | Writer, A4 landscape | 1 | DPD-013 case C |
| 5 | `pdf-landscape-3pp.pdf` | Writer, A4 landscape | 3 | DPD-013 case C (3-page) |
| 6 | `pdf-portrait-24pp.pdf` | Writer, A4 portrait | 24 | DPD-013 gate and pager window |
| 7 | `pdf-corrupt.pdf` | first 1500 bytes of file 3 (`%PDF-` header, truncated) | n/a | failure path |
| 8 | `pptx-1-slide.pptx` | Impress flat ODP, 16:9 | 1 | DPD-026 |
| 9 | `pptx-4x3.pptx` | Impress, page 28 cm x 21 cm | 4 | DPD-026 |
| 10 | `pptx-corrupt.pptx` | first 2048 bytes of file 9 | n/a | DPD-026 corrupt |
| 11 | `sample.pptx` | copy of `tests/fixtures/sample.pptx` | 3 | DPD-026 normal deck |
| 12 | `sample.ppt` | copy of `tests/fixtures/sample.ppt` | 3 | never native |
| 13 | `sample.odp` | copy of `tests/fixtures/sample.odp` | 3 | never native |
| 14 | `xlsx-hidden-sheet.xlsx` | Calc flat ODS: sheet "Summary" (small table), sheet "Wide" (40 columns x 20 rows), sheet "Hidden notes" with `table:display="false"` | 3 sheets, **2 visible** | DPD-027 hidden + wide |
| 15 | `xlsx-corrupt.xlsx` | first 2048 bytes of file 14 | n/a | DPD-027 corrupt |
| 16 | `sample.xlsx` | copy | 3 | DPD-027 small workbook |
| 17 | `sample.xls` | copy | 3 | never native |
| 18 | `sample.ods` | copy | 3 | never native |
| 19 | `docx-1p.docx` | Writer, A4 | 1 | DPD-028 one page |
| 20 | `docx-12pp.docx` | Writer: heading, paragraphs, a 4x6 table, an embedded PNG, a footer with a page number field, and **one landscape section** (pages 7-8) | about 12 (LibreOffice decides) | DPD-028 main case |
| 21 | `docx-corrupt.docx` | first 2048 bytes of file 20 | n/a | DPD-028 corrupt |
| 22 | `sample.docx` | copy of `tests/fixtures/sample.docx` | 2 | tiny |
| 23 | `sample.doc` | copy | 2 | never native |
| 24 | `sample.odt` | copy | 2 | never native |
| 25 | `sample-operations-playbook.docx` | copy of `tests/fixtures/sample-operations-playbook.docx` | 6 | believable doc, same as dev document 129 |

(25 committed files; `README.md` and `generated/.gitignore` complete the folder.)

### Generator spec: `PLUGIN/bin/make-document-fixtures.py`

- Python 3 stdlib only (`argparse`, `pathlib`, `shutil`, `subprocess`, `tempfile`, `zipfile`, `base64`). Header comment says how to run: `ddev exec python3 wp-content/plugins/custom-post-types-dp/bin/make-document-fixtures.py [--large]`.
- Paths: the plugin root is two levels above the script; output `data/seeders/files/documents/`; copy sources from `tests/fixtures/`. Create the output folder if needed. Overwrite existing outputs (it is a generator).
- Conversion helper (copy the pattern from `tests/fixtures/README.md`): `soffice --headless --norestore -env:UserInstallation=file://<tempdir>/profile --convert-to <ext> --outdir <out> <source>` through `subprocess.run( [...list...], timeout=180, check=True )`; a fresh temp profile per run; never `shell=True`; fail loudly with the soffice stderr.
- Flat sources are written as strings in the script, using the same namespaces and layout as `tests/fixtures/sample.fodt` (text), `sample.fodp` (slides) and `sample.fods` (sheets); open those three files and copy their structure.
  - **Text (`.fodt`)**: paragraph style with `fo:break-before="page"` between pages (see `sample.fodt`), `N` pages from a loop. Portrait page layout is the default; landscape needs a page layout `style:page-layout-properties fo:page-width="29.7cm" fo:page-height="21cm" style:print-orientation="landscape"` with its master page applied (`style:master-page-name`). Each page has a heading "Sample page k of N" and two paragraphs of invented text so that the PDF text layer is selectable.
  - `docx-12pp`: 12 pages as above plus (a) a 4x6 `table:table` on page 2, (b) a `draw:frame` with a `draw:image` holding a base64 PNG in `office:binary-data` (embed a tiny 64x64 PNG built with `zlib`/`struct`, or a fixed base64 string), (c) a master page footer containing a `text:page-number` field, (d) pages 7-8 in a landscape master page. The page count comes out near 12; the plan only needs "more than 8".
  - **Slides (`.fodp`)**: start from `sample.fodp`; change only the page size (16:9 = 28 cm x 15.75 cm, 4:3 = 28 cm x 21 cm) and the number of slides.
  - **Sheets (`.fods`)**: start from the structure of `sample.fods`; hidden sheet = a table style with `<style:table-properties table:display="false"/>` applied to that `table:table`.
- Conversions: `.fodt` to `pdf`/`docx`; `.fodp` to `pptx`; `.fods` to `xlsx`. Corrupt files are `data[:n]` slices of the good file, written with `write_bytes`.
- `--large` writes the two 10 MB files into `generated/`: `workbook-10mb.xlsx` (a valid workbook: write a `.fods` with enough rows of pseudo-random numbers, convert it with `soffice`, and grow the row count until the `.xlsx` is at least 10 MB) and `word-10mb.docx` (a valid DOCX of about 10 MB: many paragraphs, or a stored incompressible PNG via `os.urandom`). Print the final sizes. Without `--large` these are not touched.
- After conversion print one line per file with its size; exit non-zero if any expected file is missing or empty. Total committed size must stay under 2 MB: print the total and warn above it.
- Add a short `README.md` in `PLUGIN/data/seeders/files/documents/`: what each file is (the table above), how to regenerate, and the note that the content is invented.
- Add `PLUGIN/data/seeders/files/documents/generated/.gitignore` containing `*` and `!.gitignore` so the large files are never committed.

## 5. The CSV

`PLUGIN/data/seeders/documents.csv`: add one new column **`file`** as the **last** column (header becomes `order,title,description,category,software_tools,filetype,total_pages,author,file`). The 8 existing rows get an empty last cell (append `,` to each line). Append these rows (keep CSV quoting rules: wrap any cell with a comma in double quotes; descriptions below avoid commas on purpose):

| order | title | category | software_tools | filetype | total_pages | file |
|---|---|---|---|---|---|---|
| 100 | Sample Portrait PDF, One Page | reports | Google Workspace | PDF | 1 | pdf-portrait-1p.pdf |
| 110 | Sample Portrait PDF, Three Pages | reports | Google Workspace\|Claude | PDF | 3 | pdf-portrait-3pp.pdf |
| 120 | Sample Portrait PDF, Four Pages | reports | Canva | PDF | 4 | pdf-portrait-4pp.pdf |
| 130 | Sample Landscape PDF, One Page | executive-docs | Canva | PDF | 1 | pdf-landscape-1p.pdf |
| 140 | Sample Landscape PDF, Three Pages | executive-docs | Canva\|Adobe CC | PDF | 3 | pdf-landscape-3pp.pdf |
| 150 | Sample Long PDF, Twenty-Four Pages | operations-docs | Notion\|Slack\|Asana\|Google Workspace | PDF | 24 | pdf-portrait-24pp.pdf |
| 160 | Sample Corrupt PDF | reports | (empty) | PDF | 4 | pdf-corrupt.pdf |
| 170 | Sample Three-Slide Deck | presentation-decks | Canva | PPTX | 3 | sample.pptx |
| 180 | Sample One-Slide Deck | presentation-decks | Canva | PPTX | 1 | pptx-1-slide.pptx |
| 190 | Sample 4:3 Deck | presentation-decks | Adobe CC | PPTX | 4 | pptx-4x3.pptx |
| 200 | Sample Corrupt Deck | presentation-decks | (empty) | PPTX | 3 | pptx-corrupt.pptx |
| 210 | Sample Legacy PowerPoint | presentation-decks | (empty) | PPT | 3 | sample.ppt |
| 220 | Sample OpenDocument Presentation | presentation-decks | (empty) | ODP | 3 | sample.odp |
| 230 | Sample Budget Workbook | reports | Google Workspace\|Salesforce\|HubSpot | XLSX | 3 | sample.xlsx |
| 240 | Sample Workbook with a Hidden Sheet | reports | Google Workspace | XLSX | 2 | xlsx-hidden-sheet.xlsx |
| 250 | Sample Corrupt Workbook | reports | (empty) | XLSX | 3 | xlsx-corrupt.xlsx |
| 260 | Sample Legacy Excel Workbook | reports | (empty) | XLS | 3 | sample.xls |
| 270 | Sample OpenDocument Spreadsheet | reports | (empty) | ODS | 3 | sample.ods |
| 280 | Sample One-Page Word Document | operations-docs | Google Workspace | DOCX | 1 | docx-1p.docx |
| 290 | Sample Twelve-Page Word Document | operations-docs | Notion\|Google Workspace | DOCX | 12 | docx-12pp.docx |
| 300 | Sample Corrupt Word Document | operations-docs | (empty) | DOCX | 2 | docx-corrupt.docx |
| 310 | Sample Two-Page Word Document | operations-docs | (empty) | DOCX | 2 | sample.docx |
| 320 | Sample Legacy Word Document | operations-docs | (empty) | DOC | 2 | sample.doc |
| 330 | Sample OpenDocument Text | operations-docs | (empty) | ODT | 2 | sample.odt |
| 340 | Sample Operations Playbook | operations-docs | Notion\|Slack | DOCX | 6 | sample-operations-playbook.docx |

For every new row: `author` = `Dave`; `description` = one plain sentence (no commas) saying what the file tests, e.g. "Three portrait pages: the last view has an empty right side." Write real sentences for all 25, not placeholders.

Rules the existing tests impose: titles unique and not matching any existing title; `order` strictly ascending down the file; categories must be among the four slugs; each software tool must exist in `software-tools.csv` (checked by name).

## 6. The seeder change (`PLUGIN/includes/seeders/z-documents.php`)

1. Add `'file'` to the `columns` array in `cptdp_register_seeder_documents()` **and** to the `cptdp_seed_read_csv()` call in `cptdp_seed_documents()` (it takes the expected column list, so both lists must match the CSV header).
2. Add constant-like helper `cptdp_seed_documents_files_dir(): string` returning `CPTDP_PATH . 'data/seeders/files/documents/'`.
3. In the validation loop (before any write) add, for a non-empty `file`:
   - reject when it contains `..`, a leading `/`, a backslash, or characters outside `[A-Za-z0-9._-]`;
   - reject when the extension (lower case) is not one of `pdf docx doc odt pptx ppt odp xlsx xls ods`;
   - reject when `is_readable( dir . file )` is false: message `File "x" for "title" (row N) does not exist in data/seeders/files/documents/`.
   Use the same `WP_Error( 'cptdp_seed_csv_row', ... )` shape as the other row errors. An empty `file` is valid (no attachment).
4. Add function `cptdp_seed_attach_document_file( int $post_id, string $relative_file )`: returns the attachment ID or `WP_Error`.
   - Copy the fixture into the uploads folder, never reference the fixture itself: `$upload = wp_upload_dir();` `$name = wp_unique_filename( $upload['path'], $relative_file );` `copy()` to `$upload['path'] . '/' . $name`; on failure return a `WP_Error`.
   - `wp_check_filetype( $name )` for the mime type (WordPress knows all ten extensions; do not touch `upload_mimes`).
   - `wp_insert_attachment( array( 'post_mime_type' => ..., 'post_title' => <file name without extension>, 'post_status' => 'inherit' ), $dest_path, $post_id )`. **Do not** call `wp_generate_attachment_metadata()` (not images).
   - `update_post_meta( $post_id, 'cptdp_document_file', $attachment_id )` as the **last** step, after the terms are set, so the scheduler sees a complete document.
5. In the creation branch (inside `if ( ! $dry_run )`), after `wp_set_object_terms()`, call it when `file` is non-empty and return the error if it fails. In dry-run nothing is copied or attached.
6. `$result['created']` keeps listing titles only (the seeder contract). Update the file's docblock: it now attaches files from `data/seeders/files/documents/`, run page generation afterwards.
7. Do not change `includes/cli/*` or the `cptdp_seeders` contract. The `--all` order stays.

## 7. Tests

Edit `PLUGIN/tests/seeders/test-documents.php` (class `CPTDP_Test_Seeder_Documents`). Use the helpers listed in section 2. Add:

- `test_csv_has_a_file_column_and_every_listed_file_exists`: header contains `file`; for each non-empty `file` the path exists in the files folder.
- `test_every_new_kind_is_covered`: the set of lower-case extensions across the `file` column includes all ten (`pdf docx doc odt pptx ppt odp xlsx xls ods`), plus at least one row with an empty `file`.
- `test_seeding_attaches_the_file`: after `run_seeder()`, for a row with a file, `cptdp_document_file` meta is an attachment whose file exists in uploads and whose basename starts with the fixture's name; for a row with no file the meta is empty.
- `test_dry_run_attaches_nothing`: `run_seeder( true )` creates no posts and no attachments (`count` of `attachment` posts unchanged).
- `test_bad_file_values_are_rejected_before_any_write` (data provider): `../x.pdf`, `/etc/passwd.pdf`, `a b.pdf`, `evil.exe`, `missing.pdf`: each via `temp_csv()` returns a `WP_Error` and `count_items()` is 0.
- `test_second_run_does_not_attach_twice`: run twice; attachment count equals the number of rows with a file.

New file `PLUGIN/tests/documents/test-seed-fixtures.php` (look at how other tests in `tests/documents/` are laid out and what base class they extend; use `CPTDP_Test_Case` if none fits): for each fixture in the folder assert the magic bytes (`%PDF-`; `PK` for docx/pptx/xlsx/odt/odp/ods; the OLE2 signature `D0CF11E0A1B11AE1` for doc/ppt/xls) and that the three `*-corrupt.*` files are rejected: `pdf-corrupt.pdf` is smaller than 2 KB and has no `%%EOF`; the three corrupt Office files fail `ZipArchive::open()` or, if your PHP reads the truncated zip, fail `CPTDP_Pptx_Converter::inspect()` / `CPTDP_Xlsx_Converter::inspect()` / the Word `inspect()` (find the Word class name in `PLUGIN/includes/documents/`; the class file is named in `AGENTS.md`). Also assert that `xlsx-hidden-sheet.xlsx` contains `state="hidden"` in `xl/workbook.xml` (read it with `ZipArchive`).

Conversion check (skip with `markTestSkipped` when LibreOffice is missing, copy the skip pattern from the existing converter tests in `tests/documents/`): converting `pdf-landscape-3pp.pdf` gives 3 images with width greater than height; `pptx-1-slide.pptx` gives 1 image; `pptx-4x3.pptx` gives 4 images with ratio about 1.33; `xlsx-hidden-sheet.xlsx` gives 2 pages; `docx-12pp.docx` gives more than 8 pages. Find the call to use in the existing tests (`CPTDP_Page_Converter::convert()` is described in `AGENTS.md`).

**Scheduler side effect to check:** seeding 25 files in a test fires `CPTDP_Page_Scheduler::ensure()` for each. Run the seeder tests; if they are slow, make loopback HTTP requests (`spawn_cron`), or leave scheduled events behind, neutralise it in `set_up()` of the documents seeder test with `remove_action( 'added_post_meta', array( 'CPTDP_Page_Scheduler', 'on_file_meta' ), 10 )` (the hook is added in `class-page-scheduler.php` around line 30; re-adding is not needed because the test lib restores hooks after each test).

Run (from repo root), all must pass before the PR:

```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Documents
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```

If the test library is missing, run `ddev exec "bash wp-content/plugins/custom-post-types-dp/bin/install-wp-tests.sh"` first. Known and expected before your change: the theme suite has 2 failures in `Devportfolio_Test_Navigation` (the Services link). Compare the plugin totals before and after and write the new totals into `AGENTS.md`.

## 8. Order of work (one commit per step; each commit ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`)

Work on a branch `feat/DPD-033-Seed-Documents`, never on `main`. Do not push or open a PR unless asked.

1. `build(custom-post-types-dp): add the document fixture generator` — `bin/make-document-fixtures.py`, then run it, check the output sizes and look at `pdf-portrait-3pp.pdf` page count with `ddev exec gs -q -dNODISPLAY -dNOSAFER -c "(path) (r) file runpdfbegin pdfpagecount = quit"`; same for the other PDFs (the counts in the table of section 4 must be exact for PDFs).
2. `feat(custom-post-types-dp): add document fixture files` — the generated files, `README.md`, `generated/.gitignore`. Check `git status` shows them as untracked/added and the total size.
3. `test(custom-post-types-dp): cover the document fixture files` — `tests/documents/test-seed-fixtures.php`.
4. `feat(custom-post-types-dp): attach fixture files in the documents seeder` — CSV (header, 8 old rows, 25 new rows) and `z-documents.php`.
5. `test(custom-post-types-dp): cover the documents seeder file column` — the seeder test edits.
6. `chore(custom-post-types-dp): bump version to 0.23.0`.
7. `docs(agents): document the seeded fixtures and the file column` — in `AGENTS.md`: update the Seeders paragraph (the `documents` seeder now creates 33 documents and attaches files from `data/seeders/files/documents/`, columns now include `file`), add the generator command, the `--large` files, the new test totals, and bump "Master plugin ... (v0.22.0" to v0.23.0.
8. `docs(plans): add DPD-033 plan` — this file, with the "As built" section filled in.

## 9. Verification on a real database (do this before the last commit; record results in "As built")

Take a snapshot first: `ddev snapshot --name before-dpd-033`. Then:

```bash
ddev wp cptdp seed --all --dry-run          # lists 25 new documents, writes nothing
ddev wp cptdp seed --all                    # on a DB that already has the categories and tools: the 25 are created, the 8 old and 14 hand-made are untouched
ddev wp cptdp pages generate --all --sync   # converts everything; takes a few minutes
ddev wp cptdp pages status                  # one row per document
```

Expected `pages status` (column `status`): `ready` for every row **except** `Sample Corrupt PDF` (`failed` or `needs_browser`: either is acceptable, write down which), and `failed` for the three corrupt Office files, each with a message that recommends uploading a PDF. Expected `pages` column: equal to the table in section 4 for PDFs; for decks and workbooks equal to the slide or visible-sheet count (hidden sheet gives 2); for Word files "more than 8" for the 12-page one. Re-run `ddev wp cptdp seed --all`: everything is reported as skipped (idempotent). Open `https://devportfolio.ddev.site/documents/`, then click one document of each kind and confirm the modal opens (PDF as a book, Word/PPTX/XLSX as converted pages; the corrupt ones show "can't be previewed"). Check `wp-content/uploads/cptdp-pages/` has one folder per converted document and no `cptdp-pptx-*` folder is left in `/tmp` (`ddev exec "ls /tmp | grep cptdp-pptx"`).

## 10. Do not

- Do not edit `tests/fixtures/*`, any viewer, converter or scheduler file, or `.ddev/config.yaml`.
- Do not commit anything from `wp-content/uploads/` or the real Tidy Teams files.
- Do not use `shell=True`, `os.system`, or string-built shell commands in the generator, and do not read paths from request input anywhere (the seeder reads only the CSV in the plugin).
- Do not run `ddev wp db reset` or point PHPUnit at the dev database (`db`); the tests use `wordpress_test`.
- Do not weaken a generic seeder test to make it pass; fix the data or the code.

## 11. As built

Implemented the generator, committed synthetic fixture set, CSV file column, upload attachment logic,
fixture tests and seeder tests. The generated PDF page counts are 1, 3, 4, 1, 3 and 24 as specified.
`pptx-1-slide.pptx` contains one slide and `pptx-4x3.pptx` contains four slides. The hidden workbook
contains the hidden-sheet marker and the corrupt fixtures are truncated.

Validation:

* `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seeder_Documents` — 25 tests passed.
* `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Seed_Fixtures` — 3 tests passed.
* Full plugin suite — 702 tests passed, 12 skipped.
* Theme suite — the two pre-existing Services navigation failures remain.

The generator uses LibreOffice and Ghostscript inside DDEV. The PDF multipage outputs are assembled
from generated single-page PDFs with Ghostscript because LibreOffice did not reliably honour repeated
flat-XML page-break styles in this environment. The committed fixture total is about 784 KB. The
real-database seed and page-generation commands were not run to avoid changing the existing dev database.
