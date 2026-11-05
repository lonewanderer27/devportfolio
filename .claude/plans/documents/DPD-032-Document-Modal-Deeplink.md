# Plan: Document modal deeplink

Ticket DPD-032 (Low, User Interface): *As a user, if I have the link of an existing document, deeplinking shall allow me to view the same document again.*
Branch suggestion `feat/DPD-032-Document-Modal-Deeplink`. Theme only; no plugin change, no version bump. Builds on `DPD-009-010-011-012-014-015-Document-Modal.md` (open/close contract) and `DPD-002-003-025-Header-Categories.md` (the `?category=` URL pattern this copies).

**Out of scope:** a "Copy link" button (the ticket is about opening a link someone already has; can follow as its own ticket), deep linking to a page inside the document (`&page=N`), the mobile viewer (DPD-017/019), social/OG metadata.

## Context (what the code does today)
- `document-modal/view.js` opens the shared `<dialog>` from `open( id, trigger )` on a click of any `[data-document-id]`, only when `(min-width: 701px)`. It never touches the URL. Closing runs through the dialog's `close` event (Close button, Esc, backdrop, shrinking to 700px or less).
- `document-categories/view.js` already keeps `?category=<slug>` in the URL (`pushState` on click, `popstate` re-applies, bad values cleaned with `replaceState`) and builds URLs from `window.location.href`, so extra params and the hash are preserved.
- The modal payload (`devportfolio_document_modal_payload()`) is keyed by post ID and has no slug, and the payload holds **published documents only**.
- `document` is a public CPT, so `/documents/<slug>/` already renders through `templates/single.html` (a plain post page, no shelf or modal).

## Key decisions
1. **Link format: `/documents/?document_slug=<slug>`.** The slug is stable and readable, and an ID would leak ordering and break if content is re-seeded. `document_slug` is deliberately not `document`: WordPress reserves `document` as the CPT query var, and using it makes the archive URL resolve back to the singular request. A query arg on the archive needs no rewrite rule, no flush and no template: the page already contains the modal and the full payload. It sits next to `?category=` and the two are independent (a deeplinked document opens whatever category pill is active).
2. **Payload gains `slug`** (`$post->post_name`), and `view.js` builds a `slug -> id` map from the payload once. No new query, no server-side change to the page.
3. **URL is the source of truth for "which document is open".**
   - Opening (click) does `history.pushState( { document: slug }, '', urlWith( slug ) )`.
   - Closing by any route removes `document`. If the current history entry was pushed by this modal, call `history.back()`; otherwise (page loaded with the param, or entry not ours) `history.replaceState` with the param removed. This keeps Back = close and avoids a stack of dead entries.
   - `popstate` opens or closes the dialog to match the URL (Back closes, Forward reopens), without pushing again. Same shape as the category pills.
4. **First load:** after the payload is parsed and `desktop.matches`, read `?document_slug`. A known slug calls `open( id, null )` (no opener, so focus returns to the page on close; `els.close.focus()` already runs). An unknown, unpublished or trashed slug is dropped with `replaceState`, like a bad `?category`, and the shelf shows normally.
5. **`/documents/<slug>/` single URL (decision for the owner, recommended: redirect).** Someone who has the pretty permalink should also land on the modal. Recommend `template_redirect` in `functions.php` (`is_singular( 'document' )` -> `301`/`302` to `archive_link?document_slug=<slug>`, via `get_post_type_archive_link()`). Cost: the single template is no longer reachable for documents; today it is an unstyled generic page, so little is lost. Alternative: leave single URLs alone and ship only the query link. Ask before building step 4.
6. **Small screens (700px or less):** the modal does not exist there (DPD-017 will own it). The param is left in the URL and nothing opens; no error. If the window is widened later, do **not** auto-open (only the first-load and popstate paths open). When DPD-017 lands it should reuse the same `?document_slug` read.
7. **Shrinking to 700px or less while open** already closes the dialog; that close path removes the param like any other.
8. **Category interplay:** `urlForSlug()` in `document-categories/view.js` uses the current URL, so changing a pill while the modal is closed keeps nothing stale, and the modal being open blocks pill clicks anyway. No change there. A category pushState must not drop `?document_slug`, which it does not.
9. **Accessibility and focus:** unchanged. Deeplink open has no trigger, so the `close` handler's `opener` guard (already `if ( opener && document.contains( opener ) )`) simply skips; consider focusing the matching shelf button when it exists, as a nicety.
10. **Escaping and safety:** the slug is only compared against payload keys, never written into the DOM or HTML; `URL`/`searchParams` handle encoding.

## Steps
One conventional commit each, tests separate, both suites green after each.

1. `feat(theme): add the document slug to the modal payload` (`functions.php`), then `test(theme): cover the payload slug` (extend `tests/test-document-modal.php`).
2. `feat(theme): open the document modal from a ?document_slug link`: `view.js` read of the param on load, slug map, unknown-slug cleanup. Pure URL helpers go in a new `blocks/document-modal/deeplink.js` (`slugFromUrl( href )`, `urlWithSlug( href, slug )`, `urlWithoutSlug( href )`, preserving other args and the hash) so they can be `node --test`ed, matching `pager.js` / `mode.js`. Then `test(theme): cover the deeplink helpers` (`blocks/document-modal/tests/deeplink.test.mjs`).
3. `feat(theme): keep the open document in the URL`: pushState on open, back()/replaceState on close, `popstate` handling, a `pushedByModal` flag in `view.js`.
4. (if approved) `feat(theme): redirect a single document URL to the modal link`, then `test(theme): cover the single document redirect` (`go_to()` the single URL, assert the redirect target; unpublished/draft not redirected).
5. `docs(agents): document the document deeplink` (theme architecture, `document-modal` paragraph; test counts).
6. `docs(plans): add DPD-032 plan`, this file only, last.

Commit trailer on each: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. PR title: `feat(documents): DPD-032 deeplink to a document in the modal` (template `.github/pull_request_template.md`).

## Tests
- PHPUnit (theme): payload has `slug` equal to `post_name`; payload still excludes drafts; (step 4) redirect target and no redirect for non-documents or unpublished.
- Node (`deeplink.test.mjs`): reads a slug; empty/missing/array-ish values give `''`; adds or removes the param keeping `?category=` and the hash; encodes odd slugs.
- No browser runner in the repo, so check by hand in headless Chrome or a browser against `https://devportfolio.ddev.site/documents/`:

| Case | Expect |
|---|---|
| Open `/documents/?document_slug=<slug>` fresh tab, desktop | Modal open on that document, shelf behind it |
| Same with `&category=<other>` | Modal open, other category pill active |
| `?document_slug=nope` or a draft's slug | Param removed, no modal, no history entry added |
| Click a book | URL gains `?document_slug=<slug>`; Back closes; Forward reopens |
| Close with X, Esc, backdrop | Param gone; one Back leaves the page, not an extra hop |
| Open from deeplink, then close | Param gone, no `history.back()` leaving the site |
| Window 700px or less with the param | No modal, no error; widening does not open it |
| Refresh while open | Reopens the same document |

## Risks
- **`history.back()` on a deeplink-opened modal** would leave the site if called blindly: the `pushedByModal` flag guards it, and it is the first thing to test.
- **Esc/backdrop/resize all end in the dialog `close` event**, so the URL cleanup lives there once, not in each handler. A `popstate`-triggered close must not clean the URL again (flag it).
- **Slug changes** (editor renames a document) break old links; WordPress's own old-slug redirect only applies to the canonical single URL, so with the step 4 redirect old `/documents/<old>/` links keep working, and the query link does not. Acceptable for a Low ticket; mention in the PR.
