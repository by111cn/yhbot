<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '消息日志';
$activePage = 'message_logs';
$userId = $_SESSION['user_id'];

if (isset($_POST['action']) && $_POST['action'] === 'clear') {
    $botId = (int)($_POST['bot_id'] ?? 0);
    if ($botId) {
        $stmt = db()->prepare("DELETE FROM message_logs WHERE bot_id=? AND bot_id IN (SELECT id FROM bots WHERE user_id=?)");
        $stmt->execute([$botId, $userId]);
        $success = '日志已清空';
    }
}

$bots = getBotList($userId);
$selectedBotId = (int)get('bot_id', 0);
$filter = $_GET['filter'] ?? 'all';

$logs = [];
$selectedBot = null;
if ($selectedBotId) {
    foreach ($bots as $b) {
        if ((int)$b['id'] === $selectedBotId) {
            $selectedBot = $b;
            break;
        }
    }
    $stmt = db()->prepare("SELECT * FROM message_logs WHERE bot_id=? ORDER BY created_at DESC LIMIT 200");
    $stmt->execute([$selectedBotId]);
    $logs = $stmt->fetchAll();
}

/**
 * 解析日志类型
 * 返回: ['kind' => 'plugin_error|api_error|menu_api_error|plugin_file_error|info|send|receive', 'label' => '...', 'icon' => '...']
 */
function parseLogKind($log) {
    $type = $log['type'] ?? 'info';
    $content = $log['content'] ?? '';
    $extra = $log['extra'] ?? '';

    // 插件文件错误
    if (strpos($content, '插件文件不存在') !== false || strpos($content, '插件文件内容为空') !== false) {
        return ['kind' => 'plugin_file_error', 'label' => '插件文件错误', 'icon' => 'fa-file-circle-xmark', 'color' => '#dc2626'];
    }
    // 插件执行错误
    if (strpos($content, '插件执行错误') !== false) {
        return ['kind' => 'plugin_error', 'label' => '插件执行错误', 'icon' => 'fa-puzzle-piece', 'color' => '#7c3aed'];
    }
    // 菜单API调用失败
    if (strpos($content, '菜单API调用失败') !== false) {
        return ['kind' => 'menu_api_error', 'label' => '菜单API失败', 'icon' => 'fa-list-check', 'color' => '#ea580c'];
    }
    // API调用失败（发送了失败提示消息）
    if (strpos($content, 'API调用失败') !== false || (strpos($extra, '失败原因:') !== false && strpos($extra, 'API:') !== false)) {
        return ['kind' => 'api_error', 'label' => 'API调用失败', 'icon' => 'fa-triangle-exclamation', 'color' => '#dc2626'];
    }
    // API图片/视频/文件失败
    if (strpos($content, 'API图片失败') !== false || strpos($content, 'API视频失败') !== false || strpos($content, 'API文件失败') !== false) {
        return ['kind' => 'api_media_error', 'label' => '媒体获取失败', 'icon' => 'fa-image', 'color' => '#dc2626'];
    }
    // 命中菜单
    if (strpos($content, '命中菜单') !== false) {
        return ['kind' => 'info', 'label' => '菜单命中', 'icon' => 'fa-list', 'color' => '#0891b2'];
    }
    // 未匹配
    if (strpos($content, '未匹配') !== false) {
        return ['kind' => 'info', 'label' => '未匹配', 'icon' => 'fa-circle-question', 'color' => '#6b7280'];
    }
    // 管理员审核
    if (strpos($content, '审核通过') !== false || strpos($content, '审核拒绝') !== false || strpos($extra, '管理员审核') !== false || strpos($content, '管理员绑定') !== false) {
        return ['kind' => 'info', 'label' => '管理员操作', 'icon' => 'fa-user-shield', 'color' => '#0891b2'];
    }
    // 接收
    if ($type === 'receive') {
        return ['kind' => 'receive', 'label' => '接收', 'icon' => 'fa-arrow-down', 'color' => '#10b981'];
    }
    // 发送
    if ($type === 'send') {
        return ['kind' => 'send', 'label' => '发送', 'icon' => 'fa-arrow-up', 'color' => '#3b82f6'];
    }
    // error 类型
    if ($type === 'error') {
        return ['kind' => 'error', 'label' => '错误', 'icon' => 'fa-circle-exclamation', 'color' => '#dc2626'];
    }
    return ['kind' => 'info', 'label' => '信息', 'icon' => 'fa-info-circle', 'color' => '#6b7280'];
}

