<?php
/**
 * 云湖机器人 Webhook 回调接口
 * 
 * 接收云湖推送的 HTTP POST 消息，调用 message_handler.php 处理
 * 与 ws_server.php 共享相同的处理逻辑
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/message_handler.php';

// 给 webhook 整个请求足够时间（视频上传要 1-3 分钟）
set_time_limit(300);
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');
ignore_user_abort(true);

// ===== 致命错误抓取 =====
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $logPath = __DIR__ . '/../uploads/webhook-fatal.log';
        if (is_writable(dirname($logPath)) || !file_exists($logPath)) {
            error_log(sprintf(
                "[WEBHOOK-FATAL] %s in %s:%d",
                $err['message'], $err['file'], $err['line']
            ), 3, $logPath);
        }
    }
});

// ===== 获取原始 POST 数据 =====
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => 'Invalid JSON']);
    exit;
}

// ===== 获取机器人 ID =====
$botId = (int)($_GET['bot'] ?? 0);
if (!$botId) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => 'Bot ID required']);
    exit;
}

// ===== 查找机器人 =====
$bot = getBot($botId);
if (!$bot || $bot['status'] != 1) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'msg' => 'Bot not found or disabled']);
    exit;
}

// ===== 解析云湖 Webhook 推送数据 =====
$header = $data['header'] ?? [];
$event  = $data['event'] ?? [];

$sender     = $event['sender'] ?? [];
$fromId     = $sender['senderId'] ?? '';
$fromName   = $sender['senderNickname'] ?? '';

$chat       = $event['chat'] ?? [];
$chatId     = $chat['chatId'] ?? '';
$chatType   = $chat['chatType'] ?? 'bot';

$msg        = $event['message'] ?? [];
$msgId      = $msg['msgId'] ?? '';
$msgType    = $msg['contentType'] ?? 'text';
$msgContent = $msg['content'] ?? [];

$instructionId   = $msg['instructionId'] ?? 0;
$instructionName = $msg['instructionName'] ?? '';

// 提取消息文本内容
$content = $msgContent['text'] ?? '';
if (empty($content) && $msgType === 'messageButton') {
    $content = $msgContent['button'] ?? $instructionName;
}

// ===== 构建统一事件数据 =====
$eventData = [
    'content'    => $content,
    'chatType'   => $chatType,
    'fromId'     => $fromId,
    'fromName'   => $fromName,
    'chatId'     => $chatId,
    'msgType'    => $msgType,
    'msgId'      => $msgId,
    'rawData'    => $rawData,
];

// ===== 非文本/按钮消息直接返回 =====
if (($msgType !== 'text' && $msgType !== 'messageButton') || empty($content)) {
    // 但仍保存到数据库
    $replyTargetId = $chatType === 'group' ? $chatId : $fromId;
    $replyTargetType = $chatType === 'group' ? 'group' : 'user';
    $stmt = db()->prepare("INSERT INTO messages (bot_id, chat_type, chat_id, from_id, from_name, content, msg_type, extra, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$botId, $chatType, $chatId, $fromId, $fromName, $content, $msgType, $rawData]);
    echo json_encode(['code' => 0, 'msg' => 'ok']);
    exit;
}

// ===== 快速返回 200，后台异步处理 =====
session_write_close();
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('Connection: close');
echo json_encode(['code' => 0, 'msg' => 'accepted', 'async' => true]);
header('Content-Length: ' . ob_get_length());
ob_flush(); flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ===== 后台调用共享处理逻辑 =====
try {
    processBotMessage($bot, $eventData);
} catch (Throwable $e) {
    addMessageLog($botId, 'error', 'processBotMessage 异常: ' . $e->getMessage(), $botId, $bot['name'], 'webhook');
}
