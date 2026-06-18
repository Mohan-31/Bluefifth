<?php
// Single Vercel entry point — routes all PHP requests to the correct file.
// Static files (CSS, images, JS) are served by Vercel's filesystem handler
// before this script ever runs.

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = rtrim($uri, '/') ?: '/';
$root = __DIR__;

// Clean-URL aliases → actual PHP files
$clean = [
    '/'         => '/index.php',
    '/checkout' => '/checkout.php',
    '/track'    => '/track.php',
    '/profile'  => '/profile.php',
    '/invoice'  => '/invoice.php',
];

if (isset($clean[$uri])) {
    require $root . $clean[$uri];
    return;
}

// Resolve the URI to a PHP file under the project root
$candidates = [
    $root . $uri,               // /shop/cart.php  → shop/cart.php
    $root . $uri . '.php',      // /shop/cart      → shop/cart.php
    $root . $uri . '/index.php',// /admin/         → admin/index.php
];

$realRoot = realpath($root) . DIRECTORY_SEPARATOR;

foreach ($candidates as $file) {
    if (!is_file($file)) continue;
    $real = realpath($file);
    if (!$real) continue;
    if (!str_starts_with($real, $realRoot)) continue;          // path traversal guard
    if (pathinfo($real, PATHINFO_EXTENSION) !== 'php') continue;
    if ($real === realpath(__FILE__)) continue;                 // don't serve the router itself
    require $real;
    return;
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
