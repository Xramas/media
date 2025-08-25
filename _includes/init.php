<?php
// 文件: _includes/init.php

// 开启错误报告，但在生产环境中建议记录到日志而非显示
error_reporting(E_ALL);
ini_set('display_errors', 0); // 生产环境设为 0
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // 确保 ../logs 目录存在且可写

// 引入核心配置文件
require_once __DIR__ . '/../config.php';

// 尝试获取数据库连接
$pdo = Database::getConnection();

// 如果连接失败，则重定向到友好的错误页面
if (!$pdo) {
    // 使用header重定向。确保此代码前没有任何输出。
    header('Location: error.php');
    exit; // 重定向后必须退出脚本
}

// 此文件执行完毕后，$pdo 变量将对引入它的文件可用
