<?php
/**
 * 系统内置插件：管理员审核
 * 功能：插件审核 + 创作者审核（仅管理员私聊可用）
 * 命令列表：
 *   插件审核：通过 ID / 拒绝 ID [原因]
 *   创作者审核：创作者列表 / 通过创作者 ID / 拒绝创作者 ID 原因
 *   绑定管理员
 */

if (!function_exists('systemAdminReview')) {
    function systemAdminReview($bot, $botId, $content, $chatType, $fromId, $fromName, $chatId, $replyTargetId, $replyTargetType, $messageId) {
        $trimmedContent = trim($content);

        // ===== 管理员绑定 =====
        if ($chatType === 'bot' && $trimmedContent === '绑定管理员') {
            $stmt = db()->prepare("INSERT INTO settings (user_id, `key`, `value`) VALUES (0, 'admin_yunhu_id', ?) ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([$fromId, $fromId]);
            yunhuSendMessage($bot['token'], $replyTargetId, "✅ 已绑定为管理员（云湖ID: {$fromId}）\n现在您可以管理插件审核和创作者审核了", 'text', $replyTargetType);
            addMessageLog($botId, 'send', "管理员绑定成功: {$fromId}", $botId, $bot['name'], '管理员绑定');
            return true;
        }

        // 仅私聊且管理员可用
        if ($chatType !== 'bot') return false;

        $adminYunhuId = getSetting('admin_yunhu_id');
        $adminQQ = getSetting('admin_qq');
        $isAdmin = (!empty($adminYunhuId) && $fromId === $adminYunhuId)
                || (!empty($adminQQ) && $fromId === $adminQQ);

        if (!$isAdmin) return false;

        // ===== 审核菜单 =====
        if ($trimmedContent === '审核菜单' || $trimmedContent === '菜单' || $trimmedContent === '帮助') {
            $menuText = "📋 管理员审核命令菜单\n"
                . "━━━━━━━━━━━━━━\n"
                . "📌 插件审核\n"
                . "  通过 ID\n"
                . "  拒绝 ID [原因]\n"
                . "  示例：通过 5\n"
                . "  示例：拒绝 5 代码有bug\n"
                . "━━━━━━━━━━━━━━\n"
                . "📌 创作者审核\n"
                . "  创作者列表\n"
                . "  通过创作者 ID\n"
                . "  拒绝创作者 ID 原因\n"
                . "  示例：通过创作者 3\n"
                . "  示例：拒绝创作者 3 理由不充分\n"
                . "━━━━━━━━━━━━━━\n"
                . "📌 其他命令\n"
                . "  绑定管理员\n"
                . "  审核菜单\n"
                . "━━━━━━━━━━━━━━\n"
                . "💡 仅管理员私聊可用，群聊无效";
            yunhuSendMessage($bot['token'], $replyTargetId, $menuText, 'text', $replyTargetType);
            return true;
        }

        $handled = false;

        // ====== 插件审核：通过 / 拒绝 ======
        if (preg_match('/^(通过|approve)\s*(\d+)$/iu', $trimmedContent, $m)) {
            $pluginId = intval($m[2]);
            $up = db()->prepare("UPDATE plugins SET market_status = 2, updated_at = NOW() WHERE id = ? AND market_status = 1");
            $up->execute([$pluginId]);
            if ($up->rowCount() > 0) {
                yunhuSendMessage($bot['token'], $replyTargetId, "✅ 审核通过 #{$pluginId}，已发布到市场", 'text', $replyTargetType);
                addMessageLog($botId, 'send', "插件 #{$pluginId} 审核通过", $botId, $bot['name'], '管理员审核');
                _notifyPluginSubmitter($bot, $pluginId, '通过', "✅ 您的插件已通过审核，已发布到市场！");

                // 邮件通知
                _sendPluginReviewMail($pluginId, true);
            } else {
                yunhuSendMessage($bot['token'], $replyTargetId, "❌ 审核失败 #{$pluginId}，插件不存在或已被处理", 'text', $replyTargetType);
            }
            $handled = true;

        } elseif (preg_match('/^(拒绝|reject)\s*(\d+)(\s+(.+))?$/iu', $trimmedContent, $m)) {
            $pluginId = intval($m[2]);
            $note = !empty($m[4]) ? trim($m[4]) : '未通过审核';
            $up = db()->prepare("UPDATE plugins SET market_status = 3, updated_at = NOW() WHERE id = ? AND market_status = 1");
            $up->execute([$pluginId]);
            if ($up->rowCount() > 0) {
                yunhuSendMessage($bot['token'], $replyTargetId, "❌ 已拒绝 #{$pluginId}，插件已退回", 'text', $replyTargetType);
                addMessageLog($botId, 'send', "插件 #{$pluginId} 审核拒绝", $botId, $bot['name'], '管理员审核');
                _notifyPluginSubmitter($bot, $pluginId, '拒绝', "❌ 您的插件未通过审核\n原因：{$note}");

                // 邮件通知
                _sendPluginReviewMail($pluginId, false, $note);
            } else {
                yunhuSendMessage($bot['token'], $replyTargetId, "❌ 操作失败 #{$pluginId}，插件不存在或已被处理", 'text', $replyTargetType);
            }
            $handled = true;

        // ====== 创作者申请审核 ======
        } elseif ($trimmedContent === '创作者列表' || $trimmedContent === '创作者申请') {
            $rows = db()->query("SELECT a.id, a.user_id, a.reason, u.username, u.nickname
                FROM creator_applications a LEFT JOIN users u ON a.user_id = u.id
                WHERE a.status = 0 ORDER BY a.id DESC LIMIT 20")->fetchAll();
            if (empty($rows)) {
                yunhuSendMessage($bot['token'], $replyTargetId, "✅ 当前没有待审核的创作者申请", 'text', $replyTargetType);
            } else {
                $txt = "📋 创作者申请待审核（共" . count($rows) . "条）：\n";
                foreach ($rows as $r) {
                    $name = $r['nickname'] ?: $r['username'];
                    $reason = mb_substr(str_replace(["\r","\n"], ' ', $r['reason']), 0, 30);
                    $txt .= "\n#{$r['id']} {$name}(UID:{$r['user_id']})\n  理由：{$reason}…\n  通过：通过创作者 {$r['id']}\n  拒绝：拒绝创作者 {$r['id']} 备注\n";
                }
                yunhuSendMessage($bot['token'], $replyTargetId, $txt, 'text', $replyTargetType);
            }
            $handled = true;

        } elseif (preg_match('/^通过创作者\s*(\d+)$/iu', $trimmedContent, $m)) {
            $appId = (int)$m[1];
            try {
                db()->beginTransaction();
                $stmt = db()->prepare("SELECT * FROM creator_applications WHERE id = ? FOR UPDATE");
                $stmt->execute([$appId]);
                $app = $stmt->fetch();
                if (!$app || (int)$app['status'] !== 0) {
                    db()->commit();
                    yunhuSendMessage($bot['token'], $replyTargetId, "❌ 操作失败 #{$appId}：申请不存在或已被处理", 'text', $replyTargetType);
                } else {
                    db()->prepare("UPDATE creator_applications SET status=1, reviewer_id=0, review_note='私聊审核通过', reviewed_at=NOW() WHERE id=?")->execute([$appId]);
                    db()->prepare("UPDATE users SET role=2 WHERE id=?")->execute([$app['user_id']]);
                    db()->commit();
                    $uid = $app['user_id'];
                    yunhuSendMessage($bot['token'], $replyTargetId, "✅ 已通过创作者申请 #{$appId}\n用户UID:{$uid} 已升级为创作者，可开发插件", 'text', $replyTargetType);
                    addMessageLog($botId, 'send', "创作者申请 #{$appId} 通过", $botId, $bot['name'], "私聊审核 UID:{$uid}");

                    // 邮件通知
                    _sendCreatorReviewMail($uid, true);
                }
            } catch (Exception $e) {
                try { db()->commit(); } catch (Exception $_) {}
                yunhuSendMessage($bot['token'], $replyTargetId, "❌ 审核失败：" . $e->getMessage(), 'text', $replyTargetType);
            }
            $handled = true;

        } elseif (preg_match('/^拒绝创作者\s*(\d+)(\s+(.+))?$/iu', $trimmedContent, $m)) {
            $appId = (int)$m[1];
            $note = !empty($m[3]) ? trim($m[3]) : '';
            if ($note === '') {
                yunhuSendMessage($bot['token'], $replyTargetId, "⚠️ 拒绝创作者时请填写备注原因，格式：拒绝创作者 {$appId} 原因说明", 'text', $replyTargetType);
            } else {
                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare("SELECT * FROM creator_applications WHERE id = ? FOR UPDATE");
                    $stmt->execute([$appId]);
                    $app = $stmt->fetch();
                    if (!$app || (int)$app['status'] !== 0) {
                        db()->commit();
                        yunhuSendMessage($bot['token'], $replyTargetId, "❌ 操作失败 #{$appId}：申请不存在或已被处理", 'text', $replyTargetType);
                    } else {
                        $noteSub = mb_substr($note, 0, 255);
                        db()->prepare("UPDATE creator_applications SET status=2, reviewer_id=0, review_note=?, reviewed_at=NOW() WHERE id=?")->execute([$noteSub, $appId]);
                        db()->commit();
                        $uid = $app['user_id'];
                        yunhuSendMessage($bot['token'], $replyTargetId, "❌ 已拒绝创作者申请 #{$appId}\n用户UID:{$uid}\n原因：{$noteSub}", 'text', $replyTargetType);
                        addMessageLog($botId, 'send', "创作者申请 #{$appId} 拒绝", $botId, $bot['name'], "私聊审核 UID:{$uid} 原因:{$noteSub}");

                        // 邮件通知
                        _sendCreatorReviewMail($uid, false, $noteSub);
                    }
                } catch (Exception $e) {
                    try { db()->commit(); } catch (Exception $_) {}
                    yunhuSendMessage($bot['token'], $replyTargetId, "❌ 审核失败：" . $e->getMessage(), 'text', $replyTargetType);
                }
            }
            $handled = true;
        }

        return $handled;
    }
}

// ===== 内部辅助函数 =====

if (!function_exists('_notifyPluginSubmitter')) {
    function _notifyPluginSubmitter($bot, $pluginId, $action, $message) {
        try {
            $stmt = db()->prepare("SELECT p.*, u.nickname FROM plugins p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?");
            $stmt->execute([$pluginId]);
            $pluginInfo = $stmt->fetch();
            if (!$pluginInfo) return;
            $oaStmt = db()->prepare("SELECT openid FROM oauth_accounts WHERE user_id = ? AND provider IN ('qq', 'wx') LIMIT 1");
            $oaStmt->execute([$pluginInfo['user_id']]);
            $targetId = $oaStmt->fetchColumn();
            if (!empty($targetId)) {
                $submitterMsg = "📦 插件审核通知\n插件：{$pluginInfo['name']}\n结果：{$message}";
                yunhuSendMessage($bot['token'], $targetId, $submitterMsg, 'text', 'user');
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('_sendPluginReviewMail')) {
    function _sendPluginReviewMail($pluginId, $approved, $reason = '') {
        try {
            $stmt = db()->prepare("SELECT p.name, u.username, u.email FROM plugins p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?");
            $stmt->execute([$pluginId]);
            $plugin = $stmt->fetch();
            if (!$plugin || empty($plugin['email'])) return;

            $username = $plugin['username'] ?: '用户';
            $pluginName = $plugin['name'] ?: '未命名插件';

            if ($approved) {
                $subject = '【' . getSiteName() . '】插件审核通过通知';
                $body = "尊敬的{$username}您好，您的插件{$pluginName}已通过审核，已自动上传至插件市场";
            } else {
                $reasonText = $reason ?: '未符合平台要求';
                $subject = '【' . getSiteName() . '】插件审核未通过通知';
                $body = "尊敬的{$username}您好，您的插件{$pluginName}不通过审核，原因{$reasonText}";
            }
            sendMail($plugin['email'], $subject, $body);
        } catch (Exception $e) {}
    }
}

if (!function_exists('_sendCreatorReviewMail')) {
    function _sendCreatorReviewMail($userId, $approved, $reason = '') {
        try {
            $stmt = db()->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user || empty($user['email'])) return;

            $username = $user['username'] ?: '用户';

            if ($approved) {
                $subject = '【' . getSiteName() . '】创作者申请通过通知';
                $body = "尊敬的{$username}您好，您的创作者申请已通过，欢迎您开发更多的插件";
            } else {
                $reasonText = $reason ?: '未说明';
                $subject = '【' . getSiteName() . '】创作者申请未通过通知';
                $body = "尊敬的{$username}您好，您的创作者申请未通过，原因如下{$reasonText}";
            }
            sendMail($user['email'], $subject, $body);
        } catch (Exception $e) {}
    }
}
