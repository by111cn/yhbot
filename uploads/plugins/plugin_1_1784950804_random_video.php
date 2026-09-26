<?php
$info = array(
    'name' => '随机视频',
    'author' => '白屿',
    'version' => '1.0.0',
    'desc' => '发送随机视频，自动上传到云湖',
    'command_list' => array('随机视频', 'video', 'shuixin')
);

$plugin_bot_token = '';

function has_been_loaded($info) {
    return true;
}

function group($msg, $botapi) {
    return runPlugin($msg, 'group', $botapi);
}

function C2C($msg, $botapi) {
    return runPlugin($msg, 'private', $botapi);
}

function button($button_event, $botapi) {
    return null;
}

function runPlugin($msg, $chatType, $botapi) {
    global $plugin_bot_token;
    
    $content = trim($msg['content'] ?? '');
    $cmd = array('随机视频', 'video', 'shuixin');
    
    $matched = false;
    foreach ($cmd as $c) {
        if ($content === $c || strpos($content, $c . ' ') === 0) {
            $matched = true;
            break;
        }
    }
    
    if (!$matched) {
        return null;
    }
    
    $chatId = $msg['chat']['id'] ?? '';
    $recvType = ($chatType === 'group') ? 'group' : 'user';
    
    $token = '';
    if (is_object($botapi) && isset($botapi->token)) {
        $token = $botapi->token;
    } elseif (is_array($botapi) && isset($botapi['token'])) {
        $token = $botapi['token'];
    } elseif (!empty($plugin_bot_token)) {
        $token = $plugin_bot_token;
    }
    
    if (empty($token)) {
        return array('type' => 'text', 'content' => '未找到机器人Token');
    }
    
    sendText($token, $chatId, $recvType, '正在获取视频，请稍候...');
    
    $videoData = downloadVideo();
    if (!$videoData) {
        return array('type' => 'text', 'content' => '获取视频失败，请稍后再试');
    }
    
    sendText($token, $chatId, $recvType, '视频上传中，请稍候...');
    
    $videoKey = uploadToYunhu($token, $videoData);
    if (!$videoKey) {
        return array('type' => 'text', 'content' => '视频上传失败');
    }
    
    return array(
        'type' => 'video',
        'content' => $videoKey
    );
}

function sendText($token, $recvId, $recvType, $text) {
    $body = array(
        'recvId' => $recvId,
        'recvType' => $recvType,
        'contentType' => 'text',
        'content' => array('text' => $text)
    );
    $url = 'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . urlencode($token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function uploadToYunhu($token, $videoData, $filename = 'video.mp4') {
    // token 作为 URL 参数传入（云湖官方要求）
    $url = 'https://chat-go.jwzhd.com/open-apis/v1/video/upload?token=' . urlencode($token);
    
    // 保存到临时文件（兼容 PHP 7）
    $tmpFile = tempnam(sys_get_temp_dir(), 'video_') . '.mp4';
    file_put_contents($tmpFile, $videoData);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => ['video' => new CURLFile($tmpFile, 'video/mp4', $filename)],
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_TIMEOUT => 60,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    
    // 删除临时文件
    @unlink($tmpFile);
    
    $d = json_decode($r, true);
    if ($d && isset($d['data']['videoKey'])) {
        return $d['data']['videoKey'];
    }
    if ($d && isset($d['videoKey'])) {
        return $d['videoKey'];
    }
    
    return null;
}

function downloadVideo() {
    $apis = array(
        'https://api.by111.cn/api/sp/sc.php',
        'https://api.by111.cn/api/sp/mn.php',
        'https://api.by111.cn/api/sp/hs.php',
        'https://api.by111.cn/api/sp/bs.php'
    );
    
    shuffle($apis);
    
    foreach ($apis as $api) {
        $ch = curl_init($api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $r = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode == 200 && $r) {
            $d = json_decode($r, true);
            if ($d) {
                $videoUrl = '';
                if (!empty($d['url'])) $videoUrl = $d['url'];
                elseif (!empty($d['data']['url'])) $videoUrl = $d['data']['url'];
                elseif (!empty($d['video'])) $videoUrl = $d['video'];
                elseif (!empty($d['data'])) $videoUrl = $d['data'];
                
                if ($videoUrl && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    $videoData = fetchVideo($videoUrl);
                    if ($videoData) return $videoData;
                }
            }
            
            if (strpos($contentType, 'video') !== false) {
                return $r;
            }
        }
    }
    
    return false;
}

function fetchVideo($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $r = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $r && strlen($r) > 1024) {
        return $r;
    }
    return false;
}
