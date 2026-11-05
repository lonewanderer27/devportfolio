# Presentation fixtures (DPB-026)

`sample.pptx`, `sample.odp` and `sample.ppt` are the same three-slide, 16:9 deck (blue, green and red title bars on white, "Slide one/two/three"). They were made by LibreOffice from `sample.fodp` (a hand-written flat ODP, the readable source), so they are real files the converter and its validation accept.

Regenerate them (DDEV, needs LibreOffice):

```bash
ddev exec "cd /tmp && rm -rf fx && mkdir fx && cp /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/sample.fodp fx/ && cd fx && for t in pptx odp ppt; do soffice --headless --norestore -env:UserInstallation=file:///tmp/fx/prof --convert-to \$t --outdir out sample.fodp; done && cp out/sample.* /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/"
```

`sample.ppt` is large (about 450 KB) because LibreOffice embeds its master and style data in the legacy format. Tests that convert them skip when LibreOffice is not installed; the validation tests build their other cases (bombs, macros, external links) in memory.

# Spreadsheet fixtures (DPB-027)

`sample.xlsx`, `sample.xls` and `sample.ods` are one believable workbook, a Tidy Teams "Q3 2026 Operations Budget Tracker" (mock data, AUD): **Summary** (Q3 budget against actual by line item, variance and variance %, notes), **Monthly Budget** (a 15-column FY2027 budget by month, Jul 2026 to Jun 2027, with totals, EBITDA and margin) and **Headcount** (roles, hours, hourly cost and computed weekly cost). They have styled headers, currency and percentage formats and real formulas, including cross-sheet ones (the Summary budget column sums the Monthly Budget sheet). LibreOffice made them from `sample.fods`, the readable flat ODS source.

Regenerate them (DDEV, needs LibreOffice Calc):

```bash
ddev exec "cd /tmp && rm -rf fxs && mkdir fxs && cd fxs && cp /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/sample.fods . && for t in xlsx xls ods; do soffice --headless --norestore -env:UserInstallation=file:///tmp/fxs/prof --convert-to \$t --outdir out sample.fods; done && cp out/sample.xlsx out/sample.xls out/sample.ods /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/"
```

Tests expect **three sheets** (three pages). The validation cases (macros, external links, bombs) are built in memory.

Word fixtures: regenerate sample.docx, sample.doc and sample.odt from sample.fodt with LibreOffice Writer.

# Operations playbook sample

`sample-operations-playbook.html` is the readable source for the believable
six-page `sample-operations-playbook.docx` used by the Documents page demo. It
is a Q4 2026 operations playbook with delivery controls, weekly cadence,
support escalation, handoff guidance, templates, and an appendix. Regenerate
the DOCX with LibreOffice Writer:

```bash
ddev exec "rm -rf /tmp/q4-docx && mkdir -p /tmp/q4-docx && libreoffice --headless --convert-to odt --outdir /tmp/q4-docx /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/sample-operations-playbook.html && libreoffice --headless --convert-to docx --outdir /tmp/q4-docx /tmp/q4-docx/sample-operations-playbook.odt && cp /tmp/q4-docx/sample-operations-playbook.docx /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/"
```

The demo entry is Document post `129`, titled `Q4 2026 Operations Playbook`,
with attachment `128`. Its local upload path is
`wp-content/uploads/2026/11/dave-operations-playbook.docx` (uploads are
runtime data and are not committed); use the committed fixture to recreate it.
