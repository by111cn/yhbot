<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '个人资料';
$activePage = 'profile';
$user = getCurrentUser();

// 读取聚合登录配置（用于判断是否显示绑定入口）
$oauthConfig = [];
try {
    $stmt = db()->prepare("SELECT `value` FROM settings WHERE `key` = 'oauth_config' AND user_id = 0");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) {
        $cfg = json_decode($row['value'], true) ?: [];
        if (!empty($cfg['enabled'])) {
            $oauthConfig = array_filter(array_map('trim', explode(',', $cfg['types'] ?? '')));
        }
    }
} catch (Exception $e) {
    $oauthConfig = [];
}

// 查询当前用户的 QQ / 微信绑定状态
$oauthBindings = [];
if ($oauthConfig) {
    $stmt = db()->prepare("SELECT provider, openid, nickname, avatar FROM oauth_accounts WHERE user_id = ? AND provider IN ('qq', 'wx')");
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll() as $item) {
        $oauthBindings[$item['provider']] = $item;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // 头像上传处理（AJAX 独立处理，返回 JSON 后退出）
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        header('Content-Type: application/json; charset=utf-8');
        $file = $_FILES['avatar_file'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes, true)) {
            echo json_encode(['code' => 1, 'msg' => '头像仅支持 JPG/PNG/GIF/WebP 格式']);
            exit;
        } elseif ($file['size'] > $maxSize) {
            echo json_encode(['code' => 1, 'msg' => '头像文件不能超过 2MB']);
            exit;
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeExts = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'];
            $ext = strtolower($ext);
            if (!isset($safeExts[$ext])) {
                $ext = 'jpg';
            }
            $ext = $safeExts[$ext];

            $uploadDir = dirname(__DIR__) . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            // 目录不存在或不可写，给出明确错误
            if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                echo json_encode(['code' => 1, 'msg' => '服务器头像目录不可写，请联系管理员创建 uploads/avatars/ 目录']);
                exit;
            }

            $fileName = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $avatarUrl = SITE_URL . '/uploads/avatars/' . $fileName;
                db()->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatarUrl, $user['id']]);
                echo json_encode(['code' => 0, 'msg' => '头像上传成功', 'avatar' => $avatarUrl]);
                exit;
            } else {
                echo json_encode(['code' => 1, 'msg' => '头像保存失败，请检查目录权限']);
                exit;
            }
        }
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
        $error = '用户名不能为空';
    } elseif (!isset($error) || strpos($error, '用户名') === false) {
        // 空邮箱存为 NULL，避免触发 email 唯一约束冲突
        $emailValue = $email !== '' ? $email : null;
        $stmt = db()->prepare("UPDATE users SET username=?, nickname=?, email=? WHERE id=?");
        $stmt->execute([$username, $username, $emailValue, $user['id']]);
        if (!isset($success)) $success = '资料更新成功';
    }

    if (!empty($newPass)) {
        if (!password_verify($oldPass, $user['password'])) {
            $error = '原密码错误';
        } elseif ($newPass !== $confirmPass) {
            $error = '两次输入的密码不一致';
        } elseif (strlen($newPass) < 6) {
            $error = '新密码长度至少6位';
        } else {
            $hash = hashPassword($newPass);
            $stmt = db()->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$hash, $user['id']]);
            $success = '密码修改成功';
        }
    }

    $user = getCurrentUser();
}

require '../includes/load_header.php';

// 获取用户头像
$userAvatar = !empty($user['avatar']) ? $user['avatar'] : '';
?>

