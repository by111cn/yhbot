<?php
/**
 * 白屿云平台 BotAPI 管理系统 - 安装脚本
 */
$step = 1;
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = 2;
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = $_POST['admin_user'] ?? 'admin';
    $adminPass = $_POST['admin_pass'] ?? '';

    try {
        $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        $sql = file_get_contents(__DIR__ . '/sql/database.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement . ';');
            }
        }

        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, role) VALUES (?, ?, ?, 0)");
        $stmt->execute([$adminUser, $hash, '管理员']);

        $config = "<?php\n";
        $config .= "/**\n * 白屿云平台 BotAPI 管理系统 - 配置文件\n */\n\n";
        $config .= "define('DEBUG_MODE', true);\n";
        $config .= "if (DEBUG_MODE) {\n    error_reporting(E_ALL);\n    ini_set('display_errors', 1);\n} else {\n    error_reporting(0);\n    ini_set('display_errors', 0);\n    ini_set('log_errors', 1);\n}\n\n";
        $config .= "define('SITE_NAME', '白屿云平台 BotAPI 管理系统');\n";
        $config .= "define('SITE_VERSION', '1.0.0');\n\n";
        $config .= "\$__scheme = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS']!=='off') || (!empty(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO']==='https') ? 'https' : 'http';\n";
        $config .= "\$__host = \$_SERVER['HTTP_HOST'] ?? 'localhost';\n";
        $config .= "define('SITE_URL', \$__scheme.'://'.\$__host);\n";
        $config .= "\$__isHttps = (\$__scheme==='https');\nunset(\$__scheme, \$__host);\n\n";
        $config .= "date_default_timezone_set('Asia/Shanghai');\n\n";
        $config .= "if (session_status() === PHP_SESSION_NONE) {\n";
        $config .= "    if (PHP_VERSION_ID>=70300) {\n";
        $config .= "        session_set_cookie_params(['lifetime'=>86400*7,'path'=>'/','domain'=>'','secure'=>\$__isHttps,'httponly'=>true,'samesite'=>'Lax']);\n";
        $config .= "    }\n    session_name('YHSESSION');\n    session_start();\n    unset(\$__isHttps);\n}\n\n";
        $config .= "class DB {\n";
        $config .= "    private static \$instance = null;\n    private \$pdo;\n";
        $config .= "    private function __construct() {\n";
        $config .= "        \$dsn = \"mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4\";\n";
        $config .= "        \$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false];\n";
        $config .= "        try {\n            \$this->pdo = new PDO(\$dsn, '{$dbUser}', '{$dbPass}', \$options);\n            \$this->pdo->exec(\"SET time_zone = '+08:00'\");\n";
        $config .= "        } catch (PDOException \$e) {\n            if (ini_get('display_errors')) die('数据库连接失败: '.\$e->getMessage());\n";
        $config .= "            die('数据库连接失败，请联系管理员。');\n        }\n    }\n";
        $config .= "    public static function getInstance() {\n        if (self::\$instance===null) self::\$instance=new self();\n        return self::\$instance;\n    }\n";
        $config .= "    public function getPDO() { return \$this->pdo; }\n";
        $config .= "    public function prepare(\$sql) { return \$this->pdo->prepare(\$sql); }\n";
        $config .= "    public function query(\$sql) { return \$this->pdo->query(\$sql); }\n";
        $config .= "    public function lastInsertId() { return \$this->pdo->lastInsertId(); }\n";
        $config .= "    public function beginTransaction() { return \$this->pdo->beginTransaction(); }\n";
        $config .= "    public function commit() { return \$this->pdo->commit(); }\n";
        $config .= "    public function rollBack() { return \$this->pdo->rollBack(); }\n";
        $config .= "}\nfunction db() { return DB::getInstance(); }\n";

        file_put_contents(__DIR__ . '/config.php', $config);
        $success = true;
        $msg = "安装成功！管理员账号: {$adminUser}，请立即删除 install.php 文件以确保安全。";
    } catch (Exception $e) {
        $error = "安装失败: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 白屿云平台</title>
    <link rel="stylesheet" href="./assets/font-awesome/css/all.min.css?v=6.4.0">
    <link rel="icon" type="image/svg+xml" href="./favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="./favicon.ico">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --info: #3b82f6;
            --warning: #f59e0b;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
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
        #particles { position:fixed; inset:0; z-index:0; }

        .bg-glow {
            position:fixed; border-radius:50%; filter:blur(80px); opacity:0.35; z-index:0; pointer-events:none;
        }
        .glow-1 { width:500px; height:500px; background:radial-gradient(circle, rgba(99,102,241,0.5), transparent); top:-150px; left:-100px; animation:glowFloat 8s ease-in-out infinite; }
        .glow-2 { width:400px; height:400px; background:radial-gradient(circle, rgba(245,158,11,0.4), transparent); bottom:-120px; right:-80px; animation:glowFloat 10s ease-in-out infinite reverse; }
        @keyframes glowFloat {
            0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(40px,-30px) scale(1.08)} 66%{transform:translate(-30px,20px) scale(.95)}
        }

        .install-box {
            background: rgba(17,25,40,0.88);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            box-shadow: 0 32px 100px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 560px;
            padding: 44px 44px;
            position: relative;
            z-index: 1;
            animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1);
            max-height: 92vh;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .install-box::-webkit-scrollbar { display: none; width: 0; height: 0; }
        @keyframes cardIn {
            from { opacity:0; transform:translateY(30px) scale(.95); }
            to { opacity:1; transform:translateY(0) scale(1); }
        }
        .install-box::before {
            content:''; position:absolute; inset:-1px; border-radius:29px; padding:1px;
            background:linear-gradient(135deg, rgba(99,102,241,0.4), rgba(245,158,11,0.25), rgba(16,185,129,0.3));
            -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; pointer-events:none;
        }

        /* Steps */
        .steps {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            justify-content: center;
        }
        .step-dot {
            width: 42px; height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            background: rgba(255,255,255,0.06);
            color: rgba(148,163,184,0.5);
            border: 2px solid rgba(255,255,255,0.08);
            position: relative;
            transition: all 0.4s;
        }
        .step-dot.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
        }
        .step-dot.done {
            background: rgba(16,185,129,0.2);
            border-color: var(--success);
            color: var(--success);
        }
        .step-line {
            width: 60px; height: 2px;
            background: rgba(255,255,255,0.08);
            align-self: center;
            transition: all 0.4s;
        }
        .step-line.done { background: var(--success); }

        .install-box h1 {
            text-align: center;
            margin-bottom: 6px;
            font-size: 24px;
            font-weight: 700;
            color: #f1f5f9;
        }
        .install-box .subtitle {
            text-align: center;
            color: rgba(148,163,184,0.7);
            margin-bottom: 30px;
            font-size: 14px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 16px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .section-title i {
            width: 32px; height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: #fff;
        }
        .section-title i.icon-db { background: linear-gradient(135deg, var(--info), #2563eb); }
        .section-title i.icon-admin { background: linear-gradient(135deg, var(--warning), #d97706); }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #cbd5e1;
            font-size: 13px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.04);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 14px;
            color: #f1f5f9;
            transition: all 0.3s;
            outline: none;
        }
        .form-group input:hover {
            border-color: rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
        }
        .form-group input:focus {
            border-color: rgba(99,102,241,0.6);
            box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }
        .form-group input::placeholder { color: rgba(148,163,184,0.35); }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.5;
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

        .btn-install {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            letter-spacing: 0.3px;
        }
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(99,102,241,0.45);
        }
        .btn-install:active { transform: scale(0.98); }

        .login-link {
            text-align: center;
            margin-top: 22px;
        }
        .login-link a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--success), #059669);
            color: #fff;
            text-decoration: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        .login-link a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16,185,129,0.45);
        }

        /* 成功界面动画 */
        .success-icon {
            width: 80px; height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--success);
            border: 2px solid rgba(16,185,129,0.3);
            animation: successPop 0.5s cubic-bezier(0.16,1,0.3,1);
        }
        @keyframes successPop {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 600px) {
            .install-box { padding: 32px 24px; border-radius: 22px; margin: 16px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <canvas id="particles"></canvas>
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>

    <div class="install-box">
        <div class="steps">
            <div class="step-dot <?php echo $success ? 'done' : 'active'; ?>">1</div>
            <div class="step-line <?php echo $success ? 'done' : ''; ?>"></div>
            <div class="step-dot <?php echo $success ? 'done' : ''; ?>">2</div>
            <div class="step-line <?php echo $success ? 'done' : ''; ?>"></div>
            <div class="step-dot <?php echo $success ? 'done' : ''; ?>">3</div>
        </div>
        <h1><i class="fas fa-magic" style="margin-right:6px;background:linear-gradient(135deg,#f59e0b,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;"></i>安装向导</h1>
        <p class="subtitle">白屿云平台 BotAPI 管理系统 · 快速部署</p>

        <?php if ($success): ?>
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="font-size:16px;"></i>
                <span><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="login-link">
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> 前往登录</a>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle" style="font-size:16px;"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="section-title">
                    <i class="fas fa-database icon-db"></i> 数据库配置
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>数据库名称</label>
                        <input type="text" name="db_name" placeholder="cloudbot" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数据库用户名</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="db_pass" placeholder="留空则无密码">
                    </div>
                </div>

                <div class="section-title" style="margin-top:24px;">
                    <i class="fas fa-user-shield icon-admin"></i> 管理员账号
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_user" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_pass" placeholder="至少6位" required>
                    </div>
                </div>

                <button type="submit" class="btn-install">
                    <i class="fas fa-rocket" style="margin-right:6px;"></i> 开始安装
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var canvas=document.getElementById('particles'),ctx=canvas.getContext('2d'),w,h,particles=[],maxP=60;
        function resize(){w=canvas.width=window.innerWidth;h=canvas.height=window.innerHeight;}
        resize();window.addEventListener('resize',resize);
        for(var i=0;i<maxP;i++) particles.push({x:Math.random()*w,y:Math.random()*h,r:Math.random()*1.2+0.4,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,o:Math.random()*.5+.2});
        function draw(){
            ctx.clearRect(0,0,w,h);
            for(var i=0;i<particles.length;i++){var p=particles[i];p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>w)p.vx*=-1;if(p.y<0||p.y>h)p.vy*=-1;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle='rgba(148,163,184,'+p.o+')';ctx.fill();}
            for(var i=0;i<particles.length;i++){for(var j=i+1;j<particles.length;j++){var dx=particles[i].x-particles[j].x,dy=particles[i].y-particles[j].y,dist=Math.sqrt(dx*dx+dy*dy);if(dist<100){ctx.beginPath();ctx.moveTo(particles[i].x,particles[i].y);ctx.lineTo(particles[j].x,particles[j].y);ctx.strokeStyle='rgba(245,158,11,'+(.06*(1-dist/100))+')';ctx.lineWidth=.5;ctx.stroke();}}}
            requestAnimationFrame(draw);
        }
        draw();
    })();
    </script>
</body>
</html>
