<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$publicPath = __DIR__;
$relativePath = ltrim(str_replace('\\', '/', $uri), '/');
$requestedFile = $publicPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

// Để PHP built-in server tự trả CSS, JavaScript, ảnh và các file public.
if (
    $uri !== '/'
    && !str_contains($relativePath, '..')
    && is_file($requestedFile)
) {
    return false;
}

require $publicPath.'/index.php';
