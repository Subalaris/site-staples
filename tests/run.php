<?php
require __DIR__ . '/bootstrap.php';

use SiteStaples\Settings;
use SiteStaples\Plugin;
use SiteStaples\Guard;
use SiteStaples\Modules\Svg;
use SiteStaples\Modules\Duplicate;
use SiteStaples\Modules\Media;
use SiteStaples\Modules\Revisions;
use SiteStaples\Modules\Transfer;

$passed = 0;
function expect( $condition, $message ) { global $passed; if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$passed; echo "PASS $message\n"; }
function denied( $callback, $message ) { $caught = false; try { $callback(); } catch ( RuntimeException $e ) { $caught = 403 === $e->getCode(); } expect( $caught, $message ); }
function post_fixture( $id, $type = 'post', $parent = 0, $name = '' ) { $GLOBALS['test_posts'][ $id ] = (object) array( 'ID' => $id, 'post_parent' => $parent, 'post_type' => $type, 'post_status' => 'revision' === $type ? 'inherit' : 'publish', 'post_name' => $name, 'post_title' => 'Example', 'post_content' => '<p>Content \\ example</p>', 'post_excerpt' => 'Excerpt', 'menu_order' => 0, 'comment_status' => 'open', 'ping_status' => 'open' ); return $GLOBALS['test_posts'][ $id ]; }
function image_fixture( $path, $w, $h, $color ) { $image = imagecreatetruecolor( $w, $h ); imagefill( $image, 0, 0, $color ); imagepng( $image, $path ); }
try {
	do_action( 'activate_' . plugin_basename( SITE_STAPLES_FILE ) );
	expect( Settings::get() === Settings::defaults(), 'activation creates disabled defaults' );
	Plugin::boot();
	expect( ! class_exists( Svg::class, false ) && ! class_exists( Media::class, false ), 'disabled modules are not loaded' );
	expect( ! has_filter( 'comments_open' ) && ! has_filter( 'upload_mimes' ), 'disabled modules register no feature hooks' );
	$s = Settings::defaults(); $s['comments']['enabled'] = true; update_option( Settings::OPTION, $s );
	do_action( 'activate_' . plugin_basename( SITE_STAPLES_FILE ) ); do_action( 'deactivate_' . plugin_basename( SITE_STAPLES_FILE ) );
	expect( Settings::get() === $s, 'reactivation and deactivation preserve settings' );
	Plugin::boot(); post_fixture( 10 ); post_fixture( 11, 'page' );
	expect( false === apply_filters( 'comments_open', true, 10 ) && false === apply_filters( 'pings_open', true, 11 ), 'global comments and pings close existing content' );
	expect( is_wp_error( apply_filters( 'pre_comment_approved', 1, array( 'comment_post_ID' => 10 ) ) ), 'new comments rejected with an error' );
	remove_all_filters( 'comments_open' ); remove_all_filters( 'pings_open' ); remove_all_filters( 'pre_comment_approved' );
	$comments = new SiteStaples\Modules\Comments( array( 'scope' => 'selected', 'post_types' => array( 'post' ) ) );
	expect( false === $comments->open( true, 10 ) && true === $comments->open( true, 11 ), 'selected comment scope preserves other types' );
	expect( array() === $comments->comments( array( 'existing' ), 10 ) && array( 'existing' ) === $comments->comments( array( 'existing' ), 11 ), 'comments hidden only within selected scope' );
	expect( '' === $comments->block( '<form>Comments</form>', array( 'blockName' => 'core/comments' ), (object) array( 'context' => array( 'postId' => 10 ) ) ) && '<form>Comments</form>' === $comments->block( '<form>Comments</form>', array( 'blockName' => 'core/comments' ), (object) array( 'context' => array( 'postId' => 11 ) ) ), 'block-theme comment forms and lists respect selected scope' );
	expect( apply_filters( 'comments_open', true, 10 ), 'normal hooks restore when module is absent on next request' );

	$_SERVER['REQUEST_METHOD'] = 'POST'; $GLOBALS['test_caps']['manage_options'] = false;
	denied( fn() => Guard::post( 'example' ), 'unauthorized administrative actions denied' );
	expect( Settings::get() === Settings::sanitize( array() ), 'unauthorized settings save preserves configuration' );
	$GLOBALS['test_caps']['manage_options'] = true; $GLOBALS['test_nonces'] = false;
	denied( fn() => Guard::post( 'example' ), 'invalid nonces denied' );
	$GLOBALS['test_nonces'] = true; $_SERVER['REQUEST_METHOD'] = 'GET';
	denied( fn() => Guard::post( 'example' ), 'GET mutation denied' ); $_SERVER['REQUEST_METHOD'] = 'POST';

	$svg = new Svg( array( 'roles' => array( 'editor' ) ) );
	$clean = Svg::sanitize( '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><foreignObject><p>bad</p></foreignObject><rect width="10" height="10"/></svg>' );
	expect( ! is_wp_error( $clean ) && ! str_contains( $clean, 'script' ) && ! str_contains( $clean, 'onload' ) && ! str_contains( $clean, 'foreignObject' ), 'bundled sanitizer removes scripts, handlers and foreign objects' );
	$clean = Svg::sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><style>@import "https://evil.test/a";</style><image href="https://evil.test/a"/><path style="fill:url(https://evil.test/a)"/><use href="javascript:alert(1)"/></svg>' );
	expect( ! is_wp_error( $clean ) && ! str_contains( $clean, 'evil.test' ) && ! str_contains( $clean, 'javascript' ) && ! str_contains( $clean, 'style' ), 'SVG CSS and external resource references removed' );
	$clean = Svg::sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="\\75rl(https://evil.test/x)"/><image srcset="https://evil.test/x 1x"/><animateTransform attributeName="transform"/><a xmlns="http://www.w3.org/1999/xhtml" href="#x">text</a></svg>' );
	expect( ! is_wp_error( $clean ) && ! str_contains( $clean, 'evil.test' ) && ! str_contains( $clean, 'animateTransform' ) && ! str_contains( $clean, 'xhtml' ), 'SVG strips escaped CSS URLs, HTML namespaces, resource attributes and animation' );
	foreach ( array( '<svg>', '<html/>', '<svg/>', '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>', '<?php echo 1; ?><svg xmlns="http://www.w3.org/2000/svg"/>', str_repeat( 'a', Svg::MAX_BYTES + 1 ) ) as $unsafe ) {
		expect( is_wp_error( Svg::sanitize( $unsafe ) ), 'invalid, oversized or entity-bearing SVG rejected' );
	}
	$path = $GLOBALS['test_uploads'] . '/test.svg'; file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="2"/></svg>' );
	$GLOBALS['test_caps']['manage_options'] = false; $GLOBALS['test_roles'] = array( 'subscriber' );
	expect( ! $svg->allowed() && ! isset( $svg->mimes( array() )['svg'] ), 'unselected SVG role denied' );
	expect( false === $svg->filetype( array(), $path, 'test.svg', array( 'svg' => 'image/svg+xml' ) )['type'], 'SVG MIME override cannot bypass role restriction' );
	$GLOBALS['test_roles'] = array( 'editor' );
	expect( $svg->allowed(), 'explicit SVG role with upload permission allowed' );
	$GLOBALS['test_caps']['upload_files'] = false; expect( ! $svg->allowed(), 'role selection does not grant upload capability' );
	$GLOBALS['test_caps']['upload_files'] = true; $GLOBALS['test_caps']['manage_options'] = true;
	$GLOBALS['test_multisite'] = true; expect( ! isset( $svg->mimes( array() )['svg'] ), 'SVG respects network policy even when network filter ran earlier' );
	$GLOBALS['test_network_svg'] = true; expect( isset( $svg->mimes( array() )['svg'] ), 'network-allowed SVG remains available for permitted role' ); $GLOBALS['test_multisite'] = false;
	$svg->register(); expect( 'image/svg+xml' === wp_check_filetype_and_ext( $path, 'test.svg', array( 'svg' => 'image/svg+xml' ) )['type'], 'real WordPress MIME pipeline accepts sanitized SVG' );
	expect( false === wp_check_filetype_and_ext( $path, 'test.svg', array( 'png' => 'image/png' ) )['type'], 'SVG respects caller and network MIME restrictions' );
	file_put_contents( $path, '<svg' ); expect( isset( $svg->prefilter( array( 'name' => 'test.svg', 'tmp_name' => $path ) )['error'] ), 'unsafe SVG upload returns a useful error' );
	$attachment = post_fixture( 20, 'attachment' ); $attachment->post_mime_type = 'image/svg+xml'; $GLOBALS['test_meta'][20]['_wp_attached_file'] = array( $path );
	expect( ! empty( $svg->preview( array( 'url' => 'test.svg' ), $attachment )['sizes']['full'] ), 'SVG Media Library preview supplied' );
	$final = $svg->final_upload( array( 'file' => $path ) ); expect( isset( $final['error'] ) && ! file_exists( $path ), 'custom upload final guard rejects and removes unsafe SVG' );

	$duplicate = new Duplicate( array( 'post_types' => array( 'post', 'page' ) ) );
	$GLOBALS['test_meta'][10] = array( '_thumbnail_id' => array( 55 ), '_wp_page_template' => array( 'custom.php' ), 'subtitle' => array( 'Hello' ), '_edit_lock' => array( 'lock' ), '_wp_old_slug' => array( 'old' ), '_sku' => array( 'unique' ), 'external_id' => array( 'abc' ), '_integration_data' => array( 'private' ) );
	$GLOBALS['test_terms'][10]['category'] = array( 4, 6 ); $id = $duplicate->duplicate( 10 );
	expect( is_int( $id ) && 'draft' === get_post( $id )->post_status && get_post( $id )->post_content === get_post( 10 )->post_content, 'duplicate is a draft with exact content and excerpt' );
	expect( 55 === get_post_meta( $id, '_thumbnail_id', true ) && 'Hello' === get_post_meta( $id, 'subtitle', true ) && array( 4, 6 ) === $GLOBALS['test_terms'][$id]['category'], 'duplicate copies featured image, safe metadata and taxonomy assignments' );
	expect( '' === get_post_meta( $id, '_edit_lock', true ) && '' === get_post_meta( $id, '_sku', true ) && '' === get_post_meta( $id, 'external_id', true ) && '' === get_post_meta( $id, '_integration_data', true ), 'duplicate excludes editing state and unique/protected metadata' );
	foreach ( array( 'read_post', 'create_posts', 'assign_terms' ) as $cap ) { $GLOBALS['test_caps'][$cap] = false; expect( is_wp_error( $duplicate->duplicate( 10 ) ), 'duplicate denies missing ' . $cap ); $GLOBALS['test_caps'][$cap] = true; }
	expect( is_wp_error( $duplicate->duplicate( 20 ) ), 'duplicate excludes unselected types' );

	$doc = array( 'plugin' => 'site-staples', 'schema_version' => 2, 'settings' => Settings::defaults() );
	expect( Settings::defaults() === Transfer::parse( json_encode( $doc ) ), 'versioned export round trips through strict import validation' );
	$invalids = array( '{', '[]', str_repeat( 'x', Transfer::MAX_BYTES + 1 ) );
	foreach ( array( 'version', 'extra', 'boolean', 'limit', 'role', 'type', 'missing', 'action' ) as $case ) {
		$bad = $doc;
		switch ( $case ) {
			case 'version': $bad['schema_version'] = 999; break;
			case 'extra': $bad['extra'] = true; break;
			case 'boolean': $bad['settings']['svg']['enabled'] = 'true'; break;
			case 'limit': $bad['settings']['revisions']['limit'] = -1; break;
			case 'role': $bad['settings']['svg']['roles'] = array( 'unknown' ); break;
			case 'type': $bad['settings']['duplicate']['post_types'] = array( 'attachment' ); break;
			case 'missing': unset( $bad['settings']['media'] ); break;
			case 'action': $bad['settings']['revisions']['cleanup'] = true; break;
		}
		$invalids[] = json_encode( $bad );
	}
	foreach ( $invalids as $bad ) { expect( is_wp_error( Transfer::parse( $bad ) ), 'invalid import rejected without side effects' ); }
	$bad = $doc; $bad['settings']['svg']['roles'] = (object) array(); expect( is_wp_error( Transfer::parse( json_encode( $bad ) ) ), 'strict import rejects a JSON object in an array field' );
	$transfer = new Transfer( array() ); $state = array( 'token' => 'valid', 'before' => Settings::get(), 'settings' => Settings::defaults() );
	$state['settings']['revisions'] = array( 'enabled' => true, 'limit' => 0 );
	$before_posts = $GLOBALS['test_posts'];
	expect( is_wp_error( $transfer->apply_preview( $state, 'wrong' ) ), 'import requires its preview token' );
	$GLOBALS['test_caps']['manage_options'] = false; expect( is_wp_error( $transfer->apply_preview( $state, 'valid' ) ), 'unauthorized import denied' ); $GLOBALS['test_caps']['manage_options'] = true;
	add_filter( 'sanitize_option_' . Settings::OPTION, array( Settings::class, 'sanitize' ) );
	expect( true === $transfer->apply_preview( $state, 'valid' ) && 0 === Settings::get()['revisions']['limit'] && Settings::get()['revisions']['enabled'], 'typed imported settings persist through Settings API sanitization' );
	expect( $before_posts === $GLOBALS['test_posts'], 'import never runs cleanup or changes content' );
	expect( is_wp_error( $transfer->apply_preview( $state, 'valid' ) ), 'stale import preview rejected' );

	$GLOBALS['test_posts'] = array(); post_fixture( 1 );
	for ( $i = 2; $i <= 207; ++$i ) { post_fixture( $i, 'revision', 1, '1-revision-v1' ); }
	post_fixture( 208, 'revision', 1, '1-autosave-v1' );
	$revisions = new Revisions( array( 'limit' => 2 ) ); $state = $revisions->preview();
	expect( 204 === $state['count'], 'cleanup SQL preview counts only excess normal revisions' );
	expect( 208 === count( $GLOBALS['test_posts'] ), 'revision preview never deletes content' );
	post_fixture( 209, 'revision', 1, '1-revision-v1' );
	$state = $revisions->cleanup( $state ); expect( 100 === $state['deleted'] && 104 === $state['remaining'], 'revision cleanup is bounded to 100 per batch' );
	$state = $revisions->cleanup( $state ); $state = $revisions->cleanup( $state );
	expect( 204 === $state['deleted'] && 0 === $state['remaining'] && get_post(208) && get_post(209) && get_post(206) && get_post(207), 'cleanup preserves autosaves, newest N and post-preview revisions' );
	expect( 0 === $revisions->cleanup( $state )['remaining'], 'completed cleanup is safely repeatable' );
	$GLOBALS['test_caps']['manage_options'] = false; expect( is_wp_error( $revisions->cleanup( $state ) ), 'unauthorized revision cleanup denied' ); $GLOBALS['test_caps']['manage_options'] = true;
	expect( is_wp_error( (new Revisions( array( 'limit' => 3 ) ))->cleanup( $state ) ), 'cleanup rejects changed revision policy' );
	add_option( 'site_staples_revision_lock_1', time() ); expect( is_wp_error( $revisions->cleanup( $state ) ), 'concurrent revision cleanup denied' ); delete_option( 'site_staples_revision_lock_1' );
	$zero = new Revisions( array( 'limit' => 0 ) ); $z = $zero->preview(); expect( 3 === $z['count'], 'zero-limit cleanup counts all normal revisions and excludes autosaves' );
	$GLOBALS['test_caps']['delete_post'] = false; expect( is_wp_error( $zero->cleanup( $z ) ) && get_post(206), 'cleanup checks delete permission on the revision parent' ); $GLOBALS['test_caps']['delete_post'] = true;
	$zero->cleanup( $z ); expect( get_post( 208 ) && get_post( 1 ) && 2 === count( $GLOBALS['test_posts'] ), 'zero-limit cleanup preserves autosave and parent content' );
	$_POST = array(); denied( function () { $GLOBALS['test_caps']['manage_options'] = false; (new Revisions(array('limit'=>0)))->handle_cleanup(); }, 'cleanup handler checks permissions before confirmation' ); $GLOBALS['test_caps']['manage_options'] = true;
	$rejected = false; try { $zero->handle_cleanup(); } catch ( RuntimeException $e ) { $rejected = str_contains( $e->getMessage(), 'explicitly confirm' ); } expect( $rejected, 'cleanup requires explicit confirmation and saved preview' );

	$media = new Media( array() ); $target = $GLOBALS['test_uploads'] . '/original.png'; $source = $GLOBALS['test_uploads'] . '/replacement.png';
	image_fixture( $target, 400, 300, 0xff0000 ); image_fixture( $source, 600, 400, 0x00ff00 );
	$post = post_fixture( 30, 'attachment' ); $post->post_mime_type = 'image/png';
	$original_meta = array( 'width' => 400, 'height' => 300, 'file' => 'original.png', 'sizes' => array() );
	$GLOBALS['test_meta'][30] = array( '_wp_attached_file' => array( $target ), '_wp_attachment_metadata' => array( $original_meta ) );
	$file = array( 'name' => 'replacement.png', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK );
	expect( true === Media::validate_file( $file, $target, 'image/png' ), 'valid image passes real WordPress MIME and GD decoding checks' );
	$bad = $file; $bad['name'] = 'replacement.jpg'; expect( is_wp_error( Media::validate_file( $bad, $target, 'image/png' ) ), 'replacement extension mismatch rejected' );
	$bad_path = $GLOBALS['test_uploads'] . '/fake.png'; file_put_contents( $bad_path, '<?php echo "not image";' ); $bad = $file; $bad['tmp_name'] = $bad_path; expect( is_wp_error( Media::validate_file( $bad, $target, 'image/png' ) ), 'forged image contents rejected' );
	$bad = $file; $bad['error'] = UPLOAD_ERR_PARTIAL; expect( is_wp_error( Media::validate_file( $bad, $target, 'image/png' ) ), 'partial replacement upload rejected' );
	$hash = hash_file( 'sha256', $target ); $GLOBALS['test_caps']['edit_post'] = false;
	expect( is_wp_error( $media->replace( 30, $file ) ) && $hash === hash_file( 'sha256', $target ), 'unauthorized replacement preserves original bytes' ); $GLOBALS['test_caps']['edit_post'] = true;
	expect( is_wp_error( $media->replace( 30, $file ) ), 'replacement rejects non-HTTP upload provenance' );
	$GLOBALS['test_meta_fail_once'] = true; $result = $media->transaction( 30, $source, $target );
	expect( is_wp_error( $result ) && ! $GLOBALS['test_meta_fail_once'] && $hash === hash_file( 'sha256', $target ) && $original_meta === get_post_meta( 30, '_wp_attachment_metadata', true ), 'metadata commit failure rolls back original file and attachment data' );
	expect( ! glob( $GLOBALS['test_uploads'] . '/original-*' ) && ! glob( $GLOBALS['test_uploads'] . '/.site-staples-*' ), 'failure removes staged and newly committed thumbnails' );
	// Fail thumbnail generation before any live files are touched.
	$broken_sizes = static function ( $sizes ) { $sizes['missing'] = array( 'width' => 50, 'height' => 50, 'crop' => true ); return $sizes; };
	class FailingEditor extends WP_Image_Editor_GD { public function multi_resize( $sizes ) { return array(); } }
	$broken_editor = static function () { return array( FailingEditor::class ); };
	add_filter( 'wp_image_editors', $broken_editor ); add_filter( 'intermediate_image_sizes_advanced', $broken_sizes );
	$result = $media->transaction( 30, $source, $target );
	expect( is_wp_error( $result ) && $hash === hash_file( 'sha256', $target ) && $original_meta === get_post_meta(30, '_wp_attachment_metadata', true ), 'thumbnail generation failure preserves original bytes and metadata' );
	remove_filter( 'wp_image_editors', $broken_editor ); remove_filter( 'intermediate_image_sizes_advanced', $broken_sizes );
	$url = wp_get_attachment_url(30); $result = $media->transaction( 30, $source, $target );
	expect( true === $result, 'replacement transaction succeeds with real GD thumbnail generation' );
	$new_meta = get_post_meta(30, '_wp_attachment_metadata', true );
	expect( 600 === $new_meta['width'] && 400 === $new_meta['height'] && ! empty( $new_meta['sizes']['thumbnail'] ) && is_file( $GLOBALS['test_uploads'] . '/' . $new_meta['sizes']['thumbnail']['file'] ), 'replacement regenerates dimensions and actual thumbnails' );
	expect( $url === wp_get_attachment_url(30) && $target === get_attached_file(30), 'replacement preserves ID, attached file path and public URL' );
	add_option( 'site_staples_media_lock_30', time() ); expect( is_wp_error( $media->transaction( 30, $source, $target ) ), 'concurrent replacement denied by lock' ); delete_option( 'site_staples_media_lock_30' );

	$image_controls = new SiteStaples\Modules\ImageSizes( array( 'disabled_sizes' => array( 'thumbnail' ) ) ); $image_controls->register();
	$result = $media->transaction( 30, $source, $target );
	expect( true === $result && empty( get_post_meta( 30, '_wp_attachment_metadata', true )['sizes']['thumbnail'] ), 'media replacement honors enabled image size exclusions' );
	remove_filter( 'intermediate_image_sizes', array( $image_controls, 'names' ), 99 ); remove_filter( 'intermediate_image_sizes_advanced', array( $image_controls, 'filter' ), 99 );

	// Every exposed action handler must fail before doing work for an unauthorized user.
	$old_caps = $GLOBALS['test_caps']; $GLOBALS['test_caps'] = array();
	foreach ( array( array($duplicate, 'handle'), array($media, 'handle'), array($revisions, 'handle_preview'), array($revisions, 'handle_cleanup'), array($transfer, 'export'), array($transfer, 'preview'), array($transfer, 'apply') ) as $handler ) {
		denied( $handler, 'unauthorized action handler denied: ' . get_class($handler[0]) . '::' . $handler[1] );
	}
	$GLOBALS['test_caps'] = $old_caps;
	$preserved = $GLOBALS['test_posts']; $config = Settings::get();
	define( 'WP_UNINSTALL_PLUGIN', plugin_basename( SITE_STAPLES_FILE ) );
	require dirname(__DIR__) . '/uninstall.php';
	expect( $config === Settings::get(), 'uninstall preserves configuration by default' );
	$config['uninstall'] = true; update_option( Settings::OPTION, $config ); SiteStaples\uninstall_site();
	expect( false === get_option( Settings::OPTION ) && $preserved === $GLOBALS['test_posts'], 'opt-in uninstall removes configuration without changing content' );

	echo "\n$passed assertions passed. Core hooks/MIME/GD + isolated API doubles + real SQL queries.\n";
} catch ( Throwable $e ) {
	fwrite( STDERR, $e . "\n" ); exit( 1 );
} finally {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $GLOBALS['test_uploads'], FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $entry ) { $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ); } rmdir( $GLOBALS['test_uploads'] );
}
