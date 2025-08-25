<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/_includes/header.php';
// 更新引用，指向新的、统一的服务文件
require_once __DIR__ . '/functions/AliyunVodService.php'; 

// 获取视频ID，如果为空则退出
$videoId = $_GET['videoId'] ?? '';
if (empty($videoId)) { 
    die("错误：未提供视频ID。"); 
}

// 通过重构后的函数获取视频信息
$playInfoBody = getPlayInfo($videoId);
$playURL = getMezzanineInfo($videoId);

// 初始化默认的标题和描述
$videoTitle = "视频加载失败";
$videoDescription = "暂无描述。";

// 修正了变量名，现在可以正确地从API响应中获取信息
if ($playInfoBody && isset($playInfoBody->videoBase)) {
    $videoTitle = htmlspecialchars($playInfoBody->videoBase->title);
    if (!empty($playInfoBody->videoBase->description)) {
        $videoDescription = htmlspecialchars($playInfoBody->videoBase->description);
    }
}

// 设置页面标题
$pageTitle = $videoTitle . " - 我的视频网站";
?>
<title><?php echo htmlspecialchars($pageTitle); ?></title>

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