<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h3><span class="icon-dot"></span>个人资料</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <div style="text-align:center;margin-bottom:28px;">
            <div id="avatarContainer" style="position:relative;display:inline-block;cursor:pointer;width:88px;height:88px;overflow:hidden;border-radius:50%;" onclick="document.getElementById('avatarInput').click();">
                <?php if (!empty($userAvatar)): ?>
                    <img id="avatarImg" src="<?php echo h($userAvatar); ?>" style="width:88px !important;height:88px !important;max-width:88px !important;max-height:88px !important;border-radius:50%;object-fit:cover;box-shadow:0 8px 24px rgba(99,102,241,.3);display:block;" alt="头像" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#818cf8);display:none;align-items:center;justify-content:center;color:#fff;font-size:36px;font-weight:700;box-shadow:0 8px 24px rgba(99,102,241,.3);position:absolute;top:0;left:0;">
                        <?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?>
                    </div>
                <?php else: ?>
                    <div style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#818cf8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:36px;font-weight:700;box-shadow:0 8px 24px rgba(99,102,241,.3);">
                        <?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?>
                    </div>
                <?php endif; ?>
                <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid var(--bg-card);">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <input type="file" id="avatarInput" name="avatar_file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="previewAndUploadAvatar(this)">
            <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">点击头像更换</div>
            <div style="font-size:18px;font-weight:600;"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
            <div style="color:var(--text-muted);font-size:13px;margin-top:4px;">
                <?php
                $urole = (int)($user['role'] ?? 1);
                $roleMap = [
                    0 => ['管理员', 'linear-gradient(135deg,#ef4444,#b91c1c)', '#fff', 'fas fa-crown'],
                    2 => ['创作者', 'linear-gradient(135deg,#f59e0b,#d97706)', '#fff', 'fas fa-pen-fancy'],
                    1 => ['普通用户', '#e2e8f0', '#475569', 'fas fa-user'],
                ];
                $r = $roleMap[$urole] ?? $roleMap[1];
                ?>
                <span class="tag" style="background:<?php echo $r[1]; ?>;color:<?php echo $r[2]; ?>;font-size:12px;border:0;padding:3px 10px;border-radius:20px;">
                    <i class="<?php echo $r[3]; ?>" style="margin-right:3px;"></i><?php echo $r[0]; ?>
                </span>
                <span style="margin:0 8px;">·</span>
                注册于 <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
            </div>
        </div>

        <div class="tabs">
            <div class="tab-item active" data-target="tab-info"><i class="fas fa-user"></i> 基本信息</div>
            <div class="tab-item" data-target="tab-security"><i class="fas fa-shield-alt"></i> 安全设置</div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="tab-pane active" id="tab-info">
                <div class="form-group">
                    <label>用户ID</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['id']); ?>" disabled>
                    <span class="hint">用户ID不可修改</span>
                </div>
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" placeholder="设置用户名">
                </div>
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="绑定邮箱地址">
                </div>
            </div>

            <div class="tab-pane" id="tab-security">
                <div class="alert alert-info"><i class="fas fa-info-circle"></i> 如需修改密码，请填写以下所有字段</div>
                <div class="form-group">
                    <label>原密码</label>
                    <input type="password" name="old_password" class="form-control" placeholder="输入原密码">
                </div>
                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" name="new_password" class="form-control" placeholder="输入新密码（至少6位）">
                </div>
                <div class="form-group">
                    <label>确认新密码</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="再次输入新密码">
                </div>

                <?php if (!empty($oauthConfig)): ?>
                <div class="bind-section">
                    <div class="bind-title"><i class="fas fa-link"></i> 账号绑定</div>
                    <?php
                    $bindProviders = [
                        'qq' => ['name' => 'QQ', 'icon' => 'fab fa-qq', 'color' => '#12b7f5'],
                        'wx' => ['name' => '微信', 'icon' => 'fab fa-weixin', 'color' => '#07c160'],
                    ];
                    foreach ($bindProviders as $key => $info):
                        $isBound = isset($oauthBindings[$key]);
                        $bindInfo = $oauthBindings[$key] ?? null;
                    ?>
                    <div class="bind-item <?php echo $isBound ? 'bound' : ''; ?>">
                        <div class="bind-icon" style="background:<?php echo $info['color']; ?>;">
                            <i class="<?php echo $info['icon']; ?>"></i>
                        </div>
                        <div class="bind-info">
                            <div class="bind-name"><?php echo $info['name']; ?></div>
                            <div class="bind-status">
                                <?php if ($isBound): ?>
                                    <span class="tag tag-success">已绑定</span>
                                    <?php if (!empty($bindInfo['nickname'])): ?>
                                        <span class="bind-nickname"><?php echo h($bindInfo['nickname']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="tag">未绑定</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bind-action">
                            <?php if ($isBound): ?>
                                <button type="button" class="btn btn-sm btn-danger" onclick="unbindOAuth('<?php echo $key; ?>', '<?php echo $info['name']; ?>')">
                                    <i class="fas fa-unlink"></i> 解绑
                                </button>
                            <?php elseif (in_array($key, $oauthConfig, true)): ?>
                                <a href="<?php echo SITE_URL; ?>/api/oauth.php?action=bind&type=<?php echo $key; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-link"></i> 绑定
                                </a>
                            <?php else: ?>
                                <span class="hint">未在聚合登录中启用</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> 保存修改
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.bind-section {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}
.bind-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.bind-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-card);
    margin-bottom: 12px;
    transition: all 0.2s;
}
.bind-item.bound {
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(16, 185, 129, 0.04);
}
.bind-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.bind-info {
    flex: 1;
    min-width: 0;
}
.bind-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}
.bind-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.bind-status .tag {
    background: var(--bg-hover);
    color: var(--text-muted);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
}
.bind-status .tag-success {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}
.bind-nickname {
    font-size: 12px;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 160px;
}
.bind-action {
    flex-shrink: 0;
}
.bind-action .hint {
    font-size: 12px;
    color: var(--text-muted);
}
</style>

