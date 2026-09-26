<?php
/**
 * 白屿云平台 BotAPI - 消息管理 API
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
requireLogin();

$action = get('action');

switch ($action) {
    case 'logs':
        // 获取消息日志
        $botId = intval(get('bot_id'));
        $page = max(1, intval(get('page', 1)));
        $perPage = max(1, min(100, intval(get('per_page', 20))));

        // 构建 WHERE 条件
        $where = "b.user_id = ?";
        $params = [$_SESSION['user_id']];
        if ($botId) {
            $where .= " AND l.bot_id = ?";
            $params[] = $botId;
        }
        $keyword = get('keyword');
        if ($keyword) {
            $where .= " AND (l.content LIKE ? OR l.from_name LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }

        // 精确的 COUNT 查询（不使用字符串替换）
        $countSql = "SELECT COUNT(*) as total FROM message_logs l LEFT JOIN bots b ON l.bot_id = b.id WHERE {$where}";
        $stmt = db()->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['total'];

        // 分页数据查询
        $offset = ($page - 1) * $perPage;
        $dataSql = "SELECT l.*, b.name as bot_name FROM message_logs l LEFT JOIN bots b ON l.bot_id = b.id WHERE {$where} ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = db()->prepare($dataSql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        success('获取成功', [
            'list' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ]);
        break;

    case 'messages':
        // 获取聊天消息
        $botId = intval(get('bot_id'));
        $type = get('type', 'private');
        $limit = intval(get('limit', 50));

        $sql = "SELECT * FROM messages WHERE bot_id = ? AND chat_type = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = db()->prepare($sql);
        $stmt->execute([$botId, $type, $limit]);
        $messages = $stmt->fetchAll();

        success('获取成功', $messages);
        break;

    case 'send':
        // 发送消息
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $botId = intval(post('bot_id'));
        $chatId = post('chat_id');
        $content = post('content');
        $type = post('msg_type', 'text');

        if (empty($chatId) || empty($content)) {
            error('请填写必填项');
        }

        $bot = getBot($botId);
        if (!$bot || $bot['user_id'] != $_SESSION['user_id']) {
            error('机器人不存在或无权限');
        }

        $recvType = post('recv_type', 'user');
        $result = yunhuSendMessage($bot['token'], $chatId, $content, $type, $recvType);
        if ($result && isset($result['code']) && $result['code'] == 1) {
            addMessageLog($botId, 'send', $content, $_SESSION['user_id'], getCurrentUser()['username'] ?? '', json_encode(['chat_id' => $chatId, 'type' => $type]));
            success('发送成功');
        } else {
            error('发送失败: ' . ($result['msg'] ?? '未知错误'));
        }
        break;

    case 'delete':
        // 删除消息日志 (支持 GET/POST 双通道)
        $id = intval(post('id')) ?: intval(get('id'));
        try {
            $stmt = db()->prepare("DELETE l FROM message_logs l JOIN bots b ON l.bot_id = b.id WHERE l.id = ? AND b.user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            success('删除成功');
        } catch (Exception $e) {
            error('删除失败');
        }
        break;

    case 'clear':
        // 清空日志 (支持 GET/POST 双通道)
        $botId = intval(post('bot_id')) ?: intval(get('bot_id'));
        try {
            if ($botId) {
                $stmt = db()->prepare("DELETE l FROM message_logs l JOIN bots b ON l.bot_id = b.id WHERE l.bot_id = ? AND b.user_id = ?");
                $stmt->execute([$botId, $_SESSION['user_id']]);
            } else {
                $stmt = db()->prepare("DELETE l FROM message_logs l JOIN bots b ON l.bot_id = b.id WHERE b.user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
            success('清空成功');
        } catch (Exception $e) {
            error('清空失败');
        }
        break;

    default:
        error('未知操作');
}
