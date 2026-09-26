<?php
/**
 * 插件开发文档（PHP 版）
 * 基于平台实际插件系统架构编写，与代码 100% 对齐
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = '插件开发文档';
$activePage = 'plugin_dev_doc';
$user = getCurrentUser();

include __DIR__ . '/../includes/load_header.php';
?>

<!-- ============ 文档头部 ============ -->
<div class="page-header">
    <h2 class="page-title"><i class="icon">📖</i> 插件开发文档</h2>
    <p class="page-desc">基于 <?php echo getSiteName(); ?> v<?php echo SITE_VERSION; ?> 插件系统的完整开发指南</p>
</div>

<div class="doc-container">

<!-- ============ 侧边导航 ============ -->
<nav class="doc-sidebar" id="docSidebar">
    <div class="doc-nav-title">📑 目录导航</div>
    <a href="#sec1" class="doc-nav-link">1. 快速入门</a>
    <a href="#sec1-1" class="doc-nav-link sub">1.1 开发前准备</a>
    <a href="#sec1-2" class="doc-nav-link sub">1.2 最小示例</a>
    <a href="#sec2" class="doc-nav-link">2. 插件元信息</a>
    <a href="#sec3" class="doc-nav-link">3. 四个核心回调</a>
    <a href="#sec4" class="doc-nav-link">4. 回调参数详解</a>
    <a href="#sec4-1" class="doc-nav-link sub">4.1 $msg 消息对象</a>
    <a href="#sec4-2" class="doc-nav-link sub">4.2 $botapi 机器人实例</a>
    <a href="#sec5" class="doc-nav-link">5. 返回值格式</a>
    <a href="#sec6" class="doc-nav-link">6. 平台内置辅助函数</a>
    <a href="#sec7" class="doc-nav-link">7. contentType 速查表</a>
    <a href="#sec8" class="doc-nav-link">8. 实战：缓存与文件读写</a>
    <a href="#sec9" class="doc-nav-link">9. 调试与排错</a>
    <a href="#sec10" class="doc-nav-link">10. 完整实战案例</a>
    <a href="#sec11" class="doc-nav-link">11. FAQ</a>
    <a href="#sec12" class="doc-nav-link">12. 安全加固</a>
</nav>

<!-- ============ 文档正文 ============ -->
<div class="doc-content">

<!-- ===== 1. 快速入门 ===== -->
<h2 id="sec1" class="doc-section-title">1. 快速入门</h2>

<h3 id="sec1-1" class="doc-h3">1.1 开发前准备</h3>
<p>开发插件前请确认满足以下条件：</p>
<div class="doc-callout doc-callout-info">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>前置要求</strong>
        <ol style="margin:6px 0 0; padding-left:20px;">
            <li>拥有平台账号且已申请 <strong>创作者</strong> 权限（或管理员）</li>
            <li>已创建至少一个机器人并获取 Token</li>
            <li>掌握 PHP 基础语法（数组、函数、PDO、curl）</li>
        </ol>
    </div>
</div>

<h3 id="sec1-2" class="doc-h3">1.2 最小示例 — Hello World</h3>
<p>以下是一个完整可运行的插件，保存为 <code>hello_world.php</code>：</p>

<pre class="doc-code-block"><code><span class="php-tag">&lt;?php</span>
<span class="php-comment">// ===== 1. 插件元信息 =====</span>
<span class="php-var">$info</span> = [
    <span class="php-str">'name'</span>           =&gt; <span class="php-str">'Hello World'</span>,
    <span class="php-str">'version'</span>        =&gt; <span class="php-str">'1.0.0'</span>,
    <span class="php-str">'author'</span>         =&gt; <span class="php-str">'你的名字'</span>,
    <span class="php-str">'desc'</span>           =&gt; <span class="php-str">'一个最简单的入门插件'</span>,
    <span class="php-str">'command_list'</span>   =&gt; [<span class="php-str">'hello'</span>, <span class="php-str">'你好'</span>],
];

<span class="php-comment">// ===== 2. 加载确认回调 =====</span>
<span class="php-keyword">function</span> <span class="php-func">has_been_loaded</span>(<span class="php-var">$info</span>) {
    <span class="php-keyword">return</span> <span class="php-const">true</span>;
}

<span class="php-comment">// ===== 3. 群聊回调 =====</span>
<span class="php-keyword">function</span> <span class="php-func">group</span>(<span class="php-var">$msg</span>, <span class="php-var">$botapi</span>) {
    <span class="php-keyword">return</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-str">'group'</span>, <span class="php-var">$botapi</span>);
}

<span class="php-comment">// ===== 4. 私聊回调 =====</span>
<span class="php-keyword">function</span> <span class="php-func">C2C</span>(<span class="php-var">$msg</span>, <span class="php-var">$botapi</span>) {
    <span class="php-keyword">return</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-str">'private'</span>, <span class="php-var">$botapi</span>);
}

<span class="php-comment">// ===== 5. 按钮回调（本示例不处理，返回 null）=====</span>
<span class="php-keyword">function</span> <span class="php-func">button</span>(<span class="php-var">$button_event</span>, <span class="php-var">$botapi</span>) {
    <span class="php-keyword">return</span> <span class="php-const">null</span>;
}

<span class="php-comment">// ===== 6. 业务主逻辑 =====</span>
<span class="php-keyword">function</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-var">$chatType</span>, <span class="php-var">$botapi</span>) {
    <span class="php-var">$content</span> = <span class="php-func">trim</span>(<span class="php-var">$msg</span>[<span class="php-str">'content'</span>] ?? <span class="php-str">''</span>);
    <span class="php-var">$cmdList</span> = [<span class="php-str">'hello'</span>, <span class="php-str">'你好'</span>];

    <span class="php-comment">// 命令匹配：精确匹配 或 "命令+空格+参数"</span>
    <span class="php-var">$matched</span> = <span class="php-const">false</span>;
    <span class="php-keyword">foreach</span> (<span class="php-var">$cmdList</span> <span class="php-keyword">as</span> <span class="php-var">$cmd</span>) {
        <span class="php-keyword">if</span> (<span class="php-var">$content</span> === <span class="php-var">$cmd</span> || <span class="php-func">strpos</span>(<span class="php-var">$content</span>, <span class="php-var">$cmd</span> . <span class="php-str">' '</span>) === <span class="php-num">0</span>) {
            <span class="php-var">$matched</span> = <span class="php-const">true</span>;
            <span class="php-keyword">break</span>;
        }
    }
    <span class="php-keyword">if</span> (!<span class="php-var">$matched</span>) <span class="php-keyword">return</span> <span class="php-const">null</span>;

    <span class="php-keyword">return</span> [
        <span class="php-str">'type'</span>    =&gt; <span class="php-str">'text'</span>,
        <span class="php-str">'content'</span> =&gt; <span class="php-str">'Hello, World! 🎉 你已成功开发了第一个插件'</span>,
    ];
}</code></pre>

<div class="doc-callout doc-callout-tip">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>命令匹配规则</strong>：用户发送 <code>hello</code> 或 <code>你好</code> 时触发。加 <code>+</code> 可携带参数，如 <code>hello 白屿</code>。
    </div>
</div>

<!-- ===== 2. 插件元信息 ===== -->
<h2 id="sec2" class="doc-section-title">2. 插件元信息 <code>$info</code></h2>
<p>每个插件必须定义一个 <code>$info</code> 数组，平台通过它识别插件的名称、版本和触发命令。</p>

<table class="doc-table">
    <thead>
        <tr><th>字段</th><th>必填</th><th>类型</th><th>说明</th></tr>
    </thead>
    <tbody>
        <tr><td><code>name</code></td><td>✅</td><td>string</td><td>插件名称，不超过 30 字</td></tr>
        <tr><td><code>version</code></td><td>✅</td><td>string</td><td>语义化版本号，如 <code>1.2.3</code></td></tr>
        <tr><td><code>author</code></td><td>✅</td><td>string</td><td>作者署名</td></tr>
        <tr><td><code>desc</code></td><td>可选</td><td>string</td><td>一句话功能描述</td></tr>
        <tr><td><code>command_list</code></td><td>可选</td><td>array</td><td>触发命令列表，用于群内 <code>+</code> 提权和菜单显示</td></tr>
    </tbody>
</table>

<h3 class="doc-h3">示例</h3>
<pre class="doc-code-block"><code><span class="php-var">$info</span> = [
    <span class="php-str">'name'</span>         =&gt; <span class="php-str">'翻译助手'</span>,
    <span class="php-str">'version'</span>      =&gt; <span class="php-str">'1.0.0'</span>,
    <span class="php-str">'author'</span>       =&gt; <span class="php-str">'白屿'</span>,
    <span class="php-str">'desc'</span>         =&gt; <span class="php-str">'多语言翻译，支持中英日韩互译'</span>,
    <span class="php-str">'command_list'</span> =&gt; [<span class="php-str">'翻译'</span>, <span class="php-str">'translate'</span>, <span class="php-str">'fy'</span>, <span class="php-str">'英文翻译'</span>],
];</code></pre>

<!-- ===== 3. 四个核心回调 ===== -->
<h2 id="sec3" class="doc-section-title">3. 四个核心回调函数</h2>
<p>平台在收到消息后，会根据消息来源（群聊/私聊/按钮）自动调用对应的回调函数。所有回调必须定义，不需要的返回 <code>null</code>。</p>

<h3 class="doc-h3">3.1 <code>has_been_loaded($info)</code> — 加载确认</h3>
<pre class="doc-code-block"><code><span class="php-keyword">function</span> <span class="php-func">has_been_loaded</span>(<span class="php-var">$info</span>) {
    <span class="php-keyword">return</span> <span class="php-const">true</span>; <span class="php-comment">// 返回 true 表示插件正常加载</span>
}</code></pre>

<h3 class="doc-h3">3.2 <code>group($msg, $botapi)</code> — 群聊消息</h3>
<p>当用户在 <strong>群聊</strong> 中发送消息时触发。</p>
<pre class="doc-code-block"><code><span class="php-keyword">function</span> <span class="php-func">group</span>(<span class="php-var">$msg</span>, <span class="php-var">$botapi</span>) {
    <span class="php-keyword">return</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-str">'group'</span>, <span class="php-var">$botapi</span>);
}</code></pre>

<h3 class="doc-h3">3.3 <code>C2C($msg, $botapi)</code> — 私聊消息</h3>
<p>当用户在 <strong>私聊</strong> 机器人时触发。</p>
<pre class="doc-code-block"><code><span class="php-keyword">function</span> <span class="php-func">C2C</span>(<span class="php-var">$msg</span>, <span class="php-var">$botapi</span>) {
    <span class="php-keyword">return</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-str">'private'</span>, <span class="php-var">$botapi</span>);
}</code></pre>

<h3 class="doc-h3">3.4 <code>button($button_event, $botapi)</code> — 按钮点击</h3>
<p>当用户点击交互卡片按钮时触发。如不处理按钮事件，直接返回 <code>null</code>。</p>
<pre class="doc-code-block"><code><span class="php-keyword">function</span> <span class="php-func">button</span>(<span class="php-var">$button_event</span>, <span class="php-var">$botapi</span>) {
    <span class="php-keyword">return</span> <span class="php-const">null</span>;
}</code></pre>

<div class="doc-callout doc-callout-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>命名约定</strong>：<code>group</code> 和 <code>C2C</code> 是大小写敏感的，<code>C2C</code> 必须全大写。主逻辑函数 <code>runPlugin()</code> 是约定俗成的命名，你可以在 <code>group</code>/<code>C2C</code> 中传不同的 <code>$chatType</code> 参数区分消息来源。
    </div>
</div>

<!-- ===== 4. 回调参数详解 ===== -->
<h2 id="sec4" class="doc-section-title">4. 回调参数详解</h2>

<h3 id="sec4-1" class="doc-h3">4.1 <code>$msg</code> — 消息对象</h3>
<p>这是云湖 Webhook 推送的消息数据结构，平台已做简化封装：</p>
<pre class="doc-code-block"><code><span class="php-var">$msg</span> = [
    <span class="php-str">'content'</span>  =&gt; <span class="php-str">'用户发送的文本'</span>,    <span class="php-comment">// 消息正文</span>
    <span class="php-str">'chat'</span>     =&gt; [
        <span class="php-str">'id'</span>   =&gt; <span class="php-str">'g_12345'</span>,       <span class="php-comment">// 聊天 ID（群 ID 或用户 ID）</span>
        <span class="php-str">'type'</span> =&gt; <span class="php-str">'group'</span>,         <span class="php-comment">// group / bot（私聊）</span>
    ],
    <span class="php-str">'sender'</span>   =&gt; [
        <span class="php-str">'id'</span>   =&gt; <span class="php-str">'u_666'</span>,         <span class="php-comment">// 发送者云湖 ID</span>
        <span class="php-str">'name'</span> =&gt; <span class="php-str">'小明'</span>,          <span class="php-comment">// 发送者昵称</span>
    ],
];</code></pre>

<h3 id="sec4-2" class="doc-h3">4.2 <code>$botapi</code> — 机器人实例</h3>
<p>包含当前机器人的连接信息和回复目标：</p>
<pre class="doc-code-block"><code><span class="php-var">$botapi</span> = [
    <span class="php-str">'token'</span>    =&gt; <span class="php-str">'xxxx...'</span>,       <span class="php-comment">// 机器人 Token（用于调 API）</span>
    <span class="php-str">'recvId'</span>   =&gt; <span class="php-str">'g_12345'</span>,      <span class="php-comment">// 回复目标 ID（即 $msg['chat']['id']）</span>
    <span class="php-str">'recvType'</span> =&gt; <span class="php-str">'group'</span>,        <span class="php-comment">// group / user</span>
];</code></pre>

<div class="doc-callout doc-callout-tip">
    <i class="fas fa-lightbulb"></i>
    <div><strong>提示</strong>：<code>$botapi</code> 已包含回复目标，直接传给 <code>yunhuSendMessage()</code> 即可发送消息。</div>
</div>

<h3 class="doc-h3">4.3 安全取 Token 的辅助函数</h3>
<pre class="doc-code-block"><code><span class="php-keyword">function</span> <span class="php-func">getBotToken</span>(<span class="php-var">$botapi</span>) {
    <span class="php-keyword">if</span> (<span class="php-func">is_array</span>(<span class="php-var">$botapi</span>) &amp;&amp; !<span class="php-func">empty</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>])) {
        <span class="php-keyword">return</span> <span class="php-var">$botapi</span>[<span class="php-str">'token'</span>];
    }
    <span class="php-keyword">if</span> (<span class="php-func">is_object</span>(<span class="php-var">$botapi</span>) &amp;&amp; !<span class="php-func">empty</span>(<span class="php-var">$botapi</span>-&gt;token)) {
        <span class="php-keyword">return</span> <span class="php-var">$botapi</span>-&gt;token;
    }
    <span class="php-keyword">return</span> <span class="php-str">''</span>;
}</code></pre>

<!-- ===== 5. 返回值格式 ===== -->
<h2 id="sec5" class="doc-section-title">5. 返回值格式</h2>
<p>插件执行完毕后，通过 <code>return</code> 返回一个 <code>array</code> 告诉平台如何回复用户。返回 <code>null</code> 表示不回复。</p>

<div class="doc-callout doc-callout-danger">
    <i class="fas fa-radiation"></i>
    <div><strong>核心规则</strong>：不返回或返回 <code>null</code>，平台会跳过此插件继续匹配下一个插件/菜单/API。</div>
</div>

<h3 class="doc-h3">5.1 文本消息</h3>
<pre class="doc-code-block"><code><span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'text'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-str">'这是一条普通文本消息'</span>,
];</code></pre>

<h3 class="doc-h3">5.2 图片消息</h3>
<pre class="doc-code-block"><code><span class="php-comment">// 需先调用上传接口获取 imageKey</span>
<span class="php-var">$imageKey</span> = <span class="php-func">uploadImage</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>], <span class="php-str">'/tmp/x.jpg'</span>);

<span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'image'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-var">$imageKey</span>,
];</code></pre>

<h3 class="doc-h3">5.3 文件消息</h3>
<pre class="doc-code-block"><code><span class="php-var">$fileKey</span> = <span class="php-func">uploadFile</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>], <span class="php-func">file_get_contents</span>(<span class="php-str">'/tmp/a.txt'</span>), <span class="php-str">'文件.txt'</span>);

<span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'file'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-var">$fileKey</span>,
];</code></pre>

<h3 class="doc-h3">5.4 视频消息</h3>
<pre class="doc-code-block"><code><span class="php-var">$videoKey</span> = <span class="php-func">uploadVideo</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>], <span class="php-str">'/tmp/v.mp4'</span>, <span class="php-str">'视频.mp4'</span>);

<span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'video'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-var">$videoKey</span>,
];</code></pre>

<h3 class="doc-h3">5.5 Markdown 消息</h3>
<pre class="doc-code-block"><code><span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'markdown'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-str">"## 标题\n\n**加粗** *斜体* `代码`\n\n- 列表项1\n- 列表项2"</span>,
];</code></pre>

<h3 class="doc-h3">5.6 HTML 消息</h3>
<pre class="doc-code-block"><code><span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'html'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-str">'&lt;b&gt;加粗&lt;/b&gt;&lt;i&gt;斜体&lt;/i&gt;&lt;u&gt;下划线&lt;/u&gt;&lt;br&gt;换行'</span>,
];</code></pre>

<!-- ===== 6. 平台内置辅助函数 ===== -->
<h2 id="sec6" class="doc-section-title">6. 平台内置辅助函数</h2>
<p>以下函数在 <code>functions.php</code> 中定义，插件运行时可直接调用。</p>

<h3 class="doc-h3">6.1 主动发送消息</h3>
<pre class="doc-code-block"><code><span class="php-comment">// 参数：token, recvId, content, contentType, recvType</span>
<span class="php-var">$token</span>   = <span class="php-var">$botapi</span>[<span class="php-str">'token'</span>];
<span class="php-var">$recvId</span>  = <span class="php-var">$botapi</span>[<span class="php-str">'recvId'</span>];
<span class="php-var">$recvType</span> = <span class="php-var">$botapi</span>[<span class="php-str">'recvType'</span>];

<span class="php-comment">// 发送文本</span>
<span class="php-func">yunhuSendMessage</span>(<span class="php-var">$token</span>, <span class="php-var">$recvId</span>, <span class="php-str">'处理中...'</span>, <span class="php-str">'text'</span>, <span class="php-var">$recvType</span>);

<span class="php-comment">// 发送 Markdown</span>
<span class="php-var">$md</span> = <span class="php-str">"## 结果\n\n1. 项目A\n2. 项目B"</span>;
<span class="php-func">yunhuSendMessage</span>(<span class="php-var">$token</span>, <span class="php-var">$recvId</span>, <span class="php-var">$md</span>, <span class="php-str">'markdown'</span>, <span class="php-var">$recvType</span>);</code></pre>

<div class="doc-callout doc-callout-info">
    <i class="fas fa-info-circle"></i>
    <div><code>yunhuSendMessage()</code> 支持 5 种 contentType：<code>text</code> / <code>markdown</code> / <code>html</code> / <code>image</code> / <code>file</code> / <code>video</code>。群聊 recvType=<code>'group'</code>，私聊 recvType=<code>'user'</code>。</div>
</div>

<h3 class="doc-h3">6.2 上传文件到云湖</h3>
<pre class="doc-code-block"><code><span class="php-comment">// 上传图片（需先保存到本地临时文件）</span>
<span class="php-var">$imageKey</span> = <span class="php-func">uploadImage</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>], <span class="php-str">'/path/to/image.jpg'</span>);

<span class="php-comment">// 上传文件（内容 + 文件名）</span>
<span class="php-var">$fileKey</span> = <span class="php-func">uploadFile</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>], <span class="php-func">file_get_contents</span>(<span class="php-str">'/path/to/file.zip'</span>), <span class="php-str">'下载文件.zip'</span>);

<span class="php-comment">// 上传视频</span>
<span class="php-var">$videoKey</span> = <span class="php-func">uploadVideo</span>(<span class="php-var">$botapi</span>[<span class="php-str">'token'</span>], <span class="php-str">'/path/to/video.mp4'</span>, <span class="php-str">'视频.mp4'</span>);</code></pre>

<h3 class="doc-h3">6.3 数据库操作</h3>
<pre class="doc-code-block"><code><span class="php-comment">// 读取用户数据（参数：用户云湖 ID）</span>
<span class="php-var">$userId</span> = <span class="php-var">$msg</span>[<span class="php-str">'sender'</span>][<span class="php-str">'id'</span>] ?? <span class="php-str">'0'</span>;

<span class="php-comment">// 查询</span>
<span class="php-var">$row</span> = <span class="php-func">db</span>()-&gt;<span class="php-func">prepare</span>(<span class="php-str">"SELECT * FROM my_plugin_data WHERE user_id = ?"</span>);
<span class="php-var">$row</span>-&gt;<span class="php-func">execute</span>([<span class="php-var">$userId</span>]);
<span class="php-var">$data</span> = <span class="php-var">$row</span>-&gt;<span class="php-func">fetch</span>();

<span class="php-comment">// 写入</span>
<span class="php-func">db</span>()-&gt;<span class="php-func">prepare</span>(<span class="php-str">"INSERT INTO my_plugin_data (user_id, value) VALUES (?, ?)"</span>)
    -&gt;<span class="php-func">execute</span>([<span class="php-var">$userId</span>, <span class="php-str">'hello'</span>]);</code></pre>

<div class="doc-callout doc-callout-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div><strong>命名空间隔离</strong>：插件运行在独立命名空间 <code>plugin_{id}</code> 下。如需在插件中创建自定义数据表，建议加前缀 <code>plug_xxx_</code>。同时请使用 PHP 命名空间规范，引用全局类需加 <code>\</code>（如 <code>\PDO</code>、<code>\CURLFile</code>、<code>\Exception</code>）。</div>
</div>

<h3 class="doc-h3">6.4 消息日志记录</h3>
<pre class="doc-code-block"><code><span class="php-comment">// 记录插件操作日志到平台消息日志系统</span>
<span class="php-func">addMessageLog</span>(
    <span class="php-var">$botapi</span>[<span class="php-str">'id'</span>] ?? <span class="php-num">0</span>,          <span class="php-comment">// bot_id</span>
    <span class="php-str">'send'</span>,                       <span class="php-comment">// 类型：send / recv</span>
    <span class="php-str">'翻译完成：hello → 你好'</span>,       <span class="php-comment">// 内容</span>
    <span class="php-var">$msg</span>[<span class="php-str">'sender'</span>][<span class="php-str">'id'</span>],          <span class="php-comment">// 来源 ID</span>
    <span class="php-var">$msg</span>[<span class="php-str">'sender'</span>][<span class="php-str">'name'</span>],        <span class="php-comment">// 来源昵称</span>
    <span class="php-str">'插件：翻译助手'</span>               <span class="php-comment">// 备注</span>
);</code></pre>

<h3 class="doc-h3">6.5 HTTP 网络请求</h3>
<pre class="doc-code-block"><code><span class="php-comment">// POST JSON 请求（平台内置函数）</span>
<span class="php-var">$result</span> = <span class="php-func">curlPostJson</span>(<span class="php-str">'https://api.example.com/translate'</span>, [
    <span class="php-str">'text'</span>   =&gt; <span class="php-str">'你好'</span>,
    <span class="php-str">'target'</span> =&gt; <span class="php-str">'en'</span>,
]);
<span class="php-var">$data</span> = <span class="php-func">json_decode</span>(<span class="php-var">$result</span>, <span class="php-const">true</span>);

<span class="php-comment">// GET 请求（需在插件内自行实现）</span>
<span class="php-var">$data</span> = <span class="php-func">file_get_contents</span>(<span class="php-str">'https://api.example.com/ip'</span>, <span class="php-const">false</span>,
    <span class="php-func">stream_context_create</span>([
        <span class="php-str">'http'</span> =&gt; [<span class="php-str">'timeout'</span> =&gt; <span class="php-num">10</span>, <span class="php-str">'user_agent'</span> =&gt; <span class="php-str">'BaiYuBot/1.0'</span>]
    ]));</code></pre>

<!-- ===== 7. contentType 速查表 ===== -->
<h2 id="sec7" class="doc-section-title">7. <code>contentType</code> 类型速查表</h2>
<table class="doc-table">
    <thead>
        <tr><th>返回 type</th><th>对应 contentType</th><th>content 字段</th></tr>
    </thead>
    <tbody>
        <tr><td><code>text</code></td><td>text</td><td>纯文本字符串</td></tr>
        <tr><td><code>markdown</code></td><td>markdown</td><td>Markdown 格式字符串</td></tr>
        <tr><td><code>html</code></td><td>html</td><td>HTML 富文本字符串</td></tr>
        <tr><td><code>image</code></td><td>image</td><td>imageKey（上传后获取）</td></tr>
        <tr><td><code>file</code></td><td>file</td><td>fileKey（上传后获取）</td></tr>
        <tr><td><code>video</code></td><td>video</td><td>videoKey（上传后获取）</td></tr>
    </tbody>
</table>

<!-- ===== 8. 实战：缓存与文件读写 ===== -->
<h2 id="sec8" class="doc-section-title">8. 实战：缓存与文件读写</h2>
<p>以下示例展示如何缓存用户搜索结果（30 分钟有效），利用 JSON 文件做持久化。</p>
<pre class="doc-code-block"><code><span class="php-comment">// ===== 保存搜索结果（按用户 ID 隔离）=====</span>
<span class="php-keyword">function</span> <span class="php-func">saveSearchResult</span>(<span class="php-var">$userId</span>, <span class="php-var">$results</span>) {
    <span class="php-var">$f</span> = <span class="php-const">__DIR__</span> . <span class="php-str">'/search_cache.json'</span>;
    <span class="php-var">$all</span> = <span class="php-func">file_exists</span>(<span class="php-var">$f</span>) ? (<span class="php-func">json_decode</span>(<span class="php-func">file_get_contents</span>(<span class="php-var">$f</span>), <span class="php-const">true</span>) ?: []) : [];
    <span class="php-var">$all</span>[<span class="php-var">$userId</span>] = [<span class="php-str">'time'</span> =&gt; <span class="php-func">time</span>(), <span class="php-str">'data'</span> =&gt; <span class="php-var">$results</span>];
    <span class="php-func">file_put_contents</span>(<span class="php-var">$f</span>, <span class="php-func">json_encode</span>(<span class="php-var">$all</span>, <span class="php-const">JSON_UNESCAPED_UNICODE</span>));
}

<span class="php-comment">// ===== 读取搜索结果（30 分钟内有效）=====</span>
<span class="php-keyword">function</span> <span class="php-func">getSearchResult</span>(<span class="php-var">$userId</span>) {
    <span class="php-var">$f</span> = <span class="php-const">__DIR__</span> . <span class="php-str">'/search_cache.json'</span>;
    <span class="php-keyword">if</span> (!<span class="php-func">file_exists</span>(<span class="php-var">$f</span>)) <span class="php-keyword">return</span> <span class="php-const">false</span>;
    <span class="php-var">$all</span> = <span class="php-func">json_decode</span>(<span class="php-func">file_get_contents</span>(<span class="php-var">$f</span>), <span class="php-const">true</span>);
    <span class="php-keyword">if</span> (<span class="php-func">empty</span>(<span class="php-var">$all</span>[<span class="php-var">$userId</span>]) || <span class="php-func">time</span>() - <span class="php-var">$all</span>[<span class="php-var">$userId</span>][<span class="php-str">'time'</span>] &gt; <span class="php-num">1800</span>) <span class="php-keyword">return</span> <span class="php-const">false</span>;
    <span class="php-keyword">return</span> <span class="php-var">$all</span>[<span class="php-var">$userId</span>][<span class="php-str">'data'</span>];
}</code></pre>

<div class="doc-callout doc-callout-tip">
    <i class="fas fa-lightbulb"></i>
    <div><strong>缓存策略</strong>：涉及搜索结果、用户偏好等临时数据，推荐用 JSON 文件缓存（存放在 <code>plugins/</code> 目录下）。涉及需要持久化、高并发的数据，请用数据库表。</div>
</div>

<!-- ===== 9. 调试与排错 ===== -->
<h2 id="sec9" class="doc-section-title">9. 调试与排错</h2>

<h3 class="doc-h3">9.1 本地调试环境</h3>
<p>用 XAMPP/WAMP 搭建本地 PHP 环境，将项目放到 <code>htdocs</code> 目录下，修改 <code>config.php</code> 中的 <code>DEBUG_MODE = true</code>。</p>

<h3 class="doc-h3">9.2 返回消息透传调试</h3>
<pre class="doc-code-block"><code><span class="php-comment">// 不需要 var_dump，直接把调试信息作为消息返回给用户</span>
<span class="php-keyword">return</span> [
    <span class="php-str">'type'</span>    =&gt; <span class="php-str">'text'</span>,
    <span class="php-str">'content'</span> =&gt; <span class="php-str">'[调试] 收到内容：'</span> . <span class="php-var">$msg</span>[<span class="php-str">'content'</span>],
];</code></pre>

<h3 class="doc-h3">9.3 常见问题排查清单</h3>
<table class="doc-table">
    <thead>
        <tr><th>问题</th><th>可能原因</th><th>解决方案</th></tr>
    </thead>
    <tbody>
        <tr><td>插件无响应</td><td>命令未匹配 / 函数名拼写错误</td><td>检查 <code>command_list</code> 和回调函数名</td></tr>
        <tr><td>PHP Fatal error</td><td>语法错误 / 未定义函数</td><td>用 <code>php -l x.php</code> 检查语法</td></tr>
        <tr><td>命令以 <code>/</code> 开头不触发</td><td>平台不使用 <code>/</code> 前缀</td><td>用 <code>命令+空格</code> 或直接命令名</td></tr>
        <tr><td>上传文件失败</td><td>临时目录权限不足</td><td>检查 <code>sys_get_temp_dir()</code> 可写</td></tr>
        <tr><td>Token 获取失败</td><td><code>$botapi</code> 结构异常</td><td>用 <code>getBotToken()</code> 辅助函数</td></tr>
    </tbody>
</table>

<!-- ===== 10. 完整实战案例 ===== -->
<h2 id="sec10" class="doc-section-title">10. 完整实战案例 — 天气查询插件</h2>
<p>一个功能完整的天气查询插件，包含命令解析、进度提示、API 调用、格式化返回。</p>

<pre class="doc-code-block"><code><span class="php-tag">&lt;?php</span>
<span class="php-var">$info</span> = [
    <span class="php-str">'name'</span>           =&gt; <span class="php-str">'天气查询'</span>,
    <span class="php-str">'version'</span>        =&gt; <span class="php-str">'1.0.0'</span>,
    <span class="php-str">'author'</span>         =&gt; <span class="php-str">'白屿'</span>,
    <span class="php-str">'desc'</span>           =&gt; <span class="php-str">'查询城市天气，支持中文城市名'</span>,
    <span class="php-str">'command_list'</span>   =&gt; [<span class="php-str">'天气'</span>, <span class="php-str">'weather'</span>, <span class="php-str">'tq'</span>],
];

<span class="php-keyword">function</span> <span class="php-func">has_been_loaded</span>(<span class="php-var">$info</span>) { <span class="php-keyword">return</span> <span class="php-const">true</span>; }
<span class="php-keyword">function</span> <span class="php-func">group</span>(<span class="php-var">$msg</span>, <span class="php-var">$botapi</span>) { <span class="php-keyword">return</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-str">'group'</span>, <span class="php-var">$botapi</span>); }
<span class="php-keyword">function</span> <span class="php-func">C2C</span>(<span class="php-var">$msg</span>, <span class="php-var">$botapi</span>)  { <span class="php-keyword">return</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-str">'private'</span>, <span class="php-var">$botapi</span>); }
<span class="php-keyword">function</span> <span class="php-func">button</span>(<span class="php-var">$e</span>, <span class="php-var">$botapi</span>)  { <span class="php-keyword">return</span> <span class="php-const">null</span>; }

<span class="php-keyword">function</span> <span class="php-func">runPlugin</span>(<span class="php-var">$msg</span>, <span class="php-var">$chatType</span>, <span class="php-var">$botapi</span>) {
    <span class="php-var">$content</span> = <span class="php-func">trim</span>(<span class="php-var">$msg</span>[<span class="php-str">'content'</span>] ?? <span class="php-str">''</span>);

    <span class="php-comment">// 1. 命令匹配</span>
    <span class="php-var">$matched</span> = <span class="php-const">false</span>; <span class="php-var">$city</span> = <span class="php-str">''</span>;
    <span class="php-keyword">foreach</span> ([<span class="php-str">'天气'</span>, <span class="php-str">'weather'</span>, <span class="php-str">'tq'</span>] <span class="php-keyword">as</span> <span class="php-var">$cmd</span>) {
        <span class="php-keyword">if</span> (<span class="php-var">$content</span> === <span class="php-var">$cmd</span>) {
            <span class="php-var">$matched</span> = <span class="php-const">true</span>; <span class="php-var">$city</span> = <span class="php-str">''</span>; <span class="php-keyword">break</span>;
        }
        <span class="php-keyword">if</span> (<span class="php-func">strpos</span>(<span class="php-var">$content</span>, <span class="php-var">$cmd</span> . <span class="php-str">' '</span>) === <span class="php-num">0</span>) {
            <span class="php-var">$matched</span> = <span class="php-const">true</span>;
            <span class="php-var">$city</span> = <span class="php-func">trim</span>(<span class="php-func">mb_substr</span>(<span class="php-var">$content</span>, <span class="php-func">mb_strlen</span>(<span class="php-var">$cmd</span>) + <span class="php-num">1</span>));
            <span class="php-keyword">break</span>;
        }
    }
    <span class="php-keyword">if</span> (!<span class="php-var">$matched</span>) <span class="php-keyword">return</span> <span class="php-const">null</span>;

    <span class="php-comment">// 2. 缺少城市名时返回用法</span>
    <span class="php-keyword">if</span> (<span class="php-func">empty</span>(<span class="php-var">$city</span>)) {
        <span class="php-keyword">return</span> [<span class="php-str">'type'</span> =&gt; <span class="php-str">'text'</span>, <span class="php-str">'content'</span> =&gt; <span class="php-str">'请输入城市名，如：天气 北京'</span>];
    }

    <span class="php-comment">// 3. 先发"查询中"提示（利用主动发消息）</span>
    <span class="php-var">$token</span> = <span class="php-var">$botapi</span>[<span class="php-str">'token'</span>];
    <span class="php-var">$recvId</span> = <span class="php-var">$botapi</span>[<span class="php-str">'recvId'</span>];
    <span class="php-var">$recvType</span> = <span class="php-var">$chatType</span> === <span class="php-str">'group'</span> ? <span class="php-str">'group'</span> : <span class="php-str">'user'</span>;

    <span class="php-func">yunhuSendMessage</span>(<span class="php-var">$token</span>, <span class="php-var">$recvId</span>, <span class="php-str">'🌤 正在查询天气…'</span>, <span class="php-str">'text'</span>, <span class="php-var">$recvType</span>);

    <span class="php-comment">// 4. 调用第三方天气 API</span>
    <span class="php-var">$resp</span> = <span class="php-func">file_get_contents</span>(<span class="php-str">'https://api.example.com/weather?city='</span> . <span class="php-func">urlencode</span>(<span class="php-var">$city</span>), <span class="php-const">false</span>,
        <span class="php-func">stream_context_create</span>([<span class="php-str">'http'</span> =&gt; [<span class="php-str">'timeout'</span> =&gt; <span class="php-num">10</span>]]));
    <span class="php-var">$data</span> = <span class="php-func">json_decode</span>(<span class="php-var">$resp</span>, <span class="php-const">true</span>);

    <span class="php-keyword">if</span> (<span class="php-func">empty</span>(<span class="php-var">$data</span>[<span class="php-str">'success'</span>])) {
        <span class="php-keyword">return</span> [<span class="php-str">'type'</span> =&gt; <span class="php-str">'text'</span>, <span class="php-str">'content'</span> =&gt; <span class="php-str">'❌ 查询失败，请检查城市名'</span>];
    }

    <span class="php-comment">// 5. 格式化返回 Markdown</span>
    <span class="php-var">$text</span> = <span class="php-str">"📍 {$city} 实时天气\n"</span>
        . <span class="php-str">"━━━━━━━━━━\n"</span>
        . <span class="php-str">"🌡 温度：{$data['temp']}°C\n"</span>
        . <span class="php-str">"💧 湿度：{$data['humidity']}%\n"</span>
        . <span class="php-str">"🌬 风速：{$data['wind']}m/s\n"</span>
        . <span class="php-str">"☀️ 天气：{$data['weather']}"</span>;

    <span class="php-keyword">return</span> [<span class="php-str">'type'</span> =&gt; <span class="php-str">'text'</span>, <span class="php-str">'content'</span> =&gt; <span class="php-var">$text</span>];
}</code></pre>

<!-- ===== 11. FAQ ===== -->
<h2 id="sec11" class="doc-section-title">11. 常见 FAQ</h2>

<div class="doc-faq">
    <details>
        <summary><strong>Q1：如何让我的插件在群聊中触发？</strong></summary>
        <p>群聊中发送插件 <code>command_list</code> 中定义的命令即可触发。如命令为 <code>天气</code>，发送 <code>天气 北京</code> 即可。命令匹配不区分大小写。</p>
    </details>
    <details>
        <summary><strong>Q2：命令匹配出错怎么办？</strong></summary>
        <p>用 <code>return array('type'=&gt;'text','content'=&gt;'收到');exit;</code> 直接返回消息，不依赖框架的命令匹配。注意：返回的 content 不能为空，否则会被忽略。</p>
        <p><strong>常见误区</strong>：使用平台内置的「关键词触发」而不是自己匹配命令时，必须确保任何合法输入都能返回非空内容或 null。</p>
    </details>
    <details>
        <summary><strong>Q3：如何上传图片/文件？</strong></summary>
        <p>需先通过上传接口获取 <code>imageKey</code>/<code>fileKey</code>，再作为返回值 <code>content</code> 传回。上传接口：<code>https://chat-go.jwzhd.com/open-apis/v1/file/upload?token=</code>（POST multipart）。</p>
    </details>
    <details>
        <summary><strong>Q4：多个插件命令冲突怎么办？</strong></summary>
        <p>建议每个命令唯一。如需共用 <code>翻译</code> 命令，可以加子命令 <code>翻译中英</code> / <code>翻译中日</code>，在 <code>runPlugin</code> 中用 <code>{翻译}\s+(.*)</code> 正则匹配。平台按插件 ID 顺序依次匹配，一旦命中即返回。</p>
    </details>
    <details>
        <summary><strong>Q5：如何发送多条消息？</strong></summary>
        <p>通过 <code>yunhuSendMessage()</code> 主动发送，不受返回值限制。返回值只决定平台自动回复的那一条消息。如需发送后不自动回复，返回 <code>null</code>。</p>
    </details>
</div>

<!-- ===== 12. 安全加固 ===== -->
<h2 id="sec12" class="doc-section-title">12. 安全加固清单</h2>

<div class="doc-checklist">
    <div class="doc-checklist-item">
        <i class="fas fa-shield-alt"></i>
        <div><strong>Token 安全</strong>：不要将 Token 硬编码在插件代码中（已通过 <code>$botapi['token']</code> 自动传入）。不要将 Token 写入日志、缓存文件。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-database"></i>
        <div><strong>SQL 注入防护</strong>：所有数据库查询必须使用 PDO 预处理语句（<code>prepare + execute</code>），禁止拼接 SQL。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-bug"></i>
        <div><strong>XSS 防护</strong>：输出用户输入到 HTML 消息时，用 <code>htmlspecialchars()</code> 转义。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-lock"></i>
        <div><strong>敏感信息</strong>：API Key、第三方密钥等不要硬编码，建议存入数据库 <code>settings</code> 表。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-file-upload"></i>
        <div><strong>上传防护</strong>：如插件涉及文件上传，验证文件类型和大小（限制 20MB），检查 MIME 类型。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-globe"></i>
        <div><strong>外部 API</strong>：调用第三方 API 时设置超时（≤10s），验证返回数据格式，处理异常情况。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-user-shield"></i>
        <div><strong>权限控制</strong>：涉及管理操作的命令，校验 <code>$msg['sender']['id']</code> 是否为管理员。</div>
    </div>
    <div class="doc-checklist-item">
        <i class="fas fa-ban"></i>
        <div><strong>禁用函数</strong>：不要使用 <code>eval</code>、<code>exec</code>、<code>system</code>、<code>shell_exec</code> 等危险函数。</div>
    </div>
</div>

<!-- ===== 文档底部 ===== -->
<div class="doc-footer">
    <div class="doc-footer-line"></div>
    <p><i class="fas fa-book"></i> 本文档由 <strong><?php echo getSiteName(); ?></strong> 自动生成</p>
    <p>版本 v<?php echo SITE_VERSION; ?> · 更新于 <?php echo date('Y-m'); ?></p>
    <p>如有疑问请联系管理员 · QQ <strong>482171260</strong></p>
</div>

</div><!-- /doc-content -->
</div><!-- /doc-container -->

<!-- ============ 文档页样式 ============ -->
<style>
.doc-container {
    display: flex;
    gap: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

/* 侧边导航 */
.doc-sidebar {
    width: 220px;
    flex-shrink: 0;
    position: sticky;
    top: 20px;
    align-self: flex-start;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    padding: 16px 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.doc-nav-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-muted);
    padding: 0 16px 10px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 6px;
}
.doc-nav-link {
    display: block;
    padding: 7px 16px;
    font-size: 13px;
    color: var(--text);
    text-decoration: none;
    transition: all 0.15s;
    border-left: 3px solid transparent;
}
.doc-nav-link:hover {
    background: rgba(99,102,241,0.06);
    border-left-color: var(--primary);
    color: var(--primary);
}
.doc-nav-link.sub {
    padding-left: 28px;
    font-size: 12px;
    color: var(--text-muted);
}

