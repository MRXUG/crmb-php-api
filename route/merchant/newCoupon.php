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
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {

    //优惠券
    Route::group('coupon', function () {
        Route::get('list', '/list')->name('systemStoreCouponLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('receiveList', '/receiveList')->name('systemStoreCouponLst')->option([
            '_alias' => '领取列表',
        ]);

        Route::get('show/:id', '/show')->name('systemStoreCouponLst')->option([
            '_alias' => '优惠券详情',
        ]);

        Route::get('failure/:id', '/failure')->name('systemStoreCouponLst')->option([
            '_alias' => '优惠券失效',
        ]);

        Route::get('cancelled/:id', '/cancelled')->name('systemStoreCouponLst')->option([
            '_alias' => '优惠券取消',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemStoreCouponLst')->option([
            '_alias' => '删除优惠券',
        ]);
    })->prefix('merchant.coupon.CouponStock')->option([
        '_path' => '/marketing/coupon/list',
        '_auth' => true,
    ]);


    Route::post('coupon/save', 'merchant.coupon.SaveCoupon/preSaveCreate')->option([
        '_alias' => '编辑优惠券',
        //'_path' => '/user/list',
        '_auth'  => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
