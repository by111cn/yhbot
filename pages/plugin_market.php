<?php
/**
 * BotAPI 插件市场
 * 浏览和发现社区共享的插件
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = '插件市场';
$activePage = 'plugin_market';
?>
<?php include __DIR__ . '/../includes/load_header.php'; ?>

    <!-- 插件市场页面 -->
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-store"></i> 插件市场</h2>
        <p class="page-desc">发现并安装社区共享的插件</p>
    </div>

    <!-- 搜索栏 -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" class="form-control" id="searchKeyword" placeholder="搜索插件名称、说明或作者..." oninput="searchPlugins()">
        </div>
        <button class="btn btn-primary" onclick="searchPlugins()">
            <i class="fas fa-search"></i> 搜索
        </button>
    </div>

    <!-- 加载状态 -->
    <div class="market-loading" id="marketLoading">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <span>正在加载插件列表...</span>
        </div>
    </div>

    <!-- 插件网格 -->
    <div class="market-grid" id="marketGrid"></div>

    <!-- 分页 -->
    <div class="pagination" id="marketPagination"></div>

<script>
// ===== 插件市场数据 =====
let currentPage = 1;
let totalPages = 1;
let myPlugins = {};

// ===== 搜索防抖 =====
let searchTimer = null;
function searchPlugins() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentPage = 1;
        loadPlugins();
    }, 300);
}

// ===== 加载插件列表 =====
function loadPlugins() {
    const keyword = document.getElementById('searchKeyword').value.trim();
    const loading = document.getElementById('marketLoading');
    const grid = document.getElementById('marketGrid');
    const pagination = document.getElementById('marketPagination');

    loading.style.display = 'flex';
    grid.innerHTML = '';
    pagination.innerHTML = '';

    const params = new URLSearchParams();
    params.set('action', 'list');
    params.set('page', currentPage);
    if (keyword) {
        params.set('keyword', keyword);
    }

    Promise.all([
        fetch('../api/plugin_market.php?' + params.toString()).then(r => r.json()),
        fetch('../api/plugin.php?action=list').then(r => r.json())
    ])
    .then(([marketData, myData]) => {
        loading.style.display = 'none';

        if (myData && myData.success && myData.data) {
            myPlugins = {};
            myData.data.forEach(p => {
                if (p.name) {
                    myPlugins[p.name] = p;
                }
            });
        }

        if (marketData.success) {
            renderPlugins(marketData.data, marketData.total, marketData.page, marketData.pages);
        } else {
            grid.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-circle"></i></div><p>加载失败</p><p class="subtext">' + (marketData.message || '未知错误') + '</p></div>';
        }
    })
    .catch(err => {
        loading.style.display = 'none';
        grid.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-circle"></i></div><p>网络错误</p><p class="subtext">' + err.message + '</p></div>';
    });
}

// ===== 渲染插件卡片 =====
function renderPlugins(plugins, total, page, pages) {
    const grid = document.getElementById('marketGrid');
    const pagination = document.getElementById('marketPagination');
    totalPages = pages;

    if (!plugins || plugins.length === 0) {
        const keyword = document.getElementById('searchKeyword').value.trim();
        if (keyword) {
            grid.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-search-minus"></i></div><p>没有找到匹配的插件</p><p class="subtext">尝试使用其他关键词搜索</p></div>';
        } else {
            grid.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-box-open"></i></div><p>还没有插件上架</p><p class="subtext">等待开发者们分享他们的作品</p></div>';
        }
        pagination.innerHTML = '';
        return;
    }

    let html = '';
    plugins.forEach(p => {
        let commands = [];
        try { commands = JSON.parse(p.command_list || '[]'); } catch(e) { commands = []; }

        const installed = myPlugins[p.name];
        const hasUpdate = installed && installed.version && p.version && installed.version !== p.version;
        const isInstalled = !!installed;

        let btnHtml = '';
        if (hasUpdate) {
            btnHtml = '<button class="btn btn-sm btn-warning" onclick="installPlugin(this, ' + p.id + ', true)"><i class="fas fa-sync-alt"></i> 更新到 v' + escapeHtml(p.version) + '</button>';
        } else if (isInstalled) {
            btnHtml = '<button class="btn btn-sm btn-success" disabled><i class="fas fa-check"></i> 已安装</button>';
        } else {
            btnHtml = '<button class="btn btn-sm btn-primary" onclick="installPlugin(this, ' + p.id + ', false)"><i class="fas fa-download"></i> 一键安装</button>';
        }

        let versionBadge = '';
        if (hasUpdate) {
            versionBadge = '    <span class="market-card-version has-update">v' + escapeHtml(p.version) + ' <i class="fas fa-arrow-up"></i></span>';
        } else if (p.version) {
            versionBadge = '    <span class="market-card-version">v' + escapeHtml(p.version) + '</span>';
        }

        html += '<div class="market-card">';
        html += '  <div class="market-card-header">';
        html += '    <div class="market-card-name">' + escapeHtml(p.name || '未命名插件') + '</div>';
        html += versionBadge;
        html += '  </div>';
        html += '  <div class="market-card-body">';
        html += '    <div class="market-card-meta">';
        if (p.author) {
            html += '      <span class="market-meta-item"><i class="fas fa-user"></i> ' + escapeHtml(p.author) + '</span>';
        }
        if (p.publisher_name) {
            html += '      <span class="market-meta-item"><i class="fas fa-user-check"></i> ' + escapeHtml(p.publisher_name) + '</span>';
        }
        if (hasUpdate) {
            html += '      <span class="market-meta-item update-hint"><i class="fas fa-tag"></i> 本地 v' + escapeHtml(installed.version) + '</span>';
        }
        html += '    </div>';
        if (p.desc) {
            html += '    <div class="market-card-desc">' + escapeHtml(p.desc) + '</div>';
        }
        if (commands.length > 0) {
            html += '    <div class="market-card-commands">';
            html += '      <small class="text-muted"><i class="fas fa-terminal"></i> 命令：</small>';
            html += '      <div class="market-command-tags">';
            commands.forEach(cmd => {
                html += '        <span class="market-command-tag">' + escapeHtml(cmd) + '</span>';
            });
            html += '      </div>';
            html += '    </div>';
        }
        html += '  </div>';
        html += '  <div class="market-card-footer">';
        html += '    <small class="text-muted"><i class="far fa-calendar-alt"></i> ' + escapeHtml(p.created_at || '') + '</small>';
        html += btnHtml;
        html += '  </div>';
        html += '</div>';
    });
    grid.innerHTML = html;

    // 渲染分页
    renderPagination(pagination, page, pages);
}

// ===== 渲染分页 =====
function renderPagination(container, current, pages) {
    if (pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // 上一页
    if (current > 1) {
        html += '<a href="javascript:void(0)" onclick="goToPage(' + (current - 1) + ')"><i class="fas fa-chevron-left"></i></a>';
    } else {
        html += '<span class="disabled"><i class="fas fa-chevron-left"></i></span>';
    }

    // 页码
    const start = Math.max(1, current - 2);
    const end = Math.min(pages, current + 2);

    if (start > 1) {
        html += '<a href="javascript:void(0)" onclick="goToPage(1)">1</a>';
        if (start > 2) {
            html += '<span class="disabled">...</span>';
        }
    }

    for (let i = start; i <= end; i++) {
        if (i === current) {
            html += '<span class="active">' + i + '</span>';
        } else {
            html += '<a href="javascript:void(0)" onclick="goToPage(' + i + ')">' + i + '</a>';
        }
    }

    if (end < pages) {
        if (end < pages - 1) {
            html += '<span class="disabled">...</span>';
        }
        html += '<a href="javascript:void(0)" onclick="goToPage(' + pages + ')">' + pages + '</a>';
    }

    // 下一页
    if (current < pages) {
        html += '<a href="javascript:void(0)" onclick="goToPage(' + (current + 1) + ')"><i class="fas fa-chevron-right"></i></a>';
    } else {
        html += '<span class="disabled"><i class="fas fa-chevron-right"></i></span>';
    }

    container.innerHTML = html;
}

// ===== 切换页码 =====
function goToPage(page) {
    if (page < 1 || page > totalPages || page === currentPage) return;
    currentPage = page;
    loadPlugins();

    // 滚动到顶部
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===== 一键安装/更新插件 =====
function installPlugin(btn, pluginId, isUpdate) {
    const msg = isUpdate ? '确定要更新此插件到最新版本吗？' : '确定要安装此插件到你的插件管理吗？';
    if (!confirm(msg)) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isUpdate ? '更新中...' : '安装中...');

    fetch('../api/plugin_market.php?action=install', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(pluginId)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            alert(data.message || (isUpdate ? '更新成功' : '安装成功'));
            loadPlugins();
        } else {
            btn.innerHTML = isUpdate ? '<i class="fas fa-sync-alt"></i> 更新' : '<i class="fas fa-download"></i> 一键安装';
            alert('操作失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = isUpdate ? '<i class="fas fa-sync-alt"></i> 更新' : '<i class="fas fa-download"></i> 一键安装';
        alert('请求失败: ' + err.message);
    });
}

// ===== HTML 转义 =====
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== 页面加载完成后自动加载 =====
document.addEventListener('DOMContentLoaded', function() {
    loadPlugins();

    // 回车触发搜索
    document.getElementById('searchKeyword').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            clearTimeout(searchTimer);
            currentPage = 1;
            loadPlugins();
        }
    });
});
</script>

<style>
/* 插件市场专用样式 */
.market-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 60px 20px;
}
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    color: var(--text-muted);
    font-size: 14px;
}
.loading-spinner i {
    font-size: 32px;
    color: var(--primary);
}

