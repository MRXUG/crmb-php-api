<?php

use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {

    // 商家数据看板
    Route::group('dashboard', function () {
        Route::post('getStatisticsData', '/getStatisticsData')->name('merchantStatisticsData')->option([
            '_alias' => '商家数据看板',
        ]);

    })->prefix('merchant.DataCenter.DataCenterDashboardController')->option([
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
