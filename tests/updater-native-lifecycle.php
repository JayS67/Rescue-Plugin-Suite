<?php
/**
 * Destructive integration test ONLY for a disposable WordPress installation.
 * RPS_UPDATER_TESTS=1 wp eval-file tests/updater-native-lifecycle.php manual /path/package.zip
 * Modes: manual, automatic, rejected-source, incomplete-package.
 * Run a fresh WP request after each test to confirm the replacement can bootstrap.
 */
if (!defined('ABSPATH') || getenv('RPS_UPDATER_TESTS') !== '1') throw new RuntimeException('Use an explicitly opted-in disposable WordPress installation.');
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$mode = $args[0] ?? 'manual';
$package = $args[1] ?? '';
if (!is_readable($package)) throw new RuntimeException('Supply the complete production ZIP path.');
$plugin = Plugin_UI_Suite_Updater::plugin_file();
$version_before = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false)['Version'];
if (!is_plugin_active($plugin)) throw new RuntimeException('Activate this plugin first.');
wp_set_current_user(1);
add_filter('filesystem_method', static function() { return 'direct'; });
add_filter('pre_http_request', static function($pre, $request, $url) { return strpos($url, home_url('/')) === 0 ? $pre : new WP_Error('fixture_network_disabled', 'External services are disabled in this fixture.'); }, 10, 3);
set_transient(Plugin_UI_Suite_Updater::RELEASE_TRANSIENT, ['version'=>'15.1.2', 'download_url'=>$package, 'url'=>'https://example.test/releases/v15.1.2', 'body'=>'Local lifecycle fixture'], HOUR_IN_SECONDS);
$offer = (object)['id'=>'github.com/JayS67/Rescue-Plugin-Suite','slug'=>'rescue-plugin-suite','plugin'=>$plugin,'new_version'=>'15.1.2','package'=>$package,'url'=>'https://example.test/releases/v15.1.2'];
set_site_transient('update_plugins', (object)['last_checked'=>time(), 'checked'=>[$plugin=>$version_before], 'response'=>[$plugin=>$offer], 'no_update'=>[]]);
$rejection = new WP_Error('fixture_rejected_source', 'An earlier validator rejected the fixture.');
if ($mode === 'rejected-source') add_filter('upgrader_source_selection', static function() use ($rejection) { return $rejection; }, 5);
$complete = false;
add_action('upgrader_process_complete', static function() use (&$complete) { $complete = true; });
if ($mode === 'automatic') {
  if (!defined('DOING_CRON')) define('DOING_CRON', true);
  update_site_option('auto_update_plugins', [$plugin]);
  add_filter('automatic_updater_disabled', '__return_false');
  add_filter('automatic_updates_is_vcs_checkout', '__return_false');
  $automatic = new WP_Automatic_Updater();
  $result = $automatic->update('plugin', $offer);
} else {
  $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
  $result = $upgrader->upgrade($plugin);
  if (!$result && $upgrader->skin->get_errors()->has_errors()) $result = $upgrader->skin->get_errors();
}
wp_clean_plugins_cache(false);
$version_after = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false)['Version'];
if (in_array($mode, ['rejected-source', 'incomplete-package'], true)) {
  $expected = $mode === 'rejected-source' ? 'fixture_rejected_source' : 'rescue_update_incomplete';
  if (!is_wp_error($result) || $result->get_error_code() !== $expected || $version_after !== $version_before) throw new RuntimeException('Unsafe failure: result=' . (is_wp_error($result) ? $result->get_error_code() . ': ' . $result->get_error_message() : wp_json_encode($result)) . ', before=' . $version_before . ', after=' . $version_after);
  echo "PASS {$mode}: native updater reported WP_Error and preserved {$version_before}.\n";
} else {
  if (is_wp_error($result) || !$complete || $version_after !== '15.1.2') throw new RuntimeException('Native update did not complete: ' . (is_wp_error($result) ? $result->get_error_message() : wp_json_encode($result)));
  if (!is_file(WP_PLUGIN_DIR . '/' . $plugin)) throw new RuntimeException('Plugin basename changed.');
  if ($mode === 'automatic' && !is_plugin_active($plugin)) throw new RuntimeException('Automatic update deactivated the plugin.');
  if (is_file(ABSPATH . '.maintenance')) throw new RuntimeException('Maintenance mode was left enabled.');
  echo "PASS {$mode}: {$version_before} -> {$version_after}, installed basename preserved, maintenance removed.\n";
}
