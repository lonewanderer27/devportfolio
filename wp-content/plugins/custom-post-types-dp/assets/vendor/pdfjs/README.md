# pdf.js (vendored)

- Package: `pdfjs-dist@4.10.38` (npm), files from `build/`: `pdf.min.mjs`, `pdf.worker.min.mjs`
- Licence: Apache-2.0, see `LICENSE` in this folder
- Used by: the document page generator in wp-admin (`assets/admin-document-pages.js`) and, through
  `cptdp_document_pdfjs_urls()`, by the theme's document viewer fallback (`devportfolio/document-modal`).
- One shared copy. The theme's client render gate (`capability.js`, minimum browser versions) must
  match the browsers this pdf.js version supports: update both together when upgrading.
- Upgrade: `npm pack pdfjs-dist@<version>`, copy the two files and `LICENSE`, update this file.
