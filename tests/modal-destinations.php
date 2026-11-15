<?php
/** Run with wp eval-file against a disposable WordPress fixture. */
if (!defined('ABSPATH') || !class_exists('Plugin_Adopted_UI_Shortcode')) {
  throw new RuntimeException('Load this regression with WordPress and Rescue Plugin Suite active.');
}
function rps_modal_assert($condition, $message) {
  if (!$condition) throw new RuntimeException($message);
  echo "PASS $message\n";
}
$tag = 'rps_modal_destination_fixture';
$page_ids = [];
$key = 'rps_adoptables_page_' . md5($tag);
$settings = ['shortcodes'=>['adoptables'=>$tag], 'global'=>['adoptables_page_url'=>'']];
add_shortcode($tag, '__return_empty_string');
delete_transient($key);
try {
  $resolve = [Plugin_Adopted_UI_Shortcode::class, 'resolve_adoptables_page_url'];
  $configured = $settings;
  $configured['global']['adoptables_page_url'] = 'https://example.test/available/';
  rps_modal_assert(call_user_func($resolve, ['adoptables_cta_url'=>'https://example.test/override/'], $configured) === 'https://example.test/override/', 'A specific adoption link overrides the General display page.');
  rps_modal_assert(call_user_func($resolve, [], $configured) === 'https://example.test/available/', 'An empty override inherits the General display page.');
  rps_modal_assert(call_user_func($resolve, ['adoptables_cta_url'=>'javascript:alert(1)'], $configured) === 'https://example.test/available/', 'An unsafe override cannot become the adoption link.');
  $draft_id = wp_insert_post(['post_type'=>'page', 'post_status'=>'draft', 'post_title'=>'Unpublished animal page', 'post_content'=>'[' . $tag . ']']);
  $page_ids[] = $draft_id;
  // Remove other supported aliases from this test's discovery search without changing their pages.
  $exclude_other_pages = static function ($query) use ($tag) {
    if (!$query->is_main_query() && in_array($query->get('s'), ['plugin_adoptables_ui', 'asm-suite/adoptables'], true)) $query->set('post__in', [-1]);
  };
  add_action('pre_get_posts', $exclude_other_pages);
  rps_modal_assert(call_user_func($resolve, [], $settings) === '', 'An unpublished page never creates a broken public adoption destination.');
  $published_id = wp_insert_post(['post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Available animal test page', 'post_content'=>'[' . $tag . ']']);
  $page_ids[] = $published_id;
  delete_transient($key);
  rps_modal_assert(call_user_func($resolve, [], $settings) === get_permalink($published_id), 'A published page using the configured widget shortcode is discovered.');
  wp_update_post(['ID'=>$published_id, 'post_status'=>'draft']);
  rps_modal_assert(call_user_func($resolve, [], $settings) === '', 'A cached page stops being a destination after it is unpublished.');
  remove_action('pre_get_posts', $exclude_other_pages);

  $cta_enabled = static function ($options) { return array_merge((array)$options, ['adoptables_cta_enabled'=>1, 'adoptables_cta_url'=>'https://example.test/available/', 'adoptables_cta_text'=>'Looking for your next best friend?']); };
  add_filter('option_plugin_adopted_ui_options', $cta_enabled);
  $html = Plugin_Adopted_UI_Shortcode::render_shortcode();
  remove_filter('option_plugin_adopted_ui_options', $cta_enabled);
  rps_modal_assert(strpos($html, 'href="https://example.test/available/"') !== false && strpos($html, 'Looking for your next best friend?') !== false, 'An enabled adopted modal renders its invitation and working destination.');
  $cta_disabled = static function ($options) { return array_merge((array)$options, ['adoptables_cta_enabled'=>0]); };
  add_filter('option_plugin_adopted_ui_options', $cta_disabled);
  $html = Plugin_Adopted_UI_Shortcode::render_shortcode();
  remove_filter('option_plugin_adopted_ui_options', $cta_disabled);
  rps_modal_assert(strpos($html, 'id="asm-plugin-adoptables-cta"') === false, 'An administrator can hide the adopted modal invitation.');
} finally {
  foreach ($page_ids as $id) wp_delete_post($id, true);
  delete_transient($key);
  remove_shortcode($tag);
  if (isset($exclude_other_pages)) remove_action('pre_get_posts', $exclude_other_pages);
  if (isset($cta_enabled)) remove_filter('option_plugin_adopted_ui_options', $cta_enabled);
  if (isset($cta_disabled)) remove_filter('option_plugin_adopted_ui_options', $cta_disabled);
}
