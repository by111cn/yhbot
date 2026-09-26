<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '赞赏人员';
$activePage = 'appreciation';
$user = getCurrentUser();

require '../includes/load_header.php';
?>

<div class="card" style="max-width:720px;">
    <div class="card-header">
        <h3><span class="icon-dot" style="background:#ec4899;"></span>赞赏人员</h3>
    </div>
    <div class="card-body">
        <div style="text-align:center;margin-bottom:32px;">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#ec4899,#f472b6);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:36px;box-shadow:0 8px 24px rgba(236,72,153,0.3);margin-bottom:12px;">
                <i class="fas fa-heart"></i>
            </div>
            <div style="font-size:18px;font-weight:600;color:var(--text);">感谢每一位支持者</div>
            <div style="color:var(--text-muted);font-size:13px;margin-top:4px;">您的支持是我们前进的动力</div>
        </div>

        <div class="appreciation-grid">
            <div class="appreciation-card">
                <img class="appreciation-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=3805409582&s=640" alt="酵色">
                <div class="appreciation-name">酵色</div>
                <div class="appreciation-qq"><i class="fab fa-qq"></i> 3805409582</div>
                <div class="appreciation-amount">￥6.66</div>
            </div>
            <div class="appreciation-card">
                <img class="appreciation-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=2151559271&s=640" alt="狐狸">
                <div class="appreciation-name">狐狸</div>
                <div class="appreciation-qq"><i class="fab fa-qq"></i> 2151559271</div>
                <div class="appreciation-amount">￥5.20</div>
            </div>
            <div class="appreciation-card">
                <img class="appreciation-avatar" src="https://q4.qlogo.cn/g?b=qq&nk=2383526861&s=640" alt="白茶清欢">
                <div class="appreciation-name">白茶清欢</div>
                <div class="appreciation-qq"><i class="fab fa-qq"></i> 2383526861</div>
                <div class="appreciation-amount">￥2.00</div>
            </div>
        </div>


    </div>
</div>

<style>
.appreciation-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.appreciation-card {
    text-align: center;
    padding: 18px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-card);
    transition: all 0.25s;
}
.appreciation-card:hover {
    border-color: #f472b6;
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(236,72,153,0.1);
}
.appreciation-avatar {
    width: 56px; height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--border);
    margin-bottom: 8px;
}
.appreciation-card:hover .appreciation-avatar {
    border-color: #ec4899;
}
.appreciation-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 2px;
}
.appreciation-qq {
    font-size: 11px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    margin-bottom: 4px;
}
.appreciation-amount {
    font-size: 14px;
    font-weight: 700;
    color: #ec4899;
    font-family: var(--font-mono);
}
.appreciation-desc {
    font-size: 11px;
    color: var(--text-muted);
}
@media (max-width: 600px) {
    .appreciation-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>

<?php require '../includes/load_footer.php'; ?>
