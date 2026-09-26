<?php
/**
 * 白屿云平台 BotAPI - 群聊管理
 * 基于云湖开放API文档 400-450
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
requireLogin();
$user = getCurrentUser();
$activePage = 'group_chat';
$pageTitle = '群聊消息';
$userId = $_SESSION['user_id'];

// 获取用户的机器人列表
$bots = db()->prepare("SELECT * FROM bots WHERE user_id = ? ORDER BY id ASC");
$bots->execute([$userId]);
$bots = $bots->fetchAll();

$selectedBotId = intval($_GET['bot_id'] ?? ($bots[0]['id'] ?? 0));
$selectedChatId = $_GET['chat_id'] ?? '';

// 获取群聊会话列表
$groupChats = [];
if ($selectedBotId) {
    $stmt = db()->prepare("
        SELECT chat_id, MAX(from_name) as user_name, MAX(created_at) as last_msg_time,
               COUNT(*) as msg_count
        FROM messages 
        WHERE bot_id = ? AND chat_type = 'group'
        GROUP BY chat_id 
        ORDER BY last_msg_time DESC 
        LIMIT 100
    ");
    $stmt->execute([$selectedBotId]);
    $groupChats = $stmt->fetchAll();
}

// 获取选中群的聊天记录
$messages = [];
if ($selectedBotId && $selectedChatId) {
    $stmt = db()->prepare("
        SELECT * FROM messages 
        WHERE bot_id = ? AND chat_id = ? AND chat_type = 'group' 
        ORDER BY created_at ASC 
        LIMIT 200
    ");
    $stmt->execute([$selectedBotId, $selectedChatId]);
    $messages = $stmt->fetchAll();
}

// 查询群信息（如果选择了群聊）
$groupInfo = null;
if ($selectedBotId && $selectedChatId) {
    $bot = db()->prepare("SELECT * FROM bots WHERE id = ? AND user_id = ?");
    $bot->execute([$selectedBotId, $userId]);
    $bot = $bot->fetch();
    if ($bot) {
        $groupInfo = yunhuGetGroupInfo($bot['token'], $selectedChatId);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/font-awesome/css/all.min.css?v=6.4.0">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/favicon.svg">
    <style>
    .im-container { display:flex; height:calc(100vh - 100px); background:var(--card-bg); border-radius:12px; overflow:hidden; border:1px solid var(--border-color); min-height:600px; }
    .im-sidebar { width:320px; min-width:260px; border-right:1px solid var(--border-color); display:flex; flex-direction:column; background:var(--bg-secondary); }
    .im-sidebar-header { padding:14px 16px; border-bottom:1px solid var(--border-color); }
    .im-session-list { flex:1; overflow-y:auto; }
    .im-session-item { display:flex; align-items:center; gap:10px; padding:12px 16px; cursor:pointer; border-bottom:1px solid var(--border-color); transition:background 0.15s; text-decoration:none; color:inherit; }
    .im-session-item:hover { background:var(--bg-hover); }
    .im-session-item.active { background:var(--primary-bg); border-left:3px solid var(--primary); }
    .im-session-avatar { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; color:#fff; }
    .im-session-avatar.group { border-radius:12px; }
    .im-session-info { flex:1; min-width:0; }
    .im-session-name { font-weight:600; font-size:14px; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .im-session-preview { font-size:12px; color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .im-session-meta { text-align:right; flex-shrink:0; }
    .im-session-time { font-size:11px; color:var(--text-secondary); }
    .im-session-badge { display:inline-block; background:var(--primary); color:#fff; font-size:10px; padding:1px 6px; border-radius:10px; margin-top:2px; }
    .im-main { flex:1; display:flex; flex-direction:column; min-width:0; min-height:0; }
    .im-main-header { padding:12px 20px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:10px; background:var(--bg-secondary); }
    .im-main-body { flex:1; min-height:0; overflow-y:auto; padding:16px 20px; display:flex; flex-direction:column; gap:12px; }
    .im-main-footer { flex-shrink:0; padding:12px 16px; border-top:1px solid var(--border-color); display:flex; gap:10px; align-items:center; background:var(--bg-secondary); }
    .im-main-footer input { flex:1; }
    .im-empty { flex:1; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); flex-direction:column; gap:10px; }
    .im-empty i { font-size:48px; opacity:0.3; }
    .msg-row { display:flex; gap:10px; max-width:80%; }
    .msg-row.sent { align-self:flex-end; flex-direction:row-reverse; }
    .msg-bubble { padding:10px 14px; border-radius:14px; font-size:14px; line-height:1.5; word-break:break-word; }
    .msg-row.received .msg-bubble { background:var(--bg-secondary); color:var(--text-primary); border-bottom-left-radius:4px; }
    .msg-row.sent .msg-bubble { background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
    .msg-time { font-size:11px; color:var(--text-secondary); text-align:center; margin:8px 0; }
    .msg-sender { font-size:11px; color:var(--text-secondary); margin-bottom:2px; }
    .group-info-bar { margin:0 20px 12px; padding:10px 14px; background:var(--bg-secondary); border-radius:8px; font-size:12px; display:flex; gap:12px; flex-wrap:wrap; }
    .group-info-bar span { display:flex; align-items:center; gap:4px; }
    .group-info-bar .label { color:var(--text-secondary); }
    @media (max-width:768px) {
        .im-container { height:calc(100vh - 130px); min-height:auto; }
        .im-sidebar { width:100%; min-width:auto; }
        .im-main { display:none; }
        .im-container.show-chat .im-sidebar { display:none; }
        .im-container.show-chat .im-main { display:flex; width:100%; }
    }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-users"></i> 群聊管理</h1>
                <p class="text-secondary">管理机器人所加入的群聊 · 基于云湖 Bot OpenAPI</p>
            </div>

            <?php if (empty($bots)): ?>
            <div class="card">
                <div class="card-body" style="text-align:center;padding:60px 20px;">
                    <i class="fas fa-crown" style="font-size:48px;color:var(--text-secondary);opacity:0.3;"></i>
                    <p style="margin-top:14px;color:var(--text-secondary);">您还没有绑定机器人，请先添加官机</p>
                    <a href="<?php echo SITE_URL; ?>/pages/bot_management.php" class="btn btn-primary" style="margin-top:10px;">前往官机管理</a>
                </div>
            </div>
            <?php else: ?>

            <div class="im-container" id="imContainer">
                <!-- 左侧群聊列表 -->
                <div class="im-sidebar" id="imSidebar">
                    <div class="im-sidebar-header">
                        <label style="font-size:12px;color:var(--text-secondary);display:block;margin-bottom:6px;">选择机器人</label>
                        <select class="form-control" onchange="switchBot(this.value)" style="font-size:13px;">
                            <?php foreach ($bots as $bot): ?>
                            <option value="<?php echo $bot['id']; ?>" <?php echo $bot['id'] == $selectedBotId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bot['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="im-session-list" id="sessionList">
                        <?php if (empty($groupChats)): ?>
                        <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                            <i class="fas fa-inbox" style="font-size:36px;opacity:0.3;"></i>
                            <p style="margin-top:8px;font-size:13px;">暂无群聊会话</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($groupChats as $chat): ?>
                        <a href="?bot_id=<?php echo $selectedBotId; ?>&chat_id=<?php echo urlencode($chat['chat_id']); ?>"
                           class="im-session-item <?php echo $chat['chat_id'] == $selectedChatId ? 'active' : ''; ?>"
                           onclick="selectChat(event, '<?php echo htmlspecialchars($chat['chat_id']); ?>', '<?php echo htmlspecialchars($chat['user_name'] ?: $chat['chat_id']); ?>')">
                            <div class="im-session-avatar group" style="background:<?php echo groupAvatarColor($chat['chat_id']); ?>">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="im-session-info">
                                <div class="im-session-name"><?php echo htmlspecialchars($chat['user_name'] ?: $chat['chat_id']); ?></div>
                                <div class="im-session-preview">ID: <?php echo htmlspecialchars(substr($chat['chat_id'], 0, 18)); ?>...</div>
                            </div>
                            <div class="im-session-meta">
                                <div class="im-session-time"><?php echo groupTimeAgo($chat['last_msg_time']); ?></div>
                                <div class="im-session-badge"><?php echo $chat['msg_count']; ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 右侧聊天区域 -->
                <div class="im-main" id="imMain">
                    <?php if ($selectedChatId): ?>
                    <div class="im-main-header">
                        <button class="btn btn-sm btn-outline" onclick="showSidebar()" style="display:none;" id="backToSidebarBtn">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="im-session-avatar group" style="width:34px;height:34px;font-size:14px;background:<?php echo groupAvatarColor($selectedChatId); ?>">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <strong style="font-size:14px;" id="chatTitle"><?php echo htmlspecialchars((!empty($groupChats) ? $groupChats[0]['user_name'] : '') ?: $selectedChatId); ?></strong>
                            <div style="font-size:11px;color:var(--text-secondary);" id="chatSubtitle">
                                群聊 · <?php echo $groupInfo['data']['group_name'] ?? $selectedChatId; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($groupInfo && !empty($groupInfo['data'])): ?>
                    <div class="group-info-bar">
                        <span><span class="label">群名称:</span> <?php echo htmlspecialchars($groupInfo['data']['group_name'] ?? '-'); ?></span>
                        <span><span class="label">群主:</span> <?php echo htmlspecialchars($groupInfo['data']['owner_user_name'] ?? '-'); ?></span>
                        <span><span class="label">成员数:</span> <?php echo intval($groupInfo['data']['member_count'] ?? 0); ?></span>
                        <span><span class="label">创建:</span> <?php echo htmlspecialchars($groupInfo['data']['created_at'] ?? '-'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="im-main-body" id="msgList">
                        <?php if (empty($messages)): ?>
                        <div class="im-empty">
                            <i class="fas fa-comment-dots"></i>
                            <p>暂无聊天记录，在下方发送第一条群消息吧</p>
                        </div>
                        <?php else: ?>
                        <?php
                        $lastDate = '';
                        foreach ($messages as $msg):
                            $isSent = ($msg['type'] ?? '') === 'send';
                            $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                            if ($msgDate !== $lastDate): $lastDate = $msgDate; ?>
                        <div class="msg-time"><?php echo $msgDate; ?></div>
                        <?php endif; ?>
                        <div class="msg-row <?php echo $isSent ? 'sent' : 'received'; ?>">
                            <div class="im-session-avatar" style="width:30px;height:30px;font-size:12px;border-radius:8px;<?php echo $isSent ? 'background:#10b981;' : 'background:' . groupAvatarColor($selectedChatId) . ';'; ?>">
                                <?php echo $isSent ? '<i class="fas fa-robot" style="font-size:10px;"></i>' : mb_substr($msg['from_name'] ?? '群', 0, 1); ?>
                            </div>
                            <div>
                                <?php if (!$isSent): ?><div class="msg-sender"><?php echo htmlspecialchars($msg['from_name'] ?? '群成员'); ?></div><?php endif; ?>
                                <div class="msg-bubble"><?php echo groupFormatMsgContent($msg['content']); ?></div>
                                <div style="font-size:10px;color:var(--text-secondary);margin-top:2px;<?php echo $isSent ? 'text-align:right;' : ''; ?>">
                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="im-main-footer">
                        <input type="text" id="sendInput" class="form-control" placeholder="输入群消息内容 (按Enter发送)..."
                               onkeydown="if(event.key==='Enter')sendGroupMessage()" style="font-size:14px;">
                        <button class="btn btn-primary" onclick="sendGroupMessage()">
                            <i class="fas fa-paper-plane"></i> 发送
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="im-empty">
                        <i class="fas fa-users"></i>
                        <p>选择一个群聊会话开始管理</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
    var selectedBotId = <?php echo $selectedBotId; ?>;
    var selectedChatId = '<?php echo addslashes($selectedChatId); ?>';

    function switchBot(botId) {
        window.location.href = '?bot_id=' + botId;
    }

    function selectChat(e, chatId, groupName) {
        if (window.innerWidth <= 768) {
            e.preventDefault();
            document.getElementById('imContainer').classList.add('show-chat');
            document.getElementById('chatTitle').textContent = groupName;
            document.getElementById('chatSubtitle').textContent = '群聊 · ' + chatId;
            loadMessages(chatId);
        }
    }

    function showSidebar() {
        document.getElementById('imContainer').classList.remove('show-chat');
    }

    function loadMessages(chatId) {
        document.getElementById('msgList').innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> 加载中...</div>';
        window.location.href = '?bot_id=' + selectedBotId + '&chat_id=' + encodeURIComponent(chatId);
    }

    function sendGroupMessage() {
        var input = document.getElementById('sendInput');
        var content = input.value.trim();
        if (!content) return;
        if (!selectedChatId) {
            showToast('请先选择群聊', 'warning');
            return;
        }

        var btn = document.querySelector('.im-main-footer .btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        var formData = new FormData();
        formData.append('bot_id', selectedBotId);
        formData.append('chat_id', selectedChatId);
        formData.append('content', content);
        formData.append('msg_type', 'text');
        formData.append('recv_type', 'group');

        fetch('<?php echo SITE_URL; ?>/api/message.php?action=send', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> 发送';
            if (res.code === 0) {
                input.value = '';
                var msgHtml = '<div class="msg-row sent">' +
                    '<div class="im-session-avatar" style="width:30px;height:30px;font-size:12px;border-radius:8px;background:#10b981;"><i class="fas fa-robot" style="font-size:10px;"></i></div>' +
                    '<div>' +
                    '<div class="msg-bubble">' + escapeHtml(content) + '</div>' +
                    '<div style="font-size:10px;color:var(--text-secondary);margin-top:2px;text-align:right;">刚刚</div>' +
                    '</div></div>';
                var msgList = document.getElementById('msgList');
                if (msgList.querySelector('.im-empty')) msgList.innerHTML = '';
                msgList.insertAdjacentHTML('beforeend', msgHtml);
                msgList.scrollTop = msgList.scrollHeight;
                showToast('群消息发送成功', 'success');
            } else {
                showToast('发送失败: ' + (res.msg || '未知错误'), 'error');
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> 发送';
            showToast('网络错误', 'error');
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    (function() {
        var el = document.getElementById('msgList');
        if (el) el.scrollTop = el.scrollHeight;
    })();

    function checkMobile() {
        var backBtn = document.getElementById('backToSidebarBtn');
        if (backBtn) backBtn.style.display = window.innerWidth <= 768 ? 'inline-flex' : 'none';
    }
    window.addEventListener('resize', checkMobile);
    checkMobile();
    </script>

    <?php require dirname(__DIR__) . '/includes/load_footer.php'; ?>
</body>
</html>
<?php
function groupAvatarColor($id) {
    $colors = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#14b8a6','#3b82f6','#a855f7'];
    $hash = crc32($id);
    return $colors[abs($hash) % count($colors)];
}

function groupTimeAgo($time) {
    $diff = time() - strtotime($time);
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    return date('m-d', strtotime($time));
}

function groupFormatMsgContent($content) {
    $text = htmlspecialchars($content);
    $text = preg_replace('/(https?:\/\/[^\s<]+)/i', '<a href="$1" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">$1</a>', $text);
    return $text;
}

// 获取群信息
function yunhuGetGroupInfo($token, $groupId) {
    $result = curlPostJson(
        'https://chat-go.jwzhd.com/open-apis/v1/bot/group-info?token=' . urlencode($token),
        ['groupId' => $groupId]
    );
    return json_decode($result, true);
}
?>
