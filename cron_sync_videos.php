<?php
// 此脚本为命令行 (CLI) 环境设计，超时时间设置为无限
set_time_limit(0); 

// 引入必要文件
require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\SearchMediaRequest;

// 从config获取数据库连接
$pdo = Database::getConnection();
if (!$pdo) {
    die("数据库配置未找到或不正确。请先通过浏览器运行 install.php。\n");
}

// 从config获取VOD客户端
$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) {
    die("创建VOD客户端失败，请检查配置文件中的阿里云密钥。\n");
}

echo "[" . date('Y-m-d H:i:s') . "] 开始使用 SearchMedia 进行全量同步...\n";

$scrollToken = null; // 初始化 ScrollToken
$pageSize = 100;
$totalSynced = 0;
$page = 1;

do {
    try {
        echo "正在获取第 " . $page++ . " 批数据...\n";
        
        $request = new SearchMediaRequest([
            'pageSize' => $pageSize,
            'sortBy' => 'CreationTime:Desc' 
        ]);
        
        if ($scrollToken) {
            $request->scrollToken = $scrollToken;
        }

        $response = $vodClient->searchMedia($request);
        
        $mediaList = $response->body->mediaList ?? [];
        $pageMediaCount = count($mediaList);

        if ($pageMediaCount > 0) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO videos (video_id, title, cover_url, duration, creation_time)
                 VALUES (:video_id, :title, :cover_url, :duration, :creation_time)
                 ON DUPLICATE KEY UPDATE
                   title = VALUES(title),
                   cover_url = VALUES(cover_url),
                   duration = VALUES(duration),
                   creation_time = VALUES(creation_time)"
            );

            foreach ($mediaList as $media) {
                if (isset($media->video)) {
                    $video = $media->video;

                    // !! 关键修复在这里：安全地处理 creation_time !!
                    // 1. 如果 creationTime 不为空，则格式化它
                    // 2. 如果为空，则将变量设置为 null
                    $creationTime = !empty($video->creationTime) 
                        ? str_replace(['T', 'Z'], ' ', $video->creationTime) 
                        : null;

                    $stmt->execute([
                        ':video_id' => $video->videoId,
                        ':title' => $video->title,
                        ':cover_url' => $video->coverURL,
                        ':duration' => !empty($video->duration) && is_numeric($video->duration) ? (float)$video->duration : 0,
                        ':creation_time' => $creationTime // 将格式化后的值或 null 传入
                    ]);
                }
            }
            $pdo->commit();
            $totalSynced += $pageMediaCount;
        }
        
        $scrollToken = $response->body->scrollToken ?? null;

        if ($scrollToken) {
            sleep(1); 
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 打印更详细的错误，包括是在处理哪个 videoId 时出错
        $errorVideoId = isset($video) ? $video->videoId : 'N/A';
        echo "处理 videoId: {$errorVideoId} 时出错 - " . $e->getMessage() . "\n";
        break; 
    }
} while ($scrollToken);

echo "[" . date('Y-m-d H:i:s') . "] 同步完成！本次共处理 " . $totalSynced . " 条视频数据。\n";
