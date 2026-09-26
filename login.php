<?php
/**
 * 白屿云平台 BotAPI 管理系统 - 登录页面
 */
require_once 'config.php';
require_once 'functions.php';

$error = '';

// 如果已登录则跳转首页
if (isLogin()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

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

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(post('username'));
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = '请输入用户ID/邮箱和密码';
    } else {
        try {
            // 登录方式：用户ID（纯数字）、QQ邮箱、用户名（仅管理员）
            if (ctype_digit($username)) {
                // 纯数字 → 按用户ID查询
                $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$username]);
            } elseif (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                // 邮箱格式 → 按邮箱查询
                $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$username]);
            } else {
                // 非数字非邮箱 → 仅允许管理员用用户名登录
                $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 0 LIMIT 1");
                $stmt->execute([$username]);
            }
            $user = $stmt->fetch();

            if ($user && verifyPassword($password, $user['password'])) {
                try {
                    $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                } catch (Exception $e) {}

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['show_announcement'] = true;

                $redirect = get('redirect', SITE_URL . '/index.php');
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = '用户ID/邮箱或密码错误';
            }
        } catch (Exception $e) {
            $error = '系统错误: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/font-awesome/css/all.min.css?v=6.4.0">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/favicon.ico">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --danger: #ef4444;
            --shadow-xl: 0 24px 80px rgba(0,0,0,0.3);
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
        
        /* ---- 动态粒子背景 ---- */
        #particles {
            position: fixed;
            inset: 0;
            z-index: 0;
        }
        
        /* ---- 彩虹渐变光晕 ---- */
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
            background: radial-gradient(circle, rgba(139,92,246,0.5), transparent);
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

        /* ---- 卡片 ---- */
        .login-container {
            background: rgba(17,25,40,0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            box-shadow: 0 32px 100px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04) inset;
            width: 100%;
            max-width: 440px;
            padding: 48px 44px;
            position: relative;
            z-index: 1;
            animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1);
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .login-container::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 29px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(99,102,241,0.4), rgba(139,92,246,0.2), rgba(236,72,153,0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }
        .login-header .logo-wrap {
            width: 72px; height: 72px;
            margin: 0 auto 18px;
            border-radius: 22px;
            background: linear-gradient(135deg, #6366f1, #818cf8, #a78bfa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #fff;
            box-shadow: 0 8px 40px rgba(99,102,241,0.45), 0 0 0 0 rgba(99,102,241,0.5);
            animation: logoPulse 2.5s ease-in-out infinite;
        }
        @keyframes logoPulse {
            0%,100% { box-shadow: 0 8px 40px rgba(99,102,241,0.45), 0 0 0 0 rgba(99,102,241,0.5); }
            50% { box-shadow: 0 8px 40px rgba(99,102,241,0.6), 0 0 0 12px rgba(99,102,241,0); }
        }
        .login-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .login-header p {
            color: rgba(148,163,184,0.8);
            font-size: 14px;
        }

        /* ---- 错误提示 ---- */
        .error-msg {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-size: 14px;
            display: <?php echo $error ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 10px;
            animation: shake 0.45s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }

        /* ---- 表单 ---- */
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #cbd5e1;
            font-size: 13px;
            letter-spacing: 0.3px;
        }
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
            padding: 14px 16px 14px 46px;
            background: rgba(255,255,255,0.04);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            font-size: 15px;
            color: #f1f5f9;
            transition: all 0.3s;
            outline: none;
        }
        .input-wrapper input.has-toggle {
            padding-right: 46px;
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
        .input-wrapper input:focus + i,
        .input-wrapper input:focus ~ i {
            color: #818cf8;
        }
        .input-wrapper input::placeholder {
            color: rgba(148,163,184,0.35);
        }
        
        /* 密码可见切换 */
        .pw-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(148,163,184,0.55);
            cursor: pointer;
            font-size: 15px;
            width: 32px;
            height: 32px;
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

        /* ---- 登录按钮 ---- */
        .login-btn {
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
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(99,102,241,0.45);
        }
        .login-btn:active {
            transform: scale(0.98);
        }
        .login-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        .login-btn:hover::after {
            transform: translateX(100%);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: rgba(148,163,184,0.6);
            font-size: 14px;
        }
        .login-footer a {
            color: #818cf8;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .login-footer a:hover {
            color: #a5b4fc;
            text-shadow: 0 0 20px rgba(99,102,241,0.5);
        }
        /* ---- 第三方登录 ---- */
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

        /* 响应式 */
        @media (max-width: 480px) {
            .login-container { padding: 36px 24px; border-radius: 22px; margin: 16px; }
            .login-header h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <!-- 光晕背景 -->
    <canvas id="particles"></canvas>
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>
    <div class="bg-glow glow-3"></div>

    <div class="login-container">
        <div class="login-header">
            <div class="logo-wrap"><i class="fas fa-robot"></i></div>
            <h1><?php echo getSiteName(); ?></h1>
            <p>万物皆可 BotAPI</p>
        </div>

        <div class="error-msg">
            <i class="fas fa-exclamation-circle" style="font-size:16px;flex-shrink:0;"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label>用户ID / 邮箱</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="用户ID或邮箱（管理员可用用户名）" required autofocus autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label>密码</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="passwordField" class="has-toggle" placeholder="请输入密码" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" id="pwToggle" title="显示密码" aria-label="显示密码">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i> 登 录
            </button>
        </form>

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

        <div class="login-footer">
            还没有账号？<a href="<?php echo SITE_URL; ?>/register.php">立即注册</a>
        </div>
    </div>

    <script>
    // === 密码可见切换 ===
    (function(){
        var pw = document.getElementById('passwordField');
        var btn = document.getElementById('pwToggle');
        if(pw && btn){
            btn.addEventListener('click', function(){
                var show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
                var title = show ? '隐藏密码' : '显示密码';
                btn.title = title;
                btn.setAttribute('aria-label', title);
            });
        }
    })();

    // === 粒子背景 Canvas ===
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
                x: Math.random()*w,
                y: Math.random()*h,
                r: Math.random()*1.5+0.5,
                vx: (Math.random()-0.5)*0.4,
                vy: (Math.random()-0.5)*0.4,
                o: Math.random()*0.5+0.2
            });
        }

        function draw(){
            ctx.clearRect(0,0,w,h);
            for(var i=0; i<particles.length; i++){
                var p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                if(p.x<0||p.x>w) p.vx*=-1;
                if(p.y<0||p.y>h) p.vy*=-1;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
                ctx.fillStyle = 'rgba(148,163,184,'+p.o+')';
                ctx.fill();
            }
            // 连线
            for(var i=0;i<particles.length;i++){
                for(var j=i+1;j<particles.length;j++){
                    var dx=particles[i].x-particles[j].x;
                    var dy=particles[i].y-particles[j].y;
                    var dist=Math.sqrt(dx*dx+dy*dy);
                    if(dist<100){
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x,particles[i].y);
                        ctx.lineTo(particles[j].x,particles[j].y);
                        ctx.strokeStyle='rgba(99,102,241,'+(0.08*(1-dist/100))+')';
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
