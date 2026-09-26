<?php
/**
 * 白屿云平台 BotAPI 管理系统 - 注册页面
 */
require_once 'config.php';
require_once 'functions.php';

if (isLogin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// 获取启用的聚合登录配置（兼容新旧两种格式）
$oauthEnabled = [];
try {
    $stmt = db()->prepare("SELECT `value` FROM settings WHERE `key` = 'oauth_config' AND user_id = 0");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) {
        $cfg = json_decode($row['value'], true) ?: [];
        if (is_array($cfg['enabled'] ?? null)) {
            // 旧格式：enabled 是数组 ['github','qq','wechat',...]，映射到新键名
            $keyMap = ['wechat' => 'wx'];
            foreach ($cfg['enabled'] as $k) {
                $oauthEnabled[] = $keyMap[$k] ?? $k;
            }
        } elseif (!empty($cfg['enabled'])) {
            // 新格式：enabled=1，types="qq,wx"
            $oauthEnabled = array_filter(array_map('trim', explode(',', $cfg['types'] ?? '')));
        }
    }
} catch (Exception $e) {
    // 读取配置失败，忽略（不显示第三方登录）
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度应为3-20个字符';
    } elseif (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        $error = '用户名只能包含字母、数字、下划线和中文';
    } elseif ($password !== $confirm) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } else {
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = '用户名已存在';
        } else {
            $hash = hashPassword($password);
            // 注册默认角色为 1（普通用户），不允许直接注册管理员
            $stmt = db()->prepare("INSERT INTO users (username, password, email, nickname, role) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$username, $hash, $email, $nickname ?: $username]);
            $success = '注册成功！即将跳转到登录页...';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/font-awesome/css/all.min.css?v=6.4.0">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/favicon.ico">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --danger: #ef4444;
            --success: #10b981;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background: #0f0f23;
        }

        #particles { position: fixed; inset: 0; z-index: 0; }

        .bg-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: 0;
            pointer-events: none;
        }
        .glow-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(99,102,241,0.5), transparent);
            top: -150px; left: -100px;
            animation: glowFloat 8s ease-in-out infinite;
        }
        .glow-2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(16,185,129,0.4), transparent);
            bottom: -120px; right: -80px;
            animation: glowFloat 10s ease-in-out infinite reverse;
        }
        .glow-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(236,72,153,0.35), transparent);
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            animation: glowPulse 4s ease-in-out infinite;
        }
        @keyframes glowFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(40px,-30px) scale(1.08); }
            66% { transform: translate(-30px,20px) scale(0.95); }
        }
        @keyframes glowPulse {
            0%,100% { opacity: 0.25; transform: translate(-50%,-50%) scale(0.9); }
            50% { opacity: 0.5; transform: translate(-50%,-50%) scale(1.15); }
        }

        .auth-box {
            background: rgba(17,25,40,0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            box-shadow: 0 32px 100px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04) inset;
            width: 100%;
            max-width: 480px;
            padding: 44px 44px;
            position: relative;
            z-index: 1;
            animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1);
            /* 高度自适应，不限制 90vh，避免内部出现滚动条 */
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .auth-box::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 29px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(99,102,241,0.4), rgba(16,185,129,0.2), rgba(236,72,153,0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .auth-box h1 {
            text-align: center;
            margin-bottom: 6px;
            font-size: 26px;
            font-weight: 700;
            color: #f1f5f9;
            letter-spacing: -0.5px;
        }
        .auth-box .subtitle {
            text-align: center;
            color: rgba(148,163,184,0.8);
            margin-bottom: 32px;
            font-size: 14px;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.25);
            color: #6ee7b7;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #cbd5e1;
            font-size: 13px;
            letter-spacing: 0.3px;
        }
        .form-group label .required { color: #f87171; margin-left: 2px; }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(148,163,184,0.5);
            font-size: 15px;
            transition: all 0.3s;
            pointer-events: none;
            z-index: 1;
        }
        .input-wrapper input {
            width: 100%;
            padding: 13px 16px 13px 44px;
            background: rgba(255,255,255,0.04);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            font-size: 14px;
            color: #f1f5f9;
            transition: all 0.3s;
            outline: none;
        }
        .input-wrapper input.has-toggle {
            padding-right: 44px;
        }
        .pw-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(148,163,184,0.55);
            cursor: pointer;
            font-size: 14px;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 1;
        }
        .pw-toggle:hover {
            color: #818cf8;
            background: rgba(255,255,255,0.06);
        }
        .pw-toggle:active {
            background: rgba(255,255,255,0.1);
        }
        .input-wrapper input:hover {
            border-color: rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
        }
        .input-wrapper input:focus {
            border-color: rgba(99,102,241,0.6);
            background: rgba(255,255,255,0.05);
            box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }
        .input-wrapper input::placeholder { color: rgba(148,163,184,0.35); }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
            margin-top: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(99,102,241,0.45);
        }
        .btn-submit:active { transform: scale(0.98); }
        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        .btn-submit:hover::after { transform: translateX(100%); }

        .auth-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: rgba(148,163,184,0.6);
        }
        .auth-link a {
            color: #818cf8;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .auth-link a:hover {
            color: #a5b4fc;
            text-shadow: 0 0 20px rgba(99,102,241,0.5);
        }

        .oauth-divider {
            display: flex; align-items: center; gap: 14px;
            margin: 20px 0 16px; color: rgba(148,163,184,0.5);
            font-size: 12px; letter-spacing: 1px;
        }
        .oauth-divider::before, .oauth-divider::after {
            content: ''; flex: 1; height: 1px;
            background: rgba(255,255,255,0.06);
        }
        .oauth-btns {
            display: flex; gap: 10px; flex-wrap: wrap;
            justify-content: center;
        }
        .oauth-btn {
            flex: 1; min-width: 70px; padding: 10px 6px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; background: rgba(255,255,255,0.03);
            color: #cbd5e1; font-size: 13px; cursor: pointer;
            text-decoration: none; display: flex; align-items: center;
            justify-content: center; gap: 6px;
            transition: all 0.25s; text-align: center;
        }
        .oauth-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        .oauth-btn i { font-size: 18px; }

        @media (max-width: 540px) {
            .auth-box { padding: 36px 24px; border-radius: 22px; margin: 16px; }
            .form-row { grid-template-columns: 1fr; }
            .auth-box h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <canvas id="particles"></canvas>
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>
    <div class="bg-glow glow-3"></div>

    <div class="auth-box">
        <h1><i class="fas fa-user-plus" style="margin-right:8px;background:linear-gradient(135deg,#6366f1,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;"></i>注册账号</h1>
        <p class="subtitle"><?php echo getSiteName(); ?></p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle" style="font-size:16px;flex-shrink:0;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="font-size:16px;flex-shrink:0;"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <script>setTimeout(function(){ location.href='login.php'; }, 2000);</script>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>用户名 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="3-20个字符" required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <div class="input-wrapper">
                        <i class="fas fa-tag"></i>
                        <input type="text" name="nickname" placeholder="显示名称" autocomplete="nickname">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="可选" autocomplete="email">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>密码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="regPassword" class="has-toggle" placeholder="至少6位" required autocomplete="new-password">
                        <button type="button" class="pw-toggle" data-target="regPassword" title="显示密码" aria-label="显示密码">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>确认密码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" id="regConfirmPassword" class="has-toggle" placeholder="再次输入密码" required autocomplete="new-password">
                        <button type="button" class="pw-toggle" data-target="regConfirmPassword" title="显示密码" aria-label="显示密码">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-user-plus" style="margin-right:6px;"></i> 立即注册
            </button>
        </form>
        <?php endif; ?>

        <?php if (!empty($oauthEnabled)): ?>
        <div class="oauth-divider">第三方登录</div>
        <div class="oauth-btns">
            <?php
            $oaMap = [
                'qq'        => ['name' => 'QQ',     'icon' => 'fab fa-qq'],
                'wx'        => ['name' => '微信',   'icon' => 'fab fa-weixin'],
                'alipay'    => ['name' => '支付宝', 'icon' => 'fab fa-alipay'],
                'sina'      => ['name' => '微博',   'icon' => 'fab fa-weibo'],
                'baidu'     => ['name' => '百度',   'icon' => 'fas fa-paw'],
                'douyin'    => ['name' => '抖音',   'icon' => 'fab fa-tiktok'],
                'huawei'    => ['name' => '华为',   'icon' => 'fas fa-mobile-alt'],
                'xiaomi'    => ['name' => '小米',   'icon' => 'fas fa-mobile'],
                'google'    => ['name' => '谷歌',   'icon' => 'fab fa-google'],
                'microsoft' => ['name' => '微软',   'icon' => 'fab fa-microsoft'],
                'dingtalk'  => ['name' => '钉钉',   'icon' => 'fas fa-bell'],
                'feishu'    => ['name' => '飞书',   'icon' => 'fas fa-paper-plane'],
                'gitee'     => ['name' => 'Gitee',  'icon' => 'fab fa-git-alt'],
                'github'    => ['name' => 'GitHub', 'icon' => 'fab fa-github'],
            ];
            foreach ($oauthEnabled as $p):
                $info = $oaMap[$p] ?? ['name' => $p, 'icon' => 'fas fa-sign-in-alt'];
            ?>
            <a href="<?= SITE_URL ?>/api/oauth.php?action=login&type=<?= urlencode($p) ?>" class="oauth-btn" title="<?= $info['name'] ?>登录">
                <i class="<?= $info['icon'] ?>"></i> <?= $info['name'] ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="auth-link">
            已有账号？<a href="<?php echo SITE_URL; ?>/login.php">立即登录</a>
        </div>
    </div>

    <script>
    // === 密码可见切换 ===
    (function(){
        var toggles = document.querySelectorAll('.pw-toggle');
        toggles.forEach(function(btn){
            btn.addEventListener('click', function(){
                var targetId = btn.getAttribute('data-target');
                var pw = document.getElementById(targetId);
                if(!pw) return;
                var show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
                var title = show ? '隐藏密码' : '显示密码';
                btn.title = title;
                btn.setAttribute('aria-label', title);
            });
        });
    })();

    // === 粒子背景 ===
    (function(){
        var canvas = document.getElementById('particles');
        var ctx = canvas.getContext('2d');
        var w, h, particles = [];
        var maxP = 80;

        function resize(){
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        for(var i=0; i<maxP; i++){
            particles.push({
                x: Math.random()*w, y: Math.random()*h,
                r: Math.random()*1.5+0.5,
                vx: (Math.random()-0.5)*0.4, vy: (Math.random()-0.5)*0.4,
                o: Math.random()*0.5+0.2
            });
        }
        function draw(){
            ctx.clearRect(0,0,w,h);
            for(var i=0;i<particles.length;i++){
                var p=particles[i];
                p.x+=p.vx; p.y+=p.vy;
                if(p.x<0||p.x>w) p.vx*=-1;
                if(p.y<0||p.y>h) p.vy*=-1;
                ctx.beginPath();
                ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
                ctx.fillStyle='rgba(148,163,184,'+p.o+')';
                ctx.fill();
            }
            for(var i=0;i<particles.length;i++){
                for(var j=i+1;j<particles.length;j++){
                    var dx=particles[i].x-particles[j].x;
                    var dy=particles[i].y-particles[j].y;
                    var dist=Math.sqrt(dx*dx+dy*dy);
                    if(dist<100){
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x,particles[i].y);
                        ctx.lineTo(particles[j].x,particles[j].y);
                        ctx.strokeStyle='rgba(16,185,129,'+(0.08*(1-dist/100))+')';
                        ctx.lineWidth=0.5;
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(draw);
        }
        draw();
    })();
    </script>
</body>
</html>
