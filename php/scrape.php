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

// Fetch page
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
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
    ],
]);
$html     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['success' => false, 'error' => '接続エラー: ' . $curlErr]);
    exit;
}
if ($httpCode >= 400) {
    echo json_encode(['success' => false, 'error' => "HTTPエラー ($httpCode)。このサイトはアクセスを拒否しています。"]);
    exit;
}
if (!$html) {
    echo json_encode(['success' => false, 'error' => 'ページの取得に失敗しました']);
    exit;
}

// Parse HTML
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', mb_detect_encoding($html, 'UTF-8,SJIS,EUC-JP', true)));
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Page title
$titleNodes = $xpath->query('//title');
$pageTitle  = ($titleNodes->length > 0)
    ? trim($titleNodes->item(0)->textContent)
    : $url;

// Image attributes to check (in priority order)
$srcAttrs = ['src', 'data-src', 'data-lazy-src', 'data-original', 'data-url', 'data-lazy'];

$images = [];
$seen   = [];

foreach ($xpath->query('//img') as $img) {
    $src = null;
    foreach ($srcAttrs as $attr) {
        $val = trim($img->getAttribute($attr));
        if ($val !== '' && strpos($val, 'data:') !== 0) {
            $src = $val;
            break;
        }
    }
    if (!$src) continue;

    $fullUrl = resolveUrl($url, $src);
    if (!$fullUrl || isset($seen[$fullUrl])) continue;
    $seen[$fullUrl] = true;

    $path     = parse_url($fullUrl, PHP_URL_PATH) ?? '';
    $filename = basename($path) ?: ('image_' . (count($images) + 1) . '.jpg');
    if (!pathinfo($filename, PATHINFO_EXTENSION)) {
        $filename .= '.jpg';
    }

    $images[] = [
        'url'      => $fullUrl,
        'alt'      => $img->getAttribute('alt'),
        'filename' => $filename,
        'source'   => $url,
    ];
}

echo json_encode([
    'success'    => true,
    'images'     => $images,
    'count'      => count($images),
    'page_title' => $pageTitle,
], JSON_UNESCAPED_UNICODE);

// ── Helpers ────────────────────────────────────────────────────────────────

function resolveUrl(string $base, string $rel): ?string
{
    if (preg_match('/^https?:\/\//i', $rel)) return $rel;
    if (strpos($rel, '//') === 0) {
        return (parse_url($base, PHP_URL_SCHEME) ?? 'https') . ':' . $rel;
    }
    if (strpos($rel, ':') !== false) return null; // non-http scheme

    $p      = parse_url($base);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $port   = isset($p['port']) ? ':' . $p['port'] : '';

    if (strpos($rel, '/') === 0) {
        return "$scheme://$host$port$rel";
    }

    // Relative path – resolve against base directory
    $dir  = isset($p['path']) ? rtrim(dirname($p['path']), '/') : '';
    $raw  = $dir . '/' . $rel;

    $segs = [];
    foreach (explode('/', $raw) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($segs); continue; }
        $segs[] = $seg;
    }

    return "$scheme://$host$port/" . implode('/', $segs);
}
