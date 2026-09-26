<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = 'API分享';
$activePage = 'api_share';
$userId = $_SESSION['user_id'];

$stmt = db()->prepare("SELECT a.*, c.name as category_name FROM apis a LEFT JOIN api_categories c ON a.category_id=c.id WHERE a.user_id=? ORDER BY a.created_at DESC");
$stmt->execute([$userId]);
$myApis = $stmt->fetchAll();

$stmt = db()->prepare("SELECT m.*, b.name as bot_name FROM menus m LEFT JOIN bots b ON m.bot_id=b.id WHERE m.user_id=? ORDER BY m.created_at DESC");
$stmt->execute([$userId]);
$myMenus = $stmt->fetchAll();

require '../includes/load_header.php';
?>

<style>
.share-tabs{display:flex;gap:8px;border-bottom:2px solid var(--border);margin-bottom:24px}
.share-tab{padding:12px 24px;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;font-weight:600;color:var(--text-muted);font-size:14px;transition:all var(--transition-fast);border-radius:8px 8px 0 0;display:flex;align-items:center;gap:8px}
.share-tab:hover{color:var(--primary);background:rgba(99,102,241,.04)}
.share-tab.active{color:var(--primary);border-bottom-color:var(--primary);background:rgba(99,102,241,.06)}
.share-pane{display:none;animation:fadeSlideIn .25s ease}
.share-pane.active{display:block}
.share-section{background:var(--bg);border-radius:var(--radius-md);border:1px solid var(--border-light);padding:24px;margin-bottom:20px}
.share-section h4{font-size:15px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.share-section h4 i{color:var(--primary);font-size:17px}
.share-card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:16px}
.share-card{background:var(--bg-card);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:16px;cursor:pointer;transition:all var(--transition-fast);display:flex;align-items:center;gap:12px;position:relative;box-shadow:var(--shadow-xs)}
.share-card:hover{border-color:var(--primary-light);box-shadow:var(--shadow-md)}
.share-card.selected{border-color:var(--primary);background:rgba(99,102,241,.04);box-shadow:0 0 0 2px rgba(99,102,241,.1)}
.share-card .check{position:absolute;top:10px;right:10px;width:22px;height:22px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:10px;color:#fff;transition:all var(--transition-fast)}
.share-card.selected .check{background:var(--primary);border-color:var(--primary)}
.share-card .check i{opacity:0;font-size:10px}
.share-card.selected .check i{opacity:1}
.share-card .sc-icon{width:40px;height:40px;border-radius:var(--radius-xs);background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.share-card .sc-info{flex:1;min-width:0}
.share-card .sc-info h5{font-size:14px;font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.share-card .sc-info p{font-size:12px;color:var(--text-muted);margin:0}
.share-counter{display:flex;gap:24px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)}
.share-counter .sc-item{text-align:center;flex:1;background:var(--bg);border-radius:var(--radius-xs);padding:16px}
.share-counter .sc-item h5{font-size:28px;font-weight:700;color:var(--primary);margin-bottom:4px}
.share-counter .sc-item p{font-size:13px;color:var(--text-muted)}
.share-result{display:flex;align-items:center;gap:12px;padding:16px;background:var(--bg);border-radius:var(--radius-xs);border:1px solid var(--border);margin-top:16px;animation:fadeSlideIn .3s ease}
.share-result input{flex:1;background:var(--bg-card);border:1.5px solid var(--border);border-radius:6px;padding:10px 14px;font-family:monospace;font-size:13px;outline:none}
.share-result input:focus{border-color:var(--primary)}
.import-box{display:flex;gap:12px;align-items:center}
.import-box input{flex:1;background:var(--bg-card);border:1.5px solid var(--border);border-radius:6px;padding:11px 16px;font-size:14px;outline:none}
.import-box input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
</style>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span>API分享</h3>
    </div>
    <div class="card-body">
        <div class="share-tabs">
            <div class="share-tab active" onclick="switchShareTab('share')">
                <i class="fas fa-share-alt"></i> 生成分享
            </div>
            <div class="share-tab" onclick="switchShareTab('import')">
                <i class="fas fa-download"></i> 导入分享
            </div>
        </div>

        <div class="share-pane active" id="tab-share">
            <div class="share-section">
                <h4><i class="fas fa-code"></i> 选择要分享的 API</h4>
                <div class="share-card-grid" id="apiShareGrid">
                    <?php foreach ($myApis as $api): ?>
                        <div class="share-card" data-id="<?php echo $api['id']; ?>" data-type="api" onclick="toggleShareCard(this)">
                            <div class="check"><i class="fas fa-check"></i></div>
                            <div class="sc-icon"><i class="fas fa-code"></i></div>
                            <div class="sc-info">
                                <h5><?php echo htmlspecialchars($api['name']); ?></h5>
                                <p><?php echo htmlspecialchars($api['category_name'] ?? '未分类'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($myApis)): ?>
                        <div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:40px;">
                            <i class="fas fa-inbox" style="font-size:36px;margin-bottom:12px;opacity:.3"></i>
                            <p>暂无API，请先添加API</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="share-section">
                <h4><i class="fas fa-layer-group"></i> 选择要分享的菜单</h4>
                <div class="share-card-grid" id="menuShareGrid">
                    <?php foreach ($myMenus as $menu): ?>
                        <div class="share-card" data-id="<?php echo $menu['id']; ?>" data-type="menu" onclick="toggleShareCard(this)">
                            <div class="check"><i class="fas fa-check"></i></div>
                            <div class="sc-icon"><i class="fas fa-list"></i></div>
                            <div class="sc-info">
                                <h5><?php echo htmlspecialchars($menu['name']); ?></h5>
                                <p><?php echo htmlspecialchars($menu['bot_name'] ?? '未指定'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($myMenus)): ?>
                        <div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:40px;">
                            <i class="fas fa-inbox" style="font-size:36px;margin-bottom:12px;opacity:.3"></i>
                            <p>暂无菜单</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="share-counter">
                <div class="sc-item">
                    <h5 id="selectedApiCount">0</h5>
                    <p>已选API</p>
                </div>
                <div class="sc-item">
                    <h5 id="selectedMenuCount">0</h5>
                    <p>已选菜单</p>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary btn-lg" onclick="generateShareCode()" style="width:100%;">
                    <i class="fas fa-magic"></i> 生成分享码
                </button>
            </div>

            <div class="share-result" id="shareResult" style="display:none;">
                <i class="fas fa-check-circle" style="color:var(--success);font-size:20px;"></i>
                <input type="text" id="shareCodeInput" readonly value="" placeholder="分享码将在此显示...">
                <button class="btn btn-primary" onclick="copyShareCode()">
                    <i class="fas fa-copy"></i> 复制
                </button>
            </div>
        </div>

        <div class="share-pane" id="tab-import">
            <div class="share-section">
                <h4><i class="fas fa-download"></i> 导入分享</h4>
                <p style="color:var(--text-muted);margin-bottom:18px;font-size:14px;">
                    <i class="fas fa-info-circle" style="margin-right:6px;color:var(--info);"></i>
                    输入其他用户生成的分享码，即可导入他们的API和菜单配置
                </p>
                <div class="import-box">
                    <input type="text" id="importCodeInput" placeholder="请粘贴分享码...">
                    <button class="btn btn-primary btn-lg" onclick="importShareCode()">
                        <i class="fas fa-download"></i> 导入
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchShareTab(tab) {
    document.querySelectorAll('.share-tab').forEach(function(t){t.classList.remove('active')});
    document.querySelectorAll('.share-pane').forEach(function(p){p.classList.remove('active')});
    event.target.closest('.share-tab').classList.add('active');
    document.getElementById('tab-'+tab).classList.add('active');
}
function toggleShareCard(el){el.classList.toggle('selected');updateCount();}
function updateCount(){
    document.getElementById('selectedApiCount').textContent=document.querySelectorAll('#apiShareGrid .share-card.selected').length;
    document.getElementById('selectedMenuCount').textContent=document.querySelectorAll('#menuShareGrid .share-card.selected').length;
}
function generateShareCode(){
    var apis=[],menus=[];
    document.querySelectorAll('#apiShareGrid .share-card.selected').forEach(function(el){apis.push(parseInt(el.dataset.id));});
    document.querySelectorAll('#menuShareGrid .share-card.selected').forEach(function(el){menus.push(parseInt(el.dataset.id));});
    if(apis.length===0&&menus.length===0){showToast('请至少选择一个API或菜单','warning');return;}

    var btn=event.target;
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> 正在生成...';

    fetch('../api/share.php?action=generate',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'data='+encodeURIComponent(JSON.stringify({apis:apis,menus:menus}))
    })
    .then(function(r){return r.json();})
    .then(function(res){
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-magic"></i> 生成分享码';
        if(res.code===0){
            document.getElementById('shareCodeInput').value=res.data.code;
            document.getElementById('shareResult').style.display='flex';
            showToast('分享码已生成','success');
        }else{
            showToast(res.msg||'生成失败','error');
        }
    })
    .catch(function(e){
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-magic"></i> 生成分享码';
        showToast('网络错误：'+e.message,'error');
    });
}
function copyShareCode(){var i=document.getElementById('shareCodeInput');i.select();document.execCommand('copy');showToast('分享码已复制','success');}
function importShareCode(){
    var code=document.getElementById('importCodeInput').value.trim();
    if(!code){showToast('请输入分享码','warning');return;}
    if(!/^[a-zA-Z0-9]{12}$/.test(code)){showToast('分享码格式不正确（12位字母数字）','warning');return;}

    var btn=event.target;
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> 正在导入...';

    fetch('../api/share.php?action=query',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'code='+encodeURIComponent(code)
    })
    .then(function(r){return r.json();})
    .then(function(res){
        if(res.code!==0){
            btn.disabled=false;
            btn.innerHTML='<i class="fas fa-download"></i> 导入';
            showToast(res.msg||'查询失败','error');
            return;
        }
        var d=res.data.data;
        var msg='找到分享：API '+(d.apis?d.apis.length:0)+'个，菜单 '+(d.menus?d.menus.length:0)+'个'+'\n分享者：'+(d.sharer||'未知')+'\n分享时间：'+(d.time||'未知')+'\n\n确认导入吗？（同名项将被跳过）';
        if(!confirm(msg)){btn.disabled=false;btn.innerHTML='<i class="fas fa-download"></i> 导入';return;}

        // 确认后执行导入
        btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> 导入中...';
        return fetch('../api/share.php?action=import',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'code='+encodeURIComponent(code)
        });
    })
    .then(function(r){return r?r.json():null;})
    .then(function(res2){
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-download"></i> 导入';
        if(res2&&res2.code===0){
            showToast(res2.msg,'success');
            document.getElementById('importCodeInput').value='';
        }else if(res2){
            showToast(res2.msg||'导入失败','error');
        }
    })
    .catch(function(e){
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-download"></i> 导入';
        showToast('网络错误：'+e.message,'error');
    });
}
</script>

<?php require '../includes/load_footer.php'; ?>
