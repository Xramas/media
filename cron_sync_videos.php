<?php
// 文件: cron_sync_videos.php (专业增强版)

// 此脚本为命令行 (CLI) 环境设计，超时时间设置为无限
set_time_limit(0);

// --- 1. 定义锁文件路径和句柄 ---
$lockFile = __DIR__ . '/cron.lock';
$lockHandle = fopen($lockFile, 'c');

if (!$lockHandle) {
    echo "错误：无法创建锁文件。\n";
    exit(1);
}

// --- 2. 注册一个“必定会执行”的清理函数 ---
// 无论脚本如何退出（正常结束、出错、被Ctrl+C中断），此函数都会被调用
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    if ($lockHandle) {
        flock($lockHandle, LOCK_UN); // 释放文件锁
        fclose($lockHandle);       // 关闭文件句柄
        unlink($lockFile);         // 删除锁文件
        echo "\n清理完成，锁文件已移除。\n";
    }
});


// --- 3. 尝试获取文件锁 ---
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "另一个同步进程正在运行，脚本退出。\n";
    exit(1);
}

// --- 4. 增强的日志和进度输出函数 ---
function write_log(string $message, bool $display = true): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logFile = $logDir . '/cron_sync_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);

    // 如果$display为true，则同时在命令行输出
    if ($display) {
        echo "[{$timestamp}] {$message}\n";
    }
}

// --- 5. 初始化 ---
require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\SearchMediaRequest;

$pdo = Database::getConnection();
if (!$pdo) {
    write_log("致命错误: 数据库连接失败。");
    exit(1);
}

$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) {
    write_log("致命错误: 阿里云VOD客户端创建失败。");
    exit(1);
}

// --- 6. 主逻辑 ---
write_log("===== 开始同步视频 (按 Ctrl+C 可安全退出) =====");
$scrollToken = null;
$pageSize = 100;
$totalSynced = 0;
$totalFailed = 0;
$page = 1;

do {
    try {
        write_log("正在获取第 " . $page++ . " 批数据...");
        
        $request = new SearchMediaRequest([
            'pageSize'    => $pageSize,
            'sortBy'      => 'CreationTime:Desc',
            'returnFields' => 'VideoId,Title,CoverURL,Duration,CreationTime'
        ]);
        
        if ($scrollToken) {
            $request->scrollToken = $scrollToken;
        }

        $response = $vodClient->searchMedia($request);
        
        $mediaList = $response->body->mediaList ?? [];
        $pageMediaCount = count($mediaList);

        if ($pageMediaCount > 0) {
            write_log("成功获取 {$pageMediaCount} 条数据，开始写入数据库...");
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO videos (video_id, title, cover_url, duration, creation_time)
                 VALUES (:video_id, :title, :cover_url, :duration, :creation_time)
                 ON DUPLICATE KEY UPDATE
                   title = VALUES(title), cover_url = VALUES(cover_url),
                   duration = VALUES(duration), creation_time = VALUES(creation_time)"
            );
            
            // 显示进度条
            $processedCount = 0;
            foreach ($mediaList as $media) {
                // ... (内部错误处理逻辑保持不变) ...
                $processedCount++;
                $percentage = round(($processedCount / $pageMediaCount) * 100);
                printf("  -> 处理中: [%-50s] %d%% (%d/%d)\r", str_repeat("=", $percentage / 2), $percentage, $processedCount, $pageMediaCount);

                try {
                    if (!isset($media->video) || !isset($media->video->videoId)) {
                        continue;
                    }
                    $video = $media->video;
                    $creationTime = !empty($video->creationTime) ? str_replace(['T', 'Z'], ' ', $video->creationTime) : null;
                    $stmt->execute([
                        ':video_id' => $video->videoId,
                        ':title' => $video->title,
                        ':cover_url' => $video->coverURL,
                        ':duration' => !empty($video->duration) && is_numeric($video->duration) ? (float)$video->duration : 0,
                        ':creation_time' => $creationTime
                    ]);
                } catch (Exception $e) {
                    $totalFailed++;
                    // 写入日志但不显示在主进度上
                    write_log("错误: 处理 videoId: {$video->videoId} 失败 - " . $e->getMessage(), false); 
                    continue;
                }
            }
            $pdo->commit();
            echo "\n"; // 进度条结束后换行
            $totalSynced += ($pageMediaCount - $totalFailed);
        } else {
            write_log("未获取到更多数据。");
        }
        
        $scrollToken = $response->body->scrollToken ?? null;
        if ($scrollToken) {
            write_log("等待1秒后获取下一批...");
            sleep(1); 
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        write_log("严重错误: 同步过程中断 - " . $e->getMessage());
        break; 
    }
} while ($scrollToken);

write_log("同步完成！成功处理: {$totalSynced} 条，失败: {$totalFailed} 条。");
write_log("===== 同步任务结束 =====");

// 脚本正常结束，清理函数 register_shutdown_function 依然会自动执行
exit(0);