/* 文档正文 */
.doc-content {
    flex: 1;
    min-width: 0;
    padding-bottom: 40px;
}
.doc-section-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--text);
    margin: 36px 0 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border);
    scroll-margin-top: 20px;
}
.doc-section-title code {
    font-size: 18px;
    background: rgba(99,102,241,0.08);
    padding: 2px 8px;
    border-radius: 6px;
    color: var(--primary);
}
.doc-h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
    margin: 24px 0 10px;
}
.doc-content p {
    color: var(--text);
    line-height: 1.8;
    font-size: 14px;
    margin-bottom: 12px;
}
.doc-content code {
    font-family: var(--font-mono);
    font-size: 13px;
    background: rgba(99,102,241,0.08);
    color: var(--primary);
    padding: 2px 6px;
    border-radius: 4px;
}

/* 代码块 */
.doc-code-block {
    background: #1e293b;
    border-radius: 10px;
    padding: 18px 20px;
    overflow-x: auto;
    margin: 12px 0 20px;
    font-size: 13px;
    line-height: 1.7;
    border: 1px solid #334155;
}
.doc-code-block code {
    background: none;
    color: #e2e8f0;
    padding: 0;
    font-family: 'Fira Code', 'Cascadia Code', Consolas, monospace;
    white-space: pre;
}
/* PHP 语法高亮 */
.php-tag      { color: #c084fc; font-weight: 600; }
.php-comment  { color: #64748b; font-style: italic; }
.php-keyword  { color: #f472b6; }
.php-func     { color: #60a5fa; }
.php-var      { color: #fbbf24; }
.php-str      { color: #86efac; }
.php-num      { color: #fb923c; }
.php-const    { color: #c084fc; }

/* 提示框 */
.doc-callout {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    border-radius: var(--radius-md);
    margin: 14px 0;
    font-size: 13px;
    line-height: 1.7;
}
.doc-callout i { font-size: 18px; margin-top: 2px; flex-shrink: 0; }
.doc-callout-info    { background: rgba(59,130,246,0.08); border-left: 4px solid #3b82f6; }
.doc-callout-info i  { color: #3b82f6; }
.doc-callout-tip     { background: rgba(16,185,129,0.08); border-left: 4px solid #10b981; }
.doc-callout-tip i   { color: #10b981; }
.doc-callout-warning { background: rgba(245,158,11,0.08); border-left: 4px solid #f59e0b; }
.doc-callout-warning i { color: #f59e0b; }
.doc-callout-danger  { background: rgba(239,68,68,0.08); border-left: 4px solid #ef4444; }
.doc-callout-danger i { color: #ef4444; }

/* 表格 */
.doc-table {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0 20px;
    font-size: 13px;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--border);
}
.doc-table th {
    background: var(--bg);
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.doc-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
}
.doc-table tr:last-child td { border-bottom: none; }
.doc-table tr:hover td { background: rgba(99,102,241,0.03); }
.doc-table code {
    font-size: 12px;
}

/* FAQ */
.doc-faq details {
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 14px 16px;
    margin-bottom: 10px;
    background: var(--bg-card);
    transition: border-color 0.2s;
}
.doc-faq details[open] {
    border-color: var(--primary-light);
}
.doc-faq summary {
    cursor: pointer;
    font-size: 14px;
    color: var(--text);
    user-select: none;
}
.doc-faq details[open] summary {
    color: var(--primary);
    margin-bottom: 8px;
}
.doc-faq p {
    font-size: 13px;
    color: var(--text-muted);
    margin: 6px 0 0;
}

/* 安全清单 */
.doc-checklist {
    display: grid;
    gap: 10px;
    margin-top: 12px;
}
.doc-checklist-item {
    display: flex;
    gap: 12px;
    padding: 12px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 13px;
    line-height: 1.6;
    transition: border-color 0.2s;
}
.doc-checklist-item:hover {
    border-color: var(--primary-light);
}
.doc-checklist-item i {
    color: var(--primary);
    font-size: 16px;
    margin-top: 2px;
    flex-shrink: 0;
}

/* 底部 */
.doc-footer {
    text-align: center;
    padding: 30px 0 10px;
    color: var(--text-muted);
    font-size: 13px;
}
.doc-footer-line {
    height: 1px;
    background: var(--border);
    margin-bottom: 20px;
}
.doc-footer p { margin: 4px 0; }

/* 响应式 */
@media (max-width: 900px) {
    .doc-container { flex-direction: column; }
    .doc-sidebar {
        position: static;
        width: 100%;
        max-height: none;
        order: 2;
    }
    .doc-content { order: 1; }
}
</style>

<?php include __DIR__ . '/../includes/load_footer.php'; ?>
