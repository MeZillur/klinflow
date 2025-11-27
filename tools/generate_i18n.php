<?php
// tools/generate_i18n.php
// Usage: php tools/generate_i18n.php
// Scans modules/DMS/Views for candidate strings and writes:
// - resources/lang/en.php
// - resources/lang/bn.php (copy of en as placeholder)
// - tools/i18n_replacements.json (map of file -> strings found)

$rootViews = __DIR__ . '/../modules/DMS/Views';
$projectRoot = realpath(__DIR__ . '/..');
if (!is_dir($rootViews)) {
    fwrite(STDERR, "Error: views folder not found: $rootViews\n");
    exit(1);
}

$strings = []; // key => true
$fileMap = []; // file => [strings...]

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootViews));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (!preg_match('/\.php$/', $f->getFilename())) continue;
    $path = $f->getRealPath();
    $txt = file_get_contents($path);

    // Capture >text< occurrences (non-greedy); skip very short/long ones
    if (preg_match_all('/>([^<>]{2,300})</u', $txt, $m)) {
        foreach ($m[1] as $s) {
            $s2 = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
            if ($s2 === '' || strlen($s2) < 2 || strlen($s2) > 200) continue;
            // skip strings that look like HTML attributes, links, numeric only
            if (preg_match('/^[\s\W0-9]+$/u', $s2)) continue;
            $strings[$s2] = true;
            $fileMap[$path][] = $s2;
        }
    }

    // Capture strings inside PHP single/double quotes in templates (simple)
    if (preg_match_all('/(["\'])([^\1]{2,200}?)\1/u', $txt, $m2)) {
        foreach ($m2[2] as $s) {
            $s2 = trim($s);
            if ($s2 === '' || strlen($s2) < 2 || strlen($s2) > 200) continue;
            // Skip obvious code-like strings (containing $ or -> or :: or /)
            if (preg_match('/[\$>\-:\/=]/', $s2)) continue;
            $strings[$s2] = true;
            $fileMap[$path][] = $s2;
        }
    }
}

// Deduplicate fileMap arrays
foreach ($fileMap as $k => $arr) $fileMap[$k] = array_values(array_unique($arr));
ksort($fileMap);

// Prepare resources/lang dir
$langDir = $projectRoot . '/resources/lang';
if (!is_dir($langDir)) @mkdir($langDir, 0755, true);

// Write en.php
$enFile = $langDir . '/en.php';
$enArr = [];
foreach ($strings as $k => $_) $enArr[$k] = $k;
$php = "<?php\nreturn " . var_export($enArr, true) . ";\n";
file_put_contents($enFile, $php);
echo "Wrote: $enFile (" . count($enArr) . " keys)\n";

// Write bn.php as placeholder (copy of en)
$bnFile = $langDir . '/bn.php';
$bnArr = $enArr; // copy, edit later for actual translations
file_put_contents($bnFile, "<?php\nreturn " . var_export($bnArr, true) . ";\n");
echo "Wrote: $bnFile (placeholders)\n";

// Write replacements plan (for review/apply)
$planFile = __DIR__ . '/i18n_replacements.json';
$plan = ['generated_at' => date('c'), 'files' => $fileMap];
file_put_contents($planFile, json_encode($plan, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Wrote plan: $planFile\n";
echo "Review resources/lang/en.php and resources/lang/bn.php, then run the apply script in dry-run mode:\n";
echo "  php tools/apply_i18n.php --dry-run\n";