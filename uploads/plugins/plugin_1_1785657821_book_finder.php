<?php
/**
 * 找书插件 - 搜索书库并上传书籍
 */
$info = array(
    'name' => '找书',
    'author' => '白屿',
    'version' => '2.0.0',
    'desc' => '搜索书库并上传书籍',
    'command_list' => array('找书', 'searchbook', 'book', '下载', '番茄', '解析')
);

function has_been_loaded($info) { return true; }
function group($msg, $botapi) { return runPlugin($msg, $botapi); }
function C2C($msg, $botapi) { return runPlugin($msg, $botapi); }
function button($button_event, $botapi) { return null; }

function runPlugin($msg, $botapi) {
    if (empty($msg['content'])) return null;
    $content = \trim($msg['content']);

    if (\preg_match('/^下载\s*(\d+)$/u', $content, $m)) {
        return handleDownload($botapi, $msg, (int)$m[1]);
    }
    if (\preg_match('/^(番茄|解析)\s*(https?:\/\/\S+)/iu', $content, $m)) {
        return handleTomato($botapi, $msg, $m[2]);
    }
    if (\preg_match('/^(找书|searchbook|book)\s*(.*)$/iu', $content, $m)) {
        return handleSearch($msg, \trim($m[2]));
    }
    return null;
}

/**
 * 番茄小说解析（保持原样）
 */
function handleTomato($botapi, $msg, $url) {
    $token = getBotToken($botapi);
    $recvId = \is_array($botapi) ? ($botapi['recvId'] ?? '') : '';
    $recvType = \is_array($botapi) ? ($botapi['recvType'] ?? 'user') : 'user';

    if (empty($token)) {
        return array('type' => 'text', 'content' => '❌ 无法获取机器人Token');
    }
    if (empty($recvId)) {
        $recvId = $msg['chat']['id'] ?? '';
    }

    sendProgress($token, $recvId, $recvType, '📥 正在解析番茄小说，请稍候...');

    $apiUrl = 'https://api.by111.cn/api/jx/fq.php?apikey=748a062f5afb18ec726d1041fb1a3caf&url=' . \urlencode($url);
    $apiResponse = httpGet($apiUrl, 30);

    if ($apiResponse === false) {
        return array('type' => 'text', 'content' => '❌ 解析请求失败，请稍后重试');
    }

    $fileData = null;
    $jsonData = @\json_decode($apiResponse, true);

    if (\is_array($jsonData)) {
        if (isset($jsonData['code']) && $jsonData['code'] != 0) {
            $errMsg = $jsonData['message'] ?? $jsonData['msg'] ?? '未知错误';
            return array('type' => 'text', 'content' => '❌ 解析失败：' . $errMsg);
        }
        $downloadUrl = '';
        if (!empty($jsonData['url']) && \is_string($jsonData['url'])) {
            $downloadUrl = $jsonData['url'];
        } elseif (!empty($jsonData['download']) && \is_string($jsonData['download'])) {
            $downloadUrl = $jsonData['download'];
        } elseif (!empty($jsonData['data']) && \is_string($jsonData['data'])) {
            $downloadUrl = $jsonData['data'];
        } elseif (!empty($jsonData['data']['url']) && \is_string($jsonData['data']['url'])) {
            $downloadUrl = $jsonData['data']['url'];
        } elseif (!empty($jsonData['data']['download']) && \is_string($jsonData['data']['download'])) {
            $downloadUrl = $jsonData['data']['download'];
        }

        if (!empty($downloadUrl) && \strpos($downloadUrl, 'http') === 0) {
            $fileData = httpGet($downloadUrl, 60);
        } elseif (!empty($jsonData['content']) && \is_string($jsonData['content'])) {
            $fileData = $jsonData['content'];
        } elseif (!empty($jsonData['data']) && \is_string($jsonData['data']) && \strlen($jsonData['data']) > 100) {
            $fileData = $jsonData['data'];
        }
    } else {
        $fileData = $apiResponse;
    }

    if (empty($fileData) || \strlen($fileData) < 100) {
        return array('type' => 'text', 'content' => '❌ 解析失败，未获取到小说内容');
    }

    $fileName = '番茄小说_' . \date('Ymd_His') . '.txt';
    return finishUpload($token, $recvId, $recvType, $fileData, $fileName, $msg);
}

