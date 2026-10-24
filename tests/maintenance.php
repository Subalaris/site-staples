<?php
/** Maintenance page selection, privacy boundaries, old settings and template-pipeline regressions. */
require __DIR__ . '/bootstrap.php';

use SiteStaples\Settings;
use SiteStaples\Modules\Maintenance;
use SiteStaples\Modules\Redirects;
use SiteStaples\Modules\Transfer;

$checks = 0;
function maintenance_expect( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks; echo "PASS $message\n";
}
function maintenance_fixture( $id, $status = 'publish', $password = '', $type = 'page' ) {
	return $GLOBALS['test_posts'][$id] = (object)array('ID'=>$id,'post_type'=>$type,'post_status'=>$status,'post_password'=>$password,'post_title'=>'Coming & soon','post_content'=>'<p>A custom maintenance page.</p>');
}
// Only query execution is doubled; WordPress hook dispatch and production page preparation run unchanged.
class WP_Query {
	public $posts = array();
	public $query_vars = array();
	public $is_page = false;
	public $is_404 = true;
	public function query( $args ) {
		$this->query_vars=$args;
		$post=get_post($args['page_id']);
		$this->posts=$post?array($post):array();
		if (!empty($GLOBALS['test_query_wrong_post'])) $this->posts=array(get_post(324));
		$this->is_page=true; $this->is_404=false;
		return $this->posts;
	}
}
function setup_postdata( $post ) { $GLOBALS['test_setup_post']=$post->ID; }
function redirect_canonical() {}
function wp_old_slug_redirect() {}
function have_posts() { return ($GLOBALS['test_loop_index']??0)<count($GLOBALS['wp_query']->posts); }
function the_post() { $GLOBALS['post']=$GLOBALS['wp_query']->posts[$GLOBALS['test_loop_index']??0]; $GLOBALS['test_loop_index']=($GLOBALS['test_loop_index']??0)+1; }
function the_content() { echo apply_filters('the_content',$GLOBALS['post']->post_content); }
function language_attributes() { echo 'lang="en"'; }
function bloginfo( $key ) { echo 'UTF-8'; }
function body_class( $classes ) { echo 'class="'.htmlspecialchars(implode(' ',$classes),ENT_QUOTES).'"'; }
function wp_head() { echo '<style id="maintenance-assets">.page-design{color:purple}</style>'; $GLOBALS['test_content_before_head']=!empty($GLOBALS['test_content_assets']); }
function wp_body_open() { echo '<!-- body-open hook -->'; }
function wp_footer() { echo '<script id="maintenance-script">window.pageScriptLoaded=true;</script>'; }
function get_header() { throw new RuntimeException('Theme header must not be loaded'); }
function get_footer() { throw new RuntimeException('Theme footer must not be loaded'); }

