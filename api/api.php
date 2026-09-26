<?php
/**
 * API相关接口
 */
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'get':
        // 获取单个API信息
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM apis WHERE id = ?");
        $stmt->execute([$id]);
        $api = $stmt->fetch();
        
        if ($api) {
            success('获取成功', $api);
        } else {
            error('API不存在');
        }
        break;
        
    case 'delete':
        // 删除API
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare("DELETE FROM apis WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() > 0) {
            success('删除成功');
        } else {
            error('API不存在');
        }
        break;
        
    case 'toggle':
        // 切换API状态
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare("UPDATE apis SET status = 1 - status WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() > 0) {
            success('状态更新成功');
        } else {
            error('API不存在');
        }
        break;
        
    case 'test':
        // 测试API
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM apis WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $api = $stmt->fetch();
        
        if (!$api) {
            error('API不存在');
            exit;
        }
        
        $url = $api['api_url'];
        $method = strtoupper($api['method'] ?? 'GET');
        $timeout = (int)($api['timeout'] ?? 10);
        
        // 解析 headers
        $headers = [];
        if (!empty($api['headers'])) {
            $decoded = json_decode($api['headers'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $headers[] = "$k: $v";
                }
            }
        }
        
        // 解析 body
        $body = null;
        if (!empty($api['body']) && $method === 'POST') {
            $body = $api['body'];
        }
        
        $result = curlRequest($url, $method, $headers, $body, $timeout);

        if ($result['error']) {
            error('请求失败: ' . $result['error']);
        } else {
            $rawBody = is_string($result['body']) ? $result['body'] : '';
            $contentType = $result['content_type'] ?? '';
            $isImage = $contentType && stripos($contentType, 'image/') === 0;
            $imagePreview = '';

            // 图片类型：保存到本地以提供预览，避免二进制直接当文本显示
            if ($isImage && $rawBody !== '') {
                $imagePreview = yunhuSaveImageLocally($rawBody, 'test');
            }

            // 容错：确保响应体是安全的 UTF-8 字符串，避免 json_encode 失败
            $responseBody = $rawBody;
            if (!mb_check_encoding($responseBody, 'UTF-8')) {
                $encoding = mb_detect_encoding($responseBody, ['UTF-8', 'GBK', 'GB2312', 'ISO-8859-1'], true);
                if ($encoding && $encoding !== 'UTF-8') {
                    $responseBody = mb_convert_encoding($responseBody, 'UTF-8', $encoding);
                } else {
                    $responseBody = mb_convert_encoding($responseBody, 'UTF-8', 'UTF-8');
                }
            }
            // 限制响应大小，避免页面卡死
            $displayBody = $isImage ? '[图片二进制内容，已生成预览]' : $responseBody;
            if (mb_strlen($displayBody) > 51200) {
                $displayBody = mb_substr($displayBody, 0, 51200) . "\n\n... [响应已截断，完整大小: " . strlen($rawBody) . " 字节]";
            }
            success('测试成功', [
                'http_code' => $result['http_code'],
                'response' => $displayBody,
                'size' => strlen($rawBody),
                'content_type' => $contentType,
                'is_image' => $isImage,
                'image_url' => $imagePreview,
            ]);
        }
        break;
        
    default:
        error('未知操作');
}
