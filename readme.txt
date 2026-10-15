=== CS2FS Converter ===
Contributors: follaky
Donate link: https://github.com/follaky
Tags: code snippets, fluent snippets, migration, import, export
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert Code Snippets (CodeSnippets Free / Pro) entries to Fluent Snippets importable JSON, save to uploads folder and locally, and add a one-click "Import local" button inside FluentSnippets.
This plugin is of the "use once" kind - once it's job is done, you can (and should) remove it. It is good practice to remove unused plugins.

== Description ==

CS2FS Converter is a WordPress admin tool that reads Code Snippets / Code Snippets Pro entries and turns them into Fluent Snippets importable JSON files.

**What it does**

- Adds a Tools -> CS2FS Converter page for administrators.
- Lists snippets from the `wp_snippets` table (trash excluded) with their Code Snippets scope, the Fluent Snippets type and run location they map to, and notes on anything you must check by hand. You choose which to include and can override the type.
- Maps every Code Snippets scope to the matching Fluent Snippets type and run location (see the table below), and keeps tags and priority.
- Generates a Fluent Snippets JSON export (code is base64-encoded, snippets are marked draft) and downloads it as `fluent-snippets-export-YYYY-MM-DD.json`. Code is read from the database on the server, not posted through the browser.
- Saves the same export in `wp-content/uploads/cs2fs_export/` and registers those files via the `fluent_snippets_local_import_files` filter so Fluent Snippets can offer them as "Local" imports.
- Adds a one-click "Import local" button inside Fluent Snippets to pull the latest export from `uploads/cs2fs_export` without uploading a file. The import report lists snippets that need attention (was active, had a condition, single-use, content shortcode).
- Leaves your database untouched; it only reads from `wp_snippets` and writes JSON files.

**Scope mapping**

* global: PHP / everywhere
* admin: PHP / admin only
* front-end: PHP / everywhere, with an `is_admin()` guard prepended
* single-use: PHP / everywhere (no "run once" in Fluent Snippets: flagged)
* content: PHP + HTML / shortcode (flagged: replace `[code_snippet]` shortcodes)
* head-content, body-content, footer-content: PHP + HTML / wp_head, wp_body_open, wp_footer
* admin-css, site-css: CSS / admin_head, wp_head
* site-head-js, site-footer-js: JS / wp_head, wp_footer
* condition: skipped (condition definitions, not code)

**Limits**

- Code Snippets conditions are not converted; affected snippets are flagged so you can recreate them in Fluent Snippets.
- Multisite network snippets (`ms_snippets`) are not read.
- The front-end guard is a plain `return` at the top of the file, so a snippet starting with a `namespace` declaration needs editing by hand.

== Installation ==

1. Upload the plugin files (`CS2FS-converter.php` and `assets/`) to `/wp-content/plugins/cs2fs-converter/`, or install the plugin ZIP through the WordPress plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Tools -> CS2FS Converter to export snippets.

== Frequently Asked Questions ==

= What do I need installed? =

Code Snippets (or Code Snippets Pro) must be present so `wp_snippets` exists; Fluent Snippets must be installed to import and to see the inline "Import local" button.

= Where are exports saved? =

Each export is downloaded and also saved to `wp-content/uploads/cs2fs_export/`. Fluent Snippets can read from this directory via the provided filter.

= What defaults are applied to exported snippets? =

Snippets are imported as `draft`, so nothing runs twice while Code Snippets is still active. Deactivate Code Snippets, then publish the snippets you need in Fluent Snippets.

== Changelog ==

= 1.1 =
* Correct Fluent Snippets type and run location for every Code Snippets scope (head/body/footer content and admin CSS were imported as PHP; everything ran in the footer).
* Keep tags and priority; skip trashed snippets and condition rows.
* Flag snippets that were active, had a condition, are single-use or are content shortcodes.
* Read code on the server instead of posting it through the form; works on PHP 7.4.
* Finnish translation now loads (file renamed to the `fi` locale).

= 1.0 =
* Initial release: export from Code Snippets to Fluent Snippets JSON, save to uploads, add inline "Import local" button in Fluent Snippets.

