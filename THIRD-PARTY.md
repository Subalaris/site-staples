# Bundled dependency

Site Staples bundles **enshrined/svg-sanitize 1.0.0** by Daryll Doyle and contributors,
licensed GPL-2.0-or-later. Its complete runtime source is in `lib/svg-sanitize/src/`;
its license and original Composer metadata are in the parent directory.

Upstream: https://github.com/darylldoyle/svg-sanitizer/tree/1.0.0

Files were retrieved from that tag's raw source. Runtime source changes are limited to namespace isolation: `enshrined\svgSanitize` becomes
`SiteStaples\Vendor\SvgSanitize`. This prevents conflicts with other plugins
using another sanitizer version. A PHPCS comment documents that the nesting
exception carries a DOM element as context rather than rendering it. `lib/svg-sanitize/SHA256SUMS` records bundled
file hashes. The scanner CLI is not a runtime dependency and is not distributed.

The plugin applies additional restrictions *outside* the library: 2 MB input
limit, no DTD/entities or PHP instructions, an SVG namespace/root requirement,
removal of CSS/animation/foreign namespaces and escaped attribute values, and
fragment-only links. Some legitimate SVG artwork will lose
styling or embedded resources; convert that artwork to paths or a raster image.

The release ZIP includes this dependency. Composer is not needed on the WordPress
server. For updates, retrieve a maintained upstream release, review security
advisories, apply the namespace transformation, regenerate checksums, run the
security tests and build a new release. Never replace this dependency with a
hand-written regular-expression sanitizer.
