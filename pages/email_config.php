<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '邮箱配置';
$activePage = 'email_config';

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $settings = [
            'mail_host'       => trim($_POST['mail_host'] ?? ''),
            'mail_port'       => trim($_POST['mail_port'] ?? '465'),
            'mail_username'   => trim($_POST['mail_username'] ?? ''),
            'mail_password'   => trim($_POST['mail_password'] ?? ''),
            'mail_from_name'  => trim($_POST['mail_from_name'] ?? ''),
            'mail_from_addr'  => trim($_POST['mail_from_addr'] ?? ''),
            'mail_encryption' => trim($_POST['mail_encryption'] ?? 'ssl'),
            'mail_enabled'    => trim($_POST['mail_enabled'] ?? '0'),
        ];

        $stmt = db()->prepare("INSERT INTO settings (user_id, `key`, `value`) VALUES (0, ?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v, $v]);
        }
        $success = '邮箱配置已保存';
    } elseif ($action === 'test') {
        $testEmail = trim($_POST['test_email'] ?? '');
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $error = '测试邮箱地址格式不正确';
        } else {
            $result = sendTestMail($testEmail);
            if ($result === true) {
                $success = '测试邮件已发送至 ' . $testEmail . '，请查收';
            } else {
                $error = '发送失败：' . $result;
            }
        }
    }
}

// 获取配置
$mailHost       = getSetting('mail_host');
$mailPort       = getSetting('mail_port') ?: '465';
$mailUsername   = getSetting('mail_username');
$mailPassword   = getSetting('mail_password');
$mailFromName   = getSetting('mail_from_name');
$mailFromAddr   = getSetting('mail_from_addr');
$mailEncryption = getSetting('mail_encryption') ?: 'ssl';
$mailEnabled    = getSetting('mail_enabled');

require '../includes/load_header.php';
?>