/**
 * 判断是否为错误日志
 */
function isErrorLog($log) {
    $kind = parseLogKind($log);
    return in_array($kind['kind'], ['plugin_error', 'plugin_file_error', 'api_error', 'menu_api_error', 'api_media_error', 'error']);
}

/**
 * 格式化 extra 字段：解析 "字段: 值" 格式并返回数组
 */
function parseExtraFields($extra) {
    if (empty($extra)) return [];
    $fields = [];
    $lines = explode("\n", $extra);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        // 堆栈跟踪部分
        if ($line === '堆栈:' || strpos($line, '堆栈:') === 0) {
            $fields[] = ['key' => '堆栈', 'value' => ltrim(substr($line, 4)), 'is_stack' => true];
            continue;
        }
        // 以 #数字 开头的堆栈行
        if (preg_match('/^#\d+/', $line)) {
            // 追加到上一个堆栈字段
            if (!empty($fields) && isset($fields[count($fields)-1]['is_stack'])) {
                $fields[count($fields)-1]['value'] .= "\n" . $line;
                continue;
            }
        }
        // "字段: 值" 格式
        if (preg_match('/^([^:：]+)[：:]\s*(.*)$/', $line, $m)) {
            $key = trim($m[1]);
            $value = trim($m[2]);
            $fields[] = ['key' => $key, 'value' => $value];
        } else {
            // 不符合格式的行作为附加信息
            $fields[] = ['key' => '', 'value' => $line];
        }
    }
    return $fields;
}

require '../includes/load_header.php';
?>

