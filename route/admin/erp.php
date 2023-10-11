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
        Route::get('createUrl', '/createUrl')->name('JuShuiTanCreateUrl')->option([
            '_alias' => '授权回调',
            '_auth' => false,
            '_form' => 'AuthorizeCallback',
        ]);

        Route::get('AuthorizeCallback', '/AuthorizeCallback')->name('JuShuiTanCallback');
        
        Route::any('deliverySyncCallback', '/deliverySyncCallback')->name('JuShuiTanDeliverySyncCallback');
        Route::any('cancelOrderSyncCallback', '/cancelOrderSyncCallback')->name('JuShuiTanCancelOrderSyncCallback');
        Route::any('stockSyncCallback', '/stockSyncCallback')->name('JuShuiTanStockSyncCallback');
        Route::any('shippingSyncCallback', '/shippingSyncCallback')->name('JuShuiTanShippingSyncCallback');

    })->prefix('admin.Erp.JuShuiTanController')->option([
        '_path' => '/Erp/JuShuiTan',
        '_auth' => true,
    ]);


})->middleware(AllowOriginMiddleware::class);
