# CLAUDE.md: CS2FS Converter

WordPress plugin (`CS2FS-converter.php`, v1.0, Dec 2025) that converts Code
Snippets / Code Snippets Pro entries into a Fluent Snippets import, plus an
"Import local" button inside Fluent Snippets. Successor to the Python/Jupyter
tool in `../CS2FS` (Aug 2025), which does the same from an export file and is
not developed further.

GitHub: `FollaKy/Folla-CS2FS-Converter` (renamed from `…-Migrator`). Text domain `cs2fs-converter`; EN, de_DE, fi.

## Status (1.1, 2026-10-15)

Plan steps 1-4 are done: the tidy-up is commit 963a14c, and the rewrite is version 1.1 (`csfc_scope_map()` /
`csfc_map_snippet()`; export reads rows by ID server-side). Tested on the XAMPP
`wordpress` site (Code Snippets 3.3.0 and 4.0.0 + Fluent Snippets 10.56): 15 snippets,
all scopes; type, run_at, priority, tags, draft status and code verified in FS;
trash excluded; attention report correct. Test rows are named "CS2FS test …"
in `wp_snippets`, and FS + the converter are still installed there.

Also tested 2026-10-15 after updating the local site to Code Snippets 4.0.0
(schema migrated, `condition_id` present): condition rows are skipped, the
conditional snippet is flagged, and the 15 earlier snippets re-export with
identical code (imported as duplicates). Code Snippets 4's flat files are an
execution cache; the DB table is still the source.

**Distribution: wordpress.org** (decided 2026-10-15; Fluent support suggested
it). So no License Server / licence client: wp.org ships updates. The folla.fi
shop can at most carry a 0 € page linking to the wp.org listing. Plugin Check
1.x: no errors (commit 78025fa). Translations de_DE/fi complete. Submission zip:
`cs2fs-converter-1.1.zip` (gitignored; folder `cs2fs-converter/`, slug
`cs2fs-converter` = text domain). Next: user submits at
wordpress.org/plugins/developers/add/; after approval, SVN + banner/icon assets.

The defect list below is the original 2026-10-14 review, kept for reference.

Sources used for the review:
- **Code Snippets 4.0.0**, downloaded from wp.org (scopes in
  `php/Model/Snippet.php::get_all_scopes()`, schema in `php/Core/DB.php`).
- **Fluent Snippets 10.51**: the reference copy in `easy-code-manager/`
  (gitignored, never committed). Run locations in
  `app/Services/CodeRunner.php`, conditions in
  `app/Services/FluentSnippetCondition.php`, shortcode check in
  `app/Hooks/Handlers/CodeHandler.php`.

## Why conversions go wrong

`csfc_render_converter_page()` guesses the type from `scope` only, and
`csfc_handle_export()` gives every snippet `run_at = wp_footer`.

| Code Snippets scope | Converter now | Correct Fluent Snippets target |
|---|---|---|
| `global` | PHP, wp_footer | `PHP`, `run_at: all` |
| `admin` | PHP, wp_footer | `PHP`, `run_at: backend` |
| `front-end` | PHP, wp_footer | `PHP`, `run_at: all` + prepend `if ( is_admin() ) { return; }` (FS has no front-end-only PHP option, and no condition for it) |
| `single-use` | PHP, wp_footer | `PHP`, `run_at: all`, keep draft and warn: FS has no "run once" |
| `content` | php_content, wp_footer | `php_content`, `run_at: shortcode`. Warn: pages using `[code_snippet id=…]` must switch to Fluent Snippets' shortcode |
| `head-content` | **PHP** (wrong) | `php_content`, `run_at: wp_head` |
| `body-content` (new in 4.0) | **PHP** (wrong) | `php_content`, `run_at: wp_body_open` |
| `footer-content` | **PHP** (wrong) | `php_content`, `run_at: wp_footer` |
| `admin-css` | **PHP** (wrong) | `css`, `run_at: admin_head` |
| `site-css` | css, wp_footer | `css`, `run_at: wp_head` (FS outputs CSS in wp_head or admin_head only) |
| `site-head-js` | js, wp_footer | `js`, `run_at: wp_head` |
| `site-footer-js` | js, wp_footer | `js`, `run_at: wp_footer` |
| `condition` (new in 4.0) | PHP (wrong) | **skip**: rows holding condition definitions, not code |

Fluent Snippets run locations, for reference: PHP `all` / `backend`;
JS `wp_head` / `wp_footer` / `admin_head` / `admin_footer`; CSS `wp_head` /
`admin_head`; php_content `wp_head` / `wp_footer` / `wp_body_open` /
`before_content` / `after_content` / `shortcode`.

## Other defects

- **Trashed snippets are exported.** Code Snippets marks trash as
  `active = -1`. Exclude them by default.
- **Priority and tags are dropped.** Both are columns (`priority` SMALLINT,
  `tags` LONGTEXT, comma-separated); FS meta has `priority` and `tags`.
- **Code Snippets conditions are lost silently.** Snippets with
  `condition_id > 0` should be flagged in the UI and the import report
  (FS conditions are a different model: user/page/date/FluentCRM rules), so
  the user can recreate them.
- **Active state is not reported.** Importing as draft is right (running the
  same code in both plugins can fatal on duplicate functions), but the user
  needs to know which snippets were active in Code Snippets so they can
  publish them in FS after deactivating Code Snippets.
- **Code goes through the browser.** The form posts every snippet's code back
  in hidden fields (`snippet[i][code]`). That can change line endings, hits
  `max_input_vars` on large sites, and trusts posted code. Post only IDs and
  read the rows server-side.
- **`str_contains()` fatals on PHP 7.4**, although the header says
  `Requires PHP: 7.4`.
- **Multisite network snippets** (`{base_prefix}ms_snippets`) are not read.
  Decide whether to support or just document.
- The local-import AJAX handler requires `install_plugins` but the page
  requires `manage_options`; align them. FS itself requires
  `unfiltered_html` to save snippets.

## Tidying (uncommitted state as of 2026-10-14)

- Real change: Finnish translation renamed `cs2fs-converter-fi_FI.*` →
  `cs2fs-converter-fi.*` (WordPress's Finnish locale is `fi`, so the
  `fi_FI` file never loaded). Both `cs2fs-converter-fi.po` and `…-fi.mo` exist (both untracked; git sees the
  `fi_FI` files as deleted), so commit them as a rename. `Folla-CS2FS-Converter.zip` was rebuilt.
- Everything else showing as modified is line endings only
  (`git diff --ignore-cr-at-eol` shows nothing). Add a `.gitattributes` or
  normalise before committing.
- Remove `backup-local-changes.patch` (leftover from an old merge conflict).
- `Folla-CS2FS-Converter.zip` should probably be a build output, not a
  committed file.
- `../CS2FS` (the Python version) has only line-ending changes; nothing to
  commit there.

## Plan

1. Tidy as above.
2. Rewrite the mapping as one table-driven function (scope → type, run_at,
   optional code prefix, warning), used by both the listing and the export.
3. Post IDs only; read code, priority, tags, active, condition_id from the
   table; skip trash and `condition` rows; show "was active" and "had a
   condition" in the list and the import report.
4. Test locally: no site has both plugins yet. Use the XAMPP `wordpress`
   site (has Code Snippets), add Fluent Snippets, create one snippet per
   scope (plus a trashed one, one with priority/tags, one with a
   condition), convert, import, and check every snippet's type, run_at,
   code, priority and tags in Fluent Snippets. Drive it with XAMPP's
   `php.exe` + `C:\xampp\wp-cli\wp-cli.phar` (WSL PHP has no MySQL driver).
