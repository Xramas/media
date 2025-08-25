<?php
// 文件: videos.php (修复后)

// !! 关键修复：将引用从已删除的 init.php 改为新的 config.php !!
require_once __DIR__ . '/config.php'; 

// 在 config.php 之后加载页头
require_once __DIR__ . '/_includes/header.php';

// --- 辅助函数 (保持不变) ---
function formatDuration(float $seconds): string {
    $m = floor($seconds / 60);
    $s = floor($seconds % 60);
    return sprintf('%02d:%02d', $m, $s);
}
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
// 从 config.php 中获取数据库连接
$pdo = Database::getConnection();

// 如果数据库连接失败，页面会显示空白，但错误会记录在 logs/php_errors.log 中
if (!$pdo) {
    // 您可以选择在这里显示一个友好的错误信息
    echo "<div class='container'><p>错误：无法连接到数据库，请检查配置或联系管理员。</p></div>";
} else {
    $pageSize = 20;
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($currentPage < 1) { $currentPage = 1; }

    $totalVideos = $pdo->query("SELECT count(*) FROM videos")->fetchColumn();
    $totalPages = ceil($totalVideos / $pageSize);
    $offset = ($currentPage - 1) * $pageSize;

    $stmt = $pdo->prepare("SELECT * FROM videos ORDER BY creation_time DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $videoList = $stmt->fetchAll(PDO::FETCH_OBJ);

?>
<title>视频列表 (第 <?php echo $currentPage; ?> 页) - 我的视频网站</title>

<div class="container">
    <h2>视频列表 <small style="color:#777;font-size:0.7em;">(共 <?php echo $totalVideos; ?> 个)</small></h2>
    
    <div class="video-grid">
        <?php if (!empty($videoList)): ?>
            <?php foreach ($videoList as $video): ?>
                <?php
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
<?php
} // 结束 else 数据库连接成功

require_once __DIR__ . '/_includes/footer.php';
?>
