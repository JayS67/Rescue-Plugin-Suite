<?php
if (!defined('ABSPATH')) exit;

/** Native WordPress.org-style updater for the GitHub release ZIP. */
final class Plugin_UI_Suite_Updater {
  const REPO = 'JayS67/Rescue-Plugin-Suite';
  const SLUG = 'rescue-plugin-suite';
  const PACKAGE_DIRECTORY = 'rescue-plugin-suite';
  const IGNORE_KEY = 'plugin_ui_suite_ignored_update_version';
  const RELEASE_TRANSIENT = 'plugin_ui_suite_latest_github_release';
  const LAST_CHECK_KEY = 'plugin_ui_suite_last_update_check';
  const AUTO_UPDATES_KEY = 'plugin_ui_suite_auto_updates_enabled';
  const LAST_DIAGNOSTICS_KEY = 'plugin_ui_suite_update_diagnostics_v2';
  const LOG_KEY = 'plugin_ui_suite_update_trace_v1';

  public static function repository() { return self::REPO; }
  public static function api_url() { return 'https://api.github.com/repos/' . self::repository() . '/releases'; }
  public static function releases_url() { return 'https://github.com/' . self::repository() . '/releases'; }
  public static function plugin_file() { return plugin_basename(PLUGIN_SUITE_PATH . 'plugin-ui-suite.php'); }
  private static function plugin_directory() { return dirname(self::plugin_file()); }

  public static function normalize_version($version) { $version = trim((string)$version); $version = preg_replace('/^refs\/tags\//i', '', $version); return preg_replace('/^v(?=\d)/i', '', $version); }
  public static function compare_versions($left, $right, $operator = null) { $left = self::normalize_version($left); $right = self::normalize_version($right); return $operator === null ? version_compare($left, $right) : version_compare($left, $right, $operator); }

