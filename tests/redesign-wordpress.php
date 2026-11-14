<?php
/** Run in a disposable installed WordPress site: wp eval-file tests/redesign-wordpress.php */
if (!defined('ABSPATH') || !class_exists('Plugin_UI_Suite_Plugin')) { fwrite(STDERR, "Run through WP-CLI with this plugin active.\n"); exit(2); }
$check = static function($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "ok: $message\n"; };
$row = new ReflectionMethod('Plugin_UI_Suite_Plugin', 'row');
$row->setAccessible(true);
ob_start(); $row->invoke(null, 'Year', '<select name="year"><option>2026</option></select>'); $html = ob_get_clean();
preg_match('/<th scope="row" id="([^"]+)"/', $html, $heading);
$check(!empty($heading[1]) && strpos($html, 'aria-labelledby="'.$heading[1].'"') !== false, 'Settings controls reference the visible row heading');
$html = do_shortcode('[stats]');
$check(strpos($html, 'aria-describedby=') !== false && strpos($html, 'Rescue impact statistics') !== false, 'Impact widget exposes its text alternative');
$check(strpos($html, 'aria-label="Impact statistics year"') !== false, 'Impact year selector has an accessible name');
$check(strpos($html, '<h1') === false, 'Embedded impact widget does not add a second page-level heading');
$check(!preg_match('~<script(?:\\s|>)~', $html), 'Executable widget scripts stay outside post content');
$inline = implode("\n", wp_scripts()->get_data('rescue-plugin-suite-widgets', 'after') ?: []);
$check(strpos($inline, '&&') !== false && strpos($inline, '&#038;') === false, 'Widget operators survive block-theme text processing');
$runtime_compilers = array_filter(wp_scripts()->registered, static function($script) { return strpos((string)$script->src, 'cdn.tailwindcss.com') !== false; });
$check(!$runtime_compilers, 'No browser-side Tailwind compiler is registered');
$check(wp_style_is('rescue-plugin-suite-utilities', 'enqueued'), 'Widgets use the bundled utility stylesheet');
