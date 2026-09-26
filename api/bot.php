<?php
/**
 * 白屿云平台 BotAPI - 客机管理 API
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
requireLogin();

$action = get('action');

switch ($action) {
    case 'list':
        // 获取机器人列表
        $userId = $_SESSION['user_id'];
        $bots = getBotList($userId);
        success('获取成功', $bots);
        break;

    case 'get':
        // 获取单个机器人详情
        $id = intval(get('id'));
        $bot = getBot($id);
        if ($bot && $bot['user_id'] == $_SESSION['user_id']) {
            success('获取成功', $bot);
        } else {
            error('机器人不存在或无权限');
        }
        break;

    case 'add':
        // 添加机器人
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $name = post('name');
        $token = post('token');
        $callbackUrl = post('callback_url', '');
        $avatar = post('avatar', '');

        if (empty($name) || empty($token)) {
            error('请填写必填项');
        }

        $currentUser = getCurrentUser();
        $isAdminUser = ($currentUser['role'] ?? 1) == 0;

        // 非管理员检查额度
        if (!$isAdminUser && ($currentUser['quota'] ?? 0) <= 0) {
            error('额度不足，请先兑换卡密获取额度');
        }

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("INSERT INTO bots (user_id, name, token, callback_url, avatar, status, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$_SESSION['user_id'], $name, $token, $callbackUrl, $avatar]);
            if (!$isAdminUser) {
                db()->prepare("UPDATE users SET quota = quota - 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
            }
            db()->commit();
            success('添加成功', ['id' => db()->lastInsertId()]);
        } catch (Exception $e) {
            db()->rollBack();
            error('添加失败: ' . $e->getMessage());
        }
        break;

    case 'edit':
        // 编辑机器人
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $id = intval(post('id'));
        $name = post('name');
        $token = post('token');
        $callbackUrl = post('callback_url', '');
        $avatar = post('avatar', '');
        $status = intval(post('status', 1));

        if (empty($name) || empty($token)) {
            error('请填写必填项');
        }

        try {
            $stmt = db()->prepare("UPDATE bots SET name=?, token=?, callback_url=?, avatar=?, status=? WHERE id=? AND user_id=?");
            $stmt->execute([$name, $token, $callbackUrl, $avatar, $status, $id, $_SESSION['user_id']]);
            success('更新成功');
        } catch (Exception $e) {
            error('更新失败: ' . $e->getMessage());
        }
        break;

    case 'delete':
        // 删除机器人
        $id = intval(post('id')) ?: intval(get('id'));
        if ($id <= 0) {
            error('参数错误');
        }
        $currentUser = getCurrentUser();
        $isAdminUser = ($currentUser['role'] ?? 1) == 0;
        try {
            db()->beginTransaction();
            $stmt = db()->prepare("DELETE FROM bots WHERE id=? AND user_id=?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            // 非管理员返还额度
            if (!$isAdminUser && $stmt->rowCount() > 0) {
                db()->prepare("UPDATE users SET quota = quota + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
            }
            db()->commit();
            success('删除成功' . (!$isAdminUser ? '，额度已返还' : ''));
        } catch (Exception $e) {
            db()->rollBack();
            error('删除失败');
        }
        break;

    case 'toggle':
        // 切换状态 (支持 GET/POST 双通道)
        $id = intval(post('id')) ?: intval(get('id'));
        $status = intval(post('status')) ?: intval(get('status'));
        try {
            $stmt = db()->prepare("UPDATE bots SET status=? WHERE id=? AND user_id=?");
            $stmt->execute([$status, $id, $_SESSION['user_id']]);
            success('状态更新成功');
        } catch (Exception $e) {
            error('操作失败');
        }
        break;

    case 'set_owner':
        // 设置机器人的主人ID (支持 GET/POST 双通道)
        $id = intval(post('id')) ?: intval(get('id'));
        $ownerId = post('owner_id') ?: get('owner_id', '');
        if ($id <= 0 || empty($ownerId)) {
            error('参数错误');
        }
        try {
            $stmt = db()->prepare("UPDATE bots SET owner_id=? WHERE id=? AND user_id=?");
            $stmt->execute([$ownerId, $id, $_SESSION['user_id']]);
            success('主人设置成功');
        } catch (Exception $e) {
            error('设置失败');
        }
        break;

    case 'sync_avatar':
        // 从云湖获取机器人头像
        $id = intval(get('id'));
        $bot = getBot($id);
        if (!$bot || $bot['user_id'] != $_SESSION['user_id']) {
            error('机器人不存在或无权限');
        }
        $avatarUrl = yunhuFetchBotAvatar($bot['token']);
        if ($avatarUrl) {
            try {
                db()->prepare("UPDATE bots SET avatar=? WHERE id=?")->execute([$avatarUrl, $id]);
                success('头像同步成功', ['avatar' => $avatarUrl]);
            } catch (Exception $e) {
                error('保存失败');
            }
        } else {
            error('未能从云湖获取到头像，请手动填写');
        }
        break;

    default:
        error('未知操作');
}
