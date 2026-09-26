<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '用户列表';
$activePage = 'user_list';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $uid = (int)($_POST['id'] ?? 0);
        $newStatus = (int)($_POST['status'] ?? 1);
        if ($uid != $_SESSION['user_id']) {
            db()->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $uid]);
            $success = $newStatus == 1 ? '用户已启用' : '用户已禁用';
        }
    } elseif ($action === 'change_role') {
        $uid = (int)($_POST['id'] ?? 0);
        $newRole = (int)($_POST['role'] ?? 1);
        if ($uid != $_SESSION['user_id'] && in_array($newRole, [1, 2], true)) {
            db()->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $uid]);
            $success = '角色已更新为：' . getRoleLabel($newRole);
        }
    }
}

// 待审核提示
$pendingCount = countTable('creator_applications', 'status = 0', []);

// 分页
$page = max(1, (int)get('page', 1));
$perPage = 20;
$keyword = get('keyword');
$statusFilter = get('status', '');
$roleFilter = get('role', '');

$where = '1=1';
$params = [];
if ($keyword) {
    $where .= ' AND (username LIKE ? OR email LIKE ? OR nickname LIKE ?)';
    $params = array_fill(0, 3, "%{$keyword}%");
}
if ($statusFilter !== '') {
    $where .= ' AND status = ?';
    $params[] = (int)$statusFilter;
}
if ($roleFilter !== '') {
    $where .= ' AND role = ?';
    $params[] = (int)$roleFilter;
}

$total = countTable('users', $where, $params);
$pageInfo = pagination($total, $page, $perPage);
$offset = $pageInfo['offset'];

$params[] = $pageInfo['perPage'];
$params[] = $offset;
$stmt = db()->prepare("SELECT * FROM users WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->execute($params);
$users = $stmt->fetchAll();

$currentUser = getCurrentUser();

require '../includes/load_header.php';
?>

<style>
.role-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:600;}
.role-badge.admin{background:linear-gradient(135deg,#fee2e2,#fecaca);color:#991b1b;}
.role-badge.creator{background:linear-gradient(135deg,#ffedd5,#fed7aa);color:#9a3412;}
.role-badge.user{background:#e2e8f0;color:#475569;}
.filter-bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
</style>

<?php if ($pendingCount > 0): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
    <i class="fas fa-clock" style="color:#f59e0b;"></i>
    当前有 <strong><?php echo $pendingCount; ?></strong> 条创作者申请待审核，
    <a href="<?php echo SITE_URL; ?>/pages/identity_review.php" style="font-weight:600;color:var(--primary);">前往审核 →</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>用户列表 <small style="color:var(--text-muted);font-weight:400;">(共 <?php echo $total; ?> 人)</small></h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- 搜索与筛选 -->
        <div class="flex-between gap-2" style="margin-bottom:20px;">
            <form method="GET" class="filter-bar">
                <input type="text" name="keyword" class="form-control" style="max-width:260px;" placeholder="搜索用户名/邮箱/昵称" value="<?php echo h($keyword); ?>">
                <select name="status" class="form-control" style="max-width:120px;">
                    <option value="">全部状态</option>
                    <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>正常</option>
                    <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>禁用</option>
                </select>
                <select name="role" class="form-control" style="max-width:130px;">
                    <option value="">全部角色</option>
                    <option value="0" <?php echo $roleFilter === '0' ? 'selected' : ''; ?>>管理员</option>
                    <option value="2" <?php echo $roleFilter === '2' ? 'selected' : ''; ?>>创作者</option>
                    <option value="1" <?php echo $roleFilter === '1' ? 'selected' : ''; ?>>普通用户</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> 搜索</button>
            </form>
        </div>

        <!-- 表格 -->
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>昵称</th>
                        <th>邮箱</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">暂无用户数据</td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): $urole = (int)($u['role'] ?? 1); ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><strong><?php echo h($u['username']); ?></strong></td>
                        <td><?php echo h($u['nickname'] ?: '-'); ?></td>
                        <td><?php echo h($u['email'] ?: '-'); ?></td>
                        <td>
                            <?php if ($urole === 0): ?>
                                <span class="role-badge admin"><i class="fas fa-crown"></i> 管理员</span>
                            <?php elseif ($urole === 2): ?>
                                <span class="role-badge creator"><i class="fas fa-pen-fancy"></i> 创作者</span>
                            <?php else: ?>
                                <span class="role-badge user"><i class="fas fa-user"></i> 普通用户</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="tag <?php echo ($u['status'] ?? 1) == 1 ? 'tag-green' : 'tag-red'; ?>">
                                <?php echo ($u['status'] ?? 1) == 1 ? '正常' : '禁用'; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($u['created_at'])); ?></td>
                        <td>
                            <div class="flex-center gap-1" style="flex-wrap:wrap;">
                                <?php if ($u['id'] != $currentUser['id']): ?>
                                    <?php if ($urole !== 0): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <select name="role" onchange="if(confirm('确定修改此用户的用户组？'))this.form.submit();else return false;" class="form-control" style="width:auto;padding:4px 6px;font-size:12px;display:inline-block;">
                                            <option value="1" <?php echo $urole === 1 ? 'selected' : ''; ?>>普通用户</option>
                                            <option value="2" <?php echo $urole === 2 ? 'selected' : ''; ?>>创作者</option>
                                        </select>
                                    </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定<?php echo ($u['status'] ?? 1) == 1 ? '禁用' : '启用'; ?>此用户吗？')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo ($u['status'] ?? 1) == 1 ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo ($u['status'] ?? 1) == 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                            <?php echo ($u['status'] ?? 1) == 1 ? '<i class="fas fa-ban"></i> 禁用' : '<i class="fas fa-check"></i> 启用'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                <span class="tag tag-purple" style="font-size:11px;">当前账号</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($pageInfo['totalPages'] > 1): ?>
        <div class="pagination">
            <?php
            $query = [];
            if ($keyword) $query[] = 'keyword=' . urlencode($keyword);
            if ($statusFilter !== '') $query[] = 'status=' . $statusFilter;
            if ($roleFilter !== '') $query[] = 'role=' . $roleFilter;
            $qs = $query ? '&' . implode('&', $query) : '';
            ?>
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1 . $qs; ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pageInfo['totalPages']; $i++): ?>
            <a href="?page=<?php echo $i . $qs; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $pageInfo['totalPages']): ?>
            <a href="?page=<?php echo $page+1 . $qs; ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
