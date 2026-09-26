<?php
/**
 * BotAPI 插件市场 API
 * 插件发布/审核/市场浏览
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

if (!isLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$isAdmin = isAdmin();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'my_published':
        handleMyPublished($userId);
        break;
    case 'publish':
        handlePublish($userId);
        break;
    case 'unpublish':
        handleUnpublish($userId, $isAdmin);
        break;
    case 'download':
        handleDownload($userId);
        break;
    case 'install':
        handleInstall($userId);
        break;
    case 'review_list':
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'无权限']); exit; }
        handleReviewList();
        break;
    case 'approve':
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'无权限']); exit; }
        handleApprove();
        break;
    case 'reject':
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'无权限']); exit; }
        handleReject();
        break;
    case 'reviewed_list':
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'无权限']); exit; }
        handleReviewedList();
        break;
    case 'delete':
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'无权限']); exit; }
        handleDelete();
        break;
    default:
        echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
}

/**
 * 获取市场插件列表（只显示审核通过的）
 */
function handleList() {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    $keyword = trim($_GET['keyword'] ?? '');

    try {
        $where = "WHERE p.market_status = 2";
        $params = [];

        if (!empty($keyword)) {
            $where .= " AND (p.name LIKE ? OR p.desc LIKE ? OR p.author LIKE ?)";
            $kw = '%' . $keyword . '%';
            $params = [$kw, $kw, $kw];
        }

        $countStmt = db()->prepare("SELECT COUNT(*) FROM plugins p {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = db()->prepare("SELECT p.id, p.name, p.author, p.version, p.desc, p.command_list, 
            p.created_at, p.updated_at, u.nickname AS publisher_name
            FROM plugins p 
            LEFT JOIN users u ON p.user_id = u.id 
            {$where} 
            ORDER BY p.updated_at DESC 
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $plugins = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $plugins,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 获取我发布的市场插件
 */
function handleMyPublished($userId) {
    try {
        $stmt = db()->prepare("SELECT p.*, u.nickname AS publisher_name 
            FROM plugins p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE p.user_id = ? AND p.market_status > 0 
            ORDER BY p.updated_at DESC");
        $stmt->execute([$userId]);
        $plugins = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $plugins], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 发布插件到市场（提交审核）
 */
function handlePublish($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT * FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($plugin['user_id'] != $userId && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => '无权限操作此插件'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($plugin['market_status'] == 2) {
            echo json_encode(['success' => false, 'message' => '该插件已在市场中'], JSON_UNESCAPED_UNICODE);
            return;
        }

        db()->prepare("UPDATE plugins SET market_status = 1, updated_at = NOW() WHERE id = ?")
            ->execute([$id]);

        // 推送审核通知给管理员（通过机器人私聊）
        try {
            // 优先用云湖 ID，兼容旧版 admin_qq
            $adminTarget = getSetting('admin_yunhu_id');
            if (empty($adminTarget)) {
                $adminTarget = getSetting('admin_qq');
            }
            if (!empty($adminTarget)) {
                $botStmt = db()->prepare("SELECT * FROM bots WHERE status = 1 LIMIT 1");
                $botStmt->execute();
                $notifyBot = $botStmt->fetch();
                if ($notifyBot) {
                    $pluginName = $plugin['name'];
                    $pluginDesc = !empty($plugin['desc']) ? mb_substr($plugin['desc'], 0, 100) : '无说明';
                    $msg = "📦 插件审核请求 #{$id}\n"
                        . "名称：{$pluginName}\n"
                        . "作者：" . ($plugin['author'] ?: $pluginName) . "\n"
                        . "版本：{$plugin['version']}\n"
                        . "说明：{$pluginDesc}\n"
                        . "----------------\n"
                        . "回复「通过 {$id}」审核通过\n"
                        . "回复「拒绝 {$id}」审核拒绝";
                    yunhuSendMessage($notifyBot['token'], $adminTarget, $msg, 'text', 'user');
                }
            }

            // 同步推送邮件通知给管理员
            $adminEmail = '482171260@qq.com';
            $mailSubject = '【' . getSiteName() . '】新插件审核请求 #' . $id;
            $mailBody = "收到新的插件审核请求，请及时审核。\n\n"
                . "插件编号：#{$id}\n"
                . "插件名称：{$pluginName}\n"
                . "作者：" . ($plugin['author'] ?: $pluginName) . "\n"
                . "版本：{$plugin['version']}\n"
                . "说明：{$pluginDesc}\n\n"
                . "请登录管理后台进行审核。";
            sendMail($adminEmail, $mailSubject, $mailBody);
        } catch (Exception $e) {
            // 静默处理，不影响用户操作
        }

        echo json_encode(['success' => true, 'message' => '已提交审核，请等待管理员审核'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 从市场下架插件
 */
function handleUnpublish($userId, $isAdmin) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT * FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($plugin['user_id'] != $userId && !$isAdmin) {
            echo json_encode(['success' => false, 'message' => '无权限操作此插件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        db()->prepare("UPDATE plugins SET market_status = 0, updated_at = NOW() WHERE id = ?")
            ->execute([$id]);

        echo json_encode(['success' => true, 'message' => '已从市场下架'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 从市场下载插件源码
 */
function handleDownload($userId) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT p.*, u.nickname AS publisher_name FROM plugins p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE p.id = ? AND p.market_status = 2");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '插件不存在或未上架'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 获取源码
        $code = $plugin['plugin_code'] ?? '';
        if (empty($code) && !empty($plugin['plugin_file'])) {
            $filePath = dirname(__DIR__) . '/uploads/plugins/' . $plugin['plugin_file'];
            if (file_exists($filePath)) {
                $code = file_get_contents($filePath);
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $plugin['id'],
                'name' => $plugin['name'],
                'author' => $plugin['author'],
                'version' => $plugin['version'],
                'desc' => $plugin['desc'],
                'command_list' => $plugin['command_list'],
                'code' => $code,
                'publisher_name' => $plugin['publisher_name'] ?? ''
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '获取失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 一键安装插件到自己的插件管理（已安装则自动更新版本）
 */
function handleInstall($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT * FROM plugins WHERE id = ? AND market_status = 2");
        $stmt->execute([$id]);
        $source = $stmt->fetch();

        if (!$source) {
            echo json_encode(['success' => false, 'message' => '插件不存在或未上架'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 检查是否已安装过同名插件
        $check = db()->prepare("SELECT * FROM plugins WHERE user_id = ? AND name = ? AND market_status = 0");
        $check->execute([$userId, $source['name']]);
        $existing = $check->fetch();

        $code = $source['plugin_code'] ?? '';
        $pluginFile = '';

        if (!empty($code)) {
            $uploadDir = dirname(__DIR__) . '/uploads/plugins/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'plugin_' . $userId . '_' . time() . '_market.php';
            $destPath = $uploadDir . $fileName;
            if (file_put_contents($destPath, $code) !== false) {
                $pluginFile = $fileName;
            }
        }

        if ($existing) {
            // 已安装：更新版本
            $oldVersion = $existing['version'] ?? '0';
            $newVersion = $source['version'];

            db()->prepare("UPDATE plugins SET 
                author = ?, version = ?, `desc` = ?, command_list = ?, 
                plugin_code = ?, plugin_file = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?")
                ->execute([
                    $source['author'],
                    $newVersion,
                    $source['desc'],
                    $source['command_list'],
                    $code,
                    $pluginFile,
                    $existing['id'],
                    $userId
                ]);

            // 清理旧的插件文件
            $oldFile = $existing['plugin_file'] ?? '';
            if (!empty($oldFile) && $oldFile !== $pluginFile) {
                $oldPath = dirname(__DIR__) . '/uploads/plugins/' . $oldFile;
                if (file_exists($oldPath)) unlink($oldPath);
            }

            echo json_encode([
                'success' => true,
                'message' => "插件已从 {$oldVersion} 更新到 {$newVersion} 版本",
                'is_update' => true
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // 未安装：创建新插件记录
            db()->prepare("INSERT INTO plugins 
                (user_id, bot_id, name, author, version, `desc`, command_list, plugin_code, plugin_file, enabled, market_status)
                VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, 1, 0)")
                ->execute([
                    $userId,
                    $source['name'],
                    $source['author'],
                    $source['version'],
                    $source['desc'],
                    $source['command_list'],
                    $code,
                    $pluginFile
                ]);

            echo json_encode([
                'success' => true,
                'message' => '安装成功，请在插件管理中查看',
                'is_update' => false
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '安装失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 获取待审核列表（管理员）
 */
function handleReviewList() {
    try {
        $stmt = db()->query("SELECT p.*, u.nickname AS publisher_name, u.username 
            FROM plugins p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE p.market_status = 1 
            ORDER BY p.updated_at DESC");
        $plugins = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $plugins], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 审核通过
 */
function handleApprove() {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // 查询插件及作者信息
        $stmt = db()->prepare("SELECT p.*, u.username, u.email FROM plugins p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.market_status = 1");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在或非待审核状态'], JSON_UNESCAPED_UNICODE);
            return;
        }

        db()->prepare("UPDATE plugins SET market_status = 2, updated_at = NOW() WHERE id = ? AND market_status = 1")
            ->execute([$id]);

        // 发送邮件通知
        if (!empty($plugin['email'])) {
            $username = $plugin['username'] ?: '用户';
            $pluginName = $plugin['name'] ?: '未命名插件';
            $subject = '【' . getSiteName() . '】插件审核通过通知';
            $body = "尊敬的{$username}您好，您的插件{$pluginName}已通过审核，已自动上传至插件市场";
            sendMail($plugin['email'], $subject, $body);
        }

        echo json_encode(['success' => true, 'message' => '审核通过，已发布到市场'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 审核拒绝
 */
function handleReject() {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $reason = trim($_POST['reason'] ?? '');
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // 查询插件及作者信息
        $stmt = db()->prepare("SELECT p.*, u.username, u.email FROM plugins p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.market_status = 1");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在或非待审核状态'], JSON_UNESCAPED_UNICODE);
            return;
        }

        db()->prepare("UPDATE plugins SET market_status = 3, updated_at = NOW() WHERE id = ? AND market_status = 1")
            ->execute([$id]);

        // 发送邮件通知
        if (!empty($plugin['email'])) {
            $username = $plugin['username'] ?: '用户';
            $pluginName = $plugin['name'] ?: '未命名插件';
            $reasonText = $reason ?: '未符合平台要求';
            $subject = '【' . getSiteName() . '】插件审核未通过通知';
            $body = "尊敬的{$username}您好，您的插件{$pluginName}不通过审核，原因{$reasonText}";
            sendMail($plugin['email'], $subject, $body);
        }

        echo json_encode(['success' => true, 'message' => '已拒绝发布'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 获取已审核列表（管理员）- 包含已通过(2)和已拒绝(3)
 */
function handleReviewedList() {
    try {
        $stmt = db()->query("SELECT p.*, u.nickname AS publisher_name, u.username
            FROM plugins p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.market_status IN (2, 3)
            ORDER BY p.updated_at DESC");
        $plugins = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $plugins], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 删除插件（管理员）- 从数据库和文件系统中彻底删除
 */
function handleDelete() {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // 查询插件信息
        $stmt = db()->prepare("SELECT * FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 删除插件文件
        if (!empty($plugin['plugin_file'])) {
            $filePath = dirname(__DIR__) . '/uploads/plugins/' . $plugin['plugin_file'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 从数据库删除
        db()->prepare("DELETE FROM plugins WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true, 'message' => '插件已删除'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
