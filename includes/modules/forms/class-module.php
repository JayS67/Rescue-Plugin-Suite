<?php
/**
 * Plugin Name: Rescue Plugin Suite Forms Shortcodes
 * Description: Provides separate shortcodes for rescue management online forms ([straysafe_adoption_form], [straysafe_volunteer_form], [straysafe_waiting_list_form], [straysafe_lost_cat_form]).
 * Version: 1.0.0
 * Author: Rescue Plugin Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

final class StraySafe_Forms_Shortcodes {

    public static function init() {
        add_shortcode('straysafe_adoption_form', [__CLASS__, 'render_adoption']);
        // Backwards-compatible aliases used by earlier Suite releases and saved settings.
        add_shortcode('adoption_form', [__CLASS__, 'render_adoption']);
        add_shortcode('straysafe_volunteer_form', [__CLASS__, 'render_volunteer']);
        add_shortcode('volunteer_form', [__CLASS__, 'render_volunteer']);
        add_shortcode('straysafe_waiting_list_form', [__CLASS__, 'render_waiting_list']);
        add_shortcode('straysafe_lost_cat_form', [__CLASS__, 'render_lost_cat']);
    }

    public static function render_adoption($atts = []) {
        return self::render_form('59');
    }

    public static function render_volunteer($atts = []) {
        return self::render_form('104');
    }

    public static function render_waiting_list($atts = []) {
        return self::render_form('106');
    }

    public static function render_lost_cat($atts = []) {
        return self::render_form('51');
    }

    private static function render_form($form_id) {
        $form_id = sanitize_text_field($form_id);
        $settings = get_option('straysafe_ui_suite_settings_v83', []);
        $account = '';
        if (is_array($settings) && !empty($settings['forms']['account'])) {
            $account = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$settings['forms']['account']);
        } elseif (is_array($settings) && !empty($settings['proxy']['account'])) {
            $account = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$settings['proxy']['account']);
        }

        if (empty($account) || empty($form_id)) {
            return '<p>Form could not be loaded.</p>';
        }

        $script_url = esc_url(
            'https://service.sheltermanager.com/asmservice?account=' .
            rawurlencode($account) .
            '&method=online_form_js&formid=' .
            rawurlencode($form_id)
        );

        ob_start();
        ?>
        <section class="rescue-suite-form-wrap" aria-label="Online application form">
            <noscript><p>This application form requires JavaScript. Please contact the rescue if you need help applying.</p></noscript>
            <div id="asm3-onlineform" aria-live="polite"></div>
            <script type="text/javascript" src="<?php echo esc_url($script_url); ?>"></script>
        </section>
        <?php
        return ob_get_clean();
    }
}

StraySafe_Forms_Shortcodes::init();
