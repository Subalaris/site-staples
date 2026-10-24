<?php
/** Scale, migration, failure recovery and API regressions using the isolated WordPress harness. */
require __DIR__ . '/bootstrap.php';

use SiteStaples\Settings;
use SiteStaples\RedirectRules;
use SiteStaples\RedirectStore;
use SiteStaples\RedirectManager;
use SiteStaples\Modules\Redirects;
use SiteStaples\Modules\Transfer;

$assertions = 0;
function redirect_expect( $condition, $label ) {
	global $assertions;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$assertions; echo "PASS $label\n";
}
function redirect_rule( $from, $to, $status = 302 ) { return array( 'from' => $from, 'to' => $to, 'status' => $status ); }
function runtime_redirects() { return new Redirects( Settings::get( false )['redirects'] ); }
class RedirectJson extends RuntimeException {
	public function __construct( public $success, public $payload, public $status ) { parent::__construct( 'JSON response' ); }
}
function check_ajax_referer( $action, $key ) { if ( ! $GLOBALS['test_nonces'] ) { throw new RuntimeException( 'Invalid nonce', 403 ); } }
function wp_send_json_error( $data, $status = 200 ) { throw new RedirectJson( false, $data, $status ); }
function wp_send_json_success( $data ) { throw new RedirectJson( true, $data, 200 ); }
function redirect_api( $post ) {
	$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $post;
	try { ( new RedirectManager() )->handle(); } catch ( RedirectJson $response ) { return $response; }
	throw new RuntimeException( 'No API response' );
}
try {
	$legacy = Settings::defaults();
	$legacy['redirects'] = array( 'enabled' => true, 'rules' => array( redirect_rule( '/old', '/middle', 301 ), redirect_rule( '/middle', '/final' ) ) );
	$legacy['comments']['enabled'] = true;
	update_option( Settings::OPTION, $legacy, false );
	$GLOBALS['test_fail_option_add'] = static fn( $key ) => str_starts_with( $key, RedirectStore::PREFIX ) && str_ends_with( $key, '_bd' );
	// Force a deterministic staged bucket failure regardless of generated token.
	$bucket = substr(hash('sha256','/old'),0,2);
	$GLOBALS['test_fail_option_add'] = static fn( $key ) => str_starts_with( $key, RedirectStore::PREFIX ) && str_ends_with( $key, '_' . $bucket );
	redirect_expect( is_wp_error( Settings::migrate_redirects() ) && get_option( Settings::OPTION ) === $legacy, 'failed migration preserves the original configuration and rules' );
	unset($GLOBALS['test_fail_option_add']);
	redirect_expect( true === Settings::migrate_redirects(), 'existing array rules migrate without reactivation' );
	$generation = RedirectStore::current_token();
	redirect_expect( RedirectStore::valid_token( $generation ) && $legacy === Settings::get(), 'migration round trips the exact previous configuration' );
	redirect_expect( true === Settings::migrate_redirects() && $generation === RedirectStore::current_token(), 'migration is idempotent' );
	redirect_expect( array('url'=>'https://example.test/final','status'=>301) === runtime_redirects()->match('/old'), 'migrated chains collapse with the first source status' );
	redirect_expect( array('url'=>'https://example.test/final','status'=>302) === runtime_redirects()->match('/middle'), 'each chain entry retains its own status' );

	$rules = array();
	for ( $i = 0; $i < 10000; ++$i ) { $rules[] = redirect_rule('/legacy-' . $i, '/destination-' . $i, $i % 2 ? 301 : 302); }
	$settings = Settings::get(); $settings['redirects']['rules'] = $rules;
	$start = microtime(true);
	redirect_expect( true === Settings::save($settings), '10,000 unique rules save successfully' );
	printf("INFO 10,000-rule save: %.3f seconds in the isolated option harness\n", microtime(true)-$start);
	$GLOBALS['test_option_reads'] = array();
	$runtime = runtime_redirects();
	redirect_expect(!in_array(RedirectStore::PREFIX.RedirectStore::current_token(),$GLOBALS['test_option_reads'],true),'frontend settings never load the full rule document');
	$GLOBALS['test_option_reads'] = array();
	redirect_expect( array('url'=>'https://example.test/destination-9999','status'=>301) === $runtime->match('/legacy-9999?discard=secret'), 'last rule in a 10,000-rule set resolves and discards incoming queries' );
	redirect_expect( 1 === count($GLOBALS['test_option_reads']) && preg_match('/_[a-f0-9]{2}$/', $GLOBALS['test_option_reads'][0]), 'a visitor match reads exactly one indexed bucket and no full rule document' );
	$GLOBALS['test_option_reads'] = array();
	redirect_expect( false === $runtime->match('/missing') && 1 === count($GLOBALS['test_option_reads']), 'unmatched requests perform only one bounded bucket lookup' );
	$root = get_option(Settings::OPTION);
	redirect_expect( is_string($root['redirects']['rules']) && strlen(serialize($root)) < 3000, 'ordinary settings contain only a compact snapshot reference' );
	$autoloaded = array_filter($GLOBALS['test_autoload'], static fn($autoload,$key) => str_starts_with($key,RedirectStore::PREFIX) && false !== $autoload, ARRAY_FILTER_USE_BOTH);
	redirect_expect(array() === $autoloaded, 'all redirect manifests, buckets and leases are explicitly non-autoloaded');
	$too_many = $rules; $too_many[] = redirect_rule('/one-too-many','/done');
	redirect_expect( is_wp_error(RedirectRules::validate_rules($too_many)), '10,001 unique rules are rejected without relying on duplicate-path errors' );

	$chain = array();
	for ( $i = 0; $i < 10000; ++$i ) { $chain[] = redirect_rule('/chain-' . $i,'/chain-' . ($i+1)); }
	$start = microtime(true); $compiled = RedirectRules::compile($chain);
	redirect_expect( count($compiled) === 10000 && '/chain-10000' === $compiled['/chain-0']['to'], 'a 10,000-hop chain compiles without recursive stack growth' );
	printf("INFO 10,000-hop validation and compilation: %.3f seconds\n",microtime(true)-$start);
	$chain[9999]['to']='/chain-0';
	redirect_expect(is_wp_error(RedirectRules::compile($chain)), 'a cycle at the end of a 10,000-rule graph is rejected');

	$manager = new RedirectManager();
	$page = $manager->listing('destination-', 400);
	redirect_expect(25 === count($page['rules']) && 400 === $page['pages'] && 400 === $page['page'], 'server pagination returns only the final 25 matching rules' );
	$page = $manager->listing('DESTINATION-9999', 1000);
	redirect_expect(1 === $page['matched'] && 1 === $page['page'] && '/legacy-9999' === $page['rules'][0]['from'], 'search covers destination paths case-insensitively and clamps invalid pages');
	redirect_expect(0 === $manager->listing('nothing-matches')['matched'], 'empty search results are explicit');
	$token = RedirectStore::current_token();
	redirect_expect(true === $manager->mutate('update',$token,'/legacy-0',redirect_rule('/renamed','/target',301)), 'one-row edits can rename a source at the rule limit');
	redirect_expect(false === runtime_redirects()->match('/legacy-0') && 'https://example.test/target' === runtime_redirects()->match('/renamed')['url'], 'renaming removes the old source and immediately exposes the new source');
	redirect_expect(is_wp_error($manager->mutate('delete',$token,'/legacy-1')), 'stale tabs cannot overwrite or delete newer rules');
	$token = RedirectStore::current_token();
	redirect_expect(is_wp_error($manager->mutate('add',$token,'',redirect_rule('/extra','/target'))), 'the manager enforces the 10,000-rule limit');
	redirect_expect(is_wp_error($manager->mutate('update',$token,'/renamed',redirect_rule('/legacy-1','/target'))), 'renaming onto another source is rejected');
	redirect_expect(true === $manager->mutate('delete',$token,'/legacy-1'), 'delete publishes a new complete snapshot');
	redirect_expect(false === runtime_redirects()->match('/legacy-1') && count(Settings::get()['redirects']['rules'])===9999, 'delete removes exactly one stored rule');
	$token = RedirectStore::current_token();
	$old = Settings::get();
	$GLOBALS['test_fail_option_update'] = Settings::OPTION;
	redirect_expect(is_wp_error($manager->mutate('add',$token,'',redirect_rule('/new','/target'))) && Settings::get()===$old && $token===RedirectStore::current_token(), 'failed publication leaves the previous full snapshot active');
	unset($GLOBALS['test_fail_option_update']);
	$GLOBALS['test_fail_option_add'] = static fn($key) => str_starts_with($key,RedirectStore::PREFIX) && str_ends_with($key,'_'.substr(hash('sha256','/new'),0,2));
	redirect_expect(is_wp_error($manager->mutate('add',$token,'',redirect_rule('/new','/target'))) && $token===RedirectStore::current_token(), 'failed bucket writes never publish a partial rule set');
	unset($GLOBALS['test_fail_option_add']);

	add_option(RedirectStore::PREFIX.'lock',time().':other-writer','',false);
	redirect_expect(is_wp_error($manager->mutate('delete',$token,'/renamed')), 'a concurrent writer is rejected');
	delete_option(RedirectStore::PREFIX.'lock');
	add_option(RedirectStore::PREFIX.'lock',(time()-121).':crashed-writer','',false);
	redirect_expect(true===RedirectStore::lock(), 'an abandoned expired writer lease can be reclaimed atomically');
	$GLOBALS['test_options'][RedirectStore::PREFIX.'lock']=time().':successor';
	RedirectStore::unlock();
	redirect_expect(str_ends_with(get_option(RedirectStore::PREFIX.'lock'),':successor'), 'an expired writer cannot release a successor lease');
	delete_option(RedirectStore::PREFIX.'lock');

	$token=RedirectStore::current_token();
	$before=Settings::get();
	$form=array('redirects'=>array('managed'=>'1','enabled'=>'1'),'comments'=>array('enabled'=>'1','scope'=>'all'),'revisions'=>array('limit'=>'10'));
	$storage=Settings::sanitize_storage($form);
	redirect_expect($storage===Settings::sanitize_storage($storage),'Settings API double sanitization preserves prepared storage on first save');
	update_option(Settings::OPTION,$storage,false); RedirectStore::unlock();
	redirect_expect($before['redirects']['rules']===Settings::get()['redirects']['rules'] && $token===RedirectStore::current_token(), 'saving ordinary settings preserves all redirects and reuses their snapshot');
	$export=wp_json_encode(Transfer::document(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
	redirect_expect(strlen($export)>262144 && !is_wp_error(Transfer::parse($export)), 'large exports beyond the old 256 KB limit round trip through the strict parser');
	$preview=array('token'=>'preview','before'=>Settings::get(),'settings'=>Settings::defaults());
	redirect_expect(true===(new Transfer(array()))->apply_preview($preview,'preview') && array()===Settings::get()['redirects']['rules'], 'confirmed imports atomically replace the complete redirect set, including clearing it');
	redirect_expect(false===runtime_redirects()->match('/renamed'), 'cleared imports cannot leave stale compiled redirects active');
	RedirectStore::cleanup($token);
	redirect_expect(false===RedirectStore::document($token), 'retired snapshot manifests can be removed');
	$active=RedirectStore::current_token(); RedirectStore::cleanup($active);
	redirect_expect(false!==RedirectStore::document($active), 'cleanup never removes the active snapshot');

	$token=RedirectStore::current_token();
	redirect_expect(redirect_api(array('operation'=>'list'))->success, 'AJAX listing returns a paginated response');
	redirect_expect(!redirect_api(array('operation'=>'add','version'=>$token,'from'=>'/unsafe','to'=>'https://evil.test','status'=>'302'))->success, 'AJAX rejects external destinations');
	redirect_expect(!redirect_api(array('operation'=>'add','version'=>$token,'from'=>'/new','to'=>'/target','status'=>'302garbage'))->success, 'AJAX rejects malformed status values instead of coercing them');
	$GLOBALS['test_caps']['manage_options']=false;
	redirect_expect(403===redirect_api(array('operation'=>'list'))->status, 'AJAX denies users without manage_options even for reads');
	$GLOBALS['test_caps']['manage_options']=true; $GLOBALS['test_nonces']=false;
	$caught=false; try {redirect_api(array('operation'=>'list'));} catch(RuntimeException $e){$caught=$e->getCode()===403;}
	redirect_expect($caught,'AJAX rejects invalid nonces before processing input'); $GLOBALS['test_nonces']=true;
	// Rebuild a nonempty set before checking runtime site binding and cleanup.
	$config=Settings::get(); $config['redirects']['rules']=array(redirect_rule('/old','/final')); Settings::save($config);
	$GLOBALS['test_home_url']='http://example.test';
	redirect_expect('http://example.test/final'===runtime_redirects()->match('/old')['url'], 'admin HTTPS and frontend HTTP do not disable the same-site lookup');
	$GLOBALS['test_home_url']='https://moved.test';
	redirect_expect(false===runtime_redirects()->match('/old'), 'compiled snapshots fail closed after the site origin changes');
	unset($GLOBALS['test_home_url']);
	// Recover old staging manifests even when the scheduled cleanup did not run.
	$orphan=RedirectStore::stage(array(redirect_rule('/abandoned','/target')));
	$document=RedirectStore::document($orphan); $document['created']=time()-HOUR_IN_SECONDS-1;
	update_option(RedirectStore::PREFIX.$orphan,$document,false);
	RedirectStore::sweep();
	redirect_expect(false===RedirectStore::document($orphan) && false!==RedirectStore::document(RedirectStore::current_token()), 'admin sweep removes abandoned snapshots and preserves the active one');
	$config=Settings::get(); $config['uninstall']=false; Settings::save($config);
	define('WP_UNINSTALL_PLUGIN',plugin_basename(SITE_STAPLES_FILE)); require dirname(__DIR__).'/uninstall.php';
	$token=RedirectStore::current_token();
	redirect_expect(false!==RedirectStore::document($token),'uninstall preserves redirect snapshots unless configuration removal is enabled');
	$config['uninstall']=true; Settings::save($config); SiteStaples\uninstall_site();
	$left=array_filter(array_keys($GLOBALS['test_options']),static fn($name)=>str_starts_with($name,RedirectStore::PREFIX));
	redirect_expect(array()===array_values($left) && false===get_option(Settings::OPTION),'opt-in uninstall removes snapshot documents, lookup buckets and locks');

	echo "$assertions scalable redirect assertions passed.\n";
} finally {
	RedirectStore::unlock();
	rmdir($GLOBALS['test_uploads']);
}
