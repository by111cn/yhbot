<?php
/**
 * 消息自动清理计划任务
 * 用途：自动清理消息记录（私聊 + 群聊 + 消息日志）
 * 用法：
 *   php cron_cleanup_messages.php           # 清理5天前的消息
 *   php cron_cleanup_messages.php --all     # 清空全部消息
 * 建议：通过 Linux cron 或宝塔计划任务每天执行一次
 *       示例：0 3 * * * php /www/wwwroot/yh.by111.cn/cron_cleanup_messages.php >> /www/wwwroot/yh.by111.cn/logs/cleanup.log 2>&1
 */

define('RETENTION_DAYS', 5);

$isWeb = php_sapi_name() === 'cli' || php_sapi_name() === 'cgi-fcgi' ? false : true;

if (!$isWeb) {
    require_once __DIR__ . '/config.php';
    $clearAll = isset($argv[1]) && $argv[1] === '--all';
    run_cleanup($clearAll);
    exit;
}

// ============ Web 模式 ============
define('CRON_MODE', true);
require_once __DIR__ . '/config.php';

$loginError = '';
$justLoggedOut = isset($_GET['logout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $loginError = '请输入账号和密码';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $loginError = '账号或密码错误';
        } elseif ((int)($user['role'] ?? 1) !== 0) {
            $loginError = '该账号无管理员权限';
        } else {
            $_SESSION['cleanup_admin'] = true;
            $_SESSION['cleanup_admin_user'] = $user['username'];
        }
    }
}

if ($justLoggedOut) {
    unset($_SESSION['cleanup_admin'], $_SESSION['cleanup_admin_user']);
}

$isAdmin = !empty($_SESSION['cleanup_admin']);

