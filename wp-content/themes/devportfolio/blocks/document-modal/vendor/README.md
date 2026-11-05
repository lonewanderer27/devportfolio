# Vendored libraries

## StPageFlip (`page-flip.browser.js`)

- Package: `page-flip` **2.0.7** (npm), file `dist/js/page-flip.browser.js`, unmodified.
- Licence: MIT, see `LICENSE` in this folder (Copyright (c) 2020 Nodlik).
- Used by `../viewer.js` for the open-book view. It is a UMD build that defines `window.St.PageFlip`, so the viewer loads it with a plain `<script>` tag on the first open of a document that has pages to show. Nothing loads it earlier.
- It injects its own small stylesheet (`.stf__*` classes) when it loads. The viewer sets `autoSize: false` and `size: 'fixed'`, so the book size comes from the theme, not the library.
- The package is unmaintained upstream; this copy is pinned on purpose.

## pdf.js

Not vendored here. The plugin `custom-post-types-dp` ships pdf.js 4.10.38 (Apache-2.0) in `assets/vendor/pdfjs/` so the admin page generator and this viewer share one copy. The viewer imports it from the URLs the plugin reports (`cptdp_document_pdfjs_urls()`, passed to the script as `data-pdfjs`), and only on the fallback path.

pdf.js 4.10.38's modern build uses `Promise.withResolvers` and `AbortSignal.any` without polyfills. The client render gate (`../capability.js`) therefore requires both features and sets its minimum browser versions to the first releases that have them: Chrome and Edge 119, Firefox 124, Safari 17.4. If the plugin's pdf.js is upgraded, re-check those floors.
