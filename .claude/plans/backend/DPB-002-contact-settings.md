# Plan: DPB-002 "Contact" settings

## Context
Depends on DPB-000 (master plugin `custom-post-types-dp`, done). Story title says "settings post type", but acceptance criteria describe fields in a **Contact section of Settings** (admin enters value, saves, value persists). Singleton config data, not a collection → implement as a **Settings API page** (`Settings → Contact`) backed by one option, not a `register_post_type` CPT. A CPT would give list table + many posts, wrong fit for "one email, one WhatsApp URL, one LinkedIn URL".

Spec:
- `email` (string)
- `whatsapp_url` (string)
- `linkedin_url` (string)

## Files to create
```
wp-content/plugins/custom-post-types-dp/includes/settings/contact.php
wp-content/plugins/custom-post-types-dp/includes/settings/index.php   # silence-is-golden, like other dirs
```

## Files to edit
- `wp-content/plugins/custom-post-types-dp/includes/class-loader.php`: `load_modules()` also globs `includes/settings/*.php` (skip `index.php`). Settings modules self-hook on `admin_menu` / `admin_init` / `rest_api_init`, so no activation work needed.
- `wp-content/plugins/custom-post-types-dp/uninstall.php`: `delete_option( 'cptdp_contact' )`.
- `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: bump version `0.2.1` → `0.3.0` (header + `CPTDP_VERSION`).
- `AGENTS.md`: Overview mention settings modules in `includes/settings/*.php` and the `cptdp_contact` option.

No `.gitignore` change (plugin dir already allowlisted).

## Design

### Storage
Single option `cptdp_contact`, array:
```php
array(
	'email'        => '',
	'whatsapp_url' => '',
	'linkedin_url' => '',
)
```
Single array keeps one `register_setting`, one row, one uninstall delete.

### `contact.php`
- `defined( 'ABSPATH' ) || exit;` guard. Prefix `cptdp_`, text domain `custom-post-types-dp`.
- `cptdp_contact_register_settings()` on `admin_init` and `rest_api_init`:
  - `register_setting( 'cptdp_contact', 'cptdp_contact', array( 'type' => 'object', 'default' => defaults, 'sanitize_callback' => 'cptdp_contact_sanitize', 'show_in_rest' => array( 'schema' => array( 'type' => 'object', 'properties' => email/whatsapp_url/linkedin_url as string ) ) ) )`.
  - `add_settings_section( 'cptdp_contact_section', 'Contact', callback description, 'cptdp-contact' )`.
  - `add_settings_field` x3: `email` (`type="email"`), `whatsapp_url` (`type="url"`), `linkedin_url` (`type="url"`). Field names `cptdp_contact[email]` etc. Labels tied via `label_for`. Placeholders: `you@example.com`, `https://wa.me/<number>`, `https://www.linkedin.com/in/<handle>`.
- `cptdp_contact_add_menu()` on `admin_menu`: `add_options_page( 'Contact', 'Contact', 'manage_options', 'cptdp-contact', 'cptdp_contact_render_page' )` → shows under **Settings → Contact**.
- `cptdp_contact_render_page()`: capability check, `<form method="post" action="options.php">`, `settings_fields( 'cptdp_contact' )`, `do_settings_sections( 'cptdp-contact' )`, `submit_button()`. Core prints "Settings saved." via `settings_errors` on options pages automatically.
- `cptdp_contact_sanitize( $input )`:
  - Non-array → defaults.
  - `email` → `sanitize_email()`; invalid → `''` + `add_settings_error` ("Invalid email address.").
  - `whatsapp_url`, `linkedin_url` → `esc_url_raw( trim(), array( 'https' ) )` (https only). Empty allowed (all fields optional). Host check: WhatsApp host in `wa.me`, `api.whatsapp.com`, `chat.whatsapp.com`, `whatsapp.com`; LinkedIn host ends `linkedin.com`. Mismatch → `''` + `add_settings_error`. Keeps junk and `javascript:` out.
  - Return merged array with only the three known keys.
- Public getter `cptdp_get_contact( ?string $key = null )`: returns full array (wp_parse_args with defaults) or single value; used later by theme/patterns/blocks. Output must still be escaped by caller (`esc_url`, `esc_html`, `antispambot` for email).
- Escape on render: `esc_attr` for values, `esc_html_e` for labels.

### Why `register_setting` with REST schema
Gets REST at `/wp/v2/settings` free (admin-only read/write) for future block-editor / headless use. Cost: a small schema array. Skip if scope-creep concern; remove `show_in_rest` and plan still satisfies criteria.

## Acceptance mapping
| # | Criterion | How satisfied |
|---|-----------|---------------|
| 0 | Plugin active | precondition (settings load via loader on `plugins_loaded`) |
| 1 | Email field | `add_settings_field` `email` (`type=email`) |
| 2 | WhatsApp URL field | `add_settings_field` `whatsapp_url` |
| 3 | LinkedIn URL field | `add_settings_field` `linkedin_url` |
| 4 | Save succeeds | Settings API → `options.php`, "Settings saved." notice |
| 5 | Persist after refresh | values stored in `cptdp_contact`, re-read in field render |

## Verification
1. `ddev wp plugin deactivate custom-post-types-dp && ddev wp plugin activate custom-post-types-dp` → success, no PHP notices (`ddev logs`).
2. `ddev php -l` on each new/edited PHP file.
3. Admin (`ddev launch` → wp-admin → Settings): "Contact" submenu present with 3 fields.
   - Enter `hello@example.com`, `https://wa.me/639171234567`, `https://www.linkedin.com/in/example` → Save → "Settings saved." and values shown.
   - Hard refresh → values persist.
   - Clear all → save → saves empty without error.
   - Bad inputs: `not-an-email`, `javascript:alert(1)`, `http://evil.com/x` for LinkedIn → rejected with error notice, stored value `''`.
4. `ddev wp option get cptdp_contact --format=json` → matches saved values.
5. `ddev wp eval 'var_dump( cptdp_get_contact( "email" ) );'` → saved email.
6. Non-admin (e.g. editor) → no Contact menu; direct URL denied.
7. REST (if kept): `ddev wp eval 'echo wp_json_encode( rest_do_request( new WP_REST_Request( "GET", "/wp/v2/settings" ) )->get_data()["cptdp_contact"] );'` run as admin user (`--user=admin`).
8. Regression: Services CPT still loads (`ddev wp post-type get service`).
9. `git status` shows only `contact.php`, `settings/index.php`, `class-loader.php`, `uninstall.php`, plugin main file, `AGENTS.md`, plan file.
10. Commit: `feat(custom-post-types-dp): add Contact settings` (+ Co-Authored-By trailer).

## Out of scope
Frontend rendering of contact links (blocks/patterns/header), additional social networks, per-field validation beyond host check, multisite network settings, translations files.

## Decisions (settled)
- Settings page, not a CPT.
- WhatsApp: admin enters the **full URL** (e.g. `https://wa.me/639171234567`). Stored as-is after sanitising. No phone-number field, no link building.

## Open questions
- Host allowlist strictness OK, or accept any https URL?
