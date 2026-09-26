-- 白屿云平台 BotAPI 管理系统 数据库结构
-- 版本: 1.0.0

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 用户表
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `role` tinyint(1) NOT NULL DEFAULT '1' COMMENT '角色: 0=管理员(唯一), 1=普通用户, 2=创作者',
  `quota` int(11) DEFAULT '0' COMMENT '机器人额度, 管理员不受限制',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 机器人表
DROP TABLE IF EXISTS `bots`;
CREATE TABLE `bots` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '所属用户ID',
  `name` varchar(100) NOT NULL COMMENT '机器人名称',
  `token` varchar(255) NOT NULL COMMENT '机器人Token',
  `callback_url` varchar(255) DEFAULT NULL COMMENT '回调地址',
  `owner_id` varchar(50) DEFAULT NULL COMMENT '主人ID',
  `owner_name` varchar(50) DEFAULT NULL COMMENT '主人名称',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态: 0=关闭, 1=开启',
  `welcome_msg` text COMMENT '欢迎消息',
  `unknown_reply` text COMMENT '未知指令回复',
  `api_callback` varchar(255) DEFAULT NULL COMMENT 'API回调地址',
  `avatar` varchar(500) DEFAULT NULL COMMENT '机器人头像URL',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='机器人表';

-- API分类表
DROP TABLE IF EXISTS `api_categories`;
CREATE TABLE `api_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID, 0=系统默认',
  `name` varchar(50) NOT NULL COMMENT '分类名称',
  `icon` varchar(50) DEFAULT 'fa-circle' COMMENT '图标',
  `color` varchar(20) DEFAULT '#4f46e5' COMMENT '颜色',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API分类表';

-- API接口表
DROP TABLE IF EXISTS `apis`;
CREATE TABLE `apis` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `bot_id` int(11) DEFAULT '0' COMMENT '机器人ID',
  `category_id` int(11) DEFAULT '0' COMMENT '分类ID',
  `name` varchar(100) NOT NULL COMMENT 'API名称',
  `command` varchar(100) NOT NULL COMMENT '触发指令',
  `description` text COMMENT '功能描述',
  `api_url` varchar(500) NOT NULL COMMENT 'API地址',
  `method` varchar(10) DEFAULT 'GET' COMMENT '请求方式: GET/POST',
  `send_type` varchar(20) DEFAULT 'text' COMMENT '发送类型: text/image/voice/video',
  `param_split` varchar(20) DEFAULT ' ' COMMENT '参数分割符',
  `trigger_mode` varchar(20) DEFAULT 'exact' COMMENT '触发模式: exact精准/fuzzy模糊/left左边/right右边',
  `pre_content` text COMMENT '调用前发送内容',
  `response_template` text COMMENT '响应模板',
  `headers` text COMMENT '自定义Header(JSON)',
  `body` text COMMENT '自定义Body(JSON)',
  `timeout` int(11) DEFAULT '10' COMMENT '超时时间(秒)',
  `cache_time` int(11) DEFAULT '0' COMMENT '缓存时间(秒)',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态: 0=关闭, 1=开启',
  `is_public` tinyint(1) DEFAULT '0' COMMENT '是否公开: 0=私有, 1=公开',
  `use_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  KEY `command` (`command`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API接口表';

-- 菜单表
DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `bot_id` int(11) NOT NULL COMMENT '机器人ID',
  `name` varchar(50) NOT NULL COMMENT '菜单名称',
  `type` varchar(20) DEFAULT 'text' COMMENT '菜单类型: text/link/api',
  `content` text COMMENT '菜单内容',
  `apis` text COMMENT '关联API列表(JSON)',
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜单表';

-- 模板表
DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `type` varchar(20) DEFAULT 'reply' COMMENT '模板类型: reply/welcome/unknown',
  `content` text NOT NULL COMMENT '模板内容',
  `variables` text COMMENT '可用变量(JSON)',
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模板表';

-- 消息表
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` int(11) NOT NULL COMMENT '机器人ID',
  `chat_type` varchar(20) DEFAULT 'private' COMMENT '聊天类型: private/group',
  `chat_id` varchar(50) NOT NULL COMMENT '聊天ID',
  `from_id` varchar(50) NOT NULL COMMENT '发送者ID',
  `from_name` varchar(100) DEFAULT NULL COMMENT '发送者名称',
  `content` text COMMENT '消息内容',
  `msg_type` varchar(20) DEFAULT 'text' COMMENT '消息类型',
  `extra` text COMMENT '额外信息(JSON)',
  `is_reply` tinyint(1) DEFAULT '0' COMMENT '是否已回复',
  `reply_content` text COMMENT '回复内容',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`),
  KEY `chat_type` (`chat_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消息表';

-- 消息日志表
DROP TABLE IF EXISTS `message_logs`;
CREATE TABLE `message_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` int(11) NOT NULL COMMENT '机器人ID',
  `type` varchar(20) DEFAULT 'receive' COMMENT '类型: receive/send',
  `content` text COMMENT '内容',
  `from_id` varchar(50) DEFAULT NULL COMMENT '来源ID',
  `from_name` varchar(100) DEFAULT NULL COMMENT '来源名称',
  `extra` text COMMENT '额外信息',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消息日志表';

-- 设置表
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `key` varchar(50) NOT NULL,
  `value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_key` (`user_id`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='设置表';

-- 卡密表
DROP TABLE IF EXISTS `cdkeys`;
CREATE TABLE `cdkeys` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cdkey` varchar(50) NOT NULL COMMENT '卡密',
  `type` varchar(20) DEFAULT 'trial' COMMENT '类型: trial/day/week/month/year/permanent',
  `duration_days` int(11) DEFAULT '0' COMMENT '有效天数, 0=永久',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态: 0=未使用, 1=已使用, 2=已禁用',
  `used_by` int(11) DEFAULT NULL COMMENT '最后使用者ID',
  `used_at` datetime DEFAULT NULL COMMENT '最后使用时间',
  `max_uses_per_account` int(11) DEFAULT '1' COMMENT '单个账号最多使用次数',
  `created_by` int(11) NOT NULL COMMENT '创建者ID',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cdkey` (`cdkey`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密表';

-- 卡密使用记录表（跟踪每个账号对每个卡密的使用次数）
DROP TABLE IF EXISTS `cdkey_usages`;
CREATE TABLE `cdkey_usages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cdkey_id` int(11) unsigned NOT NULL COMMENT '卡密ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `use_count` int(11) DEFAULT '1' COMMENT '该账号使用次数',
  `first_used_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '首次使用时间',
  `last_used_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最近使用时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cdkey_user` (`cdkey_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密使用记录表';

-- 插入默认分类数据（已移除默认分类，由用户自行创建）
-- INSERT INTO `api_categories` (`id`, `user_id`, `name`, `icon`, `color`, `sort_order`) VALUES
-- (1, 0, '解析', 'fa-link', '#4f46e5', 1),
-- (2, 0, '查询', 'fa-search', '#10b981', 2),
-- (3, 0, '娱乐', 'fa-gamepad', '#f59e0b', 3),
-- (4, 0, '工具', 'fa-wrench', '#6b7280', 4),
-- (5, 0, '图片', 'fa-image', '#ec4899', 5);

-- 分享码表
DROP TABLE IF EXISTS `share_codes`;
CREATE TABLE `share_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(12) NOT NULL COMMENT '分享码(12位字母数字)',
  `data` text NOT NULL COMMENT '分享数据(JSON)',
  `created_by` int(11) NOT NULL COMMENT '创建者ID',
  `used_count` int(11) DEFAULT '0' COMMENT '被使用次数',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API分享码表';

-- OAuth聚合登录账户表
DROP TABLE IF EXISTS `oauth_accounts`;
CREATE TABLE `oauth_accounts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth聚合登录账户表';

-- 插件表（PHP BotAPI 插件）
DROP TABLE IF EXISTS `plugins`;
CREATE TABLE `plugins` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表';

-- 创作者申请表
DROP TABLE IF EXISTS `creator_applications`;
CREATE TABLE `creator_applications` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='创作者申请表';

SET FOREIGN_KEY_CHECKS = 1;
