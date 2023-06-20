<?php

use think\facade\Route;

Route::group('api/internal/', function () {
    Route::get('trigger/autoTakeOrder', 'api.internal.AutoTakeOrder/httpTrigger');
    Route::get('trigger/orderRefund', 'api.internal.OrderRefund/httpTrigger');
})->middleware(\app\common\middleware\InternalRequestMiddleware::class);
