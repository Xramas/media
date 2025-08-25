<?php
// 文件: cron_discover_videos.php (v8 - 分批事务最终版)

set_time_limit(0);

// --- 1. 注册必定执行的清理函数 ---
$lockFile = __DIR__ . '/discover.lock';
$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle) { echo "错误：无法创建锁文件。\n"; exit(1); }
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    if ($lockHandle) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        @unlink($lockFile);
        echo "\n清理完成，发现任务锁已移除。\n";
    }
});

// --- 2. 尝试获取文件锁 ---
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "另一个发现任务正在运行，脚本退出。\n";
    exit(1);
}

// --- 3. 初始化 ---
require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\SearchMediaRequest;

$pdo = Database::getConnection();
if (!$pdo) { echo "致命错误: 数据库连接失败。\n"; exit(1); }
$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) { echo "致命错误: VOD客户端创建失败。\n"; exit(1); }

// --- 4. 主逻辑 ---
echo "===== 开始发现新视频 (SearchMedia) (按 Ctrl+C 可安全退出) =====\n";
$totalDiscovered = 0;
$scrollToken = null;
$page = 1;

try {
    while (true) {
        printf("  -> 正在请求第 %d 批... 累计已发现 %d 条\r", $page++, $totalDiscovered);
        
        $request = new SearchMediaRequest([
            'searchType'  => 'video',
            'status'      => 'Normal',
            'sortBy'      => 'CreationTime:Desc',
            'scrollToken' => $scrollToken,
            'returnFields' => 'video,CreationTime',
            'pageSize'    => 100
        ]);

        $response = $vodClient->searchMedia($request);
        $mediaList = $response->body->mediaList ?? [];
        $mediaCount = count($mediaList);

        if ($mediaCount > 0) {
            // !! 关键修复：将事务处理移入循环内部，为每一批数据开启独立的事务 !!
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO videos (VideoId, CreationTime)
                     VALUES (:VideoId, :CreationTime)
                     ON DUPLICATE KEY UPDATE CreationTime=VALUES(CreationTime)"
                );

                foreach ($mediaList as $media) {
                    if (isset($media->video->videoId) && !empty($media->video->videoId)) {
                        $creationTime = !empty($media->creationTime) ? str_replace(['T', 'Z'], ' ', $media->creationTime) : null;
                        $stmt->execute([':VideoId' => $media->video->videoId, ':CreationTime' => $creationTime]);
                    }
                }
                
                // 提交本批次的100条数据
                $pdo->commit();
                $totalDiscovered += $mediaCount;

            } catch (Exception $e) {
                // 如果本批次内部出错，则回滚本批次
                $pdo->rollBack();
                echo "\n警告：处理批次 " . ($page - 1) . " 时数据库出错，已跳过。错误: " . $e->getMessage() . "\n";
            }
        }

        if (empty($response->body->scrollToken) || $mediaCount === 0) {
            break;
        }
        
        $scrollToken = $response->body->scrollToken;
        sleep(1);
    }

    echo "\n";
    echo "发现任务完成，本次运行累计处理 " . $totalDiscovered . " 条记录。\n";

} catch (Exception $e) {
    // 这个 catch 现在只处理API调用等全局性错误
    echo "\n严重错误 (API层面): " . $e->getMessage() . "\n";
}

echo "===== 发现任务结束 =====\n";
exit(0);
