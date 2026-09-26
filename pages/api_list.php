<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = 'API列表';
$activePage = 'api_list';
$userId = $_SESSION['user_id'];

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = db()->prepare("DELETE FROM apis WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    header('Location: api_list.php?msg=删除成功');
    exit;
}

$categoryId = (int)(get('category_id', 0));
$botId = (int)(get('bot_id', 0));
$search = get('search', '');

$where = "a.user_id = ?";
$params = [$userId];
if ($categoryId) { $where .= " AND a.category_id = ?"; $params[] = $categoryId; }
if ($botId) { $where .= " AND a.bot_id = ?"; $params[] = $botId; }
if ($search) { $where .= " AND (a.name LIKE ? OR a.command LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = db()->prepare("SELECT COUNT(*) as count FROM apis a WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['count'];

$pageInfo = pagination($total, max(1, (int)get('page', 1)), 12);
$offset = $pageInfo['offset'];

$stmt = db()->prepare("SELECT a.*, c.name as category_name, c.icon as category_icon, c.color as category_color, b.name as bot_name FROM apis a LEFT JOIN api_categories c ON a.category_id=c.id LEFT JOIN bots b ON a.bot_id=b.id WHERE $where ORDER BY a.created_at DESC LIMIT {$pageInfo['perPage']} OFFSET {$offset}");
$stmt->execute($params);
$apis = $stmt->fetchAll();

$categories = getCategories($userId);
$bots = getBotList($userId);

require '../includes/load_header.php';
?>

<style>
.api-card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.api-card{background:var(--bg-card);border-radius:var(--radius-md);border:1px solid var(--border);padding:20px 22px;transition:all var(--transition-base);position:relative;box-shadow:var(--shadow-xs)}
.api-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:var(--primary-light)}
.api-card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.api-card-title{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.api-card-title h4{font-size:15px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0}
.api-card-actions{display:flex;gap:4px;opacity:0;transition:opacity var(--transition-fast);flex-shrink:0}
.api-card:hover .api-card-actions{opacity:1}
.api-card-actions .btn-icon{width:30px;height:30px;border-radius:6px;border:1px solid var(--border);background:var(--bg-card);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);transition:all var(--transition-fast);font-size:12px}
.api-card-actions .btn-icon:hover{background:var(--danger);color:#fff;border-color:var(--danger)}
.api-card-actions .btn-icon.edit:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.method-badge{padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.method-badge.get{background:#dbeafe;color:#1d4ed8}
.method-badge.post{background:#d1fae5;color:#065f46}
.method-badge.put{background:#fef3c7;color:#92400e}
.method-badge.delete{background:#fee2e2;color:#991b1b}
.api-card-url{font-size:12px;color:var(--text-muted);font-family:monospace;word-break:break-all;margin-bottom:10px;line-height:1.5;display:flex;align-items:center;gap:6px}
.api-card-url i{color:var(--primary);font-size:12px}
.api-card-tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.api-card-footer{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--border-light);font-size:12px;color:var(--text-muted)}
.api-card-footer .meta{display:flex;gap:12px;align-items:center}
.api-card-footer .meta span{display:flex;align-items:center;gap:4px}
.api-card-footer .detail-link{color:var(--primary);text-decoration:none;font-size:12px;font-weight:500;display:flex;align-items:center;gap:4px}
.api-card-footer .detail-link:hover{text-decoration:underline}
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>API 列表 <small style="color:var(--text-muted);font-weight:400;">(共 <?php echo $total; ?> 个)</small></h3>
        <div class="flex-between gap-2">
            <a href="api_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> 添加API</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($msg = get('msg')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="filter-bar">
            <select class="form-control" onchange="location.href='?category_id='+this.value+'&bot_id=<?php echo $botId; ?>&search=<?php echo urlencode($search); ?>'">
                <option value="">全部分类</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-control" onchange="location.href='?bot_id='+this.value+'&category_id=<?php echo $categoryId; ?>&search=<?php echo urlencode($search); ?>'">
                <option value="">全部机器人</option>
                <?php foreach ($bots as $bot): ?>
                    <option value="<?php echo $bot['id']; ?>" <?php echo $botId == $bot['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bot['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="search-box" style="flex:1;min-width:200px;">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="搜索 API 名称或指令..." value="<?php echo htmlspecialchars($search); ?>"
                       onkeydown="if(event.key==='Enter')location.href='?search='+this.value+'&category_id=<?php echo $categoryId; ?>&bot_id=<?php echo $botId; ?>'">
            </div>
        </div>

        <?php if (empty($apis)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                <p>暂无API</p>
                <span class="subtext">快去添加你的第一个API吧</span>
            </div>
        <?php else: ?>
            <div class="api-card-grid">
                <?php foreach ($apis as $api): ?>
                <div class="api-card">
                    <div class="api-card-top">
                        <div class="api-card-title">
                            <span class="method-badge <?php echo strtolower($api['method']); ?>"><?php echo $api['method']; ?></span>
                            <h4><?php echo htmlspecialchars($api['name']); ?></h4>
                        </div>
                        <div class="api-card-actions">
                            <a href="api_add.php?edit=<?php echo $api['id']; ?>" class="btn-icon edit" title="编辑"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?php echo $api['id']; ?>" class="btn-icon" onclick="return confirm('确定删除此API吗？')" title="删除"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                    <div class="api-card-url">
                        <i class="fas fa-terminal"></i> <?php echo htmlspecialchars($api['command']); ?>
                    </div>
                    <div class="api-card-url" style="font-size:11px;">
                        <i class="fas fa-link"></i> <?php echo htmlspecialchars($api['api_url']); ?>
                    </div>
                    <div class="api-card-tags">
                        <span class="tag tag-purple"><?php echo htmlspecialchars($api['category_name'] ?? '未分类'); ?></span>
                        <span class="tag tag-green"><?php echo $api['send_type']; ?></span>
                    </div>
                    <div class="api-card-footer">
                        <div class="meta">
                            <span><i class="fas fa-robot"></i> <?php echo htmlspecialchars($api['bot_name'] ?? '全部'); ?></span>
                            <span><i class="far fa-clock"></i> <?php echo date('m-d', strtotime($api['created_at'])); ?></span>
                        </div>
                        <a href="api_add.php?edit=<?php echo $api['id']; ?>" class="detail-link">详情 <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pageInfo['totalPages'] > 1): ?>
                <div class="pagination">
                    <?php if ($pageInfo['page'] > 1): ?>
                        <a href="?page=<?php echo $pageInfo['page']-1; ?>&category_id=<?php echo $categoryId; ?>&bot_id=<?php echo $botId; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i=max(1,$pageInfo['page']-2); $i<=min($pageInfo['totalPages'], $pageInfo['page']+2); $i++): ?>
                        <?php if ($i == $pageInfo['page']): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&category_id=<?php echo $categoryId; ?>&bot_id=<?php echo $botId; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($pageInfo['page'] < $pageInfo['totalPages']): ?>
                        <a href="?page=<?php echo $pageInfo['page']+1; ?>&category_id=<?php echo $categoryId; ?>&bot_id=<?php echo $botId; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require '../includes/load_footer.php'; ?>
