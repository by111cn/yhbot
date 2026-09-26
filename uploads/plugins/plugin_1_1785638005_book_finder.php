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

// ===== 蓝奏云优享版官方 API 配置（备用解析通道）=====
// appToken：蓝奏云优享版账号凭证
if (!\defined('ILANZOU_APP_TOKEN')) {
    \define('ILANZOU_APP_TOKEN', '51659e5901e5b8fe194a5b15d9c7e96c-1');
}
// 书库所在文件夹 ID（蓝奏云优享账号里的文件夹 ID，根目录填 0；留空则跳过官方备用通道）
if (!\defined('ILANZOU_FOLDER_ID')) {
    \define('ILANZOU_FOLDER_ID', '');
}

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
    $jsonData = @json_decode($apiResponse, true);

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
    $upResult = uploadFile($token, $fileData, $fileName);

    if (empty($upResult['ok'])) {
        // 云湖上传失败或跳过，回退到本地存储并返回下载链接
        $local = saveBookLocally($fileData, $fileName);
        if ($local['ok'] && !empty($local['url'])) {
            $isLargeFile = \strlen($fileData) > 1024 * 1024;
            $prefix = $isLargeFile ? '📦 文件较大，已生成本地下载链接：' : '⚠️ 云湖上传失败，已转为本地下载链接：';
            return array('type' => 'text', 'content' =>
                $prefix . "\n\n"
                . "📖 {$fileName}\n"
                . "📦 大小：" . \round(\strlen($fileData)/1024, 1) . " KB\n"
                . "🔗 下载：{$local['url']}\n\n"
                . "（链接 24 小时内有效）"
            );
        }
        $err = $upResult['error'] ?: '未知原因';
        $localErr = !empty($local['error']) ? "\n📁 本地存储也失败：{$local['error']}" : '';
        $dbg = !empty($upResult['debug']) ? "\n\n💡 调试信息：{$upResult['debug']}" : '';
        return array('type' => 'text', 'content' => "❌ 上传到云湖失败：{$err}{$localErr}{$dbg}");
    }
    $fileKey = $upResult['key'];

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
    $parseJson = @json_decode($parseData, true);
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
    $upResult = uploadFile($token, $fileData, $fileName);
    if (empty($upResult['ok'])) {
        // 云湖上传失败或跳过，回退到本地存储并返回下载链接
        $local = saveBookLocally($fileData, $fileName);
        if ($local['ok'] && !empty($local['url'])) {
            clearSearchResult($userId);
            $isLargeFile = \strlen($fileData) > 1024 * 1024;
            $prefix = $isLargeFile ? '📦 文件较大，已生成本地下载链接：' : '⚠️ 云湖上传失败，已转为本地下载链接：';
            return array('type' => 'text', 'content' =>
                $prefix . "\n\n"
                . "📖 {$fileName}\n"
                . "📦 大小：" . \round(\strlen($fileData)/1024, 1) . " KB\n"
                . "🔗 下载：{$local['url']}\n\n"
                . "（链接 24 小时内有效）"
            );
        }
        $err = $upResult['error'] ?: '未知原因';
        $localErr = !empty($local['error']) ? "\n📁 本地存储也失败：{$local['error']}" : '';
        $dbg = !empty($upResult['debug']) ? "\n\n💡 调试信息：{$upResult['debug']}" : '';
        return array('type' => 'text', 'content' => "❌ 上传「{$fileName}」到云湖失败：{$err}{$localErr}{$dbg}");
    }
    $fileKey = $upResult['key'];

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
        $d = @json_decode(@file_get_contents($cacheFile), true);
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
        $rj = @json_decode($root, true);
        if (empty($rj['success']) || empty($rj['data'])) continue;

        foreach ($rj['data'] as $item) {
            if ($item['fileType'] === 'folder') {
                $sub = httpGet($item['parserUrl']);
                if ($sub === false) continue;
                $sj = @json_decode($sub, true);
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
    @file_put_contents($cacheFile, json_encode($all, JSON_UNESCAPED_UNICODE));
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
        return @file_get_contents($url, false, stream_context_create(array(
            'http' => array('timeout' => $timeout, 'user_agent' => 'Mozilla/5.0')
        )));
    }
    return false;
}

