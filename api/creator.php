<?php
/**
 * 创作者申请与审核 API
 * action: apply（提交申请）、review（审核通过/拒绝，仅管理员）
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

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'apply':
        handleApply($userId);
        break;
    case 'review':
        handleReview($userId);
        break;
    case 'list':
        handleList($userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
}

/**
 * 普通用户提交创作者申请
 */
function handleApply($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '请使用POST提交'], JSON_UNESCAPED_UNICODE);
        return;
    }
    verifyCsrf();

    $user = getCurrentUser();
    $role = (int)($user['role'] ?? 1);

    // 已是创作者或管理员无需申请
    if ($role === 0 || $role === 2) {
        echo json_encode(['success' => false, 'message' => '您当前身份无需申请创作者'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $reason = trim($_POST['reason'] ?? '');
    $portfolio = trim($_POST['portfolio'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (mb_strlen($reason) < 5) {
        echo json_encode(['success' => false, 'message' => '申请理由至少5个字'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (mb_strlen($reason) > 1000) {
        echo json_encode(['success' => false, 'message' => '申请理由最多1000字'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (mb_strlen($portfolio) > 2000) {
        echo json_encode(['success' => false, 'message' => '作品最多2000字'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // 检查是否有待审核申请
        $stmt = db()->prepare("SELECT id, status FROM creator_applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $last = $stmt->fetch();

        if ($last && (int)$last['status'] === 0) {
            echo json_encode(['success' => false, 'message' => '您已有申请正在审核中，请耐心等待'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 同步更新用户邮箱
        db()->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $userId]);

        // 插入新申请（若上一条是被拒绝，允许重新提交）
        db()->prepare("INSERT INTO creator_applications (user_id, reason, portfolio, status) VALUES (?, ?, ?, 0)")
            ->execute([$userId, $reason, $portfolio]);
        $appId = (int)db()->lastInsertId();

        // 推送审核通知给管理员（通过机器人私聊）
        try {
            $adminTarget = getSetting('admin_yunhu_id');
            if (empty($adminTarget)) {
                $adminTarget = getSetting('admin_qq');
            }
            if (!empty($adminTarget)) {
                $botStmt = db()->prepare("SELECT * FROM bots WHERE status = 1 LIMIT 1");
                $botStmt->execute();
                $notifyBot = $botStmt->fetch();
                if ($notifyBot) {
                    $userInfo = getCurrentUser();
                    $userName = $userInfo['username'] ?: ($userInfo['nickname'] ?: '用户');
                    $reasonShort = mb_substr(str_replace(["\r","\n"], ' ', $reason), 0, 50);
                    $msg = "🆕 创作者申请通知 #{$appId}\n"
                        . "申请人：{$userName} (UID:{$userId})\n"
                        . "邮箱：{$email}\n"
                        . "理由：{$reasonShort}…\n"
                        . "----------------\n"
                        . "回复「创作者列表」查看全部待审核\n"
                        . "回复「通过创作者 {$appId}」审核通过\n"
                        . "回复「拒绝创作者 {$appId} 原因」审核拒绝";
                    yunhuSendMessage($notifyBot['token'], $adminTarget, $msg, 'text', 'user');
                }
            }

            // 同步推送邮件通知给管理员
            $adminEmail = '482171260@qq.com';
            $mailSubject = '【' . getSiteName() . '】新创作者申请通知 #' . $appId;
            $mailBody = "收到新的创作者申请，请及时审核。\n\n"
                . "申请编号：#{$appId}\n"
                . "申请人：{$userName}\n"
                . "用户UID：{$userId}\n"
                . "邮箱：{$email}\n"
                . "申请理由：{$reasonShort}\n\n"
                . "请登录管理后台进行审核。";
            sendMail($adminEmail, $mailSubject, $mailBody);
        } catch (Exception $e) {
            // 静默处理，不影响用户操作
        }

        echo json_encode(['success' => true, 'message' => '申请已提交，请等待管理员审核'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '提交失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 管理员审核申请：通过 -> 用户role改为2；拒绝 -> 记录备注
 */
function handleReview($userId) {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '仅管理员可审核'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '请使用POST提交'], JSON_UNESCAPED_UNICODE);
        return;
    }
    verifyCsrf();

    $appId = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0); // 1通过 2拒绝
    $note = trim($_POST['note'] ?? '');

    if ($appId <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的申请ID'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (!in_array($status, [1, 2], true)) {
        echo json_encode(['success' => false, 'message' => '无效的审核状态'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($status === 2 && $note === '') {
        echo json_encode(['success' => false, 'message' => '拒绝申请时请填写备注原因'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (mb_strlen($note) > 255) {
        echo json_encode(['success' => false, 'message' => '备注最多255字'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("SELECT * FROM creator_applications WHERE id = ? FOR UPDATE");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) {
            db()->rollBack();
            echo json_encode(['success' => false, 'message' => '申请不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ((int)$app['status'] !== 0) {
            db()->rollBack();
            echo json_encode(['success' => false, 'message' => '该申请已被处理'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $targetUserId = (int)$app['user_id'];

        // 查询用户信息用于发邮件
        $uStmt = db()->prepare("SELECT username, email FROM users WHERE id = ?");
        $uStmt->execute([$targetUserId]);
        $targetUser = $uStmt->fetch();

        // 更新申请状态
        db()->prepare("UPDATE creator_applications 
            SET status = ?, reviewer_id = ?, review_note = ?, reviewed_at = NOW() 
            WHERE id = ?")
            ->execute([$status, $userId, $note, $appId]);

        // 通过则升级用户角色
        if ($status === 1) {
            db()->prepare("UPDATE users SET role = 2 WHERE id = ?")->execute([$targetUserId]);
            $msg = '已通过审核，该用户已升级为创作者';

            // 发送邮件通知
            if ($targetUser && !empty($targetUser['email'])) {
                $username = $targetUser['username'] ?: '用户';
                $subject = '【' . getSiteName() . '】创作者申请通过通知';
                $body = "尊敬的{$username}您好，您的创作者申请已通过，欢迎您开发更多的插件";
                sendMail($targetUser['email'], $subject, $body);
            }
        } else {
            $msg = '已拒绝该申请';

            // 发送邮件通知
            if ($targetUser && !empty($targetUser['email'])) {
                $username = $targetUser['username'] ?: '用户';
                $reasonText = $note ?: '未说明';
                $subject = '【' . getSiteName() . '】创作者申请未通过通知';
                $body = "尊敬的{$username}您好，您的创作者申请未通过，原因如下{$reasonText}";
                sendMail($targetUser['email'], $subject, $body);
            }
        }

        db()->commit();
        echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        try { db()->rollBack(); } catch (Exception $_) {}
        echo json_encode(['success' => false, 'message' => '审核失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 管理员获取待审核/已审核申请列表
 */
function handleList($userId) {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '仅管理员可查看'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $status = isset($_GET['status']) ? (int)$_GET['status'] : -1;

    try {
        $sql = "SELECT a.*, u.username, u.nickname, u.email 
                FROM creator_applications a 
                LEFT JOIN users u ON a.user_id = u.id";
        $params = [];
        if ($status >= 0) {
            $sql .= " WHERE a.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY a.id DESC LIMIT 200";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $list = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $list], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
