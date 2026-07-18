<?php
$etag = md5_file(__DIR__ . '/widget.js');
header('Content-Type: application/javascript');
header('ETag: "' . $etag . '"');

$version = $_GET['v'] ?? '';
if ($version) {
    header('Cache-Control: public, max-age=31536000, immutable');
} else {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
    http_response_code(304);
    exit;
}

readfile(__DIR__ . '/widget.js');
