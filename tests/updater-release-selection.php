<?php
/** Standalone regression tests for GitHub release eligibility and semantic selection. */
define('ABSPATH', __DIR__ . '/');
define('PLUGIN_SUITE_VERSION', '15.0.8-beta');
define('PLUGIN_SUITE_PATH', dirname(__DIR__) . '/');
function plugin_basename($path) { return 'rescue-plugin-suite/plugin-ui-suite.php'; }
function get_option($key, $default = false) { return $default; }
function wp_trim_words($text) { return $text; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function esc_url_raw($url) { return $url; }
require dirname(__DIR__) . '/includes/core/class-updater.php';
function release($tag, $pre = false, $asset = true) {
  $version = Plugin_UI_Suite_Updater::normalize_version($tag);
  return ['tag_name'=>$tag, 'draft'=>false, 'prerelease'=>$pre, 'published_at'=>'2026-01-01T00:00:00Z', 'html_url'=>'https://example.test/'.$tag, 'assets'=>$asset ? [['name'=>'rescue-plugin-suite-v'.$version.'.zip','browser_download_url'=>'https://example.test/'.$version.'.zip']] : []];
}
function selected($releases, $installed, $channel) { return Plugin_UI_Suite_Updater::select_release_candidate($releases, $installed, $channel)['release']['version'] ?? ''; }
function ok($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "ok: $message\n"; }

ok(version_compare('15.0.8', '15.0.8-beta', '>') === true, 'PHP treats stable as newer than matching beta');
ok(selected([release('v15.0.8')], '15.0.8-beta', 'beta') === '15.0.8', 'beta can update to matching stable');
ok(selected([release('v15.0.9')], '15.0.8-beta', 'beta') === '15.0.9', 'beta can update to newer stable');
ok(selected([release('v15.0.9-beta', true)], '15.0.8-beta', 'beta') === '15.0.9-beta', 'beta can update to newer beta');
ok(selected([release('v15.0.9-beta', true), release('v15.0.8')], '15.0.8', 'stable') === '', 'stable channel excludes beta');
ok(selected([release('v15.0.9')], '15.0.9-rc1', 'beta') === '15.0.9', 'RC can update to stable');
ok(selected([release('v15.0.8'), release('v15.0.9-beta', true)], '15.0.8-beta', 'beta') === '15.0.9-beta', 'highest semantic version wins regardless of publication order');
$result = Plugin_UI_Suite_Updater::select_release_candidate([release('v15.0.8', false, false)], '15.0.8-beta', 'beta');
ok(empty($result['release']) && $result['candidates'][0]['rejection_reason'] === 'Exact release package missing', 'missing exact stable asset is diagnosed and rejected');
ok($result['candidates'][0]['expected_asset'] === 'rescue-plugin-suite-v15.0.8.zip', 'exact custom asset is required');
$updater_source = file_get_contents(dirname(__DIR__) . '/includes/core/class-updater.php');
ok(strpos($updater_source, 'self::clear_update_caches();') !== false && strpos($updater_source, 'self::latest_release(true); wp_update_plugins();') !== false, 'manual refresh clears updater caches before a forced API refresh and native metadata rebuild');
