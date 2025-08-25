<?php
// 文件: cron_update_details.php (v7 - 数据解析最终修复版)

set_time_limit(0);

// --- 1. 注册必定执行的清理函数 ---
$lockFile = __DIR__ . '/update.lock';
$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle) { echo "错误：无法创建锁文件。\n"; exit(1); }
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    if ($lockHandle) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        @unlink($lockFile);
        echo "\n清理完成，更新任务锁已移除。\n";
    }
});

// --- 2. 尝试获取文件锁 ---
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "另一个更新任务正在运行，脚本退出。\n";
    exit(1);
}

// --- 3. 初始化 ---
require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\GetVideoInfoRequest;

$pdo = Database::getConnection();
if (!$pdo) { echo "致命错误: 数据库连接失败。\n"; exit(1); }
$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) { echo "致命错误: VOD客户端创建失败。\n"; exit(1); }

// --- 4. 主逻辑 ---
echo "===== 开始更新视频详细信息 (v7) (按 Ctrl+C 可安全退出) =====\n";

try {
    $stmt = $pdo->query("SELECT VideoId FROM videos WHERE is_details_fetched = 0 ORDER BY CreationTime DESC");
    $videosToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalToUpdate = count($videosToUpdate);

    if ($totalToUpdate === 0) { echo "没有需要更新详情的视频。\n"; exit(0); }
    echo "发现 " . $totalToUpdate . " 个视频需要更新详情。\n";

    $updateStmt = $pdo->prepare(
        "UPDATE videos SET
           AppId = :AppId, AuditStatus = :AuditStatus, CoverURL = :CoverURL, CreationTime = :CreationTime, 
           DownloadSwitch = :DownloadSwitch, Duration = :Duration, ModificationTime = :ModificationTime, 
           PreprocessStatus = :PreprocessStatus, RegionId = :RegionId, Size = :Size, Snapshots = :Snapshots, 
           Status = :Status, StorageClass = :StorageClass, StorageLocation = :StorageLocation, 
           TemplateGroupId = :TemplateGroupId, Title = :Title,
           is_details_fetched = 1
         WHERE VideoId = :VideoId"
    );

    $updatedCount = 0;
    foreach ($videosToUpdate as $videoId) {
        printf("  -> 正在更新 %s... (%d/%d)\r", $videoId, ++$updatedCount, $totalToUpdate);
        
        try {
            $request = new GetVideoInfoRequest(['videoId' => $videoId]);
            $response = $vodClient->getVideoInfo($request);
            
            // !! 关键修复：使用全小写的 'video' 来访问嵌套的对象 !!
            $video = $response->body->video ?? null;

            if ($video) {
                $creationTime = !empty($video->creationTime) ? str_replace(['T', 'Z'], ' ', $video->creationTime) : null;
                $modificationTime = !empty($video->modificationTime) ? str_replace(['T', 'Z'], ' ', $video->modificationTime) : null;
                $snapshotsJson = isset($video->snapshots->snapshot) ? json_encode($video->snapshots->snapshot) : null;
                
                $updateStmt->execute([
                    ':AppId' => $video->appId,
                    ':AuditStatus' => $video->auditStatus,
                    ':CoverURL' => $video->coverURL,
                    ':CreationTime' => $creationTime,
                    ':DownloadSwitch' => $video->downloadSwitch,
                    ':Duration' => $video->duration,
                    ':ModificationTime' => $modificationTime,
                    ':PreprocessStatus' => $video->preprocessStatus,
                    ':RegionId' => $video->regionId,
                    ':Size' => $video->size,
                    ':Snapshots' => $snapshotsJson,
                    ':Status' => $video->status,
                    ':StorageClass' => $video->storageClass,
                    ':StorageLocation' => $video->storageLocation,
                    ':TemplateGroupId' => $video->templateGroupId,
                    ':Title' => $video->title,
                    ':VideoId' => $videoId
                ]);
            } else {
                 echo "\n警告：获取 VideoId {$videoId} 详情失败，API未返回有效的 'video' 对象结构。\n";
            }
        } catch (Exception $e) {
            echo "\n警告：处理 VideoId {$videoId} 时发生API异常: " . $e->getMessage() . "\n";
        }
        sleep(1);
    }
    echo "\n";
    echo "更新任务完成。\n";

} catch (Exception $e) { echo "\n严重错误: " . $e->getMessage() . "\n"; }
echo "===== 更新任务结束 =====\n";
exit(0);
