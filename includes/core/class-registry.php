<?php
if (!defined('ABSPATH')) exit;

/**
 * Central registry framework for Rescue Plugin Suite modules.
 *
 * Modules register metadata here so navigation, settings, integrations,
 * analytics, help, migrations and assets can be discovered without each module
 * rebuilding those systems independently.
 */
final class Plugin_UI_Suite_Registry {
  private static $cache = [];
  private static $items = [
    'modules'=>[], 'settings'=>[], 'navigation'=>[], 'widgets'=>[], 'integrations'=>[],
    'analytics'=>[], 'permissions'=>[], 'help'=>[], 'notifications'=>[], 'assets'=>[],
    'migrations'=>[], 'updates'=>[], 'setup_steps'=>[],
  ];
  public static function register($type, $id, array $metadata) {
    $type = sanitize_key($type); $id = sanitize_key($id);
    if (!isset(self::$items[$type])) self::$items[$type] = [];
    self::$items[$type][$id] = wp_parse_args($metadata, ['id'=>$id]);
    self::$cache = [];
  }
  public static function all($type) { $type = sanitize_key($type); return self::$items[$type] ?? []; }
  public static function get($type, $id, $default=null) { $type=sanitize_key($type); $id=sanitize_key($id); return self::$items[$type][$id] ?? $default; }
  public static function register_module($id, array $metadata) { self::register('modules', $id, $metadata); }
  public static function register_setting($id, array $metadata) {
    self::register('settings', $id, wp_parse_args($metadata, [
      'module'=>'global','page'=>'global','section'=>'general','field_id'=>$id,'label'=>$id,
      'description'=>'','field_type'=>'text','default'=>null,'validation_callback'=>null,
      'sanitisation_callback'=>'sanitize_text_field','conditional_visibility'=>[], 'dependencies'=>[],
      'capability'=>'manage_options','exportable'=>true,'sensitive'=>false,'search_keywords'=>[],
      'tooltip'=>'','help_text'=>'',
    ]));
  }
  public static function search_settings($query) {
    $query = strtolower(trim((string)$query)); if ($query === '') return [];
    return array_filter(self::all('settings'), function($setting) use ($query) {
      $haystack = strtolower(implode(' ', array_filter([
        $setting['module'] ?? '', $setting['page'] ?? '', $setting['section'] ?? '', $setting['field_id'] ?? '',
        $setting['label'] ?? '', $setting['description'] ?? '', $setting['tooltip'] ?? '', $setting['help_text'] ?? '',
        implode(' ', (array)($setting['search_keywords'] ?? [])),
      ])));
      return strpos($haystack, $query) !== false;
    });
  }
  public static function module_enabled($id) {
    $module = self::get('modules', $id, []);
    $flags = wp_parse_args($module['flags'] ?? [], ['installed'=>true,'enabled'=>true,'hidden'=>false,'experimental'=>false,'beta'=>false,'deprecated'=>false,'future_premium'=>false]);
    return !empty($flags['installed']) && !empty($flags['enabled']) && empty($flags['hidden']) && empty($flags['future_premium']);
  }
  public static function register_navigation($id, array $metadata) { self::register('navigation', $id, $metadata); }
  public static function navigation_items($context='admin') {
    if (isset(self::$cache['navigation_'.$context])) return self::$cache['navigation_'.$context];
    $items = array_filter(self::all('navigation'), function($item) use ($context) {
      if (($item['context'] ?? 'admin') !== $context) return false;
      $module = $item['module'] ?? '';
      return $module === '' || self::module_enabled($module);
    });
    uasort($items, function($a,$b){ return (int)($a['order'] ?? 100) <=> (int)($b['order'] ?? 100); });
    self::$cache['navigation_'.$context] = $items;
    return $items;
  }
  public static function add_admin_menus() {
    foreach (self::navigation_items('admin') as $item) {
      if (!empty($item['parent_slug'])) {
        add_submenu_page($item['parent_slug'], $item['page_title'] ?? $item['label'], $item['menu_title'] ?? $item['label'], $item['capability'] ?? 'manage_options', $item['slug'], $item['callback']);
      } else {
        add_options_page($item['page_title'] ?? $item['label'], $item['menu_title'] ?? $item['label'], $item['capability'] ?? 'manage_options', $item['slug'], $item['callback']);
      }
    }
  }
  public static function render_tabs($active_slug) {
    echo '<div class="plugin-suite-tabs">';
    foreach (self::navigation_items('tab') as $item) {
      $href = add_query_arg(['page'=>$item['parent_page'] ?? 'plugin-ui-suite','tab'=>$item['slug']], admin_url('options-general.php'));
      printf('<a class="plugin-suite-tab %s" href="%s">%s</a>', $active_slug === $item['slug'] ? 'active' : '', esc_url($href), esc_html($item['label']));
    }
    echo '</div>';
  }
  public static function settings_for_page($page) {
    return array_filter(self::all('settings'), function($setting) use ($page) {
      if (($setting['page'] ?? '') !== $page) return false;
      $module = $setting['module'] ?? '';
      return $module === '' || self::module_enabled($module);
    });
  }
  public static function render_settings_page($page, array $values, $form_action, $nonce_action, $option_name='suite') {
    $settings = self::settings_for_page($page);
    if (empty($settings)) { echo '<p>'.esc_html__('No registry settings are available for this page yet.', 'plugin-ui-suite').'</p>'; return; }
    $sections = [];
    foreach ($settings as $id=>$setting) $sections[$setting['section'] ?? 'general'][$id] = $setting;
    echo '<form method="post" action="'.esc_url($form_action).'">'; wp_nonce_field($nonce_action);
    echo '<input type="hidden" name="action" value="plugin_ui_suite_save" /><input type="hidden" name="active_tab" value="'.esc_attr($page).'" />';
    foreach ($sections as $section=>$fields) {
      echo '<div class="plugin-suite-card"><h2>'.esc_html(ucwords(str_replace(['_','-'],' ', $section))).'</h2><table class="form-table" role="presentation">';
      foreach ($fields as $field) self::render_field($field, $values, $option_name);
      echo '</table></div>';
    }
    submit_button(__('Save changes', 'plugin-ui-suite'));
    echo '</form>';
  }
  private static function render_field(array $field, array $values, $option_name) {
    $module = $field['module'] ?? 'global'; $field_id = $field['field_id'] ?? ''; if ($field_id === '') return;
    $value = $values[$module][$field_id] ?? ($field['default'] ?? '');
    $name = $option_name.'['.$module.']['.$field_id.']';
    $attrs = '';
    foreach ((array)($field['conditional_visibility'] ?? []) as $key=>$expected) $attrs .= ' data-visible-'.esc_attr($key).'="'.esc_attr((string)$expected).'"';
    $html = '';
    switch ($field['field_type'] ?? 'text') {
      case 'checkbox': $html = '<label><input type="checkbox" name="'.esc_attr($name).'" value="1" '.checked($value,1,false).$attrs.' /> '.esc_html($field['description'] ?? '').'</label>'; break;
      case 'textarea': $html = '<textarea class="large-text" rows="4" name="'.esc_attr($name).'"'.$attrs.'>'.esc_textarea((string)$value).'</textarea>'; break;
      case 'select': $html = '<select name="'.esc_attr($name).'"'.$attrs.'>'; foreach ((array)($field['options'] ?? []) as $k=>$label) $html .= '<option value="'.esc_attr($k).'" '.selected($value,$k,false).'>'.esc_html($label).'</option>'; $html .= '</select>'; break;
      case 'number': $html = '<input type="number" class="small-text" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'"'.$attrs.' />'; break;
      case 'color': $html = '<input type="color" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'"'.$attrs.' />'; break;
      case 'password': $html = '<input type="password" class="regular-text" autocomplete="new-password" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'"'.$attrs.' />'; break;
      default: $html = '<input type="text" class="regular-text" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'"'.$attrs.' />';
    }
    if (!empty($field['tooltip'])) $html .= ' <span class="dashicons dashicons-editor-help" title="'.esc_attr($field['tooltip']).'"></span>';
    if (!empty($field['help_text'])) $html .= '<p class="description">'.esc_html($field['help_text']).'</p>';
    echo '<tr><th scope="row"><label>'.esc_html($field['label'] ?? $field_id).'</label></th><td>'.$html.'</td></tr>';
  }
  public static function sanitize_registered_values($page, array $input, array $current) {
    foreach (self::settings_for_page($page) as $setting) {
      $module = $setting['module'] ?? ''; $field = $setting['field_id'] ?? ''; if ($module === '' || $field === '') continue;
      if (!array_key_exists($module, $input) || !array_key_exists($field, (array)$input[$module])) continue;
      $value = $input[$module][$field];
      $callback = $setting['sanitisation_callback'] ?? null;
      if (is_callable($callback)) $value = call_user_func($callback, $value);
      elseif (($setting['field_type'] ?? '') === 'checkbox') $value = !empty($value) ? 1 : 0;
      elseif (($setting['field_type'] ?? '') === 'number') $value = is_numeric($value) ? 0 + $value : ($setting['default'] ?? 0);
      else $value = sanitize_text_field($value);
      $current[$module][$field] = $value;
    }
    return $current;
  }


