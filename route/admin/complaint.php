<?php

use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use think\facade\Route;

Route::group(function () {

    // 投诉单 微信支付投诉
    Route::group('complaint/wechatpay', function () {
        Route::get('list', '/list')->name('AdminWechatPayComplaintList')->option([
            '_alias' => '微信支付投诉-列表',
        ]);
        Route::get('statistics', '/statistics')->name('AdminWechatPayComplaintStatistics')->option([
            '_alias' => '微信支付投诉-统计信息',
        ]);
        Route::get('detail/:complaint_id', '/detail')->name('AdminWechatPayComplaintDetail')->option([
            '_alias' => '微信支付投诉-详情',
        ]);

    })->prefix('admin.Complaint.WechatComplaintController')->option([
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
