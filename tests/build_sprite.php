<?php
/**
 * Build public/assets/icons/sprite.svg from the Lucide icon set (ISC licence).
 * Usage: php tests/build_sprite.php /path/to/lucide-static/icons
 * Scans app/ for icon('name') / nav icon names and bundles exactly those icons.
 */
$icons = rtrim($argv[1] ?? '', '/');
$root  = dirname(__DIR__);
if (!is_dir($icons)) { fwrite(STDERR, "icons dir not found\n"); exit(1); }
$names = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') { continue; }
    foreach (file($f->getPathname()) as $line) {
        // lines that pass an icon name: icon(...), ui_metric(...), icon maps and the nav config
        if (!preg_match('/icon\(|ui_metric\(|\$icons?\s*=|\$ic\s*=|\x27icon\x27|\x27\/[a-z\/-]*\x27\s*,\s*\x27[a-z0-9-]+\x27\s*\]|ui_empty\(/', $line)) { continue; }
        if (preg_match_all("/'([a-z][a-z0-9-]*)'/", $line, $m)) {
            foreach ($m[1] as $cand) { if (is_file("$icons/$cand.svg")) { $names[] = $cand; } }
        }
    }
}
$names = array_values(array_unique($names)); sort($names);
$out = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none">' . "\n";
$missing = [];
foreach ($names as $n) {
    $file = "$icons/$n.svg";
    if (!is_file($file)) { $missing[] = $n; continue; }
    $svg = file_get_contents($file);
    if (!preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $m)) { continue; }
    $out .= '<symbol id="' . $n . '" viewBox="0 0 24 24">' . trim(preg_replace('/\s+/', ' ', $m[1])) . "</symbol>\n";
}
$out .= "</svg>\n";
file_put_contents($root . '/public/assets/icons/sprite.svg', $out);
echo count($names) - count($missing) . " icons bundled\n";
if ($missing) { echo "MISSING: " . implode(', ', $missing) . "\n"; exit(2); }
