<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$url  = trim($data['url'] ?? '');

if (!$url) {
    echo json_encode(['success' => false, 'error' => 'URLが入力されていません']);
    exit;
}
if (!preg_match('/^https?:\/\//i', $url)) {
    $url = 'https://' . $url;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => ['Accept: text/html,*/*;q=0.8', 'Accept-Language: ja,en;q=0.5'],
]);
$html     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) { echo json_encode(['success' => false, 'error' => '接続エラー: ' . $curlErr]); exit; }
if ($httpCode >= 400) { echo json_encode(['success' => false, 'error' => "HTTPエラー ($httpCode)"]); exit; }
if (!$html)   { echo json_encode(['success' => false, 'error' => 'ページの取得に失敗しました']); exit; }

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', mb_detect_encoding($html, 'UTF-8,SJIS,EUC-JP', true)));
libxml_clear_errors();

$xpath = new DOMXPath($dom);

$titleNodes = $xpath->query('//title');
$pageTitle  = ($titleNodes->length > 0) ? trim($titleNodes->item(0)->textContent) : $url;

$videoExts  = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'avi', 'mkv', 'm4v', 'flv', 'wmv', 'mp4v', '3gp'];
$videoMimes = ['video/mp4','video/webm','video/ogg','video/ogv','video/quicktime',
               'video/x-msvideo','video/x-matroska','video/x-m4v','video/x-flv','video/x-ms-wmv'];

$videos = [];
$seen   = [];

function addVideoEntry(array &$videos, array &$seen, string $rawUrl, string $baseUrl, string $type = '', string $label = ''): void
{
    global $videoExts, $videoMimes;

    $fullUrl = resolveVideoUrl($baseUrl, $rawUrl);
    if (!$fullUrl || isset($seen[$fullUrl])) return;
    if (!preg_match('/^https?:\/\//i', $fullUrl)) return;

    $path    = parse_url($fullUrl, PHP_URL_PATH) ?? '';
    $ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $ctLower = strtolower(trim(explode(';', $type)[0]));

    if (!in_array($ext, $videoExts) && !in_array($ctLower, $videoMimes)) return;

    $seen[$fullUrl] = true;
    $filename = urldecode(basename($path)) ?: ('video_' . (count($videos) + 1) . '.' . ($ext ?: 'mp4'));

    $videos[] = [
        'url'      => $fullUrl,
        'filename' => $filename,
        'ext'      => $ext ? strtoupper($ext) : 'VIDEO',
        'label'    => $label ?: $filename,
        'source'   => $baseUrl,
    ];
}

// 1. <video> elements and their data attributes
foreach ($xpath->query('//video') as $el) {
    foreach (['src', 'data-src', 'data-video', 'data-video-src', 'data-url'] as $attr) {
        $val = trim($el->getAttribute($attr));
        if ($val) addVideoEntry($videos, $seen, $val, $url, '', $el->getAttribute('title'));
    }
}

// 2. <source> elements (inside <video> or standalone)
foreach ($xpath->query('//source') as $el) {
    $src  = trim($el->getAttribute('src'));
    $type = trim($el->getAttribute('type'));
    if ($src) addVideoEntry($videos, $seen, $src, $url, $type);
}

// 3. <a href="..."> linking to video files
foreach ($xpath->query('//a') as $el) {
    $href = trim($el->getAttribute('href'));
    if (!$href) continue;
    $ext = strtolower(pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, $videoExts)) {
        addVideoEntry($videos, $seen, $href, $url, '', trim($el->textContent));
    }
}

// 4. Regex scan of raw HTML (catches URLs in JS variables, JSON embeds, etc.)
$extPat = implode('|', $videoExts);
$urlPat = '/https?:\/\/[^\s"\'\]\[{}()<>\\\\]+\.(' . $extPat . ')(\?[^\s"\']*)?/i';
if (preg_match_all($urlPat, $html, $matches)) {
    foreach ($matches[0] as $matchUrl) {
        addVideoEntry($videos, $seen, $matchUrl, $url);
    }
}

echo json_encode([
    'success'    => true,
    'videos'     => $videos,
    'count'      => count($videos),
    'page_title' => $pageTitle,
], JSON_UNESCAPED_UNICODE);

function resolveVideoUrl(string $base, string $rel): ?string
{
    if (preg_match('/^https?:\/\//i', $rel)) return $rel;
    if (strpos($rel, '//') === 0) return (parse_url($base, PHP_URL_SCHEME) ?? 'https') . ':' . $rel;
    if (strpos($rel, ':') !== false) return null;
    $p      = parse_url($base);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $port   = isset($p['port']) ? ':' . $p['port'] : '';
    if (strpos($rel, '/') === 0) return "$scheme://$host$port$rel";
    $dir  = isset($p['path']) ? rtrim(dirname($p['path']), '/') : '';
    $segs = [];
    foreach (explode('/', $dir . '/' . $rel) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($segs); continue; }
        $segs[] = $seg;
    }
    return "$scheme://$host$port/" . implode('/', $segs);
}
