# @silurus/ooxml (PPTX viewer subset)

Vendored from https://github.com/yukiyokotani/office-open-xml-viewer, npm package `@silurus/ooxml` **0.89.7** (MIT, see `LICENSE` and `THIRD_PARTY_NOTICES.md`).

A read-only, in-browser viewer: a Rust/WebAssembly parser reads the `.pptx`, a Canvas 2D renderer draws one slide at a time. It runs entirely in the visitor's browser and sends nothing anywhere (fonts are not requested from any host unless `useGoogleFonts` is set, which the modal never does).

Only what `PptxViewer` needs is included (the import graph of `dist/pptx.mjs`, about 6 MB of 21 MB in the package): `pptx.mjs`, its shared chunks, `assets/render-worker-*.js` and `pptx_parser_bg.wasm` (about 2 MB, loaded next to `slide-pull-client-*.js`). The DOCX and XLSX engines, MathJax (equations), the 3D, region-map and chart-ex add-ons and the legacy `.ppt` add-on are **not** included, so those features degrade: equations and some chart types show placeholders, and `.ppt` / `.odp` are not supported.

It is loaded with `import()` only when a visitor opens a `.pptx` that has no server-made page images (see `../../viewer.js`), so nothing here is downloaded for other documents.

## Serving it well (host settings)

Measured on DDEV (headless Chrome): opening a deck downloads about 22 files and 555 KB (gzip) of JavaScript plus the WebAssembly file, and shows the first slide in roughly 200 ms.

- **Compress `application/wasm`.** `pptx_parser_bg.wasm` is 1.99 MB raw but 0.75 MB with gzip (0.57 MB with brotli), and many servers (including DDEV's nginx) do not compress it by default. nginx: add `application/wasm` to `gzip_types`. Apache: `AddOutputFilterByType DEFLATE application/wasm`. This saves about 1.2 MB on every first open of a native-viewer deck.
- **Serve `.mjs` as JavaScript and `.wasm` as `application/wasm`** (both are needed for the browser to run them).
- **Cache for a long time.** Every chunk has a content hash in its name. The three files that do not (`pptx.mjs`, `pptx-source-worker.mjs`, `pptx_parser_bg.wasm`) are requested with `?v=<version>` by `viewer.js` (`PPTX_VERSION`), so a long `Cache-Control: max-age` is safe.

## Updating

```bash
mkdir /tmp/ooxml && cd /tmp/ooxml && npm init -y && npm install @silurus/ooxml --ignore-scripts
# Copy dist/pptx.mjs and every file it imports (static and dynamic), plus dist/pptx_parser_bg.wasm,
# LICENSE and THIRD_PARTY_NOTICES.md into this folder, keeping the `assets/` subfolder.
```

Then check in a real browser that a deck loads with no failed requests, update the version above **and `PPTX_VERSION` in `../../viewer.js`**, and run the theme tests (`Devportfolio_Test_Document_Presentations` checks that the two agree and that only the three stable-name files lack a hash).
