<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '身份审核';
$activePage = 'identity_review';

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'review_creator') {
        $appId = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT * FROM creator_applications WHERE id = ? FOR UPDATE");
            $stmt->execute([$appId]);
            $app = $stmt->fetch();
            if ($app && (int)$app['status'] === 0 && in_array($status, [1, 2], true)) {
                if ($status === 2 && $note === '') {
                    $error = '拒绝申请时请填写备注';
                } else {
                    db()->prepare("UPDATE creator_applications 
                        SET status = ?, reviewer_id = ?, review_note = ?, reviewed_at = NOW() 
                        WHERE id = ?")
                        ->execute([$status, $_SESSION['user_id'], $note, $appId]);
                    if ($status === 1) {
                        db()->prepare("UPDATE users SET role = 2 WHERE id = ?")->execute([$app['user_id']]);
                        $success = '已通过创作者申请，用户已升级为创作者';
                    } else {
                        $success = '已拒绝该创作者申请';
                    }
                }
            }
            db()->commit();
        } catch (Exception $e) {
            try { db()->rollBack(); } catch (Exception $_) {}
            $error = '操作失败：' . $e->getMessage();
        }
    }
}

// 筛选
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// 查询申请列表
$where = '1=1';
$params = [];
if ($statusFilter >= 0) {
    $where .= ' AND a.status = ?';
    $params[] = $statusFilter;
} elseif ($tab === 'pending') {
    $where .= ' AND a.status = 0';
} elseif ($tab === 'approved') {
    $where .= ' AND a.status = 1';
} elseif ($tab === 'rejected') {
    $where .= ' AND a.status = 2';
}

$sql = "SELECT a.*, u.username, u.nickname, u.email, u.role as user_role,
        r.username as reviewer_name
        FROM creator_applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        LEFT JOIN users r ON a.reviewer_id = r.id 
        WHERE {$where} ORDER BY a.id DESC LIMIT 100";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll();

// 统计
$pendingCount = countTable('creator_applications', 'status = 0', []);
$approvedCount = countTable('creator_applications', 'status = 1', []);
$rejectedCount = countTable('creator_applications', 'status = 2', []);

require '../includes/load_header.php';
?>

