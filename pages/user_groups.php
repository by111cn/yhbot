<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '用户组';
$activePage = 'user_groups';

// 统计各角色人数
$adminCount = countTable('users', 'role = 0', []);
$creatorCount = countTable('users', 'role = 2', []);
$normalCount = countTable('users', 'role = 1', []);
$totalUsers = countTable('users', '1=1', []);

// 处理添加用户组
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_group') {
        $groupName = trim($_POST['group_name'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $displayName = trim($_POST['display_name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $bgColor = trim($_POST['bg_color'] ?? '');
        $textColor = trim($_POST['text_color'] ?? '#fff');
        $description = trim($_POST['description'] ?? '');

        if ($groupName && $roleId > 2 && $displayName) {
            try {
                // 检查 role_id 是否已存在
                $stmt = db()->prepare("SELECT id FROM user_roles WHERE role_id = ?");
                $stmt->execute([$roleId]);
                if ($stmt->fetch()) {
                    $error = '角色ID已存在，请使用其他ID';
                } else {
                    $stmt = db()->prepare("INSERT INTO user_roles (group_name, role_id, display_name, icon, bg_color, text_color, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$groupName, $roleId, $displayName, $icon, $bgColor, $textColor, $description]);
                    $success = '用户组添加成功';
                }
            } catch (Exception $e) {
                $error = '添加失败：' . $e->getMessage();
            }
        } else {
            $error = '请填写必填项，且角色ID需大于2';
        }
    }
}

require '../includes/load_header.php';
?>

<style>
.group-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.group-card{border-radius:12px;padding:20px;text-align:center;border:1px solid var(--border);transition:all 0.2s;cursor:pointer;}
.group-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.06);}
.group-card.admin{background:linear-gradient(135deg,#fef2f2,#fee2e2);border-color:#fecaca;}
.group-card.creator{background:linear-gradient(135deg,#fffbeb,#ffedd5);border-color:#fed7aa;}
.group-card.user{background:linear-gradient(135deg,#f0f9ff,#e0e7ff);border-color:#c7d2fe;}
.group-card .group-icon{font-size:28px;margin-bottom:8px;}
.group-card.admin .group-icon{color:#b91c1c;}
.group-card.creator .group-icon{color:#d97706;}
.group-card.user .group-icon{color:#4338ca;}
.group-card .group-name{font-size:15px;font-weight:700;margin-bottom:4px;}
.group-card.admin .group-name{color:#991b1b;}
.group-card.creator .group-name{color:#9241f12;}
.group-card.user .group-name{color:#3730a3;}
.group-card .group-count{font-size:24px;font-weight:800;margin:6px 0;}
.group-card.admin .group-count{color:#dc2626;}
.group-card.creator .group-count{color:#f59e0b;}
.group-card.user .group-count{color:#6366f1;}
.group-card .group-desc{font-size:12px;color:var(--text-muted);line-height:1.5;}
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;}
.modal{background:var(--card-bg);border-radius:12px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.modal-header{padding:20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
.modal-header h3{margin:0;font-size:18px;}
.modal-close{background:none;border:none;font-size:24px;color:var(--text-muted);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;}
.modal-close:hover{background:var(--hover);}
.modal-body{padding:20px;}
.modal-footer{padding:20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:12px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-weight:600;margin-bottom:6px;font-size:13px;}
.form-group .form-control{width:100%;}
.form-group .required{color:#ef4444;}
@media(max-width:600px){.group-cards{grid-template-columns:1fr;}}
</style>

<?php if (isset($success)): ?>
<div class="alert alert-success" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-error" style="margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3><i class="fas fa-users-cog" style="color:#6366f1;"></i> 用户组管理</h3>
        <button class="btn btn-primary btn-sm" onclick="showAddGroupModal()"><i class="fas fa-plus"></i> 添加用户组</button>
    </div>
    <div class="card-body">
        <div class="group-cards">
            <div class="group-card admin">
                <div class="group-icon"><i class="fas fa-crown"></i></div>
                <div class="group-name">管理员</div>
                <div class="group-count"><?php echo $adminCount; ?></div>
                <div class="group-desc">全局唯一，拥有全站最高权限<br>不可被创建或降级</div>
            </div>
            <div class="group-card creator">
                <div class="group-icon"><i class="fas fa-pen-fancy"></i></div>
                <div class="group-name">创作者</div>
                <div class="group-count"><?php echo $creatorCount; ?></div>
                <div class="group-desc">可开发/上传插件、管理API<br>需申请通过或管理员手动授权</div>
            </div>
            <div class="group-card user">
                <div class="group-icon"><i class="fas fa-user"></i></div>
                <div class="group-name">普通用户</div>
                <div class="group-count"><?php echo $normalCount; ?></div>
                <div class="group-desc">注册默认身份<br>不可开发插件，可在插件管理中申请创作者</div>
            </div>
        </div>
    </div>
</div>

<!-- 添加用户组模态框 -->
<div class="modal-overlay" id="addGroupModal" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3>添加用户组</h3>
            <button class="modal-close" onclick="closeAddGroupModal()">&times;</button>
        </div>
        <form method="POST" id="addGroupForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="add_group">
            <div class="modal-body">
                <div class="form-group">
                    <label>用户组名称 <span class="required">*</span></label>
                    <input type="text" name="group_name" class="form-control" required placeholder="例如：高级创作者">
                </div>
                <div class="form-group">
                    <label>角色ID <span class="required">*</span></label>
                    <input type="number" name="role_id" class="form-control" required min="3" placeholder="请输入大于2的整数（3及以上）">
                    <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block;">角色ID需大于2（0=管理员，1=普通用户，2=创作者）</small>
                </div>
                <div class="form-group">
                    <label>显示名称 <span class="required">*</span></label>
                    <input type="text" name="display_name" class="form-control" required placeholder="例如：高级创作者">
                </div>
                <div class="form-group">
                    <label>图标</label>
                    <input type="text" name="icon" class="form-control" placeholder="FontAwesome图标类名，例如：fas fa-star">
                    <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block;">可选，例如：fas fa-star、fas fa-award</small>
                </div>
                <div class="form-group">
                    <label>背景色</label>
                    <input type="text" name="bg_color" class="form-control" placeholder="例如：linear-gradient(135deg,#8b5cf6,#ec4899)" value="linear-gradient(135deg,#8b5cf6,#ec4899)">
                </div>
                <div class="form-group">
                    <label>文字色</label>
                    <input type="text" name="text_color" class="form-control" placeholder="例如：#fff" value="#fff">
                </div>
                <div class="form-group">
                    <label>描述</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="描述该用户组的权限和特点..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAddGroupModal()">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddGroupModal() {
    document.getElementById('addGroupModal').style.display = 'flex';
}

function closeAddGroupModal() {
    document.getElementById('addGroupModal').style.display = 'none';
}

// 点击模态框外部关闭
document.getElementById('addGroupModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddGroupModal();
    }
});
</script>

<?php require '../includes/load_footer.php'; ?>
