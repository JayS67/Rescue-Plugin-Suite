<?php
/**
 * Module: Rescue Plugin Suite Data Proxy
 * Description: Proxies rescue management JSON endpoints so credentials never reach browsers and animal images are served safely.
 * Version: 1.3.6
 */

if (!defined('ABSPATH')) exit;

/**
 * Return the one canonical ASM connection configuration.
 *
 * Environment variables and wp-config.php constants intentionally override the
 * saved Integration values so secrets can remain outside the database. The
 * saved proxy section is the editable Integration source when no override is
 * present. Do not add feature-specific ASM settings: every ASM consumer must
 * use these helpers.
 */
function plugin_asm_configuration() {
  $saved = get_option('plugin_ui_suite_settings_v83', []);
  $proxy = is_array($saved) && isset($saved['proxy']) && is_array($saved['proxy']) ? $saved['proxy'] : [];
  $fields = [
    'ASM_BASE_URL' => ['key' => 'base_url', 'default' => 'https://service.sheltermanager.com/asmservice'],
    'ASM_ACCOUNT' => ['key' => 'account', 'default' => ''],
    'ASM_USERNAME' => ['key' => 'username', 'default' => ''],
    'ASM_PASSWORD' => ['key' => 'password', 'default' => ''],
    // ASM's public service endpoints do not currently use an API key, but
    // keeping this slot here prevents a second configuration path if that
    // changes for a supported deployment.
    'ASM_API_KEY' => ['key' => 'api_key', 'default' => ''],
  ];
  $configuration = [];
  foreach ($fields as $constant => $field) {
    $environment = getenv($constant);
    if ($environment !== false && $environment !== '') {
      $configuration[$field['key']] = ['value' => (string)$environment, 'source' => 'environment'];
    } elseif (defined($constant) && constant($constant) !== '') {
      $configuration[$field['key']] = ['value' => (string)constant($constant), 'source' => 'constant'];
    } elseif (($proxy[$field['key']] ?? '') !== '') {
      $configuration[$field['key']] = ['value' => (string)$proxy[$field['key']], 'source' => 'saved_setting'];
    } else {
      $configuration[$field['key']] = ['value' => $field['default'], 'source' => 'default'];
    }
  }
  return $configuration;
}

function plugin_asm_get_secret($name, $default = '') {
  $env = getenv($name);
  if ($env !== false && $env !== '') return $env;
  if (defined($name) && constant($name) !== '') return constant($name);
  $keys = ['ASM_BASE_URL' => 'base_url', 'ASM_ACCOUNT' => 'account', 'ASM_USERNAME' => 'username', 'ASM_PASSWORD' => 'password', 'ASM_API_KEY' => 'api_key'];
  $configuration = plugin_asm_configuration();
  return isset($keys[$name], $configuration[$keys[$name]]) ? $configuration[$keys[$name]]['value'] : $default;
}

function plugin_asm_base_url() { return plugin_asm_get_secret('ASM_BASE_URL', 'https://service.sheltermanager.com/asmservice'); }
function plugin_asm_account()  { return plugin_asm_get_secret('ASM_ACCOUNT', ''); }
function plugin_asm_user()     { return plugin_asm_get_secret('ASM_USERNAME', ''); }
function plugin_asm_pass()     { return plugin_asm_get_secret('ASM_PASSWORD', ''); }
function plugin_asm_api_key()  { return plugin_asm_get_secret('ASM_API_KEY', ''); }

function plugin_asm_online_form_url($form_id) {
  return add_query_arg([
    'account' => plugin_asm_account(),
    'method' => 'online_form_js',
    'formid' => $form_id,
  ], plugin_asm_base_url());
}

function plugin_asm_http_get($params, $opts = []) {
  $base = plugin_asm_base_url();
  $url_params = array_merge(['account' => plugin_asm_account()], $params);
  $url = add_query_arg($url_params, $base);

  $defaults = [
    'timeout'     => 25,
    'redirection' => 5,
    'headers'     => [
      'User-Agent' => 'WordPress Rescue Plugin Suite Proxy',
      'Accept'     => 'application/json,*/*;q=0.8',
    ],
  ];

  $res = wp_remote_get($url, array_merge($defaults, $opts));

  if (is_wp_error($res)) return $res;

  $code = wp_remote_retrieve_response_code($res);
  $body = wp_remote_retrieve_body($res);

  if ($code < 200 || $code >= 300) {
    return new WP_Error('asm_error', 'ASM request failed', [
      'status' => $code,
      'body'   => $body,
      'url'    => $url,
    ]);
  }

  return $res;
}

function plugin_asm_error_response($err) {
  if (!is_wp_error($err)) {
    return new WP_REST_Response(['error' => 'Unknown error'], 500);
  }

  $data = $err->get_error_data();
  $status = isset($data['status']) ? (int)$data['status'] : 500;
  $body = isset($data['body']) ? (string)$data['body'] : '';
  $url  = isset($data['url']) ? (string)$data['url'] : '';

  return new WP_REST_Response([
    'error'  => $err->get_error_message(),
    'status' => $status,
    'detail' => mb_substr($body, 0, 600),
    'hint'   => $url ? 'ASM URL built successfully (hidden if you prefer)' : '',
  ], 500);
}

function plugin_cache_image_to_uploads($content_type, $bytes, $animalid = '', $seq = '') {
  if (!function_exists('wp_upload_dir') || !is_string($bytes) || $bytes === '' || stripos((string)$content_type, 'image/') !== 0) return;
  $uploads = wp_upload_dir();
  if (empty($uploads['basedir'])) return;
  $dir = trailingslashit($uploads['basedir']) . 'asm-plugin-suite-cache';
  if (!wp_mkdir_p($dir)) return;
  $ext = (stripos($content_type, 'png') !== false) ? 'png' : ((stripos($content_type, 'webp') !== false) ? 'webp' : 'jpg');
  $name = sanitize_file_name(($animalid ?: 'animal') . '-' . ($seq ?: '1') . '.' . $ext);
  $path = trailingslashit($dir) . $name;
  if (!file_exists($path)) {
    file_put_contents($path, $bytes, LOCK_EX);
    if (function_exists('wp_get_image_editor')) {
      $editor = wp_get_image_editor($path);
      if (!is_wp_error($editor)) {
        $editor->resize(900, 900, false);
        $editor->save(trailingslashit($dir) . preg_replace('/\.' . preg_quote($ext, '/') . '$/', '-900.' . $ext, $name));
        if (function_exists('imagewebp') && $ext !== 'webp') {
          $webp = wp_get_image_editor($path);
          if (!is_wp_error($webp)) $webp->save(trailingslashit($dir) . preg_replace('/\.' . preg_quote($ext, '/') . '$/', '.webp', $name), 'image/webp');
        }
      }
    }
  }
}

function plugin_stream_image_response($content_type, $bytes) {
  while (ob_get_level()) { @ob_end_clean(); }
  @ini_set('zlib.output_compression', '0');

  status_header(200);
  header('Content-Type: ' . $content_type);
  if (function_exists('plugin_suite_cache_bypass') && plugin_suite_cache_bypass()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
  } else {
    header('Cache-Control: public, max-age=300');
  }
  header('X-Content-Type-Options: nosniff');

  echo $bytes;
  exit;
}

