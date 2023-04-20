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
        R::get('getEstimateGoodsResult/:resultCode', 'PlatformCoupon/getEstimateGoodsResult'); # 获取预估结果数据
        R::get('getCouponOne/:discountNum', 'PlatformCoupon/getCouponOne'); # 选择优惠券数据
        R::post('updateStatus/:id', 'PlatformCoupon/updateStatus'); # 修改优惠券状态
        R::get('getCouponStatusCount', 'PlatformCoupon/getCouponStatusCount'); # 获取优惠券状态个数
        R::get('getEditCouponProductInfo/:id', 'PlatformCoupon/getEditCouponProductInfo'); # 获取编辑优惠券商品基本信息
        R::get('getEditCouponProductList/:id', 'PlatformCoupon/getEditCouponProductList'); # 获取编辑优惠券商品列表
        R::post('updateProduct/:productId', 'PlatformCoupon/updateProduct'); # 编辑商品信息
        R::post('scopeCount', 'PlatformCoupon/scopeCount'); # 范围计数

    })->prefix('admin.coupon.platform.')->option([
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
