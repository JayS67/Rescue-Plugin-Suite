<?php
/**
 * Plugin Name: Rescue Plugin Suite
 * Description: Unified commercial plugin suite for adoptables, adopted animals, statistics and forms, with shared settings, previews, proxy integration, setup wizard, diagnostics and snapshots.
 * Version: 15.0.5-beta
 * Author: Jordan Sutton | Webstax
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('plugin_ui_suite_installed_version')) {
  function plugin_ui_suite_installed_version() {
    $source = is_readable(__FILE__) ? (string) file_get_contents(__FILE__, false, null, 0, 8192) : '';
    if (preg_match('/^[ \t\/*#@]*Version:\s*([^\r\n*]+)/mi', $source, $matches)) {
      return trim($matches[1]);
    }
    return '0.0.0';
  }
}
define('PLUGIN_SUITE_VERSION', plugin_ui_suite_installed_version());
define('PLUGIN_SUITE_SCHEMA_VERSION', '1.2.2');
define('PLUGIN_SUITE_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_SUITE_URL', plugin_dir_url(__FILE__));

require_once PLUGIN_SUITE_PATH . 'includes/core/helpers.php';
require_once PLUGIN_SUITE_PATH . 'includes/core/class-registry.php';
require_once PLUGIN_SUITE_PATH . 'includes/core/class-plugin.php';
require_once PLUGIN_SUITE_PATH . 'includes/core/class-seo.php';
require_once PLUGIN_SUITE_PATH . 'includes/core/class-updater.php';

Plugin_UI_Suite_Plugin::init();
Plugin_UI_Suite_SEO::init();
Plugin_UI_Suite_Updater::init();
register_activation_hook(__FILE__, ['Plugin_UI_Suite_Plugin', 'activate']);
