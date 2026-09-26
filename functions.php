<?php
/**
 * 白屿云平台 BotAPI 管理系统 - 公共函数库
 */
require_once 'config.php';

// ==================== 会话与认证 ====================

// 检查是否登录
function isLogin() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// 检查是否为管理员（全局唯一的超级管理员，role=0）
function isAdmin() {
    if (!isLogin()) return false;
    $user = getCurrentUser();
    if (!$user) return false;
    if (($user['role'] ?? 1) != 0) return false;
    // 唯一性保护：确保只有 id 最小的 role=0 才是真管理员，避免出现多个
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $minId = (int)db()->query("SELECT COALESCE(MIN(id),0) FROM users WHERE role = 0")->fetchColumn();
        $cached = ($minId > 0 && (int)$user['id'] === $minId);
    } catch (Exception $e) {
        $cached = true;
    }
    return $cached;
}

// 检查是否为创作者（或管理员）
function isCreator() {
    if (!isLogin()) return false;
    $user = getCurrentUser();
    if (!$user) return false;
    $role = (int)($user['role'] ?? 1);
    return $role === 0 || $role === 2;
}

// 是否有权限开发/上传/管理插件（创作者或管理员）
function canDevelopPlugin() {
    return isCreator();
}

// 获取用户角色标签（展示用）
function getRoleLabel($role = null) {
    if ($role === null) {
        $u = getCurrentUser();
        if (!$u) return '用户';
        $role = (int)($u['role'] ?? 1);
    } else {
        $role = (int)$role;
    }
    if ($role === 0) return '管理员';
    if ($role === 2) return '创作者';
    return '普通用户';
}

// 获取用户角色徽章样式
function getRoleBadgeClass($role = null) {
    if ($role === null) {
        $u = getCurrentUser();
        if (!$u) return '';
        $role = (int)($u['role'] ?? 1);
    } else {
        $role = (int)$role;
    }
    if ($role === 0) return 'background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff';
    if ($role === 2) return 'background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff';
    return 'background:#e2e8f0;color:#475569';
}

// 需要登录
function requireLogin() {
    if (!isLogin()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

// 需要管理员权限
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

// 获取当前用户信息
function getCurrentUser() {
    if (!isLogin()) return null;
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// ==================== CSRF 防护 ====================

// 生成 CSRF Token
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 输出 CSRF 隐藏域
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

// 验证 CSRF Token（用于 POST 请求）
function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals(csrfToken(), $token)) {
            error('请求验证失败，请刷新页面重试');
        }
    }
}

// ==================== 密码处理 ====================

// 密码加密
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// 验证密码
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// 生成随机字符串
function randomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

// ==================== 输入处理 ====================

