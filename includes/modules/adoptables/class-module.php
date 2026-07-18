<?php
/**
 * Plugin Name: Rescue Plugin Suite Adoptables
 * Description: Provides [adoptables] shortcode for the adoptable/adopted widget with scoped styling and settings.
 * Version: 1.1.12
 * Author: Jordan Sutton | Webstax
 */

if (!defined('ABSPATH')) exit;

final class Plugin_Adoptables_UI_Shortcode {
  const SHORTCODE = 'adoptables';
  const TAILWIND_HANDLE = 'plugin-adoptables-tailwind';
  const ROBOTO_HANDLE   = 'plugin-adoptables-roboto';

  const OPT_KEY = 'plugin_adoptables_ui_options';
  const RESET_ACTION = 'plugin_adoptables_ui_reset_field';

  public static function init() {
    add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'conditionally_enqueue_assets'], 20);

    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'admin_init']);
    add_action('admin_post_' . self::RESET_ACTION, [__CLASS__, 'handle_reset']);
    add_action('wp_ajax_plugin_load_apply_form', [__CLASS__, 'ajax_load_apply_form']);
    add_action('wp_ajax_nopriv_plugin_load_apply_form', [__CLASS__, 'ajax_load_apply_form']);
  }

  private static function normalise_shortcode_setting($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if ($value[0] !== '[') $value = '[' . $value . ']';
    return $value;
  }

  public static function ajax_load_apply_form() {
    check_ajax_referer('plugin_apply_form', 'nonce');
    $o = self::get_options();
    // The Suite settings page is the source of truth. Fall back to the legacy
    // module option so older installs continue to work.
    $suite = get_option('plugin_ui_suite_settings_v83', []);
    $suite_adoptables = (is_array($suite) && isset($suite['adoptables']) && is_array($suite['adoptables'])) ? $suite['adoptables'] : [];
    $enabled = array_key_exists('enable_apply_button', $suite_adoptables)
      ? !empty($suite_adoptables['enable_apply_button'])
      : !empty($o['enable_apply_button']);
    if (!$enabled) wp_send_json_error(['message' => 'Application form is disabled.'], 403);
    $configured = $suite_adoptables['apply_form_shortcode'] ?? ($o['apply_form_shortcode'] ?? '');
    $shortcode = self::normalise_shortcode_setting($configured);
    if ($shortcode === '') wp_send_json_error(['message' => 'No application form shortcode has been configured.'], 400);
    $html = do_shortcode($shortcode);
    if (trim((string)$html) === '' || $html === $shortcode) wp_send_json_error(['message' => 'The configured application form shortcode could not be rendered.'], 400);
    wp_send_json_success(['html' => $html]);
  }

  private static function get_saved_options() {
    $legacy_brand = 'stray' . 'safe';
    foreach ([self::OPT_KEY, $legacy_brand . '_adoptables_ui_options'] as $key) {
      $value = get_option($key, []);
      if (is_array($value) && !empty($value)) return $value;
    }
    return [];
  }

  public static function default_options() {
    return [
      'brand_color'      => '#ff647e',
      'background_color' => '#f9d6dd',

      'paw_opacity' => 0.08,
      'paw_count'   => 10,

      'title_text'    => 'Meet Our Adoptable Animals',
      'subtitle_text' => 'Longest in our care are shown first',
      'footer_text'   => 'Every adoption changes a life. Thank you for supporting rescue work. 🐾',

      'loading_status_text'     => 'Loading adoptable animals…',
      'loading_page_label_text' => 'Loading…',
      'tips_text'               => 'Tip: Click the dark background (outside the card) or press ESC to close.',

      'font_family' => 'Roboto',

      'cats_only' => 1,
      'show_top_navigation' => 1,

      'cols_mobile'  => 2,
      'rows_mobile'  => 3,
      'cols_tablet'  => 3,
      'rows_tablet'  => 3,
      'cols_desktop' => 3,
      'rows_desktop' => 3,

      'gap_x_mobile'  => 20,
      'gap_y_mobile'  => 20,
      'gap_x_tablet'  => 24,
      'gap_y_tablet'  => 24,
      'gap_x_desktop' => 24,
      'gap_y_desktop' => 24,

      'card_scale_mobile'  => 100,
      'card_scale_tablet'  => 100,
      'card_scale_desktop' => 100,

      'card_padding' => 12,
      'card_radius'  => 16,

      'modal_max_width' => 896,

      'fs_heading_mobile'   => 28,
      'fs_heading_tablet'   => 36,
      'fs_heading_desktop'  => 42,

      'fs_subheading_mobile'  => 16,
      'fs_subheading_tablet'  => 18,
      'fs_subheading_desktop' => 20,

      'fs_footer_mobile'  => 14,
      'fs_footer_tablet'  => 16,
      'fs_footer_desktop' => 16,

      'fs_page_label_mobile'  => 13,
      'fs_page_label_tablet'  => 13,
      'fs_page_label_desktop' => 13,

      'fs_modal_name_mobile'  => 18,
      'fs_modal_name_tablet'  => 20,
      'fs_modal_name_desktop' => 20,

      'fs_modal_meta_mobile'  => 14,
      'fs_modal_meta_tablet'  => 14,
      'fs_modal_meta_desktop' => 14,

      'fs_modal_desc_mobile'  => 16,
      'fs_modal_desc_tablet'  => 18,
      'fs_modal_desc_desktop' => 18,

      'fs_tips_mobile'  => 12,
      'fs_tips_tablet'  => 12,
      'fs_tips_desktop' => 12,

      'fw_heading'    => 800,
      'fw_subheading' => 600,
      'fw_footer'     => 500,
      'fw_page_label' => 600,

      'fw_modal_name' => 800,
      'fw_modal_meta' => 600,
      'fw_modal_desc' => 400,
      'fw_tips'       => 600,

      'modal_divider_color'     => '#ff647e',
      'modal_divider_thickness' => 2,
      'modal_divider_radius'    => 999,
      'modal_global_text'       => '',

      'show_reservation_label'    => 1,
      'reservation_pending_label' => 'Pending Adoption',
      'reservation_active_label'  => 'Reserved',
      'reservation_label_halign'  => 'right',
      'reservation_label_valign'  => 'top',
      'card_border_enabled' => 1,
      'card_border_color' => '#401268',
      'card_border_weight' => 2,
      'enable_deep_links' => 1,
      'share_button_text' => 'Share',
      'share_copied_text' => 'Link copied',
      'enable_apply_button' => 0,
      'apply_button_text' => 'Apply',
      'apply_form_shortcode' => 'adoption_form',
      'apply_button_bg_color' => '#401268',
      'apply_button_text_color' => '#ffffff',
      'apply_button_border_color' => '#401268',
      'apply_button_radius' => 16,
      'modal_contact_url' => '',
      'custom_button_1_enabled' => 0, 'custom_button_1_text' => '', 'custom_button_1_url' => '', 'custom_button_1_new_tab' => 0, 'custom_button_1_style' => 'primary',
      'custom_button_2_enabled' => 0, 'custom_button_2_text' => '', 'custom_button_2_url' => '', 'custom_button_2_new_tab' => 0, 'custom_button_2_style' => 'secondary',
      'custom_button_3_enabled' => 0, 'custom_button_3_text' => '', 'custom_button_3_url' => '', 'custom_button_3_new_tab' => 0, 'custom_button_3_style' => 'outline',
    ];
  }

  public static function get_options() {
    $d = self::default_options();
    $saved = self::get_saved_options();
    if (!is_array($saved)) $saved = [];
    return array_merge($d, $saved);
  }

  public static function sanitize_options($input) {
    $d = self::default_options();
    $out = [];

    $out['brand_color']      = isset($input['brand_color']) ? sanitize_hex_color($input['brand_color']) : $d['brand_color'];
    $out['background_color'] = isset($input['background_color']) ? sanitize_hex_color($input['background_color']) : $d['background_color'];

    $po = isset($input['paw_opacity']) ? (float)$input['paw_opacity'] : (float)$d['paw_opacity'];
    if (!is_finite($po)) $po = (float)$d['paw_opacity'];
    $out['paw_opacity'] = max(0.0, min(0.25, $po));

    $pc = isset($input['paw_count']) ? intval($input['paw_count']) : intval($d['paw_count']);
    $out['paw_count'] = max(0, min(80, $pc));

    $out['title_text']    = isset($input['title_text']) ? sanitize_text_field($input['title_text']) : $d['title_text'];
    $out['subtitle_text'] = isset($input['subtitle_text']) ? sanitize_text_field($input['subtitle_text']) : $d['subtitle_text'];
    $out['footer_text']   = isset($input['footer_text']) ? sanitize_textarea_field($input['footer_text']) : $d['footer_text'];

    $out['loading_status_text']     = isset($input['loading_status_text']) ? sanitize_text_field($input['loading_status_text']) : $d['loading_status_text'];
    $out['loading_page_label_text'] = isset($input['loading_page_label_text']) ? sanitize_text_field($input['loading_page_label_text']) : $d['loading_page_label_text'];
    $out['tips_text']               = isset($input['tips_text']) ? sanitize_text_field($input['tips_text']) : $d['tips_text'];

    $font = isset($input['font_family']) ? sanitize_text_field($input['font_family']) : $d['font_family'];
    $font = trim($font);
    if ($font !== '') $font = preg_replace('/[^a-zA-Z0-9 ,\-]/', '', $font);
    $out['font_family'] = $font;

    $out['cats_only'] = isset($input['cats_only']) ? (int)!!$input['cats_only'] : (int)$d['cats_only'];
    $out['show_top_navigation'] = isset($input['show_top_navigation']) ? (int)!!$input['show_top_navigation'] : (int)$d['show_top_navigation'];

    foreach ([
      'cols_mobile','rows_mobile',
      'cols_tablet','rows_tablet',
      'cols_desktop','rows_desktop',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(1, min(6, $v));
    }

    foreach ([
      'gap_x_mobile','gap_y_mobile',
      'gap_x_tablet','gap_y_tablet',
      'gap_x_desktop','gap_y_desktop',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(0, min(80, $v));
    }

    foreach (['card_scale_mobile','card_scale_tablet','card_scale_desktop'] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(50, min(200, $v));
    }

    $cp = isset($input['card_padding']) ? intval($input['card_padding']) : $d['card_padding'];
    $out['card_padding'] = max(0, min(40, $cp));

    $cr = isset($input['card_radius']) ? intval($input['card_radius']) : $d['card_radius'];
    $out['card_radius'] = max(0, min(40, $cr));

    $mw = isset($input['modal_max_width']) ? intval($input['modal_max_width']) : $d['modal_max_width'];
    $out['modal_max_width'] = max(320, min(1400, $mw));

    foreach ([
      'fs_heading_mobile','fs_heading_tablet','fs_heading_desktop',
      'fs_subheading_mobile','fs_subheading_tablet','fs_subheading_desktop',
      'fs_footer_mobile','fs_footer_tablet','fs_footer_desktop',
      'fs_page_label_mobile','fs_page_label_tablet','fs_page_label_desktop',
      'fs_modal_name_mobile','fs_modal_name_tablet','fs_modal_name_desktop',
      'fs_modal_meta_mobile','fs_modal_meta_tablet','fs_modal_meta_desktop',
      'fs_modal_desc_mobile','fs_modal_desc_tablet','fs_modal_desc_desktop',
      'fs_tips_mobile','fs_tips_tablet','fs_tips_desktop',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(10, min(80, $v));
    }

    foreach ([
      'fw_heading','fw_subheading','fw_footer','fw_page_label',
      'fw_modal_name','fw_modal_meta','fw_modal_desc','fw_tips',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(100, min(900, $v));
    }

    $out['modal_divider_color'] = isset($input['modal_divider_color']) ? sanitize_hex_color($input['modal_divider_color']) : $d['modal_divider_color'];

    $mdt = isset($input['modal_divider_thickness']) ? intval($input['modal_divider_thickness']) : $d['modal_divider_thickness'];
    $out['modal_divider_thickness'] = max(1, min(12, $mdt));

    $mdr = isset($input['modal_divider_radius']) ? intval($input['modal_divider_radius']) : $d['modal_divider_radius'];
    $out['modal_divider_radius'] = max(0, min(999, $mdr));

    $out['modal_global_text'] = isset($input['modal_global_text']) ? sanitize_textarea_field($input['modal_global_text']) : $d['modal_global_text'];

    $out['show_reservation_label'] = isset($input['show_reservation_label']) ? (int)!!$input['show_reservation_label'] : (int)$d['show_reservation_label'];
    $out['reservation_pending_label'] = isset($input['reservation_pending_label']) ? sanitize_text_field($input['reservation_pending_label']) : $d['reservation_pending_label'];
    $out['reservation_active_label']  = isset($input['reservation_active_label']) ? sanitize_text_field($input['reservation_active_label']) : $d['reservation_active_label'];

    $allowed_h = ['left', 'center', 'right'];
    $halign = isset($input['reservation_label_halign']) ? sanitize_text_field($input['reservation_label_halign']) : $d['reservation_label_halign'];
    $out['reservation_label_halign'] = in_array($halign, $allowed_h, true) ? $halign : $d['reservation_label_halign'];

    $allowed_v = ['top', 'bottom'];
    $valign = isset($input['reservation_label_valign']) ? sanitize_text_field($input['reservation_label_valign']) : $d['reservation_label_valign'];
    $out['reservation_label_valign'] = in_array($valign, $allowed_v, true) ? $valign : $d['reservation_label_valign'];
    $out['enable_apply_button'] = isset($input['enable_apply_button']) ? (int)!!$input['enable_apply_button'] : (int)$d['enable_apply_button'];
    $out['apply_button_text'] = isset($input['apply_button_text']) ? sanitize_text_field($input['apply_button_text']) : $d['apply_button_text'];
    $out['apply_form_shortcode'] = isset($input['apply_form_shortcode']) ? sanitize_text_field($input['apply_form_shortcode']) : $d['apply_form_shortcode'];
    $out['apply_button_bg_color'] = isset($input['apply_button_bg_color']) ? sanitize_hex_color($input['apply_button_bg_color']) : $d['apply_button_bg_color'];
    $out['apply_button_text_color'] = isset($input['apply_button_text_color']) ? sanitize_hex_color($input['apply_button_text_color']) : $d['apply_button_text_color'];
    $out['apply_button_border_color'] = isset($input['apply_button_border_color']) ? sanitize_hex_color($input['apply_button_border_color']) : $d['apply_button_border_color'];
    $out['apply_button_radius'] = isset($input['apply_button_radius']) ? max(0, min(48, intval($input['apply_button_radius']))) : $d['apply_button_radius'];

    $out['card_border_enabled'] = isset($input['card_border_enabled']) ? (int)!!$input['card_border_enabled'] : (int)$d['card_border_enabled'];
    $out['card_border_color'] = isset($input['card_border_color']) ? sanitize_hex_color($input['card_border_color']) : $d['card_border_color'];
    $cbw = isset($input['card_border_weight']) ? intval($input['card_border_weight']) : intval($d['card_border_weight']);
    $out['card_border_weight'] = max(0, min(20, $cbw));
    for ($i=1; $i<=3; $i++) {
      $out['custom_button_'.$i.'_enabled'] = isset($input['custom_button_'.$i.'_enabled']) ? (int)!!$input['custom_button_'.$i.'_enabled'] : (int)$d['custom_button_'.$i.'_enabled'];
      $out['custom_button_'.$i.'_text'] = isset($input['custom_button_'.$i.'_text']) ? sanitize_text_field($input['custom_button_'.$i.'_text']) : $d['custom_button_'.$i.'_text'];
      $out['custom_button_'.$i.'_url'] = isset($input['custom_button_'.$i.'_url']) ? esc_url_raw($input['custom_button_'.$i.'_url']) : $d['custom_button_'.$i.'_url'];
      $out['custom_button_'.$i.'_new_tab'] = isset($input['custom_button_'.$i.'_new_tab']) ? (int)!!$input['custom_button_'.$i.'_new_tab'] : (int)$d['custom_button_'.$i.'_new_tab'];
      $style = isset($input['custom_button_'.$i.'_style']) ? sanitize_text_field($input['custom_button_'.$i.'_style']) : $d['custom_button_'.$i.'_style'];
      $out['custom_button_'.$i.'_style'] = in_array($style, ['primary','secondary','outline'], true) ? $style : $d['custom_button_'.$i.'_style'];
    }

    return $out;
  }

  public static function handle_reset() {
    if (!current_user_can('manage_options')) wp_die('Not allowed.');
    check_admin_referer('plugin_adoptables_ui_reset_field');

    $field = isset($_GET['field']) ? sanitize_text_field($_GET['field']) : '';
    $defaults = self::default_options();
    if (!array_key_exists($field, $defaults)) {
      wp_safe_redirect(admin_url('options-general.php?page=plugin-adoptables-ui'));
      exit;
    }

    $opts = self::get_saved_options();
    if (!is_array($opts)) $opts = [];
    $opts[$field] = $defaults[$field];
    update_option(self::OPT_KEY, $opts);

    wp_safe_redirect(admin_url('options-general.php?page=plugin-adoptables-ui'));
    exit;
  }

  private static function reset_button($field_key, $label = 'Reset') {
    $url = add_query_arg(
      [
        'action'   => self::RESET_ACTION,
        'field'    => $field_key,
        '_wpnonce' => wp_create_nonce('plugin_adoptables_ui_reset_field'),
      ],
      admin_url('admin-post.php')
    );
    printf(
      ' <a href="%s" class="button button-secondary" style="vertical-align:middle;">%s</a>',
      esc_url($url),
      esc_html($label)
    );
  }

  public static function admin_menu() {
    add_options_page(
      'Rescue Plugin Suite Adoptables',
      'Rescue Plugin Suite Adoptables',
      'manage_options',
      'plugin-adoptables-ui',
      [__CLASS__, 'render_settings_page']
    );
  }

  public static function admin_init() {
    register_setting('plugin_adoptables_ui_group', self::OPT_KEY, [
      'sanitize_callback' => [__CLASS__, 'sanitize_options'],
      'default' => self::default_options(),
    ]);

    add_settings_section('plugin_aui_design', 'Design', '__return_false', 'plugin-adoptables-ui');
    add_settings_section('plugin_aui_text', 'Text', '__return_false', 'plugin-adoptables-ui');
    add_settings_section('plugin_aui_responsive', 'Responsive (Device-specific)', '__return_false', 'plugin-adoptables-ui');
    add_settings_section('plugin_aui_typography', 'Typography (Device-specific)', '__return_false', 'plugin-adoptables-ui');
    add_settings_section('plugin_aui_modal', 'Modal', '__return_false', 'plugin-adoptables-ui');
    add_settings_section('plugin_aui_reservation', 'Reservation labels', '__return_false', 'plugin-adoptables-ui');
    add_settings_section('plugin_aui_data', 'Data', '__return_false', 'plugin-adoptables-ui');

    add_settings_field('brand_color', 'Brand colour', [__CLASS__, 'field_brand_color'], 'plugin-adoptables-ui', 'plugin_aui_design');
    add_settings_field('background_color', 'Background colour', [__CLASS__, 'field_background_color'], 'plugin-adoptables-ui', 'plugin_aui_design');
    add_settings_field('paw_opacity', 'Paw print opacity (0–0.25)', [__CLASS__, 'field_paw_opacity'], 'plugin-adoptables-ui', 'plugin_aui_design');
    add_settings_field('paw_count', 'Paw print count (0–80)', [__CLASS__, 'field_paw_count'], 'plugin-adoptables-ui', 'plugin_aui_design');
    add_settings_field('font_family', 'Font family', [__CLASS__, 'field_font_family'], 'plugin-adoptables-ui', 'plugin_aui_design');
    add_settings_field('card_padding', 'Card padding (px)', [__CLASS__, 'field_card_padding'], 'plugin-adoptables-ui', 'plugin_aui_design');
    add_settings_field('card_radius', 'Card corner radius (px)', [__CLASS__, 'field_card_radius'], 'plugin-adoptables-ui', 'plugin_aui_design');

    add_settings_field('title_text', 'Title text', [__CLASS__, 'field_title_text'], 'plugin-adoptables-ui', 'plugin_aui_text');
    add_settings_field('subtitle_text', 'Subtitle text', [__CLASS__, 'field_subtitle_text'], 'plugin-adoptables-ui', 'plugin_aui_text');
    add_settings_field('footer_text', 'Footer text', [__CLASS__, 'field_footer_text'], 'plugin-adoptables-ui', 'plugin_aui_text');
    add_settings_field('loading_status_text', 'Loading status text', [__CLASS__, 'field_loading_status_text'], 'plugin-adoptables-ui', 'plugin_aui_text');
    add_settings_field('loading_page_label_text', 'Loading page label text', [__CLASS__, 'field_loading_page_label_text'], 'plugin-adoptables-ui', 'plugin_aui_text');
    add_settings_field('tips_text', 'Tips text (modal)', [__CLASS__, 'field_tips_text'], 'plugin-adoptables-ui', 'plugin_aui_text');

    add_settings_field('responsive_grid', 'Columns & rows (mobile/tablet/PC)', [__CLASS__, 'field_responsive_grid'], 'plugin-adoptables-ui', 'plugin_aui_responsive');
    add_settings_field('responsive_grid_gaps', 'Column & row spacing (mobile/tablet/PC)', [__CLASS__, 'field_responsive_grid_gaps'], 'plugin-adoptables-ui', 'plugin_aui_responsive');
    add_settings_field('responsive_card_scale', 'Card size % (keeps aspect ratio)', [__CLASS__, 'field_responsive_card_scale'], 'plugin-adoptables-ui', 'plugin_aui_responsive');

    add_settings_field('responsive_typography', 'Font sizes & weights', [__CLASS__, 'field_typography'], 'plugin-adoptables-ui', 'plugin_aui_typography');

    add_settings_field('modal_max_width', 'Modal max width (px)', [__CLASS__, 'field_modal_max_width'], 'plugin-adoptables-ui', 'plugin_aui_modal');
    add_settings_field('modal_divider_color', 'Modal divider colour', [__CLASS__, 'field_modal_divider_color'], 'plugin-adoptables-ui', 'plugin_aui_modal');
    add_settings_field('modal_divider_thickness', 'Modal divider thickness (px)', [__CLASS__, 'field_modal_divider_thickness'], 'plugin-adoptables-ui', 'plugin_aui_modal');
    add_settings_field('modal_divider_radius', 'Modal divider shape radius (px)', [__CLASS__, 'field_modal_divider_radius'], 'plugin-adoptables-ui', 'plugin_aui_modal');
    add_settings_field('modal_global_text', 'Modal global text', [__CLASS__, 'field_modal_global_text'], 'plugin-adoptables-ui', 'plugin_aui_modal');

    add_settings_field('show_reservation_label', 'Show reservation label', [__CLASS__, 'field_show_reservation_label'], 'plugin-adoptables-ui', 'plugin_aui_reservation');
    add_settings_field('reservation_pending_label', 'Pending Adoption label text', [__CLASS__, 'field_reservation_pending_label'], 'plugin-adoptables-ui', 'plugin_aui_reservation');
    add_settings_field('reservation_active_label', 'Other active reservation label text', [__CLASS__, 'field_reservation_active_label'], 'plugin-adoptables-ui', 'plugin_aui_reservation');
    add_settings_field('reservation_label_halign', 'Reservation label horizontal alignment', [__CLASS__, 'field_reservation_label_halign'], 'plugin-adoptables-ui', 'plugin_aui_reservation');
    add_settings_field('reservation_label_valign', 'Reservation label vertical alignment', [__CLASS__, 'field_reservation_label_valign'], 'plugin-adoptables-ui', 'plugin_aui_reservation');

    add_settings_field('cats_only', 'Only show cats', [__CLASS__, 'field_cats_only'], 'plugin-adoptables-ui', 'plugin_aui_data');
  }

  public static function render_settings_page() { ?>
    <div class="wrap">
      <h1>Rescue Plugin Suite Adoptables</h1>
      <p><strong>Shortcode:</strong> <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code></p>
      <form method="post" action="options.php">
        <?php
          settings_fields('plugin_adoptables_ui_group');
          do_settings_sections('plugin-adoptables-ui');
          submit_button();
        ?>
      </form>
    </div>
  <?php }

  public static function field_brand_color() {
    $o = self::get_options();
    $apply_shortcode_value = self::normalise_shortcode_setting($o['apply_form_shortcode'] ?? '');
    $apply_button_enabled = !empty($o['enable_apply_button']) && $apply_shortcode_value !== '';
    $apply_shortcode_available = false;
    $apply_form_html = '';
    if ($apply_button_enabled) {
      $apply_form_html = do_shortcode($apply_shortcode_value);
      $apply_shortcode_available = trim((string)$apply_form_html) !== '' && trim((string)$apply_form_html) !== trim((string)$apply_shortcode_value);
    }
    printf('<input type="color" name="%s[brand_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['brand_color']));
    self::reset_button('brand_color');
  }

  public static function field_background_color() {
    $o = self::get_options();
    printf('<input type="color" name="%s[background_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['background_color']));
    self::reset_button('background_color');
  }

  public static function field_paw_opacity() {
    $o = self::get_options();
    printf('<input type="number" step="0.01" min="0" max="0.25" name="%s[paw_opacity]" value="%s" style="width:110px;" />', esc_attr(self::OPT_KEY), esc_attr((string)$o['paw_opacity']));
    self::reset_button('paw_opacity');
  }

  public static function field_paw_count() {
    $o = self::get_options();
    printf('<input type="number" min="0" max="80" name="%s[paw_count]" value="%d" style="width:110px;" />', esc_attr(self::OPT_KEY), intval($o['paw_count']));
    self::reset_button('paw_count');
    echo '<p class="description">Controls how many paw prints are rendered (max 80).</p>';
  }

  public static function field_font_family() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[font_family]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['font_family']));
    self::reset_button('font_family');
    echo '<p class="description">Default is Roboto. Leave blank to inherit your theme font.</p>';
  }

  public static function field_card_padding() {
    $o = self::get_options();
    printf('<input type="number" min="0" max="40" name="%s[card_padding]" value="%d" style="width:110px;" />', esc_attr(self::OPT_KEY), intval($o['card_padding']));
    self::reset_button('card_padding');
  }

  public static function field_card_radius() {
    $o = self::get_options();
    printf('<input type="number" min="0" max="40" name="%s[card_radius]" value="%d" style="width:110px;" />', esc_attr(self::OPT_KEY), intval($o['card_radius']));
    self::reset_button('card_radius');
  }

  public static function field_title_text() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[title_text]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['title_text']));
    self::reset_button('title_text');
  }

  public static function field_subtitle_text() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[subtitle_text]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['subtitle_text']));
    self::reset_button('subtitle_text');
  }

  public static function field_footer_text() {
    $o = self::get_options();
    printf('<textarea name="%s[footer_text]" rows="3" class="large-text">%s</textarea>', esc_attr(self::OPT_KEY), esc_textarea($o['footer_text']));
    self::reset_button('footer_text');
  }

  public static function field_loading_status_text() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[loading_status_text]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['loading_status_text']));
    self::reset_button('loading_status_text');
  }

  public static function field_loading_page_label_text() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[loading_page_label_text]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['loading_page_label_text']));
    self::reset_button('loading_page_label_text');
  }

  public static function field_tips_text() {
    $o = self::get_options();
    printf('<input type="text" class="large-text" name="%s[tips_text]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['tips_text']));
    self::reset_button('tips_text');
  }

  public static function field_responsive_grid() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);

    $rows = [
      ['Mobile', 'cols_mobile', 'rows_mobile'],
      ['Tablet', 'cols_tablet', 'rows_tablet'],
      ['PC',     'cols_desktop','rows_desktop'],
    ];

    echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Device</th><th>Columns</th><th>Rows</th></tr></thead><tbody>';
    foreach ($rows as [$label, $ck, $rk]) {
      echo '<tr>';
      echo '<td>' . esc_html($label) . '</td>';
      echo '<td>';
      printf('<input type="number" min="1" max="6" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($ck), intval($o[$ck]));
      self::reset_button($ck);
      echo '</td>';
      echo '<td>';
      printf('<input type="number" min="1" max="6" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($rk), intval($o[$rk]));
      self::reset_button($rk);
      echo '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  }

  public static function field_responsive_grid_gaps() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);

    $rows = [
      ['Mobile', 'gap_x_mobile', 'gap_y_mobile'],
      ['Tablet', 'gap_x_tablet', 'gap_y_tablet'],
      ['PC',     'gap_x_desktop','gap_y_desktop'],
    ];

    echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Device</th><th>Column spacing (px)</th><th>Row spacing (px)</th></tr></thead><tbody>';
    foreach ($rows as [$label, $gx, $gy]) {
      echo '<tr>';
      echo '<td>' . esc_html($label) . '</td>';
      echo '<td>';
      printf('<input type="number" min="0" max="80" name="%s[%s]" value="%d" style="width:110px;" /> ', $k, esc_attr($gx), intval($o[$gx]));
      self::reset_button($gx);
      echo '</td>';
      echo '<td>';
      printf('<input type="number" min="0" max="80" name="%s[%s]" value="%d" style="width:110px;" /> ', $k, esc_attr($gy), intval($o[$gy]));
      self::reset_button($gy);
      echo '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  }

  public static function field_responsive_card_scale() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);

    $rows = [
      ['Mobile', 'card_scale_mobile'],
      ['Tablet', 'card_scale_tablet'],
      ['PC',     'card_scale_desktop'],
    ];

    echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Device</th><th>Card size (%)</th></tr></thead><tbody>';
    foreach ($rows as [$label, $fk]) {
      echo '<tr>';
      echo '<td>' . esc_html($label) . '</td>';
      echo '<td>';
      printf('<input type="number" min="50" max="200" name="%s[%s]" value="%d" style="width:110px;" /> ', $k, esc_attr($fk), intval($o[$fk]));
      self::reset_button($fk);
      echo '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  }

  public static function field_typography() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);

    echo '<p class="description">Defaults are restored to match original V1 styling.</p>';
    echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Text</th><th>Mobile</th><th>Tablet</th><th>PC</th><th>Weight</th></tr></thead><tbody>';

    $rows = [
      ['Modal name', 'fs_modal_name_mobile','fs_modal_name_tablet','fs_modal_name_desktop','fw_modal_name'],
      ['Modal meta', 'fs_modal_meta_mobile','fs_modal_meta_tablet','fs_modal_meta_desktop','fw_modal_meta'],
      ['Modal desc', 'fs_modal_desc_mobile','fs_modal_desc_tablet','fs_modal_desc_desktop','fw_modal_desc'],
      ['Tips',       'fs_tips_mobile','fs_tips_tablet','fs_tips_desktop','fw_tips'],
    ];

    foreach ($rows as [$label,$m,$t,$d,$w]) {
      echo '<tr><td>' . esc_html($label) . '</td>';

      foreach ([$m,$t,$d] as $key) {
        echo '<td>';
        printf('<input type="number" min="10" max="80" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($key), intval($o[$key]));
        self::reset_button($key);
        echo '</td>';
      }

      echo '<td>';
      printf('<input type="number" min="100" max="900" step="100" name="%s[%s]" value="%d" style="width:110px;" /> ', $k, esc_attr($w), intval($o[$w]));
      self::reset_button($w);
      echo '</td>';

      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  public static function field_modal_max_width() {
    $o = self::get_options();
    printf('<input type="number" min="320" max="1400" name="%s[modal_max_width]" value="%d" />', esc_attr(self::OPT_KEY), intval($o['modal_max_width']));
    self::reset_button('modal_max_width');
  }

  public static function field_modal_divider_color() {
    $o = self::get_options();
    printf('<input type="color" name="%s[modal_divider_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['modal_divider_color']));
    self::reset_button('modal_divider_color');
  }

  public static function field_modal_divider_thickness() {
    $o = self::get_options();
    printf('<input type="number" min="1" max="12" name="%s[modal_divider_thickness]" value="%d" style="width:110px;" />', esc_attr(self::OPT_KEY), intval($o['modal_divider_thickness']));
    self::reset_button('modal_divider_thickness');
  }

  public static function field_modal_divider_radius() {
    $o = self::get_options();
    printf('<input type="number" min="0" max="999" name="%s[modal_divider_radius]" value="%d" style="width:110px;" />', esc_attr(self::OPT_KEY), intval($o['modal_divider_radius']));
    self::reset_button('modal_divider_radius');
    echo '<p class="description">0 = square ends, larger values = more rounded ends.</p>';
  }

  public static function field_modal_global_text() {
    $o = self::get_options();
    printf('<textarea name="%s[modal_global_text]" rows="5" class="large-text">%s</textarea>', esc_attr(self::OPT_KEY), esc_textarea($o['modal_global_text']));
    self::reset_button('modal_global_text');
    echo '<p class="description">Shows below the modal description and divider. Line breaks are preserved.</p>';
  }

  public static function field_show_reservation_label() {
    $o = self::get_options();
    printf(
      '<label><input type="checkbox" name="%s[show_reservation_label]" value="1" %s /> Show reservation label on cards and modal</label>',
      esc_attr(self::OPT_KEY),
      checked(!empty($o['show_reservation_label']), true, false)
    );
    self::reset_button('show_reservation_label');
  }

  public static function field_reservation_pending_label() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[reservation_pending_label]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['reservation_pending_label']));
    self::reset_button('reservation_pending_label');
  }

  public static function field_reservation_active_label() {
    $o = self::get_options();
    printf('<input type="text" class="regular-text" name="%s[reservation_active_label]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['reservation_active_label']));
    self::reset_button('reservation_active_label');
  }

  public static function field_reservation_label_halign() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);
    $current = (string)$o['reservation_label_halign'];

    echo '<select name="' . $k . '[reservation_label_halign]">';
    foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $value => $label) {
      printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($label));
    }
    echo '</select> ';
    self::reset_button('reservation_label_halign');
  }

  public static function field_reservation_label_valign() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);
    $current = (string)$o['reservation_label_valign'];

    echo '<select name="' . $k . '[reservation_label_valign]">';
    foreach (['top' => 'Top', 'bottom' => 'Bottom'] as $value => $label) {
      printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($label));
    }
    echo '</select> ';
    self::reset_button('reservation_label_valign');
  }

  public static function field_cats_only() {
    $o = self::get_options();
    printf('<label><input type="checkbox" name="%s[cats_only]" value="1" %s /> Only show cats</label>', esc_attr(self::OPT_KEY), checked(!empty($o['cats_only']), true, false));
    self::reset_button('cats_only');
  }

  private static function current_request_has_shortcode() {
    if (is_admin()) return false;
    global $post;
    return ($post && isset($post->post_content) && has_shortcode($post->post_content, self::SHORTCODE));
  }

  public static function conditionally_enqueue_assets() {
    if (!self::current_request_has_shortcode()) return;
    self::enqueue_assets();
  }

  private static function enqueue_assets() {
    $opts = self::get_options();

    wp_enqueue_style('rescue-plugin-suite-frontend', PLUGIN_SUITE_URL . 'assets/css/frontend.css', [], PLUGIN_SUITE_VERSION);
    wp_enqueue_script('rescue-plugin-suite-shared-modal', PLUGIN_SUITE_URL . 'assets/js/shared-modal.js', [], PLUGIN_SUITE_VERSION, true);

    if (trim((string)$opts['font_family']) === 'Roboto') {
      wp_enqueue_style(
        self::ROBOTO_HANDLE,
        'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap',
        [],
        null
      );
    }

    wp_register_script(self::TAILWIND_HANDLE . '-config', '', [], null, true);
    wp_enqueue_script(self::TAILWIND_HANDLE . '-config');
    wp_add_inline_script(
      self::TAILWIND_HANDLE . '-config',
      'window.tailwind = window.tailwind || {}; window.tailwind.config = { corePlugins: { preflight: false } };',
      'before'
    );

    wp_enqueue_script(
      self::TAILWIND_HANDLE,
      'https://cdn.tailwindcss.com',
      [],
      null,
      true
    );
  }

  public static function render_shortcode($atts = [], $content = null) {
    self::enqueue_assets();

    $o = self::get_options();
    $apply_shortcode_value = self::normalise_shortcode_setting($o['apply_form_shortcode'] ?? '');
    $apply_button_enabled = !empty($o['enable_apply_button']) && $apply_shortcode_value !== '';

    // Resolve the configured Suite form without rendering a second copy. ASM's
    // online form script uses the fixed global ID "asm3-onlineform", so two
    // instances on one page conflict. The frontend reuses an existing form or
    // creates the single instance only when Apply is clicked.
    $apply_shortcode_tag = trim($apply_shortcode_value, "[] \t\n\r\0\x0B");
    if (strpos($apply_shortcode_tag, ' ') !== false) {
      $apply_shortcode_tag = strtok($apply_shortcode_tag, ' ');
    }
    $apply_form_id = '';
    $apply_form_account = 'plugin';
    if (class_exists('Plugin_UI_Suite_Plugin')) {
      $suite_forms = Plugin_UI_Suite_Plugin::get_forms();
      if (isset($suite_forms[$apply_shortcode_tag])) {
        $apply_form_id = preg_replace('/[^0-9]/', '', (string)$suite_forms[$apply_shortcode_tag]);
      }
      $suite_settings = Plugin_UI_Suite_Plugin::get_settings();
      if (!empty($suite_settings['forms']['account'])) {
        $apply_form_account = sanitize_text_field($suite_settings['forms']['account']);
      }
    }
    $apply_form_script_url = ($apply_form_id !== '')
      ? 'https://service.sheltermanager.com/asmservice?account=' . rawurlencode($apply_form_account) . '&method=online_form_js&formid=' . rawurlencode($apply_form_id)
      : '';
    $modal_contact_url = isset($o['modal_contact_url']) ? esc_url((string)$o['modal_contact_url']) : '';

    $ff = trim((string)$o['font_family']);
    $font_css = ($ff === '') ? "font-family: inherit;" : ("font-family: " . esc_html($ff) . ", sans-serif;");

    $scale_m = max(0.5, min(2.0, ((int)$o['card_scale_mobile']) / 100));
    $scale_t = max(0.5, min(2.0, ((int)$o['card_scale_tablet']) / 100));
    $scale_d = max(0.5, min(2.0, ((int)$o['card_scale_desktop']) / 100));

    $vars = [
      "--asm-brand: " . esc_attr($o['brand_color']),
      "--asm-bg: " . esc_attr($o['background_color']),
      "--asm-paw-opacity: " . esc_attr((string)$o['paw_opacity']),
      "--asm-modal-maxw: " . (int)$o['modal_max_width'] . "px",

      "--asm-cols-m: " . (int)$o['cols_mobile'],
      "--asm-cols-t: " . (int)$o['cols_tablet'],
      "--asm-cols-d: " . (int)$o['cols_desktop'],

      "--asm-gapx-m: " . (int)$o['gap_x_mobile'] . "px",
      "--asm-gapy-m: " . (int)$o['gap_y_mobile'] . "px",
      "--asm-gapx-t: " . (int)$o['gap_x_tablet'] . "px",
      "--asm-gapy-t: " . (int)$o['gap_y_tablet'] . "px",
      "--asm-gapx-d: " . (int)$o['gap_x_desktop'] . "px",
      "--asm-gapy-d: " . (int)$o['gap_y_desktop'] . "px",

      "--asm-card-pad: " . (int)$o['card_padding'] . "px",
      "--asm-card-radius: " . (int)$o['card_radius'] . "px",
      "--asm-card-border-width: " . (!empty($o['card_border_enabled']) ? (int)$o['card_border_weight'] : 0) . "px",
      "--asm-card-border-color: " . esc_attr(!empty($o['card_border_enabled']) ? ($o['card_border_color'] ?: $o['brand_color']) : 'transparent'),

      "--asm-scale-m: " . $scale_m,
      "--asm-scale-t: " . $scale_t,
      "--asm-scale-d: " . $scale_d,

      "--asm-fs-mn-m: " . (int)$o['fs_modal_name_mobile'] . "px",
      "--asm-fs-mn-t: " . (int)$o['fs_modal_name_tablet'] . "px",
      "--asm-fs-mn-d: " . (int)$o['fs_modal_name_desktop'] . "px",
      "--asm-fw-mn: " . (int)$o['fw_modal_name'],

      "--asm-fs-mm-m: " . (int)$o['fs_modal_meta_mobile'] . "px",
      "--asm-fs-mm-t: " . (int)$o['fs_modal_meta_tablet'] . "px",
      "--asm-fs-mm-d: " . (int)$o['fs_modal_meta_desktop'] . "px",
      "--asm-fw-mm: " . (int)$o['fw_modal_meta'],

      "--asm-fs-md-m: " . (int)$o['fs_modal_desc_mobile'] . "px",
      "--asm-fs-md-t: " . (int)$o['fs_modal_desc_tablet'] . "px",
      "--asm-fs-md-d: " . (int)$o['fs_modal_desc_desktop'] . "px",
      "--asm-fw-md: " . (int)$o['fw_modal_desc'],

      "--asm-fs-tip-m: " . (int)$o['fs_tips_mobile'] . "px",
      "--asm-fs-tip-t: " . (int)$o['fs_tips_tablet'] . "px",
      "--asm-fs-tip-d: " . (int)$o['fs_tips_desktop'] . "px",
      "--asm-fw-tip: " . (int)$o['fw_tips'],

      "--asm-divider-color: " . esc_attr($o['modal_divider_color']),
      "--asm-divider-thickness: " . (int)$o['modal_divider_thickness'] . "px",
      "--asm-divider-radius: " . (int)$o['modal_divider_radius'] . "px",
    ];
    $vars_css = implode('; ', $vars) . ';';

    $paw_count = max(0, min(80, (int)$o['paw_count']));

    ob_start();
    ?>
<style>
  #asm-adoptables-widget,
  #asm-adoptables-widget * { <?php echo $font_css; ?> }

  #asm-adoptables-widget { opacity:0; visibility:hidden; }
  #asm-adoptables-widget.asm-ready { opacity:1; visibility:visible; transition: opacity .18s ease-out; }

  /* Critical pre-Tailwind hiding. These overlays must never render before the CDN utilities load. */
  #asm-modal.hidden,
  #asm-filters-wrap.hidden,
  #asm-favourites-modal.hidden,
  #asm-compare-modal.hidden {
    display:none !important;
    visibility:hidden !important;
    opacity:0 !important;
    pointer-events:none !important;
  }

  #asm-modal:not(.asm-modal-ready) {
    display:none !important;
    visibility:hidden !important;
    opacity:0 !important;
    pointer-events:none !important;
  }

  /* Prevent utility-sized icons briefly inheriting the browser's default SVG dimensions. */
  #asm-adoptables-widget svg,
  #asm-modal svg,
  #asm-filters-wrap svg,
  #asm-favourites-modal svg,
  #asm-compare-modal svg {
    max-width:100%;
    max-height:100%;
  }
  .asm-scroll-arrow { width:1rem !important; height:1rem !important; }

  @keyframes float { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  @keyframes pawPrint {
    0%   { opacity: 0; transform: scale(0.5) rotate(-15deg); }
    50%  { opacity: var(--asm-paw-opacity, 0.08); transform: scale(1) rotate(0deg); }
    100% { opacity: 0; transform: scale(1.2) rotate(15deg); }
  }

  .asm-icon-float { animation: float 3s ease-in-out infinite; }
  .asm-paw-bg { position:absolute; opacity:0; animation:pawPrint 4s ease-in-out infinite; pointer-events:none; }

  @keyframes asmArrowBounce { 0%,100%{transform:translateY(0);} 50%{transform:translateY(4px);} }
  .asm-scroll-arrow { animation: asmArrowBounce 1.2s ease-in-out infinite; }

  @media (hover: none) and (pointer: coarse) {
    .asm-ad-card:hover { transform: none !important; box-shadow: none !important; }
    #asm-adoptables-widget.asm-ready { transition:none; }
  }

  #asm-modal { pointer-events: auto; isolation:isolate; }
  #asm-modal.hidden { pointer-events: none; }
  #asm-modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.5); }
  #asm-modal-viewport{
    position:absolute;
    inset:0;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    min-height:100dvh;
    padding:1rem;
    box-sizing:border-box;
    overscroll-behavior:contain;
  }

  #asm-modal-panel { background:#fff !important; }
  #asm-modal-panel .asm-modal-col { background:#fff !important; }

  #asm-modal-panel{
    display:flex;
    flex-direction:column;
    overflow:hidden;
    max-height:78dvh;
    position:relative;
    z-index:2;
    border-style:solid;
    border-width:2px;
    border-radius:1rem;
    box-sizing:border-box;
    -webkit-transform: translateZ(0);
    transform: translateZ(0);
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
  }

  #asm-modal-panel > .sticky{
    position:sticky;
    top:0;
    z-index:20;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.75rem;
    padding:1rem;
    border-bottom:1px solid #e5e7eb;
    background:#fff;
  }

  #asm-modal-header-actions{ display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
  #asm-modal-header-actions button,
  .asm-modal-nav,
  #asm-modal-photo-prev,
  #asm-modal-photo-next{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
  }
  #asm-modal-header-actions button{
    min-height:2.5rem;
    border-style:solid;
    border-width:2px;
    border-radius:.75rem;
    padding:0 .75rem;
    font-size:.875rem;
    font-weight:700;
    line-height:1;
  }
  #asm-modal-close,
  #asm-modal-favourite,
  #asm-modal-share{
    width:2.5rem;
    padding-left:0;
    padding-right:0;
  }
  #asm-modal-header-actions svg{ width:1.25rem; height:1.25rem; flex:0 0 auto; }
  #asm-modal-favourite-text,
  #asm-modal-share-text{ display:none; }
  #asm-modal-photo-prev.hidden,
  #asm-modal-photo-next.hidden,
  #asm-apply-toggle-top.hidden,
  #asm-apply-back-top.hidden,
  #asm-modal-favourite.hidden,
  #asm-modal-share.hidden,
  #asm-badge-reserved.hidden,
  #asm-badge-bonded.hidden,
  #asm-badge-indoor.hidden,
  #asm-modal-global-text-wrap.hidden,
  #asm-modal-form-col.hidden,
  #asm-scroll-hint.hidden,
  #asm-scroll-hint-mobile.hidden{ display:none !important; }

  #asm-modal{
    position: fixed !important;
    inset: 0 !important;
    z-index: 2147483647 !important;
  }

  #asm-field-desc,
  #asm-modal-global-text{
    max-height: none !important;
    overflow: visible !important;
    white-space: pre-wrap !important;
    word-break: break-word;
    font-size: inherit !important;
    font-weight: inherit !important;
    line-height: inherit !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  #asm-modal-global-text-wrap{
    margin-left: 0 !important;
    padding-left: 0 !important;
  }

  #asm-modal-scroll {
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto !important;
    background:#fff;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
  }
  #asm-modal-scroll > .grid{
    display:grid;
    grid-template-columns:minmax(0, 1fr);
    background:#fff;
  }
  #asm-modal-desc-col,
  #asm-modal-form-col{ grid-column:1 / -1; }
  #asm-modal-media-col,
  #asm-modal-info-col,
  #asm-modal-desc-col{ padding:1rem; box-sizing:border-box; }
  #asm-filters-scroll { -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
  #asm-filters-panel{ width: 100% !important; max-width: var(--asm-modal-maxw, 896px) !important; }
  #asm-grid{ align-content: start; }

  #asm-modal-panel{
    width: 100% !important;
    max-width: var(--asm-modal-maxw, 896px) !important;
  }

  #asm-modal-media-col .relative,
  #asm-adoptables-widget .asm-ad-imgwrap{
    overflow:hidden !important;
    background:#f8fafc;
    -webkit-transform:translateZ(0);
    transform:translateZ(0);
    -webkit-backface-visibility:hidden;
    backface-visibility:hidden;
    contain:paint;
  }

  #asm-modal-media-col .relative{
    position:relative;
    width:100%;
    aspect-ratio:1 / 1;
    max-width:420px;
    margin-left:auto;
    margin-right:auto;
    border-radius:1rem;
  }

  #asm-modal-mainimg,
  #asm-modal-thumbs img,
  #asm-adoptables-widget .asm-ad-imgwrap img{
    display:block;
    width:100%;
    height:100%;
    object-fit:cover;
    opacity:1 !important;
    visibility:visible !important;
    -webkit-transform:translateZ(0);
    transform:translateZ(0);
    -webkit-backface-visibility:hidden;
    backface-visibility:hidden;
  }

  #asm-modal-thumbs{ display:flex; gap:.5rem; overflow-x:auto; -webkit-overflow-scrolling:touch; }
  #asm-modal-thumbs button{ flex:0 0 auto; }

  #asm-modal-name{ font-size: var(--asm-fs-mn-m) !important; font-weight: var(--asm-fw-mn) !important; }
  #asm-modal-meta{ font-size: var(--asm-fs-mm-m) !important; font-weight: var(--asm-fw-mm) !important; }
  #asm-field-desc,
  #asm-modal-global-text{
    font-size: var(--asm-fs-md-m) !important;
    font-weight: var(--asm-fw-md) !important;
  }
  #asm-tips{ font-size: var(--asm-fs-tip-m) !important; font-weight: var(--asm-fw-tip) !important; }

  @media (min-width: 768px){
    #asm-modal-scroll > .grid{ grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); }
    #asm-modal-media-col .relative{ max-width:none; }
    #asm-modal-name{ font-size: var(--asm-fs-mn-t) !important; }
    #asm-modal-meta{ font-size: var(--asm-fs-mm-t) !important; }
    #asm-field-desc,
    #asm-modal-global-text{ font-size: var(--asm-fs-md-t) !important; }
    #asm-tips{ font-size: var(--asm-fs-tip-t) !important; }
  }

  @media (min-width: 640px){
    #asm-modal-favourite,
    #asm-modal-share{
      width:auto;
      padding-left:.75rem;
      padding-right:.75rem;
    }
    #asm-modal-favourite-text,
    #asm-modal-share-text{ display:inline; }
  }

  @media (min-width: 1024px){
    #asm-modal-name{ font-size: var(--asm-fs-mn-d) !important; }
    #asm-modal-meta{ font-size: var(--asm-fs-mm-d) !important; }
    #asm-field-desc,
    #asm-modal-global-text{ font-size: var(--asm-fs-md-d) !important; }
    #asm-tips{ font-size: var(--asm-fs-tip-d) !important; }
  }

  #asm-adoptables-widget .asm-ad-card{
    width:100%;
    margin-left:auto;
    margin-right:auto;
    border-radius: var(--asm-card-radius, 16px) !important;
    overflow:hidden !important;
    -webkit-mask-image:-webkit-radial-gradient(white, black);
  }

  #asm-adoptables-widget .asm-ad-card > .relative{
    border-top-left-radius: inherit;
    border-top-right-radius: inherit;
    overflow:hidden !important;
  }

  #asm-adoptables-widget .asm-ad-card .asm-ad-imgwrap{
    overflow:hidden !important;
    border-top-left-radius: inherit !important;
    border-top-right-radius: inherit !important;
  }

  #asm-adoptables-widget .asm-ad-card .asm-ad-imgwrap img{
    display:block;
    border-radius: 0 !important;
  }

  #asm-adoptables-widget .asm-ad-card-pad{ padding: var(--asm-card-pad, 12px) !important; }

  @media (max-width: 767px){ #asm-adoptables-widget .asm-ad-card{ max-width: calc(100% * var(--asm-scale-m, 1)); } }
  @media (min-width: 768px) and (max-width: 1023px){ #asm-adoptables-widget .asm-ad-card{ max-width: calc(100% * var(--asm-scale-t, 1)); } }
  @media (min-width: 1024px){ #asm-adoptables-widget .asm-ad-card{ max-width: calc(100% * var(--asm-scale-d, 1)); } }


  .asm-modal-animal-nav{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:14px;pointer-events:auto;width:100%;}
  .asm-modal-nav{position:static;transform:none;z-index:2147483648;pointer-events:auto;min-width:160px;}

  .asm-modal-divider{
    height: var(--asm-divider-thickness, 2px);
    background: var(--asm-divider-color, var(--asm-brand));
    border-radius: var(--asm-divider-radius, 999px);
    width: 100%;
  }

  #asm-modal-photo-prev,#asm-modal-photo-next{z-index:20;}
  #asm-modal-prev-animal,#asm-modal-next-animal{z-index:30;}
  #asm-modal-photo-prev{left:12px;top:50%;transform:translateY(-50%);}
  #asm-modal-photo-next{right:12px;top:50%;transform:translateY(-50%);}
  .asm-fav-top-left{left:8px;top:8px;right:auto;bottom:auto;}
  .asm-fav-top-right{right:8px;top:8px;left:auto;bottom:auto;}
  .asm-fav-bottom-left{left:8px;bottom:8px;right:auto;top:auto;}
  .asm-fav-bottom-right{right:8px;bottom:8px;left:auto;top:auto;}
  .asm-modal-animal-nav .asm-modal-nav[disabled]{opacity:.45;cursor:not-allowed;}
</style>

<div id="asm-adoptables-widget"
     class="min-h-full w-full overflow-x-hidden overflow-y-auto relative rounded-xl"
     style="background:var(--asm-bg); <?php echo esc_attr($vars_css); ?>">

  <?php
    $paw_svg = function($size){
      $w = (int)$size;
      $h = (int)$size;
      return '<svg width="'.$w.'" height="'.$h.'" viewBox="0 0 100 100" fill="var(--asm-brand)">
        <ellipse cx="50" cy="70" rx="18" ry="20" />
        <ellipse cx="59" cy="38" rx="6" ry="8" />
        <ellipse cx="44" cy="41" rx="6" ry="8" />
        <ellipse cx="73" cy="51" rx="6" ry="8" />
        <ellipse cx="32" cy="48" rx="6" ry="8" />
      </svg>';
    };

    for ($i=0; $i < $paw_count; $i++) {
      $top  = mt_rand(6, 90);
      $left = mt_rand(4, 92);
      $delay = (mt_rand(0, 360) / 100) . 's';
      $size = mt_rand(30, 44);
      $dur  = (mt_rand(360, 520) / 100) . 's';
      $rot  = mt_rand(-25, 25);

      echo '<div class="asm-paw-bg" style="top:'.$top.'%; left:'.$left.'%; animation-delay:'.$delay.'; animation-duration:'.$dur.'; transform: rotate('.$rot.'deg);">'
          . $paw_svg($size)
          . '</div>';
    }
  ?>

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10">
    <div class="text-center mb-6">
      <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-2">
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold" style="color:var(--asm-brand);">
          <?php echo esc_html($o['title_text']); ?>
        </h2>
      </div>
      <p class="text-base sm:text-lg font-semibold" style="color:#64748b;">
        <?php echo esc_html($o['subtitle_text']); ?>
      </p>
      <p id="asm-adoptables-status" class="mt-3 text-sm font-medium" role="status" aria-live="polite" style="color:#64748b; display:none;"></p>
    </div>

    <?php if (!empty($o['show_top_navigation']) || !empty($o['enable_filters'])) : ?>
    <div class="flex items-center justify-between gap-3 mb-5">
      <?php if (!empty($o['show_top_navigation'])) : ?>
        <button id="asm-prev"
                class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
                style="border-color:var(--asm-brand);"
                aria-label="Previous">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2">
            <path d="M15 18l-6-6 6-6"/>
          </svg>
        </button>

        <div class="flex-1 text-center">
          <span id="asm-page-label" class="text-sm font-semibold" aria-live="polite" style="color:#334155;">
            <?php echo esc_html($o['loading_page_label_text']); ?>
          </span>
        </div>
      <?php else : ?>
        <div class="flex-1" aria-hidden="true"></div>
      <?php endif; ?>

      <button id="asm-open-filters" type="button" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl border-2 bg-white hover:shadow-md text-sm font-semibold<?php echo empty($o['enable_filters']) ? ' hidden' : ''; ?>" style="border-color:var(--asm-brand); color:var(--asm-brand);">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5h18"/><path d="M6 12h12"/><path d="M10 19h4"/></svg>
        <span>Filters</span>
      </button>

      <?php if (!empty($o['show_top_navigation'])) : ?>
        <button id="asm-next"
                class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
                style="border-color:var(--asm-brand);"
                aria-label="Next">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2">
            <path d="M9 6l6 6-6 6"/>
          </svg>
        </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div id="asm-filters-wrap" class="fixed inset-0 z-[2147483646] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="asm-filters-title" tabindex="-1">
      <div id="asm-filters-backdrop" class="absolute inset-0 bg-black/50"></div>
      <div class="absolute inset-0 flex flex-col items-center justify-center p-4 sm:p-6"
           style="min-height:100dvh; padding-top: max(env(safe-area-inset-top), 16px); padding-bottom: max(env(safe-area-inset-bottom), 16px);">
        <div id="asm-filters-panel" class="bg-white rounded-2xl shadow-2xl border-2 overflow-hidden flex flex-col max-h-[78vh] sm:max-h-[72vh] lg:max-h-[68vh]"
             style="border-color:var(--asm-brand);">
          <div class="sticky top-0 z-20 bg-white flex items-center justify-between px-4 sm:px-6 py-4 border-b">
            <h3 id="asm-filters-title" class="text-lg sm:text-xl font-extrabold" style="color:#334155;">Filter animals</h3>
            <button id="asm-close-filters" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border-2 bg-white hover:shadow-md text-2xl leading-none font-semibold" style="border-color:var(--asm-brand); color:var(--asm-brand);" aria-label="Close filters">
              <span aria-hidden="true">×</span>
            </button>
          </div>
          <div id="asm-filters-scroll" class="flex-1 overflow-y-auto bg-white">
            <div class="p-4 sm:p-6 bg-white">
              <div class="bg-white/90 backdrop-blur-sm rounded-2xl border shadow-sm p-3 sm:p-4" style="border-color:var(--asm-brand);">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                  <div id="asm-filter-age-wrap" class="hidden"><label for="asm-filter-age" class="block text-xs font-bold mb-1" style="color:#64748b;"><?php echo esc_html($o['filter_age_label'] ?? 'Age'); ?></label><select id="asm-filter-age" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">All</option></select></div>
                  <div id="asm-filter-sex-wrap" class="hidden"><label for="asm-filter-sex" class="block text-xs font-bold mb-1" style="color:#64748b;"><?php echo esc_html($o['filter_sex_label'] ?? 'Sex'); ?></label><select id="asm-filter-sex" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">All</option></select></div>
                  <div id="asm-filter-breed-wrap" class="hidden sm:col-span-2"><label for="asm-filter-breed" class="block text-xs font-bold mb-1" style="color:#64748b;"><?php echo esc_html($o['filter_breed_label'] ?? 'Breed'); ?></label><select id="asm-filter-breed" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">All</option></select></div>
                  <div><label for="asm-filter-indoor" class="block text-xs font-bold mb-1" style="color:#64748b;">Indoor only</label><select id="asm-filter-indoor" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">No preference</option><option value="yes">Yes</option><option value="no">No</option></select></div>
                  <div><label for="asm-filter-bonded" class="block text-xs font-bold mb-1" style="color:#64748b;">Bonded pair</label><select id="asm-filter-bonded" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">No preference</option><option value="yes">Yes</option><option value="no">No</option></select></div>
                  <div><label for="asm-filter-good-cats" class="block text-xs font-bold mb-1" style="color:#64748b;">Good with cats</label><select id="asm-filter-good-cats" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">No preference</option><option value="Yes">Yes</option><option value="No">No</option><option value="Unknown">Unknown</option><option value="Selective">Selective</option></select></div>
                  <div><label for="asm-filter-good-dogs" class="block text-xs font-bold mb-1" style="color:#64748b;">Good with dogs</label><select id="asm-filter-good-dogs" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">No preference</option><option value="Yes">Yes</option><option value="No">No</option><option value="Unknown">Unknown</option><option value="Selective">Selective</option></select></div>
                  <div><label for="asm-filter-good-children" class="block text-xs font-bold mb-1" style="color:#64748b;">Good with children</label><select id="asm-filter-good-children" class="w-full rounded-xl border px-3 py-2 bg-white" style="border-color:var(--asm-brand);"><option value="">No preference</option><option value="Yes">Yes</option><option value="No">No</option><option value="Unknown">Unknown</option><option value="Over 5">Over 5</option><option value="Over 12">Over 12</option></select></div>
                  <div class="sm:col-span-2 flex flex-col gap-3 pt-1">
                    <label id="asm-filter-pending-wrap" class="hidden text-sm font-semibold" style="color:#334155;"><input type="checkbox" id="asm-filter-hide-pending" value="1" /> <?php echo esc_html($o['filter_exclude_pending_label'] ?? 'Hide animals pending adoption'); ?></label>
                    <label id="asm-favourites-only-wrap" class="hidden text-sm font-semibold" style="color:#334155;"><input type="checkbox" id="asm-show-favourites-only" value="1" /> <?php echo esc_html($o['show_only_favourites_label'] ?? 'Show favourites only'); ?></label>
                  </div>
                </div>
              </div>
              <div id="asm-favourites-summary" class="mt-3 hidden"></div>
              <div class="mt-4 flex items-center justify-end gap-2">
                <button id="asm-reset-filters" type="button" class="inline-flex items-center justify-center px-4 h-10 rounded-xl border-2 bg-white text-sm font-semibold hover:shadow-md" style="border-color:var(--asm-brand); color:var(--asm-brand);">Reset</button>
                <button id="asm-apply-filters" type="button" class="inline-flex items-center justify-center px-4 h-10 rounded-xl border-2 text-sm font-semibold hover:shadow-md" style="background:var(--asm-brand); color:#fff; border-color:var(--asm-brand);">Apply filters</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="asm-grid-wrap">
      <div id="asm-grid" class="grid" aria-live="polite" aria-busy="true"></div>
    </div>

    <div class="flex items-center justify-between gap-3 mt-8">
      <button id="asm-prev-bottom"
              class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
              style="border-color:var(--asm-brand);"
              aria-label="Previous">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>

      <div class="flex-1 text-center">
        <span id="asm-page-label-bottom" class="text-sm font-semibold" style="color:#334155;"></span>
      </div>

      <button id="asm-next-bottom"
              class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
              style="border-color:var(--asm-brand);"
              aria-label="Next">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2">
          <path d="M9 6l6 6-6 6"/>
        </svg>
      </button>
    </div>

    <div class="text-center mt-8">
      <p class="text-sm sm:text-base font-medium px-2" style="color:#64748b;">
        <?php echo esc_html($o['footer_text']); ?>
      </p>
    </div>
  </div>

  <div id="asm-modal" class="fixed inset-0 z-[2147483647] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="asm-modal-name" aria-describedby="asm-field-desc" tabindex="-1" style="<?php echo esc_attr($vars_css); ?>">
    <div id="asm-modal-backdrop" class="absolute inset-0 bg-black/50"></div>

    <div id="asm-modal-viewport" class="absolute inset-0 flex flex-col items-center justify-center p-4 sm:p-6"
         style="min-height:100dvh; padding-top: max(env(safe-area-inset-top), 16px); padding-bottom: max(env(safe-area-inset-bottom), 16px);">

      <div id="asm-modal-panel"
           class="bg-white rounded-2xl shadow-2xl border-2 overflow-hidden flex flex-col max-h-[78vh] sm:max-h-[72vh] lg:max-h-[68vh]"
           style="border-color:var(--asm-brand);">

        <div class="sticky top-0 z-20 bg-white flex items-center justify-between px-4 sm:px-6 py-4 border-b">
          <div class="flex items-center gap-3 min-w-0">
            <div class="asm-icon-float w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                 style="background:var(--asm-brand);">
              <span class="text-white text-xl">🐱</span>
            </div>
            <div class="min-w-0">
              <div id="asm-modal-name" class="truncate" style="color:#334155;">Cat</div>
              <div id="asm-modal-meta" class="truncate" style="color:#64748b;">—</div>
            </div>
          </div>

          <div id="asm-modal-header-actions" class="flex items-center gap-2 shrink-0">
            <button id="asm-apply-toggle-top" data-action-role="apply"
                    class="inline-flex items-center gap-2 px-3 h-10 rounded-xl border-2 hover:shadow-md text-sm font-semibold<?php echo !$apply_button_enabled ? ' hidden' : ''; ?>"
                    style="background:<?php echo esc_attr($o['apply_button_bg_color'] ?? '#401268'); ?>;color:<?php echo esc_attr($o['apply_button_text_color'] ?? '#ffffff'); ?>;border-color:<?php echo esc_attr($o['apply_button_border_color'] ?? '#401268'); ?>;border-radius:<?php echo (int)($o['apply_button_radius'] ?? 16); ?>px;"
                    type="button"><?php echo esc_html($o['apply_button_text'] ?? 'Apply'); ?></button>
            <button id="asm-apply-back-top"
                    class="inline-flex items-center gap-2 px-3 h-10 rounded-xl border-2 hover:shadow-md text-sm font-semibold hidden"
                    style="background:#ffffff;color:var(--asm-brand);border-color:var(--asm-brand);"
                    type="button">Back</button>
            <button id="asm-modal-favourite" data-action-role="favourite"
                    class="inline-flex items-center justify-center gap-2 w-9 sm:w-auto px-0 sm:px-3 h-9 sm:h-10 rounded-xl border-2 bg-white hover:shadow-md text-sm font-semibold<?php echo empty($o['enable_favourites']) ? ' hidden' : ''; ?>"
                    style="border-color:var(--asm-brand); color:var(--asm-brand);"
                    type="button">
              <span id="asm-modal-favourite-icon" aria-hidden="true"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.1 20.3l-.1.1-.1-.1C7.1 16 4 13.2 4 9.8 4 7.5 5.8 6 8 6c1.3 0 2.5.6 3.3 1.6C12.2 6.6 13.4 6 14.7 6 16.9 6 18.7 7.5 18.7 9.8c0 3.4-3.1 6.2-6.6 10.5z"/></svg></span>
              <span id="asm-modal-favourite-text" class="hidden sm:inline">Add to favourites</span>
            </button>
            <button id="asm-modal-share" data-action-role="share"
                    class="inline-flex items-center justify-center gap-2 w-9 sm:w-auto px-0 sm:px-3 h-9 sm:h-10 rounded-xl border-2 bg-white hover:shadow-md text-sm font-semibold<?php echo empty($o['enable_deep_links']) ? ' hidden' : ''; ?>"
                    style="border-color:var(--asm-brand); color:var(--asm-brand);"
                    type="button">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/>
                <path d="M16 6l-4-4-4 4"/>
                <path d="M12 2v13"/>
              </svg>
              <span id="asm-modal-share-text" class="hidden sm:inline"><?php echo esc_html($o['share_button_text'] ?? 'Share'); ?></span>
            </button>
            <button id="asm-modal-close" data-action-role="close"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-xl border-2 bg-white hover:shadow-md shrink-0"
                    style="border-color:var(--asm-brand);"
                    aria-label="Close">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <div id="asm-modal-scroll" class="flex-1 overflow-y-auto bg-white">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-0 bg-white">

            <div id="asm-modal-media-col" class="asm-modal-col p-4 sm:p-6 bg-white">
              <div class="relative rounded-2xl overflow-hidden border bg-gray-50 w-full aspect-square max-w-[420px] mx-auto md:max-w-none">
                <button id="asm-modal-photo-prev" type="button" class="hidden absolute inline-flex items-center justify-center w-9 h-9 rounded-full border-2 bg-white/95 shadow-md" style="border-color:var(--asm-brand);" aria-label="Previous photo"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button><img id="asm-modal-mainimg" src="" alt="" class="w-full h-full object-cover" loading="eager" decoding="sync" fetchpriority="high" /><button id="asm-modal-photo-next" type="button" class="hidden absolute inline-flex items-center justify-center w-9 h-9 rounded-full border-2 bg-white/95 shadow-md" style="border-color:var(--asm-brand);" aria-label="Next photo"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg></button>
              </div>
              <div id="asm-modal-thumbs" class="mt-3 flex gap-2 overflow-x-auto pb-1" data-layout-role="gallery"></div>
              <div class="flex md:hidden items-center justify-center mt-4 select-none" id="asm-scroll-hint-mobile" style="color:#64748b;">
                <div class="flex items-center gap-2 text-sm font-semibold">
                  <span>Scroll to find out more</span>
                  <svg class="w-4 h-4 asm-scroll-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14" />
                    <path d="M19 12l-7 7-7-7" />
                  </svg>
                </div>
              </div>

            </div>

            <div id="asm-modal-info-col" class="asm-modal-col p-4 sm:p-6 md:border-l bg-white">
              
                <div class="flex flex-wrap gap-2 mb-4" data-layout-role="badges">
                  <span id="asm-badge-reserved"
                        class="hidden px-3 py-1 rounded-full font-extrabold text-white text-sm lg:text-base"
                        style="background:var(--asm-brand);">Reserved</span>

                  <span id="asm-badge-time"
                        class="px-3 py-1 rounded-full font-bold bg-gray-100 text-sm lg:text-base"
                        style="color:#334155;">—</span>
                  <span id="asm-badge-bonded" class="hidden px-3 py-1 rounded-full font-extrabold text-sm lg:text-base" style="background:<?php echo esc_attr($o['bonded_label_bg_color'] ?? '#ec4899'); ?>;color:<?php echo esc_attr($o['bonded_label_text_color'] ?? '#ffffff'); ?>;"><?php echo esc_html($o['bonded_label_text'] ?? 'Bonded Pair'); ?></span>
                  <span id="asm-badge-indoor" class="hidden px-3 py-1 rounded-full font-extrabold text-sm lg:text-base" style="background:<?php echo esc_attr($o['indoor_only_label_bg_color'] ?? '#0f766e'); ?>;color:<?php echo esc_attr($o['indoor_only_label_text_color'] ?? '#ffffff'); ?>;"><?php echo esc_html($o['indoor_only_label_text'] ?? 'Indoor Only'); ?></span>
                </div>

                <div id="asm-modal-info-cards" class="grid grid-cols-2 gap-3" data-layout-role="info_cards">
                  <div class="bg-white rounded-2xl p-3 border shadow-sm">
                    <div class="text-xs font-bold" style="color:#64748b;">Shelter Code</div>
                    <div id="asm-field-code" class="text-sm lg:text-base font-extrabold" style="color:#334155;">—</div>
                  </div>
                  <div class="bg-white rounded-2xl p-3 border shadow-sm">
                    <div class="text-xs font-bold" style="color:#64748b;">Age</div>
                    <div id="asm-field-age" class="text-sm lg:text-base font-extrabold" style="color:#334155;">—</div>
                  </div>
                  <div class="bg-white rounded-2xl p-3 border shadow-sm">
                    <div class="text-xs font-bold" style="color:#64748b;">Sex</div>
                    <div id="asm-field-sex" class="text-sm lg:text-base font-extrabold" style="color:#334155;">—</div>
                  </div>
                  <div class="bg-white rounded-2xl p-3 border shadow-sm">
                    <div class="text-xs font-bold" style="color:#64748b;">Breed</div>
                    <div id="asm-field-breed" class="text-sm lg:text-base font-extrabold" style="color:#334155;">—</div>
                  </div>
                </div>

                <div id="asm-modal-compatibility" data-layout-role="compatibility_cards" class="mt-4 grid grid-cols-2 gap-3">
                  <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:#64748b;">Good with cats</div><div id="asm-field-good-cats" class="text-sm lg:text-base font-extrabold" style="color:#334155;">Unknown</div></div>
                  <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:#64748b;">Good with dogs</div><div id="asm-field-good-dogs" class="text-sm lg:text-base font-extrabold" style="color:#334155;">Unknown</div></div>
                  <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:#64748b;">Good with children</div><div id="asm-field-good-children" class="text-sm lg:text-base font-extrabold" style="color:#334155;">Unknown</div></div>
                  <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:#64748b;">Special needs</div><div id="asm-field-special-needs" class="text-sm lg:text-base font-extrabold" style="color:#334155;">Unknown</div></div>
                </div>

                <div id="asm-tips" class="mt-4" data-layout-role="tips" style="color:#64748b;">
                  <?php echo esc_html($o['tips_text']); ?>
                </div>

                <div class="hidden md:flex items-center justify-center mt-4 select-none" id="asm-scroll-hint" data-layout-role="scroll_hint" style="color:#64748b;">
                  <div class="flex items-center gap-2 text-sm font-semibold">
                    <span>Scroll to find out more</span>
                    <svg class="w-4 h-4 asm-scroll-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M12 5v14" />
                      <path d="M19 12l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
              
            </div>

            <div id="asm-modal-desc-col" class="asm-modal-col p-4 sm:p-6 bg-white md:col-span-2 border-t">
              <div class="bg-white rounded-2xl p-4 border shadow-sm" data-layout-role="description">
                <div class="text-xs font-bold mb-2" style="color:#64748b;">Description</div>
                <div id="asm-field-desc" class="leading-relaxed" style="color:#334155;">—</div>

                <div class="mt-4">
                  <div class="asm-modal-divider"></div>
                </div>

                <div id="asm-modal-global-text-wrap" data-layout-role="global_text" class="mt-4<?php echo trim((string)$o['modal_global_text']) === '' ? ' hidden' : ''; ?>">
                  <div id="asm-modal-global-text" class="leading-relaxed" style="color:#334155;"><?php echo esc_html(trim((string)$o['modal_global_text'])); ?></div>
                </div>

                <div id="asm-contact-footer" data-layout-role="contact_footer" class="mt-4 text-sm" style="color:#64748b;">Have a question? <?php if ($modal_contact_url): ?><a href="<?php echo $modal_contact_url; ?>" class="font-semibold underline" style="color:var(--asm-brand);">Contact Us</a><?php else: ?><span class="font-semibold" style="color:var(--asm-brand);">Contact Us</span><?php endif; ?></div>

                <?php
                  $styles = [
                    'primary' => 'background:var(--asm-brand);color:#ffffff;border-color:var(--asm-brand);',
                    'secondary' => 'background:#ffffff;color:var(--asm-brand);border-color:var(--asm-brand);',
                    'outline' => 'background:transparent;color:var(--asm-brand);border-color:var(--asm-brand);',
                  ];
                  $custom_buttons = [];
                  for ($i=1; $i<=3; $i++) {
                    if (empty($o['custom_button_'.$i.'_enabled']) || empty($o['custom_button_'.$i.'_text']) || empty($o['custom_button_'.$i.'_url'])) continue;
                    $custom_buttons[] = [
                      'text' => (string)$o['custom_button_'.$i.'_text'],
                      'url' => esc_url((string)$o['custom_button_'.$i.'_url']),
                      'new_tab' => !empty($o['custom_button_'.$i.'_new_tab']),
                      'style' => $styles[$o['custom_button_'.$i.'_style'] ?? 'primary'] ?? $styles['primary'],
                    ];
                  }
                ?>
                <?php if (!empty($custom_buttons)): ?>
                  <div id="asm-modal-custom-buttons" data-layout-role="custom_buttons" class="mt-4 flex flex-col sm:flex-row gap-2">
                    <?php foreach ($custom_buttons as $btn): ?>
                      <a href="<?php echo $btn['url']; ?>" <?php echo $btn['new_tab'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="inline-flex items-center justify-center px-4 h-10 rounded-xl border-2 text-sm font-semibold hover:shadow-md" style="<?php echo esc_attr($btn['style']); ?>"><?php echo esc_html($btn['text']); ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div id="asm-modal-form-col" class="asm-modal-col p-0 bg-white md:col-span-2 hidden">
              <div id="asm-apply-form-wrap" class="bg-white p-4 sm:p-6" aria-live="polite">
                <style>#asm-apply-form-wrap .asm-apply-summary,#asm-apply-form-wrap .plugin-apply-summary,[id^="asm-apply-cat-"]{display:none !important;}</style>
                <div id="asm-apply-form-mount"></div>
                <p id="asm-apply-form-status" class="hidden" style="color:#64748b;font-weight:600;">Loading application form…</p>
              </div>
            </div>

          </div>
        </div>

      </div>

      <div class="asm-modal-animal-nav">
        <button id="asm-modal-prev-animal" class="asm-modal-nav inline-flex items-center justify-center gap-2 px-4 h-11 rounded-xl border-2 bg-white/95 shadow-md hover:shadow-lg hidden" style="border-color:var(--asm-brand);" type="button" aria-label="Previous animal">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
          <span class="text-sm font-semibold" style="color:var(--asm-brand);">Previous animal</span>
        </button>
        <button id="asm-modal-next-animal" class="asm-modal-nav inline-flex items-center justify-center gap-2 px-4 h-11 rounded-xl border-2 bg-white/95 shadow-md hover:shadow-lg hidden" style="border-color:var(--asm-brand);" type="button" aria-label="Next animal">
          <span class="text-sm font-semibold" style="color:var(--asm-brand);">Next animal</span>
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
        </button>
      </div>
    </div>
  </div>

  <div id="asm-favourites-modal" class="fixed inset-0 z-[2147483646] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="asm-favourites-modal-title" tabindex="-1">
    <div class="absolute inset-0 bg-black/50" id="asm-favourites-backdrop"></div>
    <div class="absolute inset-0 flex flex-col items-center justify-center p-4 sm:p-6"><div class="bg-white rounded-2xl shadow-2xl border-2 w-full max-w-3xl max-h-[80vh] overflow-hidden" style="border-color:var(--asm-brand);"><div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b"><h3 id="asm-favourites-modal-title" class="text-lg sm:text-xl font-extrabold" style="color:#334155;"><?php echo esc_html($o['favourites_modal_title'] ?? 'Saved favourites'); ?></h3><button id="asm-close-favourites-modal" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border-2 bg-white hover:shadow-md" style="border-color:var(--asm-brand);" aria-label="Close"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div><div id="asm-favourites-modal-body" class="p-4 sm:p-6 overflow-y-auto" style="max-height:calc(80vh - 72px);"></div></div></div>
  </div>

  <div id="asm-compare-modal" class="fixed inset-0 z-[2147483646] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="asm-compare-modal-title" tabindex="-1">
    <div class="absolute inset-0 bg-black/50" id="asm-compare-backdrop"></div>
    <div class="absolute inset-0 flex flex-col items-center justify-center p-4 sm:p-6"><div class="bg-white rounded-2xl shadow-2xl border-2 w-full max-w-5xl max-h-[84vh] overflow-hidden" style="border-color:var(--asm-brand);"><div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b"><h3 id="asm-compare-modal-title" class="text-lg sm:text-xl font-extrabold" style="color:#334155;"><?php echo esc_html($o['compare_modal_title'] ?? 'Compare favourites'); ?></h3><button id="asm-close-compare-modal" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border-2 bg-white hover:shadow-md" style="border-color:var(--asm-brand);" aria-label="Close"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--asm-brand)" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div><div id="asm-compare-modal-body" class="p-4 sm:p-6 overflow-y-auto" style="max-height:calc(84vh - 72px);"></div></div></div>
  </div>

</div>

<script>
(function(){
  const ROOT = document.getElementById("asm-adoptables-widget");
  if(!ROOT) return;

  const LOADING_STATUS_TEXT = <?php echo wp_json_encode((string)$o['loading_status_text']); ?>;

  const ASM_WIDGET = {
    proxyBase: "/wp-json/plugin/v1/adoptables",
    imageProxyBase: "/wp-json/plugin/v1/animal-image",
    brandColor: getComputedStyle(ROOT).getPropertyValue("--asm-brand").trim() || "#ff647e",
    catsOnly: <?php echo !empty($o['cats_only']) ? 'true' : 'false'; ?>,
    showReservationLabel: <?php echo !empty($o['show_reservation_label']) ? 'true' : 'false'; ?>,
    showPendingReservationLabel: <?php echo !empty($o['show_pending_reservation_label']) ? 'true' : 'false'; ?>,
    showOtherReservationLabel: <?php echo !empty($o['show_other_reservation_label']) ? 'true' : 'false'; ?>,
    reservationPendingLabel: <?php echo wp_json_encode((string)$o['reservation_pending_label']); ?>,
    reservationActiveLabel: <?php echo wp_json_encode((string)$o['reservation_active_label']); ?>,
    reservationLabelHAlign: <?php echo wp_json_encode((string)$o['reservation_label_halign']); ?>,
    reservationLabelVAlign: <?php echo wp_json_encode((string)$o['reservation_label_valign']); ?>,

    cols: {
      m: <?php echo (int)$o['cols_mobile']; ?>,
      t: <?php echo (int)$o['cols_tablet']; ?>,
      d: <?php echo (int)$o['cols_desktop']; ?>
    },
    rows: {
      m: <?php echo (int)$o['rows_mobile']; ?>,
      t: <?php echo (int)$o['rows_tablet']; ?>,
      d: <?php echo (int)$o['rows_desktop']; ?>
    },
    gaps: {
      m: { x: <?php echo (int)$o['gap_x_mobile']; ?>,  y: <?php echo (int)$o['gap_y_mobile']; ?> },
      t: { x: <?php echo (int)$o['gap_x_tablet']; ?>,  y: <?php echo (int)$o['gap_y_tablet']; ?> },
      d: { x: <?php echo (int)$o['gap_x_desktop']; ?>, y: <?php echo (int)$o['gap_y_desktop']; ?> }
    },
    enableDeepLinks: <?php echo !empty($o['enable_deep_links']) ? 'true' : 'false'; ?>,
    shareButtonText: <?php echo wp_json_encode((string)($o['share_button_text'] ?? 'Share')); ?>,
    shareCopiedText: <?php echo wp_json_encode((string)($o['share_copied_text'] ?? 'Link copied')); ?>,
    enableFilters: <?php echo !empty($o['enable_filters']) ? 'true' : 'false'; ?>,
    enableFilterAge: <?php echo !empty($o['enable_filter_age']) ? 'true' : 'false'; ?>,
    enableFilterSex: <?php echo !empty($o['enable_filter_sex']) ? 'true' : 'false'; ?>,
    enableFilterBreed: <?php echo !empty($o['enable_filter_breed']) ? 'true' : 'false'; ?>,
    enableExcludePendingFilter: <?php echo !empty($o['enable_exclude_pending_filter']) ? 'true' : 'false'; ?>,
    fallbackDescription: <?php echo wp_json_encode((string)($o['fallback_description'] ?? 'More information about this animal will be added soon. Please contact the rescue for details.')); ?>,
    detectBonded: <?php echo !empty($o['detect_bonded_from_description']) ? 'true' : 'false'; ?>,
    detectIndoorOnly: <?php echo !empty($o['detect_indoor_only_from_description']) ? 'true' : 'false'; ?>,
    enableSlideshowControls: <?php echo !empty($o['enable_modal_slideshow_controls']) ? 'true' : 'false'; ?>,
    enableFavourites: <?php echo !empty($o['enable_favourites']) ? 'true' : 'false'; ?>,
    favouritesLabel: <?php echo wp_json_encode((string)($o['favourites_label_text'] ?? 'Favourites')); ?>,
    favouriteButtonPosition: <?php echo wp_json_encode((string)($o['favourite_button_position'] ?? 'top_left')); ?>,
    enableCompareTool: <?php echo !empty($o['enable_compare_tool']) ? 'true' : 'false'; ?>,
    enableModals: <?php echo !empty($o['enable_modals']) || !empty($o['enable_adoptables_modals']) ? 'true' : 'false'; ?>,
    compareButtonText: <?php echo wp_json_encode((string)($o['compare_button_text'] ?? 'Compare favourites')); ?>,
    displayStyle: <?php echo wp_json_encode((string)($o['display_style'] ?? 'classic')); ?>,
    builderCardOrder: <?php echo wp_json_encode(array_values(array_filter(array_map('trim', preg_split('/\R+/', (string)($o['builder_card_order'] ?? '')))))); ?>,
    builderModalOrder: <?php echo wp_json_encode(array_values(array_filter(array_map('trim', preg_split('/\R+/', (string)($o['builder_modal_order'] ?? '')))))); ?>,
    builderHeaderActions: <?php echo wp_json_encode(array_values(array_filter(array_map('trim', preg_split('/\R+/', (string)($o['builder_header_actions'] ?? '')))))); ?>,
    applyFormEnabled: <?php echo $apply_button_enabled ? 'true' : 'false'; ?>,
    applyFormScriptUrl: <?php echo wp_json_encode($apply_form_script_url); ?>
  };

  function qs(id){ return document.getElementById(id); }

  function animalEventContext(a){
    if (!a) return {};
    return {
      animal_id: String(getCatId(a) || ''),
      animal_name: safeText(a.ANIMALNAME ?? a.AnimalName ?? a.name ?? ''),
      animal_code: safeText(a.CODE ?? a.SHELTERCODE ?? a.ShelterCode ?? ''),
      modal_url: modalLink(a)
    };
  }

  function trackEvent(name, context){
    try {
      const body = new URLSearchParams();
      body.set("action", "plugin_suite_track");
      body.set("event", name);
      if (context) body.set("context", JSON.stringify(context));
      body.set("nonce", <?php echo wp_json_encode(wp_create_nonce('plugin_suite_track')); ?>);
      fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, { method: "POST", credentials: "same-origin", headers: {"Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"}, body: body.toString() });
    } catch (e) {}
  }


  const RECENTLY_VIEWED_KEY = "plugin_recently_viewed_cats_v1";
  const FAVOURITES_KEY = "plugin_suite_favourites_v1";

  function getCatId(a){
    return String(a.ID ?? a.AnimalID ?? a.ANIMALID ?? a.animalid ?? "");
  }

  function getViewedItems(){
    if (!ASM_WIDGET.enableRecentlyViewed) return [];
    try {
      const raw = localStorage.getItem(RECENTLY_VIEWED_KEY);
      const items = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(items)) return [];
      const now = Date.now();
      const maxAge = Math.max(1, ASM_WIDGET.recentlyViewedDays) * 86400000;
      return items.filter(item => item && item.id && item.ts && (now - Number(item.ts) <= maxAge));
    } catch (e) {
      return [];
    }
  }

  function saveViewedItems(items){
    try {
      localStorage.setItem(RECENTLY_VIEWED_KEY, JSON.stringify(items.slice(0, Math.max(1, ASM_WIDGET.recentlyViewedLimit))));
    } catch (e) {}
  }

  function markRecentlyViewed(id){
    return;
    if (!id) return;
    const now = Date.now();
    const items = getViewedItems().filter(item => String(item.id) !== String(id));
    items.unshift({ id: String(id), ts: now });
    saveViewedItems(items);
  }

  function wasRecentlyViewed(id){
    return false;
  }

  function getFavouriteIds(){
    if (!ASM_WIDGET.enableFavourites) return [];
    try {
      const raw = localStorage.getItem(FAVOURITES_KEY);
      const items = raw ? JSON.parse(raw) : [];
      return Array.isArray(items) ? items.map(String) : [];
    } catch (e) { return []; }
  }
  function saveFavouriteIds(ids){ try { localStorage.setItem(FAVOURITES_KEY, JSON.stringify(ids.slice(0, 100))); } catch (e) {} }
  function isFavourite(id){ return getFavouriteIds().includes(String(id)); }
  function toggleFavourite(id){
    const ids = getFavouriteIds();
    const sid = String(id);
    const idx = ids.indexOf(sid);
    if (idx >= 0) ids.splice(idx,1); else ids.unshift(sid);
    trackEvent("favourite_toggle");
    saveFavouriteIds(ids);
    renderFavouritesSummary();
    applyFilters({ preservePage: true });
  }
  function renderFavouritesSummary(){
    const wrap = qs('asm-favourites-summary');
    const favWrap = qs('asm-favourites-only-wrap');
    if (!wrap || !favWrap || !ASM_WIDGET.enableFavourites) return;
    favWrap.classList.remove('hidden');
    const count = getFavouriteIds().length;
    wrap.classList.toggle('hidden', false);
    wrap.innerHTML = `<div class="flex flex-wrap gap-2"><button type="button" id="asm-favourites-count-btn" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 bg-white shadow-sm" style="border-color:${ASM_WIDGET.brandColor}; color:#334155;"><span style="color:${ASM_WIDGET.brandColor};">${favouriteIconSvg(true,'w-4 h-4')}</span><span>${escHtml(ASM_WIDGET.favouritesLabel)} (${count})</span></button>${count > 0 ? `<button type="button" id="asm-clear-favourites-btn" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 bg-white shadow-sm" style="border-color:${ASM_WIDGET.brandColor}; color:#334155;">Clear favourites</button>` : ``}${ASM_WIDGET.enableCompareTool ? `<button type="button" id="asm-compare-favourites-btn" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 bg-white shadow-sm" style="border-color:${ASM_WIDGET.brandColor}; color:#334155;">${escHtml(ASM_WIDGET.compareButtonText)}</button>` : ``}</div>`;
    const only = qs('asm-show-favourites-only');
    const btn = qs('asm-favourites-count-btn');
    if (btn) btn.addEventListener('click', () => { openFavouritesModal(); });
    const cmp = qs('asm-compare-favourites-btn');
    if (cmp) cmp.addEventListener('click', openCompareModal);
    const clr = qs('asm-clear-favourites-btn');
    if (clr) clr.addEventListener('click', () => { saveFavouriteIds([]); renderFavouritesSummary(); applyFilters(); });
  }

  function slugifyPart(value){
    return String(value || "")
      .toLowerCase()
      .replace(/&/g, " and ")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .replace(/-{2,}/g, "-");
  }

  function catShareSlug(animal){
    const name = safeText(animal?.ANIMALNAME ?? animal?.AnimalName ?? animal?.NAME, "");
    const code = safeText(animal?.CODE ?? animal?.SHELTERCODE ?? animal?.ShelterCode, "");
    const id = String(getCatId(animal)).replace(/\D+/g, "");
    const left = slugifyPart(name);
    const right = slugifyPart(code);
    return [left, right].filter(Boolean).join("-") || id;
  }

  function modalUrl(animal){
    const url = new URL(window.location.href);
    if (ASM_WIDGET.enableDeepLinks && animal) url.searchParams.set("cat", catShareSlug(animal));
    return url.toString();
  }

  function syncModalUrl(animal){
    if (!ASM_WIDGET.enableDeepLinks || !window.history || !window.history.replaceState) return;
    const url = new URL(window.location.href);
    if (animal) url.searchParams.set("cat", catShareSlug(animal));
    else url.searchParams.delete("cat");
    window.history.replaceState({}, "", url.toString());
  }

  function requestedCatId(){
    const url = new URL(window.location.href);
    return (url.searchParams.get("cat") || "").trim().toLowerCase();
  }


  function showStatus(msg, isError=false){
    const el = qs("asm-adoptables-status");
    if(!el) return;
    el.style.display = msg ? "block" : "none";
    el.textContent = msg || "";
    el.style.color = isError ? "#b91c1c" : "#64748b";
  }

  function safeText(v, fallback="—"){
    const s = (v ?? "").toString().trim();
    return s ? s : fallback;
  }

  function isCat(a){
    const sid = Number(a.SPECIESID ?? a.speciesid ?? a.SpeciesID ?? NaN);
    const sname = (a.SPECIESNAME ?? a.speciesname ?? a.SpeciesName ?? "").toString().toLowerCase();
    return sid === 2 || sname.includes("cat");
  }

  function daysOnShelter(a){
    const d = Number(a.DAYSONSHELTER ?? a.DaysOnShelter ?? 0);
    return Number.isFinite(d) ? d : 0;
  }

  function imageCount(a){
    const c = Number(a.WEBSITEIMAGECOUNT ?? a.WebsiteImageCount ?? a.WEBSITEIMAGES ?? 1);
    return Math.max(1, Math.min(12, Number.isFinite(c) ? c : 1));
  }

  function currentDevice(){
    if (window.matchMedia("(min-width: 1024px)").matches) return "d";
    if (window.matchMedia("(min-width: 768px)").matches) return "t";
    return "m";
  }

  function applyGridColumns(){
    const grid = qs("asm-grid");
    if(!grid) return;

    const dev = currentDevice();
    const cols = ASM_WIDGET.cols[dev] || 2;
    const g = ASM_WIDGET.gaps[dev] || { x: 20, y: 20 };

    grid.style.display = "grid";
    grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
    grid.style.columnGap = `${g.x}px`;
    grid.style.rowGap = `${g.y}px`;
  }

  function parsePossibleJson(value){
    if (typeof value !== "string") return value;
    const s = value.trim();
    if (!s) return value;
    if ((s.startsWith("[") && s.endsWith("]")) || (s.startsWith("{") && s.endsWith("}"))) {
      try { return JSON.parse(s); } catch(e) {}
    }
    return value;
  }

  function getReservationInfo(a){
    if (!ASM_WIDGET.showReservationLabel) {
      return { show: false, label: "", kind: "none" };
    }

    const primaryStatus = String(a.primary_reservation_status ?? "").trim().toLowerCase();
    const hasActive = Boolean(a.has_active_reservation);

    if (primaryStatus === "pending adoption") {
      return {
        show: ASM_WIDGET.showPendingReservationLabel,
        label: ASM_WIDGET.reservationPendingLabel,
        kind: "pending"
      };
    }

    if (hasActive) {
      return {
        show: ASM_WIDGET.showOtherReservationLabel,
        label: ASM_WIDGET.reservationActiveLabel,
        kind: "active"
      };
    }

    return { show: false, label: "", kind: "none" };
  }

  function reservationBadgePositionClasses(){
    const h = ASM_WIDGET.reservationLabelHAlign;
    const v = ASM_WIDGET.reservationLabelVAlign;

    let horizontal = "right-2";
    if (h === "left") horizontal = "left-2";
    if (h === "center") horizontal = "left-1/2 -translate-x-1/2";

    let vertical = "top-2";
    if (v === "bottom") vertical = "bottom-2";

    return `${vertical} ${horizontal}`;
  }

  function favouriteAnimals(){
    const ids = getFavouriteIds();
    return (allCats || []).filter(a => ids.includes(String(getCatId(a))));
  }

  function openFavouritesModal(){
    const modal = qs('asm-favourites-modal');
    const body = qs('asm-favourites-modal-body');
    if (!modal || !body) return;
    const cats = favouriteAnimals();
    body.innerHTML = cats.length ? `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">${cats.map(a => { const id = getCatId(a); const name = safeText(a.ANIMALNAME ?? a.AnimalName); const breed = safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1 ?? a.BreedName1); return `<button type="button" class="text-left rounded-2xl overflow-hidden border-2 bg-white shadow-sm hover:shadow-md" style="border-color:${ASM_WIDGET.brandColor};" data-open-fav="${escAttr(id)}"><div class="aspect-square bg-gray-100"><img src="${escAttr(proxyImageUrl(id,1))}" alt="${escAttr(name)}" class="w-full h-full object-cover" loading="eager" decoding="async" /></div><div class="p-3"><div class="font-extrabold" style="color:#334155;">${escHtml(name)}</div><div class="text-sm" style="color:#64748b;">${escHtml(breed)}</div></div></button>`; }).join('')}</div>` : `<div class="text-center"><p style="color:#64748b;margin:0 0 1rem;">No favourites saved yet.</p><button type="button" id="asm-favourites-empty-close" class="inline-flex items-center justify-center rounded-full border px-4 py-2 bg-white shadow-sm" style="border-color:${ASM_WIDGET.brandColor};color:#334155;">Close</button></div>`;
    body.querySelectorAll('[data-open-fav]').forEach(btn => btn.addEventListener('click', () => { const cat = (allCats || []).find(a => String(getCatId(a)) === String(btn.getAttribute('data-open-fav'))); if (cat) { closeFavouritesModal(); openModal(cat); } }));
    body.querySelector('#asm-favourites-empty-close')?.addEventListener('click', closeFavouritesModal);
    openDialog(modal, qs('asm-close-favourites-modal'));
  }
  function closeFavouritesModal(){ const modal=qs('asm-favourites-modal'); if(!modal)return; closeDialog(modal); }
  function openCompareModal(){
    const modal = qs('asm-compare-modal');
    const body = qs('asm-compare-modal-body');
    if (!modal || !body) return;
    const cats = favouriteAnimals().slice(0,4);
    const cols = cats.length >= 4 ? 'xl:grid-cols-4' : (cats.length >= 3 ? 'lg:grid-cols-3' : 'md:grid-cols-2');
    body.innerHTML = cats.length >= 2 ? `<div class="grid grid-cols-1 ${cols} gap-4">${cats.map(a => { const id = getCatId(a); const name = safeText(a.ANIMALNAME ?? a.AnimalName); const breed = safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1 ?? a.BreedName1); const age = safeText(a.ANIMALAGE ?? a.AnimalAge); const sex = safeText(a.SEXNAME ?? a.SexName ?? a.SEX); const code = safeText(a.CODE ?? a.ShelterCode ?? a.SHELTERCODE); return `<div class="rounded-2xl border-2 p-4 bg-white" style="border-color:${ASM_WIDGET.brandColor};"><div class="aspect-square rounded-xl overflow-hidden bg-gray-100 mb-3"><img src="${escAttr(proxyImageUrl(id,1))}" alt="${escAttr(name)}" class="w-full h-full object-cover" loading="eager" decoding="async" /></div><h4 class="text-lg font-extrabold mb-3" style="color:#334155;">${escHtml(name)}</h4><div class="space-y-2 text-sm"><div><strong>Breed:</strong> ${escHtml(breed)}</div><div><strong>Age:</strong> ${escHtml(age)}</div><div><strong>Sex:</strong> ${escHtml(sex)}</div><div><strong>Code:</strong> ${escHtml(code)}</div><div><strong>Bonded:</strong> ${hasBondedLabel(a) ? 'Yes' : 'No'}</div><div><strong>Indoor only:</strong> ${hasIndoorOnlyLabel(a) ? 'Yes' : 'No'}</div></div></div>`; }).join('')}</div>` : `<p style="color:#64748b;">Save at least two favourites to compare them.</p>`;
    openDialog(modal, qs('asm-close-compare-modal'));
  }
  function closeCompareModal(){ const modal=qs('asm-compare-modal'); if(!modal)return; closeDialog(modal); }

  function animalAgeMonths(a){
    const direct = Number(a.AGE_MONTHS ?? a.age_months ?? a.AgeMonths);
    if (Number.isFinite(direct) && direct >= 0) return direct;

    const age = String(a.ANIMALAGE ?? a.AnimalAge ?? '').toLowerCase().trim();
    if (!age) return null;

    const yearsMatch = age.match(/(\d+(?:\.\d+)?)\s*year/);
    const monthsMatch = age.match(/(\d+(?:\.\d+)?)\s*month/);
    const weeksMatch = age.match(/(\d+(?:\.\d+)?)\s*week/);

    let totalMonths = 0;
    let matched = false;

    if (yearsMatch && yearsMatch[1] !== '') {
      totalMonths += parseFloat(yearsMatch[1]) * 12;
      matched = true;
    }
    if (monthsMatch && monthsMatch[1] !== '') {
      totalMonths += parseFloat(monthsMatch[1]);
      matched = true;
    }
    if (weeksMatch && weeksMatch[1] !== '') {
      totalMonths += parseFloat(weeksMatch[1]) / 4.345;
      matched = true;
    }

    if (matched) return Math.max(0, Math.round(totalMonths));
    if (age.includes('kitten')) return 6;
    if (age.includes('senior')) return 120;
    return null;
  }

  function inferAgeGroup(a){
    const band = safeText(a.AGE_BAND ?? a.age_band ?? a.AgeBand, '');
    if (band) return band;
    const totalMonths = animalAgeMonths(a);
    if (totalMonths === null) return '';
    if (totalMonths < 12) return 'Under 1 year';
    if (totalMonths < 36) return '1 to 3 years';
    if (totalMonths < 60) return '3 to 5 years';
    return '5+ years';
  }
  function descText(a){ return String(a.ANIMALCOMMENTS ?? a.WEBSITEMEDIANOTES ?? a.DESCRIPTION ?? a.ANIMALDESCRIPTION ?? '').trim(); }
  function hasPendingAdoption(a){ return String(a.primary_reservation_status ?? '').trim().toLowerCase() === 'pending adoption'; }
  function hasBondedLabel(a){ return ASM_WIDGET.detectBonded && /bonded with/i.test(descText(a)); }
  function hasIndoorOnlyLabel(a){ return ASM_WIDGET.detectIndoorOnly && /\bindoor\b/i.test(descText(a)); }
  function pickFirstValue(a, keys){ for (const key of keys){ const value = a?.[key]; if (value !== undefined && value !== null && safeText(value,'') !== '') return safeText(value,''); } return ''; }
  function pickByNormalisedKey(a, target){
    if (!a || typeof a !== 'object') return '';
    const wanted = String(target || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    for (const [key, value] of Object.entries(a)) {
      const normKey = String(key || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      if (normKey === wanted && value !== undefined && value !== null && safeText(value,'') !== '') return safeText(value,'');
    }
    return '';
  }
  function pickByKeyFragments(a, fragments){
    if (!a || typeof a !== 'object') return '';
    const wanted = (Array.isArray(fragments) ? fragments : [fragments]).map(f => String(f || '').toLowerCase().replace(/[^a-z0-9]/g, ''));
    for (const [key, value] of Object.entries(a)) {
      const normKey = String(key || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      if (wanted.every(f => normKey.includes(f)) && value !== undefined && value !== null && safeText(value,'') !== '') return safeText(value,'');
    }
    return '';
  }
  function pickGoodWithNameValue(a, kind){
    const directKeys = {
      cats: ['IsGoodWithCatsName','ISGOODWITHCATSNAME','isgoodwithcatsname','GOODWITHCATSNAME','GoodWithCatsName','good_with_cats_name'],
      dogs: ['IsGoodWithDogsName','ISGOODWITHDOGSNAME','isgoodwithdogsname','GOODWITHDOGSNAME','GoodWithDogsName','good_with_dogs_name'],
      children: ['IsGoodWithChildrenName','ISGOODWITHCHILDRENNAME','isgoodwithchildrenname','GOODWITHCHILDRENNAME','GoodWithChildrenName','good_with_children_name']
    };
    const exactTarget = { cats: 'IsGoodWithCatsName', dogs: 'IsGoodWithDogsName', children: 'IsGoodWithChildrenName' };
    const fragments = { cats: ['good','with','cats'], dogs: ['good','with','dogs'], children: ['good','with','children'] };
    return pickFirstValue(a, directKeys[kind]) || pickByNormalisedKey(a, exactTarget[kind]) || pickByKeyFragments(a, fragments[kind]);
  }
  function normaliseChoice(value, allowed){ const raw = safeText(value, ''); if (!raw) return 'Unknown'; const cleaned = raw.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim(); const hit = allowed.find(v => v.toLowerCase() === cleaned.toLowerCase()); if (hit) return hit; const lower = cleaned.toLowerCase(); if (['1','true','yes','y'].includes(lower)) return 'Yes'; if (['0','false','no','n'].includes(lower)) return 'No'; return cleaned; }
  function goodWithCatsValue(a){ return normaliseChoice(pickGoodWithNameValue(a, 'cats'), ['Yes','No','Unknown','Selective']); }
  function goodWithDogsValue(a){ return normaliseChoice(pickGoodWithNameValue(a, 'dogs'), ['Yes','No','Unknown','Selective']); }
  function goodWithChildrenValue(a){ return normaliseChoice(pickGoodWithNameValue(a, 'children'), ['Yes','No','Unknown','Over 5','Over 12']); }
  function specialNeedsValue(a){
    const raw = pickFirstValue(a, ['HasSpecialNeeds','HASSPECIALNEEDS','hasspecialneeds','SPECIALNEEDS','SpecialNeeds','special_needs','SPECIAL_NEEDS','SPECIALNEEDSNAME']);
    if (raw === null || raw === undefined || raw === '') return 'Unknown';
    const val = String(raw).trim().toLowerCase();
    if (['1','true','yes','y'].includes(val)) return 'Yes – see description';
    if (['0','false','no','n'].includes(val)) return 'No';
    if (val === 'unknown') return 'Unknown';
    return val === 'no' ? 'No' : 'Yes – see description';
  }
  function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function escAttr(s){ return escHtml(s).replace(/"/g,String.fromCharCode(38,113,117,111,116,59)).replace(/'/g,String.fromCharCode(38,35,48,51,57,59)); }
  function fillSelect(el, items){ if(!el) return; const current=el.value; el.innerHTML='<option value="">All</option>' + items.map(v=>`<option value="${escHtml(v)}">${escHtml(v)}</option>`).join(''); if ([...el.options].some(o=>o.value===current)) el.value=current; }
  function setupFilterUi(rawCats){
    const wrap = qs('asm-filters-wrap');
    const row = qs('asm-filter-button-row');
    if (!wrap) return;
    const enabled = ASM_WIDGET.enableFilters;
    row?.classList.toggle('hidden', !enabled);
    if (!enabled) return;
    qs('asm-filter-age-wrap')?.classList.toggle('hidden', !ASM_WIDGET.enableFilterAge);
    qs('asm-filter-sex-wrap')?.classList.toggle('hidden', !ASM_WIDGET.enableFilterSex);
    qs('asm-filter-breed-wrap')?.classList.toggle('hidden', !ASM_WIDGET.enableFilterBreed);
    qs('asm-filter-pending-wrap')?.classList.toggle('hidden', !ASM_WIDGET.enableExcludePendingFilter);
    if (ASM_WIDGET.enableFilterAge) fillSelect(qs('asm-filter-age'), ['Under 1 year','1 to 3 years','3 to 5 years','5+ years']);
    if (ASM_WIDGET.enableFilterSex) fillSelect(qs('asm-filter-sex'), Array.from(new Set(rawCats.map(a=>safeText(a.SEXNAME ?? a.SexName ?? a.SEX, '')).filter(Boolean))).sort());
    if (ASM_WIDGET.enableFilterBreed) fillSelect(qs('asm-filter-breed'), Array.from(new Set(rawCats.map(a=>safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1 ?? a.BreedName1, '')).filter(Boolean))).sort());
    ['asm-filter-age','asm-filter-sex','asm-filter-breed','asm-filter-indoor','asm-filter-bonded','asm-filter-good-cats','asm-filter-good-dogs','asm-filter-good-children','asm-filter-hide-pending','asm-show-favourites-only'].forEach(id=>{ const el=qs(id); if(el && !el.dataset.bound){ el.addEventListener('change', applyFilters); el.dataset.bound='1'; } });
    renderFavouritesSummary();
  }
  function applyFilters(options){
    const opts = options && typeof options === 'object' ? options : {};
    const preservePage = !!opts.preservePage;
    const currentPage = pageIndex;
    const raw = Array.isArray(rawCats) ? rawCats.slice() : [];
    let cats = raw;
    const age = qs('asm-filter-age')?.value || '';
    const sex = qs('asm-filter-sex')?.value || '';
    const breed = qs('asm-filter-breed')?.value || '';
    const indoor = qs('asm-filter-indoor')?.value || '';
    const bonded = qs('asm-filter-bonded')?.value || '';
    const goodCats = qs('asm-filter-good-cats')?.value || '';
    const goodDogs = qs('asm-filter-good-dogs')?.value || '';
    const goodChildren = qs('asm-filter-good-children')?.value || '';
    const hidePending = !!qs('asm-filter-hide-pending')?.checked;
    const showFavs = !!qs('asm-show-favourites-only')?.checked;
    if (age) cats = cats.filter(a => inferAgeGroup(a) === age);
    if (sex) cats = cats.filter(a => safeText(a.SEXNAME ?? a.SexName ?? a.SEX, '') === sex);
    if (breed) cats = cats.filter(a => safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1 ?? a.BreedName1, '') === breed);
    if (indoor === 'yes') cats = cats.filter(a => hasIndoorOnlyLabel(a));
    if (indoor === 'no') cats = cats.filter(a => !hasIndoorOnlyLabel(a));
    if (bonded === 'yes') cats = cats.filter(a => hasBondedLabel(a));
    if (bonded === 'no') cats = cats.filter(a => !hasBondedLabel(a));
    if (goodCats) cats = cats.filter(a => goodWithCatsValue(a) === goodCats);
    if (goodDogs) cats = cats.filter(a => goodWithDogsValue(a) === goodDogs);
    if (goodChildren) cats = cats.filter(a => goodWithChildrenValue(a) === goodChildren);
    if (hidePending) cats = cats.filter(a => !hasPendingAdoption(a));
    if (showFavs && ASM_WIDGET.enableFavourites) { const ids = getFavouriteIds(); cats = cats.filter(a => ids.includes(String(getCatId(a)))); }
    filteredCats = cats.slice();
    pageIndex = preservePage ? currentPage : 0;
    updateSeoForAnimals(filteredCats.length ? filteredCats : raw);
    renderPage();
  }

  const dialogStack = [];
  const dialogIds = ['asm-modal','asm-filters-wrap','asm-favourites-modal','asm-compare-modal'];
  function isOpenDialog(el){ return !!el && !el.classList.contains('hidden') && el.getAttribute('aria-hidden') !== 'true'; }
  function anyDialogOpen(){ return dialogIds.some(id => isOpenDialog(qs(id))); }
  function focusableEls(container){
    if (!container) return [];
    const selector = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
    return Array.from(container.querySelectorAll(selector)).filter(el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length));
  }
  function topDialog(){
    for (let i = dialogStack.length - 1; i >= 0; i--) {
      if (isOpenDialog(dialogStack[i].el)) return dialogStack[i].el;
    }
    return dialogIds.map(id => qs(id)).filter(isOpenDialog).pop() || null;
  }
  function openDialog(dialog, initialFocus){
    if (!dialog) return;
    const exists = dialogStack.find(entry => entry.el === dialog);
    const active = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const hiddenOwner = active ? active.closest('[aria-hidden="true"]') : null;
    const trigger = active && !dialog.contains(active) && !hiddenOwner ? active : null;
    if (!exists) dialogStack.push({ el: dialog, trigger });
    dialog.classList.remove('hidden');
    dialog.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
    window.setTimeout(() => {
      const target = initialFocus || focusableEls(dialog)[0] || dialog;
      if (target && typeof target.focus === 'function') target.focus({ preventScroll: true });
    }, 0);
  }
  function closeDialog(dialog, restoreFocus = true){
    if (!dialog) return;
    dialog.classList.add('hidden');
    dialog.setAttribute('aria-hidden','true');
    const idx = dialogStack.findIndex(entry => entry.el === dialog);
    const entry = idx >= 0 ? dialogStack.splice(idx, 1)[0] : null;
    if (!anyDialogOpen()) document.body.style.overflow='';
    if (restoreFocus && entry && entry.trigger && document.contains(entry.trigger) && typeof entry.trigger.focus === 'function') {
      window.setTimeout(() => entry.trigger.focus({ preventScroll: true }), 0);
    }
  }
  function closeTopDialog(){
    const dialog = topDialog();
    if (!dialog) return false;
    if (dialog.id === 'asm-modal') closeModal();
    else if (dialog.id === 'asm-filters-wrap') closeFilters();
    else if (dialog.id === 'asm-favourites-modal') closeFavouritesModal();
    else if (dialog.id === 'asm-compare-modal') closeCompareModal();
    return true;
  }
  function trapDialogFocus(event, dialog){
    if (!dialog || event.key !== 'Tab') return;
    const nodes = focusableEls(dialog);
    if (!nodes.length) {
      event.preventDefault();
      dialog.focus?.({ preventScroll: true });
      return;
    }
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function openFilters(){ ensureModalInBody(); const m=qs('asm-filters-wrap'); if(!m) return; openDialog(m, qs('asm-close-filters')); }
  function closeFilters(){ const m=qs('asm-filters-wrap'); if(!m) return; closeDialog(m); }
  function resetFilters(){ ['asm-filter-age','asm-filter-sex','asm-filter-breed','asm-filter-indoor','asm-filter-bonded','asm-filter-good-cats','asm-filter-good-dogs','asm-filter-good-children'].forEach(id=>{ const el=qs(id); if(el) el.value=''; }); ['asm-filter-hide-pending','asm-show-favourites-only'].forEach(id=>{ const el=qs(id); if(el) el.checked=false; }); applyFilters(); }

  let rawCats = [];
  let allCats = [];
  let filteredCats = [];
  let pageIndex = 0;

  let stableMinHeight = 0;
  function updateStableHeight(){
    const wrap = qs("asm-grid-wrap");
    const grid = qs("asm-grid");
    if(!wrap || !grid) return;
    wrap.style.minHeight = "0px";
    const h = grid.getBoundingClientRect().height;
    stableMinHeight = Math.max(stableMinHeight, Math.ceil(h));
    wrap.style.minHeight = stableMinHeight + "px";
  }

  function resetStableHeight(){
    stableMinHeight = 0;
    const wrap = qs("asm-grid-wrap");
    if (wrap) wrap.style.minHeight = "0px";
  }

  function cardTemplate(a){
    const id = getCatId(a);
    const name = safeText(a.ANIMALNAME ?? a.AnimalName ?? a.NAME);
    const age = safeText(a.ANIMALAGE ?? a.AnimalAge);
    const sex = safeText(a.SEXNAME ?? a.SexName ?? a.SEX);
    const breed = safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1 ?? a.BreedName1);
    const cardImgUrl = proxyImageUrl(id, 1);
    const reservation = getReservationInfo(a);
    const badgePos = reservationBadgePositionClasses();
    const isFav = isFavourite(id);
    const displayClass = ASM_WIDGET.displayStyle === "list" ? "sm:flex sm:items-stretch" : "";
    const safeName = escHtml(name);
    const safeAge = escHtml(age);
    const safeSex = escHtml(sex);
    const safeBreed = escHtml(breed);
    const safeId = escAttr(id);
    const safeNameAttr = escAttr(name);
    const safeImgUrl = escAttr(cardImgUrl);
    const bits = {
      image: `<div class="relative"><div class="asm-ad-imgwrap bg-gray-100 aspect-square w-full"><img src="${safeImgUrl}" alt="${safeNameAttr}" class="block w-full h-full object-cover" loading="eager" decoding="async" fetchpriority="auto" /></div><div class="hidden sm:block absolute inset-x-0 bottom-0 p-1.5 sm:p-2"><div class="rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 bg-white/85 backdrop-blur-sm shadow-sm border" style="border-color:${ASM_WIDGET.brandColor};"><div class="font-extrabold leading-tight truncate text-[13px] sm:text-base md:text-lg" style="color:#334155;">${safeName}</div><div class="font-semibold truncate text-[11px] sm:text-sm" style="color:#64748b;">${safeSex} • ${safeAge}</div></div></div></div>`,
      reservation_badge: reservation.show ? `<div class="absolute ${badgePos} px-2 py-1 rounded-full font-extrabold text-white shadow text-[10px] sm:text-sm lg:text-base" style="background:${ASM_WIDGET.brandColor}; max-width: calc(100% - 1rem);">${escHtml(reservation.label)}</div>` : ``,
      name_meta: `<div class="asm-ad-card-pad sm:hidden"><div class="font-extrabold leading-tight truncate text-[15px]" style="color:#334155;">${safeName}</div><div class="font-semibold truncate text-[12px]" style="color:#64748b;">${safeSex} • ${safeAge}</div></div>`,
      breed_line: `<div class="asm-ad-card-pad hidden sm:block"><div class="font-semibold truncate text-xs sm:text-sm lg:text-base" style="color:#64748b;">${safeBreed}</div></div>`,
      favourite_button: ASM_WIDGET.enableFavourites && ASM_WIDGET.favouriteButtonPosition !== 'hidden' ? `<button type="button" class="asm-fav-toggle absolute z-20 inline-flex items-center justify-center w-7 h-7 sm:w-10 sm:h-10 rounded-full border-2 bg-white/95 shadow asm-fav-${escAttr(ASM_WIDGET.favouriteButtonPosition.replace('_','-'))}" style="border-color:${ASM_WIDGET.brandColor}; color:${ASM_WIDGET.brandColor};" data-favid="${safeId}" aria-pressed="${isFav ? 'true' : 'false'}" aria-label="${isFav ? 'Remove from favourites' : 'Add to favourites'}: ${safeNameAttr}">${favouriteIconSvg(isFav, "w-3.5 h-3.5 sm:w-5 sm:h-5")}</button>` : ``
    };
    const order = Array.isArray(ASM_WIDGET.builderCardOrder) && ASM_WIDGET.builderCardOrder.length ? ASM_WIDGET.builderCardOrder : ['image','reservation_badge','name_meta','breed_line','favourite_button'];
    return `<div class="asm-ad-card ${displayClass} group text-left bg-white shadow-md overflow-hidden transition-all duration-300 hover:shadow-xl hover:scale-[1.02] focus-within:ring-4 focus-within:ring-pink-200" style="position:relative;border-style:solid;border-color:var(--asm-card-border-color);border-width:var(--asm-card-border-width);" data-animalid="${safeId}" data-animal-name="${safeNameAttr}">${order.map(key => bits[key] ?? '').join('')}<button type="button" class="asm-card-open absolute inset-0" data-animalid="${safeId}" aria-label="Open ${safeNameAttr}" aria-haspopup="dialog" aria-controls="asm-modal" style="background:transparent;border:0;padding:0;margin:0;cursor:pointer;z-index:1;"></button></div>`;
  }


  function favouriteIconSvg(filled, classes){
    const cls = classes || 'w-4 h-4';
    if (filled) {
      return `<svg class="${cls}" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5"><path d="M12.1 20.3l-.1.1-.1-.1C7.1 16 4 13.2 4 9.8 4 7.5 5.8 6 8 6c1.3 0 2.5.6 3.3 1.6C12.2 6.6 13.4 6 14.7 6 16.9 6 18.7 7.5 18.7 9.8c0 3.4-3.1 6.2-6.6 10.5z"/></svg>`;
    }
    return `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.1 20.3l-.1.1-.1-.1C7.1 16 4 13.2 4 9.8 4 7.5 5.8 6 8 6c1.3 0 2.5.6 3.3 1.6C12.2 6.6 13.4 6 14.7 6 16.9 6 18.7 7.5 18.7 9.8c0 3.4-3.1 6.2-6.6 10.5z"/></svg>`;
  }

  function proxyImageUrl(animalid, seq){
    const id = encodeURIComponent(String(animalid));
    const s  = encodeURIComponent(String(seq));
    return `${ASM_WIDGET.imageProxyBase}?animalid=${id}&seq=${s}`;
  }

  function renderPage(){
    const grid = qs("asm-grid");
    const prev = qs("asm-prev");
    const next = qs("asm-next");
    const label = qs("asm-page-label");
    const prevB = qs("asm-prev-bottom");
    const nextB = qs("asm-next-bottom");
    const labelB = qs("asm-page-label-bottom");

    if(!grid || !prevB || !nextB || !labelB) return;

    applyGridColumns();

    const dev = currentDevice();
    const cols = ASM_WIDGET.cols[dev] || 2;
    const rows = ASM_WIDGET.rows[dev] || 3;
    const perPage = Math.max(1, cols * rows);

    const sourceCats = Array.isArray(filteredCats) ? filteredCats : (Array.isArray(allCats) ? allCats : []);
    const pageCount = Math.max(1, Math.ceil(sourceCats.length / perPage));
    pageIndex = Math.max(0, Math.min(pageIndex, pageCount - 1));

    const start = pageIndex * perPage;
    const end = Math.min(start + perPage, sourceCats.length);
    const slice = sourceCats.slice(start, end);

    const filtersActive = !!(
      qs('asm-filter-age')?.value ||
      qs('asm-filter-sex')?.value ||
      qs('asm-filter-breed')?.value ||
      qs('asm-filter-good-cats')?.value ||
      qs('asm-filter-good-dogs')?.value ||
      qs('asm-filter-good-children')?.value ||
      qs('asm-filter-special-needs')?.value ||
      qs('asm-filter-hide-pending')?.checked ||
      qs('asm-show-favourites-only')?.checked
    );
    grid.innerHTML = sourceCats.length
      ? slice.map(cardTemplate).join("")
      : `<div class="col-span-full rounded-2xl border-2 bg-white p-6 text-center font-semibold" style="border-color:${ASM_WIDGET.brandColor};color:#64748b;">${filtersActive ? 'No animals found matching your filters.' : 'No animals are currently available.'}</div>`;
    grid.setAttribute("aria-busy", "false");

    const text = sourceCats.length
      ? `Showing ${start + 1}-${end} of ${sourceCats.length} • Page ${pageIndex + 1}/${pageCount}`
      : (filtersActive ? "No animals found matching your filters." : "No animals are currently available.");

    if (label) label.textContent = '';
    if (label && label.parentElement) label.parentElement.style.display = 'none';
    labelB.textContent = text;

    const atStart = pageIndex <= 0;
    const atEnd = pageIndex >= pageCount - 1;
    if (prev) prev.disabled = atStart;
    if (next) next.disabled = atEnd;
    prevB.disabled = atStart;
    nextB.disabled = atEnd;

    grid.querySelectorAll(".asm-ad-card").forEach(card => {
      const fav = card.querySelector(".asm-fav-toggle");
      if (fav) {
        const favHandler = (e) => {
          e.preventDefault();
          e.stopPropagation();
          toggleFavourite(fav.getAttribute("data-favid"));
        };
        fav.addEventListener("click", favHandler);
      }
      const openBtn = card.querySelector(".asm-card-open[data-animalid]");
      if (openBtn) {
        openBtn.addEventListener("click", () => {
          const id = openBtn.getAttribute("data-animalid");
          const a = sourceCats.find(x => String(x.ID ?? x.ANIMALID ?? x.animalid ?? x.AnimalID) === String(id));
          if(a && ASM_WIDGET.enableModals) openModal(a);
        });
      }
    });

    requestAnimationFrame(() => updateStableHeight());
  }

  let currentModalIndex = -1;
  let activeModalAnimal = null;
  let cleanupCurrentModal = null;

  function ensureModalInBody(){
    const moveToBody = (id) => {
      const el = qs(id);
      if (el && el.parentElement !== document.body) {
        document.body.appendChild(el);
      }
    };
    moveToBody("asm-modal");
    moveToBody("asm-filters-wrap");
    moveToBody("asm-favourites-modal");
    moveToBody("asm-compare-modal");
  }

  function updateModalNav(){
    const prevBtn = qs("asm-modal-prev-animal");
    const nextBtn = qs("asm-modal-next-animal");
    if (!prevBtn || !nextBtn) return;
    const total = Array.isArray(filteredCats) && filteredCats.length ? filteredCats.length : (Array.isArray(allCats) ? allCats.length : 0);
    const hasPrev = currentModalIndex > 0;
    const hasNext = currentModalIndex >= 0 && currentModalIndex < total - 1;
    prevBtn.classList.toggle("hidden", !hasPrev);
    nextBtn.classList.toggle("hidden", !hasNext);
    prevBtn.disabled = !hasPrev;
    nextBtn.disabled = !hasNext;
  }

  function openAdjacentModal(direction){
    const nextIndex = currentModalIndex + direction;
    const source = Array.isArray(filteredCats) ? filteredCats : (Array.isArray(allCats) ? allCats : []);
    if (nextIndex < 0 || nextIndex >= source.length) return;
    const target = source[nextIndex];
    if (target) openModal(target);
  }


  function applyBuilderOrders(){
    const header = qs('asm-modal-header-actions');
    if (header) {
      const groups = {
        apply: [qs('asm-apply-toggle-top'), qs('asm-apply-back-top')],
        favourite: [qs('asm-modal-favourite')],
        share: [qs('asm-modal-share')],
        close: [qs('asm-modal-close')]
      };
      const order = Array.isArray(ASM_WIDGET.builderHeaderActions) && ASM_WIDGET.builderHeaderActions.length ? ASM_WIDGET.builderHeaderActions : ['apply','favourite','share','close'];
      const used = new Set();
      order.concat(['apply','favourite','share','close']).forEach(key => {
        if (used.has(key) || !groups[key]) return;
        used.add(key);
        groups[key].forEach(el => { if (el) header.appendChild(el); });
      });
    }

    if (!Array.isArray(ASM_WIDGET.builderModalOrder) || !ASM_WIDGET.builderModalOrder.length) return;

    const infoCol = qs('asm-modal-info-col');
    const descBox = qs('asm-modal-desc-col')?.querySelector('[data-layout-role="description"]');
    const infoRoles = new Set(['badges','info_cards','compatibility_cards','tips','scroll_hint']);
    const descRoles = new Set(['global_text','contact_footer','custom_buttons']);

    ASM_WIDGET.builderModalOrder.forEach(key => {
      const selector = `[data-layout-role="${key}"]`;
      const el = document.querySelector(selector);
      if (!el) return;

      if (infoCol && infoRoles.has(key)) {
        infoCol.appendChild(el);
        return;
      }

      if (descBox && descRoles.has(key)) {
        descBox.appendChild(el);
      }
    });

    if (infoCol) {
      ['badges','info_cards','compatibility_cards','tips','scroll_hint'].forEach(key => {
        const el = infoCol.querySelector(`[data-layout-role="${key}"]`);
        if (el) infoCol.appendChild(el);
      });
    }
  }

  function updateSeoForAnimals(list){
    if (!ROOT || !Array.isArray(list) || !list.length) return;
    let script = ROOT.querySelector('script[type="application/ld+json"]');
    if (!script) { script = document.createElement('script'); script.type = 'application/ld+json'; ROOT.appendChild(script); }
    const imageBase = ASM_WIDGET.imageProxyBase;
    const data = {"@context":"https://schema.org","@type":"ItemList","itemListElement": list.slice(0,12).map((a, idx) => ({"@type":"ListItem","position": idx + 1, "item": {"@type":"AnimalShelter","name": safeText(a.ANIMALNAME ?? a.AnimalName), "description": safeText(a.ANIMALCOMMENTS ?? a.DESCRIPTION ?? a.ANIMALDESCRIPTION), "image": `${imageBase}?animalid=${encodeURIComponent(String(getCatId(a)))}&seq=1`}}))};
    script.textContent = JSON.stringify(data);
  }

  function openModal(a){
    activeModalAnimal = a || null;
    ensureModalInBody();

    const modal = qs("asm-modal");
    const nameEl = qs("asm-modal-name");
    const metaEl = qs("asm-modal-meta");

    const codeEl = qs("asm-field-code");
    const ageEl  = qs("asm-field-age");
    const sexEl  = qs("asm-field-sex");
    const breedEl= qs("asm-field-breed");
    const descEl = qs("asm-field-desc");

    const badgeReserved = qs("asm-badge-reserved");
    const badgeTime = qs("asm-badge-time");

    const mainImg = qs("asm-modal-mainimg");
    const thumbs = qs("asm-modal-thumbs");

    const scrollEl = qs("asm-modal-scroll");
    if (scrollEl) {
      scrollEl.scrollTop = 0;
      requestAnimationFrame(() => { scrollEl.scrollTop = 0; });
    }

    const id = getCatId(a);
    const source = Array.isArray(filteredCats) ? filteredCats : (Array.isArray(allCats) ? allCats : []);
    currentModalIndex = source.findIndex(x => String(getCatId(x)) === String(id));
    updateModalNav();
    applyBuilderOrders();
    const name = safeText(a.ANIMALNAME ?? a.AnimalName);
    const code = safeText(a.CODE ?? a.ShelterCode ?? a.SHELTERCODE);
    const age  = safeText(a.ANIMALAGE ?? a.AnimalAge);
    const sex  = safeText(a.SEXNAME ?? a.SexName ?? a.SEX);
    const breed= safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1 ?? a.BreedName1);
    const descSource = descText(a);
    const desc = safeText(descSource, ASM_WIDGET.fallbackDescription || "No description yet.");

    const reservation = getReservationInfo(a);

    const d = daysOnShelter(a);
    const timeText = d === 1 ? "1 day in our care" : `${d} days in our care`;

    nameEl.textContent = name;
    syncModalUrl(a);
    metaEl.textContent = code;
    codeEl.textContent = code;
    ageEl.textContent = age;
    sexEl.textContent = sex;
    breedEl.textContent = breed;
    descEl.textContent = desc;

    badgeTime.textContent = timeText;
    badgeReserved.textContent = reservation.label || "";
    badgeReserved.classList.toggle("hidden", !reservation.show);
    trackEvent("adoptables_modal_open");
    const bondedBadge = qs("asm-badge-bonded");
    const indoorBadge = qs("asm-badge-indoor");
    if (bondedBadge) bondedBadge.classList.toggle("hidden", !hasBondedLabel(a));
    if (indoorBadge) indoorBadge.classList.toggle("hidden", !hasIndoorOnlyLabel(a));
    const goodCatsEl = qs('asm-field-good-cats');
    const goodDogsEl = qs('asm-field-good-dogs');
    const goodChildrenEl = qs('asm-field-good-children');
    const specialNeedsEl = qs('asm-field-special-needs');
    if (goodCatsEl) goodCatsEl.textContent = goodWithCatsValue(a);
    if (goodDogsEl) goodDogsEl.textContent = goodWithDogsValue(a);
    if (goodChildrenEl) goodChildrenEl.textContent = goodWithChildrenValue(a);
    if (specialNeedsEl) specialNeedsEl.textContent = specialNeedsValue(a);

	    const prevPhoto = qs("asm-modal-photo-prev");
	    const nextPhoto = qs("asm-modal-photo-next");
		    const count = imageCount(a);
		    let urls = [];
		    const failedPhotoUrls = new Set();
		    const photoFailureCounts = new Map();
		    for(let i=1;i<=count;i++){
		      urls.push(proxyImageUrl(id, i));
		    }
		    urls.slice(0, 4).forEach((url) => {
		      const preload = new Image();
		      preload.decoding = "async";
		      preload.src = url;
		    });

		    let currentPhotoIndex = 0;
		    let photoRequestToken = 0;
	    function updatePhotoButtons(){
	      const showControls = ASM_WIDGET.enableSlideshowControls && urls.length > 1;
	      if (prevPhoto) prevPhoto.classList.toggle("hidden", !showControls);
	      if (nextPhoto) nextPhoto.classList.toggle("hidden", !showControls);
	      thumbs?.querySelectorAll('button[data-idx]').forEach((button) => button.setAttribute('aria-current', Number(button.getAttribute('data-idx')) === currentPhotoIndex ? 'true' : 'false'));
	    }
	    function removePhotoUrl(url){
	      if (!url || failedPhotoUrls.has(url)) return;
	      failedPhotoUrls.add(url);
	      const failedIndex = urls.indexOf(url);
	      urls = urls.filter(item => item !== url);
	      if (failedIndex >= 0 && currentPhotoIndex > failedIndex) currentPhotoIndex -= 1;
		      renderThumbs();
		      setMain(Math.min(currentPhotoIndex, urls.length - 1));
		    }
		    function notePhotoLoadFailure(url, imgEl){
		      if (!url || failedPhotoUrls.has(url)) return;
		      const attempts = (photoFailureCounts.get(url) || 0) + 1;
		      photoFailureCounts.set(url, attempts);
		      if (attempts < 2) {
		        window.setTimeout(() => {
		          const retry = new Image();
		          retry.decoding = "async";
		          retry.onload = () => {
		            photoFailureCounts.delete(url);
		            if (imgEl && imgEl.isConnected && imgEl.getAttribute('src') === url) {
		              imgEl.setAttribute('src', url);
		            }
		          };
		          retry.onerror = () => removePhotoUrl(url);
		          retry.src = url;
		        }, 180);
		        return;
		      }
		      removePhotoUrl(url);
		    }
		    function renderThumbs(){
		      if (!thumbs) return;
	      thumbs.innerHTML = urls.map((u, idx) => `
	        <button type="button"
	          class="shrink-0 w-16 h-16 rounded-xl overflow-hidden border-2 bg-gray-50 hover:shadow"
	          style="border-color:${ASM_WIDGET.brandColor};"
	          data-idx="${idx}"
	          aria-label="Show ${escAttr(name)} photo ${idx+1}">
          <img src="${escAttr(u)}" alt="${escAttr(name)} photo ${idx+1}" class="w-full h-full object-cover" loading="eager" decoding="async" fetchpriority="auto" />
	        </button>
	      `).join("");
		      thumbs.querySelectorAll("button[data-idx]").forEach(b => {
		        b.addEventListener("click", () => setMain(Number(b.getAttribute("data-idx"))));
		        const thumbImg = b.querySelector('img');
		        if (thumbImg) thumbImg.onerror = () => notePhotoLoadFailure(thumbImg.getAttribute('src'), thumbImg);
		      });
		      updatePhotoButtons();
		    }
	    function setMain(i){
	      if (!mainImg) return;
	      if (!urls.length) {
	        mainImg.removeAttribute('src');
	        mainImg.alt = `${name} photo not available`;
	        updatePhotoButtons();
	        return;
		      }
		      currentPhotoIndex = Math.max(0, Math.min(i, urls.length - 1));
		      const url = urls[currentPhotoIndex];
		      mainImg.setAttribute('loading', 'eager');
		      mainImg.setAttribute('decoding', 'sync');
		      mainImg.setAttribute('fetchpriority', 'high');
		      mainImg.alt = `${name} photo ${currentPhotoIndex+1}`;
		      updatePhotoButtons();
		      const requestToken = ++photoRequestToken;
		      const commit = () => {
		        if (requestToken !== photoRequestToken) return;
		        mainImg.onerror = () => notePhotoLoadFailure(url, mainImg);
		        if (mainImg.getAttribute('src') !== url) mainImg.src = url;
		      };
		      const preloader = new Image();
		      preloader.decoding = "async";
		      preloader.onload = () => {
		        photoFailureCounts.delete(url);
		        commit();
		      };
		      preloader.onerror = () => {
		        if (requestToken === photoRequestToken) notePhotoLoadFailure(url, mainImg);
		      };
		      preloader.src = url;
		      if (preloader.complete && preloader.naturalWidth > 0) commit();
		    }
	    renderThumbs();
	    setMain(0);
	    if (prevPhoto) prevPhoto.onclick = () => { if (urls.length) setMain((currentPhotoIndex - 1 + urls.length) % urls.length); };
	    if (nextPhoto) nextPhoto.onclick = () => { if (urls.length) setMain((currentPhotoIndex + 1) % urls.length); };
    let startX = null;
    if (mainImg) {
      mainImg.ontouchstart = (e) => { startX = e.touches && e.touches[0] ? e.touches[0].clientX : null; };
      mainImg.ontouchend = (e) => {
        const endX = e.changedTouches && e.changedTouches[0] ? e.changedTouches[0].clientX : null;
        if (startX === null || endX === null || urls.length < 2) return;
        const delta = endX - startX;
        if (Math.abs(delta) < 35) return;
        if (delta > 0) setMain((currentPhotoIndex - 1 + urls.length) % urls.length); else setMain((currentPhotoIndex + 1) % urls.length);
      };
    }

    const mediaCol = qs("asm-modal-media-col");
    const infoCol = qs("asm-modal-info-col");
    const descCol = qs("asm-modal-desc-col");
    const formCol = qs("asm-modal-form-col");
    const applyToggle = qs("asm-apply-toggle-top");
    const applyBackTop = qs("asm-apply-back-top");

    let movedApplyForm = null;
    let movedApplyFormPlaceholder = null;
    let applyFormInitialising = false;

    function restoreMovedApplyForm(){
      if (movedApplyForm && movedApplyFormPlaceholder && movedApplyFormPlaceholder.parentNode) {
        movedApplyFormPlaceholder.parentNode.insertBefore(movedApplyForm, movedApplyFormPlaceholder);
        movedApplyFormPlaceholder.remove();
      }
      movedApplyForm = null;
      movedApplyFormPlaceholder = null;
    }
    cleanupCurrentModal = restoreMovedApplyForm;

    function findExistingApplyForm(){
      const all = Array.from(document.querySelectorAll('.plugin-form-wrap'));
      return all.find(wrap => !formCol.contains(wrap) && wrap.querySelector('#asm3-onlineform')) || null;
    }

    function ensureApplyForm(){
      const mount = qs('asm-apply-form-mount');
      const status = qs('asm-apply-form-status');
      if (!mount || !ASM_WIDGET.applyFormEnabled) return;
      if (mount.querySelector('#asm3-onlineform') || mount.querySelector('.plugin-form-wrap')) return;

      const existing = findExistingApplyForm();
      if (existing) {
        movedApplyFormPlaceholder = document.createComment('plugin-application-form-placeholder');
        existing.parentNode.insertBefore(movedApplyFormPlaceholder, existing);
        movedApplyForm = existing;
        mount.appendChild(existing);
        if (status) status.classList.add('hidden');
        return;
      }

      if (!ASM_WIDGET.applyFormScriptUrl || applyFormInitialising) {
        if (status) {
          status.textContent = 'The application form configured in Plugin Suite settings could not be loaded.';
          status.style.color = '#b91c1c';
          status.classList.remove('hidden');
        }
        return;
      }

      applyFormInitialising = true;
      if (status) {
        status.textContent = 'Loading application form…';
        status.style.color = '#64748b';
        status.classList.remove('hidden');
      }
	      const wrap = document.createElement('section');
	      wrap.className = 'plugin-form-wrap';
	      wrap.setAttribute('aria-label', 'Online application form');
	      const frame = document.createElement('iframe');
	      frame.title = 'Online application form';
	      frame.style.cssText = 'display:block;width:100%;min-height:860px;border:0;background:#fff;';
		      frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
	      const frameDoc = (scriptUrl) => {
	        const src = escAttr(scriptUrl);
	        const scriptTag = '<scr' + 'ipt type="text/javascript" src="' + src + '"></scr' + 'ipt>';
	        return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_top"><style>html,body{margin:0;padding:0;background:#fff;color:#334155;font-family:Arial,sans-serif;}body{min-height:760px;}input,select,textarea,button{font:inherit;max-width:100%;}img,iframe{max-width:100%;} .rescue-suite-form-wrap{padding:0;}.rescue-suite-form-wrap .asm3-onlineform-summary,.rescue-suite-form-wrap .onlineform-summary{display:none!important;}</style></head><body><section class="rescue-suite-form-wrap" aria-label="Online application form"><noscript><p>This application form requires JavaScript. Please contact the rescue if you need help applying.</p></noscript><div id="asm3-onlineform" aria-live="polite"></div>${scriptTag}</section></body></html>`;
	      };
	      wrap.appendChild(frame);
	      mount.appendChild(wrap);
	      const hasRenderedForm = () => {
	        try {
	          const doc = frame.contentDocument || frame.contentWindow?.document;
	          const target = doc ? doc.getElementById('asm3-onlineform') : null;
	          if (!target) return false;
	          const height = Math.max(doc.body?.scrollHeight || 0, doc.documentElement?.scrollHeight || 0);
	          if (height > 200) frame.style.minHeight = `${Math.min(Math.max(height + 20, 860), 1800)}px`;
	          return !!(target.querySelector('form, iframe, input, textarea, select, button') || target.children.length || target.textContent.trim());
	        } catch (e) {
	          return false;
	        }
	      };
	      const showFormLoadError = () => {
	        applyFormInitialising = false;
	        if (status) {
	          status.textContent = 'The application form loaded but did not render. Please refresh the page or use the application form below the animal listings.';
          status.style.color = '#b91c1c';
          status.classList.remove('hidden');
        }
      };
	      let formWaitTimer = null;
	      const waitForRenderedForm = () => {
	        if (formWaitTimer) return;
	        let tries = 0;
	        formWaitTimer = window.setInterval(() => {
	          if (hasRenderedForm()) {
	            applyFormInitialising = false;
	            if (status) status.classList.add('hidden');
	            window.clearInterval(formWaitTimer);
	            formWaitTimer = null;
	            return;
	          }
	          tries += 1;
		          if (tries >= 44) {
		            window.clearInterval(formWaitTimer);
		            formWaitTimer = null;
		            showFormLoadError();
		          }
		        }, 250);
		      };
		      frame.addEventListener('load', waitForRenderedForm, { once: true });
		      frame.srcdoc = frameDoc(ASM_WIDGET.applyFormScriptUrl);
		      window.setTimeout(waitForRenderedForm, 100);
	    }

    function showDetailsView(){
      if (mediaCol) mediaCol.classList.remove("hidden");
      if (infoCol) infoCol.classList.remove("hidden");
      if (descCol) descCol.classList.remove("hidden");
      if (formCol) formCol.classList.add("hidden");
      if (applyToggle) applyToggle.classList.remove("hidden");
      if (applyBackTop) applyBackTop.classList.add("hidden");
      restoreMovedApplyForm();
      if (scrollEl) {
        scrollEl.scrollTop = 0;
        requestAnimationFrame(() => { scrollEl.scrollTop = 0; });
      }
    }

    function showFormView(){
      ensureApplyForm();
      if (mediaCol) mediaCol.classList.add("hidden");
      if (infoCol) infoCol.classList.add("hidden");
      if (descCol) descCol.classList.add("hidden");
      if (formCol) formCol.classList.remove("hidden");
      if (applyToggle) applyToggle.classList.add("hidden");
      if (applyBackTop) applyBackTop.classList.remove("hidden");
      if (scrollEl) {
        scrollEl.scrollTop = 0;
        requestAnimationFrame(() => { scrollEl.scrollTop = 0; });
      }
    }

    const formWrap = qs("asm-apply-form-wrap");
    if (formWrap && !formWrap.dataset.successScrollBound) {
      formWrap.dataset.successScrollBound = "1";

      const successPattern = /(thank you|successfully submitted|submitted successfully|submission successful|application (?:has been )?(?:received|submitted|sent)|form (?:has been )?(?:submitted|sent)|we have received|your response has been recorded)/i;
      let submissionArmedUntil = 0;
      let knownControlCount = 0;
      let iframeLoads = new WeakMap();

      const formViewIsOpen = () => !!formCol && !formCol.classList.contains("hidden");

      const forceModalFormTop = (smooth = false) => {
        if (!formViewIsOpen()) return;
        const behaviour = smooth ? "smooth" : "auto";
        const mount = qs("asm-apply-form-mount");
        const panel = qs("asm-modal-panel");
        const modal = qs("asm-modal");
        const candidates = [scrollEl, panel, formCol, formWrap, mount];

        let node = formWrap.parentElement;
        while (node && node !== document.body) {
          candidates.push(node);
          if (node === modal) break;
          node = node.parentElement;
        }

        [...new Set(candidates.filter(Boolean))].forEach((el) => {
          try { el.scrollTo({ top: 0, left: 0, behavior: behaviour }); }
          catch (e) { el.scrollTop = 0; el.scrollLeft = 0; }
          el.scrollTop = 0;
        });

        // ASM can add an inner scrolling wrapper, so reset any scrollable descendants too.
        formWrap.querySelectorAll('*').forEach((el) => {
          if (el.scrollTop > 0 || el.scrollHeight > el.clientHeight + 8) el.scrollTop = 0;
        });

        requestAnimationFrame(() => {
          if (scrollEl) scrollEl.scrollTop = 0;
          if (panel) panel.scrollTop = 0;
          requestAnimationFrame(() => {
            if (scrollEl) scrollEl.scrollTop = 0;
            if (panel) panel.scrollTop = 0;
          });
        });
      };

      const repeatTopCorrection = () => {
        [0, 120, 300, 600, 1000, 1600, 2400, 3500, 5000].forEach((delay, index) => {
          window.setTimeout(() => forceModalFormTop(index === 1), delay);
        });
      };

      const armSubmissionWatch = () => {
        if (!formViewIsOpen()) return;
        submissionArmedUntil = Date.now() + 12000;
        knownControlCount = formWrap.querySelectorAll('input,select,textarea,button').length;
        // The response can arrive quickly, but do not jump immediately before ASM has processed the click.
        [500, 1000, 1800, 3000, 5000, 8000].forEach((delay) => {
          window.setTimeout(() => {
            if (Date.now() <= submissionArmedUntil && formViewIsOpen()) {
              const text = (formWrap.innerText || formWrap.textContent || '').replace(/\s+/g, ' ').trim();
              const controls = formWrap.querySelectorAll('input,select,textarea,button').length;
              if (successPattern.test(text) || (knownControlCount > 2 && controls < Math.max(2, Math.floor(knownControlCount / 3)))) {
                repeatTopCorrection();
                submissionArmedUntil = 0;
              }
            }
          }, delay);
        });
      };

      formWrap.addEventListener("submit", armSubmissionWatch, true);
      formWrap.addEventListener("pointerdown", (event) => {
        const target = event.target && event.target.closest ? event.target.closest('button,input[type="submit"],input[type="button"],a') : null;
        if (!target) return;
        const label = String(target.textContent || target.value || target.getAttribute("aria-label") || "").trim();
        if (target.matches('[type="submit"]') || /submit|send|apply|finish|complete|next/i.test(label)) armSubmissionWatch();
      }, true);
      formWrap.addEventListener("click", (event) => {
        const target = event.target && event.target.closest ? event.target.closest('button,input[type="submit"],input[type="button"],a') : null;
        if (!target) return;
        const label = String(target.textContent || target.value || target.getAttribute("aria-label") || "").trim();
        if (target.matches('[type="submit"]') || /submit|send|apply|finish|complete/i.test(label)) armSubmissionWatch();
      }, true);

      const bindIframeLoads = () => {
        formWrap.querySelectorAll('iframe').forEach((frame) => {
          if (iframeLoads.has(frame)) return;
          iframeLoads.set(frame, 0);
          frame.addEventListener('load', () => {
            const count = (iframeLoads.get(frame) || 0) + 1;
            iframeLoads.set(frame, count);
            // A second iframe load commonly represents the submitted confirmation page.
            if (count > 1 || Date.now() <= submissionArmedUntil) repeatTopCorrection();
          });
        });
      };

      const observer = new MutationObserver(() => {
        bindIframeLoads();
        if (!formViewIsOpen()) return;
        const text = (formWrap.innerText || formWrap.textContent || "").replace(/\s+/g, " ").trim();
        const controls = formWrap.querySelectorAll('input,select,textarea,button').length;
        const successVisible = successPattern.test(text);
        const formWasReplaced = Date.now() <= submissionArmedUntil && knownControlCount > 2 && controls < Math.max(2, Math.floor(knownControlCount / 3));
        if (successVisible || formWasReplaced) {
          repeatTopCorrection();
          submissionArmedUntil = 0;
        }
      });
      observer.observe(formWrap, { childList: true, subtree: true, characterData: true, attributes: true });
      bindIframeLoads();

      // Final fallback: while the application view is open, recognise confirmation text
      // even where ASM changes content without firing a conventional submit event.
      window.setInterval(() => {
        if (!formViewIsOpen()) return;
        const text = (formWrap.innerText || formWrap.textContent || "").replace(/\s+/g, " ").trim();
        if (successPattern.test(text)) repeatTopCorrection();
      }, 500);
    }

    showDetailsView();

    if (applyToggle) {
      applyToggle.onclick = () => { trackEvent("adoptables_apply_click", animalEventContext(activeModalAnimal)); showFormView(); };
    }

    if (applyBackTop) {
      applyBackTop.onclick = () => showDetailsView();
    }

    const shareBtn = qs("asm-modal-share");
    const shareText = qs("asm-modal-share-text");
    if (shareBtn && shareText) {
      shareText.textContent = ASM_WIDGET.shareButtonText;
      shareBtn.onclick = async () => {
        const url = modalUrl(a);
        try {
          if (navigator.share && /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '')) {
            await navigator.share({ url });
          } else if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(url);
          } else {
            const input = document.createElement("input");
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            input.remove();
          }
          shareText.textContent = ASM_WIDGET.shareCopiedText;
          window.setTimeout(() => { shareText.textContent = ASM_WIDGET.shareButtonText; }, 1800);
        } catch (err) {}
      };
    }

    const modalFavouriteBtn = qs("asm-modal-favourite");
    const modalFavouriteText = qs("asm-modal-favourite-text");
    const modalFavouriteIcon = qs("asm-modal-favourite-icon");
    function syncModalFavouriteState(){
      if (!modalFavouriteBtn) return;
      const fav = isFavourite(id);
      modalFavouriteBtn.setAttribute('aria-pressed', fav ? 'true' : 'false');
      if (modalFavouriteIcon) modalFavouriteIcon.innerHTML = favouriteIconSvg(fav, 'w-4 h-4');
      if (modalFavouriteText) modalFavouriteText.textContent = fav ? 'Saved to favourites' : 'Add to favourites';
    }
    if (modalFavouriteBtn) {
      modalFavouriteBtn.classList.toggle('hidden', !ASM_WIDGET.enableFavourites);
      modalFavouriteBtn.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleFavourite(id);
        syncModalFavouriteState();
      };
      syncModalFavouriteState();
    }

	    modal.classList.add("asm-modal-ready");
	    openDialog(modal, qs("asm-modal-close"));
  }

  function closeModal(){
    const modal = qs("asm-modal");
    if (typeof cleanupCurrentModal === 'function') cleanupCurrentModal();
    cleanupCurrentModal = null;
    const detailsView = qs("asm-modal-details-view");
    const formView = qs("asm-modal-form-view");
    if (formView) formView.classList.add("hidden");
    if (detailsView) detailsView.classList.remove("hidden");
    closeDialog(modal);
    currentModalIndex = -1;
    updateModalNav();
    syncModalUrl(null);
  }

  async function fetchAdoptables(){
    const res = await fetch(ASM_WIDGET.proxyBase, { method: "GET", credentials: "same-origin" });
    if(!res.ok){
      const t = await res.text().catch(()=>"");
      throw new Error(`Proxy request failed (${res.status}). ${t}`.trim());
    }
    const data = await res.json();
    if(!Array.isArray(data)) throw new Error("Proxy did not return an array.");
    return data;
  }

  async function initAdoptables(){
    showStatus(LOADING_STATUS_TEXT);
    ensureModalInBody();

    qs("asm-prev")?.addEventListener("click", () => { pageIndex = Math.max(0, pageIndex - 1); renderPage(); });
    qs("asm-next")?.addEventListener("click", () => { pageIndex = pageIndex + 1; renderPage(); });
    qs("asm-prev-bottom")?.addEventListener("click", () => { pageIndex = Math.max(0, pageIndex - 1); renderPage(); });
    qs("asm-next-bottom")?.addEventListener("click", () => { pageIndex = pageIndex + 1; renderPage(); });

    qs("asm-filters-panel")?.addEventListener("click", (e) => e.stopPropagation());
    qs("asm-open-filters")?.addEventListener("click", openFilters);
    qs("asm-close-filters")?.addEventListener("click", closeFilters);
    qs("asm-filters-backdrop")?.addEventListener("click", closeFilters);
    qs("asm-apply-filters")?.addEventListener("click", () => { applyFilters(); closeFilters(); });
    qs("asm-reset-filters")?.addEventListener("click", resetFilters);
    qs("asm-close-favourites-modal")?.addEventListener("click", closeFavouritesModal);
    qs("asm-favourites-backdrop")?.addEventListener("click", closeFavouritesModal);
    qs("asm-close-compare-modal")?.addEventListener("click", closeCompareModal);
    qs("asm-compare-backdrop")?.addEventListener("click", closeCompareModal);

    qs("asm-modal-close").addEventListener("click", closeModal);
    qs("asm-modal-backdrop").addEventListener("click", closeModal);
    qs("asm-modal-prev-animal").addEventListener("click", (e) => { e.preventDefault(); e.stopPropagation(); openAdjacentModal(-1); });
    qs("asm-modal-next-animal").addEventListener("click", (e) => { e.preventDefault(); e.stopPropagation(); openAdjacentModal(1); });

    qs("asm-modal-panel").addEventListener("click", (e) => e.stopPropagation());
    qs("asm-modal").addEventListener("click", (e) => {
      const panel = qs("asm-modal-panel");
      if (!panel.contains(e.target)) closeModal();
    });

    document.addEventListener("keydown", (e) => {
      const modal = qs("asm-modal");
      const isOpen = modal && !modal.classList.contains("hidden");
      const activeDialog = topDialog();
      if (activeDialog && e.key === "Tab") trapDialogFocus(e, activeDialog);
      if(e.key === "Escape") {
        if (closeTopDialog()) e.preventDefault();
        return;
      }
      if(!isOpen || activeDialog !== modal) return;
      if(e.key === "ArrowLeft") openAdjacentModal(-1);
      if(e.key === "ArrowRight") openAdjacentModal(1);
    });

    window.addEventListener("resize", () => {
      resetStableHeight();
      renderPage();
      requestAnimationFrame(() => updateStableHeight());
    }, { passive: true });

    try {
      const animals = await fetchAdoptables();
      let cats = ASM_WIDGET.catsOnly ? animals.filter(isCat) : animals;
      cats.sort((a,b) => daysOnShelter(b) - daysOnShelter(a));

      rawCats = cats;
      allCats = rawCats.slice();
      filteredCats = rawCats.slice();
      setupFilterUi(rawCats);
      showStatus(allCats.length ? "" : "No cats available.", !allCats.length);
      pageIndex = 0;

      applyFilters();

	      let initialDeepLinkOpened = false;
	      const openRequestedCat = () => {
	        const wanted = requestedCatId();
	        if (!wanted) return false;
	        const target = allCats.find(x => String(getCatId(x)).toLowerCase() === wanted || catShareSlug(x) === wanted);
	        if (target) {
	          openModal(target);
	          return true;
	        }
	        return false;
	      };

	      window.addEventListener("popstate", () => {
	        const wantedId = requestedCatId();
	        if (!wantedId) {
	          closeModal();
	          return;
	        }
	        openRequestedCat();
	      });

	      const reveal = () => {
	        requestAnimationFrame(() => {
	          requestAnimationFrame(() => {
	            ROOT.classList.add("asm-ready");
	            if (!initialDeepLinkOpened) {
	              initialDeepLinkOpened = true;
	              openRequestedCat();
	            }
	          });
	        });
	      };

      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(reveal).catch(reveal);
      } else {
        reveal();
      }
    } catch (err){
      console.error(err);
      showStatus(
        "Could not load adoptables from the proxy endpoint. Check that the Rescue Plugin Suite data proxy is active and /wp-json/plugin/v1/adoptables returns data.",
        true
      );
      qs("asm-page-label").textContent = "Load failed.";
      qs("asm-page-label-bottom").textContent = "Load failed.";
      ROOT.classList.add("asm-ready");
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAdoptables);
  } else {
    initAdoptables();
  }
})();
</script>
    <?php
    return ob_get_clean();
  }
}

Plugin_Adoptables_UI_Shortcode::init();
