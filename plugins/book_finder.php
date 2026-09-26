<?php
/**
 * 找书插件 - 搜索书库并上传书籍
 * 书库来源：
 *   https://www.ilanzou.com/s/pFLLSCzm
 *   https://www.ilanzou.com/s/4sfn1IDr?code=8888
 *   https://www.ilanzou.com/s/zaan1IQf?code=8888
 *   https://www.ilanzou.com/s/aW3n1IRL?code=8888
 *   https://www.ilanzou.com/s/uErn1Ir3?code=8888
 *   https://www.ilanzou.com/s/iuWn1I9F?code=8888
 *   https://www.ilanzou.com/s/XcOn1Id5?code=8888
 *   https://www.ilanzou.com/s/80an1IaN?code=8888
 */
$info = array(
    'name' => '找书',
    'author' => '白屿',
    'version' => '1.0.0',
    'desc' => '搜索书库并上传书籍',
    'command_list' => array('找书', 'searchbook', 'book', '下载', '番茄', '解析')
);

function has_been_loaded($info) {
    return true;
}

function group($msg, $botapi) {
    return runPlugin($msg, $botapi);
}

function C2C($msg, $botapi) {
    return runPlugin($msg, $botapi);
}

function button($button_event, $botapi) {
    return null;
}

function runPlugin($msg, $botapi) {
    if (empty($msg['content'])) return null;
    $content = trim($msg['content']);

    // 下载命令: 下载 N
    if (preg_match('/^下载\s*(\d+)$/u', $content, $m)) {
        return handleDownload($botapi, $msg, (int)$m[1]);
    }

    // 番茄解析命令: 番茄URL / 解析URL（不需要空格）
    if (preg_match('/^(番茄|解析)\s*(https?:\/\/\S+)/iu', $content, $m)) {
        return handleTomato($botapi, $msg, $m[2]);
    }

    // 搜索命令: 找书关键词 / 找书 关键词
    if (preg_match('/^(找书|searchbook|book)\s*(.*)$/iu', $content, $m)) {
        return handleSearch($msg, trim($m[2]));
    }

    return null;
}

/**
 * 番茄小说解析
 */
function handleTomato($botapi, $msg, $url) {
    $token = getBotToken($botapi);
    $recvId = is_array($botapi) ? ($botapi['recvId'] ?? '') : '';
    $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'user') : 'user';

    if (empty($token)) {
        return array('type' => 'text', 'content' => '❌ 无法获取机器人Token');
    }

    if (empty($recvId)) {
        $recvId = $msg['chat']['id'] ?? '';
    }

    sendProgress($token, $recvId, $recvType, '📥 正在解析番茄小说，请稍候...');

    $apiUrl = 'https://api.by111.cn/api/jx/fq.php?apikey=748a062f5afb18ec726d1041fb1a3caf&url=' . urlencode($url);
    $apiResponse = httpGet($apiUrl, 30);

    if ($apiResponse === false) {
        return array('type' => 'text', 'content' => '❌ 解析请求失败，请稍后重试');
    }

    // 解析 JSON 响应，提取真实下载链接或文本内容
    $fileData = null;
    $jsonData = json_decode($apiResponse, true);

    if (is_array($jsonData)) {
        // 检查是否有错误
        if (isset($jsonData['code']) && $jsonData['code'] != 0) {
            $errMsg = $jsonData['message'] ?? $jsonData['msg'] ?? '未知错误';
            return array('type' => 'text', 'content' => '❌ 解析失败：' . $errMsg);
        }
        // 尝试从多种字段提取下载链接
        $downloadUrl = '';
        if (!empty($jsonData['url']) && is_string($jsonData['url'])) {
            $downloadUrl = $jsonData['url'];
        } elseif (!empty($jsonData['download']) && is_string($jsonData['download'])) {
            $downloadUrl = $jsonData['download'];
        } elseif (!empty($jsonData['data']) && is_string($jsonData['data'])) {
            $downloadUrl = $jsonData['data'];
        } elseif (!empty($jsonData['data']['url']) && is_string($jsonData['data']['url'])) {
            $downloadUrl = $jsonData['data']['url'];
        } elseif (!empty($jsonData['data']['download']) && is_string($jsonData['data']['download'])) {
            $downloadUrl = $jsonData['data']['download'];
        }

        if (!empty($downloadUrl) && strpos($downloadUrl, 'http') === 0) {
            $fileData = httpGet($downloadUrl, 60);
        } elseif (!empty($jsonData['content']) && is_string($jsonData['content'])) {
            $fileData = $jsonData['content'];
        } elseif (!empty($jsonData['data']) && is_string($jsonData['data']) && strlen($jsonData['data']) > 100) {
            $fileData = $jsonData['data'];
        }
    } else {
        // 不是 JSON，直接作为文件内容
        $fileData = $apiResponse;
    }

    if (empty($fileData) || strlen($fileData) < 100) {
        return array('type' => 'text', 'content' => '❌ 解析失败，未获取到小说内容');
    }

    $fileName = '番茄小说_' . date('Ymd_His') . '.txt';
    $fileKey = \yunhuUploadFile($token, $fileData, $fileName);
    if (empty($fileKey)) {
        $lastError = \yunhuGetLastUploadError();
        $err = $lastError['error'] ?? '未知原因';
        $dbg = !empty($lastError['debug']) ? "\n\n💡 调试信息：{$lastError['debug']}" : '';
        return array('type' => 'text', 'content' => "❌ 上传到云湖失败：{$err}{$dbg}");
    }

    sendFile($token, $recvId, $recvType, $fileKey, $fileName);
    return array('type' => 'text', 'content' => '✅ 番茄小说解析完成');
}

