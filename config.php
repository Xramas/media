<?php
// 文件: config.php (v2 - 支持超时版)

// --- 1. 全局错误处理 ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/php_errors.log');

// --- 2. 加载 Composer 自动加载器 ---
require_once __DIR__ . '/vendor/autoload.php';

// --- 3. 核心类定义 ---
use AlibabaCloud\SDK\Vod\V20170321\Vod;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;

class Database {
    private static $pdo;
    private static $config;
    public static function getConnection(): ?PDO {
        if (self::$pdo === null) {
            if (self::$config === null) {
                self::$config = self::loadConfig();
            }
            if (!self::$config) {
                error_log("数据库错误: config.credentials.php 文件未找到。");
                return null; 
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', self::$config['db_host'], self::$config['db_name']);
            try {
                self::$pdo = new PDO($dsn, self::$config['db_user'], self::$config['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                error_log("数据库连接失败: " . $e->getMessage());
                return null;
            }
        }
        return self::$pdo;
    }
    private static function loadConfig(): ?array {
        $configFile = __DIR__ . '/config.credentials.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        return null;
    }
}

class AliyunVodClientFactory {
    private static $client;
    private static $config;
    public static function createClient(): ?Vod {
        if (self::$client === null) {
             if (self::$config === null) {
                self::$config = self::loadConfig();
            }
            if (!self::$config || empty(self::$config['aliyun_ak_id'])) {
                error_log("阿里云VOD错误: 配置未找到或不完整。");
                return null;
            }
            try {
                $openApiConfig = new OpenApiConfig([
                    "accessKeyId" => self::$config['aliyun_ak_id'],
                    "accessKeySecret" => self::$config['aliyun_ak_secret'],
                ]);
                $openApiConfig->endpoint = "vod." . self::$config['aliyun_region_id'] . ".aliyuncs.com";

                // !! 关键改动：为底层的 Guzzle 客户端设置超时 !!
                // 这可以防止任何单次API调用无限期地卡住脚本
                $httpClientOptions = [
                    'timeout' => 15.0, // 总超时时间（秒）
                    'connect_timeout' => 5.0, // 连接超时时间（秒）
                ];
                
                self::$client = new Vod($openApiConfig, $httpClientOptions);

            } catch (Exception $e) {
                error_log("创建VOD客户端失败: " . $e->getMessage());
                return null;
            }
        }
        return self::$client;
    }
    private static function loadConfig(): ?array {
        $configFile = __DIR__ . '/config.credentials.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        return null;
    }
}
