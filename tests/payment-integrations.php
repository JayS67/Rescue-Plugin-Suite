<?php
/** Disposable WordPress fixture. No gateway or mail calls; saved settings are filtered in memory. */
if (!defined('ABSPATH') || !class_exists('Plugin_Payments_Module')) throw new RuntimeException('Run with WordPress and the suite active.');
$check = static function($pass,$message) { if (!$pass) throw new RuntimeException($message); echo "ok: $message\n"; };
$admins = get_users(['role'=>'administrator','number'=>1]);
if (!$admins) throw new RuntimeException('An administrator fixture is required.');
$previous_user = get_current_user_id(); wp_set_current_user($admins[0]->ID);
$original_audit = get_option(Plugin_Payments_Module::AUDIT_KEY, null);
$seed = Plugin_Payments_Module::defaults();
$seed['active_provider']='paypal';
$seed['general']['live_mode']=1;
$seed['gift_aid']['enabled']=1; $seed['gift_aid']['required']=1;
$seed['fee_recovery']['enabled']=1; $seed['fee_recovery']['payer']='donor';
$seed['campaigns']=['fixture-campaign'=>['slug'=>'fixture-campaign','title'=>'Keep this campaign','goal'=>100,'enabled'=>1,'status'=>'active']];
foreach (Plugin_Payments_Module::gateway_manager()->all() as $id=>$provider) {
  $seed['provider_settings'][$id]=['enabled'=>1];
  foreach ($provider->get_configuration_fields() as $key=>$field) $seed['provider_settings'][$id][$key]=($field['type']??'text')==='checkbox' ? 1 : ($key==='secret_key'?'sk_fixture_secret':($key==='publishable_key'?'pk_fixture_public':"fixture-$id-$key"));
}
$option_filter=static function() use (&$seed) { return $seed; };
$remote_calls=0;
$block_remote=static function() use (&$remote_calls) { $remote_calls++; return new WP_Error('fixture_network_disabled','No external calls in provider settings tests.'); };
add_filter('pre_option_'.Plugin_Payments_Module::OPTION_KEY,$option_filter);
add_filter('pre_http_request',$block_remote);
try {
  $expected=Plugin_Payments_Module::settings();
  foreach (Plugin_Payments_Module::gateway_manager()->all() as $id=>$provider) {
    ob_start(); Plugin_Payments_Module::render_integration_provider_panel('payment-'.$id); $html=ob_get_clean();
    $check(strpos($html,$provider->get_name().' integration')!==false && strpos($html,'Save integration')!==false,"$id integration renders completely");
    $check(strpos($html,'[_integration_provider]" value="'.$id.'"')!==false,"$id form identifies the provider being edited");
    $values=['enabled'=>0];
    foreach ($provider->get_configuration_fields() as $key=>$field) {
      $type=$field['type']??'text';
      $values[$key]=$type==='checkbox' ? 0 : ($type==='password' ? '  ' : "updated-$id-$key");
      if ($type==='password') $check(strpos($html,'value="'.$seed['provider_settings'][$id][$key].'"')===false,"$id/$key is not echoed into the form");
    }
    $result=Plugin_Payments_Module::sanitize_settings(['_integration_provider'=>$id,'active_provider'=>'sumup','provider_settings'=>[$id=>$values]]);
    $check($result['provider_settings'][$id]['enabled']===0,"$id can be disabled explicitly");
    foreach ($provider->get_configuration_fields() as $key=>$field) {
      $type=$field['type']??'text';
      $check($result['provider_settings'][$id][$key]===($type==='password'?$expected['provider_settings'][$id][$key]:$values[$key]),"$id/$key saves correctly and blank secrets are retained");
    }
    $expected_other=$expected; unset($expected_other['provider_settings'][$id]);
    $actual_other=$result; unset($actual_other['provider_settings'][$id]);
    $check($actual_other===$expected_other,"$id save preserves every unrelated setting and other provider");
    $updated=Plugin_Payments_Module::sanitize_settings(['_integration_provider'=>$id,'provider_settings'=>[$id=>['enabled'=>1]]]);
    foreach ($provider->get_configuration_fields() as $key=>$field) if (($field['type']??'text')!=='checkbox') $check($updated['provider_settings'][$id][$key]===$expected['provider_settings'][$id][$key],"$id omitted credential $key is preserved");
  }
  $result=Plugin_Payments_Module::sanitize_settings(['general'=>$expected['general']]);
  $check($result===$expected,'Saving one Donations group preserves all other groups and every integration');
  $check(Plugin_Payments_Module::sanitize_settings(['_integration_provider'=>'unknown','provider_settings'=>[]])===$expected,'Unknown integration scope cannot reset settings');
  wp_set_current_user(0);
  ob_start(); Plugin_Payments_Module::render_integration_provider_panel('payment-stripe'); $anonymous=ob_get_clean();
  $check($anonymous==='','Anonymous users cannot render integration credentials');
  $check($remote_calls===0,'Rendering and saving integration settings performs no external gateway calls');
} finally {
  remove_filter('pre_option_'.Plugin_Payments_Module::OPTION_KEY,$option_filter);
  remove_filter('pre_http_request',$block_remote);
  if ($original_audit===null) delete_option(Plugin_Payments_Module::AUDIT_KEY); else update_option(Plugin_Payments_Module::AUDIT_KEY,$original_audit,false);
  wp_set_current_user($previous_user);
}
