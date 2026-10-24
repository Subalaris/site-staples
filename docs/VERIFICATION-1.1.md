# Verification — Site Staples

## 1.2.0 redirect scale update — 2026-10-24

The redirect manager now supports 10,000 exact local rules. Existing arrays migrate
to immutable non-autoloaded snapshots; the main settings option stores only a token.
A visitor lookup reads one hash bucket and never loads the full rule document.
Graph validation and exact-chain flattening occur on save in linear time.

Verification for this update:

- Existing core and feature suites remain in use, including real GD operations,
  real WordPress hooks and revision SQL executed in isolated SQLite.
- `php tests/redirects.php` covers 10,000 unique rules and a 10,000-hop chain,
  frontend read bounds, migration failure/retry/idempotence, paginated destination
  search, add/edit/rename/delete, stale editors, failed snapshot publication,
  failed bucket writes, writer contention, lease recovery, import/export above
  256 KB, complete import replacement, nonce/capability checks, site-origin
  binding, abandoned snapshot recovery and opt-in uninstall cleanup.
- `tests/redirect-rows.cjs` runs the production editor and tab controller in
  headless Chromium with a simulated AJAX transport. It checks 25-row pages,
  search/no-results, independent saves, multiple unsaved drafts, conflicts and
  failures retaining input, native validation, focus after saves, deletion
  confirmation, exclusion of rule fields from the Settings API payload, and
  horizontal overflow at 390px. Desktop and mobile screenshots were inspected.
- PHP syntax checks and the installed Plugin Check review and direct-database
  sniffs are run against runtime files. File-type and direct-access checks run
  against the extracted release ZIP.

These checks do not establish MySQL performance, persistent object-cache behavior,
real browser-to-WordPress AJAX integration, screen-reader behavior or full WCAG
conformance. Install the release ZIP on a staging WordPress site to confirm the
migration and actual redirect response headers before deploying it to production.
The earlier results below describe the 1.1 implementation and its then-current UI.

## Historical 1.1.0 results

The user reported all original v1 features working on their site before this
release. The following checks were performed for 1.1 using PHP CLI 8.5.4 and local
WordPress 7.1.3 core source. Tests do not load or change a live site's database.

## Automated results

- `php tests/run.php`: **95 passing assertions**, retaining all original tests and
  adding a real GD media-replacement transaction with image-size exclusions active.
- `php tests/features.php`: **95 passing assertions** for the new release:
  - Disabled modules do not load; exact v1 settings retain existing values and
    add disabled defaults without writes on read. Version 1 and version 2 import
    compatibility, strict array/object distinctions, maintenance text bounds,
    Unicode character counts and boolean/catalog validation.
  - Redirect chains, 301/302 status, HEAD, POST exclusion, query stripping,
    preview/API exclusion, subdirectory sites, encoded normalization, invalid or
    external paths, protected endpoints, duplicate sources, rule count, self-loops,
    longer cycles, case/punctuation equivalents and corrupt runtime settings.
    Changing the home URL does not reset unrelated saved settings.
  - Native image registry/generation filtering, retention of disabled choices in
    the configuration list, and restoration when filters are removed.
  - Administrator/selected-role maintenance bypass, anonymous and unselected
    roles, escaping, REST 503 error data, no-cache calls, native page response
    arguments, recovery link, and dispatch stopping before redirects.
  - Role selection and multiple-role matching for toolbar/dashboard cleanup,
    precise nodes/contexts, and unchanged capabilities.
  - Explicit post type column registration, original column preservation,
    IDs/thumbnails/empty states, source read permissions and unselected columns.
- `php tests/ui.php`: **117 passing structural checks** using actual WordPress
  Settings API table helpers and the plugin renderer. Covers unique IDs, labels
  for inputs and textareas, disabled defaults, separate action forms, required
  confirmations and readable import previews.
- PHP syntax lint covers all first-party PHP, test tools and the bundled sanitizer.
  Packaging validates a single `site-staples/` root, all runtime modules, dependency
  files, required docs and no tests/development dependencies. Sanitizer file
  checksums remain verified.

## Boundaries

The suite uses real WordPress hooks, MIME checks, revision helpers and GD image
editing. It doubles session/capability/option/post APIs and executes revision SQL
in isolated SQLite. Maintenance tests check the response arguments/error data
passed to native APIs, not an actual network HTTP response. Redirect matching
and validation are tested independently of browser and server canonical redirects.

The existing sandbox restrictions still prevent live database/network/browser
verification. WPCS/compatibility sniffs are configured but not executed because
their Composer dependencies were unavailable. No visual, keyboard, screen-reader,
MySQL performance or minimum PHP/WordPress version matrix pass is claimed.

## Suggested checks on the working site

1. Update the plugin, confirm all old settings remain, then enable each new module
   individually. The new toggles should initially be off.
2. Disable a known image size, upload an image and inspect generated sizes/srcset;
   repeat with file replacement and with your actual upload/storage integrations.
3. Test local 302 redirects before using 301, including trailing slashes, a two-rule
   chain, subdirectory paths where applicable and any existing redirect plugins.
4. Enable maintenance from an administrator session, check a private browser and
   REST endpoint for HTTP 503/Retry-After, then check a selected role. Verify cache
   purges and recovery through login/admin before leaving it enabled.
5. Sign in with selected/unselected admin-cleanup roles, including a multi-role
   account; confirm direct capabilities remain unchanged. Check the optional
   columns on posts, pages and a selected custom type.
