<?php
/**
 * Plugin Name: ASM Plugin Suite Statistics
 * Description: Adds the statistics UI via shortcode [stats] with settings.
 * Version: 12
 * Author: Jordan Sutton
 */

if (!defined('ABSPATH')) exit;

define('STRAYSAFE_STATS_OPT', 'straysafe_stats_ui_options');
define('STRAYSAFE_STATS_RESET_ACTION', 'straysafe_stats_ui_reset_field');

/**
 * Defaults
 */
function straysafe_stats_ui_default_options() {
  return [
    // Core look
    'brand_color'      => '#ff647e',
    'background_color' => '#f9d6dd',
    'min_year'         => 2026,

    // Paw prints (match Adoptables/Adopted)
    'paw_opacity' => 0.08,  // peak opacity at 50% of animation
    'paw_count'   => 10,    // how many pawprints to show (max 80)

    // Text
    'title_text'        => 'Our Rescue Impact',
    'year_label_prefix' => "This Year's Statistics —",
    'footer_text'       => 'Every number represents a life changed. Thank you for supporting rescue work. 🐾',

    // Leave blank to inherit theme font
    'font_family'      => '',

    // Card styling
    'card_radius'      => 16,
    'card_padding'     => 24,

    // Layout: 'grid' OR 'one_row' (kept for backwards compatibility)
    'layout_mode'      => 'grid',

    // Responsive layout (per device)
    'cols_mobile'  => 2,
    'rows_mobile'  => 3,
    'cols_tablet'  => 2,
    'rows_tablet'  => 3,
    'cols_desktop' => 3,
    'rows_desktop' => 2,

    // Responsive typography (px)
    'fs_heading_mobile'   => 28,
    'fs_heading_tablet'   => 36,
    'fs_heading_desktop'  => 42,

    'fs_subheading_mobile'  => 16,
    'fs_subheading_tablet'  => 18,
    'fs_subheading_desktop' => 20,

    'fs_paragraph_mobile'  => 14,
    'fs_paragraph_tablet'  => 16,
    'fs_paragraph_desktop' => 16,

    // Card size (px) - 0 means "auto"
    // Width = max-width per card, Height = min-height per card
    'card_w_mobile'  => 0,
    'card_w_tablet'  => 0,
    'card_w_desktop' => 0,

    'card_h_mobile'  => 0,
    'card_h_tablet'  => 0,
    'card_h_desktop' => 0,

    // Per-card headings/captions
    'label_brought'    => 'Cats Brought In',
    'caption_brought'  => '🏠 Found their way to us',

    'label_adopted'    => 'Cats Adopted',
    'caption_adopted'  => '💕 Forever homes found',

    'label_vaccinated'   => 'Cats Vaccinated',
    'caption_vaccinated' => '💉 Protected & healthy',

    'label_neutered'   => 'Cats Neutered',
    'caption_neutered' => '✂️ Spay & neuter care',

    'label_chipped'    => 'Cats Microchipped',
    'caption_chipped'  => '📍 Always traceable',

    'label_in_care'    => 'Cats In Our Care',
    'caption_in_care'  => '🐾 Currently safe with us',

    // Per-card icon slugs (selectable)
    'icon_brought'     => 'home',
    'icon_adopted'     => 'heart',
    'icon_vaccinated'  => 'syringe',
    'icon_neutered'    => 'scissors',
    'icon_chipped'     => 'tag',
    'icon_in_care'     => 'shield',

    // Order: one key per line
    // allowed keys: brought, adopted, vaccinated, neutered, chipped, in_care
    'card_order' => "brought\nadopted\nvaccinated\nneutered\nchipped\nin_care",
  ];
}

function straysafe_stats_ui_get_options() {
  $defaults = straysafe_stats_ui_default_options();
  $saved = get_option(STRAYSAFE_STATS_OPT, []);
  if (!is_array($saved)) $saved = [];
  return array_merge($defaults, $saved);
}

function straysafe_stats_ui_allowed_card_keys() {
  return ['brought','adopted','vaccinated','neutered','chipped','in_care'];
}

function straysafe_stats_ui_parse_order($raw) {
  $allowed = straysafe_stats_ui_allowed_card_keys();
  $lines = preg_split("/\r\n|\n|\r/", (string)$raw);
  $out = [];

  foreach ($lines as $line) {
    $k = trim(strtolower($line));
    if ($k === '') continue;
    if (in_array($k, $allowed, true) && !in_array($k, $out, true)) {
      $out[] = $k;
    }
  }
  foreach ($allowed as $k) {
    if (!in_array($k, $out, true)) $out[] = $k;
  }
  return $out;
}

/**
 * Icon library (SVG paths)
 * Stored as slug in settings.
 */
