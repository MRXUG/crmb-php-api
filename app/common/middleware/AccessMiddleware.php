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
        return $next($request);
    }
}