6. Export schema 2 settings, preview/reapply them, and preview a saved v1 export.
   Check all changed feature/role/rule/text fields before confirmation.
7. Check narrow screens, keyboard navigation and translated labels; run WPCS and
   the declared minimum/current WordPress and PHP matrix in an unrestricted setup.

Multisite network defaults and overrides remain future work. All configuration
in this release stays per site. See README.md for caching, local redirect grammar,
maintenance exclusions and third-party integration limitations.

## Settings organization update — 2026-10-23

- PHP lint passes for all 37 PHP files; `node --check assets/admin.js` passes.
- Core and feature suites retain 95 passing assertions each.
- The updated isolated Settings API renderer passes 140 structural checks,
  including explicit configuration-form ownership, submittable collapsed fields,
  disclosure defaults, jump-link destinations, adjacent tool placement, separate
  action forms and required confirmations.
- Browser verification was attempted with Playwright against isolated rendered
  HTML and core WordPress styles. Chromium could not start: after resolving its
  shared libraries using existing temporary files, the environment rejected a
  browser process operation (`sandbox_host_linux.cc: Operation not permitted`).
  No browser interaction, screenshot, responsive, keyboard, text-zoom or
  assistive-technology pass is claimed for this update. The live site and its
  database were not used by these tests.
- The package tool now includes the page-scoped `assets/` directory. Existing
  distribution archives have not been rebuilt for this unreleased update.

### Vertical tabs follow-up

- `php tests/ui.php` retains all 140 passing structural checks, and PHP lint and
  JavaScript syntax checks pass. All setting controls keep their configuration
  form ownership when tab panels are hidden.
- `node tests/admin-tabs.cjs` exercises the actual enhancement script with DOM
  stand-ins: selection, roving focus, arrow/Home/End keys, retained edits, unsaved
  feedback, the first invalid field across groups, history, restored selection,
  action-return notices and unavailable session storage. This is a behavior check
  using stand-ins, not a browser or assistive-technology check.
- Groups remain visible and directly linked when JavaScript is unavailable. The
  white background is scoped to the Site Staples settings screen. At narrow
  widths the vertical tab list stacks above its panel.
- The browser restriction described above remains; visual appearance, real
  keyboard interactions, text zoom and responsive layout remain unverified.

### Supplied dashboard template follow-up

The local `site-staples-dashboard.html` design was adapted into the production
admin renderer and page-scoped assets. All eleven modules plus data removal now
render as feature cards. Switches are native checkbox inputs with switch
semantics. Counts and badges reflect edited selections; the sticky save bar
shows unsaved status, and Discard invokes the configuration form's native reset.
Cleanup and import/export retain independent POST forms and confirmations.
The actual WordPress sidebar/toolbar are retained; preview-only shell elements,
fake data, placeholder categories and simulated success dialogs were not copied.

- Core and feature suites each pass 95 assertions. Structural UI checks pass
  146 assertions, covering card coverage, native switch ownership, configuration
  reset ownership and independent action forms as well as existing checks.
- The JavaScript behavior checks pass with DOM stand-ins, now additionally
  exercising switch feedback, enabled counts, dirty/save state and discard.
- PHP lint passes for all 37 files; JavaScript syntax validation passes.
- No browser or screenshot pass is claimed. The known process restriction still
  prevents browser verification; visual matching, responsive layout, focus,
  contrast and assistive-technology behavior need review in the working admin.
- Existing distribution archives have not been rebuilt.

### Readability and visible-options follow-up

- Increased the typography hierarchy to a 12px minimum (14px body/help controls,
  16px main text and feature titles), with larger decorative icons.
- Removed the in-content unsaved warning. The existing persistent footer status
  changes its text and background while retaining the same padding and border.
- Replaced disclosures with always-visible fieldsets. The enhancement disables
  feature options when their switch is off. A configuration-form `formdata`
  handler retains disabled values, including checked/unchecked selections and
  edits made before disabling. Discard restores values and enabled/disabled
  presentation. Without JavaScript, native options remain editable and saveable.
- Structural checks pass 148 assertions. JavaScript behavior checks with DOM
  stand-ins additionally cover inactive fieldsets, retained disabled-field values,
  unchecked selections, edits before disabling, footer feedback and discard.
- Browser verification remains unavailable; these checks do not establish a
  visual, keyboard, responsive or assistive-technology pass.

### Redirect row editor follow-up

- Redirect settings now use From/To text inputs and native temporary/permanent
  selects, with Add/Remove actions, focus handling, a 100-row limit and paired
  path validation. Empty rows are ignored. The form parser still uses the shared
  path, uniqueness and cycle validator; the stored/imported schema is unchanged.
- The legacy textarea form parser remains compatible. PHP feature checks now pass
  106 assertions, adding sparse-row parsing, trimmed paths, empty lists, invalid
  rows and preservation of saved configuration on rejected input.
- Structural UI checks pass 162 assertions. The row-controller checks pass with
  DOM stand-ins for add/remove, names, focus targets, limit handling, incomplete
  rows, change notification and restoration on Discard. Existing tab/controller
  tests and 95 core assertions pass. PHP and JavaScript syntax checks pass.
- The editor script loads before the dashboard enhancement so row restoration
  finishes before save-state feedback refreshes. Native input/select ownership
  also lets disabled feature options retain their values on configuration saves.
- Browser verification remains blocked as described above; simulated behavior
  checks do not establish visual or assistive-technology verification.
