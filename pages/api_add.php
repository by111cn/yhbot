<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$pageTitle = '添加API';
$activePage = 'api_add';
$userId = $_SESSION['user_id'];
$categories = getCategories($userId);
$bots = getBotList($userId);

$editApi = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = db()->prepare("SELECT * FROM apis WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $editApi = $stmt->fetch();
    if ($editApi) $pageTitle = '编辑API';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $command = trim($_POST['command'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $apiUrl = trim($_POST['api_url'] ?? '');
    $method = $_POST['method'] ?? 'GET';
    $sendType = $_POST['send_type'] ?? 'text';
    $paramSplit = trim($_POST['param_split'] ?? ' ');
    $triggerMode = $_POST['trigger_mode'] ?? 'exact';
    $preContent = trim($_POST['pre_content'] ?? '');
    $respTpl = trim($_POST['response_template'] ?? '');
    $headers = trim($_POST['headers'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $timeout = (int)($_POST['timeout'] ?? 10);
    $cacheTime = (int)($_POST['cache_time'] ?? 0);
    $botId = (int)($_POST['bot_id'] ?? 0);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if (empty($name) || empty($command) || empty($apiUrl)) {
        $error = '请填写必填项';
    } elseif ($editApi) {
        $stmt = db()->prepare("UPDATE apis SET bot_id=?,category_id=?,name=?,command=?,description=?,api_url=?,method=?,send_type=?,param_split=?,trigger_mode=?,pre_content=?,response_template=?,headers=?,body=?,timeout=?,cache_time=?,is_public=? WHERE id=? AND user_id=?");
        $stmt->execute([$botId,$categoryId,$name,$command,$desc,$apiUrl,$method,$sendType,$paramSplit,$triggerMode,$preContent,$respTpl,$headers,$body,$timeout,$cacheTime,$isPublic,$editApi['id'],$userId]);
        header('Location: api_list.php?msg=更新成功');
        exit;
    } else {
        $stmt = db()->prepare("INSERT INTO apis (user_id,bot_id,category_id,name,command,description,api_url,method,send_type,param_split,trigger_mode,pre_content,response_template,headers,body,timeout,cache_time,is_public) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$userId,$botId,$categoryId,$name,$command,$desc,$apiUrl,$method,$sendType,$paramSplit,$triggerMode,$preContent,$respTpl,$headers,$body,$timeout,$cacheTime,$isPublic]);
        header('Location: api_list.php?msg=添加成功');
        exit;
    }
}

require '../includes/load_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><span class="icon-dot"></span><?php echo $editApi ? '编辑 API' : 'API 配置'; ?></h3>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo $editApi ? '?edit='.$editApi['id'] : ''; ?>">
            <?php echo csrfField(); ?>
            <div class="tabs">
                <div class="tab-item active" data-target="tab-basic"><i class="fas fa-sliders-h"></i> 基本配置</div>
                <div class="tab-item" data-target="tab-perm"><i class="fas fa-shield-alt"></i> 权限设置</div>
                <div class="tab-item" data-target="tab-advanced"><i class="fas fa-cog"></i> 高级配置</div>
            </div>

            <div class="tab-pane active" id="tab-basic">
                <div class="form-row">
                    <div class="form-group">
                        <label>所属分类 <span class="required">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">请选择分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($editApi && $editApi['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>功能指令 <span class="required">*</span></label>
                        <input type="text" name="command" class="form-control" placeholder="如: /天气" required
                               value="<?php echo $editApi ? htmlspecialchars($editApi['command']) : ''; ?>">
                        <span class="hint">用户通过此指令触发API调用</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>API名称 <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="API名称" required
                               value="<?php echo $editApi ? htmlspecialchars($editApi['name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>API地址 <span class="required">*</span></label>
                        <input type="text" name="api_url" class="form-control" placeholder="https://api.example.com/xxx" required
                               value="<?php echo $editApi ? htmlspecialchars($editApi['api_url']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>请求方式</label>
                        <select name="method" class="form-control">
                            <?php foreach (['GET','POST','PUT','DELETE'] as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($editApi && $editApi['method'] == $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>发送类型</label>
                        <select name="send_type" class="form-control">
                            <?php foreach (['text'=>'文本','markdown'=>'Markdown','html'=>'HTML','image'=>'图片','file'=>'文件','video'=>'视频'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo ($editApi && $editApi['send_type'] == $v) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>触发模式</label>
                        <select name="trigger_mode" class="form-control">
                            <?php foreach (['exact'=>'精准触发','fuzzy'=>'模糊触发','left'=>'左边触发','right'=>'右边触发'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo ($editApi && $editApi['trigger_mode'] == $v) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>参数分割符</label>
                        <input type="text" name="param_split" class="form-control" placeholder="默认为空格"
                               value="<?php echo $editApi ? htmlspecialchars($editApi['param_split']) : ' '; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>指令说明</label>
                    <textarea name="description" class="form-control" placeholder="详细描述该API的功能和用途"><?php echo $editApi ? htmlspecialchars($editApi['description']) : ''; ?></textarea>
                </div>
            </div>

            <div class="tab-pane" id="tab-perm">
                <div class="form-group">
                    <label>关联机器人</label>
                    <select name="bot_id" class="form-control">
                        <option value="0">全部机器人</option>
                        <?php foreach ($bots as $bot): ?>
                            <option value="<?php echo $bot['id']; ?>" <?php echo ($editApi && $editApi['bot_id'] == $bot['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bot['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_public" value="1" <?php echo ($editApi && $editApi['is_public']) ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                        <span>公开API（允许其他用户查看和使用）</span>
                    </label>
                </div>
            </div>

            <div class="tab-pane" id="tab-advanced">
                <div class="form-group">
                    <label>调用前发送内容</label>
                    <textarea name="pre_content" class="form-control" placeholder="如：正在查询中，请稍候..."><?php echo $editApi ? htmlspecialchars($editApi['pre_content']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>响应模板</label>
                    <textarea name="response_template" class="form-control" placeholder="配置发送给用户的内容，可用变量如 {result} 等"><?php echo $editApi ? htmlspecialchars($editApi['response_template']) : ''; ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>自定义Headers (JSON)</label>
                        <textarea name="headers" class="form-control" placeholder='{"Authorization": "Bearer xxx"}'><?php echo $editApi ? htmlspecialchars($editApi['headers']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>自定义Body (JSON)</label>
                        <textarea name="body" class="form-control" placeholder='{"key": "value"}'><?php echo $editApi ? htmlspecialchars($editApi['body']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>超时时间 (秒)</label>
                        <input type="number" name="timeout" class="form-control" value="<?php echo $editApi ? $editApi['timeout'] : '10'; ?>" min="1" max="60">
                    </div>
                    <div class="form-group">
                        <label>缓存时间 (秒)</label>
                        <input type="number" name="cache_time" class="form-control" value="<?php echo $editApi ? $editApi['cache_time'] : '0'; ?>" min="0">
                    </div>
                </div>
            </div>

            <!-- 可用变量参考 -->
            <div class="mt-6">
                <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:12px;">
                    <button type="button" id="btnToggleVars" class="btn btn-secondary">
                        <i class="fas fa-code"></i> 查看变量
                    </button>
                    <small style="color:#9ca3af;">点击变量即可复制到剪贴板，可在 API 地址、Headers、Body、响应模板中使用</small>
                </div>
                <div id="varsPanel" style="display:none;">
                    <div class="card" style="border-left:3px solid var(--primary); margin-bottom:0;">
                        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <h4 style="margin:0;"><i class="fas fa-tags"></i> 可用变量</h4>
                            <button type="button" id="btnCloseVars" class="btn btn-sm btn-secondary" style="padding:4px 10px;"><i class="fas fa-times"></i> 收起</button>
                        </div>
                        <div class="card-body" style="padding:16px;">
                            <div class="var-category" style="margin-bottom:16px;">
                                <h5 style="margin:0 0 10px 0; font-size:14px; color:var(--text-muted);">文本处理（用于截取/替换 API 返回内容）</h5>
                                <div class="var-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
                                    <div class="var-card" data-var="{文本取右#关键词}" title="点击复制"><div class="var-name">{文本取右#关键词}</div><div class="var-desc">取 API 返回内容中关键词右边的内容</div></div>
                                    <div class="var-card" data-var="{文本取中#前#后}" title="点击复制"><div class="var-name">{文本取中#前#后}</div><div class="var-desc">取 API 返回内容两关键词之间的内容</div></div>
                                    <div class="var-card" data-var="{文本取左#关键词}" title="点击复制"><div class="var-name">{文本取左#关键词}</div><div class="var-desc">取 API 返回内容中关键词左边的内容</div></div>
                                    <div class="var-card" data-var="{文本替换#原文本#新文本}" title="点击复制"><div class="var-name">{文本替换#原文本#新文本}</div><div class="var-desc">将 API 返回内容中的原文本替换为新文本</div></div>
                                    <div class="var-card" data-var="{换行}" title="点击复制"><div class="var-name">{换行}</div><div class="var-desc">在文本中插入换行符，用于多行消息</div></div>
                                </div>
                            </div>
                            <div class="var-category" style="margin-bottom:16px;">
                                <h5 style="margin:0 0 10px 0; font-size:14px; color:var(--text-muted);">常用信息</h5>
                                <div class="var-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
                                    <div class="var-card" data-var="{原始返回}" title="点击复制"><div class="var-name">{原始返回}</div><div class="var-desc">API 返回的原始内容（用于富媒体消息）</div></div>
                                    <div class="var-card" data-var="{参数}" title="点击复制"><div class="var-name">{参数}</div><div class="var-desc">用户消息中的首个参数（无参数时取去掉指令后的内容）</div></div>
                                    <div class="var-card" data-var="{全部参数}" title="点击复制"><div class="var-name">{全部参数}</div><div class="var-desc">所有参数以空格连接</div></div>
                                    <div class="var-card" data-var="{用户}" title="点击复制"><div class="var-name">{用户}</div><div class="var-desc">消息发送者的名称</div></div>
                                    <div class="var-card" data-var="{用户ID}" title="点击复制"><div class="var-name">{用户ID}</div><div class="var-desc">消息发送者的用户 ID，私聊和群聊都可用</div></div>
                                    <div class="var-card" data-var="{群ID}" title="点击复制"><div class="var-name">{群ID}</div><div class="var-desc">群聊 ID，仅在群聊消息中可用</div></div>
                                    <div class="var-card" data-var="{当前时间}" title="点击复制"><div class="var-name">{当前时间}</div><div class="var-desc">当前时间，格式：YYYY-MM-DD HH:mm:ss</div></div>
                                    <div class="var-card" data-var="{时间戳}" title="点击复制"><div class="var-name">{时间戳}</div><div class="var-desc">当前的时间戳</div></div>
                                    <div class="var-card" data-var="{自适应}" title="点击复制"><div class="var-name">{自适应}</div><div class="var-desc">获取图片尺寸，替换为 #宽度px #高度px 格式（需配合 markdown 图片语法使用）</div></div>
                                </div>
                            </div>
                            <div class="var-category" style="margin-bottom:0;">
                                <h5 style="margin:0 0 10px 0; font-size:14px; color:var(--text-muted);">API 地址与模板通用</h5>
                                <div class="var-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
                                    <div class="var-card" data-var="{0}" title="点击复制"><div class="var-name">{0} {1} {2} ...</div><div class="var-desc">用户传入的第 N 个参数</div></div>
                                    <div class="var-card" data-var="{user_id}" title="点击复制"><div class="var-name">{user_id}</div><div class="var-desc">发送者 ID</div></div>
                                    <div class="var-card" data-var="{chat_id}" title="点击复制"><div class="var-name">{chat_id}</div><div class="var-desc">聊天 ID</div></div>
                                    <div class="var-card" data-var="{bot_id}" title="点击复制"><div class="var-name">{bot_id}</div><div class="var-desc">机器人 ID</div></div>
                                    <div class="var-card" data-var="{user_name}" title="点击复制"><div class="var-name">{user_name}</div><div class="var-desc">用户名称</div></div>
                                    <div class="var-card" data-var="{bot_name}" title="点击复制"><div class="var-name">{bot_name}</div><div class="var-desc">机器人名称</div></div>
                                    <div class="var-card" data-var="{command}" title="点击复制"><div class="var-name">{command}</div><div class="var-desc">触发指令</div></div>
                                    <div class="var-card" data-var="{time}" title="点击复制"><div class="var-name">{time}</div><div class="var-desc">当前时间</div></div>
                                    <div class="var-card" data-var="{date}" title="点击复制"><div class="var-name">{date}</div><div class="var-desc">当前日期</div></div>
                                    <div class="var-card" data-var="{result}" title="点击复制"><div class="var-name">{result}</div><div class="var-desc">API 返回结果</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API 响应测试区域 -->
            <div class="mt-6">
                <div class="api-test-section" style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
                    <div class="form-group" style="flex:1; min-width:200px; margin-bottom:0;">
                        <label>测试参数 <small style="color:#9ca3af;">(可选，替换参数占位符 {0} {1} ...)</small></label>
                        <input type="text" id="testParams" class="form-control" placeholder="参数以空格分隔，如: 北京 today">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <button type="button" id="btnTestApi" class="btn btn-info btn-lg" style="white-space:nowrap;">
                            <i class="fas fa-flask"></i> 测试API
                        </button>
                    </div>
                </div>
                <div id="apiTestResult" style="display:none; margin-top:16px;">
                    <div class="card" style="border-left: 3px solid var(--primary);">
                        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <h4 style="margin:0;"><i class="fas fa-terminal"></i> API响应</h4>
                            <span id="testHttpCode" style="font-size:13px; padding:3px 10px; border-radius:12px; background:var(--bg-hover);"></span>
                        </div>
                        <div class="card-body" style="padding:12px 16px;">
                            <div style="display:flex; gap:12px; margin-bottom:8px;">
                                <small style="color:#9ca3af;">响应耗时: <strong id="testDuration">-</strong></small>
                                <small style="color:#9ca3af;">响应大小: <strong id="testSize">-</strong></small>
                            </div>
                            <pre id="testResponseBody" style="background:var(--bg-hover); color:var(--text); padding:12px; border-radius:8px; max-height:300px; overflow:auto; margin:0; font-size:13px; line-height:1.6; white-space:pre-wrap; word-break:break-all;"></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex-between">
                <a href="api_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回列表</a>
                <div style="display:flex; gap:10px;">
                    <button type="button" id="btnTestApiBottom" class="btn btn-info" style="display:none;">
                        <i class="fas fa-flask"></i> 测试API
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> <?php echo $editApi ? '保存修改' : '保存API'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.var-card {
    background: var(--bg-hover, #f8f9fa);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: all .2s ease;
    position: relative;
}
.var-card:hover {
    border-color: var(--primary, #3b82f6);
    box-shadow: 0 2px 8px rgba(59,130,246,.12);
    transform: translateY(-1px);
}
.var-card.copied {
    border-color: #10b981;
    background: #10b98110;
}
.var-card .var-name {
    color: var(--primary, #3b82f6);
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 6px;
    word-break: break-all;
}
.var-card .var-desc {
    color: var(--text-muted, #6b7280);
    font-size: 12px;
    line-height: 1.5;
}
.var-card::after {
    content: "\f0c5";
    font-family: "Font Awesome 5 Free";
    font-weight: 400;
    position: absolute;
    top: 8px;
    right: 10px;
    font-size: 12px;
    color: var(--text-muted, #9ca3af);
    opacity: 0;
    transition: opacity .2s ease;
}
.var-card:hover::after {
    opacity: 1;
}
.var-card.copied::after {
    content: "\f00c";
    font-weight: 900;
    color: #10b981;
    opacity: 1;
}
</style>

<script>
(function() {
    // 变量面板展开/收起
    var btnToggleVars = document.getElementById('btnToggleVars');
    var btnCloseVars = document.getElementById('btnCloseVars');
    var varsPanel = document.getElementById('varsPanel');

    function toggleVarsPanel(show) {
        if (varsPanel) varsPanel.style.display = show ? 'block' : 'none';
    }

    if (btnToggleVars) {
        btnToggleVars.addEventListener('click', function() {
            toggleVarsPanel(varsPanel.style.display === 'none');
        });
    }
    if (btnCloseVars) {
        btnCloseVars.addEventListener('click', function() { toggleVarsPanel(false); });
    }

    // 点击变量卡片复制到剪贴板
    document.querySelectorAll('.var-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var text = this.getAttribute('data-var') || '';
            var self = this;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() { markCopied(self); });
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); markCopied(self); } catch(e) {}
                document.body.removeChild(ta);
            }
        });
    });

    function markCopied(el) {
        el.classList.add('copied');
        var original = el.querySelector('.var-name').textContent;
        el.querySelector('.var-name').textContent = '已复制';
        setTimeout(function() {
            el.classList.remove('copied');
            el.querySelector('.var-name').textContent = original;
        }, 1200);
    }

    var btnTest = document.getElementById('btnTestApi');
    var btnTestBottom = document.getElementById('btnTestApiBottom');
    var testParams = document.getElementById('testParams');
    var resultPanel = document.getElementById('apiTestResult');
    var httpCodeEl = document.getElementById('testHttpCode');
    var durationEl = document.getElementById('testDuration');
    var sizeEl = document.getElementById('testSize');
    var bodyEl = document.getElementById('testResponseBody');

    function getFormData() {
        return {
            api_url: document.querySelector('[name="api_url"]').value.trim(),
            method: document.querySelector('[name="method"]').value,
            headers: document.querySelector('[name="headers"]').value.trim(),
            body: document.querySelector('[name="body"]').value.trim(),
            timeout: document.querySelector('[name="timeout"]').value || 10
        };
    }

    function showLoading() {
        resultPanel.style.display = 'block';
        bodyEl.textContent = '正在请求...';
        httpCodeEl.textContent = '...';
        durationEl.textContent = '...';
        sizeEl.textContent = '...';
        httpCodeEl.style.background = 'transparent';
        httpCodeEl.style.color = 'var(--text-muted, #999)';
    }

    function showResult(data, duration) {
        resultPanel.style.display = 'block';
        httpCodeEl.textContent = data.http_code || '-';
        var ok = data.http_code >= 200 && data.http_code < 300;
        httpCodeEl.style.background = ok ? '#10b98120' : '#ef444420';
        httpCodeEl.style.color = ok ? '#10b981' : '#ef4444';
        durationEl.textContent = duration + 'ms';
        sizeEl.textContent = (data.size || 0) + ' bytes';
        // 图片类型：直接渲染预览
        if (data.is_image && data.image_url) {
            bodyEl.innerHTML = '<div style="margin-bottom:8px;color:#10b981;font-weight:600;">✓ 图片响应 (' + (data.content_type || 'image') + ')</div>' +
                '<a href="' + data.image_url + '" target="_blank"><img src="' + data.image_url + '" style="max-width:100%;max-height:400px;border-radius:6px;border:1px solid #e5e7eb;" /></a>' +
                '<div style="margin-top:8px;font-size:12px;color:#6b7280;word-break:break-all;">' + data.image_url + '</div>';
            return;
        }
        var displayText = data.response || '';
        try { var p = JSON.parse(displayText); displayText = JSON.stringify(p, null, 2); } catch(e) {}
        bodyEl.textContent = displayText;
    }

    function showError(msg) {
        resultPanel.style.display = 'block';
        httpCodeEl.textContent = 'ERR';
        httpCodeEl.style.background = '#ef444420';
        httpCodeEl.style.color = '#ef4444';
        durationEl.textContent = '-';
        sizeEl.textContent = '-';
        bodyEl.textContent = msg;
    }

    // 编辑模式：后端代理（无跨域问题）
    <?php if ($editApi): ?>
    function doTest() {
        showLoading();
        var start = Date.now();
        fetch('../api/api.php?action=test&id=<?php echo $editApi['id']; ?>')
            .then(function(r) {
                return r.text().then(function(txt) {
                    try { return JSON.parse(txt); }
                    catch(e) { return { code: -1, msg: '服务器返回异常: ' + txt.substring(0, 200) }; }
                });
            })
            .then(function(data) {
                var dur = Date.now() - start;
                if (data.code === 0) showResult(data.data, dur);
                else showError(data.msg || '测试失败');
            })
            .catch(function(err) { showError('请求失败: ' + err.message); });
    }
    <?php else: ?>
    // 添加模式：直接请求（部分 API 可能有跨域限制）
    function doTest() {
        var form = getFormData();
        if (!form.api_url) {
            if (typeof showToast !== 'undefined') showToast('请先填写 API 地址', 'warning');
            else alert('请先填写 API 地址');
            return;
        }
        var url = form.api_url;
        var params = testParams.value.trim();
        if (params) {
            var args = params.split(/\s+/);
            for (var i = 0; i < args.length; i++) {
                url = url.replace(new RegExp('\\{' + i + '\\}', 'g'), encodeURIComponent(args[i]));
            }
        }

        showLoading();
        var start = Date.now();

        var hdr = {};
        if (form.headers) { try { hdr = JSON.parse(form.headers); } catch(e) {} }
        var opts = { method: form.method, headers: hdr };
        if (form.method === 'POST' && form.body) {
            opts.body = form.body;
            if (!opts.headers['Content-Type']) opts.headers['Content-Type'] = 'application/json';
        }

        var ctrl = new AbortController();
        opts.signal = ctrl.signal;
        var timer = setTimeout(function() { ctrl.abort(); }, (parseInt(form.timeout) || 10) * 1000);

        fetch(url, opts)
            .then(function(resp) {
                clearTimeout(timer);
                var dur = Date.now() - start;
                return resp.text().then(function(txt) {
                    showResult({ http_code: resp.status, response: txt, size: txt.length }, dur);
                });
            })
            .catch(function(err) {
                clearTimeout(timer);
                showError('请求失败: ' + (err.name === 'AbortError' ? '请求超时' : err.message));
            });
    }
    <?php endif; ?>

    if (btnTest) btnTest.addEventListener('click', doTest);
    if (btnTestBottom) {
        btnTestBottom.style.display = 'inline-block';
        btnTestBottom.addEventListener('click', doTest);
    }

    if (testParams) {
        testParams.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doTest();
            }
        });
    }
})();
</script>

<?php require '../includes/load_footer.php'; ?>