/**
 * 搜索书籍
 */
function handleSearch($msg, $keyword) {
    $userId = $msg['sender']['id'] ?? $msg['chat']['id'] ?? '0';

    if ($keyword === '') {
        return array('type' => 'text', 'content' =>
            "📚 找书插件\n\n"
            . "使用方式：\n"
            . "找书<关键词> - 搜索书库\n"
            . "下载 <序号> - 上传书籍\n"
            . "番茄 <URL> - 解析番茄小说\n"
            . "解析 <URL> - 解析番茄小说\n\n"
            . "示例：\n"
            . "找书总裁\n"
            . "下载 1\n"
            . "番茄 https://xxx.com/book/123"
        );
    }

    $books = getBookList();
    if ($books === false) {
        return array('type' => 'text', 'content' => '❌ 获取书库失败，服务器可能无法连接外部API');
    }
    if (empty($books)) {
        return array('type' => 'text', 'content' => '📚 书库暂无书籍');
    }

    // 模糊匹配
    $results = array();
    foreach ($books as $book) {
        if (preg_match('/' . preg_quote($keyword, '/') . '/iu', $book['fileName'])) {
            $results[] = $book;
        }
    }

    if (empty($results)) {
        return array('type' => 'text', 'content' => '❌ 未找到包含「' . $keyword . '」的书籍');
    }

    saveSearchResult($userId, $results);

    $total = count($results);
    $show = min($total, 20);
    $lines = array('📚 找到 ' . $total . ' 本与「' . $keyword . '」相关的书籍：', str_repeat('─', 30));
    for ($i = 0; $i < $show; $i++) {
        $lines[] = ($i + 1) . '. ' . $results[$i]['fileName'] . '（' . $results[$i]['sizeStr'] . '）';
    }
    if ($total > 20) {
        $lines[] = '… 还有 ' . ($total - 20) . ' 条，请更精确搜索';
    }
    $lines[] = str_repeat('─', 30);
    $lines[] = '回复「下载 序号」上传';

    return array('type' => 'text', 'content' => implode("\n", $lines));
}

/**
 * 下载并上传书籍
 */
