# Site Staples 1.1.0

A lightweight WordPress plugin with eleven practical, independently enabled modules.
Every module starts disabled and registers its functionality only when enabled.
Settings are under **Settings → Site Staples**. No services, telemetry, advertising,
JavaScript framework, frontend assets, scheduled jobs, or network calls are used.

## Requirements and installation

- WordPress **6.6 or newer**; PHP **8.1 or newer**.
- PHP DOM/Libxml and Fileinfo for safe SVGs; GD or Imagick supporting the relevant
  raster format for media replacement. PHP Zip is needed only to build the ZIP.
- Standard local WordPress uploads storage for media replacement.

When using the source repository, run `php tools/package.php` to build the
installable ZIP; generated archives are excluded from Git.

Upload `dist/site-staples-1.1.0.zip` through **Plugins → Add New → Upload Plugin**,
install, and activate. The ZIP contains the `site-staples/` plugin folder, including
the sanitizer. Alternatively copy the source folder into `wp-content/plugins/`.
No Composer installation is required on the server. Enable the desired modules
and save. Settings and content are preserved on activation and deactivation.

## Configuration and workflows

### Upgrading from 1.0

Existing options are read without rewriting or resetting your configured modules.
All five new modules start disabled. Saving persists the expanded configuration.
Exports now use schema version 2; complete schema version 1 files still import,
with all five new modules disabled. The import preview shows those changes before
you confirm. Partial or falsely versioned configuration files remain invalid.

### Disable comments

Enable the feature and choose the entire site or selected post types. New comments,
pingbacks and trackbacks are blocked through WordPress submission and REST paths,
including on existing content. Standard forms and displayed comments are hidden.
Comment supports, editor metaboxes and comment list columns are removed for the
selected types. Global mode also hides Comments/Discussion menus, the toolbar
comment item and dashboard activity. Existing comments and post comment-status
values are untouched. Disable the module to restore normal behavior next request.

Themes that hardcode their own form or plugins that insert directly into database
or low-level `wp_insert_comment()` can bypass normal WordPress submission hooks.
Other post types retain their comments in selected mode; the shared Comments menu
remains available for those types. Comment feeds for a globally disabled scope or
an affected singular post return HTTP 403. Custom comment widgets/queries may need
integration; this is not an access-control layer for previously public comments.

### Safe SVG support

Administrators with `manage_options` and `upload_files` are allowed by default.
Additional selected roles must already have `upload_files`; this feature grants
no capabilities. SVGs pass through bundled **enshrined/svg-sanitize 1.0.0** and an
additional restrictive SVG policy. Files that cannot be parsed/sanitized, exceed
2 MB, contain DTD/entities or lack an SVG namespace are rejected. CSS, animation and external
resources are removed, so some artwork changes appearance. SVGZ is not supported.

The normal WordPress upload size, extension and MIME allowlist checks remain in
place. The module never enables unrestricted uploads. Its MIME correction only
applies to validated SVG bytes for an allowed user and allowed MIME mapping,
including custom upload action names. Media Library previews use the sanitized
file with a 300 × 150 presentation box; vector dimensions are not rewritten.
Disabling prevents further SVG uploads but does not delete previously uploaded
SVGs. See [THIRD-PARTY.md](THIRD-PARTY.md) for source, licensing and maintenance.

### Duplicate posts and pages

Select posts, pages and any explicitly desired custom types. Use **Duplicate as
draft** in a content list; the new draft opens in the editor. Users need permission
to read the source, create that type and assign its taxonomies. Content, excerpt,
featured image reference, taxonomy assignments, menu order, parent and appropriate
metadata are copied. Media files themselves and child posts are not duplicated.

Editing locks, old slugs, trash state, unique-looking IDs/SKUs/tokens and protected
integration metadata are excluded. `_thumbnail_id` and `_wp_page_template` are
known safe exceptions. There is no universal registry of unique third-party
metadata: review integrations that put unique data in ordinary custom fields.
Developers can use `site_staples_duplicate_meta_key( $copy, $key, $source_post )`
to opt appropriate fields in or out; hard-excluded editing/unique keys stay out.
Post passwords, source author, slug/GUID and publication date are not copied.

