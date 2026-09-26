<?php
/**
 * 斗地主插件
 * 支持群聊多人游戏，AI自动补位
 *
 * 命令列表：
 *   斗地主 / 创建房间  - 创建游戏房间
 *   加入               - 加入房间
 *   开始               - 开始游戏（需3人，不足自动补AI）
 *   叫地主 / 不叫       - 叫地主阶段
 *   抢 / 不抢           - 抢地主阶段
 *   出 <牌面>          - 出牌，如 出 3344 或 出 3 3 4 4
 *   不要 / 过           - 不出牌
 *   手牌               - 私聊查看手牌
 *   桌面               - 查看当前桌面信息
 *   规则               - 查看玩法
 *   退出               - 退出房间
 */

$info = array(
    'name'         => '斗地主',
    'version'      => '1.0.0',
    'author'       => '白屿',
    'desc'         => '经典斗地主，支持群聊多人对战、AI补位、全部牌型',
    'command_list' => array('斗地主', '创建房间', '加入', '开始', '叫地主', '不叫', '抢', '不抢', '不要', '过', '手牌', '桌面', '规则', '退出'),
);

function has_been_loaded($info) { return true; }

function group($msg, $botapi) {
    return runPlugin($msg, 'group', $botapi);
}

function C2C($msg, $botapi) {
    return runPlugin($msg, 'private', $botapi);
}

function button($button_event, $botapi) { return null; }

// ==================================================
// 主路由
// ==================================================
function runPlugin($msg, $chatType, $botapi) {
    $content = trim($msg['content'] ?? '');
    if ($content === '') return null;

    $chatId = $msg['chat']['id'] ?? '';
    $userId = $msg['sender']['id'] ?? '';
    $userName = $msg['sender']['name'] ?? '玩家';

    // 私聊只处理「手牌」命令
    if ($chatType === 'private') {
        if ($content === '手牌' || $content === '看牌') {
            return handlePrivateHand($chatId, $userId);
        }
        return null;
    }

    // ===== 超时检测（60秒无行动自动跳过下一位）=====
    $timeoutResult = checkTimeout($chatId, $botapi);
    if ($timeoutResult !== null) {
        // 超时并处理过跳转，正常继续本次命令
        // 但如果本次请求的玩家正好是超时玩家或当前玩家已经发生变化，仍按原逻辑执行
        // 这里直接把消息文本在超时后返回继续走正常流程
    }

    // 群聊命令匹配
    // 斗地主 / 创建房间
    if ($content === '斗地主' || $content === '创建房间') {
        return handleCreateRoom($chatId, $userId, $userName);
    }
    // 加入
    if ($content === '加入') {
        return handleJoin($chatId, $userId, $userName);
    }
    // 开始
    if ($content === '开始') {
        return handleStart($chatId, $userId, $botapi);
    }
    // 叫地主
    if ($content === '叫地主' || $content === '叫') {
        return handleBid($chatId, $userId, true, $botapi);
    }
    // 不叫
    if ($content === '不叫') {
        return handleBid($chatId, $userId, false, $botapi);
    }
    // 抢
    if ($content === '抢') {
        return handleGrab($chatId, $userId, true, $botapi);
    }
    // 不抢
    if ($content === '不抢') {
        return handleGrab($chatId, $userId, false, $botapi);
    }
    // 出牌
    if (preg_match('/^出\s*(.+)$/u', $content, $m)) {
        return handlePlay($chatId, $userId, trim($m[1]), $botapi);
    }
    // 不要 / 过
    if ($content === '不要' || $content === '过' || $content === 'pass') {
        return handlePass($chatId, $userId, $botapi);
    }
    // 手牌
    if ($content === '手牌' || $content === '看牌') {
        return handleHand($chatId, $userId, $botapi);
    }
    // 桌面
    if ($content === '桌面' || $content === '状态') {
        return handleTableInfo($chatId);
    }
    // 规则
    if ($content === '规则' || $content === '玩法') {
        return handleRules();
    }
    // 帮助
    if ($content === '帮助' || $content === 'help' || $content === '指令' || $content === '命令') {
        return handleHelp();
    }
    // 退出
    if ($content === '退出') {
        return handleQuit($chatId, $userId);
    }

    return null;
}

// ==================================================
// 游戏状态持久化
// ==================================================
function gameStateFile($chatId) {
    $dir = __DIR__ . '/ddz_data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/game_' . preg_replace('/[^a-zA-Z0-9_]/', '', $chatId) . '.json';
}

function loadGame($chatId) {
    $f = gameStateFile($chatId);
    if (!file_exists($f)) return null;
    $data = @json_decode(@file_get_contents($f), true);
    if (!is_array($data)) return null;
    // 超时清理（1小时无操作）
    if (isset($data['updatedAt']) && time() - $data['updatedAt'] > 3600) {
        @unlink($f);
        return null;
    }
    return $data;
}