/**
 * 搜索书籍
 */
function handleSearch($msg, $keyword) {
    $userId = $msg['sender']['id'] ?? $msg['chat']['id'] ?? '0';

    if ($keyword === '') {
        return array('type' => 'text', 'content' =>
            "📚 找书插件\n\n"
            . "找书<关键词> - 搜索书库\n"
            . "下载 <序号> - 获取书籍\n"
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
        if (\preg_match('/' . \preg_quote($keyword, '/') . '/iu', $book['fileName'])) {
            $results[] = $book;
        }
    }

    if (empty($results)) {
        return array('type' => 'text', 'content' => '❌ 未找到包含「' . $keyword . '」的书籍');
    }

    saveSearchResult($userId, $results);

    $total = \count($results);
    $show = \min($total, 20);
    $lines = array('📚 找到 ' . $total . ' 本与「' . $keyword . '」相关的书籍：', \str_repeat('─', 30));
    for ($i = 0; $i < $show; $i++) {
        $lines[] = ($i + 1) . '. ' . $results[$i]['fileName'] . '（' . $results[$i]['sizeStr'] . '）';
    }
    if ($total > 20) {
        $lines[] = '… 还有 ' . ($total - 20) . ' 条，请更精确搜索';
    }
    $lines[] = \str_repeat('─', 30);
    $lines[] = '回复「下载 序号」获取';

    return array('type' => 'text', 'content' => \implode("\n", $lines));
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
    if ($idx < 0 || $idx >= \count($searchData)) {
        return array('type' => 'text', 'content' => '❌ 序号无效，请输入 1 ~ ' . \count($searchData));
    }

    $book = $searchData[$idx];
    $fileName = $book['fileName'];
    $token = getBotToken($botapi);
    $recvId = \is_array($botapi) ? ($botapi['recvId'] ?? '') : '';
    $recvType = \is_array($botapi) ? ($botapi['recvType'] ?? 'user') : 'user';

    if (empty($token)) {
        return array('type' => 'text', 'content' => '❌ 无法获取机器人Token');
    }
    if (empty($recvId)) {
        $recvId = $msg['chat']['id'] ?? '';
    }

    sendProgress($token, $recvId, $recvType, '📥 正在下载「' . $fileName . '」，请稍候...');

    // parserUrl 通常直接返回文件内容，兼容返回 JSON 下载链接的情况
    $parseData = httpGet($book['parserUrl'], 60);
    if ($parseData === false || \strlen($parseData) < 100) {
        return array('type' => 'text', 'content' => '❌ 获取文件内容失败');
    }

    $fileData = null;
    $parseJson = @\json_decode($parseData, true);
    if (\is_array($parseJson)) {
        // JSON 响应：尝试提取下载链接
        $downloadUrl = '';
        if (!empty($parseJson['success']) && !empty($parseJson['data']) && \is_string($parseJson['data'])) {
            $downloadUrl = $parseJson['data'];
        } elseif (!empty($parseJson['url']) && \is_string($parseJson['url'])) {
            $downloadUrl = $parseJson['url'];
        } elseif (!empty($parseJson['download']) && \is_string($parseJson['download'])) {
            $downloadUrl = $parseJson['download'];
        }

        if (!empty($downloadUrl) && \strpos($downloadUrl, 'http') === 0) {
            $fileData = httpGet($downloadUrl, 60);
        }
        if (empty($fileData) && !empty($parseJson['message'])) {
            return array('type' => 'text', 'content' => '❌ 解析失败：' . $parseJson['message']);
        }
    } else {
        // 非 JSON，直接作为文件内容
        $fileData = $parseData;
    }

    if (empty($fileData) || \strlen($fileData) < 100) {
        return array('type' => 'text', 'content' => '❌ 下载「' . $fileName . '」失败');
    }

    return finishUpload($token, $recvId, $recvType, $fileData, $fileName, $msg, $userId);
}

/**
 * 统一处理上传 + 发送 + 本地兜底（番茄和下载共用）
 */
function finishUpload($token, $recvId, $recvType, $fileData, $fileName, $msg, $userId = null) {
    $upResult = uploadFile($token, $fileData, $fileName);

    if (!empty($upResult['ok'])) {
        sendFile($token, $recvId, $recvType, $upResult['key'], $fileName);
        if ($userId !== null) clearSearchResult($userId);
        return array('type' => 'text', 'content' => '✅ 上传完成：' . $fileName);
    }

    // 云湖上传失败或跳过，回退到本地存储
    $local = saveBookLocally($fileData, $fileName);
    if ($local['ok'] && !empty($local['url'])) {
        if ($userId !== null) clearSearchResult($userId);
        $isLargeFile = \strlen($fileData) > 1024 * 1024;
        $prefix = $isLargeFile ? '📦 文件较大，已生成本地下载链接：' : '⚠️ 云湖上传失败，已转为本地下载链接：';
        return array('type' => 'text', 'content' =>
            $prefix . "\n\n"
            . "📖 {$fileName}\n"
            . "📦 大小：" . \round(\strlen($fileData) / 1024, 1) . " KB\n"
            . "🔗 下载：{$local['url']}\n\n"
            . "（链接 24 小时内有效）"
        );
    }

    $err = $upResult['error'] ?: '未知原因';
    $localErr = !empty($local['error']) ? "\n📁 本地存储也失败：{$local['error']}" : '';
    $dbg = !empty($upResult['debug']) ? "\n\n💡 调试信息：{$upResult['debug']}" : '';
    return array('type' => 'text', 'content' => "❌ 上传「{$fileName}」失败：{$err}{$localErr}{$dbg}");
}

// ===== 工具函数 =====

function getBotToken($botapi) {
    if (\is_array($botapi) && !empty($botapi['token'])) return $botapi['token'];
    if (\is_object($botapi) && !empty($botapi->token)) return $botapi->token;
    return '';
}

function httpGet($url, $timeout = 20) {
    if (\function_exists('curl_init')) {
        $ch = \curl_init();
        \curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ));
        $d = \curl_exec($ch);
        $c = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);
        if ($c == 200 && $d) return $d;
        return false;
    }
    if (\ini_get('allow_url_fopen')) {
        return @\file_get_contents($url, false, \stream_context_create(array(
            'http' => array('timeout' => $timeout, 'user_agent' => 'Mozilla/5.0')
        )));
    }
    return false;
}

