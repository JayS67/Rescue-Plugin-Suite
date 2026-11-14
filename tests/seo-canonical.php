<?php
/** Standalone canonical, metadata, sitemap and request-cache regressions. */
define('ABSPATH', __DIR__ . '/');
$queries = [];
$reads = 0;
$removed = [];
function get_query_var($key) { global $queries; return $queries[$key] ?? ''; }
function get_option($key, $default = []) { return $default; }
function get_transient($key) {
  global $reads; $reads++;
  return [['ANIMALID'=>'42', 'ANIMALNAME'=>'Mittens </script><script>alert(1)</script>', 'ANIMALCOMMENTS'=>'A friendly rescue cat.']];
}
function home_url($path = '') { return 'https://rescue.example' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function rest_url($path) { return 'https://rescue.example/wp-json/' . $path; }
function sanitize_title($value) { return 'mittens'; }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_unslash($value) { return $value; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_trim_words($value, ...$args) { return $value; }
function remove_action($hook, $callback) { global $removed; $removed[] = $callback; }
function is_singular() { return true; }
function get_queried_object() { return (object)['post_content'=>'[adoptables]']; }
function has_shortcode($content, $tag) { return strpos($content, '[' . $tag . ']') !== false; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function get_permalink($post) { return home_url('/adopt/'); }
require dirname(__DIR__) . '/includes/core/class-seo.php';
function ok($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "ok: $message\n"; }
$canonical = 'https://rescue.example/cats/42-mittens/';
$_GET['cat'] = '42';
$_SERVER['REQUEST_URI'] = '/adopt/?cat=42&utm_source=mail';
ok(Plugin_UI_Suite_SEO::filter_canonical('original') === $canonical, 'Modal and tracking variants select the crawlable profile canonical');
$_SERVER['REQUEST_URI'] = '//other.example/adopt/?cat=42';
ok(Plugin_UI_Suite_SEO::filter_canonical('original') === $canonical, 'Request URI cannot change the canonical host');
unset($_GET['cat']);
$queries[Plugin_UI_Suite_SEO::ADOPTABLE_VAR] = '42-old-name';
ok(Plugin_UI_Suite_SEO::filter_canonical('original') === $canonical, 'Old profile slugs canonicalise to the current profile name');
ob_start(); Plugin_UI_Suite_SEO::head_metadata(); $head = ob_get_clean();
ok(substr_count($head, 'rel="canonical"') === 1 && in_array('rel_canonical', $removed, true), 'Exactly one suite canonical replaces the WordPress page canonical');
ok(strpos($head, 'utm_source') === false, 'Tracking parameters are excluded from metadata');
$urls = Plugin_UI_Suite_SEO::sitemap_animals();
ok($urls[0]['url'] === $canonical && !isset($urls[0]['lastmod']), 'Sitemap uses profile URLs without fabricated modification dates');
ok($reads === 2, 'Repeated metadata and sitemap lookups read each animal collection once');
ob_start(); Plugin_UI_Suite_SEO::page_level_schema(); $schema = ob_get_clean();
ok(substr_count($schema, '</script>') === 1 && strpos($schema, '\\u003C') !== false, 'Feed text cannot terminate JSON-LD script elements');
$queries = [];
$_GET = ['adopted'=>'42'];
ok(Plugin_UI_Suite_SEO::filter_canonical('original') === 'https://rescue.example/happy-endings/42-mittens/', 'Adopted modal variants canonicalise to the adopted profile');
$_GET = ['cat'=>'mittens-script-script-alert-1-script'];
ok(Plugin_UI_Suite_SEO::filter_canonical('original') === $canonical, 'Name-based shared modal links select the same canonical');
$_GET = ['cat'=>'42'];
define('WPSEO_VERSION', 'test');
ob_start(); Plugin_UI_Suite_SEO::head_metadata(); $head = ob_get_clean();
ok($head === '', 'Active SEO plugins own canonical and social tags without duplicate suite output');
