<?php
/** Disposable WordPress regression for preserving existing display choices. */
if (!defined('ABSPATH')) exit(2);
$original = get_option(Plugin_UI_Suite_Plugin::OPT_KEY);
$defaults = get_option(Plugin_UI_Suite_Plugin::USER_DEFAULTS_KEY);
$admin = get_users(['role'=>'administrator','number'=>1]);
wp_set_current_user($admin[0]->ID);
try {
  delete_option(Plugin_UI_Suite_Plugin::USER_DEFAULTS_KEY);
  $legacy = $original;
  unset($legacy['adoptables']['layout_preset'], $legacy['adoptables']['card_design'], $legacy['adopted']['layout_preset'], $legacy['adopted']['card_design'], $legacy['stats']['layout_preset']);
  $legacy['adoptables']['display_style']='compact';
  $legacy['adopted']['display_style']='list';
  $legacy['stats']['layout_mode']='one_row';
  update_option(Plugin_UI_Suite_Plugin::OPT_KEY, $legacy);
  Plugin_UI_Suite_Plugin::ensure_defaults_loaded();
  $saved = get_option(Plugin_UI_Suite_Plugin::OPT_KEY);
  if ($saved['adoptables']['card_design'] !== 'compact' || $saved['adopted']['layout_preset'] !== 'list' || $saved['stats']['layout_preset'] !== 'compact') throw new RuntimeException('Admin default filling lost existing presentation choices.');
  $saved['adoptables']['layout_preset']='blog';
  $saved['adoptables']['card_design']='thumbnail';
  update_option(Plugin_UI_Suite_Plugin::OPT_KEY,$saved);
  Plugin_UI_Suite_Plugin::ensure_defaults_loaded();
  $current=Plugin_UI_Suite_Plugin::get_settings();
  if ($current['adoptables']['layout_preset']!=='blog' || $current['adoptables']['card_design']!=='thumbnail') throw new RuntimeException('Explicit presentation choices were replaced.');
  echo "PASS existing layout migration and explicit design preservation.\n";
} finally {
  update_option(Plugin_UI_Suite_Plugin::OPT_KEY,$original);
  if ($defaults === false) delete_option(Plugin_UI_Suite_Plugin::USER_DEFAULTS_KEY); else update_option(Plugin_UI_Suite_Plugin::USER_DEFAULTS_KEY,$defaults);
}
