<?php
if (!defined('ABSPATH')) exit;

final class Plugin_UI_Suite_Updater {
  const REPO = 'JayS67/Rescue-Plugin-Suite';
  const IGNORE_KEY = 'plugin_ui_suite_ignored_update_version';
  const RELEASE_TRANSIENT = 'plugin_ui_suite_latest_github_release';
  const LAST_CHECK_KEY = 'plugin_ui_suite_last_update_check';
  const AUTO_UPDATES_KEY = 'plugin_ui_suite_auto_updates_enabled';
  const LAST_DIAGNOSTICS_KEY = 'plugin_ui_suite_update_diagnostics_v1';

  public static function repository() { return self::REPO; }
  public static function api_url() { return 'https://api.github.com/repos/' . self::repository() . '/releases/latest'; }
  public static function releases_url() { return 'https://github.com/' . self::repository() . '/releases'; }

  public static function init() {
    add_action('init', [__CLASS__, 'boot_update_checker']);
    add_action('admin_init', [__CLASS__, 'handle_ignore']);
    add_action('admin_init', [__CLASS__, 'handle_update_actions']);
    add_action('admin_notices', [__CLASS__, 'render_update_banner']);
    add_filter('auto_update_plugin', [__CLASS__, 'filter_auto_update'], 10, 2);
  }

