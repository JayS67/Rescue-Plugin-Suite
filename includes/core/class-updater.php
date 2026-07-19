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
  const LOG_KEY = 'plugin_ui_suite_update_trace_v2';
  const TRANSIENT_STATE_KEY = 'plugin_ui_suite_native_update_state_v1';
  const REFRESH_NOTICE_KEY = 'plugin_ui_suite_update_refresh_notice';
  const UPDATE_LOG_FILE = 'rescue-plugin-suite-update.log';

  /** Request-local update context. It is deliberately never used to load replacement code. */
  private static $update_context = [];
  private static $shutdown_registered = false;

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
    self::$update_context = array_merge(self::$update_context, ['stage'=>$stage], array_intersect_key((array)$data, array_flip(['source', 'destination'])));
    $entry = array_merge(['time'=>current_time('mysql'), 'stage'=>$stage, 'installed_version'=>PLUGIN_SUITE_VERSION, 'plugin_basename'=>self::plugin_file(), 'slug'=>self::SLUG], $data);
    $log = get_option(self::LOG_KEY, []); if (!is_array($log)) $log = []; $log[] = $entry;
    update_option(self::LOG_KEY, array_slice($log, -100), false);
    self::write_update_log($entry);
  }
  /** This logger is best-effort: a permissions or disk error must not affect an update. */
  private static function write_update_log($entry) {
    try {
      if (!function_exists('wp_upload_dir') || !function_exists('wp_json_encode')) return;
      $uploads = wp_upload_dir(null, false); $directory = is_array($uploads) ? ($uploads['basedir'] ?? '') : '';
      if (!$directory || !empty($uploads['error'])) return;
      $line = wp_json_encode($entry); if (!is_string($line)) return;
      @file_put_contents(trailingslashit($directory) . self::UPDATE_LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $ignored) { /* Diagnostic logging must never break WordPress. */ }
  }
  private static function update_request_context() {
    return (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'update.php' && isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['upgrade-plugin', 'update-selected'], true));
  }
  public static function register_shutdown_diagnostics() {
    if (self::$shutdown_registered) return; self::$shutdown_registered = true;
    register_shutdown_function([__CLASS__, 'capture_shutdown_error']);
  }
  public static function capture_shutdown_error() {
    try {
      $error = error_get_last(); $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
      if (!$error || !in_array((int)($error['type'] ?? 0), $fatal_types, true)) return;
      self::write_update_log(['time'=>gmdate('Y-m-d H:i:s'), 'stage'=>self::$update_context['stage'] ?? 'unknown', 'event'=>'shutdown_fatal', 'php_error_type'=>(int)$error['type'], 'message'=>(string)$error['message'], 'file'=>(string)$error['file'], 'line'=>(int)$error['line'], 'request_uri'=>(string)($_SERVER['REQUEST_URI'] ?? ''), 'installed_version'=>defined('PLUGIN_SUITE_VERSION') ? PLUGIN_SUITE_VERSION : '', 'source'=>self::$update_context['source'] ?? '', 'destination'=>self::$update_context['destination'] ?? '', 'active'=>function_exists('is_plugin_active') ? (bool)is_plugin_active(self::plugin_file()) : null]);
    } catch (Throwable $ignored) { /* Never turn shutdown diagnostics into a second failure. */ }
  }
  private static function callback_stage($callback, $when, $data = []) { self::trace('updater_callback_' . $when, array_merge(['callback'=>$callback], $data)); }
  private static function diagnostics($values) { update_option(self::LAST_DIAGNOSTICS_KEY, $values, false); }

  public static function init() {
    add_action('admin_init', [__CLASS__, 'handle_ignore']); add_action('admin_init', [__CLASS__, 'handle_update_actions']); add_action('admin_notices', [__CLASS__, 'render_update_banner']);
    // This updater is the sole update-metadata writer. Do not initialise PUC here.
    add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update_transient'], 10, 1);
    add_filter('pre_site_transient_update_plugins', [__CLASS__, 'serve_native_update_transient'], 10, 1);
    add_filter('site_transient_update_plugins', [__CLASS__, 'observe_native_update_transient'], 10, 1);
    add_action('admin_init', [__CLASS__, 'trace_update_request'], 1);
    if (self::update_request_context()) self::register_shutdown_diagnostics();
    add_action('admin_init', [__CLASS__, 'audit_update_hooks'], 999);
    add_filter('plugins_api', [__CLASS__, 'plugin_information'], 10, 3);
    add_filter('auto_update_plugin', [__CLASS__, 'filter_auto_update'], 10, 2);
    add_filter('upgrader_package_options', [__CLASS__, 'package_options']);
    add_filter('upgrader_pre_install', [__CLASS__, 'pre_install'], 10, 3);
    add_action('upgrader_process_complete', [__CLASS__, 'after_upgrade'], 10, 2);
  }

  /** Evaluate every API candidate; selection is semantic-version based, never API order. */
  public static function select_release_candidate(array $releases, $installed_version, $channel) {
    $installed = self::normalize_version($installed_version);
    $channel = $channel === 'stable' ? 'stable' : 'beta';
    $selected = [];
    $evaluations = [];

    foreach ($releases as $candidate) {
      if (!is_array($candidate)) continue;
      $tag = (string)($candidate['tag_name'] ?? '');
      $version = self::normalize_version($tag);
      $draft = !empty($candidate['draft']);
      $prerelease = !empty($candidate['prerelease']);
      $expected = $version === '' ? '' : self::expected_asset_name($version);
      $assets = [];
      $package = [];
      foreach ((array)($candidate['assets'] ?? []) as $asset) {
        $name = (string)($asset['name'] ?? '');
        if ($name !== '') $assets[] = $name;
        if ($name === $expected && !empty($asset['browser_download_url'])) $package = $asset;
      }
      $channel_eligible = !$draft && ($channel !== 'stable' || !$prerelease);
      $newer = $version !== '' && self::compare_versions($version, $installed, '>');
      $reason = '';
      if ($version === '') $reason = 'Invalid or missing release tag';
      elseif ($draft) $reason = 'Draft release';
      elseif ($channel === 'stable' && $prerelease) $reason = 'Prerelease excluded on stable channel';
      elseif (!$package) $reason = 'Exact release package missing';
      elseif (!$newer) $reason = 'Not newer than installed version';
      elseif ($selected && !self::compare_versions($version, $selected['version'], '>')) $reason = 'A semantically newer eligible release is selected';

      $evaluation = [
        'tag'=>$tag, 'normalised_version'=>$version, 'draft'=>$draft, 'prerelease'=>$prerelease,
        'expected_asset'=>$expected, 'assets'=>$assets, 'asset_present'=>(bool)$package,
        'release_channel'=>$channel, 'channel_eligible'=>$channel_eligible,
        'comparison'=>$version === '' ? '' : $version . ' ' . (self::compare_versions($version, $installed, '>') ? '>' : (self::compare_versions($version, $installed, '<') ? '<' : '=')) . ' ' . $installed,
        'newer_than_installed'=>$newer, 'selected'=>false, 'rejection_reason'=>$reason,
      ];
      if ($reason === '') {
        if ($selected) {
          foreach ($evaluations as &$previous) if (!empty($previous['selected'])) { $previous['selected'] = false; $previous['rejection_reason'] = 'A semantically newer eligible release is selected'; }
          unset($previous);
        }
        $selected = ['version'=>$version, 'date'=>(string)($candidate['published_at'] ?? ''), 'notes'=>wp_trim_words(wp_strip_all_tags((string)($candidate['body'] ?? '')),40), 'url'=>esc_url_raw($candidate['html_url'] ?? self::releases_url()), 'body'=>(string)($candidate['body'] ?? ''), 'asset'=>$expected, 'download_url'=>esc_url_raw($package['browser_download_url'])];
        $evaluation['selected'] = true;
      }
      $evaluations[] = $evaluation;
    }
    return ['release'=>$selected, 'candidates'=>$evaluations];
  }

  public static function latest_release($force=false) {
    if (!$force && ($cached = get_transient(self::RELEASE_TRANSIENT)) && is_array($cached)) return $cached;
    $diag = ['repository'=>self::repository(),'repository_visibility'=>'public (browser_download_url; no token)','api_url'=>self::api_url(),'installed_version'=>PLUGIN_SUITE_VERSION,'plugin_basename'=>self::plugin_file(),'slug'=>self::SLUG,'zip_root_expectation'=>self::PACKAGE_DIRECTORY,'release_channel'=>self::release_channel(),'last_check_time'=>'','api_response_status'=>'','latest_eligible_release'=>'','github_tag'=>'','release_prerelease'=>'','expected_asset_filename'=>'','selected_asset_filename'=>'','selected_package_url'=>'','assets_returned'=>[],'candidate_evaluations'=>[],'last_error'=>''];
    $response = wp_remote_get(self::api_url(), ['timeout'=>20,'headers'=>['Accept'=>'application/vnd.github+json','User-Agent'=>'Rescue-Plugin-Suite/'.PLUGIN_SUITE_VERSION]]);
    update_option(self::LAST_CHECK_KEY, current_time('mysql'), false); $diag['last_check_time']=current_time('mysql');
    if (is_wp_error($response)) { $diag['last_error']=$response->get_error_message(); self::diagnostics($diag); return []; }
    $code=(int)wp_remote_retrieve_response_code($response); $diag['api_response_status'] = $code;
    if ($code < 200 || $code >= 300) { $diag['last_error']='GitHub returned HTTP '.$code; self::diagnostics($diag); return []; }
    $releases=json_decode((string)wp_remote_retrieve_body($response),true);
    if (!is_array($releases)) { $diag['last_error']='GitHub returned an invalid release response.'; self::diagnostics($diag); return []; }
    $result = self::select_release_candidate($releases, PLUGIN_SUITE_VERSION, self::release_channel());
    $release = $result['release']; $diag['candidate_evaluations'] = $result['candidates'];
    if (!$release) { $diag['last_error']='No installable eligible GitHub release newer than the installed version was returned.'; self::diagnostics($diag); return []; }
    $diag['latest_eligible_release']=$release['version']; $diag['github_tag']='v'.$release['version']; $diag['expected_asset_filename']=$release['asset']; $diag['selected_asset_filename']=$release['asset']; $diag['selected_package_url']=$release['download_url'];
    self::diagnostics($diag); set_transient(self::RELEASE_TRANSIENT,$release,6*HOUR_IN_SECONDS); self::trace('release_selected',['latest_version'=>$release['version'],'asset'=>$release['asset'],'package_url'=>$release['download_url'],'candidate_evaluations'=>$result['candidates']]); return $release;
  }

  private static function clear_update_caches() {
    delete_transient(self::RELEASE_TRANSIENT);
    delete_option(self::TRANSIENT_STATE_KEY);
    delete_option(self::LAST_DIAGNOSTICS_KEY);
    delete_site_transient('update_plugins');
  }

  private static function update_object($force=false) { $release=self::latest_release($force); if (empty($release['version']) || empty($release['download_url']) || self::compare_versions($release['version'], PLUGIN_SUITE_VERSION, '<=')) return null; $update=(object)['id'=>'github.com/'.self::repository(),'slug'=>self::SLUG,'plugin'=>self::plugin_file(),'new_version'=>$release['version'],'url'=>$release['url'] ?: self::releases_url(),'package'=>$release['download_url'],'tested'=>get_bloginfo('version'),'requires'=>'5.8','requires_php'=>'7.4']; self::trace('update_object_created', ['latest_version'=>$update->new_version,'package_url'=>$update->package]); return $update; }
  private static function update_state($update = null) { if ($update === null) $update=self::update_object(false); $state=['plugin'=>self::plugin_file(),'updated_at'=>current_time('mysql'),'update'=>$update ? (array)$update : []]; update_option(self::TRANSIENT_STATE_KEY,$state,false); return $state; }
  private static function state_update_object() { $state=get_option(self::TRANSIENT_STATE_KEY,[]); $update=is_array($state) ? ($state['update'] ?? []) : []; if (!is_array($update) || empty($update['plugin']) || $update['plugin']!==self::plugin_file() || empty($update['package']) || empty($update['new_version']) || !self::compare_versions($update['new_version'],PLUGIN_SUITE_VERSION,'>')) return null; return (object)$update; }
  private static function record_removal($source) { $state=get_option(self::TRANSIENT_STATE_KEY,[]); if (!is_array($state)) $state=[]; $state['last_removal_source']=$source; $state['last_removal_time']=current_time('mysql'); update_option(self::TRANSIENT_STATE_KEY,$state,false); }
  private static function merge_update($transient, $update, $source) { if (!is_object($transient)) $transient=new stdClass(); $transient->response=(array)($transient->response ?? []); $transient->no_update=(array)($transient->no_update ?? []); $key=self::plugin_file(); if ($update) { $transient->response[$key]=$update; unset($transient->no_update[$key]); self::trace('native_transient_written',['source'=>$source,'response_key'=>$key,'new_version'=>$update->new_version,'package_url'=>$update->package]); } else { unset($transient->response[$key]); unset($transient->no_update[$key]); self::record_removal($source); self::trace('native_transient_removed',['source'=>$source,'response_key'=>$key]); } return $transient; }
  public static function inject_update_transient($transient) { $update=self::update_object(false); self::update_state($update); return self::merge_update($transient,$update,'pre_set_site_transient_update_plugins'); }
  /** Supply the saved native object on reads without recursively reading this transient or calling GitHub. */
  public static function serve_native_update_transient($pre) { if ($pre !== false) return $pre; $raw=get_site_option('_site_transient_update_plugins', false); if (!is_object($raw)) return false; $update=self::state_update_object(); if (!$update) return false; $context=self::transient_read_context(); self::trace('native_transient_read',['source'=>'pre_site_transient_update_plugins','context'=>$context,'response_key'=>self::plugin_file(),'new_version'=>$update->new_version,'package_url'=>$update->package]); return self::merge_update($raw,$update,'pre_site_transient_update_plugins'); }
  public static function observe_native_update_transient($transient) { self::trace('native_transient_observed',['source'=>'site_transient_update_plugins','context'=>self::transient_read_context(),'entry'=>self::safe_native_entry($transient)]); return $transient; }
  private static function transient_read_context() { $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,12); foreach ($trace as $frame) { if (($frame['class'] ?? '')==='Plugin_Upgrader' && ($frame['function'] ?? '')==='upgrade') return 'Plugin_Upgrader::upgrade'; } return (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow']==='update.php') ? 'wp-admin/update.php' : 'normal'; }
  private static function safe_native_entry($transient) { $entry=is_object($transient) && isset($transient->response) && is_array($transient->response) ? ($transient->response[self::plugin_file()] ?? null) : null; return $entry ? ['id'=>$entry->id ?? '','slug'=>$entry->slug ?? '','plugin'=>$entry->plugin ?? '','new_version'=>$entry->new_version ?? '','package'=>$entry->package ?? '','url'=>$entry->url ?? ''] : []; }
  public static function trace_update_request() { if ((isset($GLOBALS['pagenow']) && $GLOBALS['pagenow']==='update.php')) self::trace('wp_admin_update_php_start',['entry'=>self::safe_native_entry(get_site_transient('update_plugins'))]); }
  public static function audit_update_hooks() { global $wp_filter; $hooks=['pre_set_site_transient_update_plugins','pre_site_transient_update_plugins','site_transient_update_plugins','wp_update_plugins','wp_clean_plugins_cache','wp_clean_update_cache']; foreach (array_keys((array)$wp_filter) as $hook) if (strpos($hook,'update_plugins_')===0) $hooks[]=$hook; $hooks=array_unique($hooks); $audit=[]; foreach ($hooks as $hook) { $audit[$hook]=[]; if (empty($wp_filter[$hook]) || !is_object($wp_filter[$hook])) continue; foreach ((array)$wp_filter[$hook]->callbacks as $priority=>$callbacks) foreach ((array)$callbacks as $callback) { $fn=$callback['function']; $name=is_string($fn)?$fn:(is_array($fn)?(is_object($fn[0])?get_class($fn[0]):$fn[0]).'::'.$fn[1]:'closure'); $audit[$hook][]=['priority'=>(int)$priority,'callback'=>$name]; } } self::diagnostics(array_merge((array)get_option(self::LAST_DIAGNOSTICS_KEY,[]),['filter_execution_order'=>$audit,'competing_updater'=>'Custom GitHub updater only; Plugin Update Checker is not initialized or used.'])); }
  public static function native_update_entry($point) { $transient=get_site_transient('update_plugins'); $entry=is_object($transient) && isset($transient->response) && is_array($transient->response) ? ($transient->response[self::plugin_file()] ?? null) : null; self::trace($point,['entry'=>self::safe_native_entry($transient)]); return $entry; }
  public static function plugin_information($result,$action,$args) { if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) return $result; $release=self::latest_release(false); self::trace('plugins_api', ['latest_version'=>$release['version'] ?? '']); return (object)['name'=>'Rescue Plugin Suite','slug'=>self::SLUG,'version'=>$release['version'] ?? PLUGIN_SUITE_VERSION,'author'=>'Jordan Sutton | Webstax','homepage'=>self::releases_url(),'download_link'=>$release['download_url'] ?? '','sections'=>['description'=>'Unified rescue plugin suite.','changelog'=>!empty($release['body']) ? wp_kses_post(wpautop($release['body'])) : 'No release notes were returned by GitHub.']]; }

  private static function is_our_upgrade($hook_extra) { return ($hook_extra['type'] ?? '') === 'plugin' && in_array(self::plugin_file(), (array)($hook_extra['plugins'] ?? [$hook_extra['plugin'] ?? '']), true); }
  public static function package_options($options) {
    if (!self::is_our_upgrade((array)($options['hook_extra'] ?? []))) return $options;
    self::callback_stage(__FUNCTION__, 'before', ['package_url'=>(string)($options['package'] ?? ''), 'destination'=>(string)($options['destination'] ?? '')]);
    self::callback_stage(__FUNCTION__, 'after');
    return $options;
  }
  public static function pre_install($response,$hook_extra,$upgrader) {
    if (!self::is_our_upgrade((array)$hook_extra)) return $response;
    self::callback_stage(__FUNCTION__, 'before', ['destination'=>defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '']);
    self::callback_stage(__FUNCTION__, 'after', is_wp_error($response) ? ['error'=>$response->get_error_message()] : []);
    return $response;
  }
  /**
   * The release ZIP has the canonical rescue-plugin-suite/ root. Do not attach
   * upgrader_source_selection: WordPress core must own moving/replacing files.
   */
  private static function read_plugin_header($file) {
    $header = is_readable($file) ? (string)@file_get_contents($file, false, null, 0, 8192) : '';
    preg_match('/^[ \t\/*#@]*Version:\s*([^\r\n*]+)/mi', $header, $version);
    return ['Version'=>trim($version[1] ?? '')];
  }
  public static function after_upgrade($upgrader,$hook_extra) {
    if (!self::is_our_upgrade((array)$hook_extra)) return;
    self::callback_stage(__FUNCTION__, 'before');
    // Header parsing is plain text only; never execute the replacement bootstrap during this request.
    $plugin_data = self::read_plugin_header(PLUGIN_SUITE_PATH . 'plugin-ui-suite.php');
    self::callback_stage(__FUNCTION__, 'after', ['post_update_version'=>$plugin_data['Version'] ?? '', 'active'=>function_exists('is_plugin_active') ? (bool)is_plugin_active(self::plugin_file()) : null]);
  }

  public static function handle_ignore() { if (empty($_GET['plugin_suite_ignore_update']) || !current_user_can('update_plugins')) return; check_admin_referer('plugin_suite_ignore_update'); update_option(self::IGNORE_KEY,sanitize_text_field(wp_unslash($_GET['plugin_suite_ignore_update'])),false); wp_safe_redirect(remove_query_arg(['plugin_suite_ignore_update','_wpnonce'])); exit; }
  public static function handle_update_actions() { $updates=!empty($_GET['page']) && $_GET['page']==='plugin-ui-suite' && ($_GET['tab'] ?? '')==='updates'; if (!$updates || !current_user_can('update_plugins')) return; if (!empty($_POST['plugin_suite_check_updates'])) { check_admin_referer('plugin_suite_updates'); self::trace('manual_refresh_start'); self::clear_update_caches(); self::record_removal('manual_refresh'); self::trace('native_transient_removed',['source'=>'manual_refresh']); self::latest_release(true); wp_update_plugins(); $entry=self::native_update_entry('manual_refresh_verified'); $args=['page'=>'plugin-ui-suite','tab'=>'updates','checked'=>'1']; if (!$entry || empty($entry->package) || empty($entry->new_version) || ($entry->plugin ?? '')!==self::plugin_file()) { set_transient(self::REFRESH_NOTICE_KEY,'Update metadata has not been registered with WordPress. Refresh update data.',MINUTE_IN_SECONDS); $args['metadata']='missing'; } wp_safe_redirect(add_query_arg($args,admin_url('options-general.php'))); exit; } if (!empty($_POST['plugin_suite_validate_release'])) { check_admin_referer('plugin_suite_updates'); self::validate_release_package(); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','validated'=>'1'],admin_url('options-general.php'))); exit; } if (isset($_POST['plugin_suite_auto_updates'])) { check_admin_referer('plugin_suite_updates'); update_option(self::AUTO_UPDATES_KEY,!empty($_POST['enabled'])?1:0,false); wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates','saved'=>'1'],admin_url('options-general.php'))); exit; } }
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
  public static function render_update_banner() { if (!current_user_can('update_plugins')) return; $entry=self::native_update_entry('before_update_now_url'); if (!$entry || empty($entry->package) || empty($entry->new_version) || ($entry->plugin ?? '')!==self::plugin_file() || !self::compare_versions($entry->new_version,PLUGIN_SUITE_VERSION,'>')) { $release=self::latest_release(false); if (!empty($release['version']) && self::compare_versions($release['version'],PLUGIN_SUITE_VERSION,'>')) echo '<div class="notice notice-error"><p>Update metadata has not been registered with WordPress. Refresh update data.</p></div>'; return; } if (get_option(self::IGNORE_KEY)===$entry->new_version) return; $url=wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(self::plugin_file())),'upgrade-plugin_'.self::plugin_file()); echo '<div class="notice notice-warning"><p><strong>Rescue Plugin Suite update available.</strong> Installed version: '.esc_html(PLUGIN_SUITE_VERSION).'. Latest version: '.esc_html($entry->new_version).'.</p><p><a class="button button-primary" href="'.esc_url($url).'">Update Now</a></p></div>'; }
  private static function render_candidate_evaluations($candidates) {
    if (!is_array($candidates) || !$candidates) return;
    echo '<h3>Release candidate evaluation</h3><table class="widefat striped"><thead><tr><th>Tag</th><th>Normalised version</th><th>Draft</th><th>Prerelease</th><th>Expected asset</th><th>Asset present</th><th>Channel eligible</th><th>Newer than installed</th><th>Selected</th><th>Rejection reason</th></tr></thead><tbody>';
    foreach ($candidates as $candidate) { echo '<tr><td>'.esc_html($candidate['tag'] ?? '').'</td><td>'.esc_html($candidate['normalised_version'] ?? '').'</td><td>'.esc_html(!empty($candidate['draft'])?'true':'false').'</td><td>'.esc_html(!empty($candidate['prerelease'])?'true':'false').'</td><td>'.esc_html($candidate['expected_asset'] ?? '').'</td><td>'.esc_html(!empty($candidate['asset_present'])?'true':'false').'</td><td>'.esc_html(!empty($candidate['channel_eligible'])?'true':'false').'</td><td>'.esc_html(!empty($candidate['newer_than_installed'])?'true':'false').'</td><td>'.esc_html(!empty($candidate['selected'])?'true':'false').'</td><td>'.esc_html($candidate['rejection_reason'] ?? '').'</td></tr>'; }
    echo '</tbody></table>';
  }

  public static function render_updates_panel($embedded=false) { if (!current_user_can('update_plugins')) return; $release=self::latest_release(!empty($_GET['checked'])); $native=self::native_update_entry('render_updates_tab'); $raw=get_site_option('_site_transient_update_plugins',false); $state=(array)get_option(self::TRANSIENT_STATE_KEY,[]); $diag=array_merge((array)get_option(self::LAST_DIAGNOSTICS_KEY,[]),['active_plugin_basename'=>self::plugin_file(),'native_transient_exists'=>is_object($raw)?'yes':'no','native_response_key_exists'=>$native?'yes':'no','native_response_new_version'=>$native->new_version ?? '','native_response_package_url'=>$native->package ?? '','native_no_update_key_exists'=>is_object($raw) && isset($raw->no_update[self::plugin_file()])?'yes':'no','custom_selected_version'=>$release['version'] ?? '','custom_selected_package'=>$release['download_url'] ?? '','update_object_source'=>'Plugin_UI_Suite_Updater authoritative GitHub release cache','last_transient_write_time'=>$state['updated_at'] ?? '','last_transient_removal_source'=>$state['last_removal_source'] ?? '']); self::diagnostics($diag); $notice=get_transient(self::REFRESH_NOTICE_KEY); if ($notice) { delete_transient(self::REFRESH_NOTICE_KEY); echo '<div class="notice notice-error"><p>'.esc_html($notice).'</p></div>'; } if (!$embedded) echo '<div class="wrap"><h1>Rescue Plugin Suite Updates</h1>'; echo '<p>Installed: <code>'.esc_html(PLUGIN_SUITE_VERSION).'</code> &mdash; Latest: <code>'.esc_html($release['version'] ?? 'Unknown').'</code></p>'; if ($native) echo '<details><summary>Native update object</summary><pre>'.esc_html(wp_json_encode(self::safe_native_entry((object)['response'=>[self::plugin_file()=>$native]]),JSON_PRETTY_PRINT)).'</pre></details>'; echo '<form method="post">'; wp_nonce_field('plugin_suite_updates'); echo '<button class="button" name="plugin_suite_check_updates" value="1">Check for updates now</button> <button class="button" name="plugin_suite_validate_release" value="1">Validate release package</button></form><h2>Update diagnostics</h2><table class="widefat striped"><tbody>'; foreach ((array)$diag as $key=>$value) echo '<tr><th>'.esc_html(ucwords(str_replace('_',' ',$key))).'</th><td><code>'.esc_html(is_scalar($value)?(string)$value:wp_json_encode($value)).'</code></td></tr>'; echo '</tbody></table>'; self::render_candidate_evaluations($diag['candidate_evaluations'] ?? []); echo '<h2>Update trace</h2><pre>'.esc_html(wp_json_encode(get_option(self::LOG_KEY,[]),JSON_PRETTY_PRINT)).'</pre>'; if (!$embedded) echo '</div>'; }
  public static function render_updates_page() { self::render_updates_panel(false); }
}