// HTML 转义快捷函数（用于输出时防 XSS）
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// 安全过滤（输入时使用，strip_tags + trim + 转义）
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// 获取原始 POST 数据（不经过 clean 过滤，用于 API 中需要保留原始内容的场景）
function rawPost($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// 获取GET参数
function get($key, $default = '') {
    return isset($_GET[$key]) ? clean($_GET[$key]) : $default;
}

// 获取POST参数
function post($key, $default = '') {
    return isset($_POST[$key]) ? clean($_POST[$key]) : $default;
}

// JSON响应
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 成功响应
function success($msg = '操作成功', $data = []) {
    jsonResponse(['code' => 0, 'msg' => $msg, 'data' => $data]);
}

// 失败响应
function error($msg = '操作失败', $code = 1) {
    jsonResponse(['code' => $code, 'msg' => $msg]);
}

// 获取机器人列表
function getBotList($userId = null) {
    $sql = "SELECT b.*, u.username as owner_name FROM bots b LEFT JOIN users u ON b.owner_id = u.id";
    $params = [];
    if ($userId) {
        $sql .= " WHERE b.user_id = ?";
        $params[] = $userId;
    }
    $sql .= " ORDER BY b.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 获取单个机器人
function getBot($id) {
    $stmt = db()->prepare("SELECT * FROM bots WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// 获取机器人头像URL（优先真实头像，回退DiceBear占位）
function getBotAvatar($bot) {
    if (!empty($bot['avatar'])) {
        return $bot['avatar'];
    }
    // 无头像时用 DiceBear bottts 风格占位（基于机器人名称生成，同一名称始终同一头像）
    $seed = !empty($bot['name']) ? $bot['name'] : ($bot['id'] ?? 'bot');
    return 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($seed);
}

// 从云湖 API 尝试获取机器人头像
function yunhuFetchBotAvatar($token) {
    // 尝试通过 bot/bot-info 接口获取（需要云湖 bot_id，先通过 token 查找）
    // 云湖 API 没有直接通过 token 查 bot-info 的端点，改用 bot/console/my-bots 的思路
    // 实际上，我们可以尝试用 GET 请求 bot edit 接口的返回值
    // 更可靠的方式：通过 curl 向 https://chat-go.jwzhd.com/open-apis/v1/bot/console/bot-info 尝试
    // 但最稳妥：让用户自己填写
    
    // 暂时：尝试访问已知的云湖机器人信息 API
    $url = 'https://chat-go.jwzhd.com/open-apis/v1/bot/console/bot-info';
    $data = ['token' => $token];
    
    $result = curlPostJson($url, $data, 10);
    if (!$result) return null;
    
    $json = json_decode($result, true);
    
    // 尝试多种可能的返回结构
    $avatarUrl = null;
    if (!empty($json['data']['avatarUrl'])) {
        $avatarUrl = $json['data']['avatarUrl'];
    } elseif (!empty($json['data']['avatar'])) {
        $avatarUrl = $json['data']['avatar'];
    } elseif (!empty($json['avatarUrl'])) {
        $avatarUrl = $json['avatarUrl'];
    } elseif (!empty($json['avatar'])) {
        $avatarUrl = $json['avatar'];
    }
    
    return $avatarUrl;
}

// 获取API分类
function getCategories($userId = null) {
    $sql = "SELECT * FROM api_categories";
    $params = [];
    if ($userId) {
        $sql .= " WHERE user_id = ? OR user_id = 0";
        $params[] = $userId;
    }
    $sql .= " ORDER BY sort_order ASC, id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 获取API列表
function getApiList($userId = null, $categoryId = null, $botId = null) {
    $sql = "SELECT a.*, c.name as category_name, c.icon as category_icon FROM apis a 
            LEFT JOIN api_categories c ON a.category_id = c.id WHERE 1=1";
    $params = [];
    if ($userId) {
        $sql .= " AND a.user_id = ?";
        $params[] = $userId;
    }
    if ($categoryId) {
        $sql .= " AND a.category_id = ?";
        $params[] = $categoryId;
    }
    if ($botId) {
        $sql .= " AND a.bot_id = ?";
        $params[] = $botId;
    }
    $sql .= " ORDER BY a.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 获取菜单列表
function getMenuList($userId = null, $botId = null) {
    $sql = "SELECT m.*, b.name as bot_name FROM menus m LEFT JOIN bots b ON m.bot_id = b.id WHERE 1=1";
    $params = [];
    if ($userId) {
        $sql .= " AND m.user_id = ?";
        $params[] = $userId;
    }
    if ($botId) {
        $sql .= " AND m.bot_id = ?";
        $params[] = $botId;
    }
    $sql .= " ORDER BY m.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 获取消息列表
function getMessages($botId, $type = 'private', $limit = 50) {
    $stmt = db()->prepare("SELECT * FROM messages WHERE bot_id = ? AND chat_type = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$botId, $type, $limit]);
    return $stmt->fetchAll();
}

// 获取消息日志
function getMessageLogs($botId = null, $limit = 100) {
    $sql = "SELECT l.*, b.name as bot_name FROM message_logs l LEFT JOIN bots b ON l.bot_id = b.id";
    $params = [];
    if ($botId) {
        $sql .= " WHERE l.bot_id = ?";
        $params[] = $botId;
    }
    $sql .= " ORDER BY l.created_at DESC LIMIT ?";
    $params[] = $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 允许的表名白名单（防止 SQL 注入）
function allowedTable($table) {
    $allowed = ['apis', 'bots', 'users', 'menus', 'messages', 'message_logs', 'api_categories', 'templates', 'cdkeys', 'settings', 'share_codes', 'oauth_accounts', 'plugins', 'creator_applications', 'user_roles'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException("Invalid table name: {$table}");
    }
    return $table;
}

// 验证 WHERE 子句安全性（防止 SQL 注入）
function validateWhereClause($where) {
    if (!is_string($where)) return false;
    $lower = strtolower($where);
    $dangerous = [
        'union', 'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 'truncate',
        'exec', 'execute', 'declare', 'script', '--', ';', '/*', '*/', '@@', 'char(', '0x',
        'information_schema', 'mysql.', 'sys.', 'performance_schema'
    ];
    foreach ($dangerous as $word) {
        if (strpos($lower, $word) !== false) return false;
    }
    if (preg_match('/\b(and|or)\s+[\'\d]/i', $where)) return false;
    return true;
}

// 统计数量（表名使用白名单，WHERE 条件仅支持纯参数化）
function countTable($table, $where = '1=1', $params = []) {
    $table = allowedTable($table);
    if (!validateWhereClause($where)) {
        throw new InvalidArgumentException('Invalid WHERE clause: ' . $where);
    }
    $stmt = db()->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    $row = $stmt->fetch();
    return (int)$row['count'];
}

// 获取系统设置
function getSetting($key) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = db()->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row['value'] ?? '';
    } catch (Exception $e) {
        $cache[$key] = '';
    }
    return $cache[$key];
}

// 写入设置
function setSetting($key, $value) {
    try {
        $stmt = db()->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([$key, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 获取站点名称
function getSiteName() {
    $name = getSetting('site_name');
    return $name ?: SITE_NAME;
}

/**
 * 发送邮件通知（使用后台配置的SMTP）
 * @param string $to 收件邮箱
 * @param string $subject 邮件主题
 * @param string $body 邮件正文
 * @return bool|string 成功返回true，失败返回错误信息
 */
function sendMail($to, $subject, $body) {
    // 检查邮件功能是否启用
    if (getSetting('mail_enabled') !== '1') {
        return false;
    }

    $host       = getSetting('mail_host');
    $port       = (int)(getSetting('mail_port') ?: 465);
    $username   = getSetting('mail_username');
    $password   = getSetting('mail_password');
    $fromName   = getSetting('mail_from_name') ?: getSiteName();
    $fromAddr   = getSetting('mail_from_addr') ?: $username;
    $encryption = getSetting('mail_encryption') ?: 'ssl';

    if (!$host || !$username || !$password) {
        return 'SMTP配置不完整';
    }

    $headers = [
        'From' => mb_encode_mimeheader($fromName) . ' <' . $fromAddr . '>',
        'Reply-To' => $fromAddr,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    $headerStr = '';
    foreach ($headers as $k => $v) {
        $headerStr .= $k . ': ' . $v . "\r\n";
    }

    $encodedSubject = mb_encode_mimeheader($subject);

    // SMTP发送
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]
    ]);
    $fp = stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return '无法连接SMTP服务器';
    }
    stream_set_timeout($fp, 15);

    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '220') { fclose($fp); return false; }

    fwrite($fp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    while ($line = fgets($fp, 515)) { if (substr($line, 3, 1) === ' ') break; }

    if ($encryption === 'tls') {
        fwrite($fp, "STARTTLS\r\n");
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '220') { fclose($fp); return false; }
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    }

    fwrite($fp, "AUTH LOGIN\r\n");
    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '334') { fclose($fp); return false; }

    fwrite($fp, base64_encode($username) . "\r\n");
    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '334') { fclose($fp); return false; }

    fwrite($fp, base64_encode($password) . "\r\n");
    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '235') { fclose($fp); return false; }

    fwrite($fp, "MAIL FROM:<{$fromAddr}>\r\n");
    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '250') { fclose($fp); return false; }

    fwrite($fp, "RCPT TO:<{$to}>\r\n");
    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '250') { fclose($fp); return false; }

    fwrite($fp, "DATA\r\n");
    $response = fgets($fp, 515);
    if (substr($response, 0, 3) !== '354') { fclose($fp); return false; }

    $message = $headerStr . "Subject: {$encodedSubject}\r\nTo: {$to}\r\n\r\n" . $body . "\r\n.\r\n";
    fwrite($fp, $message);
    $response = fgets($fp, 515);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return substr($response, 0, 3) === '250';
}

// 添加消息日志
function addMessageLog($botId, $type, $content, $fromId, $fromName, $extra = '') {
    $stmt = db()->prepare("INSERT INTO message_logs (bot_id, type, content, from_id, from_name, extra, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$botId, $type, $content, $fromId, $fromName, $extra]);
}

// 云湖SDK 发送消息 (Bot Open API: /open-apis/v1/bot/send) — 文档 400-452
function yunhuSendMessage($token, $recvId, $content, $contentType = 'text', $recvType = 'user') {
    // content 键名按 contentType 区分
    $contentKeyMap = [
        'text'     => 'text',
        'markdown' => 'text',
        'html'     => 'text',
        'image'    => 'imageKey',
        'file'     => 'fileKey',
        'video'    => 'videoKey',
    ];
    $key = $contentKeyMap[$contentType] ?? 'text';
    $body = [
        'recvId'      => $recvId,
        'recvType'    => $recvType,
        'contentType' => $contentType,
        'content'     => [$key => $content]
    ];
    $result = curlPostJson(
        'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . urlencode($token),
        $body
    );
    return json_decode($result, true);
}

// 云湖 流式发送消息 — 文档 400-453
function yunhuSendStream($token, $receiveId, $content, $receiveType = 'user') {
    $body = [
        'receiveId'   => $receiveId,
        'receiveType' => $receiveType,
        'content'     => $content,
        'streamId'    => uniqid('stream_', true)
    ];
    $result = curlPostJson(
        'https://www.yhchat.com/open/api/v1/messages/stream',
        $body,
        30,
        ['Authorization: Bearer ' . $token]
    );
    return json_decode($result, true);
}

// 云湖 编辑消息 — 文档 400-454
function yunhuEditMessage($token, $msgId, $recvId, $content, $contentType = 'text', $recvType = 'user') {
    $contentKeyMap = [
        'text'     => 'text',
        'markdown' => 'text',
        'html'     => 'text',
        'image'    => 'imageKey',
        'file'     => 'fileKey',
        'video'    => 'videoKey',
    ];
    $key = $contentKeyMap[$contentType] ?? 'text';
    $body = [
        'msgId'       => $msgId,
        'recvId'      => $recvId,
        'recvType'    => $recvType,
        'contentType' => $contentType,
        'content'     => [$key => $content]
    ];
    $result = curlPostJson(
        'https://chat-go.jwzhd.com/open-apis/v1/bot/edit?token=' . urlencode($token),
        $body
    );
    return json_decode($result, true);
}

// 通用 CURL POST（JSON），$extraHeaders 可追加自定义头
function curlPostJson($url, $data, $timeout = 15, $extraHeaders = []) {
    $ch = curl_init();
    $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// ==================== 图片处理辅助 ====================

// 图片魔数（文件签名）→ 扩展名映射
function yunhuDetectImageExt($data) {
    if (!is_string($data) || strlen($data) < 4) return 'jpg';
    $bin = substr($data, 0, 12);
    $hex = strtolower(bin2hex($bin));
    // JPEG: FF D8 FF
    if (strpos($hex, 'ffd8ff') === 0) return 'jpg';
    // PNG: 89 50 4E 47 0D 0A 1A 0A
    if (strpos($hex, '89504e47') === 0) return 'png';
    // GIF: 47 49 46 38
    if (strpos($hex, '47494638') === 0) return 'gif';
    // WebP: 52 49 46 46 ?? ?? ?? ?? 57 45 42 50
    if (strpos($hex, '52494646') === 0 && strlen($data) > 12) {
        $hex2 = strtolower(bin2hex(substr($data, 8, 4)));
        if ($hex2 === '57454250') return 'webp';
    }
    // BMP: 42 4D
    if (strpos($hex, '424d') === 0) return 'bmp';
    return 'jpg';
}

// 判断响应体是否为图片二进制
function yunhuIsImageBinary($data) {
    if (!is_string($data) || strlen($data) < 8) return false;
    $hex = strtolower(bin2hex(substr($data, 0, 12)));
    return strpos($hex, 'ffd8ff')    === 0   // JPEG
        || strpos($hex, '89504e47')  === 0   // PNG
        || strpos($hex, '47494638')  === 0   // GIF
        || strpos($hex, '424d')      === 0   // BMP
        || (strpos($hex, '52494646') === 0 && strlen($data) > 12
            && strtolower(bin2hex(substr($data, 8, 4))) === '57454250'); // WebP
}

// 从响应体中智能提取图片 URL（支持 JSON、纯文本 URL、二进制）
// 返回 ['type' => 'url'|'binary'|'none', 'data' => string]
function yunhuExtractImage($response) {
    if (!is_string($response) || $response === '') {
        return ['type' => 'none', 'data' => ''];
    }
    // 1) 先尝试 JSON
    $json = json_decode($response, true);
    if (is_array($json)) {
        // 候选字段（按优先级）
        $candidates = ['imageUrl', 'image_url', 'url', 'image', 'data', 'src', 'img'];
        foreach ($candidates as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && $json[$k] !== '') {
                $val = $json[$k];
                // data 可能是 base64 或 URL
                if ($k === 'data' && !preg_match('#^https?://#i', $val)) continue;
                if (preg_match('#^https?://#i', $val)) {
                    return ['type' => 'url', 'data' => $val];
                }
            }
        }
        // data 嵌套对象里的 url
        if (isset($json['data']) && is_array($json['data'])) {
            foreach (['url', 'image', 'src', 'img'] as $k) {
                if (!empty($json['data'][$k]) && preg_match('#^https?://#i', $json['data'][$k])) {
                    return ['type' => 'url', 'data' => $json['data'][$k]];
                }
            }
        }
    }
    // 2) 判断是否图片二进制
    if (yunhuIsImageBinary($response)) {
        return ['type' => 'binary', 'data' => $response];
    }
    // 3) 纯文本，尝试用正则提取 URL
    if (preg_match('#(https?://[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp|bmp))#i', $response, $m)) {
        return ['type' => 'url', 'data' => $m[1]];
    }
    // 4) 没有图片
    return ['type' => 'none', 'data' => $response];
}

// 把图片二进制保存到 uploads/images/，返回可访问的 URL
// 失败返回空字符串
function yunhuSaveImageLocally($imageData, $prefix = 'img') {
    $r = yunhuSaveImageLocallyWithStatus($imageData, $prefix);
    return $r['ok'] ? $r['url'] : '';
}

// 带详细状态的本地保存函数
// 返回: ['ok'=>bool, 'url'=>string, 'error'=>string, 'path'=>string]
function yunhuSaveImageLocallyWithStatus($imageData, $prefix = 'img') {
    $res = ['ok' => false, 'url' => '', 'error' => '', 'path' => ''];
    if (!is_string($imageData) || $imageData === '') {
        $res['error'] = '图片数据为空';
        return $res;
    }
    $ext = yunhuDetectImageExt($imageData);
    $filename = $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
    $dir = __DIR__ . '/uploads/images';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            $res['error'] = "无法创建目录: {$dir} (检查权限)";
            return $res;
        }
    }
    if (!is_writable($dir)) {
        $res['error'] = "目录不可写: {$dir} (chmod 0755)";
        return $res;
    }
    $path = $dir . '/' . $filename;
    $bytes = file_put_contents($path, $imageData);
    if ($bytes === false) {
        $lastErr = error_get_last();
        $res['error'] = "file_put_contents 失败: " . ($lastErr['message'] ?? '未知错误');
        return $res;
    }
    if ($bytes === 0) {
        $res['error'] = "file_put_contents 写入了 0 字节（数据可能为空）";
        return $res;
    }
    $res['ok']   = true;
    $res['url']  = SITE_URL . '/uploads/images/image.php?f=' . $filename;
    $res['path'] = $path;
    return $res;
}

// 通用二进制本地保存（用于视频/文件失败备份）
// 返回: ['ok'=>bool, 'url'=>string, 'error'=>string, 'path'=>string]
function yunhuSaveBinaryLocallyWithStatus($binData, $ext, $prefix = 'bin', $relDir = 'uploads/files') {
    $res = ['ok' => false, 'url' => '', 'error' => '', 'path' => ''];
    if (!is_string($binData) || $binData === '') { $res['error'] = '数据为空'; return $res; }
    $ext = strtolower(trim($ext)) ?: 'bin';
    $filename = $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
    $dir = __DIR__ . '/' . trim($relDir, '/');
    if (!is_dir($dir)) { if (!mkdir($dir, 0755, true) && !is_dir($dir)) { $res['error'] = "无法创建目录: {$dir}"; return $res; } }
    if (!is_writable($dir)) { $res['error'] = "目录不可写: {$dir}"; return $res; }
    $path = $dir . '/' . $filename;
    $bytes = file_put_contents($path, $binData);
    if ($bytes === false) { $res['error'] = "file_put_contents 失败"; return $res; }
    if ($bytes === 0) { $res['error'] = "写入了 0 字节"; return $res; }
    $res['ok']   = true;
    $res['path'] = $path;
    // URL 用 uploads/files/file.php?f=xxx 中转（避免直接暴露磁盘路径）
    $res['url']  = SITE_URL . '/' . trim($relDir, '/') . '/file.php?f=' . $filename;
    return $res;
}

// 通过 image.php 中转暴露（用于把外部 URL 提前下载到本地）
// 返回本地可访问 URL
function yunhuCacheRemoteImage($remoteUrl, $prefix = 'img') {
    if (empty($remoteUrl) || !preg_match('#^https?://#i', $remoteUrl)) return '';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $remoteUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($data === false || $data === '' || $code >= 400) return '';
    return yunhuSaveImageLocally($data, $prefix);
}

// ========== 云湖媒体上传（图片/视频/文件）==========
// V2 重构版 - 见下方 yunhuUploadV2
// ========== 云湖媒体上传 v2（带详细错误 + 多策略 fallback）==========
//
// 4 种上传策略依次尝试，每种都打详细日志方便排查：
//   1) multipart/form-data 字段名 file
//   2) multipart/form-data 字段名 image
//   3) multipart/form-data 字段名 media
//   4) raw body  + Content-Type: image/png 等
//
// 任一成功立即返回（不浪费带宽）。失败时详细原因（HTTP码、curl错误、云湖code/msg）返回
// 由调用方决定是否写业务日志。
function _yunhuUploadMedia($token, $fileData, $endpoint, $keyName, $filename = 'file', $mimeType = 'application/octet-stream', $strategies = null) {
    $res = ['ok' => false, 'key' => '', 'error' => '', 'debug' => ''];
    if (empty($fileData) || !is_string($fileData)) { $res['error'] = '文件数据为空'; return $res; }

    $tmp = tempnam(sys_get_temp_dir(), 'yh_up_');
    if ($tmp === false) { $res['error'] = '无法创建临时文件: ' . sys_get_temp_dir(); return $res; }
    $writeResult = file_put_contents($tmp, $fileData);
    if ($writeResult === false || !is_file($tmp) || filesize($tmp) === 0) { 
        @unlink($tmp); 
        $res['error'] = '临时文件写入失败或为空'; 
        return $res; 
    }
    $tmpSize = filesize($tmp);

    // 单次尝试（闭包）
    $tryOnce = function ($strategy) use ($token, $endpoint, $tmp, $mimeType, $filename, $tmpSize) {
        $url = $endpoint . '?token=' . urlencode($token);
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => 'YHBot/1.0',
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 90,
        ];
        if ($strategy === 'raw') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = file_get_contents($tmp);
            $opts[CURLOPT_HTTPHEADER] = ['Content-Type: ' . $mimeType, 'Expect: '];
        } else {
            $opts[CURLOPT_POST] = true;
            if (class_exists('CURLFile')) {
                $opts[CURLOPT_POSTFIELDS] = [$strategy => new CURLFile($tmp, $mimeType, $filename)];
            } else {
                $opts[CURLOPT_POSTFIELDS] = [$strategy => '@' . $tmp . ';type=' . $mimeType . ';filename=' . $filename];
            }
            $opts[CURLOPT_HTTPHEADER] = ['Expect: '];
        }
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        return ['http' => $code, 'body' => (string)$resp, 'err' => $err, 'errno' => $errno, 'size' => $tmpSize];
    };

    // 默认策略表
    if ($strategies === null) {
        $strategies = ['file', 'image', 'media', 'raw'];
    }
    $allDebugs = [];
    $lastDebug = ''; $lastError = '';
    foreach ($strategies as $idx => $strategy) {
        // 写一行"开始尝试"到错误日志，方便排查卡死位置
        $logPath = __DIR__ . '/../uploads/upload-debug.log';
        if (is_writable(dirname($logPath)) || !file_exists($logPath)) {
            error_log(sprintf("[YH-UPLOAD] try %d/%d strategy=%s size=%d", $idx+1, count($strategies), $strategy, $tmpSize), 3, $logPath);
        }
        $r = $tryOnce($strategy);
        if (is_writable(dirname($logPath)) || !file_exists($logPath)) {
            error_log(sprintf("[YH-UPLOAD] done %d/%d strategy=%s HTTP=%d curlErr=%s body前200=%s", $idx+1, count($strategies), $strategy, $r['http'], $r['err'], substr($r['body'], 0, 200)), 3, $logPath);
        }
        $dbg = '[策略 ' . ($idx+1) . '/' . count($strategies) . " {$strategy}] HTTP={$r['http']} 大小={$r['size']} curlErr={$r['err']} body前200=" . substr($r['body'], 0, 200);
        $allDebugs[] = $dbg;
        $lastDebug = $dbg;
        if ($r['http'] === 0 || $r['http'] >= 500) { $lastError = "策略 {$strategy} 网络失败: HTTP={$r['http']} curlErr={$r['err']}"; continue; }
        $json = json_decode($r['body'], true);
        if (!is_array($json)) { $lastError = "策略 {$strategy} 响应非 JSON: " . substr($r['body'], 0, 200); continue; }
        if (isset($json['code']) && $json['code'] != 1) {
            $lastError = "策略 {$strategy} 云湖拒绝: code=" . ($json['code'] ?? '?') . ' msg=' . ($json['msg'] ?? '');
            if (($json['code'] ?? 0) == 1003) break; // token 错误就别再试
            continue;
        }
        // 提取 key
        $key = '';
        if (!empty($json[$keyName]) && is_string($json[$keyName])) $key = $json[$keyName];
        elseif (!empty($json['data'][$keyName]) && is_string($json['data'][$keyName])) $key = $json['data'][$keyName];
        elseif (!empty($json['data']['key']) && is_string($json['data']['key'])) $key = $json['data']['key'];
        elseif (!empty($json['data']['url']) && is_string($json['data']['url'])) $key = basename(parse_url($json['data']['url'], PHP_URL_PATH)) ?: $json['data']['url'];
        elseif (!empty($json['url']) && is_string($json['url'])) $key = basename(parse_url($json['url'], PHP_URL_PATH)) ?: $json['url'];
        if (!empty($key)) {
            unlink($tmp);
            $res['ok']    = true;
            $res['key']   = $key;
            $res['debug'] = "成功: 策略 {$strategy} | " . $dbg;
            return $res;
        }
        $lastError = "策略 {$strategy} code=1 但无 {$keyName}: " . substr($r['body'], 0, 300);
    }
    unlink($tmp);
    $res['error'] = $lastError ?: '未知失败';
    $res['debug'] = implode(' || ', $allDebugs);
    return $res;
}

