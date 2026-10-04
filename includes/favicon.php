<?php
// includes/favicon.php
// Centralized favicon generator with cache-busting and graceful fallbacks.
require_once __DIR__ . '/settings.php';

$docRoot = dirname(__DIR__);
$dbFavicon = getSiteSetting('branding.favicon_url', '');

// Strip domain if absolute url for local check
$localPath = $dbFavicon;
if (strpos($localPath, 'http') === 0) {
    $parsed = parse_url($localPath, PHP_URL_PATH);
    if ($parsed) $localPath = $parsed;
}

$useCustom = false;
if (!empty($dbFavicon) && !str_contains($dbFavicon, 'monogram.png') && $dbFavicon !== '/assets/logo/logo.png' && $dbFavicon !== '/assets/logo/favicon.png') {
    // Only use custom if it's external OR it actually exists on disk
    if (strpos($dbFavicon, 'http') === 0 || file_exists($docRoot . $localPath)) {
        $useCustom = true;
    }
}

if ($useCustom) {
    $v = (strpos($dbFavicon, 'http') === 0) ? '1' : filemtime($docRoot . $localPath);
    echo '<link rel="icon" href="' . htmlspecialchars($dbFavicon) . '?v=' . $v . '"/>' . "\n";
} else {
    $favPng = '/assets/logo/favicon.png';
    $favFallback = '/assets/logo/logo.png';
    $favIco = '/assets/logo/favicon.ico';
    $appleTouch = '/assets/logo/apple-touch-icon.png';
    
    $mainFav = file_exists($docRoot . $favPng) ? $favPng : $favFallback;
    $mainV = file_exists($docRoot . $mainFav) ? filemtime($docRoot . $mainFav) : '1';
    
    // Output main PNG in multiple standard sizes to ensure crisp rendering everywhere
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . htmlspecialchars($mainFav) . '?v=' . $mainV . '"/>' . "\n";
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . htmlspecialchars($mainFav) . '?v=' . $mainV . '"/>' . "\n";
    echo '<link rel="icon" type="image/png" sizes="512x512" href="' . htmlspecialchars($mainFav) . '?v=' . $mainV . '"/>' . "\n";
    
    if (file_exists($docRoot . $favIco)) {
        echo '<link rel="icon" sizes="any" href="' . htmlspecialchars($favIco) . '?v=' . filemtime($docRoot . $favIco) . '"/>' . "\n";
    }
    
    if (file_exists($docRoot . $appleTouch)) {
        echo '<link rel="apple-touch-icon" sizes="180x180" href="' . htmlspecialchars($appleTouch) . '?v=' . filemtime($docRoot . $appleTouch) . '"/>' . "\n";
    }
}
?>