function handleDownload($botapi, $msg, $num) {
    $userId = $msg['sender']['id'] ?? $msg['chat']['id'] ?? '0';

    $searchData = getSearchResult($userId);
    if ($searchData === false) {
        return array('type' => 'text', 'content' => '❌ 请先使用「找书<关键词>」搜索');
    }

    $idx = $num - 1;
    if ($idx < 0 || $idx >= count($searchData)) {
        return array('type' => 'text', 'content' => '❌ 序号无效，请输入 1 ~ ' . count($searchData));
    }

    $book = $searchData[$idx];
    $fileName = $book['fileName'];
    $token = getBotToken($botapi);
    $recvId = is_array($botapi) ? ($botapi['recvId'] ?? '') : '';
    $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'user') : 'user';

    if (empty($token)) {
        return array('type' => 'text', 'content' => '❌ 无法获取机器人Token');
    }

    if (empty($recvId)) {
        $recvId = $msg['chat']['id'] ?? '';
    }

    // 发送进度提示
    sendProgress($token, $recvId, $recvType, '📥 正在下载「' . $fileName . '」，请稍候...');

    // parserUrl 通常直接返回文件内容（redirectUrl 重定向到实际文件）
    // 也兼容返回 JSON 下载链接的情况
    $parseData = httpGet($book['parserUrl'], 60);
    if ($parseData === false || strlen($parseData) < 100) {
        return array('type' => 'text', 'content' => '❌ 获取文件内容失败');
    }

    $fileData = null;

    // 尝试解析为 JSON（兼容返回下载链接或错误信息的情况）
    $parseJson = json_decode($parseData, true);
    if (is_array($parseJson)) {
        $downloadUrl = '';
        if (!empty($parseJson['success']) && !empty($parseJson['data']) && is_string($parseJson['data'])) {
            $downloadUrl = $parseJson['data'];
        } elseif (!empty($parseJson['url']) && is_string($parseJson['url'])) {
            $downloadUrl = $parseJson['url'];
        } elseif (!empty($parseJson['download']) && is_string($parseJson['download'])) {
            $downloadUrl = $parseJson['download'];
        }

        if (!empty($downloadUrl) && strpos($downloadUrl, 'http') === 0) {
            $fileData = httpGet($downloadUrl, 60);
        }

        // JSON 返回了错误信息
        if (empty($fileData) && !empty($parseJson['message'])) {
            return array('type' => 'text', 'content' => '❌ 解析失败：' . $parseJson['message']);
        }
    } else {
        // 不是 JSON，直接作为文件内容（parserUrl 直接返回文件内容的常见情况）
        $fileData = $parseData;
    }

    if (empty($fileData) || strlen($fileData) < 100) {
        return array('type' => 'text', 'content' => '❌ 下载「' . $fileName . '」失败');
    }

    // 上传到云湖
    $fileKey = \yunhuUploadFile($token, $fileData, $fileName);
    if (empty($fileKey)) {
        $lastError = \yunhuGetLastUploadError();
        $err = $lastError['error'] ?? '未知原因';
        $dbg = !empty($lastError['debug']) ? "\n\n💡 调试信息：{$lastError['debug']}" : '';
        return array('type' => 'text', 'content' => "❌ 上传「{$fileName}」到云湖失败：{$err}{$dbg}");
    }

    // 发送文件
    sendFile($token, $recvId, $recvType, $fileKey, $fileName);
    clearSearchResult($userId);

    return array('type' => 'text', 'content' => '✅ 上传完成：' . $fileName);
}

/**
 * 获取机器人Token
 */
function getBotToken($botapi) {
    if (is_array($botapi) && !empty($botapi['token'])) {
        return $botapi['token'];
    }
    if (is_object($botapi) && !empty($botapi->token)) {
        return $botapi->token;
    }
    return '';
}

/**
 * 获取所有书库完整文件列表
 */
