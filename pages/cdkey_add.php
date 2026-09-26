<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '添加卡密';
$activePage = 'cdkey_add';
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $type = $_POST['type'] ?? 'trial';
    $count = max(1, min(500, (int)($_POST['count'] ?? 1)));
    $maxUses = max(1, min(9999, (int)($_POST['max_uses_per_account'] ?? 1)));
    $note = trim($_POST['note'] ?? '');

    // 类型对应天数
    $typeMap = ['trial' => 3, 'week' => 7, 'month' => 30, 'year' => 365, 'permanent' => 0];
    if (!isset($typeMap[$type])) $type = 'trial';

    // 卡密前缀
    $prefixMap = ['trial' => 'byty_', 'week' => 'byzk_', 'month' => 'byyk_', 'year' => 'bynk_', 'permanent' => 'byyj_'];

    $durationDays = $typeMap[$type];

    // 批量生成
    $inserted = 0;
    $keys = [];
    for ($i = 0; $i < $count; $i++) {
        $prefix = $prefixMap[$type];
        $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 11);
        $cdkey = $prefix . $random;
        try {
            db()->prepare("INSERT INTO cdkeys (cdkey, type, duration_days, max_uses_per_account, created_by, note) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$cdkey, $type, $durationDays, $maxUses, $userId, $note]);
            $keys[] = $cdkey;
            $inserted++;
        } catch (Exception $e) {
            // 重复则跳过
        }
    }

    $success = "成功生成 {$inserted} 个卡密";
}

require '../includes/load_header.php';
?>

<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h3><span class="icon-dot"></span>添加卡密</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label><i class="fas fa-tag"></i> 卡密类型</label>
                <select name="type" class="form-control">
                    <option value="trial">体验卡 (3天)</option>
                    <option value="week">周卡 (7天)</option>
                    <option value="month">月卡 (30天)</option>
                    <option value="year">年卡 (365天)</option>
                    <option value="permanent">永久卡</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> 生成数量</label>
                <input type="number" name="count" class="form-control" value="1" min="1" max="500" placeholder="1-500">
                <span class="hint">单次最多生成 500 个卡密</span>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user-check"></i> 单个账号使用次数</label>
                <input type="number" name="max_uses_per_account" class="form-control" value="1" min="1" max="9999" placeholder="1-9999">
                <span class="hint">同一账号最多可兑换此卡密的次数，默认 1 次</span>
            </div>
            <div class="form-group">
                <label><i class="fas fa-pen"></i> 备注</label>
                <input type="text" name="note" class="form-control" placeholder="可选，用于标识批次">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('确定生成卡密吗？')">
                <i class="fas fa-magic"></i> 生成卡密
            </button>
        </form>

        <?php if (!empty($keys)): ?>
        <div style="margin-top:24px;padding:16px;background:var(--surface);border-radius:8px;border:1px solid var(--border);">
            <h4 style="margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-list" style="color:#10b981;"></i> 生成的卡密
                <button class="btn btn-sm btn-outline" onclick="copyAll()" style="margin-left:auto;">
                    <i class="fas fa-copy"></i> 一键复制
                </button>
            </h4>
            <textarea id="cdkeyList" readonly style="width:100%;height:200px;font-family:monospace;font-size:13px;padding:10px;border:1px solid var(--border);border-radius:6px;background:var(--bg);resize:vertical;"><?php echo implode("\n", $keys); ?></textarea>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyAll() {
    var ta = document.getElementById('cdkeyList');
    ta.select();
    document.execCommand('copy');
    alert('已复制到剪贴板');
}
</script>

<?php require '../includes/load_footer.php'; ?>
