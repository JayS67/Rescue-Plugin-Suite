<?php
/** Validate a generated release ZIP without executing plugin code. Usage: php tests/package-bootstrap-smoke.php package.zip */
if ($argc !== 2 || !is_readable($argv[1])) { fwrite(STDERR, "Usage: php tests/package-bootstrap-smoke.php package.zip\n"); exit(2); }
if (!class_exists('ZipArchive')) { fwrite(STDERR, "ZipArchive is required.\n"); exit(2); }
$zip = new ZipArchive(); if ($zip->open($argv[1]) !== true) { fwrite(STDERR, "Cannot open release ZIP.\n"); exit(1); }
$root = sys_get_temp_dir() . '/rescue-plugin-suite-package-' . bin2hex(random_bytes(6)); mkdir($root, 0700, true);
if (!$zip->extractTo($root)) { fwrite(STDERR, "Cannot extract release ZIP.\n"); exit(1); } $zip->close();
register_shutdown_function(static function () use ($root) { exec('rm -rf ' . escapeshellarg($root)); });
$plugin = $root . '/rescue-plugin-suite'; $bootstrap = $plugin . '/plugin-ui-suite.php';
if (!is_file($bootstrap)) { throw new RuntimeException('Canonical bootstrap is missing.'); }
$files = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) if (strtolower($file->getExtension()) === 'php') $files[] = $file->getPathname();
$symbols = [];
foreach ($files as $file) {
  $tokens = token_get_all((string)file_get_contents($file)); $class_depth = 0; $brace_depth = 0; $pending_class = false;
  for ($i=0, $n=count($tokens); $i<$n; $i++) {
    $token=$tokens[$i];
    if ($token === '{') { $brace_depth++; if ($pending_class) { $class_depth=$brace_depth; $pending_class=false; } continue; }
    if ($token === '}') { if ($class_depth === $brace_depth) $class_depth=0; $brace_depth--; continue; }
    if (!is_array($token)) continue;
    $type=$token[0]; $class_reference=false;
    if ($type === T_CLASS) { for ($k=$i-1; $k>=0; $k--) { if (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) continue; $class_reference = is_array($tokens[$k]) && $tokens[$k][0] === T_DOUBLE_COLON; break; } }
    if (!$class_reference && in_array($type, [T_CLASS, T_INTERFACE, T_TRAIT], true)) { $pending_class=true; }
    if ($class_reference || ($class_depth && $brace_depth >= $class_depth) || !in_array($type, [T_CLASS, T_INTERFACE, T_TRAIT, T_FUNCTION, T_CONST], true)) continue;
    for ($j=$i+1; $j<$n; $j++) {
      if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
      if ($type === T_FUNCTION && ($tokens[$j] === '(' || $tokens[$j] === '&')) { if ($tokens[$j] === '&') continue; break; }
      if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name=$tokens[$j][1]; if (isset($symbols[$name])) throw new RuntimeException("Duplicate global symbol {$name}: {$symbols[$name]} and {$file}"); $symbols[$name]=$file; break; }
      if ($tokens[$j] === ';' || $tokens[$j] === '{') break;
    }
  }
}
$reachable = [$bootstrap]; $seen = [];
while ($reachable) {
  $file = array_pop($reachable); if (isset($seen[$file])) continue; $seen[$file]=true; $source=(string)file_get_contents($file);
  if (preg_match_all("/PLUGIN_SUITE_PATH\\s*\\.\\s*'([^']+)'/", $source, $matches)) foreach ($matches[1] as $relative) { $target=$plugin . '/' . $relative; if (!is_file($target)) throw new RuntimeException("Missing runtime dependency referenced by " . basename($file) . ": {$relative}"); $reachable[]=$target; }
}
echo 'Package smoke test passed: ' . count($files) . " PHP files, " . count($seen) . " reachable runtime files.\n";
