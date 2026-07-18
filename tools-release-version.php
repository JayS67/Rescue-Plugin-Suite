<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }
$version = $argv[1] ?? '';
if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version)) { fwrite(STDERR, "Usage: php tools-release-version.php 14.0.34\n"); exit(1); }
$files = ['plugin-ui-suite.php','readme.txt','changelog.md'];
foreach ($files as $file) {
  if (!is_file($file)) continue;
  $body = file_get_contents($file);
  $body = preg_replace('/(Version:\s*)\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?/i', '$1'.$version, $body);
  $body = preg_replace('/(Stable tag:\s*)\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?/i', '$1'.$version, $body);
  $body = preg_replace("/(define\('PLUGIN_SUITE_VERSION',\s*')([^']+)('\);)/", '$1'.$version.'$3', $body);
  file_put_contents($file, $body);
}
echo "Updated release version to {$version}\n";
