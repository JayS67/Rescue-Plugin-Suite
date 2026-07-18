<?php
/**
 * Module: Rescue Plugin Suite Forms Shortcodes
 * Description: Provides separate shortcodes for rescue management online forms ([plugin_adoption_form], [plugin_volunteer_form], [plugin_waiting_list_form], [plugin_lost_cat_form]).
 * Version: 1.0.0
 * Author: Rescue Plugin Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin_Forms_Shortcodes {

    public static function init() {
        add_shortcode('plugin_adoption_form', [__CLASS__, 'render_adoption']);
        // Backwards-compatible aliases used by earlier Suite releases and saved settings.
        add_shortcode('adoption_form', [__CLASS__, 'render_adoption']);
        add_shortcode('plugin_volunteer_form', [__CLASS__, 'render_volunteer']);
        add_shortcode('volunteer_form', [__CLASS__, 'render_volunteer']);
        add_shortcode('plugin_waiting_list_form', [__CLASS__, 'render_waiting_list']);
        add_shortcode('plugin_lost_cat_form', [__CLASS__, 'render_lost_cat']);
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
        $account = function_exists('plugin_asm_account') ? plugin_asm_account() : '';

        if (empty($account) || empty($form_id)) {
            return '<p>Form could not be loaded.</p>';
        }

        $script_url = function_exists('plugin_asm_online_form_url') ? esc_url(plugin_asm_online_form_url($form_id)) : '';

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

Plugin_Forms_Shortcodes::init();
