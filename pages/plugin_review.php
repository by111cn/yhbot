<?php
/**
 * BotAPI 插件审核管理
 * 管理员审核用户提交到市场的插件
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
requireLogin();
requireAdmin();

$pageTitle = '插件审核';
$activePage = 'plugin_review';

require __DIR__ . '/../includes/load_header.php';
?>

    <!-- 插件审核页面 -->
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-clipboard-check"></i> 插件审核</h2>
        <p class="page-desc">审核用户提交的插件</p>
    </div>

    <!-- 审核标签切换 -->
    <div class="review-tabs">
        <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending')">
            <i class="fas fa-hourglass-half"></i> 待审核
        </button>
        <button class="tab-btn" data-tab="reviewed" onclick="switchTab('reviewed')">
            <i class="fas fa-check-circle"></i> 已审核
        </button>
    </div>

    <!-- 待审核列表 -->
    <div class="review-panel" id="pendingPanel">
        <div class="review-list" id="pendingList">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                <h3>加载中...</h3>
            </div>
        </div>
    </div>

    <!-- 已审核列表 -->
    <div class="review-panel" id="reviewedPanel" style="display:none;">
        <div class="review-list" id="reviewedList">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                <h3>加载中...</h3>
            </div>
        </div>
    </div>

<script>
// ===== 审核数据加载 =====
function loadPendingList() {
    const container = document.getElementById('pendingList');
    container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div><h3>加载中...</h3></div>';

    fetch('../api/plugin_market.php?action=review_list')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><h3>加载失败</h3><p>' + escapeHtml(data.message || '未知错误') + '</p></div>';
                return;
            }
            if (!data.data || data.data.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-inbox"></i></div><h3>暂无待审核插件</h3><p>暂时没有用户提交的插件等待审核</p></div>';
                return;
            }
            renderPendingList(data.data);
        })
        .catch(err => {
            container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><h3>请求失败</h3><p>' + escapeHtml(err.message) + '</p></div>';
        });
}

function loadReviewedList() {
    const container = document.getElementById('reviewedList');
    container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div><h3>加载中...</h3></div>';

    // 已审核 = 已通过(2) + 已拒绝(3)
    fetch('../api/plugin_market.php?action=reviewed_list')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data || data.data.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-check-circle"></i></div><h3>暂无已审核插件</h3><p>尚未有插件通过或拒绝审核</p></div>';
                return;
            }
            renderReviewedList(data.data);
        })
        .catch(err => {
            container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><h3>请求失败</h3><p>' + escapeHtml(err.message) + '</p></div>';
        });
}

function renderPendingList(plugins) {
    const container = document.getElementById('pendingList');
    container.innerHTML = '';

    plugins.forEach(p => {
        const card = document.createElement('div');
        card.className = 'plugin-card';
        card.id = 'review-' + p.id;
        card.innerHTML = `
            <div class="plugin-card-header">
                <div class="plugin-name">
                    ${escapeHtml(p.name)}
                    ${p.version ? '<span class="plugin-version">v' + escapeHtml(p.version) + '</span>' : ''}
                </div>
                <div class="plugin-actions">
                    <span class="review-badge pending">待审核</span>
                </div>
            </div>
            <div class="plugin-card-body">
                <div class="plugin-meta">
                    ${p.author ? '<span class="plugin-author">👤 ' + escapeHtml(p.author) + '</span>' : ''}
                    <span class="plugin-owner">📝 ${escapeHtml(p.publisher_name || p.username || '未知用户')}</span>
                    ${p.created_at ? '<span class="plugin-time">🕐 ' + escapeHtml(p.created_at) + '</span>' : ''}
                </div>
                ${p.desc ? '<div class="plugin-desc">' + nl2br(escapeHtml(p.desc)) + '</div>' : ''}
            </div>
            <div class="plugin-card-footer review-footer">
                <button class="btn btn-success btn-sm" onclick="approvePlugin(${p.id})">
                    <i class="fas fa-check"></i> 通过
                </button>
                <button class="btn btn-danger btn-sm" onclick="rejectPlugin(${p.id})">
                    <i class="fas fa-times"></i> 拒绝
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

function renderReviewedList(plugins) {
    const container = document.getElementById('reviewedList');
    container.innerHTML = '';

    plugins.forEach(p => {
        const card = document.createElement('div');
        card.className = 'plugin-card';
        card.id = 'reviewed-' + p.id;
        card.innerHTML = `
            <div class="plugin-card-header">
                <div class="plugin-name">
                    ${escapeHtml(p.name)}
                    ${p.version ? '<span class="plugin-version">v' + escapeHtml(p.version) + '</span>' : ''}
                </div>
                <div class="plugin-actions">
                    ${p.market_status == 2
                        ? '<span class="review-badge approved">已通过</span>'
                        : '<span class="review-badge rejected">已拒绝</span>'
                    }
                </div>
            </div>
            <div class="plugin-card-body">
                <div class="plugin-meta">
                    ${p.author ? '<span class="plugin-author">👤 ' + escapeHtml(p.author) + '</span>' : ''}
                    <span class="plugin-owner">📝 ${escapeHtml(p.publisher_name || p.username || '未知用户')}</span>
                    ${p.updated_at ? '<span class="plugin-time">🕐 ' + escapeHtml(p.updated_at) + '</span>' : ''}
                </div>
                ${p.desc ? '<div class="plugin-desc">' + nl2br(escapeHtml(p.desc)) + '</div>' : ''}
            </div>
            <div class="plugin-card-footer review-footer">
                <button class="btn btn-danger btn-sm" onclick="deletePlugin(${p.id})">
                    <i class="fas fa-trash"></i> 删除
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

// ===== 删除插件 =====
function deletePlugin(id) {
    if (!confirm('确定要删除此插件吗？此操作不可恢复，将同时删除插件文件和数据库记录！')) return;

    const card = document.getElementById('reviewed-' + id);
    const btn = card ? card.querySelector('.btn-danger') : null;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 删除中...'; }

    fetch('../api/plugin_market.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || '删除成功', 'success');
            loadReviewedList();
        } else {
            showToast(data.message || '删除失败', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> 删除'; }
        }
    })
    .catch(err => {
        showToast('请求失败: ' + err.message, 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> 删除'; }
    });
}

// ===== 审核操作 =====
function approvePlugin(id) {
    if (!confirm('确定要审核通过此插件吗？')) return;
    doReview(id, 'approve');
}

function rejectPlugin(id) {
    if (!confirm('确定要拒绝此插件吗？')) return;
    doReview(id, 'reject');
}

function doReview(id, action) {
    const btn = document.querySelector('#review-' + id + ' .btn-success, #review-' + id + ' .btn-danger');
    if (btn) { btn.disabled = true; btn.textContent = '处理中...'; }

    fetch('../api/plugin_market.php?action=' + action, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || '操作成功', 'success');
            loadPendingList();
        } else {
            showToast(data.message || '操作失败', 'error');
            if (btn) { btn.disabled = false; btn.textContent = action === 'approve' ? '通过' : '拒绝'; }
        }
    })
    .catch(err => {
        showToast('请求失败: ' + err.message, 'error');
        if (btn) { btn.disabled = false; btn.textContent = action === 'approve' ? '通过' : '拒绝'; }
    });
}

// ===== 标签切换 =====
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.tab-btn[data-tab="' + tab + '"]').classList.add('active');

    document.getElementById('pendingPanel').style.display = tab === 'pending' ? 'block' : 'none';
    document.getElementById('reviewedPanel').style.display = tab === 'reviewed' ? 'block' : 'none';

    if (tab === 'reviewed') {
        loadReviewedList();
    }
}

// ===== Toast 通知（使用全局 showToast）=====
function showToast(message, type) {
    if (window.showToast && window.showToast !== showToast) {
        window.showToast(message, type);
        return;
    }
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + (type || 'info');

    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    toast.innerHTML = '<span>' + icon + ' ' + escapeHtml(message) + '</span>';

    container.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
}

// ===== 工具函数 =====
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function nl2br(str) {
    if (!str) return '';
    return str.replace(/\n/g, '<br>');
}

// ===== 初始化 =====
document.addEventListener('DOMContentLoaded', function() {
    loadPendingList();
});
</script>

<style>
/* 审核标签页切换 */
.review-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
    background: #fff;
    border-radius: 10px 10px 0 0;
    overflow: hidden;
}
.review-tabs .tab-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
}
.review-tabs .tab-btn:hover {
    background: #f8fafc;
    color: #4338ca;
}
.review-tabs .tab-btn.active {
    color: #4338ca;
    border-bottom-color: #6366f1;
    background: #f8faff;
}

