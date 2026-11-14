<?php
/** Fixture only: wp eval-file tests/settings-save.php quiz|featured|forms|global|stats */
if (!defined('ABSPATH') || !class_exists('Plugin_UI_Suite_Plugin')) throw new RuntimeException('Run in a disposable WordPress installation.');
wp_set_current_user(1);
remove_all_filters('wp_redirect');
add_filter('wp_redirect', static function() { return false; });
$tab = $args[0] ?? 'quiz';
$original = get_option(Plugin_UI_Suite_Plugin::OPT_KEY);
$seed = Plugin_UI_Suite_Plugin::get_settings();
$seed['quiz']['quiz_enabled'] = 1; $seed['quiz']['q3_hide'] = 1; $seed['quiz']['quiz_title_text'] = 'Preserve this quiz title';
$seed['featured']['enabled'] = 1; $seed['featured']['mode'] = 'manual'; $seed['featured']['manual_id'] = '42';
$seed['global']['delete_data_on_uninstall'] = 1;
update_option(Plugin_UI_Suite_Plugin::OPT_KEY, $seed);
$input = [
  'quiz'=>['quiz'=>['q2_text'=>'Saved new question']],
  'featured'=>['featured'=>['title_text'=>'Saved new title']],
  'forms'=>['forms'=>[]],
  'global'=>['global'=>['widget_transparent_background'=>'1','widget_show_titles'=>'0','widget_show_subtitles'=>'0','widget_show_footers'=>'0']],
  'stats'=>['stats'=>['card_order'=>"adopted\nbrought"]],
];
if (!isset($input[$tab])) throw new RuntimeException('Unknown test scenario.');
register_shutdown_function(static function() use ($tab, $seed, $original) {
  $saved = Plugin_UI_Suite_Plugin::get_settings();
  update_option(Plugin_UI_Suite_Plugin::OPT_KEY, $original);
  $good = true;
  if ($tab === 'quiz') $good = $saved['quiz']['quiz_title_text'] === $seed['quiz']['quiz_title_text'] && $saved['quiz']['q3_hide'] === 1 && $saved['quiz']['quiz_enabled'] === 1 && $saved['quiz']['q2_text'] === 'Saved new question';
  if ($tab === 'featured') $good = $saved['featured']['enabled'] === 1 && $saved['featured']['mode'] === 'manual' && $saved['featured']['manual_id'] === '42';
  if ($tab === 'forms') $good = $saved['forms']['items'] === $seed['forms']['items'];
  if ($tab === 'global') $good = $saved['global']['delete_data_on_uninstall'] === 1 && $saved['global']['widget_transparent_background'] === 1 && $saved['global']['widget_show_titles'] === 0;
  if ($tab === 'stats') $good = $saved['stats']['card_order'] === "adopted\nbrought" && $saved['stats']['builder_card_order'] === "adopted\nbrought";
  if (!$good) { fwrite(STDERR, "FAIL $tab: partial save lost existing settings.\n"); exit(1); }
  echo "PASS $tab: submitted values saved; other subtabs preserved.\n";
});
$_POST = ['active_tab'=>$tab, 'active_subtab'=>'appearance', 'suite'=>$input[$tab], '_wpnonce'=>wp_create_nonce('plugin_ui_suite_save')];
$_REQUEST = $_POST;
Plugin_UI_Suite_Plugin::handle_save();