function saveGame($chatId, $game) {
    $now = time();
    $game['updatedAt'] = $now;

    // 自动设置/重置回合计时器
    // 构造一个回合标识 = 阶段 + 当前行动玩家
    $phase = $game['phase'] ?? '';
    if ($phase === 'bidding') {
        $curIdx = $game['bidPlayer'] ?? -1;
    } elseif ($phase === 'grabbing') {
        $curIdx = $game['grabCurrent'] ?? -1;
    } elseif ($phase === 'playing') {
        $curIdx = $game['currentPlayer'] ?? -1;
    } else {
        $curIdx = -1;
    }
    $turnKey = $phase . '|' . $curIdx;

    // 若和上次的 turnKey 不同（或未设置过），重设计时器
    if ($curIdx >= 0) {
        if (!isset($game['_lastTurnKey']) || $game['_lastTurnKey'] !== $turnKey) {
            // 新玩家进入回合 → 设置计时
            $game['currentTurnStartedAt'] = $now;
            $game['_lastTurnKey'] = $turnKey;
        } elseif (!isset($game['currentTurnStartedAt']) || $game['currentTurnStartedAt'] <= 0) {
            // 没有计时器也要补上
            $game['currentTurnStartedAt'] = $now;
            if (!isset($game['_lastTurnKey'])) $game['_lastTurnKey'] = $turnKey;
        }
    }

    $f = gameStateFile($chatId);
    @file_put_contents($f, json_encode($game, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function deleteGame($chatId) {
    $f = gameStateFile($chatId);
    if (file_exists($f)) @unlink($f);
}

// ==================================================
// 超时自动跳转（60秒无行动自动跳下一位）
// ==================================================
/**
 * 每次有人发消息时检测，如果当前应该行动的真人玩家已超过60秒无动作，
 * 自动视为"不要/不叫/不抢"并跳到下一位；若下一位仍超时则链式处理
 * （直到找到AI玩家或回到原行动者或完成一轮）
 */
function checkTimeout($chatId, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) return null;
    $phase = $game['phase'];
    if ($phase !== 'bidding' && $phase !== 'grabbing' && $phase !== 'playing') return null;

    $now = time();
    $limit = 60;
    $changed = false;
    $msgs = [];

    // 最多处理 3 轮跳转，避免死循环（3人一轮最多跳3次）
    for ($i = 0; $i < 5; $i++) {
        $game = loadGame($chatId);
        if ($game === null) break;

        if ($phase !== $game['phase']) break; // 阶段变了就停

        // 定位当前行动玩家
        if ($game['phase'] === 'bidding') {
            $curIdx = $game['bidPlayer'];
        } elseif ($game['phase'] === 'grabbing') {
            $curIdx = $game['grabCurrent'] ?? 0;
        } else {
            $curIdx = $game['currentPlayer'];
        }
        $curPlayer = $game['players'][$curIdx] ?? null;
        if ($curPlayer === null) break;

        // AI 不会超时（AI会自动出牌）
        if (!empty($curPlayer['isAI'])) break;

        // 如果没有开始计时，设置一次
        $startedAt = $game['currentTurnStartedAt'] ?? 0;
        if ($startedAt <= 0) {
            $game['currentTurnStartedAt'] = $now;
            saveGame($chatId, $game);
            break;
        }

        // 未超时
        if ($now - $startedAt < $limit) break;

        // 超时：自动跳
        $phase = $game['phase'];
        if ($phase === 'bidding') {
            $result = autoSkipBid($chatId, $game, $botapi);
            if ($result) $msgs[] = $result;
        } elseif ($phase === 'grabbing') {
            $result = autoSkipGrab($chatId, $game, $botapi);
            if ($result) $msgs[] = $result;
        } else {
            $result = autoSkipPass($chatId, $game, $botapi);
            if ($result) $msgs[] = $result;
        }
        $changed = true;

        // 如果阶段变了（例如叫地主到抢地主，或确定了地主）就停
        $ng = loadGame($chatId);
        if ($ng === null || $ng['phase'] !== $phase) break;
    }

    if (!$changed) return null;

    // 主动发送超时消息
    $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
    $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
    $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
    if ($token && !empty($msgs)) {
        $text = implode("\n\n", $msgs);
        \yunhuSendMessage($token, $recvId, $text, 'text', $recvType);
    }
    return ['handled' => true];
}

/** 叫地主阶段：真人超时 → 自动不叫 */
function autoSkipBid($chatId, $game, $botapi) {
    $idx = $game['bidPlayer'];
    $player = $game['players'][$idx];
    $player['name'] = $player['name']; // 防止未定义

    $game['bidHistory'][] = ['player' => $idx, 'bid' => false];
    $next = ($idx + 1) % 3;

    $allPass = true;
    foreach ($game['bidHistory'] as $h) {
        if (!empty($h['bid'])) { $allPass = false; break; }
    }
    if (count($game['bidHistory']) >= 3 && $allPass) {
        deleteGame($chatId);
        return "⏱️ {$player['name']} 60秒未行动，自动不叫\n\n🙅 所有人都不叫地主，重新开始！\n发送「斗地主」创建新房间";
    }

    $game['bidPlayer'] = $next;
    $game['currentTurnStartedAt'] = time();
    saveGame($chatId, $game);

    $nextPlayer = $game['players'][$next];
    $msg = "⏱️ {$player['name']} 60秒未行动，自动不叫\n\n{$nextPlayer['name']} 叫地主\n发送「叫地主」或「不叫」";

    // 如果下家是AI，自动处理
    if (!empty($nextPlayer['isAI'])) {
        $aiResult = aiBid($chatId, $game, $botapi);
        if ($aiResult && is_array($aiResult)) {
            $msg .= "\n\n" . ($aiResult['content'] ?? '');
        }
    }
    return $msg;
}

/** 抢地主阶段：真人超时 → 自动不抢 */
function autoSkipGrab($chatId, $game, $botapi) {
    $idx = $game['grabCurrent'] ?? 0;
    $player = $game['players'][$idx];

    $game['grabCurrent'] = ($idx + 1) % 3;
    $start = $game['grabStart'] ?? 0;

    if ($game['grabCurrent'] === $start) {
        $game['bidPlayer'] = $start;
        // 地主确定
        $r = confirmLandlord($chatId, $game, $botapi);
        $c = is_array($r) ? ($r['content'] ?? '') : '';
        return "⏱️ {$player['name']} 60秒未行动，自动不抢\n\n" . $c;
    }

    $game['currentTurnStartedAt'] = time();
    saveGame($chatId, $game);

    $nextPlayer = $game['players'][$game['grabCurrent']];
    $msg = "⏱️ {$player['name']} 60秒未行动，自动不抢\n\n{$nextPlayer['name']} 可以抢\n发送「抢」或「不抢」";
    if (!empty($nextPlayer['isAI'])) {
        $aiResult = aiGrab($chatId, $game, $botapi);
        if ($aiResult && is_array($aiResult)) {
            $msg .= "\n\n" . ($aiResult['content'] ?? '');
        }
    }
    return $msg;
}

/** 出牌阶段：真人超时 → 自动不要 */
function autoSkipPass($chatId, $game, $botapi) {
    $idx = $game['currentPlayer'];
    $player = $game['players'][$idx];

    // 首出不能不要 → 这种情况直接跳过，并强制触发AI或继续下一位
    if ($game['lastPlay'] === null || $game['passCount'] >= 2) {
        // 首出超时：AI帮忙出一张最小的单（如果是AI就直接AI出牌逻辑）
        // 真人首出超时：直接进入AI打牌流程找最小的出
        return autoSkipLead($chatId, $game, $botapi, $player);
    }

    $game['passCount']++;
    $nextIdx = ($idx + 1) % 3;
    $game['currentPlayer'] = $nextIdx;

    $landlordTag = !empty($player['isLandlord']) ? '👑' : '🧑‍🌾';
    $nextPlayer = $game['players'][$nextIdx];

    if ($game['passCount'] >= 2) {
        $lastIdx = $game['lastPlay']['player'];
        $game['currentPlayer'] = $lastIdx;
        $game['lastPlay'] = null;
        $game['passCount'] = 0;
        $game['currentTurnStartedAt'] = time();
        saveGame($chatId, $game);
        $last = $game['players'][$lastIdx];
        $msg = "⏱️ {$landlordTag} {$player['name']} 60秒未行动，自动不要\n" . str_repeat('─', 20) . "\n其他玩家都不要，{$last['name']} 自由出牌";
        if (!empty($last['isAI'])) {
            usleep(300000);
            $aiR = aiPlay($chatId, $game, $botapi);
            if ($aiR && is_array($aiR)) $msg .= "\n\n" . ($aiR['content'] ?? '');
        }
        return $msg;
    }

    $game['currentTurnStartedAt'] = time();
    saveGame($chatId, $game);
    $msg = "⏱️ {$landlordTag} {$player['name']} 60秒未行动，自动不要\n轮到 {$nextPlayer['name']} 出牌";
    if (!empty($nextPlayer['isAI'])) {
        usleep(300000);
        $aiR = aiPlay($chatId, $game, $botapi);
        if ($aiR && is_array($aiR)) $msg .= "\n\n" . ($aiR['content'] ?? '');
    }
    return $msg;
}

/** 首出超时：出一张最小的单牌或对子，继续游戏 */
function autoSkipLead($chatId, $game, $botapi, $player) {
    $idx = $game['currentPlayer'];
    $hand = $game['players'][$idx]['cards'];
    if (empty($hand)) return null;
    sort($hand, SORT_NUMERIC);

    // 找最小的单牌出（但不出王/2，除非只剩这些）
    $smallest = null;
    foreach ($hand as $c) {
        if ($c < 15) { $smallest = $c; break; }
    }
    if ($smallest === null) $smallest = $hand[0];
    $playCards = [$smallest];

    // 从手牌移除
    $tempHand = $hand;
    $i = array_search($smallest, $tempHand);
    if ($i !== false) unset($tempHand[$i]);
    $tempHand = array_values($tempHand);

    $game['players'][$idx]['cards'] = $tempHand;
    $type = analyzeCards($playCards);
    $game['lastPlay'] = ['player' => $idx, 'cards' => $playCards, 'type' => $type];
    $game['passCount'] = 0;

    $remaining = count($tempHand);
    $playedStr = cardsToStr($playCards);
    $landlordTag = !empty($player['isLandlord']) ? '👑' : '🧑‍🌾';
    $msg = "⏱️ {$landlordTag} {$player['name']} 60秒未行动，自动出牌：{$playedStr}（单张）\n剩余 {$remaining} 张";

    if ($remaining === 0) {
        $game['phase'] = 'finished';
        $game['winner'] = $idx;
        saveGame($chatId, $game);
        $endR = handleGameEnd($chatId, $game);
        $endText = is_array($endR) ? ($endR['content'] ?? '') : '';
        return $msg . "\n\n" . $endText;
    }

    $nextIdx = ($idx + 1) % 3;
    $game['currentPlayer'] = $nextIdx;
    $game['currentTurnStartedAt'] = time();
    saveGame($chatId, $game);

    $nextPlayer = $game['players'][$nextIdx];
    $msg .= "\n\n轮到 {$nextPlayer['name']} 出牌";
    if (!empty($nextPlayer['isAI'])) {
        usleep(300000);
        $aiR = aiPlay($chatId, $game, $botapi);
        if ($aiR && is_array($aiR)) $msg .= "\n\n" . ($aiR['content'] ?? '');
    }

    // 超时出牌的真人玩家：私聊通知剩余手牌
    if (empty($player['isAI'])) {
        $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
        $role = !empty($player['isLandlord']) ? '👑地主' : '🧑‍🌾农民';
        $handText = "⏱️ 系统代你自动出牌：{$playedStr}\n\n当前手牌（{$role}，共 {$remaining} 张）：\n" . cardsToStr($tempHand);
        sendPrivateMsg($token, $player['id'], $handText);
    }

    return $msg;
}

/** 更新回合计时器（切换玩家时调用）*/
function updateTurnTimer(&$game) {
    $game['currentTurnStartedAt'] = time();
}

// ==================================================
// 牌值工具
// ==================================================
// 牌值: 3=3, 4=4,..., 9=9, 10=10, J=11, Q=12, K=13, A=14, 2=15, 小王=16, 大王=17
function cardName($val) {
    if ($val <= 10) return (string)$val;
    $map = [11 => 'J', 12 => 'Q', 13 => 'K', 14 => 'A', 15 => '2', 16 => '小🃏', 17 => '大🃏'];
    return $map[$val] ?? '?';
}

function cardsToStr($cards) {
    $parts = [];
    foreach ($cards as $c) $parts[] = cardName($c);
    return implode(' ', $parts);
}

// 解析用户输入的牌面 → 牌值数组
function parseCards($input) {
    $input = trim($input);
    if ($input === '') return [];

    // 统一分隔符：空格、逗号、横杠 → 统一处理
    $input = preg_replace('/[\s,\-，]+/u', ' ', $input);
    $input = str_replace('10', 'T', $input); // 10 → T 临时替换

    $tokens = explode(' ', $input);
    $result = [];
    foreach ($tokens as $t) {
        $t = trim($t);
        if ($t === '') continue;
        $lower = strtolower($t);
        $val = null;
        if ($t === 'T' || $t === 't' || $t === '10') $val = 10;
        elseif ($t === 'J' || $t === 'j') $val = 11;
        elseif ($t === 'Q' || $t === 'q') $val = 12;
        elseif ($t === 'K' || $t === 'k') $val = 13;
        elseif ($t === 'A' || $t === 'a') $val = 14;
        elseif ($t === '2') $val = 15;
        elseif ($t === '小王' || $t === '小' || $lower === 'x') $val = 16;
        elseif ($t === '大王' || $t === '大' || $lower === 'd' || $lower === 'w') $val = 17;
        elseif (ctype_digit($t) && $t >= 3 && $t <= 9) $val = (int)$t;
        if ($val !== null) $result[] = $val;
    }
    return $result;
}

// ==================================================
// 发牌
// ==================================================
function dealCards() {
    // 54张牌（4花色 × 13点数 + 2王）
    $deck = [];
    for ($v = 3; $v <= 15; $v++) {
        $deck[] = $v; $deck[] = $v; $deck[] = $v; $deck[] = $v;
    }
    $deck[] = 16; // 小王
    $deck[] = 17; // 大王
    shuffle($deck);

    $hands = [[], [], []];
    for ($i = 0; $i < 51; $i++) {
        $hands[$i % 3][] = $deck[$i];
    }
    $bottom = array_slice($deck, 51, 3);

    // 排序
    foreach ($hands as &$h) sort($h, SORT_NUMERIC);
    unset($h);
    sort($bottom, SORT_NUMERIC);

    return ['hands' => $hands, 'bottom' => $bottom];
}

// ==================================================
// 牌型识别
// 返回: ['type' => 'single|pair|...', 'value' => 主牌值, 'len' => 长度] 或 false
// ==================================================
function analyzeCards($cards) {
    if (empty($cards)) return false;
    sort($cards, SORT_NUMERIC);
    $n = count($cards);

    // 统计每个点数出现的次数
    $counts = array_count_values($cards);
    $groups = []; // [count => [values...]]
    foreach ($counts as $val => $cnt) {
        $groups[$cnt][] = $val;
    }
    foreach ($groups as &$g) sort($g, SORT_NUMERIC);
    unset($g);

    // 王炸
    if ($n === 2 && $cards[0] === 16 && $cards[1] === 17) {
        return ['type' => 'rocket', 'value' => 100, 'len' => 0];
    }

    // 炸弹
    if ($n === 4 && isset($groups[4])) {
        return ['type' => 'bomb', 'value' => $groups[4][0], 'len' => 0];
    }

    // 单张
    if ($n === 1) {
        return ['type' => 'single', 'value' => $cards[0], 'len' => 1];
    }

    // 对子
    if ($n === 2 && isset($groups[2])) {
        return ['type' => 'pair', 'value' => $groups[2][0], 'len' => 1];
    }

    // 三张
    if ($n === 3 && isset($groups[3])) {
        return ['type' => 'triple', 'value' => $groups[3][0], 'len' => 1];
    }

    // 三带一
    if ($n === 4 && isset($groups[3]) && isset($groups[1])) {
        return ['type' => 'triple_single', 'value' => $groups[3][0], 'len' => 1];
    }

    // 三带二
    if ($n === 5 && isset($groups[3]) && isset($groups[2])) {
        return ['type' => 'triple_pair', 'value' => $groups[3][0], 'len' => 1];
    }

    // 四带二（单）
    if ($n === 6 && isset($groups[4]) && isset($groups[1]) && count($groups[1]) === 2) {
        return ['type' => 'four_two_single', 'value' => $groups[4][0], 'len' => 1];
    }

    // 四带二（对）
    if ($n === 8 && isset($groups[4]) && isset($groups[2]) && count($groups[2]) === 2) {
        return ['type' => 'four_two_pair', 'value' => $groups[4][0], 'len' => 1];
    }

    // 顺子（5张以上连续，不含2和王）
    if ($n >= 5 && !isset($groups[2]) && !isset($groups[3]) && !isset($groups[4]) && $cards[$n-1] < 15) {
        $isStraight = true;
        for ($i = 1; $i < $n; $i++) {
            if ($cards[$i] !== $cards[$i-1] + 1) { $isStraight = false; break; }
        }
        if ($isStraight) return ['type' => 'straight', 'value' => $cards[0], 'len' => $n];
    }

    // 连对（3对以上连续，不含2和王）
    if ($n >= 6 && $n % 2 === 0 && isset($groups[2]) && count($groups[2]) === $n / 2 && !isset($groups[3]) && !isset($groups[4]) && !isset($groups[1])) {
        $pairs = $groups[2];
        $cnt = count($pairs);
        if ($pairs[$cnt-1] < 15) {
            $isConsec = true;
            for ($i = 1; $i < $cnt; $i++) {
                if ($pairs[$i] !== $pairs[$i-1] + 1) { $isConsec = false; break; }
            }
            if ($isConsec) return ['type' => 'pair_straight', 'value' => $pairs[0], 'len' => $cnt];
        }
    }

    // 飞机（2组以上连续三张，不带翅膀）
    if ($n >= 6 && $n % 3 === 0 && isset($groups[3]) && count($groups[3]) === $n / 3 && !isset($groups[4]) && !isset($groups[2]) && !isset($groups[1])) {
        $triples = $groups[3];
        $cnt = count($triples);
        if ($triples[$cnt-1] < 15) {
            $isConsec = true;
            for ($i = 1; $i < $cnt; $i++) {
                if ($triples[$i] !== $triples[$i-1] + 1) { $isConsec = false; break; }
            }
            if ($isConsec) return ['type' => 'plane', 'value' => $triples[0], 'len' => $cnt];
        }
    }

    // 飞机带单（每组三张带1单）
    if (isset($groups[3]) && count($groups[3]) >= 2) {
        $triples = $groups[3];
        $cnt = count($triples);
        // 飞机带单：n = cnt*4
        if ($n === $cnt * 4 && $triples[$cnt-1] < 15) {
            $isConsec = true;
            for ($i = 1; $i < $cnt; $i++) {
                if ($triples[$i] !== $triples[$i-1] + 1) { $isConsec = false; break; }
            }
            if ($isConsec && !isset($groups[4])) {
                // 检查带的单张不是和王成对
                return ['type' => 'plane_single', 'value' => $triples[0], 'len' => $cnt];
            }
        }
        // 飞机带对：n = cnt*5
        if ($n === $cnt * 5 && $triples[$cnt-1] < 15) {
            $isConsec = true;
            for ($i = 1; $i < $cnt; $i++) {
                if ($triples[$i] !== $triples[$i-1] + 1) { $isConsec = false; break; }
            }
            if ($isConsec && isset($groups[2]) && count($groups[2]) === $cnt && !isset($groups[4]) && !isset($groups[1])) {
                return ['type' => 'plane_pair', 'value' => $triples[0], 'len' => $cnt];
            }
        }
    }

    return false;
}

// ==================================================
// 牌型比较：能否压过上家
// ==================================================
function canBeat($newType, $lastType) {
    if (empty($lastType)) return true; // 首出
    // 王炸最大
    if ($newType['type'] === 'rocket') return true;
    if ($lastType['type'] === 'rocket') return false;
    // 炸弹大过非炸弹
    if ($newType['type'] === 'bomb' && $lastType['type'] !== 'bomb') return true;
    if ($newType['type'] !== 'bomb' && $lastType['type'] === 'bomb') return false;
    // 同类型比较
    if ($newType['type'] === $lastType['type']) {
        if ($newType['type'] === 'bomb') return $newType['value'] > $lastType['value'];
        // 顺子/连对/飞机需要长度相同
        if (in_array($newType['type'], ['straight', 'pair_straight', 'plane', 'plane_single', 'plane_pair'])) {
            if ($newType['len'] !== $lastType['len']) return false;
        }
        return $newType['value'] > $lastType['value'];
    }
    return false;
}

// ==================================================
// 房间管理
// ==================================================
function handleCreateRoom($chatId, $userId, $userName) {
    $game = loadGame($chatId);
    if ($game !== null && $game['phase'] !== 'finished') {
        $phaseName = phaseName($game['phase']);
        return ['type' => 'text', 'content' => "❌ 当前群已有进行中的斗地主房间（{$phaseName}）\n\n发送「退出」可退出房间"];
    }

    $game = [
        'chatId'    => $chatId,
        'phase'     => 'waiting',
        'players'   => [
            ['id' => $userId, 'name' => $userName, 'cards' => [], 'isLandlord' => false, 'isAI' => false, 'ready' => false],
        ],
        'bottomCards' => [],
        'currentPlayer' => 0,
        'lastPlay' => null,
        'passCount' => 0,
        'bidPlayer' => 0,
        'bidHistory' => [],
        'landlord' => -1,
        'createdAt' => time(),
        'winner' => -1,
    ];
    saveGame($chatId, $game);

    return ['type' => 'text', 'content' =>
        "🃏 斗地主房间已创建！\n\n"
        . "房主：{$userName}\n"
        . "当前人数：1/3\n\n"
        . "发送「加入」参与游戏\n"
        . "3人满员后发送「开始」开局\n"
        . "不足3人开始将自动补AI"
    ];
}

function handleJoin($chatId, $userId, $userName) {
    $game = loadGame($chatId);
    if ($game === null) {
        return ['type' => 'text', 'content' => '❌ 当前群没有斗地主房间，请先发送「斗地主」创建房间'];
    }
    if ($game['phase'] !== 'waiting') {
        return ['type' => 'text', 'content' => '❌ 游戏已开始，无法加入'];
    }

    // 检查是否已在房间
    foreach ($game['players'] as $p) {
        if ($p['id'] === $userId) {
            return ['type' => 'text', 'content' => '❌ 你已加入房间'];
        }
    }

    if (count($game['players']) >= 3) {
        return ['type' => 'text', 'content' => '❌ 房间已满（3/3）'];
    }

    $game['players'][] = ['id' => $userId, 'name' => $userName, 'cards' => [], 'isLandlord' => false, 'isAI' => false, 'ready' => false];
    saveGame($chatId, $game);

    $cnt = count($game['players']);
    $names = implode('、', array_map(function($p) { return $p['name']; }, $game['players']));
    return ['type' => 'text', 'content' =>
        "✅ {$userName} 加入了房间\n"
        . "当前人数：{$cnt}/3\n"
        . "玩家：{$names}\n\n"
        . ($cnt === 3 ? "人员已满，发送「开始」即可开局" : "还需 " . (3 - $cnt) . " 人加入")
    ];
}

function handleStart($chatId, $userId, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) {
        return ['type' => 'text', 'content' => '❌ 当前群没有斗地主房间，请先发送「斗地主」创建房间'];
    }
    if ($game['phase'] !== 'waiting') {
        return ['type' => 'text', 'content' => '❌ 游戏已在进行中'];
    }

    // 只有第一个玩家（房主）能开始
    if ($game['players'][0]['id'] !== $userId) {
        return ['type' => 'text', 'content' => '❌ 只有房主 ' . $game['players'][0]['name'] . ' 可以开始游戏'];
    }

    $realCount = count($game['players']);
    if ($realCount < 2) {
        return ['type' => 'text', 'content' => '❌ 至少需要2人才能开始（将补AI到3人）'];
    }

    // 补AI玩家
    $aiNames = ['🤖机器人A', '🤖机器人B', '🤖机器人C'];
    $aiIdx = 0;
    while (count($game['players']) < 3) {
        $game['players'][] = [
            'id' => 'ai_' . $chatId . '_' . $aiIdx,
            'name' => $aiNames[$aiIdx],
            'cards' => [], 'isLandlord' => false, 'isAI' => true, 'ready' => false,
        ];
        $aiIdx++;
    }

    // 发牌
    $dealt = dealCards();
    for ($i = 0; $i < 3; $i++) {
        $game['players'][$i]['cards'] = $dealt['hands'][$i];
    }
    $game['bottomCards'] = $dealt['bottom'];
    $game['phase'] = 'bidding';
    $game['bidPlayer'] = mt_rand(0, 2); // 随机选先叫
    $game['bidHistory'] = [];
    $game['landlord'] = -1;
    saveGame($chatId, $game);

    // 私聊发牌
    $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
    for ($i = 0; $i < 3; $i++) {
        $p = $game['players'][$i];
        if (!$p['isAI']) {
            $handStr = "🃏 你的手牌（共" . count($p['cards']) . "张）：\n" . cardsToStr($p['cards']);
            sendPrivateMsg($token, $p['id'], $handStr);
        }
    }

    $firstPlayer = $game['players'][$game['bidPlayer']];
    $names = [];
    for ($i = 0; $i < 3; $i++) {
        $names[] = ($i + 1) . ". " . $game['players'][$i]['name'];
    }

    return ['type' => 'text', 'content' =>
        "🎰 发牌完毕！游戏开始！\n"
        . str_repeat('─', 28) . "\n"
        . "座位顺序：\n" . implode("\n", $names) . "\n"
        . str_repeat('─', 28) . "\n"
        . "手牌已私聊发送，请查看私聊\n\n"
        . "📌 叫地主阶段\n"
        . "由 {$firstPlayer['name']} 先叫\n"
        . "发送「叫地主」或「不叫」"
    ];
}

// ==================================================
// 叫地主 / 抢地主
// ==================================================
function handleBid($chatId, $userId, $bid, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) return null;
    if ($game['phase'] !== 'bidding') return null;

    $currentBid = $game['bidPlayer'];
    $player = $game['players'][$currentBid];

    // 验证是否轮到该玩家
    if ($player['id'] !== $userId) {
        return ['type' => 'text', 'content' => "❌ 现在轮到 {$player['name']} 叫地主"];
    }

    $game['bidHistory'][] = ['player' => $currentBid, 'bid' => $bid];
    saveGame($chatId, $game);

    if ($bid) {
        // 叫了地主 → 其他人可以抢
        $game['phase'] = 'grabbing';
        $game['bidPlayer'] = $currentBid;
        $game['grabStart'] = $currentBid;
        $game['grabCurrent'] = ($currentBid + 1) % 3;
        saveGame($chatId, $game);

        $nextPlayer = $game['players'][$game['grabCurrent']];

        // 如果下家是AI，自动处理
        $msg = "📢 {$player['name']} 叫地主！\n\n"
            . "{$nextPlayer['name']} 可以抢地主\n"
            . "发送「抢」或「不抢」";

        // AI自动处理
        if ($nextPlayer['isAI']) {
            $result = aiGrab($chatId, $game, $botapi);
            if ($result) return $result;
        }

        return ['type' => 'text', 'content' => $msg];
    } else {
        // 不叫 → 下一个
        $player['name'] = $player['name'];
        $next = ($currentBid + 1) % 3;

        // 检查是否所有人都不叫
        $allPass = true;
        foreach ($game['bidHistory'] as $h) {
            if ($h['bid']) { $allPass = false; break; }
        }

        if (count($game['bidHistory']) >= 3 && $allPass) {
            // 所有人都不叫 → 重新发牌
            deleteGame($chatId);
            return ['type' => 'text', 'content' =>
                "🙅 所有人都不叫地主，重新开始！\n\n"
                . "发送「斗地主」创建新房间"
            ];
        }

        $game['bidPlayer'] = $next;
        saveGame($chatId, $game);

        $nextPlayer = $game['players'][$next];
        $msg = "🙅 {$player['name']} 不叫\n\n{$nextPlayer['name']} 叫地主\n发送「叫地主」或「不叫」";

        // AI自动叫
        if ($nextPlayer['isAI']) {
            return aiBid($chatId, $game, $botapi);
        }

        return ['type' => 'text', 'content' => $msg];
    }
}

