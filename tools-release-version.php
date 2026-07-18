<?php
$version = $argv[1] ?? '';
$normalized = preg_replace('/^v(?=\d)/i', '', trim($version));
if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $normalized)) { fwrite(STDERR, "Usage: php tools-release-version.php 15.0.0-beta\n"); exit(1); }
foreach (['plugin-ui-suite.php', 'readme.txt'] as $file) {
  if (!is_file($file)) continue;
  $body = file_get_contents($file);
  $body = preg_replace('/^([ \t*#@]*Version:\s*)\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?/mi', '${1}'.$normalized, $body);
  $body = preg_replace('/^([ \t*#@]*Stable tag:\s*)\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?/mi', '${1}'.$normalized, $body);
  file_put_contents($file, $body);
}
echo "Updated release version to {$normalized}\n";
