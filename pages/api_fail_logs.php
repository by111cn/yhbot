<?php
/**
 * 白屿云平台 BotAPI - API 失败日志查看器
 */
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = 'API错误日志';
$activePage = 'message_logs';
$userId = $_SESSION['user_id'];

$selectedBotId = (int)($_GET['bot_id'] ?? 0);

// 读取本地日志文件
$logFile = dirname(__DIR__) . '/logs/api_fail.log';
$logLines = [];
if (file_exists($logFile)) {
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // 如果指定了 bot_id，过滤
    if ($selectedBotId) {
        $needle = "bot={$selectedBotId}(";
        foreach ($allLines as $line) {
            if (strpos($line, $needle) !== false) {
                $logLines[] = $line;
            }
        }
    } else {
        $logLines = $allLines;
    }
    // 取最近 200 条，倒序
    $logLines = array_reverse(array_slice($logLines, -200));
}

// 清空日志
if (isset($_POST['action']) && $_POST['action'] === 'clear_log') {
    if (file_exists($logFile)) {
        @unlink($logFile);
    }
    $logLines = [];
    $success = '错误日志已清空';
}

// 同时获取数据库中的失败记录
$dbFailLogs = [];
if ($selectedBotId) {
    $stmt = db()->prepare("SELECT * FROM message_logs WHERE bot_id = ? AND extra LIKE '%API失败%' ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$selectedBotId]);
    $dbFailLogs = $stmt->fetchAll();
}

require '../includes/load_header.php';
?>

<style>
.fail-log-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 20px; }
.fail-log-header { padding: 14px 20px; background: linear-gradient(135deg, #fef2f2, #fee2e2); border-bottom: 1px solid #fecaca; display: flex; align-items: center; gap: 10px; }
.fail-log-header .icon-danger { font-size: 20px; color: #ef4444; }
.fail-log-header h4 { margin: 0; font-size: 15px; color: #991b1b; }
.fail-log-body { padding: 16px 20px; }
.fail-item { padding: 12px 16px; border-bottom: 1px solid var(--border-light); font-size: 13px; font-family: 'SF Mono', 'Consolas', 'Monaco', monospace; line-height: 1.6; word-break: break-all; }
.fail-item:last-child { border-bottom: none; }
.fail-item .time { color: var(--text-muted); font-size: 11px; }
.fail-item .reason { color: #ef4444; font-weight: 600; }
.fail-item .url { color: #6366f1; }
.fail-item .http { color: #f59e0b; font-weight: 600; }
.fail-item .curl-er { color: #dc2626; }
.fail-item .resp { color: var(--text-muted); max-height: 60px; overflow: hidden; }
.empty-fail { text-align: center; padding: 40px 20px; color: var(--text-muted); }
.db-fail-item { padding: 10px 14px; border-left: 3px solid #ef4444; background: #fef2f2; border-radius: 6px; margin-bottom: 8px; font-size: 13px; }
.db-fail-item .f-time { font-size: 11px; color: var(--text-muted); }
.db-fail-item .f-content { font-size: 13px; color: var(--text); }
.db-fail-item .f-extra { font-size: 12px; color: var(--text-muted); font-family: monospace; white-space: pre-wrap; word-break: break-all; margin-top: 4px; }
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>API 失败诊断日志</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>

        <div style="display:flex; gap:12px; align-items:center; margin-bottom:16px; flex-wrap:wrap;">
            <a href="message_logs.php?bot_id=<?php echo $selectedBotId; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> 返回消息日志
            </a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('确定清空本地错误日志文件吗？数据库日志不会被删除。')">
                <input type="hidden" name="action" value="clear_log">
                <button type="submit" class="btn btn-danger"><i class="fas fa-eraser"></i> 清空文件日志</button>
            </form>
        </div>

        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <!-- 左侧：本地日志文件 -->
            <div style="flex:1; min-width:350px;">
                <div class="fail-log-card">
                    <div class="fail-log-header">
                        <span class="icon-danger"><i class="fas fa-file-code"></i></span>
                        <div>
                            <h4>本地日志文件 <code style="font-size:12px;font-weight:400">/logs/api_fail.log</code></h4>
                            <small style="color:var(--text-muted)">最近 200 条（最新在上）</small>
                        </div>
                    </div>
                    <div class="fail-log-body">
                        <?php if (empty($logLines)): ?>
                            <div class="empty-fail">
                                <i class="fas fa-check-circle" style="font-size:32px;color:#22c55e;display:block;margin-bottom:8px;"></i>
                                暂无 API 调用失败记录 - 太棒了！
                            </div>
                        <?php else: ?>
                            <?php foreach ($logLines as $line): ?>
                                <?php
                                // 解析日志行
                                $ts = '';
                                if (preg_match('/^\[(.+?)\]/', $line, $m)) { $ts = $m[1]; }
                                $reason = '';
                                if (preg_match('/\[YH-API-FAIL\].*? (网络错误.*?)(\||$)/', $line, $m)) $reason = $m[1];
                                elseif (preg_match('/\[YH-API-FAIL\].*? (HTTP\d+)(\||$)/', $line, $m)) $reason = $m[1];
                                elseif (preg_match('/\[YH-API-FAIL\].*? (服务器错误.*?)(\||$)/', $line, $m)) $reason = $m[1];
                                elseif (preg_match('/\[YH-API-FAIL\].*? (接口.*?)(\||$)/', $line, $m)) $reason = $m[1];
                                elseif (preg_match('/\[YH-API-FAIL\].*? (客户端错误.*?)(\||$)/', $line, $m)) $reason = $m[1];
                                elseif (preg_match('/\[YH-API-FAIL\].*? (API返回.*?)(\||$)/', $line, $m)) $reason = $m[1];
                                ?>
                                <div class="fail-item">
                                    <?php if ($ts): ?><div class="time">时间: <?php echo h($ts); ?></div><?php endif; ?>
                                    <div>
                                        <?php if ($reason): ?><span class="reason">失败原因: <?php echo h($reason); ?></span><br><?php endif; ?>
                                        <?php echo preg_replace_callback('/(URL=)([^|]+)/', function($m){ return 'URL=<span class="url">'.htmlspecialchars($m[2]).'</span>'; }, preg_replace_callback('/(HTTP=)(\d+)/', function($m){ return 'HTTP=<span class="http">'.$m[2].'</span>'; }, htmlspecialchars($line))); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 右侧：数据库中的失败记录 -->
            <div style="flex:1; min-width:350px;">
                <div class="fail-log-card">
                    <div class="fail-log-header" style="background:linear-gradient(135deg, #fff7ed, #ffedd5);border-bottom-color:#fed7aa;">
                        <span class="icon-danger" style="color:#f59e0b;"><i class="fas fa-database"></i></span>
                        <div>
                            <h4 style="color:#9a3412;">数据库失败记录</h4>
                            <small style="color:var(--text-muted)">最近 50 条 API 调用失败</small>
                        </div>
                    </div>
                    <div class="fail-log-body">
                        <?php if (empty($dbFailLogs)): ?>
                            <div class="empty-fail">
                                <i class="fas fa-inbox" style="font-size:32px;color:var(--text-muted);display:block;margin-bottom:8px;"></i>
                                数据库中也暂无失败记录
                            </div>
                        <?php else: ?>
                            <?php foreach ($dbFailLogs as $log): ?>
                                <div class="db-fail-item">
                                    <div class="f-time"><i class="far fa-clock"></i> <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></div>
                                    <div class="f-content"><?php echo nl2br(h($log['content'])); ?></div>
                                    <?php if (!empty($log['extra'])): ?>
                                        <div class="f-extra"><?php echo h($log['extra']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="card-header"><h4>常见 API 失败原因及解决方法</h4></div>
            <div class="card-body">
                <table style="width:100%;font-size:13px;">
                    <tr style="background:var(--bg);">
                        <th style="padding:8px 12px;text-align:left;width:120px;">失败类型</th>
                        <th style="padding:8px 12px;text-align:left;">可能原因</th>
                        <th style="padding:8px 12px;text-align:left;width:250px;">解决方法</th>
                    </tr>
                    <tr><td style="padding:8px 12px;"><span class="log-tag type-private">CurlErr</span></td><td style="padding:8px 12px;">DNS解析失败、SSL证书不受信任、目标服务器不可达</td><td style="padding:8px 12px;">检查API地址是否有效；在API管理中确认URL可被正常访问</td></tr>
                    <tr><td style="padding:8px 12px;"><span class="log-tag type-group">HTTP 404</span></td><td style="padding:8px 12px;">API地址错误、接口路径已变更</td><td style="padding:8px 12px;">点击日志中的URL链接检查是否可用</td></tr>
                    <tr><td style="padding:8px 12px;"><span class="log-tag type-all">HTTP 403</span></td><td style="padding:8px 12px;">API需要鉴权（Token/Key）但未提供</td><td style="padding:8px 12px;">在API管理的Headers中添加认证头</td></tr>
                    <tr><td style="padding:8px 12px;"><span class="log-tag status-sent">HTTP 500</span></td><td style="padding:8px 12px;">目标API服务器内部错误</td><td style="padding:8px 12px;">联系API提供方检查服务状态</td></tr>
                    <tr><td style="padding:8px 12px;"><span class="log-tag status-received">返回空白</span></td><td style="padding:8px 12px;">API返回空响应；变量占位符{0}{1}未被正确替换</td><td style="padding:8px 12px;">检查URL中变量替换是否正确，用实际参数测试</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