function handleGrab($chatId, $userId, $grab, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) return null;
    if ($game['phase'] !== 'grabbing') return null;

    $current = $game['grabCurrent'] ?? 0;
    $player = $game['players'][$current];

    if ($player['id'] !== $userId) {
        return ['type' => 'text', 'content' => "❌ 现在轮到 {$player['name']} 决定"];
    }

    if ($grab) {
        // 抢地主 → 原叫地主者可以再抢
        $game['bidPlayer'] = $current; // 当前抢的人成为地主候选
        $game['grabCurrent'] = ($current + 1) % 3;
        saveGame($chatId, $game);

        // 检查是否回到叫地主的人
        if ($game['grabCurrent'] === ($game['grabStart'] ?? 0)) {
            // 地主确定
            return confirmLandlord($chatId, $game, $botapi);
        }

        $nextPlayer = $game['players'][$game['grabCurrent']];
        $msg = "📢 {$player['name']} 抢地主！\n\n{$nextPlayer['name']} 可以抢\n发送「抢」或「不抢」";

        if ($nextPlayer['isAI']) {
            return aiGrab($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];

    } else {
        // 不抢 → 下一个
        $game['grabCurrent'] = ($current + 1) % 3;
        saveGame($chatId, $game);

        if ($game['grabCurrent'] === ($game['grabStart'] ?? 0)) {
            // 地主确定（最初叫地主的人）
            $game['bidPlayer'] = $game['grabStart'];
            return confirmLandlord($chatId, $game, $botapi);
        }

        $nextPlayer = $game['players'][$game['grabCurrent']];
        $msg = "🙅 {$player['name']} 不抢\n\n{$nextPlayer['name']} 可以抢\n发送「抢」或「不抢」";

        if ($nextPlayer['isAI']) {
            return aiGrab($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];
    }
}

function confirmLandlord($chatId, $game, $botapi) {
    $landlordIdx = $game['bidPlayer'];
    $game['landlord'] = $landlordIdx;
    $game['players'][$landlordIdx]['isLandlord'] = true;
    $game['phase'] = 'playing';
    $game['currentPlayer'] = $landlordIdx;
    $game['lastPlay'] = null;
    $game['passCount'] = 0;

    // 地主获得底牌
    $bottom = $game['bottomCards'];
    foreach ($bottom as $c) {
        $game['players'][$landlordIdx]['cards'][] = $c;
    }
    sort($game['players'][$landlordIdx]['cards'], SORT_NUMERIC);

    saveGame($chatId, $game);

    $landlord = $game['players'][$landlordIdx];
    $bottomStr = cardsToStr($bottom);

    // 私聊通知地主的新手牌
    $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
    if (!$landlord['isAI']) {
        $handStr = "🃏 你是地主！手牌（共" . count($landlord['cards']) . "张）：\n" . cardsToStr($landlord['cards']);
        sendPrivateMsg($token, $landlord['id'], $handStr);
    }

    $msg = "👑 地主确定：{$landlord['name']}！\n"
        . "底牌：{$bottomStr}\n\n"
        . "游戏正式开始！\n"
        . "地主先出牌，发送「出 <牌面>」出牌\n"
        . "例如：出 34567 或 出 33\n"
        . "不跟牌发送「不要」\n"
        . "查看手牌发送「手牌」";

    // 如果地主是AI，自动出牌
    if ($landlord['isAI']) {
        usleep(300000);
        return aiPlay($chatId, $game, $botapi);
    }

    return ['type' => 'text', 'content' => $msg];
}

// ==================================================
// 出牌逻辑
// ==================================================
function handlePlay($chatId, $userId, $cardInput, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) return null;
    if ($game['phase'] !== 'playing') {
        return ['type' => 'text', 'content' => '❌ 当前没有进行中的游戏'];
    }

    $currentIdx = $game['currentPlayer'];
    $player = $game['players'][$currentIdx];

    if ($player['id'] !== $userId) {
        return ['type' => 'text', 'content' => "❌ 现在轮到 {$player['name']} 出牌"];
    }

    $playCards = parseCards($cardInput);
    if (empty($playCards)) {
        return ['type' => 'text', 'content' => "❌ 无法识别牌面，请使用数字/字母表示\n例如：出 34567 或 出 3344 或 出 小王大王"];
    }

    // 检查是否有这些牌
    $handCards = $player['cards'];
    $tempHand = $handCards;
    foreach ($playCards as $c) {
        $idx = array_search($c, $tempHand);
        if ($idx === false) {
            $cardName = cardName($c);
            return ['type' => 'text', 'content' => "❌ 你没有 {$cardName} 这张牌\n发送「手牌」查看你的手牌"];
        }
        unset($tempHand[$idx]);
        $tempHand = array_values($tempHand);
    }

    // 识别牌型
    $cardType = analyzeCards($playCards);
    if ($cardType === false) {
        return ['type' => 'text', 'content' => '❌ 牌型不合法：' . cardsToStr($playCards) . "\n\n支持：单张/对子/三张/三带一/三带二/顺子/连对/飞机/炸弹/王炸"];
    }

    // 检查是否能压过上家
    $lastPlay = $game['lastPlay'];
    if ($lastPlay !== null && !canBeat($cardType, $lastPlay['type'])) {
        $lastStr = cardsToStr($lastPlay['cards']);
        $typeName = typeName($lastPlay['type']['type']);
        return ['type' => 'text', 'content' => "❌ 牌型无法压过上家\n上家出：{$lastStr}（{$typeName}）\n你的出牌：" . cardsToStr($playCards)];
    }

    // 执行出牌
    $game['players'][$currentIdx]['cards'] = $tempHand;
    $game['lastPlay'] = [
        'player' => $currentIdx,
        'cards' => $playCards,
        'type' => $cardType,
    ];
    $game['passCount'] = 0;

    $playedStr = cardsToStr($playCards);
    $typeName = typeName($cardType['type']);
    $remaining = count($tempHand);

    // 检查是否有人出完
    if ($remaining === 0) {
        $game['phase'] = 'finished';
        $game['winner'] = $currentIdx;
        saveGame($chatId, $game);
        return handleGameEnd($chatId, $game);
    }

    // 出牌的玩家如果是真人，私聊通知剩余手牌
    $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
    if (empty($player['isAI'])) {
        $role = !empty($player['isLandlord']) ? '👑地主' : '🧑‍🌾农民';
        $pm = "✅ 出牌成功：{$playedStr}（{$typeName}）\n\n当前手牌（{$role}，共 {$remaining} 张）：\n" . cardsToStr($tempHand);
        sendPrivateMsg($token, $player['id'], $pm);
    }

    // 下一个玩家
    $game['currentPlayer'] = ($currentIdx + 1) % 3;
    saveGame($chatId, $game);

    $nextPlayer = $game['players'][$game['currentPlayer']];
    $landlordTag = $player['isLandlord'] ? '👑' : '🧑‍🌾';

    $msg = "{$landlordTag} {$player['name']} 出牌：{$playedStr}（{$typeName}）\n"
        . "剩余 {$remaining} 张\n\n"
        . "轮到 {$nextPlayer['name']} 出牌\n"
        . "跟牌发送「出 <牌面>」，不跟发送「不要」";

    // 下家是AI则自动出牌
    if ($nextPlayer['isAI']) {
        $extraMsg = $msg;
        saveGame($chatId, $game);
        // 先发当前消息
        $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
        $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
        $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
        if ($token) \yunhuSendMessage($token, $recvId, $extraMsg, 'text', $recvType);
        // AI出牌
        usleep(300000);
        $aiResult = aiPlay($chatId, $game, $botapi);
        if ($aiResult) return $aiResult;
        return null; // 已通过主动消息发送
    }

    return ['type' => 'text', 'content' => $msg];
}

