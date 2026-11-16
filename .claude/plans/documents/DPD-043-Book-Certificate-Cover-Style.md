# Plan: Certificate cover style for the document book

Ticket DPD-043 (Small, User Interface), area *Book / Cover style*: *As an editor, I can pick a **Certificate** cover for a document so a diploma, licence or award looks like one on the Documents shelf.*
Branch `feature/DPD-043-Book-Certificate-Cover-Style` (already checked out). Builds on `DPD-005-006-008-Document-Book-Item.md` (the six styles and the `--cv`/`--ct`/`--cat-c` + `__pat` contract) and the already committed `cptdp_cover_style` editor option (`f1d7c1f`, `07a60a5`).

**Out of scope:** a new post type or taxonomy for certificates, per-document colours, the modal or viewer (they never draw the cover), the seeder (no cover style column today), and the Collaterals page (no book).

## Context (what the code does today)
- Six styles live in **two** lists that must match: `cptdp_document_cover_styles()` (plugin, `includes/post-types/document.php`, feeds the editor `<select>` and the sanitiser) and `devportfolio_cover_styles()` (theme, `functions.php`, whitelists `data-cover-style` in `document-book/render.php`). Unknown slug = `midnight`.
- Each style is one CSS block in `blocks/document-book/style.css`: it sets `--cv` (cover), `--ct` (text), optionally `--cat-c`, and draws its pattern in the `.documents-book__pat` layer (`<i aria-hidden>`, first child). Only Horizon uses the gradient; the rest are black, white and grey.
- The cover is 2:3, with a 16px spine (`::before`) and a dashed stitch (`::after`) on the left; the title is vertically centred (`align-self: center`) with a short ornament line (`__ttl::after`); the footer holds `PDF · 24 pp` and `DAVE`. No markup is needed for a new style.

