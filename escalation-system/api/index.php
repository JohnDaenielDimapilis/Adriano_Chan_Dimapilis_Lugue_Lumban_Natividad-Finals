<?php
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = trim($path, '/');

if ($path === '' || $path === 'api' || $path === 'api/index.php') {
    $target = $root . DIRECTORY_SEPARATOR . 'index.php';
} else {
    $cleanPath = str_replace(['..\\', '../'], '', $path);
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
}

if (is_dir($target)) {
    $target .= DIRECTORY_SEPARATOR . 'index.php';
}

$realTarget = realpath($target);
if (!$realTarget || !str_starts_with($realTarget, $root) || !is_file($realTarget) || pathinfo($realTarget, PATHINFO_EXTENSION) !== 'php') {
    http_response_code(404);
    exit('Page not found.');
}

require $realTarget;