function plugin_fetch_asm_image($animalid, $seq, &$debug_out = null) {
  $url = add_query_arg([
    'account'  => plugin_asm_account(),
    'method'   => 'animal_image',
    'animalid' => $animalid,
    'seq'      => $seq,
  ], plugin_asm_base_url());

  $attempts = 2;

  for ($i = 1; $i <= $attempts; $i++) {
    $res = wp_remote_get($url, [
      'timeout'     => 25,
      'redirection' => 5,
      'headers'     => [
        'Accept'     => 'image/*,*/*;q=0.8',
        'User-Agent' => 'WordPress Rescue Plugin Suite Proxy',
      ],
    ]);

    if (is_wp_error($res)) {
      $debug_out = ['ok' => false, 'attempt' => $i, 'error' => $res->get_error_message(), 'url' => $url];
      continue;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $ct   = wp_remote_retrieve_header($res, 'content-type');

    $debug_out = [
      'ok'      => false,
      'attempt' => $i,
      'status'  => $code,
      'ct'      => $ct,
      'len'     => is_string($body) ? strlen($body) : 0,
      'url'     => $url,
    ];

    if ($code >= 200 && $code < 300 && is_string($body) && $body !== '' && is_string($ct) && stripos($ct, 'image/') === 0) {
      $debug_out['ok'] = true;
      return ['ct' => $ct, 'body' => $body];
    }

    usleep(120000);
  }

  return null;
}

function plugin_asm_pick_report_status_field(array $row) {
  foreach ([
    'STATUSNAME', 'StatusName', 'statusname',
    'STATUSES', 'Statuses', 'statuses',
    'RESERVATIONSTATUSES', 'ReservationStatuses', 'reservationstatuses',
    'RESERVATIONSTATUSNAME', 'ReservationStatusName', 'reservationstatusname',
  ] as $k) {
    if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') {
      return trim((string)$row[$k]);
    }
  }
  return 'Reserved';
}

function plugin_asm_split_statuses($status_string) {
  $status_string = trim((string)$status_string);
  if ($status_string === '') return [];

  $parts = preg_split('/\s*(?:,|\|)\s*/', $status_string);
  $out = [];

  foreach ($parts as $part) {
    $clean = trim((string)$part);
    if ($clean !== '') $out[] = $clean;
  }

  return $out;
}


function plugin_asm_age_to_months($age_text) {
  $age = strtolower(trim((string)$age_text));
  if ($age === '') return null;

  $total = 0.0;
  $matched = false;

  if (preg_match('/(\d+(?:\.\d+)?)\s*year/', $age, $m)) {
    $total += ((float)$m[1]) * 12;
    $matched = true;
  }
  if (preg_match('/(\d+(?:\.\d+)?)\s*month/', $age, $m)) {
    $total += (float)$m[1];
    $matched = true;
  }
  if (preg_match('/(\d+(?:\.\d+)?)\s*week/', $age, $m)) {
    $total += ((float)$m[1]) / 4.345;
    $matched = true;
  }

  if ($matched) return max(0, (int) round($total));
  if (strpos($age, 'kitten') !== false) return 6;
  if (strpos($age, 'senior') !== false) return 120;
  return null;
}

function plugin_asm_age_band_from_months($months) {
  if ($months === null || $months === '') return '';
  $months = (int)$months;
  if ($months < 12) return 'Under 1 year';
  if ($months < 36) return '1 to 3 years';
  if ($months < 60) return '3 to 5 years';
  return '5+ years';
}


function plugin_suite_settings() {
  $settings = get_option('plugin_ui_suite_settings_v83', []);
  return is_array($settings) ? $settings : [];
}

function plugin_suite_data_source() {
  $settings = plugin_suite_settings();
  $source = sanitize_key($settings['global']['data_source'] ?? 'asm');
  if (!empty($settings['global']['preview_mode']) && current_user_can('manage_options')) {
    $preview = sanitize_key($_GET['asm_suite_source'] ?? $_GET['source_preview'] ?? '');
    if (in_array($preview, ['asm','custom_api','shelterluv','petpoint'], true)) $source = $preview;
  }
  return in_array($source, ['asm','custom_api','shelterluv','petpoint'], true) ? $source : 'asm';
}

function plugin_suite_cache_ttl($key, $default) {
  $settings = plugin_suite_settings();
  $proxy = is_array($settings['proxy'] ?? null) ? $settings['proxy'] : [];
  $global = is_array($settings['global'] ?? null) ? $settings['global'] : [];
  $value = $proxy[$key] ?? $global[$key] ?? $default;
  if (!is_numeric($value)) return (int)$default;
  return max(0, min(3600, (int)$value));
}

function plugin_suite_cache_bypass() {
  $settings = plugin_suite_settings();
  return !empty($settings['global']['bypass_plugin_cache']);
}

function plugin_suite_cache_get($key) {
  if (plugin_suite_cache_bypass()) return false;
  return get_transient($key);
}

function plugin_suite_cache_set($key, $value, $ttl) {
  if (plugin_suite_cache_bypass()) return;
  $ttl = (int)$ttl;
  if ($ttl <= 0) return;
  set_transient($key, $value, $ttl);
}

function plugin_suite_array_is_list(array $array) {
  $expected = 0;
  foreach ($array as $key => $_value) {
    if ($key !== $expected) return false;
    $expected++;
  }
  return true;
}


function plugin_suite_last_good_key($key) {
  return 'plugin_ui_suite_last_good_' . md5((string)$key);
}

function plugin_suite_last_good_set($key, $value) {
  update_option(plugin_suite_last_good_key($key), ['time' => current_time('mysql'), 'data' => $value], false);
}

function plugin_suite_last_good_get($key) {
  $stored = get_option(plugin_suite_last_good_key($key), []);
  return is_array($stored) && array_key_exists('data', $stored) ? $stored['data'] : null;
}

function plugin_suite_last_good_meta($key) {
  $stored = get_option(plugin_suite_last_good_key($key), []);
  return is_array($stored) ? ['time' => ($stored['time'] ?? ''), 'count' => (is_array($stored['data'] ?? null) ? count($stored['data']) : 0)] : ['time' => '', 'count' => 0];
}

function plugin_suite_response_or_last_good($key, $error) {
  $fallback = plugin_suite_last_good_get($key);
  if ($fallback !== null) {
    set_transient('asm_suite_last_good_served_notice', 1, 60);
    return rest_ensure_response($fallback);
  }
  return is_wp_error($error) ? plugin_asm_error_response($error) : $error;
}

function plugin_suite_field_map() {
  $settings = plugin_suite_settings();
  $g = is_array($settings['global'] ?? null) ? $settings['global'] : [];
  $raw = (string)($g['field_map'] ?? '');
  $map = [];
  foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '=') === false) continue;
    [$target, $sources] = array_map('trim', explode('=', $line, 2));
    $target = preg_replace('/[^A-Za-z0-9_]/', '', $target);
    if ($target === '') continue;
    $map[$target] = array_values(array_filter(array_map('trim', preg_split('/[,|]/', $sources)), function($v){ return $v !== ''; }));
  }
  return $map;
}

function plugin_suite_apply_field_map(array &$out, array $row) {
  foreach (plugin_suite_field_map() as $target => $sources) {
    plugin_custom_api_ensure_field($out, $row, $target, $sources);
  }
}

function plugin_custom_api_config() {
  $settings = plugin_suite_settings();
  $g = is_array($settings['global'] ?? null) ? $settings['global'] : [];
  $base = esc_url_raw($g['custom_api_url'] ?? '');
  $endpoint = function($specific, $suffix) use ($g, $base) {
    $url = esc_url_raw($g[$specific] ?? '');
    if ($url !== '') return $url;
    if ($base !== '') return untrailingslashit($base) . $suffix;
    return '';
  };
  return [
    'base_url' => $base,
    'adoptables_url' => $endpoint('custom_api_adoptables_url', '/adoptables'),
    'adoptions_url' => $endpoint('custom_api_adoptions_url', '/adoptions'),
    'report_url' => $endpoint('custom_api_report_url', '/report'),
    'incare_url' => $endpoint('custom_api_incare_url', '/in-care-count'),
    'image_url' => $endpoint('custom_api_image_url', '/animal-image'),
    'api_key' => (string)($g['custom_api_key'] ?? ''),
    'auth_header' => preg_replace('/[^A-Za-z0-9\-]/', '', (string)($g['custom_api_auth_header'] ?? 'X-API-Key')),
  ];
}

function plugin_custom_api_headers() {
  $cfg = plugin_custom_api_config();
  $headers = [
    'User-Agent' => 'WordPress Rescue Plugin Suite Proxy',
    'Accept' => 'application/json,*/*;q=0.8',
  ];
  if ($cfg['api_key'] !== '' && $cfg['auth_header'] !== '') {
    $headers[$cfg['auth_header']] = $cfg['api_key'];
  }
  return $headers;
}

