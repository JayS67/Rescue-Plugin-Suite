<?php
/**
 * Plugin Name: ASM Plugin Suite Adopted
 * Description: Provides [adopted] shortcode for the adopted animals widget with scoped styling and settings.
 * Version: 1.1.14
 * Author: Jordan Sutton
 */

if (!defined('ABSPATH')) exit;

final class StraySafe_Adopted_UI_Shortcode {
  const SHORTCODE        = 'adopted';
  const TAILWIND_HANDLE  = 'straysafe-adopted-tailwind';
  const INTER_HANDLE     = 'straysafe-adopted-inter';

  const OPT_KEY = 'straysafe_adopted_ui_options';
  const RESET_ACTION = 'straysafe_adopted_ui_reset_field';

  public static function init() {
    add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'conditionally_enqueue_assets'], 20);

    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'admin_init']);
    add_action('admin_post_' . self::RESET_ACTION, [__CLASS__, 'handle_reset']);
  }

  /* -------------------------
   * Defaults / options
   * ------------------------- */
  public static function default_options() {
    return [
      // Core look
      'brand_color'      => '#ff647e',
      'background_color' => '#f9d6dd',

      // Text colours
      'text_primary_color' => '#334155',
      'text_muted_color'   => '#64748b',

      // Paw prints (same settings as Adoptables UI)
      'paw_opacity' => 0.08,  // peak opacity at 50% of animation
      'paw_count'   => 10,    // how many pawprints to show (max 80)

      // Text
      'title_text'    => 'Found My Forever Home',
      'subtitle_text' => 'Recently adopted animals, with the newest happy endings first',
      'footer_text'   => 'Want to be part of the next happy ending? Consider adopting or fostering. 🐾',

      // Font
      'font_family' => 'Inter',

      // Minimum year in dropdown
      'min_year' => 2025,
      'show_top_navigation' => 1,

      // Grid (per device)
      'cols_mobile'  => 2,
      'rows_mobile'  => 3,
      'cols_tablet'  => 3,
      'rows_tablet'  => 3,
      'cols_desktop' => 3,
      'rows_desktop' => 3,

      // Card size %
      'card_scale_mobile'  => 100,
      'card_scale_tablet'  => 100,
      'card_scale_desktop' => 100,

      // Card cosmetics
      'card_radius'  => 16, // px
      'card_padding' => 12, // px (bottom section padding)

      // Buttons cosmetics
      'button_radius' => 16, // px

      // Typography (device-specific)
      'fs_heading_mobile'   => 30,
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

      'fs_card_name_mobile'  => 13,
      'fs_card_name_tablet'  => 16,
      'fs_card_name_desktop' => 18,

      'fs_card_meta_mobile'  => 11,
      'fs_card_meta_tablet'  => 14,
      'fs_card_meta_desktop' => 14,

      'fs_badge_mobile'  => 10,
      'fs_badge_tablet'  => 12,
      'fs_badge_desktop' => 16,

      // Weights
      'fw_heading'    => 800,
      'fw_subheading' => 600,
      'fw_footer'     => 500,
      'fw_page_label' => 600,

      'fw_card_name'  => 800,
      'fw_card_meta'  => 600,
      'fw_badge'      => 800,

      'card_border_enabled' => 1,
      'card_border_color'   => '#401268',
      'card_border_weight'  => 2,
      'date_label_halign'   => 'right',
      'date_label_valign'   => 'top',

      'enable_modals'       => 1,
      'modal_max_width'     => 896,
      'enable_deep_links'   => 1,
      'share_button_text'   => 'Share',
      'share_copied_text'   => 'Link copied',
      'modal_global_text'   => '',
      'adoptables_cta_enabled' => 0,
      'adoptables_cta_text' => 'Looking for your next best friend?',
      'adoptables_cta_button_text' => 'View animals for adoption',
      'adoptables_cta_url' => '',
    ];
  }

  public static function get_options() {
    $d = self::default_options();
    $saved = get_option(self::OPT_KEY, []);
    if (!is_array($saved)) $saved = [];
    return array_merge($d, $saved);
  }

  public static function sanitize_options($input) {
    $d = self::default_options();
    $out = [];

    $out['brand_color']      = isset($input['brand_color']) ? sanitize_hex_color($input['brand_color']) : $d['brand_color'];
    $out['background_color'] = isset($input['background_color']) ? sanitize_hex_color($input['background_color']) : $d['background_color'];

    $out['text_primary_color'] = isset($input['text_primary_color']) ? sanitize_hex_color($input['text_primary_color']) : $d['text_primary_color'];
    $out['text_muted_color']   = isset($input['text_muted_color']) ? sanitize_hex_color($input['text_muted_color']) : $d['text_muted_color'];

    $po = isset($input['paw_opacity']) ? (float)$input['paw_opacity'] : (float)$d['paw_opacity'];
    if (!is_finite($po)) $po = (float)$d['paw_opacity'];
    $out['paw_opacity'] = max(0.0, min(0.25, $po));

    $pc = isset($input['paw_count']) ? intval($input['paw_count']) : intval($d['paw_count']);
    $out['paw_count'] = max(0, min(80, $pc));

    $out['title_text']    = isset($input['title_text']) ? sanitize_text_field($input['title_text']) : $d['title_text'];
    $out['subtitle_text'] = isset($input['subtitle_text']) ? sanitize_text_field($input['subtitle_text']) : $d['subtitle_text'];
    $out['footer_text']   = isset($input['footer_text']) ? sanitize_textarea_field($input['footer_text']) : $d['footer_text'];

    $font = isset($input['font_family']) ? sanitize_text_field($input['font_family']) : $d['font_family'];
    $font = trim($font);
    if ($font !== '') $font = preg_replace('/[^a-zA-Z0-9 ,\-]/', '', $font);
    $out['font_family'] = $font;

    $miny = isset($input['min_year']) ? intval($input['min_year']) : intval($d['min_year']);
    $out['min_year'] = max(2000, min((int)date('Y'), $miny));
    $out['show_top_navigation'] = isset($input['show_top_navigation']) ? (int)!!$input['show_top_navigation'] : (int)$d['show_top_navigation'];

    foreach ([
      'cols_mobile','rows_mobile',
      'cols_tablet','rows_tablet',
      'cols_desktop','rows_desktop',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(1, min(6, $v));
    }

    foreach (['card_scale_mobile','card_scale_tablet','card_scale_desktop'] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(50, min(200, $v));
    }

    $cr = isset($input['card_radius']) ? intval($input['card_radius']) : intval($d['card_radius']);
    $out['card_radius'] = max(0, min(48, $cr));

    $cp = isset($input['card_padding']) ? intval($input['card_padding']) : intval($d['card_padding']);
    $out['card_padding'] = max(0, min(48, $cp));

    $br = isset($input['button_radius']) ? intval($input['button_radius']) : intval($d['button_radius']);
    $out['button_radius'] = max(0, min(48, $br));
    $out['card_border_enabled'] = isset($input['card_border_enabled']) ? (int)!!$input['card_border_enabled'] : (int)$d['card_border_enabled'];
    $out['card_border_color'] = isset($input['card_border_color']) ? sanitize_hex_color($input['card_border_color']) : $d['card_border_color'];
    $cbw = isset($input['card_border_weight']) ? intval($input['card_border_weight']) : intval($d['card_border_weight']);
    $out['card_border_weight'] = max(0, min(20, $cbw));
    $allowed_h = ['left','right'];
    $halign = isset($input['date_label_halign']) ? sanitize_text_field($input['date_label_halign']) : $d['date_label_halign'];
    $out['date_label_halign'] = in_array($halign, $allowed_h, true) ? $halign : $d['date_label_halign'];
    $allowed_v = ['top','bottom'];
    $valign = isset($input['date_label_valign']) ? sanitize_text_field($input['date_label_valign']) : $d['date_label_valign'];
    $out['date_label_valign'] = in_array($valign, $allowed_v, true) ? $valign : $d['date_label_valign'];

    $out['enable_modals'] = isset($input['enable_modals']) ? (int)!!$input['enable_modals'] : (int)$d['enable_modals'];
    $mmw = isset($input['modal_max_width']) ? intval($input['modal_max_width']) : intval($d['modal_max_width']);
    $out['modal_max_width'] = max(320, min(1400, $mmw));
    $out['enable_deep_links'] = isset($input['enable_deep_links']) ? (int)!!$input['enable_deep_links'] : (int)$d['enable_deep_links'];
    $out['share_button_text'] = isset($input['share_button_text']) ? sanitize_text_field($input['share_button_text']) : $d['share_button_text'];
    $out['share_copied_text'] = isset($input['share_copied_text']) ? sanitize_text_field($input['share_copied_text']) : $d['share_copied_text'];
    $out['modal_global_text'] = isset($input['modal_global_text']) ? sanitize_textarea_field($input['modal_global_text']) : $d['modal_global_text'];
    $out['adoptables_cta_enabled'] = isset($input['adoptables_cta_enabled']) ? (int)!!$input['adoptables_cta_enabled'] : (int)$d['adoptables_cta_enabled'];
    $out['adoptables_cta_text'] = isset($input['adoptables_cta_text']) ? sanitize_text_field($input['adoptables_cta_text']) : $d['adoptables_cta_text'];
    $out['adoptables_cta_button_text'] = isset($input['adoptables_cta_button_text']) ? sanitize_text_field($input['adoptables_cta_button_text']) : $d['adoptables_cta_button_text'];
    $out['adoptables_cta_url'] = isset($input['adoptables_cta_url']) ? esc_url_raw($input['adoptables_cta_url']) : $d['adoptables_cta_url'];

    foreach ([
      'fs_heading_mobile','fs_heading_tablet','fs_heading_desktop',
      'fs_subheading_mobile','fs_subheading_tablet','fs_subheading_desktop',
      'fs_footer_mobile','fs_footer_tablet','fs_footer_desktop',
      'fs_page_label_mobile','fs_page_label_tablet','fs_page_label_desktop',
      'fs_card_name_mobile','fs_card_name_tablet','fs_card_name_desktop',
      'fs_card_meta_mobile','fs_card_meta_tablet','fs_card_meta_desktop',
      'fs_badge_mobile','fs_badge_tablet','fs_badge_desktop',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(10, min(90, $v));
    }

    foreach ([
      'fw_heading','fw_subheading','fw_footer','fw_page_label',
      'fw_card_name','fw_card_meta','fw_badge',
    ] as $k) {
      $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
      $out[$k] = max(100, min(900, $v));
    }

    return $out;
  }

  /* -------------------------
   * Reset (per-field)
   * ------------------------- */
  public static function handle_reset() {
    if (!current_user_can('manage_options')) wp_die('Not allowed.');
    check_admin_referer('straysafe_adopted_ui_reset_field');

    $field = isset($_GET['field']) ? sanitize_text_field($_GET['field']) : '';
    $defaults = self::default_options();
    if (!array_key_exists($field, $defaults)) {
      wp_safe_redirect(admin_url('options-general.php?page=straysafe-adopted-ui'));
      exit;
    }

    $opts = get_option(self::OPT_KEY, []);
    if (!is_array($opts)) $opts = [];
    $opts[$field] = $defaults[$field];
    update_option(self::OPT_KEY, $opts);

    wp_safe_redirect(admin_url('options-general.php?page=straysafe-adopted-ui'));
    exit;
  }

  private static function reset_button($field_key, $label = 'Reset') {
    $url = add_query_arg(
      [
        'action'   => self::RESET_ACTION,
        'field'    => $field_key,
        '_wpnonce' => wp_create_nonce('straysafe_adopted_ui_reset_field'),
      ],
      admin_url('admin-post.php')
    );
    printf(
      ' <a href="%s" class="button button-secondary" style="vertical-align:middle;">%s</a>',
      esc_url($url),
      esc_html($label)
    );
  }

  /* -------------------------
   * Admin settings UI
   * ------------------------- */
  public static function admin_menu() {
    add_options_page(
      'ASM Plugin Suite Adopted',
      'ASM Plugin Suite Adopted',
      'manage_options',
      'straysafe-adopted-ui',
      [__CLASS__, 'render_settings_page']
    );
  }

  public static function admin_init() {
    register_setting('straysafe_adopted_ui_group', self::OPT_KEY, [
      'sanitize_callback' => [__CLASS__, 'sanitize_options'],
      'default' => self::default_options(),
    ]);

    add_settings_section('ss_adopted_design', 'Design', '__return_false', 'straysafe-adopted-ui');
    add_settings_section('ss_adopted_text', 'Text', '__return_false', 'straysafe-adopted-ui');
    add_settings_section('ss_adopted_responsive', 'Responsive (Device-specific)', '__return_false', 'straysafe-adopted-ui');
    add_settings_section('ss_adopted_cards', 'Cards & Buttons', '__return_false', 'straysafe-adopted-ui');
    add_settings_section('ss_adopted_typography', 'Typography (Device-specific)', '__return_false', 'straysafe-adopted-ui');
    add_settings_section('ss_adopted_data', 'Data', '__return_false', 'straysafe-adopted-ui');
    add_settings_section('ss_adopted_modal', 'Modal', '__return_false', 'straysafe-adopted-ui');

    add_settings_field('brand_color', 'Brand colour', [__CLASS__, 'field_brand_color'], 'straysafe-adopted-ui', 'ss_adopted_design');
    add_settings_field('background_color', 'Background colour', [__CLASS__, 'field_background_color'], 'straysafe-adopted-ui', 'ss_adopted_design');
    add_settings_field('text_primary_color', 'Primary text colour', [__CLASS__, 'field_text_primary_color'], 'straysafe-adopted-ui', 'ss_adopted_design');
    add_settings_field('text_muted_color', 'Muted text colour', [__CLASS__, 'field_text_muted_color'], 'straysafe-adopted-ui', 'ss_adopted_design');

    add_settings_field('paw_opacity', 'Paw print opacity (0–0.25)', [__CLASS__, 'field_paw_opacity'], 'straysafe-adopted-ui', 'ss_adopted_design');
    add_settings_field('paw_count', 'Paw print count (0–80)', [__CLASS__, 'field_paw_count'], 'straysafe-adopted-ui', 'ss_adopted_design');

    add_settings_field('font_family', 'Font family', [__CLASS__, 'field_font_family'], 'straysafe-adopted-ui', 'ss_adopted_design');

    add_settings_field('title_text', 'Title text', [__CLASS__, 'field_title_text'], 'straysafe-adopted-ui', 'ss_adopted_text');
    add_settings_field('subtitle_text', 'Subtitle text', [__CLASS__, 'field_subtitle_text'], 'straysafe-adopted-ui', 'ss_adopted_text');
    add_settings_field('footer_text', 'Footer text', [__CLASS__, 'field_footer_text'], 'straysafe-adopted-ui', 'ss_adopted_text');

    add_settings_field('responsive_grid', 'Columns & rows (mobile/tablet/PC)', [__CLASS__, 'field_responsive_grid'], 'straysafe-adopted-ui', 'ss_adopted_responsive');
    add_settings_field('responsive_card_scale', 'Card size % (keeps aspect ratio)', [__CLASS__, 'field_responsive_card_scale'], 'straysafe-adopted-ui', 'ss_adopted_responsive');

    add_settings_field('card_cosmetics', 'Card & button styling', [__CLASS__, 'field_card_cosmetics'], 'straysafe-adopted-ui', 'ss_adopted_cards');

    add_settings_field('typography', 'Font sizes & weights', [__CLASS__, 'field_typography'], 'straysafe-adopted-ui', 'ss_adopted_typography');

    add_settings_field('min_year', 'Minimum year in dropdown', [__CLASS__, 'field_min_year'], 'straysafe-adopted-ui', 'ss_adopted_data');
    add_settings_field('enable_modals', 'Enable adopted modals', [__CLASS__, 'field_enable_modals'], 'straysafe-adopted-ui', 'ss_adopted_modal');
    add_settings_field('deep_links', 'Deep links and sharing', [__CLASS__, 'field_deep_links'], 'straysafe-adopted-ui', 'ss_adopted_modal');
    add_settings_field('modal_max_width', 'Modal max width (px)', [__CLASS__, 'field_modal_max_width'], 'straysafe-adopted-ui', 'ss_adopted_modal');
    add_settings_field('modal_global_text', 'Modal text below story', [__CLASS__, 'field_modal_global_text'], 'straysafe-adopted-ui', 'ss_adopted_modal');
    add_settings_field('adoptables_cta', 'Adoptables link section', [__CLASS__, 'field_adoptables_cta'], 'straysafe-adopted-ui', 'ss_adopted_modal');
  }

  public static function render_settings_page() { ?>
    <div class="wrap">
      <h1>ASM Plugin Suite Adopted</h1>
      <form method="post" action="options.php">
        <?php
          settings_fields('straysafe_adopted_ui_group');
          do_settings_sections('straysafe-adopted-ui');
          submit_button();
        ?>
      </form>
    </div>
  <?php }

  /* ---- field renderers ---- */
  public static function field_brand_color() {
    $o = self::get_options();
    printf('<input type="color" name="%s[brand_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['brand_color']));
    self::reset_button('brand_color');
  }
  public static function field_background_color() {
    $o = self::get_options();
    printf('<input type="color" name="%s[background_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['background_color']));
    self::reset_button('background_color');
  }
  public static function field_text_primary_color() {
    $o = self::get_options();
    printf('<input type="color" name="%s[text_primary_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['text_primary_color']));
    self::reset_button('text_primary_color');
  }
  public static function field_text_muted_color() {
    $o = self::get_options();
    printf('<input type="color" name="%s[text_muted_color]" value="%s" />', esc_attr(self::OPT_KEY), esc_attr($o['text_muted_color']));
    self::reset_button('text_muted_color');
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
    echo '<p class="description">Default is Inter. Leave blank to inherit your theme font.</p>';
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

  public static function field_card_cosmetics() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);

    echo '<p class="description">These control the rounded corners and spacing on cards and navigation buttons.</p>';
    echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';

    echo '<tr><td>Card corner radius (px)</td><td>';
    printf('<input type="number" min="0" max="48" name="%s[card_radius]" value="%d" style="width:110px;" /> ', $k, intval($o['card_radius']));
    self::reset_button('card_radius');
    echo '</td></tr>';

    echo '<tr><td>Card bottom padding (px)</td><td>';
    printf('<input type="number" min="0" max="48" name="%s[card_padding]" value="%d" style="width:110px;" /> ', $k, intval($o['card_padding']));
    self::reset_button('card_padding');
    echo '</td></tr>';

    echo '<tr><td>Button corner radius (px)</td><td>';
    printf('<input type="number" min="0" max="48" name="%s[button_radius]" value="%d" style="width:110px;" /> ', $k, intval($o['button_radius']));
    self::reset_button('button_radius');
    echo '</td></tr>';

    echo '</tbody></table>';
  }

  public static function field_typography() {
    $o = self::get_options();
    $k = esc_attr(self::OPT_KEY);

    echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Text</th><th>Mobile</th><th>Tablet</th><th>PC</th><th>Weight</th></tr></thead><tbody>';

    $rows = [
      ['Heading',    'fs_heading_mobile','fs_heading_tablet','fs_heading_desktop','fw_heading'],
      ['Subheading', 'fs_subheading_mobile','fs_subheading_tablet','fs_subheading_desktop','fw_subheading'],
      ['Footer',     'fs_footer_mobile','fs_footer_tablet','fs_footer_desktop','fw_footer'],
      ['Page label', 'fs_page_label_mobile','fs_page_label_tablet','fs_page_label_desktop','fw_page_label'],
      ['Card name',  'fs_card_name_mobile','fs_card_name_tablet','fs_card_name_desktop','fw_card_name'],
      ['Card meta',  'fs_card_meta_mobile','fs_card_meta_tablet','fs_card_meta_desktop','fw_card_meta'],
      ['Badge',      'fs_badge_mobile','fs_badge_tablet','fs_badge_desktop','fw_badge'],
    ];

    foreach ($rows as [$label,$m,$t,$d,$w]) {
      echo '<tr><td>' . esc_html($label) . '</td>';

      foreach ([$m,$t,$d] as $key) {
        echo '<td>';
        printf('<input type="number" min="10" max="90" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($key), intval($o[$key]));
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

  public static function field_min_year() {
    $o = self::get_options();
    printf('<input type="number" min="2000" max="%d" name="%s[min_year]" value="%d" style="width:110px;" />', (int)date('Y'), esc_attr(self::OPT_KEY), intval($o['min_year']));
    self::reset_button('min_year');
    echo '<p class="description">Controls the earliest year shown in the dropdown (cannot be after the current year).</p>';
  }

  public static function field_enable_modals() {
    $o = self::get_options();
    printf('<label><input type="checkbox" name="%s[enable_modals]" value="1" %s /> Open adopted animals in a modal when cards are clicked</label>', esc_attr(self::OPT_KEY), checked(!empty($o['enable_modals']), true, false));
    self::reset_button('enable_modals');
  }

  public static function field_deep_links() {
    $o = self::get_options();
    $key = esc_attr(self::OPT_KEY);
    printf('<p><label><input type="checkbox" name="%s[enable_deep_links]" value="1" %s /> Enable direct adopted-modal links and sharing</label> ', $key, checked(!empty($o['enable_deep_links']), true, false));
    self::reset_button('enable_deep_links');
    echo '</p>';
    printf('<p><label>Share button text<br><input type="text" class="regular-text" name="%s[share_button_text]" value="%s" /></label> ', $key, esc_attr($o['share_button_text']));
    self::reset_button('share_button_text');
    echo '</p>';
    printf('<p><label>Copied text<br><input type="text" class="regular-text" name="%s[share_copied_text]" value="%s" /></label> ', $key, esc_attr($o['share_copied_text']));
    self::reset_button('share_copied_text');
    echo '</p><p class="description">The public URL base is set in ASM Plugin Suite → Global → Adopted UI page URL.</p>';
  }

  public static function field_modal_max_width() {
    $o = self::get_options();
    printf('<input type="number" min="320" max="1400" name="%s[modal_max_width]" value="%d" style="width:110px;" />', esc_attr(self::OPT_KEY), intval($o['modal_max_width']));
    self::reset_button('modal_max_width');
  }

  public static function field_modal_global_text() {
    $o = self::get_options();
    printf('<textarea name="%s[modal_global_text]" rows="5" class="large-text">%s</textarea>', esc_attr(self::OPT_KEY), esc_textarea($o['modal_global_text']));
    self::reset_button('modal_global_text');
    echo '<p class="description">Shown in adopted modals below the animal story section.</p>';
  }

  public static function field_adoptables_cta() {
    $o = self::get_options();
    $key = esc_attr(self::OPT_KEY);
    printf('<p><label><input type="checkbox" name="%s[adoptables_cta_enabled]" value="1" %s /> Show this section in adopted modals</label> ', $key, checked(!empty($o['adoptables_cta_enabled']), true, false));
    self::reset_button('adoptables_cta_enabled');
    echo '</p>';
    printf('<p><label>Text<br><input type="text" class="regular-text" name="%s[adoptables_cta_text]" value="%s" /></label> ', $key, esc_attr($o['adoptables_cta_text']));
    self::reset_button('adoptables_cta_text');
    echo '</p>';
    printf('<p><label>Button text<br><input type="text" class="regular-text" name="%s[adoptables_cta_button_text]" value="%s" /></label> ', $key, esc_attr($o['adoptables_cta_button_text']));
    self::reset_button('adoptables_cta_button_text');
    echo '</p>';
    printf('<p><label>Adoptables page URL<br><input type="url" class="regular-text code" name="%s[adoptables_cta_url]" value="%s" placeholder="%s" /></label> ', $key, esc_attr($o['adoptables_cta_url']), esc_attr(home_url('/adopt/')));
    self::reset_button('adoptables_cta_url');
    echo '</p>';
  }

  /* -------------------------
   * Frontend assets
   * ------------------------- */
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

    wp_enqueue_style('rescue-plugin-suite-frontend', STRAYSAFE_SUITE_URL . 'assets/css/frontend.css', [], STRAYSAFE_SUITE_VERSION);
    wp_enqueue_script('rescue-plugin-suite-shared-modal', STRAYSAFE_SUITE_URL . 'assets/js/shared-modal.js', [], STRAYSAFE_SUITE_VERSION, true);

    if (trim((string)$opts['font_family']) === 'Inter') {
      wp_enqueue_style(
        self::INTER_HANDLE,
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap',
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

  /* -------------------------
   * Shortcode render
   * ------------------------- */
  public static function render_shortcode($atts = [], $content = null) {
    self::enqueue_assets();
    $o = self::get_options();
    $suite_settings = class_exists('StraySafe_UI_Suite_Plugin') ? StraySafe_UI_Suite_Plugin::get_settings() : [];
    $global_settings = is_array($suite_settings) && isset($suite_settings['global']) && is_array($suite_settings['global']) ? $suite_settings['global'] : [];
    $adopted_page_url = esc_url_raw($global_settings['adopted_page_url'] ?? '');

    $ff = trim((string)$o['font_family']);
    $font_css = ($ff === '') ? "font-family: inherit;" : ("font-family: " . esc_html($ff) . ", sans-serif;");

    $scale_m = max(0.5, min(2.0, ((int)$o['card_scale_mobile']) / 100));
    $scale_t = max(0.5, min(2.0, ((int)$o['card_scale_tablet']) / 100));
    $scale_d = max(0.5, min(2.0, ((int)$o['card_scale_desktop']) / 100));

    $vars = [
      // Keep existing --ss-* variables (used throughout this file)
      "--ss-brand: " . esc_attr($o['brand_color']),
      "--ss-bg: " . esc_attr($o['background_color']),
      "--ss-text: " . esc_attr($o['text_primary_color']),
      "--ss-muted: " . esc_attr($o['text_muted_color']),
      "--ss-paw-opacity: " . esc_attr((string)$o['paw_opacity']),

      // Alias to match Adoptables UI paw SVG fill token (as requested)
      "--asm-brand: " . esc_attr($o['brand_color']),

      "--ss-cols-m: " . (int)$o['cols_mobile'],
      "--ss-cols-t: " . (int)$o['cols_tablet'],
      "--ss-cols-d: " . (int)$o['cols_desktop'],

      "--ss-scale-m: " . $scale_m,
      "--ss-scale-t: " . $scale_t,
      "--ss-scale-d: " . $scale_d,

      "--ss-card-radius: " . (int)$o['card_radius'] . "px",
      "--ss-card-pad: " . (int)$o['card_padding'] . "px",
      "--ss-btn-radius: " . (int)$o['button_radius'] . "px",
      "--ss-modal-maxw: " . (int)$o['modal_max_width'] . "px",
      "--ss-card-border-width: " . (!empty($o['card_border_enabled']) ? (int)$o['card_border_weight'] : 0) . "px",
      "--ss-card-border-color: " . esc_attr(!empty($o['card_border_enabled']) ? ($o['card_border_color'] ?: $o['brand_color']) : 'transparent'),

      "--ss-fs-h-m: " . (int)$o['fs_heading_mobile'] . "px",
      "--ss-fs-h-t: " . (int)$o['fs_heading_tablet'] . "px",
      "--ss-fs-h-d: " . (int)$o['fs_heading_desktop'] . "px",
      "--ss-fw-h: " . (int)$o['fw_heading'],

      "--ss-fs-sh-m: " . (int)$o['fs_subheading_mobile'] . "px",
      "--ss-fs-sh-t: " . (int)$o['fs_subheading_tablet'] . "px",
      "--ss-fs-sh-d: " . (int)$o['fs_subheading_desktop'] . "px",
      "--ss-fw-sh: " . (int)$o['fw_subheading'],

      "--ss-fs-ft-m: " . (int)$o['fs_footer_mobile'] . "px",
      "--ss-fs-ft-t: " . (int)$o['fs_footer_tablet'] . "px",
      "--ss-fs-ft-d: " . (int)$o['fs_footer_desktop'] . "px",
      "--ss-fw-ft: " . (int)$o['fw_footer'],

      "--ss-fs-pl-m: " . (int)$o['fs_page_label_mobile'] . "px",
      "--ss-fs-pl-t: " . (int)$o['fs_page_label_tablet'] . "px",
      "--ss-fs-pl-d: " . (int)$o['fs_page_label_desktop'] . "px",
      "--ss-fw-pl: " . (int)$o['fw_page_label'],

      "--ss-fs-cn-m: " . (int)$o['fs_card_name_mobile'] . "px",
      "--ss-fs-cn-t: " . (int)$o['fs_card_name_tablet'] . "px",
      "--ss-fs-cn-d: " . (int)$o['fs_card_name_desktop'] . "px",
      "--ss-fw-cn: " . (int)$o['fw_card_name'],

      "--ss-fs-cm-m: " . (int)$o['fs_card_meta_mobile'] . "px",
      "--ss-fs-cm-t: " . (int)$o['fs_card_meta_tablet'] . "px",
      "--ss-fs-cm-d: " . (int)$o['fs_card_meta_desktop'] . "px",
      "--ss-fw-cm: " . (int)$o['fw_card_meta'],

      "--ss-fs-bd-m: " . (int)$o['fs_badge_mobile'] . "px",
      "--ss-fs-bd-t: " . (int)$o['fs_badge_tablet'] . "px",
      "--ss-fs-bd-d: " . (int)$o['fs_badge_desktop'] . "px",
      "--ss-fw-bd: " . (int)$o['fw_badge'],
    ];
    $vars_css = implode('; ', $vars) . ';';

    $paw_count = max(0, min(80, (int)$o['paw_count']));

    // Progressive enhancement: preload the current year's first page server-side.
    // This gives visitors and search engines meaningful adopted-animal content before JavaScript runs.
    $seo_adoptions = [];
    if (function_exists('rest_do_request')) {
      $seo_request = new WP_REST_Request('GET', '/straysafe/v1/adoptions');
      $seo_request->set_param('speciesid', 2);
      $seo_request->set_param('year', (int) current_time('Y'));
      $seo_response = rest_do_request($seo_request);
      if (!is_wp_error($seo_response) && $seo_response->get_status() >= 200 && $seo_response->get_status() < 300) {
        $seo_data = $seo_response->get_data();
        if (is_array($seo_data)) {
          $seo_adoptions = array_slice($seo_data, 0, max(1, (int)$o['cols_desktop'] * (int)$o['rows_desktop']));
        }
      }
    }

    ob_start();
    ?>
<section id="asm-successstories-widget" aria-labelledby="asm-ss-heading"
     class="w-full relative overflow-hidden rounded-2xl"
     style="background:var(--ss-bg); <?php echo esc_attr($vars_css); ?>">

  <!-- Decorative paw prints (NEW SVG) -->
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

    // Random pawprints (supports up to 80). Uses inline positions so they don't repeat the same spots.
    for ($i=0; $i < $paw_count; $i++) {
      $top  = mt_rand(6, 90);
      $left = mt_rand(4, 92);
      $delay = (mt_rand(0, 360) / 100) . 's'; // 0.00s–3.60s
      $size = mt_rand(30, 44);               // px
      $dur  = (mt_rand(360, 520) / 100) . 's'; // 3.60s–5.20s
      $rot  = mt_rand(-25, 25);

      echo '<div class="asm-paw-bg" style="top:'.$top.'%; left:'.$left.'%; animation-delay:'.$delay.'; animation-duration:'.$dur.'; transform: rotate('.$rot.'deg);">'
          . $paw_svg($size)
          . '</div>';
    }
  ?>

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10">
    <div class="text-center mb-6">
      <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-2">
        <h2 id="asm-ss-heading" class="leading-tight" style="color:var(--ss-brand);"><?php echo esc_html($o['title_text']); ?></h2>
      </div>

      <p id="asm-ss-subheading" style="color:var(--ss-muted);"><?php echo esc_html($o['subtitle_text']); ?></p>

      <div class="mt-4 flex justify-center">
        <select id="asm-ss-year"
                class="w-full max-w-[260px] sm:max-w-[300px] px-4 py-2 border shadow-sm font-semibold bg-white"
                style="border-color:var(--ss-brand); color:var(--ss-text); border-radius: var(--ss-btn-radius);">
        </select>
      </div>
    </div>

    <?php if (!empty($o['show_top_navigation'])) : ?>
    <!-- TOP NAV -->
    <div class="flex items-center justify-between gap-3 mb-5">
      <button id="asm-ss-prev"
              class="shrink-0 inline-flex items-center justify-center w-11 h-11 bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
              style="border-color:var(--ss-brand); border-radius: var(--ss-btn-radius);"
              aria-label="Previous">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>

      <div class="flex-1 text-center">
        <span id="asm-ss-page-label" style="color:var(--ss-text);"><?php echo esc_html('Loading…'); ?></span>
      </div>

      <button id="asm-ss-next"
              class="shrink-0 inline-flex items-center justify-center w-11 h-11 bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
              style="border-color:var(--ss-brand); border-radius: var(--ss-btn-radius);"
              aria-label="Next">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2">
          <path d="M9 6l6 6-6 6"/>
        </svg>
      </button>
    </div>
    <?php endif; ?>

    <div id="asm-ss-grid" class="grid gap-5 lg:gap-6 items-stretch" aria-live="polite" aria-busy="true">
      <?php foreach ($seo_adoptions as $seo_animal) :
        $seo_id = preg_replace('/\D+/', '', (string)($seo_animal['ANIMALID'] ?? $seo_animal['AnimalID'] ?? $seo_animal['ID'] ?? ''));
        $seo_name = trim((string)($seo_animal['ANIMALNAME'] ?? $seo_animal['AnimalName'] ?? 'Adopted cat'));
        $seo_age = trim((string)($seo_animal['ANIMALAGE'] ?? $seo_animal['AnimalAge'] ?? ''));
        $seo_sex = trim((string)($seo_animal['SEXNAME'] ?? $seo_animal['SexName'] ?? $seo_animal['SEX'] ?? ''));
        $seo_breed = trim((string)($seo_animal['BREEDNAME'] ?? $seo_animal['BreedName'] ?? $seo_animal['BREEDNAME1'] ?? ''));
        $seo_story = trim((string)($seo_animal['ANIMALCOMMENTS'] ?? $seo_animal['WEBSITEMEDIANOTES'] ?? $seo_animal['DESCRIPTION'] ?? $seo_animal['ANIMALDESCRIPTION'] ?? ''));
        $seo_img = $seo_id !== '' ? rest_url('straysafe/v1/animal-image') . '?animalid=' . rawurlencode($seo_id) . '&seq=1' : '';
        $seo_url = '';
        if ($adopted_page_url !== '' && $seo_id !== '') {
          $seo_url = add_query_arg(['adopted' => $seo_id], $adopted_page_url);
        } elseif (class_exists('StraySafe_UI_Suite_SEO') && $seo_id !== '') {
          $seo_url = StraySafe_UI_Suite_SEO::profile_url($seo_animal, true);
        }
      ?>
        <article class="asm-card bg-white rounded-2xl shadow-md overflow-hidden w-full" style="border-style:solid;border-color:var(--ss-card-border-color);border-width:var(--ss-card-border-width);">
          <?php if ($seo_img !== '') : ?>
            <img src="<?php echo esc_url($seo_img); ?>" alt="<?php echo esc_attr($seo_name . ', an adopted cat'); ?>" loading="eager" decoding="async" style="width:100%;aspect-ratio:1/1;object-fit:cover;display:block;" />
          <?php endif; ?>
          <div style="padding:var(--ss-card-pad);">
            <h3 class="ss-card-name" style="margin:0;color:var(--ss-text);"><?php if ($seo_url !== '') : ?><a href="<?php echo esc_url($seo_url); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($seo_name); ?></a><?php else : ?><?php echo esc_html($seo_name); ?><?php endif; ?></h3>
            <p class="ss-card-meta" style="margin:.4rem 0 0;color:var(--ss-muted);"><?php echo esc_html(implode(' • ', array_filter([$seo_sex, $seo_age, $seo_breed]))); ?></p>
            <?php if ($seo_story !== '') : ?><p style="margin:.75rem 0 0;color:var(--ss-text);line-height:1.6;"><?php echo esc_html(wp_trim_words($seo_story, 28)); ?></p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- BOTTOM NAV -->
    <div class="mt-6 flex items-center justify-between gap-3">
      <button id="asm-ss-prev-bottom"
              class="shrink-0 inline-flex items-center justify-center w-11 h-11 bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
              style="border-color:var(--ss-brand); border-radius: var(--ss-btn-radius);"
              aria-label="Previous">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>

      <div class="flex-1 text-center">
        <span id="asm-ss-page-label-bottom" style="color:var(--ss-text);"></span>
      </div>

      <button id="asm-ss-next-bottom"
              class="shrink-0 inline-flex items-center justify-center w-11 h-11 bg-white shadow-md border-2 transition disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-xl"
              style="border-color:var(--ss-brand); border-radius: var(--ss-btn-radius);"
              aria-label="Next">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2">
          <path d="M9 6l6 6-6 6"/>
        </svg>
      </button>
    </div>

    <div class="text-center mt-8">
      <p id="asm-ss-footer" class="px-2" style="color:var(--ss-muted);"><?php echo esc_html($o['footer_text']); ?></p>
    </div>
  </div>

  <div id="asm-ss-modal" class="hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="asm-ss-modal-name" tabindex="-1" style="<?php echo esc_attr($vars_css); ?>">
    <div id="asm-ss-modal-backdrop" class="absolute inset-0 bg-black/50"></div>
    <div id="asm-ss-modal-viewport" class="absolute inset-0 overflow-y-auto p-4 sm:p-6" style="min-height:100dvh;display:flex;align-items:flex-start;justify-content:center;">
      <div id="asm-ss-modal-panel" class="bg-white rounded-2xl shadow-2xl border-2 overflow-hidden flex flex-col" style="border-color:var(--ss-brand);">
        <div class="sticky top-0 z-20 bg-white flex items-center justify-between px-4 sm:px-6 py-4 border-b">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" style="background:var(--ss-brand);"><span class="text-white text-xl">🐱</span></div>
            <div class="min-w-0">
              <div id="asm-ss-modal-name" class="truncate font-extrabold" style="color:var(--ss-text);">Animal</div>
              <div id="asm-ss-modal-meta" class="truncate font-semibold text-sm" style="color:var(--ss-muted);">—</div>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button id="asm-ss-modal-share" class="inline-flex items-center justify-center gap-2 w-9 sm:w-auto px-0 sm:px-3 h-9 sm:h-10 rounded-xl border-2 bg-white hover:shadow-md text-sm font-semibold<?php echo empty($o['enable_deep_links']) ? ' hidden' : ''; ?>" style="border-color:var(--ss-brand);color:var(--ss-brand);" aria-label="Share adopted cat" type="button">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/>
                <path d="M16 6l-4-4-4 4"/>
                <path d="M12 2v13"/>
              </svg>
              <span id="asm-ss-modal-share-text" class="hidden sm:inline"><?php echo esc_html($o['share_button_text'] ?? 'Share'); ?></span>
            </button>
            <button id="asm-ss-modal-close" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border-2 bg-white hover:shadow-md shrink-0" style="border-color:var(--ss-brand);" aria-label="Close" type="button">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
        <div id="asm-ss-modal-scroll" class="flex-1 overflow-y-auto bg-white">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-0 bg-white">
            <div id="asm-ss-modal-media-col" class="p-4 sm:p-6 bg-white">
              <div class="relative rounded-2xl overflow-hidden border bg-gray-50 w-full aspect-square max-w-[420px] mx-auto md:max-w-none">
                <img id="asm-ss-modal-mainimg" src="" alt="" class="w-full h-full object-cover" loading="eager" decoding="sync" fetchpriority="high" />
                <button id="asm-ss-modal-photo-prev" type="button" class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/90 border-2 shadow hidden" style="border-color:var(--ss-brand);" aria-label="Previous picture"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>
                <button id="asm-ss-modal-photo-next" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/90 border-2 shadow hidden" style="border-color:var(--ss-brand);" aria-label="Next picture"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="var(--ss-brand)" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg></button>
              </div>
              <div id="asm-ss-modal-thumbs" class="mt-3 flex gap-2 overflow-x-auto pb-1"></div>
            </div>
            <div id="asm-ss-modal-info-col" class="p-4 sm:p-6 md:border-l bg-white">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:var(--ss-muted);">Adopted</div><div id="asm-ss-modal-adopted-date" class="text-sm lg:text-base font-extrabold leading-snug" style="color:var(--ss-text);">—</div></div>
                <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:var(--ss-muted);">Age</div><div id="asm-ss-modal-age" class="text-sm lg:text-base font-extrabold leading-snug" style="color:var(--ss-text);">—</div></div>
                <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:var(--ss-muted);">Sex</div><div id="asm-ss-modal-sex" class="text-sm lg:text-base font-extrabold leading-snug" style="color:var(--ss-text);">—</div></div>
                <div class="bg-white rounded-2xl p-3 border shadow-sm"><div class="text-xs font-bold" style="color:var(--ss-muted);">Breed</div><div id="asm-ss-modal-breed" class="text-sm lg:text-base font-extrabold leading-snug" style="color:var(--ss-text);">—</div></div>
              </div>
              <?php if (!empty($o['adoptables_cta_enabled']) && !empty($o['adoptables_cta_url'])) : ?>
                <div id="asm-ss-adoptables-cta" class="mt-5 rounded-2xl border p-4 bg-white shadow-sm" style="border-color:var(--ss-brand);">
                  <?php if (trim((string)($o['adoptables_cta_text'] ?? '')) !== '') : ?>
                    <p class="font-semibold mb-3" style="color:var(--ss-text);"><?php echo esc_html($o['adoptables_cta_text']); ?></p>
                  <?php endif; ?>
                  <a class="inline-flex items-center justify-center px-4 h-11 rounded-xl border-2 font-bold shadow-sm" href="<?php echo esc_url($o['adoptables_cta_url']); ?>" style="background:var(--ss-brand);border-color:var(--ss-brand);color:#fff;text-decoration:none;">
                    <?php echo esc_html($o['adoptables_cta_button_text'] ?: 'View animals for adoption'); ?>
                  </a>
                </div>
              <?php endif; ?>
              <div class="mt-4 text-sm leading-snug font-semibold" style="color:var(--ss-muted);font-size:.875rem;line-height:1.45;">
                Tip: Click the dark background (outside the card) or press ESC to close.
              </div>
              <div id="asm-ss-modal-scroll-hint" class="asm-ss-story-scroll-hint items-center justify-center mt-4 select-none" style="color:var(--ss-muted);">
                <div class="flex items-center gap-2 text-sm font-semibold">
                  <span>Scroll to read my story</span>
                  <svg class="w-4 h-4 asm-ss-scroll-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 5v14" />
                    <path d="M19 12l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>
            <div id="asm-ss-modal-story-section" class="hidden px-4 sm:px-6 pt-0 pb-3 sm:pb-4 bg-white md:col-span-2 border-t">
              <div class="bg-white rounded-2xl px-3 sm:px-4 py-2 border shadow-sm">
                <div id="asm-ss-modal-story-wrap" class="hidden">
                  <div class="text-base sm:text-lg font-extrabold mb-2" style="color:var(--ss-muted);">Story</div>
                  <div id="asm-ss-modal-story" class="leading-relaxed" style="color:var(--ss-text); white-space:pre-wrap; word-break:break-word;"></div>
                </div>
                <div id="asm-ss-modal-global-text-wrap" class="hidden leading-relaxed text-sm sm:text-base" style="color:var(--ss-text); white-space:pre-wrap; word-break:break-word;margin:0;">
                  <div class="asm-ss-story-divider" aria-hidden="true"></div>
                  <div id="asm-ss-modal-global-text"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div id="asm-ss-modal-animal-nav" class="shrink-0 flex items-center justify-between gap-3 px-4 sm:px-6 py-3 border-t bg-white">
          <button id="asm-ss-modal-prev-animal" type="button" class="inline-flex items-center justify-center gap-2 px-4 h-11 rounded-xl border-2 bg-white shadow-sm hidden" style="border-color:var(--ss-brand);color:var(--ss-brand);" aria-label="Previous adopted cat"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg><span>Previous cat</span></button>
          <button id="asm-ss-modal-next-animal" type="button" class="ml-auto inline-flex items-center justify-center gap-2 px-4 h-11 rounded-xl border-2 bg-white shadow-sm hidden" style="border-color:var(--ss-brand);color:var(--ss-brand);" aria-label="Next adopted cat"><span>Next cat</span><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg></button>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
  /* Avoid unstyled flash before Tailwind/JS render */
  #asm-successstories-widget { opacity: 0; visibility: hidden; }
  #asm-successstories-widget.asm-ready { opacity: 1; visibility: visible; transition: opacity 0.18s ease-out; }

  #asm-successstories-widget,
  #asm-successstories-widget *,
  #asm-ss-modal,
  #asm-ss-modal * { <?php echo $font_css; ?> }

  @keyframes pawPrint {
    0%   { opacity: 0; transform: scale(0.5) rotate(-15deg); }
    50%  { opacity: var(--ss-paw-opacity, 0.08); transform: scale(1) rotate(0deg); }
    100% { opacity: 0; transform: scale(1.2) rotate(15deg); }
  }
  #asm-successstories-widget .asm-paw-bg { position:absolute; opacity:0; animation:pawPrint 4s ease-in-out infinite; pointer-events:none; }

  @media (hover: none) and (pointer: coarse) { #asm-successstories-widget .asm-card:hover { transform:none !important; box-shadow:none !important; } #asm-successstories-widget.asm-ready { transition:none; } }
  @media (max-width: 640px) { #asm-successstories-widget .asm-paw-bg { display:none; } }
  @keyframes asmSsArrowBounce { 0%,100%{transform:translateY(0);} 50%{transform:translateY(4px);} }
  #asm-ss-modal-scroll-hint { display:none; }
  #asm-ss-modal-scroll-hint.asm-ss-story-scroll-hint-visible { display:flex; }
  .asm-ss-scroll-arrow { width:1rem !important; height:1rem !important; animation:asmSsArrowBounce 1.2s ease-in-out infinite; }
  .asm-ss-story-divider { border-top:1px solid rgba(100,116,139,.28); height:0; margin:.35rem 0; }
  #asm-ss-modal-global-text { padding:.25rem 0; }

  /* Typography hooks */
  #asm-ss-heading { font-size: var(--ss-fs-h-m); font-weight: var(--ss-fw-h); }
  #asm-ss-subheading { font-size: var(--ss-fs-sh-m); font-weight: var(--ss-fw-sh); }
  #asm-ss-footer { font-size: var(--ss-fs-ft-m); font-weight: var(--ss-fw-ft); }
  #asm-ss-page-label, #asm-ss-page-label-bottom { font-size: var(--ss-fs-pl-m); font-weight: var(--ss-fw-pl); }

  .ss-card-name { font-size: var(--ss-fs-cn-m); font-weight: var(--ss-fw-cn); }
  .ss-card-meta { font-size: var(--ss-fs-cm-m); font-weight: var(--ss-fw-cm); }
  .ss-badge { font-size: var(--ss-fs-bd-m); font-weight: var(--ss-fw-bd); }

  @media (min-width: 768px){
    #asm-ss-heading { font-size: var(--ss-fs-h-t); }
    #asm-ss-subheading { font-size: var(--ss-fs-sh-t); }
    #asm-ss-footer { font-size: var(--ss-fs-ft-t); }
    #asm-ss-page-label, #asm-ss-page-label-bottom { font-size: var(--ss-fs-pl-t); }

    .ss-card-name { font-size: var(--ss-fs-cn-t); }
    .ss-card-meta { font-size: var(--ss-fs-cm-t); }
    .ss-badge { font-size: var(--ss-fs-bd-t); }
  }
  @media (min-width: 1024px){
    #asm-ss-heading { font-size: var(--ss-fs-h-d); }
    #asm-ss-subheading { font-size: var(--ss-fs-sh-d); }
    #asm-ss-footer { font-size: var(--ss-fs-ft-d); }
    #asm-ss-page-label, #asm-ss-page-label-bottom { font-size: var(--ss-fs-pl-d); }

    .ss-card-name { font-size: var(--ss-fs-cn-d); }
    .ss-card-meta { font-size: var(--ss-fs-cm-d); }
    .ss-badge { font-size: var(--ss-fs-bd-d); }
  }

  #asm-ss-modal{ position:fixed !important; inset:0 !important; z-index:2147483647 !important; isolation:isolate; }
  #asm-ss-modal:not(.asm-ss-modal-ready){ display:none !important; visibility:hidden !important; opacity:0 !important; pointer-events:none !important; }
  #asm-ss-modal.hidden{ display:none !important; }
  #asm-ss-modal-viewport{ overscroll-behavior:contain; -webkit-overflow-scrolling:touch; padding-top:max(16px, env(safe-area-inset-top)); padding-bottom:max(16px, env(safe-area-inset-bottom)); }
  #asm-ss-modal-panel{ width:min(100%, var(--ss-modal-maxw, 896px)); max-width:var(--ss-modal-maxw,896px); height:min(88dvh, 900px); max-height:calc(100dvh - 32px); min-height:0; margin:auto 0; position:relative; z-index:2; }
  #asm-ss-modal-panel > .sticky, #asm-ss-modal-animal-nav{ flex:0 0 auto; }
  #asm-ss-modal-scroll{ min-height:0; overflow-y:auto !important; overscroll-behavior:contain; -webkit-overflow-scrolling:touch; }
  #asm-ss-modal-animal-nav{ position:relative; z-index:5; }
  #asm-ss-modal-share,
  #asm-ss-modal-close,
  #asm-ss-modal-photo-prev,
  #asm-ss-modal-photo-next,
  #asm-ss-modal-prev-animal,
  #asm-ss-modal-next-animal{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
  }
  #asm-ss-modal-share,
  #asm-ss-modal-close{
    min-height:2.5rem;
    border-style:solid;
    border-width:2px;
    border-radius:.75rem;
    font-size:.875rem;
    font-weight:700;
    line-height:1;
  }
  #asm-ss-modal-share,
  #asm-ss-modal-close{ width:2.5rem; padding-left:0; padding-right:0; }
  #asm-ss-modal svg{ width:1.25rem; height:1.25rem; flex:0 0 auto; }
  #asm-ss-modal-share-text{ display:none; }
  #asm-ss-modal-share.hidden,
  #asm-ss-modal-photo-prev.hidden,
  #asm-ss-modal-photo-next.hidden,
  #asm-ss-modal-prev-animal.hidden,
  #asm-ss-modal-next-animal.hidden,
  #asm-ss-modal-story-section.hidden,
  #asm-ss-modal-story-wrap.hidden,
  #asm-ss-modal-global-text-wrap.hidden{ display:none !important; }
  #asm-ss-modal-media-col .relative,
  #asm-successstories-widget .asm-card .bg-gray-100{
    overflow:hidden !important;
    background:#f8fafc;
    -webkit-transform:translateZ(0);
    transform:translateZ(0);
    -webkit-backface-visibility:hidden;
    backface-visibility:hidden;
    contain:paint;
  }
  #asm-ss-modal-mainimg,
  #asm-ss-modal-thumbs img,
  #asm-successstories-widget .asm-card img{
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
  @media (max-width:767px){ #asm-ss-modal-panel{ height:calc(100dvh - 24px); max-height:calc(100dvh - 24px); } #asm-ss-modal-animal-nav span{ display:none; } #asm-ss-modal-animal-nav button{ width:44px; padding-left:0; padding-right:0; } }
  @media (min-width:640px){ #asm-ss-modal-share{ width:auto; padding-left:.75rem; padding-right:.75rem; } #asm-ss-modal-share-text{ display:inline; } }
  .asm-card{ width:100%; margin-left:auto; margin-right:auto; border-radius: var(--ss-card-radius) !important; }
  @media (max-width: 767px){ .asm-card{ max-width: calc(100% * var(--ss-scale-m, 1)); } }
  @media (min-width: 768px) and (max-width: 1023px){ .asm-card{ max-width: calc(100% * var(--ss-scale-t, 1)); } }
  @media (min-width: 1024px){ .asm-card{ max-width: calc(100% * var(--ss-scale-d, 1)); } }
</style>

<script>
(() => {
  const ROOT = document.getElementById("asm-successstories-widget");
  if (!ROOT) return;

  const ADOPTED_MODAL = document.getElementById("asm-ss-modal");
  if (ADOPTED_MODAL && ADOPTED_MODAL.parentNode !== document.body) document.body.appendChild(ADOPTED_MODAL);
  let modalPageScrollY = 0;
  let modalPageLocked = false;
  function lockModalPage(){
    if (modalPageLocked) return;
    modalPageLocked = true;
    modalPageScrollY = window.scrollY || window.pageYOffset || 0;
    document.documentElement.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.top = `-${modalPageScrollY}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    document.body.style.overflow = 'hidden';
  }
  function unlockModalPage(){
    if (!modalPageLocked) return;
    modalPageLocked = false;
    document.documentElement.style.overflow = '';
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.body.style.overflow = '';
    window.scrollTo(0, modalPageScrollY);
  }
  function modalIsOpen(){
    const modal = qs('asm-ss-modal');
    return !!modal && !modal.classList.contains('hidden') && modal.getAttribute('aria-hidden') !== 'true';
  }
  function focusableEls(container){
    if (!container) return [];
    const selector = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
    return Array.from(container.querySelectorAll(selector)).filter(el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length));
  }
  function trapModalFocus(event){
    const modal = qs('asm-ss-modal');
    if (!modalIsOpen() || event.key !== 'Tab' || !modal) return;
    const nodes = focusableEls(modal);
    if (!nodes.length) {
      event.preventDefault();
      modal.focus({ preventScroll: true });
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

  const markReady = (afterReady) => {
    requestAnimationFrame(() => {
      if (!ROOT.classList.contains("asm-ready")) ROOT.classList.add("asm-ready");
      if (typeof afterReady === "function") afterReady();
    });
  };

  const OPTS = <?php echo wp_json_encode([
    'min_year' => (int)$o['min_year'],
    'rows' => [
      'm' => (int)$o['rows_mobile'],
      't' => (int)$o['rows_tablet'],
      'd' => (int)$o['rows_desktop'],
    ],
    'enable_modals' => !empty($o['enable_modals']),
    'modal_max_width' => (int)$o['modal_max_width'],
    'enable_deep_links' => !empty($o['enable_deep_links']),
    'share_button_text' => (string)($o['share_button_text'] ?? 'Share'),
    'share_copied_text' => (string)($o['share_copied_text'] ?? 'Link copied'),
    'adopted_page_url' => (string)$adopted_page_url,
    'modal_global_text' => (string)$o['modal_global_text'],
  ]); ?>;

  const CFG = {
    proxyBase: "/wp-json/straysafe/v1",
    brandColor: getComputedStyle(ROOT).getPropertyValue("--ss-brand").trim() || "#ff647e",
    textColor: getComputedStyle(ROOT).getPropertyValue("--ss-text").trim() || "#334155",
    mutedColor: getComputedStyle(ROOT).getPropertyValue("--ss-muted").trim() || "#64748b",
    minYear: Number(OPTS.min_year) || 2000,
    rows: OPTS.rows || { m:3, t:3, d:3 },
    speciesId: 2,
    dateField: "MOVEMENTDATE",
    enableModals: !!OPTS.enable_modals,
    modalMaxWidth: Number(OPTS.modal_max_width) || 896,
    enableDeepLinks: !!OPTS.enable_deep_links,
    shareButtonText: String(OPTS.share_button_text || 'Share'),
    shareCopiedText: String(OPTS.share_copied_text || 'Link copied'),
    adoptedPageUrl: String(OPTS.adopted_page_url || ''),
    modalGlobalText: String(OPTS.modal_global_text || '')
  };

  const qs = (id) => document.getElementById(id);

  function safeText(v, fallback="—"){
    const s = (v ?? "").toString().trim();
    return s ? s : fallback;
  }
  function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function escAttr(s){ return escHtml(s).replace(/"/g,String.fromCharCode(38,113,117,111,116,59)).replace(/'/g,String.fromCharCode(38,35,48,51,57,59)); }

  function normalizeSex(v){
    if (v === 0 || v === "0") return "Female";
    if (v === 1 || v === "1") return "Male";
    const s = (v ?? "").toString().trim().toLowerCase();
    if (s.startsWith("f")) return "Female";
    if (s.startsWith("m")) return "Male";
    return "—";
  }

  function currentDevice(){
    if (window.matchMedia("(min-width: 1024px)").matches) return "d";
    if (window.matchMedia("(min-width: 768px)").matches) return "t";
    return "m";
  }

  function colsFor(dev){
    const prop = dev === "m" ? "--ss-cols-m" : (dev === "t" ? "--ss-cols-t" : "--ss-cols-d");
    return Number(getComputedStyle(ROOT).getPropertyValue(prop)) || 2;
  }

  function rowsFor(dev){
    const value = CFG.rows && Object.prototype.hasOwnProperty.call(CFG.rows, dev) ? Number(CFG.rows[dev]) : 1;
    return Math.max(1, value || 1);
  }

  function applyGridColumns(){
    const grid = qs("asm-ss-grid");
    if(!grid) return;
    const dev = currentDevice();
    const cols = colsFor(dev);
    grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
  }

  function perPageCount(){
    const dev = currentDevice();
    const cols = colsFor(dev);
    const rows = rowsFor(dev);
    return Math.max(1, cols * rows);
  }

  function getNumericAnimalId(a){
    const raw = a.ANIMALID ?? a.AnimalID ?? a.animalid ?? a.ID ?? a.Id ?? "";
    const digits = String(raw).replace(/\D/g, "");
    return digits || "";
  }

  function imgUrl(animalId, seq){
    return `${CFG.proxyBase}/animal-image?animalid=${encodeURIComponent(animalId)}&seq=${encodeURIComponent(seq)}`;
  }

  function slugifyPart(value){
    return String(value || "")
      .toLowerCase()
      .replace(/&/g, " and ")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .replace(/-{2,}/g, "-");
  }

  function adoptedShareSlug(animal){
    const name = safeText(animal?.ANIMALNAME ?? animal?.AnimalName ?? animal?.NAME, "");
    const code = safeText(animal?.CODE ?? animal?.SHELTERCODE ?? animal?.ShelterCode, "");
    const id = getNumericAnimalId(animal);
    return [slugifyPart(name), slugifyPart(code)].filter(Boolean).join("-") || id;
  }

  function adoptedModalUrl(animal){
    const href = CFG.adoptedPageUrl || window.location.href;
    const value = adoptedShareSlug(animal) || getNumericAnimalId(animal);
    try {
      const url = new URL(href, window.location.origin);
      if (CFG.enableDeepLinks && value) url.searchParams.set("adopted", value);
      return url.toString();
    } catch (e) {
      const sep = String(href).includes("?") ? "&" : "?";
      return String(href) + sep + "adopted=" + encodeURIComponent(value);
    }
  }

  function syncAdoptedModalUrl(animal){
    if (!CFG.enableDeepLinks || !window.history || !window.history.replaceState) return;
    const url = new URL(window.location.href);
    if (animal) url.searchParams.set("adopted", adoptedShareSlug(animal) || getNumericAnimalId(animal));
    else url.searchParams.delete("adopted");
    window.history.replaceState({}, "", url.toString());
  }

  function requestedAdoptedId(){
    const url = new URL(window.location.href);
    return (url.searchParams.get("adopted") || "").trim().toLowerCase();
  }

  function matchesAdoptedRequest(animal, wanted){
    const value = String(wanted || "").toLowerCase();
    if (!value) return false;
    const id = String(getNumericAnimalId(animal)).toLowerCase();
    const slug = adoptedShareSlug(animal);
    return value === id || value === slug || (!!id && value.startsWith(id + "-"));
  }

  function parseDateString(raw){
    if (!raw) return null;
    const s = String(raw).trim();
    if (!s || s === '0' || s === '0000-00-00') return null;
    const uk = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})(?:\s|$)/);
    if (uk) {
      const d = new Date(Number(uk[3]), Number(uk[2]) - 1, Number(uk[1]));
      return Number.isNaN(d.getTime()) ? null : d;
    }
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? null : d;
  }

  function getDate(a){
    const fields = ['MOVEMENTDATE','MovementDate','ADOPTIONDATE','AdoptionDate','DATEADOPTED','DateAdopted','MOSTRECENTADOPTIONDATE','MostRecentAdoptionDate','ACTIVEMOVEMENTDATE','ActiveMovementDate'];
    for (const field of fields) {
      const parsed = parseDateString(a?.[field]);
      if (parsed) return parsed;
    }
    return null;
  }

  function adoptionTime(a){
    const d = getDate(a);
    return d ? d.getTime() : 0;
  }

  function formatUKDate(d){
    return new Intl.DateTimeFormat("en-GB", { day:"2-digit", month:"short", year:"numeric" }).format(d);
  }

  async function fetchAdoptions(selectedYear, includeAllYears=false){
    const params = new URLSearchParams();
    params.set("speciesid", String(CFG.speciesId));
    if (includeAllYears) params.set("years", "20");
    else params.set("year", String(selectedYear));
    const url = `${CFG.proxyBase}/adoptions?${params.toString()}`;
    const cacheKey = includeAllYears ? "years-20" : String(selectedYear);

    if (fetchAdoptions.cache[cacheKey]) return fetchAdoptions.cache[cacheKey];

    const res = await fetch(url, { method: "GET", credentials: "same-origin", cache: "default" });
    const text = await res.text();
    if (!res.ok) throw new Error(`(${res.status}) ${text}`.slice(0, 500));

    const data = JSON.parse(text);
    if (!Array.isArray(data)) throw new Error("Expected array from adoptions endpoint.");
    fetchAdoptions.cache[cacheKey] = data;
    return data;
  }
  fetchAdoptions.cache = fetchAdoptions.cache || {};

  let all = [];
  let shown = [];
  let pageIndex = 0;


  function dateBadgePositionClasses(){
    const h = <?php echo wp_json_encode((string)($o['date_label_halign'] ?? 'right')); ?> === 'left' ? 'left-2' : 'right-2';
    const v = <?php echo wp_json_encode((string)($o['date_label_valign'] ?? 'top')); ?> === 'bottom' ? 'bottom-2' : 'top-2';
    return `${v} ${h}`;
  }

  function cardTemplate(a){
    const animalId = getNumericAnimalId(a);
    const name  = safeText(a.ANIMALNAME ?? a.AnimalName ?? a.NAME);
    const age   = safeText(a.ANIMALAGE ?? a.AnimalAge);
    const sex   = normalizeSex(a.SEXNAME ?? a.SexName ?? a.SEX);
    const breed = safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1);

    const thumb = animalId ? imgUrl(animalId, 1) : "";
    const safeId = escAttr(animalId);
    const safeName = escHtml(name);
    const safeNameAttr = escAttr(name);
    const safeAge = escHtml(age);
    const safeSex = escHtml(sex);
    const safeBreed = escHtml(breed);
    const imageBlock = animalId
      ? `<img src="${escAttr(thumb)}" alt="${safeNameAttr}" class="block w-full h-full object-cover" loading="eager" decoding="async" fetchpriority="auto" />`
      : `<div class="w-full h-full flex items-center justify-center text-4xl">🐾</div>`;

    return `
      <button type="button" class="asm-card group text-left bg-white rounded-2xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-xl hover:scale-[1.02] w-full ${CFG.enableModals ? 'focus:outline-none focus:ring-4 focus:ring-pink-200 cursor-pointer' : ''}" data-animalid="${safeId}" tabindex="0"
           style="border-style:solid;border-color:var(--ss-card-border-color);border-width:var(--ss-card-border-width);" ${CFG.enableModals ? `aria-label="View adoption story for ${safeNameAttr}" aria-haspopup="dialog" aria-controls="asm-ss-modal"` : ''}>
        <div class="relative">
          <div class="bg-gray-100 aspect-square w-full" style="line-height:0;overflow:hidden;">${imageBlock}</div>

          <div class="absolute inset-x-0 bottom-0 p-1.5 sm:p-2">
            <div class="rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 bg-white/85 backdrop-blur-sm shadow-sm border"
                 style="border-color:${CFG.brandColor};">
              <div class="ss-card-name leading-tight truncate" style="color:${CFG.textColor};">${safeName}</div>
              <div class="ss-card-meta truncate" style="color:${CFG.mutedColor};">
                <span class="sm:hidden">${safeAge} • ${safeBreed}</span>
                <span class="hidden sm:inline">${safeSex} • ${safeAge}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hidden sm:block" style="padding: var(--ss-card-pad);">
          <div class="font-semibold truncate text-xs sm:text-sm lg:text-base" style="color:${CFG.mutedColor};">${safeBreed}</div>
        </div>
      </button>
    `;
  }

  let lastModalTrigger = null;
  let currentModalAnimalIndex = -1;
  let currentModalPhotoIndex = 0;
  let currentModalPhotoUrls = [];

  function trackEvent(name){
    if (!name) return;
    try {
      const body = new URLSearchParams();
      body.set('action', 'asm_plugin_suite_track');
      body.set('event', String(name));
      body.set('nonce', <?php echo wp_json_encode(wp_create_nonce('asm_plugin_suite_track')); ?>);
      fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
        body: body.toString(),
        keepalive: true
      }).catch(() => {});
    } catch (e) {}
  }

  function closeModal(){
    const modal = qs("asm-ss-modal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.setAttribute("aria-hidden", "true");
    syncAdoptedModalUrl(null);
    unlockModalPage();
    if (lastModalTrigger && typeof lastModalTrigger.focus === 'function') lastModalTrigger.focus();
  }

  function findRequestedAdoptedAnimal(){
    const wanted = requestedAdoptedId();
    if (!wanted) return null;
    return (shown || []).find((x) => matchesAdoptedRequest(x, wanted))
      || (all || []).find((x) => matchesAdoptedRequest(x, wanted))
      || null;
  }

  function openRequestedAdoptedAnimal(){
    const target = findRequestedAdoptedAnimal();
    if (!target) return false;
    lastModalTrigger = null;
    openModal(target);
    return true;
  }

  function updateModalAnimalNavigation(){
    const prev = qs('asm-ss-modal-prev-animal');
    const next = qs('asm-ss-modal-next-animal');
    const total = Array.isArray(shown) ? shown.length : 0;
    const hasPrev = currentModalAnimalIndex > 0;
    const hasNext = currentModalAnimalIndex >= 0 && currentModalAnimalIndex < total - 1;
    if (prev) { prev.classList.toggle('hidden', !hasPrev); prev.disabled = !hasPrev; }
    if (next) { next.classList.toggle('hidden', !hasNext); next.disabled = !hasNext; }
  }

  function openAdjacentAdoptedAnimal(direction){
    const index = currentModalAnimalIndex + direction;
    if (!Array.isArray(shown) || index < 0 || index >= shown.length) return;
    openModal(shown[index]);
  }

  function openModal(a){
    if (!CFG.enableModals) return;
    const modal = qs("asm-ss-modal");
    if (!modal) return;

    const animalId = getNumericAnimalId(a);
    currentModalAnimalIndex = Array.isArray(shown) ? shown.findIndex(item => String(getNumericAnimalId(item)) === String(animalId)) : -1;
    updateModalAnimalNavigation();
    const name  = safeText(a.ANIMALNAME ?? a.AnimalName ?? a.NAME);
    const age   = safeText(a.ANIMALAGE ?? a.AnimalAge);
    const sex   = normalizeSex(a.SEXNAME ?? a.SexName ?? a.SEX);
    const breed = safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1);
    const d = getDate(a);
    const adoptedLabel = d ? formatUKDate(d) : '—';
    const shelterCode = safeText(a.CODE ?? a.SHELTERCODE ?? a.ShelterCode ?? a.sheltercode ?? a.Sheltercode, animalId || '');

    trackEvent("adopted_modal_open");
    syncAdoptedModalUrl(a);
    qs("asm-ss-modal-name").textContent = name;
    qs("asm-ss-modal-meta").textContent = shelterCode || [sex, age].filter(value => value && value !== '—').join(' • ') || breed;
    qs("asm-ss-modal-adopted-date").textContent = adoptedLabel;
    qs("asm-ss-modal-age").textContent = age;
    qs("asm-ss-modal-sex").textContent = sex;
    qs("asm-ss-modal-breed").textContent = breed;
    const story = safeText(a.ANIMALCOMMENTS ?? a.WEBSITEMEDIANOTES ?? a.DESCRIPTION ?? a.ANIMALDESCRIPTION, '');
    const storySection = qs("asm-ss-modal-story-section");
    const storyWrap = qs("asm-ss-modal-story-wrap");
    const storyEl = qs("asm-ss-modal-story");
    const globalTextWrap = qs("asm-ss-modal-global-text-wrap");
    const globalTextEl = qs("asm-ss-modal-global-text");
    const globalText = String(CFG.modalGlobalText || '').replace(/\r\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    let hasStory = false;
    if (storyWrap && storyEl) {
      if (story && story !== '—') {
        storyEl.textContent = story;
        storyWrap.classList.remove('hidden');
        hasStory = true;
      } else {
        storyEl.textContent = '';
        storyWrap.classList.add('hidden');
      }
    }
    if (globalTextWrap && globalTextEl) {
      globalTextEl.textContent = globalText;
      globalTextWrap.classList.toggle('hidden', !globalText);
      globalTextWrap.classList.toggle('mt-3', !!globalText && hasStory);
    }
    if (storySection) storySection.classList.toggle('hidden', !hasStory && !globalText);
    [qs("asm-ss-modal-scroll-hint")].forEach((hint) => {
      if (!hint) return;
      hint.classList.toggle('asm-ss-story-scroll-hint-visible', hasStory);
    });

    const shareBtn = qs("asm-ss-modal-share");
    const shareText = qs("asm-ss-modal-share-text");
    if (shareBtn) {
      shareBtn.classList.toggle("hidden", !CFG.enableDeepLinks);
      shareBtn.setAttribute("aria-label", `Share ${name}`);
      if (shareText) shareText.textContent = CFG.shareButtonText;
      shareBtn.onclick = async () => {
        const url = adoptedModalUrl(a);
        try {
          if (navigator.share) {
            await navigator.share({ title: name, url });
          } else if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(url);
            if (shareText) {
              shareText.textContent = CFG.shareCopiedText;
              window.setTimeout(() => { shareText.textContent = CFG.shareButtonText; }, 1600);
            }
          } else {
            window.prompt("Copy this link", url);
          }
        } catch (e) {}
      };
    }

	    const countRaw = Number(a.WEBSITEIMAGECOUNT ?? a.WebsiteImageCount ?? a.WEBSITEIMAGES ?? 1);
	    const count = Math.max(1, Math.min(12, Number.isFinite(countRaw) ? countRaw : 1));
	    let urls = [];
	    const failedPhotoUrls = new Set();
	    const photoFailureCounts = new Map();
	    for (let i = 1; i <= count; i++) urls.push(imgUrl(animalId, i));
	    urls.slice(0, 4).forEach((url) => {
	      const preload = new Image();
	      preload.decoding = "async";
	      preload.src = url;
	    });

	    const mainImg = qs("asm-ss-modal-mainimg");
	    const thumbs = qs("asm-ss-modal-thumbs");
	    const prevPhoto = qs('asm-ss-modal-photo-prev');
	    const nextPhoto = qs('asm-ss-modal-photo-next');
	    let photoRequestToken = 0;
	    currentModalPhotoUrls = urls;
	    const updatePhotoButtons = () => {
	      const multiplePhotos = urls.length > 1;
	      if (prevPhoto) prevPhoto.classList.toggle('hidden', !multiplePhotos);
	      if (nextPhoto) nextPhoto.classList.toggle('hidden', !multiplePhotos);
	      thumbs?.querySelectorAll('button[data-idx]').forEach((button) => button.setAttribute('aria-current', Number(button.dataset.idx) === currentModalPhotoIndex ? 'true' : 'false'));
	    };
	    const removePhotoUrl = (url) => {
	      if (!url || failedPhotoUrls.has(url)) return;
	      failedPhotoUrls.add(url);
	      const failedIndex = urls.indexOf(url);
	      urls = urls.filter(item => item !== url);
	      currentModalPhotoUrls = urls;
	      if (failedIndex >= 0 && currentModalPhotoIndex > failedIndex) currentModalPhotoIndex -= 1;
	      renderThumbs();
	      setMain(Math.min(currentModalPhotoIndex, urls.length - 1));
	    };
	    const notePhotoLoadFailure = (url, imgEl) => {
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
	    };
	    const renderThumbs = () => {
	      if (!thumbs) return;
	      thumbs.innerHTML = urls.map((u, idx) => `
	        <button type="button" class="shrink-0 w-16 h-16 rounded-xl overflow-hidden border-2 bg-gray-50 hover:shadow" style="border-color:${CFG.brandColor};" data-idx="${idx}" aria-label="Show ${escAttr(name)} photo ${idx + 1}">
	          <img src="${escAttr(u)}" alt="${escAttr(name)} photo ${idx + 1}" class="w-full h-full object-cover" loading="eager" decoding="async" fetchpriority="auto" />
	        </button>`).join('');
	      thumbs.querySelectorAll('button[data-idx]').forEach((b) => {
	        b.addEventListener('click', () => setMain(Number(b.dataset.idx)));
	        const thumbImg = b.querySelector('img');
	        if (thumbImg) thumbImg.onerror = () => notePhotoLoadFailure(thumbImg.getAttribute('src'), thumbImg);
	      });
	      updatePhotoButtons();
	    };
	    const setMain = (i) => {
	      if (!mainImg) return;
	      if (!urls.length) {
	        mainImg.removeAttribute('src');
	        mainImg.alt = `${name} photo not available`;
	        updatePhotoButtons();
	        return;
	      }
	      currentModalPhotoIndex = (i + urls.length) % urls.length;
	      const url = urls[currentModalPhotoIndex];
	      mainImg.setAttribute('loading', 'eager');
	      mainImg.setAttribute('decoding', 'sync');
	      mainImg.setAttribute('fetchpriority', 'high');
	      mainImg.alt = `${name} photo ${currentModalPhotoIndex + 1}`;
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
	    };
	    renderThumbs();
	    setMain(0);
	    if (prevPhoto) prevPhoto.onclick = () => { if (urls.length) setMain(currentModalPhotoIndex - 1); };
	    if (nextPhoto) nextPhoto.onclick = () => { if (urls.length) setMain(currentModalPhotoIndex + 1); };
    let touchStartX = null;
    if (mainImg) {
      mainImg.ontouchstart = (event) => { touchStartX = event.touches?.[0]?.clientX ?? null; };
      mainImg.ontouchend = (event) => {
        const endX = event.changedTouches?.[0]?.clientX ?? null;
        if (touchStartX === null || endX === null || urls.length < 2 || Math.abs(endX - touchStartX) < 35) return;
        setMain(currentModalPhotoIndex + (endX > touchStartX ? -1 : 1));
      };
    }

    const scrollEl = qs("asm-ss-modal-scroll");
    if (scrollEl) scrollEl.scrollTop = 0;
    modal.classList.add("asm-ss-modal-ready");
    modal.classList.remove("hidden");
    modal.setAttribute("aria-hidden", "false");
    lockModalPage();
    const closeButton = qs('asm-ss-modal-close');
    if (closeButton) closeButton.focus();
  }

  function updateStructuredData(items){
    const old = document.getElementById('asm-ss-structured-data');
    if (old) old.remove();
    if (!Array.isArray(items) || !items.length) return;
    const list = items.slice(0, 100).map((a, index) => {
      const animalId = getNumericAnimalId(a);
      const name = safeText(a.ANIMALNAME ?? a.AnimalName ?? a.NAME, 'Adopted animal');
      const breed = safeText(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1, '');
      const age = safeText(a.ANIMALAGE ?? a.AnimalAge, '');
      const d = getDate(a);
      const description = [breed, age, d ? `Adopted ${formatUKDate(d)}` : 'Adopted'].filter(Boolean).join('. ');
      return {
        '@type': 'ListItem',
        position: index + 1,
        item: {
          '@type': 'Thing',
          name,
          description,
          url: CFG.enableDeepLinks ? adoptedModalUrl(a) : undefined,
          image: animalId ? imgUrl(animalId, 1) : undefined
        }
      };
    });
    const script = document.createElement('script');
    script.id = 'asm-ss-structured-data';
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      name: <?php echo wp_json_encode((string)$o['title_text']); ?>,
      itemListElement: list
    });
    document.head.appendChild(script);
  }

  function renderPage(){
    const grid = qs("asm-ss-grid");

    const prevTop  = qs("asm-ss-prev");
    const nextTop  = qs("asm-ss-next");
    const labelTop = qs("asm-ss-page-label");

    const prevBot  = qs("asm-ss-prev-bottom");
    const nextBot  = qs("asm-ss-next-bottom");
    const labelBot = qs("asm-ss-page-label-bottom");

    if (!grid || !prevBot || !nextBot || !labelBot) return;

    applyGridColumns();

    const perPage = perPageCount();
    const pageCount = Math.max(1, Math.ceil(shown.length / perPage));
    pageIndex = Math.max(0, Math.min(pageIndex, pageCount - 1));

    const start = pageIndex * perPage;
    const end   = Math.min(start + perPage, shown.length);
    const slice = shown.slice(start, end);

    grid.innerHTML = slice.map(cardTemplate).join("");
    grid.setAttribute("aria-busy", "false");
    updateStructuredData(shown);

    const text = shown.length
      ? `Page ${pageIndex + 1} of ${pageCount}`
      : `No adopted cats found.`;

    if (labelTop) labelTop.textContent = text;
    labelBot.textContent = text;

    const atStart = pageIndex <= 0;
    const atEnd   = pageIndex >= pageCount - 1;

    if (prevTop) prevTop.disabled = atStart;
    prevBot.disabled = atStart;
    if (nextTop) nextTop.disabled = atEnd;
    nextBot.disabled = atEnd;
  }

  function buildShownList(){
    const list = all.slice();
    list.sort((a,b) => adoptionTime(b) - adoptionTime(a));
    shown = list;
    pageIndex = 0;
    renderPage();
  }

  function setYearOptions(){
    const sel = qs("asm-ss-year");
    if (!sel) return;

    const nowYear = new Date().getFullYear();
    const minYear = Math.min(nowYear, Math.max(2000, CFG.minYear || 2000));

    sel.innerHTML = "";
    for (let y = nowYear; y >= minYear; y--) {
      const opt = document.createElement("option");
      opt.value = String(y);
      opt.textContent = String(y);
      sel.appendChild(opt);
    }
    sel.value = String(nowYear);
  }

  async function reload(){
    const sel = qs("asm-ss-year");
    const selectedYear = sel ? sel.value : String(CFG.minYear);
    const wanted = requestedAdoptedId();

	    try {
	      all = await fetchAdoptions(selectedYear, !!wanted);
	      buildShownList();
	      if (wanted) {
	        markReady(() => requestAnimationFrame(openRequestedAdoptedAnimal));
	        return;
	      }
	    } catch (err) {
	      console.error(err);
	      all = [];
      shown = [];
      const lt = qs("asm-ss-page-label");
      const lb = qs("asm-ss-page-label-bottom");
      if (lt) lt.textContent = "Load failed.";
      if (lb) lb.textContent = "Load failed.";
	      renderPage();
	    }
	    markReady();
  }

  function init(){
    const goPrev = () => { pageIndex = Math.max(0, pageIndex - 1); renderPage(); };
    const goNext = () => { pageIndex = pageIndex + 1; renderPage(); };

    const openCardById = (id) => {
      if (!CFG.enableModals || !id) return;
      const target = shown.find((x) => String(getNumericAnimalId(x)) === String(id))
        || all.find((x) => String(getNumericAnimalId(x)) === String(id));
      if (target) openModal(target);
    };

    const grid = qs('asm-ss-grid');
    if (grid) {
      grid.addEventListener('click', (e) => {
        const card = e.target && e.target.closest ? e.target.closest('[data-animalid]') : null;
        if (!card || !grid.contains(card)) return;
        lastModalTrigger = card;
        openCardById(card.getAttribute('data-animalid'));
      });

      grid.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target && e.target.closest ? e.target.closest('[data-animalid]') : null;
        if (!card || !grid.contains(card)) return;
        e.preventDefault();
        lastModalTrigger = card;
        openCardById(card.getAttribute('data-animalid'));
      });
    }

    qs("asm-ss-prev")?.addEventListener("click", goPrev);
    qs("asm-ss-next")?.addEventListener("click", goNext);
    qs("asm-ss-prev-bottom")?.addEventListener("click", goPrev);
    qs("asm-ss-next-bottom")?.addEventListener("click", goNext);

    const modal = qs('asm-ss-modal');
    if (modal) {
      qs('asm-ss-modal-close')?.addEventListener('click', closeModal);
      qs('asm-ss-modal-backdrop')?.addEventListener('click', closeModal);
      qs('asm-ss-modal-viewport')?.addEventListener('click', (e) => { if (e.target === e.currentTarget) closeModal(); });
      qs('asm-ss-modal-panel')?.addEventListener('click', (e) => e.stopPropagation());
      qs('asm-ss-modal-prev-animal')?.addEventListener('click', () => openAdjacentAdoptedAnimal(-1));
      qs('asm-ss-modal-next-animal')?.addEventListener('click', () => openAdjacentAdoptedAnimal(1));
      document.addEventListener('keydown', (e) => {
        if (!modalIsOpen()) return;
        if (e.key === 'Tab') trapModalFocus(e);
        if (e.key === 'Escape') { e.preventDefault(); closeModal(); }
        if (e.key === 'ArrowLeft') openAdjacentAdoptedAnimal(-1);
        if (e.key === 'ArrowRight') openAdjacentAdoptedAnimal(1);
      });
    }

    window.addEventListener('popstate', () => {
      const wanted = requestedAdoptedId();
      if (!wanted) {
        if (modalIsOpen()) closeModal();
        return;
      }
      openRequestedAdoptedAnimal();
    });

    window.addEventListener("resize", () => { renderPage(); }, { passive: true });

    setYearOptions();
    qs("asm-ss-year").addEventListener("change", reload);

    reload();
  }

  init();
})();
</script>
    <?php
    return ob_get_clean();
  }
}

StraySafe_Adopted_UI_Shortcode::init();
