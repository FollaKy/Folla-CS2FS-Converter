=== CS2FS Converter ===
Contributors: follaky
Donate link: https://github.com/follaky
Tags: code snippets, fluent snippets, migration, import, export
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert Code Snippets (CodeSnippets Free / Pro) entries to Fluent Snippets importable JSON, save to uploads folder and locally, and add a one-click "Import local" button inside FluentSnippets.
This plugin is of the "use once" kind - once it's job is done, you can (and should) remove it. It is good practice to remove unused plugins.

== Description ==

CS2FS Converter is a WordPress admin tool that reads Code Snippets / Code Snippets Pro entries and turns them into Fluent Snippets importable JSON files.

**What it does**

- Adds a Tools -> CS2FS Converter page for administrators.
- Lists snippets from the `wp_snippets` table with their name, description, and detected type; lets you choose which to include and optionally override the type (PHP, PHP + HTML, JS, CSS).
- Generates a Fluent Snippets JSON export (code is base64-encoded, snippets are marked draft, `run_at` defaults to `wp_footer`) and downloads it as `fluent-snippets-export-YYYY-MM-DD.json`.
- Saves the same export in `wp-content/uploads/cs2fs_export/` and registers those files via the `fluent_snippets_local_import_files` filter so Fluent Snippets can offer them as "Local" imports.
- Adds a one-click "Import local" cloud icon inside Fluent Snippets to pull the latest export from `uploads/cs2fs_export` without uploading a file.
- Leaves your database untouched; it only reads from `wp_snippets` and writes JSON files.

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

Snippets are marked `draft` and `run_at` defaults to `wp_footer`. Adjust these inside Fluent Snippets after import if needed.

== Changelog ==

= 1.0 =
* Initial release: export from Code Snippets to Fluent Snippets JSON, save to uploads, add inline "Import local" button in Fluent Snippets.

