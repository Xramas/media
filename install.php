<?php
// --- 安装程序 ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$configFile = __DIR__ . '/config.credentials.php';
$lockFile = __DIR__ . '/install.lock';
$errors = [];
$successMessage = '';

// 如果已安装（存在lock文件），则退出
if (file_exists($lockFile)) {
    die("安装程序已被锁定。如果需要重新安装，请先删除服务器上的 'install.lock' 文件。");
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. 获取表单数据
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $aliyun_ak_id = $_POST['aliyun_ak_id'] ?? '';
    $aliyun_ak_secret = $_POST['aliyun_ak_secret'] ?? '';
    $aliyun_region_id = $_POST['aliyun_region_id'] ?? 'cn-shanghai';
    
    // 2. 验证数据库连接
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $errors[] = "数据库连接失败: " . $e->getMessage();
    }

    // 3. 验证阿里云连接 (如果数据库连接成功)
    if (empty($errors)) {
        require_once __DIR__ . '/vendor/autoload.php';
        try {
            $config = new \Darabonba\OpenApi\Models\Config([
                "accessKeyId" => $aliyun_ak_id,
                "accessKeySecret" => $aliyun_ak_secret,
            ]);
            $config->endpoint = "vod." . $aliyun_region_id . ".aliyuncs.com";
            $vodClient = new \AlibabaCloud\SDK\Vod\V20170321\Vod($config);
            // 尝试一个无害的API调用来验证密钥
            $vodClient->getVideoList(new \AlibabaCloud\SDK\Vod\V20170321\Models\GetVideoListRequest(['pageSize' => 1]));
        } catch (Exception $e) {
            $errors[] = "阿里云VOD连接失败: " . $e->getMessage();
        }
    }

    // 4. 创建数据表 (如果一切正常)
    if (empty($errors)) {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `videos` (
              `video_id` VARCHAR(32) NOT NULL,
              `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `cover_url` VARCHAR(512) DEFAULT NULL,
              `duration` FLOAT DEFAULT NULL,
              `creation_time` DATETIME DEFAULT NULL,
              `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`video_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $errors[] = "创建数据表失败: " . $e->getMessage();
        }
    }

    // 5. 生成配置文件 (如果一切正常)
    if (empty($errors)) {
        $configContent = "<?php\n\n// 该文件由 install.php 自动生成\n\nreturn [\n";
        $configContent .= "    'db_host' => '" . addslashes($db_host) . "',\n";
        $configContent .= "    'db_name' => '" . addslashes($db_name) . "',\n";
        $configContent .= "    'db_user' => '" . addslashes($db_user) . "',\n";
        $configContent .= "    'db_pass' => '" . addslashes($db_pass) . "',\n";
        $configContent .= "    'aliyun_ak_id' => '" . addslashes($aliyun_ak_id) . "',\n";
        $configContent .= "    'aliyun_ak_secret' => '" . addslashes($aliyun_ak_secret) . "',\n";
        $configContent .= "    'aliyun_region_id' => '" . addslashes($aliyun_region_id) . "',\n";
        $configContent .= "];\n";

        if (file_put_contents($configFile, $configContent)) {
            // 创建锁定文件
            touch($lockFile);
            $successMessage = "安装成功！配置文件已生成，数据表已创建。为了安全，请立即删除或重命名 install.php 文件。";
        } else {
            $errors[] = "写入配置文件 'config.credentials.php' 失败，请检查目录权限。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>网站安装程序</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .installer { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h1 { text-align: center; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { display: block; width: 100%; background: #007bff; color: #fff; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; text-align: center; }
        .success a { font-weight: bold; color: #0c5460; }
    </style>
</head>
<body>
    <div class="installer">
        <h1>网站安装程序</h1>

        <?php if (!empty($successMessage)): ?>
            <div class="success">
                <p><?php echo $successMessage; ?></p>
                <p><a href="index.php">访问您的网站首页</a></p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <h3>数据库配置</h3>
                <div class="form-group">
                    <label for="db_host">数据库地址</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="db_name">数据库名称</label>
                    <input type="text" id="db_name" name="db_name" required>
                </div>
                <div class="form-group">
                    <label for="db_user">数据库用户</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">数据库密码</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>

                <h3>阿里云VOD配置</h3>
                <div class="form-group">
                    <label for="aliyun_ak_id">AccessKey ID</label>
                    <input type="text" id="aliyun_ak_id" name="aliyun_ak_id" required>
                </div>
                <div class="form-group">
                    <label for="aliyun_ak_secret">AccessKey Secret</label>
                    <input type="password" id="aliyun_ak_secret" name="aliyun_ak_secret" required>
                </div>
                <div class="form-group">
                    <label for="aliyun_region_id">Region ID</label>
                    <input type="text" id="aliyun_region_id" name="aliyun_region_id" value="cn-shanghai" required>
                </div>

                <button type="submit" class="btn">立即安装</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
