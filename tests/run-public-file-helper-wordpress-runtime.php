<?php
/** Read-only native WordPress i18n check;run with only this candidate loaded. */
if(!defined('ABSPATH')||!function_exists('mcp_wpml_public_file_helper_language')){throw new RuntimeException('Active candidate required');}
if(is_admin()){throw new RuntimeException('Public gettext context required');}
$source='Maximum file size: %s';$checks=[];$original=determine_locale();
foreach(['nb_NO'=>'Maksimal filstørrelse: %s','en_US'=>$source] as $locale=>$expected){
 $switched=switch_to_locale($locale);
 if(!$switched&&determine_locale()!==$locale){throw new RuntimeException('Required locale is not already installed: '.$locale);}
 try{
  $checks[$locale.' standard catalog']=mcp_wpml_public_file_helper_language($source,$source,'formidable-pro')===$expected;
  $checks[$locale.' existing translation']=mcp_wpml_public_file_helper_language('Existing native translation',$source,'formidable-pro')==='Existing native translation';
  $checks[$locale.' other domain']=mcp_wpml_public_file_helper_language($source,$source,'unrelated-domain')===$source;
 }finally{if($switched){restore_previous_locale();}}
}
$checks['Caller locale restored']=determine_locale()===$original;$checks['Native gettext hook registered']=false!==has_filter('gettext','mcp_wpml_public_file_helper_language');$failed=array_keys(array_filter($checks,static fn($ok)=>!$ok));echo wp_json_encode(['passed'=>!$failed,'checks'=>$checks,'failed'=>$failed,'persistent_writes'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;if($failed){throw new RuntimeException('Native i18n regression failed');}
