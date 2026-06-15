<?php
$data   = json_decode(file_get_contents('php://input'), true);
$images = $data['images'] ?? [];

if (empty($images)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => '画像が選択されていません']);
    exit;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'scraper_');

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ZIPファイルの作成に失敗しました']);
    exit;
}

$extMap = [
    'image/jpeg'   => 'jpg',
    'image/jpg'    => 'jpg',
    'image/png'    => 'png',
    'image/gif'    => 'gif',
    'image/webp'   => 'webp',
    'image/svg+xml'=> 'svg',
    'image/bmp'    => 'bmp',
    'image/avif'   => 'avif',
];

$counts = [];

foreach ($images as $item) {
    $imgUrl = $item['url'] ?? '';
    if (!$imgUrl) continue;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $imgUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $imgData    = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!$imgData) continue;

    $filename = $item['filename'] ?? 'image.jpg';
    $info     = pathinfo($filename);
    $base     = $info['filename'];
    $ext      = $info['extension'] ?? '';

    if (!$ext) {
        $ct  = strtolower(trim(explode(';', $contentType)[0]));
        $ext = $extMap[$ct] ?? 'jpg';
        $filename = "$base.$ext";
    }

    // Deduplicate filenames
    if (isset($counts[$filename])) {
        $counts[$filename]++;
        $finalName = "{$base}_{$counts[$filename]}.$ext";
    } else {
        $counts[$filename] = 0;
        $finalName = $filename;
    }

    $zip->addFromString($finalName, $imgData);
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="scraped_images.zip"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
readfile($tmpFile);
unlink($tmpFile);
