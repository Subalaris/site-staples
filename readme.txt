=== Site Staples ===
Contributors: subalaris
Tags: utilities, comments, svg, duplicate, revisions
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Eleven practical, opt-in features with native per-site settings. Everything starts disabled.

== Description ==
Disable comments, sanitize SVG uploads, duplicate drafts, replace local raster media,
limit revisions, and import/export configuration. Also control image sizes, add local
redirects, show a maintenance page, clean up admin presentation by role and add
useful content columns. Settings → Site Staples.

Bundled dependency: enshrined/svg-sanitize 1.0.0 (GPL-2.0-or-later). PHP DOM and
Fileinfo are required for SVGs. GD/Imagick format support is needed for replacement.
No telemetry, external services or runtime Composer installation.

See README.md for complete configuration, supported formats, integration and
multisite limitations. The redirects editor requires JavaScript; existing redirects work without it.

== Installation ==
1. Upload the ZIP through Plugins → Add New → Upload Plugin.
2. Activate Site Staples.
3. Open Settings → Site Staples, enable desired features and save.

== Frequently Asked Questions ==
= Are comments or settings deleted when I disable the plugin? =
No. Deactivation preserves settings and content. Uninstall removes configuration
only if explicitly enabled in settings. Site content is never deleted on uninstall.

= Does importing settings run cleanup? =
No. Imports update configuration only, after strict validation and confirmation.
Revision cleanup is a separate previewed, confirmed action, up to 100 per batch.

= Which media can I replace? =
Local JPEG, PNG, GIF, WebP and AVIF, with the same MIME type and extension.
Dimensions may change; caches can take time to refresh. Offloaded files and
symbolic links are not supported. ID and main public URL are preserved.

= Does this support multisite? =
Settings are per site; there is no network administration UI. Network activation
leaves unconfigured sites disabled. Network upload policies still apply. Network
uninstall honors each site's individual configuration-removal choice.

== Changelog ==
= 1.3.1 =
* Automatically hide theme menus, headers and footers on custom maintenance pages.
* Keep content filters and WordPress asset hooks for page styles and scripts.

= 1.3.0 =
* Select a published page for custom coming-soon or maintenance content.
* Retain HTTP 503, role bypasses and safe fallback messages.
* Schema 3 exports include the selected page; schema 1/2 imports remain supported.

= 1.2.0 =
* Manage up to 10,000 local redirects with search, pagination and independent saves.
* Automatic migration, indexed lookups, chain compilation and stale-edit protection.
* Settings exports remain compatible; JSON import limit increased to 32 MB.

= 1.1.0 =
* Five new opt-in modules: image sizes, local redirects, maintenance, admin cleanup, columns.
* Existing settings preserved; schema 2 export with schema 1 import compatibility.
* New modules disabled by default, including after upgrade or legacy import.

= 1.0.0 =
* Initial version: six independent, disabled-by-default modules.
* Bundled SVG sanitization, permission checks, safe previews and media rollback.
