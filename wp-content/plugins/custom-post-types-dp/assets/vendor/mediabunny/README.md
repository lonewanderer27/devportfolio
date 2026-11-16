# Mediabunny (vendored)

- Version: **1.66.0** (npm `mediabunny`), released under the **MPL-2.0** (see `LICENSE`; keep the notice at the top of the JS file).
- Source: https://github.com/Vanilagy/mediabunny , https://mediabunny.dev
- File: `mediabunny.min.js` is the package's `dist/bundles/mediabunny.min.cjs`, unchanged. Loaded with a plain `<script>`, it exposes the global `Mediabunny`.
- Used by `assets/admin-video-preview.js` (DPV-041) to cut a 6 second H.264 MP4 preview clip of an uploaded video in the admin's browser (WebCodecs). It is only enqueued on the Video edit screen, never for visitors.

## Update

```bash
cd "$(mktemp -d)" && npm pack mediabunny@<version> && tar xzf mediabunny-*.tgz
cp package/dist/bundles/mediabunny.min.cjs <plugin>/assets/vendor/mediabunny/mediabunny.min.js
cp package/LICENSE <plugin>/assets/vendor/mediabunny/LICENSE
```

Then change the version here and the `$version` of the `cptdp-mediabunny` script in `includes/post-types/video.php`, and re-check the API used in `admin-video-preview.js` (`Input`, `UrlSource`, `ALL_FORMATS`, `Output`, `Mp4OutputFormat`, `BufferTarget`, `Conversion.init`, `Quality`).
