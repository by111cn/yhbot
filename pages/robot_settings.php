<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '机器人设置';
$activePage = 'robot_settings';
$userId = $_SESSION['user_id'];

// 获取机器人列表
$bots = getBotList($userId);
$selectedBot = null;

if (isset($_GET['bot_id'])) {
    $selectedBot = getBot((int)$_GET['bot_id']);
    if ($selectedBot && $selectedBot['user_id'] != $userId) {
        $selectedBot = null;
    }
} elseif (!empty($bots)) {
    $selectedBot = $bots[0];
}

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedBot) {
    verifyCsrf();
    $botId = $selectedBot['id'];
    $welcomeMsg = trim($_POST['welcome_msg'] ?? '');
    $unknownReply = trim($_POST['unknown_reply'] ?? '');
    $apiCallback = trim($_POST['api_callback'] ?? '');
    $ownerId = trim($_POST['owner_id'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    
    $stmt = db()->prepare("UPDATE bots SET welcome_msg = ?, unknown_reply = ?, api_callback = ?, owner_id = ?, owner_name = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$welcomeMsg, $unknownReply, $apiCallback, $ownerId, $ownerName, $botId, $userId]);
    $success = '设置保存成功';
    
    // 刷新数据
    $selectedBot = getBot($botId);
}

require '../includes/load_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>机器人设置</h3>
        <select class="form-control" style="width: auto;" onchange="location.href='?bot_id='+this.value">
            <?php foreach ($bots as $bot): ?>
                <option value="<?php echo $bot['id']; ?>" <?php echo $selectedBot && $selectedBot['id'] == $bot['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($bot['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="card-body">
        <?php if (!$selectedBot): ?>
            <div class="empty-state">
                <i class="fas fa-robot"></i>
                <p>请先添加机器人</p>
            </div>
        <?php else: ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab-item active" data-target="tab-basic">基础设置</div>
                <div class="tab-item" data-target="tab-reply">回复设置</div>
                <div class="tab-item" data-target="tab-advanced">高级设置</div>
            </div>
            
            <form method="POST" action="?bot_id=<?php echo $selectedBot['id']; ?>">
                <?php echo csrfField(); ?>
                <!-- 基础设置 -->
                <div class="tab-pane active" id="tab-basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label>机器人名称</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($selectedBot['name']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Token</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($selectedBot['token']); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>主人ID</label>
                            <input type="text" name="owner_id" class="form-control" value="<?php echo htmlspecialchars($selectedBot['owner_id'] ?? ''); ?>" placeholder="设置主人ID">
                        </div>
                        <div class="form-group">
                            <label>主人名称</label>
                            <input type="text" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($selectedBot['owner_name'] ?? ''); ?>" placeholder="主人显示名称">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <div>
                            <span class="status-badge <?php echo $selectedBot['status'] ? 'active' : 'inactive'; ?>">
                                <?php echo $selectedBot['status'] ? '已启用' : '已关闭'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- 回复设置 -->
                <div class="tab-pane" id="tab-reply">
                    <div class="form-group">
                        <label>欢迎消息</label>
                        <textarea name="welcome_msg" class="form-control" rows="4" placeholder="新用户加入时发送的欢迎消息"><?php echo htmlspecialchars($selectedBot['welcome_msg'] ?? ''); ?></textarea>
                        <small style="color: var(--text-light);">支持变量: {user_name} {bot_name}</small>
                    </div>
                    <div class="form-group">
                        <label>未知指令回复</label>
                        <textarea name="unknown_reply" class="form-control" rows="4" placeholder="用户发送未知指令时的回复"><?php echo htmlspecialchars($selectedBot['unknown_reply'] ?? ''); ?></textarea>
                        <small style="color: var(--text-light);">支持变量: {command} {user_name}</small>
                    </div>
                </div>
                
                <!-- 高级设置 -->
                <div class="tab-pane" id="tab-advanced">
                    <div class="form-group">
                        <label>API回调地址</label>
                        <input type="text" name="api_callback" class="form-control" value="<?php echo htmlspecialchars($selectedBot['api_callback'] ?? ''); ?>" placeholder="自定义API回调地址">
                        <small style="color: var(--text-light);">系统默认回调: <?php echo SITE_URL; ?>/api/webhook.php?bot=<?php echo $selectedBot['id']; ?></small>
                    </div>
                    <div class="form-group">
                        <label class="toggle-switch" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" <?php echo $selectedBot['status'] ? 'checked' : ''; ?> disabled>
                            <span class="toggle-slider"></span>
                            <span>启用机器人（在客机管理中切换）</span>
                        </label>
                    </div>
                </div>
                
                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
