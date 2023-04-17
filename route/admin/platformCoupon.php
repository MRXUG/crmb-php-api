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
        R::post('update/:id', 'PlatformCoupon/update'); # 创建平台优惠券
        R::get('selectCoupon', 'PlatformCoupon/selectCoupon'); # 选择优惠券
        R::get('merDetails/:amount', 'PlatformCoupon/merDetails'); # 优惠券明细
        R::get('lst', 'PlatformCoupon/lst'); # 优惠券列表
        R::post('createEstimateGoods', 'PlatformCoupon/createEstimateGoods'); # 平台优惠券创建时预估数据
        R::get('getEstimateGoodsResult/:resultCode', 'PlatformCoupon/getEstimateGoodsResult');

    })->prefix('admin.coupon.platform.')->option([
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
