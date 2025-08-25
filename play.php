<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/_includes/header.php'; // 使用页头
require_once __DIR__ . '/functions/GetPlayInfo.php';
require_once __DIR__ . '/functions/GetMezzanineInfo.php';

// ... (所有PHP逻辑保持不变) ...
$videoId = $_GET['videoId'] ?? '';
if (empty($videoId)) { die("错误：未提供视频ID。"); }
$playInfoBody = getPlayInfo($videoId);
$playURL = getMezzanineInfo($videoId);
$videoTitle = "视频加载失败";
$videoDescription = "暂无描述。";
if ($playInfoBody) {
    $videoTitle = htmlspecialchars($playInfo_body->videoBase->title);
    if (!empty($playInfo_body->videoBase->description)) {
        $videoDescription = htmlspecialchars($playInfo_body->videoBase->description);
    }
}
$pageTitle = $videoTitle . " - 我的视频网站";
?>
<title><?php echo $pageTitle; ?></title>

<div class="container">
    <div class="player-container">
        <?php if (!empty($playURL)): ?>
            
            <div class="video-wrapper"> 
                <video class="video-player" controls autoplay>
                    <source src="<?php echo htmlspecialchars($playURL); ?>" type="video/mp4">
                    您的浏览器不支持 video 标签。
                </video>
            </div>
            <div class="video-meta">
                <h2><?php echo $videoTitle; ?></h2>
                <p><?php echo nl2br($videoDescription); ?></p>
            </div>
        <?php else: ?>
            <h2>视频加载失败</h2>
            <p style="color: red;">无法获取视频源文件地址...</p>
        <?php endif; ?>
        <a href="videos.php" class="back-link">返回视频列表</a>
    </div>
</div>

<?php require_once __DIR__ . '/_includes/footer.php'; ?>
