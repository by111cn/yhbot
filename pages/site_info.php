<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '站点信息';
$activePage = 'site_info';

// 系统统计
$phpVersion = phpversion();
$mysqlVersion = db()->query("SELECT VERSION() as v")->fetch()['v'];
$diskFree = round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2);
$diskTotal = round(disk_total_space(__DIR__) / 1024 / 1024 / 1024, 2);
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');
$maxExecTime = ini_get('max_execution_time') . 's';

// 业务统计
$userCount = countTable('users');
$botCount = countTable('bots');
$apiCount = countTable('apis');
$menuCount = countTable('menus');
$msgCount = countTable('messages');
$cdkeyUnused = countTable('cdkeys', 'status = 0');
$cdkeyUsed = countTable('cdkeys', 'status = 1');
$todayMsg = countTable('messages', 'DATE(created_at) = DATE(NOW())');

// 处理站点设置保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $siteName = trim($_POST['site_name'] ?? '');
    $adminQQ = trim($_POST['admin_qq'] ?? '');
    $adminYunhuId = trim($_POST['admin_yunhu_id'] ?? '');
    $groupChat = trim($_POST['group_chat'] ?? '');
    $siteNotice = trim($_POST['site_notice'] ?? '');

    $settings = [
        'site_name'      => $siteName,
        'admin_qq'       => $adminQQ,
        'admin_yunhu_id' => $adminYunhuId,
        'group_chat'     => $groupChat,
        'site_notice'    => $siteNotice,
    ];

    // 处理 LOGO 上传
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['site_logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (!in_array($ext, $allowed, true)) {
            $error = '仅支持 jpg、png、gif、webp、svg 格式的图片';
        } else {
            $dir = dirname(__DIR__) . '/uploads/images';
            $filename = 'site_logo.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                $settings['site_logo'] = SITE_URL . '/uploads/images/' . $filename;
            } else {
                $error = 'LOGO 上传失败，请检查 uploads/images 目录权限';
            }
        }
    }

    $stmt = db()->prepare("INSERT INTO settings (user_id, `key`, `value`) VALUES (0, ?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v, $v]);
    }
    if (!isset($error)) $success = '站点设置已保存';
}

// 获取设置
$customSiteName = getSetting('site_name');
$adminQQ = getSetting('admin_qq');
$adminYunhuId = getSetting('admin_yunhu_id');
$groupChat = getSetting('group_chat');
$siteNotice = getSetting('site_notice');
$siteLogo = getSetting('site_logo');

require '../includes/load_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>站点信息</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>

        <!-- 站点设置 -->
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="form-group" style="max-width:480px;">
                <label><i class="fas fa-globe"></i> 站点名称</label>
                <input type="text" name="site_name" class="form-control" value="<?php echo h($customSiteName ?: SITE_NAME); ?>" placeholder="输入站点名称">
                <span class="hint">自定义站点展示名称</span>
            </div>
            <div class="form-group" style="max-width:480px;">
                <label><i class="fas fa-image"></i> 站点 LOGO</label>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <?php if ($siteLogo): ?>
                    <img src="<?php echo h($siteLogo); ?>" style="width:48px;height:48px;border-radius:10px;object-fit:contain;border:1px solid var(--border);background:#fff;">
                    <?php endif; ?>
                    <input type="file" name="site_logo" accept="image/*" class="form-control" style="flex:1;min-width:180px;">
                </div>
                <span class="hint">建议尺寸 200x200 以内，支持 jpg、png、gif、webp、svg</span>
            </div>
            <div class="form-group" style="max-width:480px;">
                <label><i class="fab fa-qq"></i> 站长QQ</label>
                <input type="text" name="admin_qq" class="form-control" value="<?php echo h($adminQQ); ?>" placeholder="输入站长QQ号">
                <span class="hint">旧版标识，云湖机器人改用下方云湖ID</span>
            </div>
            <div class="form-group" style="max-width:480px;">
                <label><i class="fas fa-robot"></i> 管理员云湖ID</label>
                <input type="text" name="admin_yunhu_id" class="form-control" value="<?php echo h($adminYunhuId); ?>" placeholder="向机器人发送「绑定管理员」自动获取">
                <span class="hint">向机器人私聊发送「绑定管理员」自动填入，也可手动填写云湖用户ID</span>
            </div>
            <div class="form-group" style="max-width:480px;">
                <label><i class="fas fa-users"></i> 官方群聊</label>
                <input type="text" name="group_chat" class="form-control" value="<?php echo h($groupChat); ?>" placeholder="输入群号或群链接">
            </div>
            <div class="form-group" style="max-width:480px;">
                <label><i class="fas fa-bullhorn"></i> 网站公告</label>
                <textarea name="site_notice" class="form-control" rows="4" placeholder="输入网站公告内容"><?php echo h($siteNotice); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:24px;">
                <i class="fas fa-save"></i> 保存设置
            </button>
        </form>

        <!-- 系统环境 -->
        <h4 style="margin-bottom:12px;color:var(--text-secondary);">系统环境</h4>
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-label">PHP 版本</div>
                <div class="stat-value"><?php echo $phpVersion; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">MySQL 版本</div>
                <div class="stat-value"><?php echo $mysqlVersion; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">磁盘空间</div>
                <div class="stat-value"><?php echo $diskFree; ?>G / <?php echo $diskTotal; ?>G</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">上传限制</div>
                <div class="stat-value"><?php echo $maxUpload; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">POST 限制</div>
                <div class="stat-value"><?php echo $maxPost; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">执行超时</div>
                <div class="stat-value"><?php echo $maxExecTime; ?></div>
            </div>
        </div>

        <!-- 业务统计 -->
        <h4 style="margin-bottom:12px;color:var(--text-secondary);">业务统计</h4>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">注册用户</div>
                <div class="stat-value" style="color:#6366f1;"><?php echo $userCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">机器人数量</div>
                <div class="stat-value" style="color:#10b981;"><?php echo $botCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">API 数量</div>
                <div class="stat-value" style="color:#f59e0b;"><?php echo $apiCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">菜单数量</div>
                <div class="stat-value" style="color:#ec4899;"><?php echo $menuCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">消息总数</div>
                <div class="stat-value" style="color:#8b5cf6;"><?php echo $msgCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">今日消息</div>
                <div class="stat-value" style="color:#06b6d4;"><?php echo $todayMsg; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">未使用卡密</div>
                <div class="stat-value" style="color:#10b981;"><?php echo $cdkeyUnused; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">已使用卡密</div>
                <div class="stat-value" style="color:#6b7280;"><?php echo $cdkeyUsed; ?></div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
