<?php
if (!defined('ABSPATH')) exit;

final class Plugin_UI_Suite_Updater {
  const REPO = 'JordanSutton/Rescue-Plugin-Suite';
  const IGNORE_KEY = 'plugin_ui_suite_ignored_update_version';
  const RELEASE_TRANSIENT = 'plugin_ui_suite_latest_github_release';

  public static function init() {
    add_action('init', [__CLASS__, 'boot_update_checker']);
    add_action('admin_init', [__CLASS__, 'handle_ignore']);
    add_action('admin_notices', [__CLASS__, 'render_update_banner']);
  }

  public static function boot_update_checker() {
    $autoload = PLUGIN_SUITE_PATH . 'vendor/autoload.php';
    if (is_readable($autoload)) require_once $autoload;
    if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) return;
    $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
      'https://github.com/' . self::REPO,
      PLUGIN_SUITE_PATH . 'plugin-ui-suite.php',
      'plugin-ui-suite'
    );
    if (method_exists($checker, 'getVcsApi') && method_exists($checker->getVcsApi(), 'enableReleaseAssets')) $checker->getVcsApi()->enableReleaseAssets();
  }

  public static function latest_release() {
    $cached = get_transient(self::RELEASE_TRANSIENT);
    if (is_array($cached)) return $cached;
    $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', ['timeout'=>8,'headers'=>['Accept'=>'application/vnd.github+json']]);
    if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) >= 300) return [];
    $json = json_decode((string)wp_remote_retrieve_body($response), true);
    if (!is_array($json) || !empty($json['prerelease'])) return [];
    $release = [
      'version' => ltrim((string)($json['tag_name'] ?? ''), 'v'),
      'date' => (string)($json['published_at'] ?? ''),
      'notes' => wp_trim_words(wp_strip_all_tags((string)($json['body'] ?? '')), 40),
      'url' => esc_url_raw($json['html_url'] ?? 'https://github.com/' . self::REPO . '/releases'),
    ];
    set_transient(self::RELEASE_TRANSIENT, $release, 6 * HOUR_IN_SECONDS);
    return $release;
  }

  public static function handle_ignore() {
    if (empty($_GET['plugin_suite_ignore_update']) || !current_user_can('update_plugins')) return;
    check_admin_referer('plugin_suite_ignore_update');
    update_option(self::IGNORE_KEY, sanitize_text_field(wp_unslash($_GET['plugin_suite_ignore_update'])), false);
    wp_safe_redirect(remove_query_arg(['plugin_suite_ignore_update','_wpnonce']));
    exit;
  }

  public static function render_update_banner() {
    if (!current_user_can('update_plugins') || empty($_GET['page']) || $_GET['page'] !== 'plugin-ui-suite') return;
    $release = self::latest_release();
    if (empty($release['version']) || version_compare($release['version'], PLUGIN_SUITE_VERSION, '<=')) return;
    if (get_option(self::IGNORE_KEY) === $release['version']) return;
    $update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=plugin-ui-suite/plugin-ui-suite.php'), 'upgrade-plugin_plugin-ui-suite/plugin-ui-suite.php');
    $ignore_url = wp_nonce_url(add_query_arg('plugin_suite_ignore_update', rawurlencode($release['version'])), 'plugin_suite_ignore_update');
    echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Rescue Plugin Suite update available.', 'plugin-ui-suite') . '</strong> ' . esc_html(sprintf(__('Installed: %1$s. Latest: %2$s. Released: %3$s.', 'plugin-ui-suite'), PLUGIN_SUITE_VERSION, $release['version'], $release['date'])) . '</p>';
    if (!empty($release['notes'])) echo '<p>' . esc_html($release['notes']) . '</p>';
    echo '<p><a class="button button-primary" href="' . esc_url($update_url) . '">' . esc_html__('Update Now', 'plugin-ui-suite') . '</a> <a class="button" href="' . esc_url($release['url']) . '" target="_blank" rel="noopener">' . esc_html__('View Changelog', 'plugin-ui-suite') . '</a> <a class="button" href="' . esc_url($ignore_url) . '">' . esc_html__('Ignore This Version', 'plugin-ui-suite') . '</a></p></div>';
  }
}