<script>
function unbindOAuth(type, name) {
    if (!confirm('确定要解除绑定' + name + '账号吗？')) return;
    var formData = new FormData();
    formData.append('type', type);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');
    fetch('<?php echo SITE_URL; ?>/api/oauth.php?action=unbind', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.code === 0) {
            alert(res.msg || '解绑成功');
            location.reload();
        } else {
            alert(res.msg || '解绑失败');
        }
    })
    .catch(function() {
        alert('网络错误，请稍后重试');
    });
}

function previewAndUploadAvatar(input) {
    if (!input.files || !input.files[0]) return;

    var file = input.files[0];
    var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (allowedTypes.indexOf(file.type) === -1) {
        alert('仅支持 JPG/PNG/GIF/WebP 格式');
        input.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('头像文件不能超过 2MB');
        input.value = '';
        return;
    }

    // 预览：input 的上一个兄弟元素就是头像容器
    var container = input.previousElementSibling;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = container.querySelector('img');
        var fallback = container.querySelector('div[style*="border-radius:50%"]');

        if (img) {
            img.src = e.target.result;
            img.style.display = '';
            if (fallback) fallback.style.display = 'none';
        } else if (fallback) {
            var newImg = document.createElement('img');
            newImg.src = e.target.result;
            newImg.style.cssText = 'width:88px !important;height:88px !important;max-width:88px !important;max-height:88px !important;border-radius:50%;object-fit:cover;box-shadow:0 8px 24px rgba(99,102,241,.3);display:block;';
            newImg.alt = '头像';
            container.insertBefore(newImg, fallback);
            fallback.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);

    // 上传：只发送头像文件和 CSRF，不发送其他表单字段
    var formData = new FormData();
    formData.append('avatar_file', file);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    fetch(location.href, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.code === 0) {
            location.reload();
        } else {
            alert(res.msg || '头像上传失败');
            input.value = '';
        }
    })
    .catch(function() {
        alert('网络错误，头像上传失败');
        input.value = '';
    });
}
</script>

<?php require '../includes/load_footer.php'; ?>