function getBookList() {
    $cacheFile = __DIR__ . '/book_cache.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
        $d = json_decode(file_get_contents($cacheFile), true);
        if (!empty($d)) return $d;
    }

    $sources = array(
        'https://www.ilanzou.com/s/pFLLSCzm',
        'https://www.ilanzou.com/s/4sfn1IDr?code=8888',
        'https://www.ilanzou.com/s/zaan1IQf?code=8888',
        'https://www.ilanzou.com/s/aW3n1IRL?code=8888',
        'https://www.ilanzou.com/s/uErn1Ir3?code=8888',
        'https://www.ilanzou.com/s/iuWn1I9F?code=8888',
        'https://www.ilanzou.com/s/XcOn1Id5?code=8888',
        'https://www.ilanzou.com/s/80an1IaN?code=8888',
    );

    $all = array();
    foreach ($sources as $url) {
        $root = httpGet('https://lz0.qaiu.top/v2/getFileList?url=' . urlencode($url));
        if ($root === false) continue;
        $rj = json_decode($root, true);
        if (empty($rj['success']) || empty($rj['data'])) continue;

        foreach ($rj['data'] as $item) {
            if ($item['fileType'] === 'folder') {
                $sub = httpGet($item['parserUrl']);
                if ($sub === false) continue;
                $sj = json_decode($sub, true);
                if (!empty($sj['success']) && !empty($sj['data'])) {
                    foreach ($sj['data'] as $f) {
                        if ($f['fileType'] === 'file') {
                            $all[] = array(
                                'fileName' => $f['fileName'],
                                'sizeStr'  => $f['sizeStr'] ?? '',
                                'parserUrl'=> $f['parserUrl'],
                            );
                        }
                    }
                }
            } elseif ($item['fileType'] === 'file') {
                $all[] = array(
                    'fileName' => $item['fileName'],
                    'sizeStr'  => $item['sizeStr'] ?? '',
                    'parserUrl'=> $item['parserUrl'],
                );
            }
        }
    }

    if (empty($all)) return false;
    file_put_contents($cacheFile, json_encode($all, JSON_UNESCAPED_UNICODE));
    return $all;
}

/**
 * HTTP GET 请求
 */
function httpGet($url, $timeout = 20) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ));
        $d = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($c == 200 && $d) return $d;
        return false;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'user_agent' => 'Mozilla/5.0')
        ));
        return file_get_contents($url, false, $ctx);
    }
    return false;
}

/**
 * 发送文件消息
 */
function sendFile($token, $recvId, $recvType, $fileKey, $fileName) {
    if (empty($token) || empty($fileKey)) return;
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => 'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . urlencode($token),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(array(
            'recvId'      => $recvId,
            'recvType'    => $recvType,
            'contentType' => 'file',
            'content'     => array('fileKey' => $fileKey, 'fileName' => $fileName),
        )),
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 15,
    ));
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

/**
 * 发送进度提示
 */
function sendProgress($token, $recvId, $recvType, $text) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . urlencode($token),
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode(array(
            'recvId' => $recvId,
            'recvType' => $recvType,
            'contentType' => 'text',
            'content' => array('text' => $text),
        )),
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_TIMEOUT => 5,
    ));
    curl_exec($ch);
    curl_close($ch);
}

// ====== 搜索结果缓存（按用户ID） ======
function saveSearchResult($userId, $results) {
    $f = __DIR__ . '/search_cache.json';
    $all = array();
    if (file_exists($f)) {
        $all = json_decode(file_get_contents($f), true) ?: array();
    }
    $all[$userId] = array('time' => time(), 'books' => $results);
    file_put_contents($f, json_encode($all, JSON_UNESCAPED_UNICODE));
}

function getSearchResult($userId) {
    $f = __DIR__ . '/search_cache.json';
    if (!file_exists($f)) return false;
    $all = json_decode(file_get_contents($f), true);
    if (empty($all[$userId]) || time() - $all[$userId]['time'] > 1800) return false;
    return $all[$userId]['books'];
}

function clearSearchResult($userId) {
    $f = __DIR__ . '/search_cache.json';
    if (!file_exists($f)) return;
    $all = json_decode(file_get_contents($f), true) ?: array();
    unset($all[$userId]);
    file_put_contents($f, json_encode($all, JSON_UNESCAPED_UNICODE));
}
