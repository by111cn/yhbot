<?php
/**
 * 彩虹聚合登录接口
 * 文档：https://login.az0.cn/doc.php
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleOAuthLogin();
        break;
    case 'bind':
        handleOAuthBind();
        break;
    case 'unbind':
        handleOAuthUnbind();
        break;
    case 'callback':
        handleOAuthCallback();
        break;
    default:
        jsonError('无效操作');
}

/**
 * 获取并校验聚合登录配置
 */
function getOAuthConfig() {
    $row = db()->prepare("SELECT `value` FROM settings WHERE `key` = 'oauth_config' AND user_id = 0");
    $row->execute();
    $cfg = $row->fetch();
    if (!$cfg) {
        return null;
    }
    $data = json_decode($cfg['value'], true);
    if (!is_array($data) || empty($data['enabled'])) {
        return null;
    }
    if (empty($data['connect_url']) || empty($data['appid']) || empty($data['appkey'])) {
        return null;
    }
    $data['types'] = array_filter(array_map('trim', explode(',', $data['types'] ?? '')));
    if (empty($data['types'])) {
        return null;
    }
    return $data;
}

/**
 * 发起登录跳转
 */
function handleOAuthLogin() {
    $_SESSION['oauth_mode'] = 'login';
    doOAuthRedirect('login');
}

/**
 * 发起绑定跳转（需要已登录）
 */
function handleOAuthBind() {
    if (!isLogin()) {
        jsonError('请先登录');
    }
    $_SESSION['oauth_mode'] = 'bind';
    doOAuthRedirect('bind');
}

/**
 * 执行聚合登录/绑定跳转
 */
function doOAuthRedirect($mode) {
    $type = $_GET['type'] ?? '';
    if (!$type) {
        jsonError('缺少登录方式参数');
    }

    $cfg = getOAuthConfig();
    if (!$cfg) {
        jsonError('聚合登录未启用或配置不完整');
    }

    if (!in_array($type, $cfg['types'])) {
        jsonError('当前登录方式未启用');
    }

    $callback = SITE_URL . '/api/oauth.php?action=callback';
    $url = rtrim($cfg['connect_url'], '?') . '?act=login'
        . '&appid=' . urlencode($cfg['appid'])
        . '&appkey=' . urlencode($cfg['appkey'])
        . '&type=' . urlencode($type)
        . '&redirect_uri=' . urlencode($callback);

    $resp = httpGet($url, 15);
    if ($resp === false) {
        jsonError('登录接口请求失败');
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        $preview = $resp;
        if (empty(trim($preview))) {
            $preview = '(空响应)';
        } else {
            // 去掉 HTML 标签，截短展示
            $preview = html_entity_decode(strip_tags($preview));
            if (mb_strlen($preview) > 200) {
                $preview = mb_substr($preview, 0, 200) . '…';
            }
        }
        error_log('Rainbow OAuth login invalid response: ' . substr($resp, 0, 500));
        jsonError('登录接口返回异常：' . $preview);
    }
    if ($data['code'] != 0) {
        $errMsg = $data['msg'] ?? '';
        if (empty($errMsg)) {
            $errMsg = '聚合平台未返回错误详情';
        }
        error_log('Rainbow OAuth login failed: code=' . $data['code'] . ' msg=' . $errMsg . ' raw=' . substr($resp, 0, 500));
        jsonError('登录接口返回错误：' . $errMsg . ' (code=' . $data['code'] . ')');
    }
    if (empty($data['url'])) {
        jsonError('登录接口未返回跳转地址');
    }

    header('Location: ' . $data['url']);
    exit;
}

/**
 * 解绑 OAuth 账号
 */
function handleOAuthUnbind() {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('请求方式错误');
    }
    verifyCsrf();
    if (!isLogin()) {
        jsonError('请先登录');
    }
    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['qq', 'wx'], true)) {
        jsonError('不支持的解绑类型');
    }
    $stmt = db()->prepare("DELETE FROM oauth_accounts WHERE user_id = ? AND provider = ?");
    $stmt->execute([$_SESSION['user_id'], $type]);
    success('解绑成功');
}

/**
 * 回调处理
 */
