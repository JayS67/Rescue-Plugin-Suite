<?php
if (!defined('ABSPATH')) exit;

/**
 * SEO and public profile layer for Rescue Plugin Suite.
 * Keeps modal-based UIs while exposing crawlable, canonical animal pages.
 */
final class Plugin_UI_Suite_SEO {
  const ADOPTABLE_VAR = 'plugin_animal_slug';
  const ADOPTED_VAR   = 'plugin_adopted_slug';

  public static function init() {
    add_action('init', [__CLASS__, 'rewrite_rules'], 5);
    add_filter('query_vars', [__CLASS__, 'query_vars']);
    add_action('template_redirect', [__CLASS__, 'render_profile_page']);
    add_filter('document_title_parts', [__CLASS__, 'title_parts']);
    add_action('wp_head', [__CLASS__, 'head_metadata'], 2);
    add_filter('robots_txt', [__CLASS__, 'robots_txt'], 10, 2);
    add_action('wp_sitemaps_init', [__CLASS__, 'register_sitemap_provider']);
    add_action('wp_head', [__CLASS__, 'page_level_schema'], 30);
    add_filter('wpseo_canonical', [__CLASS__, 'filter_canonical']);
    add_filter('wpseo_title', [__CLASS__, 'filter_seo_title']);
    add_filter('wpseo_metadesc', [__CLASS__, 'filter_description']);
    add_filter('wpseo_opengraph_title', [__CLASS__, 'filter_seo_title']);
    add_filter('wpseo_opengraph_desc', [__CLASS__, 'filter_description']);
    add_filter('wpseo_opengraph_image', [__CLASS__, 'filter_social_image']);
    add_filter('wpseo_twitter_title', [__CLASS__, 'filter_seo_title']);
    add_filter('wpseo_twitter_description', [__CLASS__, 'filter_description']);
    add_filter('wpseo_twitter_image', [__CLASS__, 'filter_social_image']);
    add_filter('rank_math/frontend/canonical', [__CLASS__, 'filter_canonical']);
    add_filter('rank_math/frontend/title', [__CLASS__, 'filter_seo_title']);
    add_filter('rank_math/frontend/description', [__CLASS__, 'filter_description']);
    add_filter('rank_math/opengraph/facebook/title', [__CLASS__, 'filter_seo_title']);
    add_filter('rank_math/opengraph/facebook/description', [__CLASS__, 'filter_description']);
    add_filter('rank_math/opengraph/facebook/image', [__CLASS__, 'filter_social_image']);
    add_filter('rank_math/opengraph/twitter/title', [__CLASS__, 'filter_seo_title']);
    add_filter('rank_math/opengraph/twitter/description', [__CLASS__, 'filter_description']);
    add_filter('rank_math/opengraph/twitter/image', [__CLASS__, 'filter_social_image']);
  }

  public static function rewrite_rules() {
    add_rewrite_rule('^cats/([^/]+)/?$', 'index.php?' . self::ADOPTABLE_VAR . '=$matches[1]', 'top');
    add_rewrite_rule('^happy-endings/([^/]+)/?$', 'index.php?' . self::ADOPTED_VAR . '=$matches[1]', 'top');
  }

  public static function query_vars($vars) {
    $vars[] = self::ADOPTABLE_VAR;
    $vars[] = self::ADOPTED_VAR;
    return $vars;
  }

  public static function profile_url($animal, $adopted = false) {
    $id = self::animal_id($animal);
    $name = self::animal_name($animal);
    if (!$id) return home_url('/');
    $base = $adopted ? 'happy-endings' : 'cats';
    return home_url('/' . $base . '/' . rawurlencode($id . '-' . sanitize_title($name)) . '/');
  }

