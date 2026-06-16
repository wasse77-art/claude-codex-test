<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$url  = trim($data['url'] ?? '');

if (!$url) { echo json_encode(['success' => false, 'error' => 'URLが入力されていません']); exit; }
if (!preg_match('/^https?:\/\//i', $url)) { $url = 'https://' . $url; }

// ── Fetch page ──────────────────────────────────────────────────────────────
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
$html    = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr)       { echo json_encode(['success' => false, 'error' => '接続エラー: ' . $curlErr]); exit; }
if ($httpCode >= 400){ echo json_encode(['success' => false, 'error' => "HTTPエラー ($httpCode)"]); exit; }
if (!$html)         { echo json_encode(['success' => false, 'error' => 'ページの取得に失敗しました']); exit; }

// ── Parse DOM ───────────────────────────────────────────────────────────────
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', mb_detect_encoding($html, 'UTF-8,SJIS,EUC-JP', true)));
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$titleNodes = $xpath->query('//title');
$pageTitle  = ($titleNodes->length > 0) ? trim($titleNodes->item(0)->textContent) : $url;

$findings = [];

// ── 1. Direct HTML5 video files ─────────────────────────────────────────────
$videoExts  = ['mp4','webm','ogg','ogv','mov','avi','mkv','m4v','flv','wmv','3gp'];
$videoMimes = ['video/mp4','video/webm','video/ogg','video/quicktime','video/x-matroska','video/x-m4v'];
$directUrls = []; $directSeen = []; $directFormats = [];