### Replace media

Open an attachment's full **Edit Media** screen and use **Replace file**. The
replacement must have the identical extension (including jpg versus jpeg) and
MIME type. Version 1 supports **JPEG, PNG, GIF, WebP and AVIF** only, when the server
can decode them. SVG, PDF, audio, video and document replacement are excluded.
Permissions require `upload_files` and `edit_post` for the attachment. Checks
include verified HTTP upload provenance, size, WordPress MIME validation,
Fileinfo, image headers and image decoding.

The original attachment ID, attached path and main public URL stay unchanged.
Images and registered thumbnails are prepared in a random staging directory;
overwritten files are backed up and metadata is committed after file generation.
Handled generation, file or metadata failures roll back the changes. Obsolete
registered thumbnails are removed only after success. Previous editor backup
metadata is cleared after success. Historical unreferenced editor backups are
not swept. Big-image auto-scaling is not applied, preserving the main filename.
The original EXIF orientation is retained; thumbnail generation uses the native
editor. Animated GIF/WebP thumbnails depend on the server's editor.

**Dimensions may change. Cached copies can take time to refresh.** Existing HTML
containing hardcoded dimensions or old derivative URLs is not rewritten. Purge
site/CDN caches separately. Do not use on offloaded, symbolic-link, shared-file or
nonlocal storage. Custom storage/metadata hooks may need integration. As with
other filesystem operations, a process kill, machine crash, exhausted filesystem
or failed filesystem rollback cannot be made fully atomic with the database.
Backups are recommended. A process killed mid-operation may leave a
`.site-staples-*` directory and `site_staples_media_lock_ID` option; an administrator
should inspect/recover staged backups before removing that specific stale lock.

### Revision limits and cleanup

Set a maximum of **0–1000** normal revisions per post. The limit applies to post
types supporting revisions, when WordPress next saves a revision. **0 disables
future normal revisions.** Saving/importing settings never triggers an immediate
cleanup. Core WordPress may trim older revisions on the next revision save.
Autosaves are preserved. Other plugins or type-specific revision filters can
still affect the final WordPress retention behavior.

To remove existing old revisions, enable/save the feature, choose **Preview
cleanup**, review the count, explicitly check the confirmation and choose
**Remove next batch**. Each request removes up to 100 old normal revisions and
then shows progress. Continue each batch explicitly. Autosaves, parent posts and
the newest N normal revisions remain; revisions created after the preview are
excluded. Cleanup requires `manage_options` and permission to delete each parent.
A changed limit invalidates the run. Previews expire after one hour. A per-site lock prevents overlapping cleanup
requests. After a killed request, inspect for active cleanup workers before
removing a stale `site_staples_revision_lock_SITEID` option. No unattended
job continues deletion. Back up the database first. SQL preview/count work may
still take time on a very large revisions table; deletion requests are bounded.

### Settings import/export

Enable and save this module to reveal its tools. **Export settings** downloads a
versioned JSON configuration. **Preview import** validates a `.json` file up to
256 KB and shows changed settings before an explicit **Apply imported
configuration** confirmation. The preview lasts 15 minutes and is specific to
the current administrator and site. Changing settings invalidates it. An import
can disable the import/export module itself; enable it again through settings.

Only configuration is imported. Imports never invoke cleanup, duplication or
media replacement. All keys and types are checked: unknown/missing properties,
wrong version, string booleans, out-of-range numbers, duplicate list values and
unregistered roles/post types are rejected. Importing from a site with unavailable
custom post types requires activating their plugin first. The current public shape is in
[docs/settings-schema-v2.json](docs/settings-schema-v2.json); registered type/role
checks are additionally enforced at runtime. All errors explain how to retry.

### Generated image sizes

