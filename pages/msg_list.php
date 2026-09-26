<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '消息管理';
$activePage = 'msg_list';
$userId = $_SESSION['user_id'];

$bots = getBotList($userId);
$chatType = get('type', 'private');
$selectedBot = null;

if (isset($_GET['bot_id'])) $selectedBot = getBot((int)$_GET['bot_id']);
if (!$selectedBot && !empty($bots)) $selectedBot = $bots[0];

$sessions = [];
if ($selectedBot) {
    $stmt = db()->prepare("SELECT DISTINCT from_id, from_name, chat_type AS type, MAX(created_at) as last_time FROM messages WHERE bot_id=? AND chat_type=? GROUP BY from_id, chat_type ORDER BY last_time DESC LIMIT 30");
    $stmt->execute([$selectedBot['id'], $chatType]);
    $sessions = $stmt->fetchAll();
}

$activeSession = get('session', '');
$messages = [];
if ($selectedBot && $activeSession) {
    $stmt = db()->prepare("SELECT * FROM messages WHERE bot_id=? AND from_id=? AND chat_type=? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$selectedBot['id'], $activeSession, $chatType]);
    $messages = $stmt->fetchAll();
}

require '../includes/load_header.php';
?>

<style>
.msg-layout{display:flex;gap:0;height:calc(100vh - 190px);background:var(--bg-card);border-radius:var(--radius-md);border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow-sm)}
.msg-sidebar{width:280px;border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;background:var(--bg-hover)}
.msg-sidebar-header{padding:16px 18px;border-bottom:1px solid var(--border);font-weight:600;font-size:14px;display:flex;align-items:center;justify-content:space-between;background:var(--bg-card)}
.msg-sidebar-header select{border:1.5px solid var(--border);border-radius:6px;padding:6px 10px;font-size:13px;outline:none;cursor:pointer;min-width:130px;background:var(--bg-card)}
.msg-list{flex:1;overflow-y:auto}
.msg-item{display:flex;align-items:center;gap:12px;padding:13px 18px;cursor:pointer;transition:all var(--transition-fast);border-left:3px solid transparent;border-bottom:1px solid var(--border-light);text-decoration:none;color:inherit}
.msg-item:hover{background:var(--bg-card)}
.msg-item.active{background:var(--bg-card);border-left-color:var(--primary);box-shadow:var(--shadow-xs)}
.msg-item .avatar-img{width:42px;height:42px;border-radius:50%;background:var(--bg);border:2px solid var(--border);object-fit:cover;flex-shrink:0}
.msg-item .msg-info{flex:1;min-width:0}
.msg-item .msg-info h5{font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.msg-item .msg-info p{font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-item .msg-tag{font-size:11px;padding:3px 10px;border-radius:var(--radius-full);font-weight:600;flex-shrink:0}
.msg-tag.private{background:#fce7f3;color:#be185d}
.msg-tag.group{background:#e0e7ff;color:#3730a3}
.msg-main{flex:1;display:flex;flex-direction:column;overflow:hidden}
.msg-main-header{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(180deg,var(--bg-card),var(--bg-hover))}
.msg-main-header h5{font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px}
.msg-tabs{display:flex;gap:6px}
.msg-tab{padding:7px 18px;border-radius:var(--radius-xs);font-size:13px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--bg);transition:all var(--transition-fast);text-decoration:none;color:var(--text-secondary)}
.msg-tab:hover{border-color:var(--primary-light);color:var(--primary)}
.msg-tab.active{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border-color:var(--primary);box-shadow:0 4px 12px rgba(99,102,241,.25)}
.msg-messages{flex:1;overflow-y:auto;padding:22px;display:flex;flex-direction:column;gap:14px}
.msg-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);text-align:center}
.msg-empty i{font-size:52px;margin-bottom:14px;opacity:.25}
.msg-bubble-row{display:flex;gap:10px;max-width:72%}
.msg-bubble-row.me{align-self:flex-end;flex-direction:row-reverse}
.msg-bubble-row .msg-avatar{width:34px;height:34px;border-radius:50%;background:var(--bg);border:2px solid var(--border);flex-shrink:0;object-fit:cover}
.msg-bubble-row .bubble{padding:11px 15px;border-radius:16px;background:var(--bg);font-size:14px;line-height:1.5;box-shadow:var(--shadow-xs);word-break:break-word}
.msg-bubble-row.me .bubble{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border-bottom-right-radius:4px}
.msg-bubble-row:not(.me) .bubble{border-bottom-left-radius:4px}
.msg-bubble-row .msg-time{font-size:11px;color:var(--text-muted);margin-top:5px;text-align:right}
.msg-input-area{padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;align-items:center;background:var(--bg)}
.msg-input-area input{flex:1;padding:11px 18px;border:1.5px solid var(--border);border-radius:var(--radius-full);font-size:14px;outline:none;transition:all var(--transition-fast);background:var(--bg-card)}
.msg-input-area input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.08)}