try {
	maintenance_fixture(321);
	maintenance_fixture(322,'draft');
	maintenance_fixture(323,'publish','secret');
	maintenance_fixture(324,'publish','','post');
	maintenance_fixture(325,'private');
	maintenance_fixture(326,'trash');
	maintenance_fixture(327,'publish','0');
	maintenance_expect(0===Settings::defaults()['maintenance']['page_id'],'custom maintenance pages are opt-in');
	$old=Settings::v2_defaults(); $old['maintenance']['enabled']=true; $old['maintenance']['title']='Existing title'; $old['comments']['enabled']=true;
	update_option(Settings::OPTION,$old,false);
	$upgraded=Settings::get();
	maintenance_expect($upgraded['maintenance']['enabled'] && $upgraded['maintenance']['title']==='Existing title' && $upgraded['maintenance']['page_id']===0 && $upgraded['comments']['enabled'],'schema 2 settings upgrade without resetting maintenance or unrelated features');
	maintenance_expect(get_option(Settings::OPTION)===$old,'reading an older configuration never rewrites it');
	$doc=array('plugin'=>'site-staples','schema_version'=>2,'settings'=>$old);
	maintenance_expect($upgraded===Transfer::parse(wp_json_encode($doc)),'old schema 2 exports import with the default maintenance message');
	$bad=$doc; $bad['settings']['maintenance']['page_id']=321;
	maintenance_expect(is_wp_error(Transfer::parse(wp_json_encode($bad))),'schema 2 documents cannot smuggle in the new schema 3 field');
	$doc['schema_version']=3; $doc['settings']=$upgraded; $doc['settings']['maintenance']['page_id']=321;
	maintenance_expect($doc['settings']===Transfer::parse(wp_json_encode($doc)),'schema 3 custom-page configurations round trip');
	foreach (array('321',true,-1,1.5,array(),999,322,323,324,325,326,327) as $id) {
		$bad=$doc; $bad['settings']['maintenance']['page_id']=$id;
		maintenance_expect(is_wp_error(Transfer::parse(wp_json_encode($bad))),'invalid, private or protected imported maintenance selection rejected: '.json_encode($id));
	}
	$bad=$doc; unset($bad['settings']['maintenance']['page_id']);
	maintenance_expect(is_wp_error(Transfer::parse(wp_json_encode($bad))),'schema 3 requires its documented maintenance field');
	$form=array('maintenance'=>array('enabled'=>'1','page_id'=>'321','title'=>'Fallback','message'=>'Try again soon'),'revisions'=>array('limit'=>'10'));
	$clean=Settings::sanitize($form);
	maintenance_expect(321===$clean['maintenance']['page_id'] && 'Fallback'===$clean['maintenance']['title'],'native settings forms parse the selected page and retain fallback copy');
	maintenance_expect(true===Settings::save($clean) && 321===Settings::get()['maintenance']['page_id'],'custom-page settings persist through snapshot storage');
	maintenance_expect(3===Transfer::document()['schema_version'] && 321===Transfer::document()['settings']['maintenance']['page_id'],'exports use schema 3 and include the page ID');

	$module=new Maintenance($clean['maintenance']);
	$GLOBALS['test_caps']['manage_options']=true;
	$GLOBALS['test_show_admin_bar']=true;
	maintenance_expect(null===$module->display() && empty($GLOBALS['test_setup_post']),'administrators bypass the custom page before any query is replaced');
	maintenance_expect(apply_filters('template_include','/theme/normal.php')==='/theme/normal.php' && true===$GLOBALS['test_show_admin_bar'],'administrator bypass retains the normal template and toolbar');
	$GLOBALS['test_caps']['manage_options']=false;
	$GLOBALS['test_is_admin']=false;
	$GLOBALS['test_logged_in']=false;
	$_SERVER['REQUEST_URI']='/a-page-that-does-not-exist/?campaign=example';
	add_action('template_redirect','redirect_canonical'); add_action('template_redirect','wp_old_slug_redirect');
	$GLOBALS['wp_query']=(object)array('original'=>true); $GLOBALS['wp_the_query']=$GLOBALS['wp_query']; $GLOBALS['post']=null;
	$original=$GLOBALS['wp_query']; $GLOBALS['test_query_wrong_post']=true;
	maintenance_expect(false===$module->prepare_page() && $GLOBALS['wp_query']===$original && $GLOBALS['wp_the_query']===$original,'unexpected query results restore the previous globals and fall back safely');
	unset($GLOBALS['test_query_wrong_post']);
	maintenance_expect(null===$module->display() && $GLOBALS['test_status']===503,'custom maintenance display keeps the HTTP 503 response');
	maintenance_expect($GLOBALS['wp_query'] instanceof WP_Query && $GLOBALS['wp_query']===$GLOBALS['wp_the_query'] && $GLOBALS['post']->ID===321 && $GLOBALS['test_setup_post']===321,'custom display sets the selected page as the main WordPress content context');
	maintenance_expect($GLOBALS['wp_query']->is_page && !$GLOBALS['wp_query']->is_404 && $GLOBALS['wp_query']->query_vars['post_status']==='publish','even missing requested URLs use a published-page query rather than a 404 template');
	maintenance_expect($_SERVER['REQUEST_URI']==='/a-page-that-does-not-exist/?campaign=example','custom maintenance rendering preserves the visitor URL');
	maintenance_expect(false===has_action('template_redirect','redirect_canonical') && false===has_action('template_redirect','wp_old_slug_redirect'),'canonical and old-slug redirects cannot redirect away from maintenance');
	maintenance_expect(did_action('site_staples_maintenance_page')>0,'the custom maintenance response is marked for other Site Staples modules');
	$redirects=new Redirects(array('rules'=>array(array('from'=>'/a-page-that-does-not-exist','to'=>'/redirected','status'=>302))));
	unset($GLOBALS['test_redirect']); $redirects->redirect();
	maintenance_expect(!isset($GLOBALS['test_redirect']),'Simple redirects never overrides the selected maintenance page');
	$GLOBALS['test_status']=200;
	$canvas=apply_filters('template_include','/theme/custom-page-template.php');
	maintenance_expect(is_file($canvas) && str_ends_with($canvas,'/includes/templates/maintenance-page.php') && $GLOBALS['test_status']===503,'maintenance uses its blank canvas while preserving HTTP 503');
	maintenance_expect($module->page_template('')===$canvas,'maintenance canvas does not require a working theme page template');
	maintenance_expect(false===$GLOBALS['test_show_admin_bar'],'blocked visitors do not receive WordPress toolbar navigation');
	$GLOBALS['post']->post_content='<section class="page-design"><header>Coming soon</header><p>Custom page content</p><footer>Contact our team</footer></section>';
	add_filter('the_content',static function($content){$GLOBALS['test_content_assets']=true;return $content.'<span>Filtered page content</span>';});
	$GLOBALS['test_loop_index']=0;
	ob_start(); include $canvas; $canvas_html=ob_get_clean();
	maintenance_expect(str_contains($canvas_html,'Custom page content') && str_contains($canvas_html,'Filtered page content'),'canvas renders selected content through WordPress content filters');
	maintenance_expect(str_contains($canvas_html,'<header>Coming soon</header>') && str_contains($canvas_html,'<footer>Contact our team</footer>'),'intentional page content is preserved rather than hiding every header and footer element with CSS');
	maintenance_expect(str_contains($canvas_html,'maintenance-assets') && str_contains($canvas_html,'maintenance-script') && str_contains($canvas_html,'body-open hook'),'WordPress head, body and footer asset hooks still run');
	maintenance_expect($GLOBALS['test_content_before_head'],'blocks and builder content can enqueue assets before wp_head renders');

	foreach(array(322,323,324,325,326,327,999) as $id){
		$settings=$clean['maintenance']; $settings['page_id']=$id;
		$fallback=new Maintenance($settings); $caught=false;
		try{$fallback->display();}catch(RuntimeException $e){$caught=$e->getCode()===503 && str_contains($e->getMessage(),'Fallback');}
		maintenance_expect($caught,'unavailable or protected selected pages use the default message: '.$id);
	}
	$GLOBALS['test_posts'][321]->post_status='draft';
	maintenance_expect(321===Settings::get()['maintenance']['page_id'] && Settings::get()['maintenance']['enabled'],'a later unpublished page does not silently disable maintenance or erase its configuration');
	$GLOBALS['test_posts'][321]->post_status='publish';
	$rest=$module->rest(null,null,null);
	maintenance_expect(is_wp_error($rest) && $rest->get_error_data()['status']===503,'custom HTML pages do not open REST access to visitors');
	$GLOBALS['test_logged_in']=true; $GLOBALS['test_roles']=array('editor'); $settings=$clean['maintenance']; $settings['roles']=array('editor');
	remove_filter('template_include',array($module,'page_template'),PHP_INT_MAX);
	$GLOBALS['test_show_admin_bar']=true;
	maintenance_expect(null===(new Maintenance($settings))->display(),'allowed roles still bypass custom maintenance rendering');
	maintenance_expect(apply_filters('template_include','/theme/normal.php')==='/theme/normal.php' && true===$GLOBALS['test_show_admin_bar'],'allowed roles keep normal theme navigation and toolbar');
	$GLOBALS['test_caps']['manage_options']=true;
	$p=proc_open(array(PHP_BINARY,__DIR__.'/render-admin.php','--enabled','--maintenance-pages'),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
	$html=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
	maintenance_expect(0===proc_close($p) && ''===$err,'custom-page selector renders without warnings');
	$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();$xpath=new DOMXPath($dom);
	maintenance_expect(1===$xpath->query('//*[@id="staples-maintenance-page"]/option[@value="321" and @selected]')->length,'the native selector restores the selected published page');
	maintenance_expect(0===$xpath->query('//*[@id="staples-maintenance-page"]/option[@value="322" or @value="323" or @value="324"]')->length,'private and password-protected pages never appear in the selector');
	$default=$clean; $default['maintenance']['page_id']=0;
	maintenance_expect(true===Settings::save($default) && 0===Settings::get()['maintenance']['page_id'] && null!==get_post(321),'removing the selection persists the default option without deleting the page');
	$GLOBALS['test_caps']['manage_options']=false; $GLOBALS['test_logged_in']=false;
	$caught=false; try{(new Maintenance(Settings::get()['maintenance']))->display();}catch(RuntimeException $e){$caught=$e->getCode()===503 && str_contains($e->getMessage(),'Fallback');}
	maintenance_expect($caught,'clearing the selected page restores the default maintenance message');
	echo "$checks maintenance-page assertions passed.\n";
}finally{rmdir($GLOBALS['test_uploads']);}
