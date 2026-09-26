<?php
/**
 * 文件/视频中转访问入口
 * 用法: /uploads/files/file.php?f=文件名
 * 作用: 安全地暴露 uploads/files/ 下的文件给云湖服务器访问
 */
$root = __DIR__;
$file = isset($_GET['f']) ? basename($_GET['f']) : '';
$path = $root . DIRECTORY_SEPARATOR . $file;

// 仅允许字母数字、下划线、点、横线（防目录穿越）
if (!preg_match('/^[A-Za-z0-9._-]+$/', $file) || !is_file($path)) {
    http_response_code(404);
    exit('Not Found');
}

// 根据扩展名输出 MIME
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'mkv'  => 'video/x-matroska',
    'flv'  => 'video/x-flv',
    'wmv'  => 'video/x-ms-wmv',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip'  => 'application/zip',
    'rar'  => 'application/x-rar-compressed',
    '7z'   => 'application/x-7z-compressed',
    'txt'  => 'text/plain',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'csv'  => 'text/csv',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// 大文件（视频/音频）支持 Range 请求（流媒体播放）
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
    header('Accept-Ranges: bytes');
} else {
    header('Accept-Ranges: bytes');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');

$fp = fopen($path, 'rb');
if ($fp === false) { http_response_code(500); exit('Open failed'); }
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
