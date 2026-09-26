<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '申请创作者';
$activePage = 'creator_apply';
$userId = (int)$_SESSION['user_id'];
$user = getCurrentUser();
$role = (int)($user['role'] ?? 1);

// 查询是否已有申请
$stmt = db()->prepare("SELECT * FROM creator_applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId]);
$lastApp = $stmt->fetch();

require '../includes/load_header.php';
?>

<style>
.apply-card{max-width:680px;margin:0 auto}
.role-banner{padding:18px 22px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:14px}
.role-banner.admin{background:linear-gradient(135deg,#fee2e2,#fecaca);color:#991b1b}
.role-banner.creator{background:linear-gradient(135deg,#ffedd5,#fed7aa);color:#9a3412}
.role-banner.user{background:linear-gradient(135deg,#e0e7ff,#c7d2fe);color:#3730a3}
.role-banner i{font-size:28px}
.role-banner h4{margin:0 0 2px;font-size:16px}
.role-banner p{margin:0;font-size:12px;opacity:0.85}
.status-banner{padding:14px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px}
.status-banner.pending{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
.status-banner.approved{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
.status-banner.rejected{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.form-row{display:grid;grid-template-columns:1fr;gap:16px}
.form-group label{font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.form-group label .required{color:#ef4444}
textarea{min-height:120px;resize:vertical}
.benefit-list{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
.benefit-item{padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:13px}
.benefit-item i{color:#6366f1;margin-right:6px}
@media(max-width:600px){.benefit-list{grid-template-columns:1fr}}
</style>

<div class="apply-card">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-pen"></i> 创作者申请</h3>
        </div>
        <div class="card-body">

            <!-- 当前身份横幅 -->
            <?php if ($role === 0): ?>
            <div class="role-banner admin">
                <i class="fas fa-crown"></i>
                <div>
                    <h4>当前身份：超级管理员</h4>
                    <p>您已拥有全站最高权限，无需申请创作者</p>
                </div>
            </div>
            <?php elseif ($role === 2): ?>
            <div class="role-banner creator">
                <i class="fas fa-pen-fancy"></i>
                <div>
                    <h4>当前身份：创作者</h4>
                    <p>您已获得插件/API 开发权限，尽情施展才华吧！</p>
                </div>
            </div>
            <?php else: ?>
            <div class="role-banner user">
                <i class="fas fa-user"></i>
                <div>
                    <h4>当前身份：普通用户</h4>
                    <p>普通用户无法开发插件，需提交申请成为创作者</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- 申请状态（已有记录） -->
            <?php if ($lastApp): $s = (int)$lastApp['status']; ?>
                <?php if ($s === 0): ?>
                <div class="status-banner pending">
                    <i class="fas fa-clock"></i>
                    <span>您的创作者申请正在审核中，请耐心等待管理员处理…提交时间：<?php echo h($lastApp['created_at']); ?></span>
                </div>
                <?php elseif ($s === 1): ?>
                <div class="status-banner approved">
                    <i class="fas fa-check-circle"></i>
                    <span>🎉 您的申请已通过！现在您可以在 功能设置 → 插件管理 / 添加API 中开发自己的功能了。</span>
                </div>
                <?php elseif ($s === 2): ?>
                <div class="status-banner rejected">
                    <i class="fas fa-times-circle"></i>
                    <span>申请被拒绝：<?php echo h($lastApp['review_note'] ?: '未填写备注'); ?>（可重新提交）</span>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($role === 1): ?>
            <!-- 权益说明 -->
            <h5 style="font-size:14px;margin:0 0 12px;color:#0f172a;">📌 成为创作者您将获得</h5>
            <div class="benefit-list">
                <div class="benefit-item"><i class="fas fa-puzzle-piece"></i> 上传并管理 PHP 插件</div>
                <div class="benefit-item"><i class="fas fa-plus-circle"></i> 自定义 API 接口对接</div>
                <div class="benefit-item"><i class="fas fa-store"></i> 发布作品到插件市场</div>
                <div class="benefit-item"><i class="fas fa-list-check"></i> 自定义机器人菜单</div>
            </div>

            <form method="POST" action="<?php echo SITE_URL; ?>/api/creator.php?action=apply" id="applyForm">
                <?php echo csrfField(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>邮箱 <span class="required">*</span></label>
                        <input type="email" class="form-control" name="email" required maxlength="100" placeholder="请输入您的邮箱地址，方便管理员联系您" value="<?php echo h($user['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>申请理由 <span class="required">*</span></label>
                        <textarea class="form-control" name="reason" required maxlength="1000" placeholder="请简单说明您申请创作者的目的，例如：希望开发自己的云湖机器人插件、对接个人API等..."><?php echo h($lastApp['reason'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>作品</label>
                        <textarea class="form-control" name="portfolio" maxlength="2000" placeholder="选填，提供您的作品链接，有助于更快通过审核..."><?php echo h($lastApp['portfolio'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> 提交申请
                    </button>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
document.getElementById('applyForm').addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    fetch(this.action, {method:'POST', body: fd, credentials:'same-origin'})
    .then(r => r.json()).then(d => {
        if (d.success) { alert('✅ ' + (d.message || '提交成功')); location.reload(); }
        else { alert('❌ ' + (d.message || '提交失败')); }
    }).catch(err => alert('网络错误：' + err));
});
</script>

<?php require '../includes/load_footer.php'; ?>
