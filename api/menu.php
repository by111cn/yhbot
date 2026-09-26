<?php
/**
 * 白屿云平台 BotAPI - 菜单管理 API
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
requireLogin();

$action = get('action');

switch ($action) {
    case 'list':
        $userId = $_SESSION['user_id'];
        $botId = get('bot_id');
        $menus = getMenuList($userId, $botId ?: null);
        success('获取成功', $menus);
        break;

    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $botId = intval(post('bot_id'));
        $name = post('name');
        $type = post('type', 'text');
        $content = post('content', '');

        if (empty($botId) || empty($name)) {
            error('请填写必填项');
        }

        try {
            $stmt = db()->prepare("INSERT INTO menus (user_id, bot_id, name, type, content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $botId, $name, $type, $content]);
            success('添加成功', ['id' => db()->lastInsertId()]);
        } catch (Exception $e) {
            error('添加失败: ' . $e->getMessage());
        }
        break;

    case 'edit':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $id = intval(post('id'));
        $botId = intval(post('bot_id'));
        $name = post('name');
        $type = post('type', 'text');
        $content = post('content', '');

        if (empty($name)) {
            error('请填写必填项');
        }

        try {
            $stmt = db()->prepare("UPDATE menus SET bot_id=?, name=?, type=?, content=? WHERE id=? AND user_id=?");
            $stmt->execute([$botId, $name, $type, $content, $id, $_SESSION['user_id']]);
            success('更新成功');
        } catch (Exception $e) {
            error('更新失败: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $id = intval(post('id')) ?: intval(get('id'));
        try {
            $stmt = db()->prepare("DELETE FROM menus WHERE id=? AND user_id=?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            success('删除成功');
        } catch (Exception $e) {
            error('删除失败');
        }
        break;

    case 'sync':
        // 同步菜单到云湖机器人
        $botId = intval(post('bot_id'));
        $bot = getBot($botId);
        if (!$bot || $bot['user_id'] != $_SESSION['user_id']) {
            error('机器人不存在或无权限');
        }

        $menus = getMenuList($_SESSION['user_id'], $botId);

        $buttons = [];
        foreach ($menus as $menu) {
            $buttons[] = [
                'name' => $menu['name'],
                'type' => 'callback',
                'value' => $menu['content'] ?? ''
            ];
        }

        // 调用云湖API设置菜单
        $response = curlPostJson(
            'https://yhchat-bot.yhchat.cn/api/setMenu',
            ['token' => $bot['token'], 'buttons' => $buttons]
        );
        $result = json_decode($response, true);
        if ($result && isset($result['code']) && $result['code'] == 0) {
            success('菜单同步成功');
        } else {
            error('同步失败: ' . ($result['msg'] ?? '未知错误'));
        }
        break;

    default:
        error('未知操作');
}