/**
 * 保存书籍到服务器本地（云湖上传失败时的兜底方案）
 * 专用目录：uploads/books/，有独立 file.php 下载端点
 * 返回：['ok' => true, 'url' => '下载链接']
 *       ['ok' => false, 'url' => '', 'error' => '错误原因']
 */
function saveBookLocally($fileData, $fileName) {
    $ext = \strtolower(\pathinfo($fileName, PATHINFO_EXTENSION));
    if (empty($ext)) $ext = 'txt';

    // 向上查找项目根目录（包含 config.php 的目录）
    // __DIR__ 在插件运行时可能是 uploads/plugins，需要向上查找避免路径重复
    $projectRoot = '';
    $testDir = __DIR__;
    for ($i = 0; $i < 5; $i++) {
        if (\is_file($testDir . '/config.php')) {
            $projectRoot = $testDir;
            break;
        }
        $parent = \dirname($testDir);
        if ($parent === $testDir) break;
        $testDir = $parent;
    }

    if (empty($projectRoot)) {
        // 兜底：使用 dirname(__DIR__)（可能在 uploads/plugins 场景下路径重复，但至少能工作）
        $projectRoot = \dirname(__DIR__);
    }

    $dir = $projectRoot . '/uploads/books';
    $relDir = 'uploads/books';

    // 1. 目录不存在则尝试创建
    if (!\is_dir($dir)) {
        $mk = \mkdir($dir, 0755, true);
        if (!$mk) {
            $lastErr = \error_get_last();
            $errInfo = $lastErr ? $lastErr['message'] : '未知原因';
            return ['ok' => false, 'url' => '', 'error' => "目录创建失败: {$errInfo} ({$dir})"];
        }
    }

    // 2. 目录不可写？
    if (!\is_writable($dir)) {
        // 尝试 chmod
        @\chmod($dir, 0755);
        if (!\is_writable($dir)) {
            return ['ok' => false, 'url' => '', 'error' => "目录不可写，请设置权限: {$dir} (当前权限: " . \substr(\sprintf('%o', \fileperms($dir)), -4) . ")"];
        }
    }

    // 3. 检查 file.php 下载端点，不存在则自动创建（最小可用版本）
    $filePhp = $dir . '/file.php';
    if (!\is_file($filePhp)) {
        $filePhpContent = '<?php' . "\n"
            . '$f = isset($_GET[\'f\']) ? basename($_GET[\'f\']) : \'\';' . "\n"
            . '$p = __DIR__ . DIRECTORY_SEPARATOR . $f;' . "\n"
            . 'if (!preg_match(\'/^[A-Za-z0-9._-]+$/\', $f) || !is_file($p)) { http_response_code(404); exit(\'Not Found\'); }' . "\n"
            . '$ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));' . "\n"
            . '$m = [\'txt\'=>\'text/plain; charset=utf-8\',\'zip\'=>\'application/zip\',\'epub\'=>\'application/epub+zip\',\'pdf\'=>\'application/pdf\',\'rar\'=>\'application/x-rar-compressed\',\'7z\'=>\'application/x-7z-compressed\',\'doc\'=>\'application/msword\',\'docx\'=>\'application/vnd.openxmlformats-officedocument.wordprocessingml.document\'];' . "\n"
            . 'header(\'Content-Type: \' . ($m[$ext] ?? \'application/octet-stream\'));' . "\n"
            . 'header(\'Content-Length: \' . filesize($p));' . "\n"
            . 'header(\'Content-Disposition: attachment; filename="\' . $f . \'"\' . \');' . "\n"
            . 'header(\'Access-Control-Allow-Origin: *\');' . "\n"
            . 'readfile($p);' . "\n"
            . 'exit;' . "\n";
        $writeResult = \file_put_contents($filePhp, $filePhpContent);
        if ($writeResult === false) {
            return ['ok' => false, 'url' => '', 'error' => "无法创建下载端点 file.php: {$dir}/file.php (目录权限不足)"];
        }
    }

    // 4. 生成文件名（全英文，兼容 file.php 正则校验）
    $filename = 'book_' . \date('Ymd_His') . '_' . \substr(\md5(\uniqid('', true)), 0, 8) . '.' . $ext;
    $path = $dir . '/' . $filename;

    // 5. 写入文件
    $bytes = \file_put_contents($path, $fileData);
    if ($bytes === false || $bytes === 0) {
        $lastErr = \error_get_last();
        $errInfo = $lastErr ? $lastErr['message'] : '未知原因';
        $dirFree = \disk_free_space($dir) !== false ? \round(\disk_free_space($dir) / 1024 / 1024, 1) . ' MB' : '未知';
        return ['ok' => false, 'url' => '', 'error' => "写入文件失败: {$errInfo} (路径: {$path}, 数据大小: " . \strlen($fileData) . " 字节, 磁盘剩余: {$dirFree})"];
    }

    // 6. 验证写入成功
    if (!\is_file($path) || \filesize($path) != \strlen($fileData)) {
        return ['ok' => false, 'url' => '', 'error' => "写入验证失败: 写入后大小不匹配 (期望: " . \strlen($fileData) . ", 实际: " . (\is_file($path) ? \filesize($path) : '文件不存在') . ")"];
    }

    // 7. 生成下载 URL
    $baseUrl = '';
    if (\defined('SITE_URL') && !empty(\SITE_URL)) {
        $baseUrl = \rtrim(\SITE_URL, '/');
    } else {
        // SITE_URL 未定义，尝试从 $_SERVER 动态构造
        if (isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }
    }

    $url = $baseUrl . '/' . $relDir . '/file.php?f=' . $filename;

    return ['ok' => true, 'url' => $url, 'path' => $path, 'size' => $bytes];
}

