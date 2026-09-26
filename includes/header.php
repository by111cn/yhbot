<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
requireLogin();
$user = getCurrentUser();

// 会话校验：数据库里查不到该用户（账号被删/迁移/损坏），强制退出登录
if (!$user || !is_array($user) || !isset($user['id'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// 角色相关
$userRole = (int)($user['role'] ?? 1);
$isCreatorOrAdmin = ($userRole === 0 || $userRole === 2);

// 获取统计数据用于徽章
$userId = $user['id'];
$botCount = countTable('bots', 'user_id = ?', [$userId]);
$apiCount = $isCreatorOrAdmin ? countTable('apis', 'user_id = ?', [$userId]) : 0;

// 显示用的安全昵称（防止空值/表情导致 mb_substr 异常）
$displayName = trim(($user['nickname'] ?? '') ?: ($user['username'] ?? '用户'));
if ($displayName === '') $displayName = '用户';
$displayInitial = mb_substr($displayName, 0, 1) ?: 'U';

// 角色展示名与徽章样式
$roleLabelMap = [0 => '管理员', 1 => '普通用户', 2 => '创作者'];
$roleLabel = $roleLabelMap[$userRole] ?? '普通用户';
$roleBadgeStyleMap = [
    0 => 'background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;',
    1 => 'background:#e2e8f0;color:#475569;',
    2 => 'background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;',
];
$roleBadgeStyle = $roleBadgeStyleMap[$userRole] ?? $roleBadgeStyleMap[1];

// 待审创作者申请数（管理员侧边栏徽章用）
$pendingCount = 0;
if ($userRole === 0) {
    try { $pendingCount = countTable('creator_applications', 'status = 0', []); } catch (Exception $e) { $pendingCount = 0; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo getSiteName(); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/font-awesome/css/all.min.css?v=6.4.0">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/favicon.ico">
</head>
<body>
    <div class="app-container">
        <!-- 侧边栏 -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <?php $siteLogo = getSetting('site_logo'); ?>
                    <?php if ($siteLogo): ?>
                    <img src="<?php echo h($siteLogo); ?>" class="logo-img" alt="logo">
                    <?php else: ?>
                    <span class="logo-icon"><i class="fas fa-island-tropical"></i></span>
                    <?php endif; ?>
                    <span>白屿云平台</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <!-- 首页（最顶部） -->
                    <li class="<?php echo $activePage === 'home' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/index.php">
                            <i class="fas fa-th-large"></i>
                            <span>仪表盘</span>
                        </a>
                    </li>

                    <!-- 官机管理 -->
                    <li class="<?php echo $activePage === 'bot' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/pages/bot_management.php">
                            <i class="fas fa-crown"></i>
                            <span>官机管理</span>
                            <?php if ($botCount > 0): ?>
                            <span class="nav-badge"><?php echo $botCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- 兑换卡密 -->
                    <li class="<?php echo $activePage === 'redeem' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/pages/redeem.php">
                            <i class="fas fa-gift"></i>
                            <span>兑换卡密</span>
                        </a>
                    </li>

                    <?php if (($user['role'] ?? 1) == 0): ?>
                    <!-- 分隔 -->
                    <li class="sidebar-divider"></li>

                    <!-- 网站管理 -->
                    <li class="nav-dropdown <?php echo in_array($activePage, ['site_info','email_config','oauth_config','plugin_review']) ? 'open' : ''; ?>">
                        <a href="javascript:void(0)" class="dropdown-toggle">
                            <i class="fas fa-cog"></i>
                            <span>网站管理</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="<?php echo $activePage === 'site_info' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/site_info.php">站点信息</a>
                            </li>
                            <li class="<?php echo $activePage === 'email_config' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/email_config.php">邮箱配置</a>
                            </li>
                            <li class="<?php echo $activePage === 'plugin_review' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/plugin_review.php">插件审核</a>
                            </li>
                            <li class="<?php echo $activePage === 'oauth_config' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/oauth_config.php">聚合登录</a>
                            </li>
                        </ul>
                    </li>

                    <!-- 用户管理 -->
                    <li class="nav-dropdown <?php echo in_array($activePage, ['user_list','identity_review','user_groups']) ? 'open' : ''; ?>">
                        <a href="javascript:void(0)" class="dropdown-toggle">
                            <i class="fas fa-users-cog"></i>
                            <span>用户管理</span>
                            <?php if ($pendingCount > 0): ?>
                            <span class="nav-badge" style="background:#f59e0b;color:#fff;"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="<?php echo $activePage === 'user_list' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/user_list.php">用户列表</a>
                            </li>
                            <li class="<?php echo $activePage === 'identity_review' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/identity_review.php">
                                    身份审核
                                    <?php if ($pendingCount > 0): ?>
                                    <span style="color:#f59e0b;font-size:11px;margin-left:4px;"><?php echo $pendingCount; ?>待审</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $activePage === 'user_groups' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/user_groups.php">用户组</a>
                            </li>
                        </ul>
                    </li>

                    <!-- 卡密管理 -->
                    <li class="nav-dropdown <?php echo in_array($activePage, ['cdkey_add','cdkey_manage']) ? 'open' : ''; ?>">
                        <a href="javascript:void(0)" class="dropdown-toggle">
                            <i class="fas fa-key"></i>
                            <span>卡密管理</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="<?php echo $activePage === 'cdkey_add' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/cdkey_add.php">添加卡密</a>
                            </li>
                            <li class="<?php echo $activePage === 'cdkey_manage' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/cdkey_manage.php">卡密管理</a>
                            </li>
                        </ul>
                    </li>

                    <!-- 分隔 -->
                    <li class="sidebar-divider"></li>
                    <?php endif; ?>

                    <!-- 功能设置（插件管理 + 插件市场 + 菜单列表 + API管理） -->
                    <li class="nav-dropdown <?php echo in_array($activePage, ['plugins','plugin_market','menu','api_add','api_list','api_categories','api_share','creator_apply']) ? 'open' : ''; ?>">
                        <a href="javascript:void(0)" class="dropdown-toggle">
                            <i class="fas fa-sliders-h"></i>
                            <span>功能设置</span>
                            <?php if ($apiCount > 0): ?>
                            <span class="nav-badge nav-badge-primary"><?php echo $apiCount; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="<?php echo $activePage === 'plugins' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/plugins.php">插件管理</a>
                            </li>
                            <li class="<?php echo $activePage === 'plugin_market' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/plugin_market.php">插件市场</a>
                            </li>
                            <li class="<?php echo $activePage === 'menu' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/menu_list.php">菜单列表</a>
                            </li>
                            <li class="sidebar-sub-divider"></li>
                            <li class="<?php echo $activePage === 'api_add' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/api_add.php">添加API</a>
                            </li>
                            <li class="<?php echo $activePage === 'api_list' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/api_list.php">API列表</a>
                            </li>
                            <li class="<?php echo $activePage === 'api_categories' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/api_categories.php">分类管理</a>
                            </li>
                            <li class="<?php echo $activePage === 'api_share' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/api_share.php">API分享</a>
                            </li>
                        </ul>
                    </li>

                    <!-- 分隔 -->
                    <li class="sidebar-divider"></li>

                    <!-- 消息管理 -->
                    <li class="nav-dropdown <?php echo in_array($activePage, ['private_chat','group_chat','message_logs']) ? 'open' : ''; ?>">
                        <a href="javascript:void(0)" class="dropdown-toggle">
                            <i class="fas fa-comments"></i>
                            <span>消息管理</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="<?php echo $activePage === 'private_chat' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/private_chat.php">私聊消息</a>
                            </li>
                            <li class="<?php echo $activePage === 'group_chat' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/group_chat.php">群聊消息</a>
                            </li>
                            <li class="<?php echo $activePage === 'message_logs' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>/pages/message_logs.php">消息日志</a>
                            </li>
                        </ul>
                    </li>

                    <!-- 分隔 -->
                    <li class="sidebar-divider"></li>

                    <!-- 个人资料 -->
                    <li class="<?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/pages/profile.php">
                            <i class="fas fa-user-gear"></i>
                            <span>个人资料</span>
                        </a>
                    </li>

                    <!-- 赞赏人员 -->
                    <li class="<?php echo $activePage === 'appreciation' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/pages/appreciation.php">
                            <i class="fas fa-heart"></i>
                            <span>赞赏人员</span>
                        </a>
                    </li>

                    <!-- 插件开发文档 -->
                    <li class="<?php echo $activePage === 'plugin_dev_doc' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/pages/plugin_dev_doc.php">
                            <i class="fas fa-book"></i>
                            <span>插件开发文档</span>
                        </a>
                    </li>

                    <!-- 分隔 -->
                    <li class="sidebar-divider"></li>

                    <!-- 关于系统 -->
                    <li class="<?php echo $activePage === 'about' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/pages/about.php">
                            <i class="fas fa-info-circle"></i>
                            <span>关于系统</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <?php
                $sidebarQQ = getSetting('admin_qq');
                $sidebarGroup = getSetting('group_chat');
            ?>
            <?php if ($sidebarQQ || $sidebarGroup): ?>
            <!-- 侧边栏联系信息 -->
            <div class="sidebar-contact">
                <?php if ($sidebarQQ): ?>
                <a href="tencent://message/?uin=<?php echo h($sidebarQQ); ?>" class="sidebar-contact-item">
                    <i class="fab fa-qq"></i>
                    <span>站长QQ</span>
                </a>
                <?php endif; ?>
                <?php if ($sidebarGroup): ?>
                <?php $groupUrl = strpos($sidebarGroup, 'http') === 0 ? $sidebarGroup : 'https://qun.qq.com/join.html'; ?>
                <a href="<?php echo h($groupUrl); ?>" target="_blank" class="sidebar-contact-item">
                    <i class="fas fa-users"></i>
                    <span>官方群聊</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 侧边栏底部用户信息 -->
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo h($user['avatar']); ?>" alt="头像" style="width:38px;height:38px;min-width:38px;max-width:38px;border-radius:50%;object-fit:cover;display:block;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span class="avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?></span>
                        <?php else: ?>
                            <?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
                        <div class="sidebar-user-role">
                            <span style="padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;<?php echo $roleBadgeStyle; ?>">
                                <?php echo $roleLabel; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- 侧边栏遮罩 -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- 主内容区 -->
        <main class="main-content">
            <header class="top-header">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="breadcrumb">
                        <a href="<?php echo SITE_URL; ?>/index.php"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right" style="font-size:9px;"></i>
                        <span><?php echo isset($pageTitle) ? $pageTitle : '仪表盘'; ?></span>
                    </div>
                    <h2 class="page-title" style="font-size:18px;margin-top:2px;"><?php echo isset($pageTitle) ? $pageTitle : '仪表盘'; ?></h2>
                </div>
                <div class="header-right">
                    <!-- 暗色模式切换 -->
                    <button class="theme-toggle-btn" id="themeToggle" title="切换暗色模式">
                        <i class="fas fa-moon"></i>
                        <i class="fas fa-sun"></i>
                    </button>

                    <!-- 通知中心 -->
                    <button class="notify-btn" id="notifyBtn" title="通知">
                        <i class="fas fa-bell"></i>
                        <span class="notify-dot" id="notifyDot" style="display:none;"></span>
                    </button>

                    <div class="user-dropdown">
                        <button class="user-btn" id="userDropdown">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?php echo h($user['avatar']); ?>" alt="头像" style="width:32px;height:32px;min-width:32px;max-width:32px;border-radius:50%;object-fit:cover;flex-shrink:0;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <span class="user-avatar" style="display:none;"><?php echo htmlspecialchars($displayInitial); ?></span>
                            <?php else: ?>
                                <span class="user-avatar"><?php echo htmlspecialchars($displayInitial); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($displayName); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-content" id="userMenu">
                            <a href="<?php echo SITE_URL; ?>/pages/profile.php">
                                <i class="fas fa-user-gear"></i> 个人资料
                            </a>
                            <div class="divider"></div>
                            <a href="<?php echo SITE_URL; ?>/logout.php">
                                <i class="fas fa-sign-out-alt"></i> 退出登录
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
