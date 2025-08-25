<?php
// 文件: play.php (重构后)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/_includes/header.php';

// 引入需要用到的SDK模型类
use AlibabaCloud\SDK\Vod\V20170321\Models\GetPlayInfoRequest;
use AlibabaCloud\SDK\Vod\V20170321\Models\GetMezzanineInfoRequest;

// 1. 初始化
$videoId = $_GET['videoId'] ?? '';
$playInfoBody = null;
$playURL = null;
$videoTitle = "视频加载失败";
$videoDescription = "暂无描述。";

if (empty($videoId)) {
    die("错误：未提供视频ID。"); 
}

// 2. 获取VOD客户端实例
$vodClient = AliyunVodClientFactory::createClient();

if ($vodClient) {
    // 3. 直接调用SDK获取视频信息
    try {
        $request = new GetPlayInfoRequest(['videoId' => $videoId]);
        $response = $vodClient->getPlayInfo($request);
        $playInfoBody = $response->body;
    } catch (Exception $e) {
        error_log("Aliyun API Error (GetPlayInfo): " . $e->getMessage());
    }

    // 4. 直接调用SDK获取播放地址
    try {
        $request = new GetMezzanineInfoRequest(['videoId' => $videoId]);
        $response = $vodClient->getMezzanineInfo($request);
        if (isset($response->body->mezzanine->fileURL)) {
            $playURL = $response->body->mezzanine->fileURL;
        }
    } catch (Exception $e) {
        error_log("Aliyun API Error (GetMezzanineInfo): " . $e->getMessage());
    }
}

// 5. 处理获取到的数据
if ($playInfoBody && isset($playInfoBody->videoBase)) {
    $videoTitle = htmlspecialchars($playInfoBody->videoBase->title);
    if (!empty($playInfoBody->videoBase->description)) {
        $videoDescription = htmlspecialchars($playInfoBody->videoBase->description);
    }
}

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
