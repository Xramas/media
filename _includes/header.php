<?php
// 获取当前页面的文件名，用于导航栏高亮
$currentPageFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="site-header">
        <h1 class="logo"><a href="index.php">视频网站</a></h1>
        <nav class="main-nav">
            <ul>
                <li><a href="index.php" class="<?php echo ($currentPageFile == 'index.php') ? 'active' : ''; ?>">首页</a></li>
                <li><a href="videos.php" class="<?php echo ($currentPageFile == 'videos.php' || $currentPageFile == 'play.php') ? 'active' : ''; ?>">视频</a></li>
                <li><a href="about.php" class="<?php echo ($currentPageFile == 'about.php') ? 'active' : ''; ?>">关于</a></li>
            </ul>
        </nav>
    </header>

    <main>
