<?php
/**
 * 白屿云平台 BotAPI - 手机端头部模板
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
requireLogin();
$user = getCurrentUser();

$userId = $_SESSION['user_id'];
$userRole = (int)($user['role'] ?? 1);
$isCreatorOrAdmin = ($userRole === 0 || $userRole === 2);
$botCount = countTable('bots', 'user_id = ?', [$userId]);
$apiCount = $isCreatorOrAdmin ? countTable('apis', 'user_id = ?', [$userId]) : 0;

$roleLabelMap = [0 => '管理员', 1 => '普通用户', 2 => '创作者'];
$roleLabel = $roleLabelMap[$userRole] ?? '普通用户';

// 待审创作者申请数
$pendingCount = 0;
if ($userRole === 0) {
    try { $pendingCount = countTable('creator_applications', 'status = 0', []); } catch (Exception $e) { $pendingCount = 0; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo getSiteName(); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/mobile.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/font-awesome/css/all.min.css?v=6.4.0">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/favicon.ico">
</head>
<body class="mobile-app">
    <div class="app-container">

        <!-- 手机端顶栏 -->
        <div class="mobile-top-bar">
            <button class="btn-icon-circle" id="mobileMenuBtn" aria-label="菜单">
                <i class="fas fa-bars"></i>
            </button>
            <div class="brand">
                <?php $siteLogo = getSetting('site_logo'); ?>
                <?php if ($siteLogo): ?>
                <img src="<?php echo h($siteLogo); ?>" class="brand-img" alt="logo">
                <?php else: ?>
                <span class="brand-icon"><i class="fas fa-island-tropical"></i></span>
                <?php endif; ?>
                <span>白屿云平台</span>
            </div>
            <div class="header-actions">
                <button class="btn-icon-circle" id="mobileThemeToggle" aria-label="切换主题">
                    <i class="fas fa-moon"></i>
                    <i class="fas fa-sun" style="display:none;"></i>
                </button>
                <a href="<?php echo SITE_URL; ?>/pages/profile.php" class="btn-icon-circle" aria-label="个人资料">
                    <i class="fas fa-user-circle"></i>
                </a>
            </div>
        </div>

        <!-- 侧滑抽屉菜单 -->
        <div class="mobile-drawer-overlay" id="mobileDrawerOverlay"></div>
        <div class="mobile-drawer" id="mobileDrawer">
            <div class="drawer-header">
                <div class="drawer-user">
                    <div class="drawer-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo h($user['avatar']); ?>" alt="头像" style="width:48px;height:48px;min-width:48px;max-width:48px;border-radius:50%;object-fit:cover;display:block;" onerror="this.style.display='none';this.parentElement.querySelector('.avatar-fallback').style.display='flex';">
                            <span class="avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?></span>
                        <?php else: ?>
                            <?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="drawer-name"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
                        <div class="drawer-role" style="font-size:12px;">
                            <?php echo $roleLabel; ?>
                        </div>
                    </div>
                </div>
                <button class="drawer-close" id="mobileDrawerClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="drawer-nav">
                <!-- 仪表盘（最顶部） -->
                <a href="<?php echo SITE_URL; ?>/index.php" class="<?php echo ($activePage ?? '') === 'home' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-th-large"></i> 仪表盘
                </a>

                <!-- 官机管理 -->
                <a href="<?php echo SITE_URL; ?>/pages/bot_management.php" class="<?php echo ($activePage ?? '') === 'bot' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-crown"></i> 官机管理
                    <?php if ($botCount > 0): ?>
                    <span style="margin-left:auto;background:rgba(99,102,241,0.3);color:var(--primary-light);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600;"><?php echo $botCount; ?></span>
                    <?php endif; ?>
                </a>

                <!-- 兑换卡密 -->
                <a href="<?php echo SITE_URL; ?>/pages/redeem.php" class="<?php echo ($activePage ?? '') === 'redeem' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-gift"></i> 兑换卡密
                </a>

                <?php if (($user['role'] ?? 1) == 0): ?>
                <div class="drawer-divider"></div>
                <div class="drawer-section-title">网站管理</div>
                <a href="<?php echo SITE_URL; ?>/pages/site_info.php" class="<?php echo ($activePage ?? '') === 'site_info' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-info-circle"></i> 站点信息
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/email_config.php" class="<?php echo ($activePage ?? '') === 'email_config' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-envelope"></i> 邮箱配置
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/plugin_review.php" class="<?php echo ($activePage ?? '') === 'plugin_review' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-check-double"></i> 插件审核
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/oauth_config.php" class="<?php echo ($activePage ?? '') === 'oauth_config' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-link"></i> 聚合登录
                </a>

                <div class="drawer-divider"></div>
                <div class="drawer-section-title">用户管理<?php if ($pendingCount > 0): ?><span style="background:#f59e0b;color:#fff;padding:1px 6px;border-radius:8px;font-size:10px;margin-left:6px;"><?php echo $pendingCount; ?></span><?php endif; ?></div>
                <a href="<?php echo SITE_URL; ?>/pages/user_list.php" class="<?php echo ($activePage ?? '') === 'user_list' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-list-alt"></i> 用户列表
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/identity_review.php" class="<?php echo ($activePage ?? '') === 'identity_review' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-user-check"></i> 身份审核
                    <?php if ($pendingCount > 0): ?><span style="margin-left:auto;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:8px;font-size:10px;"><?php echo $pendingCount; ?>待审</span><?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/user_groups.php" class="<?php echo ($activePage ?? '') === 'user_groups' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> 用户组
                </a>

                <div class="drawer-divider"></div>
                <div class="drawer-section-title">卡密管理</div>
                <a href="<?php echo SITE_URL; ?>/pages/cdkey_add.php" class="<?php echo ($activePage ?? '') === 'cdkey_add' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> 添加卡密
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/cdkey_manage.php" class="<?php echo ($activePage ?? '') === 'cdkey_manage' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-key"></i> 卡密管理
                </a>

                <div class="drawer-divider"></div>
                <?php endif; ?>

                <!-- 功能设置（插件管理 + 插件市场 + 菜单列表 + API管理） -->
                <div class="drawer-divider"></div>
                <div class="drawer-section-title">功能设置</div>
                <a href="<?php echo SITE_URL; ?>/pages/plugins.php" class="<?php echo ($activePage ?? '') === 'plugins' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-puzzle-piece"></i> 插件管理
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/plugin_market.php" class="<?php echo ($activePage ?? '') === 'plugin_market' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-store"></i> 插件市场
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/menu_list.php" class="<?php echo ($activePage ?? '') === 'menu' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> 菜单列表
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/api_add.php" class="<?php echo ($activePage ?? '') === 'api_add' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> 添加API
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/api_list.php" class="<?php echo ($activePage ?? '') === 'api_list' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-list"></i> API列表
                    <?php if ($apiCount > 0): ?>
                    <span style="margin-left:auto;background:rgba(99,102,241,0.3);color:var(--primary-light);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600;"><?php echo $apiCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/api_categories.php" class="<?php echo ($activePage ?? '') === 'api_categories' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-folder"></i> 分类管理
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/api_share.php" class="<?php echo ($activePage ?? '') === 'api_share' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-share-alt"></i> API分享
                </a>

                <div class="drawer-divider"></div>
                <div class="drawer-section-title">消息管理</div>
                <a href="<?php echo SITE_URL; ?>/pages/private_chat.php" class="<?php echo ($activePage ?? '') === 'private_chat' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-user-friends"></i> 私聊消息
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/group_chat.php" class="<?php echo ($activePage ?? '') === 'group_chat' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-users"></i> 群聊消息
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/message_logs.php" class="<?php echo ($activePage ?? '') === 'message_logs' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-history"></i> 消息日志
                </a>

                <div class="drawer-divider"></div>

                <!-- 个人资料 -->
                <a href="<?php echo SITE_URL; ?>/pages/profile.php" class="<?php echo ($activePage ?? '') === 'profile' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-user-gear"></i> 个人资料
                </a>

                <!-- 赞赏人员 -->
                <a href="<?php echo SITE_URL; ?>/pages/appreciation.php" class="<?php echo ($activePage ?? '') === 'appreciation' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-heart"></i> 赞赏人员
                </a>

                <!-- 插件开发文档 -->
                <a href="<?php echo SITE_URL; ?>/pages/plugin_dev_doc.php" class="<?php echo ($activePage ?? '') === 'plugin_dev_doc' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-book"></i> 插件开发文档
                </a>

                <div class="drawer-divider"></div>

                <!-- 关于系统 -->
                <a href="<?php echo SITE_URL; ?>/pages/about.php" class="<?php echo ($activePage ?? '') === 'about' ? 'drawer-active' : ''; ?>">
                    <i class="fas fa-info-circle"></i> 关于系统
                </a>
            </nav>

            <?php
                $sidebarQQ = getSetting('admin_qq');
                $sidebarGroup = getSetting('group_chat');
            ?>
            <?php if ($sidebarQQ || $sidebarGroup): ?>
            <div class="drawer-contact">
                <?php if ($sidebarQQ): ?>
                <a href="tencent://message/?uin=<?php echo h($sidebarQQ); ?>" class="drawer-contact-item">
                    <i class="fab fa-qq"></i> 站长QQ
                </a>
                <?php endif; ?>
                <?php if ($sidebarGroup): ?>
                <?php $groupUrl = strpos($sidebarGroup, 'http') === 0 ? $sidebarGroup : 'https://qun.qq.com/join.html'; ?>
                <a href="<?php echo h($groupUrl); ?>" target="_blank" class="drawer-contact-item">
                    <i class="fas fa-users"></i> 官方群聊
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="drawer-footer">
                <div class="drawer-user-info">
                    <div class="drawer-user-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo h($user['avatar']); ?>" alt="头像" style="width:40px;height:40px;min-width:40px;max-width:40px;border-radius:50%;object-fit:cover;display:block;" onerror="this.style.display='none';this.parentElement.querySelector('.avatar-fallback').style.display='flex';">
                            <span class="avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?></span>
                        <?php else: ?>
                            <?php echo mb_substr($user['nickname'] ?: $user['username'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div class="drawer-user-detail">
                        <div class="drawer-user-name"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
                        <div class="drawer-user-role" style="font-size:12px;"><?php echo $roleLabel; ?></div>
                    </div>
                </div>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="drawer-logout">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            </div>
        </div>

        <!-- 主内容区 -->
        <main class="main-content">
            <div class="content-wrapper">
