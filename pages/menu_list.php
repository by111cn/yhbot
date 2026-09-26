<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '菜单列表';
$activePage = 'menu';
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'text';
    $content = trim($_POST['content'] ?? '');
    $botId = (int)($_POST['bot_id'] ?? 0);
    $apis = isset($_POST['apis']) ? json_encode($_POST['apis']) : '';

    if ($action === 'add') {
        if (empty($name) || $botId === 0) { $error = '菜单名称和机器人为必填项'; }
        else {
            $stmt = db()->prepare("INSERT INTO menus (user_id, bot_id, name, type, content, apis) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$userId, $botId, $name, $type, $content, $apis]);
            $success = '菜单添加成功';
        }
    } elseif ($action === 'edit') {
        $stmt = db()->prepare("UPDATE menus SET bot_id=?, name=?, type=?, content=?, apis=? WHERE id=? AND user_id=?");
        $stmt->execute([$botId, $name, $type, $content, $apis, $id, $userId]);
        $success = '菜单更新成功';
    }
}

$menus = getMenuList($userId);
$bots = getBotList($userId);
$allApis = getApiList($userId);

require '../includes/load_header.php';
?>

<style>
.menu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}
.menu-card{background:var(--bg-card);border-radius:var(--radius-md);border:1px solid var(--border);padding:20px 22px;transition:all var(--transition-base);position:relative;box-shadow:var(--shadow-xs)}
.menu-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:var(--primary-light)}
.menu-card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.menu-card-title{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.menu-card-title .dot{width:8px;height:8px;border-radius:50%;background:var(--success);flex-shrink:0}
.menu-card-title h4{font-size:15px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0}
.menu-actions{display:flex;gap:4px;opacity:0;transition:opacity var(--transition-fast);flex-shrink:0}
.menu-card:hover .menu-actions{opacity:1}
.menu-actions .btn-icon{width:30px;height:30px;border-radius:6px;border:1px solid var(--border);background:var(--bg-card);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);transition:all var(--transition-fast);font-size:12px}
.menu-actions .btn-icon:hover{background:var(--danger);color:#fff;border-color:var(--danger)}
.menu-actions .btn-icon.edit:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.menu-card-bot{font-size:13px;color:var(--primary);font-weight:500;margin-bottom:8px;display:flex;align-items:center;gap:4px}
.menu-card-content{font-size:13px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5;word-break:break-all;max-height:40px;overflow:hidden}
.menu-card-tags{display:flex;gap:6px;flex-wrap:wrap}
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>菜单列表</h3>
        <div class="flex-between gap-2">
            <button class="btn btn-primary" onclick="openModal('addMenuModal')">
                <i class="fas fa-plus"></i> 添加菜单
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="filter-bar">
            <select class="form-control" onchange="location.href='?bot_id='+this.value">
                <option value="">全部机器人</option>
                <?php foreach ($bots as $bot): ?>
                    <option value="<?php echo $bot['id']; ?>" <?php echo get('bot_id') == $bot['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bot['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="search-box" style="flex:1;">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="搜索菜单..."
                       onkeydown="if(event.key==='Enter')location.href='?search='+this.value">
            </div>
        </div>

        <?php if (empty($menus)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-layer-group"></i></div>
                <p>还没有菜单</p>
                <span class="subtext">点击右上角添加你的第一个菜单</span>
            </div>
        <?php else: ?>
            <div class="menu-grid">
                <?php foreach ($menus as $menu): ?>
                <div class="menu-card">
                    <div class="menu-card-top">
                        <div class="menu-card-title">
                            <span class="dot"></span>
                            <h4><?php echo htmlspecialchars($menu['name']); ?></h4>
                        </div>
                        <div class="menu-actions">
                            <button class="btn-icon edit" onclick="editMenu(<?php echo htmlspecialchars(json_encode($menu)); ?>)" title="编辑">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" onclick="confirmDelete('../api/menu.php?action=delete&id=<?php echo $menu['id']; ?>', '确定删除此菜单吗？')" title="删除">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="menu-card-bot">
                        <i class="fas fa-robot"></i> <?php echo htmlspecialchars($menu['bot_name'] ?? '未指定'); ?>
                    </div>
                    <div class="menu-card-content">
                        <?php echo htmlspecialchars(mb_substr($menu['content'], 0, 80) ?: '无内容'); ?>
                    </div>
                    <div class="menu-card-tags">
                        <span class="tag tag-green">权限: 全部</span>
                        <span class="tag tag-orange">类型: <?php echo $menu['type']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 添加模态框 -->
<div class="modal-overlay" id="addMenuModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>添加菜单</h3>
            <button class="modal-close" onclick="closeModal('addMenuModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>所属机器人 <span class="required">*</span></label>
                    <select name="bot_id" class="form-control" required>
                        <option value="">请选择机器人</option>
                        <?php foreach ($bots as $bot): ?>
                            <option value="<?php echo $bot['id']; ?>"><?php echo htmlspecialchars($bot['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>菜单名称 <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="如：站长工具" required>
                </div>
                <div class="form-group">
                    <label>菜单类型</label>
                    <select name="type" class="form-control">
                        <option value="text">文字菜单</option>
                        <option value="link">链接菜单</option>
                        <option value="api">API菜单</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>菜单内容</label>
                    <textarea name="content" class="form-control" placeholder="菜单显示内容"></textarea>
                </div>
                <div class="form-group">
                    <label>关联API</label>
                    <select name="apis[]" class="form-control" multiple style="height:120px;">
                        <?php foreach ($allApis as $api): ?>
                            <option value="<?php echo $api['id']; ?>"><?php echo htmlspecialchars($api['name']); ?> (<?php echo $api['command']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">按住 Ctrl 可多选</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMenuModal')">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑模态框 -->
<div class="modal-overlay" id="editMenuModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>编辑菜单</h3>
            <button class="modal-close" onclick="closeModal('editMenuModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editMenuId">
                <div class="form-group">
                    <label>所属机器人 <span class="required">*</span></label>
                    <select name="bot_id" id="editMenuBotId" class="form-control" required>
                        <?php foreach ($bots as $bot): ?>
                            <option value="<?php echo $bot['id']; ?>"><?php echo htmlspecialchars($bot['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>菜单名称 <span class="required">*</span></label>
                    <input type="text" name="name" id="editMenuName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>菜单类型</label>
                    <select name="type" id="editMenuType" class="form-control">
                        <option value="text">文字菜单</option>
                        <option value="link">链接菜单</option>
                        <option value="api">API菜单</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>菜单内容</label>
                    <textarea name="content" id="editMenuContent" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editMenuModal')">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function editMenu(menu) {
    document.getElementById('editMenuId').value = menu.id;
    document.getElementById('editMenuBotId').value = menu.bot_id;
    document.getElementById('editMenuName').value = menu.name;
    document.getElementById('editMenuType').value = menu.type;
    document.getElementById('editMenuContent').value = menu.content;
    openModal('editMenuModal');
}
</script>

<?php require '../includes/load_footer.php'; ?>