Enable this module and select the registered image sizes that should **not** be
generated. The settings list shows width, height and crop mode; 0 indicates an
unconstrained dimension. Core/theme registered sizes remain visible in settings
even after disabling them, so you can re-enable them. Saved selections for a
previous theme are retained and marked as not currently registered.

Exclusions affect future uploads, regeneration and replacement. Both server-side
generation and the registered-size list used by newer WordPress upload clients
are filtered. The original image and WordPress big-image scaling remain enabled.
Turning on this module does not delete existing files or rewrite content.
Explicit media replacement still removes obsolete derivatives as documented above.
Fewer generated widths can reduce responsive `srcset` choices and cause larger
images to be downloaded. Some size selectors may omit disabled sizes; a theme
requesting a missing size may fall back to the full image. Third-party processors
that bypass native WordPress size hooks need their own integration.

### Simple redirects

Enable the feature, then add redirect rows with **From path**, **To path** and a
**Redirect type** dropdown. For example, enter `/old-page/`, `/new-page/` and
**302 — Temporary**. Choose **Add redirect** for another rule or **Remove** to
delete a row, then **Save changes** to apply the configuration. Blank rows are
ignored; partially completed rows need both paths. Discard restores the original
rows along with the rest of your configuration. Without JavaScript, fill the
blank row and save to add a rule, or clear both paths to remove an existing rule.

Use 302 while testing, since browsers can retain a 301 after you disable the
module. Up to 100 exact path rules are supported. Sources must be unique; source
and destination must stay on this site. Trailing slashes are treated alike;
matching otherwise uses the path's case. Percent-encoded unreserved characters
normalize consistently. Query strings, fragments, wildcards, absolute/external
URLs, dot segments, encoded delimiters, malformed UTF-8 and protected WordPress
routes are rejected. Include the home URL's path prefix for subdirectory sites.

Only GET/HEAD frontend page requests redirect. Admin, login, PHP endpoints, REST,
feeds, trackbacks and preview routes are left to WordPress. Incoming ordinary
query strings are discarded, preventing accidental forwarding of tokens. Chains
within the plugin resolve directly to the final destination using the first
rule's status. Graph validation rejects cycles, including conservative checks
for case and punctuation variants that WordPress may canonicalize. Other plugins,
server/CDN redirects and permalink changes can still conflict; test the final
URL on your site. No access logging, hit tracking or external lookup is used.

### Maintenance mode

Enable the module, customize its plain-text visitor title/message and optionally
select extra roles that may view the frontend. Empty text uses a translated
default. The title allows 120 Unicode characters and the message 2000.
Administrators with `manage_options` always retain access, and an admin-toolbar
indicator links back to the setting. A logged-in user with any selected role also
bypasses maintenance; merely having an account does not grant a bypass.

Frontend pages return WordPress's native standalone page with **HTTP 503**,
no-cache headers and `Retry-After: 3600`. REST requests from visitors also return
503. Maintenance runs before Site Staples redirects. **Login, wp-admin, AJAX,
cron, XML-RPC and static uploads remain available** for recovery and background
work. This is a visitor notice, not a content privacy/security feature.

Page caches, reverse proxies and CDNs may serve pages without executing WordPress.
Purge or disable those caches when enabling/disabling maintenance. If you lose a
normal admin session, log in at WordPress's login URL and disable the setting.
Deactivating Site Staples restores normal frontend responses on the next request.

### Dashboard & toolbar

Choose the roles to affect and the core dashboard widgets/toolbar items to hide.
A user with **any** selected role is affected, including administrators if selected.
Widgets include Events and News, Quick Draft, Activity, At a Glance and Site Health.
Toolbar choices include the WordPress menu, Updates, Comments, New Content,
Customize and Search; toolbar choices apply in admin and on the frontend.

This only changes presentation: capabilities, direct URLs and permissions are
untouched. The account menu and maintenance recovery indicator are never hidden
by this feature. Third-party widgets and custom toolbar items are outside the
current allowlist. Disable the module to restore normal presentation.

