<?php
// 引入VOD SDK的GetPlayInfoRequest模型类
use AlibabaCloud\SDK\Vod\V20170321\Models\GetPlayInfoRequest;

/**
 * 根据视频ID获取视频播放信息
 *
 * @param string $videoId 视频的唯一ID
 * @return object|null 返回包含播放信息的对象，如果失败则返回 null
 */
function getPlayInfo(string $videoId): ?object
{
    // 从工厂类获取 VOD 客户端实例
    $vodClient = AliyunVodClientFactory::createClient();

    if (!$vodClient) {
        // 客户端创建失败，错误已在工厂类中记录
        return null;
    }

    try {
        // 创建 GetPlayInfo 的 API 请求对象
        $request = new GetPlayInfoRequest([
            'videoId' => $videoId
        ]);

        // 调用 API 并获取响应
        $response = $vodClient->getPlayInfo($request);
        
        // 通常我们关心的是包含多个播放地址的 PlayInfoList
        if (!empty($response->body->playInfoList->playInfo)) {
            // 返回播放信息对象
            return $response->body;
        }

        return null;

    } catch (Exception $e) {
        error_log("Aliyun API Error (GetPlayInfo): " . $e->getMessage());
        return null;
    }
}
