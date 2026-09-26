<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '兑换卡密';
$activePage = 'redeem';
$userId = $_SESSION['user_id'];
$user = getCurrentUser();
$isAdminUser = ($user['role'] ?? 1) == 0;

// 获取当前额度
$currentQuota = $user['quota'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $cdkey = trim($_POST['cdkey'] ?? '');

    if (empty($cdkey)) {
        $error = '请输入卡密';
    } else {
        // 查找卡密
        $stmt = db()->prepare("SELECT * FROM cdkeys WHERE cdkey = ?");
        $stmt->execute([$cdkey]);
        $key = $stmt->fetch();

        if (!$key) {
            $error = '卡密不存在';
        } elseif ($key['status'] == 2) {
            $error = '该卡密已被禁用';
        } else {
            $maxUses = (int)($key['max_uses_per_account'] ?? 1);

            if ($maxUses <= 1) {
                // 单次使用：保持原有逻辑
                if ($key['status'] == 1) {
                    $error = '该卡密已被使用';
                } else {
                    try {
                        db()->beginTransaction();
                        $stmt = db()->prepare("UPDATE cdkeys SET status = 1, used_by = ?, used_at = NOW() WHERE id = ? AND status = 0");
                        $stmt->execute([$userId, $key['id']]);
                        if ($stmt->rowCount() === 0) {
                            throw new Exception('卡密状态异常');
                        }
                        db()->prepare("INSERT INTO cdkey_usages (cdkey_id, user_id, use_count) VALUES (?, ?, 1)")
                            ->execute([$key['id'], $userId]);
                        db()->prepare("UPDATE users SET quota = quota + 1 WHERE id = ?")->execute([$userId]);
                        db()->commit();
                        $currentQuota++;
                        $success = '兑换成功！获得 1 个机器人额度，当前额度: ' . $currentQuota;
                    } catch (Exception $e) {
                        db()->rollBack();
                        $error = '兑换失败: ' . $e->getMessage();
                    }
                }
            } else {
                // 多次使用：检查该账号使用次数
                $usage = db()->prepare("SELECT use_count FROM cdkey_usages WHERE cdkey_id = ? AND user_id = ?");
                $usage->execute([$key['id'], $userId]);
                $usageRow = $usage->fetch();

                $usedCount = $usageRow ? (int)$usageRow['use_count'] : 0;

                if ($usedCount >= $maxUses) {
                    $error = '您已使用此卡密达到上限（' . $maxUses . '次）';
                } else {
                    try {
                        db()->beginTransaction();
                        if ($usageRow) {
                            db()->prepare("UPDATE cdkey_usages SET use_count = use_count + 1, last_used_at = NOW() WHERE cdkey_id = ? AND user_id = ?")
                                ->execute([$key['id'], $userId]);
                        } else {
                            db()->prepare("INSERT INTO cdkey_usages (cdkey_id, user_id, use_count) VALUES (?, ?, 1)")
                                ->execute([$key['id'], $userId]);
                        }
                        // 更新卡密最后使用者信息（不改变状态，其他账号仍可使用）
                        db()->prepare("UPDATE cdkeys SET used_by = ?, used_at = NOW() WHERE id = ?")
                            ->execute([$userId, $key['id']]);
                        db()->prepare("UPDATE users SET quota = quota + 1 WHERE id = ?")->execute([$userId]);
                        db()->commit();
                        $currentQuota++;
                        $remaining = $maxUses - $usedCount - 1;
                        $success = '兑换成功！获得 1 个机器人额度，当前额度: ' . $currentQuota
                                 . '（该卡密还可使用 ' . $remaining . ' 次）';
                    } catch (Exception $e) {
                        db()->rollBack();
                        $error = '兑换失败: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

require '../includes/load_header.php';
?>

<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h3><span class="icon-dot"></span>兑换卡密</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- 当前额度信息 -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-label">当前额度</div>
                <div class="stat-value"><?php echo $isAdminUser ? '无限' : $currentQuota; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">已用额度</div>
                <div class="stat-value"><?php echo countTable('bots', 'user_id = ?', [$userId]); ?></div>
            </div>
        </div>

        <?php if (!$isAdminUser): ?>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label><i class="fas fa-key"></i> 卡密</label>
                <input type="text" name="cdkey" class="form-control" placeholder="请输入卡密，如: byyj_XXXXXXXXXXX" required autocomplete="off">
                <span class="hint">每张卡密可兑换 1 个机器人额度，1 个额度等于 1 个机器人</span>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-gift"></i> 立即兑换
            </button>
        </form>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-crown"></i></div>
            <p>管理员拥有无限额度</p>
            <span class="subtext">无需兑换卡密即可创建机器人</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