function handlePass($chatId, $userId, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) return null;
    if ($game['phase'] !== 'playing') return null;

    $currentIdx = $game['currentPlayer'];
    $player = $game['players'][$currentIdx];

    if ($player['id'] !== $userId) {
        return ['type' => 'text', 'content' => "❌ 现在轮到 {$player['name']} 出牌"];
    }

    // 首出不能不要
    if ($game['lastPlay'] === null || $game['passCount'] >= 2) {
        return ['type' => 'text', 'content' => '❌ 你是首出，必须出牌'];
    }

    $game['passCount']++;
    $nextIdx = ($currentIdx + 1) % 3;
    $game['currentPlayer'] = $nextIdx;
    saveGame($chatId, $game);

    $nextPlayer = $game['players'][$nextIdx];
    $landlordTag = $player['isLandlord'] ? '👑' : '🧑‍🌾';

    // 如果两人都不要，上家自由出
    if ($game['passCount'] >= 2) {
        $lastPlayerIdx = $game['lastPlay']['player'];
        $game['currentPlayer'] = $lastPlayerIdx;
        $game['lastPlay'] = null;
        $game['passCount'] = 0;
        saveGame($chatId, $game);

        $lastPlayer = $game['players'][$lastPlayerIdx];
        $msg = "{$landlordTag} {$player['name']} 不要\n"
            . str_repeat('─', 20) . "\n"
            . "其他玩家都不要，{$lastPlayer['name']} 自由出牌";

        if ($lastPlayer['isAI']) {
            $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
            $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
            $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
            if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);
            usleep(300000);
            return aiPlay($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];
    }

    $msg = "{$landlordTag} {$player['name']} 不要\n轮到 {$nextPlayer['name']} 出牌";

    if ($nextPlayer['isAI']) {
        $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
        $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
        $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
        if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);
        usleep(300000);
        return aiPlay($chatId, $game, $botapi);
    }

    return ['type' => 'text', 'content' => $msg];
}