.market-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 18px;
    margin-top: 8px;
}

.market-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    transition: all var(--transition-spring);
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.market-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--info), var(--accent));
    opacity: 0;
    transition: opacity var(--transition-base);
}
.market-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}
.market-card:hover::before {
    opacity: 1;
}

.market-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.market-card-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    flex: 1;
    line-height: 1.3;
}
.market-card-version {
    display: inline-block;
    background: linear-gradient(135deg, var(--primary-bg), rgba(192, 132, 252, 0.12));
    color: var(--primary-dark);
    padding: 2px 10px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 700;
    margin-left: 8px;
    flex-shrink: 0;
    border: 1px solid rgba(244, 114, 182, 0.15);
}
.market-card-version.has-update {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.3);
    animation: pulseUpdate 2s infinite;
}
@keyframes pulseUpdate {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
    50% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
}
.update-hint {
    background: #fef3c7 !important;
    color: #92400e !important;
}
html.dark .update-hint {
    background: rgba(245, 158, 11, 0.15) !important;
    color: #fcd34d !important;
}

.market-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.market-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 13px;
}
.market-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-secondary);
    background: var(--bg);
    padding: 2px 10px;
    border-radius: var(--radius-full);
    font-size: 12px;
}
.market-meta-item i {
    color: var(--text-muted);
    font-size: 11px;
}

