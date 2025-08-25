<?php
// 文件: error.php

// 为了确保页面能正确加载页头和页脚，我们先引入它们
require_once __DIR__ . '/_includes/header.php'; 
?>

<title>出现问题了 - 我的视频网站</title>

<style>
    .error-container {
        text-align: center;
        padding: 60px 20px;
        background-color: #fff;
        border-radius: 8px;
        margin-top: 40px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .error-container h1 {
        font-size: 2.5em;
        color: #dc3545; /* 红色，表示错误 */
        margin-bottom: 20px;
    }
    .error-container p {
        font-size: 1.2em;
        color: #6c757d; /* 灰色文字 */
        max-width: 600px;
        margin: 0 auto 30px auto;
    }
    .error-container .cta-button {
        display: inline-block;
        padding: 12px 25px;
        background-color: var(--primary-color);
        color: #fff;
        border-radius: 5px;
        font-size: 1.1em;
        font-weight: bold;
        transition: background-color 0.2s;
    }
     .error-container .cta-button:hover {
        background-color: var(--primary-hover);
        color: #fff;
    }
</style>

<div class="container">
    <div class="error-container">
        <h1>:( 网站暂时无法访问</h1>
        <p>抱歉，我们的服务器可能出现了一些临时问题，或者网站正在维护中。请稍后再试。</p>
        <p>如果问题持续存在，请联系网站管理员。</p>
        <a href="index.php" class="cta-button">返回首页</a>
    </div>
</div>

<?php require_once __DIR__ . '/_includes/footer.php'; ?>
