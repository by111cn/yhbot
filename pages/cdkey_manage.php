<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '卡密管理';
$activePage = 'cdkey_manage';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'disable') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE cdkeys SET status = 2 WHERE id = ?")->execute([$id]);
        $success = '卡密已禁用';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM cdkeys WHERE id = ?")->execute([$id]);
        $success = '卡密已删除';
    } elseif ($action === 'clean_used') {
        $stmt = db()->prepare("DELETE FROM cdkeys WHERE status = 1");
        $stmt->execute();
        $success = '已清除所有已使用的卡密';
    }
}

// 分页与筛选
$page = max(1, (int)get('page', 1));
$perPage = 20;
$keyword = get('keyword');
$statusFilter = get('status', '');
$typeFilter = get('type', '');

$where = '1=1';
$joinWhere = '1=1';
$params = [];
$joinParams = [];
if ($keyword) {
    $where .= ' AND cdkey LIKE ?';
    $joinWhere .= ' AND c.cdkey LIKE ?';
    $params[] = "%{$keyword}%";
    $joinParams[] = "%{$keyword}%";
}
if ($statusFilter !== '') {
    $where .= ' AND status = ?';
    $joinWhere .= ' AND c.status = ?';
    $params[] = (int)$statusFilter;
    $joinParams[] = (int)$statusFilter;
}
if ($typeFilter) {
    $where .= ' AND type = ?';
    $joinWhere .= ' AND c.type = ?';
    $params[] = $typeFilter;
    $joinParams[] = $typeFilter;
}

$total = countTable('cdkeys', $where, $params);
$pageInfo = pagination($total, $page, $perPage);
$offset = $pageInfo['offset'];

$joinParams[] = $pageInfo['perPage'];
$joinParams[] = $offset;
$stmt = db()->prepare("SELECT c.*, u.username as used_username, a.username as created_username FROM cdkeys c LEFT JOIN users u ON c.used_by = u.id LEFT JOIN users a ON c.created_by = a.id WHERE {$joinWhere} ORDER BY c.id DESC LIMIT ? OFFSET ?");
$params = $joinParams;
$stmt->execute($params);
$cdkeys = $stmt->fetchAll();

require '../includes/load_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>卡密管理 <small style="color:var(--text-muted);font-weight:400;">(共 <?php echo $total; ?> 个)</small></h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- 搜索与筛选 -->
        <div class="flex-between gap-2" style="margin-bottom:20px;flex-wrap:wrap;">
            <form method="GET" class="flex-center gap-2">
                <input type="text" name="keyword" class="form-control" style="max-width:220px;" placeholder="搜索卡密" value="<?php echo h($keyword); ?>">
                <select name="status" class="form-control" style="max-width:120px;">
                    <option value="">全部状态</option>
                    <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>未使用</option>
                    <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>已使用</option>
                    <option value="2" <?php echo $statusFilter === '2' ? 'selected' : ''; ?>>已禁用</option>
                </select>
                <select name="type" class="form-control" style="max-width:120px;">
                    <option value="">全部类型</option>
                    <option value="trial" <?php echo $typeFilter === 'trial' ? 'selected' : ''; ?>>体验卡</option>
                    <option value="week" <?php echo $typeFilter === 'week' ? 'selected' : ''; ?>>周卡</option>
                    <option value="month" <?php echo $typeFilter === 'month' ? 'selected' : ''; ?>>月卡</option>
                    <option value="year" <?php echo $typeFilter === 'year' ? 'selected' : ''; ?>>年卡</option>
                    <option value="permanent" <?php echo $typeFilter === 'permanent' ? 'selected' : ''; ?>>永久卡</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> 搜索</button>
            </form>
            <div class="flex-center gap-2">
                <form method="POST" onsubmit="return confirm('确定清除所有已使用的卡密吗？此操作不可撤销。')">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="clean_used">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-broom"></i> 清除已使用</button>
                </form>
            </div>
        </div>

        <!-- 表格 -->
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>卡密</th>
                        <th>类型</th>
                        <th>单号次数</th>
                        <th>状态</th>
                        <th>使用者</th>
                        <th>使用时间</th>
                        <th>创建者</th>
                        <th>备注</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cdkeys)): ?>
                    <tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">暂无卡密数据</td></tr>
                    <?php else: ?>
                    <?php foreach ($cdkeys as $ck): 
                        $typeLabels = ['trial'=>'体验卡','week'=>'周卡','month'=>'月卡','year'=>'年卡','permanent'=>'永久卡'];
                    ?>
                    <tr>
                        <td><?php echo $ck['id']; ?></td>
                        <td><code style="font-size:12px;background:var(--bg);padding:2px 6px;border-radius:4px;"><?php echo h($ck['cdkey']); ?></code></td>
                        <td><span class="tag tag-blue"><?php echo $typeLabels[$ck['type']] ?? $ck['type']; ?></span></td>
                        <td style="text-align:center;"><?php echo (int)($ck['max_uses_per_account'] ?? 1); ?></td>
                        <td>
                            <?php 
                            $statusConf = [0 => ['green', '未使用'], 1 => ['orange', '已使用'], 2 => ['red', '已禁用']];
                            $s = $statusConf[$ck['status']] ?? ['gray', '未知'];
                            ?>
                            <span class="tag tag-<?php echo $s[0]; ?>"><?php echo $s[1]; ?></span>
                        </td>
                        <td><?php echo h($ck['used_username'] ?? '-'); ?></td>
                        <td><?php echo $ck['used_at'] ? date('Y-m-d H:i', strtotime($ck['used_at'])) : '-'; ?></td>
                        <td><?php echo h($ck['created_username'] ?? '-'); ?></td>
                        <td><?php echo h($ck['note'] ?: '-'); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ck['created_at'])); ?></td>
                        <td>
                            <?php if ($ck['status'] == 0): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('确定禁用此卡密吗？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="disable">
                                <input type="hidden" name="id" value="<?php echo $ck['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i> 禁用</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($ck['status'] != 0): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除此卡密吗？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $ck['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
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
            if ($typeFilter) $query[] = 'type=' . $typeFilter;
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
