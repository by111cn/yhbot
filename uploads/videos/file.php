<?php
/**
 * 视频中转访问入口（流媒体支持 Range）
 * 用法: /uploads/videos/file.php?f=文件名
 */
$root = __DIR__;
$file = isset($_GET['f']) ? basename($_GET['f']) : '';
$path = $root . DIRECTORY_SEPARATOR . $file;

if (!preg_match('/^[A-Za-z0-9._-]+$/', $file) || !is_file($path)) {
    http_response_code(404);
    exit('Not Found');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$videoMime = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'mkv'  => 'video/x-matroska',
    'flv'  => 'video/x-flv',
    'wmv'  => 'video/x-ms-wmv',
];
$mime = $videoMime[$ext] ?? 'video/mp4';

$size = filesize($path);
$start = 0; $end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = (int)$m[1];
    if ($m[2] !== '') $end = (int)$m[2];
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');

$fp = fopen($path, 'rb');
if (!$fp) { http_response_code(500); exit('Open failed'); }
if ($start > 0) fseek($fp, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fp)) {
    $chunk = fread($fp, min(8192, $remaining));
    echo $chunk;
    $remaining -= strlen($chunk);
    @ob_flush(); @flush();
}
fclose($fp);
exit;
