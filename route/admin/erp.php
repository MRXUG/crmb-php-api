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

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {


    //配置分类
    Route::group('erp/jushuitan', function () {
        Route::get('AuthorizeCallback', '/AuthorizeCallback')->name('JuShuiTanCallback')->option([
            '_alias' => '授权回调',
            '_auth' => false,
            '_form' => 'AuthorizeCallback',
        ]);
        Route::get('createUrl', '/createUrl')->name('JuShuiTanCreateUrl')->option([
            '_alias' => '授权回调',
            '_auth' => false,
            '_form' => 'AuthorizeCallback',
        ]);

        Route::any('deliverySyncCallback', '/deliverySyncCallback')->name('JuShuiTanDeliverySyncCallback')->option([
            '_alias' => '物流同步',
            '_auth' => false,
            '_form' => 'deliverySyncCallback',
        ]);
        Route::any('cancelOrderSyncCallback', '/cancelOrderSyncCallback')->name('JuShuiTanCancelOrderSyncCallback')->option([
            '_alias' => '取消订单',
            '_auth' => false,
            '_form' => 'cancelOrderSyncCallback',
        ]);
        Route::any('stockSyncCallback', '/stockSyncCallback')->name('JuShuiTanStockSyncCallback')->option([
            '_alias' => '库存同步',
            '_auth' => false,
            '_form' => 'stockSyncCallback',
        ]);
        Route::any('shippingSyncCallback', '/shippingSyncCallback')->name('JuShuiTanShippingSyncCallback')->option([
            '_alias' => '售后发货',
            '_auth' => false,
            '_form' => 'shippingSyncCallback',
        ]);

    })->prefix('admin.Erp.JuShuiTanController')->option([
        '_path' => '/Erp/JuShuiTan',
        '_auth' => true,
    ]);


})->middleware(AllowOriginMiddleware::class)
    ->middleware(LogMiddleware::class);
