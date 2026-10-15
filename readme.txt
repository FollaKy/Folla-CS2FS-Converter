=== CS2FS Converter ===
Contributors: follaky
Tags: code snippets, fluent snippets, migration, import, export
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Move your Code Snippets (free or Pro) to Fluent Snippets: the right type and location for every snippet, imported as drafts in one click.

== Description ==

Moving from Code Snippets to Fluent Snippets? This plugin copies your snippets across, puts each one in the right place, and tells you which ones need a look before you switch them on.

It started as a Python script of mine. This is the same idea, inside WordPress.

**How it works**

1. Go to Tools > CS2FS Converter. You'll see all your snippets, what each one becomes in Fluent Snippets, and any notes.
2. Untick the ones you don't want and click Generate. You get a JSON file to keep as a backup.
3. Open Fluent Snippets and click "Import local". Done.

Everything arrives as a draft, so nothing runs twice while Code Snippets is still active. When you're ready, deactivate Code Snippets and publish the snippets you need.

**What carries over**

* The code, name, description, tags and priority.
* The right type and location for every kind of snippet: PHP, CSS, JavaScript, and HTML for the head, body or footer.
* Which snippets were active, so you know what to publish.

**What needs a look**

The converter flags these, both on the list and after the import:

* Snippets with a condition (Code Snippets 4). Fluent Snippets has its own conditions, so set them up again there.
* Single-use snippets. Fluent Snippets has no "run once", so a published one runs on every page load.
* Content snippets. Pages using `[code_snippet]` need the Fluent Snippets shortcode instead.

Snippets in the trash are left out.

Once you've moved everything, delete this plugin. You only need it once.

**Where each snippet goes**

* Run everywhere: PHP, everywhere
* Only run in the admin: PHP, admin only
* Only run on the site front-end: PHP, everywhere, with a check that stops it in the admin
* Only run once: PHP, everywhere (flagged)
* Content (shortcode): PHP + HTML, shortcode (flagged)
* Head, body and footer content: PHP + HTML in wp_head, wp_body_open or wp_footer
* Admin and site CSS: CSS in admin_head or wp_head
* Head and footer JavaScript: JS in wp_head or wp_footer

You can still change the type of any snippet on the list before exporting.

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the ZIP there.
2. Activate it.
3. Go to Tools > CS2FS Converter.

You need Code Snippets (free or Pro) with your snippets in it, and Fluent Snippets installed for the import.

== Frequently Asked Questions ==

= Does it change my Code Snippets? =

No. It only reads them. Your snippets stay in Code Snippets until you delete them yourself.

= Why are the imported snippets drafts? =

So the same code doesn't run twice. Two copies of a function in both plugins can take a site down. Deactivate Code Snippets first, then publish in Fluent Snippets.

= Where is the export file saved? =

You download it, and a copy goes to `wp-content/uploads/cs2fs_export/` for the "Import local" button. That folder is blocked from the web on Apache and the files get random names. On Nginx, block the folder in your server config or delete the files after importing.

= My snippet starts with a namespace. Anything to watch? =

Yes, if it was a front-end only snippet. The converter adds a short admin check at the top of the code, and PHP needs `namespace` to come first. Move the check below it after importing.

= Does it work on multisite? =

It converts each site's own snippets. Network-wide snippets aren't read.

= Is this an official Code Snippets or Fluent Snippets plugin? =

No. It's an independent tool by Folla Ky and isn't affiliated with either plugin.

== Changelog ==

= 1.1 =
* Every kind of snippet now gets the right type and location. Before, head, body and footer HTML and admin CSS came in as PHP, and everything ran in the footer.
* Tags and priority carry over. Trashed snippets and Code Snippets 4 condition rows are skipped.
* Snippets that were active, had a condition, run once or are shortcodes are flagged.
* The code is read on the server instead of being sent through the form.
* The saved export gets a random file name and is blocked from the web.
* German and Finnish translations are complete, and the Finnish one now loads.
* Works on PHP 7.4. Needs WordPress 6.2 or newer.

= 1.0 =
* First release.
