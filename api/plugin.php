<?php
/**
 * BotAPI 插件 API
 * 处理插件的 CRUD、文件上传、开关、设置保存
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// 禁止错误输出，确保只返回 JSON
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

// 权限检查
if (!isLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}
// 写操作权限分级：
// - save（新建插件）仅创作者/管理员
// - toggle/delete/save_settings/bind_bot 允许所有用户（管理已安装的插件）
$creatorOnlyActions = ['save'];
if (in_array($action, $creatorOnlyActions, true) && !canDevelopPlugin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '仅创作者或管理员可创建插件，请先申请创作者身份'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$action = $_GET['action'] ?? '';

// 插件上传目录
define('PLUGIN_UPLOAD_DIR', __DIR__ . '/../uploads/plugins/');

switch ($action) {
    case 'list':
        handleList($userId);
        break;
    case 'save':
        handleSave($userId);
        break;
    case 'toggle':
        handleToggle($userId);
        break;
    case 'delete':
        handleDelete($userId);
        break;
    case 'save_settings':
        handleSaveSettings($userId);
        break;
    case 'bind_bot':
        handleBindBot($userId);
        break;
    case 'get':
        handleGet($userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
}

/**
 * 获取插件列表
 */
function handleList($userId) {
    try {
        $stmt = db()->prepare("SELECT id, name, author, version, `desc`, command_list, enabled, bot_id, market_status 
            FROM plugins WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        $plugins = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $plugins
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 保存插件（新建/编辑）
 */
function handleSave($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $botId = isset($_POST['bot_id']) ? intval($_POST['bot_id']) : 0;
    $name = trim($_POST['name'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $version = trim($_POST['version'] ?? '1.0.0');
    $desc = trim($_POST['desc'] ?? '');
    $commandList = $_POST['command_list'] ?? '[]';
    $settingsSchema = trim($_POST['settings_schema'] ?? '');
    $existingFile = $_POST['existing_file'] ?? '';
    $botIdSelect = isset($_POST['bot_id_select']) ? intval($_POST['bot_id_select']) : 0;

    // 如果 bot_id 为 0，使用选择器中的值
    if ($botId === 0 && $botIdSelect > 0) {
        $botId = $botIdSelect;
    }

    // 验证必填字段
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => '插件名称不能为空'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // 验证 settings_schema 是否为合法 JSON
    if (!empty($settingsSchema)) {
        $parsed = json_decode($settingsSchema, true);
        if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => '设置页面设计器 JSON 格式错误'], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    // 验证 command_list 是否为合法 JSON
    if (!empty($commandList)) {
        $parsed = json_decode($commandList, true);
        if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => '命令列表 JSON 格式错误'], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    // 处理文件上传
    $filePath = '';
    $pluginCode = '';
    if (isset($_FILES['plugin_file_upload']) && $_FILES['plugin_file_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['plugin_file_upload'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // 只允许 .php 文件
        if ($ext !== 'php') {
            echo json_encode(['success' => false, 'message' => '只允许上传 .php 格式的插件文件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 限制文件大小 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => '文件大小不能超过 5MB'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 创建目录（如果不存在）
        if (!is_dir(PLUGIN_UPLOAD_DIR)) {
            mkdir(PLUGIN_UPLOAD_DIR, 0755, true);
        }

        // 检查目录是否可写
        if (!is_writable(PLUGIN_UPLOAD_DIR)) {
            echo json_encode(['success' => false, 'message' => '插件上传目录不可写，请联系管理员'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 生成唯一文件名（移除原文件名中的.php扩展名，避免双重扩展名）
        $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
        $baseName = preg_replace('/\.php$/i', '', $baseName);
        $newName = 'plugin_' . $userId . '_' . time() . '_' . $baseName . '.php';
        $destPath = PLUGIN_UPLOAD_DIR . $newName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => '文件上传失败，请检查目录权限'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 删除旧文件（如果有）
        if (!empty($existingFile)) {
            $oldPath = PLUGIN_UPLOAD_DIR . $existingFile;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $pluginCode = file_get_contents($destPath);
        $filePath = $newName;
    }

    try {
        if ($id > 0) {
            // 编辑现有插件
            $stmt = db()->prepare("SELECT id, user_id, plugin_file, plugin_code FROM plugins WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 权限检查
            if ($existing['user_id'] != $userId && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => '无权限编辑此插件'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 如果没有上传新文件，保留原有文件内容和路径
            if (empty($filePath)) {
                $filePath = $existing['plugin_file'];
                $pluginCode = $existing['plugin_code'];
            } elseif (!empty($existing['plugin_file']) && $filePath !== $existing['plugin_file']) {
                // 上传了新文件且与旧文件不同，删除旧文件
                $oldFilePath = PLUGIN_UPLOAD_DIR . $existing['plugin_file'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            db()->prepare("UPDATE plugins SET 
                bot_id = ?, name = ?, author = ?, version = ?, `desc` = ?, 
                command_list = ?, plugin_code = ?, plugin_file = ?, 
                settings_schema = ?, updated_at = NOW() 
                WHERE id = ?")
                ->execute([$botId, $name, $author, $version, $desc, $commandList, 
                           $pluginCode, $filePath, $settingsSchema, $id]);

        } else {
            // 新建插件——必须上传文件
            if (empty($filePath)) {
                echo json_encode(['success' => false, 'message' => '请上传插件文件'], JSON_UNESCAPED_UNICODE);
                return;
            }
            db()->prepare("INSERT INTO plugins 
                (user_id, bot_id, name, author, version, `desc`, command_list, 
                 plugin_code, plugin_file, settings_schema, enabled) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
                ->execute([$userId, $botId, $name, $author, $version, $desc, 
                           $commandList, $pluginCode, $filePath, $settingsSchema]);
        }

        echo json_encode(['success' => true, 'message' => '插件保存成功'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '保存失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 切换插件启用/停用
 */
function handleToggle($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT id, user_id FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 权限检查
        if ($plugin['user_id'] != $userId && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => '无权限操作此插件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        db()->prepare("UPDATE plugins SET enabled = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$enabled, $id]);

        echo json_encode(['success' => true, 'message' => $enabled ? '插件已启用' : '插件已停用'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 删除插件
 */
function handleDelete($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT id, user_id, plugin_file FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 权限检查
        if ($plugin['user_id'] != $userId && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => '无权限删除此插件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 删除关联的上传文件
        if (!empty($plugin['plugin_file'])) {
            $filePath = PLUGIN_UPLOAD_DIR . $plugin['plugin_file'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        db()->prepare("DELETE FROM plugins WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true, 'message' => '插件已删除'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 保存插件设置
 */
function handleSaveSettings($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $settingsValues = $_POST['settings_values'] ?? '{}';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // 验证 JSON 格式
    $parsed = json_decode($settingsValues, true);
    if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => '设置参数 JSON 格式错误'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT id, user_id FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch();

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => '插件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($plugin['user_id'] != $userId && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => '无权限修改此插件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        db()->prepare("UPDATE plugins SET settings_values = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$settingsValues, $id]);

        echo json_encode(['success' => true, 'message' => '设置保存成功'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '保存失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 绑定插件到机器人
 */
function handleBindBot($userId) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $botId = isset($_POST['bot_id']) ? intval($_POST['bot_id']) : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的插件 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = db()->prepare("SELECT id, user_id FROM plugins WHERE id = ?");
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

        // 验证 bot_id 属于当前用户（0 表示不绑定）
        if ($botId > 0) {
            $botStmt = db()->prepare("SELECT id FROM bots WHERE id = ? AND user_id = ?");
            $botStmt->execute([$botId, $userId]);
            if (!$botStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => '机器人不存在或无权限'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        db()->prepare("UPDATE plugins SET bot_id = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$botId, $id]);

        echo json_encode(['success' => true, 'message' => '机器人绑定成功'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 获取单个插件详情
 */
function handleGet($userId) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
            echo json_encode(['success' => false, 'message' => '无权限查看此插件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['success' => true, 'data' => $plugin], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
