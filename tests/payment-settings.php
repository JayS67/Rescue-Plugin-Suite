<?php
/** wp eval-file tests/payment-settings.php -- fixture only, no gateway calls or stored changes. */
if (!defined('ABSPATH') || !class_exists('Plugin_Payments_Module')) throw new RuntimeException('Run in WordPress with the suite active.');
$check = static function($pass, $message) { if (!$pass) throw new RuntimeException($message); echo "ok: $message\n"; };
$s = Plugin_Payments_Module::defaults(); $s['gift_aid']['enabled'] = 0;
$check(Plugin_Payments_Module::gift_aid_selection($s, ['gift_aid'=>true], true) === false, 'Disabled Gift Aid cannot be forced through POST');
$s['gift_aid']['enabled'] = 1; $s['gift_aid']['required'] = 1;
$check(Plugin_Payments_Module::gift_aid_selection($s, ['gift_aid'=>false], true) === false, 'Gift Aid respects provider capabilities');
$check(is_wp_error(Plugin_Payments_Module::gift_aid_selection($s, ['gift_aid'=>true], false)), 'Required declaration is validated server-side before checkout');
$check(Plugin_Payments_Module::gift_aid_selection($s, ['gift_aid'=>true], true) === true, 'Required consent can be provided');
$s['gift_aid']['required'] = 0;
$check(Plugin_Payments_Module::gift_aid_selection($s, ['gift_aid'=>true], false) === false, 'Optional Gift Aid remains optional');
$s['gift_aid']['wording'] = 'Fixture consent wording'; $s['gift_aid']['declaration'] = 'Fixture declaration text';
$filter = static function($settings) use (&$s) { return $s; };
add_filter('plugin_payments_widget_settings', $filter);
$html = Plugin_Payments_Module::render_shortcode();
$check(strpos($html, 'Fixture consent wording') !== false && strpos($html, 'Fixture declaration text') !== false, 'Configured wording and declaration render together');
$s['gift_aid']['enabled'] = 0; $html = Plugin_Payments_Module::render_shortcode();
$check(strpos($html, 'data-payment-gift-aid') === false, 'Disabled Gift Aid removes the checkbox');
remove_filter('plugin_payments_widget_settings', $filter);
