# Plan: Book category, make the category name above each document singular

Ticket DPD-036 (Low, User Interface), area *Book / Category*: *As a visitor, the category printed above each document reads as the kind of thing that document is ("Operations Doc"), not the name of the group it belongs to ("Operations Docs").*
Branch suggestion `feat/DPD-036-Book-Category-Singular`. Touches the plugin (a term field, so a version bump) and the theme (read it). Builds on `DPD-005-006-008-Document-Book-Item.md` (the cover's `__cat` line) and `DPD-007-Seed-Document-Category.md` (the four seeded terms).

**Out of scope:** the category pills above the shelf (they name the groups and stay plural: "Operations Docs"), the Videos and Collaterals covers (same pattern, but their own tickets; the field is built so they can reuse it), automatic grammar rules, translations.

## What WordPress offers (checked, so we lean on it where it exists)
- A taxonomy has a built-in `singular_name` label (`register_taxonomy( ... 'labels' => [ 'singular_name' => ... ] )`). Ours is `Document Category`: that is the singular of the **taxonomy** ("one Document Category"), not of a **term**. It cannot turn "Operations Docs" into "Operations Doc", so it is not the answer.
- A term has `name`, `slug`, `description` and nothing else. No built-in singular field, and no core function that singularises a word (only `WP_Post_Type` / taxonomy labels exist).
- WordPress does provide the right extension point: **term meta** (`register_term_meta()`, `get_term_meta()`), with the standard `{taxonomy}_add_form_fields` / `{taxonomy}_edit_form_fields` hooks for the admin field and `created_{taxonomy}` / `edited_{taxonomy}` to save it. So the plan uses term meta, which is "the WordPress way" for extra data on a term, instead of inventing a table or an option.

## Context (what the code does today)
- `devportfolio_document_category_name( $post_id )` (`functions.php`) returns the name of the first `document_category` term by name. It is the one source for **two** things: the cover's `__cat` line (`document-book/render.php`, uppercased by CSS) and the `category` entry of the modal payload (the outlined pill in the mobile Details sheet, DPD-023).
- The category pills (`document-categories/render.php`) read `$term->name` directly and do not use that function, so changing the function does not touch them.
- The plugin has no term meta yet (`grep register_term_meta` finds nothing), and the document-categories seeder (`includes/seeders/document-categories.php`, CSV `title,slug`) skips an existing term and never modifies it.
- Seeded names today: Executive Docs, Operations Docs, Presentation Decks, Reports.

## Key decisions
1. **Term meta `cptdp_singular_name`** on `document_category` (string, optional). Registered with `register_term_meta( 'document_category', 'cptdp_singular_name', ... )`, `type: string`, `single: true`, `sanitize_callback: sanitize_text_field`, `show_in_rest: true` (so the block editor and REST show it like other plugin meta), `auth_callback` requiring `manage_categories`.
2. **Admin field.** A text input "Singular name" on the Add and Edit term screens, with help text: *Shown above each document's cover, for example "Operations Doc". Leave empty to use the category name.* Added through `document_category_add_form_fields` and `document_category_edit_form_fields`, saved on `created_document_category` / `edited_document_category` with a nonce and `current_user_can( 'manage_categories' )`. A new column "Singular" on the term list is optional (step 3).
3. **Fallback, never auto-grammar.** If the field is empty the cover shows the term name exactly as today. We do **not** strip a trailing "s" automatically: it breaks real names ("Process", "Analysis", "News", "Press Kits" -> wrong or right depending on luck) and would silently change content the owner wrote. The owner decides the singular, per term.
4. **One helper in the plugin, used by the theme through `function_exists`** (the pattern the theme already uses for plugin APIs): `cptdp_get_term_singular_name( $term )` returns the meta when set, else `$term->name`. The theme's `devportfolio_document_category_name()` returns `cptdp_get_term_singular_name( $terms[0] )` when that function exists, else the name (the theme stays usable without the plugin). Because the function is shared, **the mobile Details sheet pill becomes singular too**, which is consistent ("Operations Doc" describes the document). The pills above the shelf keep `$term->name`.
5. **Which term** when a document has several: unchanged (first by name); the singular is read from that same term.
6. **Seeder and existing data.** Add a third CSV column `singular` to `data/seeders/document-categories.csv` (`Executive Doc`, `Operations Doc`, `Presentation Deck`, `Report`), required by the seeder's column list. New terms get the meta on create. Existing terms are skipped by the seeder, so also: when a term already exists **and its `cptdp_singular_name` is empty**, the seeder fills only that meta (never name or slug, never overwrites a value the owner typed) and reports it as `updated`. That keeps "seeders never modify existing content" true for everything but an empty field the seeder owns. Run: `ddev wp cptdp seed document-categories`.
7. **Cover text.** The `__cat` span prints the singular (escaped, as now); CSS uppercase is unchanged ("OPERATIONS DOC"). The cover's title-length logic and layout do not depend on it. The pill and the cover now differ on purpose.
8. **Screen readers and the payload.** The cover is inside the item's labelled button, so nothing else changes. The payload `category` string becomes the singular; no JS change.

## Steps
One conventional commit each, tests separate, plugin and theme suites green after each. Bump the plugin `Version` header in the last plugin commit.

1. `feat(custom-post-types-dp): register the singular name term meta` (`includes/post-types/document-category.php`: `register_term_meta`, helper `cptdp_get_term_singular_name()`), then `test(custom-post-types-dp): cover the term meta` (`tests/taxonomies/test-document-category.php`: registered, sanitised, REST visible, helper fallback to the name when empty or whitespace).
2. `feat(custom-post-types-dp): add the Singular name field to the category form` (add and edit forms, nonce, capability check, escaped value), then tests (saved on create and edit, empty clears it, no save without the capability or nonce, markup escaped).
3. (optional) `feat(custom-post-types-dp): show the singular name in the category list` (one admin column).
4. `feat(custom-post-types-dp): seed the singular names` (CSV column, seeder fills an empty meta on existing terms, `updated` outcome in the report and in `--dry-run`), then `test(custom-post-types-dp): cover the seeder` (`tests/seeders/test-document-categories.php`: new term gets the meta, existing term with empty meta is filled, a typed value is never overwritten, dry run writes nothing, idempotent second run).
5. `feat(theme): print the singular category name on the cover` (`devportfolio_document_category_name()` through `function_exists`), then `test(theme): cover the singular category` (`tests/test-document-book.php`: cover shows "Operations Doc" when set, falls back to the name when empty or when the plugin function is missing, pills above the shelf still print "Operations Docs", payload `category` is singular).
6. `chore(custom-post-types-dp): bump version to 0.24.0`, `docs(agents): document the singular category name` (Overview: term meta, helper, seeder column; theme: `document-book` and the payload; test counts), then `docs(plans): add DPD-036 plan`, this file only, last.

## Risks and checks
- Existing databases need `ddev wp cptdp seed document-categories` once (step 4) or the covers keep the plural, by design.
- Run both suites (plugin and theme), since a plugin change can break theme tests; run the plugin `install-wp-tests.sh` first if the container was restarted.
- Browser check: `https://devportfolio.ddev.site/documents/` at 390 and 1280px, plus the mobile Details sheet; confirm the pills are still plural. Editing a term's singular in wp-admin should show on the next page load (no cache layer to clear).

Commit trailer on each: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. PR title: `feat(documents): DPD-036 singular category name on the book cover` (template `.github/pull_request_template.md`).
