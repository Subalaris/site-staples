# Changelog

## 1.3.1 — 2026-10-24

- Render custom maintenance pages in a content-only canvas, automatically omitting
  theme menus, headers and footers for blocked visitors.
- Keep WordPress content rendering and asset hooks; render content before wp_head
  so block and builder styles can enqueue, and hide the blocked visitor's admin bar.
- Preserve normal theme navigation for administrators and explicitly allowed roles.

## 1.3.0 — 2026-10-24

- Added a published-page selector for custom maintenance and coming-soon designs.
- Render the selected page through the normal WordPress theme/template pipeline
  at the visitor's original URL, retaining HTTP 503, no-cache and Retry-After.
- Preserve administrator/role bypasses, REST maintenance responses and the plain
  message fallback when a selected page is unavailable or protected.
- Added schema 3 exports with strict legacy schema 1/2 compatibility and page validation.
- Link the maintenance toolbar indicator directly to the Site behavior tab.

## 1.2.0 — 2026-10-24

- Increased redirect capacity to 10,000 rules with linear graph validation and
  chain compilation at save time; visitors use one indexed lookup bucket.
- Moved redirect definitions out of ordinary settings into immutable,
  non-autoloaded snapshots, with automatic migration and verified publication.
- Added server pagination and search, per-rule saves, native validation,
  independent discard/delete controls, stale-tab protection and writer leases.
- Preserved schema 1/2 settings transfers and increased imports to 32 MB.
- Added snapshot retirement and opt-in uninstall cleanup.
- Fixed Plugin Check findings and kept development files outside release ZIPs.

- Replaced redirect text rules with editable From/To rows, native 301/302 type
  selection and Add/Remove actions, retaining shared validation and import schema.

- Applied the supplied dashboard template across every feature group: branded
  header, purple accents, line icons, rounded cards, native checkbox switches,
  enabled counts and a sticky save bar with configuration-only discard.

- Added vertical feature-group tabs and a white settings background, with keyboard
  navigation, remembered selection, direct links and validation in hidden groups.

- Grouped settings into Content, Media, Site behavior, Admin workspace and Plugin
  settings, with direct navigation links and clearer feature labels.
- Kept feature options visible and inactive when disabled, preserving their
  values on save. Increased type to a 12px minimum and scaled the icons.
- Moved unsaved-change feedback into the footer to keep tab content stable.
- Placed cleanup and import/export tools beside their settings, keeping action
  forms and explicit confirmations independent of configuration saves.
- Added page-scoped admin assets and form-ownership regression checks.

## 1.1.0 — 2026-10-22

- Added image-size generation controls with responsive-image explanations.
- Added exact local 301/302 redirects, protected-route checks and cycle prevention.
- Added configurable maintenance pages and REST 503 responses with role bypass.
- Added core dashboard/toolbar cleanup by role without changing capabilities.
- Added optional IDs, featured images and modified timestamps to selected content lists.
- Preserved existing configuration on upgrade; every new module starts disabled.
- Added schema version 2 exports, strict previews for all new fields, and complete
  schema version 1 import compatibility with disabled defaults for new modules.
- Added regression checks for migration, redirects, maintenance, role behavior,
  image-size/media interactions and native settings markup.

## 1.0.0 — 2026-10-22

- Initial release with six independently enabled modules, all disabled by default.
- Scoped comment suppression without deletion or persistent post changes.
- SVG uploads with isolated, bundled enshrined/svg-sanitize 1.0.0 and role restrictions.
- Permission-aware draft duplication with conservative metadata copying.
- Local raster media replacement with staged thumbnails and rollback.
- Future revision limits and explicitly confirmed, batched revision cleanup.
- Strict, versioned configuration exports and previewed imports.
- Native per-site Settings API page and opt-in configuration removal on uninstall.
