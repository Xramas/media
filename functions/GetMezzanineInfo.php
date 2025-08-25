<?php
// 引入VOD SDK的GetMezzanineInfoRequest模型类
use AlibabaCloud\SDK\Vod\V20170321\Models\GetMezzanineInfoRequest;

/**
 * 根据视频ID获取其源文件信息（通常是上传的原始MP4文件）
 *
 * @param string $videoId 视频的唯一ID
 * @return string|null 返回源文件的URL，如果失败则返回 null
 */
function getMezzanineInfo(string $videoId): ?string
{
    // 从工厂类获取 VOD 客户端实例
    $vodClient = AliyunVodClientFactory::createClient();

    if (!$vodClient) {
        // 客户端创建失败，错误已在工厂类中记录
        return null;
    }

    try {
        // 创建 GetMezzanineInfo 的 API 请求对象
        $request = new GetMezzanineInfoRequest([
            'videoId' => $videoId
        ]);

        // 调用 API 并获取响应
        $response = $vodClient->getMezzanineInfo($request);
        
        // 检查并返回源文件的URL
        if (!empty($response->body->mezzanine->fileURL)) {
            return $response->body->mezzanine->fileURL;
        }

        return null;

    } catch (Exception $e) {
        error_log("Aliyun API Error (GetMezzanineInfo): " . $e->getMessage());
        return null;
    }
}