function _yunhuMarkUploadError($res) {
    // 把详细错误塞到全局，方便 webhook 写入业务日志
    $GLOBALS['_yunhu_last_upload_error'] = $res;
}
function yunhuGetLastUploadError() {
    return $GLOBALS['_yunhu_last_upload_error'] ?? null;
}

// 云湖上传图片 → imageKey
function yunhuUploadImage($token, $imageData, $filename = 'image.jpg') {
    $ext = yunhuDetectImageExt($imageData);
    $mimeMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp'];
    $mime = $mimeMap[$ext] ?? 'image/jpeg';
    $endpoint = 'https://chat-go.jwzhd.com/open-apis/v1/image/upload';
    // 图片用 'image' 字段（云湖官方）
    $r = _yunhuUploadMedia($token, $imageData, $endpoint, 'imageKey', $filename, $mime, ['image', 'file', 'media', 'raw']);
    if (!$r['ok']) _yunhuMarkUploadError($r);
    return $r['key'];
}

// 云湖上传视频 → videoKey
function yunhuUploadVideo($token, $videoData, $filename = 'video.mp4') {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeMap = [
        'mp4'=>'video/mp4','avi'=>'video/x-msvideo','mov'=>'video/quicktime',
        'mkv'=>'video/x-matroska','webm'=>'video/webm','flv'=>'video/x-flv','wmv'=>'video/x-ms-wmv',
    ];
    $mime = $mimeMap[$ext] ?? 'video/mp4';
    $endpoint = 'https://chat-go.jwzhd.com/open-apis/v1/video/upload';
    // 视频用 'video' 字段（云湖官方：VideoUploader.ts 用 form.append('video', blob, filename)）
    $r = _yunhuUploadMedia($token, $videoData, $endpoint, 'videoKey', $filename, $mime, ['video', 'file', 'media', 'raw']);
    if (!$r['ok']) {
        _yunhuMarkUploadError($r);
        // 失败时本地备份（方便用户下载调试），路径放到 $r['debug'] 里
        $local = yunhuSaveBinaryLocallyWithStatus($videoData, $ext ?: 'mp4', 'video', 'uploads/videos');
        if ($local['ok']) {
            $r['debug'] .= " || 本地备份URL: " . $local['url'];
        }
        _yunhuMarkUploadError($r);  // 重新设回更新后的 debug
    }
    return $r['key'];
}