<style>
.review-tabs{display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border);padding:0;}
.review-tab{padding:10px 20px;font-size:13px;font-weight:600;color:var(--text-muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;background:none;border-top:none;border-left:none;border-right:none;}
.review-tab:hover{color:var(--text);}
.review-tab.active{color:var(--primary);border-bottom-color:var(--primary);}
.review-tab .tab-count{display:inline-block;background:var(--bg-hover);padding:1px 7px;border-radius:10px;font-size:11px;margin-left:6px;font-weight:400;}
.review-tab.active .tab-count{background:rgba(99,102,241,0.12);color:var(--primary);}
.review-tab .tab-count.has-pending{background:#fef3c7;color:#92400e;}

.app-card{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:14px;transition:all 0.2s;}
.app-card:hover{box-shadow:0 2px 12px rgba(0,0,0,0.06);}
.app-card .app-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;}
.app-card .app-user{font-weight:600;color:var(--text);font-size:15px;}
.app-card .app-meta{font-size:12px;color:var(--text-muted);margin-top:4px;}
.app-card .app-body{background:var(--bg-hover);border-radius:8px;padding:12px 14px;margin-bottom:12px;font-size:13px;line-height:1.7;}
.app-card .app-body-label{font-weight:600;color:var(--text);margin-bottom:4px;font-size:12px;}
.app-card .app-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;align-items:flex-start;}
.app-card textarea{width:100%;min-height:56px;border:1px solid var(--border);border-radius:8px;padding:8px 10px;font-size:13px;margin-bottom:8px;resize:vertical;background:var(--bg-card);color:var(--text);}
.app-card .status-tag{padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.status-pending{background:#fef3c7;color:#92400e;}
.status-approved{background:#ecfdf5;color:#065f46;}
.status-rejected{background:#fef2f2;color:#991b1b;}

.empty-state{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:14px;}
</style>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-check" style="color:#6366f1;"></i> 身份审核 
            <?php if ($pendingCount > 0): ?>
            <span class="tag tag-red" style="margin-left:8px;"><?php echo $pendingCount; ?> 条待审</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="card-body">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- Tab 切换 -->
        <div class="review-tabs">
            <button class="review-tab <?php echo $tab === 'pending' ? 'active' : ''; ?>" onclick="location.href='?tab=pending'">
                待审核 <span class="tab-count <?php echo $pendingCount > 0 ? 'has-pending' : ''; ?>"><?php echo $pendingCount; ?></span>
            </button>
            <button class="review-tab <?php echo $tab === 'approved' ? 'active' : ''; ?>" onclick="location.href='?tab=approved'">
                已通过 <span class="tab-count"><?php echo $approvedCount; ?></span>
            </button>
            <button class="review-tab <?php echo $tab === 'rejected' ? 'active' : ''; ?>" onclick="location.href='?tab=rejected'">
                已拒绝 <span class="tab-count"><?php echo $rejectedCount; ?></span>
            </button>
        </div>

        <?php if (empty($apps)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox" style="font-size:32px;margin-bottom:10px;display:block;"></i>
            当前分类下暂无申请记录
        </div>
        <?php else: ?>
        <?php foreach ($apps as $app): $s = (int)$app['status']; ?>
        <div class="app-card">
            <div class="app-head">
                <div>
                    <div class="app-user">
                        <i class="fas fa-user-circle"></i>
                        <?php echo h($app['nickname'] ?: $app['username']); ?>
                        <span style="font-weight:400;font-size:12px;color:var(--text-muted);">(@<?php echo h($app['username']); ?>)</span>
                        <span style="font-size:12px;color:var(--text-muted);margin-left:8px;">UID: <?php echo $app['user_id']; ?></span>
                    </div>
                    <div class="app-meta">
                        申请时间：<?php echo h($app['created_at']); ?>
                        <?php if (!empty($app['email'])): ?> · 邮箱：<?php echo h($app['email']); ?><?php endif; ?>
                    </div>
                </div>
                <div>
                    <?php if ($s === 0): ?>
                    <span class="status-tag status-pending"><i class="fas fa-clock"></i> 待审核</span>
                    <?php elseif ($s === 1): ?>
                    <span class="status-tag status-approved"><i class="fas fa-check"></i> 已通过</span>
                    <?php else: ?>
                    <span class="status-tag status-rejected"><i class="fas fa-times"></i> 已拒绝</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-body">
                <div class="app-body-label"><i class="fas fa-quote-left"></i> 申请理由</div>
                <div style="white-space:pre-wrap;"><?php echo h($app['reason']); ?></div>
            </div>
            <?php if (!empty($app['portfolio'])): ?>
            <div class="app-body">
                <div class="app-body-label"><i class="fas fa-briefcase"></i> 作品/经验/联系方式</div>
                <div style="white-space:pre-wrap;"><?php echo h($app['portfolio']); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($s !== 0): ?>
            <div style="font-size:12px;color:var(--text-muted);padding:8px 0;border-top:1px solid var(--border);">
                <?php if (!empty($app['reviewer_name'])): ?>审核人：<?php echo h($app['reviewer_name']); ?> · <?php endif; ?>
                审核时间：<?php echo h($app['reviewed_at'] ?? '-'); ?>
                <?php if (!empty($app['review_note'])): ?> · 备注：<?php echo h($app['review_note']); ?><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($s === 0): ?>
            <form method="POST" class="review-form" data-id="<?php echo $app['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="review_creator">
                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                <textarea name="note" placeholder="拒绝时必填备注原因，通过可不填..."></textarea>
                <div class="app-actions">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="submitReview(this, 2)">
                        <i class="fas fa-times"></i> 拒绝
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="submitReview(this, 1)">
                        <i class="fas fa-check"></i> 通过
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function submitReview(btn, status) {
    var form = btn.closest('form');
    var noteInput = form.querySelector('textarea[name="note"]');
    var note = noteInput.value.trim();
    if (status === 2 && note === '') {
        alert('请填写拒绝备注原因');
        noteInput.focus();
        return;
    }
    var statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'status';
    statusInput.value = status;
    form.appendChild(statusInput);

    var confirmText = status === 1
        ? '确定通过该创作者申请？用户将立即获得插件开发权限。'
        : '确定拒绝该申请？';
    if (confirm(confirmText)) {
        form.submit();
    } else {
        form.removeChild(statusInput);
    }
}
</script>

<?php require '../includes/load_footer.php'; ?>
