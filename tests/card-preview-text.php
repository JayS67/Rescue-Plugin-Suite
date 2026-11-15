<?php
/** Run in a disposable WordPress: wp eval-file tests/card-preview-text.php */
if (!defined('ABSPATH') || !function_exists('plugin_suite_card_excerpt')) throw new RuntimeException('Run with WordPress and the suite active.');
$cases = [
  ["First paragraph\r\nSecond line\r\n\r\n<p>Third &amp; final<br>Fourth line</p><script>alert(1)</script><style>body{display:none}</style>", "First paragraph\nSecond line\n\nThird & final\nFourth line"],
  ['<div>Intro</div><ul><li>One</li><li>Two</li></ul>', "Intro\nOne\nTwo"],
  ['<p>First &lt;img src=x onerror=alert(1)&gt;</p><p>Second<br>Third</p>', "First <img src=x onerror=alert(1)>\nSecond\nThird"],
  ["A short story.\n\nA new paragraph.", "A short story.\n\nA new paragraph."],
];
foreach ($cases as [$input, $expected]) if (plugin_suite_card_excerpt($input) !== $expected) throw new RuntimeException('Server-rendered preview formatting differs: ' . plugin_suite_card_excerpt($input));
if (plugin_suite_card_excerpt("One\nTwo\nThree Four Five", 4) !== "One\nTwo\nThree Four…") throw new RuntimeException('Word limit lost line breaks.');
$long = plugin_suite_card_excerpt(str_repeat('🐾', 400));
if ($long !== str_repeat('🐾', 360) . '…') throw new RuntimeException('Character limit split Unicode.');
$escaped = esc_html(plugin_suite_card_excerpt('&lt;img src=x onerror=alert(1)&gt;'));
if (strpos($escaped, '<img') !== false) throw new RuntimeException('Preview fallback rendered unsafe markup.');
echo "PASS server-rendered preview paragraphs, entities, safe output and bounded Unicode text.\n";
