<?php
/**
 * 升级脚本 - 数据库结构修复与升级
 * 使用后请删除此文件
 */
require_once 'config.php';

$upgrades = [];
$messages = [];

// 检查并创建 share_codes 表
try {
    $hasTable = db()->query("SHOW TABLES LIKE 'share_codes'")->rowCount() > 0;
    if (!$hasTable) {
        db()->query("CREATE TABLE `share_codes` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `code` varchar(12) NOT NULL COMMENT '分享码(12位字母数字)',
          `data` text NOT NULL COMMENT '分享数据(JSON)',
          `created_by` int(11) NOT NULL COMMENT '创建者ID',
          `used_count` int(11) DEFAULT '0' COMMENT '被使用次数',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `code` (`code`),
          KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API分享码表'");
        $upgrades[] = ['ok' => true, 'msg' => 'share_codes 表创建成功'];
    } else {
        $messages[] = ['info' => 'share_codes 表已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'share_codes 表创建失败: ' . $e->getMessage()];
}

// 检查并添加 avatar 字段
try {
    $col = db()->query("SHOW COLUMNS FROM `bots` LIKE 'avatar'")->fetch();
    if (!$col) {
        db()->query("ALTER TABLE `bots` ADD COLUMN `avatar` varchar(500) DEFAULT NULL COMMENT '机器人头像URL' AFTER `api_callback`");
        $upgrades[] = ['ok' => true, 'msg' => 'avatar 字段添加成功'];
    } else {
        $messages[] = ['info' => 'avatar 字段已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'avatar 字段添加失败: ' . $e->getMessage()];
}

// 检查并创建 oauth_accounts 表
try {
    $hasTable = db()->query("SHOW TABLES LIKE 'oauth_accounts'")->rowCount() > 0;
    if (!$hasTable) {
        db()->query("CREATE TABLE `oauth_accounts` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL COMMENT '绑定的用户ID',
          `provider` varchar(30) NOT NULL COMMENT '平台: qq/wechat/github/google',
          `openid` varchar(128) NOT NULL COMMENT '平台用户唯一标识',
          `unionid` varchar(128) DEFAULT NULL COMMENT 'UnionID(微信)',
          `nickname` varchar(100) DEFAULT NULL COMMENT '平台昵称',
          `avatar` varchar(500) DEFAULT NULL COMMENT '平台头像',
          `access_token` text COMMENT 'Access Token',
          `refresh_token` text COMMENT 'Refresh Token',
          `expires_at` datetime DEFAULT NULL COMMENT 'Token过期时间',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `provider_openid` (`provider`, `openid`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth聚合登录账户表'");
        $upgrades[] = ['ok' => true, 'msg' => 'oauth_accounts 表创建成功'];
    } else {
        $messages[] = ['info' => 'oauth_accounts 表已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'oauth_accounts 表创建失败: ' . $e->getMessage()];
}

// 初始化默认彩虹聚合登录配置
try {
    $cfg = db()->query("SELECT id FROM settings WHERE `key` = 'oauth_config' AND user_id = 0")->fetch();
    if (!$cfg) {
        $defaultConfig = json_encode([
            'enabled'     => 0,
            'connect_url' => 'https://login.az0.cn/connect.php',
            'appid'       => '',
            'appkey'      => '',
            'types'       => ''
        ], JSON_UNESCAPED_UNICODE);
        db()->prepare("INSERT INTO settings (user_id, `key`, `value`) VALUES (0, 'oauth_config', ?)")->execute([$defaultConfig]);
        $upgrades[] = ['ok' => true, 'msg' => '彩虹聚合登录默认配置已创建'];
    } else {
        $messages[] = ['info' => 'oauth_config 已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'oauth_config 初始化失败: ' . $e->getMessage()];
}

// 卡密：添加 max_uses_per_account 字段
try {
    $col = db()->query("SHOW COLUMNS FROM `cdkeys` LIKE 'max_uses_per_account'")->fetch();
    if (!$col) {
        db()->query("ALTER TABLE `cdkeys` ADD COLUMN `max_uses_per_account` int(11) DEFAULT '1' COMMENT '单个账号最多使用次数' AFTER `used_at`");
        $upgrades[] = ['ok' => true, 'msg' => 'cdkeys.max_uses_per_account 字段添加成功'];
    } else {
        $messages[] = ['info' => 'max_uses_per_account 字段已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'max_uses_per_account 字段添加失败: ' . $e->getMessage()];
}

// 卡密使用记录表
try {
    $hasTable = db()->query("SHOW TABLES LIKE 'cdkey_usages'")->rowCount() > 0;
    if (!$hasTable) {
        db()->query("CREATE TABLE `cdkey_usages` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `cdkey_id` int(11) unsigned NOT NULL COMMENT '卡密ID',
          `user_id` int(11) NOT NULL COMMENT '用户ID',
          `use_count` int(11) DEFAULT '1' COMMENT '该账号使用次数',
          `first_used_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '首次使用时间',
          `last_used_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最近使用时间',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_cdkey_user` (`cdkey_id`, `user_id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密使用记录表'");
        $upgrades[] = ['ok' => true, 'msg' => 'cdkey_usages 表创建成功'];
    } else {
        $messages[] = ['info' => 'cdkey_usages 表已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'cdkey_usages 表创建失败: ' . $e->getMessage()];
}

// 修复各表 id 列缺少 PRIMARY KEY / AUTO_INCREMENT 的问题
$tablesToFix = ['apis', 'messages', 'message_logs'];
foreach ($tablesToFix as $table) {
    try {
        $colInfo = db()->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'")->fetch();
        if (!$colInfo) {
            $messages[] = ['info' => "{$table}.id 列不存在，跳过"];
            continue;
        }
        $needPK = ($colInfo['Key'] ?? '') !== 'PRI';
        $needAI = stripos($colInfo['Extra'] ?? '', 'auto_increment') === false;

        if ($needPK) {
            // 先检查是否已有主键（可能在其他列上）
            $pkResult = db()->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll();
            if (count($pkResult) > 0) {
                $upgrades[] = ['ok' => false, 'msg' => "{$table} 已有其他主键，请手动修复 id 列"];
                continue;
            }
            // 没有主键：先加 PRIMARY KEY
            db()->query("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
            $upgrades[] = ['ok' => true, 'msg' => "{$table}.id PRIMARY KEY 添加成功"];
            $needPK = false; // 已修复
        }

        if ($needAI && !$needPK) {
            db()->query("ALTER TABLE `{$table}` MODIFY `id` int(11) unsigned NOT NULL AUTO_INCREMENT");
            $upgrades[] = ['ok' => true, 'msg' => "{$table}.id AUTO_INCREMENT 修复成功"];
        } elseif (!$needAI && !$needPK) {
            $messages[] = ['info' => "{$table}.id 已正常，跳过"];
        }
    } catch (Exception $e) {
        $upgrades[] = ['ok' => false, 'msg' => "{$table} 修复失败: " . $e->getMessage()];
    }
}

// 检查并升级 plugins 表（从 HTTP 代理插件升级为 PHP BotAPI 插件模型）
try {
    $hasTable = db()->query("SHOW TABLES LIKE 'plugins'")->rowCount() > 0;
    if (!$hasTable) {
        // 全新安装：创建新表
        db()->query("CREATE TABLE `plugins` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL COMMENT '创建者用户ID',
          `bot_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联机器人ID',
          `name` varchar(100) NOT NULL COMMENT '插件名称',
          `author` varchar(50) DEFAULT '' COMMENT '作者',
          `version` varchar(20) DEFAULT '1.0.0' COMMENT '版本号',
          `desc` text COMMENT '插件说明',
          `command_list` text COMMENT '命令列表(JSON数组)',
          `plugin_code` longtext COMMENT '插件PHP源码',
          `plugin_file` varchar(255) DEFAULT NULL COMMENT '上传的.php文件路径',
          `settings_schema` text COMMENT '设置页面设计器(JSON)',
          `settings_values` text COMMENT '已保存的设置参数(JSON)',
          `enabled` tinyint(1) DEFAULT 1 COMMENT '是否启用',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `bot_id` (`bot_id`),
          KEY `enabled` (`enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表'");
        $upgrades[] = ['ok' => true, 'msg' => 'plugins 表创建成功'];
    } else {
        // 检查是否为旧表结构（有 plugin_key 字段说明是旧版）
        $hasPluginKey = db()->query("SHOW COLUMNS FROM `plugins` LIKE 'plugin_key'")->rowCount() > 0;
        if ($hasPluginKey) {
            // 备份旧表
            db()->query("RENAME TABLE `plugins` TO `plugins_old_backup`");
            // 创建新表
            db()->query("CREATE TABLE `plugins` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL COMMENT '创建者用户ID',
              `bot_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联机器人ID',
              `name` varchar(100) NOT NULL COMMENT '插件名称',
              `author` varchar(50) DEFAULT '' COMMENT '作者',
              `version` varchar(20) DEFAULT '1.0.0' COMMENT '版本号',
              `desc` text COMMENT '插件说明',
              `command_list` text COMMENT '命令列表(JSON数组)',
            `plugin_code` longtext COMMENT '插件PHP源码',
            `plugin_file` varchar(255) DEFAULT NULL COMMENT '上传的.php文件路径',
              `settings_schema` text COMMENT '设置页面设计器(JSON)',
              `settings_values` text COMMENT '已保存的设置参数(JSON)',
              `enabled` tinyint(1) DEFAULT 1 COMMENT '是否启用',
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `user_id` (`user_id`),
              KEY `bot_id` (`bot_id`),
              KEY `enabled` (`enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表'");
            $upgrades[] = ['ok' => true, 'msg' => 'plugins 表已升级为 PHP BotAPI 插件模型（旧表备份为 plugins_old_backup）'];
        } else {
            $messages[] = ['info' => 'plugins 表已是新版结构，跳过'];
        }
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'plugins 表升级失败: ' . $e->getMessage()];
}

// 检查 plugins 表是否有 market_status 字段（插件市场）
try {
    $hasMarket = db()->query("SHOW COLUMNS FROM `plugins` LIKE 'market_status'")->rowCount() > 0;
    if (!$hasMarket) {
        db()->query("ALTER TABLE `plugins` ADD `market_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '市场状态: 0=私有 1=待审核 2=已上架' AFTER `enabled`");
        $upgrades[] = ['ok' => true, 'msg' => 'plugins 表新增 market_status 字段成功'];
    } else {
        $messages[] = ['info' => 'market_status 字段已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'market_status 字段添加失败: ' . $e->getMessage()];
}

// 用户角色扩展：原 role=0管理员 1普通用户；增加 role=2 创作者
try {
    $roleCol = db()->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
    if ($roleCol) {
        // 确保默认值仍为 1（普通用户），兼容旧结构
        db()->query("ALTER TABLE `users` MODIFY COLUMN `role` tinyint(1) NOT NULL DEFAULT 1 COMMENT '角色: 0=管理员 1=普通用户 2=创作者'");
        $upgrades[] = ['ok' => true, 'msg' => 'users.role 列已扩展为三档（0管理员/1普通用户/2创作者）'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'users.role 扩展失败: ' . $e->getMessage()];
}

// 创作者申请表
try {
    $hasTable = db()->query("SHOW TABLES LIKE 'creator_applications'")->rowCount() > 0;
    if (!$hasTable) {
        db()->query("CREATE TABLE `creator_applications` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL COMMENT '申请用户ID',
          `reason` text COMMENT '申请理由',
          `portfolio` text COMMENT '作品/经验/联系方式',
          `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=通过 2=拒绝',
          `reviewer_id` int(11) DEFAULT NULL COMMENT '审核人ID',
          `review_note` varchar(255) DEFAULT NULL COMMENT '审核备注',
          `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='创作者申请表'");
        $upgrades[] = ['ok' => true, 'msg' => 'creator_applications 表创建成功'];
    } else {
        $messages[] = ['info' => 'creator_applications 表已存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'creator_applications 表创建失败: ' . $e->getMessage()];
}

// 确保管理员账号全局唯一（最多一个 role=0）：保留 id 最小的管理员，其余降级为创作者(role=2)
try {
    $admins = db()->query("SELECT id FROM users WHERE role = 0 ORDER BY id ASC")->fetchAll();
    if (count($admins) > 1) {
        $firstId = (int)$admins[0]['id'];
        $ids = [];
        foreach (array_slice($admins, 1) as $a) $ids[] = (int)$a['id'];
        $in = implode(',', $ids);
        db()->query("UPDATE users SET role = 2 WHERE id IN ($in) AND role = 0");
        $upgrades[] = ['ok' => true, 'msg' => '已保证管理员唯一性：id=' . $firstId . ' 保留为管理员，其余 ' . count($ids) . ' 位降级为创作者'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => '管理员唯一性修正失败: ' . $e->getMessage()];
}

// 移除 WebSocket connection_mode 字段（已废弃）
try {
    $hasMode = db()->query("SHOW COLUMNS FROM `bots` LIKE 'connection_mode'")->rowCount() > 0;
    if ($hasMode) {
        db()->query("ALTER TABLE `bots` DROP COLUMN `connection_mode`");
        $upgrades[] = ['ok' => true, 'msg' => '已移除废弃的 connection_mode 字段'];
    } else {
        $messages[] = ['info' => 'connection_mode 字段不存在，跳过'];
    }
} catch (Exception $e) {
    $upgrades[] = ['ok' => false, 'msg' => 'connection_mode 字段移除失败: ' . $e->getMessage()];
}
// 兼容旧升级：清理旧字段（如有）
try {
    $hasWs = db()->query("SHOW COLUMNS FROM `bots` LIKE 'ws_enabled'")->rowCount() > 0;
    if ($hasWs) {
        $messages[] = ['info' => '检测到旧 ws_enabled 字段，保留不动（不再使用）'];
    }
} catch (Exception $e) {}

// 汇总
$allOk = true;
foreach ($upgrades as $u) {
    if (!$u['ok']) $allOk = false;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>数据库升级</title>
    <style>
        body{font-family:sans-serif;max-width:600px;margin:60px auto;padding:20px;background:#f5f5f5;}
        .box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
        h2{color:#333;margin-top:0;}
        .success{color:#10b981;}
        .error{color:#ef4444;}
        .info{color:#6366f1;}
    </style>
</head>
<body>
<div class="box">
    <h2>数据库升级</h2>
    <?php foreach ($upgrades as $u): ?>
        <p class="<?= $u['ok'] ? 'success' : 'error' ?>">
            <?= $u['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($u['msg']) ?>
        </p>
    <?php endforeach; ?>
    <?php foreach ($messages as $m): ?>
        <p style="color:#6366f1;">ℹ <?= htmlspecialchars($m['info']) ?></p>
    <?php endforeach; ?>
    <?php if ($allOk): ?>
        <p style="margin-top:20px;"><strong>升级完成！</strong> 请 <a href="index.php">回到首页</a>。</p>
        <p style="color:#999;font-size:12px;">建议删除 upgrade.php 文件以保安全。</p>
    <?php endif; ?>
</div>
</body>
</html>
