<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('RAIDER2_STOP_DIR', __DIR__ . '/tmp_raider2_stop');
if (!file_exists(RAIDER2_STOP_DIR)) {
    @mkdir(RAIDER2_STOP_DIR, 0777, true);
}
function raider2_stop_file($processId) {
    return RAIDER2_STOP_DIR . '/stop_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $processId);
}
function raider2_is_stopped($processId) {
    return !empty($processId) && file_exists(raider2_stop_file($processId));
}

function raider2_clear_stop($processId) {
    $file = raider2_stop_file($processId);
    if (file_exists($file)) {
        @unlink($file);
    }
}
function raider2_check_stop($processId) {
    if (!empty($processId) && raider2_is_stopped($processId)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'stopped' => true,
            'message' => 'とめちゃったよ',
            'processId' => $processId
        ]);
        exit;
    }
}
// メンバー取得
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $processId = isset($_POST['processId']) ? trim($_POST['processId']) : '';
    if ($action === 'stop') {
        header('Content-Type: application/json');
        if (empty($processId)) {
            echo json_encode(['success' => false, 'error' => 'processIdが必要です']);
            exit;
        }
        if (file_put_contents(raider2_stop_file($processId), 'stop') === false) {
            echo json_encode(['success' => false, 'error' => '停止失敗']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => '停止しろｗ', 'processId' => $processId]);
        exit;
    }
    if ($action === 'getMembers') {
        header('Content-Type: application/json');
$tokens = preg_split('/[\s,]+/', trim($_POST['token']));
$tokens = array_filter(array_map('trim', $tokens));
if (empty($tokens)) {
    echo json_encode([
        'success' => false,
        'error' => 'トークンなし'
    ]);
    exit;
}
        $token = reset($tokens); 
        $guildId = trim($_POST['guildId']);
        $delay = isset($_POST['delay']) ? floatval($_POST['delay']) : 0.5;
        $usleepTime = intval($delay * 100000);
        
        if (empty($token) || empty($guildId)) {
            echo json_encode(['success' => false, 'error' => 'トークンとサーバーIDを入力してください']);
            exit;
        }
        
        if (!preg_match('/^\d{17,20}$/', $guildId)) {
            echo json_encode(['success' => false, 'error' => 'サーバーIDが無効']);
            exit;
        }
    
        $ch = curl_init('https://discord.com/api/v10/users/@me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $token,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $userResult = curl_exec($ch);
        $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($userHttpCode !== 200) {
            $errorData = json_decode($userResult, true);
            echo json_encode([
                'success' => false, 
                'error' => 'トークンが無効: ' . $userHttpCode . ' - ' . (isset($errorData['message']) ? $errorData['message'] : 'ガチエラー')
            ]);
            exit;
        }
        
        $memberIds = array();
        $after = null;
        $hasMore = true;
        while ($hasMore) {
            $url = "https://discord.com/api/v10/guilds/$guildId/members?limit=100";
            if ($after) {
                $url .= "&after=$after";
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: ' . $token,
                'Content-Type: application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
 
                if ($httpCode === 403 && $after === null) {

                    $ch = curl_init("https://discord.com/api/v10/guilds/$guildId/channels");
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Authorization: ' . $token,
                        'Content-Type: application/json'
                    ));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $channelsResult = curl_exec($ch);
                    $channelsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($channelsHttpCode === 200) {
                        $channels = json_decode($channelsResult, true);
                        if (is_array($channels)) {
                            foreach ($channels as $channel) {
                                if (!is_array($channel)) continue;
                                if (isset($channel['type']) && $channel['type'] == 0 && isset($channel['id'])) {
                                    $channelId = $channel['id'];
                                    $before = null;
                                    $channelHasMore = true;
                                    $maxMessagesPerChannel = 1000; 
                                    $messageCount = 0;
                                    while ($channelHasMore && $messageCount < $maxMessagesPerChannel) {
                                        $msgUrl = "https://discord.com/api/v10/channels/$channelId/messages?limit=100";
                                        if ($before) {
                                            $msgUrl .= "&before=$before";
                                        }
                                        $msgCh = curl_init($msgUrl);
                                        curl_setopt($msgCh, CURLOPT_HTTPHEADER, array(
                                            'Authorization: ' . $token,
                                            'Content-Type: application/json'
                                        ));
                                        curl_setopt($msgCh, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($msgCh, CURLOPT_TIMEOUT, 10);
                                        $msgResult = curl_exec($msgCh);
                                        $msgHttpCode = curl_getinfo($msgCh, CURLINFO_HTTP_CODE);
                                        curl_close($msgCh);
                                        
                                        if ($msgHttpCode === 200) {
                                            $messages = json_decode($msgResult, true);
                                            if (is_array($messages) && count($messages) > 0) {
                                                foreach ($messages as $message) {
                                                    if (!is_array($message)) continue;
                                                    if (isset($message['author']['id'])) {
                                                        $authorId = $message['author']['id'];
                                                        if (!in_array($authorId, $memberIds)) {
                                                            $memberIds[] = $authorId;
                                                        }
                                                    }
                                                    if (isset($message['mentions']) && is_array($message['mentions'])) {
                                                        foreach ($message['mentions'] as $mention) {
                                                            if (isset($mention['id']) && !in_array($mention['id'], $memberIds)) {
                                                                $memberIds[] = $mention['id'];
                                                            }
                                                        }
                                                    }
                                                    if (isset($message['referenced_message']) && is_array($message['referenced_message'])) {
                                                        $refMsg = $message['referenced_message'];
                                                        if (isset($refMsg['author']['id']) && !in_array($refMsg['author']['id'], $memberIds)) {
                                                            $memberIds[] = $refMsg['author']['id'];
                                                        }
                                                    }
                                                }
                                                $messageCount += count($messages);
                                                if (count($messages) < 100) {
                                                    $channelHasMore = false;
                                                } else {
                                                    $before = $messages[count($messages) - 1]['id'];
                                                }
                                            } else {
                                                $channelHasMore = false;
                                            }
                                        } else {
                                            $channelHasMore = false;
                                        }
                                        usleep($usleepTime);
                                    }
                                }
                            }
                            if (count($memberIds) > 0) {
                                echo json_encode([
                                    'success' => true, 
                                    'memberIds' => array_values(array_unique($memberIds)), 
                                    'count' => count(array_unique($memberIds)),
                                    'note' => '危ないにゃんこたちｗ'
                                ]);
                                exit;
                            }
                        }
                    }
                }
                
                $errorData = json_decode($result, true);
    }
    if (empty($processId)) {
        $processId = uniqid('raider_', true);
    }
    ignore_user_abort(true);
    raider2_clear_stop($processId);
    header('Content-Type: application/json');
    $tokens = preg_split('/[\s,]+/', trim($_POST['token']));
    $tokens = array_filter(array_map('trim', $tokens));
                $errorMessage = 'APIエラー: ' . $httpCode;
                if (isset($errorData['message'])) {
                    $errorMessage .= ' - ' . $errorData['message'];
                }
                if (isset($errorData['code'])) {
                    $errorCode = $errorData['code'];
                    $errorMessage .= ' (コード: ' . $errorCode . ')';
                    $errorDetails = '';
                    switch ($errorCode) {
                        case 50001:
                            $errorDetails = 'ガチエラー';
                            break;
                        case 50013:
                            $errorDetails = 'ガチエラー';
                            break;
                        case 10004:
                            $errorDetails = 'ガチエラー';
                            break;
                        default:
                            if ($httpCode === 403) {
                                $errorDetails = 'ガチエラー';
                            }
                    }
                    if ($errorDetails) {
                        $errorMessage .= '\n\n' . $errorDetails;
                    }
                } else if ($httpCode === 403) {
                    $errorMessage .= 'ガチエラー';
                }
                echo json_encode([
                    'success' => false, 
                    'error' => $errorMessage,
                    'error_code' => isset($errorData['code']) ? $errorData['code'] : null,
                    'raw_response' => substr($result, 0, 500)
                ]);
                exit;
            }
            $members = json_decode($result, true);
            if (!is_array($members) || count($members) === 0) {
                $hasMore = false;
            }
            foreach ($members as $member) {
                if (!is_array($member)) continue;
                if (isset($member['user']['id'])) {
                    $memberIds[] = $member['user']['id'];
                }
            }
            if (count($members) < 100) { //ランダムメンション時取得自体がサーバーのチャンネルの発言者なので人数多いとだるいから100人まで取得
                $hasMore = false;
            } else {

                $lastMember = $members[count($members) - 1];
                if (isset($lastMember['user']['id'])) {
                    $after = $lastMember['user']['id'];
                } else {
                    $hasMore = false;
                }
            }
            usleep($usleepTime); 
        echo json_encode(['success' => true, 'memberIds' => $memberIds, 'count' => count($memberIds)]);
        exit;
    }
    $tokens = preg_split('/[\s,]+/', trim($_POST['token']));
    $tokens = array_filter(array_map('trim', $tokens));
    $kazu = intval($_POST['kazu']);
    $guildIds = explode(',', $_POST['chi']); 
    $message = $_POST['msg'];
    $delay = isset($_POST['delay']) ? floatval($_POST['delay']) : 0.5;
    $usleepTime = intval($delay * 100000);
    $channelIds = array();
    if (isset($_POST['channelIds']) && !empty($_POST['channelIds'])) {
        $channelIds = array_filter(array_map('trim', explode(',', $_POST['channelIds'])));
    }
    $str = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $memberIdsArray = array();
    if (isset($_POST['memberIds']) && !empty($_POST['memberIds'])) {
        $memberIdsArray = array_filter(array_map('trim', explode(',', $_POST['memberIds'])));
    }
    $channelCache = array();
    $channelRequests = array();
    $channelRequestMap = array(); 
     // リクエスト処理とメッセージ形成の部分
    if (empty($channelIds)) {
        raider2_check_stop($processId);
        foreach ($tokens as $token) {
            $token = trim($token); 
            if (empty($token)) continue;
            foreach ($guildIds as $guildId) {
                $guildId = trim($guildId);
                if (empty($guildId)) continue;
                
                $cacheKey = $token . '|' . $guildId;
                if (!isset($channelCache[$cacheKey])) {
                    $url = "https://discord.com/api/v10/guilds/$guildId/channels";
                    $channelRequests[] = array(
                        'url' => $url,
                        'token' => $token,
                        'cacheKey' => $cacheKey
                    );
                    $channelRequestMap[count($channelRequests) - 1] = array('token' => $token, 'guildId' => $guildId);
                }
            }
        }
        if (count($channelRequests) > 0) {
            $channelMultiHandle = curl_multi_init();
            $channelCurlHandles = array();
            $maxConcurrentChannels = 10; //お好み
            $channelRequestIndex = 0;
            $totalChannelRequests = count($channelRequests);
            while ($channelRequestIndex < $totalChannelRequests && count($channelCurlHandles) < $maxConcurrentChannels) {
                $req = $channelRequests[$channelRequestIndex];
                $ch = curl_init($req['url']);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: ' . $req['token'],
                    'Content-Type: application/json'
                ));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                curl_multi_add_handle($channelMultiHandle, $ch);
                $channelCurlHandles[$channelRequestIndex] = $ch;
                $channelRequestIndex++;
            }
            do {
                raider2_check_stop($processId);
                $mrc = curl_multi_exec($channelMultiHandle, $running);
                curl_multi_select($channelMultiHandle, 0.1);
                
                while ($info = curl_multi_info_read($channelMultiHandle)) {
                    if ($info['msg'] == CURLMSG_DONE) {
                        $handle = $info['handle'];
                        $key = array_search($handle, $channelCurlHandles);
                        
                        if ($key !== false) {
                            $req = $channelRequests[$key];
                            $result = curl_multi_getcontent($handle);
                            $channels = json_decode($result, true);
                            
                            if (is_array($channels)) {
                                $channelCache[$req['cacheKey']] = $channels;
                            }
                            
                            curl_multi_remove_handle($channelMultiHandle, $handle);
                            curl_close($handle);
                            unset($channelCurlHandles[$key]);
                            if ($channelRequestIndex < $totalChannelRequests) {
                                $req = $channelRequests[$channelRequestIndex];
                                $ch = curl_init($req['url']);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                    'Authorization: ' . $req['token'],
                                    'Content-Type: application/json'
                                ));
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                                
                                curl_multi_add_handle($channelMultiHandle, $ch);
                                $channelCurlHandles[$channelRequestIndex] = $ch;
                                $channelRequestIndex++;
                            }
                        }
                    }
                }
            } while ($running > 0 || $channelRequestIndex < $totalChannelRequests);
            foreach ($channelCurlHandles as $handle) {
                curl_multi_remove_handle($channelMultiHandle, $handle);
                curl_close($handle);
            }
            curl_multi_close($channelMultiHandle);
        }
    }
    $requests = array();
    $mentionIndex = 0;
    $totalMembers = count($memberIdsArray);
    $membersPerMention = intval($_POST['menmen']); 
    for ($i = 0; $i < $kazu; $i++) {
        raider2_check_stop($processId);
        if (!empty($channelIds)) {
            foreach ($tokens as $token) {
                $token = trim($token); 
                if (empty($token)) continue;
                foreach ($channelIds as $channelId) {
                    $channelId = trim($channelId);
                    if (empty($channelId)) continue;
                    
                    $be = substr(str_shuffle($str), 0, 10);
                    $mentions = '';
                    if ($totalMembers > 0) {
                        $mentionCount = min($membersPerMention, $totalMembers);
                        $selectedMembers = array();
                        
                        for ($j = 0; $j < $mentionCount; $j++) {
                            $currentIndex = ($mentionIndex + $j) % $totalMembers;
                            $selectedMembers[] = $memberIdsArray[$currentIndex];
                        }
                        $mentionIndex = ($mentionIndex + $mentionCount) % $totalMembers;
                        foreach ($selectedMembers as $memberId) {
                            $mentions .= '<@' . $memberId . '> ';
                        }
                        $message2 = $mentions . $message . " " . $be;
                    } else {
                        $message2 = $message . " " . $be;
                    }
                    $url2 = "https://discord.com/api/v10/channels/$channelId/messages";
                    $requests[] = array(
                        'url' => $url2,
                        'token' => $token,
                        'data' => array('content' => $message2)
                    );
                }
            }
        } else {
            foreach ($tokens as $token) {
                $token = trim($token); 
                if (empty($token)) continue;
                foreach ($guildIds as $guildId) {
                    $guildId = trim($guildId);
                    if (empty($guildId)) continue;
                    
                    $cacheKey = $token . '|' . $guildId;
                    if (!isset($channelCache[$cacheKey])) continue;
                    
                    $channels = $channelCache[$cacheKey];
                    if (!is_array($channels)) continue;

                    foreach ($channels as $channel) {
                 
                        if (!is_array($channel)) continue;
                        if (!isset($channel['type']) || !isset($channel['id'])) continue;
           
                        if ($channel['type'] != 0) continue;
                        $channelId = $channel['id'];
                        $be = substr(str_shuffle($str), 0, 10);
                        $mentions = '';
                        if ($totalMembers > 0) {
                            $mentionCount = min($membersPerMention, $totalMembers);
                            $selectedMembers = array();
                            
                            for ($j = 0; $j < $mentionCount; $j++) {
                                $currentIndex = ($mentionIndex + $j) % $totalMembers;
                                $selectedMembers[] = $memberIdsArray[$currentIndex];
                            }
                            
                            $mentionIndex = ($mentionIndex + $mentionCount) % $totalMembers;
                            
                            foreach ($selectedMembers as $memberId) {
                                $mentions .= '<@' . $memberId . '> ';
                            }
                            $message2 = $message . $mentions . " " . $be;
                        } else {
                            $message2 = $message . " " . $be;
                        }
                        $url2 = "https://discord.com/api/v10/channels/$channelId/messages";
                        
               
                        $requests[] = array(
                            'url' => $url2,
                            'token' => $token,
                            'data' => array('content' => $message2)
                        );
                    }
                }
            }
        }
    }
    //並列処理
    $multiHandle = curl_multi_init();
    $curlHandles = array();
    $maxConcurrent = 10; 
    $requestIndex = 0;
    $totalRequests = count($requests);
    while ($requestIndex < $totalRequests && count($curlHandles) < $maxConcurrent) {
        $request = $requests[$requestIndex];
        $ch = curl_init($request['url']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $request['token'],
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request['data']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$requestIndex] = $ch;
        $requestIndex++;
    }
    do {
        raider2_check_stop($processId);
        $mrc = curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle, 0.1);
        while ($info = curl_multi_info_read($multiHandle)) {
            if ($info['msg'] == CURLMSG_DONE) {
                $handle = $info['handle'];
                $key = array_search($handle, $curlHandles);
                curl_multi_remove_handle($multiHandle, $handle);
                curl_close($handle);
                unset($curlHandles[$key]);
                
                usleep($usleepTime); 
                
                if ($requestIndex < $totalRequests) {
                    $request = $requests[$requestIndex];
                    $ch = curl_init($request['url']);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Authorization: ' . $request['token'],
                        'Content-Type: application/json'
                    ));
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request['data']));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    curl_multi_add_handle($multiHandle, $ch);
                    $curlHandles[$requestIndex] = $ch;
                    $requestIndex++;
                }
            }
        }
    } while ($running > 0 || $requestIndex < $totalRequests);
    foreach ($curlHandles as $handle) {
        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);
    }
    
    curl_multi_close($multiHandle);
    echo json_encode([
        'success' => true,
        'message' => '処理を完了しました',
        'processId' => $processId,
        'sent' => $totalRequests
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="content-type" charset="UTF-8"> 
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="keywords" content="にゃんこraider" />
<meta name="description" content="webランダムメンション" />
    <title>おにゃんこ</title>
    <script src="https://cdn.tailwindcss.com"></script>
 <link rel="stylesheet" href="data/fl.css">
</head>
<body class="relative pt-5 bg-[url(data/haikei.png)]  overflow-x-hidden">
  <div class="absolute inset-0 bg-[url('data/haikei.png')] bg-[length:100%_100%] bg-no-repeat z-0">
    <div class="stars"></div>
  </div>
<div id="obj" class="absolute left-0 top-0 pointer-events-none">
  <img src="http://localhost/nyanko/raid/data/ww.png"
       class="w-[900px] drop-shadow-[0_0_40px_cyan]" />
</div>
<!-- 背景のロケット-->
<script>
const obj = document.getElementById("obj");
let x = window.innerWidth / 2;
let y = window.innerHeight / 2;
let mouseX = x;
let mouseY = y;
let angle = 0;
window.addEventListener("mousemove", (e) => {
  mouseX = e.clientX;
  mouseY = e.clientY;
});
function animate() {
  const moveSpeed = 0.01; 
  x += (mouseX - x) * moveSpeed;
  y += (mouseY - y) * moveSpeed;
  const targetAngle = Math.atan2(mouseY - y, mouseX - x) * (180 / Math.PI);
  const angleSpeed = 0.04; 
  angle += (targetAngle - angle) * angleSpeed;
  obj.style.transform = `
    translate(${x}px, ${y}px)
    rotate(${angle}deg)
  `;
  requestAnimationFrame(animate);
}
animate();
</script>
    <img class="manzdev" src="https://pbs.twimg.com/media/HHmQ_lVagAArLOV?format=png&name=small" alt="ManzDev">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQdLQOXiC87IOL1wgR-rsGWEgfJKivI3N_5wg&s" alt="tailwind" class="tailwind">
    <img src="https://cdn.creazilla.com/icons/3254431/tailwindcss-icon-icon-size_512.png" alt="css" class="css">
   <div class="mx-auto max-w-screen-2xl px-4 md:px-8 relative z-20">
            <a href="" class="inline-flex items-center gap-2.5 text-2xl font-bold text-red-500 md:text-3xl" aria-label="logo">
                <img class="animate-spin h-10 w-10 text-indigo-500" fill="currentColor" src="https://pbs.twimg.com/profile_images/2050634965206138880/7ylzqCaE_400x400.jpg" style="border-radius:50%;">
                BOOT NTR
            </a>
            <div class="text-center">
                <p class="mb-4 text-2xl font-bold text-white md:mb-6 lg:text-3xl animate-bounce ">BOOT NTR CFW</p>
                <a href="https://www.youtube.com/watch?v=r7Z0HgfIbHg&lc=UgxXE-c4ep18zvHjNIl4AaABAg"class="font-bold text-red-500">詳しい使い方はここを押せ</a>
            </div>
            <form id="botForm" method="post">
                <input type="hidden" id="action" name="action" value="send" />
                <input type="hidden" id="processId" name="processId" value="" />
                <div class="sm:col-span-2">
                    <label for="token" class="mb-2 inline-block text-sm text-white sm:text-base">
    token
</label>
<textarea id="token" name="token" value="" class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label for="chi" class="mb-2 inline-block text-sm text-white sm:text-base">
    サーバーID
</label>
<input type="text" value="" id="chi" name="chi" class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring" />
                </div>
                <div class="sm:col-span-2">
                    <label for="delay" class="mb-2 inline-block text-sm text-white sm:text-base">
    遅延時間
</label>
<input type="text" value="0.5" id="delay" name="delay" class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring" />
                </div>
                <div class="sm:col-span-2">
                    <label for="channelIds" class="mb-2 inline-block text-sm text-white sm:text-base">
    チャンネルID優先
</label>
<input type="text" value="" id="channelIds" name="channelIds" class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring" />
                </div>
                                <div class="sm:col-span-2">
                    <label for="channelIds" class="mb-2 inline-block text-sm text-white sm:text-base">
    メンション数
</label>
<input type="number" value="" id="menmen" name="menmen" class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring" />
                </div>
                
                <div class="sm:col-span-2">
                    <label for="msg" class="mb-2 inline-block text-sm text-white sm:text-base">めせじ</label>
                    <textarea name="msg" value=""class="h-64 w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring"></textarea>
                </div>
         
				<div class="sm:col-span-2">
                    <label for="chi" class="mb-4 inline-block text-sm text-white sm:text-base">送信数</label>
                    <input type="text" value="100"id="kazu" name="kazu" class="mb-4 w-full rounded border bg-gray-50 px-3 py-3 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring" />
                </div>
                <div class="flex items-center sm:col-span-2 mb-4 gap-2">
                    <button type="submit" id="startBtn" class="inline-block rounded-lg bg-red-500 px-8 py-3 text-center text-sm font-semibold text-white outline-none ring-red-300 transition duration-100 hover:bg-red-600 focus-visible:ring active:bg-red-700 logo-glow" style="filter: drop-shadow(0 0 4px rgb(204, 87, 87)) ">FIREEEEEEEEEEEEEEEEEEEEEEEEEEEEE</button>
                    <button type="button" id="stopBtn" disabled class="inline-block rounded-lg bg-gray-500 px-8 py-3 text-center text-sm font-semibold text-white outline-none ring-gray-300 transition duration-100 hover:bg-gray-600 focus-visible:ring active:bg-gray-700 logo-glow" style="filter: drop-shadow(0 0 4px rgb(66, 66, 66)) drop-shadow(0 0 1px rgb(97, 97, 97));">停止</button>
                </div>
                
                <div class="sm:col-span-2 mb-4">
                    <button type="button" id="getMembersBtn" class="inline-block rounded-lg bg-blue-500 px-8 py-3 text-center text-sm font-semibold text-white outline-none ring-blue-300 transition duration-100 hover:bg-blue-600 focus-visible:ring active:bg-blue-700 md:text-base logo-glow" style="filter: drop-shadow(0 0 4px rgb(47, 118, 165)) ">Random mentionぽちっとなｗ</button>
                </div>
                
                <div class="sm:col-span-2 mb-4">
                    <label for="memberResult" class="mb-2 inline-block text-sm text-white sm:text-base">取得したメンバーUID</label>
                    <textarea id="memberResult" readonly class="h-64 w-full rounded border bg-gray-50 px-3 py-2 text-gray-800 outline-none ring-indigo-300 transition duration-100 focus:ring" placeholder="ここにメンバーUIDが表示されます..."></textarea>
                    <div id="memberStatus" class="mt-2 text-sm text-gray-600"></div>
                    <input type="hidden" id="memberIds" name="memberIds" value="" />
                </div>

<div class="flex divide-x rounded-lg border bg-gray-50">
      <div class="flex items-center p-2 text-indigo-500 md:p-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>

   <div class="p-4 md:p-6">
        <h3 class="mb-2 text-lg font-semibold md:text-xl">SIMPLE AND NYANKO&#128574;</h3>
        <p class="text-gray-500">ついにおにゃんこパワーを解き放つ時がきたニャ 質問とか喧嘩売りたい人はdiscord @eg43もしくわ、、、、twitter @ctkp44rr</p>
</div>
            </form>
        </div>

</body>
</html>
<script>
    //phpで停止ができないからjs使用
document.addEventListener('DOMContentLoaded', function() {
    const getMembersBtn = document.getElementById('getMembersBtn');
    const memberResult = document.getElementById('memberResult');
    const memberStatus = document.getElementById('memberStatus');
    const tokenInput = document.getElementById('token');
    const guildIdInput = document.getElementById('chi');
    const botForm = document.getElementById('botForm');
    const actionInput = document.getElementById('action');
    const processIdInput = document.getElementById('processId');
    const stopBtn = document.getElementById('stopBtn');
    const startBtn = document.getElementById('startBtn');
    let currentProcessId = '';
    let isRunning = false;
    function setStatus(message, colorClass) {
        memberStatus.textContent = message;
        memberStatus.className = 'mt-2 text-sm ' + colorClass;
    }
    async function sendRequest(formData) {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        return response.json();
    }
    botForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (isRunning) {
            return;
        }

        currentProcessId = 'raider_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        processIdInput.value = currentProcessId;
        actionInput.value = 'send';
        stopBtn.disabled = false;
        startBtn.disabled = true;
        isRunning = true;
        setStatus('successssssssssssssssssssssssssssssss', 'text-blue-600');

        const formData = new FormData(botForm);
        try {
            const data = await sendRequest(formData);
            if (data.success) {
                setStatus('successssssssssssssss', 'text-green-600');
            } else if (data.stopped) {
                setStatus('stop successful', 'text-yellow-600');
            } else {
                setStatus('エラー: ' + (data.error || 'ガチエラー'), 'text-red-600');
                console.error('Error details:', data);
            }
        } catch (error) {
            setStatus('エラー: ' + error.message, 'text-red-600');
            console.error('Error:', error);
        } finally {
            stopBtn.disabled = true;
            startBtn.disabled = false;
            isRunning = false;
            currentProcessId = '';
            processIdInput.value = '';
        }
    });

    stopBtn.addEventListener('click', async function() {
        if (!currentProcessId || !isRunning) {
            return;
        }
        stopBtn.disabled = true;
        setStatus('停止', 'text-yellow-600');

        const formData = new FormData();
        formData.append('action', 'stop');
        formData.append('processId', currentProcessId);

        try {
            const data = await sendRequest(formData);
            if (data.success) {
                setStatus('停止', 'text-yellow-600');
            } else {
                setStatus('停止エラー: ' + (data.error || 'ガチエラー'), 'text-red-600');
                console.error('Stop error details:', data);
                stopBtn.disabled = false;
            }
        } catch (error) {
            setStatus('停止エラー: ' + error.message, 'text-red-600');
            console.error('Stop request error:', error);
            stopBtn.disabled = false;
        }
    });

    getMembersBtn.addEventListener('click', async function() {
        const token = tokenInput.value.trim();
        const guildId = guildIdInput.value.trim();

        if (!token || !guildId) {
            setStatus('エラー: トークンとサーバーIDを入力してください', 'text-red-600');
            return;
        }

        const firstToken = token.split(',')[0].trim();

        setStatus('危ないメンバーを取得中...（時間がかかる場合があります）', 'text-blue-600');
        memberResult.value = '';
        getMembersBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'getMembers');
            formData.append('token', firstToken);
            formData.append('guildId', guildId);

            const data = await sendRequest(formData);

            if (data.success) {
                if (data.memberIds && data.memberIds.length > 0) {
                    memberResult.value = data.memberIds.join('\n');
                    document.getElementById('memberIds').value = data.memberIds.join(',');
                    setStatus(`成功: ${data.count}人の危ないメンバーUIDを取得したおにゃんこだっちゃｗ`, 'text-green-600');
                } else {
                    setStatus('危ないメンバーいないにゃんこ', 'text-yellow-600');
                }
            } else {
                let errorMsg = 'ガチエラー: ' + (data.error || 'ガチエラー');
                if (data.raw_response) {
                    errorMsg += '\n詳細: ' + data.raw_response.substring(0, 200);
                }
                memberStatus.innerHTML = errorMsg.replace(/\n/g, '<br>');
                memberStatus.className = 'mt-2 text-sm text-red-600 whitespace-pre-wrap';
                console.error('Error details:', data);
            }
        } catch (error) {
            setStatus('ガチエラー: ' + error.message, 'text-red-600');
            console.error('Error:', error);
        } finally {
            getMembersBtn.disabled = false;
        }
    });
});
</script>