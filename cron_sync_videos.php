<?php
// 此脚本为命令行 (CLI) 环境设计，超时时间设置为无限
set_time_limit(0);

// --- 增强1：引入文件锁，防止脚本重复执行 ---
$lockFile = __DIR__ . '/cron.lock';
$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // 如果文件被锁定，说明有另一个实例正在运行，则直接退出
    exit("另一个同步进程正在运行。\n");
}


// --- 增强2：定义一个简单的日志函数 ---
define('LOG_FILE', __DIR__ . '/logs/cron_sync_' . date('Y-m-d') . '.log');
function write_log(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[{$timestamp}] {$message}\n", FILE_APPEND);
}


// 引入必要文件
require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\SearchMediaRequest;

// 检查数据库连接
$pdo = Database::getConnection();
if (!$pdo) {
    write_log("致命错误: 数据库配置未找到或不正确。请先通过浏览器运行 install.php。");
    exit; // 严重问题，直接退出
}

// 检查VOD客户端
$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) {
    write_log("致命错误: 创建VOD客户端失败，请检查配置文件中的阿里云密钥。");
    exit; // 严重问题，直接退出
}


write_log("===== 开始使用 SearchMedia 进行全量同步 =====");

$scrollToken = null;
$pageSize = 100; // 每次API调用获取的数量
$totalSynced = 0;
$totalFailed = 0;
$page = 1;

do {
    $currentVideoId = 'N/A'; // 用于记录当前处理的视频ID，方便排错
    try {
        write_log("正在获取第 " . $page++ . " 批数据...");
        
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
            // 使用事务确保数据一致性
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
                // --- 增强3：更强的错误容忍度 ---
                // 将try-catch移入循环内部，单个视频失败不影响整体
                try {
                    if (!isset($media->video) || !isset($media->video->videoId)) {
                        write_log("警告: 收到一条无效的媒体数据，已跳过。");
                        continue;
                    }

                    $video = $media->video;
                    $currentVideoId = $video->videoId;

                    $creationTime = !empty($video->creationTime) 
                        ? str_replace(['T', 'Z'], ' ', $video->creationTime) 
                        : null;

                    $stmt->execute([
                        ':video_id' => $currentVideoId,
                        ':title' => $video->title,
                        ':cover_url' => $video->coverURL,
                        ':duration' => !empty($video->duration) && is_numeric($video->duration) ? (float)$video->duration : 0,
                        ':creation_time' => $creationTime
                    ]);
                } catch (Exception $e) {
                    $totalFailed++;
                    // 记录单条视频处理失败的日志，然后继续处理下一条
                    write_log("错误: 处理 videoId: {$currentVideoId} 时失败 - " . $e->getMessage());
                    continue; // 继续下一个循环
                }
            }
            $pdo->commit();
            $totalSynced += ($pageMediaCount - $totalFailed); // 减去本页失败的数量
        }
        
        $scrollToken = $response->body->scrollToken ?? null;

        // 如果还有下一页，稍微等待一下，避免API调用过于频繁
        if ($scrollToken) {
            sleep(1); 
        }

    } catch (Exception $e) {
        // 这个catch块处理API调用、数据库连接等全局性的大问题
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        write_log("严重错误: 在获取分页数据时出错 (当前VideoId: {$currentVideoId}) - " . $e->getMessage());
        break; // 出现严重错误，中断整个同步过程
    }
} while ($scrollToken);

write_log("同步完成！本次成功处理 {$totalSynced} 条，失败 {$totalFailed} 条视频数据。");
write_log("===== 同步任务结束 =====");


// --- 增强1（后续）：脚本结束时，释放文件锁 ---
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
