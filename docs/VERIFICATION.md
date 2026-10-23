# Verification — Site Staples 1.0.0

Run date: 2026-10-22. PHP CLI 8.5.4, DOM/Libxml, Fileinfo, GD, Python 3/SQLite,
and local WordPress 7.1.3 core source. Minimum declared support is WordPress 6.6 /
PHP 8.1; that version matrix has **not** been executed in this sandbox.

## Passed automated checks

- `php tests/run.php`: **94 assertions**. Actual WordPress hook registration and
  lifecycle actions; disabled module lazy loading; activation/reactivation/defaults;
  deactivation and settings persistence; scoped classic/block comment suppression;
  POST/capability/nonce guards and denial at every action handler.
- SVG: bundled sanitizer removes script, event attributes and foreign content;
  rejects malformed XML, invalid roots, missing namespaces, DTD/entities, PHP
  instructions and oversized input; strips external resources, CSS escape URLs,
  CSS, animation and foreign namespaces; enforces role/upload capability and
  network MIME policy; exercises the real core MIME pipeline, upload rejection,
  final custom-action guard and Media Library preview data.
- Duplication: draft/content/excerpt, featured image, safe metadata and terms;
  exclusions for locks/old state/protected and unique metadata; denials for source
  read, type creation and taxonomy assignment permissions and unsupported types.
- Transfer: versioned round trip; malformed/oversized JSON, unknown/missing keys,
  wrong types/versions, unknown roles/types, object-versus-array distinction;
  token/permissions/stale previews; typed Settings API persistence; importing a
  zero revision limit never mutates content.
- Revisions: production prepared SQL executed against isolated SQLite fixtures;
  exact preview count, 100-item batch, multiple batches, completed retry, newest
  normal revisions, autosaves, parent content and revisions created after preview;
  zero limit, missing parent delete capability, changed policy, concurrent lock,
  and required confirmation. Uses actual core revision/autosave helpers.
- Media: actual WordPress MIME checks and actual GD decoding/thumbnail generation;
  extension mismatch, forged contents, partial upload, missing permission and
  non-HTTP provenance; thumbnail-generation failure preserves original; injected
  metadata commit failure restores original bytes/data and removes new thumbnails;
  successful transaction regenerates dimensions and real thumbnails while keeping
  attachment ID/path/URL; concurrent replacement lock.
- Uninstall: default settings remain, opt-in removes configuration, site content
  remains. Network upload-policy checks use a test double, not a live network.
- `php tests/ui.php`: **53 structural checks** on actual plugin settings markup
  rendered through WordPress's Settings API table helpers. Checks unique IDs,
  native associated labels, separate forms, disabled defaults, hidden action tools,
  explicit required confirmations, preview tokens, readable change descriptions
  and labeled import selection.
- `php tools/lint.php`: syntax checks on all first-party, test, tool and bundled
  PHP files. Release archive inspection checks a single `site-staples/` root,
  bundled sanitizer/license, required runtime files and no development files.

## Scope and unverified work

These are isolated integration and unit tests. Session, option/post storage,
capability mapping, nonces and upload provenance are API doubles. No live WordPress
site database was changed, no existing users were created, and tests never load
`wp-config.php`. SQLite exercises the production SQL shape and results; it does
not establish MySQL/MariaDB query plans or large-site performance.

Shell networking/sockets are blocked. Composer dependency installation failed
because Packagist could not be resolved, so PHP_CodeSniffer/WPCS and the optional
PHP compatibility sniffs were **not run**. The maintained sanitizer's complete
runtime files were retrieved from the upstream 1.0.0 raw source using the web
tool, namespace-isolated and bundled; there is no missing runtime dependency.

Headless Chromium launch failed with `sandbox_host_linux.cc: shutdown: Operation
not permitted`. Thus no screenshot, visual layout, keyboard navigation, browser
form submission or assistive-technology audit is claimed. DOM checks establish
markup properties only.

Before production rollout, run these checks on a disposable WordPress installation
and the target hosting stack:

1. Install the supplied ZIP; activate, save each configuration, deactivate and
   reactivate; verify persistence and no content changes. Run WPCS and the
   minimum/current WordPress and PHP test matrix.
2. As an administrator and each selected/unselected role, upload clean/hostile SVGs
   through Media Library and REST; inspect previews and error messages. Repeat
   with multisite network SVG policy allowed/denied.
3. Duplicate public/private content, pages and selected custom types as users with
   differing read/create/assign permissions; verify the editor redirect and copied
   presentation/taxonomy fields for your integrations.
4. Replace each server-supported raster type through the actual multipart form;
   verify original/thumbnail URLs, dimensions, caches, custom metadata hooks, disk
   failure recovery and storage constraints. The successful HTTP upload route is
   not exercised by the CLI suite; its transaction engine is.
5. Preview/confirm/continue cleanup on MySQL/MariaDB with realistic data, autosaves
   and a large revision table. Check cross-user conflicts and interrupted runs.
6. Export/import via browser, cancel/back out of previews, allow them to expire,
   alter settings in a second session, and verify rejection and clear feedback.
7. Check keyboard-only use, focus, narrow screens, 200% zoom and screen-reader
   labels; verify uninstall choices on a disposable single site/network.

Known functional limits and recovery guidance are in README.md. Process crashes
cannot provide a cross-filesystem/database atomic transaction. Offloaded media,
arbitrary third-party unique metadata, custom comments rendering and network-wide
administration require separate integration or future work.
