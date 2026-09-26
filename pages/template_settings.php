<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '模板设置';
$activePage = 'template_settings';
$userId = $_SESSION['user_id'];

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'reply';
    $content = trim($_POST['content'] ?? '');
    
    if ($action === 'add') {
        if (empty($name) || empty($content)) {
            $error = '模板名称和内容不能为空';
        } else {
            $stmt = db()->prepare("INSERT INTO templates (user_id, name, type, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $type, $content]);
            $success = '模板添加成功';
        }
    } elseif ($action === 'edit') {
        $stmt = db()->prepare("UPDATE templates SET name = ?, type = ?, content = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $type, $content, $id, $userId]);
        $success = '模板更新成功';
    }
}

// 获取模板列表
$stmt = db()->prepare("SELECT * FROM templates WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$templates = $stmt->fetchAll();

require '../includes/load_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>模板设置</h3>
        <button class="btn btn-primary" onclick="openModal('addTemplateModal')">
            <i class="fas fa-plus"></i> 添加模板
        </button>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab-item active" data-target="tab-reply">回复模板</div>
            <div class="tab-item" data-target="tab-welcome">欢迎模板</div>
            <div class="tab-item" data-target="tab-unknown">未知指令模板</div>
        </div>
        
        <div class="tab-pane active" id="tab-reply">
            <?php 
            $replyTemplates = array_filter($templates, fn($t) => $t['type'] === 'reply');
            if (empty($replyTemplates)): 
            ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>暂无回复模板</p>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($replyTemplates as $tpl): ?>
                        <div class="item-card">
                            <div class="card-actions">
                                <button class="btn-icon edit" onclick="editTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="confirmDelete('../api/category.php?action=delete_template&id=<?php echo $tpl['id']; ?>&csrf=<?php echo csrfToken(); ?>', '确定删除此模板吗？')" title="删除">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="item-card-title"><?php echo htmlspecialchars($tpl['name']); ?></div>
                            <div class="item-card-desc" style="margin-top: 8px; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars(mb_substr($tpl['content'], 0, 100))); ?><?php echo mb_strlen($tpl['content']) > 100 ? '...' : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-pane" id="tab-welcome">
            <?php 
            $welcomeTemplates = array_filter($templates, fn($t) => $t['type'] === 'welcome');
            if (empty($welcomeTemplates)): 
            ?>
                <div class="empty-state">
                    <i class="fas fa-hand-sparkles"></i>
                    <p>暂无欢迎模板</p>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($welcomeTemplates as $tpl): ?>
                        <div class="item-card">
                            <div class="card-actions">
                                <button class="btn-icon edit" onclick="editTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="confirmDelete('../api/category.php?action=delete_template&id=<?php echo $tpl['id']; ?>&csrf=<?php echo csrfToken(); ?>', '确定删除此模板吗？')" title="删除">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="item-card-title"><?php echo htmlspecialchars($tpl['name']); ?></div>
                            <div class="item-card-desc" style="margin-top: 8px; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars(mb_substr($tpl['content'], 0, 100))); ?><?php echo mb_strlen($tpl['content']) > 100 ? '...' : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-pane" id="tab-unknown">
            <?php 
            $unknownTemplates = array_filter($templates, fn($t) => $t['type'] === 'unknown');
            if (empty($unknownTemplates)): 
            ?>
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <p>暂无未知指令模板</p>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($unknownTemplates as $tpl): ?>
                        <div class="item-card">
                            <div class="card-actions">
                                <button class="btn-icon edit" onclick="editTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="confirmDelete('../api/category.php?action=delete_template&id=<?php echo $tpl['id']; ?>&csrf=<?php echo csrfToken(); ?>', '确定删除此模板吗？')" title="删除">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="item-card-title"><?php echo htmlspecialchars($tpl['name']); ?></div>
                            <div class="item-card-desc" style="margin-top: 8px; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars(mb_substr($tpl['content'], 0, 100))); ?><?php echo mb_strlen($tpl['content']) > 100 ? '...' : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 添加模板模态框 -->
<div class="modal-overlay" id="addTemplateModal">
    <div class="modal">
        <div class="modal-header">
            <h3>添加模板</h3>
            <button class="modal-close" onclick="closeModal('addTemplateModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>模板名称 <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="模板名称" required>
                </div>
                <div class="form-group">
                    <label>模板类型</label>
                    <select name="type" class="form-control" id="templateType">
                        <option value="reply">回复模板</option>
                        <option value="welcome">欢迎模板</option>
                        <option value="unknown">未知指令模板</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>模板内容 <span class="required">*</span></label>
                    <textarea name="content" class="form-control" rows="6" placeholder="模板内容，支持变量..." required></textarea>
                    <small style="color: var(--text-light);">可用变量: {user_name} {bot_name} {time} {date} {result}</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTemplateModal')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑模板模态框 -->
<div class="modal-overlay" id="editTemplateModal">
    <div class="modal">
        <div class="modal-header">
            <h3>编辑模板</h3>
            <button class="modal-close" onclick="closeModal('editTemplateModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editTplId">
                <div class="form-group">
                    <label>模板名称 <span class="required">*</span></label>
                    <input type="text" name="name" id="editTplName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>模板类型</label>
                    <select name="type" id="editTplType" class="form-control">
                        <option value="reply">回复模板</option>
                        <option value="welcome">欢迎模板</option>
                        <option value="unknown">未知指令模板</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>模板内容 <span class="required">*</span></label>
                    <textarea name="content" id="editTplContent" class="form-control" rows="6" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTemplateModal')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTemplate(tpl) {
    document.getElementById('editTplId').value = tpl.id;
    document.getElementById('editTplName').value = tpl.name;
    document.getElementById('editTplType').value = tpl.type;
    document.getElementById('editTplContent').value = tpl.content;
    openModal('editTemplateModal');
}
</script>

<?php require '../includes/load_footer.php'; ?>