if (!$isAdmin) {
    ?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息清理 - 管理员验证</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
        .login-box{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);padding:40px 36px;max-width:380px;width:100%;margin:20px}
        .logo{text-align:center;margin-bottom:24px}
        .logo-icon{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:24px;margin-bottom:12px}
        .logo h2{font-size:18px;color:#1f2937}
        .logo p{font-size:13px;color:#6b7280;margin-top:4px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:13px;color:#374151;margin-bottom:6px;font-weight:500}
        .form-group input{width:100%;padding:11px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;transition:all .2s;outline:none}
        .form-group input:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.1)}
        .error{background:#fef2f2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;border:1px solid #fecaca}
        .tip{background:#fef3c7;color:#92400e;padding:10px 14px;border-radius:8px;font-size:12px;margin-bottom:20px;border:1px solid #fde68a;line-height:1.5}
        .btn-login{width:100%;padding:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:all .2s}
        .btn-login:hover{opacity:.9;transform:translateY(-1px)}
        .back{display:block;text-align:center;margin-top:16px;color:#6b7280;font-size:13px;text-decoration:none}
        .back:hover{color:#374151}
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <div class="logo-icon">🔐</div>
        <h2>管理员验证</h2>
        <p>消息清理操作需要管理员权限</p>
    </div>
    <?php if ($loginError): ?>
        <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
    <?php endif; ?>
    <div class="tip">
        <strong>⚠️ 此操作将永久删除消息记录，不可恢复。</strong><br>
        请确保您是管理员后再继续。
    </div>
    <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>管理员账号</label>
            <input type="text" name="username" required autocomplete="username" placeholder="请输入管理员账号">
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required autocomplete="current-password" placeholder="请输入密码">
        </div>
        <button type="submit" class="btn-login">验证并进入</button>
    </form>
    <a class="back" href="<?php echo SITE_URL; ?>">← 返回首页</a>
</div>
</body>
</html>
<?php exit;
}

// 已登录管理员
$adminUser = $_SESSION['cleanup_admin_user'] ?? '';
$ran = false;
$output = '';
$ranMode = '';

$postAction = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    if ($postAction === 'execute') {
        run_cleanup(false);
        $ranMode = 'timed';
    } elseif ($postAction === 'execute_all') {
        run_cleanup(true);
        $ranMode = 'all';
    }
    $output = ob_get_clean();
    $ran = true;
}

$previewTimed = isset($_GET['preview']);
$previewAll = isset($_GET['preview_all']);
if ($previewTimed) {
    ob_start();
    define('DRY_RUN', true);
    run_cleanup(false);
    $output = ob_get_clean();
    $ran = true;
    $ranMode = 'preview_timed';
} elseif ($previewAll) {
    ob_start();
    define('DRY_RUN', true);
    run_cleanup(true);
    $output = ob_get_clean();
    $ran = true;
    $ranMode = 'preview_all';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息清理</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f3f4f6;color:#1f2937;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .box{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:32px;max-width:600px;width:100%;margin:20px}
        h1{font-size:18px;margin-bottom:8px}
        .tip{color:#6b7280;font-size:13px;margin-bottom:24px;line-height:1.6}
        .btns{display:flex;gap:10px;flex-wrap:wrap}
        .btn{padding:10px 24px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-weight:500;text-decoration:none;display:inline-block}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-danger:hover{background:#dc2626}
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-warning:hover{background:#d97706}
        .btn-secondary{background:#e5e7eb;color:#374151}
        .btn-secondary:hover{background:#d1d5db}
        .output{background:#1f2937;color:#e5e7eb;padding:16px;border-radius:8px;font-family:monospace;font-size:12px;line-height:1.6;max-height:400px;overflow:auto;white-space:pre-wrap;margin-top:20px}
        .back{display:block;margin-top:20px;color:#3b82f6;font-size:13px;text-decoration:none}
        .back:hover{text-decoration:underline}
        .header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb}
        .admin-info{font-size:13px;color:#6b7280}
        .admin-info strong{color:#374151}
        .section{margin-bottom:20px}
        .section-title{font-size:14px;font-weight:600;color:#374151;margin-bottom:8px}
        .section-desc{font-size:12px;color:#6b7280;margin-bottom:12px;line-height:1.5}
        .action-group{background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;border:1px solid #e5e7eb}
        .action-group .title{font-size:14px;font-weight:500;color:#1f2937;margin-bottom:4px}
        .action-group .desc{font-size:12px;color:#6b7280;margin-bottom:12px}
        .action-group .buttons{display:flex;gap:8px;flex-wrap:wrap}
        .mode-badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:500;margin-left:8px}
        .mode-timed{background:#dbeafe;color:#1e40af}
        .mode-all{background:#fee2e2;color:#991b1b}
    </style>
</head>
<body>
<div class="box">
    <div class="header-bar">
        <div class="admin-info">管理员：<strong><?php echo htmlspecialchars($adminUser); ?></strong></div>
        <a class="back" href="?logout=1">退出</a>
    </div>

    <?php if ($ran): ?>
        <h1>
            <?php
            if ($ranMode === 'preview_timed' || $ranMode === 'preview_all') {
                echo '预览结果';
            } elseif ($ranMode === 'all') {
                echo '清空结果';
            } else {
                echo '清理结果';
            }
            ?>
        </h1>
        <div class="output"><?php echo htmlspecialchars($output); ?></div>
        <div class="btns" style="margin-top:16px">
            <?php if ($ranMode === 'preview_timed'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('确认删除5天前的消息？此操作不可撤销！')">
                    <input type="hidden" name="action" value="execute">
                    <button type="submit" class="btn btn-danger">确认执行清理（5天前）</button>
                </form>
            <?php elseif ($ranMode === 'preview_all'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('⚠️ 确认清空全部消息？此操作不可撤销！')">
                    <input type="hidden" name="action" value="execute_all">
                    <button type="submit" class="btn btn-danger">确认清空全部</button>
                </form>
            <?php endif; ?>
            <a class="btn btn-secondary" href="cron_cleanup_messages.php">返回</a>
        </div>
    <?php else: ?>
        <h1>消息自动清理</h1>
        <p class="tip">选择清理模式，操作不可撤销。建议先预览。</p>

        <div class="action-group">
            <div class="title">🕐 按时间清理 <span class="mode-badge mode-timed">保留 <?php echo RETENTION_DAYS; ?> 天</span></div>
            <div class="desc">删除 <?php echo RETENTION_DAYS; ?> 天前的消息记录（私聊、群聊、消息日志），近期消息保留。</div>
            <div class="buttons">
                <a class="btn btn-secondary" href="?preview=1">预览（不删除）</a>
                <form method="post" style="display:inline" onsubmit="return confirm('确认清理5天前的消息？此操作不可撤销！')">
                    <input type="hidden" name="action" value="execute">
                    <button type="submit" class="btn btn-danger">执行清理</button>
                </form>
            </div>
        </div>

        <div class="action-group">
            <div class="title">🔥 清空全部消息 <span class="mode-badge mode-all">危险</span></div>
            <div class="desc"><strong>⚠️ 将清空所有消息记录（私聊、群聊、消息日志），不可恢复！</strong></div>
            <div class="buttons">
                <a class="btn btn-secondary" href="?preview_all=1">预览（不删除）</a>
                <form method="post" style="display:inline" onsubmit="return confirm('⚠️ 确认清空全部消息？此操作不可撤销！')">
                    <input type="hidden" name="action" value="execute_all">
                    <button type="submit" class="btn btn-warning">清空全部</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <a class="back" href="<?php echo SITE_URL; ?>">← 返回管理面板</a>
</div>
</body>
</html>
<?php exit; ?>

<?php
// ========== 清理逻辑 ==========

function run_cleanup(bool $clearAll = false): void {
    $startTime = microtime(true);

    cleanup_log('========== 消息清理任务开始 ==========');

    try {
        $dryRun = defined('DRY_RUN') && DRY_RUN;

        if ($clearAll) {
            cleanup_log('模式：清空全部消息' . ($dryRun ? '（预览模式）' : ''));
        } else {
            cleanup_log('模式：删除 ' . RETENTION_DAYS . ' 天前的消息' . ($dryRun ? '（预览模式）' : ''));
            $cutoffDate = date('Y-m-d H:i:s', time() - RETENTION_DAYS * 86400);
            cleanup_log('清理截止时间：' . $cutoffDate);
        }

        // 1. messages 表
        if ($dryRun) {
            if ($clearAll) {
                $msgCount = (int)db()->query("SELECT COUNT(*) FROM messages")->fetchColumn();
                cleanup_log("[预览] messages 表将清空全部 {$msgCount} 条消息");
            } else {
                $stmt = db()->prepare("SELECT COUNT(*) FROM messages WHERE created_at <= ?");
                $stmt->execute([$cutoffDate]);
                $msgCount = (int)$stmt->fetchColumn();
                cleanup_log("[预览] messages 表将删除 {$msgCount} 条消息");
            }
        } else {
            if ($clearAll) {
                db()->query("TRUNCATE TABLE messages");
                cleanup_log("messages 表已清空");
            } else {
                $stmt = db()->prepare("DELETE FROM messages WHERE created_at <= ?");
                $stmt->execute([$cutoffDate]);
                $msgDeleted = $stmt->rowCount();
                cleanup_log("messages 表删除 {$msgDeleted} 条消息");
            }

            if ($clearAll) {
                cleanup_log("  私聊消息：0 条");
                cleanup_log("  群聊消息：0 条");
            } else {
                $stats = db()->query("SELECT chat_type, COUNT(*) as cnt FROM messages GROUP BY chat_type")->fetchAll();
                foreach ($stats as $row) {
                    $typeLabel = $row['chat_type'] === 'group' ? '群聊' : '私聊';
                    cleanup_log("  剩余 {$typeLabel} 消息：{$row['cnt']} 条");
                }
            }
        }

        // 2. message_logs 表
        if ($dryRun) {
            if ($clearAll) {
                $logCount = (int)db()->query("SELECT COUNT(*) FROM message_logs")->fetchColumn();
                cleanup_log("[预览] message_logs 表将清空全部 {$logCount} 条日志");
            } else {
                $stmt = db()->prepare("SELECT COUNT(*) FROM message_logs WHERE created_at <= ?");
                $stmt->execute([$cutoffDate]);
                $logCount = (int)$stmt->fetchColumn();
                cleanup_log("[预览] message_logs 表将删除 {$logCount} 条日志");
            }
        } else {
            if ($clearAll) {
                db()->query("TRUNCATE TABLE message_logs");
                cleanup_log("message_logs 表已清空");
            } else {
                $stmt = db()->prepare("DELETE FROM message_logs WHERE created_at <= ?");
                $stmt->execute([$cutoffDate]);
                $logDeleted = $stmt->rowCount();
                cleanup_log("message_logs 表删除 {$logDeleted} 条日志");
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        cleanup_log("清理完成，耗时 {$elapsed}s");

    } catch (Exception $e) {
        cleanup_log('清理任务异常: ' . $e->getMessage());
    }

    cleanup_log('========== 消息清理任务结束 ==========');
}

function cleanup_log(string $msg): void {
    $time = date('Y-m-d H:i:s');
    $line = "[{$time}] {$msg}" . PHP_EOL;
    echo $line;
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/cleanup_messages.log', $line, FILE_APPEND);
}
