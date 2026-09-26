<?php
/**
 * 云湖机器人消息处理核心逻辑
 * 被 webhook.php 调用使用
 */

/**
 * 处理接收到的机器人消息（插件/菜单/API 匹配）
 *
 * @param array $bot         机器人数据
 * @param array $eventData   事件数据 [
 *   'content'     => string 消息文本
 *   'chatType'    => string 聊天类型 (bot/group)
 *   'fromId'      => string 发送者ID
 *   'fromName'    => string 发送者昵称
 *   'chatId'      => string 聊天ID
 *   'msgType'     => string 消息类型 (text/messageButton/...)
 *   'msgId'       => string 消息ID
 *   'rawData'     => string 原始JSON数据
 * ]
 */
function processBotMessage($bot, $eventData) {
    $botId    = $bot['id'];
    $content  = $eventData['content'] ?? '';
    $chatType = $eventData['chatType'] ?? 'bot';
    $fromId   = $eventData['fromId'] ?? '';
    $fromName = $eventData['fromName'] ?? '';
    $chatId   = $eventData['chatId'] ?? '';
    $msgType  = $eventData['msgType'] ?? 'text';
    $rawData  = $eventData['rawData'] ?? '';

    // 确定回复目标
    if ($chatType === 'group') {
        $replyTargetId   = $chatId;
        $replyTargetType = 'group';
    } else {
        $replyTargetId   = $fromId;
        $replyTargetType = 'user';
    }

    // 保存消息到数据库
    $stmt = db()->prepare("INSERT INTO messages (bot_id, chat_type, chat_id, from_id, from_name, content, msg_type, extra, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$botId, $chatType, $chatId, $fromId, $fromName, $content, $msgType, $rawData]);
    $messageId = db()->lastInsertId();

    // 记录日志
    addMessageLog($botId, 'receive', $content, $fromId, $fromName, "类型: {$msgType}, 聊天: {$chatType}, 聊天ID: {$chatId}");

    // 只处理文本/按钮消息
    if (($msgType !== 'text' && $msgType !== 'messageButton') || empty($content)) {
        return;
    }

    // ===== 系统内置插件：管理员审核（插件审核 + 创作者审核）=====
    require_once dirname(__DIR__) . '/plugins/system_admin_review.php';
    if (systemAdminReview($bot, $botId, $content, $chatType, $fromId, $fromName, $chatId, $replyTargetId, $replyTargetType, $messageId)) {
        return;
    }

    // ===== 系统内置命令：#by 运行状态 =====
    if (trim($content) === '#by') {
        $statusMsg = buildSystemStatus($botId);
        yunhuSendMessage($bot['token'], $replyTargetId, $statusMsg, 'text', $replyTargetType);
        addMessageLog($botId, 'send', $statusMsg, $botId, $bot['name'], '内置命令: #by 运行状态');
        $stmt2 = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
        $stmt2->execute([$statusMsg, $messageId]);
        return;
    }

    // ===== 插件调用 =====
    $stmt = db()->prepare("SELECT * FROM plugins WHERE (bot_id = ? OR bot_id = 0) AND enabled = 1");
    $stmt->execute([$botId]);
    $plugins = $stmt->fetchAll();

    if (!empty($plugins)) {
        foreach ($plugins as $plugin) {
            $pluginId = $plugin['id'] ?? 0;
            $pluginFile = $plugin['plugin_file'] ?? '';
            $pluginCode = $plugin['plugin_code'] ?? '';

            $code = '';
            if (!empty($pluginCode) && trim($pluginCode) !== '') {
                $code = $pluginCode;
            } elseif (!empty($pluginFile)) {
                $pluginPath = dirname(__DIR__) . '/uploads/plugins/' . $pluginFile;
                if (!file_exists($pluginPath)) {
                    addMessageLog($botId, 'error', "插件文件不存在: {$pluginPath}", $botId, $bot['name'], "插件: {$plugin['name']} (ID:{$pluginId})\n预期路径: {$pluginPath}\n原因: 上传的插件文件被删除或路径错误");
                    continue;
                }
                $code = file_get_contents($pluginPath);
                if ($code === false || trim($code) === '') {
                    addMessageLog($botId, 'error', "插件文件内容为空: {$pluginPath}", $botId, $bot['name'], "插件: {$plugin['name']} (ID:{$pluginId})\n路径: {$pluginPath}\n原因: 插件文件为空，可能上传失败");
                    continue;
                }
            }

            if (empty(trim($code))) continue;

            try {
                // 安全警告：eval() 执行用户上传的插件代码存在安全风险
                // 当前仅限创作者/管理员上传插件，建议未来实现插件签名验证或沙箱隔离
                $code = preg_replace('/^\s*<\?(?:php)?\s*/i', '', $code);
                $pluginDir = dirname(__DIR__) . '/uploads/plugins';
                $code = str_replace('__DIR__', var_export($pluginDir, true), $code);
                $ns = 'plugin_' . $pluginId;
                $code = 'namespace ' . $ns . ' {' . "\nuse CURLFile, PDO, Exception;\n" . $code . "\n}";
                eval($code);

                $msgData = [
                    'content' => $content,
                    'chat' => ['id' => $chatId, 'type' => $chatType],
                    'sender' => ['id' => $fromId, 'name' => $fromName],
                ];

                $result = null;
                $groupFunc = $ns . '\group';
                $c2cFunc = $ns . '\C2C';

                if ($chatType === 'group' && function_exists($groupFunc)) {
                    $result = $groupFunc($msgData, ['token' => $bot['token'], 'recvId' => $replyTargetId, 'recvType' => $replyTargetType]);
                } elseif ($chatType !== 'group' && function_exists($c2cFunc)) {
                    $result = $c2cFunc($msgData, ['token' => $bot['token'], 'recvId' => $replyTargetId, 'recvType' => $replyTargetType]);
                }

                if ($result !== null && is_array($result)) {
                    $replyType = $result['type'] ?? 'text';
                    $replyContent = $result['content'] ?? '';
                    if (!empty($replyContent)) {
                        yunhuSendMessage($bot['token'], $replyTargetId, $replyContent, $replyType, $replyTargetType);
                        addMessageLog($botId, 'send', $replyContent, $botId, $bot['name'], "插件: {$plugin['name']} (类型={$replyType})");
                        $stmt2 = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
                        $stmt2->execute([$replyType === 'text' ? $replyContent : '[' . $replyType . ']', $messageId]);
                        return;
                    }
                }
            } catch (Throwable $e) {
                $errorDetail = "插件: {$plugin['name']} (ID:{$pluginId})\n"
                    . "错误: " . $e->getMessage() . "\n"
                    . "文件: " . $e->getFile() . ":" . $e->getLine() . "\n"
                    . "堆栈:\n" . $e->getTraceAsString();
                addMessageLog($botId, 'error', "插件执行错误 [{$plugin['name']}]: " . $e->getMessage(), $botId, $bot['name'], $errorDetail);
            }
        }
    }

    // ===== 菜单匹配 =====
    $stmt = db()->prepare("SELECT * FROM menus WHERE bot_id = ? AND status = 1 AND (name = ? OR content = ?) LIMIT 1");
    $stmt->execute([$botId, $content, $content]);
    $matchedMenu = $stmt->fetch();

    if ($matchedMenu) {
        $menuType = $matchedMenu['type'] ?? 'text';
        $menuContent = $matchedMenu['content'] ?? '';
        addMessageLog($botId, 'info', "命中菜单: {$matchedMenu['name']} (类型={$menuType})", $botId, $bot['name'], '');

        switch ($menuType) {
            case 'text':
                if (!empty($menuContent)) {
                    yunhuSendMessage($bot['token'], $replyTargetId, $menuContent, 'text', $replyTargetType);
                    addMessageLog($botId, 'send', $menuContent, $botId, $bot['name'], "菜单: {$matchedMenu['name']}");
                    $stmt2 = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
                    $stmt2->execute([$menuContent, $messageId]);
                }
                break;
            case 'link':
                if (!empty($menuContent)) {
                    yunhuSendMessage($bot['token'], $replyTargetId, $menuContent, 'text', $replyTargetType);
                    addMessageLog($botId, 'send', $menuContent, $botId, $bot['name'], "菜单(链接): {$matchedMenu['name']}");
                    $stmt2 = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
                    $stmt2->execute([$menuContent, $messageId]);
                }
                break;
            case 'api':
                if (!empty($matchedMenu['apis'])) {
                    $apiIds = json_decode($matchedMenu['apis'], true);
                    if (is_array($apiIds) && count($apiIds) > 0) {
                        foreach ($apiIds as $apiId) {
                            $stmt2 = db()->prepare("SELECT * FROM apis WHERE id = ? AND status = 1");
                            $stmt2->execute([(int)$apiId]);
                            $menuApi = $stmt2->fetch();
                            if ($menuApi) {
                                $apiUrl = $menuApi['api_url'];
                                $apiMethod = strtoupper($menuApi['method'] ?? 'GET');
                                $apiTimeout = (int)($menuApi['timeout'] ?? 10);
                                $menuContext = [
                                    'args' => [], 'user_id' => $fromId, 'chat_id' => $chatId,
                                    'bot_id' => $botId, 'user_name' => $fromName,
                                    'bot_name' => $bot['name'] ?? '', 'command' => $content, 'content' => $content,
                                ];
                                $apiUrl = replaceApiVariables($apiUrl, $menuContext, true);
                                $apiResult = curlRequest($apiUrl, $apiMethod, [], null, $apiTimeout);
                                $apiResponse = $apiResult['body'];
                                if ($apiResponse && $apiResult['http_code'] < 400) {
                                    $jsonData = json_decode($apiResponse, true);
                                    $replyText = $apiResponse;
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                                        foreach (['data','result','msg','text','content','message'] as $k) {
                                            if (isset($jsonData[$k]) && (is_string($jsonData[$k]) || is_numeric($jsonData[$k]))) {
                                                $replyText = (string)$jsonData[$k]; break;
                                            }
                                        }
                                    }
                                    $menuContext['response'] = $apiResponse;
                                    $menuContext['result'] = $replyText;
                                    $replyText = replaceApiVariables($replyText, $menuContext);
                                    yunhuSendMessage($bot['token'], $replyTargetId, $replyText, 'text', $replyTargetType);
                                    addMessageLog($botId, 'send', $replyText, $botId, $bot['name'], "菜单API: {$matchedMenu['name']}|{$menuApi['name']}");
                                    $stmt3 = db()->prepare("UPDATE apis SET use_count = use_count + 1 WHERE id = ?");
                                    $stmt3->execute([$menuApi['id']]);
                                } else {
                                    $menuFailReason = !empty($apiResult['error']) ? "网络错误: {$apiResult['error']}" : "HTTP{$apiResult['http_code']}";
                                    $menuDiag = "菜单API: {$matchedMenu['name']}|{$menuApi['name']}\n"
                                        . "失败原因: {$menuFailReason}\n"
                                        . "请求URL: {$apiUrl}\n"
                                        . "请求方法: {$apiMethod}\n"
                                        . "HTTP状态码: {$apiResult['http_code']}\n";
                                    if (!empty($apiResult['error'])) $menuDiag .= "CURL错误: {$apiResult['error']}\n";
                                    if (!empty($apiResponse)) $menuDiag .= "响应内容: " . mb_substr($apiResponse, 0, 500) . "\n";
                                    elseif (empty($apiResponse)) $menuDiag .= "响应内容: (空)\n";
                                    addMessageLog($botId, 'error', "菜单API调用失败: {$menuApi['name']}", $botId, $bot['name'], $menuDiag);
                                }
                            }
                        }
                    }
                }
                break;
            default:
                if (!empty($menuContent)) {
                    yunhuSendMessage($bot['token'], $replyTargetId, $menuContent, 'text', $replyTargetType);
                    addMessageLog($botId, 'send', $menuContent, $botId, $bot['name'], "菜单: {$matchedMenu['name']}");
                    $stmt2 = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
                    $stmt2->execute([$menuContent, $messageId]);
                }
                break;
        }
        return;
    }

    // ===== API 匹配 =====
    $stmt = db()->prepare("SELECT * FROM apis WHERE (bot_id = 0 OR bot_id = ?) AND status = 1 ORDER BY trigger_mode DESC, id DESC");
    $stmt->execute([$botId]);
    $apis = $stmt->fetchAll();

    $matchedApi = null;
    $commandText = '';
    $args = [];

    foreach ($apis as $api) {
        $command = $api['command'];
        $triggerMode = $api['trigger_mode'] ?? 'exact';

        switch ($triggerMode) {
            case 'exact':
                if ($content === $command) { $matchedApi = $api; }
                break;
            case 'fuzzy':
                if (strpos($content, $command) !== false) {
                    $matchedApi = $api;
                    $commandText = $command;
                    $split = $api['param_split'] ?? ' ';
                    $afterCommand = substr($content, strpos($content, $command) + strlen($command));
                    $args = $split ? array_filter(explode($split, trim($afterCommand))) : [trim($afterCommand)];
                }
                break;
            case 'left':
            case 'prefix':
                if (strpos($content, $command) === 0) {
                    $matchedApi = $api;
                    $commandText = $command;
                    $split = $api['param_split'] ?? ' ';
                    $afterCommand = substr($content, strlen($command));
                    $args = $split ? array_filter(explode($split, trim($afterCommand))) : [trim($afterCommand)];
                }
                break;
            case 'right':
                $cmdLen = strlen($command);
                if ($cmdLen > 0 && substr($content, -$cmdLen) === $command) {
                    $matchedApi = $api;
                    $commandText = $command;
                }
                break;
            case 'regex':
                if (preg_match($command, $content, $matches)) {
                    $matchedApi = $api;
                    $args = array_slice($matches, 1);
                }
                break;
        }
        if ($matchedApi) break;
    }

    $varContext = [
        'args' => array_values($args),
        'user_id' => $fromId, 'chat_id' => $chatId, 'bot_id' => $botId,
        'user_name' => $fromName, 'bot_name' => $bot['name'] ?? '',
        'command' => $commandText, 'content' => $content,
    ];

    if (!$matchedApi) {
        if (!empty($bot['unknown_reply'])) {
            $reply = replaceApiVariables($bot['unknown_reply'], $varContext);
            yunhuSendMessage($bot['token'], $replyTargetId, $reply, 'text', $replyTargetType);
            addMessageLog($botId, 'send', $reply, $botId, $bot['name'], "未匹配API | 用户消息={$content}");
        } else {
            addMessageLog($botId, 'info', "未匹配任何API且无未知回复设置", $fromId, $fromName, "用户消息: {$content}");
        }
        return;
    }

    // ===== 发送调用前内容 =====
    if (!empty($matchedApi['pre_content'])) {
        $preContent = replaceApiVariables($matchedApi['pre_content'], $varContext);
        yunhuSendMessage($bot['token'], $replyTargetId, $preContent, 'text', $replyTargetType);
    }

    // ===== 调用 API =====
    $apiUrl = replaceApiVariables($matchedApi['api_url'], $varContext, true);
    $method = strtoupper($matchedApi['method'] ?? 'GET');
    $timeout = (int)($matchedApi['timeout'] ?? 10);
    $headerArr = [];
    if (!empty($matchedApi['headers'])) {
        $headers = json_decode($matchedApi['headers'], true);
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $headerArr[] = "$k: " . replaceApiVariables($v, $varContext);
            }
        }
    }
    $postBody = null;
    if (!empty($matchedApi['body'])) {
        $postBody = replaceApiVariables($matchedApi['body'], $varContext);
    }

    $result = curlRequest($apiUrl, $method, $headerArr, $postBody, $timeout);
    $response = $result['body'];
    $httpCode = $result['http_code'];
    $sendType = $matchedApi['send_type'] ?? 'text';
    $responseTemplate = $matchedApi['response_template'] ?? '';

    if ($response === false || $response === null || $response === '' || $httpCode >= 400) {
        $failReason = '';
        if (!empty($result['error'])) $failReason = "网络错误: {$result['error']}";
        elseif ($httpCode >= 500) $failReason = "服务器错误 HTTP{$httpCode}";
        elseif ($httpCode == 404) $failReason = "接口不存在 HTTP404";
        elseif ($httpCode == 403) $failReason = "接口拒绝访问 HTTP403";
        elseif ($httpCode == 401) $failReason = "认证失败 HTTP401";
        elseif ($httpCode >= 400) $failReason = "客户端错误 HTTP{$httpCode}";
        elseif ($response === '' || $response === null) $failReason = "API返回空白";
        else $failReason = "未知原因";

        $diagInfo = "API: {$matchedApi['name']} (ID:{$matchedApi['id']})\n"
            . "失败原因: {$failReason}\n"
            . "请求URL: {$apiUrl}\n"
            . "请求方法: {$method}\n"
            . "HTTP状态码: {$httpCode}\n"
            . "超时设置: {$timeout}秒\n";
        if (!empty($result['error'])) {
            $diagInfo .= "CURL错误: {$result['error']}\n";
        }
        if (!empty($headerArr)) {
            $diagInfo .= "请求头: " . implode(', ', $headerArr) . "\n";
        }
        if (!empty($postBody)) {
            $diagInfo .= "请求体: " . mb_substr($postBody, 0, 500) . "\n";
        }
        if (!empty($response)) {
            $diagInfo .= "响应内容: " . mb_substr($response, 0, 500) . "\n";
        }

        $errMsg = "API调用失败，请稍后再试";
        yunhuSendMessage($bot['token'], $replyTargetId, $errMsg, 'text', $replyTargetType);
        addMessageLog($botId, 'send', $errMsg, $botId, $bot['name'], $diagInfo);
        return;
    }

    // ===== 图片类型处理 =====
    if ($sendType === 'image') {
        handleImageResponse($bot, $matchedApi, $response, $replyTargetId, $replyTargetType, $varContext, $messageId, $botId, $responseTemplate);
        return;
    }

    // ===== 视频类型处理 =====
    if ($sendType === 'video') {
        handleVideoResponse($bot, $matchedApi, $response, $replyTargetId, $replyTargetType, $messageId, $botId);
        return;
    }

    // ===== 文件类型处理 =====
    if ($sendType === 'file') {
        handleFileResponse($bot, $matchedApi, $response, $replyTargetId, $replyTargetType, $messageId, $botId);
        return;
    }

    // ===== 文本类型响应 =====
    $varContext['response'] = $response;
    $varContext['result'] = $response;

    if (!empty($responseTemplate)) {
        $reply = replaceApiVariables($responseTemplate, $varContext);
    } else {
        $reply = $response;
    }

    $jsonData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $candidateKeys = ['data', 'result', 'msg', 'text', 'content', 'message', 'hitokoto', 'joke'];
        foreach ($candidateKeys as $key) {
            if (isset($jsonData[$key]) && (is_string($jsonData[$key]) || is_numeric($jsonData[$key]))) {
                $reply = (string)$jsonData[$key]; break;
            }
        }
        if ($reply === $response && isset($jsonData['data']) && is_array($jsonData['data'])) {
            foreach (['text', 'content', 'message', 'msg', 'joke', 'hitokoto'] as $k) {
                if (isset($jsonData['data'][$k]) && (is_string($jsonData['data'][$k]) || is_numeric($jsonData['data'][$k]))) {
                    $reply = (string)$jsonData['data'][$k]; break;
                }
            }
        }
        if (isset($jsonData['hitokoto']) && !empty($jsonData['from'])) {
            $reply = $jsonData['hitokoto'] . "\n——《" . $jsonData['from'] . "》";
        }
    }

    $varContext['result'] = $reply;
    $reply = replaceApiVariables($reply, $varContext);

    yunhuSendMessage($bot['token'], $replyTargetId, $reply, $sendType, $replyTargetType);
    addMessageLog($botId, 'send', $reply, $botId, $bot['name'], "API: {$matchedApi['name']}");

    $stmt = db()->prepare("UPDATE apis SET use_count = use_count + 1 WHERE id = ?");
    $stmt->execute([$matchedApi['id']]);
    $stmt = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
    $stmt->execute([$reply, $messageId]);
}

