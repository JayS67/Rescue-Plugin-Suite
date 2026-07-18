<?php
/**
 * Plugin Name: Rescue Plugin Suite
 * Description: Unified commercial plugin suite for adoptables, adopted animals, statistics and forms, with shared settings, previews, proxy integration, setup wizard, diagnostics and snapshots.
 * Version: 14.0.33
 * Author: Jordan Sutton | Webstax
 */

if (!defined('ABSPATH')) exit;

define('PLUGIN_SUITE_VERSION', '14.0.33');
define('PLUGIN_SUITE_SCHEMA_VERSION', '1.2.1');
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