function handleOAuthCallback() {
    $type = $_GET['type'] ?? '';
    $code = $_GET['code'] ?? '';

    if (!$type || !$code) {
        die('回调参数缺失');
    }

    $cfg = getOAuthConfig();
    if (!$cfg) {
        die('聚合登录未启用或配置不完整');
    }

    $url = rtrim($cfg['connect_url'], '?') . '?act=callback'
        . '&appid=' . urlencode($cfg['appid'])
        . '&appkey=' . urlencode($cfg['appkey'])
        . '&type=' . urlencode($type)
        . '&code=' . urlencode($code);

    $resp = httpGet($url, 20);
    if ($resp === false) {
        die('获取用户信息失败');
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        $preview = $resp;
        if (empty(trim($preview))) {
            $preview = '(空响应)';
        } else {
            $preview = html_entity_decode(strip_tags($preview));
            if (mb_strlen($preview) > 200) {
                $preview = mb_substr($preview, 0, 200) . '…';
            }
        }
        error_log('Rainbow OAuth callback invalid response: ' . substr($resp, 0, 500));
        die('获取用户信息失败：' . $preview);
    }
    if ($data['code'] != 0) {
        $errMsg = $data['msg'] ?? '';
        if (empty($errMsg)) {
            $errMsg = '聚合平台未返回错误详情';
        }
        error_log('Rainbow OAuth callback failed: code=' . $data['code'] . ' msg=' . $errMsg . ' raw=' . substr($resp, 0, 500));
        die('获取用户信息失败：' . $errMsg . ' (code=' . $data['code'] . ')');
    }

    $openid   = $data['social_uid'] ?? '';
    $nickname = $data['nickname']   ?? '';
    $avatar   = $data['faceimg']    ?? '';
    $unionid  = $data['unionid']    ?? null;

    if (!$openid) {
        die('未获取到用户标识');
    }

    $provider = $type;
    $now = date('Y-m-d H:i:s');
    $mode = $_SESSION['oauth_mode'] ?? 'login';

    if ($mode === 'bind') {
        // 绑定模式：必须已登录
        if (!isLogin()) {
            die('绑定失败：登录状态已过期，请重新登录后再试');
        }
        $currentUserId = (int)$_SESSION['user_id'];

        // 查找该 OAuth 账号是否已被其他用户绑定
        $stmt = db()->prepare("SELECT user_id FROM oauth_accounts WHERE provider = ? AND openid = ?");
        $stmt->execute([$provider, $openid]);
        $existing = $stmt->fetch();

        if ($existing && (int)$existing['user_id'] !== $currentUserId) {
            die('该' . ($provider === 'qq' ? 'QQ' : '微信') . '账号已绑定到其他用户');
        }

        if ($existing) {
            // 更新当前用户的绑定信息
            $up = db()->prepare("UPDATE oauth_accounts SET nickname = ?, avatar = ?, unionid = ?, expires_at = NULL WHERE provider = ? AND openid = ?");
            $up->execute([$nickname, $avatar, $unionid, $provider, $openid]);
        } else {
            // 删除同一 provider 的旧绑定（避免一个用户绑定多个同平台账号）
            db()->prepare("DELETE FROM oauth_accounts WHERE user_id = ? AND provider = ?")
                ->execute([$currentUserId, $provider]);
            // 新增绑定
            $bind = db()->prepare("INSERT INTO oauth_accounts (user_id, provider, openid, unionid, nickname, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $bind->execute([$currentUserId, $provider, $openid, $unionid, $nickname, $avatar, $now]);
        }

        // 清理模式标记
        unset($_SESSION['oauth_mode']);
        header('Location: ' . SITE_URL . '/pages/profile.php');
        exit;
    }

    // 登录/注册模式
    // 查找是否已绑定
    $stmt = db()->prepare("SELECT user_id FROM oauth_accounts WHERE provider = ? AND openid = ?");
    $stmt->execute([$provider, $openid]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = (int)$existing['user_id'];
        // 更新昵称、头像
        $up = db()->prepare("UPDATE oauth_accounts SET nickname = ?, avatar = ?, unionid = ?, expires_at = NULL WHERE provider = ? AND openid = ?");
        $up->execute([$nickname, $avatar, $unionid, $provider, $openid]);
    } else {
        // 创建新用户
        $username = 'user_' . strtolower(randomString(8));
        for ($i = 0; $i < 20; $i++) {
            $chk = db()->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if (!$chk->fetch()) {
                break;
            }
            $username = 'user_' . strtolower(randomString(8)) . $i;
        }

        $password = randomString(16);
        $email    = $username . '@oauth.local';

        $ins = db()->prepare("INSERT INTO users (username, password, email, role, status, nickname, avatar, created_at, updated_at) VALUES (?, ?, ?, 1, 1, ?, ?, ?, ?)");
        $ins->execute([$username, hashPassword($password), $email, $nickname, $avatar, $now, $now]);
        $userId = (int)db()->lastInsertId();

        $bind = db()->prepare("INSERT INTO oauth_accounts (user_id, provider, openid, unionid, nickname, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $bind->execute([$userId, $provider, $openid, $unionid, $nickname, $avatar, $now]);
    }

    // 登录会话
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['status'])) {
        die('账号异常或已被禁用');
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['show_announcement'] = true;

    // 清理模式标记
    unset($_SESSION['oauth_mode']);
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

/**
 * 发送 GET 请求
 */
function httpGet($url, $timeout = 15) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; RainbowOAuth/1.0)');
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) {
            error_log('Rainbow OAuth HTTP error: ' . $err);
            return false;
        }
        return $resp;
    }

    // 兼容没有 curl 的环境
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => $timeout,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; RainbowOAuth/1.0)\r\n",
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    return file_get_contents($url, false, $ctx);
}

function jsonError($msg) {
    echo json_encode(['code' => 1, 'msg' => $msg]);
    exit;
}
