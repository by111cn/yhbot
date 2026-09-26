<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '聚合登录';
$activePage = 'oauth_config';

// 所有可选平台
$providerMap = [
    'qq'        => ['name' => 'QQ',         'icon' => 'fab fa-qq'],
    'wx'        => ['name' => '微信',       'icon' => 'fab fa-weixin'],
    'alipay'    => ['name' => '支付宝',     'icon' => 'fab fa-alipay'],
    'sina'      => ['name' => '微博',       'icon' => 'fab fa-weibo'],
    'baidu'     => ['name' => '百度',       'icon' => 'fab fa-paw'],
    'douyin'    => ['name' => '抖音',       'icon' => 'fab fa-tiktok'],
    'huawei'    => ['name' => '华为',       'icon' => 'fas fa-mobile-alt'],
    'xiaomi'    => ['name' => '小米',       'icon' => 'fas fa-mobile'],
    'google'    => ['name' => '谷歌',       'icon' => 'fab fa-google'],
    'microsoft' => ['name' => '微软',       'icon' => 'fab fa-microsoft'],
    'dingtalk'  => ['name' => '钉钉',       'icon' => 'fas fa-bell'],
    'feishu'    => ['name' => '飞书',       'icon' => 'fas fa-paper-plane'],
    'gitee'     => ['name' => 'Gitee',      'icon' => 'fab fa-git-alt'],
    'github'    => ['name' => 'GitHub',     'icon' => 'fab fa-github'],
];

// 读取配置
$stmt = db()->prepare("SELECT `value` FROM settings WHERE `key` = 'oauth_config' AND user_id = 0");
$stmt->execute();
$row = $stmt->fetch();
$cfg = $row ? json_decode($row['value'], true) : [];
$cfg = is_array($cfg) ? $cfg : [];

// 默认值
$cfg['enabled']     = isset($cfg['enabled']) ? (bool)$cfg['enabled'] : false;
$cfg['connect_url'] = $cfg['connect_url'] ?? 'https://login.az0.cn/connect.php';
$cfg['appid']       = $cfg['appid'] ?? '';
$cfg['appkey']      = $cfg['appkey'] ?? '';
$cfg['types']       = $cfg['types'] ?? '';

$selectedTypes = array_filter(array_map('trim', explode(',', $cfg['types'])));