// ==================================================
// 游戏结束
// ==================================================
function handleGameEnd($chatId, $game) {
    $winnerIdx = $game['winner'];
    $winner = $game['players'][$winnerIdx];
    $isLandlordWin = $winner['isLandlord'];

    $names = [];
    $roles = [];
    for ($i = 0; $i < 3; $i++) {
        $p = $game['players'][$i];
        $role = $p['isLandlord'] ? '👑地主' : '🧑‍🌾农民';
        $names[] = $p['name'];
        $roles[] = $p['name'] . "（{$role}）";
    }

    if ($isLandlordWin) {
        $result = "🎉 地主获胜！\n";
    } else {
        $result = "🎉 农民获胜！\n";
    }
    $result .= str_repeat('═', 28) . "\n";
    $result .= implode("\n", $roles) . "\n";
    $result .= str_repeat('═', 28) . "\n";
    $result .= "赢家：{$winner['name']}\n\n";
    $result .= "发送「斗地主」开始新一局";

    deleteGame($chatId);
    return ['type' => 'text', 'content' => $result];
}

// ==================================================
// 查看手牌
// ==================================================
function handleHand($chatId, $userId, $botapi) {
    $game = loadGame($chatId);
    if ($game === null) {
        return ['type' => 'text', 'content' => '❌ 当前群没有进行中的游戏'];
    }
    if ($game['phase'] === 'waiting') {
        return ['type' => 'text', 'content' => '❌ 游戏尚未开始'];
    }

    foreach ($game['players'] as $p) {
        if ($p['id'] === $userId) {
            $role = $p['isLandlord'] ? '👑地主' : '🧑‍🌾农民';
            $cnt = count($p['cards']);
            $cards = cardsToStr($p['cards']);
            // 私聊发送手牌
            $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
            $sent = false;
            if ($token) {
                $msg = "🃏 你的手牌（{$role}，共{$cnt}张）：\n" . $cards;
                $sent = sendPrivateMsg($token, $userId, $msg);
            }
            if ($sent) {
                return ['type' => 'text', 'content' => '✅ 手牌已私聊发送，请查看私聊消息'];
            }
            // 私聊失败，群内显示（仅自己可见不太可能，直接显示）
            return ['type' => 'text', 'content' => "🃏 你的手牌（{$role}，共{$cnt}张）：\n" . $cards . "\n\n⚠️ 私聊发送失败，已在此显示"];
        }
    }
    return ['type' => 'text', 'content' => '❌ 你不在当前游戏中'];
}

