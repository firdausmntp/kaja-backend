<?php

/**
 * Fix Storage Symlink for Shared Hosting
 * Run this file once via browser: https://semenjana.biz.id/kaja/fix-storage-symlink.php
 */

// Path configurations
$publicPath = __DIR__ . '/public';
$storagePath = __DIR__ . '/storage/app/public';
$symlinkPath = $publicPath . '/storage';

echo "<h1>Storage Symlink Fixer</h1>";
echo "<p>Current directory: " . __DIR__ . "</p>";

// Check if storage directory exists
if (!is_dir($storagePath)) {
    echo "<p style='color: red;'>❌ Storage directory not found: $storagePath</p>";
    exit;
}

// Check if public directory exists
if (!is_dir($publicPath)) {
    echo "<p style='color: red;'>❌ Public directory not found: $publicPath</p>";
    exit;
}

// Remove existing symlink if it exists
if (is_link($symlinkPath)) {
    unlink($symlinkPath);
    echo "<p style='color: orange;'>🔄 Removed existing symlink</p>";
}

// Create symlink
if (symlink($storagePath, $symlinkPath)) {
    echo "<p style='color: green;'>✅ Symlink created successfully!</p>";
    echo "<p>From: $symlinkPath</p>";
    echo "<p>To: $storagePath</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create symlink</p>";
    echo "<p>This might be because your hosting doesn't support symlinks.</p>";
    echo "<p>Please use Solution 2 (Copy Method) instead.</p>";
}

// Test symlink
if (is_dir($symlinkPath)) {
    echo "<p style='color: green;'>✅ Symlink is working correctly!</p>";
} else {
    echo "<p style='color: red;'>❌ Symlink test failed</p>";
}

echo "<br><hr>";
echo "<h2>Test URLs:</h2>";
echo "<ul>";
echo "<li><a href='/storage/test.txt' target='_blank'>Test symlink access</a></li>";
echo "<li><a href='/storage/menus/' target='_blank'>Check menus folder</a></li>";
echo "</ul>";

echo "<br><p><strong>After running this, delete this file for security!</strong></p>";
