<?php
$root = dirname(__DIR__);
$core = file_get_contents($root . '/includes/core/class-plugin.php');
$updater = file_get_contents($root . '/includes/core/class-updater.php');
$payments = file_get_contents($root . '/includes/modules/payments/class-module.php');
$js = file_get_contents($root . '/assets/js/admin-sortable.js');
$errors = [];
function ok($cond, $msg){ global $errors; if (!$cond) $errors[] = $msg; }
ok(substr_count($core, "'slug'=>'plugin-ui-suite'") === 1, 'Expected one suite settings admin registration.');
ok(strpos($core, "add_submenu_page('options-general.php','Rescue Plugin Suite Updates'") === false, 'Updates submenu must not be registered.');
ok(strpos($core, "add_submenu_page('options-general.php','Payments:") === false, 'Payments submenus must not be registered.');
ok(strpos($core, "'data-source'=>'Integrations'") !== false, 'Integrations tab missing.');
ok(strpos($core, "'registry'=>'Registry'") !== false && strpos($core, 'developer_mode_enabled') !== false, 'Registry tab must remain developer-only.');
ok(strpos($core, "'updates'=>'Updates'") !== false, 'Updates tab missing.');
ok(strpos($core, "'layout'=>'Layout builder','adopted'") === false, 'Standalone layout tab still registered.');
ok(strpos($updater, "const REPO = 'JayS67/Rescue-Plugin-Suite'") !== false, 'Canonical repository constant missing or wrong.');
ok(strpos($updater, 'JordanSutton/Rescue-Plugin-Suite') === false, 'Old hard-coded repository remains.');
ok(strpos($payments, "'payments_section'=>\$tab_id") !== false, 'Payments subtab URLs must stay inside suite page.');
ok(strpos($core, "plugin-ui-suite-payments-") !== false && strpos($core, 'redirect_legacy_admin_urls') !== false, 'Legacy payment redirects missing.');
ok(strpos($js, 'data-sortable-input') !== false && strpos($js, "join('\\n')") !== false, 'Sortable hidden field sync missing.');
ok(strpos($js, "ArrowUp") !== false && strpos($js, "ArrowDown") !== false, 'Keyboard reorder support missing.');
if ($errors) { fwrite(STDERR, implode("\n", $errors) . "\n"); exit(1); }
echo "Static admin IA checks passed\n";
