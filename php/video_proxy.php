<?php
// Stream a remote video file to the browser as a download.
// Uses curl WRITEFUNCTION to avoid loading the entire file into PHP memory.

while (ob_get_level()) ob_end_clean();

$url      = $_GET['url']      ?? '';
$filename = $_GET['filename'] ?? '';

if (!$url || !preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400);
    exit('Invalid URL');
}

if (!$filename) {
    $filename = urldecode(basename(parse_url($url, PHP_URL_PATH))) ?: 'video.mp4';
}

// HEAD request to resolve redirects and get metadata
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_NOBODY         => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
curl_exec($ch);
$ct      = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$size    = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
$code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
curl_close($ch);

if ($code >= 400) {
    http_response_code(404);
    exit('Video not found');
}

$mime = $ct ? trim(explode(';', $ct)[0]) : 'application/octet-stream';
if (strpos($mime, 'video/') !== 0 && !in_array($mime, ['application/octet-stream', 'binary/octet-stream'])) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
if ($size > 0) header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');

// Stream body in 128 KB chunks
$ch = curl_init($effUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 0,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_BUFFERSIZE     => 131072,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_WRITEFUNCTION  => static function ($ch, $data): int {
        echo $data;
        flush();
        return strlen($data);
    },
]);
curl_exec($ch);
curl_close($ch);
