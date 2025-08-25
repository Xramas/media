<?php
// 文件: cron_discover_videos.php (v4)
set_time_limit(0);

require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\GetVideoListRequest;

$pdo = Database::getConnection();
if (!$pdo) { echo "致命错误: 数据库连接失败。\n"; exit(1); }
$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) { echo "致命错误: VOD客户端创建失败。\n"; exit(1); }

echo "===== 开始发现新视频 (GetVideoList) =====\n";
$pageNo = 1;
$totalDiscovered = 0;

try {
    while (true) {
        printf("  -> 正在请求第 %d 页...\r", $pageNo);
        $request = new GetVideoListRequest(['status' => 'Normal', 'pageNo' => $pageNo, 'pageSize' => 100]);
        $response = $vodClient->getVideoList($request);
        $videoList = $response->body->videoList->video ?? [];
        $videoCount = count($videoList);

        if ($videoCount > 0) {
            // !! 关键改动：使用与数据库一致的大小写字段名 !!
            $stmt = $pdo->prepare(
                "INSERT INTO videos (VideoId, CreationTime)
                 VALUES (:VideoId, :CreationTime)
                 ON DUPLICATE KEY UPDATE CreationTime=VALUES(CreationTime)"
            );
            
            foreach ($videoList as $video) {
                $creationTime = !empty($video->creationTime) ? str_replace(['T', 'Z'], ' ', $video->creationTime) : null;
                $stmt->execute([':VideoId' => $video->videoId, ':CreationTime' => $creationTime]);
            }
            $totalDiscovered += $videoCount;
        }

        if ($videoCount < 100) { break; }
        $pageNo++;
        sleep(1);
    }
    echo "\n";
    echo "发现任务完成，累计处理 " . $totalDiscovered . " 个视频ID。\n";
} catch (Exception $e) { echo "\n严重错误: " . $e->getMessage() . "\n"; }

echo "===== 发现任务结束 =====\n";
