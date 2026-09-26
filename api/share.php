<?php
/**
 * API分享码接口
 * action=generate  生成分享码（POST）
 * action=query     查询分享码数据（GET）
 * action=import    导入分享码（POST）
 */
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$action = get('action');
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'generate':
        // 生成12位字母数字分享码
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $raw = rawPost('data', '');
        $data = json_decode($raw, true);
        if (!$data || (empty($data['apis']) && empty($data['menus']))) {
            error('请至少选择一个API或菜单');
        }
        // 确保是当前用户的资源
        if (!empty($data['apis'])) {
            $placeholders = implode(',', array_fill(0, count($data['apis']), '?'));
            $stmt = db()->prepare("SELECT COUNT(*) FROM apis WHERE id IN ({$placeholders}) AND user_id = ?");
            $params = array_merge(array_map('intval', $data['apis']), [$userId]);
            $stmt->execute($params);
            if ($stmt->fetchColumn() < count($data['apis'])) {
                error('包含无效或非本人的API');
            }
        }
        if (!empty($data['menus'])) {
            $placeholders = implode(',', array_fill(0, count($data['menus']), '?'));
            $stmt = db()->prepare("SELECT COUNT(*) FROM menus WHERE id IN ({$placeholders}) AND user_id = ?");
            $params = array_merge(array_map('intval', $data['menus']), [$userId]);
            $stmt->execute($params);
            if ($stmt->fetchColumn() < count($data['menus'])) {
                error('包含无效或非本人的菜单');
            }
        }

        // 查询分享者用户名
        $userStmt = db()->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $sharer = $userStmt->fetchColumn() ?: '未知用户';

        $payload = json_encode([
            'apis' => $data['apis'] ?? [],
            'menus' => $data['menus'] ?? [],
            'sharer' => $sharer,
            'time' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        // 生成唯一12位码（重试最多5次防碰撞）
        $maxRetry = 5;
        for ($i = 0; $i < $maxRetry; $i++) {
            $code = generateRandomCode(12);
            $check = db()->prepare("SELECT id FROM share_codes WHERE code = ?");
            $check->execute([$code]);
            if (!$check->fetch()) {
                break;
            }
            if ($i === $maxRetry - 1) {
                error('生成失败，请重试');
            }
        }

        $stmt = db()->prepare("INSERT INTO share_codes (code, data, created_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$code, $payload, $userId]);
        success('分享码生成成功', ['code' => $code]);

        break;

    case 'query':
        // 查询分享码对应的数据
        $code = get('code', '');
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $code);
        if (strlen($code) !== 12) {
            error('分享码格式不正确（12位字母数字）');
        }
        $stmt = db()->prepare("SELECT * FROM share_codes WHERE code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) {
            error('分享码无效或已过期');
        }
        $apiIds = [];
        $menuIds = [];
        $data = json_decode($row['data'], true);
        if ($data) {
            $apiIds = $data['apis'] ?? [];
            $menuIds = $data['menus'] ?? [];
        }
        // 返回原始数据，由前端展示
        success('查询成功', [
            'code' => $row['code'],
            'data' => $data,
            'created_at' => $row['created_at'],
            'api_count' => count($apiIds),
            'menu_count' => count($menuIds),
        ]);

        break;

    case 'import':
        // 导入分享码中的数据
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误');
        }
        $code = post('code', '');
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $code);
        if (strlen($code) !== 12) {
            error('分享码格式不正确（12位字母数字）');
        }
        $stmt = db()->prepare("SELECT * FROM share_codes WHERE code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) {
            error('分享码无效或已过期');
        }
        $data = json_decode($row['data'], true);
        if (!$data) {
            error('分享数据解析失败');
        }

        $apiIds = array_map('intval', $data['apis'] ?? []);
        $menuIds = array_map('intval', $data['menus'] ?? []);
        $importedApis = 0;
        $importedMenus = 0;

        // 导入API（复制一份给当前用户）
        if (!empty($apiIds)) {
            $placeholders = implode(',', array_fill(0, count($apiIds), '?'));
            $stmt = db()->prepare("SELECT * FROM apis WHERE id IN ({$placeholders})");
            $stmt->execute($apiIds);
            $srcApis = $stmt->fetchAll();
            foreach ($srcApis as $api) {
                // 检查当前用户是否已有同名的API
                $dup = db()->prepare("SELECT id FROM apis WHERE user_id = ? AND name = ?");
                $dup->execute([$userId, $api['name']]);
                if ($dup->fetch()) {
                    continue; // 跳过同名
                }
                $apiName = $api['name'];
                $apiUrl = $api['api_url'];
                $method = $api['method'] ?? 'GET';
                $headers = $api['headers'] ?? '';
                $body = $api['body'] ?? '';
                $timeout = $api['timeout'] ?? 10;
                $categoryId = $api['category_id'] ?? 0;
                $description = $api['description'] ?? '';

                $ins = db()->prepare("INSERT INTO apis (user_id, name, api_url, method, headers, body, timeout, category_id, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $ins->execute([$userId, $apiName, $apiUrl, $method, $headers, $body, $timeout, $categoryId, $description]);
                $importedApis++;
            }
        }

        // 导入菜单
        if (!empty($menuIds)) {
            $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
            $stmt = db()->prepare("SELECT * FROM menus WHERE id IN ({$placeholders})");
            $stmt->execute($menuIds);
            $srcMenus = $stmt->fetchAll();
            foreach ($srcMenus as $menu) {
                $dup = db()->prepare("SELECT id FROM menus WHERE user_id = ? AND name = ?");
                $dup->execute([$userId, $menu['name']]);
                if ($dup->fetch()) {
                    continue;
                }
                $menuName = $menu['name'];
                $menuType = $menu['type'] ?? 'text';
                $content = $menu['content'] ?? '';
                $botId = $menu['bot_id'] ?? 0;
                $parentId = $menu['parent_id'] ?? 0;
                $sort = $menu['sort'] ?? 0;

                $ins = db()->prepare("INSERT INTO menus (user_id, bot_id, name, type, content, parent_id, sort, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $ins->execute([$userId, $botId, $menuName, $menuType, $content, $parentId, $sort]);
                $importedMenus++;
            }
        }

        // 更新使用计数
        db()->prepare("UPDATE share_codes SET used_count = used_count + 1 WHERE code = ?")->execute([$code]);

        success("导入成功！API: {$importedApis}个，菜单: {$importedMenus}个", [
            'api_count' => $importedApis,
            'menu_count' => $importedMenus,
        ]);

        break;

    default:
        error('未知操作');
}

/**
 * 生成指定长度的随机字母数字码（大小写字母+数字）
 */
function generateRandomCode($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}
