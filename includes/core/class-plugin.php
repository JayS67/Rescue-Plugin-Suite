<?php
if (!defined('ABSPATH')) exit;

final class Plugin_UI_Suite_Plugin {
  const SNAP_KEY = 'plugin_ui_suite_snapshots_v1';
  const LOG_KEY = 'plugin_ui_suite_version_log_v1';
  const WIZARD_KEY = 'plugin_ui_suite_needs_setup_v1';
  const OPT_KEY = 'plugin_ui_suite_settings_v83';
  const USER_DEFAULTS_KEY = 'plugin_suite_user_defaults_v1';
  const ANALYTICS_KEY = 'plugin_suite_analytics_v1';
  const WEBHOOK_AUDIT_KEY = 'plugin_suite_webhook_audit_v1';
  const WEBHOOK_CRON_HOOK = 'plugin_suite_retry_webhooks_cron';
  const SCHEMA_VERSION_KEY = 'plugin_ui_suite_schema_version';
  const SCHEMA_VERSION = PLUGIN_SUITE_SCHEMA_VERSION;

  public static function init() {
    self::include_modules();
    add_action('admin_init', [__CLASS__, 'ensure_defaults_loaded'], 5);
    add_action('admin_init', [__CLASS__, 'redirect_legacy_admin_urls'], 6);
    add_action('admin_init', [__CLASS__, 'maybe_redirect_to_setup_wizard'], 20);
    add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
    add_action('admin_menu', [__CLASS__, 'admin_menu'], 20);
    add_action('admin_menu', [__CLASS__, 'remove_legacy_admin_pages'], 999);
    add_filter('plugin_action_links', [__CLASS__, 'plugin_action_links'], 10, 2);
    add_filter('plugin_row_meta', [__CLASS__, 'plugin_row_meta'], 10, 2);
    add_action('wp', [__CLASS__, 'maybe_disable_cache_for_suite_pages']);
    add_action('admin_post_plugin_ui_suite_save', [__CLASS__, 'handle_save']);
    add_action('admin_post_plugin_ui_suite_preview', [__CLASS__, 'render_preview']);
    add_action('admin_post_plugin_ui_suite_export', [__CLASS__, 'handle_export']);
    add_action('admin_post_plugin_ui_suite_import', [__CLASS__, 'handle_import']);
    add_action('admin_post_plugin_ui_suite_export_module', [__CLASS__, 'handle_export_module']);
    add_action('admin_post_plugin_ui_suite_import_module', [__CLASS__, 'handle_import_module']);
    add_action('admin_post_plugin_ui_suite_save_pack', [__CLASS__, 'handle_save_pack']);
    add_action('admin_post_plugin_ui_suite_load_pack', [__CLASS__, 'handle_load_pack']);
    add_action('admin_post_plugin_ui_suite_reset_defaults', [__CLASS__, 'handle_reset_defaults']);
    add_action('admin_post_plugin_ui_suite_registry_export', [__CLASS__, 'handle_registry_export']);
    add_action('admin_post_plugin_ui_suite_registry_import', [__CLASS__, 'handle_registry_import']);
    add_action('admin_post_plugin_ui_suite_registry_reset', [__CLASS__, 'handle_registry_reset']);
    add_action('admin_post_plugin_ui_suite_save_current_defaults', [__CLASS__, 'handle_save_current_defaults']);
    add_action('admin_post_plugin_ui_suite_restore_snapshot', [__CLASS__, 'handle_restore_snapshot']);
    add_action('admin_post_plugin_ui_suite_delete_snapshot', [__CLASS__, 'handle_delete_snapshot']);
    add_action('admin_post_plugin_ui_suite_proxy_test', [__CLASS__, 'handle_proxy_test']);
    add_action('admin_post_plugin_ui_suite_proxy_clear_cache', [__CLASS__, 'handle_proxy_clear_cache']);
    add_action('admin_post_plugin_ui_suite_provider_diagnostics', [__CLASS__, 'handle_provider_diagnostics']);
    add_action('admin_post_plugin_ui_suite_setup_save', [__CLASS__, 'handle_setup_save']);
    add_action('admin_post_plugin_ui_suite_export_enquiries', [__CLASS__, 'handle_export_enquiries']);
    add_action('admin_post_plugin_ui_suite_test_enquiry_integration', [__CLASS__, 'handle_test_enquiry_integration']);
    add_action('admin_post_plugin_ui_suite_retry_webhooks', [__CLASS__, 'handle_retry_webhooks']);
    add_action('admin_post_plugin_ui_suite_download_diagnostics', [__CLASS__, 'handle_download_diagnostics']);
    add_action('admin_post_plugin_ui_suite_copy_system_information', [__CLASS__, 'handle_copy_system_information']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_block_editor_assets']);
    add_action(self::WEBHOOK_CRON_HOOK, [__CLASS__, 'process_webhook_retry_queue']);
    add_action('wp_footer', [__CLASS__, 'render_last_good_admin_notice'], 99);
    add_action('update_option_' . self::OPT_KEY, [__CLASS__, 'sync_legacy_options_from_db'], 10, 2);
    add_action('init', [__CLASS__, 'register_form_shortcodes'], 20);
    add_action('init', [__CLASS__, 'register_custom_ui_shortcodes'], 21);
    add_action('init', [__CLASS__, 'register_featured_shortcodes'], 22);
    add_action('init', [__CLASS__, 'register_quiz_shortcode'], 23);
    add_action('init', [__CLASS__, 'register_blocks'], 24);
    add_action('wp_ajax_plugin_suite_track', [__CLASS__, 'handle_track_event']);
    add_action('wp_ajax_nopriv_plugin_suite_track', [__CLASS__, 'handle_track_event']);
    self::ensure_webhook_retry_schedule();
    self::maybe_run_migrations();
    self::register_core_framework_metadata();
  }


  public static function plugin_file() {
    return plugin_basename(PLUGIN_SUITE_PATH . 'plugin-ui-suite.php');
  }

  private static function admin_page_url($tab = 'global') {
    $args = ['page' => 'plugin-ui-suite'];
    if ($tab !== '') $args['tab'] = $tab;
    return add_query_arg($args, admin_url('options-general.php'));
  }

  private static function help_centre_url() {
    return self::admin_page_url('help');
  }

  private static function support_url() {
    return self::github_url('issues/new');
  }

  public static function plugin_action_links($links, $plugin_file) {
    if ($plugin_file !== self::plugin_file() || !current_user_can('manage_options')) return $links;
    $action_links = [
      'settings' => '<a href="' . esc_url(self::admin_page_url('global')) . '">' . esc_html__('Settings', 'plugin-ui-suite') . '</a>',
      'help_centre' => '<a href="' . esc_url(self::help_centre_url()) . '">' . esc_html__('Help Centre', 'plugin-ui-suite') . '</a>',
    ];
    return array_merge($links, $action_links);
  }

  public static function plugin_row_meta($links, $plugin_file) {
    if ($plugin_file !== self::plugin_file() || !current_user_can('manage_options')) return $links;
    $links[] = '<a href="' . esc_url(self::help_centre_url()) . '">' . esc_html__('Documentation', 'plugin-ui-suite') . '</a>';
    $links[] = '<a href="' . esc_url(self::support_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Support', 'plugin-ui-suite') . '</a>';
    return $links;
  }

  public static function redirect_legacy_admin_urls() {
    if (empty($_GET['page'])) return;
    $page = sanitize_key(wp_unslash($_GET['page']));
    if ($page === 'plugin-ui-suite-updates') {
      wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'updates'], admin_url('options-general.php'))); exit;
    }
    if (strpos($page, 'plugin-ui-suite-payments-') === 0) {
      $section = substr($page, strlen('plugin-ui-suite-payments-'));
      if ($section === 'providers') {
        wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'data-source','subtab'=>'payment-stripe','plugin_msg'=>'Payment provider connections now live in Integrations. Choose the provider you need from this page.'], admin_url('options-general.php'))); exit;
      }
      $allowed = ['dashboard','general','widget','campaigns','gift-aid','fees','appearance','transactions','reports','diagnostics','help'];
      if (!in_array($section, $allowed, true)) $section = 'dashboard';
      wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'payments','payments_section'=>$section], admin_url('options-general.php'))); exit;
    }
    if ($page === 'plugin-ui-suite' && !empty($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'payments' && !empty($_GET['payments_section']) && sanitize_key(wp_unslash($_GET['payments_section'])) === 'providers') {
      wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'data-source','subtab'=>'payment-stripe','plugin_msg'=>'Payment provider connections now live in Integrations. Choose the provider you need from this page.'], admin_url('options-general.php'))); exit;
    }
    if ($page === 'plugin-ui-suite' && !empty($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'data-source' && !empty($_GET['subtab'])) {
      $legacy_subtab = sanitize_key(wp_unslash($_GET['subtab']));
      $map = ['provider'=>'asm','endpoints'=>'custom-api','authentication'=>'custom-api','field-mapping'=>'custom-api','cache'=>'asm','connection-test'=>'asm'];
      if (isset($map[$legacy_subtab])) { wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'data-source','subtab'=>$map[$legacy_subtab]], admin_url('options-general.php'))); exit; }
    }
    if ($page === 'plugin-ui-suite' && !empty($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'widgets') {
      wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'featured','plugin_msg'=>'Widgets has been retired. Featured Animal is now its own module.'], admin_url('options-general.php'))); exit;
    }
    if ($page === 'plugin-ui-suite' && !empty($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'layout') {
      wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'adoptables','subtab'=>'layout'], admin_url('options-general.php'))); exit;
    }
  }

  public static function activate() {
    $merged = self::merge_with_defaults(self::default_settings(), self::load_saved_settings());
    update_option(self::OPT_KEY, $merged, false);
    update_option(self::WIZARD_KEY, 1, false);
    self::record_version_event('activated');
    self::sync_legacy_options($merged);
    self::ensure_webhook_retry_schedule();
    if (class_exists('Plugin_UI_Suite_SEO')) Plugin_UI_Suite_SEO::rewrite_rules();
    flush_rewrite_rules(false);
  }


  private static function register_core_framework_metadata() {
    if (!class_exists('Plugin_UI_Suite_Registry')) return;
    foreach ([
      'global'=>['name'=>'General','description'=>'Shared site-wide display, cache and housekeeping settings.','icon'=>'admin-generic'],
      'proxy'=>['name'=>'Developer Tools','description'=>'Developer-only proxy, registry and diagnostics tools.','icon'=>'admin-tools','flags'=>['hidden'=>!Plugin_UI_Suite_Registry::developer_mode_enabled()]],
      'adoptables'=>['name'=>'Adoptables','description'=>'Public adoptable animal widget and layout settings.','icon'=>'pets'],
      'adopted'=>['name'=>'Adopted','description'=>'Adopted animal UI and layout settings.','icon'=>'heart'],
      'featured'=>['name'=>'Featured Animal','description'=>'Single featured animal embed for homepages and campaign pages.','icon'=>'star-filled'],
      'diagnostics'=>['name'=>'Diagnostics','description'=>'Plugin health centre, support information and environment checks.','icon'=>'sos'],
      'stats'=>['name'=>'Statistics','description'=>'Impact statistics display settings.','icon'=>'chart-bar'],
      'forms'=>['name'=>'Forms','description'=>'Application and enquiry form routing.','icon'=>'feedback'],
      'payments'=>['name'=>'Payments','description'=>'Donation behaviour, widget, campaigns, Gift Aid and reporting.','icon'=>'money-alt'],
    ] as $id=>$module) Plugin_UI_Suite_Registry::register_module($id, $module);
    foreach (self::default_settings() as $module=>$fields) {
      if (!is_array($fields)) continue;
      foreach ($fields as $field=>$default) {
        if (is_array($default)) continue;
        Plugin_UI_Suite_Registry::register_setting($module.'_'.$field, [
          'module'=>$module,'page'=>$module,'section'=>'general','field_id'=>$field,
          'label'=>ucwords(str_replace('_',' ',(string)$field)),'default'=>$default,
          'search_keywords'=>[$module, str_replace('_',' ',(string)$field)],
          'sensitive'=>preg_match('/(password|secret|token|api_key|key)$/', (string)$field) === 1,
        ]);
      }
    }
    foreach (['Donation Widget','Adoptables','Adopted','Featured Animal','Statistics','Forms','Diagnostics','Match Quiz'] as $panel) Plugin_UI_Suite_Registry::register('analytics', sanitize_title($panel), ['title'=>$panel,'module'=>sanitize_title($panel)]);
    foreach ([
      'Stripe'=>['url'=>'https://docs.stripe.com/keys','content'=>'Go to Stripe Dashboard → Developers → API keys. Copy the publishable key and secret key. Paste them into Integrations → Stripe, save, then use Diagnostics to confirm the connection. If a payment fails, check test mode and webhook signing secret first.'],
      'PayPal'=>['url'=>'https://developer.paypal.com/dashboard/applications/live','content'=>'Go to PayPal Developer Dashboard → Apps & Credentials. Create or open an app. Copy the Client ID and Secret. Paste them into Integrations → PayPal, save, then make a small sandbox test donation.'],
      'Square'=>['url'=>'https://developer.squareup.com/apps','content'=>'Go to Square Developer → Applications. Copy the Application ID, Access Token and Location ID. Paste them into Integrations → Square. Use sandbox mode until your checkout has been tested.'],
      'GoCardless'=>['url'=>'https://manage.gocardless.com/developers/access-tokens','content'=>'Go to GoCardless Dashboard → Developers → Access tokens. Create an access token, paste it into Integrations → GoCardless, then test a Direct Debit flow before enabling live donations.'],
      'SumUp'=>['url'=>'https://developer.sumup.com/','content'=>'Go to SumUp developer settings and copy the merchant/API details supplied for your account. Paste them into Integrations → SumUp and test with a small payment link before launch.'],
      'ASM'=>['url'=>'https://sheltermanager.com/site/en_asm3_help.html','content'=>'Use Integrations → ASM. Enter the ASM service URL, account, username and password together. Save, then click Test connection. If animals do not load, confirm the account name and user permissions in ASM.'],
      'Other supported rescue systems'=>['url'=>'','content'=>'Choose the matching rescue platform under Integrations. Only fill in the fields shown for that platform. If your platform uses hosted application forms, paste the hosted form link rather than expecting the suite to collect answers directly.'],
    ] as $guide=>$data) Plugin_UI_Suite_Registry::register('help', sanitize_title($guide), ['title'=>$guide,'keywords'=>[$guide,'setup','test','troubleshoot'],'external_url'=>$data['url'],'content'=>$data['content']]);
    foreach ([
      'organisation'=>'Tell the suite who you are and which rescue details appear in public displays.',
      'website'=>'Add the pages where visitors will adopt, donate, view adopted animals and contact you.',
      'rescue-management'=>'Choose your animal management platform and keep credentials beside that platform.',
      'integrations'=>'Connect ASM, custom APIs or other supported systems in one place.',
      'payment-provider'=>'Connect Stripe, PayPal, Square, GoCardless or SumUp in Integrations, then choose the default donation provider in Payments.',
      'campaigns'=>'Create your first appeal so donations can be tracked clearly.',
      'donation-widget'=>'Set the public donation wording, amounts and thank-you path.',
      'forms'=>'Add application or enquiry forms and explain platform limitations.',
      'featured-animal'=>'Choose a featured animal embed for your website.',
      'analytics'=>'Decide how visitor and donation analytics should be recorded.',
      'updates'=>'Check the installed version and choose whether automatic updates are enabled.',
      'finished'=>'Review the checklist and save your setup.',
    ] as $i=>$description) Plugin_UI_Suite_Registry::register('setup_steps', $i, ['module'=>'global','title'=>ucwords(str_replace('-',' ', $i)),'description'=>$description,'order'=>count(Plugin_UI_Suite_Registry::all('setup_steps'))+1]);
    foreach (['global'=>'General','data-source'=>'Integrations','adoptables'=>'Adoptables','adopted'=>'Adopted','featured'=>'Featured Animal','stats'=>'Statistics','payments'=>'Donations','quiz'=>'Match Quiz','forms'=>'Forms','diagnostics'=>'Diagnostics','updates'=>'Updates','help'=>'Help Centre'] as $i=>$label) Plugin_UI_Suite_Registry::register_navigation('tab_'.$i, ['context'=>'tab','slug'=>$i,'label'=>$label,'module'=>in_array($i, ['data-source','updates'], true) ? 'global' : $i,'order'=>10 + count(Plugin_UI_Suite_Registry::all('navigation'))]);
    if (Plugin_UI_Suite_Registry::developer_mode_enabled()) foreach (['proxy'=>'Developer Tools','registry'=>'Registry'] as $i=>$label) Plugin_UI_Suite_Registry::register_navigation('tab_'.$i, ['context'=>'tab','slug'=>$i,'label'=>$label,'module'=>'global','order'=>90 + count(Plugin_UI_Suite_Registry::all('navigation'))]);
    Plugin_UI_Suite_Registry::register_navigation('settings_root', ['context'=>'admin','slug'=>'plugin-ui-suite','label'=>'Rescue Plugin Suite','page_title'=>'Rescue Plugin Suite','menu_title'=>'Rescue Plugin Suite','capability'=>'manage_options','callback'=>[__CLASS__,'render_settings_page'],'order'=>10]);
  }

  private static function include_modules() {
    self::define_proxy_constants_from_settings();
    require_once PLUGIN_SUITE_PATH . 'includes/modules/forms/class-module.php';
    require_once PLUGIN_SUITE_PATH . 'includes/modules/adoptables/class-module.php';
    require_once PLUGIN_SUITE_PATH . 'includes/modules/adopted/class-module.php';
    require_once PLUGIN_SUITE_PATH . 'includes/modules/statistics/class-module.php';
    require_once PLUGIN_SUITE_PATH . 'includes/modules/asm-proxy/class-module.php';
    require_once PLUGIN_SUITE_PATH . 'includes/modules/payments/class-module.php';
  }

  public static function default_settings() {
    $defaults = [
      'global' => [
        'style_behavior' => 'original_ui_defaults',
        'cache_adoptables_seconds' => 60,
        'cache_adopted_seconds' => 120,
        'cache_stats_seconds' => 60,
        'bypass_plugin_cache' => 0,
        'delete_data_on_uninstall' => 0,
        'adoptables_page_url' => '',
        'adopted_page_url' => '',
        'brand_color' => '#401268',
        'background_color' => '#fcf5fd',
        'modal_divider_color' => '#d6d6d6',
        'text_primary_color' => '#374151',
        'text_muted_color' => '#6b7280',
        'paw_opacity' => '0.15',
        'paw_count' => 50,
        'font_family' => 'Roboto',
        'font_scale_percent' => 100,
        'adoptables_style_source' => 'auto',
        'adopted_style_source' => 'auto',
        'stats_style_source' => 'auto',
        'data_source' => 'asm',
        'custom_api_url' => '',
        'custom_api_key' => '',
        'custom_api_auth_header' => 'X-API-Key',
        'custom_api_adoptables_url' => '',
        'custom_api_adoptions_url' => '',
        'custom_api_report_url' => '',
        'custom_api_incare_url' => '',
        'custom_api_image_url' => '',
        'field_map' => '',
        'provider_profile' => '',
        'preview_mode' => 0,
        'enquiry_log_enabled' => 1,
        'enquiry_email' => '',
        'enquiry_webhook_url' => '',
        'enquiry_webhook_secret' => '',
        'analytics_consent_mode' => 'immediate',
        'analytics_consent_cookie' => '',
        'shelterluv_adoptables_url' => '',
        'shelterluv_adoptions_url' => '',
        'shelterluv_report_url' => '',
        'shelterluv_incare_url' => '',
        'shelterluv_image_url' => '',
        'shelterluv_api_key' => '',
        'shelterluv_base_url' => 'https://www.shelterluv.com',
        'shelterluv_org_id' => '',
        'shelterluv_statuses' => 'adoptable,foster',
        'shelterluv_location_ids' => '',
        'shelterluv_animal_type' => 'cat',
        'petpoint_username' => '',
        'petpoint_password' => '',
        'petpoint_base_url' => '',
        'petpoint_adoptables_url' => '',
        'petpoint_adoptions_url' => '',
        'petpoint_report_url' => '',
        'petpoint_incare_url' => '',
        'petpoint_image_url' => '',
        'petpoint_shelter_id' => '',
        'petpoint_location_ids' => '',
        'petpoint_species_id' => '2',
        'petpoint_statuses' => 'available,foster',
        'petpoint_adopted_report_id' => '',
        'supported_species' => 'cats,dogs,rabbits,birds,horses,small_animals,reptiles,other',
        'enabled_species' => 'cats',
      ],
      'adoptables' => [
        'brand_color' => '#401268', 'background_color' => '#fcf5fd', 'paw_opacity' => '0.15', 'paw_count' => 50,
        'title_text' => 'Meet Our Adoptable Animals', 'subtitle_text' => 'Longest in our care are shown first',
        'footer_text' => 'Every adoption changes a life. Thank you for supporting rescue work. 🐾',
        'loading_status_text' => 'Loading adoptable animals...', 'loading_page_label_text' => 'Loading...',
        'tips_text' => 'Tip: Click the dark background (outside the card) or press ESC to close.', 'font_family' => 'Roboto',
        'cats_only' => 1, 'show_top_navigation' => 1, 'cols_mobile' => 2, 'rows_mobile' => 3, 'cols_tablet' => 2, 'rows_tablet' => 3,
        'cols_desktop' => 4, 'rows_desktop' => 2, 'gap_x_mobile' => 20, 'gap_y_mobile' => 20, 'gap_x_tablet' => 24,
        'gap_y_tablet' => 24, 'gap_x_desktop' => 24, 'gap_y_desktop' => 24, 'card_scale_mobile' => 100,
        'card_scale_tablet' => 100, 'card_scale_desktop' => 100, 'card_padding' => 12, 'card_radius' => 16,
        'modal_max_width' => 896, 'fs_heading_mobile' => 28, 'fs_heading_tablet' => 36, 'fs_heading_desktop' => 42,
        'fs_subheading_mobile' => 16, 'fs_subheading_tablet' => 18, 'fs_subheading_desktop' => 20,
        'fs_footer_mobile' => 14, 'fs_footer_tablet' => 16, 'fs_footer_desktop' => 16,
        'fs_page_label_mobile' => 13, 'fs_page_label_tablet' => 13, 'fs_page_label_desktop' => 13,
        'fs_modal_name_mobile' => 18, 'fs_modal_name_tablet' => 20, 'fs_modal_name_desktop' => 20,
        'fs_modal_meta_mobile' => 14, 'fs_modal_meta_tablet' => 14, 'fs_modal_meta_desktop' => 14,
        'fs_modal_desc_mobile' => 16, 'fs_modal_desc_tablet' => 18, 'fs_modal_desc_desktop' => 18,
        'fs_tips_mobile' => 12, 'fs_tips_tablet' => 12, 'fs_tips_desktop' => 12,
        'fw_heading' => 800, 'fw_subheading' => 600, 'fw_footer' => 500, 'fw_page_label' => 600,
        'fw_modal_name' => 800, 'fw_modal_meta' => 600, 'fw_modal_desc' => 400, 'fw_tips' => 600,
        'modal_divider_color' => '#d6d6d6', 'modal_divider_thickness' => 2, 'modal_divider_radius' => 999,
        'modal_global_text' => "If you think this kitty could be the perfect match for you, please complete the adoption application form located below our adoptable cats.\n\nWe do not rehome on a first-come, first-served basis; instead, we carefully match each of our precious cats and kittens with the most suitable homes. Therefore, if you see the enquiries active label, rest assured you can still apply! 🐾✨\n\nAll of our cats are rehomed vaccinated, flea and worm treated, microchipped, neutered (if old enough), and with 5 weeks free pet insurance provided by Agria.\n\nTo ensure every cat goes to the right home, all applicants will require a homecheck and as always, adoption fees apply. ✅\n\nWith love,\nYour rescue team 🐾",
        'show_reservation_label' => 1, 'show_pending_reservation_label' => 1, 'show_other_reservation_label' => 1, 'reservation_pending_label' => 'Pending Adoption', 'reservation_active_label' => 'Enquiries Active',
        'reservation_label_halign' => 'left', 'reservation_label_valign' => 'top',
        'card_border_enabled' => 1, 'card_border_color' => '#401268', 'card_border_weight' => 2,
        'enable_deep_links' => 1, 'share_button_text' => 'Share', 'share_copied_text' => 'Link copied',
        'enable_apply_button' => 1, 'apply_button_text' => 'Apply', 'apply_form_shortcode' => 'adoption_form',
        'apply_button_bg_color' => '#401268', 'apply_button_text_color' => '#ffffff', 'apply_button_border_color' => '#401268', 'apply_button_radius' => 16,
        'modal_contact_url' => '',
        'custom_button_1_enabled' => 0, 'custom_button_1_text' => '', 'custom_button_1_url' => '', 'custom_button_1_new_tab' => 0, 'custom_button_1_style' => 'primary',
        'custom_button_2_enabled' => 0, 'custom_button_2_text' => '', 'custom_button_2_url' => '', 'custom_button_2_new_tab' => 0, 'custom_button_2_style' => 'secondary',
        'custom_button_3_enabled' => 0, 'custom_button_3_text' => '', 'custom_button_3_url' => '', 'custom_button_3_new_tab' => 0, 'custom_button_3_style' => 'outline',
        'enable_filters' => 1, 'enable_filter_age' => 1, 'enable_filter_sex' => 1, 'enable_filter_breed' => 1, 'enable_exclude_pending_filter' => 1,
        'filter_age_label' => 'Age', 'filter_sex_label' => 'Sex', 'filter_breed_label' => 'Breed', 'filter_exclude_pending_label' => 'Hide animals pending adoption',
        'fallback_description' => 'More information about this animal will be added soon. Please contact the rescue for details.',
        'detect_bonded_from_description' => 1, 'bonded_label_text' => 'Bonded Pair', 'bonded_label_bg_color' => '#ec4899', 'bonded_label_text_color' => '#ffffff',
        'detect_indoor_only_from_description' => 1, 'indoor_only_label_text' => 'Indoor Only', 'indoor_only_label_bg_color' => '#0f766e', 'indoor_only_label_text_color' => '#ffffff',
        'enable_modal_slideshow_controls' => 1,
        'enable_favourites' => 1, 'favourites_label_text' => 'Favourites', 'show_only_favourites_label' => 'Show favourites only', 'favourite_button_position' => 'top_left',
        'enable_compare_tool' => 1, 'compare_button_text' => 'Compare favourites', 'favourites_modal_title' => 'Saved favourites', 'compare_modal_title' => 'Compare favourites',
        'enable_modals' => 1,
        'display_style' => 'classic',
        'builder_card_order' => "image\nreservation_badge\nname_meta\nbreed_line\nfavourite_button",
        'builder_modal_order' => "gallery\nbadges\ninfo_cards\ntips\ndescription\nglobal_text\ncontact_footer\ncustom_buttons",
        'builder_header_actions' => "apply\nfavourite\nshare\nclose",
      ],
      'adopted' => [
        'brand_color' => '#401268', 'background_color' => '#fcf5fd', 'text_primary_color' => '#374151', 'text_muted_color' => '#6b7280',
        'paw_opacity' => '0.15', 'paw_count' => 50, 'title_text' => 'Found My Furever Home',
        'subtitle_text' => 'Recently adopted cats - the newest happy endings first',
        'footer_text' => 'Want to be part of the next happy ending? Consider adopting or fostering 🐾',
        'font_family' => '', 'min_year' => 2025, 'show_top_navigation' => 1, 'cols_mobile' => 2, 'rows_mobile' => 3, 'cols_tablet' => 3, 'rows_tablet' => 3,
        'cols_desktop' => 4, 'rows_desktop' => 2, 'card_scale_mobile' => 100, 'card_scale_tablet' => 100, 'card_scale_desktop' => 100,
        'card_radius' => 16, 'card_padding' => 12, 'button_radius' => 16,
        'card_border_enabled' => 1, 'card_border_color' => '#401268', 'card_border_weight' => 2, 'date_label_halign' => 'right', 'date_label_valign' => 'top',
        'fs_heading_mobile' => 30, 'fs_heading_tablet' => 36, 'fs_heading_desktop' => 42,
        'fs_subheading_mobile' => 16, 'fs_subheading_tablet' => 18, 'fs_subheading_desktop' => 20,
        'fs_footer_mobile' => 14, 'fs_footer_tablet' => 16, 'fs_footer_desktop' => 16,
        'fs_page_label_mobile' => 13, 'fs_page_label_tablet' => 13, 'fs_page_label_desktop' => 13,
        'fs_card_name_mobile' => 13, 'fs_card_name_tablet' => 16, 'fs_card_name_desktop' => 18,
        'fs_card_meta_mobile' => 11, 'fs_card_meta_tablet' => 14, 'fs_card_meta_desktop' => 14,
        'fs_badge_mobile' => 10, 'fs_badge_tablet' => 12, 'fs_badge_desktop' => 16,
        'fw_heading' => 800, 'fw_subheading' => 600, 'fw_footer' => 500, 'fw_page_label' => 600,
        'fw_card_name' => 800, 'fw_card_meta' => 600, 'fw_badge' => 800,
        'enable_modals' => 1, 'enable_deep_links' => 1, 'share_button_text' => 'Share', 'share_copied_text' => 'Link copied',
        'modal_global_text' => 'Every adoption changes a life. Thank you for supporting rescue work. 🐾',
        'adoptables_cta_enabled' => 0,
        'adoptables_cta_text' => 'Looking for your next best friend?',
        'adoptables_cta_button_text' => 'View animals for adoption',
        'adoptables_cta_url' => '',
        'builder_card_order' => "image\ndate_badge\nname_meta\nstory_excerpt\nshare_button",
        'builder_modal_order' => "gallery\nname_meta\nadoption_date\nstory\nglobal_text\nshare_button",
      ],
      'stats' => [
        'brand_color' => '#401268', 'background_color' => '#fcf5fd', 'min_year' => 2026, 'paw_opacity' => '0.15', 'paw_count' => 50,
        'title_text' => 'Our Rescue Impact', 'year_label_prefix' => "This Year's Statistics -",
        'footer_text' => 'Every number represents a life changed. Thank you for supporting rescue work. 🐾', 'font_family' => '',
        'card_radius' => 16, 'card_padding' => 24, 'card_border_enabled' => 1, 'card_border_color' => '#401268', 'card_border_weight' => 2, 'layout_mode' => 'one_row', 'cols_mobile' => 2, 'rows_mobile' => 3,
        'cols_tablet' => 2, 'rows_tablet' => 3, 'cols_desktop' => 6, 'rows_desktop' => 1,
        'fs_heading_mobile' => 28, 'fs_heading_tablet' => 36, 'fs_heading_desktop' => 42,
        'fs_subheading_mobile' => 16, 'fs_subheading_tablet' => 18, 'fs_subheading_desktop' => 20,
        'fs_paragraph_mobile' => 14, 'fs_paragraph_tablet' => 16, 'fs_paragraph_desktop' => 16,
        'card_w_mobile' => 0, 'card_w_tablet' => 0, 'card_w_desktop' => 0, 'card_h_mobile' => 0, 'card_h_tablet' => 0, 'card_h_desktop' => 0,
        'label_brought' => 'Brought In', 'caption_brought' => 'Found their way to us', 'icon_brought' => 'heart',
        'label_adopted' => 'Adopted', 'caption_adopted' => 'Furever homes found', 'icon_adopted' => 'home',
        'label_vaccinated' => 'Vaccinated', 'caption_vaccinated' => 'Protected & healthy', 'icon_vaccinated' => 'syringe',
        'label_neutered' => 'Neutered', 'caption_neutered' => 'Spay & neuter care', 'icon_neutered' => 'stethoscope',
        'label_chipped' => 'Microchipped', 'caption_chipped' => 'Always traceable', 'icon_chipped' => 'map_pin',
        'label_in_care' => 'In Our Care', 'caption_in_care' => 'Currently safe with us', 'icon_in_care' => 'shield',
        'card_order' => "brought\nadopted\nvaccinated\nneutered\nchipped\nin_care",
        'builder_card_order' => "brought\nadopted\nvaccinated\nneutered\nchipped\nin_care",
      ],
      'shortcodes' => ['adoptables' => 'adoptables', 'adopted' => 'adopted', 'statistics' => 'stats'],
      'featured' => [
        'shortcode' => 'featured_animal',
        'enabled' => 1,
        'mode' => 'random',
        'manual_id' => '',
        'title_text' => 'Featured animal',
        'subtitle_text' => 'Meet one of the animals looking for a home',
        'button_text' => 'View animal',
        'layout_order' => "image\ntitle\nmeta\nbutton",
      ],
      'quiz' => [
        'quiz_shortcode' => 'adoption_match_quiz',
        'quiz_enabled' => 1,
        'quiz_title_text' => 'Find your match',
        'quiz_intro_text' => 'Answer a few quick questions and we will suggest adoptable animals that may suit your home.',
        'q1_text' => 'What age would you prefer?',
        'q1_kitten_label' => 'Under 1 year',
        'q1_adult_label' => '1 to 3 years',
        'q1_senior_label' => '5+ years',
        'q1_either_label' => 'No preference',
        'q2_text' => 'Do you have a preference for sex?',
        'q2_female_label' => 'Female',
        'q2_male_label' => 'Male',
        'q2_either_label' => 'No preference',
        'q3_text' => 'Would you prefer an indoor-only cat?',
        'q3_yes_label' => 'Yes',
        'q3_no_label' => 'No preference',
        'q3_hide' => 0,
        'results_title_text' => 'Suggested matches',
        'results_empty_text' => 'No exact matches were found. Please browse all adoptables instead.',
        'age_categories' => "Under 1 year|0|1\n1 to 3 years|1|3\n3 to 5 years|3|5\n5+ years|5|",
        'question_order' => "age\nsex\nindoor\nbonded\ngood_cats\ngood_dogs\ngood_children",
        'answer_mappings' => "age|ANIMALAGE|10\nsex|SEXNAME|8\nindoor|ANIMALCOMMENTS|6\nbonded|ANIMALCOMMENTS|4\ngood_cats|GOODWITHCATS|5\ngood_dogs|GOODWITHDOGS|5\ngood_children|GOODWITHCHILDREN|5"
      ],
      'forms' => [
        'account' => 'plugin',
        'items' => [
          ['shortcode' => 'adoption_form', 'form_id' => '59'],
          ['shortcode' => 'volunteer_form', 'form_id' => '104'],
          ['shortcode' => 'waiting_list_form', 'form_id' => '106'],
          ['shortcode' => 'lost_cat_form', 'form_id' => '51'],
        ],
        'layout_order' => "intro\nform_embed\nprivacy_note\nsubmit_guidance",
        'platform_support_notes' => "asm|Embedded ASM forms are supported. The suite tracks application intent only.\ncustom_api|Custom API form submission requires a compatible endpoint.\nshelterluv|Use hosted Shelterluv application links where available.\npetpoint|Use hosted PetPoint application links where available.",
      ],
      'proxy' => [
        'base_url' => 'https://service.sheltermanager.com/asmservice',
        'account' => '',
        'username' => '',
        'password' => '',
        'cache_adoptables_seconds' => 60,
        'cache_reports_seconds' => 60,
        'cache_incare_seconds' => 60,
        'cache_adoptions_seconds' => 300,
      ],
    ];
    $saved_defaults = get_option(self::USER_DEFAULTS_KEY, []);
    if (is_array($saved_defaults) && !empty($saved_defaults)) {
      $defaults = self::merge_with_defaults($defaults, $saved_defaults);
    }
    return $defaults;
  }


  public static function maybe_run_migrations() {
    $current = get_option(self::SCHEMA_VERSION_KEY, '0.0.0');
    if (version_compare($current, self::SCHEMA_VERSION, '>=')) return;
    $lock = 'plugin_ui_suite_migration_lock';
    if (get_transient($lock)) return;
    set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);
    $target = $current;
    if (version_compare($target, '1.1.0', '<')) {
      add_option(self::SCHEMA_VERSION_KEY, '1.1.0', '', false);
      $target = '1.1.0';
    }
    if (version_compare($target, '1.2.0', '<')) {
      $settings = self::get_settings();
      if (!isset($settings['global']['delete_data_on_uninstall'])) {
        $settings['global']['delete_data_on_uninstall'] = 0;
        update_option(self::OPT_KEY, $settings, false);
      }
      $target = '1.2.0';
    }
    update_option(self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION, false);
    delete_transient($lock);
  }

  private static function candidate_option_keys() {
    $legacy_brand = 'stray' . 'safe';
    return [
      self::OPT_KEY,
      $legacy_brand . '_ui_suite_settings_v83',
    ];
  }

  private static function load_saved_settings() {
    foreach (self::candidate_option_keys() as $key) {
      $value = get_option($key, null);
      if (is_array($value) && !empty($value)) return $value;
    }
    return [];
  }

  private static function merge_with_defaults($defaults, $saved) {
    if (!is_array($defaults)) return $saved;
    $merged = $defaults;
    if (!is_array($saved)) return $merged;
    foreach ($defaults as $key => $default_value) {
      if (!array_key_exists($key, $saved)) continue;
      $saved_value = $saved[$key];
      if (is_array($default_value)) {
        $merged[$key] = self::merge_with_defaults($default_value, is_array($saved_value) ? $saved_value : []);
        continue;
      }
      if ($saved_value === null) continue;
      if (is_string($saved_value) && trim($saved_value) === '' && !(is_string($default_value) && $default_value === '')) continue;
      $merged[$key] = $saved_value;
    }
    return $merged;
  }

  public static function ensure_defaults_loaded() {
    if (!current_user_can('manage_options')) return;
    $defaults = self::default_settings();
    $saved = self::load_saved_settings();
    $merged = self::merge_with_defaults($defaults, $saved);
    if ($saved !== $merged) update_option(self::OPT_KEY, $merged, false);
    self::sync_legacy_options($merged);
  }

  public static function get_settings() {
    $settings = self::merge_with_defaults(self::default_settings(), self::load_saved_settings());
    $saved = self::load_saved_settings();
    if (isset($saved['widgets']) && is_array($saved['widgets'])) {
      $settings['featured'] = self::featured_settings(['featured'=>$settings['featured'] ?? [], 'widgets'=>$saved['widgets']]);
    }
    return $settings;
  }

  private static function sanitize_shortcode_tag($value) {
    $value = strtolower((string)$value);
    $value = preg_replace('/[^a-z0-9_\-]/', '', $value);
    return trim($value, '_-');
  }

  private static function default_forms_items() { return self::default_settings()['forms']['items']; }

  public static function get_forms() {
    $settings = self::get_settings();
    $items = isset($settings['forms']['items']) && is_array($settings['forms']['items']) ? $settings['forms']['items'] : [];
    $forms = [];
    foreach ($items as $item) {
      $shortcode = self::sanitize_shortcode_tag($item['shortcode'] ?? '');
      $form_id = preg_replace('/[^0-9]/', '', (string)($item['form_id'] ?? ''));
      if ($shortcode && $form_id) $forms[$shortcode] = $form_id;
    }
    return $forms;
  }


  private static function provider_profile_templates() {
    return [
      '' => ['label' => 'Custom / no template', 'map' => ''],
      'generic_pet_json' => ['label' => 'Generic pet JSON', 'map' => "ANIMALID=id,animal_id,pet_id\nANIMALNAME=name,pet_name\nCODE=code,reference,shelter_code\nANIMALAGE=age,age_text\nSEXNAME=sex,gender\nBREEDNAME=breed,primary_breed\nSPECIESNAME=species,type\nWEBSITEIMAGECOUNT=image_count,photo_count\nANIMALCOMMENTS=bio,description,story"],
      'shelterluv_basic' => ['label' => 'Shelterluv-style animals', 'map' => "ANIMALID=id,animal_id\nANIMALNAME=name\nCODE=internal_id,external_id,code\nANIMALAGE=age\nSEXNAME=sex\nBREEDNAME=breed,primary_breed\nSPECIESNAME=species,animal_type\nANIMALCOMMENTS=description,bio,notes"],
      'petpoint_basic' => ['label' => 'PetPoint-style animals', 'map' => "ANIMALID=AnimalID,PetID,id\nANIMALNAME=AnimalName,PetName,name\nCODE=AnimalCode,ReferenceNumber,code\nANIMALAGE=Age,animal_age\nSEXNAME=Sex,Gender\nBREEDNAME=PrimaryBreed,Breed\nSPECIESNAME=Species\nANIMALCOMMENTS=Description,WebMemo,PetMemo"],
    ];
  }


  public static function enqueue_admin_assets($hook) {
    if ($hook !== 'settings_page_plugin-ui-suite') return;
    wp_enqueue_script('plugin-suite-admin-help', PLUGIN_SUITE_URL . 'assets/js/admin-help.js', [], PLUGIN_SUITE_VERSION, true);
    wp_enqueue_script('plugin-suite-admin-sortable', PLUGIN_SUITE_URL . 'assets/js/admin-sortable.js', [], PLUGIN_SUITE_VERSION, true);
  }

  public static function enqueue_block_editor_assets() {
    if (!function_exists('wp_enqueue_script')) return;
    wp_enqueue_script('plugin-suite-block-preview', PLUGIN_SUITE_URL . 'assets/js/block-preview.js', ['wp-blocks','wp-element','wp-components','wp-block-editor','wp-server-side-render'], PLUGIN_SUITE_VERSION, true);
  }

  private static function ensure_webhook_retry_schedule() {
    if (function_exists('wp_next_scheduled') && !wp_next_scheduled(self::WEBHOOK_CRON_HOOK)) wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::WEBHOOK_CRON_HOOK);
  }

  public static function register_blocks() {
    if (!function_exists('register_block_type')) return;
    $blocks = [
      'asm-suite/adoptables' => ['title' => 'ASM Suite Adoptables', 'shortcode' => 'adoptables'],
      'asm-suite/adopted' => ['title' => 'ASM Suite Adopted', 'shortcode' => 'adopted'],
      'asm-suite/statistics' => ['title' => 'ASM Suite Statistics', 'shortcode' => 'stats'],
      'asm-suite/featured-animal' => ['title' => 'ASM Suite Featured Animal', 'shortcode' => 'featured_animal'],
      'asm-suite/adoption-form' => ['title' => 'ASM Suite Adoption Form', 'shortcode' => 'adoption_form'],
    ];
    foreach ($blocks as $name => $meta) {
      register_block_type($name, [
        'api_version' => 2,
        'title' => $meta['title'],
        'category' => 'widgets',
        'attributes' => ['source' => ['type'=>'string'], 'layout' => ['type'=>'string'], 'filters' => ['type'=>'boolean'], 'style' => ['type'=>'string']],
        'render_callback' => function($attrs = []) use ($meta) { $pairs = ''; foreach (['source','layout','style'] as $key) { if (!empty($attrs[$key])) $pairs .= ' ' . $key . '="' . esc_attr($attrs[$key]) . '"'; } if (array_key_exists('filters', (array)$attrs)) $pairs .= ' filters="' . (!empty($attrs['filters']) ? '1' : '0') . '"'; return do_shortcode('[' . $meta['shortcode'] . $pairs . ']'); },
      ]);
    }
  }

  private static function last_good_feed_summary() {
    if (!function_exists('plugin_suite_last_good_meta')) return [];
    $settings = self::get_settings();
    $source = sanitize_key($settings['global']['data_source'] ?? 'asm');
    $g = $settings['global'] ?? [];
    $rows = [];
    if ($source === 'custom_api') {
      $cfg = ['adoptables_url' => $g['custom_api_adoptables_url'] ?: (!empty($g['custom_api_url']) ? untrailingslashit($g['custom_api_url']) . '/adoptables' : ''), 'adoptions_url' => $g['custom_api_adoptions_url'] ?: (!empty($g['custom_api_url']) ? untrailingslashit($g['custom_api_url']) . '/adoptions' : '')];
      $rows['Custom API adoptables'] = plugin_suite_last_good_meta('plugin_custom_api_adoptables_v2_' . md5($cfg['adoptables_url'] . '|' . wp_json_encode(!empty($g['custom_api_key']) ? ['api_key'=>$g['custom_api_key']] : [])));
      $rows['Custom API adoptions'] = plugin_suite_last_good_meta('plugin_custom_api_adoptions_v2_' . md5($cfg['adoptions_url'] . '|' . wp_json_encode(!empty($g['custom_api_key']) ? ['api_key'=>$g['custom_api_key']] : [])));
    } elseif (in_array($source, ['shelterluv','petpoint'], true)) {
      $rows[ucfirst($source) . ' adoptables'] = ['time' => 'Stored per endpoint query', 'count' => 0];
      $rows[ucfirst($source) . ' adoptions'] = ['time' => 'Stored per endpoint query', 'count' => 0];
    }
    return $rows;
  }

  public static function register_form_shortcodes() {
    foreach (self::get_forms() as $shortcode => $form_id) {
      add_shortcode($shortcode, function() use ($form_id) { return Plugin_UI_Suite_Plugin::render_form_shortcode($form_id); });
    }
  }

  public static function render_form_shortcode($form_id) {
    $settings = self::get_settings();
    $account = sanitize_text_field($settings['forms']['account'] ?? ($settings['proxy']['account'] ?? ''));
    $form_id = preg_replace('/[^0-9]/', '', (string)$form_id);
    if (!$account || !$form_id) return '<p>Form could not be loaded.</p>';
    $script_url = esc_url('https://service.sheltermanager.com/asmservice?account=' . rawurlencode($account) . '&method=online_form_js&formid=' . rawurlencode($form_id));
    ob_start(); ?>
    <section class="rescue-suite-form-wrap" aria-label="Online application form"><noscript><p>This application form requires JavaScript. Please contact the rescue if you need help applying.</p></noscript><div id="asm3-onlineform" aria-live="polite"></div><script type="text/javascript" src="<?php echo esc_url($script_url); ?>"></script></section>
    <?php return ob_get_clean();
  }

  public static function register_custom_ui_shortcodes() {
    $settings = self::get_settings();
    $map = $settings['shortcodes'] ?? [];
    $a = self::sanitize_shortcode_tag($map['adoptables'] ?? 'adoptables');
    $b = self::sanitize_shortcode_tag($map['adopted'] ?? 'adopted');
    $c = self::sanitize_shortcode_tag($map['statistics'] ?? 'stats');
    if ($a && class_exists('Plugin_Adoptables_UI_Shortcode') && $a !== Plugin_Adoptables_UI_Shortcode::SHORTCODE) add_shortcode($a, ['Plugin_Adoptables_UI_Shortcode', 'render_shortcode']);
    if ($b && class_exists('Plugin_Adopted_UI_Shortcode') && $b !== Plugin_Adopted_UI_Shortcode::SHORTCODE) add_shortcode($b, ['Plugin_Adopted_UI_Shortcode', 'render_shortcode']);
    if ($c && $c !== 'stats') add_shortcode($c, function(){ return do_shortcode('[stats]'); });
  }

  public static function register_featured_shortcodes() {
    $settings = self::get_settings();
    $featured = self::sanitize_shortcode_tag($settings['featured']['shortcode'] ?? ($settings['widgets']['featured_shortcode'] ?? 'featured_animal'));
    if ($featured) add_shortcode($featured, [__CLASS__, 'render_featured_shortcode']);
  }

  public static function register_quiz_shortcode() {
    $settings = self::get_settings();
    $tag = self::sanitize_shortcode_tag($settings['quiz']['quiz_shortcode'] ?? 'adoption_match_quiz');
    if ($tag) add_shortcode($tag, [__CLASS__, 'render_quiz_shortcode']);
  }

  public static function render_quiz_shortcode($atts = []) {
    $settings = self::get_settings();
    $q = $settings['quiz'] ?? [];
    if (empty($q['quiz_enabled'])) return '';
    $adoptables_page_url = esc_url_raw($settings['global']['adoptables_page_url'] ?? '');
    ob_start(); ?>
    <section class="asm-adoption-quiz-widget" aria-labelledby="asm-quiz-title" style="--asm-quiz-brand:<?php echo esc_attr($settings['adoptables']['brand_color'] ?? '#401268'); ?>; --asm-quiz-bg:<?php echo esc_attr($settings['adoptables']['background_color'] ?? '#fcf5fd'); ?>; background:var(--asm-quiz-bg); border:none; border-radius:24px; padding:20px; box-shadow:0 12px 28px rgba(15,23,42,.08);">
      <h2 id="asm-quiz-title" style="margin:0;color:var(--asm-quiz-brand);font-size:2rem;line-height:1.15;"><?php echo esc_html($q['quiz_title_text'] ?? 'Find your match'); ?></h2>
      <p style="margin:.5rem 0 1.25rem;color:#6b7280;"><?php echo esc_html($q['quiz_intro_text'] ?? 'Answer a few quick questions and we will suggest adoptable animals that may suit your home.'); ?></p>
      <form id="asm-quiz-questions" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;"><?php echo esc_html($q['q1_text'] ?? 'What age would you prefer?'); ?></span><select id="asm-quiz-age" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either"><?php echo esc_html($q['q1_either_label'] ?? 'No preference'); ?></option><option value="Under 1 year">Under 1 year</option><option value="1 to 3 years">1 to 3 years</option><option value="3 to 5 years">3 to 5 years</option><option value="5+ years">5+ years</option></select></label>
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;"><?php echo esc_html($q['q2_text'] ?? 'Do you have a preference for sex?'); ?></span><select id="asm-quiz-sex" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either"><?php echo esc_html($q['q2_either_label'] ?? 'No preference'); ?></option><option value="Female"><?php echo esc_html($q['q2_female_label'] ?? 'Female'); ?></option><option value="Male"><?php echo esc_html($q['q2_male_label'] ?? 'Male'); ?></option></select></label>
        <?php if (empty($q['q3_hide'])): ?>
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;"><?php echo esc_html($q['q3_text'] ?? 'Would you prefer an indoor-only cat?'); ?></span><select id="asm-quiz-indoor" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either"><?php echo esc_html($q['q3_no_label'] ?? 'No preference'); ?></option><option value="yes"><?php echo esc_html($q['q3_yes_label'] ?? 'Yes'); ?></option><option value="no">No</option></select></label>
        <?php endif; ?>
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;">Bonded pair</span><select id="asm-quiz-bonded" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either">No preference</option><option value="yes">Yes</option><option value="no">No</option></select></label>
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;">Good with cats</span><select id="asm-quiz-good-cats" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either">No preference</option><option value="Yes">Yes</option><option value="No">No</option><option value="Unknown">Unknown</option><option value="Selective">Selective</option></select></label>
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;">Good with dogs</span><select id="asm-quiz-good-dogs" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either">No preference</option><option value="Yes">Yes</option><option value="No">No</option><option value="Unknown">Unknown</option><option value="Selective">Selective</option></select></label>
        <label style="display:block;"><span style="display:block;font-weight:700;color:#374151;margin-bottom:.35rem;">Good with children</span><select id="asm-quiz-good-children" style="width:100%;padding:10px 12px;border-radius:14px;border:1px solid #d1d5db;"><option value="either">No preference</option><option value="Yes">Yes</option><option value="No">No</option><option value="Unknown">Unknown</option><option value="Over 5">Over 5</option><option value="Over 12">Over 12</option></select></label>
        <div><button id="asm-quiz-run" type="button" style="display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:999px;background:var(--asm-quiz-brand);color:#fff;border:none;font-weight:700;cursor:pointer;">Show matches</button></div>
      </form>
      <div style="margin-top:1.25rem;"><h3 style="margin:0 0 .75rem;color:#111827;font-size:1.25rem;"><?php echo esc_html($q['results_title_text'] ?? 'Suggested matches'); ?></h3><div id="asm-quiz-results" role="status" aria-live="polite" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;"></div></div>
    </section>
    <script>(function(){
	      const root=document.currentScript.previousElementSibling; if(!root) return;
	      const endpoint=<?php echo wp_json_encode(rest_url('plugin/v1/adoptables')); ?>;
	      const adoptablesPageUrl=<?php echo wp_json_encode($adoptables_page_url); ?>;
	      let animals=[];
	      function safe(v){ return String(v ?? '').trim(); }
	      function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
	      function escAttr(s){ return escHtml(s).replace(/"/g,String.fromCharCode(38,113,117,111,116,59)).replace(/'/g,String.fromCharCode(38,35,48,51,57,59)); }
	      function slugifyPart(value){ return String(value || '').toLowerCase().replace(/&/g,' and ').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-'); }
	      function catModalSlug(a){ const id=String(a.ID ?? a.ANIMALID ?? a.AnimalID ?? '').replace(/\D+/g,''); const name=slugifyPart(a.ANIMALNAME ?? a.AnimalName ?? ''); const code=slugifyPart(a.CODE ?? a.SHELTERCODE ?? a.ShelterCode ?? ''); return [name, code].filter(Boolean).join('-') || id; }
	      function modalLink(a){ const href=adoptablesPageUrl || window.location.href; try { const url=new URL(href, window.location.origin); url.searchParams.set('cat', catModalSlug(a)); return url.toString(); } catch(e) { const sep=String(href).includes('?') ? '&' : '?'; return String(href) + sep + 'cat=' + encodeURIComponent(catModalSlug(a)); } }
	      function descText(a){ return safe(a.ANIMALCOMMENTS ?? a.WEBSITEMEDIANOTES ?? a.DESCRIPTION ?? a.ANIMALDESCRIPTION).toLowerCase(); }
      function sexValue(a){ const raw=safe(a.SEXNAME ?? a.SexName ?? a.SEX); if(/^f/i.test(raw) || raw==='0') return 'Female'; if(/^m/i.test(raw) || raw==='1') return 'Male'; return raw; }
      function ageMonths(a){
        const direct=Number(a.AGE_MONTHS ?? a.age_months ?? a.AgeMonths);
        if (Number.isFinite(direct) && direct >= 0) return direct;
        const age=safe(a.ANIMALAGE ?? a.AnimalAge).toLowerCase(); if(!age) return null;
        let total=0, matched=false;
        const y=age.match(/(\d+(?:\.\d+)?)\s*year/); const m=age.match(/(\d+(?:\.\d+)?)\s*month/); const w=age.match(/(\d+(?:\.\d+)?)\s*week/);
        if(y){ total += parseFloat(y[1]) * 12; matched=true; }
        if(m){ total += parseFloat(m[1]); matched=true; }
        if(w){ total += parseFloat(w[1]) / 4.345; matched=true; }
        return matched ? Math.max(0, Math.round(total)) : null;
      }
      function ageBand(a){
        const band=safe(a.AGE_BAND ?? a.age_band ?? a.AgeBand); if (band) return band;
        const months=ageMonths(a); if (months===null) return '';
        if (months < 12) return 'Under 1 year';
        if (months < 36) return '1 to 3 years';
        if (months < 60) return '3 to 5 years';
        return '5+ years';
      }
      function hasIndoor(a){ return /\bindoor\b/i.test(descText(a)); }
      function hasBonded(a){ return /bonded with/i.test(descText(a)); }
      function normaliseChoice(v, allowed){ const raw=safe(v); if (!raw) return 'Unknown'; const hit=allowed.find(x => x.toLowerCase()===raw.toLowerCase()); return hit || raw; }
      function pickVal(a, keys){ for (const key of keys){ const val = a?.[key]; if (val !== undefined && val !== null && safe(val) !== '') return safe(val); } return ''; }
      function goodWithCats(a){ return normaliseChoice(pickVal(a,['ISGOODWITHCATSNAME','GoodWithCatsName','good_with_cats_name','GOODWITHCATSNAME','GOODWITHCATS','GoodWithCats','good_with_cats','GOOD_WITH_CATS']), ['Yes','No','Unknown','Selective']); }
      function goodWithDogs(a){ return normaliseChoice(pickVal(a,['ISGOODWITHDOGSNAME','GoodWithDogsName','good_with_dogs_name','GOODWITHDOGSNAME','GOODWITHDOGS','GoodWithDogs','good_with_dogs','GOOD_WITH_DOGS']), ['Yes','No','Unknown','Selective']); }
      function goodWithChildren(a){ return normaliseChoice(pickVal(a,['ISGOODWITHCHILDRENNAME','GoodWithChildrenName','good_with_children_name','GOODWITHCHILDRENNAME','GOODWITHCHILDREN','GoodWithChildren','good_with_children','GOOD_WITH_CHILDREN']), ['Yes','No','Unknown','Over 5','Over 12']); }
      function hasPending(a){ return String(a.primary_reservation_status ?? '').toLowerCase()==='pending adoption'; }
      function img(a){ const id=String(a.ID ?? a.ANIMALID ?? a.AnimalID ?? ''); return <?php echo wp_json_encode(rest_url('plugin/v1/animal-image')); ?> + '?animalid=' + encodeURIComponent(id) + '&seq=1'; }
	      function matchesAll(a,opts){ return (opts.age==='either' || ageBand(a)===opts.age) && (opts.sex==='either' || sexValue(a)===opts.sex) && (opts.indoor==='either' || (opts.indoor==='yes' ? hasIndoor(a) : !hasIndoor(a))) && (opts.bonded==='either' || (opts.bonded==='yes' ? hasBonded(a) : !hasBonded(a))) && (opts.goodCats==='either' || goodWithCats(a)===opts.goodCats) && (opts.goodDogs==='either' || goodWithDogs(a)===opts.goodDogs) && (opts.goodChildren==='either' || goodWithChildren(a)===opts.goodChildren); }
      function scoreAnimal(a, opts){
        let score=0;
        if(opts.age==='either' || ageBand(a)===opts.age) score += 30;
        if(opts.sex==='either' || sexValue(a)===opts.sex) score += 10;
        if(opts.indoor==='either' || (opts.indoor==='yes' && hasIndoor(a)) || (opts.indoor==='no' && !hasIndoor(a))) score += 10;
        if(opts.bonded==='either' || (opts.bonded==='yes' && hasBonded(a)) || (opts.bonded==='no' && !hasBonded(a))) score += 10;
        if(opts.goodCats==='either' || goodWithCats(a)===opts.goodCats) score += 15;
        if(opts.goodDogs==='either' || goodWithDogs(a)===opts.goodDogs) score += 15;
        if(opts.goodChildren==='either' || goodWithChildren(a)===opts.goodChildren) score += 15;
        return score;
      }
	      function render(list){ const el=root.querySelector('#asm-quiz-results'); if(!list.length){ el.innerHTML='<p style="color:#6b7280;grid-column:1/-1;"><?php echo esc_js($q['results_empty_text'] ?? 'No exact matches were found. Please browse all adoptables instead.'); ?></p>'; return; } el.innerHTML=list.slice(0,6).map(a=>{ const name=safe(a.ANIMALNAME ?? a.AnimalName); const meta=[sexValue(a), safe(a.ANIMALAGE ?? a.AnimalAge), safe(a.BREEDNAME ?? a.BreedName ?? a.BREEDNAME1)].filter(Boolean).map(escHtml).join(' • '); const compat=['Cats: ' + goodWithCats(a),'Dogs: ' + goodWithDogs(a),'Children: ' + goodWithChildren(a)].map(escHtml).join(' • '); const url=modalLink(a); return `<a href="${escAttr(url)}" aria-label="View ${escAttr(name)}" style="display:block;background:#fff;border:2px solid var(--asm-quiz-brand);border-radius:20px;overflow:hidden;text-decoration:none;color:inherit;"><div style="aspect-ratio:1/1;background:#f3f4f6;"><img src="${escAttr(img(a))}" alt="${escAttr(name)}" style="width:100%;height:100%;object-fit:cover;display:block;" /></div><div style="padding:12px 14px;"><div style="font-weight:800;color:#111827;">${escHtml(name)}</div><div style="margin-top:.35rem;color:#6b7280;">${meta}</div><div style="margin-top:.5rem;color:#6b7280;font-size:.92rem;">${compat}</div></div></a>`; }).join(''); }
	      function updateSeo(list){ const data={"@context":"https://schema.org","@type":"ItemList","itemListElement":list.slice(0,6).map((a,i)=>({"@type":"ListItem","position":i+1,"item":{"@type":"Thing","additionalType":"https://schema.org/Animal","name":safe(a.ANIMALNAME ?? a.AnimalName),"description":safe(a.ANIMALCOMMENTS ?? a.DESCRIPTION ?? a.ANIMALDESCRIPTION),"image":img(a),"url":modalLink(a)}}))}; let tag=root.querySelector('script[type="application/ld+json"]'); if(!tag){ tag=document.createElement('script'); tag.type='application/ld+json'; root.appendChild(tag);} tag.textContent=JSON.stringify(data); }
      fetch(endpoint,{credentials:'same-origin'}).then(r=>r.json()).then(data=>{ animals=(Array.isArray(data)?data:[]).filter(a=>!hasPending(a)); const initial=animals.slice(0,3); render(initial); updateSeo(animals); }).catch(()=>render([]));
      root.querySelector('#asm-quiz-run').addEventListener('click',()=>{
        const opts={ age:root.querySelector('#asm-quiz-age').value, sex:root.querySelector('#asm-quiz-sex').value, indoor:(root.querySelector('#asm-quiz-indoor') ? root.querySelector('#asm-quiz-indoor').value : 'either'), bonded:root.querySelector('#asm-quiz-bonded').value, goodCats:root.querySelector('#asm-quiz-good-cats').value, goodDogs:root.querySelector('#asm-quiz-good-dogs').value, goodChildren:root.querySelector('#asm-quiz-good-children').value };
        let list=animals.filter(a=>matchesAll(a,opts));
        render(list); updateSeo(list);
      });
    })();</script>
    <?php return ob_get_clean();
  }


  public static function render_featured_shortcode($atts = []) {
    $settings = self::get_settings();
    $w = self::featured_settings($settings);
    if (empty($w['enabled'])) return '';
    $mode = $w['mode'] ?? 'random';
    $manual = preg_replace('/\D+/', '', (string)($w['manual_id'] ?? ''));
    $title = $w['title_text'] ?? 'Featured animal';
    $subtitle = $w['subtitle_text'] ?? 'Meet one of the animals looking for a home';
    $button = $w['button_text'] ?? 'View animal';
    $adoptables_page_url = esc_url_raw($settings['global']['adoptables_page_url'] ?? '');
    ob_start(); ?>
    <section class="asm-featured-animal-widget" aria-labelledby="asm-featured-title" style="--asm-featured-brand:<?php echo esc_attr($settings['adoptables']['brand_color'] ?? '#401268'); ?>; --asm-featured-bg:<?php echo esc_attr($settings['adoptables']['background_color'] ?? '#fcf5fd'); ?>; background:var(--asm-featured-bg); border:2px solid var(--asm-featured-brand); border-radius:24px; padding:20px;">
      <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
        <div style="flex:0 0 280px;max-width:100%;"><div style="aspect-ratio:1/1;background:#f3f4f6;border-radius:20px;overflow:hidden;"><img id="asm-featured-image" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" /></div></div>
        <div style="flex:1 1 320px;min-width:0;">
          <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;">Featured</div>
          <h3 style="margin:.35rem 0 0;font-size:2rem;line-height:1.15;color:var(--asm-featured-brand);"><?php echo esc_html($title); ?></h3>
          <p style="margin:.4rem 0 1rem;color:#6b7280;"><?php echo esc_html($subtitle); ?></p>
          <div id="asm-featured-name" style="font-size:1.5rem;font-weight:800;color:#111827;">Loading…</div>
          <div id="asm-featured-meta" style="margin:.4rem 0 1rem;color:#4b5563;">Please wait</div>
          <a id="asm-featured-link" href="#" style="display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:999px;background:var(--asm-featured-brand);color:#fff;text-decoration:none;font-weight:700;"><?php echo esc_html($button); ?></a>
        </div>
      </div>
    </section>
    <script>(function(){
      const root=document.currentScript.previousElementSibling; if(!root) return;
      const endpoint=<?php echo wp_json_encode(rest_url('plugin/v1/adoptables')); ?>;
      const adoptablesPageUrl=<?php echo wp_json_encode($adoptables_page_url); ?>;
      function slugifyPart(value){ return String(value || '').toLowerCase().replace(/&/g,' and ').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-'); }
      function animalModalSlug(animal){ const id=String(animal.ID ?? animal.ANIMALID ?? animal.AnimalID ?? '').replace(/\D+/g,''); const name=slugifyPart(animal.ANIMALNAME ?? animal.AnimalName ?? ''); const code=slugifyPart(animal.CODE ?? animal.SHELTERCODE ?? animal.ShelterCode ?? ''); return [name, code].filter(Boolean).join('-') || id; }
      function modalDeepLink(base,param,value){ const href=base || window.location.href; try { const url=new URL(href, window.location.origin); url.searchParams.set(param, value); return url.toString(); } catch(e) { const sep=String(href).includes('?') ? '&' : '?'; return String(href) + sep + encodeURIComponent(param) + '=' + encodeURIComponent(value); } }
      fetch(endpoint,{credentials:'same-origin'}).then(r=>r.json()).then(data=>{
        if(!Array.isArray(data)||!data.length) throw new Error('No animals');
        let animals=data.filter(a=>Number(a.SPECIESID ?? a.SpeciesID ?? 0)===2 || String(a.SPECIESNAME ?? a.SpeciesName ?? '').toLowerCase().includes('cat'));
        const mode=<?php echo wp_json_encode($mode); ?>;
        const manual=<?php echo wp_json_encode($manual); ?>;
        let animal=null;
        if(mode==='manual' && manual) animal=animals.find(a=>String(a.ID ?? a.ANIMALID ?? a.AnimalID ?? '')===String(manual));
        if(!animal && mode==='newest'){ animals.sort((a,b)=>Number(a.DAYSONSHELTER ?? a.DaysOnShelter ?? 0)-Number(b.DAYSONSHELTER ?? b.DaysOnShelter ?? 0)); animal=animals[0]; }
        if(!animal){ animal=animals[Math.floor(Math.random()*animals.length)]; }
        if(!animal) throw new Error('No animal');
        const id=String(animal.ID ?? animal.ANIMALID ?? animal.AnimalID ?? '');
        const name=String(animal.ANIMALNAME ?? animal.AnimalName ?? 'Animal');
        const age=String(animal.ANIMALAGE ?? animal.AnimalAge ?? '');
        const sex=String(animal.SEXNAME ?? animal.SexName ?? animal.SEX ?? '');
        const breed=String(animal.BREEDNAME ?? animal.BreedName ?? animal.BREEDNAME1 ?? '');
        const img=<?php echo wp_json_encode(rest_url('plugin/v1/animal-image')); ?> + '?animalid=' + encodeURIComponent(id) + '&seq=1';
        root.querySelector('#asm-featured-image').src=img;
        root.querySelector('#asm-featured-image').alt=name;
        root.querySelector('#asm-featured-name').textContent=name;
        root.querySelector('#asm-featured-meta').textContent=[sex, age, breed].filter(Boolean).join(' • ');
        root.querySelector('#asm-featured-link').href=modalDeepLink(adoptablesPageUrl,'cat',animalModalSlug(animal)); const fl=root.querySelector('#asm-featured-link'); if(fl && !fl.dataset.bound){ fl.dataset.bound='1'; fl.addEventListener('click',()=>{ const body=new URLSearchParams(); body.set('action','plugin_suite_track'); body.set('event','featured_widget_click'); body.set('nonce',<?php echo wp_json_encode(wp_create_nonce('plugin_suite_track')); ?>); fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()}); }); }
      }).catch(err=>{ root.querySelector('#asm-featured-name').textContent='No featured animal available'; root.querySelector('#asm-featured-meta').textContent=''; root.querySelector('#asm-featured-link').style.display='none'; });
    })();</script>
    <?php return ob_get_clean();
  }

  public static function all_suite_shortcodes() {
    $settings = self::get_settings();
    $tags = [self::sanitize_shortcode_tag($settings['shortcodes']['adoptables'] ?? 'adoptables'), self::sanitize_shortcode_tag($settings['shortcodes']['adopted'] ?? 'adopted'), self::sanitize_shortcode_tag($settings['shortcodes']['statistics'] ?? 'stats')];
    foreach (self::get_forms() as $tag => $id) $tags[] = $tag;
    $tags[] = self::sanitize_shortcode_tag($settings['featured']['shortcode'] ?? ($settings['widgets']['featured_shortcode'] ?? 'featured_animal'));
    $tags[] = self::sanitize_shortcode_tag($settings['quiz']['quiz_shortcode'] ?? 'adoption_match_quiz');
    return array_values(array_unique(array_filter($tags)));
  }

  public static function maybe_disable_cache_for_suite_pages() {
    if (!is_singular()) return;
    $post = get_queried_object();
    if (!$post || empty($post->post_content)) return;
    foreach (self::all_suite_shortcodes() as $tag) {
      if (has_shortcode($post->post_content, $tag)) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
        nocache_headers();
        return;
      }
    }
  }

  public static function admin_menu() {
    if (class_exists('Plugin_UI_Suite_Registry')) Plugin_UI_Suite_Registry::add_admin_menus();
    else add_options_page('Rescue Plugin Suite','Rescue Plugin Suite','manage_options','plugin-ui-suite',[__CLASS__,'render_settings_page']);
    add_submenu_page(null,'Rescue Plugin Suite Setup','Rescue Plugin Suite Setup','manage_options','plugin-ui-suite-setup',[__CLASS__,'render_setup_wizard']);
      }

  public static function remove_legacy_admin_pages() {
    remove_submenu_page('options-general.php', 'plugin-adoptables-ui');
    remove_submenu_page('options-general.php', 'plugin-adopted-ui');
    remove_submenu_page('options-general.php', 'plugin-stats-ui');
  }

  private static function sanitise_hex($value, $fallback) { $san = sanitize_hex_color($value); return $san ? $san : $fallback; }
  private static function sanitise_int($value, $fallback, $min, $max) { if ($value === '' || $value === null || !is_numeric($value)) $v = (int)$fallback; else $v = (int)$value; if($v<$min)$v=$min; if($v>$max)$v=$max; return $v; }
  private static function sanitise_float_string($value, $fallback, $min, $max) { $v = is_numeric($value)?(float)$value:(float)$fallback; if(!is_finite($v))$v=(float)$fallback; $v=max($min,min($max,$v)); return rtrim(rtrim(number_format($v,2,'.',''),'0'),'.'); }
  private static function sanitise_text($value, $fallback='') { $v=sanitize_text_field((string)$value); return $v===''?$fallback:$v; }
  private static function sanitise_textarea_csv($value) {
    $value = wp_unslash((string)$value);
    $value = str_replace(["\r\n", "\r", ";", "|"], ["\n", "\n", ',', ','], $value);
    $parts = preg_split('/[\n,]+/', $value);
    $parts = array_values(array_filter(array_map(function($item){
      return trim(wp_strip_all_tags((string)$item));
    }, (array)$parts)));
    return implode(',', $parts);
  }
  private static function sanitise_textarea($value, $fallback='') { $v=sanitize_textarea_field((string)$value); return $v===''?$fallback:$v; }
  private static function scaled($value, $scale_percent) { return max(10, min(80, (int) round($value * ($scale_percent / 100)))); }

  private static function get_style_behavior($settings=null) {
    if (!is_array($settings)) $settings = self::get_settings();
    $mode = $settings['global']['style_behavior'] ?? 'original_ui_defaults';
    return in_array($mode, ['original_ui_defaults','global_style_first'], true) ? $mode : 'original_ui_defaults';
  }

  private static function get_style_source($ui, $settings) {
    $key = $ui . '_style_source';
    $source = $settings['global'][$key] ?? 'auto';
    return in_array($source, ['auto','global','custom'], true) ? $source : 'auto';
  }

  private static function apply_global_style($section_key, $section, $global) {
    $section['brand_color'] = $global['brand_color'];
    $section['background_color'] = $global['background_color'];
    if (array_key_exists('modal_divider_color', $section)) $section['modal_divider_color'] = $global['modal_divider_color'];
    if (array_key_exists('text_primary_color', $section)) $section['text_primary_color'] = $global['text_primary_color'];
    if (array_key_exists('text_muted_color', $section)) $section['text_muted_color'] = $global['text_muted_color'];
    $section['paw_opacity'] = $global['paw_opacity'];
    $section['paw_count'] = $global['paw_count'];
    $section['font_family'] = $global['font_family'];
    $scale = intval($global['font_scale_percent']);
    if ($scale !== 100) {
      foreach ($section as $k => $v) if (strpos($k, 'fs_') === 0) $section[$k] = self::scaled((int)$v, $scale);
    }
    return $section;
  }

  private static function resolve_section_style($section_key, $section, $settings) {
    $source = self::get_style_source($section_key, $settings);
    if ($source === 'global') return self::apply_global_style($section_key, $section, $settings['global']);
    if ($source === 'custom') return $section;
    return self::get_style_behavior($settings) === 'global_style_first' ? self::apply_global_style($section_key, $section, $settings['global']) : $section;
  }

  private static function legacy_keys($section) {
    return array_keys(self::default_settings()[$section]);
  }

  private static function colour_keys($section) {
    $map = [
      'adoptables' => ['brand_color','background_color','modal_divider_color','card_border_color','apply_button_bg_color','apply_button_text_color','apply_button_border_color','bonded_label_bg_color','bonded_label_text_color','indoor_only_label_bg_color','indoor_only_label_text_color'],
      'adopted' => ['brand_color','background_color','text_primary_color','text_muted_color','card_border_color'],
      'stats' => ['brand_color','background_color','card_border_color'],
    ];
    return $map[$section] ?? [];
  }

  private static function float_keys($section) {
    return ['paw_opacity'];
  }
  private static function bool_keys($section) {
    return $section === 'adoptables' ? ['cats_only','show_top_navigation','show_reservation_label','show_pending_reservation_label','show_other_reservation_label','enable_deep_links','enable_apply_button','card_border_enabled','enable_filters','enable_filter_age','enable_filter_sex','enable_filter_breed','enable_exclude_pending_filter','detect_bonded_from_description','detect_indoor_only_from_description','enable_modal_slideshow_controls','enable_favourites','enable_compare_tool','enable_modals','enable_adoptables_modals'] : (($section === 'adopted') ? ['show_top_navigation','card_border_enabled','enable_modals','enable_deep_links','adoptables_cta_enabled'] : (($section === 'stats') ? ['card_border_enabled'] : []));
  }
  private static function textarea_keys($section) {
    $map = ['adoptables' => ['footer_text','modal_global_text','fallback_description','builder_card_order','builder_modal_order','builder_header_actions'], 'adopted' => ['footer_text','modal_global_text','builder_card_order','builder_modal_order'], 'stats' => ['footer_text','card_order','builder_card_order']];
    return $map[$section] ?? [];
  }
  private static function select_keys($section) {
    $map = ['adoptables' => ['reservation_label_halign','reservation_label_valign','display_style','favourite_button_position'], 'adopted' => ['date_label_halign','date_label_valign','display_style'], 'stats' => ['layout_mode']];
    return $map[$section] ?? [];
  }
  private static function icon_keys($section) { return $section === 'stats' ? ['icon_brought','icon_adopted','icon_vaccinated','icon_neutered','icon_chipped','icon_in_care'] : []; }

  private static function sanitize_section($section, $input, $current = []) {
    $defaults = self::default_settings()[$section];
    $out = self::merge_with_defaults($defaults, is_array($current) ? $current : []);
    foreach (self::legacy_keys($section) as $key) {
      if (!array_key_exists($key, $input)) continue;
      if (in_array($key, self::colour_keys($section), true)) $out[$key] = self::sanitise_hex($input[$key] ?? '', $defaults[$key]);
      elseif (in_array($key, self::float_keys($section), true)) $out[$key] = self::sanitise_float_string($input[$key] ?? '', $defaults[$key], 0, 0.25);
      elseif (in_array($key, self::bool_keys($section), true)) $out[$key] = !empty($input[$key]) ? 1 : 0;
      elseif (in_array($key, self::textarea_keys($section), true)) $out[$key] = self::sanitise_textarea($input[$key] ?? '', $defaults[$key]);
      elseif (in_array($key, self::icon_keys($section), true)) $out[$key] = self::sanitize_icon_slug($input[$key] ?? $defaults[$key]);
      elseif ($section === 'adopted' && $key === 'adoptables_cta_url') $out[$key] = esc_url_raw($input[$key] ?? '');
      elseif (in_array($key, self::select_keys($section), true)) {
        $val = sanitize_key($input[$key] ?? $defaults[$key]);
        if ($section === 'adoptables' && $key === 'reservation_label_halign') $out[$key] = in_array($val, ['left','right'], true) ? $val : $defaults[$key];
        elseif ($section === 'adoptables' && $key === 'reservation_label_valign') $out[$key] = in_array($val, ['top','bottom'], true) ? $val : $defaults[$key];
        elseif ($section === 'adoptables' && $key === 'display_style') $out[$key] = in_array($val, ['classic','compact','list'], true) ? $val : $defaults[$key];
        elseif ($section === 'adoptables' && $key === 'favourite_button_position') $out[$key] = in_array($val, ['top_left','top_right','bottom_left','bottom_right','hidden'], true) ? $val : $defaults[$key];
        elseif ($section === 'adopted' && $key === 'date_label_halign') $out[$key] = in_array($val, ['left','right'], true) ? $val : $defaults[$key];
        elseif ($section === 'adopted' && $key === 'date_label_valign') $out[$key] = in_array($val, ['top','bottom'], true) ? $val : $defaults[$key];
        elseif ($section === 'adopted' && $key === 'display_style') $out[$key] = in_array($val, ['classic','compact','list'], true) ? $val : $defaults[$key];
        elseif ($section === 'stats' && $key === 'layout_mode') $out[$key] = in_array($val, ['grid','one_row'], true) ? $val : $defaults[$key];
      } else {
        $default = $defaults[$key];
        if (is_int($default)) {
          $min=0; $max=1200;
          if (strpos($key,'rows_')===0 or strpos($key,'cols_')===0) $max=12;
          elseif (strpos($key,'fs_')===0) {$min=10; $max=80;}
          elseif (strpos($key,'fw_')===0) {$min=100; $max=900;}
          elseif (in_array($key,['paw_count'],true)) {$min=0; $max=80;}
          elseif (in_array($key,['modal_max_width','card_w_mobile','card_w_tablet','card_w_desktop','card_h_mobile','card_h_tablet','card_h_desktop'],true)) {$min=0; $max=1600;}
          elseif (in_array($key,['card_radius','card_padding','button_radius','gap_x_mobile','gap_y_mobile','gap_x_tablet','gap_y_tablet','gap_x_desktop','gap_y_desktop','modal_divider_thickness','modal_divider_radius'],true)) {$min=0; $max=999;}
          elseif (in_array($key,['card_scale_mobile','card_scale_tablet','card_scale_desktop'],true)) {$min=50; $max=200;}
          elseif (in_array($key,['min_year'],true)) {$min=2000; $max=3000;}
          $out[$key] = self::sanitise_int($input[$key] ?? '', $default, $min, $max);
        } else {
          $out[$key] = self::sanitise_text($input[$key] ?? '', $default);
        }
      }
    }
    return $out;
  }

  public static function handle_save() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_save');
    $active_tab = sanitize_key($_POST['active_tab'] ?? 'global');
    $active_subtab = sanitize_key($_POST['active_subtab'] ?? '');
    $input = isset($_POST['suite']) && is_array($_POST['suite']) ? wp_unslash($_POST['suite']) : [];
    $defaults = self::default_settings();
    $clean = self::get_settings();

    if ($active_tab === 'global') {
      $g = $input['global'] ?? [];
      $style_behavior = $g['style_behavior'] ?? ($clean['global']['style_behavior'] ?? $defaults['global']['style_behavior']);
      $clean['global']['style_behavior'] = in_array($style_behavior, ['original_ui_defaults','global_style_first'], true) ? $style_behavior : $defaults['global']['style_behavior'];
      foreach (['brand_color','background_color','modal_divider_color','text_primary_color','text_muted_color'] as $k) $clean['global'][$k] = self::sanitise_hex($g[$k] ?? '', $clean['global'][$k] ?? $defaults['global'][$k]);
      $clean['global']['paw_opacity'] = self::sanitise_float_string($g['paw_opacity'] ?? '', $clean['global']['paw_opacity'] ?? $defaults['global']['paw_opacity'], 0, 0.25);
      $clean['global']['paw_count'] = self::sanitise_int($g['paw_count'] ?? '', $clean['global']['paw_count'] ?? $defaults['global']['paw_count'], 0, 80);
      $clean['global']['font_family'] = preg_replace('/[^a-zA-Z0-9 ,\-]/', '', (string)($g['font_family'] ?? ($clean['global']['font_family'] ?? $defaults['global']['font_family'])));
      $clean['global']['font_scale_percent'] = self::sanitise_int($g['font_scale_percent'] ?? '', $clean['global']['font_scale_percent'] ?? $defaults['global']['font_scale_percent'], 50, 200);
      $clean['global']['cache_adoptables_seconds'] = self::sanitise_int($g['cache_adoptables_seconds'] ?? '', $clean['global']['cache_adoptables_seconds'] ?? $defaults['global']['cache_adoptables_seconds'], 0, 600);
      $clean['global']['cache_adopted_seconds'] = self::sanitise_int($g['cache_adopted_seconds'] ?? '', $clean['global']['cache_adopted_seconds'] ?? $defaults['global']['cache_adopted_seconds'], 0, 600);
      $clean['global']['cache_stats_seconds'] = self::sanitise_int($g['cache_stats_seconds'] ?? '', $clean['global']['cache_stats_seconds'] ?? $defaults['global']['cache_stats_seconds'], 0, 600);
      if (array_key_exists('bypass_plugin_cache', $g) || array_key_exists('bypa' . 'plugin_cache', $g)) $clean['global']['bypass_plugin_cache'] = (!empty($g['bypass_plugin_cache']) || !empty($g['bypa' . 'plugin_cache'])) ? 1 : 0;
      $clean['global']['delete_data_on_uninstall'] = !empty($g['delete_data_on_uninstall']) ? 1 : 0;
      foreach (['adoptables_page_url','adopted_page_url'] as $k) $clean['global'][$k] = esc_url_raw($g[$k] ?? ($clean['global'][$k] ?? ''));
      foreach (['adoptables_style_source','adopted_style_source','stats_style_source'] as $k) {
        $style_source = $g[$k] ?? ($clean['global'][$k] ?? 'auto');
        $clean['global'][$k] = in_array($style_source, ['auto','global','custom'], true) ? $style_source : ($clean['global'][$k] ?? 'auto');
      }
      $source = sanitize_key($g['data_source'] ?? ($clean['global']['data_source'] ?? 'asm'));
      $clean['global']['data_source'] = in_array($source, ['asm','custom_api','shelterluv','petpoint'], true) ? $source : 'asm';
      $clean['global']['custom_api_url'] = esc_url_raw($g['custom_api_url'] ?? ($clean['global']['custom_api_url'] ?? ''));
      $clean['global']['custom_api_key'] = self::sanitise_text($g['custom_api_key'] ?? '', $clean['global']['custom_api_key'] ?? '');
      $clean['global']['custom_api_auth_header'] = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($g['custom_api_auth_header'] ?? ($clean['global']['custom_api_auth_header'] ?? 'X-API-Key')));
      foreach (['custom_api_adoptables_url','custom_api_adoptions_url','custom_api_report_url','custom_api_incare_url','custom_api_image_url'] as $k) $clean['global'][$k] = esc_url_raw($g[$k] ?? ($clean['global'][$k] ?? ''));
      $clean['global']['field_map'] = sanitize_textarea_field($g['field_map'] ?? ($clean['global']['field_map'] ?? ''));
      $clean['global']['provider_profile'] = sanitize_key($g['provider_profile'] ?? ($clean['global']['provider_profile'] ?? ''));
      if (array_key_exists('preview_mode', $g)) $clean['global']['preview_mode'] = !empty($g['preview_mode']) ? 1 : 0;
      if (array_key_exists('enquiry_log_enabled', $g)) $clean['global']['enquiry_log_enabled'] = !empty($g['enquiry_log_enabled']) ? 1 : 0;
      $clean['global']['enquiry_email'] = sanitize_email($g['enquiry_email'] ?? ($clean['global']['enquiry_email'] ?? ''));
      $clean['global']['enquiry_webhook_url'] = esc_url_raw($g['enquiry_webhook_url'] ?? ($clean['global']['enquiry_webhook_url'] ?? ''));
      $clean['global']['enquiry_webhook_secret'] = sanitize_text_field($g['enquiry_webhook_secret'] ?? ($clean['global']['enquiry_webhook_secret'] ?? ''));
      $mode = sanitize_key($g['analytics_consent_mode'] ?? ($clean['global']['analytics_consent_mode'] ?? 'immediate'));
      $clean['global']['analytics_consent_mode'] = in_array($mode, ['immediate','cookie'], true) ? $mode : 'immediate';
      $clean['global']['analytics_consent_cookie'] = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($g['analytics_consent_cookie'] ?? ($clean['global']['analytics_consent_cookie'] ?? '')));
      $clean['global']['shelterluv_api_key'] = self::sanitise_text($g['shelterluv_api_key'] ?? '', $clean['global']['shelterluv_api_key'] ?? '');
      $clean['global']['shelterluv_base_url'] = esc_url_raw($g['shelterluv_base_url'] ?? ($clean['global']['shelterluv_base_url'] ?? 'https://www.shelterluv.com'));
      foreach (['shelterluv_adoptables_url','shelterluv_adoptions_url','shelterluv_report_url','shelterluv_incare_url','shelterluv_image_url','petpoint_adoptables_url','petpoint_adoptions_url','petpoint_report_url','petpoint_incare_url','petpoint_image_url'] as $endpoint_key) {
        $clean['global'][$endpoint_key] = esc_url_raw($g[$endpoint_key] ?? ($clean['global'][$endpoint_key] ?? ''));
      }
      $clean['global']['shelterluv_org_id'] = self::sanitise_text($g['shelterluv_org_id'] ?? '', $clean['global']['shelterluv_org_id'] ?? '');
      $clean['global']['shelterluv_statuses'] = self::sanitise_textarea_csv($g['shelterluv_statuses'] ?? ($clean['global']['shelterluv_statuses'] ?? 'adoptable,foster'));
      $clean['global']['shelterluv_location_ids'] = self::sanitise_textarea_csv($g['shelterluv_location_ids'] ?? ($clean['global']['shelterluv_location_ids'] ?? ''));
      $shelterluv_animal_type = $g['shelterluv_animal_type'] ?? ($clean['global']['shelterluv_animal_type'] ?? 'cat');
      $clean['global']['shelterluv_animal_type'] = in_array($shelterluv_animal_type, ['cat','cats','feline','any'], true) ? $shelterluv_animal_type : ($clean['global']['shelterluv_animal_type'] ?? 'cat');
      $clean['global']['petpoint_username'] = self::sanitise_text($g['petpoint_username'] ?? '', $clean['global']['petpoint_username'] ?? '');
      if (array_key_exists('petpoint_password', $g) && $g['petpoint_password'] !== '') {
        $clean['global']['petpoint_password'] = sanitize_text_field((string)$g['petpoint_password']);
      }
      $clean['global']['petpoint_base_url'] = esc_url_raw($g['petpoint_base_url'] ?? ($clean['global']['petpoint_base_url'] ?? ''));
      $clean['global']['petpoint_shelter_id'] = self::sanitise_text($g['petpoint_shelter_id'] ?? '', $clean['global']['petpoint_shelter_id'] ?? '');
      $clean['global']['petpoint_location_ids'] = self::sanitise_textarea_csv($g['petpoint_location_ids'] ?? ($clean['global']['petpoint_location_ids'] ?? ''));
      $clean['global']['petpoint_species_id'] = preg_replace('/[^0-9,]/', '', (string)($g['petpoint_species_id'] ?? ($clean['global']['petpoint_species_id'] ?? '2')));
      $clean['global']['petpoint_statuses'] = self::sanitise_textarea_csv($g['petpoint_statuses'] ?? ($clean['global']['petpoint_statuses'] ?? 'available,foster'));
      $clean['global']['petpoint_adopted_report_id'] = self::sanitise_text($g['petpoint_adopted_report_id'] ?? '', $clean['global']['petpoint_adopted_report_id'] ?? '');
      $clean['global']['enabled_species'] = self::sanitise_textarea_csv($g['enabled_species'] ?? ($clean['global']['enabled_species'] ?? 'cats'));
      $clean['global']['supported_species'] = self::sanitise_textarea_csv($g['supported_species'] ?? ($clean['global']['supported_species'] ?? 'cats,dogs,rabbits,birds,horses,small_animals,reptiles,other'));
    } elseif (in_array($active_tab, ['adoptables','adopted','stats'], true)) {
      $clean[$active_tab] = self::sanitize_section($active_tab, $input[$active_tab] ?? [], $clean[$active_tab] ?? []);
      if (!empty($input['shortcodes']) && is_array($input['shortcodes'])) { $sc=$input['shortcodes']; foreach (['adoptables','adopted','statistics'] as $k) if (isset($sc[$k])) $clean['shortcodes'][$k] = self::sanitize_shortcode_tag($sc[$k]) ?: ($clean['shortcodes'][$k] ?? $defaults['shortcodes'][$k]); }
      if ($active_tab === 'stats' && !empty($clean['stats']['builder_card_order'])) $clean['stats']['card_order'] = $clean['stats']['builder_card_order'];
    } elseif ($active_tab === 'featured') {
      $fi = $input['featured'] ?? [];
      $clean['featured']['shortcode'] = self::sanitize_shortcode_tag($fi['shortcode'] ?? ($clean['featured']['shortcode'] ?? ($clean['widgets']['featured_shortcode'] ?? $defaults['featured']['shortcode']))) ?: ($clean['featured']['shortcode'] ?? $defaults['featured']['shortcode']);
      $clean['featured']['enabled'] = !empty($fi['enabled']) ? 1 : 0;
      $clean['featured']['mode'] = in_array(($fi['mode'] ?? 'random'), ['random','newest','manual'], true) ? $fi['mode'] : 'random';
      $clean['featured']['manual_id'] = preg_replace('/\D+/', '', (string)($fi['manual_id'] ?? ''));
      foreach (['title_text','subtitle_text','button_text'] as $k) $clean['featured'][$k] = self::sanitise_text($fi[$k] ?? '', $defaults['featured'][$k]);
      $clean['featured']['layout_order'] = sanitize_textarea_field($fi['layout_order'] ?? ($clean['featured']['layout_order'] ?? $defaults['featured']['layout_order']));
    } elseif ($active_tab === 'quiz') {
      $q = $input['quiz'] ?? [];
      $clean['quiz']['quiz_shortcode'] = self::sanitize_shortcode_tag($q['quiz_shortcode'] ?? ($clean['quiz']['quiz_shortcode'] ?? $defaults['quiz']['quiz_shortcode'])) ?: ($clean['quiz']['quiz_shortcode'] ?? $defaults['quiz']['quiz_shortcode']);
      $clean['quiz']['quiz_enabled'] = !empty($q['quiz_enabled']) ? 1 : 0;
      foreach (['quiz_title_text','quiz_intro_text','q1_text','q1_kitten_label','q1_adult_label','q1_senior_label','q1_either_label','q2_text','q2_female_label','q2_male_label','q2_either_label','q3_text','q3_yes_label','q3_no_label','results_title_text','results_empty_text','age_categories'] as $k) $clean['quiz'][$k] = self::sanitise_text($q[$k] ?? '', $defaults['quiz'][$k]);
      $clean['quiz']['q3_hide'] = !empty($q['q3_hide']) ? 1 : 0;
      $clean['quiz']['question_order'] = sanitize_textarea_field($q['question_order'] ?? ($clean['quiz']['question_order'] ?? $defaults['quiz']['question_order']));
      $clean['quiz']['answer_mappings'] = sanitize_textarea_field($q['answer_mappings'] ?? ($clean['quiz']['answer_mappings'] ?? $defaults['quiz']['answer_mappings']));
    } elseif ($active_tab === 'forms') {
      $fo = $input['forms'] ?? [];
      $clean['forms']['account'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($fo['account'] ?? ($clean['forms']['account'] ?? $defaults['forms']['account'])));
      $clean['forms']['items'] = [];
      $items = isset($fo['items']) && is_array($fo['items']) ? $fo['items'] : [];
      for ($i=0; $i<10; $i++) {
        $row = isset($items[$i]) && is_array($items[$i]) ? $items[$i] : [];
        $clean['forms']['items'][] = ['shortcode' => self::sanitize_shortcode_tag($row['shortcode'] ?? ''), 'form_id' => preg_replace('/[^0-9]/', '', (string)($row['form_id'] ?? ''))];
      }
      $clean['forms']['layout_order'] = sanitize_textarea_field($fo['layout_order'] ?? ($clean['forms']['layout_order'] ?? $defaults['forms']['layout_order']));
      $clean['forms']['platform_support_notes'] = sanitize_textarea_field($fo['platform_support_notes'] ?? ($clean['forms']['platform_support_notes'] ?? $defaults['forms']['platform_support_notes']));
    } elseif ($active_tab === 'data-source') {
      $g = $input['global'] ?? [];
      $source = sanitize_key($g['data_source'] ?? ($clean['global']['data_source'] ?? 'asm'));
      $clean['global']['data_source'] = in_array($source, ['asm','custom_api','shelterluv','petpoint'], true) ? $source : 'asm';
      foreach (['custom_api_url','custom_api_adoptables_url','custom_api_adoptions_url','custom_api_report_url','custom_api_incare_url','custom_api_image_url','shelterluv_base_url','shelterluv_adoptables_url','shelterluv_adoptions_url','shelterluv_report_url','shelterluv_incare_url','shelterluv_image_url','petpoint_base_url','petpoint_adoptables_url','petpoint_adoptions_url','petpoint_report_url','petpoint_incare_url','petpoint_image_url'] as $k) $clean['global'][$k] = esc_url_raw($g[$k] ?? ($clean['global'][$k] ?? ''));
      foreach (['custom_api_key','custom_api_auth_header','provider_profile','field_map','shelterluv_api_key','shelterluv_org_id','petpoint_username','petpoint_shelter_id','petpoint_adopted_report_id'] as $k) $clean['global'][$k] = sanitize_text_field($g[$k] ?? ($clean['global'][$k] ?? ''));
      if (array_key_exists('petpoint_password', $g) && $g['petpoint_password'] !== '') $clean['global']['petpoint_password'] = sanitize_text_field((string)$g['petpoint_password']);
      if (array_key_exists('preview_mode', $g)) $clean['global']['preview_mode'] = !empty($g['preview_mode']) ? 1 : 0;
      if (array_key_exists('bypass_plugin_cache', $g)) $clean['global']['bypass_plugin_cache'] = !empty($g['bypass_plugin_cache']) ? 1 : 0;
    } elseif ($active_tab === 'layout') {
      foreach (['adoptables','adopted','stats'] as $section_key) {
        if (!empty($input[$section_key]) && is_array($input[$section_key])) $clean[$section_key] = self::sanitize_section($section_key, $input[$section_key], $clean[$section_key] ?? []);
      }
      if (!empty($clean['stats']['builder_card_order'])) $clean['stats']['card_order'] = $clean['stats']['builder_card_order'];
      $fi = $input['featured'] ?? [];
      if (isset($fi['layout_order'])) $clean['featured']['layout_order'] = sanitize_textarea_field($fi['layout_order']);
      $fo = $input['forms'] ?? [];
      if (isset($fo['layout_order'])) $clean['forms']['layout_order'] = sanitize_textarea_field($fo['layout_order']);
      if (isset($fo['platform_support_notes'])) $clean['forms']['platform_support_notes'] = sanitize_textarea_field($fo['platform_support_notes']);
      $q = $input['quiz'] ?? [];
      if (isset($q['question_order'])) $clean['quiz']['question_order'] = sanitize_textarea_field($q['question_order']);
      if (isset($q['answer_mappings'])) $clean['quiz']['answer_mappings'] = sanitize_textarea_field($q['answer_mappings']);
      $g = $input['global'] ?? [];
      if (isset($g['enabled_species'])) $clean['global']['enabled_species'] = self::sanitise_textarea_csv($g['enabled_species']);
      if (isset($g['supported_species'])) $clean['global']['supported_species'] = self::sanitise_textarea_csv($g['supported_species']);
    } elseif ($active_tab === 'proxy') {
      $pr = $input['proxy'] ?? [];
      $clean['proxy']['base_url'] = esc_url_raw($pr['base_url'] ?? ($clean['proxy']['base_url'] ?? $defaults['proxy']['base_url']));
      $clean['proxy']['account'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($pr['account'] ?? ($clean['proxy']['account'] ?? '')));
      $clean['proxy']['username'] = sanitize_text_field((string)($pr['username'] ?? ($clean['proxy']['username'] ?? '')));
      if (array_key_exists('password', $pr) && $pr['password'] !== '') {
        $clean['proxy']['password'] = (string)$pr['password'];
      }
      $clean['proxy']['cache_adoptables_seconds'] = self::sanitise_int($pr['cache_adoptables_seconds'] ?? '', $clean['proxy']['cache_adoptables_seconds'] ?? $defaults['proxy']['cache_adoptables_seconds'], 0, 600);
      $clean['proxy']['cache_reports_seconds'] = self::sanitise_int($pr['cache_reports_seconds'] ?? '', $clean['proxy']['cache_reports_seconds'] ?? $defaults['proxy']['cache_reports_seconds'], 0, 600);
      $clean['proxy']['cache_incare_seconds'] = self::sanitise_int($pr['cache_incare_seconds'] ?? '', $clean['proxy']['cache_incare_seconds'] ?? $defaults['proxy']['cache_incare_seconds'], 0, 600);
      $clean['proxy']['cache_adoptions_seconds'] = self::sanitise_int($pr['cache_adoptions_seconds'] ?? '', $clean['proxy']['cache_adoptions_seconds'] ?? $defaults['proxy']['cache_adoptions_seconds'], 0, 3600);
    }
    if (class_exists('Plugin_UI_Suite_Registry')) {
      $clean = Plugin_UI_Suite_Registry::sanitize_registered_values($active_tab, $input, $clean);
    }

    self::create_snapshot('save');
    update_option(self::OPT_KEY, $clean, false);
    self::define_proxy_constants_from_settings($clean);
    $args=['page'=>'plugin-ui-suite','tab'=>$active_tab,'updated'=>'true']; if($active_subtab!=='') $args['subtab']=$active_subtab; wp_safe_redirect(add_query_arg($args, admin_url('options-general.php')));
    exit;
  }


  public static function handle_save_current_defaults() {
    if (!current_user_can('manage_options')) wp_die('Not allowed');
    check_admin_referer('plugin_ui_suite_save_current_defaults');
    update_option(self::USER_DEFAULTS_KEY, self::get_settings(), false);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','saved_defaults'=>'1'], admin_url('options-general.php')));
    exit;
  }

  public static function handle_reset_defaults() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_reset_defaults');
    self::create_snapshot('reset_defaults');
    $defaults = self::default_settings();
    update_option(self::OPT_KEY, $defaults, false);
    self::sync_legacy_options($defaults);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'global','updated'=>'defaults-reset'], admin_url('options-general.php')));
    exit;
  }

  public static function sync_legacy_options_from_db($old, $new) {
    self::sync_legacy_options(is_array($new) ? $new : self::get_settings());
  }

  public static function sync_legacy_options($settings) {
    if (!is_array($settings)) $settings = self::default_settings();
    if (class_exists('Plugin_Adoptables_UI_Shortcode')) update_option(Plugin_Adoptables_UI_Shortcode::OPT_KEY, self::resolve_section_style('adoptables', array_merge(Plugin_Adoptables_UI_Shortcode::default_options(), $settings['adoptables']), $settings));
    if (class_exists('Plugin_Adopted_UI_Shortcode')) update_option(Plugin_Adopted_UI_Shortcode::OPT_KEY, self::resolve_section_style('adopted', array_merge(Plugin_Adopted_UI_Shortcode::default_options(), $settings['adopted']), $settings));
    if (function_exists('plugin_stats_ui_default_options')) update_option('plugin_stats_ui_options', self::resolve_section_style('stats', array_merge(plugin_stats_ui_default_options(), $settings['stats']), $settings));
  }


  public static function handle_registry_export() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_registry_export');
    $include_sensitive = !empty($_POST['include_sensitive']);
    $settings = class_exists('Plugin_UI_Suite_Registry') ? Plugin_UI_Suite_Registry::export_settings(self::get_settings(), $include_sensitive) : self::get_settings();
    nocache_headers(); header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename=rescue-plugin-suite-registry-settings.json');
    echo wp_json_encode(['version'=>PLUGIN_SUITE_VERSION,'schema'=>self::SCHEMA_VERSION,'sensitive_included'=>$include_sensitive,'settings'=>$settings], JSON_PRETTY_PRINT); exit;
  }

  public static function handle_registry_import() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_registry_import');
    if (empty($_FILES['import_file']['tmp_name'])) wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'registry','plugin_msg'=>'No import file selected'], admin_url('options-general.php')));
    $json = file_get_contents($_FILES['import_file']['tmp_name']); $payload = json_decode($json, true);
    if (!is_array($payload) || !is_array($payload['settings'] ?? null)) wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'registry','plugin_msg'=>'Registry import was not valid JSON'], admin_url('options-general.php')));
    $current = self::get_settings(); $merged = self::merge_with_defaults($current, $payload['settings']); update_option(self::OPT_KEY, $merged, false); self::sync_legacy_options($merged);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'registry','updated'=>'imported','plugin_msg'=>'Registry settings imported'], admin_url('options-general.php'))); exit;
  }

  public static function handle_registry_reset() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_registry_reset');
    $module = sanitize_key($_POST['module'] ?? ''); $settings = self::get_settings(); $defaults = self::default_settings();
    if ($module && isset($defaults[$module])) { $settings[$module] = $defaults[$module]; update_option(self::OPT_KEY, $settings, false); self::sync_legacy_options($settings); }
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'registry','updated'=>'reset','plugin_msg'=>'Registry module settings reset'], admin_url('options-general.php'))); exit;
  }

  public static function handle_export() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_export');
    $payload = [
      'suite' => self::exportable_settings(self::get_settings()),
      'legacy' => [
        'adoptables' => get_option('plugin_adoptables_ui_options', []),
        'adopted' => get_option('plugin_adopted_ui_options', []),
        'stats' => get_option('plugin_stats_ui_options', []),
      ],
    ];
    nocache_headers();
    header('Content-Type: application/json; charset=' . get_bloginfo('charset'));
    header('Content-Disposition: attachment; filename=plugin-ui-suite-settings.json');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  public static function handle_import() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_import');
    if (empty($_FILES['import_file']['tmp_name'])) wp_die('No import file uploaded.');
    $json = file_get_contents($_FILES['import_file']['tmp_name']);
    $data = json_decode($json, true);
    if (!is_array($data)) wp_die('Invalid import file.');
    self::create_snapshot('import');
    if (!empty($data['suite']) && is_array($data['suite'])) {
      $merged = array_replace_recursive(self::default_settings(), $data['suite']);
      $existing = self::get_settings();
      if (isset($existing['proxy']) && is_array($existing['proxy'])) {
        foreach (['account','username','password'] as $secret_key) {
          if (empty($merged['proxy'][$secret_key]) && !empty($existing['proxy'][$secret_key])) $merged['proxy'][$secret_key] = $existing['proxy'][$secret_key];
        }
      }
      if (isset($existing['global']) && is_array($existing['global'])) {
        foreach (['custom_api_key','shelterluv_api_key','petpoint_username','petpoint_password'] as $secret_key) {
          if (empty($merged['global'][$secret_key]) && !empty($existing['global'][$secret_key])) $merged['global'][$secret_key] = $existing['global'][$secret_key];
        }
      }
      update_option(self::OPT_KEY, $merged, false);
    }
    if (!empty($data['legacy']) && is_array($data['legacy'])) {
      if (isset($data['legacy']['adoptables']) && is_array($data['legacy']['adoptables'])) update_option('plugin_adoptables_ui_options', $data['legacy']['adoptables']);
      if (isset($data['legacy']['adopted']) && is_array($data['legacy']['adopted'])) update_option('plugin_adopted_ui_options', $data['legacy']['adopted']);
      if (isset($data['legacy']['stats']) && is_array($data['legacy']['stats'])) update_option('plugin_stats_ui_options', $data['legacy']['stats']);
    }
    self::sync_legacy_options(self::get_settings());
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'global','updated'=>'imported'], admin_url('options-general.php')));
    exit;
  }

  private static function field_name($section,$key){ return 'suite['.$section.']['.$key.']'; }
  private static function text_input($section,$key,$value,$class='regular-text'){ printf('<input type="text" name="%s" value="%s" class="%s" />', esc_attr(self::field_name($section,$key)), esc_attr($value), esc_attr($class)); }
  private static function number_input($section,$key,$value,$min,$max,$step=1){ printf('<input type="number" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />', esc_attr(self::field_name($section,$key)), esc_attr($value), esc_attr($min), esc_attr($max), esc_attr($step)); }
  private static function colour_input($section,$key,$value){ printf('<input type="color" name="%s" value="%s" /> <code>%s</code>', esc_attr(self::field_name($section,$key)), esc_attr($value), esc_html($value)); }
  private static function checkbox_input($section,$key,$checked,$label){ $name = self::field_name($section,$key); printf('<input type="hidden" name="%s" value="0" /><label><input type="checkbox" name="%s" value="1" %s /> %s</label>', esc_attr($name), esc_attr($name), checked(!empty($checked), true, false), esc_html($label)); }
  private static function textarea_input($section,$key,$value,$rows=4){ printf('<textarea name="%s" rows="%d" class="large-text">%s</textarea>', esc_attr(self::field_name($section,$key)), (int)$rows, esc_textarea($value)); }
  private static function select_input($section,$key,$value,$options){ printf('<select name="%s">', esc_attr(self::field_name($section,$key))); foreach($options as $ov=>$ol) printf('<option value="%s" %s>%s</option>', esc_attr($ov), selected((string)$value,(string)$ov,false), esc_html($ol)); echo '</select>'; }
  private static function icon_options(){ return ['heart'=>'Heart','home'=>'Home','syringe'=>'Syringe','stethoscope'=>'Stethoscope','map_pin'=>'Map Pin','shield'=>'Shield','tag'=>'Tag','scissors'=>'Scissors','handshake'=>'Handshake','house_heart'=>'House + Heart','users'=>'People','clipboard'=>'Clipboard','calendar'=>'Calendar','check'=>'Check','chart'=>'Chart','info'=>'Info','heartbeat'=>'Heartbeat','bandage'=>'Bandage','pill'=>'Pill','id_card'=>'ID Card','qr'=>'Scan / QR','shield_check'=>'Shield + Check','pawprints_a'=>'Paw Prints A','pawprints_b'=>'Paw Prints B','pawprints_c'=>'Paw Prints C','pawprints_d'=>'Paw Prints Cute','pawprints_e'=>'Paw Prints Bold']; }
  private static function sanitize_icon_slug($v){ $v=sanitize_key((string)$v); return array_key_exists($v,self::icon_options())?$v:'heart'; }
  private static function sortable_list($name,$values,$labels){
    $current=[];
    if (!is_array($values)) $values=[];
    foreach ($values as $v){ $v=sanitize_key((string)$v); if($v!=='' && isset($labels[$v]) && !in_array($v,$current,true)) $current[]=$v; }
    foreach (array_keys($labels) as $v){ if(!in_array($v,$current,true)) $current[]=$v; }
    $id = 'plugin-sortable-' . md5($name);
    echo '<ul class="plugin-sortable" data-sortable-input="'.esc_attr($id).'" role="listbox" aria-label="Drag or use arrow keys to reorder layout items">';
    foreach ($current as $v){ echo '<li draggable="true" tabindex="0" role="option" aria-grabbed="false" data-value="'.esc_attr($v).'"><span>'.esc_html($labels[$v]).'</span><span class="dashicons dashicons-move" aria-hidden="true"></span></li>'; }
    echo '</ul>';
    printf('<textarea id="%s" class="plugin-sortable-input" name="%s" rows="6" style="display:none;">%s</textarea><p class="description" data-sortable-status hidden></p>', esc_attr($id), esc_attr($name), esc_textarea(implode("
",$current)));
  }
  private static function preview_url($ui,$device='desktop'){ return wp_nonce_url(add_query_arg(['action'=>'plugin_ui_suite_preview','ui'=>$ui,'device'=>$device], admin_url('admin-post.php')), 'plugin_ui_suite_preview_'.$ui.'_'.$device); }

  public static function render_preview() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    $ui = sanitize_key($_GET['ui'] ?? 'adoptables');
    $device = sanitize_key($_GET['device'] ?? 'desktop');
    if (!in_array($ui,['adoptables','adopted','stats','featured','quiz'],true)) $ui='adoptables';
    if (!in_array($device,['mobile','tablet','desktop'],true)) $device='desktop';
    check_admin_referer('plugin_ui_suite_preview_'.$ui.'_'.$device);
    self::sync_legacy_options(self::get_settings());
    $widths=['mobile'=>430,'tablet'=>900,'desktop'=>1280]; $width=$widths[$device];
    $settings = self::get_settings();
    $shortcodes=['adoptables'=>self::sanitize_shortcode_tag($settings['shortcodes']['adoptables'] ?? 'adoptables'),'adopted'=>self::sanitize_shortcode_tag($settings['shortcodes']['adopted'] ?? 'adopted'),'stats'=>self::sanitize_shortcode_tag($settings['shortcodes']['statistics'] ?? 'stats'),'featured'=>self::sanitize_shortcode_tag($settings['featured']['shortcode'] ?? ($settings['widgets']['featured_shortcode'] ?? 'featured_animal')),'quiz'=>self::sanitize_shortcode_tag($settings['quiz']['quiz_shortcode'] ?? 'adoption_match_quiz')];
    $tag = $shortcodes[$ui] ?? 'adoptables';
    $html=do_shortcode('['.$tag.']');
    status_header(200); nocache_headers(); ?>
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php do_action('wp_head'); ?><style>body{margin:0;padding:16px;background:#eef1f5;font-family:Arial,sans-serif}.plugin-preview-shell{width:min(100%,<?php echo (int)$width; ?>px);margin:0 auto;background:#fff;border:1px solid #d0d7de;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.08)}.plugin-preview-bar{padding:10px 14px;background:#f6f8fa;border-bottom:1px solid #d8dee4;font-size:12px;font-weight:600;color:#344054}</style></head><body><div class="plugin-preview-shell"><div class="plugin-preview-bar"><?php echo esc_html(ucfirst($ui).' preview · '.ucfirst($device)); ?></div><?php echo $html; ?></div><?php do_action('wp_footer'); ?></body></html><?php exit; }

  private static function preview_frame($ui) {
    echo '<div class="plugin-suite-card" style="grid-column:1/-1;">';
    echo '<div class="plugin-suite-preview-header"><h2 style="margin:0;">Live preview</h2><div class="plugin-suite-preview-switch">';
    foreach (['mobile'=>'Mobile','tablet'=>'Tablet','desktop'=>'PC'] as $device=>$label) printf('<button type="button" class="button plugin-preview-btn" data-ui="%1$s" data-src="%2$s">%3$s</button> ', esc_attr($ui), esc_url(self::preview_url($ui,$device)), esc_html($label));
    echo '</div></div>';
    printf('<iframe class="plugin-suite-preview-frame" id="plugin-preview-%1$s" src="%2$s" loading="lazy"></iframe>', esc_attr($ui), esc_url(self::preview_url($ui,'desktop')));
    echo '</div>';
  }

  private static function preview_frame_custom($ui, $title = 'Live preview') {
    echo '<div class="plugin-suite-card" style="grid-column:1/-1;">';
    echo '<div class="plugin-suite-preview-header"><h2 style="margin:0;">'.esc_html($title).'</h2><div class="plugin-suite-preview-switch">';
    foreach (['mobile'=>'Mobile','tablet'=>'Tablet','desktop'=>'PC'] as $device=>$label) printf('<button type="button" class="button plugin-preview-btn" data-ui="%1$s" data-src="%2$s">%3$s</button> ', esc_attr($ui), esc_url(self::preview_url($ui,$device)), esc_html($label));
    echo '</div></div>';
    printf('<iframe class="plugin-suite-preview-frame" id="plugin-preview-%1$s" src="%2$s" loading="lazy"></iframe>', esc_attr($ui), esc_url(self::preview_url($ui,'desktop')));
    echo '</div>';
  }

  private static function row($label,$html){ echo '<tr><th>'.$label.'</th><td>'.$html.'</td></tr>'; }
  private static function start_wrap(){ ?>
    <style>
      .plugin-suite-tabs{display:flex;gap:8px;margin:18px 0 22px;flex-wrap:wrap}.plugin-suite-tab{padding:10px 14px;border:1px solid #ccd0d4;border-bottom:none;background:#f6f7f7;text-decoration:none;color:#1d2327;border-radius:6px 6px 0 0}.plugin-suite-tab.active{background:#fff;font-weight:600}.plugin-suite-panel{background:#fff;border:1px solid #ccd0d4;padding:22px;max-width:1450px}.plugin-suite-panel .form-table tr{display:table-row}.plugin-suite-panel .form-table th,.plugin-suite-panel .form-table td{padding:10px 8px;vertical-align:top}.plugin-suite-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-items:start}.plugin-suite-card{border:1px solid #e2e4e7;border-radius:10px;padding:16px;background:#fcfcfc;min-width:0}.plugin-suite-card h2,.plugin-suite-card h3{margin-top:0}.plugin-suite-inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.plugin-suite-table{width:100%;border-collapse:collapse}.plugin-suite-table th,.plugin-suite-table td{padding:10px 8px;border-top:1px solid #e2e4e7;vertical-align:top;text-align:left}.plugin-suite-card .form-table{width:100%;table-layout:fixed}.plugin-suite-card .form-table th{width:210px;max-width:210px;white-space:normal;word-break:break-word}.plugin-suite-card .form-table td{overflow-wrap:anywhere}.plugin-suite-card input[type=text],.plugin-suite-card textarea,.plugin-suite-card select,.plugin-suite-card .regular-text,.plugin-suite-card .large-text{width:100%;max-width:100%}.plugin-suite-card input.small-text{width:72px;max-width:100%}.plugin-suite-card input[type=color]{width:48px;min-width:48px;padding:0}.plugin-suite-save{margin-top:20px}.plugin-suite-preview-header{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:12px}.plugin-suite-preview-switch{display:flex;gap:8px;flex-wrap:wrap}.plugin-suite-preview-frame{width:100%;height:880px;border:1px solid #d0d7de;border-radius:12px;background:#fff}.plugin-suite-note{padding:12px 14px;background:#f6f7f7;border:1px solid #e2e4e7;border-radius:8px}.plugin-subgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.plugin-triplet{display:grid;grid-template-columns:150px 1fr 1fr 1fr 1fr;gap:8px;align-items:center}.plugin-triplet>div{min-width:0}.plugin-suite-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.plugin-sortable{list-style:none;margin:0;padding:0;border:1px solid #d0d7de;border-radius:10px;background:#fff}.plugin-sortable li{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;border-top:1px solid #eef2f6;cursor:move}.plugin-sortable li:first-child{border-top:none}.plugin-sortable li.dragging{opacity:.45;transform:scale(.99)}.plugin-sortable li:focus-visible,.plugin-suite-tab:focus-visible,.plugin-suite-subnav a:focus-visible,.plugin-suite-card .button:focus-visible{outline:3px solid #7c3aed;outline-offset:2px}.plugin-sortable li:hover{background:#f8fafc}.plugin-suite-card{transition:box-shadow .18s ease,border-color .18s ease}.plugin-suite-card:hover{border-color:#c4b5fd;box-shadow:0 8px 24px rgba(64,18,104,.07)}@media (prefers-reduced-motion: reduce){.plugin-suite-card,.plugin-sortable li{transition:none!important;transform:none!important}}.plugin-sortable .dashicons{color:#6b7280}.plugin-suite-subnav{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}.plugin-suite-subnav a{display:inline-block;padding:8px 12px;border:1px solid #d0d7de;border-radius:8px;background:#f6f7f7;text-decoration:none;color:#1d2327}.plugin-suite-subnav a.active{background:#401268;color:#fff;border-color:#401268}.plugin-suite-subsection{display:none}.plugin-suite-subsection.active{display:block}
      @media (max-width: 1200px){.plugin-suite-grid,.plugin-subgrid{grid-template-columns:1fr}.plugin-triplet{grid-template-columns:1fr}}
    </style>
  <?php }

  public static function render_settings_page() {
    if (!current_user_can('manage_options')) return;
    $settings = self::get_settings();
    $tab = sanitize_key($_GET['tab'] ?? 'global');
    if (in_array($tab, ['registry','proxy'], true) && (!class_exists('Plugin_UI_Suite_Registry') || !Plugin_UI_Suite_Registry::developer_mode_enabled())) $tab = 'global';
    if (!in_array($tab,['global','data-source','adoptables','adopted','featured','stats','payments','quiz','forms','proxy','diagnostics','registry','updates','help'], true)) $tab='global';
    echo '<div class="wrap"><h1>Rescue Plugin Suite</h1><p class="description">Manage rescue website displays, animal data, enquiries and donations from one place. Start with General, connect services in Integrations, then adjust each public experience.</p><p>Default shortcodes: <code>[adoptables]</code> <code>[adopted]</code> <code>[stats]</code> <code>[adoption_form]</code> <code>[volunteer_form]</code> <code>[waiting_list_form]</code> <code>[lost_cat_form]</code></p>';
    if (!empty($_GET['updated'])) echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    if (!empty($_GET['plugin_msg'])) echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(wp_unslash($_GET['plugin_msg'])) . '</p></div>';
    self::start_wrap();
    if (class_exists('Plugin_UI_Suite_Registry')) Plugin_UI_Suite_Registry::render_tabs($tab);
    echo '<div class="plugin-suite-panel">';
    if ($tab === 'global') self::render_global_tab($settings);
    elseif ($tab === 'data-source') self::render_data_source_tab($settings);
    elseif ($tab === 'adoptables') self::render_adoptables_tab($settings['adoptables'], $settings['global']);
    elseif ($tab === 'adopted') self::render_adopted_tab($settings['adopted'], $settings['global']);
    elseif ($tab === 'stats') self::render_stats_tab($settings['stats'], $settings['global']);
    elseif ($tab === 'featured') self::render_featured_tab($settings);
    elseif ($tab === 'payments' && class_exists('Plugin_Payments_Module')) Plugin_Payments_Module::render_admin_page(true);
    elseif ($tab === 'quiz') self::render_quiz_tab($settings);
    elseif ($tab === 'forms') self::render_forms_tab($settings);
    elseif ($tab === 'proxy') self::render_proxy_tab($settings);
    elseif ($tab === 'diagnostics') self::render_diagnostics_tab($settings);
    elseif ($tab === 'registry') self::render_registry_tab($settings);
    elseif ($tab === 'updates' && class_exists('Plugin_UI_Suite_Updater')) Plugin_UI_Suite_Updater::render_updates_panel(true);
    else self::render_help_tab($settings);
    echo "</div><script>(function(){document.addEventListener('click',function(e){const btn=e.target.closest('.plugin-preview-btn');if(!btn)return;const frame=document.getElementById('plugin-preview-'+btn.dataset.ui);if(frame&&btn.dataset.src)frame.src=btn.dataset.src;});})();</script></div>";
  }

  private static function form_start($tab){ $subtab=sanitize_key($_GET['subtab'] ?? ''); echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_save'); echo '<input type="hidden" name="action" value="plugin_ui_suite_save" /><input type="hidden" name="active_tab" value="'.esc_attr($tab).'" /><input type="hidden" name="active_subtab" value="'.esc_attr($subtab).'" />'; }
  private static function form_end(){ echo '<p class="plugin-suite-save">'; submit_button('Save changes','primary','submit',false); echo '</p></form>'; }

  private static function render_global_tab($s) {
    echo '<div class="plugin-suite-grid">';

    self::form_start('global');
    echo '<div class="plugin-suite-card"><h2>General settings</h2><p class="description">Use this page for organisation-wide behaviour, shared styling, public page links, plugin-wide cache, import/export and safe reset tools. Service connections now live in Integrations.</p><table class="form-table">';
    ob_start(); self::select_input('global','style_behavior',$s['global']['style_behavior'], ['original_ui_defaults'=>'Original UI defaults first','global_style_first'=>'Global style first']); self::row('UI style behaviour', ob_get_clean());
    foreach ([['cache_adoptables_seconds','Adoptables cache seconds'],['cache_adopted_seconds','Adopted cache seconds'],['cache_stats_seconds','Statistics cache seconds']] as $r){ ob_start(); self::number_input('global',$r[0],$s['global'][$r[0]],0,600); self::row($r[1], ob_get_clean()); }
    ob_start(); self::checkbox_input('global','bypass_plugin_cache',$s['global']['bypass_plugin_cache'] ?? 0,'Bypass suite transients and request fresh API data'); self::row('Cache bypass', ob_get_clean() . '<p class="description">When enabled, adoptables, adopted animals, statistics and SEO profile feeds skip the plugin cache. This may increase requests to your configured API.</p>');
    ob_start(); self::checkbox_input('global','delete_data_on_uninstall',$s['global']['delete_data_on_uninstall'] ?? 0,'Delete all Rescue Plugin Suite data when uninstalling'); self::row('Uninstall data removal', ob_get_clean() . '<p class="description">Leave disabled to preserve settings, API keys, payment configuration, campaigns and user preferences during updates or uninstall/reinstall workflows.</p>');
    self::row('Adoptables UI page URL', '<input type="url" name="suite[global][adoptables_page_url]" value="' . esc_attr($s['global']['adoptables_page_url'] ?? '') . '" class="regular-text code" placeholder="' . esc_attr(home_url('/adopt/')) . '" /><p class="description">Used by the featured animal widget and adoptable modal share links. Set this to the page containing the Adoptables UI shortcode.</p>');
    self::row('Adopted UI page URL', '<input type="url" name="suite[global][adopted_page_url]" value="' . esc_attr($s['global']['adopted_page_url'] ?? '') . '" class="regular-text code" placeholder="' . esc_attr(home_url('/happy-endings/')) . '" /><p class="description">Used by adopted modal share links and adopted animal links. Set this to the page containing the Adopted UI shortcode.</p>');
    ob_start(); self::select_input('global','adoptables_style_source',$s['global']['adoptables_style_source'],['auto'=>'Auto','global'=>'Always use global','custom'=>'Always use custom']); self::row('Adoptables style source', ob_get_clean());
    ob_start(); self::select_input('global','adopted_style_source',$s['global']['adopted_style_source'],['auto'=>'Auto','global'=>'Always use global','custom'=>'Always use custom']); self::row('Adopted style source', ob_get_clean());
    ob_start(); self::select_input('global','stats_style_source',$s['global']['stats_style_source'],['auto'=>'Auto','global'=>'Always use global','custom'=>'Always use custom']); self::row('Statistics style source', ob_get_clean());
    echo '</table><div class="plugin-suite-note"><strong>How Auto works</strong><br>Original UI defaults first keeps each UI on its own settings by default.<br>Global style first makes each UI inherit the Global tab values unless that UI is set to Custom.</div>';
    self::form_end();
    echo '</div>';

    self::form_start('global');
    echo '<div class="plugin-suite-card"><h2>Shared styling</h2><p class="description">These styles can be reused by Adoptables, Adopted and Statistics so public displays feel consistent.</p><table class="form-table">';
    foreach (['brand_color'=>'Brand colour','background_color'=>'Background colour','modal_divider_color'=>'Modal divider colour','text_primary_color'=>'Primary text colour','text_muted_color'=>'Muted text colour'] as $k=>$label){ ob_start(); self::colour_input('global',$k,$s['global'][$k]); self::row($label, ob_get_clean()); }
    ob_start(); self::number_input('global','paw_opacity',$s['global']['paw_opacity'],0,0.25,0.01); self::row('Paw print opacity', ob_get_clean());
    ob_start(); self::number_input('global','paw_count',$s['global']['paw_count'],0,80); self::row('Paw print count', ob_get_clean());
    ob_start(); self::text_input('global','font_family',$s['global']['font_family']); self::row('Font family', ob_get_clean());
    ob_start(); self::number_input('global','font_scale_percent',$s['global']['font_scale_percent'],50,200); self::row('Global font scale %', ob_get_clean());
    echo '</table>';
    self::form_end();
    echo '</div>';

    echo '<div class="plugin-suite-card"><h2>Reset to baked defaults</h2><p class="description">This resets the suite settings to the baked defaults and re-syncs the three UI modules.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('plugin_ui_suite_reset_defaults');
    echo '<input type="hidden" name="action" value="plugin_ui_suite_reset_defaults"><p><button type="submit" class="button">Reset to baked defaults</button></p></form>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_save_current_defaults'); echo '<input type="hidden" name="action" value="plugin_ui_suite_save_current_defaults"><p><button type="submit" class="button button-primary">Save current settings as defaults</button></p></form></div>';

    echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Export / Import</h2><div class="plugin-suite-actions">';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_export'); echo '<input type="hidden" name="action" value="plugin_ui_suite_export" />'; submit_button('Export settings','secondary','submit',false); echo '</form>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">'; wp_nonce_field('plugin_ui_suite_import'); echo '<input type="hidden" name="action" value="plugin_ui_suite_import" /><input type="file" name="import_file" accept="application/json" /> '; submit_button('Import settings','secondary','submit',false); echo '</form>';
    $packs = get_option('plugin_ui_suite_setting_packs', []); if (!is_array($packs)) $packs = []; echo '<hr><h3>Named setting packs</h3><div class="plugin-suite-actions">'; echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_save_pack'); echo '<input type="hidden" name="action" value="plugin_ui_suite_save_pack" /><input type="text" name="pack_name" placeholder="Pack name" class="regular-text" /> '; submit_button('Save current settings as pack','secondary','submit',false); echo '</form>'; echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_load_pack'); echo '<input type="hidden" name="action" value="plugin_ui_suite_load_pack" /><select name="pack_id">'; foreach($packs as $pack_id=>$pack){ printf('<option value="%s">%s</option>', esc_attr($pack_id), esc_html($pack['name'] ?? $pack_id)); } echo '</select> '; submit_button('Load selected pack','secondary','submit',false); echo '</form></div><p class="description">Export includes suite settings and the three UI option sets. Import restores them on this site. Settings exports from newer suite builds are accepted where keys are compatible.</p></div>';
    echo '</div>';
  }

  private static function render_data_source_tab($s) {
    $sub = sanitize_key($_GET['subtab'] ?? 'asm');
    $items = ['asm'=>'ASM','shelterluv'=>'Shelterluv','petpoint'=>'PetPoint','custom-api'=>'Custom API'];
    if (class_exists('Plugin_Payments_Module')) foreach (Plugin_Payments_Module::integration_provider_labels() as $id=>$label) $items[$id] = $label;
    $sub = self::subtab_nav('data-source', $items);
    if (!isset($items[$sub])) $sub = 'asm';
    echo '<div class="plugin-suite-note"><strong>One service, one page.</strong> Each integration contains its credentials, authentication, endpoints or webhook URL, mapping notes, cache behaviour, status, diagnostics and documentation links. Existing option keys are preserved for backwards compatibility.</div>';
    $payment_labels = class_exists('Plugin_Payments_Module') ? Plugin_Payments_Module::integration_provider_labels() : [];
    if (isset($payment_labels[$sub])) { Plugin_Payments_Module::render_integration_provider_panel($sub); return; }

    self::form_start('data-source');
    echo '<div class="plugin-suite-grid">';
    echo '<div class="plugin-suite-card"><h2>'.esc_html($items[$sub]).' integration</h2><p class="description">Complete the details supplied by this service, save, then use Diagnostics to confirm the connection. Leave optional endpoint overrides blank unless your provider or developer supplied them.</p><table class="form-table">';
    ob_start(); self::select_input('global','data_source',$s['global']['data_source'] ?? 'asm', ['asm'=>'Animal Shelter Manager (ASM)','custom_api'=>'Custom API','shelterluv'=>'Shelterluv','petpoint'=>'PetPoint']); self::row('Use as live animal source', ob_get_clean());
    if ($sub === 'asm') {
      self::row('Credentials', '<p class="description">ASM service URL, account, username and password are read from the existing ASM proxy constants/settings. Use Developer Tools only when a developer needs raw proxy checks.</p>');
      self::row('Documentation', '<a href="https://sheltermanager.com/site/en_asm3_help.html" target="_blank" rel="noopener">ASM API documentation</a>');
    } elseif ($sub === 'shelterluv') {
      foreach (['shelterluv_api_key'=>'API key','shelterluv_base_url'=>'Base URL','shelterluv_org_id'=>'Organisation ID','shelterluv_statuses'=>'Statuses','shelterluv_location_ids'=>'Location IDs','shelterluv_animal_type'=>'Animal type'] as $key=>$label) self::row($label, '<input type="'.($key==='shelterluv_api_key'?'password':'text').'" name="suite[global]['.esc_attr($key).']" value="'.esc_attr($s['global'][$key] ?? '').'" class="regular-text" />');
      foreach (['shelterluv_adoptables_url'=>'Adoptables endpoint','shelterluv_adoptions_url'=>'Adoptions endpoint','shelterluv_report_url'=>'Report endpoint','shelterluv_incare_url'=>'In-care endpoint','shelterluv_image_url'=>'Image endpoint'] as $key=>$label) self::row($label, '<input type="url" name="suite[global]['.esc_attr($key).']" value="'.esc_attr($s['global'][$key] ?? '').'" class="regular-text code" />');
    } elseif ($sub === 'petpoint') {
      foreach (['petpoint_username'=>'Username','petpoint_password'=>'Password','petpoint_base_url'=>'Base URL','petpoint_shelter_id'=>'Shelter ID','petpoint_location_ids'=>'Location IDs','petpoint_species_id'=>'Species ID','petpoint_statuses'=>'Statuses','petpoint_adopted_report_id'=>'Adopted report ID'] as $key=>$label) self::row($label, '<input type="'.($key==='petpoint_password'?'password':'text').'" name="suite[global]['.esc_attr($key).']" value="'.esc_attr($s['global'][$key] ?? '').'" class="regular-text" />');
      foreach (['petpoint_adoptables_url'=>'Adoptables endpoint','petpoint_adoptions_url'=>'Adoptions endpoint','petpoint_report_url'=>'Report endpoint','petpoint_incare_url'=>'In-care endpoint','petpoint_image_url'=>'Image endpoint'] as $key=>$label) self::row($label, '<input type="url" name="suite[global]['.esc_attr($key).']" value="'.esc_attr($s['global'][$key] ?? '').'" class="regular-text code" />');
    } else {
      foreach (['custom_api_url'=>'Base URL','custom_api_auth_header'=>'Authentication header','custom_api_key'=>'API key'] as $key=>$label) self::row($label, '<input type="'.($key==='custom_api_key'?'password':($key==='custom_api_url'?'url':'text')).'" name="suite[global]['.esc_attr($key).']" value="'.esc_attr($s['global'][$key] ?? '').'" class="regular-text code" />');
      foreach (['custom_api_adoptables_url'=>'Adoptables endpoint','custom_api_adoptions_url'=>'Adoptions endpoint','custom_api_report_url'=>'Report endpoint','custom_api_incare_url'=>'In-care endpoint','custom_api_image_url'=>'Image endpoint'] as $key=>$label) self::row($label, '<input type="url" name="suite[global]['.esc_attr($key).']" value="'.esc_attr($s['global'][$key] ?? '').'" class="regular-text code" />');
    }
    ob_start(); self::select_input('global','provider_profile',$s['global']['provider_profile'] ?? '', array_map(function($v){ return $v['label']; }, self::provider_profile_templates())); self::row('Field mapping template', ob_get_clean());
    self::row('Field mapping', '<textarea name="suite[global][field_map]" rows="6" class="large-text code">' . esc_textarea($s['global']['field_map'] ?: (self::provider_profile_templates()[$s['global']['provider_profile'] ?? '']['map'] ?? '')) . '</textarea>');
    self::row('Provider cache', '<p class="description">Animal and statistics feed cache times are plugin-wide so public displays stay consistent. Change them in General → General settings. Provider cache can be bypassed from there when testing.</p>');
    self::row('Status and health', '<strong>'.esc_html(($s['global']['data_source'] ?? 'asm') === str_replace('-', '_', $sub) ? 'Selected as live source' : 'Configured but not selected').'</strong><p class="description">Use Advanced Diagnostics in developer mode for raw endpoint payloads.</p>');
    echo '</table></div></div>'; self::form_end();
  }

  private static function subtab_nav($tab, $items) {
    $current = sanitize_key($_GET['subtab'] ?? array_key_first($items));
    echo '<div class="plugin-suite-subnav">';
    foreach ($items as $key => $label) {
      $url = add_query_arg(['page'=>'plugin-ui-suite','tab'=>$tab,'subtab'=>$key], admin_url('options-general.php'));
      printf('<a class="%s" href="%s">%s</a>', $current === $key ? 'active' : '', esc_url($url), esc_html($label));
    }
    echo '</div>';
    return $current;
  }

  private static function render_typography_table($section,$rows,$o) {
    echo '<table class="plugin-suite-table"><thead><tr><th>Text</th><th>Mobile</th><th>Tablet</th><th>PC</th><th>Weight</th></tr></thead><tbody>';
    foreach ($rows as $slug=>$label) {
      echo '<tr><td>'.esc_html($label).'</td><td>'; self::number_input($section,'fs_'.$slug.'_mobile',$o['fs_'.$slug.'_mobile'],10,80); echo '</td><td>'; self::number_input($section,'fs_'.$slug.'_tablet',$o['fs_'.$slug.'_tablet'],10,80); echo '</td><td>'; self::number_input($section,'fs_'.$slug.'_desktop',$o['fs_'.$slug.'_desktop'],10,80); echo '</td><td>';
      $fw='fw_'.$slug; if (isset($o[$fw])) self::number_input($section,$fw,$o[$fw],100,900,100); else echo '—';
      echo '</td></tr>';
    }
    echo '</tbody></table>';
  }

  private static function render_adoptables_tab($o, $g) {
    self::form_start('adoptables');
    $items = [
      'design'=>'Design',
      'text'=>'Text',
      'responsive'=>'Responsive',
      'typography'=>'Typography',
      'modal'=>'Modal',
      'labels'=>'Reservation labels',
      'sharing'=>'Sharing & apply',
      'filters'=>'Filters & extras',
      'layout'=>'Layout builder',
      'embed'=>'Embed',
    ];
    $sub = self::subtab_nav('adoptables', $items);
    echo '<div class="plugin-suite-grid">';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='design'?'active':'').'" data-subtab="design"><h2>Design</h2><table class="form-table">';
    foreach (['brand_color'=>'Brand colour','background_color'=>'Background colour','modal_divider_color'=>'Modal divider colour','card_border_color'=>'Card border colour'] as $k=>$label){ ob_start(); self::colour_input('adoptables',$k,$o[$k]); self::row($label, ob_get_clean()); }
    foreach ([['paw_opacity','Paw print opacity',0,0.25,0.01],['paw_count','Paw print count',0,80,1],['font_family','Font family',0,0,0],['card_padding','Card padding (px)',0,120,1],['card_radius','Card corner radius (px)',0,120,1],['card_border_weight','Card border weight (px)',0,20,1]] as $r){ ob_start(); if($r[0]==='font_family') self::text_input('adoptables',$r[0],$o[$r[0]]); else self::number_input('adoptables',$r[0],$o[$r[0]],$r[2],$r[3],$r[4]); self::row($r[1], ob_get_clean()); }
    ob_start(); self::checkbox_input('adoptables','card_border_enabled',$o['card_border_enabled'],'Show card border'); self::row('Card border', ob_get_clean());
    ob_start(); self::select_input('adoptables','display_style',$o['display_style'] ?? 'classic',['classic'=>'Classic','compact'=>'Compact','list'=>'List']); self::row('Display style', ob_get_clean());
    echo '</table></div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='text'?'active':'').'" data-subtab="text"><h2>Text</h2><table class="form-table">';
    foreach (['title_text'=>'Title text','subtitle_text'=>'Subtitle text','footer_text'=>'Footer text','loading_status_text'=>'Loading status text','loading_page_label_text'=>'Loading page label text','tips_text'=>'Tips text (modal)'] as $k=>$label){ ob_start(); if($k==='footer_text') self::textarea_input('adoptables',$k,$o[$k],3); else self::text_input('adoptables',$k,$o[$k]); self::row($label, ob_get_clean()); }
    echo '</table></div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='responsive'?'active':'').'" data-subtab="responsive"><h2>Responsive</h2><table class="form-table">';
    foreach ([['Mobile columns / rows','cols_mobile','rows_mobile'],['Tablet columns / rows','cols_tablet','rows_tablet'],['PC columns / rows','cols_desktop','rows_desktop']] as $r){ ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('adoptables',$r[1],$o[$r[1]],1,12); self::number_input('adoptables',$r[2],$o[$r[2]],1,12); echo '</div>'; self::row($r[0], ob_get_clean()); }
    foreach ([['Mobile gap X / Y','gap_x_mobile','gap_y_mobile'],['Tablet gap X / Y','gap_x_tablet','gap_y_tablet'],['PC gap X / Y','gap_x_desktop','gap_y_desktop']] as $r){ ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('adoptables',$r[1],$o[$r[1]],0,200); self::number_input('adoptables',$r[2],$o[$r[2]],0,200); echo '</div>'; self::row($r[0], ob_get_clean()); }
    ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('adoptables','card_scale_mobile',$o['card_scale_mobile'],50,200); self::number_input('adoptables','card_scale_tablet',$o['card_scale_tablet'],50,200); self::number_input('adoptables','card_scale_desktop',$o['card_scale_desktop'],50,200); echo '</div>'; self::row('Card scale M / T / PC', ob_get_clean());
    ob_start(); self::checkbox_input('adoptables','show_top_navigation',$o['show_top_navigation'] ?? 1,'Show the upper previous/next navigation above the cards'); self::row('Upper navigation', ob_get_clean());
    echo '</table></div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='typography'?'active':'').'" data-subtab="typography"><h2>Typography</h2>';
    self::render_typography_table('adoptables', ['heading'=>'Heading','subheading'=>'Subheading','footer'=>'Footer','page_label'=>'Page label','modal_name'=>'Modal name','modal_meta'=>'Modal meta','modal_desc'=>'Modal description','tips'=>'Tips'], $o);
    echo '</div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='modal'?'active':'').'" data-subtab="modal"><h2>Modal</h2><table class="form-table">';
    foreach ([['modal_max_width','Modal max width (px)',320,1600],['modal_divider_thickness','Modal divider thickness (px)',0,100],['modal_divider_radius','Modal divider shape radius (px)',0,999]] as $r){ ob_start(); self::number_input('adoptables',$r[0],$o[$r[0]],$r[2],$r[3]); self::row($r[1], ob_get_clean()); }
    ob_start(); self::checkbox_input('adoptables','enable_modals',$o['enable_modals'] ?? 1,'Enable adoptable animal modals'); self::row('Adoptable modals', ob_get_clean());
    ob_start(); self::textarea_input('adoptables','modal_global_text',$o['modal_global_text'] ?? '',6); self::row('Modal global text', ob_get_clean());
    echo '</table></div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='labels'?'active':'').'" data-subtab="labels"><h2>Reservation labels</h2><table class="form-table">';
    ob_start(); self::checkbox_input('adoptables','show_reservation_label',$o['show_reservation_label'],'Show reservation label on cards and modal'); self::row('Show reservation label', ob_get_clean());
    ob_start(); self::checkbox_input('adoptables','show_pending_reservation_label',$o['show_pending_reservation_label'] ?? 1,'Show Pending Adoption labels'); self::row('Pending Adoption labels', ob_get_clean());
    ob_start(); self::checkbox_input('adoptables','show_other_reservation_label',$o['show_other_reservation_label'] ?? 1,'Show other active reservation labels'); self::row('Other reservation labels', ob_get_clean());
    foreach (['reservation_pending_label'=>'Pending Adoption label text','reservation_active_label'=>'Other active reservation label text'] as $k=>$label){ ob_start(); self::text_input('adoptables',$k,$o[$k]); self::row($label, ob_get_clean()); }
    ob_start(); self::select_input('adoptables','reservation_label_halign',$o['reservation_label_halign'],['left'=>'Left','center'=>'Center','right'=>'Right']); self::row('Reservation label horizontal alignment', ob_get_clean());
    ob_start(); self::select_input('adoptables','reservation_label_valign',$o['reservation_label_valign'],['top'=>'Top','bottom'=>'Bottom']); self::row('Reservation label vertical alignment', ob_get_clean());
    echo '</table></div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='sharing'?'active':'').'" data-subtab="sharing"><h2>Sharing & apply</h2><table class="form-table">';
    foreach (['share_button_text'=>'Share button text','share_copied_text'=>'Share copied text','apply_button_text'=>'Apply button text','apply_form_shortcode'=>'Form shortcode tag','modal_contact_url'=>'Contact page URL'] as $k=>$label){ ob_start(); self::text_input('adoptables',$k,$o[$k] ?? ''); self::row($label, ob_get_clean()); }
    ob_start(); self::checkbox_input('adoptables','enable_deep_links',$o['enable_deep_links'] ?? 1,'Enable direct links and sharing to each cat modal'); self::row('Deep links and share', ob_get_clean());
    ob_start(); self::checkbox_input('adoptables','enable_apply_button',$o['enable_apply_button'] ?? 1,'Show Apply button in the modal'); self::row('Apply button', ob_get_clean());
    echo '</table></div>';

    echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='filters'?'active':'').'" data-subtab="filters"><h2>Filters & extras</h2><table class="form-table">';
    ob_start(); self::checkbox_input('adoptables','enable_filters',$o['enable_filters'] ?? 1,'Show filter controls'); self::row('Enable filters', ob_get_clean());
    foreach (['enable_filter_age'=>'Age','enable_filter_sex'=>'Sex','enable_filter_breed'=>'Breed','enable_exclude_pending_filter'=>'Hide pending adoption','enable_modal_slideshow_controls'=>'Enable modal slideshow controls','enable_favourites'=>'Enable favourites','detect_bonded_from_description'=>'Detect bonded from description','detect_indoor_only_from_description'=>'Detect indoor only from description'] as $k=>$label){ ob_start(); self::checkbox_input('adoptables',$k,$o[$k] ?? 0,$label); self::row($label, ob_get_clean()); }
    foreach (['filter_age_label'=>'Age filter label','filter_sex_label'=>'Sex filter label','filter_breed_label'=>'Breed filter label','filter_exclude_pending_label'=>'Hide pending label','favourites_label_text'=>'Favourites label','show_only_favourites_label'=>'Show favourites label','compare_button_text'=>'Compare button text','bonded_label_text'=>'Bonded badge label','indoor_only_label_text'=>'Indoor only badge label','fallback_description'=>'Fallback description'] as $k=>$label){ ob_start(); if($k==='fallback_description') self::textarea_input('adoptables',$k,$o[$k] ?? '',3); else self::text_input('adoptables',$k,$o[$k] ?? ''); self::row($label, ob_get_clean()); }
    foreach (['bonded_label_bg_color'=>'Bonded badge background','bonded_label_text_color'=>'Bonded badge text','indoor_only_label_bg_color'=>'Indoor badge background','indoor_only_label_text_color'=>'Indoor badge text'] as $k=>$label){ ob_start(); self::colour_input('adoptables',$k,$o[$k] ?? '#ffffff'); self::row($label, ob_get_clean()); }
    ob_start(); self::select_input('adoptables','favourite_button_position',$o['favourite_button_position'] ?? 'top_left',['top_left'=>'Top left','top_right'=>'Top right','bottom_left'=>'Bottom left','bottom_right'=>'Bottom right','hidden'=>'Hidden']); self::row('Favourite button position', ob_get_clean());
    echo '</table></div>';

    if ($sub === 'embed') { self::render_embed_card('adoptables','Adoptables', self::sanitize_shortcode_tag(self::get_settings()['shortcodes']['adoptables'] ?? 'adoptables'), 'Place your available animals on adoption pages.'); self::preview_frame('adoptables'); } else { echo '<div class="plugin-suite-card plugin-suite-subsection '.($sub==='layout'?'active':'').'" data-subtab="layout"><h2>Adoptables card layout</h2>'; self::sortable_list(self::field_name('adoptables','builder_card_order'), preg_split('/\R+/', (string)($o['builder_card_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['image'=>'Image','reservation_badge'=>'Reservation badge','name_meta'=>'Name and meta','breed_line'=>'Breed line','favourite_button'=>'Favourite button']); echo '<h2>Adoptables modal layout</h2>'; self::sortable_list(self::field_name('adoptables','builder_modal_order'), preg_split('/\R+/', (string)($o['builder_modal_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['gallery'=>'Gallery','badges'=>'Badges','info_cards'=>'Info cards','tips'=>'Tips','description'=>'Description','global_text'=>'Global text','contact_footer'=>'Contact footer','custom_buttons'=>'Custom buttons']); echo '<h2>Header actions</h2>'; self::sortable_list(self::field_name('adoptables','builder_header_actions'), preg_split('/\R+/', (string)($o['builder_header_actions'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['apply'=>'Apply','favourite'=>'Favourite','share'=>'Share','close'=>'Close']); echo '</div>'; }

    echo '</div>';
    self::form_end();
  }

  private static function render_adopted_tab($o, $g) {
    self::form_start('adopted');
    $sub = self::subtab_nav('adopted', ['design'=>'Design','text'=>'Text','responsive'=>'Responsive','typography'=>'Typography','modal'=>'Modal','layout'=>'Layout builder','embed'=>'Embed']);
    echo '<div class="plugin-suite-grid">';

    if($sub==='design') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Design</h2><table class="form-table">';
      foreach (['brand_color'=>'Brand colour','background_color'=>'Background colour','text_primary_color'=>'Primary text colour','text_muted_color'=>'Muted text colour','card_border_color'=>'Card border colour'] as $k=>$label){ ob_start(); self::colour_input('adopted',$k,$o[$k]); self::row($label, ob_get_clean()); }
      foreach ([['paw_opacity','Paw print opacity',0,0.25,0.01],['paw_count','Paw print count',0,80,1],['font_family','Font family',0,0,0],['card_border_weight','Card border weight (px)',0,20,1]] as $r){ ob_start(); if($r[0]==='font_family') self::text_input('adopted',$r[0],$o[$r[0]]); else self::number_input('adopted',$r[0],$o[$r[0]],$r[2],$r[3],$r[4]); self::row($r[1], ob_get_clean()); }
      ob_start(); self::checkbox_input('adopted','card_border_enabled',$o['card_border_enabled'],'Show card border'); self::row('Card border', ob_get_clean());
      echo '</table></div>';
    } elseif($sub==='text') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Text</h2><table class="form-table">';
      foreach (['title_text'=>'Title text','subtitle_text'=>'Subtitle text','footer_text'=>'Footer text'] as $k=>$label){ ob_start(); if($k==='footer_text') self::textarea_input('adopted',$k,$o[$k],3); else self::text_input('adopted',$k,$o[$k]); self::row($label, ob_get_clean()); }
      echo '</table></div>';
    } elseif($sub==='responsive') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Responsive</h2><table class="form-table">';
      foreach ([['Mobile columns / rows','cols_mobile','rows_mobile'],['Tablet columns / rows','cols_tablet','rows_tablet'],['PC columns / rows','cols_desktop','rows_desktop']] as $r){ ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('adopted',$r[1],$o[$r[1]],1,12); self::number_input('adopted',$r[2],$o[$r[2]],1,12); echo '</div>'; self::row($r[0], ob_get_clean()); }
      ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('adopted','card_scale_mobile',$o['card_scale_mobile'],50,200); self::number_input('adopted','card_scale_tablet',$o['card_scale_tablet'],50,200); self::number_input('adopted','card_scale_desktop',$o['card_scale_desktop'],50,200); echo '</div>'; self::row('Card size M / T / PC', ob_get_clean());
      ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('adopted','card_radius',$o['card_radius'],0,200); self::number_input('adopted','card_padding',$o['card_padding'],0,200); self::number_input('adopted','button_radius',$o['button_radius'],0,200); echo '</div>'; self::row('Card radius / padding / button radius', ob_get_clean());
      ob_start(); self::number_input('adopted','min_year',$o['min_year'],2000,3000); self::row('Minimum year in dropdown', ob_get_clean());
      ob_start(); self::checkbox_input('adopted','show_top_navigation',$o['show_top_navigation'] ?? 1,'Show the upper previous/next navigation above the cards'); self::row('Upper navigation', ob_get_clean());
      echo '</table></div>';
    } elseif($sub==='typography') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Typography</h2>';
      self::render_typography_table('adopted', ['heading'=>'Heading','subheading'=>'Subheading','footer'=>'Footer','page_label'=>'Page label','card_name'=>'Card name','card_meta'=>'Card meta','badge'=>'Badge'], $o);
      echo '</div>';
    } elseif($sub==='modal') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Modal</h2><table class="form-table">';
      ob_start(); self::checkbox_input('adopted','enable_modals',$o['enable_modals'] ?? 1,'Enable adopted animal modals'); self::row('Adopted modals', ob_get_clean());
      ob_start(); self::checkbox_input('adopted','enable_deep_links',$o['enable_deep_links'] ?? 1,'Enable direct links and sharing to each adopted cat modal'); self::row('Deep links and share', ob_get_clean());
      foreach (['share_button_text'=>'Share button text','share_copied_text'=>'Share copied text'] as $k=>$label){ ob_start(); self::text_input('adopted',$k,$o[$k] ?? ''); self::row($label, ob_get_clean()); }
      ob_start(); self::textarea_input('adopted','modal_global_text',$o['modal_global_text'] ?? '',5); self::row('Modal text below story', ob_get_clean() . '<p class="description">Shown below the story section in adopted modals.</p>');
      ob_start(); self::checkbox_input('adopted','adoptables_cta_enabled',$o['adoptables_cta_enabled'] ?? 0,'Show an adoptables link section in adopted modals'); self::row('Adoptables link section', ob_get_clean());
      ob_start(); self::text_input('adopted','adoptables_cta_text',$o['adoptables_cta_text'] ?? ''); self::row('Adoptables link text', ob_get_clean());
      ob_start(); self::text_input('adopted','adoptables_cta_button_text',$o['adoptables_cta_button_text'] ?? ''); self::row('Adoptables button text', ob_get_clean());
      self::row('Adoptables page URL', '<input type="url" name="' . esc_attr(self::field_name('adopted','adoptables_cta_url')) . '" value="' . esc_attr($o['adoptables_cta_url'] ?? '') . '" class="regular-text code" placeholder="' . esc_attr(home_url('/adopt/')) . '" />');
      ob_start(); self::number_input('adopted','modal_max_width',$o['modal_max_width'] ?? 896,320,1600); self::row('Modal max width (px)', ob_get_clean());
      ob_start(); self::select_input('adopted','date_label_halign',$o['date_label_halign'],['left'=>'Left','right'=>'Right']); self::row('Date label horizontal alignment', ob_get_clean());
      ob_start(); self::select_input('adopted','date_label_valign',$o['date_label_valign'],['top'=>'Top','bottom'=>'Bottom']); self::row('Date label vertical alignment', ob_get_clean());
      echo '</table></div>';
    } elseif ($sub==='embed') {
      self::render_embed_card('adopted','Adopted', self::sanitize_shortcode_tag(self::get_settings()['shortcodes']['adopted'] ?? 'adopted'), 'Place adopted animals and success stories on your adopted page.'); self::preview_frame('adopted');
    } else {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Adopted card layout</h2>'; self::sortable_list(self::field_name('adopted','builder_card_order'), preg_split('/\R+/', (string)($o['builder_card_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['image'=>'Image','date_badge'=>'Adoption date badge','name_meta'=>'Name and meta','story_excerpt'=>'Story excerpt','share_button'=>'Share button']); echo '<h2>Adopted modal layout</h2>'; self::sortable_list(self::field_name('adopted','builder_modal_order'), preg_split('/\R+/', (string)($o['builder_modal_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['gallery'=>'Gallery','name_meta'=>'Name and meta','adoption_date'=>'Adoption date','story'=>'Story','global_text'=>'Global text','share_button'=>'Share button']); echo '</div>';
    }

    echo '</div>';
    self::form_end();
  }

  private static function render_stats_tab($o, $g) {
    self::form_start('stats');
    $sub = self::subtab_nav('stats', ['design'=>'Design','text'=>'Text','responsive'=>'Responsive','typography'=>'Typography','cards'=>'Cards','layout'=>'Layout builder','embed'=>'Embed']);
    echo '<div class="plugin-suite-grid">';

    if($sub==='design') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Design</h2><table class="form-table">';
      foreach (['brand_color'=>'Brand colour','background_color'=>'Background colour','card_border_color'=>'Card border colour'] as $k=>$label){ ob_start(); self::colour_input('stats',$k,$o[$k]); self::row($label, ob_get_clean()); }
      foreach ([['paw_opacity','Paw print opacity',0,0.25,0.01],['paw_count','Paw print count',0,80,1],['font_family','Font family',0,0,0],['card_radius','Card corner radius (px)',0,200,1],['card_padding','Card padding (px)',0,200,1],['card_border_weight','Card border weight (px)',0,20,1]] as $r){ ob_start(); if($r[0]==='font_family') self::text_input('stats',$r[0],$o[$r[0]]); else self::number_input('stats',$r[0],$o[$r[0]],$r[2],$r[3],$r[4]); self::row($r[1], ob_get_clean()); }
      ob_start(); self::checkbox_input('stats','card_border_enabled',$o['card_border_enabled'],'Show card border'); self::row('Card border', ob_get_clean());
      ob_start(); self::select_input('stats','layout_mode',$o['layout_mode'],['grid'=>'Grid','one_row'=>'One row on tablet / PC']); self::row('Layout mode', ob_get_clean());
      echo '</table></div>';
    } elseif($sub==='text') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Text</h2><table class="form-table">';
      foreach (['title_text'=>'Title text','year_label_prefix'=>'Year label prefix','min_year'=>'Minimum year','footer_text'=>'Footer text'] as $k=>$label){ ob_start(); if($k==='footer_text') self::textarea_input('stats',$k,$o[$k],3); elseif($k==='min_year') self::number_input('stats',$k,$o[$k],2000,3000); else self::text_input('stats',$k,$o[$k]); self::row($label, ob_get_clean()); }
      echo '</table></div>';
    } elseif($sub==='responsive') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Responsive</h2><table class="form-table">';
      foreach ([['Mobile columns / rows','cols_mobile','rows_mobile'],['Tablet columns / rows','cols_tablet','rows_tablet'],['PC columns / rows','cols_desktop','rows_desktop']] as $r){ ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('stats',$r[1],$o[$r[1]],1,12); self::number_input('stats',$r[2],$o[$r[2]],1,12); echo '</div>'; self::row($r[0], ob_get_clean()); }
      foreach ([['Card width M / T / PC','card_w_mobile','card_w_tablet','card_w_desktop'],['Card height M / T / PC','card_h_mobile','card_h_tablet','card_h_desktop']] as $r){ ob_start(); echo '<div class="plugin-suite-inline">'; self::number_input('stats',$r[1],$o[$r[1]],0,1600); self::number_input('stats',$r[2],$o[$r[2]],0,1600); self::number_input('stats',$r[3],$o[$r[3]],0,1600); echo '</div>'; self::row($r[0], ob_get_clean()); }
      echo '</table></div>';
    } elseif($sub==='typography') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Typography</h2><table class="plugin-suite-table"><thead><tr><th>Text</th><th>Mobile</th><th>Tablet</th><th>PC</th></tr></thead><tbody>';
      foreach (['heading'=>'Heading','subheading'=>'Subheading','paragraph'=>'Paragraph'] as $slug=>$label){ echo '<tr><td>'.esc_html($label).'</td><td>'; self::number_input('stats','fs_'.$slug.'_mobile',$o['fs_'.$slug.'_mobile'],10,80); echo '</td><td>'; self::number_input('stats','fs_'.$slug.'_tablet',$o['fs_'.$slug.'_tablet'],10,80); echo '</td><td>'; self::number_input('stats','fs_'.$slug.'_desktop',$o['fs_'.$slug.'_desktop'],10,80); echo '</td></tr>'; }
      echo '</tbody></table></div>';
    } elseif($sub==='cards') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Cards</h2><table class="form-table">'; ob_start(); self::textarea_input('stats','card_order',$o['card_order'],7); self::row('Card order', ob_get_clean()); echo '</table><table class="plugin-suite-table"><thead><tr><th>Card</th><th>Heading</th><th>Caption</th><th>Icon</th></tr></thead><tbody>';
      foreach (['brought','adopted','vaccinated','neutered','chipped','in_care'] as $card){ echo '<tr><td><code>'.esc_html($card).'</code></td><td>'; self::text_input('stats','label_'.$card,$o['label_'.$card]); echo '</td><td>'; self::text_input('stats','caption_'.$card,$o['caption_'.$card]); echo '</td><td>'; self::select_input('stats','icon_'.$card,$o['icon_'.$card], self::icon_options()); echo '</td></tr>'; }
      echo '</tbody></table></div>';
    } elseif ($sub==='embed') {
      self::render_embed_card('stats','Statistics', self::sanitize_shortcode_tag(self::get_settings()['shortcodes']['statistics'] ?? 'stats'), 'Place rescue impact statistics on your website.'); self::preview_frame('stats');
    } else {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Statistics card order</h2>'; self::sortable_list(self::field_name('stats','builder_card_order'), preg_split('/\R+/', (string)($o['builder_card_order'] ?? $o['card_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['brought'=>'Brought In','adopted'=>'Adopted','vaccinated'=>'Vaccinated','neutered'=>'Neutered','chipped'=>'Microchipped','in_care'=>'In Our Care']); echo '</div>';
    }

    echo '</div>';
    self::form_end();
  }


  private static function featured_settings($settings) {
    $defaults = self::default_settings()['featured'];
    $legacy = isset($settings['widgets']) && is_array($settings['widgets']) ? $settings['widgets'] : [];
    $featured = isset($settings['featured']) && is_array($settings['featured']) ? $settings['featured'] : [];
    $mapped = [];
    foreach (['shortcode'=>'featured_shortcode','enabled'=>'featured_enabled','mode'=>'featured_mode','manual_id'=>'featured_manual_id','title_text'=>'featured_title_text','subtitle_text'=>'featured_subtitle_text','button_text'=>'featured_button_text','layout_order'=>'featured_layout_order'] as $new => $old) {
      if (array_key_exists($old, $legacy) && !array_key_exists($new, $featured)) $mapped[$new] = $legacy[$old];
    }
    return array_merge($defaults, $mapped, $featured);
  }

  private static function render_embed_card($module, $label, $tag, $description) {
    $name = in_array($module, ['adoptables','adopted','stats'], true) ? 'suite[shortcodes][' . ($module === 'stats' ? 'statistics' : $module) . ']' : self::field_name($module, 'shortcode');
    echo '<div class="plugin-suite-card"><h2>Embed</h2><p class="description">'.esc_html($description).'</p><table class="form-table"><tr><th>Shortcode</th><td><input type="text" name="'.esc_attr($name).'" class="regular-text code" value="'.esc_attr($tag).'" /> <button type="button" class="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(\'['.esc_js($tag).']\')">Copy shortcode</button><p class="description">Use <code>['.esc_html($tag).']</code> on a page, post or page-builder shortcode block.</p></td></tr></table><h3>Parameters</h3><p class="description">This embed works without required parameters. Configure display, wording and behaviour in the module settings above, then paste the shortcode wherever the feature should appear.</p><h3>Example</h3><p><code>['.esc_html($tag).']</code></p></div>';
  }

  private static function render_featured_tab($settings) {
    $f = self::featured_settings($settings);
    self::form_start('featured');
    $sub = self::subtab_nav('featured', ['dashboard'=>'Dashboard','layouts'=>'Layouts','appearance'=>'Appearance','filters'=>'Filters','embed'=>'Embed','analytics'=>'Analytics','help'=>'Help']);
    echo '<div class="plugin-suite-grid">';
    if ($sub === 'layouts') {
      echo '<div class="plugin-suite-card"><h2>Featured Animal layout</h2>'; self::sortable_list(self::field_name('featured','layout_order'), preg_split('/\R+/', (string)($f['layout_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['image'=>'Image','title'=>'Title','meta'=>'Animal details','button'=>'View button']); echo '</div>'; self::preview_frame_custom('featured','Featured Animal preview');
    } elseif ($sub === 'appearance') {
      echo '<div class="plugin-suite-card"><h2>Appearance</h2><p class="description">Featured Animal inherits Adoptables colours and button styling so website displays stay consistent.</p></div>'; self::preview_frame_custom('featured','Featured Animal preview');
    } elseif ($sub === 'filters') {
      echo '<div class="plugin-suite-card"><h2>Animal selection</h2><table class="form-table">'; ob_start(); self::select_input('featured','mode',$f['mode'],['random'=>'Random available animal','newest'=>'Newest intake','manual'=>'Specific animal ID']); self::row('Selection method', ob_get_clean()); ob_start(); self::text_input('featured','manual_id',$f['manual_id']); self::row('Animal ID', ob_get_clean()); echo '</table></div>';
    } elseif ($sub === 'embed') {
      self::render_embed_card('featured','Featured Animal', self::sanitize_shortcode_tag($f['shortcode'] ?? 'featured_animal'), 'Place a single highlighted adoptable animal on a homepage, sidebar or campaign page.'); self::preview_frame_custom('featured','Featured Animal live preview');
    } elseif ($sub === 'analytics') {
      $analytics = self::get_analytics(); echo '<div class="plugin-suite-card"><h2>Featured Animal analytics</h2><p>Tracked clicks: <strong>'.esc_html((string)($analytics['featured_widget_click'] ?? 0)).'</strong></p></div>';
    } elseif ($sub === 'help') {
      echo '<div class="plugin-suite-card"><h2>Featured Animal help</h2><p>Use Featured Animal when you want one adoptable animal to receive extra attention. Choose random for freshness, newest intake for urgency, or manual ID for a campaign.</p></div>';
    } else {
      echo '<div class="plugin-suite-card"><h2>Featured Animal</h2><table class="form-table">'; ob_start(); self::checkbox_input('featured','enabled',$f['enabled'],'Enable Featured Animal shortcode'); self::row('Enable', ob_get_clean()); foreach (['shortcode'=>'Shortcode','title_text'=>'Title','subtitle_text'=>'Subtitle','button_text'=>'Button text'] as $k=>$label){ ob_start(); self::text_input('featured',$k,$f[$k]); self::row($label, ob_get_clean()); } echo '</table></div>'; self::preview_frame_custom('featured','Featured Animal preview');
    }
    echo '</div>'; self::form_end();
  }

  private static function render_quiz_tab($settings) {
    $q = $settings['quiz'];
    self::form_start('quiz');
    $sub = self::subtab_nav('quiz', ['settings'=>'Settings','questions'=>'Questions','mapping'=>'Answer mapping','preview'=>'Preview scoring']);
    echo '<div class="plugin-suite-grid">';
    if($sub==='settings') {
      echo '<div class="plugin-suite-card"><h2>Quiz settings</h2><table class="form-table">';
      ob_start(); self::checkbox_input('quiz','quiz_enabled',$q['quiz_enabled'],'Enable adoption match quiz shortcode'); self::row('Enable', ob_get_clean());
      foreach (['quiz_shortcode'=>'Shortcode','quiz_title_text'=>'Title text','quiz_intro_text'=>'Intro text','results_title_text'=>'Results heading','results_empty_text'=>'No results text'] as $k=>$label){ ob_start(); self::text_input('quiz',$k,$q[$k]); self::row($label, ob_get_clean()); }
      echo '</table></div>';
    } elseif($sub==='questions') {
      echo '<div class="plugin-suite-card"><h2>Questions</h2><table class="form-table">';
      foreach (['q1_text'=>'Question 1 text','q2_text'=>'Question 2 text','q3_text'=>'Question 3 text'] as $k=>$label){ ob_start(); self::text_input('quiz',$k,$q[$k]); self::row($label, ob_get_clean()); }
      ob_start(); self::textarea_input('quiz','age_categories',$q['age_categories'] ?? '',5); self::row('Age categories', ob_get_clean());
      ob_start(); echo '<p class="description">One category per line in the format <code>Label|min|max</code> using years. Leave max blank for no upper limit.</p>'; self::row('Age category format', ob_get_clean());
      foreach (['q2_female_label'=>'Female label','q2_male_label'=>'Male label','q2_either_label'=>'Question 2 no preference label','q3_yes_label'=>'Indoor only yes label','q3_no_label'=>'Question 3 no preference label'] as $k=>$label){ ob_start(); self::text_input('quiz',$k,$q[$k]); self::row($label, ob_get_clean()); }
      ob_start(); self::checkbox_input('quiz','q3_hide',$q['q3_hide'],'Hide indoor-only question'); self::row('Question 3 visibility', ob_get_clean());
      echo '</table></div>';
    } elseif($sub==='mapping') {
      echo '<div class="plugin-suite-card"><h2>Visual answer mapping</h2><p class="description">Map each answer key to a rescue management field and weight. Format: answer_key|field|weight.</p><textarea class="large-text code" rows="8" name="'.esc_attr(self::field_name('quiz','answer_mappings')).'">'.esc_textarea($q['answer_mappings'] ?? '').'</textarea></div>';
      echo '<div class="plugin-suite-card"><h2>Question order</h2>'; self::sortable_list(self::field_name('quiz','question_order'), preg_split('/\R+/', (string)($q['question_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['age'=>'Age preference','sex'=>'Sex preference','indoor'=>'Indoor-only','bonded'=>'Bonded pair','good_cats'=>'Good with cats','good_dogs'=>'Good with dogs','good_children'=>'Good with children']); echo '</div>';
    } elseif($sub==='preview') {
      echo '<div class="plugin-suite-card"><h2>Scoring preview</h2><p class="description">The preview below uses the saved shortcode renderer so administrators can test question order and answer mappings before publishing.</p></div>'; self::preview_frame_custom('quiz','Match Quiz preview');
    } else {
      echo '<div class="plugin-suite-card"><h2>Quiz builder</h2><p class="description">Use Questions to edit wording, Mapping to connect answers to rescue-management fields, and Preview scoring to test the quiz.</p></div>';
    }
    echo '</div>';
    self::form_end();
  }

  private static function render_forms_tab($settings) {
    self::form_start('forms'); $items=$settings['forms']['items'];
    echo '<div class="plugin-suite-grid"><div class="plugin-suite-card" style="grid-column:span 2;"><h2>Forms</h2><table class="form-table">'; ob_start(); self::text_input('forms','account',$settings['forms']['account']); self::row('Shelter Manager account', ob_get_clean()); echo '</table><table class="plugin-suite-table"><thead><tr><th>#</th><th>Shortcode</th><th>Form ID</th><th>Use</th></tr></thead><tbody>';
    for($i=0;$i<10;$i++){ $row=$items[$i] ?? ['shortcode'=>'','form_id'=>'']; $tag=self::sanitize_shortcode_tag($row['shortcode'] ?? ''); echo '<tr><td>'.($i+1).'</td><td><input type="text" name="suite[forms][items]['.$i.'][shortcode]" value="'.esc_attr($row['shortcode'] ?? '').'" class="regular-text" /></td><td><input type="text" name="suite[forms][items]['.$i.'][form_id]" value="'.esc_attr($row['form_id'] ?? '').'" class="small-text" /></td><td>'.($tag?'<code>['.esc_html($tag).']</code>':'').'</td></tr>'; }
    echo '</tbody></table></div>';
    echo '<div class="plugin-suite-card" style="grid-column:span 2;"><h2>Platform limitations</h2><textarea class="large-text code" rows="6" name="'.esc_attr(self::field_name('forms','platform_support_notes')).'">'.esc_textarea($settings['forms']['platform_support_notes'] ?? '').'</textarea><p class="description">Format: platform|plain-English support or limitation note. These notes keep platform-specific behaviour explicit for administrators.</p></div>';
    $global = $settings['global'] ?? [];
    echo '<div class="plugin-suite-card" style="grid-column:span 2;"><h2>Application / enquiry integrations</h2><p class="description">Embedded ASM or third-party forms submit inside their own systems. The suite does not capture applicant answers; it records privacy-safe application intent events when visitors open the application flow.</p><table class="form-table">';
    ob_start(); self::checkbox_input('global','enquiry_log_enabled',$global['enquiry_log_enabled'] ?? 1,'Log application intent events'); self::row('Enquiry event log', ob_get_clean());
    self::row('Notification email', '<input type="email" name="suite[global][enquiry_email]" value="' . esc_attr($global['enquiry_email'] ?? '') . '" class="regular-text" /><p class="description">Optional. Sends a lightweight notification when someone opens the application flow.</p>');
    self::row('Webhook URL', '<input type="url" name="suite[global][enquiry_webhook_url]" value="' . esc_attr($global['enquiry_webhook_url'] ?? '') . '" class="regular-text code" placeholder="https://hooks.zapier.com/..." /><p class="description">Optional Google Sheets/Zapier/CRM webhook. Payload contains time, page, event and hashed IP only.</p>');
    self::row('Webhook secret', '<input type="password" name="suite[global][enquiry_webhook_secret]" value="' . esc_attr($global['enquiry_webhook_secret'] ?? '') . '" class="regular-text" autocomplete="new-password" /><p class="description">Optional HMAC secret sent in X-ASM-Suite-Signature.</p>');
    ob_start(); self::select_input('global','analytics_consent_mode',$global['analytics_consent_mode'] ?? 'immediate',['immediate'=>'Track immediately','cookie'=>'Wait for consent cookie']); self::row('Consent-aware tracking', ob_get_clean() . '<p class="description">Choose whether enquiry-intent analytics fires immediately or only after a cookie/consent plugin sets the named cookie.</p>');
    self::row('Consent cookie name', '<input type="text" name="suite[global][analytics_consent_cookie]" value="' . esc_attr($global['analytics_consent_cookie'] ?? '') . '" class="regular-text code" placeholder="cookie_consent" /><p class="description">Used only when consent mode waits for a cookie.</p>');
    $queue = get_option(self::webhook_queue_key(), []); if (!is_array($queue)) $queue = [];
    echo '</table><p><a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=plugin_ui_suite_export_enquiries'), 'plugin_ui_suite_export_enquiries')) . '">Export enquiry intent CSV</a> <a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=plugin_ui_suite_test_enquiry_integration'), 'plugin_ui_suite_test_enquiry_integration')) . '">Send test webhook/email</a> <a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=plugin_ui_suite_retry_webhooks'), 'plugin_ui_suite_retry_webhooks')) . '">Retry queued webhooks</a></p><p class="description">Queued webhook retries: ' . esc_html((string)count($queue)) . (!empty($queue) ? ' · Next retry: ' . esc_html((string)($queue[0]['_next_retry'] ?? 'as soon as possible')) . ' · Last reason: ' . esc_html((string)($queue[count($queue)-1]['_retry_reason'] ?? 'Unknown')) : '') . '</p>'; self::render_webhook_audit_table(); echo '</div></div>';
    self::form_end();
  }

  private static function render_layout_tab($settings) {
    self::form_start('layout');
    $sub = self::subtab_nav('layout', ['adoptables'=>'Adoptables','adopted'=>'Adopted','statistics'=>'Statistics','donation'=>'Donation Widget','forms'=>'Forms','quiz'=>'Match Quiz','featured'=>'Featured Animal','species'=>'Species']);
    echo '<div class="plugin-suite-grid">';
    if ($sub === 'adoptables') {
      echo '<div class="plugin-suite-card"><h2>Adoptables card layout</h2>'; self::sortable_list(self::field_name('adoptables','builder_card_order'), preg_split('/\R+/', (string)($settings['adoptables']['builder_card_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['image'=>'Image','reservation_badge'=>'Reservation badge','name_meta'=>'Name and meta','breed_line'=>'Breed line','favourite_button'=>'Favourite button']); echo '</div>';
      echo '<div class="plugin-suite-card"><h2>Adoptables modal layout</h2>'; self::sortable_list(self::field_name('adoptables','builder_modal_order'), preg_split('/\R+/', (string)($settings['adoptables']['builder_modal_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['gallery'=>'Gallery','badges'=>'Badges','info_cards'=>'Info cards','tips'=>'Tips','description'=>'Description','global_text'=>'Global text','contact_footer'=>'Contact footer','custom_buttons'=>'Custom buttons']); echo '</div>';
      echo '<div class="plugin-suite-card"><h2>Adoptables header actions</h2>'; self::sortable_list(self::field_name('adoptables','builder_header_actions'), preg_split('/\R+/', (string)($settings['adoptables']['builder_header_actions'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['apply'=>'Apply','favourite'=>'Favourite','share'=>'Share','close'=>'Close']); echo '</div>'; self::preview_frame('adoptables');
    } elseif ($sub === 'adopted') {
      echo '<div class="plugin-suite-card"><h2>Adopted card layout</h2>'; self::sortable_list(self::field_name('adopted','builder_card_order'), preg_split('/\R+/', (string)($settings['adopted']['builder_card_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['image'=>'Image','date_badge'=>'Adoption date badge','name_meta'=>'Name and meta','story_excerpt'=>'Story excerpt','share_button'=>'Share button']); echo '</div>';
      echo '<div class="plugin-suite-card"><h2>Adopted modal layout</h2>'; self::sortable_list(self::field_name('adopted','builder_modal_order'), preg_split('/\R+/', (string)($settings['adopted']['builder_modal_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['gallery'=>'Gallery','name_meta'=>'Name and meta','adoption_date'=>'Adoption date','story'=>'Story','global_text'=>'Global text','share_button'=>'Share button']); echo '</div>'; self::preview_frame('adopted');
    } elseif ($sub === 'statistics') {
      echo '<div class="plugin-suite-card"><h2>Statistics card order</h2>'; self::sortable_list(self::field_name('stats','builder_card_order'), preg_split('/\R+/', (string)($settings['stats']['builder_card_order'] ?? $settings['stats']['card_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['brought'=>'Brought In','adopted'=>'Adopted','vaccinated'=>'Vaccinated','neutered'=>'Neutered','chipped'=>'Microchipped','in_care'=>'In Our Care']); echo '</div>'; self::preview_frame('stats');
    } elseif ($sub === 'donation') {
      echo '<div class="plugin-suite-card"><h2>Donation widget builder</h2><p class="description">Donation Widget layout controls are now edited in Payments → Donation Widget inside this settings screen.</p></div>'; 
    } elseif ($sub === 'forms') {
      echo '<div class="plugin-suite-card"><h2>Forms layout</h2>'; self::sortable_list(self::field_name('forms','layout_order'), preg_split('/\R+/', (string)($settings['forms']['layout_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['intro'=>'Intro text','form_embed'=>'Form embed','privacy_note'=>'Privacy note','submit_guidance'=>'Submission guidance']); echo '</div>';
      echo '<div class="plugin-suite-card"><h2>Platform support notes</h2><textarea class="large-text code" rows="6" name="'.esc_attr(self::field_name('forms','platform_support_notes')).'">'.esc_textarea($settings['forms']['platform_support_notes'] ?? '').'</textarea><p class="description">Format: platform|plain-English limitation or support note.</p></div>';
    } elseif ($sub === 'quiz') {
      echo '<div class="plugin-suite-card"><h2>Match Quiz question order</h2>'; self::sortable_list(self::field_name('quiz','question_order'), preg_split('/\R+/', (string)($settings['quiz']['question_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['age'=>'Age preference','sex'=>'Sex preference','indoor'=>'Indoor-only','bonded'=>'Bonded pair','good_cats'=>'Good with cats','good_dogs'=>'Good with dogs','good_children'=>'Good with children']); echo '</div>';
      echo '<div class="plugin-suite-card"><h2>Answer mapping</h2><textarea class="large-text code" rows="8" name="'.esc_attr(self::field_name('quiz','answer_mappings')).'">'.esc_textarea($settings['quiz']['answer_mappings'] ?? '').'</textarea><p class="description">Format: answer_key|rescue management field|weight.</p></div>'; self::preview_frame_custom('quiz','Quiz preview');
    } elseif ($sub === 'species') {
      echo '<div class="plugin-suite-card"><h2>Species support</h2><table class="form-table">'; self::row('Available species','<textarea class="large-text code" rows="4" name="'.esc_attr(self::field_name('global','supported_species')).'">'.esc_textarea($settings['global']['supported_species'] ?? '').'</textarea>'); self::row('Enabled species','<textarea class="large-text code" rows="4" name="'.esc_attr(self::field_name('global','enabled_species')).'">'.esc_textarea($settings['global']['enabled_species'] ?? '').'</textarea><p class="description">Use slugs such as cats, dogs, rabbits, birds, horses, small_animals, reptiles and other.</p>'); echo '</table></div>';
    } else {
      echo '<div class="plugin-suite-card"><h2>Featured Animal layout</h2>'; self::sortable_list(self::field_name('featured','layout_order'), preg_split('/\R+/', (string)(self::featured_settings($settings)['layout_order'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['image'=>'Image','title'=>'Title','meta'=>'Animal details','button'=>'View button']); echo '</div>'; self::preview_frame_custom('featured','Featured Animal preview');
    }
    echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Builder tools</h2><p class="description">Drag items to reorder. Save changes to persist. Clear a layout field and save to reset to defaults.</p></div></div>';
    self::form_end();
  }

  private static function get_proxy_settings($settings = null) {
    if (!is_array($settings)) $settings = self::get_settings();
    $defaults = self::default_settings()['proxy'];
    $proxy = isset($settings['proxy']) && is_array($settings['proxy']) ? array_merge($defaults, $settings['proxy']) : $defaults;
    return $proxy;
  }

  private static function define_proxy_constants_from_settings($settings = null) {
    $proxy = self::get_proxy_settings($settings ?: self::load_saved_settings());
    $pairs = [
      'ASM_BASE_URL' => $proxy['base_url'] ?? '',
      'ASM_ACCOUNT' => $proxy['account'] ?? '',
      'ASM_USERNAME' => $proxy['username'] ?? '',
      'ASM_PASSWORD' => $proxy['password'] ?? '',
    ];
    foreach ($pairs as $name => $value) {
      if (!defined($name) && $value !== '') define($name, $value);
    }
  }

  private static function module_versions() {
    return [
      'suite_core' => PLUGIN_SUITE_VERSION,
      'adoptables' => '15',
      'adopted' => '18',
      'statistics' => '11',
      'forms' => '1.0.0',
      'proxy' => '1.3.6',
    ];
  }

  private static function record_version_event($event) {
    $log = get_option(self::LOG_KEY, []);
    if (!is_array($log)) $log = [];
    array_unshift($log, [
      'time' => current_time('mysql'),
      'event' => sanitize_key($event),
      'suite_version' => self::module_versions()['suite_core'],
      'module_versions' => self::module_versions(),
    ]);
    $log = array_slice($log, 0, 25);
    update_option(self::LOG_KEY, $log, false);
  }

  private static function create_snapshot($reason = 'manual') {
    $snapshots = get_option(self::SNAP_KEY, []);
    if (!is_array($snapshots)) $snapshots = [];
    array_unshift($snapshots, [
      'id' => wp_generate_uuid4(),
      'time' => current_time('mysql'),
      'reason' => sanitize_key($reason),
      'suite' => self::exportable_settings(self::get_settings()),
      'legacy' => [
        'adoptables' => get_option('plugin_adoptables_ui_options', []),
        'adopted' => get_option('plugin_adopted_ui_options', []),
        'stats' => get_option('plugin_stats_ui_options', []),
      ],
    ]);
    $snapshots = array_slice($snapshots, 0, 10);
    update_option(self::SNAP_KEY, $snapshots, false);
  }

  private static function get_snapshots() {
    $snapshots = get_option(self::SNAP_KEY, []);
    return is_array($snapshots) ? $snapshots : [];
  }

  public static function handle_restore_snapshot() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_restore_snapshot');
    $id = sanitize_text_field($_POST['snapshot_id'] ?? '');
    $snapshots = self::get_snapshots();
    foreach ($snapshots as $snapshot) {
      if (($snapshot['id'] ?? '') !== $id) continue;
      self::create_snapshot('pre_restore');
      if (!empty($snapshot['suite']) && is_array($snapshot['suite'])) update_option(self::OPT_KEY, $snapshot['suite'], false);
      if (!empty($snapshot['legacy']['adoptables'])) update_option('plugin_adoptables_ui_options', $snapshot['legacy']['adoptables']);
      if (!empty($snapshot['legacy']['adopted'])) update_option('plugin_adopted_ui_options', $snapshot['legacy']['adopted']);
      if (!empty($snapshot['legacy']['stats'])) update_option('plugin_stats_ui_options', $snapshot['legacy']['stats']);
      self::record_version_event('snapshot_restore');
      wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'diagnostics','updated'=>'true','plugin_msg'=>'Snapshot restored'], admin_url('options-general.php')));
      exit;
    }
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'diagnostics','plugin_msg'=>'Snapshot not found'], admin_url('options-general.php')));
    exit;
  }

  public static function handle_delete_snapshot() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_delete_snapshot');
    $id = sanitize_text_field($_POST['snapshot_id'] ?? '');
    $snapshots = array_values(array_filter(self::get_snapshots(), function($snapshot) use ($id){ return ($snapshot['id'] ?? '') !== $id; }));
    update_option(self::SNAP_KEY, $snapshots, false);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'diagnostics','updated'=>'true','plugin_msg'=>'Snapshot deleted'], admin_url('options-general.php')));
    exit;
  }

  public static function handle_export_module() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_export_module');
    $module = sanitize_key($_POST['module'] ?? '');
    $settings = self::get_settings();
    $payload = ['module' => $module, 'version' => self::module_versions()[$module] ?? '', 'data' => self::exportable_module_settings($module, $settings[$module] ?? [])];
    nocache_headers();
    header('Content-Type: application/json; charset=' . get_bloginfo('charset'));
    header('Content-Disposition: attachment; filename=plugin-ui-suite-' . $module . '.json');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  public static function handle_import_module() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_import_module');
    $module = sanitize_key($_POST['module'] ?? '');
    if (empty($_FILES['import_file']['tmp_name'])) wp_die('No import file uploaded.');
    $data = json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true);
    if (!is_array($data) || ($data['module'] ?? '') !== $module || !is_array($data['data'] ?? null)) wp_die('Invalid module import file.');
    $settings = self::get_settings();
    self::create_snapshot('import_' . $module);
    $settings[$module] = array_replace_recursive(self::default_settings()[$module], $data['data']);
    if ($module === 'proxy') {
      $current = self::get_settings()['proxy'] ?? [];
      foreach (['account','username','password'] as $secret_key) {
        if (empty($settings[$module][$secret_key]) && !empty($current[$secret_key])) $settings[$module][$secret_key] = $current[$secret_key];
      }
    }
    update_option(self::OPT_KEY, $settings, false);
    self::sync_legacy_options($settings);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'diagnostics','updated'=>'true','plugin_msg'=>ucfirst($module).' imported'], admin_url('options-general.php')));
    exit;
  }



  public static function handle_save_pack() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_save_pack');
    $name = sanitize_text_field($_POST['pack_name'] ?? '');
    if ($name === '') $name = 'Settings pack ' . current_time('Y-m-d H:i');
    $packs = get_option('plugin_ui_suite_setting_packs', []);
    if (!is_array($packs)) $packs = [];
    $packs[sanitize_title($name)] = ['name'=>$name,'created'=>current_time('mysql'),'suite'=>self::exportable_settings(self::get_settings())];
    update_option('plugin_ui_suite_setting_packs', $packs, false);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'global','updated'=>'true','plugin_msg'=>'Settings pack saved'], admin_url('options-general.php'))); exit;
  }
  public static function handle_load_pack() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_load_pack');
    $id = sanitize_key($_POST['pack_id'] ?? '');
    $packs = get_option('plugin_ui_suite_setting_packs', []);
    if (!is_array($packs) || empty($packs[$id]['suite']) || !is_array($packs[$id]['suite'])) wp_die('Settings pack not found.');
    self::create_snapshot('load_pack');
    $merged = array_replace_recursive(self::default_settings(), $packs[$id]['suite']);
    $existing = self::get_settings();
    if (isset($existing['proxy']) && is_array($existing['proxy'])) foreach (['account','username','password'] as $secret_key) if (empty($merged['proxy'][$secret_key]) && !empty($existing['proxy'][$secret_key])) $merged['proxy'][$secret_key] = $existing['proxy'][$secret_key];
    if (isset($existing['global']) && is_array($existing['global'])) foreach (['custom_api_key','shelterluv_api_key','petpoint_username','petpoint_password'] as $secret_key) if (empty($merged['global'][$secret_key]) && !empty($existing['global'][$secret_key])) $merged['global'][$secret_key] = $existing['global'][$secret_key];
    update_option(self::OPT_KEY, $merged, false); self::sync_legacy_options($merged);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'global','updated'=>'true','plugin_msg'=>'Settings pack loaded'], admin_url('options-general.php'))); exit;
  }

  private static function exportable_settings($settings) {
    if (!is_array($settings)) return [];
    $copy = $settings;
    if (isset($copy['proxy']) && is_array($copy['proxy'])) {
      unset($copy['proxy']['account'], $copy['proxy']['username'], $copy['proxy']['password']);
    }
    if (isset($copy['global']) && is_array($copy['global'])) {
      unset(
        $copy['global']['custom_api_key'],
        $copy['global']['shelterluv_api_key'],
        $copy['global']['petpoint_username'],
        $copy['global']['petpoint_password'],
        $copy['global']['enquiry_webhook_secret']
      );
    }
    return $copy;
  }

  private static function exportable_module_settings($module, $data) {
    if (!is_array($data)) return [];
    if ($module === 'proxy') {
      unset($data['account'], $data['username'], $data['password']);
    }
    return $data;
  }

  public static function render_admin_notices() {
    if (!current_user_can('manage_options')) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || strpos((string)$screen->id, 'plugin-ui-suite') === false) return;
    $settings = self::get_settings();
    $source_status = self::data_source_status($settings);
    if (!empty($source_status['missing'])) {
      echo '<div class="notice notice-warning"><p>' . esc_html($source_status['label']) . ' is not fully configured. Missing: ' . esc_html(implode(', ', $source_status['missing'])) . '. Use the <a href="' . esc_url(add_query_arg(['page'=>'plugin-ui-suite-setup'], admin_url('options-general.php'))) . '">setup wizard</a> or the relevant settings tab.</p></div>';
    }
  }

  public static function maybe_redirect_to_setup_wizard() {
    if (!current_user_can('manage_options')) return;
    if (!get_option(self::WIZARD_KEY)) return;
    if (wp_doing_ajax()) return;
    $page = sanitize_key($_GET['page'] ?? '');
    if ($page === 'plugin-ui-suite-setup') return;
    delete_option(self::WIZARD_KEY);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite-setup'], admin_url('options-general.php')));
    exit;
  }

  public static function handle_setup_save() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_setup_save');
    $settings = self::get_settings();
    $global = wp_unslash($_POST['suite']['global'] ?? []);
    $adoptables = wp_unslash($_POST['suite']['adoptables'] ?? []);
    $adopted = wp_unslash($_POST['suite']['adopted'] ?? []);
    $quiz = wp_unslash($_POST['suite']['quiz'] ?? []);
    $pr = wp_unslash($_POST['suite']['proxy'] ?? []);
    $source = sanitize_key($global['data_source'] ?? ($settings['global']['data_source'] ?? 'asm'));
    $settings['global']['data_source'] = in_array($source, ['asm','custom_api','shelterluv','petpoint'], true) ? $source : 'asm';
    $settings['global']['custom_api_url'] = esc_url_raw($global['custom_api_url'] ?? ($settings['global']['custom_api_url'] ?? ''));
    $settings['global']['custom_api_key'] = sanitize_text_field((string)($global['custom_api_key'] ?? ($settings['global']['custom_api_key'] ?? '')));
    $settings['global']['custom_api_auth_header'] = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($global['custom_api_auth_header'] ?? ($settings['global']['custom_api_auth_header'] ?? 'X-API-Key')));
    foreach (['custom_api_adoptables_url','custom_api_adoptions_url','custom_api_report_url','custom_api_incare_url','custom_api_image_url','shelterluv_adoptables_url','shelterluv_adoptions_url','shelterluv_report_url','shelterluv_incare_url','shelterluv_image_url','petpoint_adoptables_url','petpoint_adoptions_url','petpoint_report_url','petpoint_incare_url','petpoint_image_url'] as $k) $settings['global'][$k] = esc_url_raw($global[$k] ?? ($settings['global'][$k] ?? ''));
    $settings['global']['field_map'] = sanitize_textarea_field($global['field_map'] ?? ($settings['global']['field_map'] ?? ''));
    $settings['global']['provider_profile'] = sanitize_key($global['provider_profile'] ?? ($settings['global']['provider_profile'] ?? ''));
    $settings['global']['preview_mode'] = !empty($global['preview_mode']) ? 1 : 0;
    $settings['global']['enquiry_log_enabled'] = !empty($global['enquiry_log_enabled']) ? 1 : 0;
    $settings['global']['enquiry_email'] = sanitize_email($global['enquiry_email'] ?? ($settings['global']['enquiry_email'] ?? ''));
    $settings['global']['enquiry_webhook_url'] = esc_url_raw($global['enquiry_webhook_url'] ?? ($settings['global']['enquiry_webhook_url'] ?? ''));
    $settings['global']['enquiry_webhook_secret'] = sanitize_text_field($global['enquiry_webhook_secret'] ?? ($settings['global']['enquiry_webhook_secret'] ?? ''));
    $settings['global']['shelterluv_api_key'] = sanitize_text_field((string)($global['shelterluv_api_key'] ?? ($settings['global']['shelterluv_api_key'] ?? '')));
    $settings['global']['petpoint_username'] = sanitize_text_field((string)($global['petpoint_username'] ?? ($settings['global']['petpoint_username'] ?? '')));
    $settings['global']['petpoint_password'] = sanitize_text_field((string)($global['petpoint_password'] ?? ($settings['global']['petpoint_password'] ?? '')));
    $settings['proxy']['base_url'] = esc_url_raw($pr['base_url'] ?? $settings['proxy']['base_url']);
    $settings['proxy']['account'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($pr['account'] ?? ''));
    $settings['proxy']['username'] = sanitize_text_field((string)($pr['username'] ?? ''));
    $settings['proxy']['password'] = (string)($pr['password'] ?? '');
    $settings['adoptables']['enable_filters'] = !empty($adoptables['enable_filters']) ? 1 : 0;
    $settings['adoptables']['enable_favourites'] = !empty($adoptables['enable_favourites']) ? 1 : 0;
    $settings['adopted']['enable_modals'] = !empty($adopted['enable_modals']) ? 1 : 0;
    $settings['quiz']['quiz_enabled'] = !empty($quiz['quiz_enabled']) ? 1 : 0;
    update_option(self::OPT_KEY, $settings, false);
    delete_option(self::WIZARD_KEY);
    self::sync_legacy_options($settings);
    self::record_version_event('setup_completed');
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'proxy','updated'=>'true','plugin_msg'=>'Setup completed'], admin_url('options-general.php')));
    exit;
  }

  public static function handle_proxy_test() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_proxy_test');
    $settings = self::get_settings();
    $source = sanitize_key($settings['global']['data_source'] ?? 'asm');
    $msg = 'Proxy test failed';

    if ($source === 'asm') {
      if (function_exists('plugin_asm_http_get') && function_exists('plugin_asm_user') && function_exists('plugin_asm_pass')) {
        $res = plugin_asm_http_get(['method'=>'json_adoptable_animals','username'=>plugin_asm_user(),'password'=>plugin_asm_pass()]);
        if (is_wp_error($res)) {
          $msg = 'Proxy test failed: ' . $res->get_error_message();
          set_transient('plugin_ui_suite_last_proxy_error', $res->get_error_message(), 3600);
        } else {
          $msg = 'ASM proxy connection successful';
          set_transient('plugin_ui_suite_last_proxy_error', '', 60);
          set_transient('plugin_ui_suite_last_proxy_success', current_time('mysql'), 86400);
        }
      }
    } elseif ($source === 'custom_api') {
      $url = $settings['global']['custom_api_adoptables_url'] ?? '';
      if (!$url && !empty($settings['global']['custom_api_url'])) $url = untrailingslashit($settings['global']['custom_api_url']) . '/adoptables';
      if (!$url) {
        $msg = 'Custom API test failed: adoptables endpoint is not configured';
        set_transient('plugin_ui_suite_last_proxy_error', $msg, 3600);
      } else {
        $headers = ['Accept' => 'application/json'];
        $header_name = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($settings['global']['custom_api_auth_header'] ?? 'X-API-Key'));
        $api_key = (string)($settings['global']['custom_api_key'] ?? '');
        if ($api_key !== '' && $header_name !== '') $headers[$header_name] = $api_key;
        if ($api_key !== '') $url = add_query_arg(['api_key' => $api_key], $url);
        $res = wp_remote_get($url, ['timeout'=>20,'headers'=>$headers]);
        if (is_wp_error($res)) {
          $msg = 'Custom API test failed: ' . $res->get_error_message();
          set_transient('plugin_ui_suite_last_proxy_error', $res->get_error_message(), 3600);
        } else {
          $code = wp_remote_retrieve_response_code($res);
          $body = wp_remote_retrieve_body($res);
          $data = json_decode($body, true);
          $items = is_array($data) && isset($data['items']) && is_array($data['items']) ? $data['items'] : $data;
          if ($code >= 200 && $code < 300 && is_array($items)) {
            $msg = 'Custom API connection successful';
            set_transient('plugin_ui_suite_last_proxy_error', '', 60);
            set_transient('plugin_ui_suite_last_proxy_success', current_time('mysql'), 86400);
          } else {
            $msg = 'Custom API test failed: endpoint did not return a JSON array';
            set_transient('plugin_ui_suite_last_proxy_error', $msg, 3600);
          }
        }
      }
    } else {
      $cfg_msg = ucfirst($source) . ' connector is configured. Test the public REST routes to confirm your provider-specific endpoint contract.';
      $msg = $cfg_msg;
      set_transient('plugin_ui_suite_last_proxy_error', '', 60);
      set_transient('plugin_ui_suite_last_proxy_success', current_time('mysql'), 86400);
    }
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'proxy','plugin_msg'=>$msg], admin_url('options-general.php')));
    exit;
  }


  private static function endpoint_diagnostic($label, $route, $params = []) {
    $req = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) $req->set_param($key, $value);
    $res = rest_do_request($req);
    $status = (int)$res->get_status();
    $data = $res->get_data();
    $count = is_array($data) ? count($data) : 0;
    $sample_keys = [];
    if (is_array($data)) {
      $first = $data[0] ?? $data;
      if (is_array($first)) $sample_keys = array_slice(array_keys($first), 0, 12);
    }
    return [
      'label' => $label,
      'route' => $route,
      'status' => $status,
      'ok' => $status >= 200 && $status < 300,
      'count' => $count,
      'sample_keys' => $sample_keys,
      'message' => is_array($data) && isset($data['error']) ? (string)$data['error'] : '',
    ];
  }

  public static function handle_provider_diagnostics() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_provider_diagnostics');
    $year = (int)current_time('Y');
    $results = [
      self::endpoint_diagnostic('Adoptables feed', '/plugin/v1/adoptables'),
      self::endpoint_diagnostic('Adoptions feed', '/plugin/v1/adoptions', ['years' => 2]),
      self::endpoint_diagnostic('In-care count', '/plugin/v1/in-care-count'),
      self::endpoint_diagnostic('Summary by year', '/plugin/v1/report', ['title' => 'Summary By Year', 'years' => 5]),
      self::endpoint_diagnostic('Current-year adoptions', '/plugin/v1/adoptions', ['year' => $year]),
    ];
    set_transient('plugin_ui_suite_provider_diagnostics', ['time' => current_time('mysql'), 'results' => $results], 30 * MINUTE_IN_SECONDS);
    $failed = array_filter($results, function($row){ return empty($row['ok']); });
    $msg = empty($failed) ? 'Provider diagnostics passed' : 'Provider diagnostics completed with warnings';
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'proxy','plugin_msg'=>$msg], admin_url('options-general.php')));
    exit;
  }

  private static function render_provider_diagnostics_results() {
    $diag = get_transient('plugin_ui_suite_provider_diagnostics');
    if (!is_array($diag) || empty($diag['results']) || !is_array($diag['results'])) return;
    echo '<h3>Last provider diagnostics</h3>';
    echo '<p class="description">Generated ' . esc_html($diag['time'] ?? '') . '. Counts and keys help confirm that your selected source can feed the public displays without custom reports.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Items</th><th>Sample keys</th><th>Message</th></tr></thead><tbody>';
    foreach ($diag['results'] as $row) {
      echo '<tr>';
      echo '<td><strong>' . esc_html($row['label'] ?? '') . '</strong><br><code>' . esc_html($row['route'] ?? '') . '</code></td>';
      echo '<td>' . (!empty($row['ok']) ? '<span style="color:#0a7d33;font-weight:600;">OK</span>' : '<span style="color:#b32d2e;font-weight:600;">Check</span>') . ' <code>' . esc_html((string)($row['status'] ?? '')) . '</code></td>';
      echo '<td>' . esc_html((string)($row['count'] ?? 0)) . '</td>';
      echo '<td><code>' . esc_html(implode(', ', (array)($row['sample_keys'] ?? []))) . '</code></td>';
      echo '<td>' . esc_html($row['message'] ?? '') . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  }

  public static function handle_proxy_clear_cache() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_proxy_clear_cache');
    global $wpdb;
    $like_patterns = ['_transient_plugin_asm_%','_transient_timeout_plugin_asm_%','_transient_plugin_custom_api_%','_transient_timeout_plugin_custom_api_%','_transient_plugin_shelterluv_%','_transient_timeout_plugin_shelterluv_%','_transient_plugin_petpoint_%','_transient_timeout_plugin_petpoint_%','_transient_asm_suite_seo_%','_transient_timeout_asm_suite_seo_%','plugin_ui_suite_last_good_%'];
    foreach ($like_patterns as $pattern) {
      $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
    }
    wp_cache_flush();
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'proxy','plugin_msg'=>'Proxy cache cleared'], admin_url('options-general.php')));
    exit;
  }

  private static function credential_source_label() {
    foreach (['ASM_ACCOUNT','ASM_USERNAME','ASM_PASSWORD'] as $const) {
      if (getenv($const) !== false && getenv($const) !== '') return 'Environment';
    }
    foreach (['ASM_ACCOUNT','ASM_USERNAME','ASM_PASSWORD'] as $const) {
      if (defined($const) && constant($const) !== '') return 'Suite / config constant';
    }
    return 'Not configured';
  }

  private static function proxy_status() {
    $last_error = get_transient('plugin_ui_suite_last_proxy_error');
    $last_success = get_transient('plugin_ui_suite_last_proxy_success');
    $settings = self::get_settings();
    return [
      'credential_source' => self::credential_source_label(),
      'data_source' => self::data_source_status($settings),
      'last_error' => is_string($last_error) ? $last_error : '',
      'last_success' => is_string($last_success) ? $last_success : '',
      'routes' => [
        rest_url('plugin/v1/adoptables'),
        rest_url('plugin/v1/report'),
        rest_url('plugin/v1/in-care-count'),
        rest_url('plugin/v1/adoptions'),
        rest_url('plugin/v1/animal-image'),
      ],
    ];
  }


  private static function enquiry_events_key() { return 'plugin_suite_enquiry_events_v1'; }


  private static function webhook_queue_key() { return 'plugin_suite_webhook_retry_queue_v1'; }

  private static function webhook_audit($entry, $status, $reason = '') {
    $audit = get_option(self::WEBHOOK_AUDIT_KEY, []);
    if (!is_array($audit)) $audit = [];
    $audit[] = ['time'=>current_time('mysql'), 'status'=>sanitize_key($status), 'event'=>sanitize_key($entry['event'] ?? ''), 'animal'=>sanitize_text_field($entry['context']['animal_name'] ?? ''), 'attempts'=>(int)($entry['_attempts'] ?? 0), 'reason'=>sanitize_text_field($reason)];
    if (count($audit) > 500) $audit = array_slice($audit, -500);
    update_option(self::WEBHOOK_AUDIT_KEY, $audit, false);
  }

  private static function queue_webhook_retry($entry, $reason) {
    $queue = get_option(self::webhook_queue_key(), []);
    if (!is_array($queue)) $queue = [];
    $attempts = max(0, (int)($entry['_attempts'] ?? 0)) + 1;
    $delay = min(DAY_IN_SECONDS, 15 * MINUTE_IN_SECONDS * (2 ** min($attempts - 1, 6)));
    $entry['_attempts'] = $attempts;
    $entry['_retry_reason'] = sanitize_text_field($reason);
    $entry['_queued_at'] = current_time('mysql');
    $entry['_next_retry'] = gmdate('Y-m-d H:i:s', time() + $delay);
    $queue[] = $entry;
    if (count($queue) > 500) $queue = array_slice($queue, -500);
    update_option(self::webhook_queue_key(), $queue, false);
    self::webhook_audit($entry, 'queued', $reason);
  }

  private static function send_enquiry_webhook($entry, $settings = null, $queue_failures = true) {
    $settings = $settings ?: self::get_settings();
    $global = $settings['global'] ?? [];
    if (empty($global['enquiry_webhook_url'])) return true;
    $body = wp_json_encode($entry);
    $headers = ['Content-Type' => 'application/json'];
    if (!empty($global['enquiry_webhook_secret'])) $headers['X-ASM-Suite-Signature'] = hash_hmac('sha256', $body, $global['enquiry_webhook_secret']);
    $res = wp_remote_post($global['enquiry_webhook_url'], ['timeout' => 8, 'blocking' => true, 'headers' => $headers, 'body' => $body]);
    if (is_wp_error($res) || (int)wp_remote_retrieve_response_code($res) >= 400) {
      $reason = is_wp_error($res) ? $res->get_error_message() : 'HTTP ' . (int)wp_remote_retrieve_response_code($res);
      if ($queue_failures) self::queue_webhook_retry($entry, $reason);
      else self::webhook_audit($entry, 'failed', $reason);
      return false;
    }
    self::webhook_audit($entry, 'delivered', 'HTTP ' . (int)wp_remote_retrieve_response_code($res));
    return true;
  }

  public static function handle_test_enquiry_integration() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_test_enquiry_integration');
    $entry = ['time'=>current_time('mysql'),'event'=>'test_enquiry_integration','source'=>'admin_test','page'=>admin_url('options-general.php?page=plugin-ui-suite&tab=forms'),'ip_hash'=>'test','user_agent'=>'Rescue Plugin Suite test','context'=>['animal_id'=>'test','animal_name'=>'Test animal','animal_code'=>'TEST','modal_url'=>'']];
    $settings = self::get_settings();
    if (!empty($settings['global']['enquiry_email'])) wp_mail($settings['global']['enquiry_email'], 'Rescue Plugin Suite test enquiry notification', 'This is a test notification from Rescue Plugin Suite.');
    $ok = self::send_enquiry_webhook($entry, $settings);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'forms','plugin_msg'=>$ok ? 'Test enquiry integration sent' : 'Test webhook queued for retry'], admin_url('options-general.php'))); exit;
  }

  public static function process_webhook_retry_queue($manual = false) {
    $queue = get_option(self::webhook_queue_key(), []);
    if (!is_array($queue) || !$queue) return ['sent'=>0,'remaining'=>0];
    delete_option(self::webhook_queue_key());
    $sent = 0; $kept = []; $now = time();
    foreach ($queue as $entry) {
      $next = !empty($entry['_next_retry']) ? strtotime($entry['_next_retry'] . ' UTC') : 0;
      if (!$manual && $next && $next > $now) { $kept[] = $entry; continue; }
      $clean = $entry; unset($clean['_retry_reason'], $clean['_queued_at'], $clean['_next_retry']);
      if (self::send_enquiry_webhook($clean, null, false)) $sent++;
      else self::queue_webhook_retry($clean, $entry['_retry_reason'] ?? 'Retry failed');
    }
    $new = get_option(self::webhook_queue_key(), []); if (!is_array($new)) $new = [];
    $merged = array_merge($kept, $new);
    if ($merged) update_option(self::webhook_queue_key(), $merged, false);
    return ['sent'=>$sent,'remaining'=>count($merged)];
  }

  public static function handle_retry_webhooks() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_retry_webhooks');
    $result = self::process_webhook_retry_queue(true);
    wp_safe_redirect(add_query_arg(['page'=>'plugin-ui-suite','tab'=>'forms','plugin_msg'=>'Webhook retry sent ' . (int)$result['sent'] . ' item(s); remaining queue: ' . (int)$result['remaining']], admin_url('options-general.php'))); exit;
  }

  private static function render_webhook_audit_table() {
    $audit = get_option(self::WEBHOOK_AUDIT_KEY, []); if (!is_array($audit) || !$audit) return;
    $rows = array_slice(array_reverse($audit), 0, 10);
    echo '<h3>Webhook delivery audit</h3><table class="plugin-suite-table"><thead><tr><th>Time</th><th>Status</th><th>Event</th><th>Animal</th><th>Attempts</th><th>Reason</th></tr></thead><tbody>';
    foreach ($rows as $row) echo '<tr><td>' . esc_html($row['time'] ?? '') . '</td><td><code>' . esc_html($row['status'] ?? '') . '</code></td><td>' . esc_html($row['event'] ?? '') . '</td><td>' . esc_html($row['animal'] ?? '') . '</td><td>' . esc_html((string)($row['attempts'] ?? 0)) . '</td><td>' . esc_html($row['reason'] ?? '') . '</td></tr>';
    echo '</tbody></table>';
  }

  public static function render_last_good_admin_notice() {
    if (!current_user_can('manage_options')) return;
    $notice = get_transient('asm_suite_last_good_served_notice');
    if (!$notice) return;
    delete_transient('asm_suite_last_good_served_notice');
    echo '<div style="position:fixed;left:16px;bottom:16px;z-index:2147483647;background:#fff3cd;border:1px solid #dba617;color:#3c2f00;padding:12px 14px;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.18);max-width:360px;font:14px/1.4 system-ui,sans-serif;"><strong>Rescue Plugin Suite:</strong> serving last-known-good feed data because the live provider response failed.</div>';
  }

  private static function record_enquiry_event($event) {
    $settings = self::get_settings();
    $global = $settings['global'] ?? [];
    if (empty($global['enquiry_log_enabled'])) return;
    $entry = [
      'time' => current_time('mysql'),
      'event' => sanitize_key($event),
      'source' => 'embedded_or_external_form_intent',
      'page' => esc_url_raw(wp_get_referer() ?: ($_SERVER['HTTP_REFERER'] ?? '')),
      'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt('nonce')),
      'user_agent' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180),
      'context' => [],
    ];
    $context = json_decode(wp_unslash($_POST['context'] ?? ''), true);
    if (is_array($context)) {
      $entry['context'] = [
        'animal_id' => sanitize_text_field($context['animal_id'] ?? ''),
        'animal_name' => sanitize_text_field($context['animal_name'] ?? ''),
        'animal_code' => sanitize_text_field($context['animal_code'] ?? ''),
        'modal_url' => esc_url_raw($context['modal_url'] ?? ''),
      ];
    }
    $events = get_option(self::enquiry_events_key(), []);
    if (!is_array($events)) $events = [];
    $events[] = $entry;
    if (count($events) > 1000) $events = array_slice($events, -1000);
    update_option(self::enquiry_events_key(), $events, false);
    if (!empty($global['enquiry_email'])) {
      wp_mail($global['enquiry_email'], 'New adoption enquiry intent', "An adoption enquiry intent was recorded.\n\nTime: {$entry['time']}\nPage: {$entry['page']}\nSource: {$entry['source']}");
    }
    self::send_enquiry_webhook($entry, $settings);
  }

  public static function handle_export_enquiries() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_export_enquiries');
    $events = get_option(self::enquiry_events_key(), []);
    if (!is_array($events)) $events = [];
    nocache_headers();
    header('Content-Type: text/csv; charset=' . get_bloginfo('charset'));
    header('Content-Disposition: attachment; filename=asm-suite-enquiry-events.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['time','event','source','page','animal_id','animal_name','animal_code','modal_url','ip_hash','user_agent']);
    foreach ($events as $row) { $ctx = is_array($row['context'] ?? null) ? $row['context'] : []; fputcsv($out, [$row['time'] ?? '', $row['event'] ?? '', $row['source'] ?? '', $row['page'] ?? '', $ctx['animal_id'] ?? '', $ctx['animal_name'] ?? '', $ctx['animal_code'] ?? '', $ctx['modal_url'] ?? '', $row['ip_hash'] ?? '', $row['user_agent'] ?? '']); }
    fclose($out);
    exit;
  }

  private static function analytics_defaults() {
    return [
      'adoptables_modal_open' => 0,
      'adoptables_apply_click' => 0,
      'adopted_modal_open' => 0,
      'favourite_toggle' => 0,
      'featured_widget_click' => 0,
    ];
  }

  private static function get_analytics() {
    $data = get_option(self::ANALYTICS_KEY, []);
    if (!is_array($data)) $data = [];
    return array_merge(self::analytics_defaults(), $data);
  }

  public static function handle_track_event() {
    $event = sanitize_key($_POST['event'] ?? '');
    $allowed = array_keys(self::analytics_defaults());
    if (!$event || !in_array($event, $allowed, true)) {
      wp_send_json_error(['message' => 'Invalid event'], 400);
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
    if ($nonce !== '' && !wp_verify_nonce($nonce, 'plugin_suite_track')) {
      wp_send_json_error(['message' => 'Invalid analytics nonce'], 403);
    }

    $settings = self::get_settings();
    $global = $settings['global'] ?? [];
    if (($global['analytics_consent_mode'] ?? 'immediate') === 'cookie') {
      $cookie = (string)($global['analytics_consent_cookie'] ?? '');
      if ($cookie === '' || empty($_COOKIE[$cookie])) wp_send_json_success(['event' => $event, 'consent_waiting' => true]);
    }

    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $rate_key = 'plugin_suite_track_' . md5($event . '|' . $ip);
    $hits = (int)get_transient($rate_key);
    if ($hits >= 30) {
      wp_send_json_success(['event' => $event, 'rate_limited' => true]);
    }
    set_transient($rate_key, $hits + 1, MINUTE_IN_SECONDS);

    $data = self::get_analytics();
    $data[$event] = (int)($data[$event] ?? 0) + 1;
    update_option(self::ANALYTICS_KEY, $data, false);
    if ($event === 'adoptables_apply_click') self::record_enquiry_event($event);
    wp_send_json_success(['event' => $event, 'count' => $data[$event]]);
  }


  private static function data_source_definitions() {
    return [
      'asm' => [
        'label' => 'ASM / Shelter Manager',
        'summary' => 'Fully supported live data source using the ASM proxy and report endpoints.',
        'state' => 'live',
      ],
      'shelterluv' => [
        'label' => 'Shelterluv',
        'summary' => 'Live connector enabled using configured Shelterluv endpoints, API key and optional filters.',
        'state' => 'groundwork',
      ],
      'petpoint' => [
        'label' => 'PetPoint',
        'summary' => 'Live connector enabled using configured PetPoint endpoints, credentials and optional filters.',
        'state' => 'groundwork',
      ],
      'custom_api' => [
        'label' => 'Custom API',
        'summary' => 'Fully usable live source using your own JSON feed endpoints, optional API key header and source-aware proxy routing.',
        'state' => 'live',
      ],
    ];
  }

  private static function source_runtime_config($settings) {
    $status = self::data_source_status($settings);
    $g = is_array($settings['global'] ?? null) ? $settings['global'] : [];
    $p = is_array($settings['proxy'] ?? null) ? $settings['proxy'] : [];
    $source = $status['key'] ?? 'asm';
    if ($source === 'asm') {
      return [
        'items' => [
          'Base URL' => (string)($p['base_url'] ?? 'https://service.sheltermanager.com/asmservice'),
          'Account' => (string)($p['account'] ?? ''),
          'Username saved' => !empty($p['username']) ? 'Yes' : 'No',
          'Password saved' => !empty($p['password']) ? 'Yes' : 'No',
          'REST routes' => implode(' | ', [rest_url('plugin/v1/adoptables'), rest_url('plugin/v1/adoptions'), rest_url('plugin/v1/report'), rest_url('plugin/v1/in-care-count'), rest_url('plugin/v1/animal-image')]),
        ],
        'contract' => 'ASM is the native live connector. The suite normalises ASM records into the standard rescue animal shape before the public displays render.',
      ];
    }
    if ($source === 'custom_api') {
      $base = esc_url_raw($g['custom_api_url'] ?? '');
      $endpoint = function($specific, $suffix) use ($g, $base) {
        $url = esc_url_raw($g[$specific] ?? '');
        if ($url !== '') return $url;
        if ($base !== '') return untrailingslashit($base) . $suffix;
        return '';
      };
      $header = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($g['custom_api_auth_header'] ?? 'X-API-Key'));
      return [
        'items' => [
          'Base URL' => $base,
          'Adoptables endpoint' => $endpoint('custom_api_adoptables_url', '/adoptables'),
          'Adoptions endpoint' => $endpoint('custom_api_adoptions_url', '/adoptions'),
          'Report endpoint' => $endpoint('custom_api_report_url', '/report'),
          'In-care endpoint' => $endpoint('custom_api_incare_url', '/in-care-count'),
          'Image endpoint' => $endpoint('custom_api_image_url', '/animal-image'),
          'Auth header' => $header,
          'API key saved' => !empty($g['custom_api_key']) ? 'Yes' : 'No',
        ],
        'contract' => 'Custom API is live. Feed endpoints should return JSON arrays or an items array in the suite-normalised animal shape. AGE_MONTHS and AGE_BAND are supported and will be derived if omitted.',
      ];
    }
    if ($source === 'shelterluv') {
      return [
        'items' => [
          'Base URL' => (string)($g['shelterluv_base_url'] ?? 'https://www.shelterluv.com'),
          'Organisation ID' => (string)($g['shelterluv_org_id'] ?? ''),
          'API key saved' => !empty($g['shelterluv_api_key']) ? 'Yes' : 'No',
          'Animal type' => (string)($g['shelterluv_animal_type'] ?? 'cat'),
          'Statuses' => (string)($g['shelterluv_statuses'] ?? 'adoptable,foster'),
          'Location IDs' => (string)($g['shelterluv_location_ids'] ?? ''),
        ],
        'contract' => 'Shelterluv connects through configurable API endpoints and normalises adoptables, adoptions, reports and images into the shared suite data model.',
      ];
    }
    return [
      'items' => [
        'Base URL' => (string)($g['petpoint_base_url'] ?? ''),
        'Shelter ID / database' => (string)($g['petpoint_shelter_id'] ?? ''),
        'Username saved' => !empty($g['petpoint_username']) ? 'Yes' : 'No',
        'Password saved' => !empty($g['petpoint_password']) ? 'Yes' : 'No',
        'Location IDs' => (string)($g['petpoint_location_ids'] ?? ''),
        'Species ID' => (string)($g['petpoint_species_id'] ?? '2'),
        'Statuses' => (string)($g['petpoint_statuses'] ?? 'available,foster'),
        'Adopted report ID' => (string)($g['petpoint_adopted_report_id'] ?? ''),
      ],
      'contract' => 'PetPoint connects through configurable API endpoints and normalises adoptables, adoptions, reports and images into the shared suite data model.',
    ];
  }

  private static function source_contract_reference() {
    return [
      'adoptables' => [
        'required' => ['ID', 'ANIMALNAME'],
        'supported' => ['CODE','ANIMALAGE','AGE_MONTHS','AGE_BAND','SEXNAME','SEX','BREEDNAME','BREEDNAME1','SPECIESID','SPECIESNAME','DAYSONSHELTER','WEBSITEIMAGECOUNT','WEBSITEIMAGES','ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION','HASACTIVERESERVE','HASACTIVERESERVENAME','reservation_statuses','reservation_status_counts','reservation_count','has_active_reservation','primary_reservation_status'],
      ],
      'adoptions' => [
        'required' => ['ID', 'ANIMALNAME'],
        'supported' => ['CODE','ANIMALAGE','AGE_MONTHS','AGE_BAND','SEXNAME','SEX','BREEDNAME','BREEDNAME1','SPECIESID','SPECIESNAME','WEBSITEIMAGECOUNT','WEBSITEIMAGES','ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION','ACTIVEMOVEMENTDATE','MOSTRECENTADOPTIONDATE','ADOPTIONDATE','DATEADOPTED','MOVEMENTDATE'],
      ],
      'reports' => [
        'required' => ['A JSON array result for each report row, or an object containing an items array'],
        'supported' => ['title query parameter is passed through by the suite proxy'],
      ],
      'image' => [
        'required' => ['Binary image response or a direct proxied image URL target'],
        'supported' => ['animalid or id query parameter for lookup'],
      ],
    ];
  }

  private static function data_source_status($settings) {
    $defs = self::data_source_definitions();
    $key = sanitize_key($settings['global']['data_source'] ?? 'asm');
    if (!isset($defs[$key])) $key = 'asm';
    $def = $defs[$key];
    $missing = [];
    if ($key === 'asm') {
      if (empty($settings['proxy']['account'])) $missing[] = 'ASM account';
      if (empty($settings['proxy']['username'])) $missing[] = 'ASM username';
      if (empty($settings['proxy']['password'])) $missing[] = 'ASM password';
    } elseif ($key === 'custom_api') {
      if (empty($settings['global']['custom_api_adoptables_url']) && empty($settings['global']['custom_api_url'])) $missing[] = 'Custom API adoptables URL';
      if (empty($settings['global']['custom_api_adoptions_url']) && empty($settings['global']['custom_api_url'])) $missing[] = 'Custom API adoptions URL';
    } elseif ($key === 'shelterluv') {
      if (empty($settings['global']['shelterluv_api_key'])) $missing[] = 'Shelterluv API key';
      if (empty($settings['global']['shelterluv_org_id'])) $missing[] = 'Shelterluv organisation ID';
    } elseif ($key === 'petpoint') {
      if (empty($settings['global']['petpoint_username'])) $missing[] = 'PetPoint username';
      if (empty($settings['global']['petpoint_password'])) $missing[] = 'PetPoint password';
      if (empty($settings['global']['petpoint_shelter_id'])) $missing[] = 'PetPoint shelter ID';
    }
    $state_labels = ['live' => 'Live now', 'ready' => 'Ready for feed', 'groundwork' => 'Ready'];
    return [
      'key' => $key,
      'label' => $def['label'],
      'state' => $def['state'],
      'state_label' => $state_labels[$def['state']] ?? ucfirst($def['state']),
      'configured' => empty($missing),
      'configured_label' => empty($missing) ? 'Configured' : 'Needs details',
      'missing' => $missing,
    ];
  }

  private static function render_data_source_fields($settings, $context = 'settings') {
    $global = $settings['global'] ?? [];
    $proxy = $settings['proxy'] ?? [];
    echo '<div class="plugin-source-fields">';

    echo '<div data-source-fields="asm">';
    echo '<table class="form-table">';
    self::row('ASM account', '<input type="text" name="suite[proxy][account]" value="' . esc_attr($proxy['account'] ?? '') . '" class="regular-text" />');
    self::row('ASM username', '<input type="text" name="suite[proxy][username]" value="' . esc_attr($proxy['username'] ?? '') . '" class="regular-text" autocomplete="off" />');
    self::row('ASM password', '<input type="password" name="suite[proxy][password]" value="' . esc_attr($proxy['password'] ?? '') . '" class="regular-text" autocomplete="new-password" />');
    echo '</table></div>';

    echo '<div data-source-fields="shelterluv">';
    echo '<table class="form-table">';
    self::row('Shelterluv base URL', '<input type="url" name="suite[global][shelterluv_base_url]" value="' . esc_attr($global['shelterluv_base_url'] ?? 'https://www.shelterluv.com') . '" class="regular-text code" placeholder="https://www.shelterluv.com" />');
    self::row('Shelterluv organisation ID', '<input type="text" name="suite[global][shelterluv_org_id]" value="' . esc_attr($global['shelterluv_org_id'] ?? '') . '" class="regular-text" /><p class="description">Used by the live Shelterluv connector for organisation-scoped API requests.</p>');
    self::row('Shelterluv API key', '<input type="password" name="suite[global][shelterluv_api_key]" value="' . esc_attr($global['shelterluv_api_key'] ?? '') . '" class="regular-text" autocomplete="new-password" />');
    self::row('Animal type', '<select name="suite[global][shelterluv_animal_type]"><option value="cat" ' . selected(($global['shelterluv_animal_type'] ?? 'cat'),'cat',false) . '>cat</option><option value="cats" ' . selected(($global['shelterluv_animal_type'] ?? 'cat'),'cats',false) . '>cats</option><option value="feline" ' . selected(($global['shelterluv_animal_type'] ?? 'cat'),'feline',false) . '>feline</option><option value="any" ' . selected(($global['shelterluv_animal_type'] ?? 'cat'),'any',false) . '>any</option></select>');
    self::row('Statuses to include', '<textarea name="suite[global][shelterluv_statuses]" rows="3" class="large-text code">' . esc_textarea($global['shelterluv_statuses'] ?? 'adoptable,foster') . '</textarea><p class="description">Comma or line separated. Example: adoptable, foster</p>');
    self::row('Location IDs', '<textarea name="suite[global][shelterluv_location_ids]" rows="3" class="large-text code">' . esc_textarea($global['shelterluv_location_ids'] ?? '') . '</textarea><p class="description">Optional comma or line separated list for location filtering.</p>');
    self::row('Adoptables endpoint', '<input type="url" name="suite[global][shelterluv_adoptables_url]" value="' . esc_attr($global['shelterluv_adoptables_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    self::row('Adoptions endpoint', '<input type="url" name="suite[global][shelterluv_adoptions_url]" value="' . esc_attr($global['shelterluv_adoptions_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    self::row('Reports endpoint', '<input type="url" name="suite[global][shelterluv_report_url]" value="' . esc_attr($global['shelterluv_report_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    self::row('In-care endpoint', '<input type="url" name="suite[global][shelterluv_incare_url]" value="' . esc_attr($global['shelterluv_incare_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    self::row('Image endpoint', '<input type="url" name="suite[global][shelterluv_image_url]" value="' . esc_attr($global['shelterluv_image_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    echo '</table></div>';

    echo '<div data-source-fields="petpoint">';
    echo '<table class="form-table">';
    self::row('PetPoint base URL', '<input type="url" name="suite[global][petpoint_base_url]" value="' . esc_attr($global['petpoint_base_url'] ?? '') . '" class="regular-text code" placeholder="https://ws.petango.com/webservices/adoptablesearch" />');
    self::row('PetPoint shelter ID', '<input type="text" name="suite[global][petpoint_shelter_id]" value="' . esc_attr($global['petpoint_shelter_id'] ?? '') . '" class="regular-text" /><p class="description">Used by the live PetPoint connector for shelter-scoped API requests.</p>');
    self::row('PetPoint username', '<input type="text" name="suite[global][petpoint_username]" value="' . esc_attr($global['petpoint_username'] ?? '') . '" class="regular-text" />');
    self::row('PetPoint password', '<input type="password" name="suite[global][petpoint_password]" value="' . esc_attr($global['petpoint_password'] ?? '') . '" class="regular-text" autocomplete="new-password" />');
    self::row('PetPoint species ID', '<input type="text" name="suite[global][petpoint_species_id]" value="' . esc_attr($global['petpoint_species_id'] ?? '2') . '" class="regular-text code" /><p class="description">Cat is commonly 2. Use comma separated values if your feed needs more than one.</p>');
    self::row('Statuses to include', '<textarea name="suite[global][petpoint_statuses]" rows="3" class="large-text code">' . esc_textarea($global['petpoint_statuses'] ?? 'available,foster') . '</textarea>');
    self::row('Location IDs', '<textarea name="suite[global][petpoint_location_ids]" rows="3" class="large-text code">' . esc_textarea($global['petpoint_location_ids'] ?? '') . '</textarea>');
    self::row('Adopted report ID', '<input type="text" name="suite[global][petpoint_adopted_report_id]" value="' . esc_attr($global['petpoint_adopted_report_id'] ?? '') . '" class="regular-text code" /><p class="description">Optional report identifier sent to provider endpoints that need one.</p>');
    self::row('Adoptables endpoint', '<input type="url" name="suite[global][petpoint_adoptables_url]" value="' . esc_attr($global['petpoint_adoptables_url'] ?? '') . '" class="regular-text code" placeholder="Uses base URL if blank" />');
    self::row('Adoptions endpoint', '<input type="url" name="suite[global][petpoint_adoptions_url]" value="' . esc_attr($global['petpoint_adoptions_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    self::row('Reports endpoint', '<input type="url" name="suite[global][petpoint_report_url]" value="' . esc_attr($global['petpoint_report_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    self::row('In-care endpoint', '<input type="url" name="suite[global][petpoint_incare_url]" value="' . esc_attr($global['petpoint_incare_url'] ?? '') . '" class="regular-text code" placeholder="Uses adoptables/base URL if blank" />');
    self::row('Image endpoint', '<input type="url" name="suite[global][petpoint_image_url]" value="' . esc_attr($global['petpoint_image_url'] ?? '') . '" class="regular-text code" placeholder="Derived from base URL if blank" />');
    echo '</table></div>';

    echo '<div data-source-fields="custom_api">';
    echo '<table class="form-table">';
    self::row('Custom API base URL', '<input type="url" name="suite[global][custom_api_url]" value="' . esc_attr($global['custom_api_url'] ?? '') . '" class="regular-text code" placeholder="https://example.org/api" /><p class="description">Optional base URL. If the endpoint fields below are blank the suite will derive /adoptables, /adoptions, /report, /in-care-count and /animal-image from this base.</p>');
    self::row('Adoptables endpoint', '<input type="url" name="suite[global][custom_api_adoptables_url]" value="' . esc_attr($global['custom_api_adoptables_url'] ?? '') . '" class="regular-text code" placeholder="https://example.org/api/adoptables" />');
    self::row('Adoptions endpoint', '<input type="url" name="suite[global][custom_api_adoptions_url]" value="' . esc_attr($global['custom_api_adoptions_url'] ?? '') . '" class="regular-text code" placeholder="https://example.org/api/adoptions" />');
    self::row('Report endpoint', '<input type="url" name="suite[global][custom_api_report_url]" value="' . esc_attr($global['custom_api_report_url'] ?? '') . '" class="regular-text code" placeholder="https://example.org/api/report" />');
    self::row('In-care endpoint', '<input type="url" name="suite[global][custom_api_incare_url]" value="' . esc_attr($global['custom_api_incare_url'] ?? '') . '" class="regular-text code" placeholder="https://example.org/api/in-care-count" />');
    self::row('Image endpoint', '<input type="url" name="suite[global][custom_api_image_url]" value="' . esc_attr($global['custom_api_image_url'] ?? '') . '" class="regular-text code" placeholder="https://example.org/api/animal-image" />');
    self::row('Auth header', '<input type="text" name="suite[global][custom_api_auth_header]" value="' . esc_attr($global['custom_api_auth_header'] ?? 'X-API-Key') . '" class="regular-text" />');
    self::row('Custom API key', '<input type="password" name="suite[global][custom_api_key]" value="' . esc_attr($global['custom_api_key'] ?? '') . '" class="regular-text" autocomplete="new-password" /><p class="description">The suite sends the key in the header above and also as api_key for feeds that prefer a query parameter.</p>');
    ob_start(); self::select_input('global','provider_profile',$global['provider_profile'] ?? '', array_map(function($v){ return $v['label']; }, self::provider_profile_templates())); self::row('Provider template', ob_get_clean() . '<p class="description">Use as a copy/paste guide for the field mapper below.</p>');
    ob_start(); self::checkbox_input('global','preview_mode',$global['preview_mode'] ?? 0,'Allow admins to preview another source with ?asm_suite_source=custom_api, shelterluv, petpoint or asm'); self::row('Safe preview mode', ob_get_clean());
    self::row('Field mapper', '<textarea name="suite[global][field_map]" rows="6" class="large-text code" placeholder="ANIMALNAME=name,pet_name&#10;CODE=shelter_code,reference&#10;ANIMALCOMMENTS=bio,description">' . esc_textarea($global['field_map'] ?: (self::provider_profile_templates()[$global['provider_profile'] ?? '']['map'] ?? '')) . '</textarea><p class="description">Optional. One mapping per line: suite field = provider field(s). Applies to Custom API, Shelterluv and PetPoint normalisation.</p>');
    echo '</table></div>';

    echo '</div>';
  }

  public static function render_setup_wizard() {
    if (!current_user_can('manage_options')) return;
    $settings = self::get_settings();
    $source_defs = self::data_source_definitions();
    $source_status = self::data_source_status($settings);
    echo '<div class="wrap"><h1>Rescue Plugin Suite setup wizard</h1><p>Follow the plain-English steps below to configure your organisation, payments, campaigns, donation widget, rescue management connection and website integration.</p>';
    echo '<style>.plugin-wizard-progress{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0 20px}.plugin-wizard-progress .plugin-step{border:1px solid #d8d8e2;border-radius:16px;padding:14px;background:#fff}.plugin-wizard-progress .plugin-step strong{display:block;margin-bottom:4px}.plugin-source-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}.plugin-source-card{position:relative;border:1px solid #d8d8e2;border-radius:18px;padding:16px;background:#fff;cursor:pointer}.plugin-source-card.active{border-color:#401268;box-shadow:0 0 0 2px rgba(64,18,104,.08)}.plugin-source-card input{position:absolute;opacity:0;pointer-events:none}.plugin-source-chip{display:inline-block;padding:3px 8px;border-radius:999px;background:#f3edf8;font-size:12px;font-weight:600;margin-bottom:8px}.plugin-source-fields [data-source-fields]{display:none}.plugin-source-fields [data-source-fields].active{display:block}.plugin-setup-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.plugin-checklist{margin:0;padding-left:18px}</style>';
    echo '<div class="plugin-suite-panel" style="max-width:1040px;">';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('plugin_ui_suite_setup_save');
    echo '<input type="hidden" name="action" value="plugin_ui_suite_setup_save" />';
    echo '<div class="plugin-wizard-progress">';
    $wizard_steps = class_exists('Plugin_UI_Suite_Registry') ? Plugin_UI_Suite_Registry::setup_steps() : [];
    if (empty($wizard_steps)) $wizard_steps = ['welcome'=>['title'=>'Welcome','description'=>'What this wizard will set up'], 'finished'=>['title'=>'Finished','description'=>'Review and save']];
    $step_number = 0;
    foreach ($wizard_steps as $step) {
      $step_number++;
      echo '<div class="plugin-step"><strong>Step ' . (int)$step_number . '</strong>' . esc_html($step['title'] ?? '') . '<br><span class="description">' . esc_html($step['description'] ?? (($step_number < 5) ? 'Required to launch' : 'Can be refined later')) . '</span></div>';
    }
    echo '</div>';
    echo '<div class="plugin-suite-subnav">';
    $step_number = 0;
    foreach ($wizard_steps as $step) { $step_number++; echo '<button type="button" class="button plugin-wizard-step-btn" data-step="'.(int)$step_number.'">'.esc_html($step['title'] ?? '').'</button> '; }
    echo '</div>';

    echo '<div class="plugin-suite-subsection active" data-step="1"><h2>Step 1 · ASM connection</h2><p class="description">This quick setup configures the native ASM connection. Custom API is also available as a live source from the Global tab after setup.</p><div class="plugin-source-grid">';
    echo '<label class="plugin-source-card active" data-source-card="asm">';
    echo '<input type="radio" name="suite[global][data_source]" value="asm" checked="checked" />';
    echo '<span class="plugin-source-chip">Live now</span>';
    echo '<strong>Animal Shelter Manager (ASM)</strong><p>The native ASM connector is active and uses the proxy credentials saved in this wizard.</p>';
    echo '</label>';
    echo '</div></div>';

    $proxy_settings = self::get_proxy_settings($settings);
    echo '<div class="plugin-suite-subsection" data-step="2"><h2>Step 2 · Connection details</h2><p class="description">Enter your ASM connection details below.</p>';
    echo '<table class="form-table">';
    ob_start(); self::text_input('proxy','base_url',$proxy_settings['base_url']); self::row('ASM base URL', ob_get_clean());
    ob_start(); self::text_input('proxy','account',$proxy_settings['account']); self::row('ASM account', ob_get_clean());
    ob_start(); self::text_input('proxy','username',$proxy_settings['username']); self::row('ASM username', ob_get_clean());
    printf('<tr><th>ASM password</th><td><input type="password" name="%s" value="%s" class="regular-text" autocomplete="new-password" /></td></tr>', esc_attr(self::field_name('proxy','password')), esc_attr($proxy_settings['password']));
    echo '</table></div>';

    echo '<div class="plugin-suite-subsection" data-step="3"><h2>Step 3 · Style</h2><table class="form-table">';
    self::row('Theme preset', '<select name="suite[global][preset_name]"><option value="rescue_default">Rescue Default</option><option value="classic">Classic</option><option value="modern">Modern</option><option value="minimal">Minimal</option></select>');
    self::row('Accent colour', '<input type="color" name="suite[global][brand_color]" value="' . esc_attr($settings['adoptables']['brand_color'] ?? '#401268') . '" />');
    echo '</table><div class="plugin-suite-note"><strong>Tip</strong><br>This step only sets the starting point. Per-module styling remains available later in the main suite tabs.</div></div>';

    echo '<div class="plugin-suite-subsection" data-step="4"><h2>Step 4 · Features</h2><table class="form-table">';
    echo '<tr><th>Enable adoptables filters</th><td><label><input type="checkbox" name="suite[adoptables][enable_filters]" value="1" ' . checked(!empty($settings['adoptables']['enable_filters']), true, false) . ' /> Enable filters</label></td></tr>';
    echo '<tr><th>Enable favourites</th><td><label><input type="checkbox" name="suite[adoptables][enable_favourites]" value="1" ' . checked(!empty($settings['adoptables']['enable_favourites']), true, false) . ' /> Enable favourites</label></td></tr>';
    echo '<tr><th>Enable adopted modals</th><td><label><input type="checkbox" name="suite[adopted][enable_modals]" value="1" ' . checked(!empty($settings['adopted']['enable_modals']), true, false) . ' /> Enable adopted modals</label></td></tr>';
    echo '<tr><th>Enable match quiz</th><td><label><input type="checkbox" name="suite[quiz][quiz_enabled]" value="1" ' . checked(!empty($settings['quiz']['quiz_enabled']), true, false) . ' /> Enable quiz</label></td></tr>';
    echo '</table><h3>Before you finish</h3><ul class="plugin-checklist"><li>Chosen source: <strong>' . esc_html($source_status['label']) . '</strong></li><li>Connector state: ' . esc_html($source_status['state_label']) . '</li><li>Configuration status: ' . esc_html($source_status['configured_label']) . '</li>';
    if (!empty($source_status['missing'])) echo '<li>Still missing: ' . esc_html(implode(', ', $source_status['missing'])) . '</li>';
    echo '<li>Core shortcodes and module settings remain available in the main suite pages after setup.</li></ul></div>';

    echo '<p class="plugin-setup-actions">';
    submit_button('Save and finish','primary','submit',false);
    echo ' <a class="button" href="' . esc_url(add_query_arg(['page'=>'plugin-ui-suite'], admin_url('options-general.php'))) . '">Skip for now</a>';
    echo '</p></form></div>';
    echo '<script>(function(){function currentSource(){const checked=document.querySelector("input[name=\"suite[global][data_source]\"]:checked");return checked?checked.value:"asm";}function syncSourceUi(){const source=currentSource();document.querySelectorAll("[data-source-fields]").forEach(function(el){el.classList.toggle("active",el.getAttribute("data-source-fields")===source);});document.querySelectorAll("[data-source-card]").forEach(function(el){el.classList.toggle("active",el.getAttribute("data-source-card")===source);});}document.addEventListener("click",function(e){const btn=e.target.closest(".plugin-wizard-step-btn");if(btn){e.preventDefault();const step=btn.getAttribute("data-step");document.querySelectorAll(".plugin-suite-subsection[data-step]").forEach(function(el){el.classList.toggle("active",el.getAttribute("data-step")===step);});}const card=e.target.closest("[data-source-card]");if(card){const input=card.querySelector("input[type=radio]");if(input){input.checked=true;syncSourceUi();}}});document.addEventListener("change",function(e){if(e.target&&e.target.name==="suite[global][data_source]")syncSourceUi();});syncSourceUi();})();</script>';
    echo '</div>';
  }



  private static function render_registry_tab($settings) {
    if (!class_exists('Plugin_UI_Suite_Registry') || !Plugin_UI_Suite_Registry::developer_mode_enabled()) { echo '<p>Registry tools are available only when Developer Mode or WP_DEBUG is enabled.</p>'; return; }
    if (!class_exists('Plugin_UI_Suite_Registry')) { echo '<p>Registry framework is unavailable.</p>'; return; }
    $query = sanitize_text_field($_GET['settings_search'] ?? '');
    echo '<div class="plugin-suite-grid"><div class="plugin-suite-card"><h2>Plugin-wide settings search</h2><form method="get"><input type="hidden" name="page" value="plugin-ui-suite"><input type="hidden" name="tab" value="registry"><p><input class="regular-text" name="settings_search" value="'.esc_attr($query).'" placeholder="PayPal, Gift Aid, Adoptables, Featured Animal, Statistics"> <button class="button button-primary">Search</button></p></form>';
    if ($query !== '') { $results = Plugin_UI_Suite_Registry::search_settings($query); echo '<h3>Results</h3><table class="widefat striped"><thead><tr><th>Setting</th><th>Module</th><th>Page</th><th>Field</th></tr></thead><tbody>'; if (!$results) echo '<tr><td colspan="4">No matching settings found.</td></tr>'; foreach ($results as $id=>$setting) echo '<tr><td>'.esc_html($setting['label'] ?? $id).'</td><td>'.esc_html($setting['module'] ?? '').'</td><td>'.esc_html($setting['page'] ?? '').'</td><td><code>'.esc_html($setting['field_id'] ?? '').'</code></td></tr>'; echo '</tbody></table>'; }
    echo '</div><div class="plugin-suite-card"><h2>Registry import / export</h2><p class="description">Exports use the Settings Registry. Sensitive fields are excluded unless explicitly requested.</p><div class="plugin-suite-actions"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_registry_export'); echo '<input type="hidden" name="action" value="plugin_ui_suite_registry_export"><label><input type="checkbox" name="include_sensitive" value="1"> Include sensitive values</label> '; submit_button('Export registry settings','secondary','submit',false); echo '</form><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_registry_import'); echo '<input type="hidden" name="action" value="plugin_ui_suite_registry_import"><input type="file" name="import_file" accept="application/json"> '; submit_button('Import registry settings','secondary','submit',false); echo '</form></div></div>';
    echo '<div class="plugin-suite-card"><h2>Feature flags</h2><table class="widefat striped"><thead><tr><th>Module</th><th>Flags</th><th>Reset</th></tr></thead><tbody>'; foreach (Plugin_UI_Suite_Registry::all('modules') as $id=>$module) { $flags = wp_parse_args($module['flags'] ?? [], ['installed'=>true,'enabled'=>true,'hidden'=>false,'experimental'=>false,'beta'=>false,'deprecated'=>false,'future_premium'=>false]); echo '<tr><td>'.esc_html($module['name'] ?? $id).'</td><td><code>'.esc_html(wp_json_encode($flags)).'</code></td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('plugin_ui_suite_registry_reset'); echo '<input type="hidden" name="action" value="plugin_ui_suite_registry_reset"><input type="hidden" name="module" value="'.esc_attr($id).'">'; submit_button('Reset module','small','submit',false); echo '</form></td></tr>'; } echo '</tbody></table></div>';
    echo '<div class="plugin-suite-card"><h2>Registry-driven help</h2>'; foreach (Plugin_UI_Suite_Registry::all('help') as $guide) echo '<details><summary>'.esc_html($guide['title'] ?? '').'</summary><p>'.esc_html($guide['content'] ?? '').'</p></details>'; echo '</div>';
    echo '<div class="plugin-suite-card"><h2>Analytics panels</h2><ul>'; foreach (Plugin_UI_Suite_Registry::all('analytics') as $panel) echo '<li>'.esc_html($panel['title'] ?? '').'</li>'; echo '</ul></div>';
    echo '<div class="plugin-suite-card"><h2>Setup wizard steps</h2><ol>'; $steps = Plugin_UI_Suite_Registry::all('setup_steps'); uasort($steps, function($a,$b){ return ($a['order'] ?? 100) <=> ($b['order'] ?? 100); }); foreach ($steps as $step) echo '<li>'.esc_html($step['title'] ?? '').'</li>'; echo '</ol></div></div>';
  }

  private static function render_help_tab($settings) {
    $sections = [
      'Documentation' => 'Start with the setup wizard, then use Integrations for services, each UI module for embeds and Forms only for application or enquiry forms.',
      'FAQs' => 'Most display questions are answered in the relevant module\'s Embed or Appearance page. Provider questions live in Integrations and Diagnostics.',
      'Troubleshooting' => 'Run Diagnostics, copy system information, confirm REST API health, then check cache, cron and integration status before opening support.',
      'Known Issues' => 'Review open GitHub issues before reporting a bug so duplicate reports can be consolidated.',
      'Report a Bug' => 'Attach copied system information, reproduction steps, screenshots and expected versus actual behaviour.',
      'Feature Requests' => 'Describe the rescue workflow, who needs it, and how often it is used.',
      'Release Notes' => 'Check the latest release before updating production sites.',
      'Support workflow' => '1) Run Diagnostics. 2) Copy System Information. 3) Search Known Issues. 4) Open a focused GitHub issue.'
    ];
    echo '<div class="plugin-suite-grid"><div class="plugin-suite-card"><h2>'.esc_html__('Help Centre','plugin-ui-suite').'</h2><p>'.esc_html__('Contextual help is available for each major module, with a dedicated support workflow for production rescue websites.','plugin-ui-suite').'</p><p><a class="button button-primary" href="' . esc_url(add_query_arg(['page'=>'plugin-ui-suite-setup'], admin_url('options-general.php'))) . '">'.esc_html__('Open setup wizard','plugin-ui-suite').'</a> <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=plugin_ui_suite_copy_system_information'), 'plugin_ui_suite_copy_system_information')) . '">'.esc_html__('Copy System Information','plugin-ui-suite').'</a></p></div>';
    foreach ($sections as $title=>$content) echo '<div class="plugin-suite-card"><h2>'.esc_html($title).'</h2><p>'.esc_html($content).'</p></div>';
    echo '<div class="plugin-suite-card" style="grid-column:1/-1"><h2>GitHub support</h2><div class="plugin-suite-actions"><a class="button" href="'.esc_url(self::github_url('issues/new')).'" target="_blank" rel="noopener">Report a Bug</a><a class="button" href="'.esc_url(self::github_url('issues')).'" target="_blank" rel="noopener">Known Issues</a><a class="button" href="'.esc_url(self::github_url('issues/new?labels=enhancement')).'" target="_blank" rel="noopener">Feature Requests</a><a class="button" href="'.esc_url(self::github_url('releases/latest')).'" target="_blank" rel="noopener">Latest Releases</a><a class="button" href="'.esc_url(self::github_url()).'" target="_blank" rel="noopener">Repository</a></div></div>';
    if (class_exists('Plugin_UI_Suite_Registry')) foreach (Plugin_UI_Suite_Registry::help_items() as $guide) { echo '<div class="plugin-suite-card"><h2>'.esc_html($guide['title'] ?? '').'</h2><p>'.wp_kses_post($guide['content'] ?? '').'</p>'; if (!empty($guide['external_url'])) echo '<p><a class="button" href="'.esc_url($guide['external_url']).'" target="_blank" rel="noopener">'.esc_html__('Open official documentation','plugin-ui-suite').'</a></p>'; echo '</div>'; }
    echo '</div>';
  }

  private static function render_proxy_tab($settings) {
    $proxy = self::get_proxy_settings($settings);
    $status = self::proxy_status();
    self::form_start('proxy');
    echo '<div class="plugin-suite-grid">';
    echo '<div class="plugin-suite-card"><h2>Connection</h2><table class="form-table">';
    ob_start(); self::text_input('proxy','base_url',$proxy['base_url']); self::row('ASM base URL', ob_get_clean());
    ob_start(); self::text_input('proxy','account',$proxy['account']); self::row('ASM account', ob_get_clean());
    ob_start(); self::text_input('proxy','username',$proxy['username']); self::row('ASM username', ob_get_clean());
    printf('<tr><th>ASM password</th><td><input type="password" name="%s" value="%s" class="regular-text" autocomplete="new-password" /></td></tr>', esc_attr(self::field_name('proxy','password')), esc_attr($proxy['password']));
    echo '</table></div>';
    echo '<div class="plugin-suite-card"><h2>Cache</h2><table class="form-table">';
    foreach ([['cache_adoptables_seconds','Adoptables cache seconds'],['cache_reports_seconds','Reports cache seconds'],['cache_incare_seconds','In-care cache seconds'],['cache_adoptions_seconds','Adoptions cache seconds']] as $row) { ob_start(); self::number_input('proxy',$row[0],$proxy[$row[0]],0,3600); self::row($row[1], ob_get_clean()); }
    echo '</table></div>';
    echo '<div class="plugin-suite-card"><h2>Diagnostics</h2><table class="form-table">';
    self::row('Credential source', '<code>' . esc_html($status['credential_source']) . '</code>');
    self::row('Selected data source', esc_html($status['data_source']['label'] ?? 'Animal Shelter Manager (ASM)'));
    self::row('Connector state', esc_html($status['data_source']['state_label'] ?? 'Live'));
    self::row('Configuration', esc_html($status['data_source']['configured_label'] ?? 'Managed in this suite'));
    if (!empty($status['data_source']['missing'])) self::row('Missing details', esc_html(implode(', ', $status['data_source']['missing'])));
    self::row('Last successful test', esc_html($status['last_success'] ?: 'None yet'));
    self::row('Last error', esc_html($status['last_error'] ?: 'None'));
    self::row('REST routes', '<code>' . esc_html(implode(' | ', $status['routes'])) . '</code>');
    echo '</table></div>';
    echo '</div>';
    self::form_end();

    // Tool actions must not be nested inside the main settings form. Nested forms
    // cause browsers to close the settings form early, which prevents proxy
    // credentials from being submitted with the Save changes button.
    echo '<div class="plugin-suite-grid" style="margin-top:16px;">';
    echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Tools</h2><div class="plugin-suite-actions">';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_proxy_test'); echo '<input type="hidden" name="action" value="plugin_ui_suite_proxy_test" />'; submit_button('Test connection','secondary','submit',false); echo '</form>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_proxy_clear_cache'); echo '<input type="hidden" name="action" value="plugin_ui_suite_proxy_clear_cache" />'; submit_button('Clear proxy cache','secondary','submit',false); echo '</form>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_provider_diagnostics'); echo '<input type="hidden" name="action" value="plugin_ui_suite_provider_diagnostics" />'; submit_button('Run provider diagnostics','secondary','submit',false); echo '</form>';
    echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'plugin-ui-suite-setup'], admin_url('options-general.php'))) . '">Open setup wizard</a>';
    echo '</div>';
    self::render_provider_diagnostics_results();
    echo '</div></div>';
  }


  private static function image_cache_storage_bytes() {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir'] ?? '') . 'asm-plugin-suite-cache';
    if (!$dir || !is_dir($dir)) return 0;
    $bytes = 0; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) { if ($file->isFile()) $bytes += $file->getSize(); }
    return $bytes;
  }

  private static function render_provider_health_dashboard($settings) {
    $status = self::proxy_status(); $last_good = self::last_good_feed_summary(); $queue = get_option(self::webhook_queue_key(), []); if (!is_array($queue)) $queue = [];
    $diag = get_transient('plugin_ui_suite_provider_diagnostics'); $failed = 0;
    if (is_array($diag) && !empty($diag['results']) && is_array($diag['results'])) foreach ($diag['results'] as $row) { if (empty($row['ok'])) $failed++; }
    echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Provider health dashboard</h2><table class="plugin-suite-table"><tbody>';
    echo '<tr><th>Selected source</th><td>' . esc_html($status['data_source']['label'] ?? 'Unknown') . '</td></tr>';
    echo '<tr><th>Last successful sync/test</th><td>' . esc_html($status['last_success'] ?: 'None recorded') . '</td></tr>';
    echo '<tr><th>Last provider error</th><td>' . esc_html($status['last_error'] ?: 'None recorded') . '</td></tr>';
    echo '<tr><th>Failed endpoint checks</th><td>' . esc_html((string)$failed) . '</td></tr>';
    echo '<tr><th>Queued webhook deliveries</th><td>' . esc_html((string)count($queue)) . '</td></tr>';
    echo '<tr><th>Image cache storage</th><td>' . esc_html(size_format(self::image_cache_storage_bytes())) . '</td></tr>';
    foreach ($last_good as $label => $meta) echo '<tr><th>' . esc_html($label . ' cache age') . '</th><td>' . esc_html(($meta['time'] ?? 'None') . ' · items: ' . ($meta['count'] ?? 0)) . '</td></tr>';
    echo '</tbody></table></div>';
  }

  private static function redacted_settings($settings) {
    foreach (['custom_api_key','enquiry_webhook_secret','shelterluv_api_key','petpoint_password'] as $key) if (isset($settings['global'][$key])) $settings['global'][$key] = $settings['global'][$key] ? '[redacted]' : '';
    return $settings;
  }


  private static function github_url($path = '') {
    $base = apply_filters('plugin_ui_suite_github_repository_url', 'https://github.com/WebstaxStudio/Rescue-Plugin-Suite');
    return rtrim($base, '/') . ($path ? '/' . ltrim($path, '/') : '');
  }

  private static function system_information($settings = null) {
    if (!is_array($settings)) $settings = self::get_settings();
    $active_plugins = function_exists('get_plugins') ? get_plugins() : [];
    $active = function_exists('get_option') ? (array)get_option('active_plugins', []) : [];
    $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
    $status = self::proxy_status();
    $payments = class_exists('Plugin_UI_Suite_Payments') && method_exists('Plugin_UI_Suite_Payments','get_settings') ? Plugin_UI_Suite_Payments::get_settings() : [];
    return [
      'Plugin Version' => defined('PLUGIN_SUITE_VERSION') ? PLUGIN_SUITE_VERSION : '',
      'WordPress Version' => get_bloginfo('version'),
      'PHP Version' => PHP_VERSION,
      'Database Version' => $GLOBALS['wpdb']->db_version(),
      'Theme' => $theme ? $theme->get('Name') . ' ' . $theme->get('Version') : '',
      'Active Plugins' => array_values(array_map(function($file) use ($active_plugins){ return ($active_plugins[$file]['Name'] ?? $file) . (!empty($active_plugins[$file]['Version']) ? ' ' . $active_plugins[$file]['Version'] : ''); }, $active)),
      'Active Integrations' => $settings['global']['data_source'] ?? 'asm',
      'Active Payment Providers' => implode(', ', array_filter(array_keys((array)($payments['providers'] ?? [])), function($p) use ($payments){ return !empty($payments['providers'][$p]['enabled']); })),
      'REST API Status' => rest_url('plugin/v1/adoptables'),
      'Cron Status' => function_exists('wp_next_scheduled') && wp_next_scheduled(self::WEBHOOK_CRON_HOOK) ? 'Scheduled' : 'Not scheduled',
      'Debug Mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
      'Memory Limit' => ini_get('memory_limit'),
      'Upload Max Filesize' => ini_get('upload_max_filesize'),
      'Post Max Size' => ini_get('post_max_size'),
      'Site Health Summary' => 'Source: ' . ($status['data_source']['label'] ?? 'Unknown') . '; last success: ' . ($status['last_success'] ?: 'none') . '; last error: ' . ($status['last_error'] ?: 'none'),
      'Relevant Diagnostics' => ['webhook_queue_count'=>count((array)get_option(self::webhook_queue_key(), [])), 'image_cache_bytes'=>self::image_cache_storage_bytes(), 'schema_version'=>get_option(self::SCHEMA_VERSION_KEY, '')],
    ];
  }

  private static function format_system_information($settings = null) {
    $info = self::system_information($settings);
    $lines = ["### Rescue Plugin Suite System Information"];
    foreach ($info as $key => $value) {
      if (is_array($value)) $value = wp_json_encode($value, JSON_PRETTY_PRINT);
      $lines[] = '- ' . $key . ': ' . (string)$value;
    }
    return implode("\n", $lines);
  }

  public static function handle_copy_system_information() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_copy_system_information');
    nocache_headers(); header('Content-Type: text/plain; charset=' . get_bloginfo('charset')); header('Content-Disposition: attachment; filename=rescue-suite-system-information.txt'); echo esc_html(self::format_system_information()); exit;
  }

  public static function handle_download_diagnostics() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('plugin_ui_suite_download_diagnostics');
    $bundle = ['generated_at'=>current_time('mysql'), 'suite_version'=>defined('PLUGIN_SUITE_VERSION') ? PLUGIN_SUITE_VERSION : '', 'settings'=>self::redacted_settings(self::get_settings()), 'provider_health'=>self::proxy_status(), 'last_good_feeds'=>self::last_good_feed_summary(), 'webhook_queue_count'=>count((array)get_option(self::webhook_queue_key(), [])), 'image_cache_bytes'=>self::image_cache_storage_bytes(), 'environment'=>['php'=>PHP_VERSION,'wp'=>get_bloginfo('version'),'site_url'=>home_url('/'),'rest_url'=>rest_url('plugin/v1/adoptables')]];
    nocache_headers(); header('Content-Type: application/json; charset=' . get_bloginfo('charset')); header('Content-Disposition: attachment; filename=asm-suite-diagnostics.json'); echo wp_json_encode($bundle, JSON_PRETTY_PRINT); exit;
  }

  private static function render_diagnostics_tab($settings) {
    $log = get_option(self::LOG_KEY, []); if (!is_array($log)) $log = [];
    $snapshots = self::get_snapshots();
    $versions = self::module_versions();
    $status = self::proxy_status();
    $analytics = self::get_analytics();
    $sub = self::subtab_nav('diagnostics', ['overview'=>'Overview','health'=>'Provider health','sources'=>'Source contracts','versions'=>'Version log','snapshots'=>'Snapshots','modules'=>'Module import/export']);
    echo '<div class="plugin-suite-grid">';
    if($sub==='overview') {
      echo '<div class="plugin-suite-card"><h2>Versions</h2><table class="plugin-suite-table"><tbody>';
      foreach ($versions as $k => $v) echo '<tr><th>' . esc_html(ucwords(str_replace('_',' ',$k))) . '</th><td><code>' . esc_html($v) . '</code></td></tr>';
      echo '<tr><th>Settings option key</th><td><code>' . esc_html(self::OPT_KEY) . '</code></td></tr>';
      echo '</tbody></table></div>';
      echo '<div class="plugin-suite-card"><h2>Proxy health</h2><table class="plugin-suite-table"><tbody>';
      foreach (['credential_source' => 'Credential source', 'last_success' => 'Last successful test', 'last_error' => 'Last error'] as $key => $label) echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($status[$key] ?: 'None') . '</td></tr>';
      echo '<tr><th>Data source</th><td>' . esc_html($status['data_source']['label'] ?? 'Unknown') . '</td></tr>';
      echo '<tr><th>Connector state</th><td>' . esc_html($status['data_source']['state_label'] ?? 'Unknown') . '</td></tr>';
      echo '<tr><th>Configuration</th><td>' . esc_html($status['data_source']['configured_label'] ?? 'Unknown') . '</td></tr>';
      if (!empty($status['data_source']['missing'])) echo '<tr><th>Missing details</th><td>' . esc_html(implode(', ', $status['data_source']['missing'])) . '</td></tr>';
      echo '</tbody></table></div>';
      echo '<div class="plugin-suite-card"><h2>Analytics</h2><table class="plugin-suite-table"><tbody>';
      foreach ($analytics as $key => $count) echo '<tr><th>' . esc_html(ucwords(str_replace('_',' ',$key))) . '</th><td>' . esc_html((string)$count) . '</td></tr>';
      echo '</tbody></table></div>';
      $last_good = self::last_good_feed_summary();
      if (!empty($last_good)) { echo '<div class="plugin-suite-card"><h2>Last-known-good feeds</h2><table class="plugin-suite-table"><tbody>'; foreach ($last_good as $label=>$meta) echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html(($meta['time'] ?? 'None') . ' · items: ' . ($meta['count'] ?? 0)) . '</td></tr>'; echo '</tbody></table></div>'; }
      echo '<div class="plugin-suite-card"><h2>Support diagnostics</h2><p>Download a redacted support bundle with settings, REST route health, cache status and environment checks.</p><p><a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=plugin_ui_suite_download_diagnostics'), 'plugin_ui_suite_download_diagnostics')) . '">Download diagnostics bundle</a> <a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=plugin_ui_suite_copy_system_information'), 'plugin_ui_suite_copy_system_information')) . '">Copy System Information</a></p></div>';

    } elseif($sub==='health') {
      self::render_provider_health_dashboard($settings);
    } elseif($sub==='sources') {
      $runtime = self::source_runtime_config($settings);
      $ref = self::source_contract_reference();
      echo '<div class="plugin-suite-card"><h2>Selected source runtime</h2><table class="plugin-suite-table"><tbody>';
      foreach (($runtime['items'] ?? []) as $label => $value) echo '<tr><th>' . esc_html($label) . '</th><td><code>' . esc_html(((string)$value !== '' ? (string)$value : 'Not set')) . '</code></td></tr>';
      echo '</tbody></table><p class="description">' . esc_html($runtime['contract'] ?? '') . '</p></div>';
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Normalised source contract reference</h2><p class="description">These are the suite-standard fields the groundwork is preparing each connector to resolve into. Custom API can already feed these live.</p>';
      foreach ($ref as $section => $meta) {
        echo '<h3>' . esc_html(ucfirst($section)) . '</h3>';
        echo '<p><strong>Required:</strong> <code>' . esc_html(implode(', ', (array)($meta['required'] ?? []))) . '</code></p>';
        echo '<p><strong>Supported:</strong> <code>' . esc_html(implode(', ', (array)($meta['supported'] ?? []))) . '</code></p>';
      }
      echo '<p class="description">Example Custom API adoptables item: <code>{ID, ANIMALNAME, CODE, ANIMALAGE, AGE_MONTHS, AGE_BAND, SEXNAME, BREEDNAME, SPECIESID, SPECIESNAME, WEBSITEIMAGECOUNT, ANIMALCOMMENTS}</code></p></div>';
    } elseif($sub==='versions') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Version log</h2>';
      if (!$log) echo '<p>No version events logged yet.</p>';
      else { echo '<table class="plugin-suite-table"><thead><tr><th>Time</th><th>Event</th><th>Suite</th></tr></thead><tbody>'; foreach ($log as $entry) echo '<tr><td>' . esc_html($entry['time'] ?? '') . '</td><td><code>' . esc_html($entry['event'] ?? '') . '</code></td><td>' . esc_html($entry['suite_version'] ?? '') . '</td></tr>'; echo '</tbody></table>'; }
      echo '</div>';
    } elseif($sub==='snapshots') {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Settings snapshots</h2>';
      if (!$snapshots) { echo '<p>No snapshots yet. A snapshot is created before reset, import, module import and save.</p>'; }
      else {
        echo '<table class="plugin-suite-table"><thead><tr><th>Time</th><th>Reason</th><th>ID</th><th>Actions</th></tr></thead><tbody>';
        foreach ($snapshots as $snapshot) {
          echo '<tr><td>' . esc_html($snapshot['time'] ?? '') . '</td><td><code>' . esc_html($snapshot['reason'] ?? '') . '</code></td><td><code>' . esc_html(substr($snapshot['id'] ?? '', 0, 8)) . '</code></td><td><div class="plugin-suite-actions">';
          echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_restore_snapshot'); echo '<input type="hidden" name="action" value="plugin_ui_suite_restore_snapshot" /><input type="hidden" name="snapshot_id" value="' . esc_attr($snapshot['id'] ?? '') . '" />'; submit_button('Restore','secondary','submit',false); echo '</form>';
          echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_delete_snapshot'); echo '<input type="hidden" name="action" value="plugin_ui_suite_delete_snapshot" /><input type="hidden" name="snapshot_id" value="' . esc_attr($snapshot['id'] ?? '') . '" />'; submit_button('Delete','secondary','submit',false); echo '</form>';
          echo '</div></td></tr>';
        }
        echo '</tbody></table>';
      }
      echo '</div>';
    } else {
      echo '<div class="plugin-suite-card" style="grid-column:1/-1;"><h2>Module export / import</h2><div class="plugin-suite-actions">';
      foreach (['adoptables','adopted','stats','forms','proxy'] as $module) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('plugin_ui_suite_export_module'); echo '<input type="hidden" name="action" value="plugin_ui_suite_export_module" /><input type="hidden" name="module" value="' . esc_attr($module) . '" />'; submit_button('Export ' . ucfirst($module),'secondary','submit',false); echo '</form>';
      }
      echo '</div><div class="plugin-suite-actions" style="margin-top:12px;">';
      foreach (['adoptables','adopted','stats','forms','proxy'] as $module) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">'; wp_nonce_field('plugin_ui_suite_import_module'); echo '<input type="hidden" name="action" value="plugin_ui_suite_import_module" /><input type="hidden" name="module" value="' . esc_attr($module) . '" /><label>' . esc_html(ucfirst($module)) . ' import <input type="file" name="import_file" accept="application/json" /></label> '; submit_button('Import','secondary','submit',false); echo '</form>';
      }
      echo '</div></div>';
    }
    echo '</div>';
  }

}
