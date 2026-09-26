<?php
/**
 * BotAPI 插件管理
 * 基于 PHP BotAPI 插件规范管理 .php 插件
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

// 插件开发权限：仅创作者或管理员
$canDev = canDevelopPlugin();

$userId = $_SESSION['user_id']; // 当前用户ID
$pageTitle = '插件管理';

// 获取用户的机器人列表（用于绑定插件）
$bots = db()->prepare("SELECT id, name FROM bots WHERE user_id = ? AND status = 1 ORDER BY id DESC");
$bots->execute([$userId]);
$botList = $bots->fetchAll();

// 获取当前选中的机器人 ID
$currentBotId = isset($_GET['bot_id']) ? intval($_GET['bot_id']) : 0;

// 查询插件列表
if ($currentBotId > 0) {
    $stmt = db()->prepare("SELECT * FROM plugins WHERE user_id = ? AND bot_id = ? ORDER BY id DESC");
    $stmt->execute([$userId, $currentBotId]);
} else {
    $stmt = db()->prepare("SELECT * FROM plugins WHERE user_id = ? ORDER BY bot_id, id DESC");
    $stmt->execute([$userId]);
}
$plugins = $stmt->fetchAll();

// 管理员可查看所有用户的插件
$isAdmin = isAdmin();
if ($isAdmin) {
    if ($currentBotId > 0) {
        $stmt = db()->query("SELECT p.*, u.username, b.name AS bot_name FROM plugins p 
            LEFT JOIN users u ON p.user_id = u.id 
            LEFT JOIN bots b ON p.bot_id = b.id 
            WHERE p.bot_id = {$currentBotId} 
            ORDER BY p.id DESC");
    } else {
        $stmt = db()->query("SELECT p.*, u.username, b.name AS bot_name FROM plugins p 
            LEFT JOIN users u ON p.user_id = u.id 
            LEFT JOIN bots b ON p.bot_id = b.id 
            ORDER BY p.bot_id, p.id DESC");
    }
    $plugins = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/load_header.php'; ?>

    <!-- 插件管理页面 -->
    <div class="page-header">
        <h2 class="page-title"><i class="icon">🧩</i> 插件管理</h2>
        <p class="page-desc">📖 <a href="<?php echo SITE_URL; ?>/pages/plugin_dev_doc.php">插件开发文档</a></p>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success alert-dismissible">
            <span>✅ 插件保存成功！</span>
            <button onclick="this.parentElement.remove()" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-info alert-dismissible">
            <span>🗑️ 插件已删除。</span>
            <button onclick="this.parentElement.remove()" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['uploaded'])): ?>
        <div class="alert alert-success alert-dismissible">
            <span>📄 插件文件上传成功！</span>
            <button onclick="this.parentElement.remove()" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 机器人选择器 -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">🤖 选择机器人</h3>
        </div>
        <div class="card-body">
            <div class="bot-selector">
                <a href="?bot_id=0" class="bot-chip <?= $currentBotId == 0 ? 'active' : '' ?>">
                    全部插件
                </a>
                <?php foreach ($botList as $bot): ?>
                <a href="?bot_id=<?= $bot['id'] ?>" class="bot-chip <?= $currentBotId == $bot['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($bot['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 操作栏 -->
    <div class="action-bar">
        <?php if ($canDev && ($currentBotId > 0 || count($botList) > 0)): ?>
        <button class="btn btn-primary" onclick="showPluginModal()">➕ 新建插件</button>
        <?php elseif ($canDev): ?>
        <button class="btn btn-primary" disabled title="请先创建机器人">➕ 新建插件</button>
        <small class="text-muted ml-2">请先在"官机管理"中创建机器人</small>
        <?php else: ?>
        <a href="<?php echo SITE_URL; ?>/pages/creator_apply.php" class="btn btn-primary">
            <i class="fas fa-pen-fancy"></i> 申请创作者
        </a>
        <?php endif; ?>
        <span class="plugin-count text-muted ml-auto">共 <?= count($plugins) ?> 个插件</span>
    </div>

    <!-- 插件列表 -->
    <div class="plugin-grid">
        <?php if (empty($plugins)): ?>
        <div class="empty-state">
            <div class="empty-icon">🧩</div>
            <h3>还没有插件</h3>
            <?php if ($canDev): ?>
            <p>点击上方"新建插件"按钮，上传您的 PHP 插件文件</p>
            <button class="btn btn-outline" onclick="showPluginModal()">创建第一个插件</button>
            <?php else: ?>
            <p>成为创作者后即可创建和管理自己的插件</p>
            <a href="<?php echo SITE_URL; ?>/pages/creator_apply.php" class="btn btn-outline">
                <i class="fas fa-pen-fancy"></i> 申请成为创作者
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($plugins as $plugin): 
            $commandList = json_decode($plugin['command_list'] ?? '[]', true) ?: [];
            $settingsSchema = json_decode($plugin['settings_schema'] ?? '[]', true) ?: [];
            $settingsValues = json_decode($plugin['settings_values'] ?? '{}', true) ?: [];
            $hasSettings = !empty($settingsSchema);
            $hasFile = !empty($plugin['plugin_file']) || !empty($plugin['plugin_code']);
        ?>
        <?php
            $marketLabels = ['', '🟡 待审核', '🟢 已上架', '🔴 已拒绝'];
            $marketStatus = isset($plugin['market_status']) ? intval($plugin['market_status']) : 0;
        ?>
        <div class="plugin-card <?= $plugin['enabled'] ? '' : 'disabled' ?>" id="plugin-<?= $plugin['id'] ?>">
            <div class="plugin-card-header">
                <div class="plugin-name">
                    <?= htmlspecialchars($plugin['name']) ?>
                    <?php if (!empty($plugin['version'])): ?>
                    <span class="plugin-version">v<?= htmlspecialchars($plugin['version']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="plugin-actions">
                    <!-- 启用/停用开关 -->
                    <label class="toggle-switch" title="<?= $plugin['enabled'] ? '停用' : '启用' ?>">
                        <input type="checkbox" <?= $plugin['enabled'] ? 'checked' : '' ?> 
                            onchange="togglePlugin(<?= $plugin['id'] ?>, this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                    <!-- 设置按钮 -->
                    <?php if ($hasSettings): ?>
                    <button class="btn btn-sm btn-outline" onclick="showSettingsModal(<?= $plugin['id'] ?>)" title="插件设置">
                        ⚙️
                    </button>
                    <?php endif; ?>
                    <?php if ($canDev): ?>
                    <!-- 编辑按钮（仅创作者/管理员） -->
                    <button class="btn btn-sm btn-outline" onclick="editPlugin(<?= $plugin['id'] ?>)" title="编辑">
                        ✏️
                    </button>
                    <!-- 发布到市场按钮（仅创作者/管理员） -->
                    <button class="btn btn-sm btn-outline" onclick="publishToMarket(<?= $plugin['id'] ?>)" title="发布到市场">
                        📦
                    </button>
                    <?php endif; ?>
                    <!-- 删除按钮 -->
                    <button class="btn btn-sm btn-danger-outline" onclick="deletePlugin(<?= $plugin['id'] ?>)" title="删除">
                        🗑️
                    </button>
                </div>
            </div>
            <div class="plugin-card-body">
                <div class="plugin-meta">
                    <?php if (!empty($plugin['author'])): ?>
                    <span class="plugin-author">👤 <?= htmlspecialchars($plugin['author']) ?></span>
                    <?php endif; ?>
                    <?php if (isset($plugin['bot_name'])): ?>
                    <span class="plugin-bot">🤖 <?= htmlspecialchars($plugin['bot_name']) ?></span>
                    <?php elseif (isset($plugin['username'])): ?>
                    <span class="plugin-owner">📝 <?= htmlspecialchars($plugin['username']) ?></span>
                    <?php endif; ?>
                    <?php if ($hasFile): ?>
                    <span class="plugin-has-file">📄 已上传源码</span>
                    <?php endif; ?>
                    <span class="plugin-status <?= $plugin['enabled'] ? 'on' : 'off' ?>">
                        <?= $plugin['enabled'] ? '🟢 已启用' : '🔴 已停用' ?>
                    </span>
                    <?php if ($marketStatus > 0): ?>
                    <span class="plugin-market-status">
                        <?= $marketLabels[$marketStatus] ?? '' ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($plugin['desc'])): ?>
                <div class="plugin-desc"><?= nl2br(htmlspecialchars($plugin['desc'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($commandList)): ?>
                <div class="plugin-commands">
                    <small class="text-muted">📋 命令列表：</small>
                    <div class="command-tags">
                        <?php foreach ($commandList as $cmd): ?>
                        <span class="command-tag"><?= htmlspecialchars($cmd) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($hasSettings): ?>
                <div class="plugin-settings-hint">
                    <small>⚙️ <?= count($settingsSchema) ?> 项可配置参数</small>
                </div>
                <?php endif; ?>
                <!-- 绑定机器人 -->
                <div class="plugin-bind-bot">
                    <small class="text-muted">🤖 绑定机器人：</small>
                    <select class="form-control bot-bind-select" onchange="bindBot(<?= $plugin['id'] ?>, this.value)" style="width:auto;display:inline-block;font-size:12px;padding:2px 8px;">
                        <option value="0" <?= ($plugin['bot_id'] ?? 0) == 0 ? 'selected' : ''; ?>>未绑定</option>
                        <?php foreach ($botList as $bot): ?>
                        <option value="<?= $bot['id'] ?>" <?= ($plugin['bot_id'] ?? 0) == $bot['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($bot['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="plugin-card-footer">
                <small class="text-muted">创建：<?= htmlspecialchars($plugin['created_at']) ?></small>
                <?php if ($plugin['updated_at'] != $plugin['created_at']): ?>
                <small class="text-muted"> | 更新：<?= htmlspecialchars($plugin['updated_at']) ?></small>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($canDev): ?>
<!-- ===== 插件编辑/新建弹窗 ===== -->
<div class="modal-overlay" id="pluginModal" style="display:none;">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="pluginModalTitle">新建插件</h3>
            <button class="modal-close" onclick="closePluginModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="pluginForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="pluginId" value="">
                <input type="hidden" name="bot_id" id="pluginBotId" value="<?= $currentBotId ?>">

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label>插件名称 <span class="required">*</span></label>
                        <input type="text" name="name" id="pluginName" class="form-control" 
                            placeholder="例如：天气查询" required maxlength="100">
                    </div>
                    <div class="form-group flex-1">
                        <label>版本号</label>
                        <input type="text" name="version" id="pluginVersion" class="form-control" 
                            placeholder="1.0.0" value="1.0.0" maxlength="20">
                    </div>
                </div>

                <div class="form-group">
                    <label>作者</label>
                    <input type="text" name="author" id="pluginAuthor" class="form-control" 
                        placeholder="插件的作者名称" maxlength="50">
                </div>

                <div class="form-group">
                    <label>插件说明</label>
                    <textarea name="desc" id="pluginDesc" class="form-control" rows="3" 
                        placeholder="描述插件的功能和用途..."></textarea>
                </div>

                <div class="form-group">
                    <label>命令列表</label>
                    <div id="commandList">
                        <div class="command-input-row">
                            <input type="text" class="form-control command-input" placeholder="输入命令，如 /weather" maxlength="100">
                            <button type="button" class="btn btn-sm btn-outline" onclick="addCommand(this)">+</button>
                        </div>
                    </div>
                    <small class="text-muted">插件支持的指令列表，对应 <code>group()</code> / <code>C2C()</code> 中解析的命令</small>
                </div>

                <div class="form-group">
                    <label>插件源码 *</label>
                    <div class="upload-area">
                        <div class="file-upload-wrapper">
                            <button type="button" class="btn btn-sm btn-outline file-select-btn" onclick="document.querySelector('input[name=\'plugin_file_upload\']').click()">选择文件</button>
                            <span id="fileStatus" class="file-status">未选择文件</span>
                            <input type="file" name="plugin_file_upload" class="form-control file-input-hidden" accept=".php" 
                                onchange="onFileSelected(this)">
                        </div>
                        <small class="text-muted">上传 .php 格式的 PHP 插件文件，最大 5MB</small>
                        <div id="filePreview" class="file-preview" style="display:none;"></div>
                        <input type="hidden" name="existing_file" id="existingFile" value="">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        设置页面设计器
                        <small class="text-muted">（JSON 格式，定义插件的可配置参数）</small>
                    </label>
                    <textarea name="settings_schema" id="settingsSchema" class="form-control code-editor" rows="8" 
                        placeholder='每项格式：{"type": "类型", "name": "参数名", "label": "显示名", ...}

可用 type：text, password, textarea, number, select, switch

示例：
[
  {"type": "text", "name": "api_key", "label": "API Key", "placeholder": "请输入API Key"},
  {"type": "password", "name": "secret", "label": "密钥"},
  {"type": "number", "name": "max_tokens", "label": "最大Token数", "default": 100},
  {"type": "select", "name": "model", "label": "模型", "options": [
    {"value": "gpt-3.5", "label": "GPT-3.5"},
    {"value": "gpt-4", "label": "GPT-4"}
  ]},
  {"type": "switch", "name": "debug", "label": "调试模式", "default": false}
]'></textarea>
                </div>

                <div class="form-group">
                    <label>归属机器人</label>
                    <select name="bot_id" id="pluginBotIdSelect" class="form-control" 
                        onchange="document.getElementById('pluginBotId').value=this.value">
                        <option value="0">不绑定</option>
                        <?php foreach ($botList as $bot): ?>
                        <option value="<?= $bot['id'] ?>" <?= $currentBotId == $bot['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bot['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closePluginModal()">取消</button>
            <button class="btn btn-primary" onclick="savePlugin()" id="savePluginBtn">💾 保存插件</button>
        </div>
    </div>
</div>

<!-- ===== 插件设置弹窗 ===== -->
<div class="modal-overlay" id="settingsModal" style="display:none;">
    <div class="modal modal-md">
        <div class="modal-header">
            <h3>⚙️ 插件设置</h3>
            <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="settingsFormContainer">
                <p class="text-muted text-center">加载中...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeSettingsModal()">取消</button>
            <button class="btn btn-primary" onclick="saveSettings()">💾 保存设置</button>
        </div>
    </div>
</div>

<script>
// ===== 插件数据缓存 =====
let pluginsData = <?= json_encode($plugins, JSON_UNESCAPED_UNICODE) ?>;

// ===== 弹窗操作 =====
function showPluginModal() {
    document.getElementById('pluginForm').reset();
    document.getElementById('pluginId').value = '';
    document.getElementById('pluginBotId').value = '<?= $currentBotId ?>';
    document.getElementById('pluginBotIdSelect').value = '<?= $currentBotId ?>';
    document.getElementById('pluginVersion').value = '1.0.0';
    document.getElementById('pluginModalTitle').textContent = '新建插件';
    document.getElementById('settingsSchema').value = '';
    // Reset command list
    document.getElementById('commandList').innerHTML = '<div class="command-input-row"><input type="text" class="form-control command-input" placeholder="输入命令，如 /weather" maxlength="100"><button type="button" class="btn btn-sm btn-outline" onclick="addCommand(this)">+</button></div>';
    // Reset file
    document.querySelector('input[name="plugin_file_upload"]').value = '';
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('existingFile').value = '';
    document.getElementById('pluginModal').style.display = 'flex';
}

function closePluginModal() {
    document.getElementById('pluginModal').style.display = 'none';
}

function closeSettingsModal() {
    document.getElementById('settingsModal').style.display = 'none';
}

function onFileSelected(input) {
    const file = input.files[0];
    const preview = document.getElementById('filePreview');
    const status = document.getElementById('fileStatus');
    if (file) {
        preview.innerHTML = '📄 <strong>' + file.name + '</strong> (' + (file.size / 1024).toFixed(1) + ' KB)';
        preview.style.display = 'block';
        status.textContent = file.name;
        status.style.color = '#1e293b';
    } else {
        preview.style.display = 'none';
        status.textContent = '未选择文件';
        status.style.color = '#64748b';
    }
}

// ===== 命令列表管理 =====
function addCommand(btn) {
    const row = document.createElement('div');
    row.className = 'command-input-row';
    row.innerHTML = '<input type="text" class="form-control command-input" placeholder="输入命令" maxlength="100"><button type="button" class="btn btn-sm btn-danger-outline" onclick="this.parentElement.remove()">-</button>';
    btn.parentElement.parentElement.appendChild(row);
}

function getCommands() {
    const inputs = document.querySelectorAll('.command-input');
    const commands = [];
    inputs.forEach(input => {
        const val = input.value.trim();
        if (val) commands.push(val);
    });
    return commands;
}

function setCommands(commands) {
    const container = document.getElementById('commandList');
    if (!commands || commands.length === 0) {
        container.innerHTML = '<div class="command-input-row"><input type="text" class="form-control command-input" placeholder="输入命令，如 /weather" maxlength="100"><button type="button" class="btn btn-sm btn-outline" onclick="addCommand(this)">+</button></div>';
        return;
    }
    container.innerHTML = '';
    commands.forEach((cmd, idx) => {
        const row = document.createElement('div');
        row.className = 'command-input-row';
        row.innerHTML = `<input type="text" class="form-control command-input" value="${escapeHtml(cmd)}" maxlength="100">
            <button type="button" class="btn btn-sm ${idx === 0 ? 'btn-outline' : 'btn-danger-outline'}" 
            onclick="${idx === 0 ? 'addCommand(this)' : 'this.parentElement.remove()'}">${idx === 0 ? '+' : '-'}</button>`;
        container.appendChild(row);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== 保存插件 =====
function savePlugin() {
    const id = document.getElementById('pluginId').value;
    const botId = document.getElementById('pluginBotId').value || document.getElementById('pluginBotIdSelect').value;
    const name = document.getElementById('pluginName').value.trim();
    const version = document.getElementById('pluginVersion').value.trim();
    const author = document.getElementById('pluginAuthor').value.trim();
    const desc = document.getElementById('pluginDesc').value.trim();
    const commandList = getCommands();
    const settingsSchema = document.getElementById('settingsSchema').value.trim();

    if (!name) { alert('请输入插件名称'); return; }

    // Validate settings_schema JSON if provided
    if (settingsSchema) {
        try { JSON.parse(settingsSchema); }
        catch(e) { alert('设置页面设计器 JSON 格式错误: ' + e.message); return; }
    }

    const formData = new FormData();
    formData.append('id', id);
    formData.append('bot_id', botId);
    formData.append('name', name);
    formData.append('version', version);
    formData.append('author', author);
    formData.append('desc', desc);
    formData.append('command_list', JSON.stringify(commandList));
    formData.append('settings_schema', settingsSchema);
    formData.append('existing_file', document.getElementById('existingFile').value);

    // 上传文件
    const fileInput = document.querySelector('input[name="plugin_file_upload"]');
    if (fileInput.files[0]) {
        formData.append('plugin_file_upload', fileInput.files[0]);
    }

    const btn = document.getElementById('savePluginBtn');
    btn.disabled = true;
    btn.textContent = '保存中...';

    fetch('../api/plugin.php?action=save', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '💾 保存插件';
        if (data.success) {
            window.location.href = '?bot_id=' + encodeURIComponent(botId) + '&saved=1';
        } else {
            alert('保存失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '💾 保存插件';
        alert('请求失败: ' + err.message);
    });
}

// ===== 编辑插件 =====
function editPlugin(pluginId) {
    const plugin = pluginsData.find(p => p.id == pluginId);
    if (!plugin) { alert('插件不存在'); return; }

    document.getElementById('pluginId').value = plugin.id;
    document.getElementById('pluginName').value = plugin.name || '';
    document.getElementById('pluginVersion').value = plugin.version || '1.0.0';
    document.getElementById('pluginAuthor').value = plugin.author || '';
    document.getElementById('pluginDesc').value = plugin.desc || '';
    document.getElementById('settingsSchema').value = plugin.settings_schema ? JSON.stringify(JSON.parse(plugin.settings_schema), null, 2) : '';
    document.getElementById('pluginBotId').value = plugin.bot_id;
    document.getElementById('pluginBotIdSelect').value = plugin.bot_id;
    document.getElementById('existingFile').value = plugin.plugin_file || '';
    document.getElementById('pluginModalTitle').textContent = '编辑插件';

    // 设置命令列表
    const commands = JSON.parse(plugin.command_list || '[]');
    setCommands(commands);

    // 文件预览
    if (plugin.plugin_file) {
        document.getElementById('filePreview').innerHTML = '📄 已有文件: <strong>' + plugin.plugin_file + '</strong>';
        document.getElementById('filePreview').style.display = 'block';
    } else {
        document.getElementById('filePreview').style.display = 'none';
    }

    document.getElementById('pluginModal').style.display = 'flex';
}

// ===== 显示设置弹窗 =====
function showSettingsModal(pluginId) {
    const plugin = pluginsData.find(p => p.id == pluginId);
    if (!plugin) return;

    let schema = [];
    try { schema = JSON.parse(plugin.settings_schema || '[]'); } catch(e) {}
    let values = {};
    try { values = JSON.parse(plugin.settings_values || '{}'); } catch(e) {}

    let html = `<input type="hidden" id="settingsPluginId" value="${pluginId}"><div class="settings-form">`;

    if (schema.length === 0) {
        html += '<p class="text-muted text-center">此插件没有可配置参数</p>';
    }

    schema.forEach((item, idx) => {
        const val = values[item.name] !== undefined ? values[item.name] : (item.default !== undefined ? item.default : '');
        html += `<div class="form-group">`;
        html += `<label>${escapeHtml(item.label || item.name)}</label>`;

        switch (item.type) {
            case 'text':
            case 'password':
                html += `<input type="${item.type}" name="setting_${item.name}" class="form-control" 
                    value="${escapeHtml(String(val))}" placeholder="${escapeHtml(item.placeholder || '')}">`;
                break;
            case 'textarea':
                html += `<textarea name="setting_${item.name}" class="form-control" rows="4" 
                    placeholder="${escapeHtml(item.placeholder || '')}">${escapeHtml(String(val))}</textarea>`;
                break;
            case 'number':
                html += `<input type="number" name="setting_${item.name}" class="form-control" 
                    value="${escapeHtml(String(val))}" placeholder="${escapeHtml(item.placeholder || '')}">`;
                break;
            case 'select':
                html += `<select name="setting_${item.name}" class="form-control">`;
                if (item.options && Array.isArray(item.options)) {
                    item.options.forEach(opt => {
                        const selected = (opt.value == val) ? 'selected' : '';
                        html += `<option value="${escapeHtml(String(opt.value))}" ${selected}>${escapeHtml(opt.label)}</option>`;
                    });
                }
                html += `</select>`;
                break;
            case 'switch':
                const checked = val === true || val === 'true' || val === 1 || val === '1' ? 'checked' : '';
                html += `<label class="toggle-switch"><input type="checkbox" name="setting_${item.name}" ${checked}><span class="toggle-slider"></span></label>`;
                break;
        }
        if (item.desc) {
            html += `<small class="text-muted">${escapeHtml(item.desc)}</small>`;
        }
        html += `</div>`;
    });
    html += '</div>';

    document.getElementById('settingsFormContainer').innerHTML = html;
    document.getElementById('settingsModal').style.display = 'flex';
}

// ===== 保存设置 =====
function saveSettings() {
    const pluginId = document.getElementById('settingsPluginId').value;
    const plugin = pluginsData.find(p => p.id == pluginId);
    if (!plugin) return;

    let schema = [];
    try { schema = JSON.parse(plugin.settings_schema || '[]'); } catch(e) {}

    const values = {};
    schema.forEach(item => {
        const el = document.querySelector(`[name="setting_${item.name}"]`);
        if (!el) return;
        if (item.type === 'switch') {
            values[item.name] = el.checked;
        } else {
            values[item.name] = el.value;
        }
    });

    fetch('../api/plugin.php?action=save_settings', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(pluginId) + '&settings_values=' + encodeURIComponent(JSON.stringify(values))
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeSettingsModal();
            // 更新本地缓存
            plugin.settings_values = JSON.stringify(values);
            window.location.href = '?bot_id=' + encodeURIComponent('<?= $currentBotId ?>') + '&saved=1';
        } else {
            alert('保存失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(err => {
        alert('请求失败: ' + err.message);
    });
}

// ===== 切换插件启用/停用 =====
function togglePlugin(id, enabled) {
    fetch('../api/plugin.php?action=toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id) + '&enabled=' + (enabled ? 1 : 0)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('操作失败: ' + (data.message || '未知错误'));
            // 恢复开关
            const card = document.getElementById('plugin-' + id);
            if (card) {
                const toggle = card.querySelector('input[type=checkbox]');
                if (toggle) toggle.checked = !enabled;
            }
            return;
        }
        // 更新卡片样式
        const card = document.getElementById('plugin-' + id);
        if (enabled) {
            card.classList.remove('disabled');
            card.querySelector('.plugin-status').className = 'plugin-status on';
            card.querySelector('.plugin-status').textContent = '🟢 已启用';
        } else {
            card.classList.add('disabled');
            card.querySelector('.plugin-status').className = 'plugin-status off';
            card.querySelector('.plugin-status').textContent = '🔴 已停用';
        }
        // 更新缓存
        const plugin = pluginsData.find(p => p.id == id);
        if (plugin) plugin.enabled = enabled ? 1 : 0;
    })
    .catch(err => alert('请求失败: ' + err.message));
}

// ===== 删除插件 =====
function deletePlugin(id) {
    if (!confirm('确定要删除此插件吗？此操作不可撤销！')) return;
    fetch('../api/plugin.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '?bot_id=<?= $currentBotId ?>&deleted=1';
        } else {
            alert('删除失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(err => alert('请求失败: ' + err.message));
}

// ===== 绑定机器人 =====
function bindBot(id, botId) {
    fetch('../api/plugin.php?action=bind_bot', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id) + '&bot_id=' + encodeURIComponent(botId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || '绑定成功', 'success');
        } else {
            alert('绑定失败: ' + (data.message || '未知错误'));
            location.reload();
        }
    })
    .catch(err => alert('请求失败: ' + err.message));
}

// ===== 发布到市场（提交审核）=====
function publishToMarket(id) {
    if (!confirm('确定将此插件提交到市场审核吗？')) return;
    fetch('../api/plugin_market.php?action=publish', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message || '提交成功');
        } else {
            alert('操作失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(err => alert('请求失败: ' + err.message));
}

// ===== 点击遮罩关闭弹窗 =====
window.onclick = function(event) {
    if (event.target === document.getElementById('pluginModal')) closePluginModal();
    if (event.target === document.getElementById('settingsModal')) closeSettingsModal();
};

// ===== ESC 关闭弹窗 =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePluginModal();
        closeSettingsModal();
    }
});
</script>

<style>
/* 插件管理专用样式 */
.plugin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.plugin-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
}
.plugin-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: #6366f1; }
.plugin-card.disabled { opacity: 0.65; }

