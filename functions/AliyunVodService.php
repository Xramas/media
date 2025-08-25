<?php
use AlibabaCloud\SDK\Vod\V20170321\Models;

/**
 * 一个通用的函数，用于调用阿里云VOD的各种API
 *
 * @param string $method 要调用的方法名 (例如 'getPlayInfo')
 * @param array $params API请求需要的参数 (例如 ['videoId' => 'xxxx'])
 * @return object|null 成功则返回API响应的body部分，失败返回null
 */
function callVodApi(string $method, array $params): ?object
{
    $vodClient = AliyunVodClientFactory::createClient();
    if (!$vodClient) {
        // 工厂类中已经记录了错误
        return null;
    }

    try {
        // 根据方法名动态构建请求类的完整名称
        $requestClass = 'AlibabaCloud\\SDK\\Vod\\V20170321\\Models\\' . ucfirst($method) . 'Request';
        
        // 检查请求类是否存在
        if (!class_exists($requestClass)) {
            error_log("Aliyun API Error: Request class {$requestClass} not found.");
            return null;
        }

        // 创建请求对象并传入参数
        $request = new $requestClass($params);
        
        // 动态调用客户端对应的方法
        $response = $vodClient->$method($request);
        
        // 直接返回响应的 body 部分
        return $response->body;

    } catch (Exception $e) {
        // 记录详细错误日志
        error_log("Aliyun API Error ({$method}): " . $e->getMessage());
        return null;
    }
}

/**
 * 根据视频ID获取视频播放信息 (重构后)
 */
function getPlayInfo(string $videoId): ?object
{
    return callVodApi('getPlayInfo', ['videoId' => $videoId]);
}

/**
 * 根据视频ID获取其源文件信息 (重构后)
 */
function getMezzanineInfo(string $videoId): ?string
{
    $responseBody = callVodApi('getMezzanineInfo', ['videoId' => $videoId]);
    
    // 从响应中提取并返回URL
    if (!empty($responseBody->mezzanine->fileURL)) {
        return $responseBody->mezzanine->fileURL;
    }

    return null;
}