// ===== 以下为辅助函数 =====

function handleImageResponse($bot, $matchedApi, $response, $replyTargetId, $replyTargetType, $varContext, $messageId, $botId, $responseTemplate) {
    $img = yunhuExtractImage($response);
    $imageBinary = '';
    if ($img['type'] === 'binary') {
        $imageBinary = $img['data'];
    } elseif ($img['type'] === 'url') {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $img['data'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $imageBinary = curl_exec($ch);
        curl_close($ch);
    } else {
        $retry = !empty($responseTemplate) ? replaceApiVariables($responseTemplate, $varContext) : $response;
        $img2 = yunhuExtractImage($retry);
        if ($img2['type'] === 'binary') $imageBinary = $img2['data'];
        elseif ($img2['type'] === 'url') {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $img2['data'], CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $imageBinary = curl_exec($ch);
            curl_close($ch);
        }
    }

    if (!empty($imageBinary) && yunhuIsImageBinary($imageBinary)) {
        $imgExt = yunhuDetectImageExt($imageBinary);
        $imageKey = yunhuUploadImage($bot['token'], $imageBinary, 'image.' . $imgExt);
        if ($imageKey) {
            yunhuSendMessage($bot['token'], $replyTargetId, $imageKey, 'image', $replyTargetType);
            addMessageLog($botId, 'send', '[图片] imageKey=' . $imageKey, $botId, $bot['name'], "API: {$matchedApi['name']}");
            $stmt = db()->prepare("UPDATE apis SET use_count = use_count + 1 WHERE id = ?");
            $stmt->execute([$matchedApi['id']]);
            $stmt = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
            $stmt->execute(['[图片]', $messageId]);
            return;
        }
    }
    $errMsg = "图片获取失败，请稍后再试";
    yunhuSendMessage($bot['token'], $replyTargetId, $errMsg, 'text', $replyTargetType);
    addMessageLog($botId, 'send', $errMsg, $botId, $bot['name'], "API图片失败: {$matchedApi['name']}");
}

function handleVideoResponse($bot, $matchedApi, $response, $replyTargetId, $replyTargetType, $messageId, $botId) {
    $videoBinary = '';
    $videoExt = 'mp4';
    $jsonData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $videoUrl = '';
        foreach (['videoUrl', 'url', 'data', 'result', 'video'] as $k) {
            if (!empty($jsonData[$k]) && is_string($jsonData[$k]) && filter_var($jsonData[$k], FILTER_VALIDATE_URL)) {
                $videoUrl = $jsonData[$k]; break;
            }
            if (is_array($jsonData['data'] ?? null)) {
                foreach (['videoUrl','url','video'] as $k2) {
                    if (!empty($jsonData['data'][$k2]) && filter_var($jsonData['data'][$k2], FILTER_VALIDATE_URL)) {
                        $videoUrl = $jsonData['data'][$k2]; break 2;
                    }
                }
            }
        }
        if ($videoUrl) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $videoUrl, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $videoBinary = curl_exec($ch);
            curl_close($ch);
            $videoExt = strtolower(pathinfo(parse_url($videoUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'mp4';
        }
    }
    if (empty($videoBinary)) { $videoBinary = $response; }
    if (!empty($videoBinary)) {
        $videoKey = yunhuUploadVideo($bot['token'], $videoBinary, 'video.' . $videoExt);
        if ($videoKey) {
            yunhuSendMessage($bot['token'], $replyTargetId, $videoKey, 'video', $replyTargetType);
            addMessageLog($botId, 'send', '[视频] videoKey=' . $videoKey, $botId, $bot['name'], "API: {$matchedApi['name']}");
            $stmt = db()->prepare("UPDATE apis SET use_count = use_count + 1 WHERE id = ?");
            $stmt->execute([$matchedApi['id']]);
            $stmt = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
            $stmt->execute(['[视频]', $messageId]);
            return;
        }
    }
    $errMsg = "视频获取失败，请稍后再试";
    yunhuSendMessage($bot['token'], $replyTargetId, $errMsg, 'text', $replyTargetType);
    addMessageLog($botId, 'send', $errMsg, $botId, $bot['name'], "API视频失败: {$matchedApi['name']}");
}

function handleFileResponse($bot, $matchedApi, $response, $replyTargetId, $replyTargetType, $messageId, $botId) {
    $fileBinary = '';
    $fileName = 'file';
    $jsonData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $fileUrl = '';
        foreach (['fileUrl', 'url', 'data', 'result', 'file'] as $k) {
            if (!empty($jsonData[$k]) && is_string($jsonData[$k]) && filter_var($jsonData[$k], FILTER_VALIDATE_URL)) {
                $fileUrl = $jsonData[$k]; break;
            }
            if (is_array($jsonData['data'] ?? null)) {
                foreach (['fileUrl','url','file'] as $k2) {
                    if (!empty($jsonData['data'][$k2]) && filter_var($jsonData['data'][$k2], FILTER_VALIDATE_URL)) {
                        $fileUrl = $jsonData['data'][$k2]; break 2;
                    }
                }
            }
        }
        if (!empty($jsonData['fileName']) && is_string($jsonData['fileName'])) $fileName = $jsonData['fileName'];
        elseif (!empty($jsonData['title']) && is_string($jsonData['title'])) $fileName = $jsonData['title'];
        elseif (!empty($jsonData['name']) && is_string($jsonData['name'])) $fileName = $jsonData['name'];
        if ($fileUrl) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $fileUrl, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $fileBinary = curl_exec($ch);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            if (($p = parse_url($fileUrl, PHP_URL_PATH)) && basename($p)) $fileName = basename($p);
            elseif ($finalUrl && ($p = parse_url($finalUrl, PHP_URL_PATH)) && basename($p)) $fileName = basename($p);
        }
    }
    if (empty($fileBinary)) { $fileBinary = $response; }
    if (!empty($fileBinary)) {
        $fileKey = yunhuUploadFile($bot['token'], $fileBinary, $fileName);
        if ($fileKey) {
            yunhuSendMessage($bot['token'], $replyTargetId, $fileKey, 'file', $replyTargetType);
            addMessageLog($botId, 'send', '[文件] fileKey=' . $fileKey, $botId, $bot['name'], "API: {$matchedApi['name']}");
            $stmt = db()->prepare("UPDATE apis SET use_count = use_count + 1 WHERE id = ?");
            $stmt->execute([$matchedApi['id']]);
            $stmt = db()->prepare("UPDATE messages SET is_reply = 1, reply_content = ? WHERE id = ?");
            $stmt->execute(['[文件]', $messageId]);
            return;
        }
    }
    $errMsg = "文件获取失败，请稍后再试";
    yunhuSendMessage($bot['token'], $replyTargetId, $errMsg, 'text', $replyTargetType);
    addMessageLog($botId, 'send', $errMsg, $botId, $bot['name'], "API文件失败: {$matchedApi['name']}");
}