  public static function help_items() {
    $items = self::all('help');
    uasort($items, function($a,$b){ return (int)($a['order'] ?? 100) <=> (int)($b['order'] ?? 100); });
    return $items;
  }
  public static function setup_steps() {
    $steps = array_filter(self::all('setup_steps'), function($step){ $module = $step['module'] ?? ''; return $module === '' || self::module_enabled($module); });
    uasort($steps, function($a,$b){ return (int)($a['order'] ?? 100) <=> (int)($b['order'] ?? 100); });
    return $steps;
  }
  public static function integration_items($type='') {
    $items = array_filter(self::all('integrations'), function($item) use ($type){ return ($type === '' || ($item['type'] ?? '') === $type) && self::module_enabled($item['module'] ?? ''); });
    uasort($items, function($a,$b){ return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')); });
    return $items;
  }

  public static function export_settings(array $settings, $include_sensitive=false) {
    $registered = self::all('settings'); $export = [];
    foreach ($registered as $setting) {
      if (empty($setting['exportable']) || (!empty($setting['sensitive']) && !$include_sensitive)) continue;
      $module = $setting['module'] ?? ''; $field = $setting['field_id'] ?? '';
      if ($module !== '' && $field !== '' && isset($settings[$module][$field])) $export[$module][$field] = $settings[$module][$field];
    }
    return $export;
  }
}
