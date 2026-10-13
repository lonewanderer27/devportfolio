# Plan: master plugin `custom-post-types-dp`

## Context
Portfolio site has no custom plugin. Need master plugin to host portfolio modules (CPTs later). Scope now: empty scaffold, description "Loads all portfolio modules when active." No CPTs registered yet. Loader + modules architecture so CPTs drop in later.

## Files to create
```
wp-content/plugins/custom-post-types-dp/
├── custom-post-types-dp.php        # plugin header + bootstrap
├── includes/
│   ├── class-loader.php            # discovers + requires modules
│   └── post-types/
│       └── index.php               # "Silence is golden"; future CPT files go here
├── LICENSE                         # MIT, (c) 2026 Ian James Puzon <adrianejames27@gmail.com>
├── index.php                       # "Silence is golden"
└── uninstall.php                   # empty-safe cleanup stub (optional)
```

## Files to edit
- `.gitignore`: replace line 19 `!/wp-content/plugins/your-custom-plugin/` with `!/wp-content/plugins/custom-post-types-dp/` (plugin dir is ignored by allowlist otherwise).
- `AGENTS.md`: update "tracked" + "ignored" sections and Overview ("No custom theme/plugin exists yet") to mention new plugin.

## Design
- `custom-post-types-dp.php`: header
  - Plugin Name: Custom Post Types DP
  - Description: Loads all portfolio modules when active.
  - Version 0.1.0, Requires at least 6.5, Requires PHP 8.4, Author Ian James Puzon, License MIT, License URI https://opensource.org/licenses/MIT, Text Domain `custom-post-types-dp`.
  - WP headers have no author-email field; put email in file docblock (`@author Ian James Puzon <adrianejames27@gmail.com>`) and in `LICENSE` copyright line. Optionally `Author URI: mailto:adrianejames27@gmail.com`.
  - `defined( 'ABSPATH' ) || exit;` guard.
  - Define constants `CPTDP_VERSION`, `CPTDP_PATH`, `CPTDP_URL`.
  - Require `includes/class-loader.php`, call `CPTDP_Loader::init()`.
- `CPTDP_Loader`: hooks `plugins_loaded`; globs `includes/post-types/*.php` (skip `index.php`), requires each. Zero modules = no-op. Each future module self-registers on `init`.
- WPCS: tabs, Yoda, `esc_*`, prefix `cptdp_` for functions/classes.
- Future module convention: one file per CPT, `register_post_type` + taxonomies on `init`, `show_in_rest => true`, flush rewrite on activation only.

## Verification
1. `ddev wp plugin list` → `custom-post-types-dp` listed, status inactive.
2. `ddev wp plugin activate custom-post-types-dp` → success, no PHP notices (`ddev logs` / debug.log).
3. `ddev wp plugin get custom-post-types-dp --field=description` → "Loads all portfolio modules when active."
4. Drop dummy module file into `includes/post-types/`, confirm loaded, then remove.
5. `git status` shows plugin dir tracked (allowlist works); `git check-ignore -v` returns nothing for plugin files.
6. Commit: `feat: add custom-post-types-dp master plugin scaffold`.
