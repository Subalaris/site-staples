<?php
/** Regression tests for the 1.1 roadmap modules and legacy migration. */
require __DIR__ . '/bootstrap.php';

use SiteStaples\Settings;
use SiteStaples\Plugin;
use SiteStaples\RedirectRules;
use SiteStaples\Modules\Transfer;
use SiteStaples\Modules\Redirects;
use SiteStaples\Modules\ImageSizes;
use SiteStaples\Modules\Maintenance;
use SiteStaples\Modules\AdminCleanup;
use SiteStaples\Modules\Columns;

$count = 0;
function check_feature( $condition, $label ) {
	global $count;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL ' . $label ); }
	++$count; echo "PASS $label\n";
}
function rule( $from, $to, $status = 302 ) { return array( 'from' => $from, 'to' => $to, 'status' => $status ); }
function form_fixture() {
	$form = array();
	foreach ( Settings::defaults() as $key => $module ) {
		if ( ! is_array( $module ) ) { continue; }
		$form[$key] = array();
		foreach ( $module as $field => $value ) {
			if ( 'rules' === $field ) { $form[$key]['rules_text'] = ''; }
			elseif ( is_bool($value) ) { if ($value) { $form[$key][$field] = '1'; } }
			elseif ( is_int($value) ) { $form[$key][$field] = (string)$value; }
			else { $form[$key][$field] = $value; }
		}
	}
	return $form;
}
class TestToolbar {
	public $removed = array();
	public $added = array();
	public function remove_node( $id ) { $this->removed[] = $id; }
	public function add_node( $node ) { $this->added[$node['id']] = $node; }
}
try {
	Plugin::activate(); Plugin::boot();
	foreach ( array( ImageSizes::class, Redirects::class, Maintenance::class, AdminCleanup::class, Columns::class ) as $class ) {
		check_feature( ! class_exists($class, false), 'disabled module does not load: ' . $class );
	}
	$legacy = Settings::legacy_defaults(); $legacy['comments']['enabled'] = true; $legacy['revisions']['limit'] = 7;
	update_option( Settings::OPTION, $legacy ); $upgraded = Settings::get();
	check_feature( $upgraded['comments']['enabled'] && 7 === $upgraded['revisions']['limit'], 'upgrade preserves existing feature settings' );
	foreach ( array('image_sizes','redirects','maintenance','admin_cleanup','columns') as $key ) { check_feature( false === $upgraded[$key]['enabled'], 'new module defaults disabled on upgrade: ' . $key ); }
	check_feature( $legacy === get_option(Settings::OPTION), 'reading legacy configuration does not write options' );
	$doc = array( 'plugin' => 'site-staples', 'schema_version' => 1, 'settings' => $legacy );
	check_feature( $upgraded === Transfer::parse(wp_json_encode($doc)), 'complete legacy exports upgrade without activating new modules' );
	$bad=$doc; $bad['settings']['maintenance']=Settings::defaults()['maintenance']; check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'version 1 imports cannot smuggle version 2 fields' );
	$bad=$doc; unset($bad['settings']['svg']); check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'partial legacy export rejected' );
	$doc['schema_version']=2; $doc['settings']=$upgraded;
	check_feature( $upgraded === Transfer::parse(wp_json_encode($doc)), 'version 2 configuration round trips' );
	$bad=$doc; $bad['settings']['redirects']['rules']=(object)array(); check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'redirect rules require a JSON array' );
	$bad=$doc; $bad['settings']['columns']['show_id']='true'; check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'column flags require booleans' );
	$bad=$doc; $bad['settings']['maintenance']['message']='<script>alert(1)</script>'; check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'imported maintenance text must be plain text' );
	$bad=$doc; $bad['settings']['maintenance']['title']=str_repeat('a',121); check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'maintenance text length is bounded' );
	$bad=$doc; $bad['settings']['admin_cleanup']['toolbar']=array('my-account'); check_feature( is_wp_error(Transfer::parse(wp_json_encode($bad))), 'cleanup cannot hide the account recovery menu' );

	$valid=array(rule('/old/','/middle',301),rule('/middle/','/new/',302));
	check_feature( ! is_wp_error(RedirectRules::validate_rules($valid)), 'valid redirect chain accepted' );
	$redirects=new Redirects(array('rules'=>$valid));
	check_feature( array('url'=>'https://example.test/new','status'=>301) === $redirects->match('/old/?private=discarded'), 'redirect chains collapse with source status and drop query data' );
	check_feature( array('url'=>'https://example.test/new','status'=>302) === $redirects->match('/middle','HEAD'), 'HEAD requests use configured temporary status' );
	foreach ( array('POST','PUT','DELETE','OPTIONS') as $method ) { check_feature( false === $redirects->match('/old',$method), 'redirect leaves ' . $method . ' unchanged' ); }
	foreach ( array('/old/?rest_route=%2Fwp%2Fv2','/old/?preview=true','/old/?preview_id=1','/old/?customize_changeset_uuid=abc') as $uri ) { check_feature( false === $redirects->match($uri), 'redirect leaves API and preview requests unchanged' ); }
	check_feature( false === $redirects->match('/unknown'), 'unmatched URL left unchanged' );
	foreach ( array('//evil.test/x','https://evil.test/x',"/x\r\nLocation:evil",'/../x','/%2e%2e/x','/x%2Fy','/x%5cy','/%252e/x','/x?bad=1','/x#bad','/x%zz','/a//b','/wp-admin','/wp-login.php','/%77p-admin/x','/wp-json/wp/v2','/anything.php','/wp-content/file') as $path ) {
		check_feature( is_wp_error(RedirectRules::validate_rules(array(rule('/safe',$path)))), 'unsafe or protected redirect destination rejected: ' . json_encode($path) );
	}
	foreach ( array(array(rule('/a','/a/')),array(rule('/a','/A')),array(rule('/a','/a.')),array(rule('/a','/a%21')),array(rule('/a','/B'),rule('/b','/C'),rule('/c','/A')),array(rule('/a','/b'),rule('/a/','/c')),array(rule('/a','/b'),rule('/b','/c'),rule('/c','/a')),array(rule('/a','/b',307)),array(rule('/a','/b','301')),array_fill(0,101,rule('/a','/b'))) as $rules ) { check_feature( is_wp_error(RedirectRules::validate_rules($rules)), 'self, duplicate, cyclic, invalid-status or oversized rule set rejected' ); }
	check_feature( false === (new Redirects(array('rules'=>array(rule('/a','/b'),rule('/b','/a')))))->match('/a'), 'runtime loop guard fails closed for corrupted settings' );
	check_feature( '/old' === RedirectRules::path('/%6Fld/'), 'unreserved encoded paths normalize for loop checks' );
	$GLOBALS['test_home_url']='https://example.test/subsite';
	check_feature( is_wp_error(RedirectRules::validate_rules(array(rule('/outside','/new')))), 'subdirectory site rejects paths outside its prefix' );
	$sub=new Redirects(array('rules'=>array(rule('/subsite/old','/subsite/new'))));
	check_feature( 'https://example.test/subsite/new' === $sub->match('/subsite/old')['url'], 'subdirectory destinations include the prefix once' );
	$saved=Settings::defaults(); $saved['comments']['enabled']=true; $saved['redirects']['rules']=array(rule('/subsite/old','/subsite/new')); update_option(Settings::OPTION,$saved); $GLOBALS['test_home_url']='https://example.test/moved'; check_feature( Settings::get()['comments']['enabled'], 'home URL changes do not reset unrelated saved settings' );
	unset($GLOBALS['test_home_url']);

	$form=form_fixture(); $form['redirects']=array('enabled'=>'1','rules_text'=>"301 /old/ /new/\n302 /gone /replacement"); $form['maintenance']=array('enabled'=>'1','roles'=>array('editor'),'title'=>'A & B','message'=>"Back soon\nThank you"); $form['image_sizes']=array('enabled'=>'1','disabled_sizes'=>array('thumbnail'));
	$clean=Settings::sanitize($form);
	check_feature( 2 === count($clean['redirects']['rules']) && 301 === $clean['redirects']['rules'][0]['status'] && $clean['maintenance']['enabled'], 'browser form saves typed rules and new feature settings' );
	update_option(Settings::OPTION,$clean); $form['redirects']['rules_text']='301 /a /b extra';
	check_feature( $clean === Settings::sanitize($form), 'malformed redirect form preserves saved configuration' );
	$row_form = form_fixture();
	$row_form['redirects'] = array( 'enabled' => '1', 'rows_present' => '1', 'rules_rows' => array(
		3 => array( 'from' => ' /old/ ', 'to' => '/new/', 'status' => '301' ),
		8 => array( 'from' => '/other', 'to' => '/destination', 'status' => '302' ),
		9 => array( 'from' => '', 'to' => '', 'status' => '302' ),
	) );
	$row_clean = Settings::sanitize( $row_form );
	check_feature( array( rule('/old/','/new/',301), rule('/other','/destination',302) ) === $row_clean['redirects']['rules'], 'row editor parses sparse indices, trims paths, skips empty rows and preserves status types' );
	foreach ( array(
		array( array( 'from' => '/partial', 'to' => '', 'status' => '302' ) ),
		array( array( 'from' => '', 'to' => '/partial', 'status' => '302' ) ),
		array( array( 'from' => '/old', 'to' => '/new', 'status' => '307' ) ),
		array( array( 'from' => array('/old'), 'to' => '/new', 'status' => '302' ) ),
		array( array( 'from' => '/old', 'to' => '/new', 'status' => '302', 'extra' => 'bad' ) ),
		array( array( 'from' => '/old', 'to' => 'https://elsewhere.test', 'status' => '302' ) ),
		array( array( 'from' => '/loop', 'to' => '/loop', 'status' => '302' ) ),
		array( array( 'from' => '/same', 'to' => '/new', 'status' => '302' ), array( 'from' => '/same', 'to' => '/other', 'status' => '301' ) ),
		array_fill( 0, RedirectRules::MAX_RULES + 1, array( 'from' => '', 'to' => '', 'status' => '302' ) ),
	) as $invalid_rows ) {
		$invalid_form = $row_form; $invalid_form['redirects']['rules_rows'] = $invalid_rows;
		check_feature( $clean === Settings::sanitize($invalid_form), 'invalid redirect rows preserve saved configuration' );
	}
	$empty_form = $row_form; unset($empty_form['redirects']['rules_rows']);
	check_feature( array() === Settings::sanitize($empty_form)['redirects']['rules'], 'removing every redirect row saves an empty rule list' );
	$unicode=Settings::defaults(); $unicode['maintenance']['title']=str_repeat('é',120); check_feature( ! is_wp_error(Settings::validate($unicode)), 'maintenance limits count Unicode characters rather than bytes' );
	$before_posts=$GLOBALS['test_posts']; $transfer=new Transfer(array()); $state=array('before'=>Settings::get(),'settings'=>$upgraded,'token'=>'confirmed');
	check_feature( true === $transfer->apply_preview($state,'confirmed') && $before_posts===$GLOBALS['test_posts'], 'new-format import changes configuration only' );

	$images=new ImageSizes(array('disabled_sizes'=>array('thumbnail'))); $images->register();
	check_feature( ! isset(wp_get_registered_image_subsizes()['thumbnail']), 'disabled size is absent from native generation registry and client requests' );
	check_feature( isset(Settings::image_sizes()['thumbnail']), 'disabled registered size stays visible in settings for re-enabling' );
	check_feature( array('medium'=>array('width'=>300)) === apply_filters('intermediate_image_sizes_advanced',array('thumbnail'=>array('width'=>150),'medium'=>array('width'=>300))), 'generation filter removes only selected sizes' );
	remove_filter('intermediate_image_sizes',array($images,'names'),99); remove_filter('intermediate_image_sizes_advanced',array($images,'filter'),99);
	check_feature( isset(wp_get_registered_image_subsizes()['thumbnail']), 'disabling module restores native image registry' );

	$maintenance=new Maintenance(array('roles'=>array('editor'),'title'=>'Service <script>unsafe</script>','message'=>"Back & soon\nThanks"));
	check_feature( $maintenance->allowed(), 'administrators always bypass maintenance' );
	$GLOBALS['test_caps']['manage_options']=false; $GLOBALS['test_logged_in']=true; $GLOBALS['test_roles']=array('editor');
	check_feature( $maintenance->allowed(), 'explicitly allowed authenticated role bypasses maintenance' );
	$GLOBALS['test_logged_in']=false; check_feature( ! $maintenance->allowed(), 'unauthenticated visitors cannot use role bypass' );
	$GLOBALS['test_logged_in']=true; $GLOBALS['test_roles']=array('subscriber'); check_feature( ! $maintenance->allowed(), 'unselected logged-in role remains in maintenance' );
	check_feature( ! str_contains($maintenance->html(),'<script>') && str_contains($maintenance->html(),'&lt;script&gt;') && str_contains($maintenance->html(),'&amp;'), 'visitor page escapes all configured text' );
	$rest=$maintenance->rest(null,null,null); check_feature( is_wp_error($rest) && 503===$rest->get_error_data()['status'] && ! empty($GLOBALS['test_nocache']), 'maintenance REST response is unavailable and uncacheable' );
	$GLOBALS['test_is_admin']=false; $caught=false;
	try { $maintenance->display(); } catch(RuntimeException $e) { $caught=503===$e->getCode(); }
	check_feature( $caught, 'maintenance visitor page sends HTTP 503 through WordPress' );
	$GLOBALS['test_caps']['manage_options']=true; check_feature( null===$maintenance->display() && 'existing'===$maintenance->rest('existing',null,null), 'administrative bypass preserves normal page and REST results' );
	$bar=new TestToolbar(); $maintenance->toolbar($bar); check_feature( isset($bar->added['site-staples-maintenance']), 'administrator sees maintenance recovery link' );
	$maintenance->register(); $redirects->register(); check_feature( 0===has_action('template_redirect',array($maintenance,'display')) && 5===has_action('template_redirect',array($redirects,'redirect')), 'maintenance runs before redirects' );
	$GLOBALS['test_caps']['manage_options']=false; $GLOBALS['test_roles']=array('subscriber'); unset($GLOBALS['test_redirect']); $caught=false; try { do_action('template_redirect'); } catch(RuntimeException $e) { $caught=503===$e->getCode(); } check_feature( $caught && !isset($GLOBALS['test_redirect']), 'maintenance stops frontend dispatch before a redirect can run' ); $GLOBALS['test_caps']['manage_options']=true;
	$GLOBALS['test_is_admin']=true;

	$cleanup=new AdminCleanup(array('roles'=>array('editor'),'widgets'=>array('dashboard_primary'),'toolbar'=>array('comments','new-content')));
	$GLOBALS['test_roles']=array('subscriber'); $bar=new TestToolbar(); $cleanup->toolbar($bar); check_feature( array()===$bar->removed, 'unselected role keeps toolbar' );
	$GLOBALS['test_roles']=array('subscriber','editor'); $caps=$GLOBALS['test_caps']; $cleanup->toolbar($bar); $cleanup->dashboard();
	check_feature( array('comments','new-content')===$bar->removed && 4===count($GLOBALS['test_removed_boxes']), 'selected role removes only chosen toolbar nodes and dashboard widget contexts' );
	check_feature( $caps===$GLOBALS['test_caps'], 'admin cleanup never changes capabilities' );

	$columns=new Columns(array('post_types'=>array('post','page'),'show_id'=>true,'show_thumbnail'=>true,'show_modified'=>true)); $columns->types();
	check_feature( has_filter('manage_post_posts_columns') && has_filter('manage_page_posts_columns') && ! has_filter('manage_book_posts_columns'), 'columns register only for explicitly selected types' );
	$cols=$columns->columns(array('title'=>'Title')); check_feature( isset($cols['title'],$cols['site_staples_id'],$cols['site_staples_thumbnail'],$cols['site_staples_modified']), 'columns preserve original list and add selected data' );
	ob_start(); $columns->cell('site_staples_id',42); $out=ob_get_clean(); check_feature('42'===$out,'ID cell uses the content ID');
	ob_start(); $columns->cell('site_staples_thumbnail',42); $out=ob_get_clean(); check_feature(str_contains($out,'No featured image'),'empty thumbnail state is explained');
	$GLOBALS['test_thumbnail']=true; ob_start(); $columns->cell('site_staples_thumbnail',42); $out=ob_get_clean(); check_feature(str_contains($out,'width="50"'),'thumbnail uses native image markup');
	$GLOBALS['test_caps']['read_post']=false; ob_start(); $columns->cell('site_staples_id',42); $out=ob_get_clean(); check_feature(''===$out,'column output respects source read permissions');
	$none=new Columns(array('post_types'=>array('post'),'show_id'=>false,'show_thumbnail'=>false,'show_modified'=>false)); check_feature(array('title'=>'Title')===$none->columns(array('title'=>'Title')),'unselected column options leave list unchanged');
	echo "$count roadmap feature assertions passed.\n";
} finally {
	rmdir($GLOBALS['test_uploads']);
}
