<?php

use think\facade\Route;

//todo 请求IP的白名单
Route::group('api/internal/', function () {
    Route::get('trigger/autoTakeOrder', 'api.internal.AutoTakeOrder/httpTrigger');
});
