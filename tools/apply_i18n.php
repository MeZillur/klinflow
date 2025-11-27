<?php
/*
 * tools/apply_i18n.php
 *
 * Conservative, compatibility-friendly replacer for the i18n plan.
 * Usage:
 *  php tools/apply_i18n.php --dry-run   # show planned replacements (no writes)
 *  php tools/apply_i18n.php --apply     # apply changes (creates backups)
 *  php tools/apply_i18n.php --rollback  # restore most recent backup
 *
 * This version avoids modern PHP syntax that can cause parse errors on older PHP builds.
 */

$projectRoot = realpath(__DIR__ . '/..');
$planFile = __DIR__ . '/i18n_replacements.json';

if (!is_file($planFile)) {
    fwrite(STDERR, "Plan file not found: $planFile\nRun tools/generate_i18n.php first.\n");
    exit(1);
}

$planRaw = file_get_contents($planFile);
$plan = json_decode($planRaw, true);
if (!is_array($plan)) {
    fwrite(STDERR, "Failed to parse plan JSON: $planFile\n");
    exit(1);
}
$files = isset($plan['files']) ? $plan['files'] : array();

$argvStr = implode(' ', isset($argv) ? $argv : array());
$dry = in_array('--dry-run', isset($argv) ? $argv : array(), true);
$apply = in_array('--apply', isset($argv) ? $argv : array(), true);
$rollback = in_array('--rollback', isset($argv) ? $argv : array(), true);

$backupRoot = $projectRoot . '/backups/i18n-' . date('YmdHis');

if ($rollback) {
    $bdir = $projectRoot . '/backups';
    if (!is_dir($bdir)) { echo "No backups folder\n"; exit(0); }
    $subs = glob($bdir . '/i18n-*', GLOB_ONLYDIR);
    if (!$subs) { echo "No i18n backups found\n"; exit(0); }
    usort($subs, function($a, $b){ return strcmp($b, $a); });
    $latest = $subs[0];
    echo "Restoring from $latest\n";
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($latest));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = substr($f->getRealPath(), strlen($latest) + 1);
        $dest = $projectRoot . '/' . $rel;
        @mkdir(dirname($dest), 0755, true);
        copy($f->getRealPath(), $dest);
        echo "Restored: $rel\n";
    }
    echo "Restore complete.\n";
    exit(0);
}

if (!$dry && !$apply) {
    echo "Specify --dry-run or --apply or --rollback\n";
    exit(0);
}

if ($apply) {
    @mkdir($backupRoot, 0755, true);
    echo "Backups will be stored under: $backupRoot\n";
}

$report = array();

foreach ($files as $file => $strings) {
    if (!is_file($file)) continue;
    $orig = file_get_contents($file);
    $new = $orig;
    $changes = array();

    // iterate unique strings for this file
    $uniqueStrings = array_values(array_unique($strings));
    foreach ($uniqueStrings as $s) {
        $sTrim = trim($s);
        if ($sTrim === '') continue;

        // prepare the PHP replacement snippet safely (single-quoted key)
        $keyForPhp = str_replace("'", "\\'", $sTrim);
        $phpCall = "<?= \$h(__( '" . $keyForPhp . "' )) ?>";

        // Pattern 1: exact text between > and < (with optional surrounding whitespace)
        // Use preg_quote to escape regex meta-characters in $sTrim
        $p = '/>(\\s*)' . preg_quote($sTrim, '/') . '(\\s*)</u';
        if (preg_match($p, $new)) {
            $new = preg_replace($p, '>$1' . $phpCall . '$2<', $new, 1);
            $changes[] = $sTrim;
            continue;
        }

        // Pattern 2: a straightforward occurrence likely in plain HTML text
        // We add context checks to avoid replacing inside attributes or PHP code.
        // Look for occurrences preceded by '>' or whitespace and followed by '<' or whitespace.
        $contextPattern = '/([>\\s])' . preg_quote($sTrim, '/') . '([\\s<])/u';
        if (preg_match($contextPattern, $new)) {
            // Replace only the first safe occurrence
            $new = preg_replace($contextPattern, '$1' . $phpCall . '$2', $new, 1);
            $changes[] = $sTrim;
        }
    }

    if (!empty($changes)) {
        $report[$file] = $changes;
        if ($dry) {
            echo "DRY: would change " . basename($file) . " :\n";
            foreach ($changes as $c) echo "  - $c\n";
            echo "\n";
        } elseif ($apply) {
            // create backup using relative path under backupRoot
            $rel = ltrim(str_replace($projectRoot, '', $file), '/');
            $bakDest = $backupRoot . '/' . $rel;
            @mkdir(dirname($bakDest), 0755, true);
            copy($file, $bakDest);
            file_put_contents($file, $new);
            echo "UPDATED: $rel (" . count($changes) . " replacements). Backup at: $bakDest\n";
        }
    }
}

if ($dry) {
    echo "Dry-run complete. To apply run: php tools/apply_i18n.php --apply\n";
} else {
    echo "Apply complete. Backups saved under: $backupRoot\n";
}

exit(0);