function sendFile($token, $recvId, $recvType, $fileKey, $fileName) {
    if (empty($token) || empty($fileKey)) return;
    $ch = \curl_init();
    \curl_setopt_array($ch, array(
        CURLOPT_URL            => 'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . \urlencode($token),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => \json_encode(array(
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
    \curl_exec($ch);
    \curl_close($ch);
}

function sendProgress($token, $recvId, $recvType, $text) {
    $ch = \curl_init();
    \curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . \urlencode($token),
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => \json_encode(array(
            'recvId' => $recvId,
            'recvType' => $recvType,
            'contentType' => 'text',
            'content' => array('text' => $text),
        )),
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_TIMEOUT => 5,
    ));
    \curl_exec($ch);
    \curl_close($ch);
}

// ===== 书库列表 =====

function getBookList() {
    $cacheFile = __DIR__ . '/book_cache.json';
    if (\file_exists($cacheFile) && (\time() - \filemtime($cacheFile) < 300)) {
        $d = @\json_decode(@\file_get_contents($cacheFile), true);
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
        $root = httpGet('https://lz0.qaiu.top/v2/getFileList?url=' . \urlencode($url));
        if ($root === false) continue;
        $rj = @\json_decode($root, true);
        if (empty($rj['success']) || empty($rj['data'])) continue;

        foreach ($rj['data'] as $item) {
            if ($item['fileType'] === 'folder') {
                $sub = httpGet($item['parserUrl']);
                if ($sub === false) continue;
                $sj = @\json_decode($sub, true);
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
    @\file_put_contents($cacheFile, \json_encode($all, JSON_UNESCAPED_UNICODE));
    return $all;
}

// ===== 云湖上传 =====

/**
 * 上传文件到云湖
 * 大文件(>1MB)直接跳过，小文件尝试 multipart + raw 两种策略
 */
function uploadFile($token, $data, $fileName) {
    $ret = ['ok' => false, 'key' => '', 'error' => '', 'debug' => ''];
    if (empty($token)) { $ret['error'] = '机器人 Token 为空'; return $ret; }

    $size = \strlen($data);
    if ($size < 10) { $ret['error'] = '文件内容为空'; return $ret; }
    if ($size > 20 * 1024 * 1024) {
        $ret['error'] = '文件过大（' . \round($size / 1024 / 1024, 2) . ' MB），限制 20MB';
        return $ret;
    }

    // 大文件直接跳过云湖上传，避免超时等待
    if ($size > 1024 * 1024) {
        $ret['error'] = '文件超过 1MB，云湖上传通道不稳定，直接转本地存储';
        $ret['debug'] = '文件大小 ' . \round($size / 1024 / 1024, 2) . ' MB，跳过上传直接走本地下载';
        return $ret;
    }

    $ext = \strtolower(\pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeMap = [
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'epub' => 'application/epub+zip', 'zip' => 'application/zip',
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    $url = 'https://chat-go.jwzhd.com/open-apis/v1/file/upload?token=' . \urlencode($token);

    // 策略1：multipart/form-data
    $boundary = '----BaiYuBoundary' . \md5(\uniqid(\mt_rand(), true));
    $eol = "\r\n";
    $body = '--' . $boundary . $eol
        . 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . $eol
        . 'Content-Type: ' . $mime . $eol . $eol
        . $data . $eol
        . '--' . $boundary . '--' . $eol;

    $r1 = curlUpload($url, $body, [
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . \strlen($body),
        'Expect: ',
        'Connection: close',
    ]);

    if ($key = extractFileKey($r1['resp'])) {
        return ['ok' => true, 'key' => $key, 'error' => '', 'debug' => 'multipart成功'];
    }

    // 策略2：raw 二进制直传
    $r2 = curlUpload($url, $data, [
        'Content-Type: ' . $mime,
        'Content-Length: ' . $size,
        'Expect: ',
        'Connection: close',
    ]);

    if ($key = extractFileKey($r2['resp'])) {
        return ['ok' => true, 'key' => $key, 'error' => '', 'debug' => 'raw直传成功'];
    }

    // 组装错误
    $errs = [];
    if (!empty($r1['err']))  $errs[] = "multipart: {$r1['err']}";
    if (!empty($r2['err']))  $errs[] = "raw: {$r2['err']}";
    if (empty($errs)) {
        if ($r1['code'] != 200) $errs[] = "multipart HTTP: {$r1['code']}";
        if ($r2['code'] != 200) $errs[] = "raw HTTP: {$r2['code']}";
    }
    $ret['error'] = empty($errs) ? '云湖未返回 fileKey' : \implode('；', $errs);

    $body1 = $r1['resp'] ? \mb_substr($r1['resp'], 0, 150) : '(空)';
    $body2 = $r2['resp'] ? \mb_substr($r2['resp'], 0, 150) : '(空)';
    $ret['debug'] = "文件：{$fileName}，" . \round($size / 1024, 1) . "KB\nmultipart → HTTP{$r1['code']}，响应：{$body1}\nraw → HTTP{$r2['code']}，响应：{$body2}";

    return $ret;
}

/**
 * 公共 cURL POST 上传（禁用 Expect: 100-continue 避免死锁）
 */
function curlUpload($url, $body, array $headers) {
    $ch = \curl_init();
    \curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'YHBot/1.0',
    ));
    $resp = \curl_exec($ch);
    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = \curl_error($ch);
    \curl_close($ch);
    return ['resp' => $resp, 'code' => $code, 'err' => $err];
}

/**
 * 从云湖响应中提取 fileKey
 */
function extractFileKey($resp) {
    if (empty($resp)) return '';
    $d = \json_decode($resp, true);
    if (!\is_array($d)) return '';
    if (!empty($d['data']['fileKey'])) return $d['data']['fileKey'];
    if (!empty($d['fileKey']))         return $d['fileKey'];
    if (!empty($d['data']['key']))      return $d['data']['key'];
    if (!empty($d['key']))              return $d['key'];
    return '';
}

// ===== 本地存储兜底 =====

/**
 * 保存书籍到 uploads/books/，返回下载链接
 * 自动创建 file.php 下载端点
 */
function saveBookLocally($fileData, $fileName) {
    $ext = \strtolower(\pathinfo($fileName, PATHINFO_EXTENSION));
    if (empty($ext)) $ext = 'txt';

    // 向上查找项目根目录（包含 config.php 的目录）
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
    if (empty($projectRoot)) $projectRoot = \dirname(__DIR__);

    $dir = $projectRoot . '/uploads/books';

    // 创建目录
    if (!\is_dir($dir)) {
        if (!\mkdir($dir, 0755, true)) {
            return ['ok' => false, 'url' => '', 'error' => "目录创建失败: {$dir}（权限不足）"];
        }
    }
    if (!\is_writable($dir)) {
        @\chmod($dir, 0755);
        if (!\is_writable($dir)) {
            return ['ok' => false, 'url' => '', 'error' => "目录不可写: {$dir}（请设置 755 权限）"];
        }
    }

    // 自动创建 file.php 下载端点
    $filePhp = $dir . '/file.php';
    if (!\is_file($filePhp)) {
        $content = <<<'FPHP'
<?php
$f = isset($_GET['f']) ? basename($_GET['f']) : '';
$p = __DIR__ . DIRECTORY_SEPARATOR . $f;
if (!preg_match('/^[A-Za-z0-9._-]+$/', $f) || !is_file($p)) {
    http_response_code(404);
    exit('Not Found');
}
$m = [
    'txt' => 'text/plain; charset=utf-8',
    'zip' => 'application/zip',
    'epub' => 'application/epub+zip',
    'pdf' => 'application/pdf',
    'rar' => 'application/x-rar-compressed',
    '7z' => 'application/x-7z-compressed',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$e = strtolower(pathinfo($p, PATHINFO_EXTENSION));
header('Content-Type: ' . ($m[$e] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($p));
header('Content-Disposition: attachment; filename="' . $f . '"');
header('Access-Control-Allow-Origin: *');
readfile($p);
exit;
FPHP;
        if (\file_put_contents($filePhp, $content) === false) {
            return ['ok' => false, 'url' => '', 'error' => "无法创建下载端点 file.php（权限不足）"];
        }
    }

    // 写入文件
    $filename = 'book_' . \date('Ymd_His') . '_' . \substr(\md5(\uniqid('', true)), 0, 8) . '.' . $ext;
    $path = $dir . '/' . $filename;
    $bytes = \file_put_contents($path, $fileData);

    if ($bytes === false || $bytes === 0) {
        $lastErr = \error_get_last();
        return ['ok' => false, 'url' => '', 'error' => "写入失败: " . ($lastErr['message'] ?? '未知') . "（路径: {$path}）"];
    }
    if (!\is_file($path) || \filesize($path) != \strlen($fileData)) {
        return ['ok' => false, 'url' => '', 'error' => "写入验证失败（大小不匹配）"];
    }

    // 生成下载 URL
    $baseUrl = '';
    if (\defined('SITE_URL') && !empty(\SITE_URL)) {
        $baseUrl = \rtrim(\SITE_URL, '/');
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    return ['ok' => true, 'url' => $baseUrl . '/uploads/books/file.php?f=' . $filename, 'path' => $path, 'size' => $bytes];
}

// ===== 搜索结果缓存（按用户ID） =====

function saveSearchResult($userId, $results) {
    $f = __DIR__ . '/search_cache.json';
    $all = [];
    if (\file_exists($f)) {
        $all = \json_decode(@\file_get_contents($f), true) ?: [];
    }
    $all[$userId] = ['time' => \time(), 'books' => $results];
    @\file_put_contents($f, \json_encode($all, JSON_UNESCAPED_UNICODE));
}

function getSearchResult($userId) {
    $f = __DIR__ . '/search_cache.json';
    if (!\file_exists($f)) return false;
    $all = \json_decode(@\file_get_contents($f), true);
    if (empty($all[$userId]) || \time() - $all[$userId]['time'] > 1800) return false;
    return $all[$userId]['books'];
}

function clearSearchResult($userId) {
    $f = __DIR__ . '/search_cache.json';
    if (!\file_exists($f)) return;
    $all = \json_decode(@\file_get_contents($f), true) ?: [];
    unset($all[$userId]);
    @\file_put_contents($f, \json_encode($all, JSON_UNESCAPED_UNICODE));
}
