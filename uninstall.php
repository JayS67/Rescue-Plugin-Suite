<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$settings = get_option('plugin_ui_suite_settings_v83', []);
if (empty($settings['global']['delete_data_on_uninstall'])) {
  wp_clear_scheduled_hook('plugin_suite_retry_webhooks_cron');
  return;
}

$options = [
  'plugin_ui_suite_settings_v83',
  'plugin_ui_suite_snapshots_v1',
  'plugin_ui_suite_version_log_v1',
  'plugin_ui_suite_needs_setup_v1',
  'plugin_suite_user_defaults_v1',
  'plugin_suite_analytics_v1',
  'plugin_suite_enquiry_events_v1',
  'plugin_suite_webhook_retry_queue_v1',
  'plugin_suite_webhook_audit_v1',
  'plugin_ui_suite_setting_packs',
  'plugin_adoptables_ui_options',
  'plugin_adopted_ui_options',
  'plugin_stats_ui_options',
  'plugin_ui_suite_provider_diagnostics',
  'plugin_ui_suite_schema_version',
  'plugin_ui_suite_ignored_update_version',
  'plugin_payments_settings_v1',
  'plugin_payments_audit_v1',
  'plugin_payments_webhook_events_v1',
  'plugin_payments_payment_events_v1',
  'plugin_payments_subscription_events_v1',
  'plugin_payments_gocardless_pending_v1',
  'plugin_payments_gift_aid_v1',
];

wp_clear_scheduled_hook('plugin_suite_retry_webhooks_cron');

foreach ($options as $option) {
  delete_option($option);
  delete_site_option($option);
}

$patterns = [
  '_transient_plugin_%',
  '_transient_timeout_plugin_%',
  '_transient_plugin_ui_suite_%',
  '_transient_timeout_plugin_ui_suite_%',
  '_transient_plugin_suite_track_%',
  '_transient_timeout_plugin_suite_track_%',
  'plugin_ui_suite_last_good_%',
];

foreach ($patterns as $pattern) {
  $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
}
