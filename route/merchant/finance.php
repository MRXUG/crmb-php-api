<?php
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;
Route::group(function () {
    // 货款
    Route::group('goodsPayment', function () {
        // 列表
        Route::get('lst', '/lst')->name('merchantFinanceProfitList')->option([
            '_alias' => '货款列表',
        ]);
        // 导出
        Route::get('export', '/export')->name('merchantFinanceProfitListExport')->option([
            '_alias' => '货款列表导出',
        ]);
        // 货款和收益统计
        Route::get('stat', '/stat')->name('merchantFinanceStat')->option([
            '_alias' => '统计',
        ]);
    })->prefix('merchant.system.finance.GoodsPayment')->option([
        '_path' => 'merchant/finance',
        '_auth' => true,
    ]);


    // 收益 路由=group的name参数+get/post的rule参数
    Route::group('profit', function () {
        // 列表
        Route::get('lst', '/lst')->name('merchantFinanceProfitList')->option([
            '_alias' => '收益列表',
        ]);
        // 导出
        Route::get('export', '/export')->name('merchantFinanceProfitListExport')->option([
            '_alias' => '收益列表导出',
        ]);
        // 收益账户
    // prefix里是文件位置
    })->prefix('merchant.system.finance.Profit')->option([
        '_path' => 'merchant/finance',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
