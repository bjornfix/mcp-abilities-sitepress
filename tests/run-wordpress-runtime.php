<?php
/** Native WordPress storage/capability checks; WPML hooks are explicit fixtures. */
if (!defined('ABSPATH') || !function_exists('wp_get_ability')) { throw new RuntimeException('WordPress required.'); }
if (!defined('ICL_SITEPRESS_VERSION') || 'contract-fixture' !== ICL_SITEPRESS_VERSION) { throw new RuntimeException('Load the test flag before WordPress using --require.'); }
$sitepress = new class {
 public function get_active_languages() { return array('en'=>array('code'=>'en','default_locale'=>'en_GB'), 'fr'=>array('code'=>'fr','default_locale'=>'fr_FR')); }
};
$GLOBALS['sitepress'] = $sitepress;
$fixture_details = array();
$persist_links = true;
add_filter('wpml_element_type', static function($type) { return 'post_' . $type; });
add_filter('wpml_default_language', static function() { return 'en'; });
add_filter('wpml_current_language', static function() { return 'en'; });
add_filter('wpml_element_language_details', static function($value,$args) use (&$fixture_details) {
 if (str_starts_with($args['element_type'],'post_')) { throw new RuntimeException('Expected raw type for language details.'); }
 return $fixture_details[(int)$args['element_id']] ?? null;
},10,2);
add_filter('wpml_get_element_translations', static function($value,$trid,$type) use (&$fixture_details) {
 $result=array();
 foreach ($fixture_details as $id=>$details) { if ((int)$details->trid===(int)$trid) { $result[$details->language_code]=(object)array('element_id'=>$id,'language_code'=>$details->language_code,'original'=>empty($details->source_language_code)); } }
 return $result;
},10,3);
add_filter('wpml_object_id', static function($id,$type,$fallback,$lang) use (&$fixture_details) {
 $trid=$fixture_details[$id]->trid??0;
 foreach ($fixture_details as $candidate=>$details) { if ($trid && $details->trid===$trid && $details->language_code===$lang) { return $candidate; } }
 return $fallback?$id:0;
},10,4);
add_action('wpml_set_element_language_details', static function($args) use (&$fixture_details,&$persist_links) {
 if (!$persist_links) { return; }
 $fixture_details[$args['element_id']]=(object)array('trid'=>$args['trid']?:$args['element_id'],'language_code'=>$args['language_code'],'source_language_code'=>$args['source_language_code']);
});
$user=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'));
if (!$user) { throw new RuntimeException('Administrator required.'); }
wp_set_current_user((int)$user[0]);
$ids=array();
$check=static function($ok,$message) { if (!$ok) { throw new RuntimeException($message); } };
$create=static function($args) use (&$ids) {
 $id=wp_insert_post(wp_slash($args),true);
 if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
 $ids[]=(int)$id; return (int)$id;
};
$run=static function($name,$input) {
 $ability=wp_get_ability($name);
 if (!$ability) { throw new RuntimeException('Missing '.$name); }
 $result=$ability->execute($input);
 if (is_wp_error($result)) { throw new RuntimeException($name.': '.$result->get_error_message()); }
 return $result;
};
try {
 $source=$create(array('post_type'=>'page','post_status'=>'draft','post_title'=>'SitePress fixture C:\\guides','post_content'=>'<p>Fixture C:\\guides\\entry</p>'));
 $fixture_details[$source]=(object)array('trid'=>$source,'language_code'=>'en','source_language_code'=>null);
 $r=$run('wpml/list-active-languages',array());
 $check($r['success'] && 2===$r['total'],'Configured languages unavailable outside frontend.');
 $input=array('source_id'=>$source,'target_lang'=>'fr','copy_elementor'=>false,'copy_featured_image'=>false,'copy_taxonomies'=>false,'copy_selected_meta'=>false);
 $r=$run('wpml/ensure-post-translation',$input);
 if (!empty($r['target_id'])) { $ids[]=(int)$r['target_id']; }
 $check($r['success'] && $r['created'],'Native shell creation failed.');
 $target=(int)$r['target_id'];
 $check(get_post($target)->post_content===get_post($source)->post_content,'Shell changed content backslashes.');
 $again=$run('wpml/ensure-page-translation',array('source_id'=>$source,'target_lang'=>'fr'));
 $check($again['success'] && !$again['created'] && $again['target_id']===$target,'Existing translation was not reused.');
 $r=$run('wpml/link-post-translation',array('source_id'=>$source,'target_id'=>$target,'target_lang'=>'fr'));
 $check($r['success'],'Existing link was not idempotent.');
 $other=$create(array('post_type'=>'page','post_status'=>'draft','post_title'=>'Other fixture'));
 $r=$run('wpml/link-post-translation',array('source_id'=>$source,'target_id'=>$other,'target_lang'=>'fr'));
 $check(!$r['success'] && !isset($fixture_details[$other]),'Occupied translation language overwritten.');
 $deny=static function($caps,$cap,$uid,$args) use ($source) { return in_array($cap,array('edit_post','read_post'),true) && (int)($args[0]??0)===$source?array('do_not_allow'):$caps; };
 add_filter('map_meta_cap',$deny,999,4);
 try {
  foreach (array('wpml/get-element-language-details','wpml/get-post-translations') as $name) { $check(!$run($name,array('id'=>$source))['success'],'Inaccessible source disclosed by '.$name); }
  $check(!$run('wpml/ensure-post-translation',$input)['success'],'Inaccessible source copied.');
 } finally { remove_filter('map_meta_cap',$deny,999); }
 $fixture_details[$other]=(object)array('trid'=>$other,'language_code'=>'en','source_language_code'=>null);
 $persist_links=false;
 $r=$run('wpml/ensure-post-translation',array_merge($input,array('source_id'=>$other,'target_status'=>'publish')));
 if (!empty($r['target_id'])) { $ids[]=(int)$r['target_id']; }
 $check(!$r['success'] && 'draft'===get_post_status($r['target_id']),'Unlinked shell was published.');
 $occupied=$create(array('post_type'=>'post','post_status'=>'publish','post_title'=>'SitePress occupied fixture','post_name'=>'sitepress-native-slug-fixture'));
 $renamed=$create(array('post_type'=>'post','post_status'=>'publish','post_title'=>'SitePress rename fixture','post_name'=>'sitepress-native-previous-fixture'));
 $before_slug=get_post($renamed)->post_name;
 $expected_slug=wp_unique_post_slug(get_post($occupied)->post_name,$renamed,get_post_status($renamed),'post',0);
 $fixture_details[$renamed]=(object)array('trid'=>$renamed,'language_code'=>'fr','source_language_code'=>null);
 $r=$run('wpml/update-translated-post-url',array('id'=>$renamed,'target_lang'=>'fr','slug'=>get_post($occupied)->post_name));
 $check($r['success'] && $r['after_slug']===$expected_slug,'Native slug uniqueness was bypassed.');
 if ('publish'===get_post_status($renamed) && $before_slug!==$expected_slug) { $check(in_array($before_slug,get_post_meta($renamed,'_wp_old_slug',false),true),'Old slug history removed.'); }
 echo "PASS: native WordPress abilities, permissions, draft creation, content bytes and native slug policy; WPML integration uses fixture hooks\n";
} finally {
 foreach (array_unique($ids) as $id) { wp_delete_post($id,true); }
}
