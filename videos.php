<?php
require_once __DIR__ . '/config.php'; // 包含了新的DB和VOD逻辑
require_once __DIR__ . '/_includes/header.php';

// --- 辅助函数 ---

/**
 * 将秒数格式化为 MM:SS 格式的字符串
 * @param float $seconds
 * @return string
 */
function formatDuration(float $seconds): string {
    $m = floor($seconds / 60);
    $s = floor($seconds % 60);
    return sprintf('%02d:%02d', $m, $s);
}

/**
 * 渲染分页HTML的函数
 * @param int $currentPage
 * @param int $totalPages
 * @return void
 */
function renderPagination(int $currentPage, int $totalPages): void {
    if ($totalPages <= 1) { return; }
    echo '<nav><ul class="pagination">';
    if ($currentPage > 1) { echo '<li><a href="?page=' . ($currentPage - 1) . '">上一页</a></li>'; }
    $window = 2;
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $window && $i <= $currentPage + $window)) {
            if ($i == $currentPage) { echo '<li><span class="current">' . $i . '</span></li>'; } 
            else { echo '<li><a href="?page=' . $i . '">' . $i . '</a></li>'; }
        } elseif ($i == $currentPage - $window - 1 || $i == $currentPage + $window + 1) {
            echo '<li><span class="dots">...</span></li>';
        }
    }
    if ($currentPage < $totalPages) { echo '<li><a href="?page=' . ($currentPage + 1) . '">下一页</a></li>'; }
    echo '</ul></nav>';
}

// --- 主要逻辑 ---

$pdo = Database::getConnection(); // 从config获取数据库连接
if (!$pdo) {
    // 如果无法连接数据库（通常是未安装），显示提示信息
    die("网站配置不正确，无法连接到数据库。请先访问 <a href='install.php'>install.php</a> 完成安装。");
}

$pageSize = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) { $currentPage = 1; }

// 从数据库获取总数
$totalVideos = $pdo->query("SELECT count(*) FROM videos")->fetchColumn();
$totalPages = ceil($totalVideos / $pageSize);
$offset = ($currentPage - 1) * $pageSize;

// 从数据库获取当前页的数据 (按创建时间倒序)
$stmt = $pdo->prepare("SELECT * FROM videos ORDER BY creation_time DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$videoList = $stmt->fetchAll(PDO::FETCH_OBJ); // 以对象形式获取，方便模板渲染

$errorMessage = '';
?>
<title>视频列表 (第 <?php echo $currentPage; ?> 页) - 我的视频网站</title>

<div class="container">
    <h2>视频列表 <small style="color:#777;font-size:0.7em;">(共 <?php echo $totalVideos; ?> 个)</small></h2>
    
    <div class="video-grid">
        <?php if (!empty($videoList)): ?>
            <?php foreach ($videoList as $video): ?>
                <?php
                    // 注意：现在字段名来自数据库 (video_id, cover_url)
                    $title = htmlspecialchars($video->title);
                    $coverUrl = !empty($video->cover_url) ? htmlspecialchars($video->cover_url) : '';
                    $duration = formatDuration($video->duration);
                    $videoId = $video->video_id;
                ?>
                <div class='video-card loading'>
                    <a href='play.php?videoId=<?php echo $videoId; ?>'>
                        <div class='video-thumbnail'>
                            <?php if (!empty($coverUrl)): ?>
                                <img src='<?php echo $coverUrl; ?>' alt='<?php echo $title; ?>'>
                            <?php endif; ?>
                            <span class='video-duration'><?php echo $duration; ?></span>
                        </div>
                        <div class='video-info'>
                            <h3><?php echo $title; ?></h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>暂无视频，或后台数据正在同步中...</p>
        <?php endif; ?>
    </div>

    <?php renderPagination($currentPage, $totalPages); ?>
</div>

<?php require_once __DIR__ . '/_includes/footer.php'; ?>