### Content list columns

Select posts, pages and explicit custom types, then choose Content ID, Featured
image and/or Last modified date/time. The columns appear in the supported admin
content lists and preserve existing columns. Thumbnail cells use WordPress's
native image markup; content without an image has a clear empty state. These
columns do not register image sizes, change permissions or add sortable queries.
Dates use the site's date/time format. Disable the module to remove its columns.

## Architecture and development

`site-staples.php` registers lifecycle hooks and a small namespace autoloader.
`includes/Plugin.php` conditionally instantiates enabled modules. Disabled modules
and their dependencies are not loaded. `includes/Settings.php` owns the exact
schema and Settings API sanitization. `includes/Admin.php` renders feature cards
with native form controls grouped into Content, Media, Site behavior, Admin
workspace and Plugin settings. Vertical tabs on a white settings surface show one group at a time; without
JavaScript, the same links navigate the visible groups. Tab selection is retained
after saving, and arrow keys, Home and End move between tabs. Feature options
remain visible. With JavaScript enabled, options become inactive when their
feature is off, while their current values are retained when saving. The supplied dashboard design uses a purple accent (#7212DB), with matching checked controls,
soft violet surfaces, line icons, switch styling and a sticky save bar. Badges and enabled
counts reflect the current selection; a highlighted status in the save bar
indicates unsaved changes without shifting the tab content. Text uses a 12px
minimum, with larger body text and scaled line icons. Discard resets configuration inputs
without resetting independent action confirmations. Small, page-scoped CSS and
JavaScript enhance spacing, keyboard focus and save feedback; settings and
explicit tool confirmations remain usable without JavaScript. Module handlers use POST, scoped nonces,
capability checks and validation; output is escaped and strings are translatable.
`lib/` contains the namespace-isolated sanitizer. No server Composer autoloader
or third-party plugin is required.

Development commands (from this directory):

```sh
php tools/lint.php
WP_ROOT=/path/to/wordpress php tests/run.php
WP_ROOT=/path/to/wordpress php tests/features.php
php tests/ui.php
node tests/admin-tabs.cjs
node tests/redirect-rows.cjs
php tools/package.php
# Optional coding standards tools, after Composer access is available:
composer install
vendor/bin/phpcs
```

The test suite requires PHP DOM, Fileinfo, GD and Python 3 with SQLite. It uses
actual WordPress hooks, core MIME helpers, GD image editing and revision helpers,
API doubles for session/options/posts, and executes the production revision SQL
against an isolated SQLite database. It never bootstraps or alters your site's DB.
See [docs/VERIFICATION-1.1.md](docs/VERIFICATION-1.1.md) for actual results and remaining
live-site checks. Tests and development dependencies are excluded from the ZIP.

## Multisite

Version 1 provides **per-site settings only**. Site activation initializes disabled
defaults for that site. Network activation loads the plugin on all sites but does
not bulk-create configuration: untouched sites read disabled defaults. Each site
administrator configures its own site; no network settings UI, defaults,
overrides or automatic site provisioning are provided. WordPress network upload
file-type policy is respected: SVG must also be allowed there. Role selection
never bypasses network policy. Network uninstall visits sites in pages of 100 and
honors each site's individual uninstall choice. Very large networks should
review uninstall runtime limits.

## Deactivation and uninstall

Deactivation preserves all settings and content. With **Remove Site Staples
configuration when the plugin is deleted** off (the default), uninstall also
preserves configuration. Turn it on and save before deleting the plugin to remove
that site's configuration and plugin-owned temporary administrative options.
Uninstall never deletes posts, comments, revisions, attachments or uploaded files.
Completed media replacements and confirmed revision deletions are not reversible
by deactivation; retain your own backups. Expiring preview state in an external
object cache may remain until its TTL elapses.

## License

GPL-2.0-or-later; see LICENSE and THIRD-PARTY.md.