function handlePrivateHand($userId, $chatId) {
    // 私聊中查看手牌（chatId 实际是用户自己的ID）
    // 需要搜索所有游戏文件找该用户
    $dir = __DIR__ . '/ddz_data';
    if (!is_dir($dir)) return ['type' => 'text', 'content' => '❌ 没有进行中的游戏'];

    $files = glob($dir . '/game_*.json');
    foreach ($files as $f) {
        $game = @json_decode(@file_get_contents($f), true);
        if (!is_array($game)) continue;
        if ($game['phase'] === 'waiting' || $game['phase'] === 'finished') continue;
        foreach ($game['players'] as $p) {
            if ($p['id'] === $userId) {
                $role = $p['isLandlord'] ? '👑地主' : '🧑‍🌾农民';
                $cnt = count($p['cards']);
                return ['type' => 'text', 'content' => "🃏 你的手牌（{$role}，共{$cnt}张）：\n" . cardsToStr($p['cards'])];
            }
        }
    }
    return ['type' => 'text', 'content' => '❌ 没有找到你参与的游戏'];
}

// ==================================================
// 桌面信息
// ==================================================
function handleTableInfo($chatId) {
    $game = loadGame($chatId);
    if ($game === null) {
        return ['type' => 'text', 'content' => '❌ 当前群没有斗地主房间'];
    }

    $phase = phaseName($game['phase']);
    $lines = ["📊 桌面信息", str_repeat('─', 24)];
    $lines[] = "阶段：{$phase}";

    if ($game['phase'] === 'waiting') {
        $cnt = count($game['players']);
        $lines[] = "人数：{$cnt}/3";
        $lines[] = "玩家：";
        for ($i = 0; $i < $cnt; $i++) {
            $lines[] = "  " . ($i + 1) . ". " . $game['players'][$i]['name'];
        }
        if ($cnt < 3) $lines[] = "\n发送「加入」参与游戏";
    } elseif ($game['phase'] === 'bidding') {
        $cur = $game['players'][$game['bidPlayer']];
        $lines[] = "当前叫地主：{$cur['name']}";
        if (!empty($game['currentTurnStartedAt'])) {
            $rem = max(0, 60 - (time() - (int)$game['currentTurnStartedAt']));
            $lines[] = "剩余时间：{$rem} 秒（60秒未行动自动跳过）";
        }
        $lines[] = "发送「叫地主」或「不叫」";
    } elseif ($game['phase'] === 'grabbing') {
        $cur = $game['players'][$game['grabCurrent'] ?? 0];
        $lines[] = "当前抢地主：{$cur['name']}";
        if (!empty($game['currentTurnStartedAt'])) {
            $rem = max(0, 60 - (time() - (int)$game['currentTurnStartedAt']));
            $lines[] = "剩余时间：{$rem} 秒（60秒未行动自动跳过）";
        }
        $lines[] = "发送「抢」或「不抢」";
    } elseif ($game['phase'] === 'playing') {
        $cur = $game['players'][$game['currentPlayer']];
        $lines[] = "当前出牌：{$cur['name']}";
        if (!empty($game['currentTurnStartedAt'])) {
            $rem = max(0, 60 - (time() - (int)$game['currentTurnStartedAt']));
            $lines[] = "剩余时间：{$rem} 秒（60秒未行动自动跳过）";
        }

        // 各玩家剩余牌数
        for ($i = 0; $i < 3; $i++) {
            $p = $game['players'][$i];
            $role = $p['isLandlord'] ? '👑' : '🧑‍🌾';
            $lines[] = "  {$role} {$p['name']}：剩余" . count($p['cards']) . "张";
        }

        if ($game['lastPlay'] !== null) {
            $lp = $game['lastPlay'];
            $lastPlayer = $game['players'][$lp['player']];
            $lines[] = "\n上家出牌：" . cardsToStr($lp['cards']);
            $lines[] = "出牌者：{$lastPlayer['name']}";
        } else {
            $lines[] = "\n自由出牌（无人压制）";
        }
    }

    return ['type' => 'text', 'content' => implode("\n", $lines)];
}

// ==================================================
// 退出房间
// ==================================================
function handleQuit($chatId, $userId) {
    $game = loadGame($chatId);
    if ($game === null) {
        return ['type' => 'text', 'content' => '❌ 当前群没有斗地主房间'];
    }

    if ($game['phase'] !== 'waiting') {
        // 游戏进行中退出 = 弃权
        deleteGame($chatId);
        return ['type' => 'text', 'content' => "🏃 游戏已结束（有人退出）\n\n发送「斗地主」创建新房间"];
    }

    // 等待阶段退出
    $newPlayers = [];
    $found = false;
    foreach ($game['players'] as $p) {
        if ($p['id'] === $userId) {
            $found = true;
            continue;
        }
        $newPlayers[] = $p;
    }

    if (!$found) {
        return ['type' => 'text', 'content' => '❌ 你不在当前房间'];
    }

    if (empty($newPlayers)) {
        deleteGame($chatId);
        return ['type' => 'text', 'content' => '🏃 房间已解散（最后一人退出）'];
    }

    $game['players'] = $newPlayers;
    saveGame($chatId, $game);

    $cnt = count($newPlayers);
    $names = implode('、', array_map(function($p) { return $p['name']; }, $newPlayers));
    return ['type' => 'text', 'content' => "🏃 已退出房间\n当前人数：{$cnt}/3\n玩家：{$names}"];
}

// ==================================================
// 规则
// ==================================================
function handleRules() {
    return ['type' => 'text', 'content' =>
        "📜 斗地主玩法规则\n"
        . str_repeat('═', 28) . "\n"
        . "【基础】\n"
        . "• 3人游戏，54张牌（含大小王）\n"
        . "• 地主17+3=20张，农民各17张\n"
        . "• 谁先出完牌谁赢\n\n"
        . "【叫地主】\n"
        . "• 随机选人先叫，可叫或不叫\n"
        . "• 叫了之后其他人可抢\n"
        . "• 最终叫/抢到的人当地主\n\n"
        . "【牌型】\n"
        . "• 单张：3~大王\n"
        . "• 对子：两张相同\n"
        . "• 三张/三带一/三带二\n"
        . "• 顺子：5张以上连续（不含2和王）\n"
        . "• 连对：3对以上连续\n"
        . "• 飞机：2组以上连续三张\n"
        . "• 飞机带翅膀（单/对）\n"
        . "• 四带二（单/对）\n"
        . "• 炸弹：四张相同\n"
        . "• 王炸：大小王（最大）\n\n"
        . "【大小比较】\n"
        . "3 < 4 < 5 < 6 < 7 < 8 < 9 < 10 < J < Q < K < A < 2 < 小王 < 大王\n"
        . "炸弹 > 任何非炸弹牌型\n"
        . "王炸 > 一切\n\n"
        . "【牌面输入】\n"
        . "数字3-9直接输入\n"
        . "10输入10或T\n"
        . "J/Q/K/A直接输入字母\n"
        . "2输入2\n"
        . "小王输入「小王」或x\n"
        . "大王输入「大王」或d\n"
        . "出牌示例：出 34567 / 出 3344 / 出 小王 大王\n\n"
        . "【命令】\n"
        . "斗地主 - 创建房间\n"
        . "加入 - 加入房间\n"
        . "开始 - 开始游戏\n"
        . "手牌 - 私聊查看手牌\n"
        . "桌面 - 查看桌面信息\n"
        . "退出 - 退出房间"
    ];
}

// ==================================================
// 帮助
// ==================================================
function handleHelp() {
    return ['type' => 'text', 'content' =>
        "🃏 斗地主 - 命令帮助\n"
        . str_repeat('═', 28) . "\n"
        . "斗地主      创建房间\n"
        . "加入        加入房间\n"
        . "开始        开始游戏（不足3人补AI）\n"
        . "叫地主/不叫  叫地主阶段\n"
        . "抢/不抢      抢地主阶段\n"
        . "出 <牌面>    出牌（如：出 34567 / 出 3344 / 出 小王 大王）\n"
        . "不要/过      不跟牌\n"
        . "手牌        私聊查看手牌\n"
        . "桌面        查看桌面信息\n"
        . "规则        查看玩法\n"
        . "退出        退出房间"
    ];
}

