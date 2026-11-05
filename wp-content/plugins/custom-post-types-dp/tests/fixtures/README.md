# Presentation fixtures (DPB-026)

`sample.pptx`, `sample.odp` and `sample.ppt` are the same three-slide, 16:9 deck (blue, green and red title bars on white, "Slide one/two/three"). They were made by LibreOffice from `sample.fodp` (a hand-written flat ODP, the readable source), so they are real files the converter and its validation accept.

Regenerate them (DDEV, needs LibreOffice):

```bash
ddev exec "cd /tmp && rm -rf fx && mkdir fx && cp /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/sample.fodp fx/ && cd fx && for t in pptx odp ppt; do soffice --headless --norestore -env:UserInstallation=file:///tmp/fx/prof --convert-to \$t --outdir out sample.fodp; done && cp out/sample.* /var/www/html/wp-content/plugins/custom-post-types-dp/tests/fixtures/"
```

`sample.ppt` is large (about 450 KB) because LibreOffice embeds its master and style data in the legacy format. Tests that convert them skip when LibreOffice is not installed; the validation tests build their other cases (bombs, macros, external links) in memory.