/* 审核面板 */
.review-panel {
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 审核列表 */
.review-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* 审核卡片 - 复用 plugins.php 卡片样式 */
.review-list .plugin-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
}
.review-list .plugin-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #6366f1;
}

.review-list .plugin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.review-list .plugin-name {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.review-list .plugin-version {
    display: inline-block;
    background: #e0e7ff;
    color: #4338ca;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 6px;
}
.review-list .plugin-actions {
    display: flex;
    gap: 4px;
    align-items: center;
    flex-shrink: 0;
}

.review-list .plugin-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    color: #64748b;
}
.review-list .plugin-author,
.review-list .plugin-owner,
.review-list .plugin-time {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 4px;
}
.review-list .plugin-desc {
    font-size: 13px;
    color: #475569;
    margin-bottom: 8px;
    line-height: 1.5;
}

.review-list .plugin-card-footer {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    gap: 8px;
}

/* 审核徽章 */
.review-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
.review-badge.pending {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.review-badge.approved {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.review-badge.rejected {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* 审核操作按钮 */
.review-footer .btn-success {
    background: #10b981;
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.review-footer .btn-success:hover {
    background: #059669;
}
.review-footer .btn-success:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.review-footer .btn-danger {
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.review-footer .btn-danger:hover {
    background: #dc2626;
}
.review-footer .btn-danger:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

/* 空状态 */
.review-list .empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
}
.review-list .empty-icon {
    font-size: 48px;
    margin-bottom: 12px;
    color: #94a3b8;
}
.review-list .empty-state h3 {
    color: #334155;
    margin-bottom: 8px;
}
.review-list .empty-state p {
    color: #64748b;
    margin-bottom: 16px;
}

/* 响应式 */
@media (max-width: 768px) {
    .review-tabs .tab-btn {
        font-size: 13px;
        padding: 10px 12px;
    }
    .review-list .plugin-card-header {
        flex-direction: column;
        gap: 8px;
    }
    .review-list .plugin-meta {
        flex-direction: column;
        gap: 4px;
    }
}
</style>

<?php require __DIR__ . '/../includes/load_footer.php'; ?>
