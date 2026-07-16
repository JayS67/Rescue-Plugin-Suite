<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$options = [
  'straysafe_ui_suite_settings_v83',
  'straysafe_ui_suite_snapshots_v1',
  'straysafe_ui_suite_version_log_v1',
  'straysafe_ui_suite_needs_setup_v1',
  'asm_plugin_suite_user_defaults_v1',
  'asm_plugin_suite_analytics_v1',
  'asm_plugin_suite_enquiry_events_v1',
  'asm_plugin_suite_webhook_retry_queue_v1',
  'asm_plugin_suite_webhook_audit_v1',
  'straysafe_ui_suite_setting_packs',
  'straysafe_adoptables_ui_options',
  'straysafe_adopted_ui_options',
  'straysafe_stats_ui_options',
  'straysafe_ui_suite_provider_diagnostics',
];

wp_clear_scheduled_hook('asm_plugin_suite_retry_webhooks_cron');

foreach ($options as $option) {
  delete_option($option);
  delete_site_option($option);
}

$patterns = [
  '_transient_ss_%',
  '_transient_timeout_ss_%',
  '_transient_straysafe_ui_suite_%',
  '_transient_timeout_straysafe_ui_suite_%',
  '_transient_asm_plugin_suite_track_%',
  '_transient_timeout_asm_plugin_suite_track_%',
  'straysafe_ui_suite_last_good_%',
];

foreach ($patterns as $pattern) {
  $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
}