.market-card-desc {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 10px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.market-card-commands {
    margin-top: auto;
    margin-bottom: 4px;
}
.market-command-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
}
.market-command-tag {
    background: linear-gradient(135deg, rgba(52, 211, 153, 0.08), rgba(16, 185, 129, 0.08));
    color: #065f46;
    border: 1px solid rgba(52, 211, 153, 0.2);
    padding: 2px 8px;
    border-radius: var(--radius-xs);
    font-size: 11px;
    font-family: var(--font-mono, monospace);
}
html.dark .market-command-tag {
    background: linear-gradient(135deg, rgba(52, 211, 153, 0.12), rgba(16, 185, 129, 0.08));
    color: #a7f3d0;
    border-color: rgba(52, 211, 153, 0.2);
}

.market-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--border-light);
}
.market-card-footer small {
    font-size: 11px;
}

/* 空状态覆盖 */
.market-grid .empty-state {
    grid-column: 1 / -1;
}

/* 搜索栏增强 */
.filter-bar .search-box {
    flex: 1;
    max-width: 420px;
}

/* 响应式 */
@media (max-width: 768px) {
    .market-grid {
        grid-template-columns: 1fr;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        max-width: 100%;
    }
    .filter-bar .btn {
        width: 100%;
    }
    .market-card-footer {
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
    }
    .market-card-footer .btn {
        width: 100%;
    }
}
</style>

<?php require __DIR__ . '/../includes/load_footer.php'; ?>
