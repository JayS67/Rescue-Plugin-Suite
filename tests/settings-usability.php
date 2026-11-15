<?php
/** Run in a disposable installed WordPress site: wp eval-file tests/settings-usability.php */
if (!defined('ABSPATH') || !class_exists('Plugin_UI_Suite_Plugin')) { fwrite(STDERR, "Run with WordPress and the plugin active.\n"); exit(2); }
$admins = get_users(['role'=>'administrator', 'number'=>1]);
if (!$admins) throw new RuntimeException('The fixture needs an administrator account.');
wp_set_current_user($admins[0]->ID);
$check = static function($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$settings = Plugin_UI_Suite_Plugin::get_settings();
$panels = [
  'global'=>[''],
  'data-source'=>['asm','custom-api','shelterluv','petpoint'],
  'adoptables'=>['design','text','responsive','typography','modal','labels','sharing','filters','layout','embed'],
  'adopted'=>['design','text','responsive','typography','modal','embed'],
  'stats'=>['design','text','responsive','typography','cards','embed'],
  'featured'=>['dashboard','appearance','filters','embed','analytics','help'],
  'quiz'=>['settings','questions','preview'],
  'forms'=>[''],
  'shop'=>['shop','payments','email'],
];
$no_op_names = ['suite[featured][layout_order]', 'suite[quiz][age_categories]', 'suite[quiz][question_order]', 'suite[quiz][answer_mappings]', 'suite[forms][layout_order]', 'suite[forms][platform_support_notes]', 'suite[global][supported_species]', 'suite[global][enabled_species]', 'suite[adopted][builder_card_order]', 'suite[adopted][builder_modal_order]', 'suite[stats][rows_mobile]', 'suite[stats][rows_tablet]', 'suite[stats][rows_desktop]', 'suite[proxy][api_key]'];
$panel_count = 0; $control_count = 0;
$previous = libxml_use_internal_errors(true);
try {
  foreach ($panels as $tab=>$subtabs) foreach ($subtabs as $subtab) {
    $_GET = ['page'=>'plugin-ui-suite','tab'=>$tab,'subtab'=>$subtab];
    if ($tab === 'shop') $_GET += ['shop_section'=>'settings','shop_tab'=>$subtab];
    ob_start(); Plugin_UI_Suite_Plugin::render_settings_page(); $html = ob_get_clean();
    $check(strpos($html, 'rps-settings-content') !== false, "$tab/$subtab did not render settings");
    foreach ($no_op_names as $name) $check(strpos($html, 'name="' . $name . '"') === false, "$tab/$subtab still exposes unused $name");
    $doc = new DOMDocument(); $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html); $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//input|//select|//textarea') as $control) {
      $type = strtolower($control->getAttribute('type'));
      if (in_array($type,['hidden','button','submit'],true) || strpos($control->getAttribute('style'),'display:none') !== false) continue;
      $label = trim($control->getAttribute('aria-label'));
      foreach (preg_split('/\s+/', trim($control->getAttribute('aria-labelledby'))) as $id) {
        if ($id !== '' && ($heading = $doc->getElementById($id))) $label .= trim($heading->textContent);
      }
      for ($ancestor=$control->parentNode;$ancestor;$ancestor=$ancestor->parentNode) if (strtolower($ancestor->nodeName)==='label') $label .= trim($ancestor->textContent);
      $id = $control->getAttribute('id');
      if ($id !== '') foreach ($xpath->query('//label') as $explicit) if ($explicit->getAttribute('for')===$id) $label .= trim($explicit->textContent);
      $check(trim($label) !== '', "$tab/$subtab has an unnamed control: " . $control->getAttribute('name'));
      $control_count++;
    }
    $navigation = $xpath->query('//nav[contains(@class,"plugin-suite-subnav")]');
    foreach ($navigation as $nav) {
      $check($nav->getAttribute('aria-label') !== '', "$tab sections have no accessible name");
      $active = 0; foreach ($nav->getElementsByTagName('a') as $link) if ($link->getAttribute('aria-current')==='page') $active++;
      $check($active===1, "$tab sections must identify exactly one current page");
    }
    $panel_count++;
  }
  foreach (['adopted'=>'layout','featured'=>'layouts','quiz'=>'mapping','stats'=>'layout'] as $tab=>$stale) {
    $_GET=['page'=>'plugin-ui-suite','tab'=>$tab,'subtab'=>$stale];
    ob_start(); Plugin_UI_Suite_Plugin::render_settings_page(); $html=ob_get_clean();
    $check(strpos($html,'aria-current="page"')!==false && strpos($html,'Save changes')!==false, "Stale $tab/$stale link must fall back to a usable panel");
  }
} finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
echo "Settings audit passed: $panel_count panels, $control_count named controls; retired controls hidden and stale section links handled.\n";
