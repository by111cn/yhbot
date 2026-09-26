<?php
/**
 * 图片中转访问入口
 * 用法: /uploads/images/image.php?f=文件名
 * 作用: 安全地暴露 uploads/images/ 下的图片给云湖服务器访问
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
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');
readfile($path);
exit;
