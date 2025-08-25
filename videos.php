<?php
// 文件: videos.php (v4)

require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/_includes/header.php';

// ... (辅助函数与上一版相同) ...
function formatDuration_v4(float $seconds): string { $m = floor($seconds / 60); $s = floor($seconds % 60); return sprintf('%02d:%02d', $m, $s); }
function renderPagination_v4(int $currentPage, int $totalPages): void { if ($totalPages <= 1) { return; } echo '<nav><ul class="pagination">'; if ($currentPage > 1) { echo '<li><a href="?page=' . ($currentPage - 1) . '">上一页</a></li>'; } $window = 2; for ($i = 1; $i <= $totalPages; $i++) { if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $window && $i <= $currentPage + $window)) { echo '<li><a href="?page=' . $i . '" class="' . ($i == $currentPage ? 'current' : '') . '">' . $i . '</a></li>'; } elseif ($i == $currentPage - $window - 1 || $i == $currentPage + $window + 1) { echo '<li><span class="dots">...</span></li>'; } } if ($currentPage < $totalPages) { echo '<li><a href="?page=' . ($currentPage + 1) . '">下一页</a></li>'; } echo '</ul></nav>'; }


$pdo = Database::getConnection();

if (!$pdo) {
    echo "<div class='container'><p>错误：无法连接到数据库。</p></div>";
} else {
    $pageSize = 20;
    $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $totalVideos = $pdo->query("SELECT count(*) FROM videos WHERE is_details_fetched = 1")->fetchColumn();
    $totalPages = ceil($totalVideos / $pageSize);
    $offset = ($currentPage - 1) * $pageSize;
    
    // !! 关键改动：使用大小写一致的字段名进行查询 !!
    $stmt = $pdo->prepare(
        "SELECT VideoId, Title, CoverURL, Duration 
         FROM videos 
         WHERE is_details_fetched = 1 
         ORDER BY CreationTime DESC 
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $videoList = $stmt->fetchAll();
?>
<title>视频列表 - 我的视频网站</title>

<div class="container">
    <h2>视频列表 <small>(共 <?php echo $totalVideos; ?> 个)</small></h2>
    
    <div class="video-grid">
        <?php if (!empty($videoList)): ?>
            <?php foreach ($videoList as $video): ?>
                <?php
                    // !! 关键改动：使用大小写一致的数组键名 !!
                    $title = htmlspecialchars($video['Title'] ?? '无标题');
                    $coverUrl = !empty($video['CoverURL']) ? htmlspecialchars($video['CoverURL']) : '';
                    $duration = formatDuration_v4($video['Duration'] ?? 0);
                    $videoId = $video['VideoId'];
                ?>
                <div class='video-card'>
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
            <p>暂无视频，或后台正在更新视频详情中...</p>
        <?php endif; ?>
    </div>

    <?php renderPagination_v4($currentPage, $totalPages); ?>
</div>
<?php
}
require_once __DIR__ . '/_includes/footer.php';
?>