/**
 * 通知插件提交者审核结果
 */
function notifyPluginSubmitter($bot, $pluginId, $action, $message) {
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

/**
 * 构建系统运行状态信息（#by 命令）
 */
function buildSystemStatus($botId) {
    // ===== 框架名称 =====
    $frameworkName = getSiteName();

    // ===== 插件名称列表 =====
    $stmt = db()->prepare("SELECT name FROM plugins WHERE (bot_id = ? OR bot_id = 0) AND enabled = 1 ORDER BY id ASC");
    $stmt->execute([$botId]);
    $plugins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $pluginNames = !empty($plugins) ? implode('  ', $plugins) : '暂无启用插件';

    // ===== 在线时长（用数据库存储，避免文件权限问题）=====
    $startTime = (int)getSetting('system_start_time');
    if ($startTime <= 0) {
        $startTime = time();
        setSetting('system_start_time', (string)$startTime);
    }
    $uptime = formatUptime(time() - $startTime);

    // ===== 当前北京时间 =====
    $now = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    $currentTime = $now->format('Y-m-d H:i:s');

    // ===== 组装消息 =====
    $msg = "框架名称：{$frameworkName}\n"
        . "插件名称：{$pluginNames}\n"
        . "在线时长：{$uptime}\n"
        . "当前时间：{$currentTime}\n"
        . "官机类型：webhook";

    return $msg;
}

/**
 * 格式化在线时长
 */
function formatUptime($seconds) {
    if ($seconds < 60) return $seconds . '秒';
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    $parts = [];
    if ($days > 0) $parts[] = $days . '天';
    if ($hours > 0) $parts[] = $hours . '小时';
    if ($minutes > 0) $parts[] = $minutes . '分钟';
    if ($secs > 0) $parts[] = $secs . '秒';
    return implode('', $parts);
}
