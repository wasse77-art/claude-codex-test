<?php
// Image proxy — fetches an image server-side to avoid browser CORS restrictions.
// Only serves responses whose Content-Type starts with "image/".

$url = $_GET['url'] ?? '';

if (!$url || !preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400);
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$data     = curl_exec($ch);
$ct       = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$data || $httpCode >= 400 || !preg_match('/^image\//i', $ct)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $ct);
header('Cache-Control: public, max-age=3600');
echo $data;
