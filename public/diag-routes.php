<?php
// TEMPORARY DIAGNOSTIC — delete immediately after reading the output.
// This does NOT use Laravel at all — it just checks, at the raw
// filesystem level, what routes/api.php (relative to THIS public/
// folder) actually contains right now.

$apiRoutesPath = __DIR__ . '/../routes/api.php';
$realPath = realpath($apiRoutesPath);

echo '<pre style="font-family: monospace; white-space: pre-wrap;">';
echo "PHP version: " . phpversion() . "\n";
echo "This script's directory: " . __DIR__ . "\n";
echo "Resolved path to routes/api.php: " . ($realPath ?: 'FILE DOES NOT EXIST AT THIS PATH') . "\n";

if ($realPath) {
    echo "File last modified: " . date('Y-m-d H:i:s', filemtime($realPath)) . "\n";
    echo "File size: " . filesize($realPath) . " bytes\n";
    echo "Contains 'ping-test-123': " . (str_contains(file_get_contents($realPath), 'ping-test-123') ? 'YES' : 'NO') . "\n";
    echo "Contains 'resolve': " . (str_contains(file_get_contents($realPath), "'resolve'") ? 'YES' : 'NO') . "\n";
    echo "\n--- Full contents below ---\n\n";
    echo htmlspecialchars(file_get_contents($realPath));
}

echo '</pre>';
