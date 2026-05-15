<?php
/**
 * fix_paths.php
 * Safe, idempotent replacement script to prefix root paths with /jobmington/
 * Usage: php tools/fix_paths.php
 */

$root = realpath(__DIR__ . '/..');
if (!$root) {
    echo "Unable to determine project root\n";
    exit(1);
}

$exts = ['php', 'html', 'htm', 'js', 'css', 'md', 'txt'];
$skipDirs = ['.git', 'node_modules', 'vendor', 'uploads', 'assets/images', 'assets/fonts'];
$patterns = [
    // href="/jobmington/ or href='/jobmington/  -> keep same quote
    '/(href)=("|\')\/(?!jobmington\/) /i', // placeholder with space to show intent (will not be used)
];

function shouldSkip($path, $skipDirs) {
    foreach ($skipDirs as $s) {
        if (strpos($path, DIRECTORY_SEPARATOR . $s . DIRECTORY_SEPARATOR) !== false || substr($path, -strlen(DIRECTORY_SEPARATOR . $s)) === DIRECTORY_SEPARATOR . $s) {
            return true;
        }
    }
    return false;
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$changed = [];
$totalFiles = 0;
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $filePath = $file->getRealPath();
    if (shouldSkip($filePath, $skipDirs)) continue;

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), $exts)) continue;

    $content = file_get_contents($filePath);
    if ($content === false) continue;

    $orig = $content;

    // href="/jobmington/ and href='/jobmington/ -> href="/jobmington/ or href='/jobmington/
    $content = preg_replace_callback('#\b(href)=(["\"])\/((?!jobmington\/))#i', function($m){
        return $m[1] . '=' . $m[2] . '/jobmington/';
    }, $content);

    // action="/jobmington/ and action='/jobmington/
    $content = preg_replace_callback('#\b(action)=(["\"])\/((?!jobmington\/))#i', function($m){
        return $m[1] . '=' . $m[2] . '/jobmington/';
    }, $content);

    // redirect('/jobmington/ or redirect("/jobmington/ -> redirect('/jobmington/ or redirect("/jobmington/
    $content = preg_replace_callback('#\b(redirect)\(\s*(["\"])\/((?!jobmington\/))#i', function($m){
        return $m[1] . '(' . $m[2] . '/jobmington/';
    }, $content);

    // Also handle href="/jobmington/something (without immediate slash end)
    $content = preg_replace_callback('#\b(href)=(["\"])\/((?!jobmington\/))#i', function($m){
        return $m[1] . '=' . $m[2] . '/jobmington/' . $m[3];
    }, $content);

    // action with path
    $content = preg_replace_callback('#\b(action)=(["\"])\/((?!jobmington\/))#i', function($m){
        return $m[1] . '=' . $m[2] . '/jobmington/' . $m[3];
    }, $content);

    // redirect with path
    $content = preg_replace_callback('#\b(redirect)\(\s*(["\"])\/((?!jobmington\/))#i', function($m){
        return $m[1] . '(' . $m[2] . '/jobmington/' . $m[3];
    }, $content);

    if ($content !== $orig) {
        // Backup original
        $bak = $filePath . '.bak-' . date('YmdHis');
        copy($filePath, $bak);
        file_put_contents($filePath, $content);
        $changed[] = $filePath;
    }
    $totalFiles++;
}

echo "Scanned files: $totalFiles\n";
echo "Modified files: " . count($changed) . "\n";
foreach ($changed as $c) {
    echo " - $c\n";
}

if (empty($changed)) {
    echo "No changes made (likely already prefixed).
";
} else {
    echo "Done. Please review the changed files and remove backups when satisfied.\n";
}