<style>
.config-card{max-width:680px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:600px){.form-row{grid-template-columns:1fr;}}
.test-section{margin-top:24px;padding-top:24px;border-top:1px dashed var(--border);}
.status-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
.status-badge.on{background:#dcfce7;color:#166534;}
.status-badge.off{background:#fee2e2;color:#991b1b;}
</style>

<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-envelope" style="color:#6366f1;"></i> 邮箱配置
            <?php if ($mailEnabled === '1'): ?>
            <span class="status-badge on"><i class="fas fa-check-circle"></i> 已启用</span>
            <?php else: ?>
            <span class="status-badge off"><i class="fas fa-times-circle"></i> 未启用</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="card-body config-card">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save">

            <div class="form-group">
                <label><i class="fas fa-toggle-on"></i> 启用状态</label>
                <select name="mail_enabled" class="form-control">
                    <option value="1" <?php echo $mailEnabled === '1' ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo $mailEnabled !== '1' ? 'selected' : ''; ?>>禁用</option>
                </select>
                <span class="hint">启用后系统将使用此配置发送邮件（如注册验证、通知等）</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-server"></i> SMTP 服务器 <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="mail_host" class="form-control" value="<?php echo h($mailHost); ?>" placeholder="例如：smtp.qq.com" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-plug"></i> 端口 <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="mail_port" class="form-control" value="<?php echo h($mailPort); ?>" placeholder="465" required>
                    <span class="hint">SSL通常为465，TLS通常为587</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> SMTP 用户名 <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="mail_username" class="form-control" value="<?php echo h($mailUsername); ?>" placeholder="例如：user@qq.com" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-key"></i> SMTP 密码 / 授权码 <span style="color:#ef4444;">*</span></label>
                    <input type="password" name="mail_password" class="form-control" value="<?php echo h($mailPassword); ?>" placeholder="邮箱密码或SMTP授权码" required>
                    <span class="hint">QQ邮箱需使用授权码，非登录密码</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> 发件人名称</label>
                    <input type="text" name="mail_from_name" class="form-control" value="<?php echo h($mailFromName); ?>" placeholder="例如：白屿云平台">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-at"></i> 发件人邮箱</label>
                    <input type="email" name="mail_from_addr" class="form-control" value="<?php echo h($mailFromAddr); ?>" placeholder="默认使用SMTP用户名">
                    <span class="hint">留空则使用SMTP用户名</span>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> 加密方式</label>
                <select name="mail_encryption" class="form-control">
                    <option value="ssl" <?php echo $mailEncryption === 'ssl' ? 'selected' : ''; ?>>SSL（端口465）</option>
                    <option value="tls" <?php echo $mailEncryption === 'tls' ? 'selected' : ''; ?>>TLS（端口587）</option>
                    <option value="none" <?php echo $mailEncryption === 'none' ? 'selected' : ''; ?>>不加密（端口25）</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> 保存配置
            </button>
        </form>

        <!-- 测试发送 -->
        <div class="test-section">
            <h4 style="margin-bottom:12px;"><i class="fas fa-paper-plane" style="color:#6366f1;"></i> 发送测试邮件</h4>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:12px;">保存配置后，可发送一封测试邮件验证配置是否正确。</p>
            <form method="POST" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="test">
                <input type="email" name="test_email" class="form-control" style="max-width:300px;" placeholder="输入测试收件邮箱" required value="<?php echo h($mailFromAddr ?: $mailUsername); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-paper-plane"></i> 发送测试
                </button>
            </form>
        </div>

        <!-- 常见SMTP配置参考 -->
        <div class="test-section">
            <h4 style="margin-bottom:12px;"><i class="fas fa-info-circle" style="color:#6366f1;"></i> 常见邮箱SMTP配置参考</h4>
            <div class="table-wrapper">
                <table class="table" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>邮箱服务商</th>
                            <th>SMTP服务器</th>
                            <th>端口</th>
                            <th>加密</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>QQ邮箱</td><td>smtp.qq.com</td><td>465</td><td>SSL</td></tr>
                        <tr><td>163邮箱</td><td>smtp.163.com</td><td>465</td><td>SSL</td></tr>
                        <tr><td>126邮箱</td><td>smtp.126.com</td><td>465</td><td>SSL</td></tr>
                        <tr><td>阿里邮箱</td><td>smtp.aliyun.com</td><td>465</td><td>SSL</td></tr>
                        <tr><td>Gmail</td><td>smtp.gmail.com</td><td>465</td><td>SSL</td></tr>
                        <tr><td>Outlook</td><td>smtp.office365.com</td><td>587</td><td>TLS</td></tr>
                        <tr><td>腾讯企业邮</td><td>smtp.exmail.qq.com</td><td>465</td><td>SSL</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>

<?php
/**
 * 发送测试邮件（使用fsockopen直接SMTP通信，不依赖第三方库）
 */
function sendTestMail($to) {
    $host       = getSetting('mail_host');
    $port       = (int)(getSetting('mail_port') ?: 465);
    $username   = getSetting('mail_username');
    $password   = getSetting('mail_password');
    $fromName   = getSetting('mail_from_name') ?: getSiteName();
    $fromAddr   = getSetting('mail_from_addr') ?: $username;
    $encryption = getSetting('mail_encryption') ?: 'ssl';

    if (!$host || !$username || !$password) {
        return '请先完整填写并保存SMTP配置';
    }

    $subject = '【' . getSiteName() . '】测试邮件';
    $body    = "这是一封来自「" . getSiteName() . "」的测试邮件。\n\n如果您收到此邮件，说明邮箱配置正确。\n\n发送时间：" . date('Y-m-d H:i:s');

    // 使用PHP内置mail函数发送（简单方式，兼容性最好）
    $headers = [
        'From' => mb_encode_mimeheader($fromName) . ' <' . $fromAddr . '>',
        'Reply-To' => $fromAddr,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];

    $headerStr = '';
    foreach ($headers as $k => $v) {
        $headerStr .= $k . ': ' . $v . "\r\n";
    }

    $encodedSubject = mb_encode_mimeheader($subject);

    // 尝试使用SMTP方式发送
    $result = smtpSend($host, $port, $username, $password, $fromAddr, $to, $encodedSubject, $body, $headerStr, $encryption);
    return $result;
}

/**
 * 通过SMTP协议直接发送邮件
 */
function smtpSend($host, $port, $username, $password, $from, $to, $subject, $body, $headers, $encryption) {
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return '无法连接到SMTP服务器：' . $host . ':' . $port . '（' . $errstr . '）';
    }

    stream_set_timeout($fp, 15);

    $log = [];
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '220') {
        fclose($fp);
        return '服务器未就绪：' . trim($response);
    }

    // EHLO
    fwrite($fp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = '';
    while ($line = fgets($fp, 515)) {
        $log[] = 'S: ' . trim($line);
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }

    // STARTTLS
    if ($encryption === 'tls') {
        fwrite($fp, "STARTTLS\r\n");
        $response = fgets($fp, 515);
        $log[] = 'S: ' . trim($response);
        if (substr($response, 0, 3) !== '220') {
            fclose($fp);
            return 'STARTTLS失败：' . trim($response);
        }
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    }

    // AUTH LOGIN
    fwrite($fp, "AUTH LOGIN\r\n");
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '334') {
        fclose($fp);
        return 'AUTH请求失败：' . trim($response);
    }

    fwrite($fp, base64_encode($username) . "\r\n");
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '334') {
        fclose($fp);
        return '用户名认证失败：' . trim($response);
    }

    fwrite($fp, base64_encode($password) . "\r\n");
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '235') {
        fclose($fp);
        return '密码/授权码认证失败：' . trim($response) . '（请检查密码或授权码是否正确）';
    }

    // MAIL FROM
    fwrite($fp, "MAIL FROM:<{$from}>\r\n");
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '250') {
        fclose($fp);
        return '发件人设置失败：' . trim($response);
    }

    // RCPT TO
    fwrite($fp, "RCPT TO:<{$to}>\r\n");
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '250') {
        fclose($fp);
        return '收件人设置失败：' . trim($response);
    }

    // DATA
    fwrite($fp, "DATA\r\n");
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '354') {
        fclose($fp);
        return 'DATA命令失败：' . trim($response);
    }

    // 邮件内容：所有头部字段必须在空行之前
    $message = $headers;
    $message .= "Subject: {$subject}\r\n";
    $message .= "To: {$to}\r\n";
    $message .= "\r\n";
    $message .= $body . "\r\n";
    $message .= ".\r\n";

    fwrite($fp, $message);
    $response = fgets($fp, 515);
    $log[] = 'S: ' . trim($response);
    if (substr($response, 0, 3) !== '250') {
        fclose($fp);
        return '邮件发送失败：' . trim($response);
    }

    // QUIT
    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return true;
}
