# Plan: Software Tool icon, store the icon of each software and populate it in the database

Ticket DPB-033 (Medium, Backend), area *Software Tool / Icon*: *As the site owner, each software tool has an icon stored with it, and the icons of the 31 existing tools are in the database without uploading them one by one.*
Branch suggestion `feat/DPB-033-Software-Tool-Icon`. **Plugin** (`custom-post-types-dp`), so a minor **version bump**; no theme change (the theme side is `DPD-037-Book-Software-Used-Display-The-Icon-Of-The-Software-Used-Rather-Than-Text.md`, which depends on this ticket). Builds on `DPB-005-software-tool-cpt.md` and `DPB-021-populate-software-tools.md`.

**Out of scope:** showing the icon anywhere on the site (DPD-037), changing how tools are picked on documents, videos and collaterals, the modal and Details sheet pills, colour variants or dark-mode icons, and fetching icons from the internet at runtime (icons are bundled and imported once).

## What WordPress already offers (checked, so we lean on it)
- `software_tool` already declares `supports: title, editor, thumbnail, page-attributes` and `show_in_rest: true`. The **featured image** is therefore WordPress's own, native place to store one image per post: it appears in the admin edit screen ("Set thumbnail"), in the REST API as `featured_media`, in the existing admin list **Thumbnail** column (50x50), and through `get_the_post_thumbnail()`, `wp_get_attachment_image()` (sizes, `srcset`, width and height attributes, `alt`, lazy loading). No new field, table or option is needed.
- `DPB-021` deliberately left images out ("uploaded manually in wp-admin; brand assets are not bundled in the repo"). This ticket changes that: the icons are bundled in the plugin and imported by the seeder.

## Decision: the icon is the featured image
Store the icon as the tool's **featured image** (`_thumbnail_id`), not as a new meta field. Reasons: it is built in, editable in the usual screen, already shown in the admin list, REST-ready, and every theme helper already knows it. A custom `cptdp_icon` meta would duplicate all of that. The admin labels become icon-specific (`set_featured_image` is already "Set thumbnail"; change the wording to "Set icon", "Remove icon", "Use as icon", and the list column header to "Icon").

## File format (decision for the owner, recommended: PNG now, SVG later)
- **Recommended: square PNG (or WebP), 128x128, transparent background.** Uploads cleanly through the normal media pipeline, no security surface, crisp at 24 to 48px on retina screens, and WordPress makes the smaller sizes.
- **SVG is nicer** (one file, any size) **but WordPress blocks SVG uploads** by default, and an SVG can carry scripts. If wanted later, add it as its own step: allow `image/svg+xml` only for the seeder and for users who can `manage_options`, sanitise every file, and serve it as an `<img>`, never inline. Not part of this ticket.
- Greyscale or brand colour is a **design decision** (the theme is greyscale with one gradient). Recommend importing the brand-colour icons and letting DPD-037 decide how to tint (for example grey until hover). Keep one file per tool.

## Sources and licensing
- Logos are trademarks. Use only the **official brand assets or the Simple Icons set (CC0 for the artwork, brand guidelines still apply)** and record the source and licence of each file in `data/seeders/icons/README.md` (file name, source URL, licence, date). Do not redraw or recolour a logo beyond what the vendor's guidelines allow.
- Some of the 31 tools have no widely published icon or no usable licence (candidates: *Cisco Duo*, *Meta Business Suite*, *Metricool*, *Otter.ai*, *Adobe PDF*, *Zendesk*). For those the plan is a **neutral monogram fallback**, generated once as a PNG (rounded square, tool initial, ink on `fill` grey) and flagged `placeholder` in the README, so the owner can replace it later in wp-admin without code changes. No tool is left without an icon.

## Storage layout
```
wp-content/plugins/custom-post-types-dp/data/seeders/icons/
  README.md          # source, licence, date per file
  adobe-cc.png
  adobe-pdf.png
  asana.png
  ...                # one file per tool, named by the tool's slug (sanitize_title( title ))
```
31 files at roughly 2 to 8 KB each, so the plugin grows by well under 300 KB. `data/seeders/software-tools.csv` gains an `icon` column holding the file name (empty = no icon, tool still seeded).

