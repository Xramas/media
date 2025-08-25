<?php
// 文件: clear_videos.php (一键清空视频条目脚本)

// --- 1. 安全检查：确保脚本在命令行环境下运行 ---
if (php_sapi_name() !== 'cli') {
    die("错误：此脚本只能在命令行 (CLI) 环境下运行。");
}

// --- 2. 安全检查：获取命令行参数 ---
$options = getopt("", ["confirm"]);
if (!isset($options['confirm'])) {
    echo "============================================================\n";
    echo "  安全警告：这是一个危险操作，将会清空数据库中的所有视频条目！\n";
    echo "============================================================\n";
    echo "请使用 --confirm 参数来确认您的操作。\n";
    echo "正确用法: php clear_videos.php --confirm\n";
    exit(1);
}

// --- 3. 初始化 ---
require_once __DIR__ . '/config.php';

echo "===== 开始清空视频条目 =====\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "致命错误: 数据库连接失败，请检查 config.credentials.php 中的配置。\n";
    exit(1);
}
echo "数据库连接成功。\n";

// --- 4. 执行清空操作 ---
try {
    echo "正在获取当前视频数量...\n";
    $count = $pdo->query("SELECT count(*) FROM videos")->fetchColumn();
    
    if ($count == 0) {
        echo "数据库中的 'videos' 表已经为空，无需操作。\n";
    } else {
        echo "发现 " . $count . " 条视频记录，准备清空...\n";
        
        // TRUNCATE TABLE 是最高效的清空表操作
        $pdo->exec("TRUNCATE TABLE videos");
        
        echo "成功！已清空 " . $count . " 条视频记录。\n";
    }

} catch (Exception $e) {
    echo "\n严重错误: 操作失败！\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    exit(1);
}

echo "===== 清空任务结束 =====\n";
exit(0);