## Key decisions
1. **Slug `certificate`, label "Certificate", appended last** in both lists (the plugin list's first entry stays the default; order is the dropdown order). No migration: existing documents keep their stored slug.
2. **CSS only, no markup change.** The look is built from the existing `__pat` layer, so `render.php`, the shelf and every other block are untouched.
3. **Design: the shared cover skeleton plus a certificate seal.** A first version (thick double frame, centred text, extra padding, a cover-sized rosette watermark) was rejected in review because it broke the shelf's rhythm: its category, title and footer no longer lined up with the other books, and it was much heavier than their patterns. The built version only changes what the other styles change:
   - **Colours:** white cover with fine guilloche rings spreading from the seal (`--cv: repeating-radial-gradient(circle at 76% 24%, ...)` in ink at 8% over `--book-surface`; chosen in review over a crosshatch, a sunburst and rings on grey, because a plain white cover looked flat), ink text (`--ct: var(--book-ink)`), no brand gradient, so the "only Horizon has a gradient" rule and its test keep holding.
   - **Layout:** the shared padding and left-aligned category, title and ornament, untouched.
   - **Frame:** exactly Midnight's hairline (`__pat` inset `10px 10px 10px 26px`, `6px 6px 6px 18px` at 820px, shared selector), in ink at 24%. An inner second line was tried and dropped: with the shared padding it touched the text.
   - **Seal:** `__pat::after`, a small line-art award seal with two ribbons (inline SVG data URI, stroke 1.6), top right (`top: 11%; right: 9%; width: 26%`) where Horizon has its sun. Percent sizes, so no phone override; it clears a four-line title at both sizes.
   - No image files (the theme ships none); title, category and footer remain real text.
4. **Contrast:** ink on white, same as the existing light styles; the frame and seal are decorative (`aria-hidden` layer), so nothing depends on them.
5. **No new tokens.** Only `--book-ink` and `--book-surface`. The spine and stitch stay (a certificate on a shelf is still a book).

## Preview
Open [`DPD-043-certificate-cover-preview.html`](DPD-043-certificate-cover-preview.html) in a browser. It loads the real `document-book/style.css` (the rules below live there now) and shows a short title, a worst-case four-line title, and the six existing styles beside it for comparison.

```css
/* Appended to blocks/document-book/style.css, after Two-Tone */
/* Certificate (DPD-043): white with Midnight's hairline frame, and a small award seal where Horizon has its sun.
 * Fine guilloche rings spread from the seal, like the security print on a diploma. */
.documents-book[data-cover-style="certificate"] {
	--cv: repeating-radial-gradient(circle at 76% 24%, transparent 0 6px, color-mix(in srgb, var(--book-ink) 8%, transparent) 6px 7px), var(--book-surface);
	--ct: var(--book-ink);
}

.documents-book[data-cover-style="certificate"] .documents-book__pat {
	inset: 10px 10px 10px 26px;
	border: 1px solid color-mix(in srgb, var(--book-ink) 24%, transparent);
}

.documents-book[data-cover-style="certificate"] .documents-book__pat::after {
	content: "";
	position: absolute;
	top: 11%;
	right: 9%;
	width: 26%;
	aspect-ratio: 44 / 58;
	background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 44 58' fill='%23fff' stroke='%23000' stroke-width='1.6' stroke-linejoin='round'%3E%3Cpath d='M13 34 7 56l9-5 5 6 3-23z'/%3E%3Cpath d='M31 34l6 22-9-5-5 6-3-23z'/%3E%3Cpath d='M40.3 20.0 L40.2 20.3 L40.1 20.6 L39.8 20.9 L39.5 21.2 L39.1 21.5 L38.6 21.7 L38.1 22.0 L37.7 22.2 L37.3 22.4 L36.9 22.6 L36.6 22.8 L36.4 23.1 L36.3 23.3 L36.3 23.6 L36.4 23.9 L36.6 24.2 L36.9 24.5 L37.2 24.9 L37.5 25.3 L37.8 25.8 L38.1 26.2 L38.3 26.6 L38.5 27.0 L38.6 27.4 L38.6 27.7 L38.4 28.0 L38.2 28.3 L37.9 28.5 L37.5 28.6 L37.1 28.7 L36.6 28.8 L36.0 28.8 L35.5 28.8 L35.0 28.8 L34.6 28.8 L34.2 28.8 L33.8 28.9 L33.6 29.1 L33.4 29.3 L33.3 29.5 L33.3 29.8 L33.4 30.2 L33.4 30.7 L33.6 31.2 L33.7 31.7 L33.8 32.2 L33.8 32.7 L33.8 33.2 L33.8 33.6 L33.7 33.9 L33.5 34.2 L33.3 34.4 L32.9 34.5 L32.6 34.5 L32.1 34.5 L31.7 34.3 L31.2 34.2 L30.7 33.9 L30.2 33.7 L29.8 33.5 L29.4 33.3 L29.0 33.2 L28.7 33.2 L28.4 33.2 L28.2 33.3 L28.0 33.6 L27.9 33.9 L27.8 34.3 L27.7 34.7 L27.5 35.2 L27.4 35.7 L27.3 36.2 L27.1 36.7 L26.9 37.1 L26.7 37.4 L26.4 37.7 L26.1 37.8 L25.8 37.9 L25.5 37.8 L25.1 37.6 L24.7 37.3 L24.4 37.0 L24.0 36.6 L23.7 36.2 L23.4 35.8 L23.1 35.5 L22.8 35.1 L22.5 34.9 L22.3 34.8 L22.0 34.7 L21.7 34.8 L21.5 34.9 L21.2 35.1 L20.9 35.5 L20.6 35.8 L20.3 36.2 L20.0 36.6 L19.6 37.0 L19.3 37.3 L18.9 37.6 L18.5 37.8 L18.2 37.9 L17.9 37.8 L17.6 37.7 L17.3 37.4 L17.1 37.1 L16.9 36.7 L16.7 36.2 L16.6 35.7 L16.5 35.2 L16.3 34.7 L16.2 34.3 L16.1 33.9 L16.0 33.6 L15.8 33.3 L15.6 33.2 L15.3 33.2 L15.0 33.2 L14.6 33.3 L14.2 33.5 L13.8 33.7 L13.3 33.9 L12.8 34.2 L12.3 34.3 L11.9 34.5 L11.4 34.5 L11.1 34.5 L10.7 34.4 L10.5 34.2 L10.3 33.9 L10.2 33.6 L10.2 33.2 L10.2 32.7 L10.2 32.2 L10.3 31.7 L10.4 31.2 L10.6 30.7 L10.6 30.2 L10.7 29.8 L10.7 29.5 L10.6 29.3 L10.4 29.1 L10.2 28.9 L9.8 28.8 L9.4 28.8 L9.0 28.8 L8.5 28.8 L8.0 28.8 L7.4 28.8 L6.9 28.7 L6.5 28.6 L6.1 28.5 L5.8 28.3 L5.6 28.0 L5.4 27.7 L5.4 27.4 L5.5 27.0 L5.7 26.6 L5.9 26.2 L6.2 25.8 L6.5 25.3 L6.8 24.9 L7.1 24.5 L7.4 24.2 L7.6 23.9 L7.7 23.6 L7.7 23.3 L7.6 23.1 L7.4 22.8 L7.1 22.6 L6.7 22.4 L6.3 22.2 L5.9 22.0 L5.4 21.7 L4.9 21.5 L4.5 21.2 L4.2 20.9 L3.9 20.6 L3.8 20.3 L3.7 20.0 L3.8 19.7 L3.9 19.4 L4.2 19.1 L4.5 18.8 L4.9 18.5 L5.4 18.3 L5.9 18.0 L6.3 17.8 L6.7 17.6 L7.1 17.4 L7.4 17.2 L7.6 16.9 L7.7 16.7 L7.7 16.4 L7.6 16.1 L7.4 15.8 L7.1 15.5 L6.8 15.1 L6.5 14.7 L6.2 14.2 L5.9 13.8 L5.7 13.4 L5.5 13.0 L5.4 12.6 L5.4 12.3 L5.6 12.0 L5.8 11.7 L6.1 11.5 L6.5 11.4 L6.9 11.3 L7.4 11.2 L8.0 11.2 L8.5 11.2 L9.0 11.2 L9.4 11.2 L9.8 11.2 L10.2 11.1 L10.4 10.9 L10.6 10.7 L10.7 10.5 L10.7 10.2 L10.6 9.8 L10.6 9.3 L10.4 8.8 L10.3 8.3 L10.2 7.8 L10.2 7.3 L10.2 6.8 L10.2 6.4 L10.3 6.1 L10.5 5.8 L10.7 5.6 L11.1 5.5 L11.4 5.5 L11.9 5.5 L12.3 5.7 L12.8 5.8 L13.3 6.1 L13.8 6.3 L14.2 6.5 L14.6 6.7 L15.0 6.8 L15.3 6.8 L15.6 6.8 L15.8 6.7 L16.0 6.4 L16.1 6.1 L16.2 5.7 L16.3 5.3 L16.5 4.8 L16.6 4.3 L16.7 3.8 L16.9 3.3 L17.1 2.9 L17.3 2.6 L17.6 2.3 L17.9 2.2 L18.2 2.1 L18.5 2.2 L18.9 2.4 L19.3 2.7 L19.6 3.0 L20.0 3.4 L20.3 3.8 L20.6 4.2 L20.9 4.5 L21.2 4.9 L21.5 5.1 L21.7 5.2 L22.0 5.3 L22.3 5.2 L22.5 5.1 L22.8 4.9 L23.1 4.5 L23.4 4.2 L23.7 3.8 L24.0 3.4 L24.4 3.0 L24.7 2.7 L25.1 2.4 L25.5 2.2 L25.8 2.1 L26.1 2.2 L26.4 2.3 L26.7 2.6 L26.9 2.9 L27.1 3.3 L27.3 3.8 L27.4 4.3 L27.5 4.8 L27.7 5.3 L27.8 5.7 L27.9 6.1 L28.0 6.4 L28.2 6.7 L28.4 6.8 L28.7 6.8 L29.0 6.8 L29.4 6.7 L29.8 6.5 L30.2 6.3 L30.7 6.1 L31.2 5.8 L31.7 5.7 L32.1 5.5 L32.6 5.5 L32.9 5.5 L33.3 5.6 L33.5 5.8 L33.7 6.1 L33.8 6.4 L33.8 6.8 L33.8 7.3 L33.8 7.8 L33.7 8.3 L33.6 8.8 L33.4 9.3 L33.4 9.8 L33.3 10.2 L33.3 10.5 L33.4 10.7 L33.6 10.9 L33.8 11.1 L34.2 11.2 L34.6 11.2 L35.0 11.2 L35.5 11.2 L36.0 11.2 L36.6 11.2 L37.1 11.3 L37.5 11.4 L37.9 11.5 L38.2 11.7 L38.4 12.0 L38.6 12.3 L38.6 12.6 L38.5 13.0 L38.3 13.4 L38.1 13.8 L37.8 14.2 L37.5 14.7 L37.2 15.1 L36.9 15.5 L36.6 15.8 L36.4 16.1 L36.3 16.4 L36.3 16.7 L36.4 16.9 L36.6 17.2 L36.9 17.4 L37.3 17.6 L37.7 17.8 L38.1 18.0 L38.6 18.3 L39.1 18.5 L39.5 18.8 L39.8 19.1 L40.1 19.4 L40.2 19.7 L40.3 20.0z'/%3E%3Ccircle cx='22' cy='20' r='10.5'/%3E%3C/svg%3E") center / contain no-repeat;
}

/* Inside the existing @media (max-width: 820px) block: Midnight's small inset, shared */
.documents-book[data-cover-style="midnight"] .documents-book__pat,
.documents-book[data-cover-style="certificate"] .documents-book__pat {
	inset: 6px 6px 6px 18px;
}

```

## Steps (one conventional commit each, tests with their change)
1. `feat(custom-post-types-dp): add the certificate cover style` — `document.php`: one entry in `cptdp_document_cover_styles()`. Then `test(custom-post-types-dp): cover the certificate cover style` — `tests/post-types/test-document.php`: the slug is accepted by the sanitiser and offered in the meta box, order unchanged for the first six.
2. `feat(theme): draw the certificate cover` — `functions.php` (`devportfolio_cover_styles()` entry), `blocks/document-book/style.css` (the block above, plus the 820px sizes). Then `test(theme): cover the certificate cover` — `tests/test-document-book.php`: update `test_cover_styles_are_the_six_in_order` to seven; the per-style loops already cover the slug rendering and the CSS rule per style; add `--ct: var(--book-ink)` to the light list (no `--book-grad`/`--book-g1` is covered by the existing loop); `test_certificate_shares_the_cover_layout_with_a_frame_and_seal`: no own padding or title/category rules, Midnight's frame and inset, the seal's position and data URI, and the shared 820px inset.
3. `docs(agents): document the certificate cover style` — Overview/theme architecture: "six styles" becomes seven, light styles list gains Certificate; test counts. Then `docs(plans): add the DPD-043 plan` — this file only, last.

Stage explicit paths only (untracked plans of other tickets sit in the tree). Footer `Refs: DPD-043`, then the Co-Authored-By line. PR title: `feat(theme): add a certificate cover style for documents`.

## Checks
- `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter CPTDP_Test_Document` and the theme suite `--filter Devportfolio_Test_Document_Book`; full suites before the PR.
- Browser (headless Chrome or DevTools) on `/documents/` with a document set to Certificate, at 360, 820 and 1440px: the watermark does not hurt the title contrast at `xlong` titles (4 lines) and the frame stays clear of the footer; hover lift and pressed state unchanged.

## Open questions for the owner
- **Look:** resolved in review: aligned to the existing covers (see Key decision 3).
- **Gold or other accent:** the theme is greyscale and only Horizon has colour; I left the certificate greyscale. Say so if a gold rosette is wanted (it would need a new token and a change to the "only Horizon" test).