<style>
.log-filter{display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;padding:16px 20px;background:var(--bg);border-radius:var(--radius-xs)}
.filter-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.filter-tab{padding:6px 14px;border-radius:var(--radius-full);font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--card-bg);color:var(--text-muted);transition:all .2s;user-select:none}
.filter-tab:hover{border-color:var(--primary);color:var(--primary)}
.filter-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.filter-tab .count{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:10px;background:rgba(0,0,0,0.1);font-size:11px}
.filter-tab.active .count{background:rgba(255,255,255,0.3)}
.log-list{display:flex;flex-direction:column;gap:0}
.log-item{display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border-light);transition:all var(--transition-fast);border-left:3px solid transparent;padding-left:12px;margin-left:-12px}
.log-item:hover{background:var(--bg)}
.log-item:last-child{border-bottom:none}
.log-avatar{width:40px;height:40px;border-radius:50%;background:var(--bg);border:2px solid var(--border);flex-shrink:0;overflow:hidden}
.log-avatar img{width:100%;height:100%;object-fit:cover}
.log-content{flex:1;min-width:0}
.log-header{display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap}
.log-header h5{font-size:14px;font-weight:600;margin:0}
.log-tags{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.log-tag{font-size:11px;padding:3px 10px;border-radius:var(--radius-full);font-weight:600;white-space:nowrap;display:inline-flex;align-items:center;gap:4px}
.log-tag.kind-badge{color:#fff}
.log-meta{font-size:12px;color:var(--text-muted);margin-bottom:8px;display:flex;gap:16px;flex-wrap:wrap}
.log-meta span{display:flex;align-items:center;gap:4px}
.log-text{font-size:14px;line-height:1.65;color:var(--text);word-break:break-word}
.log-time{font-size:12px;color:var(--text-muted);margin-top:8px;display:flex;align-items:center;gap:4px}

/* 错误日志特殊样式 */
.log-item.is-error{background:linear-gradient(90deg,rgba(254,242,242,0.5) 0%,transparent 100%);border-left-color:#ef4444}
.log-item.is-error.plugin-err{border-left-color:#7c3aed;background:linear-gradient(90deg,rgba(245,243,255,0.6) 0%,transparent 100%)}
.log-item.is-error.menu-err{border-left-color:#ea580c;background:linear-gradient(90deg,rgba(255,247,237,0.6) 0%,transparent 100%)}

/* 诊断信息面板 */
.diag-toggle{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--primary);cursor:pointer;margin-top:8px;padding:4px 12px;border-radius:var(--radius-full);background:#e0e7ff;transition:all .2s;user-select:none;font-weight:600}
.diag-toggle:hover{background:#cdd6fe}
.log-item.is-error .diag-toggle{background:#fee2e2;color:#dc2626}
.log-item.is-error .diag-toggle:hover{background:#fecaca}
.log-item.is-error.plugin-err .diag-toggle{background:#ede9fe;color:#7c3aed}
.log-item.is-error.plugin-err .diag-toggle:hover{background:#ddd6fe}
.log-item.is-error.menu-err .diag-toggle{background:#ffedd5;color:#ea580c}
.log-item.is-error.menu-err .diag-toggle:hover{background:#fed7aa}

.diag-panel{margin-top:10px;border-radius:8px;overflow:hidden;border:1px solid var(--border);display:none}
.diag-panel.show{display:block;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.diag-panel-header{padding:8px 14px;background:#fef2f2;color:#991b1b;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px;border-bottom:1px solid #fecaca}
.log-item.is-error.plugin-err .diag-panel-header{background:#f5f3ff;color:#6b21a8;border-bottom-color:#ddd6fe}
.log-item.is-error.menu-err .diag-panel-header{background:#fff7ed;color:#9a3412;border-bottom-color:#fed7aa}
.log-item:not(.is-error) .diag-panel-header{background:#f0f9ff;color:#075985;border-bottom-color:#bae6fd}
.diag-panel-body{padding:10px 14px;background:#fff;font-size:12px;line-height:1.6}
.diag-field{display:flex;gap:8px;padding:4px 0;border-bottom:1px dashed #f1f5f9;align-items:flex-start}
.diag-field:last-child{border-bottom:none}
.diag-field-key{flex-shrink:0;min-width:90px;font-weight:600;color:#475569;font-family:monospace;font-size:11px;padding-top:1px}
.diag-field-value{flex:1;word-break:break-all;color:#1e293b;font-family:monospace;font-size:11px;white-space:pre-wrap}
/* 高亮字段 */
.diag-field.key-fail .diag-field-value{color:#dc2626;font-weight:700}
.diag-field.key-http .diag-field-value{color:#dc2626;font-weight:700}
.diag-field.key-url .diag-field-value{color:#2563eb;word-break:break-all}
.diag-field.key-curl .diag-field-value{color:#dc2626}
.diag-field.key-stack{flex-direction:column}
.diag-field.key-stack .diag-field-value{margin-top:4px;padding:8px;background:#1e293b;color:#e2e8f0;border-radius:4px;overflow-x:auto;font-size:10px;line-height:1.4;max-height:200px;overflow-y:auto}
.diag-field.key-response .diag-field-value,
.diag-field.key-body .diag-field-value{background:#f8fafc;padding:6px 8px;border-radius:4px;border:1px solid #e2e8f0;max-height:160px;overflow-y:auto}

.empty-state{text-align:center}
.log-summary{padding:12px 16px;background:linear-gradient(90deg,#fef2f2 0%,#fff 100%);border:1px solid #fecaca;border-radius:8px;margin-bottom:16px;font-size:13px;color:#991b1b;display:flex;align-items:center;gap:8px}
.log-summary .num{font-weight:700;font-size:16px}
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>消息日志</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>

        <div class="log-filter">
            <select class="form-control" onchange="location.href='?bot_id='+this.value" style="min-width:180px;">
                <option value="">请选择机器人</option>
                <?php foreach ($bots as $bot): ?>
                    <option value="<?php echo $bot['id']; ?>" <?php echo $selectedBotId == $bot['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bot['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selectedBotId): ?>
                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('确定清空该机器人的所有日志吗？')">
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="bot_id" value="<?php echo $selectedBotId; ?>">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> 清空日志</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$selectedBotId): ?>
            <div class="empty-state" style="padding:60px 20px;">
                <div class="empty-icon"><i class="fas fa-robot"></i></div>
                <p>请先选择一个机器人查看日志</p>
            </div>
        <?php elseif (empty($logs)): ?>
            <div class="empty-state" style="padding:60px 20px;">
                <div class="empty-icon"><i class="fas fa-scroll"></i></div>
                <p>暂无消息日志</p>
                <span class="subtext">当机器人收发消息后，日志将显示在这里</span>
            </div>
        <?php else: ?>
            <?php
                // 统计各类日志数量
                $counts = ['all' => count($logs), 'error' => 0, 'receive' => 0, 'send' => 0];
                foreach ($logs as $log) {
                    if (isErrorLog($log)) $counts['error']++;
                    elseif ($log['type'] === 'receive') $counts['receive']++;
                    elseif ($log['type'] === 'send') $counts['send']++;
                }
            ?>
            <?php if ($counts['error'] > 0): ?>
            <div class="log-summary">
                <i class="fas fa-triangle-exclamation"></i>
                <span>检测到 <span class="num"><?php echo $counts['error']; ?></span> 条错误日志，包含 API 调用失败、插件执行错误等详细信息，请展开查看诊断详情</span>
            </div>
            <?php endif; ?>

            <div class="filter-tabs">
                <div class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="location.href='?bot_id=<?php echo $selectedBotId; ?>&filter=all'">
                    全部 <span class="count"><?php echo $counts['all']; ?></span>
                </div>
                <div class="filter-tab <?php echo $filter === 'error' ? 'active' : ''; ?>" onclick="location.href='?bot_id=<?php echo $selectedBotId; ?>&filter=error'">
                    <i class="fas fa-triangle-exclamation"></i> 错误 <span class="count"><?php echo $counts['error']; ?></span>
                </div>
                <div class="filter-tab <?php echo $filter === 'receive' ? 'active' : ''; ?>" onclick="location.href='?bot_id=<?php echo $selectedBotId; ?>&filter=receive'">
                    <i class="fas fa-arrow-down"></i> 接收 <span class="count"><?php echo $counts['receive']; ?></span>
                </div>
                <div class="filter-tab <?php echo $filter === 'send' ? 'active' : ''; ?>" onclick="location.href='?bot_id=<?php echo $selectedBotId; ?>&filter=send'">
                    <i class="fas fa-arrow-up"></i> 发送 <span class="count"><?php echo $counts['send']; ?></span>
                </div>
            </div>

            <div class="log-list">
                <?php foreach ($logs as $log):
                    $kind = parseLogKind($log);
                    $isError = isErrorLog($log);

                    // 筛选
                    if ($filter === 'error' && !$isError) continue;
                    if ($filter === 'receive' && $log['type'] !== 'receive') continue;
                    if ($filter === 'send' && $log['type'] !== 'send') continue;

                    $extraFields = parseExtraFields($log['extra'] ?? '');
                    $hasExtra = !empty($extraFields);
                    $extraClass = '';
                    if ($isError) {
                        $extraClass = ' is-error';
                        if ($kind['kind'] === 'plugin_error' || $kind['kind'] === 'plugin_file_error') $extraClass .= ' plugin-err';
                        elseif ($kind['kind'] === 'menu_api_error') $extraClass .= ' menu-err';
                    }
                ?>
                    <div class="log-item<?php echo $extraClass; ?>" data-kind="<?php echo $kind['kind']; ?>">
                        <div class="log-avatar">
                            <img src="<?php echo ($log['type'] === 'send' && $selectedBot) ? h(getBotAvatar($selectedBot)) : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($log['from_id'] ?? 'user'); ?>" alt="" onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/bottts/svg?seed=bot'">
                        </div>
                        <div class="log-content">
                            <div class="log-header">
                                <h5><?php echo htmlspecialchars($log['from_name'] ?: ($log['type'] === 'send' ? '机器人' : '未知用户')); ?></h5>
                                <div class="log-tags">
                                    <span class="log-tag kind-badge" style="background:<?php echo $kind['color']; ?>">
                                        <i class="fas <?php echo $kind['icon']; ?>"></i> <?php echo $kind['label']; ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (($log['from_id'] ?? '') || ($log['group_id'] ?? '')): ?>
                            <div class="log-meta">
                                <?php if ($log['from_id'] ?? ''): ?>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($log['from_id']); ?></span>
                                <?php endif; ?>
                                <?php if ($log['group_id'] ?? ''): ?>
                                    <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($log['group_id']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="log-text"><?php echo nl2br(htmlspecialchars($log['content'])); ?></div>

                            <?php if ($hasExtra): ?>
                            <span class="diag-toggle" onclick="var p=this.nextElementSibling;p.classList.toggle('show');this.querySelector('span').textContent=p.classList.contains('show')?'收起诊断':'展开诊断'">
                                <i class="fas fa-stethoscope"></i> <span><?php echo $isError ? '展开诊断' : '展开诊断'; ?></span>
                                <i class="fas fa-chevron-down" style="font-size:9px"></i>
                            </span>
                            <div class="diag-panel<?php echo $isError ? ' show' : ''; ?>">
                                <div class="diag-panel-header">
                                    <i class="fas fa-bug"></i>
                                    <?php if ($isError): ?>
                                        诊断信息（错误详情）
                                    <?php else: ?>
                                        附加信息
                                    <?php endif; ?>
                                </div>
                                <div class="diag-panel-body">
                                    <?php foreach ($extraFields as $field):
                                        $keyLower = mb_strtolower($field['key']);
                                        $fieldClass = '';
                                        if (strpos($keyLower, '失败原因') !== false || strpos($keyLower, '原因') !== false) $fieldClass = ' key-fail';
                                        elseif (strpos($keyLower, 'http') !== false && strpos($keyLower, '状态') !== false) $fieldClass = ' key-http';
                                        elseif (strpos($keyLower, 'url') !== false || strpos($keyLower, '请求地址') !== false || strpos($keyLower, '请求url') !== false) $fieldClass = ' key-url';
                                        elseif (strpos($keyLower, 'curl') !== false) $fieldClass = ' key-curl';
                                        elseif (isset($field['is_stack']) || strpos($keyLower, '堆栈') !== false) $fieldClass = ' key-stack';
                                        elseif (strpos($keyLower, '响应内容') !== false || strpos($keyLower, 'response') !== false) $fieldClass = ' key-response';
                                        elseif (strpos($keyLower, '请求体') !== false || strpos($keyLower, 'body') !== false) $fieldClass = ' key-body';
                                    ?>
                                        <div class="diag-field<?php echo $fieldClass; ?>">
                                            <?php if (!empty($field['key'])): ?>
                                            <div class="diag-field-key"><?php echo htmlspecialchars($field['key']); ?></div>
                                            <?php endif; ?>
                                            <div class="diag-field-value"><?php echo htmlspecialchars($field['value']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="log-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// 错误日志默认滚动到第一条
document.addEventListener('DOMContentLoaded', function() {
    var firstError = document.querySelector('.log-item.is-error .diag-panel.show');
    if (firstError) {
        // 不自动滚动，避免影响用户浏览
    }
});
</script>

<?php require '../includes/load_footer.php'; ?>
