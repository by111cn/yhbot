<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$pageTitle = '仪表盘';
$activePage = 'home';
$userId = $_SESSION['user_id'];

// 统计数据
$apiCount = countTable('apis', 'user_id = ?', [$userId]);
$botCount = countTable('bots', 'user_id = ?', [$userId]);

$stmt = db()->prepare("SELECT COUNT(*) as count FROM message_logs l JOIN bots b ON l.bot_id = b.id WHERE b.user_id = ?");
$stmt->execute([$userId]);
$logCount = $stmt->fetch()['count'];

$stmt = db()->prepare("SELECT COUNT(*) as count FROM message_logs l JOIN bots b ON l.bot_id = b.id WHERE b.user_id = ? AND DATE(l.created_at) = CURDATE()");
$stmt->execute([$userId]);
$todayCount = $stmt->fetch()['count'];

// 最近API
$recentApis = getApiList($userId);
$recentApis = array_slice($recentApis, 0, 5);

// 最近消息日志
$stmt = db()->prepare("SELECT l.*, b.name as bot_name FROM message_logs l JOIN bots b ON l.bot_id = b.id WHERE b.user_id = ? ORDER BY l.created_at DESC LIMIT 6");
$stmt->execute([$userId]);
$recentLogs = $stmt->fetchAll();

require 'includes/load_header.php';
?>