// 云湖上传文件 → fileKey
function yunhuUploadFile($token, $fileData, $filename = 'file.bin') {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeMap = [
        'pdf'=>'application/pdf','doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip'=>'application/zip','rar'=>'application/x-rar-compressed',
        '7z'=>'application/x-7z-compressed','txt'=>'text/plain','csv'=>'text/csv',
        'json'=>'application/json','xml'=>'application/xml','html'=>'text/html',
        'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pptm'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    $endpoint = 'https://chat-go.jwzhd.com/open-apis/v1/file/upload';
    // 文件用 'file' 字段（云湖官方：FileUploader.ts 用 form.append('file', blob, filename)）
    $r = _yunhuUploadMedia($token, $fileData, $endpoint, 'fileKey', $filename, $mime, ['file', 'media', 'raw']);
    if (!$r['ok']) _yunhuMarkUploadError($r);
    return $r['key'];
}

// 通用 CURL 请求
function curlRequest($url, $method = 'GET', $headers = [], $body = null, $timeout = 10) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($body !== null && $method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    return [
        'body'         => $response,
        'http_code'    => $httpCode,
        'content_type' => $contentType,
        'error'        => $error,
    ];
}

/**
 * 统一替换 API/模板/回复中的变量
 *
 * 支持的变量：
 *   {0} {1} ...                 用户传入的第 N 个参数
 *   {参数}                      首个参数（无时取去掉指令后的内容）
 *   {全部参数}                   所有参数以空格连接
 *   {user_id} / {用户ID}        发送者 ID
 *   {chat_id} / {群ID}          聊天/群 ID
 *   {bot_id}                    机器人 ID
 *   {user_name} / {用户}        发送者名称
 *   {bot_name}                  机器人名称
 *   {command}                   触发指令
 *   {time} / {当前时间}         当前时间 Y-m-d H:i:s
 *   {date}                      当前日期 Y-m-d
 *   {时间戳}                    当前时间戳
 *   {result} / {原始返回}       API 原始返回内容
 *   {换行}                      换行符 \n
 *   {文本取右#关键词}           取 API 返回中关键词右侧内容
 *   {文本取左#关键词}           取 API 返回中关键词左侧内容
 *   {文本取中#前#后}            取 API 返回中两关键词之间的内容
 *   {文本替换#原文#新文}        替换 API 返回中的文本
 *   {自适应}                    从 API 返回的图片 URL 获取尺寸，返回 "#宽度px #高度px"
 *
 * @param string $text
 * @param array $context
 * @return string
 */
function replaceApiVariables($text, $context, $urlEncode = false) {
    if (!is_string($text) || $text === '') return $text;

    $args = $context['args'] ?? [];
    if (!is_array($args)) $args = [];

    $userId    = $context['user_id'] ?? $context['from_id'] ?? '';
    $chatId    = $context['chat_id'] ?? '';
    $botId     = $context['bot_id'] ?? '';
    $userName  = $context['user_name'] ?? $context['from_name'] ?? '';
    $botName   = $context['bot_name'] ?? '';
    $command   = $context['command'] ?? '';
    $content   = $context['content'] ?? '';
    $response  = $context['response'] ?? '';
    $result    = $context['result'] ?? $response;

    $applyEncode = function ($v) use ($urlEncode) {
        return $urlEncode ? rawurlencode((string)$v) : (string)$v;
    };

    // 基础变量
    $replacements = [
        '{user_id}'   => $applyEncode($userId),
        '{用户ID}'     => $applyEncode($userId),
        '{chat_id}'   => $applyEncode($chatId),
        '{群ID}'       => $applyEncode($chatId),
        '{bot_id}'    => $applyEncode($botId),
        '{user_name}' => $userName,
        '{用户}'       => $userName,
        '{bot_name}'  => $botName,
        '{command}'   => $command,
        '{time}'      => date('Y-m-d H:i:s'),
        '{当前时间}'   => date('Y-m-d H:i:s'),
        '{date}'      => date('Y-m-d'),
        '{时间戳}'     => (string)time(),
        '{result}'    => $result,
        '{原始返回}'   => $response,
        '{换行}'       => "\n",
    ];

    // 数字参数 {0} {1} ...
    foreach ($args as $idx => $arg) {
        $replacements['{' . $idx . '}'] = $applyEncode($arg);
    }

    // {参数}：优先取第一个参数，没有时取去掉指令后的剩余内容
    $param = $args[0] ?? '';
    if ($param === '' && $command !== '' && $content !== '') {
        $param = trim(preg_replace('/^' . preg_quote($command, '/') . '\s*/u', '', $content));
    }
    $replacements['{参数}']     = $applyEncode($param);
    $replacements['{全部参数}'] = $applyEncode(implode(' ', $args));

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);

    $responseText = (string)$response;

    // 文本处理函数（基于 API 原始返回），URL 场景不处理且不应编码
    if (!$urlEncode) {
        $text = preg_replace_callback('/\{文本取右#([^#}]+)\}/u', function ($m) use ($responseText) {
            $keyword = $m[1];
            $pos = mb_strpos($responseText, $keyword);
            if ($pos === false) return '';
            return mb_substr($responseText, $pos + mb_strlen($keyword));
        }, $text);

        $text = preg_replace_callback('/\{文本取左#([^#}]+)\}/u', function ($m) use ($responseText) {
            $keyword = $m[1];
            $pos = mb_strpos($responseText, $keyword);
            if ($pos === false) return '';
            return mb_substr($responseText, 0, $pos);
        }, $text);

        $text = preg_replace_callback('/\{文本取中#([^#}]+)#([^#}]+)\}/u', function ($m) use ($responseText) {
            $left  = $m[1];
            $right = $m[2];
            $lpos = mb_strpos($responseText, $left);
            if ($lpos === false) return '';
            $start = $lpos + mb_strlen($left);
            $rpos = mb_strpos($responseText, $right, $start);
            if ($rpos === false) return '';
            return mb_substr($responseText, $start, $rpos - $start);
        }, $text);

        $text = preg_replace_callback('/\{文本替换#([^#}]+)#([^#}]+)\}/u', function ($m) use ($responseText) {
            return str_replace($m[1], $m[2], $responseText);
        }, $text);

        // {自适应}：尝试从返回内容中提取图片 URL 并获取尺寸
        if (mb_strpos($text, '{自适应}') !== false) {
            $imageInfo = yunhuExtractImage($responseText);
            $adaptive  = '';
            if ($imageInfo['type'] === 'url') {
                $imgData = file_get_contents($imageInfo['data']);
                if ($imgData !== false && function_exists('getimagesizefromstring')) {
                    $size = getimagesizefromstring($imgData);
                    if ($size !== false) {
                        $adaptive = '#' . $size[0] . 'px #' . $size[1] . 'px';
                    }
                }
            }
            $text = str_replace('{自适应}', $adaptive, $text);
        }
    }

    return $text;
}