function plugin_custom_api_request($url, $query = [], $binary = false) {
  if (!$url) {
    return new WP_Error('custom_api_missing', 'Custom API endpoint is not configured', ['status' => 500]);
  }
  if (!empty($query)) $url = add_query_arg($query, $url);
  $headers = plugin_custom_api_headers();
  if ($binary) $headers['Accept'] = 'image/*,*/*;q=0.8';
  $res = wp_remote_get($url, [
    'timeout' => 25,
    'redirection' => 5,
    'headers' => $headers,
  ]);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  if ($code < 200 || $code >= 300) {
    return new WP_Error('custom_api_error', 'Custom API request failed', [
      'status' => $code,
      'body' => wp_remote_retrieve_body($res),
      'url' => $url,
    ]);
  }
  return $res;
}

function plugin_custom_api_extract_items($data, $preferred = '') {
  if (!is_array($data)) return [];
  if (plugin_suite_array_is_list($data)) return $data;

  $keys = array_filter([$preferred, 'items', 'data', 'animals', 'adoptables', 'adoptions', 'results']);
  foreach ($keys as $key) {
    if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
  }
  return [];
}

function plugin_custom_api_first_value(array $row, array $keys, $default = '') {
  foreach ($keys as $key) {
    if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
  }
  return $default;
}

function plugin_custom_api_ensure_field(array &$out, array $row, $target, array $keys) {
  if (array_key_exists($target, $out) && $out[$target] !== '') return;
  $value = plugin_custom_api_first_value($row, $keys, '');
  if ($value !== '') $out[$target] = $value;
}



function plugin_provider_global() {
  $settings = plugin_suite_settings();
  return is_array($settings['global'] ?? null) ? $settings['global'] : [];
}

function plugin_provider_endpoint($global, $key, $base_key, $suffix) {
  $url = esc_url_raw($global[$key] ?? '');
  if ($url !== '') return $url;
  $base = esc_url_raw($global[$base_key] ?? '');
  return $base !== '' ? untrailingslashit($base) . $suffix : '';
}

function plugin_provider_auth_headers($provider) {
  $g = plugin_provider_global();
  $headers = [
    'User-Agent' => 'WordPress Rescue Plugin Suite Proxy',
    'Accept' => 'application/json,*/*;q=0.8',
  ];
  if ($provider === 'shelterluv') {
    $key = (string)($g['shelterluv_api_key'] ?? '');
    if ($key !== '') {
      $headers['Authorization'] = 'Bearer ' . $key;
      $headers['X-API-Key'] = $key;
    }
  } elseif ($provider === 'petpoint') {
    $user = (string)($g['petpoint_username'] ?? '');
    $pass = (string)($g['petpoint_password'] ?? '');
    if ($user !== '' || $pass !== '') $headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
  }
  return $headers;
}

function plugin_provider_config($provider) {
  $g = plugin_provider_global();
  if ($provider === 'shelterluv') {
    return [
      'adoptables_url' => plugin_provider_endpoint($g, 'shelterluv_adoptables_url', 'shelterluv_base_url', '/api/v1/animals'),
      'adoptions_url' => plugin_provider_endpoint($g, 'shelterluv_adoptions_url', 'shelterluv_base_url', '/api/v1/adoptions'),
      'report_url' => plugin_provider_endpoint($g, 'shelterluv_report_url', 'shelterluv_base_url', '/api/v1/reports'),
      'incare_url' => plugin_provider_endpoint($g, 'shelterluv_incare_url', 'shelterluv_base_url', '/api/v1/animals'),
      'image_url' => plugin_provider_endpoint($g, 'shelterluv_image_url', 'shelterluv_base_url', '/api/v1/animal-image'),
      'org_id' => (string)($g['shelterluv_org_id'] ?? ''),
      'statuses' => (string)($g['shelterluv_statuses'] ?? 'adoptable,foster'),
      'locations' => (string)($g['shelterluv_location_ids'] ?? ''),
      'animal_type' => (string)($g['shelterluv_animal_type'] ?? 'cat'),
    ];
  }
  return [
    'adoptables_url' => plugin_provider_endpoint($g, 'petpoint_adoptables_url', 'petpoint_base_url', ''),
    'adoptions_url' => plugin_provider_endpoint($g, 'petpoint_adoptions_url', 'petpoint_base_url', '/adoptions'),
    'report_url' => plugin_provider_endpoint($g, 'petpoint_report_url', 'petpoint_base_url', '/report'),
    'incare_url' => plugin_provider_endpoint($g, 'petpoint_incare_url', 'petpoint_base_url', ''),
    'image_url' => plugin_provider_endpoint($g, 'petpoint_image_url', 'petpoint_base_url', '/animal-image'),
    'shelter_id' => (string)($g['petpoint_shelter_id'] ?? ''),
    'species_id' => (string)($g['petpoint_species_id'] ?? '2'),
    'statuses' => (string)($g['petpoint_statuses'] ?? 'available,foster'),
    'locations' => (string)($g['petpoint_location_ids'] ?? ''),
  ];
}

function plugin_provider_csv($value) {
  $parts = preg_split('/[\r\n,]+/', (string)$value);
  return array_values(array_filter(array_map('trim', $parts), function($v){ return $v !== ''; }));
}

function plugin_provider_request($provider, $url, $query = [], $binary = false) {
  if (!$url) return new WP_Error($provider . '_missing', ucfirst($provider) . ' endpoint is not configured', ['status' => 500]);
  if (!empty($query)) $url = add_query_arg($query, $url);
  $headers = plugin_provider_auth_headers($provider);
  if ($binary) $headers['Accept'] = 'image/*,*/*;q=0.8';
  $res = wp_remote_get($url, ['timeout' => 25, 'redirection' => 5, 'headers' => $headers]);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  if ($code < 200 || $code >= 300) return new WP_Error($provider . '_error', ucfirst($provider) . ' request failed', ['status'=>$code,'body'=>wp_remote_retrieve_body($res),'url'=>$url]);
  return $res;
}

function plugin_provider_items($data, $preferred) {
  return plugin_custom_api_extract_items($data, $preferred);
}

function plugin_provider_query($provider, $kind, WP_REST_Request $req = null) {
  $cfg = plugin_provider_config($provider);
  $query = [];
  if ($provider === 'shelterluv') {
    if ($cfg['org_id'] !== '') $query['org_id'] = $cfg['org_id'];
    if ($kind === 'adoptables') {
      $statuses = plugin_provider_csv($cfg['statuses']);
      if ($statuses) $query['status'] = implode(',', $statuses);
      if ($cfg['animal_type'] !== '') $query['type'] = $cfg['animal_type'];
      $locations = plugin_provider_csv($cfg['locations']);
      if ($locations) $query['location_id'] = implode(',', $locations);
    }
  } elseif ($provider === 'petpoint') {
    if ($cfg['shelter_id'] !== '') $query['shelterid'] = $cfg['shelter_id'];
    if ($cfg['species_id'] !== '') $query['speciesid'] = $cfg['species_id'];
    if ($kind === 'adoptables') {
      $statuses = plugin_provider_csv($cfg['statuses']);
      if ($statuses) $query['status'] = implode(',', $statuses);
      $locations = plugin_provider_csv($cfg['locations']);
      if ($locations) $query['location'] = implode(',', $locations);
    }
  }
  if ($req) foreach (['speciesid','year','years','title','animalid','seq'] as $key) {
    $val = $req->get_param($key);
    if ($val !== null && $val !== '') $query[$key] = $val;
  }
  return $query;
}

