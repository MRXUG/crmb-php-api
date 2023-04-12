<?php

use think\facade\Route as R;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

# 平台优惠券
R::group(function () {
    R::group('platformCoupon', function () {

        R::post('create', 'PlatformCoupon/create'); # 创建平台优惠券

    })->prefix('admin.coupon.platform.')->option([
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
