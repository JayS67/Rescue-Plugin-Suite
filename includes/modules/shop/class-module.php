<?php
if (!defined('ABSPATH')) exit;

/** Suite bridge for the uploaded shop. Legacy storage identifiers remain stable. */
final class Plugin_Rescue_Shop_Module {
  private static $booted = false;
  private static $legacy_active = false;

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'redirect_legacy_admin'], 1);
    add_action('admin_init', [__CLASS__, 'redirect_legacy_admin']);
    add_filter('parent_file', [__CLASS__, 'parent_file'], 20);
    add_filter('submenu_file', [__CLASS__, 'submenu_file'], 20);
    if (did_action('plugins_loaded')) self::boot();
    else add_action('plugins_loaded', [__CLASS__, 'boot'], 20);
  }

  public static function boot() {
    if (self::$booted) return;
    self::$booted = true;
    // The standalone plugin owns the same CPTs, AJAX routes and scheduled hook.
    // Do not register a second engine or deactivate a working shop automatically.
    self::$legacy_active = class_exists('StraySafe_Shop', false);
    if (self::$legacy_active) return;
    if (!defined('RPS_SHOP_VERSION')) define('RPS_SHOP_VERSION', PLUGIN_SUITE_VERSION);
    if (!defined('RPS_SHOP_PATH')) define('RPS_SHOP_PATH', __DIR__ . '/');
    if (!defined('RPS_SHOP_URL')) define('RPS_SHOP_URL', PLUGIN_SUITE_URL . 'includes/modules/shop/');
    require_once __DIR__ . '/class-engine.php';
    Rescue_Shop_Engine::init();
    foreach (['rescue_shop'=>'catalogue','rescue_shop_product'=>'product_shortcode','rescue_shop_basket'=>'basket_shortcode'] as $tag=>$method) {
      add_shortcode($tag, ['Rescue_Shop_Engine', $method]);
    }
    // Refresh only missing defaults. Existing products, orders, credentials and modes
    // are intentionally retained when switching from the standalone shop.
    add_option('ssshop_intro', 'Every purchase helps animals in need.');
    add_option('ssshop_sustainability', 'Choose digital where available for a paper-free way to send something special while supporting rescue work.');
    add_option('ssshop_stripe_mode', 'test');
    add_option('ssshop_shop_title', 'Rescue Shop');
    if (!wp_next_scheduled('ssshop_delivery_tick')) wp_schedule_event(time()+300, 'hourly', 'ssshop_delivery_tick');
  }

  public static function activate() {
    self::boot();
    if (!self::$legacy_active && class_exists('Rescue_Shop_Engine', false)) Rescue_Shop_Engine::register_content();
  }

  public static function deactivate() {
    if (!self::$legacy_active) wp_clear_scheduled_hook('ssshop_delivery_tick');
  }

  public static function admin_url($section='overview', array $args=[]) {
    return add_query_arg(array_merge(['page'=>'plugin-ui-suite','tab'=>'shop','shop_section'=>$section], $args), admin_url('admin.php'));
  }

  public static function render_admin_page() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="rps-shop-admin" data-rps-ui><h2>Rescue Shop</h2>';
    if (self::$legacy_active) {
      echo '<div class="notice notice-info inline"><p>The standalone shop is still active. Deactivate the standalone shop in <a href="' . esc_url(admin_url('plugins.php')) . '">Plugins</a> to use Rescue Shop inside the suite. Existing products, orders, settings, basket links and e-card links are retained. Do not delete shop data.</p></div></div>';
      return;
    }
    if (!class_exists('Rescue_Shop_Engine', false)) { echo '<p>The shop could not be loaded.</p></div>'; return; }
    $section = sanitize_key(wp_unslash($_GET['shop_section'] ?? 'overview'));
    $links = [
      'overview'=>['Overview', self::admin_url()],
      'products'=>['Products', admin_url('edit.php?post_type=ssshop_product')],
      'orders'=>['Orders', admin_url('edit.php?post_type=ssshop_order')],
      'categories'=>['Categories', admin_url('edit-tags.php?taxonomy=ssshop_category&post_type=ssshop_product')],
      'settings'=>['Settings', self::admin_url('settings')],
    ];
    echo '<nav class="plugin-suite-subnav" aria-label="Shop sections">';
    foreach ($links as $key=>$link) echo '<a href="' . esc_url($link[1]) . '" ' . ($section===$key ? 'class="active" aria-current="page"' : '') . '>' . esc_html($link[0]) . '</a>';
    echo '</nav>';
    ob_start();
    if ($section === 'settings') Rescue_Shop_Engine::settings_page();
    else Rescue_Shop_Engine::dashboard();
    $html = ob_get_clean();
    // Embedded suite panels already supply the page's H1 and outer wrapper.
    $html = preg_replace('~<h1\b~i', '<h2', $html);
    $html = str_ireplace('</h1>', '</h2>', $html);
    echo self::accessible_admin_html($html);
    echo '<div class="plugin-suite-note"><strong>Storefront:</strong> <code>[rescue_shop]</code> &nbsp; <strong>Basket:</strong> <code>[rescue_shop_basket]</code><p>Existing shop shortcodes and data are supported. Physical products remain enquiry-only; digital products use the shop’s Stripe checkout.</p></div></div>';
  }

  private static function accessible_admin_html($html) {
    $html = str_replace('<nav class="nav-tab-wrapper">', '<nav class="nav-tab-wrapper" aria-label="Shop settings sections">', $html);
    $html = preg_replace('/(<a\b[^>]*class="[^"]*nav-tab-active[^"]*")/', '$1 aria-current="page"', $html);
    $counter = 0;
    return preg_replace_callback('~<tr\b[^>]*>.*?</tr>~is', static function($match) use (&$counter) {
      $row = $match[0];
      if (!preg_match('~<th\b[^>]*>~i', $row)) return $row;
      $id = 'rps-shop-field-' . (++$counter);
      $row = preg_replace('~<th\b([^>]*)>~i', '<th$1 scope="row" id="' . $id . '">', $row, 1);
      return preg_replace_callback('~<(input|select|textarea)\b[^>]*>~i', static function($control) use ($id) {
        if (preg_match('~type=["\'](?:hidden|submit|button)["\']|aria-label(?:ledby)?=~i', $control[0])) return $control[0];
        return preg_replace('~^<(input|select|textarea)~i', '<$1 aria-labelledby="' . $id . '"', $control[0]);
      }, $row);
    }, $html);
  }

  public static function redirect_legacy_admin() {
    if (self::$legacy_active || !current_user_can('manage_options')) return;
    $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
    if (!in_array($page, ['straysafe-shop','straysafe-shop-settings'], true)) return;
    $args = [];
    if ($page === 'straysafe-shop-settings') $args['shop_tab'] = sanitize_key(wp_unslash($_GET['tab'] ?? 'shop'));
    wp_safe_redirect(self::admin_url($page === 'straysafe-shop-settings' ? 'settings' : 'overview', $args));
    exit;
  }

  private static function is_shop_editor() {
    global $typenow;
    $type = $typenow ?: sanitize_key(wp_unslash($_GET['post_type'] ?? ''));
    if (!$type && !empty($_GET['post'])) $type = get_post_type(absint($_GET['post']));
    return in_array($type, ['ssshop_product','ssshop_order'], true) || ($_GET['taxonomy'] ?? '') === 'ssshop_category';
  }
  public static function parent_file($parent) { return !self::$legacy_active && self::is_shop_editor() ? 'plugin-ui-suite' : $parent; }
  public static function submenu_file($submenu) { return !self::$legacy_active && self::is_shop_editor() ? 'plugin-ui-suite-shop' : $submenu; }
}
Plugin_Rescue_Shop_Module::init();
