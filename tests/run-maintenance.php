<?php
/** Fixed-target Interface exercised with upstream Unnotifier Options, handler and renderer. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/'); define('ICL_SITEPRESS_VERSION','5.1.0'); define('UNNO_VERSION','1.2.10'); define('UNNO_PLUGIN_URL','https://invalid.example/unnotifier/'); define('DAY_IN_SECONDS',86400);
$source=getenv('MCP_UNNOTIFIER_SOURCE_DIR'); if (!$source || !is_file($source.'/src/Data/Options.php')) {throw new RuntimeException('Provide unchanged upstream Unnotifier source.');}
spl_autoload_register(static function($class) use($source) {if(str_starts_with($class,'UNNO\\')) {require_once $source.'/src/'.str_replace('\\','/',substr($class,5)).'.php';}});
$options=$transients=$hooks=$abilities=array(); $admin=true; $fail_option=''; $fail_count=0; $writes=0; $renderer_calls=0;
function get_option($key,$default=false) {return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value) {if($key===$GLOBALS['fail_option'] && ++$GLOBALS['fail_count']===1){return false;} if(($GLOBALS['options'][$key]??null)===$value){return false;} $GLOBALS['options'][$key]=$value; ++$GLOBALS['writes']; return true;}
function get_transient($key) {return $GLOBALS['transients'][$key]??false;}
function set_transient($key,$value,$expiry) {$GLOBALS['transients'][$key]=$value;return true;}
function current_user_can($cap) {return $GLOBALS['admin'];} function is_admin(){return true;}
function add_action($name,$callback,$priority=10,$args=1) {$GLOBALS['hooks'][$name][$priority][]=$callback;}
function has_action($name,$callback) {foreach($GLOBALS['hooks'][$name]??array() as $priority=>$callbacks){if(in_array($callback,$callbacks,true)){return $priority;}}return false;}
function do_action($name,...$args) {$hooks=$GLOBALS['hooks'][$name]??array();ksort($hooks);foreach($hooks as $callbacks){foreach($callbacks as $cb){$cb(...$args);}}}
function wp_register_ability($name,$args) {$GLOBALS['abilities'][$name]=$args;}
function wp_json_encode($v) {return json_encode($v);} function wp_strip_all_tags($v) {return strip_tags($v);} function sanitize_text_field($v) {return trim(strip_tags($v));} function absint($v){return abs((int)$v);}
function wp_enqueue_script(...$a){} function wp_enqueue_style(...$a){} function wp_localize_script(...$a){} function wp_create_nonce($a){return 'SECRET_NONCE';} function admin_url($p=''){return 'https://invalid.example/'.$p;}
function __($v,...$a){return $v;} function esc_attr__($v,...$a){return $v;} function esc_html__($v,...$a){return $v;} function esc_attr($v){return htmlspecialchars($v);} function esc_html($v){return htmlspecialchars($v);} function esc_url($v){return $v;}
class WP_Upgrader {public static $locked=false;public static function create_lock(...$args){if(self::$locked){return false;}return self::$locked=true;}public static function release_lock(...$args){self::$locked=false;}}
require dirname(__DIR__).'/includes/administration-abilities.php'; require dirname(__DIR__).'/includes/maintenance-abilities.php'; mcp_wpml_register_maintenance_abilities();
$facade_class=new ReflectionClass('UNNO\\Core\\Notices'); $facade=$facade_class->newInstanceWithoutConstructor(); $renderer=new UNNO\Core\NoticeRenderer();
$facade_class->getProperty('notice_renderer')->setValue($facade,$renderer);$facade_class->getProperty('instance')->setValue(null,$facade);
add_action('unno_print_notices',array($renderer,'print_notices'),10,1);
$native=UNNO\Data\Options::instance();
$checks=0;function check($ok,$message){++$GLOBALS['checks'];if(!$ok){throw new RuntimeException($message);}}
function call_ability($name,$input=array()) {$a=$GLOBALS['abilities']['wpml/'.$name];$r=($a['execute_callback'])($input);check(!str_contains(json_encode($r),'SECRET'),'Private native data escaped');return $r;}
function reset_case(){ $GLOBALS['options']=array('unno_settings'=>array('mode'=>'individual','unknown'=>'preserved','global_hidden_notices'=>array('11111111_22222222'=>array('excerpt'=>'unrelated','unknown'=>'preserved'))));$GLOBALS['transients']=array();$GLOBALS['fail_option']='';$GLOBALS['fail_count']=0;$GLOBALS['mcp_wpml_pricing_render_pending']=null;}
function policy($hidden){$r=call_ability('set-pricing-notice-hidden',array('hidden'=>$hidden));return call_ability('set-pricing-notice-hidden',array('hidden'=>$hidden,'confirm'=>true,'expected_state_hash'=>$r['before']['state_hash']));}
function render($target,$extras=array()) {$GLOBALS['unno_captured_notices_by_hook']=array('admin_notices'=>array_merge(array($target),$extras));ob_start();do_action('unno_print_notices','admin_notices');$out=ob_get_clean();return $out;}
$target=array('id'=>'abcdef01_1234abcd','source_plugin'=>'WPML Multilingual CMS','content'=>'<div class="notice notice-info"><h2>Your automatic translation pricing has been upgraded</h2><p>Finish the recommended setup from the Translations Dashboard to use the new pricing.</p><a href="/wp-admin/admin.php?page=tm%2Fmenu%2Fmain.php">Show me how</a></div>');
$other=array('id'=>'9876abcd_9876abcd','source_plugin'=>'WPML Multilingual CMS','content'=>'<div class="notice notice-error">License error SECRET_ERROR</div>');
reset_case();$before=$writes;$preview=call_ability('set-pricing-notice-hidden',array('hidden'=>true));check($preview['preview']&&$writes===$before,'Preview changed options');
check('stale_pricing_policy'===call_ability('set-pricing-notice-hidden',array('hidden'=>true,'confirm'=>true,'expected_state_hash'=>str_repeat('0',64)))['code'],'Stale preview applied');
$r=policy(true);check($r['success']&&$r['visibility_verification_pending'],'Policy falsely claimed observed hide');
$before=$writes;$r=policy(true);check(!$r['applied']&&$writes===$before,'Policy repeat wrote state');
$out=render($target,array($other));$r=call_ability('get-pricing-notice-state');
check(!str_contains($out,'Your automatic translation pricing')&&str_contains($out,'SECRET_ERROR'),'Exact target or other error visibility incorrect');
check($r['visibility_verified']&&$r['last_native_render']['render_pipeline_completed']&&1===$r['native_hidden_managed_count'],'Native render verification missing');
check(1===$r['last_native_render']['matched_before']&&0===$r['last_native_render']['matched_after']&&$r['last_native_render']['other_records_preserved'],'Before/after proof incorrect');
check('preserved'===get_option('unno_settings')['unknown']&&isset(get_option('unno_settings')['global_hidden_notices']['11111111_22222222']),'Unknown native settings or another preference lost');
$handler=new UNNO\Core\NoticeHandler($native);check($handler->is_notice_hidden($target['id'],array(),$native->get_global_hidden_notices()),'Native handler did not recognize stored preference');
$r=policy(false);check($r['success']&&0===$r['after']['native_hidden_managed_count']&&1===count($native->get_global_hidden_notices()),'Restore changed another preference');
$out=render($target,array($other));check(str_contains($out,'Your automatic translation pricing'),'Restored target stayed hidden');
foreach(array('<p>Security vulnerability</p>','<p>Plugin update available</p>','<p>License expired</p>','<script>SECRET</script>') as $suffix){reset_case();policy(true);$mixed=$target;$mixed['content'].=$suffix;$out=render($mixed,array($other));check(str_contains($out,'Your automatic translation pricing')&&1===count($native->get_global_hidden_notices()),'Mixed/critical notice group hidden');}
reset_case();policy(true);$wrong=$target;$wrong['source_plugin']='Other plugin';$out=render($wrong);check(str_contains($out,'Your automatic translation pricing')&&1===count($native->get_global_hidden_notices()),'Different source was hidden');
reset_case();policy(true);render($target);$native->remove_global_hidden_notice($target['id']);$out=render($target);$r=call_ability('get-pricing-notice-state');check(!$r['hidden_requested']&&str_contains($out,'Your automatic translation pricing'),'Human native restore was overridden');
reset_case();policy(true);$fail_option='mcp_wpml_pricing_notice_policy';$fail_count=0;$out=render($target);check(str_contains($out,'Your automatic translation pricing')&&!isset($native->get_global_hidden_notices()[$target['id']]),'Failed ownership tracking orphaned native hide');
$fail_option='';$out=render($target);check(!str_contains($out,'Your automatic translation pricing')&&call_ability('get-pricing-notice-state')['visibility_verified'],'Retry did not recover pending policy');
reset_case();policy(true);$fail_option='unno_settings';$fail_count=0;$out=render($target);check(str_contains($out,'Your automatic translation pricing')&&!call_ability('get-pricing-notice-state')['visibility_verified'],'Failed native save reported success');
$fail_option='';render($target);check(call_ability('get-pricing-notice-state')['visibility_verified'],'Pending native save treated as human restore');
$admin=false;foreach($abilities as $a){check(!$a['permission_callback'](),'Missing administrator boundary');}check('permission_denied'===call_ability('get-pricing-notice-state')['code'],'Direct handler bypassed permission');$admin=true;
foreach($abilities as $a){check(false===$a['input_schema']['additionalProperties'],'Arbitrary inputs accepted');}
check(array('hidden','confirm','expected_state_hash')===array_keys($abilities['wpml/set-pricing-notice-hidden']['input_schema']['properties']),'Arbitrary notice/callback input introduced');
// Provider data projection must never return raw repository/account data.
class FixtureInstaller {public function get_repositories(){return array('wpml'=>array('channel'=>'stable','site_key'=>'SECRET_KEY','products'=>array('sitepress-multilingual-cms'=>array('version'=>'5.2.0','download'=>'SECRET_DOWNLOAD'))));}public function get_repository($name){return array();}}
function WP_Installer(){return $GLOBALS['fixture_installer']??new FixtureInstaller();}function WP_Installer_Channels(){return $GLOBALS['fixture_channels']??null;}function wp_remote_get(...$args){throw new RuntimeException('No external provider call allowed in cached status test');}function wp_update_plugins(){throw new RuntimeException('No update refresh allowed in cached status test');}function get_site_transient($name){return (object)array('response'=>array());}
$r=call_ability('get-update-channel-status');check('5.2.0'===$r['native_feed_core_version']&&'stable'===$r['channel']&&'not_established'===$r['provider_health'],'Commercial feed projection wrong');
$installer_source=getenv('MCP_WPML_INSTALLER_DIR');
if ($installer_source) {
 define('MONTH_IN_SECONDS',2592000);
 require $installer_source.'/class-wp-installer.php';require $installer_source.'/class-wp-installer-channels.php';require $installer_source.'/settings-native.php';require $installer_source.'/subscription-native.php';
 $options['wp_installer_settings_common']=array('repositories'=>array('wpml'=>array('channel'=>'production','using_products_fallback'=>true,'subscription'=>array('key'=>'SECRET_KEY','site_url'=>'SECRET_URL','data'=>(object)array('status'=>4)))));
 $native_class=new ReflectionClass('WP_Installer');$fixture_installer=$native_class->newInstanceWithoutConstructor();
 $native_class->getProperty('repositories')->setValue($fixture_installer,array('wpml'=>array('api-url'=>'https://api.wpml.org')));
 $native_class->getProperty('settings')->setValue($fixture_installer,array('repositories'=>array('wpml'=>array('data'=>array('downloads'=>array('plugins'=>array('wpml'=>array('slug'=>'sitepress-multilingual-cms','version'=>'5.2.0','url'=>'SECRET_DOWNLOAD'))))))));
 $fixture_channels=(new ReflectionClass('WP_Installer_Channels'))->newInstanceWithoutConstructor();
 $before=$writes;$r=call_ability('get-update-channel-status',array('refresh'=>false));
 check('5.2.0'===$r['native_feed_core_version']&&'production'===$r['channel']&&$r['native_cached_feed_inspected'],'Actual native product/channel getters not used');
 check(true===$r['native_using_fallback_feed']&&true===$r['cached_subscription_valid'],'Actual cached native status booleans lost');
 check(false===$r['fresh_provider_request_performed']&&$before===$writes,'Cached read refreshed provider or changed stored native settings');
}
echo "PASS: {$checks} fixed-target/native Unnotifier hide, renderer, restore, human choice, idempotence, partial failure, redaction, schema and permission assertions\n";
