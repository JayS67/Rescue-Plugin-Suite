<?php
/** Failed release lookups must not repeatedly block native update hooks. */
define('ABSPATH', __DIR__ . '/');
define('PLUGIN_SUITE_VERSION', '15.1.2');
define('PLUGIN_SUITE_PATH', dirname(__DIR__) . '/');
define('MINUTE_IN_SECONDS', 60);
$transients = [];
$requests = 0;
class WP_Error { public function get_error_message() { return 'Offline fixture'; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function plugin_basename($file) { return 'rescue-plugin-suite/plugin-ui-suite.php'; }
function get_option($key, $default = false) { return $default; }
function update_option(...$args) { return true; }
function current_time($format) { return '2026-01-01 00:00:00'; }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
function wp_remote_get(...$args) { global $requests; $requests++; return new WP_Error(); }
require dirname(__DIR__) . '/includes/core/class-updater.php';
Plugin_UI_Suite_Updater::latest_release();
Plugin_UI_Suite_Updater::latest_release();
if ($requests !== 1) throw new RuntimeException('Repeated update hooks retried a failed release request.');
Plugin_UI_Suite_Updater::latest_release(true);
if ($requests !== 2) throw new RuntimeException('Manual refresh did not bypass the negative cache.');
echo "Release lookup failure is cached, and manual refresh still retries.\n";
