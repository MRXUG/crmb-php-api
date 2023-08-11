<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\middleware;

use app\Request;
use crmeb\interfaces\MiddlewareInterface;
use think\facade\Log;
use think\Response;

class AccessMiddleware implements MiddlewareInterface
{

    public function handle(Request $request, \Closure $next): Response
    {
        // 全局request_id
        $headers    = $request->header();
        $xRequestId = '';
        foreach ($headers as $key => $val) {
            if (strtolower($key) == 'x-request-id') {
                $xRequestId = $val[0];
                break;
            }
        }
        if (empty($xRequestId)) {
            $xRequestId = mini_unique_id();
        }
        $_SERVER['x_request_id'] = $xRequestId;
        $response                = $next($request);
        try {
            $responseData = json_decode($response->getContent(), true);

            if (is_null($responseData) || !is_array($responseData)) {
                $responseData = [$responseData];
            }

            Log::info('[API-Request]'.json_encode([
                'url'         => $request->url(),
                'method'      => $request->method(),
                'header'      => self::cutHeaderLog($headers),
                'params'      => $request->param(),
                //'return_code' => $response->getStatusCode(),
                // 'return_body' => $responseData['data'] ?? [],
            ]));

        } catch (\Exception $exception) {

            Log::debug('[API-Request]'.json_encode([
                'url'    => $request->url(),
                'method' => $request->method(),
                'header' => $request->header(),
                'params' => $request->param(),
            ]));
        }
        return $response;
    }

    public static function cutHeaderLog($header)
    {
        unset($header["host"]);
        unset($header["from-client"]);
        unset($header["from-path"]);
        unset($header["user-agent"]);
        unset($header["accept-encoding"]);
        unset($header["referer"]);
        unset($header["x-client-proto"]);
        unset($header["x-client-proto-ver"]);
        unset($header["x-forwarded-host"]);
        unset($header["x-forwarded-server"]);
        unset($header["app-name"]);
        unset($header["app-path"]);
        unset($header["content-type"]);
        unset($header["api-version"]);
        unset($header["cdp-code"]);
        unset($header["sec-fetch-dest"]);
        unset($header["sec-fetch-mode"]);
        unset($header["sec-fetch-site"]);
        unset($header["auth-sign"]);
        unset($header["auth-timestamp"]);
        unset($header["auth-client"]);
        unset($header["x-forwarded-port"]);
        unset($header["x-forwarded-proto"]);
        unset($header["x-stgw-time"]);
        return $header;
    }
}
