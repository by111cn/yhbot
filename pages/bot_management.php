<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '官机管理';
$activePage = 'bot';
$userId = $_SESSION['user_id'];

$currentUser = getCurrentUser();
$isAdminUser = ($currentUser['role'] ?? 1) == 0;

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $callbackUrl = trim($_POST['callback_url'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        if (empty($name) || empty($token)) {
            $error = '名称和Token不能为空';
        } elseif (!$isAdminUser && ($currentUser['quota'] ?? 0) <= 0) {
            $error = '额度不足，请先兑换卡密获取额度';
        } else {
            try {
                db()->beginTransaction();
                $stmt = db()->prepare("INSERT INTO bots (user_id, name, token, callback_url, avatar, status) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$userId, $name, $token, $callbackUrl, $avatar]);
                // 非管理员消耗额度
                if (!$isAdminUser) {
                    db()->prepare("UPDATE users SET quota = quota - 1 WHERE id = ?")->execute([$userId]);
                }
                db()->commit();
                $success = '机器人添加成功';
            } catch (Exception $e) {
                db()->rollBack();
                $error = '添加失败: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'edit') {
        $botId = (int)($_POST['bot_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $callbackUrl = trim($_POST['callback_url'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $ownerId = trim($_POST['owner_id'] ?? '');
        $ownerName = trim($_POST['owner_name'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $stmt = db()->prepare("UPDATE bots SET name=?, token=?, callback_url=?, avatar=?, owner_id=?, owner_name=?, status=? WHERE id=? AND user_id=?");
        $stmt->execute([$name, $token, $callbackUrl, $avatar, $ownerId, $ownerName, $status, $botId, $userId]);
        $success = '机器人更新成功';
    }
}

$bots = getBotList($userId);
require '../includes/load_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>我的机器人</h3>
        <button class="btn btn-primary" onclick="openModal('addBotModal')">
            <i class="fas fa-plus"></i> 添加机器人
        </button>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?><?php if ($error === '额度不足，请先兑换卡密获取额度'): ?> <a href="<?php echo SITE_URL; ?>/pages/redeem.php" style="color:var(--primary);font-weight:600;">兑换卡密</a><?php endif; ?></div>
        <?php endif; ?>

        <?php if (empty($bots)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-robot"></i></div>
                <p>还没有添加机器人</p>
                <span class="subtext">点击右上角按钮添加你的第一个机器人</span>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($bots as $bot): ?>
                <div class="item-card">
                    <div class="card-actions">
                        <button class="btn-icon edit" onclick="editBot(<?php echo $bot['id']; ?>)" title="编辑">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon" onclick="confirmDelete('../api/bot.php?action=delete&id=<?php echo $bot['id']; ?>', '确定要删除机器人「<?php echo htmlspecialchars($bot['name']); ?>」吗？')" title="删除">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="bot-card-new">
                        <div class="bot-card-top">
                            <img src="<?php echo h(getBotAvatar($bot)); ?>" class="avatar avatar-lg" style="border-color:<?php echo $bot['status'] ? 'var(--success)' : 'var(--border)'; ?>" alt="" onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/bottts/svg?seed=<?php echo urlencode($bot['id']); ?>'">
                            <div style="flex:1;min-width:0;">
                                <h4 style="font-size:16px;font-weight:600;margin-bottom:4px;"><?php echo htmlspecialchars($bot['name']); ?></h4>
                                <p style="font-size:13px;color:var(--text-muted);word-break:break-all;font-family:monospace;"><?php echo htmlspecialchars(mb_substr($bot['token'], 0, 20)) . '...'; ?></p>
                            </div>
                            <span class="status-badge <?php echo $bot['status'] ? 'active' : 'inactive'; ?>">
                                <?php echo $bot['status'] ? '运行中' : '已关闭'; ?>
                            </span>
                        </div>
                        <div class="bot-card-meta">
                            <span><i class="far fa-clock"></i> <?php echo date('Y-m-d', strtotime($bot['created_at'])); ?></span>
                            <?php if ($bot['owner_id']): ?>
                            <span><i class="fas fa-crown"></i> 主人: <?php echo htmlspecialchars($bot['owner_name'] ?: $bot['owner_id']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bot-card-actions">
                            <button class="btn btn-sm <?php echo $bot['status'] ? 'btn-danger' : 'btn-success'; ?>" onclick="toggleBot(<?php echo $bot['id']; ?>, <?php echo $bot['status'] ? 0 : 1; ?>)">
                                <?php echo $bot['status'] ? '<i class="fas fa-pause"></i> 关闭' : '<i class="fas fa-play"></i> 启用'; ?>
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="copyCallback('<?php echo SITE_URL; ?>/api/webhook.php?bot=<?php echo $bot['id']; ?>')">
                                <i class="fas fa-link"></i> 回调
                            </button>
                            <button class="btn btn-sm btn-info" onclick="setOwner(<?php echo $bot['id']; ?>)">
                                <i class="fas fa-user-tag"></i> 主人
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 添加模态框 -->
<div class="modal-overlay" id="addBotModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>添加机器人</h3>
            <button class="modal-close" onclick="closeModal('addBotModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>机器人名称 <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="给机器人取个名字" required>
                </div>
                <div class="form-group">
                    <label>Token <span class="required">*</span></label>
                    <input type="text" name="token" class="form-control" placeholder="云湖机器人Token" required>
                </div>
                <div class="form-group">
                    <label>回调地址</label>
                    <input type="text" name="callback_url" class="form-control" placeholder="可选，自定义回调地址">
                    <span class="hint">留空则使用系统默认回调地址</span>
                </div>
                <div class="form-group">
                    <label>头像URL</label>
                    <input type="text" name="avatar" class="form-control" placeholder="可选，机器人头像直链，支持 jpg/png/gif">
                    <span class="hint">留空使用随机生成头像</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBotModal')">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑模态框 -->
<div class="modal-overlay" id="editBotModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>编辑机器人</h3>
            <button class="modal-close" onclick="closeModal('editBotModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="bot_id" id="editBotId">
                <div class="form-group">
                    <label>机器人名称 <span class="required">*</span></label>
                    <input type="text" name="name" id="editBotName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Token <span class="required">*</span></label>
                    <input type="text" name="token" id="editBotToken" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>回调地址</label>
                    <input type="text" name="callback_url" id="editBotCallback" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>主人ID</label>
                        <input type="text" name="owner_id" id="editBotOwnerId" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>主人名称</label>
                        <input type="text" name="owner_name" id="editBotOwnerName" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>头像URL
                        <button type="button" class="btn btn-xs btn-outline" onclick="syncAvatar()" style="margin-left:8px;padding:2px 10px;">
                            <i class="fas fa-sync-alt"></i> 从云湖同步
                        </button>
                    </label>
                    <input type="text" name="avatar" id="editBotAvatar" class="form-control" placeholder="机器人头像直链或留空使用随机头像">
                    <div id="avatarPreview" style="margin-top:8px;display:none;">
                        <img src="" style="width:64px;height:64px;border-radius:12px;object-fit:cover;border:2px solid var(--border);" alt="头像预览">
                    </div>
                </div>
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="status" id="editBotStatus" value="1">
                        <span class="toggle-slider"></span>
                        <span>启用机器人</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editBotModal')">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存修改</button>
            </div>
        </form>
    </div>
</div>

<style>
.bot-card-new{display:flex;flex-direction:column;gap:14px;}
.bot-card-top{display:flex;align-items:center;gap:14px;}
.bot-card-meta{display:flex;gap:16px;font-size:13px;color:var(--text-muted);flex-wrap:wrap;}
.bot-card-meta span{display:flex;align-items:center;gap:4px;}
</style>

<script>
function editBot(id) {
    fetch('../api/bot.php?action=get&id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.code === 0) {
            document.getElementById('editBotId').value = data.data.id;
            document.getElementById('editBotName').value = data.data.name;
            document.getElementById('editBotToken').value = data.data.token;
            document.getElementById('editBotCallback').value = data.data.callback_url || '';
            document.getElementById('editBotOwnerId').value = data.data.owner_id || '';
            document.getElementById('editBotOwnerName').value = data.data.owner_name || '';
            document.getElementById('editBotStatus').checked = data.data.status == 1;
            var avatarVal = data.data.avatar || '';
            document.getElementById('editBotAvatar').value = avatarVal;
            updateAvatarPreview(avatarVal);
            openModal('editBotModal');
        }
    });
}
function updateAvatarPreview(url) {
    var preview = document.getElementById('avatarPreview');
    var img = preview.querySelector('img');
    if (url) {
        img.src = url;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var avatarInput = document.getElementById('editBotAvatar');
    if (avatarInput) {
        avatarInput.addEventListener('input', function() {
            updateAvatarPreview(this.value);
        });
    }
});

function syncAvatar() {
    var botId = document.getElementById('editBotId').value;
    if (!botId) { showToast('请先保存机器人', 'error'); return; }
    var btn = document.querySelector('.btn-outline');
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> 同步中';
    btn.disabled = true;
    fetch('../api/bot.php?action=sync_avatar&id=' + botId)
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> 从云湖同步';
        btn.disabled = false;
        if (data.code === 0) {
            document.getElementById('editBotAvatar').value = data.data.avatar;
            updateAvatarPreview(data.data.avatar);
            showToast('头像同步成功', 'success');
        } else {
            showToast(data.msg || '同步失败，请手动填写', 'error');
        }
    })
    .catch(function() {
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> 从云湖同步';
        btn.disabled = false;
        showToast('网络错误', 'error');
    });
}
function toggleBot(id, status) {
    fetch('../api/bot.php?action=toggle&id=' + id + '&status=' + status, { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        if (data.code === 0) location.reload();
        else showToast(data.msg, 'error');
    });
}
function setOwner(id) {
    var ownerId = prompt('请输入主人ID:');
    if (ownerId) {
        fetch('../api/bot.php?action=set_owner&id=' + id + '&owner_id=' + ownerId, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.code === 0) location.reload();
            else showToast(data.msg, 'error');
        });
    }
}
function copyCallback(url) { copyToClipboard(url); }
</script>

<?php require '../includes/load_footer.php'; ?>