// ==================================================
// AI 逻辑
// ==================================================
function aiBid($chatId, $game, $botapi) {
    $idx = $game['bidPlayer'];
    $player = $game['players'][$idx];

    // AI叫地主策略：看手牌质量
    $score = aiHandScore($player['cards']);
    $bid = $score >= 8; // 阈值

    $game['bidHistory'][] = ['player' => $idx, 'bid' => $bid];
    saveGame($chatId, $game);

    if ($bid) {
        $game['phase'] = 'grabbing';
        $game['bidPlayer'] = $idx;
        $game['grabStart'] = $idx;
        $game['grabCurrent'] = ($idx + 1) % 3;
        saveGame($chatId, $game);

        $nextPlayer = $game['players'][$game['grabCurrent']];
        $msg = "📢 {$player['name']} 叫地主！\n\n{$nextPlayer['name']} 可以抢\n发送「抢」或「不抢」";

        if ($nextPlayer['isAI']) {
            $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
            $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
            $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
            if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);
            usleep(300000);
            return aiGrab($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];
    } else {
        $next = ($idx + 1) % 3;
        $allPass = true;
        foreach ($game['bidHistory'] as $h) {
            if ($h['bid']) { $allPass = false; break; }
        }
        if (count($game['bidHistory']) >= 3 && $allPass) {
            deleteGame($chatId);
            return ['type' => 'text', 'content' => "🙅 所有人都不叫地主，重新开始！\n\n发送「斗地主」创建新房间"];
        }

        $game['bidPlayer'] = $next;
        saveGame($chatId, $game);

        $nextPlayer = $game['players'][$next];
        $msg = "🙅 {$player['name']} 不叫\n\n{$nextPlayer['name']} 叫地主\n发送「叫地主」或「不叫」";

        if ($nextPlayer['isAI']) {
            $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
            $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
            $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
            if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);
            usleep(300000);
            return aiBid($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];
    }
}

function aiGrab($chatId, $game, $botapi) {
    $idx = $game['grabCurrent'] ?? 0;
    $player = $game['players'][$idx];

    $score = aiHandScore($player['cards']);
    $grab = $score >= 10; // 抢地主阈值更高

    if ($grab) {
        $game['bidPlayer'] = $idx;
        $game['grabCurrent'] = ($idx + 1) % 3;
        saveGame($chatId, $game);

        if ($game['grabCurrent'] === ($game['grabStart'] ?? 0)) {
            return confirmLandlord($chatId, $game, $botapi);
        }

        $nextPlayer = $game['players'][$game['grabCurrent']];
        $msg = "📢 {$player['name']} 抢地主！\n\n{$nextPlayer['name']} 可以抢\n发送「抢」或「不抢」";

        if ($nextPlayer['isAI']) {
            $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
            $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
            $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
            if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);
            usleep(300000);
            return aiGrab($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];
    } else {
        $game['grabCurrent'] = ($idx + 1) % 3;
        saveGame($chatId, $game);

        if ($game['grabCurrent'] === ($game['grabStart'] ?? 0)) {
            $game['bidPlayer'] = $game['grabStart'];
            return confirmLandlord($chatId, $game, $botapi);
        }

        $nextPlayer = $game['players'][$game['grabCurrent']];
        $msg = "🙅 {$player['name']} 不抢\n\n{$nextPlayer['name']} 可以抢\n发送「抢」或「不抢」";

        if ($nextPlayer['isAI']) {
            $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
            $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
            $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';
            if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);
            usleep(300000);
            return aiGrab($chatId, $game, $botapi);
        }
        return ['type' => 'text', 'content' => $msg];
    }
}

// AI手牌评分（用于叫地主判断）
function aiHandScore($cards) {
    $score = 0;
    $counts = array_count_values($cards);
    foreach ($counts as $val => $cnt) {
        if ($val === 17) $score += 4;      // 大王
        elseif ($val === 16) $score += 3;  // 小王
        elseif ($val === 15) $score += 2 * $cnt; // 2
        if ($cnt === 4) $score += 6;       // 炸弹
        if ($cnt === 3) $score += 2;       // 三张
    }
    // 王炸
    if (isset($counts[16]) && isset($counts[17])) $score += 5;
    return $score;
}

// AI出牌
function aiPlay($chatId, $game, $botapi) {
    $idx = $game['currentPlayer'];
    $player = $game['players'][$idx];

    if (!$player['isAI']) return null;

    $hand = $player['cards'];
    $lastPlay = $game['lastPlay'];
    $isLandlord = $player['isLandlord'];

    // 寻找能出的牌
    $play = null;

    if ($lastPlay === null) {
        // 首出：选择策略性出牌
        $play = aiFindLead($hand, $isLandlord, $game);
    } else {
        // 跟牌：找能压过的牌
        $play = aiFindFollow($hand, $lastPlay['type'], $isLandlord, $game);
    }

    $token = is_array($botapi) ? ($botapi['token'] ?? '') : '';
    $recvId = is_array($botapi) ? ($botapi['recvId'] ?? $chatId) : $chatId;
    $recvType = is_array($botapi) ? ($botapi['recvType'] ?? 'group') : 'group';

    if ($play === null) {
        // 不要
        $game['passCount']++;
        $nextIdx = ($idx + 1) % 3;
        $game['currentPlayer'] = $nextIdx;

        $landlordTag = $isLandlord ? '👑' : '🧑‍🌾';
        $msg = "{$landlordTag} {$player['name']} 不要\n轮到 {$game['players'][$nextIdx]['name']} 出牌";

        if ($game['passCount'] >= 2) {
            $lastPlayerIdx = $game['lastPlay']['player'];
            $game['currentPlayer'] = $lastPlayerIdx;
            $game['lastPlay'] = null;
            $game['passCount'] = 0;
            $lastPlayer = $game['players'][$lastPlayerIdx];
            $msg = "{$landlordTag} {$player['name']} 不要\n" . str_repeat('─', 20) . "\n其他玩家都不要，{$lastPlayer['name']} 自由出牌";
        }

        saveGame($chatId, $game);
        if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);

        // 继续AI出牌
        $nextPlayer = $game['players'][$game['currentPlayer']];
        if ($nextPlayer['isAI']) {
            usleep(300000);
            return aiPlay($chatId, $game, $botapi);
        }
        return null;
    }

    // 执行出牌
    $tempHand = $hand;
    foreach ($play as $c) {
        $i = array_search($c, $tempHand);
        if ($i !== false) unset($tempHand[$i]);
        $tempHand = array_values($tempHand);
    }

    $game['players'][$idx]['cards'] = $tempHand;
    $cardType = analyzeCards($play);
    $game['lastPlay'] = ['player' => $idx, 'cards' => $play, 'type' => $cardType];
    $game['passCount'] = 0;

    $playedStr = cardsToStr($play);
    $typeName = typeName($cardType['type']);
    $remaining = count($tempHand);
    $landlordTag = $isLandlord ? '👑' : '🧑‍🌾';

    if ($remaining === 0) {
        $game['phase'] = 'finished';
        $game['winner'] = $idx;
        saveGame($chatId, $game);
        $endMsg = "{$landlordTag} {$player['name']} 出牌：{$playedStr}（{$typeName}）\n剩余 0 张\n\n";
        if ($token) \yunhuSendMessage($token, $recvId, $endMsg, 'text', $recvType);
        return handleGameEnd($chatId, $game);
    }

    $game['currentPlayer'] = ($idx + 1) % 3;
    saveGame($chatId, $game);

    $nextPlayer = $game['players'][$game['currentPlayer']];
    $msg = "{$landlordTag} {$player['name']} 出牌：{$playedStr}（{$typeName}）\n剩余 {$remaining} 张\n\n轮到 {$nextPlayer['name']} 出牌";

    if ($token) \yunhuSendMessage($token, $recvId, $msg, 'text', $recvType);

    if ($nextPlayer['isAI']) {
        usleep(300000);
        return aiPlay($chatId, $game, $botapi);
    }

    return null;
}

// AI首出策略
function aiFindLead($hand, $isLandlord, $game) {
    if (empty($hand)) return null;
    $counts = array_count_values($hand);

    // 如果剩牌很少，尝试一次性出完
    if (count($hand) <= 5) {
        $type = analyzeCards($hand);
        if ($type !== false) return $hand;
    }

    // 优先出顺子（消耗多张牌）
    $straight = findStraight($hand);
    if ($straight !== null && count($hand) > 10) return $straight;

    // 出连对
    $pairStr = findPairStraight($hand);
    if ($pairStr !== null && count($hand) > 10) return $pairStr;

    // 出三带一
    foreach ($counts as $val => $cnt) {
        if ($cnt === 3 && $val < 15) {
            // 找一张单牌带
            foreach ($counts as $v2 => $c2) {
                if ($v2 !== $val && $c2 === 1) {
                    return [$val, $val, $val, $v2];
                }
            }
            return [$val, $val, $val];
        }
    }

    // 出对子（最小的）
    $pairs = [];
    foreach ($counts as $val => $cnt) {
        if ($cnt >= 2 && $val < 15) $pairs[] = $val;
    }
    if (!empty($pairs)) {
        sort($pairs, SORT_NUMERIC);
        $v = $pairs[0];
        return [$v, $v];
    }

    // 出单张（最小的，但不出王和2除非只剩这些）
    $singles = [];
    foreach ($counts as $val => $cnt) {
        if ($cnt === 1) $singles[] = $val;
    }
    if (!empty($singles)) {
        sort($singles, SORT_NUMERIC);
        // 如果最小的单张是2或王，且手牌很多，不出
        if ($singles[0] >= 15 && count($hand) > 5) {
            // 找最小的一张牌出
            $allCards = $hand;
            sort($allCards, SORT_NUMERIC);
            return [$allCards[0]];
        }
        return [$singles[0]];
    }

    // 兜底：出最小的牌
    $sorted = $hand;
    sort($sorted, SORT_NUMERIC);
    return [$sorted[0]];
}

