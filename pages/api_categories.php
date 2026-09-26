<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '分类管理';
$activePage = 'api_categories';
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-link');
    $color = trim($_POST['color'] ?? '#4f46e5');
    $sort = (int)($_POST['sort_order'] ?? 0);

    if ($action === 'add') {
        if (empty($name)) { $error = '分类名称不能为空'; }
        else {
            $stmt = db()->prepare("INSERT INTO api_categories (user_id, name, icon, color, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $icon, $color, $sort]);
            $success = '分类添加成功';
        }
    } elseif ($action === 'edit') {
        $stmt = db()->prepare("UPDATE api_categories SET name=?, icon=?, color=?, sort_order=? WHERE id=? AND user_id=?");
        $stmt->execute([$name, $icon, $color, $sort, $id, $userId]);
        $success = '分类更新成功';
    }
}

$categories = getCategories($userId);
$iconList = ['fa-link','fa-search','fa-gamepad','fa-wrench','fa-image','fa-music','fa-video','fa-star','fa-heart','fa-cloud','fa-code','fa-globe','fa-terminal','fa-database','fa-chart-line','fa-cog'];

require '../includes/load_header.php';
?>

<style>
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px}
.cat-card{background:var(--bg-card);border-radius:var(--radius-md);border:1px solid var(--border);padding:22px;transition:all var(--transition-base);position:relative;box-shadow:var(--shadow-xs);overflow:hidden}
.cat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:transparent}
.cat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--cat-color,var(--primary));opacity:0.3;transition:opacity var(--transition-base)}
.cat-card:hover::before{opacity:1}
.cat-card-top{display:flex;align-items:center;gap:14px;margin-bottom:16px}
.cat-icon{width:52px;height:52px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;box-shadow:0 4px 15px rgba(0,0,0,0.15)}
.cat-info h4{font-size:15px;font-weight:600;margin-bottom:3px}
.cat-info p{font-size:13px;color:var(--text-muted);margin:0;display:flex;align-items:center;gap:4px}
.cat-actions{position:absolute;top:16px;right:16px;display:flex;gap:6px;opacity:0;transition:opacity var(--transition-fast);z-index:2}
.cat-card:hover .cat-actions{opacity:1}
.cat-btn{width:32px;height:32px;border-radius:var(--radius-xs);border:1px solid var(--border);background:var(--bg-card);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);transition:all var(--transition-fast);font-size:13px;box-shadow:var(--shadow-xs)}
.cat-btn:hover{background:var(--danger);color:#fff;border-color:var(--danger);transform:scale(1.05)}
.cat-btn.edit:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.cat-footer{display:flex;gap:6px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border-light);flex-wrap:wrap}
.cat-footer .btn{flex:1;min-width:0;justify-content:center;font-size:12px;padding:8px 6px;white-space:nowrap}
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>分类管理</h3>
        <button class="btn btn-primary" onclick="openModal('addCategoryModal')">
            <i class="fas fa-plus"></i> 添加分类
        </button>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                <p>还没有分类</p>
                <span class="subtext">创建分类来更好地管理你的API</span>
            </div>
        <?php else: ?>
            <div class="cat-grid">
                <?php foreach ($categories as $cat):
                    $apiCount = countTable('apis', 'category_id = ?', [$cat['id']]);
                ?>
                <div class="cat-card" style="--cat-color:<?php echo htmlspecialchars($cat['color']); ?>;">
                    <div class="cat-actions">
                        <button class="cat-btn edit" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" title="编辑">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="cat-btn" onclick="confirmDelete('../api/category.php?action=delete&id=<?php echo $cat['id']; ?>', '确定删除分类「<?php echo htmlspecialchars($cat['name']); ?>」吗？')" title="删除">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="cat-card-top">
                        <div class="cat-icon" style="background:<?php echo htmlspecialchars($cat['color']); ?>;">
                            <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                        </div>
                        <div class="cat-info">
                            <h4><?php echo htmlspecialchars($cat['name']); ?></h4>
                            <p><i class="fas fa-cube"></i> <?php echo $apiCount; ?> 个API</p>
                        </div>
                    </div>
                    <div class="cat-footer">
                        <button class="btn btn-secondary" onclick="copyToClipboard('<?php echo htmlspecialchars($cat['name']); ?>')" title="复制分类名称">
                            <i class="fas fa-copy"></i> 复制
                        </button>
                        <a href="api_list.php?category_id=<?php echo $cat['id']; ?>" class="btn btn-primary" title="查看该分类下的API">
                            <i class="fas fa-external-link-alt"></i> 查看
                        </a>
                        <button class="btn btn-danger" onclick="confirmDelete('../api/category.php?action=delete&id=<?php echo $cat['id']; ?>', '确定删除分类「<?php echo htmlspecialchars($cat['name']); ?>」吗？该操作不可恢复！')" title="删除该分类">
                            <i class="fas fa-trash"></i> 删除
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 添加模态框 -->
<div class="modal-overlay" id="addCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>添加分类</h3>
            <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>分类名称 <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="请输入分类名称" required>
                </div>
                <div class="form-group">
                    <label>分类图标</label>
                    <div class="icon-picker">
                        <?php foreach ($iconList as $i => $ic): ?>
                            <div class="icon-option <?php echo $i === 0 ? 'selected' : ''; ?>" data-icon="<?php echo $ic; ?>">
                                <i class="fas <?php echo $ic; ?>"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="icon" value="fa-link" id="addIconInput">
                </div>
                <div class="form-group">
                    <label>颜色</label>
                    <input type="color" name="color" class="form-control" value="#6366f1" style="height:42px;padding:4px 8px;">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" class="form-control" value="0" placeholder="数字越小越靠前">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑模态框 -->
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>编辑分类</h3>
            <button class="modal-close" onclick="closeModal('editCategoryModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editCatId">
                <div class="form-group">
                    <label>分类名称 <span class="required">*</span></label>
                    <input type="text" name="name" id="editCatName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>分类图标</label>
                    <input type="text" name="icon" id="editCatIcon" class="form-control">
                </div>
                <div class="form-group">
                    <label>颜色</label>
                    <input type="color" name="color" id="editCatColor" class="form-control" style="height:42px;padding:4px 8px;">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" id="editCatSort" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(cat) {
    document.getElementById('editCatId').value = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatIcon').value = cat.icon;
    document.getElementById('editCatColor').value = cat.color;
    document.getElementById('editCatSort').value = cat.sort_order;
    openModal('editCategoryModal');
}
</script>

<?php require '../includes/load_footer.php'; ?>