// 处理保存
$saveMsg = '';
$saveErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $newCfg = [
        'enabled'     => (int)($_POST['enabled'] ?? 0),
        'connect_url' => trim($_POST['connect_url'] ?? ''),
        'appid'       => trim($_POST['appid'] ?? ''),
        'appkey'      => trim($_POST['appkey'] ?? ''),
        'types'       => trim($_POST['types'] ?? ''),
    ];

    if ($newCfg['connect_url'] === '') {
        $newCfg['connect_url'] = 'https://login.az0.cn/connect.php';
    }

    $json = json_encode($newCfg, JSON_UNESCAPED_UNICODE);
    try {
        $stmt = db()->prepare("SELECT id FROM settings WHERE `key` = 'oauth_config' AND user_id = 0");
        $stmt->execute();
        $exists = $stmt->fetch();
        if ($exists) {
            db()->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'oauth_config' AND user_id = 0")->execute([$json]);
        } else {
            db()->prepare("INSERT INTO settings (user_id, `key`, `value`) VALUES (0, 'oauth_config', ?)")->execute([$json]);
        }
        $cfg = $newCfg;
        $cfg['enabled'] = (bool)$cfg['enabled'];
        $selectedTypes = array_filter(array_map('trim', explode(',', $cfg['types'])));
        $saveMsg = '配置已保存';
    } catch (Exception $e) {
        $saveErr = '保存失败：' . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<style>
.oauth-config-wrap .desc-line {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 20px;
}
.oauth-config-wrap .desc-line a {
    color: var(--primary);
    text-decoration: none;
    margin-left: 6px;
}
.oauth-config-wrap .desc-line a:hover {
    text-decoration: underline;
}
.oauth-config-wrap .form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.oauth-config-wrap .form-row .form-group {
    flex: 1;
    min-width: 240px;
}
.oauth-config-wrap .tag-section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin: 18px 0 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.oauth-config-wrap .provider-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.oauth-config-wrap .provider-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.oauth-config-wrap .provider-tag:hover {
    border-color: var(--primary-light);
    color: var(--primary);
}
.oauth-config-wrap .provider-tag.active {
    border-color: var(--primary);
    background: var(--primary-bg);
    color: var(--primary);
}
.oauth-config-wrap .provider-tag i {
    font-size: 14px;
}
</style>

<div class="card oauth-config-wrap">
    <div class="card-header">
        <h3>
            <span class="icon-dot purple"></span>
            🌈 彩虹聚合登录配置
        </h3>
        <div class="card-actions">
            <button class="btn btn-sm btn-primary" onclick="document.getElementById('oauthConfigForm').submit()">
                <i class="fas fa-save"></i> 保存配置
            </button>
        </div>
    </div>
    <div class="card-body">
        <form id="oauthConfigForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <?php if ($saveMsg): ?>
                <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?php echo $saveMsg; ?></div>
            <?php endif; ?>
            <?php if ($saveErr): ?>
                <div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-exclamation-circle"></i> <?php echo $saveErr; ?></div>
            <?php endif; ?>

            <div class="desc-line">
                <i class="fas fa-info-circle"></i>
                配置彩虹聚合登录后，用户可通过QQ、微信、支付宝等第三方账号快速登录/注册。
                <a href="https://login.az0.cn/" target="_blank" rel="noopener">前往申请 →</a>
            </div>

            <div class="form-group">
                <label>启用聚合登录</label>
                <label class="toggle-switch">
                    <input type="checkbox" name="enabled" value="1" <?php echo $cfg['enabled'] ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span id="enableText" style="font-size:13px;color:var(--text-secondary);"><?php echo $cfg['enabled'] ? '开启' : '关闭'; ?></span>
                </label>
                <div class="hint">开启后将在登录/注册页面显示第三方登录入口</div>
            </div>

            <div class="form-group">
                <label>聚合登录接口地址</label>
                <input type="text" name="connect_url" class="form-control" value="<?php echo htmlspecialchars($cfg['connect_url']); ?>" placeholder="https://login.az0.cn/connect.php">
                <div class="hint">彩虹聚合登录API地址，一般无需修改。回调地址请填写：<code><?php echo SITE_URL; ?>/api/oauth.php?action=callback</code></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>AppID</label>
                    <input type="text" name="appid" class="form-control" value="<?php echo htmlspecialchars($cfg['appid']); ?>" placeholder="在彩虹聚合登录后台获取">
                </div>
                <div class="form-group">
                    <label>AppKey</label>
                    <input type="text" name="appkey" class="form-control" value="<?php echo htmlspecialchars($cfg['appkey']); ?>" placeholder="在彩虹聚合登录后台获取">
                </div>
            </div>

            <div class="form-group">
                <label>启用的登录方式</label>
                <input type="text" id="typesInput" name="types" class="form-control" value="<?php echo htmlspecialchars($cfg['types']); ?>" placeholder="qq,wx,alipay">
                <div class="hint">用英文逗号分隔，可选值：<?php echo implode(', ', array_keys($providerMap)); ?></div>
            </div>

            <div class="tag-section-title"><i class="fas fa-list-ul"></i> 可选登录方式</div>
            <div class="provider-tags" id="providerTags">
                <?php foreach ($providerMap as $key => $info): ?>
                <span class="provider-tag <?php echo in_array($key, $selectedTypes) ? 'active' : ''; ?>" data-key="<?php echo $key; ?>">
                    <i class="<?php echo $info['icon']; ?>"></i>
                    <?php echo $info['name']; ?> (<?php echo $key; ?>)
                </span>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var tags = document.querySelectorAll('#providerTags .provider-tag');
    var input = document.getElementById('typesInput');

    function getSelected() {
        return input.value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
    }

    function updateTags() {
        var selected = getSelected();
        tags.forEach(function(tag) {
            if (selected.indexOf(tag.dataset.key) !== -1) {
                tag.classList.add('active');
            } else {
                tag.classList.remove('active');
            }
        });
    }

    tags.forEach(function(tag) {
        tag.addEventListener('click', function() {
            var key = this.dataset.key;
            var selected = getSelected();
            var idx = selected.indexOf(key);
            if (idx !== -1) {
                selected.splice(idx, 1);
            } else {
                selected.push(key);
            }
            input.value = selected.join(',');
            updateTags();
        });
    });

    input.addEventListener('input', updateTags);

    // 启用开关文字切换
    var checkbox = document.querySelector('input[name="enabled"]');
    var enableText = document.getElementById('enableText');
    checkbox.addEventListener('change', function() {
        enableText.textContent = this.checked ? '开启' : '关闭';
    });
})();
</script>

<?php require '../includes/load_footer.php'; ?>