  private static function ui_modal_url($animal, $adopted = false) {
    $id = self::animal_id($animal);
    if (!$id) return self::profile_url($animal, $adopted);
    $settings = class_exists('Plugin_UI_Suite_Plugin') ? Plugin_UI_Suite_Plugin::get_settings() : get_option('plugin_ui_suite_settings_v83', []);
    $global = is_array($settings) && isset($settings['global']) && is_array($settings['global']) ? $settings['global'] : [];
    $base = $adopted ? ($global['adopted_page_url'] ?? '') : ($global['adoptables_page_url'] ?? '');
    $base = esc_url_raw($base);
    if ($base === '') return self::profile_url($animal, $adopted);
    return add_query_arg([$adopted ? 'adopted' : 'cat' => $id], $base);
  }

  private static function animal_id($a) {
    if (!is_array($a)) return '';
    $raw = $a['ID'] ?? $a['ANIMALID'] ?? $a['AnimalID'] ?? $a['animalid'] ?? '';
    return preg_replace('/\D+/', '', (string)$raw);
  }

  private static function animal_name($a) {
    if (!is_array($a)) return 'Cat';
    $name = trim((string)($a['ANIMALNAME'] ?? $a['AnimalName'] ?? $a['NAME'] ?? 'Cat'));
    return $name !== '' ? $name : 'Cat';
  }

  private static function shelter_code($a) {
    if (!is_array($a)) return '';
    return self::pick($a, ['CODE','SHELTERCODE','ShelterCode','sheltercode'], '');
  }

  private static function slug_part($value) {
    $value = strtolower((string)$value);
    $value = str_replace('&', ' and ', $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return preg_replace('/-{2,}/', '-', $value);
  }

  private static function modal_slug($animal) {
    $name = self::slug_part(self::animal_name($animal));
    $code = self::slug_part(self::shelter_code($animal));
    $slug = trim(implode('-', array_filter([$name, $code])), '-');
    return $slug !== '' ? $slug : self::animal_id($animal);
  }

  private static function pick($a, $keys, $fallback = '') {
    foreach ($keys as $key) {
      if (isset($a[$key]) && trim((string)$a[$key]) !== '') return trim((string)$a[$key]);
    }
    return $fallback;
  }

  private static function description($a) {
    return self::pick($a, ['ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION','AnimalComments','WebsiteMediaNotes'], '');
  }

  private static function image_url($a, $seq = 1) {
    $id = self::animal_id($a);
    return $id ? add_query_arg(['animalid'=>$id, 'seq'=>(int)$seq], rest_url('plugin/v1/animal-image')) : '';
  }

  private static function cache_bypass() {
    if (function_exists('plugin_suite_cache_bypass')) return plugin_suite_cache_bypass();
    $settings = get_option('plugin_ui_suite_settings_v83', []);
    return is_array($settings) && !empty($settings['global']['bypass_plugin_cache']);
  }

  private static function cache_get($key) {
    if (self::cache_bypass()) return false;
    return get_transient($key);
  }

  private static function cache_set($key, $value, $ttl) {
    if (self::cache_bypass()) return;
    set_transient($key, $value, (int)$ttl);
  }

  private static function fetch_custom_api_items($endpoint_key, $preferred_key, $normalizer, $query = []) {
    if (!function_exists('plugin_custom_api_config') || !function_exists('plugin_custom_api_request') || !function_exists('plugin_custom_api_extract_items')) return [];
    $cfg = plugin_custom_api_config();
    $url = $cfg[$endpoint_key] ?? '';
    if (!$url) return [];
    if (!empty($cfg['api_key'])) $query['api_key'] = $cfg['api_key'];
    $res = plugin_custom_api_request($url, $query);
    if (is_wp_error($res)) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) return [];
    $items = plugin_custom_api_extract_items($data, $preferred_key);
    $items = array_values(array_filter($items, 'is_array'));
    return function_exists($normalizer) ? array_values(array_map($normalizer, $items)) : $items;
  }

