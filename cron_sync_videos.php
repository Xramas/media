<?php
// 文件: cron_update_details.php (v6)
set_time_limit(0);

require_once __DIR__ . '/config.php';
use AlibabaCloud\SDK\Vod\V20170321\Models\GetVideoInfoRequest;

$pdo = Database::getConnection();
if (!$pdo) { echo "致命错误: 数据库连接失败。\n"; exit(1); }
$vodClient = AliyunVodClientFactory::createClient();
if (!$vodClient) { echo "致命错误: VOD客户端创建失败。\n"; exit(1); }

echo "===== 开始更新视频详细信息 (v6) =====\n";

try {
    $stmt = $pdo->query("SELECT VideoId FROM videos WHERE is_details_fetched = 0 ORDER BY CreationTime DESC");
    $videosToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalToUpdate = count($videosToUpdate);

    if ($totalToUpdate === 0) { echo "没有需要更新详情的视频。\n"; exit(0); }
    echo "发现 " . $totalToUpdate . " 个视频需要更新详情。\n";

    // !! 关键改动：准备包含所有字段的、按字母排序的更新语句 !!
    $updateStmt = $pdo->prepare(
        "UPDATE videos SET
           AppId = :AppId,
           AuditStatus = :AuditStatus,
           CoverURL = :CoverURL,
           DownloadSwitch = :DownloadSwitch,
           Duration = :Duration,
           ModificationTime = :ModificationTime,
           PreprocessStatus = :PreprocessStatus,
           RegionId = :RegionId,
           Size = :Size,
           Snapshots = :Snapshots,
           Status = :Status,
           StorageClass = :StorageClass,
           StorageLocation = :StorageLocation,
           TemplateGroupId = :TemplateGroupId,
           Title = :Title,
           is_details_fetched = 1
         WHERE VideoId = :VideoId"
    );

    $updatedCount = 0;
    foreach ($videosToUpdate as $videoId) {
        printf("  -> 正在更新 %s... (%d/%d)\r", $videoId, ++$updatedCount, $totalToUpdate);
        
        try {
            $request = new GetVideoInfoRequest(['videoId' => $videoId]);
            $response = $vodClient->getVideoInfo($request);
            $video = $response->body->video;

            if ($video) {
                $modificationTime = !empty($video->modificationTime) ? str_replace(['T', 'Z'], ' ', $video->modificationTime) : null;
                $snapshotsJson = isset($video->snapshots->snapshot) ? json_encode($video->snapshots->snapshot) : null;
                
                // !! 关键改动：绑定所有参数 !!
                $updateStmt->execute([
                    ':AppId' => $video->appId,
                    ':AuditStatus' => $video->auditStatus,
                    ':CoverURL' => $video->coverURL,
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
            }
        } catch (Exception $e) {
            echo "\n警告：获取 VideoId {$videoId} 详情失败: " . $e->getMessage() . "\n";
        }
        sleep(1);
    }
    echo "\n";
    echo "更新任务完成。\n";

} catch (Exception $e) { echo "\n严重错误: " . $e->getMessage() . "\n"; }
echo "===== 更新任务结束 =====\n";