@media(max-width:768px){
    .msg-layout{flex-direction:column;height:auto}
    .msg-sidebar{width:100%;max-height:300px}
    .msg-main{min-height:350px;overflow:visible}
    .msg-tabs{flex-shrink:0}
    .msg-tab{padding:8px 16px;font-size:14px;z-index:1;position:relative}
}
</style>

<div class="card" style="margin-bottom:0;">
    <div class="card-header" style="border-radius:var(--radius-md) var(--radius-md) 0 0;">
        <h3><span class="icon-dot"></span>消息管理</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (!$selectedBot): ?>
            <div class="empty-state" style="padding:80px 20px;">
                <div class="empty-icon"><i class="fas fa-robot"></i></div>
                <p>请先添加机器人</p>
                <span class="subtext"><a href="bot_management.php" style="color:var(--primary);">前往添加</a></span>
            </div>
        <?php else: ?>
            <div class="msg-layout">
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <span><i class="fas fa-comments" style="margin-right:6px;color:var(--primary);"></i>会话</span>
                        <select onchange="location.href='?bot_id='+this.value+'&type=<?php echo $chatType; ?>'">
                            <?php foreach ($bots as $bot): ?>
                                <option value="<?php echo $bot['id']; ?>" <?php echo $selectedBot && $selectedBot['id'] == $bot['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bot['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="msg-list">
                        <?php if (empty($sessions)): ?>
                            <div style="text-align:center;color:var(--text-muted);padding:50px 20px;font-size:13px;">
                                <i class="fas fa-comments" style="font-size:36px;margin-bottom:12px;opacity:.3"></i>
                                <p>暂无会话数据</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sessions as $session): ?>
                                <a href="?bot_id=<?php echo $selectedBot['id']; ?>&type=<?php echo $chatType; ?>&session=<?php echo urlencode($session['from_id']); ?>"
                                   class="msg-item <?php echo $activeSession === $session['from_id'] ? 'active' : ''; ?>">
                                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($session['from_id']); ?>" class="avatar-img" alt="">
                                    <div class="msg-info">
                                        <h5><?php echo htmlspecialchars($session['from_name'] ?: $session['from_id']); ?></h5>
                                        <p><?php echo $session['last_time'] ? date('m-d H:i', strtotime($session['last_time'])) : ''; ?></p>
                                    </div>
                                    <span class="msg-tag <?php echo $session['type'] === 'private' ? 'private' : 'group'; ?>">
                                        <?php echo $session['type'] === 'private' ? '私聊' : '群聊'; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="msg-main">
                    <div class="msg-main-header">
                        <h5>
                            <?php if ($activeSession): ?>
                            <span style="width:8px;height:8px;border-radius:50%;background:var(--success);display:inline-block;"></span>
                            <?php echo htmlspecialchars($activeSession); ?>
                            <?php else: ?>
                            选择会话开始聊天
                            <?php endif; ?>
                        </h5>
                        <div class="msg-tabs">
                            <a href="?bot_id=<?php echo $selectedBot['id']; ?>&type=private" class="msg-tab <?php echo $chatType === 'private' ? 'active' : ''; ?>">
                                <i class="fas fa-user"></i> 私聊
                            </a>
                            <a href="?bot_id=<?php echo $selectedBot['id']; ?>&type=group" class="msg-tab <?php echo $chatType === 'group' ? 'active' : ''; ?>">
                                <i class="fas fa-users"></i> 群聊
                            </a>
                        </div>
                    </div>
                    <div class="msg-messages">
                        <?php if (!$activeSession): ?>
                            <div class="msg-empty">
                                <i class="fas fa-comments"></i>
                                <p>选择左侧会话开始聊天</p>
                            </div>
                        <?php elseif (empty($messages)): ?>
                            <div class="msg-empty">
                                <i class="fas fa-comment-dots"></i>
                                <p>暂无消息记录</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_reverse($messages) as $msg): ?>
                                <div class="msg-bubble-row <?php echo $msg['from_id'] == 'bot' ? 'me' : ''; ?>">
                                    <img src="<?php echo $msg['from_id'] == 'bot' ? h(getBotAvatar($selectedBot)) : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($msg['from_id']); ?>" class="msg-avatar" alt="" onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/bottts/svg?seed=bot'">
                                    <div>
                                        <div class="bubble"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                                        <div class="msg-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="msg-input-area">
                        <input type="text" id="chatInput" placeholder="输入消息内容...">
                        <button class="btn btn-primary" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i> 发送
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function sendMessage(){var t=document.getElementById('chatInput').value.trim();if(!t)return;showToast('消息已发送: '+t,'info');document.getElementById('chatInput').value='';}
document.addEventListener('DOMContentLoaded',function(){var i=document.getElementById('chatInput');if(i)i.addEventListener('keypress',function(e){if(e.key==='Enter')sendMessage();});});
</script>

<?php require '../includes/load_footer.php'; ?>
