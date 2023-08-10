<?php

use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {

    // 投诉单 微信支付投诉
    Route::group('complaint/wechatpay', function () {
        Route::get('list', '/list')->name('WechatPayComplaintList')->option([
            '_alias' => '微信支付投诉-列表',
        ]);
        Route::get('statistics', '/statistics')->name('WechatPayComplaintStatistics')->option([
            '_alias' => '微信支付投诉-统计信息',
        ]);
        Route::get('detail/:complaint_id', '/detail')->name('WechatPayComplaintDetail')->option([
            '_alias' => '微信支付投诉-详情',
        ]);
        Route::post('response/:complaint_id', '/response')->name('WechatPayComplaintResponse')->option([
            '_alias' => '微信支付投诉-回复客户',
        ]);
        Route::post('refund/:complaint_id', '/refund')->name('WechatPayComplaintRefund')->option([
            '_alias' => '微信支付投诉-退款审批',
        ]);
        Route::get('complete/:complaint_id', '/complete')->name('WechatPayComplaintComplete')->option([
            '_alias' => '微信支付投诉-处理完成',
        ]);
        Route::post('uploadImage', '/uploadImage')->name('WechatPayComplaintUploadImage')->option([
            '_alias' => '微信支付投诉-上传图片',
        ]);

    })->prefix('merchant.Complaint.WechatComplaintController')->option([
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
