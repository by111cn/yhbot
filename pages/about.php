<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '关于系统';
$activePage = 'about';
$user = getCurrentUser();

$phpVersion = phpversion();
$mysqlVersion = db()->query("SELECT VERSION() as v")->fetch()['v'];
$userCount = countTable('users');

require '../includes/load_header.php';
?>

<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h3><span class="icon-dot"></span>关于系统</h3>
    </div>
    <div class="card-body">
        <div style="text-align:center;margin-bottom:32px;">
            <div style="width:88px;height:88px;border-radius:24px;background:linear-gradient(135deg,#6366f1,#818cf8);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:40px;box-shadow:0 8px 24px rgba(99,102,241,.3);margin-bottom:16px;">
                <i class="fas fa-island-tropical"></i>
            </div>
            <div style="font-size:20px;font-weight:700;color:var(--text);"><?php echo getSiteName(); ?></div>
            <div style="color:var(--text-muted);font-size:13px;margin-top:6px;">版本 v<?php echo SITE_VERSION; ?></div>
        </div>

        <div class="about-member-grid">
            <div class="about-member-card">
                <img class="about-member-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=482171260&s=640" alt="白屿">
                <div class="about-member-role"><i class="fas fa-feather"></i> 作者</div>
                <div class="about-member-name">白屿</div>
                <div class="about-member-qq"><i class="fab fa-qq"></i> 482171260</div>
            </div>
            <div class="about-member-card">
                <img class="about-member-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=2475185778&s=640" alt="云烟">
                <div class="about-member-role"><i class="fas fa-crown"></i> CEO</div>
                <div class="about-member-name">云烟</div>
                <div class="about-member-qq"><i class="fab fa-qq"></i> 2475185778</div>
            </div>
            <div class="about-member-card">
                <img class="about-member-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=2182344375&s=640" alt="白轩">
                <div class="about-member-role"><i class="fas fa-headset"></i> 技术支持</div>
                <div class="about-member-name">白轩</div>
                <div class="about-member-qq"><i class="fab fa-qq"></i> 2182344375</div>
            </div>
            <div class="about-member-card">
                <img class="about-member-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=2151559271&s=640" alt="狐狸">
                <div class="about-member-role"><i class="fas fa-paw"></i> 吉祥物</div>
                <div class="about-member-name">狐狸</div>
                <div class="about-member-qq"><i class="fab fa-qq"></i> 2151559271</div>
            </div>
            <div class="about-member-card">
                <img class="about-member-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=1512967450&s=640" alt="魔王">
                <div class="about-member-role"><i class="fas fa-paw"></i> 吉祥物</div>
                <div class="about-member-name">魔王</div>
                <div class="about-member-qq"><i class="fab fa-qq"></i> 1512967450</div>
            </div>
            <div class="about-member-card">
                <img class="about-member-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=3805409582&s=640" alt="酵色">
                <div class="about-member-role"><i class="fas fa-chess-king"></i> 皇帝</div>
                <div class="about-member-name">酵色</div>
                <div class="about-member-qq"><i class="fab fa-qq"></i> 3805409582</div>
            </div>
        </div>

        <div class="about-divider"></div>

        <div class="about-section-title"><i class="fas fa-server"></i> 系统信息</div>
        <div class="sys-info-grid">
            <div class="sys-info-item">
                <div class="sys-info-val"><?php echo $phpVersion; ?></div>
                <div class="sys-info-lbl">PHP 版本</div>
            </div>
            <div class="sys-info-item">
                <div class="sys-info-val"><?php echo explode('.', $mysqlVersion)[0] . '.' . (explode('.', $mysqlVersion)[1] ?? '0'); ?></div>
                <div class="sys-info-lbl">MySQL</div>
            </div>
            <div class="sys-info-item">
                <div class="sys-info-val"><?php echo $userCount; ?></div>
                <div class="sys-info-lbl">用户总数</div>
            </div>
            <div class="sys-info-item">
                <div class="sys-info-val">v<?php echo SITE_VERSION; ?></div>
                <div class="sys-info-lbl">系统版本</div>
            </div>
        </div>
    </div>
</div>

<style>
.about-member-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.about-member-card {
    text-align: center;
    padding: 22px 14px 18px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-card);
    transition: all 0.25s;
}
.about-member-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(99,102,241,0.1);
}
.about-member-avatar {
    width: 64px; height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--border);
    margin-bottom: 10px;
    transition: border-color 0.2s;
}
.about-member-card:hover .about-member-avatar {
    border-color: var(--primary);
}
.about-member-role {
    font-size: 11px;
    font-weight: 600;
    color: var(--primary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(99,102,241,0.08);
    padding: 3px 10px;
    border-radius: 20px;
    margin-bottom: 6px;
}
.about-member-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
}
.about-member-qq {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.about-divider {
    height: 1px;
    background: var(--border);
    margin: 24px 0;
}
.about-section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 14px;
}
.about-section-title i {
    color: var(--primary);
}
.sys-info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
.sys-info-item {
    text-align: center;
    padding: 14px 10px;
    background: var(--bg);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}
.sys-info-val {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    font-family: var(--font-mono);
}
.sys-info-lbl {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}
@media (max-width: 600px) {
    .about-member-grid { grid-template-columns: repeat(2, 1fr); }
    .sys-info-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php require '../includes/load_footer.php'; ?>