<!-- 欢迎横幅 -->
<div class="hero-banner">
    <div class="hero-bg-shapes">
        <div class="hero-shape shape-1"></div>
        <div class="hero-shape shape-2"></div>
        <div class="hero-shape shape-3"></div>
    </div>
    <div class="hero-content">
        <div class="hero-left">
            <div class="hero-greeting">
                <?php
                $hour = (int)date('H');
                $greeting = $hour < 6 ? '夜深了' : ($hour < 9 ? '早上好' : ($hour < 12 ? '上午好' : ($hour < 14 ? '中午好' : ($hour < 18 ? '下午好' : '晚上好'))));
                ?>
                <span class="hero-wave"><?php echo ['🌅','☀️','🌤️','🌙','🌟'][(int)($hour/6)]; ?></span>
                <?php echo $greeting; ?>，<strong><?php echo h((($user && isset($user['nickname'])) ? ($user['nickname'] ?: $user['username']) : '用户')); ?></strong>
            </div>
            <p class="hero-subtitle">欢迎回到白屿云平台 BotAPI 管理系统，一切运行正常</p>
        </div>
        <div class="hero-right">
            <div class="hero-stat-mini">
                <div class="hero-stat-value"><?php echo $todayCount; ?></div>
                <div class="hero-stat-label">今日消息</div>
            </div>
            <div class="hero-divider"></div>
            <div class="hero-stat-mini">
                <div class="hero-stat-value"><?php echo $apiCount + $botCount; ?></div>
                <div class="hero-stat-label">资源配置</div>
            </div>
            <div class="hero-divider"></div>
            <div class="hero-date-badge">
                <i class="far fa-calendar-alt"></i>
                <span><?php echo date('Y/m/d'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 统计卡片 -->
<div class="stats-grid">
    <div class="stat-card card-purple">
        <div class="stat-icon purple">
            <i class="fas fa-code"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $apiCount; ?></div>
            <div class="stat-label">API 总量</div>
        </div>
        <div class="stat-spark spark-purple"></div>
    </div>
    <div class="stat-card card-green">
        <div class="stat-icon green">
            <i class="fas fa-robot"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $botCount; ?></div>
            <div class="stat-label">官机数量</div>
        </div>
        <div class="stat-spark spark-green"></div>
    </div>
    <div class="stat-card card-orange">
        <div class="stat-icon orange">
            <i class="fas fa-message"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $logCount; ?></div>
            <div class="stat-label">消息日志</div>
        </div>
        <div class="stat-spark spark-orange"></div>
    </div>
    <div class="stat-card card-blue">
        <div class="stat-icon blue">
            <i class="fas fa-bolt"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $todayCount; ?></div>
            <div class="stat-label">今日消息</div>
        </div>
        <div class="stat-spark spark-blue"></div>
    </div>
</div>

<!-- 双栏布局 -->
<div class="card-grid cols-2">
    <!-- 快捷入口 -->
    <div class="card">
        <div class="card-header">
            <h3><span class="icon-dot purple"></span>快捷入口</h3>
        </div>
        <div class="card-body">
            <div class="quick-grid" style="grid-template-columns: repeat(3, 1fr);">
                <a href="pages/api_add.php" class="quick-item">
                    <div class="quick-icon" style="color:#6366f1;background:rgba(99,102,241,0.08);">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <span class="quick-label">添加API</span>
                </a>
                <a href="pages/api_list.php" class="quick-item">
                    <div class="quick-icon" style="color:#10b981;background:rgba(16,185,129,0.08);">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <span class="quick-label">API列表</span>
                </a>
                <a href="pages/menu_list.php" class="quick-item">
                    <div class="quick-icon" style="color:#f59e0b;background:rgba(245,158,11,0.08);">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <span class="quick-label">菜单列表</span>
                </a>
                <a href="pages/bot_management.php" class="quick-item">
                    <div class="quick-icon" style="color:#ec4899;background:rgba(236,72,153,0.08);">
                        <i class="fas fa-crown"></i>
                    </div>
                    <span class="quick-label">官机管理</span>
                </a>
                <a href="pages/message_logs.php" class="quick-item">
                    <div class="quick-icon" style="color:#8b5cf6;background:rgba(139,92,246,0.08);">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <span class="quick-label">消息日志</span>
                </a>
                <a href="pages/profile.php" class="quick-item">
                    <div class="quick-icon" style="color:#06b6d4;background:rgba(6,182,212,0.08);">
                        <i class="fas fa-user-gear"></i>
                    </div>
                    <span class="quick-label">个人设置</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 最近API -->
    <div class="card">
        <div class="card-header">
            <h3><span class="icon-dot purple"></span>最近添加的 API</h3>
            <a href="pages/api_list.php" class="btn btn-sm btn-primary">查看全部 <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body">
            <?php if (empty($recentApis)): ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                    <p>暂无 API</p>
                    <span class="subtext">快去添加你的第一个 API 吧</span>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($recentApis as $api): ?>
                        <div class="mini-api-item">
                            <div class="mini-api-icon" style="background: <?php echo $api['category_color'] ?? 'var(--primary)'; ?>;">
                                <i class="fas <?php echo $api['category_icon'] ?? 'fa-link'; ?>"></i>
                            </div>
                            <div class="mini-api-info">
                                <div class="mini-api-name"><?php echo htmlspecialchars($api['name']); ?></div>
                                <div class="mini-api-cmd"><?php echo htmlspecialchars($api['command']); ?></div>
                            </div>
                            <span class="tag tag-blue"><?php echo htmlspecialchars($api['method']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 最近消息日志 -->
<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot orange"></span>最近消息日志</h3>
        <a href="pages/message_logs.php" class="btn btn-sm btn-secondary">查看全部 <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="card-body">
        <?php if (empty($recentLogs)): ?>
            <div class="empty-state" style="padding:40px 20px;">
                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                <p>暂无消息日志</p>
                <span class="subtext">当机器人收发消息后，日志将显示在这里</span>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>机器人</th>
                            <th>用户</th>
                            <th>类型</th>
                            <th>方向</th>
                            <th>内容</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($log['bot_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($log['from_name'] ?: ($log['from_id'] ?: '-')); ?></td>
                            <td>
                                <span class="tag <?php echo ($log['chat_type'] ?? '') === 'group' ? 'tag-indigo' : 'tag-pink'; ?>">
                                    <?php echo ($log['chat_type'] ?? '') === 'group' ? '群聊' : '私聊'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="tag <?php echo $log['type'] === 'send' ? 'tag-green' : 'tag-blue'; ?>">
                                    <?php echo $log['type'] === 'send' ? '已发送' : '已接收'; ?>
                                </span>
                            </td>
                            <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?php echo htmlspecialchars($log['content']); ?>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted);">
                                <?php echo date('m-d H:i', strtotime($log['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ===== Hero Banner ===== */
.hero-banner {
    position: relative;
    background: linear-gradient(135deg, #1a1744 0%, #272162 40%, #312e81 70%, #3730a3 100%);
    border-radius: var(--radius-xl);
    padding: 32px 36px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(49,46,129,0.3);
}
.hero-bg-shapes {
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.hero-shape {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
}
.hero-shape.shape-1 {
    width: 280px; height: 280px;
    top: -80px; right: -60px;
}
.hero-shape.shape-2 {
    width: 160px; height: 160px;
    bottom: -40px; right: 180px;
    background: rgba(255,255,255,0.05);
}
.hero-shape.shape-3 {
    width: 100px; height: 100px;
    top: 20px; left: 40%;
    background: rgba(255,255,255,0.04);
    border-radius: 30%;
    transform: rotate(45deg);
}
.hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.hero-greeting {
    font-size: 24px;
    font-weight: 400;
    color: rgba(255,255,255,0.95);
    letter-spacing: -0.3px;
}
.hero-greeting strong {
    font-weight: 700;
    color: #fff;
}
.hero-wave {
    display: inline-block;
    font-size: 26px;
    margin-right: 4px;
    animation: waveBounce 2s ease-in-out infinite;
}
@keyframes waveBounce {
    0%,100% { transform: rotate(0deg); }
    25% { transform: rotate(15deg) scale(1.1); }
    75% { transform: rotate(-8deg) scale(1.05); }
}
.hero-subtitle {
    color: rgba(255,255,255,0.55);
    font-size: 13px;
    margin-top: 4px;
    letter-spacing: 0.3px;
}
.hero-right {
    display: flex;
    align-items: center;
    gap: 20px;
    background: rgba(255,255,255,0.07);
    padding: 16px 24px;
    border-radius: var(--radius-md);
    backdrop-filter: blur(10px);
}
.hero-stat-mini {
    text-align: center;
}
.hero-stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    letter-spacing: -0.5px;
    font-feature-settings: "tnum";
}
.hero-stat-label {
    font-size: 11px;
    color: rgba(255,255,255,0.45);
    margin-top: 3px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.hero-divider {
    width: 1px;
    height: 36px;
    background: rgba(255,255,255,0.12);
}
.hero-date-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    color: rgba(255,255,255,0.5);
    font-size: 13px;
    font-weight: 500;
}

/* ===== 统计卡片的微动效装饰 ===== */
.stat-spark {
    position: absolute;
    bottom: 12px;
    right: 16px;
    width: 60px;
    height: 28px;
    border-radius: 14px;
    opacity: 0.06;
    transition: opacity var(--transition-base);
}
.stat-card:hover .stat-spark { opacity: 0.12; }
.spark-purple { background: var(--primary); }
.spark-green { background: var(--success); }
.spark-orange { background: var(--warning); }
.spark-blue { background: var(--info); }

/* ===== 迷你API列表项 ===== */
.mini-api-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg);
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
    border: 1.5px solid transparent;
}
.mini-api-item:hover {
    border-color: var(--border);
    background: var(--bg-card);
    box-shadow: var(--shadow-sm);
    transform: translateX(3px);
}
.mini-api-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 15px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.mini-api-info { flex: 1; min-width: 0; }
.mini-api-name {
    font-size: 14px; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mini-api-cmd {
    font-size: 12px; color: var(--text-muted);
    font-family: var(--font-mono);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

@media (max-width: 768px) {
    .hero-content { flex-direction: column; text-align: center; }
    .hero-right { width: 100%; justify-content: center; }
    .hero-greeting { font-size: 20px; }
}
</style>

<?php require 'includes/load_footer.php'; ?>