function plugin_provider_adoptables($provider) {
  $cfg = plugin_provider_config($provider);
  $query = plugin_provider_query($provider, 'adoptables');
  $cache_key = 'plugin_' . $provider . '_adoptables_v1_' . md5($cfg['adoptables_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  $res = plugin_provider_request($provider, $cfg['adoptables_url'], $query);
  if (is_wp_error($res)) return plugin_suite_response_or_last_good($cache_key, $res);
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) return plugin_suite_response_or_last_good($cache_key, new WP_REST_Response(['error' => 'Unexpected ' . ucfirst($provider) . ' response'], 500));
  $items = plugin_provider_items($data, 'adoptables');
  $filtered = array_values(array_map('plugin_custom_api_normalize_adoptable', array_filter($items, 'is_array')));
  plugin_suite_last_good_set($cache_key, $filtered);
  plugin_suite_cache_set($cache_key, $filtered, plugin_suite_cache_ttl('cache_adoptables_seconds', 60));
  return rest_ensure_response($filtered);
}

function plugin_provider_adoptions($provider, WP_REST_Request $req) {
  $cfg = plugin_provider_config($provider);
  $query = plugin_provider_query($provider, 'adoptions', $req);
  $cache_key = 'plugin_' . $provider . '_adoptions_v1_' . md5($cfg['adoptions_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  $res = plugin_provider_request($provider, $cfg['adoptions_url'], $query);
  if (is_wp_error($res)) return plugin_suite_response_or_last_good($cache_key, $res);
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) return plugin_suite_response_or_last_good($cache_key, new WP_REST_Response(['error' => 'Unexpected ' . ucfirst($provider) . ' response'], 500));
  $items = plugin_provider_items($data, 'adoptions');
  $filtered = array_values(array_map('plugin_custom_api_normalize_adoption', array_filter($items, 'is_array')));
  plugin_suite_last_good_set($cache_key, $filtered);
  plugin_suite_cache_set($cache_key, $filtered, plugin_suite_cache_ttl('cache_adoptions_seconds', 300));
  return rest_ensure_response($filtered);
}

function plugin_provider_report($provider, WP_REST_Request $req) {
  $cfg = plugin_provider_config($provider);
  $query = plugin_provider_query($provider, 'report', $req);
  $title = trim((string)($query['title'] ?? ''));
  if ($title === '') return new WP_REST_Response(['error' => 'title required'], 400);
  $cache_key = 'plugin_' . $provider . '_report_v1_' . md5($cfg['report_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  if (empty($cfg['report_url'])) {
    $fallback = plugin_suite_fallback_report($provider, $title, $req);
    if ($fallback !== null) {
      plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
      return rest_ensure_response($fallback);
    }
  }
  $res = plugin_provider_request($provider, $cfg['report_url'], $query);
  if (is_wp_error($res)) {
    $fallback = plugin_suite_fallback_report($provider, $title, $req);
    if ($fallback !== null) {
      plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
      return rest_ensure_response($fallback);
    }
    return plugin_asm_error_response($res);
  }
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) return new WP_REST_Response(['error' => 'Unexpected ' . ucfirst($provider) . ' response'], 500);
  $items = plugin_provider_items($data, 'items');
  if (!empty($items)) $data = $items;
  plugin_suite_cache_set($cache_key, $data, plugin_suite_cache_ttl('cache_reports_seconds', 60));
  return rest_ensure_response($data);
}

function plugin_provider_incare($provider) {
  $cfg = plugin_provider_config($provider);
  $query = plugin_provider_query($provider, 'adoptables');
  $cache_key = 'plugin_' . $provider . '_incare_v1_' . md5($cfg['incare_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  $res = plugin_provider_request($provider, $cfg['incare_url'], $query);
  if (is_wp_error($res)) return plugin_asm_error_response($res);
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (is_numeric($data)) $count = (int)$data;
  elseif (is_array($data)) {
    if (isset($data['count']) || isset($data['in_care']) || isset($data['total'])) $count = (int)($data['count'] ?? $data['in_care'] ?? $data['total']);
    else $count = count(plugin_provider_items($data, 'animals')) ?: count(plugin_provider_items($data, 'adoptables'));
  } else return new WP_REST_Response(['error' => 'Unexpected ' . ucfirst($provider) . ' response'], 500);
  $out = ['count' => $count];
  plugin_suite_cache_set($cache_key, $out, plugin_suite_cache_ttl('cache_incare_seconds', 60));
  return rest_ensure_response($out);
}

function plugin_provider_image($provider, WP_REST_Request $req) {
  $cfg = plugin_provider_config($provider);
  $query = plugin_provider_query($provider, 'image', $req);
  if (!empty($query['animalid']) && empty($query['id'])) $query['id'] = $query['animalid'];
  $res = plugin_provider_request($provider, $cfg['image_url'], $query, true);
  if (is_wp_error($res)) return plugin_asm_error_response($res);
  $body = wp_remote_retrieve_body($res);
  $ct = wp_remote_retrieve_header($res, 'content-type');
  if (!is_string($body) || $body === '' || stripos((string)$ct, 'image/') !== 0) return new WP_REST_Response(['error' => 'Image not available'], 404);
  plugin_cache_image_to_uploads($ct, $body, $query['animalid'] ?? ($query['id'] ?? ''), $query['seq'] ?? '1');
  plugin_stream_image_response($ct, $body);
}


function plugin_suite_response_data($response) {
  if ($response instanceof WP_REST_Response) return $response->get_data();
  if ($response instanceof WP_HTTP_Response) return $response->get_data();
  return $response;
}

function plugin_suite_year_from_row(array $row) {
  foreach (['MOVEMENTDATE','MovementDate','ADOPTIONDATE','AdoptionDate','DATEADOPTED','DateAdopted','MOSTRECENTADOPTIONDATE','MostRecentAdoptionDate','adoption_date','date_adopted','adopted_at'] as $key) {
    if (empty($row[$key])) continue;
    $ts = strtotime((string)$row[$key]);
    if ($ts) return (int)date('Y', $ts);
    if (preg_match('/(19|20)\d{2}/', (string)$row[$key], $m)) return (int)$m[0];
  }
  return 0;
}

function plugin_suite_summary_from_adoptions_array(array $rows) {
  $counts = [];
  foreach ($rows as $row) {
    if (!is_array($row)) continue;
    $year = plugin_suite_year_from_row($row);
    if ($year <= 0) continue;
    if (!isset($counts[$year])) $counts[$year] = 0;
    $counts[$year]++;
  }
  krsort($counts);
  $out = [];
  foreach ($counts as $year => $count) {
    $out[] = ['YEAR' => (int)$year, 'Year' => (int)$year, 'ADOPTIONS' => (int)$count, 'Adoptions' => (int)$count, 'TOTAL' => (int)$count, 'Total' => (int)$count];
  }
  return $out;
}

function plugin_suite_fallback_report($source, $title, WP_REST_Request $req) {
  $title = trim((string)$title);
  if ($title === 'Cats In Care Now') {
    if ($source === 'custom_api') $response = plugin_custom_api_adoptables();
    elseif (in_array($source, ['shelterluv','petpoint'], true)) $response = plugin_provider_adoptables($source);
    else return null;
    $animals = plugin_suite_response_data($response);
    if (is_array($animals)) return [['cats_in_care' => count($animals), 'CATS_IN_CARE' => count($animals), 'source' => 'derived_from_adoptables']];
  }
  if ($title === 'Summary By Year') {
    $fallback_req = new WP_REST_Request('GET', '/plugin/v1/adoptions');
    $fallback_req->set_param('years', $req->get_param('years') ?: 20);
    if ($source === 'custom_api') $response = plugin_custom_api_adoptions($fallback_req);
    elseif (in_array($source, ['shelterluv','petpoint'], true)) $response = plugin_provider_adoptions($source, $fallback_req);
    else return null;
    $adoptions = plugin_suite_response_data($response);
    if (is_array($adoptions)) return plugin_suite_summary_from_adoptions_array($adoptions);
  }
  if ($title === 'Active Reservations Proxy') return [];
  return null;
}

function plugin_asm_fallback_report($title, WP_REST_Request $req) {
  if ($title === 'Cats In Care Now') {
    $response = rest_do_request(new WP_REST_Request('GET', '/plugin/v1/adoptables'));
    $animals = plugin_suite_response_data($response);
    if (is_array($animals)) return [['cats_in_care' => count($animals), 'CATS_IN_CARE' => count($animals), 'source' => 'derived_from_adoptables']];
  }
  if ($title === 'Summary By Year') {
    $fallback_req = new WP_REST_Request('GET', '/plugin/v1/adoptions');
    $fallback_req->set_param('years', $req->get_param('years') ?: 20);
    $response = rest_do_request($fallback_req);
    $adoptions = plugin_suite_response_data($response);
    if (is_array($adoptions)) return plugin_suite_summary_from_adoptions_array($adoptions);
  }
  if ($title === 'Active Reservations Proxy') return [];
  return null;
}

function plugin_filter_allowed_keys($row, $allowed) {
  $out = [];
  if (!is_array($row)) return $out;
  foreach ($allowed as $k) {
    if (array_key_exists($k, $row)) $out[$k] = $row[$k];
  }
  return $out;
}

function plugin_custom_api_normalize_adoptable($row) {
  $allowed = [
    'ID','ANIMALID','ANIMALNAME','CODE','ANIMALAGE','SEXNAME','SEX',
    'BREEDNAME','BREEDNAME1','SPECIESID','SPECIESNAME','DAYSONSHELTER','DaysOnShelter',
    'HASACTIVERESERVE','HasActiveReserve','HASACTIVERESERVENAME','WEBSITEIMAGECOUNT','WebsiteImageCount','WEBSITEIMAGES',
    'ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION','AnimalID','AnimalName','ShelterCode','AnimalAge','SexName','BreedName','BreedName1','SpeciesID','SpeciesName','IsGoodWithCatsName','IsGoodWithDogsName','IsGoodWithChildrenName','ISGOODWITHCATSNAME','ISGOODWITHDOGSNAME','ISGOODWITHCHILDRENNAME','GOODWITHCATSNAME','GOODWITHDOGSNAME','GOODWITHCHILDRENNAME','GOODWITHCATS','GOODWITHDOGS','GOODWITHCHILDREN','GOOD_WITH_CATS','GOOD_WITH_DOGS','GOOD_WITH_CHILDREN','GoodWithCats','GoodWithDogs','GoodWithChildren','HasSpecialNeeds','HASSPECIALNEEDS','SPECIALNEEDS','SPECIAL_NEEDS','SpecialNeeds','SPECIALNEEDSNAME',
    'AGE_MONTHS','AGE_BAND','reservation_statuses','reservation_status_counts','reservation_count','has_active_reservation','primary_reservation_status'
  ];
  $out = plugin_filter_allowed_keys($row, $allowed);
  plugin_custom_api_ensure_field($out, $row, 'ID', ['id','animal_id','animalid','AnimalID','ANIMALID']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALID', ['animal_id','animalid','id','AnimalID','ID']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALNAME', ['animal_name','animalname','name','Name','AnimalName']);
  plugin_custom_api_ensure_field($out, $row, 'CODE', ['code','shelter_code','sheltercode','ShelterCode']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALAGE', ['animal_age','age','age_text','AnimalAge']);
  plugin_custom_api_ensure_field($out, $row, 'SEXNAME', ['sex_name','sex','gender','SexName','SEX']);
  plugin_custom_api_ensure_field($out, $row, 'BREEDNAME', ['breed_name','breed','primary_breed','BreedName','BreedName1']);
  plugin_custom_api_ensure_field($out, $row, 'SPECIESID', ['species_id','speciesid','SpeciesID']);
  plugin_custom_api_ensure_field($out, $row, 'SPECIESNAME', ['species_name','species','SpeciesName']);
  plugin_custom_api_ensure_field($out, $row, 'DAYSONSHELTER', ['days_on_shelter','days_in_care','DaysOnShelter']);
  plugin_custom_api_ensure_field($out, $row, 'WEBSITEIMAGECOUNT', ['website_image_count','image_count','photo_count','WebsiteImageCount','WebsiteImages']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALCOMMENTS', ['animal_comments','description','bio','story','AnimalComments']);
  plugin_suite_apply_field_map($out, $row);
  $age_months = $row['AGE_MONTHS'] ?? $row['age_months'] ?? plugin_asm_age_to_months(plugin_custom_api_first_value($row, ['ANIMALAGE','AnimalAge','animal_age','age','age_text'], ''));
  if ($age_months !== null && $age_months !== '') {
    $out['AGE_MONTHS'] = (int)$age_months;
    $out['AGE_BAND'] = $row['AGE_BAND'] ?? $row['age_band'] ?? plugin_asm_age_band_from_months((int)$age_months);
  }
  return $out;
}

function plugin_custom_api_normalize_adoption($row) {
  $allowed = [
    'ID','ANIMALID','ANIMALNAME','CODE','ANIMALAGE','SEXNAME','SEX','BREEDNAME','BREEDNAME1','SPECIESID','SPECIESNAME',
    'WEBSITEIMAGECOUNT','WebsiteImageCount','WEBSITEIMAGES','ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION','IsGoodWithCatsName','IsGoodWithDogsName','IsGoodWithChildrenName','ISGOODWITHCATSNAME','ISGOODWITHDOGSNAME','ISGOODWITHCHILDRENNAME','GOODWITHCATSNAME','GOODWITHDOGSNAME','GOODWITHCHILDRENNAME','GOODWITHCATS','GOODWITHDOGS','GOODWITHCHILDREN','GOOD_WITH_CATS','GOOD_WITH_DOGS','GOOD_WITH_CHILDREN','GoodWithCats','GoodWithDogs','GoodWithChildren','HasSpecialNeeds','HASSPECIALNEEDS','SPECIALNEEDS','SPECIAL_NEEDS','SpecialNeeds','SPECIALNEEDSNAME',
    'ACTIVEMOVEMENTDATE','MOSTRECENTADOPTIONDATE','ADOPTIONDATE','DATEADOPTED','MOVEMENTDATE','AnimalID','AnimalName','ShelterCode','AnimalAge','SexName','Sex','BreedName','BreedName1','SpeciesID','SpeciesName','WebsiteImageCount','WebsiteImages','ActiveMovementDate','MostRecentAdoptionDate','AdoptionDate','DateAdopted','MovementDate',
    'AGE_MONTHS','AGE_BAND'
  ];
  $out = plugin_filter_allowed_keys($row, $allowed);
  plugin_custom_api_ensure_field($out, $row, 'ID', ['id','animal_id','animalid','AnimalID','ANIMALID']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALID', ['animal_id','animalid','id','AnimalID','ID']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALNAME', ['animal_name','animalname','name','Name','AnimalName']);
  plugin_custom_api_ensure_field($out, $row, 'CODE', ['code','shelter_code','sheltercode','ShelterCode']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALAGE', ['animal_age','age','age_text','AnimalAge']);
  plugin_custom_api_ensure_field($out, $row, 'SEXNAME', ['sex_name','sex','gender','SexName','SEX']);
  plugin_custom_api_ensure_field($out, $row, 'BREEDNAME', ['breed_name','breed','primary_breed','BreedName','BreedName1']);
  plugin_custom_api_ensure_field($out, $row, 'SPECIESID', ['species_id','speciesid','SpeciesID']);
  plugin_custom_api_ensure_field($out, $row, 'SPECIESNAME', ['species_name','species','SpeciesName']);
  plugin_custom_api_ensure_field($out, $row, 'WEBSITEIMAGECOUNT', ['website_image_count','image_count','photo_count','WebsiteImageCount','WebsiteImages']);
  plugin_custom_api_ensure_field($out, $row, 'ANIMALCOMMENTS', ['animal_comments','description','bio','story','AnimalComments']);
  plugin_custom_api_ensure_field($out, $row, 'MOVEMENTDATE', ['movement_date','adoption_date','date_adopted','adopted_at','MovementDate','AdoptionDate','DateAdopted']);
  plugin_suite_apply_field_map($out, $row);
  $age_months = $row['AGE_MONTHS'] ?? $row['age_months'] ?? plugin_asm_age_to_months(plugin_custom_api_first_value($row, ['ANIMALAGE','AnimalAge','animal_age','age','age_text'], ''));
  if ($age_months !== null && $age_months !== '') {
    $out['AGE_MONTHS'] = (int)$age_months;
    $out['AGE_BAND'] = $row['AGE_BAND'] ?? $row['age_band'] ?? plugin_asm_age_band_from_months((int)$age_months);
  }
  return $out;
}

function plugin_custom_api_adoptables() {
  $cfg = plugin_custom_api_config();
  $query = $cfg['api_key'] !== '' ? ['api_key' => $cfg['api_key']] : [];
  $cache_key = 'plugin_custom_api_adoptables_v2_' . md5($cfg['adoptables_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  $res = plugin_custom_api_request($cfg['adoptables_url'], $query);
  if (is_wp_error($res)) return plugin_suite_response_or_last_good($cache_key, $res);
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) return plugin_suite_response_or_last_good($cache_key, new WP_REST_Response(['error' => 'Unexpected Custom API response'], 500));
  $data = plugin_custom_api_extract_items($data, 'adoptables');
  $filtered = array_values(array_map('plugin_custom_api_normalize_adoptable', array_filter($data, 'is_array')));
  plugin_suite_last_good_set($cache_key, $filtered);
  plugin_suite_cache_set($cache_key, $filtered, plugin_suite_cache_ttl('cache_adoptables_seconds', 60));
  return rest_ensure_response($filtered);
}

function plugin_custom_api_adoptions(WP_REST_Request $req) {
  $cfg = plugin_custom_api_config();
  $query = [];
  foreach (['speciesid','year','years'] as $key) {
    $val = $req->get_param($key);
    if ($val !== null && $val !== '') $query[$key] = $val;
  }
  if ($cfg['api_key'] !== '') $query['api_key'] = $cfg['api_key'];
  $cache_key = 'plugin_custom_api_adoptions_v2_' . md5($cfg['adoptions_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  $res = plugin_custom_api_request($cfg['adoptions_url'], $query);
  if (is_wp_error($res)) return plugin_suite_response_or_last_good($cache_key, $res);
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) return plugin_suite_response_or_last_good($cache_key, new WP_REST_Response(['error' => 'Unexpected Custom API response'], 500));
  $data = plugin_custom_api_extract_items($data, 'adoptions');
  $filtered = array_values(array_map('plugin_custom_api_normalize_adoption', array_filter($data, 'is_array')));
  plugin_suite_last_good_set($cache_key, $filtered);
  plugin_suite_cache_set($cache_key, $filtered, plugin_suite_cache_ttl('cache_adoptions_seconds', 300));
  return rest_ensure_response($filtered);
}

function plugin_custom_api_report(WP_REST_Request $req) {
  $cfg = plugin_custom_api_config();
  $title = trim((string)$req->get_param('title'));
  if ($title === '') return new WP_REST_Response(['error' => 'title required'], 400);
  $query = ['title' => $title];
  if ($cfg['api_key'] !== '') $query['api_key'] = $cfg['api_key'];
  $cache_key = 'plugin_custom_api_report_v2_' . md5($cfg['report_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  if (empty($cfg['report_url'])) {
    $fallback = plugin_suite_fallback_report('custom_api', $title, $req);
    if ($fallback !== null) {
      plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
      return rest_ensure_response($fallback);
    }
  }
  $res = plugin_custom_api_request($cfg['report_url'], $query);
  if (is_wp_error($res)) {
    $fallback = plugin_suite_fallback_report('custom_api', $title, $req);
    if ($fallback !== null) {
      plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
      return rest_ensure_response($fallback);
    }
    return plugin_asm_error_response($res);
  }
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) {
    $fallback = plugin_suite_fallback_report('custom_api', $title, $req);
    if ($fallback !== null) {
      plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
      return rest_ensure_response($fallback);
    }
    return new WP_REST_Response(['error' => 'Unexpected Custom API response'], 500);
  }
  if (!plugin_suite_array_is_list($data)) {
    $items = plugin_custom_api_extract_items($data, 'items');
    if (!empty($items)) $data = $items;
  }
  plugin_suite_cache_set($cache_key, $data, plugin_suite_cache_ttl('cache_reports_seconds', 60));
  return rest_ensure_response($data);
}

function plugin_custom_api_incare() {
  $cfg = plugin_custom_api_config();
  $query = $cfg['api_key'] !== '' ? ['api_key' => $cfg['api_key']] : [];
  $cache_key = 'plugin_custom_api_incare_v2_' . md5($cfg['incare_url'] . '|' . wp_json_encode($query));
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return rest_ensure_response($cached);
  $res = plugin_custom_api_request($cfg['incare_url'], $query);
  if (is_wp_error($res)) return plugin_asm_error_response($res);
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (is_numeric($data)) {
    $count = (int)$data;
  } elseif (is_array($data)) {
    if (!plugin_suite_array_is_list($data)) {
      $items = plugin_custom_api_extract_items($data, 'items');
      if (isset($items[0]) && is_array($items[0])) $data = $items[0];
    }
    if (isset($data[0]) && is_array($data[0])) $data = $data[0];
    $count = (int)($data['count'] ?? $data['COUNT'] ?? $data['cats_in_care'] ?? $data['CATS_IN_CARE'] ?? $data['in_care'] ?? $data['IN_CARE'] ?? 0);
  } else {
    return new WP_REST_Response(['error' => 'Unexpected Custom API response'], 500);
  }
  $out = ['count' => $count];
  plugin_suite_cache_set($cache_key, $out, plugin_suite_cache_ttl('cache_incare_seconds', 60));
  return rest_ensure_response($out);
}

function plugin_custom_api_image(WP_REST_Request $req) {
  $cfg = plugin_custom_api_config();
  $query = [];
  foreach (['animalid','seq'] as $key) {
    $val = $req->get_param($key);
    if ($val !== null && $val !== '') $query[$key] = $val;
  }
  if (!empty($query['animalid']) && empty($query['id'])) $query['id'] = $query['animalid'];
  if ($cfg['api_key'] !== '') $query['api_key'] = $cfg['api_key'];
  $res = plugin_custom_api_request($cfg['image_url'], $query, true);
  if (is_wp_error($res)) return plugin_asm_error_response($res);
  $body = wp_remote_retrieve_body($res);
  $ct = wp_remote_retrieve_header($res, 'content-type');
  if (!is_string($body) || $body === '' || stripos((string)$ct, 'image/') !== 0) return new WP_REST_Response(['error' => 'Image not available'], 404);
  plugin_cache_image_to_uploads($ct, $body, $query['animalid'] ?? ($query['id'] ?? ''), $query['seq'] ?? '1');
  plugin_stream_image_response($ct, $body);
}

function plugin_asm_get_reservations() {
  $cache_key = 'plugin_asm_reservations_v3';
  $cached = plugin_suite_cache_get($cache_key);
  if ($cached !== false) return $cached;

  $res = plugin_asm_http_get([
    'method'   => 'json_report',
    'username' => plugin_asm_user(),
    'password' => plugin_asm_pass(),
    'title'    => 'Active Reservations Proxy',
  ]);

  if (is_wp_error($res)) {
    plugin_suite_cache_set($cache_key, [], plugin_suite_cache_ttl('cache_reports_seconds', 60));
    return [];
  }

  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data)) {
    plugin_suite_cache_set($cache_key, [], plugin_suite_cache_ttl('cache_reports_seconds', 60));
    return [];
  }

  $by_animal = [];

  foreach ($data as $row) {
    if (!is_array($row)) continue;

    $aid = (int)($row['AID'] ?? $row['aid'] ?? 0);
    if ($aid <= 0) continue;

    $raw_status = plugin_asm_pick_report_status_field($row);
    $statuses = plugin_asm_split_statuses($raw_status);
    if (empty($statuses)) $statuses = ['Reserved'];

    if (!isset($by_animal[$aid])) {
      $by_animal[$aid] = [
        'reservation_statuses' => [],
        'reservation_count'    => 0,
      ];
    }

    foreach ($statuses as $status) {
      $by_animal[$aid]['reservation_statuses'][] = $status;
      $by_animal[$aid]['reservation_count']++;
    }
  }

  plugin_suite_cache_set($cache_key, $by_animal, plugin_suite_cache_ttl('cache_reports_seconds', 60));
  return $by_animal;
}

add_action('rest_api_init', function () {

  register_rest_route('plugin/v1', '/adoptables', [
    'methods' => 'GET',
    'callback' => function() {
      $source = plugin_suite_data_source();
      if ($source === 'custom_api') return plugin_custom_api_adoptables();
      if (in_array($source, ['shelterluv','petpoint'], true)) return plugin_provider_adoptables($source);
      if ($source !== 'asm') return new WP_REST_Response(['error'=>'Unsupported data source'], 400);
      $cache_key = 'plugin_asm_adoptables_v7';
      $cached = plugin_suite_cache_get($cache_key);
      if ($cached !== false) return rest_ensure_response($cached);

      $res = plugin_asm_http_get([
        'method'   => 'json_adoptable_animals',
        'username' => plugin_asm_user(),
        'password' => plugin_asm_pass(),
      ]);

      if (is_wp_error($res)) return plugin_asm_error_response($res);

      $data = json_decode(wp_remote_retrieve_body($res), true);
      if (!is_array($data)) return new WP_REST_Response(['error' => 'Unexpected ASM response'], 500);

      $reservations = plugin_asm_get_reservations();

      $allowed = [
        'ID','ANIMALID','ANIMALNAME','CODE','ANIMALAGE','SEXNAME','SEX',
        'BREEDNAME','BREEDNAME1','SPECIESID','SPECIESNAME',
        'DAYSONSHELTER','DaysOnShelter',
        'HASACTIVERESERVE','HasActiveReserve','HASACTIVERESERVENAME',
        'WEBSITEIMAGECOUNT','WebsiteImageCount','WEBSITEIMAGES',
        'ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION',
        'AnimalID','AnimalName','ShelterCode','AnimalAge','SexName','BreedName','BreedName1','SpeciesID','SpeciesName','IsGoodWithCatsName','IsGoodWithDogsName','IsGoodWithChildrenName','ISGOODWITHCATSNAME','ISGOODWITHDOGSNAME','ISGOODWITHCHILDRENNAME','GOODWITHCATSNAME','GOODWITHDOGSNAME','GOODWITHCHILDRENNAME','GOODWITHCATS','GOODWITHDOGS','GOODWITHCHILDREN','GOOD_WITH_CATS','GOOD_WITH_DOGS','GOOD_WITH_CHILDREN','GoodWithCats','GoodWithDogs','GoodWithChildren','HasSpecialNeeds','HASSPECIALNEEDS','SPECIALNEEDS','SPECIAL_NEEDS','SpecialNeeds','SPECIALNEEDSNAME',
      ];

      $filtered = array_map(function($row) use ($allowed, $reservations) {
        $out = [];
        if (!is_array($row)) return $out;

        foreach ($allowed as $k) {
          if (array_key_exists($k, $row)) $out[$k] = $row[$k];
        }

        $aid = (int)($row['ID'] ?? $row['AnimalID'] ?? $row['ANIMALID'] ?? 0);
        $reservation_row = ($aid > 0 && isset($reservations[$aid])) ? $reservations[$aid] : null;

        $age_months = plugin_asm_age_to_months($row['ANIMALAGE'] ?? $row['AnimalAge'] ?? '');
        if ($age_months !== null) {
          $out['AGE_MONTHS'] = $age_months;
          $out['AGE_BAND'] = plugin_asm_age_band_from_months($age_months);
        }

        if ($reservation_row) {
          $all_statuses = array_values(array_filter($reservation_row['reservation_statuses'], function($s){
            return is_string($s) && trim($s) !== '';
          }));

          $unique_statuses = array_values(array_unique($all_statuses));
          $status_counts = array_count_values($all_statuses);

          $primary_status = '';
          foreach ($unique_statuses as $status) {
            if (strcasecmp(trim((string)$status), 'Pending Adoption') === 0) {
              $primary_status = 'Pending Adoption';
              break;
            }
          }
          if ($primary_status === '') {
            $primary_status = $unique_statuses[0] ?? '';
          }

          $out['reservation_statuses']       = $unique_statuses;
          $out['reservation_status_counts']  = empty($status_counts) ? new stdClass() : $status_counts;
          $out['reservation_count']          = (int)$reservation_row['reservation_count'];
          $out['has_active_reservation']     = true;
          $out['primary_reservation_status'] = $primary_status;
        } else {
          $fallback_has_active = false;

          foreach (['HASACTIVERESERVE','HasActiveReserve'] as $k) {
            if (!array_key_exists($k, $row)) continue;
            $v = $row[$k];
            if (is_string($v)) {
              $fallback_has_active = $fallback_has_active || strtolower(trim($v)) === 'yes';
            } else {
              $fallback_has_active = $fallback_has_active || ((int)$v === 1);
            }
          }

          if (array_key_exists('HASACTIVERESERVENAME', $row)) {
            $fallback_has_active = $fallback_has_active || strtolower(trim((string)$row['HASACTIVERESERVENAME'])) === 'yes';
          }

          $out['reservation_statuses']       = [];
          $out['reservation_status_counts']  = new stdClass();
          $out['reservation_count']          = 0;
          $out['has_active_reservation']     = (bool)$fallback_has_active;
          $out['primary_reservation_status'] = '';
        }

        return $out;
      }, $data);

      plugin_suite_cache_set($cache_key, $filtered, plugin_suite_cache_ttl('cache_adoptables_seconds', 60));
      return rest_ensure_response($filtered);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('plugin/v1', '/report', [
    'methods' => 'GET',
    'callback' => function(WP_REST_Request $req) {
      $source = plugin_suite_data_source();
      if ($source === 'custom_api') return plugin_custom_api_report($req);
      if (in_array($source, ['shelterluv','petpoint'], true)) return plugin_provider_report($source, $req);
      if ($source !== 'asm') return new WP_REST_Response(['error'=>'Unsupported data source'], 400);
      $title = trim((string)$req->get_param('title'));
      if ($title === '') return new WP_REST_Response(['error' => 'title required'], 400);

      $allowed_titles = ['Summary By Year', 'Cats In Care Now', 'Active Reservations Proxy'];
      if (!in_array($title, $allowed_titles, true)) {
        return new WP_REST_Response(['error' => 'Report not allowed'], 403);
      }

      $cache_key = 'plugin_asm_report_' . md5($title);
      $cached = plugin_suite_cache_get($cache_key);
      if ($cached !== false) return rest_ensure_response($cached);

      $res = plugin_asm_http_get([
        'method'   => 'json_report',
        'username' => plugin_asm_user(),
        'password' => plugin_asm_pass(),
        'title'    => $title,
      ]);

      if (is_wp_error($res)) {
        $fallback = plugin_asm_fallback_report($title, $req);
        if ($fallback !== null) {
          plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
          return rest_ensure_response($fallback);
        }
        return plugin_asm_error_response($res);
      }

      $data = json_decode(wp_remote_retrieve_body($res), true);
      if (!is_array($data)) {
        $fallback = plugin_asm_fallback_report($title, $req);
        if ($fallback !== null) {
          plugin_suite_cache_set($cache_key, $fallback, plugin_suite_cache_ttl('cache_reports_seconds', 60));
          return rest_ensure_response($fallback);
        }
        return new WP_REST_Response(['error' => 'Unexpected ASM response'], 500);
      }

      plugin_suite_cache_set($cache_key, $data, plugin_suite_cache_ttl('cache_reports_seconds', 60));
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('plugin/v1', '/in-care-count', [
    'methods' => 'GET',
    'callback' => function() {
      $source = plugin_suite_data_source();
      if ($source === 'custom_api') return plugin_custom_api_incare();
      if (in_array($source, ['shelterluv','petpoint'], true)) return plugin_provider_incare($source);
      if ($source !== 'asm') return new WP_REST_Response(['error'=>'Unsupported data source'], 400);
      $cache_key = 'plugin_asm_in_care_count_v1';
      $cached = plugin_suite_cache_get($cache_key);
      if ($cached !== false) return rest_ensure_response($cached);

      $res = plugin_asm_http_get([
        'method'   => 'json_report',
        'username' => plugin_asm_user(),
        'password' => plugin_asm_pass(),
        'title'    => 'Cats In Care Now',
      ]);

      if (is_wp_error($res)) return plugin_asm_error_response($res);

      $data = json_decode(wp_remote_retrieve_body($res), true);
      if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
        return new WP_REST_Response(['error' => 'Unexpected ASM response'], 500);
      }

      $row = $data[0];
      $raw = null;
      foreach (['cats_in_care','CATS_IN_CARE','Cats_In_Care','CATSINCARE','CatsInCare'] as $k) {
        if (array_key_exists($k, $row)) { $raw = $row[$k]; break; }
      }

      $count = is_numeric($raw) ? (int)$raw : 0;
      $out = ['count' => $count];
      plugin_suite_cache_set($cache_key, $out, plugin_suite_cache_ttl('cache_incare_seconds', 60));
      return rest_ensure_response($out);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('plugin/v1', '/adoptions', [
    'methods' => 'GET',
    'callback' => function(WP_REST_Request $req) {
      $source = plugin_suite_data_source();
      if ($source === 'custom_api') return plugin_custom_api_adoptions($req);
      if (in_array($source, ['shelterluv','petpoint'], true)) return plugin_provider_adoptions($source, $req);
      if ($source !== 'asm') return new WP_REST_Response(['error'=>'Unsupported data source'], 400);
      $speciesid = (int) $req->get_param('speciesid');
      $year      = (int) $req->get_param('year');
      $yearsBack = (int) $req->get_param('years');

      if ($year > 0) {
        $from_ts = strtotime('01-01-' . $year . ' 00:00:00');
        $to_ts   = strtotime('31-12-' . $year . ' 23:59:59');
      } else {
        if ($yearsBack <= 0) $yearsBack = 10;
        if ($yearsBack > 20) $yearsBack = 20;
        $to_ts = current_time('timestamp');
        $from_ts = strtotime('-' . $yearsBack . ' years', $to_ts);
      }

      $ranges = [
        ['label' => 'UK', 'fromdate' => date('d/m/Y', $from_ts), 'todate' => date('d/m/Y', $to_ts)],
        ['label' => 'US', 'fromdate' => date('m/d/Y', $from_ts), 'todate' => date('m/d/Y', $to_ts)],
      ];

      foreach ($ranges as $try) {
        $cache_key = 'plugin_asm_adoptions_v2_' . md5($try['label'].'|'.$try['fromdate'].'|'.$try['todate'].'|sid:' . ($speciesid ?: 0));
        $cached = plugin_suite_cache_get($cache_key);
        if ($cached !== false) return rest_ensure_response($cached);

        $res = plugin_asm_http_get([
          'method'   => 'json_adopted_animals',
          'username' => plugin_asm_user(),
          'password' => plugin_asm_pass(),
          'fromdate' => $try['fromdate'],
          'todate'   => $try['todate'],
        ]);

        if (is_wp_error($res)) {
          $data = $res->get_error_data();
          $body = isset($data['body']) ? (string)$data['body'] : '';
          return new WP_REST_Response([
            'error'   => $res->get_error_message(),
            'status'  => isset($data['status']) ? (int)$data['status'] : 500,
            'snippet' => mb_substr($body, 0, 800),
            'try'     => $try,
          ], 500);
        }

        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);

        if (!is_array($data) || (isset($data['error']) && !isset($data[0]))) {
          continue;
        }

        if ($speciesid > 0) {
          $data = array_values(array_filter($data, function($row) use ($speciesid){
            if (!is_array($row)) return false;
            $sid_raw = $row['SPECIESID'] ?? $row['SpeciesID'] ?? $row['speciesid'] ?? 0;
            return (int)$sid_raw === $speciesid;
          }));
        }

        $allowed = [
          'ID','ANIMALID','ANIMALNAME','CODE','ANIMALAGE','SEXNAME','SEX',
          'BREEDNAME','BREEDNAME1','SPECIESID','SPECIESNAME',
          'WEBSITEIMAGECOUNT','WebsiteImageCount','WEBSITEIMAGES',
          'ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION','IsGoodWithCatsName','IsGoodWithDogsName','IsGoodWithChildrenName','ISGOODWITHCATSNAME','ISGOODWITHDOGSNAME','ISGOODWITHCHILDRENNAME','GOODWITHCATSNAME','GOODWITHDOGSNAME','GOODWITHCHILDRENNAME','GOODWITHCATS','GOODWITHDOGS','GOODWITHCHILDREN','GOOD_WITH_CATS','GOOD_WITH_DOGS','GOOD_WITH_CHILDREN','GoodWithCats','GoodWithDogs','GoodWithChildren','HasSpecialNeeds','HASSPECIALNEEDS','SPECIALNEEDS','SPECIAL_NEEDS','SpecialNeeds','SPECIALNEEDSNAME',
          'ACTIVEMOVEMENTDATE','MOSTRECENTADOPTIONDATE','ADOPTIONDATE','DATEADOPTED','MOVEMENTDATE',
          'AnimalID','AnimalName','ShelterCode','AnimalAge','SexName','Sex',
          'BreedName','BreedName1','SpeciesID','SpeciesName',
          'WebsiteImageCount','WebsiteImages',
          'ActiveMovementDate','MostRecentAdoptionDate','AdoptionDate','DateAdopted','MovementDate'
        ];

        $filtered = array_map(function($row) use ($allowed){
          $out = [];
          if (!is_array($row)) return $out;
          foreach ($allowed as $k) {
            if (array_key_exists($k, $row)) $out[$k] = $row[$k];
          }
          $age_months = plugin_asm_age_to_months($row['ANIMALAGE'] ?? $row['AnimalAge'] ?? '');
          if ($age_months !== null) {
            $out['AGE_MONTHS'] = $age_months;
            $out['AGE_BAND'] = plugin_asm_age_band_from_months($age_months);
          }
          return $out;
        }, $data);

        plugin_suite_cache_set($cache_key, $filtered, plugin_suite_cache_ttl('cache_adoptions_seconds', 300));
        return rest_ensure_response($filtered);
      }

      return new WP_REST_Response([
        'error' => 'Unexpected ASM response (not a JSON array)',
        'tries' => $ranges,
      ], 500);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('plugin/v1', '/animal-image', [
    'methods' => 'GET',
    'callback' => function(WP_REST_Request $req) {
      $source = plugin_suite_data_source();
      if ($source === 'custom_api') return plugin_custom_api_image($req);
      if (in_array($source, ['shelterluv','petpoint'], true)) return plugin_provider_image($source, $req);
      if ($source !== 'asm') return new WP_REST_Response(['error'=>'Unsupported data source'], 400);
      $animalid = preg_replace('/\D+/', '', (string) $req->get_param('animalid'));
      $raw_seq  = $req->get_param('seq');
      $explicit_seq = $raw_seq !== null && $raw_seq !== '';
      $seq      = preg_replace('/\D+/', '', (string) $raw_seq);
      $debug    = (string) $req->get_param('debug') === '1';
      $allow_fallback = !$explicit_seq || (string) $req->get_param('fallback') === '1';

      if ($animalid === '') return new WP_REST_Response(['error' => 'animalid required'], 400);
      if ($seq === '') $seq = '1';

      $seqs_to_try = $allow_fallback ? [(int)$seq, (int)$seq + 1, (int)$seq + 2, (int)$seq + 3] : [(int)$seq];
      $seqs_to_try = array_values(array_unique(array_filter($seqs_to_try, function($n){ return $n >= 1 && $n <= 12; })));

      $last_debug = null;

      foreach ($seqs_to_try as $s) {
        $img = plugin_fetch_asm_image($animalid, (string)$s, $last_debug);
        if ($img && isset($img['ct']) && isset($img['body'])) {
          if ($debug) {
            return rest_ensure_response([
              'ok' => true,
              'animalid' => $animalid,
              'requested_seq' => (int)$seq,
              'served_seq' => $s,
              'fallback' => $allow_fallback,
              'ct' => $img['ct'],
              'len' => strlen($img['body']),
              'attempt_info' => $last_debug,
            ]);
          }
          plugin_cache_image_to_uploads($img['ct'], $img['body'], $animalid, (string)$s);
          plugin_stream_image_response($img['ct'], $img['body']);
        }
      }

      if ($debug) {
        return rest_ensure_response([
          'ok' => false,
          'animalid' => $animalid,
          'requested_seq' => $seq,
          'tried' => $seqs_to_try,
          'fallback' => $allow_fallback,
          'last_attempt' => $last_debug,
        ]);
      }

      return new WP_REST_Response(['error' => 'Image not available'], 404);
    },
    'permission_callback' => '__return_true',
  ]);

});
