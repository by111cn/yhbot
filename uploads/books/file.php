<?php
/**
 * 书籍下载中转访问入口
 * 用法: /uploads/books/file.php?f=文件名
 * 作用: 安全地暴露 uploads/books/ 下的书籍文件给用户下载
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
    'txt'  => 'text/plain; charset=utf-8',
    'zip'  => 'application/zip',
    'rar'  => 'application/x-rar-compressed',
    '7z'   => 'application/x-7z-compressed',
    'epub' => 'application/epub+zip',
    'pdf'  => 'application/pdf',
    'mobi' => 'application/x-mobipocket-ebook',
    'azw3' => 'application/vnd.amazon.ebook',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'csv'  => 'text/csv',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// 显示原始文件名（从 book_时间_哈希.扩展名 中提取扩展名时）
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
    http_response_code(200);
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . ($end - $start + 1));
header('Content-Disposition: attachment; filename="' . $file . '"');
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