/**
 * 上传文件到云湖
 * 优先调用平台官方 \yunhuUploadFile，失败后手动构建 multipart 重试
 * 返回：成功 => ['ok' => true, 'key' => 'xxx']
 *       失败 => ['ok' => false, 'error' => '原因', 'debug' => '详细信息']
 */
function uploadFile($token, $data, $fileName) {
    $ret = ['ok' => false, 'key' => '', 'error' => '', 'debug' => ''];
    if (empty($token)) { $ret['error'] = '机器人 Token 为空'; return $ret; }
    $size = strlen($data);
    if ($size < 10) { $ret['error'] = '文件内容为空'; return $ret; }
    if ($size > 20 * 1024 * 1024) {
        $ret['error'] = '文件过大（' . round($size/1024/1024, 2) . ' MB），限制 20MB';
        return $ret;
    }

    // 大文件（> 1MB）直接跳过云湖上传，避免 30s 超时等待
    // 已知云湖 API 对此服务器的大文件上传稳定失败（HTTP 0 + 0 bytes）
    if ($size > 1024 * 1024) {
        $ret['error'] = '文件超过 1MB，云湖上传通道不稳定，直接转本地存储';
        $ret['debug'] = '文件大小 ' . round($size/1024/1024, 2) . ' MB，超过云湖上传阈值（1MB），跳过上传直接走本地下载';
        return $ret;
    }

    $sizeKb = round($size / 1024, 1);

    // MIME
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeMap = [
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'epub' => 'application/epub+zip', 'zip' => 'application/zip',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';

    $url = 'https://chat-go.jwzhd.com/open-apis/v1/file/upload?token=' . urlencode($token);

    // ===== 策略1：multipart/form-data 字符串上传（HTTP/1.1）=====
    // 关键：禁用 Expect: 100-continue，否则大文件上传会死锁
    $boundary = '----BaiYuBoundary' . md5(uniqid(mt_rand(), true));
    $eol = "\r\n";
    $body = '';
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . $eol;
    $body .= 'Content-Type: ' . $mime . $eol . $eol;
    $body .= $data . $eol;
    $body .= '--' . $boundary . '--' . $eol;
    $bodyLen = strlen($body);

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . $bodyLen,
            'Expect: ',
            'Connection: close',
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'YHBot/1.0',
    ));
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($code == 200 && !empty($r)) {
        $d = json_decode($r, true);
        if (!empty($d['data']['fileKey'])) return ['ok'=>true,'key'=>$d['data']['fileKey'],'error'=>'','debug'=>'multipart成功'];
        if (!empty($d['fileKey']))         return ['ok'=>true,'key'=>$d['fileKey'],'error'=>'','debug'=>'multipart成功'];
        if (!empty($d['data']['key']))      return ['ok'=>true,'key'=>$d['data']['key'],'error'=>'','debug'=>'multipart成功'];
        if (!empty($d['key']))              return ['ok'=>true,'key'=>$d['key'],'error'=>'','debug'=>'multipart成功'];
        if (!empty($d['message'])) $ret['error'] = $d['message'];
        elseif (!empty($d['msg'])) $ret['error'] = $d['msg'];
    }

    // ===== 策略2：raw 二进制直传（HTTP/1.1 + 禁用 Expect）=====
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: ' . $mime,
            'Content-Length: ' . $size,
            'Expect: ',
            'Connection: close',
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'YHBot/1.0',
    ));
    $r2 = curl_exec($ch);
    $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err2 = curl_error($ch);
    curl_close($ch);

    if ($code2 == 200 && !empty($r2)) {
        $d = json_decode($r2, true);
        if (!empty($d['data']['fileKey'])) return ['ok'=>true,'key'=>$d['data']['fileKey'],'error'=>'','debug'=>'raw直传成功'];
        if (!empty($d['fileKey']))         return ['ok'=>true,'key'=>$d['fileKey'],'error'=>'','debug'=>'raw直传成功'];
        if (!empty($d['data']['key']))      return ['ok'=>true,'key'=>$d['data']['key'],'error'=>'','debug'=>'raw直传成功'];
        if (!empty($d['key']))              return ['ok'=>true,'key'=>$d['key'],'error'=>'','debug'=>'raw直传成功'];
        if (!empty($d['message'])) $ret['error'] = $d['message'];
        elseif (!empty($d['msg'])) $ret['error'] = $d['msg'];
    }

    // 组装错误
    if (empty($ret['error'])) {
        $errs = [];
        if (!empty($err))  $errs[] = "multipart: {$err}";
        if (!empty($err2)) $errs[] = "raw: {$err2}";
        if (empty($errs) && $code != 200) $errs[] = "multipart HTTP: {$code}";
        if (empty($errs) && $code2 != 200) $errs[] = "raw HTTP: {$code2}";
        $ret['error'] = empty($errs) ? '云湖未返回 fileKey' : implode('；', $errs);
    }

    $body1 = $r ? mb_substr($r, 0, 150) : '(空)';
    $body2 = $r2 ? mb_substr($r2, 0, 150) : '(空)';
    $ret['debug'] = "文件：{$fileName}，{$sizeKb}KB\nmultipart → HTTP{$code}，响应：{$body1}\nraw → HTTP{$code2}，响应：{$body2}";

    return $ret;
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
        $all = json_decode(@file_get_contents($f), true) ?: array();
    }
    $all[$userId] = array('time' => time(), 'books' => $results);
    @file_put_contents($f, json_encode($all, JSON_UNESCAPED_UNICODE));
}

function getSearchResult($userId) {
    $f = __DIR__ . '/search_cache.json';
    if (!file_exists($f)) return false;
    $all = json_decode(@file_get_contents($f), true);
    if (empty($all[$userId]) || time() - $all[$userId]['time'] > 1800) return false;
    return $all[$userId]['books'];
}

function clearSearchResult($userId) {
    $f = __DIR__ . '/search_cache.json';
    if (!file_exists($f)) return;
    $all = json_decode(@file_get_contents($f), true) ?: array();
    unset($all[$userId]);
    @file_put_contents($f, json_encode($all, JSON_UNESCAPED_UNICODE));
}