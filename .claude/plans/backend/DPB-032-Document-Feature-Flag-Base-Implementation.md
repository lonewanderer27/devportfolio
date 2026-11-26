# Plan: Document feature flags, base implementation (DPB-032, Feature Flag / Document / Base Implementation)

Plugin: `custom-post-types-dp` 0.23.0 -> **0.24.0**. Small theme change: the configuration screen lives in the theme. Branch `feat/DPB-032-Document-Feature-Flag-Base-Implementation`, cut from `main`. Builds on DPB-025 to DPB-031 (page pipeline, Office conversion, names and thumbnails) and DPD-013 to DPD-032 (viewer, mobile viewer, sheets, deeplink).

> **Status: plan only, nothing implemented.** Effort figures come from reading `AGENTS.md`, not the code; confirm each touch point when you reach it.

## Principle: this site has no concept of tiers
WordPress knows **features, on or off**. It does not know plans, tiers, prices or customers. Only **the creator** (the portfolio's author) decides what is on. Anything about who gets what lives outside this site, in the author's own system. So: no tier names, no "upgrade" wording, no per-customer logic in this code base.

Tiers still exist **as the author's own reference** (see "Author reference: tier mapping" at the end of this plan). They are used when the author sets up the route on their own server in Phase 2: the author's system turns a customer's tier into the flat list of flags this site receives. They are documentation, not code here.

## Goal
1. **Now (this ticket):** a feature registry, one question every call site asks (`cptdp_feature_enabled()`), and a **configuration screen exposed by the theme that only the author can see**: it appears only when the logged-in user's email matches the author's email.
2. **Later (separate ticket, designed for here, not built):** the flags are controlled remotely by a **route on the author's own portfolio that returns only JSON** and can selectively enable or disable features entirely. This ticket must leave a clean seam for it.

## Non-goals
- Tiers, billing, license keys, customer accounts.
- Building or calling the remote route (Phase 2).
- Gating every feature (see Scope).
- Changing how any feature behaves when it is on.

## Phase 1 (this ticket): local configuration, author only

### Who counts as the author
- The author's email is the one already stored in **Settings -> Contact** (`cptdp_contact['email']`, read with `cptdp_get_contact()`). No new field.
- `cptdp_is_feature_admin( ?WP_User $user = null ): bool` is true only when **all** hold: the user is logged in, has `manage_options`, and `user_email` equals the author's email (case-insensitive, trimmed, both non-empty). The default is **false** (no author email set = nobody sees the screen).
- Filter `cptdp_feature_admin` ( bool, WP_User ) so tests can drive it and Phase 2 can replace the rule.
- **Honest limit:** a WordPress email is not verified. Anyone who can edit a user's email on this site could match it. The `manage_options` requirement narrows that to administrators, and the real protection against other administrators is that the gate hides the screen and refuses writes; it is not a security boundary against someone who owns the server or database. Phase 2 (a remote source the client cannot edit) is what makes it enforceable.

### The configuration screen (in the theme)
- **Appearance -> Document features** (`devportfolio_` prefix, `add_theme_page` on `admin_menu`), registered **only** when `cptdp_is_feature_admin()` is true. For anyone else the page does not exist (a direct URL gives the normal "not allowed" response), and the menu entry is absent.
- Lists every registry feature with a checkbox, its label, a one-line description and its current **source** (`local setting` or `default`; in Phase 2 also `remote`). Saved with a nonce, capability check and the same email check again on submit.
- The theme calls the plugin through `function_exists()` and shows a short notice if the plugin is missing; the theme's front end never depends on this screen.
- The screen holds **no state of its own**: it writes the plugin's option (below). Switching theme hides the screen but never loses or changes the saved flags.

### Storage and API (plugin)
- Registry: `includes/features/class-features.php`, `CPTDP_Features::registry()` returns `key => [ label, description, default, requires ]`. **No tier field.** A new feature is one entry plus its call sites.
- Option `cptdp_features` (array of key => bool), not REST-exposed, sanitised against the registry (unknown keys dropped). Writes happen **only** through one function, `cptdp_features_save()`, which itself checks `cptdp_is_feature_admin()` (WP-CLI is allowed, as it already runs with server access).
- `cptdp_feature_enabled( string $key ): bool` resolves: filter `cptdp_feature_enabled` (the Phase 2 seam and the test seam) -> option -> registry default. Unknown key = false. `requires` makes a feature false when its dependency is off. Cached per request.
- Theme and other callers use `function_exists( 'cptdp_feature_enabled' ) ? cptdp_feature_enabled( $key ) : true`, so the theme works without the plugin and an old plugin never hides anything.

### Scope: first features covered
Chosen because each already has a switch or a clean boundary. Everything else is a later registry entry.

| Key | Label | Today's switch |
|---|---|---|
| `office_pptx` | PowerPoint conversion (PPTX, PPT, ODP) | none (scheduler) |
| `office_xlsx` | Excel conversion (XLSX, XLS, ODS) | none (scheduler) |
| `office_docx` | Word conversion (DOCX, DOC, ODT) | none (scheduler) |
| `native_pdf` | Native viewer: PDF | `native_pdf` setting |
| `native_pptx` | Native viewer: PowerPoint | `native_pptx` setting |
| `native_xlsx` | Native viewer: Excel | `native_xlsx` setting |
| `native_docx` | Native viewer: Word | `native_docx` setting |
| `trim_bottom` | Bottom trim | `trim_bottom` setting |

`native_*` requires its `office_*` flag (PDF has no requirement). Later registry entries (each its own ticket): `mobile_viewer`, `mobile_pages_sheet`, `mobile_details_sheet`, `deeplink`, `page_flip_book`, `software_tags`, `cover_styles`, `filter_animation`, `page_names`, `page_thumbnails`, `document_statistics`. The page-flip book and the mobile viewer need a simpler fallback viewer before they can be turned off, so they wait.

### Enforcement (what makes a flag real)
A flag is checked **where the thing is produced**, never only where it is drawn.
1. **Server work:** with an `office_*` flag off, the scheduler queues nothing and the file kind is treated as unsupported (status `failed`, message "this file type is not enabled on this site"; never `needs_browser`).
2. **Payload:** `devportfolio_document_viewer_data()` and the modal payload omit `nativeUrl` when its `native_*` flag is off, so the file URL is not in the page JSON.
3. **URL helpers:** `cptdp_document_native_pptx_url()`, `_xlsx_url()`, `_docx_url()` and the PDF native check return empty/false when off (the single choke points).
4. **Settings -> Documents:** the matching checkbox is **disabled** with the note "Not enabled on this site" when its flag is off, and the sanitiser **ignores** a posted value for an off flag. Wording never mentions plans or upgrades.
5. **REST:** a write to an off flag is dropped (a partial update still keeps saved values).
6. **Conversion status** for an off format reads "Not enabled", not "LibreOffice missing".
7. **No asset loading:** the vendored `ooxml` engine's `import()` is not reached for an off flag.
8. `trim_bottom` off: `cptdp_get_document_pages()` returns `crop_bottom` 0 (already how the existing setting behaves).

### WP-CLI
- `ddev wp cptdp features list` shows key, state, source.
- `ddev wp cptdp features enable|disable <key>` writes the option.

### Migration (upgrade to 0.24.0)
First load of 0.24.0 (guarded by option `cptdp_features_migrated`) writes `cptdp_features` so everything **already working stays on** (derived from the saved `cptdp_documents` settings and existing converted Office sets). New installs get registry defaults. The migration never turns anything off.

### Steps
1. Registry, `cptdp_feature_enabled()`, `requires`, cache; tests.
2. `cptdp_is_feature_admin()`, `cptdp_features_save()` guard; tests (email match, case, empty email, no `manage_options`, logged out).
3. Gate the three `office_*` conversions (scheduler and converter entry points); tests.
4. Gate the four native viewers (URL helpers, payload, settings field, sanitiser, REST); tests.
5. Gate `trim_bottom`.
6. Theme: Appearance -> Document features screen, only for the author; tests that it does not exist for anyone else.
7. WP-CLI `features` command; migration.
8. Version bump to 0.24.0, update the Overview in `AGENTS.md`, mark Status here.

### Tests
- Plugin: `tests/features/test-features.php` (registry, resolution order, `requires`, cache), `tests/features/test-feature-admin.php` (author gate), `tests/documents/test-feature-gates.php` (scheduler, payload, URL helpers, trim, settings and REST), `tests/cli/test-features-command.php`, migration.
- Theme: `Devportfolio_Test_Document_Features_Screen` (menu present only for the author, submit refused for others, no fatal without the plugin).
- Existing suites must still pass with every flag on (expected today: plugin 726, theme 297, JS 148).

## Phase 2 (future ticket, not built here): remote control by the author's portfolio

**Goal:** the author's own portfolio exposes **a route that returns only JSON** and selectively enables or disables features on this site, entirely, with no tiers on this side.

Design constraints so Phase 1 does not need rework:
- **One new source, same helper.** The remote answer plugs in through the existing `cptdp_feature_enabled` filter (or a source list ahead of the option). Call sites do not change.
- **Contract (draft):** `GET <author portfolio>/<route>` returns `{ "version": 1, "features": { "office_pptx": true, "native_pdf": false, ... } }`. Unknown keys ignored; missing keys fall through to the local option/default. Only booleans accepted.
- **Fetch off the request path:** server-side with `wp_remote_get`, stored in a transient with a TTL, refreshed by WP-Cron. **Visitors never trigger the fetch**, and a failed or slow fetch **keeps the last good answer** (and after a long outage falls back to the local setting), so the site never breaks because the author's site is down.
- **Authenticity:** the response must be authenticated (signed JSON or a shared secret header; decide in that ticket) and fetched over HTTPS only, with a timeout, size cap and strict schema check. An unauthenticated route would let anyone turn features off or on.
- **Remote wins over local when present.** The Phase 1 screen then shows `remote` as the source and disables the checkbox for that key, so the author sees why.
- **Where the URL comes from:** a constant or the author's email domain is not enough; decide in that ticket (likely a field on the author-only screen, or `wp-config.php`).
- **Privacy:** the request sends nothing about visitors; at most the site URL and the plugin version, and only if that ticket decides it needs them.
- This site still has **no tier concept**: the remote route returns a flat list of flags. How the author derives that list (per customer, per deal) is the author's system's business.

## Author reference: tier mapping (for the author's server, NOT implemented in WordPress)
Use this as the source when building the Phase 2 route on the author's portfolio: each customer has a tier there, and the route returns the flat `features` list for that tier. **Nothing below is in this repository's code, registry, settings, option, WP-CLI or UI.** Adding a tier name to the plugin or theme would break the principle above.

Proposed tiers (a proposal, change freely):
- **Base:** PDF shelf, category filters, basic modal, download. No flags needed (core, never gated).
- **Pro:** Office conversion, mobile viewer, deeplinks, and the polish features.
- **Premium:** everything in Pro plus native viewers (selectable text), the page-flip book, page names, page thumbnails and the mobile Pages and Details sheets.

Flags in this ticket (registry keys) by tier:

| Registry key | Feature | Tier |
|---|---|---|
| `office_pptx` | PowerPoint conversion | Pro |
| `office_xlsx` | Excel conversion | Pro |
| `office_docx` | Word conversion | Pro |
| `native_pdf` | Native viewer: PDF | Premium |
| `native_pptx` | Native viewer: PowerPoint | Premium |
| `native_xlsx` | Native viewer: Excel | Premium |
| `native_docx` | Native viewer: Word | Premium |
| `trim_bottom` | Bottom trim | Pro |

Flags for later tickets (not registered yet):

| Future key | Feature | Tier | Needs first |
|---|---|---|---|
| `mobile_viewer` | Mobile viewer (swipe, zoom, spread, landscape) | Pro | a basic fallback viewer |
| `mobile_pages_sheet` | Mobile Pages sheet | Premium | `page_thumbnails` |
| `mobile_details_sheet` | Mobile Details sheet | Pro | none |
| `deeplink` | `?document_slug=` deeplink and redirect | Pro | none |
| `page_flip_book` | Page-flip open book | Premium | a plain fallback viewer |
| `software_tags` | Software tags | Pro | none |
| `cover_styles` | Cover styles | Pro | register `cptdp_cover_style` |
| `filter_animation` | Filter View Transitions | Pro | none |
| `page_names` | Page names | Premium | none |
| `page_thumbnails` | Page thumbnails | Premium | none |
| `document_statistics` | Statistics and highlight | Pro | none |

Never gated, in any tier: download, security validation (macro, zip-bomb and external-link checks), accessibility (keyboard, `aria`, reduced motion), the basic shelf and modal.

Example of what the author's route would return for a **Pro** customer (flat, no tier word): `{ "version": 1, "features": { "office_pptx": true, "office_xlsx": true, "office_docx": true, "trim_bottom": true, "native_pdf": false, "native_pptx": false, "native_xlsx": false, "native_docx": false } }`.

## Risks
- **Email match is a soft gate** (see "Honest limit"); real enforcement arrives with Phase 2.
- **Gating after the work is done:** if a flag is checked only in the theme, the cost (conversion, storage) is still paid. Enforcement rules 1 and 2 cover this.
- **Stale cached payloads:** the payload is built per request today; confirm no page cache stores `nativeUrl` before relying on rule 2.
- **Lock-out:** with no author email set, nobody sees the screen. Settings -> Contact gets a hint line pointing to this, and WP-CLI is always available.

## Open questions
- Is the Settings -> Contact email the right "author email", or should it be a separate field (for example if the contact email is a shared inbox)?
- Keep the screen under Appearance (theme) as asked, or also add a link from Settings -> Documents for the author?
- Phase 2: authentication scheme for the JSON route (signed payload vs shared secret) and where the route URL is configured.