  public static function boot_update_checker() {
    $autoload = PLUGIN_SUITE_PATH . 'vendor/autoload.php';
    if (is_readable($autoload)) require_once $autoload;
    if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) return;
    $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker('https://github.com/' . self::repository(), PLUGIN_SUITE_PATH . 'plugin-ui-suite.php', 'plugin-ui-suite');
    if (method_exists($checker, 'getVcsApi') && method_exists($checker->getVcsApi(), 'enableReleaseAssets')) $checker->getVcsApi()->enableReleaseAssets();
  }

  public static function latest_release($force=false) {
    if (!$force) { $cached = get_transient(self::RELEASE_TRANSIENT); if (is_array($cached)) return $cached; }
    $diag = ['repository'=>self::repository(),'api_url'=>self::api_url(),'last_response_code'=>'','latest_version'=>'','installed_version'=>PLUGIN_SUITE_VERSION,'release_asset_selected'=>'','last_error'=>''];
    $response = wp_remote_get(self::api_url(), ['timeout'=>12,'headers'=>['Accept'=>'application/vnd.github+json','User-Agent'=>'Rescue-Plugin-Suite/'.PLUGIN_SUITE_VERSION]]);
    update_option(self::LAST_CHECK_KEY, current_time('mysql'), false);
    if (is_wp_error($response)) { $diag['last_error'] = $response->get_error_message(); update_option(self::LAST_DIAGNOSTICS_KEY, $diag, false); return []; }
    $diag['last_response_code'] = (int)wp_remote_retrieve_response_code($response);
    if ((int)$diag['last_response_code'] >= 300) { $diag['last_error'] = 'GitHub returned HTTP ' . $diag['last_response_code']; update_option(self::LAST_DIAGNOSTICS_KEY, $diag, false); return []; }
    $json = json_decode((string)wp_remote_retrieve_body($response), true);
    if (!is_array($json) || !empty($json['prerelease'])) { $diag['last_error'] = 'No stable latest release returned.'; update_option(self::LAST_DIAGNOSTICS_KEY, $diag, false); return []; }
    $asset = '';
    foreach ((array)($json['assets'] ?? []) as $candidate) { if (!empty($candidate['name']) && preg_match('/\\.zip$/i', $candidate['name'])) { $asset = $candidate['name']; break; } }
    $release = ['version'=>ltrim((string)($json['tag_name'] ?? ''), 'v'),'date'=>(string)($json['published_at'] ?? ''),'notes'=>wp_trim_words(wp_strip_all_tags((string)($json['body'] ?? '')), 40),'url'=>esc_url_raw($json['html_url'] ?? self::releases_url()),'body'=>(string)($json['body'] ?? ''),'asset'=>$asset];
    $diag['latest_version'] = $release['version']; $diag['release_asset_selected'] = $asset;
    update_option(self::LAST_DIAGNOSTICS_KEY, $diag, false);
    set_transient(self::RELEASE_TRANSIENT, $release, 6 * HOUR_IN_SECONDS);
    return $release;
  }

  public static function handle_ignore() {
    if (empty($_GET['plugin_suite_ignore_update']) || !current_user_can('update_plugins')) return;
    check_admin_referer('plugin_suite_ignore_update'); update_option(self::IGNORE_KEY, sanitize_text_field(wp_unslash($_GET['plugin_suite_ignore_update'])), false); wp_safe_redirect(remove_query_arg(['plugin_suite_ignore_update','_wpnonce'])); exit;
  }

  public static function handle_update_actions() {
    $suite_updates = !empty($_GET['page']) && $_GET['page'] === 'plugin-ui-suite' && ($_GET['tab'] ?? '') === 'updates';
    $legacy_updates = !empty($_GET['page']) && $_GET['page'] === 'plugin-ui-suite-updates';
    if ($legacy_updates) { wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates'], admin_url('options-general.php'))); exit; }
    if (!$suite_updates || !current_user_can('update_plugins')) return;
    if (!empty($_POST['plugin_suite_check_updates'])) { check_admin_referer('plugin_suite_updates'); delete_transient(self::RELEASE_TRANSIENT); self::latest_release(true); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','checked'=>'1'], admin_url('options-general.php'))); exit; }
    if (isset($_POST['plugin_suite_auto_updates'])) { check_admin_referer('plugin_suite_updates'); update_option(self::AUTO_UPDATES_KEY, !empty($_POST['enabled']) ? 1 : 0, false); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','saved'=>'1'], admin_url('options-general.php'))); exit; }
  }

  public static function filter_auto_update($update, $item) { if (empty($item->plugin) || $item->plugin !== 'plugin-ui-suite/plugin-ui-suite.php') return $update; return (bool)get_option(self::AUTO_UPDATES_KEY, 0); }

  public static function render_update_banner() {
    if (!current_user_can('update_plugins')) return;
    $release = self::latest_release();
    if (empty($release['version']) || version_compare($release['version'], PLUGIN_SUITE_VERSION, '<=') || get_option(self::IGNORE_KEY) === $release['version']) return;
    $update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=plugin-ui-suite/plugin-ui-suite.php'), 'upgrade-plugin_plugin-ui-suite/plugin-ui-suite.php');
    $ignore_url = wp_nonce_url(add_query_arg('plugin_suite_ignore_update', rawurlencode($release['version'])), 'plugin_suite_ignore_update');
    echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Rescue Plugin Suite update available.', 'plugin-ui-suite') . '</strong> ' . esc_html(sprintf(__('Installed version: %1$s. Latest version: %2$s.', 'plugin-ui-suite'), PLUGIN_SUITE_VERSION, $release['version'])) . '</p>';
    if (!empty($release['notes'])) echo '<p>' . esc_html($release['notes']) . '</p>';
    echo '<p><a class="button button-primary" href="' . esc_url($update_url) . '">' . esc_html__('Update Now', 'plugin-ui-suite') . '</a> <a class="button" href="' . esc_url(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates'], admin_url('options-general.php'))) . '">' . esc_html__('View Changelog', 'plugin-ui-suite') . '</a> <a class="button" href="' . esc_url($ignore_url) . '">' . esc_html__('Ignore This Version', 'plugin-ui-suite') . '</a></p></div>';
  }

  public static function render_updates_panel($embedded=false) {
    if (!current_user_can('update_plugins')) return;
    $release = self::latest_release(!empty($_GET['checked'])); $latest = $release['version'] ?? __('Unknown','plugin-ui-suite');
    $is_current = !empty($release['version']) ? version_compare($release['version'], PLUGIN_SUITE_VERSION, '<=') : null; $diag = get_option(self::LAST_DIAGNOSTICS_KEY, []); if (!is_array($diag)) $diag=[];
    if (!$embedded) echo '<div class="wrap plugin-suite-updates"><h1>' . esc_html__('Rescue Plugin Suite Updates', 'plugin-ui-suite') . '</h1>';
    echo '<div class="plugin-suite-update-grid">';
    foreach ([__('Installed version','plugin-ui-suite')=>PLUGIN_SUITE_VERSION, __('Latest version','plugin-ui-suite')=>$latest, __('Repository','plugin-ui-suite')=>self::repository(), __('GitHub API URL','plugin-ui-suite')=>self::api_url(), __('Last checked','plugin-ui-suite')=>get_option(self::LAST_CHECK_KEY, __('Never','plugin-ui-suite')), __('Automatic updates','plugin-ui-suite')=>(get_option(self::AUTO_UPDATES_KEY,0)?__('Enabled','plugin-ui-suite'):__('Disabled','plugin-ui-suite')), __('Status','plugin-ui-suite')=>($is_current === null ? __('Unable to confirm','plugin-ui-suite') : ($is_current ? __('Up to date','plugin-ui-suite') : __('Update available','plugin-ui-suite')))] as $label=>$value) echo '<div class="plugin-suite-update-card"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    echo '</div><form class="plugin-suite-update-actions" method="post">'; wp_nonce_field('plugin_suite_updates'); echo '<button class="button button-primary" name="plugin_suite_check_updates" value="1">' . esc_html__('Check for updates now', 'plugin-ui-suite') . '</button> <a class="button" href="' . esc_url(PLUGIN_SUITE_URL . 'changelog.md') . '">' . esc_html__('View changelog', 'plugin-ui-suite') . '</a> <a class="button" target="_blank" rel="noopener" href="' . esc_url(self::releases_url()) . '">' . esc_html__('View releases', 'plugin-ui-suite') . '</a></form>';
    echo '<form class="plugin-suite-update-actions" method="post">'; wp_nonce_field('plugin_suite_updates'); echo '<input type="hidden" name="plugin_suite_auto_updates" value="1"/><label><input type="checkbox" name="enabled" value="1" ' . checked(get_option(self::AUTO_UPDATES_KEY,0),1,false) . '/> ' . esc_html__('Enable automatic updates for this plugin', 'plugin-ui-suite') . '</label> <button class="button">' . esc_html__('Save automatic update setting', 'plugin-ui-suite') . '</button></form>';
    echo '<h2>' . esc_html__('Update diagnostics', 'plugin-ui-suite') . '</h2><table class="widefat striped"><tbody>'; foreach (['repository','api_url','last_response_code','latest_version','installed_version','release_asset_selected','last_error'] as $key) echo '<tr><th>'.esc_html(ucwords(str_replace('_',' ',$key))).'</th><td><code>'.esc_html((string)($diag[$key] ?? '')).'</code></td></tr>'; echo '</tbody></table>';
    echo '<h2>' . esc_html__('Release notes', 'plugin-ui-suite') . '</h2><div class="plugin-suite-release-notes">'; if (!empty($release['body'])) echo wp_kses_post(wpautop($release['body'])); else echo '<p>' . esc_html__('No release notes were returned by GitHub. Try checking again later or open GitHub releases.', 'plugin-ui-suite') . '</p>'; echo '</div>';
    if (!$embedded) echo '</div>';
  }
  public static function render_updates_page() { self::render_updates_panel(false); }
}