// AI跟牌策略
function aiFindFollow($hand, $lastType, $isLandlord, $game) {
    $counts = array_count_values($hand);
    $lastPlayerIdx = $game['lastPlay']['player'] ?? 0;
    $lastPlayer = $game['players'][$lastPlayerIdx] ?? null;

    // 判断上家是否是队友（农民vs农民）
    $isTeammate = false;
    if (!$isLandlord && $lastPlayer && !$lastPlayer['isLandlord']) {
        $isTeammate = true;
    }

    // 如果是队友出的牌且队友牌少，不压
    if ($isTeammate) {
        $teammateCards = count($lastPlayer['cards'] ?? []);
        if ($teammateCards <= 3) return null; // 队友快赢了，不压
    }

    $type = $lastType['type'];
    $value = $lastType['value'];
    $len = $lastType['len'];

    switch ($type) {
        case 'single':
            // 找最小的能压过的单张
            foreach ($hand as $c) {
                if ($c > $value) {
                    // 优先不出王和2
                    if ($c < 15 || count($hand) <= 3) return [$c];
                }
            }
            // 找到了但是是2或王
            foreach ($hand as $c) {
                if ($c > $value) return [$c];
            }
            break;

        case 'pair':
            $pairs = [];
            foreach ($counts as $val => $cnt) {
                if ($cnt >= 2 && $val > $value) $pairs[] = $val;
            }
            if (!empty($pairs)) {
                sort($pairs, SORT_NUMERIC);
                return [$pairs[0], $pairs[0]];
            }
            break;

        case 'triple':
            foreach ($counts as $val => $cnt) {
                if ($cnt >= 3 && $val > $value && $val < 15) {
                    return [$val, $val, $val];
                }
            }
            break;

        case 'triple_single':
            foreach ($counts as $val => $cnt) {
                if ($cnt >= 3 && $val > $value && $val < 15) {
                    // 找一张单牌
                    foreach ($counts as $v2 => $c2) {
                        if ($v2 !== $val && $c2 >= 1) {
                            return [$val, $val, $val, $v2];
                        }
                    }
                }
            }
            break;

        case 'triple_pair':
            foreach ($counts as $val => $cnt) {
                if ($cnt >= 3 && $val > $value && $val < 15) {
                    // 找一对
                    foreach ($counts as $v2 => $c2) {
                        if ($v2 !== $val && $c2 >= 2) {
                            return [$val, $val, $val, $v2, $v2];
                        }
                    }
                }
            }
            break;

        case 'straight':
            // 找等长更大的顺子
            $result = findStraightFollow($hand, $value, $len);
            if ($result !== null) return $result;
            break;

        case 'pair_straight':
            $result = findPairStraightFollow($hand, $value, $len);
            if ($result !== null) return $result;
            break;

        case 'plane':
        case 'plane_single':
        case 'plane_pair':
            // 飞机跟牌（简化处理）
            break;

        case 'bomb':
            // 找更大的炸弹
            foreach ($counts as $val => $cnt) {
                if ($cnt === 4 && $val > $value) {
                    return [$val, $val, $val, $val];
                }
            }
            // 王炸
            if (isset($counts[16]) && isset($counts[17])) {
                return [16, 17];
            }
            break;
    }

    // 找不到同类型 → 考虑出炸弹/王炸
    if ($type !== 'bomb' && $type !== 'rocket') {
        // 手牌很少时考虑出炸弹
        if (count($hand) <= 8) {
            foreach ($counts as $val => $cnt) {
                if ($cnt === 4) {
                    return [$val, $val, $val, $val];
                }
            }
            if (isset($counts[16]) && isset($counts[17])) {
                return [16, 17];
            }
        }
    }

    return null;
}

// 找顺子
function findStraight($hand) {
    $unique = array_unique($hand);
    $valid = [];
    foreach ($unique as $v) {
        if ($v < 15) $valid[] = $v; // 不含2和王
    }
    sort($valid, SORT_NUMERIC);
    for ($len = 12; $len >= 5; $len--) {
        for ($i = 0; $i <= count($valid) - $len; $i++) {
            $isOk = true;
            for ($j = 1; $j < $len; $j++) {
                if ($valid[$i + $j] !== $valid[$i] + $j) { $isOk = false; break; }
            }
            if ($isOk) {
                return array_slice($valid, $i, $len);
            }
        }
    }
    return null;
}

// 找连对
function findPairStraight($hand) {
    $counts = array_count_values($hand);
    $pairs = [];
    foreach ($counts as $val => $cnt) {
        if ($cnt >= 2 && $val < 15) $pairs[] = $val;
    }
    sort($pairs, SORT_NUMERIC);
    for ($len = count($pairs); $len >= 3; $len--) {
        for ($i = 0; $i <= count($pairs) - $len; $i++) {
            $isOk = true;
            for ($j = 1; $j < $len; $j++) {
                if ($pairs[$i + $j] !== $pairs[$i] + $j) { $isOk = false; break; }
            }
            if ($isOk) {
                $result = [];
                for ($j = 0; $j < $len; $j++) $result[] = $pairs[$i + $j];
                $result2 = [];
                foreach ($result as $v) { $result2[] = $v; $result2[] = $v; }
                return $result2;
            }
        }
    }
    return null;
}

// 找等长更大的顺子
function findStraightFollow($hand, $minValue, $len) {
    $unique = array_unique($hand);
    $valid = [];
    foreach ($unique as $v) {
        if ($v < 15) $valid[] = $v;
    }
    sort($valid, SORT_NUMERIC);
    for ($i = 0; $i <= count($valid) - $len; $i++) {
        if ($valid[$i] <= $minValue) continue;
        $isOk = true;
        for ($j = 1; $j < $len; $j++) {
            if (!isset($valid[$i + $j]) || $valid[$i + $j] !== $valid[$i] + $j) { $isOk = false; break; }
        }
        if ($isOk) {
            return array_slice($valid, $i, $len);
        }
    }
    return null;
}

// 找等长更大的连对
function findPairStraightFollow($hand, $minValue, $len) {
    $counts = array_count_values($hand);
    $pairs = [];
    foreach ($counts as $val => $cnt) {
        if ($cnt >= 2 && $val < 15) $pairs[] = $val;
    }
    sort($pairs, SORT_NUMERIC);
    for ($i = 0; $i <= count($pairs) - $len; $i++) {
        if ($pairs[$i] <= $minValue) continue;
        $isOk = true;
        for ($j = 1; $j < $len; $j++) {
            if (!isset($pairs[$i + $j]) || $pairs[$i + $j] !== $pairs[$i] + $j) { $isOk = false; break; }
        }
        if ($isOk) {
            $result = [];
            for ($j = 0; $j < $len; $j++) {
                $result[] = $pairs[$i + $j];
                $result[] = $pairs[$i + $j];
            }
            return $result;
        }
    }
    return null;
}

// ==================================================
// 工具函数
// ==================================================
function phaseName($phase) {
    $map = [
        'waiting'   => '等待加入',
        'bidding'   => '叫地主',
        'grabbing'  => '抢地主',
        'playing'   => '出牌中',
        'finished'  => '已结束',
    ];
    return $map[$phase] ?? $phase;
}

function typeName($type) {
    $map = [
        'single'         => '单张',
        'pair'           => '对子',
        'triple'         => '三张',
        'triple_single'  => '三带一',
        'triple_pair'    => '三带二',
        'straight'       => '顺子',
        'pair_straight'  => '连对',
        'plane'          => '飞机',
        'plane_single'   => '飞机带单',
        'plane_pair'     => '飞机带对',
        'four_two_single'=> '四带二',
        'four_two_pair'  => '四带二对',
        'bomb'           => '炸弹',
        'rocket'         => '王炸',
    ];
    return $map[$type] ?? $type;
}

function sendPrivateMsg($token, $userId, $text) {
    if (empty($token) || empty($userId)) return false;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://chat-go.jwzhd.com/open-apis/v1/bot/send?token=' . urlencode($token),
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode([
            'recvId' => $userId,
            'recvType' => 'user',
            'contentType' => 'text',
            'content' => ['text' => $text],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_TIMEOUT => 5,
    ]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}