$addDirect = function(string $src, string $type = '') use ($url, $videoExts, $videoMimes, &$directUrls, &$directSeen, &$directFormats) {
    if (!$src || strpos($src, 'data:') === 0) return;
    $full = auResolve($url, $src);
    if (!$full || isset($directSeen[$full])) return;
    $ext = strtolower(pathinfo(parse_url($full, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $ct  = strtolower(trim(explode(';', $type)[0]));
    if (!in_array($ext, $videoExts) && !in_array($ct, $videoMimes)) return;
    $directSeen[$full] = true;
    $directUrls[] = $full;
    if ($ext) $directFormats[] = strtoupper($ext);
};
foreach ($xpath->query('//video') as $el) {
    foreach (['src','data-src','data-video-src'] as $a) { $v = trim($el->getAttribute($a)); if ($v) $addDirect($v); }
}
foreach ($xpath->query('//source') as $el) { $addDirect(trim($el->getAttribute('src')), trim($el->getAttribute('type'))); }
foreach ($xpath->query('//a') as $el) {
    $h = trim($el->getAttribute('href'));
    if ($h && in_array(strtolower(pathinfo(parse_url($h, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)), $videoExts)) $addDirect($h);
}
if ($directUrls) {
    $findings[] = [
        'type'  => 'direct', 'icon' => '🎬',
        'label' => '直接埋め込み動画 (HTML5 Video)',
        'detail'=> implode(' / ', array_unique($directFormats) ?: ['VIDEO']) . ' 形式',
        'count' => count($directUrls), 'sample_urls' => array_slice($directUrls, 0, 3),
        'status'=> 'green', 'dl_icon' => '✅',
        'dl_note'=> '動画スクレイパータブから直接ダウンロードできます',
    ];
}

// ── 2. HLS (.m3u8) ──────────────────────────────────────────────────────────
$hlsUrls = []; $hlsSeen = [];
$addHls = function(string $raw) use ($url, &$hlsUrls, &$hlsSeen) {
    $full = preg_match('/^https?:\/\//i', $raw) ? $raw : auResolve($url, $raw);
    if ($full && !isset($hlsSeen[$full])) { $hlsSeen[$full] = true; $hlsUrls[] = $full; }
};
foreach ($xpath->query('//source') as $el) {
    $src = trim($el->getAttribute('src')); $t = trim($el->getAttribute('type'));
    if ($src && (stripos($t, 'mpegurl') !== false || stripos($src, '.m3u8') !== false)) $addHls($src);
}
if (preg_match_all('/https?:\/\/[^\s"\'<>]+\.m3u8(\?[^\s"\'<>]*)?/i', $html, $m)) foreach ($m[0] as $u) $addHls($u);
if (preg_match_all('/["\']([^"\']*\.m3u8(\?[^"\']*)?)["\']/', $html, $m)) {
    foreach ($m[1] as $u) { $r = auResolve($url, $u); if ($r) $addHls($r); }
}
if ($hlsUrls) {
    $enc = hlsEncrypted($hlsUrls[0]);
    $findings[] = [
        'type'  => 'hls', 'icon' => '📡',
        'label' => 'HLS ストリーミング',
        'detail'=> '.m3u8 形式（セグメント分割配信）' . ($enc ? ' ／ 🔐 暗号化あり (AES-128)' : ' ／ 🔓 暗号化なし'),
        'count' => count($hlsUrls), 'sample_urls' => array_slice($hlsUrls, 0, 3),
        'status'=> $enc ? 'red' : 'yellow',
        'dl_icon' => $enc ? '❌' : '⚠️',
        'dl_note' => $enc
            ? '暗号化あり → 通常ダウンロード不可（一部ツールで復号可能な場合あり）'
            : '暗号化なし → ffmpeg / VLC / yt-dlp でダウンロード可能',
        'extra' => ['encrypted' => $enc],
    ];
}

// ── 3. DASH (.mpd) ──────────────────────────────────────────────────────────
$dashUrls = []; $dashSeen = [];
$addDash = function(string $u) use (&$dashUrls, &$dashSeen) {
    if (!isset($dashSeen[$u])) { $dashSeen[$u] = true; $dashUrls[] = $u; }
};
if (preg_match_all('/https?:\/\/[^\s"\'<>]+\.mpd(\?[^\s"\'<>]*)?/i', $html, $m)) foreach ($m[0] as $u) $addDash($u);
if (preg_match_all('/["\']([^"\']*\.mpd(\?[^"\']*)?)["\']/', $html, $m)) {
    foreach ($m[1] as $u) { $r = auResolve($url, $u); if ($r) $addDash($r); }
}
foreach ($xpath->query('//source') as $el) {
    $t = trim($el->getAttribute('type')); $s = trim($el->getAttribute('src'));
    if ($s && stripos($t, 'dash+xml') !== false) { $r = auResolve($url, $s); if ($r) $addDash($r); }
}
if ($dashUrls) {
    $findings[] = [
        'type'  => 'dash', 'icon' => '📶',
        'label' => 'DASH ストリーミング',
        'detail'=> '.mpd 形式（Dynamic Adaptive Streaming over HTTP）',
        'count' => count($dashUrls), 'sample_urls' => array_slice($dashUrls, 0, 3),
        'status'=> 'yellow', 'dl_icon' => '⚠️',
        'dl_note'=> 'yt-dlp または ffmpeg でダウンロード可能（DRM の有無による）',
    ];
}

// ── 4. YouTube ───────────────────────────────────────────────────────────────
$ytIds = []; $ytSeen = [];
$addYt = function(string $id) use (&$ytIds, &$ytSeen) {
    if ($id && !isset($ytSeen[$id])) { $ytSeen[$id] = true; $ytIds[] = $id; }
};
foreach ($xpath->query('//iframe') as $el) {
    $s = trim($el->getAttribute('src'));
    if (preg_match('/youtube(?:-nocookie)?\.com\/embed\/([a-zA-Z0-9_-]{11})/i', $s, $m)) $addYt($m[1]);
}
if (preg_match_all('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/|live\/))([a-zA-Z0-9_-]{11})/i', $html, $m))
    foreach ($m[1] as $id) $addYt($id);
if ($ytIds) {
    $findings[] = [
        'type'  => 'youtube', 'icon' => '▶️',
        'label' => 'YouTube 動画',
        'detail'=> 'YouTube 埋め込み・リンク',
        'count' => count($ytIds),
        'sample_urls' => array_map(fn($id) => "https://www.youtube.com/watch?v={$id}", array_slice($ytIds, 0, 3)),
        'status'=> 'red', 'dl_icon' => '⚠️',
        'dl_note'=> 'yt-dlp コマンドでダウンロード可能（YouTube 利用規約に注意）',
    ];
}

// ── 5. Vimeo ─────────────────────────────────────────────────────────────────
$vmIds = []; $vmSeen = [];
$addVm = function(string $id) use (&$vmIds, &$vmSeen) {
    if ($id && !isset($vmSeen[$id])) { $vmSeen[$id] = true; $vmIds[] = $id; }
};
foreach ($xpath->query('//iframe') as $el) {
    $s = trim($el->getAttribute('src'));
    if (preg_match('/player\.vimeo\.com\/video\/(\d+)/i', $s, $m)) $addVm($m[1]);
}
if (preg_match_all('/vimeo\.com\/(?:video\/)?(\d{6,12})/i', $html, $m)) foreach ($m[1] as $id) $addVm($id);
if ($vmIds) {
    $findings[] = [
        'type'  => 'vimeo', 'icon' => '🎞️',
        'label' => 'Vimeo 動画',
        'detail'=> 'Vimeo 埋め込み・リンク',
        'count' => count($vmIds),
        'sample_urls' => array_map(fn($id) => "https://vimeo.com/{$id}", array_slice($vmIds, 0, 3)),
        'status'=> 'red', 'dl_icon' => '⚠️',
        'dl_note'=> 'yt-dlp でダウンロード可能（プライベート設定・DRM による）',
    ];
}

// ── 6. Dailymotion ───────────────────────────────────────────────────────────
if (preg_match_all('/dailymotion\.com\/(?:embed\/video\/|video\/)([a-zA-Z0-9]+)/i', $html, $m)) {
    $ids = array_unique($m[1]);
    $findings[] = [
        'type'  => 'dailymotion', 'icon' => '🎥',
        'label' => 'Dailymotion 動画',
        'detail'=> 'Dailymotion 埋め込み',
        'count' => count($ids),
        'sample_urls' => array_map(fn($id) => "https://www.dailymotion.com/video/{$id}", array_slice($ids, 0, 3)),
        'status'=> 'red', 'dl_icon' => '⚠️', 'dl_note' => 'yt-dlp でダウンロード可能',
    ];
}

// ── 7. Twitch ────────────────────────────────────────────────────────────────
if (preg_match('/player\.twitch\.tv|twitch\.tv\/(?:videos|clip)/i', $html)) {
    $findings[] = [
        'type'  => 'twitch', 'icon' => '🟣',
        'label' => 'Twitch 動画',
        'detail'=> 'Twitch プレーヤー（VOD / クリップ）',
        'count' => 1, 'sample_urls' => [],
        'status'=> 'red', 'dl_icon' => '⚠️', 'dl_note' => 'yt-dlp でダウンロード可能（VOD・クリップのみ）',
    ];
}

// ── 8. NicoNico ──────────────────────────────────────────────────────────────
if (preg_match('/nicovideo\.jp|nico\.ms/i', $html)) {
    $ids = [];
    if (preg_match_all('/nicovideo\.jp\/watch\/((?:sm|nm|so|ax|ca|yo|nl)?\d+)/i', $html, $m))
        $ids = array_unique($m[1]);
    $findings[] = [
        'type'  => 'nicovideo', 'icon' => '⚪',
        'label' => 'ニコニコ動画',
        'detail'=> 'nicovideo.jp',
        'count' => max(count($ids), 1),
        'sample_urls' => array_map(fn($id) => "https://www.nicovideo.jp/watch/{$id}", array_slice($ids, 0, 3)),
        'status'=> 'red', 'dl_icon' => '⚠️',
        'dl_note' => 'yt-dlp でダウンロード可能（会員限定動画はログイン情報が必要）',
    ];
}

// ── 9. Bilibili ───────────────────────────────────────────────────────────────
if (preg_match('/bilibili\.com/i', $html)) {
    $findings[] = [
        'type'  => 'bilibili', 'icon' => '🔵',
        'label' => 'Bilibili 動画',
        'detail'=> 'bilibili.com',
        'count' => 1, 'sample_urls' => [],
        'status'=> 'red', 'dl_icon' => '⚠️', 'dl_note' => 'yt-dlp でダウンロード可能',
    ];
}

// ── 10. Twitter / X ──────────────────────────────────────────────────────────
if (preg_match('/(?:twitter|x)\.com\/[^"\'\/]+\/status\/\d+/i', $html)) {
    $findings[] = [
        'type'  => 'twitter', 'icon' => '🐦',
        'label' => 'Twitter / X 動画',
        'detail'=> 'Twitter/X 埋め込みポスト',
        'count' => 1, 'sample_urls' => [],
        'status'=> 'red', 'dl_icon' => '⚠️', 'dl_note' => 'yt-dlp でダウンロード可能',
    ];
}

// ── 11. Video player libraries ────────────────────────────────────────────────
$libs = [];
if (preg_match('/jwplayer\s*\(/i', $html) || preg_match('/jwplayer\.com/i', $html)) $libs[] = 'JW Player';
if (preg_match('/videojs\s*[(\[]/i', $html) || preg_match('/video\.js/i', $html))    $libs[] = 'Video.js';
if (preg_match('/brightcove/i', $html))  $libs[] = 'Brightcove';
if (preg_match('/wistia/i', $html))      $libs[] = 'Wistia';
if (preg_match('/kaltura/i', $html))     $libs[] = 'Kaltura';
if (preg_match('/\bplyr\b/i', $html))    $libs[] = 'Plyr';
if (preg_match('/flowplayer/i', $html))  $libs[] = 'Flowplayer';
if ($libs) {
    $findings[] = [
        'type'  => 'player_lib', 'icon' => '🎛️',
        'label' => '動画プレーヤーライブラリ検出',
        'detail'=> implode(', ', $libs),
        'count' => count($libs), 'sample_urls' => [],
        'status'=> 'yellow', 'dl_icon' => 'ℹ️',
        'dl_note'=> 'プレーヤーが HLS / DASH / MP4 を読み込みます。ブラウザの開発者ツール → Network タブで動画URLを確認してください。',
    ];
}

// ── Result ────────────────────────────────────────────────────────────────────
echo json_encode([
    'success'    => true,
    'url'        => $url,
    'page_title' => $pageTitle,
    'findings'   => $findings,
    'summary'    => count($findings) === 0
        ? 'このページで動画コンテンツを検出できませんでした'
        : count($findings) . ' 種類の動画形式を検出しました',
], JSON_UNESCAPED_UNICODE);

// ── Helpers ───────────────────────────────────────────────────────────────────

function hlsEncrypted(string $m3u8Url): bool
{
    $ch = curl_init($m3u8Url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body !== false && strpos($body, '#EXT-X-KEY') !== false;
}

function auResolve(string $base, string $rel): ?string
{
    if (preg_match('/^https?:\/\//i', $rel)) return $rel;
    if (strpos($rel, '//') === 0) return (parse_url($base, PHP_URL_SCHEME) ?? 'https') . ':' . $rel;
    if (strpos($rel, ':') !== false) return null;
    $p = parse_url($base);
    $scheme = $p['scheme'] ?? 'https'; $host = $p['host'] ?? '';
    $port   = isset($p['port']) ? ':' . $p['port'] : '';
    if (strpos($rel, '/') === 0) return "$scheme://$host$port$rel";
    $dir = isset($p['path']) ? rtrim(dirname($p['path']), '/') : '';
    $segs = [];
    foreach (explode('/', $dir . '/' . $rel) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($segs); continue; }
        $segs[] = $seg;
    }
    return "$scheme://$host$port/" . implode('/', $segs);
}
