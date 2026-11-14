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
class WP_Error { public function __construct(public $code = '', public $message = '') {} public function get_error_message() { return $this->message; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
$trace_writes = 0;
function current_time($format) { return '2026-01-01 00:00:00'; }
function get_option($key, $default = false) { return $default; }
function update_option(...$args) { global $trace_writes; $trace_writes++; return true; }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
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

$error = new WP_Error('validation_failed', 'The package was rejected by another validator.');
ok(Plugin_UI_Suite_Updater::source_selection($error, '/tmp/', new stdClass(), ['plugin'=>'rescue-plugin-suite/plugin-ui-suite.php']) === $error, 'WP_Error source is preserved without a fatal object-to-string conversion');
ok(Plugin_UI_Suite_Updater::package_options(null) === null, 'non-array package options pass through unchanged');
$ours = ['type'=>'plugin', 'action'=>'update', 'plugin'=>'rescue-plugin-suite/plugin-ui-suite.php'];
Plugin_UI_Suite_Updater::package_options(['hook_extra'=>$ours]);
$before = $trace_writes;
Plugin_UI_Suite_Updater::pre_install($response, $theme);
Plugin_UI_Suite_Updater::post_install($response, $unrelated, []);
ok($trace_writes === $before, 'previous own-package context cannot claim a later unrelated upgrade');
class PackageFilesystem {
  public $missing = '';
  public function is_file($file) { return $this->missing === '' || strpos($file, $this->missing) === false; }
  public function get_contents($file) { return "<?php\n/**\n * Plugin Name: Rescue Plugin Suite\n * Version: 15.1.2\n */"; }
}
$wp_filesystem = new PackageFilesystem();
$wp_filesystem->missing = 'includes/core/helpers.php';
$rejected = Plugin_UI_Suite_Updater::source_selection('/tmp/rescue-plugin-suite/', '/tmp/', new stdClass(), $ours);
ok(is_wp_error($rejected) && $rejected->code === 'rescue_update_incomplete', 'an incomplete production archive is rejected before installed files are removed');
$wp_filesystem->missing = '';
ok(Plugin_UI_Suite_Updater::source_selection('/tmp/rescue-plugin-suite/', '/tmp/', new stdClass(), $ours) === '/tmp/rescue-plugin-suite/', 'a complete canonical production archive passes source validation');
