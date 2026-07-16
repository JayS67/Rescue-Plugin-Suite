<?php
/**
 * Plugin Name: ASM Plugin Suite
 * Description: Unified ASM plugin suite for adoptables, adopted animals, statistics and forms, with shared settings, previews, proxy integration, setup wizard, diagnostics and snapshots.
 * Version: 14.0.32
 * Author: Jordan Sutton
 */

if (!defined('ABSPATH')) exit;

define('STRAYSAFE_SUITE_VERSION', '14.0.32');
define('STRAYSAFE_SUITE_PATH', plugin_dir_path(__FILE__));
define('STRAYSAFE_SUITE_URL', plugin_dir_url(__FILE__));

require_once STRAYSAFE_SUITE_PATH . 'includes/core/helpers.php';
require_once STRAYSAFE_SUITE_PATH . 'includes/core/class-plugin.php';
require_once STRAYSAFE_SUITE_PATH . 'includes/core/class-seo.php';

StraySafe_UI_Suite_Plugin::init();
StraySafe_UI_Suite_SEO::init();
register_activation_hook(__FILE__, ['StraySafe_UI_Suite_Plugin', 'activate']);