## Behaviour
1. **Seeder `software-tools`** gets a fifth column `icon` (validated before any write, like the other columns: the file must exist in `data/seeders/icons/` and be a PNG, WebP or JPEG by `wp_check_filetype_and_ext()`).
2. **New tool:** created as today, then the icon is **sideloaded** into the media library with `media_handle_sideload()` from a temporary copy (the bundled file is never moved), attached to the tool (`post_parent`), titled "<Tool> icon", given the alt text "<Tool> logo" (`_wp_attachment_image_alt`), and set as the featured image with `set_post_thumbnail()`.
3. **Existing tool without an icon** (the 31 tools that are already in the database): the seeder **fills only the missing featured image** and never touches title, content, order, URL or an icon the owner set by hand. It is reported as `updated` in the run summary and in `--dry-run` (the DPD-036 pattern for existing terms). Idempotent: a second run reports everything as skipped.
4. **Same image twice:** before sideloading, look for an attachment with the meta `_cptdp_icon_source` equal to the file name (and its hash); reuse it instead of creating a duplicate when a tool was deleted and recreated.
5. **Failures:** a missing or corrupt file stops that tool only, is listed as an error with the file name, and exits non-zero; nothing is half-written (the tool post is created first, the icon second, and a failed icon leaves a tool without an icon, which is valid).
6. `ddev wp cptdp seed software-tools [--dry-run]` is the one command for local and every other environment, and `--all` order is unchanged.

## Helpers (the API the theme uses)
In `includes/post-types/software-tool.php`:
- `cptdp_get_software_tool_icon( int $tool_id, string $size = 'cptdp-tool-icon' ): array` returns `array( 'id' => int, 'url' => string, 'width' => int, 'height' => int, 'alt' => string, 'html' => string )`, or an empty array when the tool has no icon (the caller falls back to the name). `html` comes from `wp_get_attachment_image()`, so it carries `srcset`, `loading="lazy"`, `decoding="async"` and explicit width and height (no layout shift).
- A registered image size `cptdp-tool-icon` (96x96, hard crop off, so a non-square icon is not cut) so a 24 to 32px icon is sharp on 2x and 3x screens without loading the full file.
- An admin column renamed **Icon** that shows the image at 32px (the existing 50px thumbnail cell is reused) and an empty-state dash.
- `register_post_meta` is **not** needed; the featured image is already in REST as `featured_media`.

## Steps (one conventional commit each, tests with their change)
1. `feat(custom-post-types-dp): add the tool icon size and helper` (`cptdp_get_software_tool_icon()`, the image size, icon wording for the admin labels and column), then `test(custom-post-types-dp): cover the icon helper` (`tests/post-types/test-software-tool.php`: returns the attachment data, an empty array without an icon, alt text, size registered, labels and column header).
2. `feat(custom-post-types-dp): bundle the software tool icons` (`data/seeders/icons/*.png` and `README.md` with the source and licence of each file; monograms flagged `placeholder`).
3. `feat(custom-post-types-dp): seed the software tool icons` (CSV `icon` column, validation, sideload, fill-only-when-empty for existing tools, `updated` outcome, duplicate reuse), then `test(custom-post-types-dp): cover the icon seeder` (`tests/seeders/test-software-tools.php`: new tool gets an icon, existing tool without one is filled, an icon set by hand is never replaced, dry run writes nothing, second run is a no-op, bad or missing file is reported, no duplicate attachment).
4. `chore(custom-post-types-dp): bump version to <next minor>` (the version is chosen at merge time; `0.24.0` is planned for DPD-036), then `docs(agents): document the software tool icon` (Overview: featured image as icon, helper, seeder column, image size, bundled folder; test counts), then `docs(plans): add DPB-033 plan`, this file only, last.

## Populate the database (every environment, one time)
1. `ddev snapshot` first on any database you care about.
2. `ddev wp cptdp seed software-tools --dry-run` (expect 31 `updated`, 0 created on an environment that already has the tools).
3. `ddev wp cptdp seed software-tools`, then open Software Tools in wp-admin: every row shows an icon in the **Icon** column, and the placeholders are the ones listed in the README (replace them in wp-admin when a real asset is available; the seeder will not overwrite them).
4. Staging and production: run the same commands after deploy; the media library receives 31 images.

## Risks and checks
- Run the plugin suite (`install-wp-tests.sh` first if the container restarted); the seeder tests need a writable uploads dir in the test environment (the WP test library provides one) and must clean up the attachments they create.
- `media_handle_sideload()` needs the `image`, `file` and `media` admin includes when run from WP-CLI; load them in the seeder.
- File size and count: confirm the bundled folder stays small; optimise the PNGs lossless before committing.
- Trademark and brand-guideline review of the icon set by the owner before launch (the README is the record).
- Seeders are content, not structure: still never run on activation.

Commit trailer on each: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. PR title: `feat(software-tool): DPB-033 store and seed the icon of each software tool` (template `.github/pull_request_template.md`).