.plugin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.plugin-name {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.plugin-version {
    display: inline-block;
    background: #e0e7ff;
    color: #4338ca;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 6px;
}
.plugin-actions {
    display: flex;
    gap: 4px;
    align-items: center;
    flex-shrink: 0;
}

.plugin-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    color: #64748b;
}
.plugin-author, .plugin-bot, .plugin-owner, .plugin-has-file {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 4px;
}
.plugin-status { font-weight: 500; }
.plugin-status.on { color: #10b981; }
.plugin-status.off { color: #ef4444; }
.plugin-market-status {
    background: #fef3c7;
    color: #92400e;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.plugin-desc {
    font-size: 13px;
    color: #475569;
    margin-bottom: 8px;
    line-height: 1.5;
}

.plugin-commands { margin-bottom: 8px; }
.command-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
}
.command-tag {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-family: monospace;
}

.plugin-settings-hint { margin-top: 8px; }
.plugin-bind-bot { margin-top: 8px; display: flex; align-items: center; gap: 6px; }
.bot-bind-select { cursor: pointer; }

.plugin-card-footer {
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px solid #f1f5f9;
}

/* 机器人选择器 */
.bot-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.bot-chip {
    display: inline-block;
    padding: 6px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: 13px;
    text-decoration: none;
    color: #475569;
    transition: all 0.2s;
}
.bot-chip:hover { border-color: #6366f1; color: #4338ca; }
.bot-chip.active { background: #6366f1; color: #fff; border-color: #6366f1; }

/* 操作栏 */
.action-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

/* 命令输入行 */
.command-input-row {
    display: flex;
    gap: 6px;
    margin-bottom: 6px;
}
.command-input-row input { flex: 1; }

/* 上传区域 */
.upload-area {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
}

/* 文件预览 */
.file-preview {
    margin-top: 8px;
    padding: 8px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    font-size: 13px;
}

/* 文件上传包装器 */
.file-upload-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
.file-select-btn {
    flex-shrink: 0;
}
.file-status {
    flex: 1;
    font-size: 13px;
    color: #64748b;
}
.file-input-hidden {
    display: none;
}

/* 弹窗增强 */
.modal-lg { max-width: 700px; }
.modal-md { max-width: 500px; }
#pluginModal .modal {
    max-height: 85vh;
}
#pluginModal .modal-body {
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}
#pluginModal .modal-body::-webkit-scrollbar {
    width: 6px;
}
#pluginModal .modal-body::-webkit-scrollbar-track {
    background: transparent;
}
#pluginModal .modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
#pluginModal .modal-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* 开关 */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
}
.toggle-switch input { display: none; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: #cbd5e1;
    border-radius: 22px;
    transition: 0.3s;
}
.toggle-slider:before {
    content: "";
    position: absolute;
    height: 16px; width: 16px;
    left: 3px; bottom: 3px;
    background: #fff;
    border-radius: 50%;
    transition: 0.3s;
}
input:checked + .toggle-slider { background: #6366f1; }
input:checked + .toggle-slider:before { transform: translateX(18px); }

/* 设置表单 */
.settings-form { max-height: 400px; overflow-y: auto; }

/* 空状态 */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
}
.empty-icon { font-size: 48px; margin-bottom: 12px; }
.empty-state h3 { color: #334155; margin-bottom: 8px; }
.empty-state p { color: #64748b; margin-bottom: 16px; }

/* 表单行 */
.form-row { display: flex; gap: 12px; }
.flex-1 { flex: 1; }

/* 响应式 */
@media (max-width: 768px) {
    .plugin-grid { grid-template-columns: 1fr; }
    .form-row { flex-direction: column; }
    .modal-lg, .modal-md { max-width: 95vw; }
}
</style>

<?php endif; ?>

<?php require __DIR__ . '/../includes/load_footer.php'; ?>