function straysafe_stats_ui_icon_catalog() {
  // slug => [Label, SVG paths (24x24 viewBox)]
  return [
    // Core / general
    'home' => ['Home', '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>' ],
    'chart' => ['Chart', '<path d="M3 3h2v18H3V3zm16 6h2v12h-2V9zM11 13h2v8h-2v-8zM7 11h2v10H7V11zM15 5h2v16h-2V5z"/>' ],
    'clipboard' => ['Clipboard', '<path d="M16 4h-1.18C14.4 2.84 13.3 2 12 2s-2.4.84-2.82 2H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-4-1c.55 0 1 .45 1 1h-2c0-.55.45-1 1-1zm4 19H8V6h8v16z"/>' ],
    'calendar' => ['Calendar', '<path d="M7 10h5v5H7v-5zm10-7h-1V1h-2v2H10V1H8v2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H7V8h10v13z"/>' ],
    'info' => ['Info', '<path d="M11 7h2V9h-2V7zm0 4h2v6h-2v-6zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>' ],
    'check' => ['Check', '<path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>' ],

    // Rescue / shelter / adoption
    'pawprints_a' => ['Paw Prints (🐾 Emoji A)', '
<g fill="currentColor">
  <g transform="translate(6.3 13.4) rotate(-18)">
    <ellipse cx="0" cy="2.1" rx="3.2" ry="2.6"></ellipse>
    <ellipse cx="-2.4" cy="-1.2" rx="1.05" ry="1.25"></ellipse>
    <ellipse cx="-0.9" cy="-2.0" rx="1.00" ry="1.20"></ellipse>
    <ellipse cx="0.9" cy="-2.0" rx="1.00" ry="1.20"></ellipse>
    <ellipse cx="2.4" cy="-1.2" rx="1.05" ry="1.25"></ellipse>
  </g>
  <g transform="translate(16.6 8.6) rotate(14) scale(0.92)">
    <ellipse cx="0" cy="2.0" rx="2.9" ry="2.3"></ellipse>
    <ellipse cx="-2.2" cy="-1.0" rx="0.95" ry="1.15"></ellipse>
    <ellipse cx="-0.8" cy="-1.7" rx="0.9" ry="1.05"></ellipse>
    <ellipse cx="0.8" cy="-1.7" rx="0.9" ry="1.05"></ellipse>
    <ellipse cx="2.2" cy="-1.0" rx="0.95" ry="1.15"></ellipse>
  </g>
</g>
' ],
    'pawprints_b' => ['Paw Prints (🐾 Emoji B)', '
<g fill="currentColor">
  <g transform="translate(6.5 13.7) rotate(-20)">
    <ellipse cx="0" cy="2.2" rx="3.45" ry="2.85"></ellipse>
    <ellipse cx="-2.55" cy="-1.1" rx="1.15" ry="1.35"></ellipse>
    <ellipse cx="-0.95" cy="-2.05" rx="1.10" ry="1.30"></ellipse>
    <ellipse cx="0.95" cy="-2.05" rx="1.10" ry="1.30"></ellipse>
    <ellipse cx="2.55" cy="-1.1" rx="1.15" ry="1.35"></ellipse>
  </g>
  <g transform="translate(16.7 8.5) rotate(16) scale(0.9)">
    <ellipse cx="0" cy="2.0" rx="3.1" ry="2.55"></ellipse>
    <ellipse cx="-2.35" cy="-1.0" rx="1.05" ry="1.25"></ellipse>
    <ellipse cx="-0.85" cy="-1.8" rx="1.00" ry="1.15"></ellipse>
    <ellipse cx="0.85" cy="-1.8" rx="1.00" ry="1.15"></ellipse>
    <ellipse cx="2.35" cy="-1.0" rx="1.05" ry="1.25"></ellipse>
  </g>
</g>
' ],
    'pawprints_c' => ['Paw Prints (🐾 Emoji C)', '
<g fill="currentColor">
  <g transform="translate(6.6 13.3) rotate(-16) scale(0.92)">
    <ellipse cx="0" cy="2.05" rx="3.0" ry="2.45"></ellipse>
    <ellipse cx="-2.25" cy="-1.1" rx="0.95" ry="1.15"></ellipse>
    <ellipse cx="-0.85" cy="-1.9" rx="0.9" ry="1.05"></ellipse>
    <ellipse cx="0.85" cy="-1.9" rx="0.9" ry="1.05"></ellipse>
    <ellipse cx="2.25" cy="-1.1" rx="0.95" ry="1.15"></ellipse>
  </g>
  <g transform="translate(16.8 8.6) rotate(18) scale(0.82)">
    <ellipse cx="0" cy="2.0" rx="2.75" ry="2.2"></ellipse>
    <ellipse cx="-2.05" cy="-0.95" rx="0.85" ry="1.05"></ellipse>
    <ellipse cx="-0.75" cy="-1.6" rx="0.8" ry="0.95"></ellipse>
    <ellipse cx="0.75" cy="-1.6" rx="0.8" ry="0.95"></ellipse>
    <ellipse cx="2.05" cy="-0.95" rx="0.85" ry="1.05"></ellipse>
  </g>
</g>
' ],
    'pawprints_d' => ['Paw Prints (🐾 Cute)', '
<g fill="currentColor">
  <g transform="translate(6.4 13.6) rotate(-22)">
    <ellipse cx="0" cy="2.15" rx="3.25" ry="2.7"></ellipse>
    <ellipse cx="-2.7" cy="-1.0" rx="1.05" ry="1.35"></ellipse>
    <ellipse cx="-0.95" cy="-2.2" rx="1.0" ry="1.3"></ellipse>
    <ellipse cx="0.95" cy="-2.2" rx="1.0" ry="1.3"></ellipse>
    <ellipse cx="2.7" cy="-1.0" rx="1.05" ry="1.35"></ellipse>
  </g>
  <g transform="translate(16.6 8.4) rotate(12) scale(0.9)">
    <ellipse cx="0" cy="2.0" rx="2.95" ry="2.35"></ellipse>
    <ellipse cx="-2.45" cy="-0.95" rx="0.95" ry="1.2"></ellipse>
    <ellipse cx="-0.9" cy="-1.95" rx="0.9" ry="1.15"></ellipse>
    <ellipse cx="0.9" cy="-1.95" rx="0.9" ry="1.15"></ellipse>
    <ellipse cx="2.45" cy="-0.95" rx="0.95" ry="1.2"></ellipse>
  </g>
</g>
' ],
    'pawprints_e' => ['Paw Prints (🐾 Bold)', '
<g fill="currentColor">
  <g transform="translate(6.5 13.8) rotate(-18)">
    <ellipse cx="0" cy="2.25" rx="3.7" ry="3.0"></ellipse>
    <ellipse cx="-2.85" cy="-1.0" rx="1.25" ry="1.5"></ellipse>
    <ellipse cx="-1.05" cy="-2.2" rx="1.2" ry="1.45"></ellipse>
    <ellipse cx="1.05" cy="-2.2" rx="1.2" ry="1.45"></ellipse>
    <ellipse cx="2.85" cy="-1.0" rx="1.25" ry="1.5"></ellipse>
  </g>
  <g transform="translate(16.6 8.5) rotate(16) scale(0.88)">
    <ellipse cx="0" cy="2.05" rx="3.35" ry="2.75"></ellipse>
    <ellipse cx="-2.6" cy="-0.95" rx="1.1" ry="1.35"></ellipse>
    <ellipse cx="-0.95" cy="-1.95" rx="1.05" ry="1.25"></ellipse>
    <ellipse cx="0.95" cy="-1.95" rx="1.05" ry="1.25"></ellipse>
    <ellipse cx="2.6" cy="-0.95" rx="1.1" ry="1.35"></ellipse>
  </g>
</g>
' ],

    'heart' => ['Heart', '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>' ],
    'handshake' => ['Handshake', '<path d="M16.5 10.5 14 13l-1.5-1.5 2.5-2.5 1.5 1.5zM2 9l5-5 4 4-5 5H2V9zm20 0v4h-4l-3 3-4-4 3-3 1 1-2 2 2 2 2-2-1-1 2-2 4 0z"/>' ],
    'house_heart' => ['House + Heart', '<path d="M12 3 2 12h3v8h6v-5h2v5h6v-8h3L12 3zm2.5 8.5c0 1.9-1.7 3.4-2.5 4.1-.8-.7-2.5-2.2-2.5-4.1 0-1.1.9-2 2-2 .7 0 1.3.3 1.7.8.4-.5 1-.8 1.7-.8 1.1 0 2 .9 2 2z"/>' ],
    'users' => ['People', '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>' ],

    // Medical / treatment
    'stethoscope' => ['Stethoscope', '<path d="M19 2h-1v7c0 3.31-2.69 6-6 6S6 12.31 6 9V2H5v7c0 3.87 3.13 7 7 7s7-3.13 7-7V2zM12 18c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>' ],
    'bandage' => ['Bandage', '<path d="M17.73 3.27a4.01 4.01 0 0 0-5.66 0l-.71.71-.71-.71a4.01 4.01 0 0 0-5.66 5.66l.71.71-.71.71a4.01 4.01 0 0 0 5.66 5.66l.71-.71.71.71a4.01 4.01 0 0 0 5.66-5.66l-.71-.71.71-.71a4.01 4.01 0 0 0 0-5.66zM8.34 5.39a2 2 0 0 1 2.83 0l.71.71-2.83 2.83-.71-.71a2 2 0 0 1 0-2.83zm8.49 12.02a2 2 0 0 1-2.83 0l-.71-.71 2.83-2.83.71.71a2 2 0 0 1 0 2.83zM14.12 12l-2.12 2.12L9.88 12 12 9.88 14.12 12z"/>' ],
    'pill' => ['Pill', '<path d="M8.46 20.54a6 6 0 0 1 0-8.49l3.59-3.59a6 6 0 0 1 8.49 8.49l-3.59 3.59a6 6 0 0 1-8.49 0zm9.9-9.9a4 4 0 0 0-5.66 0L9.11 14.23 13.77 18.9l3.59-3.59a4 4 0 0 0 0-5.66z"/>' ],
    'syringe' => ['Syringe', '<path d="M20.71 7.04a.996.996 0 0 0-1.41 0l-2.34 2.34-1.29-1.29 1.34-1.34a.996.996 0 1 0-1.41-1.41l-1.34 1.34-.7-.7a.996.996 0 1 0-1.41 1.41l.7.7-7.9 7.9c-.39.39-.39 1.02 0 1.41l.71.71-2.12 2.12a.996.996 0 1 0 1.41 1.41l2.12-2.12.71.71c.39.39 1.02.39 1.41 0l7.9-7.9.7.7a.996.996 0 1 0 1.41-1.41l-.7-.7 1.34-1.34a.996.996 0 0 0 0-1.41l-1.29-1.29 2.34-2.34c.39-.39.39-1.02 0-1.41z"/>' ],
    'heartbeat' => ['Heartbeat', '<path d="M3 13h3l2-6 4 12 2-6h7v-2h-6l-3 9-4-12-2 6H3v-1z"/>' ],

    // Neuter / microchip / ID
    'scissors' => ['Scissors', '<path d="M9.64 7.64a2.5 2.5 0 1 1-3.54 3.54 2.5 2.5 0 0 1 3.54-3.54zm8.26 8.72a2.5 2.5 0 1 1-3.54 3.54 2.5 2.5 0 0 1 3.54-3.54zM20 4.41 12.41 12 20 19.59 18.59 21 11 13.41 3.41 21 2 19.59 9.59 12 2 4.41 3.41 3 11 10.59 18.59 3 20 4.41z"/>' ],
    'tag' => ['Tag', '<path d="M20.59 13.41 11 3.83V3h-.83L2 10.17V11l9.59 9.59c.78.78 2.05.78 2.83 0l6.17-6.17c.78-.78.78-2.05 0-2.83zM7.5 8.5A1.5 1.5 0 1 1 9 7a1.5 1.5 0 0 1-1.5 1.5z"/>' ],
    'id_card' => ['ID Card', '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 6h16v2H4V6zm0 12V10h16v8H4zm3-6h6v2H7v-2zm0 3h8v2H7v-2z"/>' ],
    'qr' => ['Scan / QR', '<path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm10 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h4v2h-4v-2zm4-6h2v4h-2v-4zm-6 0h4v2h-4v-2z"/>' ],

    // Safety / in care
    'shield' => ['Shield', '<path d="M12 2 4 5v6c0 5 3.4 9.74 8 11 4.6-1.26 8-6 8-11V5l-8-3zm0 18c-3.31-1.12-6-4.87-6-9V6.3l6-2.25 6 2.25V11c0 4.13-2.69 7.88-6 9z"/>' ],
    'shield_check' => ['Shield + Check', '<path d="M12 2 4 5v6c0 5 3.4 9.74 8 11 4.6-1.26 8-6 8-11V5l-8-3zm-1 14-3-3 1.4-1.4L11 13.2l3.6-3.6L16 11l-5 5z"/>' ],
    'lock' => ['Lock', '<path d="M12 17a2 2 0 0 0 1-3.73V12a1 1 0 0 0-2 0v1.27A2 2 0 0 0 12 17zm6-7h-1V8a5 5 0 0 0-10 0v2H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zM9 8a3 3 0 0 1 6 0v2H9V8z"/>' ],

    // Location / field work
    'map_pin' => ['Map Pin', '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/>' ],
    'truck' => ['Transport', '<path d="M20 8h-3V4H3v13h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h1v-5l-4-4zM8 18a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm11 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm3-6h-5V9h2l3 3v0z"/>' ],

    // Supplies / care
    'bowl' => ['Food Bowl', '<path d="M4 14c0 3.31 3.58 6 8 6s8-2.69 8-6H4zm16-2c0-2.76-3.58-5-8-5s-8 2.24-8 5h16z"/>' ],
    'water' => ['Water Drop', '<path d="M12 2S6 9 6 13a6 6 0 0 0 12 0c0-4-6-11-6-11zm0 17a4 4 0 0 1-4-4c0-2.08 2.08-5.62 4-8 1.92 2.38 4 5.92 4 8a4 4 0 0 1-4 4z"/>' ],
    'clean' => ['Clean / Sparkle', '<path d="M7 5l1.5 3L12 9.5 8.5 11 7 14.5 5.5 11 2 9.5 5.5 8 7 5zm12 2 1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2zM15 13l1.5 3L20 17.5 16.5 19 15 22.5 13.5 19 10 17.5 13.5 16 15 13z"/>' ],

    // Donations / support (optional)
    'donation' => ['Donation', '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/><path d="M11 7h2v4h4v2h-4v4h-2v-4H7v-2h4V7z"/>' ],
  ];
}

function straysafe_stats_ui_sanitize_icon_slug($slug) {
  $slug = sanitize_text_field((string)$slug);
  $slug = strtolower(trim($slug));
  $catalog = straysafe_stats_ui_icon_catalog();
  return array_key_exists($slug, $catalog) ? $slug : 'home';
}

/**
 * Reset handler (GET-based, no nested forms)
 */
add_action('admin_post_' . STRAYSAFE_STATS_RESET_ACTION, function () {
  if (!current_user_can('manage_options')) {
    wp_die('Not allowed.');
  }

  check_admin_referer('straysafe_stats_ui_reset_field');

  $field = isset($_GET['field']) ? sanitize_text_field($_GET['field']) : '';
  $defaults = straysafe_stats_ui_default_options();

  if (!array_key_exists($field, $defaults)) {
    wp_safe_redirect(admin_url('options-general.php?page=straysafe-stats-ui'));
    exit;
  }

  $opts = get_option(STRAYSAFE_STATS_OPT, []);
  if (!is_array($opts)) $opts = [];

  $opts[$field] = $defaults[$field];
  update_option(STRAYSAFE_STATS_OPT, $opts);

  wp_safe_redirect(admin_url('options-general.php?page=straysafe-stats-ui'));
  exit;
});

/**
 * Reset link (button-looking)
 */
function straysafe_stats_ui_reset_button($field_key, $label = 'Reset') {
  $url = add_query_arg(
    [
      'action' => STRAYSAFE_STATS_RESET_ACTION,
      'field'  => $field_key,
      '_wpnonce' => wp_create_nonce('straysafe_stats_ui_reset_field'),
    ],
    admin_url('admin-post.php')
  );

  printf(
    ' <a href="%s" class="button button-secondary" style="vertical-align:middle;">%s</a>',
    esc_url($url),
    esc_html($label)
  );
}

/**
 * Admin Settings Page
 */
add_action('admin_menu', function () {
  add_options_page(
    'ASM Plugin Suite Statistics',
    'ASM Plugin Suite Statistics',
    'manage_options',
    'straysafe-stats-ui',
    'straysafe_stats_ui_render_settings_page'
  );
});

add_action('admin_init', function () {
  register_setting('straysafe_stats_ui_group', STRAYSAFE_STATS_OPT, [
    'sanitize_callback' => 'straysafe_stats_ui_sanitize_options',
    'default' => straysafe_stats_ui_default_options(),
  ]);

  add_settings_section('straysafe_stats_ui_section_design', 'Design', '__return_false', 'straysafe-stats-ui');
  add_settings_section('straysafe_stats_ui_section_text', 'Text', '__return_false', 'straysafe-stats-ui');
  add_settings_section('straysafe_stats_ui_section_responsive', 'Responsive (Device-specific)', '__return_false', 'straysafe-stats-ui');
  add_settings_section('straysafe_stats_ui_section_cards', 'Cards', '__return_false', 'straysafe-stats-ui');

  // Design
  add_settings_field('brand_color', 'Brand colour', 'straysafe_stats_ui_field_brand_color', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');
  add_settings_field('background_color', 'Background colour', 'straysafe_stats_ui_field_background_color', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');

  // NEW: Paw controls
  add_settings_field('paw_opacity', 'Paw print opacity (0–0.25)', 'straysafe_stats_ui_field_paw_opacity', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');
  add_settings_field('paw_count', 'Paw print count (0–80)', 'straysafe_stats_ui_field_paw_count', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');

  add_settings_field('layout_mode', 'Layout mode', 'straysafe_stats_ui_field_layout_mode', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');
  add_settings_field('font_family', 'Font family (optional)', 'straysafe_stats_ui_field_font_family', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');
  add_settings_field('card_radius', 'Card corner radius (px)', 'straysafe_stats_ui_field_card_radius', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');
  add_settings_field('card_padding', 'Card padding (px)', 'straysafe_stats_ui_field_card_padding', 'straysafe-stats-ui', 'straysafe_stats_ui_section_design');

  // Text
  add_settings_field('title_text', 'Title text', 'straysafe_stats_ui_field_title_text', 'straysafe-stats-ui', 'straysafe_stats_ui_section_text');
  add_settings_field('year_label_prefix', 'Year label prefix', 'straysafe_stats_ui_field_year_label_prefix', 'straysafe-stats-ui', 'straysafe_stats_ui_section_text');
  add_settings_field('min_year', 'Minimum year', 'straysafe_stats_ui_field_min_year', 'straysafe-stats-ui', 'straysafe_stats_ui_section_text');
  add_settings_field('footer_text', 'Footer text', 'straysafe_stats_ui_field_footer_text', 'straysafe-stats-ui', 'straysafe_stats_ui_section_text');

  // Responsive
  add_settings_field('responsive_grid', 'Columns & rows (mobile/tablet/PC)', 'straysafe_stats_ui_field_responsive_grid', 'straysafe-stats-ui', 'straysafe_stats_ui_section_responsive');
  add_settings_field('responsive_typography', 'Font sizes (heading/subheading/paragraph)', 'straysafe_stats_ui_field_responsive_typography', 'straysafe-stats-ui', 'straysafe_stats_ui_section_responsive');
  add_settings_field('responsive_card_size', 'Card width & height (px)', 'straysafe_stats_ui_field_responsive_card_size', 'straysafe-stats-ui', 'straysafe_stats_ui_section_responsive');

  // Cards
  add_settings_field('card_order', 'Card order', 'straysafe_stats_ui_field_card_order', 'straysafe-stats-ui', 'straysafe_stats_ui_section_cards');
  add_settings_field('labels_caps_icons', 'Card headings, captions & icons', 'straysafe_stats_ui_field_labels_caps_icons', 'straysafe-stats-ui', 'straysafe_stats_ui_section_cards');
});

function straysafe_stats_ui_sanitize_options($input) {
  $d = straysafe_stats_ui_default_options();
  $out = [];

  $out['brand_color']      = isset($input['brand_color']) ? sanitize_hex_color($input['brand_color']) : $d['brand_color'];
  $out['background_color'] = isset($input['background_color']) ? sanitize_hex_color($input['background_color']) : $d['background_color'];

  // NEW: Paw controls
  $po = isset($input['paw_opacity']) ? (float)$input['paw_opacity'] : (float)$d['paw_opacity'];
  if (!is_finite($po)) $po = (float)$d['paw_opacity'];
  $out['paw_opacity'] = max(0.0, min(0.25, $po));

  $pc = isset($input['paw_count']) ? intval($input['paw_count']) : intval($d['paw_count']);
  $out['paw_count'] = max(0, min(80, $pc));

  $layout = isset($input['layout_mode']) ? sanitize_text_field($input['layout_mode']) : $d['layout_mode'];
  $out['layout_mode'] = in_array($layout, ['grid','one_row'], true) ? $layout : $d['layout_mode'];

  $out['min_year'] = isset($input['min_year']) ? max(1900, min(3000, intval($input['min_year']))) : $d['min_year'];

  $out['title_text']        = isset($input['title_text']) ? sanitize_text_field($input['title_text']) : $d['title_text'];
  $out['year_label_prefix'] = isset($input['year_label_prefix']) ? sanitize_text_field($input['year_label_prefix']) : $d['year_label_prefix'];
  $out['footer_text']       = isset($input['footer_text']) ? sanitize_textarea_field($input['footer_text']) : $d['footer_text'];

  $font = isset($input['font_family']) ? sanitize_text_field($input['font_family']) : $d['font_family'];
  $font = trim($font);
  if ($font !== '') $font = preg_replace('/[^a-zA-Z0-9 ,\-]/', '', $font);
  $out['font_family'] = $font;

  $out['card_radius']  = isset($input['card_radius']) ? max(0, min(64, intval($input['card_radius']))) : $d['card_radius'];
  $out['card_padding'] = isset($input['card_padding']) ? max(0, min(64, intval($input['card_padding']))) : $d['card_padding'];

  // Columns / rows per device
  foreach ([
    'cols_mobile','rows_mobile',
    'cols_tablet','rows_tablet',
    'cols_desktop','rows_desktop',
  ] as $k) {
    $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
    $out[$k] = max(1, min(6, $v));
  }

  // Font sizes (px)
  foreach ([
    'fs_heading_mobile','fs_heading_tablet','fs_heading_desktop',
    'fs_subheading_mobile','fs_subheading_tablet','fs_subheading_desktop',
    'fs_paragraph_mobile','fs_paragraph_tablet','fs_paragraph_desktop',
  ] as $k) {
    $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
    $out[$k] = max(10, min(80, $v));
  }

  // Card size constraints (px) - 0 allowed
  foreach ([
    'card_w_mobile','card_w_tablet','card_w_desktop',
    'card_h_mobile','card_h_tablet','card_h_desktop',
  ] as $k) {
    $v = isset($input[$k]) ? intval($input[$k]) : $d[$k];
    $out[$k] = max(0, min(1200, $v));
  }

  $out['card_order'] = isset($input['card_order']) ? sanitize_textarea_field($input['card_order']) : $d['card_order'];

  foreach ([
    'label_brought','caption_brought','icon_brought',
    'label_adopted','caption_adopted','icon_adopted',
    'label_vaccinated','caption_vaccinated','icon_vaccinated',
    'label_neutered','caption_neutered','icon_neutered',
    'label_chipped','caption_chipped','icon_chipped',
    'label_in_care','caption_in_care','icon_in_care',
  ] as $k) {
    if (substr($k, 0, 5) === 'icon_') {
      $out[$k] = isset($input[$k]) ? straysafe_stats_ui_sanitize_icon_slug($input[$k]) : $d[$k];
    } else {
      $out[$k] = isset($input[$k]) ? sanitize_text_field($input[$k]) : $d[$k];
    }
  }

  return $out;
}

function straysafe_stats_ui_render_settings_page() { ?>
  <div class="wrap">
    <h1>ASM Plugin Suite Statistics</h1>
    <form method="post" action="options.php">
      <?php
        settings_fields('straysafe_stats_ui_group');
        do_settings_sections('straysafe-stats-ui');
        submit_button();
      ?>
    </form>
  </div>
<?php }

/** --- Field renderers (each includes a Reset link/button) --- */
function straysafe_stats_ui_field_brand_color() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="color" name="%s[brand_color]" value="%s" />', esc_attr(STRAYSAFE_STATS_OPT), esc_attr($o['brand_color']));
  straysafe_stats_ui_reset_button('brand_color');
}
function straysafe_stats_ui_field_background_color() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="color" name="%s[background_color]" value="%s" />', esc_attr(STRAYSAFE_STATS_OPT), esc_attr($o['background_color']));
  straysafe_stats_ui_reset_button('background_color');
}

// NEW: Paw fields
function straysafe_stats_ui_field_paw_opacity() {
  $o = straysafe_stats_ui_get_options();
  printf(
    '<input type="number" step="0.01" min="0" max="0.25" name="%s[paw_opacity]" value="%s" style="width:110px;" />',
    esc_attr(STRAYSAFE_STATS_OPT),
    esc_attr((string)$o['paw_opacity'])
  );
  straysafe_stats_ui_reset_button('paw_opacity');
  echo '<p class="description">Peak opacity at the middle of the animation.</p>';
}
function straysafe_stats_ui_field_paw_count() {
  $o = straysafe_stats_ui_get_options();
  printf(
    '<input type="number" min="0" max="80" name="%s[paw_count]" value="%d" style="width:110px;" />',
    esc_attr(STRAYSAFE_STATS_OPT),
    intval($o['paw_count'])
  );
  straysafe_stats_ui_reset_button('paw_count');
  echo '<p class="description">How many pawprints to render (max 80).</p>';
}

function straysafe_stats_ui_field_layout_mode() {
  $o = straysafe_stats_ui_get_options();
  $name = esc_attr(STRAYSAFE_STATS_OPT) . '[layout_mode]';
  ?>
  <select name="<?php echo esc_attr($name); ?>">
    <option value="grid" <?php selected($o['layout_mode'], 'grid'); ?>>Grid (current)</option>
    <option value="one_row" <?php selected($o['layout_mode'], 'one_row'); ?>>One row on tablet/PC (no scroll)</option>
  </select>
  <?php straysafe_stats_ui_reset_button('layout_mode'); ?>
  <p class="description">Layout mode is kept for backwards compatibility. Use the Responsive columns/rows settings below for precise control.</p>
  <?php
}
function straysafe_stats_ui_field_font_family() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="text" class="regular-text" name="%s[font_family]" value="%s" />', esc_attr(STRAYSAFE_STATS_OPT), esc_attr($o['font_family']));
  straysafe_stats_ui_reset_button('font_family');
  echo '<p class="description">Leave blank to inherit your theme font. Example: Inter, system-ui, Arial</p>';
}
function straysafe_stats_ui_field_card_radius() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="number" min="0" max="64" name="%s[card_radius]" value="%d" />', esc_attr(STRAYSAFE_STATS_OPT), intval($o['card_radius']));
  straysafe_stats_ui_reset_button('card_radius');
}
function straysafe_stats_ui_field_card_padding() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="number" min="0" max="64" name="%s[card_padding]" value="%d" />', esc_attr(STRAYSAFE_STATS_OPT), intval($o['card_padding']));
  straysafe_stats_ui_reset_button('card_padding');
}
function straysafe_stats_ui_field_title_text() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="text" class="regular-text" name="%s[title_text]" value="%s" />', esc_attr(STRAYSAFE_STATS_OPT), esc_attr($o['title_text']));
  straysafe_stats_ui_reset_button('title_text');
}
function straysafe_stats_ui_field_year_label_prefix() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="text" class="regular-text" name="%s[year_label_prefix]" value="%s" />', esc_attr(STRAYSAFE_STATS_OPT), esc_attr($o['year_label_prefix']));
  straysafe_stats_ui_reset_button('year_label_prefix');
}
function straysafe_stats_ui_field_min_year() {
  $o = straysafe_stats_ui_get_options();
  printf('<input type="number" min="1900" max="3000" name="%s[min_year]" value="%d" />', esc_attr(STRAYSAFE_STATS_OPT), intval($o['min_year']));
  straysafe_stats_ui_reset_button('min_year');
}
function straysafe_stats_ui_field_footer_text() {
  $o = straysafe_stats_ui_get_options();
  printf('<textarea name="%s[footer_text]" rows="3" class="large-text">%s</textarea>', esc_attr(STRAYSAFE_STATS_OPT), esc_textarea($o['footer_text']));
  straysafe_stats_ui_reset_button('footer_text');
  echo '<p class="description">Shown under the cards.</p>';
}

function straysafe_stats_ui_field_responsive_grid() {
  $o = straysafe_stats_ui_get_options();
  $k = esc_attr(STRAYSAFE_STATS_OPT);

  echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Device</th><th>Columns</th><th>Rows</th></tr></thead><tbody>';

  $rows = [
    ['Mobile', 'cols_mobile', 'rows_mobile'],
    ['Tablet', 'cols_tablet', 'rows_tablet'],
    ['PC',     'cols_desktop','rows_desktop'],
  ];

  foreach ($rows as [$label, $ck, $rk]) {
    echo '<tr>';
    echo '<td>' . esc_html($label) . '</td>';

    echo '<td>';
    printf('<input type="number" min="1" max="6" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($ck), intval($o[$ck]));
    straysafe_stats_ui_reset_button($ck);
    echo '</td>';

    echo '<td>';
    printf('<input type="number" min="1" max="6" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($rk), intval($o[$rk]));
    straysafe_stats_ui_reset_button($rk);
    echo '</td>';

    echo '</tr>';
  }

  echo '</tbody></table>';
  echo '<p class="description">Columns directly control layout. If there is a lone card on the last row in 2-column layout, it will span both columns (aligned to the outer edges above).</p>';
}

function straysafe_stats_ui_field_responsive_typography() {
  $o = straysafe_stats_ui_get_options();
  $k = esc_attr(STRAYSAFE_STATS_OPT);

  $items = [
    ['Heading',   'fs_heading_mobile',   'fs_heading_tablet',   'fs_heading_desktop'],
    ['Subheading','fs_subheading_mobile','fs_subheading_tablet','fs_subheading_desktop'],
    ['Paragraph', 'fs_paragraph_mobile', 'fs_paragraph_tablet', 'fs_paragraph_desktop'],
  ];

  echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Text</th><th>Mobile (px)</th><th>Tablet (px)</th><th>PC (px)</th></tr></thead><tbody>';

  foreach ($items as [$label,$m,$t,$d]) {
    echo '<tr>';
    echo '<td>' . esc_html($label) . '</td>';

    echo '<td>';
    printf('<input type="number" min="10" max="80" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($m), intval($o[$m]));
    straysafe_stats_ui_reset_button($m);
    echo '</td>';

    echo '<td>';
    printf('<input type="number" min="10" max="80" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($t), intval($o[$t]));
    straysafe_stats_ui_reset_button($t);
    echo '</td>';

    echo '<td>';
    printf('<input type="number" min="10" max="80" name="%s[%s]" value="%d" style="width:90px;" /> ', $k, esc_attr($d), intval($o[$d]));
    straysafe_stats_ui_reset_button($d);
    echo '</td>';

    echo '</tr>';
  }

  echo '</tbody></table>';
}

function straysafe_stats_ui_field_responsive_card_size() {
  $o = straysafe_stats_ui_get_options();
  $k = esc_attr(STRAYSAFE_STATS_OPT);

  echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>Device</th><th>Card max width (px)</th><th>Card min height (px)</th></tr></thead><tbody>';

  $rows = [
    ['Mobile', 'card_w_mobile',  'card_h_mobile'],
    ['Tablet', 'card_w_tablet',  'card_h_tablet'],
    ['PC',     'card_w_desktop', 'card_h_desktop'],
  ];

  foreach ($rows as [$label,$wk,$hk]) {
    echo '<tr>';
    echo '<td>' . esc_html($label) . '</td>';

    echo '<td>';
    printf('<input type="number" min="0" max="1200" name="%s[%s]" value="%d" style="width:110px;" /> ', $k, esc_attr($wk), intval($o[$wk]));
    straysafe_stats_ui_reset_button($wk);
    echo '<p class="description" style="margin:4px 0 0;">0 = auto</p>';
    echo '</td>';

    echo '<td>';
    printf('<input type="number" min="0" max="1200" name="%s[%s]" value="%d" style="width:110px;" /> ', $k, esc_attr($hk), intval($o[$hk]));
    straysafe_stats_ui_reset_button($hk);
    echo '<p class="description" style="margin:4px 0 0;">0 = auto</p>';
    echo '</td>';

    echo '</tr>';
  }

  echo '</tbody></table>';
}

function straysafe_stats_ui_field_card_order() {
  $o = straysafe_stats_ui_get_options();
  printf('<textarea name="%s[card_order]" rows="7" cols="40" class="large-text code">%s</textarea>', esc_attr(STRAYSAFE_STATS_OPT), esc_textarea($o['card_order']));
  straysafe_stats_ui_reset_button('card_order');
  echo '<p class="description">One key per line. Allowed keys: brought, adopted, vaccinated, neutered, chipped, in_care</p>';
}

function straysafe_stats_ui_field_labels_caps_icons() {
  $o = straysafe_stats_ui_get_options();
  $k = esc_attr(STRAYSAFE_STATS_OPT);
  $catalog = straysafe_stats_ui_icon_catalog();

  $rows = [
    ['brought',   'label_brought',   'caption_brought',   'icon_brought'],
    ['adopted',   'label_adopted',   'caption_adopted',   'icon_adopted'],
    ['vaccinated','label_vaccinated','caption_vaccinated','icon_vaccinated'],
    ['neutered',  'label_neutered',  'caption_neutered',  'icon_neutered'],
    ['chipped',   'label_chipped',   'caption_chipped',   'icon_chipped'],
    ['in_care',   'label_in_care',   'caption_in_care',   'icon_in_care'],
  ];

  echo '<table class="widefat striped" style="max-width:980px;">';
  echo '<thead><tr>
          <th style="width:120px;">Card</th>
          <th>Heading</th>
          <th>Caption</th>
          <th style="width:240px;">Icon</th>
        </tr></thead><tbody>';

  foreach ($rows as [$key, $labelKey, $capKey, $iconKey]) {
    echo '<tr>';
    echo '<td><code>' . esc_html($key) . '</code></td>';

    echo '<td>';
    printf('<input type="text" class="regular-text" name="%s[%s]" value="%s" />', $k, esc_attr($labelKey), esc_attr($o[$labelKey] ?? ''));
    straysafe_stats_ui_reset_button($labelKey);
    echo '</td>';

    echo '<td>';
    printf('<input type="text" class="regular-text" name="%s[%s]" value="%s" />', $k, esc_attr($capKey), esc_attr($o[$capKey] ?? ''));
    straysafe_stats_ui_reset_button($capKey);
    echo '</td>';

    echo '<td>';
    printf('<select name="%s[%s]">', $k, esc_attr($iconKey));
    foreach ($catalog as $slug => $meta) {
      $label = $meta[0];
      printf(
        '<option value="%s" %s>%s</option>',
        esc_attr($slug),
        selected(($o[$iconKey] ?? ''), $slug, false),
        esc_html($label)
      );
    }
    echo '</select>';
    straysafe_stats_ui_reset_button($iconKey);
    echo '</td>';

    echo '</tr>';
  }

  echo '</tbody></table>';
  echo '<p class="description">Icons are inline SVG and will keep your existing brand-colour behaviour.</p>';
}

/**
 * Shortcode UI
 */
add_shortcode('stats', function () {
  $opts = straysafe_stats_ui_get_options();

  // Tailwind CDN (convenient).
  wp_enqueue_script('straysafe-tailwind', 'https://cdn.tailwindcss.com', [], null, false);

  // Prevent Tailwind preflight from affecting the rest of the site
  wp_add_inline_script('straysafe-tailwind', 'tailwind.config = { corePlugins: { preflight: false } };', 'before');

  $font_css = empty($opts['font_family'])
    ? "font-family: inherit;"
    : "font-family: " . esc_html($opts['font_family']) . ", sans-serif;";

  $instance = wp_unique_id('straysafe_stats_');
  $order = straysafe_stats_ui_parse_order($opts['card_order']);
  $catalog = straysafe_stats_ui_icon_catalog();

  // Paw controls
  $paw_count = max(0, min(80, (int)($opts['paw_count'] ?? 0)));

  // We'll control columns via CSS vars (settings) + JS for centering/spans.
  $cardsWrapClass = 'ss-cards-grid';

  $label = function($k) use ($opts) { return $opts["label_{$k}"] ?? ''; };
  $caption = function($k) use ($opts) { return $opts["caption_{$k}"] ?? ''; };
  $iconSlug = function($k) use ($opts) {
    $key = "icon_{$k}";
    return isset($opts[$key]) ? straysafe_stats_ui_sanitize_icon_slug($opts[$key]) : 'home';
  };

  // CSS variables (use "none"/"auto" when 0 so we don't clamp to 0px)
  $cardWm = ((int)$opts['card_w_mobile']  > 0) ? ((int)$opts['card_w_mobile']  . 'px') : 'none';
  $cardWt = ((int)$opts['card_w_tablet']  > 0) ? ((int)$opts['card_w_tablet']  . 'px') : 'none';
  $cardWd = ((int)$opts['card_w_desktop'] > 0) ? ((int)$opts['card_w_desktop'] . 'px') : 'none';

  $cardHm = ((int)$opts['card_h_mobile']  > 0) ? ((int)$opts['card_h_mobile']  . 'px') : 'auto';
  $cardHt = ((int)$opts['card_h_tablet']  > 0) ? ((int)$opts['card_h_tablet']  . 'px') : 'auto';
  $cardHd = ((int)$opts['card_h_desktop'] > 0) ? ((int)$opts['card_h_desktop'] . 'px') : 'auto';

  $vars = [
    "--ss-cols-m: " . (int)$opts['cols_mobile'],
    "--ss-cols-t: " . (int)$opts['cols_tablet'],
    "--ss-cols-d: " . (int)$opts['cols_desktop'],

    "--ss-fs-h-m: " . (int)$opts['fs_heading_mobile'] . "px",
    "--ss-fs-h-t: " . (int)$opts['fs_heading_tablet'] . "px",
    "--ss-fs-h-d: " . (int)$opts['fs_heading_desktop'] . "px",

    "--ss-fs-s-m: " . (int)$opts['fs_subheading_mobile'] . "px",
    "--ss-fs-s-t: " . (int)$opts['fs_subheading_tablet'] . "px",
    "--ss-fs-s-d: " . (int)$opts['fs_subheading_desktop'] . "px",

    "--ss-fs-p-m: " . (int)$opts['fs_paragraph_mobile'] . "px",
    "--ss-fs-p-t: " . (int)$opts['fs_paragraph_tablet'] . "px",
    "--ss-fs-p-d: " . (int)$opts['fs_paragraph_desktop'] . "px",

    "--ss-card-w-m: " . $cardWm,
    "--ss-card-w-t: " . $cardWt,
    "--ss-card-w-d: " . $cardWd,

    "--ss-card-h-m: " . $cardHm,
    "--ss-card-h-t: " . $cardHt,
    "--ss-card-h-d: " . $cardHd,

    // NEW: paw + brand var used by the SVG fill
    "--ss-paw-opacity: " . esc_attr((string)($opts['paw_opacity'] ?? 0.08)),
    "--asm-brand: " . esc_attr($opts['brand_color']),
  ];
  $vars_css = implode('; ', $vars) . ';';

  ob_start();
  ?>
  <div class="straysafe-stats-ui-wrap" id="<?php echo esc_attr($instance); ?>" style="<?php echo esc_attr($font_css . ' ' . $vars_css); ?>">
    <style>
      /* Scoped only */
      #<?php echo esc_attr($instance); ?> { margin:0 !important; padding:0 !important; }
      .entry-content #<?php echo esc_attr($instance); ?> { margin-top:0 !important; }
      .entry-content #<?php echo esc_attr($instance); ?> > :first-child { margin-top:0 !important; }
      #<?php echo esc_attr($instance); ?>, #<?php echo esc_attr($instance); ?> * { box-sizing:border-box; }

      /* Prevent flash: hidden until JS marks ready */
      #<?php echo esc_attr($instance); ?> { opacity:0; visibility:hidden; }
      #<?php echo esc_attr($instance); ?>.ss-ready { opacity:1; visibility:visible; transition:opacity .18s ease-out; }

      @keyframes straysafe_countUp { from{opacity:0; transform:translateY(20px);} to{opacity:1; transform:translateY(0);} }
      @keyframes straysafe_float { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }

      /* Paw animation uses configurable opacity and keeps per-paw rotation (via CSS var) */
      @keyframes straysafe_pawPrint {
        0%   { opacity: 0; transform: rotate(var(--ss-paw-rot, 0deg)) scale(0.5); }
        50%  { opacity: var(--ss-paw-opacity, 0.08); transform: rotate(var(--ss-paw-rot, 0deg)) scale(1); }
        100% { opacity: 0; transform: rotate(var(--ss-paw-rot, 0deg)) scale(1.2); }
      }

      #<?php echo esc_attr($instance); ?> .counter-card { animation: straysafe_countUp 0.6s ease-out forwards; opacity: 0; }
      #<?php echo esc_attr($instance); ?> .counter-card:nth-child(1){animation-delay:.1s}
      #<?php echo esc_attr($instance); ?> .counter-card:nth-child(2){animation-delay:.2s}
      #<?php echo esc_attr($instance); ?> .counter-card:nth-child(3){animation-delay:.3s}
      #<?php echo esc_attr($instance); ?> .counter-card:nth-child(4){animation-delay:.4s}
      #<?php echo esc_attr($instance); ?> .counter-card:nth-child(5){animation-delay:.5s}
      #<?php echo esc_attr($instance); ?> .counter-card:nth-child(6){animation-delay:.6s}

      #<?php echo esc_attr($instance); ?> .icon-float { animation: straysafe_float 3s ease-in-out infinite; }
      #<?php echo esc_attr($instance); ?> .paw-bg { position:absolute; opacity:0; animation:straysafe_pawPrint 4s ease-in-out infinite; pointer-events:none; }

      @media (hover:none) and (pointer:coarse) {
        #<?php echo esc_attr($instance); ?> .counter-card:hover { transform:none !important; box-shadow:none !important; }
      }
      @media (max-width: 640px) { #<?php echo esc_attr($instance); ?> .paw-bg { display:none; } }

      /* Responsive grid (columns from settings) */
      #<?php echo esc_attr($instance); ?> .ss-cards-grid{
        display:grid;
        gap: 1rem;
        grid-template-columns: repeat(var(--ss-cols-m, 2), minmax(0, 1fr));
        justify-items: stretch;
        align-items: stretch;
      }
      @media (min-width: 640px){
        #<?php echo esc_attr($instance); ?> .ss-cards-grid{
          gap: 1.5rem;
          grid-template-columns: repeat(var(--ss-cols-t, 2), minmax(0, 1fr));
        }
      }
      @media (min-width: 1024px){
        #<?php echo esc_attr($instance); ?> .ss-cards-grid{
          grid-template-columns: repeat(var(--ss-cols-d, 3), minmax(0, 1fr));
        }
      }

      /* Settings-driven overrides */
      #<?php echo esc_attr($instance); ?> .counter-card {
        border-radius: <?php echo (int) $opts['card_radius']; ?>px !important;
        border-style: solid !important;
        border-width: <?php echo !empty($opts['card_border_enabled']) ? (int)$opts['card_border_weight'] : 0; ?>px !important;
        border-color: <?php echo esc_attr(!empty($opts['card_border_enabled']) ? ($opts['card_border_color'] ?: $opts['brand_color']) : 'transparent'); ?> !important;
        padding: <?php echo (int) $opts['card_padding']; ?>px !important;
      }

      /* Card sizing (device-specific) */
      #<?php echo esc_attr($instance); ?> .counter-card{
        width: 100%;
      }
      @media (max-width: 639px){
        #<?php echo esc_attr($instance); ?> .counter-card{
          max-width: var(--ss-card-w-m, none) !important;
          min-height: var(--ss-card-h-m, auto) !important;
        }
      }
      @media (min-width: 640px) and (max-width: 1023px){
        #<?php echo esc_attr($instance); ?> .counter-card{
          max-width: var(--ss-card-w-t, none) !important;
          min-height: var(--ss-card-h-t, auto) !important;
        }
      }
      @media (min-width: 1024px){
        #<?php echo esc_attr($instance); ?> .counter-card{
          max-width: var(--ss-card-w-d, none) !important;
          min-height: var(--ss-card-h-d, auto) !important;
        }
      }

      /* Typography (device-specific) */
      #<?php echo esc_attr($instance); ?> .ss-heading {
        font-size: var(--ss-fs-h-m, 28px) !important;
        font-weight: 800 !important;
        line-height: 1.15;
      }
      #<?php echo esc_attr($instance); ?> .ss-subheading {
        font-size: var(--ss-fs-s-m, 16px) !important;
      }
      #<?php echo esc_attr($instance); ?> .ss-paragraph {
        font-size: var(--ss-fs-p-m, 14px) !important;
      }
      @media (min-width: 640px){
        #<?php echo esc_attr($instance); ?> .ss-heading { font-size: var(--ss-fs-h-t, 36px) !important; }
        #<?php echo esc_attr($instance); ?> .ss-subheading { font-size: var(--ss-fs-s-t, 18px) !important; }
        #<?php echo esc_attr($instance); ?> .ss-paragraph { font-size: var(--ss-fs-p-t, 16px) !important; }
      }
      @media (min-width: 1024px){
        #<?php echo esc_attr($instance); ?> .ss-heading { font-size: var(--ss-fs-h-d, 42px) !important; }
        #<?php echo esc_attr($instance); ?> .ss-subheading { font-size: var(--ss-fs-s-d, 20px) !important; }
        #<?php echo esc_attr($instance); ?> .ss-paragraph { font-size: var(--ss-fs-p-d, 16px) !important; }
      }

      /* One-row mode: preserve prior behaviour if selected */
      <?php if (($opts['layout_mode'] ?? 'grid') === 'one_row'): ?>
      @media (min-width: 640px) {
        #<?php echo esc_attr($instance); ?> .counter-card { padding: 14px !important; }
        #<?php echo esc_attr($instance); ?> .icon-float { width: 44px !important; height: 44px !important; margin-bottom: 10px !important; }
        #<?php echo esc_attr($instance); ?> .icon-float svg { width: 22px !important; height: 22px !important; }
        #<?php echo esc_attr($instance); ?> .stat-number { font-size: 1.6rem !important; line-height: 1.9rem !important; }
        #<?php echo esc_attr($instance); ?> .stat-heading { font-size: 0.95rem !important; line-height: 1.15rem !important; }
        #<?php echo esc_attr($instance); ?> .stat-caption { font-size: 0.78rem !important; line-height: 1.05rem !important; }
      }
      @media (min-width: 768px) {
        #<?php echo esc_attr($instance); ?> .counter-card { padding: 16px !important; }
        #<?php echo esc_attr($instance); ?> .stat-number { font-size: 1.75rem !important; }
      }
      <?php endif; ?>
    </style>

    <div class="min-h-full w-full overflow-x-hidden overflow-y-auto relative rounded-2xl"
         style="background:<?php echo esc_attr($opts['background_color']); ?>;">

      <!-- Decorative paw prints (match Adoptables/Adopted) -->
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
          $top   = mt_rand(6, 90);
          $left  = mt_rand(4, 92);
          $delay = (mt_rand(0, 360) / 100) . 's';     // 0.00s–3.60s
          $size  = mt_rand(30, 44);                   // px
          $dur   = (mt_rand(360, 520) / 100) . 's';   // 3.60s–5.20s
          $rot   = mt_rand(-25, 25);

          echo '<div class="paw-bg" style="top:'.$top.'%; left:'.$left.'%; animation-delay:'.$delay.'; animation-duration:'.$dur.'; --ss-paw-rot: '.$rot.'deg;">'
              . $paw_svg($size)
              . '</div>';
        }
      ?>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 relative z-10">
        <div class="text-center mb-8 sm:mb-10">
          <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                        <h1 class="ss-heading leading-tight"
                id="<?php echo esc_attr($instance); ?>_main_title"
                style="color:<?php echo esc_attr($opts['brand_color']); ?>;">
              <?php echo esc_html($opts['title_text']); ?>
            </h1>
          </div>

          <p class="ss-subheading font-semibold"
             id="<?php echo esc_attr($instance); ?>_year_label"
             style="color:#64748b;">
            <?php echo esc_html($opts['year_label_prefix']); ?> —
          </p>

          <div class="mt-4 flex justify-center">
            <select
              id="<?php echo esc_attr($instance); ?>_year_select"
              class="w-full max-w-[240px] sm:max-w-[280px] px-4 py-2 rounded-xl border shadow-sm font-semibold bg-white"
              style="border-color:<?php echo esc_attr($opts['brand_color']); ?>; color:#334155;">
            </select>
          </div>

          <p id="<?php echo esc_attr($instance); ?>_status"
             class="mt-3 text-sm font-medium"
             style="color:#64748b; display:none;"></p>
        </div>

        <div class="<?php echo esc_attr($cardsWrapClass); ?>" data-ss-grid>
          <?php foreach ($order as $key) :
            $is_in_care = ($key === 'in_care');
            $value_id = $instance . '_count_' . $key;
            $extra_classes = $is_in_care ? ' hidden' : '';
            $slug = $iconSlug($key);
            $iconPaths = isset($catalog[$slug]) ? $catalog[$slug][1] : $catalog['home'][1];
          ?>
            <div
              class="counter-card bg-white shadow-md border-2 transition-all duration-300 hover:shadow-xl hover:scale-[1.02]<?php echo esc_attr($extra_classes); ?>"
              id="<?php echo esc_attr($instance . '_card_' . $key); ?>"
              style="border-color:<?php echo esc_attr(!empty($opts['card_border_enabled']) ? ($opts['card_border_color'] ?: $opts['brand_color']) : 'transparent'); ?>; border-width:<?php echo !empty($opts['card_border_enabled']) ? (int)$opts['card_border_weight'] : 0; ?>px;">

              <div class="flex flex-col items-center text-center">
                <div class="icon-float mb-3 sm:mb-4 w-14 h-14 sm:w-16 sm:h-16 rounded-full flex items-center justify-center"
                     style="background:<?php echo esc_attr($opts['brand_color']); ?>;">
                  <svg class="w-8 h-8 sm:w-9 sm:h-9 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <?php echo $iconPaths; ?>
                  </svg>
                </div>

                <span id="<?php echo esc_attr($value_id); ?>"
                      class="stat-number text-4xl sm:text-5xl font-extrabold mb-2"
                      style="color:<?php echo esc_attr($opts['brand_color']); ?>;">—</span>

                <span class="stat-heading text-base sm:text-lg font-semibold" style="color:#334155;">
                  <?php echo esc_html($label($key)); ?>
                </span>

                <span class="stat-caption text-sm mt-1" style="color:#64748b;">
                  <?php echo esc_html($caption($key)); ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="text-center mt-8 sm:mt-10">
          <p class="ss-paragraph font-medium px-2" style="color:#64748b;">
            <?php echo esc_html($opts['footer_text']); ?>
          </p>
        </div>
      </div>
    </div>

    <script>
      (function(){
        const root = document.getElementById(<?php echo wp_json_encode($instance); ?>);
        if (!root) return;

        const PROXY = {
          baseUrl: "/wp-json/straysafe/v1",
          reportTitle: "Summary By Year"
        };

        const MIN_YEAR = <?php echo (int) $opts['min_year']; ?>;
        const YEAR_LABEL_PREFIX = <?php echo wp_json_encode($opts['year_label_prefix']); ?>;

        const ids = {
          yearSelect: <?php echo wp_json_encode($instance . '_year_select'); ?>,
          yearLabel:  <?php echo wp_json_encode($instance . '_year_label'); ?>,
          status:     <?php echo wp_json_encode($instance . '_status'); ?>,
          inCareCard: <?php echo wp_json_encode($instance . '_card_in_care'); ?>,
          count: {
            brought:   <?php echo wp_json_encode($instance . '_count_brought'); ?>,
            adopted:   <?php echo wp_json_encode($instance . '_count_adopted'); ?>,
            vaccinated:<?php echo wp_json_encode($instance . '_count_vaccinated'); ?>,
            neutered:  <?php echo wp_json_encode($instance . '_count_neutered'); ?>,
            chipped:   <?php echo wp_json_encode($instance . '_count_chipped'); ?>,
            in_care:   <?php echo wp_json_encode($instance . '_count_in_care'); ?>,
          }
        };

        function byId(id){ return root.querySelector('#' + CSS.escape(id)); }

        function getGridEl(){
          return root.querySelector('[data-ss-grid]');
        }

        function getVisibleCards(){
          const grid = getGridEl();
          if (!grid) return [];
          return Array.from(grid.querySelectorAll('.counter-card')).filter(el => !el.classList.contains('hidden'));
        }

        function currentColumnCount(){
          const grid = getGridEl();
          if (!grid) return 0;
          const gtc = getComputedStyle(grid).gridTemplateColumns || "";
          const cols = gtc.split(' ').filter(Boolean).length;
          return cols || 0;
        }

        function resetGridOverrides(cards){
          for (const el of cards){
            el.style.gridColumn = "";
          }
        }

        function updateGridLayout(){
          const grid = getGridEl();
          if (!grid) return;

          // Clear previous inline override so we can return to the CSS-defined columns
          grid.style.gridTemplateColumns = "";

          const cards = getVisibleCards();
          resetGridOverrides(cards);

          const cols = currentColumnCount();
          if (!cols || cards.length === 0) return;

          // If fewer cards than configured columns, reduce columns so the row fills
          if (cards.length < cols) {
            const activeCols = cards.length;
            grid.style.gridTemplateColumns = `repeat(${activeCols}, minmax(0, 1fr))`;

            const remainder = cards.length % activeCols;

            // Special case: 2 columns and lone last card spans both columns
            if (activeCols === 2 && remainder === 1){
              cards[cards.length - 1].style.gridColumn = "1 / -1";
              return;
            }

            // 3-column centering behaviour
            if (activeCols === 3){
              if (remainder === 1){
                cards[cards.length - 1].style.gridColumn = "2 / span 1";
              } else if (remainder === 2){
                cards[cards.length - 2].style.gridColumn = "2 / span 1";
                cards[cards.length - 1].style.gridColumn = "3 / span 1";
              }
            }
          }
        }

        // Keep floating icons in sync even when cards appear/disappear
        let floatStart = performance.now();
        const FLOAT_PERIOD = 3; // must match CSS: straysafe_float 3s

        function syncIconFloat(){
          const grid = getGridEl();
          if (!grid) return;

          const icons = grid.querySelectorAll('.counter-card:not(.hidden) .icon-float');
          const elapsed = (performance.now() - floatStart) / 1000;
          const offset = -(elapsed % FLOAT_PERIOD);

          icons.forEach(icon => {
            icon.style.animationDelay = `${offset}s`;
          });
        }

        function showStatus(msg, isError = false) {
          const el = byId(ids.status);
          if (!el) return;
          el.style.display = msg ? "block" : "none";
          el.textContent = msg || "";
          el.style.color = isError ? "#b91c1c" : "#64748b";
        }

        function normalizeRow(r) {
          const yearRaw = r.year ?? r.YEAR ?? r.Year;
          const year = parseInt(yearRaw, 10);
          return {
            year: Number.isFinite(year) ? year : NaN,
            cats_brought_in: Number(r.cats_brought_in ?? r.CATS_BROUGHT_IN ?? 0),
            cats_adopted: Number(r.cats_adopted ?? r.CATS_ADOPTED ?? 0),
            cats_vaccinated: Number(r.cats_vaccinated ?? r.CATS_VACCINATED ?? 0),
            cats_neutered: Number(r.cats_neutered ?? r.CATS_NEUTERED ?? 0),
            cats_chipped: Number(r.cats_chipped ?? r.CATS_CHIPPED ?? 0),
          };
        }

        function buildProxyReportUrl() {
          return `${PROXY.baseUrl}/report?title=${encodeURIComponent(PROXY.reportTitle)}`;
        }

        async function fetchReportRowsViaProxy() {
          const url = buildProxyReportUrl();
          const res = await fetch(url, { method: "GET", credentials: "same-origin" });
          const text = await res.text();
          if (!res.ok) throw new Error(`Proxy request failed (${res.status}): ${text}`.trim());
          const data = JSON.parse(text);
          if (!Array.isArray(data)) throw new Error("Proxy did not return an array.");
          return data.map(normalizeRow).filter(r => Number.isInteger(r.year));
        }

        async function fetchInCareCount() {
          const url = `${PROXY.baseUrl}/in-care-count`;
          const res = await fetch(url, { method: "GET", credentials: "same-origin" });
          const text = await res.text();
          if (!res.ok) throw new Error(`In-care failed (${res.status}): ${text}`.trim());
          const data = JSON.parse(text);
          const n = Number(data?.count);
          return Number.isFinite(n) ? n : 0;
        }

        let rows = [];
        let renderToken = 0;

        async function populateYearDropdown() {
          const select = byId(ids.yearSelect);
          if (!select) return;

          const years = [...new Set(rows.map(r => r.year).filter(y => y >= MIN_YEAR))].sort((a,b)=>b-a);
          select.innerHTML = "";

          if (!years.length) {
            showStatus(`No years ≥ ${MIN_YEAR} found in report.`, true);
            return;
          }

          for (const y of years) {
            const opt = document.createElement("option");
            opt.value = String(y);
            opt.textContent = String(y);
            select.appendChild(opt);
          }

          select.addEventListener("change", () => renderYear(Number(select.value)));
          select.value = String(years[0]);
          await renderYear(years[0]);
        }

        function setText(id, v) {
          const el = byId(id);
          if (el) el.textContent = v;
        }

        async function renderYear(year) {
          const row = rows.find(r => r.year === year);
          if (!row) return;

          const labelEl = byId(ids.yearLabel);
          if (labelEl) labelEl.textContent = `${YEAR_LABEL_PREFIX} ${row.year}`;

          setText(ids.count.brought, row.cats_brought_in);
          setText(ids.count.adopted, row.cats_adopted);
          setText(ids.count.vaccinated, row.cats_vaccinated);
          setText(ids.count.neutered, row.cats_neutered);
          setText(ids.count.chipped, row.cats_chipped);

          const card = byId(ids.inCareCard);
          const valueEl = byId(ids.count.in_care);
          if (!card || !valueEl) return;

          const latestYearInReport = Math.max(...rows.map(r => r.year));

          if (year !== latestYearInReport) {
            card.classList.add('hidden');
            updateGridLayout();
            syncIconFloat();
            return;
          }

          card.classList.remove('hidden');
          updateGridLayout();
          syncIconFloat();

          const myToken = ++renderToken;
          valueEl.textContent = "…";

          try {
            const n = await fetchInCareCount();
            if (myToken !== renderToken) return;
            valueEl.textContent = n;
          } catch (e) {
            console.error(e);
            if (myToken !== renderToken) return;
            valueEl.textContent = "—";
          } finally {
            updateGridLayout();
            syncIconFloat();
          }
        }

        async function init() {
          showStatus("Loading stats…");
          try {
            rows = await fetchReportRowsViaProxy();
            showStatus("");

            window.addEventListener('resize', () => { updateGridLayout(); syncIconFloat(); }, { passive: true });

            await populateYearDropdown();
            updateGridLayout();
            syncIconFloat();

            // Reveal only after layout settles (prevents flash/jumps)
            requestAnimationFrame(() => {
              requestAnimationFrame(() => root.classList.add('ss-ready'));
            });
          } catch (err) {
            console.error(err);
            showStatus(`Could not load stats. ${err.message || ""}`.trim(), true);
            // Still reveal so user can see error message
            root.classList.add('ss-ready');
          }
        }

        if (document.readyState === "loading") {
          document.addEventListener("DOMContentLoaded", init);
        } else {
          init();
        }
      })();
    </script>
  </div>
  <?php
  return ob_get_clean();
});
