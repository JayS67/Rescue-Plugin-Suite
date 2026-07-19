<?php
/** Regression tests for global WordPress upgrader hook signatures and guards. */
define('ABSPATH', __DIR__ . '/');
define('PLUGIN_SUITE_VERSION', '15.0.8-beta');
define('PLUGIN_SUITE_PATH', dirname(__DIR__) . '/');
$filters = [];
$actions = [];
function plugin_basename($path) { return 'rescue-plugin-suite/plugin-ui-suite.php'; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { global $filters; $filters[$hook] = [$callback, $priority, $accepted_args]; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { global $actions; $actions[$hook] = [$callback, $priority, $accepted_args]; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function is_wp_error($value) { return false; }
require dirname(__DIR__) . '/includes/core/class-updater.php';
function ok($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "ok: $message\n"; }

Plugin_UI_Suite_Updater::init();
ok($filters['upgrader_pre_install'][2] === 2, 'pre-install hook accepts the two arguments WordPress supplies');
ok($filters['upgrader_post_install'][2] === 3, 'post-install hook accepts its response, context, and result arguments');
ok($filters['upgrader_source_selection'][2] === 4, 'source-selection hook accepts all WordPress filter arguments');
ok($actions['upgrader_process_complete'][2] === 2, 'process-complete action accepts its upgrader and context arguments');
ok((new ReflectionMethod('Plugin_UI_Suite_Updater', 'pre_install'))->getNumberOfRequiredParameters() === 2, 'pre_install has exactly two required parameters');
ok((new ReflectionMethod('Plugin_UI_Suite_Updater', 'post_install'))->getNumberOfRequiredParameters() === 3, 'post_install has exactly three required parameters');

$unrelated = ['type' => 'plugin', 'action' => 'update', 'plugin' => 'akismet/akismet.php'];
$options = ['hook_extra' => $unrelated, 'package' => 'https://downloads.wordpress.org/plugin/akismet.zip'];
ok(Plugin_UI_Suite_Updater::package_options($options) === $options, 'unrelated package options are unchanged');
$response = new stdClass();
ok(Plugin_UI_Suite_Updater::pre_install($response, $unrelated) === $response, 'unrelated pre-install response is unchanged');
ok(Plugin_UI_Suite_Updater::post_install($response, $unrelated, ['destination' => '/plugins/akismet/']) === $response, 'unrelated post-install response is unchanged');
ok(Plugin_UI_Suite_Updater::source_selection('/tmp/akismet/', '/tmp/', new stdClass(), $unrelated) === '/tmp/akismet/', 'unrelated source selection is unchanged');
$theme = ['type' => 'theme', 'action' => 'update', 'theme' => 'twentytwentyfive'];
ok(Plugin_UI_Suite_Updater::pre_install($response, $theme) === $response, 'theme pre-install response is unchanged');
