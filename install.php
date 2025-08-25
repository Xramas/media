<?php
// 文件: install.php (v6 - 终极完整版)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$configFile = __DIR__ . '/config.credentials.php';
$lockFile = __DIR__ . '/install.lock';
$errors = [];
$successMessage = '';

if (file_exists($lockFile)) {
    die("安装程序已被锁定。请先删除 'install.lock' 和 'config.credentials.php' 文件，并手动删除旧的 'videos' 数据表，然后重试。");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $aliyun_ak_id = $_POST['aliyun_ak_id'] ?? '';
    $aliyun_ak_secret = $_POST['aliyun_ak_secret'] ?? '';
    $aliyun_region_id = $_POST['aliyun_region_id'] ?? 'cn-shanghai';
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) { $errors[] = "数据库连接失败: " . $e->getMessage(); }

    if (empty($errors)) {
        require_once __DIR__ . '/vendor/autoload.php';
        try {
            $config = new \Darabonba\OpenApi\Models\Config(["accessKeyId" => $aliyun_ak_id, "accessKeySecret" => $aliyun_ak_secret]);
            $config->endpoint = "vod." . $aliyun_region_id . ".aliyuncs.com";
            $vodClient = new \AlibabaCloud\SDK\Vod\V20170321\Vod($config);
            $vodClient->getVodTemplate(new \AlibabaCloud\SDK\Vod\V20170321\Models\GetVodTemplateRequest(['vodTemplateId'=>'VOD_NO_EXIST_TEMPLATE_ID']));
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Forbidden') !== false) {
                 $errors[] = "阿里云VOD连接失败: AccessKeyId或AccessKeySecret错误。";
            }
        }
    }

    if (empty($errors)) {
        try {
            // !! 关键改动：终极、完整、排序、带注释的表结构 !!
            $sql = "CREATE TABLE IF NOT EXISTS `videos` (
              `AppId` VARCHAR(32) DEFAULT NULL COMMENT '应用ID',
              `AuditStatus` VARCHAR(50) DEFAULT NULL COMMENT '审核状态',
              `CoverURL` VARCHAR(1024) DEFAULT NULL COMMENT '封面地址',
              `CreationTime` DATETIME DEFAULT NULL COMMENT '创建时间 (UTC)',
              `DownloadSwitch` VARCHAR(10) DEFAULT NULL COMMENT '下载开关',
              `Duration` FLOAT DEFAULT NULL COMMENT '时长（秒）',
              `is_details_fetched` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已获取详情',
              `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '行更新时间',
              `ModificationTime` DATETIME DEFAULT NULL COMMENT '修改时间 (UTC)',
              `PreprocessStatus` VARCHAR(50) DEFAULT NULL COMMENT '预处理状态',
              `RegionId` VARCHAR(50) DEFAULT NULL COMMENT '区域ID',
              `Size` BIGINT DEFAULT NULL COMMENT '文件大小（字节）',
              `Snapshots` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '截图地址列表 (JSON)',
              `Status` VARCHAR(50) DEFAULT NULL COMMENT '视频状态',
              `StorageClass` VARCHAR(50) DEFAULT NULL COMMENT '存储类型',
              `StorageLocation` VARCHAR(255) DEFAULT NULL COMMENT '存储地址',
              `TemplateGroupId` VARCHAR(32) DEFAULT NULL COMMENT '转码模板组ID',
              `Title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '视频标题',
              `VideoId` VARCHAR(32) NOT NULL COMMENT '视频ID',
              PRIMARY KEY (`VideoId`),
              INDEX `idx_CreationTime` (`CreationTime`),
              INDEX `idx_is_details_fetched` (`is_details_fetched`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频信息全量表';";
            $pdo->exec($sql);
        } catch (PDOException $e) { $errors[] = "创建数据表失败: " . $e->getMessage(); }
    }

    if (empty($errors)) {
        $configContent = "<?php\n\nreturn [\n" .
            "    'db_host' => '" . addslashes($db_host) . "',\n" .
            "    'db_name' => '" . addslashes($db_name) . "',\n" .
            "    'db_user' => '" . addslashes($db_user) . "',\n" .
            "    'db_pass' => '" . addslashes($db_pass) . "',\n" .
            "    'aliyun_ak_id' => '" . addslashes($aliyun_ak_id) . "',\n" .
            "    'aliyun_ak_secret' => '" . addslashes($aliyun_ak_secret) . "',\n" .
            "    'aliyun_region_id' => '" . addslashes($aliyun_region_id) . "',\n" .
            "];\n";
        if (file_put_contents($configFile, $configContent)) {
            touch($lockFile);
            $successMessage = "安装成功！";
        } else { $errors[] = "写入配置文件失败。"; }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><title>网站安装程序</title><style>body{font-family:sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.installer{background:#fff;padding:40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);width:100%;max-width:500px}h1{text-align:center;color:#333}.form-group{margin-bottom:20px}label{display:block;font-weight:700;margin-bottom:5px;color:#555}input[type=text],input[type=password]{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}.btn{display:block;width:100%;background:#007bff;color:#fff;padding:12px;border:none;border-radius:4px;font-size:16px;cursor:pointer}.btn:hover{background:#0056b3}.error{background:#f8d7da;color:#721c24;padding:10px;border:1px solid #f5c6cb;border-radius:4px;margin-bottom:20px}.success{background:#d4edda;color:#155724;padding:15px;border:1px solid #c3e6cb;border-radius:4px;text-align:center}.success a{font-weight:700;color:#0c5460}</style></head><body><div class="installer"><h1>网站安装程序 (v6 - 终极版)</h1><?php if(!empty($successMessage)):?><div class="success"><p><?php echo $successMessage;?></p><p><a href="index.php">访问您的网站首页</a></p></div><?php else:?><?php if(!empty($errors)):?><div class="error"><?php foreach($errors as $error)echo "<p>$error</p>";?></div><?php endif;?><form method="POST"><h3>数据库配置</h3><div class="form-group"><label for="db_host">数据库地址</label><input type="text" id="db_host" name="db_host" value="localhost" required></div><div class="form-group"><label for="db_name">数据库名称</label><input type="text" id="db_name" name="db_name" required></div><div class="form-group"><label for="db_user">数据库用户</label><input type="text" id="db_user" name="db_user" required></div><div class="form-group"><label for="db_pass">数据库密码</label><input type="password" id="db_pass" name="db_pass"></div><h3>阿里云VOD配置</h3><div class="form-group"><label for="aliyun_ak_id">AccessKey ID</label><input type="text" id="aliyun_ak_id" name="aliyun_ak_id" required></div><div class="form-group"><label for="aliyun_ak_secret">AccessKey Secret</label><input type="password" id="aliyun_ak_secret" name="aliyun_ak_secret" required></div><div class="form-group"><label for="aliyun_region_id">Region ID</label><input type="text" id="aliyun_region_id" name="aliyun_region_id" value="cn-shanghai" required></div><button type="submit" class="btn">立即安装</button></form><?php endif;?></div></body></html>
