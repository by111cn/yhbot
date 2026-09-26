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
    
    \yunhuSendMessage($token, $chatId, '正在获取视频，请稍候...', 'text', $recvType);
    
    $videoData = downloadVideo();
    if (!$videoData) {
        return array('type' => 'text', 'content' => '获取视频失败，请稍后再试');
    }
    
    \yunhuSendMessage($token, $chatId, '视频上传中，请稍候...', 'text', $recvType);
    
    $videoKey = \yunhuUploadVideo($token, $videoData);
    if (!$videoKey) {
        return array('type' => 'text', 'content' => '视频上传失败');
    }
    
    return array(
        'type' => 'video',
        'content' => $videoKey
    );
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
