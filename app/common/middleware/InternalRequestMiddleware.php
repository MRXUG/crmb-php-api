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
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Route;
use think\Response;

class InternalRequestMiddleware extends BaseMiddleware
{

    public function before(Request $request)
    {
        $allowIp = ["127.0.0.1","192.168.0.2"];
        if (!in_array($request->ip(), $allowIp)){
            throw new ValidateException('没有权限访问内部接口');
        }
    }

    public function after(Response $response)
    {
    }
}