  private static function fetch_adoptables() {
    $cache = self::cache_get('asm_suite_seo_adoptables_v1');
    if (is_array($cache)) return $cache;
    $source = function_exists('plugin_suite_data_source') ? plugin_suite_data_source() : 'asm';
    if ($source === 'custom_api') {
      $data = self::fetch_custom_api_items('adoptables_url', 'adoptables', 'plugin_custom_api_normalize_adoptable');
      self::cache_set('asm_suite_seo_adoptables_v1', $data, 300);
      return $data;
    }
    if ($source !== 'asm') return [];
    if (!function_exists('plugin_asm_http_get')) return [];
    $res = plugin_asm_http_get([
      'method' => 'json_adoptable_animals',
      'username' => function_exists('plugin_asm_user') ? plugin_asm_user() : '',
      'password' => function_exists('plugin_asm_pass') ? plugin_asm_pass() : '',
    ]);
    if (is_wp_error($res)) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) return [];
    self::cache_set('asm_suite_seo_adoptables_v1', $data, 300);
    return $data;
  }

  private static function fetch_adopted() {
    $cache = self::cache_get('asm_suite_seo_adopted_v1');
    if (is_array($cache)) return $cache;
    $source = function_exists('plugin_suite_data_source') ? plugin_suite_data_source() : 'asm';
    if ($source === 'custom_api') {
      $data = self::fetch_custom_api_items('adoptions_url', 'adoptions', 'plugin_custom_api_normalize_adoption', ['years' => 10]);
      self::cache_set('asm_suite_seo_adopted_v1', $data, 900);
      return $data;
    }
    if ($source !== 'asm') return [];
    if (!function_exists('plugin_asm_http_get')) return [];
    $to = current_time('timestamp');
    $from = strtotime('-10 years', $to);
    $res = plugin_asm_http_get([
      'method' => 'json_adopted_animals',
      'username' => function_exists('plugin_asm_user') ? plugin_asm_user() : '',
      'password' => function_exists('plugin_asm_pass') ? plugin_asm_pass() : '',
      'fromdate' => date('d/m/Y', $from),
      'todate' => date('d/m/Y', $to),
    ]);
    if (is_wp_error($res)) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) return [];
    self::cache_set('asm_suite_seo_adopted_v1', $data, 900);
    return $data;
  }

  private static function requested_profile() {
    $adoptable = get_query_var(self::ADOPTABLE_VAR);
    $adopted = get_query_var(self::ADOPTED_VAR);
    if (!$adoptable && !$adopted) return null;
    $is_adopted = (bool)$adopted;
    $slug = $is_adopted ? $adopted : $adoptable;
    preg_match('/^(\d+)/', (string)$slug, $m);
    $id = $m[1] ?? '';
    if (!$id) return ['animal'=>null, 'adopted'=>$is_adopted];
    $list = $is_adopted ? self::fetch_adopted() : self::fetch_adoptables();
    foreach ($list as $animal) {
      if (self::animal_id($animal) === $id) return ['animal'=>$animal, 'adopted'=>$is_adopted];
    }
    return ['animal'=>null, 'adopted'=>$is_adopted];
  }

  private static function requested_modal_profile() {
    $cat = isset($_GET['cat']) ? sanitize_text_field(wp_unslash($_GET['cat'])) : '';
    $adopted = isset($_GET['adopted']) ? sanitize_text_field(wp_unslash($_GET['adopted'])) : '';
    if ($cat === '' && $adopted === '') return null;
    $is_adopted = $adopted !== '';
    $wanted_raw = $is_adopted ? $adopted : $cat;
    $wanted = self::slug_part($wanted_raw);
    if ($wanted === '') return null;
    $list = $is_adopted ? self::fetch_adopted() : self::fetch_adoptables();
    foreach ($list as $animal) {
      $id = self::animal_id($animal);
      $slug = self::modal_slug($animal);
      if ($wanted === strtolower($id) || $wanted === $slug || ($id !== '' && strpos($wanted, strtolower($id) . '-') === 0)) {
        return ['animal'=>$animal, 'adopted'=>$is_adopted, 'modal'=>true];
      }
    }
    return null;
  }

  private static function current_profile() {
    $profile = self::requested_profile();
    if ($profile && !empty($profile['animal'])) {
      $profile['modal'] = false;
      return $profile;
    }
    $modal = self::requested_modal_profile();
    return ($modal && !empty($modal['animal'])) ? $modal : null;
  }

  private static function preview_title($profile) {
    $name = self::animal_name($profile['animal']);
    $code = self::shelter_code($profile['animal']);
    return $code !== '' ? $name . ' - ' . $code : $name;
  }

  private static function preview_description($profile) {
    $a = $profile['animal'];
    $name = self::animal_name($a);
    $breed = self::pick($a, ['BREEDNAME','BreedName','BREEDNAME1','BreedName1'], 'cat');
    $desc = self::description($a);
    if ($desc === '') $desc = !empty($profile['adopted']) ? "Read {$name}'s adoption success story." : "Meet {$name}, a {$breed} currently available for adoption.";
    return wp_trim_words(wp_strip_all_tags($desc), 32, '…');
  }

  private static function preview_url($profile) {
    if (!empty($profile['modal']) && !empty($_SERVER['REQUEST_URI'])) {
      $uri = (string) wp_unslash($_SERVER['REQUEST_URI']);
      if (strpos($uri, '/') === 0) return home_url($uri);
    }
    return !empty($profile['modal']) ? self::ui_modal_url($profile['animal'], !empty($profile['adopted'])) : self::profile_url($profile['animal'], !empty($profile['adopted']));
  }

  public static function title_parts($parts) {
    $profile = self::current_profile();
    if (!$profile || empty($profile['animal'])) return $parts;
    $parts['title'] = self::preview_title($profile);
    return $parts;
  }

  public static function filter_canonical($value) {
    $profile = self::current_profile();
    return ($profile && !empty($profile['animal'])) ? self::preview_url($profile) : $value;
  }

  public static function filter_seo_title($value) {
    $profile = self::current_profile();
    return ($profile && !empty($profile['animal'])) ? self::preview_title($profile) : $value;
  }

  public static function filter_description($value) {
    $profile = self::current_profile();
    if (!$profile || empty($profile['animal'])) return $value;
    return self::preview_description($profile);
  }

  public static function filter_social_image($value) {
    $profile = self::current_profile();
    if (!$profile || empty($profile['animal'])) return $value;
    $image = self::image_url($profile['animal']);
    return $image ?: $value;
  }

  public static function head_metadata() {
    $profile = self::current_profile();
    if (!$profile || empty($profile['animal'])) return;
    $a = $profile['animal'];
    $title = self::preview_title($profile);
    $desc = self::preview_description($profile);
    $url = self::preview_url($profile);
    $image = self::image_url($a);
    $seo_plugin_active = defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION');
    if (!$seo_plugin_active) {
      echo "\n" . '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
      echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    if ($image) echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
    if ($image) echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
  }

  public static function render_profile_page() {
    $profile = self::requested_profile();
    if (!$profile) return;
    if (empty($profile['animal'])) {
      global $wp_query;
      $wp_query->set_404(); status_header(404); nocache_headers();
      return;
    }
    $a = $profile['animal'];
    $adopted = $profile['adopted'];
    $name = self::animal_name($a);
    $age = self::pick($a, ['ANIMALAGE','AnimalAge']);
    $sex = self::pick($a, ['SEXNAME','SexName','SEX']);
    $breed = self::pick($a, ['BREEDNAME','BreedName','BREEDNAME1','BreedName1']);
    $code = self::pick($a, ['CODE','SHELTERCODE','ShelterCode']);
    $desc = self::description($a);
    $image = self::image_url($a);
    $canonical = self::profile_url($a, $adopted);
    $schema = [
      '@context'=>'https://schema.org', '@type'=>'Article',
      'headline'=>$adopted ? $name . ' adoption success story' : $name . ' available for adoption',
      'description'=>wp_strip_all_tags($desc ?: ($adopted ? 'An adoption success story.' : 'A cat available for adoption.')),
      'mainEntityOfPage'=>$canonical,
      'image'=>$image ? [$image] : [],
      'about'=>[
        '@type'=>'Thing', 'name'=>$name, 'additionalType'=>'https://schema.org/Animal',
        'additionalProperty'=>array_values(array_filter([
          $breed ? ['@type'=>'PropertyValue','name'=>'Breed','value'=>$breed] : null,
          $age ? ['@type'=>'PropertyValue','name'=>'Age','value'=>$age] : null,
          $sex ? ['@type'=>'PropertyValue','name'=>'Sex','value'=>$sex] : null,
        ])),
      ],
    ];
    status_header(200);
    get_header();
    ?>
    <main id="primary" class="site-main asm-animal-profile" style="max-width:1040px;margin:0 auto;padding:32px 20px;">
      <article>
        <nav aria-label="Breadcrumb" style="margin-bottom:18px;"><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> <span aria-hidden="true">›</span> <span><?php echo esc_html($adopted ? 'Happy endings' : 'Cats for adoption'); ?></span></nav>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:32px;align-items:start;">
          <div><?php if ($image): ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name . ($breed ? ', ' . $breed : '') . ($adopted ? ', adopted cat' : ', cat available for adoption')); ?>" width="900" height="900" style="width:100%;height:auto;aspect-ratio:1/1;object-fit:cover;border-radius:24px;" fetchpriority="high"><?php endif; ?></div>
          <div>
            <h1><?php echo esc_html($name); ?></h1>
            <p><strong><?php echo esc_html($adopted ? 'Adoption success story' : 'Available for adoption'); ?></strong></p>
            <dl style="display:grid;grid-template-columns:max-content 1fr;gap:8px 18px;">
              <?php if ($breed): ?><dt><strong>Breed</strong></dt><dd><?php echo esc_html($breed); ?></dd><?php endif; ?>
              <?php if ($age): ?><dt><strong>Age</strong></dt><dd><?php echo esc_html($age); ?></dd><?php endif; ?>
              <?php if ($sex): ?><dt><strong>Sex</strong></dt><dd><?php echo esc_html($sex); ?></dd><?php endif; ?>
              <?php if ($code): ?><dt><strong>Shelter code</strong></dt><dd><?php echo esc_html($code); ?></dd><?php endif; ?>
            </dl>
            <?php if ($desc): ?><div style="margin-top:24px;line-height:1.7;"><?php echo wpautop(esc_html($desc)); ?></div><?php endif; ?>
            <?php if (!$adopted): ?><p style="margin-top:24px;"><a href="<?php echo esc_url(home_url('/adopt/')); ?>" class="button">Apply to adopt <?php echo esc_html($name); ?></a></p><?php endif; ?>
          </div>
        </div>
      </article>
      <script type="application/ld+json"><?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?></script>
    </main>
    <?php
    get_footer();
    exit;
  }

  public static function page_level_schema() {
    if (!is_singular()) return;
    $post = get_queried_object();
    if (!$post || empty($post->post_content)) return;
    $settings = class_exists('Plugin_UI_Suite_Plugin') ? Plugin_UI_Suite_Plugin::get_settings() : [];
    $content = (string)$post->post_content;
    $schemas = [];
    $adoptables_tag = $settings['shortcodes']['adoptables'] ?? 'adoptables';
    $adopted_tag = $settings['shortcodes']['adopted'] ?? 'adopted';
    $quiz_tag = $settings['quiz']['quiz_shortcode'] ?? 'adoption_match_quiz';
    if (has_shortcode($content, $adoptables_tag) || has_shortcode($content, 'plugin_adoptables_ui')) {
      $items = [];
      foreach (array_slice(self::fetch_adoptables(), 0, 50) as $i=>$a) {
        $items[] = ['@type'=>'ListItem','position'=>$i+1,'url'=>self::ui_modal_url($a,false),'name'=>self::animal_name($a)];
      }
      $schemas[] = ['@context'=>'https://schema.org','@type'=>'ItemList','name'=>'Cats available for adoption','itemListElement'=>$items];
    }
    if (has_shortcode($content, $adopted_tag) || has_shortcode($content, 'plugin_adopted_ui')) {
      $items = [];
      foreach (array_slice(self::fetch_adopted(), 0, 50) as $i=>$a) {
        $items[] = ['@type'=>'ListItem','position'=>$i+1,'url'=>self::ui_modal_url($a,true),'name'=>self::animal_name($a)];
      }
      $schemas[] = ['@context'=>'https://schema.org','@type'=>'ItemList','name'=>'Adoption success stories','itemListElement'=>$items];
    }
    if (has_shortcode($content, $quiz_tag)) {
      $schemas[] = ['@context'=>'https://schema.org','@type'=>'WebApplication','name'=>'Cat adoption match quiz','applicationCategory'=>'LifestyleApplication','operatingSystem'=>'Web','url'=>get_permalink($post)];
    }
    $suite_tags = [];
    if (class_exists('Plugin_UI_Suite_Plugin')) $suite_tags = Plugin_UI_Suite_Plugin::all_suite_shortcodes();
    $has_suite = false;
    foreach ($suite_tags as $tag) { if ($tag && has_shortcode($content, $tag)) { $has_suite = true; break; } }
    if ($has_suite) {
      $schemas[] = [
        '@context'=>'https://schema.org', '@type'=>'NGO', 'name'=>get_bloginfo('name') ?: 'Animal rescue',
        'url'=>home_url('/'), 'areaServed'=>['@type'=>'AdministrativeArea','name'=>'Greater Manchester'],
        'knowsAbout'=>['Cat rescue','Cat adoption','Cat fostering','Animal welfare']
      ];
    }
    $form_tags = class_exists('Plugin_UI_Suite_Plugin') ? array_keys(Plugin_UI_Suite_Plugin::get_forms()) : [];
    foreach ($form_tags as $tag) {
      if ($tag && has_shortcode($content, $tag)) {
        $schemas[] = ['@context'=>'https://schema.org','@type'=>'ContactPage','name'=>get_the_title($post),'url'=>get_permalink($post)];
        break;
      }
    }
    foreach ($schemas as $schema) {
      echo "\n<script type=\"application/ld+json\">" . wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
  }

  public static function robots_txt($output, $public) {
    if ($public) $output .= "\nSitemap: " . esc_url_raw(home_url('/wp-sitemap.xml')) . "\n";
    return $output;
  }

  public static function register_sitemap_provider($server) {
    if (!class_exists('WP_Sitemaps_Provider')) return;
    $provider = new class extends WP_Sitemaps_Provider {
      public function __construct() { $this->name='plugin-animals'; $this->object_type='plugin-animals'; }
      public function get_url_list($page_num, $object_subtype='') {
        $urls=[];
        foreach (Plugin_UI_Suite_SEO::sitemap_animals() as $item) $urls[]=['loc'=>$item['url'],'lastmod'=>$item['lastmod']];
        return $urls;
      }
      public function get_max_num_pages($object_subtype='') { return 1; }
    };
    $server->registry->add_provider('plugin-animals', $provider);
  }

  public static function sitemap_animals() {
    $out=[]; $lastmod=current_time('c');
    foreach (self::fetch_adoptables() as $a) $out[]=['url'=>self::ui_modal_url($a,false),'lastmod'=>$lastmod];
    foreach (self::fetch_adopted() as $a) $out[]=['url'=>self::ui_modal_url($a,true),'lastmod'=>$lastmod];
    return $out;
  }
}
