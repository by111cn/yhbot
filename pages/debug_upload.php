<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '上传调试';
$activePage = 'debug_upload';

require '../includes/load_header.php';
?>
<style>
.debug-section{margin:20px 0;padding:18px;background:var(--bg);border-radius:var(--radius-xs);border:1px solid var(--border-light)}
.debug-section h4{margin:0 0 12px;font-size:15px;display:flex;align-items:center;gap:8px}
.debug-section h4 .badge{font-size:11px;padding:2px 8px;border-radius:var(--radius-full);background:var(--primary);color:#fff}
.log-box{background:#0f172a;color:#94a3b8;padding:14px 18px;border-radius:8px;font-family:'Consolas','Courier New',monospace;font-size:12px;line-height:1.7;max-height:400px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}
.log-box .ok{color:#4ade80}
.log-box .err{color:#f87171}
.log-box .warn{color:#fbbf24}
.log-box .info{color:#60a5fa}
.log-box .time{color:#64748b}
.config-grid{display:grid;grid-template-columns:200px 1fr;gap:6px 14px;font-size:13px}
.config-grid .k{color:var(--text-muted)}
.config-grid .v{font-family:monospace;color:var(--text)}
.config-grid .v.danger{color:#dc2626;font-weight:600}
.config-grid .v.ok{color:#16a34a;font-weight:600}
.refresh-btn{position:fixed;top:80px;right:20px;z-index:100}
.file-list{list-style:none;padding:0;margin:0}
.file-list li{padding:8px 12px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;font-size:13px}
.file-list li:last-child{border-bottom:none}
.file-list .meta{color:var(--text-muted);font-size:11px}
.empty{color:var(--text-muted);font-style:italic;padding:10px}
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>上传调试面板</h3>
        <button class="btn btn-secondary refresh-btn" onclick="location.reload()"><i class="fas fa-sync"></i> 刷新</button>
    </div>
    <div class="card-body">

        <div class="debug-section">
            <h4>1. PHP / FPM 配置 <span class="badge" style="background:#6366f1">影响上传能不能跑完</span></h4>
            <div class="config-grid">
                <div class="k">PHP 版本</div><div class="v"><?= PHP_VERSION ?></div>
                <div class="k">max_execution_time</div>
                <div class="v <?= (int)ini_get('max_execution_time') < 60 ? 'danger' : 'ok' ?>"><?= ini_get('max_execution_time') ?: '(无限制)' ?> 秒</div>
                <div class="k">memory_limit</div>
                <div class="v"><?= ini_get('memory_limit') ?: '(无限制)' ?></div>
                <div class="k">cURL 超时（当前代码）</div><div class="v">单策略 45 秒，4 策略共 180 秒</div>
                <div class="k">SAPI</div><div class="v"><?= php_sapi_name() ?></div>
                <div class="k">ignore_user_abort</div>
                <div class="v <?= ignore_user_abort() ? 'ok' : 'danger' ?>"><?= ignore_user_abort() ? 'true (已开启)' : 'false (会因客户端断开而退出!)' ?></div>
                <div class="k">fastcgi_finish_request</div>
                <div class="v <?= function_exists('fastcgi_finish_request') ? 'ok' : 'danger' ?>"><?= function_exists('fastcgi_finish_request') ? '可用 (已开启)' : '不可用' ?></div>
                <div class="k">临时目录</div>
                <div class="v <?= is_writable(sys_get_temp_dir()) ? 'ok' : 'danger' ?>"><?= h(sys_get_temp_dir()) ?> <?= is_writable(sys_get_temp_dir()) ? '(可写)' : '(不可写!)' ?></div>
            </div>
        </div>

        <?php
        $logFiles = [
            'uploads/webhook-startup.log' => '启动日志（每次 webhook 进来/退出）',
            'uploads/webhook-fatal.log'   => '致命错误（FPM 杀进程时记录）',
            'uploads/upload-debug.log'    => '上传策略进度（每个策略 try/done）',
        ];
        foreach ($logFiles as $rel => $title):
            $abs = __DIR__ . '/../' . $rel;
            $exists = is_file($abs);
            $size = $exists ? filesize($abs) : 0;
            $content = $exists ? file_get_contents($abs) : '';
            // 只取最后 50 行
            $lines = explode("\n", trim($content));
            $lines = array_slice($lines, -50);
            $content = implode("\n", $lines);
            // 简单高亮
            $content = h($content);
            $content = preg_replace('/^\[BOOT ([^\]]+)\]/m', '<span class="info">[BOOT $1]</span>', $content);
            $content = preg_replace('/^\[EXIT ([^\]]+)\] (.*?)(CLIENT-ABORT)/m', '<span class="warn">[EXIT $1] $2</span><span class="err">$3</span>', $content);
            $content = preg_replace('/\[YH-UPLOAD\] try/m', '<span class="info">[YH-UPLOAD] try</span>', $content);
            $content = preg_replace('/\[YH-UPLOAD\] done/m', '<span class="info">[YH-UPLOAD] done</span>', $content);
            $content = preg_replace('/\[FATAL\]/m', '<span class="err">[FATAL]</span>', $content);
            $content = preg_replace('/\[FASTCGI-FINISH ([^\]]+)\]/m', '<span class="ok">[FASTCGI-FINISH $1]</span>', $content);
        ?>
        <div class="debug-section">
            <h4><?= h($title) ?> <span class="badge" style="background:<?= $exists ? '#10b981' : '#9ca3af' ?>"><?= $exists ? h($size) . ' 字节' : '不存在' ?></span></h4>
            <?php if ($exists && $content): ?>
                <div class="log-box"><?= $content ?: '(空)' ?></div>
            <?php else: ?>
                <div class="empty">文件不存在或为空 — 说明 PHP 还没跑到写日志那一步，或者根本没收到 webhook</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="debug-section">
            <h4>2. uploads/videos/ 备份文件</h4>
            <?php
            $videoDir = __DIR__ . '/../uploads/videos';
            $files = is_dir($videoDir) ? @scandir($videoDir) : [];
            $files = array_filter($files ?: [], fn($f) => !in_array($f, ['.', '..', 'index.html', 'file.php']));
            if (empty($files)): ?>
                <div class="empty">(空目录) — 视频上传失败时本应备份到这里</div>
            <?php else: ?>
                <ul class="file-list">
                    <?php foreach ($files as $f):
                        $abs = $videoDir . '/' . $f;
                        $sz = is_file($abs) ? filesize($abs) : 0;
                        $url = SITE_URL . '/uploads/videos/file.php?f=' . urlencode($f);
                    ?>
                    <li>
                        <div>
                            <a href="<?= h($url) ?>" target="_blank"><i class="fas fa-play-circle"></i> <?= h($f) ?></a>
                        </div>
                        <div class="meta"><?= h($sz) ?> 字节 · <?= h(date('Y-m-d H:i:s', filemtime($abs))) ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="debug-section">
            <h4>3. uploads/files/ 备份文件</h4>
            <?php
            $fileDir = __DIR__ . '/../uploads/files';
            $files = is_dir($fileDir) ? @scandir($fileDir) : [];
            $files = array_filter($files ?: [], fn($f) => !in_array($f, ['.', '..', 'index.html', 'file.php']));
            if (empty($files)) {
                echo '<div class="empty">(空目录)</div>';
            } else {
                echo '<ul class="file-list">';
                foreach ($files as $f) {
                    $abs = $fileDir . '/' . $f;
                    $sz = is_file($abs) ? filesize($abs) : 0;
                    $url = SITE_URL . '/uploads/files/file.php?f=' . urlencode($f);
                    echo '<li>';
                    echo '<div><a href="' . h($url) . '" target="_blank"><i class="fas fa-file"></i> ' . h($f) . '</a></div>';
                    echo '<div class="meta">' . h($sz) . ' 字节 · ' . h(date('Y-m-d H:i:s', filemtime($abs))) . '</div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            ?>
        </div>

        <div class="debug-section">
            <h4>4. 最近 5 条 message_logs（视频/上传相关）</h4>
            <?php
            try {
                $stmt = db()->prepare("SELECT * FROM message_logs WHERE content LIKE '%视频%' OR content LIKE '%上传%' OR content LIKE '%FATAL%' ORDER BY id DESC LIMIT 5");
                $stmt->execute();
                $logs = $stmt->fetchAll();
            } catch (Throwable $e) { $logs = []; }
            if (empty($logs)): ?>
                <div class="empty">没有相关日志</div>
            <?php else: ?>
                <div class="log-box"><?php foreach ($logs as $l): ?>
<span class="time">[<?= h($l['created_at']) ?>]</span> <span class="<?= $l['type']==='error'?'err':'info' ?>">[<?= h($l['type']) ?>]</span> <?= h($l['content']) ?>

<?php endforeach; ?></div>
            <?php endif; ?>
        </div>

        <div class="debug-section">
            <h4>5. 立即测试上传</h4>
            <p style="color:var(--text-muted);font-size:13px">填入 Bot Token 和一个公网可访问的视频 URL（http://…mp4），同步跑上传流程，立即看到结果：</p>
            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                <div>
                    <label class="form-label">Bot Token</label>
                    <input type="text" name="test_token" class="form-control" value="<?= h($_GET['test_token'] ?? '') ?>" style="width:280px" placeholder="从机器人管理复制">
                </div>
                <div>
                    <label class="form-label">视频 URL</label>
                    <input type="text" name="test_url" class="form-control" value="<?= h($_GET['test_url'] ?? '') ?>" style="width:380px" placeholder="https://example.com/test.mp4">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-rocket"></i> 测试</button>
            </form>
            <?php
            if (!empty($_GET['test_token']) && !empty($_GET['test_url'])) {
                $token = $_GET['test_token'];
                $testUrl = $_GET['test_url'];
                echo '<div class="log-box" style="margin-top:14px">';
                echo "<span class='info'>[TEST START]</span>  token=" . h(substr($token, 0, 6)) . "***  url={$testUrl}\n";
                // 下载视频
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $testUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $bin = curl_exec($ch);
                $info = curl_getinfo($ch);
                $err = curl_error($ch);
                curl_close($ch);
                echo "<span class='info'>[DOWNLOAD]</span> http={$info['http_code']} size=" . strlen($bin) . " err={$err} ctype=" . ($info['content_type'] ?? '?') . "\n";
                if ($bin && strlen($bin) > 1000) {
                    $ext = strtolower(pathinfo(parse_url($testUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'mp4';
                    $t0 = microtime(true);
                    $key = yunhuUploadVideo($token, $bin, 'test.' . $ext);
                    $dt = round((microtime(true) - $t0) * 1000);
                    echo "<span class='info'>[UPLOAD]</span> 耗时={$dt}ms\n";
                    echo "<span class='" . ($key ? 'ok' : 'err') . "'>[RESULT]</span> videoKey=" . ($key ?: '(空)');
                    $lastErr = yunhuGetLastUploadError();
                    if ($lastErr) {
                        echo "\n<span class='err'>[ERROR]</span> " . h($lastErr['error']);
                        echo "\n<span class='warn'>[DEBUG]</span> " . h(substr($lastErr['debug'] ?? '', 0, 800));
                    }
                } else {
                    echo "<span class='err'>[DOWNLOAD-FAIL]</span> 视频下载失败\n";
                }
                echo '</div>';
            }
            ?>
        </div>

    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