  /** Keep a small, inspectable trace without exposing response bodies or credentials. */
  private static function trace($stage, $data = []) {
    $entry = array_merge(['time'=>current_time('mysql'), 'stage'=>$stage, 'installed_version'=>PLUGIN_SUITE_VERSION, 'plugin_basename'=>self::plugin_file(), 'slug'=>self::SLUG], $data);
    $log = get_option(self::LOG_KEY, []); if (!is_array($log)) $log = []; $log[] = $entry;
    update_option(self::LOG_KEY, array_slice($log, -100), false);
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) error_log('Rescue Plugin Suite updater: ' . wp_json_encode($entry));
  }
  private static function diagnostics($values) { update_option(self::LAST_DIAGNOSTICS_KEY, $values, false); }

  public static function init() {
    add_action('admin_init', [__CLASS__, 'handle_ignore']); add_action('admin_init', [__CLASS__, 'handle_update_actions']); add_action('admin_notices', [__CLASS__, 'render_update_banner']);
    add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update_transient']);
    add_filter('plugins_api', [__CLASS__, 'plugin_information'], 10, 3);
    add_filter('auto_update_plugin', [__CLASS__, 'filter_auto_update'], 10, 2);
    add_filter('upgrader_package_options', [__CLASS__, 'package_options']);
    add_filter('upgrader_pre_install', [__CLASS__, 'pre_install'], 10, 3);
    add_filter('upgrader_source_selection', [__CLASS__, 'source_selection'], 10, 4);
    add_action('upgrader_process_complete', [__CLASS__, 'after_upgrade'], 10, 2);
  }

  public static function latest_release($force=false) {
    if (!$force && ($cached = get_transient(self::RELEASE_TRANSIENT)) && is_array($cached)) { self::trace('release_cache_hit', ['latest_version'=>$cached['version'] ?? '']); return $cached; }
    $diag = ['repository'=>self::repository(),'api_url'=>self::api_url(),'installed_version'=>PLUGIN_SUITE_VERSION,'plugin_basename'=>self::plugin_file(),'slug'=>self::SLUG,'package_directory'=>self::PACKAGE_DIRECTORY,'last_response_code'=>'','latest_version'=>'','release_asset_selected'=>'','download_url'=>'','last_error'=>''];
    self::trace('github_request', ['api_url'=>self::api_url()]);
    $response = wp_remote_get(self::api_url(), ['timeout'=>20,'headers'=>['Accept'=>'application/vnd.github+json','User-Agent'=>'Rescue-Plugin-Suite/'.PLUGIN_SUITE_VERSION]]); update_option(self::LAST_CHECK_KEY, current_time('mysql'), false);
    if (is_wp_error($response)) { $diag['last_error'] = $response->get_error_message(); self::trace('github_error', ['error'=>$diag['last_error']]); self::diagnostics($diag); return []; }
    $diag['last_response_code'] = (int) wp_remote_retrieve_response_code($response);
    if ($diag['last_response_code'] < 200 || $diag['last_response_code'] >= 300) { $diag['last_error'] = 'GitHub returned HTTP ' . $diag['last_response_code']; self::trace('github_error', ['error'=>$diag['last_error']]); self::diagnostics($diag); return []; }
    $releases = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($releases)) { $diag['last_error'] = 'GitHub returned an invalid release response.'; self::diagnostics($diag); self::trace('github_error', ['error'=>$diag['last_error']]); return []; }
    if (isset($releases['tag_name'])) $releases = [$releases];
    $release_json = []; foreach ($releases as $candidate) { if (is_array($candidate) && empty($candidate['draft'])) { $release_json = $candidate; break; } }
    if (!$release_json) { $diag['last_error'] = 'No published GitHub release was returned.'; self::diagnostics($diag); self::trace('github_error', ['error'=>$diag['last_error']]); return []; }
    $asset = ''; $download_url = '';
    foreach ((array)($release_json['assets'] ?? []) as $candidate) {
      $name = (string)($candidate['name'] ?? '');
      if (preg_match('/^rescue-plugin-suite-.+\.zip$/i', $name) && !empty($candidate['browser_download_url'])) { $asset = $name; $download_url = esc_url_raw($candidate['browser_download_url']); break; }
    }
    // A GitHub source archive has a tag-dependent root directory and cannot safely replace this plugin.
    $release = ['version'=>self::normalize_version($release_json['tag_name'] ?? ''),'date'=>(string)($release_json['published_at'] ?? ''),'notes'=>wp_trim_words(wp_strip_all_tags((string)($release_json['body'] ?? '')), 40),'url'=>esc_url_raw($release_json['html_url'] ?? self::releases_url()),'body'=>(string)($release_json['body'] ?? ''),'asset'=>$asset,'download_url'=>$download_url];
    $diag['latest_version']=$release['version']; $diag['release_asset_selected']=$asset; $diag['download_url']=$download_url;
    if (!$release['version']) $diag['last_error']='Release tag did not include a version.'; elseif (!$download_url) $diag['last_error']='No valid Rescue Plugin Suite release ZIP asset was available; GitHub source archives are intentionally rejected.';
    self::diagnostics($diag); self::trace('release_selected', ['latest_version'=>$release['version'],'asset'=>$asset,'package_url'=>$download_url,'error'=>$diag['last_error']]);
    if (!$diag['last_error']) set_transient(self::RELEASE_TRANSIENT, $release, 6 * HOUR_IN_SECONDS);
    return $release;
  }

  private static function update_object($force=false) { $release=self::latest_release($force); if (empty($release['version']) || empty($release['download_url']) || self::compare_versions($release['version'], PLUGIN_SUITE_VERSION, '<=')) return null; $update=(object)['id'=>self::repository(),'slug'=>self::SLUG,'plugin'=>self::plugin_file(),'new_version'=>$release['version'],'url'=>$release['url'] ?: self::releases_url(),'package'=>$release['download_url'],'tested'=>get_bloginfo('version'),'requires_php'=>'7.4']; self::trace('update_object_created', ['latest_version'=>$update->new_version,'package_url'=>$update->package]); return $update; }
  public static function inject_update_transient($transient) { if (!is_object($transient)) $transient=new stdClass(); $transient->response=(array)($transient->response ?? []); $transient->no_update=(array)($transient->no_update ?? []); $update=self::update_object(false); if ($update) { $transient->response[self::plugin_file()]=$update; unset($transient->no_update[self::plugin_file()]); self::trace('transient_update_available', ['latest_version'=>$update->new_version]); } else { unset($transient->response[self::plugin_file()]); $transient->no_update[self::plugin_file()]=(object)['slug'=>self::SLUG,'plugin'=>self::plugin_file(),'new_version'=>PLUGIN_SUITE_VERSION]; self::trace('transient_no_update'); } return $transient; }
  public static function plugin_information($result,$action,$args) { if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) return $result; $release=self::latest_release(false); self::trace('plugins_api', ['latest_version'=>$release['version'] ?? '']); return (object)['name'=>'Rescue Plugin Suite','slug'=>self::SLUG,'version'=>$release['version'] ?? PLUGIN_SUITE_VERSION,'author'=>'Jordan Sutton | Webstax','homepage'=>self::releases_url(),'download_link'=>$release['download_url'] ?? '','sections'=>['description'=>'Unified rescue plugin suite.','changelog'=>!empty($release['body']) ? wp_kses_post(wpautop($release['body'])) : 'No release notes were returned by GitHub.']]; }

  private static function is_our_upgrade($hook_extra) { return ($hook_extra['type'] ?? '') === 'plugin' && in_array(self::plugin_file(), (array)($hook_extra['plugins'] ?? [$hook_extra['plugin'] ?? '']), true); }
  public static function package_options($options) { if (self::is_our_upgrade((array)($options['hook_extra'] ?? []))) self::trace('upgrader_package_options', ['package_url'=>(string)($options['package'] ?? ''),'destination'=>(string)($options['destination'] ?? '')]); return $options; }
  public static function pre_install($response,$hook_extra,$upgrader) { if (!self::is_our_upgrade((array)$hook_extra)) return $response; self::trace('upgrader_pre_install', ['destination'=>defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '']); if (is_wp_error($response)) self::trace('upgrader_pre_install_error', ['error'=>$response->get_error_message()]); return $response; }
  public static function source_selection($source,$remote_source,$upgrader,$hook_extra) {
    if (!self::is_our_upgrade((array)$hook_extra)) return $source;
    self::trace('upgrader_source_selection', ['source'=>$source,'remote_source'=>$remote_source]);
    if (basename(untrailingslashit($source)) !== self::PACKAGE_DIRECTORY || !is_readable(trailingslashit($source).'plugin-ui-suite.php')) { $error=new WP_Error('plugin_suite_invalid_package', 'The update ZIP must contain rescue-plugin-suite/plugin-ui-suite.php at its root.'); self::trace('zip_validation_failed', ['error'=>$error->get_error_message()]); return $error; }
    $installed_directory=self::plugin_directory(); if ($installed_directory !== '.' && $installed_directory !== self::PACKAGE_DIRECTORY) { $target=trailingslashit($remote_source).$installed_directory; if (!rename(untrailingslashit($source), $target)) { $error=new WP_Error('plugin_suite_package_rename_failed', 'Unable to map the verified package to the installed plugin directory.'); self::trace('zip_validation_failed', ['error'=>$error->get_error_message()]); return $error; } $source=trailingslashit($target); self::trace('source_mapped_to_installed_directory', ['destination_directory'=>$installed_directory]); }
    self::trace('zip_validated', ['source'=>$source]); return $source;
  }
  public static function after_upgrade($upgrader,$hook_extra) { if (!self::is_our_upgrade((array)$hook_extra)) return; $plugin_data=function_exists('get_plugin_data') && is_readable(PLUGIN_SUITE_PATH.'plugin-ui-suite.php') ? get_plugin_data(PLUGIN_SUITE_PATH.'plugin-ui-suite.php', false, false) : []; self::trace('upgrader_process_complete', ['post_update_version'=>$plugin_data['Version'] ?? '', 'final_plugin_metadata'=>$plugin_data, 'active'=>function_exists('is_plugin_active') ? (bool)is_plugin_active(self::plugin_file()) : null]); delete_transient(self::RELEASE_TRANSIENT); delete_site_transient('update_plugins'); delete_option(self::IGNORE_KEY); }

  public static function handle_ignore() { if (empty($_GET['plugin_suite_ignore_update']) || !current_user_can('update_plugins')) return; check_admin_referer('plugin_suite_ignore_update'); update_option(self::IGNORE_KEY,sanitize_text_field(wp_unslash($_GET['plugin_suite_ignore_update'])),false); wp_safe_redirect(remove_query_arg(['plugin_suite_ignore_update','_wpnonce'])); exit; }
  public static function handle_update_actions() { $updates=!empty($_GET['page']) && $_GET['page']==='plugin-ui-suite' && ($_GET['tab'] ?? '')==='updates'; if (!$updates || !current_user_can('update_plugins')) return; if (!empty($_POST['plugin_suite_check_updates'])) { check_admin_referer('plugin_suite_updates'); delete_transient(self::RELEASE_TRANSIENT); wp_update_plugins(); self::latest_release(true); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','checked'=>'1'],admin_url('options-general.php'))); exit; } if (isset($_POST['plugin_suite_auto_updates'])) { check_admin_referer('plugin_suite_updates'); update_option(self::AUTO_UPDATES_KEY,!empty($_POST['enabled'])?1:0,false); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','saved'=>'1'],admin_url('options-general.php'))); exit; } }
  public static function filter_auto_update($update,$item) { return (!empty($item->plugin) && $item->plugin===self::plugin_file()) ? (bool)get_option(self::AUTO_UPDATES_KEY,0) : $update; }
  public static function render_update_banner() { if (!current_user_can('update_plugins')) return; $release=self::latest_release(); if (empty($release['version']) || self::compare_versions($release['version'],PLUGIN_SUITE_VERSION,'<=') || get_option(self::IGNORE_KEY)===$release['version']) return; $url=wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(self::plugin_file())),'upgrade-plugin_'.self::plugin_file()); echo '<div class="notice notice-warning"><p><strong>Rescue Plugin Suite update available.</strong> Installed version: '.esc_html(PLUGIN_SUITE_VERSION).'. Latest version: '.esc_html($release['version']).'.</p><p>'.(!empty($release['download_url']) ? '<a class="button button-primary" href="'.esc_url($url).'">Update Now</a>' : '<strong>Update unavailable: a valid release ZIP was not found.</strong>').'</p></div>'; }
  public static function render_updates_panel($embedded=false) { if (!current_user_can('update_plugins')) return; $release=self::latest_release(!empty($_GET['checked'])); $diag=get_option(self::LAST_DIAGNOSTICS_KEY,[]); if (!$embedded) echo '<div class="wrap"><h1>Rescue Plugin Suite Updates</h1>'; echo '<p>Installed: <code>'.esc_html(PLUGIN_SUITE_VERSION).'</code> &mdash; Latest: <code>'.esc_html($release['version'] ?? 'Unknown').'</code></p>'; echo '<form method="post">'; wp_nonce_field('plugin_suite_updates'); echo '<button class="button" name="plugin_suite_check_updates" value="1">Check for updates now</button></form><h2>Update diagnostics</h2><table class="widefat striped"><tbody>'; foreach ((array)$diag as $key=>$value) echo '<tr><th>'.esc_html(ucwords(str_replace('_',' ',$key))).'</th><td><code>'.esc_html(is_scalar($value)?(string)$value:wp_json_encode($value)).'</code></td></tr>'; echo '</tbody></table><h2>Update trace</h2><pre>'.esc_html(wp_json_encode(get_option(self::LOG_KEY,[]),JSON_PRETTY_PRINT)).'</pre>'; if (!$embedded) echo '</div>'; }
  public static function render_updates_page() { self::render_updates_panel(false); }
}