// 分页函数
function pagination($total, $page, $perPage = 20) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'offset' => $offset
    ];
}

// 友好的时间显示
function timeAgo($time) {
    $diff = time() - strtotime($time);
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y-m-d', strtotime($time));
}

/**
 * 设备检测 - 判断是否为移动端
 * @return bool
 */
function isMobile() {
    if (isset($_COOKIE['device_mode'])) {
        return $_COOKIE['device_mode'] === 'mobile';
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobiles = [
        'Mobile', 'Android', 'iPhone', 'iPod', 'iPad',
        'Windows Phone', 'BlackBerry', 'webOS', 'Opera Mini',
        'IEMobile', 'Symbian', 'Nokia'
    ];
    foreach ($mobiles as $m) {
        if (stripos($ua, $m) !== false) return true;
    }
    if (preg_match('/(ipad|tablet|playbook|silk)/i', $ua)) return true;
    return false;
}

/**
 * 获取模板类型：desktop / mobile
 * @return string
 */
function getTemplateType() {
    return isMobile() ? 'mobile' : 'desktop';
}

/**
 * 加载对应设备的头部模板（直接引入，保持变量作用域）
 * 用法: require __DIR__ . '/../includes/load_header.php'; 
 * 或:  require 'includes/load_header.php';
 */

// 获取机器人统计数据
function getBotStats($botId) {
    return [
        'api_count' => countTable('apis', 'bot_id = ?', [$botId]),
        'msg_count' => countTable('messages', 'bot_id = ?', [$botId]),
        'today_count' => countTable('messages', 'bot_id = ? AND DATE(created_at) = DATE(NOW())', [$botId])
    ];
}
