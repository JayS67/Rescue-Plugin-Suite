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
  const RELEASE_CHANNEL_KEY = 'plugin_ui_suite_release_channel';
  const LAST_DIAGNOSTICS_KEY = 'plugin_ui_suite_update_diagnostics_v2';
  const LOG_KEY = 'plugin_ui_suite_update_trace_v1';

  public static function repository() { return self::REPO; }
  public static function api_url() { return 'https://api.github.com/repos/' . self::repository() . '/releases'; }
  public static function releases_url() { return 'https://github.com/' . self::repository() . '/releases'; }
  public static function plugin_file() { return plugin_basename(PLUGIN_SUITE_PATH . 'plugin-ui-suite.php'); }
  private static function plugin_directory() { return dirname(self::plugin_file()); }

  public static function normalize_version($version) { $version = trim((string)$version); $version = preg_replace('/^refs\/tags\//i', '', $version); return preg_replace('/^v(?=\d)/i', '', $version); }
  public static function expected_asset_name($version) { return 'rescue-plugin-suite-v' . self::normalize_version($version) . '.zip'; }
  private static function release_channel() { return get_option(self::RELEASE_CHANNEL_KEY, 'beta') === 'stable' ? 'stable' : 'beta'; }
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
    if (!$force && ($cached = get_transient(self::RELEASE_TRANSIENT)) && is_array($cached)) return $cached;
    $diag = ['repository'=>self::repository(),'repository_visibility'=>'public (browser_download_url; no token)','api_url'=>self::api_url(),'installed_version'=>PLUGIN_SUITE_VERSION,'plugin_basename'=>self::plugin_file(),'slug'=>self::SLUG,'zip_root_expectation'=>self::PACKAGE_DIRECTORY,'release_channel'=>self::release_channel(),'last_check_time'=>'','latest_eligible_release'=>'','github_tag'=>'','release_prerelease'=>'','expected_asset_filename'=>'','selected_asset_filename'=>'','selected_package_url'=>'','assets_returned'=>[],'last_error'=>''];
    $response = wp_remote_get(self::api_url(), ['timeout'=>20,'headers'=>['Accept'=>'application/vnd.github+json','User-Agent'=>'Rescue-Plugin-Suite/'.PLUGIN_SUITE_VERSION]]);
    update_option(self::LAST_CHECK_KEY, current_time('mysql'), false); $diag['last_check_time']=current_time('mysql');
    if (is_wp_error($response)) { $diag['last_error']=$response->get_error_message(); self::diagnostics($diag); return []; }
    $code=(int)wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) { $diag['last_error']='GitHub returned HTTP '.$code; self::diagnostics($diag); return []; }
    $releases=json_decode((string)wp_remote_retrieve_body($response),true);
    if (!is_array($releases)) { $diag['last_error']='GitHub returned an invalid release response.'; self::diagnostics($diag); return []; }
    foreach ($releases as $candidate) {
      if (!is_array($candidate) || !empty($candidate['draft'])) continue;
      if (self::release_channel()==='stable' && !empty($candidate['prerelease'])) continue;
      $version=self::normalize_version($candidate['tag_name'] ?? ''); if ($version==='') continue;
      $expected=self::expected_asset_name($version); $assets=[]; $selected=[];
      foreach ((array)($candidate['assets'] ?? []) as $asset) { $name=(string)($asset['name'] ?? ''); if ($name!=='') $assets[]=$name; if ($name === $expected && !empty($asset['browser_download_url'])) $selected=$asset; }
      $diag['latest_eligible_release']=$version; $diag['github_tag']=(string)($candidate['tag_name'] ?? ''); $diag['release_prerelease']=!empty($candidate['prerelease'])?'yes':'no'; $diag['expected_asset_filename']=$expected; $diag['assets_returned']=$assets;
      if (!$selected) { $diag['last_error']='Release package unavailable: expected '.$expected.'. GitHub Source code archives are not valid WordPress update packages.'; self::diagnostics($diag); self::trace('release_package_unavailable',['expected_asset'=>$expected,'assets'=>$assets]); return []; }
      $url=esc_url_raw($selected['browser_download_url']); $release=['version'=>$version,'date'=>(string)($candidate['published_at'] ?? ''),'notes'=>wp_trim_words(wp_strip_all_tags((string)($candidate['body'] ?? '')),40),'url'=>esc_url_raw($candidate['html_url'] ?? self::releases_url()),'body'=>(string)($candidate['body'] ?? ''),'asset'=>$expected,'download_url'=>$url];
      $diag['selected_asset_filename']=$expected; $diag['selected_package_url']=$url; self::diagnostics($diag); set_transient(self::RELEASE_TRANSIENT,$release,6*HOUR_IN_SECONDS); self::trace('release_selected',['latest_version'=>$version,'asset'=>$expected,'package_url'=>$url]); return $release;
    }
    $diag['last_error']='No eligible published GitHub release was returned.'; self::diagnostics($diag); return [];
  }

  private static function update_object($force=false) { $release=self::latest_release($force); if (empty($release['version']) || empty($release['download_url']) || self::compare_versions($release['version'], PLUGIN_SUITE_VERSION, '<=')) return null; $update=(object)['id'=>self::repository(),'slug'=>self::SLUG,'plugin'=>self::plugin_file(),'new_version'=>$release['version'],'url'=>$release['url'] ?: self::releases_url(),'package'=>$release['download_url'],'tested'=>get_bloginfo('version'),'requires'=>'5.8','requires_php'=>'7.4']; self::trace('update_object_created', ['latest_version'=>$update->new_version,'package_url'=>$update->package]); return $update; }
  public static function inject_update_transient($transient) { if (!is_object($transient)) $transient=new stdClass(); $transient->response=(array)($transient->response ?? []); $transient->no_update=(array)($transient->no_update ?? []); $update=self::update_object(false); if ($update) { $transient->response[self::plugin_file()]=$update; unset($transient->no_update[self::plugin_file()]); self::trace('transient_update_available', ['latest_version'=>$update->new_version]); } else { unset($transient->response[self::plugin_file()]); $transient->no_update[self::plugin_file()]=(object)['slug'=>self::SLUG,'plugin'=>self::plugin_file(),'new_version'=>PLUGIN_SUITE_VERSION]; self::trace('transient_no_update'); } return $transient; }
  public static function plugin_information($result,$action,$args) { if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) return $result; $release=self::latest_release(false); self::trace('plugins_api', ['latest_version'=>$release['version'] ?? '']); return (object)['name'=>'Rescue Plugin Suite','slug'=>self::SLUG,'version'=>$release['version'] ?? PLUGIN_SUITE_VERSION,'author'=>'Jordan Sutton | Webstax','homepage'=>self::releases_url(),'download_link'=>$release['download_url'] ?? '','sections'=>['description'=>'Unified rescue plugin suite.','changelog'=>!empty($release['body']) ? wp_kses_post(wpautop($release['body'])) : 'No release notes were returned by GitHub.']]; }

  private static function is_our_upgrade($hook_extra) { return ($hook_extra['type'] ?? '') === 'plugin' && in_array(self::plugin_file(), (array)($hook_extra['plugins'] ?? [$hook_extra['plugin'] ?? '']), true); }
  public static function package_options($options) { if (self::is_our_upgrade((array)($options['hook_extra'] ?? []))) self::trace('upgrader_package_options', ['package_url'=>(string)($options['package'] ?? ''),'destination'=>(string)($options['destination'] ?? '')]); return $options; }
  public static function pre_install($response,$hook_extra,$upgrader) { if (!self::is_our_upgrade((array)$hook_extra)) return $response; self::trace('upgrader_pre_install', ['destination'=>defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '']); if (is_wp_error($response)) self::trace('upgrader_pre_install_error', ['error'=>$response->get_error_message()]); return $response; }
  public static function source_selection($source,$remote_source,$upgrader,$hook_extra) {
    if (!self::is_our_upgrade((array)$hook_extra)) return $source;
    $source=trailingslashit($source); $bootstrap=$source.'plugin-ui-suite.php';
    // WordPress may unpack a valid ZIP under a temporary wrapper. Inspect the
    // bootstrap rather than rejecting that harmless directory name.
    if (!is_readable($bootstrap)) { $children=glob($source.'*', GLOB_ONLYDIR); if (is_array($children) && count($children)===1 && is_readable(trailingslashit($children[0]).'plugin-ui-suite.php')) { $source=trailingslashit($children[0]); $bootstrap=$source.'plugin-ui-suite.php'; } }
    $header=is_readable($bootstrap) ? (string)file_get_contents($bootstrap,false,null,0,8192) : '';
    if (!preg_match('/Plugin Name:\s*Rescue Plugin Suite/i',$header) || !preg_match('/Version:\s*([^\r\n*]+)/i',$header,$match)) { $error=new WP_Error('plugin_suite_invalid_package','Release package unavailable or invalid: plugin-ui-suite.php must identify Rescue Plugin Suite.'); self::trace('zip_validation_failed',['error'=>$error->get_error_message()]); return $error; }
    $installed_directory=self::plugin_directory(); if ($installed_directory!=='.' && basename(untrailingslashit($source))!==$installed_directory) { $target=trailingslashit($remote_source).$installed_directory; if (file_exists($target) || !rename(untrailingslashit($source),$target)) { $error=new WP_Error('plugin_suite_package_rename_failed','Unable to map the verified package to the installed plugin directory.'); self::trace('zip_validation_failed',['error'=>$error->get_error_message()]); return $error; } $source=trailingslashit($target); }
    self::trace('zip_validated',['source'=>$source,'packaged_version'=>trim($match[1])]); return $source;
  }

  public static function after_upgrade($upgrader,$hook_extra) { if (!self::is_our_upgrade((array)$hook_extra)) return; $plugin_data=function_exists('get_plugin_data') && is_readable(PLUGIN_SUITE_PATH.'plugin-ui-suite.php') ? get_plugin_data(PLUGIN_SUITE_PATH.'plugin-ui-suite.php', false, false) : []; self::trace('upgrader_process_complete', ['post_update_version'=>$plugin_data['Version'] ?? '', 'final_plugin_metadata'=>$plugin_data, 'active'=>function_exists('is_plugin_active') ? (bool)is_plugin_active(self::plugin_file()) : null]); delete_transient(self::RELEASE_TRANSIENT); delete_site_transient('update_plugins'); delete_option(self::IGNORE_KEY); }

  public static function handle_ignore() { if (empty($_GET['plugin_suite_ignore_update']) || !current_user_can('update_plugins')) return; check_admin_referer('plugin_suite_ignore_update'); update_option(self::IGNORE_KEY,sanitize_text_field(wp_unslash($_GET['plugin_suite_ignore_update'])),false); wp_safe_redirect(remove_query_arg(['plugin_suite_ignore_update','_wpnonce'])); exit; }
  public static function handle_update_actions() { $updates=!empty($_GET['page']) && $_GET['page']==='plugin-ui-suite' && ($_GET['tab'] ?? '')==='updates'; if (!$updates || !current_user_can('update_plugins')) return; if (!empty($_POST['plugin_suite_check_updates'])) { check_admin_referer('plugin_suite_updates'); delete_transient(self::RELEASE_TRANSIENT); wp_update_plugins(); self::latest_release(true); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','checked'=>'1'],admin_url('options-general.php'))); exit; } if (!empty($_POST['plugin_suite_validate_release'])) { check_admin_referer('plugin_suite_updates'); self::validate_release_package(); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','validated'=>'1'],admin_url('options-general.php'))); exit; } if (isset($_POST['plugin_suite_auto_updates'])) { check_admin_referer('plugin_suite_updates'); update_option(self::AUTO_UPDATES_KEY,!empty($_POST['enabled'])?1:0,false); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','saved'=>'1'],admin_url('options-general.php'))); exit; } }
  private static function validate_release_package() {
    $release=self::latest_release(true); $result=['download_success'=>'no','http_status'=>'','archive_opens'=>'no','top_level_directory'=>'','bootstrap_exists'=>'no','plugin_name'=>'','packaged_version'=>'','suitable_for_wordpress_installation'=>'no'];
    if (empty($release['download_url'])) { $result['error']='Release package unavailable.'; self::diagnostics(array_merge((array)get_option(self::LAST_DIAGNOSTICS_KEY,[]),['release_package_validation'=>$result])); return; }
    $response=wp_remote_get($release['download_url'],['timeout'=>60]); $result['http_status']=(string)wp_remote_retrieve_response_code($response);
    if (is_wp_error($response) || (int)$result['http_status']!==200) { $result['error']=is_wp_error($response)?$response->get_error_message():'Download returned HTTP '.$result['http_status']; self::diagnostics(array_merge((array)get_option(self::LAST_DIAGNOSTICS_KEY,[]),['release_package_validation'=>$result])); return; }
    $tmp=wp_tempnam('rescue-plugin-suite-release.zip'); file_put_contents($tmp,wp_remote_retrieve_body($response));
    if (!class_exists('ZipArchive')) { $result['error']='ZipArchive is unavailable on this server.'; } else { $zip=new ZipArchive(); if ($zip->open($tmp)===true) { $result['archive_opens']='yes'; $names=[]; for($i=0;$i<$zip->numFiles;$i++) $names[]=$zip->getNameIndex($i); $roots=array_values(array_unique(array_filter(array_map(function($name){ return strtok($name,'/'); },$names)))); $result['top_level_directory']=$roots[0] ?? ''; $bootstrap='rescue-plugin-suite/plugin-ui-suite.php'; $index=$zip->locateName($bootstrap); if(count($roots)===1 && $result['top_level_directory']===self::PACKAGE_DIRECTORY && $index!==false) { $result['bootstrap_exists']='yes'; $header=$zip->getFromIndex($index); preg_match('/Plugin Name:\s*([^\r\n*]+)/i',$header,$name); preg_match('/Version:\s*([^\r\n*]+)/i',$header,$version); $result['plugin_name']=trim($name[1] ?? ''); $result['packaged_version']=trim($version[1] ?? ''); $result['suitable_for_wordpress_installation']=($result['plugin_name']==='Rescue Plugin Suite' && $result['packaged_version']===($release['version'] ?? ''))?'yes':'no'; } $zip->close(); } else $result['error']='Downloaded file is not a readable ZIP archive.'; }
    @unlink($tmp); self::diagnostics(array_merge((array)get_option(self::LAST_DIAGNOSTICS_KEY,[]),['release_package_validation'=>$result])); self::trace('release_package_validated',$result);
  }
  public static function filter_auto_update($update,$item) { return (!empty($item->plugin) && $item->plugin===self::plugin_file()) ? (bool)get_option(self::AUTO_UPDATES_KEY,0) : $update; }
  public static function render_update_banner() { if (!current_user_can('update_plugins')) return; $release=self::latest_release(); if (empty($release['version']) || self::compare_versions($release['version'],PLUGIN_SUITE_VERSION,'<=') || get_option(self::IGNORE_KEY)===$release['version']) return; $url=wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(self::plugin_file())),'upgrade-plugin_'.self::plugin_file()); echo '<div class="notice notice-warning"><p><strong>Rescue Plugin Suite update available.</strong> Installed version: '.esc_html(PLUGIN_SUITE_VERSION).'. Latest version: '.esc_html($release['version']).'.</p><p>'.(!empty($release['download_url']) ? '<a class="button button-primary" href="'.esc_url($url).'">Update Now</a>' : '<strong>Update unavailable: a valid release ZIP was not found.</strong>').'</p></div>'; }
  public static function render_updates_panel($embedded=false) { if (!current_user_can('update_plugins')) return; $release=self::latest_release(!empty($_GET['checked'])); $diag=get_option(self::LAST_DIAGNOSTICS_KEY,[]); if (!$embedded) echo '<div class="wrap"><h1>Rescue Plugin Suite Updates</h1>'; echo '<p>Installed: <code>'.esc_html(PLUGIN_SUITE_VERSION).'</code> &mdash; Latest: <code>'.esc_html($release['version'] ?? 'Unknown').'</code></p>'; echo '<form method="post">'; wp_nonce_field('plugin_suite_updates'); echo '<button class="button" name="plugin_suite_check_updates" value="1">Check for updates now</button> <button class="button" name="plugin_suite_validate_release" value="1">Validate release package</button></form><h2>Update diagnostics</h2><table class="widefat striped"><tbody>'; foreach ((array)$diag as $key=>$value) echo '<tr><th>'.esc_html(ucwords(str_replace('_',' ',$key))).'</th><td><code>'.esc_html(is_scalar($value)?(string)$value:wp_json_encode($value)).'</code></td></tr>'; echo '</tbody></table><h2>Update trace</h2><pre>'.esc_html(wp_json_encode(get_option(self::LOG_KEY,[]),JSON_PRETTY_PRINT)).'</pre>'; if (!$embedded) echo '</div>'; }
  public static function render_updates_page() { self::render_updates_panel(false); }
}
