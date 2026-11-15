<?php
/** Standalone regressions for documented ASM built-ins, missing data and report compatibility. */
define('ABSPATH', __DIR__ . '/');
$settings = ['global' => ['stats_data_mode' => 'built_in'], 'proxy' => ['account' => 'fixture-account']];
$transients = []; $transient_ttls = []; $routes = []; $requests = []; $fail_month = 0; $shelter_invalid = false;
$summary_report = null;
class WP_REST_Request {
  private $params = [];
  public function __construct(...$args) {}
  public function get_param($key) { return $this->params[$key] ?? null; }
  public function set_param($key, $value) { $this->params[$key] = $value; }
}
class WP_HTTP_Response {
  protected $data;
  public function __construct($data, $status = 200) { $this->data = $data; }
  public function get_data() { return $this->data; }
}
class WP_REST_Response extends WP_HTTP_Response {}
class WP_Error {
  public function __construct(...$args) {}
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_option($key, $default = []) { global $settings; return $key === 'plugin_ui_suite_settings_v83' ? $settings : $default; }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function set_transient($key, $value, $ttl) { global $transients, $transient_ttls; $transients[$key] = $value; $transient_ttls[$key] = $ttl; }
function wp_date($format) { return $format === 'n' ? '3' : '2026'; }
function sanitize_key($value) { return $value; }
function add_action($name, $callback) { if ($name === 'rest_api_init') $callback(); }
function register_rest_route($ns, $path, $args) { global $routes; $routes[$path] = $args['callback']; }
function rest_ensure_response($data) { return new WP_REST_Response($data); }
function add_query_arg($params, $url) { return $url . '?' . http_build_query($params); }
function wp_remote_retrieve_response_code($res) { return $res['code']; }
function wp_remote_retrieve_body($res) { return $res['body']; }
function wp_json_encode($value) { return json_encode($value); }
function monthly_fixture($month, $year) {
  // Shape and field spelling follow ASM's SACMetricsPublisher.processStats.
  // Other outcomes deliberately differ from adoptions; nested transfers are intake.
  $group = ['otherIntakes' => 1, 'ownerIntendedEuthanasia' => 0, 'relinquishedbyOwner' => 2,
    'seizedOrImpounded' => 0, 'strayAtLarge' => 3,
    'transferredinFromAgency' => ['inState' => 4, 'international' => 0, 'outOfState' => 0]];
  $zero = array_map(static function($value) { return is_array($value) ? array_fill_keys(array_keys($value), 0) : 0; }, $group);
  return [['recordMonth' => (string)$month, 'recordYear' => (string)$year, 'species' => 'feline',
    'liveIntake' => ['adult' => $group, 'ageUnknown' => $zero, 'upToFiveMonths' => $group],
    'outComes' => ['liveOutcomes' => ['adult' => ['adoption' => 2, 'otherLiveOutcome' => 99],
      'ageUnknown' => ['adoption' => 0], 'upToFiveMonths' => ['adoption' => 3]]]]];
}
function wp_remote_get($url, $options) {
  global $requests, $fail_month, $shelter_invalid, $summary_report;
  parse_str(parse_url($url, PHP_URL_QUERY), $params); $requests[] = $params;
  if ($params['method'] === 'json_monthly_stats') {
    if ((int)$params['month'] === $fail_month) return new WP_Error('fixture_failure');
    $data = monthly_fixture((int)$params['month'], (int)$params['year']);
  } elseif ($params['method'] === 'json_shelter_animals') {
    $data = $shelter_invalid ? [['ID' => 1]] : [['ID' => 1, 'SPECIESID' => 2], ['ID' => 2, 'SPECIESID' => 1], ['ID' => 3, 'SPECIESNAME' => 'Cat']];
  } elseif ($params['method'] === 'json_report') {
    if ($params['title'] === 'Active Reservations Proxy') $data = [['AID' => 42, 'STATUSNAME' => 'Pending Adoption']];
    elseif ($params['title'] === 'Summary By Year' && $summary_report === false) return ['code' => 200, 'body' => 'ERROR: report not found'];
    elseif ($params['title'] === 'Summary By Year' && is_array($summary_report)) $data = $summary_report;
    else $data = $params['title'] === 'Cats In Care Now' ? [['cats_in_care' => 7]] : [['YEAR' => 2026, 'CATS_ADOPTED' => 9, 'CATS_VACCINATED' => 12]];
  } else throw new RuntimeException('Unexpected method: ' . $params['method']);
  return ['code' => 200, 'body' => json_encode($data)];
}
require dirname(__DIR__) . '/includes/modules/asm-proxy/class-module.php';
function ok($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "ok: $message\n"; }
function report_request($year = 2026) { $req = new WP_REST_Request(); $req->set_param('title', 'Summary By Year'); $req->set_param('year', $year); return $req; }
$report = plugin_asm_builtin_summary(report_request());
ok($report[0]['cats_brought_in'] === 60 && $report[0]['cats_adopted'] === 15, 'Current-year built-in statistics aggregate three months, including nested transfers and only adoption outcomes');
ok($report[0]['cats_vaccinated'] === null && $report[0]['cats_neutered'] === null && $report[0]['cats_chipped'] === null, 'Health totals absent from the standard API remain unknown');
ok(count($requests) === 3 && !in_array('json_report', array_column($requests, 'method'), true), 'Built-in fallback needs no installed custom report and never requests future months');
ok(array_values($transient_ttls) === [86400, 86400, 60, 60], 'Current period statistics honor the configured General cache while completed months are retained longer');
plugin_asm_builtin_summary(report_request());
ok(count($requests) === 3, 'A repeat visitor reuses cached yearly statistics');
$past = plugin_asm_builtin_summary(report_request(2025));
ok($past[0]['cats_adopted'] === 60 && count($requests) === 15, 'A historical year aggregates all twelve months only when requested');
$count = $routes['/in-care-count'](new WP_REST_Request())->get_data();
ok($count === ['count' => 2, 'source' => 'asm_shelter_animals'], 'In-care counts use all shelter animals, filter cats and expose only the aggregate');
ok(!isset(end($requests)['sensitive']), 'Personal data is never opted into at the ASM endpoint');
$settings['proxy']['account'] = 'second-fixture-account';
$fail_month = 2;
$partial = plugin_asm_builtin_summary(report_request());
ok($partial[0]['cats_brought_in'] === null && $partial[0]['cats_adopted'] === null, 'One failed month cannot become a misleading partial annual total');
ok(count($requests) === 18, 'Changing rescue accounts invalidates the aggregate cache');
$shelter_invalid = true;
$unknown = $routes['/in-care-count'](new WP_REST_Request())->get_data();
ok($unknown['count'] === null, 'Unknown species data cannot become an invented zero in care');
$bad = monthly_fixture(1, 2026); unset($bad[0]['liveIntake']['adult']['strayAtLarge']);
ok(plugin_asm_monthly_impact($bad, 1, 2026) === null, 'An incomplete count structure is rejected');
ok(plugin_asm_monthly_impact(monthly_fixture(1, 2026), 2, 2026) === null, 'Month/year/species identity must match the requested statistics');
$settings['global']['stats_data_mode'] = 'custom_report';
$custom = $routes['/report'](report_request())->get_data();
ok($custom[0]['CATS_VACCINATED'] === 12 && end($requests)['method'] === 'json_report', 'Optional custom reports retain additional health metrics');
$custom_count = $routes['/in-care-count'](new WP_REST_Request())->get_data();
ok($custom_count['count'] === 7, 'Optional legacy in-care reports remain usable');
$derived = plugin_suite_summary_from_adoptions_array([
  ['ANIMALID' => 1, 'SPECIESID' => 2, 'MOVEMENTDATE' => '31/12/2025'],
  ['ANIMALID' => 1, 'SPECIESID' => 2, 'MOVEMENTDATE' => '31/12/2025'],
  ['ANIMALID' => 2, 'SPECIESID' => 2, 'MOVEMENTDATE' => '2026-01-01T00:30:00+14:00'],
  ['ANIMALID' => 3, 'SPECIESNAME' => 'Dog', 'MOVEMENTDATE' => '2026-01-02'],
  ['ANIMALID' => 4, 'SPECIESID' => 2, 'MOVEMENTDATE' => 'not a date'],
]);
ok(count($derived) === 2 && $derived[0]['year'] === 2026 && $derived[0]['cats_adopted'] === 1 && $derived[1]['cats_adopted'] === 1, 'Record fallback handles UK dates, explicit local years, duplicate animals and species filtering');
ok($derived[0]['cats_brought_in'] === null && $derived[0]['source'] === 'adoption_records', 'Record fallback labels its limited source and does not fabricate intake');
$fallback = plugin_suite_fallback_report('custom_api', 'Cats In Care Now', new WP_REST_Request());
ok($fallback[0]['cats_in_care'] === null, 'Adoptable listings are never relabelled as all animals in care');
$before = count($requests);
ok(plugin_asm_get_reservations() === [] && count($requests) === $before, 'Default adoptable views do not request an optional reservation report');
$settings['global']['asm_reservation_report_enabled'] = 1;
$reservations = plugin_asm_get_reservations();
ok(($reservations[42]['reservation_statuses'] ?? []) === ['Pending Adoption'] && end($requests)['title'] === 'Active Reservations Proxy', 'Advanced reservation details can use an existing optional report');
$settings['global']['stats_data_mode'] = 'built_in';
$settings['global']['cache_stats_seconds'] = 0;
$fail_month = 0;
$before = count($requests);
plugin_asm_builtin_summary(report_request()); plugin_asm_builtin_summary(report_request());
ok(count($requests) === $before + 6, 'Zero statistics cache seconds bypasses successful current-year and monthly caches immediately');
$settings['global']['cache_stats_seconds'] = 60;
$settings['proxy']['account'] = 'auto-full-report';
$summary_report = [['YEAR' => 2026, 'CATS_BROUGHT_IN' => '43', 'CATS_ADOPTED' => 56, 'CATS_VACCINATED' => 41, 'CATS_NEUTERED' => 0, 'CATS_CHIPPED' => 38, 'PRIVATE_OWNER' => 'do not expose this column']];
$before = count($requests);
$automatic = $routes['/report'](report_request())->get_data()[0];
ok($automatic['cats_vaccinated'] === 41 && $automatic['cats_neutered'] === 0 && $automatic['cats_chipped'] === 38, 'Automatic mode restores existing annual health totals, including explicit zeros');
ok($automatic['cats_brought_in'] === 43 && $automatic['cats_adopted'] === 56 && count($requests) === $before + 1, 'A valid existing full report preserves earlier totals and avoids redundant monthly requests');
ok(strpos(json_encode([$automatic, $transients]), 'do not expose this column') === false, 'Automatic report discovery stores and exposes only validated aggregate fields');
$routes['/report'](report_request());
ok(count($requests) === $before + 1, 'A cached existing report is reused without an upstream probe');
$settings['proxy']['account'] = 'auto-partial-report';
$summary_report = [['YEAR' => 2026, 'CATS_VACCINATED' => 7, 'CATS_NEUTERED' => 'unknown', 'CATS_CHIPPED' => 0]];
$automatic = $routes['/report'](report_request())->get_data()[0];
ok($automatic['cats_brought_in'] === 60 && $automatic['cats_adopted'] === 15 && $automatic['cats_vaccinated'] === 7 && $automatic['cats_neutered'] === null, 'Partial reports augment built-in totals without turning malformed health values into zero');
$settings['proxy']['account'] = 'auto-missing-report';
$summary_report = false;
$before = count($requests);
$automatic = $routes['/report'](report_request())->get_data()[0];
$routes['/report'](report_request());
ok($automatic['cats_adopted'] === 15 && $automatic['cats_chipped'] === null && count($requests) === $before + 4, 'Missing optional reports fall back to built-ins and are not probed on every view');
$settings['proxy']['account'] = 'auto-other-year';
$summary_report = [['YEAR' => 2025, 'CATS_VACCINATED' => 900]];
$automatic = $routes['/report'](report_request())->get_data()[0];
ok($automatic['cats_vaccinated'] === null, 'Health totals from a different year cannot populate the selected year');
