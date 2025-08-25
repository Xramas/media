<?php
// 文件: play.php (v4 - 源文件播放版)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/_includes/header.php';

// 引入需要用到的SDK模型类
use AlibabaCloud\SDK\Vod\V20170321\Models\GetMezzanineInfoRequest; // 关键改动：更换引用的类

// --- 1. 初始化 ---
$videoId = $_GET['videoId'] ?? '';

// 定义一个统一的错误页面函数
function render_error_page($title, $message) {
    echo "<title>" . htmlspecialchars($title) . "</title>";
    echo "<div class='container'><div class='page-content'><h1>" . htmlspecialchars($title) . "</h1><p>" . htmlspecialchars($message) . "</p><a href='videos.php' class='back-link'>返回列表</a></div></div>";
    require_once __DIR__ . '/_includes/footer.php';
    exit;
}

if (empty($videoId)) {
    render_error_page("错误", "未提供有效的视频ID。");
}

$pdo = Database::getConnection();
$videoData = null;
$playURL = null;

// --- 2. 从本地数据库获取视频静态信息 ---
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE VideoId = :VideoId AND is_details_fetched = 1");
    $stmt->execute([':VideoId' => $videoId]);
    $videoData = $stmt->fetch();
}

if (!$videoData) {
    render_error_page("视频不存在", "无法在数据库中找到该视频，或该视频详情尚未同步。");
}

// --- 3. 调用 GetMezzanineInfo API 获取源文件播放地址 ---
$vodClient = AliyunVodClientFactory::createClient();
if ($vodClient) {
    try {
        // 关键改动：使用 GetMezzanineInfoRequest
        $request = new GetMezzanineInfoRequest(['videoId' => $videoId]);
        $response = $vodClient->getMezzanineInfo($request);
        
        // 关键改动：从 mezzanine 对象中获取 fileURL
        if (!empty($response->body->mezzanine->fileURL)) {
            $playURL = $response->body->mezzanine->fileURL;
        }
    } catch (Exception $e) {
        error_log("Aliyun API Error (GetMezzanineInfo for $videoId): " . $e->getMessage());
    }
}

// --- 4. 准备页面变量 ---
$videoTitle = htmlspecialchars($videoData['Title'] ?? '无标题');
// 假设未来数据库中可能有Description字段
$videoDescription = !empty($videoData['Description']) ? htmlspecialchars($videoData['Description']) : '暂无描述。';
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
            <p style="color: red;">无法从阿里云获取视频源文件地址。这可能是因为源文件已被删除或相关权限问题。</p>
        <?php endif; ?>
        <a href="videos.php" class="back-link">返回视频列表</a>
    </div>
</div>

<?php require_once __DIR__ . '/_includes/footer.php'; ?>
