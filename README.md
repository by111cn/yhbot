# 白屿云平台 BotAPI 管理系统

一款基于 PHP + MySQL 构建的**云湖机器人官方框架**，仿照截图中的 BotAPI 管理后台设计，支持多机器人管理、API 接口配置、菜单管理、模板设置、消息日志等功能。

## 功能特性

- 首页仪表盘：统计 API 数量、机器人数量、消息日志、今日消息
- 客机管理：添加/删除/编辑机器人，设置主人，复制回调地址，启用/禁用
- API 管理：添加 API 接口、分类管理、API 列表、API 分享广场
- 菜单列表：为机器人配置菜单，支持关联 API
- 机器人设置：基础设置、欢迎消息、未知指令回复、高级配置
- 模板设置：回复模板、欢迎模板、未知指令模板管理
- 消息管理：私聊/群聊消息管理，支持发送消息
- 消息日志：查看机器人收发消息日志，支持清空
- 个人资料：修改昵称、邮箱、密码

## 环境要求

- PHP 7.4+ (推荐 8.0+)
- MySQL 5.7+ 或 MariaDB 10.3+
- Apache/Nginx 服务器
- 支持 PDO MySQL 扩展
- 支持 cURL 扩展
- 支持 Session 扩展

## 安装步骤

1. 将项目目录上传到你的网站根目录

2. 确保目录有写入权限（安装时会自动写入 `config.php`）

3. 访问安装向导：
   ```
   http://你的域名/install.php
   ```

4. 填写数据库信息和管理员账号，点击安装

5. 安装完成后，**删除 `install.php` 文件** 以确保安全

6. 访问后台登录：
   ```
   http://你的域名/login.php
   ```

## 目录结构

```
.
├── config.php              # 数据库配置文件（安装后生成）
├── functions.php           # 公共函数库
├── index.php               # 首页仪表盘
├── login.php               # 登录页面
├── register.php            # 注册页面
├── logout.php              # 退出登录
├── install.php             # 安装向导
├── upgrade.php             # 数据库升级脚本
├── includes/
│   ├── header.php          # 公共头部模板
│   ├── load_header.php     # 加载头部
│   ├── load_footer.php     # 加载尾部
│   ├── footer.php          # 公共尾部模板
│   ├── mobile_header.php   # 移动端头部
│   └── sidebar.php         # 侧边栏导航
├── pages/
│   ├── bot_management.php  # 客机管理
│   ├── api_add.php         # 添加/编辑 API
│   ├── api_categories.php  # 分类管理
│   ├── api_list.php        # API 列表
│   ├── api_share.php       # API 分享广场
│   ├── menu_list.php       # 菜单列表
│   ├── robot_settings.php  # 机器人设置
│   ├── template_settings.php # 模板设置
│   ├── msg_list.php        # 消息管理
│   ├── message_logs.php    # 消息日志
│   └── profile.php         # 个人资料
├── api/
│   ├── bot.php             # 机器人 AJAX 接口
│   ├── api.php             # API 管理 AJAX 接口
│   ├── menu.php            # 菜单 AJAX 接口
│   ├── category.php        # 分类 AJAX 接口
│   ├── message.php         # 消息 AJAX 接口
│   └── webhook.php         # 云湖机器人 Webhook 回调
├── assets/
│   ├── css/style.css       # 全局样式
│   └── js/main.js          # 公共脚本
└── sql/
    └── database.sql        # 数据库结构
```

## 使用说明

### 1. 添加机器人

进入「客机管理」，点击「添加机器人」，填写：
- **名称**：机器人名称
- **Token**：云湖机器人后台获取的 Token
- **回调地址**：可选，自定义回调地址

### 2. 配置 Webhook

在云湖机器人后台，将回调地址设置为：
```
http://你的域名/api/webhook.php?bot=机器人ID
```

例如：
```
http://example.com/api/webhook.php?bot=1
```

### 3. 添加 API 接口

进入「API管理」→「添加API」，填写：
- **功能指令**：触发指令，如 `/天气` 或 `天气`
- **API地址**：第三方接口地址，可用变量 `{0}`, `{1}`, `{user_id}`, `{chat_id}`
- **触发模式**：精确触发、前缀触发、正则匹配
- **发送类型**：文本、图片、语音、视频、Markdown

### 4. 配置菜单

进入「菜单列表」，添加菜单项，可关联多个 API 指令，方便用户通过菜单选择功能。

## API 地址变量说明

在 API 地址中可以使用以下变量：
- `{0}`, `{1}`, `{2}` ... 用户传入的参数
- `{user_id}` 发送者ID
- `{chat_id}` 聊天ID
- `{bot_id}` 机器人ID

## 模板变量说明

在模板和回复中可以使用以下变量：
- `{user_name}` 用户名称
- `{bot_name}` 机器人名称
- `{command}` 触发指令
- `{time}` 当前时间
- `{date}` 当前日期
- `{result}` API 返回结果

## 截图功能对照

| 截图功能 | 对应文件/页面 |
|---------|-------------|
| 首页统计 | `index.php` |
| 客机管理 | `pages/bot_management.php` |
| 添加API | `pages/api_add.php` |
| 分类管理 | `pages/api_categories.php` |
| API列表 | `pages/api_list.php` |
| 菜单列表 | `pages/menu_list.php` |
| 消息管理 | `pages/msg_list.php` |
| 消息日志 | `pages/message_logs.php` |
| 个人资料 | `pages/profile.php` |

## 安全提示

- 安装完成后请删除 `install.php`
- 定期修改管理员密码
- 不要将数据库密码泄露给他人
- 建议使用 HTTPS 部署

## 开源协议

MIT License

## 更新日志

### v1.0.0
- 正式版发布
